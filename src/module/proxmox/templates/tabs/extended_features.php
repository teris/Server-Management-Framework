<!-- Erweiterte Features Tab -->
<div class="tab-content" id="extended-features-tab" style="display: none;">
<div class="card">
    <div class="card-header">
        <h3 class="mb-0">🔧 <?= t('extended_features') ?></h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <button class="btn btn-secondary w-100" onclick="ModuleManager.makeRequest('proxmox', 'get_proxmox_nodes').then(r => console.log(r))">
                    📡 <?= t('get_nodes') ?>
                </button>
            </div>
            <div class="col-md-4">
                <button class="btn btn-secondary w-100" onclick="proxmoxModule.loadStorages()">
                    💾 <?= t('load_storages') ?>
                </button>
            </div>
            <div class="col-md-4">
                <button class="btn btn-secondary w-100" onclick="proxmoxModule.showCloneDialog()">
                    📋 <?= t('clone_vm') ?>
                </button>
            </div>
        </div>
        
        <!-- Clone Dialog (versteckt) -->
        <div id="clone-dialog" class="card mt-4 hidden">
            <div class="card-header">
                <h4 class="mb-0">📋 <?= t('clone_vm_dialog') ?></h4>
            </div>
            <div class="card-body">
                <form onsubmit="proxmoxModule.cloneVM(event)">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="clone_node"><?= t('node') ?></label>
                                <input type="text" class="form-control" id="clone_node" name="node" value="pve" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="clone_source"><?= t('source_vm_id') ?></label>
                                <input type="number" class="form-control" id="clone_source" name="vmid" required placeholder="100">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="clone_newid"><?= t('new_vm_id') ?></label>
                                <input type="number" class="form-control" id="clone_newid" name="newid" required placeholder="101">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="clone_name"><?= t('new_name') ?></label>
                                <input type="text" class="form-control" id="clone_name" name="name" required placeholder="<?= t('clone_vm_01') ?>">
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><?= t('clone_vm') ?></button>
                    <button type="button" class="btn btn-secondary" onclick="proxmoxModule.hideCloneDialog()"><?= t('cancel') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
</div>
