<div class="card mt-4">
    <div class="card-header">
        <h3 class="mb-0">🗄️ <?= t('database_api_endpoints') ?></h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4 mb-2">
                <button class="btn btn-outline-success w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"admin","action":"get_all_databases"}'>📋 <?= t('all_databases') ?></button>
            </div>
            <div class="col-md-4 mb-2">
                <button class="btn btn-outline-success w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"database","action":"create_database","name":"test_db","user":"test_user","password":"test123"}'>➕ <?= t('create_database') ?></button>
            </div>
            <div class="col-md-4 mb-2">
                <button class="btn btn-outline-success w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"admin","action":"delete_database","database_id":"1"}'>🗑️ <?= t('delete_database') ?></button>
            </div>
        </div>
    </div>
</div>


