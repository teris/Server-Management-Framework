# Support Tickets Module

Ein vollständiges Support-Ticket-Management-System für das Server Management Administrationscenter.

## Features

### Ticket-Verwaltung
- **Ticket-Erstellung**: Administratoren können Tickets für Kunden erstellen
- **Ticket-Anzeige**: Detaillierte Ansicht aller Ticket-Informationen
- **Ticket-Bearbeitung**: Priorität, Status und andere Felder ändern
- **Ticket-Löschung**: Sichere Löschung von Tickets mit Bestätigung

### Antwort-System
- **Antworten auf Tickets**: Administratoren können auf Kunden-Tickets antworten
- **Interne Notizen**: Interne Notizen, die nur für Administratoren sichtbar sind
- **Antwort-Historie**: Vollständige Historie aller Antworten und Notizen

### Status-Management
- **Status-Änderungen**: Tickets zwischen verschiedenen Status wechseln
  - Open (Offen)
  - In Progress (In Bearbeitung)
  - Waiting Customer (Wartet auf Kunde)
  - Waiting Admin (Wartet auf Admin)
  - Resolved (Gelöst)
  - Closed (Geschlossen)

### Prioritäts-Management
- **Prioritäts-Stufen**: 
  - Low (Niedrig)
  - Medium (Mittel)
  - High (Hoch)
  - Urgent (Dringend)

### Filter und Suche
- **Status-Filter**: Tickets nach Status filtern
- **Prioritäts-Filter**: Tickets nach Priorität filtern
- **Text-Suche**: Suche in Betreff, Nachricht und Kundendaten
- **Pagination**: Seitenweise Anzeige großer Ticket-Mengen

### Massenaktionen
- **Massenauswahl**: Mehrere Tickets gleichzeitig auswählen
- **Massenaktionen**:
  - Massenweise schließen
  - Massenweise löschen
  - Massenweise Priorität ändern
  - Massenweise Status ändern

### Statistiken
- **Übersicht**: Gesamtanzahl, offene Tickets, geschlossene Tickets
- **Zeitraum-Analyse**: Statistiken für verschiedene Zeiträume
- **Performance-Metriken**: Durchschnittliche Lösungszeiten

## Installation

1. **Modul-Konfiguration registrieren**:
   Das Modul muss in der Hauptkonfiguration des Systems registriert werden.

2. **Datenbank-Updates**:
   Stellen Sie sicher, dass die erforderlichen Datenbank-Tabellen existieren:
   - `support_tickets`
   - `ticket_replies`

3. **Berechtigungen**:
   Das Modul unterstützt verschiedene Benutzerrollen:
   - `admin`: Vollzugriff (lesen, schreiben, löschen, verwalten)
   - `manager`: Lesen und schreiben
   - `support`: Lesen und schreiben
   - `user`: Kein Zugriff

## Verwendung

### Ticket-Erstellung
1. Klicken Sie auf "Neues Ticket"
2. Wählen Sie den Kunden aus
3. Setzen Sie Priorität und Kategorie
4. Geben Sie Betreff und Nachricht ein
5. Klicken Sie auf "Erstellen"

### Ticket-Bearbeitung
1. Klicken Sie auf das Auge-Symbol bei einem Ticket
2. Verwenden Sie die Schnellaktionen:
   - Antworten
   - Priorität ändern
   - Status ändern
   - Schließen
   - Löschen

### Antworten
1. Öffnen Sie ein Ticket
2. Klicken Sie auf "Antworten"
3. Geben Sie Ihre Nachricht ein
4. Wählen Sie "Interne Antwort" für nur-administrative Notizen
5. Klicken Sie auf "Antwort senden"

### Massenaktionen
1. Wählen Sie mehrere Tickets mit den Checkboxen aus
2. Verwenden Sie die Massenaktion-Buttons
3. Bestätigen Sie die Aktion

## Dateistruktur

```
support-tickets/
├── Module.php              # Hauptmodul-Klasse
├── config.php              # Modul-Konfiguration
├── README.md               # Diese Datei
├── templates/
│   └── main.php           # Haupt-Template
├── lang/
│   ├── de.xml             # Deutsche Übersetzungen
│   └── en.xml             # Englische Übersetzungen
└── assets/
    ├── module.css         # Modul-spezifische Styles
    └── module.js          # Client-seitige Funktionalität
```

## API-Endpunkte

Das Modul stellt folgende AJAX-Endpunkte bereit:

### Ticket-Management
- `get_tickets` - Tickets auflisten mit Filterung und Pagination
- `get_ticket` - Einzelnes Ticket mit Details laden
- `create_ticket` - Neues Ticket erstellen
- `update_ticket` - Ticket aktualisieren
- `delete_ticket` - Ticket löschen

### Antwort-System
- `reply_ticket` - Antwort auf Ticket hinzufügen
- `get_ticket_replies` - Antworten zu einem Ticket laden
- `add_internal_note` - Interne Notiz hinzufügen

### Status-Management
- `close_ticket` - Ticket schließen
- `reopen_ticket` - Ticket wieder öffnen
- `change_priority` - Priorität ändern
- `change_status` - Status ändern
- `assign_ticket` - Ticket zuweisen

### Massenaktionen
- `bulk_action` - Massenaktionen ausführen

### Statistiken
- `get_statistics` - Statistiken laden

## Konfiguration

Die Modul-Konfiguration befindet sich in `config.php`:

```php
return [
    'name' => 'Support Tickets',
    'key' => 'support-tickets',
    'version' => '1.0.0',
    'description' => 'Support Ticket Management System for Administrators',
    'author' => 'Server Management Team',
    'icon' => 'bi-headset',
    'permissions' => [
        'admin' => ['read', 'write', 'delete', 'manage'],
        'manager' => ['read', 'write'],
        'support' => ['read', 'write'],
        'user' => []
    ],
    'settings' => [
        'tickets_per_page' => 20,
        'auto_assignment' => false,
        'notification_email' => '',
        'default_priority' => 'medium',
        'default_status' => 'open',
        'allowed_file_types' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt'],
        'max_file_size' => 5242880, // 5MB
        'auto_close_days' => 30,
        'escalation_hours' => 24
    ]
];
```

## Übersetzungen

Das Modul unterstützt mehrsprachige Benutzeroberflächen:

- **Deutsch** (`lang/de.xml`)
- **Englisch** (`lang/en.xml`)

Neue Sprachen können durch Hinzufügen weiterer XML-Dateien hinzugefügt werden.

## Styling

Das Modul verwendet Bootstrap 5 für das Layout und bietet zusätzliche CSS-Klassen:

- `.support-tickets-module` - Hauptcontainer
- `.ticket-info` - Ticket-Informationen
- `.ticket-replies` - Antwort-Bereich
- `.reply-item` - Einzelne Antworten
- `.reply-item.internal` - Interne Antworten
- `.bulk-actions` - Massenaktionen-Bereich

## JavaScript

Das Modul verwendet eine objektorientierte JavaScript-Klasse `SupportTicketsModule` für:

- AJAX-Kommunikation
- DOM-Manipulation
- Event-Handling
- Modal-Management
- Formular-Validierung

## Sicherheit

- **SQL-Injection-Schutz**: Verwendung von Prepared Statements
- **XSS-Schutz**: HTML-Escaping aller Benutzer-Eingaben
- **CSRF-Schutz**: Session-basierte Authentifizierung
- **Berechtigungsprüfung**: Rollenbasierte Zugriffskontrolle

## Erweiterungen

Das Modul ist erweiterbar durch:

- **Hooks**: Event-System für benutzerdefinierte Aktionen
- **Einstellungen**: Konfigurierbare Parameter
- **Templates**: Anpassbare Benutzeroberfläche
- **Sprachen**: Mehrsprachige Unterstützung

## Support

Bei Fragen oder Problemen:

1. Überprüfen Sie die Browser-Konsole auf JavaScript-Fehler
2. Prüfen Sie die Server-Logs auf PHP-Fehler
3. Stellen Sie sicher, dass alle Abhängigkeiten installiert sind
4. Überprüfen Sie die Datenbankverbindung und Tabellen

## Changelog

### Version 1.0.0
- Erste Veröffentlichung
- Vollständige Ticket-Verwaltung
- Antwort-System
- Massenaktionen
- Statistiken
- Mehrsprachige Unterstützung
