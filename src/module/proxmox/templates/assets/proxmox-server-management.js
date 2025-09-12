/**
 * Proxmox Module - Server Management
 * Verwaltung von Server-Aktionen (Start, Stop, Restart, Delete, Edit)
 */

// Module Request Funktion
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

// Server-Management-Tab anzeigen
proxmoxModule.showServerManagement = async function(vmid, node, type = 'qemu') {
    this.currentServer = { vmid, node, type };
    
    // Alle anderen Tabs verstecken
    this.hideAllTabs();
    
    // Server-Management-Tab anzeigen
    const managementTab = document.getElementById('server-management-tab');
    if (managementTab) {
        managementTab.style.display = 'block';
    }
    
    try {
        const [configResult, statusResult] = await Promise.all([
            proxmoxModule.makeModuleRequest('get_vm_config', { vmid, node, type }),
            proxmoxModule.makeModuleRequest('get_vm_status', { vmid, node, type })
        ]);
        
        let html = `
            <div class="row">
                <div class="col-md-6">
                    <h6>Server-Details</h6>
                    <table class="table table-sm">
                        <tr><td>VM ID:</td><td>${vmid}</td></tr>
                        <tr><td>Node:</td><td>${node}</td></tr>
                        <tr><td>Status:</td><td><span class="badge ${this.getStatusClass(statusResult.data?.status)}">${this.getStatusText(statusResult.data?.status)}</span></td></tr>
                        <tr><td>Arbeitsspeicher (MB):</td><td>${configResult.data?.memory || 0}</td></tr>
                        <tr><td>CPU Kerne:</td><td>${configResult.data?.cores || 0}</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6>Server-Aktionen</h6>
                    <div class="d-grid gap-2">
                        <button class="btn btn-info" onclick="proxmoxModule.showServerDetails(${vmid}, '${node}', '${type || 'qemu'}')">
                            <i class="fas fa-chart-line"></i> Erweiterte Details
                        </button>
                        <button class="btn btn-success" onclick="proxmoxModule.startServer(${vmid}, '${node}', '${type || 'qemu'}')" ${statusResult.data?.status === 'running' ? 'disabled' : ''}>
                            <i class="fas fa-play"></i> Server starten
                        </button>
                        <button class="btn btn-warning" onclick="proxmoxModule.stopServer(${vmid}, '${node}', '${type || 'qemu'}')" ${statusResult.data?.status !== 'running' ? 'disabled' : ''}>
                            <i class="fas fa-stop"></i> Server stoppen
                        </button>
                        <button class="btn btn-info" onclick="proxmoxModule.restartServer(${vmid}, '${node}', '${type || 'qemu'}')" ${statusResult.data?.status !== 'running' ? 'disabled' : ''}>
                            <i class="fas fa-redo"></i> Server neu starten
                        </button>
                        <button class="btn btn-primary" onclick="proxmoxModule.editServer(${vmid}, '${node}')">
                            <i class="fas fa-edit"></i> Server bearbeiten
                        </button>
                        <button class="btn btn-danger" onclick="proxmoxModule.deleteServer(${vmid}, '${node}', '${type || 'qemu'}')">
                            <i class="fas fa-trash"></i> Server löschen
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('server-management-content').innerHTML = html;
        
    } catch (error) {
        showNotification('Fehler beim Laden der Server-Konfiguration: ' + error.message, 'error');
    }
};

// Server starten
proxmoxModule.startServer = async function(vmid, node) {
    try {
        const result = await proxmoxModule.makeModuleRequest('start_vm', { vmid, node });
        
        if (result.success) {
            showNotification('Server erfolgreich gestartet', 'success');
            this.loadServerList();
        } else {
            showNotification('Fehler beim Starten des Servers: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Fehler beim Starten des Servers: ' + error.message, 'error');
    }
};

// Server stoppen
proxmoxModule.stopServer = async function(vmid, node) {
    try {
        const result = await proxmoxModule.makeModuleRequest('stop_vm', { vmid, node });
        
        if (result.success) {
            showNotification('Server erfolgreich gestoppt', 'success');
            this.loadServerList();
        } else {
            showNotification('Fehler beim Stoppen des Servers: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Fehler beim Stoppen des Servers: ' + error.message, 'error');
    }
};

// Server neu starten
proxmoxModule.restartServer = async function(vmid, node) {
    try {
        const result = await proxmoxModule.makeModuleRequest('restart_vm', { vmid, node });
        
        if (result.success) {
            showNotification('Server erfolgreich neu gestartet', 'success');
            this.loadServerList();
        } else {
            showNotification('Fehler beim Neustarten des Servers: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Fehler beim Neustarten des Servers: ' + error.message, 'error');
    }
};

// Server bearbeiten (leitet zur Bearbeitungsseite weiter)
proxmoxModule.editServer = function(vmid, node) {
    // Verwende die neue Ajax-basierte Funktion aus proxmox-server-list.js
    if (typeof window.proxmoxModule !== 'undefined' && window.proxmoxModule.editServer) {
        window.proxmoxModule.editServer(vmid, node);
    } else {
        // Fallback: Direkte URL-Weiterleitung
        window.location.href = `?option=modules&mod=proxmox&vm=${vmid}&edit=1&node=${encodeURIComponent(node)}`;
    }
};

// Server löschen
proxmoxModule.deleteServer = async function(vmid, node) {
    if (!confirm('Möchten Sie diesen Server wirklich löschen?')) {
        return;
    }
    
    try {
        const result = await proxmoxModule.makeModuleRequest('delete_vm', { vmid, node });
        
        if (result.success) {
            showNotification('Server erfolgreich gelöscht', 'success');
            this.loadServerList();
            this.closeServerManagement();
        } else {
            showNotification('Fehler beim Löschen des Servers: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Fehler beim Löschen des Servers: ' + error.message, 'error');
    }
};

// Alle Tabs verstecken
proxmoxModule.hideAllTabs = function() {
    const tabs = [
        'node-selection-tab',
        'server-list-tab',
        'vm-creation-tab', 
        'extended-features-tab',
        'server-management-tab',
        'server-details-tab'
    ];
    
    tabs.forEach(tabId => {
        const tab = document.getElementById(tabId);
        if (tab) {
            tab.style.display = 'none';
        }
    });
};

// Server-Management-Tab schließen
proxmoxModule.closeServerManagement = function() {
    // Server-Liste-Tab wieder anzeigen
    const serverListTab = document.getElementById('server-list-tab');
    if (serverListTab) {
        serverListTab.style.display = 'block';
    }
    
    // Server-Management-Tab verstecken
    const managementTab = document.getElementById('server-management-tab');
    if (managementTab) {
        managementTab.style.display = 'none';
    }
    
    // Server-Liste aktualisieren
    this.loadServerList();
};

// closeServerDetails ist jetzt in proxmox-server-list.js definiert

proxmoxModule.showServerDetails = async function(vmid, node, type = 'qemu') {
    this.hideAllTabs();
    const detailsTab = document.getElementById('server-details-tab');
    if (detailsTab) {
        detailsTab.style.display = 'block';
    }

    try {
        const result = await this.makeModuleRequest('get_vm_status', { vmid, node, type });
        if (result.success && result.data) {
            this.displayDetailedServerInfo(result.data, vmid, node, type);
        } else {
            showNotification('Fehler beim Laden der Server-Details: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Fehler beim Laden der Server-Details: ' + error.message, 'error');
    }
};

proxmoxModule.displayDetailedServerInfo = function(data, vmid, node, type = 'qemu') {
    const content = document.getElementById('server-details-content');
    if (!content) return;

    const server = data.data || data;
    
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
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-tools"></i> Aktionen</h5>
                    </div>
                    <div class="card-body">
                        <div class="btn-group" role="group">
                            <button class="btn btn-outline-primary" onclick="proxmoxModule.startServer(${vmid}, '${node}')" ${server.status === 'running' ? 'disabled' : ''}>
                                <i class="fas fa-play"></i> Starten
                            </button>
                            <button class="btn btn-outline-warning" onclick="proxmoxModule.stopServer(${vmid}, '${node}')" ${server.status !== 'running' ? 'disabled' : ''}>
                                <i class="fas fa-stop"></i> Stoppen
                            </button>
                            <button class="btn btn-outline-info" onclick="proxmoxModule.restartServer(${vmid}, '${node}')" ${server.status !== 'running' ? 'disabled' : ''}>
                                <i class="fas fa-redo"></i> Neustart
                            </button>
                            <button class="btn btn-outline-danger" onclick="proxmoxModule.deleteServer(${vmid}, '${node}')">
                                <i class="fas fa-trash"></i> Löschen
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
};