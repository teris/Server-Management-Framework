# Umsetzung und Nachweise AP18–AP27

Stand: 24.10.2026. Arbeitszweig `feature/ap18-ap27-modular-shop`, Teststand 3.3.0 auf dem eigenen Server abgenommen. Öffentliche GitHub-Veröffentlichung dieses Stands noch ausstehend.

| AP | Umsetzung | Nachweis |
|---|---|---|
| 18 | Autoloading, Container, Kern-/Modulverträge, Manifestprüfung, Migrationen und Legacykompatibilität | Modul-/Abhängigkeits-/Abschalttests, Kernregressionen |
| 19 | Gemeinsame Providerkonten, Legacyadapterbrücken, Kernberechtigungen für Shopressourcen, VM-Identität, korrigiertes Netzwerk | Eigentümer-/Kontowechsel-/Sperr-/Netzwerkprüfungen; OGP separat in den bestehenden Providervertragstests |
| 20 | Begrenzter öffentlicher Status, gemeinsame Supportidentität, geschützte interne Notizen/Exporte, Akteur/Request/Job und Auditaufbewahrung | HTTP- und Autorisierungstests; keine Providerinterna im öffentlichen Status |
| 21 | Bestehender Shop inventarisiert und in elf Fähigkeiten getrennt, Betreiber-/Privatdateien ausgeschlossen | `extensions/shop.storage/SOURCE-MANIFEST.json`, Betriebsdokumentation und Paketdateiliste |
| 22 | Bestehende Katalog-/Paket-/Modulmengen-/Anfrage-/Bestellsnapshotlogik übernommen | manipulierte Mengen, unbekannte Module, Centbeträge und Anfragebetrieb ohne Zahlungsanbieter geprüft |
| 23 | Bestehende PayPal-Verarbeitung mit Empfängerbindung, strikten Captureprüfungen und Kernschutz ergänzt | falscher Betrag/Währung/Empfänger, doppelte Capture-ID, Fremdbestellung und verspätete Zahlung geprüft |
| 24 | Bestehende Bereitstellungsphasen ergänzt; dauerhafte Kernjobs/Outbox, Fencing und Unsicherheitsjournal | parallele Claims, verlorene Sperre, Wiederholung und Sandbox-Schreibsperre geprüft |
| 25 | Vorhandene Rechnung/PDF/Verträge/Mail/Angebote/Gutscheine/Modulabos hinter Modulgrenzen | Belegnummer, PDF-Hash, unveränderte gebuchte Daten beim Abschalten und Importwiederholung geprüft |
| 26 | Shop, Kunden-/Adminoberfläche und Kern-Modulverwaltung mit gemeinsamer Sitzung und privatem Branding | Framework-Admin öffnet Shopverwaltung; Shop-Kunde öffnet Kernportal; deaktivierte Menüpunkte entfallen |
| 27 | Trockenlauf, echter Import in Datenkopien, Quell-/ID-/Betrags-/Schlüssel-/PDF-Prüfung und Rückweg | vorhandene lokale Shopkopie auf SQLite und MariaDB importiert; Wiederholung ohne Duplikate; tatsächliche Serversicherung wiederhergestellt |

## Übernommene Quelle

Das vorhandene lokale Projekt „Online Shop“ liefert 128 öffentliche Quell-/Schema-/View-/Bibliotheksdateien. Private Laufzeitkonfiguration, Datenbank, Kundendokumente und Originalbetreiberlogos sind nicht in Git-/Release-Inhalten enthalten. Die gemeinsame Speichermigration ist ausdrücklich eine Kompatibilitätsgrenze; es wurden keine unabhängigen Kundenkonten oder eine zweite Bestellanwendung daneben aufgebaut.

## Vorhandene Datenkopie

Die eingefrorene lokale Shopdatenbank wurde unverändert gelesen. Der geprüfte Import enthält 6 Kunden, 1 Backendkonto, 4 Tickets mit 6 Antworten, 9 Produkte, 6 Anfragen, 2 Angebote, 1 Bestellung, 1 Zahlung und 1 Rechnung. Bestell- und Zahlungssumme jeweils **1.071 Cent**. Sechs verschlüsselte Werte wurden mit geprüftem Kontext übertragen; eine referenzierte Rechnungs-PDF wurde anhand ihrer Prüfsumme übernommen. Backend-/Kunden-/Ticket-IDs sind über die Migrationstabelle nachvollziehbar. Ein zweiter Import derselben Kopie erzeugt keine zweite Buchung.

Die Kopie enthält keine zugeordneten Shop-Providerressourcen. Der Übergang bestehender produktiver Ressourcen wird daher über simulierte Eigentümer-, Konto-, VM-Identitäts- und Sperrfälle geprüft; eine Behauptung über einen echten Provider-Ressourcenimport wäre mit dieser Kopie nicht belegt. Der laufende ursprüngliche Shop wurde nicht umgeschaltet.

## Testserver und Sicherung

Ziel ist ausschließlich `https://remote.orga-consult.eu`. Der Benutzer hat für diesen Testupload ausdrücklich FTP auf Port 21 gewählt. Vor dem Upload wurden 615 Dateien mit Prüfsummen einschließlich der Originalkonfiguration und die Datenbank separat außerhalb von Git gesichert. Die Serversicherung umfasst 37 Basistabellen und 3 Ansichten. Alle Zeilen ließen sich in einer separaten MariaDB identisch rekonstruieren. Der Testserver verwendet PHP 8.4.27 und MariaDB 11.8.6.

Die tatsächliche Datenbank besitzt zusätzliche namensbasierte Modulstrukturen. Die Kompatibilitätsmigration ergänzt numerische Zuordnungen und erhält die ursprünglichen Spalten und Einträge. Bestehende Framework-Kunden, Tickets und Providerbestände werden bei der Testbereitstellung nicht durch die lokale Shopdatenkopie ersetzt.

Version **3.3.0 ist aktiviert**. Alle elf Shopmodule sind installiert und aktiv; 60 Routen sind registriert. Der neue Worker wurde per PHP-CLI erfolgreich ausgeführt und läuft jede Minute über den tatsächlich eingerichteten Cronjob. Sein Heartbeat wurde anschließend automatisch aktualisiert. Das System bleibt im Sandbox-/Anfragebetrieb mit gesperrten Liveoperationen.

Die Originalkonfiguration liegt jetzt im privaten Hostingverzeichnis außerhalb des Webroots. 19 Bestandsbereiche wurden über die ursprünglichen Spalten und Zeilenwerte abgeglichen; lediglich betriebliche Login-/Änderungszeitstempel sind vom Wertvergleich ausgenommen. Nach Entfernen der drei temporären Prüfzugänge sind wieder **3 Kunden, 2 Backendnutzer, 2 Tickets und 6 Antworten** vorhanden. Erhalten sind unter anderem 12 VMs, 17 IPs, 46 Websites, 23 Domains, 43 Mailkonten und 6 Datenbanken. Bestehende Providerkonfiguration und individuelle Modulschalter wurden übernommen.

Bei der Serverabnahme zusätzlich behoben: zu spät geladene private Konfiguration bei direkten Kernanmeldungen, eine fehlende gemeinsame Darstellungshilfe auf leeren Shopverwaltungsseiten und ein JavaScript-Syntaxfehler im bisherigen Kunden-Dashboard.

Private Sicherungen, Datenkopien, Zugangsdaten und Schlüssel liegen ausschließlich im lokalen Sicherungsbereich beziehungsweise im privaten Hostingverzeichnis. Die drei temporären Testkonten, ihre Prüfsitzungen, der Uploadhelfer und das gesamte temporäre Stagingverzeichnis sind entfernt. Anschließend liefern Startseite und Status HTTP 200; private Pfade bleiben gesperrt.

## Prüfergebnisse

- **19 lokale Testsuiten erfolgreich**, darunter 119 Autorisierungs-/Sitzungsprüfungen, 86 Modulprüfungen, 12 Importprüfungen, 13 Tests der Node-Agenten, 54 Shop-HTTP-Prüfungen und Paket-/Update-/TLS-/Nebenläufigkeitsprüfungen.
- Dieselben Kern-Datenbankfälle auf SQLite, MariaDB 10.11.14, MySQL 8.0.46 und PostgreSQL 16.15; der Shop zusätzlich auf SQLite und beiden MySQL-Ausprägungen geprüft.
- **87 HTTP-Prüfungen direkt auf dem Testserver**: öffentliche Seiten, geschützte Pfade, Administration, gemeinsame Anmeldung, Modulabschaltung, serverseitige Anfragepreise und zwei getrennte Supportkunden mit internen Notizen.
- **13 Browserprüfungen auf dem Testserver**, Desktop 1440 und Mobil 390 Pixel; Navigation, gemeinsame Anmeldung, Kern-/Shopverwaltung und Kundenportal ohne unbehandelte JavaScriptfehler oder fehlende geprüfte Assets.
- **23 zusätzliche lokale Browserprüfungen** einschließlich bestehender Benutzer-/Einstellungs-/Registrierungsabläufe und Kunden-Dashboard.
- SHA-256-Abgleich aller 501 Deploymentdateien einschließlich privater Konfiguration; serverseitige PDF-Erzeugung und private Sicherung geprüft. Die vollständigen lokalen PHP-Syntaxprüfungen laufen mit kurzen Tags an und aus.

Maschinenlesbare, bereinigte Nachweise: [Testergebnisse AP18–AP27](testergebnisse-ap18-ap27.json). Kein entfernter GitHub-Actions-Lauf wird für diesen noch nicht gepushten Zweig behauptet.

## Grenzen

- Keine echten PayPal-Zahlungen und keine kostenpflichtigen oder zerstörenden Provideraktionen ausgeführt.
- Der optionale Shop unterstützt derzeit SQLite und MySQL/MariaDB; PostgreSQL bleibt eine Kernplattform.
- Die bestehende Shopanwendung wurde unter einer gemeinsamen Speichermigration gekapselt; die fachlichen Fähigkeiten besitzen getrennte Beiträge und Abhängigkeiten.
- Ein öffentlicher Release mit Signatur und GitHub-Veröffentlichung sowie die produktive Umschaltung des ursprünglichen Shops sind eigene Folgeschritte. Die Testbereitstellung läuft im Sandbox-/Anfragemodus.
