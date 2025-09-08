<div class="card mt-4">
    <div class="card-header">
        <h3 class="mb-0">🖥️ <?= t('proxmox_api_endpoints') ?></h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-2 mb-2">
                <button class="btn btn-outline-primary w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"proxmox","action":"get_proxmox_nodes"}'>📡 <?= t('load_nodes') ?></button>
            </div>
            <div class="col-md-2 mb-2">
                <button class="btn btn-outline-primary w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"proxmox","action":"get_proxmox_storages","node":"pve"}'>💾 <?= t('load_storages') ?></button>
            </div>
            <div class="col-md-2 mb-2">
                <button class="btn btn-outline-primary w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"proxmox","action":"get_vm_config","node":"pve","vmid":"100"}'>⚙️ <?= t('vm_config')    ?></button>
            </div>
            <div class="col-md-2 mb-2">
                <button class="btn btn-outline-primary w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"proxmox","action":"get_vm_status","node":"pve","vmid":"100"}'>📊 <?= t('vm_status') ?></button>
            </div>
            <div class="col-md-2 mb-2">
                <button class="btn btn-outline-primary w-100 endpoints-test-btn" data-module="endpoints" data-action="proxy_endpoint" data-params='{"module":"proxmox","action":"clone_vm","node":"pve","vmid":"100","newid":"101","name":"clone-test"}'>📋 <?= t('clone_vm') ?></button>
            </div>
        </div>
    </div>
</div>


