<?php
/**
 * Server Management Framework
 * 
 * @author Teris
 * @version 3.1.2
 */

if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    require_once dirname(__DIR__) . '/../config/config.inc.php';
    require_once dirname(__DIR__) . '/../core/DatabaseManager.php';
    header('Content-Type: application/json');
    try {
        $db = DatabaseManager::getInstance();
        if ($_POST['action'] === 'delete_user' && isset($_POST['user_id'])) {
            $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
            $db->execute($stmt, [$_POST['user_id']]);
            echo json_encode(['success' => true]);
            exit;
        }
        if ($_POST['action'] === 'delete_group' && isset($_POST['group_id'])) {
            $stmt = $db->prepare('DELETE FROM groups WHERE id = ?');
            $db->execute($stmt, [$_POST['group_id']]);
            echo json_encode(['success' => true]);
            exit;
        }
        echo json_encode(['success' => false, 'error' => 'Unbekannte Aktion oder fehlende Parameter.']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}
?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h2 class="mb-0"><i class="bi bi-graph-up"></i> <?= t('overview') ?></h2>
            </div>
            <div class="card-body">
                <!-- Statistik-Karten -->
                <div class="row mb-4">
                    <?php foreach ($dashboardStats as $key => $stat): ?>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <div class="card border-0 bg-light" data-stat="<?= $key ?>">
                            <div class="card-body text-center">
                                <h5 class="card-title text-muted"><?= htmlspecialchars($stat['label']) ?></h5>
                                <div class="display-6 fw-bold text-primary" id="<?= $key ?>-count"><?= $stat['count'] ?></div>
                                <?php if (isset($stat['status'])): ?>
                                <span class="badge bg-<?= $stat['status'] === 'running' ? 'success' : ($stat['status'] === 'stopped' ? 'danger' : 'warning') ?>">
                                    <?= $stat['status_text'] ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Admin-Navigation -->
                <div class="mb-4">
                    <h3><i class="bi bi-gear"></i> <?= t('administration') ?></h3>
                    <ul class="nav nav-tabs" id="adminTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#admin-overview" type="button" role="tab">
                                <i class="bi bi-graph-up"></i> <?= t('overview') ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="plugins-tab" data-bs-toggle="tab" data-bs-target="#admin-plugins" type="button" role="tab">
                                <i class="bi bi-puzzle"></i> <?= t('plugins') ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#admin-logs" type="button" role="tab">
                                <i class="bi bi-journal-text"></i> <?= t('logs') ?>
                            </button>
                        </li>
                    </ul>
                </div>
                
                <!-- Admin-Inhalte -->
                <div class="tab-content" id="adminTabContent">
                    <!-- Übersicht -->
                    <div class="tab-pane fade show active" id="admin-overview" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <h4><?= t('system_overview') ?></h4>
                                <div class="list-group list-group-flush">
                                    <div class="list-group-item d-flex justify-content-between">
                                        <span><strong><?= t('php_version') ?>:</strong></span>
                                        <span><?= phpversion() ?></span>
                                    </div>
                                    <div class="list-group-item d-flex justify-content-between">
                                        <span><strong><?= t('server') ?>:</strong></span>
                                        <span><?= $_SERVER['SERVER_SOFTWARE'] ?? t('unknown') ?></span>
                                    </div>
                                    <div class="list-group-item d-flex justify-content-between">
                                        <span><strong><?= t('active_sessions') ?>:</strong></span>
                                        <span id="active-sessions">-</span>
                                    </div>
                                    <div class="list-group-item d-flex justify-content-between">
                                        <span><strong><?= t('system_load') ?>:</strong></span>
                                        <span id="system-load">-</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h4><?= t('quick_actions') ?></h4>
                                <div class="d-grid gap-2">
                                    <button class="btn btn-primary" onclick="refreshAllStats()">
                                        <i class="bi bi-arrow-clockwise"></i> <?= t('refresh_all_stats') ?>
                                    </button>
                                    <button class="btn btn-secondary" onclick="clearCache()">
                                        <i class="bi bi-trash"></i> <?= t('clear_cache') ?>
                                    </button>
                                    <button class="btn btn-warning" onclick="testAllConnections()">
                                        <i class="bi bi-plug"></i> <?= t('test_connections') ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                                    
                    <!-- Plugin-Verwaltung -->
                    <div class="tab-pane fade" id="admin-plugins" role="tabpanel">
                        <h4><?= t('available_plugins') ?></h4>
                        <div class="row">
                            <?php foreach ($pluginManager->getEnabledPlugins() as $plugin_key => $plugin_info): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><?= htmlspecialchars($plugin_info['name'] ?? $plugin_key) ?></h5>
                                        <p class="card-text text-muted"><?= htmlspecialchars($plugin_info['description'] ?? t('no_description_available')) ?></p>
                                        <a href="?option=<?= $plugin_key ?>" class="btn btn-primary btn-sm">
                                            <i class="bi bi-box-arrow-up-right"></i> <?= t('open') ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Logs -->
                    <div class="tab-pane fade" id="admin-logs" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4><?= t('system_logs') ?></h4>
                            <button class="btn btn-primary btn-sm" onclick="loadLogs()">
                                <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                            </button>
                        </div>
                        <div id="logs-content">
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden"><?= t('loading') ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>