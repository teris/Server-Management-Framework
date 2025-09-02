<?php
/**
 * Debug Tools Index - Zentrale Anlaufstelle für alle Debug-Funktionen
 * Integriert alle Debug-Tools in eine übersichtliche Oberfläche
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Einfache Checks
$framework_exists = file_exists('../../framework.php');
$auth_exists = file_exists('../auth_handler.php');
$main_index_exists = file_exists('../index.php');

if ($framework_exists) {
    require_once '../../framework.php';
}

if ($auth_exists) {
    require_once '../auth_handler.php';
}

// AJAX Handler für verschiedene Debug-Funktionen
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'system_status':
            echo json_encode(getSystemStatus());
            break;
            
        case 'test_endpoint':
            $endpoint = $_POST['endpoint'] ?? '';
            $method = $_POST['method'] ?? 'GET';
            $params = json_decode($_POST['params'] ?? '{}', true);
            echo json_encode(testEndpoint($endpoint, $method, $params));
            break;
            
        case 'database_test':
            echo json_encode(testDatabase());
            break;
            
        case 'soap_test':
            echo json_encode(testSOAP());
            break;
            
        case 'ispconfig_test':
            echo json_encode(testISPConfig());
            break;
            
        case 'clear_logs':
            echo json_encode(clearLogs());
            break;
            
        case 'quick_fix':
            echo json_encode(runQuickFix());
            break;
            
        case 'view_logs':
            $type = $_POST['type'] ?? 'error';
            echo json_encode(['content' => getLogContent($type)]);
            break;
            
        default:
            echo json_encode(['error' => 'Unbekannte Aktion']);
    }
    exit;
}

// System Status Funktion
function getSystemStatus() {
    $status = [
        'php_version' => PHP_VERSION,
        'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'extensions' => [],
        'database' => 'unknown',
        'session' => 'unknown',
        'framework' => 'unknown',
        'files' => []
    ];
    
    // PHP Extensions
    $required_extensions = ['curl', 'soap', 'pdo_mysql', 'json', 'session'];
    foreach ($required_extensions as $ext) {
        $status['extensions'][$ext] = extension_loaded($ext);
    }
    
    // Database Check
    if (class_exists('Database')) {
        try {
            $db = Database::getInstance();
            $status['database'] = 'connected';
        } catch (Exception $e) {
            $status['database'] = 'error: ' . $e->getMessage();
        }
    } else {
        $status['database'] = 'class not found';
    }
    
    // Session Check
    if (class_exists('SessionManager')) {
        try {
            SessionManager::startSession();
            $status['session'] = SessionManager::isLoggedIn() ? 'logged_in' : 'not_logged_in';
        } catch (Exception $e) {
            $status['session'] = 'error: ' . $e->getMessage();
        }
    } else {
        $status['session'] = 'not available';
    }
    
    // Framework Check
    if (class_exists('ServiceManager')) {
        $status['framework'] = 'loaded';
    } else {
        $status['framework'] = 'not loaded';
    }
    
    // File Checks
    $status['files'] = [
        'framework.php' => file_exists('../../framework.php'),
        'auth_handler.php' => file_exists('../auth_handler.php'),
        'index.php' => file_exists('../index.php'),
        'config.inc.php' => file_exists('../../config/config.inc.php')
    ];
    
    return $status;
}

// Endpoint Test Funktion
function testEndpoint($endpoint, $method = 'GET', $params = []) {
    try {
        switch ($endpoint) {
            case 'proxmox_nodes':
                if (class_exists('ProxmoxGet')) {
                    $proxmox = new ProxmoxGet();
                    return ['success' => true, 'data' => $proxmox->getNodes()];
                }
                return ['success' => false, 'error' => 'ProxmoxGet class not found'];
                
            case 'ispconfig_test':
                return testISPConfig();
                
            case 'database_test':
                return testDatabase();
                
            default:
                return ['success' => false, 'error' => 'Unknown endpoint'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Database Test Funktion
function testDatabase() {
    try {
        if (!class_exists('Database')) {
            return ['success' => false, 'error' => 'Database class not found'];
        }
        
        $db = Database::getInstance();
        $connection = $db->getConnection();
        
        // Test query
        $stmt = $connection->query("SELECT VERSION() as version");
        $version = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check tables
        $stmt = $connection->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        return [
            'success' => true,
            'version' => $version['version'],
            'tables' => $tables,
            'table_count' => count($tables)
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// SOAP Test Funktion
function testSOAP() {
    $results = [];
    
    // Check SOAP extension
    $results['soap_extension'] = extension_loaded('soap');
    
    // Check SOAP functions
    $results['soap_functions'] = function_exists('soap_create_client');
    
    // Test SOAP client creation
    if ($results['soap_extension']) {
        try {
            $client = new SoapClient(null, ['location' => 'http://localhost', 'uri' => 'http://localhost']);
            $results['soap_client'] = true;
        } catch (Exception $e) {
            $results['soap_client'] = false;
            $results['soap_error'] = $e->getMessage();
        }
    }
    
    return $results;
}

// ISPConfig Test Funktion
function testISPConfig() {
    try {
        // Prüfe ob die ISPConfig Klassen verfügbar sind
        if (!class_exists('ISPConfigGet')) {
            return ['success' => false, 'error' => 'ISPConfigGet class not found'];
        }
        
        if (!class_exists('ServiceManager')) {
            return ['success' => false, 'error' => 'ServiceManager class not found'];
        }
        
        // Teste eine einfache ISPConfig Funktion direkt
        try {
            $ispconfig = new ISPConfigGet();
            
            // Prüfe grundlegende Eigenschaften
            $result = [
                'class_loaded' => true,
                'client_available' => isset($ispconfig->client) && $ispconfig->client !== null,
                'session_available' => isset($ispconfig->session_id) && !empty($ispconfig->session_id)
            ];
            
            // Teste Verbindung durch Session-Check
            if ($result['session_available']) {
                $result['connection'] = 'active_session';
                $result['session_id'] = substr($ispconfig->session_id, 0, 10) . '...';
                
                // Teste eine einfache SOAP-Funktion wenn möglich
                if ($result['client_available']) {
                    try {
                        // Teste mit einer einfachen Funktion
                        $testResult = $ispconfig->client->get_function_list($ispconfig->session_id);
                        $result['soap_test'] = 'success';
                        $result['available_functions'] = is_array($testResult) ? count($testResult) : 'unknown';
                    } catch (Exception $e) {
                        $result['soap_test'] = 'failed';
                        $result['soap_error'] = $e->getMessage();
                    }
                }
            } else {
                $result['connection'] = 'no_session';
                $result['note'] = 'ISPConfig class loaded but no active session';
            }
            
            return ['success' => true, 'data' => $result];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'ISPConfig initialization failed: ' . $e->getMessage()];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Log Content Funktion
function getLogContent($type) {
    switch ($type) {
        case 'error':
            return showErrorLog();
        case 'access':
            return showAccessLog();
        case 'activity':
            return showActivityLog();
        default:
            return "Unbekannter Log-Typ: $type";
    }
}

function showErrorLog() {
    $error_log = ini_get('error_log');
    
    if (empty($error_log)) {
        $error_log = '/var/log/apache2/error.log';
        if (!file_exists($error_log)) {
            $error_log = '/var/log/nginx/error.log';
        }
        if (!file_exists($error_log)) {
            $error_log = sys_get_temp_dir() . '/php_errors.log';
        }
    }
    
    if (!file_exists($error_log)) {
        return "Error log file not found!\nPossible locations:\n- /var/log/apache2/error.log\n- /var/log/nginx/error.log\n- " . sys_get_temp_dir() . "/php_errors.log";
    }
    
    if (!is_readable($error_log)) {
        return "Error log file not readable!";
    }
    
    $content = "=== PHP ERROR LOG ===\n";
    $content .= "Log file: $error_log\n";
    $content .= "File size: " . formatBytes(filesize($error_log)) . "\n";
    $content .= str_repeat("-", 50) . "\n";
    
    $lines = file($error_log);
    $recent_lines = array_slice($lines, -50);
    
    foreach ($recent_lines as $line) {
        $content .= $line;
    }
    
    return $content;
}

function showAccessLog() {
    $access_logs = [
        '/var/log/apache2/access.log',
        '/var/log/nginx/access.log',
        '/var/log/httpd/access_log'
    ];
    
    $log_file = null;
    foreach ($access_logs as $log) {
        if (file_exists($log) && is_readable($log)) {
            $log_file = $log;
            break;
        }
    }
    
    if (!$log_file) {
        return "No accessible access log found!\nChecked locations:\n" . implode("\n", array_map(function($log) { return "- $log"; }, $access_logs));
    }
    
    $content = "=== ACCESS LOG ===\n";
    $content .= "Log file: $log_file\n";
    $content .= "File size: " . formatBytes(filesize($log_file)) . "\n";
    $content .= str_repeat("-", 50) . "\n";
    
    $lines = file($log_file);
    $recent_lines = array_slice($lines, -100);
    
    foreach ($recent_lines as $line) {
        $content .= $line;
    }
    
    return $content;
}

function showActivityLog() {
    try {
        if (!class_exists('Database')) {
            return "Database class not found!";
        }
        
        $db = Database::getInstance();
        $connection = $db->getConnection();
        
        $stmt = $connection->prepare("SELECT id, action, details, status, created_at FROM activity_log ORDER BY created_at DESC LIMIT 50");
        $stmt->execute();
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $content = "=== ACTIVITY LOG ===\n";
        $content .= "Total entries: " . count($logs) . "\n";
        $content .= str_repeat("-", 50) . "\n";
        
        foreach ($logs as $log) {
            $content .= sprintf("[%s] %s - %s (%s)\n", 
                $log['created_at'], 
                $log['action'], 
                $log['details'], 
                $log['status']
            );
        }
        
        return $content;
    } catch (Exception $e) {
        return "Error reading activity log: " . $e->getMessage();
    }
}

function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}

// Clear Logs Funktion
function clearLogs() {
    $cleared = [];
    $errors = [];

    try {
        // Clear PHP error log
        $error_log = ini_get('error_log');
        if (!empty($error_log) && file_exists($error_log)) {
            if (is_writable($error_log)) {
                if (file_put_contents($error_log, '') !== false) {
                    $cleared[] = 'PHP Error Log';
                } else {
                    $errors[] = 'Failed to clear PHP Error Log';
                }
            } else {
                $errors[] = 'PHP Error Log not writable';
            }
        }
        
        // Clear activity log in database
        try {
            if (class_exists('Database')) {
                $db = Database::getInstance()->getConnection();
                
                // Keep last 10 entries, delete the rest
                $stmt = $db->prepare("DELETE FROM activity_log WHERE id NOT IN (SELECT id FROM (SELECT id FROM activity_log ORDER BY created_at DESC LIMIT 10) as keeper)");
                
                if ($stmt->execute()) {
                    $affected = $stmt->rowCount();
                    $cleared[] = "Database Activity Log ($affected entries removed)";
                }
            }
        } catch (Exception $e) {
            $errors[] = 'Database Activity Log: ' . $e->getMessage();
        }
        
        // Clear old session files
        $session_path = session_save_path();
        if (empty($session_path)) {
            $session_path = sys_get_temp_dir();
        }
        
        $session_files = glob($session_path . '/sess_*');
        $cleared_sessions = 0;
        $session_errors = 0;
        
        foreach ($session_files as $file) {
            // Only delete old session files (older than 1 hour)
            if (file_exists($file) && (time() - filemtime($file)) > 3600) {
                if (unlink($file)) {
                    $cleared_sessions++;
                } else {
                    $session_errors++;
                }
            }
        }
        
        if ($cleared_sessions > 0) {
            $cleared[] = "Old Session Files ($cleared_sessions files)";
        }
        
        if ($session_errors > 0) {
            $errors[] = "Could not delete $session_errors session files";
        }
        
        // Clear debug log files
        $debug_files = [
            __DIR__ . '/debug.log',
            __DIR__ . '/ajax_debug.log',
            __DIR__ . '/test.log'
        ];
        
        $cleared_debug = 0;
        foreach ($debug_files as $file) {
            if (file_exists($file) && is_writable($file)) {
                if (file_put_contents($file, '') !== false) {
                    $cleared_debug++;
                }
            }
        }
        
        if ($cleared_debug > 0) {
            $cleared[] = "Debug Log Files ($cleared_debug files)";
        }
        
        // Clear temp files
        $temp_path = sys_get_temp_dir();
        $temp_files = glob($temp_path . '/server_mgmt_*');
        $cleared_temp = 0;
        
        foreach ($temp_files as $file) {
            if (file_exists($file) && is_writable($file)) {
                if (unlink($file)) {
                    $cleared_temp++;
                }
            }
        }
        
        if ($cleared_temp > 0) {
            $cleared[] = "Temp Files ($cleared_temp files)";
        }
        
    } catch (Exception $e) {
        $errors[] = 'General error: ' . $e->getMessage();
    }
    
    return [
        'success' => count($errors) === 0,
        'cleared' => $cleared,
        'errors' => $errors,
        'message' => count($cleared) . ' items cleared, ' . count($errors) . ' errors'
    ];
}

// Quick Fix Funktion
function runQuickFix() {
    $fixes = [];
    $errors = [];
    
    // Check and fix session directory
    $session_dir = session_save_path();
    if (!is_dir($session_dir)) {
        if (mkdir($session_dir, 0755, true)) {
            $fixes[] = "Session-Verzeichnis erstellt: $session_dir";
        } else {
            $errors[] = "Konnte Session-Verzeichnis nicht erstellen: $session_dir";
        }
    }
    
    // Check and fix log directory
    $log_dir = '../logs';
    if (!is_dir($log_dir)) {
        if (mkdir($log_dir, 0755, true)) {
            $fixes[] = "Log-Verzeichnis erstellt: $log_dir";
        } else {
            $errors[] = "Konnte Log-Verzeichnis nicht erstellen: $log_dir";
        }
    }
    
    // Check database connection and repair if needed
    try {
        if (class_exists('Database')) {
            $db = Database::getInstance();
            $connection = $db->getConnection();
            
            // Test basic query
            $stmt = $connection->query("SELECT 1");
            $fixes[] = "Datenbank-Verbindung erfolgreich";
            
            // Check and repair activity_log table
            try {
                $stmt = $connection->query("SELECT COUNT(*) FROM activity_log");
                $fixes[] = "Activity Log Tabelle OK";
            } catch (Exception $e) {
                // Try to repair activity_log table
                try {
                    $connection->exec("CREATE TABLE IF NOT EXISTS activity_log (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        action VARCHAR(255) NOT NULL,
                        details TEXT,
                        status VARCHAR(50) DEFAULT 'success',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )");
                    $fixes[] = "Activity Log Tabelle erstellt/repariert";
                } catch (Exception $e2) {
                    $errors[] = "Konnte Activity Log Tabelle nicht reparieren: " . $e2->getMessage();
                }
            }
            
            // Check and repair users table
            try {
                $stmt = $connection->query("SELECT COUNT(*) FROM users");
                $fixes[] = "Users Tabelle OK";
            } catch (Exception $e) {
                try {
                    $connection->exec("CREATE TABLE IF NOT EXISTS users (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        username VARCHAR(50) UNIQUE NOT NULL,
                        email VARCHAR(100) UNIQUE NOT NULL,
                        password_hash VARCHAR(255) NOT NULL,
                        role ENUM('admin', 'user') DEFAULT 'user',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        last_login TIMESTAMP NULL
                    )");
                    $fixes[] = "Users Tabelle erstellt/repariert";
                } catch (Exception $e2) {
                    $errors[] = "Konnte Users Tabelle nicht reparieren: " . $e2->getMessage();
                }
            }
            
        } else {
            $errors[] = "Database-Klasse nicht gefunden";
        }
    } catch (Exception $e) {
        $errors[] = "Datenbank-Fehler: " . $e->getMessage();
    }
    
    // Check PHP extensions
    $required_extensions = ['curl', 'soap', 'pdo_mysql', 'json', 'session'];
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $errors[] = "PHP Extension fehlt: $ext";
        } else {
            $fixes[] = "PHP Extension OK: $ext";
        }
    }
    
    // Check file permissions
    $files_to_check = [
        '../../config/config.inc.php' => 'Config-Datei',
        '../../framework.php' => 'Framework-Datei',
        '../auth_handler.php' => 'Auth-Handler'
    ];
    
    foreach ($files_to_check as $file => $description) {
        if (file_exists($file)) {
            if (is_readable($file)) {
                $fixes[] = "$description lesbar";
            } else {
                $errors[] = "$description nicht lesbar";
            }
        } else {
            $errors[] = "$description nicht gefunden";
        }
    }
    
    return [
        'success' => count($errors) === 0,
        'fixes' => $fixes,
        'errors' => $errors,
        'message' => count($fixes) . ' Fixes angewendet, ' . count($errors) . ' Fehler gefunden'
    ];
}

$system_status = getSystemStatus();

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔧 Debug Tools - Server Management Framework</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e1e2e 0%, #2d2d44 100%);
            color: #fff;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            backdrop-filter: blur(10px);
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4, #45b7d1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .status-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .status-card h3 {
            color: #4ecdc4;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }
        
        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 8px 0;
            padding: 5px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .status-ok { color: #51cf66; }
        .status-error { color: #ff6b6b; }
        .status-warning { color: #ffd43b; }
        
        .tools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .tool-category {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 25px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .tool-category:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .tool-category h3 {
            color: #ff6b6b;
            margin-bottom: 15px;
            font-size: 1.2rem;
            border-bottom: 2px solid #ff6b6b;
            padding-bottom: 10px;
        }
        
        .tool-button {
            display: block;
            width: 100%;
            background: rgba(78, 205, 196, 0.1);
            color: #4ecdc4;
            text-decoration: none;
            padding: 12px 15px;
            margin: 8px 0;
            border-radius: 8px;
            transition: all 0.3s ease;
            border: 1px solid rgba(78, 205, 196, 0.3);
            cursor: pointer;
            text-align: left;
        }
        
        .tool-button:hover {
            background: rgba(78, 205, 196, 0.2);
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(78, 205, 196, 0.3);
        }
        
        .tool-description {
            font-size: 0.9rem;
            color: #bbb;
            margin-top: 5px;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .quick-btn {
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4);
            color: white;
            text-decoration: none;
            padding: 15px 20px;
            border-radius: 10px;
            text-align: center;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .quick-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }
        
        .info-section {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 25px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin-top: 30px;
        }
        
        .info-section h3 {
            color: #45b7d1;
            margin-bottom: 15px;
        }
        
        .log-viewer {
            background: #1a1a1a;
            border-radius: 8px;
            padding: 15px;
            color: #0f0;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 300px;
            overflow-y: auto;
            white-space: pre-wrap;
            margin-top: 15px;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
        }
        
        .modal-content {
            background: #2d2d2d;
            margin: 5% auto;
            padding: 20px;
            border-radius: 15px;
            width: 80%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #fff;
        }
        
        .test-result {
            background: #1a1a1a;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            border-left: 4px solid #4ecdc4;
        }
        
        .test-result.error {
            border-left-color: #ff6b6b;
        }
        
        .test-result.success {
            border-left-color: #51cf66;
        }
        
        @media (max-width: 768px) {
            .tools-grid {
                grid-template-columns: 1fr;
            }
            .status-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 Debug Tools</h1>
            <p>Server Management Framework - Zentrale Debug-Anlaufstelle</p>
        </div>
        
        <!-- System Status -->
        <div class="status-grid">
            <div class="status-card">
                <h3>🖥️ System Status</h3>
                <div class="status-item">
                    <span>PHP Version:</span>
                    <span class="<?= version_compare(PHP_VERSION, '7.4', '>=') ? 'status-ok' : 'status-error' ?>">
                        <?= $system_status['php_version'] ?>
                    </span>
                </div>
                <div class="status-item">
                    <span>Server:</span>
                    <span class="status-ok"><?= htmlspecialchars($system_status['server']) ?></span>
                </div>
                <div class="status-item">
                    <span>Database:</span>
                    <span class="<?= strpos($system_status['database'], 'connected') !== false ? 'status-ok' : 'status-error' ?>">
                        <?= $system_status['database'] ?>
                    </span>
                </div>
                <div class="status-item">
                    <span>Session:</span>
                    <span class="<?= strpos($system_status['session'], 'error') === false ? 'status-ok' : 'status-error' ?>">
                        <?= $system_status['session'] ?>
                    </span>
                </div>
            </div>
            
            <div class="status-card">
                <h3>🔌 PHP Extensions</h3>
                <?php foreach ($system_status['extensions'] as $ext => $loaded): ?>
                <div class="status-item">
                    <span><?= $ext ?>:</span>
                    <span class="<?= $loaded ? 'status-ok' : 'status-error' ?>">
                        <?= $loaded ? '✅ Loaded' : '❌ Missing' ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="status-card">
                <h3>📁 File Status</h3>
                <?php foreach ($system_status['files'] as $file => $exists): ?>
                <div class="status-item">
                    <span><?= $file ?>:</span>
                    <span class="<?= $exists ? 'status-ok' : 'status-error' ?>">
                        <?= $exists ? '✅ Found' : '❌ Missing' ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="../index.php" class="quick-btn">🏠 Zur Hauptseite</a>
            <a href="../login.php" class="quick-btn">🔐 Login-Seite</a>
            <a href="../setup.php" class="quick-btn">⚙️ Setup</a>
            <button onclick="runQuickFix()" class="quick-btn">🔧 Auto-Fix</button>
            <button onclick="testAll()" class="quick-btn">🧪 Volltest</button>
            <button onclick="clearLogs()" class="quick-btn">🗑️ Logs löschen</button>
        </div>
        
        <!-- Debug Tools -->
        <div class="tools-grid">
            <div class="tool-category">
                <h3>🔍 System Debug</h3>
                <button class="tool-button" onclick="showSystemInfo()">
                    🐛 System-Informationen
                    <div class="tool-description">Detaillierte PHP- und Server-Informationen</div>
                </button>
                <button class="tool-button" onclick="showSessionInfo()">
                    🔑 Session-Informationen
                    <div class="tool-description">Session-Status und Benutzer-Daten</div>
                </button>
                <button class="tool-button" onclick="testDatabase()">
                    🗄️ Datenbank-Test
                    <div class="tool-description">Datenbank-Verbindung und Tabellen prüfen</div>
                </button>
                <button class="tool-button" onclick="showLogs('error')">
                    📝 Error Logs
                    <div class="tool-description">PHP Error Log anzeigen</div>
                </button>
            </div>
            
            <div class="tool-category">
                <h3>🌐 API Debug</h3>
                <button class="tool-button" onclick="testSOAP()">
                    🧼 SOAP Test
                    <div class="tool-description">SOAP-Extension und ISPConfig-Verbindung</div>
                </button>
                <button class="tool-button" onclick="testISPConfig()">
                    🌐 ISPConfig API
                    <div class="tool-description">ISPConfig-API-Verbindung testen</div>
                </button>
                <button class="tool-button" onclick="testProxmox()">
                    🖥️ Proxmox API
                    <div class="tool-description">Proxmox-API-Verbindung testen</div>
                </button>
                <button class="tool-button" onclick="testOVH()">
                    ☁️ OVH API
                    <div class="tool-description">OVH-API-Verbindung testen</div>
                </button>
            </div>
            
            <div class="tool-category">
                <h3>🔧 Endpoint Tests</h3>
                <button class="tool-button" onclick="testEndpoint('proxmox_nodes')">
                    🖥️ Proxmox Nodes
                    <div class="tool-description">Proxmox-Knoten abrufen</div>
                </button>
                <button class="tool-button" onclick="testEndpoint('proxmox_vms')">
                    💻 Proxmox VMs
                    <div class="tool-description">Virtuelle Maschinen auflisten</div>
                </button>
                <button class="tool-button" onclick="testEndpoint('ispconfig_sites')">
                    🌐 ISPConfig Sites
                    <div class="tool-description">ISPConfig-Websites abrufen</div>
                </button>
                <button class="tool-button" onclick="testEndpoint('ovh_servers')">
                    ☁️ OVH Server
                    <div class="tool-description">OVH-Server auflisten</div>
                </button>
            </div>
            
            <div class="tool-category">
                <h3>📊 Monitoring</h3>
                <button class="tool-button" onclick="showLogs('access')">
                    📈 Access Logs
                    <div class="tool-description">Apache/Nginx Access Log</div>
                </button>
                <button class="tool-button" onclick="showLogs('activity')">
                    🎯 Activity Log
                    <div class="tool-description">Anwendungs-Activity Log</div>
                </button>
                <button class="tool-button" onclick="showPerformance()">
                    ⚡ Performance
                    <div class="tool-description">System-Performance-Metriken</div>
                </button>
                <button class="tool-button" onclick="showMemoryUsage()">
                    💾 Speicher-Nutzung
                    <div class="tool-description">PHP-Speicher und Cache-Status</div>
                </button>
            </div>
        </div>
        
        <!-- Information Section -->
        <div class="info-section">
            <h3>📋 Debug-Informationen</h3>
            <p><strong>Zweck:</strong> Diese Debug-Tools helfen bei der Entwicklung und Fehleranalyse des Server Management Frameworks.</p>
            <p><strong>Sicherheit:</strong> Diese Tools sollten in Produktionsumgebungen NICHT zugänglich sein!</p>
            <p><strong>Aktualisierung:</strong> <?= date('d.m.Y H:i:s') ?></p>
            
            <h4 style="margin-top: 20px; color: #ffd43b;">⚠️ Häufige Probleme:</h4>
            <ul style="margin: 10px 0 0 20px; line-height: 1.6;">
                <li><strong>SOAP Extension fehlt:</strong> <code>sudo apt-get install php-soap</code></li>
                <li><strong>Datenbank-Verbindung:</strong> framework.php Konfiguration prüfen</li>
                <li><strong>Session-Probleme:</strong> PHP Session-Konfiguration und Verzeichnis-Rechte</li>
                <li><strong>ISPConfig API:</strong> Remote API aktivieren und Benutzer-Berechtigung prüfen</li>
                <li><strong>AJAX-Fehler:</strong> Browser-Entwicklertools → Network Tab für Details</li>
            </ul>
        </div>
    </div>

    <!-- Modal für Ergebnisse -->
    <div id="resultModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Ergebnis</h2>
            <div id="modalContent"></div>
        </div>
    </div>

    <script>
        // Modal Funktionen
        function showModal(title, content) {
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalContent').innerHTML = content;
            document.getElementById('resultModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('resultModal').style.display = 'none';
        }
        
        // AJAX Helper
        async function makeRequest(action, data = {}) {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: action,
                        ...data
                    })
                });
                
                return await response.json();
            } catch (error) {
                return { success: false, error: error.message };
            }
        }
        
        // System Info
        async function showSystemInfo() {
            const result = await makeRequest('system_status');
            let content = '<div class="test-result success">';
            content += '<h3>System-Informationen</h3>';
            content += '<pre>' + JSON.stringify(result, null, 2) + '</pre>';
            content += '</div>';
            showModal('System-Informationen', content);
        }
        
        // Session Info
        async function showSessionInfo() {
            let content = '<div class="test-result">';
            content += '<h3>Session-Informationen</h3>';
            content += '<pre>';
            content += 'Session Status: ' + (<?= session_status() === PHP_SESSION_ACTIVE ? 'true' : 'false' ?>) + '\n';
            content += 'Session ID: ' + '<?= session_id() ?>' + '\n';
            content += 'Session Name: ' + '<?= session_name() ?>' + '\n';
            content += '</pre>';
            content += '</div>';
            showModal('Session-Informationen', content);
        }
        
        // Database Test
        async function testDatabase() {
            const result = await makeRequest('database_test');
            let content = '<div class="test-result ' + (result.success ? 'success' : 'error') + '">';
            content += '<h3>Datenbank-Test</h3>';
            content += '<pre>' + JSON.stringify(result, null, 2) + '</pre>';
            content += '</div>';
            showModal('Datenbank-Test', content);
        }
        
        // SOAP Test
        async function testSOAP() {
            const result = await makeRequest('soap_test');
            let content = '<div class="test-result">';
            content += '<h3>SOAP Test</h3>';
            content += '<pre>' + JSON.stringify(result, null, 2) + '</pre>';
            content += '</div>';
            showModal('SOAP Test', content);
        }
        
        // ISPConfig Test
        async function testISPConfig() {
            const result = await makeRequest('ispconfig_test');
            let content = '<div class="test-result ' + (result.success ? 'success' : 'error') + '">';
            content += '<h3>ISPConfig API Test</h3>';
            content += '<pre>' + JSON.stringify(result, null, 2) + '</pre>';
            content += '</div>';
            showModal('ISPConfig API Test', content);
        }
        
        // Proxmox Test
        async function testProxmox() {
            const result = await makeRequest('test_endpoint', { endpoint: 'proxmox_nodes' });
            let content = '<div class="test-result ' + (result.success ? 'success' : 'error') + '">';
            content += '<h3>Proxmox API Test</h3>';
            content += '<pre>' + JSON.stringify(result, null, 2) + '</pre>';
            content += '</div>';
            showModal('Proxmox API Test', content);
        }
        
        // OVH Test
        async function testOVH() {
            let content = '<div class="test-result warning">';
            content += '<h3>OVH API Test</h3>';
            content += '<p>OVH API Test noch nicht implementiert</p>';
            content += '</div>';
            showModal('OVH API Test', content);
        }
        
        // Endpoint Tests
        async function testEndpoint(endpoint) {
            const result = await makeRequest('test_endpoint', { endpoint: endpoint });
            let content = '<div class="test-result ' + (result.success ? 'success' : 'error') + '">';
            content += '<h3>Endpoint Test: ' + endpoint + '</h3>';
            content += '<pre>' + JSON.stringify(result, null, 2) + '</pre>';
            content += '</div>';
            showModal('Endpoint Test: ' + endpoint, content);
        }
        
        // Logs anzeigen
        async function showLogs(type) {
            const result = await makeRequest('view_logs', { type: type });
            let content = '<div class="test-result">';
            content += '<h3>Logs: ' + type + '</h3>';
            content += '<div class="log-viewer">' + (result.content || 'Keine Logs verfügbar') + '</div>';
            content += '</div>';
            showModal('Logs: ' + type, content);
        }
        
        // Performance
        async function showPerformance() {
            let content = '<div class="test-result">';
            content += '<h3>Performance-Metriken</h3>';
            content += '<pre>';
            content += 'Memory Usage: ' + (memory_get_usage(true) / 1024 / 1024).toFixed(2) + ' MB\n';
            content += 'Peak Memory: ' + (memory_get_peak_usage(true) / 1024 / 1024).toFixed(2) + ' MB\n';
            content += 'Execution Time: ' + (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) + ' seconds\n';
            content += '</pre>';
            content += '</div>';
            showModal('Performance-Metriken', content);
        }
        
        // Memory Usage
        async function showMemoryUsage() {
            let content = '<div class="test-result">';
            content += '<h3>Speicher-Nutzung</h3>';
            content += '<pre>';
            content += 'Current Memory: ' + (memory_get_usage(true) / 1024 / 1024).toFixed(2) + ' MB\n';
            content += 'Peak Memory: ' + (memory_get_peak_usage(true) / 1024 / 1024).toFixed(2) + ' MB\n';
            content += 'Memory Limit: ' + ini_get('memory_limit') + '\n';
            content += 'Max Execution Time: ' + ini_get('max_execution_time') + ' seconds\n';
            content += '</pre>';
            content += '</div>';
            showModal('Speicher-Nutzung', content);
        }
        
        // Quick Fix
        async function runQuickFix() {
            if (!confirm('Auto-Fix ausführen? Dies kann System-Einstellungen ändern.')) {
                return;
            }
            
            const result = await makeRequest('quick_fix');
            let content = '<div class="test-result ' + (result.success ? 'success' : 'error') + '">';
            content += '<h3>Auto-Fix Ergebnis</h3>';
            content += '<pre>' + JSON.stringify(result, null, 2) + '</pre>';
            content += '</div>';
            showModal('Auto-Fix Ergebnis', content);
        }
        
        // Clear Logs
        async function clearLogs() {
            if (!confirm('Alle Logs löschen? Diese Aktion kann nicht rückgängig gemacht werden.')) {
                return;
            }
            
            const result = await makeRequest('clear_logs');
            let content = '<div class="test-result ' + (result.success ? 'success' : 'error') + '">';
            content += '<h3>Logs löschen</h3>';
            content += '<pre>' + JSON.stringify(result, null, 2) + '</pre>';
            content += '</div>';
            showModal('Logs löschen', content);
        }
        
        // Volltest
        async function testAll() {
            if (!confirm('Volltest ausführen? Dies kann einige Minuten dauern.')) {
                return;
            }
            
            let content = '<div class="test-result">';
            content += '<h3>Volltest läuft...</h3>';
            content += '<div id="testProgress">';
            
            const tests = [
                { name: 'System Status', func: () => makeRequest('system_status') },
                { name: 'Database Test', func: () => makeRequest('database_test') },
                { name: 'SOAP Test', func: () => makeRequest('soap_test') },
                { name: 'ISPConfig Test', func: () => makeRequest('ispconfig_test') }
            ];
            
            for (const test of tests) {
                content += '<p>Testing ' + test.name + '...</p>';
                try {
                    const result = await test.func();
                    content += '<p class="' + (result.success ? 'success' : 'error') + '">' + test.name + ': ' + (result.success ? 'OK' : 'FAILED') + '</p>';
                } catch (error) {
                    content += '<p class="error">' + test.name + ': ERROR - ' + error.message + '</p>';
                }
            }
            
            content += '<p><strong>Volltest abgeschlossen!</strong></p>';
            content += '</div></div>';
            
            showModal('Volltest Ergebnis', content);
        }
        
        // Modal schließen bei Klick außerhalb
        window.onclick = function(event) {
            const modal = document.getElementById('resultModal');
            if (event.target === modal) {
                closeModal();
            }
        }
        
        // Keyboard Shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                switch (e.key) {
                    case '1':
                        e.preventDefault();
                        showSystemInfo();
                        break;
                    case '2':
                        e.preventDefault();
                        testDatabase();
                        break;
                    case '3':
                        e.preventDefault();
                        testSOAP();
                        break;
                    case 'r':
                        e.preventDefault();
                        location.reload();
                        break;
                    case 'Escape':
                        e.preventDefault();
                        closeModal();
                        break;
                }
            }
        });
        
        console.log('🔧 Debug Tools loaded');
        console.log('Shortcuts: Ctrl+1 (System), Ctrl+2 (DB), Ctrl+3 (SOAP), Ctrl+R (Reload), Esc (Close Modal)');
    </script>
</body>
</html>