# Frontpanel - Server Management System

Das Frontpanel ist das öffentliche Frontend des Server Management Systems, das Kunden den Zugriff auf Server-Status, Support-Tickets und ein Kundenportal bietet.

## 🚀 Features

### Öffentlicher Bereich
- **Server-Status Dashboard**: Live-Überwachung von Proxmox VMs und Game Servern
- **Responsive Design**: Optimiert für alle Geräte (Desktop, Tablet, Mobile)
- **Moderne UI**: Bootstrap 5 mit Custom CSS und Animationen
- **Real-time Updates**: Automatische Status-Updates alle 30 Sekunden

### Kundenbereich
- **Kundenregistrierung**: Einfache Registrierung mit E-Mail-Verifikation
- **Kundenlogin**: Sichere Authentifizierung mit Remember-Me-Funktion
- **Account-Management**: Persönliche Einstellungen und Profilverwaltung
- **Support-Tickets**: Vollständiges Ticket-System für Kundenanfragen

### Support-System
- **Ticket-Erstellung**: Einfaches Formular für Support-Anfragen
- **Prioritätsstufen**: Low, Medium, High, Urgent
- **E-Mail-Benachrichtigungen**: Automatische Bestätigungen und Updates
- **Admin-Benachrichtigungen**: Sofortige Benachrichtigung bei neuen Tickets

## 📁 Dateistruktur

```
public/
├── index.php              # Hauptseite mit Server-Status
├── login.php              # Kundenlogin
├── register.php           # Kundenregistrierung
├── dashboard.php          # Kunden-Dashboard (wird erstellt)
├── assets/
│   ├── frontpanel.css     # Haupt-CSS für das Frontpanel
│   ├── frontpanel.js      # Haupt-JavaScript
│   ├── login.css          # CSS für Login/Registrierung
│   ├── login.js           # JavaScript für Login
│   └── register.js        # JavaScript für Registrierung
├── api/                   # API-Endpunkte
│   ├── tickets.php        # Ticket-API
│   └── status.php         # Status-API
└── README.md              # Diese Datei
```

## 🛠️ Installation

### 1. Voraussetzungen
- PHP 7.4 oder höher
- MySQL/MariaDB Datenbank
- Web-Server (Apache/Nginx)
- SMTP-Server für E-Mail-Versand

### 2. Datenbank-Setup
Führen Sie das SQL-Skript `src/frontpanel-database.sql` aus, um alle notwendigen Tabellen zu erstellen:

```bash
mysql -u username -p database_name < src/frontpanel-database.sql
```

**Erforderliche Tabellen:**
- `customers` - Kundenverwaltung mit E-Mail-Verifikation
- `support_tickets` - Support-Tickets mit Prioritäten und Kategorien
- `ticket_replies` - Ticket-Antworten von Kunden und Admins
- `verification_tokens` - E-Mail-Verifikation und Passwort-Reset
- `login_attempts` - Login-Versuche für Brute-Force-Schutz

**Alternative:** Sie können die Tabellen auch manuell über phpMyAdmin oder einen anderen Datenbank-Client erstellen.

### 3. Konfiguration
Passen Sie die folgenden Einstellungen in den PHP-Dateien an:

- **E-Mail-Einstellungen**: SMTP-Server, Absender-Adressen
- **Datenbank-Verbindung**: Host, Benutzername, Passwort, Datenbankname
- **URLs**: Domain und Pfade für Links und Redirects

### 4. Berechtigungen
Stellen Sie sicher, dass der Web-Server Schreibrechte auf folgende Verzeichnisse hat:
- `logs/` (falls vorhanden)
- `uploads/` (falls vorhanden)

## 🔧 Konfiguration

### E-Mail-Einstellungen
In den API-Dateien können Sie die E-Mail-Einstellungen anpassen:

```php
// In tickets.php und register.php
$adminEmail = 'admin@ihredomain.com';
$fromEmail = 'noreply@ihredomain.com';
$replyToEmail = 'support@ihredomain.com';
```

### CORS-Einstellungen
Das Frontpanel unterstützt CORS für API-Aufrufe. Passen Sie die Header in den API-Dateien an:

```php
header('Access-Control-Allow-Origin: https://ihredomain.com');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
```

## 🎨 Anpassungen

### CSS-Anpassungen
Das Design kann über die CSS-Variablen in `assets/frontpanel.css` angepasst werden:

```css
:root {
    --primary-color: #0d6efd;      /* Hauptfarbe */
    --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --border-radius: 12px;         /* Rundungen */
    --shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* Schatten */
}
```

### JavaScript-Anpassungen
Das Frontpanel verwendet jQuery und Bootstrap. Zusätzliche Funktionen können in den JavaScript-Dateien hinzugefügt werden.

## 📱 Responsive Design

Das Frontpanel ist vollständig responsive und optimiert für:
- **Desktop**: Vollständige Funktionalität mit Sidebar-Navigation
- **Tablet**: Angepasste Layouts für mittlere Bildschirmgrößen
- **Mobile**: Touch-optimierte Bedienung mit Mobile-First-Ansatz

## 🔒 Sicherheit

### Implementierte Sicherheitsmaßnahmen
- **Passwort-Hashing**: Bcrypt mit hoher Kosten
- **SQL-Injection-Schutz**: Prepared Statements
- **XSS-Schutz**: HTML-Escaping aller Ausgaben
- **CSRF-Schutz**: Session-basierte Token-Validierung
- **Brute-Force-Schutz**: Account-Sperrung nach fehlgeschlagenen Logins
- **Rate-Limiting**: API-Aufrufe pro IP-Adresse begrenzt

### Empfohlene zusätzliche Maßnahmen
- **HTTPS**: Erzwingen Sie HTTPS für alle Verbindungen
- **Firewall**: Konfigurieren Sie eine Web Application Firewall
- **Logging**: Überwachen Sie alle Login-Versuche und API-Aufrufe
- **Updates**: Halten Sie alle Abhängigkeiten aktuell

## 🚀 Performance

### Optimierungen
- **Caching**: 30-Sekunden-Cache für Status-Updates
- **Lazy Loading**: Bilder und Inhalte werden bei Bedarf geladen
- **Minifizierung**: CSS und JavaScript können minifiziert werden
- **CDN**: Bootstrap und jQuery über CDN geladen

### Monitoring
- **Performance-Metriken**: System Health Score
- **Status-Updates**: Automatische Überwachung aller Services
- **Alert-System**: Benachrichtigungen bei Problemen

## 📊 API-Dokumentation

### Status-API (`/api/status.php`)
**GET** `/api/status.php`
- Liefert aktuellen Server-Status
- Inklusive VM-Status, Game Server-Status und System-Informationen
- Cache: 30 Sekunden

**Response:**
```json
{
    "success": true,
    "data": {
        "vms": [...],
        "gameServers": [...],
        "systemInfo": {...},
        "overall_status": {...},
        "alerts": [...],
        "performance_metrics": {...}
    }
}
```

### Ticket-API (`/api/tickets.php`)
**POST** `/api/tickets.php`
- Erstellt neue Support-Tickets
- Validiert alle Eingaben
- Sendet Bestätigungs-E-Mails

**Request Body:**
```json
{
    "subject": "Betreff des Tickets",
    "email": "kunde@example.com",
    "priority": "medium",
    "message": "Beschreibung des Problems"
}
```

## 🐛 Fehlerbehebung

### Häufige Probleme

**1. Datenbank-Verbindung fehlgeschlagen**
- Überprüfen Sie die Datenbank-Einstellungen in `sys.conf.php`
- Stellen Sie sicher, dass die Datenbank läuft und erreichbar ist

**2. E-Mails werden nicht gesendet**
- Überprüfen Sie die SMTP-Einstellungen
- Prüfen Sie die Server-Logs auf Fehler
- Testen Sie die E-Mail-Funktionalität mit einem einfachen `mail()`-Aufruf

**3. API-Aufrufe schlagen fehl**
- Überprüfen Sie die CORS-Einstellungen
- Stellen Sie sicher, dass alle Pfade korrekt sind
- Prüfen Sie die Browser-Entwicklertools auf Fehler

**4. CSS/JavaScript wird nicht geladen**
- Überprüfen Sie die Pfade zu den Asset-Dateien
- Stellen Sie sicher, dass die Dateien existieren und lesbar sind
- Prüfen Sie die Browser-Entwicklertools auf 404-Fehler

**5. VM Object Access Fehler (BEHOBEN in v1.1.4)**
- **Problem**: `PHP Fatal error: Cannot use object of type VM as array`
- **Lösung**: Alle VM-Objekt-Zugriffe verwenden jetzt korrekte Objekt-Notation
- **Status**: ✅ Vollständig behoben

**6. Game Server Array-Zugriff Fehler (BEHOBEN in v1.1.5)**
- **Problem**: `Cannot access offset of type string on string` bei Game Server-Daten
- **Lösung**: Korrekte Verarbeitung der OGP API-Antwort-Struktur mit `message`-Array
- **Status**: ✅ Vollständig behoben

## 📞 Support

Bei Fragen oder Problemen:
1. Überprüfen Sie die Server-Logs
2. Schauen Sie in die Browser-Entwicklertools
3. Testen Sie die Funktionalität Schritt für Schritt
4. Erstellen Sie ein Support-Ticket über das Frontpanel

## 📝 Changelog

### Version 1.1.6
- **Auto-Refresh Funktionalität**: jQuery-basierte Auto-Aktualisierung aller Server-Status-Daten alle 10 Sekunden
- **Echtzeit-Updates**: Proxmox VMs, Game Server und System-Informationen werden automatisch aktualisiert
- **Manueller Refresh-Button**: Hinzugefügter manueller Aktualisierungs-Button mit Spinner-Animation
- **Lade-Indikatoren**: Visuelle Lade-Indikatoren während der Status-Aktualisierung
- **Intelligente Aktualisierung**: Auto-Refresh wird pausiert, wenn der Tab nicht sichtbar ist, um Ressourcen zu sparen
- **Verbesserte UI**: Erweiterte Benutzeroberfläche mit Zeitstempel der letzten Aktualisierung
- **Neue Übersetzungen**: "Refresh" / "Aktualisieren" in Deutsch und Englisch hinzugefügt

### Version 1.1.5
- **Game Server Anzeige verbessert**: Korrekte Verarbeitung der OGP API-Antwort-Struktur
- Game Server-Daten werden jetzt aus dem `message`-Array gelesen
- Neue Felder für Game Server: Spiel-Typ, Server-Name, IP-Adresse, Port
- Übersetzungen für alle neuen Game Server-Felder hinzugefügt (DE/EN)
- Verbesserte Fehlerbehandlung für OGP API-Antworten

### Version 1.1.4
- **VM Object Access Fehler behoben**: `Cannot use object of type VM as array` Fehler vollständig behoben
- Alle VM-Objekt-Zugriffe in `public/index.php` auf reine Objekt-Notation umgestellt
- Entfernung der gemischten Array/Objekt-Zugriffe mit null coalescing Operator
- Korrekte Verwendung der VM-Klassen-Eigenschaften (`cpu_usage`, `memory_usage`)

### Version 1.1.3
- **open_basedir Fehler vollständig behoben**: Alle Verzeichniszugriffe mit @ Operator und try-catch abgesichert
- Verwendung von relativen Pfaden statt absoluten Pfaden für bessere open_basedir Kompatibilität
- Alle /proc-Datei-Zugriffe mit @file_exists() abgesichert
- Vollständige Unterdrückung von open_basedir Warnungen

### Version 1.1.2
- **open_basedir Fehler behoben**: Robuste Implementierung der System-Informationen-Methoden
- `getDiskUsage()`, `getCPUUsage()`, `getMemoryUsage()`, `getUptime()`, `getLoadAverage()` Methoden robuster gemacht
- Mehrere Fallback-Methoden für System-Informationen implementiert (PHP-Funktionen, /proc-Dateien, System-Befehle)
- Bessere Fehlerbehandlung und Logging für alle System-Informationen-Methoden

### Version 1.1.1
- Dateipfad-Fehler behoben: `framework.php` wird jetzt korrekt aus dem Root-Verzeichnis geladen
- `getSystemInfo()` Methode zu `ServiceManager` in `framework.php` hinzugefügt
- Datenbankstruktur für Frontpanel in `src/frontpanel-database.sql` erstellt

### Version 1.1.0
- E-Mail-Konfiguration zentralisiert in `config/config.inc.php`
- ServiceManager um `getSystemInfo()` und `getOGPGameServers()` erweitert
- Funktionskonflikte mit `t()` Funktion behoben
- Alle statischen E-Mail-Adressen durch Konfigurationskonstanten ersetzt

### Version 1.0.0
- Initiale Version des Frontpanels
- Server-Status-Überwachung
- Kundenregistrierung und -login
- Support-Ticket-System
- Responsive Design
- API-Endpunkte für Status und Tickets

## 📄 Lizenz

Dieses Projekt steht unter der MIT-Lizenz. Siehe `LICENSE` für Details.

## 🤝 Beitragen

Beiträge sind willkommen! Bitte:
1. Forken Sie das Repository
2. Erstellen Sie einen Feature-Branch
3. Committen Sie Ihre Änderungen
4. Erstellen Sie einen Pull Request

---

**Entwickelt mit ❤️ für das Server Management System**
