# Email-Modul - Mehrsprachige Dokumentation

## Übersicht

Das Email-Modul ermöglicht die Verwaltung von E-Mail-Accounts über das ISPConfig-API. Es ist vollständig mehrsprachig implementiert und unterstützt Deutsch und Englisch.

## Funktionen

### 📧 E-Mail-Erstellung
- Erstellen neuer E-Mail-Accounts mit ISPConfig
- Automatische Benutzer-Erstellung mit sicheren Passwörtern
- Speicherplatz-Quota-Verwaltung
- Domain-spezifische E-Mail-Adressen

### 📱 E-Mail-Client-Konfiguration
- IMAP/SMTP-Server-Konfiguration
- SSL/TLS-Verschlüsselung
- Alternative Ports (POP3, unverschlüsselt)
- Automatische Konfigurationsdaten

### 🌐 Webmail-Integration
- Roundcube Webmail-Zugang
- Horde Webmail-Zugang
- Domain-spezifische Webmail-URLs
- Direkte Links zu Webmail-Interfaces

### 🔧 Erweiterte Funktionen
- Sichere Passwort-Generierung
- E-Mail-Client-Konfigurationshilfe
- Erweiterte E-Mail-Funktionen (Autoresponder, Weiterleitungen, etc.)

### 🌍 Mehrsprachigkeit
- Vollständige deutsche und englische Übersetzungen
- Dynamische Sprachumschaltung
- Fallback auf Deutsch bei fehlenden Übersetzungen

## Sprachdateien

### Struktur
```
module/email/lang/
├── de.xml          # Deutsche Übersetzungen
└── en.xml          # Englische Übersetzungen
```

### Wichtige Übersetzungsschlüssel

#### Formular-Elemente
- `module_title` - Modul-Titel
- `create_email` - E-Mail Adresse erstellen
- `email_address` - E-Mail Adresse
- `login_name` - Login Name
- `password` - Passwort
- `storage_space` - Speicherplatz (MB)
- `full_name` - Vollständiger Name (optional)
- `domain` - Domain

#### E-Mail-Client-Konfiguration
- `email_client_config` - E-Mail Client Konfiguration
- `imap_receive` - IMAP (Empfang)
- `smtp_send` - SMTP (Versand)
- `server` - Server
- `port` - Port
- `security` - Sicherheit
- `username` - Benutzername
- `authentication` - Authentifizierung
- `required` - Erforderlich
- `alternative_ports` - Alternative Ports

#### Webmail-Zugang
- `webmail_access` - Webmail Zugang
- `webmail_description` - Webmail-Beschreibung
- `roundcube_webmail` - Roundcube Webmail
- `horde_webmail` - Horde Webmail
- `generate_secure_password` - Sicheres Passwort generieren

#### Erweiterte Funktionen
- `advanced_email_functions` - Erweiterte E-Mail Funktionen
- `autoresponder` - Autoresponder (Abwesenheitsnotiz)
- `email_forwarding` - E-Mail Weiterleitungen
- `spam_filter_settings` - Spam-Filter Einstellungen
- `email_aliases` - E-Mail Aliase
- `catch_all_addresses` - Catch-All Adressen
- `ispconfig_note` - ISPConfig-Hinweis

#### Meldungen
- `email_created_successfully` - E-Mail Account erfolgreich erstellt
- `email_deleted_successfully` - E-Mail Account erfolgreich gelöscht
- `secure_password_generated` - Sicheres Passwort generiert
- `email_created_message` - E-Mail Adresse wurde erfolgreich erstellt!
- `please_enter_domain` - Bitte geben Sie zuerst Ihre Domain ein
- `webmail_url` - Webmail URL

#### Fehlerbehandlung
- `unknown_action` - Unbekannte Aktion
- `admin_rights_required` - Admin-Rechte erforderlich
- `validation_failed` - Validierung fehlgeschlagen
- `error_creating_email` - Fehler beim Erstellen der E-Mail
- `error_deleting_email` - Fehler beim Löschen der E-Mail
- `error_getting_emails` - Fehler beim Abrufen der E-Mails
- `network_error` - Netzwerkfehler
- `unknown_error` - Unbekannter Fehler

## Verwendung

### PHP-Code

```php
// Module instanziieren
$module = new EmailModule();

// Übersetzungen abrufen
$translations = $module->tMultiple([
    'module_title',
    'create_email',
    'email_address'
]);

// Einzelne Übersetzung
$title = $module->t('module_title');

// Template rendern
$content = $module->getContent();
```

### JavaScript

```javascript
// Übersetzungen laden
emailModule.loadTranslations();

// Übersetzung verwenden
const message = emailModule.t('email_created_message');

// Mit Parametern
const configInfo = emailModule.t('email_config_alert', {
    email: 'user@example.com',
    domain: 'example.com'
});
```

### AJAX-Endpunkte

#### E-Mail erstellen
```javascript
const formData = new FormData();
formData.append('email', 'user@example.com');
formData.append('login', 'user');
formData.append('password', 'secure_password');
formData.append('quota', '1000');
formData.append('domain', 'example.com');

const result = await ModuleManager.makeRequest('email', 'create_email', formData);
```

#### Übersetzungen abrufen
```javascript
const result = await fetch('?module=email&action=get_translations');
const translations = await result.json();
```

## Template-Struktur

Das Template verwendet Bootstrap-Klassen für ein modernes Design:

```html
<div class="card">
    <div class="card-header">
        <h2>📧 <?php echo $translations['module_title']; ?></h2>
    </div>
    <div class="card-body">
        <!-- Formular-Inhalt -->
    </div>
</div>
```

## E-Mail-Client-Konfiguration

### IMAP (Empfang)
- **Server:** mail.ihre-domain.de
- **Port:** 993 (SSL/TLS)
- **Sicherheit:** SSL/TLS
- **Benutzername:** Ihre E-Mail-Adresse

### SMTP (Versand)
- **Server:** mail.ihre-domain.de
- **Port:** 587 (STARTTLS)
- **Sicherheit:** STARTTLS
- **Authentifizierung:** Erforderlich

### Alternative Ports
- **IMAP:** 143 (STARTTLS)
- **POP3:** 995 (SSL/TLS), 110 (STARTTLS)
- **SMTP:** 465 (SSL/TLS), 25 (unverschlüsselt - nicht empfohlen)

## Webmail-Zugang

### Roundcube Webmail
```
https://ihre-domain.de/webmail
```

### Horde Webmail
```
https://ihre-domain.de/horde
```

## Konfiguration

### Sprachauswahl
Die Sprache wird in `sys.conf.php` konfiguriert:

```php
// Standardsprache
$_SESSION['language'] = 'de';

// Verfügbare Sprachen
$available_languages = ['de', 'en'];
```

### Fallback-Verhalten
- Primär: Gewählte Sprache
- Sekundär: Deutsch (Standard)
- Tertiär: Übersetzungsschlüssel

## Best Practices

### Neue Übersetzungen hinzufügen

1. **Sprachdatei erweitern**
```xml
<new_key>Neue Übersetzung</new_key>
```

2. **Module-Code aktualisieren**
```php
$translation = $this->t('new_key');
```

3. **JavaScript erweitern**
```javascript
const message = emailModule.t('new_key');
```

### Parameter in Übersetzungen

```xml
<email_config_alert>E-Mail Account erfolgreich erstellt!\n\nE-Mail: {email}\n\nIMAP Server: mail.{domain}</email_config_alert>
```

```php
$message = $this->t('email_config_alert', [
    'email' => 'user@example.com',
    'domain' => 'example.com'
]);
```

### Validierung

```php
$errors = $this->validate($data, [
    'email' => 'required|email',
    'login' => 'required|min:3|max:20',
    'password' => 'required|min:6',
    'quota' => 'required|numeric|min:10',
    'domain' => 'required'
]);

if (!empty($errors)) {
    return $this->error($this->t('validation_failed'), $errors);
}
```

## Fehlerbehandlung

### Übersetzungsfehler
- Fehlende Schlüssel werden als Schlüssel selbst zurückgegeben
- Ungültige XML-Dateien werden ignoriert
- Fallback auf Standardsprache

### AJAX-Fehler
```php
try {
    // Operation ausführen
    return $this->success($result, $this->t('operation_successful'));
} catch (Exception $e) {
    return $this->error($this->t('operation_failed') . ': ' . $e->getMessage());
}
```

## Testing

### Testskript ausführen
```bash
php debug/test_email_multilingual.php
```

### Tests umfassen
- ✅ Sprachdateien-Validierung
- ✅ LanguageManager-Tests
- ✅ Module-Übersetzungen
- ✅ AJAX-Funktionalität
- ✅ Template-Rendering
- ✅ Fehlerbehandlung
- ✅ E-Mail-spezifische Übersetzungen

## Wartung

### Cache leeren
```php
// LanguageManager-Cache leeren
$language_manager = new LanguageManager();
$language_manager->clearCache();
```

### Logs prüfen
```php
// Übersetzungsfehler loggen
$this->log('Translation missing: ' . $key, 'WARNING');
```

## Erweiterungen

### Neue Sprache hinzufügen

1. Sprachdatei erstellen: `module/email/lang/fr.xml`
2. In `sys.conf.php` hinzufügen: `$available_languages[] = 'fr';`
3. Übersetzungen vervollständigen

### Neue Funktionen

1. Übersetzungsschlüssel definieren
2. PHP-Code implementieren
3. Template anpassen
4. JavaScript erweitern
5. Tests schreiben

## E-Mail-spezifische Features

### Sichere Passwort-Generierung
- Mindestens 12 Zeichen
- Groß- und Kleinbuchstaben
- Zahlen und Sonderzeichen
- Zufällige Reihenfolge

### Webmail-Integration
- Automatische URL-Generierung
- Domain-spezifische Links
- Direkte Öffnung in neuem Tab

### E-Mail-Client-Konfiguration
- Automatische Konfigurationsdaten
- SSL/TLS-Verschlüsselung
- Alternative Ports
- Benutzerfreundliche Anzeige

## Support

Bei Fragen oder Problemen:
- Dokumentation prüfen
- Testskript ausführen
- Logs analysieren
- GitHub-Issue erstellen 