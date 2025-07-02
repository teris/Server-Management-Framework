# 🚀 ServiceManager API Documentation

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
// Returns: Array with 'ip' and 'mac' or null

// Erweiterte VPS-Abfragen (direkt über OVHGet)
$ovhGet = new OVHGet();

// Einzelner VPS
$vps = $ovhGet->getVPS('vpsXXXXX.ovh.net');

// VPS IP-Adressen
$ips = $ovhGet->getVPSIPs('vpsXXXXX.ovh.net');

// Details einer IP
$ipDetails = $ovhGet->getVPSIPDetails('vpsXXXXX.ovh.net', '1.2.3.4');
```

#### Dedicated Server
```php
$ovhGet = new OVHGet();

// Alle Dedicated Server
$servers = $ovhGet->getDedicatedServers();

// Einzelner Dedicated Server
$server = $ovhGet->getDedicatedServer('ns12345.ip-1-2-3.eu');
```

### **✏️ POST Operations**

#### Domain Management
```php
// Domain bestellen
$result = $serviceManager->orderOVHDomain('example.com', 1); // domain, duration in years
// Returns: API response or false

// Erweiterte Domain-Operationen (direkt über OVHPost)
$ovhPost = new OVHPost();

// Domain bearbeiten
$domainData = ['autoRenew' => true];
$result = $ovhPost->editDomain('example.com', $domainData);

// Domain löschen
$result = $ovhPost->deleteDomain('example.com');
```

#### DNS Management
```php
$ovhPost = new OVHPost();

// DNS Record erstellen
$recordData = [
    'fieldType' => 'A',
    'subDomain' => 'www',
    'target' => '1.2.3.4',
    'ttl' => 3600
];
$result = $ovhPost->createDNSRecord('example.com', $recordData);

// DNS Record bearbeiten
$updateData = ['target' => '5.6.7.8'];
$result = $ovhPost->editDNSRecord('example.com', '12345', $updateData);

// DNS Record löschen
$result = $ovhPost->deleteDNSRecord('example.com', '12345');

// DNS Zone aktualisieren
$result = $ovhPost->refreshDNSZone('example.com');
```

#### VPS Management
```php
$ovhPost = new OVHPost();

// VPS neustarten
$result = $ovhPost->rebootVPS('vpsXXXXX.ovh.net');

// VPS stoppen
$result = $ovhPost->stopVPS('vpsXXXXX.ovh.net');

// VPS starten
$result = $ovhPost->startVPS('vpsXXXXX.ovh.net');
```

---

## 🗃️ **DATABASE OPERATIONS**

### **Activity Log**
```php
$db = Database::getInstance();

// Aktion loggen
$db->logAction('VM erstellt', json_encode($vmData), 'success');

// Activity Log abrufen
$logs = $db->getActivityLog(50); // Limit: 50 Einträge
```

---

## 🔄 **DATA MAPPING**

### **JSON zu PHP Objekten konvertieren**
```php
// Proxmox VM Response zu VM Object
$vmObject = DataMapper::mapToVM($proxmoxResponse);

// ISPConfig Website Response zu Website Object
$websiteObject = DataMapper::mapToWebsite($ispconfigResponse);

// OVH Domain Response zu Domain Object
$domainObject = DataMapper::mapToDomain($ovhResponse);

// Objekt zu Array
$vmArray = $vmObject->toArray();
```

---

## 🧪 **TESTING & DEBUGGING**

### **Einzelne API-Klassen direkt nutzen**
```php
// Direkte Proxmox API Nutzung
$proxmoxGet = new ProxmoxGet();
$proxmoxPost = new ProxmoxPost();

// Direkte ISPConfig API Nutzung
$ispconfigGet = new ISPConfigGet();
$ispconfigPost = new ISPConfigPost();

// Direkte OVH API Nutzung
$ovhGet = new OVHGet();
$ovhPost = new OVHPost();
```

### **Error Handling**
```php
try {
    $result = $serviceManager->createProxmoxVM($vmData);
    if ($result === false) {
        echo "Fehler beim Erstellen der VM";
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
}
```

---

## 📊 **RETURN VALUES**

### **Erfolgreiche Responses:**
- **GET Operations:** Array of Objects oder einzelnes Object
- **POST Operations:** API Response Array oder Boolean

### **Fehler-Responses:**
- **`false`** bei API-Fehlern
- **`null`** wenn Ressource nicht gefunden
- **`[]`** (leeres Array) wenn keine Ergebnisse

### **Object Properties:**
```php
// VM Object
$vm->vmid, $vm->name, $vm->node, $vm->status, $vm->cores, $vm->memory

// Website Object  
$website->domain, $website->ip_address, $website->system_user, $website->active

// Database Object
$database->database_name, $database->database_user, $database->database_type

// Email Object
$email->email, $email->login, $email->name, $email->quota, $email->active

// Domain Object
$domain->domain, $domain->expiration, $domain->autoRenew, $domain->nameServers

// VPS Object
$vps->name, $vps->state, $vps->ips, $vps->mac_addresses, $vps->cluster
```

---

## ⚡ **QUICK REFERENCE**

### **Häufigste Operationen:**
```php
$serviceManager = new ServiceManager();

// VMs auflisten und steuern
$vms = $serviceManager->getProxmoxVMs();
$serviceManager->controlProxmoxVM('pve', '100', 'start');

// Websites verwalten
$websites = $serviceManager->getISPConfigWebsites();
$serviceManager->createISPConfigWebsite($websiteData);

// Domains verwalten
$domains = $serviceManager->getOVHDomains();
$serviceManager->orderOVHDomain('example.com', 1);

// VPS MAC abrufen
$macInfo = $serviceManager->getOVHVPSMacAddress('vpsXXXXX.ovh.net');
```