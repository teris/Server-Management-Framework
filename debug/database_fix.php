<?php
/**
 * Database Fix - Korrigiert SQL-Syntaxfehler in der Database-Klasse
 * Überschreibt die fehlerhafte getActivityLog Methode
 */

// =============================================================================
// KORRIGIERTE DATABASE CLASS
// =============================================================================
class DatabaseFixed {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . Config::DB_HOST . ";dbname=" . Config::DB_NAME,
                Config::DB_USER,
                Config::DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
                ]
            );
        } catch(PDOException $e) {
            throw new Exception("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function logAction($action, $details, $status) {
        try {
            $stmt = $this->connection->prepare("INSERT INTO activity_log (action, details, status, created_at) VALUES (?, ?, ?, NOW())");
            return $stmt->execute([$action, $details, $status]);
        } catch (PDOException $e) {
            error_log("Database logAction error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * KORRIGIERTE getActivityLog Methode
     * Problem: LIMIT mit Platzhalter funktioniert nicht bei allen MariaDB Versionen
     */
    public function getActivityLog($limit = 50) {
        try {
            // Limit als Integer validieren und direkt in Query einbauen
            $limit = (int) $limit;
            if ($limit <= 0) $limit = 50;
            if ($limit > 1000) $limit = 1000; // Max. Limit für Performance
            
            // Query ohne Platzhalter für LIMIT
            $sql = "SELECT id, action, details, status, created_at 
                    FROM activity_log 
                    ORDER BY created_at DESC 
                    LIMIT " . $limit;
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute();
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Sicherstellen dass created_at im richtigen Format ist
            foreach ($results as &$row) {
                if (isset($row['created_at'])) {
                    // Timestamp zu deutschem Format konvertieren falls nötig
                    $row['created_at_formatted'] = date('d.m.Y H:i:s', strtotime($row['created_at']));
                }
            }
            
            return $results;
            
        } catch (PDOException $e) {
            error_log("Database getActivityLog error: " . $e->getMessage());
            
            // Fallback: Versuche ohne LIMIT
            try {
                $stmt = $this->connection->prepare("SELECT id, action, details, status, created_at FROM activity_log ORDER BY created_at DESC");
                $stmt->execute();
                $all_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Manuell limitieren
                return array_slice($all_results, 0, $limit);
                
            } catch (PDOException $e2) {
                error_log("Database fallback error: " . $e2->getMessage());
                return [];
            }
        }
    }
    
    /**
     * Zusätzliche Debug-Methode
     */
    public function testActivityLog() {
        try {
            // 1. Tabelle existiert?
            $stmt = $this->connection->query("SHOW TABLES LIKE 'activity_log'");
            if ($stmt->rowCount() == 0) {
                return [
                    'success' => false,
                    'error' => 'Tabelle activity_log existiert nicht',
                    'fix' => 'Führe database-structure.sql aus'
                ];
            }
            
            // 2. Spalten prüfen
            $stmt = $this->connection->query("DESCRIBE activity_log");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $required_columns = ['id', 'action', 'details', 'status', 'created_at'];
            $missing_columns = array_diff($required_columns, $columns);
            
            if (!empty($missing_columns)) {
                return [
                    'success' => false,
                    'error' => 'Fehlende Spalten: ' . implode(', ', $missing_columns),
                    'fix' => 'Tabelle neu erstellen oder ALTER TABLE ausführen'
                ];
            }
            
            // 3. Eintrag einfügen testen
            $this->logAction('Database Test', 'Test-Eintrag für Debugging', 'success');
            
            // 4. Einträge zählen
            $stmt = $this->connection->query("SELECT COUNT(*) as count FROM activity_log");
            $count = $stmt->fetch()['count'];
            
            // 5. Letzten Eintrag abrufen
            $stmt = $this->connection->query("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 1");
            $last_entry = $stmt->fetch();
            
            return [
                'success' => true,
                'total_entries' => $count,
                'last_entry' => $last_entry,
                'columns' => $columns
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'sql_state' => $e->getCode()
            ];
        }
    }
    
    /**
     * Activity Log Tabelle reparieren
     */
    public function repairActivityLog() {
        try {
            // Tabelle löschen und neu erstellen
            $this->connection->exec("DROP TABLE IF EXISTS activity_log");
            
            $sql = "
            CREATE TABLE activity_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                action VARCHAR(255) NOT NULL,
                details TEXT,
                status ENUM('success', 'error', 'pending') NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_action (action),
                INDEX idx_status (status),
                INDEX idx_created_at (created_at)
            )";
            
            $this->connection->exec($sql);
            
            // Test-Einträge einfügen
            $this->logAction('Database Repair', 'Activity Log Tabelle repariert', 'success');
            $this->logAction('Test Entry 1', 'Test-Eintrag zur Überprüfung', 'success');
            $this->logAction('Test Entry 2', 'Zweiter Test-Eintrag', 'success');
            
            return [
                'success' => true,
                'message' => 'Activity Log Tabelle erfolgreich repariert',
                'test_entries_added' => 3
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Fehler beim Reparieren: ' . $e->getMessage()
            ];
        }
    }
}

// =============================================================================
// SOFORT-TEST FUNKTIONEN
// =============================================================================

function testDatabaseFix() {
    require_once '../framework.php';
    
    echo "<h2>🔧 Database Fix Test</h2>";
    echo "<style>body{font-family:Arial;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} pre{background:#f5f5f5;padding:10px;}</style>";
    
    try {
        // Korrigierte Database-Instanz verwenden
        $db = DatabaseFixed::getInstance();
        
        echo "<h3 class='info'>1. Datenbank-Verbindung testen...</h3>";
        $connection = $db->getConnection();
        echo "<p class='success'>✅ Verbindung erfolgreich</p>";
        
        echo "<h3 class='info'>2. Activity Log Tabelle testen...</h3>";
        $test_result = $db->testActivityLog();
        
        if ($test_result['success']) {
            echo "<p class='success'>✅ Activity Log Tabelle OK</p>";
            echo "<p>Gesamt-Einträge: " . $test_result['total_entries'] . "</p>";
            echo "<p>Spalten: " . implode(', ', $test_result['columns']) . "</p>";
            
            if (isset($test_result['last_entry'])) {
                echo "<h4>Letzter Eintrag:</h4>";
                echo "<pre>" . print_r($test_result['last_entry'], true) . "</pre>";
            }
            
        } else {
            echo "<p class='error'>❌ " . $test_result['error'] . "</p>";
            if (isset($test_result['fix'])) {
                echo "<p class='info'>💡 Fix: " . $test_result['fix'] . "</p>";
            }
            
            // Auto-Repair anbieten
            echo "<h3 class='info'>3. Auto-Repair versuchen...</h3>";
            if (isset($_GET['repair'])) {
                $repair_result = $db->repairActivityLog();
                
                if ($repair_result['success']) {
                    echo "<p class='success'>✅ " . $repair_result['message'] . "</p>";
                    echo "<p>Test-Einträge hinzugefügt: " . $repair_result['test_entries_added'] . "</p>";
                } else {
                    echo "<p class='error'>❌ " . $repair_result['error'] . "</p>";
                }
            } else {
                echo "<a href='?repair=1' style='background:#007acc;color:white;padding:10px;text-decoration:none;border-radius:3px;'>🔧 Tabelle reparieren</a>";
            }
        }
        
        echo "<h3 class='info'>4. getActivityLog Methode testen...</h3>";
        $logs = $db->getActivityLog(5);
        
        if (!empty($logs)) {
            echo "<p class='success'>✅ getActivityLog funktioniert - " . count($logs) . " Einträge abgerufen</p>";
            echo "<h4>Letzte 5 Einträge:</h4>";
            echo "<pre>" . print_r($logs, true) . "</pre>";
        } else {
            echo "<p class='error'>❌ getActivityLog gibt leeres Array zurück</p>";
        }
        
    } catch (Exception $e) {
        echo "<p class='error'>❌ Kritischer Fehler: " . $e->getMessage() . "</p>";
        echo "<p class='info'>Datei: " . $e->getFile() . " Zeile: " . $e->getLine() . "</p>";
    }
    
    echo "<hr>";
    echo "<h3>🛠️ Manuelle Fixes:</h3>";
    echo "<p><strong>Option 1:</strong> <a href='?repair=1'>🔧 Auto-Repair ausführen</a></p>";
    echo "<p><strong>Option 2:</strong> database-structure.sql neu importieren</p>";
    echo "<p><strong>Option 3:</strong> Korrigierte Database-Klasse in framework.php ersetzen</p>";
}

// =============================================================================
// WEB INTERFACE
// =============================================================================

if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    if (isset($_GET['test'])) {
        testDatabaseFix();
    } else {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Database Fix Tool</title>
            <style>
                body { font-family: Arial; margin: 20px; background: #f5f5f5; }
                .container { background: white; padding: 30px; border-radius: 10px; max-width: 800px; margin: 0 auto; }
                .btn { background: #007acc; color: white; padding: 12px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px 5px; }
                .btn:hover { background: #005a9e; }
                .error { background: #ffe6e6; padding: 15px; border-left: 4px solid #ff4444; margin: 10px 0; }
                .success { background: #e6ffe6; padding: 15px; border-left: 4px solid #44ff44; margin: 10px 0; }
                .code { background: #f0f0f0; padding: 10px; border-radius: 3px; font-family: monospace; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>🔧 Database Fix Tool</h1>
                
                <div class="error">
                    <h3>❌ Problem erkannt:</h3>
                    <p><strong>Fehler:</strong> SQL Syntax Error bei getActivityLog()</p>
                    <p><strong>Ursache:</strong> LIMIT-Parameter in PDO prepared statement</p>
                    <p><strong>Lösung:</strong> Korrigierte Database-Klasse verwenden</p>
                </div>
                
                <h3>🛠️ Verfügbare Fixes:</h3>
                
                <a href="?test=1" class="btn">🧪 Database testen</a>
                <a href="?test=1&repair=1" class="btn">🔧 Auto-Repair ausführen</a>
                
                <h3>📋 Manuelle Reparatur:</h3>
                
                <p><strong>Option 1:</strong> framework.php ersetzen</p>
                <div class="code">
                    1. Backup von framework.php erstellen<br>
                    2. Database-Klasse durch DatabaseFixed ersetzen<br>
                    3. getActivityLog Methode korrigieren
                </div>
                
                <p><strong>Option 2:</strong> Nur getActivityLog Methode ersetzen</p>
                <div class="code">
                    // In framework.php, Database-Klasse, getActivityLog ersetzen durch:<br>
                    public function getActivityLog($limit = 50) {<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;$limit = (int) $limit;<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;$sql = "SELECT * FROM activity_log ORDER BY created_at DESC LIMIT " . $limit;<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;$stmt = $this->connection->prepare($sql);<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;$stmt->execute();<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;return $stmt->fetchAll(PDO::FETCH_ASSOC);<br>
                    }
                </div>
                
                <div class="success">
                    <h3>✅ Nach dem Fix:</h3>
                    <p>• Activity Log wird korrekt geladen</p>
                    <p>• Keine SQL-Syntax-Fehler mehr</p>
                    <p>• Admin Dashboard funktioniert vollständig</p>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}
?>
