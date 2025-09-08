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
    
    <?php include __DIR__ . '/sections/proxmox.php'; ?>
    
    <?php include __DIR__ . '/sections/ispconfig.php'; ?>
    
    <?php include __DIR__ . '/sections/ovh.php'; ?>
    
    <?php include __DIR__ . '/sections/virtual-mac.php'; ?>
    
    <?php include __DIR__ . '/sections/database.php'; ?>
    
    <?php include __DIR__ . '/sections/email.php'; ?>
    
    <?php include __DIR__ . '/sections/system.php'; ?>
    
    <?php include __DIR__ . '/sections/ogp.php'; ?>

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