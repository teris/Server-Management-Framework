<?php
/**
 * Terminal Module Updater
 * Aktualisiert externe Libraries und Komponenten
 */

class TerminalModuleUpdater {
    private $modulePath;
    private $publicPath;
    private $updateLog = [];
    private $errors = [];
    
    // Versionsinformationen
    private $libraryVersions = [
        'novnc' => [
            'current' => null,
            'latest' => null,
            'url' => 'https://api.github.com/repos/novnc/noVNC/releases/latest',
            'download_url' => 'https://github.com/novnc/noVNC/archive/refs/tags/',
            'path' => '/public/assets/novnc'
        ],
        'xtermjs' => [
            'current' => null,
            'latest' => null,
            'url' => 'https://api.github.com/repos/xtermjs/xterm.js/releases/latest',
            'download_url' => 'https://github.com/xtermjs/xterm.js/releases/download/',
            'path' => '/public/assets/xtermjs'
        ]
    ];
    
    public function __construct() {
        $this->modulePath = dirname(__FILE__);
        $this->publicPath = dirname(dirname(dirname(__FILE__))) . '/public';
        $this->loadCurrentVersions();
    }
    
    /**
     * Lädt aktuelle Versionen
     */
    private function loadCurrentVersions() {
        // noVNC Version laden
        $novncPath = $this->publicPath . '/assets/novnc/package.json';
        if (file_exists($novncPath)) {
            $package = json_decode(file_get_contents($novncPath), true);
            $this->libraryVersions['novnc']['current'] = $package['version'] ?? 'unknown';
        }
        
        // xterm.js Version laden
        $xtermPath = $this->publicPath . '/assets/xtermjs/package.json';
        if (file_exists($xtermPath)) {
            $package = json_decode(file_get_contents($xtermPath), true);
            $this->libraryVersions['xtermjs']['current'] = $package['version'] ?? 'unknown';
        }
    }
    
    /**
     * Prüft auf verfügbare Updates
     */
    public function checkForUpdates() {
        echo "<h2>Update-Prüfung</h2>\n";
        
        $updates = [];
        $hasUpdates = false;
        
        foreach ($this->libraryVersions as $library => $info) {
            echo "<h3>Prüfe $library...</h3>\n";
            
            try {
                $latestVersion = $this->getLatestVersion($library);
                $this->libraryVersions[$library]['latest'] = $latestVersion;
                
                $needsUpdate = $this->compareVersions($info['current'], $latestVersion);
                
                $updates[$library] = [
                    'current' => $info['current'],
                    'latest' => $latestVersion,
                    'needs_update' => $needsUpdate,
                    'update_available' => $needsUpdate
                ];
                
                if ($needsUpdate) {
                    $hasUpdates = true;
                    echo "✓ Update verfügbar: {$info['current']} → $latestVersion\n";
                } else {
                    echo "ℹ Aktuell: $latestVersion\n";
                }
                
            } catch (Exception $e) {
                $this->errors[] = "Fehler beim Prüfen von $library: " . $e->getMessage();
                echo "✗ Fehler: " . $e->getMessage() . "\n";
                
                $updates[$library] = [
                    'current' => $info['current'],
                    'latest' => 'unknown',
                    'needs_update' => false,
                    'update_available' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'has_updates' => $hasUpdates,
            'libraries' => $updates,
            'errors' => $this->errors
        ];
    }
    
    /**
     * Aktualisiert alle Libraries
     */
    public function updateLibraries($data = []) {
        echo "<h2>Library-Updates</h2>\n";
        
        $updateResults = [];
        $librariesToUpdate = $data['libraries'] ?? array_keys($this->libraryVersions);
        
        foreach ($librariesToUpdate as $library) {
            if (!isset($this->libraryVersions[$library])) {
                continue;
            }
            
            echo "<h3>Update $library...</h3>\n";
            
            try {
                $result = $this->updateLibrary($library);
                $updateResults[$library] = $result;
                
                if ($result['success']) {
                    echo "✓ $library erfolgreich aktualisiert\n";
                } else {
                    echo "✗ $library Update fehlgeschlagen: " . $result['error'] . "\n";
                }
                
            } catch (Exception $e) {
                $this->errors[] = "Fehler beim Update von $library: " . $e->getMessage();
                echo "✗ Fehler: " . $e->getMessage() . "\n";
                
                $updateResults[$library] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // Update-Log speichern
        $this->saveUpdateLog($updateResults);
        
        return [
            'results' => $updateResults,
            'errors' => $this->errors,
            'success' => empty($this->errors)
        ];
    }
    
    /**
     * Aktualisiert eine einzelne Library
     */
    private function updateLibrary($library) {
        $info = $this->libraryVersions[$library];
        
        if (!$info['latest']) {
            $info['latest'] = $this->getLatestVersion($library);
        }
        
        // Backup erstellen
        $backupPath = $this->createBackup($library);
        
        try {
            // Alte Version entfernen
            $libraryPath = $this->publicPath . $info['path'];
            if (is_dir($libraryPath)) {
                $this->removeDirectory($libraryPath);
            }
            
            // Neue Version herunterladen
            $downloadUrl = $this->getDownloadUrl($library, $info['latest']);
            $zipPath = $this->downloadLibrary($library, $downloadUrl);
            
            // Entpacken
            $extractPath = $this->publicPath . $info['path'];
            $this->extractLibrary($zipPath, $extractPath);
            
            // Zip-Datei löschen
            unlink($zipPath);
            
            // Version aktualisieren
            $this->libraryVersions[$library]['current'] = $info['latest'];
            
            return [
                'success' => true,
                'old_version' => $info['current'],
                'new_version' => $info['latest'],
                'backup_path' => $backupPath
            ];
            
        } catch (Exception $e) {
            // Bei Fehler: Backup wiederherstellen
            if ($backupPath && is_dir($backupPath)) {
                $this->restoreBackup($library, $backupPath);
            }
            
            throw $e;
        }
    }
    
    /**
     * Holt die neueste Version einer Library
     */
    private function getLatestVersion($library) {
        $info = $this->libraryVersions[$library];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $info['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Terminal-Module-Updater/1.0');
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$response) {
            throw new Exception("Fehler beim Abrufen der Version: HTTP $httpCode");
        }
        
        $data = json_decode($response, true);
        if (!$data || !isset($data['tag_name'])) {
            throw new Exception("Ungültige API-Antwort");
        }
        
        return ltrim($data['tag_name'], 'v'); // 'v' entfernen
    }
    
    /**
     * Vergleicht Versionen
     */
    private function compareVersions($current, $latest) {
        if ($current === 'unknown' || $current === null) {
            return true; // Wenn Version unbekannt, Update durchführen
        }
        
        return version_compare($current, $latest, '<');
    }
    
    /**
     * Erstellt ein Backup einer Library
     */
    private function createBackup($library) {
        $sourcePath = $this->publicPath . $this->libraryVersions[$library]['path'];
        $backupPath = $this->modulePath . '/backups/' . $library . '_' . date('Y-m-d_H-i-s');
        
        if (!is_dir($sourcePath)) {
            return null;
        }
        
        if (!is_dir(dirname($backupPath))) {
            mkdir(dirname($backupPath), 0755, true);
        }
        
        $this->copyDirectory($sourcePath, $backupPath);
        
        return $backupPath;
    }
    
    /**
     * Stellt ein Backup wieder her
     */
    private function restoreBackup($library, $backupPath) {
        $targetPath = $this->publicPath . $this->libraryVersions[$library]['path'];
        
        if (is_dir($targetPath)) {
            $this->removeDirectory($targetPath);
        }
        
        $this->copyDirectory($backupPath, $targetPath);
    }
    
    /**
     * Holt Download-URL für eine Library
     */
    private function getDownloadUrl($library, $version) {
        $info = $this->libraryVersions[$library];
        
        switch ($library) {
            case 'novnc':
                return $info['download_url'] . 'v' . $version . '.zip';
            case 'xtermjs':
                return $info['download_url'] . 'v' . $version . '/xterm-' . $version . '.zip';
            default:
                throw new Exception("Unbekannte Library: $library");
        }
    }
    
    /**
     * Lädt eine Library herunter
     */
    private function downloadLibrary($library, $url) {
        $zipPath = $this->modulePath . '/temp/' . $library . '_' . time() . '.zip';
        
        if (!is_dir(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Terminal-Module-Updater/1.0');
        
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$data) {
            throw new Exception("Download fehlgeschlagen: HTTP $httpCode");
        }
        
        if (file_put_contents($zipPath, $data) === false) {
            throw new Exception("Fehler beim Speichern der Datei");
        }
        
        return $zipPath;
    }
    
    /**
     * Entpackt eine Library
     */
    private function extractLibrary($zipPath, $extractPath) {
        if (!class_exists('ZipArchive')) {
            throw new Exception("ZipArchive-Klasse nicht verfügbar");
        }
        
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== TRUE) {
            throw new Exception("Fehler beim Öffnen der ZIP-Datei");
        }
        
        if (!is_dir($extractPath)) {
            mkdir($extractPath, 0755, true);
        }
        
        $zip->extractTo($extractPath);
        $zip->close();
    }
    
    /**
     * Kopiert ein Verzeichnis rekursiv
     */
    private function copyDirectory($source, $destination) {
        if (!is_dir($source)) {
            return false;
        }
        
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        
        $files = array_diff(scandir($source), ['.', '..']);
        
        foreach ($files as $file) {
            $srcPath = $source . '/' . $file;
            $destPath = $destination . '/' . $file;
            
            if (is_dir($srcPath)) {
                $this->copyDirectory($srcPath, $destPath);
            } else {
                copy($srcPath, $destPath);
            }
        }
        
        return true;
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
     * Speichert Update-Log
     */
    private function saveUpdateLog($results) {
        $logContent = "Terminal Module Update am " . date('Y-m-d H:i:s') . "\n";
        $logContent .= "Ergebnisse:\n";
        
        foreach ($results as $library => $result) {
            $logContent .= "- $library: " . ($result['success'] ? 'Erfolgreich' : 'Fehlgeschlagen') . "\n";
            if (!$result['success']) {
                $logContent .= "  Fehler: " . $result['error'] . "\n";
            }
        }
        
        file_put_contents($this->modulePath . '/update.log', $logContent);
    }
}
?>
