# OVH API Integration

Die OVH API Integration ermöglicht die Verwaltung von Cloud-Services, Domains und Virtual MAC-Adressen über die OVH Cloud API.

## 🔧 Konfiguration

### 1. OVH API-Zugangsdaten einrichten

Fügen Sie folgende Konfiguration in `config/config.inc.php` hinzu:

```php
// ===== OVH KONFIGURATION =====
const OVH_USEING = true;
const OVH_APPLICATION_KEY = 'your_ovh_app_key';
const OVH_APPLICATION_SECRET = 'your_ovh_app_secret';
const OVH_CONSUMER_KEY = 'your_ovh_consumer_key';
const OVH_ENDPOINT = 'https://eu.api.ovh.com/1.0';  // eu, ca, us
```

### 2. OVH API-Anwendung erstellen

1. Gehen Sie zu [OVH API Keys](https://api.ovh.com/createToken/)
2. Erstellen Sie eine neue API-Anwendung
3. Notieren Sie sich Application Key, Secret und Consumer Key
4. Wählen Sie den richtigen Endpoint (eu, ca, us)

## 📚 API-Klassen

### OVHGet Klasse

Lese-Operationen für OVH.

#### getDomains()
Holt alle Domains.

```php
$ovh = new OVHGet();
$domains = $ovh->getDomains();

foreach ($domains as $domain) {
    echo "Domain: {$domain}\n";
}
```

#### getVPSList()
Holt alle VPS-Instanzen.

```php
$ovh = new OVHGet();
$vpsList = $ovh->getVPSList();

foreach ($vpsList as $vps) {
    echo "VPS: {$vps['name']} - Status: {$vps['state']}\n";
    echo "IP: {$vps['ip']}\n";
    echo "---\n";
}
```

#### getVirtualMacAddresses($serviceName)
Holt alle Virtual MAC-Adressen für einen Service.

```php
$ovh = new OVHGet();
$serviceName = 'mein-server';
$virtualMacs = $ovh->getVirtualMacAddresses($serviceName);

foreach ($virtualMacs as $mac) {
    echo "MAC: {$mac->macAddress}\n";
    echo "Service: {$mac->serviceName}\n";
    echo "Interface: {$mac->virtualNetworkInterface}\n";
    echo "Type: {$mac->type}\n";
    
    if (!empty($mac->ips)) {
        echo "Zugewiesene IPs:\n";
        foreach ($mac->ips as $ip) {
            echo "  - {$ip['ipAddress']}\n";
        }
    }
    echo "---\n";
}
```

#### getDedicatedServers()
Holt alle Dedicated Server.

```php
$ovh = new OVHGet();
$servers = $ovh->getDedicatedServers();

foreach ($servers as $server) {
    echo "Server: {$server['name']}\n";
    echo "Status: {$server['state']}\n";
    echo "---\n";
}
```

### OVHPost Klasse

Schreib-Operationen für OVH.

#### createVirtualMac($serviceName, $virtualNetworkInterface, $type = 'ovh')
Erstellt eine neue Virtual MAC-Adresse.

**Parameter:**
- `$serviceName` (string) - Name des OVH Dedicated Servers
- `$virtualNetworkInterface` (string) - Netzwerk-Interface (z.B. 'eth0')
- `$type` (string) - MAC-Typ (Standard: 'ovh')

```php
$ovh = new OVHPost();
$serviceName = 'mein-server';
$result = $ovh->createVirtualMac($serviceName, 'eth0', 'ovh');

if ($result) {
    echo "Virtual MAC erfolgreich erstellt!\n";
    echo "MAC-Adresse: {$result['macAddress']}\n";
} else {
    echo "Fehler beim Erstellen der Virtual MAC\n";
}
```

#### addVirtualMacIP($serviceName, $macAddress, $ipAddress, $virtualNetworkInterface)
Fügt eine IP-Adresse zu einer Virtual MAC hinzu.

**Parameter:**
- `$serviceName` (string) - Name des OVH Dedicated Servers
- `$macAddress` (string) - MAC-Adresse im Format '00:1a:2b:3c:4d:5e'
- `$ipAddress` (string) - IP-Adresse die zugewiesen werden soll
- `$virtualNetworkInterface` (string) - Netzwerk-Interface

```php
$ovh = new OVHPost();
$result = $ovh->addVirtualMacIP(
    'mein-server',
    '00:1a:2b:3c:4d:5e',
    '192.168.1.100',
    'eth0'
);

if ($result) {
    echo "IP erfolgreich zu Virtual MAC hinzugefügt!\n";
} else {
    echo "Fehler beim Hinzufügen der IP\n";
}
```

#### createDNSRecord($domain, $recordData)
Erstellt einen DNS-Record.

```php
$ovh = new OVHPost();
$domain = 'example.com';
$recordData = [
    'fieldType' => 'A',
    'subDomain' => 'www',
    'target' => '192.168.1.100',
    'ttl' => 3600
];

$result = $ovh->createDNSRecord($domain, $recordData);

if ($result) {
    echo "DNS-Record erfolgreich erstellt!\n";
}
```

## 🔧 ServiceManager Integration

### getVirtualMacAddresses($serviceName)
Holt alle Virtual MAC-Adressen über den ServiceManager.

```php
$serviceManager = new ServiceManager();
$serviceName = 'mein-server';
$virtualMacs = $serviceManager->getVirtualMacAddresses($serviceName);

foreach ($virtualMacs as $mac) {
    echo "MAC: {$mac->macAddress} - Service: {$mac->serviceName}\n";
}
```

### createVirtualMac($serviceName, $virtualNetworkInterface, $type)
Erstellt eine Virtual MAC über den ServiceManager.

```php
$serviceManager = new ServiceManager();
$result = $serviceManager->createVirtualMac('mein-server', 'eth0', 'ovh');

if ($result) {
    echo "Virtual MAC erfolgreich erstellt!\n";
}
```

### addIPToVirtualMac($serviceName, $macAddress, $ipAddress, $virtualNetworkInterface)
Fügt eine IP zu einer Virtual MAC über den ServiceManager hinzu.

```php
$serviceManager = new ServiceManager();
$result = $serviceManager->addIPToVirtualMac(
    'mein-server',
    '00:1a:2b:3c:4d:5e',
    '192.168.1.100',
    'eth0'
);
```

### OvhAPI($type, $url, $code = null)
Generische OVH API-Funktion für direkten Zugriff.

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

// VPS-Status abrufen
$vpsStatus = $serviceManager->OvhAPI('get', '/vps/vps123/status');
```

## 📊 Datenmodelle

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

## 🔍 Fehlerbehandlung

### API-Status prüfen

```php
$serviceManager = new ServiceManager();

// OVH API-Status prüfen
$apiCheck = $serviceManager->checkAPIEnabled('ovh');
if ($apiCheck !== true) {
    echo "OVH API Fehler: " . $apiCheck['message'] . "\n";
    echo "Lösung: " . $apiCheck['solution'] . "\n";
    exit;
}
```

### Try-Catch Fehlerbehandlung

```php
try {
    $ovh = new OVHGet();
    $domains = $ovh->getDomains();
    
    if (is_array($domains)) {
        foreach ($domains as $domain) {
            echo "Domain: {$domain}\n";
        }
    } else {
        echo "Fehler beim Abrufen der Domains: " . $domains['message'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    error_log("OVH Error: " . $e->getMessage());
}
```

## 📝 Praktische Beispiele

### 1. Virtual MAC-Management

```php
<?php
require_once 'framework.php';

$serviceManager = new ServiceManager();

echo "=== OVH Virtual MAC-Management ===\n\n";

// Service-Name (OVH Dedicated Server)
$serviceName = 'mein-server';

// Alle Virtual MAC-Adressen abrufen
$virtualMacs = $serviceManager->getVirtualMacAddresses($serviceName);

if (empty($virtualMacs)) {
    echo "Keine Virtual MAC-Adressen gefunden.\n";
} else {
    foreach ($virtualMacs as $mac) {
        echo "MAC: {$mac->macAddress}\n";
        echo "Service: {$mac->serviceName}\n";
        echo "Interface: {$mac->virtualNetworkInterface}\n";
        echo "Type: {$mac->type}\n";
        
        if (!empty($mac->ips)) {
            echo "Zugewiesene IPs:\n";
            foreach ($mac->ips as $ip) {
                echo "  - {$ip['ipAddress']}\n";
            }
        }
        
        echo "---\n";
    }
}

// Neue Virtual MAC erstellen
echo "Erstelle neue Virtual MAC...\n";
$result = $serviceManager->createVirtualMac($serviceName, 'eth0', 'ovh');

if ($result) {
    echo "✅ Virtual MAC erfolgreich erstellt\n";
    
    // IP zu Virtual MAC hinzufügen
    $macAddress = $result['macAddress']; // Angenommen, die API gibt die MAC zurück
    $ipAddress = '192.168.1.100';
    
    $ipResult = $serviceManager->addIPToVirtualMac($serviceName, $macAddress, $ipAddress, 'eth0');
    
    if ($ipResult) {
        echo "✅ IP {$ipAddress} zu Virtual MAC hinzugefügt\n";
    } else {
        echo "❌ Fehler beim Hinzufügen der IP\n";
    }
} else {
    echo "❌ Fehler beim Erstellen der Virtual MAC\n";
}
?>
```

### 2. Domain-Management

```php
<?php
require_once 'framework.php';

$serviceManager = new ServiceManager();

echo "=== OVH Domain-Management ===\n\n";

// Alle Domains abrufen
$domains = $serviceManager->OvhAPI('get', '/domain');

if (is_array($domains)) {
    foreach ($domains as $domain) {
        echo "Domain: {$domain}\n";
        
        // DNS-Records abrufen
        $dnsRecords = $serviceManager->OvhAPI('get', "/domain/zone/{$domain}/record");
        
        if (is_array($dnsRecords)) {
            echo "DNS-Records:\n";
            foreach ($dnsRecords as $record) {
                echo "  - {$record['fieldType']} {$record['subDomain']} {$record['target']}\n";
            }
        }
        
        echo "---\n";
    }
}

// Neuen DNS-Record erstellen
$domain = 'example.com';
$dnsData = [
    'fieldType' => 'A',
    'subDomain' => 'www',
    'target' => '192.168.1.100',
    'ttl' => 3600
];

echo "Erstelle DNS-Record für {$domain}...\n";
$result = $serviceManager->OvhAPI('post', "/domain/zone/{$domain}/record", $dnsData);

if ($result) {
    echo "✅ DNS-Record erfolgreich erstellt\n";
} else {
    echo "❌ Fehler beim Erstellen des DNS-Records\n";
}
?>
```

### 3. VPS-Management

```php
<?php
require_once 'framework.php';

$serviceManager = new ServiceManager();

echo "=== OVH VPS-Management ===\n\n";

// Alle VPS-Instanzen abrufen
$vpsList = $serviceManager->OvhAPI('get', '/vps');

if (is_array($vpsList)) {
    foreach ($vpsList as $vpsId) {
        // VPS-Details abrufen
        $vpsDetails = $serviceManager->OvhAPI('get', "/vps/{$vpsId}");
        
        if (is_array($vpsDetails)) {
            echo "VPS: {$vpsDetails['name']}\n";
            echo "Status: {$vpsDetails['state']}\n";
            echo "IP: {$vpsDetails['ip']}\n";
            echo "Region: {$vpsDetails['zone']}\n";
            echo "---\n";
        }
    }
}

// VPS starten (Beispiel)
$vpsId = 'vps123';
echo "Starte VPS {$vpsId}...\n";
$result = $serviceManager->OvhAPI('post', "/vps/{$vpsId}/start");

if ($result) {
    echo "✅ VPS gestartet\n";
} else {
    echo "❌ Fehler beim Starten der VPS\n";
}
?>
```

### 4. Automatisches IP-Management

```php
<?php
require_once 'framework.php';

$serviceManager = new ServiceManager();

echo "=== Automatisches IP-Management ===\n\n";

// Service-Name
$serviceName = 'mein-server';

// IP-Pool definieren
$ipPool = [
    '192.168.1.100',
    '192.168.1.101',
    '192.168.1.102',
    '192.168.1.103',
    '192.168.1.104'
];

// Alle Virtual MAC-Adressen abrufen
$virtualMacs = $serviceManager->getVirtualMacAddresses($serviceName);

// Verfügbare IPs finden
$usedIPs = [];
foreach ($virtualMacs as $mac) {
    if (!empty($mac->ips)) {
        foreach ($mac->ips as $ip) {
            $usedIPs[] = $ip['ipAddress'];
        }
    }
}

$availableIPs = array_diff($ipPool, $usedIPs);

echo "Verfügbare IPs: " . implode(', ', $availableIPs) . "\n";

// Neue Virtual MAC mit verfügbarer IP erstellen
if (!empty($availableIPs)) {
    $newIP = array_shift($availableIPs);
    
    echo "Erstelle Virtual MAC mit IP {$newIP}...\n";
    $result = $serviceManager->createVirtualMac($serviceName, 'eth0', 'ovh');
    
    if ($result) {
        $macAddress = $result['macAddress'];
        
        // IP zuweisen
        $ipResult = $serviceManager->addIPToVirtualMac($serviceName, $macAddress, $newIP, 'eth0');
        
        if ($ipResult) {
            echo "✅ Virtual MAC mit IP {$newIP} erstellt\n";
        } else {
            echo "❌ Fehler beim Zuweisen der IP\n";
        }
    } else {
        echo "❌ Fehler beim Erstellen der Virtual MAC\n";
    }
} else {
    echo "❌ Keine verfügbaren IPs im Pool\n";
}
?>
```

### 5. DNS-Bulk-Operationen

```php
<?php
require_once 'framework.php';

$serviceManager = new ServiceManager();

echo "=== DNS-Bulk-Operationen ===\n\n";

// Domain
$domain = 'example.com';

// DNS-Records die erstellt werden sollen
$dnsRecords = [
    [
        'fieldType' => 'A',
        'subDomain' => 'www',
        'target' => '192.168.1.100',
        'ttl' => 3600
    ],
    [
        'fieldType' => 'A',
        'subDomain' => 'api',
        'target' => '192.168.1.101',
        'ttl' => 3600
    ],
    [
        'fieldType' => 'CNAME',
        'subDomain' => 'mail',
        'target' => 'mail.example.com',
        'ttl' => 3600
    ],
    [
        'fieldType' => 'MX',
        'subDomain' => '@',
        'target' => 'mail.example.com',
        'ttl' => 3600,
        'priority' => 10
    ]
];

foreach ($dnsRecords as $record) {
    echo "Erstelle DNS-Record: {$record['fieldType']} {$record['subDomain']} {$record['target']}\n";
    
    $result = $serviceManager->OvhAPI('post', "/domain/zone/{$domain}/record", $record);
    
    if ($result) {
        echo "✅ DNS-Record erstellt\n";
    } else {
        echo "❌ Fehler beim Erstellen des DNS-Records\n";
    }
    
    echo "---\n";
}

echo "DNS-Bulk-Operationen abgeschlossen!\n";
?>
```

## 🔗 Nützliche Links

- [OVH API Dokumentation](https://api.ovh.com/)
- [OVH API Keys](https://api.ovh.com/createToken/)
- [OVH API Explorer](https://api.ovh.com/console/)

## ❗ Wichtige Hinweise

1. **API-Keys**: Verwenden Sie sichere API-Keys und rotieren Sie diese regelmäßig
2. **Endpoints**: Wählen Sie den richtigen Endpoint (eu, ca, us) für Ihre Region
3. **Rate Limiting**: Beachten Sie die OVH API Rate Limits
4. **Virtual MAC**: Virtual MAC-Adressen ermöglichen flexible IP-Zuweisung
5. **DNS**: DNS-Änderungen können einige Zeit zur Verbreitung benötigen
6. **Sicherheit**: Verwenden Sie HTTPS für alle API-Verbindungen 