# Release bauen und veröffentlichen

`bin/build-release.py` liest ausschließlich die Dateiliste und Inhalte aus einem angegebenen Git-Commit. Ignorierte Betreiberdateien, uncommittete Änderungen und Git-Historie gelangen nicht in das Anwendungspaket. Voraussetzungen: Python 3, cryptography, Git und ein privat verwahrter RSA-Signierschlüssel mit mindestens 3072 Bit außerhalb des Checkout.

```bash
python3 bin/build-release.py --ref <vollständiger-commit> --key /privater/pfad/release-private.pem --output /privater/pfad/build-3.2.8
```

Ein zweiter Bau aus demselben Commit und Schlüssel muss identische Assets ergeben. Zeitstempel kommen aus dem Commit; JSON, ZIP-Reihenfolge und RSA-PKCS1v1.5/SHA-256-Signaturen sind deterministisch. Ergebnisse sind das Vollpaket, Manifest/Signatur, öffentlicher Schlüssel, Release Notes, Buildinformationen sowie signierte SHA-256-Prüfsummen. Private Schlüssel werden weder kopiert noch protokolliert.

Das Paket vor Veröffentlichung neu installieren und durch den echten UpdateManager mit separatem Privatverzeichnis und Datenbankkopie migrieren, aktivieren und rückrollen. Regressionen und GitHub-CI desselben Commits prüfen. Der CI-Job `release_package` baut mit einem kurzlebigen Testschlüssel zweimal und führt die echte Paketabnahme aus; der private Produktionssignierschlüssel wird nicht in CI hinterlegt. Danach den Tag auf genau diesen Commit setzen, zunächst einen Releaseentwurf erstellen, alle Assets hochladen und erst den vollständigen Entwurf veröffentlichen. `main` des separaten Entwicklungszweigs muss dafür nicht verschoben werden. Reale Serverbereitstellung und produktspezifische Providerabnahme bleiben ein eigener Schritt.

3.2.8 wird als Vollpaket ausgeliefert. Ein separates Kernpaket wird für den erstmaligen Wechsel vom ursprünglichen 3.2.7 nicht angeboten. Der integrierte Updater erfordert unabhängig vom Download die serverseitige Vertrauens-/Worker-/Symlink-Einrichtung. Der veröffentlichte Public-Key-Fingerabdruck muss beim ersten Einsatz unabhängig geprüft werden.
