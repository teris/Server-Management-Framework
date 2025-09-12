/**
 * Proxmox Module - Core JavaScript
 * Hauptmodul mit Initialisierung und Basis-Funktionen
 */

window.proxmoxModule = {
    currentServer: null,
    
    init: function() {
        console.log('Proxmox module initialized');
        // Zeige den Server-Liste-Tab an (enthält die Nodes-Übersicht)
        this.showServerListTab();
        
        // Lade die Nodes-Übersicht
        this.loadNodesOverview();
    },
    
    // Nodes-Übersicht laden
    loadNodesOverview: async function() {
        try {
            console.log('Loading nodes overview...');
            const result = await this.makeModuleRequest('get_proxmox_nodes');
            
            console.log('Raw API response:', result);
            
            if (result && result.success) {
                // Prüfe verschiedene mögliche Datenstrukturen
                let nodes = null;
                if (Array.isArray(result.data)) {
                    nodes = result.data;
                } else if (result.data && Array.isArray(result.data.data)) {
                    nodes = result.data.data;
                } else if (result.data && result.data.data && Array.isArray(result.data.data)) {
                    nodes = result.data.data;
                } else {
                    console.error('Unexpected data structure:', result.data);
                    this.showError('Unerwartete Datenstruktur: ' + JSON.stringify(result.data));
                    return;
                }
                
                console.log('Processed nodes:', nodes);
                this.displayNodesOverview(nodes);
            } else {
                console.error('Failed to load nodes:', result);
                this.showError('Fehler beim Laden der Nodes: ' + (result ? result.error : 'Keine Antwort erhalten'));
            }
        } catch (error) {
            console.error('Error loading nodes:', error);
            this.showError('Fehler beim Laden der Nodes: ' + error.message);
        }
    },
    
    // Nodes-Übersicht anzeigen
    displayNodesOverview: function(nodes) {
        console.log('Displaying nodes overview:', nodes);
        const container = document.getElementById('nodes-overview-container');
        if (!container) {
            console.error('nodes-overview-container not found');
            return;
        }
        
        // Prüfe ob nodes ein Array ist
        if (!Array.isArray(nodes)) {
            console.error('Nodes is not an array:', typeof nodes, nodes);
            container.innerHTML = `
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> Fehler: Nodes-Daten haben das falsche Format
                    <pre>${JSON.stringify(nodes, null, 2)}</pre>
                </div>
            `;
            return;
        }
        
        if (nodes.length === 0) {
            container.innerHTML = `
                <div class="alert alert-info" role="alert">
                    <i class="fas fa-info-circle"></i> Keine Nodes verfügbar
                </div>
            `;
            return;
        }
        
        let html = '<div class="row">';
        nodes.forEach(node => {
            const statusClass = node.status === 'online' ? 'success' : 'danger';
            const statusIcon = node.status === 'online' ? 'fa-check-circle' : 'fa-times-circle';
            
            html += `
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-server"></i> ${node.node}</h5>
                            <span class="badge bg-${statusClass}">
                                <i class="fas ${statusIcon}"></i> ${node.status || 'unknown'}
                            </span>
                        </div>
                        <div class="card-body">
                            <p><strong>CPU:</strong> ${node.maxcpu || 'N/A'} Kerne</p>
                            <p><strong>RAM:</strong> ${this.formatRAM(node.maxmem) || 'N/A'}</p>
                            <p><strong>Uptime:</strong> ${node.uptime || 'N/A'} Sekunden</p>
                            <button class="btn btn-primary btn-sm" onclick="proxmoxModule.selectNode('${node.node}')">
                                <i class="fas fa-arrow-right"></i> Auswählen
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        
        container.innerHTML = html;
    },
    
    // Server-Liste aktualisieren
    refreshServerList: function() {
        // Lade Nodes-Übersicht und Server-Liste
        this.loadNodesOverview();
        this.loadServerList();
    },
    
    // Fehler anzeigen
    showError: function(message) {
        const container = document.getElementById('server-list-container');
        container.innerHTML = `
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle"></i> ${message}
            </div>
        `;
    },
    
    // Nodes-Übersicht laden (Fallback für core.js)
    loadNodesOverview: function() {
        // Diese Funktion wird von proxmox-server-list.js überschrieben
        console.log('loadNodesOverview called from core.js - should be overridden');
        
        // Fallback: Lade Nodes direkt
        this.loadNodes();
    },
    
    // Nodes laden (Fallback)
    loadNodes: async function() {
        try {
            const result = await this.makeModuleRequest('get_proxmox_nodes');
            if (result.success && result.data) {
                this.displayNodes(result.data);
            } else {
                this.showError('Fehler beim Laden der Nodes: ' + (result.error || 'Unbekannter Fehler'));
            }
        } catch (error) {
            this.showError('Fehler beim Laden der Nodes: ' + error.message);
        }
    },
    
    // makeModuleRequest - Nur ModuleManager verwenden
    makeModuleRequest: async function(action, data = {}) {
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
    },
    
    // displayNodes Fallback
    displayNodes: function(nodes) {
        // Versuche zuerst den nodes-overview-container, dann node-selection-content
        let content = document.getElementById('nodes-overview-container');
        if (!content) {
            content = document.getElementById('node-selection-content');
        }
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
                                    <div class="fw-bold">${this.formatRAM(node.maxmem || 0)}</div>
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
                                    <i class="fas fa-info-circle"></i> Details
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        content.innerHTML = html;
    },
    
    // formatRAM Fallback
    formatRAM: function(bytes) {
        if (!bytes || bytes === 0) return '0 GB';
        const gb = bytes / 1024 / 1024 / 1024;
        return gb.toFixed(2) + ' GB';
    },
    
    // formatUptime Fallback
    formatUptime: function(uptime) {
        if (!uptime) return 'Nie';
        
        const days = Math.floor(uptime / 86400);
        const hours = Math.floor((uptime % 86400) / 3600);
        const minutes = Math.floor((uptime % 3600) / 60);
        
        let result = '';
        if (days > 0) result += days + ' Tage ';
        if (hours > 0) result += hours + ' Stunden ';
        if (minutes > 0) result += minutes + ' Minuten';
        
        return result || 'Nie';
    },
    
    // selectNode Fallback
    selectNode: function(nodeName) {
        console.log('Node selected:', nodeName);
        // Diese Funktion wird von anderen JS-Dateien überschrieben
    },
    
    // loadNodeStatus Fallback
    loadNodeStatus: function(nodeName) {
        console.log('Node status requested for:', nodeName);
        // Diese Funktion wird von anderen JS-Dateien überschrieben
    },
    
    // showNodeSelection Fallback
    showNodeSelection: function() {
        // Verstecke alle anderen Tabs
        this.hideAllTabs();
        
        // Zeige den Node-Auswahl-Tab
        const nodeSelectionTab = document.getElementById('node-selection-tab');
        if (nodeSelectionTab) {
            nodeSelectionTab.style.display = 'block';
        }
        
        // Lade die Nodes
        this.loadNodes();
    },
    
    // hideAllTabs Fallback
    hideAllTabs: function() {
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
    },
    
    // RAM-Formatierung Hilfsfunktion
    formatRAM: function(bytes) {
        if (!bytes || bytes === 0) return '0 GB';
        const gb = bytes / 1024 / 1024 / 1024;
        return gb.toFixed(2) + ' GB';
    },
    
    // Node auswählen
    selectNode: function(nodeName) {
        console.log('Node selected:', nodeName);
        this.currentNode = nodeName;
        // Diese Funktion wird von anderen JS-Dateien überschrieben
        alert('Node ' + nodeName + ' ausgewählt. Diese Funktion wird von anderen JavaScript-Dateien implementiert.');
    },
    
    // Server-Liste-Tab anzeigen
    showServerListTab: function() {
        // Verstecke alle anderen Tabs
        this.hideAllTabs();
        
        // Zeige den Server-Liste-Tab
        const serverListTab = document.getElementById('server-list-tab');
        if (serverListTab) {
            serverListTab.style.display = 'block';
            console.log('Server list tab shown');
        } else {
            console.error('server-list-tab not found');
        }
    },
    
    // VM-Erstellungsformular anzeigen
    showCreateVMForm: function() {
        // Verstecke alle anderen Tabs
        this.hideAllTabs();
        
        // Zeige das VM-Erstellungsformular
        const vmCreationForm = document.getElementById('vm-creation-form');
        if (vmCreationForm) {
            vmCreationForm.style.display = 'block';
        }
        
        // Lade Formular-Optionen wenn ein Node ausgewählt ist
        if (this.currentNode) {
            this.loadVMFormOptions();
        }
    },
    
    // VM-Formular-Optionen laden
    loadVMFormOptions: async function() {
        if (!this.currentNode) return;
        
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
            
            // ISO-Dateien laden
            this.loadIsoFiles();
            
        } catch (error) {
            console.error('Fehler beim Laden der Formular-Optionen:', error);
        }
    },
    
    // ISO-Dateien laden
    loadIsoFiles: async function() {
        if (!this.currentNode) {
            console.log('loadIsoFiles: Kein currentNode gesetzt');
            return;
        }
        
        console.log('loadIsoFiles: Lade ISO-Dateien für Node', this.currentNode);
        
        try {
            // Lade zuerst die Storage-Liste
            const storageResult = await this.makeModuleRequest('get_proxmox_storages', { node: this.currentNode });
            console.log('Storage result:', storageResult);
            
            if (storageResult.success && storageResult.data && storageResult.data.data) {
                const isoSelect = document.getElementById('vm_ide2');
                if (isoSelect) {
                    console.log('ISO Select gefunden:', isoSelect);
                    // Leere das Dropdown
                    isoSelect.innerHTML = '<option value="">Kein ISO ausgewählt</option>';
                    
                    // Lade ISO-Dateien von allen Storage-Volumes
                    const loadPromises = [];
                    storageResult.data.data.forEach(storage => {
                        if (storage.type === 'dir' || storage.type === 'lvm' || storage.type === 'zfspool') {
                            console.log('Lade ISO-Dateien für Storage:', storage.storage);
                            loadPromises.push(
                                this.makeModuleRequest('get_iso_files', { 
                                    node: this.currentNode, 
                                    storage: storage.storage 
                                }).then(result => ({ storage: storage.storage, result }))
                            );
                        }
                    });
                    
                    const isoResults = await Promise.all(loadPromises);
                    console.log('Alle ISO-Ergebnisse:', isoResults);
                    
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
                } else {
                    console.error('vm_ide2 Select-Element nicht gefunden');
                }
            } else {
                console.log('Keine Storage-Daten erhalten');
            }
        } catch (error) {
            console.error('Fehler beim Laden der ISO-Dateien:', error);
        }
    }
};
