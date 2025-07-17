<div id="endpoints-content">
    <div class="card">
        <div class="card-header">
            <h2 class="mb-0">🔌 <?php echo $translations['api_endpoints_tester']; ?></h2>
        </div>
        <div class="card-body">
            <p><?php echo $translations['test_api_endpoints']; ?></p>
        </div>
    </div>
    
    <!-- Proxmox Endpoints -->
    <div class="card mt-4">
        <div class="card-header">
            <h3 class="mb-0">🖥️ <?php echo $translations['proxmox_api_endpoints']; ?></h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-2 mb-2">
                    <button class="btn btn-outline-primary w-100" onclick="testEndpoint('proxmox', 'get_proxmox_nodes')">📡 <?php echo $translations['load_nodes']; ?></button>
                </div>
                <div class="col-md-2 mb-2">
                    <button class="btn btn-outline-primary w-100" onclick="testEndpointWithParam('proxmox', 'get_proxmox_storages', 'node', 'pve')">💾 <?php echo $translations['load_storages']; ?></button>
                </div>
                <div class="col-md-2 mb-2">
                    <button class="btn btn-outline-primary w-100" onclick="testEndpointWithParams('proxmox', 'get_vm_config', {node: 'pve', vmid: '100'})">⚙️ <?php echo $translations['vm_config']; ?></button>
                </div>
                <div class="col-md-2 mb-2">
                    <button class="btn btn-outline-primary w-100" onclick="testEndpointWithParams('proxmox', 'get_vm_status', {node: 'pve', vmid: '100'})">📊 <?php echo $translations['vm_status']; ?></button>
                </div>
                <div class="col-md-2 mb-2">
                    <button class="btn btn-outline-primary w-100" onclick="testEndpointWithParams('proxmox', 'clone_vm', {node: 'pve', vmid: '100', newid: '101', name: 'clone-test'})">📋 <?php echo $translations['clone_vm']; ?></button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ISPConfig Endpoints -->
    <div class="endpoint-section">
        <h3>🌐 ISPConfig API Endpoints</h3>
        <div class="endpoint-buttons">
            <button class="btn" onclick="testEndpoint('ispconfig', 'get_ispconfig_clients')">👥 Clients laden</button>
            <button class="btn" onclick="testEndpoint('ispconfig', 'get_ispconfig_server_config')">⚙️ Server Config</button>
            <button class="btn" onclick="testEndpointWithParam('ispconfig', 'get_website_details', 'domain_id', '1')">🌐 Website Details</button>
            <button class="btn" onclick="testEndpointWithParams('ispconfig', 'create_ftp_user', {domain_id: '1', username: 'test', password: 'test123', quota: '500'})">👤 FTP User Test</button>
        </div>
    </div>
    
    <!-- OVH Endpoints -->
    <div class="endpoint-section">
        <h3>🔗 OVH API Endpoints</h3>
        <div class="endpoint-buttons">
            <button class="btn" onclick="testEndpointWithParam('ovh', 'get_ovh_domain_zone', 'domain', 'example.com')">🌐 Domain Zone</button>
            <button class="btn" onclick="testEndpointWithParam('ovh', 'get_ovh_dns_records', 'domain', 'example.com')">📝 DNS Records</button>
            <button class="btn" onclick="testEndpointWithParam('ovh', 'get_vps_ips', 'vps_name', 'vpsXXXXX.ovh.net')">🌐 VPS IPs</button>
            <button class="btn" onclick="testEndpointWithParams('ovh', 'get_vps_ip_details', {vps_name: 'vpsXXXXX.ovh.net', ip: '1.2.3.4'})">📊 IP Details</button>
            <button class="btn" onclick="testEndpointWithParams('ovh', 'control_ovh_vps', {vps_name: 'vpsXXXXX.ovh.net', vps_action: 'reboot'})">🔄 VPS Control</button>
            <button class="btn" onclick="testEndpointWithParams('ovh', 'create_dns_record', {domain: 'example.com', type: 'A', subdomain: 'test', target: '1.2.3.4'})">➕ DNS Record</button>
            <button class="btn" onclick="testEndpointWithParam('ovh', 'refresh_dns_zone', 'domain', 'example.com')">🔄 DNS Refresh</button>
            <button class="btn" onclick="testEndpoint('ovh', 'get_ovh_failover_ips')">📋 Failover IPs</button>
        </div>
    </div>
    
    <!-- Virtual MAC Endpoints -->
    <div class="endpoint-section">
        <h3>🔌 Virtual MAC API Endpoints</h3>
        <div class="endpoint-buttons">
            <button class="btn" onclick="testEndpoint('virtual-mac', 'get_all_virtual_macs')">📋 Alle Virtual MACs</button>
            <button class="btn" onclick="testEndpoint('virtual-mac', 'get_dedicated_servers')">🖥️ Dedicated Servers</button>
            <button class="btn" onclick="testEndpointWithParam('virtual-mac', 'get_virtual_mac_details', 'service_name', 'ns3112327.ip-54-36-111.eu')">🔍 MAC Details</button>
            <button class="btn" onclick="testEndpointWithParams('virtual-mac', 'create_virtual_mac', {service_name: 'ns3112327.ip-54-36-111.eu', virtual_network_interface: 'eth0', type: 'ovh'})">➕ Virtual MAC</button>
            <button class="btn" onclick="testEndpointWithParams('virtual-mac', 'assign_ip_to_virtual_mac', {service_name: 'ns3112327.ip-54-36-111.eu', mac_address: '02:00:00:96:1f:85', ip_address: '192.168.1.100', virtual_machine_name: 'test-vm'})">🌐 IP zuweisen</button>
            <button class="btn" onclick="testEndpointWithParams('virtual-mac', 'create_reverse_dns', {ip_address: '192.168.1.100', reverse: 'test.example.com'})">🔄 Reverse DNS</button>
        </div>
    </div>
    
    <!-- Database Endpoints -->
    <div class="endpoint-section">
        <h3>🗄️ Database API Endpoints</h3>
        <div class="endpoint-buttons">
            <button class="btn" onclick="testEndpoint('admin', 'get_all_databases')">📋 Alle Datenbanken</button>
            <button class="btn" onclick="testEndpointWithParams('database', 'create_database', {name: 'test_db', user: 'test_user', password: 'test123'})">➕ DB erstellen</button>
            <button class="btn" onclick="testEndpointWithParam('admin', 'delete_database', 'database_id', '1')">🗑️ DB löschen</button>
        </div>
    </div>
    
    <!-- Email Endpoints -->
    <div class="endpoint-section">
        <h3>📧 Email API Endpoints</h3>
        <div class="endpoint-buttons">
            <button class="btn" onclick="testEndpoint('admin', 'get_all_emails')">📋 Alle E-Mails</button>
            <button class="btn" onclick="testEndpointWithParams('email', 'create_email', {email: 'test@example.com', login: 'test', password: 'test123', quota: '1000', domain: 'example.com'})">➕ Email erstellen</button>
            <button class="btn" onclick="testEndpointWithParam('admin', 'delete_email', 'mailuser_id', '1')">🗑️ Email löschen</button>
        </div>
    </div>
    
    <!-- System Endpoints -->
    <div class="endpoint-section">
        <h3>⚙️ System Endpoints</h3>
        <div class="endpoint-buttons">
            <button class="btn" onclick="testEndpoint('admin', 'get_activity_log')">📜 Activity Log</button>
            <button class="btn" onclick="testHeartbeat()">💓 Session Heartbeat</button>
        </div>
    </div>
    
    <!-- Custom Test -->
    <div class="endpoint-section">
        <h3>🔧 Custom Endpoint Test</h3>
        <form onsubmit="testCustomEndpoint(event)">
            <div class="form-row">
                <div class="form-group">
                    <label for="custom_module">Module</label>
                    <select id="custom_module" name="module" required>
                        <option value="">Module wählen...</option>
                        <?php foreach (getEnabledModules() as $key => $module): ?>
                        <option value="<?= $key ?>"><?= $module['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="custom_action">Action</label>
                    <input type="text" id="custom_action" name="action" required placeholder="get_all_items">
                </div>
            </div>
            
            <div class="form-group">
                <label for="custom_params">Parameters (JSON)</label>
                <textarea id="custom_params" name="params" rows="3" placeholder='{"param1": "value1", "param2": "value2"}'></textarea>
            </div>
            
            <button type="submit" class="btn">
                Test Endpoint
            </button>
        </form>
    </div>
    
    <!-- Result Display -->
    <div id="endpoint-result" class="result-box hidden">
        <h4>🔍 Endpoint Response:</h4>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <span id="response-status" style="font-weight: bold;"></span>
            <button class="btn btn-secondary" onclick="copyResponse()">📋 Kopieren</button>
        </div>
        <pre id="endpoint-response" style="background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; max-height: 400px;"></pre>
    </div>
</div>

<script>
// Endpoints Module JavaScript
window.endpointsModule = {
    init: function() {
        console.log('Endpoints module initialized');
    },
    
    lastResponse: null,
    
    displayResult: function(module, action, result) {
        document.getElementById('endpoint-result').classList.remove('hidden');
        
        const statusEl = document.getElementById('response-status');
        if (result.success) {
            statusEl.textContent = '✅ Success';
            statusEl.style.color = '#10b981';
        } else {
            statusEl.textContent = '❌ Error';
            statusEl.style.color = '#ef4444';
        }
        
        const response = {
            module: module,
            action: action,
            timestamp: new Date().toISOString(),
            response: result
        };
        
        this.lastResponse = JSON.stringify(response, null, 2);
        document.getElementById('endpoint-response').textContent = this.lastResponse;
    },
    
    copyResponse: function() {
        if (this.lastResponse) {
            navigator.clipboard.writeText(this.lastResponse).then(() => {
                showNotification('Response kopiert!', 'success');
            }).catch(() => {
                showNotification('Kopieren fehlgeschlagen', 'error');
            });
        }
    }
};

// Test Functions
async function testEndpoint(module, action) {
    try {
        showNotification(`Testing ${module}.${action}...`, 'info');
        const result = await ModuleManager.makeRequest(module, action);
        endpointsModule.displayResult(module, action, result);
    } catch (error) {
        endpointsModule.displayResult(module, action, {success: false, error: error.message});
    }
}

async function testEndpointWithParam(module, action, paramName, paramValue) {
    try {
        showNotification(`Testing ${module}.${action}...`, 'info');
        const params = {};
        params[paramName] = paramValue;
        const result = await ModuleManager.makeRequest(module, action, params);
        endpointsModule.displayResult(module, action, result);
    } catch (error) {
        endpointsModule.displayResult(module, action, {success: false, error: error.message});
    }
}

async function testEndpointWithParams(module, action, params) {
    try {
        showNotification(`Testing ${module}.${action}...`, 'info');
        const result = await ModuleManager.makeRequest(module, action, params);
        endpointsModule.displayResult(module, action, result);
    } catch (error) {
        endpointsModule.displayResult(module, action, {success: false, error: error.message});
    }
}

async function testHeartbeat() {
    try {
        const response = await fetch('?heartbeat=1');
        const result = await response.json();
        endpointsModule.displayResult('system', 'heartbeat', result);
    } catch (error) {
        endpointsModule.displayResult('system', 'heartbeat', {success: false, error: error.message});
    }
}

async function testCustomEndpoint(event) {
    event.preventDefault();
    const form = event.target;
    const module = form.module.value;
    const action = form.action.value;
    let params = {};
    
    if (form.params.value) {
        try {
            params = JSON.parse(form.params.value);
        } catch (e) {
            showNotification('Invalid JSON in parameters', 'error');
            return;
        }
    }
    
    await testEndpointWithParams(module, action, params);
}

function copyResponse() {
    endpointsModule.copyResponse();
}

// Legacy support for old calls
function displayEndpointResult(action, result) {
    endpointsModule.displayResult('unknown', action, result);
}
</script>