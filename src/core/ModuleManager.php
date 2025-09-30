<?php
/**
 * ModuleManager - Verwaltung von Modulen
 * 
 * @author Teris
 * @version 1.0.0
 */

class ModuleManager {
    private $config_file;
    private $module_dir;
    private $db;
    private $github_installer;
    
    public function __construct() {
        $this->config_file = dirname(__DIR__) . '/sys.conf.php';
        $this->module_dir = dirname(__DIR__) . '/module/';
        
        try {
            $this->db = DatabaseManager::getInstance();
        } catch (Exception $e) {
            error_log("ModuleManager: Could not initialize DatabaseManager: " . $e->getMessage());
            $this->db = null;
        }
        
        // GitHub-Installer laden
        require_once __DIR__ . '/GitHubModuleInstaller.php';
        $this->github_installer = new GitHubModuleInstaller();
    }
    
    /**
     * Gibt alle verfügbaren Module zurück
     * Zeigt auch Module ohne module.json (können aber nicht aktiviert werden)
     */
    public function getAllModules() {
        $modules = [];
        $dirs = glob($this->module_dir . '*', GLOB_ONLYDIR);
        
        foreach ($dirs as $dir) {
            $module_key = basename($dir);
            
            // Skip lang Ordner und .bnk Backups
            if ($module_key === 'lang' || strpos($module_key, '.bnk') !== false) {
                continue;
            }
            
            $module_info = $this->getModuleInfo($module_key);
            
            if ($module_info) {
                // Modul hat gültige module.json
                $module_info['key'] = $module_key;
                $module_info['enabled'] = $this->isModuleEnabled($module_key);
                $module_info['has_json'] = true;
                $modules[$module_key] = $module_info;
            } else {
                // Modul ohne module.json - wird angezeigt aber kann nicht aktiviert werden
                $modules[$module_key] = [
                    'key' => $module_key,
                    'name' => ucfirst($module_key),
                    'author' => 'Unbekannt',
                    'version' => '?',
                    'description' => 'Keine module.json vorhanden',
                    'icon' => '⚠️',
                    'enabled' => false,
                    'has_json' => false
                ];
            }
        }
        
        return $modules;
    }
    
    /**
     * Liest die module.json eines Moduls
     * Gibt null zurück wenn nicht vorhanden (Modul kann nicht aktiviert werden)
     */
    public function getModuleInfo($module_key) {
        $json_file = $this->module_dir . $module_key . '/module.json';
        
        if (!file_exists($json_file)) {
            error_log("ModuleManager: module.json nicht gefunden für '$module_key'");
            return null;
        }
        
        $content = file_get_contents($json_file);
        $info = json_decode($content, true);
        
        if ($info === null) {
            error_log("ModuleManager: Ungültige JSON in module.json für '$module_key'");
            return null;
        }
        
        return $info;
    }
    
    /**
     * Prüft ob ein Modul aktiviert ist
     */
    public function isModuleEnabled($module_key) {
        $plugins = $this->loadPluginsArray();
        return isset($plugins[$module_key]) && !empty($plugins[$module_key]['enabled']);
    }
    
    /**
     * Lädt das $plugins Array aus sys.conf.php
     */
    private function loadPluginsArray() {
        if (!file_exists($this->config_file)) {
            return [];
        }
        
        // Lade die Konfigurationsdatei
        $plugins = [];
        include $this->config_file;
        
        // $plugins wird durch include geladen
        return $plugins ?? [];
    }
    
    /**
     * Aktiviert ein Modul (setzt enabled = true)
     */
    public function enableModule($module_key) {
        // Prüfe ob Modul existiert und module.json hat
        $module_info = $this->getModuleInfo($module_key);
        if (!$module_info) {
            throw new Exception("Modul '$module_key' hat keine gültige module.json und kann nicht aktiviert werden");
        }
        
        // Prüfe PHP-Version
        if (isset($module_info['min_php']) && version_compare(PHP_VERSION, $module_info['min_php'], '<')) {
            throw new Exception("Modul benötigt mindestens PHP " . $module_info['min_php']);
        }
        
        // Prüfe Abhängigkeiten
        if (isset($module_info['dependencies']) && !empty($module_info['dependencies'])) {
            foreach ($module_info['dependencies'] as $dep) {
                if (!$this->isModuleEnabled($dep)) {
                    throw new Exception("Abhängigkeit '$dep' ist nicht aktiviert");
                }
            }
        }
        
        // Setze enabled = true in Config
        $this->addModuleToConfig($module_key, $module_info, true);
        
        // Rufe onEnable Callback auf, falls vorhanden
        $this->callModuleCallback($module_key, 'onEnable');
        
        // Logge die Aktivierung
        if (function_exists('logActivity')) {
            logActivity("Modul '$module_key' wurde aktiviert", 'INFO');
        }
        
        return true;
    }
    
    /**
     * Deaktiviert ein Modul (setzt enabled = false)
     */
    public function disableModule($module_key) {
        // Rufe onDisable Callback auf, falls vorhanden
        $this->callModuleCallback($module_key, 'onDisable');
        
        // Setze enabled = false (entferne NICHT aus Config)
        $this->removeModuleFromConfig($module_key);
        
        // Logge die Deaktivierung
        if (function_exists('logActivity')) {
            logActivity("Modul '$module_key' wurde deaktiviert", 'INFO');
        }
        
        return true;
    }
    
    /**
     * Installiert ein Modul aus einer ZIP-Datei (manuell)
     */
    public function installModule($zip_path, $module_key = null) {
        if (!file_exists($zip_path)) {
            throw new Exception("ZIP-Datei nicht gefunden");
        }
        
        $zip = new ZipArchive();
        if ($zip->open($zip_path) !== TRUE) {
            throw new Exception("ZIP-Datei konnte nicht geöffnet werden");
        }
        
        // Temporäres Verzeichnis
        $temp_dir = sys_get_temp_dir() . '/smf_module_' . uniqid();
        mkdir($temp_dir);
        
        // Entpacken
        $zip->extractTo($temp_dir);
        $zip->close();
        
        // Suche nach module/ Verzeichnis (muss vorhanden sein!)
        $module_base = $temp_dir . '/module';
        if (!is_dir($module_base)) {
            $this->deleteDirectory($temp_dir);
            throw new Exception("Ungültige Struktur! ZIP muss 'module/<modulname>/' enthalten. Siehe: https://github.com/teris/SMF-Module");
        }
        
        // Finde das Modul-Unterverzeichnis
        $dirs = glob($module_base . '/*', GLOB_ONLYDIR);
        if (empty($dirs)) {
            $this->deleteDirectory($temp_dir);
            throw new Exception("Kein Modul-Verzeichnis in 'module/' gefunden! Siehe: https://github.com/teris/SMF-Module");
        }
        
        $module_path = $dirs[0]; // Erstes Verzeichnis
        $detected_key = basename($module_path);
        
        // Prüfe Pflicht-Dateien
        $module_json_path = $module_path . '/module.json';
        $module_php_path = $module_path . '/Module.php';
        $main_template_path = $module_path . '/templates/main.php';
        
        if (!file_exists($module_json_path)) {
            $this->deleteDirectory($temp_dir);
            throw new Exception("module.json fehlt! Siehe: https://github.com/teris/SMF-Module");
        }
        
        if (!file_exists($module_php_path)) {
            $this->deleteDirectory($temp_dir);
            throw new Exception("Module.php fehlt! Siehe: https://github.com/teris/SMF-Module");
        }
        
        if (!file_exists($main_template_path)) {
            $this->deleteDirectory($temp_dir);
            throw new Exception("templates/main.php fehlt! Siehe: https://github.com/teris/SMF-Module");
        }
        
        // Lese Modul-Informationen
        $module_info = json_decode(file_get_contents($module_json_path), true);
        if (!$module_info) {
            $this->deleteDirectory($temp_dir);
            throw new Exception("Ungültige module.json!");
        }
        
        // Ziel-Pfad
        $target_path = $this->module_dir . $detected_key;
        
        // Erstelle Backup falls vorhanden
        if (file_exists($target_path)) {
            $backup_path = $target_path . '.bnk';
            if (file_exists($backup_path)) {
                $this->deleteDirectory($backup_path);
            }
            rename($target_path, $backup_path);
        }
        
        // Verschiebe Modul
        if (!rename($module_path, $target_path)) {
            // Restore Backup bei Fehler
            if (isset($backup_path) && file_exists($backup_path)) {
                rename($backup_path, $target_path);
            }
            $this->deleteDirectory($temp_dir);
            throw new Exception("Modul konnte nicht installiert werden");
        }
        
        // Aufräumen
        $this->deleteDirectory($temp_dir);
        
        // Füge zu sys.conf.php hinzu (enabled = false!)
        $this->addModuleToConfig($detected_key, $module_info, false);
        
        // Logge die Installation
        if (function_exists('logActivity')) {
            logActivity("Modul '$detected_key' wurde installiert", 'INFO');
        }
        
        return [
            'key' => $detected_key,
            'info' => $module_info
        ];
    }
    
    /**
     * Deinstalliert ein Modul (löscht Dateien komplett)
     */
    public function uninstallModule($module_key) {
        $module_path = $this->module_dir . $module_key;
        
        if (!file_exists($module_path)) {
            throw new Exception("Modul nicht gefunden");
        }
        
        // Erstelle Backup vor dem Löschen
        $backup_path = $module_path . '.bnk';
        if (file_exists($backup_path)) {
            $this->deleteDirectory($backup_path);
        }
        
        // Verschiebe zu .bnk (als Backup)
        rename($module_path, $backup_path);
        
        // LÖSCHE das Backup komplett
        $this->deleteDirectory($backup_path);
        
        // Entferne KOMPLETT aus sys.conf.php
        $this->removeModuleCompletelyFromConfig($module_key);
        
        // Logge die Deinstallation
        if (function_exists('logActivity')) {
            logActivity("Modul '$module_key' wurde deinstalliert und gelöscht", 'INFO');
        }
        
        return true;
    }
    
    /**
     * Entfernt ein Modul KOMPLETT aus der Config
     */
    private function removeModuleCompletelyFromConfig($module_key) {
        if (!file_exists($this->config_file)) {
            throw new Exception("Config-Datei nicht gefunden");
        }
        
        // Lade das aktuelle $plugins Array
        $plugins = $this->loadPluginsArray();
        
        // Entferne das Modul komplett
        unset($plugins[$module_key]);
        
        // Speichere das aktualisierte Array
        $this->savePluginsArray($plugins);
    }
    
    /**
     * Aktualisiert ein Modul
     */
    public function updateModule($module_key, $zip_path) {
        // Deaktiviere Modul zuerst
        $was_enabled = $this->isModuleEnabled($module_key);
        if ($was_enabled) {
            $this->disableModule($module_key);
        }
        
        // Installiere neue Version
        $result = $this->installModule($zip_path, $module_key);
        
        // Aktiviere wieder, falls vorher aktiviert
        if ($was_enabled) {
            $this->enableModule($module_key);
        }
        
        // Logge das Update
        if (function_exists('logActivity')) {
            logActivity("Modul '$module_key' wurde aktualisiert", 'INFO');
        }
        
        return $result;
    }
    
    /**
     * Fügt ein Modul zur Config hinzu
     * 
     * @param string $module_key Der Modul-Schlüssel
     * @param array|null $module_info Modul-Informationen (falls bereits geladen)
     * @param bool $enabled Standard-Status (true für aktivieren, false für neu installiert)
     */
    private function addModuleToConfig($module_key, $module_info = null, $enabled = true) {
        if (!file_exists($this->config_file)) {
            throw new Exception("Config-Datei nicht gefunden");
        }
        
        // Lade das aktuelle $plugins Array
        $plugins = $this->loadPluginsArray();
        
        // Wenn Modul-Info nicht übergeben wurde, lade sie
        if ($module_info === null) {
            $module_info = $this->getModuleInfo($module_key);
            if (!$module_info) {
                throw new Exception("module.json nicht gefunden für '$module_key'");
            }
        }
        
        // Wenn Modul bereits existiert, nur Status ändern
        if (isset($plugins[$module_key])) {
            $plugins[$module_key]['enabled'] = $enabled;
            
            // Update andere Felder aus module.json
            $plugins[$module_key]['name'] = $module_info['name'] ?? $plugins[$module_key]['name'];
            $plugins[$module_key]['icon'] = $module_info['icon'] ?? $plugins[$module_key]['icon'];
            $plugins[$module_key]['version'] = $module_info['version'] ?? $plugins[$module_key]['version'];
            $plugins[$module_key]['description'] = $module_info['description'] ?? $plugins[$module_key]['description'];
            
            // require_admin aus module.json oder Standard false
            if (isset($module_info['require_admin'])) {
                $plugins[$module_key]['require_admin'] = (bool)$module_info['require_admin'];
            }
        } else {
            // Neues Modul erstellen
            $plugins[$module_key] = [
                'enabled' => $enabled,
                'name' => $module_info['name'] ?? ucfirst($module_key),
                'icon' => $module_info['icon'] ?? '📦',
                'path' => 'module/' . $module_key,
                'version' => $module_info['version'] ?? '1.0.0',
                'description' => $module_info['description'] ?? '',
                'require_admin' => isset($module_info['require_admin']) ? (bool)$module_info['require_admin'] : false
            ];
        }
        
        // Speichere das aktualisierte Array
        $this->savePluginsArray($plugins);
    }
    
    /**
     * Entfernt ein Modul aus der Config (setzt enabled auf false)
     */
    private function removeModuleFromConfig($module_key) {
        if (!file_exists($this->config_file)) {
            throw new Exception("Config-Datei nicht gefunden");
        }
        
        // Lade das aktuelle $plugins Array
        $plugins = $this->loadPluginsArray();
        
        // Setze enabled auf false
        if (isset($plugins[$module_key])) {
            $plugins[$module_key]['enabled'] = false;
        } else {
            error_log("ModuleManager: Modul '$module_key' existiert nicht in der Konfiguration");
        }
        
        // Speichere das aktualisierte Array
        $this->savePluginsArray($plugins);
    }
    
    /**
     * Speichert das $plugins Array in sys.conf.php
     * Verwendet die gleiche Methode wie system.php für Kompatibilität
     */
    private function savePluginsArray($plugins) {
        if (!file_exists($this->config_file)) {
            throw new Exception("Config-Datei nicht gefunden");
        }
        
        // Backup erstellen
        $backup_path = $this->config_file . '.bnk';
        copy($this->config_file, $backup_path);
        
        // Lese aktuelle Config
        $config_content = file_get_contents($this->config_file);
        
        // Exportiere das $plugins Array (wie in system.php)
        $plugin_export = var_export($plugins, true);
        
        // Ersetze den PLUGINS-Block (wie in system.php)
        $new_config = preg_replace(
            '/\/\/ --- PLUGINS START ---.*?\/\/ --- PLUGINS END ---/s',
            "// --- PLUGINS START ---\n\$plugins = $plugin_export;\n// --- PLUGINS END ---",
            $config_content
        );
        
        if ($new_config === null || $new_config === $config_content) {
            throw new Exception("Fehler beim Aktualisieren der Plugin-Konfiguration");
        }
        
        // Schreibe Config zurück
        $result = file_put_contents($this->config_file, $new_config);
        
        if ($result === false) {
            // Restore Backup bei Fehler
            copy($backup_path, $this->config_file);
            throw new Exception("Fehler beim Schreiben der Config-Datei");
        }
        
        return true;
    }
    
    /**
     * Ruft einen Callback eines Moduls auf
     */
    private function callModuleCallback($module_key, $callback) {
        $module_file = $this->module_dir . $module_key . '/Module.php';
        
        if (!file_exists($module_file)) {
            return;
        }
        
        require_once $module_file;
        
        // Bestimme Klassenname
        $class_name = $this->getClassNameFromKey($module_key);
        
        if (class_exists($class_name)) {
            $module = new $class_name($module_key);
            if (method_exists($module, $callback)) {
                $module->$callback();
            }
        }
    }
    
    /**
     * Konvertiert Module-Keys zu korrekten PHP-Klassennamen
     */
    private function getClassNameFromKey($key) {
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
        
        return ucfirst($key) . 'Module';
    }
    
    /**
     * Löscht ein Verzeichnis rekursiv
     */
    private function deleteDirectory($dir) {
        if (!file_exists($dir)) {
            return true;
        }
        
        if (!is_dir($dir)) {
            return unlink($dir);
        }
        
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            
            if (!$this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * Vergleicht Versionen
     */
    public function compareVersions($version1, $version2) {
        return version_compare($version1, $version2);
    }
    
    /**
     * Installiert ein Modul von GitHub
     */
    public function installFromGitHub($module_key) {
        // Installation über GitHubInstaller
        $result = $this->github_installer->installFromGitHub($module_key);
        
        // Lese module.json vom installierten Modul
        $module_info = $this->getModuleInfo($module_key);
        if (!$module_info) {
            throw new Exception("Modul wurde installiert aber module.json fehlt!");
        }
        
        // Füge zu sys.conf.php hinzu (enabled = false beim Installieren!)
        $this->addModuleToConfig($module_key, $module_info, false);
        
        // Logge die Installation
        if (function_exists('logActivity')) {
            logActivity("Modul '$module_key' von GitHub installiert", 'INFO');
        }
        
        return $result;
    }
    
    /**
     * Aktualisiert ein Modul von GitHub
     */
    public function updateFromGitHub($module_key) {
        // Speichere ob Modul aktiviert war
        $was_enabled = $this->isModuleEnabled($module_key);
        
        // Update ist wie Installation - lädt neue Dateien
        $result = $this->github_installer->updateFromGitHub($module_key);
        
        // Lese module.json vom aktualisierten Modul
        $module_info = $this->getModuleInfo($module_key);
        if (!$module_info) {
            throw new Exception("Modul wurde aktualisiert aber module.json fehlt!");
        }
        
        // Update Config mit neuen Infos (behalte enabled-Status)
        $this->addModuleToConfig($module_key, $module_info, $was_enabled);
        
        // Logge das Update
        if (function_exists('logActivity')) {
            logActivity("Modul '$module_key' von GitHub aktualisiert", 'INFO');
        }
        
        return $result;
    }
    
    /**
     * Prüft ob ein Update von GitHub verfügbar ist
     */
    public function checkGitHubUpdate($module_key) {
        return $this->github_installer->checkForUpdate($module_key);
    }
    
    /**
     * Gibt alle verfügbaren Module aus dem GitHub-Katalog zurück
     */
    public function getGitHubCatalog() {
        return $this->github_installer->getAvailableModules();
    }
    
    /**
     * Lädt den GitHub-Katalog neu
     */
    public function refreshGitHubCatalog() {
        return $this->github_installer->loadCatalog(true);
    }
}

