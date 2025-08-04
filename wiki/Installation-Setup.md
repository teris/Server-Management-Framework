# Installation & Setup

Diese Anleitung führt Sie durch die Installation und Konfiguration des Server Management Frameworks.

## 📋 Systemanforderungen

### PHP-Anforderungen
- **PHP Version**: 7.4 oder höher
- **PHP Extensions**:
  - `curl` - Für API-Kommunikation
  - `json` - Für JSON-Verarbeitung
  - `soap` - Für ISPConfig API (optional)
  - `pdo_mysql` - Für Datenbankverbindungen
  - `openssl` - Für sichere Verbindungen

### Datenbank
- **MySQL**: 5.7 oder höher
- **MariaDB**: 10.2 oder höher

### Server
- **Webserver**: Apache 2.4+ oder Nginx
- **Betriebssystem**: Linux, Windows, macOS

## 🔧 Installation

### 1. Repository klonen

```bash
git clone https://github.com/your-repo/server-management-framework.git
cd server-management-framework
```

### 2. PHP Extensions installieren

#### Ubuntu/Debian
```bash
sudo apt update
sudo apt install php-curl php-json php-soap php-mysql php-openssl
```

#### CentOS/RHEL
```bash
sudo yum install php-curl php-json php-soap php-mysql php-openssl
```

#### Windows
Entfernen Sie die Kommentare in der `php.ini`:
```ini
extension=curl
extension=json
extension=soap
extension=pdo_mysql
extension=openssl
```

#### macOS (Homebrew)
```bash
brew install php@8.x
# SOAP ist standardmäßig enthalten
```

### 3. Datenbank einrichten

#### Datenbank erstellen
```sql
CREATE DATABASE framework_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'framework_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON framework_db.* TO 'framework_user'@'localhost';
FLUSH PRIVILEGES;
```

#### Tabellen erstellen
```sql
USE framework_db;

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(255) NOT NULL,
  `details` text,
  `status` enum('success','error','warning') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `api_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `api_name` varchar(50) NOT NULL,
  `endpoint` varchar(255) NOT NULL,
  `method` varchar(10) NOT NULL,
  `success` tinyint(1) NOT NULL,
  `response_time` float DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_api_name` (`api_name`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## ⚙️ Konfiguration

### 1. Konfigurationsdatei erstellen

Erstellen Sie die Datei `config/config.inc.php`:

```php
<?php
class Config {
    // ===== DATENBANK KONFIGURATION =====
    const DB_HOST = 'localhost';
    const DB_NAME = 'framework_db';
    const DB_USER = 'framework_user';
    const DB_PASS = 'your_password';
    const DB_CHARSET = 'utf8mb4';

    // ===== PROXMOX KONFIGURATION =====
    const PROXMOX_USEING = true;  // true/false
    const PROXMOX_HOST = 'https://pve.example.com:8006';
    const PROXMOX_USER = 'root@pam';
    const PROXMOX_PASSWORD = 'your_proxmox_password';
    const PROXMOX_REALM = 'pam';  // Standard: pam

    // ===== ISPCONFIG KONFIGURATION =====
    const ISPCONFIG_USEING = true;  // true/false
    const ISPCONFIG_HOST = 'https://ispconfig.example.com:8080';
    const ISPCONFIG_USER = 'admin';
    const ISPCONFIG_PASSWORD = 'your_ispconfig_password';
    const ISPCONFIG_CLIENT_ID = 1;  // Standard: 1

    // ===== OVH KONFIGURATION =====
    const OVH_USEING = true;  // true/false
    const OVH_APPLICATION_KEY = 'your_ovh_app_key';
    const OVH_APPLICATION_SECRET = 'your_ovh_app_secret';
    const OVH_CONSUMER_KEY = 'your_ovh_consumer_key';
    const OVH_ENDPOINT = 'https://eu.api.ovh.com/1.0';  // eu, ca, us

    // ===== OGP KONFIGURATION =====
    const OGP_USEING = true;  // true/false
    const OGP_HOST = 'https://ogp.example.com';
    const OGP_USER = 'admin';
    const OGP_PASSWORD = 'your_ogp_password';
    const OGP_TOKEN = 'your_ogp_token';

    // ===== ALLGEMEINE KONFIGURATION =====
    const DEBUG_MODE = false;  // true/false
    const LOG_LEVEL = 'info';  // debug, info, warning, error
    const TIMEZONE = 'Europe/Berlin';
}
?>
```

### 2. API-Zugangsdaten konfigurieren

#### Proxmox
1. Melden Sie sich bei Ihrem Proxmox-Server an
2. Gehen Sie zu `Datacenter` → `Users`
3. Erstellen Sie einen API-Benutzer oder verwenden Sie `root@pam`
4. Notieren Sie sich Host, Benutzer und Passwort

#### ISPConfig
1. Melden Sie sich bei ISPConfig an
2. Gehen Sie zu `System` → `Remote Users`
3. Erstellen Sie einen Remote-API-Benutzer
4. Notieren Sie sich Host, Benutzer und Passwort

#### OVH
1. Gehen Sie zu [OVH API Keys](https://api.ovh.com/createToken/)
2. Erstellen Sie eine neue API-Anwendung
3. Notieren Sie sich Application Key, Secret und Consumer Key

#### OGP
1. Melden Sie sich bei OGP an
2. Gehen Sie zu `Settings` → `API`
3. Generieren Sie einen API-Token
4. Notieren Sie sich Host, Benutzer und Token

## 🧪 Installation testen

### 1. Test-Skript erstellen

Erstellen Sie `test_installation.php`:

```php
<?php
require_once 'framework.php';

echo "=== Framework Installation Test ===\n\n";

// Datenbank-Test
try {
    $db = Database::getInstance();
    echo "✅ Datenbankverbindung erfolgreich\n";
    
    // Test-Log erstellen
    $db->logAction('Installation Test', 'Framework wurde erfolgreich installiert', 'success');
    echo "✅ Logging-System funktioniert\n";
    
} catch (Exception $e) {
    echo "❌ Datenbankfehler: " . $e->getMessage() . "\n";
}

// ServiceManager-Test
try {
    $serviceManager = new ServiceManager();
    echo "✅ ServiceManager initialisiert\n";
    
    // API-Status prüfen
    $apis = ['proxmox', 'ispconfig', 'ovh', 'ogp'];
    foreach ($apis as $api) {
        $status = $serviceManager->checkAPIEnabled($api);
        if ($status === true) {
            echo "✅ {$api} API ist aktiviert\n";
        } else {
            echo "⚠️  {$api} API ist deaktiviert oder fehlerhaft\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ ServiceManager-Fehler: " . $e->getMessage() . "\n";
}

echo "\n=== Test abgeschlossen ===\n";
?>
```

### 2. Test ausführen

```bash
php test_installation.php
```

## 🔒 Sicherheitskonfiguration

### 1. Dateiberechtigungen

```bash
# Konfigurationsdatei schützen
chmod 600 config/config.inc.php

# Logs-Verzeichnis
mkdir logs
chmod 755 logs
chown www-data:www-data logs
```

### 2. Web-Server-Konfiguration

#### Apache (.htaccess)
```apache
# Konfigurationsdatei schützen
<Files "config.inc.php">
    Order allow,deny
    Deny from all
</Files>

# Framework-Dateien schützen
<Files "framework.php">
    Order allow,deny
    Allow from all
</Files>
```

#### Nginx
```nginx
# Konfigurationsdatei schützen
location ~ ^/config/.*\.php$ {
    deny all;
}

# Framework-Dateien erlauben
location ~ ^/framework\.php$ {
    allow all;
}
```

## 🚀 Nächste Schritte

Nach erfolgreicher Installation können Sie:

1. [API Integration](API-Integration) konfigurieren
2. [Beispiele & Tutorials](Beispiele-Tutorials) durchgehen
3. [Eigene Module](Modul-System) erstellen
4. [Framework Komponenten](Framework-Komponenten) verstehen

## ❗ Häufige Probleme

### PHP SOAP Extension fehlt
```bash
# Ubuntu/Debian
sudo apt install php-soap

# CentOS/RHEL
sudo yum install php-soap

# Windows: php.ini bearbeiten
extension=soap
```

### Datenbankverbindung fehlschlägt
- Überprüfen Sie Host, Benutzer und Passwort
- Stellen Sie sicher, dass der Benutzer die richtigen Rechte hat
- Prüfen Sie, ob MySQL/MariaDB läuft

### API-Verbindungen funktionieren nicht
- Überprüfen Sie die API-Zugangsdaten
- Stellen Sie sicher, dass die APIs aktiviert sind (`USEING = true`)
- Prüfen Sie Firewall-Einstellungen
- Testen Sie die API-Endpunkte manuell

## 📞 Support

Bei Problemen:

1. Überprüfen Sie die [Issues](https://github.com/your-repo/issues)
2. Erstellen Sie ein neues Issue mit detaillierter Fehlerbeschreibung
3. Fügen Sie Logs und Konfigurationsdetails hinzu 