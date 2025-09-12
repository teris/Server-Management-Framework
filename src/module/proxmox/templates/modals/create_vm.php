<!-- VM-Erstellen-Modal -->
<div class="modal fade" id="createVMModal" tabindex="-1" aria-labelledby="createVMModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createVMModalLabel"><?= t('create_vm_proxmox') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= t('close') ?>"></button>
            </div>
            <div class="modal-body">
                <form id="create-vm-modal-form" onsubmit="proxmoxModule.createVM(event)">
                    <!-- Verstecktes Node-Feld -->
                    <input type="hidden" name="node" id="vm_node_modal">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="vm_name_modal"><?= t('vm_name') ?></label>
                                <input type="text" class="form-control" id="vm_name_modal" name="name" required placeholder="<?= t('example_web_server') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="vm_id_modal"><?= t('vm_id') ?></label>
                                <input type="number" class="form-control" id="vm_id_modal" name="vmid" required placeholder="100" min="100" max="999999">
                            </div>
                        </div>
                    </div>
            
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="vm_memory_modal"><?= t('ram_mb') ?></label>
                                <select class="form-control" id="vm_memory_modal" name="memory">
                                    <option value="1024"><?= t('one_gb') ?></option>
                                    <option value="2048"><?= t('two_gb') ?></option>
                                    <option value="4096" selected><?= t('four_gb') ?></option>
                                    <option value="8192"><?= t('eight_gb') ?></option>
                                    <option value="16384"><?= t('sixteen_gb') ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="vm_cores_modal"><?= t('cpu_cores') ?></label>
                                <select class="form-control" id="vm_cores_modal" name="cores">
                                    <option value="1"><?= t('one_core') ?></option>
                                    <option value="2" selected><?= t('two_cores') ?></option>
                                    <option value="4"><?= t('four_cores') ?></option>
                                    <option value="8"><?= t('eight_cores') ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
            
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="vm_disk_modal"><?= t('disk_gb') ?></label>
                                <input type="number" class="form-control" id="vm_disk_modal" name="disk" value="20" required min="10">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="vm_node_modal"><?= t('proxmox_node') ?></label>
                                <input type="text" class="form-control" id="vm_node_modal" name="node" value="pve" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="vm_storage_modal"><?= t('storage') ?></label>
                                <input type="text" class="form-control" id="vm_storage_modal" name="storage" value="local-lvm" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="vm_bridge_modal"><?= t('network_bridge') ?></label>
                                <input type="text" class="form-control" id="vm_bridge_modal" name="bridge" value="vmbr0" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="vm_mac_modal"><?= t('mac_address') ?></label>
                        <input type="text" class="form-control" id="vm_mac_modal" name="mac" placeholder="aa:bb:cc:dd:ee:ff" pattern="[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}">
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="vm_iso_modal"><?= t('iso_image') ?></label>
                        <input type="text" class="form-control" id="vm_iso_modal" name="iso" value="local:iso/ubuntu-22.04-server-amd64.iso" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                <button type="button" class="btn btn-primary" onclick="proxmoxModule.submitCreateVM()">
                    <span class="loading hidden"></span>
                    <?= t('create_vm') ?>
                </button>
            </div>
        </div>
    </div>
</div>
