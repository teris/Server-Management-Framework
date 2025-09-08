<div class="card mt-4">
    <div class="card-header">
        <h3 class="mb-0">📧 <?= t('email_api_endpoints') ?></h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4 mb-2">
                <button class="btn btn-outline-danger w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"admin","action":"get_all_emails"}'>📋 <?= t('all_emails') ?></button>
            </div>
            <div class="col-md-4 mb-2">
                <button class="btn btn-outline-danger w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"email","action":"create_email","email":"test@example.com","login":"test","password":"test123","quota":"1000","domain":"example.com"}'>➕ <?= t('create_email') ?></button>
            </div>
            <div class="col-md-4 mb-2">
                <button class="btn btn-outline-danger w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"admin","action":"delete_email","mailuser_id":"1"}'>🗑️ <?= t('delete_email') ?></button>
            </div>
        </div>
    </div>
</div>


