<?php
/**
 * System Configuration
 * Admin Dashboard Core mit Plugin-System
 */

// Plugin Registry - Alle verfügbaren Plugins
$plugins = [
    'admin' => [
        'enabled' => true,
        'name' => 'Admin Dashboard',
        'icon' => '📊',
        'path' => 'module/admin',
        'version' => '1.0.0',
        'description' => 'Admin Dashboard und System-Verwaltung',
        'require_admin' => true
    ],
    
    'proxmox' => [
        'enabled' => true,
        'name' => 'Proxmox VM Management',
        'icon' => '🖥️',
        'path' => 'module/proxmox',
        'version' => '1.0.0',
        'description' => 'Virtuelle Maschinen mit Proxmox verwalten'
    ],
    
    'ispconfig' => [
        'enabled' => true,
        'name' => 'ISPConfig Website Management',
        'icon' => '🌐',
        'path' => 'module/ispconfig',
        'version' => '1.0.0',
        'description' => 'Websites und Webhosting mit ISPConfig verwalten'
    ],
    
    'ovh' => [
        'enabled' => true,
        'name' => 'OVH Services',
        'icon' => '🔗',
        'path' => 'module/ovh',
        'version' => '1.0.0',
        'description' => 'OVH Domains, VPS und Failover IPs verwalten'
    ],
    
    'virtual-mac' => [
        'enabled' => true,
        'name' => 'Virtual MAC Management',
        'icon' => '🔌',
        'path' => 'module/virtual-mac',
        'version' => '1.0.0',
        'description' => 'Virtual MAC Adressen für dedizierte Server verwalten'
    ],
    
    'network' => [
        'enabled' => true,
        'name' => 'Netzwerk Konfiguration',
        'icon' => '🔧',
        'path' => 'module/network',
        'version' => '1.0.0',
        'description' => 'VM Netzwerk-Einstellungen konfigurieren'
    ],
    
    'database' => [
        'enabled' => true,
        'name' => 'Datenbank Management',
        'icon' => '🗄️',
        'path' => 'module/database',
        'version' => '1.0.0',
        'description' => 'MySQL/MariaDB Datenbanken verwalten'
    ],
    
    'email' => [
        'enabled' => true,
        'name' => 'E-Mail Management',
        'icon' => '📧',
        'path' => 'module/email',
        'version' => '1.0.0',
        'description' => 'E-Mail Accounts und Postfächer verwalten'
    ],
    
    'endpoints' => [
        'enabled' => true,
        'name' => 'API Endpoint Tester',
        'icon' => '🔌',
        'path' => 'module/endpoints',
        'version' => '1.0.0',
        'description' => 'API Endpoints testen und debuggen'
    ],
    
    'custom-module' => [
        'enabled' => true,
        'name' => 'Custom Module',
        'icon' => '🔧',
        'path' => 'module/custom-module',
        'version' => '1.0.0',
        'description' => 'Benutzerdefiniertes Modul für Tests'
    ]
];

// Globale Systemeinstellungen
$system_config = [
    'version' => '3.0.0',
    'theme' => 'default',
    'language' => 'de',
    'timezone' => 'Europe/Berlin',
    'debug_mode' => true,
    'maintenance_mode' => false,
    'session_timeout' => 3600, // 60 Minuten
    'max_upload_size' => '50M',
    'enable_logging' => true,
    'log_level' => 'INFO', // DEBUG, INFO, WARNING, ERROR
    'admin_email' => 'admin@example.com'
];

// Feature Flags
$feature_flags = [
    'lazy_loading' => true,
    'advanced_search' => false,
    'bulk_operations' => false,
    'api_v2' => false,
    'dark_mode' => false,
    'multi_language' => false,
    'webhooks' => false,
    'two_factor_auth' => false,
    'plugin_auto_update' => false
];

// API Konfiguration
$api_config = [
    'rate_limit' => [
        'enabled' => true,
        'requests_per_minute' => 60,
        'requests_per_hour' => 1000
    ],
    'cors' => [
        'enabled' => false,
        'allowed_origins' => ['*'],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
        'allowed_headers' => ['Content-Type', 'Authorization']
    ],
    'timeout' => 30 // Sekunden
];

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
?>