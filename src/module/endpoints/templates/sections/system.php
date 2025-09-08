<div class="card mt-4">
    <div class="card-header">
        <h3 class="mb-0">⚙️ <?= t('system_endpoints') ?></h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-2">
                <button class="btn btn-outline-dark w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"admin","action":"get_activity_log"}'>📜 <?= t('activity_log') ?></button>
            </div>
            <div class="col-md-6 mb-2">
                <button class="btn btn-outline-dark w-100 endpoints-test-btn" data-action="testHeartbeat">💓 <?= t('session_heartbeat') ?></button>
            </div>
        </div>
    </div>
</div>


