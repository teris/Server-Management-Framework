/**
 * Proxmox Module - Initialization
 * Modul-Initialisierung und Event-Handler
 */

// Initialisierung
document.addEventListener('DOMContentLoaded', function() {
    if (window.proxmoxModule) {
        window.proxmoxModule.init();
    }
});
