<div id="proxmox-content">
    <div class="card">
        <div class="card-header">
            <h2 class="mb-0">🖥️ <?php echo $translations['create_vm_proxmox']; ?></h2>
        </div>
        <div class="card-body">
            <form onsubmit="createVM(event)">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="vm_name"><?php echo $translations['vm_name']; ?></label>
                            <input type="text" class="form-control" id="vm_name" name="name" required placeholder="<?php echo $translations['example_web_server']; ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="vm_id"><?php echo $translations['vm_id']; ?></label>
                            <input type="number" class="form-control" id="vm_id" name="vmid" required placeholder="100" min="100" max="999999">
                        </div>
                    </div>
                </div>
        
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="vm_memory"><?php echo $translations['ram_mb']; ?></label>
                            <select class="form-control" id="vm_memory" name="memory">
                                <option value="1024"><?php echo $translations['one_gb']; ?></option>
                                <option value="2048"><?php echo $translations['two_gb']; ?></option>
                                <option value="4096" selected><?php echo $translations['four_gb']; ?></option>
                                <option value="8192"><?php echo $translations['eight_gb']; ?></option>
                                <option value="16384"><?php echo $translations['sixteen_gb']; ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="vm_cores"><?php echo $translations['cpu_cores']; ?></label>
                            <select class="form-control" id="vm_cores" name="cores">
                                <option value="1"><?php echo $translations['one_core']; ?></option>
                                <option value="2" selected><?php echo $translations['two_cores']; ?></option>
                                <option value="4"><?php echo $translations['four_cores']; ?></option>
                                <option value="8"><?php echo $translations['eight_cores']; ?></option>
                            </select>
                        </div>
                    </div>
                </div>
        
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="vm_disk"><?php echo $translations['disk_gb']; ?></label>
                            <input type="number" class="form-control" id="vm_disk" name="disk" value="20" required min="10">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="vm_node"><?php echo $translations['proxmox_node']; ?></label>
                            <input type="text" class="form-control" id="vm_node" name="node" value="pve" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="vm_storage"><?php echo $translations['storage']; ?></label>
                            <input type="text" class="form-control" id="vm_storage" name="storage" value="local-lvm" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="vm_bridge"><?php echo $translations['network_bridge']; ?></label>
                            <input type="text" class="form-control" id="vm_bridge" name="bridge" value="vmbr0" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group mb-3">
                    <label for="vm_mac"><?php echo $translations['mac_address']; ?></label>
                    <input type="text" class="form-control" id="vm_mac" name="mac" placeholder="aa:bb:cc:dd:ee:ff" pattern="[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}">
                </div>
                
                <div class="form-group mb-3">
                    <label for="vm_iso"><?php echo $translations['iso_image']; ?></label>
                    <input type="text" class="form-control" id="vm_iso" name="iso" value="local:iso/ubuntu-22.04-server-amd64.iso" required>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <span class="loading hidden"></span>
                    <?php echo $translations['create_vm']; ?>
                </button>
            </form>
        </div>
    </div>
    
    <!-- Zusätzliche Features -->
    <div class="card mt-4">
        <div class="card-header">
            <h3 class="mb-0">🔧 <?php echo $translations['extended_features']; ?></h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <button class="btn btn-secondary w-100" onclick="ModuleManager.makeRequest('proxmox', 'get_proxmox_nodes').then(r => console.log(r))">
                        📡 <?php echo $translations['get_nodes']; ?>
                    </button>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-secondary w-100" onclick="loadStorages()">
                        💾 <?php echo $translations['load_storages']; ?>
                    </button>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-secondary w-100" onclick="showCloneDialog()">
                        📋 <?php echo $translations['clone_vm']; ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Clone Dialog (versteckt) -->
    <div id="clone-dialog" class="card mt-4 hidden">
        <div class="card-header">
            <h4 class="mb-0">📋 <?php echo $translations['clone_vm_dialog']; ?></h4>
        </div>
        <div class="card-body">
            <form onsubmit="cloneVM(event)">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="clone_node"><?php echo $translations['node']; ?></label>
                            <input type="text" class="form-control" id="clone_node" name="node" value="pve" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="clone_source"><?php echo $translations['source_vm_id']; ?></label>
                            <input type="number" class="form-control" id="clone_source" name="vmid" required placeholder="100">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="clone_newid"><?php echo $translations['new_vm_id']; ?></label>
                            <input type="number" class="form-control" id="clone_newid" name="newid" required placeholder="101">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="clone_name"><?php echo $translations['new_name']; ?></label>
                            <input type="text" class="form-control" id="clone_name" name="name" required placeholder="<?php echo $translations['clone_vm_01']; ?>">
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary"><?php echo $translations['clone_vm']; ?></button>
                <button type="button" class="btn btn-secondary" onclick="hideCloneDialog()"><?php echo $translations['cancel']; ?></button>
            </form>
        </div>
    </div>
</div>

<script>
// Proxmox Module JavaScript
window.proxmoxModule = {
    init: function() {
        console.log('Proxmox module initialized');
        this.loadTranslations();
    },
    
    loadTranslations: async function() {
        try {
            const result = await ModuleManager.makeRequest('proxmox', 'get_translations');
            if (result.success) {
                window.translations = result.data;
            }
        } catch (error) {
            console.error('Error loading translations:', error);
        }
    },
    
    t: function(key, params = {}) {
        let text = window.translations[key] || key;
        
        // Parameter ersetzen: {param} -> value
        Object.keys(params).forEach(param => {
            text = text.replace(new RegExp(`{${param}}`, 'g'), params[param]);
        });
        
        return text;
    },
    
    showCloneDialog: function() {
        document.getElementById('clone-dialog').classList.remove('hidden');
    },
    
    hideCloneDialog: function() {
        document.getElementById('clone-dialog').classList.add('hidden');
    },
    
    loadStorages: async function() {
        const node = document.getElementById('vm_node').value;
        if (!node) {
            showNotification(t('please_enter_node'), 'error');
            return;
        }
        
        try {
            const result = await ModuleManager.makeRequest('proxmox', 'get_proxmox_storages', { node: node });
            if (result.success) {
                console.log(t('available_storages') + ':', result.data);
                showNotification(t('storages_loaded'), 'success');
            } else {
                showNotification(t('error_getting_storages') + ': ' + result.error, 'error');
            }
        } catch (error) {
            showNotification(t('network_error'), 'error');
        }
    }
};

// Global functions für Kompatibilität
function showCloneDialog() {
    proxmoxModule.showCloneDialog();
}

function hideCloneDialog() {
    proxmoxModule.hideCloneDialog();
}

function loadStorages() {
    proxmoxModule.loadStorages();
}

// Globale Übersetzungsfunktion
function t(key, params = {}) {
    return proxmoxModule.t(key, params);
}

// Form Handler mit Module Support
async function createVM(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    setLoading(form, true);
    
    try {
        const result = await ModuleManager.makeRequest('proxmox', 'create_vm', formData);
        
        if (result.success) {
            showNotification(t('vm_created'), 'success');
            form.reset();
        } else {
            showNotification(t('error_creating_vm') + ': ' + (result.error || t('unknown_error')), 'error');
        }
    } catch (error) {
        showNotification(t('network_error') + ': ' + error.message, 'error');
    }
    
    setLoading(form, false);
}

async function cloneVM(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    setLoading(form, true);
    
    try {
        const result = await ModuleManager.makeRequest('proxmox', 'clone_vm', formData);
        
        if (result.success) {
            showNotification(t('vm_cloned'), 'success');
            form.reset();
            hideCloneDialog();
        } else {
            showNotification(t('error_cloning_vm') + ': ' + (result.error || t('unknown_error')), 'error');
        }
    } catch (error) {
        showNotification(t('network_error') + ': ' + error.message, 'error');
    }
    
    setLoading(form, false);
}
</script>