<?php
/**
 * AJAX Debug Tool - Zeigt was wirklich vom Server kommt
 */

// Debug aktivieren
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>AJAX Debug Tool</title>
    <style>
        body { font-family: monospace; margin: 20px; background: #1e1e1e; color: #fff; }
        .debug-box { background: #2d2d2d; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .error { border-left: 4px solid #ff6b6b; }
        .success { border-left: 4px solid #51cf66; }
        .warning { border-left: 4px solid #ffd43b; }
        pre { background: #1a1a1a; padding: 10px; border-radius: 3px; white-space: pre-wrap; word-wrap: break-word; max-height: 400px; overflow-y: auto; }
        button { background: #007acc; color: white; border: none; padding: 10px 15px; margin: 5px; border-radius: 3px; cursor: pointer; }
        button:hover { background: #005a9e; }
        .response-section { margin-top: 20px; }
        .tabs { display: flex; margin-bottom: 10px; }
        .tab { padding: 8px 15px; background: #444; cursor: pointer; margin-right: 5px; border-radius: 3px 3px 0 0; }
        .tab.active { background: #007acc; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>
    <h1>🔍 AJAX Debug Tool - ISPConfig Fehleranalyse</h1>
    
    <div class="debug-box">
        <h3>🧪 Tests verfügbar:</h3>
        <button onclick="testHandler()">📡 Handler.php direkt testen</button>
        <button onclick="testISPConfig()">🌐 ISPConfig API testen</button>
        <button onclick="testSOAP()">🔧 SOAP-Extension prüfen</button>
        <button onclick="testDatabase()">🗄️ Datenbank testen</button>
        <button onclick="clearResults()">🗑️ Ergebnisse löschen</button>
    </div>
    
    <div class="response-section">
        <div class="tabs">
            <div class="tab active" onclick="showTab('raw')">📄 Raw Response</div>
            <div class="tab" onclick="showTab('headers')">📋 Headers</div>
            <div class="tab" onclick="showTab('network')">🌐 Network Info</div>
            <div class="tab" onclick="showTab('console')">🔍 Console</div>
        </div>
        
        <div id="raw" class="tab-content active">
            <div class="debug-box">
                <h3>📄 Server Response (Raw):</h3>
                <pre id="raw-response">Noch keine Anfrage gesendet...</pre>
            </div>
        </div>
        
        <div id="headers" class="tab-content">
            <div class="debug-box">
                <h3>📋 Response Headers:</h3>
                <pre id="response-headers">Noch keine Anfrage gesendet...</pre>
            </div>
        </div>
        
        <div id="network" class="tab-content">
            <div class="debug-box">
                <h3>🌐 Network Information:</h3>
                <pre id="network-info">Noch keine Anfrage gesendet...</pre>
            </div>
        </div>
        
        <div id="console" class="tab-content">
            <div class="debug-box">
                <h3>🔍 Console Output:</h3>
                <pre id="console-output">Bereit für Tests...</pre>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Alle Tab-Inhalte verstecken
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Alle Tabs deaktivieren
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Gewählten Tab aktivieren
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        function log(message, type = 'info') {
            const console = document.getElementById('console-output');
            const timestamp = new Date().toLocaleTimeString();
            const icon = type === 'error' ? '❌' : type === 'success' ? '✅' : '📝';
            console.innerHTML += `${icon} [${timestamp}] ${message}\n`;
            console.scrollTop = console.scrollHeight;
        }
        
        function clearResults() {
            document.getElementById('raw-response').textContent = 'Ergebnisse gelöscht...';
            document.getElementById('response-headers').textContent = 'Ergebnisse gelöscht...';
            document.getElementById('network-info').textContent = 'Ergebnisse gelöscht...';
            document.getElementById('console-output').textContent = 'Console geleert...\n';
        }
        
        async function makeDetailedRequest(url, data, testName) {
            log(`${testName} wird gestartet...`);
            
            try {
                const startTime = performance.now();
                
                const response = await fetch(url, {
                    method: 'POST',
                    body: data
                });
                
                const endTime = performance.now();
                const duration = Math.round(endTime - startTime);
                
                // Network Info sammeln
                const networkInfo = `
Request URL: ${url}
Method: POST
Status: ${response.status} ${response.statusText}
Response Time: ${duration}ms
Content-Type: ${response.headers.get('content-type')}
Content-Length: ${response.headers.get('content-length')}
                `;
                document.getElementById('network-info').textContent = networkInfo;
                
                // Headers sammeln
                let headersText = '';
                response.headers.forEach((value, key) => {
                    headersText += `${key}: ${value}\n`;
                });
                document.getElementById('response-headers').textContent = headersText;
                
                // Response Text holen
                const responseText = await response.text();
                document.getElementById('raw-response').textContent = responseText;
                
                // Versuche JSON zu parsen
                try {
                    const jsonData = JSON.parse(responseText);
                    log(`${testName} erfolgreich - JSON geparst`, 'success');
                    log(`Response: ${JSON.stringify(jsonData, null, 2)}`);
                } catch (jsonError) {
                    log(`${testName} - JSON Parse Fehler: ${jsonError.message}`, 'error');
                    log(`Response ist kein gültiges JSON. Erste 200 Zeichen:`);
                    log(responseText.substring(0, 200));
                    
                    // Prüfe ob es HTML ist
                    if (responseText.includes('<html') || responseText.includes('<!DOCTYPE')) {
                        log('❌ Server gibt HTML zurück statt JSON - wahrscheinlich PHP-Fehler!', 'error');
                    }
                }
                
            } catch (error) {
                log(`${testName} - Netzwerk-Fehler: ${error.message}`, 'error');
                document.getElementById('raw-response').textContent = `FEHLER: ${error.message}`;
            }
        }
        
        async function testHandler() {
            const formData = new FormData();
            formData.append('action', 'test_handler');
            
            await makeDetailedRequest('handler.php', formData, 'Handler.php Test');
        }
        
        async function testISPConfig() {
            const formData = new FormData();
            formData.append('action', 'get_ispconfig_server_config');
            
            await makeDetailedRequest('', formData, 'ISPConfig API Test');
        }
        
        async function testSOAP() {
            const formData = new FormData();
            formData.append('action', 'test_soap');
            
            await makeDetailedRequest('soap_test.php', formData, 'SOAP Extension Test');
        }
        
        async function testDatabase() {
            const formData = new FormData();
            formData.append('action', 'get_activity_log');
            
            await makeDetailedRequest('', formData, 'Datenbank Test');
        }
        
        // Auto-Test beim Laden
        document.addEventListener('DOMContentLoaded', function() {
            log('AJAX Debug Tool gestartet');
            log('Führe automatischen Handler-Test aus...');
            testHandler();
        });
    </script>
</body>
</html>