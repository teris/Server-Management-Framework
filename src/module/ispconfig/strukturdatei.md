# ISPConfig Modul - Strukturdatei

## Übersicht
Das ISPConfig-Modul ist ein zentrales Modul für die Verwaltung von ISPConfig-Servern und deren Funktionen. Es bietet eine umfassende Benutzeroberfläche für Domain-Management, Benutzerverwaltung und DNS-Konfiguration.

## Verzeichnisstruktur

```
src/module/ispconfig/
├── assets/                          # JavaScript-Dateien für Frontend-Funktionalität
│   ├── dns-management.js           # DNS-Management-Funktionen
│   ├── domain-management.js        # Domain-Management-Funktionen
│   ├── module.js                   # Hauptmodul-JavaScript
│   └── user-management.js          # Benutzerverwaltungs-Funktionen
├── lang/                           # Sprachdateien
│   ├── de.xml                     # Deutsche Übersetzungen
│   └── en.xml                     # Englische Übersetzungen
├── templates/                      # PHP-Templates für die Benutzeroberfläche
│   ├── main.php                   # Haupttemplate des Moduls
│   ├── modals/                    # Modal-Dialoge
│   │   ├── dns-management.php     # DNS-Management-Modal
│   │   ├── domain-management.php  # Domain-Management-Modal
│   │   └── user-management.php    # Benutzerverwaltungs-Modal
│   ├── parts/                     # Template-Teile
│   │   ├── footer.php             # Footer-Template
│   │   └── header.php             # Header-Template
│   └── tabs/                      # Tab-Templates
│       ├── domain-management-tab.php    # Domain-Management-Tab
│       ├── user-management-tab.php      # Benutzerverwaltungs-Tab
│       └── websites-tab.php             # Websites-Tab
├── Module.php                     # Hauptmodul-Klasse
└── strukturdatei.md               # Diese Strukturdatei
```

## Dateibeschreibungen

### Core-Dateien
- **Module.php**: Hauptmodul-Klasse mit allen Kernfunktionen und API-Integrationen

### Assets (JavaScript)
- **module.js**: Hauptmodul-JavaScript mit gemeinsamen Funktionen
- **domain-management.js**: Spezifische Funktionen für Domain-Management
- **dns-management.js**: DNS-Konfiguration und -verwaltung
- **user-management.js**: Benutzerverwaltungs-Funktionen

### Templates
- **main.php**: Haupttemplate mit Tab-Navigation und Modul-Layout
- **modals/**: Modal-Dialoge für verschiedene Verwaltungsaufgaben
- **parts/**: Wiederverwendbare Template-Teile (Header, Footer)
- **tabs/**: Einzelne Tab-Inhalte für verschiedene Funktionsbereiche

### Sprachdateien
- **de.xml**: Deutsche Übersetzungen für alle Modultexte
- **en.xml**: Englische Übersetzungen für alle Modultexte

## Funktionsbereiche

### 1. Domain-Management
- Domain-Registrierung und -Verwaltung
- Domain-Einstellungen und -Konfiguration
- Domain-Status-Überwachung

### 2. Benutzerverwaltung
- ISPConfig-Benutzer erstellen und verwalten
- Benutzerrechte und -berechtigungen
- Benutzer-Profile und -Einstellungen

### 3. DNS-Management
- DNS-Zone-Konfiguration
- DNS-Record-Verwaltung
- DNS-Propagation-Überwachung

### 4. Website-Management
- Website-Erstellung und -Verwaltung
- Webspace-Konfiguration
- SSL-Zertifikat-Management

## Integration
Das Modul ist vollständig in das Framework integriert und nutzt:
- DatabaseManager für Datenbankoperationen
- LanguageManager für Mehrsprachigkeit
- AdminCore für Administrationsfunktionen
- ServiceManager->getISPConfigClients() für einheitliche Client-Abfragen
- Modulare Template-Struktur für flexible UI

## Abhängigkeiten
- ISPConfig-API für Server-Kommunikation
- Framework-Core-Klassen
- JavaScript-Assets für Frontend-Interaktion
- XML-Sprachdateien für Lokalisierung

## Entwicklungsnotizen
- Alle Templates folgen dem Framework-Standard
- JavaScript-Dateien sind modular aufgebaut
- Sprachdateien verwenden XML-Format
- Modal-Dialoge sind wiederverwendbar konzipiert

## Aktuelle Updates (2024-12-19)
- **Framework-Integration**: Alle Client-Abfragen verwenden jetzt `$serviceManager->getISPConfigClients()` aus dem Framework
- **Vereinheitlichte API**: Konsistente Fehlerbehandlung und Datenstruktur durch Framework-Funktionen
- **Verbesserte Wartbarkeit**: Reduzierte Code-Duplikation durch zentrale Client-Verwaltung
- **Robuste Fehlerbehandlung**: Einheitliche Behandlung von API-Fehlern und Fallback-Mechanismen

### Geänderte Methoden:
- `getAllUsers()` - Verwendet jetzt Framework-Funktion
- `getISPConfigClients()` - Wrapper für Framework-Funktion
- `getAllDomains()` - Integriert Framework-Client-Abfrage
- `getAllWebsites()` - Nutzt einheitliche Client-Verwaltung
