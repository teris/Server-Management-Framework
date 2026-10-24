<?php
if (PHP_SAPI !== 'cli' && (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__ || (get_included_files()[0] ?? null) === __FILE__)) { http_response_code(404); exit; }
?>
<!-- Header mit Aktionen -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="mb-0">🖥️ <?= t('server_management') ?></h2>
        <div>
            <button class="btn btn-secondary" onclick="proxmoxModule.refreshServerList()">
                <i class="fas fa-sync-alt"></i> <?= t('refresh') ?>
            </button>
        </div>
    </div>
</div>
