# Betrieb nach AP01–AP09

Stand: 24.10.2026. Diese Hinweise beschreiben den lokal umgesetzten Sicherheitsstand; ein produktiver Upload oder GitHub-Release ist nicht erfolgt.

## Arbeitsbasis

Der ursprüngliche Code entspricht exakt Tag 3.2.7 (`d132883e4ef910309c388f93edc8e1123c9858f0`). Branch: `security/ap01-ap09`. Der beim Start gelesene main-Stand `9c6183428930e651026ddac18bfbd21cce775a39` enthält eine andere Modulmanager-/Submodulstruktur; er wurde verglichen und nicht ungeprüft übernommen. Details: [Git-Ausgangsstand](git-ausgangsstand.json).

Eine vollständige Sicherung des ursprünglichen lokalen Ordners einschließlich Konfiguration und Prüfunterlagen liegt außerhalb des Repositorys unter `/mnt/c/Users/teris/.codex/backups/server-management-framework/2026-10-24-ap01/`. `snapshot.json` enthält die SHA-256-Prüfsumme. Bestehende lokale Anwendungsdaten wurden nicht durch Upstream-Dateien ersetzt.

## Konfiguration, private Dateien und HTTPS

`config/config.inc.php` und `src/sys.conf.local.php` werden nicht mehr versioniert oder paketiert. `config/config.example.php` ist eine neutrale Vorlage mit deaktivierten Providern. Vorhandene Betreiberwerte werden nicht aus der Vorlage überschrieben.

Für den Server feste absolute Pfade verwenden:

- `SMF_CONFIG_FILE`: private PHP-Konfiguration, welche die bestehende Klasse `Config` definiert.
- `SMF_PRIVATE_DIR`: dauerhaftes privates Verzeichnis **außerhalb** des auslieferbaren Projekt-/Webroots, auch außerhalb einzelner Releaseverzeichnisse. Dort liegen Sessions, Anfragebegrenzung, Exporte, Authentifizierungslogs und Schlüssel.
- `SMF_CA_FILE` oder `SMF_PROXMOX_CA_FILE`, `SMF_ISPCONFIG_CA_FILE`, `SMF_OGP_CA_FILE`, `SMF_OVH_CA_FILE`: optionaler CA-Bundle-Pfad für eigene Zertifikate. TLS-Prüfung wird dafür nicht ausgeschaltet.
- `SMF_TRUSTED_PROXIES`: nur tatsächlich vertrauenswürdige Proxy-IP-Adressen, kommasepariert. Nur diese dürfen `X-Forwarded-Proto: https` liefern. Für korrekte Client-IP-Limits muss der Webserver bei Proxybetrieb seine vertrauenswürdige Remote-IP-Verarbeitung passend konfigurieren; ungeprüfte Forwarded-IP-Header werden nicht akzeptiert.

Ohne explizites `SMF_PRIVATE_DIR` wird ein privates, nach Projektpfad getrenntes Verzeichnis neben dem Projekt verwendet. PHP muss mit `short_open_tag=Off` betrieben werden; der bekannte XML-Kurz-Tag-Konflikt bleibt Gegenstand von AP14. Im Produktivbetrieb ist HTTPS erforderlich; die lokale PHP-CLI-Vorschau auf Loopback ist ausgenommen. Cookie-Einstellungen sind zentral: ein Sessionname, HttpOnly, SameSite=Lax und Secure bei HTTPS. Formulare und AJAX-Schreibanfragen benötigen die serverseitig erzeugte CSRF-Freigabe. Diagnose-/Sicherheitsmechanismen lassen sich nicht durch Requestparameter einschalten.

Bestehende lokale Konfiguration kontrolliert aus dem Webroot verschieben:

```bash
php bin/private-config.php
```

Der Befehl kopiert bytegetreu und prüft die Kopie, übernimmt einen vorhandenen alten Verschlüsselungsschlüssel ohne Schlüsseltausch und belässt einen lokalen Loader. Bestehende private Zieldateien werden nicht still überschrieben. Ein vorhandener abweichender Schlüssel muss separat abgeglichen werden. Auf dem Server benötigen private Verzeichnisse 0700 und private Dateien 0600 sowie passende Eigentümerrechte. Bei WSL/Windows sind zusätzlich die Windows-Dateirechte maßgeblich.

Die bisherige Datenverschlüsselung wird nicht nebenbei auf ein anderes Format umgestellt. Bereits verschlüsselte Werte benötigen denselben Schlüssel; neue Schlüssel werden im privaten Verzeichnis erzeugt. Das manuelle Verschieben ist auf einer Kopie mit vorhandenem Schlüssel zu prüfen, bevor eine Bestandsinstallation umgestellt wird.

## Kleine Sicherheitsmigration und Anmeldung

Vor Freigabe einer bestehenden Installation Datenbank und Schlüssel sichern, dann im CLI-Kontext mit der passenden privaten Konfiguration ausführen:

```bash
php bin/security-migrate.php
php bin/audit-default-accounts.php
```

Die Migration ergänzt ausschließlich `security_migrations`, `security_remember_tokens` und `security_resource_assignments`. Sie ist wiederholbar. Alte Klartext-Remember-Tokens werden verworfen; Konten und Passworthashes bleiben erhalten. Die vollständige Bereinigung aller Frameworkschemata erfolgt später in AP11. Der Prüfbefehl für Beispielkonten verändert keine Konten; bei einem Treffer meldet er die betroffene Admin-ID und endet mit Status 2.

Für neue Installationen die kontoarmen Schemata aus `install/database-structure-install-*.sql` verwenden und die Sicherheitsmigration anschließend ausführen. Auch die manuellen Vollschemata erzeugen jetzt keinen vorgegebenen Administrator mehr. Ein Administrator wird ausdrücklich per CLI angelegt:

```bash
php bin/create-admin.php --username=meinadmin --email=admin@example.org
```

Das Passwort wird über die Standardeingabe gelesen, nicht als Kommandozeilenargument. Neue/geänderte Passwörter benötigen mindestens zwölf Zeichen. Vorhandene Passwörter werden beim Login weiterhin geprüft und nicht automatisch ersetzt. Der bisherige browserbasierte Installer/Setup und die alte Klartext-Passwort-Rücksetzung sind gesperrt; vollständiger neuer Installations-/Resetablauf folgt in AP10/AP11/AP13.

Nach einem Upgrade ist eine erneute Anmeldung erforderlich. Erfolgreiche Anmeldungen erneuern Session-ID und CSRF-Token. Remember-Tokens besitzen Selektor und geheimen Validator, von dem nur der Hash gespeichert wird; jede Verwendung rotiert ihn. Passwortänderung, Kontosperre und Rollenänderung werden bei der nächsten Anfrage anhand des tatsächlichen Kontos geprüft. Passwortwechsel macht andere Sitzungen ungültig; Logout widerruft die Kunden-Langzeittokens.

Die konfigurierte Sitzungslaufzeit gilt zentral. Hintergrund-Heartbeats verlängern keine inaktive Sitzung. Echte Benutzeraktivität und der ausdrückliche Verlängerungsaufruf aktualisieren sie; die Anzeige verwendet die vom Server bestätigte Restzeit. Fehlerhafte Anfragen setzen den Timer nicht zurück.

## Rollen und Ressourcen

Die 14 bisherigen Backendmodule verwalten globale Providerbestände und sind daher auf aktive Administratoren begrenzt. Dateieditor und Web-Updater bleiben vollständig gesperrt. Der Endpoint-Tester ist standardmäßig deaktiviert und nur für ausdrücklich freigegebene Admin-/Testumgebungen vorgesehen. Normale Backend-Benutzer erhalten `src/account.php`; Kunden verwenden weiterhin das Kundenportal. Der Support-Debugparameter öffnet keine internen Notizen oder Statistiken mehr.

Für Zugriffe auf einzelne Kundenressourcen existiert der begrenzte Endpunkt `src/api/resources.php`. Die lokale Zuweisung speichert getrennt Benutzerbereich, Nutzer-ID, Provider-Konto, Ressourcentyp, externe ID, Rechte und optionalen Ablauf. Vorhandene `user_permissions` werden **nicht** automatisch als Ressourcenbesitz interpretiert: Dort stehen häufig externe Kontoverknüpfungen.

| Modul | Kundenzugriff über ausdrückliche Zuordnung | Legacy-Verwaltung |
| --- | --- | --- |
| Proxmox | Eigene VM/LXC ansehen; Start/Stop/Reboot nur mit Schreibrecht und passender Instanzkennung | Admin |
| Network | Eigene zugeordnete VM/LXC ansehen; keine ungeprüfte Netzänderung | Admin |
| ISPConfig / Database / Email | Einzelne eigene Website/Datenbank/Mailbox ansehen; Providergruppe wird geprüft | Admin |
| DNS / OVH / Virtual MAC | Explizit zugeordnetes Objekt ansehen, keine globalen Bestandslisten | Admin |
| Admin / Endpoints / Migration / Support Tickets / Custom Module | Kein Zugriff auf globale Backendaktionen | Admin |
| File Editor | Gesperrt | Gesperrt |

Beispiel für eine vom Administrator bewusst geprüfte VM-Zuordnung:

```bash
php bin/assign-resource.php --realm=customer --subject=42 --module=proxmox --type=qemu --external-id=101 --node=pve1 --instance-id=00000000-0000-0000-0000-000000000101 --write
```

Die Beispiel-Instanz-ID muss durch den tatsächlichen `vmgenid` aus der Proxmox-Konfiguration ersetzt werden. LXC benötigt eine eindeutige Zeile `SMF-RESOURCE:<32 Hexzeichen>` in der Beschreibung und denselben Wert als `--instance-id`. Eine wiederverwendete VMID oder abweichende Instanzkennung wird vor einem Steuerungsaufruf abgewiesen. Für ISPConfig muss die geprüfte `sys_groupid` als `--group-id` angegeben werden. Der CLI-Befehl schreibt eine ausdrückliche lokale Zuordnung und prüft deren Form; er führt keine externen Änderungen aus. Die Zuordnung zum richtigen Kunden verantwortet der ausführende Administrator.

Die neue Einzelressourcen-Schnittstelle liefert nur einen begrenzten Feldausschnitt. Weitere Kundenoperationen wie DNS-Änderungen, Hostingzugänge, Netze, Einrichtung, Löschung und Bestellungen werden bei den späteren Provider-/Shop-Paketen gezielt ergänzt. Es wird keine unsichere globale Legacyaktion als Kundenfunktion weitergereicht.

## Dateien, Pakete und Rücknahme

DNS-Exporte verwenden zufällige IDs, Eigentümerbindung und Ablaufzeit im privaten Speicher. Frei übergebene Dateinamen/Pfade aus dem gemeinsamen System-Tmp werden nicht mehr angenommen. Fremde Kunden, fremde Benutzerbereiche, Symlinks, ungültige IDs und abgelaufene Exporte werden abgewiesen. Sichere Admin-Backup-Downloads sind als begrenzter privater Dateidienst vorbereitet; der alte Web-Updater verwaltet sie bis AP15 nicht mehr.

`bin/package-files.php` prüft und gibt ausschließlich die ausdrücklich versionierten Einträge aus `deploy/package-files.json` zurück. Neu hinzukommende Dateien werden nicht automatisch aufgenommen; fehlende Dateien, Pfadwechsel und Symlinks brechen die Auswahl ab. Produktivkonfiguration, Schlüssel, Datenbanken, Logs, Backups, Tests und offene Diagnose-/Installations-/Update-Einstiege sind ausgeschlossen. Das ist noch kein in AP28 gebautes oder veröffentlichtes Releasearchiv. Die Liste wird bei späteren neuen Anwendungsdateien bewusst aktualisiert.

`.htaccess` und `deploy/nginx-security.conf` enthalten die zusätzlichen Webserversperren. Die Nginx-Regeln müssen vor einem allgemeinen PHP-Regex-Handler eingebunden werden. Apache benötigt wirksames AllowOverride und mod_rewrite; Diagnose-/Include-Dateien besitzen außerdem eigene direkte PHP-Sperren. Die reale Serverkonfiguration und das endgültige Webroot werden erst bei AP30 abgenommen. Ein direkt gestarteter PHP-Entwicklungsserver ersetzt keine Produktions-Webserverregeln.

Rücknahme auf einer Testkopie: passenden Git-Ausgangsstand/Archiv wiederherstellen, vorher Datenbank, aktuelle Konfiguration und Schlüssel sichern. Die drei neuen Sicherheitstabellen sind additive Ergänzungen; sie müssen beim Rücksprung nicht sofort entfernt werden. Bereits widerrufene Tokens werden nicht wieder aktiviert. Private Konfiguration/Schlüssel und zwischenzeitlich geänderte Passwörter gehören ausdrücklich zum Rückrollplan; ein Git-Checkout alleine setzt sie nicht zurück.

[Tests und lokale Befehle](../../tests/README.md) · [Umsetzungsstatus](UMSETZUNG-AP01-AP09.md)
