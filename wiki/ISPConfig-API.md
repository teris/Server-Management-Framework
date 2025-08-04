# ISPConfig API Integration

Die ISPConfig API Integration ermöglicht die Verwaltung von Websites, Datenbanken und E-Mail-Accounts über das ISPConfig Web Hosting Control Panel.

## ⚠️ Wichtiger Hinweis

**ISPConfig benötigt die PHP SOAP-Erweiterung für die API-Kommunikation.**

### PHP SOAP-Erweiterung installieren

#### Ubuntu/Debian
```bash
sudo apt update
sudo apt install php-soap
sudo systemctl restart apache2
```

#### CentOS/RHEL
```bash
sudo yum install php-soap
sudo systemctl restart httpd
```

#### Windows
Entfernen Sie das Semikolon vor `extension=soap` in der `php.ini`:
```ini
extension=soap
```

#### macOS (Homebrew)
```bash
brew install php@8.x
# SOAP ist standardmäßig enthalten
```

## 🔧 Konfiguration

### 1. ISPConfig-Zugangsdaten einrichten

Fügen Sie folgende Konfiguration in `config/config.inc.php` hinzu:

```php
// ===== ISPCONFIG KONFIGURATION =====
const ISPCONFIG_USEING = true;
const ISPCONFIG_HOST = 'https://ispconfig.example.com:8080';
const ISPCONFIG_USER = 'admin';
const ISPCONFIG_PASSWORD = 'your_ispconfig_password';
const ISPCONFIG_CLIENT_ID = 1;  // Standard: 1
```

### 2. API-Benutzer erstellen

1. Melden Sie sich bei ISPConfig an
2. Gehen Sie zu `System` → `Remote Users`
3. Erstellen Sie einen neuen Remote-API-Benutzer
4. Notieren Sie sich Host, Benutzer und Passwort

## 📚 API-Klassen

### ISPConfigGet Klasse

Lese-Operationen für ISPConfig.

#### getWebsites($filter = [])
Holt alle Websites.

```php
$ispconfig = new ISPConfigGet();

// Alle Websites abrufen
$websites = $ispconfig->getWebsites();

// Websites mit Filter abrufen
$filter = ['active' => 'y'];
$activeWebsites = $ispconfig->getWebsites($filter);

foreach ($websites as $website) {
    echo "Website: {$website->domain} - IP: {$website->ip_address}\n";
    echo "Status: " . ($website->active ? 'Aktiv' : 'Inaktiv') . "\n";
    echo "Quota: {$website->hd_quota} MB\n";
    echo "---\n";
}
```

#### getDatabases($filter = [])
Holt alle Datenbanken.

```php
$ispconfig = new ISPConfigGet();
$databases = $ispconfig->getDatabases();

foreach ($databases as $database) {
    echo "Datenbank: {$database['database_name']}\n";
    echo "Benutzer: {$database['database_user']}\n";
    echo "Größe: {$database['database_size']} MB\n";
    echo "---\n";
}
```

#### getEmailAccounts($filter = [])
Holt alle E-Mail-Accounts.

```php
$ispconfig = new ISPConfigGet();
$emailAccounts = $ispconfig->getEmailAccounts();

foreach ($emailAccounts as $account) {
    echo "E-Mail: {$account['email']}\n";
    echo "Domain: {$account['domain']}\n";
    echo "Quota: {$account['quota']} MB\n";
    echo "---\n";
}
```

#### getDomains()
Holt alle Domains.

```php
$ispconfig = new ISPConfigGet();
$domains = $ispconfig->getDomains();

foreach ($domains as $domain) {
    echo "Domain: {$domain['domain']}\n";
    echo "Status: {$domain['active']}\n";
    echo "---\n";
}
```

### ISPConfigPost Klasse

Schreib-Operationen für ISPConfig.

#### createWebsite($websiteData)
Erstellt eine neue Website.

**Wichtige Parameter:**
- `ip` (Pflicht): IP-Adresse für die Website
- `domain` (Pflicht): Domain-Name der Website
- `user` (Pflicht): System-Benutzer für die Website
- `group` (Pflicht): System-Gruppe für die Website
- `quota` (Pflicht): Speicherplatz in MB
- `traffic` (Pflicht): Traffic-Limit in MB

```php
$websiteData = [
    'ip' => '192.168.1.100',
    'domain' => 'example.com',
    'user' => 'webuser',
    'group' => 'webgroup',
    'quota' => 1000,
    'traffic' => 10000,
    'ssl' => true,
    'php' => 'php-fpm-8.1'
];

$ispconfig = new ISPConfigPost();
$result = $ispconfig->createWebsite($websiteData);

if ($result) {
    echo "Website erfolgreich erstellt!\n";
} else {
    echo "Fehler beim Erstellen der Website\n";
}
```

#### createDatabase($databaseData)
Erstellt eine neue Datenbank.

```php
$databaseData = [
    'database_name' => 'meine_datenbank',
    'database_user' => 'dbuser',
    'database_password' => 'sicheres_passwort',
    'database_quota' => 100
];

$ispconfig = new ISPConfigPost();
$result = $ispconfig->createDatabase($databaseData);

if ($result) {
    echo "Datenbank erfolgreich erstellt!\n";
}
```

#### createEmailAccount($emailData)
Erstellt einen neuen E-Mail-Account.

```php
$emailData = [
    'email' => 'info@example.com',
    'password' => 'sicheres_passwort',
    'quota' => 100,
    'redirect' => false
];

$ispconfig = new ISPConfigPost();
$result = $ispconfig->createEmailAccount($emailData);

if ($result) {
    echo "E-Mail-Account erfolgreich erstellt!\n";
}
```

#### updateWebsite($websiteId, $websiteData)
Aktualisiert eine bestehende Website.

```php
$websiteData = [
    'quota' => 2000,
    'traffic' => 20000,
    'ssl' => true
];

$ispconfig = new ISPConfigPost();
$result = $ispconfig->updateWebsite(123, $websiteData);

if ($result) {
    echo "Website erfolgreich aktualisiert!\n";
}
```

#### deleteWebsite($websiteId)
Löscht eine Website.

```php
$ispconfig = new ISPConfigPost();
$result = $ispconfig->deleteWebsite(123);

if ($result) {
    echo "Website erfolgreich gelöscht!\n";
}
```

## 🔧 ServiceManager Integration

### getISPConfigWebsites()
Holt alle Websites über den ServiceManager.

```php
$serviceManager = new ServiceManager();
$websites = $serviceManager->getISPConfigWebsites();

foreach ($websites as $website) {
    echo "Website: {$website->domain} - IP: {$website->ip_address}\n";
}
```

### createISPConfigWebsite($websiteData)
Erstellt eine Website über den ServiceManager.

```php
$serviceManager = new ServiceManager();

$websiteData = [
    'ip' => '192.168.1.100',
    'domain' => 'example.com',
    'user' => 'webuser',
    'group' => 'webgroup',
    'quota' => 1000,
    'traffic' => 10000
];

$result = $serviceManager->createISPConfigWebsite($websiteData);
```

### IspconfigAPI($type, $url, $code = null)
Generische ISPConfig API-Funktion für direkten Zugriff.

```php
$serviceManager = new ServiceManager();

// Website erstellen
$websiteData = [
    'server_id' => 1,
    'ip_address' => '192.168.1.100',
    'domain' => 'example.com',
    'type' => 'vhost',
    'active' => 'y',
    'hd_quota' => 1000,
    'traffic_quota' => 10000
];
$result = $serviceManager->IspconfigAPI('post', 'sites_web_domain', $websiteData);

// Websites abrufen
$websites = $serviceManager->IspconfigAPI('get', 'sites_web_domain');

// Website aktualisieren
$updateData = ['hd_quota' => 2000];
$result = $serviceManager->IspconfigAPI('put', 'sites_web_domain', $updateData);
```

## 📊 Datenmodelle

### Website Klasse

```php
class Website {
    public $domain_id;      // Domain ID
    public $domain;         // Domain Name
    public $ip_address;     // IP Address
    public $system_user;    // System User
    public $system_group;   // System Group
    public $active;         // Active Status
    public $hd_quota;       // Disk Quota in MB
    public $traffic_quota;  // Traffic Quota in MB
    public $document_root;  // Document Root
    public $ssl_enabled;    // SSL Status
    public $php_version;    // PHP Version
    public $created_at;     // Creation Date
}
```

## 🔍 Fehlerbehandlung

### API-Status prüfen

```php
$serviceManager = new ServiceManager();

// ISPConfig API-Status prüfen
$apiCheck = $serviceManager->checkAPIEnabled('ispconfig');
if ($apiCheck !== true) {
    echo "ISPConfig API Fehler: " . $apiCheck['message'] . "\n";
    echo "Lösung: " . $apiCheck['solution'] . "\n";
    exit;
}
```

### SOAP-Verbindung testen

```php
try {
    $ispconfig = new ISPConfigGet();
    $websites = $ispconfig->getWebsites();
    echo "✅ ISPConfig SOAP-Verbindung erfolgreich\n";
} catch (SoapFault $e) {
    echo "❌ SOAP-Fehler: " . $e->getMessage() . "\n";
    echo "Stellen Sie sicher, dass die PHP SOAP-Erweiterung installiert ist.\n";
} catch (Exception $e) {
    echo "❌ Allgemeiner Fehler: " . $e->getMessage() . "\n";
}
```

### Try-Catch Fehlerbehandlung

```php
try {
    $ispconfig = new ISPConfigGet();
    $websites = $ispconfig->getWebsites();
    
    if (is_array($websites)) {
        foreach ($websites as $website) {
            echo "Website: {$website->domain}\n";
        }
    } else {
        echo "Fehler beim Abrufen der Websites: " . $websites['message'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    error_log("ISPConfig Error: " . $e->getMessage());
}
```

## 📝 Praktische Beispiele

### 1. Website-Monitoring

```php
<?php
require_once 'framework.php';

$serviceManager = new ServiceManager();

echo "=== ISPConfig Website-Monitoring ===\n\n";

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
    
    // Statistiken
    $activeWebsites = array_filter($websites, function($site) { return $site->active; });
    $totalQuota = array_sum(array_column($websites, 'hd_quota'));
    $totalTraffic = array_sum(array_column($websites, 'traffic_quota'));
    
    echo "Statistiken:\n";
    echo "Aktive Websites: " . count($activeWebsites) . " / " . count($websites) . "\n";
    echo "Gesamte Quota: {$totalQuota} MB\n";
    echo "Gesamtes Traffic-Limit: {$totalTraffic} MB\n";
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

### 3. Website-Backup-System

```php
<?php
require_once 'framework.php';

$serviceManager = new ServiceManager();

echo "=== Website-Backup-System ===\n\n";

// Alle Websites abrufen
$websites = $serviceManager->getISPConfigWebsites();

foreach ($websites as $website) {
    echo "Backup für Website: {$website->domain}\n";
    
    // Website-Konfiguration sichern
    $backupData = [
        'domain' => $website->domain,
        'ip_address' => $website->ip_address,
        'system_user' => $website->system_user,
        'system_group' => $website->system_group,
        'hd_quota' => $website->hd_quota,
        'traffic_quota' => $website->traffic_quota,
        'ssl_enabled' => $website->ssl_enabled,
        'backup_date' => date('Y-m-d H:i:s')
    ];
    
    // Backup in Datei speichern
    $backupFile = "backups/website_{$website->domain_id}_" . date('Y-m-d_H-i-s') . ".json";
    
    if (!is_dir('backups')) {
        mkdir('backups', 0755, true);
    }
    
    if (file_put_contents($backupFile, json_encode($backupData, JSON_PRETTY_PRINT))) {
        echo "✅ Backup für {$website->domain} erstellt: {$backupFile}\n";
        
        // Backup-Log erstellen
        $db = Database::getInstance();
        $db->logAction(
            'Website Backup',
            "Backup für Website {$website->domain} erstellt: {$backupFile}",
            'success'
        );
    } else {
        echo "❌ Fehler beim Backup von {$website->domain}\n";
    }
    
    echo "---\n";
}

echo "Backup-Prozess abgeschlossen!\n";
?>
```

### 4. SSL-Zertifikat-Management

```php
<?php
require_once 'framework.php';

$serviceManager = new ServiceManager();

echo "=== SSL-Zertifikat-Management ===\n\n";

// Alle Websites abrufen
$websites = $serviceManager->getISPConfigWebsites();

foreach ($websites as $website) {
    echo "Website: {$website->domain}\n";
    echo "SSL Status: " . ($website->ssl_enabled ? 'Aktiviert' : 'Deaktiviert') . "\n";
    
    if (!$website->ssl_enabled) {
        echo "Aktiviere SSL für {$website->domain}...\n";
        
        $sslData = [
            'ssl' => true,
            'ssl_cert' => '',  // Automatisches Let's Encrypt Zertifikat
            'ssl_key' => '',
            'ssl_ca' => ''
        ];
        
        $result = $serviceManager->IspconfigAPI('put', "sites_web_domain/{$website->domain_id}", $sslData);
        
        if ($result) {
            echo "✅ SSL für {$website->domain} aktiviert\n";
        } else {
            echo "❌ Fehler beim Aktivieren von SSL für {$website->domain}\n";
        }
    }
    
    echo "---\n";
}
?>
```

## 🔗 Nützliche Links

- [ISPConfig Dokumentation](https://www.ispconfig.org/documentation/)
- [PHP SOAP Dokumentation](https://www.php.net/manual/de/book.soap.php)
- [ISPConfig Forum](https://www.ispconfig.org/forum/)

## ❗ Wichtige Hinweise

1. **SOAP-Erweiterung**: Stellen Sie sicher, dass die PHP SOAP-Erweiterung installiert ist
2. **SSL-Zertifikate**: Verwenden Sie HTTPS für die ISPConfig-Verbindung
3. **Benutzerrechte**: Der API-Benutzer benötigt entsprechende Rechte
4. **Backup**: Erstellen Sie regelmäßig Backups Ihrer Websites
5. **Monitoring**: Überwachen Sie Quota- und Traffic-Nutzung
6. **Sicherheit**: Verwenden Sie starke Passwörter und sichere Verbindungen 