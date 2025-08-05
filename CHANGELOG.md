# 📋 Changelog

Alle wichtigen Änderungen am Server Management Framework werden in dieser Datei dokumentiert.

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