# Umsetzung AP01–AP09

> Historischer Prüfstand. AP10–AP17 sind inzwischen lokal umgesetzt; aktuelle Befehle und Grenzen stehen in [BETRIEB-AP10-AP17.md](BETRIEB-AP10-AP17.md) und [UMSETZUNG-AP10-AP17.md](UMSETZUNG-AP10-AP17.md).

Stand: **24.10.2026** · Branch: **security/ap01-ap09** · Ergebnis: **lokal umgesetzt und geprüft**.

Dieser Block setzt die Phase A des [Arbeitsplans](ARBEITSPLAN.md) um. Die ursprünglichen Befunde bleiben im [historischen Prüfbericht](PRUEFBERICHT.md) nachvollziehbar. Version 3.2.7 wurde bewusst noch nicht auf eine neue Releaseversion gesetzt. Ein Upload, Git-Push oder GitHub-Release ist nicht erfolgt.

## Ergebnis je Arbeitspaket

| Paket | Umsetzung | Nachweis |
| --- | --- | --- |
| AP01 | Vollständiger privater Ausgangssnapshot, Git-Arbeitsbranch, Abgleich des Archivs mit Tag 3.2.7 und main. Lokale Konfiguration, Schlüssel und Laufzeitdaten aus künftiger Versionierung ausgeschlossen. | [Git-Ausgangsstand](git-ausgangsstand.json); Originalarchiv entspricht Tag `d132883e4ef910309c388f93edc8e1123c9858f0`. |
| AP02 | Diagnose-, Test-, Installations- und Include-Einstiege direkt gesperrt; Dateieditor und alter Web-Updater bleiben geschlossen. Standardadmins aus den Vollschemata entfernt. Apache-/Nginx-Regeln und feste Paketdateiliste ergänzt. | HTTP-Prüfungen einschließlich PATH_INFO; Paketprüfungen; `bin/audit-default-accounts.php` für bestehende Installationen. |
| AP03 | Isolierte SQLite-Fixtures, getrennte Rollen und Kunden, lokaler HTTPS-Provider, Browsercode-Prüfungen und CI für PHP 8.3/8.4/8.5 eingerichtet. | [Testbericht](testergebnisse-ap01-ap09.json), [Testanleitung](../../tests/README.md); fünf Gegenproben scheitern erwartungsgemäß am unveränderten Tag 3.2.7. |
| AP04 | Gemeinsamer, live gegen aktive Konten geprüfter Benutzerkontext; Adminprüfung vor Handler-/Modul-/Providerinitialisierung. Normale Backendnutzer erhalten eine eigene Kontoseite. | Rollenmatrix Gast/Kunde/Backendnutzer/Admin; Ablehnung ohne Provideraufruf; Rollenänderung und Sperrung werden unmittelbar geprüft. |
| AP05 | Einheitliche Regeln für alle 14 Legacy-Module. Globale Providerverwaltung nur für aktive Admins; begrenzte Einzelressourcen-API mit ausdrücklichem Eigentümer, Providerkonto, Typ, Ressource und Schreibrecht. | Zwei getrennte Kunden, alle Modulregeln und elf Kombinationen aus Modul/Ressourcentyp; falsches Providerkonto, deaktiviertes Modul, fremdes Objekt und wiederverwendete VMID werden abgewiesen. |
| AP06 | Nutzergebundene, zufällige Download-IDs, private Ablage, Ablauf und Typ-/Größenbegrenzung; DNS-Export umgestellt. Privater Admin-Backup-Dateidienst vorbereitet. | Erlaubter Export sowie fremder Nutzer/Benutzerbereich, Fremdpfad, Symlink und Ablauf geprüft. Dateieditor wird nicht wieder freigegeben. |
| AP07 | Zentrale HTTP-Methoden- und CSRF-Prüfung, Token in Formularen und Same-Origin-AJAX. Persistente, gesperrte Zähler für Login und öffentliche Schreibaktionen; API-Limits aus der Konfiguration. | Fehlende/alte Tokens, verbotene Methoden, andere Browser-Sitzung, PATH_INFO und 18 gleichzeitige Limitanfragen geprüft. |
| AP08 | HTTPS-Pflicht, Zertifikats-/Hostprüfung, eigene CA und Timeouts für Providertransporte. Geheimnisse aus Rückgaben/Logs entfernt. Privater Konfigurationsloader und überprüfte Schlüsselübernahme ohne Formatwechsel. | Gültige private CA, unbekannte CA, falscher Host, abgelaufenes Zertifikat und HTTP getestet; Konfigurations-/Schlüsselkopie, Wiederholung und Konfliktfälle geprüft. |
| AP09 | Ein Sessioncookie, ein Timeoutmodell, ID-/CSRF-Regeneration, aktuelle Kontoprüfung und widerrufbare Passwortbindung. Gehashte, atomar rotierte Remember-Tokens; gemeinsame Passwortänderung und Logout. | Ablauf, mehrere Sitzungen, Passwortwechsel, Sperre, Token-Replay, zwölf parallele Einlösungen und serverbestätigte Browserlaufzeit geprüft. |

AP09 benötigt drei neue Sicherheitstabellen. Dafür wurde eine kleine, ausdrücklich per CLI auszuführende additive Migration vorgezogen. **AP11 bleibt offen**: Die vollständige Zusammenführung der Installations-/Vollschemata und Bestandsmigration ist damit nicht erledigt. Normale HTTP-Aufrufe führen keine Schemaänderungen aus.

## Sicherheitsbefunde und bewusst gesperrte Funktionen

- **F01/F02:** zentrale Admin- und Modulgrenzen behoben. Alte globale Aktionen werden nicht als Kundenrechte weitergereicht.
- **F03:** Dateieditor vollständig gesperrt. Seine allgemeine Lese-/Schreibfunktion wird nicht als repariert freigegeben.
- **F04:** bisheriger Web-Updater vollständig gesperrt, auch für Admins; fachliche Reparatur in AP15.
- **F05:** offene Diagnosen, Status-, Include- und Downloadpfade begrenzt; öffentliche Providerbestände und Support-Debugausgabe entfernt.
- **F06–F09:** Requestschutz, TLS, Rollen-/Sitzungsmodell und Kunden-Remember-Login für den geprüften Umfang umgesetzt.
- **F23:** neue Schemata erzeugen kein bekanntes Beispiel-Adminkonto. Bestehende Konten werden aus Datenerhaltungsgründen nicht automatisch gelöscht; der CLI-Prüfbefehl meldet bekannte Standardpasswörter.
- **F25:** reproduzierbare Tests, Arbeitsbasis und CI angelegt; vollständiger Releaseprozess bleibt AP28–AP30.

Die alte Klartext-Passwort-Rücksetzung bleibt ebenfalls gesperrt, bis AP13 einen kontrollierten Ersatz liefert. Eigene Passwortänderungen prüfen das aktuelle Passwort und widerrufen andere Sitzungen. Weitere Befunde aus AP10–AP27 bleiben im Arbeitsplan offen; einzelne notwendige Vorarbeiten wie die BOM-Entfernung schließen diese Pakete nicht automatisch.

Kundenoperationen sind bewusst begrenzt: zugeordnete Objekte ansehen sowie eigene Proxmox-VMs mit Schreibrecht starten/stoppen/neustarten. QEMU benötigt die tatsächliche `vmgenid`, LXC einen eindeutigen Beschreibungsmarker; ISPConfig prüft zusätzlich die Providergruppe. Neue DNS-/Hosting-/Netzwerkänderungen und Bestellungen gehören zu den späteren Provider-/Shop-Paketen. Die manuelle Zuordnung muss der Betreiber anhand des tatsächlichen Kundenbestands prüfen.

## Prüfung und praktische Grenzen

Die maschinenlesbaren Ergebnisse stehen im [Testbericht](testergebnisse-ap01-ap09.json). Die vollständige lokale Suite läuft mit PHP **8.3.6**, Node **24.22.1**, einer temporären SQLite-Datenbank und einem lokalen HTTPS-Provider. Syntaxprüfungen verwenden `short_open_tag=Off`; die allgemeine XML-Kurz-Tag-Korrektur folgt in AP14.

Die **200 Einzelprüfungen**, die Syntaxprüfung von **137 PHP-Dateien** und vier geänderten JavaScript-Dateien sind bestanden. Die fünf Gegenproben am alten Tag scheitern wie erwartet.

Ein zusätzlicher sauberer Checkout aus dem Git-Index ohne die ignorierte Betreiberkonfiguration hat dieselbe Suite vollständig bestanden (zehn Prüfläufe ohne die separat ausgeführte Tag-Gegenprobe). Echte Datenbanken und Providerzugangsdaten werden dabei nicht geladen. Die CI-Matrix ist vorbereitet; Remote-Actions wurden ohne Push noch nicht ausgeführt. PHP 8.4/8.5 werden daher nicht als lokal bestätigt ausgewiesen.

Die Prüfungen ersetzen keine Serverfreigabe: Live-MySQL/MariaDB/PostgreSQL, vorhandene individuelle Datenbestände, echte Provider, Apache/Nginx und Upload/Restore müssen im späteren Zielbetrieb gesondert geprüft werden. Es wurden keine externen Ressourcen verändert und keine produktive Datenbank migriert.

Die vorhandene lokale Konfiguration wurde bytegetreu in den privaten Speicher außerhalb dieses Projektordners übernommen. Im alten Pfad liegt nur noch ein ignorierter Loader. Vorhandene verschlüsselte Werte werden nicht auf ein anderes Format oder einen neuen Schlüssel umgestellt.

## Weiterarbeit

Vor einem Einsatz dieses Stands die [Betriebs- und Migrationshinweise](BETRIEB-AP01-AP09.md) abarbeiten: feste private Pfade/HTTPS, gemeinsame Sicherung von Datenbank und Schlüsseln, Sicherheitsmigration, Prüfung bestehender Beispielkonten sowie bewusste Ressourcenfreigaben. Alte Remember-Cookies erfordern eine erneute Anmeldung.

Der nächste fachliche Block ist **AP10–AP12**: Registrierung/Mail, verbindliches Gesamtschema und tatsächlich geprüfte Datenbankunterstützung. Die Shopmodularisierung bleibt bei AP18–AP27, Versionswechsel und Veröffentlichung bei AP28–AP30.
