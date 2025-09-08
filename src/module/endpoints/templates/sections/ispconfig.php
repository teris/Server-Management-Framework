<div class="card mt-4">
    <div class="card-header">
        <h3 class="mb-0">🌐 ISPConfig API Endpoints</h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3 mb-2">
                <button class="btn btn-outline-secondary w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"ispconfig","action":"get_ispconfig_clients"}'>👥 <?= t('load_clients') ?></button>
            </div>
            <div class="col-md-3 mb-2">
                <button class="btn btn-outline-secondary w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"ispconfig","action":"get_ispconfig_server_config"}'>⚙️ <?= t('server_config') ?></button>
            </div>
            <div class="col-md-3 mb-2">
                <button class="btn btn-outline-secondary w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"ispconfig","action":"get_website_details","domain_id":"1"}'>🌐 <?= t('website_details') ?></button>
            </div>
            <div class="col-md-3 mb-2">
                <button class="btn btn-outline-secondary w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"ispconfig","action":"create_ftp_user","domain_id":"1","username":"test","password":"test123","quota":"500"}'>👤 <?= t('ftp_user_test') ?></button>
            </div>
        </div>
    </div>
</div>


