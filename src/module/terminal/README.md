# Terminal Module

Ein modulares Terminal-Modul für das CMS, das VNC- und SSH-Verbindungen über WebSocket-Proxies ermöglicht.

## Übersicht

Das Terminal-Modul bietet:
- **VNC-Unterstützung** über noVNC (Web-basierte VNC-Client)
- **SSH-Unterstützung** über xterm.js (Web-basierte Terminal)
- **WebSocket-Proxies** für Browser-Kompatibilität
- **Automatische Installation** von Abhängigkeiten
- **API-Integration** mit Proxmox, OVH und ISPConfig

## Dateistruktur

```
src/module/terminal/
├── Module.php                    # Hauptmodul-Klasse
├── helper.php                    # AJAX-Handler für das Modul
├── README.md                     # Diese Datei
├── assets/                       # Frontend-Assets
│   ├── module.css               # CSS-Styles
│   ├── module.js                # JavaScript-Funktionalität
│   ├── novnc.js                 # noVNC-Integration
│   ├── ssh-terminal.js          # SSH-Terminal-Integration
│   ├── novnc/                   # noVNC Library (installiert)
│   ├── xtermjs/                 # xterm.js Library (installiert)
│   ├── websockify/              # VNC WebSocket-Proxy
│   └── ssh-proxy/               # SSH WebSocket-Proxy
├── lang/                        # Sprachdateien
│   ├── de.xml
│   └── en.xml
└── templates/                   # HTML-Templates
    ├── main.php                 # Haupttemplate
    ├── footer.php               # Footer-Template
    └── system/                  # System-Templates
        ├── install.php          # Installations-Script
        ├── uninstall.php        # Deinstallations-Script
        └── updater.php          # Update-Script
```

## Installation

### Automatische Installation
1. Modul im CMS aktivieren
2. "Fehlende Abhängigkeiten installieren" klicken
3. Installation läuft automatisch ab

### Manuelle Installation
```bash
php src/module/terminal/templates/system/install.php
```

## Abhängigkeiten

### PHP-Extensions
- **PHP 7.4+** - Mindestversion
- **cURL** - Für Downloads
- **JSON** - Für API-Kommunikation
- **Sockets** - Für WebSocket-Proxies (empfohlen)

### Externe Libraries
- **noVNC** - Web-basierte VNC-Client
- **xterm.js** - Web-basierte Terminal-Emulation

### WebSocket-Proxies
- **VNC-Proxy** - Konvertiert WebSocket zu VNC-TCP
- **SSH-Proxy** - Konvertiert WebSocket zu SSH-TCP

## Konfiguration

### Assets-Verzeichnis
```
src/module/terminal/assets/
├── novnc/          # noVNC Library
├── xtermjs/        # xterm.js Library
├── websockify/     # VNC WebSocket-Proxy
└── ssh-proxy/      # SSH WebSocket-Proxy
```

### Berechtigungen
```bash
# Windows (PowerShell als Administrator)
icacls "src\module\terminal\assets" /grant Everyone:F /T

# Linux/macOS
chmod -R 755 src/module/terminal/assets/
```

## API-Integration

### Unterstützte APIs
- **Proxmox** - VM-Management
- **OVH** - VPS-Management
- **ISPConfig** - Server-Management

### Server-Abruf
- **VNC-Server** - Automatisch aus APIs abgerufen
- **SSH-Server** - Automatisch aus APIs abgerufen
- **Keine Passwort-Speicherung** - Sicherheitskonzept

## Verwendung

### VNC-Verbindung
1. Server aus Liste auswählen
2. VNC-Passwort eingeben
3. Verbindung wird über WebSocket-Proxy hergestellt

### SSH-Verbindung
1. Server aus Liste auswählen
2. SSH-Credentials eingeben
3. Terminal wird über WebSocket-Proxy geöffnet

## Fehlerbehebung

### Aktueller Status (2025-09-13)

**Problem:** Terminal-Interface wird nicht angezeigt, obwohl alle Abhängigkeiten erfüllt sind.

**Debug-Ausgaben:**
```
Terminal Module Debug:
novncInstalled: true
xtermInstalled: true
proxiesInstalled: true
allDependenciesMet: true
```

**Fehlermeldungen:**
```
Admin Dashboard initialized admin-core.js:360:13
Keine spezifische Seite erkannt, lade keine zusätzlichen Scripts inc-js-loader.js:63:21
Terminal Module initialized module.js:19:17
Making request for action: get_vnc_servers module.js:676:17
Making request for action: get_ssh_servers module.js:676:17
Installation Button gefunden: null module.js:55:17
Installation Button nicht gefunden! module.js:64:21
Response status for get_ssh_servers: 200 module.js:683:17
Response headers for get_ssh_servers: 
Headers(7) { connection → "Keep-Alive", "content-length" → "62", "content-type" → "application/json", date → "Fri, 12 Sep 2025 22:18:30 GMT", "keep-alive" → "timeout=5, max=100", "server" → "Apache/2.4.58 (Win64) OpenSSL/3.1.3 PHP/8.2.12", "x-powered-by" → "PHP/8.2.12" }
module.js:684:17
Raw response for get_ssh_servers: {"success":true,"message":"Operation successful","data":[]} module.js:687:17
Parsed JSON for get_ssh_servers: 
Object { success: true, message: "Operation successful", data: [] }
module.js:691:21
Response status for get_vnc_servers: 200 module.js:683:17
Response headers for get_vnc_servers: 
Headers(7) { connection → "Keep-Alive", "content-length" → "62", "content-type" → "application/json", date → "Fri, 12 Sep 2025 22:18:30 GMT", "keep-alive" → "timeout=5, max=99", "server" → "Apache/2.4.58 (Win64) OpenSSL/3.1.3 PHP/8.2.12", "x-powered-by" → "PHP/8.2.12" }
module.js:684:17
Raw response for get_vnc_servers: {"success":true,"message":"Operation successful","data":[]} module.js:687:17
Parsed JSON for get_vnc_servers: 
Object { success: true, message: "Operation successful", data: [] }
module.js:691:21
Uncaught TypeError: can't access property "value", document.getElementById(...) is null
    showAddServerModal http://localhost/src/module/terminal/assets/module.js:333
    onclick http://localhost/src/index.php?option=modules&mod=terminal:1
module.js:333:18
```

**Analyse:**
1. ✅ **Abhängigkeiten erfüllt** - Alle Libraries installiert
2. ✅ **AJAX-Requests funktionieren** - 200 Status, saubere JSON-Responses
3. ❌ **Terminal-Interface nicht angezeigt** - Installationsassistent wird weiterhin angezeigt
4. ❌ **JavaScript-Fehler** - `document.getElementById(...) is null`

**Mögliche Ursachen:**
1. **Template-Logik** - `main.php` zeigt trotz erfüllter Abhängigkeiten Installationsassistenten
2. **HTML-Elemente fehlen** - Terminal-Interface HTML wird nicht gerendert
3. **JavaScript-Initialisierung** - Funktionen versuchen auf nicht existierende Elemente zuzugreifen

**Nächste Schritte:**
1. **Template-Debugging** - Prüfen warum `$allDependenciesMet = true` nicht zum Terminal-Interface führt
2. **HTML-Struktur** - Sicherstellen dass Terminal-Interface HTML korrekt gerendert wird
3. **JavaScript-Fixes** - Null-Checks für DOM-Elemente hinzufügen

### Häufige Probleme

#### Installation schlägt fehl
```bash
# Schreibrechte prüfen
ls -la src/module/terminal/assets/

# Berechtigungen setzen
chmod -R 755 src/module/terminal/assets/
```

#### WebSocket-Proxies funktionieren nicht
```bash
# Verzeichnisse prüfen
ls -la src/module/terminal/assets/websockify/
ls -la src/module/terminal/assets/ssh-proxy/

# Dateien prüfen
cat src/module/terminal/assets/websockify/index.php
cat src/module/terminal/assets/ssh-proxy/index.php
```

#### Libraries nicht erkannt
```bash
# Verzeichnisse prüfen
ls -la src/module/terminal/assets/novnc/
ls -la src/module/terminal/assets/xtermjs/

# Dateien zählen
find src/module/terminal/assets/novnc/ -type f | wc -l
find src/module/terminal/assets/xtermjs/ -type f | wc -l
```

## Entwicklung

### Debugging aktivieren
```php
// In main.php
echo "<script>console.log('Terminal Module Debug:');</script>";
```

### Logs prüfen
```bash
# Apache Error Log
tail -f /var/log/apache2/error.log

# PHP Error Log
tail -f /var/log/php/error.log
```

### Tests
```bash
# Installation testen
php src/module/terminal/templates/system/install.php

# Abhängigkeiten prüfen
php -r "require_once 'src/module/terminal/Module.php'; $m = new TerminalModule('terminal'); var_dump($m->checkRequirements());"
```

## Lizenz

Teil des CMS-Frameworks. Siehe Hauptprojekt-Lizenz.

## Changelog

### Version 1.0.0 (2025-09-13)
- ✅ **Initiale Implementierung** - Grundfunktionalität
- ✅ **Automatische Installation** - Abhängigkeiten werden automatisch installiert
- ✅ **WebSocket-Proxies** - VNC und SSH über Browser
- ✅ **API-Integration** - Proxmox, OVH, ISPConfig
- ❌ **Template-Problem** - Terminal-Interface wird nicht angezeigt
- ❌ **JavaScript-Fehler** - DOM-Elemente nicht gefunden

## TODO

- [ ] **Template-Problem beheben** - Terminal-Interface korrekt anzeigen
- [ ] **JavaScript-Fehler beheben** - Null-Checks für DOM-Elemente
- [ ] **WebSocket-Implementierung** - Echte WebSocket-Server statt PHP-Proxies
- [ ] **Sicherheit** - Authentifizierung und Autorisierung
- [ ] **Performance** - Optimierung der WebSocket-Verbindungen
- [ ] **Tests** - Unit-Tests und Integration-Tests
- [ ] **Dokumentation** - API-Dokumentation und Benutzerhandbuch