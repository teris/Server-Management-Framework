<?php
require_once __DIR__ . '/sys.conf.php';
define('FRAMEWORK_LOADED', true);
$databaseMode = in_array($modus_type['modus'] ?? 'api', ['mysql','database'], true);
require_once ($databaseMode ? __DIR__ . '/core/DatabaseOnlyFramework.php' : dirname(__DIR__) . '/framework.php');
require_once __DIR__ . '/auth_handler.php';
requireLogin(); Identity::requireIdentity('backend', true);
if (isset($_GET['heartbeat']) || in_array($_POST['action'] ?? '', ['heartbeat','extend_session'], true)) {
    header('Content-Type: application/json; charset=utf-8'); echo json_encode(['success'=>true,'remaining'=>SessionManager::getSessionTimeRemaining(),'timeout'=>HttpSecurity::timeout()]); exit;
}
if (($_POST['action'] ?? '') === 'logout') { Identity::logout(); header('Location: login.php', true, 303); exit; }
if (($_GET['option'] ?? '') === 'manualupdater') { require __DIR__ . '/inc/manualupdater.php'; exit; }
require_once __DIR__ . '/core/EmailTemplateManager.php';
$emailTemplateManager = EmailTemplateManager::getInstance();
$db = DatabaseManager::getInstance();
if (isset($_POST['action']) && ($_POST['core'] ?? '') === 'admin') {
    require_once __DIR__ . '/core/AdminHandler.php'; header('Content-Type: application/json; charset=utf-8');
    echo HttpSecurity::encode((new AdminHandler())->handleRequest((string) $_POST['action'], $_POST)); exit;
}
if (($_POST['action'] ?? '') === 'email_templates') { require __DIR__ . '/inc/email-templates.php'; exit; }
require_once __DIR__ . '/module/ModuleBase.php';
$pluginManager = ModuleLoader::getInstance();
if (isset($_POST['plugin'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo HttpSecurity::encode($pluginManager->handlePluginRequest((string) $_POST['plugin'], (string) ($_POST['action'] ?? ''), $_POST)); exit;
}
if (isset($_POST['action'])) { require __DIR__ . '/handler.php'; exit; }
$serviceManager = new ServiceManager();
$session_info = getSessionInfoForJS();
$lang = LanguageManager::getInstance();
require_once __DIR__ . '/core/AdminCore.php';
$adminCore = new AdminCore(); $dashboardStats = $adminCore->getDashboardStats();
$browserConfig = ['sessionInfo'=>$session_info, 'enabledPlugins'=>array_keys($pluginManager->getEnabledPlugins()),'dashboardStats'=>$dashboardStats,'jsTranslations'=>tMultiple([

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
            'name', 'domain', 'status', 'actions', 'active', 'inactive', 'edit', 'delete',
           'check_for_updates', 'start_update', 'nightly_version', 'stable_version', 'yes', 'no',
           'update_available_msg', 'no_update_available', 'update_check_error', 'select_update_type_error',
           'update_successful', 'reload_page_info', 'zip_available', 'zip_not_available',
           'creating_backup', 'backup_successfully_created', 'files_backed_up', 'database_backed_up',
           'backup_failed', 'backup_error', 'backup_list_error', 'full_backup', 'files_only',
           'confirm_delete_backup', 'delete_backup_not_implemented', 'create_backup'
        ])];
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($system_config['site_title'] ?? Config::FRONTPANEL_SITE_NAME, ENT_QUOTES, 'UTF-8') ?> – Verwaltung</title>
    
    <!-- Bootstrap CSS -->
    <link href="../public/assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="../public/assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" type="text/css" href="assets/main.css">
     <!-- jQuery -->
     <script src="../public/assets/vendor/jquery/jquery.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="../public/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <!-- ACE Editor -->
    <script src="../public/assets/vendor/ace/ace.js"></script>

<script type="application/json" id="dashboard-config"><?= json_encode($browserConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script><script src="assets/dashboard-config.js"></script>
    <script src="assets/main.js"></script>
    <script src="assets/admin-core.js"></script>
    <script src="assets/session.js"></script>
    <!-- JavaScript-Loader für inc-js Dateien -->
    <script src="assets/inc-js/inc-js-loader.js"></script>
    
    <!-- Plugin-spezifische Styles -->
    <?php foreach ($pluginManager->getAllStyles() as $style): ?>
    <link rel="stylesheet" type="text/css" href="<?= htmlspecialchars($style) ?>">
    <?php endforeach; ?>
<meta name="csrf-token" content="<?php echo HttpSecurity::csrfToken(); ?>"><script src="assets/security.js"></script></head>
<body class="bg-light">
    <!-- Sidebar-Menü -->
    <nav id="sidebarMenu" class="d-md-block bg-light sidebar collapse position-fixed" style="width: 220px; height: 100vh; z-index: 1040;">
        <div class="position-sticky d-flex flex-column h-100">
            <ul class="nav flex-column mt-3">
                <!-- Dashboard -->
                <li class="nav-item">
                    <a class="nav-link" href="?option=admin">
                        <i class="bi bi-speedometer2"></i> <?= t('dashboard') ?>
                    </a>
                </li>
                <!-- Ressourcen & Monitoring -->
                <li class="nav-item">
                    <a class="nav-link" href="?option=resources">
                        <i class="bi bi-hdd-stack"></i> <?= t('resources') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="?option=logs">
                        <i class="bi bi-journal-text"></i> <?= t('logs') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="?option=users">
                        <i class="bi bi-people"></i> <?= t('users') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="?option=createuser">
                        <i class="bi bi-person-plus"></i> <?= t('create_user') ?>
                    </a>
                </li>
                <!-- Persönliche Einstellungen -->
                <li class="nav-item">
                    <a class="nav-link" href="?option=profile">
                        <i class="bi bi-person"></i> <?= t('profile') ?>
                    </a>
                </li>
                <!-- Benutzer & Module -->
                <li class="nav-item">
                    <a class="nav-link nav-link-category" href="#" data-bs-toggle="collapse" data-bs-target="#moduleSubmenu" aria-expanded="true" aria-controls="moduleSubmenu">
                        <i class="bi bi-boxes"></i> <?= t('modules') ?>
                        <i class="bi bi-chevron-up ms-auto"></i>
                    </a>
                    <div class="collapse show" id="moduleSubmenu">
                        <ul class="nav flex-column ms-3">
                            <?php
                            // Dynamische Module laden
                            if (isset($pluginManager) && method_exists($pluginManager, 'getEnabledPlugins')) {
                                foreach ($pluginManager->getEnabledPlugins() as $plugin_key => $plugin_info): 
                            ?>
                            <li>
                                <a href="?option=modules&mod=<?= $plugin_key ?>">
                                    <?= htmlspecialchars($plugin_info['name'] ?? ucfirst($plugin_key)) ?>
                                </a>
                            </li>
                            <?php 
                                endforeach;
                            } else {
                                // Fallback für statische Module
                            ?>
                            <li class="nav-item">
                                <a class="nav-link" href="?option=modules">
                                    <i class="bi bi-boxes"></i> <?= t('modules') ?>
                                </a>
                            </li>
                            <?php } ?>
                        </ul>
                    </div>
                </li>
                
                <!-- Domain-Management -->
                <li class="nav-item">
                    <a class="nav-link nav-link-category" href="#" data-bs-toggle="collapse" data-bs-target="#domainSubmenu" aria-expanded="true" aria-controls="domainSubmenu">
                        <i class="bi bi-globe"></i> <?= t('domains') ?>
                        <i class="bi bi-chevron-up ms-auto"></i>
                    </a>
                    <div class="collapse show" id="domainSubmenu">
                        <ul class="nav flex-column ms-3">
                            <li>
                                <a href="?option=domain-settings">
                                    <i class="bi bi-gear"></i> <?= t('domain_settings') ?>
                                </a>
                            </li>
                            <li>
                                <a href="?option=domain-registrations">
                                    <i class="bi bi-list-ul"></i> <?= t('domain_registrations') ?>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                
                
                
                <!-- Optionen -->
                <li class="nav-item">
                    <a class="nav-link nav-link-category" href="#" data-bs-toggle="collapse" data-bs-target="#optionsSubmenu" aria-expanded="true" aria-controls="optionsSubmenu">
                        <i class="bi bi-gear"></i> <?= t('options') ?>
                        <i class="bi bi-chevron-up ms-auto"></i>
                    </a>
                    <div class="collapse show" id="optionsSubmenu">
                        <ul class="nav flex-column ms-3">
                            <li >
                                <a href="?option=system">
                                    <i class="bi bi-sliders"></i> <?= t('system_settings') ?>
                                </a>
                            </li>
                            <li >
                                <a href="?option=email-templates">
                                    <i class="bi bi-envelope"></i> <?= t('email_templates') ?>
                                </a>
                            </li>
                            <li >
                                <a href="?option=settings">
                                    <i class="bi bi-gear"></i> <?= t('settings') ?>
                                </a>
                            </li>
                            <li >
                                <a href="?option=manualupdater">
                                    <i class="bi bi-arrow-clockwise"></i> <?= t('manual_updater') ?>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
               
            </ul>
            <div class="mt-auto mb-3 px-3">
                <hr>
                <a href="password_change.php" class="btn btn-outline-secondary w-100 mb-2">
                    <i class="bi bi-key"></i> <?= t('password') ?>
                </a>
                <form method="post" action="index.php" class="d-inline"><?= HttpSecurity::csrfField() ?><input type="hidden" name="action" value="logout"><button type="submit" class="nav-link" style="background:none;border:0">
                    <i class="bi bi-box-arrow-right"></i> <?= t('logout') ?>
                </button></form>
            </div>
        </div>
    </nav>
    <!-- Sidebar Toggle Button -->
    <button class="btn btn-primary d-md-none position-fixed" style="top: 1rem; left: 1rem; z-index: 1050;" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Menü öffnen">
        <i class="bi bi-list"></i>
    </button>
    <!-- Hauptinhalt mit Padding für Sidebar -->
    <div class="container-fluid main-content">
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
                            <form method="post" action="index.php" class="d-inline"><?= HttpSecurity::csrfField() ?><input type="hidden" name="action" value="logout"><button type="submit" class="nav-link" style="background:none;border:0">
                                <i class="bi bi-box-arrow-right"></i> <?= t('logout') ?>
                            </button></form>
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
                        $option = isset($_GET['option']) ? $_GET['option'] : '';
                        
                        // Statische Optionen zuerst prüfen
                        switch($option) {
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
                            case 'users':
                                include __DIR__ . '/inc/users.php';
                                break;
                            case 'createuser':
                                include('inc/createuser.php');
                                break;
                            case 'domain-registrations':
                                include('inc/domain-registrations.php');
                                break;
                            case 'domain-settings':
                                include('inc/domain-settings.php');
                                break;
                            case 'system':
                                include('inc/system.php');
                                break;
                            case 'email-templates':
                                include('inc/email-templates.php');
                                break;
                            case 'manualupdater':
                                include('inc/manualupdater.php');
                                break;
                            default:
                                echo'<!-- Willkommensbereich (Standard) -->
                                        <div id="welcome-area" class="mt-5">
                                            <div class="alert alert-info text-center">
                                                '.t('welcome_admin_area').' 
                                            </div>
                                        </div>';
                                include('inc/admin.php');
                                
                                    
                                
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
                        <h2><?= t('footer') ?></h2>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-4">
                                <p>Links</p>
                            </div>
                            <div class="col-4">
                                <p><pre><?php
                               // print_r(t('debug', null, null, true));
                                ?></pre></p>
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

   
    <script src="assets/index.js"></script>

</body>
</html>
