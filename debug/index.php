<?php
/**
 * Debug Tools Index - Übersicht aller verfügbaren Debug-Tools
 * Zentrale Anlaufstelle für Entwicklung und Fehleranalyse
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Einfache Checks
$framework_exists = file_exists('../framework.php');
$auth_exists = file_exists('../auth_handler.php');
$main_index_exists = file_exists('../index.php');

if ($framework_exists) {
    require_once '../framework.php';
}

if ($auth_exists) {
    require_once '../auth_handler.php';
}

// Quick Status Check
function getSystemStatus() {
    $status = [
        'php_version' => PHP_VERSION,
        'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'extensions' => [],
        'database' => 'unknown',
        'session' => 'unknown',
        'framework' => 'unknown'
    ];
    
    // PHP Extensions
    $required_extensions = ['curl', 'soap', 'pdo_mysql', 'json', 'session'];
    foreach ($required_extensions as $ext) {
        $status['extensions'][$ext] = extension_loaded($ext);
    }
    
    // Database Check
    if (class_exists('Database')) {
        try {
            $db = Database::getInstance();
            $status['database'] = 'connected';
        } catch (Exception $e) {
            $status['database'] = 'error: ' . $e->getMessage();
        }
    } else {
        $status['database'] = 'class not found';
    }
    
    // Session Check
    if (function_exists('SessionManager::startSession')) {
        try {
            SessionManager::startSession();
            $status['session'] = SessionManager::isLoggedIn() ? 'logged_in' : 'not_logged_in';
        } catch (Exception $e) {
            $status['session'] = 'error: ' . $e->getMessage();
        }
    } else {
        $status['session'] = 'not available';
    }
    
    // Framework Check
    if (class_exists('ServiceManager')) {
        $status['framework'] = 'loaded';
    } else {
        $status['framework'] = 'not loaded';
    }
    
    return $status;
}

$system_status = getSystemStatus();

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔧 Debug Tools - Server Management Framework</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e1e2e 0%, #2d2d44 100%);
            color: #fff;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            backdrop-filter: blur(10px);
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4, #45b7d1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .status-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .status-card h3 {
            color: #4ecdc4;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }
        
        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 8px 0;
            padding: 5px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .status-ok { color: #51cf66; }
        .status-error { color: #ff6b6b; }
        .status-warning { color: #ffd43b; }
        
        .tools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .tool-category {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 25px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .tool-category:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .tool-category h3 {
            color: #ff6b6b;
            margin-bottom: 15px;
            font-size: 1.2rem;
            border-bottom: 2px solid #ff6b6b;
            padding-bottom: 10px;
        }
        
        .tool-link {
            display: block;
            color: #4ecdc4;
            text-decoration: none;
            padding: 12px 15px;
            margin: 8px 0;
            background: rgba(78, 205, 196, 0.1);
            border-radius: 8px;
            transition: all 0.3s ease;
            border-left: 4px solid #4ecdc4;
        }
        
        .tool-link:hover {
            background: rgba(78, 205, 196, 0.2);
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(78, 205, 196, 0.3);
        }
        
        .tool-description {
            font-size: 0.9rem;
            color: #bbb;
            margin-top: 5px;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .quick-btn {
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4);
            color: white;
            text-decoration: none;
            padding: 15px 20px;
            border-radius: 10px;
            text-align: center;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .quick-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }
        
        .info-section {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 25px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin-top: 30px;
        }
        
        .info-section h3 {
            color: #45b7d1;
            margin-bottom: 15px;
        }
        
        .log-viewer {
            background: #1a1a1a;
            border-radius: 8px;
            padding: 15px;
            color: #0f0;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 300px;
            overflow-y: auto;
            white-space: pre-wrap;
            margin-top: 15px;
        }
        
        @media (max-width: 768px) {
            .tools-grid {
                grid-template-columns: 1fr;
            }
            .status-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 Debug Tools</h1>
            <p>Server Management Framework - Entwickler-Tools und Fehleranalyse</p>
        </div>
        
        <!-- System Status -->
        <div class="status-grid">
            <div class="status-card">
                <h3>🖥️ System Status</h3>
                <div class="status-item">
                    <span>PHP Version:</span>
                    <span class="<?= version_compare(PHP_VERSION, '7.4', '>=') ? 'status-ok' : 'status-error' ?>">
                        <?= $system_status['php_version'] ?>
                    </span>
                </div>
                <div class="status-item">
                    <span>Server:</span>
                    <span class="status-ok"><?= htmlspecialchars($system_status['server']) ?></span>
                </div>
                <div class="status-item">
                    <span>Database:</span>
                    <span class="<?= strpos($system_status['database'], 'connected') !== false ? 'status-ok' : 'status-error' ?>">
                        <?= $system_status['database'] ?>
                    </span>
                </div>
                <div class="status-item">
                    <span>Session:</span>
                    <span class="<?= strpos($system_status['session'], 'error') === false ? 'status-ok' : 'status-error' ?>">
                        <?= $system_status['session'] ?>
                    </span>
                </div>
            </div>
            
            <div class="status-card">
                <h3>🔌 PHP Extensions</h3>
                <?php foreach ($system_status['extensions'] as $ext => $loaded): ?>
                <div class="status-item">
                    <span><?= $ext ?>:</span>
                    <span class="<?= $loaded ? 'status-ok' : 'status-error' ?>">
                        <?= $loaded ? '✅ Loaded' : '❌ Missing' ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="status-card">
                <h3>📁 File Status</h3>
                <div class="status-item">
                    <span>framework.php:</span>
                    <span class="<?= $framework_exists ? 'status-ok' : 'status-error' ?>">
                        <?= $framework_exists ? '✅ Found' : '❌ Missing' ?>
                    </span>
                </div>
                <div class="status-item">
                    <span>auth_handler.php:</span>
                    <span class="<?= $auth_exists ? 'status-ok' : 'status-error' ?>">
                        <?= $auth_exists ? '✅ Found' : '❌ Missing' ?>
                    </span>
                </div>
                <div class="status-item">
                    <span>index.php:</span>
                    <span class="<?= $main_index_exists ? 'status-ok' : 'status-error' ?>">
                        <?= $main_index_exists ? '✅ Found' : '❌ Missing' ?>
                    </span>
                </div>
                <div class="status-item">
                    <span>Framework:</span>
                    <span class="<?= $system_status['framework'] === 'loaded' ? 'status-ok' : 'status-error' ?>">
                        <?= $system_status['framework'] ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="../index.php" class="quick-btn">🏠 Zur Hauptseite</a>
            <a href="../login.php" class="quick-btn">🔐 Login-Seite</a>
            <a href="../setup.php" class="quick-btn">⚙️ Setup</a>
            <a href="endpoints_test.php" class="quick-btn">🔌 Endpoint Tester</a>
            <a href="quick_fixes.php" class="quick-btn">🔧 Quick Fix</a>
            <a href="../auth.php" class="quick-btn">🔍 Auth Test</a>
        </div>
        
        <!-- Debug Tools -->
        <div class="tools-grid">
            <div class="tool-category">
                <h3>🔍 Allgemeine Debug-Tools</h3>
                <a href="debug.php" class="tool-link">
                    🐛 Allgemeiner Debug
                    <div class="tool-description">Umfassende System-Informationen und JavaScript-Konsole</div>
                </a>
                <a href="ajax_debug.php" class="tool-link">
                    📡 AJAX Debug Tool
                    <div class="tool-description">Detaillierte AJAX-Request Analyse und Response-Debugging</div>
                </a>
                <a href="safe_handler.php" class="tool-link">
                    🛡️ Sicherer Handler
                    <div class="tool-description">Isolierter Handler mit verbesserter Fehlerbehandlung</div>
                </a>
                <a href="quick_fixes.php" class="tool-link">
                    ⚡ Quick Fixes
                    <div class="tool-description">Automatische Reparatur häufiger Probleme</div>
                </a>
            </div>
            
            <div class="tool-category">
                <h3>🗄️ Datenbank Debug-Tools</h3>
                <a href="database_fix.php" class="tool-link">
                    🔧 Database Fix
                    <div class="tool-description">SQL-Syntaxfehler korrigieren und Activity Log reparieren</div>
                </a>
                <a href="database_fix.php?test=1" class="tool-link">
                    🧪 Database Test
                    <div class="tool-description">Datenbank-Verbindung und Tabellen testen</div>
                </a>
            </div>
            
            <div class="tool-category">
                <h3>🌐 API Debug-Tools</h3>
                <a href="ispconfig_debug.php" class="tool-link">
                    🌐 ISPConfig Debug
                    <div class="tool-description">Systematisches ISPConfig-API Debugging</div>
                </a>
                <a href="soap_test.php" class="tool-link">
                    🧼 SOAP Test
                    <div class="tool-description">SOAP-Extension und ISPConfig-Verbindung testen</div>
                </a>
                <a href="endpoints_test.php" class="tool-link">
                    🔌 Endpoint Tester
                    <div class="tool-description">Alle verfügbaren API-Endpoints direkt testen</div>
                </a>
            </div>
            
            <div class="tool-category">
                <h3>🔐 Authentication Debug</h3>
                <a href="../auth.php?mode=full" class="tool-link">
                    🔍 Vollständiger Auth Test
                    <div class="tool-description">Komplette API-Authentifizierung testen</div>
                </a>
                <a href="../auth.php?mode=quick" class="tool-link">
                    ⚡ Schneller Auth Test
                    <div class="tool-description">Basis-Verbindungen zu allen APIs prüfen</div>
                </a>
                <a href="../auth.php?mode=config" class="tool-link">
                    ⚙️ Konfiguration prüfen
                    <div class="tool-description">API-Konfiguration und Zugangsdaten validieren</div>
                </a>
            </div>
            
            <div class="tool-category">
                <h3>📊 Log und Monitoring</h3>
                <a href="javascript:viewLogs('error')" class="tool-link">
                    📝 Error Logs
                    <div class="tool-description">PHP Error Log anzeigen</div>
                </a>
                <a href="javascript:viewLogs('access')" class="tool-link">
                    📈 Access Logs
                    <div class="tool-description">Apache/Nginx Access Log anzeigen</div>
                </a>
                <a href="javascript:viewLogs('activity')" class="tool-link">
                    🎯 Activity Log
                    <div class="tool-description">Anwendungs-Activity Log anzeigen</div>
                </a>
            </div>
            
            <div class="tool-category">
                <h3>🔧 Spezielle Tools</h3>
                <a href="javascript:runQuickFix()" class="tool-link">
                    🔧 Auto-Fix ausführen
                    <div class="tool-description">Automatische Problemlösung starten</div>
                </a>
                <a href="javascript:clearLogs()" class="tool-link">
                    🗑️ Logs löschen
                    <div class="tool-description">Alle Log-Dateien leeren</div>
                </a>
                <a href="javascript:testAll()" class="tool-link">
                    🧪 Volltest
                    <div class="tool-description">Alle verfügbaren Tests ausführen</div>
                </a>
            </div>
        </div>
        
        <!-- Information Section -->
        <div class="info-section">
            <h3>📋 Debug-Informationen</h3>
            <p><strong>Zweck:</strong> Diese Debug-Tools helfen bei der Entwicklung und Fehleranalyse des Server Management Frameworks.</p>
            <p><strong>Sicherheit:</strong> Diese Tools sollten in Produktionsumgebungen NICHT zugänglich sein!</p>
            <p><strong>Aktualisierung:</strong> <?= date('d.m.Y H:i:s') ?></p>
            
            <h4 style="margin-top: 20px; color: #ffd43b;">⚠️ Häufige Probleme:</h4>
            <ul style="margin: 10px 0 0 20px; line-height: 1.6;">
                <li><strong>SOAP Extension fehlt:</strong> <code>sudo apt-get install php-soap</code></li>
                <li><strong>Datenbank-Verbindung:</strong> framework.php Konfiguration prüfen</li>
                <li><strong>Session-Probleme:</strong> PHP Session-Konfiguration und Verzeichnis-Rechte</li>
                <li><strong>ISPConfig API:</strong> Remote API aktivieren und Benutzer-Berechtigung prüfen</li>
                <li><strong>AJAX-Fehler:</strong> Browser-Entwicklertools → Network Tab für Details</li>
            </ul>
            
            <div id="logViewer" class="log-viewer" style="display: none;">
                <div id="logContent">Log-Viewer bereit...</div>
            </div>
        </div>
    </div>

    <script>
        // Log Viewer
        async function viewLogs(type) {
            const logViewer = document.getElementById('logViewer');
            const logContent = document.getElementById('logContent');
            
            logViewer.style.display = 'block';
            logContent.textContent = 'Lade Logs...';
            
            try {
                const response = await fetch(`log_viewer.php?type=${type}`);
                const text = await response.text();
                logContent.textContent = text || 'Keine Logs verfügbar';
            } catch (error) {
                logContent.textContent = 'Fehler beim Laden der Logs: ' + error.message;
            }
        }
        
        // Quick Fix ausführen
        async function runQuickFix() {
            if (!confirm('Auto-Fix ausführen? Dies kann System-Einstellungen ändern.')) {
                return;
            }
            
            try {
                const response = await fetch('quick_fixes.php?autofix=1');
                const text = await response.text();
                alert('Auto-Fix abgeschlossen. Details in der Konsole.');
                console.log(text);
                location.reload();
            } catch (error) {
                alert('Fehler beim Auto-Fix: ' + error.message);
            }
        }
        
        // Logs löschen
        async function clearLogs() {
            if (!confirm('Alle Logs löschen? Diese Aktion kann nicht rückgängig gemacht werden.')) {
                return;
            }
            
            try {
                const response = await fetch('clear_logs.php');
                const result = await response.json();
                alert(result.message || 'Logs gelöscht');
                location.reload();
            } catch (error) {
                alert('Fehler beim Löschen der Logs: ' + error.message);
            }
        }
        
        // Volltest
        async function testAll() {
            if (!confirm('Volltest ausführen? Dies kann einige Minuten dauern.')) {
                return;
            }
            
            const logViewer = document.getElementById('logViewer');
            const logContent = document.getElementById('logContent');
            
            logViewer.style.display = 'block';
            logContent.textContent = 'Volltest läuft...\n';
            
            const tests = [
                'debug.php',
                'database_fix.php?test=1',
                'soap_test.php',
                '../auth.php?mode=quick'
            ];
            
            for (const test of tests) {
                try {
                    logContent.textContent += `\n--- Testing ${test} ---\n`;
                    const response = await fetch(test);
                    const text = await response.text();
                    logContent.textContent += text.substring(0, 500) + '...\n';
                } catch (error) {
                    logContent.textContent += `ERROR: ${error.message}\n`;
                }
            }
            
            logContent.textContent += '\n=== Volltest abgeschlossen ===';
        }
        
        // Auto-refresh System Status
        setInterval(async () => {
            try {
                const response = await fetch('?ajax=status');
                if (response.ok) {
                    const data = await response.json();
                    // Update status indicators
                    console.log('Status updated:', data);
                }
            } catch (error) {
                console.warn('Status update failed:', error);
            }
        }, 30000); // Alle 30 Sekunden
        
        // Keyboard Shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                switch (e.key) {
                    case '1':
                        e.preventDefault();
                        window.open('debug.php', '_blank');
                        break;
                    case '2':
                        e.preventDefault();
                        window.open('ajax_debug.php', '_blank');
                        break;
                    case '3':
                        e.preventDefault();
                        window.open('endpoints_test.php', '_blank');
                        break;
                    case 'r':
                        e.preventDefault();
                        location.reload();
                        break;
                }
            }
        });
        
        console.log('🔧 Debug Tools loaded');
        console.log('Shortcuts: Ctrl+1 (Debug), Ctrl+2 (AJAX), Ctrl+3 (Endpoints), Ctrl+R (Reload)');
    </script>
</body>
</html>

<?php
// AJAX Status Update
if (isset($_GET['ajax']) && $_GET['ajax'] === 'status') {
    header('Content-Type: application/json');
    echo json_encode(getSystemStatus());
    exit;
}
?>