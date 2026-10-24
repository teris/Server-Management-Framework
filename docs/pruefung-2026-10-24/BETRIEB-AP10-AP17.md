# Installation, Upgrade und Betrieb nach AP10–AP17

Stand: 24.10.2026. Releaseziel 3.2.8 auf der geprüften 3.2.7-Basis. Die [Releasehinweise](../releases/3.2.8.md) ergänzen diese Anleitung. Diese Anleitung ersetzt die Installations-/Migrationshinweise aus dem ursprünglichen 3.2.7-Paket und ergänzt den Sicherheitsbetrieb aus AP01–AP09.

## Plattform und Pfade

PHP mindestens 8.3, PDO mit `pdo_mysql`, `pdo_pgsql` oder `pdo_sqlite`; `mbstring`, `simplexml`, `dom`, `ctype`, `tokenizer`, `openssl`, `session`, `json`. Für HTTP-Provider `curl`, für ISPConfig `soap`. Browserassets sind lokal enthalten; kein Composer/npm im Produktivbetrieb. Der optionale Updater braucht `zip`, verfügbares `proc_open`, einen CLI-PHP-Prozess und native Sicherungswerkzeuge. SQLite-Restore benötigt `sqlite3`; für SQLite mindestens die lokal geprüfte Version 3.45.1 einsetzen. Andere SQLite-Versionen sind hier nicht abgenommen.

Lokal getestet: PHP 8.3.6, SQLite 3.45.1, MySQL 8.0.46, MariaDB 10.11.14, PostgreSQL 16.15. Das sind Prüfstände, keine Aussage über die jeweils neuesten Releases. PHP 8.4/8.5 sind als weitere CI-Prüfstände eingerichtet, aber lokal nicht bestätigt. Windows/NTFS wurde für die Entwicklung verwendet; die atomare Linux-Symlink-Aktivierung des Updaters braucht eine passende Serverumgebung. Apache/Nginx mit echtem PHP-FPM, Proxy und produktivem Dateisystem werden in AP30 abgenommen.

Beispielpfade auf dem Server:

- Anwendung: `/srv/smf/app` bzw. verwaltete Releases hinter `/srv/smf/current`.
- Private Daten: `/srv/smf-private`, außerhalb des DocumentRoot und der Releaseverzeichnisse.
- PHP-Konfiguration: `/srv/smf-private/config/runtime.php` mit der Klasse `Config` aus `config/config.example.php`.

In CLI **und** PHP-FPM dieselben festen Umgebungswerte setzen:

```bash
export SMF_PRIVATE_DIR=/srv/smf-private
export SMF_CONFIG_FILE=/srv/smf-private/config/runtime.php
```

Das Webkonto benötigt Schreibrechte auf private Sitzungs-/Token-/Updateverzeichnisse, nicht auf beliebige Systemdateien. Private Daten nicht über HTTP bereitstellen. Bei SQLite liegt auch die Datenbank mit absolutem Pfad außerhalb der Anwendung. Provider-Schlüssel bleiben unter `$SMF_PRIVATE_DIR/keys/provider-encryption.key`; vorhandene Schlüssel niemals neu erzeugen. Betreiberwerte können zusätzlich in `config/system.local.php` innerhalb des privaten Verzeichnisses stehen. Die Einstellungsoberfläche schreibt `config/ui-settings.json` dort; Zeitangaben werden auf feste Grenzen geprüft. Der JSON-Stand hat für diese vier Werte Vorrang vor PHP-Overrides.

## Neuinstallation

1. `config/config.example.php` nach dem privaten Pfad kopieren, Zugriff begrenzen und Werte bearbeiten. `DB_TYPE` sowie genau den gewählten Treiber aktivieren: `DB_USEING`, `DB_PGSQL_USEING` oder `DB_SQLITE_USEING`. Provider zunächst deaktiviert lassen. Feste HTTPS-Adresse in `FRONTPANEL_SITE_URL` und gültige Absenderadresse in `FRONTPANEL_SYSTEM_EMAIL` eintragen.
2. Für MySQL/MariaDB/PostgreSQL eine **leere, eigene Datenbank** mit einem dafür berechtigten Benutzer erstellen. `bin/install.php` legt Tabellen an, aber keinen Datenbankserver und keine SQL-Datenbank. SQLite-Datei entsteht am konfigurierten privaten Pfad.
3. Im Anwendungsverzeichnis ausführen:

```bash
php bin/check-platform.php
php bin/install.php --username=mein-admin --email=admin@example.org
php bin/migrate.php --status
```

Das neue Administratorpasswort wird interaktiv verdeckt über Standardeingabe gelesen (12–4096 Zeichen), nicht als Kommandoargument. In Automationen eine private Eingabedatei/Secret-Pipe verwenden. Kein Passwort in Shellhistorie oder Log schreiben. Die Installation erzeugt genau den ausdrücklich angegebenen Administrator und fehlende sichere Mailvorlagen. Ein erneuter Installationsversuch auf einer nicht leeren Datenbank wird verweigert.

4. HTTPS-Webserver auf die Projektwurzel richten. Apache benötigt die `.htaccess`-Regeln einschließlich Rewrite/Require; bei Nginx `deploy/nginx-security.conf` vor dem allgemeinen PHP-Handler einbinden. Interne PHP-Includes, Datenbanken, Konfiguration, CLI, Tests und Backups müssen gesperrt sein. Der PHP-Entwicklungsserver ist nur für isolierte Tests vorgesehen. `/src/login.php` öffnet die Verwaltung, `/public/index.php` das Kundenportal.
5. Mailversand und Registrierung über eine Testadresse prüfen, danach benötigte Provider mit vertrauenswürdiger Zertifikatskette einzeln konfigurieren. Reale Provideraktionen brauchen eine eigene Abnahme.

## Mail und Kontoabläufe

Ohne `SMF_SMTP_HOST` wird das konfigurierte PHP-`mail()` verwendet. Der integrierte SMTP-Transport unterstützt **implizites TLS (SMTPS)**, standardmäßig Port 465; Port 587/STARTTLS ist nicht implementiert. Verfügbar sind `SMF_SMTP_HOST`, `SMF_SMTP_PORT`, optional `SMF_SMTP_USER`, `SMF_SMTP_PASS` und `SMF_SMTP_CA_FILE` für die private CA. Zugangsdaten in der Serverumgebung halten. Zertifikat und Hostname werden geprüft. Serverannahme bedeutet noch keine bestätigte Zustellung im Zielpostfach.

`SMF_PUBLIC_URL` kann die feste HTTPS-Portaladresse überschreiben. Links kommen nie aus dem HTTP-Hostheader. Registrierungslinks gelten 24 Stunden, Rücksetzlinks 30 Minuten; erneuter Versand widerruft den alten Link. Links binden auch E-Mail-Adresse und Passwortstand. Versandfehler erhalten das ausstehende Konto; `/public/resend-verification.php` ermöglicht Wiederholung. `/public/reset-password.php` unterstützt Kunden- und Verwaltungskonten. Eine Passwortänderung entzieht alten Sitzungen und Remember-Tokens ihre Gültigkeit. Bestehende individuelle Mailvorlagen werden nicht überschrieben; Passwortvariablen werden auch in alten Vorlagen nicht mehr gefüllt.

## Upgrade von 3.2.7

1. Anwendung, gesamte Datenbank und **alle** privaten Dateien einschließlich Schlüssel/Overrides separat sichern und Wiederherstellung auf einer Kopie prüfen. Bei mehreren Instanzen alle auf dieselbe Wartungsgrenze bringen. Import alter Vollschemas über bestehende Tabellen ist kein Upgrade.
2. Falls Konfiguration/Schlüssel noch im alten Anwendungsverzeichnis liegen: alten `SMF_PRIVATE_DIR` beibehalten bzw. dessen gesamten Inhalt kontrolliert in das gewählte feste Verzeichnis übernehmen, dann `php bin/private-config.php` ausführen. Der Befehl kopiert vorhandene Dateien bytegleich, ersetzt nur die lokale Konfiguration durch einen Loader und verweigert widersprüchliche Ziele. Er übernimmt auch `src/sys.conf.local.php` in die private `config/system.local.php`. `SMF_CONFIG_FILE` danach auf die private Datei setzen. Bestehende Providerdaten nicht mit einem neuen Verschlüsselungsschlüssel öffnen.
3. Mit dem neuen Quellstand auf einer **Datenbankkopie** ausführen:

```bash
php bin/check-platform.php
php bin/migrate.php --status
php bin/migrate.php
php bin/migrate.php
```

Der zweite Lauf meldet `changed: false`. Quelle ist das nummerierte, mit Prüfsumme gespeicherte Schema `20261024_core_01`; dazu kommen die AP09-Sicherheitsmigrationen. Tabellen, zusätzliche Betreiberfelder/-tabellen/-indizes, gültige kompatible Ansichten, Passwort-Hashes und angepasste Mailvorlagen bleiben erhalten. Alte Klartext-Verifikations-/Remember-Tokens werden invalidiert; Kunden müssen ggf. neue Bestätigungslinks anfordern. PostgreSQL-Enums werden in Textfelder, alte boolesche Kernfelder in Integer überführt. SQLite baut betroffene Tabellen in einer Transaktion mit Fremdschlüsselprüfung neu auf. MySQL-DDL ist nicht vollständig transaktional: die geprüfte Sicherung ist für einen Fehlerfall zwingend.
4. Individuelle PostgreSQL-Ansichten/Trigger mit Abhängigkeiten auf geänderten Spaltentypen können die Migration kontrolliert stoppen. Solche Objekte müssen auf der Kopie gezielt angepasst werden; kein `DROP ... CASCADE` ausführen. Die drei bekannten Berichtsansichten werden, soweit vorhanden und kompatibel, mit Besitzer/Rechten wieder aufgebaut. Das ursprüngliche PostgreSQL-Vollskript enthielt zwei nicht importierbare Aggregatansichten; deren Ausschluss beim historischen Testimport ist im Testbericht ausgewiesen. Es wurde kein fehlerfreier Originalimport dieses Skripts behauptet.
5. Nach bestandener Kopienprüfung Zugriffe, Synchronisation und sonstige externe DB-Schreiber anhalten; Sicherung aktualisieren und denselben Ablauf am Server durchführen. Die Migration sperrt Framework-Anfragen und normale CLI-Schreiber. Bei Fehler bleibt der Wartungsmarker erhalten. Erst Ursache/Teilstand prüfen und Sicherung bereitstellen; nur für einen fortsetzbaren Schemaauftrag `php bin/migrate.php --resume` verwenden. Einen Marker nicht ungeprüft löschen.
6. Login, Registrierung/SMTP, Kontosperre, Zuordnungen, eigene Vorlagen und benötigte Provider prüfen, dann Verkehr freigeben. Rückkehr bei fehlgeschlagener Migration: alten Quellstand **und** zugehörige DB-/private Sicherung zusammen wiederherstellen. Ein reiner Dateirücktausch reicht nicht.

MongoDB-Konfigurationen brechen ausdrücklich ab. Alte Browserinstaller und das bisherige unsignierte GitHub-Webupdate sind ersetzt. Der Datenbankmodus ist ein Lesemodus für gespeicherte Providerbestände; er simuliert keine erfolgreichen Provideränderungen. OGP-Nutzerbearbeitung und Passwortänderung werden als nicht unterstützt gemeldet; dafür das Providerportal verwenden. Proxmox-Nutzerpasswörter werden nur für verwaltete `@pve`-Konten angeboten. API-Geheimnisse werden ausschließlich serverseitig gepflegt. Logo-Uploads bleiben bis zur modularen Betreiberkonfiguration deaktiviert.

## Signierte Updates und Wiederherstellung

Der neue Ablauf ist mit isolierten signierten Paketen abgenommen. Er installiert keine beliebigen GitHub-ZIPs. 3.2.8 erhält ein signiertes Vollpaket; der Server-Worker und die Vertrauenseinrichtung bleiben Teil der Serverbereitstellung in AP30. Der erstmalige Wechsel vom ursprünglichen 3.2.7 erfolgt durch kontrollierten Upload mit expliziter Migration.

Für die spätere Aktivierung benötigt der Worker feste externe Privat-/Konfigurationspfade, einen austauschbaren Symlink `SMF_CURRENT_LINK=/srv/smf/current` auf das aktive Release und `SMF_UPDATE_PUBLIC_KEY` auf einen **separat vertrauenswürdig eingerichteten RSA-Public-Key (mindestens 3072 Bit)** außerhalb von Anwendung und Releases. Der private Signierschlüssel gehört nicht auf den Webserver. CLI und Webprozess verwenden denselben privaten Auftragsstand. Releasecode und Symlink dürfen nur vom zuständigen Worker geändert werden.

Das ZIP enthält exakt `manifest.json`, `manifest.sig` (Base64-RSA/SHA-256 über die unveränderten Manifestbytes) und die deklarierten Dateien. Manifestfelder: `format: 1`, `type: core|full`, neue semantische `version`, `commit` (40 Hexzeichen), `schema_version`, `php_min`, `files` (Pfad → SHA-256). Grenzen: 64 MiB Archiv/entpackte Summe, 8 MiB pro Datei, maximal 1000 deklarierte Dateien. Keine Symlinks, Pfadausbrüche, privaten Konfigurationen oder Schlüssel. Kernupdates dürfen keine `public/`- oder Moduldateien überlagern. Vollupdates müssen die gesamte eigene Inventarliste erfüllen.

Ein Administrator lädt das signierte Paket unter „Manueller Updater“ hoch. Webrequests dürfen nur Aufträge speichern; der CLI-Worker führt aus:

```bash
php bin/update-worker.php --status
php bin/update-worker.php --id=<auftrag> --action=stage
php bin/update-worker.php --id=<auftrag> --action=activate
# Alternativ vom Betreiber zeitlich eingeplant, verarbeitet bereits angeforderte Schritte:
php bin/update-worker.php --once
```

Zustände: `verified` → `staged` → `applying` → `activated`; `abort` ist vor Aktivierung möglich, `rollback` nach Aktivierung bzw. bei `recovery_required`. Wiederholungen sind idempotent. Aufträge müssen innerhalb von 24 Stunden aktiviert werden. Die Aktivierung verlangt eine exklusive Sperre; laufende Requests führen zu einem sicheren Abbruch und späterem Wiederholungsversuch. Die Migration läuft mit dem neuen CLI-Code vor dem atomaren Symlinkwechsel. Framework-Version und unabhängige Pluginversionen werden getrennt behandelt.

Vorher entstehen obligatorische private DB- und Konfigurationssicherungen. MySQL/MariaDB benötigen standardmäßig `mariadb-dump`/`mariadb`, PostgreSQL `pg_dump`/`pg_restore`; abweichende geprüfte Pfade über `SMF_MYSQL_DUMP_BIN`, `SMF_MYSQL_BIN`, `SMF_PG_DUMP_BIN`, `SMF_PG_RESTORE_BIN`. Passwörter stehen im Kindprozess-Environment, nicht in Argumenten. MySQL-Sicherungen setzen transaktionale Tabellen voraus; andere Engines müssen vorab in InnoDB überführt oder separat offline gesichert werden. PostgreSQL-DB- und Rollenrechte müssen Dump/Restore erlauben. SQLite sichert per `VACUUM INTO`, stellt per SQLite-Backup-API wieder her. Externe Schreiber müssen angehalten sein; das Framework kann deren Verbindungen nicht sperren.

```bash
php bin/update-worker.php --id=<auftrag> --action=rollback
```

Automatisches Rückrollen wird verweigert, wenn nach Aktivierung Daten oder geschützte Einstellungen verändert wurden; neue Schreibvorgänge dürfen nicht verloren gehen. Dann ist ein abgestimmter manueller Wiederherstellungs-/Datenabgleich erforderlich. Bei Migrationsfehler wird die Sicherung zurückgespielt. Schlägt auch das fehl, bleibt `recovery_required` samt Wartungsmodus bestehen. Sicherungsintegrität/Dateirechte/SQL-Zugang zuerst reparieren und danach ausdrücklich `rollback` ausführen. Andere Updates währenddessen nicht aktivieren. Aufträge zeigen einen neutralen Fehlercode; Produktionsdiagnosen dürfen keine Secrets enthalten. Sicherungen/Releases werden nicht automatisch gelöscht; Aufbewahrung und Platzüberwachung gehören zum Serverbetrieb.

## Prüfungen

```bash
python3 tests/run_all.py --report /tmp/smf-regressions.json
python3 tests/test_browser_stability.py
SMF_TEST_NATIVE_BACKUP=1 python3 tests/test_database_engines.py --engines sqlite
```

Tests brauchen PHP-Extensions einschließlich curl/soap/dom/zip/sqlite3, Python 3 mit cryptography und Node. Browser: Playwright 1.62.1 mit installiertem Chromium; `SMF_PLAYWRIGHT_MODULE` und `SMF_BROWSER_BIN` können lokale Pfade angeben. Für native SQL-Tests ausschließlich einen isolierten Testserver verwenden: `SMF_TEST_MYSQL_HOST/PORT/USER/PASSWORD` oder `SMF_TEST_MYSQL_SOCKET`, bzw. `SMF_TEST_PGSQL_HOST/PORT/USER/PASSWORD`. Der Prüfbenutzer muss zufällig benannte `smf_test_*`-Datenbanken erstellen und entfernen dürfen. Aufruf mit `--engines mysql,pgsql`; Dumps via obige Binärvariablen. Keine Produktionszugänge hierfür verwenden.

Der HTTP-Installations-/Mailtest führt die dokumentierte Administratoranlage und Registrierung gegen eine frische Kopie und einen echten lokalen SMTPS-Testserver aus. Provider werden simuliert; keine bezahlten oder produktiven Ressourcen entstehen. Die Ergebnisdateien sind mit der [Umsetzungsübersicht](UMSETZUNG-AP10-AP17.md) verbunden.
