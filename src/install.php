<?php
/**
 * Server Management Framework
 * 
 * @author Teris
 * @version 3.1.2
 */
session_start();

$step = $_SESSION['install_step'] ?? 1;
$error_message = '';
$success_message = '';

// Konfigurationspfad
if (!defined('CONFIG_PATH')) {
    define('CONFIG_PATH', __DIR__ . '/../config/config.inc.php');
}

// Verfügbare Datenbanktypen
$available_db_types = [
    'mysql' => 'MySQL/MariaDB',
    'postgresql' => 'PostgreSQL', 
    'sqlite' => 'SQLite',
    'mongodb' => 'MongoDB'
];

// Prüfen, ob die Konfigurationsdatei bereits existiert und ausgefüllt ist
function is_configured() {
    if (!file_exists(CONFIG_PATH)) {
        return false;
    }
    $content = file_get_contents(CONFIG_PATH);
    return strpos($content, "const DB_USER 				= '';") === false;
}

if (is_configured() && !isset($_GET['force_install'])) {
    $error_message = "Das System scheint bereits konfiguriert zu sein. Um die Installation erneut auszuführen, fügen Sie '?force_install=true' zur URL hinzu. ACHTUNG: Dies kann bestehende Daten überschreiben.";
}

// Log-Funktion (vereinfacht)
function log_install_action($message, $is_error = false) {
    error_log(($is_error ? "INSTALL ERROR: " : "INSTALL INFO: ") . $message);
}

function execute_write_config() {
    global $error_message, $success_message, $step, $available_db_types;
    
    log_install_action("Versuche Konfigurationsdatei zu schreiben.");
    
    if (!isset($_SESSION['db_type'], $_SESSION['db_host'], $_SESSION['db_name'], $_SESSION['db_user'], $_SESSION['db_pass'])) {
        $error_message = "Datenbankdetails nicht in Session gefunden. Bitte starten Sie Schritt 1 erneut.";
        $_SESSION['install_step'] = 1;
        $step = 1;
        log_install_action($error_message, true);
        return false;
    }
    
    try {
        if (!file_exists(CONFIG_PATH)) {
            if (!is_dir(dirname(CONFIG_PATH))) {
                mkdir(dirname(CONFIG_PATH), 0755, true);
            }
            
            // Erstelle Konfigurationsdatei basierend auf gewähltem DB-Typ
            $db_type = $_SESSION['db_type'];
            $initial_config_content = generate_config_content($db_type);
            
            if (file_put_contents(CONFIG_PATH, $initial_config_content) === false) {
                throw new Exception("Konfigurationsdatei konnte nicht erstellt werden unter: " . CONFIG_PATH);
            }
            log_install_action("Konfigurationsdatei für $db_type erstellt.");
        }

        $config_content = file_get_contents(CONFIG_PATH);
        if ($config_content === false) {
            throw new Exception("Konfigurationsdatei konnte nicht gelesen werden: " . CONFIG_PATH);
        }

        // Aktualisiere Konfiguration basierend auf gewähltem DB-Typ
        $config_content = update_config_content($config_content, $_SESSION['db_type']);

        if (file_put_contents(CONFIG_PATH, $config_content) === false) {
            throw new Exception("Konfigurationsdatei konnte nicht geschrieben werden. Überprüfen Sie die Dateiberechtigungen für " . CONFIG_PATH);
        }

        $success_message = "Konfigurationsdatei erfolgreich aktualisiert.";
        log_install_action("Konfigurationsdatei erfolgreich aktualisiert.");
        $_SESSION['install_step'] = 2;
        $step = 2;
        return true;

    } catch (Exception $e) {
        $error_message = "Fehler beim Schreiben der Konfiguration: " . $e->getMessage();
        log_install_action($error_message, true);
        $_SESSION['install_step'] = 1;
        $step = 1;
        return false;
    }
}

function generate_config_content($db_type) {
    $content = "<?php\n";
    $content .= "// =============================================================================\n";
    $content .= "// CONFIG CLASS - Multi-Database Support\n";
    $content .= "// =============================================================================\n";
    $content .= "class Config {\n";
    
    // Datenbanktyp
    $content .= "    const DB_TYPE = '$db_type';\n\n";
    
    // MySQL/MariaDB Konfiguration
    $content .= "    // MySQL/MariaDB Konfiguration\n";
    $content .= "    const DB_HOST = '';\n";
    $content .= "    const DB_PORT = 3306;\n";
    $content .= "    const DB_NAME = '';\n";
    $content .= "    const DB_USER = '';\n";
    $content .= "    const DB_PASS = '';\n";
    $content .= "    const DB_CHARSET = 'utf8mb4';\n";
    $content .= "    const DB_USEING = false;\n\n";
    
    // PostgreSQL Konfiguration
    $content .= "    // PostgreSQL Konfiguration\n";
    $content .= "    const DB_PGSQL_HOST = '';\n";
    $content .= "    const DB_PGSQL_PORT = 5432;\n";
    $content .= "    const DB_PGSQL_NAME = '';\n";
    $content .= "    const DB_PGSQL_USER = '';\n";
    $content .= "    const DB_PGSQL_PASS = '';\n";
    $content .= "    const DB_PGSQL_USEING = false;\n\n";
    
    // SQLite Konfiguration
    $content .= "    // SQLite Konfiguration\n";
    $content .= "    const DB_SQLITE_PATH = 'data/database.sqlite';\n";
    $content .= "    const DB_SQLITE_USEING = false;\n\n";
    
    // MongoDB Konfiguration
    $content .= "    // MongoDB Konfiguration\n";
    $content .= "    const DB_MONGO_HOST = 'localhost';\n";
    $content .= "    const DB_MONGO_PORT = 27017;\n";
    $content .= "    const DB_MONGO_NAME = '';\n";
    $content .= "    const DB_MONGO_USER = '';\n";
    $content .= "    const DB_MONGO_PASS = '';\n";
    $content .= "    const DB_MONGO_USEING = false;\n\n";
    
    // API Konfiguration
    $content .= "    // API Konfiguration\n";
    $content .= "    const PROXMOX_HOST = 'https://your-server:8006';\n";
    $content .= "    const PROXMOX_USER = '@pve';\n";
    $content .= "    const PROXMOX_PASSWORD = '';\n\n";
    
    $content .= "    const ISPCONFIG_HOST = 'https://your-server:8080';\n";
    $content .= "    const ISPCONFIG_USER = '';\n";
    $content .= "    const ISPCONFIG_PASSWORD = '';\n\n";
    
    $content .= "    const OVH_APPLICATION_KEY = '';\n";
    $content .= "    const OVH_APPLICATION_SECRET = '';\n";
    $content .= "    const OVH_CONSUMER_KEY = '';\n";
    $content .= "    const OVH_ENDPOINT = 'ovh-eu';\n";
    $content .= "}\n";
    
    return $content;
}

function update_config_content($content, $db_type) {
    $db_host = $_SESSION['db_host'];
    $db_name = $_SESSION['db_name'];
    $db_user = $_SESSION['db_user'];
    $db_pass = $_SESSION['db_pass'];
    
    // Aktiviere den gewählten DB-Typ
    $content = preg_replace("/(const DB_TYPE\s*=\s*').*?(';)/", "$1$db_type$2", $content);
    
    switch ($db_type) {
        case 'mysql':
        case 'mariadb':
            $content = preg_replace("/(const DB_HOST\s*=\s*').*?(';)/", "$1" . addslashes($db_host) . "$2", $content);
            $content = preg_replace("/(const DB_NAME\s*=\s*').*?(';)/", "$1" . addslashes($db_name) . "$2", $content);
            $content = preg_replace("/(const DB_USER\s*=\s*').*?(';)/", "$1" . addslashes($db_user) . "$2", $content);
            $content = preg_replace("/(const DB_PASS\s*=\s*').*?(';)/", "$1" . addslashes($db_pass) . "$2", $content);
            $content = preg_replace("/(const DB_USEING\s*=\s*).*?(;)/", "$1true$2", $content);
            break;
            
        case 'postgresql':
            $content = preg_replace("/(const DB_PGSQL_HOST\s*=\s*').*?(';)/", "$1" . addslashes($db_host) . "$2", $content);
            $content = preg_replace("/(const DB_PGSQL_NAME\s*=\s*').*?(';)/", "$1" . addslashes($db_name) . "$2", $content);
            $content = preg_replace("/(const DB_PGSQL_USER\s*=\s*').*?(';)/", "$1" . addslashes($db_user) . "$2", $content);
            $content = preg_replace("/(const DB_PGSQL_PASS\s*=\s*').*?(';)/", "$1" . addslashes($db_pass) . "$2", $content);
            $content = preg_replace("/(const DB_PGSQL_USEING\s*=\s*).*?(;)/", "$1true$2", $content);
            break;
            
        case 'sqlite':
            $content = preg_replace("/(const DB_SQLITE_PATH\s*=\s*').*?(';)/", "$1" . addslashes($db_name) . "$2", $content);
            $content = preg_replace("/(const DB_SQLITE_USEING\s*=\s*).*?(;)/", "$1true$2", $content);
            break;
            
        case 'mongodb':
            $content = preg_replace("/(const DB_MONGO_HOST\s*=\s*').*?(';)/", "$1" . addslashes($db_host) . "$2", $content);
            $content = preg_replace("/(const DB_MONGO_NAME\s*=\s*').*?(';)/", "$1" . addslashes($db_name) . "$2", $content);
            $content = preg_replace("/(const DB_MONGO_USER\s*=\s*').*?(';)/", "$1" . addslashes($db_user) . "$2", $content);
            $content = preg_replace("/(const DB_MONGO_PASS\s*=\s*').*?(';)/", "$1" . addslashes($db_pass) . "$2", $content);
            $content = preg_replace("/(const DB_MONGO_USEING\s*=\s*).*?(;)/", "$1true$2", $content);
            break;
    }
    
    return $content;
}

function get_sql_file_path($db_type) {
    $sql_files = [
        'mysql' => __DIR__ . '/../install/database-structure-install-mysql.sql',
        'postgresql' => __DIR__ . '/../install/database-structure-install-postgresql.sql',
        'sqlite' => __DIR__ . '/../install/database-structure-install-sqlite.sql',
        'mongodb' => __DIR__ . '/../install/database-structure-install-mongodb.js'
    ];
    
    return $sql_files[$db_type] ?? null;
}

function execute_database_setup($db_type, $db_host, $db_name, $db_user, $db_pass) {
    global $error_message, $success_message;
    
    try {
        switch ($db_type) {
            case 'mysql':
            case 'mariadb':
                return setup_mysql($db_host, $db_name, $db_user, $db_pass);
                
            case 'postgresql':
                return setup_postgresql($db_host, $db_name, $db_user, $db_pass);
                
            case 'sqlite':
                return setup_sqlite($db_name);
                
            case 'mongodb':
                return setup_mongodb($db_host, $db_name, $db_user, $db_pass);
                
            default:
                throw new Exception("Unbekannter Datenbanktyp: $db_type");
        }
    } catch (Exception $e) {
        $error_message = "Datenbank-Setup Fehler: " . $e->getMessage();
        log_install_action($error_message, true);
        return false;
    }
}

function setup_mysql($host, $name, $user, $pass) {
    $conn = new mysqli($host, $user, $pass);
    if ($conn->connect_error) {
        throw new Exception("Verbindungsfehler: " . $conn->connect_error);
    }
    
    // Datenbank erstellen
    $conn->query("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    if ($conn->error) {
        throw new Exception("Fehler beim Erstellen der Datenbank '$name': " . $conn->error);
    }
    
    $conn->select_db($name);
    if ($conn->error) {
        throw new Exception("Fehler beim Auswählen der Datenbank '$name': " . $conn->error);
    }
    
    // SQL-Datei ausführen
    $sql_file = get_sql_file_path('mysql');
    if (!file_exists($sql_file)) {
        throw new Exception("SQL-Datei nicht gefunden: $sql_file");
    }
    
    $sql_content = file_get_contents($sql_file);
    $sql_content = preg_replace('/(--.*)|(#.*)|(\/\*[\s\S]*?\*\/)/', '', $sql_content);
    $sql_statements = array_filter(array_map('trim', explode(';', $sql_content)));
    
    if (empty($sql_statements)) {
        throw new Exception("Keine gültigen SQL-Anweisungen gefunden.");
    }
    
    $conn->begin_transaction();
    foreach ($sql_statements as $statement) {
        if (!empty($statement)) {
            if (!$conn->query($statement)) {
                throw new Exception("Fehler beim Ausführen von SQL: " . $conn->error . " | Statement: " . substr($statement, 0, 100) . "...");
            }
        }
    }
    $conn->commit();
    
    $conn->close();
    return true;
}

function setup_postgresql($host, $name, $user, $pass) {
    $dsn = "pgsql:host=$host;port=5432";
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Datenbank erstellen
    $conn->exec("CREATE DATABASE \"$name\"");
    
    // Neue Verbindung zur erstellten Datenbank
    $dsn = "pgsql:host=$host;port=5432;dbname=$name";
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // SQL-Datei ausführen
    $sql_file = get_sql_file_path('postgresql');
    if (!file_exists($sql_file)) {
        throw new Exception("SQL-Datei nicht gefunden: $sql_file");
    }
    
    $sql_content = file_get_contents($sql_file);
    $conn->exec($sql_content);
    
    return true;
}

function setup_sqlite($db_path) {
    $full_path = __DIR__ . '/../' . $db_path;
    $db_dir = dirname($full_path);
    
    if (!is_dir($db_dir)) {
        mkdir($db_dir, 0755, true);
    }
    
    $conn = new PDO("sqlite:$full_path");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // SQL-Datei ausführen
    $sql_file = get_sql_file_path('sqlite');
    if (!file_exists($sql_file)) {
        throw new Exception("SQL-Datei nicht gefunden: $sql_file");
    }
    
    $sql_content = file_get_contents($sql_file);
    $conn->exec($sql_content);
    
    return true;
}

function setup_mongodb($host, $name, $user, $pass) {
    // MongoDB Setup - hier würde die JavaScript-Datei ausgeführt werden
    // Für die Installation wird nur die Verbindung getestet
    if (!extension_loaded('mongodb')) {
        throw new Exception("MongoDB PHP Extension ist nicht installiert. Bitte installieren Sie die MongoDB PHP Extension (php-mongodb)");
    }
    
    // Prüfen ob die MongoDB-Klassen verfügbar sind
    if (!class_exists('MongoDB\Client')) {
        throw new Exception("MongoDB\Client Klasse ist nicht verfügbar. Stellen Sie sicher, dass die MongoDB PHP Extension korrekt installiert ist.");
    }
    
    $connectionString = "mongodb://";
    if (!empty($user) && !empty($pass)) {
        $connectionString .= "$user:$pass@";
    }
    $connectionString .= "$host:27017";
    
    try {
        // Verwende Reflection für dynamische Instanziierung
        if (class_exists('MongoDB\Client')) {
            $reflection = new ReflectionClass('MongoDB\Client');
            $client = $reflection->newInstance($connectionString);
            $client->listDatabases(); // Test der Verbindung
        } else {
            throw new Exception("MongoDB\Client Klasse ist nicht verfügbar");
        }
    } catch (Exception $e) {
        throw new Exception("MongoDB Verbindungsfehler: " . $e->getMessage());
    }
    
    return true;
}

// Verarbeitung der Formulareingaben
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'setup_db') {
            log_install_action("Versuche Datenbank-Setup.");
            
            $db_type = $_POST['db_type'] ?? 'mysql';
            $db_host = $_POST['db_host'] ?? 'localhost';
            $db_name = $_POST['db_name'] ?? '';
            $db_user = $_POST['db_user'] ?? '';
            $db_pass = $_POST['db_pass'] ?? '';

            if (empty($db_name) || empty($db_user)) {
                $error_message = "Datenbankname und Benutzername sind erforderlich.";
                log_install_action($error_message, true);
            } else {
                // Datenbank-Setup ausführen
                if (execute_database_setup($db_type, $db_host, $db_name, $db_user, $db_pass)) {
                    $success_message = "Datenbank '$db_name' erfolgreich eingerichtet.";
                    log_install_action($success_message);

                    // Session-Daten speichern
                    $_SESSION['db_type'] = $db_type;
                    $_SESSION['db_host'] = $db_host;
                    $_SESSION['db_name'] = $db_name;
                    $_SESSION['db_user'] = $db_user;
                    $_SESSION['db_pass'] = $db_pass;

                    // Konfiguration schreiben
                    if (execute_write_config()) {
                        $_SESSION['install_step'] = 2;
                        $step = 2;
                    }
                }
            }
        }
        elseif ($action === 'create_admin') {
            if (!is_configured()) {
                $error_message = "Die Konfigurationsdatei ist noch nicht geschrieben. Bitte führen Sie zuerst Schritt 1 aus.";
                log_install_action($error_message, true);
                $_SESSION['install_step'] = 1;
                $step = 1;
            } else {
                log_install_action("Versuche Admin-Benutzer zu erstellen.");
                
                $admin_user = $_POST['admin_user'] ?? '';
                $admin_email = $_POST['admin_email'] ?? '';
                $admin_pass = $_POST['admin_pass'] ?? '';
                $admin_pass_confirm = $_POST['admin_pass_confirm'] ?? '';

                if (empty($admin_user) || empty($admin_email) || empty($admin_pass)) {
                    $error_message = "Alle Felder für den Admin-Benutzer sind erforderlich.";
                    log_install_action($error_message, true);
                    $step = 2;
                } elseif ($admin_pass !== $admin_pass_confirm) {
                    $error_message = "Die Passwörter stimmen nicht überein.";
                    log_install_action($error_message, true);
                    $step = 2;
                } elseif (strlen($admin_pass) < 6) {
                    $error_message = "Das Passwort muss mindestens 6 Zeichen lang sein.";
                    log_install_action($error_message, true);
                    $step = 2;
                } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
                    $error_message = "Ungültige E-Mail-Adresse.";
                    log_install_action($error_message, true);
                    $step = 2;
                } else {
                    try {
                        // Framework laden
                        require_once __DIR__ . '/../framework.php';

                        if (!class_exists('DatabaseManager')) {
                            throw new Exception("DatabaseManager Klasse nicht gefunden.");
                        }

                        $db = DatabaseManager::getInstance();
                        $conn = $db->getConnection();

                        // Prüfen ob Benutzer bereits existiert
                        if ($db->isMongoDB()) {
                            // MongoDB-spezifische Prüfung
                            $collection = $db->getDriver()->getCollection('users');
                            $existing = $collection->findOne(['$or' => [
                                ['username' => $admin_user],
                                ['email' => $admin_email]
                            ]]);
                            if ($existing) {
                                throw new Exception("Ein Benutzer mit diesem Benutzernamen oder E-Mail existiert bereits.");
                            }
                        } else {
                            // SQL-basierte Prüfung
                            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                            $stmt->execute([$admin_user, $admin_email]);
                            if ($stmt->fetch()) {
                                throw new Exception("Ein Benutzer mit diesem Benutzernamen oder E-Mail existiert bereits.");
                            }
                        }

                        // Admin-Benutzer erstellen
                        $password_hash = password_hash($admin_pass, PASSWORD_DEFAULT);
                        $created_at = date('Y-m-d H:i:s');

                        if ($db->isMongoDB()) {
                            // MongoDB-spezifische Erstellung
                            if (!class_exists('MongoDB\BSON\UTCDateTime')) {
                                throw new Exception("MongoDB\BSON\UTCDateTime Klasse ist nicht verfügbar. Stellen Sie sicher, dass die MongoDB PHP Extension korrekt installiert ist.");
                            }
                            
                            $collection = $db->getDriver()->getCollection('users');
                            // Erstelle UTCDateTime Objekte über Reflection
                            $utcDateTimeReflection = new ReflectionClass('MongoDB\BSON\UTCDateTime');
                            $createdAt = $utcDateTimeReflection->newInstance();
                            $updatedAt = $utcDateTimeReflection->newInstance();
                            
                            $user_doc = [
                                'username' => $admin_user,
                                'email' => $admin_email,
                                'password_hash' => $password_hash,
                                'full_name' => $admin_user,
                                'role' => 'admin',
                                'active' => 'y',
                                'created_at' => $createdAt,
                                'updated_at' => $updatedAt
                            ];
                            $result = $collection->insertOne($user_doc);
                            if (!$result->getInsertedCount()) {
                                throw new Exception("Fehler beim Erstellen des Admin-Benutzers in MongoDB.");
                            }
                        } else {
                            // SQL-basierte Erstellung
                            $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, full_name, role, active, created_at, updated_at) VALUES (?, ?, ?, ?, 'admin', 'y', ?, ?)");
                            if (!$stmt->execute([$admin_user, $admin_email, $password_hash, $admin_user, $created_at, $created_at])) {
                                throw new Exception("Fehler beim Erstellen des Admin-Benutzers: " . implode(', ', $stmt->errorInfo()));
                            }
                        }

                        $success_message = "Admin-Benutzer '$admin_user' erfolgreich erstellt.";
                        log_install_action($success_message);
                        $_SESSION['install_step'] = 3;
                        $step = 3;

                    } catch (Exception $e) {
                        $error_message = "Fehler beim Erstellen des Admin-Benutzers: " . $e->getMessage();
                        log_install_action($error_message, true);
                        $step = 2;
                        $_SESSION['install_step'] = 2;
                    }
                }
            }
        }
        elseif ($action === 'delete_installer') {
            log_install_action("Versuche Installationsdatei zu löschen.");
            if (unlink(__FILE__)) {
                $success_message = "Installationsdatei erfolgreich gelöscht. Sie werden weitergeleitet...";
                log_install_action($success_message);
                header("Refresh:3; url=../login.php");
                exit;
            } else {
                $error_message = "Fehler beim Löschen der Installationsdatei. Bitte löschen Sie install.php manuell.";
                log_install_action($error_message, true);
                $step = 3;
                $_SESSION['install_step'] = $step;
            }
        }
        elseif ($action === 'rename_installer') {
            log_install_action("Versuche Installationsdatei umzubenennen.");
            $new_name = __FILE__ . '.installed';
            if (rename(__FILE__, $new_name)) {
                $success_message = "Installationsdatei erfolgreich zu install.php.installed umbenannt. Sie werden weitergeleitet...";
                log_install_action($success_message);
                header("Refresh:3; url=../login.php");
                exit;
            } else {
                $error_message = "Fehler beim Umbenennen der Installationsdatei. Bitte benennen Sie install.php manuell um oder löschen Sie sie.";
                log_install_action($error_message, true);
                $step = 3;
                $_SESSION['install_step'] = $step;
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Framework Installation - Multi-Database Support</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; margin: 5px; }
        .btn-primary { background-color: #007bff; color: white; }
        .btn-secondary { background-color: #6c757d; color: white; }
        .btn-success { background-color: #28a745; color: white; }
        .btn-danger { background-color: #dc3545; color: white; }
        .btn-warning { background-color: #ffc107; color: black; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .hidden { display: none; }
        .steps { margin-bottom: 30px; display: flex; justify-content: space-around; }
        .step { padding: 10px 20px; border: 1px solid #ddd; border-radius: 4px; background-color: #f8f9fa; }
        .step.active { background-color: #007bff; color: white; border-color: #007bff; }
        .progress-bar { width: 100%; background-color: #e9ecef; border-radius: 4px; margin-bottom: 20px; }
        .progress-bar-inner { height: 30px; width: 0%; background-color: #007bff; border-radius: 4px; text-align: center; color: white; line-height: 30px; font-weight: bold; }
        .db-type-info { background-color: #e7f3ff; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .db-type-info h4 { margin-top: 0; color: #0066cc; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Framework Installation - Multi-Database Support</h1>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <div class="steps">
            <div class="step <?php echo $step >= 1 ? 'active' : ''; ?>">1. Datenbank & Konfig.</div>
            <div class="step <?php echo $step >= 2 ? 'active' : ''; ?>">2. Admin-Konto</div>
            <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>">3. Abschluss</div>
        </div>

        <div class="progress-bar">
            <?php
                $progress_percentage = 0;
                if ($step === 1) $progress_percentage = 0;
                elseif ($step === 2) $progress_percentage = 33;
                elseif ($step === 3) $progress_percentage = 66;
                elseif ($step === 4) $progress_percentage = 100;
            ?>
            <div class="progress-bar-inner" style="width: <?php echo $progress_percentage; ?>%;">
                Schritt <?php echo min($step, 3); ?> von 3
            </div>
        </div>

        <?php if (!is_configured() || isset($_GET['force_install'])): ?>
            <!-- Schritt 1: Datenbankdetails -->
            <form id="dbForm" method="POST" action="install.php" class="<?php echo ($_SESSION['install_step'] ?? 1) == 1 ? '' : 'hidden'; ?>">
                <input type="hidden" name="action" value="setup_db">
                <h2>Schritt 1: Datenbank & Konfiguration</h2>
                <p>Wählen Sie den Datenbanktyp und geben Sie die entsprechenden Zugangsdaten ein.</p>
                
                <div class="form-group">
                    <label for="db_type">Datenbanktyp</label>
                    <select id="db_type" name="db_type" onchange="showDbFields()" required>
                        <?php foreach ($available_db_types as $type => $label): ?>
                            <option value="<?php echo $type; ?>" <?php echo ($_POST['db_type'] ?? $_SESSION['db_type'] ?? 'mysql') === $type ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="db-fields">
                    <div class="form-group" id="host-field">
                        <label for="db_host">Datenbank-Host</label>
                        <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? $_SESSION['db_host'] ?? 'localhost'); ?>" required>
                    </div>
                    <div class="form-group" id="name-field">
                        <label for="db_name">Datenbankname</label>
                        <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($_POST['db_name'] ?? $_SESSION['db_name'] ?? 'server_management'); ?>" required>
                    </div>
                    <div class="form-group" id="user-field">
                        <label for="db_user">Benutzername</label>
                        <input type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars($_POST['db_user'] ?? $_SESSION['db_user'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group" id="pass-field">
                        <label for="db_pass">Passwort</label>
                        <input type="password" id="db_pass" name="db_pass">
                    </div>
                </div>

                <div id="db-info" class="db-type-info">
                    <h4>MySQL/MariaDB</h4>
                    <p>Standard SQL-Datenbank. Benötigt MySQL/MariaDB Server.</p>
                </div>

                <button type="submit" class="btn btn-primary">Datenbank einrichten & Admin-Konto erstellen</button>
            </form>

            <!-- Schritt 2: Admin-Konto Erstellung -->
            <form id="adminForm" method="POST" action="install.php" class="<?php echo ($_SESSION['install_step'] ?? 1) == 2 ? '' : 'hidden'; ?>">
                <input type="hidden" name="action" value="create_admin">
                <h2>Schritt 2: Administrator-Konto erstellen</h2>
                <p>Erstellen Sie das erste Administrator-Konto für das System.</p>
                
                <div class="form-group">
                    <label for="admin_user">Admin-Benutzername</label>
                    <input type="text" id="admin_user" name="admin_user" value="<?php echo htmlspecialchars($_POST['admin_user'] ?? 'admin'); ?>" required>
                </div>
                <div class="form-group">
                    <label for="admin_email">Admin-E-Mail</label>
                    <input type="email" id="admin_email" name="admin_email" value="<?php echo htmlspecialchars($_POST['admin_email'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="admin_pass">Passwort (min. 6 Zeichen)</label>
                    <input type="password" id="admin_pass" name="admin_pass" required>
                </div>
                <div class="form-group">
                    <label for="admin_pass_confirm">Passwort bestätigen</label>
                    <input type="password" id="admin_pass_confirm" name="admin_pass_confirm" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Admin-Konto erstellen & Weiter</button>
                <button type="button" onclick="showStep(1);" class="btn btn-secondary">Zurück zu Datenbank</button>
            </form>

            <!-- Schritt 3: Abschluss -->
            <div id="summary" class="<?php echo ($_SESSION['install_step'] ?? 1) >= 3 ? '' : 'hidden'; ?>">
                <h2>Schritt 3: Installation abgeschlossen! 🎉</h2>
                <?php if (empty($error_message)): ?>
                    <p>Herzlichen Glückwunsch! Das Framework wurde erfolgreich installiert und konfiguriert.</p>
                    <p><strong>Verwendete Datenbank:</strong> <?php echo $available_db_types[$_SESSION['db_type'] ?? 'mysql']; ?></p>
                    <p><strong>Wichtige nächste Schritte:</strong></p>
                    <ul>
                        <li>Aus Sicherheitsgründen sollten Sie diese Datei (<code>install.php</code>) jetzt von Ihrem Server <strong>löschen oder umbenennen</strong>.</li>
                        <li>Sie können sich nun mit dem erstellten Administrator-Konto anmelden.</li>
                    </ul>
                    <a href="../login.php" class="btn btn-success">Zur Login-Seite</a>
                    <br><br>
                    <form method="POST" action="install.php" style="display: inline-block;">
                        <input type="hidden" name="action" value="delete_installer">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Sind Sie sicher, dass Sie die Installationsdatei löschen möchten?');">Installationsdatei jetzt löschen</button>
                    </form>
                    <form method="POST" action="install.php" style="display: inline-block; margin-left: 10px;">
                        <input type="hidden" name="action" value="rename_installer">
                        <button type="submit" class="btn btn-warning" onclick="return confirm('Sind Sie sicher, dass Sie die Installationsdatei umbenennen möchten?');">Installationsdatei umbenennen</button>
                    </form>
                <?php else: ?>
                    <p>Es gab einen Fehler im letzten Schritt. Bitte überprüfen Sie die Meldungen.</p>
                <?php endif; ?>
            </div>

        <?php endif; ?>

    </div>

    <script>
        function showDbFields() {
            const dbType = document.getElementById('db_type').value;
            const hostField = document.getElementById('host-field');
            const nameField = document.getElementById('name-field');
            const userField = document.getElementById('user-field');
            const passField = document.getElementById('pass-field');
            const dbInfo = document.getElementById('db-info');
            
            // Alle Felder zurücksetzen
            hostField.style.display = 'block';
            userField.style.display = 'block';
            passField.style.display = 'block';
            
            switch (dbType) {
                case 'sqlite':
                    hostField.style.display = 'none';
                    userField.style.display = 'none';
                    passField.style.display = 'none';
                    document.getElementById('db_name').placeholder = 'data/database.sqlite';
                    dbInfo.innerHTML = '<h4>SQLite</h4><p>Dateibasierte Datenbank. Kein separater Server erforderlich.</p>';
                    break;
                case 'mongodb':
                    document.getElementById('db_name').placeholder = 'server_management';
                    dbInfo.innerHTML = '<h4>MongoDB</h4><p>NoSQL-Datenbank. Benötigt MongoDB Server.</p>';
                    break;
                case 'postgresql':
                    document.getElementById('db_name').placeholder = 'server_management';
                    dbInfo.innerHTML = '<h4>PostgreSQL</h4><p>Erweiterte SQL-Datenbank. Benötigt PostgreSQL Server.</p>';
                    break;
                default: // mysql
                    document.getElementById('db_name').placeholder = 'server_management';
                    dbInfo.innerHTML = '<h4>MySQL/MariaDB</h4><p>Standard SQL-Datenbank. Benötigt MySQL/MariaDB Server.</p>';
                    break;
            }
        }
        
        function showStep(step) {
            document.getElementById('dbForm').classList.add('hidden');
            document.getElementById('adminForm').classList.add('hidden');
            document.getElementById('summary').classList.add('hidden');
            
            if (step === 1) {
                document.getElementById('dbForm').classList.remove('hidden');
            } else if (step === 2) {
                document.getElementById('adminForm').classList.remove('hidden');
            } else if (step >= 3) {
                document.getElementById('summary').classList.remove('hidden');
            }
        }
        
        // Initialisierung
        showDbFields();
    </script>
</body>
</html>