# Server Management Interface - Modulsystem

## Übersicht

Das Server Management Interface verwendet ein modulares System, das es ermöglicht, neue Funktionen einfach hinzuzufügen oder zu entfernen. Jedes Modul ist eigenständig und kann über die `sys.conf.php` aktiviert oder deaktiviert werden.

## Modulstruktur

Jedes Modul folgt dieser Struktur:

```
modules/
├── ModuleBase.php          # Abstrakte Basisklasse für alle Module
├── README.md               # Diese Datei
├── admin/                  # Admin-Modul
│   ├── Module.php          # Hauptklasse des Moduls
│   ├── templates/          # HTML-Templates
│   │   └── main.php        # Haupt-Template
│   └── assets/             # Optional: CSS/JS
│       ├── module.css
│       └── module.js
├── proxmox/                # Proxmox-Modul
│   ├── Module.php
│   └── templates/
│       └── main.php
└── [weitere-module]/
```

## Neues Modul erstellen

### 1. Modul-Verzeichnis erstellen

```bash
mkdir -p modules/mein-modul/templates
```

### 2. Module.php erstellen

```php
<?php
require_once dirname(dirname(__FILE__)) . '/ModuleBase.php';

class MeinModulModule extends ModuleBase {
    
    public function getContent() {
        // HTML-Content zurückgeben
        return $this->render('main', [
            'data' => $this->getData()
        ]);
    }
    
    public function handleAjaxRequest($action, $data) {
        switch ($action) {
            case 'meine_action':
                return $this->meineAction($data);
            default:
                return $this->error('Unknown action');
        }
    }
    
    private function meineAction($data) {
        // Validierung
        $errors = $this->validate($data, [
            'field' => 'required|min:3'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        // Logik hier...
        
        return $this->success($result);
    }
}
```

### 3. Template erstellen

`modules/mein-modul/templates/main.php`:

```php
<div id="mein-modul" class="tab-content">
    <h2>🎯 Mein Modul</h2>
    
    <form onsubmit="handleSubmit(event)">
        <div class="form-group">
            <label for="field">Eingabefeld</label>
            <input type="text" id="field" name="field" required>
        </div>
        
        <button type="submit" class="btn">
            <span class="loading hidden"></span>
            Absenden
        </button>
    </form>
</div>

<script>
async function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    
    try {
        const result = await ModuleManager.makeRequest('mein-modul', 'meine_action', formData);
        if (result.success) {
            showNotification('Erfolgreich!', 'success');
        } else {
            showNotification(result.error, 'error');
        }
    } catch (error) {
        showNotification('Fehler: ' + error.message, 'error');
    }
}
</script>
```

### 4. Modul registrieren

In `sys.conf.php` hinzufügen:

```php
'mein-modul' => [
    'enabled' => true,
    'name' => 'Mein Modul',
    'icon' => '🎯',
    'path' => 'modules/mein-modul',
    'require_admin' => false,
    'order' => 10,
    'description' => 'Beschreibung meines Moduls'
],
```

## Verfügbare Methoden in ModuleBase

### Pflichtmethoden

- `getContent()` - Gibt den HTML-Content für den Tab zurück
- `handleAjaxRequest($action, $data)` - Verarbeitet AJAX-Requests

### Optionale Methoden

- `init()` - Wird beim Laden des Moduls aufgerufen
- `getScripts()` - Array mit Pfaden zu JavaScript-Dateien
- `getStyles()` - Array mit Pfaden zu CSS-Dateien
- `getStats()` - Statistiken für das Dashboard
- `onEnable()` - Wird beim Aktivieren des Moduls aufgerufen
- `onDisable()` - Wird beim Deaktivieren des Moduls aufgerufen

### Helper-Methoden

- `render($template, $data)` - Rendert ein Template
- `validate($data, $rules)` - Validiert Eingabedaten
- `success($data, $message)` - Erfolgsantwort
- `error($message, $data)` - Fehlerantwort
- `log($message, $level)` - Schreibt ins Log
- `requireAdmin()` - Prüft Admin-Rechte

## Validierungsregeln

Verfügbare Validierungsregeln:

- `required` - Feld ist erforderlich
- `email` - Muss eine gültige E-Mail sein
- `numeric` - Muss numerisch sein
- `min:X` - Mindestlänge X Zeichen
- `max:X` - Maximallänge X Zeichen

Beispiel:
```php
$errors = $this->validate($data, [
    'email' => 'required|email',
    'age' => 'required|numeric|min:18|max:100',
    'name' => 'required|min:3|max:50'
]);
```

## JavaScript Integration

### Module Manager verwenden

```javascript
// AJAX-Request an ein Modul
const result = await ModuleManager.makeRequest('modul-name', 'action', {
    param1: 'value1',
    param2: 'value2'
});

// Aktuelles Modul abrufen
const currentModule = ModuleManager.currentModule;
```

### Globale Funktionen

- `showNotification(message, type)` - Zeigt eine Benachrichtigung
- `setLoading(form, loading)` - Setzt Loading-State für Formulare
- `filterTable(tableId, searchValue)` - Filtert Tabellen

## Best Practices

1. **Separation of Concerns**: Trennen Sie Logik, Präsentation und Daten
2. **Validierung**: Validieren Sie immer Benutzereingaben
3. **Fehlerbehandlung**: Fangen Sie Exceptions ab und geben Sie sinnvolle Fehlermeldungen zurück
4. **Logging**: Loggen Sie wichtige Aktionen und Fehler
5. **Sicherheit**: Prüfen Sie Berechtigungen und sanitizen Sie Eingaben
6. **Lazy Loading**: Laden Sie Daten nur wenn nötig
7. **Caching**: Nutzen Sie Caching für häufig abgerufene Daten

## Beispiel: Komplettes Modul

Siehe die vorhandenen Module als Beispiele:

- `admin/` - Komplexes Modul mit mehreren Tabs und Tabellen
- `proxmox/` - Modul mit Formular und API-Integration
- `virtual-mac/` - Modul mit mehreren Unterseiten

## Debugging

Bei `debug_mode = true` in `sys.conf.php`:

- Detaillierte Fehlermeldungen werden angezeigt
- Console.log zeigt zusätzliche Informationen
- PHP-Fehler werden vollständig angezeigt

## Module deaktivieren

In `sys.conf.php`:

```php
'mein-modul' => [
    'enabled' => false,  // Modul deaktivieren
    // ...
],
```

Deaktivierte Module werden nicht geladen und erscheinen nicht in der Navigation.