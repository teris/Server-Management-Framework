# Proxmox Management Module

## Übersicht
Das Proxmox-Modul ermöglicht die Verwaltung von virtuellen Maschinen (QEMU) und LXC-Containern über eine benutzerfreundliche Weboberfläche.

## Version: 1.1.0

## ✅ Implementierte Funktionen

### Node-Management
- Node-Übersicht mit Status-Informationen (CPU, RAM, Speicher)
- VM/Container-Zählung pro Node
- Storage-Details mit Inhalt und Verfügbarkeit

### VM/Container-Verwaltung
- Server-Liste mit Status-Anzeige
- Typ-Unterscheidung (QEMU VMs / LXC Container)
- Detaillierte Konfigurations- und Status-Daten

### VM/Container-Steuerung
- **Starten/Stoppen**: VMs und Container steuern
- **Neustart**: VMs und Container neu starten
- **Reset**: Hard-Reset für QEMU VMs (nicht für LXC)
- **Fortsetzen**: VMs und Container fortsetzen
- **Löschen**: VMs und Container löschen (nur wenn gestoppt)

### Benutzeroberfläche
- Tab-basierte Navigation ohne Modals
- Bestätigungs-Modals für kritische Aktionen
- Responsive Design
- Echtzeit-Updates

## ❌ Geplante Funktionen (To-Do)

### VM/Container-Erstellung
- [ ] **VM-Erstellung**: Neue QEMU VMs erstellen
- [ ] **LXC-Erstellung**: Neue LXC Container erstellen
- [ ] **Template-Auswahl**: Auswahl von VM/Container-Templates
- [ ] **Konfigurations-Assistent**: Schritt-für-Schritt Einrichtung

### VM/Container-Bearbeitung
- [ ] **Konfiguration bearbeiten**: VM/Container-Einstellungen ändern
- [ ] **Ressourcen anpassen**: CPU, RAM, Speicher dynamisch ändern
- [ ] **Netzwerk-Konfiguration**: Netzwerk-Einstellungen verwalten
- [ ] **Snapshot-Management**: Snapshots erstellen und verwalten

### Erweiterte Verwaltung
- [ ] **Bulk-Operationen**: Mehrere VMs/Container gleichzeitig verwalten
- [ ] **Backup-Management**: Backup-Strategien verwalten
- [ ] **Migration**: VMs zwischen Nodes migrieren
- [ ] **Monitoring**: Erweiterte Überwachungs-Features

## Technische Details

### API-Endpunkte
- `GET /nodes` - Node-Liste
- `GET /cluster/resources` - VMs und Container
- `POST /nodes/{node}/qemu/{vmid}/status/{action}` - QEMU Aktionen
- `POST /nodes/{node}/lxc/{vmid}/status/{action}` - LXC Aktionen
- `DELETE /nodes/{node}/{type}/{vmid}` - Löschen

### Dateistruktur
```
src/module/proxmox/
├── Module.php                    # Hauptmodul-Logik
├── templates/
│   ├── main.php                  # Haupt-Template
│   ├── parts/                    # Header/Footer
│   ├── tabs/                     # Tab-Templates
│   ├── modals/                   # Modal-Templates
│   └── assets/                   # JavaScript-Dateien
```

## Verwendung

### Node-Auswahl
1. Öffnen Sie das Proxmox-Modul
2. Wählen Sie einen Node aus der Übersicht
3. Klicken Sie auf "Auswählen" um VMs/Container zu laden

### VM/Container-Verwaltung
1. **Anzeigen**: Alle VMs/Container werden automatisch geladen
2. **Steuern**: Verwenden Sie die Aktions-Buttons
3. **Details**: Klicken Sie auf "Verwaltung" für erweiterte Informationen
4. **Löschen**: Bestätigen Sie die Löschung im Modal

## Fehlerbehebung

### Häufige Probleme
- **"Validation failed"**: API-Konfiguration überprüfen
- **"VM must be stopped before delete"**: VM/Container zuerst stoppen
- **"Reset wird für LXC-Container nicht unterstützt"**: Verwenden Sie "Neustart"

## Changelog

### Version 1.1.0 (Aktuell)
- ✅ Vollständige VM/Container-Verwaltung
- ✅ Node-Management mit Storage-Details
- ✅ Bestätigungs-Modals
- ✅ LXC-Container-Unterstützung
- ✅ Erweiterte Benutzeroberfläche

### Version 1.0.0
- ✅ Grundlegende VM-Verwaltung
- ✅ Node-Übersicht

## Support
- **E-Mail**: support@orga-consult.eu
- **Dokumentation**: [Proxmox VE API](https://pve.proxmox.com/wiki/Proxmox_VE_API)

---
**Entwickelt von**: Teris  
**Kompatibilität**: Proxmox VE 6.0+