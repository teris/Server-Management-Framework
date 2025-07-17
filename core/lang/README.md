# Core Language Files

Dieser Ordner enthält die mehrsprachigen Übersetzungen für die Core-Hauptdateien des Server-Management-Frameworks.

## Struktur

- `de.xml` - Deutsche Übersetzungen
- `en.xml` - Englische Übersetzungen

## Verwendung

### PHP-Seite
Die Übersetzungen werden über die `t()` und `tMultiple()` Funktionen in `sys.conf.php` verwendet:

```php
// Einzelne Übersetzung
echo t('welcome_message');

// Mehrere Übersetzungen auf einmal
$translations = tMultiple(['welcome_message', 'login_button', 'error_message']);
```

### JavaScript
Die Übersetzungen werden über die globale `t()` Funktion verwendet:

```javascript
// Einzelne Übersetzung
console.error(t('js_server_error'));

// Mit Parametern
showNotification(t('js_vm_control_success', {action: 'started'}));
```

## Übersetzungsschlüssel

### Core-UI-Elemente
- `welcome_message` - Willkommensnachricht
- `login_button` - Login-Button Text
- `logout_button` - Logout-Button Text
- `dashboard_title` - Dashboard-Titel
- `settings_title` - Einstellungen-Titel
- `profile_title` - Profil-Titel
- `logout_title` - Logout-Titel

### Validierungsmeldungen
- `validation_required` - Pflichtfeld-Fehlermeldung
- `validation_email` - E-Mail-Validierung
- `validation_min_length` - Mindestlänge-Validierung
- `validation_max_length` - Maximallänge-Validierung
- `validation_password_match` - Passwort-Bestätigung
- `validation_old_password_required` - Altes Passwort erforderlich
- `validation_new_password_required` - Neues Passwort erforderlich
- `validation_confirm_password_required` - Passwort-Bestätigung erforderlich

### Erfolgs- und Fehlermeldungen
- `login_success` - Login erfolgreich
- `login_failed` - Login fehlgeschlagen
- `logout_success` - Logout erfolgreich
- `password_change_success` - Passwort erfolgreich geändert
- `password_change_failed` - Passwort-Änderung fehlgeschlagen
- `setup_complete` - Setup abgeschlossen
- `setup_failed` - Setup fehlgeschlagen
- `update_success` - Update erfolgreich
- `update_failed` - Update fehlgeschlagen
- `session_expired` - Session abgelaufen
- `access_denied` - Zugriff verweigert
- `invalid_credentials` - Ungültige Anmeldedaten
- `database_error` - Datenbankfehler
- `configuration_error` - Konfigurationsfehler

### JavaScript-spezifische Meldungen
- `js_network_error` - Netzwerkfehler
- `js_server_error` - Server-Fehler
- `js_ajax_error` - AJAX-Fehler
- `js_session_expired` - Session abgelaufen (JS)
- `js_plugin_load_error` - Plugin-Ladefehler
- `js_unknown_error` - Unbekannter Fehler
- `js_form_submit_error` - Formular-Sendefehler
- `js_form_success` - Formular erfolgreich gesendet
- `js_vm_load_error` - VM-Ladefehler
- `js_vm_control_success` - VM-Operation erfolgreich
- `js_vm_control_error` - VM-Operationsfehler
- `js_no_vms_found` - Keine VMs gefunden
- `js_no_websites_found` - Keine Websites gefunden
- `js_no_databases_found` - Keine Datenbanken gefunden
- `js_no_emails_found` - Keine E-Mails gefunden
- `js_no_domains_found` - Keine Domains gefunden
- `js_no_logs_found` - Keine Log-Einträge gefunden
- `js_stats_updating` - Statistiken werden aktualisiert
- `js_cache_clearing` - Cache wird geleert
- `js_connections_testing` - Verbindungen werden getestet
- `js_settings_saved` - Einstellungen gespeichert
- `js_loading` - Laden...
- `js_processing` - Wird verarbeitet...
- `js_confirm_delete` - Löschen bestätigen
- `js_confirm_vm_delete` - VM-Löschung bestätigen
- `js_confirm_website_delete` - Website-Löschung bestätigen
- `js_confirm_database_delete` - Datenbank-Löschung bestätigen
- `js_confirm_email_delete` - E-Mail-Löschung bestätigen
- `js_operation_successful` - Operation erfolgreich
- `js_operation_failed` - Operation fehlgeschlagen
- `js_validation_failed` - Validierung fehlgeschlagen
- `js_access_denied` - Zugriff verweigert (JS)
- `js_timeout_error` - Zeitüberschreitung
- `js_connection_lost` - Verbindung verloren
- `js_data_load_error` - Daten-Ladefehler
- `js_data_save_error` - Daten-Speicherfehler
- `js_data_update_error` - Daten-Aktualisierungsfehler
- `js_data_delete_error` - Daten-Löschfehler
- `js_please_wait` - Bitte warten...
- `js_retry_later` - Bitte versuchen Sie es später erneut
- `js_contact_admin` - Bitte kontaktieren Sie den Administrator
- `js_debug_info` - Debug-Informationen
- `js_available_plugins` - Verfügbare Plugins
- `js_session_info` - Session-Info
- `js_not_available` - Nicht verfügbar
- `js_admin_dashboard_initialized` - Admin Dashboard initialisiert

### Admin Dashboard UI-Elemente
- `administration` - Verwaltung
- `overview` - Übersicht
- `resources` - Ressourcen
- `plugins` - Plugins
- `logs` - Logs
- `settings` - Einstellungen
- `system_overview` - System-Übersicht
- `php_version` - PHP Version
- `server` - Server
- `active_sessions` - Aktive Sessions
- `system_load` - System-Auslastung
- `quick_actions` - Schnellaktionen
- `refresh_all_stats` - Alle Stats aktualisieren
- `clear_cache` - Cache leeren
- `test_connections` - Verbindungen testen
- `resource_management` - Ressourcen-Verwaltung
- `vms` - VMs
- `websites` - Websites
- `databases` - Datenbanken
- `emails` - E-Mails
- `domains` - Domains
- `virtual_machines` - Virtuelle Maschinen
- `refresh` - Aktualisieren
- `loading` - Laden...
- `website_management` - Website Management
- `database_management` - Database Management
- `email_management` - E-Mail Management
- `email_accounts` - E-Mail-Konten
- `domain_management` - Domain Management
- `plugin_management` - Plugin-Verwaltung
- `available_plugins` - Verfügbare Plugins
- `no_description_available` - Keine Beschreibung verfügbar
- `open` - Öffnen
- `system_logs` - System-Logs
- `system_settings` - System-Einstellungen
- `general_settings` - Allgemeine Einstellungen
- `session_timeout` - Session-Timeout (Minuten)
- `auto_refresh_interval` - Auto-Refresh Intervall (Sekunden)
- `save` - Speichern
- `system_status` - System-Status
- `cache_status` - Cache-Status
- `active` - Aktiv
- `api_connections` - API-Verbindungen
- `all_ok` - Alle OK
- `last_update` - Letzte Aktualisierung
- `notification` - Benachrichtigung
- `message_here` - Nachricht hier...
- `name` - Name
- `status` - Status
- `cpu` - CPU
- `ram` - RAM
- `storage` - Speicher
- `actions` - Aktionen
- `running` - Laufend
- `stopped` - Gestoppt
- `start` - Start
- `stop` - Stop
- `delete` - Löschen
- `unknown` - Unbekannt

### Datums- und Zeitformate
- `today` - Heute
- `yesterday` - Gestern
- `this_week` - Diese Woche
- `last_week` - Letzte Woche
- `this_month` - Dieser Monat
- `last_month` - Letzter Monat
- `this_year` - Dieses Jahr
- `last_year` - Letztes Jahr
- `next_year` - Nächstes Jahr

## Hinzufügen neuer Übersetzungen

1. Fügen Sie den neuen Schlüssel zu beiden XML-Dateien hinzu
2. Verwenden Sie den Schlüssel in der entsprechenden PHP- oder JavaScript-Datei
3. Für JavaScript-Übersetzungen fügen Sie den Schlüssel zur `jsTranslations`-Liste in `index.php` hinzu

## Beispiel für neue Übersetzung

```xml
<!-- In de.xml -->
<new_message>Neue Nachricht</new_message>

<!-- In en.xml -->
<new_message>New Message</new_message>
```

```php
// In PHP
echo t('new_message');
```

```javascript
// In JavaScript
console.log(t('new_message'));
``` 