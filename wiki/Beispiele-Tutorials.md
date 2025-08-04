# Beispiele & Tutorials

Diese Seite enthält praktische Beispiele und Tutorials für die Verwendung des Server Management Frameworks.

## 🚀 Schnellstart-Tutorial

### 1. Framework initialisieren

```php
<?php
require_once 'framework.php';

// ServiceManager initialisieren
$serviceManager = new ServiceManager();

// API-Status prüfen
$apis = ['proxmox', 'ispconfig', 'ovh', 'ogp'];
foreach ($apis as $api) {
    $status = $serviceManager->checkAPIEnabled($api);
    if ($status === true) {
        echo "✅ {$api} API ist aktiviert\n";
    } else {
        echo "⚠️  {$api} API ist deaktiviert\n";
    }
}
?>
```

### 2. Erste VM erstellen

```php
<?php
require_once 'framework.php';

$serviceManager = new ServiceManager();

// VM-Daten definieren
$vmData = [
    'vmid' => 101,
    'name' => 'meine-erste-vm',
    'node' => 'pve',
    'memory' => 1024,
    'cores' => 1,
    'storage' => 'local',
    'disk' => '10'
];

// VM erstellen
$result = $serviceManager->createProxmoxVM($vmData);

if ($result) {
    echo "VM erfolgreich erstellt!\n";
    
    // VM starten
    sleep(5);
    $serviceManager->controlProxmoxVM('pve', 101, 'start');
    echo "VM gestartet!\n";
} else {
    echo "Fehler beim Erstellen der VM\n";
}
?>
```

## 📊 VM-Management Beispiele

### 1. VM-Monitoring Dashboard

```php
<?php
require_once 'framework.php';

$serviceManager = new ServiceManager();

echo "=== VM Monitoring Dashboard ===\n\n";

// Alle VMs abrufen
$vms = $serviceManager->getProxmoxVMs();

if (empty($vms)) {
    echo "Keine VMs gefunden.\n";
} else {
    foreach ($vms as $vm) {
        $statusIcon = ($vm->status === 'running') ? '🟢' : '🔴';
        
        echo "{$statusIcon} VM: {$vm->name} (ID: {$vm->vmid})\n";
        echo "   Status: {$vm->status}\n";
        echo "   CPU: {$vm->cores} Cores, Memory: {$vm->memory} MB\n";
        
        if ($vm->status === 'running') {
            echo "   CPU Usage: {$vm->cpu_usage}%\n";
            echo "   Memory Usage: {$vm->memory_usage}%\n";
            echo "   Uptime: {$vm->uptime}\n";
        }
        
        echo "   IP: {$vm->ip_address}\n";
        echo "---\n";
    }
    
    // Statistiken
    $runningVMs = array_filter($vms, function($vm) { return $vm->status === 'running'; });
    $totalMemory = array_sum(array_column($vms, 'memory'));
    $totalCores = array_sum(array_column($vms, 'cores'));
    
    echo "Statistiken:\n";
    echo "Laufende VMs: " . count($runningVMs) . " / " . count($vms) . "\n";
    echo "Gesamte Memory: {$totalMemory} MB\n";
    echo "Gesamte CPU Cores: {$totalCores}\n";
}
?>
```

### 2. Automatische VM-Backup

```php
<?php
require_once 'framework.php';

$serviceManager = new ServiceManager();

echo "=== Automatisches VM-Backup ===\n\n";

// Alle VMs abrufen
$vms = $serviceManager->getProxmoxVMs();

foreach ($vms as $vm) {
    echo "Verarbeite VM: {$vm->name}\n";
    
    // Backup erstellen
    $backupData = [
        'storage' => 'backup',
        'compress' => 'lz4',
        'mode' => 'snapshot'
    ];
    
    $result = $serviceManager->ProxmoxAPI(
        'post',
        "/nodes/{$vm->node}/qemu/{$vm->vmid}/snapshot",
        $backupData
    );
    
    if ($result) {
        echo "✅ Backup für VM {$vm->name} erstellt\n";
        
        // Backup-Log erstellen
        $db = Database::getInstance();
        $db->logAction(
            'VM Backup',
            "Backup für VM {$vm->name} (ID: {$vm->vmid}) erstellt",
            'success'
        );
    } else {
        echo "❌ Fehler beim Backup von VM {$vm->name}\n";
    }
}

echo "\nBackup-Prozess abgeschlossen!\n";
?>
```

### 3. VM-Template-System

```php
<?php
require_once 'framework.php';

$serviceManager = new ServiceManager();

// VM-Templates definieren
$vmTemplates = [
    'webserver' => [
        'memory' => 2048,
        'cores' => 2,
        'disk' => '20',
        'iso' => 'local:iso/ubuntu-20.04-server-amd64.iso',
        'description' => 'Webserver mit Ubuntu 20.04'
    ],
    'database' => [
        'memory' => 4096,
        'cores' => 4,
        'disk' => '50',
        'iso' => 'local:iso/ubuntu-20.04-server-amd64.iso',
        'description' => 'Datenbankserver mit Ubuntu 20.04'
    ],
    'game-server' => [
        'memory' => 8192,
        'cores' => 4,
        'disk' => '100',
        'iso' => 'local:iso/ubuntu-20.04-server-amd64.iso',
        'description' => 'Game-Server mit Ubuntu 20.04'
    ]
];

// Funktion zum Erstellen von VMs aus Templates
function createVMFromTemplate($serviceManager, $templateName, $vmName, $vmid) {
    global $vmTemplates;
    
    if (!isset($vmTemplates[$templateName])) {
        return false;
    }
    
    $template = $vmTemplates[$templateName];
    
    $vmData = array_merge($template, [
        'vmid' => $vmid,
        'name' => $vmName,
        'node' => 'pve',
        'bridge' => 'vmbr0',
        'storage' => 'local'
    ]);
    
    return $serviceManager->createProxmoxVM($vmData);
}

// Beispiel: VMs aus Templates erstellen
$newVMs = [
    ['name' => 'web1', 'template' => 'webserver', 'vmid' => 101],
    ['name' => 'web2', 'template' => 'webserver', 'vmid' => 102],
    ['name' => 'db1', 'template' => 'database', 'vmid' => 103],
    ['name' => 'game1', 'template' => 'game-server', 'vmid' => 104]
];

foreach ($newVMs as $vmInfo) {
    echo "Erstelle VM: {$vmInfo['name']} aus Template: {$vmInfo['template']}\n";
    
    $result = createVMFromTemplate(
        $serviceManager,
        $vmInfo['template'],
        $vmInfo['name'],
        $vmInfo['vmid']
    );
    
    if ($result) {
        echo "✅ VM {$vmInfo['name']} erfolgreich erstellt\n";
        
        // VM starten
        sleep(5);
        $serviceManager->controlProxmoxVM('pve', $vmInfo['vmid'], 'start');
        echo "🚀 VM {$vmInfo['name']} gestartet\n";
    } else {
        echo "❌ Fehler beim Erstellen von VM {$vmInfo['name']}\n";
    }
    
    echo "---\n";
}
?>
```

## 🌐 Website-Management Beispiele

### 1. ISPConfig Website-Übersicht

```php
<?php
require_once 'framework.php';

$serviceManager = new ServiceManager();

echo "=== ISPConfig Website-Übersicht ===\n\n";

// Alle Websites abrufen
$websites = $serviceManager->getISPConfigWebsites();

if (empty($websites)) {
    echo "Keine Websites gefunden.\n";
} else {
    foreach ($websites as $website) {
        $statusIcon = ($website->active) ? '🟢' : '🔴';
        
        echo "{$statusIcon} Website: {$website->domain}\n";
        echo "   IP: {$website->ip_address}\n";
        echo "   Benutzer: {$website->system_user}\n";
        echo "   Gruppe: {$website->system_group}\n";
        echo "   Quota: {$website->hd_quota} MB\n";
        echo "   Traffic: {$website->traffic_quota} MB\n";
        echo "   SSL: " . ($website->ssl_enabled ? 'Aktiviert' : 'Deaktiviert') . "\n";
        echo "---\n";
    }
}
?>
```

### 2. Automatische Website-Erstellung

```php
<?php
require_once 'framework.php';

$serviceManager = new ServiceManager();

// Website-Templates definieren
$websiteTemplates = [
    'small' => [
        'quota' => 1000,
        'traffic' => 10000,
        'description' => 'Kleine Website'
    ],
    'medium' => [
        'quota' => 5000,
        'traffic' => 50000,
        'description' => 'Mittlere Website'
    ],
    'large' => [
        'quota' => 20000,
        'traffic' => 200000,
        'description' => 'Große Website'
    ]
];

// Neue Websites erstellen
$newWebsites = [
    [
        'domain' => 'meine-website.de',
        'ip' => '192.168.1.100',
        'template' => 'medium',
        'user' => 'webuser1',
        'group' => 'webgroup1'
    ],
    [
        'domain' => 'shop.example.com',
        'ip' => '192.168.1.101',
        'template' => 'large',
        'user' => 'webuser2',
        'group' => 'webgroup2'
    ]
];

foreach ($newWebsites as $websiteInfo) {
    $template = $websiteTemplates[$websiteInfo['template']];
    
    $websiteData = array_merge($template, [
        'ip' => $websiteInfo['ip'],
        'domain' => $websiteInfo['domain'],
        'user' => $websiteInfo['user'],
        'group' => $websiteInfo['group']
    ]);
    
    echo "Erstelle Website: {$websiteInfo['domain']}\n";
    
    $result = $serviceManager->createISPConfigWebsite($websiteData);
    
    if ($result) {
        echo "✅ Website {$websiteInfo['domain']} erfolgreich erstellt\n";
        
        // Log erstellen
        $db = Database::getInstance();
        $db->logAction(
            'Website erstellt',
            "Website {$websiteInfo['domain']} mit Template {$websiteInfo['template']} erstellt",
            'success'
        );
    } else {
        echo "❌ Fehler beim Erstellen der Website {$websiteInfo['domain']}\n";
    }
    
    echo "---\n";
}
?>
```

## 🌍 OVH API Beispiele

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

## 🎮 OGP Game-Server Beispiele

### 1. Game-Server-Übersicht

```php
<?php
require_once 'framework.php';

$serviceManager = new ServiceManager();

echo "=== OGP Game-Server-Übersicht ===\n\n";

// Token testen
$tokenStatus = $serviceManager->testOGPToken();
if ($tokenStatus) {
    echo "✅ OGP Token ist gültig\n\n";
} else {
    echo "❌ OGP Token ist ungültig\n";
    exit;
}

// Alle Game-Server abrufen
$gameServers = $serviceManager->getOGPGameServers();

if (empty($gameServers)) {
    echo "Keine Game-Server gefunden.\n";
} else {
    foreach ($gameServers as $server) {
        $statusIcon = ($server['status'] === 'online') ? '🟢' : '🔴';
        
        echo "{$statusIcon} Game-Server: {$server['name']}\n";
        echo "   Spiel: {$server['game_key']}\n";
        echo "   Status: {$server['status']}\n";
        echo "   Port: {$server['port']}\n";
        echo "   IP: {$server['ip']}\n";
        echo "---\n";
    }
}
?>
```

### 2. Game-Server erstellen und verwalten

```php
<?php
require_once 'framework.php';

$serviceManager = new ServiceManager();

echo "=== OGP Game-Server erstellen ===\n\n";

// Game-Server-Daten
$gameServerData = [
    'remote_server_id' => 1,
    'game_key' => 'csgo',
    'name' => 'Mein CS:GO Server',
    'port' => 27015,
    'query_port' => 27016,
    'rcon_port' => 27017,
    'rcon_password' => 'meinpasswort'
];

echo "Erstelle Game-Server: {$gameServerData['name']}\n";
$result = $serviceManager->createOGPGameServer($gameServerData);

if ($result) {
    echo "✅ Game-Server erfolgreich erstellt\n";
    
    // RCON-Befehl senden
    echo "Sende RCON-Befehl...\n";
    $rconResult = $serviceManager->sendOGPRconCommand(
        '192.168.1.100',
        27015,
        'csgo',
        'say Hallo Welt!'
    );
    
    if ($rconResult) {
        echo "✅ RCON-Befehl erfolgreich gesendet\n";
    } else {
        echo "❌ Fehler beim Senden des RCON-Befehls\n";
    }
} else {
    echo "❌ Fehler beim Erstellen des Game-Servers\n";
}
?>
```

## 🔧 Erweiterte Beispiele

### 1. Multi-API Monitoring Dashboard

```php
<?php
require_once 'framework.php';

$serviceManager = new ServiceManager();

echo "=== Multi-API Monitoring Dashboard ===\n\n";

// Proxmox Status
echo "📊 PROXMOX STATUS:\n";
$proxmoxStatus = $serviceManager->checkAPIEnabled('proxmox');
if ($proxmoxStatus === true) {
    $vms = $serviceManager->getProxmoxVMs();
    $runningVMs = array_filter($vms, function($vm) { return $vm->status === 'running'; });
    echo "   VMs: " . count($runningVMs) . " / " . count($vms) . " laufen\n";
} else {
    echo "   ❌ Proxmox API nicht verfügbar\n";
}

// ISPConfig Status
echo "\n📊 ISPCONFIG STATUS:\n";
$ispconfigStatus = $serviceManager->checkAPIEnabled('ispconfig');
if ($ispconfigStatus === true) {
    $websites = $serviceManager->getISPConfigWebsites();
    $activeWebsites = array_filter($websites, function($site) { return $site->active; });
    echo "   Websites: " . count($activeWebsites) . " / " . count($websites) . " aktiv\n";
} else {
    echo "   ❌ ISPConfig API nicht verfügbar\n";
}

// OVH Status
echo "\n📊 OVH STATUS:\n";
$ovhStatus = $serviceManager->checkAPIEnabled('ovh');
if ($ovhStatus === true) {
    $domains = $serviceManager->OvhAPI('get', '/domain');
    echo "   Domains: " . count($domains) . " verwaltet\n";
} else {
    echo "   ❌ OVH API nicht verfügbar\n";
}

// OGP Status
echo "\n📊 OGP STATUS:\n";
$ogpStatus = $serviceManager->checkAPIEnabled('ogp');
if ($ogpStatus === true) {
    $gameServers = $serviceManager->getOGPGameServers();
    $onlineServers = array_filter($gameServers, function($server) { return $server['status'] === 'online'; });
    echo "   Game-Server: " . count($onlineServers) . " / " . count($gameServers) . " online\n";
} else {
    echo "   ❌ OGP API nicht verfügbar\n";
}

// Aktivitäts-Logs
echo "\n📊 AKTIVITÄTS-LOGS:\n";
$db = Database::getInstance();
$logs = $db->getActivityLog(5, 0);
foreach ($logs as $log) {
    $statusIcon = ($log['status'] === 'success') ? '✅' : '❌';
    echo "   {$statusIcon} {$log['action']} - {$log['created_at_formatted']}\n";
}
?>
```

### 2. Automatisiertes Deployment-System

```php
<?php
require_once 'framework.php';

$serviceManager = new ServiceManager();

echo "=== Automatisiertes Deployment-System ===\n\n";

// Deployment-Konfiguration
$deploymentConfig = [
    'project_name' => 'meine-webapp',
    'domain' => 'meine-webapp.de',
    'vm_template' => 'webserver',
    'website_template' => 'medium'
];

echo "Starte Deployment für: {$deploymentConfig['project_name']}\n\n";

// 1. VM erstellen
echo "1. Erstelle VM...\n";
$vmData = [
    'vmid' => 201,
    'name' => $deploymentConfig['project_name'],
    'node' => 'pve',
    'memory' => 2048,
    'cores' => 2,
    'storage' => 'local',
    'disk' => '20'
];

$vmResult = $serviceManager->createProxmoxVM($vmData);

if (!$vmResult) {
    echo "❌ Fehler beim Erstellen der VM\n";
    exit;
}

echo "✅ VM erstellt\n";

// 2. VM starten
echo "2. Starte VM...\n";
sleep(10);
$serviceManager->controlProxmoxVM('pve', 201, 'start');
echo "✅ VM gestartet\n";

// 3. Website erstellen
echo "3. Erstelle Website...\n";
sleep(30); // Warten bis VM vollständig gestartet ist

$websiteData = [
    'ip' => '192.168.1.200',
    'domain' => $deploymentConfig['domain'],
    'user' => 'webuser',
    'group' => 'webgroup',
    'quota' => 5000,
    'traffic' => 50000
];

$websiteResult = $serviceManager->createISPConfigWebsite($websiteData);

if (!$websiteResult) {
    echo "❌ Fehler beim Erstellen der Website\n";
    exit;
}

echo "✅ Website erstellt\n";

// 4. DNS-Record erstellen
echo "4. Erstelle DNS-Record...\n";
$dnsData = [
    'fieldType' => 'A',
    'subDomain' => 'www',
    'target' => '192.168.1.200',
    'ttl' => 3600
];

$dnsResult = $serviceManager->OvhAPI('post', "/domain/zone/{$deploymentConfig['domain']}/record", $dnsData);

if ($dnsResult) {
    echo "✅ DNS-Record erstellt\n";
} else {
    echo "⚠️  DNS-Record konnte nicht erstellt werden\n";
}

// 5. Deployment-Log erstellen
$db = Database::getInstance();
$db->logAction(
    'Deployment abgeschlossen',
    "Projekt {$deploymentConfig['project_name']} erfolgreich deployed. VM: 201, Domain: {$deploymentConfig['domain']}",
    'success'
);

echo "\n🎉 Deployment erfolgreich abgeschlossen!\n";
echo "VM ID: 201\n";
echo "Domain: {$deploymentConfig['domain']}\n";
echo "IP: 192.168.1.200\n";
?>
```

## 🔗 Nützliche Links

- [Installation & Setup](Installation-Setup)
- [Proxmox API](Proxmox-API)
- [ISPConfig API](ISPConfig-API)
- [OVH API](OVH-API)
- [OGP API](OGP-API)
- [Framework Komponenten](Framework-Komponenten)

## 💡 Tipps & Best Practices

1. **Fehlerbehandlung**: Verwenden Sie immer try-catch Blöcke
2. **Logging**: Protokollieren Sie wichtige Aktionen
3. **API-Status**: Prüfen Sie vor API-Aufrufen den Status
4. **Performance**: Verwenden Sie die generischen API-Funktionen für bessere Performance
5. **Sicherheit**: Verwenden Sie sichere Passwörter und HTTPS
6. **Backup**: Erstellen Sie regelmäßig Backups
7. **Monitoring**: Überwachen Sie Ihre Systeme kontinuierlich 