<?php
/**
 * Quick Fix Skript für häufige Probleme
 * Führe dieses Skript aus um typische Fehler zu beheben
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 Quick Fix - Server Management Framework</h1>";
echo "<style>body{font-family:Arial;margin:20px;} .fix{background:#e8f5e8;padding:10px;margin:10px 0;border-left:4px solid #4caf50;} .error{background:#ffe8e8;padding:10px;margin:10px 0;border-left:4px solid #f44336;} .warning{background:#fff3e0;padding:10px;margin:10px 0;border-left:4px solid #ff9800;}</style>";

// 1. Session-Verzeichnis prüfen
echo "<div class='fix'><h3>1. Session-Verzeichnis prüfen</h3>";
$session_path = session_save_path();
if (empty($session_path)) {
    $session_path = sys_get_temp_dir();
}
echo "Session-Pfad: $session_path<br>";
if (is_writable($session_path)) {
    echo "✅ Session-Verzeichnis ist beschreibbar";
} else {
    echo "❌ Session-Verzeichnis nicht beschreibbar!<br>";
    echo "Führe aus: <code>chmod 755 $session_path</code>";
}
echo "</div>";

// 2. Datenbank-Tabellen prüfen
echo "<div class='fix'><h3>2. Datenbank-Tabellen prüfen</h3>";
try {
    require_once '../framework.php';
    $db = Database::getInstance()->getConnection();
    
    $tables = ['users', 'activity_log', 'user_sessions'];
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "✅ Tabelle '$table' existiert<br>";
        } else {
            echo "❌ Tabelle '$table' fehlt!<br>";
        }
    }
    
    // User-Anzahl prüfen
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE active = 'y'");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Aktive Benutzer: " . $count['count'] . "<br>";
    
    if ($count['count'] == 0) {
        echo "<div class='warning'>⚠️ Keine aktiven Benutzer gefunden! Führe setup.php aus.</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Datenbank-Fehler: " . $e->getMessage() . "</div>";
}
echo "</div>";

// 3. Dateiberechtigungen prüfen
echo "<div class='fix'><h3>3. Dateiberechtigungen prüfen</h3>";
$files = ['framework.php', 'auth_handler.php', 'index.php', 'login.php'];
foreach ($files as $file) {
    if (file_exists($file)) {
        $perms = substr(sprintf('%o', fileperms($file)), -4);
        echo "✅ $file: $perms<br>";
    } else {
        echo "❌ $file: Datei nicht gefunden!<br>";
    }
}
echo "</div>";

// 4. PHP-Extensions prüfen
echo "<div class='fix'><h3>4. PHP-Extensions prüfen</h3>";
$required = ['curl', 'soap', 'pdo_mysql', 'json', 'session'];
foreach ($required as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ $ext: Verfügbar<br>";
    } else {
        echo "❌ $ext: FEHLT!<br>";
        echo "Installiere mit: <code>sudo apt-get install php-$ext</code><br>";
    }
}
echo "</div>";

// 5. Include-Path testen
echo "<div class='fix'><h3>5. Include-Path testen</h3>";
$includes = ['framework.php', 'auth_handler.php'];
foreach ($includes as $include) {
    if (file_exists($include)) {
        echo "✅ $include: Gefunden<br>";
        // Syntax-Check
        $output = shell_exec("php -l $include 2>&1");
        if (strpos($output, 'No syntax errors') !== false) {
            echo "✅ $include: Syntax OK<br>";
        } else {
            echo "❌ $include: Syntax-Fehler!<br>";
            echo "<pre>$output</pre>";
        }
    } else {
        echo "❌ $include: Nicht gefunden!<br>";
    }
}
echo "</div>";

// 6. Session-Test
echo "<div class='fix'><h3>6. Session-Test</h3>";
session_start();
$_SESSION['test'] = 'working';
if (isset($_SESSION['test']) && $_SESSION['test'] === 'working') {
    echo "✅ Sessions funktionieren<br>";
    unset($_SESSION['test']);
} else {
    echo "❌ Session-Problem!<br>";
}
echo "Session-ID: " . session_id() . "<br>";
echo "</div>";

// 7. Konfigurations-Test
echo "<div class='fix'><h3>7. Konfigurations-Test</h3>";
if (defined('Config::DB_HOST')) {
    echo "✅ Config-Klasse geladen<br>";
    echo "DB Host: " . Config::DB_HOST . "<br>";
    echo "DB Name: " . Config::DB_NAME . "<br>";
} else {
    echo "❌ Config-Klasse nicht gefunden!<br>";
}
echo "</div>";

// 8. Auto-Fix versuchen
if (isset($_GET['autofix'])) {
    echo "<div class='warning'><h3>🔧 Auto-Fix wird ausgeführt...</h3>";
    
    // Session-Verzeichnis Fix
    if (!is_writable(session_save_path())) {
        $new_path = __DIR__ . '/sessions';
        if (!is_dir($new_path)) {
            mkdir($new_path, 0755);
        }
        session_save_path($new_path);
        echo "Session-Pfad geändert zu: $new_path<br>";
    }
    
    // .htaccess erstellen falls nicht vorhanden
    if (!file_exists('.htaccess')) {
        $htaccess = "RewriteEngine On\n";
        $htaccess .= "# Security Headers\n";
        $htaccess .= "Header always set X-Content-Type-Options nosniff\n";
        $htaccess .= "Header always set X-Frame-Options DENY\n";
        file_put_contents('.htaccess', $htaccess);
        echo ".htaccess erstellt<br>";
    }
    
    echo "Auto-Fix abgeschlossen!<br>";
    echo "</div>";
}

echo "<hr>";
echo "<a href='?autofix=1' style='background:#4caf50;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>🔧 Auto-Fix ausführen</a> ";
echo "<a href='debug.php' style='background:#2196f3;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;margin-left:10px;'>🔍 Zur Debug-Seite</a>";

?>
