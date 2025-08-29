<?php
require_once dirname(__DIR__) . '/sys.conf.php';
require_once dirname(__DIR__) . '/core/LanguageManager.php';
$languageManager = LanguageManager::getInstance();

$plugins = $GLOBALS['plugins'] ?? [];
$system_config = $GLOBALS['system_config'] ?? [];
$feature_flags = $GLOBALS['feature_flags'] ?? [];
$api_config = $GLOBALS['api_config'] ?? [];
$message = '';

$activeTab = $_POST['active_tab'] ?? $_GET['tab'] ?? 'plugins';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($activeTab === 'plugins') {
        $newPlugins = [];
        if (!empty($_POST['plugin_key'])) {
            foreach ($_POST['plugin_key'] as $i => $key) {
                $key = trim($key);
                if ($key === '') continue;
                $fields = [
                    'enabled' => isset($_POST['plugin_enabled'][$i]) ? true : false,
                    'name' => trim($_POST['plugin_name'][$i] ?? ''),
                    'icon' => trim($_POST['plugin_icon'][$i] ?? ''),
                    'path' => trim($_POST['plugin_path'][$i] ?? ''),
                    'version' => trim($_POST['plugin_version'][$i] ?? ''),
                    'description' => trim($_POST['plugin_description'][$i] ?? ''),
                    'require_admin' => isset($_POST['plugin_require_admin'][$i]) ? true : false
                ];
                if (isset($_POST['delete']) && $_POST['delete'] === $key) {
                    continue; // Plugin löschen
                }
                $newPlugins[$key] = $fields;
            }
        }
        if (!empty($_POST['new_plugin_key'])) {
            $newKey = trim($_POST['new_plugin_key']);
            if ($newKey !== '') {
                $newPlugins[$newKey] = [
                    'enabled' => isset($_POST['new_plugin_enabled']) ? true : false,
                    'name' => trim($_POST['new_plugin_name'] ?? ''),
                    'icon' => trim($_POST['new_plugin_icon'] ?? ''),
                    'path' => trim($_POST['new_plugin_path'] ?? ''),
                    'version' => trim($_POST['new_plugin_version'] ?? ''),
                    'description' => trim($_POST['new_plugin_description'] ?? ''),
                    'require_admin' => isset($_POST['new_plugin_require_admin']) ? true : false
                ];
            }
        }
        $confPath = dirname(__DIR__) . '/sys.conf.php';
        $confContent = file_get_contents($confPath);
        $pluginExport = var_export($newPlugins, true);
        $confContent = preg_replace('/\/\/ --- PLUGINS START ---.*?\/\/ --- PLUGINS END ---/s', "\n// --- PLUGINS START ---\n\$plugins = $pluginExport;\n// --- PLUGINS END ---\n", $confContent);
        $result = @file_put_contents($confPath, $confContent);
        if ($result === false) {
            $message = '<div class="alert alert-danger">' . $languageManager->translateCore('system_config_save_error') . '</div>';
        } else {
            $plugins = $newPlugins;
            $message = '<div class="alert alert-success">' . $languageManager->translateCore('system_config_save_success') . '</div>';
        }
    }
    if ($activeTab === 'system_config') {
        $newConfig = [];
        foreach ($system_config as $key => $val) {
            if (is_bool($val)) {
                $newConfig[$key] = isset($_POST['system_config'][$key]);
            } elseif (is_array($val)) {
                $newConfig[$key] = isset($_POST['system_config'][$key]) ? $_POST['system_config'][$key] : $val;
            } else {
                $newConfig[$key] = $_POST['system_config'][$key] ?? $val;
            }
        }
        $confPath = dirname(__DIR__) . '/sys.conf.php';
        $confContent = file_get_contents($confPath);
        $export = var_export($newConfig, true);
        $confContent = preg_replace('/\/\/ --- SYSTEM_CONFIG START ---.*?\/\/ --- SYSTEM_CONFIG END ---/s', "\n// --- SYSTEM_CONFIG START ---\n\$system_config = $export;\n// --- SYSTEM_CONFIG END ---\n", $confContent);
        $result = @file_put_contents($confPath, $confContent);
        if ($result === false) {
            $message = '<div class="alert alert-danger">' . $languageManager->translateCore('system_config_save_error') . '</div>';
        } else {
            $system_config = $newConfig;
            $message = '<div class="alert alert-success">' . $languageManager->translateCore('system_config_save_success') . '</div>';
        }
    }
    if ($activeTab === 'feature_flags') {
        $newFlags = [];
        foreach ($feature_flags as $key => $val) {
            $newFlags[$key] = isset($_POST['feature_flags'][$key]);
        }
        $confPath = dirname(__DIR__) . '/sys.conf.php';
        $confContent = file_get_contents($confPath);
        $export = var_export($newFlags, true);
        $confContent = preg_replace('/\/\/ --- FEATURE_FLAGS START ---.*?\/\/ --- FEATURE_FLAGS END ---/s', "\n// --- FEATURE_FLAGS START ---\n\$feature_flags = $export;\n// --- FEATURE_FLAGS END ---\n", $confContent);
        $result = @file_put_contents($confPath, $confContent);
        if ($result === false) {
            $message = '<div class="alert alert-danger">' . $languageManager->translateCore('system_config_save_error') . '</div>';
        } else {
            $feature_flags = $newFlags;
            $message = '<div class="alert alert-success">' . $languageManager->translateCore('system_config_save_success') . '</div>';
        }
    }
    if ($activeTab === 'api_config') {
        $newApi = $api_config;
        foreach ($api_config as $key => $val) {
            if (is_array($val)) {
                foreach ($val as $subKey => $subVal) {
                    if (is_array($subVal)) {
                        foreach ($subVal as $k => $v) {
                            $newApi[$key][$subKey][$k] = $_POST['api_config'][$key][$subKey][$k] ?? $v;
                        }
                    } else {
                        $newApi[$key][$subKey] = $_POST['api_config'][$key][$subKey] ?? $subVal;
                    }
                }
            } else {
                $newApi[$key] = $_POST['api_config'][$key] ?? $val;
            }
        }
        $confPath = dirname(__DIR__) . '/sys.conf.php';
        $confContent = file_get_contents($confPath);
        $export = var_export($newApi, true);
        $confContent = preg_replace('/\/\/ --- API_CONFIG START ---.*?\/\/ --- API_CONFIG END ---/s', "\n// --- API_CONFIG START ---\n\$api_config = $export;\n// --- API_CONFIG END ---\n", $confContent);
        $result = @file_put_contents($confPath, $confContent);
        if ($result === false) {
            $message = '<div class="alert alert-danger">' . $languageManager->translateCore('system_config_save_error') . '</div>';
        } else {
            $api_config = $newApi;
            $message = '<div class="alert alert-success">' . $languageManager->translateCore('system_config_save_success') . '</div>';
        }
    }
}
?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h2 class="mb-0"><i class="bi bi-gear"></i> System Einstellungen</h2>
            </div>
            <div class="card-body">
                <ul class="nav nav-tabs mb-3" id="systemTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link active" data-tab="plugins" href="#">Plugins</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" data-tab="system_config" href="#">System-Konfiguration</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" data-tab="feature_flags" href="#">Feature Flags</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" data-tab="api_config" href="#">API-Konfiguration</a>
                    </li>
                </ul>
                <div class="tab-content" id="systemTabContent">
                    <div class="tab-pane fade show active" id="tab-plugins">
                        <?= $activeTab === 'plugins' ? $message : '' ?>
                        <!-- Plugins-Formular wie bisher -->
                        <form method="post">
                            <input type="hidden" name="active_tab" value="plugins">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Plugin-Key</th>
                                        <th>Aktiv</th>
                                        <th>Name</th>
                                        <th>Icon</th>
                                        <th>Pfad</th>
                                        <th>Version</th>
                                        <th>Beschreibung</th>
                                        <th>Admin?</th>
                                        <th>Aktion</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $i = 0; foreach ($plugins as $key => $fields): ?>
                                        <tr>
                                            <td><input type="text" name="plugin_key[]" value="<?= htmlspecialchars($key) ?>" class="form-control" required></td>
                                            <td class="text-center"><input type="checkbox" name="plugin_enabled[<?= $i ?>]" value="1" <?= !empty($fields['enabled']) ? 'checked' : '' ?>></td>
                                            <td><input type="text" name="plugin_name[]" value="<?= htmlspecialchars($fields['name'] ?? '') ?>" class="form-control"></td>
                                            <td><input type="text" name="plugin_icon[]" value="<?= htmlspecialchars($fields['icon'] ?? '') ?>" class="form-control"></td>
                                            <td><input type="text" name="plugin_path[]" value="<?= htmlspecialchars($fields['path'] ?? '') ?>" class="form-control"></td>
                                            <td><input type="text" name="plugin_version[]" value="<?= htmlspecialchars($fields['version'] ?? '') ?>" class="form-control"></td>
                                            <td><input type="text" name="plugin_description[]" value="<?= htmlspecialchars($fields['description'] ?? '') ?>" class="form-control"></td>
                                            <td class="text-center"><input type="checkbox" name="plugin_require_admin[<?= $i ?>]" value="1" <?= !empty($fields['require_admin']) ? 'checked' : '' ?>></td>
                                            <td><button type="submit" name="delete" value="<?= htmlspecialchars($key) ?>" class="btn btn-danger btn-sm">Löschen</button></td>
                                        </tr>
                                    <?php $i++; endforeach; ?>
                                    <tr>
                                        <td><input type="text" name="new_plugin_key" class="form-control" placeholder="Neuer Plugin-Key"></td>
                                        <td class="text-center"><input type="checkbox" name="new_plugin_enabled" value="1"></td>
                                        <td><input type="text" name="new_plugin_name" class="form-control" placeholder="Name"></td>
                                        <td><input type="text" name="new_plugin_icon" class="form-control" placeholder="Icon"></td>
                                        <td><input type="text" name="new_plugin_path" class="form-control" placeholder="Pfad"></td>
                                        <td><input type="text" name="new_plugin_version" class="form-control" placeholder="Version"></td>
                                        <td><input type="text" name="new_plugin_description" class="form-control" placeholder="Beschreibung"></td>
                                        <td class="text-center"><input type="checkbox" name="new_plugin_require_admin" value="1"></td>
                                        <td><button type="submit" class="btn btn-success btn-sm">Hinzufügen / Speichern</button></td>
                                    </tr>
                                </tbody>
                            </table>
                            <button type="submit" class="btn btn-primary">Alle Änderungen speichern</button>
                        </form>
                    </div>
                    <div class="tab-pane fade" id="tab-system_config">
                        <?= $activeTab === 'system_config' ? $message : '' ?>
                        <form method="post">
                            <input type="hidden" name="active_tab" value="system_config">
                            <table class="table table-bordered">
                                <thead><tr><th>Variable</th><th>Wert</th></tr></thead>
                                <tbody>
                                    <?php foreach ($system_config as $key => $val): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($key) ?></td>
                                            <td>
                                                <?php if (is_bool($val)): ?>
                                                    <input type="checkbox" name="system_config[<?= htmlspecialchars($key) ?>]" value="1" <?= $val ? 'checked' : '' ?>>
                                                <?php elseif (is_array($val)): ?>
                                                    <input type="text" name="system_config[<?= htmlspecialchars($key) ?>]" value="<?= htmlspecialchars(implode(',', $val)) ?>" class="form-control">
                                                <?php else: ?>
                                                    <input type="text" name="system_config[<?= htmlspecialchars($key) ?>]" value="<?= htmlspecialchars($val) ?>" class="form-control">
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <button type="submit" class="btn btn-primary">Speichern</button>
                        </form>
                    </div>
                    <div class="tab-pane fade" id="tab-feature_flags">
                        <?= $activeTab === 'feature_flags' ? $message : '' ?>
                        <form method="post">
                            <input type="hidden" name="active_tab" value="feature_flags">
                            <table class="table table-bordered">
                                <thead><tr><th>Feature</th><th>Aktiviert</th></tr></thead>
                                <tbody>
                                    <?php foreach ($feature_flags as $key => $val): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($key) ?></td>
                                            <td><input type="checkbox" name="feature_flags[<?= htmlspecialchars($key) ?>]" value="1" <?= $val ? 'checked' : '' ?>></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <button type="submit" class="btn btn-primary">Speichern</button>
                        </form>
                    </div>
                    <div class="tab-pane fade" id="tab-api_config">
                        <?= $activeTab === 'api_config' ? $message : '' ?>
                        <form method="post">
                            <input type="hidden" name="active_tab" value="api_config">
                            <table class="table table-bordered">
                                <thead><tr><th>Variable</th><th>Wert</th></tr></thead>
                                <tbody>
                                    <?php foreach ($api_config as $key => $val): ?>
                                        <?php if (is_array($val)): ?>
                                            <?php foreach ($val as $subKey => $subVal): ?>
                                                <?php if (is_array($subVal)): ?>
                                                    <?php foreach ($subVal as $k => $v): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($key . '[' . $subKey . '][' . $k . ']') ?></td>
                                                            <td><input type="text" name="api_config[<?= htmlspecialchars($key) ?>][<?= htmlspecialchars($subKey) ?>][<?= htmlspecialchars($k) ?>]" value="<?= htmlspecialchars($v) ?>" class="form-control"></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($key . '[' . $subKey . ']') ?></td>
                                                        <td><input type="text" name="api_config[<?= htmlspecialchars($key) ?>][<?= htmlspecialchars($subKey) ?>]" value="<?= htmlspecialchars($subVal) ?>" class="form-control"></td>
                                                    </tr>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td><?= htmlspecialchars($key) ?></td>
                                                <td><input type="text" name="api_config[<?= htmlspecialchars($key) ?>]" value="<?= htmlspecialchars($val) ?>" class="form-control"></td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <button type="submit" class="btn btn-primary">Speichern</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- JavaScript-Code wurde in assets/inc-js/system.js ausgelagert --> 