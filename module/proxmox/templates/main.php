<div id="proxmox" class="tab-content">
    <h2>🖥️ VM auf Proxmox anlegen</h2>
    <form onsubmit="createVM(event)">
        <div class="form-row">
            <div class="form-group">
                <label for="vm_name">VM Name</label>
                <input type="text" id="vm_name" name="name" required placeholder="z.B. web-server-01">
            </div>
            <div class="form-group">
                <label for="vm_id">VM ID</label>
                <input type="number" id="vm_id" name="vmid" required placeholder="100" min="100" max="999999">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="vm_memory">RAM (MB)</label>
                <select id="vm_memory" name="memory">
                    <option value="1024">1 GB</option>
                    <option value="2048">2 GB</option>
                    <option value="4096" selected>4 GB</option>
                    <option value="8192">8 GB</option>
                    <option value="16384">16 GB</option>
                </select>
            </div>
            <div class="form-group">
                <label for="vm_cores">CPU Kerne</label>
                <select id="vm_cores" name="cores">
                    <option value="1">1 Kern</option>
                    <option value="2" selected>2 Kerne</option>
                    <option value="4">4 Kerne</option>
                    <option value="8">8 Kerne</option>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="vm_disk">Festplatte (GB)</label>
                <input type="number" id="vm_disk" name="disk" value="20" required min="10">
            </div>
            <div class="form-group">
                <label for="vm_node">Proxmox Node</label>
                <input type="text" id="vm_node" name="node" value="pve" required>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="vm_storage">Storage</label>
                <input type="text" id="vm_storage" name="storage" value="local-lvm" required>
            </div>
            <div class="form-group">
                <label for="vm_bridge">Netzwerk Bridge</label>
                <input type="text" id="vm_bridge" name="bridge" value="vmbr0" required>
            </div>
        </div>
        
        <div class="form-group">
            <label for="vm_mac">MAC Adresse (optional)</label>
            <input type="text" id="vm_mac" name="mac" placeholder="aa:bb:cc:dd:ee:ff" pattern="[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}">
        </div>
        
        <div class="form-group">
            <label for="vm_iso">ISO Image</label>
            <input type="text" id="vm_iso" name="iso" value="local:iso/ubuntu-22.04-server-amd64.iso" required>
        </div>
        
        <button type="submit" class="btn">
            <span class="loading hidden"></span>
            VM erstellen
        </button>
    </form>
    
    <!-- Zusätzliche Features -->
    <hr>
    
    <div class="endpoint-section">
        <h3>🔧 Erweiterte Funktionen</h3>
        <div class="endpoint-buttons">
            <button class="btn btn-secondary" onclick="ModuleManager.makeRequest('proxmox', 'get_proxmox_nodes').then(r => console.log(r))">
                📡 Nodes abrufen
            </button>
            <button class="btn btn-secondary" onclick="loadStorages()">
                💾 Storages laden
            </button>
            <button class="btn btn-secondary" onclick="showCloneDialog()">
                📋 VM klonen
            </button>
        </div>
    </div>
    
    <!-- Clone Dialog (versteckt) -->
    <div id="clone-dialog" class="hidden" style="margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
        <h4>📋 VM Klonen</h4>
        <form onsubmit="cloneVM(event)">
            <div class="form-row">
                <div class="form-group">
                    <label for="clone_node">Node</label>
                    <input type="text" id="clone_node" name="node" value="pve" required>
                </div>
                <div class="form-group">
                    <label for="clone_source">Quell-VM ID</label>
                    <input type="number" id="clone_source" name="vmid" required placeholder="100">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="clone_newid">Neue VM ID</label>
                    <input type="number" id="clone_newid" name="newid" required placeholder="101">
                </div>
                <div class="form-group">
                    <label for="clone_name">Neuer Name</label>
                    <input type="text" id="clone_name" name="name" required placeholder="clone-vm-01">
                </div>
            </div>
            <button type="submit" class="btn">VM klonen</button>
            <button type="button" class="btn btn-secondary" onclick="hideCloneDialog()">Abbrechen</button>
        </form>
    </div>
</div>

<script>
// Proxmox Module JavaScript
window.proxmoxModule = {
    init: function() {
        console.log('Proxmox module initialized');
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
            showNotification('Bitte erst einen Node angeben', 'error');
            return;
        }
        
        try {
            const result = await ModuleManager.makeRequest('proxmox', 'get_proxmox_storages', { node: node });
            if (result.success) {
                console.log('Available storages:', result.data);
                showNotification('Storages geladen - siehe Konsole', 'success');
            } else {
                showNotification('Fehler: ' + result.error, 'error');
            }
        } catch (error) {
            showNotification('Netzwerkfehler', 'error');
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

// Form Handler mit Module Support
async function createVM(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    setLoading(form, true);
    
    try {
        const result = await ModuleManager.makeRequest('proxmox', 'create_vm', formData);
        
        if (result.success) {
            showNotification('VM wurde erfolgreich erstellt!', 'success');
            form.reset();
        } else {
            showNotification('Fehler: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
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
            showNotification('VM wurde erfolgreich geklont!', 'success');
            form.reset();
            hideCloneDialog();
        } else {
            showNotification('Fehler: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
    
    setLoading(form, false);
}
</script>