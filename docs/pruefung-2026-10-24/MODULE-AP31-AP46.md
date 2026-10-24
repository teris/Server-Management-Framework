# Provider-/Produktmodule, Einrichtung und Upgrade

Stand 3.10.0, 24.10.2026. Der Arbeitsumfang umfasst AP31 und AP35–AP46. Das zuvor veröffentlichte Release 3.9.9 bleibt unverändert.

## Modulgrenzen

| Komponente | Vertrag und Besitz |
| --- | --- |
| `shop.connections` | Gemeinsame verschlüsselte Anschlüsse, zeitlich begrenzte Diagnose und Einrichtungsstatus |
| `provider.ogp`, `provider.ispconfig`, `provider.proxmox`, `provider.ovh` | Adapter, Konfigurationsfelder, Leseschreibfähigkeiten, API-Diagnose und Anschlussidentität |
| `product.gameserver` | Spielkatalog, Preisoptionen, Portkontingente, Konten-/Homebindung, Bereitstellung, Verwaltung und Lebenszyklus; eigenes Schema und UI |
| `product.webhosting` | Hostingoptionen, Website-/Mail-/DNS-/Zugangslogik, Bereitstellung und eigenes Hosting-Schema |
| `product.vps` | QEMU/LXC, Speicherreservierung, Abschluss-/Exportlogik, eigenes Schema, Infrastruktur- und Sicherungsansichten |
| Commerce | Eine Kundenidentität, Angebote, Bestellungen, Zahlungen, Rechnungen, Verträge und Dokumente für alle Produktarten |

Module deklarieren Beiträge im Manifest. Vertrauenswürdige Runtime-Dateien werden innerhalb des jeweiligen Modulordners aufgelöst. Version, PHP-Anforderung und alle Abhängigkeiten müssen passen. Abgeschaltete Anschlüsse und Module sperren auch bereits aufgelöste Adapter vor Provider-IO. Änderungen von Zugangsdaten oder Freigaben verwerfen alte Adapter. Ein zusätzliches Providermodul benötigt keine Änderung an `framework.php`.

Bereits angewendete Migrationen werden nicht überschrieben. Neue Hosting-/VPS-Migrationen übernehmen vorhandene Tabellen mit `CREATE TABLE IF NOT EXISTS`; die historischen Zeilen und Schlüssel bleiben erhalten. Gameserver- und Anschlussmigrationen binden zusätzlich das Schema-JSON in ihre Prüfsumme ein. Ursprungsnachweise des übernommenen Shops behalten ihre alten Quellprüfsummen und nennen die neuen Zielpfade.

## Anschlüsse und konkrete Fähigkeiten

Der Erstinstaller und die Anschlussverwaltung unterstützen vier Systeme. Ein erreichbarer Login bedeutet nicht, dass alle Produktfunktionen freigegeben sind.

- OGP verlangt einen vorhandenen Administrator-API-Token. Geheimnisse liegen im POST-Body. Ein Tokenwechsel ändert die Anschlussidentität und macht alte Ressourcenbindungen prüfpflichtig. Für bestätigte Installation, Identität, Logs und gezielte Löschung ist die [optionale OGP-Erweiterung](../../integrations/ogp/README.md) erforderlich.
- ISPConfig prüft Web-, Mail- und Datenbankserver getrennt. Die Liste verfügbarer Funktionen bestätigt keine Remote-Benutzerrechte. Schreibfähigkeiten erfordern zusätzlich die ausdrücklich hinterlegten Benutzerfreigaben aus der ISPConfig-Konfiguration. Der tatsächliche Schreibaufruf bleibt serverseitig berechtigungsgeprüft. Eine nicht vorhandene DKIM-Sonderfunktion sperrt den Grundzugang nicht. Reseller-ID, Kundenvorlage, Serverzuordnung und Produktquoten bleiben ausdrücklich konfigurierbar.
- Proxmox liest Nodes, Ressourcenpool und Berechtigungen. VM-Anlage und VM-Steuerung sind getrennte Fähigkeiten. Passwort-/Ticket- und API-Tokenzugang bleiben möglich. Vorhandene Ressourcen benötigen weiterhin die bestätigte Kunden- und Poolzuordnung.
- OVH liest den eigenen Credential-Vertrag und seine konkreten Pfad-/Methodenregeln. Zonenlesen, Zonenänderung und Domainbestellung sind getrennt. Ein Leseschlüssel erhält dadurch keine Schreibfreigabe.

Diagnosen gelten höchstens 24 Stunden und nur für den geprüften Anschluss. Ein fehlgeschlagener Wiederholungstest entfernt die alte Freigabe. Ein Produkt verlangt seine benötigten Fähigkeiten; Mail-/Datenbankkontingente beim Hosting benötigen die entsprechenden Serverrollen und Remote-Funktionen.

## Gameserverbetrieb

Unter `/admin/gameserver` wählt der Betreiber Agent, Spiel/Mod, Linux-Architektur, Startkarte, Slots, Standort, einen exklusiven Portblock und die Aufbewahrung. Preiswerte werden in ganzen Netto-Cent gepflegt; der Checkout berechnet erneut auf dem Server. Nicht durchsetzbare RAM-/Disk-/CPU-Garantien, fremde IPs, manipulierte Optionen oder ungeeignete Definitionen werden abgewiesen. Unvollständige Angebote bleiben Anfragen.

Nach bestätigter Zahlung erzeugt der gemeinsame Worker eine dauerhafte Schreibabsicht, ein eindeutig zugeordnetes OGP-Konto und genau ein Home. Ein zufälliger Pfad trennt neue Spielinstallationen von alten numerischen Home-Verzeichnissen. Erst ein abgeschlossener Installer, vorhandenes Programm und beobachteter Spielprozess ergeben eine aktive Leistung. Unklare Antworten bleiben zur Prüfung stehen; die Administration gleicht die eigene Kennung ab.

Kunden erhalten Status, begrenztes Protokoll, Start/Stopp/Neustart und eingeschränktes RCON über die gemeinsame Ressourcenprüfung. RCON erlaubt Status-/Spielerliste sowie begrenzte Chatnachrichten, keine beliebigen Kommandos oder Pfade. Kunde, Verbindung, Agent, Home und physische Identität werden vor Providerzugriff geprüft. Kündigung, Sperre und Steuerung verwenden dieselbe Leistungssperre.

Der bestehende Zahlungs-/Rechnungs-/Vertragsweg wird weiterverwendet. Zahlungsverzug oder Rückzahlungsprüfung sperrt die Leistung; eine bestätigte Verlängerung kann sie wieder freigeben. Vertragsende stoppt und deaktiviert das Home, wartet die vereinbarte Aufbewahrung ab und entfernt ausschließlich dieses Home. Ports werden erst nach bestätigter Dateisystem- und Datenbankentfernung freigegeben. Das gemeinsame OGP-Konto wird bei normaler Leistungskündigung nicht gelöscht.

## Einrichtung und Bestandsupgrade

`/admin/einrichtung` zeigt SMTP-Prüfung, Rechnungssteller, Vertragsunterlagen, tatsächlichen Workerheartbeat und fehlende Produktfähigkeiten. Die Testmail geht ausschließlich an die Adresse des angemeldeten Administrators; SMTP-Annahme ist keine Zustellgarantie.

Bestehende Installationen verwenden **keinen Erstinstaller**. Vor dem Dateiaustausch werden Anwendung, Datenbank, privater Speicher, Schlüssel und Dokumente gesichert. Danach können neue Module in der angemeldeten Einrichtung ausgewählt und als Upgradeauftrag vorgemerkt werden. Der CLI-Worker erstellt unter Wartung einen nativen Datenbanksnapshot und Konfigurationskopien, prüft den vorher angezeigten Manifeststand und führt die Modulmigrationen in Abhängigkeitsreihenfolge aus.

CLI-Beispiel für nachgelieferte Module:

```sh
php bin/upgrade.php --modules=product.gameserver,product.webhosting,product.vps --run
php bin/upgrade.php --status
```

Bei einem abgebrochenen Auftrag bleibt die Wartung bestehen. `/upgrade.php` ist der angemeldete, CSRF-geschützte Recoveryweg. Alternativ sind `php bin/upgrade.php --resume=AUFTRAGSID --run` bzw. `--restore=AUFTRAGSID --run` verfügbar. Eine Datenbankwiederherstellung muss zum zugehörigen Dateistand erfolgen. Diese Wege erzeugen keinen zweiten Administrator und ändern keine bestehenden Zugangsdaten.

Cron bleibt eine Betreiberaufgabe, zum Beispiel jede Minute `php /pfad/zum/shop/bin/worker.php --limit=10 --retain-days=90`. Der Assistent meldet einen Scheduler erst nach einem tatsächlich aktuellen Heartbeat als bereit.

## Signierte Pakete

Ein Release wird aus einem einzigen Git-Commit reproduzierbar gebaut. Es enthält Vollpaket, getrennten Kern, Commerce-Shop, vier Provider, drei Produktmodule und ein Kern-Updatepaket. Das Vollpaket ist der normale FTP-Einstieg. Betreiberkonfiguration, Freigabedatei, Schlüssel, Datenbanken, Logs und Sicherungen sind ausgeschlossen.

`manifest.json` und `manifest.sig` sichern jedes Paket; `SHA256SUMS` besitzt eine zusätzliche Signatur. Vertrauenswürdig ist nur ein außerhalb der Anwendung gespeicherter öffentlicher Schlüssel mit separat geprüftem Fingerprint. Fingerprint des verwendeten RSA-3072-Schlüssels (DER): `c482aca369adf6ffc3154e4b415e6d094460304226f9609e3741ad5700c9b592`.

Eine reduzierte **neue** Installation kann vor dem Upload aus signierten Komponenten zusammengesetzt werden:

```sh
php bin/components.php --key=/privat/smf-release-public.pem --fingerprint=VEROEFFENTLICHTER_SHA256 \
  --package=/downloads/server-management-framework-3.10.0-framework.core.zip \
  --package=/downloads/server-management-framework-3.10.0-commerce.shop.zip \
  --package=/downloads/server-management-framework-3.10.0-provider.ogp.zip \
  --package=/downloads/server-management-framework-3.10.0-product.gameserver.zip \
  --assemble=/neuer/pfad/gameshop
```

Alle Abhängigkeiten, Versionen, Signaturen und Dateien werden vor dem Anlegen des Zielordners geprüft. Ein vorhandener Zielordner wird abgewiesen. Die abgeleitete Dateiinventur und Herkunft der ausgewählten Pakete werden festgehalten. Im neuen Shop anschließend `php bin/setup.php --prepare` ausführen und den geschützten Browserassistenten verwenden. Ein reiner Kern ohne Commerce wird über die bestehende Kern-CLI `bin/install.php` mit eigener Konfiguration eingerichtet.

Neue Provider-/Produktmodule lassen sich mit denselben `--key`, `--fingerprint` und `--package`-Angaben sowie `--install` in einen konfigurierten Shop übernehmen. Hier werden ausschließlich Provider-/Produktverzeichnisse ersetzt, zuvor nativ gesichert und migriert. Eine fehlgeschlagene Migration stellt Quellstand und Datenbank wieder her. Bei Prozessabbruch: privates Komponentenjournal prüfen und `php bin/components.php --recover=SICHERUNGSID` verwenden. Kern-/Commerce-Austausch erfolgt über den signierten Framework-Updater oder die dokumentierte gesicherte Serverübernahme.

## Prüfnachweise und Grenzen

Die Tests unterscheiden simulierte Providerprotokolle, echte lokale TLS-/SMTP-/SQL-/Browserprüfungen und tatsächliche OGP-Schreibzugriffe. Ein Mock ist kein Nachweis einer Spielinstallation.

- 26 lokale Suiten: Modulverträge, Rechte, Zahlungen, Installation, Jobs, Lebenszyklus, signierte Pakete, Upgrade/Recovery und SMTP.
- Tatsächliches signiertes Release: 584 Datei-/Paketprüfungen, 27 HTTP-/SMTPS-Prüfungen und zehn Update-/Rollbackprüfungen; reduzierte Hosting- und Gameserverpakete erfolgreich installiert. Zwei Builds desselben Commits sind identisch.
- Native MariaDB- und MySQL-8-Tests: 59 Gameserver-/20 Provider-Matrixprüfungen sowie je 84 Installertests. Chrome prüft 36 neue Modul-/Rechte-/Layoutfälle auf Desktop und Mobilgerät.
- Reduzierte signierte Neuinstallationen: Gameserver ohne ISPConfig-/VPS-Dateien und Hosting ohne OGP-Dateien. Nachinstallation eines Moduls mit erhaltener Identität und Schlüssel sowie native SQLite-Rücknahme einer fehlschlagenden Migration. Ein zusätzlich erzwungener Prozessabbruch wurde über das private Komponentenjournal aus einem frischen CLI-Prozess zurückgenommen.
- OGP-Brücke: HTTPS/Auth/IP, Eigentümer, ID-Wiederverwendung, Verzeichnisgrenzen, Installations-/Löschstatus und Geheimnisfilter mit isolierten nativen Schnittstellenfixtures.
- Upgrade auf einer Kopie der echten Bestandsdatenbank: 117 Tabellen und drei Views, 19 Module, erhaltene Geschäftszeilen und anschließend exakt übereinstimmender nativer Restore; keine Provideraufrufe.
- Reale OGP-Abnahme: eine eigene Half-Life-/SteamCMD-Leistung, vier Slots und zehn reservierte Ports; Testzahlungsfreigabe durch Administration, kein echter Geldfluss. Anfragen-/Bestell-/Rechnungsweg, echter laufender Agentprozess, RCON, Kundenisolierung und kontrollierte Beendigung wurden geprüft. Home und separates Testkonto wurden entfernt, Ports freigegeben. Die fünf ursprünglichen Homes blieben unverändert.

Eine echte ISPConfig-, OVH- oder Proxmox-Neuanlage wird hier nicht als geprüft behauptet. Diese Anbieter wurden durch Verträge und Fehler-/Berechtigungsmatrix getestet; der Betreiber muss passende Produktprofile und reale Rechte vor dem Liveverkauf abnehmen. Windows, Rsync und nicht durchsetzbare Ressourcengarantien bleiben gesperrt bzw. Anfragebetrieb. Das bestehende Testsystem bleibt standardmäßig Sandbox.
