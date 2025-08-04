# Framework Komponenten

Diese Seite beschreibt die wichtigsten Komponenten des Server Management Frameworks.

## 🏗️ Architektur-Übersicht

Das Framework basiert auf einer modularen Architektur mit folgenden Hauptkomponenten:

```
Framework.php
├── Database (Singleton)
├── DataMapper (Statisch)
├── BaseAPI (Abstrakt)
├── ServiceManager (Zentral)
└── API-Implementierungen
    ├── ProxmoxGet/ProxmoxPost
    ├── ISPConfigGet/ISPConfigPost
    ├── OVHGet/OVHPost
    └── OGPGet/OGPPost
```

## 🗄️ Database Klasse

Singleton-Pattern für Datenbankverbindungen mit PDO.

### Konstruktor
```php
private function __construct()
```
Erstellt eine neue Datenbankverbindung mit den Konfigurationswerten.

### getInstance()
```php
public static function getInstance()
```
Gibt die einzige Instanz der Database-Klasse zurück.

**Beispiel:**
```php
$db = Database::getInstance();
$connection = $db->getConnection();
```

### getConnection()
```php
public function getConnection()
```
Gibt die PDO-Verbindung zurück.

### logAction()
```php
public function logAction($action, $details, $status)
```
Loggt eine Aktion in der activity_log Tabelle.

**Parameter:**
- `$action` (string) - Name der Aktion
- `$details` (string) - Details der Aktion
- `$status` (string) - Status ('success', 'error', 'warning')

**Beispiel:**
```php
$db = Database::getInstance();
$db->logAction(
    'VM erstellt',
    'Neue VM "webserver" mit ID 101 erstellt',
    'success'
);
```

### getActivityLog()
```php
public function getActivityLog($limit = 50, $offset = 0)
```
Holt die letzten Aktivitäts-Logs aus der Datenbank.

**Parameter:**
- `$limit` (int) - Anzahl der Log-Einträge (Standard: 50, Max: 1000)
- `$offset` (int) - Offset für Paginierung (Standard: 0)

**Beispiel:**
```php
$db = Database::getInstance();
$logs = $db->getActivityLog(10, 0);
foreach ($logs as $log) {
    echo "Aktion: {$log['action']} - Status: {$log['status']}\n";
}
```

### clearActivityLogs()
```php
public function clearActivityLogs()
```
Löscht alle Aktivitäts-Logs.

## 📊 DataMapper Klasse

Statische Klasse zum Mapping von API-Daten zu Objekten.

### mapToVM()
```php
public static function mapToVM($data)
```
Mappt Proxmox VM-Daten zu einem VM-Objekt.

**Parameter:**
- `$data` (array) - Rohdaten von der Proxmox API

**Rückgabe:** VM-Objekt

**Beispiel:**
```php
$vmData = [
    'vmid' => 100,
    'name' => 'webserver',
    'status' => 'running',
    'cpu' => 2,
    'maxmem' => 2048
];

$vm = DataMapper::mapToVM($vmData);
echo "VM: {$vm->name} (ID: {$vm->vmid})";
```

### mapToVirtualMac()
```php
public static function mapToVirtualMac($data, $serviceName = null, $macAddress = null)
```
Mappt OVH Virtual MAC-Daten zu einem VirtualMac-Objekt.

**Parameter:**
- `$data` (array) - Rohdaten von der OVH API
- `$serviceName` (string) - Name des OVH Services
- `$macAddress` (string) - MAC-Adresse

**Rückgabe:** VirtualMac-Objekt

### mapToWebsite()
```php
public static function mapToWebsite($data)
```
Mappt ISPConfig Website-Daten zu einem Website-Objekt.

**Parameter:**
- `$data` (array) - Rohdaten von der ISPConfig API

**Rückgabe:** Website-Objekt

## 🔌 BaseAPI Klasse

Abstrakte Basisklasse für alle API-Implementierungen.

### authenticate()
```php
abstract protected function authenticate()
```
Muss von jeder API-Klasse implementiert werden. Handhabt die Authentifizierung.

### makeRequest()
```php
abstract protected function makeRequest($method, $url, $data = null)
```
Muss von jeder API-Klasse implementiert werden. Führt HTTP-Requests aus.

**Parameter:**
- `$method` (string) - HTTP-Methode ('GET', 'POST', 'PUT', 'DELETE')
- `$url` (string) - API-Endpunkt
- `$data` (array) - Request-Daten (optional)

### logRequest()
```php
public function logRequest($endpoint, $method, $success)
```
Loggt API-Requests in der Datenbank.

**Parameter:**
- `$endpoint` (string) - API-Endpunkt
- `$method` (string) - HTTP-Methode
- `$success` (bool) - Erfolg des Requests

## 🎛️ ServiceManager Klasse

Zentrale Verwaltungsklasse für alle APIs.

### __construct()
```php
public function __construct()
```
Initialisiert alle aktivierten APIs basierend auf der Konfiguration.

### checkAPIEnabled()
```php
private function checkAPIEnabled($apiName)
```
Prüft ob eine API aktiviert ist.

**Parameter:**
- `$apiName` (string) - Name der API ('proxmox', 'ovh', 'ispconfig', 'ogp')

**Rückgabe:**
- `true` - API ist verfügbar und funktionsfähig
- `array` - Fehler-Objekt mit 'message' und 'solution'

**Beispiel:**
```php
$serviceManager = new ServiceManager();
$apiCheck = $serviceManager->checkAPIEnabled('proxmox');
if ($apiCheck !== true) {
    echo "Proxmox API Fehler: " . $apiCheck['message'] . "\n";
    echo "Lösung: " . $apiCheck['solution'] . "\n";
}
```

### ProxmoxAPI()
```php
public function ProxmoxAPI($type, $url, $code = null)
```
Generische Proxmox API-Funktion für direkten Zugriff.

**Parameter:**
- `$type` (string) - HTTP-Methode ('get', 'post', 'put', 'delete')
- `$url` (string) - API-Endpunkt-Pfad
- `$code` (array) - Zusätzliche Parameter oder Daten (optional)

**Beispiel:**
```php
$serviceManager = new ServiceManager();

// Alle Nodes abrufen
$nodes = $serviceManager->ProxmoxAPI('get', '/nodes');

// VM starten
$result = $serviceManager->ProxmoxAPI('post', '/nodes/pve/qemu/100/status/start');

// VM-Konfiguration ändern
$configData = ['memory' => 4096, 'cores' => 4];
$result = $serviceManager->ProxmoxAPI('put', '/nodes/pve/qemu/100/config', $configData);
```

### OvhAPI()
```php
public function OvhAPI($type, $url, $code = null)
```
Generische OVH API-Funktion für direkten Zugriff.

**Beispiel:**
```php
$serviceManager = new ServiceManager();

// Alle Domains abrufen
$domains = $serviceManager->OvhAPI('get', '/domain');

// DNS-Record erstellen
$dnsData = [
    'fieldType' => 'A',
    'target' => '192.168.1.100',
    'ttl' => 3600
];
$result = $serviceManager->OvhAPI('post', '/domain/zone/example.com/record', $dnsData);
```

### IspconfigAPI()
```php
public function IspconfigAPI($type, $url, $code = null)
```
Generische ISPConfig API-Funktion für direkten Zugriff.

**Beispiel:**
```php
$serviceManager = new ServiceManager();

// Website erstellen
$websiteData = [
    'server_id' => 1,
    'ip_address' => '192.168.1.100',
    'domain' => 'example.com',
    'type' => 'vhost',
    'active' => 'y'
];
$result = $serviceManager->IspconfigAPI('post', 'sites_web_domain', $websiteData);
```

### OGPAPI()
```php
public function OGPAPI($type, $url, $code = null)
```
Generische OGP API-Funktion für direkten Zugriff.

**Beispiel:**
```php
$serviceManager = new ServiceManager();

// Server-Status abrufen
$serverStatus = $serviceManager->OGPAPI('post', 'server/status', ['remote_server_id' => 1]);
```

## 📋 Datenmodelle

### VM Klasse
```php
class VM {
    public $vmid;           // VM ID
    public $name;           // VM Name
    public $node;           // Node Name
    public $status;         // VM Status
    public $cores;          // CPU Cores
    public $memory;         // Memory in MB
    public $disk;           // Disk size in GB
    public $ip_address;     // IP Address
    public $mac_address;    // MAC Address
    public $uptime;         // Uptime
    public $cpu_usage;      // CPU Usage %
    public $memory_usage;   // Memory Usage %
}
```

### VirtualMac Klasse
```php
class VirtualMac {
    public $macAddress;     // MAC Address
    public $serviceName;    // OVH Service Name
    public $ipAddress;      // IP Address
    public $virtualNetworkInterface; // Network Interface
    public $type;           // MAC Type
    public $reverse;        // Reverse DNS
    public $ips;            // Associated IPs
    public $reverseEntries; // Reverse DNS Entries
}
```

### Website Klasse
```php
class Website {
    public $domain_id;      // Domain ID
    public $domain;         // Domain Name
    public $ip_address;     // IP Address
    public $system_user;    // System User
    public $system_group;   // System Group
    public $active;         // Active Status
    public $hd_quota;       // Disk Quota
    public $traffic_quota;  // Traffic Quota
    public $document_root;  // Document Root
    public $ssl_enabled;    // SSL Status
}
```

## 🔧 Modul-Funktionen

### getAllModules()
```php
function getAllModules()
```
Gibt alle verfügbaren Module zurück.

**Rückgabe:** Array mit Modul-Informationen

### getEnabledModules()
```php
function getEnabledModules()
```
Gibt nur die aktivierten Module zurück.

**Rückgabe:** Array mit aktivierten Modulen

### canAccessModule()
```php
function canAccessModule($module_key, $user_role)
```
Prüft ob ein Benutzer auf ein Modul zugreifen darf.

**Parameter:**
- `$module_key` (string) - Modul-Schlüssel
- `$user_role` (string) - Benutzer-Rolle

**Rückgabe:** bool

## 🔍 Fehlerbehandlung

### API-Status-Überprüfung
```php
$serviceManager = new ServiceManager();

// API-Status prüfen
$apiCheck = $serviceManager->checkAPIEnabled('proxmox');
if ($apiCheck !== true) {
    echo "API Fehler: " . $apiCheck['message'] . "\n";
    echo "Lösung: " . $apiCheck['solution'] . "\n";
    exit;
}
```

### Try-Catch Fehlerbehandlung
```php
try {
    $serviceManager = new ServiceManager();
    $vms = $serviceManager->getProxmoxVMs();
    
    if (is_array($vms)) {
        foreach ($vms as $vm) {
            echo "VM: {$vm->name}\n";
        }
    } else {
        echo "Fehler beim Abrufen der VMs: " . $vms['message'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    error_log("Framework Error: " . $e->getMessage());
}
```

### Logging von Fehlern
```php
$db = Database::getInstance();

try {
    // API-Operation
    $result = $serviceManager->createProxmoxVM($vmData);
    
    if ($result) {
        $db->logAction('VM erstellt', 'VM erfolgreich erstellt', 'success');
    } else {
        $db->logAction('VM Fehler', 'Fehler beim Erstellen der VM', 'error');
    }
    
} catch (Exception $e) {
    $db->logAction('Exception', $e->getMessage(), 'error');
}
```

## 📊 Performance-Optimierung

### Generische API-Funktionen verwenden
```php
// Besser: Generische API-Funktion
$nodes = $serviceManager->ProxmoxAPI('get', '/nodes');

// Schlechter: Spezifische Wrapper-Funktion
$proxmox = new ProxmoxGet();
$nodes = $proxmox->getNodes();
```

### Batch-Operationen
```php
// Mehrere VMs in einem Batch erstellen
$vmDataList = [
    ['vmid' => 101, 'name' => 'vm1', ...],
    ['vmid' => 102, 'name' => 'vm2', ...],
    ['vmid' => 103, 'name' => 'vm3', ...]
];

foreach ($vmDataList as $vmData) {
    $serviceManager->createProxmoxVM($vmData);
}
```

### Caching implementieren
```php
// Einfaches Caching für API-Responses
$cacheFile = 'cache/proxmox_nodes.json';
$cacheTime = 300; // 5 Minuten

if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
    $nodes = json_decode(file_get_contents($cacheFile), true);
} else {
    $nodes = $serviceManager->ProxmoxAPI('get', '/nodes');
    file_put_contents($cacheFile, json_encode($nodes));
}
```

## 🔒 Sicherheitsaspekte

### Konfigurationsdatei schützen
```php
// config/config.inc.php sollte nicht öffentlich zugänglich sein
// Apache .htaccess:
<Files "config.inc.php">
    Order allow,deny
    Deny from all
</Files>

// Nginx:
location ~ ^/config/.*\.php$ {
    deny all;
}
```

### API-Zugangsdaten sichern
```php
// Verwenden Sie starke Passwörter
// Verwenden Sie HTTPS für alle API-Verbindungen
// Rotieren Sie API-Tokens regelmäßig
// Verwenden Sie IP-Whitelisting wenn möglich
```

### Input-Validierung
```php
// Validiere VM-Daten vor dem Erstellen
function validateVMData($vmData) {
    $errors = [];
    
    if (!isset($vmData['vmid']) || $vmData['vmid'] < 100 || $vmData['vmid'] > 999999) {
        $errors[] = 'Ungültige VM ID';
    }
    
    if (!isset($vmData['name']) || strlen($vmData['name']) < 1) {
        $errors[] = 'VM Name ist erforderlich';
    }
    
    if (!isset($vmData['memory']) || $vmData['memory'] < 128) {
        $errors[] = 'Memory muss mindestens 128 MB sein';
    }
    
    return $errors;
}

$vmData = ['vmid' => 101, 'name' => 'test-vm', 'memory' => 1024];
$errors = validateVMData($vmData);

if (empty($errors)) {
    $serviceManager->createProxmoxVM($vmData);
} else {
    foreach ($errors as $error) {
        echo "Fehler: {$error}\n";
    }
}
```

## 🔗 Nützliche Links

- [Installation & Setup](Installation-Setup)
- [Proxmox API](Proxmox-API)
- [ISPConfig API](ISPConfig-API)
- [OVH API](OVH-API)
- [OGP API](OGP-API)
- [Beispiele & Tutorials](Beispiele-Tutorials)
- [Modul-System](Modul-System)

## 💡 Best Practices

1. **Singleton-Pattern**: Verwenden Sie Database::getInstance() für Datenbankverbindungen
2. **Fehlerbehandlung**: Implementieren Sie umfassende Fehlerbehandlung
3. **Logging**: Protokollieren Sie alle wichtigen Aktionen
4. **Validierung**: Validiere alle Eingabedaten
5. **Sicherheit**: Verwenden Sie sichere Verbindungen und Zugangsdaten
6. **Performance**: Verwenden Sie generische API-Funktionen für bessere Performance
7. **Modularität**: Erweitern Sie das Framework durch eigene Module 