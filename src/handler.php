<?php
/**
 * Modularer Request Handler
 * Leitet AJAX-Requests an die entsprechenden Module weiter
 */

// Dieser Handler wird in der index.php eingebunden und verarbeitet AJAX-Requests

// Legacy Support für alte Actions ohne Module
$legacy_actions = [
    // Admin Module Actions
    'get_all_vms' => 'admin',
    'control_vm' => 'admin',
    'delete_vm' => 'admin',
    'get_all_websites' => 'admin',
    'delete_website' => 'admin',
    'get_all_databases' => 'admin',
    'delete_database' => 'admin',
    'get_all_emails' => 'admin',
    'delete_email' => 'admin',
    'get_all_domains' => 'admin',
    'get_all_vps' => 'admin',
    'get_all_virtual_macs' => 'admin',
    'get_activity_log' => 'admin',
    
    // Users Module Actions
    'get_user_data' => 'users',
    'get_user_details' => 'users',
    'get_user_system_links' => 'users',
    'get_customer_details' => 'users',
    'edit_system_user' => 'users',
    'delete_system_user' => 'users',
    'edit_customer' => 'users',
    'create_customer' => 'users',
    'get_customers' => 'users',
    'create_system_user' => 'users',
    'edit_user' => 'users',
    'delete_customer' => 'users',
    'delete_user' => 'users',
    
    // Proxmox Module Actions
    'create_vm' => 'proxmox',
    'get_proxmox_nodes' => 'proxmox',
    'get_proxmox_storages' => 'proxmox',
    'get_vm_config' => 'proxmox',
    'get_vm_status' => 'proxmox',
    'clone_vm' => 'proxmox',
    
    // ISPConfig Module Actions
    'create_website' => 'ispconfig',
    'get_ispconfig_clients' => 'ispconfig',
    'get_ispconfig_server_config' => 'ispconfig',
    
    // OVH Module Actions
    'order_domain' => 'ovh',
    'get_vps_info' => 'ovh',
    'get_ovh_domain_zone' => 'ovh',
    'get_ovh_dns_records' => 'ovh',
    'get_vps_ips' => 'ovh',
    'get_vps_ip_details' => 'ovh',
    'control_ovh_vps' => 'ovh',
    'create_dns_record' => 'ovh',
    'refresh_dns_zone' => 'ovh',
    
    // Virtual MAC Module Actions
    'create_virtual_mac' => 'virtual-mac',
    'get_virtual_mac_details' => 'virtual-mac',
    'assign_ip_to_virtual_mac' => 'virtual-mac',
    'remove_ip_from_virtual_mac' => 'virtual-mac',
    'create_reverse_dns' => 'virtual-mac',
    'query_reverse_dns' => 'virtual-mac',
    'get_dedicated_servers' => 'virtual-mac',
    'get_virtual_macs_for_service' => 'virtual-mac',
    
    // Network Module Actions
    'update_vm_network' => 'network',
    
    // Database Module Actions
    'create_database' => 'database',
    
    // Email Module Actions
    'create_email' => 'email'
];

// Module Manager Handler
if (isset($_POST['action']) && in_array($_POST['action'], ['install_module', 'update_module', 'enable_module', 'disable_module', 'uninstall_module', 'install_from_github', 'update_from_github', 'check_github_updates', 'get_github_catalog'])) {
    header('Content-Type: application/json');
    
    // Session-Check und Admin-Check
    if (!SessionManager::isLoggedIn() || !isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    require_once __DIR__ . '/core/ModuleManager.php';
    $moduleManager = new ModuleManager();
    
    try {
        $action = $_POST['action'];
        
        switch ($action) {
            case 'install_module':
                if (!isset($_FILES['module_zip'])) {
                    throw new Exception('Keine ZIP-Datei hochgeladen');
                }
                
                $tmp_path = $_FILES['module_zip']['tmp_name'];
                $module_key = $_POST['module_key'] ?? null;
                
                $result = $moduleManager->installModule($tmp_path, $module_key);
                echo json_encode([
                    'success' => true,
                    'message' => 'Modul erfolgreich installiert',
                    'data' => $result
                ]);
                break;
                
            case 'update_module':
                if (!isset($_FILES['module_zip']) || !isset($_POST['module_key'])) {
                    throw new Exception('Fehlende Parameter');
                }
                
                $tmp_path = $_FILES['module_zip']['tmp_name'];
                $module_key = $_POST['module_key'];
                
                $result = $moduleManager->updateModule($module_key, $tmp_path);
                echo json_encode([
                    'success' => true,
                    'message' => 'Modul erfolgreich aktualisiert',
                    'data' => $result
                ]);
                break;
                
            case 'enable_module':
                $input = json_decode(file_get_contents('php://input'), true);
                if (!$input || !isset($input['module_key'])) {
                    throw new Exception('Modul-Key fehlt');
                }
                
                $moduleManager->enableModule($input['module_key']);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Modul erfolgreich aktiviert'
                ]);
                break;
                
            case 'disable_module':
                $input = json_decode(file_get_contents('php://input'), true);
                if (!$input || !isset($input['module_key'])) {
                    throw new Exception('Modul-Key fehlt');
                }
                
                $moduleManager->disableModule($input['module_key']);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Modul erfolgreich deaktiviert'
                ]);
                break;
                
            case 'uninstall_module':
                $input = json_decode(file_get_contents('php://input'), true);
                if (!$input || !isset($input['module_key'])) {
                    throw new Exception('Modul-Key fehlt');
                }
                
                $moduleManager->uninstallModule($input['module_key']);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Modul erfolgreich deinstalliert'
                ]);
                break;
                
            case 'install_from_github':
                $input = json_decode(file_get_contents('php://input'), true);
                if (!$input || !isset($input['module_key'])) {
                    throw new Exception('Modul-Key fehlt');
                }
                
                $result = $moduleManager->installFromGitHub($input['module_key']);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Modul erfolgreich von GitHub installiert',
                    'data' => $result
                ]);
                break;
                
            case 'update_from_github':
                $input = json_decode(file_get_contents('php://input'), true);
                if (!$input || !isset($input['module_key'])) {
                    throw new Exception('Modul-Key fehlt');
                }
                
                $result = $moduleManager->updateFromGitHub($input['module_key']);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Modul erfolgreich von GitHub aktualisiert',
                    'data' => $result
                ]);
                break;
                
            case 'check_github_updates':
                try {
                    $modules = $moduleManager->getAllModules();
                    $updates = [];
                    
                    foreach ($modules as $key => $module) {
                        try {
                            $update_info = $moduleManager->checkGitHubUpdate($key);
                            if ($update_info && isset($update_info['update_available']) && $update_info['update_available']) {
                                $updates[$key] = $update_info;
                            }
                        } catch (Exception $e) {
                            error_log("check_github_updates: Error for module $key: " . $e->getMessage());
                            // Überspringe dieses Modul bei Fehler
                        }
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'updates' => $updates
                    ]);
                } catch (Exception $e) {
                    error_log('check_github_updates error: ' . $e->getMessage());
                    echo json_encode([
                        'success' => false,
                        'error' => 'Fehler beim Prüfen der Updates: ' . $e->getMessage()
                    ]);
                }
                break;
                
            case 'get_github_catalog':
                try {
                    $catalog = $moduleManager->getGitHubCatalog();
                    echo json_encode([
                        'success' => true,
                        'catalog' => $catalog
                    ]);
                } catch (Exception $e) {
                    error_log('get_github_catalog error: ' . $e->getMessage());
                    echo json_encode([
                        'success' => false,
                        'error' => 'Fehler beim Laden des Katalogs: ' . $e->getMessage()
                    ]);
                }
                break;
        }
    } catch (Exception $e) {
        error_log('Module Manager Handler Error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    
    exit;
}

// Legacy AJAX Handler (für Kompatibilität mit alter main.js)
if (isset($_POST['action']) && !isset($_POST['module'])) {
    header('Content-Type: application/json');
    
    // Session-Check
    if (!SessionManager::isLoggedIn()) {
        echo json_encode(['success' => false, 'redirect' => 'login.php']);
        exit;
    }
    
    // Heartbeat
    if ($_POST['action'] === 'heartbeat' || isset($_GET['heartbeat'])) {
        SessionManager::updateActivity();
        echo json_encode(['success' => true]);
        exit;
    }
    
    $action = $_POST['action'];
    
    // Legacy Action zu Module mapping
    if (isset($legacy_actions[$action])) {
        $module_key = $legacy_actions[$action];
        
        // Daten sammeln
        $data = $_POST;
        unset($data['action']);
        
        // An Module weiterleiten
        $result = $moduleLoader->handleAjaxRequest($module_key, $action, $data);
        echo json_encode($result);
        exit;
    }
    
    // Unbekannte Action
    echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
    exit;
}

// Heartbeat via GET (für Session-Management)
if (isset($_GET['heartbeat'])) {
    header('Content-Type: application/json');
    
    if (!SessionManager::isLoggedIn()) {
        echo json_encode(['success' => false, 'redirect' => 'login.php']);
        exit;
    }
    
    SessionManager::updateActivity();
    echo json_encode(['success' => true]);
    exit;
}

/**
 * Helper-Funktion zum Loggen von Aktivitäten
 * Wird von Modulen verwendet
 */
function logActivity($message, $level = 'INFO', $module = null) {
    if (!isFeatureEnabled('enable_logging')) {
        return;
    }
    
    $log_levels = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3];
    $current_level = getLogLevel();
    
    if ($log_levels[$level] < $current_level) {
        return;
    }
    
    $user = $_SESSION['user'] ?? null;
    $user_id = $user['id'] ?? 0;
    $username = $user['username'] ?? 'system';
    
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => $level,
        'module' => $module,
        'user_id' => $user_id,
        'username' => $username,
        'message' => $message,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    // In Datei schreiben
    $log_file = 'logs/activity_' . date('Y-m-d') . '.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_line = sprintf(
        "[%s] %s | %s | User: %s (%d) | %s | IP: %s\n",
        $log_entry['timestamp'],
        $log_entry['level'],
        $log_entry['module'] ?? 'SYSTEM',
        $log_entry['username'],
        $log_entry['user_id'],
        $log_entry['message'],
        $log_entry['ip']
    );
    
    file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
    
    // Optional: In Datenbank schreiben
    try {
        require_once __DIR__ . '/core/DatabaseOnlyFramework.php';
        $activityLog = new ActivityLog();
        $activityLog->insert([
            'user_id' => $log_entry['user_id'],
            'action' => $log_entry['module'] . '.' . $level,
            'details' => $log_entry['message'],
            'status' => strtolower($level) === 'error' ? 'error' : 'success',
            'ip_address' => $log_entry['ip'],
            'user_agent' => $log_entry['user_agent'],
            'created_at' => $log_entry['timestamp']
        ]);
    } catch (Exception $e) {
        // Fehler beim Schreiben in DB ignorieren
        error_log("Failed to write activity log to database: " . $e->getMessage());
    }
}

/**
 * Helper-Funktion für Rate Limiting
 */
function checkRateLimit($identifier, $max_requests = 60, $time_window = 60) {
    if (!isFeatureEnabled('rate_limit')) {
        return true;
    }
    
    $cache_key = 'rate_limit_' . md5($identifier);
    $cache_file = 'cache/' . $cache_key . '.json';
    
    if (!is_dir('cache')) {
        mkdir('cache', 0755, true);
    }
    
    $now = time();
    $requests = [];
    
    if (file_exists($cache_file)) {
        $data = json_decode(file_get_contents($cache_file), true);
        if (is_array($data)) {
            // Alte Einträge entfernen
            $requests = array_filter($data, function($timestamp) use ($now, $time_window) {
                return ($now - $timestamp) < $time_window;
            });
        }
    }
    
    if (count($requests) >= $max_requests) {
        return false;
    }
    
    $requests[] = $now;
    file_put_contents($cache_file, json_encode($requests));
    
    return true;
}

/**
 * Helper-Funktion für CSRF-Schutz
 */
function validateCSRFToken($token = null) {
    if (!isFeatureEnabled('csrf_protection')) {
        return true;
    }
    
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    }
    
    if (!$token || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Helper-Funktion für Input-Sanitization
 */
function sanitizeInput($input, $type = 'string') {
    if (!isFeatureEnabled('xss_protection')) {
        return $input;
    }
    
    switch ($type) {
        case 'int':
            return (int) $input;
            
        case 'float':
            return (float) $input;
            
        case 'email':
            return filter_var($input, FILTER_SANITIZE_EMAIL);
            
        case 'url':
            return filter_var($input, FILTER_SANITIZE_URL);
            
        case 'html':
            return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
            
        case 'string':
        default:
            return htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8');
    }
}
?>