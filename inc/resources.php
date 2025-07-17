<?php
require_once dirname(__DIR__) . '/core/AdminCore.php';
$core = new AdminCore();

$vms = $core->getResources('vms');
$websites = $core->getResources('websites');
$databases = $core->getResources('databases');
$emails = $core->getResources('emails');
$domains = $core->getResources('domains');

// Websites nach Hauptdomain (system_user/system_group) und Subdomain/AliasDomain gruppieren
$grouped = [];
$aliasDomains = [];
foreach ($websites as $site) {
    $user = $site['system_user'] ?? '';
    $group = $site['system_group'] ?? '';
    if (empty($user) && empty($group)) {
        // AliasDomain
        $aliasDomains[] = $site;
    } else {
        $key = $user . '|' . $group;
        if (!isset($grouped[$key])) {
            $grouped[$key] = [
                'main' => $site,
                'subdomains' => []
            ];
        } else {
            $grouped[$key]['subdomains'][] = $site;
        }
    }
}
?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h2 class="mb-0"><i class="bi bi-hdd-stack"></i> <?= t('resources') ?></h2>
            </div>
            <div class="card-body">
                <ul class="nav nav-pills mb-3" id="resourceTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="vms-tab" data-bs-toggle="pill" data-bs-target="#resource-vms" type="button" role="tab">
                            <i class="bi bi-display"></i> <?= t('vms') ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="websites-tab" data-bs-toggle="pill" data-bs-target="#resource-websites" type="button" role="tab">
                            <i class="bi bi-globe"></i> <?= t('websites') ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="databases-tab" data-bs-toggle="pill" data-bs-target="#resource-databases" type="button" role="tab">
                            <i class="bi bi-database"></i> <?= t('databases') ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="emails-tab" data-bs-toggle="pill" data-bs-target="#resource-emails" type="button" role="tab">
                            <i class="bi bi-envelope"></i> <?= t('emails') ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="domains-tab" data-bs-toggle="pill" data-bs-target="#resource-domains" type="button" role="tab">
                            <i class="bi bi-link-45deg"></i> <?= t('domains') ?>
                        </button>
                    </li>
                </ul>
                <div class="tab-content" id="resourceTabContent">
                    <!-- VM Management -->
                    <div class="tab-pane fade show active" id="resource-vms" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4><?= t('virtual_machines') ?></h4>
                            <a class="btn btn-primary btn-sm" href="?option=resources">
                                <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                            </a>
                        </div>
                        <div id="vm-content" class="table-responsive">
                            <?php if (!empty($vms)): ?>
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th><?= t('name') ?></th>
                                            <th>Node</th>
                                            <th><?= t('status') ?></th>
                                            <th>CPU</th>
                                            <th>RAM</th>
                                            <th>Uptime</th>
                                            <th><?= t('actions') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($vms as $vm): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($vm['vmid'] ?? $vm['id'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($vm['name'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($vm['node'] ?? '-') ?></td>
                                                <td>
                                                    <?php
                                                        $status = $vm['status'] ?? $vm['state'] ?? 'unbekannt';
                                                        echo htmlspecialchars($status);
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                        $cores = $vm['cores'] ?? 1;
                                                        $cpu = $vm['cpu_usage'] ?? 0;
                                                        $cpuPercent = round($cpu * 100, 2);
                                                        echo htmlspecialchars($cores) . ' - ' . $cpuPercent . ' %';
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                        $memTotal = $vm['memory'] ?? 0;
                                                        $memUsed = $vm['memory_usage'] ?? 0;
                                                        $formatMem = function($bytes) {
                                                            if ($bytes >= 1073741824) {
                                                                return round($bytes / 1073741824, 2) . ' GB';
                                                            } elseif ($bytes >= 1048576) {
                                                                return round($bytes / 1048576, 2) . ' MB';
                                                            } elseif ($bytes > 0) {
                                                                return $bytes . ' B';
                                                            } else {
                                                                return '-';
                                                            }
                                                        };
                                                        echo $formatMem($memUsed) . ' / ' . $formatMem($memTotal);
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                        $uptime = $vm['uptime'] ?? 0;
                                                        $days = floor($uptime / 86400);
                                                        $hours = floor(($uptime % 86400) / 3600);
                                                        $minutes = floor(($uptime % 3600) / 60);
                                                        $uptimeStr = [];
                                                        if ($days > 0) $uptimeStr[] = $days . 'd';
                                                        if ($hours > 0) $uptimeStr[] = $hours . 'h';
                                                        if ($minutes > 0) $uptimeStr[] = $minutes . 'm';
                                                        echo $uptimeStr ? implode(' ', $uptimeStr) : '-';
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                        $id = $vm['vmid'] ?? $vm['id'] ?? '';
                                                        $node = $vm['node'] ?? '';
                                                        if ($status === 'running') {
                                                            echo '<a href="?option=resources&action=stop_vm&id=' . urlencode($id) . '&node=' . urlencode($node) . '" class="btn btn-warning btn-sm">Stop</a> ';
                                                        } else {
                                                            echo '<a href="?option=resources&action=start_vm&id=' . urlencode($id) . '&node=' . urlencode($node) . '" class="btn btn-success btn-sm">Start</a> ';
                                                        }
                                                        echo '<a href="?option=resources&action=delete_vm&id=' . urlencode($id) . '&node=' . urlencode($node) . '" class="btn btn-danger btn-sm">Löschen</a>';
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="alert alert-info"><?= t('no_data') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- Website Management -->
                    <div class="tab-pane fade" id="resource-websites" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4><?= t('websites') ?></h4>
                            <a class="btn btn-primary btn-sm" href="?option=resources">
                                <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                            </a>
                        </div>
                        <div id="website-content" class="table-responsive">
                            <?php if (!empty($grouped) || !empty($aliasDomains)): ?>
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th><?= t('name') ?></th>
                                            <th><?= t('status') ?></th>
                                            <th><?= t('actions') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($grouped as $key => $data): ?>
                                            <?php $site = $data['main']; ?>
                                            <tr>
                                                <td><?= htmlspecialchars($site['name'] ?? $site['domain'] ?? '-') ?></td>
                                                <td>
                                                    <?php
                                                        $active = $site['active'] ?? $site['status'] ?? '';
                                                        echo ($active === 'y' || $active === 'active') ? 'Aktiv' : 'Inaktiv';
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                        $id = $site['id'] ?? $site['domain'] ?? '';
                                                        $isActive = ($active === 'y' || $active === 'active');
                                                        echo '<a href="?option=resources&action=delete_website&id=' . urlencode($id) . '" class="btn btn-danger btn-sm">Löschen</a> ';
                                                        if ($isActive) {
                                                            echo '<a href="?option=resources&action=deactivate_website&id=' . urlencode($id) . '" class="btn btn-warning btn-sm">Deaktivieren</a>';
                                                        } else {
                                                            echo '<a href="?option=resources&action=activate_website&id=' . urlencode($id) . '" class="btn btn-success btn-sm">Aktivieren</a>';
                                                        }
                                                    ?>
                                                </td>
                                            </tr>
                                            <?php if (!empty($data['subdomains'])): ?>
                                                <?php foreach ($data['subdomains'] as $sub): ?>
                                                    <tr>
                                                        <td style="padding-left: 2em;">&rarr; <?= htmlspecialchars($sub['name'] ?? $sub['domain'] ?? '-') ?></td>
                                                        <td>
                                                            <?php
                                                                $active = $sub['active'] ?? $sub['status'] ?? '';
                                                                echo ($active === 'y' || $active === 'active') ? 'Subdomain aktiv' : 'Subdomain inaktiv';
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <?php
                                                                $id = $sub['id'] ?? $sub['domain'] ?? '';
                                                                $isActive = ($active === 'y' || $active === 'active');
                                                                echo '<a href="?option=resources&action=delete_website&id=' . urlencode($id) . '" class="btn btn-danger btn-sm">Löschen</a> ';
                                                                if ($isActive) {
                                                                    echo '<a href="?option=resources&action=deactivate_subdomain&id=' . urlencode($id) . '" class="btn btn-warning btn-sm">Deaktivieren</a>';
                                                                } else {
                                                                    echo '<a href="?option=resources&action=activate_subdomain&id=' . urlencode($id) . '" class="btn btn-success btn-sm">Aktivieren</a>';
                                                                }
                                                            ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                        <?php foreach ($aliasDomains as $alias): ?>
                                            <tr style="background-color: #e6ffe6;">
                                                <td><?= htmlspecialchars($alias['name'] ?? $alias['domain'] ?? '-') ?> <span class="badge bg-success">AliasDomain</span></td>
                                                <td>
                                                    <?php
                                                        $active = $alias['active'] ?? $alias['status'] ?? '';
                                                        echo ($active === 'y' || $active === 'active') ? 'Aktiv' : 'Inaktiv';
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                        $id = $alias['id'] ?? $alias['domain'] ?? '';
                                                        $isActive = ($active === 'y' || $active === 'active');
                                                        echo '<a href="?option=resources&action=delete_website&id=' . urlencode($id) . '" class="btn btn-danger btn-sm">Löschen</a> ';
                                                        if ($isActive) {
                                                            echo '<a href="?option=resources&action=deactivate_website&id=' . urlencode($id) . '" class="btn btn-warning btn-sm">Deaktivieren</a>';
                                                        } else {
                                                            echo '<a href="?option=resources&action=activate_website&id=' . urlencode($id) . '" class="btn btn-success btn-sm">Aktivieren</a>';
                                                        }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="alert alert-info"><?= t('no_data') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- Database Management -->
                    <div class="tab-pane fade" id="resource-databases" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4><?= t('databases') ?></h4>
                            <a class="btn btn-primary btn-sm" href="?option=resources">
                                <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                            </a>
                        </div>
                        <div id="database-content" class="table-responsive">
                            <?php if (!empty($databases)): ?>
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th><?= t('name') ?></th>
                                            <th>Typ</th>
                                            <th><?= t('status') ?></th>
                                            <th>Remote Access</th>
                                            <th><?= t('actions') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($databases as $db): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($db['database_id'] ?? $db['id'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($db['database_name'] ?? $db['name'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($db['database_type'] ?? '-') ?></td>
                                                <td>
                                                    <?php
                                                        $active = $db['active'] ?? $db['status'] ?? '';
                                                        echo ($active === 'y' || $active === 'active') ? 'Aktiv' : 'Inaktiv';
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                        $remote = $db['remote_access'] ?? '';
                                                        echo ($remote === 'y') ? 'Ja' : 'Nein';
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                        $id = $db['database_id'] ?? $db['id'] ?? $db['name'] ?? '';
                                                        $isActive = ($active === 'y' || $active === 'active');
                                                        echo '<a href="?option=resources&action=delete_database&id=' . urlencode($id) . '" class="btn btn-danger btn-sm">Löschen</a> ';
                                                        if ($isActive) {
                                                            echo '<a href="?option=resources&action=deactivate_database&id=' . urlencode($id) . '" class="btn btn-warning btn-sm">Deaktivieren</a>';
                                                        } else {
                                                            echo '<a href="?option=resources&action=activate_database&id=' . urlencode($id) . '" class="btn btn-success btn-sm">Aktivieren</a>';
                                                        }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="alert alert-info"><?= t('no_data') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- Email Management -->
                    <div class="tab-pane fade" id="resource-emails" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4><?= t('email_accounts') ?></h4>
                            <a class="btn btn-primary btn-sm" href="?option=resources">
                                <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                            </a>
                        </div>
                        <div id="email-content" class="table-responsive">
                            <?php if (!empty($emails)): ?>
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>E-Mail</th>
                                            <th><?= t('name') ?></th>
                                            <th>Quota</th>
                                            <th><?= t('status') ?></th>
                                            <th>Autoresponder</th>
                                            <th><?= t('actions') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($emails as $mail): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($mail['mailuser_id'] ?? $mail['id'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($mail['email'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($mail['name'] ?? '-') ?></td>
                                                <td>
                                                    <?php
                                                        $quota = $mail['quota'] ?? 0;
                                                        if ($quota >= 1073741824) {
                                                            echo round($quota / 1073741824, 2) . ' GB';
                                                        } elseif ($quota >= 1048576) {
                                                            echo round($quota / 1048576, 2) . ' MB';
                                                        } elseif ($quota > 0) {
                                                            echo $quota . ' B';
                                                        } else {
                                                            echo '-';
                                                        }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                        $active = $mail['active'] ?? '';
                                                        echo ($active === 'y' || $active === 'active') ? 'Aktiv' : 'Inaktiv';
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                        $ar = $mail['autoresponder'] ?? '';
                                                        echo ($ar === 'y') ? 'Ja' : 'Nein';
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                        $id = $mail['mailuser_id'] ?? $mail['id'] ?? $mail['email'] ?? '';
                                                        $isActive = ($active === 'y' || $active === 'active');
                                                        echo '<a href="?option=resources&action=delete_email&id=' . urlencode($id) . '" class="btn btn-danger btn-sm">Löschen</a> ';
                                                        if ($isActive) {
                                                            echo '<a href="?option=resources&action=deactivate_email&id=' . urlencode($id) . '" class="btn btn-warning btn-sm">Deaktivieren</a>';
                                                        } else {
                                                            echo '<a href="?option=resources&action=activate_email&id=' . urlencode($id) . '" class="btn btn-success btn-sm">Aktivieren</a>';
                                                        }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="alert alert-info"><?= t('no_data') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- Domain Management -->
                    <div class="tab-pane fade" id="resource-domains" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4><?= t('domains') ?></h4>
                            <a class="btn btn-primary btn-sm" href="?option=resources">
                                <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                            </a>
                        </div>
                        <div id="domain-content" class="table-responsive">
                            <?php if (!empty($domains)): ?>
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Domain</th>
                                            <th>Status</th>
                                            <th>Registrar</th>
                                            <th>NameServer</th>
                                            <th><?= t('actions') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($domains as $domain): ?>
                                            <?php
                                                $status = $domain['state'] ?? $domain['status'] ?? '';
                                                $rowClass = ($status === 'expired') ? 'table-warning' : '';
                                            ?>
                                            <tr class="<?= $rowClass ?>">
                                                <td><?= htmlspecialchars($domain['domain'] ?? $domain['name'] ?? '-') ?></td>
                                                <td>
                                                    <?php
                                                        echo ($status === 'ok' || $status === 'active') ? 'Aktiv' : htmlspecialchars($status);
                                                    ?>
                                                </td>
                                                <td><?= htmlspecialchars($domain['registrar'] ?? '-') ?></td>
                                                <td>
                                                    <?php
                                                        $nsArr = $domain['nameServers'] ?? [];
                                                        if (is_array($nsArr)) {
                                                            $nsList = array_map(function($ns) {
                                                                return htmlspecialchars($ns['nameServer'] ?? $ns);
                                                            }, $nsArr);
                                                            echo implode(', ', $nsList);
                                                        } else {
                                                            echo '-';
                                                        }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                        $id = $domain['domain'] ?? $domain['name'] ?? '';
                                                        echo '<a href="?option=resources&action=delete_domain&id=' . urlencode($id) . '" class="btn btn-danger btn-sm">Löschen</a>';
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="alert alert-info"><?= t('no_data') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div> 