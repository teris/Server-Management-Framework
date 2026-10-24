<?php
if (PHP_SAPI !== 'cli' && (get_included_files()[0] ?? null) === __FILE__) { http_response_code(404); exit; }
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/SchemaManager.php';

class EmailTemplateManager {
    private static ?self $instance = null;
    private PDO $db;
    private $sender;
    public function __construct(?PDO $db = null, ?callable $sender = null) { $this->db = $db ?: HttpSecurity::connection(); $this->sender = $sender ?: [Mailer::class, 'send']; }
    public static function getInstance() { return self::$instance ??= new self(); }
    public static function defaults(): array {
        return [
            'Kunden-Willkommens-E-Mail' => ['subject' => 'Willkommen bei {site_name}', 'html_content' => '<p>Hallo {firstName}, Ihr Konto ist aktiv.</p><p><a href="{loginUrl}">Anmelden</a></p>'],
            'Kunden-Verifikations-E-Mail' => ['subject' => 'E-Mail-Adresse bestätigen – {site_name}', 'html_content' => '<p>Hallo {firstName}, bitte bestätigen Sie Ihre E-Mail-Adresse.</p><p><a href="{verificationLink}">Adresse bestätigen</a></p><p>Der Link gilt 24 Stunden. Passwörter werden nicht per E-Mail versendet.</p>'],
            'Backend-Benutzer-Willkommens-E-Mail' => ['subject' => 'Ihr Benutzerkonto – {site_name}', 'html_content' => '<p>Hallo {firstName}, Ihr Benutzerkonto {username} wurde erstellt.</p><p><a href="{loginUrl}">Anmelden</a></p>'],
            'System-Anmeldedaten-E-Mail' => ['subject' => 'Ihre Dienste – {site_name}', 'html_content' => '<p>Hallo {firstName}, Ihre Dienste können Sie im Portal verwalten.</p><p><a href="{loginUrl}">Portal öffnen</a></p>'],
            'Passwort-zurücksetzen' => ['subject' => 'Passwort zurücksetzen – {site_name}', 'html_content' => '<p><a href="{resetLink}">Neues Passwort festlegen</a></p><p>Der Link gilt 30 Minuten. Wenn Sie dies nicht angefordert haben, ignorieren Sie diese Nachricht.</p>']
        ];
    }
    public function createDefaultTemplates() {
        if (!SchemaManager::hasTable($this->db, 'email_templates')) return false;
        foreach (self::defaults() as $name => $template) {
            $stmt = $this->db->prepare('SELECT id FROM email_templates WHERE template_name=?'); $stmt->execute([$name]);
            if ($stmt->fetchColumn()) continue;
            $stmt = $this->db->prepare('INSERT INTO email_templates (template_name, subject, html_content, raw_content, variables) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$name, $template['subject'], $template['html_content'], strip_tags($template['html_content']), 'firstName,lastName,email,username,loginUrl,verificationLink,resetLink,site_name']);
        }
        return true;
    }
    public function isTemplateSystemAvailable() { return SchemaManager::hasTable($this->db, 'email_templates'); }
    public function replaceTemplateVariables($content, $variables, bool $html = false) {
        // Old custom templates remain intact, but no caller can insert a password or arbitrary credentials.
        $allowed = ['firstName', 'lastName', 'email', 'username', 'loginUrl', 'verificationLink', 'resetLink', 'site_name', 'support_email', 'system_email'];
        $map = [];
        foreach ($allowed as $key) { $value = (string) ($variables[$key] ?? ''); $map['{' . $key . '}'] = $html ? htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $value; }
        foreach (['password', 'systemPasswords', 'systemCredentials', 'portalLinks'] as $key) $map['{' . $key . '}'] = '[Zugangsdaten werden nicht per E-Mail versendet]';
        return strtr((string) $content, $map);
    }
    public function sendEmailFromTemplate($name, $recipient, $variables = []) {
        try {
            $stmt = $this->db->prepare('SELECT subject, html_content FROM email_templates WHERE template_name=?'); $stmt->execute([$name]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC) ?: (self::defaults()[$name] ?? null);
            if (!$template) return false;
            $variables = array_merge(['site_name' => Config::FRONTPANEL_SITE_NAME, 'support_email' => Config::FRONTPANEL_SUPPORT_EMAIL, 'system_email' => Config::FRONTPANEL_SYSTEM_EMAIL], $variables);
            return ($this->sender)($recipient, $this->replaceTemplateVariables($template['subject'], $variables), $this->replaceTemplateVariables($template['html_content'], $variables, true)) === true;
        } catch (Throwable $e) { error_log('Email delivery failed: ' . get_class($e)); return false; }
    }
    public function sendCustomerWelcomeEmail($email, $firstName, $unusedPassword = null) { return $this->sendEmailFromTemplate('Kunden-Willkommens-E-Mail', $email, ['firstName' => $firstName, 'email' => $email, 'loginUrl' => Mailer::siteUrl() . '/public/login.php']); }
    public function sendCustomerVerificationEmail($email, $firstName, $customerId, $unusedPassword = null) { require_once __DIR__ . '/RegistrationService.php'; return (new RegistrationService($this->db, $this))->sendVerification((int) $customerId); }
    public function sendUserWelcomeEmail($email, $firstName, $username, $unusedPassword = null) { return $this->sendEmailFromTemplate('Backend-Benutzer-Willkommens-E-Mail', $email, ['firstName' => $firstName, 'email' => $email, 'username' => $username, 'loginUrl' => Mailer::siteUrl() . '/src/login.php']); }
    public function sendSystemCredentialsEmail($email, $firstName, $lastName, $username, $unusedPasswords = [], $unusedResults = []) { return $this->sendEmailFromTemplate('System-Anmeldedaten-E-Mail', $email, ['firstName' => $firstName, 'lastName' => $lastName, 'username' => $username, 'email' => $email, 'loginUrl' => Mailer::siteUrl() . '/public/login.php']); }
    public function generateSystemCredentialsHtml($unusedPasswords, $unusedLinks, $username, $firstName, $lastName) { return '<p>Zugangsdaten werden ausschließlich im Portal verwaltet.</p>'; }
}
function sendEmailFromTemplate($name, $recipient, $variables = []) { return EmailTemplateManager::getInstance()->sendEmailFromTemplate($name, $recipient, $variables); }
function sendCustomerWelcomeEmailTemplate($email, $firstName, $password = null) { return EmailTemplateManager::getInstance()->sendCustomerWelcomeEmail($email, $firstName); }
function sendCustomerVerificationEmailTemplate($email, $firstName, $id, $password = null) { return EmailTemplateManager::getInstance()->sendCustomerVerificationEmail($email, $firstName, $id); }
function sendUserWelcomeEmailTemplate($email, $firstName, $username, $password = null) { return EmailTemplateManager::getInstance()->sendUserWelcomeEmail($email, $firstName, $username); }
function sendSystemCredentialsEmailTemplate($email, $firstName, $lastName, $username, $passwords = [], $results = []) { return EmailTemplateManager::getInstance()->sendSystemCredentialsEmail($email, $firstName, $lastName, $username); }
function isEmailTemplateSystemAvailable() { return EmailTemplateManager::getInstance()->isTemplateSystemAvailable(); }
