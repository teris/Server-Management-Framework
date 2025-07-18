<?php
if (__FILE__ === $_SERVER['SCRIPT_FILENAME']) {
    header('Location: ../index.php');
    exit;
}
require_once dirname(__DIR__) . '/sys.conf.php';
require_once dirname(__DIR__) . '/config/config.inc.php';
if (!isset($pdo)) {
    $pdo = new PDO('mysql:host=' . Config::DB_HOST . ';dbname=' . Config::DB_NAME, Config::DB_USER, Config::DB_PASS);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_module_permissions' && isset($_POST['perm'])) {
    try {
        // Modul-Key zu Modul-ID Mapping laden (name und module_name, alles klein)
        $moduleKeyToId = [];
        $stmt = $pdo->query("SELECT id, name, module_name FROM modules");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $mod) {
            if (isset($mod['name'])) {
                $moduleKeyToId[strtolower($mod['name'])] = $mod['id'];
            }
            if (isset($mod['module_name'])) {
                $moduleKeyToId[strtolower($mod['module_name'])] = $mod['id'];
            }
        }
        // Alle bisherigen Rechte löschen
        $pdo->exec("DELETE FROM group_module_permissions");
        // Neue Rechte speichern
        foreach ($_POST['perm'] as $group_id => $mods) {
            foreach ($mods as $module_key => $val) {
                $module_key_lc = strtolower($module_key);
                if (!isset($moduleKeyToId[$module_key_lc])) {
                    echo '<div class="alert alert-warning">' . t('no_mapping_for_module_key') . ': ' . htmlspecialchars($module_key) . '</div>';
                    continue;
                }
                $module_id = $moduleKeyToId[$module_key_lc];
                $can_access = $val ? 1 : 0;
                $stmt = $pdo->prepare("INSERT INTO group_module_permissions (group_id, module_id, can_access) VALUES (?, ?, ?)");
                $stmt->execute([$group_id, $module_id, $can_access]);
                echo '<div class="alert alert-info">' . t('insert_permission') . ': ' . htmlspecialchars($group_id) . ', ' . htmlspecialchars($module_id) . ', ' . htmlspecialchars($can_access) . '</div>';
            }
        }
        echo '<div class="alert alert-success">' . t('module_permissions_saved') . '</div>';
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">' . t('error_saving_module_permissions') . ': ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}
?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header"><h2 class="mb-0">
                <i class="bi bi-gear"></i> <?= t('settings') ?></h2>
            </div>
            
            <div class="card-body">
                    <!-- Bootstrap Nav-Tabs -->
                    <ul class="nav nav-tabs mb-3" id="settingsTabNav" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tab-general-tab" data-bs-toggle="tab" data-bs-target="#tab-general" type="button" role="tab" aria-controls="tab-general" aria-selected="true">
                                <?= t('general'); ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-api-tab" data-bs-toggle="tab" data-bs-target="#tab-api" type="button" role="tab" aria-controls="tab-api" aria-selected="false">
                                <?= t('api_credentials'); ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-modules-tab" data-bs-toggle="tab" data-bs-target="#tab-modules" type="button" role="tab" aria-controls="tab-modules" aria-selected="false">
                                <?= t('modules'); ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tab-users-tab" data-bs-toggle="tab" data-bs-target="#tab-users" type="button" role="tab" aria-controls="tab-users" aria-selected="false">
                                <?= t('users'); ?> & <?= t('groups'); ?>
                            </button>
                        </li>
                    </ul>
                    <!-- Tab-Inhalte als Bootstrap Tab-Panes -->
                    <div class="tab-content" id="settingsTabContent">
                        <!-- Allgemein -->
                        <div class="tab-pane fade show active" id="tab-general" role="tabpanel" aria-labelledby="tab-general-tab">
                            <form id="general-settings-form" enctype="multipart/form-data" action="?option=submit&mode=save_settings">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="input-group input-group-sm mb-3">
                                            <span class="input-group-text" id="inputGroup-sizing-sm"><?= t('site_title'); ?>:</span>
                                            <input type="text" class="form-control" aria-label="Sizing site_title input" aria-describedby="inputGroup-sizing-sm" name="site_title" id="site_title">
                                        </div>

                                        <div class="input-group input-group-sm mb-3">
                                            <span class="input-group-text" id="inputGroup-sizing-sm"><?= t('logo'); ?>:</span>
                                            <input type="file" name="logo" id="logo formFile" class="form-control" type="file">
                                        </div>

                                        <div class="input-group input-group-sm mb-3">
                                            <span class="input-group-text" id="inputGroup-sizing-sm"><?= t('favicon'); ?>:</span>
                                            <input type="file" name="favicon" id="favicon formFile" class="form-control" type="file">
                                        </div>

                                        <div class="input-group input-group-sm mb-3">
                                            <label class="input-group-text" for="mode"><?= t('mode'); ?>:
                                                <select name="mode" id="mode" class="form-select">
                                                    <option value="live"><?= t('live_mode'); ?></option>
                                                    <option value="database"><?= t('database_mode'); ?></option>
                                                </select>
                                            </label>
                                        </div>
                                        <div id="db-mode-hint" class="alert alert-info" role="alert">
                                            <?= t('db_mode_hint'); ?>
                                        </div>
                                    </div>
                                    <!-- Erweiterung: Session-Timeout und Auto-Refresh -->
                                    <div class="col-md-2">
                                        <div class="settings-advanced input-group input-group-sm mb-3">
                                            <label class="input-group-text" for="session-timeout"><?= t('session_timeout'); ?>:</label>
                                            <input type="number" name="session_timeout" id="session-timeout" value="30" min="5" max="480">
                                        </div>  
                                        <div class="settings-advanced input-group input-group-sm mb-3">
                                            <label class="input-group-text" for="refresh-interval"><?= t('auto_refresh_interval'); ?>:</label>
                                            <input type="number" name="refresh_interval" id="refresh-interval" value="30" min="10" max="300">
                                        </div>
                                    </div>
                                    <!-- Erweiterung: Systemstatus -->
                                    <div class="col-md-6">
                                        <div class="settings-status ">
                                            <strong><?= t('system_status'); ?>:</strong><br>
                                            <span><strong><?= t('cache_status'); ?>:</strong> <span class="badge bg-success ms-2"><?= t('active'); ?></span></span><br>
                                            <span><strong><?= t('api_connections'); ?>:</strong> <span class="badge bg-success ms-2"><?= t('all_ok'); ?></span></span><br>
                                            <span><strong><?= t('last_update'); ?>:</strong> <span class="text-muted ms-2" id="last-update">-</span></span>
                                        </div>
                                        <button type="submit" class="btn btn-primary"><?= t('save_button'); ?></button>
                                        <span id="general-settings-status"></span>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <!-- Allgemein Ende -->
                        <!-- API-Zugangsdaten -->
                        <div class="tab-pane fade" id="tab-api" role="tabpanel" aria-labelledby="tab-api-tab">
                            <form id="api-settings-form" action="?option=submit">
                                <div id="api-credentials-list" class="row">
                                    <?php
                                    try {
                                        $pdo = new PDO('mysql:host=' . Config::DB_HOST . ';dbname=' . Config::DB_NAME, Config::DB_USER, Config::DB_PASS);
                                        $stmt = $pdo->query("SELECT * FROM api_credentials");
                                        $apis = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($apis as $api) {
                                            $title = '';
                                            if ($api['service_name'] === 'proxmox') $title = 'Proxmox';
                                            if ($api['service_name'] === 'ispconfig') $title = 'ISPConfig';
                                            if ($api['service_name'] === 'ovh') $title = 'OVH';
                                            ?>
                                            <div class="mb-4 border rounded col-md-4">
                                                <h5 class="shadow p-3 mb-5 bg-body rounded"><?= htmlspecialchars($title) ?></h5>
                                                <div class="input-group mb-3">
                                                    <label class="input-group-text" for="api_url_<?= $api['id'] ?>">URL/Endpoint: </label>
                                                    <input type="text" name="api_url_<?= $api['id'] ?>" value="<?= htmlspecialchars($api['endpoint'] ?? '') ?>" class="form-control">
                                                </div>
                                                <div class="input-group mb-3">
                                                    <label class="input-group-text" for="api_user_<?= $api['id'] ?>">Benutzer: </label>
                                                    <input type="text" name="api_user_<?= $api['id'] ?>" value="<?= htmlspecialchars($api['username'] ?? '') ?>" class="form-control">
                                                </div>
                                                <div class="input-group mb-3">
                                                    <label class="input-group-text" for="api_password_<?= $api['id'] ?>">Passwort: </label>
                                                    <input type="text" name="api_password_<?= $api['id'] ?>" value="" placeholder="********" class="form-control">
                                                </div>
                                            </div>
                                            <?php
                                        }
                                    } catch (Exception $e) {
                                        echo '<div>' . t('error_loading_api_credentials') . ': ' . htmlspecialchars($e->getMessage()) . '</div>';
                                    }
                                    ?>
                                </div>
                                <button type="submit" class="btn btn-primary"><?= t('save_button'); ?></button>
                                <span id="api-settings-status"></span>
                            </form>
                        </div>
                        <!-- API-Zugangsdaten Ende -->
                        <!-- Module -->
                        <?php
                        // Module und Gruppen laden
                        $modules = [];
                        $groups = [];
                        $permissions = [];
                        try {
                            // Aktive Module aus sys.conf.php
                            $modules = getEnabledPlugins();
                            $stmt = $pdo->query("SELECT * FROM groups");
                            $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            $stmt = $pdo->query("SELECT * FROM group_module_permissions");
                            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $perm) {
                                $permissions[$perm['group_id']][$perm['module_id']] = $perm['can_access'];
                            }
                        } catch (Exception $e) {}
                        ?>
                        <div class="tab-pane fade" id="tab-modules" role="tabpanel" aria-labelledby="tab-modules-tab">
                            <form id="modules-settings-form" action="?option=submit" method="post">
                                <input type="hidden" name="action" value="save_module_permissions">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Modul</th>
                                            <?php foreach ($groups as $group): ?>
                                                <th><?= htmlspecialchars($group['name']) ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($modules as $key => $module): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($module['name']) ?></td>
                                                <?php foreach ($groups as $group): ?>
                                                    <td class="text-center">
                                                        <input type="checkbox" name="perm[<?= $group['id'] ?>][<?= $key ?>]" value="1" <?= (isset($permissions[$group['id']][$key]) && $permissions[$group['id']][$key]) ? 'checked' : '' ?> />
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <button type="submit" class="btn btn-primary"><?= t('save_button'); ?></button>
                                <span id="modules-settings-status"></span>
                            </form>
                        </div>
                        <!-- Module Ende -->
                        <!-- Benutzer und Gruppen -->
                        <div class="tab-pane fade" id="tab-users" role="tabpanel" aria-labelledby="tab-users-tab">
                            <div id="user-management">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5><?= t('users'); ?></h5>
                                        <!-- Benutzer hinzufügen Formular -->
                                        <div id="user-add-notice"></div>
                                        <form id="user-add-form" class="mb-2">
                                            <input type="text" name="username" placeholder="Benutzername" required class="form-control mb-1">
                                            <input type="text" name="full_name" placeholder="Vollständiger Name" class="form-control mb-1">
                                            <input type="email" name="email" placeholder="E-Mail" required class="form-control mb-1">
                                            <input type="password" name="password" placeholder="Passwort" class="form-control mb-1">
                                            <select name="group_id" class="form-control mb-1">
                                                <option value="">Keine Gruppe</option>
                                                <?php
                                                // Gruppen für das Dropdown laden
                                                $groups_dropdown = [];
                                                try {
                                                    $stmt_groups = $pdo->query("SELECT id, name FROM groups");
                                                    $groups_dropdown = $stmt_groups->fetchAll(PDO::FETCH_ASSOC);
                                                } catch (Exception $e) {
                                                    $groups_dropdown = [];
                                                }
                                                foreach ($groups_dropdown as $g): ?>
                                                    <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <label><input type="checkbox" name="active" value="y" checked> Aktiv</label>
                                            <button type="submit" class="btn btn-success btn-sm ms-2"><?= t('add_user'); ?></button>
                                        </form>
                                        <div id="users-list">
                                            <?php
                                            // Benutzer löschen
                                            if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user' && isset($_POST['user_id'])) {
                                                try {
                                                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                                                    $stmt->execute([$_POST['user_id']]);
                                                    echo '<div class="alert alert-success">' . t('user_deleted') . '</div>';
                                                } catch (Exception $e) {
                                                    echo '<div class="alert alert-danger">' . t('error_deleting') . ': ' . htmlspecialchars($e->getMessage()) . '</div>';
                                                }
                                            }
                                            // Benutzer bearbeiten
                                            if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_user' && isset($_POST['user_id'])) {
                                                try {
                                                    $active = isset($_POST['active']) ? 'y' : 'n';
                                                    $group_id = !empty($_POST['group_id']) ? $_POST['group_id'] : null;
                                                    $role = '';
                                                    if ($group_id) {
                                                        $stmt_group = $pdo->prepare("SELECT name FROM groups WHERE id = ?");
                                                        $stmt_group->execute([$group_id]);
                                                        $role = $stmt_group->fetchColumn() ?: '';
                                                    }
                                                    $pwset = '';
                                                    $params = [
                                                        $_POST['username'],
                                                        $_POST['full_name'],
                                                        $_POST['email'],
                                                        $role,
                                                        $active,
                                                        $group_id,
                                                        $_POST['user_id']
                                                    ];
                                                    if (!empty($_POST['password'])) {
                                                        $pwset = ', password_hash=?';
                                                        $params = [
                                                            $_POST['username'],
                                                            $_POST['full_name'],
                                                            $_POST['email'],
                                                            $role,
                                                            $active,
                                                            $group_id,
                                                            password_hash($_POST['password'], PASSWORD_DEFAULT),
                                                            $_POST['user_id']
                                                        ];
                                                    }
                                                    $sql = "UPDATE users SET username=?, full_name=?, email=?, role=?, active=?, group_id=?" . ($pwset ? $pwset : '') . " WHERE id=?";
                                                    $stmt = $pdo->prepare($sql);
                                                    $stmt->execute($params);
                                                    echo '<div class="alert alert-success">' . t('user_updated') . '</div>';
                                                } catch (Exception $e) {
                                                    echo '<div class="alert alert-danger">' . t('error_editing') . ': ' . htmlspecialchars($e->getMessage()) . '</div>';
                                                }
                                            }
                                            // Benutzer anlegen
                                            if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_user') {
                                                try {
                                                    $group_id = !empty($_POST['group_id']) ? $_POST['group_id'] : null;
                                                    $role = '';
                                                    if ($group_id) {
                                                        $stmt_group = $pdo->prepare("SELECT name FROM groups WHERE id = ?");
                                                        $stmt_group->execute([$group_id]);
                                                        $role = $stmt_group->fetchColumn() ?: '';
                                                    }
                                                    $stmt = $pdo->prepare("INSERT INTO users (username, full_name, email, password_hash, role, active, group_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                                                    $pw = password_hash($_POST['password'], PASSWORD_DEFAULT);
                                                    $active = isset($_POST['active']) ? 'y' : 'n';
                                                    $stmt->execute([
                                                        $_POST['username'],
                                                        $_POST['full_name'],
                                                        $_POST['email'],
                                                        $pw,
                                                        $role,
                                                        $active,
                                                        $group_id
                                                    ]);
                                                    echo '<div class="alert alert-success">' . t('user_added') . '</div>';
                                                } catch (Exception $e) {
                                                    echo '<div class="alert alert-danger">' . t('error_adding') . ': ' . htmlspecialchars($e->getMessage()) . '</div>';
                                                }
                                            }
                                            // Benutzerliste
                                            try {
                                                $stmt = $pdo->query("SELECT * FROM users");
                                                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                if (count($users) === 0) {
                                                    echo '<div>' . t('no_users_found') . '</div>';
                                                } else {
                                                    foreach ($users as $user) {
                                                        if (isset($_POST['action'], $_POST['user_id']) && $_POST['action'] === 'show_edit_user' && $_POST['user_id'] == $user['id']) {
                                                            // Bearbeiten-Formular anzeigen
                                                            echo '<form method="post" action="?option=submit" class="mb-2 border rounded p-2">';
                                                            echo '<input type="hidden" name="action" value="edit_user">';
                                                            echo '<input type="hidden" name="user_id" value="' . $user['id'] . '">';
                                                            echo '<input type="text" name="username" value="' . htmlspecialchars($user['username']) . '" required class="form-control mb-1">';
                                                            echo '<input type="text" name="full_name" value="' . htmlspecialchars($user['full_name'] ?? '') . '" class="form-control mb-1">';
                                                            echo '<input type="email" name="email" value="' . htmlspecialchars($user['email']) . '" required class="form-control mb-1">';
                                                            echo '<input type="password" name="password" placeholder="Neues Passwort (leer lassen)" class="form-control mb-1">';
                                                            echo '<select name="group_id" class="form-control mb-1">';
                                                            echo '<option value="">Keine Gruppe</option>';
                                                            foreach ($groups_dropdown as $g) {
                                                                $sel = (isset($user['group_id']) && $user['group_id'] == $g['id']) ? 'selected' : '';
                                                                echo '<option value="' . $g['id'] . '" ' . $sel . '>' . htmlspecialchars($g['name']) . '</option>';
                                                            }
                                                            echo '</select>';
                                                            $checked = $user['active'] === 'y' ? 'checked' : '';
                                                            echo '<label><input type="checkbox" name="active" value="y" ' . $checked . '> Aktiv</label>';
                                                            echo '<button type="submit" class="btn btn-success btn-sm ms-2">' . t('save_user') . '</button>';
                                                            echo '</form>';
                                                        } else {
                                                            echo '<div class="user-entry mb-2">';
                                                            echo '<strong>' . htmlspecialchars($user['username']) . '</strong> (' . htmlspecialchars($user['full_name'] ?? '') . ') [' . htmlspecialchars($user['role']) . '] - ' . ($user['active'] === 'y' ? t('status_active') : t('status_inactive'));
                                                            echo '<form method="post" action="?option=submit" class="user-delete-form" style="display:inline-block;margin-left:10px;">
                                                                <input type="hidden" name="user_id" value="' . $user['id'] . '">
                                                                <input type="hidden" name="action" value="delete_user">
                                                                <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm(\'' . t('confirm_delete') . '\')">' . t('delete_user') . '</button>';
                                                            echo '</form>';
                                                            echo '</div>';
                                                        }
                                                    }
                                                }
                                            } catch (Exception $e) {
                                                echo '<div>' . t('error_loading_users') . ': ' . htmlspecialchars($e->getMessage()) . '</div>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h5><?= t('groups'); ?></h5>
                                        <!-- Gruppen hinzufügen Formular -->
                                        <div id="group-add-notice"></div>
                                        <form id="group-add-form" class="mb-2">
                                            <input type="text" name="group_name" placeholder="Gruppenname" required class="form-control mb-1">
                                            <input type="text" name="group_description" placeholder="Beschreibung" class="form-control mb-1">
                                            <button type="submit" class="btn btn-success btn-sm ms-2"><?= t('add_group'); ?></button>
                                        </form>
                                        <div id="groups-list">
                                            <?php
                                            // Gruppe löschen
                                            if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_group' && isset($_POST['group_id'])) {
                                                try {
                                                    $stmt = $pdo->prepare("DELETE FROM groups WHERE id = ?");
                                                    $stmt->execute([$_POST['group_id']]);
                                                    echo '<div class="alert alert-success">' . t('group_deleted') . '</div>';
                                                } catch (Exception $e) {
                                                    echo '<div class="alert alert-danger">' . t('error_deleting') . ': ' . htmlspecialchars($e->getMessage()) . '</div>';
                                                }
                                            }
                                            // Gruppe bearbeiten
                                            if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_group' && isset($_POST['group_id'])) {
                                                try {
                                                    $stmt = $pdo->prepare("UPDATE groups SET name=?, description=? WHERE id=?");
                                                    $stmt->execute([
                                                        $_POST['name'],
                                                        $_POST['description'],
                                                        $_POST['group_id']
                                                    ]);
                                                    echo '<div class="alert alert-success">' . t('group_updated') . '</div>';
                                                } catch (Exception $e) {
                                                    echo '<div class="alert alert-danger">' . t('error_editing') . ': ' . htmlspecialchars($e->getMessage()) . '</div>';
                                                }
                                            }
                                            // Gruppe anlegen
                                            if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['mode'] ?? '') === 'add_group') {
                                                try {
                                                    $stmt = $pdo->prepare("INSERT INTO groups (name, description) VALUES (?, ?)");
                                                    $stmt->execute([
                                                        $_POST['name'],
                                                        $_POST['description']
                                                    ]);
                                                    echo '<div class="alert alert-success">' . t('group_added') . '</div>';
                                                } catch (Exception $e) {
                                                    echo '<div class="alert alert-danger">' . t('error_adding') . ': ' . htmlspecialchars($e->getMessage()) . '</div>';
                                                }
                                            }
                                            // Gruppenliste
                                            try {
                                                $stmt = $pdo->query("SELECT * FROM groups");
                                                if ($stmt === false) {
                                                    $errorInfo = $pdo->errorInfo();
                                                    echo '<div class="alert alert-danger">' . t('sql_error') . ': ' . htmlspecialchars($errorInfo[2]) . '</div>';
                                                } else {
                                                    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                    if (count($groups) === 0) {
                                                        echo '<div>' . t('no_groups_found') . '</div>';
                                                    } else {
                                                        foreach ($groups as $group) {
                                                            if (isset($_POST['mode'], $_POST['group_id']) && $_POST['mode'] === 'show_edit_group' && $_POST['group_id'] == $group['id']) {
                                                                // Bearbeiten-Formular anzeigen
                                                                echo '<form class="group-edit-form mb-2 border rounded p-2">';
                                                                echo '<input type="hidden" name="group_id" value="' . $group['id'] . '">';
                                                                echo '<input type="text" name="group_name" value="' . htmlspecialchars($group['name']) . '" required class="form-control mb-1">';
                                                                echo '<input type="text" name="group_description" value="' . htmlspecialchars($group['description'] ?? '') . '" class="form-control mb-1">';
                                                                echo '<button type="submit" class="btn btn-success btn-sm ms-2">' . t('save_group') . '</button>';
                                                                echo '</form>';
                                                            } else {
                                                                echo '<div class="group-entry mb-2">';
                                                                echo '<strong>' . htmlspecialchars($group['name']) . '</strong> (' . htmlspecialchars($group['description'] ?? '') . ')';
                                                                echo '<form class="group-delete-form" style="display:inline-block;margin-left:10px;">'
                                                                    . '<input type="hidden" name="group_id" value="' . $group['id'] . '">' 
                                                                    . '<input type="hidden" name="action" value="delete_group">'
                                                                    . '<button type="submit" class="btn btn-outline-danger btn-sm">' . t('delete_group') . '</button><br>'
                                                                    . '</form>';
                                                                echo '</div>';
                                                            }
                                                        }
                                                    }
                                                }
                                            } catch (Exception $e) {
                                                echo '<div>' . t('error_loading_groups') . ': ' . htmlspecialchars($e->getMessage()) . '</div>';
                                            }
                                            ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <script>
                $(function() {
                    // Nur noch für Modus-Umschaltung (Datenbank/Live) benötigt
                    var modeSelect = document.getElementById('mode');
                    if (modeSelect) {
                        modeSelect.addEventListener('change', function() {
                            document.getElementById('db-mode-hint').style.display = this.value === 'database' ? 'block' : 'none';
                        });
                    }
                    // Benutzer hinzufügen per AJAX
                    $('#user-add-form').on('submit', function(e) {
                        e.preventDefault();
                        var $form = $(this);
                        var formData = $form.serialize() + '&core=admin&action=save_user';
                        $.post('index.php', formData, function(response) {
                            if (response.success) {
                                $('#user-add-notice').html('<div class="alert alert-success">' + t('user_saved') + '</div>');
                                $form[0].reset();
                                $('#users-list').load('inc/settings.php #users-list > *');
                            } else {
                                $('#user-add-notice').html('<div class="alert alert-danger">' + (response.error || t('error_saving')) + '</div>');
                            }
                        }, 'json');
                    });
                    // Gruppen hinzufügen per AJAX
                    $('#group-add-form').on('submit', function(e) {
                        e.preventDefault();
                        var $form = $(this);
                        var formData = $form.serialize() + '&core=admin&action=save_group';
                        $.post('index.php', formData, function(response) {
                            if (response.success) {
                                $('#group-add-notice').html('<div class="alert alert-success">' + t('group_saved') + '</div>');
                                $form[0].reset();
                                $('#groups-list').load('inc/settings.php #groups-list > *');
                            } else {
                                $('#group-add-notice').html('<div class="alert alert-danger">' + (response.error || t('error_saving')) + '</div>');
                            }
                        }, 'json');
                    });
                    // Einstellungen speichern per AJAX
                    $('#general-settings-form').on('submit', function(e) {
                        e.preventDefault();
                        var $form = $(this);
                        var formData = $form.serialize() + '&core=admin&action=save_settings';
                        $.post('index.php', formData, function(response) {
                            if (response.success) {
                                $('#general-settings-status').html('<div class="alert alert-success">' + t('settings_saved') + '</div>');
                            } else {
                                $('#general-settings-status').html('<div class="alert alert-danger">' + (response.error || t('error_saving')) + '</div>');
                            }
                        }, 'json');
                    });
                    // API-Zugangsdaten speichern per AJAX
                    $('#api-settings-form').on('submit', function(e) {
                        e.preventDefault();
                        var $form = $(this);
                        var formData = $form.serialize() + '&core=admin&action=save_api_credentials';
                        $.post('index.php', formData, function(response) {
                            if (response.success) {
                                $('#api-settings-status').html('<div class="alert alert-success">' + t('api_credentials_saved') + '</div>');
                            } else {
                                $('#api-settings-status').html('<div class="alert alert-danger">' + (response.error || t('error_saving')) + '</div>');
                            }
                        }, 'json');
                    });
                    // Module speichern per AJAX
                    $('#modules-settings-form').on('submit', function(e) {
                        e.preventDefault();
                        var $form = $(this);
                        var formData = $form.serialize() + '&core=admin&action=save_modules';
                        $.post('index.php', formData, function(response) {
                            if (response.success) {
                                $('#modules-settings-status').html('<div class="alert alert-success">' + t('module_permissions_saved') + '</div>');
                            } else {
                                $('#modules-settings-status').html('<div class="alert alert-danger">' + (response.error || t('error_saving')) + '</div>');
                            }
                        }, 'json');
                    });
                    // Gruppe bearbeiten per AJAX
                    $(document).on('submit', '.group-edit-form', function(e) {
                        e.preventDefault();
                        var $form = $(this);
                        var formData = $form.serialize() + '&core=admin&action=save_group';
                        $.post('index.php', formData, function(response) {
                            if (response.success) {
                                $('#group-add-notice').html('<div class="alert alert-success">' + t('group_saved') + '</div>');
                                $('#groups-list').load('inc/settings.php #groups-list > *');
                            } else {
                                $('#group-add-notice').html('<div class="alert alert-danger">' + (response.error || t('error_saving')) + '</div>');
                            }
                        }, 'json');
                    });
                    // Gruppe löschen per AJAX
                    $(document).on('submit', '.group-delete-form', function(e) {
                        e.preventDefault();
                        if (!confirm('<?= t('confirm_delete') ?>')) return;
                        var $form = $(this);
                        var formData = $form.serialize() + '&core=admin&action=delete_group';
                        $.post('inc/admin.php', formData, function(response) {
                            if (response.success) {
                                $('#group-add-notice').html('<div class="alert alert-success">' + t('group_deleted') + '</div>');
                                $('#groups-list').load('inc/settings.php #groups-list > *');
                            } else {
                                $('#group-add-notice').html('<div class="alert alert-danger">' + (response.error || t('error_deleting')) + '</div>');
                            }
                        }, 'json');
                    });
                    // Benutzer bearbeiten per AJAX
                    $(document).on('submit', '.user-edit-form', function(e) {
                        e.preventDefault();
                        var $form = $(this);
                        var formData = $form.serialize() + '&core=admin&action=save_user';
                        $.post('index.php', formData, function(response) {
                            if (response.success) {
                                $('#user-add-notice').html('<div class="alert alert-success">' + t('user_saved') + '</div>');
                                $('#users-list').load('inc/settings.php #users-list > *');
                            } else {
                                $('#user-add-notice').html('<div class="alert alert-danger">' + (response.error || t('error_saving')) + '</div>');
                            }
                        }, 'json');
                    });
                    // Benutzer löschen per AJAX
                    $(document).on('submit', '.user-delete-form', function(e) {
                        e.preventDefault();
                        if (!confirm('<?= t('confirm_delete') ?>')) return;
                        var $form = $(this);
                        var formData = $form.serialize() + '&core=admin&action=delete_user';
                        $.post('inc/admin.php', formData, function(response) {
                            if (response.success) {
                                $('#user-add-notice').html('<div class="alert alert-success">' + t('user_deleted') + '</div>');
                                $('#users-list').load('inc/settings.php #users-list > *');
                            } else {
                                $('#user-add-notice').html('<div class="alert alert-danger">' + (response.error || t('error_deleting')) + '</div>');
                            }
                        }, 'json');
                    });
                });
                </script> 
            </div>
        </div>
    </div>
</div> 