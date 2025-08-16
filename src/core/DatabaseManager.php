<?php
/**
 * Database Manager - Flexible Datenbankabstraktion für verschiedene Datenbanktypen
 * Unterstützt: MySQL, MariaDB, PostgreSQL, SQLite, MongoDB
 */

require_once __DIR__ . '/../../config/config.inc.php';

abstract class DatabaseDriver {
    protected $connection;
    protected $config;
    
    abstract public function connect();
    abstract public function disconnect();
    abstract public function query($sql, $params = []);
    abstract public function prepare($sql);
    abstract public function execute($stmt, $params = []);
    abstract public function fetch($stmt, $mode = null);
    abstract public function fetchAll($stmt, $mode = null);
    abstract public function lastInsertId();
    abstract public function beginTransaction();
    abstract public function commit();
    abstract public function rollback();
    abstract public function errorInfo();
    
    public function isConnected() {
        return $this->connection !== null;
    }
    
    public function getConnection() {
        return $this->connection;
    }
}

class MySQLDriver extends DatabaseDriver {
    public function connect() {
        try {
            $dsn = "mysql:host=" . Config::DB_HOST . 
                   ";port=" . Config::DB_PORT . 
                   ";dbname=" . Config::DB_NAME . 
                   ";charset=" . Config::DB_CHARSET;
            
            $this->connection = new PDO($dsn, Config::DB_USER, Config::DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . Config::DB_CHARSET,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            
            return true;
        } catch (PDOException $e) {
            throw new Exception("MySQL Verbindung fehlgeschlagen: " . $e->getMessage());
        }
    }
    
    public function disconnect() {
        $this->connection = null;
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->prepare($sql);
        return $this->execute($stmt, $params);
    }
    
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }
    
    public function execute($stmt, $params = []) {
        return $stmt->execute($params);
    }
    
    public function fetch($stmt, $mode = null) {
        return $stmt->fetch($mode ?: PDO::FETCH_ASSOC);
    }
    
    public function fetchAll($stmt, $mode = null) {
        return $stmt->fetchAll($mode ?: PDO::FETCH_ASSOC);
    }
    
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    public function commit() {
        return $this->connection->commit();
    }
    
    public function rollback() {
        return $this->connection->rollback();
    }
    
    public function errorInfo() {
        return $this->connection->errorInfo();
    }
}

class PostgreSQLDriver extends DatabaseDriver {
    public function connect() {
        try {
            $dsn = "pgsql:host=" . Config::DB_PGSQL_HOST . 
                   ";port=" . Config::DB_PGSQL_PORT . 
                   ";dbname=" . Config::DB_PGSQL_NAME;
            
            $this->connection = new PDO($dsn, Config::DB_PGSQL_USER, Config::DB_PGSQL_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            
            return true;
        } catch (PDOException $e) {
            throw new Exception("PostgreSQL Verbindung fehlgeschlagen: " . $e->getMessage());
        }
    }
    
    public function disconnect() {
        $this->connection = null;
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->prepare($sql);
        return $this->execute($stmt, $params);
    }
    
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }
    
    public function execute($stmt, $params = []) {
        return $stmt->execute($params);
    }
    
    public function fetch($stmt, $mode = null) {
        return $stmt->fetch($mode ?: PDO::FETCH_ASSOC);
    }
    
    public function fetchAll($stmt, $mode = null) {
        return $stmt->fetchAll($mode ?: PDO::FETCH_ASSOC);
    }
    
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    public function commit() {
        return $this->connection->commit();
    }
    
    public function rollback() {
        return $this->connection->rollback();
    }
    
    public function errorInfo() {
        return $this->connection->errorInfo();
    }
}

class SQLiteDriver extends DatabaseDriver {
    public function connect() {
        try {
            $dbPath = __DIR__ . '/../../' . Config::DB_SQLITE_PATH;
            $dbDir = dirname($dbPath);
            
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0755, true);
            }
            
            $this->connection = new PDO("sqlite:" . $dbPath, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            
            // SQLite-spezifische Einstellungen
            $this->connection->exec("PRAGMA foreign_keys = ON");
            $this->connection->exec("PRAGMA journal_mode = WAL");
            
            return true;
        } catch (PDOException $e) {
            throw new Exception("SQLite Verbindung fehlgeschlagen: " . $e->getMessage());
        }
    }
    
    public function disconnect() {
        $this->connection = null;
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->prepare($sql);
        return $this->execute($stmt, $params);
    }
    
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }
    
    public function execute($stmt, $params = []) {
        return $stmt->execute($params);
    }
    
    public function fetch($stmt, $mode = null) {
        return $stmt->fetch($mode ?: PDO::FETCH_ASSOC);
    }
    
    public function fetchAll($stmt, $mode = null) {
        return $stmt->fetchAll($mode ?: PDO::FETCH_ASSOC);
    }
    
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    public function commit() {
        return $this->connection->commit();
    }
    
    public function rollback() {
        return $this->connection->rollback();
    }
    
    public function errorInfo() {
        return $this->connection->errorInfo();
    }
}

class MongoDBDriver extends DatabaseDriver {
    /** @var \MongoDB\Client|null */
    protected $connection;
    
    public function connect() {
        try {
            if (!class_exists('MongoDB\Client')) {
                throw new Exception("MongoDB PHP Extension ist nicht installiert");
            }
            
            $connectionString = "mongodb://";
            if (!empty(Config::DB_MONGO_USER) && !empty(Config::DB_MONGO_PASS)) {
                $connectionString .= Config::DB_MONGO_USER . ":" . Config::DB_MONGO_PASS . "@";
            }
            $connectionString .= Config::DB_MONGO_HOST . ":" . Config::DB_MONGO_PORT;
            
            $this->connection = new \MongoDB\Client($connectionString);
            $this->connection->listDatabases(); // Test der Verbindung
            
            return true;
        } catch (Exception $e) {
            throw new Exception("MongoDB Verbindung fehlgeschlagen: " . $e->getMessage());
        }
    }
    
    public function disconnect() {
        $this->connection = null;
    }
    
    public function query($sql, $params = []) {
        // MongoDB verwendet keine SQL-Syntax
        throw new Exception("MongoDB unterstützt keine SQL-Abfragen");
    }
    
    public function prepare($sql) {
        // MongoDB verwendet keine Prepared Statements
        throw new Exception("MongoDB unterstützt keine Prepared Statements");
    }
    
    public function execute($stmt, $params = []) {
        // MongoDB-spezifische Implementierung
        return true;
    }
    
    public function fetch($stmt, $mode = null) {
        // MongoDB-spezifische Implementierung
        return null;
    }
    
    public function fetchAll($stmt, $mode = null) {
        // MongoDB-spezifische Implementierung
        return [];
    }
    
    public function lastInsertId() {
        // MongoDB-spezifische Implementierung
        return null;
    }
    
    public function beginTransaction() {
        // MongoDB unterstützt Transaktionen ab Version 4.0
        return true;
    }
    
    public function commit() {
        return true;
    }
    
    public function rollback() {
        return true;
    }
    
    public function errorInfo() {
        return [];
    }
    
    // MongoDB-spezifische Methoden
    public function getDatabase() {
        return $this->connection->selectDatabase(Config::DB_MONGO_NAME);
    }
    
    public function getCollection($collectionName) {
        return $this->getDatabase()->selectCollection($collectionName);
    }
}

class DatabaseManager {
    private static $instance = null;
    private $driver;
    private $driverType;
    
    private function __construct() {
        $this->driverType = strtolower(Config::DB_TYPE);
        $this->initializeDriver();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function initializeDriver() {
        switch ($this->driverType) {
            case 'mysql':
            case 'mariadb':
                if (!Config::DB_USEING) {
                    throw new Exception("MySQL/MariaDB ist in der Konfiguration deaktiviert");
                }
                $this->driver = new MySQLDriver();
                break;
                
            case 'pgsql':
            case 'postgresql':
                if (!Config::DB_PGSQL_USEING) {
                    throw new Exception("PostgreSQL ist in der Konfiguration deaktiviert");
                }
                $this->driver = new PostgreSQLDriver();
                break;
                
            case 'sqlite':
                if (!Config::DB_SQLITE_USEING) {
                    throw new Exception("SQLite ist in der Konfiguration deaktiviert");
                }
                $this->driver = new SQLiteDriver();
                break;
                
            case 'mongodb':
                if (!Config::DB_MONGO_USEING) {
                    throw new Exception("MongoDB ist in der Konfiguration deaktiviert");
                }
                $this->driver = new MongoDBDriver();
                break;
                
            default:
                throw new Exception("Unbekannter Datenbanktyp: " . $this->driverType);
        }
        
        $this->driver->connect();
    }
    
    public function getDriver() {
        return $this->driver;
    }
    
    public function getDriverType() {
        return $this->driverType;
    }
    
    // Proxy-Methoden für einfache Verwendung
    public function query($sql, $params = []) {
        return $this->driver->query($sql, $params);
    }
    
    public function prepare($sql) {
        return $this->driver->prepare($sql);
    }
    
    public function execute($stmt, $params = []) {
        return $this->driver->execute($stmt, $params);
    }
    
    public function fetch($stmt, $mode = null) {
        return $this->driver->fetch($stmt, $mode);
    }
    
    public function fetchAll($stmt, $mode = null) {
        return $this->driver->fetchAll($stmt, $mode);
    }
    
    public function lastInsertId() {
        return $this->driver->lastInsertId();
    }
    
    public function beginTransaction() {
        return $this->driver->beginTransaction();
    }
    
    public function commit() {
        return $this->driver->commit();
    }
    
    public function rollback() {
        return $this->driver->rollback();
    }
    
    public function isConnected() {
        return $this->driver->isConnected();
    }
    
    public function getConnection() {
        return $this->driver->getConnection();
    }
    
    // Spezielle Methoden für verschiedene Datenbanktypen
    public function isMongoDB() {
        return $this->driverType === 'mongodb';
    }
    
    public function isSQLite() {
        return $this->driverType === 'sqlite';
    }
    
    public function isPostgreSQL() {
        return $this->driverType === 'pgsql' || $this->driverType === 'postgresql';
    }
    
    public function isMySQL() {
        return $this->driverType === 'mysql' || $this->driverType === 'mariadb';
    }
    
    // Kompatibilitätsmethoden für bestehenden Code
    public function logAction($action, $details, $status) {
        try {
            if ($this->isMongoDB()) {
                // MongoDB-spezifische Implementierung
                $collection = $this->driver->getCollection('activity_log');
                $document = [
                    'action' => $action,
                    'details' => $details,
                    'status' => $status,
                    'created_at' => new \MongoDB\BSON\UTCDateTime()
                ];
                $result = $collection->insertOne($document);
                return $result->getInsertedCount() > 0;
            } else {
                // SQL-basierte Implementierung
                $stmt = $this->prepare("INSERT INTO activity_log (action, details, status, created_at) VALUES (?, ?, ?, NOW())");
                return $this->execute($stmt, [$action, $details, $status]);
            }
        } catch (Exception $e) {
            error_log("Database logAction error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getActivityLog($limit = 50, $offset = 0) {
        try {
            if ($this->isMongoDB()) {
                // MongoDB-spezifische Implementierung
                $collection = $this->driver->getCollection('activity_log');
                $cursor = $collection->find(
                    [],
                    [
                        'sort' => ['created_at' => -1],
                        'limit' => $limit,
                        'skip' => $offset
                    ]
                );
                
                $results = [];
                foreach ($cursor as $document) {
                    $results[] = [
                        'id' => (string)$document['_id'],
                        'action' => $document['action'],
                        'details' => $document['details'],
                        'status' => $document['status'],
                        'created_at' => $document['created_at']->toDateTime()->format('Y-m-d H:i:s'),
                        'created_at_formatted' => $document['created_at']->toDateTime()->format('d.m.Y H:i:s')
                    ];
                }
                return $results;
            } else {
                // SQL-basierte Implementierung
                $limit = (int) $limit;
                $offset = (int) $offset;
                if ($limit <= 0) $limit = 50;
                if ($limit > 1000) $limit = 1000;
                if ($offset < 0) $offset = 0;
                
                $sql = "SELECT id, action, details, status, created_at 
                        FROM activity_log 
                        ORDER BY created_at DESC 
                        LIMIT $limit OFFSET $offset";
                $stmt = $this->prepare($sql);
                $this->execute($stmt);
                $results = $this->fetchAll($stmt);
                
                foreach ($results as &$row) {
                    if (isset($row['created_at'])) {
                        $row['created_at_formatted'] = date('d.m.Y H:i:s', strtotime($row['created_at']));
                    }
                }
                return $results;
            }
        } catch (Exception $e) {
            error_log("Database getActivityLog error: " . $e->getMessage());
            return [];
        }
    }
    
    public function clearActivityLogs() {
        try {
            if ($this->isMongoDB()) {
                $collection = $this->driver->getCollection('activity_log');
                $result = $collection->deleteMany([]);
                return $result->getDeletedCount() > 0;
            } else {
                $stmt = $this->prepare("TRUNCATE TABLE activity_log");
                return $this->execute($stmt);
            }
        } catch (Exception $e) {
            error_log("Database clearActivityLogs error: " . $e->getMessage());
            return false;
        }
    }
    
    public function __destruct() {
        if ($this->driver) {
            $this->driver->disconnect();
        }
    }
}
