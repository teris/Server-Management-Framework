/**
 * Proxmox Module - Extended Features
 * Erweiterte Funktionen wie Klonen, Storage-Management, etc.
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

// Clone Dialog Funktionen
proxmoxModule.showCloneDialog = function() {
    document.getElementById('clone-dialog').classList.remove('hidden');
};

proxmoxModule.hideCloneDialog = function() {
    document.getElementById('clone-dialog').classList.add('hidden');
};

// VM klonen
proxmoxModule.cloneVM = async function(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    try {
        const result = await proxmoxModule.makeModuleRequest('clone_vm', formData);
        
        if (result.success) {
            showNotification('VM erfolgreich geklont', 'success');
            form.reset();
            this.hideCloneDialog();
            this.loadServerList();
        } else {
            showNotification('Fehler beim Klonen der VM: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Fehler beim Klonen der VM: ' + error.message, 'error');
    }
};

// Storages laden
proxmoxModule.loadStorages = async function() {
    const node = document.getElementById('vm_node').value;
    if (!node) {
        showNotification('Bitte geben Sie einen Node ein', 'error');
        return;
    }
    
    try {
        const result = await proxmoxModule.makeModuleRequest('get_proxmox_storages', { node: node });
        if (result.success) {
            console.log('Verfügbare Storages:', result.data);
            showNotification('Storages erfolgreich geladen', 'success');
        } else {
            showNotification('Fehler beim Laden der Storages: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler', 'error');
    }
};