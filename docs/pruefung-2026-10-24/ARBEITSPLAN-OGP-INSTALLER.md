# Arbeitsplan: modularer Shop, OpenGamePanel und geführte Installation

Stand: 24.10.2026. Ausgangspunkt: Framework 3.3.0 und AP18–AP27. Dieser Plan ergänzt den bisherigen Arbeitsplan; AP28–AP30 behalten ihre Bedeutung für Paketbau, Veröffentlichung und Serverabnahme. Die neuen Pakete beginnen bei **AP31**.

## Ziel und tatsächlicher Ausgangsstand

Ein Betreiber soll den Shop über einen Assistenten installieren, seine vorhandenen Systeme erkennen und prüfen lassen und passende Verkaufs-/Verwaltungsfunktionen gezielt aktivieren können. Webhosting über ISPConfig, Gameserver über OpenGamePanel, virtuelle Server über Proxmox und OVH-Angebote müssen dieselben Kunden, Bestellungen, Zahlungen, Rechte und Betriebsabläufe verwenden.

Die elf bisherigen Shopmodule kapseln die übernommene Anwendung über Routen, Aktionen und Workerbeiträge. Wesentliche Daten-/Anwendungsfunktionen liegen noch gemeinsam in `shop.storage/legacy`. Das ist eine verwendbare Kompatibilitätsbasis; für unabhängig austauschbare Provider- und Produktmodule fehlen zusätzliche Verträge und Extraktionen.

Die konkrete OGP-Lücke ist im Code nachvollziehbar:

- `framework.php` enthält `OGPGet`, `OGPPost` und OGP-Methoden des `ServiceManager`, einschließlich `ogp_api.php`, Benutzer-, Home-, Agent- und Verwaltungsoperationen.
- `ProviderRegistry` verwendet für OGP ausdrücklich den Legacyadapter. `ProviderAccounts` bindet bisher ISPConfig, Proxmox und OVH an die gemeinsamen Shopkonten an.
- Im Shop fehlen ein eigenes OGP-Providermodul, ein Gameserver-Produktmodul und ein entsprechend gebundener Bereitstellungsablauf.
- `bin/install.php` benötigt bisher eine fertige Konfiguration; der historische Webinstaller bleibt gesperrt. Ein neuer Erstinstallationsassistent ergänzt diesen Weg mit einer eigenen, begrenzten Installationsfreigabe.

**Die erfolgreiche OGP-Verbindungsprüfung ist kein Nachweis einer fertigen Gameserver-Bereitstellung.** Der neue Installer kann den vorhandenen Frameworkzugang einrichten. Die Verkaufskette folgt in den dafür vorgesehenen Arbeitspaketen.

## Konkrete Defizite und vorgezogene Korrektur

| Befund | Bedeutung | Bearbeitung |
| --- | --- | --- |
| OGP-Tokenprüfung und Tokenanlage verwendeten Zugangsdaten im URL-Pfad. | Zugangsdaten können in Proxy-/Webserver-URL-Protokollen landen. | In diesem Auftrag auf POST-Body umgestellt und mit dem vorhandenen Adapter geprüft. Eine fehlgeschlagene Anmeldung wird nicht mehr als vermeintlicher Token übernommen. |
| OGP fehlt in der gemeinsamen Shopkonten-/Bereitstellungsanbindung. | Legacy-Verwaltung ist vorhanden, aber noch keine vollständige Verkaufskette. | AP35–AP39. |
| ISPConfig-Verbindungstest verlangt bislang eine betrieberspezifische DKIM-Funktion. | Ein Standardpanel kann trotz gültigem Zugang nicht pauschal als ungeeignet bewertet werden. | Der neue Installer testet Grundzugang ohne diese Sonderfunktion; die vollständige Fähigkeitsabtrennung folgt in AP40. |
| Fachlogik und Speichermigration sind noch eng im gemeinsamen Legacybereich verbunden. | Austauschbare Providermodule brauchen zusätzliche Verträge und Extraktion. | AP31 und AP42. |

## Verbindliche Architektur

| Baustein | Zuständigkeit | Grenze |
| --- | --- | --- |
| Kern | Identität, Berechtigungen, Konfiguration, Datenbank, Modulzustand, Audit, Queue und Outbox | Keine fachliche Abhängigkeit von einem einzelnen Panel |
| Commerce | Katalog, Preissnapshots, Anfrage/Angebot/Bestellung, Zahlung, Beleg und Vertrag | Kein direkter HTTP-Aufruf eines Providers aus einem Checkoutformular |
| Providermodule | Beispielsweise `provider.ogp`, `provider.ispconfig`, `provider.proxmox`, `provider.ovh`; Namen sind Planungsnamen | Deklarierte Fähigkeiten, Konten, Versions-/Berechtigungsprüfung und Adapter |
| Produktmodule | Beispielsweise `product.gameserver`, `product.webhosting`, `product.vps`; Namen sind Planungsnamen | Produktoptionen, Ausstattungs-/Kontingentregeln und gewünschter Leistungszustand |
| Worker | Externe Bereitstellung, Abgleich, Sperrung und kontrollierte Kündigung | Eine dokumentierte externe Schreibabsicht wird nach unklarer Antwort nicht blind wiederholt |
| Installationsassistent | Voraussetzungen, private Konfiguration, Datenbank, Admin, Modulauswahl und gezielte Erkennung | Keine stillschweigende Rootinstallation fremder Panels und kein Zurücksetzen eines vorhandenen Shops |

Ein Hostingpaket kann einen vorhandenen ISPConfig-Anschluss verwenden. Ein Gameserverprodukt benötigt das Gameservermodul, einen geeigneten OGP-Anschluss, freigegebene Agents/Spieldefinitionen und einen passenden Bereitstellungsworker. Ein aktiviertes Zahlungsmodul allein stellt keine dieser Voraussetzungen her.

Bei OGP sind Remote-Agents und Gameserver-Homes getrennte Ressourcen: `server/*` verwaltet Remote-Server/Agents, während `user_games/*` die Spiele-Homes betrifft. Ein Checkout darf deshalb keinen allgemeinen Agent-Anlege- oder Agent-Neustartaufruf als Gameserverbereitstellung verwenden.

Neue Provider werden über deklarierte Modulbeiträge registriert. Dazu sind die bisher feste Providerliste und das Manifestmodell in AP31 zu erweitern. Produktoptionen werden typisiert validiert; ein dynamisches Freitextformular mit direktem Durchreichen beliebiger API-Parameter erfüllt diesen Vertrag nicht.

## Erkennung und Installation

„Installiert“ ist kein einzelner Ja/Nein-Test. Ein Panel kann lokal vorhanden, auf einem anderen Server betrieben, durch `open_basedir` unsichtbar oder vorübergehend unerreichbar sein.

| Zustand | Bedeutung und Folge |
| --- | --- |
| `not_configured` | Betreiber hat keinen Anschluss ausgewählt; der Shop bleibt unabhängig installierbar. |
| `local_found` | Bekannte Installationsdatei gefunden. Dies bestätigt weder einen laufenden Dienst noch API-Rechte. |
| `unknown` | Kein zugänglicher lokaler Hinweis. Kein Beweis für eine fehlende Installation. |
| `endpoint_missing` / `unrecognized` | Angegebene API-Adresse fehlt oder liefert keinen unterstützten Vertrag; Adresse und Panelversion prüfen. |
| `credentials_required` / `authentication_failed` | API bzw. Adresse muss mit dem richtigen Remote-Zugang geprüft werden. |
| `access_denied` / `permission_denied` | IP-Freigabe oder Berechtigungen fehlen. Ein gültiger Login allein reicht nicht. |
| `unreachable` | Netzwerk, Zertifikat oder Ziel derzeit nicht überprüfbar. Keine automatische „nicht installiert“-Aussage. |
| `ready` | Die dokumentierten Verbindungstests sind erfolgreich. Weitere fachliche Fähigkeiten bleiben einzeln auszuweisen. |

Die implementierte Erkennung liest bekannte lokale Marker, ohne fremde Konfigurationen auszuführen. Zusätzlich können eigene lokale Panel-Webroots angegeben werden. Externe Systeme werden ausschließlich über eine ausdrücklich eingegebene HTTPS-Basisadresse geprüft. Es gibt keine Suche durch fremde Netze oder automatische Portscans.

ISPConfig wird über die JSON-Remote-API mit Anmeldung, einem Server-Lesetest und Logout geprüft. Für OGP werden ein vorhandener API-Token und die Serverliste geprüft. Zugangsdaten gehören in den Requestbody; automatische Tokenrotation über `token/create` findet bei der Erkennung nicht statt. Ein OGP-Panel kann vorhanden sein, während keine Agents eingerichtet oder erreichbar sind. Die Agent-/Spiele-/Kontingentprüfung folgt deshalb separat.

Der Browserablauf umfasst sechs Schritte: Installationsfreigabe, System/Datenbank, Betreiber/Admin/Mail, Panelprüfung, Modulauswahl, abschließende Einrichtung. MySQL/MariaDB und SQLite sind die Shopziele. Der vorhandene PostgreSQL-Kernsupport bedeutet keine Freigabe der übernommenen Shop-Persistenz für PostgreSQL.

## Arbeitspakete

`[x]` = im hier dokumentierten Umfang lokal umgesetzt und geprüft; `[ ]` = offen. **AP32–AP34 sind in dieser Bearbeitung umgesetzt.** Das Bestands-Testsystem erhält dadurch keine neue Installation. Statusänderungen erfolgen anhand der jeweils genannten Abnahme. Die lokale Erstinstaller-Umsetzung und ihre tatsächlich ausgeführten Prüfungen werden gesondert im [Installer-Nachweis](INSTALLATION-SHOP.md) festgehalten.

| Status / ID | Ergebnis und Umfang | Abhängigkeit | Abnahme |
| --- | --- | --- | --- |
| [ ] **AP31** | **Modulverträge für Provider und Produktarten erweitern.** Fähigkeiten, Konfigurationsfelder, Verbindungstests, Ressourcentypen, UI- und Workerbeiträge deklarieren; feste Providerlisten ersetzen. Preisberechnung bleibt im Commerce-Kern. | AP18–AP27 | Ein zusätzliches vertrauenswürdiges Providermodul wird ohne Änderung an `framework.php` registriert. Fehlende Version/Fähigkeit/Abhängigkeit sperrt genau den betroffenen Produktweg. |
| [x] **AP32** | **Geführte Shop-Erstinstallation liefern.** Browserassistent, CLI-Einstieg, neue private Konfiguration, MySQL/MariaDB oder SQLite, eigenes Admin-Konto, Modulauswahl und Sandboxstart. FTP- und SSH-Betrieb berücksichtigen. | Bestehender Kern; unabhängig von fertigem Gameserververkauf | Frischer Paketstand lässt sich ohne vorbereitete `Config`-Klasse installieren. Administrator kann sich anmelden; Module entsprechen der Auswahl; keine Standardzugänge und keine unbestätigte Cron-/Mail-Erfolgsmeldung. |
| [x] **AP33** | **ISPConfig-/OGP-Erkennung und Diagnose liefern.** Lokale Marker, eigene Panelpfade, explizite externe Endpunkte, überprüfte Zertifikate, eigene CA, Authentifizierung und definierte Leserechte. | AP32 | ISPConfig allein, OGP allein, beide, keines, falscher Token, fehlende IP-Freigabe, abgeschaltete API, Timeout und falsches Zertifikat werden unterschieden. Fehlgeschlagene Anschlüsse bleiben deaktiviert. |
| [x] **AP34** | **Installationsschutz und Wiederaufnahme liefern.** Nachweis über Startschlüsseldatei, CSRF, begrenzte Gültigkeit, private Sitzung, gemeinsame Installationssperre, Installationsjournal, exklusive Konfigurationsaktivierung und dauerhafte Schließung nach Abschluss. | AP32 | Fehlende Freigabe, parallele Installation, fremde Datenbank und Wiedereinrichtung werden abgewehrt. Unterbrochene Modulinstallation lässt sich mit denselben Angaben ohne zweite Adminanlage fortsetzen. |
| [ ] **AP35** | **OGP-Provider aus dem Legacyweg lösen.** Gemeinsames verschlüsseltes Providerkonto und stabile Anschlussidentität, typisierte Antworten/Fehler, explizite Leseschreibfähigkeiten, POST-Authentifizierung und Versionsmatrix. Vorhandene Adapter über eine Kompatibilitätsschicht weiterführen. | AP31, AP33 | Framework und Shop verwenden dasselbe geprüfte OGP-Konto. Tokenwechsel macht alte Bindungen prüfpflichtig; Token/Passwort erscheinen in keiner URL oder Fehlermeldung. |
| [ ] **AP36** | **Gameserverkatalog und Konfigurator bereitstellen.** Spiel-/Moddefinitionen aus freigegebenen OGP-Einträgen, Agents, Betriebssystem, Slots, CPU/RAM/Datenträger soweit tatsächlich durchsetzbar, Standort, Portkontingente und erlaubte Zusatzoptionen. | AP35, bestehender Katalog | Unbekanntes Spiel, gesperrter Agent, ungültiger Port/Slotwert und manipulierte Preise werden abgewiesen. Ein nicht bereites Angebot bleibt Anfragebetrieb. Bestellsnapshot enthält genaue Auswahl und Anbieterbindung. |
| [ ] **AP37** | **OGP-Bereitstellungsworker implementieren.** Benutzerzuordnung, Homeanlage, Spielinstallation, Agent-/Portreservierung, Fortschritt und eindeutige Auftragskennung. Vorhandene Nutzer/Homes dürfen nicht ungeprüft übernommen werden. | AP35, AP36, bestehende Zahlungs-/Queueverträge | Doppeltes Ereignis, Workerabbruch, Agent offline und Timeout nach Homeanlage erzeugen keine zweite Ressource. Unklare Antwort erfordert Abgleich. Sandbox legt keine Live-Gameserver an. |
| [ ] **AP38** | **Ressourcenbindung und Kundenverwaltung ergänzen.** Anschluss, Agent, Home, Spiel/Mod, IP/Port, Eigentümer und Auftrag verbinden. Start/Stopp/Neustart, Status und begrenzte RCON-/Dateifunktionen über die zentrale Rechteprüfung führen. | AP37 | Kunde A erreicht kein Home und keinen Port von Kunde B. Wiederverwendete IDs oder geänderte Anbieterbindung erfordern erneute Prüfung. Sperren greifen vor jedem Provideraufruf. |
| [ ] **AP39** | **Kommerziellen Lebenszyklus für Gameserver abbilden.** Zahlung → Bereitstellung → Beleg → Leistung; Verlängerung, Sperre, Entsperrung, Kündigungsfrist, Aufbewahrung und endgültige Freigabe von Ports/Datenträgern definieren. | AP37, AP38, bestehende Verträge/Rechnungen | Doppelte/verspätete Zahlung und parallele Kündigung führen zu einem konsistenten Zustand. Kontolöschung entfernt keine fremden oder weiteren Leistungen. Löschungen erfolgen erst im freigegebenen Vertragsablauf. |
| [ ] **AP40** | **ISPConfig als echtes Providermodul vervollständigen.** Serverauswahl, getrennte Web-/Mail-/Datenbankfähigkeiten, Kunden-/Resellerzuordnung, Vorlagen, Quoten und wirklich vorhandene Remote-Funktionen prüfen. Bisherige betrieberspezifische DKIM-Funktion als optionale Fähigkeit ausweisen. | AP31, AP33 | Ein Standard-ISPConfig darf nicht pauschal an einer fehlenden Sonderfunktion scheitern. Jedes angebotene Hostingprodukt verlangt genau seine geprüften Fähigkeiten und Serverzuordnungen. |
| [ ] **AP41** | **Proxmox und OVH nach demselben Vertrag anschließen.** Vorhandene Adapter und Betreiberkonfiguration übernehmen, Installationsfelder/Diagnose und Produktfähigkeiten ergänzen. Provider dürfen entfernt werden, wenn keine aktive Abhängigkeit mehr besteht. | AP31, AP40 | Alle vier Systeme werden in derselben Anschlussverwaltung geführt; deaktivierte Anschlüsse erzeugen keine Provider-IO. Vorhandene VMs, Domains und Kundenbindungen behalten ihre Eigentümer. |
| [ ] **AP42** | **Produktfunktionen aus dem gemeinsamen Legacybereich extrahieren.** Gameserver-, Webhosting- und VPS-Funktionen mit eigenem Dienst-/Schema-/UI-Vertrag verschieben. Dateikopie und ein ausgeblendeter Menüpunkt allein reichen nicht als Modultrennung. | AP35–AP41 | Webhosting funktioniert ohne OGP-Dateien; Gameserver ohne ISPConfig-Konfiguration; gemeinsame Rechnungen/Verträge bleiben verwendbar. Keine zweite Kunden- oder Bestellidentität. |
| [ ] **AP43** | **Einrichtungsstatus, Nachkonfiguration und Upgradeassistent ergänzen.** SMTP-Test an eigene Adresse, Scheduler-/Workerheartbeat, Providerfähigkeiten, Vertrags-/Betreiberangaben und Produktbereitschaft sichtbar machen. Für vorhandene Installationen getrennten angemeldeten Upgrade-/Recoveryweg liefern. | AP32–AP42 | Betreiber sieht konkrete offene Einrichtungsschritte. Der Erstinstaller setzt keine vorhandenen Konten zurück. Nachinstallierte Provider/Module benötigen keinen kompletten Neuaufbau. |
| [ ] **AP44** | **Vollständige Integrationsabnahme auf Staging durchführen.** Installer-/Provider-Matrix, zwei Kunden, Fehler-/Wiederholungsfälle, Originaldatenvergleich sowie Rückweg auf Datenkopien; anschließend eine ausdrücklich freigegebene Test-Gameserverleistung gegen ein echtes OGP. | AP37–AP43 | Vollständiger Weg von Anfrage bzw. Sandboxzahlung bis gebundener Testleistung und kontrollierter Beendigung ist belegt. Mocktests werden von echter Agent-/Spielinstallation getrennt ausgewiesen. |
| [ ] **AP45** | **Modulpakete, Dokumentation und Release vorbereiten.** Getrennte Kern-/Shop-/Providerpakete, Vertrauens-/Versionsprüfung, Upgradehinweise, Changelog und Lizenzbestand. Gemeinsam verwendete Datenschemata in Migrationsreihenfolge halten. | AP42, AP44 | Installationspakete enthalten den Assistenten, alle benötigten Abhängigkeiten und keine Freigabeschlüssel/privaten Dateien. Eine Installation aus dem tatsächlichen Paket besteht erneut. |
| [ ] **AP46** | **Geprüften Stand auf dem eigenen Testsystem übernehmen und veröffentlichen.** Eigene Domain/DB/Schlüssel sichern, Upgrade statt Erstinstallation, gezielte Modulaktivierung, Worker und Erreichbarkeit prüfen; GitHub-Release nach eigener Beauftragung. | AP44, AP45 | Die bestehende 3.3.0-Testinstallation und ihre Konten bleiben erhalten. Release, Dateistand, Datenbank, Modulzustand und Prüfprotokoll stimmen überein. |

## Reihenfolge für die stückweise Umsetzung

1. **Erstinstallation und Diagnose:** AP32–AP34. Unabhängig von einem Gameserververkauf verwendbar.
2. **Erweiterungsvertrag und OGP-Verkauf:** AP31, AP35–AP39. Hier entsteht der tatsächliche Gameserver-Shopweg.
3. **Gleicher Modulvertrag für alle Systeme:** AP40–AP43. Gemeinsamen Legacybereich gezielt reduzieren und Nachkonfiguration ergänzen.
4. **Abnahme und Auslieferung:** AP44–AP46. Testserver und öffentliche Veröffentlichung getrennt nachvollziehbar abnehmen.

Die Bestandsinstallation auf `remote.orga-consult.eu` wird nicht durch den neuen Erstinstaller ersetzt. Dessen lokale Neuinstallationstests verwenden neue temporäre Verzeichnisse und Datenbanken.

## Mindesttests für die Zielabnahme

- Shop ohne externes Panel, nur ISPConfig, nur OGP und beide Panels; jeweils lokal bzw. extern, soweit eine reale Testinstanz bereitsteht.
- Gültiges Zertifikat/eigene CA, fehlende API, verwehrte Rechte, gesperrte Shop-IP, falscher Token und begrenzte Zeitüberschreitung.
- Frische MySQL/MariaDB-/SQLite-Installation, unterbrochene Installation und gleicher Wiederanlauf, parallel gestartete Einrichtung, Fremddatenbank und bereits eingerichteter Shop.
- Unabhängige Provider-/Produkt-/Zahlungsmodulabschaltung unter Erhalt bestehender Belege und Leistungen.
- Falscher Kunde, andere Anbieterbindung, wiederverwendete Ressource, abgelaufene Sperre und unklarer externer Schreibzustand.
- Serverbereitschaft, Spielinstallation und Portfreigabe als eigenständige Ergebnisse; „Home wurde angelegt“ ist noch keine ausgelieferte Leistung.

## Primärquellen und überprüfte API-Basis

Die vorhandene OGP-Anbindung entspricht der im offiziellen Projekt dokumentierten `ogp_api.php`. Dort sind Tokenprüfung, Serverliste, Benutzer-/Homeoperationen und Spielsteuerung getrennt. Die Erkennung nutzt ausschließlich die notwendigen Prüfaufrufe; der spätere Worker muss die fachlichen Operationen einzeln abbilden. Quelle: [OpenGamePanel – ogp_api.php](https://github.com/OpenGamePanel/OGP-Website/blob/master/ogp_api.php), geprüft am 24.10.2026.

Die offizielle separate [OGP-WHMCS-Integration](https://github.com/OpenGamePanel/OGP-WHMCS) ist eine Referenz für einen externen Commerce-Anschluss. Ihre Existenz ersetzt nicht die Implementierung eines eigenen geprüften Shopmoduls.

Für ISPConfig werden die im vorhandenen Adapter genutzten JSON-Remote-Verträge übernommen und mit simulierten TLS-Endpunkten geprüft. Maßgeblich für die endgültige Versions-/Berechtigungsmatrix bleiben die API-Dokumentation und Remote-Benutzerrechte der tatsächlich eingesetzten ISPConfig-Version: [offizielles ISPConfig-Repository](https://git.ispconfig.org/ispconfig/ispconfig3). Der Git-Server war bei dieser Recherche nicht abrufbar; eine aktuelle Quellprüfung dieses Servers oder die Erprobung gegen eine echte ISPConfig-Instanz wird nicht behauptet.
