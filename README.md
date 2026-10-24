# Server Management Framework

PHP-Anwendung für die Verwaltung von Proxmox, ISPConfig, OVH und OGP mit Administrationsoberfläche und Kundenportal. Der Quellstand trägt weiterhin **3.2.7**; AP01–AP17 sind lokal überarbeitet. Eine neue Versionsnummer, GitHub-Veröffentlichung und der Upload auf den eigenen Server gehören zu AP28–AP30.

Lizenz: **Apache License 2.0**, siehe [LICENSE](LICENSE). Es gibt keine vorgegebenen Zugangsdaten und keinen Webinstaller.

## Installation und Upgrade

Die verbindliche [Installations- und Betriebsanleitung](docs/pruefung-2026-10-24/BETRIEB-AP10-AP17.md) beschreibt private Konfiguration, Datenbank, Administratoranlage, Mailversand, Webserver und Updates. Für bestehende Installationen zuerst den Abschnitt „Upgrade von 3.2.7“ lesen. Alte Anleitungen zum direkten SQL-Import oder zum Aufruf von `src/install.php`/`src/setup.php` sind überholt.

Voraussetzung ist **PHP 8.3 oder neuer** mit PDO und dem passenden SQL-Treiber, mbstring, SimpleXML, DOM, ctype, tokenizer, OpenSSL, Session und JSON. Aktivierte HTTP-Provider benötigen cURL, ISPConfig SOAP. Der Updater benötigt ZIP und native Datenbankwerkzeuge; SQLite-Rückrollen zusätzlich sqlite3. Composer und Node sind für den Anwendungsbetrieb nicht erforderlich.

Lokal geprüft wurden PHP **8.3.6** und SQLite, MySQL **8.0.46**, MariaDB **10.11.14** sowie PostgreSQL **16.15** mit Neuinstallation und zwei alten Schemaausprägungen, einschließlich nativer Sicherung/Wiederherstellung. PHP 8.4/8.5 stehen in der CI-Matrix, wurden hier noch nicht ausgeführt. **MongoDB wird ausdrücklich abgewiesen**; alte MongoDB-Dateien sind historische Quellen und nicht im Anwendungspaket.

## Geprüfter Funktionsumfang

- Registrierung mit gehashten Einmaltokens, Bestätigung per POST, erneutem Versand, behandelten Versandfehlern und Passwort-Rücksetzlinks. Keine Benutzerpasswörter per E-Mail und keine automatische Provideranlage bei Registrierung.
- Gemeinsame Sitzungen und Zugriffsschutz für Verwaltung und Kundenportal. Lokale Benutzerverwaltung schützt den letzten aktiven Administrator. Provideraktionen melden Fehler und Teilergebnisse nachvollziehbar.
- Ein verbindliches Schema in `database/schema.json` mit expliziter, wiederholbarer CLI-Migration. Individuelle Daten und Mailvorlagen werden erhalten; alte unbestätigte Links müssen neu angefordert werden.
- API-Modus für Providerzugriffe; Datenbankmodus zeigt gespeicherte Bestände und führt keine Provideroperationen vor. Die Providerverträge sind mit Testadaptern geprüft; echte Provideränderungen wurden nicht ausgeführt.
- Signierte Kern-/Vollupdates mit serverseitigen Aufträgen, CLI-Worker, obligatorischen Sicherungen und kontrolliertem Rückrollen. GitHub-Quell-ZIPs ohne Signatur werden nicht angenommen. Die Produktionsintegration bleibt Bestandteil von AP28–AP30.
- Lokal ausgelieferte Browserbibliotheken mit Lizenz- und Prüfsummenverzeichnis in `deploy/vendor-assets.json`. Desktop, Mobilansicht und Tastaturbedienung der Kernabläufe sind geprüft.

API-Zugangsdaten werden in privater Serverkonfiguration gepflegt; die Oberfläche zeigt deren Metadaten. Ein modularer Shop und neue Modulverträge folgen in AP18–AP27; siehe [Arbeitsplan](docs/pruefung-2026-10-24/ARBEITSPLAN.md) und [Shop-Konzept](docs/pruefung-2026-10-24/MODULARES-SHOPSYSTEM.md).

## Entwicklung und Nachweise

```bash
python3 tests/run_all.py --report /tmp/smf-regressions.json
```

Benötigt werden PHP mit den Testextensions, Python 3 mit cryptography und Node.js. Der Browserlauf benötigt zusätzlich Playwright; Einzelheiten und native Datenbanktests stehen in der Betriebsanleitung. Die [Umsetzungsübersicht AP10–AP17](docs/pruefung-2026-10-24/UMSETZUNG-AP10-AP17.md) verlinkt die Ergebnisse und bekannten Grenzen. Die GitHub-Actions-Konfiguration ist vorbereitet; ohne Push ist kein entfernter CI-Lauf belegt.
