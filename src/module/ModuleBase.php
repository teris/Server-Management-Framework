<?php
if (PHP_SAPI !== 'cli' && (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__ || (get_included_files()[0] ?? null) === __FILE__)) { http_response_code(404); exit; }

/**
 * ModuleBase - Abstrakte Basisklasse für alle Module
 */

// LanguageManager einbinden
require_once dirname(__DIR__) . '/core/LanguageManager.php';
require_once dirname(__DIR__) . '/core/ResourceAccess.php';
require_once dirname(__DIR__) . '/core/ProviderTransport.php';

abstract class ModuleBase {
    protected $module_key;
    protected $module_config;
    protected $user_role;
    protected $user_id;
    protected $language_manager;
    
    public function __construct($module_key) {
        $this->module_key = $module_key;
        $this->module_config = getModuleConfig($module_key);
        
        $identity = Identity::current('backend');
        $this->user_role = $identity['role'] ?? null;
        $this->user_id = $identity['id'] ?? null;

        // Sprachmanager initialisieren
        $this->language_manager = LanguageManager::getInstance();
    }
    
    /**
     * Prüft ob der aktuelle Benutzer auf das Modul zugreifen darf
     */
    public function canAccess() {
        return ResourceAccess::legacyAllowed($this->module_key);
    }
    
    /**
     * Gibt den HTML-Content für die Tab-Navigation zurück
     */
    public function getTabButton() {
        if (!$this->canAccess()) {
            return '';
        }
        
        $config = $this->module_config;
        return sprintf(
            '<button class="tab" onclick="showTab(\'%s\', this)">%s %s</button>',
            $this->module_key,
            $config['icon'],
            $config['name']
        );
    }
    
    /**
     * Abstrakte Methode - muss von jedem Modul implementiert werden
     * Gibt den HTML-Content für den Tab-Inhalt zurück
     */
    abstract public function getContent();
    
    /**
     * Abstrakte Methode - muss von jedem Modul implementiert werden
     * Verarbeitet AJAX-Requests für das Modul
     */
    abstract public function handleAjaxRequest($action, $data);
    
    /**
     * Optional - kann überschrieben werden
     * Gibt zusätzliche JavaScript-Dateien für das Modul zurück
     */
    public function getScripts() {
        $scripts = [];
        $script_path = $this->module_config['path'] . '/assets/module.js';
        if (file_exists($script_path)) {
            $scripts[] = $script_path;
        }
        return $scripts;
    }
    
    /**
     * Optional - kann überschrieben werden
     * Gibt zusätzliche CSS-Dateien für das Modul zurück
     */
    public function getStyles() {
        $styles = [];
        $style_path = $this->module_config['path'] . '/assets/module.css';
        if (file_exists($style_path)) {
            $styles[] = $style_path;
        }
        return $styles;
    }
    
    /**
     * Optional - kann überschrieben werden
     * Wird beim Initialisieren des Moduls aufgerufen
     */
    public function init() {
        // Kann von Subklassen überschrieben werden
    }
    
    /**
     * Helper: Rendert ein Template
     */
    protected function render($template, $data = []) {
        $template_path = $this->module_config['path'] . '/templates/' . $template . '.php';
        
        if (!file_exists($template_path)) {
            throw new Exception("Template not found: $template_path");
        }
        
        // Store data in a way that can be accessed by included files
        $GLOBALS['_template_data'] = $data;
        
        // Extract variables to make them available in current scope
        extract($data);
        
        // Start output buffering
        ob_start();
        
        // Include the template with variables available
        include $template_path;
        
        // Clean up
        unset($GLOBALS['_template_data']);
        
        return ob_get_clean();
    }
    
    /**
     * Helper: Übersetzt einen Schlüssel für das aktuelle Modul
     */
    protected function t($key, $default = null) {
        return $this->language_manager->translate($this->module_key, $key, $default);
    }
    
    /**
     * Helper: Übersetzt mehrere Schlüssel für das aktuelle Modul
     */
    protected function tMultiple($keys) {
        return $this->language_manager->translateMultiple($this->module_key, $keys);
    }
    
    /**
     * Helper: Log-Funktion für Module
     */
    protected function log($message, $level = 'INFO') {
        if (function_exists('logActivity')) {
            logActivity($this->module_key . ': ' . $message, $level);
        }
    }
    
    /**
     * Helper: Validiert Eingabedaten
     */
    protected function validate($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            if (!isset($data[$field]) && strpos($rule, 'required') !== false) {
                $errors[$field] = "Field $field is required";
                continue;
            }
            
            if (isset($data[$field])) {
                $value = $data[$field];
                
                // Email validation
                if (strpos($rule, 'email') !== false && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = "Invalid email format";
                }
                
                // Numeric validation
                if (strpos($rule, 'numeric') !== false && !is_numeric($value)) {
                    $errors[$field] = "Field must be numeric";
                }
                
                // Min length
                if (preg_match('/min:(\d+)/', $rule, $matches)) {
                    if (strlen($value) < $matches[1]) {
                        $errors[$field] = "Field must be at least {$matches[1]} characters";
                    }
                }
                
                // Max length
                if (preg_match('/max:(\d+)/', $rule, $matches)) {
                    if (strlen($value) > $matches[1]) {
                        $errors[$field] = "Field must not exceed {$matches[1]} characters";
                    }
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Helper: Erfolgsantwort für AJAX
     */
    protected function success($data = null, $message = 'Operation successful') {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data
        ];
    }
    
    /**
     * Helper: Fehlerantwort für AJAX
     */
    protected function error($message = 'Operation failed', $data = null) {
        return [
            'success' => false,
            'error' => $message,
            'data' => $data
        ];
    }
    
    /**
     * Helper: Prüft Admin-Rechte
     */
    protected function requireAdmin() {
        if ($this->user_role !== 'admin') {
            throw new Exception('Admin rights required');
        }
    }
    
    /**
     * Optional - kann überschrieben werden
     * Gibt Statistiken für das Dashboard zurück
     */
    public function getStats() {
        return [];
    }
    
    /**
     * Optional - kann überschrieben werden
     * Cleanup-Funktionen beim Deaktivieren des Moduls
     */
    public function onDisable() {
        // Kann von Subklassen überschrieben werden
    }
    
    /**
     * Optional - kann überschrieben werden
     * Setup-Funktionen beim Aktivieren des Moduls
     */
    public function onEnable() {
        // Kann von Subklassen überschrieben werden
    }
}

/**
 * Module Loader - Lädt und verwaltet alle Module
 */
class ModuleLoader {
    private static $instance = null;
    private $modules = [];
    private $loaded_modules = [];
    
    private function __construct() {
        $this->loadModules();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new ModuleLoader();
        }
        return self::$instance;
    }
	
	public function getEnabledModules() {
        return getEnabledPlugins();
    }
    
    private function loadModules() {
        // Verwende das bestehende Plugin-System
        $enabled_modules = getEnabledPlugins();
        
        foreach ($enabled_modules as $key => $config) {
            if (!ResourceAccess::legacyAllowed($key)) continue;
            $module_file = $config['path'] . '/Module.php';
            
            if (file_exists($module_file)) {
                require_once $module_file;
                
                // Konvertiere Module-Keys zu korrekten PHP-Klassennamen
                $class_name = $this->getClassNameFromKey($key);
                
                if (class_exists($class_name)) {
                    $this->loaded_modules[$key] = new $class_name($key);
                    $this->loaded_modules[$key]->init();
                } else {
                    error_log("Module class not found: $class_name for key: $key");
                }
            } else {
                error_log("Module file not found: $module_file");
            }
        }
    }
    
    /**
     * Konvertiert Module-Keys zu korrekten PHP-Klassennamen
     */
    private function getClassNameFromKey($key) {
        // Spezielle Mappings für Module mit Bindestrichen
        $special_mappings = [
            'virtual-mac' => 'VirtualMacModule',
            'custom-module' => 'CustomModuleModule',
            'support-tickets' => 'SupportTicketsModule',
            'migration' => 'MigrationModule',
            'file-editor' => 'FileEditorModule'
        ];
        
        if (isset($special_mappings[$key])) {
            return $special_mappings[$key];
        }
        
        // Standard: Ersten Buchstaben groß + "Module"
        return ucfirst($key) . 'Module';
    }
    
    public function getModule($key) {
        return isset($this->loaded_modules[$key]) ? $this->loaded_modules[$key] : null;
    }
    
    public function getAllModules() {
        return $this->loaded_modules;
    }
    
    public function getTabButtons() {
        $buttons = [];
        foreach ($this->loaded_modules as $module) {
            $button = $module->getTabButton();
            if ($button) {
                $buttons[] = $button;
            }
        }
        return implode("\n", $buttons);
    }
    
    public function getModuleContents() {
        $contents = [];
        foreach ($this->loaded_modules as $key => $module) {
            if ($module->canAccess()) {
                $contents[$key] = $module->getContent();
            }
        }
        return $contents;
    }
    
    public function getAllScripts() {
        $scripts = [];
        foreach ($this->loaded_modules as $module) {
            $scripts = array_merge($scripts, $module->getScripts());
        }
        return $scripts;
    }
    
    public function getAllStyles() {
        $styles = [];
        foreach ($this->loaded_modules as $module) {
            $styles = array_merge($styles, $module->getStyles());
        }
        return $styles;
    }
    
    public function handleAjaxRequest($module_key, $action, $data) {
        $module = $this->getModule($module_key);
        
        if (!$module) {
            return ['success' => false, 'error' => 'Module not found'];
        }
        
        if (!$module->canAccess()) {
            return ['success' => false, 'error' => 'Access denied'];
        }
        
        try {
            // Spezielle Behandlung für getContent Aktion
            if ($action === 'getContent') {
                $content = $module->getContent();
                $config = getModuleConfig($module_key);
                
                // Debug-Log
                error_log("ModuleLoader: getContent for $module_key - Content length: " . strlen($content));
                
                return [
                    'success' => true,
                    'content' => $content,
                    'title' => $config['name'] ?? $module_key
                ];
            }
            
            return HttpSecurity::redact($module->handleAjaxRequest($action, $data));
        } catch (Exception $e) {
            error_log("ModuleLoader: Error handling request for $module_key/$action: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Legacy Support für Plugin-System
     */
    public function handlePluginRequest($plugin_key, $action, $data) {
        return $this->handleAjaxRequest($plugin_key, $action, $data);
    }
    
    public function getEnabledPlugins() {
        return $this->getEnabledModules();
    }
    
        public function getAllPlugins() {
        // Konvertiere Module zu Plugin-ähnlichen Objekten für die Kompatibilität
        $plugins = [];
        foreach ($this->loaded_modules as $key => $module) {
            $config = getPluginConfig($key);
            $plugins[$key] = new class($module, $config) {
                private $module;
                private $config;
                
                public function __construct($module, $config) {
                    $this->module = $module;
                    $this->config = $config;
                }
                
                public function isEnabled() {
                    return $this->config['enabled'] ?? true;
                }
                
                public function getIcon() {
                    return $this->config['icon'] ?? '📦';
                }
                
                public function getName() {
                    return $this->config['name'] ?? 'Unknown';
                }
                
                public function getDescription() {
                    return $this->config['description'] ?? '';
                }
                
                public function getVersion() {
                    return $this->config['version'] ?? '1.0.0';
                }
            };
        }
        return $plugins;
    }
    
    /**
     * Legacy Plugin-System Kompatibilität
     */
    public function getPlugin($key) {
        return $this->getModule($key);
    }
    
    public function isPluginEnabled($key) {
        $module = $this->getModule($key);
        return $module !== null;
    }
    
    public function getPluginConfig($key) {
        return getPluginConfig($key);
    }
    
    public function getDashboardStats() {
        $stats = [];
        foreach ($this->loaded_modules as $key => $module) {
            if ($module->canAccess()) {
                $module_stats = $module->getStats();
                if (!empty($module_stats)) {
                    $stats[$key] = $module_stats;
                }
            }
        }
        return $stats;
    }
}
?>