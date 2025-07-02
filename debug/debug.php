<?php
/**
 * Debug-Seite zur Fehleranalyse
 * Zeigt PHP-Fehler, Session-Informationen und JavaScript-Konsole
 */

// Debug-Modus aktivieren
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

require_once '../framework.php';
require_once '../auth_handler.php';

echo "<!DOCTYPE html>";
echo "<html lang='de'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<title>Debug - Server Management</title>";
echo "<style>";
echo "body { font-family: monospace; margin: 20px; background: #1e1e1e; color: #fff; }";
echo ".debug-section { background: #2d2d2d; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #007acc; }";
echo ".debug-section h3 { color: #007acc; margin-top: 0; }";
echo ".error { border-left-color: #ff6b6b; }";
echo ".error h3 { color: #ff6b6b; }";
echo ".success { border-left-color: #51cf66; }";
echo ".success h3 { color: #51cf66; }";
echo ".warning { border-left-color: #ffd43b; }";
echo ".warning h3 { color: #ffd43b; }";
echo "pre { background: #1a1a1a; padding: 10px; border-radius: 3px; overflow-x: auto; }";
echo ".console { background: #000; color: #0f0; padding: 10px; border-radius: 3px; min-height: 100px; }";
echo "button { background: #007acc; color: white; border: none; padding: 8px 15px; margin: 5px; border-radius: 3px; cursor: pointer; }";
echo "button:hover { background: #005a9e; }";
echo "</style>";
echo "</head>";
echo "<body>";

echo "<h1>🔍 Debug-Informationen - Server Management Framework</h1>";

// PHP-Informationen
echo "<div class='debug-section success'>";
echo "<h3>✅ PHP-Konfiguration</h3>";
echo "<pre>";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "\n";
echo "Script: " . ($_SERVER['SCRIPT_NAME'] ?? 'Unknown') . "\n";
echo "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown') . "\n";
echo "</pre>";
echo "</div>";

// Session-Informationen
echo "<div class='debug-section'>";
echo "<h3>🔑 Session-Informationen</h3>";
echo "<pre>";

SessionManager::startSession();

echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'INACTIVE') . "\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Name: " . session_name() . "\n";

if (SessionManager::isLoggedIn()) {
    echo "Login Status: EINGELOGGT ✅\n";
    $user_info = SessionManager::getUserInfo();
    echo "Benutzer: " . $user_info['username'] . "\n";
    echo "E-Mail: " . $user_info['email'] . "\n";
    echo "Rolle: " . $user_info['role'] . "\n";
    echo "Session verbleibt: " . SessionManager::getSessionTimeRemaining() . " Sekunden\n";
} else {
    echo "Login Status: NICHT EINGELOGGT ❌\n";
}

echo "\nSession-Daten:\n";
echo "SESSION Array:\n";
print_r($_SESSION);
echo "</pre>";
echo "</div>";

// Datenbank-Verbindung testen
echo "<div class='debug-section'>";
echo "<h3>🗄️ Datenbank-Verbindung</h3>";
echo "<pre>";

try {
    $db = Database::getInstance();
    echo "Datenbank-Verbindung: ERFOLGREICH ✅\n";
    
    $connection = $db->getConnection();
    $stmt = $connection->query("SELECT VERSION() as version");
    $version = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "MySQL Version: " . $version['version'] . "\n";
    
    // Tabellen prüfen
    $stmt = $connection->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Verfügbare Tabellen: " . implode(', ', $tables) . "\n";
    
    // User-Tabelle prüfen
    if (in_array('users', $tables)) {
        $stmt = $connection->query("SELECT COUNT(*) as count FROM users");
        $userCount = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Anzahl Benutzer: " . $userCount['count'] . "\n";
    } else {
        echo "❌ FEHLER: users Tabelle nicht gefunden!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Datenbank-Fehler: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "</div>";

// PHP-Erweiterungen prüfen
echo "<div class='debug-section'>";
echo "<h3>🔧 PHP-Erweiterungen</h3>";
echo "<pre>";

$required_extensions = ['curl', 'soap', 'pdo_mysql', 'json', 'session'];
foreach ($required_extensions as $ext) {
    $status = extension_loaded($ext) ? '✅' : '❌';
    echo "$ext: $status\n";
}

echo "</pre>";
echo "</div>";

// API-Tests
echo "<div class='debug-section'>";
echo "<h3>🌐 API-Verbindungen testen</h3>";
echo "<button onclick='testAPI(\"proxmox\")'>Proxmox testen</button>";
echo "<button onclick='testAPI(\"ispconfig\")'>ISPConfig testen</button>";
echo "<button onclick='testAPI(\"ovh\")'>OVH testen</button>";
echo "<div id='api-results'></div>";
echo "</div>";

// Error Log anzeigen
echo "<div class='debug-section warning'>";
echo "<h3>⚠️ PHP Error Log (letzte 20 Zeilen)</h3>";
echo "<pre>";

$error_log = ini_get('error_log');
if (file_exists($error_log)) {
    $lines = file($error_log);
    $recent_lines = array_slice($lines, -20);
    echo implode('', $recent_lines);
} else {
    echo "Error Log nicht gefunden oder leer.\n";
    echo "Standard Error Log Pfad: $error_log\n";
}

echo "</pre>";
echo "</div>";

// JavaScript-Konsole
echo "<div class='debug-section error'>";
echo "<h3>🐛 JavaScript-Konsole</h3>";
echo "<div class='console' id='js-console'>JavaScript-Fehler werden hier angezeigt...</div>";
echo "<button onclick='clearConsole()'>Konsole leeren</button>";
echo "<button onclick='testJavaScript()'>JavaScript testen</button>";
echo "</div>";

// Live-Log für AJAX-Requests
echo "<div class='debug-section'>";
echo "<h3>📡 AJAX-Request Log</h3>";
echo "<div id='ajax-log' class='console'>AJAX-Requests werden hier angezeigt...</div>";
echo "<button onclick='clearAjaxLog()'>Log leeren</button>";
echo "</div>";

echo "<script>";
?>

// JavaScript-Fehlerbehandlung
window.onerror = function(msg, url, lineNo, columnNo, error) {
    const console = document.getElementById('js-console');
    const errorMsg = `
❌ FEHLER: ${msg}
📁 Datei: ${url}
📍 Zeile: ${lineNo}, Spalte: ${columnNo}
🕐 Zeit: ${new Date().toLocaleTimeString()}
${error ? '🔍 Stack: ' + error.stack : ''}
----------------------------------------
`;
    console.innerHTML += errorMsg;
    console.scrollTop = console.scrollHeight;
    return false;
};

// Console.log abfangen
const originalLog = console.log;
const originalError = console.error;
const originalWarn = console.warn;

console.log = function(...args) {
    originalLog.apply(console, args);
    const jsConsole = document.getElementById('js-console');
    jsConsole.innerHTML += `📝 LOG: ${args.join(' ')}\n`;
    jsConsole.scrollTop = jsConsole.scrollHeight;
};

console.error = function(...args) {
    originalError.apply(console, args);
    const jsConsole = document.getElementById('js-console');
    jsConsole.innerHTML += `❌ ERROR: ${args.join(' ')}\n`;
    jsConsole.scrollTop = jsConsole.scrollHeight;
};

console.warn = function(...args) {
    originalWarn.apply(console, args);
    const jsConsole = document.getElementById('js-console');
    jsConsole.innerHTML += `⚠️ WARN: ${args.join(' ')}\n`;
    jsConsole.scrollTop = jsConsole.scrollHeight;
};

// AJAX-Monitoring
const originalFetch = window.fetch;
window.fetch = function(...args) {
    const ajaxLog = document.getElementById('ajax-log');
    ajaxLog.innerHTML += `📡 AJAX Request: ${args[0]}\n🕐 ${new Date().toLocaleTimeString()}\n`;
    
    return originalFetch.apply(this, args)
        .then(response => {
            ajaxLog.innerHTML += `✅ Response: ${response.status} ${response.statusText}\n`;
            ajaxLog.scrollTop = ajaxLog.scrollHeight;
            return response;
        })
        .catch(error => {
            ajaxLog.innerHTML += `❌ AJAX Error: ${error.message}\n`;
            ajaxLog.scrollTop = ajaxLog.scrollHeight;
            throw error;
        });
};

function clearConsole() {
    document.getElementById('js-console').innerHTML = 'Konsole geleert...\n';
}

function clearAjaxLog() {
    document.getElementById('ajax-log').innerHTML = 'AJAX-Log geleert...\n';
}

function testJavaScript() {
    console.log('JavaScript-Test erfolgreich!');
    console.warn('Dies ist eine Test-Warnung');
    
    // Teste framework.js Funktionen falls verfügbar
    if (typeof SessionManager !== 'undefined') {
        console.log('SessionManager gefunden');
    } else {
        console.error('SessionManager nicht gefunden');
    }
    
    // Teste ob main.js geladen ist
    if (typeof showNotification !== 'undefined') {
        console.log('main.js Funktionen verfügbar');
        try {
            showNotification('Debug-Test erfolgreich!', 'success');
        } catch (e) {
            console.error('Fehler bei showNotification:', e.message);
        }
    } else {
        console.error('main.js Funktionen nicht verfügbar');
    }
}

async function testAPI(apiType) {
    const resultsDiv = document.getElementById('api-results');
    resultsDiv.innerHTML += `<h4>🧪 Teste ${apiType.toUpperCase()} API...</h4>`;
    
    try {
        const formData = new FormData();
        formData.append('action', `get_${apiType}_test`);
        
        const response = await fetch('', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            resultsDiv.innerHTML += `<pre style="color: #51cf66;">✅ ${apiType} API: ERFOLGREICH</pre>`;
        } else {
            resultsDiv.innerHTML += `<pre style="color: #ff6b6b;">❌ ${apiType} API: FEHLER - ${result.error || 'Unbekannter Fehler'}</pre>`;
        }
    } catch (error) {
        resultsDiv.innerHTML += `<pre style="color: #ff6b6b;">❌ ${apiType} API: NETZWERK-FEHLER - ${error.message}</pre>`;
    }
}

// Automatische Tests beim Laden
document.addEventListener('DOMContentLoaded', function() {
    console.log('Debug-Seite geladen');
    
    // Teste ob jQuery verfügbar ist
    if (typeof $ !== 'undefined') {
        console.log('jQuery verfügbar: Version ' + $.fn.jquery);
    } else {
        console.log('jQuery nicht verfügbar');
    }
    
    // Teste ob wichtige Funktionen verfügbar sind
    const functions = ['showTab', 'makeRequest', 'showNotification'];
    functions.forEach(func => {
        if (typeof window[func] !== 'undefined') {
            console.log(`✅ Funktion ${func} verfügbar`);
        } else {
            console.warn(`⚠️ Funktion ${func} nicht verfügbar`);
        }
    });
});

<?php
echo "</script>";
echo "</body></html>";
?>
