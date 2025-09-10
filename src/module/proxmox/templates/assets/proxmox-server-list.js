/**
 * Proxmox Module - Server List Management
 * Verwaltung der Server-Liste und deren Darstellung
 */

// AJAX-Request-Funktion für das CMS
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

// Server-Liste laden
proxmoxModule.loadServerList = async function(node = null) {
    // Lade Nodes-Übersicht nur wenn keine Node spezifiziert ist
    if (!node) {
        await this.loadNodesOverview();
    }
    
    const container = document.getElementById('server-list-container');
    
    if (!container) {
        console.error('Container server-list-container not found!');
        return;
    }
    
    container.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">Laden...</span>
            </div>
            <p class="mt-2">Laden...</p>
        </div>
    `;
    
    try {
        const data = node ? { node } : {};
        const result = await this.makeModuleRequest('get_vms', data);
        
        if (result.success) {
            this.renderServerList(result.data);
        } else {
            this.showError(result.error || 'Fehler beim Laden der VMs');
        }
    } catch (error) {
        this.showError('Netzwerkfehler: ' + error.message);
    }
};

// Nodes-Übersicht laden
proxmoxModule.loadNodesOverview = async function() {
    try {
        const result = await this.makeModuleRequest('get_proxmox_nodes');
        if (result.success && result.data) {
            this.displayNodesOverview(result.data);
        } else {
            console.error('Fehler beim Laden der Nodes:', result.error);
        }
    } catch (error) {
        console.error('Fehler beim Laden der Nodes:', error);
    }
};

// Nodes-Übersicht anzeigen
proxmoxModule.displayNodesOverview = function(nodes) {
    const container = document.getElementById('nodes-overview-container');
    if (!container) return;

    if (!nodes.data || nodes.data.length === 0) {
        container.innerHTML = '<div class="text-center py-4"><p class="text-muted">Keine Nodes verfügbar</p></div>';
        return;
    }

    let html = '<div class="row">';
    
    nodes.data.forEach(node => {
        html += `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="fas fa-server text-primary"></i> ${node.node}
                        </h6>
                        <span class="badge bg-${node.status === 'online' ? 'success' : 'danger'}">${node.status || 'unknown'}</span>
                    </div>
                    <div class="card-body">
                        <div class="row text-center mb-2">
                            <div class="col-6">
                                <small class="text-muted">CPU Kerne</small>
                                <div class="fw-bold">${node.maxcpu || 0}</div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">RAM</small>
                                <div class="fw-bold">${this.formatRAM(node.maxmem || 0)}</div>
                            </div>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">Laufzeit:</small><br>
                            <span class="fw-bold">${this.formatUptime(node.uptime || 0)}</span>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">VMs/Container:</small><br>
                            <span class="fw-bold">${node.vms || 0}</span>
                        </div>
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary btn-sm" onclick="proxmoxModule.selectNode('${node.node}')">
                                <i class="fas fa-check"></i> Auswählen
                            </button>
                            <button class="btn btn-outline-info btn-sm" onclick="proxmoxModule.loadNodeStatus('${node.node}')">
                                <i class="fas fa-info-circle"></i> Details
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
};

// RAM-Formatierung Hilfsfunktion
proxmoxModule.formatRAM = function(bytes) {
    if (!bytes || bytes === 0) return '0 GB';
    const gb = bytes / 1024 / 1024 / 1024;
    return gb.toFixed(2) + ' GB';
};

// Server-Liste rendern
proxmoxModule.renderServerList = function(servers) {
    const container = document.getElementById('server-list-container');
    
    if (!servers || servers.length === 0) {
        container.innerHTML = `
            <div class="text-center py-5">
                <i class="fas fa-server fa-3x text-muted mb-3"></i>
                <h5>Wählen Sie einen Node aus</h5>
                <p class="text-muted">Klicken Sie auf "Auswählen" bei einem Node, um VMs und Container anzuzeigen</p>
            </div>
        `;
        return;
    }
    
    let html = '<div class="row">';
    
    servers.forEach(server => {
        const statusClass = this.getStatusClass(server.status);
        const statusText = this.getStatusText(server.status);
        
        html += `
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            ${server.type === 'lxc' ? '<i class="fas fa-cube text-info"></i>' : '<i class="fas fa-server text-primary"></i>'} 
                            ${server.name || (server.type === 'lxc' ? 'Container ' : 'VM ') + server.vmid}
                        </h6>
                        <div>
                            <span class="badge ${server.type === 'lxc' ? 'bg-info' : 'bg-secondary'} me-1">${server.type === 'lxc' ? 'LXC' : 'QEMU'}</span>
                        <span class="badge ${statusClass}">${statusText}</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-4">
                                <small class="text-muted">ID</small>
                                <div class="fw-bold">${server.vmid}</div>
                            </div>
                            <div class="col-4">
                                <small class="text-muted">CPU Kerne</small>
                                <div class="fw-bold">${server.cores || server.cpus || 0}</div>
                            </div>
                            <div class="col-4">
                                <small class="text-muted">RAM</small>
                                <div class="fw-bold">${this.formatRAM(server.memory || 0)}</div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <small class="text-muted">Node:</small> ${server.node || 'N/A'}<br>
                            <small class="text-muted">Laufzeit:</small> ${this.formatUptime(server.uptime)}
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="btn-group w-100" role="group">
                            <button class="btn btn-outline-info btn-sm" onclick="proxmoxModule.showServerDetails(${server.vmid}, '${server.node}', '${server.type || 'qemu'}')">
                                <i class="fas fa-cog"></i>Verwaltung
                            </button>
                        </div>
                        <div class="btn-group w-100 mt-2" role="group">
                            <button class="btn btn-outline-success btn-sm" onclick="proxmoxModule.startServer(${server.vmid}, '${server.node}', '${server.type || 'qemu'}')" ${server.status === 'running' ? 'disabled' : ''}>
                                <i class="fas fa-play"></i>Starten
                            </button>
                            <button class="btn btn-outline-warning btn-sm" onclick="proxmoxModule.stopServer(${server.vmid}, '${server.node}', '${server.type || 'qemu'}')" ${server.status !== 'running' ? 'disabled' : ''}>
                                <i class="fas fa-stop"></i>Stoppen
                            </button>
                            <button class="btn btn-outline-info btn-sm" onclick="proxmoxModule.restartServer(${server.vmid}, '${server.node}', '${server.type || 'qemu'}')" ${server.status !== 'running' ? 'disabled' : ''}>
                                <i class="fas fa-redo"></i>Neustart
                            </button>
                        </div>
                        <div class="btn-group w-100 mt-2" role="group">
                            <button class="btn btn-outline-warning btn-sm" onclick="proxmoxModule.resetServer(${server.vmid}, '${server.node}', '${server.type || 'qemu'}')" ${server.status !== 'running' || (server.type || 'qemu') === 'lxc' ? 'disabled' : ''}>
                                <i class="fas fa-undo"></i>Reset
                            </button>
                            <button class="btn btn-outline-success btn-sm" onclick="proxmoxModule.resumeServer(${server.vmid}, '${server.node}', '${server.type || 'qemu'}')" ${server.status === 'running' ? 'disabled' : ''}>
                                <i class="fas fa-play"></i>Fortsetzen
                            </button>
                            <button class="btn btn-outline-danger btn-sm" onclick="proxmoxModule.deleteServer(${server.vmid}, '${server.node}', '${server.type || 'qemu'}')">
                                <i class="fas fa-trash"></i>Löschen
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
};

// Status-Klasse ermitteln
proxmoxModule.getStatusClass = function(status) {
    switch(status) {
        case 'running': return 'bg-success';
        case 'stopped': return 'bg-secondary';
        case 'paused': return 'bg-warning';
        case 'suspended': return 'bg-info';
        default: return 'bg-dark';
    }
};

// Status-Text ermitteln
proxmoxModule.getStatusText = function(status) {
    switch(status) {
        case 'running': return 'Läuft';
        case 'stopped': return 'Gestoppt';
        case 'paused': return 'Pausiert';
        case 'suspended': return 'Suspendiert';
        default: return 'Unbekannt';
    }
};

// Uptime formatieren
proxmoxModule.formatUptime = function(uptime) {
    if (!uptime) return 'Nie';
    
    const days = Math.floor(uptime / 86400);
    const hours = Math.floor((uptime % 86400) / 3600);
    const minutes = Math.floor((uptime % 3600) / 60);
    
    let result = '';
    if (days > 0) result += days + ' Tage ';
    if (hours > 0) result += hours + ' Stunden ';
    if (minutes > 0) result += minutes + ' Minuten';
    
    return result || 'Nie';
};

// Fehler anzeigen
proxmoxModule.showError = function(message) {
    const container = document.getElementById('server-list-container');
    container.innerHTML = `
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            ${message}
        </div>
    `;
};

// Server-Bearbeitungsseite öffnen
proxmoxModule.editServer = function(vmid, node) {
    // Direkte URL-Weiterleitung zur Bearbeitungsseite
    const newUrl = `?option=modules&mod=proxmox&vm=${vmid}&edit=1&node=${encodeURIComponent(node)}`;
    window.location.href = newUrl;
};

// Zurück zur Server-Liste
proxmoxModule.backToServerList = function() {
    // Direkte URL-Weiterleitung zur Server-Liste
    const newUrl = '?option=modules&mod=proxmox';
    window.location.href = newUrl;
};


// Server-Details für Bearbeitungsseite laden
proxmoxModule.loadServerDetails = async function(vmid, node) {
    try {
        const result = await this.makeModuleRequest('get_vm_config', { vmid, node });
        
        if (result.success) {
            const config = result.data;
            document.getElementById('edit_vm_name').value = config.name || '';
            document.getElementById('edit_vm_description').value = config.description || '';
            document.getElementById('edit_vm_memory').value = config.memory || 0;
            document.getElementById('edit_vm_cores').value = config.cores || 0;
            document.getElementById('edit_vm_disk').value = config.disk_size || 0;
            document.getElementById('edit_vm_ip').value = config.ip_address || '';
            document.getElementById('edit_vm_mac').value = config.mac_address || '';
            
            // Aktualisiere die Info-Anzeige
            document.getElementById('memory-info').textContent = Math.round((config.memory || 0) / 1024) + ' MB';
            document.getElementById('cores-info').textContent = config.cores || 0;
            document.getElementById('last-update').textContent = new Date().toLocaleString('de-DE');
        } else {
            showNotification('Fehler beim Laden der Server-Details: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Fehler beim Laden der Server-Details: ' + error.message, 'error');
    }
};

// Server-Status prüfen
proxmoxModule.checkServerStatus = async function(vmid, node) {
    try {
        const result = await this.makeModuleRequest('get_vm_status', { vmid, node });
        
        if (result.success) {
            const status = result.data.status || 'stopped';
            const uptime = result.data.uptime || 0;
            
            // Status-Badge aktualisieren
            const statusIndicator = document.getElementById('status-indicator');
            const statusClass = this.getStatusClass(status);
            const statusText = this.getStatusText(status);
            
            statusIndicator.className = `badge ${statusClass}`;
            statusIndicator.innerHTML = `<i class="fas fa-circle"></i> ${statusText}`;
            
            // Uptime aktualisieren
            document.getElementById('uptime-info').textContent = this.formatUptime(uptime);
            
            // Button-Status aktualisieren
            const startBtn = document.getElementById('start-server-btn');
            const stopBtn = document.getElementById('stop-server-btn');
            const restartBtn = document.getElementById('restart-server-btn');
            
            if (status === 'running') {
                startBtn.disabled = true;
                stopBtn.disabled = false;
                restartBtn.disabled = false;
            } else {
                startBtn.disabled = false;
                stopBtn.disabled = true;
                restartBtn.disabled = true;
            }
        } else {
            document.getElementById('status-indicator').innerHTML = '<i class="fas fa-exclamation-triangle"></i> Status unbekannt';
        }
    } catch (error) {
        document.getElementById('status-indicator').innerHTML = '<i class="fas fa-exclamation-triangle"></i> Fehler beim Status-Check';
    }
};

// Diese Funktionen sind jetzt in proxmox-confirmation.js definiert

// Erweiterte Server-Details anzeigen
proxmoxModule.showServerDetails = async function(vmid, node, type = 'qemu') {
    this.hideAllTabs();
    const detailsTab = document.getElementById('server-details-tab');
    if (detailsTab) {
        detailsTab.style.display = 'block';
    }

    try {
        const [statusResult, configResult] = await Promise.all([
            this.makeModuleRequest('get_vm_status', { vmid, node, type }),
            this.makeModuleRequest('get_vm_config', { vmid, node, type })
        ]);
        
        if (statusResult.success && statusResult.data && configResult.success && configResult.data) {
            this.displayDetailedServerInfo(statusResult.data, configResult.data, vmid, node, type);
        } else {
            showNotification('Fehler beim Laden der Server-Details: ' + (statusResult.error || configResult.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Fehler beim Laden der Server-Details: ' + error.message, 'error');
    }
};

// Detaillierte Server-Informationen anzeigen
proxmoxModule.displayDetailedServerInfo = function(statusData, configData, vmid, node, type = 'qemu') {
    const content = document.getElementById('server-details-content');
    if (!content) return;

    const server = statusData.data || statusData;
    const config = configData.data || configData;
    
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

    // Format CPU percentage
    const formatCPU = (cpu) => {
        return (cpu * 100).toFixed(2) + '%';
    };

    content.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-server"></i> Grundinformationen</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr><td><strong>VM ID:</strong></td><td>${vmid}</td></tr>
                            <tr><td><strong>Name:</strong></td><td>${server.name || 'N/A'}</td></tr>
                            <tr><td><strong>Status:</strong></td><td><span class="badge bg-${server.status === 'running' ? 'success' : 'danger'}">${server.status || 'N/A'}</span></td></tr>
                            <tr><td><strong>Node:</strong></td><td>${node}</td></tr>
                            <tr><td><strong>Laufzeit:</strong></td><td>${formatUptime(server.uptime || 0)}</td></tr>
                            <tr><td><strong>PID:</strong></td><td>${server.pid || 'N/A'}</td></tr>
                        </table>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-microchip"></i> CPU & RAM</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr><td><strong>CPU Kerne:</strong></td><td>${server.cpus || 'N/A'}</td></tr>
                            <tr><td><strong>CPU Auslastung:</strong></td><td>${formatCPU(server.cpu || 0)}</td></tr>
                            <tr><td><strong>RAM (Aktuell):</strong></td><td>${formatBytes(server.mem || 0)}</td></tr>
                            <tr><td><strong>RAM (Maximal):</strong></td><td>${formatBytes(server.maxmem || 0)}</td></tr>
                            <tr><td><strong>Balloon RAM:</strong></td><td>${formatBytes(server.balloon || 0)}</td></tr>
                            <tr><td><strong>Freier RAM:</strong></td><td>${formatBytes(server.freemem || 0)}</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-hdd"></i> Festplatte</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr><td><strong>Festplatte (Aktuell):</strong></td><td>${formatBytes(server.disk || 0)}</td></tr>
                            <tr><td><strong>Festplatte (Maximal):</strong></td><td>${formatBytes(server.maxdisk || 0)}</td></tr>
                            <tr><td><strong>Gelesen:</strong></td><td>${formatBytes(server.diskread || 0)}</td></tr>
                            <tr><td><strong>Geschrieben:</strong></td><td>${formatBytes(server.diskwrite || 0)}</td></tr>
                        </table>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-network-wired"></i> Netzwerk</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr><td><strong>Eingehend:</strong></td><td>${formatBytes(server.netin || 0)}</td></tr>
                            <tr><td><strong>Ausgehend:</strong></td><td>${formatBytes(server.netout || 0)}</td></tr>
                        </table>
                        ${server.nics ? Object.keys(server.nics).map(nic => `
                            <h6 class="mt-3">${nic}</h6>
                            <table class="table table-sm">
                                <tr><td><strong>Eingehend:</strong></td><td>${formatBytes(server.nics[nic].netin || 0)}</td></tr>
                                <tr><td><strong>Ausgehend:</strong></td><td>${formatBytes(server.nics[nic].netout || 0)}</td></tr>
                            </table>
                        `).join('') : ''}
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle"></i> Balloon-Informationen</h5>
                    </div>
                    <div class="card-body">
                        ${server.ballooninfo ? `
                            <table class="table table-sm">
                                <tr><td><strong>Freier Speicher:</strong></td><td>${formatBytes(server.ballooninfo.free_mem || 0)}</td></tr>
                                <tr><td><strong>Maximaler Speicher:</strong></td><td>${formatBytes(server.ballooninfo.max_mem || 0)}</td></tr>
                                <tr><td><strong>Gesamter Speicher:</strong></td><td>${formatBytes(server.ballooninfo.total_mem || 0)}</td></tr>
                                <tr><td><strong>Major Page Faults:</strong></td><td>${(server.ballooninfo.major_page_faults || 0).toLocaleString()}</td></tr>
                                <tr><td><strong>Minor Page Faults:</strong></td><td>${(server.ballooninfo.minor_page_faults || 0).toLocaleString()}</td></tr>
                                <tr><td><strong>Swapped In:</strong></td><td>${formatBytes(server.ballooninfo.mem_swapped_in || 0)}</td></tr>
                                <tr><td><strong>Swapped Out:</strong></td><td>${formatBytes(server.ballooninfo.mem_swapped_out || 0)}</td></tr>
                            </table>
                        ` : '<p class="text-muted">Keine Balloon-Informationen verfügbar</p>'}
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-cogs"></i> Technische Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr><td><strong>QEMU Version:</strong></td><td>${server['running-qemu'] || 'N/A'}</td></tr>
                                    <tr><td><strong>Maschine:</strong></td><td>${server['running-machine'] || 'N/A'}</td></tr>
                                    <tr><td><strong>QMP Status:</strong></td><td><span class="badge bg-${server.qmpstatus === 'running' ? 'success' : 'warning'}">${server.qmpstatus || 'N/A'}</span></td></tr>
                                    <tr><td><strong>Agent:</strong></td><td><span class="badge bg-${server.agent ? 'success' : 'secondary'}">${server.agent ? 'Aktiv' : 'Inaktiv'}</span></td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr><td><strong>HA Managed:</strong></td><td><span class="badge bg-${server.ha?.managed ? 'success' : 'secondary'}">${server.ha?.managed ? 'Ja' : 'Nein'}</span></td></tr>
                                    <tr><td><strong>Proxmox Support:</strong></td><td><span class="badge bg-success">Verfügbar</span></td></tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-cog"></i> VM-Konfiguration</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Grundkonfiguration</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>Name:</strong></td><td>${config.name || 'N/A'}</td></tr>
                                    <tr><td><strong>OS Typ:</strong></td><td>${config.ostype || 'N/A'}</td></tr>
                                    <tr><td><strong>CPU:</strong></td><td>${config.cpu || 'N/A'}</td></tr>
                                    <tr><td><strong>CPU Kerne:</strong></td><td>${config.cores || 'N/A'}</td></tr>
                                    <tr><td><strong>CPU Sockets:</strong></td><td>${config.sockets || 'N/A'}</td></tr>
                                    <tr><td><strong>RAM:</strong></td><td>${this.formatRAM(config.memory || 0)}</td></tr>
                                    <tr><td><strong>BIOS:</strong></td><td>${config.bios || 'N/A'}</td></tr>
                                    <tr><td><strong>Boot Order:</strong></td><td>${config.boot || 'N/A'}</td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>System-Einstellungen</h6>
                                <table class="table table-sm">
                                    <tr><td><strong>Auto Start:</strong></td><td><span class="badge bg-${config.onboot ? 'success' : 'secondary'}">${config.onboot ? 'Aktiviert' : 'Deaktiviert'}</span></td></tr>
                                    <tr><td><strong>Agent:</strong></td><td><span class="badge bg-${config.agent ? 'success' : 'secondary'}">${config.agent ? 'Aktiviert' : 'Deaktiviert'}</span></td></tr>
                                    <tr><td><strong>VM Gen ID:</strong></td><td><code class="small">${config.vmgenid || 'N/A'}</code></td></tr>
                                    <tr><td><strong>SMBIOS UUID:</strong></td><td><code class="small">${config.smbios1 ? config.smbios1.split('=')[1] : 'N/A'}</code></td></tr>
                                    <tr><td><strong>Digest:</strong></td><td><code class="small">${config.digest || 'N/A'}</code></td></tr>
                                    <tr><td><strong>Erstellt:</strong></td><td>${config.meta ? new Date(parseInt(config.meta.split('ctime=')[1]) * 1000).toLocaleString() : 'N/A'}</td></tr>
                                </table>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6>Speicher & Netzwerk</h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-muted">Festplatten</h6>
                                        ${Object.keys(config).filter(key => key.startsWith('scsi') || key.startsWith('sata') || key.startsWith('ide')).map(key => `
                                            <div class="mb-2">
                                                <strong>${key.toUpperCase()}:</strong><br>
                                                <code class="small">${config[key]}</code>
                                            </div>
                                        `).join('')}
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-muted">Netzwerk</h6>
                                        ${Object.keys(config).filter(key => key.startsWith('net')).map(key => `
                                            <div class="mb-2">
                                                <strong>${key.toUpperCase()}:</strong><br>
                                                <code class="small">${config[key]}</code>
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-tools"></i> Aktionen</h5>
                    </div>
                    <div class="card-body">
                        <div class="btn-group mb-2" role="group">
                            <button class="btn btn-outline-success" onclick="proxmoxModule.startServer(${vmid}, '${node}', '${type}')" ${server.status === 'running' ? 'disabled' : ''}>
                                <i class="fas fa-play"></i> Starten
                            </button>
                            <button class="btn btn-outline-warning" onclick="proxmoxModule.stopServer(${vmid}, '${node}', '${type}')" ${server.status !== 'running' ? 'disabled' : ''}>
                                <i class="fas fa-stop"></i> Stoppen
                            </button>
                            <button class="btn btn-outline-info" onclick="proxmoxModule.restartServer(${vmid}, '${node}', '${type}')" ${server.status !== 'running' ? 'disabled' : ''}>
                                <i class="fas fa-redo"></i> Neustart
                            </button>
                        </div>
                        <div class="btn-group mb-2" role="group">
                            <button class="btn btn-outline-warning" onclick="proxmoxModule.resetServer(${vmid}, '${node}', '${type}')" ${server.status !== 'running' || type === 'lxc' ? 'disabled' : ''}>
                                <i class="fas fa-undo"></i> Reset
                            </button>
                            <button class="btn btn-outline-success" onclick="proxmoxModule.resumeServer(${vmid}, '${node}', '${type}')" ${server.status === 'running' ? 'disabled' : ''}>
                                <i class="fas fa-play"></i> Fortsetzen
                            </button>
                            <button class="btn btn-outline-danger" onclick="proxmoxModule.deleteServer(${vmid}, '${node}', '${type}')">
                                <i class="fas fa-trash"></i> Löschen
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
};

// Server-Details schließen
proxmoxModule.closeServerDetails = function() {
    // Alle Tabs verstecken (inklusive Details-Tab)
    this.hideAllTabs();
    
    // Zeige die Server-Liste
    const serverListTab = document.getElementById('server-list-tab');
    if (serverListTab) {
        serverListTab.style.display = 'block';
    }
    
    // Server-Liste aktualisieren
    this.loadServerList();
};