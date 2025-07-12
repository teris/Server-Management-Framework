# �� ServiceManager API Dokumentation

## 📖 Übersicht
Der `ServiceManager` ist die zentrale Klasse für alle API-Operationen. Hier sind alle verfügbaren Methoden:

---

## 🖥️ **PROXMOX API**

### **📖 GET Operations**

#### VMs abrufen
```php
// Alle VMs von allen Nodes
$vms = $serviceManager->getProxmoxVMs();
// Returns: Array of VM objects

// Einzelne VM Details (direkt über ProxmoxGet)
$proxmoxGet = new ProxmoxGet();
$vm = $proxmoxGet->getVM('pve', '100');
// Returns: VM object or null

$vmStatus = $proxmoxGet->getVMStatus('pve', '100');
// Returns: Array with status info

$vmConfig = $proxmoxGet->getVMConfig('pve', '100');
// Returns: Array with VM configuration
```

#### Nodes & Infrastructure
```php
$proxmoxGet = new ProxmoxGet();

// Alle Proxmox Nodes
$nodes = $proxmoxGet->getNodes();
// Returns: Array of nodes

// Storages eines Nodes
$storages = $proxmoxGet->getStorages('pve');
// Returns: Array of storages

// Netzwerke eines Nodes
$networks = $proxmoxGet->getNetworks('pve');
// Returns: Array of networks
```

### **✏️ POST Operations**

#### VM Management
```php
// VM erstellen
$vmData = [
    'vmid' => '101',
    'name' => 'test-server',
    'node' => 'pve',
    'memory' => '4096',
    'cores' => '2',
    'disk' => '20',
    'storage' => 'local-lvm',
    'bridge' => 'vmbr0',
    'mac' => 'aa:bb:cc:dd:ee:ff', // optional
    'iso' => 'local:iso/ubuntu-22.04.iso'
];
$result = $serviceManager->createProxmoxVM($vmData);
// Returns: API response array or false

// VM steuern (start, stop, reset, suspend, resume)
$result = $serviceManager->controlProxmoxVM('pve', '100', 'start');
$result = $serviceManager->controlProxmoxVM('pve', '100', 'stop');
$result = $serviceManager->controlProxmoxVM('pve', '100', 'reset');
$result = $serviceManager->controlProxmoxVM('pve', '100', 'suspend');
$result = $serviceManager->controlProxmoxVM('pve', '100', 'resume');
// Returns: API response array or false

// VM löschen
$result = $serviceManager->deleteProxmoxVM('pve', '100');
// Returns: API response array or false
```

#### Erweiterte VM Operations (direkt über ProxmoxPost)
```php
$proxmoxPost = new ProxmoxPost();

// VM bearbeiten
$updateData = [
    'memory' => '8192',
    'cores' => '4'
];
$result = $proxmoxPost->editVM('pve', '100', $updateData);

// VM klonen
$result = $proxmoxPost->cloneVM('pve', '100', '102', 'clone-of-vm-100');
// Parameters: node, source_vmid, new_vmid, new_name
```

---

## 🌐 **ISPCONFIG API**

### **📖 GET Operations**

#### Websites
```php
// Alle aktiven Websites
$websites = $serviceManager->getISPConfigWebsites();
// Returns: Array of Website objects

// Erweiterte Abfragen (direkt über ISPConfigGet)
$ispconfigGet = new ISPConfigGet();

// Website mit Filter
$websites = $ispconfigGet->getWebsites(['domain' => 'example.com']);

// Einzelne Website
$website = $ispconfigGet->getWebsite('123'); // domain_id

// Alle Clients
$clients = $ispconfigGet->getClients();

// Server Konfiguration
$serverConfig = $ispconfigGet->getServerConfig();
```

#### Datenbanken
```php
// Alle aktiven Datenbanken
$databases = $serviceManager->getISPConfigDatabases();
// Returns: Array of Database_Entry objects

// Einzelne Datenbank (direkt über ISPConfigGet)
$ispconfigGet = new ISPConfigGet();
$database = $ispconfigGet->getDatabase('456'); // database_id
```

#### E-Mail Accounts
```php
// Alle aktiven E-Mail Accounts
$emails = $serviceManager->getISPConfigEmails();
// Returns: Array of EmailAccount objects

// Einzelner E-Mail Account (direkt über ISPConfigGet)
$ispconfigGet = new ISPConfigGet();
$email = $ispconfigGet->getEmailAccount('789'); // mailuser_id
```

### **✏️ POST Operations**

#### Website Management
```php
// Website erstellen
$websiteData = [
    'domain' => 'example.com',
    'ip' => '192.168.1.100',
    'user' => 'web1',
    'group' => 'client1',
    'quota' => 1000,
    'traffic' => 10000
];
$result = $serviceManager->createISPConfigWebsite($websiteData);
// Returns: API response or false

// Website löschen
$result = $serviceManager->deleteISPConfigWebsite('123'); // domain_id
// Returns: API response or false

// Website bearbeiten (direkt über ISPConfigPost)
$ispconfigPost = new ISPConfigPost();
$updateData = ['quota' => 2000];
$result = $ispconfigPost->editWebsite('123', $updateData);
```

#### Datenbank Management
```php
// Datenbank erstellen
$dbData = [
    'name' => 'my_database',
    'user' => 'db_user',
    'password' => 'secure_password'
];
$result = $serviceManager->createISPConfigDatabase($dbData);
// Returns: API response or false

// Datenbank löschen
$result = $serviceManager->deleteISPConfigDatabase('456'); // database_id
// Returns: API response or false

// Datenbank bearbeiten (direkt über ISPConfigPost)
$ispconfigPost = new ISPConfigPost();
$updateData = ['database_password' => 'new_password'];
$result = $ispconfigPost->editDatabase('456', $updateData);
```

#### E-Mail Management
```php
// E-Mail Account erstellen
$emailData = [
    'email' => 'user@example.com',
    'login' => 'user',
    'password' => 'secure_password',
    'name' => 'Max Mustermann',
    'domain' => 'example.com',
    'user' => 'user', // Login ohne @domain
    'quota' => 1000
];
$result = $serviceManager->createISPConfigEmail($emailData);
// Returns: API response or false

// E-Mail Account löschen
$result = $serviceManager->deleteISPConfigEmail('789'); // mailuser_id
// Returns: API response or false

// E-Mail Account bearbeiten (direkt über ISPConfigPost)
$ispconfigPost = new ISPConfigPost();
$updateData = ['quota' => 2000];
$result = $ispconfigPost->editEmailAccount('789', $updateData);
```

---

## 🔗 **OVH API**

### **📖 GET Operations**

#### Domains
```php
// Alle Domains
$domains = $serviceManager->getOVHDomains();
// Returns: Array of Domain objects

// Erweiterte Domain-Abfragen (direkt über OVHGet)
$ovhGet = new OVHGet();

// Einzelne Domain
$domain = $ovhGet->getDomain('example.com');

// Domain Zone Info
$zone = $ovhGet->getDomainZone('example.com');

// DNS Records einer Domain
$records = $ovhGet->getDomainZoneRecords('example.com');
```

#### VPS
```php
// Alle VPS
$vpsList = $serviceManager->getOVHVPS();
// Returns: Array of VPS objects

// VPS MAC-Adresse abrufen (vereinfacht)
$macInfo = $serviceManager->getOVHVPSMacAddress('vpsXXXXX.ovh.net');
```

#### Dedicated Servers
```php
// Alle Dedicated Server
$dedicatedServers = $serviceManager->getOVHDedicatedServers();
// Returns: Array of Dedicated Server objects

// Einzelner Dedicated Server
$server = $ovhGet->getDedicatedServer('nsXXXXX.ovh.net');
```

### **✏️ POST Operations**

#### Domain Management
```php
// Domain bestellen
$result = $serviceManager->orderOVHDomain('example.com', 1);
// Parameters: domain_name, duration_in_years
// Returns: API response or false

// Domain löschen
$result = $serviceManager->deleteOVHDomain('example.com');
// Returns: API response or false
```

#### VPS Management
```php
// VPS bestellen
$vpsData = [
    'model' => 'vps-ssd-1',
    'datacenter' => 'gra1',
    'duration' => 'P1M' // ISO 8601 duration
];
$result = $serviceManager->orderOVHVPS($vpsData);
// Returns: API response or false

// VPS löschen
$result = $serviceManager->deleteOVHVPS('vpsXXXXX.ovh.net');
// Returns: API response or false
```

---

## 🔐 **Authentication & Testing**

### API-Verbindungen testen
```php
// Alle APIs testen
$authHandler = new AuthHandler();
$results = $authHandler->testAllAPIs();

// Einzelne APIs testen
$proxmoxStatus = $authHandler->testProxmox();
$ispconfigStatus = $authHandler->testISPConfig();
$ovhStatus = $authHandler->testOVH();
```

### Error Handling
```php
try {
    $result = $serviceManager->createProxmoxVM($vmData);
    if ($result === false) {
        throw new Exception('VM creation failed');
    }
} catch (Exception $e) {
    error_log('API Error: ' . $e->getMessage());
    // Handle error appropriately
}
```

---

## 📊 **Admin Dashboard**

### Dashboard-Funktionen
```php
// Dashboard initialisieren
$adminCore = new AdminCore();

// Ressourcen abrufen
$resources = $adminCore->getAllResources();

// Statistiken abrufen
$stats = $adminCore->getStatistics();

// Activity Log abrufen
$activities = $adminCore->getActivityLog();
```

### Real-time Updates
```javascript
// JavaScript für Real-time Updates
setInterval(function() {
    AjaxHandler.heartbeat();
}, 30000); // Alle 30 Sekunden
```

---

## 🛠️ **Debug & Development**

### Debug-Modus aktivieren
```php
// In config/config.inc.php
define('DEBUG_MODE', true);
define('LOG_LEVEL', 'DEBUG');
```

### Debug-Tools verwenden
```bash
# Debug-Interface öffnen
php debug.php

# Spezifische Debug-Tests
php debug/ispconfig_debug.php
php debug/ovh_failover_mac.php
php debug/soap_test.php
```

### Logs abrufen
```php
// Activity Log
$activities = $adminCore->getActivityLog();

// Error Log
$errors = $adminCore->getErrorLog();

// API Log
$apiLogs = $adminCore->getAPILog();
```

---

## 📝 **Beispiele**

### Vollständiges Beispiel: VM erstellen und konfigurieren
```php
<?php
require_once 'framework.php';

try {
    $serviceManager = new ServiceManager();
    
    // 1. VM erstellen
    $vmData = [
        'vmid' => '101',
        'name' => 'web-server',
        'node' => 'pve',
        'memory' => '4096',
        'cores' => '2',
        'disk' => '50',
        'storage' => 'local-lvm',
        'bridge' => 'vmbr0',
        'iso' => 'local:iso/ubuntu-22.04.iso'
    ];
    
    $result = $serviceManager->createProxmoxVM($vmData);
    if ($result) {
        echo "VM erfolgreich erstellt\n";
        
        // 2. VM starten
        $serviceManager->controlProxmoxVM('pve', '101', 'start');
        echo "VM gestartet\n";
        
        // 3. Website für die VM erstellen
        $websiteData = [
            'domain' => 'web-server.local',
            'ip' => '192.168.1.101',
            'user' => 'web1',
            'group' => 'client1',
            'quota' => 1000,
            'traffic' => 10000
        ];
        
        $websiteResult = $serviceManager->createISPConfigWebsite($websiteData);
        if ($websiteResult) {
            echo "Website erfolgreich erstellt\n";
        }
    }
    
} catch (Exception $e) {
    error_log('Error: ' . $e->getMessage());
    echo "Fehler: " . $e->getMessage() . "\n";
}
?>
```

### Beispiel: Backup-Strategie
```php
<?php
// Backup aller VMs
$vms = $serviceManager->getProxmoxVMs();
foreach ($vms as $vm) {
    if ($vm->status === 'running') {
        // VM stoppen vor Backup
        $serviceManager->controlProxmoxVM($vm->node, $vm->vmid, 'stop');
        sleep(10); // Warten bis VM gestoppt ist
    }
    
    // Backup erstellen
    $backupResult = $proxmoxPost->createBackup($vm->node, $vm->vmid);
    
    if ($vm->status === 'running') {
        // VM wieder starten
        $serviceManager->controlProxmoxVM($vm->node, $vm->vmid, 'start');
    }
}
?>
```

---

## 🔧 **Konfiguration**

### Erweiterte Konfiguration
```php
// In config/config.inc.php

// Timeout-Einstellungen
define('API_TIMEOUT', 30);
define('CURL_TIMEOUT', 60);

// Retry-Einstellungen
define('MAX_RETRIES', 3);
define('RETRY_DELAY', 5);

// Logging-Einstellungen
define('LOG_ENABLED', true);
define('LOG_FILE', 'logs/api.log');
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR

// Cache-Einstellungen
define('CACHE_ENABLED', true);
define('CACHE_DURATION', 300); // 5 Minuten
```

---

## 📚 **Weitere Ressourcen**

- **[README.md](README.md)** - Projektübersicht und Installation
- **[CONTRIBUTING.md](CONTRIBUTING.md)** - Richtlinien für Beiträge
- **[BOOTSTRAP_MIGRATION.md](BOOTSTRAP_MIGRATION.md)** - UI-Migration Details
- **[GitHub Repository](https://github.com/teris/server-management-framework)** - Source Code

---

**Viel Erfolg beim Verwenden des Server Management Frameworks! 🚀**
