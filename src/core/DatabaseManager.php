<?php
if (PHP_SAPI !== 'cli' && (get_included_files()[0] ?? null) === __FILE__) { http_response_code(404); exit; }
require_once dirname(__DIR__, 2) . '/config/load.php';

/** Bind integer/boolean values consistently, including native PostgreSQL/MySQL statements. */
class PortableStatement extends PDOStatement {
    protected function __construct() {}
    public function execute(?array $params = null): bool {
        if ($params !== null) foreach ($params as $key => $value) {
            if (is_bool($value)) $value = (int) $value;
            if (is_array($value)) $value = json_encode($value, JSON_THROW_ON_ERROR);
            if (is_object($value)) throw new InvalidArgumentException('Unsupported SQL parameter value');
            $this->bindValue(is_int($key) ? $key + 1 : $key, $value, is_int($value) ? PDO::PARAM_INT : ($value === null ? PDO::PARAM_NULL : PDO::PARAM_STR));
        }
        return parent::execute();
    }
}

/** Only SQL engines with an actual PDO implementation are supported. */
class PortablePDO extends PDO {
    public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null) {
        $options ??= []; $options[PDO::ATTR_STATEMENT_CLASS] = [PortableStatement::class, []];
        parent::__construct($dsn, $username, $password, $options);
    }
    private function portable(string $sql): string {
        $driver = $this->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'pgsql') {
            $segments = preg_split('/("(?:[^"]|"")*"|\'(?:[^\']|\'\')*\'|`[^`]*`)/', $sql, -1, PREG_SPLIT_DELIM_CAPTURE);
            foreach ($segments as $index => &$segment) if ($index % 2 === 0) $segment = preg_replace('/\bsystem_user\b/i', '"system_user"', $segment);
            unset($segment); $sql = implode('', $segments);
        }
        // Legacy SQL identifiers; values must continue to use bound parameters.
        if ($driver !== 'mysql') $sql = preg_replace('/`([a-zA-Z_][a-zA-Z0-9_]*)`/', '"$1"', $sql);
        $quote = $driver === 'mysql' ? '`groups`' : '"groups"';
        return preg_replace('/\b(FROM|JOIN|INTO|UPDATE|TABLE)\s+groups\b/i', '$1 ' . $quote, $sql);
    }
    public function prepare(string $query, array $options = []): PDOStatement|false { return parent::prepare($this->portable($query), $options); }
    public function exec(string $statement): int|false { return parent::exec($this->portable($statement)); }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        return $fetchMode === null ? parent::query($this->portable($query)) : parent::query($this->portable($query), $fetchMode, ...$fetchModeArgs);
    }
    public function lastInsertId(?string $name = null): string|false {
        if ($name === null && $this->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') return (string) parent::query('SELECT LASTVAL()')->fetchColumn();
        return parent::lastInsertId($name);
    }
}

class DatabaseDriver {
    protected ?PDO $connection = null;
    public function __construct(string $type) {
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
        $user = $password = null;
        if ($type === 'mysql') {
            if (!Config::DB_USEING) throw new RuntimeException('MySQL/MariaDB ist deaktiviert');
            $dsn = defined('Config::DB_SOCKET') && Config::DB_SOCKET !== '' ? 'mysql:unix_socket=' . Config::DB_SOCKET . ';dbname=' . Config::DB_NAME . ';charset=utf8mb4' : 'mysql:host=' . Config::DB_HOST . ';port=' . Config::DB_PORT . ';dbname=' . Config::DB_NAME . ';charset=utf8mb4';
            $user = Config::DB_USER; $password = Config::DB_PASS; $options[PDO::ATTR_EMULATE_PREPARES] = false;
        } elseif ($type === 'pgsql') {
            if (!Config::DB_PGSQL_USEING) throw new RuntimeException('PostgreSQL ist deaktiviert');
            $dsn = 'pgsql:host=' . Config::DB_PGSQL_HOST . ';port=' . Config::DB_PGSQL_PORT . ';dbname=' . Config::DB_PGSQL_NAME;
            $user = Config::DB_PGSQL_USER; $password = Config::DB_PGSQL_PASS; $options[PDO::ATTR_EMULATE_PREPARES] = false;
        } elseif ($type === 'sqlite') {
            if (!Config::DB_SQLITE_USEING) throw new RuntimeException('SQLite ist deaktiviert');
            $path = Config::DB_SQLITE_PATH;
            if ($path === '') throw new RuntimeException('SQLite benötigt einen Dateipfad');
            if ($path !== ':memory:' && $path[0] !== '/' && !preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) $path = dirname(__DIR__, 2) . '/' . $path;
            if ($path !== ':memory:' && !is_dir(dirname($path)) && !mkdir(dirname($path), 0700, true)) throw new RuntimeException('SQLite-Verzeichnis nicht verfügbar');
            $dsn = 'sqlite:' . $path;
        } else { throw new RuntimeException('Nicht unterstützter Datenbanktyp: ' . $type); }
        if (!in_array($type, PDO::getAvailableDrivers(), true)) throw new RuntimeException('Fehlender PDO-Treiber: ' . $type);
        try { $this->connection = new PortablePDO($dsn, $user, $password, $options); }
        catch (PDOException $e) { throw new RuntimeException('Datenbankverbindung fehlgeschlagen (' . $type . ')'); }
        if ($type === 'sqlite') {
            $this->connection->exec('PRAGMA foreign_keys = ON');
            $this->connection->exec('PRAGMA journal_mode = WAL');
            $this->connection->exec('PRAGMA busy_timeout = 10000');
        }
    }
    public function connect() { return $this->isConnected(); }
    public function disconnect() { $this->connection = null; }
    public function getConnection() { return $this->connection; }
    public function isConnected() { return $this->connection instanceof PDO; }
    public function prepare($sql) { return $this->connection->prepare($sql); }
    public function query($sql, $params = []) { $stmt = $this->prepare($sql); $this->execute($stmt, $params); return $stmt; }
    public function execute($stmt, $params = []) {
        if (!$stmt instanceof PDOStatement) throw new InvalidArgumentException('execute benötigt ein vorbereitetes Statement');
        $params = array_map(function ($value) { return is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : (is_bool($value) ? (int) $value : $value); }, $params);
        foreach ($params as $key => $value) $stmt->bindValue(is_int($key) ? $key + 1 : $key, $value, is_int($value) ? PDO::PARAM_INT : (is_bool($value) ? PDO::PARAM_BOOL : ($value === null ? PDO::PARAM_NULL : PDO::PARAM_STR)));
        return $stmt->execute();
    }
    public function fetch($stmt, $mode = null) { return $stmt->fetch($mode ?? PDO::FETCH_ASSOC); }
    public function fetchAll($stmt, $mode = null) { return $stmt->fetchAll($mode ?? PDO::FETCH_ASSOC); }
    public function lastInsertId() { return $this->connection->lastInsertId(); }
    public function beginTransaction() { return $this->connection->beginTransaction(); }
    public function commit() { return $this->connection->commit(); }
    public function rollback() { return $this->connection->rollBack(); }
    public function errorInfo() { return $this->connection->errorInfo(); }
}
class MySQLDriver extends DatabaseDriver { public function __construct() { parent::__construct('mysql'); } }
class PostgreSQLDriver extends DatabaseDriver { public function __construct() { parent::__construct('pgsql'); } }
class SQLiteDriver extends DatabaseDriver { public function __construct() { parent::__construct('sqlite'); } }

class DatabaseManager {
    private static ?self $instance = null;
    private DatabaseDriver $driver;
    private string $driverType;
    private function __construct() {
        $type = strtolower(Config::DB_TYPE);
        if ($type === 'mongodb') throw new RuntimeException('MongoDB wird nicht unterstützt. Keine Datenoperation wurde ausgeführt.');
        $this->driverType = ['mariadb' => 'mysql', 'postgresql' => 'pgsql'][$type] ?? $type;
        $class = ['mysql' => MySQLDriver::class, 'pgsql' => PostgreSQLDriver::class, 'sqlite' => SQLiteDriver::class][$this->driverType] ?? null;
        if (!$class) throw new RuntimeException('Nicht unterstützter Datenbanktyp: ' . $type);
        $this->driver = new $class();
    }
    public static function getInstance() { return self::$instance ??= new self(); }
    public function getDriver() { return $this->driver; }
    public function getDriverType() { return $this->driverType; }
    public function getConnection() { return $this->driver->getConnection(); }
    public function query($sql, $params = []) { return $this->driver->query($sql, $params); }
    public function prepare($sql) { return $this->driver->prepare($sql); }
    public function execute($stmt, $params = []) { return $this->driver->execute($stmt, $params); }
    public function fetch($stmt, $mode = null) { return $this->driver->fetch($stmt, $mode); }
    public function fetchAll($stmt, $mode = null) { return $this->driver->fetchAll($stmt, $mode); }
    public function lastInsertId() { return $this->driver->lastInsertId(); }
    public function beginTransaction() { return $this->driver->beginTransaction(); }
    public function commit() { return $this->driver->commit(); }
    public function rollback() { return $this->driver->rollback(); }
    public function isConnected() { return $this->driver->isConnected(); }
    public function isMongoDB() { return false; }
    public function isSQLite() { return $this->driverType === 'sqlite'; }
    public function isPostgreSQL() { return $this->driverType === 'pgsql'; }
    public function isMySQL() { return $this->driverType === 'mysql'; }
    public function logAction($action, $details, $status) { return $this->query('INSERT INTO activity_log (action, details, status) VALUES (?, ?, ?)', [$action, $details, $status])->rowCount() === 1; }
    public function getActivityLog($limit = 50, $offset = 0) {
        $stmt = $this->query('SELECT id, action, details, status, created_at FROM activity_log ORDER BY created_at DESC LIMIT ? OFFSET ?', [max(1, min(1000, (int) $limit)), max(0, (int) $offset)]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) $row['created_at_formatted'] = date('d.m.Y H:i:s', strtotime($row['created_at']));
        return $rows;
    }
    public function clearActivityLogs() { $this->getConnection()->exec('DELETE FROM activity_log'); return true; }
}
