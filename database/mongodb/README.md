# MongoDB-Datenbankstruktur für Server Management System

## Übersicht

Dieses Verzeichnis enthält die MongoDB-spezifische Datenbankstruktur für das Server Management System. MongoDB ist eine NoSQL-Datenbank, die sich besonders gut für flexible Schemas und horizontale Skalierung eignet.

## Dateien

### `database-structure-mongodb.js`
- **Ziel**: MongoDB (alle Versionen ab 3.6)
- **Beschreibung**: JavaScript-Datei mit MongoDB-spezifischer Struktur
- **Features**:
  - 25 Collections mit JSON Schema Validierung
  - Optimierte Indizes für Performance
  - Grunddaten für sofortige Nutzung
  - MongoDB-spezifische Datentypen

## MongoDB-spezifische Besonderheiten

### 1. Collections statt Tabellen
- **Collections**: Äquivalent zu SQL-Tabellen
- **Dokumente**: Äquivalent zu SQL-Zeilen
- **Felder**: Äquivalent zu SQL-Spalten

### 2. JSON Schema Validierung
- **Strenge Datentyp-Überprüfung**
- **Enum-Werte für bestimmte Felder**
- **Min/Max-Längen für Strings**
- **Regex-Patterns für E-Mail-Validierung**

### 3. Indizierung
- **Unique Indizes** für eindeutige Werte
- **Compound Indizes** für komplexe Abfragen
- **TTL-Indizes** für automatische Dokument-Löschung (optional)

### 4. Datentypen
- **ObjectId**: Automatisch generierte eindeutige IDs
- **Date**: Native MongoDB-Datums-Objekte
- **Arrays**: Für Tags und Namenserver
- **Objects**: Für zusätzliche Konfigurationen

## Installation

### Voraussetzungen
- MongoDB Server (Version 3.6 oder höher)
- MongoDB Shell (mongosh oder mongo)

### 1. MongoDB starten
```bash
# MongoDB als Service starten
sudo systemctl start mongod

# Oder MongoDB manuell starten
mongod --dbpath /var/lib/mongodb
```

### 2. Datenbankstruktur erstellen
```bash
# Mit MongoDB Shell (ältere Versionen)
mongo server_management < database-structure-mongodb.js

# Mit MongoDB Shell (neuere Versionen)
mongosh server_management < database-structure-mongodb.js

# Oder interaktiv
mongosh
use server_management
load("database-structure-mongodb.js")
```

### 3. Überprüfung
```javascript
// Datenbank auflisten
show dbs

// Collections auflisten
show collections

// Admin-Benutzer prüfen
db.users.findOne({username: "admin"})
```

## Standard-Zugangsdaten

**⚠️ WICHTIG: Diese Zugangsdaten sind nur für Tests gedacht und sollten nach der Installation sofort geändert werden!**

### Admin-Benutzer
- **Benutzername:** `admin`
- **E-Mail:** `admin@mongodb-server.com`
- **Passwort:** `password`
- **Rolle:** `admin`
- **Status:** `active`

### Passwort-Hash
Das Standardpasswort ist mit PHP's `password_hash()` gehasht:
```
$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
```

## MongoDB-spezifische Konfiguration

### 1. Verbindungs-String
```php
// In config/config.inc.php
const DB_TYPE = 'mongodb';
const DB_MONGODB_URI = 'mongodb://localhost:27017';
const DB_MONGODB_NAME = 'server_management';
```

### 2. PHP MongoDB Treiber
```bash
# MongoDB PHP Extension installieren
sudo pecl install mongodb

# Oder über Composer
composer require mongodb/mongodb
```

### 3. Verbindung testen
```php
<?php
try {
    $manager = new MongoDB\Driver\Manager("mongodb://localhost:27017");
    $query = new MongoDB\Driver\Query([]);
    $cursor = $manager->executeQuery("server_management.users", $query);
    echo "MongoDB-Verbindung erfolgreich!";
} catch (Exception $e) {
    echo "Fehler: " . $e->getMessage();
}
?>
```

## Performance-Optimierungen

### 1. Indizes
- **Unique Indizes** für eindeutige Felder
- **Compound Indizes** für häufige Abfragen
- **TTL-Indizes** für automatische Bereinigung

### 2. Aggregation Pipeline
```javascript
// Beispiel: Benutzeraktivitäten gruppieren
db.user_activities.aggregate([
    { $match: { created_at: { $gte: new Date(Date.now() - 24*60*60*1000) } } },
    { $group: { _id: "$user_id", activity_count: { $sum: 1 } } },
    { $sort: { activity_count: -1 } }
])
```

### 3. Sharding (für große Datenmengen)
```javascript
// Sharding aktivieren
sh.enableSharding("server_management")

// Shard-Key definieren
sh.shardCollection("server_management.users", { "username": 1 })
```

## Wartung und Backup

### 1. Backup erstellen
```bash
# Komplettes Backup
mongodump --db server_management --out /backup/

# Einzelne Collection
mongoexport --db server_management --collection users --out users.json
```

### 2. Backup wiederherstellen
```bash
# Komplettes Backup
mongorestore --db server_management /backup/server_management/

# Einzelne Collection
mongoimport --db server_management --collection users --file users.json
```

### 3. Datenbank-Statistiken
```javascript
// Collection-Statistiken
db.users.stats()

// Datenbank-Statistiken
db.stats()

// Index-Statistiken
db.users.getIndexes()
```

## Monitoring

### 1. MongoDB-Status
```javascript
// Server-Status
db.serverStatus()

// Aktuelle Operationen
db.currentOp()

// Datenbank-Profile
db.getProfilingStatus()
```

### 2. Performance-Metriken
```javascript
// Langsame Queries (wenn Profiling aktiviert)
db.system.profile.find({millis: {$gt: 100}}).sort({ts: -1})

// Index-Nutzung
db.users.aggregate([
    { $indexStats: {} }
])
```

## Troubleshooting

### Häufige Probleme

1. **Verbindungsfehler**
   - MongoDB-Service läuft nicht
   - Falsche Port-Nummer (Standard: 27017)
   - Firewall-Einstellungen

2. **Authentifizierungsfehler**
   - Benutzer existiert nicht
   - Falsche Berechtigungen
   - Authentication Database falsch

3. **Performance-Probleme**
   - Fehlende Indizes
   - Große Dokumente ohne Limitierung
   - Ineffiziente Abfragen

### Debugging
```javascript
// Debug-Modus aktivieren
db.setLogLevel(1)

// Queries mit explain() analysieren
db.users.find({username: "admin"}).explain("executionStats")
```

## Sicherheit

### 1. Authentifizierung
```javascript
// Benutzer erstellen
use admin
db.createUser({
    user: "admin",
    pwd: "sicheres_passwort",
    roles: ["userAdminAnyDatabase", "dbAdminAnyDatabase"]
})
```

### 2. Netzwerk-Sicherheit
```bash
# MongoDB nur lokal binden
mongod --bind_ip 127.0.0.1

# Oder in mongod.conf
net:
  bindIp: 127.0.0.1
```

### 3. SSL/TLS
```bash
# SSL-Zertifikat konfigurieren
mongod --sslMode requireSSL --sslPEMKeyFile /path/to/cert.pem
```

## Migration von SQL-Datenbanken

### 1. Daten exportieren
```bash
# MySQL zu JSON
mysqldump --single-transaction --routines --triggers --hex-blob \
  --complete-insert --extended-insert=FALSE \
  server_management > dump.sql
```

### 2. Daten transformieren
```bash
# SQL zu JSON konvertieren (benötigt spezielle Tools)
# Empfohlen: Manuelle Überprüfung der Datenstruktur
```

### 3. Daten importieren
```bash
# JSON-Dateien in MongoDB importieren
mongoimport --db server_management --collection users users.json
```

## Support

Bei Problemen oder Fragen:
- MongoDB-Logs prüfen: `/var/log/mongodb/`
- MongoDB-Dokumentation: https://docs.mongodb.com/
- Community-Forum: https://community.mongodb.com/

## Changelog

### Version 2.0 (2025-09-03)
- Vollständige MongoDB-Struktur erstellt
- JSON Schema Validierung implementiert
- Optimierte Indizes hinzugefügt
- Grunddaten für sofortige Nutzung
- Umfassende Dokumentation

### Version 1.0 (Original)
- Keine MongoDB-Unterstützung
