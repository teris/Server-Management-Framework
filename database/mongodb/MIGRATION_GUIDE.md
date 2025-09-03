# MongoDB-Migrationsanleitung

## Übersicht

Diese Anleitung beschreibt den Prozess der Migration von SQL-Datenbanken (MySQL, PostgreSQL, SQLite) zu MongoDB für das Server Management System.

## Voraussetzungen

### 1. Systemanforderungen
- **MongoDB Server**: Version 3.6 oder höher
- **PHP**: Version 7.4 oder höher mit MongoDB Extension
- **Speicherplatz**: Mindestens 2x der aktuellen SQL-Datenbank
- **Backup**: Vollständiges Backup der bestehenden Datenbank

### 2. PHP MongoDB Extension
```bash
# Ubuntu/Debian
sudo apt-get install php-mongodb

# CentOS/RHEL
sudo yum install php-mongodb

# Über PECL
sudo pecl install mongodb

# Über Composer
composer require mongodb/mongodb
```

### 3. MongoDB Shell
```bash
# Ubuntu/Debian
sudo apt-get install mongodb-clients

# CentOS/RHEL
sudo yum install mongodb

# Über offizielle MongoDB-Repositorys
wget -qO - https://www.mongodb.org/static/pgp/server-6.0.asc | sudo apt-key add -
echo "deb [ arch=amd64,arm64 ] https://repo.mongodb.org/apt/ubuntu focal/mongodb-org/6.0 multiverse" | sudo tee /etc/apt/sources.list.d/mongodb-org-6.0.list
sudo apt-get update
sudo apt-get install mongodb-org-shell
```

## Migrationsstrategien

### Strategie 1: Vollständige Migration (Empfohlen)
- **Vorteile**: Saubere, neue Struktur
- **Nachteile**: Temporärer Ausfall des Systems
- **Dauer**: 2-4 Stunden
- **Risiko**: Mittel

### Strategie 2: Schrittweise Migration
- **Vorteile**: Minimale Ausfallzeiten
- **Nachteile**: Komplexere Implementierung
- **Dauer**: 1-2 Wochen
- **Risiko**: Niedrig

### Strategie 3: Hybrid-Ansatz
- **Vorteile**: Flexibilität, schrittweise Umstellung
- **Nachteile**: Doppelte Wartung
- **Dauer**: 2-4 Wochen
- **Risiko**: Niedrig

## Vollständige Migration (Strategie 1)

### Phase 1: Vorbereitung

#### 1.1 Backup erstellen
```bash
# MySQL
mysqldump -u root -p --single-transaction --routines --triggers \
  --hex-blob --complete-insert server_management > backup_mysql_$(date +%Y%m%d_%H%M%S).sql

# PostgreSQL
pg_dump -U postgres -h localhost -F c server_management > backup_postgresql_$(date +%Y%m%d_%H%M%S).dump

# SQLite
cp server_management.db backup_sqlite_$(date +%Y%m%d_%H%M%S).db
```

#### 1.2 Daten exportieren
```bash
# MySQL zu CSV
mysql -u root -p server_management -e "
SELECT 'id,username,email,password_hash,full_name,role,active,last_login,failed_login_attempts,locked_until,password_changed_at,created_at,updated_at'
UNION ALL
SELECT CONCAT_WS(',', id, username, email, password_hash, full_name, role, active, 
       COALESCE(last_login, ''), failed_login_attempts, COALESCE(locked_until, ''), 
       COALESCE(password_changed_at, ''), created_at, updated_at)
FROM users
INTO OUTFILE '/tmp/users.csv'
FIELDS TERMINATED BY ','
ENCLOSED BY '\"'
LINES TERMINATED BY '\n';"
```

#### 1.3 MongoDB vorbereiten
```bash
# MongoDB starten
sudo systemctl start mongod

# Datenbank erstellen
mongosh
use server_management
exit
```

### Phase 2: Struktur erstellen

#### 2.1 MongoDB-Struktur laden
```bash
# JavaScript-Datei ausführen
mongosh server_management < database-structure-mongodb.js
```

#### 2.2 Überprüfung
```javascript
// Collections auflisten
show collections

// Admin-Benutzer prüfen
db.users.findOne({username: "admin"})

// Indizes prüfen
db.users.getIndexes()
```

### Phase 3: Daten migrieren

#### 3.1 CSV zu JSON konvertieren
```bash
# Python-Skript für CSV zu JSON Konvertierung
cat > csv_to_json.py << 'EOF'
import csv
import json
import sys
from datetime import datetime

def csv_to_json(csv_file, json_file, collection_name):
    data = []
    
    with open(csv_file, 'r', encoding='utf-8') as file:
        csv_reader = csv.DictReader(file)
        
        for row in csv_reader:
            # Datumsfelder konvertieren
            for key, value in row.items():
                if 'date' in key.lower() or 'created' in key.lower() or 'updated' in key.lower():
                    if value and value.strip():
                        try:
                            # MySQL-Datumsformat parsen
                            dt = datetime.strptime(value, '%Y-%m-%d %H:%M:%S')
                            row[key] = dt.isoformat()
                        except:
                            row[key] = value
                    else:
                        row[key] = None
                
                # Numerische Felder konvertieren
                elif key in ['id', 'failed_login_attempts']:
                    if value and value.strip():
                        row[key] = int(value)
                    else:
                        row[key] = 0
            
            data.append(row)
    
    # JSON-Datei schreiben
    with open(json_file, 'w', encoding='utf-8') as file:
        json.dump(data, file, indent=2, ensure_ascii=False)
    
    print(f"Konvertiert: {len(data)} Datensätze von {csv_file} zu {json_file}")
    print(f"Collection: {collection_name}")

if __name__ == "__main__":
    if len(sys.argv) != 4:
        print("Verwendung: python csv_to_json.py <csv_file> <json_file> <collection_name>")
        sys.exit(1)
    
    csv_to_json(sys.argv[1], sys.argv[2], sys.argv[3])
EOF

# CSV zu JSON konvertieren
python3 csv_to_json.py /tmp/users.csv /tmp/users.json users
```

#### 3.2 JSON in MongoDB importieren
```bash
# Benutzer importieren
mongoimport --db server_management --collection users --file /tmp/users.json --mode upsert --upsertFields username

# Weitere Collections importieren
# ... (für jede Tabelle wiederholen)
```

#### 3.3 Datenvalidierung
```javascript
// Anzahl der Datensätze prüfen
db.users.countDocuments()

// Beispieldatensatz anzeigen
db.users.findOne()

// Duplikate prüfen
db.users.aggregate([
    { $group: { _id: "$username", count: { $sum: 1 } } },
    { $match: { count: { $gt: 1 } } }
])
```

### Phase 4: Anwendung aktualisieren

#### 4.1 Konfiguration ändern
```php
// config/config.inc.php
const DB_TYPE = 'mongodb';
const DB_MONGODB_URI = 'mongodb://localhost:27017';
const DB_MONGODB_NAME = 'server_management';
const DB_MONGODB_USER = '';
const DB_MONGODB_PASS = '';
```

#### 4.2 Datenbankverbindung aktualisieren
```php
// Beispiel für MongoDB-Verbindung
class MongoDBConnection {
    private $manager;
    private $database;
    
    public function __construct() {
        $this->manager = new MongoDB\Driver\Manager(DB_MONGODB_URI);
        $this->database = DB_MONGODB_NAME;
    }
    
    public function query($collection, $filter = [], $options = []) {
        $query = new MongoDB\Driver\Query($filter, $options);
        return $this->manager->executeQuery("{$this->database}.{$collection}", $query);
    }
    
    public function insert($collection, $document) {
        $bulk = new MongoDB\Driver\BulkWrite();
        $bulk->insert($document);
        return $this->manager->executeBulkWrite("{$this->database}.{$collection}", $bulk);
    }
    
    public function update($collection, $filter, $update, $options = []) {
        $bulk = new MongoDB\Driver\BulkWrite();
        $bulk->update($filter, $update, $options);
        return $this->manager->executeBulkWrite("{$this->database}.{$collection}", $bulk);
    }
    
    public function delete($collection, $filter) {
        $bulk = new MongoDB\Driver\BulkWrite();
        $bulk->delete($filter);
        return $this->manager->executeBulkWrite("{$this->database}.{$collection}", $bulk);
    }
}
```

#### 4.3 Queries anpassen
```php
// Alte SQL-Query
// SELECT * FROM users WHERE username = ? AND active = 'y'

// Neue MongoDB-Query
$filter = ['username' => $username, 'active' => 'y'];
$cursor = $db->query('users', $filter);
$user = $cursor->toArray()[0] ?? null;
```

### Phase 5: Testen und Validierung

#### 5.1 Funktionalität testen
```bash
# Anwendung starten
php -S localhost:8000

# Login testen
curl -X POST http://localhost:8000/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"password"}'
```

#### 5.2 Datenintegrität prüfen
```javascript
// Alle Collections durchgehen
db.getCollectionNames().forEach(function(collectionName) {
    print("Collection: " + collectionName);
    print("Anzahl Dokumente: " + db[collectionName].countDocuments());
    print("Beispieldokument:");
    printjson(db[collectionName].findOne());
    print("---");
});
```

#### 5.3 Performance testen
```javascript
// Query-Performance testen
db.users.find({username: "admin"}).explain("executionStats")

// Index-Nutzung prüfen
db.users.aggregate([
    { $indexStats: {} }
])
```

## Schrittweise Migration (Strategie 2)

### Phase 1: Hybrid-System einrichten

#### 1.1 Dual-Write implementieren
```php
class DualDatabaseManager {
    private $sqlDb;
    private $mongoDb;
    
    public function writeUser($userData) {
        // Beide Datenbanken beschreiben
        $sqlResult = $this->sqlDb->insertUser($userData);
        $mongoResult = $this->mongoDb->insertUser($userData);
        
        return $sqlResult && $mongoResult;
    }
    
    public function readUser($userId) {
        // Aus MongoDB lesen (schneller)
        $user = $this->mongoDb->getUser($userId);
        if (!$user) {
            // Fallback auf SQL
            $user = $this->sqlDb->getUser($userId);
        }
        return $user;
    }
}
```

#### 1.2 Daten-Synchronisation
```php
class DataSynchronizer {
    public function syncTable($tableName) {
        $sqlData = $this->sqlDb->getAll($tableName);
        $mongoData = $this->transformToMongo($sqlData);
        
        foreach ($mongoData as $document) {
            $this->mongoDb->upsert($tableName, $document);
        }
    }
    
    private function transformToMongo($sqlData) {
        // SQL-Daten in MongoDB-Format transformieren
        $mongoData = [];
        foreach ($sqlData as $row) {
            $mongoData[] = $this->transformRow($row);
        }
        return $mongoData;
    }
}
```

### Phase 2: Modulweise Migration

#### 2.1 Benutzer-Management migrieren
```php
// Nur Benutzer-bezogene Funktionen auf MongoDB umstellen
class UserManager {
    private $db;
    
    public function __construct() {
        // MongoDB für Benutzer
        $this->db = new MongoDBConnection();
    }
    
    public function createUser($userData) {
        return $this->db->insert('users', $userData);
    }
    
    public function getUser($userId) {
        $cursor = $this->db->query('users', ['_id' => new MongoDB\BSON\ObjectId($userId)]);
        return $cursor->toArray()[0] ?? null;
    }
}
```

#### 2.2 Weitere Module schrittweise migrieren
- Support-Tickets
- Domains
- Virtuelle Maschinen
- Websites

### Phase 3: Vollständige Umstellung

#### 3.1 SQL-Datenbank entfernen
```bash
# Nach erfolgreicher Migration
sudo systemctl stop mysql
sudo systemctl disable mysql
sudo apt-get remove mysql-server mysql-client
```

#### 3.2 Konfiguration bereinigen
```php
// Nur noch MongoDB-Konfiguration
const DB_TYPE = 'mongodb';
// SQL-spezifische Konstanten entfernen
```

## Hybrid-Ansatz (Strategie 3)

### Konzept
- **Lese-Operationen**: MongoDB (schneller)
- **Schreib-Operationen**: Beide Datenbanken
- **Migration**: Kontinuierlich im Hintergrund

### Implementierung
```php
class HybridDatabaseManager {
    public function read($collection, $filter) {
        // Immer aus MongoDB lesen
        return $this->mongoDb->query($collection, $filter);
    }
    
    public function write($collection, $data, $operation = 'insert') {
        // Beide Datenbanken beschreiben
        $mongoResult = $this->mongoDb->$operation($collection, $data);
        $sqlResult = $this->sqlDb->$operation($collection, $data);
        
        // MongoDB-Ergebnis zurückgeben
        return $mongoResult;
    }
    
    public function backgroundSync() {
        // Hintergrund-Synchronisation
        $this->syncNewData();
        $this->cleanupOldData();
    }
}
```

## Rollback-Plan

### Bei Problemen zurück zu SQL

#### 1. Konfiguration zurücksetzen
```php
// config/config.inc.php
const DB_TYPE = 'mysql';  // Zurück zu MySQL
// MongoDB-Konfiguration auskommentieren
```

#### 2. Datenbank wiederherstellen
```bash
# MySQL-Backup wiederherstellen
mysql -u root -p server_management < backup_mysql_$(date +%Y%m%d_%H%M%S).sql

# PostgreSQL-Backup wiederherstellen
pg_restore -U postgres -h localhost -d server_management backup_postgresql_$(date +%Y%m%d_%H%M%S).dump
```

#### 3. Anwendung neu starten
```bash
# Web-Server neu starten
sudo systemctl restart apache2
# oder
sudo systemctl restart nginx
```

## Monitoring während der Migration

### 1. Performance-Metriken
```javascript
// MongoDB-Performance überwachen
db.serverStatus()

// Aktuelle Operationen
db.currentOp()

// Langsame Queries
db.system.profile.find({millis: {$gt: 100}})
```

### 2. Datenintegrität
```javascript
// Dokumente zählen
db.users.countDocuments()

// Duplikate prüfen
db.users.aggregate([
    { $group: { _id: "$username", count: { $sum: 1 } } },
    { $match: { count: { $gt: 1 } } }
])
```

### 3. Logs überwachen
```bash
# MongoDB-Logs
tail -f /var/log/mongodb/mongod.log

# Anwendungs-Logs
tail -f /var/log/apache2/error.log
```

## Abschluss der Migration

### 1. Finale Validierung
- Alle Funktionen getestet
- Performance akzeptabel
- Datenintegrität bestätigt
- Backup der MongoDB-Datenbank erstellt

### 2. Dokumentation aktualisieren
- Neue MongoDB-Struktur dokumentiert
- Konfigurationsänderungen festgehalten
- Troubleshooting-Guide erstellt

### 3. Team schulen
- MongoDB-Grundlagen
- Neue Query-Syntax
- Monitoring und Wartung

## Häufige Probleme und Lösungen

### 1. Datentyp-Konvertierung
```php
// SQL INT zu MongoDB int
$mongoData['failed_login_attempts'] = (int) $sqlData['failed_login_attempts'];

// SQL DATETIME zu MongoDB Date
$mongoData['created_at'] = new MongoDB\BSON\UTCDateTime(
    strtotime($sqlData['created_at']) * 1000
);
```

### 2. Auto-Increment IDs
```php
// SQL AUTO_INCREMENT zu MongoDB ObjectId
$mongoData['_id'] = new MongoDB\BSON\ObjectId();
// Oder SQL-ID beibehalten
$mongoData['sql_id'] = (int) $sqlData['id'];
```

### 3. Transaktionen
```javascript
// MongoDB 4.0+ Transaktionen
const session = db.getMongo().startSession();
session.startTransaction();

try {
    // Operationen ausführen
    db.users.insertOne({...}, {session});
    db.customers.insertOne({...}, {session});
    
    session.commitTransaction();
} catch (error) {
    session.abortTransaction();
    throw error;
} finally {
    session.endSession();
}
```

## Support und Hilfe

### Bei Problemen
1. **Logs prüfen**: MongoDB und Anwendungs-Logs
2. **Datenbank-Status**: `db.serverStatus()`
3. **Community-Forum**: https://community.mongodb.com/
4. **Dokumentation**: https://docs.mongodb.com/

### Nützliche Tools
- **MongoDB Compass**: GUI für MongoDB
- **Studio 3T**: Alternative GUI
- **MongoDB Charts**: Visualisierung
- **MongoDB Ops Manager**: Monitoring und Backup

## Fazit

Die Migration zu MongoDB bietet:
- **Bessere Performance** für komplexe Abfragen
- **Flexiblere Schemas** für zukünftige Anforderungen
- **Horizontale Skalierung** für wachsende Datenmengen
- **Moderne NoSQL-Features** wie Aggregation Pipeline

Mit der richtigen Planung und Ausführung ist die Migration ein wichtiger Schritt zur Modernisierung des Systems.
