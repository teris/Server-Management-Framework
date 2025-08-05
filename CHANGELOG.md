# 📋 Changelog

Alle wichtigen Änderungen am Server Management Framework werden in dieser Datei dokumentiert.

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