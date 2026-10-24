/**
 * Proxmox Module - Confirmation Modal
 * Modal für Bestätigungen von VM/LXC-Aktionen
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

// Bestätigungs-Modal anzeigen
proxmoxModule.showConfirmationModal = function(message, action, vmid, node, type = 'qemu') {
    const modal = document.getElementById('confirmationModal');
    const messageElement = document.getElementById('confirmationMessage');
    const confirmButton = document.getElementById('confirmAction');
    
    if (!modal || !messageElement || !confirmButton) {
        console.error('Confirmation modal elements not found');
        return;
    }
    
    // Nachricht setzen
    messageElement.innerHTML = message;
    
    // Button-Text und -Farbe je nach Aktion anpassen
    let buttonClass = 'btn-danger';
    let buttonText = 'Bestätigen';
    let buttonIcon = 'fas fa-check';
    
    switch(action) {
        case 'start':
            buttonClass = 'btn-success';
            buttonText = 'Starten';
            buttonIcon = 'fas fa-play';
            break;
        case 'stop':
            buttonClass = 'btn-warning';
            buttonText = 'Stoppen';
            buttonIcon = 'fas fa-stop';
            break;
        case 'restart':
            buttonClass = 'btn-info';
            buttonText = 'Neustart';
            buttonIcon = 'fas fa-redo';
            break;
        case 'reset':
            buttonClass = 'btn-warning';
            buttonText = 'Zurücksetzen';
            buttonIcon = 'fas fa-undo';
            break;
        case 'resume':
            buttonClass = 'btn-success';
            buttonText = 'Fortsetzen';
            buttonIcon = 'fas fa-play';
            break;
        case 'delete':
            buttonClass = 'btn-danger';
            buttonText = 'Löschen';
            buttonIcon = 'fas fa-trash';
            break;
    }
    
    confirmButton.className = `btn ${buttonClass}`;
    confirmButton.innerHTML = `<i class="${buttonIcon}"></i> ${buttonText}`;
    
    // Event-Listener für Bestätigung
    confirmButton.onclick = function() {
        // Modal schließen
        const bootstrapModal = bootstrap.Modal.getInstance(modal);
        if (bootstrapModal) {
            bootstrapModal.hide();
        }
        
        // Aktion ausführen
        proxmoxModule.executeVMAction(action, vmid, node, type);
    };
    
    // Modal anzeigen
    const bootstrapModal = new bootstrap.Modal(modal);
    bootstrapModal.show();
};

// VM/LXC-Aktion ausführen
proxmoxModule.executeVMAction = async function(action, vmid, node, type = 'qemu') {
    try {
        let result;
        
        switch(action) {
            case 'start':
                result = await this.makeModuleRequest('start_vm', { vmid, node, type });
                break;
            case 'stop':
                result = await this.makeModuleRequest('stop_vm', { vmid, node, type });
                break;
            case 'restart':
                result = await this.makeModuleRequest('restart_vm', { vmid, node, type });
                break;
            case 'reset':
                result = await this.makeModuleRequest('reset_vm', { vmid, node, type });
                break;
            case 'resume':
                result = await this.makeModuleRequest('resume_vm', { vmid, node, type });
                break;
            case 'delete':
                result = await this.makeModuleRequest('delete_vm', { vmid, node, type });
                break;
            default:
                showNotification('Unbekannte Aktion: ' + action, 'error');
                return;
        }
        
        if (result.success) {
            const actionTexts = {
                'start': 'gestartet',
                'stop': 'gestoppt',
                'restart': 'neu gestartet',
                'reset': 'zurückgesetzt',
                'resume': 'fortgesetzt',
                'delete': 'gelöscht'
            };
            
            showNotification(`Server erfolgreich ${actionTexts[action]}`, 'success');
            this.loadServerList();
        } else {
            showNotification(`Fehler beim ${actionTexts[action] || action} des Servers: ` + result.error, 'error');
        }
    } catch (error) {
        showNotification(`Fehler beim ${action} des Servers: ` + error.message, 'error');
    }
};

// Wrapper-Funktionen für die verschiedenen Aktionen
proxmoxModule.startServer = function(vmid, node, type = 'qemu') {
    const message = `Möchten Sie den Server (${type.toUpperCase()} ${vmid}) wirklich starten?`;
    this.showConfirmationModal(message, 'start', vmid, node, type);
};

proxmoxModule.stopServer = function(vmid, node, type = 'qemu') {
    const message = `Möchten Sie den Server (${type.toUpperCase()} ${vmid}) wirklich stoppen?`;
    this.showConfirmationModal(message, 'stop', vmid, node, type);
};

proxmoxModule.restartServer = function(vmid, node, type = 'qemu') {
    const message = `Möchten Sie den Server (${type.toUpperCase()} ${vmid}) wirklich neu starten?`;
    this.showConfirmationModal(message, 'restart', vmid, node, type);
};

proxmoxModule.resetServer = function(vmid, node, type = 'qemu') {
    const message = `Möchten Sie den Server (${type.toUpperCase()} ${vmid}) wirklich zurücksetzen?<br><small class="text-muted">Dies ist ein Hard-Reset und kann Datenverlust verursachen!</small>`;
    this.showConfirmationModal(message, 'reset', vmid, node, type);
};

proxmoxModule.resumeServer = function(vmid, node, type = 'qemu') {
    const message = `Möchten Sie den Server (${type.toUpperCase()} ${vmid}) wirklich fortsetzen?`;
    this.showConfirmationModal(message, 'resume', vmid, node, type);
};

proxmoxModule.deleteServer = function(vmid, node, type = 'qemu') {
    const message = `Möchten Sie den Server (${type.toUpperCase()} ${vmid}) wirklich löschen?<br><small class="text-muted">Diese Aktion kann nicht rückgängig gemacht werden!</small>`;
    this.showConfirmationModal(message, 'delete', vmid, node, type);
};
