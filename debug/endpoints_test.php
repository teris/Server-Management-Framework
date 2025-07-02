<?php
/**
 * Endpoint Tester - Alle verfügbaren PHP-Endpoints direkt testbar
 * Umfassender Tester für framework.php und handler.php Endpoints
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../framework.php';

// Mock Session für Tests (da einige Endpoints Session erfordern)
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'debug_user';
$_SESSION['role'] = 'admin';
$_SESSION['last_activity'] = time();

// Test Results Storage
$test_results = [];
$current_test = '';

// Logging function
function logTest($endpoint, $method, $params, $result, $success) {
    global $test_results;
    $test_results[] = [
        'endpoint' => $endpoint,
        'method' => $method,
        'params' => $params,
        'result' => $result,
        'success' => $success,
        'timestamp' => date('H:i:s'),
        'memory' => memory_get_usage(true)
    ];
}

// Safe test execution
function safeTest($callback, $description) {
    global $current_test;
    $current_test = $description;
    
    try {
        $start_time = microtime(true);
        $result = $callback();
        $execution_time = round((microtime(true) - $start_time) * 1000, 2);
        
        return [
            'success' => true,
            'result' => $result,
            'execution_time' => $execution_time,
            'description' => $description
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'description' => $description
        ];
    }
}

// AJAX Handler for endpoint testing
if (isset($_POST['test_endpoint'])) {
    header('Content-Type: application/json');
    
    $endpoint = $_POST['endpoint'];
    $method = $_POST['method'] ?? 'GET';
    $params = json_decode($_POST['params'] ?? '{}', true);
    
    try {
        $result = testEndpoint($endpoint, $method, $params);
        logTest($endpoint, $method, $params, $result, true);
        echo json_encode(['success' => true, 'result' => $result]);
    } catch (Exception $e) {
        logTest($endpoint, $method, $params, $e->getMessage(), false);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Main test function
function testEndpoint($endpoint, $method = 'GET', $params = []) {
    switch ($endpoint) {
        // === PROXMOX ENDPOINTS ===
        case 'proxmox_nodes':
            $proxmox = new ProxmoxGet();
            return $proxmox->getNodes();
            
        case 'proxmox_vms':
            $proxmox = new ProxmoxGet();
            return $proxmox->getVMs();
            
        case 'proxmox_vm_config':
            $proxmox = new ProxmoxGet();
            $node = $params['node'] ?? 'pve';
            $vmid = $params['vmid'] ?? '100';
            return $proxmox->getVMConfig($node, $vmid);
            
        case 'proxmox_vm_status':
            $proxmox = new ProxmoxGet();
            $node = $params['node'] ?? 'pve';
            $vmid = $params['vmid'] ?? '100';
            return $proxmox->getVMStatus($node, $vmid);
            
        case 'proxmox_storages':
            $proxmox = new ProxmoxGet();
            $node = $params['node'] ?? null;
            return $proxmox->getStorages($node);
            
        case 'proxmox_networks':
            $proxmox = new ProxmoxGet();
            $node = $params['node'] ?? 'pve';
            return $proxmox->getNetworks($node);
            
        // === ISPCONFIG ENDPOINTS ===
        case 'ispconfig_server_config':
            $ispconfig = new ISPConfigGet();
            return $ispconfig->getServerConfig();
            
        case 'ispconfig_websites':
            $ispconfig = new ISPConfigGet();
            return array_map(function($site) { return $site->toArray(); }, $ispconfig->getWebsites());
            
        case 'ispconfig_databases':
            $ispconfig = new ISPConfigGet();
            return array_map(function($db) { return $db->toArray(); }, $ispconfig->getDatabases());
            
        case 'ispconfig_emails':
            $ispconfig = new ISPConfigGet();
            return array_map(function($email) { return $email->toArray(); }, $ispconfig->getEmailAccounts());
            
        case 'ispconfig_clients':
            $ispconfig = new ISPConfigGet();
            return $ispconfig->getClients();
            
        // === OVH ENDPOINTS ===
        case 'ovh_domains':
            $ovh = new OVHGet();
            return array_map(function($domain) { return $domain->toArray(); }, $ovh->getDomains());
            
        case 'ovh_domain_zone':
            $ovh = new OVHGet();
            $domain = $params['domain'] ?? 'example.com';
            return $ovh->getDomainZone($domain);
            
        case 'ovh_dns_records':
            $ovh = new OVHGet();
            $domain = $params['domain'] ?? 'example.com';
            return $ovh->getDomainZoneRecords($domain);
            
        case 'ovh_vps_list':
            $ovh = new OVHGet();
            return array_map(function($vps) { return $vps->toArray(); }, $ovh->getVPSList());
            
        case 'ovh_vps_ips':
            $ovh = new OVHGet();
            $vpsName = $params['vps_name'] ?? 'vps-12345.vps.ovh.net';
            return $ovh->getVPSIPs($vpsName);
            
        case 'ovh_dedicated_servers':
            $ovh = new OVHGet();
            return $ovh->getDedicatedServers();
            
        // === SERVICE MANAGER ENDPOINTS ===
        case 'service_manager_vms':
            $serviceManager = new ServiceManager();
            return array_map(function($vm) { return $vm->toArray(); }, $serviceManager->getProxmoxVMs());
            
        case 'service_manager_websites':
            $serviceManager = new ServiceManager();
            return array_map(function($site) { return $site->toArray(); }, $serviceManager->getISPConfigWebsites());
            
        case 'service_manager_databases':
            $serviceManager = new ServiceManager();
            return array_map(function($db) { return $db->toArray(); }, $serviceManager->getISPConfigDatabases());
            
        case 'service_manager_emails':
            $serviceManager = new ServiceManager();
            return array_map(function($email) { return $email->toArray(); }, $serviceManager->getISPConfigEmails());
            
        case 'service_manager_domains':
            $serviceManager = new ServiceManager();
            return array_map(function($domain) { return $domain->toArray(); }, $serviceManager->getOVHDomains());
            
        case 'service_manager_vps':
            $serviceManager = new ServiceManager();
            return array_map(function($vps) { return $vps->toArray(); }, $serviceManager->getOVHVPS());
            
        // === DATABASE ENDPOINTS ===
        case 'database_activity_log':
            $db = Database::getInstance();
            return $db->getActivityLog(20);
            
        case 'database_connection':
            $db = Database::getInstance();
            $connection = $db->getConnection();
            $stmt = $connection->query("SELECT VERSION() as version, NOW() as current_time");
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        case 'database_tables':
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query("SHOW TABLES");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
            
        // === HANDLER.PHP SIMULATION ===
        case 'handler_get_all_vms':
            return simulateHandlerCall('get_all_vms', $params);
            
        case 'handler_get_all_websites':
            return simulateHandlerCall('get_all_websites', $params);
            
        case 'handler_get_all_databases':
            return simulateHandlerCall('get_all_databases', $params);
            
        case 'handler_get_all_emails':
            return simulateHandlerCall('get_all_emails', $params);
            
        case 'handler_get_all_domains':
            return simulateHandlerCall('get_all_domains', $params);
            
        case 'handler_get_all_vps':
            return simulateHandlerCall('get_all_vps', $params);
            
        case 'handler_get_activity_log':
            return simulateHandlerCall('get_activity_log', $params);
            
        // === MOCK DATA ENDPOINTS ===
        case 'mock_email_data':
            return [
                ['mailuser_id' => '1', 'email' => 'admin@example.com', 'login' => 'admin', 'name' => 'Administrator', 'domain' => 'example.com', 'quota' => '1000', 'active' => 'y'],
                ['mailuser_id' => '2', 'email' => 'support@example.com', 'login' => 'support', 'name' => 'Support Team', 'domain' => 'example.com', 'quota' => '2000', 'active' => 'y'],
                ['mailuser_id' => '3', 'email' => 'info@test.com', 'login' => 'info', 'name' => 'Information', 'domain' => 'test.com', 'quota' => '500', 'active' => 'y']
            ];
            
        case 'mock_vm_data':
            return [
                ['vmid' => '100', 'name' => 'web-server-01', 'node' => 'pve', 'status' => 'running', 'cores' => '2', 'memory' => '4096', 'disk' => '20'],
                ['vmid' => '101', 'name' => 'database-01', 'node' => 'pve', 'status' => 'running', 'cores' => '4', 'memory' => '8192', 'disk' => '50'],
                ['vmid' => '102', 'name' => 'backup-server', 'node' => 'pve2', 'status' => 'stopped', 'cores' => '1', 'memory' => '2048', 'disk' => '100']
            ];
            
        default:
            throw new Exception("Unknown endpoint: $endpoint");
    }
}

// Simulate handler.php calls
function simulateHandlerCall($action, $params) {
    // This simulates what handler.php would do
    $_POST['action'] = $action;
    foreach ($params as $key => $value) {
        $_POST[$key] = $value;
    }
    
    // Include the actual handler logic
    ob_start();
    include '../handler.php';
    $output = ob_get_clean();
    
    // Try to decode JSON response
    $result = json_decode($output, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return $result;
    } else {
        return ['raw_output' => $output];
    }
}

// Available endpoints configuration
$endpoints = [
    'Proxmox API' => [
        'proxmox_nodes' => ['name' => 'Get Nodes', 'params' => []],
        'proxmox_vms' => ['name' => 'Get All VMs', 'params' => []],
        'proxmox_vm_config' => ['name' => 'Get VM Config', 'params' => ['node' => 'pve', 'vmid' => '100']],
        'proxmox_vm_status' => ['name' => 'Get VM Status', 'params' => ['node' => 'pve', 'vmid' => '100']],
        'proxmox_storages' => ['name' => 'Get Storages', 'params' => ['node' => 'pve']],
        'proxmox_networks' => ['name' => 'Get Networks', 'params' => ['node' => 'pve']]
    ],
    'ISPConfig API' => [
        'ispconfig_server_config' => ['name' => 'Server Config', 'params' => []],
        'ispconfig_websites' => ['name' => 'Get Websites', 'params' => []],
        'ispconfig_databases' => ['name' => 'Get Databases', 'params' => []],
        'ispconfig_emails' => ['name' => 'Get Email Accounts', 'params' => []],
        'ispconfig_clients' => ['name' => 'Get Clients', 'params' => []]
    ],
    'OVH API' => [
        'ovh_domains' => ['name' => 'Get Domains', 'params' => []],
        'ovh_domain_zone' => ['name' => 'Get Domain Zone', 'params' => ['domain' => 'example.com']],
        'ovh_dns_records' => ['name' => 'Get DNS Records', 'params' => ['domain' => 'example.com']],
        'ovh_vps_list' => ['name' => 'Get VPS List', 'params' => []],
        'ovh_vps_ips' => ['name' => 'Get VPS IPs', 'params' => ['vps_name' => 'vps-12345.vps.ovh.net']],
        'ovh_dedicated_servers' => ['name' => 'Get Dedicated Servers', 'params' => []]
    ],
    'Service Manager' => [
        'service_manager_vms' => ['name' => 'Service Manager VMs', 'params' => []],
        'service_manager_websites' => ['name' => 'Service Manager Websites', 'params' => []],
        'service_manager_databases' => ['name' => 'Service Manager Databases', 'params' => []],
        'service_manager_emails' => ['name' => 'Service Manager Emails', 'params' => []],
        'service_manager_domains' => ['name' => 'Service Manager Domains', 'params' => []],
        'service_manager_vps' => ['name' => 'Service Manager VPS', 'params' => []]
    ],
    'Database' => [
        'database_activity_log' => ['name' => 'Activity Log', 'params' => []],
        'database_connection' => ['name' => 'Connection Test', 'params' => []],
        'database_tables' => ['name' => 'List Tables', 'params' => []]
    ],
    'Handler Simulation' => [
        'handler_get_all_vms' => ['name' => 'Handler: Get VMs', 'params' => []],
        'handler_get_all_websites' => ['name' => 'Handler: Get Websites', 'params' => []],
        'handler_get_all_databases' => ['name' => 'Handler: Get Databases', 'params' => []],
        'handler_get_all_emails' => ['name' => 'Handler: Get Emails', 'params' => []],
        'handler_get_all_domains' => ['name' => 'Handler: Get Domains', 'params' => []],
        'handler_get_all_vps' => ['name' => 'Handler: Get VPS', 'params' => []],
        'handler_get_activity_log' => ['name' => 'Handler: Activity Log', 'params' => []]
    ],
    'Mock Data' => [
        'mock_email_data' => ['name' => 'Mock Email Data', 'params' => []],
        'mock_vm_data' => ['name' => 'Mock VM Data', 'params' => []]
    ]
];

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔌 Endpoint Tester - Framework API</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container { max-width: 1600px; margin: 0 auto; }
        
        .header {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
            text-align: center;
        }
        
        .header h1 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 2.2rem;
        }
        
        .main-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
        
        .endpoints-panel, .results-panel {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .panel-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .panel-content {
            padding: 20px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .endpoint-category {
            margin-bottom: 25px;
            border: 1px solid #e1e5e9;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .category-header {
            background: #f8f9fa;
            padding: 15px 20px;
            font-weight: 600;
            color: #495057;
            border-bottom: 1px solid #e1e5e9;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .category-header:hover {
            background: #e9ecef;
        }
        
        .category-content {
            padding: 10px;
        }
        
        .endpoint-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 15px;
            margin: 5px 0;
            background: #f8f9fa;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .endpoint-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        
        .endpoint-info {
            flex: 1;
        }
        
        .endpoint-name {
            font-weight: 500;
            color: #495057;
        }
        
        .endpoint-id {
            font-size: 0.85rem;
            color: #6c757d;
            font-family: 'Courier New', monospace;
        }
        
        .endpoint-params {
            font-size: 0.8rem;
            color: #28a745;
            margin-top: 3px;
        }
        
        .test-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .test-btn:hover {
            background: #218838;
            transform: scale(1.05);
        }
        
        .test-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }
        
        .controls {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .control-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .control-btn:hover {
            background: #5a6fe8;
            transform: translateY(-2px);
        }
        
        .results-container {
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .result-item {
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            margin-bottom: 15px;
            overflow: hidden;
        }
        
        .result-header {
            background: #f8f9fa;
            padding: 12px 15px;
            border-bottom: 1px solid #e1e5e9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .result-success {
            border-left: 4px solid #28a745;
        }
        
        .result-error {
            border-left: 4px solid #dc3545;
        }
        
        .result-content {
            padding: 15px;
            background: #f8f9fa;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-success { background: #28a745; }
        .status-error { background: #dc3545; }
        .status-loading { background: #ffc107; animation: pulse 1.5s infinite; }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .execution-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .params-editor {
            background: #f8f9fa;
            border: 1px solid #e1e5e9;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .params-editor textarea {
            width: 100%;
            min-height: 100px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            border: 1px solid #ced4da;
            border-radius: 6px;
            padding: 10px;
            resize: vertical;
        }
        
        @media (max-width: 1200px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔌 Framework Endpoint Tester</h1>
            <p>Direkte Tests aller verfügbaren API-Endpoints aus framework.php und handler.php</p>
        </div>
        
        <div class="main-grid">
            <!-- Endpoints Panel -->
            <div class="endpoints-panel">
                <div class="panel-header">
                    📋 Verfügbare Endpoints
                </div>
                <div class="panel-content">
                    <div class="controls">
                        <button class="control-btn" onclick="testAllEndpoints()">🧪 Alle testen</button>
                        <button class="control-btn" onclick="testCategory('Proxmox API')">🖥️ Nur Proxmox</button>
                        <button class="control-btn" onclick="testCategory('ISPConfig API')">🌐 Nur ISPConfig</button>
                        <button class="control-btn" onclick="testCategory('OVH API')">🔗 Nur OVH</button>
                        <button class="control-btn" onclick="clearResults()">🗑️ Ergebnisse löschen</button>
                    </div>
                    
                    <?php foreach ($endpoints as $category => $categoryEndpoints): ?>
                    <div class="endpoint-category">
                        <div class="category-header" onclick="toggleCategory(this)">
                            📁 <?= htmlspecialchars($category) ?> (<?= count($categoryEndpoints) ?> Endpoints)
                        </div>
                        <div class="category-content">
                            <?php foreach ($categoryEndpoints as $endpointId => $endpoint): ?>
                            <div class="endpoint-item">
                                <div class="endpoint-info">
                                    <div class="endpoint-name"><?= htmlspecialchars($endpoint['name']) ?></div>
                                    <div class="endpoint-id"><?= htmlspecialchars($endpointId) ?></div>
                                    <?php if (!empty($endpoint['params'])): ?>
                                    <div class="endpoint-params">
                                        Params: <?= htmlspecialchars(json_encode($endpoint['params'])) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <button class="test-btn" onclick="testSingleEndpoint('<?= $endpointId ?>', '<?= htmlspecialchars($endpoint['name']) ?>', <?= htmlspecialchars(json_encode($endpoint['params'])) ?>)">
                                    ▶️ Test
                                </button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Results Panel -->
            <div class="results-panel">
                <div class="panel-header">
                    📊 Test-Ergebnisse
                </div>
                <div class="panel-content">
                    <div class="params-editor">
                        <label for="customParams"><strong>Custom Parameters (JSON):</strong></label>
                        <textarea id="customParams" placeholder='{"node": "pve", "vmid": "100"}'></textarea>
                        <button class="control-btn" style="margin-top: 10px;" onclick="setCustomParams()">📝 Parameter setzen</button>
                    </div>
                    
                    <div class="results-container" id="resultsContainer">
                        <div style="text-align: center; color: #6c757d; padding: 40px;">
                            🚀 Bereit für Tests - Wählen Sie einen Endpoint zum Testen
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let testCounter = 0;
        let customParameters = {};
        
        // Toggle category visibility
        function toggleCategory(header) {
            const content = header.nextElementSibling;
            const isVisible = content.style.display !== 'none';
            content.style.display = isVisible ? 'none' : 'block';
        }
        
        // Test single endpoint
        async function testSingleEndpoint(endpointId, endpointName, defaultParams) {
            const params = Object.keys(customParameters).length > 0 ? customParameters : defaultParams;
            
            addResultPlaceholder(endpointId, endpointName);
            
            try {
                const formData = new FormData();
                formData.append('test_endpoint', '1');
                formData.append('endpoint', endpointId);
                formData.append('method', 'GET');
                formData.append('params', JSON.stringify(params));
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                updateResult(endpointId, result, params);
                
            } catch (error) {
                updateResult(endpointId, {
                    success: false,
                    error: error.message
                }, params);
            }
        }
        
        // Test all endpoints
        async function testAllEndpoints() {
            if (!confirm('Alle Endpoints testen? Dies kann einige Minuten dauern.')) {
                return;
            }
            
            clearResults();
            
            const endpoints = <?= json_encode($endpoints) ?>;
            let totalTests = 0;
            
            for (const category in endpoints) {
                for (const endpointId in endpoints[category]) {
                    totalTests++;
                }
            }
            
            let currentTest = 0;
            
            for (const category in endpoints) {
                for (const endpointId in endpoints[category]) {
                    currentTest++;
                    const endpoint = endpoints[category][endpointId];
                    
                    console.log(`Testing ${currentTest}/${totalTests}: ${endpointId}`);
                    await testSingleEndpoint(endpointId, endpoint.name, endpoint.params);
                    
                    // Small delay to prevent overwhelming the server
                    await new Promise(resolve => setTimeout(resolve, 100));
                }
            }
            
            console.log('All tests completed!');
        }
        
        // Test category
        async function testCategory(categoryName) {
            const endpoints = <?= json_encode($endpoints) ?>;
            const categoryEndpoints = endpoints[categoryName];
            
            if (!categoryEndpoints) {
                alert('Category not found: ' + categoryName);
                return;
            }
            
            if (!confirm(`Alle ${Object.keys(categoryEndpoints).length} Endpoints in "${categoryName}" testen?`)) {
                return;
            }
            
            for (const endpointId in categoryEndpoints) {
                const endpoint = categoryEndpoints[endpointId];
                await testSingleEndpoint(endpointId, endpoint.name, endpoint.params);
                await new Promise(resolve => setTimeout(resolve, 100));
            }
        }
        
        // Add result placeholder
        function addResultPlaceholder(endpointId, endpointName) {
            testCounter++;
            
            const resultsContainer = document.getElementById('resultsContainer');
            
            const resultItem = document.createElement('div');
            resultItem.className = 'result-item';
            resultItem.id = `result-${endpointId}`;
            
            resultItem.innerHTML = `
                <div class="result-header">
                    <div>
                        <span class="status-indicator status-loading"></span>
                        <strong>${endpointName}</strong>
                        <span style="font-size: 0.9rem; color: #6c757d;"> (${endpointId})</span>
                    </div>
                    <div class="execution-time">Testing...</div>
                </div>
                <div class="result-content">🔄 Test läuft...</div>
            `;
            
            if (resultsContainer.firstChild && resultsContainer.firstChild.style) {
                resultsContainer.insertBefore(resultItem, resultsContainer.firstChild);
            } else {
                resultsContainer.innerHTML = '';
                resultsContainer.appendChild(resultItem);
            }
            
            resultItem.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        
        // Update result
        function updateResult(endpointId, result, params) {
            const resultElement = document.getElementById(`result-${endpointId}`);
            if (!resultElement) return;
            
            const success = result.success;
            const statusClass = success ? 'result-success' : 'result-error';
            const statusIndicator = success ? 'status-success' : 'status-error';
            
            resultElement.className = `result-item ${statusClass}`;
            
            const statusElement = resultElement.querySelector('.status-indicator');
            statusElement.className = `status-indicator ${statusIndicator}`;
            
            const executionTime = result.execution_time ? `${result.execution_time}ms` : 'N/A';
            resultElement.querySelector('.execution-time').textContent = executionTime;
            
            let content = '';
            
            if (params && Object.keys(params).length > 0) {
                content += `🔧 Parameters:\n${JSON.stringify(params, null, 2)}\n\n`;
            }
            
            if (success) {
                content += `✅ Success!\n\n📊 Result:\n${JSON.stringify(result.result, null, 2)}`;
            } else {
                content += `❌ Error: ${result.error}\n\n`;
                if (result.file && result.line) {
                    content += `📁 File: ${result.file}\n📍 Line: ${result.line}\n\n`;
                }
                if (result.result) {
                    content += `📊 Additional Info:\n${JSON.stringify(result.result, null, 2)}`;
                }
            }
            
            resultElement.querySelector('.result-content').textContent = content;
        }
        
        // Set custom parameters
        function setCustomParams() {
            const paramsText = document.getElementById('customParams').value.trim();
            
            if (!paramsText) {
                customParameters = {};
                alert('Custom parameters cleared!');
                return;
            }
            
            try {
                customParameters = JSON.parse(paramsText);
                alert('Custom parameters set successfully!');
            } catch (error) {
                alert('Invalid JSON: ' + error.message);
            }
        }
        
        // Clear results
        function clearResults() {
            document.getElementById('resultsContainer').innerHTML = `
                <div style="text-align: center; color: #6c757d; padding: 40px;">
                    🗑️ Ergebnisse gelöscht - Bereit für neue Tests
                </div>
            `;
            testCounter = 0;
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                switch (e.key) {
                    case 't':
                        e.preventDefault();
                        testAllEndpoints();
                        break;
                    case 'c':
                        e.preventDefault();
                        clearResults();
                        break;
                    case 'p':
                        e.preventDefault();
                        testCategory('Proxmox API');
                        break;
                    case 'i':
                        e.preventDefault();
                        testCategory('ISPConfig API');
                        break;
                    case 'o':
                        e.preventDefault();
                        testCategory('OVH API');
                        break;
                }
            }
        });
        
        // Initialize
        console.log('🔌 Endpoint Tester loaded');
        console.log('Shortcuts: Ctrl+T (Test All), Ctrl+C (Clear), Ctrl+P (Proxmox), Ctrl+I (ISPConfig), Ctrl+O (OVH)');
    </script>
</body>
</html>