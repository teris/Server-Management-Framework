<?php
/**
 * E-Mail-Template-Manager
 * 
 * TODO: Diese Klasse verwaltet alle E-Mail-Templates des Systems
 * TODO: Sollte direkt in der index.php geladen werden
 * TODO: Ersetzt alle alten E-Mail-Funktionen durch Template-System
 * 
 * @author Teris
 * @version 3.1.2
 */

// DatabaseManager laden falls noch nicht geladen
if (!isset($db)) {
    require_once dirname(__DIR__) . '/core/DatabaseManager.php';
    $db = DatabaseManager::getInstance();
}
class EmailTemplateManager {
    
    private static $instance = null;
    private $db;
    
    /**
     * Singleton-Pattern
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->db = DatabaseManager::getInstance();
    }
    
    /**
     * E-Mail-Template aus Datenbank laden und senden
     * 
     * TODO: Hauptfunktion für das Versenden von E-Mails mit Templates
     */
    public function sendEmailFromTemplate($templateName, $recipientEmail, $variables = []) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM email_templates WHERE template_name = ?");
            $this->db->execute($stmt, [$templateName]);
            $template = $this->db->fetch($stmt);
            
            if (!$template) {
                throw new Exception("Template '$templateName' nicht gefunden");
            }
            
            // Standard-Variablen hinzufügen
            $defaultVariables = [
                'site_name' => Config::FRONTPANEL_SITE_NAME,
                'support_email' => Config::FRONTPANEL_SUPPORT_EMAIL,
                'system_email' => Config::FRONTPANEL_SYSTEM_EMAIL
            ];
            
            $variables = array_merge($defaultVariables, $variables);
            
            // Variablen ersetzen
            $subject = $this->replaceTemplateVariables($template['subject'], $variables);
            $htmlContent = $this->replaceTemplateVariables($template['html_content'], $variables);
            
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=utf-8',
                'From: ' . Config::FRONTPANEL_SYSTEM_EMAIL,
                'Reply-To: ' . Config::FRONTPANEL_SUPPORT_EMAIL,
                'X-Mailer: PHP/' . phpversion()
            ];
            
            $result = mail($recipientEmail, $subject, $htmlContent, implode("\r\n", $headers));
            
            if ($result) {
                $this->db->logAction(
                    'Email Sent',
                    "E-Mail gesendet an $recipientEmail mit Template: " . $template['template_name'],
                    'success'
                );
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Failed to send email from template '$templateName': " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Template-Variablen ersetzen
     * 
     * TODO: Ersetzt alle {variable} Platzhalter in Templates
     */
    public function replaceTemplateVariables($content, $variables) {
        foreach ($variables as $key => $value) {
            $content = str_replace('{' . $key . '}', $value, $content);
        }
        return $content;
    }
    
    /**
     * System-Anmeldedaten für E-Mail generieren
     * 
     * TODO: Generiert HTML für System-Anmeldedaten in E-Mails
     */
    public function generateSystemCredentialsHtml($systemPasswords, $portalLinks, $username, $firstName, $lastName) {
        $html = '';
        
        // ISPConfig
        if (isset($systemPasswords['ispconfig']) && isset($portalLinks['ispconfig'])) {
            $html .= '
                <div style="padding: 15px; border-radius: 8px; margin: 10px 0; border: 1px solid #ffeaa7;">
                    <h4 style="margin-top: 0; color: #856404;">🌐 Webhosting-Verwaltung</h4>
                    <p>Portal: <a href="' . $portalLinks['ispconfig'] . '">' . $portalLinks['ispconfig'] . '</a></p>
                    <p>Benutzername: ' . htmlspecialchars($username) . '</p>
                    <p>Passwort: ' . htmlspecialchars($systemPasswords['ispconfig']) . '</p>
                </div>';
        }
        
        // OpenGamePanel
        if (isset($systemPasswords['ogp']) && isset($portalLinks['ogp'])) {
            $html .= '
                <div style="padding: 15px; border-radius: 8px; margin: 10px 0; border: 1px solid #bee5eb;">
                    <h4 style="margin-top: 0; color: #0c5460;">🎮 Spieleserver-Verwaltung</h4>
                    <p>Portal: <a href="' . $portalLinks['ogp'] . '">' . $portalLinks['ogp'] . '</a></p>
                    <p>Benutzername: ' . htmlspecialchars($firstName . ' ' . $lastName) . '</p>
                    <p>Passwort: ' . htmlspecialchars($systemPasswords['ogp']) . '</p>
                </div>';
        }
        
        // Proxmox
        if (isset($systemPasswords['proxmox']) && isset($portalLinks['proxmox'])) {
            $html .= '
                <div style="padding: 15px; border-radius: 8px; margin: 10px 0; border: 1px solid #c3e6cb;">
                    <h4 style="margin-top: 0; color: #155724;">🖥️ Virtuelle Maschinen</h4>
                    <p>Portal: <a href="' . $portalLinks['proxmox'] . '">' . $portalLinks['proxmox'] . '</a></p>
                    <p>Benutzername: ' . htmlspecialchars($username) . '</p>
                    <p>Login Domäne: Proxmox VE authentication Server (PVE)</p>
                    <p>Passwort: ' . htmlspecialchars($systemPasswords['proxmox']) . '</p>
                </div>';
        }
        
        return $html;
    }
    
    /**
     * Kunden-Willkommens-E-Mail senden
     * 
     * TODO: Ersetzt sendCustomerWelcomeEmail() Funktion
     */
    public function sendCustomerWelcomeEmail($email, $firstName, $password) {
        $loginUrl = "https://" . $_SERVER['HTTP_HOST'] . "/public/login.php";
        
        $variables = [
            'firstName' => $firstName,
            'email' => $email,
            'password' => $password,
            'loginUrl' => $loginUrl
        ];
        
        return $this->sendEmailFromTemplate('Kunden-Willkommens-E-Mail', $email, $variables);
    }
    
    /**
     * Kunden-Verifikations-E-Mail senden
     * 
     * TODO: Ersetzt sendCustomerVerificationEmail() Funktion
     */
    public function sendCustomerVerificationEmail($email, $firstName, $customerId, $password) {
        try {
            $verificationToken = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            // Token in Datenbank speichern
            $stmt = $this->db->prepare("INSERT INTO customer_verification_tokens (customer_id, token, expires_at) VALUES (?, ?, ?)");
            $this->db->execute($stmt, [$customerId, $verificationToken, $expires]);
            
            $verificationLink = "https://" . $_SERVER['HTTP_HOST'] . "/public/verify.php?token=" . $verificationToken;
            
            $variables = [
                'firstName' => $firstName,
                'email' => $email,
                'password' => $password,
                'verificationLink' => $verificationLink
            ];
            
            return $this->sendEmailFromTemplate('Kunden-Verifikations-E-Mail', $email, $variables);
            
        } catch (Exception $e) {
            error_log("Failed to send customer verification email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Backend-Benutzer-Willkommens-E-Mail senden
     * 
     * TODO: Ersetzt sendUserWelcomeEmail() Funktion
     */
    public function sendUserWelcomeEmail($email, $firstName, $username, $password) {
        $loginUrl = "https://" . $_SERVER['HTTP_HOST'] . "/src/";
        
        $variables = [
            'firstName' => $firstName,
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'loginUrl' => $loginUrl
        ];
        
        return $this->sendEmailFromTemplate('Backend-Benutzer-Willkommens-E-Mail', $email, $variables);
    }
    
    /**
     * System-Anmeldedaten-E-Mail senden
     * 
     * TODO: Ersetzt sendSystemCredentialsEmail() Funktion
     */
    public function sendSystemCredentialsEmail($email, $firstName, $lastName, $username, $systemPasswords, $systemResults) {
        $loginUrl = "https://" . $_SERVER['HTTP_HOST'] . "/public/login.php";
        
        // Portal-Links aus der Config laden
        $portalLinks = [];
        if (Config::ISPCONFIG_USEING) {
            $portalLinks['ispconfig'] = Config::ISPCONFIG_HOST;
        }
        if (Config::OGP_USEING) {
            $portalLinks['ogp'] = Config::OGP_HOST;
        }
        if (Config::PROXMOX_USEING) {
            $portalLinks['proxmox'] = Config::PROXMOX_HOST;
        }
        
        $systemCredentials = $this->generateSystemCredentialsHtml($systemPasswords, $portalLinks, $username, $firstName, $lastName);
        
        $variables = [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'username' => $username,
            'loginUrl' => $loginUrl,
            'systemCredentials' => $systemCredentials
        ];
        
        return $this->sendEmailFromTemplate('System-Anmeldedaten-E-Mail', $email, $variables);
    }
    
    /**
     * Prüfen ob Template-System verfügbar ist
     * 
     * TODO: Prüft ob E-Mail-Template-System korrekt installiert ist
     */
    public function isTemplateSystemAvailable() {
        try {
            // Prüfen ob Tabelle existiert
            $stmt = $this->db->prepare("SHOW TABLES LIKE 'email_templates'");
            $this->db->execute($stmt);
            $tableExists = $this->db->fetch($stmt);
            
            if (!$tableExists) {
                return false;
            }
            
            // Prüfen ob Templates vorhanden sind
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM email_templates");
            $this->db->execute($stmt);
            $result = $this->db->fetch($stmt);
            $count = $result['count'] ?? 0;
            
            return $count > 0;
            
        } catch (Exception $e) {
            error_log("Error checking email template system: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Standard-Templates erstellen falls sie nicht existieren
     * 
     * TODO: Erstellt automatisch Standard-Templates beim ersten Aufruf
     */
    public function createDefaultTemplates() {
        try {
            // Prüfen ob Tabelle existiert
            $stmt = $this->db->prepare("SHOW TABLES LIKE 'email_templates'");
            $this->db->execute($stmt);
            $tableExists = $this->db->fetch($stmt);
            
            if (!$tableExists) {
                error_log("E-Mail-Templates Tabelle existiert nicht. Bitte führen Sie database/email-templates-structure.sql aus.");
                return false;
            }
            
            // Prüfen ob Templates bereits existieren
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM email_templates");
            $this->db->execute($stmt);
            $result = $this->db->fetch($stmt);
            $count = $result['count'] ?? 0;
            
            if ($count > 0) {
                return true; // Templates bereits vorhanden
            }
            
            $defaultTemplates = [
                [
                    'template_name' => 'Kunden-Willkommens-E-Mail',
                    'subject' => 'Willkommen bei {site_name}!',
                    'html_content' => $this->getDefaultWelcomeTemplate(),
                    'raw_content' => $this->getDefaultWelcomeTemplateRaw(),
                    'variables' => 'firstName,email,password,loginUrl,site_name'
                ],
                [
                    'template_name' => 'Kunden-Verifikations-E-Mail',
                    'subject' => 'E-Mail-Adresse bestätigen - {site_name}',
                    'html_content' => $this->getDefaultVerificationTemplate(),
                    'raw_content' => $this->getDefaultVerificationTemplateRaw(),
                    'variables' => 'firstName,email,password,verificationLink,site_name'
                ],
                [
                    'template_name' => 'Backend-Benutzer-Willkommens-E-Mail',
                    'subject' => 'Ihre Backend-Anmeldedaten - {site_name}',
                    'html_content' => $this->getDefaultBackendWelcomeTemplate(),
                    'raw_content' => $this->getDefaultBackendWelcomeTemplateRaw(),
                    'variables' => 'firstName,username,email,password,loginUrl,site_name'
                ],
                [
                    'template_name' => 'System-Anmeldedaten-E-Mail',
                    'subject' => 'Ihre System-Anmeldedaten - {site_name}',
                    'html_content' => $this->getDefaultSystemCredentialsTemplate(),
                    'raw_content' => $this->getDefaultSystemCredentialsTemplateRaw(),
                    'variables' => 'firstName,lastName,email,username,systemPasswords,portalLinks,loginUrl,site_name,systemCredentials'
                ]
            ];
            
            foreach ($defaultTemplates as $template) {
                $stmt = $this->db->prepare("
                    INSERT INTO email_templates (template_name, subject, html_content, raw_content, variables, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $this->db->execute($stmt, [
                    $template['template_name'],
                    $template['subject'],
                    $template['html_content'],
                    $template['raw_content'],
                    $template['variables']
                ]);
            }
            
            $this->db->logAction('Email Templates', 'Standard-Templates erstellt', 'success');
            
        } catch (Exception $e) {
            error_log("Failed to create default email templates: " . $e->getMessage());
        }
    }
    
    // Standard-Template-Inhalte
    private function getDefaultWelcomeTemplate() {
        return '
        <html>
        <head>
            <title>Willkommen bei {site_name}</title>
        </head>
        <body>
            <h2>Willkommen bei {site_name}!</h2>
            <p>Hallo {firstName},</p>
            <p>Ihr Konto wurde erfolgreich erstellt und ist sofort aktiv. Sie können sich jetzt in unserem Kundenportal anmelden.</p>
            
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3>Ihre Anmeldedaten:</h3>
                <p><strong>E-Mail:</strong> {email}</p>
                <p><strong>Passwort:</strong> {password}</p>
            </div>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{loginUrl}" 
                   style="background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; display: inline-block;">
                    Zum Kundenportal anmelden
                </a>
            </div>
            
            <p>Falls Sie Fragen haben, können Sie sich gerne an unseren Support wenden.</p>
            
            <p>Mit freundlichen Grüßen<br>
            Ihr {site_name} Team</p>
        </body>
        </html>';
    }
    
    private function getDefaultWelcomeTemplateRaw() {
        return 'Willkommen bei {site_name}!

Hallo {firstName},

Ihr Konto wurde erfolgreich erstellt und ist sofort aktiv. Sie können sich jetzt in unserem Kundenportal anmelden.

Ihre Anmeldedaten:
E-Mail: {email}
Passwort: {password}

Anmelden: {loginUrl}

Falls Sie Fragen haben, können Sie sich gerne an unseren Support wenden.

Mit freundlichen Grüßen
Ihr {site_name} Team';
    }
    
    private function getDefaultVerificationTemplate() {
        return '
        <html>
        <head>
            <title>E-Mail-Adresse bestätigen</title>
        </head>
        <body>
            <h2>Willkommen bei {site_name}!</h2>
            <p>Hallo {firstName},</p>
            <p>vielen Dank für Ihre Registrierung. Um Ihr Konto zu aktivieren, bestätigen Sie bitte Ihre E-Mail-Adresse.</p>
            
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3>Ihre Anmeldedaten:</h3>
                <p><strong>E-Mail:</strong> {email}</p>
                <p><strong>Passwort:</strong> {password}</p>
            </div>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{verificationLink}" 
                   style="background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; display: inline-block;">
                    E-Mail-Adresse bestätigen
                </a>
            </div>
            
            <p>Falls der Button nicht funktioniert, kopieren Sie diesen Link in Ihren Browser:</p>
            <p style="word-break: break-all; color: #666;">{verificationLink}</p>
            
            <p>Dieser Link ist 24 Stunden gültig.</p>
            
            <p>Mit freundlichen Grüßen<br>
            Ihr {site_name} Team</p>
        </body>
        </html>';
    }
    
    private function getDefaultVerificationTemplateRaw() {
        return 'Willkommen bei {site_name}!

Hallo {firstName},

vielen Dank für Ihre Registrierung. Um Ihr Konto zu aktivieren, bestätigen Sie bitte Ihre E-Mail-Adresse.

Ihre Anmeldedaten:
E-Mail: {email}
Passwort: {password}

E-Mail bestätigen: {verificationLink}

Dieser Link ist 24 Stunden gültig.

Mit freundlichen Grüßen
Ihr {site_name} Team';
    }
    
    private function getDefaultBackendWelcomeTemplate() {
        return '
        <html>
        <head>
            <title>Backend-Anmeldedaten</title>
        </head>
        <body>
            <h2>Willkommen im Backend von {site_name}!</h2>
            <p>Hallo {firstName},</p>
            <p>Ihr Backend-Benutzerkonto wurde erfolgreich erstellt. Sie können sich jetzt im Admin-Bereich anmelden.</p>
            
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <h3>Ihre Anmeldedaten:</h3>
                <p><strong>Benutzername:</strong> {username}</p>
                <p><strong>E-Mail:</strong> {email}</p>
                <p><strong>Passwort:</strong> {password}</p>
            </div>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{loginUrl}" 
                   style="background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; display: inline-block;">
                    Zum Backend anmelden
                </a>
            </div>
            
            <p>Falls Sie Fragen haben, können Sie sich gerne an den Administrator wenden.</p>
            
            <p>Mit freundlichen Grüßen<br>
            Ihr {site_name} Team</p>
        </body>
        </html>';
    }
    
    private function getDefaultBackendWelcomeTemplateRaw() {
        return 'Willkommen im Backend von {site_name}!

Hallo {firstName},

Ihr Backend-Benutzerkonto wurde erfolgreich erstellt. Sie können sich jetzt im Admin-Bereich anmelden.

Ihre Anmeldedaten:
Benutzername: {username}
E-Mail: {email}
Passwort: {password}

Anmelden: {loginUrl}

Falls Sie Fragen haben, können Sie sich gerne an den Administrator wenden.

Mit freundlichen Grüßen
Ihr {site_name} Team';
    }
    
    private function getDefaultSystemCredentialsTemplate() {
        return '
        <html>
        <head>
            <title>System-Anmeldedaten</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #007bff; border-bottom: 2px solid #007bff; padding-bottom: 10px;">
                    Ihre System-Anmeldedaten
                </h2>
                
                <p>Hallo {firstName} {lastName},</p>
                
                <p>Ihr Konto wurde erfolgreich aktiviert! Ihre Benutzerkonten in den folgenden Systemen wurden erfolgreich angelegt:</p>
                
                <div style="padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #007bff;">
                    <h3 style="margin-top: 0; color: #007bff;">🎯 Frontpanel-Anmeldung</h3>
                    <p><strong>Portal:</strong> <a href="{loginUrl}">{loginUrl}</a></p>
                    <p><strong>E-Mail:</strong> {email}</p>
                    <p><strong>Passwort:</strong> <span style="padding: 2px 6px; border-radius: 4px;">Das Passwort, das Sie bei der Registrierung angegeben haben</span></p>
                </div>
                
                <h3>🔐 Externe Systeme - Neue Anmeldedaten</h3>
                <p><strong>Wichtig:</strong> Sie können alle Dienstleistungen, welche sie bei uns angefordert haben, über unsere Externe Systeme ebenfalls verwalten.<br>
                Für jedes externe System wurde ein eigenes Passwort generiert. <br>
                Bitte ändern Sie diese Passwörter daher nach dem ersten Login aus Sicherheitsgründen!</p>
                
                <div style="margin: 20px 0;">
                    {systemCredentials}
                </div>
                
                <div style="padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #ffeaa7;">
                    <h3 style="margin-top: 0; color: #856404;">⚠️ WICHTIGER SICHERHEITSHINWEIS</h3>
                    <p><strong>Bitte ändern Sie die Passwörter in den externen Systemen nach dem ersten Login!</strong></p>
                    <p>Die generierten Passwörter sind nur für den ersten Login gedacht. Aus Sicherheitsgründen sollten Sie diese sofort durch eigene, sichere Passwörter ersetzen.</p>
                    <ul>
                        <li>Verwenden Sie mindestens 12 Zeichen</li>
                        <li>Kombinieren Sie Groß- und Kleinbuchstaben, Zahlen und Sonderzeichen</li>
                        <li>Verwenden Sie für jedes System ein unterschiedliches Passwort</li>
                        <li>Speichern Sie die neuen Passwörter sicher ab</li>
                    </ul>
                </div>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="{loginUrl}" 
                       style="padding: 15px 30px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold;">
                        Jetzt im Frontpanel anmelden
                    </a>
                </div>
                
                <p>Falls der Button nicht funktioniert, kopieren Sie diesen Link in Ihren Browser:</p>
                <p style="word-break: break-all; color: #666; background: #f8f9fa; padding: 10px; border-radius: 4px;">{loginUrl}</p>
                
                <p>Falls Sie Fragen haben oder Probleme beim Login haben, kontaktieren Sie uns gerne unter <strong>{support_email}</strong></p>
                
                <p>Mit freundlichen Grüßen<br>
                Ihr <strong>{site_name}</strong> Team</p>
            </div>
        </body>
        </html>';
    }
    
    private function getDefaultSystemCredentialsTemplateRaw() {
        return 'Ihre System-Anmeldedaten

Hallo {firstName} {lastName},

Ihr Konto wurde erfolgreich aktiviert! Ihre Benutzerkonten in den folgenden Systemen wurden erfolgreich angelegt:

🎯 Frontpanel-Anmeldung
Portal: {loginUrl}
E-Mail: {email}
Passwort: Das Passwort, das Sie bei der Registrierung angegeben haben

🔐 Externe Systeme - Neue Anmeldedaten
Wichtig: Sie können alle Dienstleistungen, welche sie bei uns angefordert haben, über unsere Externe Systeme ebenfalls verwalten.
Für jedes externe System wurde ein eigenes Passwort generiert.
Bitte ändern Sie diese Passwörter daher nach dem ersten Login aus Sicherheitsgründen!

{systemCredentials}

⚠️ WICHTIGER SICHERHEITSHINWEIS
Bitte ändern Sie die Passwörter in den externen Systemen nach dem ersten Login!
Die generierten Passwörter sind nur für den ersten Login gedacht. Aus Sicherheitsgründen sollten Sie diese sofort durch eigene, sichere Passwörter ersetzen.

- Verwenden Sie mindestens 12 Zeichen
- Kombinieren Sie Groß- und Kleinbuchstaben, Zahlen und Sonderzeichen
- Verwenden Sie für jedes System ein unterschiedliches Passwort
- Speichern Sie die neuen Passwörter sicher ab

Jetzt im Frontpanel anmelden: {loginUrl}

Falls Sie Fragen haben oder Probleme beim Login haben, kontaktieren Sie uns gerne unter {support_email}

Mit freundlichen Grüßen
Ihr {site_name} Team';
    }
}

// Globale Hilfsfunktionen für Rückwärtskompatibilität
// TODO: Diese Funktionen sollten durch die EmailTemplateManager-Klasse ersetzt werden

function sendEmailFromTemplate($templateName, $recipientEmail, $variables = []) {
    return EmailTemplateManager::getInstance()->sendEmailFromTemplate($templateName, $recipientEmail, $variables);
}

function sendCustomerWelcomeEmailTemplate($email, $firstName, $password) {
    return EmailTemplateManager::getInstance()->sendCustomerWelcomeEmail($email, $firstName, $password);
}

function sendCustomerVerificationEmailTemplate($email, $firstName, $customerId, $password) {
    return EmailTemplateManager::getInstance()->sendCustomerVerificationEmail($email, $firstName, $customerId, $password);
}

function sendUserWelcomeEmailTemplate($email, $firstName, $username, $password) {
    return EmailTemplateManager::getInstance()->sendUserWelcomeEmail($email, $firstName, $username, $password);
}

function sendSystemCredentialsEmailTemplate($email, $firstName, $lastName, $username, $systemPasswords, $systemResults) {
    return EmailTemplateManager::getInstance()->sendSystemCredentialsEmail($email, $firstName, $lastName, $username, $systemPasswords, $systemResults);
}

function isEmailTemplateSystemAvailable() {
    return EmailTemplateManager::getInstance()->isTemplateSystemAvailable();
}
?>
