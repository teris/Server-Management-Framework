# Schrittweiser Arbeitsplan

Stand: 24.10.2026 · Ausgangsversion: 3.2.7 · Alle Arbeitspakete sind zunächst **offen**.

Dieser Plan setzt die [25 Befunde des Prüfberichts](PRUEFBERICHT.md) und das [Shopkonzept](MODULARES-SHOPSYSTEM.md) in einzeln beauftragbare Schritte um. Die Reihenfolge berücksichtigt Sicherheit, Datenbestand, bestehenden „Online Shop“, Versionsaktualisierung, GitHub-Release und Upload auf den eigenen Server.

## Vorgehensweise pro Arbeitspaket

1. Betroffene Dateien und bestehenden Daten-/Konfigurationsstand festhalten; konkrete Abnahme aus dieser Liste übernehmen.
2. Eine begrenzte Änderung auf versionierter Arbeitsbasis umsetzen. Zusammenhängende Sicherheitskorrekturen dürfen gemeinsam geliefert werden.
3. Passende Regressionstests mit lokalen Daten und simulierten Provider-Antworten ausführen. Externe Schreibaktionen sind kein Standardtest.
4. Änderung, Prüfergebnis, Restrisiken und gegebenenfalls Migration/Rücknahme dokumentieren.
5. Arbeitspaket erst nach erfüllter Abnahme als abgeschlossen markieren; offene Punkte im Plan weiterführen.

**Aufwand:** S = ungefähr 0,5–1, M = 1–3, L = 3–7 konzentrierte Personentage. Das sind erste Größenordnungen, keine Termine oder Angebote. Bei L-Paketen wird vor Umsetzung ein begrenzter erster Teil mit eigener Abnahme gebildet. Nach AP01/AP03 und dem Schemaabgleich werden die Schätzungen aktualisiert.

## Phase A: Sichere Arbeitsbasis und unmittelbare Absicherung

| Status / ID | Paket und konkretes Ergebnis | Abhängigkeit | Größe | Abnahme / Befunde |
| --- | --- | --- | --- | --- |
| [ ] AP01 | **Git-Arbeitsbasis herstellen.** Lokalen Archivstand mit GitHub main und Tag 3.2.7 vergleichen, eigene Abweichungen separat sichern, Arbeitsbranch und Ausgangssnapshot anlegen. Geheimnisse, Datenbanken und Laufzeitdateien ausschließen. | Keine | S–M | Jede Änderung ist einem Ausgangsstand zuzuordnen; keine vorhandenen lokalen Anpassungen gehen verloren. F25. |
| [ ] AP02 | **Gefährliche Einstiege absichern.** Dateieditor zunächst deaktivieren; Debug-/Test-/Include-/Installationspfade aus Produktionszugriff und Paketen nehmen; offene Wiederinstallation schließen. Prüfung vorhandener Beispiel-Admin-Konten vorbereiten. Serverregeln für Apache und Nginx bereitstellen. | AP01; dringende Sperrregeln können vorgezogen werden | M | Nicht angemeldete HTTP-Anfragen erreichen weder Updater noch Debug-/Dateiaktionen; keine bekannten Standardkonten bei Neuinstallation. F01–F05, F23. |
| [ ] AP03 | **Isolierte Prüfumgebung und CI aufbauen.** Testdatenbank, getrennte Nutzerrollen, Provider-Testadapter und reproduzierbare Syntax-/Schema-/Regressionstests. Zunächst bewährte Alt-Laufzeit erfassen; neue Zielmatrix später ergänzen. | AP01 | M | Tests laufen aus einem frischen Checkout ohne Live-Zugangsdaten. Sie bilden die wesentlichen Befunde tatsächlich ab und schlagen vor deren Behebung fehl. F25. |
| [ ] AP04 | **Benutzerkontext und zentrale Rollenprüfung reparieren.** Sessionformat vereinheitlichen, aktive Konten prüfen, AdminHandler und administrative Routen vor dem Dispatch schützen; require_admin verbindlich machen. | AP03 | M | Rollenmatrix Gast/Kunde/Backend-Nutzer/Admin besteht für Seite und AJAX; Ablehnung erfolgt vor Provider-Aufruf. F01, F02, F08. |
| [ ] AP05 | **Ressourcenrechte für alle 14 Module festlegen.** Für jede Aktion Rolle, Kundenkonto, Provider-Konto und konkrete Ressource prüfen. Deaktivierte Module und Endpoint-Proxy einbeziehen; interne Ticketnotizen schützen. | AP04 | L | Kunde A kann keine VM, Zone, Website, Mailbox, Datenbank oder Tickets von Kunde B lesen/ändern. Admin darf nur entsprechend seiner Rechte handeln. F02, F05. |
| [ ] AP06 | **Dateien, Exporte und Backups begrenzen.** Verzeichnisgrenzen/Symlinks sicher prüfen, opaque Export-/Backup-IDs verwenden, temporäre Dateien nutzergebunden verwalten. Dateieditor nur bei nachgewiesener sicherer Begrenzung wieder freigeben. | AP04 | M | Fremdpfade und Symlinks scheitern; erlaubte Downloads funktionieren; Daten-/Konfigurationsdateien sind weder öffentlich lesbar noch über den Editor schreibbar. F03–F05. |
| [ ] AP07 | **CSRF- und Anfragebegrenzung wirksam integrieren.** Zentrale Methoden-/Tokenprüfung, nebenläufigkeitssichere Limits für Login, Registrierung, Kontakt, Tickets und Domainabfrage. Konfigurationsschalter an die richtigen Speicherorte anbinden. | AP03, AP04 | M | Fehlende Tokens und verbotene Methoden werden abgewiesen; konfigurierte Limits greifen nachweislich. F06. |
| [ ] AP08 | **Provider-Transport und Geheimnisse absichern.** TLS-Prüfung, eigene CA, Timeouts, bereinigte Fehlermeldungen und Logausgaben. Produktionsschlüssel außerhalb öffentlich erreichbarer Pfade halten; bestehende verschlüsselte Daten nur mit geplanter Schlüsselübernahme migrieren. | AP03 | M | Zertifikatsfehler werden erkannt; erlaubte private CAs funktionieren; Passwörter/Tickets stehen nicht in Antworten oder Logs. F07. |
| [ ] AP09 | **Sitzungen und Kundenlogin vereinheitlichen.** Einheitliche Cookie-/Timeout-Regeln, Session-Regeneration, gehashte und rotierte Remember-Tokens, Widerruf bei Passwortwechsel/Logout und Kontosperre. | AP04, AP11 | M | Ablauf, Wiederanmeldung, Passwortwechsel und Sperrung sind mit mehreren Sitzungen getestet; keine widersprüchlichen Rollen/Laufzeiten. F08, F09. |

**Abschluss Phase A:** Alle P0-Befunde sind behoben oder die betroffene Funktion ist wirksam gesperrt. Die betroffenen P1-Schutzmechanismen sind getestet. Eine erneute Freigabe erfolgt für den tatsächlich geprüften Funktionsumfang.

## Phase B: Installation und Kernfunktionen stabilisieren

| Status / ID | Paket und konkretes Ergebnis | Abhängigkeit | Größe | Abnahme / Befunde |
| --- | --- | --- | --- | --- |
| [ ] AP10 | **Registrierung und Mailfluss reparieren.** Gemeinsamer Bootstrap, ein Verifikationstoken-Modell, Versandfehler und erneuter Versand, Aktivierung ohne automatische Gesamtanlage in allen Providern. Keine Benutzerpasswörter per Mail. | AP07, AP09, AP11 | M | Registrierung → Link → Aktivierung → Login funktioniert; SMTP-Ausfall, abgelaufener/verwendeter Link und Wiederholung sind getestet. F10. |
| [ ] AP11 | **Ein verbindliches Datenbankschema und sichere Migrationen.** Alle Tabellen-/Spaltenabweichungen zwischen Installer, Vollschema, Datenbankmodus und Anwendung erfassen. Migrationen nummerieren, Neuinstallation und Upgrade zusammenführen; Daten und individuelle Vorlagen erhalten. | AP01, AP03 | L | Leere Installation und Kopie einer 3.2.7-Datenbank erreichen denselben Zielstand ohne Beispieldaten oder verlorene Nutzerdaten. F10–F12, F23. |
| [ ] AP12 | **Datenbankunterstützung ehrlich und testbar machen.** MySQL/MariaDB produktiv prüfen, SQLite-Kernabläufe reparieren; PostgreSQL-Abfragen über Adapter testen. MongoDB-Dummys entfernen und unvollständige Unterstützung ausdrücklich sperren. | AP11 | M–L | Pro freigegebener Datenbank bestehen dieselben Kernprüfungen. Nicht unterstützte Konfigurationen brechen klar statt scheinbar erfolgreich ab. F12, F13. |
| [ ] AP13 | **Benutzerverwaltung und Service-Verträge reparieren.** Zehn fehlende Methoden und Provider-Signaturen abgleichen, Fehler-/Ergebnisverträge vereinheitlichen; Passwortänderungen und Teilfehler kontrolliert behandeln. | AP03, AP11 | M | Jede angebotene Nutzeraktion läuft mit simuliertem Provider und definierten Fehlerfällen; keine nicht vorhandenen Methoden. F14. |
| [ ] AP14 | **Gemeinsame Validierung, Sprache und Codierung korrigieren.** Numerische Grenzen und Textlängen trennen, leere Pflichtwerte abweisen, Sprachliste normalisieren, BOM und XML-Kurz-Tag-Konflikt entfernen. | AP03 | S–M | Die dokumentierten Quote-/required-/Sprach-/BOM-Regressionen bestehen. F17, F18, F21. |
| [ ] AP15 | **Updater als kontrollierten Ablauf neu ordnen.** Update-ID mit Typ, Version, Commit und Manifest serverseitig halten; nur die richtige Versionsstelle ändern, Geheimnisse erhalten, Paketprüfung und Migrations-/Rückrollschritte implementieren. | AP04, AP06, AP11 | L | Voll-/Kernupdate über mehrere Requests, Abbruch und Wiederholung sind geprüft; Pluginversionen und Konfiguration bleiben korrekt. Paketintegration wird in AP28 abschließend getestet. F04, F15, F16. |
| [ ] AP16 | **Rendering und Browsercode bereinigen.** Einheitliches getContent-Ergebnis, reine statische JS-Dateien und kontrollierte JSON-Initialisierung; parallele Lazy-Loading-/Assetpfade bereinigen. Danach gezielte Desktop-/Mobil-, Tastatur- und Fehlermeldungsprüfung. | AP04, AP14 | M | Proxmox-Lazy-Loading liefert gültiges JSON; alle ausgelieferten JS-Dateien sind parsebar; Kernansichten funktionieren in der Testinstallation. F19, F21, F22. |
| [ ] AP17 | **Dokumentation und unterstützte Plattformen berichtigen.** Installationspfade, Lizenz-/Versionsangaben, Runtime/Extensions und Funktionsumfang aus tatsächlich geprüften Kombinationen ableiten; Upgrade-Anleitung verfassen. | AP11/AP12 für den jeweiligen Umfang; neue API-Dokumentation zusätzlich AP18 | S–M | Ein unabhängiger Installationsdurchlauf nach Dokumentation gelingt. PHP-Mindestversion und Breaking Changes sind ausdrücklich dokumentiert. F24. |

**Abschluss Phase B:** Neuinstallation, Upgrade, Login/Registrierung, Benutzerverwaltung und die freigegebenen Module bestehen die vereinbarte Kernabnahme. Ein behobener Befund wird mit Test-/Commitverweis geschlossen, nicht nur aufgrund geänderter Dateien.

## Phase C: Modulare Basis und vorhandenen Shop übernehmen

| Status / ID | Paket und konkretes Ergebnis | Abhängigkeit | Größe | Abnahme |
| --- | --- | --- | --- | --- |
| [ ] AP18 | **Kern und Modulverträge einführen.** Bootstrap, Autoloading, Konfiguration, Datenbankzugriff, Benutzerkontext, Providerverträge und ModuleRegistry getrennt organisieren. Alte ServiceManager-Aufrufe zunächst über eine Kompatibilitätsschicht weiterführen. | AP04, AP09, AP12, AP13 | L | Bestehende freigegebene Funktionen bestehen ihre Regressionen; ein deaktiviertes Modul lädt keine Routen/Jobs; Manifest-Abhängigkeiten werden geprüft. F25. |
| [ ] AP19 | **Provider-Anbindungen zusammenführen.** Bewährte Shopadapter für OVH, ISPConfig und Proxmox mit dem Framework-Vertrag abgleichen; OGP separat prüfen. Kunden-/Providerbindung, Quoten, Node/VM-Typ, Aufgabenstatus und unklare Schreibresultate vereinheitlichen. Netzwerkfehler F20 beheben. | AP05, AP08, AP18 | L | Gleiche Authentifizierung und Ressourcenprüfung in Verwaltung und Shop; Timeouts erzeugen keine doppelten externen Ressourcen. |
| [ ] AP20 | **Öffentliche APIs, Support und Betriebsprotokolle begrenzen.** Öffentlichen Status definieren; Tickets, Kontakt und Exporte mit klaren Sichtbarkeiten/Datengrenzen versehen. Logs mit Akteur, Request-/Job-ID und angemessener Aufbewahrung. | AP05, AP07, AP11 | M | Interne Daten/Notizen bleiben geschützt; Spambegrenzung greift; ein fehlgeschlagener Auftrag ist ohne Geheimnisse nachvollziehbar. |
| [ ] AP21 | **„Online Shop“ als Erweiterung inventarisieren und abtrennen.** Source-/Schema-/Job-/Konfigurationsabgleich erstellen; generische Shopfunktionen, Branding und Betreiberwerte separat abbilden. Noch keine Übernahme produktiver privater Dateien. | AP18, AP19 | M–L | Für jeden übernommenen Baustein existiert eine Quelle, Zielzuständigkeit und Regression; keine zweite unabhängige Kunden-/Provideridentität. Siehe Shopkonzept. |
| [ ] AP22 | **Katalog, Pakete, Konfigurator und Checkout-Modul.** Bestehende Produkt-/Zusatzmodullogik übernehmen; Centbeträge, Währung, Laufzeiten, Limits, Produkt-/Vertrags-Snapshots und serverseitige Preisberechnung festlegen. | AP21 | L | Manipulierte Preise/Mengen werden abgewiesen; ohne Zahlungsanbieter bleibt ein funktionsfähiger Angebots-/Anfragebetrieb möglich. |
| [ ] AP23 | **Zahlungen und wiederholbare Ereignisverarbeitung.** PayPal-Anbindung und Abgleichlogik übernehmen; eindeutige Zahlungs-/Event-IDs, Betrags-/Währungsprüfung, Signaturprüfung und Sandbox-Trennung festlegen. | AP22 | L | Gefälschte/abweichende Zahlungen bleiben unwirksam; Wiederholungen und vertauschte Ereignisreihenfolge erzeugen keine Doppelbuchung/-einrichtung. |
| [ ] AP24 | **Bereitstellung und Ressourcenbindung als Worker-Modul.** Bestehende Job-, Phasen-, Sperr- und Prüflogik übernehmen; ISPConfig/Proxmox/OVH/Manuell getrennt anbinden. Provider-Auftrag und lokaler Status bleiben nachvollziehbar. | AP19, AP22, AP23 | L | Doppelte Jobs, Worker-Abbruch, Provider-Timeout und bereits vorhandene Ressource werden sicher behandelt. Sandbox kann keine Live-Ressource anlegen. |
| [ ] AP25 | **Rechnungen, Verträge und optionale Zusatzmodule.** Rechnung/PDF, Mail, Benachrichtigungen, Angebote, Kündigungen, Gutscheine und Erweiterungsabos nacheinander extrahieren. Unveränderbare Bestell-/Belegbezüge und bestehende Nummernkreise erhalten. | AP22, AP23 | L; nach Funktionsgruppen teilen | Ein Auftrag lässt sich von Angebot/Zahlung bis Beleg/Leistung/Vertragsende verfolgen; Deaktivierung optionaler Module beschädigt keine gebuchten Leistungen. |
| [ ] AP26 | **Shop- und Verwaltungsoberflächen modular anschließen.** Kundenportal, Adminoberfläche, Paketverwaltung und Modulverwaltung an denselben Kern hängen. Farben, Logos, Texte, URLs und Direktzugänge als austauschbare Betreiberkonfiguration. | AP16, AP21, AP22 | M–L | Shop kann einzeln installiert/aktiviert werden; Administration bleibt ohne Shop verwendbar; Zugang und bestehende Kundenprodukte bleiben zugeordnet. |
| [ ] AP27 | **Bestehenden „Online Shop“ kontrolliert migrieren.** Zuerst Datenkopie und Trockenlauf, dann Abgleich von Kunden, Aufträgen, Zahlungen, Rechnungen, Verträgen, Ressourcen und verschlüsselten Werten. Alte URLs weiterleiten und Worker kontrolliert umschalten. | AP24, AP25, AP26 | L | Mengen, Summen, IDs, Anbieterbindungen und Schlüsselprüfung stimmen vor/nach Migration überein; produktiver Import hat einen geprüften Rückweg. |

**Abschluss Phase C:** Ein Shop kann als getrenntes Erweiterungspaket installiert werden. Derselbe Kern verwaltet Berechtigungen, Kunden und Provider. Das Bestandsprojekt ist erst nach erfolgreicher Probe migrierbar; ein vollständiges Kopieren seines Webroots ist kein Migrationsverfahren.

## Phase D: Version, GitHub-Release und Upload auf den eigenen Server

| Status / ID | Paket und konkretes Ergebnis | Abhängigkeit | Größe | Abnahme |
| --- | --- | --- | --- | --- |
| [ ] AP28 | **Reproduzierbare Releasepakete bauen.** Explizite Dateilisten für Kern, Verwaltung und optional Shop; Beispiele ohne echte Werte, benötigte Migrationen/Abhängigkeiten, Manifest, Checksummen und attestierte/signierte Herkunft. Pakete neu installieren und mit dem Updater prüfen, sofern dieser freigegeben wird. | Abgenommener Releaseumfang und AP17; neuer Updater zusätzlich AP15; modulare Hauptversion zusätzlich AP18–AP20; Shop-Paket zusätzlich AP27 | M | CI erzeugt versionierte Pakete aus genau einem Commit. Keine privaten Konfigurationen, Datenbanken, Kundendokumente, Debugskripte, Backups oder Schlüssel enthalten. |
| [ ] AP29 | **Version und GitHub-Release vorbereiten.** Version gezielt setzen, Changelog/Upgradehinweise schreiben, überprüften Commit taggen und vollständigen Releaseentwurf mit allen Paketen anlegen. Veröffentlichung nach bestandener Staging-Abnahme aus AP30. | AP28; alle für diese Version zugesagten Pakete geschlossen | S–M | Tag, Manifest, Versionsanzeige und Release Notes stimmen überein. Öffentliche Veröffentlichung enthält nur geprüfte und vollständige Artefakte. |
| [ ] AP30 | **Auf eigenem Server testen und veröffentlichen.** Zielpfad/Domain, Webserver, PHP/Extensions, DB, Uploadweg und Cron erfassen. Backup inklusive Schlüssel und DB erstellen und Wiederherstellung prüfen. Staging aus Releasepaket installieren, Migration/Funktionen prüfen, danach GitHub veröffentlichen und dasselbe Paket produktiv aktivieren. | AP29 als Entwurf | M | Nachweisbarer Backup-/Restore-Test, erfolgreiche Stagingabnahme, geprüfter Upload, passende Worker-/Schema-/Versionsstände und gesunder Produktionsstart. Bei Fehlern dokumentierter Rollback. |

## Vorgeschlagene Versionsschritte

Die Namen sind Planungsziele. Die tatsächliche Versionsnummer wird unmittelbar vor dem Release mit GitHub abgeglichen.

| Version | Inhalt | Voraussetzung |
| --- | --- | --- |
| **3.2.8** | Begrenzter Sicherheits-/Stabilitätshotfix für den bisherigen Zweig: P0 schließen, sicherheitsrelevante Kernfehler korrigieren, gefährliche Funktionen nötigenfalls deaktivieren. | AP01–AP09 sowie die jeweils erforderlichen Regressionen/Migrationen; keine versteckte inkompatible PHP-Anhebung. AP28–AP30 für diesen Umfang. |
| **3.3.x, falls sinnvoll** | Neue Verträge/Bootstrap/Adapter additiv anbieten; alte Aufrufe weiter unterstützen und Übergang dokumentieren. | Stabile Phase B und getestete Kompatibilität. Kein Zwang zu einem zusätzlichen Zwischenrelease. |
| **4.0.0** | Neue modulare Hauptbasis, konsolidierte Kern-/Datenbankwege, bewusst dokumentierte API-/Runtime-Änderungen; Vorschlag PHP >=8.4, Tests auch mit 8.5. | Phasen A/B, Kern und Provider aus Phase C, eigene Upgradeabnahme. |
| **Shop-Erweiterung 1.0.0** | Generisches Shop-Paket aus den bestehenden Funktionen mit eigener Version und erklärter Kern-Kompatibilität. | AP21–AP27, eigene Paket-/Daten-/Zahlungs-/Bereitstellungsabnahme. |

Ein Shop-Zuwachs muss keinen dringenden Sicherheitshotfix verzögern. Kernversion, Shopversion, einzelne Modulversionen und Datenbankschema-Version bleiben getrennt.

**Kurzer Weg für den Hotfix:** AP01–AP08 und die dafür nötigen Sitzungs-/Schema-/Funktionskorrekturen abnehmen; unvollständig reparierte gefährliche Funktionen wirksam sperren. Anschließend nur die zu diesem Umfang gehörende Dokumentation aus AP17 und AP28–AP30 durchführen. AP18–AP27 sind dafür keine Voraussetzung. Ein noch gesperrter Web-Updater wird bei diesem ersten Release durch einen kontrollierten Serverupload ersetzt.

## Release- und Rückrollkriterien

- Alle P0 und die für den veröffentlichten Umfang relevanten P1 sind geschlossen; verbleibende Einschränkungen werden mit tatsächlich gesperrter Funktion dokumentiert.
- Frische Installation und Upgrade von einer realistischen 3.2.7-Datenkopie sind geprüft. Eine erfolgte Prüfung unter PHP 8.3 ersetzt keine Abnahme unter der neuen PHP-Zielversion.
- Tests enthalten zwei getrennte Kunden, mindestens einen normalen Backend-Nutzer und einen Administrator sowie negative Berechtigungsfälle.
- Live- und Sandbox-Konfiguration sind eindeutig getrennt; keine echten Bestellungen oder Ressourcenänderungen zum bloßen Funktionstest.
- Datenbank, private Dateien und Verschlüsselungsschlüssel werden zusammen gesichert. Ein Dateirücksprung allein ist kein Datenbank-Rollback.
- Vor nicht rückwärtskompatiblen Migrationen wird festgelegt, ob Wartung, Restore oder eine kompatible Übergangsmigration erforderlich ist. Ein Backupzeitpunkt und eine Restoreentscheidung berücksichtigen zwischenzeitliche Kundenzahlungen.
- Produktivuploads verwenden ein geprüftes verschlüsseltes Verfahren, vorzugsweise SSH/SFTP. Betreiberwerte und Geheimnisse werden serverseitig gehalten und bleiben bei Updates erhalten.
- Jobs/Cron werden genau einmal auf dem aktiven Stand betrieben; alte und neue Worker bearbeiten keine identischen Live-Aufträge parallel.
- Release-/Serverprotokoll dokumentiert Commit, Paket-Hash, Schema, PHP, Sicherung, Tests und Wiederanlauf. Anschließend Status, Login, Bestandsprodukte und Worker prüfen.

GitHub unterstützt unveränderbare Releases und zugehörige Release-Attestierungen. Sinnvoller Ablauf: Entwurf erstellen, alle Pakete anhängen, erst danach veröffentlichen. Quelle: [GitHub – Immutable releases](https://docs.github.com/en/code-security/concepts/supply-chain-security/immutable-releases), geprüft am 24.10.2026. Diese Funktion wird für das Zielrepository erst bei Umsetzung eingerichtet; sie wurde in dieser Prüfung nicht verändert.

## Empfohlener nächster Auftrag

**AP01–AP04 als erster Umsetzungsblock:** Arbeitsbasis sichern, gefährliche Web-Einstiege sperren, die entsprechenden isolierten Regressionen einrichten und die zentrale Rollenprüfung reparieren. Der erste Block ist abgeschlossen, wenn normale Konten die geprüften Adminaktionen nicht mehr erreichen und keine Debug-/Updateaktion ungeschützt ausführbar ist.

Für die spätere Bereitstellung fehlen noch die konkreten Zielangaben des Framework-Servers. Diese werden in AP30 erhoben. Zugangsdaten des Projekts „Online Shop“ werden nicht automatisch als Zugang zum neuen Ziel angenommen.
