<div id="endpoints-content">
    <!-- Aktionsergebnis anzeigen -->
    <div id="action-result" style="display: none;"></div>
    
    <div class="card">
        <div class="card-header">
            <h2 class="mb-0">🔌 <?= t('api_endpoints_tester') ?></h2>
        </div>
        <div class="card-body">
            <p><?= t('test_api_endpoints') ?></p>
        </div>
    </div>
    
    <!-- Proxmox Endpoints -->
    <div class="card mt-4">
        <div class="card-header">
            <h3 class="mb-0">🖥️ <?= t('proxmox_api_endpoints') ?></h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-2 mb-2">
                    <button class="btn btn-outline-primary w-100 endpoints-test-btn" data-module="proxmox" data-action="get_proxmox_nodes">📡 <?= t('load_nodes') ?></button>
                </div>
                <div class="col-md-2 mb-2">
                    <button class="btn btn-outline-primary w-100 endpoints-test-btn" data-module="proxmox" data-action="get_proxmox_storages" data-param-name="node" data-param-value="pve">💾 <?= t('load_storages') ?></button>
                </div>
                <div class="col-md-2 mb-2">
                    <button class="btn btn-outline-primary w-100 endpoints-test-btn" data-module="proxmox" data-action="get_vm_config" data-params='{"node": "pve", "vmid": "100"}'>⚙️ <?= t('vm_config')    ?></button>
                </div>
                <div class="col-md-2 mb-2">
                                <button class="btn btn-outline-primary w-100 endpoints-test-btn" data-module="proxmox" data-action="get_vm_status" data-params='{"node": "pve", "vmid": "100"}'>📊 <?= t('vm_status') ?></button>
                </div>
                <div class="col-md-2 mb-2">
                    <button class="btn btn-outline-primary w-100 endpoints-test-btn" data-module="proxmox" data-action="clone_vm" data-params='{"node": "pve", "vmid": "100", "newid": "101", "name": "clone-test"}'>📋 <?= t('clone_vm') ?></button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ISPConfig Endpoints -->
    <div class="card mt-4">
        <div class="card-header">
            <h3 class="mb-0">🌐 ISPConfig API Endpoints</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-2">
                    <button class="btn btn-outline-secondary w-100 endpoints-test-btn" data-module="ispconfig" data-action="get_ispconfig_clients">👥 <?= t('load_clients') ?></button>
                </div>
                <div class="col-md-3 mb-2">
                    <button class="btn btn-outline-secondary w-100 endpoints-test-btn" data-module="ispconfig" data-action="get_ispconfig_server_config">⚙️ <?= t('server_config') ?></button>
                </div>
                <div class="col-md-3 mb-2">
                    <button class="btn btn-outline-secondary w-100 endpoints-test-btn" data-module="ispconfig" data-action="get_website_details" data-param-name="domain_id" data-param-value="1">🌐 <?= t('website_details') ?></button>
                </div>
                <div class="col-md-3 mb-2">
                    <button class="btn btn-outline-secondary w-100 endpoints-test-btn" data-module="ispconfig" data-action="create_ftp_user" data-params='{"domain_id": "1", "username": "test", "password": "test123", "quota": "500"}'>👤 <?= t('ftp_user_test') ?></button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- OVH Endpoints -->
    <div class="card mt-4">
        <div class="card-header">
            <h3 class="mb-0">🔗 <?= t('ovh_api_endpoints') ?></h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-2">
                    <button class="btn btn-outline-info w-100 endpoints-test-btn" data-module="ovh" data-action="get_ovh_domain_zone" data-param-name="domain" data-param-value="example.com">🌐 <?= t('domain_zone') ?></button>
                </div>
                <div class="col-md-3 mb-2">
                    <button class="btn btn-outline-info w-100 endpoints-test-btn" data-module="ovh" data-action="get_ovh_dns_records" data-param-name="domain" data-param-value="example.com">📝 <?= t('dns_records') ?></button>
                </div>
                <div class="col-md-3 mb-2">
                    <button class="btn btn-outline-info w-100 endpoints-test-btn" data-module="ovh" data-action="get_vps_ips" data-param-name="vps_name" data-param-value="vpsXXXXX.ovh.net">🌐 <?= t('vps_ips') ?></button>
                </div>
                <div class="col-md-3 mb-2">
                    <button class="btn btn-outline-info w-100 endpoints-test-btn" data-module="ovh" data-action="get_vps_ip_details" data-params='{"vps_name": "vpsXXXXX.ovh.net", "ip": "1.2.3.4"}'>📊 <?= t('ip_details') ?></button>
                </div>
                <div class="col-md-3 mb-2">
                    <button class="btn btn-outline-info w-100 endpoints-test-btn" data-module="ovh" data-action="control_ovh_vps" data-params='{"vps_name": "vpsXXXXX.ovh.net", "vps_action": "reboot"}'>🔄 <?= t('vps_control') ?></button>
                </div>
                <div class="col-md-3 mb-2">
                    <button class="btn btn-outline-info w-100 endpoints-test-btn" data-module="ovh" data-action="create_dns_record" data-params='{"domain": "example.com", "type": "A", "subdomain": "test", "target": "1.2.3.4"}'>➕ <?= t('dns_record') ?></button>
                </div>
                <div class="col-md-3 mb-2">
                    <button class="btn btn-outline-info w-100 endpoints-test-btn" data-module="ovh" data-action="refresh_dns_zone" data-param-name="domain" data-param-value="example.com">🔄 <?= t('dns_refresh') ?></button>
                </div>
                <div class="col-md-3 mb-2">
                    <button class="btn btn-outline-info w-100 endpoints-test-btn" data-module="ovh" data-action="get_ovh_failover_ips">📋 <?= t('failover_ips') ?></button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Virtual MAC Endpoints -->
    <div class="card mt-4">
        <div class="card-header">
            <h3 class="mb-0">🔌 <?= t('virtual_mac_api_endpoints') ?></h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-2 mb-2">
                    <button class="btn btn-outline-warning w-100 endpoints-test-btn" data-module="virtual-mac" data-action="get_all_virtual_macs">📋 <?= t('all_virtual_macs') ?></button>
                </div>
                <div class="col-md-2 mb-2">
                    <button class="btn btn-outline-warning w-100 endpoints-test-btn" data-module="virtual-mac" data-action="get_dedicated_servers">🖥️ <?= t('dedicated_servers') ?></button>
                </div>
                <div class="col-md-2 mb-2">
                    <button class="btn btn-outline-warning w-100 endpoints-test-btn" data-module="virtual-mac" data-action="get_virtual_mac_details" data-param-name="service_name" data-param-value="ns3112327.ip-54-36-111.eu">🔍 <?= t('mac_details') ?></button>
                </div>
                <div class="col-md-2 mb-2">
                    <button class="btn btn-outline-warning w-100 endpoints-test-btn" data-module="virtual-mac" data-action="create_virtual_mac" data-params='{"service_name": "ns3112327.ip-54-36-111.eu", "virtual_network_interface": "eth0", "type": "ovh"}'>➕ <?= t('virtual_mac') ?></button>
                </div>
                <div class="col-md-2 mb-2">
                    <button class="btn btn-outline-warning w-100 endpoints-test-btn" data-module="virtual-mac" data-action="assign_ip_to_virtual_mac" data-params='{"service_name": "ns3112327.ip-54-36-111.eu", "mac_address": "02:00:00:96:1f:85", "ip_address": "192.168.1.100", "virtual_machine_name": "test-vm"}'>🌐 <?= t('assign_ip') ?></button>
                </div>
                <div class="col-md-2 mb-2">
                    <button class="btn btn-outline-warning w-100 endpoints-test-btn" data-module="virtual-mac" data-action="create_reverse_dns" data-params='{"ip_address": "192.168.1.100", "reverse": "test.example.com"}'>🔄 <?= t('reverse_dns') ?></button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Database Endpoints -->
    <div class="card mt-4">
        <div class="card-header">
            <h3 class="mb-0">🗄️ <?= t('database_api_endpoints') ?></h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-2">
                    <button class="btn btn-outline-success w-100 endpoints-test-btn" data-module="admin" data-action="get_all_databases">📋 <?= t('all_databases') ?></button>
                </div>
                <div class="col-md-4 mb-2">
                    <button class="btn btn-outline-success w-100 endpoints-test-btn" data-module="database" data-action="create_database" data-params='{"name": "test_db", "user": "test_user", "password": "test123"}'>➕ <?= t('create_database') ?></button>
                </div>
                <div class="col-md-4 mb-2">
                    <button class="btn btn-outline-success w-100 endpoints-test-btn" data-module="admin" data-action="delete_database" data-param-name="database_id" data-param-value="1">🗑️ <?= t('delete_database') ?></button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Email Endpoints -->
    <div class="card mt-4">
        <div class="card-header">
            <h3 class="mb-0">📧 <?= t('email_api_endpoints') ?></h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-2">
                    <button class="btn btn-outline-danger w-100 endpoints-test-btn" data-module="admin" data-action="get_all_emails">📋 <?= t('all_emails') ?></button>
                </div>
                <div class="col-md-4 mb-2">
                    <button class="btn btn-outline-danger w-100 endpoints-test-btn" data-module="email" data-action="create_email" data-params='{"email": "test@example.com", "login": "test", "password": "test123", "quota": "1000", "domain": "example.com"}'>➕ <?= t('create_email') ?></button>
                </div>
                <div class="col-md-4 mb-2">
                        <button class="btn btn-outline-danger w-100 endpoints-test-btn" data-module="admin" data-action="delete_email" data-param-name="mailuser_id" data-param-value="1">🗑️ <?= t('delete_email') ?></button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- System Endpoints -->
    <div class="card mt-4">
        <div class="card-header">
            <h3 class="mb-0">⚙️ <?= t('system_endpoints') ?></h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-2">
                    <button class="btn btn-outline-dark w-100 endpoints-test-btn" data-module="admin" data-action="get_activity_log">📜 <?= t('activity_log') ?></button>
                </div>
                <div class="col-md-6 mb-2">
                    <button class="btn btn-outline-dark w-100 endpoints-test-btn" data-action="testHeartbeat">💓 <?= t('session_heartbeat') ?></button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Custom Test -->
    <div class="card mt-4">
        <div class="card-header">
            <h3 class="mb-0">🔧 <?= t('custom_endpoint_test') ?></h3>
        </div>
        <div class="card-body">
            <form class="endpoints-custom-form">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="custom_module"><?= t('module') ?></label>
                            <select class="form-control" id="custom_module" name="module" required>
                                <option value=""><?= t('select_module') ?></option>
                                <?php foreach (getEnabledModules() as $key => $module): ?>
                                <option value="<?= $key ?>"><?= $module['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="custom_action"><?= t('action') ?></label>
                            <input type="text" class="form-control" id="custom_action" name="action" required placeholder="get_all_items">
                        </div>
                    </div>
                </div>
                
                <div class="form-group mb-3">
                    <label for="custom_params">Parameters (JSON)</label>
                    <textarea class="form-control" id="custom_params" name="params" rows="3" placeholder='{"param1": "value1", "param2": "value2"}'></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <?= t('test_endpoint') ?>
                </button>
            </form>
        </div>
    </div>
    
    <!-- Result Display -->
    <div class="card mt-4">
        <div class="card-header">
            <h4 class="mb-0">🔍 <?= t('endpoint_response') ?></h4>
        </div>
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span id="endpoint-status" class="fw-bold"></span>
                    <button class="btn btn-secondary endpoints-test-btn" data-action="copyResponse">📋 <?= t('copy') ?></button>
            </div>
            <pre id="endpoint-response" class="bg-light p-3 rounded" style="overflow-x: auto; max-height: 400px; white-space: pre-wrap;"></pre>
        </div>
    </div>
</div>

<script src="module/endpoints/assets/module.js"></script>