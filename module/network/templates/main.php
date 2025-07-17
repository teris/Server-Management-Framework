<div id="network-content">
    <div class="card">
        <div class="card-header">
            <h2 class="mb-0">🔧 <?php echo $translations['vm_network_configuration']; ?></h2>
        </div>
        <div class="card-body">
            <form onsubmit="updateVMNetwork(event)">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="net_vmid"><?php echo $translations['vm_id']; ?></label>
                            <input type="number" class="form-control" id="net_vmid" name="vmid" required placeholder="100" min="100">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="net_mac"><?php echo $translations['mac_address']; ?></label>
                            <input type="text" class="form-control" id="net_mac" name="mac" required placeholder="aa:bb:cc:dd:ee:ff" pattern="[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}">
                        </div>
                    </div>
                </div>
                
                <div class="form-group mb-3">
                    <label for="net_ip"><?php echo $translations['ip_address']; ?></label>
                    <input type="text" class="form-control" id="net_ip" name="ip" required placeholder="192.168.1.100">
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <span class="loading hidden"></span>
                    <?php echo $translations['update_network']; ?>
                </button>
            </form>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">📚 <?php echo $translations['helpful_information']; ?></h3>
                </div>
                <div class="card-body">
                    <h4><?php echo $translations['mac_address_format']; ?></h4>
                    <p><?php echo $translations['mac_format_description']; ?></p>
                    <p><?php echo $translations['examples']; ?>:</p>
                    <ul>
                        <li><?php echo $translations['ovh_virtual_mac']; ?>: <code>02:00:00:xx:xx:xx</code></li>
                        <li><?php echo $translations['vmware_mac']; ?>: <code>00:50:56:xx:xx:xx</code></li>
                        <li><?php echo $translations['kvm_qemu_mac']; ?>: <code>52:54:00:xx:xx:xx</code></li>
                    </ul>
                    
                    <h4><?php echo $translations['ip_configuration']; ?></h4>
                    <p><?php echo $translations['ip_config_description']; ?></p>
                    <ul>
                        <li><?php echo $translations['dhcp_mac_binding']; ?></li>
                        <li><?php echo $translations['cloud_init']; ?></li>
                        <li><?php echo $translations['manual_vm']; ?></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">⚙️ <?php echo $translations['advanced_network_options']; ?></h3>
                </div>
                <div class="card-body">
                    <p class="text-muted fst-italic">
                        <?php echo $translations['advanced_network_description']; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Network Module JavaScript
window.networkModule = {
    init: function() {
        console.log('Network module initialized');
        this.loadTranslations();
    },
    
    translations: {},
    
    loadTranslations: function() {
        // Lade Übersetzungen vom Server mit neuem Format
        ModuleManager.makeRequest('network', 'get_translations')
            .then(data => {
                if (data.success) {
                    this.translations = data.translations;
                }
            })
            .catch(error => console.error('Error loading translations:', error));
    },
    
    t: function(key, params = {}) {
        let text = this.translations[key] || key;
        
        // Parameter ersetzen
        Object.keys(params).forEach(param => {
            text = text.replace(`{${param}}`, params[param]);
        });
        
        return text;
    }
};

// Form Handler
async function updateVMNetwork(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    setLoading(form, true);
    
    try {
        const result = await ModuleManager.makeRequest('network', 'update_vm_network', formData);
        
        if (result.success) {
            showNotification(networkModule.t('vm_network_updated_message'), 'success');
            form.reset();
        } else {
            showNotification('Fehler: ' + (result.error || networkModule.t('unknown_error')), 'error');
        }
    } catch (error) {
        showNotification(networkModule.t('network_error') + ': ' + error.message, 'error');
    }
    
    setLoading(form, false);
}
</script>