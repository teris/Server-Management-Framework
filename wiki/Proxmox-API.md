# Proxmox API Integration

Die Proxmox API Integration ermöglicht die vollständige Verwaltung von virtuellen Maschinen und Containern über das Proxmox Virtual Environment.

## 🔧 Konfiguration

### 1. Proxmox-Zugangsdaten einrichten

Fügen Sie folgende Konfiguration in `config/config.inc.php` hinzu:

```php
// ===== PROXMOX KONFIGURATION =====
const PROXMOX_USEING = true;
const PROXMOX_HOST = 'https://pve.example.com:8006';
const PROXMOX_USER = 'root@pam';
const PROXMOX_PASSWORD = 'your_proxmox_password';
const PROXMOX_REALM = 'pam';  // Standard: pam
```

### 2. API-Benutzer erstellen

1. Melden Sie sich bei Ihrem Proxmox-Server an
2. Gehen Sie zu `Datacenter` → `Users`
3. Erstellen Sie einen neuen Benutzer oder verwenden Sie `root@pam`
4. Stellen Sie sicher, dass der Benutzer API-Zugriff hat

## 📚 API-Klassen

### ProxmoxGet Klasse

Lese-Operationen für Proxmox.

#### getNodes()
Holt alle verfügbaren Nodes.

```php
$proxmox = new ProxmoxGet();
$nodes = $proxmox->getNodes();

foreach ($nodes as $node) {
    echo "Node: " . $node['node'] . "\n";
    echo "Status: " . $node['status'] . "\n";
    echo "CPU: " . $node['cpu'] . "\n";
    echo "Memory: " . $node['memory'] . " MB\n";
}
```

#### getVMs($node = null)
Holt alle VMs von allen Nodes oder einem spezifischen Node.

```php
// Alle VMs von allen Nodes
$vms = $proxmox->getVMs();

// VMs von einem spezifischen Node
$vms = $proxmox->getVMs('pve');

foreach ($vms as $vm) {
    echo "VM: {$vm->name} (ID: {$vm->vmid}) - Status: {$vm->status}\n";
    echo "CPU: {$vm->cores} Cores, Memory: {$vm->memory} MB\n";
    echo "IP: {$vm->ip_address}\n";
}
```

#### getVM($node, $vmid)
Holt Details einer spezifischen VM.

```php
$vm = $proxmox->getVM('pve', 100);
if ($vm) {
    echo "VM Details:\n";
    echo "Name: {$vm->name}\n";
    echo "Status: {$vm->status}\n";
    echo "CPU Usage: {$vm->cpu_usage}%\n";
    echo "Memory Usage: {$vm->memory_usage}%\n";
    echo "Uptime: {$vm->uptime}\n";
}
```

#### getNodeStatus($node)
Holt den Status eines spezifischen Nodes.

```php
$status = $proxmox->getNodeStatus('pve');
echo "Node Status: {$status['status']}\n";
echo "CPU Load: {$status['cpu']}\n";
echo "Memory Usage: {$status['memory']['used']} / {$status['memory']['total']} MB\n";
```

### ProxmoxPost Klasse

Schreib-Operationen für Proxmox.

#### createVM($vmData)
Erstellt eine neue VM.

**Wichtige Parameter:**
- `vmid` (Pflicht): Eindeutige ID der VM (100-999999)
- `name` (Pflicht): Name der virtuellen Maschine
- `node` (Pflicht): Name des Proxmox-Nodes
- `memory` (Pflicht): RAM in MB
- `cores` (Pflicht): Anzahl der CPU-Kerne
- `bridge` (Optional): Netzwerk-Bridge (Standard: 'vmbr0')
- `mac` (Optional): MAC-Adresse für das Netzwerk
- `storage` (Pflicht): Name des Storage
- `disk` (Pflicht): Größe der Festplatte in GB
- `iso` (Optional): ISO-Image für Installation

```php
$vmData = [
    'vmid' => 101,
    'name' => 'webserver',
    'node' => 'pve',
    'memory' => 2048,
    'cores' => 2,
    'bridge' => 'vmbr0',
    'mac' => '00:1a:2b:3c:4d:5e',
    'storage' => 'local',
    'disk' => '20',
    'iso' => 'local:iso/ubuntu-20.04-server-amd64.iso'
];

$proxmox = new ProxmoxPost();
$result = $proxmox->createVM($vmData);

if ($result) {
    echo "VM erfolgreich erstellt!\n";
} else {
    echo "Fehler beim Erstellen der VM\n";
}
```

#### controlProxmoxVM($node, $vmid, $action)
Steuert eine VM.

**Verfügbare Aktionen:**
- `start` - VM starten
- `stop` - VM stoppen
- `reset` - VM neu starten
- `suspend` - VM pausieren
- `resume` - VM fortsetzen
- `shutdown` - VM herunterfahren

```php
$proxmox = new ProxmoxPost();

// VM starten
$result = $proxmox->controlProxmoxVM('pve', 101, 'start');

// VM stoppen
$result = $proxmox->controlProxmoxVM('pve', 101, 'stop');

// VM neu starten
$result = $proxmox->controlProxmoxVM('pve', 101, 'reset');
```

#### deleteVM($node, $vmid)
Löscht eine VM.

```php
$proxmox = new ProxmoxPost();
$result = $proxmox->deleteVM('pve', 101);

if ($result) {
    echo "VM erfolgreich gelöscht!\n";
}
```

#### cloneVM($node, $vmid, $newVmid, $newName)
Kloniert eine VM.

```php
$proxmox = new ProxmoxPost();
$result = $proxmox->cloneVM('pve', 101, 102, 'webserver-clone');

if ($result) {
    echo "VM erfolgreich geklont!\n";
}
```

## 🔧 ServiceManager Integration

### getProxmoxVMs()
Holt alle VMs über den ServiceManager.

```php
$serviceManager = new ServiceManager();
$vms = $serviceManager->getProxmoxVMs();

foreach ($vms as $vm) {
    echo "VM: {$vm->name} (ID: {$vm->vmid}) - Status: {$vm->status}\n";
}
```

### createProxmoxVM($vmData)
Erstellt eine VM über den ServiceManager.

```php
$serviceManager = new ServiceManager();

$vmData = [
    'vmid' => 101,
    'name' => 'webserver',
    'node' => 'pve',
    'memory' => 2048,
    'cores' => 2,
    'storage' => 'local',
    'disk' => '20'
];

$result = $serviceManager->createProxmoxVM($vmData);
```

### controlProxmoxVM($node, $vmid, $action)
Steuert eine VM über den ServiceManager.

```php
$serviceManager = new ServiceManager();
$serviceManager->controlProxmoxVM('pve', 101, 'start');
```

### ProxmoxAPI($type, $url, $code = null)
Generische Proxmox API-Funktion für direkten Zugriff.

```php
$serviceManager = new ServiceManager();

// Alle Nodes abrufen
$nodes = $serviceManager->ProxmoxAPI('get', '/nodes');

// VM-Konfiguration abrufen
$vmConfig = $serviceManager->ProxmoxAPI('get', '/nodes/pve/qemu/100/config');

// VM starten
$result = $serviceManager->ProxmoxAPI('post', '/nodes/pve/qemu/100/status/start');

// VM-Konfiguration ändern
$configData = [
    'memory' => 4096,
    'cores' => 4
];
$result = $serviceManager->ProxmoxAPI('put', '/nodes/pve/qemu/100/config', $configData);
```

## 📊 Datenmodelle

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

## 🔍 Fehlerbehandlung

### API-Status prüfen

```php
$serviceManager = new ServiceManager();

// Proxmox API-Status prüfen
$apiCheck = $serviceManager->checkAPIEnabled('proxmox');
if ($apiCheck !== true) {
    echo "Proxmox API Fehler: " . $apiCheck['message'] . "\n";
    echo "Lösung: " . $apiCheck['solution'] . "\n";
    exit;
}
```

### Try-Catch Fehlerbehandlung

```php
try {
    $proxmox = new ProxmoxGet();
    $vms = $proxmox->getVMs();
    
    if (is_array($vms)) {
        foreach ($vms as $vm) {
            echo "VM: {$vm->name}\n";
        }
    } else {
        echo "Fehler beim Abrufen der VMs: " . $vms['message'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    error_log("Proxmox Error: " . $e->getMessage());
}
```

## 📝 Praktische Beispiele

### 1. VM-Monitoring

```php
<?php
require_once 'framework.php';

$serviceManager = new ServiceManager();

// Alle VMs abrufen und Status anzeigen
$vms = $serviceManager->getProxmoxVMs();

echo "=== VM Monitoring ===\n";
foreach ($vms as $vm) {
    echo "VM: {$vm->name} (ID: {$vm->vmid})\n";
    echo "  Status: {$vm->status}\n";
    echo "  CPU: {$vm->cpu_usage}%\n";
    echo "  Memory: {$vm->memory_usage}%\n";
    echo "  Uptime: {$vm->uptime}\n";
    echo "  IP: {$vm->ip_address}\n";
    echo "---\n";
}
?>
```

### 2. VM-Backup-System

```php
<?php
require_once 'framework.php';

$serviceManager = new ServiceManager();

// Alle laufenden VMs stoppen
$vms = $serviceManager->getProxmoxVMs();
foreach ($vms as $vm) {
    if ($vm->status === 'running') {
        echo "Stoppe VM: {$vm->name}\n";
        $serviceManager->controlProxmoxVM($vm->node, $vm->vmid, 'stop');
        sleep(10); // Warten bis VM gestoppt ist
    }
}

// Backup erstellen (über generische API)
foreach ($vms as $vm) {
    $backupData = [
        'storage' => 'backup',
        'compress' => 'lz4'
    ];
    
    $result = $serviceManager->ProxmoxAPI(
        'post', 
        "/nodes/{$vm->node}/qemu/{$vm->vmid}/snapshot", 
        $backupData
    );
    
    if ($result) {
        echo "Backup für VM {$vm->name} erstellt\n";
    }
}

// VMs wieder starten
foreach ($vms as $vm) {
    if ($vm->status === 'running') {
        echo "Starte VM: {$vm->name}\n";
        $serviceManager->controlProxmoxVM($vm->node, $vm->vmid, 'start');
    }
}
?>
```

### 3. Automatische VM-Erstellung

```php
<?php
require_once 'framework.php';

$serviceManager = new ServiceManager();

// VM-Template definieren
$vmTemplates = [
    'webserver' => [
        'memory' => 2048,
        'cores' => 2,
        'disk' => '20',
        'iso' => 'local:iso/ubuntu-20.04-server-amd64.iso'
    ],
    'database' => [
        'memory' => 4096,
        'cores' => 4,
        'disk' => '50',
        'iso' => 'local:iso/ubuntu-20.04-server-amd64.iso'
    ]
];

// Neue VMs erstellen
$newVMs = [
    ['name' => 'web1', 'type' => 'webserver', 'vmid' => 101],
    ['name' => 'web2', 'type' => 'webserver', 'vmid' => 102],
    ['name' => 'db1', 'type' => 'database', 'vmid' => 103]
];

foreach ($newVMs as $vmInfo) {
    $template = $vmTemplates[$vmInfo['type']];
    
    $vmData = array_merge($template, [
        'vmid' => $vmInfo['vmid'],
        'name' => $vmInfo['name'],
        'node' => 'pve',
        'bridge' => 'vmbr0',
        'storage' => 'local'
    ]);
    
    $result = $serviceManager->createProxmoxVM($vmData);
    
    if ($result) {
        echo "VM {$vmInfo['name']} erfolgreich erstellt\n";
        
        // VM starten
        sleep(5);
        $serviceManager->controlProxmoxVM('pve', $vmInfo['vmid'], 'start');
    } else {
        echo "Fehler beim Erstellen von VM {$vmInfo['name']}\n";
    }
}
?>
```

## 🔗 Nützliche Links

- [Proxmox API Dokumentation](https://pve.proxmox.com/pve-docs/api-viewer/)
- [Proxmox VE Handbuch](https://pve.proxmox.com/pve-docs/)
- [Proxmox Forum](https://forum.proxmox.com/)

## ❗ Wichtige Hinweise

1. **Sicherheit**: Verwenden Sie starke Passwörter und HTTPS
2. **Backup**: Erstellen Sie regelmäßig Backups Ihrer VMs
3. **Monitoring**: Überwachen Sie CPU- und Memory-Nutzung
4. **Updates**: Halten Sie Proxmox VE aktuell
5. **Firewall**: Konfigurieren Sie eine Firewall für den Proxmox-Server 