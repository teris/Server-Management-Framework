<?php
/**
 * Terminal Module Uninstaller
 * Entfernt alle installierten Komponenten
 */

class TerminalModuleUninstaller {
    private $modulePath;
    private $publicPath;
    private $removedFiles = [];
    private $removedDirectories = [];
    private $errors = [];
    private $warnings = [];
    
    public function __construct() {
        $this->modulePath = dirname(__FILE__);
        $this->publicPath = dirname(dirname(dirname(__FILE__))) . '/public';
    }
    
    /**
     * Hauptdeinstallation
     */
    public function uninstall() {
        echo "<h2>Terminal Module Deinstallation</h2>\n";
        
        // 1. Libraries entfernen
        $this->removeLibraries();
        
        // 2. WebSocket-Proxies entfernen
        $this->removeWebSocketProxies();
        
        // 3. Konfigurationsdateien entfernen
        $this->removeConfigurations();
        
        // 4. Datenbanktabellen entfernen
        $this->removeDatabaseTables();
        
        // 5. Logs entfernen
        $this->removeLogs();
        
        // 6. Deinstallation abschließen
        $this->finalizeUninstall();
        
        return [
            'removed_files' => $this->removedFiles,
            'removed_directories' => $this->removedDirectories,
            'errors' => $this->errors,
            'warnings' => $this->warnings
        ];
    }
    
    /**
     * Entfernt alle Libraries
     */
    private function removeLibraries() {
        echo "<h3>1. Libraries entfernen...</h3>\n";
        
        $libraries = [
            $this->publicPath . '/assets/novnc',
            $this->publicPath . '/assets/xtermjs'
        ];
        
        foreach ($libraries as $library) {
            if (is_dir($library)) {
                if ($this->removeDirectory($library)) {
                    $this->removedDirectories[] = basename($library);
                    echo "✓ Verzeichnis entfernt: " . basename($library) . "\n";
                } else {
                    $this->errors[] = "Fehler beim Entfernen von: " . $library;
                    echo "✗ Fehler beim Entfernen von: " . basename($library) . "\n";
                }
            } else {
                echo "ℹ Verzeichnis existiert nicht: " . basename($library) . "\n";
            }
        }
    }
    
    /**
     * Entfernt WebSocket-Proxies
     */
    private function removeWebSocketProxies() {
        echo "<h3>2. WebSocket-Proxies entfernen...</h3>\n";
        
        $proxies = [
            $this->publicPath . '/websockify',
            $this->publicPath . '/ssh-proxy'
        ];
        
        foreach ($proxies as $proxy) {
            if (is_dir($proxy)) {
                if ($this->removeDirectory($proxy)) {
                    $this->removedDirectories[] = basename($proxy);
                    echo "✓ Proxy entfernt: " . basename($proxy) . "\n";
                } else {
                    $this->errors[] = "Fehler beim Entfernen von: " . $proxy;
                    echo "✗ Fehler beim Entfernen von: " . basename($proxy) . "\n";
                }
            } else {
                echo "ℹ Proxy existiert nicht: " . basename($proxy) . "\n";
            }
        }
    }
    
    /**
     * Entfernt Konfigurationsdateien
     */
    private function removeConfigurations() {
        echo "<h3>3. Konfigurationsdateien entfernen...</h3>\n";
        
        $configFiles = [
            $this->modulePath . '/config/config.php',
            $this->modulePath . '/install.log',
            $this->modulePath . '/update.log'
        ];
        
        foreach ($configFiles as $file) {
            if (file_exists($file)) {
                if (unlink($file)) {
                    $this->removedFiles[] = basename($file);
                    echo "✓ Datei entfernt: " . basename($file) . "\n";
                } else {
                    $this->errors[] = "Fehler beim Entfernen von: " . $file;
                    echo "✗ Fehler beim Entfernen von: " . basename($file) . "\n";
                }
            } else {
                echo "ℹ Datei existiert nicht: " . basename($file) . "\n";
            }
        }
        
        // Config-Verzeichnis entfernen
        $configDir = $this->modulePath . '/config';
        if (is_dir($configDir)) {
            if ($this->removeDirectory($configDir)) {
                $this->removedDirectories[] = 'config';
                echo "✓ Config-Verzeichnis entfernt\n";
            }
        }
    }
    
    /**
     * Entfernt Datenbanktabellen
     */
    private function removeDatabaseTables() {
        echo "<h3>4. Datenbanktabellen entfernen...</h3>\n";
        
        try {
            $db = DatabaseManager::getInstance();
            
            $tables = [
                'terminal_sessions',
                'terminal_vnc_servers',
                'terminal_ssh_servers'
            ];
            
            foreach ($tables as $table) {
                $result = $db->query("DROP TABLE IF EXISTS $table");
                if ($result) {
                    echo "✓ Tabelle entfernt: $table\n";
                } else {
                    $this->warnings[] = "Tabelle konnte nicht entfernt werden: $table";
                    echo "⚠ Tabelle konnte nicht entfernt werden: $table\n";
                }
            }
            
        } catch (Exception $e) {
            $this->errors[] = "Datenbankfehler: " . $e->getMessage();
            echo "✗ Datenbankfehler: " . $e->getMessage() . "\n";
        }
    }
    
    /**
     * Entfernt Log-Dateien
     */
    private function removeLogs() {
        echo "<h3>5. Log-Dateien entfernen...</h3>\n";
        
        $logFiles = [
            $this->modulePath . '/install.log',
            $this->modulePath . '/update.log',
            $this->modulePath . '/uninstall.log'
        ];
        
        foreach ($logFiles as $file) {
            if (file_exists($file)) {
                if (unlink($file)) {
                    $this->removedFiles[] = basename($file);
                    echo "✓ Log entfernt: " . basename($file) . "\n";
                } else {
                    $this->warnings[] = "Log konnte nicht entfernt werden: " . $file;
                    echo "⚠ Log konnte nicht entfernt werden: " . basename($file) . "\n";
                }
            }
        }
    }
    
    /**
     * Schließt die Deinstallation ab
     */
    private function finalizeUninstall() {
        echo "<h3>6. Deinstallation abschließen...</h3>\n";
        
        // Deinstallation-Log erstellen
        $logContent = "Terminal Module Deinstallation abgeschlossen am " . date('Y-m-d H:i:s') . "\n";
        $logContent .= "Entfernte Dateien: " . count($this->removedFiles) . "\n";
        $logContent .= "Entfernte Verzeichnisse: " . count($this->removedDirectories) . "\n";
        $logContent .= "Fehler: " . count($this->errors) . "\n";
        $logContent .= "Warnungen: " . count($this->warnings) . "\n";
        
        file_put_contents($this->modulePath . '/uninstall.log', $logContent);
        
        echo "✓ Deinstallation abgeschlossen\n";
    }
    
    /**
     * Entfernt ein Verzeichnis rekursiv
     */
    private function removeDirectory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * Prüft ob das Modul vollständig deinstalliert werden kann
     */
    public function canUninstall() {
        $checks = [
            'novnc_removable' => !is_dir($this->publicPath . '/assets/novnc'),
            'xtermjs_removable' => !is_dir($this->publicPath . '/assets/xtermjs'),
            'proxies_removable' => !is_dir($this->publicPath . '/websockify') && !is_dir($this->publicPath . '/ssh-proxy'),
            'config_removable' => !is_dir($this->modulePath . '/config')
        ];
        
        return [
            'can_uninstall' => !in_array(false, $checks),
            'checks' => $checks
        ];
    }
}
?>
