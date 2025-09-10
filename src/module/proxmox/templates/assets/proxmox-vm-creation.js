/**
 * Proxmox Module - VM Creation
 * Verwaltung der VM-Erstellung und -Bearbeitung
 */

// VM-Erstellen-Dialog anzeigen
proxmoxModule.showCreateVMDialog = function() {
    new bootstrap.Modal(document.getElementById('createVMModal')).show();
};

// VM erstellen (Modal)
proxmoxModule.submitCreateVM = async function() {
    const form = document.getElementById('create-vm-modal-form');
    const formData = new FormData(form);
    
    try {
        const result = await proxmoxModule.makeModuleRequest('create_vm', formData);
        
        if (result.success) {
            showNotification('VM erfolgreich erstellt', 'success');
            form.reset();
            this.loadServerList();
            bootstrap.Modal.getInstance(document.getElementById('createVMModal'))?.hide();
        } else {
            showNotification('Fehler beim Erstellen der VM: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Fehler beim Erstellen der VM: ' + error.message, 'error');
    }
};

// VM erstellen (Tab-Formular)
proxmoxModule.createVM = async function(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    try {
        const result = await proxmoxModule.makeModuleRequest('create_vm', formData);
        
        if (result.success) {
            showNotification('VM erfolgreich erstellt', 'success');
            form.reset();
            this.loadServerList();
        } else {
            showNotification('Fehler beim Erstellen der VM: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Fehler beim Erstellen der VM: ' + error.message, 'error');
    }
};

// Server bearbeiten
proxmoxModule.editServer = async function(vmid, node) {
    try {
        const result = await proxmoxModule.makeModuleRequest('get_vm_config', { vmid, node });
        
        if (result.success) {
            const config = result.data;
            document.getElementById('edit_vm_id').value = vmid;
            document.getElementById('edit_node').value = node;
            document.getElementById('edit_vm_name').value = config.name || '';
            document.getElementById('edit_vm_description').value = config.description || '';
            document.getElementById('edit_vm_memory').value = config.memory || 0;
            document.getElementById('edit_vm_cores').value = config.cores || 0;
            
            new bootstrap.Modal(document.getElementById('editServerModal')).show();
        } else {
            showNotification('Fehler beim Abrufen der VM-Konfiguration: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Fehler beim Abrufen der VM-Konfiguration: ' + error.message, 'error');
    }
};

// Server aktualisieren
proxmoxModule.submitUpdateServer = async function() {
    const form = document.getElementById('edit-server-form');
    const formData = new FormData(form);
    
    try {
        const result = await proxmoxModule.makeModuleRequest('update_vm', formData);
        
        if (result.success) {
            showNotification('VM erfolgreich aktualisiert', 'success');
            this.loadServerList();
            bootstrap.Modal.getInstance(document.getElementById('editServerModal'))?.hide();
        } else {
            showNotification('Fehler beim Aktualisieren der VM: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Fehler beim Aktualisieren der VM: ' + error.message, 'error');
    }
};