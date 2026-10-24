# OpenGamePanel-Erweiterung für den modularen Shop

Die unveränderte OGP-API `ogp_api.php` reicht für eine bestätigte Bereitstellung und gezielte Löschung nicht aus. `smf_ogp_bridge.php` ergänzt den Vertrag `smf-ogp-bridge/1`. Installation im **vorhandenen OGP-Webroot**, nicht im Framework-Webroot. Bestehende OGP-Dateien und Agentkonfigurationen werden nicht ersetzt.

1. OGP-Datenbank, Konfiguration und Agentdaten sichern. Einen bestehenden OGP-Administrator-API-Token verwenden und die Shop-IP in OGP freigeben.
2. Einen zufälligen Schlüssel mit 32 Byte Entropie als 64 Hexzeichen in einem Passwortmanager erzeugen. Nur dessen SHA-256 wird auf dem OGP-Server gespeichert. Beispiel für die lokale Berechnung: `php -r 'echo hash("sha256", trim(fgets(STDIN))), PHP_EOL;'` und den Schlüssel auf Standardeingabe eingeben. Den Schlüssel nicht als Shellargument verwenden.
3. `smf_ogp_bridge.php` in den OGP-Webroot kopieren. Bevorzugt außerhalb dieses Webroots unter `../private/smf-ogp-bridge.json` eine Datei mit `{"key_sha256":"DER_64STELLIGE_HASH"}` und Dateirechten 0600 anlegen. Alternativ für FTP-Betrieb `includes/smf_ogp_bridge_config.php` erstellen:

   ```php
   <?php http_response_code(404); exit;
   // SMF_OGP_BRIDGE_KEY_SHA256: DER_64STELLIGE_HASH
   ```

   Die Kommentarzeile benötigt den echten Hash aus Schritt 2. Der rohe Schlüssel gehört nicht in diese Datei und nicht auf den FTP-Server.
4. Im Shop unter **Einrichtung → Anschlüsse** die HTTPS-OGP-Adresse, den vorhandenen API-Token und den Erweiterungsschlüssel speichern. Verbindung erneut prüfen. TLS wird einschließlich Zertifikatsprüfung verwendet; eigene CA ist möglich.
5. Ausschließlich für den Shop reservierte Portbereiche, einen tatsächlich erreichbaren Agent und eine unterstützte Spiel-/Moddefinition freigeben. Benötigte Startkarte hinterlegen. Zunächst Sandbox und Anfragebetrieb verwenden.

Alle Aufrufe benötigen HTTPS-POST, beide Geheimnisse und die bestehende OGP-IP-Freigabe. Die Erweiterung prüft Home, zufällige Auftragskennung, Agent, Besitzer, Spiel/Mod, IP/Port und eine opaque physische Identität. Startparameter werden typisiert gesetzt. Frische Installationen erhalten ein eigenes zufälliges Unterverzeichnis; ein vorhandenes ausführbares Spiel wird dabei nicht übernommen. Löschung setzt einen gestoppten Server, gestoppten Installer, abgeschaltetes FTP und einen überschneidungsfreien Pfad voraus. Es gibt keinen frei verwendbaren Shell- oder Dateipfad-Endpunkt und keine allgemeine Kundenlöschung.

Der Shop führt seine Schreibabsicht vor jedem nicht eindeutig wiederholbaren Aufruf dauerhaft. Bei Timeout erfolgt ein Abgleich über die eigene zufällige Kennung; ein unklarer Auftrag erzeugt keine zweite Ressource. Die OGP-Tabellen werden nicht in eine andere Storage-Engine umgewandelt. Änderungen anderer OGP-Administratoren werden beim nächsten Identitätsabgleich abgewehrt; OGP und Shop dürfen denselben Portbereich nicht unabhängig vergeben.

## Geprüfte Grundlage und Grenzen

Die Anbindung wurde gegen die auf dem eigenen Testsystem installierte OGP-Website-API und echte Linux-Agents geprüft. Eine Half-Life-/SteamCMD-Testinstallation lief bis zur RCON-Abfrage und kontrollierten Entfernung. Das ist keine allgemeine Freigabe jeder Spieldefinition oder Agentversion.

- Direkte Bereitstellung: Linux-Spieldefinitionen mit tatsächlich verfügbarem SteamCMD-Installer und durchsetzbaren Slots. Keine Garantie für RAM, Datenträgergröße oder harte CPU-Limits über diese API.
- Rsync bleibt Anfragebetrieb: Die geprüfte native API verwendet im Rsync-Startpfad eine nicht gesetzte Home-ID. Eine bloße API-Erfolgsantwort wäre kein Installationsnachweis.
- Neue native Homes besitzen zunächst leere Startparameter. Der native PHP-8-Startpfad kann dann an `array_key_exists(..., null)` abbrechen. Die Erweiterung initialisiert die Parameter, ohne OGP-Quelldateien zu verändern.
- Native Start-/Stoppantworten und sichtbarer Prozesszustand sind getrennt. Insbesondere Stopp kann verzögert bestätigt werden. Ports werden erst nach bestätigter Entfernung freigegeben.
- Windows und alternative Installer benötigen eine eigene Agent-/Spielabnahme; sie bleiben im Anfragebetrieb.

Primärquellen: [OGP-Website API](https://github.com/OpenGamePanel/OGP-Website/blob/master/ogp_api.php), [native API-Hilfsfunktionen](https://github.com/OpenGamePanel/OGP-Website/blob/master/includes/api_functions.php). Die Erweiterung steht unter **GPL-3.0-or-later**, siehe [Lizenz](LICENSE-GPL-3.0.txt). Es werden keine fremden OGP-Quelldateien mitgeliefert.
