# Umsetzung AP10–AP17

Stand: 24.10.2026. Grundlage: abgeschlossene AP01–AP09 (`b433a09`), lokaler Arbeitszweig `stability/ap10-ap17`. Ausgangssicherung und Betreiberkonfiguration bleiben außerhalb der Versionierung. Die Anwendung trägt weiterhin 3.2.7; Veröffentlichung und Serverupload folgen separat in AP28–AP30.

## Ergebnisse je Arbeitspaket

| Paket | Umgesetzt und abgenommen |
|---|---|
| AP10 | Registrierung, Bestätigung per CSRF-geschütztem POST, gehashte/gebundene Einmaltokens, Ablauf/Wiederverwendung, SMTP-Ausfall und erneuter Versand. Sicherer SMTPS-Transport und feste HTTPS-Linkbasis. Keine Passwortmails und keine automatische Provideranlage. Tatsächlicher HTTP-/SMTPS-Durchlauf auf frischer Installation. |
| AP11 | 41 verbindliche Kerntabellen in `database/schema.json`, nummerierte Prüfsummenmigration, neun identische treiberspezifische SQL-Abbilder, CLI-Installer ohne Beispielkonten, ausdrücklicher Upgradebefehl. Daten, Hashes, individuelle Vorlagen und zusätzliche Betreiberobjekte bleiben in den geprüften Ausgangsschemas erhalten. |
| AP12 | Tatsächliche SQL-Prüfungen auf SQLite, MySQL 8, MariaDB 10.11 und PostgreSQL 16, jeweils frisch und aus zwei historischen Schemas. Einheitliches PDO-Binding/Identifier-Behandlung, portable Zeit-/Log-/Syncabfragen. MongoDB-Dummy entfernt und Auswahl ausdrücklich abgewiesen. Datenbankmodus liest echte Cachebestände und verweigert Provideränderungen. |
| AP13 | Zehn fehlende Nutzermethoden und Provider-Signaturen ergänzt; gemeinsame lokale Kontoverwaltung, Schutz des letzten Administrators, keine Hashes/Passwörter in Antworten. Providerergebnisse normalisiert, Teillöschung erhält Konto und Zuordnungen zur Klärung. Resetlinks statt erzeugter Mailpasswörter. OGP-Bearbeitung/-Passwort ausdrücklich nicht angeboten. |
| AP14 | Gemeinsame numerische/Textvalidierung, Pflichtwert-/Null-/Array-/Unendlichkeitsgrenzen, normalisierte Sprachen und sichere Sprachpfade/Fallbacks. UTF-8 ohne BOM und PHP-Parseprüfung mit kurzen Tags ein und aus. |
| AP15 | Signaturpflichtiger Kern-/Vollupdater mit festem Auftrag und Version/Commit/Manifest. Web lädt/plant, CLI bereitet vor und aktiviert mit Sperre, DB-/Privatdateisicherung, realer Kandidatenmigration und atomarem Symlink. Konfiguration/Schlüssel und Pluginversionen erhalten. Abbruch/Wiederholung, manipulierte Pakete, Schreibschutz, Rückrollen und fehlgeschlagene Wiederherstellung getestet. |
| AP16 | Proxmox-Rendering liefert einen String/valide JSON-Antwort; keine rekursive Modulanlage. Statische JS-Dateien und geschützte JSON-Konfiguration. Tote/parallele Lazyloader entfernt, authentifizierte Includewege, lokale Bibliotheken mit Lizenzen/Prüfsummen, Mobil- und Tastaturprüfung. Private Einstellungen über Folgeanfragen und Cachemodus im Browser geprüft. |
| AP17 | README, Portalhinweise und Migrationsanleitung ersetzt; Apache-2.0-Lizenz, tatsächliche Runtime-/Extensionanforderungen, Funktionsgrenzen und Breaking Changes dokumentiert. Installations-/Upgrade-/Mail-/Update-/Recovery-Anleitung und unabhängiger Installationslauf mit genau diesen Befehlen vorhanden. CI und explizite Paketdateiliste angepasst. |

## Nachweise

Abschluss: **16 erfolgreiche Prüfgruppen**, davon 15 auch aus einem sauberen Checkout ohne lokale Betreiberdateien; **988 Datenbankprüfungen** auf vier SQL-Systemen, **41 Updaterprüfungen**, **21 Browserprüfungen**, **162 PHP-Dateien** jeweils mit kurzen Tags ein/aus und **53 JS-Dateien**. Die fünf negativen Sicherheitskontrollen am ursprünglichen Tag scheitern wie erwartet. Die explizite Anwendungsliste umfasst **307 Dateien**.

Maschinenlesbare Ergebnisse: [testergebnisse-ap10-ap17.json](testergebnisse-ap10-ap17.json). Der Bericht enthält die gemeinsame Suite, SQL-Engine-Matrix einschließlich nativer Wiederherstellung, Headless-Browser und Ausgangsschema-Anpassungen. Die historischen AP01–AP09-Nachweise bleiben im Verzeichnis erhalten.

- PHP-Syntax für Anwendung/Tests mit `short_open_tag=Off` und `On`; alle ausgelieferten JS-Dateien separat geparst.
- Bestehende Autorisierungs-, Sitzungs-, CSRF-, TLS-, Dateizugriffs- und Parallelitätsregressionen weiter ausgeführt. Das ursprüngliche Tag 3.2.7 bleibt die negative Kontrolle für fünf geschlossene Sicherheitslücken.
- SQL-Matrix: Neuinstallation, altes Installerschema, altes Vollschema; Erhalt von Account-Hash, Betreiberfeld/-tabelle/-index/-ansicht und eigener Mailvorlage. Zusätzlich gemeinsame Konto-/Token-/Reset-/Verwaltungsabläufe sowie statische Anwendungsabfragen per EXPLAIN.
- Native Sicherung/Wiederherstellung und Manipulationsabwehr auf den vier tatsächlichen Serverständen. Isolierte zufällige Testdatenbanken werden nach jeder Prüfung entfernt.
- Signierte echte ZIPs mit RSA-3072-Testschlüssel und separaten Workerinstanzen, tatsächlichem CLI-Migrationsprozess und SQLite-Wiederherstellung. Fehlerhafte Sicherung hält die Anwendung gesperrt und erfordert ausdrückliche Wiederherstellung.
- Frische HTTP-Installation ohne private Betreiberdateien, echter lokaler SMTPS-Testserver und Browser: Desktop 1440×1000, Mobil 390×844, Tastatur, Formfehler, Benutzerspeicherung und persistente Einstellungen; keine externen Assetanfragen oder JavaScript-Laufzeitfehler auf den geprüften Kernseiten.
- Paketdateiliste enthält neue Migrationen, Portaldateien, geschützten Updater und lokale Bibliotheken; private Dateien, Diagnose-/Webinstaller und historische MongoDB-Skripte sind ausgeschlossen. Dies ist noch kein veröffentlichtes Releasepaket.

## Grenzen und nächste Schritte

Der historische PostgreSQL-Vollschemaimport benötigt die Entfernung seiner Datenbank-/psql-Umschaltung und zweier schon ursprünglich ungültiger Aggregatansichten. Die unveränderten historischen Fixtures bleiben nachvollziehbar im Testverzeichnis. Gültige kompatible Ansichten werden geprüft; individuelle Abhängigkeiten können eine sichere Migration stoppen und müssen dann gesondert angepasst werden. MySQL-DDL kann bei Fehlern einen Teilstand hinterlassen: Datenbanksicherung und Wiederherstellungsprobe bleiben erforderlich.

Lokal bestätigt ist PHP 8.3.6. Die CI für PHP 8.4/8.5, native SQL-Dienste und Chromium ist konfiguriert und wurde ohne Push noch nicht bei GitHub ausgeführt. Liveprovider-Schreibtests, produktiver Apache/Nginx/PHP-FPM, TLS/Proxy, reale SMTP-Zustellung, Größe/Laufzeit großer Kundendatenbanken und die produktiven Rechte/Backups sind noch separat abzunehmen.

Der Updater verwendet in Tests künstliche signierte Releases. Produktionssignierung, endgültiger Releaseumfang, Schlüsselverteilung, geplanter Worker und Serverpfade werden in AP28–AP30 eingerichtet und mit dem echten Paket geprüft. Automatisches Rückrollen verweigert nachträglich veränderte Daten/Einstellungen. Es ersetzt keine fachlich abgestimmte Wiederherstellung nach neuen Kundenschreibvorgängen. Externe Datenbankschreiber müssen für Migration/Update ebenfalls angehalten sein.

Der Shop und neue Modulverträge sind weiterhin AP18–AP27. Das [Shop-Konzept](MODULARES-SHOPSYSTEM.md) bleibt die Grundlage für die spätere Übernahme aus „Online Shop“. Zugangsdaten bleiben private Serverkonfiguration, Logo-Uploads bis zur modularen Betreiberkonfiguration deaktiviert.

Die verbindlichen nächsten Betriebsbefehle stehen in [BETRIEB-AP10-AP17.md](BETRIEB-AP10-AP17.md). Es wurde kein Produktivschema verändert, keine Providerressource angelegt und nichts zu GitHub oder auf den eigenen Server hochgeladen.
