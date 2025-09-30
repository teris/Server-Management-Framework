<?php
/**
 * GitHub Module Installer
 * Installiert Module direkt von GitHub basierend auf dem modules.json Katalog
 * 
 * @author Teris
 * @version 1.0.0
 */

class GitHubModuleInstaller {
    private $catalog_url = 'https://raw.githubusercontent.com/teris/SMF-Module/main/modules.json';
    private $local_catalog_file;
    private $module_dir;
    private $catalog_cache_file;
    private $cache_ttl = 3600; // 1 Stunde Cache
    
    public function __construct() {
        $this->module_dir = dirname(__DIR__) . '/module/';
        $this->local_catalog_file = dirname(__DIR__) . '/module/modules.json';
        $this->catalog_cache_file = dirname(__DIR__) . '/cache/github_modules_catalog.json';
        
        // Cache-Verzeichnis erstellen
        $cache_dir = dirname($this->catalog_cache_file);
        if (!is_dir($cache_dir)) {
            @mkdir($cache_dir, 0755, true);
        }
    }
    
    /**
     * Lädt den Modul-Katalog (lokal oder von GitHub)
     */
    public function loadCatalog($force_refresh = false) {
        // ERSTE PRIORITÄT: Lokale Katalog-Datei (wie in test/)
        if (file_exists($this->local_catalog_file)) {
            $content = file_get_contents($this->local_catalog_file);
            $data = json_decode($content, true);
            if ($data !== null) {
                error_log('GitHubModuleInstaller: Lokale Katalog-Datei verwendet');
                return $data;
            }
        }
        
        // ZWEITE PRIORITÄT: Cache prüfen
        if (!$force_refresh && file_exists($this->catalog_cache_file)) {
            $cache_age = time() - filemtime($this->catalog_cache_file);
            if ($cache_age < $this->cache_ttl) {
                $content = file_get_contents($this->catalog_cache_file);
                $data = json_decode($content, true);
                if ($data !== null) {
                    error_log('GitHubModuleInstaller: Cache verwendet');
                    return $data;
                }
            }
        }
        
        // DRITTE PRIORITÄT: Von GitHub laden
        error_log('GitHubModuleInstaller: Versuche von GitHub zu laden: ' . $this->catalog_url);
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'SMF-Module-Manager/1.0'
            ]
        ]);
        
        $content = @file_get_contents($this->catalog_url, false, $context);
        
        if ($content !== false) {
            $data = json_decode($content, true);
            if ($data !== null) {
                // Speichere im Cache
                @file_put_contents($this->catalog_cache_file, $content);
                error_log('GitHubModuleInstaller: Von GitHub geladen und gecacht');
                return $data;
            }
        }
        
        // VIERTE PRIORITÄT: Veralteter Cache
        error_log('GitHubModuleInstaller: GitHub nicht erreichbar, versuche veralteten Cache');
        if (file_exists($this->catalog_cache_file)) {
            $content = file_get_contents($this->catalog_cache_file);
            $data = json_decode($content, true);
            if ($data !== null) {
                error_log('GitHubModuleInstaller: Veralteter Cache verwendet');
                return $data;
            }
        }
        
        // FALLBACK: Leeres Array
        error_log('GitHubModuleInstaller: Kein Katalog verfügbar, gebe leeres Array zurück');
        return [];
    }
    
    /**
     * Installiert ein Modul von GitHub (wie test/install.php)
     */
    public function installFromGitHub($module_key) {
        $catalog = $this->loadCatalog();
        
        if (!isset($catalog[$module_key])) {
            throw new Exception("Modul '$module_key' nicht im Katalog gefunden");
        }
        
        $module = $catalog[$module_key];
        
        // PHP-Version prüfen
        if (isset($module['min_php']) && version_compare(PHP_VERSION, $module['min_php'], '<')) {
            throw new Exception("Modul benötigt mindestens PHP " . $module['min_php']);
        }
        
        // Temp-Datei (wie in test/install.php)
        $tmp_zip = sys_get_temp_dir() . "/{$module_key}.zip";
        
        // ZIP herunterladen (wie in test/install.php)
        $zip_content = @file_get_contents($module['download']);
        if ($zip_content === false) {
            throw new Exception('Fehler beim Herunterladen von GitHub');
        }
        file_put_contents($tmp_zip, $zip_content);
        
        // Entpacken in temporäres Verzeichnis (wie in test/install.php)
        $tmp_extract = sys_get_temp_dir() . "/{$module_key}_extract/";
        if (!file_exists($tmp_extract)) {
            mkdir($tmp_extract, 0777, true);
        }
        
        $zip = new ZipArchive();
        if ($zip->open($tmp_zip) !== TRUE) {
            unlink($tmp_zip);
            throw new Exception('Fehler beim Entpacken');
        }
        $zip->extractTo($tmp_extract);
        $zip->close();
        unlink($tmp_zip);
        
        // Den Unterordner verschieben (wie in test/install.php)
        $source = $tmp_extract . $module['path_in_archive'];
        $target = $this->module_dir . $module_key;
        
        // Prüfe ob Quelle existiert
        if (!is_dir($source)) {
            $this->deleteDirectory($tmp_extract);
            throw new Exception("Modul-Pfad nicht gefunden: " . $module['path_in_archive']);
        }
        
        // Falls altes Modul existiert, löschen (wie in test/install.php)
        if (is_dir($target)) {
            $backup_path = $target . '.bnk';
            if (file_exists($backup_path)) {
                $this->deleteDirectory($backup_path);
            }
            rename($target, $backup_path);
        }
        
        // Verschieben (wie in test/install.php)
        if (!rename($source, $target)) {
            // Restore Backup
            if (isset($backup_path) && file_exists($backup_path)) {
                rename($backup_path, $target);
            }
            $this->deleteDirectory($tmp_extract);
            throw new Exception('Fehler beim Installieren');
        }
        
        // Temp-Verzeichnis aufräumen (wie in test/install.php)
        $this->deleteDirectory($tmp_extract);
        
        // Lese module.json vom installierten Modul
        $module_info = $this->getModuleInfo($module_key);
        if (!$module_info) {
            throw new Exception("module.json konnte nicht gelesen werden nach Installation");
        }
        
        // Logge die Installation
        if (function_exists('logActivity')) {
            logActivity("Modul '$module_key' von GitHub installiert", 'INFO');
        }
        
        return [
            'key' => $module_key,
            'name' => $module_info['name'] ?? $module['name'],
            'version' => $module_info['version'] ?? $module['version'],
            'description' => $module_info['description'] ?? ''
        ];
    }
    
    /**
     * Aktualisiert ein Modul von GitHub (ist identisch mit Installation)
     */
    public function updateFromGitHub($module_key) {
        return $this->installFromGitHub($module_key);
    }
    
    /**
     * Liest module.json vom installierten Modul
     */
    private function getModuleInfo($module_key) {
        $json_file = $this->module_dir . $module_key . '/module.json';
        
        if (!file_exists($json_file)) {
            return null;
        }
        
        $content = file_get_contents($json_file);
        return json_decode($content, true);
    }
    
    /**
     * Prüft ob ein Update verfügbar ist
     */
    public function checkForUpdate($module_key) {
        try {
            // Lade lokale Version
            $local_json = $this->module_dir . $module_key . '/module.json';
            if (!file_exists($local_json)) {
                return null;
            }
            
            $local_info = json_decode(file_get_contents($local_json), true);
            $local_version = $local_info['version'] ?? '0.0.0';
            
            // Lade GitHub-Katalog
            $catalog = $this->loadCatalog();
            if (!isset($catalog[$module_key])) {
                return null;
            }
            
            $github_version = $catalog[$module_key]['version'] ?? '0.0.0';
            
            // Vergleiche Versionen
            if (version_compare($github_version, $local_version, '>')) {
                return [
                    'update_available' => true,
                    'local_version' => $local_version,
                    'github_version' => $github_version,
                    'module_info' => $catalog[$module_key]
                ];
            }
            
            return [
                'update_available' => false,
                'local_version' => $local_version,
                'github_version' => $github_version
            ];
            
        } catch (Exception $e) {
            error_log('GitHubModuleInstaller: Error checking update for ' . $module_key . ': ' . $e->getMessage());
            return null;
        }
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
     * Gibt alle verfügbaren Module aus dem GitHub-Katalog zurück
     */
    public function getAvailableModules() {
        try {
            return $this->loadCatalog();
        } catch (Exception $e) {
            return [];
        }
    }
}

