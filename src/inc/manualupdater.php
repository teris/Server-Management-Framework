<?php
// @intelephense-ignore-file
/**
 * Server Management Framework
 * 
 * @author Teris
 * @version 3.1.2
 */

// Sicherheitscheck - wird von index.php gesetzt
if (!defined('FRAMEWORK_LOADED')) {
    define('FRAMEWORK_LOADED', true);
}

// GitHub Repository Konfiguration
define('GITHUB_REPO', 'teris/Server-Management-Framework');
define('GITHUB_API_BASE', 'https://api.github.com/repos/' . GITHUB_REPO);

class ManualUpdater {
    private $currentVersion;
    private $changelogVersion;
    private $githubVersion;
    private $isNightly = false;
    private $updateType = 'framework'; // 'framework' oder 'full'
    
    public function __construct() {
        $this->currentVersion = $this->getCurrentVersion();
        $this->changelogVersion = $this->getChangelogVersion();
        $this->checkVersionConsistency();
    }
    
    /**
     * Aktuelle Version aus sys.conf.php abrufen
     */
    private function getCurrentVersion() {
        global $system_config;
        return $system_config['version'] ?? '0.0.0';
    }
    
    /**
     * Version aus CHANGELOG.md parsen
     */
    private function getChangelogVersion() {
        $changelogPath = __DIR__ . '/../../CHANGELOG.md';
        if (!file_exists($changelogPath)) {
            return '0.0.0';
        }
        
        $content = file_get_contents($changelogPath);
        if (preg_match('/## \[([0-9]+\.[0-9]+\.[0-9]+)\]/', $content, $matches)) {
            return $matches[1];
        }
        
        return '0.0.0';
    }
    
    /**
     * Prüft ob die Versionen übereinstimmen
     */
    private function checkVersionConsistency() {
        $this->isNightly = version_compare($this->currentVersion, $this->changelogVersion, '!=');
    }
    
    /**
     * GitHub API aufrufen um neueste Version zu prüfen
     */
    public function checkForUpdates() {
        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'User-Agent: Server-Management-Framework-Updater/1.0',
                        'Accept: application/vnd.github.v3+json'
                    ],
                    'timeout' => 30
                ]
            ]);
            
            $response = file_get_contents(GITHUB_API_BASE . '/releases/latest', false, $context);
            
            if ($response === false) {
                throw new Exception('GitHub API nicht erreichbar');
            }
            
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Ungültige JSON-Antwort von GitHub');
            }
            
            if (isset($data['tag_name'])) {
                $this->githubVersion = ltrim($data['tag_name'], 'v');
                return [
                    'success' => true,
                    'current_version' => $this->currentVersion,
                    'changelog_version' => $this->changelogVersion,
                    'github_version' => $this->githubVersion,
                    'is_nightly' => $this->isNightly,
                    'update_available' => version_compare($this->githubVersion, $this->currentVersion, '>'),
                    'release_data' => $data
                ];
            } else {
                throw new Exception('Keine Release-Informationen gefunden');
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'current_version' => $this->currentVersion,
                'changelog_version' => $this->changelogVersion,
                'is_nightly' => $this->isNightly
            ];
        }
    }
    
    /**
     * Download eines Releases von GitHub
     */
    public function downloadRelease($tagName, $updateType = 'framework') {
        $this->updateType = $updateType;
        
        try {
            // Release-Informationen abrufen
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'User-Agent: Server-Management-Framework-Updater/1.0',
                        'Accept: application/vnd.github.v3+json'
                    ],
                    'timeout' => 30
                ]
            ]);
            
            $response = file_get_contents(GITHUB_API_BASE . '/releases/tags/' . $tagName, false, $context);
            
            if ($response === false) {
                throw new Exception('Release-Informationen nicht abrufbar');
            }
            
            $releaseData = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Ungültige Release-Daten');
            }
            
            // Asset-URL bestimmen basierend auf Update-Typ
            $assetUrl = null;
            $assetName = null;
            
            if ($updateType === 'framework') {
                // Framework-Only Update
                foreach ($releaseData['assets'] as $asset) {
                    if (strpos($asset['name'], 'framework-standalone.zip') !== false) {
                        $assetUrl = $asset['browser_download_url'];
                        $assetName = $asset['name'];
                        break;
                    }
                }
            } else {
                // Vollständiges Update
                foreach ($releaseData['assets'] as $asset) {
                    if (strpos($asset['name'], '.zip') !== false && 
                        strpos($asset['name'], 'framework-standalone') === false) {
                        $assetUrl = $asset['browser_download_url'];
                        $assetName = $asset['name'];
                        break;
                    }
                }
            }
            
            if (!$assetUrl) {
                throw new Exception('Kein passendes Asset für Update-Typ gefunden');
            }
            
            // Download durchführen
            $downloadPath = sys_get_temp_dir() . '/' . $assetName;
            $this->downloadFile($assetUrl, $downloadPath);
            
            return [
                'success' => true,
                'download_path' => $downloadPath,
                'asset_name' => $assetName,
                'release_tag' => $tagName,
                'update_type' => $updateType
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Datei von URL herunterladen
     */
    private function downloadFile($url, $destination) {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: Server-Management-Framework-Updater/1.0'
                ],
                'timeout' => 300 // 5 Minuten
            ]
        ]);
        
        $data = file_get_contents($url, false, $context);
        
        if ($data === false) {
            throw new Exception('Download fehlgeschlagen');
        }
        
        if (file_put_contents($destination, $data) === false) {
            throw new Exception(t('file_could_not_be_saved'));
        }
    }
    
    /**
     * Update installieren
     */
    public function installUpdate($downloadPath, $backupPath = null) {
        try {
            // Backup erstellen falls gewünscht
            if ($backupPath) {
                $this->createBackup($backupPath);
            }
            
            // ZIP-Datei entpacken
            $zip = new ZipArchive();
            $result = $zip->open($downloadPath);
            
            if ($result !== TRUE) {
                throw new Exception('ZIP-Datei konnte nicht geöffnet werden: ' . $result);
            }
            
            // Entpacken basierend auf Update-Typ
            if ($this->updateType === 'framework') {
                $this->extractFrameworkOnly($zip);
            } else {
                $this->extractFullUpdate($zip);
            }
            
            $zip->close();
            
            // Version in sys.conf.php aktualisieren
            $this->updateVersionInConfig();
            
            // Temporäre Datei löschen
            unlink($downloadPath);
            
            return [
                'success' => true,
                'message' => t('update_successfully_installed')
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Erstellt ein manuelles Backup
     */
    public function createManualBackup($includeDatabase = true, $simpleBackup = false) {
        try {
            $backupDir = __DIR__ . '/../../backups';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }
            
            $timestamp = date('Y-m-d_H-i-s');
            $backupName = "manual_backup_{$timestamp}";
            $tempBackupPath = $backupDir . '/' . $backupName;
            $zipPath = $backupDir . '/' . $backupName . '.zip';
            
            // Temporäres Backup-Verzeichnis erstellen
            if (!mkdir($tempBackupPath, 0755, true)) {
                throw new Exception('Backup-Verzeichnis konnte nicht erstellt werden');
            }
            
            $results = [];
            
            // Dateien-Backup
            try {
                if ($simpleBackup) {
                    $filesBackup = $this->backupEssentialFiles($tempBackupPath);
                } else {
                    $filesBackup = $this->backupFiles($tempBackupPath);
                }
                $results['files'] = $filesBackup;
                
                if (!$filesBackup['success']) {
                    throw new Exception('Dateien-Backup fehlgeschlagen: ' . $filesBackup['error']);
                }
            } catch (Exception $e) {
                throw new Exception('Dateien-Backup Fehler: ' . $e->getMessage());
            }
            
            // Datenbank-Backup
            if ($includeDatabase) {
                try {
                    $dbBackup = $this->backupDatabase($tempBackupPath);
                    $results['database'] = $dbBackup;
                    
                    if (!$dbBackup['success']) {
                        throw new Exception('Datenbank-Backup fehlgeschlagen: ' . $dbBackup['error']);
                    }
                } catch (Exception $e) {
                    throw new Exception('Datenbank-Backup Fehler: ' . $e->getMessage());
                }
            }
            
            // Backup-Info erstellen
            $backupInfo = [
                'created' => date('Y-m-d H:i:s'),
                'type' => 'manual',
                'include_database' => $includeDatabase,
                'simple_backup' => $simpleBackup,
                'files_count' => $filesBackup['files_count'] ?? 0,
                'database_size' => $includeDatabase ? ($dbBackup['size'] ?? 0) : 0,
                'total_size' => $this->getDirectorySize($tempBackupPath)
            ];
            
            file_put_contents($tempBackupPath . '/backup_info.json', json_encode($backupInfo, JSON_PRETTY_PRINT));
            
            // ZIP-Datei erstellen
            $zipResult = $this->createZipFromDirectory($tempBackupPath, $zipPath);
            if (!$zipResult['success']) {
                // Temporäres Verzeichnis aufräumen bei ZIP-Fehler
                $this->deleteDirectory($tempBackupPath);
                throw new Exception('ZIP-Erstellung fehlgeschlagen: ' . $zipResult['error']);
            }
            
            // Prüfen ob ZIP-Datei erstellt wurde
            if (!file_exists($zipPath) || filesize($zipPath) === 0) {
                $this->deleteDirectory($tempBackupPath);
                throw new Exception('ZIP-Datei wurde nicht korrekt erstellt');
            }
            
            // Temporäres Verzeichnis löschen
            $this->deleteDirectory($tempBackupPath);
            
            return [
                'success' => true,
                'message' => 'Backup erfolgreich erstellt',
                'backup_path' => $zipPath,
                'backup_name' => $backupName . '.zip',
                'backup_size' => filesize($zipPath),
                'results' => $results,
                'info' => $backupInfo
            ];
            
        } catch (Exception $e) {
            // Temporäres Verzeichnis aufräumen bei Fehler
            if (isset($tempBackupPath) && is_dir($tempBackupPath)) {
                try {
                    $this->deleteDirectory($tempBackupPath);
                } catch (Exception $deleteError) {
                    // Log delete error but don't throw it
                    error_log('Fehler beim Löschen des temporären Verzeichnisses: ' . $deleteError->getMessage());
                }
            }
            
            // Detaillierte Fehlermeldung für Debugging
            $errorMessage = $e->getMessage();
            if (isset($tempBackupPath)) {
                $errorMessage .= " (Temp-Pfad: $tempBackupPath)";
            }
            if (isset($zipPath)) {
                $errorMessage .= " (ZIP-Pfad: $zipPath)";
            }
            
            return [
                'success' => false,
                'error' => $errorMessage
            ];
        }
    }
    
    /**
     * Erstellt ein MySQL-Datenbank-Backup
     */
    private function backupDatabase($backupPath) {
        try {
            // Datenbank-Konfiguration laden
            $configFile = __DIR__ . '/../../config/config.inc.php';
            if (!file_exists($configFile)) {
                throw new Exception('Datenbank-Konfiguration nicht gefunden');
            }
            
            // Variablen zurücksetzen
            $db_host = $db_name = $db_user = $db_pass = null;
            
            include $configFile;
            
            if (!isset($db_host) || !isset($db_name) || !isset($db_user)) {
                throw new Exception('Datenbank-Konfiguration unvollständig (Host, Name oder User fehlt)');
            }
            
            // Database-Verzeichnis erstellen
            $databaseDir = $backupPath . '/database';
            if (!mkdir($databaseDir, 0755, true)) {
                throw new Exception('Database-Backup-Verzeichnis konnte nicht erstellt werden');
            }
            
            $dbFile = $databaseDir . '/database_backup.sql';
            
            // Prüfe ob mysqldump verfügbar ist
            $mysqldumpPath = $this->findMysqldumpPath();
            if (!$mysqldumpPath) {
                throw new Exception('mysqldump nicht gefunden. Bitte installieren Sie MySQL Client Tools.');
            }
            
            // MySQL Dump Befehl zusammenstellen
            $command = sprintf(
                '%s --host=%s --user=%s %s %s > %s 2>&1',
                escapeshellarg($mysqldumpPath),
                escapeshellarg($db_host),
                isset($db_pass) && !empty($db_pass) ? '--password=' . escapeshellarg($db_pass) : '',
                escapeshellarg($db_name),
                escapeshellarg($dbFile)
            );
            
            // Befehl ausführen
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                $errorMsg = 'MySQL Dump fehlgeschlagen (Code: ' . $returnCode . ')';
                if (!empty($output)) {
                    $errorMsg .= ': ' . implode("\n", $output);
                }
                throw new Exception($errorMsg);
            }
            
            if (!file_exists($dbFile)) {
                throw new Exception('Datenbank-Backup-Datei wurde nicht erstellt');
            }
            
            if (filesize($dbFile) === 0) {
                throw new Exception('Datenbank-Backup ist leer');
            }
            
            return [
                'success' => true,
                'file' => $dbFile,
                'size' => filesize($dbFile),
                'message' => 'Datenbank-Backup erfolgreich erstellt'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Findet den Pfad zu mysqldump
     */
    private function findMysqldumpPath() {
        $possiblePaths = [
            'mysqldump',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            '/opt/mysql/bin/mysqldump',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe',
            'C:\\xampp\\mysql\\bin\\mysqldump.exe'
        ];
        
        foreach ($possiblePaths as $path) {
            if ($this->commandExists($path)) {
                return $path;
            }
        }
        
        return false;
    }
    
    /**
     * Prüft ob ein Befehl existiert
     */
    private function commandExists($command) {
        $output = [];
        $returnCode = 0;
        exec($command . ' --version 2>&1', $output, $returnCode);
        return $returnCode === 0;
    }
    
    /**
     * Erstellt ein Dateien-Backup
     */
    private function backupFiles($backupPath) {
        try {
            $sourceDir = __DIR__ . '/../../';
            $filesBackupDir = $backupPath . '/files';
            
            if (!mkdir($filesBackupDir, 0755, true)) {
                throw new Exception('Dateien-Backup-Verzeichnis konnte nicht erstellt werden');
            }
            
            $filesCount = 0;
            $excludeDirs = ['backups', 'node_modules', '.git', 'vendor', 'cache', 'logs'];
            $excludeFiles = ['.gitignore', '.htaccess'];
            
            // Einfache Backup-Methode ohne rekursive Iteratoren
            $this->backupDirectoryRecursive($sourceDir, $filesBackupDir, $excludeDirs, $excludeFiles, $filesCount);
            
            return [
                'success' => true,
                'files_count' => $filesCount,
                'message' => "{$filesCount} Dateien erfolgreich gesichert"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Erstellt ein Backup nur der wichtigsten Dateien
     */
    private function backupEssentialFiles($backupPath) {
        try {
            $filesBackupDir = $backupPath . '/files';
            
            if (!mkdir($filesBackupDir, 0755, true)) {
                throw new Exception('Dateien-Backup-Verzeichnis konnte nicht erstellt werden');
            }
            
            $filesCount = 0;
            $essentialFiles = [
                'framework.php',
                'config/config.inc.php',
                'src/core/',
                'src/inc/',
                'src/assets/',
                'src/module/',
                'CHANGELOG.md',
                'README.md',
                'LICENSE'
            ];
            
            $sourceDir = __DIR__ . '/../../';
            
            foreach ($essentialFiles as $file) {
                $sourcePath = $sourceDir . $file;
                $targetPath = $filesBackupDir . '/' . $file;
                
                if (is_file($sourcePath)) {
                    $targetDir = dirname($targetPath);
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0755, true);
                    }
                    if (copy($sourcePath, $targetPath)) {
                        $filesCount++;
                    }
                } elseif (is_dir($sourcePath)) {
                    $this->backupDirectoryRecursive($sourcePath, $targetPath, [], [], $filesCount);
                }
            }
            
            return [
                'success' => true,
                'files_count' => $filesCount,
                'message' => "{$filesCount} wichtige Dateien erfolgreich gesichert"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Rekursive Backup-Methode ohne Iterator
     */
    private function backupDirectoryRecursive($sourceDir, $targetDir, $excludeDirs, $excludeFiles, &$filesCount) {
        if (!is_dir($sourceDir) || !is_readable($sourceDir)) {
            return;
        }
        
        $items = @scandir($sourceDir);
        if ($items === false) {
            return;
        }
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $sourcePath = $sourceDir . '/' . $item;
            $relativePath = str_replace(__DIR__ . '/../../', '', $sourcePath);
            $relativePath = ltrim($relativePath, '/\\');
            
            // Verzeichnisse ausschließen
            $shouldExclude = false;
            foreach ($excludeDirs as $excludeDir) {
                if (strpos($relativePath, $excludeDir) === 0) {
                    $shouldExclude = true;
                    break;
                }
            }
            
            if ($shouldExclude) {
                continue;
            }
            
            if (is_dir($sourcePath)) {
                $targetPath = $targetDir . '/' . $item;
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
                $this->backupDirectoryRecursive($sourcePath, $targetPath, $excludeDirs, $excludeFiles, $filesCount);
            } elseif (is_file($sourcePath)) {
                // Dateien ausschließen
                if (in_array($item, $excludeFiles)) {
                    continue;
                }
                
                $targetPath = $targetDir . '/' . $item;
                if (copy($sourcePath, $targetPath)) {
                    $filesCount++;
                }
            }
        }
    }
    
    /**
     * Berechnet die Größe eines Verzeichnisses
     */
    private function getDirectorySize($directory) {
        $size = 0;
        $this->calculateDirectorySize($directory, $size);
        return $size;
    }
    
    /**
     * Rekursive Größenberechnung ohne Iterator
     */
    private function calculateDirectorySize($directory, &$size) {
        if (!is_dir($directory) || !is_readable($directory)) {
            return;
        }
        
        $items = @scandir($directory);
        if ($items === false) {
            return;
        }
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $path = $directory . '/' . $item;
            if (is_file($path)) {
                $size += filesize($path);
            } elseif (is_dir($path)) {
                $this->calculateDirectorySize($path, $size);
            }
        }
    }
    
    /**
     * Erstellt eine ZIP-Datei aus einem Verzeichnis
     */
    private function createZipFromDirectory($sourceDir, $zipPath) {
        try {
            if (!class_exists('ZipArchive')) {
                throw new Exception('ZIP Extension nicht verfügbar');
            }
            
            $zip = new ZipArchive();
            $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            
            if ($result !== TRUE) {
                throw new Exception('ZIP-Datei konnte nicht erstellt werden: ' . $result);
            }
            
            $this->addDirectoryToZip($zip, $sourceDir, '');
            $zip->close();
            
            return [
                'success' => true,
                'message' => 'ZIP-Datei erfolgreich erstellt'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Fügt ein Verzeichnis rekursiv zu einer ZIP-Datei hinzu
     */
    private function addDirectoryToZip($zip, $sourceDir, $zipPath) {
        $items = @scandir($sourceDir);
        if ($items === false) {
            return;
        }
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $sourcePath = $sourceDir . '/' . $item;
            $targetPath = $zipPath . ($zipPath ? '/' : '') . $item;
            
            if (is_file($sourcePath)) {
                $zip->addFile($sourcePath, $targetPath);
            } elseif (is_dir($sourcePath)) {
                $zip->addEmptyDir($targetPath);
                $this->addDirectoryToZip($zip, $sourcePath, $targetPath);
            }
        }
    }
    
    /**
     * Löscht ein Verzeichnis rekursiv
     */
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $items = @scandir($dir);
        if ($items === false) {
            return;
        }
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $path = $dir . '/' . $item;
            if (is_file($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                $this->deleteDirectory($path);
            }
        }
        
        rmdir($dir);
    }
    
    /**
     * Listet alle verfügbaren Backups auf
     */
    public function listBackups() {
        try {
            $backupDir = __DIR__ . '/../../backups';
            if (!is_dir($backupDir)) {
                return [
                    'success' => true,
                    'backups' => []
                ];
            }
            
            $backups = [];
            $items = @scandir($backupDir);
            if ($items === false) {
                return [
                    'success' => true,
                    'backups' => []
                ];
            }
            
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                
                $itemPath = $backupDir . '/' . $item;
                
                // ZIP-Dateien verarbeiten
                if (is_file($itemPath) && pathinfo($item, PATHINFO_EXTENSION) === 'zip') {
                    $backupInfo = [
                        'name' => $item,
                        'path' => $itemPath,
                        'created' => date('Y-m-d H:i:s', filemtime($itemPath)),
                        'size' => filesize($itemPath),
                        'type' => 'zip',
                        'downloadable' => true
                    ];
                    
                    // Versuche Backup-Info aus ZIP zu extrahieren (optional)
                    $backupInfo['include_database'] = true; // Default
                    $backupInfo['simple_backup'] = false; // Default
                    
                    $backups[] = $backupInfo;
                }
                // Alte Verzeichnis-Backups (für Kompatibilität)
                elseif (is_dir($itemPath)) {
                    $infoFile = $itemPath . '/backup_info.json';
                    
                    $backupInfo = [
                        'name' => $item,
                        'path' => $itemPath,
                        'created' => date('Y-m-d H:i:s', filemtime($itemPath)),
                        'size' => $this->getDirectorySize($itemPath),
                        'type' => 'directory',
                        'downloadable' => false
                    ];
                    
                    if (file_exists($infoFile)) {
                        $info = json_decode(file_get_contents($infoFile), true);
                        if ($info) {
                            $backupInfo = array_merge($backupInfo, $info);
                        }
                    }
                    
                    $backups[] = $backupInfo;
                }
            }
            
            // Nach Erstellungsdatum sortieren (neueste zuerst)
            usort($backups, function($a, $b) {
                return strtotime($b['created']) - strtotime($a['created']);
            });
            
            return [
                'success' => true,
                'backups' => $backups
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Lädt ein Backup herunter
     */
    public function downloadBackup($backupName) {
        try {
            if (empty($backupName)) {
                throw new Exception('Backup-Name fehlt');
            }
            
            $backupDir = __DIR__ . '/../../backups';
            $backupPath = $backupDir . '/' . $backupName;
            
            if (!file_exists($backupPath)) {
                throw new Exception('Backup-Datei nicht gefunden');
            }
            
            if (!is_readable($backupPath)) {
                throw new Exception('Backup-Datei ist nicht lesbar');
            }
            
            // Sicherheitsprüfung: Nur ZIP-Dateien im backups-Verzeichnis
            if (pathinfo($backupName, PATHINFO_EXTENSION) !== 'zip') {
                throw new Exception('Nur ZIP-Backups können heruntergeladen werden');
            }
            
            // Download-Header setzen
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $backupName . '"');
            header('Content-Length: ' . filesize($backupPath));
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
            
            // Datei ausgeben
            readfile($backupPath);
            exit;
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Löscht ein Backup
     */
    public function deleteBackup($backupName) {
        try {
            if (empty($backupName)) {
                throw new Exception('Backup-Name fehlt');
            }
            
            $backupDir = __DIR__ . '/../../backups';
            $backupPath = $backupDir . '/' . $backupName;
            
            if (!file_exists($backupPath)) {
                throw new Exception('Backup nicht gefunden');
            }
            
            // Sicherheitsprüfung: Nur Dateien im backups-Verzeichnis
            $realBackupPath = realpath($backupPath);
            $realBackupDir = realpath($backupDir);
            
            if (!$realBackupPath || !$realBackupDir || strpos($realBackupPath, $realBackupDir) !== 0) {
                throw new Exception('Ungültiger Backup-Pfad');
            }
            
            // Backup löschen
            if (is_file($backupPath)) {
                if (!unlink($backupPath)) {
                    throw new Exception('Backup-Datei konnte nicht gelöscht werden');
                }
                $message = 'Backup-Datei erfolgreich gelöscht';
            } elseif (is_dir($backupPath)) {
                $this->deleteDirectory($backupPath);
                $message = 'Backup-Verzeichnis erfolgreich gelöscht';
            } else {
                throw new Exception('Backup ist weder eine Datei noch ein Verzeichnis');
            }
            
            return [
                'success' => true,
                'message' => $message
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Löscht alle Backups
     */
    public function deleteAllBackups() {
        try {
            $backupDir = __DIR__ . '/../../backups';
            
            if (!is_dir($backupDir)) {
                return [
                    'success' => true,
                    'message' => 'Keine Backups vorhanden',
                    'deleted_count' => 0
                ];
            }
            
            $items = @scandir($backupDir);
            if ($items === false) {
                throw new Exception('Backup-Verzeichnis konnte nicht gelesen werden');
            }
            
            $deletedCount = 0;
            $errors = [];
            
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                
                $itemPath = $backupDir . '/' . $item;
                
                try {
                    if (is_file($itemPath)) {
                        if (unlink($itemPath)) {
                            $deletedCount++;
                        } else {
                            $errors[] = "Datei konnte nicht gelöscht werden: $item";
                        }
                    } elseif (is_dir($itemPath)) {
                        $this->deleteDirectory($itemPath);
                        $deletedCount++;
                    }
                } catch (Exception $e) {
                    $errors[] = "Fehler beim Löschen von $item: " . $e->getMessage();
                }
            }
            
            $message = "$deletedCount Backups erfolgreich gelöscht";
            if (!empty($errors)) {
                $message .= ". Fehler: " . implode(', ', $errors);
            }
            
            return [
                'success' => true,
                'message' => $message,
                'deleted_count' => $deletedCount,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Packt ein Verzeichnis oder eine Datei rekursiv in eine ZIP-Datei (bereitgestellte Funktion)
     */
    private function zipRecursive(string $source, string $destination, array $exclude = [], bool $preservePaths = true): bool {
        if (!extension_loaded('zip')) {
            throw new Exception('Zip extension not loaded.');
        }

        $source = rtrim($source, DIRECTORY_SEPARATOR);
        if (!file_exists($source)) {
            throw new Exception("Quelle existiert nicht: $source");
        }

        $destDir = dirname($destination);
        if (!is_dir($destDir) && !mkdir($destDir, 0777, true)) {
            throw new Exception("Konnte Zielverzeichnis nicht erstellen: $destDir");
        }

        $zip = new ZipArchive();
        if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception("Konnte ZIP-Datei nicht öffnen/erstellen: $destination");
        }

        // Helper: prüfen ob ein Pfad ausgeschlossen ist (Patterns via fnmatchCompat)
        $isExcluded = function(string $relativePath) use ($exclude): bool {
            foreach ($exclude as $pattern) {
                // fnmatch benötigt eventuell einen UNIX-Style slash, wir normalisieren
                $normPattern = str_replace(['\\', '/'], '/', $pattern);
                $normPath = str_replace(['\\', '/'], '/', $relativePath);
                if (fnmatchCompat($normPattern, $normPath)) {
                    return true;
                }
            }
            return false;
        };

        // Wenn Quelle eine einzelne Datei ist
        if (is_file($source)) {
            $relative = basename($source);
            if (!$isExcluded($relative)) {
                if (!$zip->addFile($source, $preservePaths ? $relative : basename($relative))) {
                    $zip->close();
                    throw new Exception("Konnte Datei nicht zum ZIP hinzufügen: $source");
                }
            }
            $zip->close();
            return true;
        }

        // Quelle ist ein Verzeichnis -> rekursiver Iterator
        $baseLen = strlen(dirname($source)) + 1; // zum Erzeugen relativer Pfade
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        // Füge Verzeichnis-Root als leeren Ordner (optional)
        $rootRelative = substr($source, $baseLen);
        if ($rootRelative !== '') {
            if (!$isExcluded($rootRelative . '/')) {
                $zip->addEmptyDir($rootRelative);
            }
        }

        foreach ($iterator as $item) {
            $filePath = $item->getPathname();
            // relative Pfade innerhalb des ZIP (Unix-style)
            $relativePath = str_replace('\\', '/', substr($filePath, $baseLen));

            if ($isExcluded($relativePath)) {
                continue;
            }

            if ($item->isDir()) {
                // Sicherstellen, dass leere Ordner angelegt werden
                if (!$zip->addEmptyDir($relativePath)) {
                    // addEmptyDir liefert false, wenn schon vorhanden oder Fehler; wir ignorieren stille Fehlschläge
                }
            } elseif ($item->isFile()) {
                if (!$zip->addFile($filePath, $preservePaths ? $relativePath : basename($relativePath))) {
                    $zip->close();
                    throw new Exception("Konnte Datei nicht zum ZIP hinzufügen: $filePath");
                }
            } elseif ($item->isLink()) {
                // Symlink: optional behandeln — hier wird der Link-Target als Dateiinhalt nicht gepackt,
                // stattdessen können wir den Link-Pfad als Textdatei speichern oder überspringen.
                // Wir überspringen Symlinks standardmäßig:
                continue;
            }
        }

        $zip->close();
        return true;
    }
    
    /**
     * Zählt Dateien in einem Verzeichnis rekursiv
     */
    private function countFilesInDirectory($directory) {
        $count = 0;
        if (is_dir($directory)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $count++;
                }
            }
        }
        return $count;
    }
    
    /**
     * Löscht ein Verzeichnis rekursiv
     */
    private function deleteDirectoryRecursive($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
        
        rmdir($dir);
    }
    
    /**
     * Kopiert ein Verzeichnis rekursiv (einfache Version ohne Iterator)
     */
    private function copyDirectoryRecursiveSimple($sourceDir, $targetDir, $excludePatterns, &$filesCount) {
        if (!is_dir($sourceDir) || !is_readable($sourceDir)) {
            throw new Exception('Quell-Verzeichnis nicht lesbar');
        }
        
        $items = @scandir($sourceDir);
        if ($items === false) {
            throw new Exception('Quell-Verzeichnis konnte nicht gelesen werden');
        }
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $sourcePath = $sourceDir . '/' . $item;
            $relativePath = str_replace(__DIR__ . '/../../', '', $sourcePath);
            $relativePath = ltrim($relativePath, '/\\');
            
            // Prüfen ob Pfad ausgeschlossen ist
            $shouldExclude = false;
            foreach ($excludePatterns as $pattern) {
                $normPattern = str_replace(['\\', '/'], '/', $pattern);
                $normPath = str_replace(['\\', '/'], '/', $relativePath);
                if (fnmatchCompat($normPattern, $normPath)) {
                    $shouldExclude = true;
                    break;
                }
            }
            
            if ($shouldExclude) {
                continue;
            }
            
            if (is_dir($sourcePath)) {
                $targetPath = $targetDir . '/' . $item;
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
                $this->copyDirectoryRecursiveSimple($sourcePath, $targetPath, $excludePatterns, $filesCount);
            } elseif (is_file($sourcePath)) {
                $targetPath = $targetDir . '/' . $item;
                if (copy($sourcePath, $targetPath)) {
                    $filesCount++;
                }
            }
        }
    }
    
    /**
     * Fügt ein Verzeichnis zur ZIP hinzu (einfache Version ohne Iterator)
     */
    private function addDirectoryToZipSimple($zip, $sourceDir, $zipPath) {
        $items = @scandir($sourceDir);
        if ($items === false) {
            return;
        }
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $sourcePath = $sourceDir . '/' . $item;
            $targetPath = $zipPath . ($zipPath ? '/' : '') . $item;
            
            if (is_file($sourcePath)) {
                $zip->addFile($sourcePath, $targetPath);
            } elseif (is_dir($sourcePath)) {
                $zip->addEmptyDir($targetPath);
                $this->addDirectoryToZipSimple($zip, $sourcePath, $targetPath);
            }
        }
    }
    
    /**
     * Testet die ZIP-Erstellung
     */
    public function testZipCreation() {
        try {
            $backupDir = __DIR__ . '/../../backups';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }
            
            $testDir = $backupDir . '/test_' . time();
            $testZip = $backupDir . '/test_' . time() . '.zip';
            
            // Test-Verzeichnis erstellen
            if (!mkdir($testDir, 0755, true)) {
                throw new Exception('Test-Verzeichnis konnte nicht erstellt werden');
            }
            
            // Test-Datei erstellen
            $testFile = $testDir . '/test.txt';
            file_put_contents($testFile, 'Test-Inhalt');
            
            // ZIP erstellen
            $zipResult = $this->createZipFromDirectory($testDir, $testZip);
            
            // Aufräumen
            $this->deleteDirectory($testDir);
            if (file_exists($testZip)) {
                unlink($testZip);
            }
            
            return [
                'success' => $zipResult['success'],
                'message' => $zipResult['success'] ? 'ZIP-Test erfolgreich' : 'ZIP-Test fehlgeschlagen: ' . $zipResult['error'],
                'zip_available' => class_exists('ZipArchive')
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'zip_available' => class_exists('ZipArchive')
            ];
        }
    }
    
    /**
     * Debuggt den Backup-Prozess
     */
    public function debugBackupProcess($includeDatabase = true, $simpleBackup = false) {
        try {
            $backupDir = __DIR__ . '/../../backups';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }
            
            $debugInfo = [];
            $debugInfo[] = "=== BACKUP DEBUG INFO ===";
            $debugInfo[] = "Backup-Verzeichnis: " . $backupDir;
            $debugInfo[] = "Include Database: " . ($includeDatabase ? 'Ja' : 'Nein');
            $debugInfo[] = "Simple Backup: " . ($simpleBackup ? 'Ja' : 'Nein');
            $debugInfo[] = "PHP Version: " . PHP_VERSION;
            $debugInfo[] = "ZIP Extension: " . (class_exists('ZipArchive') ? 'Verfügbar' : 'Nicht verfügbar');
            $debugInfo[] = "Temp Directory: " . sys_get_temp_dir();
            $debugInfo[] = "Temp Directory writable: " . (is_writable(sys_get_temp_dir()) ? 'Ja' : 'Nein');
            
            // Test-Verzeichnis erstellen
            $testDir = $backupDir . '/debug_test_' . time();
            $debugInfo[] = "Test-Verzeichnis: " . $testDir;
            
            if (!mkdir($testDir, 0755, true)) {
                throw new Exception('Test-Verzeichnis konnte nicht erstellt werden');
            }
            $debugInfo[] = "Test-Verzeichnis erstellt: OK";
            
            // Schritt 1: Dateien-Backup testen
            try {
                $debugInfo[] = "--- Dateien-Backup Test ---";
                if ($simpleBackup) {
                    $filesBackup = $this->backupEssentialFiles($testDir);
                } else {
                    $filesBackup = $this->backupFiles($testDir);
                }
                
                if ($filesBackup['success']) {
                    $debugInfo[] = "Dateien-Backup: ERFOLGREICH";
                    $debugInfo[] = "Dateien-Backup Message: " . $filesBackup['message'];
                } else {
                    $debugInfo[] = "Dateien-Backup: FEHLGESCHLAGEN";
                    $debugInfo[] = "Dateien-Backup Error: " . $filesBackup['error'];
                }
            } catch (Exception $e) {
                $debugInfo[] = "Dateien-Backup Exception: " . $e->getMessage();
            }
            
            // Schritt 2: Datenbank-Backup testen
            if ($includeDatabase) {
                try {
                    $debugInfo[] = "--- Datenbank-Backup Test ---";
                    $dbBackup = $this->backupDatabase($testDir);
                    
                    if ($dbBackup['success']) {
                        $debugInfo[] = "Datenbank-Backup: ERFOLGREICH";
                        $debugInfo[] = "Datenbank-Backup Message: " . $dbBackup['message'];
                    } else {
                        $debugInfo[] = "Datenbank-Backup: FEHLGESCHLAGEN";
                        $debugInfo[] = "Datenbank-Backup Error: " . $dbBackup['error'];
                    }
                } catch (Exception $e) {
                    $debugInfo[] = "Datenbank-Backup Exception: " . $e->getMessage();
                }
            }
            
            // Schritt 3: ZIP-Erstellung testen
            try {
                $debugInfo[] = "--- ZIP-Erstellung Test ---";
                $testZip = $backupDir . '/debug_test_' . time() . '.zip';
                $zipResult = $this->createZipFromDirectory($testDir, $testZip);
                
                if ($zipResult['success']) {
                    $debugInfo[] = "ZIP-Erstellung: ERFOLGREICH";
                    $debugInfo[] = "ZIP-Datei: " . $testZip;
                    $debugInfo[] = "ZIP-Größe: " . (file_exists($testZip) ? filesize($testZip) . ' Bytes' : 'Datei nicht gefunden');
                    if (file_exists($testZip)) {
                        unlink($testZip);
                    }
                } else {
                    $debugInfo[] = "ZIP-Erstellung: FEHLGESCHLAGEN";
                    $debugInfo[] = "ZIP-Error: " . $zipResult['error'];
                }
            } catch (Exception $e) {
                $debugInfo[] = "ZIP-Erstellung Exception: " . $e->getMessage();
            }
            
            // Aufräumen
            $this->deleteDirectory($testDir);
            $debugInfo[] = "Test-Verzeichnis gelöscht: OK";
            
            return [
                'success' => true,
                'message' => 'Debug-Informationen gesammelt',
                'debug_info' => $debugInfo
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'debug_info' => $debugInfo ?? []
            ];
        }
    }
    
    /**
     * Testet die Backup-Schritte einzeln
     */
    public function testBackupSteps($includeDatabase = true, $simpleBackup = false) {
        try {
            $backupDir = __DIR__ . '/../../backups';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }
            
            $testDir = $backupDir . '/test_steps_' . time();
            
            // Test-Verzeichnis erstellen
            if (!mkdir($testDir, 0755, true)) {
                throw new Exception('Test-Verzeichnis konnte nicht erstellt werden');
            }
            
            $results = [];
            $steps = [];
            
            // Schritt 1: Dateien-Backup testen
            try {
                $steps[] = 'Dateien-Backup wird getestet...';
                if ($simpleBackup) {
                    $filesBackup = $this->backupEssentialFiles($testDir);
                } else {
                    $filesBackup = $this->backupFiles($testDir);
                }
                $results['files'] = $filesBackup;
                
                if ($filesBackup['success']) {
                    $steps[] = 'Dateien-Backup erfolgreich: ' . $filesBackup['message'];
                } else {
                    $steps[] = 'Dateien-Backup fehlgeschlagen: ' . $filesBackup['error'];
                }
            } catch (Exception $e) {
                $steps[] = 'Dateien-Backup Exception: ' . $e->getMessage();
                $results['files'] = ['success' => false, 'error' => $e->getMessage()];
            }
            
            // Schritt 2: Datenbank-Backup testen
            if ($includeDatabase) {
                try {
                    $steps[] = 'Datenbank-Backup wird getestet...';
                    $dbBackup = $this->backupDatabase($testDir);
                    $results['database'] = $dbBackup;
                    
                    if ($dbBackup['success']) {
                        $steps[] = 'Datenbank-Backup erfolgreich: ' . $dbBackup['message'];
                    } else {
                        $steps[] = 'Datenbank-Backup fehlgeschlagen: ' . $dbBackup['error'];
                    }
                } catch (Exception $e) {
                    $steps[] = 'Datenbank-Backup Exception: ' . $e->getMessage();
                    $results['database'] = ['success' => false, 'error' => $e->getMessage()];
                }
            }
            
            // Schritt 3: ZIP-Erstellung testen
            try {
                $steps[] = 'ZIP-Erstellung wird getestet...';
                $testZip = $backupDir . '/test_steps_' . time() . '.zip';
                $zipResult = $this->createZipFromDirectory($testDir, $testZip);
                
                if ($zipResult['success']) {
                    $steps[] = 'ZIP-Erstellung erfolgreich';
                    if (file_exists($testZip)) {
                        unlink($testZip);
                    }
                } else {
                    $steps[] = 'ZIP-Erstellung fehlgeschlagen: ' . $zipResult['error'];
                }
            } catch (Exception $e) {
                $steps[] = 'ZIP-Erstellung Exception: ' . $e->getMessage();
            }
            
            // Aufräumen
            $this->deleteDirectory($testDir);
            
            return [
                'success' => true,
                'message' => 'Backup-Schritte getestet',
                'steps' => $steps,
                'results' => $results
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Framework-Only Dateien extrahieren
     */
    private function extractFrameworkOnly($zip) {
        $frameworkFiles = [
            'framework.php',
            'config/',
            'src/core/',
            'src/database-structure.sql'
        ];
        
        $rootPath = __DIR__ . '/../../';
        
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            
            // Prüfen ob Datei zu Framework-Only gehört
            $shouldExtract = false;
            foreach ($frameworkFiles as $pattern) {
                if (strpos($filename, $pattern) === 0) {
                    $shouldExtract = true;
                    break;
                }
            }
            
            if ($shouldExtract) {
                $targetPath = $rootPath . $filename;
                
                // Verzeichnis erstellen falls nötig
                $dir = dirname($targetPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                
                // Datei extrahieren
                if (substr($filename, -1) !== '/') {
                    $zip->extractTo($rootPath, $filename);
                }
            }
        }
    }
    
    /**
     * Vollständiges Update extrahieren
     */
    private function extractFullUpdate($zip) {
        $rootPath = __DIR__ . '/../../';
        
        // Alle Dateien extrahieren außer sys.conf.php
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            
            // sys.conf.php überspringen
            if (strpos($filename, 'src/sys.conf.php') !== false) {
                continue;
            }
            
            $targetPath = $rootPath . $filename;
            
            // Verzeichnis erstellen falls nötig
            $dir = dirname($targetPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            // Datei extrahieren
            if (substr($filename, -1) !== '/') {
                $zip->extractTo($rootPath, $filename);
            }
        }
    }
    
    /**
     * Backup erstellen
     */
    private function createBackup($backupPath) {
        $rootPath = __DIR__ . '/../../';
        $backupFile = $backupPath . '/backup_' . date('Y-m-d_H-i-s') . '.zip';
        
        $zip = new ZipArchive();
        if ($zip->open($backupFile, ZipArchive::CREATE) !== TRUE) {
            throw new Exception('Backup konnte nicht erstellt werden');
        }
        
        $this->addDirectoryToZip($zip, $rootPath, '');
        $zip->close();
        
        return $backupFile;
    }
    
    
    /**
     * Version in sys.conf.php aktualisieren
     */
    private function updateVersionInConfig() {
        $configPath = __DIR__ . '/sys.conf.php';
        $content = file_get_contents($configPath);
        
        // Version ersetzen
        $newVersion = $this->githubVersion ?? $this->changelogVersion;
        $content = preg_replace(
            "/'version' => '[^']*'/",
            "'version' => '{$newVersion}'",
            $content
        );
        
        file_put_contents($configPath, $content);
    }
    
    /**
     * System-Informationen abrufen
     */
    public function getSystemInfo() {
        return [
            'current_version' => $this->currentVersion,
            'changelog_version' => $this->changelogVersion,
            'is_nightly' => $this->isNightly,
            'php_version' => PHP_VERSION,
            'zip_extension' => extension_loaded('zip'),
            'curl_extension' => extension_loaded('curl'),
            'temp_dir' => sys_get_temp_dir(),
            'writable' => is_writable(sys_get_temp_dir())
        ];
    }
}

// Plattformunabhängige Helper-Funktionen
if (!function_exists('fnmatchCompat')) {
    /**
     * fnmatch Ersatz, funktioniert auch unter Windows (preg_match-basiert).
     */
    function fnmatchCompat(string $pattern, string $string): bool {
        // ** zu .* (beliebige Unterordner)
        $pattern = str_replace('**', '.*', $pattern);
        // * zu [^/]* (alles außer /)
        $pattern = str_replace('*', '[^/]*', $pattern);
        // ? zu [^/]
        $pattern = str_replace('?', '[^/]', $pattern);
        // Escapen für Regex-Ränder und Backslashes
        $pattern = '#^' . str_replace(['\\'], ['\\\\'], $pattern) . '$#';
        return (bool)preg_match($pattern, $string);
    }
}

if (!function_exists('deleteDirectoryRecursive')) {
    /**
     * Löscht ein Verzeichnis und alle enthaltenen Dateien/Unterverzeichnisse rekursiv.
     */
    function deleteDirectoryRecursive(string $dir): bool {
        if (!is_dir($dir)) {
            return false;
        }
        $items = @scandir($dir);
        if ($items === false) {
            return false;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                deleteDirectoryRecursive($path);
            } else {
                @unlink($path);
            }
        }
        return @rmdir($dir);
    }
}

// Download Handler für GET-Requests
if (isset($_GET['action']) && $_GET['action'] === 'download_backup') {
    // Sicherstellen, dass keine Ausgabe vor Headern erfolgt
    if (ob_get_level()) {
        @ob_end_clean();
    }
    $backupName = $_GET['backup_name'] ?? '';
    $updater = new ManualUpdater();
    $result = $updater->downloadBackup($backupName);
    if (!$result['success']) {
        header('Content-Type: text/plain');
        echo 'Fehler: ' . $result['error'];
    }
    exit;
}

// AJAX Handler
if (isset($_POST['action'])) {
    // Error reporting für AJAX-Requests deaktivieren
    error_reporting(0);
    ini_set('display_errors', 0);
    
    // Output buffering starten um sicherzustellen, dass keine unerwünschten Ausgaben erfolgen
    ob_start();
    
    header('Content-Type: application/json');
    
    try {
        $updater = new ManualUpdater();
        $response = [];
        
        switch ($_POST['action']) {
        case 'check_updates':
            $response = $updater->checkForUpdates();
            break;
            
        case 'download_update':
            $tagName = $_POST['tag_name'] ?? '';
            $updateType = $_POST['update_type'] ?? 'framework';
            
            if (empty($tagName)) {
                $response = ['success' => false, 'error' => 'Tag-Name fehlt'];
            } else {
                $response = $updater->downloadRelease($tagName, $updateType);
            }
            break;
            
        case 'install_update':
            $downloadPath = $_POST['download_path'] ?? '';
            $createBackup = $_POST['create_backup'] ?? false;
            
            if (empty($downloadPath)) {
                $response = ['success' => false, 'error' => 'Download-Pfad fehlt'];
            } else {
                $backupPath = $createBackup ? __DIR__ . '/../../backups' : null;
                $response = $updater->installUpdate($downloadPath, $backupPath);
            }
            break;
            
        case 'system_info':
            $response = $updater->getSystemInfo();
            break;
            
        case 'create_backup':
            $includeDatabase = filter_var($_POST['include_database'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $simpleBackup = filter_var($_POST['simple_backup'] ?? false, FILTER_VALIDATE_BOOLEAN);
            
            // Saubere Backup-Erstellung mit tmp-Verzeichnis
            try {
                $backupDir = __DIR__ . '/../../backups';
                if (!is_dir($backupDir)) {
                    if (!mkdir($backupDir, 0755, true)) {
                        throw new Exception('Backup-Verzeichnis konnte nicht erstellt werden');
                    }
                }
                
                $timestamp = date('Y-m-d_H-i-s');
                $backupName = "manual_backup_{$timestamp}";
                $tempDir = sys_get_temp_dir() . '/' . $backupName;
                $zipPath = $backupDir . '/' . $backupName . '.zip';
                
                // Temporäres Verzeichnis im tmp-Ordner erstellen
                if (!mkdir($tempDir, 0755, true)) {
                    throw new Exception('Temporäres Verzeichnis konnte nicht erstellt werden');
                }
                
                $results = [];
                $filesCount = 0;
                
                // Dateien-Backup mit der bereitgestellten zipRecursive Funktion
                try {
                    $filesBackupDir = $tempDir . '/files';
                    if (!mkdir($filesBackupDir, 0755, true)) {
                        throw new Exception('Dateien-Backup-Verzeichnis konnte nicht erstellt werden');
                    }
                    
                    $sourceDir = __DIR__ . '/../../';
                    $excludePatterns = [
                        '**/backups/**',
                        '**/node_modules/**',
                        '**/.git/**',
                        '**/vendor/**',
                        '**/cache/**',
                        '**/logs/**',
                        '**/stats/**',
                        '**/error/**',
                        '**/examples/**',
                        '**/.gitignore',
                        '**/.htaccess'
                    ];
                    
                    // Echte rekursive Dateien-Kopie mit korrekter Ausschluss-Logik (direkte Implementierung)
                    function copyRecursive($src, $dst, $excludePatterns, &$filesCount) {
                        $dir = opendir($src);
                        if (!is_dir($dst)) {
                            mkdir($dst, 0755, true);
                        }
                        while (($file = readdir($dir)) !== false) {
                            if ($file != '.' && $file != '..') {
                                $srcPath = $src . '/' . $file;
                                $dstPath = $dst . '/' . $file;
                                
                                // Prüfen ob Pfad ausgeschlossen ist
                                $relativePath = str_replace(__DIR__ . '/../../', '', $srcPath);
                                $relativePath = ltrim($relativePath, '/\\');
                                
                                $shouldExclude = false;
                                foreach ($excludePatterns as $pattern) {
                                    $normPattern = str_replace(['\\', '/'], '/', $pattern);
                                    $normPath = str_replace(['\\', '/'], '/', $relativePath);
                                    if (fnmatchCompat($normPattern, $normPath)) {
                                        $shouldExclude = true;
                                        break;
                                    }
                                }
                                
                                if ($shouldExclude) {
                                    continue;
                                }
                                
                                if (is_dir($srcPath)) {
                                    copyRecursive($srcPath, $dstPath, $excludePatterns, $filesCount);
                                } else {
                                    if (copy($srcPath, $dstPath)) {
                                        $filesCount++;
                                    }
                                }
                            }
                        }
                        closedir($dir);
                    }
                    
                    copyRecursive($sourceDir, $filesBackupDir, $excludePatterns, $filesCount);
                    
                    // Ausgeschlossene Ordner werden nach der ZIP-Erstellung gelöscht
                    
                    $results['files'] = [
                        'success' => true,
                        'files_count' => $filesCount,
                        'message' => "{$filesCount} Dateien erfolgreich gesichert"
                    ];
                    
                } catch (Exception $e) {
                    $results['files'] = [
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                    throw new Exception('Dateien-Backup fehlgeschlagen: ' . $e->getMessage());
                }
                
                // Datenbank-Backup
                if ($includeDatabase) {
                    try {
                        // Helper zum Finden von Executables (einfach, plattformtolerant)
                        $findExecutable = function(array $candidates) {
                            foreach ($candidates as $candidate) {
                                if ($candidate === null || $candidate === '') continue;
                                // Direkter Pfad
                                if (file_exists($candidate) && is_file($candidate) && is_executable($candidate)) {
                                    return $candidate;
                                }
                                // In PATH suchen (Linux)
                                if (function_exists('shell_exec')) {
                                    $which = @shell_exec('command -v ' . escapeshellcmd($candidate) . ' 2>/dev/null');
                                    if ($which) {
                                        $which = trim($which);
                                        if ($which !== '' && file_exists($which)) {
                                            return $which;
                                        }
                                    }
                                    // Windows
                                    $where = @shell_exec('where ' . escapeshellcmd($candidate) . ' 2>nul');
                                    if ($where) {
                                        $lines = array_filter(array_map('trim', explode("\n", $where)));
                                        foreach ($lines as $line) {
                                            if (file_exists($line)) return $line;
                                        }
                                    }
                                }
                            }
                            return null;
                        };
                        
                        if (!class_exists('DatabaseManager')) {
                            require_once __DIR__ . '/../core/DatabaseManager.php';
                        }
                        $dbManager = DatabaseManager::getInstance();
                        $driverType = $dbManager->getDriverType();
                        
                        $databaseDir = $tempDir . '/database';
                        if (!mkdir($databaseDir, 0755, true)) {
                            throw new Exception('Database-Backup-Verzeichnis konnte nicht erstellt werden');
                        }
                        
                        $message = '';
                        
                        switch ($driverType) {
                            case 'mysql':
                            case 'mariadb':
                                $dumpFile = $databaseDir . '/database_backup.sql';
                                $mysqldump = $findExecutable([
                                    'mysqldump',
                                    '/usr/bin/mysqldump',
                                    '/usr/local/bin/mysqldump',
                                    'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe'
                                ]);
                                if ($mysqldump) {
                                    $cmd = escapeshellcmd($mysqldump)
                                        . ' --user=' . escapeshellarg(Config::DB_USER)
                                        . ' --password=' . escapeshellarg(Config::DB_PASS)
                                        . ' --host=' . escapeshellarg(Config::DB_HOST)
                                        . ' --port=' . escapeshellarg((string)Config::DB_PORT)
                                        . ' --single-transaction --quick --routines --events --triggers'
                                        . ' --default-character-set=' . escapeshellarg(Config::DB_CHARSET)
                                        . ' ' . escapeshellarg(Config::DB_NAME);
                                    $descriptors = [
                                        1 => ['file', $dumpFile, 'w'], // stdout -> Datei
                                        2 => ['pipe', 'w']            // stderr -> Pipe
                                    ];
                                    $proc = @proc_open($cmd, $descriptors, $pipes);
                                    if (is_resource($proc)) {
                                        $stderr = stream_get_contents($pipes[2]);
                                        fclose($pipes[2]);
                                        $exitCode = proc_close($proc);
                                        if ($exitCode !== 0) {
                                            throw new Exception('mysqldump Fehler: ' . trim((string)$stderr));
                                        }
                                        $message = 'MySQL Dump via mysqldump erstellt';
                                    } else {
                                        throw new Exception('mysqldump konnte nicht gestartet werden');
                                    }
                                } else {
                                    // Fallback: einfacher PHP-Dump (Schema + Daten)
                                    /** @var PDO $pdo */
                                    $pdo = $dbManager->getConnection();
                                    $fp = fopen($dumpFile, 'w');
                                    fwrite($fp, "-- Einfacher PHP MySQL Dump: " . date('Y-m-d H:i:s') . "\nSET FOREIGN_KEY_CHECKS=0;\n");
                                    $tablesStmt = $pdo->query('SHOW TABLES');
                                    $tables = $tablesStmt ? $tablesStmt->fetchAll(PDO::FETCH_COLUMN) : [];
                                    foreach ($tables as $table) {
                                        $createStmt = $pdo->query('SHOW CREATE TABLE `' . str_replace('`','``',$table) . '`');
                                        $createRow = $createStmt ? $createStmt->fetch(PDO::FETCH_ASSOC) : null;
                                        if ($createRow && isset($createRow['Create Table'])) {
                                            fwrite($fp, "\nDROP TABLE IF EXISTS `{$table}`;\n" . $createRow['Create Table'] . ";\n");
                                        }
                                        $dataStmt = $pdo->query('SELECT * FROM `' . str_replace('`','``',$table) . '`');
                                        if ($dataStmt) {
                                            while ($row = $dataStmt->fetch(PDO::FETCH_ASSOC)) {
                                                $cols = array_map(function($c){ return '`' . str_replace('`','``',$c) . '`'; }, array_keys($row));
                                                $vals = array_map(function($v) use ($pdo){ return $v === null ? 'NULL' : $pdo->quote($v); }, array_values($row));
                                                fwrite($fp, "INSERT INTO `{$table}` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n");
                                            }
                                        }
                                    }
                                    fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
                                    fclose($fp);
                                    $message = 'MySQL Dump via PHP-Fallback erstellt';
                                }
                                $results['database'] = [
                                    'success' => true,
                                    'file' => $dumpFile,
                                    'size' => file_exists($dumpFile) ? filesize($dumpFile) : 0,
                                    'message' => $message
                                ];
                                break;
                            
                            case 'pgsql':
                            case 'postgresql':
                                $dumpFile = $databaseDir . '/database_backup.sql';
                                $pgDump = $findExecutable(['pg_dump','/usr/bin/pg_dump','/usr/local/bin/pg_dump']);
                                if (!$pgDump) {
                                    throw new Exception('pg_dump nicht gefunden');
                                }
                                $env = [ 'PGPASSWORD' => (string)Config::DB_PGSQL_PASS ];
                                $cmd = escapeshellcmd($pgDump)
                                    . ' -h ' . escapeshellarg(Config::DB_PGSQL_HOST)
                                    . ' -p ' . escapeshellarg((string)Config::DB_PGSQL_PORT)
                                    . ' -U ' . escapeshellarg(Config::DB_PGSQL_USER)
                                    . ' -F p -d ' . escapeshellarg(Config::DB_PGSQL_NAME);
                                $descriptors = [ 1 => ['file', $dumpFile, 'w'], 2 => ['pipe','w'] ];
                                $proc = @proc_open($cmd, $descriptors, $pipes, null, $env);
                                if (!is_resource($proc)) {
                                    throw new Exception('pg_dump konnte nicht gestartet werden');
                                }
                                $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
                                $exit = proc_close($proc);
                                if ($exit !== 0) {
                                    throw new Exception('pg_dump Fehler: ' . trim((string)$stderr));
                                }
                                $results['database'] = [
                                    'success' => true,
                                    'file' => $dumpFile,
                                    'size' => file_exists($dumpFile) ? filesize($dumpFile) : 0,
                                    'message' => 'PostgreSQL Dump via pg_dump erstellt'
                                ];
                                break;
                            
                            case 'sqlite':
                                $sqliteFile = __DIR__ . '/../../' . Config::DB_SQLITE_PATH;
                                $target = $databaseDir . '/database.sqlite';
                                if (!file_exists($sqliteFile)) {
                                    throw new Exception('SQLite-Datei nicht gefunden: ' . $sqliteFile);
                                }
                                if (!copy($sqliteFile, $target)) {
                                    throw new Exception('SQLite-Datei konnte nicht kopiert werden');
                                }
                                $results['database'] = [
                                    'success' => true,
                                    'file' => $target,
                                    'size' => filesize($target),
                                    'message' => 'SQLite Datei-Backup erstellt'
                                ];
                                break;
                            
                            case 'mongodb':
                                $archive = $databaseDir . '/mongodb_dump.archive.gz';
                                $mongodump = $findExecutable(['mongodump','/usr/bin/mongodump','/usr/local/bin/mongodump']);
                                if (!$mongodump) {
                                    throw new Exception('mongodump nicht gefunden');
                                }
                                $cmd = escapeshellcmd($mongodump)
                                    . ' --host ' . escapeshellarg(Config::DB_MONGO_HOST)
                                    . ' --port ' . escapeshellarg((string)Config::DB_MONGO_PORT)
                                    . ' --db ' . escapeshellarg(Config::DB_MONGO_NAME)
                                    . (Config::DB_MONGO_USER ? (' -u ' . escapeshellarg(Config::DB_MONGO_USER)) : '')
                                    . (Config::DB_MONGO_PASS ? (' -p ' . escapeshellarg(Config::DB_MONGO_PASS)) : '')
                                    . ' --archive --gzip';
                                $descriptors = [ 1 => ['file', $archive, 'w'], 2 => ['pipe','w'] ];
                                $proc = @proc_open($cmd, $descriptors, $pipes);
                                if (!is_resource($proc)) {
                                    throw new Exception('mongodump konnte nicht gestartet werden');
                                }
                                $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
                                $exit = proc_close($proc);
                                if ($exit !== 0) {
                                    throw new Exception('mongodump Fehler: ' . trim((string)$stderr));
                                }
                                $results['database'] = [
                                    'success' => true,
                                    'file' => $archive,
                                    'size' => file_exists($archive) ? filesize($archive) : 0,
                                    'message' => 'MongoDB Dump via mongodump erstellt'
                                ];
                                break;
                            
                            default:
                                throw new Exception('Datenbanktyp nicht unterstützt: ' . $driverType);
                        }
                        
                    } catch (Exception $e) {
                        $results['database'] = [
                            'success' => false,
                            'error' => $e->getMessage()
                        ];
                        throw new Exception('Datenbank-Backup fehlgeschlagen: ' . $e->getMessage());
                    }
                }
                
                // Backup-Info erstellen
                $backupInfo = [
                    'created' => date('Y-m-d H:i:s'),
                    'type' => 'manual',
                    'include_database' => $includeDatabase,
                    'simple_backup' => $simpleBackup,
                    'files_count' => $filesCount,
                    'database_size' => $includeDatabase ? ($results['database']['size'] ?? 0) : 0
                ];
                
                file_put_contents($tempDir . '/backup_info.json', json_encode($backupInfo, JSON_PRETTY_PRINT));
                
                // Finale ZIP-Datei erstellen (kompatible Exclude-Version)
                if (!class_exists('ZipArchive')) {
                    throw new Exception('ZIP Extension nicht verfügbar');
                }
                
                $zip = new ZipArchive();
                $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
                
                if ($result !== TRUE) {
                    throw new Exception('ZIP-Datei konnte nicht erstellt werden: ' . $result);
                }
                
                // Helper: rekursiv Verzeichnis zur ZIP hinzufügen mit Excludes
                $addDirectoryToZipCompat = function($zipObj, $sourceDirPath, $zipRelativePath, $excludePatterns) use (&$addDirectoryToZipCompat) {
                    $items = @scandir($sourceDirPath);
                    if ($items === false) {
                        return;
                    }
                    foreach ($items as $item) {
                        if ($item === '.' || $item === '..') {
                            continue;
                        }
                        $sourcePath = $sourceDirPath . '/' . $item;
                        $targetPath = $zipRelativePath !== '' ? ($zipRelativePath . '/' . $item) : $item;
                        // Normalisierte Pfadangabe relativ innerhalb des ZIPs
                        $normPath = ltrim(str_replace('\\', '/', $targetPath), '/');
                        // Exclude prüfen
                        $shouldExclude = false;
                        foreach ($excludePatterns as $pattern) {
                            if (fnmatchCompat($pattern, $normPath)) {
                                $shouldExclude = true;
                                break;
                            }
                        }
                        if ($shouldExclude) {
                            continue;
                        }
                        if (is_file($sourcePath)) {
                            $zipObj->addFile($sourcePath, $targetPath);
                        } elseif (is_dir($sourcePath)) {
                            $zipObj->addEmptyDir($targetPath);
                            $addDirectoryToZipCompat($zipObj, $sourcePath, $targetPath, $excludePatterns);
                        }
                    }
                };

                // Exclude-Patterns für ZIP erstellen (identisch zur Kopierphase)
                $zipExclude = [
                    '**/backups/**',
                    '**/node_modules/**',
                    '**/.git/**',
                    '**/vendor/**',
                    '**/cache/**',
                    '**/logs/**',
                    '**/stats/**',
                    '**/error/**',
                    '**/examples/**',
                    '**/.gitignore',
                    '**/.htaccess'
                ];

                $addDirectoryToZipCompat($zip, $tempDir, '', $zipExclude);
                $zip->close();
                
                // Prüfen ob ZIP-Datei erstellt wurde
                if (!file_exists($zipPath) || filesize($zipPath) === 0) {
                    throw new Exception('ZIP-Datei wurde nicht korrekt erstellt');
                }
                
                // Ausgeschlossene Ordner im tmp-Verzeichnis löschen (nach ZIP-Erstellung)
                $excludeDirs = ['backups', 'node_modules', '.git', 'vendor', 'cache', 'logs', 'stats', 'error', 'examples'];
                foreach ($excludeDirs as $excludeDir) {
                    $excludePath = $filesBackupDir . '/' . $excludeDir;
                    if (is_dir($excludePath)) {
                        // Rekursiv löschen direkt implementiert
                        $items = @scandir($excludePath);
                        if ($items !== false) {
                            foreach ($items as $item) {
                                if ($item === '.' || $item === '..') {
                                    continue;
                                }
                                
                                $itemPath = $excludePath . '/' . $item;
                                
                                if (is_dir($itemPath)) {
                                    // Rekursiv Unterverzeichnisse löschen
                                    $subItems = @scandir($itemPath);
                                    if ($subItems !== false) {
                                        foreach ($subItems as $subItem) {
                                            if ($subItem === '.' || $subItem === '..') {
                                                continue;
                                            }
                                            $subItemPath = $itemPath . '/' . $subItem;
                                            if (is_file($subItemPath)) {
                                                @unlink($subItemPath);
                                            } elseif (is_dir($subItemPath)) {
                                                // Tiefere Ebenen löschen
                                                $deepItems = @scandir($subItemPath);
                                                if ($deepItems !== false) {
                                                    foreach ($deepItems as $deepItem) {
                                                        if ($deepItem === '.' || $deepItem === '..') {
                                                            continue;
                                                        }
                                                        $deepItemPath = $subItemPath . '/' . $deepItem;
                                                        if (is_file($deepItemPath)) {
                                                            @unlink($deepItemPath);
                                                        }
                                                    }
                                                }
                                                @rmdir($subItemPath);
                                            }
                                        }
                                    }
                                    @rmdir($itemPath);
                                } elseif (is_file($itemPath)) {
                                    @unlink($itemPath);
                                }
                            }
                        }
                        @rmdir($excludePath);
                    }
                }
                
                // Temporäres Verzeichnis aus tmp löschen (kompatibel)
                if (is_dir($tempDir)) {
                    deleteDirectoryRecursive($tempDir);
                }
                
                $response = [
                    'success' => true,
                    'message' => 'Backup erfolgreich erstellt',
                    'backup_path' => $zipPath,
                    'backup_name' => $backupName . '.zip',
                    'backup_size' => filesize($zipPath),
                    'results' => $results,
                    'info' => $backupInfo
                ];
                
            } catch (Exception $e) {
                // Temporäres Verzeichnis aufräumen bei Fehler (kompatibel)
                if (isset($tempDir) && is_dir($tempDir)) {
                    deleteDirectoryRecursive($tempDir);
                }
                
                $response = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
            break;
            
        case 'list_backups':
            $response = $updater->listBackups();
            break;
            
        case 'test_backup':
            $response = ['success' => true, 'message' => 'Test erfolgreich', 'timestamp' => date('Y-m-d H:i:s')];
            break;
            
        case 'debug_backup':
            // Einfache Debug-Informationen direkt im Handler
            $debugInfo = [];
            $debugInfo[] = "=== BACKUP DEBUG INFO ===";
            $debugInfo[] = "PHP Version: " . PHP_VERSION;
            $debugInfo[] = "ZIP Extension: " . (class_exists('ZipArchive') ? 'Verfügbar' : 'Nicht verfügbar');
            $debugInfo[] = "Temp Directory: " . sys_get_temp_dir();
            $debugInfo[] = "Temp Directory writable: " . (is_writable(sys_get_temp_dir()) ? 'Ja' : 'Nein');
            $debugInfo[] = "Backup Directory: " . __DIR__ . '/../../backups';
            $debugInfo[] = "Backup Directory exists: " . (is_dir(__DIR__ . '/../../backups') ? 'Ja' : 'Nein');
            $debugInfo[] = "Backup Directory writable: " . (is_writable(__DIR__ . '/../../backups') ? 'Ja' : 'Nein');
            
            // Test ob ManualUpdater-Klasse funktioniert
            try {
                $testUpdater = new ManualUpdater();
                $debugInfo[] = "ManualUpdater-Klasse: OK";
                
                // Test ob Methoden existieren
                $debugInfo[] = "backupFiles-Methode: " . (method_exists($testUpdater, 'backupFiles') ? 'OK' : 'FEHLT');
                $debugInfo[] = "backupDatabase-Methode: " . (method_exists($testUpdater, 'backupDatabase') ? 'OK' : 'FEHLT');
                $debugInfo[] = "createZipFromDirectory-Methode: " . (method_exists($testUpdater, 'createZipFromDirectory') ? 'OK' : 'FEHLT');
                
            } catch (Exception $e) {
                $debugInfo[] = "ManualUpdater-Klasse Fehler: " . $e->getMessage();
            }
            
            $response = [
                'success' => true,
                'message' => 'Debug-Informationen gesammelt',
                'debug_info' => $debugInfo
            ];
            break;
            
        case 'test_zip':
            $response = $updater->testZipCreation();
            break;
            
        case 'test_backup_steps':
            $includeDatabase = filter_var($_POST['include_database'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $simpleBackup = filter_var($_POST['simple_backup'] ?? false, FILTER_VALIDATE_BOOLEAN);
            
            // Einfacher Backup-Schritte-Test direkt im Handler
            $steps = [];
            $results = [];
            
            try {
                $backupDir = __DIR__ . '/../../backups';
                $testDir = $backupDir . '/test_steps_' . time();
                
                // Test-Verzeichnis erstellen
                if (!mkdir($testDir, 0755, true)) {
                    throw new Exception('Test-Verzeichnis konnte nicht erstellt werden');
                }
                $steps[] = 'Test-Verzeichnis erstellt: OK';
                
                // Schritt 1: Einfaches Dateien-Backup testen
                try {
                    $steps[] = 'Dateien-Backup wird getestet...';
                    $filesDir = $testDir . '/files';
                    if (!mkdir($filesDir, 0755, true)) {
                        throw new Exception('Files-Verzeichnis konnte nicht erstellt werden');
                    }
                    
                    // Test-Datei erstellen
                    $testFile = $filesDir . '/test.txt';
                    file_put_contents($testFile, 'Test-Datei vom ' . date('Y-m-d H:i:s'));
                    
                    $steps[] = 'Dateien-Backup erfolgreich: Test-Datei erstellt';
                    $results['files'] = ['success' => true, 'message' => 'Test-Datei erstellt'];
                    
                } catch (Exception $e) {
                    $steps[] = 'Dateien-Backup fehlgeschlagen: ' . $e->getMessage();
                    $results['files'] = ['success' => false, 'error' => $e->getMessage()];
                }
                
                // Schritt 2: Einfaches Datenbank-Backup testen (nur wenn gewünscht)
                if ($includeDatabase) {
                    try {
                        $steps[] = 'Datenbank-Backup wird getestet...';
                        $dbDir = $testDir . '/database';
                        if (!mkdir($dbDir, 0755, true)) {
                            throw new Exception('Database-Verzeichnis konnte nicht erstellt werden');
                        }
                        
                        // Test-Datenbank-Datei erstellen
                        $dbFile = $dbDir . '/database_backup.sql';
                        file_put_contents($dbFile, '-- Test-Datenbank-Backup vom ' . date('Y-m-d H:i:s') . "\n-- Dies ist nur ein Test\n");
                        
                        $steps[] = 'Datenbank-Backup erfolgreich: Test-Datei erstellt';
                        $results['database'] = ['success' => true, 'message' => 'Test-Datenbank-Datei erstellt'];
                        
                    } catch (Exception $e) {
                        $steps[] = 'Datenbank-Backup fehlgeschlagen: ' . $e->getMessage();
                        $results['database'] = ['success' => false, 'error' => $e->getMessage()];
                    }
                }
                
                // Schritt 3: ZIP-Erstellung testen
                try {
                    $steps[] = 'ZIP-Erstellung wird getestet...';
                    $testZip = $backupDir . '/test_steps_' . time() . '.zip';
                    
                    if (!class_exists('ZipArchive')) {
                        throw new Exception('ZIP Extension nicht verfügbar');
                    }
                    
                    $zip = new ZipArchive();
                    $result = $zip->open($testZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
                    
                    if ($result !== TRUE) {
                        throw new Exception('ZIP-Datei konnte nicht erstellt werden: ' . $result);
                    }
                    
                    // Test-Dateien zur ZIP hinzufügen
                    if (is_dir($testDir)) {
                        $items = scandir($testDir);
                        foreach ($items as $item) {
                            if ($item !== '.' && $item !== '..') {
                                $itemPath = $testDir . '/' . $item;
                                if (is_file($itemPath)) {
                                    $zip->addFile($itemPath, $item);
                                } elseif (is_dir($itemPath)) {
                                    $zip->addEmptyDir($item);
                                    $subItems = scandir($itemPath);
                                    foreach ($subItems as $subItem) {
                                        if ($subItem !== '.' && $subItem !== '..') {
                                            $subItemPath = $itemPath . '/' . $subItem;
                                            if (is_file($subItemPath)) {
                                                $zip->addFile($subItemPath, $item . '/' . $subItem);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    
                    $zip->close();
                    
                    if (file_exists($testZip)) {
                        $steps[] = 'ZIP-Erstellung erfolgreich: ' . filesize($testZip) . ' Bytes';
                        unlink($testZip); // Test-ZIP löschen
                    } else {
                        $steps[] = 'ZIP-Erstellung fehlgeschlagen: Datei wurde nicht erstellt';
                    }
                    
                } catch (Exception $e) {
                    $steps[] = 'ZIP-Erstellung fehlgeschlagen: ' . $e->getMessage();
                }
                
                // Aufräumen - einfache Löschung
                if (is_dir($testDir)) {
                    $items = scandir($testDir);
                    foreach ($items as $item) {
                        if ($item !== '.' && $item !== '..') {
                            $itemPath = $testDir . '/' . $item;
                            if (is_file($itemPath)) {
                                unlink($itemPath);
                            } elseif (is_dir($itemPath)) {
                                $subItems = scandir($itemPath);
                                foreach ($subItems as $subItem) {
                                    if ($subItem !== '.' && $subItem !== '..') {
                                        unlink($itemPath . '/' . $subItem);
                                    }
                                }
                                rmdir($itemPath);
                            }
                        }
                    }
                    rmdir($testDir);
                }
                $steps[] = 'Test-Verzeichnis gelöscht: OK';
                
                $response = [
                    'success' => true,
                    'message' => 'Backup-Schritte getestet',
                    'steps' => $steps,
                    'results' => $results
                ];
                
            } catch (Exception $e) {
                $response = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'steps' => $steps
                ];
            }
            break;
            
        case 'download_backup':
            $backupName = $_POST['backup_name'] ?? '';
            $response = $updater->downloadBackup($backupName);
            break;
            
        case 'delete_backup':
            $backupName = $_POST['backup_name'] ?? '';
            $response = $updater->deleteBackup($backupName);
            break;
            
        case 'delete_all_backups':
            $response = $updater->deleteAllBackups();
            break;
            
        default:
            $response = ['success' => false, 'error' => 'Unbekannte Aktion'];
        }
        
    } catch (Exception $e) {
        $response = ['success' => false, 'error' => $e->getMessage()];
    }
    
    // Output buffer leeren und JSON ausgeben
    ob_clean();
    echo json_encode($response);
    exit;
}
?>


<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h1 class="mb-0">
                    <i class="bi bi-arrow-clockwise"></i>
                    <?= t('manual_updater_title') ?>
                </h1>
            </div>
            <div class="card-body">
                
                <!-- Nightly Version Warnung -->
                <div id="nightly-warning" class="nightly-warning" style="display: none;">
                    <h5><i class="bi bi-exclamation-triangle"></i> <?= t('nightly_version_detected') ?></h5>
                    <p class="mb-0">
                        <?= t('nightly_version_warning') ?>
                    </p>
                </div>
                
                <!-- System Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="bi bi-info-circle"></i> <?= t('system_info') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong><?= t('current_version') ?>:</strong> <span class="badge bg-primary version-badge" id="current-version">-</span></p>
                                <p><strong><?= t('changelog_version') ?>:</strong> <span class="badge bg-info version-badge" id="changelog-version">-</span></p>
                                <p><strong>Status:</strong> <span class="badge" id="version-status">-</span></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong><?= t('php_version') ?>:</strong> <span id="php-version">-</span></p>
                                <p><strong><?= t('zip_extension') ?>:</strong> <span class="badge" id="zip-status">-</span></p>
                                <p><strong><?= t('temp_directory') ?>:</strong> <span id="temp-dir">-</span></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Update Check -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="bi bi-search"></i> <?= t('update_check') ?></h5>
                    </div>
                    <div class="card-body">
                        <button class="btn btn-primary" id="check-updates-btn">
                            <i class="bi bi-arrow-clockwise"></i> <?= t('check_for_updates') ?>
                        </button>
                        <div id="update-info" class="mt-3" style="display: none;">
                            <p><strong><?= t('github_version') ?>:</strong> <span class="badge bg-success version-badge" id="github-version">-</span></p>
                            <p><strong><?= t('update_available') ?>:</strong> <span class="badge" id="update-available">-</span></p>
                        </div>
                    </div>
                </div>
                
                <!-- Update Auswahl -->
                <div class="card mb-4" id="update-selection" style="display: none;">
                    <div class="card-header">
                        <h5><i class="bi bi-gear"></i> <?= t('select_update_type') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="update-option" data-type="framework">
                                    <h6><i class="bi bi-cpu"></i> <?= t('framework_only') ?></h6>
                                    <p class="text-muted mb-0">
                                        <?= t('framework_only_description') ?>
                                    </p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="update-option" data-type="full">
                                    <h6><i class="bi bi-layers"></i> <?= t('full_update') ?></h6>
                                    <p class="text-muted mb-0">
                                        <?= t('full_update_description') ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="create-backup">
                                <label class="form-check-label" for="create-backup">
                                    <?= t('create_backup') ?>
                                </label>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <button class="btn btn-success" id="start-update-btn" disabled>
                                <i class="bi bi-download"></i> <?= t('start_update') ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Backup Management -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="bi bi-shield-check"></i> <?= t('backup_management') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><?= t('create_backup') ?></h6>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="include-database" checked>
                                        <label class="form-check-label" for="include-database">
                                            <?= t('include_database_backup') ?>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="simple-backup">
                                        <label class="form-check-label" for="simple-backup">
                                            Nur wichtige Dateien sichern (schneller)
                                        </label>
                                    </div>
                                </div>
                                <button class="btn btn-success" id="create-backup-btn">
                                    <i class="bi bi-shield-plus"></i> <?= t('create_backup') ?>
                                </button>
                                <button class="btn btn-outline-info btn-sm ms-2" id="test-backup-btn">
                                    <i class="bi bi-bug"></i> Test
                                </button>
                                <button class="btn btn-outline-dark btn-sm ms-1" id="debug-backup-btn">
                                    <i class="bi bi-search"></i> Debug
                                </button>
                                <button class="btn btn-outline-warning btn-sm ms-1" id="test-zip-btn">
                                    <i class="bi bi-file-zip"></i> ZIP-Test
                                </button>
                                <button class="btn btn-outline-secondary btn-sm ms-1" id="test-backup-steps-btn">
                                    <i class="bi bi-list-check"></i> Schritte-Test
                                </button>
                            </div>
                            <div class="col-md-6">
                                <h6><?= t('existing_backups') ?></h6>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-outline-primary" id="refresh-backups-btn">
                                        <i class="bi bi-arrow-clockwise"></i> <?= t('refresh_backups') ?>
                                    </button>
                                    <button class="btn btn-outline-danger" id="delete-all-backups-btn" title="Alle Backups löschen">
                                        <i class="bi bi-trash"></i> Alle löschen
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Backup List -->
                        <div id="backup-list" class="mt-4" style="display: none;">
                            <h6><?= t('backup_list') ?></h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th><?= t('backup_name') ?></th>
                                            <th><?= t('created') ?></th>
                                            <th><?= t('type') ?></th>
                                            <th><?= t('size') ?></th>
                                            <th><?= t('actions') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="backup-table-body">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Progress -->
                <div class="card mb-4 progress-container" id="progress-container">
                    <div class="card-header">
                        <h5><i class="bi bi-hourglass-split"></i> <?= t('update_progress') ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="progress mb-3">
                            <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                        </div>
                        <div id="progress-text"><?= t('update_preparing') ?></div>
                    </div>
                </div>
                
                <!-- Logs -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-list-ul"></i> <?= t('update_log') ?></h5>
                    </div>
                    <div class="card-body">
                        <div id="update-log" style="max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 0.9em;">
                            <div class="text-muted"><?= t('ready_for_update') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- Manual Updater JavaScript -->
<script>
        let selectedUpdateType = null;
        let currentReleaseData = null;
        
        // System-Informationen laden
        document.addEventListener('DOMContentLoaded', function() {
            loadSystemInfo();
        });
        
        // Update-Typ Auswahl
        document.querySelectorAll('.update-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.update-option').forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                selectedUpdateType = this.dataset.type;
                document.getElementById('start-update-btn').disabled = false;
            });
        });
        
        // Update-Check Button
        document.getElementById('check-updates-btn').addEventListener('click', function() {
            this.disabled = true;
                this.innerHTML = '<i class="bi bi-hourglass-split"></i> ' + window.jsTranslations.check_for_updates + '...';
            
            fetch('?option=manualupdater', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=check_updates'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateSystemInfo(data);
                    currentReleaseData = data.release_data;
                    
                    if (data.update_available) {
                        document.getElementById('update-selection').style.display = 'block';
                        addLog(window.jsTranslations.update_available_msg + ': ' + data.github_version, 'success');
                    } else {
                        addLog(window.jsTranslations.no_update_available, 'info');
                    }
                } else {
                    addLog(window.jsTranslations.update_check_error + ': ' + data.error, 'error');
                }
            })
            .catch(error => {
                addLog('Netzwerkfehler: ' + error.message, 'error');
            })
            .finally(() => {
                this.disabled = false;
                this.innerHTML = '<i class="bi bi-arrow-clockwise"></i> ' + window.jsTranslations.check_for_updates;
            });
        });
        
        // Update starten
        document.getElementById('start-update-btn').addEventListener('click', function() {
            if (!selectedUpdateType || !currentReleaseData) {
                addLog(window.jsTranslations.select_update_type_error, 'error');
                return;
            }
            
            const createBackup = document.getElementById('create-backup').checked;
            
            this.disabled = true;
                this.innerHTML = '<i class="bi bi-hourglass-split"></i> ' + window.jsTranslations.start_update + '...';
            
            document.getElementById('progress-container').style.display = 'block';
            updateProgress(0, 'Update wird heruntergeladen...');
            
            // Download starten
            fetch('?option=manualupdater', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=download_update&tag_name=${currentReleaseData.tag_name}&update_type=${selectedUpdateType}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateProgress(50, 'Update wird installiert...');
                    addLog('Download erfolgreich: ' + data.asset_name, 'success');
                    
                    // Installation starten
                    return fetch('?option=manualupdater', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=install_update&download_path=${encodeURIComponent(data.download_path)}&create_backup=${createBackup}`
                    });
                } else {
                    throw new Error(data.error);
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateProgress(100, 'Update erfolgreich abgeschlossen!');
                    addLog(window.jsTranslations.update_successful, 'success');
                    addLog(window.jsTranslations.reload_page_info, 'info');
                } else {
                    throw new Error(data.error);
                }
            })
            .catch(error => {
                addLog('Update fehlgeschlagen: ' + error.message, 'error');
                updateProgress(0, 'Update fehlgeschlagen');
            })
            .finally(() => {
                this.disabled = false;
                this.innerHTML = '<i class="bi bi-download"></i> ' + window.jsTranslations.start_update;
            });
        });
        
        function loadSystemInfo() {
            fetch('?option=manualupdater', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=system_info'
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('current-version').textContent = data.current_version;
                document.getElementById('changelog-version').textContent = data.changelog_version;
                document.getElementById('php-version').textContent = data.php_version;
                document.getElementById('temp-dir').textContent = data.temp_dir;
                
                // Version Status
                const statusBadge = document.getElementById('version-status');
                if (data.is_nightly) {
                    statusBadge.textContent = window.jsTranslations.nightly_version;
                    statusBadge.className = 'badge bg-warning';
                    document.getElementById('nightly-warning').style.display = 'block';
                } else {
                    statusBadge.textContent = window.jsTranslations.stable_version;
                    statusBadge.className = 'badge bg-success';
                }
                
                // ZIP Status
                const zipBadge = document.getElementById('zip-status');
                if (data.zip_extension) {
                    zipBadge.textContent = window.jsTranslations.zip_available;
                    zipBadge.className = 'badge bg-success';
                } else {
                    zipBadge.textContent = window.jsTranslations.zip_not_available;
                    zipBadge.className = 'badge bg-danger';
                }
            })
            .catch(error => {
                addLog('Fehler beim Laden der System-Informationen: ' + error.message, 'error');
            });
        }
        
        function updateSystemInfo(data) {
            document.getElementById('github-version').textContent = data.github_version;
            
            const updateBadge = document.getElementById('update-available');
            if (data.update_available) {
                updateBadge.textContent = window.jsTranslations.yes;
                updateBadge.className = 'badge bg-success';
            } else {
                updateBadge.textContent = window.jsTranslations.no;
                updateBadge.className = 'badge bg-secondary';
            }
            
            document.getElementById('update-info').style.display = 'block';
        }
        
        function updateProgress(percent, text) {
            const progressBar = document.querySelector('.progress-bar');
            const progressText = document.getElementById('progress-text');
            
            progressBar.style.width = percent + '%';
            progressBar.setAttribute('aria-valuenow', percent);
            progressText.textContent = text;
        }
        
        function addLog(message, type = 'info') {
            const log = document.getElementById('update-log');
            const timestamp = new Date().toLocaleTimeString();
            const colors = {
                'info': '#6c757d',
                'success': '#198754',
                'error': '#dc3545',
                'warning': '#fd7e14'
            };
            
            const logEntry = document.createElement('div');
            logEntry.style.color = colors[type] || colors.info;
            logEntry.innerHTML = `[${timestamp}] ${message}`;
            
            log.appendChild(logEntry);
            log.scrollTop = log.scrollHeight;
        }
        
        // Backup-Funktionalität
        document.getElementById('create-backup-btn').addEventListener('click', function() {
            this.disabled = true;
            this.innerHTML = '<i class="bi bi-hourglass-split"></i> ' + window.jsTranslations.creating_backup + '...';
            
            const includeDatabase = document.getElementById('include-database').checked;
            const simpleBackup = document.getElementById('simple-backup').checked;
            
            fetch('?option=manualupdater', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=create_backup&include_database=${includeDatabase}&simple_backup=${simpleBackup}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.text();
            })
            .then(text => {
                if (!text.trim()) {
                    throw new Error('Leere Server-Antwort erhalten');
                }
                
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        addLog(window.jsTranslations.backup_successfully_created, 'success');
                        if (data.results && data.results.files) {
                            addLog(window.jsTranslations.files_backed_up + ': ' + data.results.files.files_count, 'info');
                        }
                        if (data.results && data.results.database && data.results.database.success) {
                            addLog(window.jsTranslations.database_backed_up, 'info');
                        }
                        loadBackupList();
                    } else {
                        addLog(window.jsTranslations.backup_failed + ': ' + (data.error || 'Unbekannter Fehler'), 'error');
                    }
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.error('Response Text:', text);
                    addLog('Server-Antwort konnte nicht verarbeitet werden: ' + parseError.message, 'error');
                    addLog('Server-Antwort: ' + text.substring(0, 200) + (text.length > 200 ? '...' : ''), 'error');
                }
            })
            .catch(error => {
                console.error('Backup Error:', error);
                addLog(window.jsTranslations.backup_error + ': ' + error.message, 'error');
            })
            .finally(() => {
                this.disabled = false;
                this.innerHTML = '<i class="bi bi-shield-plus"></i> ' + window.jsTranslations.create_backup;
            });
        });
        
        document.getElementById('refresh-backups-btn').addEventListener('click', function() {
            loadBackupList();
        });
        
        document.getElementById('delete-all-backups-btn').addEventListener('click', function() {
            if (confirm('Alle Backups wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden!')) {
                this.disabled = true;
                this.innerHTML = '<i class="bi bi-hourglass-split"></i> Lösche...';
                
                fetch('?option=manualupdater', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=delete_all_backups'
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.text();
                })
                .then(text => {
                    if (!text.trim()) {
                        throw new Error('Leere Server-Antwort erhalten');
                    }
                    
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            addLog(data.message, 'success');
                            loadBackupList(); // Liste aktualisieren
                        } else {
                            addLog('Alle Backups löschen fehlgeschlagen: ' + data.error, 'error');
                        }
                    } catch (parseError) {
                        console.error('JSON Parse Error:', parseError);
                        console.error('Response Text:', text);
                        addLog('Alle Backups löschen fehlgeschlagen - Server-Antwort konnte nicht verarbeitet werden', 'error');
                    }
                })
                .catch(error => {
                    console.error('Delete All Backups Error:', error);
                    addLog('Alle Backups löschen fehlgeschlagen: ' + error.message, 'error');
                })
                .finally(() => {
                    this.disabled = false;
                    this.innerHTML = '<i class="bi bi-trash"></i> Alle löschen';
                });
            }
        });
        
        // Debug-Button
        document.getElementById('debug-backup-btn').addEventListener('click', function() {
            this.disabled = true;
            this.innerHTML = '<i class="bi bi-hourglass-split"></i> Debug...';
            
            const includeDatabase = document.getElementById('include-database').checked;
            const simpleBackup = document.getElementById('simple-backup').checked;
            
            fetch('?option=manualupdater', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=debug_backup&include_database=${includeDatabase}&simple_backup=${simpleBackup}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.text();
            })
            .then(text => {
                if (!text.trim()) {
                    throw new Error('Leere Server-Antwort erhalten');
                }
                
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        addLog('Debug-Informationen gesammelt', 'success');
                        if (data.debug_info) {
                            data.debug_info.forEach(info => {
                                addLog(info, 'info');
                            });
                        }
                    } else {
                        addLog('Debug fehlgeschlagen: ' + data.error, 'error');
                        if (data.debug_info) {
                            data.debug_info.forEach(info => {
                                addLog(info, 'info');
                            });
                        }
                    }
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.error('Response Text:', text);
                    addLog('Debug fehlgeschlagen - Server-Antwort konnte nicht verarbeitet werden', 'error');
                }
            })
            .catch(error => {
                console.error('Debug Error:', error);
                addLog('Debug fehlgeschlagen: ' + error.message, 'error');
            })
            .finally(() => {
                this.disabled = false;
                this.innerHTML = '<i class="bi bi-search"></i> Debug';
            });
        });
        
        // ZIP-Test-Button
        document.getElementById('test-zip-btn').addEventListener('click', function() {
            this.disabled = true;
            this.innerHTML = '<i class="bi bi-hourglass-split"></i> ZIP-Test...';
            
            fetch('?option=manualupdater', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=test_zip'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.text();
            })
            .then(text => {
                if (!text.trim()) {
                    throw new Error('Leere Server-Antwort erhalten');
                }
                
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        addLog('ZIP-Test erfolgreich: ' + data.message, 'success');
                        addLog('ZIP Extension verfügbar: ' + (data.zip_available ? 'Ja' : 'Nein'), 'info');
                    } else {
                        addLog('ZIP-Test fehlgeschlagen: ' + data.error, 'error');
                        addLog('ZIP Extension verfügbar: ' + (data.zip_available ? 'Ja' : 'Nein'), 'info');
                    }
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.error('Response Text:', text);
                    addLog('ZIP-Test fehlgeschlagen - Server-Antwort konnte nicht verarbeitet werden', 'error');
                }
            })
            .catch(error => {
                console.error('ZIP Test Error:', error);
                addLog('ZIP-Test fehlgeschlagen: ' + error.message, 'error');
            })
            .finally(() => {
                this.disabled = false;
                this.innerHTML = '<i class="bi bi-file-zip"></i> ZIP-Test';
            });
        });
        
        // Backup-Schritte-Test-Button
        document.getElementById('test-backup-steps-btn').addEventListener('click', function() {
            this.disabled = true;
            this.innerHTML = '<i class="bi bi-hourglass-split"></i> Teste...';
            
            const includeDatabase = document.getElementById('include-database').checked;
            const simpleBackup = document.getElementById('simple-backup').checked;
            
            fetch('?option=manualupdater', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=test_backup_steps&include_database=${includeDatabase}&simple_backup=${simpleBackup}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.text();
            })
            .then(text => {
                if (!text.trim()) {
                    throw new Error('Leere Server-Antwort erhalten');
                }
                
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        addLog('Backup-Schritte-Test erfolgreich', 'success');
                        if (data.steps) {
                            data.steps.forEach(step => {
                                addLog(step, 'info');
                            });
                        }
                    } else {
                        addLog('Backup-Schritte-Test fehlgeschlagen: ' + data.error, 'error');
                    }
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.error('Response Text:', text);
                    addLog('Backup-Schritte-Test fehlgeschlagen - Server-Antwort konnte nicht verarbeitet werden', 'error');
                }
            })
            .catch(error => {
                console.error('Backup Steps Test Error:', error);
                addLog('Backup-Schritte-Test fehlgeschlagen: ' + error.message, 'error');
            })
            .finally(() => {
                this.disabled = false;
                this.innerHTML = '<i class="bi bi-list-check"></i> Schritte-Test';
            });
        });
        
        // Test-Button für Debugging
        document.getElementById('test-backup-btn').addEventListener('click', function() {
            this.disabled = true;
            this.innerHTML = '<i class="bi bi-hourglass-split"></i> Test...';
            
            fetch('?option=manualupdater', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=test_backup'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.text();
            })
            .then(text => {
                if (!text.trim()) {
                    throw new Error('Leere Server-Antwort erhalten');
                }
                
                try {
                    const data = JSON.parse(text);
                    addLog('Test erfolgreich: ' + data.message + ' (' + data.timestamp + ')', 'success');
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.error('Response Text:', text);
                    addLog('Test fehlgeschlagen - JSON Parse Error: ' + parseError.message, 'error');
                    addLog('Server-Antwort: ' + text.substring(0, 200) + (text.length > 200 ? '...' : ''), 'error');
                }
            })
            .catch(error => {
                console.error('Test Error:', error);
                addLog('Test fehlgeschlagen: ' + error.message, 'error');
            })
            .finally(() => {
                this.disabled = false;
                this.innerHTML = '<i class="bi bi-bug"></i> Test';
            });
        });
        
        function loadBackupList() {
            fetch('?option=manualupdater', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=list_backups'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.text();
            })
            .then(text => {
                if (!text.trim()) {
                    throw new Error('Leere Server-Antwort erhalten');
                }
                
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        displayBackupList(data.backups || []);
                    } else {
                        addLog(window.jsTranslations.backup_list_error + ': ' + (data.error || 'Unbekannter Fehler'), 'error');
                    }
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.error('Response Text:', text);
                    addLog('Backup-Liste konnte nicht geladen werden: ' + parseError.message, 'error');
                }
            })
            .catch(error => {
                console.error('Backup List Error:', error);
                addLog(window.jsTranslations.backup_list_error + ': ' + error.message, 'error');
            });
        }
        
        function displayBackupList(backups) {
            const backupList = document.getElementById('backup-list');
            const tableBody = document.getElementById('backup-table-body');
            
            if (backups.length === 0) {
                backupList.style.display = 'none';
                return;
            }
            
            backupList.style.display = 'block';
            tableBody.innerHTML = '';
            
            backups.forEach(backup => {
                const row = document.createElement('tr');
                
                // Backup-Typ bestimmen
                let typeBadge = '';
                if (backup.type === 'zip') {
                    typeBadge = '<span class="badge bg-primary">ZIP</span>';
                } else {
                    typeBadge = `<span class="badge ${backup.include_database ? 'bg-success' : 'bg-info'}">
                        ${backup.include_database ? window.jsTranslations.full_backup : window.jsTranslations.files_only}
                    </span>`;
                }
                
                // Actions bestimmen
                let actions = '';
                if (backup.downloadable && backup.type === 'zip') {
                    actions = `
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="downloadBackup('${backup.name}')" title="Download">
                            <i class="bi bi-download"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteBackup('${backup.name}')" title="Löschen">
                            <i class="bi bi-trash"></i>
                        </button>
                    `;
                } else {
                    actions = `
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteBackup('${backup.name}')" title="Löschen">
                            <i class="bi bi-trash"></i>
                        </button>
                    `;
                }
                
                row.innerHTML = `
                    <td>${backup.name}</td>
                    <td>${backup.created}</td>
                    <td>${typeBadge}</td>
                    <td>${formatFileSize(backup.size)}</td>
                    <td>${actions}</td>
                `;
                tableBody.appendChild(row);
            });
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        function downloadBackup(backupName) {
            // Erstelle einen versteckten Link für den Download
            const link = document.createElement('a');
            link.href = '?option=manualupdater&action=download_backup&backup_name=' + encodeURIComponent(backupName);
            link.download = backupName;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            addLog('Download gestartet: ' + backupName, 'info');
        }
        
        function deleteBackup(backupName) {
            if (confirm(window.jsTranslations.confirm_delete_backup)) {
                fetch('?option=manualupdater', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_backup&backup_name=${encodeURIComponent(backupName)}`
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.text();
                })
                .then(text => {
                    if (!text.trim()) {
                        throw new Error('Leere Server-Antwort erhalten');
                    }
                    
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            addLog('Backup erfolgreich gelöscht: ' + backupName, 'success');
                            loadBackupList(); // Liste aktualisieren
                        } else {
                            addLog('Backup-Löschung fehlgeschlagen: ' + data.error, 'error');
                        }
                    } catch (parseError) {
                        console.error('JSON Parse Error:', parseError);
                        console.error('Response Text:', text);
                        addLog('Backup-Löschung fehlgeschlagen - Server-Antwort konnte nicht verarbeitet werden', 'error');
                    }
                })
                .catch(error => {
                    console.error('Delete Backup Error:', error);
                    addLog('Backup-Löschung fehlgeschlagen: ' + error.message, 'error');
                });
            }
        }
        
        // Backup-Liste beim Laden der Seite aktualisieren
        loadBackupList();
</script>
