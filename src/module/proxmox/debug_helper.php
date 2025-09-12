<?php
/**
 * Proxmox Debug Helper
 * Zeigt alle übergebenen Variablen und API-Antworten in einem Modal an
 */

// Einfache Sicherheitsprüfung
if (!isset($_POST['plugin']) || $_POST['plugin'] !== 'proxmox') {
    http_response_code(403);
    die('Access denied');
}

// Content-Type für JSON setzen
header('Content-Type: application/json');

// Alle übergebenen Daten sammeln
$debugData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'raw_input' => file_get_contents('php://input'),
    'post_data' => $_POST,
    'get_data' => $_GET,
    'headers' => getallheaders(),
    'server_info' => [
        'php_version' => PHP_VERSION,
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time')
    ]
];

// Wenn es ein AJAX-Request ist, versuche die Daten zu parsen
if (isset($_POST['plugin']) && $_POST['plugin'] === 'proxmox') {
    $debugData['parsed_request'] = [
        'plugin' => $_POST['plugin'] ?? 'not_set',
        'action' => $_POST['action'] ?? 'not_set',
        'data' => array_diff_key($_POST, ['plugin', 'action'])
    ];
    
    // Versuche die Proxmox API zu testen
    try {
        require_once '../../../framework.php';
        $serviceManager = new ServiceManager();
        
        // Teste die API-Verbindung
        $testResult = $serviceManager->ProxmoxAPI('get', '/version');
        $debugData['api_test'] = [
            'success' => isset($testResult['success']) ? $testResult['success'] : false,
            'result' => $testResult
        ];
        
        // Wenn es eine VM-Erstellung ist, teste die Parameter und erstelle die VM
        if (isset($_POST['action']) && $_POST['action'] === 'create_vm') {
            $vmParams = array_diff_key($_POST, ['plugin', 'action']);
            $debugData['vm_creation_test'] = [
                'parameters' => $vmParams,
                'required_fields' => ['vmid', 'node', 'name', 'memory', 'cores', 'sockets'],
                'validation' => []
            ];
            
            // Validiere erforderliche Felder
            $requiredFields = ['vmid', 'node', 'name', 'memory', 'cores', 'sockets'];
            foreach ($requiredFields as $field) {
                $debugData['vm_creation_test']['validation'][$field] = [
                    'exists' => isset($vmParams[$field]),
                    'not_empty' => !empty($vmParams[$field]),
                    'value' => $vmParams[$field] ?? 'NOT_SET'
                ];
            }
            
            // VM tatsächlich erstellen
            try {
                $vmCreationParams = [
                    'vmid' => intval($vmParams['vmid']),
                    'name' => $vmParams['name'],
                    'memory' => intval($vmParams['memory']),
                    'cores' => intval($vmParams['cores']),
                    'sockets' => intval($vmParams['sockets']),
                    'ostype' => $vmParams['ostype'] ?? 'l26',
                    'bios' => $vmParams['bios'] ?? 'seabios',
                    'machine' => $vmParams['machine'] ?? 'pc',
                    'cpu' => $vmParams['cpu'] ?? 'host',
                    'scsihw' => $vmParams['scsihw'] ?? 'lsi',
                    'vga' => $vmParams['vga'] ?? 'std',
                    'keyboard' => $vmParams['keyboard'] ?? 'de',
                    'citype' => $vmParams['citype'] ?? 'nocloud',
                    'startdate' => $vmParams['startdate'] ?? 'now'
                ];
                
                // Alle anderen Parameter hinzufügen (auch leere)
                $allFields = [
                    'pool', 'description', 'shares', 'cpulimit', 'cpuunits', 'storage',
                    'onboot', 'agent', 'kvm', 'acpi', 'localtime', 'tablet', 'autostart', 
                    'protection', 'template', 'start', 'reboot', 'unique', 'ciupgrade',
                    'net0', 'scsi0', 'ide2', 'bootdisk', 'boot', 'bootorder', 'bridge',
                    'mac', 'cdrom', 'tags', 'hookscript', 'ciuser', 'cipassword',
                    'sshkeys', 'nameserver', 'searchdomain', 'cicustom', 'ipconfig0',
                    'ipconfig1', 'serial0', 'parallel0', 'usb0', 'usb1', 'watchdog',
                    'rng0', 'migrate_downtime', 'migrate_speed', 'startup', 'args',
                    'affinity', 'smbios1', 'vmgenid'
                ];
                
                foreach ($allFields as $field) {
                    if (isset($vmParams[$field])) {
                        $vmCreationParams[$field] = $vmParams[$field];
                    }
                }
                
                $debugData['vm_creation_test']['final_parameters'] = $vmCreationParams;
                
                // VM erstellen
                $vmResult = $serviceManager->ProxmoxAPI('post', '/nodes/' . $vmParams['node'] . '/qemu', $vmCreationParams);
                $debugData['vm_creation_result'] = $vmResult;
                
            } catch (Exception $e) {
                $debugData['vm_creation_error'] = [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ];
            }
        }
        
    } catch (Exception $e) {
        $debugData['api_error'] = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];
    }
}

// HTML für das Debug-Modal generieren
$vmCreationStatus = '';
$vmCreationResult = '';

if (isset($debugData['vm_creation_result'])) {
    if (isset($debugData['vm_creation_result']['success']) && $debugData['vm_creation_result']['success']) {
        $vmCreationStatus = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> VM erfolgreich erstellt!</div>';
    } else {
        $vmCreationStatus = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> VM-Erstellung fehlgeschlagen!</div>';
    }
    $vmCreationResult = '<h6>VM-Erstellungs-Ergebnis:</h6><pre class="bg-light p-3" style="max-height: 200px; overflow-y: auto;">' . htmlspecialchars(print_r($debugData['vm_creation_result'], true)) . '</pre>';
}

$html = '<div class="modal fade" id="debugModal" tabindex="-1" aria-labelledby="debugModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="debugModalLabel">Debug: Proxmox VM-Erstellung</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                ' . $vmCreationStatus . '
                <div class="row">
                    <div class="col-12">
                        <h6>Übergebene Formulardaten:</h6>
                        <pre class="bg-light p-3" style="max-height: 300px; overflow-y: auto;">' . htmlspecialchars(print_r($debugData['post_data'], true)) . '</pre>
                    </div>
                </div>
                ' . $vmCreationResult . '
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Vollständige Debug-Informationen:</h6>
                        <pre class="bg-light p-3" style="max-height: 400px; overflow-y: auto;">' . htmlspecialchars(print_r($debugData, true)) . '</pre>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
                <button type="button" class="btn btn-primary" onclick="copyDebugData()">Daten kopieren</button>
            </div>
        </div>
    </div>
</div>

<script>
function copyDebugData() {
    const debugData = ' . json_encode($debugData) . ';
    navigator.clipboard.writeText(JSON.stringify(debugData, null, 2)).then(() => {
        alert("Debug-Daten in die Zwischenablage kopiert!");
    });
}
</script>';

// JSON-Response zurückgeben
echo json_encode([
    'success' => true,
    'debug_data' => $debugData,
    'html_modal' => $html,
    'message' => 'Debug-Informationen generiert'
]);
?>
