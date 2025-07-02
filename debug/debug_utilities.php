<?php
/**
 * Log Viewer und Debug Utilities
 * Hilfstools für das Debug-System
 */

header('Content-Type: text/plain');

$type = $_GET['type'] ?? 'error';

switch ($type) {
    case 'error':
        showErrorLog();
        break;
    case 'access':
        showAccessLog();
        break;
    case 'activity':
        showActivityLog();
        break;
    case 'clear':
        clearLogs();
        break;
    default:
        echo "Unknown log type: $type\n";
        echo "Available types: error, access, activity, clear\n";
}

function showErrorLog() {
    echo "=== PHP ERROR LOG ===\n";
    echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";
    
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
    
    echo "Log file: $error_log\n";
    
    if (!file_exists($error_log)) {
        echo "Error log file not found!\n";
        echo "Possible locations:\n";
        echo "- /var/log/apache2/error.log\n";
        echo "- /var/log/nginx/error.log\n";
        echo "- " . sys_get_temp_dir() . "/php_errors.log\n";
        return;
    }
    
    if (!is_readable($error_log)) {
        echo "Error log file not readable!\n";
        return;
    }
    
    echo "File size: " . formatBytes(filesize($error_log)) . "\n";
    echo str_repeat("-", 50) . "\n";
    
    // Show last 50 lines
    $lines = file($error_log);
    $recent_lines = array_slice($lines, -50);
    
    foreach ($recent_lines as $line) {
        echo $line;
    }
}

function showAccessLog() {
    echo "=== ACCESS LOG ===\n";
    echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";
    
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
        echo "No accessible access log found!\n";
        echo "Checked locations:\n";
        foreach ($access_logs as $log) {
            echo "- $log\n";
        }
        return;
    }
    
    echo "Log file: $log_file\n";
    echo "File size: " . formatBytes(filesize($log_file)) . "\n";
    echo str_repeat("-", 50) . "\n";
    
    // Show last 30 lines
    $lines = file($log_file);
    $recent_lines = array_slice($lines, -30);
    
    foreach ($recent_lines as $line) {
        echo $line;
    }
}

function showActivityLog() {
    echo "=== APPLICATION ACTIVITY LOG ===\n";
    echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";
    
    try {
        require_once '../framework.php';
        $db = Database::getInstance();
        $logs = $db->getActivityLog(50);
        
        if (empty($logs)) {
            echo "No activity logs found in database.\n";
            return;
        }
        
        echo "Total entries: " . count($logs) . "\n";
        echo str_repeat("-", 50) . "\n";
        
        foreach ($logs as $log) {
            $timestamp = $log['created_at'] ?? 'N/A';
            $action = $log['action'] ?? 'N/A';
            $status = $log['status'] ?? 'N/A';
            $details = $log['details'] ?? '';
            
            $status_icon = $status === 'success' ? '✅' : ($status === 'error' ? '❌' : '⚠️');
            
            echo "[$timestamp] $status_icon $action ($status)\n";
            if (!empty($details)) {
                echo "  Details: $details\n";
            }
            echo "\n";
        }
        
    } catch (Exception $e) {
        echo "Error loading activity log: " . $e->getMessage() . "\n";
    }
}

function clearLogs() {
    header('Content-Type: application/json');
    
    $cleared = [];
    $errors = [];
    
    // Clear PHP error log
    $error_log = ini_get('error_log');
    if ($error_log && file_exists($error_log) && is_writable($error_log)) {
        if (file_put_contents($error_log, '') !== false) {
            $cleared[] = 'PHP Error Log';
        } else {
            $errors[] = 'Failed to clear PHP Error Log';
        }
    }
    
    // Clear activity log in database
    try {
        require_once '../framework.php';
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
        if ($stmt->execute()) {
            $cleared[] = 'Database Activity Log (older than 1 day)';
        }
    } catch (Exception $e) {
        $errors[] = 'Database Activity Log: ' . $e->getMessage();
    }
    
    // Clear session files
    $session_path = session_save_path();
    if (empty($session_path)) {
        $session_path = sys_get_temp_dir();
    }
    
    $session_files = glob($session_path . '/sess_*');
    $cleared_sessions = 0;
    foreach ($session_files as $file) {
        if (unlink($file)) {
            $cleared_sessions++;
        }
    }
    
    if ($cleared_sessions > 0) {
        $cleared[] = "Session Files ($cleared_sessions files)";
    }
    
    echo json_encode([
        'success' => count($errors) === 0,
        'cleared' => $cleared,
        'errors' => $errors,
        'message' => count($errors) === 0 ? 'Logs successfully cleared' : 'Some logs could not be cleared'
    ]);
}

function formatBytes($size, $precision = 2) {
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

// Additional utility endpoints
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'phpinfo':
            header('Content-Type: text/html');
            phpinfo();
            break;
            
        case 'extensions':
            header('Content-Type: application/json');
            echo json_encode(get_loaded_extensions());
            break;
            
        case 'config':
            header('Content-Type: application/json');
            $config = [
                'php_version' => PHP_VERSION,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'session_save_path' => session_save_path(),
                'error_reporting' => error_reporting(),
                'display_errors' => ini_get('display_errors'),
                'log_errors' => ini_get('log_errors'),
                'error_log' => ini_get('error_log')
            ];
            echo json_encode($config, JSON_PRETTY_PRINT);
            break;
            
        case 'system_info':
            header('Content-Type: application/json');
            $info = [
                'os' => php_uname(),
                'php_sapi' => php_sapi_name(),
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
                'loaded_extensions' => get_loaded_extensions(),
                'included_files' => get_included_files(),
                'server_vars' => $_SERVER
            ];
            echo json_encode($info, JSON_PRETTY_PRINT);
            break;
            
        default:
            echo "Unknown action: " . $_GET['action'] . "\n";
    }
}
?>