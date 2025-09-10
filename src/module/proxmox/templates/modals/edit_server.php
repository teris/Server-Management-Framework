<!-- Server-Bearbeiten-Modal -->
<div class="modal fade" id="editServerModal" tabindex="-1" aria-labelledby="editServerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editServerModalLabel"><?= t('edit_server_config') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= t('close') ?>"></button>
            </div>
            <div class="modal-body">
                <form id="edit-server-form" onsubmit="proxmoxModule.updateServer(event)">
                    <input type="hidden" id="edit_vm_id" name="vmid">
                    <input type="hidden" id="edit_node" name="node">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="edit_vm_name"><?= t('server_name') ?></label>
                                <input type="text" class="form-control" id="edit_vm_name" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="edit_vm_description"><?= t('server_description') ?></label>
                                <input type="text" class="form-control" id="edit_vm_description" name="description">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="edit_vm_memory"><?= t('memory_mb') ?></label>
                                <input type="number" class="form-control" id="edit_vm_memory" name="memory" min="512" step="512">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="edit_vm_cores"><?= t('cpu_cores_count') ?></label>
                                <input type="number" class="form-control" id="edit_vm_cores" name="cores" min="1" max="32">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                <button type="button" class="btn btn-primary" onclick="proxmoxModule.submitUpdateServer()">
                    <span class="loading hidden"></span>
                    <?= t('update_server') ?>
                </button>
            </div>
        </div>
    </div>
</div>
