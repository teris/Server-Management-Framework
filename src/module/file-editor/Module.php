<?php
// ========================================
// File Editor Module
// modules/file-editor/Module.php
// ========================================

require_once dirname(dirname(__FILE__)) . '/ModuleBase.php';
require_once dirname(dirname(__FILE__)) . '/../core/FileEditor.php';

class FileEditorModule extends ModuleBase {
    
    private $fileEditor;
    
    public function __construct() {
        parent::__construct('file-editor');
        $this->fileEditor = new FileEditor();
        
    }
    
    public function getContent() {
        // Template direkt laden
        $template_path = __DIR__ . '/templates/main.php';
        if (file_exists($template_path)) {
            ob_start();
            include $template_path;
            return ob_get_clean();
        } else {
            return '<div class="alert alert-danger">Template nicht gefunden: ' . htmlspecialchars($template_path) . '</div>';
        }
    }
    
    public function handleAjaxRequest($action, $data) {
        
        switch ($action) {
            case 'get_file_content':
                return $this->getFileContent($data);
            case 'save_file':
                return $this->saveFile($data);
            case 'delete_file':
                return $this->deleteFile($data);
            case 'get_files_list':
                return $this->getFilesList($data);
            case 'check_permissions':
                return $this->checkPermissions($data);
            default:
                return $this->error('Unbekannte Aktion: ' . $action);
        }
    }
    
    /**
     * Datei-Inhalt abrufen
     */
    private function getFileContent($data) {
        $filePath = $data['file_path'] ?? '';
        
        if (empty($filePath) || !file_exists($filePath)) {
            return $this->error('Datei nicht gefunden: ' . $filePath);
        }
        
        // Datei als rohen Text laden (nicht ausführen)
        $content = file_get_contents($filePath);
        
        // Sicherstellen, dass PHP-Code nicht ausgeführt wird
        if ($this->detectFileType($filePath) === 'PHP') {
            // PHP-Code wird als reiner Text behandelt
            error_log("FileEditorModule: Loading PHP file as raw text: $filePath");
        }
        $fileType = $this->detectFileType($filePath);
        $isWritable = $this->checkWritePermission($filePath);
        
        // Debug-Log für Datei-Inhalt
        error_log("FileEditorModule: Loading file: $filePath, type: $fileType, size: " . strlen($content));
        
        return $this->success([
            'content' => $content,
            'file_type' => $fileType,
            'is_writable' => $isWritable,
            'file_name' => basename($filePath),
            'raw_content' => true // Immer als roher Text behandeln
        ]);
    }
    
    /**
     * Datei speichern
     */
    private function saveFile($data) {
        $filePath = $data['file_path'] ?? '';
        $content = $data['content'] ?? '';
        
        // Debug-Log
        error_log("FileEditorModule: saveFile called with path: $filePath");
        error_log("FileEditorModule: content length: " . strlen($content));
        error_log("FileEditorModule: file exists: " . (file_exists($filePath) ? 'yes' : 'no'));
        
        if (empty($filePath)) {
            return $this->error('Dateipfad fehlt');
        }
        
        if (!$this->checkWritePermission($filePath)) {
            return $this->error('Keine Schreibberechtigung');
        }
        
        // Backup erstellen
        if (file_exists($filePath)) {
            $pathInfo = pathinfo($filePath);
            $backupPath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.' . $pathInfo['extension'] . '.bnk';
            copy($filePath, $backupPath);
            error_log("FileEditorModule: Backup created: $backupPath");
        }
        
        // Datei speichern
        $result = file_put_contents($filePath, $content);
        if ($result !== false) {
            error_log("FileEditorModule: File saved successfully, bytes written: $result");
            return $this->success('Datei gespeichert');
        } else {
            error_log("FileEditorModule: Failed to save file");
            return $this->error('Fehler beim Speichern');
        }
    }
    
    /**
     * Datei löschen
     */
    private function deleteFile($data) {
        $filePath = $data['file_path'] ?? '';
        
        if (empty($filePath)) {
            return $this->error('Dateipfad fehlt');
        }
        
        if (!$this->checkWritePermission($filePath)) {
            return $this->error('Keine Schreibberechtigung');
        }
        
        if (unlink($filePath)) {
            return $this->success('Datei gelöscht');
        } else {
            return $this->error('Fehler beim Löschen');
        }
    }
    
    /**
     * Dateiliste abrufen
     */
    private function getFilesList($data) {
        $directory = $data['directory'] ?? '.';
        
        
        $files = $this->getFilesInDirectory($directory);
        
        
        return $this->success([
            'files' => $files,
            'directory' => $directory
        ]);
    }
    
    /**
     * Berechtigungen prüfen
     */
    private function checkPermissions($data) {
        $filePath = $data['file_path'] ?? '';
        
        if (empty($filePath)) {
            return $this->error('Dateipfad fehlt');
        }
        
        $isWritable = $this->checkWritePermission($filePath);
        $exists = file_exists($filePath);
        
        return $this->success([
            'exists' => $exists,
            'is_writable' => $isWritable
        ]);
    }
    
    /**
     * Dateien in Verzeichnis abrufen
     */
    private function getFilesInDirectory($directory) {
        $files = [];
        
        if (!is_dir($directory)) {
            return $files;
        }
        
        $items = scandir($directory);
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $fullPath = $directory . '/' . $item;
            $files[] = [
                'name' => $item,
                'path' => $fullPath,
                'is_dir' => is_dir($fullPath),
                'size' => is_file($fullPath) ? filesize($fullPath) : 0,
                'modified' => filemtime($fullPath),
                'type' => $this->detectFileType($fullPath),
                'is_writable' => $this->checkWritePermission($fullPath)
            ];
        }
        
        // Sortieren: Verzeichnisse zuerst, dann Dateien
        usort($files, function($a, $b) {
            if ($a['is_dir'] === $b['is_dir']) {
                return strcmp($a['name'], $b['name']);
            }
            return $a['is_dir'] ? -1 : 1;
        });
        
        return $files;
    }
    
    /**
     * Dateityp erkennen
     */
    private function detectFileType($filePath) {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        $types = [
            'html' => 'HTML',
            'htm' => 'HTML',
            'css' => 'CSS',
            'php' => 'PHP',
            'xml' => 'XML',
            'json' => 'JSON',
            'js' => 'JavaScript',
            'sql' => 'SQL',
            'txt' => 'Text',
            'md' => 'Markdown',
            'yml' => 'YAML',
            'yaml' => 'YAML',
            'ini' => 'INI',
            'conf' => 'INI',
            'sh' => 'Shell',
            'bash' => 'Shell'
        ];
        
        return $types[$extension] ?? 'Unknown';
    }
    
    /**
     * Schreibberechtigung prüfen
     */
    private function checkWritePermission($filePath) {
        if (!file_exists($filePath)) {
            // Prüfe ob das Verzeichnis beschreibbar ist
            $dir = dirname($filePath);
            return is_writable($dir);
        }
        
        return is_writable($filePath);
    }
    
    /**
     * Dateigröße formatieren
     */
    private function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Strukturierte Daten-Editor rendern
     */
    public function renderStructuredDataEditor($filePath, $content, $fileType, $isWritable) {
        return $this->fileEditor->renderStructuredDataEditor($filePath, $content, $fileType, $isWritable);
    }
    
    /**
     * Erweiterte Editor mit Farbcode-Erkennung rendern
     */
    public function renderAdvancedEditor($filePath, $content, $fileType, $isWritable) {
        return $this->fileEditor->renderAdvancedEditor($filePath, $content, $fileType, $isWritable);
    }
}

// Alias für Kompatibilität mit dem erwarteten Klassennamen
class_alias('FileEditorModule', 'File-editorModule');
?>
