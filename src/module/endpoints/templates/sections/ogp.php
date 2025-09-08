<div class="card mt-4">
    <div class="card-header">
        <h3 class="mb-0">🎮 <?= t('ogp_api_endpoints') ?></h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3 mb-2">
                <button class="btn btn-outline-secondary w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"ogp","action":"list_servers"}'>🖥️ <?= t('ogp_list_servers') ?></button>
            </div>
            <div class="col-md-3 mb-2">
                <button class="btn btn-outline-secondary w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"ogp","action":"list_games"}'>🎲 <?= t('ogp_list_games') ?></button>
            </div>
            <div class="col-md-3 mb-2">
                <button class="btn btn-outline-secondary w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"ogp","action":"list_user_servers"}'>👤 <?= t('ogp_list_user_servers') ?></button>
            </div>
            <div class="col-md-3 mb-2">
                <button class="btn btn-outline-secondary w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"ogp","action":"server_status","server_id":"1"}'>📊 <?= t('ogp_server_status') ?></button>
            </div>
        </div>
    </div>
</div>


