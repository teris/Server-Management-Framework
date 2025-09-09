<?php
/**
 * E-Mail-Template-Verwaltung
 * 
 * TODO: Diese Datei verwaltet alle E-Mail-Templates über eine benutzerfreundliche Oberfläche
 * TODO: Alle E-Mail-Funktionen sollten ausschließlich über das Template-System laufen
 * TODO: Fallback-System für Kompatibilität mit alten E-Mail-Funktionen
 * 
 * @author Teris
 * @version 3.1.2
 */

// AJAX-Handler für E-Mail-Template-Verwaltung
if (isset($_POST['action']) && $_POST['action'] === 'email_templates') {
    header('Content-Type: application/json');
    
    try {
        if (!isset($db)) {
            require_once dirname(__DIR__) . '/core/DatabaseManager.php';
            $db = DatabaseManager::getInstance();
        }
        
        // EmailTemplateManager laden falls noch nicht geladen
        if (!class_exists('EmailTemplateManager')) {
            require_once __DIR__ . '/../core/EmailTemplateManager.php';
        }
        
        $db = DatabaseManager::getInstance();
        
        // Prüfen und erstellen der Tabelle falls nötig
        $stmt = $db->prepare("SHOW TABLES LIKE 'email_templates'");
        $db->execute($stmt);
        $tableExists = $db->fetch($stmt);
        
        if (!$tableExists) {
            // Tabelle erstellen
            $createTableSQL = "
            CREATE TABLE IF NOT EXISTS `email_templates` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `template_name` varchar(255) NOT NULL COMMENT 'Name des Templates',
              `subject` text NOT NULL COMMENT 'E-Mail-Betreff mit Variablen',
              `html_content` longtext COMMENT 'HTML-Inhalt des Templates',
              `raw_content` longtext COMMENT 'Raw-Text-Inhalt des Templates',
              `variables` text COMMENT 'Verfügbare Variablen (kommagetrennt)',
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `template_name` (`template_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='E-Mail-Templates für das System'
            ";
            $db->execute($createTableSQL);
        }
        
        switch ($_POST['subaction'] ?? '') {
            case 'test':
                echo json_encode(['success' => true, 'message' => 'AJAX-Verbindung funktioniert', 'timestamp' => date('Y-m-d H:i:s')]);
                break;
                
            case 'load_editor':
                $templateId = $_POST['template_id'] ?? '';
                $contentType = $_POST['content_type'] ?? 'html';
                
                if (empty($templateId)) {
                    echo json_encode(['success' => false, 'message' => 'Template-ID erforderlich']);
                    break;
                }
                
                try {
                    require_once __DIR__ . '/../core/EmailTemplateEditor.php';
                    $editor = new EmailTemplateEditor();
                    $editorHtml = $editor->loadTemplateEditor($templateId, $contentType);
                    echo json_encode(['success' => true, 'editor' => $editorHtml]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Fehler beim Laden des Editors: ' . $e->getMessage()]);
                }
                break;
                
            case 'save_editor':
                $templateId = $_POST['template_id'] ?? '';
                $content = $_POST['content'] ?? '';
                $contentType = $_POST['content_type'] ?? 'html';
                
                if (empty($templateId) || empty($content)) {
                    echo json_encode(['success' => false, 'message' => 'Template-ID und Inhalt erforderlich']);
                    break;
                }
                
                try {
                    require_once __DIR__ . '/../core/EmailTemplateEditor.php';
                    $editor = new EmailTemplateEditor();
                    $result = $editor->saveTemplate($templateId, $content, $contentType);
                    
                    if ($result) {
                        echo json_encode(['success' => true, 'message' => 'Template erfolgreich gespeichert']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern']);
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern: ' . $e->getMessage()]);
                }
                break;
                
            case 'preview_template':
                $templateId = $_POST['template_id'] ?? '';
                $variables = json_decode($_POST['variables'] ?? '{}', true);
                
                try {
                    require_once __DIR__ . '/../core/EmailTemplateEditor.php';
                    $editor = new EmailTemplateEditor();
                    $preview = $editor->generatePreview($templateId, $variables);
                    echo json_encode(['success' => true, 'preview' => $preview]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Fehler bei der Vorschau: ' . $e->getMessage()]);
                }
                break;
                
            case 'test_email':
                $templateId = $_POST['template_id'] ?? '';
                
                try {
                    // Template laden
                    $stmt = $db->prepare("SELECT * FROM email_templates WHERE id = ?");
                    $db->execute($stmt, [$templateId]);
                    $template = $db->fetch($stmt);
                    if (!$template) {
                        echo json_encode(['success' => false, 'message' => 'Template nicht gefunden']);
                        break;
                    }
                    
                    // E-Mail-Adresse aus der Konfiguration holen
                    require_once __DIR__ . '/../../config/config.inc.php';
                    $userEmail = Config::FRONTPANEL_ADMIN_EMAIL;
                    
                    // Test-Variablen für das Template
                    $testVariables = [
                        'firstName' => 'Max',
                        'lastName' => 'Mustermann',
                        'email' => $userEmail,
                        'username' => 'testuser',
                        'password' => 'testpass123',
                        'loginUrl' => Config::FRONTPANEL_SITE_URL . '/login',
                        'verificationLink' => Config::FRONTPANEL_SITE_URL . '/verify?token=test123',
                        'site_name' => Config::FRONTPANEL_SITE_NAME,
                        'systemCredentials' => 'Test-System-Zugangsdaten'
                    ];
                    
                    // Template-Inhalt mit Test-Variablen ersetzen
                    $content = $template['html_content'] ?: $template['raw_content'];
                    foreach ($testVariables as $key => $value) {
                        $content = str_replace('{' . $key . '}', $value, $content);
                    }
                    
                    // E-Mail senden
                    $subject = 'Test-E-Mail: ' . $template['subject'];
                    $subject = str_replace('{site_name}', Config::FRONTPANEL_SITE_NAME, $subject);
                    
                    $headers = "MIME-Version: 1.0\r\n";
                    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                    $headers .= "From: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
                    $headers .= "Reply-To: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
                    
                    if (mail($userEmail, $subject, $content, $headers)) {
                        echo json_encode(['success' => true, 'message' => 'Test-E-Mail erfolgreich an ' . $userEmail . ' gesendet']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Fehler beim Senden der Test-E-Mail']);
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Fehler beim Senden der Test-E-Mail: ' . $e->getMessage()]);
                }
                break;
                
            case 'get_templates':
                try {
                    $stmt = $db->prepare("SELECT * FROM email_templates ORDER BY template_name");
                    $db->execute($stmt);
                    $templates = $db->fetchAll($stmt);
                    
                    // Wenn keine Templates vorhanden sind, Standard-Templates erstellen
                    if (empty($templates)) {
                        $emailTemplateManager = EmailTemplateManager::getInstance();
                        $result = $emailTemplateManager->createDefaultTemplates();
                        
                        if ($result) {
                            // Templates erneut laden
                            $stmt = $db->prepare("SELECT * FROM email_templates ORDER BY template_name");
                            $db->execute($stmt);
                            $templates = $db->fetchAll($stmt);
                        }
                    }
                    
                    echo json_encode(['success' => true, 'templates' => $templates, 'count' => count($templates)]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Datenbankfehler: ' . $e->getMessage()]);
                }
                break;
                
            case 'get_template':
                $templateId = $_POST['template_id'] ?? '';
                $stmt = $db->prepare("SELECT * FROM email_templates WHERE id = ?");
                $db->execute($stmt, [$templateId]);
                $template = $db->fetch($stmt);
                echo json_encode(['success' => true, 'template' => $template]);
                break;
                
            case 'save_template':
                $templateId = $_POST['template_id'] ?? '';
                $templateName = $_POST['template_name'] ?? '';
                $subject = $_POST['subject'] ?? '';
                $htmlContent = $_POST['html_content'] ?? '';
                $rawContent = $_POST['raw_content'] ?? '';
                $variables = $_POST['variables'] ?? '';
                
                if (empty($templateName) || empty($subject)) {
                    echo json_encode(['success' => false, 'message' => 'Template-Name und Betreff sind erforderlich']);
                    break;
                }
                
                if ($templateId) {
                    // Update existing template
                    $stmt = $db->prepare("
                        UPDATE email_templates 
                        SET template_name = ?, subject = ?, html_content = ?, raw_content = ?, variables = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $result = $db->execute($stmt, [$templateName, $subject, $htmlContent, $rawContent, $variables, $templateId]);
                } else {
                    // Create new template
                    $stmt = $db->prepare("
                        INSERT INTO email_templates (template_name, subject, html_content, raw_content, variables, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $result = $db->execute($stmt, [$templateName, $subject, $htmlContent, $rawContent, $variables]);
                    $templateId = $db->lastInsertId();
                }
                
                if ($result) {
                    $db->logAction(
                        'Email Template',
                        ($templateId ? 'Template aktualisiert' : 'Template erstellt') . ": $templateName",
                        'success'
                    );
                    echo json_encode(['success' => true, 'message' => 'Template erfolgreich gespeichert', 'template_id' => $templateId]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern des Templates']);
                }
                break;
                
            case 'delete_template':
                $templateId = $_POST['template_id'] ?? '';
                $stmt = $db->prepare("DELETE FROM email_templates WHERE id = ?");
                $result = $db->execute($stmt, [$templateId]);
                
                if ($result) {
                    $db->logAction('Email Template', "Template gelöscht: ID $templateId", 'success');
                    echo json_encode(['success' => true, 'message' => 'Template erfolgreich gelöscht']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Fehler beim Löschen des Templates']);
                }
                break;
                
                
            default:
                echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion']);
        }
        
    } catch (Exception $e) {
        error_log("Email Templates Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage(), 'debug' => $e->getTraceAsString()]);
    }
    exit;
}

/**
 * Template-Variablen ersetzen
 * TODO: Diese Funktion wird nur für die Vorschau verwendet
 */
function replaceTemplateVariables($content, $variables) {
    foreach ($variables as $key => $value) {
        $content = str_replace('{' . $key . '}', $value, $content);
    }
    return $content;
}

// TODO: Alle E-Mail-Funktionen werden jetzt über EmailTemplateManager verwendet
// TODO: Standard-Templates werden automatisch beim ersten Backend-Aufruf erstellt
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-envelope"></i> <?= t('email_template_management') ?>
                </h5>
            </div>
            <div class="card-body">
                <!-- Template-Liste -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6><?= t('existing_templates') ?></h6>
                            <button class="btn btn-primary btn-sm" id="create-template-btn">
                                <i class="bi bi-plus"></i> <?= t('create_new_template') ?>
                            </button>
                        </div>
                        <div id="templates-list" class="list-group">
                            <!-- Templates werden hier dynamisch geladen -->
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><?= t('template_variables') ?></h6>
                            </div>
                            <div class="card-body">
                                <small class="text-muted">
                                    <?= t('template_variables_info') ?><br>
                                    <code>{firstName}</code> - <?= t('variable_firstname') ?><br>
                                    <code>{lastName}</code> - <?= t('variable_lastname') ?><br>
                                    <code>{email}</code> - <?= t('variable_email') ?><br>
                                    <code>{username}</code> - <?= t('variable_username') ?><br>
                                    <code>{password}</code> - <?= t('variable_password') ?><br>
                                    <code>{loginUrl}</code> - <?= t('variable_login_url') ?><br>
                                    <code>{verificationLink}</code> - <?= t('variable_verification_link') ?><br>
                                    <code>{site_name}</code> - <?= t('variable_site_name') ?><br>
                                    <code>{systemCredentials}</code> - <?= t('variable_system_credentials') ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Template-Editor -->
                <div id="template-editor" class="card" style="display: none;">
                    <div class="card-header">
                        <h6 class="mb-0"><?= t('edit_template') ?></h6>
                    </div>
                    <div class="card-body">
                        <form id="template-form">
                            <input type="hidden" id="template-id" name="template_id">
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="template-name" class="form-label"><?= t('template_name') ?></label>
                                    <input type="text" class="form-control" id="template-name" name="template_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="template-subject" class="form-label"><?= t('template_subject') ?></label>
                                    <input type="text" class="form-control" id="template-subject" name="subject" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label"><?= t('template_type') ?></label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="template_type" id="html-template" value="html" checked>
                                    <label class="form-check-label" for="html-template">
                                        <?= t('html_template') ?>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="template_type" id="raw-template" value="raw">
                                    <label class="form-check-label" for="raw-template">
                                        <?= t('raw_text_template') ?>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="template-content" class="form-label"><?= t('template_content') ?></label>
                                
                                <!-- Editor-Container -->
                                <div id="editor-container">
                                    <!-- Editor wird hier dynamisch geladen -->
                                </div>
                                
                                <!-- Hidden input für Formular-Validierung -->
                                <input type="hidden" id="template-content" name="html_content" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="template-variables" class="form-label"><?= t('available_variables') ?></label>
                                <input type="text" class="form-control" id="template-variables" name="variables" 
                                       placeholder="firstName,lastName,email,username,password,loginUrl,verificationLink,site_name">
                            </div>
                            
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> <?= t('save_template') ?>
                    </button>
                    <button type="button" class="btn btn-info" id="preview-btn">
                        <i class="bi bi-eye"></i> <?= t('template_preview') ?>
                    </button>
                    <button type="button" class="btn btn-secondary" id="close-editor-btn">
                        <i class="bi bi-x"></i> <?= t('close_editor') ?>
                    </button>
                </div>
                        </form>
                    </div>
                </div>

                <!-- Template-Vorschau -->
                <div id="template-preview" class="card" style="display: none;">
                    <div class="card-header">
                        <h6 class="mb-0"><?= t('template_preview') ?></h6>
                    </div>
                    <div class="card-body">
                        <div id="preview-content"></div>
                        <div class="mt-3">
                            <button type="button" class="btn btn-secondary" id="close-preview-btn">
                                <i class="bi bi-x"></i> <?= t('close_preview') ?>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- TODO: JavaScript wird über das Modul-System in index.php geladen -->