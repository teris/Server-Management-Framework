<!-- Header mit Aktionen -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="mb-0">🖥️ <?= t('server_management') ?></h2>
        <div>
            <button class="btn btn-primary me-2" onclick="proxmoxModule.showCreateVMDialog()">
                <i class="fas fa-plus"></i> <?= t('create_vm') ?>
            </button>
            <button class="btn btn-secondary" onclick="proxmoxModule.refreshServerList()">
                <i class="fas fa-sync-alt"></i> <?= t('refresh') ?>
            </button>
        </div>
    </div>
</div>
