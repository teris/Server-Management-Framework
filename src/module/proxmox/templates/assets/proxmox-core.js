/**
 * Proxmox Module - Core JavaScript
 * Hauptmodul mit Initialisierung und Basis-Funktionen
 */

window.proxmoxModule = {
    currentServer: null,
    
    init: function() {
        console.log('Proxmox module initialized');
        // Lade nur die Nodes-Übersicht, keine VMs/LXCs
        this.loadNodesOverview();
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
    }
};
