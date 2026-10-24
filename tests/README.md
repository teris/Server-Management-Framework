# Sicherheitsregressionen AP01–AP09

Die Tests verwenden ausschließlich temporäre Verzeichnisse, SQLite, erfundene Benutzer und einen lokalen HTTPS-Provider. Produktive Konfigurationen und externe Anbieter werden nicht verwendet.

Voraussetzungen: PHP 8.3+ mit PDO_SQLite, mbstring, SimpleXML, cURL, ctype; Python 3.12+ mit cryptography; Node.js 20+. Der alte PHP-7.4-Hinweis im Haupt-README ist keine durch diese Tests bestätigte Laufzeitfreigabe. CI prüft 8.3, 8.4 und 8.5; lokal wurde 8.3 verwendet.

Alle Prüfungen gemeinsam: `python3 tests/run_all.py --report /tmp/smf-test-report.json`. Mit `--baseline` wird zusätzlich die unten beschriebene Gegenprobe ausgeführt. Die enthaltene CI-Matrix ist vorbereitet; ohne Push wurde noch kein Remote-Actions-Lauf gestartet.

```bash
python3 tests/check_syntax.py
php tests/run.php
python3 tests/test_http.py
python3 tests/test_tls.py
python3 tests/test_concurrency.py
python3 tests/test_packaging.py
python3 tests/test_config_migration.py
node tests/test_session_ui.cjs
node tests/test_browser_security.cjs
```

Mit `PHP_BIN` lässt sich eine andere PHP-Datei auswählen, mit `PHP_ARGS` beispielsweise eine isolierte Extension-Konfiguration. Die Verzeichnisse werden automatisch aufgeräumt. Keine Anwendungsschema-Migration läuft beim normalen HTTP-Aufruf oder beim Teststart auf einer bestehenden Datenbank.

Die zusätzliche lokale Gegenprobe `python3 tests/test_baseline.py` benötigt den gefetchten Tag 3.2.7 und zeigt, dass fünf HTTP-Sicherheitsprüfungen vor der Änderung scheitern. Sie führt die alte Anwendung nur in einer temporären Kopie mit deaktivierten Providern aus; als einzige Downloadprobe dient eine eigens erzeugte harmlose Datei.

Die JSON-Ausgaben enthalten die ausgeführte Anzahl und fehlgeschlagene Prüfungen. Die Tests decken Rollen und alle 14 Legacy-Module, zugeordnete Ressourcentypen, fremde Kunden, Providerbindung, VMID-Wiederverwendung, Dateipfade/Symlinks, Sitzungsablauf, Passwortwechsel, Tokenrotation, parallele Einlösungen, CSRF, Anfragebegrenzung und echte TLS-Ablehnungen am lokalen HTTPS-Server ab.

MySQL/MariaDB, PostgreSQL, echte Provider und produktive Apache-/Nginx-Konfigurationen benötigen ihre eigene spätere Abnahme. Die vollständige Schemakonsolidierung, allgemeine Frontend-Bereinigung und der Web-Updater bleiben bei AP11/AP12/AP15/AP16.
