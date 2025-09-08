<?php
/**
 * Server Management Framework
 * 
 * @author Teris
 * @version 3.1.4
 */

// LanguageManager einbinden (falls verfügbar)
if (file_exists(__DIR__ . '/core/LanguageManager.php')) {
    require_once __DIR__ . '/core/LanguageManager.php';
}

// --- PLUGINS START ---
$plugins = array (
  'admin' => 
  array (
    'enabled' => false,
    'name' => 'Admin Dashboard',
    'icon' => '📊',
    'path' => 'module/admin',
    'version' => '1.0.0',
    'description' => 'Admin Dashboard und System-Verwaltung',
    'require_admin' => true,
  ),
  'proxmox' => 
  array (
    'enabled' => false,
    'name' => 'Proxmox VM Management',
    'icon' => '🖥️',
    'path' => 'module/proxmox',
    'version' => '1.0.0',
    'description' => 'Virtuelle Maschinen mit Proxmox verwalten',
    'require_admin' => false,
  ),
  'ispconfig' => 
  array (
    'enabled' => true,
    'name' => 'ISPConfig Website Management',
    'icon' => '🌐',
    'path' => 'module/ispconfig',
    'version' => '1.5.0',
    'description' => 'Websites und Webhosting mit ISPConfig verwalten',
    'require_admin' => false,
  ),
  'ovh' => 
  array (
    'enabled' => false,
    'name' => 'OVH Services',
    'icon' => '🔗',
    'path' => 'module/ovh',
    'version' => '1.0.0',
    'description' => 'OVH Domains, VPS und Failover IPs verwalten',
    'require_admin' => false,
  ),
  'dns' => 
  array (
    'enabled' => true,
    'name' => 'DNS Verwaltung',
    'icon' => '🌐',
    'path' => 'module/dns',
    'version' => '2.0.0',
    'description' => 'DNS-Records und DNSSEC für OVH-Domains verwalten',
    'require_admin' => false,
  ),
  'virtual-mac' => 
  array (
    'enabled' => false,
    'name' => 'Virtual MAC Management',
    'icon' => '🔌',
    'path' => 'module/virtual-mac',
    'version' => '1.0.0',
    'description' => 'Virtual MAC Adressen für dedizierte Server verwalten',
    'require_admin' => false,
  ),
  'network' => 
  array (
    'enabled' => false,
    'name' => 'Netzwerk Konfiguration',
    'icon' => '🔧',
    'path' => 'module/network',
    'version' => '1.0.0',
    'description' => 'VM Netzwerk-Einstellungen konfigurieren',
    'require_admin' => false,
  ),
  'database' => 
  array (
    'enabled' => false,
    'name' => 'Datenbank Management',
    'icon' => '🗄️',
    'path' => 'module/database',
    'version' => '1.0.0',
    'description' => 'MySQL/MariaDB Datenbanken verwalten',
    'require_admin' => false,
  ),
  'email' => 
  array (
    'enabled' => false,
    'name' => 'E-Mail Management',
    'icon' => '📧',
    'path' => 'module/email',
    'version' => '1.0.0',
    'description' => 'E-Mail Accounts und Postfächer verwalten',
    'require_admin' => false,
  ),
  'endpoints' => 
  array (
    'enabled' => true,
    'name' => 'API Endpoint Tester',
    'icon' => '🔌',
    'path' => 'module/endpoints',
    'version' => '1.0.0',
    'description' => 'API Endpoints testen und debuggen',
    'require_admin' => false,
  ),
  'custom-module' => 
  array (
    'enabled' => false,
    'name' => 'Custom Module',
    'icon' => '🔧',
    'path' => 'module/custom-module',
    'version' => '1.0.0',
    'description' => 'Benutzerdefiniertes Modul für Tests',
    'require_admin' => false,
  ),
  'support-tickets' => 
  array (
    'enabled' => true,
    'name' => 'Support Tickets',
    'icon' => '🎫',
    'path' => 'module/support-tickets',
    'version' => '1.0.0',
    'description' => 'Support-Tickets verwalten und bearbeiten',
    'require_admin' => true,
  ),
  'migration' => 
  array (
    'enabled' => true,
    'name' => 'Migration',
    'icon' => '',
    'path' => 'module/migration',
    'version' => '1.0.0',
    'description' => 'Migrien von Daten aus Systemen',
    'require_admin' => true,
  ),
);
// --- PLUGINS END ---

// --- SYSTEM_CONFIG START ---
$system_config = array (
  'version' => '3.1.4',
  'theme' => 'default',
  'language' => 'de',
  'available_languages' => 'de,en,fr,es,it',
  'timezone' => 'Europe/Berlin',
  'debug_mode' => true,
  'maintenance_mode' => false,
  'session_timeout' => '3600',
  'max_upload_size' => '50M',
  'enable_logging' => true,
  'log_level' => 'INFO',
  'admin_email' => 'admin@example.com',
);
// --- SYSTEM_CONFIG END ---



// --- FEATURE_FLAGS START ---
$feature_flags = array (
  'lazy_loading' => true,
  'advanced_search' => false,
  'bulk_operations' => false,
  'api_v2' => false,
  'dark_mode' => false,
  'multi_language' => true,
  'webhooks' => false,
  'two_factor_auth' => false,
  'plugin_auto_update' => false,
);
// --- FEATURE_FLAGS END ---



// --- API_CONFIG START ---
$api_config = array (
  'rate_limit' => 
  array (
    'enabled' => '1',
    'requests_per_minute' => '60',
    'requests_per_hour' => '1000',
  ),
  'cors' => 
  array (
    'enabled' => '',
    'allowed_origins' => 
    array (
      0 => '*',
    ),
    'allowed_methods' => 
    array (
      0 => 'GET',
      1 => 'POST',
      2 => 'PUT',
      3 => 'DELETE',
    ),
    'allowed_headers' => 
    array (
      0 => 'Content-Type',
      1 => 'Authorization',
    ),
  ),
  'timeout' => '30',
);
// --- API_CONFIG END ---


// Security Settings
$security_config = [
    'csrf_protection' => true,
    'xss_protection' => true,
    'sql_injection_protection' => true,
    'force_https' => false,
    'secure_cookies' => true,
    'password_min_length' => 8,
    'password_require_special' => true,
    'password_require_numbers' => true,
    'password_require_uppercase' => true,
    'max_login_attempts' => 5,
    'lockout_duration' => 900, // 15 Minuten
    'session_regenerate' => 300 // 5 Minuten
];

// Dashboard Konfiguration
$dashboard_config = [
    'refresh_interval' => 30, // Sekunden
    'max_items_per_page' => 50,
    'default_view' => 'overview',
    'enable_charts' => true,
    'enable_notifications' => true
];

$mode = 'api'; //mysql oder api

$modus_type =[
    'modus' => 'api' //mysql oder api
];


// Helper Functions
function getEnabledPlugins() {
    global $plugins;
    $enabled = [];
    foreach ($plugins as $key => $plugin) {
        if ($plugin['enabled']) {
            $enabled[$key] = $plugin;
        }
    }
    return $enabled;
}

function isPluginEnabled($plugin_key) {
    global $plugins;
    
    // Check session override first
    if (isset($_SESSION['plugin_states'][$plugin_key])) {
        return $_SESSION['plugin_states'][$plugin_key];
    }
    
    return isset($plugins[$plugin_key]) && $plugins[$plugin_key]['enabled'];
}

function getPluginConfig($plugin_key) {
    global $plugins;
    return isset($plugins[$plugin_key]) ? $plugins[$plugin_key] : null;
}

/**
 * Erkennt das aktuelle Modul basierend auf dem Call-Stack
 */
function getCurrentModule() {
    // Prüfe den Call-Stack nach Modul-Klassen
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
    
    foreach ($backtrace as $frame) {
        if (isset($frame['class'])) {
            $class_name = $frame['class'];
            
            // Prüfe ob es sich um ein Modul handelt (endet mit "Module")
            if (strpos($class_name, 'Module') !== false && $class_name !== 'ModuleBase') {
                // Extrahiere den Modul-Namen aus dem Klassennamen
                $module_name = str_replace('Module', '', $class_name);
                $module_name = strtolower($module_name);
                
                // Prüfe ob das Modul existiert
                if (getPluginConfig($module_name)) {
                    return $module_name;
                }
            }
        }
        
        // Prüfe auch nach Dateipfaden, die auf Module hinweisen
        if (isset($frame['file'])) {
            $file_path = $frame['file'];
            if (preg_match('/\/module\/([^\/]+)\//', $file_path, $matches)) {
                $module_name = $matches[1];
                if (getPluginConfig($module_name)) {
                    return $module_name;
                }
            }
        }
    }
    
    return null;
}

// System Helper Functions
function isMaintenanceMode() {
    global $system_config;
    return $system_config['maintenance_mode'];
}

function isDebugMode() {
    global $system_config;
    return $system_config['debug_mode'];
}

function isFeatureEnabled($feature) {
    global $feature_flags;
    return isset($feature_flags[$feature]) && $feature_flags[$feature];
}

function isSecurityEnabled($feature) {
    global $security_config;
    return isset($security_config[$feature]) && $security_config[$feature];
}

function getSystemVersion() {
    global $system_config;
    return $system_config['version'];
}

function getLogLevel() {
    global $system_config;
    $levels = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARNING' => 2,
        'ERROR' => 3
    ];
    return $levels[$system_config['log_level']] ?? 1;
}

// Environment-specific overrides
if (file_exists(__DIR__ . '/sys.conf.local.php')) {
    require_once __DIR__ . '/sys.conf.local.php';
}

// Set timezone
date_default_timezone_set($system_config['timezone']);

// Error reporting based on debug mode
if ($system_config['debug_mode']) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ERROR | E_WARNING);
    ini_set('display_errors', 0);
}

// Language Manager Helper Functions
function getLanguageManager() {
    if (!class_exists('LanguageManager')) {
        return null;
    }
    return LanguageManager::getInstance();
}

function translate($module_key, $key, $default = null) {
    $lm = getLanguageManager();
    if (!$lm) {
        return $default !== null ? $default : $key;
    }
    return $lm->translate($module_key, $key, $default);
}

function setLanguage($language) {
    $lm = getLanguageManager();
    if (!$lm) {
        return false;
    }
    return $lm->setLanguage($language);
}

function getCurrentLanguage() {
    $lm = getLanguageManager();
    if (!$lm) {
        global $system_config;
        return $system_config['language'] ?? 'de';
    }
    return $lm->getCurrentLanguage();
}

function getAvailableLanguages() {
    $lm = getLanguageManager();
    if (!$lm) {
        global $system_config;
        return $system_config['available_languages'] ?? ['de'];
    }
    return $lm->getAvailableLanguages();
}

// Enhanced Translation Helper Functions
function t($key, $default = null, $module_key = null) {
    $lm = getLanguageManager();
    if (!$lm) {
        return $default !== null ? $default : $key;
    }
    
    // Wenn kein Modul angegeben wurde, versuche das aktuelle Modul zu erkennen
    if ($module_key === null) {
        $module_key = getCurrentModule();
    }
    
    // Wenn ein Modul erkannt wurde, verwende Modul-Übersetzungen
    if ($module_key && $module_key !== 'core') {
        return $lm->translate($module_key, $key, $default);
    }
    
    // Fallback auf Core-Übersetzungen
    return $lm->translateCore($key, $default);
}

function tMultiple($keys, $module_key = null) {
    $lm = getLanguageManager();
    if (!$lm) {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $key;
        }
        return $result;
    }
    
    // Wenn kein Modul angegeben wurde, versuche das aktuelle Modul zu erkennen
    if ($module_key === null) {
        $module_key = getCurrentModule();
    }
    
    // Wenn ein Modul erkannt wurde, verwende Modul-Übersetzungen
    if ($module_key && $module_key !== 'core') {
        return $lm->translateMultiple($module_key, $keys);
    }
    
    // Fallback auf Core-Übersetzungen
    return $lm->translateCoreMultiple($keys);
}
?>