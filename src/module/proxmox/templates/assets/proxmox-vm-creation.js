/**
 * Proxmox Module - VM Creation
 * Verwaltung der VM-Erstellung und -Bearbeitung
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

// VM-Erstellen-Dialog anzeigen
proxmoxModule.showCreateVMDialog = function() {
    // Setze den aktuellen Node
    const nodeField = document.getElementById('vm_node_modal');
    if (nodeField && this.currentNode) {
        nodeField.value = this.currentNode;
    } else {
        alert('Bitte wählen Sie zuerst einen Node aus');
        return;
    }
    
    // Lade Formular-Optionen
    this.loadVMFormOptions();
    
    new bootstrap.Modal(document.getElementById('createVMModal')).show();
};

// VM erstellen (Modal)
proxmoxModule.submitCreateVM = async function() {
    const form = document.getElementById('create-vm-modal-form');
    const formData = new FormData(form);
    
    // Konvertiere FormData zu einem normalen Objekt
    const data = {};
    for (const [key, value] of formData.entries()) {
        data[key] = value;
    }
    
    console.log('Creating VM with data:', data);
    
    try {
        const result = await proxmoxModule.makeModuleRequest('create_vm', data);
        
        if (result.success) {
            // Zeige Debug-Informationen
            if (result.html_debug) {
                // Erstelle ein Modal für die Debug-Informationen
                const debugModal = document.createElement('div');
                debugModal.className = 'modal fade';
                debugModal.innerHTML = `
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Debug: VM-Erstellung Parameter</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                ${result.html_debug}
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(debugModal);
                new bootstrap.Modal(debugModal).show();
                
                // Entferne das Modal nach dem Schließen
                debugModal.addEventListener('hidden.bs.modal', () => {
                    document.body.removeChild(debugModal);
                });
            }
            
            showNotification('Debug-Modus: VM-Parameter angezeigt', 'info');
            form.reset();
            this.loadServerList();
            bootstrap.Modal.getInstance(document.getElementById('createVMModal'))?.hide();
        } else {
            showNotification('Fehler beim Erstellen der VM: ' + result.error, 'error');
        }
    } catch (error) {
        console.error('VM creation error:', error);
        showNotification('Fehler beim Erstellen der VM: ' + error.message, 'error');
    }
};

// VM erstellen (Tab-Formular)
proxmoxModule.createVM = async function(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    // Konvertiere FormData zu einem normalen Objekt
    const data = {};
    for (const [key, value] of formData.entries()) {
        data[key] = value;
    }
    
    console.log('Creating VM with data:', data);
    
    // Debug-Helper aufrufen
    try {
        const debugResult = await this.callDebugHelper(data);
        if (debugResult.success) {
            this.showDebugModal(debugResult.html_modal);
            showNotification('Debug-Informationen angezeigt', 'info');
            return;
        }
    } catch (error) {
        console.error('Debug helper error:', error);
    }
    
    // Fallback: Normale VM-Erstellung
    try {
        const result = await proxmoxModule.makeModuleRequest('create_vm', data);
        
        if (result.success) {
            showNotification('VM erfolgreich erstellt', 'success');
            form.reset();
            this.loadServerList();
        } else {
            showNotification('Fehler beim Erstellen der VM: ' + result.error, 'error');
        }
    } catch (error) {
        console.error('VM creation error:', error);
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
    
    // Konvertiere FormData zu einem normalen Objekt
    const data = {};
    for (const [key, value] of formData.entries()) {
        data[key] = value;
    }
    
    console.log('Updating VM with data:', data);
    
    try {
        const result = await proxmoxModule.makeModuleRequest('update_vm', data);
        
        if (result.success) {
            showNotification('VM erfolgreich aktualisiert', 'success');
            this.loadServerList();
            bootstrap.Modal.getInstance(document.getElementById('editServerModal'))?.hide();
        } else {
            showNotification('Fehler beim Aktualisieren der VM: ' + result.error, 'error');
        }
    } catch (error) {
        console.error('VM update error:', error);
        showNotification('Fehler beim Aktualisieren der VM: ' + error.message, 'error');
    }
};

// ISO-Dateien laden
proxmoxModule.loadIsoFiles = async function() {
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
};

// Debug-Helper aufrufen
proxmoxModule.callDebugHelper = async function(data) {
    try {
        const formData = new FormData();
        formData.append('plugin', 'proxmox');
        formData.append('action', 'create_vm');
        
        // Alle Formulardaten hinzufügen
        for (const [key, value] of Object.entries(data)) {
            formData.append(key, value);
        }
        
        const response = await fetch('module/proxmox/debug_helper.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
    } catch (error) {
        console.error('Debug helper call failed:', error);
        throw error;
    }
};

// Debug-Modal anzeigen
proxmoxModule.showDebugModal = function(htmlModal) {
    // Entferne vorhandenes Debug-Modal
    const existingModal = document.getElementById('debugModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Erstelle neues Debug-Modal
    const debugModal = document.createElement('div');
    debugModal.innerHTML = htmlModal;
    document.body.appendChild(debugModal);
    
    // Zeige das Modal
    const modal = new bootstrap.Modal(document.getElementById('debugModal'));
    modal.show();
    
    // Entferne das Modal nach dem Schließen
    document.getElementById('debugModal').addEventListener('hidden.bs.modal', () => {
        document.getElementById('debugModal').remove();
    });
};