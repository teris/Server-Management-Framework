<?php
/**
 * Server Management Interface - Admin Dashboard Core
 * Version 3.0 mit integriertem Admin-Modul und Plugin-System
 */

require_once 'sys.conf.php';

// Framework-Loaded Konstante für Sicherheitschecks
define('FRAMEWORK_LOADED', true);

$frameworkFile = '../framework.php';
if ($modus_type['modus'] === 'mysql') {
    $frameworkFile = 'core/DatabaseOnlyFramework.php';
} elseif ($modus_type['modus']  === 'mysql') {
    $frameworkFile = 'core/DatabaseOnlyFramework.php';
}
require_once $frameworkFile;
require_once 'auth_handler.php';

// TODO: E-Mail-Template-Manager laden
require_once 'core/EmailTemplateManager.php';

// Login-Überprüfung
requireLogin();

// TODO: E-Mail-Template-System initialisieren
$emailTemplateManager = EmailTemplateManager::getInstance();
$emailTemplateManager->createDefaultTemplates();

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
    // Prüfen ob es sich um die createuser.php oder users.php Seite handelt
    $isCreateUserPage = isset($_GET['option']) && $_GET['option'] === 'createuser';
    $isUsersPage = isset($_GET['option']) && $_GET['option'] === 'users';
    $isCreateUserForm = isset($_POST['createuser_form']);
    
    // Wenn es die createuser.php Seite ist und das createuser_form Feld gesetzt ist,
    // dann nicht als AJAX behandeln
    if ($isCreateUserPage && $isCreateUserForm) {
        // Normale Seitenverarbeitung fortsetzen
    } else {
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
    
    // Manual Updater Actions
    if (isset($_GET['option']) && $_GET['option'] === 'manualupdater') {
        try {
            require_once 'inc/manualupdater.php';
            // Der AJAX-Handler ist bereits in der manualupdater.php definiert
            // und wird hier automatisch ausgeführt
        } catch (Exception $e) {
            error_log('ManualUpdater Exception: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    // Email Templates Actions
    if (isset($_POST['action']) && $_POST['action'] === 'email_templates') {
        try {
            require_once 'inc/email-templates.php';
            // Der AJAX-Handler ist bereits in der email-templates.php definiert
            // und wird hier automatisch ausgeführt
        } catch (Exception $e) {
            error_log('EmailTemplates Exception: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    // Users Module AJAX Actions (direkt verarbeiten)
    if (isset($_GET['option']) && $_GET['option'] === 'users' && isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // Debug-Log
        error_log("index.php: Users module request - action: $action");
        
        // Neue AJAX-Aktionen für erweiterte Dialogfelder
        if (in_array($action, ['get_user_data', 'get_user_details', 'get_user_system_links', 'get_customer_details', 'edit_system_user', 'delete_system_user', 'edit_customer', 'create_customer', 'get_customers', 'create_system_user', 'edit_user', 'delete_customer', 'delete_user'])) {
            // Diese Aktionen werden in users.php verarbeitet
            error_log("index.php: Including users.php for action: $action");
            include('inc/users.php');
            exit;
        } else {
            error_log("index.php: Action $action not found in users module actions");
        }
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
}

// Früher GET-Handler für Manual Updater Downloads (muss vor jeglichem HTML-Output stehen)
if (
    isset($_GET['option']) && $_GET['option'] === 'manualupdater' &&
    isset($_GET['action']) && $_GET['action'] === 'download_backup'
) {
    // Keine Ausgabe vor den Headern zulassen
    if (ob_get_level()) {
        @ob_end_clean();
    }
    require_once 'inc/manualupdater.php';
    exit;
}

// AJAX-Handler für users.php - muss vor HTML-Output stehen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_GET['ajax']) && isset($_GET['option']) && $_GET['option'] === 'users') {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_user_data':
            try {
                $userId = $_POST['user_id'];
                $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                $db->execute($stmt, [$userId]);
                $user = $db->fetch($stmt);
                
                if ($user) {
                    // System-Verknüpfungen abrufen
                    $stmt = $db->prepare("SELECT permission_type, resource_id FROM user_permissions WHERE user_id = ?");
                    $db->execute($stmt, [$userId]);
                    $systemLinks = $db->fetchAll($stmt);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $user,
                        'system_links' => $systemLinks
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Benutzer nicht gefunden'
                    ]);
                }
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Fehler beim Laden der Benutzerdaten: ' . $e->getMessage()
                ]);
            }
            exit;
            
        case 'get_user_systems':
            try {
                $userId = $_POST['user_id'];
                $stmt = $db->prepare("SELECT permission_type, resource_id FROM user_permissions WHERE user_id = ?");
                $db->execute($stmt, [$userId]);
                $systems = $db->fetchAll($stmt);
                
                echo json_encode([
                    'success' => true,
                    'systems' => $systems
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Fehler beim Laden der System-Verknüpfungen: ' . $e->getMessage()
                ]);
            }
            exit;
            
        case 'get_user_details':
            try {
                $userId = $_POST['user_id'];
                $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                $db->execute($stmt, [$userId]);
                $user = $db->fetch($stmt);
                
                if ($user) {
                    // System-Verknüpfungen abrufen
                    $stmt = $db->prepare("SELECT permission_type, resource_id FROM user_permissions WHERE user_id = ?");
                    $db->execute($stmt, [$userId]);
                    $systemLinks = $db->fetchAll($stmt);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $user,
                        'system_links' => $systemLinks
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Benutzer nicht gefunden'
                    ]);
                }
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Fehler beim Laden der Benutzerdetails: ' . $e->getMessage()
                ]);
            }
            exit;
            
        case 'get_system_user_data':
            $systemType = $_POST['system_type'];
            $systemUserId = $_POST['system_user_id'];
            
            $userData = [];
            try {
                // Hier können Sie die Logik für verschiedene Systemtypen implementieren
                switch ($systemType) {
                    case 'proxmox':
                        // Proxmox-spezifische Logik
                        break;
                    case 'ispconfig':
                        // ISPConfig-spezifische Logik
                        break;
                    case 'ovh':
                        // OVH-spezifische Logik
                        break;
                    default:
                        throw new Exception('Unbekannter Systemtyp: ' . $systemType);
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => $userData
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Fehler beim Laden der System-Benutzerdaten: ' . $e->getMessage()
                ]);
            }
            exit;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Unbekannte Aktion: ' . htmlspecialchars($_POST['action'])
            ]);
            exit;
    }
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
    <!-- JavaScript-Loader für inc-js Dateien -->
    <script src="assets/inc-js/inc-js-loader.js"></script>
    
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
                                // POST-Verarbeitung für Benutzerverwaltungsaktionen
                                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
                                    switch ($_POST['action']) {
                                        case 'activate_customer':
                                            handleActivateCustomer($serviceManager, $db);
                                            break;
                                        case 'merge_users':
                                            handleMergeUsers($serviceManager, $db);
                                            break;
                                        case 'link_existing_user':
                                            handleLinkExistingUser($serviceManager, $db);
                                            break;
                                        case 'grant_access':
                                            handleGrantAccess($serviceManager, $db);
                                            break;
                                        case 'revoke_access':
                                            handleRevokeAccess($serviceManager, $db);
                                            break;
                                        case 'edit_user':
                                            handleEditUser($db);
                                            break;
                                        case 'delete_user':
                                            handleDeleteUser($serviceManager, $db);
                                            break;
                                        case 'update_password':
                                            handleUpdatePassword($serviceManager, $db);
                                            break;
                                        case 'extend_session':
                                            // Session verlängern - einfache Implementierung
                                            if (isset($_SESSION['user_id'])) {
                                                $_SESSION['last_activity'] = time();
                                                if (isset($_POST['ajax'])) {
                                                    header('Content-Type: application/json');
                                                    echo json_encode(['success' => true, 'message' => 'Session verlängert']);
                                                    exit;
                                                } else {
                                                    $_SESSION['success_message'] = "Session erfolgreich verlängert!";
                                                    header("Location: " . $_SERVER['REQUEST_URI']);
                                                    exit;
                                                }
                                            } else {
                                                if (isset($_POST['ajax'])) {
                                                    header('Content-Type: application/json');
                                                    echo json_encode(['success' => false, 'message' => 'Keine aktive Session']);
                                                    exit;
                                                } else {
                                                    $_SESSION['error_message'] = "Keine aktive Session gefunden!";
                                                    header("Location: " . $_SERVER['REQUEST_URI']);
                                                    exit;
                                                }
                                            }
                                        default:
                                            $_SESSION['error_message'] = "Unbekannte Aktion: " . htmlspecialchars($_POST['action']);
                                            header("Location: " . $_SERVER['REQUEST_URI']);
                                            exit;
                                    }
                                }
                                
                                include('inc/users.php');
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
            'name', 'domain', 'status', 'actions', 'active', 'inactive', 'edit', 'delete',
           'check_for_updates', 'start_update', 'nightly_version', 'stable_version', 'yes', 'no',
           'update_available_msg', 'no_update_available', 'update_check_error', 'select_update_type_error',
           'update_successful', 'reload_page_info', 'zip_available', 'zip_not_available',
           'creating_backup', 'backup_successfully_created', 'files_backed_up', 'database_backed_up',
           'backup_failed', 'backup_error', 'backup_list_error', 'full_backup', 'files_only',
           'confirm_delete_backup', 'delete_backup_not_implemented', 'create_backup'
        ])) ?>;

        // URL-Parameter auswerten
        function getUrlParameter(name) {
            name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
            const regex = new RegExp('[\?&]' + name + '=([^&#]*)');
            const results = regex.exec(window.location.search);
            return results === null ? null : decodeURIComponent(results[1].replace(/\+/g, ' '));
        }

        // Sidebar-Navigation - Aktive Links hervorheben
        function highlightActiveNavLink() {
            const currentOption = getUrlParameter('option');
            const navLinks = document.querySelectorAll('#sidebarMenu .nav-link');
            
            navLinks.forEach(link => {
                link.classList.remove('active');
                const href = link.getAttribute('href');
                if (href && href.includes('option=' + currentOption)) {
                    link.classList.add('active');
                }
            });
        }
        
        // Beim Laden aktive Links hervorheben
        highlightActiveNavLink();
        
        // Editor-Funktionen für E-Mail-Templates
        window.toggleWordWrap = function(editorId) {
            const textarea = document.getElementById(editorId + "_textarea");
            if (textarea) {
                textarea.style.whiteSpace = textarea.style.whiteSpace === 'nowrap' ? 'pre-wrap' : 'nowrap';
            }
        };
        
        window.toggleLineNumbers = function(editorId) {
            // Einfache Implementierung für Textarea
            console.log("Zeilennummern umschalten für Editor:", editorId);
        };
        
        window.changeMode = function(editorId, mode) {
            const textarea = document.getElementById(editorId + "_textarea");
            if (textarea) {
                textarea.setAttribute('data-mode', mode);
                console.log("Editor-Modus geändert zu:", mode);
            }
        };
        
        
        // Vollständige HTML-Struktur aus Body-Inhalt wiederherstellen
        window.reconstructFullHtml = function(bodyContent) {
            // Prüfen ob bereits vollständige HTML-Struktur vorhanden ist
            if (bodyContent.includes('<html') && bodyContent.includes('</html>')) {
                return bodyContent;
            }
            
            // HTML-Struktur extrahieren aus dem ursprünglichen Textarea-Inhalt
            const textarea = document.getElementById('ace-editor_textarea');
            if (textarea && textarea.value) {
                const originalContent = textarea.value;
                
                // Head-Teil extrahieren
                const headMatch = originalContent.match(/<head[^>]*>([\s\S]*?)<\/head>/i);
                const headContent = headMatch ? headMatch[0] : '<head><title>E-Mail Template</title></head>';
                
                // Body-Teil mit dem bearbeiteten Inhalt ersetzen
                const bodyStart = originalContent.indexOf('<body');
                const bodyEnd = originalContent.lastIndexOf('</body>');
                
                if (bodyStart !== -1 && bodyEnd !== -1) {
                    const beforeBody = originalContent.substring(0, bodyStart);
                    const afterBody = originalContent.substring(bodyEnd + 7);
                    
                    // Neuen Body mit bearbeitetem Inhalt erstellen
                    const newBody = `<body>${bodyContent}</body>`;
                    
                    return beforeBody + newBody + afterBody;
                }
            }
            
            // Fallback: Standard HTML-Struktur
            return `<!DOCTYPE html>
<html>
<head>
    <title>E-Mail Template</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
${bodyContent}
</body>
</html>`;
        };

        window.saveEditor = function(editorId) {
            const textarea = document.getElementById(editorId + "_textarea");
            const preview = document.getElementById("ace-editor_preview");
            
            // Inhalt aus dem aktiven Modus holen
            let content = '';
            if (textarea && textarea.style.display !== 'none') {
                content = textarea.value;
            } else if (preview && preview.style.display !== 'none') {
                // Vollständige HTML-Struktur wiederherstellen
                content = reconstructFullHtml(preview.innerHTML);
            } else if (textarea) {
                content = textarea.value;
            } else if (preview) {
                content = reconstructFullHtml(preview.innerHTML);
            }
            
            if (content) {
                // Inhalt in das versteckte Input-Feld übertragen
                const hiddenInput = document.getElementById('template-content');
                if (hiddenInput) {
                    hiddenInput.value = content;
                }
                
                console.log("Speichere Inhalt:", content);
                
                // Template-ID und Content-Type ermitteln
                const templateId = document.getElementById('template-id').value;
                const contentType = document.querySelector('input[name="template_type"]:checked').value;
                
                if (templateId) {
                    // Über AJAX speichern
                    fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=email_templates&subaction=save_editor&template_id=${templateId}&content=${encodeURIComponent(content)}&content_type=${contentType}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert("Template erfolgreich gespeichert!");
                        } else {
                            alert("Fehler beim Speichern: " + (data.message || "Unbekannter Fehler"));
                        }
                    })
                    .catch(error => {
                        console.error("Fehler beim Speichern:", error);
                        alert("Fehler beim Speichern des Templates");
                    });
                } else {
                    alert("Bitte speichern Sie das Template über das Formular");
                }
            }
        };
        
        window.formatCode = function(editorId) {
            const textarea = document.getElementById(editorId + "_textarea");
            if (textarea) {
                const content = textarea.value;
                // Einfache HTML-Formatierung
                const formatted = content
                    .replace(/></g, ">\\n<")
                    .replace(/^\\s+|\\s+$/g, "");
                
                textarea.value = formatted;
            }
        };
    </script>
</body>
</html>

<?php
/**
 * Funktionen für die Kundenaktivierung
 */

/**
 * Kunden aktivieren und in allen Systemen anlegen
 */
function handleActivateCustomer($serviceManager, $db) {
    try {
        if (!isset($_POST['customer_id'])) {
            throw new Exception('Keine Kunden-ID angegeben');
        }
        
        $customerId = intval($_POST['customer_id']);
        
        // Kundendaten abrufen
        $stmt = $db->prepare("SELECT * FROM customers WHERE id = ? AND status = 'pending'");
        $stmt->execute([$customerId]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$customer) {
            throw new Exception('Kunde nicht gefunden oder bereits aktiviert');
        }
        
        // Für jedes externe System ein eigenes Passwort generieren
        $systemPasswords = [];
        
        if (Config::ISPCONFIG_USEING) {
            $systemPasswords['ispconfig'] = bin2hex(random_bytes(8)); // 16 Zeichen
        }
        if (Config::OGP_USEING) {
            $systemPasswords['ogp'] = bin2hex(random_bytes(8)); // 16 Zeichen
        }
        if (Config::PROXMOX_USEING) {
            $systemPasswords['proxmox'] = bin2hex(random_bytes(8)); // 16 Zeichen
        }
        
        // Benutzername aus E-Mail generieren
        $username = strtolower(explode('@', $customer['email'])[0]);
        
        // Benutzer in allen Systemen erstellen
        $creationResult = $serviceManager->createUserInAllSystems(
            $username,
            $systemPasswords,
            $customer['first_name'],
            $customer['last_name'],
            [
                'email' => $customer['email'],
                'company' => $customer['company'] ?? '',
                'phone' => $customer['phone'] ?? ''
            ]
        );
        
        if ($creationResult['success']) {
            // Kundenstatus auf aktiv setzen
            $stmt = $db->prepare("UPDATE customers SET status = 'active', activated_at = NOW(), updated_at = NOW() WHERE id = ?");
            $stmt->execute([$customerId]);
            
            // E-Mail mit System-Anmeldedaten senden
            $emailSent = sendSystemCredentialsEmail(
                $customer['email'],
                $customer['first_name'],
                $username,
                $systemPasswords,
                $creationResult['results']
            );
            
            if ($emailSent) {
                $_SESSION['success_message'] = "Kunde erfolgreich aktiviert! Systemkonten wurden angelegt und eine E-Mail mit den Anmeldedaten wurde gesendet.";
            } else {
                $_SESSION['warning_message'] = "Kunde aktiviert, aber E-Mail konnte nicht gesendet werden. Bitte manuell versenden.";
            }
            
            // Erfolg loggen
            $db->logAction(
                'Customer Activation',
                "Kunde $customerId erfolgreich aktiviert und in allen Systemen angelegt",
                'success'
            );
            
        } else {
            throw new Exception('Fehler beim Anlegen der Systemkonten: ' . json_encode($creationResult['errors']));
        }
        
    } catch (Exception $e) {
        error_log("Customer Activation Error: " . $e->getMessage());
        $_SESSION['error_message'] = "Fehler bei der Kundenaktivierung: " . $e->getMessage();
        
        // Fehler loggen
        if (isset($db)) {
            $db->logAction(
                'Customer Activation Failed',
                "Fehler bei der Aktivierung von Kunde $customerId: " . $e->getMessage(),
                'error'
            );
        }
    }
    
    // Zurück zur Benutzerverwaltung
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

/**
 * E-Mail mit System-Anmeldedaten senden
 */
function sendSystemCredentialsEmail($email, $firstName, $username, $systemPasswords, $systemResults) {
    try {
        $to = $email;
        $subject = "Ihre System-Anmeldedaten - " . Config::FRONTPANEL_SITE_NAME;
        
        // Portal-Links aus der Config laden
        $portalLinks = [];
        if (Config::ISPCONFIG_USEING) {
            $portalLinks['ispconfig'] = Config::ISPCONFIG_HOST;
        }
        if (Config::OGP_USEING) {
            $portalLinks['ogp'] = Config::OGP_HOST;
        }
        if (Config::PROXMOX_USEING) {
            $portalLinks['proxmox'] = Config::PROXMOX_HOST;
        }
        
        $message = "
        <html>
        <head>
            <title>System-Anmeldedaten</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #007bff; border-bottom: 2px solid #007bff; padding-bottom: 10px;'>
                    Ihre System-Anmeldedaten
                </h2>
                
                <p>Hallo {$firstName},</p>
                
                <p>Ihr Konto wurde erfolgreich aktiviert! Ihre Benutzerkonten in den folgenden Systemen wurden erfolgreich angelegt:</p>
                
                <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #007bff;'>
                    <h3 style='margin-top: 0; color: #007bff;'>🎯 Frontpanel-Anmeldung</h3>
                    <p><strong>Portal:</strong> <a href='" . Config::FRONTPANEL_SITE_URL . "/public/login.php'>" . Config::FRONTPANEL_SITE_URL . "/public/login.php</a></p>
                    <p><strong>E-Mail:</strong> {$email}</p>
                    <p><strong>Passwort:</strong> <span style='background: #e9ecef; padding: 2px 6px; border-radius: 4px;'>Das Passwort, das Sie bei der Registrierung angegeben haben</span></p>
                </div>
                
                <h3 style='color: #28a745;'>🔐 Externe Systeme - Neue Anmeldedaten</h3>
                <p><strong>Wichtig:</strong> Für jedes externe System wurde ein eigenes Passwort generiert. Bitte ändern Sie diese Passwörter nach dem ersten Login aus Sicherheitsgründen!</p>
                
                <div style='margin: 20px 0;'>";
        
        // ISPConfig
        if (isset($systemPasswords['ispconfig']) && isset($portalLinks['ispconfig'])) {
            $message .= "
                    <div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin: 10px 0; border: 1px solid #ffeaa7;'>
                        <h4 style='margin-top: 0; color: #856404;'>🌐 ISPConfig - Webhosting-Verwaltung</h4>
                        <p><strong>Portal:</strong> <a href='{$portalLinks['ispconfig']}'>{$portalLinks['ispconfig']}</a></p>
                        <p><strong>Benutzername:</strong> <span style='background: #e9ecef; padding: 2px 6px; border-radius: 4px;'>{$username}</span></p>
                        <p><strong>Passwort:</strong> <span style='background: #e9ecef; padding: 2px 6px; border-radius: 4px;'>{$systemPasswords['ispconfig']}</span></p>
                    </div>";
        }
        
        // OpenGamePanel
        if (isset($systemPasswords['ogp']) && isset($portalLinks['ogp'])) {
            $message .= "
                    <div style='background: #d1ecf1; padding: 15px; border-radius: 8px; margin: 10px 0; border: 1px solid #bee5eb;'>
                        <h4 style='margin-top: 0; color: #0c5460;'>🎮 OpenGamePanel - Spieleserver-Verwaltung</h4>
                        <p><strong>Portal:</strong> <a href='{$portalLinks['ogp']}'>{$portalLinks['ogp']}</a></p>
                        <p><strong>Benutzername:</strong> <span style='background: #e9ecef; padding: 2px 6px; border-radius: 4px;'>{$username}</span></p>
                        <p><strong>Passwort:</strong> <span style='background: #e9ecef; padding: 2px 6px; border-radius: 4px;'>{$systemPasswords['ogp']}</span></p>
                    </div>";
        }
        
        // Proxmox
        if (isset($systemPasswords['proxmox']) && isset($portalLinks['proxmox'])) {
            $message .= "
                    <div style='background: #d4edda; padding: 15px; border-radius: 8px; margin: 10px 0; border: 1px solid #c3e6cb;'>
                        <h4 style='margin-top: 0; color: #155724;'>🖥️ Proxmox - Virtuelle Maschinen</h4>
                        <p><strong>Portal:</strong> <a href='{$portalLinks['proxmox']}'>{$portalLinks['proxmox']}</a></p>
                        <p><strong>Benutzername:</strong> <span style='background: #e9ecef; padding: 2px 6px; border-radius: 4px;'>{$username}</span></p>
                        <p><strong>Passwort:</strong> <span style='background: #e9ecef; padding: 2px 6px; border-radius: 4px;'>{$systemPasswords['proxmox']}</span></p>
                    </div>";
        }
        
        $message .= "
                </div>
                
                <div style='background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #ffeaa7;'>
                    <h3 style='margin-top: 0; color: #856404;'>⚠️ WICHTIGER SICHERHEITSHINWEIS</h3>
                    <p><strong>Bitte ändern Sie die Passwörter in den externen Systemen nach dem ersten Login!</strong></p>
                    <p>Die generierten Passwörter sind nur für den ersten Login gedacht. Aus Sicherheitsgründen sollten Sie diese sofort durch eigene, sichere Passwörter ersetzen.</p>
                    <ul>
                        <li>Verwenden Sie mindestens 12 Zeichen</li>
                        <li>Kombinieren Sie Groß- und Kleinbuchstaben, Zahlen und Sonderzeichen</li>
                        <li>Verwenden Sie für jedes System ein unterschiedliches Passwort</li>
                        <li>Speichern Sie die neuen Passwörter sicher ab</li>
                    </ul>
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='" . Config::FRONTPANEL_SITE_URL . "/public/login.php' 
                       style='background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold;'>
                        🚀 Jetzt im Frontpanel anmelden
                    </a>
                </div>
                
                <p>Falls der Button nicht funktioniert, kopieren Sie diesen Link in Ihren Browser:</p>
                <p style='word-break: break-all; color: #666; background: #f8f9fa; padding: 10px; border-radius: 4px;'>" . Config::FRONTPANEL_SITE_URL . "/public/login.php</p>
                
                <p>Falls Sie Fragen haben oder Probleme beim Login haben, kontaktieren Sie uns gerne unter <strong>" . Config::FRONTPANEL_SUPPORT_EMAIL . "</strong></p>
                
                <p>Mit freundlichen Grüßen<br>
                Ihr <strong>" . Config::FRONTPANEL_SITE_NAME . "</strong> Team</p>
            </div>
        </body>
        </html>
        ";
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: ' . Config::FRONTPANEL_SYSTEM_EMAIL,
            'Reply-To: ' . Config::FRONTPANEL_SUPPORT_EMAIL,
            'X-Mailer: PHP/' . phpversion()
        ];
        
        $mailResult = mail($to, $subject, $message, implode("\r\n", $headers));
        
        if ($mailResult) {
            error_log("System credentials email sent successfully to: " . $email);
        } else {
            error_log("Failed to send system credentials email to: " . $email);
        }
        
        return $mailResult;
        
    } catch (Exception $e) {
        error_log("Failed to send system credentials email: " . $e->getMessage());
        return false;
    }
}
?>