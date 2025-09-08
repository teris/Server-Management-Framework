<?php
/**
 * Server Management Framework
 * 
 * @author Teris
 * @version 3.1.3
 */

$vms = $adminCore->getResources('vms');
$websites = $adminCore->getResources('websites');
$databases = $adminCore->getResources('databases');
$emails = $adminCore->getResources('emails');
$domains = $adminCore->getResources('domains');
$ip = $adminCore->getResources('ip');
$ogpServers = $adminCore->getResources('ogp_servers');
$ogpGameServers = $adminCore->getResources('ogp_gameservers');
$ogpGames = $adminCore->getResources('ogp_games');

// Sicherheitsmaßnahme: Stelle sicher, dass Arrays sind
if (!is_array($ogpServers)) $ogpServers = [];
if (!is_array($ogpGameServers)) $ogpGameServers = [];
if (!is_array($ogpGames)) $ogpGames = [];



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
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="ip-tab" data-bs-toggle="pill" data-bs-target="#resource-ip" type="button" role="tab">
                            <i class="bi bi-ip-stack"></i> <?= t('ip') ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="ogp-servers-tab" data-bs-toggle="pill" data-bs-target="#resource-ogp-servers" type="button" role="tab">
                            <i class="bi bi-server"></i> <?= t('ogp_servers') ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="ogp-gameservers-tab" data-bs-toggle="pill" data-bs-target="#resource-ogp-gameservers" type="button" role="tab">
                            <i class="bi bi-controller"></i> <?= t('ogp_gameservers') ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="ogp-games-tab" data-bs-toggle="pill" data-bs-target="#resource-ogp-games" type="button" role="tab">
                            <i class="bi bi-gamepad"></i> <?= t('ogp_games') ?>
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
                                                            echo '<a href="?option=resources&action=stop_vm&id=' . urlencode($id) . '&node=' . urlencode($node) . '" class="btn btn-warning btn-sm">'.t('stop').'</a> ';
                                                        } else {
                                                            echo '<a href="?option=resources&action=start_vm&id=' . urlencode($id) . '&node=' . urlencode($node) . '" class="btn btn-success btn-sm">'.t('start').'</a> ';
                                                        }
                                                        echo '<a href="?option=resources&action=delete_vm&id=' . urlencode($id) . '&node=' . urlencode($node) . '" class="btn btn-danger btn-sm">'.t('delete').'</a>';
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
                                                        echo '<a href="?option=resources&action=delete_website&id=' . urlencode($id) . '" class="btn btn-danger btn-sm">'.t('delete').'</a> ';
                                                        if ($isActive) {
                                                            echo '<a href="?option=resources&action=deactivate_website&id=' . urlencode($id) . '" class="btn btn-warning btn-sm">'.t('deactivate').'</a>';
                                                        } else {
                                                            echo '<a href="?option=resources&action=activate_website&id=' . urlencode($id) . '" class="btn btn-success btn-sm">'.t('activate').'</a>';
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
                                                                echo '<a href="?option=resources&action=delete_website&id=' . urlencode($id) . '" class="btn btn-danger btn-sm">'.t('delete').'</a> ';
                                                                if ($isActive) {
                                                                    echo '<a href="?option=resources&action=deactivate_subdomain&id=' . urlencode($id) . '" class="btn btn-warning btn-sm">'.t('deactivate').'</a>';
                                                                } else {
                                                                    echo '<a href="?option=resources&action=activate_subdomain&id=' . urlencode($id) . '" class="btn btn-success btn-sm">'.t('activate').'</a>';
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
                                                        echo '<a href="?option=resources&action=delete_website&id=' . urlencode($id) . '" class="btn btn-danger btn-sm">'.t('delete').'</a> ';
                                                        if ($isActive) {
                                                            echo '<a href="?option=resources&action=deactivate_website&id=' . urlencode($id) . '" class="btn btn-warning btn-sm">'.t('deactivate').'</a>';
                                                        } else {
                                                            echo '<a href="?option=resources&action=activate_website&id=' . urlencode($id) . '" class="btn btn-success btn-sm">'.t('activate').'</a>';
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
                                                        echo '<a href="?option=resources&action=delete_database&id=' . urlencode($id) . '" class="btn btn-danger btn-sm">'.t('delete').'</a> ';
                                                        if ($isActive) {
                                                            echo '<a href="?option=resources&action=deactivate_database&id=' . urlencode($id) . '" class="btn btn-warning btn-sm">'.t('deactivate').'</a>';
                                                        } else {
                                                            echo '<a href="?option=resources&action=activate_database&id=' . urlencode($id) . '" class="btn btn-success btn-sm">'.t('activate').'</a>';
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
                                                        echo '<a href="?option=resources&action=delete_email&id=' . urlencode($id) . '" class="btn btn-danger btn-sm">'.t('delete').'</a> ';
                                                        if ($isActive) {
                                                            echo '<a href="?option=resources&action=deactivate_email&id=' . urlencode($id) . '" class="btn btn-warning btn-sm">'.t('deactivate').'</a>';
                                                        } else {
                                                            echo '<a href="?option=resources&action=activate_email&id=' . urlencode($id) . '" class="btn btn-success btn-sm">'.t('activate').'</a>';
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
                                                        echo '<a href="?option=resources&action=delete_domain&id=' . urlencode($id) . '" class="btn btn-danger btn-sm">'.t('delete').'</a>';
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
                    <!-- IP Management -->
                    <div class="tab-pane fade" id="resource-ip" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4><?= t('ip') ?></h4>
                            <a class="btn btn-primary btn-sm" href="?option=resources">
                                <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                            </a>
                        </div>
                        <div id="ip-progressbar" class="progress mb-3" style="height: 24px; display: none;">
                          <div id="ip-progressbar-inner" class="progress-bar progress-bar-striped progress-bar-animated" 
                               role="progressbar" style="width: 0%">0%</div>
                        </div>
                        <div id="ip-content" class="table-responsive">
                            <!-- Tabelle wird per JS eingefügt -->
                        </div>
                    </div>
                    <!-- OGP Server Management -->
                    <div class="tab-pane fade" id="resource-ogp-servers" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4><?= t('ogp_servers') ?></h4>
                            <a class="btn btn-primary btn-sm" href="?option=resources">
                                <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                            </a>
                        </div>
                        <div id="ogp-servers-content" class="table-responsive">

                            <?php if (!empty($ogpServers)): ?>
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th><?= t('server_name') ?></th>
                                            <th><?= t('display_public_ip') ?></th>
                                            <th><?= t('use_nat') ?></th>
                                            <th><?= t('timeout') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ogpServers as $server): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($server['remote_server_name'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($server['display_public_ip'] ?? '-') ?></td>
                                                <td><?= ($server['use_nat'] ?? 0) ? 'Ja' : 'Nein' ?></td>
                                                <td><?= htmlspecialchars($server['timeout'] ?? '-') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="alert alert-info"><?= t('no_data') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- OGP GameServer Management -->
                    <div class="tab-pane fade" id="resource-ogp-gameservers" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4><?= t('ogp_gameservers') ?></h4>
                            <a class="btn btn-primary btn-sm" href="?option=resources">
                                <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                            </a>
                        </div>
                        <div id="ogp-gameservers-content" class="table-responsive">

                            <?php if (!empty($ogpGameServers)): ?>
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th><?= t('game_name') ?></th>
                                            <th><?= t('remote_server_name') ?></th>
                                            <th><?= t('agent_ip') ?></th>
                                            <th><?= t('server_expiration_date') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ogpGameServers as $gameServer): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($gameServer['game_name'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($gameServer['remote_server_name'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($gameServer['agent_ip'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($gameServer['server_expiration_date'] ?? '-') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="alert alert-info"><?= t('no_data') ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- OGP Games Management -->
                    <div class="tab-pane fade" id="resource-ogp-games" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4><?= t('ogp_games') ?></h4>
                            <a class="btn btn-primary btn-sm" href="?option=resources">
                                <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                            </a>
                        </div>
                        <div id="ogp-games-content" class="table-responsive">

                            <?php if (!empty($ogpGames)): ?>
                                <?php
                                // Gruppiere Spiele nach Namen
                                $groupedGames = [];
                                foreach ($ogpGames as $game) {
                                    $gameName = $game['game_name'] ?? '';
                                    if (!isset($groupedGames[$gameName])) {
                                        $groupedGames[$gameName] = [
                                            'game_name' => $gameName,
                                            'variants' => [],
                                            'all_mods' => []
                                        ];
                                    }
                                    
                                    // Sammle alle Varianten und Mods für dieses Spiel
                                    if (!empty($game['variants'])) {
                                        foreach ($game['variants'] as $variant) {
                                            $groupedGames[$gameName]['variants'][] = $variant;
                                            
                                            // Sammle Mods
                                            if (!empty($variant['mods'])) {
                                                foreach ($variant['mods'] as $mod) {
                                                    $modKey = $mod['mod_key'] ?? '';
                                                    $modName = $mod['mod_name'] ?? '';
                                                    if ($modKey && $modName) {
                                                        $groupedGames[$gameName]['all_mods'][$modKey] = $modName;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                ?>
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th><?= t('game_name') ?></th>
                                            <th><?= t('variants') ?></th>
                                            <th><?= t('mods') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($groupedGames as $gameName => $gameData): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($gameName) ?></td>
                                                <td>
                                                    <?php 
                                                                                                        $variantTypes = [];
                                                    foreach ($gameData['variants'] as $variant) {
                                                        // Extrahiere Variantentyp aus system und architecture
                                                        $system = $variant['system'] ?? '';
                                                        $architecture = $variant['architecture'] ?? '';
                                                        
                                                        if ($system && $architecture) {
                                                            $systemName = ($system === 'linux') ? 'Linux' : 'Windows';
                                                            $archName = $architecture . '-bit';
                                                            $variantKey = $systemName . ' ' . $archName;
                                                            $variantTypes[$variantKey] = true;
                                                        }
                                                    }
                                                    
                                                    // Alternative: Direkte Verarbeitung der ursprünglichen Spiele
                                                    if (empty($variantTypes)) {
                                                        foreach ($ogpGames as $originalGame) {
                                                            if (($originalGame['game_name'] ?? '') === $gameName) {
                                                                foreach ($originalGame['variants'] ?? [] as $variant) {
                                                                    // Extrahiere Variantentyp aus system und architecture (Alternative)
                                                                    $system = $variant['system'] ?? '';
                                                                    $architecture = $variant['architecture'] ?? '';
                                                                    
                                                                    if ($system && $architecture) {
                                                                        $systemName = ($system === 'linux') ? 'Linux' : 'Windows';
                                                                        $archName = $architecture . '-bit';
                                                                        $variantKey = $systemName . ' ' . $archName;
                                                                        $variantTypes[$variantKey] = true;
                                                                    }
                                                                }
                                                                break;
                                                            }
                                                        }
                                                    }
                                                    
                                                    if (!empty($variantTypes)): ?>
                                                        <ul class="list-unstyled mb-0">
                                                            <?php foreach (array_keys($variantTypes) as $variantType): ?>
                                                                <li>
                                                                    <?php
                                                                    // Bestimme Farbe und Symbol basierend auf der Variante
                                                                    $badgeClass = '';
                                                                    $icon = '';
                                                                    
                                                                    if (strpos($variantType, 'Linux') !== false) {
                                                                        $badgeClass = 'bg-warning text-dark';
                                                                        $icon = 'bi-ubuntu';
                                                                    } elseif (strpos($variantType, 'Windows') !== false) {
                                                                        $badgeClass = 'bg-success';
                                                                        $icon = 'bi-windows';
                                                                    } else {
                                                                        $badgeClass = 'bg-primary';
                                                                        $icon = 'bi-cpu';
                                                                    }
                                                                    ?>
                                                                    <span class="badge <?= $badgeClass ?>">
                                                                        <i class="bi <?= $icon ?> me-1"></i>
                                                                        <?= htmlspecialchars($variantType) ?>
                                                                    </span>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php else: ?>
                                                       -
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($gameData['all_mods'])): ?>
                                                        <ul class="list-unstyled mb-0">
                                                            <?php foreach ($gameData['all_mods'] as $modKey => $modName): ?>
                                                                <li>
                                                                    <small class="text-muted"><?= htmlspecialchars($modKey) ?>:</small> <?= htmlspecialchars($modName) ?>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
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
<script>
function loadIpMacReverseTable() {
    var bar = document.getElementById('ip-progressbar');
    var inner = document.getElementById('ip-progressbar-inner');
    var content = document.getElementById('ip-content');
    if (!bar || !inner || !content) return;
    bar.style.display = 'block';
    inner.style.width = '0%';
    inner.innerText = '0%';
    content.innerHTML = '';
    // Simulierter Fortschritt
    var percent = 0;
    var fakeInterval = setInterval(function() {
        percent += Math.floor(Math.random() * 8) + 2;
        if (percent > 90) percent = 90;
        inner.style.width = percent + '%';
        inner.innerText = percent + '%';
    }, 80);
    // AJAX-Request
    fetch('ajax_ip_mac_reverse.php')
        .then(response => response.json())
        .then(data => {
            clearInterval(fakeInterval);
            inner.style.width = '100%';
            inner.innerText = '100%';
            setTimeout(function() { bar.style.display = 'none'; }, 400);
            if (Array.isArray(data) && data.length > 0) {
                var html = '<table class="table table-bordered table-striped table-sm align-middle">';
                html += '<thead class="table-light"><tr>' +
                        '<th>'+t('ip')+'</th><th>'+t('ip_reverse')+'</th><th>'+t('mac_address')+'</th><th>'+t('type')+'</th>' +
                        '</tr></thead><tbody>';
                data.forEach(function(row) {
                    html += '<tr>' +
                        '<td>' + escapeHtml(row.ipReverse) + '</td>' +
                        '<td>' + escapeHtml(row.reverse) + '</td>' +
                        '<td>' + (row.macAddress === 'Nicht zugewiesen' ? '<span class="text-danger fw-bold">'+escapeHtml('not_assigned')+'</span>' : escapeHtml(row.macAddress)) + '</td>' +
                        '<td>' + (row.type === 'Nicht zugewiesen' ? '<span class="text-danger fw-bold">'+escapeHtml('not_assigned')+'</span>' : escapeHtml(row.type)) + '</td>' +
                        '</tr>';
                });
                html += '</tbody></table>';
                content.innerHTML = html;
            } else {
                content.innerHTML = '<div class="alert alert-info">'+escapeHtml('no_matching_combinations')+'</div>';
            }
        })
        .catch(function() {
            clearInterval(fakeInterval);
            bar.style.display = 'none';
            content.innerHTML = '<div class="alert alert-danger">'+escapeHtml('error_loading')+'</div>';
        });
}
function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>"']/g, function(m) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[m];
    });
}
document.addEventListener('DOMContentLoaded', function() {
    var ipTab = document.getElementById('ip-tab');
    if (ipTab) {
        ipTab.addEventListener('shown.bs.tab', function () {
            loadIpMacReverseTable();
        });
    } else {
        // Falls kein Tab, direkt laden
        loadIpMacReverseTable();
    }
});
</script> 