<?php
/**
 * Server Management Interface - Admin Dashboard Core
 * Version 3.0 mit integriertem Admin-Modul und Plugin-System
 */

require_once 'framework.php';
require_once 'auth_handler.php';
require_once 'sys.conf.php';

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
        if ($_POST['action'] === 'heartbeat') {
            SessionManager::updateActivity();
            echo json_encode(['success' => true]);
            exit;
        }
        
        // Core Admin Actions (direkt verarbeiten)
        if (isset($_POST['core']) && $_POST['core'] === 'admin') {
            try {
                require_once 'core/AdminHandler.php';
                $adminHandler = new AdminHandler();
                
                $result = $adminHandler->handleRequest($_POST['action'], $_POST);
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
        $action = $_POST['action'];
        
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
    $adminCore = new AdminCore();
    $dashboardStats = $adminCore->getDashboardStats();
} catch (Exception $e) {
    error_log('Error loading admin stats: ' . $e->getMessage());
    $dashboardStats = [];
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Server Management</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" type="text/css" href="assets/main.css">
    
    <!-- Plugin-spezifische Styles -->
    <?php foreach ($pluginManager->getAllStyles() as $style): ?>
    <link rel="stylesheet" type="text/css" href="<?= htmlspecialchars($style) ?>">
    <?php endforeach; ?>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <!-- User Info Header -->
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
                            <div class="badge bg-info" id="sessionTimer">
                                <i class="bi bi-clock"></i> <span id="timeRemaining">--:--</span>
                            </div>
                            <a href="password_change.php" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-key"></i> Passwort
                            </a>
                            <a href="?logout=1" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-box-arrow-right"></i> Abmelden
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h1 class="card-title">Admin Dashboard</h1>
                        <p class="card-text text-muted">Server Management System • <?= count($pluginManager->getEnabledPlugins()) ?> Plugins aktiv</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Haupt-Admin-Dashboard (immer sichtbar) -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h2 class="mb-0"><i class="bi bi-graph-up"></i> Übersicht</h2>
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
                            <h3><i class="bi bi-gear"></i> Verwaltung</h3>
                            <ul class="nav nav-tabs" id="adminTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#admin-overview" type="button" role="tab">
                                        <i class="bi bi-graph-up"></i> Übersicht
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="resources-tab" data-bs-toggle="tab" data-bs-target="#admin-resources" type="button" role="tab">
                                        <i class="bi bi-hdd-stack"></i> Ressourcen
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="plugins-tab" data-bs-toggle="tab" data-bs-target="#admin-plugins" type="button" role="tab">
                                        <i class="bi bi-puzzle"></i> Plugins
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#admin-logs" type="button" role="tab">
                                        <i class="bi bi-journal-text"></i> Logs
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#admin-settings" type="button" role="tab">
                                        <i class="bi bi-gear"></i> Einstellungen
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
                                        <h4>System-Übersicht</h4>
                                        <div class="list-group list-group-flush">
                                            <div class="list-group-item d-flex justify-content-between">
                                                <span><strong>PHP Version:</strong></span>
                                                <span><?= phpversion() ?></span>
                                            </div>
                                            <div class="list-group-item d-flex justify-content-between">
                                                <span><strong>Server:</strong></span>
                                                <span><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></span>
                                            </div>
                                            <div class="list-group-item d-flex justify-content-between">
                                                <span><strong>Aktive Sessions:</strong></span>
                                                <span id="active-sessions">-</span>
                                            </div>
                                            <div class="list-group-item d-flex justify-content-between">
                                                <span><strong>System-Auslastung:</strong></span>
                                                <span id="system-load">-</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h4>Schnellaktionen</h4>
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-primary" onclick="refreshAllStats()">
                                                <i class="bi bi-arrow-clockwise"></i> Alle Stats aktualisieren
                                            </button>
                                            <button class="btn btn-secondary" onclick="clearCache()">
                                                <i class="bi bi-trash"></i> Cache leeren
                                            </button>
                                            <button class="btn btn-warning" onclick="testAllConnections()">
                                                <i class="bi bi-plug"></i> Verbindungen testen
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Ressourcen-Verwaltung -->
                            <div class="tab-pane fade" id="admin-resources" role="tabpanel">
                                <ul class="nav nav-pills mb-3" id="resourceTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="vms-tab" data-bs-toggle="pill" data-bs-target="#resource-vms" type="button" role="tab">
                                            <i class="bi bi-display"></i> VMs
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="websites-tab" data-bs-toggle="pill" data-bs-target="#resource-websites" type="button" role="tab">
                                            <i class="bi bi-globe"></i> Websites
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="databases-tab" data-bs-toggle="pill" data-bs-target="#resource-databases" type="button" role="tab">
                                            <i class="bi bi-database"></i> Datenbanken
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="emails-tab" data-bs-toggle="pill" data-bs-target="#resource-emails" type="button" role="tab">
                                            <i class="bi bi-envelope"></i> E-Mails
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="domains-tab" data-bs-toggle="pill" data-bs-target="#resource-domains" type="button" role="tab">
                                            <i class="bi bi-link-45deg"></i> Domains
                                        </button>
                                    </li>
                                </ul>
                                
                                <div class="tab-content" id="resourceTabContent">
                                    <!-- VM Management -->
                                    <div class="tab-pane fade show active" id="resource-vms" role="tabpanel">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h4>Virtuelle Maschinen</h4>
                                            <button class="btn btn-primary btn-sm" onclick="loadVMData()">
                                                <i class="bi bi-arrow-clockwise"></i> Aktualisieren
                                            </button>
                                        </div>
                                        <div id="vm-content" class="table-responsive">
                                            <div class="text-center py-4">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="visually-hidden">Laden...</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Website Management -->
                                    <div class="tab-pane fade" id="resource-websites" role="tabpanel">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h4>Websites</h4>
                                            <button class="btn btn-primary btn-sm" onclick="loadWebsiteData()">
                                                <i class="bi bi-arrow-clockwise"></i> Aktualisieren
                                            </button>
                                        </div>
                                        <div id="website-content" class="table-responsive">
                                            <div class="text-center py-4">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="visually-hidden">Laden...</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Database Management -->
                                    <div class="tab-pane fade" id="resource-databases" role="tabpanel">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h4>Datenbanken</h4>
                                            <button class="btn btn-primary btn-sm" onclick="loadDatabaseData()">
                                                <i class="bi bi-arrow-clockwise"></i> Aktualisieren
                                            </button>
                                        </div>
                                        <div id="database-content" class="table-responsive">
                                            <div class="text-center py-4">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="visually-hidden">Laden...</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Email Management -->
                                    <div class="tab-pane fade" id="resource-emails" role="tabpanel">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h4>E-Mail-Konten</h4>
                                            <button class="btn btn-primary btn-sm" onclick="loadEmailData()">
                                                <i class="bi bi-arrow-clockwise"></i> Aktualisieren
                                            </button>
                                        </div>
                                        <div id="email-content" class="table-responsive">
                                            <div class="text-center py-4">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="visually-hidden">Laden...</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Domain Management -->
                                    <div class="tab-pane fade" id="resource-domains" role="tabpanel">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h4>Domains</h4>
                                            <button class="btn btn-primary btn-sm" onclick="loadDomainData()">
                                                <i class="bi bi-arrow-clockwise"></i> Aktualisieren
                                            </button>
                                        </div>
                                        <div id="domain-content" class="table-responsive">
                                            <div class="text-center py-4">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="visually-hidden">Laden...</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Plugin-Verwaltung -->
                            <div class="tab-pane fade" id="admin-plugins" role="tabpanel">
                                <h4>Verfügbare Plugins</h4>
                                <div class="row">
                                    <?php foreach ($pluginManager->getEnabledPlugins() as $plugin_key => $plugin_info): ?>
                                    <div class="col-md-6 col-lg-4 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h5 class="card-title"><?= htmlspecialchars($plugin_info['name'] ?? $plugin_key) ?></h5>
                                                <p class="card-text text-muted"><?= htmlspecialchars($plugin_info['description'] ?? 'Keine Beschreibung verfügbar') ?></p>
                                                <button class="btn btn-primary btn-sm" onclick="loadPluginContent('<?= $plugin_key ?>')">
                                                    <i class="bi bi-box-arrow-up-right"></i> Öffnen
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Logs -->
                            <div class="tab-pane fade" id="admin-logs" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h4>System-Logs</h4>
                                    <button class="btn btn-primary btn-sm" onclick="loadLogs()">
                                        <i class="bi bi-arrow-clockwise"></i> Aktualisieren
                                    </button>
                                </div>
                                <div id="logs-content">
                                    <div class="text-center py-4">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Laden...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Einstellungen -->
                            <div class="tab-pane fade" id="admin-settings" role="tabpanel">
                                <h4>System-Einstellungen</h4>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="mb-0">Allgemeine Einstellungen</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Session-Timeout (Minuten)</label>
                                                    <input type="number" class="form-control" id="session-timeout" value="30" min="5" max="480">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Auto-Refresh Intervall (Sekunden)</label>
                                                    <input type="number" class="form-control" id="refresh-interval" value="30" min="10" max="300">
                                                </div>
                                                <button class="btn btn-primary" onclick="saveSettings()">
                                                    <i class="bi bi-check"></i> Speichern
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header">
                                                <h5 class="mb-0">System-Status</h5>
                                            </div>
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <strong>Cache-Status:</strong>
                                                    <span class="badge bg-success ms-2">Aktiv</span>
                                                </div>
                                                <div class="mb-3">
                                                    <strong>API-Verbindungen:</strong>
                                                    <span class="badge bg-success ms-2">Alle OK</span>
                                                </div>
                                                <div class="mb-3">
                                                    <strong>Letzte Aktualisierung:</strong>
                                                    <span class="text-muted ms-2" id="last-update">-</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Plugin-Bereich -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="pluginTabs" role="tablist">
                            <?php 
                            $first = true;
                            foreach ($pluginManager->getEnabledPlugins() as $plugin_key => $plugin_info): 
                            ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= $first ? 'active' : '' ?>" 
                                        id="<?= $plugin_key ?>-tab" 
                                        data-bs-toggle="tab" 
                                        data-bs-target="#<?= $plugin_key ?>-content" 
                                        type="button" 
                                        role="tab"
                                        onclick="loadPluginContent('<?= $plugin_key ?>')">
                                    <?= htmlspecialchars($plugin_info['name'] ?? $plugin_key) ?>
                                </button>
                            </li>
                            <?php 
                            $first = false;
                            endforeach; 
                            ?>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="pluginTabContent">
                            <?php 
                            $first = true;
                            foreach ($pluginManager->getEnabledPlugins() as $plugin_key => $plugin_info): 
                            ?>
                            <div class="tab-pane fade <?= $first ? 'show active' : '' ?>" 
                                 id="<?= $plugin_key ?>-content" 
                                 role="tabpanel">
                                <div class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Laden...</span>
                                    </div>
                                </div>
                            </div>
                            <?php 
                            $first = false;
                            endforeach; 
                            ?>
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
                <strong class="me-auto" id="toastTitle">Benachrichtigung</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body" id="toastBody">
                Nachricht hier...
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/main.js"></script>
    
    <script>
        // Session-Informationen für JavaScript
        const sessionInfo = <?= json_encode($session_info ?? []) ?>;
        const enabledPlugins = <?= json_encode($pluginManager->getEnabledPlugins() ?? []) ?>;
        
        // ModuleManager für AJAX-Requests
        window.ModuleManager = {
            currentModule: 'admin',
            
            request: function(plugin, action, data = {}) {
                return $.ajax({
                    url: window.location.pathname,
                    method: 'POST',
                    data: {
                        plugin: plugin,
                        action: action,
                        ...data
                    },
                    dataType: 'json'
                });
            },
            
            makeRequest: async function(module, action, data = {}) {
                try {
                    const response = await $.ajax({
                        url: window.location.pathname,
                        method: 'POST',
                        data: {
                            plugin: module,
                            action: action,
                            ...data
                        },
                        dataType: 'json'
                    });
                    
                    // Session-Check
                    if (!response.success && response.redirect) {
                        showNotification('Session abgelaufen - Sie werden weitergeleitet', 'error');
                        setTimeout(() => {
                            window.location.href = response.redirect;
                        }, 2000);
                    }
                    
                    return response;
                } catch (error) {
                    console.error('ModuleManager.makeRequest error:', error);
                    throw error;
                }
            }
        };
        
        // Toast-Benachrichtigungen
        function showNotification(message, type = 'info') {
            const toast = document.getElementById('notificationToast');
            const toastTitle = document.getElementById('toastTitle');
            const toastBody = document.getElementById('toastBody');
            
            // Icon und Titel basierend auf Typ
            const icons = {
                'success': 'bi-check-circle-fill text-success',
                'error': 'bi-x-circle-fill text-danger',
                'warning': 'bi-exclamation-triangle-fill text-warning',
                'info': 'bi-info-circle-fill text-info'
            };
            
            toastTitle.innerHTML = `<i class="bi ${icons[type]}"></i> ${type.charAt(0).toUpperCase() + type.slice(1)}`;
            toastBody.textContent = message;
            
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
        }
        
        // Plugin-Inhalte laden
        function loadPluginContent(pluginKey) {
            const contentDiv = document.getElementById(pluginKey + '-content');
            if (!contentDiv) return;
            
            contentDiv.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Laden...</span></div></div>';
            
            ModuleManager.request(pluginKey, 'getContent')
                .done(function(response) {
                    if (response.success) {
                        contentDiv.innerHTML = response.content;
                    } else {
                        contentDiv.innerHTML = '<div class="alert alert-danger">Fehler beim Laden des Plugins: ' + (response.error || 'Unbekannter Fehler') + '</div>';
                    }
                })
                .fail(function(xhr, status, error) {
                    contentDiv.innerHTML = '<div class="alert alert-danger">Fehler beim Laden des Plugins: ' + error + '</div>';
                });
        }
        
        // Admin-Funktionen
        function refreshAllStats() {
            showNotification('Statistiken werden aktualisiert...', 'info');
            // Implementierung hier
        }
        
        function clearCache() {
            showNotification('Cache wird geleert...', 'info');
            // Implementierung hier
        }
        
        function testAllConnections() {
            showNotification('Verbindungen werden getestet...', 'info');
            // Implementierung hier
        }
        
        function loadVMData() {
            const contentDiv = document.getElementById('vm-content');
            contentDiv.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Laden...</span></div></div>';
            
            $.post(window.location.pathname, {action: 'get_vms', core: 'admin'})
                .done(function(response) {
                    if (response.success) {
                        renderVMTable(contentDiv, response.data);
                    } else {
                        contentDiv.innerHTML = '<div class="alert alert-danger">Fehler beim Laden der VMs: ' + (response.error || 'Unbekannter Fehler') + '</div>';
                    }
                })
                .fail(function(xhr, status, error) {
                    contentDiv.innerHTML = '<div class="alert alert-danger">Fehler beim Laden der VMs: ' + error + '</div>';
                });
        }
        
        function renderVMTable(container, vms) {
            if (!vms || vms.length === 0) {
                container.innerHTML = '<div class="alert alert-info">Keine VMs gefunden.</div>';
                return;
            }
            
            let html = '<table class="table table-striped table-hover">';
            html += '<thead><tr><th>Name</th><th>Status</th><th>CPU</th><th>RAM</th><th>Speicher</th><th>Aktionen</th></tr></thead><tbody>';
            
            vms.forEach(function(vm) {
                const statusClass = vm.status === 'running' ? 'success' : (vm.status === 'stopped' ? 'danger' : 'warning');
                html += '<tr>';
                html += '<td>' + vm.name + '</td>';
                html += '<td><span class="badge bg-' + statusClass + '">' + vm.status + '</span></td>';
                html += '<td>' + (vm.cpu || '-') + '</td>';
                html += '<td>' + (vm.ram || '-') + '</td>';
                html += '<td>' + (vm.storage || '-') + '</td>';
                html += '<td>';
                if (vm.status === 'running') {
                    html += '<button class="btn btn-warning btn-sm me-1" onclick="controlVM(\'' + vm.id + '\', \'stop\')"><i class="bi bi-pause"></i></button>';
                } else {
                    html += '<button class="btn btn-success btn-sm me-1" onclick="controlVM(\'' + vm.id + '\', \'start\')"><i class="bi bi-play"></i></button>';
                }
                html += '<button class="btn btn-danger btn-sm" onclick="controlVM(\'' + vm.id + '\', \'delete\')"><i class="bi bi-trash"></i></button>';
                html += '</td>';
                html += '</tr>';
            });
            
            html += '</tbody></table>';
            container.innerHTML = html;
        }
        
        function controlVM(vmId, action) {
            $.post(window.location.pathname, {action: 'control_vm', vm_id: vmId, control: action, core: 'admin'})
                .done(function(response) {
                    if (response.success) {
                        showNotification('VM ' + action + ' erfolgreich ausgeführt', 'success');
                        loadVMData();
                    } else {
                        showNotification('Fehler: ' + (response.error || 'Unbekannter Fehler'), 'error');
                    }
                })
                .fail(function(xhr, status, error) {
                    showNotification('Fehler: ' + error, 'error');
                });
        }
        
        // Ähnliche Funktionen für andere Ressourcen
        function loadWebsiteData() {
            // Implementierung ähnlich wie loadVMData
        }
        
        function loadDatabaseData() {
            // Implementierung ähnlich wie loadVMData
        }
        
        function loadEmailData() {
            // Implementierung ähnlich wie loadVMData
        }
        
        function loadDomainData() {
            // Implementierung ähnlich wie loadVMData
        }
        
        function loadLogs() {
            // Implementierung für Logs
        }
        
        function saveSettings() {
            // Implementierung für Einstellungen
            showNotification('Einstellungen gespeichert', 'success');
        }
        
        // Session-Timer
        function updateSessionTimer() {
            const timeRemaining = document.getElementById('timeRemaining');
            if (timeRemaining) {
                // Session-Timer-Logik hier
                timeRemaining.textContent = '29:45';
            }
        }
        
        // Heartbeat für Session
        function sendHeartbeat() {
            $.post(window.location.pathname, {action: 'heartbeat'})
                .done(function(response) {
                    if (!response.success && response.redirect) {
                        window.location.href = response.redirect;
                    }
                });
        }
        
        // Initialisierung
        $(document).ready(function() {
            // Erste Plugin-Inhalte laden
            const firstPlugin = Object.keys(enabledPlugins)[0];
            if (firstPlugin) {
                loadPluginContent(firstPlugin);
            }
            
            // Timer starten
            setInterval(updateSessionTimer, 1000);
            setInterval(sendHeartbeat, 30000);
            
            // Erste Ressourcen laden
            loadVMData();
        });
    </script>
</body>
</html>