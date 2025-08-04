# OGP API Integration

Die OGP API Integration ermöglicht die Verwaltung von Game-Servern über die Open Game Panel (OGP) API.

## 🔧 Konfiguration

### 1. OGP-Zugangsdaten einrichten

Fügen Sie folgende Konfiguration in `config/config.inc.php` hinzu:

```php
// ===== OGP KONFIGURATION =====
const OGP_USEING = true;
const OGP_HOST = 'https://ogp.example.com';
const OGP_USER = 'admin';
const OGP_PASSWORD = 'your_ogp_password';
const OGP_TOKEN = 'your_ogp_token';
```

### 2. OGP API-Token erstellen

1. Melden Sie sich bei OGP an
2. Gehen Sie zu `Settings` → `API`
3. Generieren Sie einen API-Token
4. Notieren Sie sich Host, Benutzer und Token

## 📚 API-Klassen

### OGPGet Klasse

Lese-Operationen für OGP.

#### getServerList()
Holt alle Remote-Server.

```php
$ogp = new OGPGet();
$servers = $ogp->getServerList();

foreach ($servers as $server) {
    echo "Server: {$server['name']} - IP: {$server['ip']}\n";
    echo "Status: {$server['status']}\n";
    echo "---\n";
}
```

#### getGameServers()
Holt alle Game-Server.

```php
$ogp = new OGPGet();
$gameServers = $ogp->getGameServers();

foreach ($gameServers as $server) {
    echo "Game-Server: {$server['name']}\n";
    echo "Spiel: {$server['game_key']}\n";
    echo "Status: {$server['status']}\n";
    echo "Port: {$server['port']}\n";
    echo "---\n";
}
```

#### getServerStatus($serverId)
Holt den Status eines spezifischen Servers.

```php
$ogp = new OGPGet();
$status = $ogp->getServerStatus(1);

echo "Server Status: {$status['status']}\n";
echo "CPU Usage: {$status['cpu']}%\n";
echo "Memory Usage: {$status['memory']}%\n";
echo "Uptime: {$status['uptime']}\n";
```

### OGPPost Klasse

Schreib-Operationen für OGP.

#### createGameServer($gameServerData)
Erstellt einen neuen Game-Server.

**Wichtige Parameter:**
- `remote_server_id` (Pflicht): ID des Remote-Servers
- `game_key` (Pflicht): Spiel-Identifier (z.B. 'csgo', 'minecraft')
- `name` (Pflicht): Name des Game-Servers
- `port` (Pflicht): Hauptport des Servers
- `query_port` (Optional): Query-Port für Server-Status
- `rcon_port` (Optional): RCON-Port für Remote-Commands
- `rcon_password` (Optional): RCON-Passwort

```php
$gameServerData = [
    'remote_server_id' => 1,
    'game_key' => 'csgo',
    'name' => 'Mein CS:GO Server',
    'port' => 27015,
    'query_port' => 27016,
    'rcon_port' => 27017,
    'rcon_password' => 'meinpasswort',
    'max_players' => 32,
    'tickrate' => 128
];

$ogp = new OGPPost();
$result = $ogp->createGameServer($gameServerData);

if ($result) {
    echo "Game-Server erfolgreich erstellt!\n";
} else {
    echo "Fehler beim Erstellen des Game-Servers\n";
}
```

#### sendRconCommand($ip, $port, $modKey, $command)
Sendet einen RCON-Befehl an einen Game-Server.

**Parameter:**
- `$ip` (string) - Server-IP-Adresse
- `$port` (int) - Server-Port
- `$modKey` (string) - Spiel-Modul-Key
- `$command` (string) - RCON-Befehl

```php
$ogp = new OGPPost();
$result = $ogp->sendRconCommand(
    '192.168.1.100',
    27015,
    'csgo',
    'say Hallo Welt!'
);

if ($result) {
    echo "RCON-Befehl erfolgreich gesendet!\n";
} else {
    echo "Fehler beim Senden des RCON-Befehls\n";
}
```

#### startGameServer($serverId)
Startet einen Game-Server.

```php
$ogp = new OGPPost();
$result = $ogp->startGameServer(1);

if ($result) {
    echo "Game-Server erfolgreich gestartet!\n";
}
```

#### stopGameServer($serverId)
Stoppt einen Game-Server.

```php
$ogp = new OGPPost();
$result = $ogp->stopGameServer(1);

if ($result) {
    echo "Game-Server erfolgreich gestoppt!\n";
}
```

#### restartGameServer($serverId)
Startet einen Game-Server neu.

```php
$ogp = new OGPPost();
$result = $ogp->restartGameServer(1);

if ($result) {
    echo "Game-Server erfolgreich neu gestartet!\n";
}
```

## 🔧 ServiceManager Integration

### getOGPGameServers()
Holt alle Game-Server über den ServiceManager.

```php
$serviceManager = new ServiceManager();
$gameServers = $serviceManager->getOGPGameServers();

foreach ($gameServers as $server) {
    echo "Game-Server: {$server['name']} - Status: {$server['status']}\n";
}
```

### createOGPGameServer($gameServerData)
Erstellt einen Game-Server über den ServiceManager.

```php
$serviceManager = new ServiceManager();

$gameServerData = [
    'remote_server_id' => 1,
    'game_key' => 'csgo',
    'name' => 'Mein CS:GO Server',
    'port' => 27015
];

$result = $serviceManager->createOGPGameServer($gameServerData);
```

### sendOGPRconCommand($ip, $port, $modKey, $command)
Sendet einen RCON-Befehl über den ServiceManager.

```php
$serviceManager = new ServiceManager();
$serviceManager->sendOGPRconCommand('192.168.1.100', 27015, 'csgo', 'say Hallo Welt!');
```

### testOGPToken()
Testet die Gültigkeit des OGP-Tokens.

```php
$serviceManager = new ServiceManager();
$tokenStatus = $serviceManager->testOGPToken();

if ($tokenStatus) {
    echo "OGP Token ist gültig!\n";
} else {
    echo "OGP Token ist ungültig!\n";
}
```

### OGPAPI($type, $url, $code = null)
Generische OGP API-Funktion für direkten Zugriff.

```php
$serviceManager = new ServiceManager();

// Server-Status abrufen
$serverStatus = $serviceManager->OGPAPI('post', 'server/status', ['remote_server_id' => 1]);

// Game-Server erstellen
$gameServerData = [
    'remote_server_id' => 1,
    'game_key' => 'csgo',
    'name' => 'Mein Server',
    'port' => 27015
];
$result = $serviceManager->OGPAPI('post', 'server/create', $gameServerData);

// RCON-Befehl senden
$rconData = [
    'ip' => '192.168.1.100',
    'port' => 27015,
    'mod_key' => 'csgo',
    'command' => 'say Hallo Welt!'
];
$result = $serviceManager->OGPAPI('post', 'server/rcon', $rconData);
```

## 🔍 Fehlerbehandlung

### API-Status prüfen

```php
$serviceManager = new ServiceManager();

// OGP API-Status prüfen
$apiCheck = $serviceManager->checkAPIEnabled('ogp');
if ($apiCheck !== true) {
    echo "OGP API Fehler: " . $apiCheck['message'] . "\n";
    echo "Lösung: " . $apiCheck['solution'] . "\n";
    exit;
}
```

### Token-Test

```php
$serviceManager = new ServiceManager();

// Token testen
$tokenStatus = $serviceManager->testOGPToken();
if ($tokenStatus) {
    echo "✅ OGP Token ist gültig\n";
} else {
    echo "❌ OGP Token ist ungültig\n";
    echo "Überprüfen Sie die Token-Konfiguration in config.inc.php\n";
    exit;
}
```

### Try-Catch Fehlerbehandlung

```php
try {
    $ogp = new OGPGet();
    $gameServers = $ogp->getGameServers();
    
    if (is_array($gameServers)) {
        foreach ($gameServers as $server) {
            echo "Game-Server: {$server['name']}\n";
        }
    } else {
        echo "Fehler beim Abrufen der Game-Server: " . $gameServers['message'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    error_log("OGP Error: " . $e->getMessage());
}
```

## 📝 Praktische Beispiele

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
    
    // Statistiken
    $onlineServers = array_filter($gameServers, function($server) { return $server['status'] === 'online'; });
    
    echo "Statistiken:\n";
    echo "Online Game-Server: " . count($onlineServers) . " / " . count($gameServers) . "\n";
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
    'rcon_password' => 'meinpasswort',
    'max_players' => 32,
    'tickrate' => 128,
    'map' => 'de_dust2'
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

### 3. Automatisches Server-Management

```php
<?php
require_once 'framework.php';

$serviceManager = new ServiceManager();

echo "=== Automatisches Server-Management ===\n\n";

// Game-Server-Templates definieren
$serverTemplates = [
    'csgo_competitive' => [
        'game_key' => 'csgo',
        'max_players' => 10,
        'tickrate' => 128,
        'map' => 'de_dust2',
        'description' => 'CS:GO Competitive Server'
    ],
    'csgo_casual' => [
        'game_key' => 'csgo',
        'max_players' => 32,
        'tickrate' => 64,
        'map' => 'de_dust2',
        'description' => 'CS:GO Casual Server'
    ],
    'minecraft_vanilla' => [
        'game_key' => 'minecraft',
        'max_players' => 20,
        'description' => 'Minecraft Vanilla Server'
    ]
];

// Neue Server erstellen
$newServers = [
    [
        'name' => 'CS:GO Comp #1',
        'template' => 'csgo_competitive',
        'port' => 27015,
        'remote_server_id' => 1
    ],
    [
        'name' => 'CS:GO Casual #1',
        'template' => 'csgo_casual',
        'port' => 27016,
        'remote_server_id' => 1
    ],
    [
        'name' => 'Minecraft #1',
        'template' => 'minecraft_vanilla',
        'port' => 25565,
        'remote_server_id' => 2
    ]
];

foreach ($newServers as $serverInfo) {
    $template = $serverTemplates[$serverInfo['template']];
    
    $gameServerData = array_merge($template, [
        'remote_server_id' => $serverInfo['remote_server_id'],
        'name' => $serverInfo['name'],
        'port' => $serverInfo['port']
    ]);
    
    echo "Erstelle Game-Server: {$serverInfo['name']}\n";
    
    $result = $serviceManager->createOGPGameServer($gameServerData);
    
    if ($result) {
        echo "✅ Game-Server {$serverInfo['name']} erfolgreich erstellt\n";
        
        // Server starten
        sleep(5);
        $startResult = $serviceManager->OGPAPI('post', 'server/start', [
            'server_id' => $result['server_id']
        ]);
        
        if ($startResult) {
            echo "🚀 Game-Server {$serverInfo['name']} gestartet\n";
        }
        
        // Log erstellen
        $db = Database::getInstance();
        $db->logAction(
            'Game-Server erstellt',
            "Game-Server {$serverInfo['name']} mit Template {$serverInfo['template']} erstellt",
            'success'
        );
    } else {
        echo "❌ Fehler beim Erstellen von Game-Server {$serverInfo['name']}\n";
    }
    
    echo "---\n";
}
?>
```

### 4. RCON-Befehls-Management

```php
<?php
require_once 'framework.php';

$serviceManager = new ServiceManager();

echo "=== RCON-Befehls-Management ===\n\n";

// Game-Server abrufen
$gameServers = $serviceManager->getOGPGameServers();

// RCON-Befehle definieren
$rconCommands = [
    'csgo' => [
        'say Server wird in 5 Minuten neu gestartet!',
        'sv_cheats 0',
        'mp_autoteambalance 1',
        'mp_limitteams 2'
    ],
    'minecraft' => [
        'say Server wird in 5 Minuten neu gestartet!',
        'save-all',
        'save-off'
    ]
];

foreach ($gameServers as $server) {
    if ($server['status'] === 'online') {
        echo "Sende RCON-Befehle an: {$server['name']}\n";
        
        $gameKey = $server['game_key'];
        if (isset($rconCommands[$gameKey])) {
            foreach ($rconCommands[$gameKey] as $command) {
                echo "  Befehl: {$command}\n";
                
                $result = $serviceManager->sendOGPRconCommand(
                    $server['ip'],
                    $server['port'],
                    $gameKey,
                    $command
                );
                
                if ($result) {
                    echo "    ✅ Befehl erfolgreich gesendet\n";
                } else {
                    echo "    ❌ Fehler beim Senden des Befehls\n";
                }
                
                sleep(1); // Kurze Pause zwischen Befehlen
            }
        }
        
        echo "---\n";
    }
}
?>
```

### 5. Server-Monitoring

```php
<?php
require_once 'framework.php';

$serviceManager = new ServiceManager();

echo "=== OGP Server-Monitoring ===\n\n";

// Remote-Server abrufen
$servers = $serviceManager->OGPAPI('post', 'server/list');

if (is_array($servers)) {
    foreach ($servers as $server) {
        echo "Server: {$server['name']} - IP: {$server['ip']}\n";
        
        // Server-Status abrufen
        $status = $serviceManager->OGPAPI('post', 'server/status', [
            'remote_server_id' => $server['remote_server_id']
        ]);
        
        if (is_array($status)) {
            $statusIcon = ($status['status'] === 'online') ? '🟢' : '🔴';
            echo "  {$statusIcon} Status: {$status['status']}\n";
            
            if (isset($status['cpu'])) {
                echo "  CPU: {$status['cpu']}%\n";
            }
            
            if (isset($status['memory'])) {
                echo "  Memory: {$status['memory']}%\n";
            }
            
            if (isset($status['uptime'])) {
                echo "  Uptime: {$status['uptime']}\n";
            }
        }
        
        // Game-Server auf diesem Server abrufen
        $gameServers = $serviceManager->OGPAPI('post', 'server/gameservers', [
            'remote_server_id' => $server['remote_server_id']
        ]);
        
        if (is_array($gameServers)) {
            echo "  Game-Server: " . count($gameServers) . "\n";
            foreach ($gameServers as $gameServer) {
                $gameStatusIcon = ($gameServer['status'] === 'online') ? '🟢' : '🔴';
                echo "    {$gameStatusIcon} {$gameServer['name']} ({$gameServer['game_key']})\n";
            }
        }
        
        echo "---\n";
    }
}
?>
```

## 🔗 Nützliche Links

- [Open Game Panel](https://www.opengamepanel.org/)
- [OGP Dokumentation](https://www.opengamepanel.org/wiki/)
- [OGP Forum](https://www.opengamepanel.org/forum/)

## ❗ Wichtige Hinweise

1. **API-Token**: Verwenden Sie einen gültigen API-Token für die Authentifizierung
2. **RCON-Passwörter**: Verwenden Sie sichere RCON-Passwörter
3. **Ports**: Stellen Sie sicher, dass die benötigten Ports verfügbar sind
4. **Server-Ressourcen**: Überwachen Sie CPU- und Memory-Nutzung
5. **Backup**: Erstellen Sie regelmäßig Backups Ihrer Game-Server
6. **Sicherheit**: Verwenden Sie HTTPS für die OGP-Verbindung
7. **Rate Limiting**: Beachten Sie mögliche API-Rate-Limits 