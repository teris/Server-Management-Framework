<?php
/**
 * E-Mail-Template-Editor
 * 
 * TODO: Spezialisierter Editor für E-Mail-Templates
 * TODO: Basiert auf FileEditor mit Template-spezifischen Features
 * 
 * @author Teris
 * @version 3.1.2
 */

require_once __DIR__ . '/FileEditor.php';

class EmailTemplateEditor extends FileEditor {
    
    private $db;
    private $templateVariables = [
        '{firstName}' => 'Vorname des Empfängers',
        '{lastName}' => 'Nachname des Empfängers',
        '{email}' => 'E-Mail-Adresse des Empfängers',
        '{username}' => 'Benutzername',
        '{password}' => 'Passwort',
        '{loginUrl}' => 'Login-URL',
        '{verificationLink}' => 'Verifizierungs-Link',
        '{site_name}' => 'Name der Website',
        '{systemCredentials}' => 'System-Anmeldedaten (HTML)'
    ];
    
    public function __construct() {
        parent::__construct();
        if (!isset($this->db)) {
            require_once dirname(__DIR__) . '/core/DatabaseManager.php';
            $this->db = DatabaseManager::getInstance();
        }
    }
    
    /**
     * E-Mail-Template-Editor laden
     * 
     * @param int $templateId Template-ID
     * @param string $contentType 'html' oder 'raw'
     * @return string HTML-Editor
     */
    public function loadTemplateEditor($templateId, $contentType = 'html') {
        $options = [
            'height' => '500px',
            'theme' => 'github',
            'fontSize' => 14,
            'showLineNumbers' => true,
            'enableAutocomplete' => true,
            'enableSnippets' => true,
            'wrap' => true,
            'readOnly' => false,
            'showToolbar' => true,
            'showVariables' => true,
            'variables' => array_keys($this->templateVariables)
        ];
        
        return $this->loadDatabaseEditor($templateId, $contentType, $options);
    }
    
    /**
     * Template-Editor mit Vorschau laden
     * 
     * @param int $templateId Template-ID
     * @param string $contentType 'html' oder 'raw'
     * @return string HTML-Editor mit Vorschau
     */
    public function loadTemplateEditorWithPreview($templateId, $contentType = 'html') {
        $editor = $this->loadTemplateEditor($templateId, $contentType);
        
        $previewHtml = '
        <div class="row mt-3">
            <div class="col-md-6">
                <h6>Editor</h6>
                ' . $editor . '
            </div>
            <div class="col-md-6">
                <h6>Vorschau</h6>
                <div id="template-preview" class="border p-3" style="height: 500px; overflow-y: auto; background: #f8f9fa;">
                    <p class="text-muted">Vorschau wird hier angezeigt...</p>
                </div>
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="updatePreview()">
                        <i class="bi bi-eye"></i> Vorschau aktualisieren
                    </button>
                </div>
            </div>
        </div>';
        
        return $previewHtml;
    }
    
    /**
     * Template-Variablen-Info generieren
     * 
     * @return string HTML
     */
    public function getVariablesInfo() {
        $html = '<div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-info-circle"></i> Verfügbare Variablen</h6>
            </div>
            <div class="card-body">
                <div class="row">';
        
        foreach ($this->templateVariables as $variable => $description) {
            $html .= '
                <div class="col-md-6 mb-2">
                    <code>' . htmlspecialchars($variable) . '</code>
                    <br><small class="text-muted">' . htmlspecialchars($description) . '</small>
                </div>';
        }
        
        $html .= '
                </div>
            </div>
        </div>';
        
        return $html;
    }
    
    /**
     * Template speichern
     * 
     * @param int $templateId Template-ID
     * @param string $content Inhalt
     * @param string $contentType 'html' oder 'raw'
     * @return bool Erfolg
     */
    public function saveTemplate($templateId, $content, $contentType = 'html') {
        try {
            $field = $contentType === 'html' ? 'html_content' : 'raw_content';
            
            $stmt = $this->db->prepare("UPDATE email_templates SET $field = ?, updated_at = NOW() WHERE id = ?");
            $result = $this->db->execute($stmt, [$content, $templateId]);
            
            if ($result) {
                $this->db->logAction('Email Template', "Template $templateId aktualisiert", 'success');
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Fehler beim Speichern des Templates: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Template-Vorschau generieren
     * 
     * @param int $templateId Template-ID
     * @param array $variables Variablen für Vorschau
     * @return string HTML-Vorschau
     */
    public function generatePreview($templateId, $variables = []) {
        try {
            $stmt = $this->db->prepare("SELECT html_content FROM email_templates WHERE id = ?");
            $this->db->execute($stmt, [$templateId]);
            $template = $this->db->fetch($stmt);
            
            if (!$template) {
                return '<p class="text-danger">Template nicht gefunden</p>';
            }
            
            $content = $template['html_content'];
            
            // Standard-Variablen für Vorschau
            $defaultVariables = [
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'email' => 'max.mustermann@example.com',
                'username' => 'max.mustermann',
                'password' => 'geheim123',
                'loginUrl' => 'https://example.com/login',
                'verificationLink' => 'https://example.com/verify?token=abc123',
                'site_name' => 'Meine Website',
                'systemCredentials' => '<div class="alert alert-info">System-Anmeldedaten hier...</div>'
            ];
            
            $variables = array_merge($defaultVariables, $variables);
            
            // Variablen ersetzen
            foreach ($variables as $key => $value) {
                $content = str_replace('{' . $key . '}', $value, $content);
            }
            
            return $content;
            
        } catch (Exception $e) {
            return '<p class="text-danger">Fehler bei der Vorschau: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    }
}
