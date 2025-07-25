<?php
/**
 * Server Management Interface - Admin Dashboard Core
 * Version 3.0 mit integriertem Admin-Modul und Plugin-System
 */

require_once 'sys.conf.php';
$frameworkFile = 'framework.php';
if ($modus_type['modus'] === 'mysql') {
    $frameworkFile = 'core/DatabaseOnlyFramework.php';
} elseif ($modus_type['modus']  === 'mysql') {
    $frameworkFile = 'core/DatabaseOnlyFramework.php';
}
require_once $frameworkFile;
require_once 'auth_handler.php';

// Login-Überprüfung
requireLogin();

// Plugin-System initialisieren
try {
    require_once 'module/ModuleBase.php';
    $pluginManager = ModuleLoader::getInstance();
} catch (Exception $e) {
    error_log('Error initializing plugin manager: ' . $e->getMessage());
    // Fallback: Leeren Plugin-Manager erstellen
    $pluginManager = new class {
        public function getEnabledPlugins() { return []; }
        public function getAllStyles() { return []; }
        public function getEnabledModules() { return []; }
    };
}

// Handler für Logout
if (isset($_GET['logout'])) {
    SessionManager::logout();
    header('Location: login.php');
    exit;
}

// AJAX Handler
if (isset($_POST['action'])) {
    // Error reporting für AJAX-Requests deaktivieren
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    ini_set('display_errors', 0);
    
    header('Content-Type: application/json');
    
    // Session-Check für AJAX
    if (!SessionManager::isLoggedIn()) {
        echo json_encode(['success' => false, 'redirect' => 'login.php']);
        exit;
    }
    
    // Heartbeat
    if (isset($_POST['action']) && $_POST['action'] === 'heartbeat') {
        SessionManager::updateActivity();
        echo json_encode(['success' => true]);
        exit;
    }
    
    // Core Admin Actions (direkt verarbeiten)
    if (isset($_POST['core']) && $_POST['core'] === 'admin') {
        try {
            require_once 'core/AdminHandler.php';
            $adminHandler = new AdminHandler();
            
            $result = $adminHandler->handleRequest($_POST['action'] ?? '', $_POST);
            echo json_encode($result);
        } catch (Exception $e) {
            error_log('AdminHandler Exception: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    // Plugin Actions
    if (isset($_POST['plugin'])) {
        $plugin_key = $_POST['plugin'];
        $action = $_POST['action'] ?? '';
        
        // Debug-Log
        error_log("index.php: Plugin request - plugin: $plugin_key, action: $action");
        
        // Daten sammeln
        $data = $_POST;
        unset($data['plugin']);
        unset($data['action']);
        
        $result = $pluginManager->handlePluginRequest($plugin_key, $action, $data);
        
        // Debug-Log
        error_log("index.php: Plugin response - " . json_encode($result));
        
        echo json_encode($result);
        exit;
    }
    
    // Legacy Support
    include("handler.php");
    exit;
}

// Session-Informationen
try {
    $session_info = getSessionInfoForJS();
} catch (Exception $e) {
    error_log('Error getting session info: ' . $e->getMessage());
    $session_info = [];
}

// Admin-Statistiken laden
try {
    require_once 'core/AdminCore.php';
    require_once 'core/LanguageManager.php';
    $adminCore = new AdminCore();
    $lang = LanguageManager::getInstance();
    $dashboardStats = $adminCore->getDashboardStats();
} catch (Exception $e) {
    error_log('Error loading admin stats: ' . $e->getMessage());
    $dashboardStats = [];
}

?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('admin_dashboard') ?> - <?= t('server_management') ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" type="text/css" href="assets/main.css">
     <!-- jQuery -->
     <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script src="assets/main.js"></script>
    <script src="assets/admin-core.js"></script>
    <script src="assets/session.js"></script>
    
    <!-- Plugin-spezifische Styles -->
    <?php foreach ($pluginManager->getAllStyles() as $style): ?>
    <link rel="stylesheet" type="text/css" href="<?= htmlspecialchars($style) ?>">
    <?php endforeach; ?>
</head>
<body class="bg-light">
    <!-- Sidebar-Menü -->
    <nav id="sidebarMenu" class="d-md-block bg-light sidebar collapse position-fixed" style="width: 220px; height: 100vh; z-index: 1040;">
        <div class="position-sticky d-flex flex-column h-100">
            <ul class="nav flex-column mt-3">
                <li class="nav-item">
                    <a class="nav-link" href="?option=admin">
                        <i class="bi bi-speedometer2"></i> <?= t('dashboard') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="?option=modules">
                        <i class="bi bi-boxes"></i> <?= t('modules') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="?option=settings">
                        <i class="bi bi-gear"></i> <?= t('settings') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="?option=profile">
                        <i class="bi bi-person"></i> <?= t('profile') ?>
                    </a>
                </li>   
                <li class="nav-item">
                    <a class="nav-link" href="?option=logs">
                        <i class="bi bi-journal-text"></i> <?= t('logs') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="?option=resources">
                        <i class="bi bi-hdd-stack"></i> <?= t('resources') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="?option=system">
                        <i class="bi bi-sliders"></i> <?= t('system_settings') ?>
                    </a>
                </li>
            </ul>
            <div class="mt-auto mb-3 px-3">
                <hr>
                <a href="password_change.php" class="btn btn-outline-secondary w-100 mb-2">
                    <i class="bi bi-key"></i> <?= t('password') ?>
                </a>
                <a href="?logout=1" class="btn btn-outline-danger w-100">
                    <i class="bi bi-box-arrow-right"></i> <?= t('logout') ?>
                </a>
            </div>
        </div>
    </nav>
    <!-- Sidebar Toggle Button -->
    <button class="btn btn-primary d-md-none position-fixed" style="top: 1rem; left: 1rem; z-index: 1050;" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Menü öffnen">
        <i class="bi bi-list"></i>
    </button>
    <!-- Hauptinhalt mit Padding für Sidebar -->
    <div class="container-fluid" style="margin-left: 220px;">
        <!-- User Info Header (immer sichtbar) -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px; font-size: 1.5rem; font-weight: bold;">
                                <?= strtoupper(substr($session_info['user']['full_name'] ?? $session_info['user']['username'], 0, 1)) ?>
                            </div>
                            <div>
                                <h4 class="mb-0"><?= htmlspecialchars($session_info['user']['full_name'] ?? $session_info['user']['username']) ?></h4>
                                <p class="text-muted mb-0"><?= htmlspecialchars($session_info['user']['email']) ?> • <?= htmlspecialchars($session_info['user']['role']) ?></p>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            
                               <a href="?option=settings" class="btn btn-outline-warning btn-sm">  
                                <i class="bi bi-database"></i> <?php
                                    $anzeigeModus = '';
                                    if (isset($modus_type['modus'])) {
                                        $anzeigeModus = ($modus_type['modus'] === 'api') ? 'LIVE' : (($modus_type['modus'] === 'mysql') ? 'CronJob' : $modus_type['modus']);
                                    }
                                    echo htmlspecialchars($anzeigeModus);
                                ?>
                                </a>
                            <div class="badge bg-info" id="sessionTimer">
                                <i class="bi bi-clock"></i> <span id="timeRemaining">--:--</span>
                            </div>
                            <?php if ($modus_type['modus']  === 'mysql'): ?>
                            <a href="update.php" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-arrow-repeat"></i><?= t('update') ?> 
                            </a>
                            <?php endif; ?>
                            <a href="password_change.php" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-key"></i> <?= t('password') ?>
                            </a>
                            <a href="?logout=1" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-box-arrow-right"></i> <?= t('logout') ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Haupt-Admin-Dashboard (ausgeblendet) -->
        <div id="admin-dashboard">
            
            <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h1 class="card-title"><?= t('admin_dashboard') ?></h1>
                                <p class="card-text text-muted"><?= t('server_management') ?> • <?= count($pluginManager->getEnabledPlugins()) ?> <?= t('plugins_active') ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Haupt-Admin-Dashboard (immer sichtbar) -->
                <?php
                    try {
                        switch((isset($_GET['option']) ? $_GET['option'] : '')) {
                            case 'admin':
                                include('inc/admin.php');
                                break;
                            case 'modules':
                                include('inc/module.php');
                                break;
                            case 'settings':
                                include('inc/settings.php');
                                break;
                            case 'profile':
                                include('inc/profile.php');
                                break;
                            case 'logs':
                                include('inc/logs.php');
                                break;
                            case 'resources':
                                include('inc/resources.php');
                                break;
                            case 'system':
                                include('inc/system.php');
                                break;
                            default:
                                echo'<!-- Willkommensbereich (Standard) -->
                                <div id="welcome-area" class="mt-5">
                                    <div class="alert alert-info text-center">
                                        '.t('welcome_admin_area').' 
                                    </div>
                                </div>';
                        }   

                    } catch (Exception $e) {
                        error_log('Error getting loading file info: ' . $e->getMessage());
                        $_GET['option'] = [];
                    }
              
                ?>

        
        <!-- Dynamischer Content Ende -->
         </div>
        <!-- Footer -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h2>Footer</h2>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-4">
                                <p>Links</p>
                            </div>
                            <div class="col-4">
                                <p>center</p>
                            </div>
                            <div class="col-4">
                                <p>right</p>
                            </div>
                        </div>
                    </div>
                
            </div>

        </div>
    </div>

    <!-- Toast für Benachrichtigungen -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        <div id="notificationToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto" id="toastTitle"><?= t('notification') ?></strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body" id="toastBody">
                <?= t('message_here') ?>
            </div>
        </div>
    </div>

   
    <script>
        window.sessionInfo = <?= json_encode($session_info ?? []) ?>;
        window.enabledPlugins = <?= json_encode($pluginManager->getEnabledPlugins() ?? []) ?>;
        window.dashboardStats = <?= json_encode($dashboardStats ?? []) ?>;
        window.jsTranslations = <?= json_encode(tMultiple([
            'js_network_error', 'js_server_error', 'js_ajax_error', 'js_session_expired',
            'js_plugin_load_error', 'js_unknown_error', 'js_form_submit_error', 'js_form_success',
            'js_vm_load_error', 'js_vm_control_success', 'js_vm_control_error', 'js_no_vms_found',
            'js_website_load_error', 'js_no_websites_found', 'js_domain_load_error', 'js_no_domains_found',
            'js_no_logs_found', 'js_stats_updating', 'js_cache_clearing', 'js_connections_testing',
            'js_settings_saved', 'js_loading', 'js_processing', 'js_confirm_delete',
            'js_confirm_vm_delete', 'js_confirm_website_delete', 'js_confirm_database_delete',
            'js_confirm_email_delete', 'js_operation_successful', 'js_operation_failed',
            'js_validation_failed', 'js_access_denied', 'js_timeout_error', 'js_connection_lost',
            'js_data_load_error', 'js_data_save_error', 'js_data_update_error', 'js_data_delete_error',
            'js_please_wait', 'js_retry_later', 'js_contact_admin', 'js_debug_info',
            'js_available_plugins', 'js_session_info', 'js_not_available', 'js_admin_dashboard_initialized',
            'name', 'domain', 'status', 'actions', 'active', 'inactive', 'edit', 'delete'
        ])) ?>;

        function loadSettingsContent() {
            const settingsDiv = document.getElementById('settings-content');
            if (!settingsDiv) return;
            settingsDiv.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Laden...</span></div></div>';
            fetch('inc/settings.php')
                .then(response => response.text())
                .then(html => {
                    settingsDiv.innerHTML = html;
                })
                .catch(err => {
                    settingsDiv.innerHTML = '<div class="alert alert-danger">Fehler beim Laden der Einstellungen.</div>';
                });
        }
        // Tab-Event für Einstellungen
        const settingsTab = document.getElementById('settings-tab');
        if (settingsTab) {
            settingsTab.addEventListener('shown.bs.tab', function (e) {
                loadSettingsContent();
            });
        }
        // URL-Parameter auswerten
        function getUrlParameter(name) {
            name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
            const regex = new RegExp('[\?&]' + name + '=([^&#]*)');
            const results = regex.exec(window.location.search);
            return results === null ? null : decodeURIComponent(results[1].replace(/\+/g, ' '));
        }
        // Wenn ?option=settings, Tab automatisch öffnen und laden
        if (getUrlParameter('option') === 'settings') {
            const tab = document.getElementById('settings-tab');
            if (tab) {
                var bsTab = new bootstrap.Tab(tab);
                bsTab.show();
                loadSettingsContent();
            }
        }

        // Sidebar-Navigation Umschalten
        function showArea(area) {
            document.getElementById('welcome-area').style.display = 'none';
            document.getElementById('admin-dashboard').style.display = 'none';
            document.getElementById('plugin-area').style.display = 'none';
            if (area === 'dashboard') {
                document.getElementById('admin-dashboard').style.display = 'block';
            } else if (area === 'modules') {
                document.getElementById('plugin-area').style.display = 'block';
            } else {
                document.getElementById('welcome-area').style.display = 'block';
            }
        }
        document.getElementById('show-dashboard').addEventListener('click', function(e) {
            e.preventDefault();
            showArea('dashboard');
        });
        document.getElementById('show-modules').addEventListener('click', function(e) {
            e.preventDefault();
            showArea('modules');
        });
        // Beim Laden nur Willkommensbereich anzeigen
        showArea();
    </script>
</body>
</html>