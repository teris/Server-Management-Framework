# 📋 Changelog

Alle wichtigen Änderungen am Server Management Framework werden in dieser Datei dokumentiert.

## [3.0.4]

### Entfernt
- **Übermäßiges Loggin** - auskommentiert der funtkon `logRequest` in den Generischen Funktionen

### Hinzugefügt
- **Manuelles Loggin** - Neue Methode zum erstellen von Logs hinzugefügt __log($action, $details, $status = 'info')
- Für Manuelles Loggin muss die Tabele im SQL angepasst werden 
        ALTER TABLE `activity_log` 
        MODIFY COLUMN `status` enum('success','error','pending','info') NOT NULL;
- Testfunktion für allgemeinen test Hinzugefügt `__test()`

### Behoben
- ISPConfigAPI Fehler `IspconfigAPI Error: SoapFault::SoapFault() expects at least 2 parameters, 1 given` behoben


## [3.0.3]

### Geändert
- Fehler behoben beim Aufruf der funktion `IspconfigAPI` durch das _get, _update, _add, _delete als Suffix angefügt wurde_

## [3.0.2]

### Hinzugefügt
- **Domain-Registrierungssystem** - Vollständiges System für Benutzer zur Registrierung von Webdomains
- **Domain-Verfügbarkeitsprüfung** - Integration der OVH API für echte Domain-Verfügbarkeitsprüfungen
- **Domain-Einstellungsverwaltung** - Admin-Bereich zur Verwaltung verfügbarer Domain-Endungen (TLDs)
- **Domain-Registrierungsverwaltung** - Admin-Bereich zur Überwachung und Genehmigung von Domain-Registrierungsanfragen
- **Multi-TLD-Unterstützung** - Automatische Prüfung aller aktivierten Domain-Endungen
- **Alternative Domain-Vorschläge** - Intelligente Vorschläge für verfügbare Alternativen
- **Rate-Limiting-System** - IP-basierte Begrenzung für Domain-Verfügbarkeitsprüfungen
- **Erweiterte Navigation** - Kollapsierbare Untermenüs mit Hauptkategorien (Modules, Domains, Optionen)
- Dynamische Plugin-Untermenüs - Automatische Generierung von Plugin-Untermenüs aus `inc/module.php`
- Visuelle Menü-Hervorhebung - CSS-Styling für Hauptkategorien in der Navigation

### Geändert
- AdminHandler erweitert - Neue Actions für Domain-Extension-Management (`add_extension`, `update_extension`, `delete_extension`, `toggle_extension_status`)
- Frontend-UI refactored - Domain-Registrierung verwendet separate Eingabefelder für Domain-Name und TLD
- AJAX-Endpoints vereinheitlicht - Alle Domain-Einstellungs-Actions verwenden `index.php` mit `core=admin`
- Logging-System verbessert - `logActivity()` durch `error_log()` ersetzt für bessere Kompatibilität
- Navigation-Struktur überarbeitet - Menü-Items in logische Kategorien gruppiert mit Untermenüs
- Domain-Verfügbarkeitsprüfung optimiert - OVH API als primäre Prüfmethode, DNS als Fallback

### Behoben
- "Unknown action" Fehler - Alle Domain-Extension-Management-Actions funktionieren korrekt
- Logging-Fehler - `logActivity()` Funktion existierte nicht, durch `error_log()` ersetzt
- PHP Fatal Error - `Call to a member function OvhAPI() on null` behoben
- Database-Instanziierung - `new Database()` durch `Database::getInstance()` ersetzt (Singleton-Pattern)
- PHP Warnings - `dns_get_record()` und Header-Fehler durch Output-Buffering behoben
- **JSON-Parsing-Fehler** - Redundante `JSON.parse()` Aufrufe entfernt

### Technische Verbesserungen
- **Datenbank-Schema** - Neue Tabellen `domain_registrations` und `domain_extensions` hinzugefügt
- **Sprachsystem-Integration** - Neue Übersetzungen in bestehende XML-basierte Sprachdateien integriert
- Bootstrap-Integration - Kollapsierbare Navigation mit Bootstrap 5 Collapse-Komponente
- Responsive Design - Mobile-optimierte Untermenüs mit Touch-freundlichen Interaktionen

### Sicherheit
- **Input-Validierung** - Domain-Namen dürfen keine Punkte enthalten
- **Rate-Limiting** - IP-basierte Begrenzung für API-Aufrufe
- **Admin-Rechte** - Alle Domain-Einstellungs-Actions erfordern Admin-Berechtigung
- **SQL-Injection-Schutz** - Prepared Statements für alle Datenbank-Operationen

## [3.0.1]

### Hinzugefügt
- **Support-Ticket Antworten-System** - Vollständige Konversationshistorie zwischen Kunden und Support-Team
- **Admin Support-Tickets Modul** - Umfassendes Admin-Modul für Support-Ticket-Verwaltung
- **Ticket-Reply-Funktionalität** - Kunden können auf Admin-Antworten reagieren
- **Automatische Status-Updates** - Ticket-Status wird basierend auf Antworten automatisch aktualisiert
- **Interne Notizen** - Admins können interne Notizen hinzufügen (nicht für Kunden sichtbar)
- **Bulk-Aktionen** - Massenbearbeitung von Tickets (schließen, löschen, Priorität ändern)
- **Ticket-Statistiken** - Umfassende Statistiken für Support-Management
- **Erweiterte Ticket-Filter** - Nach Status, Priorität und Suchbegriffen filtern
- **Ticket-Zuweisung** - Tickets können an bestimmte Admins zugewiesen werden
- **E-Mail-Templates** - Vorlagen für automatische E-Mail-Benachrichtigungen
- **Ticket-Kategorien** - Kategorisierung von Support-Tickets
- **Abteilungs-Management** - Tickets können verschiedenen Abteilungen zugewiesen werden

### Geändert
- **Support-System erweitert** - Vollständige Antworten-Funktionalität für Kunden und Admins
- **Ticket-Status-Anzeige verbessert** - Korrekte Anzeige aller nicht-geschlossenen Tickets als "offen"
- **Admin-Modul-System erweitert** - Neues Support-Tickets-Modul für Admin-Panel
- **Sprachdateien erweitert** - Neue Übersetzungen für Support-Ticket-Antworten und Admin-Modul

### Behoben
- **Support-Ticket Antworten-Anzeige** - Kunden können jetzt Admin-Antworten sehen und darauf reagieren
- **Ticket-Status-Anzeige** - Alle nicht-geschlossenen Tickets werden korrekt als "offen" angezeigt
- **Admin-Modul-Loading** - Support-Tickets-Modul wird korrekt im Admin-Panel geladen
- **SQL-Syntax-Fehler** - LIMIT/OFFSET Parameter werden korrekt als Integer behandelt
- **XML-Parser-Fehler** - Falsche schließende Tags in Sprachdateien behoben
- **Module-Registration** - Support-Tickets-Modul korrekt in Plugin-Konfiguration registriert
- **Undefined Array Key Fehler** - `$_GET['mod']` Überprüfung in module.php hinzugefügt

## [3.0.0 RC]

### Hinzugefügt
- **Frontpanel - Server Management System** - Vollständiges öffentliches Frontend für Kunden
- **Server-Status Dashboard** - Live-Überwachung von Proxmox VMs und Game Servern
- **Responsive Design** - Optimiert für alle Geräte (Desktop, Tablet, Mobile)
- **Moderne UI** - Bootstrap 5 mit Custom CSS und Animationen
- **Real-time Updates** - Automatische Status-Updates alle 30 Sekunden
- **Kundenregistrierung** - Einfache Registrierung mit E-Mail-Verifikation
- **Kundenlogin** - Sichere Authentifizierung mit Remember-Me-Funktion
- **Account-Management** - Persönliche Einstellungen und Profilverwaltung
- **Support-Tickets** - Vollständiges Ticket-System für Kundenanfragen
- **Ticket-Erstellung** - Einfaches Formular für Support-Anfragen
- **Prioritätsstufen** - Low, Medium, High, Urgent
- **E-Mail-Benachrichtigungen** - Automatische Bestätigungen und Updates
- **Admin-Benachrichtigungen** - Sofortige Benachrichtigung bei neuen Tickets
- **Auto-Refresh Funktionalität** - jQuery-basierte Auto-Aktualisierung aller Server-Status-Daten alle 10 Sekunden
- **Echtzeit-Updates** - Proxmox VMs, Game Server und System-Informationen werden automatisch aktualisiert
- **Manueller Refresh-Button** - Hinzugefügter manueller Aktualisierungs-Button mit Spinner-Animation
- **Lade-Indikatoren** - Visuelle Lade-Indikatoren während der Status-Aktualisierung
- **Intelligente Aktualisierung** - Auto-Refresh wird pausiert, wenn der Tab nicht sichtbar ist
- **Game Server Anzeige verbessert** - Korrekte Verarbeitung der OGP API-Antwort-Struktur
- **Neue Game Server-Felder** - Spiel-Typ, Server-Name, IP-Adresse, Port
- **E-Mail-Verifikationssystem** - Vollständiger Workflow für neue Kundenregistrierungen
- **Kunden-Dashboard** - Hauptseite für angemeldete Kunden mit Übersicht und Funktionen
- **Session-Management** - Sichere Verwaltung von Kunden-Sessions
- **Multi-Sprachunterstützung** - Deutsche und englische Übersetzungen für alle Kunden-Seiten

### Geändert
- **Framework.php erweitert** - PDO Wrapper-Methoden für bessere Datenbank-Kompatibilität hinzugefügt
- **Datenbank-Klasse verbessert** - Direkte PDO-Methoden wie `prepare()`, `query()`, `exec()` verfügbar gemacht
- **Sprachdateien erweitert** - Umfassende Übersetzungen für Kundenregistrierung, Login und Dashboard
- **Status-API verbessert** - Bessere Fehlerbehandlung und Caching-Mechanismen
- **UI/UX verbessert** - Erweiterte Benutzeroberfläche mit Zeitstempel der letzten Aktualisierung

### Behoben
- **Fatal Error behoben** - `Call to undefined method Database::prepare()` vollständig behoben
- **VM Object Access Fehler** - `Cannot use object of type VM as array` Fehler vollständig behoben
- **Game Server Array-Zugriff Fehler** - `Cannot access offset of type string on string` bei Game Server-Daten behoben
- **open_basedir Fehler** - Alle Verzeichniszugriffe mit @ Operator und try-catch abgesichert
- **Dateipfad-Fehler** - `framework.php` wird jetzt korrekt aus dem Root-Verzeichnis geladen
- **Funktionskonflikte** - Mit `t()` Funktion behoben
- **Datenbank-Verbindungsprobleme** - Robuste Implementierung der System-Informationen-Methoden

### Sicherheit
- **Passwort-Hashing** - Bcrypt mit hoher Kosten implementiert
- **SQL-Injection-Schutz** - Prepared Statements durchgängig verwendet
- **XSS-Schutz** - HTML-Escaping aller Ausgaben
- **CSRF-Schutz** - Session-basierte Token-Validierung
- **Brute-Force-Schutz** - Account-Sperrung nach fehlgeschlagenen Logins
- **Rate-Limiting** - API-Aufrufe pro IP-Adresse begrenzt

## [3.0.0-beta]

### Hinzugefügt
- **Benutzerliste und Bearbeitung** - Vollständige Anzeige bereits registrierter Benutzer aus allen Systemen
- **Multi-System Benutzerverwaltung** - Anzeige von Admin Dashboard, OGP, Proxmox und ISPConfig Benutzern
- **API-Verbindungsfehlerbehandlung** - Graceful Handling von API-Verbindungsproblemen mit Benutzerbenachrichtigungen
- **Benutzer-Bearbeitungsmodal** - Dynamisches Modal für die Bearbeitung von Benutzerdaten
- **System-spezifische Benutzerlisten** - Separate Tabellen für jedes System mit relevanten Feldern
- **Refresh-Funktionalität** - Aktualisierung der Benutzerlisten für jedes System
- **Löschfunktionalität** - Bestätigungsdialoge für sicheres Löschen von Benutzern
- **Erweiterte Übersetzungen** - Neue Sprachdateien für Benutzerliste und Bearbeitung
- **ISPConfig Client-Funktionen** - Vollständige CRUD-Operationen für ISPConfig Clients
- **ServiceManager Erweiterungen** - Neue Wrapper-Funktionen für ISPConfig Client-Management

### Geändert
- `users.php` erweitert um Benutzerliste-Tab mit realen Daten
- ServiceManager um ISPConfig Client-Funktionen erweitert
- Framework.php um ISPConfig Client CRUD-Operationen erweitert
- Sprachdateien um neue Übersetzungen für Benutzerliste erweitert
- Benutzerliste zeigt API-Fehler an und behandelt leere Ergebnisse gracefully

### Behoben
- Korrekte Verwendung der ServiceManager-Funktionen für ISPConfig Clients
- Einheitliche Fehlerbehandlung für alle API-Aufrufe
- Konsistente Benutzeroberfläche für alle Systeme

## [2.9.9-beta]

### Hinzugefügt
- Neues Benutzer-Management-System mit `users.php`
- Dynamisches Multi-System-Benutzer-Erstellungsformular
- System-Auswahl für Admin Dashboard, OGP, ISPConfig und Proxmox
- **Automatische Admin Dashboard Integration** - Benutzer werden immer im Admin Dashboard erstellt
- System-spezifische Parameter und Validierung
- Admin Dashboard Integration mit Benutzergruppen
- OGP Benutzer-Erstellung mit Ablaufdatum und Home-ID
- ISPConfig Benutzer-Integration mit Client-ID
- Proxmox Benutzer-Erstellung mit Realm-Auswahl (PAM, PVE, PBS)
- **Proxmox API Integration** - Vollständige Benutzer-Management-Funktionen basierend auf offizieller API
- **Einheitliche Benutzerdaten** - Alle Systeme verwenden die gleichen Grunddaten (Username, Email, Passwort)
- **Unbegrenzte Ablaufdaten** - OGP-Benutzer haben standardmäßig keine Ablaufdaten
- Erweiterte Client-seitige Validierung für alle Systeme
- Dynamische Anzeige/Ausblendung von System-spezifischen Feldern
- Tab-Navigation für Benutzer-Erstellung und Benutzerliste
- Toast-Benachrichtigungssystem für Benutzer-Feedback
- Informationsbereich mit Tipps und Warnungen

### Geändert
- Navigation erweitert um "Benutzer"-Link mit `bi-people` Icon
- Routing-System um `?option=users` erweitert
- Switch-Statement in `index.php` um Benutzer-Option ergänzt
- Formular-Struktur für Multi-System-Unterstützung angepasst

### Behoben
- Konsistente Integration in bestehende Framework-Struktur
- Einhaltung der etablierten `inc` Datei-Struktur
- System-spezifische Validierung und Datenverarbeitung

## [2.9.8]

### Hinzugefügt
- Verbesserte OGP Games Tabelle mit gruppierten Spielen nach Namen
- Automatische Zusammenfassung von Spielvarianten (Linux/Windows, 32/64-bit)
- Farbkodierte Badges für verschiedene Betriebssystem-Varianten
- Bootstrap Icons für visuelle Unterscheidung der Varianten

### Geändert
- OGP Games Ressourcen-Anzeige: Spiele werden jetzt nach Namen gruppiert statt als separate Einträge
- Varianten-Extraktion aus `system` und `architecture` Feldern statt `game_key`
- Badge-Styling: Linux-Varianten (gelb), Windows-Varianten (grün)
- Mods werden über alle Varianten eines Spiels hinweg zusammengefasst

### Behoben
- Duplikate in der OGP Games Tabelle entfernt
- Korrekte Erkennung von Linux/Windows Varianten basierend auf Datenstruktur
- Verbesserte Benutzerfreundlichkeit durch übersichtlichere Darstellung

## [2.9.7]

### Hinzugefügt
- Gameserver-Abruf im Ressourcen-Bereich implementiert
- Erweiterte Gameserver-Funktionen für die Ausgabe
- Neue AdminCore-Funktionen für Gameserver-Management

### Behoben
- Fehler in der admincore.php behoben

## [2.9.6]

### Geändert
- GitHub Repository aufgeräumt und Dokumentation konsolidiert
- Redundante Markdown-Dateien entfernt
- README.md erweitert mit API-Beispielen und UI-Framework-Info
- Neue strukturierte Dokumentation (SECURITY.md, SUPPORT.md, CHANGELOG.md)

## [2.9.5]

### Hinzugefügt
- OGP (OpenGamePanel) API Integration
- OpenGamePanel API Unterstützung
- API-Sperrfunktion
- Framework.php Dokumentation
- Erweiterte ServerManager Funktionen für vereinfachte Nutzung
- DNS Manager für OVH im Admin Panel
- Beispiel-Datei für Framework im Standalone-Modus

### Behoben
- Bugfixes in ISPCONFIG API
- Bugfixes in Proxmox API
- Bugfixes in allen Konstrukten

## [2.9.1]

### Hinzugefügt
- AJAX IP Reverse Loader
- AdminCore Funktionen
- Sprachunterstützung
- `getAllReverseDetails` Funktion für OVH Dedicated Server und FailoverIPs
- IP-Adressen Sektor

### Geändert
- info.php für Beispiel-Output angepasst

## [2.8.5]

### Behoben
- PHP Parse Error: syntax error, unexpected 'elseif' (T_ELSEIF)

## [2.8.0]

### Hinzugefügt
- Benutzerkontrolle
- Modul-Kontrolle
- sys.conf.php Kontrolle
- Modus-Umschaltung

### Behoben
- Bugfixes im Framework
- Bugfixes im Handler
- Bugfixes in database-structure

## [2.5.0]

### Hinzugefügt
- AJAX IP Reverse Loader
- AdminCore Funktionen
- Sprachunterstützung
- `getAllReverseDetails` Funktion für OVH Dedicated Server und FailoverIPs
- IP-Adressen Sektor

### Geändert
- AJAX Requests aktualisiert
- info.php für Beispiel-Output angepasst

## [2.0.0]

### Hinzugefügt
- Login-System

### Behoben
- Bugfixes

## [1.0.0]

### Hinzugefügt
- Admin Interface
- OVH Failover IP Funktionalität
- Installations-Script
- Framework.php Updates

## [First Release]

### Hinzugefügt
- Framework erstellt

---

**Hinweis:** Das Format basiert auf [Keep a Changelog](https://keepachangelog.com/de/1.0.0/). 