<?php
/**
 * Log Cleaner - Löscht verschiedene Log-Dateien
 */

header('Content-Type: application/json');

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
        require_once '../framework.php';
        $db = Database::getInstance()->getConnection();
        
        // Keep last 10 entries, delete the rest
        $stmt = $db->prepare("DELETE FROM activity_log WHERE id NOT IN (SELECT id FROM (SELECT id FROM activity_log ORDER BY created_at DESC LIMIT 10) as keeper)");
        
        if ($stmt->execute()) {
            $affected = $stmt->rowCount();
            $cleared[] = "Database Activity Log ($affected entries removed)";
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
    $errors[] = 'Critical error: ' . $e->getMessage();
}

echo json_encode([
    'success' => count($errors) === 0,
    'cleared' => $cleared,
    'errors' => $errors,
    'message' => count($errors) === 0 ? 
        'Logs successfully cleared (' . count($cleared) . ' categories)' : 
        'Some logs could not be cleared (' . count($errors) . ' errors)',
    'timestamp' => date('Y-m-d H:i:s')
]);
?>