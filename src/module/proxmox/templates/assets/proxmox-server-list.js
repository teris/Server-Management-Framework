/**
 * Proxmox Module - Server List Management
 * Verwaltung der Server-Liste und deren Darstellung
 */

// AJAX-Request-Funktion für das CMS
proxmoxModule.makeModuleRequest = async function(action, data = {}) {
    // Prüfe ob ModuleManager verfügbar ist
    if (typeof ModuleManager === 'undefined' || !ModuleManager.makeRequest) {
        console.error('ModuleManager not available!');
        return { success: false, error: 'ModuleManager not available' };
    }
    
    try {
        console.log('Making request to proxmox module:', action, data);
        const result = await ModuleManager.makeRequest('proxmox', action, data);
        console.log('ModuleManager response:', result);
        return result;
    } catch (error) {
        console.error('ModuleManager.makeRequest error:', error);
        return { success: false, error: error.message || 'Unknown error' };
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
        
        console.log('VM API response:', result);
        
        if (result.success) {
            // Prüfe verschiedene mögliche Datenstrukturen
            let servers = null;
            if (Array.isArray(result.data)) {
                servers = result.data;
            } else if (result.data && Array.isArray(result.data.data)) {
                servers = result.data.data;
            } else if (result.data && result.data.data && Array.isArray(result.data.data)) {
                servers = result.data.data;
            } else {
                console.error('Unexpected VM data structure:', result.data);
                this.showError('Unerwartete VM-Datenstruktur: ' + JSON.stringify(result.data));
                return;
            }
            
            console.log('Processed servers:', servers);
            this.renderServerList(servers);
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
        
        if (result && result.success && result.data) {
            this.displayNodesOverview(result.data);
        } else {
            this.showError('Fehler beim Laden der Nodes: ' + (result ? result.error : 'Keine Antwort erhalten'));
        }
    } catch (error) {
        this.showError('Fehler beim Laden der Nodes: ' + error.message);
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
                            <button class="btn btn-outline-warning btn-sm" onclick="proxmoxModule.showNodeTasks('${node.node}')">
                                <i class="fas fa-tasks"></i> Task-Logs
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
    console.log('Rendering server list:', servers);
    const container = document.getElementById('server-list-container');
    
    if (!container) {
        console.error('server-list-container not found');
        return;
    }
    
    // Prüfe ob servers ein Array ist
    if (!Array.isArray(servers)) {
        console.error('Servers is not an array:', typeof servers, servers);
        container.innerHTML = `
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle"></i> Fehler: Server-Daten haben das falsche Format
                <pre>${JSON.stringify(servers, null, 2)}</pre>
            </div>
        `;
        return;
    }
    
    if (servers.length === 0) {
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
                                <div class="fw-bold">${server.cores || server.cpus || server.maxcpu || 0}</div>
                            </div>
                            <div class="col-4">
                                <small class="text-muted">RAM</small>
                                <div class="fw-bold">${this.formatRAM(server.memory || server.maxmem || 0)}</div>
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
            alert('Fehler beim Laden der Server-Details: ' + result.error);
        }
    } catch (error) {
        alert('Fehler beim Laden der Server-Details: ' + error.message);
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
            alert('Fehler beim Laden der Server-Details: ' + (statusResult.error || configResult.error || 'Unbekannter Fehler'));
        }
    } catch (error) {
        alert('Fehler beim Laden der Server-Details: ' + error.message);
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

// Node auswählen
proxmoxModule.selectNode = function(node) {
    this.currentNode = node;
    console.log('Node selected:', node);
    
    // Zeige die Server-Liste Card
    const serverListCard = document.getElementById('server-list-card');
    if (serverListCard) {
        serverListCard.style.display = 'block';
    }
    
    // Lade VMs für den ausgewählten Node
    this.loadServerList(node);
};

// Node Task-Logs anzeigen
proxmoxModule.showNodeTasks = async function(node) {
    try {
        const result = await this.makeModuleRequest('get_node_tasks', { node });
        
        if (result.success && result.data) {
            this.displayNodeTasksModal(node, result.data);
        } else {
            alert('Fehler beim Laden der Task-Logs: ' + (result.error || 'Unbekannter Fehler'));
        }
    } catch (error) {
        alert('Fehler beim Laden der Task-Logs: ' + error.message);
    }
};

// Task-Logs Modal anzeigen
proxmoxModule.displayNodeTasksModal = function(node, tasksData) {
    const tasks = tasksData.data || tasksData;
    
    let tasksHtml = '';
    if (tasks && tasks.length > 0) {
        tasks.forEach(task => {
            // Formatiere Startzeit
            const startTime = task.starttime ? new Date(task.starttime * 1000).toLocaleString('de-DE') : 'N/A';
            
            // Formatiere Endzeit
            const endTime = task.endtime ? new Date(task.endtime * 1000).toLocaleString('de-DE') : 'N/A';
            
            // Berechne Dauer
            let duration = 'N/A';
            if (task.starttime && task.endtime) {
                const durationSeconds = task.endtime - task.starttime;
                const hours = Math.floor(durationSeconds / 3600);
                const minutes = Math.floor((durationSeconds % 3600) / 60);
                const seconds = durationSeconds % 60;
                duration = `${hours}h ${minutes}m ${seconds}s`;
            } else if (task.starttime && !task.endtime) {
                const durationSeconds = Math.floor(Date.now() / 1000) - task.starttime;
                const hours = Math.floor(durationSeconds / 3600);
                const minutes = Math.floor((durationSeconds % 3600) / 60);
                const seconds = durationSeconds % 60;
                duration = `${hours}h ${minutes}m ${seconds}s (läuft)`;
            }
            
            tasksHtml += `
                <tr>
                    <td>
                        <span class="badge bg-${task.status_class || 'secondary'}">${task.status_text || task.status || 'N/A'}</span>
                    </td>
                    <td>${task.type || 'N/A'}</td>
                    <td>${task.user || 'N/A'}</td>
                    <td>${task.id || 'N/A'}</td>
                    <td>${startTime}</td>
                    <td>${endTime}</td>
                    <td>${duration}</td>
                </tr>
            `;
        });
    } else {
        tasksHtml = `
            <tr>
                <td colspan="7" class="text-center text-muted">
                    <i class="fas fa-info-circle"></i> Keine Tasks gefunden
                </td>
            </tr>
        `;
    }
    
    const modalHtml = `
        <div class="modal fade" id="nodeTasksModal" tabindex="-1" aria-labelledby="nodeTasksModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="nodeTasksModalLabel">
                            <i class="fas fa-tasks"></i> Task-Logs für Node: ${node}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Status</th>
                                        <th>Typ</th>
                                        <th>Benutzer</th>
                                        <th>ID</th>
                                        <th>Startzeit</th>
                                        <th>Endzeit</th>
                                        <th>Dauer</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${tasksHtml}
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Schließen
                        </button>
                        <button type="button" class="btn btn-primary" onclick="proxmoxModule.refreshNodeTasks('${node}')">
                            <i class="fas fa-sync-alt"></i> Aktualisieren
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Entferne existierendes Modal falls vorhanden
    const existingModal = document.getElementById('nodeTasksModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Füge neues Modal hinzu
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Zeige Modal
    const modal = new bootstrap.Modal(document.getElementById('nodeTasksModal'));
    modal.show();
};

// Task-Logs aktualisieren
proxmoxModule.refreshNodeTasks = async function(node) {
    try {
        const result = await this.makeModuleRequest('get_node_tasks', { node });
        
        if (result.success && result.data) {
            // Aktualisiere die Tabelle im Modal
            const tbody = document.querySelector('#nodeTasksModal tbody');
            if (tbody) {
                const tasks = result.data.data || result.data;
                let tasksHtml = '';
                
                if (tasks && tasks.length > 0) {
                    tasks.forEach(task => {
                        tasksHtml += `
                            <tr>
                                <td>
                                    <span class="badge bg-${task.status_class || 'secondary'}">${task.status_text || task.status || 'N/A'}</span>
                                </td>
                                <td>${task.type || 'N/A'}</td>
                                <td>${task.user || 'N/A'}</td>
                                <td>${task.id || 'N/A'}</td>
                                <td>${task.starttime_formatted || 'N/A'}</td>
                                <td>${task.endtime_formatted || 'N/A'}</td>
                                <td>${task.duration_formatted || 'N/A'}</td>
                            </tr>
                        `;
                    });
                } else {
                    tasksHtml = `
                        <tr>
                            <td colspan="7" class="text-center text-muted">
                                <i class="fas fa-info-circle"></i> Keine Tasks gefunden
                            </td>
                        </tr>
                    `;
                }
                
                tbody.innerHTML = tasksHtml;
                alert('Task-Logs erfolgreich aktualisiert');
            }
        } else {
            alert('Fehler beim Aktualisieren der Task-Logs: ' + (result.error || 'Unbekannter Fehler'));
        }
    } catch (error) {
        alert('Fehler beim Aktualisieren der Task-Logs: ' + error.message);
    }
};

// VM-Erstellungsformular anzeigen
proxmoxModule.showCreateVMForm = function() {
    // Speichere den aktuellen Node
    const currentNode = this.currentNode;
    if (!currentNode) {
        alert('Bitte wählen Sie zuerst einen Node aus');
        return;
    }
    
    // Verstecke die Server-Liste
    const serverListCard = document.getElementById('server-list-card');
    if (serverListCard) {
        serverListCard.style.display = 'none';
    }
    
    // Zeige das VM-Erstellungsformular
    const vmCreationContainer = document.getElementById('vm-creation-container');
    const vmCreationForm = document.getElementById('vm-creation-form');
    if (vmCreationContainer && vmCreationForm) {
        vmCreationContainer.style.display = 'block';
        vmCreationForm.style.display = 'block';
        
        // Formular-Optionen laden
        this.loadVMFormOptions();
    } else {
        // Fallback: Zeige das VM-Erstellungsformular direkt
        const vmCreationFormDirect = document.getElementById('vm-creation-form');
        if (vmCreationFormDirect) {
            vmCreationFormDirect.style.display = 'block';
            // Formular-Optionen laden
            this.loadVMFormOptions();
        }
    }
};

// LXC-Erstellungsformular anzeigen
proxmoxModule.showCreateLXCForm = function() {
    const container = document.getElementById('server-list-container');
    if (!container) return;
    
    // Speichere den aktuellen Node
    const currentNode = this.currentNode;
    if (!currentNode) {
        alert('Bitte wählen Sie zuerst einen Node aus');
        return;
    }
    
    container.innerHTML = `
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-cube"></i> LXC erstellen</h5>
                        <button class="btn btn-outline-secondary btn-sm" onclick="proxmoxModule.showServerList()">
                            <i class="fas fa-arrow-left"></i> Zurück zur Liste
                        </button>
                    </div>
                    <div class="card-body">
                        <form id="create-lxc-form" onsubmit="proxmoxModule.submitCreateLXC(event)">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="lxc-hostname" class="form-label">Hostname *</label>
                                        <input type="text" class="form-control" id="lxc-hostname" name="hostname" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="lxc-vmid" class="form-label">Container ID *</label>
                                        <input type="number" class="form-control" id="lxc-vmid" name="vmid" min="100" max="999999" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="lxc-memory" class="form-label">RAM (MB) *</label>
                                        <input type="number" class="form-control" id="lxc-memory" name="memory" min="128" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="lxc-cores" class="form-label">CPU Kerne *</label>
                                        <input type="number" class="form-control" id="lxc-cores" name="cores" min="1" max="32" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="lxc-disk" class="form-label">Festplatte (GB) *</label>
                                        <input type="number" class="form-control" id="lxc-disk" name="disk" min="1" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="lxc-storage" class="form-label">Storage *</label>
                                        <select class="form-control" id="lxc-storage" name="storage" required>
                                            <option value="">Storage laden...</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="lxc-template" class="form-label">Template *</label>
                                        <select class="form-control" id="lxc-template" name="template" required>
                                            <option value="">Template laden...</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="lxc-password" class="form-label">Root-Passwort *</label>
                                        <input type="password" class="form-control" id="lxc-password" name="password" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="lxc-ip" class="form-label">IP-Adresse (optional)</label>
                                        <input type="text" class="form-control" id="lxc-ip" name="ip" placeholder="192.168.1.100/24">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="lxc-gateway" class="form-label">Gateway (optional)</label>
                                        <input type="text" class="form-control" id="lxc-gateway" name="gateway" placeholder="192.168.1.1">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <input type="hidden" name="node" value="${currentNode}">
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="button" class="btn btn-secondary me-md-2" onclick="proxmoxModule.showServerList()">
                                    <i class="fas fa-times"></i> Abbrechen
                                </button>
                                <button type="submit" class="btn btn-info">
                                    <i class="fas fa-cube"></i> LXC erstellen
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Lade Storage- und Template-Optionen
    this.loadLXCFormOptions();
};

// Zurück zur Server-Liste
proxmoxModule.showServerList = function() {
    // Verstecke das VM-Erstellungsformular
    const vmCreationContainer = document.getElementById('vm-creation-container');
    const vmCreationForm = document.getElementById('vm-creation-form');
    if (vmCreationContainer && vmCreationForm) {
        vmCreationContainer.style.display = 'none';
        vmCreationForm.style.display = 'none';
    }
    
    // Zeige die Server-Liste
    const serverListCard = document.getElementById('server-list-card');
    if (serverListCard) {
        serverListCard.style.display = 'block';
    }
    
    if (this.currentNode) {
        this.loadServerList(this.currentNode);
    } else {
        this.loadServerList();
    }
};

// VM-Formular-Optionen laden
proxmoxModule.loadVMFormOptions = async function() {
    try {
        const storageResult = await this.makeModuleRequest('get_proxmox_storages', { node: this.currentNode });
        
        // Storage-Optionen laden
        if (storageResult.success && storageResult.data && storageResult.data.data) {
            const storageSelect = document.getElementById('vm_storage');
            if (storageSelect) {
                storageSelect.innerHTML = '<option value="">Storage auswählen...</option>';
                storageResult.data.data.forEach(storage => {
                    if (storage.type === 'dir' || storage.type === 'lvm' || storage.type === 'zfspool') {
                        storageSelect.innerHTML += `<option value="${storage.storage}">${storage.storage} (${storage.type})</option>`;
                    }
                });
            }
        }
        
        // Node-Optionen laden
        const nodeSelect = document.getElementById('vm_node');
        if (nodeSelect && this.currentNode) {
            nodeSelect.innerHTML = `<option value="${this.currentNode}" selected>${this.currentNode}</option>`;
        }
        
        // Maximale RAM-Berechnung (vereinfacht)
        this.setupMemorySlider();
        
        // Event Listeners für bedingte Felder
        this.setupConditionalFields();
        
        // Tooltips für Collapse-Buttons einrichten
        this.setupCollapseTooltips();
        
        // SCSI Disk Preset Handling einrichten
        this.setupScsiDiskPresetHandling();
        
        // Netzwerk-Bridges laden
        this.loadNetworkBridges();
        
        // Netzwerk-Typen laden
        this.loadNetworkTypes();
        
        // ISO-Dateien laden
        this.loadIsoFiles();
        
    } catch (error) {
        console.error('Fehler beim Laden der Formular-Optionen:', error);
    }
};

// Bedingte Felder einrichten
proxmoxModule.setupConditionalFields = function() {
    // Memory Shares Toggle
    const manualMemoryCheckbox = document.getElementById('vm_manual_memory');
    const memoryAdvancedDiv = document.getElementById('vm_memory_advanced');
    
    if (manualMemoryCheckbox && memoryAdvancedDiv) {
        manualMemoryCheckbox.addEventListener('change', function() {
            memoryAdvancedDiv.style.display = this.checked ? 'block' : 'none';
        });
    }
    
    // CPU Units Toggle
    const manualCpuCheckbox = document.getElementById('vm_manual_cpu');
    const cpuAdvancedDiv = document.getElementById('vm_cpu_advanced');
    
    if (manualCpuCheckbox && cpuAdvancedDiv) {
        manualCpuCheckbox.addEventListener('change', function() {
            cpuAdvancedDiv.style.display = this.checked ? 'block' : 'none';
        });
    }
    
    // RAM Slider Synchronisation wird in setupMemorySlider() gehandhabt
    
    // Collapse Icons aktualisieren
    this.setupCollapseIcons();
};

// Netzwerk-Bridges laden
proxmoxModule.loadNetworkBridges = async function() {
    if (!this.currentNode) return;
    
    try {
        const result = await this.makeModuleRequest('get_node_networks', { node: this.currentNode });
        
        if (result.success && result.data && result.data.data) {
            const bridgeSelect = document.getElementById('vm_bridge');
            if (bridgeSelect) {
                // Leere das Dropdown
                bridgeSelect.innerHTML = '<option value="">Bridge auswählen...</option>';
                
                // Füge verfügbare Bridges hinzu
                result.data.data.forEach(bridge => {
                    const option = document.createElement('option');
                    option.value = bridge.iface;
                    option.textContent = bridge.iface;
                    if (bridge.address) {
                        option.textContent += ` (${bridge.address})`;
                    }
                    bridgeSelect.appendChild(option);
                });
            }
        }
    } catch (error) {
        console.error('Fehler beim Laden der Netzwerk-Bridges:', error);
    }
};

// Netzwerk-Typen laden
proxmoxModule.loadNetworkTypes = function() {
    const netModelSelect = document.getElementById('vm_net_model');
    if (netModelSelect) {
        // Die Netzwerktypen sind bereits in der HTML definiert, aber wir können sie hier validieren
        const networkTypes = [
            { value: 'virtio', text: 'virtio (empfohlen)' },
            { value: 'e1000', text: 'e1000' },
            { value: 'e1000e', text: 'e1000e' },
            { value: 'rtl8139', text: 'rtl8139' },
            { value: 'vmxnet3', text: 'vmxnet3' }
        ];
        
        // Leere das Dropdown und fülle es neu
        netModelSelect.innerHTML = '';
        networkTypes.forEach(type => {
            const option = document.createElement('option');
            option.value = type.value;
            option.textContent = type.text;
            netModelSelect.appendChild(option);
        });
    }
};

// ISO-Dateien laden
proxmoxModule.loadIsoFiles = async function() {
    if (!this.currentNode) return;
    
    try {
        // Lade zuerst die Storage-Liste
        const storageResult = await this.makeModuleRequest('get_proxmox_storages', { node: this.currentNode });
        
        if (storageResult.success && storageResult.data && storageResult.data.data) {
            const isoSelect = document.getElementById('vm_ide2');
            if (isoSelect) {
                // Leere das Dropdown
                isoSelect.innerHTML = '<option value="">Kein ISO ausgewählt</option>';
                
                // Lade ISO-Dateien von allen Storage-Volumes
                const loadPromises = [];
                storageResult.data.data.forEach(storage => {
                    if (storage.type === 'dir' || storage.type === 'lvm' || storage.type === 'zfspool') {
                        loadPromises.push(
                            this.makeModuleRequest('get_iso_files', { 
                                node: this.currentNode, 
                                storage: storage.storage 
                            }).then(result => ({ storage: storage.storage, result }))
                        );
                    }
                });
                
                const isoResults = await Promise.all(loadPromises);
                
                isoResults.forEach(({ storage, result }) => {
                    console.log('ISO result for storage', storage, ':', result);
                    if (result.success && result.data && Array.isArray(result.data)) {
                        console.log('Füge', result.data.length, 'ISO-Dateien hinzu für Storage', storage);
                        result.data.forEach(iso => {
                            const option = document.createElement('option');
                            option.value = `${storage}:${iso.volid},media=cdrom`;
                            option.textContent = `${iso.volid} (${storage})`;
                            isoSelect.appendChild(option);
                            console.log('ISO-Option hinzugefügt:', iso.volid);
                        });
                    } else {
                        console.log('Keine ISO-Dateien für Storage', storage, 'oder falsche Datenstruktur');
                    }
                });
            }
        }
    } catch (error) {
        console.error('Fehler beim Laden der ISO-Dateien:', error);
    }
};

// Collapse Icons einrichten
proxmoxModule.setupCollapseIcons = function() {
    const collapseElements = document.querySelectorAll('[data-bs-toggle="collapse"]');
    
    collapseElements.forEach(element => {
        element.addEventListener('click', function() {
            const target = document.querySelector(this.getAttribute('data-bs-target'));
            const icon = this.querySelector('.fa-chevron-down');
            
            if (target) {
                // Alle anderen Collapse-Elemente schließen (außer Grundlegende Konfiguration)
                const currentTargetId = this.getAttribute('data-bs-target');
                if (currentTargetId !== '#basic-config-section') {
                    collapseElements.forEach(otherElement => {
                        const otherTargetId = otherElement.getAttribute('data-bs-target');
                        if (otherTargetId !== currentTargetId && otherTargetId !== '#basic-config-section') {
                            const otherTarget = document.querySelector(otherTargetId);
                            if (otherTarget && otherTarget.classList.contains('show')) {
                                const otherIcon = otherElement.querySelector('.fa-chevron-down');
                                if (otherIcon) otherIcon.className = 'fas fa-chevron-down float-end';
                                otherTarget.classList.remove('show');
                            }
                        }
                    });
                }
                
                // Icon aktualisieren
                target.addEventListener('shown.bs.collapse', function() {
                    if (icon) icon.className = 'fas fa-chevron-up float-end';
                });
                
                target.addEventListener('hidden.bs.collapse', function() {
                    if (icon) icon.className = 'fas fa-chevron-down float-end';
                });
            }
        });
    });
};

// Memory Slider einrichten
proxmoxModule.setupMemorySlider = function() {
    const memorySlider = document.getElementById('vm_memory_slider');
    const memoryInput = document.getElementById('vm_memory');
    const memoryMaxSpan = document.getElementById('vm_memory_max');
    
    if (memorySlider && memoryInput && memoryMaxSpan) {
        // Standardwerte setzen (können später dynamisch angepasst werden)
        const defaultMax = 32768; // 32GB Standard-Maximum
        memorySlider.max = defaultMax;
        memoryInput.max = defaultMax;
        memoryMaxSpan.textContent = defaultMax;
        
        // Slider und Input synchronisieren
        memorySlider.addEventListener('input', function() {
            memoryInput.value = this.value;
        });
        
        memoryInput.addEventListener('input', function() {
            const value = parseInt(this.value);
            const max = parseInt(memorySlider.max);
            const min = parseInt(memorySlider.min);
            
            if (value >= min && value <= max) {
                memorySlider.value = value;
            }
        });
    }
};

// Collapse Tooltips einrichten
proxmoxModule.setupCollapseTooltips = function() {
    const collapseElements = document.querySelectorAll('[data-bs-toggle="collapse"]');
    
    collapseElements.forEach(element => {
        const target = document.querySelector(element.getAttribute('data-bs-target'));
        const icon = element.querySelector('.fa-chevron-down');
        const badge = element.querySelector('.badge');
        
        if (target && icon) {
            // Initiale Tooltip-Text und Badge setzen
            const updateTooltip = () => {
                if (target.classList.contains('show')) {
                    element.title = 'Zuklappen';
                    icon.className = 'fas fa-chevron-up float-end';
                    if (badge) {
                        badge.textContent = 'Zuklappen 🔼';
                        badge.className = 'badge bg-secondary ms-2';
                    }
                } else {
                    element.title = 'Aufklappen';
                    icon.className = 'fas fa-chevron-down float-end';
                    if (badge) {
                        badge.textContent = 'Aufklappen 🔽';
                        badge.className = 'badge bg-info ms-2';
                    }
                }
            };
            
            // Event Listeners für Tooltip-Updates
            target.addEventListener('shown.bs.collapse', updateTooltip);
            target.addEventListener('hidden.bs.collapse', updateTooltip);
            
            // Initiale Tooltip setzen
            updateTooltip();
        }
    });
};

// SCSI Disk Preset / Manuelle Größe und net0 Builder
proxmoxModule.setupScsiDiskPresetHandling = function() {
    const preset = document.getElementById('vm_disk_preset');
    const manualRow = document.getElementById('vm_disk_manual_row');
    const manualInput = document.getElementById('vm_disk_manual');
    const storageSelect = document.getElementById('vm_storage');
    const scsiHidden = document.getElementById('vm_scsi0_hidden');

    const rebuildScsi0 = () => {
        const storage = storageSelect && storageSelect.value ? storageSelect.value : '';
        let sizeGb = null;
        if (preset && preset.value && preset.value !== 'manual') {
            sizeGb = preset.value;
        } else if (manualInput && manualInput.value) {
            sizeGb = Math.min(1024, Math.max(1, parseInt(manualInput.value, 10) || 0));
        }
        if (storage && sizeGb) {
            scsiHidden.value = `${storage}:${sizeGb}`;
        } else {
            scsiHidden.value = '';
        }
    };

    if (preset) {
        preset.addEventListener('change', function() {
            const isManual = this.value === 'manual';
            if (manualRow) manualRow.style.display = isManual ? 'block' : 'none';
            rebuildScsi0();
        });
    }
    if (manualInput) {
        manualInput.addEventListener('input', rebuildScsi0);
    }
    if (storageSelect) {
        storageSelect.addEventListener('change', rebuildScsi0);
    }

    // net0 Builder: Modell + Bridge (+MAC optional)
    const netModel = document.getElementById('vm_net_model');
    const bridge = document.getElementById('vm_bridge');
    const mac = document.getElementById('vm_mac');
    const net0Hidden = document.getElementById('vm_net0_hidden');
    
    const rebuildNet0 = () => {
        const model = netModel ? netModel.value : 'virtio';
        const br = bridge ? bridge.value : '';
        if (br) {
            let net0 = `${model},bridge=${br}`;
            if (mac && mac.value) net0 += `,macaddr=${mac.value}`;
            if (net0Hidden) net0Hidden.value = net0;
        } else {
            if (net0Hidden) net0Hidden.value = '';
        }
    };
    
    // Event Listeners für net0 Updates
    if (netModel) netModel.addEventListener('change', rebuildNet0);
    if (bridge) bridge.addEventListener('change', rebuildNet0);
    if (mac) mac.addEventListener('input', rebuildNet0);
    
    // Wir setzen net0 dynamisch vor dem Absenden in submitCreateVM
    this._buildNet0 = function() {
        return net0Hidden ? net0Hidden.value : '';
    };
    
    // ISO-Dropdown Synchronisation
    const isoSelect = document.getElementById('vm_ide2');
    const cdromInput = document.getElementById('vm_cdrom');
    
    if (isoSelect && cdromInput) {
        isoSelect.addEventListener('change', function() {
            if (this.value) {
                // Entferne ,media=cdrom für das CD-ROM Feld
                const value = this.value.replace(',media=cdrom', '');
                cdromInput.value = value;
            } else {
                cdromInput.value = '';
            }
        });
    }
};

// LXC-Formular-Optionen laden
proxmoxModule.loadLXCFormOptions = async function() {
    try {
        const storageResult = await this.makeModuleRequest('get_proxmox_storages', { node: this.currentNode });
        
        // Storage-Optionen laden
        if (storageResult.success && storageResult.data && storageResult.data.data) {
            const storageSelect = document.getElementById('lxc-storage');
            storageSelect.innerHTML = '<option value="">Storage auswählen...</option>';
            storageResult.data.data.forEach(storage => {
                if (storage.type === 'dir' || storage.type === 'lvm' || storage.type === 'zfspool') {
                    storageSelect.innerHTML += `<option value="${storage.storage}">${storage.storage} (${storage.type})</option>`;
                }
            });
        }
        
        // Template-Optionen laden (vereinfacht)
        const templateSelect = document.getElementById('lxc-template');
        templateSelect.innerHTML = `
            <option value="">Template auswählen...</option>
            <option value="local:vztmpl/debian-11-standard_11.7-1_amd64.tar.zst">Debian 11</option>
            <option value="local:vztmpl/ubuntu-22.04-standard_22.04-1_amd64.tar.zst">Ubuntu 22.04</option>
            <option value="local:vztmpl/centos-7-standard_7.0-200.x86_64.tar.xz">CentOS 7</option>
        `;
        
    } catch (error) {
        console.error('Fehler beim Laden der LXC-Formular-Optionen:', error);
    }
};

// VM erstellen
proxmoxModule.submitCreateVM = async function(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    // SCSI0 aus Preset/Manuell + Storage zusammensetzen, falls leer
    const scsiHidden = document.getElementById('vm_scsi0_hidden');
    if (scsiHidden && !formData.get('scsi0')) {
        if (scsiHidden.value) formData.set('scsi0', scsiHidden.value);
    }
    
    // net0 aus Hidden-Feld verwenden
    const net0Hidden = document.getElementById('vm_net0_hidden');
    if (net0Hidden && net0Hidden.value) {
        formData.set('net0', net0Hidden.value);
    }
    
    // Formulardaten sammeln und bereinigen
    const vmData = {};
    
    // Alle Formularfelder durchgehen
    for (const [key, value] of formData.entries()) {
        if (value && value.trim() !== '') {
            // Checkboxen als Boolean konvertieren
            if (['onboot', 'agent', 'kvm', 'acpi', 'localtime', 'tablet', 'autostart', 'protection', 'template', 'start', 'reboot', 'unique', 'ciupgrade'].includes(key)) {
                vmData[key] = '1';
            } else {
                vmData[key] = value;
            }
        }
    }
    
    // Checkboxen die nicht gesetzt sind explizit auf 0 setzen
    const checkboxFields = ['onboot', 'agent', 'kvm', 'acpi', 'localtime', 'tablet', 'autostart', 'protection', 'template', 'start', 'reboot', 'unique', 'ciupgrade'];
    checkboxFields.forEach(field => {
        if (!vmData[field]) {
            vmData[field] = '0';
        }
    });
    
    // Standardwerte setzen falls nicht angegeben
    if (!vmData.ostype) vmData.ostype = 'l26';
    if (!vmData.bios) vmData.bios = 'seabios';
    if (!vmData.machine) vmData.machine = 'pc';
    if (!vmData.cpu) vmData.cpu = 'host';
    if (!vmData.arch) vmData.arch = 'x86_64';
    if (!vmData.scsihw) vmData.scsihw = 'lsi';
    if (!vmData.vga) vmData.vga = 'std';
    if (!vmData.keyboard) vmData.keyboard = 'de';
    if (!vmData.citype) vmData.citype = 'nocloud';
    if (!vmData.startdate) vmData.startdate = 'now';
    
    try {
        const result = await this.makeModuleRequest('create_vm', vmData);
        
        if (result.success) {
            alert('VM erfolgreich erstellt!');
            this.showServerList();
        } else {
            alert('Fehler beim Erstellen der VM: ' + (result.error || 'Unbekannter Fehler'));
        }
    } catch (error) {
        alert('Fehler beim Erstellen der VM: ' + error.message);
    }
};

// VM-Formular zurücksetzen
proxmoxModule.resetVMForm = function() {
    const form = document.getElementById('create-vm-form');
    if (form) {
        form.reset();
        
        // Standardwerte wiederherstellen
        document.getElementById('vm_cores').value = '1';
        document.getElementById('vm_sockets').value = '1';
        document.getElementById('vm_memory').value = '2048';
        document.getElementById('vm_memory_slider').value = '2048';
        document.getElementById('vm_shares').value = '1000';
        document.getElementById('vm_cpulimit').value = '0';
        document.getElementById('vm_cpuunits').value = '1024';
        document.getElementById('vm_cpu').value = 'host';
        document.getElementById('vm_ostype').value = 'l26';
        document.getElementById('vm_bios').value = 'seabios';
        document.getElementById('vm_machine').value = 'pc';
        document.getElementById('vm_boot').value = 'order=scsi0;ide2;net0';
        document.getElementById('vm_scsihw').value = 'lsi';
        document.getElementById('vm_vga').value = 'std';
        document.getElementById('vm_keyboard').value = 'de';
        document.getElementById('vm_citype').value = 'nocloud';
        document.getElementById('vm_startdate').value = 'now';
        document.getElementById('vm_migrate_downtime').value = '0.1';
        document.getElementById('vm_migrate_speed').value = '0';
        
        // Checkboxen zurücksetzen
        document.getElementById('vm_agent').checked = true;
        document.getElementById('vm_kvm').checked = true;
        document.getElementById('vm_acpi').checked = true;
        document.getElementById('vm_tablet').checked = true;
        document.getElementById('vm_reboot').checked = true;
        document.getElementById('vm_ciupgrade').checked = true;
        
        // Bedingte Felder verstecken
        document.getElementById('vm_manual_memory').checked = false;
        document.getElementById('vm_manual_cpu').checked = false;
        document.getElementById('vm_memory_advanced').style.display = 'none';
        document.getElementById('vm_cpu_advanced').style.display = 'none';
    }
};

// LXC erstellen
proxmoxModule.submitCreateLXC = async function(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    try {
        const result = await this.makeModuleRequest('create_lxc', Object.fromEntries(formData));
        
        if (result.success) {
            alert('LXC erfolgreich erstellt!');
            this.showServerList();
        } else {
            alert('Fehler beim Erstellen des LXC: ' + (result.error || 'Unbekannter Fehler'));
        }
    } catch (error) {
        alert('Fehler beim Erstellen des LXC: ' + error.message);
    }
};