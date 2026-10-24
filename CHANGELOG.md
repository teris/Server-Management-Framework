# 📋 Changelog

Alle wichtigen Änderungen am Server Management Framework werden in dieser Datei dokumentiert.



## [3.10.0] - 2026-10-24

AP31 und AP35–AP46: eigenständige Provider-/Produktmodule und vollständiger Gameserverweg.

- Deklarierte Provider-/Produkt-/UI-/Workerbeiträge; gemeinsame verschlüsselte Anschlüsse und begrenzte Fähigkeitsnachweise für OGP, ISPConfig, Proxmox und OVH.
- Gameserverangebote mit geprüften Spiel-/Moddefinitionen, Startkarte, Slots, Preisberechnung und exklusiven Portkontingenten. Bestätigte Bereitstellung, Kundensteuerung/RCON, Verlängerung, Sperre und gezielte Beendigung im gemeinsamen Zahlungs-/Rechnungs-/Vertragsweg.
- Optionale GPL-lizenzierte OGP-Erweiterung für Installationsstatus, physische Identität, zufällige Spielverzeichnisse, begrenzte Logs und bestätigte Homeentfernung. Initialisierte Startparameter umgehen den PHP-8-Fehler leerer nativer OGP-Parameter, ohne bestehende OGP-Dateien zu ersetzen.
- Physische Trennung von Webhosting- und VPS-Fachlogik, Schema, Worker- und UI-Beiträgen. Reduzierte Hosting- und Gameserverinstallationen benötigen die jeweils fremden Providerdateien nicht.
- Gemeinsame Einrichtung mit SMTP-Test an eigene Adresse und echtem Workerheartbeat; angemeldeter Upgrade-/Recoveryweg mit nativer Datenbank- und privater Konfigurationssicherung.
- Reproduzierbare, separat signierte Kern-/Commerce-/Provider-/Produktpakete. CLI prüft Schlüssel-Fingerprint, Signatur, Dateiinhalte und alle Abhängigkeiten vor Installation; fehlerhafte Modulmigrationen rollen Quelle und Datenbank zurück.
- ISPConfig-DKIM ist optional; verfügbare Funktionen und ausdrücklich hinterlegte Benutzerfreigaben bleiben getrennt. Proxmox-/OVH-Schreibfähigkeiten folgen den jeweiligen Berechtigungen. Deaktivierte oder geänderte Anschlüsse sperren alte Adapter vor Zugriffen.
- Reale, begrenzte OGP-/SteamCMD-Abnahme einschließlich laufender Half-Life-Testleistung, RCON und kontrollierter Entfernung; die ursprünglichen fünf Homes bleiben unverändert. Kein echter Zahlungsumsatz. Andere Provider-Neuanlagen sind nicht Teil dieser Liveabnahme.

Upgradehinweise, Paketprüfung, API-Grenzen und Prüfdetails: [Modulbetrieb](docs/pruefung-2026-10-24/MODULE-AP31-AP46.md). Linux/SteamCMD ist der freigegebene OGP-Direktweg; Windows, Rsync und nicht durchsetzbare RAM-/Disk-/CPU-Garantien bleiben Anfragebetrieb. Sandbox bleibt Standard.

## [3.9.9] - 2026-10-24

Öffentliches Sammelrelease des modularen Shops (AP18–AP27) und der geschützten Erstinstallation (AP32–AP34). Der bisherige interne Stand 3.3.0 wurde nicht separat veröffentlicht.

### Installation und OpenGamePanel

- Neuer Arbeitsplan AP31–AP46 für eigenständige Provider-/Produktmodule und den vollständigen Gameserver-Verkaufsablauf über OpenGamePanel.
- Geschützter Browserassistent für neue Shops ohne vorbereitete Konfiguration: Datenbank, privater Speicher, Administrator, Mailverfahren, Modulauswahl und Sandbox. CLI-Einstieg und FTP-Freigabedatei mit eigenem HTTP-Schutz.
- Lokale Installationshinweise und gezielte HTTPS-Prüfungen von ISPConfig/OGP mit bestehenden Zugangsdaten. Nicht überprüfbare Systeme werden nicht als fehlende Installation ausgegeben; ungeprüfte Anschlüsse bleiben aus.
- Wiederaufnahme nach Installationsabbruch, Schutz vorhandener Datenbanken/Konten und Ausschluss privater Installationsschlüssel aus Update-/Releasepaketen.
- Bestehende OGP-Tokenprüfung und explizite Tokenanlage übertragen Geheimnisse im POST-Body; eine fehlgeschlagene Anmeldung wird nicht als gültiger Token übernommen.
- Automatische OGP-Gameserverbereitstellung bleibt Teil der noch offenen Fachpakete. Keine erneute Installation des bestehenden Testsystems.

### Modularer Kern und Shop (bisheriger Stand 3.3.0)

AP18–AP27: modularer Kern und Übernahme des vorhandenen Online Shops. Auf dem eigenen Testsystem am 24.10.2026 aktiviert und abgenommen; dieser Stand ist in 3.9.9 enthalten.

- Namensraum-Autoloading, Servicecontainer, geprüfte Modulmanifeste und explizite, mit Prüfsummen gesicherte Migrationen. Elf Shop-Erweiterungen lassen sich unabhängig innerhalb ihrer Abhängigkeiten schalten.
- Gemeinsame Kunden-/Adminidentität, CSRF und Providerkonten für Framework und Shop. Öffentlicher Zustand ohne Infrastrukturdetails, zugeordnete Exporte und korrelierte Betriebsereignisse.
- Netzwerkänderung mit wirklichem Proxmox-Node, erhaltenen NIC-Optionen und überprüfter Cloud-Init-IP-Konfiguration statt festem `pve`.
- Bestehender Katalog, Konfigurator, Anfragen, Angebote, PayPal, Worker, Rechnungen/PDF, Verträge, Benachrichtigungen und Erweiterungsabos hinter Modulgrenzen übernommen. Betreiberwerte und private Dateien bleiben getrennt.
- PayPal prüft Betrag, Währung, Empfänger und Signatur; Zahlungs- und Ereignisreferenzen verhindern doppelte Buchungen. Sandbox und Livezugriffe sind getrennt.
- Dauerhafte Jobs mit Sperren, Phasen und Klärungszustand für unsichere Providerresultate. Abgelaufene Worker dürfen keinen Abschluss buchen; Sandbox-Aufträge können keine Live-Ressourcen anlegen.
- Prüffähiger Shopimport mit ID-Abgleich, Betragsvergleich, Schlüsselwechsel, PDF-Prüfsummen, Wiederholschutz und Wiederherstellung. Name-basierte Modulbestände des Testservers werden verlustfrei ergänzt.
- Direkte Kernanmeldungen laden die private Konfiguration vor der Sicherheitsinitialisierung; leere Verwaltungsseiten und das bisherige Kunden-Dashboard funktionieren auch beim gemeinsamen Shopzugang.
- Keine Providerzugangsdaten per Mail. Schrift-/PDF-/Mailbibliotheken und ihre Lizenzen liegen im optionalen Shop-Paket.

Installation und Grenzen: [Modularer Betrieb](docs/pruefung-2026-10-24/BETRIEB-AP18-AP27.md). Prüf- und Servernachweis: [AP18–AP27](docs/pruefung-2026-10-24/UMSETZUNG-AP18-AP27.md).

## [3.2.8] - 2026-10-24

Sicherheits- und Stabilitätsrelease des geprüften 3.2-Zweigs (AP01–AP17).

### Upgradevoraussetzungen

- **PHP >= 8.3 ist erforderlich**; die bisherige Angabe PHP >= 7.4 ist aufgehoben.
- Anwendung, Datenbank und private Konfiguration einschließlich Schlüssel gemeinsam sichern; Migration ausdrücklich mit `php bin/migrate.php` ausführen. Webinstaller und bisheriger unsignierter Web-Updater sind gesperrt.
- MongoDB ist nicht unterstützt; alte Bestätigungs-/Remember-Links werden widerrufen. Providerzugänge werden privat serverseitig gepflegt.

### Sicherheit

- Gemeinsame Identität, Rollen-/Ressourcenprüfung, CSRF, Zugriffslimits, sichere Sitzungen und rotierende gehashte Remember-Tokens.
- Diagnose-/Includezugriffe geschlossen, Dateien und Exporte an Eigentümer gebunden; private Konfiguration und Schlüssel geschützt.
- TLS-/Hostnameprüfung für Provider und SMTPS; keine Benutzerpasswörter per Mail.

### Behoben und ergänzt

- Registrierung, E-Mail-Bestätigung, erneuter Versand, Ablauf/Wiederverwendung und Passwort-Rücksetzlinks.
- Verbindliches Schema mit 41 Kerntabellen, Prüfsummenmigration und CLI-Neuinstallation ohne Beispielkonten. SQL-Adapter für SQLite, MySQL/MariaDB und PostgreSQL korrigiert.
- Fehlende Nutzer-/Providermethoden, Ergebnisverträge, Teilfehler und Schutz des letzten aktiven Administrators.
- Numerische/Textvalidierung, Sprachen, UTF-8, Proxmox-Rendering, statisches JavaScript und geschützte JSON-Konfiguration.
- Signierte Updateaufträge mit CLI-Worker, obligatorischer Sicherung, Kandidatenmigration, atomarem Wechsel und kontrolliertem Rückrollen.
- Lokale Browserbibliotheken, private Einstellungsdatei, korrekte Apache-2.0-Lizenz, Plattform-/Upgrade-Dokumentation und Regressionen.

Releasepaket, Upgradegrenzen und Prüfungen: [Release Notes 3.2.8](docs/releases/3.2.8.md). Der separate 3.3.0-Modulmanager und das spätere Shopsystem sind nicht enthalten.

## [3.2.7]

### Hinzugefügt

#### Erweiterte ISPConfig-API-Funktionen
- **37 neue ISPConfig-Funktionen** über den ServiceManager hinzugefügt
- **DNS-Funktionen (13 Funktionen)**:
  - `getISPConfigDNSZones()` - DNS-Zonen abrufen
  - `getISPConfigDNSZone($zoneId)` - Einzelne DNS-Zone abrufen
  - `getISPConfigDNSARecords()` - A-Records abrufen
  - `getISPConfigDNSAAAARecords()` - AAAA-Records abrufen
  - `getISPConfigDNSCNAMERecords()` - CNAME-Records abrufen
  - `getISPConfigDNSMXRecords()` - MX-Records abrufen
  - `getISPConfigDNSTXTRecords()` - TXT-Records abrufen
  - `createISPConfigDNSZone($zoneData)` - DNS-Zone erstellen
  - `createISPConfigDNSARecord($recordData)` - A-Record erstellen
  - `createISPConfigDNSCNAMERecord($recordData)` - CNAME-Record erstellen
  - `createISPConfigDNSMXRecord($recordData)` - MX-Record erstellen
  - `createISPConfigDNSTXTRecord($recordData)` - TXT-Record erstellen
  - `updateISPConfigDNSARecord($recordId, $recordData)` - A-Record bearbeiten
  - `deleteISPConfigDNSARecord($recordId)` - A-Record löschen

- **Mail-Funktionen (12 Funktionen)**:
  - `getISPConfigMailDomains()` - Mail-Domains abrufen
  - `getISPConfigMailAliases()` - Mail-Aliases abrufen
  - `getISPConfigMailForwards()` - Mail-Forwards abrufen
  - `getISPConfigMailCatchalls()` - Mail-Catchalls abrufen
  - `getISPConfigMailBlacklists()` - Mail-Blacklists abrufen
  - `getISPConfigMailWhitelists()` - Mail-Whitelists abrufen
  - `createISPConfigMailDomain($domainData)` - Mail-Domain erstellen
  - `createISPConfigMailAlias($aliasData)` - Mail-Alias erstellen
  - `createISPConfigMailForward($forwardData)` - Mail-Forward erstellen
  - `createISPConfigMailCatchall($catchallData)` - Mail-Catchall erstellen
  - `updateISPConfigMailAlias($aliasId, $aliasData)` - Mail-Alias bearbeiten
  - `deleteISPConfigMailAlias($aliasId)` - Mail-Alias löschen

- **Sites-Funktionen (12 Funktionen)**:
  - `getISPConfigFTPUsers()` - FTP-Users abrufen
  - `getISPConfigShellUsers()` - Shell-Users abrufen
  - `getISPConfigCronJobs()` - Cron-Jobs abrufen
  - `getISPConfigDatabaseUsers()` - Database-Users abrufen
  - `getISPConfigWebSubdomains()` - Web-Subdomains abrufen
  - `getISPConfigWebAliasdomains()` - Web-Aliasdomains abrufen
  - `createISPConfigFTPUser($userData)` - FTP-User erstellen
  - `createISPConfigShellUser($userData)` - Shell-User erstellen
  - `createISPConfigCronJob($jobData)` - Cron-Job erstellen
  - `createISPConfigDatabaseUser($userData)` - Database-User erstellen
  - `createISPConfigWebSubdomain($subdomainData)` - Web-Subdomain erstellen
  - `createISPConfigWebAliasdomain($aliasdomainData)` - Web-Aliasdomain erstellen
  - `updateISPConfigFTPUser($userId, $userData)` - FTP-User bearbeiten
  - `updateISPConfigCronJob($jobId, $jobData)` - Cron-Job bearbeiten
  - `deleteISPConfigFTPUser($userId)` - FTP-User löschen
  - `deleteISPConfigCronJob($jobId)` - Cron-Job löschen

#### ISPConfig-API-Erweiterungen
- **ISPConfigGet-Klasse erweitert**: 37 neue GET-Methoden für DNS, Mail und Sites
- **ISPConfigPost-Klasse erweitert**: 37 neue POST/PUT/DELETE-Methoden für DNS, Mail und Sites
- **Vollständige DNS-Record-Unterstützung**: A, AAAA, CNAME, MX, TXT, NS, PTR, SRV, HINFO, NAPTR, RP, DS, Alias
- **Erweiterte Mail-Funktionalität**: Domains, Aliases, Forwards, Catchalls, Blacklists, Whitelists, Spamfilter
- **Sites-Management**: FTP-Users, Shell-Users, Cron-Jobs, Database-Users, Web-Subdomains, Aliasdomains

#### Dokumentation und Beispiele
- **ISPConfig_Funktionen_Übersicht.md**: Vollständige Dokumentation aller neuen Funktionen
- **Beispiel-Verwendung**: Detaillierte Code-Beispiele für alle Funktionskategorien
- **API-Referenz**: Strukturierte Übersicht mit GET/POST-Funktionen

#### Technische Verbesserungen
- **Konsistente API-Struktur**: Alle Funktionen folgen dem gleichen Muster
- **Erweiterte Fehlerbehandlung**: Strukturierte Antworten mit Erfolg/Fehler-Status
- **API-Validierung**: Automatische Prüfung ob ISPConfig aktiviert ist
- **Sichere Aufrufe**: Verwendung der `safeAPICall`-Methode für alle neuen Funktionen
- **Filter-Unterstützung**: Alle GET-Funktionen unterstützen optionale Filter-Parameter

### Behoben

#### ServiceManager-Integration
- **safeAPICall-Parameter korrigiert**: Alle neuen Funktionen verwenden korrekte Parameter-Reihenfolge
- **API-Name-Validierung**: Konsistente Verwendung von 'ispconfig' als API-Name
- **Linter-Fehler behoben**: Alle Type-Hint-Warnungen für safeAPICall-Aufrufe behoben

### Technische Details
- **Framework-Erweiterung**: 37 neue Methoden in der ServiceManager-Klasse
- **ISPConfig-API-Abdeckung**: Vollständige Abdeckung der ISPConfig 3.x Remote-API
- **Rückwärtskompatibilität**: Alle bestehenden Funktionen bleiben unverändert
- **Code-Qualität**: Keine neuen Linter-Fehler, konsistente Code-Struktur

## [3.2.6]

### Behoben

#### Windows-spezifische Fehler
- **"Undefined array key 'node'" Fehler behoben**: Kritischer Fehler in `framework.php` Zeilen 557 und 577 behoben
- **getNodes() Methode korrigiert**: Gibt jetzt nur das `data` Array zurück statt der gesamten API-Antwort
- **Array-Key-Prüfung hinzugefügt**: `isset($nodeData['node'])` Prüfung verhindert "Undefined array key" Warnungen
- **Konsistente Datenstruktur**: Einheitliche Behandlung von Node-Daten zwischen `getVMs()` und `getLXCs()` Methoden
- **Disk-Space-Funktionen Windows-kompatibel**: `disk_free_space()` und `disk_total_space()` Fehler in `AdminCore.php` behoben
- **Windows-Pfade hinzugefügt**: XAMPP, WAMP, Laragon und Benutzer-Pfade für Windows-Umgebungen
- **Robuste Pfad-Prüfung**: `is_dir()` und `is_readable()` Prüfungen vor Disk-Space-Abfragen
- **Array to String Conversion behoben**: "Array to string conversion" Fehler in `DatabaseManager.php` Zeile 170 behoben
- **Robuste Parameter-Verarbeitung**: Arrays werden automatisch zu JSON-Strings konvertiert in execute-Methoden

#### Technische Verbesserungen
- **Robuste Fehlerbehandlung**: Verbesserte Behandlung von API-Fehlern in der Proxmox-Integration
- **Windows-Kompatibilität**: Spezifische Behebung für Windows-Umgebungen (XAMPP, etc.)
- **Code-Stabilität**: Verhindert PHP-Warnungen bei fehlenden Array-Keys

### Geändert
- **getNodes() Rückgabewert**: Gibt jetzt `$response['data']` statt `$response` zurück
- **Node-Datenverarbeitung**: `isset()` Prüfung für 'node' Key in allen Node-Iterationen
- **Fehlerbehandlung**: Leere Arrays statt Fehler-Objekte bei API-Problemen

### Technische Details
- **Betroffene Methoden**: `getNodes()`, `getVMs()`, `getLXCs()`, `getDiskUsage()`, `execute()`
- **Betroffene Dateien**: `framework.php` (Zeilen 528-545, 552, 572), `src/core/AdminCore.php` (Zeilen 844-890), `src/core/DatabaseManager.php` (alle execute-Methoden)
- **Windows-Pfade**: C:\, XAMPP, WAMP, Laragon, Benutzer-Desktop und -Dokumente
- **Fehlerbehandlung**: @ Operator für disk-Space-Funktionen, Pfad-Existenz-Prüfung, Array-zu-JSON-Konvertierung
- **Datenbanktreiber**: MySQL, PostgreSQL, SQLite - alle mit robuster Parameter-Verarbeitung
- **Kompatibilität**: Rückwärtskompatibel, keine Breaking Changes

## [3.2.5] - 2024-12-19

### Proxmox-Modul - Erweiterte VM/Container-Verwaltung

#### Hinzugefügt
- **LXC-Container-Unterstützung**: Vollständige Unterstützung für LXC-Container neben QEMU VMs
- **Node-Management**: Detaillierte Node-Übersicht mit Status-Informationen und VM/Container-Zählung
- **Storage-Details**: Umfassende Storage-Informationen mit Inhalt und Verfügbarkeit
- **Bestätigungs-Modals**: Sichere Bestätigung für kritische Aktionen (Start/Stop/Delete)
- **Erweiterte VM-Aktionen**: Reset, Resume und intelligente Button-Zustände
- **RAM-Formatierung**: Anzeige von RAM-Größen in GB mit Dezimalstellen
- **Uptime-Berechnung**: Automatische Berechnung und Anzeige der Laufzeit
- **Framework-Erweiterungen**: ServiceManager-Unterstützung für QEMU und LXC

#### Geändert
- **Tab-basierte Navigation**: Ersetzt Modal-Overlays durch intuitive Tab-Navigation
- **VM/Container-Liste**: Verbesserte Darstellung mit Typ-Unterscheidung
- **Button-Logik**: Intelligente Aktivierung/Deaktivierung basierend auf Status und Typ
- **API-Integration**: Optimierte Nutzung der Proxmox VE REST API
- **Fehlerbehandlung**: Verbesserte Fehlermeldungen und Benutzer-Feedback

#### Technische Verbesserungen
- **Framework-Integration**: Erweiterte ServiceManager-Klasse für LXC-Unterstützung
- **API-Endpunkte**: Vollständige Abdeckung aller Proxmox-API-Funktionen
- **Code-Struktur**: Modularisierte JavaScript-Architektur
- **Performance**: Optimierte Datenladung und Caching

#### Bekannte Einschränkungen
- **VM/Container-Erstellung**: Noch nicht implementiert (geplant für v1.2.0)
- **VM/Container-Bearbeitung**: Konfigurationsänderungen noch nicht verfügbar
- **Bulk-Operationen**: Mehrere VMs/Container gleichzeitig verwalten noch nicht möglich

## [3.2.4]

### Proxmox-Modul - Vollständige Überarbeitung

#### Hinzugefügt
- **Server-Management-Hauptseite**: Übersichtliche Liste aller Proxmox-Server mit Status-Anzeige
- **Server-Management-Modal**: Detaillierte Server-Verwaltung mit Start/Stop/Restart/Edit/Delete-Funktionen
- **Server-Bearbeitungs-Interface**: Umfassende Konfigurationsmöglichkeiten für Server-Einstellungen
- **Lösch-Bestätigung mit Status-Prüfung**: Server müssen gestoppt sein, bevor sie gelöscht werden können
- **VM-Erstellen-Modal**: Benutzerfreundliche Oberfläche zum Erstellen neuer virtueller Maschinen
- **Echtzeit-Status-Updates**: Automatische Aktualisierung der Server-Liste nach Aktionen
- **Responsive Design**: Optimiert für verschiedene Bildschirmgrößen

#### Geändert
- **Proxmox-Modul Übersetzungen**: Vollständige Umstellung auf die neue globale `t()`-Funktion
- **Proxmox-Modul Template**: Komplett überarbeitete Benutzeroberfläche mit modernem Design
- **Proxmox-Modul JavaScript**: Vereinfachte Übersetzungslogik und verbesserte Funktionalität
- **Modul-System**: Konsistente Verwendung der globalen `t()`-Funktion in allen Modulen

#### Technische Details
- **Entfernte Funktionen**: Manuelle Übersetzungs-Arrays und get_translations-Ajax-Aktion
- **Vereinfachte Templates**: Keine manuellen Übersetzungs-Arrays mehr erforderlich
- **Automatische Modul-Erkennung**: Proxmox-Modul nutzt jetzt die automatische Modul-Erkennung der globalen `t()`-Funktion
- **Konsistente Übersetzungen**: Alle Template-Strings verwenden jetzt die globale `t()`-Funktion
- **Neue Ajax-Aktionen**: get_vms, start_vm, stop_vm, restart_vm, delete_vm, update_vm

#### Verbesserungen
- **Wartbarkeit**: Einfachere Wartung durch zentrale Übersetzungslogik
- **Performance**: Reduzierte Server-Last durch Entfernung redundanter Übersetzungsabfragen
- **Konsistenz**: Einheitliche Übersetzungsbehandlung in allen Modulen
- **Benutzerfreundlichkeit**: Intuitive Bedienung mit klaren visuellen Hinweisen
- **Sicherheit**: Status-Prüfung vor kritischen Aktionen wie dem Löschen von Servern

## [3.2.3]

### File-Editor Modul - Verbesserungen

#### Behoben
- **Code-Ausführung verhindert**: PHP/HTML/JavaScript-Code wird nicht mehr im Hintergrund ausgeführt beim Öffnen von Dateien
- **Sichere Content-Übertragung**: Dateiinhalt wird nur als reiner Text angezeigt, keine Interpretation oder Ausführung
- **ACE Editor Fehler behoben**: `ace.edit can't find div #file-editor-ace` Fehler behoben
- **Robuste Fallback-Lösung**: Automatischer Wechsel zur Textarea bei ACE Editor Problemen

#### Geändert
- **Backup-Formatierung**: Backup-Dateien verwenden jetzt das Format `<dateiname>.<dateiendung>.bnk` anstatt Zeitstempel
- **Automatisches Überschreiben**: Backup-Dateien werden ohne Aufforderung überschrieben
- **Verbesserte Sicherheit**: Getrennte Datenebenen für HTML und JavaScript

#### Technische Verbesserungen
- **Sichere JavaScript-Initialisierung**: Inhalt wird aus Textarea geladen, nicht direkt eingebettet
- **Debug-Logs**: Erweiterte Logging-Funktionen für bessere Fehlerdiagnose
- **Element-Prüfung**: Robuste Prüfung auf DOM-Element-Existenz vor ACE Editor Initialisierung

### Sicherheit
- **Keine Code-Ausführung**: Alle Dateitypen werden nur als reiner Text behandelt
- **HTML-Escaping**: Alle Inhalte werden sicher escaped
- **Sichere Übertragung**: Keine direkte Einbettung von potentiell gefährlichem Code in JavaScript

## [3.2.2]

### Hinzugefügt

- Template erstellen:
1. Navigieren Sie zu **E-Mail-Templates** im Backend
2. Klicken Sie auf **"Neues Template"**
3. Geben Sie einen Template-Namen und E-Mail-Betreff ein
4. Wählen Sie zwischen **HTML** oder **Raw Text**
5. Geben Sie den Template-Inhalt ein
6. Verwenden Sie den **Style-Editor** für visuelle Anpassungen
7. Speichern Sie das Template

- Verfügbare Variablen:
Folgende Variablen können in Templates verwendet werden:

- `{firstName}` - Vorname des Empfängers
- `{lastName}` - Nachname des Empfängers
- `{email}` - E-Mail-Adresse des Empfängers
- `{username}` - Benutzername
- `{password}` - Passwort
- `{loginUrl}` - Login-URL
- `{verificationLink}` - Verifikations-Link
- `{site_name}` - Website-Name
- `{systemCredentials}` - System-Anmeldedaten (HTML)
- `{support_email}` - Support-E-Mail
- `{system_email}` - System-E-Mail

Standard-Templates:
Das System erstellt automatisch folgende Standard-Templates:

1. **Kunden-Willkommens-E-Mail** - Für sofort aktivierte Kunden
2. **Kunden-Verifikations-E-Mail** - Für E-Mail-Verifikation
3. **Backend-Benutzer-Willkommens-E-Mail** - Für Backend-Benutzer
4. **System-Anmeldedaten-E-Mail** - Mit System-Anmeldedaten

### Template-Variablen erweitern

Um neue Variablen zu verwenden:

1. Fügen Sie sie in das `variables`-Feld des Templates ein
2. Übergeben Sie sie beim Aufruf der Template-Funktion
3. Verwenden Sie sie im Template-Inhalt mit `{variableName}`

### Sicherheit

- Alle Template-Inhalte werden vor der Ausgabe escaped
- SQL-Injection-Schutz durch Prepared Statements
- XSS-Schutz durch HTML-Escaping
- CSRF-Schutz durch Session-Validierung

### Technische Details

- Datenbank-Schema
```sql
CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_name` varchar(255) NOT NULL,
  `subject` text NOT NULL,
  `html_content` longtext,
  `raw_content` longtext,
  `variables` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
```

## [3.2.1]

### Geändert
- Sprachdatein aktuallsiert und fehlerhafte `t()` abfragen aktuallisiert

### Hinzugefügt
- Debugabfrage für `t()` erstellt.
-- Nutzung über `print_r(t('debug', null, null, true));` für fehlende Sprachübersetzungen

## [3.2.0]

### Hinzugefügt
- **Erweiterte Benutzer-Dialogfelder**: Vollständige Anzeige aller Benutzerinformationen aus der Users-Tabelle
- **Erweiterte Kunden-Dialogfelder**: Vollständige Anzeige aller Kundeninformationen aus der Customers-Tabelle
- **Kundenbearbeitungs-Modal**: Neues Modal für die Bearbeitung von Kundendaten mit allen relevanten Feldern
- **System-Verknüpfungen**: Dynamische Anzeige von OGP, Proxmox und ISPConfig-Verbindungen in Benutzerdetails
- **AJAX-Integration**: Vollständige AJAX-Unterstützung für alle Dialogfelder mit Fehlerbehandlung

### Geändert
- **Benutzer-Bearbeitungs-Modal**: Erweitert um Sicherheitsinformationen, Zeitstempel und System-Verknüpfungen
- **Benutzer-Details-Modal**: Strukturierte Darstellung mit Bereichen für Grunddaten, Sicherheit und Zeitstempel
- **Kunden-Details-Modal**: Vollständige Integration aller Customer-Tabellenfelder
- **JavaScript-Funktionen**: Erweiterte AJAX-Funktionen mit Debug-Logging und verbesserter Fehlerbehandlung
- **AJAX-Routing**: Optimierte Request-Weiterleitung für bessere Performance

### Technische Details
- **Modal-Größen**: Alle Modals auf `modal-xl` erweitert für bessere Übersicht
- **Responsive Design**: Optimierte Darstellung für verschiedene Bildschirmgrößen
- **Zeitstempel-Formatierung**: Deutsche Lokalisierung für alle Datumsfelder
- **Status-Badges**: Farbkodierte Status-Anzeige für bessere Benutzerfreundlichkeit
- **Formular-Validierung**: Backend-Validierung für alle Eingaben mit detaillierten Fehlermeldungen

### Verbesserungen
- **Benutzerfreundlichkeit**: Vollständige Informationen in allen Dialogfeldern
- **Datenintegrität**: Vollständige Integration aller MySQL-Tabellenfelder
- **Debugging**: Console-Logging für bessere Fehlerdiagnose
- **Performance**: Optimierte AJAX-Requests und Datenbankabfragen
- **Wartbarkeit**: Strukturierte Code-Organisation und Dokumentation

## [3.1.9]

### Geändert
- **Support-Tickets-Modul Übersetzungen**: Vollständige Umstellung auf die neue globale `t()`-Funktion
- **Support-Tickets-Modul Template**: Entfernung der manuellen Übersetzungs-Arrays und Vereinfachung der Template-Logik
- **Support-Tickets-Modul JavaScript**: Keine Änderungen erforderlich - bereits optimiert mit direkten deutschen Texten
- **Modul-System**: Konsistente Verwendung der globalen `t()`-Funktion in allen Modulen

### Technische Details
- **Entfernte Funktionen**: Manuelle `$translations`-Arrays aus `getContent()`-Methode
- **Vereinfachte Templates**: Keine manuellen Übersetzungs-Arrays mehr erforderlich
- **Automatische Modul-Erkennung**: Support-Tickets-Modul nutzt jetzt die automatische Modul-Erkennung der globalen `t()`-Funktion
- **Konsistente Übersetzungen**: Alle Template-Strings verwenden jetzt die globale `t()`-Funktion

### Verbesserungen
- **Wartbarkeit**: Einfachere Wartung durch zentrale Übersetzungslogik
- **Performance**: Reduzierte Server-Last durch Entfernung redundanter Übersetzungsabfragen
- **Konsistenz**: Einheitliche Übersetzungsbehandlung in allen Modulen

## [3.1.8]

### Geändert
- **DNS-Modul Übersetzungen**: Vollständige Umstellung auf die neue globale `t()`-Funktion
- **DNS-Modul Template**: Entfernung der manuellen Übersetzungs-Arrays und Vereinfachung der Template-Logik
- **DNS-Modul JavaScript**: Keine Änderungen erforderlich - bereits optimiert mit direkten deutschen Texten
- **Modul-System**: Konsistente Verwendung der globalen `t()`-Funktion in allen Modulen

### Technische Details
- **Entfernte Funktionen**: Manuelle `$translations`-Arrays aus `getContent()`, `getContentResponse()` und `selectDomain()`-Methoden
- **Vereinfachte Templates**: Keine manuellen Übersetzungs-Arrays mehr erforderlich
- **Automatische Modul-Erkennung**: DNS-Modul nutzt jetzt die automatische Modul-Erkennung der globalen `t()`-Funktion
- **Konsistente Übersetzungen**: Alle Template-Strings verwenden jetzt die globale `t()`-Funktion

### Verbesserungen
- **Wartbarkeit**: Einfachere Wartung durch zentrale Übersetzungslogik
- **Performance**: Reduzierte Server-Last durch Entfernung redundanter Übersetzungsabfragen
- **Konsistenz**: Einheitliche Übersetzungsbehandlung in allen Modulen

## [3.1.7]

### Geändert
- **Database-Modul Übersetzungen**: Vollständige Umstellung auf die neue globale `t()`-Funktion
- **Database-Modul Template**: Entfernung der manuellen Übersetzungs-Arrays und JavaScript-Übersetzungslogik
- **Database-Modul JavaScript**: Vereinfachte Initialisierung ohne Server-seitige Übersetzungsabfrage
- **Modul-System**: Konsistente Verwendung der globalen `t()`-Funktion in allen Modulen

### Technische Details
- **Entfernte Funktionen**: `getTranslations()`-Methode und `get_translations`-Ajax-Aktion
- **Vereinfachte Templates**: Keine manuellen `$translations`-Arrays mehr erforderlich
- **Automatische Modul-Erkennung**: Database-Modul nutzt jetzt die automatische Modul-Erkennung der globalen `t()`-Funktion
- **JavaScript-Optimierung**: Reduzierte Komplexität der Client-seitigen Übersetzungslogik
- **Hardcodierte Texte**: JavaScript-Funktionen verwenden jetzt direkte deutsche Texte statt Übersetzungsabfragen

## [3.1.6]

### Geändert
- **Custom-Modul Übersetzungen**: Vollständige Umstellung auf die neue globale `t()`-Funktion
- **Custom-Modul Template**: Entfernung der manuellen Übersetzungs-Arrays und JavaScript-Übersetzungslogik
- **Custom-Modul JavaScript**: Vereinfachte Initialisierung ohne Server-seitige Übersetzungsabfrage
- **Modul-System**: Konsistente Verwendung der globalen `t()`-Funktion in allen Modulen

### Technische Details
- **Entfernte Funktionen**: `getTranslations()`-Methode und `get_translations`-Ajax-Aktion
- **Vereinfachte Templates**: Keine manuellen `$translations`-Arrays mehr erforderlich
- **Automatische Modul-Erkennung**: Custom-Modul nutzt jetzt die automatische Modul-Erkennung der globalen `t()`-Funktion
- **JavaScript-Optimierung**: Reduzierte Komplexität der Client-seitigen Übersetzungslogik

## [3.1.5]

### Hinzugefügt
- **API-Verbindungstest-Funktion**: Neue `testAllAPIConnections()` Methode in der `ServiceManager`-Klasse
  - Testet alle verfügbaren APIs (Proxmox, OVH, ISPConfig, OGP, Database)
  - Unterstützt sowohl Einzeltests als auch Gesamttests aller APIs
  - Gibt detaillierte Statusinformationen und Fehlermeldungen zurück
  - Beispiel-Verwendung: `$serviceManager->testAllAPIConnections("ovh")` für einzelne API oder `$serviceManager->testAllAPIConnections()` für alle APIs
- **Gameserver-Tab im Admin-Panel**: Neuer Tab in der Ressourcen-Verwaltung
  - Übersichtliche Statistik-Karten für Gameserver-Metriken
  - Anzeige von Gesamt-Servern, Online-Servern, Spielern und aktiven Servern
  - Vorbereitet für JavaScript-Integration mit `loadGameserverData()` und `createGameserver()`
  - Responsive Design mit Bootstrap-Karten und Icons

### Geändert
- **Admin-Modul Template**: `ServiceManager`-Instanz wird jetzt korrekt an Templates weitergegeben
- **Header-Template**: API-Status-Anzeige zeigt jetzt "Verbunden" oder "Nicht verbunden" statt `true`/`false`
- **Framework-Integration**: Verbesserte Fehlerbehandlung bei fehlenden ServiceManager-Instanzen
- **Admin-Modul Übersetzungen**: Vollständige Umstellung auf die neue globale `t()`-Funktion
- **Template-System**: Entfernung aller hardcodierten Übersetzungs-Arrays aus Templates

### Behoben
- **Doppelte Methodendeklaration**: Entfernung der duplizierten `createOGPServer()` Methode in der `ServiceManager`-Klasse
- **Template-Variable-Fehler**: `$serviceManager` ist jetzt in allen Admin-Templates verfügbar
- **API-Test-Integration**: Korrekte Einbindung der API-Tests in das Admin-Panel

### Technische Details
- **API-Test-Funktionen**: 
  - `testSingleAPI($apiName)` - Testet eine spezifische API
  - `testAllAPIConnections($apiName = null)` - Testet alle oder eine spezifische API
  - Unterstützte APIs: `proxmox`, `ovh`, `ispconfig`, `ogp`, `database`
- **Template-Struktur**: Fallback-Logik für ServiceManager-Instanzen in Templates
- **Übersetzungen**: Neue deutsche Übersetzungen für Gameserver-Funktionen

## [3.1.4]

### Geändert
- Linter Warnung in `src/inc/users.php` entfernt

### Technischer Test
- Versionprüfung mithilfe von GitHub und testSystem ausgeführt

## [3.1.3]

### Hinzugefügt
- Manual Updater (BETA): Neue Sektion zur Anzeige des letzten GitHub-Commits inkl. geänderter Dateien und selektivem Anwenden einzelner Dateien

### Geändert
- Manual Updater (BETA): Branch für Commit-Ansicht und selektives Update fest auf `main` gesetzt
- Versions-Update-Logik: Setzt Version über den `// --- SYSTEM_CONFIG START/END ---` Block in `src/sys.conf.php` (konsistent mit `src/inc/system.php`)

### Behoben
- Pfadkorrektur beim Schreiben der Version aus dem Manual Updater

## [3.1.2]

### Hinzugefügt
- Manual Updater mit folgenden Funktionen:
  - Automatische Versionsprüfung über GitHub API (Latest und Tag-basierte Releases)
  - Versionsvergleich mit `CHANGELOG.md` und Nightly-Erkennung (Stable vs. Nightly)
  - Update-Typen: Framework Only und Vollständiges Update (Asset-Auswahl je nach Typ)
  - Optionales Backup vor Installation (Dateien und – optional – Datenbank)
  - Mehrsprachigkeit (DE/EN) via Framework-`t()` Integration
- Backup-Management im Admin-Panel:
  - Erstellung von ZIP-Backups mit Datei- und optionalem Datenbank-Inhalt
  - Auflistung vorhandener Backups inkl. Größe, Datum und Aktionen (Download/Löschen)
  - Debug-/Test-Tools: Debug-Infos, ZIP-Test, schrittweiser Backup-Test
- UI/UX für Updates:
  - Systeminfo (PHP-Version, ZIP-Status, Temp-Verzeichnis)
  - Auswahl des Update-Typs, Fortschrittsbalken und Live-Log-Ausgabe

### Geändert
- Kopfkommentar in `src/inc/manualupdater.php` aktualisiert: Projekttitel auf "Server Management Framework" und Version `3.1.2`.

### Behoben
- Intelephense-Hinweis PHP6613 in `src/inc/manualupdater.php` unterdrückt mittels `// @intelephense-ignore-file` (reine Editor-/Analyse-Anpassung, keine Laufzeitänderung).

### Technische Details
- GitHub REST API v3: `releases/latest` und `releases/tags/{tag}`
- Asset-Auswahl: `framework-standalone.zip` für Framework-Only; sonst Haupt-Release-Asset
- Sicherheitsmaßnahmen: HTTPS-Downloads, ZIP-Validierung/Extraktion, Schutz von `sys.conf.php` bei Vollupdates, automatische Bereinigung temporärer Dateien

### Systemanforderungen
- PHP-Extension: `zip` (Pflicht für ZIP-Operationen)
- Netzwerkzugriff für GitHub API (über `file_get_contents` oder `curl`)
- Schreibrechte auf Framework- und Temp-Verzeichnisse

### Dokumentation
- `src/inc/README_manualupdater.md` ergänzt: Nutzung, Features, Sicherheitshinweise und Best Practices.

## [3.1.1] - Modulupdate

### DNS-Modul - Komplette Überarbeitung

#### Benutzeroberfläche
- **Formulare an die Spitze**: DNS-Formulare werden jetzt automatisch oben auf der Seite angezeigt
- **Inline-Bearbeitung**: DNS-Records können direkt in der Tabelle bearbeitet werden
- **Dynamische Formularanzeige**: "Record Hinzufügen" Button und Zone-Management nur bei ausgewählter Domain sichtbar
- **Ladebalken**: Visueller Fortschrittsbalken beim Laden der Domains
- **Responsive Design**: Optimierte Darstellung für verschiedene Bildschirmgrößen

#### Performance & Stabilität
- **Automatisches Laden**: Domains werden beim Modulaufruf automatisch geladen
- **Verhindert Doppelklicks**: Schutz vor mehrfachen API-Aufrufen bei Add/Delete-Operationen
- **Optimierte API-Calls**: Reduzierte Anzahl von API-Aufrufen durch intelligentes Caching
- **Robuste Fehlerbehandlung**: Verbesserte Behandlung von API-Fehlern und ungültigen Daten

#### Backend-Verbesserungen
- **Zone-Export**: Generierung von DNS-Zone-Dateien im Standard-Format
- **Download-Funktionalität**: Automatischer Download der exportierten Zone-Dateien
- **Verbesserte Validierung**: Robuste Datenvalidierung für alle DNS-Record-Typen
- **Activity-Logging**: Verwendung der Framework-`__log()` Funktion für bessere Nachverfolgung
- **Typsichere Array-Zugriffe**: Behebung aller PHP-Warnungen bei Array-Zugriffen

#### Code-Organisation
- **CSS-Externalisierung**: Alle CSS-Styles in separate `style.css` Datei ausgelagert
- **Modulare JavaScript-Struktur**: Verbesserte Event-Delegation und Code-Organisation
- **Saubere Trennung**: Klare Trennung zwischen Frontend und Backend-Logik

#### Fehlerbehebungen
- **"Keine Domains verfügbar" Meldung**: Entfernt, da nicht mehr benötigt
- **Doppelte DNS-Record-Erstellung**: Verhindert durch Request-Flags
- **Lösch-Loop**: Behebung des kontinuierlichen Bestätigungsdialogs
- **Domain-Dropdown-Laden**: Robuste DOM-Erkennung und Fallback-Mechanismen
- **Download-URL-Fehler**: Korrektur der doppelten Slashes in Download-URLs

#### Neue Features
- **Zone-Import/Export**: Vollständige DNS-Zone-Verwaltung
- **Dynamische UI-Elemente**: Buttons und Sektionen werden basierend auf Kontext angezeigt
- **Verbesserte Benutzerführung**: Intuitivere Bedienung durch visuelle Rückmeldungen
- **Standard-DNS-Format**: Export im RFC-konformen DNS-Zone-Format

### Geändert
- Anpassung der ISPConfig verwaltung, vorbereitet auf Version 2.0.0
- migrationsmodul vorbereitet und testweise angepasst

## [3.1.0]

### Hinzugefügt
- **Multi-Datenbank-Support**: Separate SQL-Dateien für MySQL, PostgreSQL und SQLite
- **Optimierte Indizes**: Performance-optimierte Indizes für alle häufig verwendeten Abfragen
- **Views**: Übersichts-Views für komplexe Abfragen (user_activity_overview, vm_overview, website_overview)
- **Verbesserte Constraints**: Vollständige Foreign Key Constraints für Datenintegrität
- **Standarddaten**: Grunddaten für sofortige Nutzung nach Installation
- **Dokumentation**: Umfassende Dokumentation und Migrationsleitfaden

### Geändert
- **Tabellenstruktur**: Optimierte Spaltentypen und -größen
- **Namenskonventionen**: Einheitliche Namenskonventionen für alle Tabellen und Spalten
- **Auto-Increment**: Konsistente Auto-Increment Konfiguration
- **Timestamps**: Standardisierte created_at und updated_at Felder

### Entfernt
- **Nicht verwendete Tabellen**: Entfernung von 10 nicht im System verwendeten Tabellen:
  - `backup_jobs` - Backup-Jobs (nicht im Code verwendet)
  - `domain_extensions` - Domain-Erweiterungen (nicht verwendet)
  - `groups` - Gruppen (nicht verwendet)
  - `group_module_permissions` - Gruppen-Modul-Berechtigungen (nicht verwendet)
  - `module_configs` - Modul-Konfigurationen (nicht verwendet)
  - `module_dependencies` - Modul-Abhängigkeiten (nicht verwendet)
  - `module_permissions` - Modul-Berechtigungen (nicht verwendet)
  - `network_config` - Netzwerk-Konfiguration (nicht verwendet)
  - `server_resources` - Server-Ressourcen (nicht verwendet)
  - `active_modules` - Aktive Module (nicht verwendet)

### Behoben
- **Datenintegrität**: Vollständige Referential Integrity durch Foreign Keys
- **Performance**: Optimierte Abfragen durch bessere Indizierung
- **Wartbarkeit**: Konsistente Struktur für einfachere Wartung

### Technische Details
- MySQL/MariaDB als Hauptdatenbank
- UTF8 Zeichensatz
- InnoDB Engine
- Basis-Indizes
- Grundlegende Constraints

#### MySQL/MariaDB (`database-structure-optimized.sql`)
- UTF8MB4 Zeichensatz für vollständige Unicode-Unterstützung
- InnoDB Engine für Transaktionssicherheit
- Optimierte Indizes für alle Abfrage-Patterns
- Views für komplexe Übersichtsabfragen

#### PostgreSQL (`database-structure-postgresql.sql`)
- Native ENUM-Typen für bessere Typsicherheit
- JSONB für Konfigurationsdaten
- SERIAL für Auto-Increment Felder
- Trigger für automatische updated_at Aktualisierung
- PostgreSQL-spezifische Views

#### SQLite (`database-structure-sqlite.sql`)
- CHECK Constraints für ENUM-Simulation
- INTEGER PRIMARY KEY für Auto-Increment
- Trigger für updated_at Felder
- SQLite-optimierte Views
- WAL-Modus für bessere Performance

## [3.0.9]

### Fehlerbehebung

- Model-Klasse erweitert: Der Konstruktor akzeptiert jetzt einen optionalen $data Parameter
- Daten-Speicherung hinzugefügt: Eine $data Eigenschaft zum Speichern der übergebenen Daten
- Hilfsmethoden hinzugefügt: getData() und setData() Methoden für den Zugriff auf die Daten
- Fehlende Klasse hinzugefügt: ActivityLog Klasse hinzugefügt, die von Model erbt

### Geändert
- bind_param("ss", $admin_user, $admin_email) → execute([$admin_user, $admin_email])
- get_result() → fetchAll()
- num_rows → count($result)
- close() entfernt (nicht nötig bei PDO)
- $conn->ping() durch !$conn->connect_error ersetzt
- Null-Check für den Rückgabewert von getDashboardStats()
- getConnection() Aufrufe ersetzt

###Technische Verbesserung
- Sicherstellung, dass immer ein gültiger Wert an $this->success() übergeben wird
- MongoDBClientDummy für \MongoDB\Client
- MongoDBUTCDateTimeDummy für \MongoDB\BSON\UTCDateTime
- MongoDBDatabaseDummy für \MongoDB\Database
- MongoDBCollectionDummy für \MongoDB\Collection
- MongoDBInsertOneResultDummy für \MongoDB\InsertOneResult
- MongoDBDeleteResultDummy für \MongoDB\DeleteResult
- MongoDBCursorDummy für \MongoDB\Driver\Cursor

### Bekannte Probleme
- PHP1408 Fehler in src/core/AdminCore.php

### Hinzugefügt
- Eine neue private Methode getSafeDbConnection() wurde erstellt
- createOGPUser($userData) - Erweiterte OGP Benutzererstellung
- createProxmoxUser($userData) - Erweiterte Proxmox Benutzererstellung

## [3.0.8]

### Fehlerbehebung
- Linter-Fehler P1006: Alle "Expected type 'object'. Found 'null'" Fehler wurden behoben
- Robustheit: Zusätzliche Sicherheit durch explizite Null-Checks
- Konsistenz: Alle Proxmox-Methoden haben jetzt einheitliche Fehlerbehandlung
- Typsicherheit: Der Linter kann jetzt sicher sein, dass die Objekte nicht null sind
- Undefined property: VM::$state - Der Hauptfehler wurde durch robuste Eigenschaftsabfrage behoben
- Verbesserte Fehlerbehandlung - Alle Eigenschaftszugriffe sind jetzt sicher
- Konsistente Codestruktur - Einheitliche Behandlung von Objekten und Arrays

## [3.0.7]

### Fehlerbehebung
- Fehler im Regestationsporzess der Systemanmeldungen
- Fehler in der Verwendung von createISPConfigUser()

### Benutzerregestration
- Benutzer werden nun automatisch per E-Mail benachrichtigt
- Verifizierungsprozess erzeugt benutzer inkl. Random Passwörter in den Systemen
- Login in die Systeme einzelnd möglich

### Sicherheit
- Verifizierung ist nur 24h Gültig
- Bei Aktivierung des Benutzers aus dem BackEnd, werden keine weiteren System-Benutzer angelegt

## [3.0.6]
### Fehlerbehbung
- Der DatabaseManager hatte zwei kritische Fehler, die zu Fatal Errors führten:
- Pfadfehler (src/inc/settings.php)
- Query-Methode Fehler (src/core/DatabaseManager.php)

### Auswirkungen der Korrektur

1. **Pfadfehler behoben:** DatabaseManager.php wird korrekt geladen
2. **Query-Methode funktioniert:** `fetchAll()` kann auf Statement-Objekt aufgerufen werden
3. **Datenbankabfragen funktionieren:** Alle SELECT-Statements in settings.php laufen korrekt
4. **Keine Fatal Errors mehr:** Script läuft ohne kritische Fehler

### Sicherheitshinweise

- Alle Änderungen sind rückwärtskompatibel
- Keine Änderungen an der API-Schnittstelle
- Bestehender Code funktioniert unverändert weiter
- Keine Breaking Changes


## [3.0.5] Major Release

### Neue Features

#### Datenbank-Abstraktionsschicht (DAL)
- **Neue Datenbankunterstützung**: Vollständige Unterstützung für MySQL/MariaDB, PostgreSQL, SQLite und MongoDB
- **Konfigurierbare Datenbankauswahl**: Neue Konstante `DB_TYPE` in `config/config.inc.php` für einfache Datenbankumschaltung
- **Neue Datei**: `src/core/DatabaseManager.php` - Zentrale Datenbankverwaltung mit abstrakten Treibern
- **Neue Datei**: `src/core/ActivityLogger.php` - Zentrales Aktivitäts-Logging-System
- **Neue Datei**: `DATABASE_MIGRATION.md` - Umfassende Dokumentation der Datenbankmigration

#### Admin-Benutzerverwaltung
- **Unified User Management**: Anzeige und Verwaltung von Admin-Benutzern und Frontpanel-Kunden in einer Oberfläche
- **Erweiterte Benutzerfilter**: Neue Filter für Benutzertyp, Status und Rolle
- **Kundenverwaltung**: Vollständige CRUD-Operationen für Frontpanel-Kunden aus dem Admin-Bereich

#### Dashboard-Erweiterungen
- **Domain-Status-Anzeige**: Neue Anzeige der Domain-Registrierungsstatus im Format `approved/rejected?pending`
- **Farbkodierte Status**: Grün für approved, Rot für rejected, Gelb für pending
- **Aktivitäts-Logging**: Umfassendes Logging aller Benutzeraktionen (Login, Support-Tickets, Domain-Registrierungen, etc.)
- **"Alle löschen" Button**: Möglichkeit, alle Benutzeraktivitäten zu löschen mit Protokollierung der Aktion

### Verbesserungen

#### Framework-Architektur
- **Datenbankabstraktion**: Alle Datenbankaufrufe über neue `DatabaseManager` Klasse
- **Kompatibilitätsschicht**: Bestehende `Database` Klasse als Wrapper für neue Architektur
- **Modulare Struktur**: Bessere Trennung von Datenbanktreibern und Geschäftslogik
- **Singleton-Pattern**: Optimierte Ressourcenverwaltung für Datenbankverbindungen

#### Support-System
- **Verbesserte Ticket-Anzeige**: Korrekte Darstellung von Admin-Antworten
- **Aktivitätsprotokollierung**: Automatisches Logging von Ticket-Erstellung und -Antworten
- **Status-Management**: Korrekte Behandlung des `is_internal` Flags für Admin-Antworten

#### Benutzerverwaltung
- **Erweiterte Admin-Funktionen**: Neue Methoden in `AdminCore` für Kundenverwaltung
- **AJAX-Integration**: Neue Endpunkte für Kunden-CRUD-Operationen
- **Unified Interface**: Einheitliche Benutzeroberfläche für alle Benutzertypen

### Bugfixes

#### Datenbankverbindungen
- **lastInsertId() Fehler**: Korrektur des `Call to a member function lastInsertId() on null` Fehlers
- **Verbindungsverwaltung**: Behebung von Problemen mit der Datenbankverbindung in `framework.php`
- **Pfad-Probleme**: Korrektur der `require_once` Pfade für `DatabaseManager.php`

#### Support-System
- **SQL-Syntax-Fehler**: Behebung des `#1064` SQL-Syntax-Fehlers in `public/support.php`
- **Parameter-Fehler**: Korrektur des `SQLSTATE[HY093]: Invalid parameter number` Fehlers
- **Admin-Antworten**: Behebung der fehlenden Anzeige von Administrator-Antworten
- **is_internal Flag**: Korrektur der Logik für das `is_internal` Flag bei Admin-Antworten

#### Passwort-Management
- **Spaltenname-Fehler**: Korrektur von `password` zu `password_hash` in `public/change-password.php`
- **Aktivitätsprotokollierung**: Integration des `ActivityLogger` für Passwortänderungen

#### Admin-Panel
- **AJAX-Aktionen**: Behebung der fehlenden `get_all_users` Aktion in `AdminHandler`
- **Benutzer-Management-Methoden**: Implementierung der fehlenden CRUD-Methoden für Kunden

### Neue Dateien

- `src/core/DatabaseManager.php` - Zentrale Datenbankverwaltung
- `src/core/ActivityLogger.php` - Aktivitäts-Logging-System
- `DATABASE_MIGRATION.md` - Migrationsdokumentation
- `public/clear-activities.php` - Aktivitäten-Löschung-Endpunkt

### Geänderte Dateien

#### Konfiguration
- `config/config.inc.php` - Neue `DB_TYPE` Konstante und Datenbankkonfigurationen

#### Core-System
- `framework.php` - Integration des neuen `DatabaseManager`
- `src/core/DatabaseOnlyFramework.php` - Aktualisierung für neue Architektur
- `src/core/AdminCore.php` - Neue Methoden für Kundenverwaltung
- `src/core/AdminHandler.php` - Neue AJAX-Aktionen für Benutzer-Management

#### Admin-Bereich
- `src/inc/users.php` - Unified User Management Interface
- `src/inc/profile.php` - Integration des `ActivityLogger`
- `src/inc/settings.php` - Aktualisierung für neue Datenbankarchitektur
- `src/module/admin/Module.php` - Neue AJAX-Handler für Kundenverwaltung

#### Frontend
- `public/dashboard.php` - Domain-Status-Anzeige und Aktivitäts-Logging
- `public/support.php` - Verbesserte Ticket-Anzeige und Aktivitätsprotokollierung
- `public/login.php` - Integration des `ActivityLogger`
- `public/domain-registration.php` - Aktivitätsprotokollierung
- `public/change-password.php` - Korrektur der Spaltennamen und Aktivitätsprotokollierung

#### Support-Module
- `src/module/support-tickets/Module.php` - Korrektur der `is_internal` Logik
- `src/module/support-tickets/templates/main.php` - Entfernung des `is_internal` Checkboxes

#### Styling
- `public/assets/frontpanel.css` - Neue CSS-Klassen für Domain-Status-Anzeige

### Technische Details

#### Datenbanktreiber
- **MySQL/MariaDB Driver**: Vollständige PDO-Integration
- **PostgreSQL Driver**: Native PostgreSQL-Unterstützung
- **SQLite Driver**: Lokale SQLite-Datenbanken
- **MongoDB Driver**: NoSQL-Datenbankunterstützung

#### Architektur-Änderungen
- **Abstrakte Basisklasse**: `DatabaseDriver` als Grundlage für alle Treiber
- **Singleton-Pattern**: `DatabaseManager` und `ActivityLogger` als Singletons
- **Kompatibilitätsschicht**: Bestehende Code funktioniert ohne Änderungen
- **Transaktionsmanagement**: Verbesserte Transaktionsbehandlung

### Breaking Changes

⚠️ **Wichtig**: Diese Version enthält keine Breaking Changes. Alle bestehenden Funktionen bleiben kompatibel.

### Bekannte Probleme

- **MongoDB Linter-Warnungen**: PHPDoc-Warnungen bei fehlender MongoDB PHP-Extension (nicht kritisch)
- **Session-Handling**: Verbesserte Session-Verwaltung für bessere Sicherheit

### Performance-Verbesserungen

- **Verbindungspooling**: Optimierte Datenbankverbindungsverwaltung
- **Query-Optimierung**: Verbesserte SQL-Abfragen mit korrekten JOINs
- **Caching**: Neue Caching-Mechanismen für häufig abgerufene Daten

### Sicherheitsverbesserungen

- **Aktivitätsprotokollierung**: Vollständige Protokollierung aller Benutzeraktionen
- **Session-Management**: Verbesserte Session-Sicherheit
- **SQL-Injection-Schutz**: Konsistente Verwendung von Prepared Statements

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