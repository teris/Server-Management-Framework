-- E-Mail-Template-Verwaltung Datenbankstruktur
-- TODO: Diese Datei erstellt die Datenbankstruktur für das E-Mail-Template-System
-- TODO: Standard-Templates werden automatisch beim ersten Aufruf erstellt
-- TODO: Alle E-Mail-Funktionen sollten auf diese Tabelle zugreifen
-- Für Server Management Framework

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='E-Mail-Templates für das System';

-- Standard-Templates einfügen
INSERT INTO `email_templates` (`template_name`, `subject`, `html_content`, `raw_content`, `variables`) VALUES
('Kunden-Willkommens-E-Mail', 'Willkommen bei {site_name}!', 
'<html>
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
</html>',
'Willkommen bei {site_name}!

Hallo {firstName},

Ihr Konto wurde erfolgreich erstellt und ist sofort aktiv. Sie können sich jetzt in unserem Kundenportal anmelden.

Ihre Anmeldedaten:
E-Mail: {email}
Passwort: {password}

Anmelden: {loginUrl}

Falls Sie Fragen haben, können Sie sich gerne an unseren Support wenden.

Mit freundlichen Grüßen
Ihr {site_name} Team',
'firstName,email,password,loginUrl,site_name'),

('Kunden-Verifikations-E-Mail', 'E-Mail-Adresse bestätigen - {site_name}',
'<html>
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
</html>',
'Willkommen bei {site_name}!

Hallo {firstName},

vielen Dank für Ihre Registrierung. Um Ihr Konto zu aktivieren, bestätigen Sie bitte Ihre E-Mail-Adresse.

Ihre Anmeldedaten:
E-Mail: {email}
Passwort: {password}

E-Mail bestätigen: {verificationLink}

Dieser Link ist 24 Stunden gültig.

Mit freundlichen Grüßen
Ihr {site_name} Team',
'firstName,email,password,verificationLink,site_name'),

('Backend-Benutzer-Willkommens-E-Mail', 'Ihre Backend-Anmeldedaten - {site_name}',
'<html>
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
</html>',
'Willkommen im Backend von {site_name}!

Hallo {firstName},

Ihr Backend-Benutzerkonto wurde erfolgreich erstellt. Sie können sich jetzt im Admin-Bereich anmelden.

Ihre Anmeldedaten:
Benutzername: {username}
E-Mail: {email}
Passwort: {password}

Anmelden: {loginUrl}

Falls Sie Fragen haben, können Sie sich gerne an den Administrator wenden.

Mit freundlichen Grüßen
Ihr {site_name} Team',
'firstName,username,email,password,loginUrl,site_name'),

('System-Anmeldedaten-E-Mail', 'Ihre System-Anmeldedaten - {site_name}',
'<html>
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
        
        <p>Falls Sie Fragen haben oder Probleme beim Login haben, kontaktieren Sie uns gerne unter <strong>support@example.com</strong></p>
        
        <p>Mit freundlichen Grüßen<br>
        Ihr <strong>{site_name}</strong> Team</p>
    </div>
</body>
</html>',
'Ihre System-Anmeldedaten

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

Falls Sie Fragen haben oder Probleme beim Login haben, kontaktieren Sie uns gerne unter support@example.com

Mit freundlichen Grüßen
Ihr {site_name} Team',
'firstName,lastName,email,username,systemPasswords,portalLinks,loginUrl,site_name,systemCredentials');

-- Index für bessere Performance
CREATE INDEX `idx_template_name` ON `email_templates` (`template_name`);
CREATE INDEX `idx_created_at` ON `email_templates` (`created_at`);
