# Framework-Prüfung und Verbesserungsplan

Stand: **24.10.2026** · geprüfter lokaler Ausgangsstand: **3.2.7** · Status: **Analyse abgeschlossen, Umsetzung offen**

Das Framework besitzt eine brauchbare fachliche Basis für Server-, Hosting- und Domainverwaltung. Vor einer neuen produktiven Veröffentlichung müssen jedoch mehrere zentrale Zugriffs- und Updatefehler behoben werden. Der Ausbau sollte in kleinen überprüfbaren Schritten erfolgen. Das bestehende Projekt **„Online Shop“** liefert bereits viele der benötigten Shopfunktionen und wird als Ausgangsbasis für deren Modularisierung eingeplant.

Diese Unterlagen enthalten die Prüfung und Planung. Anwendungscode, produktive Daten, Provider-Ressourcen und bestehende Veröffentlichungen wurden dabei nicht verändert. Isolierte Laufzeitproben verwendeten eine Kopie unter /tmp, eine lokale SQLite-Datei, deaktivierte Provider und harmlose Testdateien.

## Direkt weiterarbeiten

- [Arbeitspakete mit Reihenfolge, Abhängigkeiten und Abnahme](ARBEITSPLAN.md)
- [Zielarchitektur und Übernahme aus „Online Shop“](MODULARES-SHOPSYSTEM.md)
- [Prüfergebnisse als JSON](pruefergebnisse.json)
- [Dateiinventar mit SHA-256 für den Ausgangsstand](dateiinventar.json)

## Umfang und Aussagekraft

Inventarisiert wurden **251 Dateien**, darunter **118 PHP-Dateien**, **40 JavaScript-/MongoDB-Skriptdateien**, **10 SQL-Dateien**, **30 Sprach-XML-Dateien** und **14 Module**. Die Untersuchung umfasst Einstiegspunkte, Anmeldung/Sessions, Admin-/Modulrechte, Request-Dispatch, Provider-Zugriffe, Kundenportal, Benutzerverwaltung, Tickets, Datenbankschemata, Installation, Synchronisierung, Updates, Diagnosewerkzeuge und Dokumentation.

Der Bestand wurde vollständig inventarisiert und automatisiert geprüft; sicherheits- und funktionsrelevante Pfade wurden gezielt im Quellcode untersucht und teilweise ausgeführt. Das ist **keine Zusicherung, dass jede Funktion vollständig fehlerfrei ist**. Alle Provider-Aktionen, jede Bildschirmansicht und alle Datenbankvarianten Ende zu Ende auszuführen, erfordert eine gesonderte Testinstallation. Die jeweilige Belegart steht bei jedem Befund.

Auf GitHub ist weiterhin **3.2.7** das als „latest“ ausgewiesene Release, veröffentlicht am 12.09.2025. Ein bloßes Update auf ein neueres vorhandenes Release löst die Befunde daher derzeit nicht. Referenz: [Release 3.2.7](https://github.com/teris/Server-Management-Framework/releases/tag/3.2.7), am 24.10.2026 über die GitHub-Anbindung gelesen. Der lokale Ordner wurde nicht mit jedem Commit des aktuellen main-Branches abgeglichen.

## Prioritäten

- **P0 – vor jeder neuen Freigabe:** zentrale Zugriffslücken schließen und gefährliche direkte Web-Einstiege sperren.
- **P1 – vor regulärem produktivem Betrieb der betroffenen Funktion:** Datenintegrität, Anmeldung, Provider-Kommunikation und Updates stabilisieren.
- **P2 – geordnet nachziehen:** Funktions-, Wartungs- und Dokumentationsmängel beheben.

Erfasst: **25 Befunde** – **5 P0**, **13 P1**, **7 P2**. Die Einstufung bewertet den Codepfad; die tatsächliche Ausnutzbarkeit einer Installation hängt unter anderem von Webroot, Serverregeln und Dateirechten ab.

| ID | Priorität | Befund | Beleg |
| --- | --- | --- | --- |
| [F01](#f01) | P0 | Zentrale Admin-Prüfung lässt normale Benutzer durch | Lokal reproduziert |
| [F02](#f02) | P0 | Admin-Markierungen der Module werden nicht durchgesetzt | Lokal reproduziert |
| [F03](#f03) | P0 | Dateieditor liest und schreibt außerhalb des Projekts | Lokal reproduziert |
| [F04](#f04) | P0 | Updater besitzt einen direkt aufrufbaren ungeschützten Handler | Im Quellcode belegt; keine Installation ausgeführt |
| [F05](#f05) | P0 | Diagnose- und Datenendpunkte sind ohne Anmeldung erreichbar | Im Quellcode belegt; Erreichbarkeit hängt vom Webserver ab |
| [F06](#f06) | P1 | CSRF-Schutz und Begrenzung von Anfragen sind nicht wirksam angebunden | Konfigurationsfehler lokal reproduziert; Aufrufpfade statisch geprüft |
| [F07](#f07) | P1 | Provider-Verbindungen deaktivieren Zertifikatsprüfung | Im Quellcode belegt |
| [F08](#f08) | P1 | Session-Format und Laufzeiten widersprechen sich | Lokal reproduziert |
| [F09](#f09) | P1 | Kundenanmeldung verwendet unrotierte Session- und Remember-Tokens | Im Quellcode belegt |
| [F10](#f10) | P1 | Registrierung und E-Mail-Verifizierung passen nicht zusammen | Imports und Schema im Quellcode belegt; Teilbefund lokal geprüft |
| [F11](#f11) | P1 | Datenbankschemata decken die aufgerufenen Funktionen nicht ab | Schemavergleich durchgeführt |
| [F12](#f12) | P1 | SQLite-Erstimport und datenbankübergreifende SQL-Nutzung sind fehlerhaft | Lokal reproduziert; PostgreSQL-Abweichungen statisch belegt |
| [F13](#f13) | P1 | MongoDB-Implementierung enthält Erfolg meldende Platzhalter | Im Quellcode belegt; keine MongoDB-Instanz getestet |
| [F14](#f14) | P1 | Benutzerverwaltung ruft zehn nicht vorhandene Service-Methoden auf | Mit Reflection und Aufrufsuche geprüft |
| [F15](#f15) | P1 | Updater verliert Update-Typ und Zielversion zwischen Anfragen | Zustandsfluss und Ersetzungsmuster im Quellcode belegt |
| [F16](#f16) | P1 | Updateinstallation überschreibt Konfiguration und ist nicht atomar | Im Quellcode und Release-Metadaten belegt |
| [F17](#f17) | P2 | Sprachwechsel bricht wegen falschem Datentyp ab | Lokal reproduziert |
| [F18](#f18) | P1 | Gemeinsame Validierung verwechselt Zahlenbereiche mit Textlängen | Lokal reproduziert |
| [F19](#f19) | P2 | Proxmox-Rendering hält den Modulvertrag nicht ein | Im Quellcode belegt |
| [F20](#f20) | P2 | Netzwerkmodul ignoriert die angeforderte IP und erzwingt Node pve | Im Quellcode belegt; Provider nicht aufgerufen |
| [F21](#f21) | P2 | Unbeabsichtigte Ausgabe und PHP-Kurz-Tags stören die Auslieferung | Lokal reproduziert |
| [F22](#f22) | P2 | Statische JavaScript-Datei enthält unverarbeitetes PHP | Syntaxfehler lokal reproduziert; aktuell nicht eingebunden |
| [F23](#f23) | P1 | Manuelle Vollschemata liefern ein vorgegebenes Admin-Konto mit | Im Quellcode belegt; betrifft nicht den kontoarmen Installer-Schemensatz |
| [F24](#f24) | P2 | Dokumentation, Versions- und Lizenzangaben sind widersprüchlich | Im Dateibestand belegt |
| [F25](#f25) | P2 | Reproduzierbare Tests und ein Release-Prozess fehlen | Im Dateibestand belegt |

## Befunde im Detail

<a id="f01"></a>

### F01 · P0 · Zentrale Admin-Prüfung lässt normale Benutzer durch

**Beleg:** Lokal reproduziert. Fundstellen: [src/core/AdminHandler.php:27](../../src/core/AdminHandler.php#L27), [src/index.php:84](../../src/index.php#L84).

Der AdminHandler protokolliert eine falsche Rolle lediglich; die ablehnende Exception ist auskommentiert. index.php leitet angemeldete Benutzer an diesen Handler weiter. Der Konstruktor ließ im isolierten Test einen Benutzer mit Rolle user passieren.

**Auswirkung:** Administrative Aktionen können mit einem normalen Backend-Konto erreichbar sein, darunter Ressourcensteuerung und Kundenverwaltung.

**Behebung:** Admin-Rechte vor Initialisierung von Datenbank-/Provider-Diensten erzwingen und jeden administrativen Einstieg zentral absichern.

**Abnahme:** Nicht angemeldet: 401; normale Rolle: 403; Admin: berechtigter Aufruf. Abgewiesene Anfragen verursachen keinerlei Provider-Aufruf.

**Arbeitspakete:** AP02, AP04.

<a id="f02"></a>

### F02 · P0 · Admin-Markierungen der Module werden nicht durchgesetzt

**Beleg:** Lokal reproduziert. Fundstellen: [framework.php:7656](../../framework.php#L7656), [src/sys.conf.php:67](../../src/sys.conf.php#L67), [src/module/ModuleBase.php:33](../../src/module/ModuleBase.php#L33).

Die Konfiguration verwendet require_admin. canAccessModule() prüft stattdessen permissions; ohne diesen Schlüssel wird Zugriff erlaubt. Ein normales Konto durfte deshalb im Test auf den als Admin-Modul markierten Dateieditor zugreifen. Die klassische Navigation umgeht den ModuleLoader teilweise zusätzlich.

**Auswirkung:** Dateieditor und Supportverwaltung sind trotz Admin-Markierung nicht verlässlich auf Administratoren beschränkt.

**Behebung:** Einheitliche Richtlinien für Rolle, Modulaktivierung und konkrete Aktion verwenden. Fehlende Freigaben müssen ablehnen; Anzeige und Request-Handler müssen dieselbe Regel verwenden.

**Abnahme:** Alle 14 Module erhalten eine Zugriffs-Matrix; Admin-Module sind für normale Konten auch über direkte AJAX-Aufrufe gesperrt.

**Arbeitspakete:** AP04, AP05.

<a id="f03"></a>

### F03 · P0 · Dateieditor liest und schreibt außerhalb des Projekts

**Beleg:** Lokal reproduziert. Fundstellen: [src/module/file-editor/Module.php:53](../../src/module/file-editor/Module.php#L53), [src/module/file-editor/Module.php:86](../../src/module/file-editor/Module.php#L86), [src/module/file-editor/Module.php:249](../../src/module/file-editor/Module.php#L249).

Dateipfade aus Requests werden unmittelbar an file_get_contents(), file_put_contents() und unlink() gegeben. Die Prüfung berücksichtigt lediglich Dateisystemrechte. Eine harmlose Markerdatei außerhalb des Projektordners ließ sich im Test lesen und ändern.

**Auswirkung:** Je nach Rechten des PHP-Prozesses können Konfigurationen offengelegt oder ausführbare PHP-Dateien verändert werden. Zusammen mit F02 entsteht ein besonders kritischer Zugriffspfad.

**Behebung:** Dateieditor zunächst standardmäßig deaktivieren. Für eine spätere Freigabe erlaubte Verzeichnisse und Dateitypen festlegen, kanonische Pfade und Symlinks prüfen und Geheimnisse sowie Anwendungscode vom Schreiben ausschließen.

**Abnahme:** Absolute Fremdpfade, ../, Symlinks, Konfigurationsdateien und PHP-Schreibversuche werden abgewiesen; erlaubte Inhaltsdateien bleiben bearbeitbar.

**Arbeitspakete:** AP02, AP06.

<a id="f04"></a>

### F04 · P0 · Updater besitzt einen direkt aufrufbaren ungeschützten Handler

**Beleg:** Im Quellcode belegt; keine Installation ausgeführt. Fundstellen: [src/inc/manualupdater.php:11](../../src/inc/manualupdater.php#L11), [src/inc/manualupdater.php:1883](../../src/inc/manualupdater.php#L1883), [src/inc/manualupdater.php:860](../../src/inc/manualupdater.php#L860).

manualupdater.php setzt seine Schutzkonstante selbst. Vor den POST-/GET-Handlern fehlen Login- und Rollenprüfungen. Auch über index.php genügt zunächst ein Login. Beim Backup-Download wird ein übergebener Name ohne kanonische Verzeichnisbindung zusammengesetzt; nur die ZIP-Endung wird geprüft.

**Auswirkung:** Bei erreichbarem src/inc-Verzeichnis können Update- und Backup-Aktionen ohne passende Rechte aufgerufen werden; fremde ZIP-Dateien können über Pfadwechsel als Download angesprochen werden.

**Behebung:** Direkte Include-Dateien per Webserver und Bootstrap sperren; Updateaktionen nur mit Admin-Rechten und CSRF-Schutz. Backup-Dateien über serverseitige IDs und sichere Pfadauflösung auswählen.

**Abnahme:** Direktaufrufe liefern 403/404. Normale Konten können weder Backups verwalten noch Updates anstoßen. Fremdpfade und nicht registrierte Archive werden abgewiesen.

**Arbeitspakete:** AP02, AP06, AP15.

<a id="f05"></a>

### F05 · P0 · Diagnose- und Datenendpunkte sind ohne Anmeldung erreichbar

**Beleg:** Im Quellcode belegt; Erreichbarkeit hängt vom Webserver ab. Fundstellen: [src/debug/index.php:29](../../src/debug/index.php#L29), [src/ajax_ip_mac_reverse.php:11](../../src/ajax_ip_mac_reverse.php#L11), [src/api/status.php:20](../../src/api/status.php#L20), [src/download_zone.php:23](../../src/download_zone.php#L23).

Die Debug-Zentrale bietet unter anderem Logzugriff, clear_logs und quick_fix ohne requireLogin(). Der IP/MAC-Endpunkt hat nur auskommentierten Zugriffsschutz. Die Status-API veröffentlicht Infrastrukturinformationen. download_zone.php liest und löscht passende .txt-Dateien aus dem gemeinsamen temporären Verzeichnis ohne Nutzerbindung.

**Auswirkung:** Informationen über Infrastruktur und Logs können offengelegt und Diagnose-/Dateiaktionen unberechtigt ausgelöst werden.

**Behebung:** Diagnosewerkzeuge aus Produktionspaketen entfernen oder strikt auf Admin/CLI begrenzen. Öffentlichen Status ausdrücklich auf freigegebene Aggregate beschränken. Exporte nutzergebunden und mit Ablaufzeit verwalten.

**Abnahme:** Ein HTTP-Test ohne Cookie erreicht keine internen Daten oder mutierenden Diagnoseaktionen; ein fremder Nutzer kann keinen Export lesen oder löschen.

**Arbeitspakete:** AP02, AP06, AP20.

<a id="f06"></a>

### F06 · P1 · CSRF-Schutz und Begrenzung von Anfragen sind nicht wirksam angebunden

**Beleg:** Konfigurationsfehler lokal reproduziert; Aufrufpfade statisch geprüft. Fundstellen: [src/handler.php:249](../../src/handler.php#L249), [src/handler.php:211](../../src/handler.php#L211), [src/sys.conf.php:230](../../src/sys.conf.php#L230), [src/auth_handler.php:181](../../src/auth_handler.php#L181).

validateCSRFToken() fragt isFeatureEnabled() ab, obwohl csrf_protection in security_config liegt. Es gibt keine Aufrufer der Prüf-Funktion in den untersuchten PHP-Dateien. Gleiches gilt für checkRateLimit(); dessen Schalter liegt wiederum in api_config. Loginversuche werden protokolliert, aber die konfigurierte Sperre wird im Loginpfad nicht umgesetzt.

**Auswirkung:** Konfigurationswerte vermitteln einen Schutz, den die Request-Verarbeitung tatsächlich nicht erzwingt. Schreibaktionen und Anmeldungen bleiben unzureichend abgesichert.

**Behebung:** CSRF-, Methoden- und Rate-Limit-Prüfungen zentral vor dem Dispatch anwenden; öffentliche Formulare und beide Loginbereiche einbeziehen. Rate-Limits nebenläufigkeitssicher speichern.

**Abnahme:** Fehlende/falsche CSRF-Tokens führen zu 403; wiederholte Login-/Ticketanfragen zu einer definierten Begrenzung. Änderungen der Konfiguration wirken nachweisbar.

**Arbeitspakete:** AP07.

<a id="f07"></a>

### F07 · P1 · Provider-Verbindungen deaktivieren Zertifikatsprüfung

**Beleg:** Im Quellcode belegt. Fundstellen: [framework.php:652](../../framework.php#L652), [framework.php:937](../../framework.php#L937), [framework.php:4554](../../framework.php#L4554).

Proxmox-, ISPConfig- und OGP-Transportpfade schalten die TLS-Zertifikatsprüfung teilweise vollständig aus.

**Auswirkung:** Verbindungen prüfen die Gegenstelle nicht zuverlässig; administrative Zugangsdaten und Antworten können dadurch gefährdet werden.

**Behebung:** Zertifikats- und Hostnamenprüfung aktivieren. Für eigene Server eine bewusst hinterlegte CA unterstützen; Zertifikatsfehler nachvollziehbar melden.

**Abnahme:** Gültige öffentliche/eigene CA funktioniert; falscher Hostname, unbekannte CA und abgelaufenes Zertifikat werden in lokalen Transporttests abgewiesen.

**Arbeitspakete:** AP08, AP19.

<a id="f08"></a>

### F08 · P1 · Session-Format und Laufzeiten widersprechen sich

**Beleg:** Lokal reproduziert. Fundstellen: [src/auth_handler.php:75](../../src/auth_handler.php#L75), [src/module/ModuleBase.php:21](../../src/module/ModuleBase.php#L21), [src/auth_handler.php:10](../../src/auth_handler.php#L10), [src/sys.conf.php:170](../../src/sys.conf.php#L170).

Der Login schreibt user_id und role direkt in die Session; ModuleBase erwartet user[id] und user[role]. Im Test blieb die Modulrolle null. Der SessionManager verwendet fest 600 Sekunden, während die Konfiguration 3600 vorgibt.

**Auswirkung:** Module mit eigener Rollenprüfung verweigern auch Administratoren den Zugriff; andere Module verwenden eine falsche Identität. Angezeigte und wirksame Sitzungslaufzeit können abweichen.

**Behebung:** Einen zentralen Benutzerkontext für Backend und Shop festlegen; Rollen und Status aus verlässlichen Daten ableiten. Timeout und Heartbeat nur über eine gemeinsame Konfiguration steuern.

**Abnahme:** Admin-Login ergibt in jedem Modul dieselbe Benutzer-ID/Rolle. Inaktivitätsablauf, Kontosperre, Logout und konfigurierte Laufzeit sind getestet.

**Arbeitspakete:** AP04, AP09.

<a id="f09"></a>

### F09 · P1 · Kundenanmeldung verwendet unrotierte Session- und Remember-Tokens

**Beleg:** Im Quellcode belegt. Fundstellen: [public/login.php:56](../../public/login.php#L56), [public/login.php:67](../../public/login.php#L67), [public/login.php:111](../../public/login.php#L111).

Nach Kundenlogin und Wiederanmeldung wird keine Session-ID erneuert. Remember-Tokens werden im Klartext gespeichert und beim Einlösen nicht ersetzt. Backend und Kundenbereich verwenden unterschiedliche Sessionmechanismen.

**Auswirkung:** Es fehlt eine einheitliche Absicherung gegen Session-Fixation und die Wiederverwendung entwendeter Langzeittokens.

**Behebung:** Session-ID bei Anmeldung erneuern; Remember-Tokens gehasht speichern, nach Nutzung rotieren und bei Logout/Passwortwechsel widerrufen. Sichere Cookie-Einstellungen zentral setzen.

**Abnahme:** Vorherige Session-ID und bereits eingelöste Tokens funktionieren nach Login/Rotation nicht mehr; Passwortänderung widerruft bestehende Langzeitzugänge.

**Arbeitspakete:** AP09.

<a id="f10"></a>

### F10 · P1 · Registrierung und E-Mail-Verifizierung passen nicht zusammen

**Beleg:** Imports und Schema im Quellcode belegt; Teilbefund lokal geprüft. Fundstellen: [public/register.php:90](../../public/register.php#L90), [public/verify.php:42](../../public/verify.php#L42), [src/core/EmailTemplateManager.php:177](../../src/core/EmailTemplateManager.php#L177), [database/database-structure-sqlite.sql:114](../../database/database-structure-sqlite.sql#L114).

register.php verwendet EmailTemplateManager ohne die Klasse zu laden; in den tatsächlichen Basisimports ist sie nicht vorhanden. Der dadurch mögliche Error wird von catch(Exception) nicht abgefangen. Versand und Verifizierung erwarten customer_verification_tokens, die vollständigen Installationsschemata liefern verification_tokens. Der Rückgabewert des Mailversands wird nicht ausgewertet.

**Auswirkung:** Ein Kunde kann als pending gespeichert werden, während Registrierung/Versand abbrechen; eine angezeigte Erfolgsmeldung belegt keinen versendeten Aktivierungslink.

**Behebung:** Klasse über gemeinsamen Bootstrap laden, ein einheitliches Token-Schema migrieren und Versandfehler mit wiederholbarem Versand behandeln. Aktivierung und Provider-Bereitstellung entkoppeln; Benutzerpasswörter nicht an E-Mail-Templates übergeben.

**Abnahme:** Registrieren → genau ein Token → Versand/erneuter Versand → einmalige Aktivierung → Login funktioniert. Ablauf, Doppelklick und SMTP-Fehler sind abgedeckt.

**Arbeitspakete:** AP10, AP11.

<a id="f11"></a>

### F11 · P1 · Datenbankschemata decken die aufgerufenen Funktionen nicht ab

**Beleg:** Schemavergleich durchgeführt. Fundstellen: [src/core/DatabaseOnlyFramework.php:123](../../src/core/DatabaseOnlyFramework.php#L123), [src/core/DatabaseOnlyFramework.php:124](../../src/core/DatabaseOnlyFramework.php#L124), [src/core/EmailTemplateManager.php:258](../../src/core/EmailTemplateManager.php#L258), [src/install.php:207](../../src/install.php#L207).

Das MySQL-Installationsschema enthält 25 Tabellen, aber weder module_configs noch module_permissions noch email_templates. Diese werden von Datenbankmodus und Mailverwaltung erwartet. Das Mail-Schema liegt getrennt vor, wird vom Installer jedoch nicht als Migration ausgeführt.

**Auswirkung:** Eine formal erfolgreiche Installation garantiert keine lauffähigen Module. Mehrere SQL-Dateisätze und Modelle können auseinanderlaufen.

**Behebung:** Eine versionierte Migrationshistorie als verbindliche Quelle einführen. Frische Installation und Upgrade verwenden dieselben Migrationen; Schema-Version vor dem Start prüfen.

**Abnahme:** Frische und von 3.2.7 migrierte Datenbanken bestehen dieselben Schema- und Funktionsprüfungen, einschließlich Modulkonfiguration und Mailvorlagen.

**Arbeitspakete:** AP11, AP12.

<a id="f12"></a>

### F12 · P1 · SQLite-Erstimport und datenbankübergreifende SQL-Nutzung sind fehlerhaft

**Beleg:** Lokal reproduziert; PostgreSQL-Abweichungen statisch belegt. Fundstellen: [config/framework-sqlite.sql:61](../../config/framework-sqlite.sql#L61), [src/core/DatabaseManager.php:631](../../src/core/DatabaseManager.php#L631), [src/core/EmailTemplateManager.php:258](../../src/core/EmailTemplateManager.php#L258), [src/update.php:159](../../src/update.php#L159).

Der im README empfohlene leere SQLite-Import bricht mit no such table: main.users ab. Das vollständige SQLite-Schema importiert, aber DatabaseManager::logAction() scheitert an NOW(). Weitere MySQL-Ausdrücke wie SHOW TABLES und ON DUPLICATE KEY stehen auch in als datenbankübergreifend verwendeten Pfaden.

**Auswirkung:** SQLite und PostgreSQL sind nicht als vollständig unterstützte Alternativen belegt; Anmeldung, Logging, Vorlagen und Synchronisation können je nach Datenbank ausfallen.

**Behebung:** Zunächst MySQL/MariaDB als produktiven Referenzpfad und SQLite als ausdrücklich getesteten lokalen Pfad festlegen. Dialektabhängige SQL-Anteile kapseln. PostgreSQL erst nach eigener Integrationsmatrix freigeben.

**Abnahme:** Logging und Kernabläufe laufen auf jeder beworbenen Datenbank. Alle dokumentierten Installationsbefehle funktionieren auf einer leeren Instanz.

**Arbeitspakete:** AP11, AP12.

<a id="f13"></a>

### F13 · P1 · MongoDB-Implementierung enthält Erfolg meldende Platzhalter

**Beleg:** Im Quellcode belegt; keine MongoDB-Instanz getestet. Fundstellen: [src/core/DatabaseManager.php:24](../../src/core/DatabaseManager.php#L24), [src/core/DatabaseManager.php:73](../../src/core/DatabaseManager.php#L73), [src/core/DatabaseManager.php:378](../../src/core/DatabaseManager.php#L378).

Dummy-Klassen ersetzen bei noch nicht geladener MongoDB-Library echte Klassennamen. Teile des Treibers geben Erfolg oder leere Ergebnisse zurück, obwohl keine entsprechende Datenoperation stattfindet. Die überwiegend SQL-basierten Geschäftsabläufe besitzen keinen gleichwertigen MongoDB-Pfad.

**Auswirkung:** Datenverlust oder fehlende Ergebnisse können als erfolgreiche Verarbeitung erscheinen. Eine Erweiterungsprüfung allein stellt die tatsächliche Library nicht sicher.

**Behebung:** MongoDB bis zu einer vollständigen Implementierung als nicht unterstützt ausweisen und bei Auswahl eindeutig abbrechen. Dummys aus produktivem Code entfernen; echte Adapter nur mit Integrationstests freigeben.

**Abnahme:** Fehlender Treiber/fehlende Library führt zu einem klaren Startfehler. Keine Schreiboperation darf ohne gespeicherte Daten Erfolg melden.

**Arbeitspakete:** AP12.

<a id="f14"></a>

### F14 · P1 · Benutzerverwaltung ruft zehn nicht vorhandene Service-Methoden auf

**Beleg:** Mit Reflection und Aufrufsuche geprüft. Fundstellen: [src/inc/users.php:175](../../src/inc/users.php#L175), [src/inc/users.php:232](../../src/inc/users.php#L232), [src/inc/users.php:1142](../../src/inc/users.php#L1142).

Im API-Framework fehlen unter anderem getISPConfigUsers, editOGPUser, editProxmoxUser, editISPConfigUser, deleteOGPUser, deleteISPConfigUser, createISPConfigUser und drei update…UserPassword-Methoden. Die vollständige Liste steht in pruefergebnisse.json. Ein __call-Fallback existiert nicht.

**Auswirkung:** Die entsprechenden Verwaltungs- und Passwortaktionen brechen mit einem PHP Error ab; catch(Exception) behandelt diese Fehler nicht.

**Behebung:** Die vorgesehenen Provider-Funktionen und ihre Signaturen abgleichen, Aufrufer an vorhandene Verträge anpassen oder fehlende Operationen gezielt implementieren. Nicht unterstützte Aktionen explizit ausblenden/ablehnen.

**Abnahme:** Jede sichtbare Benutzeraktion besitzt eine passende Implementierung und einen Test mit simuliertem Provider; keine Undefined-method-Fehler.

**Arbeitspakete:** AP13, AP19.

<a id="f15"></a>

### F15 · P1 · Updater verliert Update-Typ und Zielversion zwischen Anfragen

**Beleg:** Zustandsfluss und Ersetzungsmuster im Quellcode belegt. Fundstellen: [src/inc/manualupdater.php:24](../../src/inc/manualupdater.php#L24), [src/inc/manualupdater.php:1914](../../src/inc/manualupdater.php#L1914), [src/inc/manualupdater.php:1573](../../src/inc/manualupdater.php#L1573).

Download und Installation erzeugen jeweils einen neuen ManualUpdater. updateType wird nur beim Download gesetzt; Installation fällt wieder auf framework zurück. githubVersion wird ebenfalls nicht verlässlich übernommen. Der Versions-Regex trifft 15 version-Felder in sys.conf.php, darunter die 14 Pluginversionen.

**Auswirkung:** Ein gewünschtes Vollupdate wird unvollständig installiert, die Zielversion kann falsch erfasst werden und unabhängige Pluginversionen werden überschrieben.

**Behebung:** Ein serverseitig gespeichertes Updatevorhaben mit ID, Typ, Zielversion, Commit und Paketprüfsumme verwenden. Nur die Framework-Version gezielt aktualisieren; Pluginversionen separat verwalten.

**Abnahme:** Vollupdate funktioniert über getrennte HTTP-Anfragen; Framework-/Modulversionen bleiben korrekt und unterbrochene Anfragen können sicher fortgesetzt werden.

**Arbeitspakete:** AP15.

<a id="f16"></a>

### F16 · P1 · Updateinstallation überschreibt Konfiguration und ist nicht atomar

**Beleg:** Im Quellcode und Release-Metadaten belegt. Fundstellen: [src/inc/manualupdater.php:1482](../../src/inc/manualupdater.php#L1482), [src/inc/manualupdater.php:1524](../../src/inc/manualupdater.php#L1524), [src/inc/manualupdater.php:226](../../src/inc/manualupdater.php#L226).

Das Framework-Paket schließt config/ ein; beim Vollupdate wird nur sys.conf.php ausgespart. Laufzeitkonfigurationen können überschrieben werden. Es fehlen eine überprüfte Paketidentität, ein verbindlicher Migrationsschritt, sichere ZIP-Eintragsgrenzen und atomare Aktivierung. Für das aktuelle Release 3.2.7 existiert zudem kein zusätzliches Vollupdate-ZIP-Asset, obwohl der Updater dieses erwartet.

**Auswirkung:** Updates können eine inkonsistente Installation oder verlorene Konfiguration hinterlassen. Ein vorhandenes Dateibackup allein garantiert keine wiederherstellbare Datenbank.

**Behebung:** Geheimnisse und Laufzeitdateien aus Paketen ausschließen. Pakete vorab in einem Staging-Verzeichnis prüfen; Pfadwechsel, Symlinks und Größenlimits kontrollieren. Datenbankmigration, Wartungsmodus, Aktivierung und Rückrollplan verbindlich definieren.

**Abnahme:** Fehlgeschlagene Pakete ändern den aktiven Stand nicht. Konfiguration/Schlüssel/Kundendaten bleiben erhalten; Dateirestore und Datenbankrestore werden praktisch geprüft.

**Arbeitspakete:** AP15, AP28, AP30.

<a id="f17"></a>

### F17 · P2 · Sprachwechsel bricht wegen falschem Datentyp ab

**Beleg:** Lokal reproduziert. Fundstellen: [src/sys.conf.php:166](../../src/sys.conf.php#L166), [src/core/LanguageManager.php:37](../../src/core/LanguageManager.php#L37).

available_languages ist eine CSV-Zeichenkette; der LanguageManager erwartet ein Array. setLanguage(en) wirft unter PHP 8.3 einen TypeError. Tatsächlich liegen überwiegend nur DE-/EN-Dateien vor.

**Auswirkung:** Der Sprachwechsel funktioniert nicht zuverlässig, und angekündigte Sprachen besitzen keine vollständige Übersetzung.

**Behebung:** Konfiguration auf eine Liste normalisieren, verfügbare Sprachdateien auswerten und Rückfallregeln definieren.

**Abnahme:** DE ↔ EN funktioniert für Kern und Module ohne Warnung/Fehler; unbekannte und fehlende Sprachen werden kontrolliert behandelt.

**Arbeitspakete:** AP14.

<a id="f18"></a>

### F18 · P1 · Gemeinsame Validierung verwechselt Zahlenbereiche mit Textlängen

**Beleg:** Lokal reproduziert. Fundstellen: [src/module/ModuleBase.php:154](../../src/module/ModuleBase.php#L154), [src/module/email/Module.php:48](../../src/module/email/Module.php#L48).

min und max prüfen immer strlen(). Die gültige Mailquote 1024 wird bei min:10 als zu kurzer Text verworfen. required prüft nur das Vorhandensein eines Schlüssels; ein vorhandener leerer String besteht diese Regel.

**Auswirkung:** Gültige Eingaben werden zurückgewiesen, leere Pflichtfelder können akzeptiert werden und fachliche Grenzen werden falsch geprüft.

**Behebung:** Typabhängige Regeln für Integer, Zahlenbereich, Textlänge, Pflichtwert und erlaubte Werte einführen; alle Module an derselben Semantik ausrichten.

**Abnahme:** 1024 MB besteht den numerischen Mindestwert 10; 9 MB und negative Werte scheitern. Leere Pflichtwerte und falsch typisierte Arrays werden sauber abgelehnt.

**Arbeitspakete:** AP14.

<a id="f19"></a>

### F19 · P2 · Proxmox-Rendering hält den Modulvertrag nicht ein

**Beleg:** Im Quellcode belegt. Fundstellen: [src/module/proxmox/Module.php:10](../../src/module/proxmox/Module.php#L10), [src/module/ModuleBase.php:379](../../src/module/ModuleBase.php#L379).

ProxmoxModule::getContent() gibt das Template direkt aus und liefert null zurück. Der AJAX-Loader erwartet HTML als Rückgabewert und baut daraus JSON.

**Auswirkung:** Beim Lazy Loading kann HTML vor der JSON-Antwort ausgegeben werden oder der Inhalt fehlen. Die klassische Include-Ansicht kann dieses Problem verdecken.

**Behebung:** Alle Module über einen einheitlichen Renderer ausgeben und das Rückgabeformat der AJAX-Aktionen verbindlich machen.

**Abnahme:** Der getContent-Aufruf produziert genau eine gültige JSON-Antwort mit HTML im vorgesehenen Feld und ohne vorangestellte Ausgabe.

**Arbeitspakete:** AP16.

<a id="f20"></a>

### F20 · P2 · Netzwerkmodul ignoriert die angeforderte IP und erzwingt Node pve

**Beleg:** Im Quellcode belegt; Provider nicht aufgerufen. Fundstellen: [src/module/network/Module.php:37](../../src/module/network/Module.php#L37).

updateVMNetwork() verlangt eine IP-Adresse, schreibt jedoch nur net0/MAC/Bridge. Die IP wird lediglich protokolliert. Der API-Pfad enthält fest /nodes/pve/ statt des tatsächlichen Nodes.

**Auswirkung:** Die Erfolgsmeldung kann eine nicht ausgeführte IP-Konfiguration suggerieren; Systeme mit anderem Nodenamen werden falsch angesprochen.

**Behebung:** Tatsächlichen Ressourcen-Node verwenden; Bridge und MAC prüfen. IP-Verwaltung über unterstützte Cloud-Init-/Gastagent-Funktionen ausdrücklich implementieren oder als nicht unterstützt kennzeichnen.

**Abnahme:** Ein anderer Nodename funktioniert im Adaptertest; Erfolg bedeutet nachprüfbar angewendete Konfiguration. Nicht unterstützte IP-Änderungen werden nicht als erfolgreich gemeldet.

**Arbeitspakete:** AP19.

<a id="f21"></a>

### F21 · P2 · Unbeabsichtigte Ausgabe und PHP-Kurz-Tags stören die Auslieferung

**Beleg:** Lokal reproduziert. Fundstellen: [framework.php:1](../../framework.php#L1), [src/module/file-editor/templates/main.php:684](../../src/module/file-editor/templates/main.php#L684).

framework.php beginnt mit UTF-8-BOM und erzeugt beim bloßen Laden die Bytes efbbbf. Ohne passende Pufferung stört das Header und Sessions; in JSON-Antworten sind es zusätzliche Bytes. Mit short_open_tag=1 wird ein XML-String im Dateieditor als PHP interpretiert und verursacht einen Parse Error.

**Auswirkung:** Header, Cookies, Downloads und JSON-Verarbeitung können abhängig von Servereinstellungen fehlschlagen.

**Behebung:** PHP-Dateien ohne BOM speichern und ungewollte Ausgaben vermeiden. XML-Öffnung konfliktfrei zusammensetzen; Zeichencodierung und relevante PHP-Einstellungen dokumentieren.

**Abnahme:** Das Laden des Frameworks erzeugt null Bytes; JSON-/Downloadantworten haben keine Präfixe. Der Dateieditor lässt sich mit beiden Kurz-Tag-Einstellungen parsen.

**Arbeitspakete:** AP14, AP16.

<a id="f22"></a>

### F22 · P2 · Statische JavaScript-Datei enthält unverarbeitetes PHP

**Beleg:** Syntaxfehler lokal reproduziert; aktuell nicht eingebunden. Fundstellen: [src/assets/index.js:1](../../src/assets/index.js#L1), [src/index.php:741](../../src/index.php#L741).

index.js enthält PHP-Templateausdrücke und ist als statisches JavaScript ungültig. Der aktuelle index.php stellt die betreffende Initialisierung bereits inline bereit; eine Einbindung von assets/index.js wurde dort nicht gefunden.

**Auswirkung:** Die Datei ist ein fehlerhafter, derzeit offenbar ungenutzter Parallelstand und würde bei statischer Einbindung im Browser abbrechen.

**Behebung:** Initialisierungsdaten bewusst über JSON im PHP-Template ausgeben; JavaScript selbst rein statisch halten. Unbenutzte Parallelstände und .bak-Dateien entfernen.

**Abnahme:** Alle ausgelieferten Browser-JavaScript-Dateien bestehen die Syntaxprüfung; es existiert nur ein verwendeter Initialisierungspfad.

**Arbeitspakete:** AP16.

<a id="f23"></a>

### F23 · P1 · Manuelle Vollschemata liefern ein vorgegebenes Admin-Konto mit

**Beleg:** Im Quellcode belegt; betrifft nicht den kontoarmen Installer-Schemensatz. Fundstellen: [database/database-structure-optimized.sql:604](../../database/database-structure-optimized.sql#L604), [database/database-structure-sqlite.sql:730](../../database/database-structure-sqlite.sql#L730).

Die manuellen Vollschemata legen ein Admin-Konto mit fest hinterlegtem Passwort-Hash an. Der reguläre Installer verwendet andere Schemadateien und erzeugt sein eigenes Passwort; das Risiko betrifft insbesondere manuelle Imports.

**Auswirkung:** Unterschiedliche Installationswege besitzen unterschiedliche Sicherheitsmerkmale; ein öffentlich bekannter Ausgangszugang kann bestehen bleiben.

**Behebung:** Vorbelegte administrative Konten aus Produktivmigrationen entfernen. Erstinstallation über einmaligen sicheren Bootstrap durchführen; bestehende Instanzen auf unveränderte Beispielkonten prüfen.

**Abnahme:** Keine Neuinstallation enthält ein bekanntes oder fest vorgegebenes administratives Passwort. Der Bootstrap kann nach Abschluss nicht erneut offen ausgeführt werden.

**Arbeitspakete:** AP02, AP11.

<a id="f24"></a>

### F24 · P2 · Dokumentation, Versions- und Lizenzangaben sind widersprüchlich

**Beleg:** Im Dateibestand belegt. Fundstellen: [README.md:6](../../README.md#L6), [LICENSE:1](../../LICENSE#L1), [README.md:5](../../README.md#L5), [PROJECT_OVERVIEW.md:9](../../PROJECT_OVERVIEW.md#L9).

Das README nennt MIT, die LICENSE-Datei Apache 2.0. Dort wird PHP >=7.4 beworben, obwohl der Code neuere Sprachmittel verwendet; die letzte Version wird teils als 3.1.0 beschrieben. PROJECT_OVERVIEW nennt eine nicht vorhandene conf.inc.php. Einige Installationspfade stimmen nicht mit dem Dateibestand überein.

**Auswirkung:** Installation, Erweiterungsentwicklung und spätere Veröffentlichung bauen auf uneindeutigen Anforderungen auf.

**Behebung:** Eine verbindliche Lizenzangabe entsprechend der vorhandenen Projektlizenz führen und die gewünschte Lizenz bei einer Änderung gesondert klären. Runtime-, Versions-, Installations- und Funktionsangaben aus dem überprüften Stand ableiten.

**Abnahme:** Alle Installationsanweisungen sind nachgebaut; Projektmetadaten nennen dieselbe Version/Lizenz und nur tatsächlich getestete PHP-/Datenbankkombinationen.

**Arbeitspakete:** AP17, AP29.

<a id="f25"></a>

### F25 · P2 · Reproduzierbare Tests und ein Release-Prozess fehlen

**Beleg:** Im Dateibestand belegt. Fundstellen: [test.php:31](../../test.php#L31), [.gitignore:21](../../.gitignore#L21).

Das lokale Projekt ist kein Git-Checkout. .github enthält Vorlagen, aber keine CI-Workflows; composer.json und ein automatisiertes Testpaket fehlen. test.php führt einen echten Provider-Aufruf aus und ist kein isolierter Regressionstest. framework.php umfasst 7.686 Zeilen; API- und Datenbankmodus pflegen teilweise parallele Klassen.

**Auswirkung:** Fehlerbehebungen, Upgrades und Veröffentlichungen lassen sich bisher nicht zuverlässig reproduzieren oder auf unbeabsichtigte Änderungen prüfen.

**Behebung:** Archiv mit Upstream abgleichen, versionierte Arbeitsbasis herstellen, isolierte Tests/CI und explizite Release-Dateilisten einführen. Kern und Provider schrittweise hinter geprüften Verträgen aufteilen.

**Abnahme:** Ein frischer Checkout kann prüfen, testen und Pakete erzeugen, ohne produktive Zugangsdaten oder externe Schreibzugriffe zu benötigen.

**Arbeitspakete:** AP01, AP03, AP18, AP28.

## Übersicht der geprüften Bereiche

„Quellprüfung“ bedeutet keine erfolgreiche Live-Abnahme. Deaktivierte Module wurden ebenfalls einbezogen, ohne sie produktiv zu aktivieren.

| Bereich/Modul | Ergebnis der Quellprüfung und nächster Schwerpunkt |
| --- | --- |
| Kern/ServiceManager | Große zentrale Datei, eager Provider-Anmeldung, gemischte Rückgabewerte; fehlende Methoden und parallele Implementierungen bereinigen. |
| Anmeldung/Kundenportal | Sessions, Rollen und Token vereinheitlichen; Registrierung/Verifikation reparieren; Profil-, Passwort- und Aktivitätsabläufe Ende zu Ende testen. |
| Admin | Zentrale Rollenprüfung erzwingen; jede administrative Aktion und Zielressource absichern. |
| Proxmox | Besitzrechte und Node/VM-Typ prüfen; Rendering reparieren; asynchrone Aufgaben bis zum bestätigten Ergebnis verfolgen. |
| ISPConfig | Websites, FTP, Datenbank und Mail mit Kunden-/Provider-Konto verknüpfen; Server-IDs und Quoten aus Profilen statt festen Vorgaben übernehmen. |
| OVH | Ressourcenbindung, erlaubte Operationen und Domainerwerb absichern; Produktverfügbarkeit und Preise nicht aus technischen Fehlern ableiten. |
| DNS | Domain-/Zonenbesitz, Eintragstypen und Import-/Exportzugriff prüfen; keine kontoübergreifenden Zonenschreibzugriffe. |
| Virtual MAC | Server-/IP-Zuordnung und zulässige Provider-Aktionen prüfen. |
| Network | Node pve und ignorierte IP korrigieren; nur tatsächlich unterstützte Änderungen anbieten. |
| Database | Gemeinsame Validierung, reale Kunden-/Serverzuordnung und Provider-Funktionsvertrag prüfen. |
| Email | Numerische Quotenvalidierung reparieren; Domains, Nutzer, Limits und Provider-Vertrag absichern. |
| Endpoints | Nur für Administratoren und Testsysteme; Proxy darf deaktivierte Module oder Ressourcenrechte nicht umgehen. |
| File Editor | Standardmäßig deaktivieren; Rechte- und Pfadbegrenzung vor jeder weiteren Nutzung. |
| Support Tickets | Adminschutz und Sichtbarkeit interner Notizen erzwingen; kundenbezogene Ticketrechte und Eingabegrenzen testen. Öffentliche API zusätzlich gegen Spam begrenzen. |
| Migration | Eigene Adminprüfung vorhanden, derzeit vom falschen Sessionformat betroffen; Fortschritt, Teilfehler und behaupteten Rollback mit Testdaten prüfen. |
| Custom Module | Sehr kleiner Beispielbaustein; als Entwicklungsvorlage behandeln und aus Standard-Produktivaktivierung ausschließen. |
| Datenbank/Installer | Ein Schema-/Migrationsweg, keine Beispieladministratoren, keine offenen Wiederinstallationen; Schema-Versprechen begrenzen. |
| Updater/Debug | Direkte Webzugriffe schließen; signierte/geprüfte Pakete, Konfigurationserhalt und Wiederherstellung planen. |
| Frontend/Assets/Sprachen | JS-Syntax, BOM, Renderverträge und Sprachwechsel korrigieren; danach Browserprüfung und Barrierefreiheit. |
| Betrieb/Release | Git-Basis, CI, Release-Dateilisten, Testserver und Rückrollprobe fehlen im lokalen Bestand. |

## Tatsächlich ausgeführte Prüfungen

| Prüfung | Ergebnis |
| --- | --- |
| PHP-Syntax, PHP 8.3.6 Ubuntu-Paket, short_open_tag=0 | **118/118 bestanden**. Syntaxfehlerfreiheit belegt keine funktionierende Anwendung. |
| Zusätzliche PHP-Prüfung mit short_open_tag=1 | **1 reproduzierbarer Parse Error** im Dateieditor-Template, Zeile 684. |
| Browser-JavaScript, Node.js 24.22.1 | **36/37 syntaktisch gültig**; index.js enthält PHP. Drei MongoDB-Shellskripte wurden vom Browser-JS-Urteil ausgeschlossen. |
| Sprach-XML | **30/30 parsebar**; inhaltliche Vollständigkeit wurde damit nicht bestätigt. |
| SQLite-Schemata auf leeren In-Memory-Datenbanken | **2/3 importierbar**; config/framework-sqlite.sql scheitert an fehlender users-Tabelle. |
| Framework laden | Gibt unerwartet **3 BOM-Bytes** aus. |
| AdminHandler mit normaler Rolle | Konstruktion trotz Rolle user erfolgreich. |
| Modulzugriff Dateieditor | Trotz require_admin mit normalem Login-Sessionformat erlaubt; Modulrolle null. |
| Dateizugriff | Lesen und Schreiben einer harmlosen Testdatei außerhalb der Projektkopie möglich. |
| Sprachwechsel | TypeError reproduziert. |
| SQLite-Logging | Fehlgeschlagen: no such function: NOW. |
| Validierung | Gültige Quote 1024 wird als Text zu kurz abgelehnt; leerer required-Wert besteht die Regel. |
| ServiceManager-Verträge | Zehn fehlende Methodennamen der Benutzerverwaltung ermittelt. |
| Update-Versionsregex | Trifft 15 Versionsfelder statt nur der Framework-Version. |
| GitHub-Metadaten | main als Standardbranch, latest=3.2.7, einziges angehängtes Paket framework-standalone.zip. |

Die Prüfung benutzte temporär entpackte PHP-Pakete, ohne Systeminstallation. Der erste Node-Versuch mit einer unvollständig entpackten Linux-Laufzeit war ein Werkzeugfehler; die berichteten JavaScript-Ergebnisse stammen aus dem danach verwendeten funktionsfähigen Node.js 24.22.1. MongoDB-Shellbefehle wurden nicht als Browser-JavaScript-Fehler gewertet.

Nicht ausgeführt: Produktionszugriffe, kostenpflichtige Bestellungen, echtes VM-Anlegen/Löschen, Provider-Passwortänderungen, SMTP-Versand, PayPal-Transaktionen, vollständige MySQL-/MariaDB-/PostgreSQL-/MongoDB-Integration und vollständige Browserabnahme. Es fand kein Upload, Commit, Push oder Release statt.

## Technischer Zielrahmen

Für den modernisierten Hauptzweig ist **PHP 8.4 als Mindestversion mit zusätzlichen Tests unter 8.5** ein sinnvoller Vorschlag. Das ist noch keine durchgeführte Kompatibilitätsfreigabe. Ein unterstützter Hotfix für den Altzweig darf nicht nebenbei die Laufzeitanforderungen ändern. Die offizielle PHP-Tabelle weist für 8.4 Sicherheitsunterstützung bis Ende 2028 und für 8.5 bis Ende 2029 aus. Quelle: [PHP Supported Versions](https://www.php.net/supported-versions.php), geprüft am 24.10.2026.

MySQL/MariaDB bleiben zunächst Referenz für Produktion. SQLite erhält ausdrücklich getestete Entwicklungs-/Testabläufe; PostgreSQL wird nach einer echten Integrationsmatrix freigegeben. MongoDB wird bis zur vollständigen Umsetzung nicht als produktiv unterstützt beworben.

Die Umsetzung folgt dem [Arbeitsplan](ARBEITSPLAN.md). Zuerst kommen die Zugriffskorrekturen und Tests, danach die funktionalen Fehler, anschließend die Extraktion des vorhandenen Shops und abschließend ein nachvollziehbarer GitHub-/Server-Release.
