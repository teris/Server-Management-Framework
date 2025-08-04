<?php
/**
 * Debug-Version der index.php
 * Nutzen Sie diese temporär um das Problem zu finden
 */

require_once '../framework.php';
require_once 'auth_handler.php';
require_once 'sys.conf.php';
require_once 'modules/ModuleBase.php';

// Login-Überprüfung
requireLogin();

// Debug-Ausgabe aktivieren
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Module Loader mit Debug initialisieren
try {
    $moduleLoader = ModuleLoader::getInstance();
    echo "<!-- ModuleLoader erfolgreich initialisiert -->\n";
} catch (Exception $e) {
    die("ModuleLoader Error: " . $e->getMessage());
}

// Debug: Zeige geladene Module
echo "<!-- Geladene Module: -->\n";
$allModules = $moduleLoader->getAllModules();
foreach ($allModules as $key => $module) {
    echo "<!-- - $key: " . get_class($module) . " -->\n";
}

// Handler für Logout
if (isset($_GET['logout'])) {
    SessionManager::logout();
    header('Location: login.php');
    exit;
}

// AJAX Handler
if (isset($_POST['action']) || (isset($_POST['module']) && isset($_POST['action']))) {
    header('Content-Type: application/json');
    
    // Debug-Ausgabe für AJAX
    error_log("AJAX Request - Module: " . ($_POST['module'] ?? 'none') . ", Action: " . $_POST['action']);
    
    if (!SessionManager::isLoggedIn()) {
        echo json_encode(['success' => false, 'redirect' => 'login.php']);
        exit;
    }
    
    if ($_POST['action'] === 'heartbeat' || isset($_GET['heartbeat'])) {
        SessionManager::updateActivity();
        echo json_encode(['success' => true]);
        exit;
    }
    
    // Legacy handler einbinden
    include("handler.php");
    exit;
}

$session_info = getSessionInfoForJS();

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Management Interface - Debug Mode</title>
    <link rel="stylesheet" type="text/css" href="assets/main.css">
    
    <style>
    /* Debug-Styles */
    .debug-info {
        background: #fef3c7;
        border: 1px solid #f59e0b;
        padding: 10px;
        margin: 10px 0;
        border-radius: 4px;
        font-family: monospace;
        font-size: 12px;
    }
    </style>
</head>
<body>
    <div class="container">
        <!-- Debug Info -->
        <div class="debug-info">
            <strong>DEBUG MODE AKTIV</strong><br>
            PHP Version: <?= phpversion() ?><br>
            Module geladen: <?= count($allModules) ?><br>
            User Role: <?= $session_info['user']['role'] ?><br>
            Session ID: <?= substr(session_id(), 0, 8) ?>...
        </div>
        
        <!-- User Info Header -->
        <div class="user-info">
            <div class="user-details">
                <div class="user-avatar">
                    <?= strtoupper(substr($session_info['user']['full_name'] ?? $session_info['user']['username'], 0, 1)) ?>
                </div>
                <div class="user-text">
                    <h3><?= htmlspecialchars($session_info['user']['full_name'] ?? $session_info['user']['username']) ?></h3>
                    <p><?= htmlspecialchars($session_info['user']['email']) ?> • <?= htmlspecialchars($session_info['user']['role']) ?></p>
                </div>
            </div>
            
            <div class="session-controls">
                <div class="session-timer" id="sessionTimer">
                    🕒 <span id="timeRemaining">--:--</span>
                </div>
                <a href="?logout=1" class="logout-btn">🚪 Abmelden</a>
            </div>
        </div>
        
        <div class="header">
            <h1>Server Management Interface - DEBUG</h1>
        </div>
        
        <!-- Tab Navigation mit Debug -->
        <div class="tabs">
            <?php
            $tabButtons = $moduleLoader->getTabButtons();
            if (empty($tabButtons)) {
                echo '<div class="debug-info">WARNUNG: Keine Tab-Buttons generiert!</div>';
            } else {
                echo $tabButtons;
            }
            ?>
        </div>
        
        <div class="content">
            <?php 
            try {
                $contents = $moduleLoader->getModuleContents();
                
                if (empty($contents)) {
                    echo '<div class="debug-info">WARNUNG: Keine Module-Contents geladen!</div>';
                }
                
                $first = true;
                foreach ($contents as $key => $content): 
            ?>
            <div id="<?= $key ?>" class="tab-content <?= !$first ? 'hidden' : '' ?>">
                <!-- Debug für jedes Modul -->
                <div class="debug-info">
                    Module: <?= $key ?><br>
                    Hidden: <?= !$first ? 'true' : 'false' ?><br>
                    Content Length: <?= strlen($content) ?> bytes
                </div>
                <?= $content ?>
            </div>
            <?php 
                $first = false;
                endforeach;
            } catch (Exception $e) {
                echo '<div class="debug-info">ERROR: ' . $e->getMessage() . '</div>';
            }
            ?>
        </div>
    </div>

    <script>
    // Debug-Konsole
    console.log('=== MODULE DEBUG ===');
    console.log('Enabled Modules:', <?= json_encode(array_keys($allModules)) ?>);
    
    // Tab-Funktionalität mit Debug
    function showTab(tabName, element) {
        console.log('showTab called:', tabName);
        
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.add('hidden');
        });
        
        document.querySelectorAll('.tab').forEach(tab => {
            tab.classList.remove('active');
        });
        
        const tabContent = document.getElementById(tabName);
        if (tabContent) {
            console.log('Tab content found:', tabName);
            tabContent.classList.remove('hidden');
            element.classList.add('active');
        } else {
            console.error('Tab content NOT found:', tabName);
            alert('Tab content not found: ' + tabName);
        }
    }
    
    // Session Timer Dummy
    function initSessionTimer() {
        console.log('Session timer initialized');
    }
    
    // Notification Dummy
    function showNotification(message, type) {
        console.log('Notification:', type, message);
        alert(type.toUpperCase() + ': ' + message);
    }
    
    // Loading State Dummy
    function setLoading(form, loading) {
        console.log('setLoading:', loading);
    }
    
    // Check if admin tab exists
    document.addEventListener('DOMContentLoaded', function() {
        const adminTab = document.getElementById('admin');
        if (adminTab) {
            console.log('Admin tab found in DOM');
            console.log('Admin tab classes:', adminTab.className);
            console.log('Admin tab display:', window.getComputedStyle(adminTab).display);
        } else {
            console.error('Admin tab NOT found in DOM!');
        }
    });
    </script>
</body>
</html>