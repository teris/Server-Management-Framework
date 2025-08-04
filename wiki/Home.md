# Server Management Framework Wiki

Willkommen zur Dokumentation des modularen Server-Management-Frameworks für Proxmox, ISPConfig, OVH API und OGP Integration.

## 🚀 Übersicht

Das Framework.php ist ein umfassendes Server Management Framework, das die Integration verschiedener APIs ermöglicht:

- **Proxmox**: Virtualisierung und Container-Management
- **ISPConfig**: Webhosting und E-Mail-Management  
- **OVH**: Cloud-Services, Domains und Virtual MAC
- **OGP**: Game Server Management

## 📚 Dokumentation

### [Installation & Setup](Installation-Setup)
- Systemanforderungen
- Installation
- Konfiguration
- Datenbank-Setup

### [API Integration](API-Integration)
- [Proxmox API](Proxmox-API)
- [ISPConfig API](ISPConfig-API)
- [OVH API](OVH-API)
- [OGP API](OGP-API)

### [Framework Komponenten](Framework-Komponenten)
- Database Klasse
- DataMapper
- BaseAPI
- ServiceManager

### [Beispiele & Tutorials](Beispiele-Tutorials)
- VM-Management
- Website-Management
- Virtual MAC-Management
- Game-Server-Management

### [Modul-System](Modul-System)
- Modul-Architektur
- Erstellen eigener Module
- Modul-Konfiguration

## 🔧 Schnellstart

```php
<?php
require_once 'framework.php';

// ServiceManager initialisieren
$serviceManager = new ServiceManager();

// Alle VMs abrufen
$vms = $serviceManager->getProxmoxVMs();
foreach ($vms as $vm) {
    echo "VM: {$vm->name} (ID: {$vm->vmid}) - Status: {$vm->status}\n";
}
?>
```

## 📋 Systemanforderungen

- PHP 7.4 oder höher
- MySQL/MariaDB
- PHP SOAP-Erweiterung (für ISPConfig)
- cURL-Erweiterung
- JSON-Erweiterung

## 🔗 Nützliche Links

- [GitHub Repository](https://github.com/your-repo)
- [Issues](https://github.com/your-repo/issues)
- [Releases](https://github.com/your-repo/releases)

## 🤝 Beitragen

Wir freuen uns über Beiträge! Bitte lesen Sie unsere [Contributing Guidelines](CONTRIBUTING.md) bevor Sie einen Pull Request erstellen.

## 📄 Lizenz

Dieses Projekt ist unter der [MIT License](LICENSE) lizenziert. 