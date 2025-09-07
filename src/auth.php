<?php
/**
 * Server Management Framework
 * 
 * @author Teris
 * @version 3.1.2
 */

// Error Reporting aktivieren
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Output buffering starten für saubere Ausgabe
ob_start();

// Check if running via web browser
$is_web = !isset($argc);

// Web-Interface CSS und HTML
if ($is_web) {
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>🔍 Authentication Tester</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Courier New', monospace;
                background: #1e1e1e;
                color: #00ff00;
                padding: 20px;
                line-height: 1.6;
            }
            .container {
                max-width: 1200px;
                margin: 0 auto;
                background: #2d2d2d;
                border-radius: 10px;
                padding: 20px;
                box-shadow: 0 0 20px rgba(0, 255, 0, 0.1);
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
                padding: 20px;
                background: #3d3d3d;
                border-radius: 8px;
            }
            .header h1 {
                color: #00ffff;
                font-size: 2rem;
                margin-bottom: 10px;
            }
            .test-section {
                background: #333;
                border-radius: 8px;
                padding: 20px;
                margin: 20px 0;
                border-left: 4px solid #00ff00;
            }
            .success { border-left-color: #00ff00; }
            .error { border-left-color: #ff0000; color: #ff6666; }
            .warning { border-left-color: #ffff00; color: #ffff99; }
            .info { border-left-color: #00ffff; color: #66ffff; }
            
            .test-controls {
                display: flex;
                gap: 15px;
                flex-wrap: wrap;
                margin-bottom: 20px;
            }
            .btn {
                background: #007acc;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 5px;
                cursor: pointer;
                font-family: inherit;
                font-size: 14px;
                transition: all 0.3s ease;
            }
            .btn:hover {
                background: #005a9e;
                transform: translateY(-2px);
            }
            .btn-success { background: #28a745; }
            .btn-danger { background: #dc3545; }
            .btn-warning { background: #ffc107; color: #000; }
            
            .output {
                background: #1a1a1a;
                padding: 20px;
                border-radius: 8px;
                white-space: pre-wrap;
                word-wrap: break-word;
                max-height: 600px;
                overflow-y: auto;
                border: 1px solid #444;
                font-size: 13px;
            }
            .config-display {
                background: #2a2a2a;
                padding: 15px;
                border-radius: 8px;
                margin: 15px 0;
            }
            .config-item {
                display: flex;
                justify-content: space-between;
                margin: 5px 0;
                padding: 5px 0;
                border-bottom: 1px solid #444;
            }
            .config-item:last-child {
                border-bottom: none;
            }
            .status-ok { color: #00ff00; }
            .status-error { color: #ff6666; }
            .status-warning { color: #ffff99; }
            
            @media (max-width: 768px) {
                .test-controls {
                    flex-direction: column;
                }
                .btn {
                    width: 100%;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🔍 Authentication Tester</h1>
                <p>Server Management Framework - API Verbindungen testen</p>
            </div>
            
            <div class="test-controls">
                <button class="btn btn-success" onclick="runTest('all')">🧪 Alle APIs testen</button>
                <button class="btn" onclick="runTest('proxmox')">🖥️ Proxmox</button>
                <button class="btn" onclick="runTest('ispconfig')">🌐 ISPConfig</button>
                <button class="btn" onclick="runTest('ovh')">🔗 OVH</button>
                <button class="btn btn-warning" onclick="runTest('config')">⚙️ Konfiguration</button>
                <button class="btn btn-danger" onclick="clearOutput()">🗑️ Löschen</button>
            </div>
            
            <div id="output" class="output">
Bereit für Tests. Wählen Sie eine Option oben.
            </div>
        </div>
        
        <script>
            async function runTest(type) {
                const output = document.getElementById('output');
                output.textContent = `🔄 ${type.toUpperCase()} Test läuft...\n`;
                
                try {
                    const response = await fetch('?test=' + type + '&ajax=1');
                    const text = await response.text();
                    output.textContent = text;
                } catch (error) {
                    output.textContent = `❌ Fehler: ${error.message}`;
                }
            }
            
            function clearOutput() {
                document.getElementById('output').textContent = 'Output gelöscht. Bereit für neue Tests.';
            }
            
            // Auto-load full test if no parameters
            if (!window.location.search) {
                setTimeout(() => runTest('config'), 1000);
            }
        </script>
    </body>
    </html>
    <?php
    
    // Wenn AJAX-Request, nur den Test-Output ausgeben
    if (isset($_GET['ajax'])) {
        ob_clean(); // Clear HTML output
        header('Content-Type: text/plain');
    }
}

// Framework laden mit Fehlerbehandlung
try {
    if (!file_exists('../framework.php')) {
        throw new Exception("framework.php nicht gefunden!");
    }
    require_once '../framework.php';
    $framework_loaded = true;
} catch (Exception $e) {
    $framework_loaded = false;
    $framework_error = $e->getMessage();
}

class AuthenticationTester {
    
    private $results = [];
    private $is_web = false;
    
    public function __construct($is_web = false) {
        $this->is_web = $is_web;
        $this->results = [
            'proxmox' => ['status' => 'pending', 'message' => '', 'details' => []],
            'ispconfig' => ['status' => 'pending', 'message' => '', 'details' => []],
            'ovh' => ['status' => 'pending', 'message' => '', 'details' => []]
        ];
    }
    
    /**
     * Testet alle API-Verbindungen
     */
    public function testAllConnections() {
        $this->output("🔍 Teste API-Verbindungen...\n\n");
        
        $this->testProxmoxConnection();
        $this->testISPConfigConnection();
        $this->testOVHConnection();
        
        $this->displayResults();
        return $this->results;
    }
    
    /**
     * Testet nur Proxmox Verbindung
     */
    public function testProxmoxConnection() {
        $this->output("🖥️  Teste Proxmox Verbindung...\n");
        
        try {
            if (!class_exists('ProxmoxGet')) {
                throw new Exception("ProxmoxGet Klasse nicht gefunden - Framework Problem");
            }
            
            $proxmox = new ProxmoxGet();
            
            // Test 1: Nodes abrufen
            $nodes = $proxmox->getNodes();
            
            if ($nodes && is_array($nodes) && count($nodes) > 0) {
                $this->results['proxmox']['status'] = 'success';
                $this->results['proxmox']['message'] = 'Verbindung erfolgreich';
                $this->results['proxmox']['details'] = [
                    'nodes_found' => count($nodes),
                    'nodes' => array_column($nodes, 'node'),
                    'host' => Config::PROXMOX_HOST,
                    'user' => Config::PROXMOX_USER
                ];
                $this->output("   ✅ Erfolgreich verbunden - " . count($nodes) . " Node(s) gefunden\n");
                
                // Test 2: VMs abrufen (falls vorhanden)
                try {
                    $vms = $proxmox->getVMs();
                    $this->results['proxmox']['details']['vms_found'] = count($vms);
                    $this->output("   📊 " . count($vms) . " VM(s) gefunden\n");
                } catch (Exception $e) {
                    $this->output("   ⚠️  Warnung bei VM-Abfrage: " . $e->getMessage() . "\n");
                }
                
            } else {
                $this->results['proxmox']['status'] = 'error';
                $this->results['proxmox']['message'] = 'Keine Nodes gefunden - Authentifizierung fehlgeschlagen';
                $this->output("   ❌ Fehler: Keine Nodes gefunden\n");
            }
            
        } catch (Exception $e) {
            $this->results['proxmox']['status'] = 'error';
            $this->results['proxmox']['message'] = 'Verbindungsfehler: ' . $e->getMessage();
            $this->results['proxmox']['details']['error'] = $e->getMessage();
            $this->output("   ❌ Fehler: " . $e->getMessage() . "\n");
        }
        
        $this->output("\n");
    }
    
    /**
     * Testet nur ISPConfig Verbindung
     */
    public function testISPConfigConnection() {
        $this->output("🌐 Teste ISPConfig Verbindung...\n");
        
        try {
            // SOAP Extension Check
            if (!extension_loaded('soap')) {
                throw new Exception("SOAP Extension nicht geladen - sudo apt-get install php-soap");
            }
            
            if (!class_exists('ISPConfigGet')) {
                throw new Exception("ISPConfigGet Klasse nicht gefunden - Framework Problem");
            }
            
            $ispconfig = new ISPConfigGet();
            
            // Test 1: Server Config abrufen
            $serverConfig = $ispconfig->getServerConfig();
            
            if ($serverConfig && is_array($serverConfig)) {
                $this->results['ispconfig']['status'] = 'success';
                $this->results['ispconfig']['message'] = 'Verbindung erfolgreich';
                $this->results['ispconfig']['details'] = [
                    'server_config_loaded' => true,
                    'host' => Config::ISPCONFIG_HOST,
                    'user' => Config::ISPCONFIG_USER
                ];
                $this->output("   ✅ Erfolgreich verbunden - Server Config geladen\n");
                
                // Test 2: Websites zählen
                try {
                    $websites = $ispconfig->getWebsites();
                    $this->results['ispconfig']['details']['websites_found'] = count($websites);
                    $this->output("   📊 " . count($websites) . " Website(s) gefunden\n");
                } catch (Exception $e) {
                    $this->output("   ⚠️  Warnung bei Website-Abfrage: " . $e->getMessage() . "\n");
                }
                
                // Test 3: Datenbanken zählen
                try {
                    $databases = $ispconfig->getDatabases();
                    $this->results['ispconfig']['details']['databases_found'] = count($databases);
                    $this->output("   📊 " . count($databases) . " Datenbank(en) gefunden\n");
                } catch (Exception $e) {
                    $this->output("   ⚠️  Warnung bei Datenbank-Abfrage: " . $e->getMessage() . "\n");
                }
                
                // Test 4: E-Mail Accounts zählen
                try {
                    $emails = $ispconfig->getEmailAccounts();
                    $this->results['ispconfig']['details']['emails_found'] = count($emails);
                    $this->output("   📊 " . count($emails) . " E-Mail Account(s) gefunden\n");
                } catch (Exception $e) {
                    $this->output("   ⚠️  Warnung bei E-Mail-Abfrage: " . $e->getMessage() . "\n");
                }
                
            } else {
                $this->results['ispconfig']['status'] = 'error';
                $this->results['ispconfig']['message'] = 'Server Config konnte nicht geladen werden - Authentifizierung fehlgeschlagen';
                $this->output("   ❌ Fehler: Server Config nicht verfügbar\n");
            }
            
        } catch (Exception $e) {
            $this->results['ispconfig']['status'] = 'error';
            $this->results['ispconfig']['message'] = 'SOAP Verbindungsfehler: ' . $e->getMessage();
            $this->results['ispconfig']['details']['error'] = $e->getMessage();
            $this->output("   ❌ Fehler: " . $e->getMessage() . "\n");
        }
        
        $this->output("\n");
    }
    
    /**
     * Testet nur OVH Verbindung
     */
    public function testOVHConnection() {
        $this->output("🔗 Teste OVH Verbindung...\n");
        
        try {
            if (!class_exists('OVHGet')) {
                throw new Exception("OVHGet Klasse nicht gefunden - Framework Problem");
            }
            
            $ovh = new OVHGet();
            
            // Test 1: Domains abrufen
            $domains = $ovh->getDomains();
            
            if ($domains && is_array($domains)) {
                $this->results['ovh']['status'] = 'success';
                $this->results['ovh']['message'] = 'Verbindung erfolgreich';
                $this->results['ovh']['details'] = [
                    'domains_found' => count($domains),
                    'endpoint' => Config::OVH_ENDPOINT,
                    'app_key' => substr(Config::OVH_APPLICATION_KEY, 0, 8) . '...'
                ];
                $this->output("   ✅ Erfolgreich verbunden - " . count($domains) . " Domain(s) gefunden\n");
                
                // Domain Details anzeigen (erste 3)
                if (count($domains) > 0) {
                    $sampleDomains = array_slice($domains, 0, 3);
                    foreach ($sampleDomains as $domain) {
                        $domainName = is_object($domain) ? $domain->domain : $domain['domain'] ?? 'N/A';
                        $expiration = is_object($domain) ? $domain->expiration : $domain['expiration'] ?? 'N/A';
                        $this->output("   📝 Domain: " . $domainName . " (Expires: " . $expiration . ")\n");
                    }
                }
                
                // Test 2: VPS abrufen
                try {
                    $vpsList = $ovh->getDedicatedServers();
                    $this->results['ovh']['details']['servers_found'] = count($vpsList);
                    $this->output("   📊 " . count($vpsList) . " Server gefunden\n");
                    
                    // Server Details anzeigen (erste 2)
                    if (count($vpsList) > 0) {
                        $sampleVPS = array_slice($vpsList, 0, 2);
                        foreach ($sampleVPS as $vps) {
                            $this->output("   📝 Server: " . $vps . "\n");
                        }
                    }
                } catch (Exception $e) {
                    $this->output("   ⚠️  Warnung bei Server-Abfrage: " . $e->getMessage() . "\n");
                }
                
            } else {
                $this->results['ovh']['status'] = 'error';
                $this->results['ovh']['message'] = 'Keine Domains gefunden - API Key oder Permissions fehlerhaft';
                $this->output("   ❌ Fehler: Keine Domains verfügbar\n");
            }
            
        } catch (Exception $e) {
            $this->results['ovh']['status'] = 'error';
            $this->results['ovh']['message'] = 'API Verbindungsfehler: ' . $e->getMessage();
            $this->results['ovh']['details']['error'] = $e->getMessage();
            $this->output("   ❌ Fehler: " . $e->getMessage() . "\n");
        }
        
        $this->output("\n");
    }
    
    /**
     * Zeigt Zusammenfassung der Testergebnisse
     */
    public function displayResults() {
        $this->output("📋 ZUSAMMENFASSUNG:\n");
        $this->output(str_repeat("=", 50) . "\n");
        
        $successCount = 0;
        $totalCount = 3;
        
        foreach ($this->results as $api => $result) {
            $status = $result['status'];
            $icon = $status === 'success' ? '✅' : '❌';
            $apiName = strtoupper($api);
            
            $this->output(sprintf("%-15s %s %s\n", $apiName, $icon, $result['message']));
            
            if ($status === 'success') {
                $successCount++;
            }
        }
        
        $this->output(str_repeat("=", 50) . "\n");
        $this->output(sprintf("Erfolgreich: %d/%d APIs\n", $successCount, $totalCount));
        
        if ($successCount === $totalCount) {
            $this->output("🎉 Alle APIs sind korrekt konfiguriert!\n");
        } else {
            $this->output("⚠️  Bitte überprüfen Sie die Konfiguration der fehlgeschlagenen APIs.\n");
        }
        
        $this->output("\n");
    }
    
    /**
     * Prüft Konfiguration
     */
    public function checkConfiguration() {
        $this->output("⚙️  KONFIGURATIONSPRÜFUNG:\n");
        $this->output(str_repeat("=", 50) . "\n");
        
        $config_issues = [];
        
        // Framework Check
        global $framework_loaded, $framework_error;
        if (!$framework_loaded) {
            $config_issues[] = "Framework nicht geladen: " . ($framework_error ?? 'Unbekannter Fehler');
        } else {
            $this->output("✅ Framework erfolgreich geladen\n");
        }
        
        // Proxmox Config prüfen
        if (!defined('Config::PROXMOX_HOST') || Config::PROXMOX_HOST === 'https://your-proxmox-host:8006') {
            $config_issues[] = "Proxmox Host nicht konfiguriert";
        }
        if (!defined('Config::PROXMOX_USER') || Config::PROXMOX_USER === 'root@pam') {
            $config_issues[] = "Proxmox User Standard-Wert";
        }
        if (!defined('Config::PROXMOX_PASSWORD') || Config::PROXMOX_PASSWORD === 'your_proxmox_password') {
            $config_issues[] = "Proxmox Passwort nicht gesetzt";
        }
        
        // ISPConfig Config prüfen
        if (!defined('Config::ISPCONFIG_HOST') || Config::ISPCONFIG_HOST === 'https://your-ispconfig-host:8080') {
            $config_issues[] = "ISPConfig Host nicht konfiguriert";
        }
        if (!defined('Config::ISPCONFIG_PASSWORD') || Config::ISPCONFIG_PASSWORD === 'your_ispconfig_password') {
            $config_issues[] = "ISPConfig Passwort nicht gesetzt";
        }
        
        // OVH Config prüfen
        if (!defined('Config::OVH_APPLICATION_KEY') || Config::OVH_APPLICATION_KEY === 'your_ovh_app_key') {
            $config_issues[] = "OVH Application Key nicht gesetzt";
        }
        if (!defined('Config::OVH_APPLICATION_SECRET') || Config::OVH_APPLICATION_SECRET === 'your_ovh_app_secret') {
            $config_issues[] = "OVH Application Secret nicht gesetzt";
        }
        if (!defined('Config::OVH_CONSUMER_KEY') || Config::OVH_CONSUMER_KEY === 'your_ovh_consumer_key') {
            $config_issues[] = "OVH Consumer Key nicht gesetzt";
        }
        
        // PHP Extensions prüfen
        $required_extensions = ['curl', 'soap', 'pdo_mysql', 'json'];
        $missing_extensions = [];
        
        foreach ($required_extensions as $ext) {
            if (!extension_loaded($ext)) {
                $missing_extensions[] = $ext;
            }
        }
        
        if (!empty($missing_extensions)) {
            $config_issues[] = "Fehlende PHP Extensions: " . implode(', ', $missing_extensions);
        }
        
        if (empty($config_issues)) {
            $this->output("✅ Konfiguration scheint vollständig zu sein.\n");
        } else {
            $this->output("❌ Konfigurationsprobleme gefunden:\n");
            foreach ($config_issues as $issue) {
                $this->output("   - $issue\n");
            }
        }
        
        $this->output("\n");
        if (class_exists('Config')) {
            $this->output("Aktuelle Konfiguration:\n");
            $this->output("  Proxmox: " . (defined('Config::PROXMOX_HOST') ? Config::PROXMOX_HOST : 'NICHT DEFINIERT') . "\n");
            $this->output("  ISPConfig: " . (defined('Config::ISPCONFIG_HOST') ? Config::ISPCONFIG_HOST : 'NICHT DEFINIERT') . "\n");
            $this->output("  OVH: " . (defined('Config::OVH_ENDPOINT') ? Config::OVH_ENDPOINT : 'NICHT DEFINIERT') . "\n");
        }
        $this->output("\n");
    }
    
    /**
     * Output-Funktion (Web oder CLI)
     */
    private function output($text) {
        if ($this->is_web) {
            echo htmlspecialchars($text);
        } else {
            echo $text;
        }
    }
    
    /**
     * Vollständiger Test mit allen Details
     */
    public function runFullTest() {
        $this->output("🚀 VOLLSTÄNDIGER API-AUTHENTIFIZIERUNGSTEST\n");
        $this->output(str_repeat("=", 60) . "\n\n");
        
        $this->checkConfiguration();
        $this->testAllConnections();
        
        return $this->results;
    }
    
    /**
     * Gibt JSON-Response für Web Interface zurück
     */
    public function getJSONResults() {
        return json_encode($this->results, JSON_PRETTY_PRINT);
    }
}

// =============================================================================
// AUSFÜHRUNG
// =============================================================================

if ($is_web) {
    // Web-Interface Handling
    if (isset($_GET['test']) && isset($_GET['ajax'])) {
        $tester = new AuthenticationTester(true);
        
        switch ($_GET['test']) {
            case 'all':
                $tester->runFullTest();
                break;
            case 'config':
                $tester->checkConfiguration();
                break;
            case 'proxmox':
                $tester->testProxmoxConnection();
                break;
            case 'ispconfig':
                $tester->testISPConfigConnection();
                break;
            case 'ovh':
                $tester->testOVHConnection();
                break;
            default:
                echo "Unknown test: " . htmlspecialchars($_GET['test']);
        }
    }
    // Andernfalls wird das HTML-Interface oben ausgegeben
} else {
    // Command Line Interface
    $tester = new AuthenticationTester(false);
    
    // Argument-Parser für verschiedene Test-Modi
    $mode = $argv[1] ?? 'full';
    
    switch ($mode) {
        case 'quick':
            $tester->testAllConnections();
            break;
        case 'config':
            $tester->checkConfiguration();
            break;
        case 'proxmox':
            $tester->testProxmoxConnection();
            break;
        case 'ispconfig':
            $tester->testISPConfigConnection();
            break;
        case 'ovh':
            $tester->testOVHConnection();
            break;
        case 'json':
            $tester->testAllConnections();
            echo $tester->getJSONResults();
            break;
        case 'full':
        default:
            $tester->runFullTest();
            break;
    }
}

// Output Buffer leeren
if ($is_web && !isset($_GET['ajax'])) {
    // HTML bereits ausgegeben, nichts zu tun
} else {
    ob_end_flush();
}
?>