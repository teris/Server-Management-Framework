# Verbindliche Schemamigration

Quelle des Schemas ist `database/schema.json`; `bin/export-schema.php` erzeugt die SQL-Abbilder für alle drei SQL-Treiber. Die Abbilder enthalten keine Konten und sind kein Ersatz für die Anwendungsmigration.

Neue Installation: `php bin/install.php --username=<name> --email=<adresse>` mit Passwort über Standardeingabe. Bestehende Installation: gesicherten Datenbankstand auf einer Kopie prüfen, Verkehr und externe Schreiber anhalten und `php bin/migrate.php` ausführen. Details, Voraussetzungen und Wiederherstellung stehen in der [Betriebsanleitung](../docs/pruefung-2026-10-24/BETRIEB-AP10-AP17.md).

Die alte Anleitung zu CSV-Export/Import, Vollschemaüberschreibung und manuell einzusetzenden Beispieladministratoren ist ersetzt. MongoDB wird nicht unterstützt.
