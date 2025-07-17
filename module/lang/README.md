# Sprachordner - Mehrsprachiges System

## Übersicht

Dieser Ordner enthält die Sprachdateien für das mehrsprachige System des Server-Management-Frameworks. Jedes Modul kann einen eigenen `lang`-Ordner haben, der XML-Dateien für verschiedene Sprachen enthält.

## Struktur

```
module/
├── admin/
│   ├── lang/
│   │   ├── de.xml    # Deutsche Übersetzungen (Standard)
│   │   ├── en.xml    # Englische Übersetzungen
│   │   ├── fr.xml    # Französische Übersetzungen
│   │   └── es.xml    # Spanische Übersetzungen
│   ├── Module.php
│   └── templates/
├── proxmox/
│   ├── lang/
│   │   ├── de.xml
│   │   └── en.xml
│   ├── Module.php
│   └── templates/
└── ...
```

## Sprachdateien erstellen

### 1. Ordnerstruktur

Erstellen Sie für Ihr Modul einen `lang`-Ordner:

```bash
mkdir -p module/ihr-modul/lang
```

### 2. XML-Datei erstellen

Erstellen Sie eine XML-Datei für jede unterstützte Sprache:

**de.xml (Deutsch - Standard):**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<translations>
    <!-- Modul-Titel -->
    <module_title>Ihr Modul</module_title>
    
    <!-- Allgemeine Texte -->
    <welcome_message>Willkommen in Ihrem Modul</welcome_message>
    <loading>Laden...</loading>
    
    <!-- Buttons -->
    <save>Speichern</save>
    <cancel>Abbrechen</cancel>
    <delete>Löschen</delete>
    
    <!-- Erfolgsmeldungen -->
    <operation_successful>Operation erfolgreich</operation_successful>
    
    <!-- Fehlermeldungen -->
    <operation_failed>Operation fehlgeschlagen</operation_failed>
</translations>
```

**en.xml (Englisch):**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<translations>
    <!-- Module Title -->
    <module_title>Your Module</module_title>
    
    <!-- General Texts -->
    <welcome_message>Welcome to your module</welcome_message>
    <loading>Loading...</loading>
    
    <!-- Buttons -->
    <save>Save</save>
    <cancel>Cancel</cancel>
    <delete>Delete</delete>
    
    <!-- Success Messages -->
    <operation_successful>Operation successful</operation_successful>
    
    <!-- Error Messages -->
    <operation_failed>Operation failed</operation_failed>
</translations>
```

## Verwendung in Modulen

### 1. ModuleBase erweitern

```php
class IhrModul extends ModuleBase {
    
    public function getContent() {
        // Mehrere Übersetzungen laden
        $translations = $this->tMultiple([
            'module_title', 'welcome_message', 'save', 'cancel'
        ]);
        
        return $this->render('main', [
            'translations' => $translations
        ]);
    }
    
    public function handleAjaxRequest($action, $data) {
        switch ($action) {
            case 'save_data':
                try {
                    // ... Daten speichern
                    return $this->success($result, $this->t('operation_successful'));
                } catch (Exception $e) {
                    return $this->error($this->t('operation_failed') . ': ' . $e->getMessage());
                }
        }
    }
}
```

### 2. In Templates verwenden

```php
<div class="card">
    <div class="card-header">
        <h3><?php echo $translations['module_title']; ?></h3>
    </div>
    <div class="card-body">
        <p><?php echo $translations['welcome_message']; ?></p>
        
        <div class="btn-group">
            <button class="btn btn-primary"><?php echo $translations['save']; ?></button>
            <button class="btn btn-secondary"><?php echo $translations['cancel']; ?></button>
        </div>
    </div>
</div>
```

## Best Practices

### 1. Schlüssel-Benennung

- Verwenden Sie aussagekräftige, konsistente Namen
- Gruppieren Sie verwandte Schlüssel mit Präfixen
- Verwenden Sie snake_case für Schlüsselnamen

**Beispiele:**
```
module_title
welcome_message
button_save
button_cancel
error_validation_failed
success_operation_completed
```

### 2. Kategorisierung

Gruppieren Sie Übersetzungen in logische Kategorien mit Kommentaren:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<translations>
    <!-- Modul-Titel -->
    <module_title>Admin-Modul</module_title>
    
    <!-- Schnellaktionen -->
    <manage_vms>VMs verwalten</manage_vms>
    <websites>Websites</websites>
    
    <!-- Erfolgsmeldungen -->
    <vm_deleted_successfully>VM erfolgreich gelöscht</vm_deleted_successfully>
    
    <!-- Fehlermeldungen -->
    <error_getting_vms>Fehler beim Abrufen der VMs</error_getting_vms>
    
    <!-- Buttons -->
    <edit>Bearbeiten</edit>
    <delete>Löschen</delete>
</translations>
```

### 3. Vollständigkeit

Stellen Sie sicher, dass alle Sprachdateien die gleichen Schlüssel enthalten:

- Erstellen Sie zuerst die deutsche Standarddatei (`de.xml`)
- Kopieren Sie die Struktur für andere Sprachen
- Übersetzen Sie alle Werte vollständig

### 4. HTML-Escaping

Für HTML-Inhalte verwenden Sie `htmlspecialchars()`:

```xml
<welcome_message>&lt;strong&gt;Willkommen&lt;/strong&gt; in Ihrem Modul</welcome_message>
```

## Fallback-Mechanismus

Das System verwendet einen intelligenten Fallback-Mechanismus:

1. **Aktuelle Sprache**: Versucht die konfigurierte Sprache zu laden
2. **Standardsprache**: Fallback auf Deutsch (`de.xml`)
3. **Schlüssel-Fallback**: Verwendet den Schlüssel selbst oder einen Standardwert

## Verfügbare Sprachen

Die verfügbaren Sprachen werden in `sys.conf.php` konfiguriert:

```php
$system_config = [
    'available_languages' => ['de', 'en', 'fr', 'es', 'it'],
    // ...
];
```

## Testen

Verwenden Sie das Test-Skript, um Ihr mehrsprachiges System zu testen:

```bash
php debug/test_multilanguage.php
```

## Migration bestehender Module

### 1. Texte identifizieren

Suchen Sie in Ihrem Modul nach hartcodierten Texten:

```php
// Vorher
return '<div class="alert alert-danger">Zugriff verweigert</div>';

// Nachher
return '<div class="alert alert-danger">' . $this->t('access_denied') . '</div>';
```

### 2. XML-Datei erstellen

Erstellen Sie `lang/de.xml` mit allen verwendeten Texten.

### 3. Module aktualisieren

Ersetzen Sie alle hartcodierten Texte durch Übersetzungsaufrufe.

### 4. Templates anpassen

Übergeben Sie Übersetzungen an Templates und verwenden Sie sie dort.

## Troubleshooting

### Häufige Probleme

1. **Übersetzungen werden nicht angezeigt**
   - Prüfen Sie den Pfad zur XML-Datei
   - Prüfen Sie die XML-Syntax
   - Prüfen Sie, ob der Schlüssel existiert

2. **Fallback funktioniert nicht**
   - Stellen Sie sicher, dass `de.xml` existiert
   - Prüfen Sie die Dateiberechtigungen

3. **Performance-Probleme**
   - Der Cache wird automatisch verwaltet
   - Bei Problemen: `$lm->clearCache()` aufrufen

### Debugging

```php
// Debug-Informationen ausgeben
$lm = getLanguageManager();
echo "Aktuelle Sprache: " . $lm->getCurrentLanguage() . "\n";
echo "Modul-Sprachen: " . implode(', ', $lm->getAvailableLanguagesForModule('ihr-modul')) . "\n";

// Übersetzungen laden
$translations = $lm->loadModuleTranslations('ihr-modul');
print_r($translations);
```

## Weitere Informationen

- [Vollständige Dokumentation](../MULTILANGUAGE_SYSTEM.md)
- [Test-Skript](../debug/test_multilanguage.php)
- [Beispiel-Implementierungen](../module/admin/lang/) 