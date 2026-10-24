# Geführte Erstinstallation des Shops

Stand: 24.10.2026. Der neue Assistent ergänzt Framework 3.3.0. Neueinstieg: `setup.php`; CLI-Einstieg: `bin/setup.php`. Der bisherige `src/install.php` bleibt gesperrt. Der neue Assistent benötigt keine vorher angelegte `Config`-Klasse.

Er richtet den Shop und die Anbindung vorhandener Panels ein. **Er installiert keine ISPConfig-/OGP-Serversoftware mit Rootrechten.** Lokal zugängliche Installationsmarker und eingegebene externe Paneladressen werden getrennt geprüft. Der vollständige Gameserver-Shopweg ist in [AP31–AP46](ARBEITSPLAN-OGP-INSTALLER.md) geplant; ein erfolgreich geprüfter OGP-Zugang ersetzt diesen noch nicht.

## Voraussetzungen

- Eigene HTTPS-Domain mit dem Anwendungsordner als Dokumentwurzel. Die vorhandenen Shoprouten verwenden absolute Pfade; Installation in einem beliebigen URL-Unterordner ist derzeit nicht freigegeben.
- PHP >=8.3, PDO mit MySQL- oder SQLite-Treiber, mbstring, SimpleXML, DOM, ctype, tokenizer, OpenSSL, Session und JSON. Panelprüfungen benötigen cURL; bei ISPConfig bleibt SOAP für den dokumentierten Frameworkumfang erforderlich.
- Leere MySQL-/MariaDB-Datenbank mit eigenem Anwendungsbenutzer oder SQLite. Niemals die vorhandene ISPConfig-/OGP-Datenbank als Shopziel wählen.
- Ein eigener beschreibbarer privater Ordner außerhalb von Anwendungs- und Webroot. Die PHP-Konfiguration einschließlich `open_basedir` muss diesen zulassen. Bei Hostingkonten eignet sich häufig der vom Hoster bereitgestellte private Speicherbereich.
- PHP benötigt anfangs Schreibrechte im Anwendungsordner `config`, um Freigabe/Sperre und die abschließende Konfigurationsverknüpfung anzulegen. Verzeichnis nicht pauschal für alle Benutzer schreibbar machen.
- Apache mit wirksamer mitgelieferter `.htaccess` oder entsprechend eingerichtetes Nginx aus `deploy/nginx-security.conf`. Die allgemeinen privaten Verzeichnisse und Sicherungsdateien bleiben zu sperren.

## Ohne SSH: Installation nach FTP-Upload

1. Den geprüften Anwendungsstand in einen neuen, leeren Webroot hochladen. Eine vorhandene Shopinstallation über ihren Upgradeweg aktualisieren.
2. `config/setup-access.example.php` nach `config/setup-access.php` kopieren. Nur den Platzhalter in der Zeile `// SMF_SETUP_TOKEN: ...` durch eine neue zufällige Zeichenfolge mit **32–128 Zeichen** aus Buchstaben, Ziffern, `_` und `-` ersetzen. Einen Passwortgenerator verwenden; PHP-Sperrcode unverändert lassen. Die Datei frisch speichern/hochladen.
3. `https://eigene-shop-domain/setup.php` öffnen. Auch der unkonfigurierte Startseitenaufruf führt dorthin. Den gewählten Startschlüssel eingeben. Er ist ab dem letzten Speichern der Freigabedatei 24 Stunden gültig.
4. Privaten Speicher und Datenbanksystem eingeben. Bei MySQL/MariaDB die bereits angelegte leere Datenbank und den zugehörigen Benutzer angeben. SQLite wird im privaten Speicher angelegt.
5. Shopname, feste HTTPS-Adresse, Administrator, eigenes Passwort, Support-/Absenderadresse und Mailverfahren eintragen. Vorhandener Systemmaildienst oder SMTP mit direktem TLS sind auswählbar. Der SMTP-Weg unterstützt in dieser Fassung kein STARTTLS; typischer SMTPS-Port ist 465.
6. Vorhandene ISPConfig-/OGP-Anschlüsse prüfen. Leere Paneladresse überspringt den Anschluss. Bei einer fehlgeschlagenen Prüfung entweder korrigieren oder den Shop ausdrücklich mit deaktiviertem Anschluss einrichten.
7. Gewünschte Zusatzmodule auswählen und das Prüfergebnis bestätigen. Die Basis aus Speicherung, Katalog, Portal und Anfrage-/Bestellverwaltung sowie notwendige Abhängigkeiten werden automatisch ergänzt.
8. Nach Abschluss mit dem selbst gewählten Administratorkonto unter `/src/login.php` anmelden. Shopverwaltung liegt unter `/admin`, Modulverwaltung unter `/src/modules.php`.

Die Freigabedatei verweigert ihren eigenen HTTP-Aufruf bereits im PHP-Code. Der Startschlüssel wird weder als URL-Parameter noch in öffentlich ausgelieferten Konfigurationsdateien gespeichert. Nach erfolgreichem Abschluss wird sie entfernt. Ein konfiguriertes System beantwortet einen erneuten Assistentenaufruf mit HTTP 409 und führt keine Wiedereinrichtung aus.

## Mit SSH oder automatisierter Bereitstellung

```bash
php bin/setup.php --prepare
# Den ausgegebenen Startschlüssel anschließend im Browser unter /setup.php eingeben.

php bin/setup.php --detect
# Lokale Installationshinweise als JSON, ohne fremde Konfigurationsdateien auszuführen.

php bin/setup.php --install < /privater-pfad/setup.json
# Dieselbe Erstinstallation mit Angaben ausschließlich über geschützte Standardeingabe.
```

Die JSON-Eingabe verwendet `setup_token`, `private_dir`, `db_type`, `db_host`, `db_port`, `db_name`, `db_user`, `db_password`, optional `db_socket`, `site_url`, `site_name`, `support_email`, `system_email`, `admin_name`, `admin_email`, `admin_password` und eine Liste `modules` mit den gewünschten gebündelten Shopmodulen. Für SQLite sind MySQL-Zugangsfelder nicht erforderlich.

Optionale Objekte `ispconfig` und `ogp` enthalten `url` und gegebenenfalls `ca_file`/`local_root`. ISPConfig erhält zusätzlich `username`, `password` und `server_id`; OGP erhält `token`. Bei `mail_mode=smtps` kommen `smtp_host`, `smtp_port`, `smtp_user` und `smtp_password` hinzu. `allow_unverified=true` ist die ausdrückliche Wahl, mit einer ungeprüften **deaktivierten** Panelanbindung fortzufahren. Ohne diese Wahl stoppt die Einrichtung vor den Migrationen, wenn ein ausgewähltes Panel die Prüfung nicht besteht.

Die Eingabedatei und Freigabe gehören nicht in Git, ein Releasepaket oder einen öffentlich lesbaren Ordner. Der CLI-Bericht enthält weder Zugangsdaten noch das Administratorpasswort.

## Bedeutung der Panelprüfung

| System | Tatsächliche Prüfung | Weiterer Einrichtungsschritt |
| --- | --- | --- |
| ISPConfig | Bekannte lokale Dateien oder eigener Panelpfad; HTTPS-JSON-Anmeldung, `server_get` für die gewählte Server-ID, Logout | Serverrollen, Web-/Mail-/Datenbankzuordnung, Templates, Quoten und produktspezifische Remote-Rechte einrichten. Die ausgewählte Webserver-ID wird vom gemeinsamen Adapter verwendet. |
| OpenGamePanel | Bekannte lokale Dateien oder eigener Panelpfad; HTTPS-`token/test` und `server/list` mit vorhandenem Betreiber-Token im POST-Body | Agents, Spiele, Kontingente und Produktzuordnung prüfen. Eigenes Gameserver-Produkt-/Bereitstellungsmodul gemäß AP35–AP39 implementieren. |

Eine fehlende lokale Datei führt zu „unbekannt“, nicht zu einer Behauptung „nicht installiert“. Fehlende API, ungültiger Zugang, gesperrte Shop-IP, fehlende Berechtigungen, Zertifikatsfehler und fehlende Erreichbarkeit bleiben getrennte Ergebnisse. Bei positiver OGP-Prüfung werden die Anzahl gelisteter Agents und die tatsächlich geprüften Fähigkeiten angegeben; deren Onlinezustand oder eine erfolgreiche Spielinstallation werden damit nicht behauptet.

Der Installer erzeugt keinen neuen OGP-Token und rotiert keinen bestehenden. Er legt bei den Verbindungstests keine Hosting-/Gameserverressourcen an. Eigene CAs werden ausdrücklich angegeben; die TLS-Prüfung wird nicht abgeschaltet.

## Private Dateien, Abschluss und laufender Betrieb

`config/config.inc.php` enthält anschließend nur die Verknüpfung zur privaten `config/runtime.php`. Im privaten Speicher liegen außerdem `config/shop.key`, Betreiber-/Shopeinstellungen und `setup/installation.json`. Diese Dateien gemeinsam mit Datenbank und Kundendokumenten sichern. Private Dateien erhalten Modus 0600, zugehörige neue Verzeichnisse 0700.

Der Assistent legt ein explizites Administratorkonto an. Er aktiviert nur die ausgewählten Module und ihre Abhängigkeiten. Der vorhandene generische Anfragekatalog aus der Shop-Basismigration ist kein Nachweis bereitgestellter oder bezahlbarer Providerprodukte. Es entsteht keine automatische Gameserver-Verkaufsfreigabe.

Nach der Installation:

- Den bestehenden Worker über den Hosting-Scheduler/Cron regelmäßig ausführen, beispielsweise jede Minute `php /absoluter/shop-pfad/bin/worker.php --limit=10 --retain-days=90`. Anschließend den Heartbeat unter `/src/modules.php` kontrollieren. **Der Erstinstaller richtet selbst keinen Cronjob ein.**
- Mailzustellung mit einer eigenen Testadresse prüfen. Das Speichern der SMTP-Zugangsdaten wird nicht als erfolgreicher Versand ausgegeben.
- Betreiber-/Vertragstexte, Produkte, Belegabläufe und die tatsächlich eingesetzten Zahlungsanbieter konfigurieren.
- Sandbox ist der Startzustand; `allow_live_operations` bleibt gesperrt. Die endgültige Betriebsfreigabe folgt nach der fachlichen Provider- und Produktabnahme.

Proxmox und OVH bleiben über die vorhandenen Konfigurations-/Verwaltungswege anschließbar. Ihre einheitliche Assistenten- und Fähigkeitsintegration ist AP41; das neue Diagnoseformular dieser Fassung konzentriert sich auf ISPConfig und OGP.

## Unterbrechung und Wiederaufnahme

Bis zum Abschluss wird keine neue öffentliche Konfigurationsverknüpfung aktiviert. Ein privates Journal bindet den begonnenen Versuch an Zielordner, Eingaben und Startschlüssel. Nach einer Unterbrechung mit **denselben Angaben, demselben Schlüssel und demselben privaten Pfad** erneut durch den Assistenten gehen oder dieselbe CLI-Eingabe erneut ausführen. Bei abgelaufener Freigabedatei denselben Schlüssel frisch speichern; ein anderer Schlüssel gehört nicht zum begonnenen Versuch.

Bereits gelaufene Migrationen werden über die vorhandenen Migrationstabellen geprüft; ein bereits angelegter passender Administrator wird beim Wiederanlauf nicht erneut angelegt oder sein Passwort zurückgesetzt. Ein geänderter Shopschlüssel stoppt den Wiederanlauf. Fremde Datenbanken ohne zugehörigen begonnenen Versuch sowie fremde befüllte Privatverzeichnisse werden abgewiesen.

Ein Abbruch ist kein automatisches Löschen der Datenbank. MySQL-DDL ist nicht vollständig transaktional. Für einen bewussten Neustart müssen Betreiber erst Sicherung und Besitz des unvollständigen Zielsystems prüfen; der Assistent führt keinen allgemeinen `DROP DATABASE`-Rückweg aus.

Vorhandene Installationen benötigen weiterhin den dokumentierten Migrations-/Updateweg. Ein eigener angemeldeter Upgrade-/Recoveryassistent ist AP43. Das bestehende Testsystem wurde in diesem Auftrag nicht erneut installiert.

## Lokaler Prüfnachweis

`python3 tests/test_setup.py` prüft echte lokale TLS-Endpunkte mit eigener CA, OGP-/ISPConfig-Antwortverträge, Geheimnisse im Requestbody, fehlgeschlagene Zertifikatsprüfung, fehlende Endpunkte, Redirects, Marker ohne Konfigurationsausführung, unterbrochene CLI-Installation und Wiederaufnahme, fremde Datenbank, privaten Speicher, vollständige Browserinstallation und abschließenden Admin-Login. Der Installer ist als eigene Suite in `tests/run_all.py` integriert.

Für denselben Durchlauf mit zusätzlicher nativer MySQL-/MariaDB-Erstinstallation `SMF_TEST_SETUP_NATIVE=1` und die dokumentierten `SMF_TEST_MYSQL_*`-Variablen setzen. Die Tests erzeugen dafür ausschließlich eine eigene zufällig benannte Testdatenbank und entfernen sie anschließend.

Die [konkreten Testergebnisse](testergebnisse-ogp-installer.json) dokumentieren 20 erfolgreiche Testsuiten, 77 gezielte Installer-/Erkennungsprüfungen, 84 Prüfungen mit zusätzlichem MariaDB-Durchlauf sowie 12 Chrome-Prüfungen auf Desktop und Mobil. Die Neuinstallationen verwenden ausschließlich die kuratierte Paketdateiliste; der echte OGP-Adapter besitzt zusätzlich Regressionen für die Übertragung von Zugangsdaten im POST-Body. Es wurden keine echte ISPConfig-/OGP-Installation, kein Agent und kein Gameserver zur bloßen Probe verändert. Eine neue öffentliche GitHub-Veröffentlichung und das Upgrade des vorhandenen Testsystems gehören zu AP45/AP46.
