# Modul-System

Das Modul-System des Server Management Frameworks ermöglicht die Erweiterung und Anpassung der Funktionalität durch eigene Module.

## 🏗️ Modul-Architektur

Das Framework verwendet eine modulare Architektur, bei der jedes Modul unabhängig aktiviert oder deaktiviert werden kann:

```
module/
├── admin/           # Admin-Modul
├── database/        # Datenbank-Modul
├── dns/            # DNS-Modul
├── email/          # E-Mail-Modul
├── endpoints/      # Endpoints-Modul
├── ispconfig/      # ISPConfig-Modul
├── network/        # Netzwerk-Modul
├── ovh/            # OVH-Modul
├── proxmox/        # Proxmox-Modul
├── virtual-mac/    # Virtual MAC-Modul
└── custom-module/  # Beispiel für ein benutzerdefiniertes Modul
```

## 📁 Modul-Struktur

Jedes Modul folgt einer einheitlichen Struktur:

```
module-name/
├── assets/         # CSS, JS, Bilder
│   ├── module.js
│   └── style.css
├── lang/           # Sprachdateien
│   ├── de.xml
│   └── en.xml
├── templates/      # HTML-Templates
│   └── main.php
├── Module.php      # Hauptmodul-Klasse
└── README.md       # Modul-Dokumentation
```

## 🔧 Modul-Funktionen

### getAllModules()
Gibt alle verfügbaren Module zurück.

```php
$modules = getAllModules();
foreach ($modules as $module) {
    echo "Modul: {$module['name']} - Status: {$module['enabled']}\n";
}
```

### getEnabledModules()
Gibt nur die aktivierten Module zurück.

```php
$enabledModules = getEnabledModules();
foreach ($enabledModules as $module) {
    echo "Aktiviertes Modul: {$module['name']}\n";
}
```

### canAccessModule($module_key, $user_role)
Prüft ob ein Benutzer auf ein Modul zugreifen darf.

```php
$hasAccess = canAccessModule('proxmox', 'admin');
if ($hasAccess) {
    echo "Benutzer hat Zugriff auf Proxmox-Modul\n";
} else {
    echo "Benutzer hat keinen Zugriff auf Proxmox-Modul\n";
}
```

## 📝 Eigene Module erstellen

### 1. Modul-Verzeichnis erstellen

```bash
mkdir module/my-custom-module
cd module/my-custom-module
```

### 2. Modul-Struktur erstellen

```bash
mkdir assets lang templates
touch Module.php README.md
touch assets/module.js assets/style.css
touch lang/de.xml lang/en.xml
touch templates/main.php
```

### 3. Hauptmodul-Klasse erstellen

```php
<?php
// module/my-custom-module/Module.php

class MyCustomModule extends ModuleBase {
    
    public function __construct() {
        parent::__construct();
        $this->module_name = 'my-custom-module';
        $this->module_title = 'Mein Benutzerdefiniertes Modul';
        $this->module_description = 'Ein Beispiel für ein benutzerdefiniertes Modul';
        $this->module_version = '1.0.0';
        $this->module_author = 'Ihr Name';
    }
    
    public function init() {
        // Modul-Initialisierung
        $this->loadLanguage();
        $this->registerRoutes();
    }
    
    public function getMenuItems() {
        return [
            [
                'title' => $this->t('menu_title'),
                'url' => 'my-custom-module',
                'icon' => 'fas fa-cog',
                'permission' => 'my-custom-module.access'
            ]
        ];
    }
    
    public function handleRequest($action) {
        switch ($action) {
            case 'index':
                return $this->showMainPage();
            case 'process':
                return $this->processData();
            default:
                return $this->showMainPage();
        }
    }
    
    private function showMainPage() {
        $data = [
            'title' => $this->t('page_title'),
            'content' => $this->t('welcome_message')
        ];
        
        return $this->renderTemplate('main.php', $data);
    }
    
    private function processData() {
        // Datenverarbeitung
        $input = $_POST['data'] ?? '';
        
        // Verarbeitung hier...
        
        return json_encode(['success' => true, 'message' => 'Daten verarbeitet']);
    }
}
?>
```

### 4. Template erstellen

```php
<!-- module/my-custom-module/templates/main.php -->
<div class="module-container">
    <div class="module-header">
        <h1><?php echo $title; ?></h1>
    </div>
    
    <div class="module-content">
        <p><?php echo $content; ?></p>
        
        <form id="custom-form" method="post">
            <div class="form-group">
                <label for="data">Daten eingeben:</label>
                <input type="text" id="data" name="data" class="form-control" required>
            </div>
            
            <button type="submit" class="btn btn-primary">
                Verarbeiten
            </button>
        </form>
        
        <div id="result"></div>
    </div>
</div>

<script>
document.getElementById('custom-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('?module=my-custom-module&action=process', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('result').innerHTML = 
            '<div class="alert alert-success">' + data.message + '</div>';
    })
    .catch(error => {
        document.getElementById('result').innerHTML = 
            '<div class="alert alert-danger">Fehler: ' + error.message + '</div>';
    });
});
</script>
```

### 5. Sprachdateien erstellen

#### Deutsch (de.xml)
```xml
<?xml version="1.0" encoding="UTF-8"?>
<language>
    <module_name>Mein Benutzerdefiniertes Modul</module_name>
    <menu_title>Benutzerdefiniertes Modul</menu_title>
    <page_title>Willkommen im benutzerdefinierten Modul</page_title>
    <welcome_message>Dies ist ein Beispiel für ein benutzerdefiniertes Modul.</welcome_message>
    <form_label>Daten eingeben:</form_label>
    <submit_button>Verarbeiten</submit_button>
    <success_message>Daten erfolgreich verarbeitet!</success_message>
    <error_message>Fehler bei der Datenverarbeitung!</error_message>
</language>
```

#### Englisch (en.xml)
```xml
<?xml version="1.0" encoding="UTF-8"?>
<language>
    <module_name>My Custom Module</module_name>
    <menu_title>Custom Module</menu_title>
    <page_title>Welcome to the custom module</page_title>
    <welcome_message>This is an example of a custom module.</welcome_message>
    <form_label>Enter data:</form_label>
    <submit_button>Process</submit_button>
    <success_message>Data processed successfully!</success_message>
    <error_message>Error processing data!</error_message>
</language>
```

### 6. CSS-Styling

```css
/* module/my-custom-module/assets/style.css */
.module-container {
    padding: 20px;
}

.module-header {
    margin-bottom: 20px;
    border-bottom: 2px solid #007bff;
    padding-bottom: 10px;
}

.module-header h1 {
    color: #007bff;
    margin: 0;
}

.module-content {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 5px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.form-control {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-primary:hover {
    background: #0056b3;
}

.alert {
    padding: 10px;
    margin-top: 15px;
    border-radius: 4px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
```

### 7. JavaScript-Funktionalität

```javascript
// module/my-custom-module/assets/module.js
class MyCustomModule {
    constructor() {
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.loadData();
    }
    
    bindEvents() {
        // Event-Listener für Formulare
        const forms = document.querySelectorAll('[data-module="my-custom-module"] form');
        forms.forEach(form => {
            form.addEventListener('submit', this.handleSubmit.bind(this));
        });
        
        // Event-Listener für Buttons
        const buttons = document.querySelectorAll('[data-module="my-custom-module"] .btn');
        buttons.forEach(button => {
            button.addEventListener('click', this.handleButtonClick.bind(this));
        });
    }
    
    handleSubmit(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const action = e.target.getAttribute('data-action') || 'process';
        
        this.sendRequest(action, formData)
            .then(response => {
                this.showMessage(response.message, 'success');
            })
            .catch(error => {
                this.showMessage(error.message, 'error');
            });
    }
    
    handleButtonClick(e) {
        const action = e.target.getAttribute('data-action');
        if (action) {
            this.sendRequest(action)
                .then(response => {
                    this.showMessage(response.message, 'success');
                })
                .catch(error => {
                    this.showMessage(error.message, 'error');
                });
        }
    }
    
    async sendRequest(action, data = null) {
        const url = `?module=my-custom-module&action=${action}`;
        
        const options = {
            method: data ? 'POST' : 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        };
        
        if (data) {
            options.body = JSON.stringify(Object.fromEntries(data));
        }
        
        const response = await fetch(url, options);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
    }
    
    showMessage(message, type) {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const alertHtml = `<div class="alert ${alertClass}">${message}</div>`;
        
        const resultDiv = document.getElementById('result');
        if (resultDiv) {
            resultDiv.innerHTML = alertHtml;
        }
    }
    
    loadData() {
        // Lade initiale Daten
        this.sendRequest('getData')
            .then(data => {
                this.updateUI(data);
            })
            .catch(error => {
                console.error('Fehler beim Laden der Daten:', error);
            });
    }
    
    updateUI(data) {
        // UI mit geladenen Daten aktualisieren
        const container = document.querySelector('[data-module="my-custom-module"]');
        if (container && data.html) {
            container.innerHTML = data.html;
        }
    }
}

// Modul initialisieren wenn DOM geladen ist
document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('[data-module="my-custom-module"]')) {
        new MyCustomModule();
    }
});
```

## 🔧 Modul-Konfiguration

### Modul aktivieren/deaktivieren

Module können über die Konfiguration aktiviert oder deaktiviert werden:

```php
// config/config.inc.php
class Config {
    // ... andere Konfigurationen ...
    
    // Modul-Konfiguration
    const MODULES = [
        'admin' => true,
        'database' => true,
        'dns' => true,
        'email' => true,
        'endpoints' => true,
        'ispconfig' => true,
        'network' => true,
        'ovh' => true,
        'proxmox' => true,
        'virtual-mac' => true,
        'my-custom-module' => true  // Neues Modul aktivieren
    ];
}
```

### Modul-Berechtigungen

```php
// config/permissions.php
$modulePermissions = [
    'my-custom-module' => [
        'access' => ['admin', 'user'],
        'process' => ['admin'],
        'getData' => ['admin', 'user']
    ]
];
```

## 📊 Modul-Integration

### Modul in das Hauptsystem integrieren

```php
// framework.php oder index.php
require_once 'module/my-custom-module/Module.php';

// Modul registrieren
$modules = [
    'my-custom-module' => new MyCustomModule()
];

// Module initialisieren
foreach ($modules as $module) {
    if ($module->isEnabled()) {
        $module->init();
    }
}
```

### Modul-Routing

```php
// handler.php oder router.php
if (isset($_GET['module'])) {
    $moduleName = $_GET['module'];
    $action = $_GET['action'] ?? 'index';
    
    if (isset($modules[$moduleName])) {
        $module = $modules[$moduleName];
        
        // Berechtigung prüfen
        if ($module->canAccess($action)) {
            $result = $module->handleRequest($action);
            echo $result;
        } else {
            echo "Zugriff verweigert";
        }
    } else {
        echo "Modul nicht gefunden";
    }
}
```

## 🔍 Modul-Debugging

### Debug-Modus aktivieren

```php
// config/config.inc.php
const DEBUG_MODE = true;
const MODULE_DEBUG = true;
```

### Debug-Logging

```php
class MyCustomModule extends ModuleBase {
    public function handleRequest($action) {
        if (Config::DEBUG_MODE) {
            error_log("MyCustomModule: Handling action: $action");
        }
        
        // ... Modul-Logik ...
    }
}
```

## 📝 Best Practices

### 1. Modul-Namenskonventionen

- Verwenden Sie Kleinbuchstaben und Bindestriche für Modul-Namen
- Verwenden Sie beschreibende Namen (z.B. `user-management`, `backup-system`)
- Vermeiden Sie generische Namen wie `module1`, `test`

### 2. Sicherheit

```php
class MyCustomModule extends ModuleBase {
    public function handleRequest($action) {
        // Input validieren
        $action = filter_var($action, FILTER_SANITIZE_STRING);
        
        // CSRF-Schutz
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->validateCSRFToken()) {
                return json_encode(['error' => 'CSRF-Token ungültig']);
            }
        }
        
        // Berechtigung prüfen
        if (!$this->canAccess($action)) {
            return json_encode(['error' => 'Zugriff verweigert']);
        }
        
        // ... Modul-Logik ...
    }
    
    private function validateCSRFToken() {
        $token = $_POST['csrf_token'] ?? '';
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}
```

### 3. Fehlerbehandlung

```php
class MyCustomModule extends ModuleBase {
    public function handleRequest($action) {
        try {
            switch ($action) {
                case 'process':
                    return $this->processData();
                default:
                    return $this->showMainPage();
            }
        } catch (Exception $e) {
            error_log("MyCustomModule Error: " . $e->getMessage());
            return json_encode([
                'error' => 'Ein Fehler ist aufgetreten',
                'debug' => Config::DEBUG_MODE ? $e->getMessage() : null
            ]);
        }
    }
}
```

### 4. Performance

```php
class MyCustomModule extends ModuleBase {
    private $cache = [];
    
    public function getData() {
        $cacheKey = 'my_module_data';
        
        // Cache prüfen
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        // Daten laden
        $data = $this->loadDataFromDatabase();
        
        // Cache setzen
        $this->cache[$cacheKey] = $data;
        
        return $data;
    }
}
```

## 🔗 Nützliche Links

- [Framework Komponenten](Framework-Komponenten)
- [Beispiele & Tutorials](Beispiele-Tutorials)
- [Installation & Setup](Installation-Setup)

## 💡 Tipps

1. **Modularität**: Halten Sie Module klein und fokussiert
2. **Wiederverwendbarkeit**: Verwenden Sie gemeinsame Komponenten
3. **Dokumentation**: Dokumentieren Sie Ihre Module gut
4. **Testing**: Testen Sie Module gründlich
5. **Updates**: Halten Sie Module aktuell
6. **Sicherheit**: Implementieren Sie immer Sicherheitsmaßnahmen
7. **Performance**: Optimieren Sie Module für bessere Performance 