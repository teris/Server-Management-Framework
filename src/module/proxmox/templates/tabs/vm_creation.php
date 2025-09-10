<!-- VM-Erstellung Tab -->
<div class="tab-content" id="vm-creation-tab" style="display: none;">
<div class="card">
    <div class="card-header">
        <h3 class="mb-0"><?= t('create_vm_proxmox') ?></h3>
    </div>
    <div class="card-body">
        <form id="create-vm-form" onsubmit="proxmoxModule.createVM(event)">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_name"><?= t('vm_name') ?></label>
                        <input type="text" class="form-control" id="vm_name" name="name" required placeholder="<?= t('example_web_server') ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_id"><?= t('vm_id') ?></label>
                        <input type="number" class="form-control" id="vm_id" name="vmid" required placeholder="100" min="100" max="999999">
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_memory"><?= t('ram_mb') ?></label>
                        <select class="form-control" id="vm_memory" name="memory">
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
                        <label for="vm_cores"><?= t('cpu_cores') ?></label>
                        <select class="form-control" id="vm_cores" name="cores">
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
                        <label for="vm_disk"><?= t('disk_gb') ?></label>
                        <input type="number" class="form-control" id="vm_disk" name="disk" value="20" required min="10">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_node"><?= t('proxmox_node') ?></label>
                        <input type="text" class="form-control" id="vm_node" name="node" value="pve" required>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_storage"><?= t('storage') ?></label>
                        <input type="text" class="form-control" id="vm_storage" name="storage" value="local-lvm" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="vm_bridge"><?= t('network_bridge') ?></label>
                        <input type="text" class="form-control" id="vm_bridge" name="bridge" value="vmbr0" required>
                    </div>
                </div>
            </div>
            
            <div class="form-group mb-3">
                <label for="vm_mac"><?= t('mac_address') ?></label>
                <input type="text" class="form-control" id="vm_mac" name="mac" placeholder="aa:bb:cc:dd:ee:ff" pattern="[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}">
            </div>
            
            <div class="form-group mb-3">
                <label for="vm_iso"><?= t('iso_image') ?></label>
                <input type="text" class="form-control" id="vm_iso" name="iso" value="local:iso/ubuntu-22.04-server-amd64.iso" required>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <span class="loading hidden"></span>
                <?= t('create_vm') ?>
            </button>
        </form>
    </div>
</div>
</div>
