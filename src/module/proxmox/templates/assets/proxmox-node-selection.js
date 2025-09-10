/**
 * Proxmox Module - Node Selection
 * Verwaltung der Node-Auswahl und -Informationen
 */

// Node-Auswahl-Tab anzeigen
proxmoxModule.showNodeSelection = function() {
    this.hideAllTabs();
    const nodeSelectionTab = document.getElementById('node-selection-tab');
    if (nodeSelectionTab) {
        nodeSelectionTab.style.display = 'block';
    }
    this.loadNodes();
};

// Module Request Funktion
proxmoxModule.makeModuleRequest = async function(action, data = {}) {
    const formData = new FormData();
    formData.append('plugin', 'proxmox');
    formData.append('action', action);
    
    // Daten hinzufügen
    for (const key in data) {
        if (data[key] !== null && data[key] !== undefined) {
            formData.append(key, data[key]);
        }
    }
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        return result;
    } catch (error) {
        console.error('Module request error:', error);
        return { success: false, error: error.message };
    }
};

// RAM-Formatierung Hilfsfunktion
function formatRAM(bytes) {
    if (!bytes || bytes === 0) return '0 GB';
    const gb = bytes / 1024 / 1024 / 1024;
    return gb.toFixed(2) + ' GB';
}

// Notification Funktion
function showNotification(message, type = 'info') {
    // Erstelle eine schöne Notification
    const notification = document.createElement('div');
    notification.className = `alert alert-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto-remove nach 5 Sekunden
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 5000);
}

// Nodes laden
proxmoxModule.loadNodes = async function() {
    try {
        const result = await this.makeModuleRequest('get_proxmox_nodes');
        if (result.success && result.data) {
            this.displayNodes(result.data);
        } else {
            showNotification('Fehler beim Laden der Nodes: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Fehler beim Laden der Nodes: ' + error.message, 'error');
    }
};

// Nodes-Übersicht zurücksetzen
proxmoxModule.resetNodesOverview = function() {
    // Verstecke die Server-Liste Card
    const serverListCard = document.getElementById('server-list-card');
    if (serverListCard) {
        serverListCard.style.display = 'none';
    }
    
    // Lade die Nodes direkt ohne Spinner
    this.loadNodes();
};

// Nodes anzeigen
proxmoxModule.displayNodes = function(nodes) {
    // Verwende den nodes-overview-container, da die Nodes dort angezeigt werden
    const content = document.getElementById('nodes-overview-container');
    if (!content) return;

    if (!nodes.data || nodes.data.length === 0) {
        content.innerHTML = '<div class="text-center py-4"><p class="text-muted">Keine Nodes verfügbar</p></div>';
        return;
    }

    let html = '<div class="row">';
    
    nodes.data.forEach(node => {
        html += `
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="fas fa-server text-primary"></i> ${node.node}
                        </h6>
                        <span class="badge bg-${node.status === 'online' ? 'success' : 'danger'}">${node.status || 'unknown'}</span>
                    </div>
                    <div class="card-body">
                        <div class="row text-center mb-3">
                            <div class="col-6">
                                <small class="text-muted">CPU Kerne</small>
                                <div class="fw-bold">${node.maxcpu || 0}</div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">RAM</small>
                                <div class="fw-bold">${formatRAM(node.maxmem || 0)}</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">Laufzeit:</small><br>
                            <span class="fw-bold">${this.formatUptime(node.uptime || 0)}</span>
                        </div>
                        <div class="mb-3">
                            <small class="text-muted">VMs/Container:</small><br>
                            <span class="fw-bold">${node.vms || 0}</span>
                        </div>
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary" onclick="proxmoxModule.selectNode('${node.node}')">
                                <i class="fas fa-check"></i> Auswählen
                            </button>
                            <button class="btn btn-outline-info" onclick="proxmoxModule.loadNodeStatus('${node.node}')">
                                <i class="fas fa-info-circle"></i> Storage-Details
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    content.innerHTML = html;
};

// Node auswählen und VMs laden
proxmoxModule.selectNode = async function(nodeName) {
    try {
        // Zeige die Server-Liste Card
        const serverListCard = document.getElementById('server-list-card');
        if (serverListCard) {
            serverListCard.style.display = 'block';
        }
        
        // Lade VMs von diesem Node
        const result = await this.makeModuleRequest('get_vms', { node: nodeName });
        if (result.success && result.data) {
            // Aktualisiere die Server-Liste mit den gefilterten VMs
            const container = document.getElementById('server-list-container');
            if (container) {
                // Rendere die Server-Liste direkt
                if (typeof proxmoxModule.renderServerList === 'function') {
                    proxmoxModule.renderServerList(result.data);
                } else {
                    // Fallback: Lade die komplette Server-Liste neu
                    proxmoxModule.loadServerList(nodeName);
                }
            }
            
            showNotification(`VMs und Container von Node "${nodeName}" geladen`, 'success');
        } else {
            showNotification('Fehler beim Laden der VMs: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Fehler beim Laden der VMs: ' + error.message, 'error');
    }
};

// Node-Status laden und anzeigen
proxmoxModule.loadNodeStatus = async function(nodeName) {
    try {
        // Lade Storage-Liste anstatt Node-Status
        const result = await this.makeModuleRequest('get_storage_list', { node: nodeName });
        if (result.success && result.data) {
            this.displayStorageDetails(result.data, nodeName);
        } else {
            showNotification('Fehler beim Laden der Storage-Details: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Fehler beim Laden der Storage-Details: ' + error.message, 'error');
    }
};

// Storage-Details anzeigen
proxmoxModule.displayStorageDetails = function(storageData, nodeName) {
    // Verwende den Nodes-Übersicht Container
    const content = document.getElementById('nodes-overview-container');
    if (!content) return;

    if (!storageData.data || storageData.data.length === 0) {
        content.innerHTML = `
            <div class="text-center py-4">
                <p class="text-muted">Keine Storage-Informationen für Node "${nodeName}" verfügbar</p>
            </div>
        `;
        return;
    }

    let html = `
        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-hdd"></i> Storage-Details für Node "${nodeName}"</h4>
                <button class="btn btn-outline-secondary btn-sm" onclick="proxmoxModule.resetNodesOverview()">
                    <i class="fas fa-arrow-left"></i> Zurück zu Nodes
                </button>
            </div>
        </div>
        <div class="row">
    `;

    storageData.data.forEach(storage => {
        const usedPercent = Math.round((storage.used_fraction || 0) * 100);
        const usedGB = Math.round((storage.used || 0) / 1024 / 1024 / 1024);
        const totalGB = Math.round((storage.total || 0) / 1024 / 1024 / 1024);
        const availGB = Math.round((storage.avail || 0) / 1024 / 1024 / 1024);
        
        const statusClass = storage.active ? 'success' : 'danger';
        const statusText = storage.active ? 'Aktiv' : 'Inaktiv';
        
        html += `
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="fas fa-hdd text-primary"></i> ${storage.storage}
                        </h6>
                        <div>
                            <span class="badge bg-${statusClass}">${statusText}</span>
                            <span class="badge bg-${storage.enabled ? 'success' : 'secondary'}">${storage.enabled ? 'Enabled' : 'Disabled'}</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-muted">Speicherplatz</small>
                                <small class="text-muted">${usedGB} GB / ${totalGB} GB (${usedPercent}%)</small>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-${usedPercent > 80 ? 'danger' : usedPercent > 60 ? 'warning' : 'success'}" 
                                     style="width: ${usedPercent}%"></div>
                            </div>
                        </div>
                        
                        <div class="row text-center mb-3">
                            <div class="col-4">
                                <small class="text-muted">Verwendet</small>
                                <div class="fw-bold">${usedGB} GB</div>
                            </div>
                            <div class="col-4">
                                <small class="text-muted">Verfügbar</small>
                                <div class="fw-bold">${availGB} GB</div>
                            </div>
                            <div class="col-4">
                                <small class="text-muted">Typ</small>
                                <div class="fw-bold">${storage.type || 'N/A'}</div>
                            </div>
                        </div>
                        
                        <div class="mb-2">
                            <small class="text-muted">Inhalt:</small><br>
                            <span class="badge bg-info me-1">${(storage.content || '').replace(/,/g, '</span><span class="badge bg-info me-1">')}</span>
                        </div>
                        
                        <div class="mb-2">
                            <small class="text-muted">Shared:</small> 
                            <span class="badge bg-${storage.shared ? 'warning' : 'secondary'}">${storage.shared ? 'Ja' : 'Nein'}</span>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-info btn-sm" onclick="proxmoxModule.loadStorageContent('${nodeName}', '${storage.storage}')">
                                <i class="fas fa-folder-open"></i> Inhalt anzeigen
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });

    html += `
        </div>
    `;

    content.innerHTML = html;
};

// Storage-Inhalt laden und anzeigen
proxmoxModule.loadStorageContent = async function(nodeName, storageName) {
    try {
        const result = await this.makeModuleRequest('get_storage_content', { 
            node: nodeName, 
            storage: storageName 
        });
        if (result.success && result.data) {
            this.displayStorageContent(result.data, nodeName, storageName);
        } else {
            showNotification('Fehler beim Laden des Storage-Inhalts: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Fehler beim Laden des Storage-Inhalts: ' + error.message, 'error');
    }
};

// Storage-Inhalt anzeigen
proxmoxModule.displayStorageContent = function(contentData, nodeName, storageName) {
    // Verwende den Nodes-Übersicht Container
    const content = document.getElementById('nodes-overview-container');
    if (!content) return;

    if (!contentData.data || contentData.data.length === 0) {
        content.innerHTML = `
            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><i class="fas fa-folder-open"></i> Inhalt von Storage "${storageName}"</h4>
                    <button class="btn btn-outline-secondary btn-sm" onclick="proxmoxModule.loadNodeStatus('${nodeName}')">
                        <i class="fas fa-arrow-left"></i> Zurück zu Storage-Liste
                    </button>
                </div>
            </div>
            <div class="text-center py-4">
                <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                <h5>Kein Inhalt verfügbar</h5>
                <p class="text-muted">Storage "${storageName}" auf Node "${nodeName}" ist leer</p>
            </div>
        `;
        return;
    }

    let html = `
        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-folder-open"></i> Inhalt von Storage "${storageName}"</h4>
                <button class="btn btn-outline-secondary btn-sm" onclick="proxmoxModule.loadNodeStatus('${nodeName}')">
                    <i class="fas fa-arrow-left"></i> Zurück zu Storage-Liste
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>VM ID</th>
                        <th>Volumen ID</th>
                        <th>Format</th>
                        <th>Inhalt</th>
                        <th>Größe</th>
                        <th>Verwendet</th>
                        <th>Erstellt</th>
                    </tr>
                </thead>
                <tbody>
    `;

    contentData.data.forEach(item => {
        const sizeGB = Math.round((item.size || 0) / 1024 / 1024 / 1024);
        const usedGB = Math.round((item.used || 0) / 1024 / 1024 / 1024);
        const createdDate = item.ctime ? new Date(item.ctime * 1000).toLocaleString() : 'N/A';
        
        html += `
            <tr>
                <td>
                    ${item.vmid ? `<span class="badge bg-primary">${item.vmid}</span>` : '-'}
                </td>
                <td>
                    <code class="small">${item.volid || 'N/A'}</code>
                </td>
                <td>
                    <span class="badge bg-info">${item.format || 'N/A'}</span>
                </td>
                <td>
                    <span class="badge bg-secondary">${item.content || 'N/A'}</span>
                </td>
                <td>
                    <strong>${sizeGB} GB</strong>
                </td>
                <td>
                    <strong>${usedGB} GB</strong>
                </td>
                <td>
                    <small class="text-muted">${createdDate}</small>
                </td>
            </tr>
        `;
    });

    html += `
                </tbody>
            </table>
        </div>
    `;

    content.innerHTML = html;
};

// Detaillierte Node-Status-Informationen anzeigen
proxmoxModule.displayNodeStatus = function(statusData, nodeName) {
    const content = document.getElementById('nodes-overview-container');
    if (!content) return;

    const status = statusData.data || statusData;
    
    // Format bytes to human readable
    const formatBytes = (bytes) => {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    // Format uptime
    const formatUptime = (seconds) => {
        const days = Math.floor(seconds / 86400);
        const hours = Math.floor((seconds % 86400) / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        return `${days}d ${hours}h ${minutes}m`;
    };

    content.innerHTML = `
        <div class="row">
            <div class="col-12 mb-3">
                <button class="btn btn-outline-secondary" onclick="proxmoxModule.loadNodes()">
                    <i class="fas fa-arrow-left"></i> Zurück zur Node-Liste
                </button>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-server"></i> Node: ${nodeName}</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>System-Informationen</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>Proxmox Version:</strong></td><td>${status.pveversion || 'N/A'}</td></tr>
                                    <tr><td><strong>Kernel:</strong></td><td>${status.kversion || 'N/A'}</td></tr>
                                    <tr><td><strong>Uptime:</strong></td><td>${formatUptime(status.uptime || 0)}</td></tr>
                                    <tr><td><strong>CPU Auslastung:</strong></td><td>${((status.cpu || 0) * 100).toFixed(2)}%</td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>Hardware</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>CPU Model:</strong></td><td>${status.cpuinfo?.model || 'N/A'}</td></tr>
                                    <tr><td><strong>CPU Kerne:</strong></td><td>${status.cpuinfo?.cores || 'N/A'}</td></tr>
                                    <tr><td><strong>CPU Sockets:</strong></td><td>${status.cpuinfo?.sockets || 'N/A'}</td></tr>
                                    <tr><td><strong>CPU MHz:</strong></td><td>${status.cpuinfo?.mhz || 'N/A'}</td></tr>
                                </table>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <h6>Arbeitsspeicher</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>Gesamt:</strong></td><td>${formatBytes(status.memory?.total || 0)}</td></tr>
                                    <tr><td><strong>Verwendet:</strong></td><td>${formatBytes(status.memory?.used || 0)}</td></tr>
                                    <tr><td><strong>Frei:</strong></td><td>${formatBytes(status.memory?.free || 0)}</td></tr>
                                    <tr><td><strong>KSM Shared:</strong></td><td>${formatBytes(status.ksm?.shared || 0)}</td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>Festplatte</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>Gesamt:</strong></td><td>${formatBytes(status.rootfs?.total || 0)}</td></tr>
                                    <tr><td><strong>Verwendet:</strong></td><td>${formatBytes(status.rootfs?.used || 0)}</td></tr>
                                    <tr><td><strong>Verfügbar:</strong></td><td>${formatBytes(status.rootfs?.avail || 0)}</td></tr>
                                    <tr><td><strong>Frei:</strong></td><td>${formatBytes(status.rootfs?.free || 0)}</td></tr>
                                </table>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <h6>System-Load</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>Load 1min:</strong></td><td>${status.loadavg?.[0] || 'N/A'}</td></tr>
                                    <tr><td><strong>Load 5min:</strong></td><td>${status.loadavg?.[1] || 'N/A'}</td></tr>
                                    <tr><td><strong>Load 15min:</strong></td><td>${status.loadavg?.[2] || 'N/A'}</td></tr>
                                    <tr><td><strong>Wait I/O:</strong></td><td>${(status.wait || 0).toFixed(4)}s</td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>Swap</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>Gesamt:</strong></td><td>${formatBytes(status.swap?.total || 0)}</td></tr>
                                    <tr><td><strong>Verwendet:</strong></td><td>${formatBytes(status.swap?.used || 0)}</td></tr>
                                    <tr><td><strong>Frei:</strong></td><td>${formatBytes(status.swap?.free || 0)}</td></tr>
                                </table>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button class="btn btn-primary" onclick="proxmoxModule.selectNode('${nodeName}')">
                                        <i class="fas fa-check"></i> VMs/Container von diesem Node laden
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
};
