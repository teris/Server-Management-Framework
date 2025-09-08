<div class="card mt-4">
    <div class="card-header">
        <h3 class="mb-0">🔌 <?= t('virtual_mac_api_endpoints') ?></h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-2 mb-2">
                <button class="btn btn-outline-warning w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"virtual-mac","action":"get_all_virtual_macs"}'>📋 <?= t('all_virtual_macs') ?></button>
            </div>
            <div class="col-md-2 mb-2">
                <button class="btn btn-outline-warning w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"virtual-mac","action":"get_dedicated_servers"}'>🖥️ <?= t('dedicated_servers') ?></button>
            </div>
            <div class="col-md-2 mb-2">
                <button class="btn btn-outline-warning w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"virtual-mac","action":"get_virtual_mac_details","service_name":"ns3112327.ip-54-36-111.eu"}'>🔍 <?= t('mac_details') ?></button>
            </div>
            <div class="col-md-2 mb-2">
                <button class="btn btn-outline-warning w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"virtual-mac","action":"create_virtual_mac","service_name":"ns3112327.ip-54-36-111.eu","virtual_network_interface":"eth0","type":"ovh"}'>➕ <?= t('virtual_mac') ?></button>
            </div>
            <div class="col-md-2 mb-2">
                <button class="btn btn-outline-warning w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"virtual-mac","action":"assign_ip_to_virtual_mac","service_name":"ns3112327.ip-54-36-111.eu","mac_address":"02:00:00:96:1f:85","ip_address":"192.168.1.100","virtual_machine_name":"test-vm"}'>🌐 <?= t('assign_ip') ?></button>
            </div>
            <div class="col-md-2 mb-2">
                <button class="btn btn-outline-warning w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"virtual-mac","action":"create_reverse_dns","ip_address":"192.168.1.100","reverse":"test.example.com"}'>🔄 <?= t('reverse_dns') ?></button>
            </div>
        </div>
    </div>
</div>


