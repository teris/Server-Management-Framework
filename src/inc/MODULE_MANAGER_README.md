# Modulverwaltung

Die Modulverwaltung ermöglicht das einfache Installieren, Aktualisieren und Deinstallieren von Modulen im Server Management Framework.

## Funktionen

- ✅ **Module installieren**: Laden Sie ZIP-Dateien mit neuen Modulen hoch
- ✅ **Module aktivieren/deaktivieren**: Schalten Sie Module ein oder aus, ohne sie zu deinstallieren
- ✅ **Module aktualisieren**: Aktualisieren Sie bestehende Module auf neue Versionen
- ✅ **Module deinstallieren**: Entfernen Sie Module (mit automatischem Backup)
- ✅ **Modulinformationen anzeigen**: Zeigt Autor, Version, Abhängigkeiten und mehr an

## Modul-Struktur

Jedes Modul muss eine `module.json` Datei im Root-Verzeichnis enthalten:

```json
{
    "name": "Mein Modul",
    "author": "Dein Name",
    "version": "1.0.0",
    "description": "Beschreibung des Moduls",
    "min_php": "8.0",
    "dependencies": [],
    "icon": "📦"
}
```

### module.json Felder

- **name** (erforderlich): Name des Moduls
- **author** (erforderlich): Name des Autors
- **version** (erforderlich): Versionsnummer (Semantic Versioning)
- **description** (optional): Kurze Beschreibung des Moduls
- **min_php** (optional): Minimale PHP-Version (Standard: 8.0)
- **dependencies** (optional): Array von Modul-Keys, die benötigt werden
- **icon** (optional): Emoji oder Icon für die Darstellung (Standard: 📦)

## Modul-Ordnerstruktur

```
custom-module/
├── module.json           # Modul-Informationen (NEU!)
├── Module.php            # Hauptklasse (extends ModuleBase)
├── lang/                 # Sprachdateien
│   ├── de.xml
│   └── en.xml
├── templates/            # PHP-Templates
│   └── main.php
└── assets/              # CSS/JS Dateien (optional)
    ├── module.css
    └── module.js
```

## Verwendung

### Als Administrator

1. Navigieren Sie zur Modulverwaltung im Admin-Panel
2. Wählen Sie eine der folgenden Aktionen:

#### Modul installieren

1. Klicken Sie auf "Modul hochladen"
2. Wählen Sie eine ZIP-Datei mit dem Modul
3. Optional: Geben Sie einen Modul-Schlüssel an
4. Klicken Sie auf "Hochladen"

Die ZIP-Datei sollte den Modul-Ordner enthalten:
```
module.zip
└── custom-module/
    ├── module.json
    ├── Module.php
    └── ...
```

#### Modul aktivieren/deaktivieren

- Klicken Sie auf die Schaltfläche "Aktivieren" oder "Deaktivieren" auf der Modulkarte
- Das System lädt die Seite neu, um die Änderungen zu übernehmen

#### Modul aktualisieren

1. Klicken Sie auf "Update" auf der Modulkarte
2. Wählen Sie die ZIP-Datei mit der neuen Version
3. Klicken Sie auf "Aktualisieren"

**Hinweis**: Das alte Modul wird automatisch gesichert (`.bnk` Datei)

#### Modul deinstallieren

1. Klicken Sie auf "Löschen" auf der Modulkarte
2. Bestätigen Sie die Aktion

**Hinweis**: Das Modul wird automatisch gesichert, bevor es gelöscht wird

## Technische Details

### ModuleManager Klasse

Die `ModuleManager` Klasse (`src/core/ModuleManager.php`) verwaltet alle Modul-Operationen:

```php
$moduleManager = new ModuleManager();

// Alle Module abrufen
$modules = $moduleManager->getAllModules();

// Modul-Informationen lesen
$info = $moduleManager->getModuleInfo('custom-module');

// Modul aktivieren
$moduleManager->enableModule('custom-module');

// Modul deaktivieren
$moduleManager->disableModule('custom-module');

// Modul installieren
$result = $moduleManager->installModule('/path/to/module.zip', 'custom-module');

// Modul aktualisieren
$result = $moduleManager->updateModule('custom-module', '/path/to/module.zip');

// Modul deinstallieren
$moduleManager->uninstallModule('custom-module');
```

### AJAX-Handler

Die Modulverwaltung verwendet folgende AJAX-Aktionen in `handler.php`:

- `install_module`: Installiert ein neues Modul
- `update_module`: Aktualisiert ein bestehendes Modul
- `enable_module`: Aktiviert ein Modul
- `disable_module`: Deaktiviert ein Modul
- `uninstall_module`: Deinstalliert ein Modul

### Sicherheit

- Nur Administratoren können Module verwalten
- Alle Operationen werden protokolliert
- Automatische Backups vor Updates/Deinstallationen
- PHP-Versionen werden geprüft
- Abhängigkeiten werden validiert

## Callbacks

Module können optionale Callbacks implementieren:

```php
class CustomModuleModule extends ModuleBase {
    /**
     * Wird beim Aktivieren des Moduls aufgerufen
     */
    public function onEnable() {
        // Initialisierung, z.B. Datenbank-Tabellen erstellen
    }
    
    /**
     * Wird beim Deaktivieren des Moduls aufgerufen
     */
    public function onDisable() {
        // Aufräumen, z.B. temporäre Dateien löschen
    }
}
```

## Beispiel: Eigenes Modul erstellen

1. Erstellen Sie einen Ordner in `src/module/`:
   ```
   src/module/mein-modul/
   ```

2. Erstellen Sie `module.json`:
   ```json
   {
       "name": "Mein Modul",
       "author": "Ihr Name",
       "version": "1.0.0",
       "description": "Beschreibung",
       "icon": "🎨"
   }
   ```

3. Erstellen Sie `Module.php`:
   ```php
   <?php
   require_once __DIR__ . '/../ModuleBase.php';
   
   class MeinModulModule extends ModuleBase {
       public function getContent() {
           return $this->render('main');
       }
       
       public function handleAjaxRequest($action, $data) {
           return $this->success('OK');
       }
   }
   ```

4. Erstellen Sie `templates/main.php`:
   ```php
   <div>
       <h3>Mein Modul</h3>
       <p>Inhalt hier...</p>
   </div>
   ```

5. Packen Sie alles als ZIP und laden Sie es über die Modulverwaltung hoch

## Fehlerbehebung

### Modul wird nicht angezeigt

- Prüfen Sie, ob `module.json` vorhanden und gültig ist
- Prüfen Sie die PHP-Fehlerprotokolle

### Modul kann nicht aktiviert werden

- Prüfen Sie die PHP-Version (min_php)
- Prüfen Sie, ob alle Abhängigkeiten installiert sind
- Prüfen Sie die Schreibrechte für `sys.conf.php`

### Update schlägt fehl

- Stellen Sie sicher, dass die ZIP-Datei korrekt strukturiert ist
- Prüfen Sie die Schreibrechte für den Modul-Ordner
- Das Backup (`.bnk`) kann manuell wiederhergestellt werden

## Backup-System

- Backups werden automatisch erstellt mit der Erweiterung `.bnk`
- Format: `<Modulname>.bnk` (z.B. `custom-module.bnk`)
- Vorhandene Backups werden überschrieben
- Manuelle Wiederherstellung: Benennen Sie `.bnk` zurück zum Original-Namen

## Support

Bei Problemen oder Fragen:
- Prüfen Sie die Logs in `logs/activity_*.log`
- Prüfen Sie die PHP-Fehlerprotokolle
- Erstellen Sie ein Issue auf GitHub
