<?php
/**
 * Authentication Tester
 * Testet die Verbindung zu allen APIs (Proxmox, ISPConfig, OVH)
 */

require_once 'framework.php';

class AuthenticationTester {
    
    private $results = [];
    
    public function __construct() {
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
        echo "🔍 Teste API-Verbindungen...\n\n";
        
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
        echo "🖥️  Teste Proxmox Verbindung...\n";
        
        try {
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
                echo "   ✅ Erfolgreich verbunden - " . count($nodes) . " Node(s) gefunden\n";
                
                // Test 2: VMs abrufen (falls vorhanden)
                try {
                    $vms = $proxmox->getVMs();
                    $this->results['proxmox']['details']['vms_found'] = count($vms);
                    echo "   📊 " . count($vms) . " VM(s) gefunden\n";
                } catch (Exception $e) {
                    echo "   ⚠️  Warnung bei VM-Abfrage: " . $e->getMessage() . "\n";
                }
                
            } else {
                $this->results['proxmox']['status'] = 'error';
                $this->results['proxmox']['message'] = 'Keine Nodes gefunden - Authentifizierung fehlgeschlagen';
                echo "   ❌ Fehler: Keine Nodes gefunden\n";
            }
            
        } catch (Exception $e) {
            $this->results['proxmox']['status'] = 'error';
            $this->results['proxmox']['message'] = 'Verbindungsfehler: ' . $e->getMessage();
            $this->results['proxmox']['details']['error'] = $e->getMessage();
            echo "   ❌ Fehler: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    /**
     * Testet nur ISPConfig Verbindung
     */
    public function testISPConfigConnection() {
        echo "🌐 Teste ISPConfig Verbindung...\n";
        
        try {
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
                echo "   ✅ Erfolgreich verbunden - Server Config geladen\n";
                
                // Test 2: Websites zählen
                try {
                    $websites = $ispconfig->getWebsites();
                    $this->results['ispconfig']['details']['websites_found'] = count($websites);
                    echo "   📊 " . count($websites) . " Website(s) gefunden\n";
                } catch (Exception $e) {
                    echo "   ⚠️  Warnung bei Website-Abfrage: " . $e->getMessage() . "\n";
                }
                
                // Test 3: Datenbanken zählen
                try {
                    $databases = $ispconfig->getDatabases();
                    $this->results['ispconfig']['details']['databases_found'] = count($databases);
                    echo "   📊 " . count($databases) . " Datenbank(en) gefunden\n";
                } catch (Exception $e) {
                    echo "   ⚠️  Warnung bei Datenbank-Abfrage: " . $e->getMessage() . "\n";
                }
                
                // Test 4: E-Mail Accounts zählen
                try {
                    $emails = $ispconfig->getEmailAccounts();
                    $this->results['ispconfig']['details']['emails_found'] = count($emails);
                    echo "   📊 " . count($emails) . " E-Mail Account(s) gefunden\n";
                } catch (Exception $e) {
                    echo "   ⚠️  Warnung bei E-Mail-Abfrage: " . $e->getMessage() . "\n";
                }
                
            } else {
                $this->results['ispconfig']['status'] = 'error';
                $this->results['ispconfig']['message'] = 'Server Config konnte nicht geladen werden - Authentifizierung fehlgeschlagen';
                echo "   ❌ Fehler: Server Config nicht verfügbar\n";
            }
            
        } catch (Exception $e) {
            $this->results['ispconfig']['status'] = 'error';
            $this->results['ispconfig']['message'] = 'SOAP Verbindungsfehler: ' . $e->getMessage();
            $this->results['ispconfig']['details']['error'] = $e->getMessage();
            echo "   ❌ Fehler: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    /**
     * Testet nur OVH Verbindung
     */
    public function testOVHConnection() {
        echo "🔗 Teste OVH Verbindung...\n";
        
        try {
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
                echo "   ✅ Erfolgreich verbunden - " . count($domains) . " Domain(s) gefunden\n";
                
                // Domain Details anzeigen (erste 3)
                if (count($domains) > 0) {
                    $sampleDomains = array_slice($domains, 0, 3);
                    foreach ($sampleDomains as $domain) {
                        echo "   📝 Domain: " . $domain->domain . " (Expires: " . ($domain->expiration ?? 'N/A') . ")\n";
                    }
                }
                
                // Test 2: VPS abrufen
                try {
                    $vpsList = $ovh->getDedicatedServers();
                    $this->results[0] = count($vpsList);
                    echo "   📊 " . count($vpsList) . " Server gefunden\n";
                    
                    //VPS Details anzeigen (erste 2)
                    if (count($vpsList) > 0) {
                       $sampleVPS = array_slice($vpsList, 0, 2);
                       foreach ($sampleVPS as $vps) {
                           echo "   📝 Server: " . $vps. " (Status: " . ($vps ?? 'N/A') . ")\n";
                       }
                    }
                } catch (Exception $e) {
                    echo "   ⚠️  Warnung bei Server-Abfrage: " . $e->getMessage() . "\n";
                }
                
            } else {
                $this->results['ovh']['status'] = 'error';
                $this->results['ovh']['message'] = 'Keine Domains gefunden - API Key oder Permissions fehlerhaft';
                echo "   ❌ Fehler: Keine Domains verfügbar\n";
            }
            
        } catch (Exception $e) {
            $this->results['ovh']['status'] = 'error';
            $this->results['ovh']['message'] = 'API Verbindungsfehler: ' . $e->getMessage();
            $this->results['ovh']['details']['error'] = $e->getMessage();
            echo "   ❌ Fehler: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    /**
     * Zeigt Zusammenfassung der Testergebnisse
     */
    public function displayResults() {
        echo "📋 ZUSAMMENFASSUNG:\n";
        echo str_repeat("=", 50) . "\n";
        
        $successCount = 0;
        $totalCount = 3;
        
        foreach ($this->results as $api => $result) {
            $status = $result['status'];
            $icon = $status === 'success' ? '✅' : '❌';
            $apiName = strtoupper($api);
            
            echo sprintf("%-15s %s %s\n", $apiName, $icon, $result['message']);
            
            if ($status === 'success') {
                $successCount++;
            }
        }
        
        echo str_repeat("=", 50) . "\n";
        echo sprintf("Erfolgreich: %d/%d APIs\n", $successCount, $totalCount);
        
        if ($successCount === $totalCount) {
            echo "🎉 Alle APIs sind korrekt konfiguriert!\n";
        } else {
            echo "⚠️  Bitte überprüfen Sie die Konfiguration der fehlgeschlagenen APIs.\n";
        }
        
        echo "\n";
    }
    
    /**
     * Gibt detaillierte Debug-Informationen aus
     */
    public function showDebugInfo() {
        echo "🔧 DEBUG INFORMATIONEN:\n";
        echo str_repeat("=", 50) . "\n";
        
        foreach ($this->results as $api => $result) {
            echo strtoupper($api) . " Details:\n";
            
            if (isset($result['details']) && is_array($result['details'])) {
                foreach ($result['details'] as $key => $value) {
                    if (is_array($value)) {
                        echo "  $key: " . implode(', ', $value) . "\n";
                    } else {
                        echo "  $key: $value\n";
                    }
                }
            }
            echo "\n";
        }
    }
    
    /**
     * Testet spezifische API-Endpunkte
     */
    public function testSpecificEndpoints() {
        echo "🎯 SPEZIFISCHE ENDPOINT TESTS:\n";
        echo str_repeat("=", 50) . "\n";
        
        // Proxmox spezifische Tests
        echo "Proxmox Endpoint Tests:\n";
        try {
            $proxmox = new ProxmoxGet();
            
            // Version Info
            $response = $proxmox->makeRequest('GET', Config::PROXMOX_HOST . '/api2/json/version');
            if ($response) {
                echo "  ✅ Version API: " . ($response['data']['version'] ?? 'N/A') . "\n";
            }
            
            // Cluster Status
            $response = $proxmox->makeRequest('GET', Config::PROXMOX_HOST . '/api2/json/cluster/status');
            if ($response) {
                echo "  ✅ Cluster API: " . count($response['data'] ?? []) . " Einträge\n";
            }
            
        } catch (Exception $e) {
            echo "  ❌ Proxmox Endpoint Fehler: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    /**
     * Prüft Konfiguration
     */
    public function checkConfiguration() {
        echo "⚙️  KONFIGURATIONSPRÜFUNG:\n";
        echo str_repeat("=", 50) . "\n";
        
        $config_issues = [];
        
        // Proxmox Config prüfen
        if (!Config::PROXMOX_HOST || Config::PROXMOX_HOST === 'https://your-proxmox-host:8006') {
            $config_issues[] = "Proxmox Host nicht konfiguriert";
        }
        if (!Config::PROXMOX_USER || Config::PROXMOX_USER === 'root@pam') {
            $config_issues[] = "Proxmox User Standard-Wert";
        }
        if (!Config::PROXMOX_PASSWORD || Config::PROXMOX_PASSWORD === 'your_proxmox_password') {
            $config_issues[] = "Proxmox Passwort nicht gesetzt";
        }
        
        // ISPConfig Config prüfen
        if (!Config::ISPCONFIG_HOST || Config::ISPCONFIG_HOST === 'https://your-ispconfig-host:8080') {
            $config_issues[] = "ISPConfig Host nicht konfiguriert";
        }
        if (!Config::ISPCONFIG_PASSWORD || Config::ISPCONFIG_PASSWORD === 'your_ispconfig_password') {
            $config_issues[] = "ISPConfig Passwort nicht gesetzt";
        }
        
        // OVH Config prüfen
        if (!Config::OVH_APPLICATION_KEY || Config::OVH_APPLICATION_KEY === 'your_ovh_app_key') {
            $config_issues[] = "OVH Application Key nicht gesetzt";
        }
        if (!Config::OVH_APPLICATION_SECRET || Config::OVH_APPLICATION_SECRET === 'your_ovh_app_secret') {
            $config_issues[] = "OVH Application Secret nicht gesetzt";
        }
        if (!Config::OVH_CONSUMER_KEY || Config::OVH_CONSUMER_KEY === 'your_ovh_consumer_key') {
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
            echo "✅ Konfiguration scheint vollständig zu sein.\n";
        } else {
            echo "❌ Konfigurationsprobleme gefunden:\n";
            foreach ($config_issues as $issue) {
                echo "   - $issue\n";
            }
        }
        
        echo "\n";
        echo "Aktuelle Konfiguration:\n";
        echo "  Proxmox: " . Config::PROXMOX_HOST . " (User: " . Config::PROXMOX_USER . ")\n";
        echo "  ISPConfig: " . Config::ISPCONFIG_HOST . " (User: " . Config::ISPCONFIG_USER . ")\n";
        echo "  OVH: " . Config::OVH_ENDPOINT . " (App Key: " . substr(Config::OVH_APPLICATION_KEY, 0, 8) . "...)\n";
        echo "\n";
    }
    
    /**
     * Vollständiger Test mit allen Details
     */
    public function runFullTest() {
        echo "🚀 VOLLSTÄNDIGER API-AUTHENTIFIZIERUNGSTEST\n";
        echo str_repeat("=", 60) . "\n\n";
        
        $this->checkConfiguration();
        $this->testAllConnections();
        $this->showDebugInfo();
        $this->testSpecificEndpoints();
        
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
// VERWENDUNG
// =============================================================================

// Wenn direkt aufgerufen (Command Line)
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $tester = new AuthenticationTester();
    
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

// =============================================================================
// WEB INTERFACE INTEGRATION
// =============================================================================

// Für AJAX Calls aus dem Web Interface
if (isset($_POST['test_auth'])) {
    header('Content-Type: application/json');
    
    $tester = new AuthenticationTester();
    $mode = $_POST['test_mode'] ?? 'all';
    
    switch ($mode) {
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
            $tester->testAllConnections();
            break;
    }
    
    echo $tester->getJSONResults();
    exit;
}

?>