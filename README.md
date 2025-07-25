# 🚀 Server Management Framework

Ein professionelles PHP-Framework für die Verwaltung von Proxmox VMs, ISPConfig Websites und OVH Services.

[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D7.4-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Framework](https://img.shields.io/badge/Framework-Standalone-orange.svg)]()

## 📋 Features

- **🖥️ Proxmox VE Integration** - Vollständige VM-Verwaltung (Erstellen, Starten, Stoppen, Klonen)
- **🌐 ISPConfig Integration** - Website, Datenbank und E-Mail Management
- **🔗 OVH API Integration** - Domain, VPS und Dedicated Server Verwaltung
- **📊 Admin Dashboard** - Moderne Web-Oberfläche mit Real-time Updates
- **🔐 Authentication Testing** - Umfassende API-Verbindungstests
- **📝 Activity Logging** - Vollständige Protokollierung aller Aktionen
- **🎯 OOP Design** - Saubere, modulare Architektur
- **🔌 Einzelne Endpunkte** - Jede API-Methode einzeln abrufbar
- **🎨 Bootstrap 5.3.2** - Moderne, responsive Benutzeroberfläche
- **⚡ jQuery 3.7.1** - Optimierte JavaScript-Funktionalität

## 🚀 Quick Start

```bash
# Repository klonen
git clone https://github.com/teris/server-management-framework.git
cd server-management-framework

# Konfiguration anpassen
nano config/config.inc.php

# Datenbank einrichten
mysql -u root -p < database-structure.sql

# Webserver starten (oder auf Apache/Nginx deployen)
php -S localhost:8000
```

## ⚙️ Installation

### Voraussetzungen

- **PHP >= 7.4** mit Extensions: `curl`, `soap`, `pdo_mysql`, `json`
- **MySQL >= 5.7** oder **MariaDB >= 10.2**
- **Apache/Nginx** Webserver
- **Zugang zu Proxmox VE, ISPConfig und/oder OVH APIs**

### Schritt-für-Schritt Installation

1. **Repository klonen:**
   ```bash
   git clone https://github.com/teris/server-management-framework.git
   cd server-management-framework
   ```

2. **Composer Dependencies (optional):**
   ```bash
   composer install
   ```

3. **Konfiguration:**
   ```bash
   nano config/config.inc.php
   # Editieren Sie die Konfiguration mit Ihren API-Credentials
   ```

4. **Datenbank einrichten:**
   ```bash
   mysql -u root -p
   CREATE DATABASE server_management;
   USE server_management;
   SOURCE database-structure.sql;
   ```

5. **Webserver konfigurieren:**
   - **Apache:** DocumentRoot auf das Projektverzeichnis setzen
   - **Nginx:** Root auf das Projektverzeichnis setzen
   - **PHP Dev Server:** `php -S localhost:8000`

6. **Permissions setzen:**
   ```bash
   chmod 755 ./
   chmod 644 config/config.inc.php
   ```

## 🔧 Konfiguration

### API-Credentials

Tragen Sie Ihre API-Credentials in `config/config.inc.php` ein:

```php
// Proxmox Configuration
const PROXMOX_HOST = 'https://your-proxmox-host:8006';
const PROXMOX_USER = 'root@pam';
const PROXMOX_PASSWORD = 'your_password';

// ISPConfig Configuration
const ISPCONFIG_HOST = 'https://your-ispconfig-host:8080';
const ISPCONFIG_USER = 'admin';
const ISPCONFIG_PASSWORD = 'your_password';

// OVH Configuration
const OVH_APPLICATION_KEY = 'your_app_key';
const OVH_APPLICATION_SECRET = 'your_app_secret';
const OVH_CONSUMER_KEY = 'your_consumer_key';
```

### OVH Consumer Key erstellen

1. Besuchen Sie: https://eu.api.ovh.com/createToken/
2. Setzen Sie diese Rechte:
   ```
   GET /*
   POST /*
   PUT /*
   DELETE /*
   ```
3. Kopieren Sie den Consumer Key in Ihre Konfiguration

## 🧪 Tests

### API-Verbindung testen

```bash
# Alle APIs testen
php auth_handler.php

# Einzelne APIs testen
php auth_handler.php proxmox
php auth_handler.php ispconfig
php auth_handler.php ovh
```

### Debug-Modus

```bash
# Debug-Interface öffnen
php debug.php
```

## 🎯 Verwendung

### Web Interface

1. Öffnen Sie das Web-Interface in Ihrem Browser
2. Navigieren Sie zwischen den Tabs:
   - **📊 Admin Dashboard** - Übersicht aller Ressourcen
   - **🖥️ Proxmox VM** - VM-Verwaltung
   - **🌐 ISPConfig** - Website-Management
   - **🔗 OVH** - Domain & VPS Verwaltung
   - **🔐 Auth Status** - API-Verbindungstests

### Programmatische Verwendung

```php
require_once 'framework.php';

$serviceManager = new ServiceManager();

// VMs verwalten
$vms = $serviceManager->getProxmoxVMs();
$serviceManager->createProxmoxVM($vmData);
$serviceManager->controlProxmoxVM('pve', '100', 'start');

// Websites verwalten
$websites = $serviceManager->getISPConfigWebsites();
$serviceManager->createISPConfigWebsite($websiteData);

// Domains verwalten
$domains = $serviceManager->getOVHDomains();
$serviceManager->orderOVHDomain('example.com', 1);
```

## 📚 Dokumentation

- **[API Dokumentation](how_to_use.md)** - Vollständige API-Referenz und Verwendungsbeispiele
- **[Bootstrap Migration](BOOTSTRAP_MIGRATION.md)** - Details zur UI-Migration
- **[Contributing Guide](CONTRIBUTING.md)** - Richtlinien für Beiträge

## 🏗️ Architektur

```
Server-Management-Framework/
├── assets/                 # CSS, JS und andere Assets
├── config/                 # Konfigurationsdateien
├── core/                   # Kern-Klassen (AdminCore, AdminHandler)
├── debug/                  # Debug-Tools und Utilities
├── module/                 # Modulare Komponenten
│   ├── admin/             # Admin-Dashboard
│   ├── proxmox/           # Proxmox-Integration
│   ├── ispconfig/         # ISPConfig-Integration
│   ├── ovh/               # OVH-Integration
│   └── ...                # Weitere Module
├── framework.php          # Haupt-Framework-Datei
├── index.php              # Web-Interface
└── auth_handler.php       # API-Authentifizierung
```

## 🤝 Contributing

Beiträge sind willkommen! Bitte lesen Sie [CONTRIBUTING.md](CONTRIBUTING.md) für Details.

1. **Fork** das Repository
2. **Erstellen** Sie einen Feature-Branch (`git checkout -b feature/amazing-feature`)
3. **Commit** Ihre Änderungen (`git commit -m 'Add amazing feature'`)
4. **Push** zum Branch (`git push origin feature/amazing-feature`)
5. **Öffnen** Sie eine Pull Request

## 🐛 Bug Reports

Bitte verwenden Sie die [GitHub Issues](https://github.com/teris/server-management-framework/issues) für Bug Reports und Feature Requests.

**Bug Report Template:**
- **Beschreibung:** Was ist passiert?
- **Erwartetes Verhalten:** Was sollte passieren?
- **Schritte zur Reproduktion:** Wie kann der Fehler reproduziert werden?
- **Environment:** PHP Version, OS, etc.
- **Logs:** Relevante Error-Logs

## 📋 Roadmap

- [x] **v2.0** - REST API für externe Integration
- [x] **v2.1** - Backup & Restore Funktionen
- [X] **v2.2** - Monitoring & Alerting
- [ ] **v2.3** - Multi-User Support mit Rollen
- [x] **v2.4** - CLI Tools (update.php)
- [X] **v2.5** - Plugin System
- [x] **v2.6** - Use Framework as Single without Interface
- [x] **v2.7** - Bootstrap 5.3.2 Migration
- [x] **V2.8** - Databasemodus

## 🔒 Sicherheit

- **API-Credentials** werden sicher gespeichert
- **HTTPS** wird für alle API-Aufrufe verwendet
- **Input Validation** für alle Benutzereingaben
- **SQL Injection** Schutz durch PDO Prepared Statements
- **Session Management** mit sicheren Cookies

**Sicherheitslücken melden:** Bitte erstellen Sie ein privates GitHub Issue.

## 📄 Lizenz

Dieses Projekt steht unter der MIT-Lizenz - siehe [LICENSE](LICENSE) für Details.

## 👥 Autoren

- **Teris** - *Initial work* - [GitHub](https://github.com/teris)

Siehe auch die Liste der [Contributors](https://github.com/teris/server-management-framework/contributors).

## 🙏 Danksagungen

- **Proxmox VE Team** - Für die ausgezeichnete Virtualisierungsplattform
- **ISPConfig Team** - Für das umfassende Hosting-Control-Panel
- **OVH** - Für die robuste API
- **PHP Community** - Für die großartigen Tools und Libraries
- **Bootstrap Team** - Für das moderne CSS-Framework

## 📊 Status

![GitHub last commit](https://img.shields.io/github/last-commit/teris/server-management-framework)
![GitHub issues](https://img.shields.io/github/issues/teris/server-management-framework)
![GitHub pull requests](https://img.shields.io/github/issues-pr/teris/server-management-framework)
![GitHub stars](https://img.shields.io/github/stars/teris/server-management-framework)

---

**Happy Server Managing! 🚀**
