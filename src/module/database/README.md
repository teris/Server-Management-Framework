# Database-Modul - Mehrsprachige Dokumentation

## Übersicht

Das Database-Modul ermöglicht die Verwaltung von MySQL/MariaDB-Datenbanken über das ISPConfig-API. Es ist vollständig mehrsprachig implementiert und unterstützt Deutsch und Englisch.

## Funktionen

### 🗄️ Datenbank-Erstellung
- Erstellen neuer MySQL/MariaDB-Datenbanken
- Automatische Benutzer-Erstellung mit sicheren Passwörtern
- UTF8MB4-Zeichensatz für volle Unicode-Unterstützung

### 🔧 Erweiterte Funktionen
- Sichere Passwort-Generierung
- Datenbank-Server-Informationen
- phpMyAdmin-Integration
- Verbindungsdaten-Anzeige

### 🌍 Mehrsprachigkeit
- Vollständige deutsche und englische Übersetzungen
- Dynamische Sprachumschaltung
- Fallback auf Deutsch bei fehlenden Übersetzungen

## Sprachdateien

### Struktur
```
module/database/lang/
├── de.xml          # Deutsche Übersetzungen
└── en.xml          # Englische Übersetzungen
```

### Wichtige Übersetzungsschlüssel

#### Formular-Elemente
- `module_title` - Modul-Titel
- `create_database` - Datenbank erstellen
- `database_name` - Datenbank Name
- `database_user` - Datenbank Benutzer
- `password` - Passwort
- `password_min_length` - Passwort-Hinweis

#### Informationen
- `connection_info` - Verbindungsdaten
- `connection_details` - Verbindungsdetails
- `host` - Host
- `port` - Port
- `charset` - Zeichensatz
- `host_info` - Host-Information
- `port_info` - Port-Information
- `charset_info` - Zeichensatz-Information

#### Erweiterte Optionen
- `advanced_options` - Erweiterte Optionen
- `database_server_info` - Datenbank-Server Info
- `generate_secure_password` - Sicheres Passwort generieren

#### Meldungen
- `database_created_successfully` - Erfolgreich erstellt
- `database_deleted_successfully` - Erfolgreich gelöscht
- `secure_password_generated` - Passwort generiert
- `database_info_message` - Server-Info
- `database_created_message` - Erstellungsmeldung

#### Fehlerbehandlung
- `unknown_action` - Unbekannte Aktion
- `admin_rights_required` - Admin-Rechte erforderlich
- `validation_failed` - Validierung fehlgeschlagen
- `error_creating_database` - Fehler beim Erstellen
- `error_deleting_database` - Fehler beim Löschen
- `error_getting_databases` - Fehler beim Abrufen
- `network_error` - Netzwerkfehler
- `unknown_error` - Unbekannter Fehler

## Verwendung

### PHP-Code

```php
// Module instanziieren
$module = new DatabaseModule();

// Übersetzungen abrufen
$translations = $module->tMultiple([
    'module_title',
    'create_database',
    'database_name'
]);

// Einzelne Übersetzung
$title = $module->t('module_title');

// Template rendern
$content = $module->getContent();
```

### JavaScript

```javascript
// Übersetzungen laden
databaseModule.loadTranslations();

// Übersetzung verwenden
const message = databaseModule.t('database_created_message');

// Mit Parametern
const alertMessage = databaseModule.t('database_connection_alert', {
    dbName: 'my_database',
    dbUser: 'db_user'
});
```

### AJAX-Endpunkte

#### Datenbank erstellen
```javascript
const formData = new FormData();
formData.append('name', 'my_database');
formData.append('user', 'db_user');
formData.append('password', 'secure_password');

const result = await ModuleManager.makeRequest('database', 'create_database', formData);
```

#### Übersetzungen abrufen
```javascript
const result = await fetch('?module=database&action=get_translations');
const translations = await result.json();
```

## Template-Struktur

Das Template verwendet Bootstrap-Klassen für ein modernes Design:

```html
<div class="card">
    <div class="card-header">
        <h2>🗄️ <?php echo $translations['module_title']; ?></h2>
    </div>
    <div class="card-body">
        <!-- Formular-Inhalt -->
    </div>
</div>
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
const message = databaseModule.t('new_key');
```

### Parameter in Übersetzungen

```xml
<welcome_message>Willkommen, {username}!</welcome_message>
```

```php
$message = $this->t('welcome_message', ['username' => 'John']);
```

### Validierung

```php
$errors = $this->validate($data, [
    'name' => 'required|min:3|max:50',
    'user' => 'required|min:3|max:20',
    'password' => 'required|min:6'
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
php debug/test_database_multilingual.php
```

### Tests umfassen
- ✅ Sprachdateien-Validierung
- ✅ LanguageManager-Tests
- ✅ Module-Übersetzungen
- ✅ AJAX-Funktionalität
- ✅ Template-Rendering
- ✅ Fehlerbehandlung

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

1. Sprachdatei erstellen: `module/database/lang/fr.xml`
2. In `sys.conf.php` hinzufügen: `$available_languages[] = 'fr';`
3. Übersetzungen vervollständigen

### Neue Funktionen

1. Übersetzungsschlüssel definieren
2. PHP-Code implementieren
3. Template anpassen
4. JavaScript erweitern
5. Tests schreiben

## Support

Bei Fragen oder Problemen:
- Dokumentation prüfen
- Testskript ausführen
- Logs analysieren
- GitHub-Issue erstellen 