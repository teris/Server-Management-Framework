<div class="card mt-4">
    <div class="card-header">
        <h3 class="mb-0">🔗 <?= t('ovh_api_endpoints') ?></h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3 mb-2">
                <button class="btn btn-outline-info w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"ovh","action":"get_ovh_domain_zone","domain":"example.com"}'>🌐 <?= t('domain_zone') ?></button>
            </div>
            <div class="col-md-3 mb-2">
                <button class="btn btn-outline-info w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"ovh","action":"get_ovh_dns_records","domain":"example.com"}'>📝 <?= t('dns_records') ?></button>
            </div>
            <div class="col-md-3 mb-2">
                <button class="btn btn-outline-info w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"ovh","action":"get_vps_ips","vps_name":"vpsXXXXX.ovh.net"}'>🌐 <?= t('vps_ips') ?></button>
            </div>
            <div class="col-md-3 mb-2">
                <button class="btn btn-outline-info w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"ovh","action":"get_vps_ip_details","vps_name":"vpsXXXXX.ovh.net","ip":"1.2.3.4"}'>📊 <?= t('ip_details') ?></button>
            </div>
            <div class="col-md-3 mb-2">
                <button class="btn btn-outline-info w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"ovh","action":"control_ovh_vps","vps_name":"vpsXXXXX.ovh.net","vps_action":"reboot"}'>🔄 <?= t('vps_control') ?></button>
            </div>
            <div class="col-md-3 mb-2">
                <button class="btn btn-outline-info w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"ovh","action":"create_dns_record","domain":"example.com","type":"A","subdomain":"test","target":"1.2.3.4"}'>➕ <?= t('dns_record') ?></button>
            </div>
            <div class="col-md-3 mb-2">
                <button class="btn btn-outline-info w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"ovh","action":"refresh_dns_zone","domain":"example.com"}'>🔄 <?= t('dns_refresh') ?></button>
            </div>
            <div class="col-md-3 mb-2">
                <button class="btn btn-outline-info w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"ovh","action":"get_ovh_failover_ips"}'>📋 <?= t('failover_ips') ?></button>
            </div>
        </div>
    </div>
</div>


