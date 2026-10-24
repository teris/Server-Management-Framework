# Modularer Betrieb und Shopübernahme – AP18–AP27

Stand 24.10.2026. Entwicklungsstand 3.3.0, Shopmodule 1.0.0. Das veröffentlichte Vorgängerrelease bleibt 3.2.8. Die Veröffentlichung eines neuen GitHub-Releases ist ein eigener Schritt.

## Voraussetzungen und Installation

PHP >= 8.3, PDO sowie die bisher dokumentierten Kernextensions. Für den Shop zusätzlich OpenSSL, mbstring und die mitgelieferten PDF-Schriften. Der Kern unterstützt SQLite, MySQL/MariaDB und PostgreSQL. Die übernommene Shop-Persistenz unterstützt **SQLite und MySQL/MariaDB**; eine Shopinstallation auf PostgreSQL wird vor Änderungen abgewiesen.

Anwendung, Datenbank und private Konfiguration/Schlüssel zusammen sichern. `Config::PRIVATE_STORAGE_DIR` oder `SMF_PRIVATE_DIR` muss auf ein dauerhaftes Verzeichnis außerhalb des Anwendungs-/Dokumentverzeichnisses zeigen. Verzeichnisse 0700, private Dateien 0600. Auf dem Testhost liegt dieses Verzeichnis im dafür vorgesehenen privaten Hostingbereich.

```bash
php bin/migrate.php
php bin/modules.php --list
php bin/modules.php --shop
```

`--shop` installiert und aktiviert die gebündelten Module in Abhängigkeitsreihenfolge. Einzelinstallation/-schaltung:

```bash
php bin/modules.php --install=shop.promotions
php bin/modules.php --enable=shop.promotions
php bin/modules.php --disable=shop.promotions
```

Aktive Abhängigkeiten verhindern das Abschalten ihrer Basis. Deaktivierung entfernt Modulrouten, Formularaktionen und Jobbeiträge; Tabellen, Zahlungen und Belege bleiben erhalten. Die Kernverwaltung unter `/src/` funktioniert auch ohne aktivierten Shop. Apache verwendet die mitgelieferte `.htaccess`. Für Nginx liefert `deploy/nginx-security.conf` die Schutz- und Shopregeln; im Serverblock ist zusätzlich `index index.php public/index.php;` zu setzen. Es gibt keine Webinstallation beliebiger PHP-/ZIP-Erweiterungen und keine Datenbankschemaänderungen beim normalen Seitenaufruf.

## Zuständigkeiten

| Modul | Aufgabe | Abhängigkeiten |
|---|---|---|
| shop.storage | Gemeinsame Shop-Persistenz, Kompatibilitätsfunktionen und vorhandene Anwendung | Kern |
| shop.catalog | Katalog, Konfigurator, Domainprüfung | storage |
| shop.portal | Kundenkonto, Support und Shopverwaltung | storage |
| shop.orders | Anfragen, Angebote und Bestellungen | catalog, portal |
| shop.payments | PayPal, Webhook und Zahlungsabgleich | orders |
| shop.provisioning | Providerressourcen, Bereitstellung und Wiederaufnahme | orders |
| shop.billing | Rechnungen/PDF, Dokumente und Belegabläufe | orders |
| shop.contracts | Verträge und Kündigungen | orders |
| shop.communications | E-Mail, FAQ und Benachrichtigungen | portal |
| shop.promotions | Gutscheine und Rabattregeln | orders |
| shop.subscriptions | Konfigurationsmodule, Erweiterungen und Abos | orders |

Die Tabellen bleiben während dieser Kompatibilitätsphase in einer gemeinsamen Speichermigration. Die fachlichen Module besitzen Routen, Aktionen und Workerbeiträge; sie betreiben keine eigenen Kundenkonten. Das ist eine Abtrennung der vorhandenen Anwendung, kein zweiter Shop neben ihr. Die Funktionen aus `extensions/shop.storage/legacy/` sind die übernommene Anwendung; `SOURCE-MANIFEST.json` weist Quelldateien und ursprüngliche Prüfsummen nach.

## Identität, Rechte und Provider

Anmeldung, Kontostatus, Sitzungsablauf, Passwort-Fingerabdruck und CSRF verwenden den Kern. Alte nackte `customer_id`-/`admin_id`-Sitzungswerte verleihen keine Rechte. Die Registrierung legt ein zu bestätigendes Kernkonto an. Supportantworten mit `is_internal=1` werden im Kundenbereich nicht ausgegeben.

Die Provider verwenden bestehende `Config`-Werte als Ausgangspunkt. Bewusst gespeicherte Shop-Verbindungen werden verschlüsselt abgelegt und auch von der Framework-Kompatibilitätsschicht verwendet. Wechsel von Konto/Endpunkt macht alte Bindungen prüfpflichtig. Das Abschalten der Verkaufsoberflächen entfernt nicht das installierte Providerkonto aus dem Kern.

Shopressourcen erhalten einen Kern-Berechtigungseintrag in `security_resource_assignments`. Kommerzielle Zuordnung und Kernrecht müssen denselben Eigentümer, dasselbe Providerkonto und dieselbe Ressource bezeichnen. Pausierte Leistungen und abgelaufene Berechtigungen erlauben keine Schreibaktion. Für QEMU wird `vmgenid` geprüft, für LXC der gespeicherte Besitzmarker; eine wiederverwendete VMID genügt nicht zur Freigabe. Vorhandene Altbindungen ohne diese Merkmale müssen erneut verifiziert werden. OGP bleibt ein separat geprüfter Legacyadapter; es gibt keinen automatischen OGP-Shop-Provisioner.

Netzwerkänderungen verlangen Node, QEMU-ID, Unicast-MAC und IP/CIDR. Die vorhandene Bridge, VLAN-/Firewalloptionen und die andere IP-Familie bleiben erhalten. Ohne Cloud-Init-Datenträger gibt es keine IP-Schreibaktion. Die gespeicherte Gastkonfiguration wird beim nächsten passenden Cloud-Init-Lauf wirksam.

## Betreiberoberfläche und Anfragebetrieb

Administratoren erreichen `/src/modules.php` auch ohne aktive Shopoberfläche. Name, Supportkontakt, HTTPS-URL, Logo-URL, Akzentfarbe und Direktzugänge werden in privater `config/shop-settings.json` gepflegt. Die gebündelte Oberfläche enthält keine Originalbetreiberlogos oder produktiven Konfigurationsdateien. Rechtstexte und Verkäuferangaben müssen vor einem Direktverkauf über die Dokument-/Shopverwaltung eingerichtet werden; ein leeres System erzeugt keine verbindlichen Betreibertexte.

Ein nicht für Direktkauf konfiguriertes Produkt bleibt als Anfrage verfügbar. Produkt-/Modulpreise, Mengen, Ausstattung, Steuer und Snapshots werden serverseitig berechnet. Formularpreise sind keine Preisquelle.

## Zahlungen und Worker

Standard ist `environment=sandbox`, `allow_live_operations=false`. Der zweite Wert kann nur in der privaten Betreiberdatei freigegeben werden. Die Oberfläche allein kann diese Freigabe nicht setzen. Sandbox-Bestellungen dürfen auch nach einer manuellen Testzahlungsbestätigung keine Live-Ressourcen automatisch erzeugen. Zahlungs-, Provider- und Mailtests dieser Umsetzung verwenden keine echten kostenpflichtigen Vorgänge.

Für PayPal müssen zur richtigen Umgebung passende App-Zugangsdaten, Webhook-ID und Händler-ID konfiguriert sein. Der Händler wird in den Bestellsnapshot übernommen und beim Orders-Capture geprüft. Das System prüft Betrag, Währung, Bestellreferenz, Signatur und Umgebung. Ein aktiviertes Abonnement ist kein Zahlungsnachweis. Ereignisse werden zusätzlich über die Provider-API abgeglichen; eindeutige Zahlungs-/Ereignisreferenzen verhindern Doppelbuchungen.

Die Signaturprüfung folgt der [PayPal-Webhook-Dokumentation](https://developer.paypal.com/api/rest/webhooks/rest/), die Serverabläufe der [Orders-API](https://developer.paypal.com/api/rest/integration/orders-api). Wiederholbare PayPal-Schreibaufrufe verwenden `PayPal-Request-Id` entsprechend der [API-Dokumentation](https://developer.paypal.com/api/make-api-requests).

```bash
php bin/worker.php --limit=10 --retain-days=90
```

Diesen Befehl regelmäßig über den Scheduler des Servers ausführen. Der HTTP-Webhook führt keine Bereitstellung bei Requestende aus. Worker besitzen eindeutige Aufträge, Sperrtokens, Fristen und gespeicherte Phasen. Eine unbekannte Antwort nach einer externen Schreibabsicht führt in einen Klärungszustand; sie löst keinen blinden Neuauftrag aus. Ein abgelaufener Worker darf keine Phasen oder Leistungszuordnung abschließen. In der Shopverwaltung werden Anbieterzustand und vorhandene Ressourcen vor einer Wiederaufnahme geprüft.

`smf_outbox` übergibt Kernereignisse transaktional an `smf_jobs`; die vorhandenen Commerce-Ereignisse bleiben für die übernommenen Belegabläufe erhalten. Im Testbetrieb bleiben Live-Provisionierung und echter Mailversand aus diesen Workerbeiträgen gesperrt. Passwort-/Zugangsdatenmails werden durch einen Verweis auf den angemeldeten Kundenbereich ersetzt.

Der begrenzte öffentliche Zustand liegt unter `/public/status.php`. Interne Infrastruktur bleibt im geschützten API-/Adminbereich. Neue Betriebsereignisse enthalten Request-/Jobbezug und Akteur mit begrenztem Kontext. `--retain-days` entfernt alte Auditereignisse und Authentifizierungsprotokolle; Rechnungen und Zahlungen werden dabei nicht gelöscht.

## Kontrollierter Import des vorhandenen Shops

Der Import arbeitet mit einer **eingefrorenen SQLite-Kopie** des vorhandenen Shops, dem zugehörigen ursprünglichen Anwendungsschlüssel und referenzierten privaten Rechnungsdateien. Diese Dateien gehören weder in Git noch in ein Releasepaket. Aktive Quellworker vor einem wirklichen Umschalten stoppen; beide Installationen dürfen nicht dieselben Aufträge bearbeiten.

```bash
php bin/migrate-shop.php --source=/private/shop-copy.sqlite \
  --source-key=/private/original-shop.key --documents=/private/shop-files
# Erst nach kontrolliertem Backup und erfolgreichem Trockenlauf:
php bin/migrate-shop.php --source=/private/shop-copy.sqlite \
  --source-key=/private/original-shop.key --documents=/private/shop-files --apply
```

Ein Import verlangt leere Geschäftstabellen im Ziel. Übereinstimmende E-Mail-Adressen werden nur bei übereinstimmendem Passwort-Fingerabdruck zusammengeführt; Konflikte stoppen den Import. Kollisionen von Backend-Benutzernamen erhalten einen dokumentierten Importnamen und bleiben über den ID-Nachweis zugeordnet. Es gibt keinen automatischen Tausch fremder Kontozugangsdaten.

Der Trockenlauf prüft Tabellen-/Spaltenabdeckung, Mengen, Beträge, Schlüsselkontexte und PDF-Prüfsummen. Unbekannte Spalten, fehlende Belege oder ein falscher Schlüssel stoppen ihn. Beim Import bleiben Geschäfts- und Rechnungsnummern erhalten; verschlüsselte Werte werden mit dem Zielschlüssel neu verschlüsselt. Die ID-Tabelle weist Zuordnungen und Quellzeilenprüfsummen nach. Aufträge mit möglicher externer Schreibabsicht und ausstehende alte Mails werden angehalten. Derselbe Quelldigest lässt sich nicht doppelt buchen.

Die vorhandenen deutschen Shop-URLs bleiben erhalten. Alte Framework-URLs unter `/public/` bleiben als Kernoberfläche verfügbar. Ein produktiver Wechsel des bestehenden Shops, seiner echten PayPal-Webhooks und seines Schedulers ist nicht Bestandteil der Bereitstellung auf `remote.orga-consult.eu`; die Übernahme wurde mit Datenkopien und einer Kopie der Testserverdatenbank geprobt. Die ursprüngliche Shopinstallation wird dabei nicht verändert.

Zum Rückweg gehören immer Datenbanksicherung, Programmstand, private Schlüssel und referenzierte Dokumente gemeinsam. Die SQLite-Importtests prüfen einen unterbrochenen Import und die Wiederherstellung des Vorzustands. Die tatsächliche Testserversicherung wurde in einer separaten MariaDB mit identischen Quellzeilen und drei wiederhergestellten Ansichten geprüft.

## Abgenommener Testbetrieb auf dem eigenen Server

`https://remote.orga-consult.eu` betreibt Framework 3.3.0 mit allen elf Shopmodulen auf PHP 8.4.27, Apache und MariaDB 11.8.6. Die vorhandenen Frameworkkonten bleiben verwendbar. Frameworkadministration: `/src/`; Modul-/Betreiberverwaltung: `/src/modules.php`; Shopverwaltung: `/admin`; Kundenportal: `/konto`. Es wurden keine dauerhaften zusätzlichen Standard- oder Testkonten angelegt.

Der CLI-Worker läuft jede Minute über den tatsächlich eingerichteten Benutzer-Cronjob mit `--limit=10 --retain-days=90`. Der private Heartbeat wurde nach dem manuellen Start erneut durch den Scheduler aktualisiert. Seine letzte Ausführung ist in der Modulverwaltung sichtbar. Sandbox und gesperrte Liveoperationen bleiben aktiv; echte Zahlung, echter Mailversand aus Commerce-Jobs und Providerbereitstellung brauchen eine eigene Betreiberkonfiguration.

Die bestehende Konfiguration wurde vorab gesichert, mit den erforderlichen privaten Speicherpfaden ergänzt und außerhalb des Webroots unter `private/smf-remote/config/runtime.php` abgelegt. `config/config.inc.php` lädt nur noch diese Datei. Individuelle alte Modulschalter wurden privat erhalten; Dateieditor und Debug bleiben aus. Drei verschlüsselte alte API-Cacheeinträge sind bytegetreu erhalten. Ein ursprünglicher `src/.encryption_key` war im FTP-Bestand nicht vorhanden; eine erfolgreiche Entschlüsselung dieser alten Cacheeinträge wird deshalb nicht behauptet. Bestehende Providerwerte aus der Konfiguration bleiben der Ausgangspunkt der gemeinsamen Providerverwaltung.

Lokale Sicherungen liegen außerhalb des Repositorys im Codex-Sicherungsbereich `backups/server-management-framework/2026-10-24-ap18-ap27`. Gesichert sind der ursprüngliche Dateibaum, die Konfiguration, die Datenbank vor und unmittelbar während des Wartungsfensters sowie die privaten Konfigurations-/Schlüsseldateien nach der Umstellung. Der Vorzustand besitzt 37 Basistabellen und drei Ansichten; die Wiederherstellung dieser Datenkopie wurde auf einer separaten MariaDB geprüft.

Bei einem nötigen Rückweg Zugriffe und den neuen Cronjob anhalten, Dateien aus `remote-before` sowie die Datenbank aus der letzten Sicherung des Wartungsfensters gemeinsam zurückspielen und die dazugehörige ursprüngliche Konfiguration wieder einsetzen. Neu hinzugekommene Anwendungsdateien anhand des Deploymentmanifests entfernen, ohne individuelle Fremddateien zu löschen. Die vorangegangene Crontab liegt ebenfalls in der privaten Serversicherung. Nach zwischenzeitlichen echten Geschäftsvorgängen zuerst deren Abgleich durchführen; ein Rücksprung darf diese nicht verlieren. Der temporäre Uploadhelfer ist kein dauerhafter Wiederherstellungsdienst.
