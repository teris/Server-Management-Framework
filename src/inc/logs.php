<?php
/**
 * Server Management Framework
 * 
 * @author Teris
 * @version 3.1.2
 */

//$lang = LanguageManager::getInstance();

$limit = 30;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

//$core = new AdminCore();
$logs = $adminCore->getActivityLogs([], $limit, $offset);

$db = Database::getInstance();
$stmt = $db->getConnection()->query('SELECT COUNT(*) FROM activity_log');
$total_logs = $stmt->fetchColumn();
$total_pages = ceil($total_logs / $limit);
?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header"><h2 class="mb-0"><i class="bi bi-journal-text"></i> <?= $lang->translateCore('logs') ?></h2></div>
            
            <div class="card-body">
                <div class="log-table-area">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2><i class="bi bi-journal-text"></i> <?= $lang->translateCore('logs') ?></h2>
                        <button id="clear-logs-btn" class="btn btn-danger btn-sm"><i class="bi bi-trash"></i> <?= $lang->translateCore('clear_logs') ?></button>
                    </div>
                    <div id="logs-table-wrapper">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th><?= $lang->translateCore('created') ?></th>
                                    <th><?= $lang->translateCore('status') ?></th>
                                    <th><?= $lang->translateCore('action') ?></th>
                                    <th><?= $lang->translateCore('details') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($logs)): ?>
                                <tr><td colspan="4"><?= $lang->translateCore('no_data') ?></td></tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($log['created_at_formatted'] ?? $log['created_at']) ?></td>
                                        <td>
                                    <?php 
                                    switch($log['status']):
                                        case "success":
                                            echo '<i class="bi bi-bookmark-check" style="color:rgb(6, 173, 0);"></i>';
                                            break;
                                        case "error":
                                            echo '<i class="bi bi-bug" style="color:rgb(204, 0, 0);"></i>';
                                            break;
                                        case "info":
                                            echo '<i class="bi bi-info-circle" style="color:rgb(206, 168, 0);"></i>';
                                            break;
                                        case "pending":
                                            echo '<i class="bi bi-hourglass" style="color:rgb(0, 98, 173);"></i>';
                                            break;
                                        default:
                                            echo '<i class="bi bi-patch-question"></i>';
                                            break;
                                    endswitch;
                                    ?>
                                </td>
                                        <td><?= htmlspecialchars($log['action']) ?></td>
                                        <td><?= htmlspecialchars($log['details']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a class="btn btn-primary stretched-link" href="?option=logs&page=<?= $page - 1 ?>">&laquo; <?= $lang->translateCore('previous') ?></a>
                            <?php else: ?>
                                <span class="disabled">&laquo; <?= $lang->translateCore('previous') ?></span>
                            <?php endif; ?>
                            <span><?= $lang->translateCore('page') ?> <?= $page ?> / <?= $total_pages ?></span>
                            <?php if ($page < $total_pages): ?>
                                <a class="btn btn-primary stretched-link" href="?option=logs&page=<?= $page + 1 ?>"><?= $lang->translateCore('next') ?> &raquo;</a>
                            <?php else: ?>
                                <span class="disabled"><?= $lang->translateCore('next') ?> &raquo;</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- JavaScript-Code wurde in assets/inc-js/logs.js ausgelagert -->
                </div> 
            </div>
        </div>
    </div>
</div>