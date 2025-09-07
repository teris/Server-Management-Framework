<?php
/**
 * Server Management Framework
 * 
 * @author Teris
 * @version 3.1.2
 */

echo "<pre>";

require_once '../framework.php';

// Service Manager initialisieren
$serviceManager = new ServiceManager();

// Schritt 1: Alle IPs abrufen
//$type = "get";
//$url = "/ip";
//$ipList = $serviceManager->OvhAPI($type, $url);
//print_r($serviceManager->getCompleteVirtualMacInfo());
//echo "<hr>";
//print_r($serviceManager->getOvhIP());

/*
try {
    $serviceManager = new ServiceManager();
    
    // Alle Dedicated Servers abrufen
    $servers = $serviceManager->OvhAPI('get', '/dedicated/server');
    $all_macs = [];
    
    foreach ($servers as $server) {
        try {
            $server_macs = $serviceManager->getVirtualMacAddresses($server);
            
            foreach ($server_macs as $mac) {
                $mac_details = $serviceManager->getVirtualMacDetails($server, $mac);
                $mac_details['service_name'] = $server;
                $all_macs[] = $mac_details;
            }
        } catch (Exception $e) {
            // Server ohne Virtual MACs überspringen
            continue;
        }
    }
    
    $this->log('Retrieved ' . count($all_macs) . ' virtual MACs');
    
    echo $this->success($all_macs);
    
} catch (Exception $e) {
    $this->log('Error retrieving all virtual MACs: ' . $e->getMessage(), 'ERROR');
    echo $this->error($e->getMessage());
}
*/

// $macData = $serviceManager->getCompleteVirtualMacInfo();
// $reverseData = $serviceManager->getOvhIP();

$macData = $serviceManager->getCompleteVirtualMacInfo();
$reverseData = $serviceManager->getOvhIP();

// Hilfsfunktion: Sucht MAC und Typ zu einer IP
function findMacAndType($macData, $ip) {
    foreach ($macData as $server) {
        if (!isset($server['virtualMacs'])) continue;
        foreach ($server['virtualMacs'] as $macObj) {
            // Falls es ein echtes Objekt ist, in Array umwandeln
            if (is_object($macObj)) $macObj = (array)$macObj;
            if (!isset($macObj['ips']) || !is_array($macObj['ips'])) continue;
            foreach ($macObj['ips'] as $ipEntry) {
                if (isset($ipEntry['ipAddress']) && $ipEntry['ipAddress'] === $ip) {
                    return [
                        'macAddress' => $macObj['macAddress'] ?? '',
                        'type' => $macObj['type'] ?? ''
                    ];
                }
            }
        }
    }
    return ['macAddress' => '', 'type' => ''];
}

echo '<div class="table-responsive">';
echo '<table class="table table-bordered table-striped table-sm align-middle">';
echo '<thead class="table-light"><tr>
        <th>IP</th>
        <th>Reverse-DNS</th>
        <th>MAC-Adresse</th>
        <th>Type</th>
      </tr></thead><tbody>';

foreach ($reverseData as $subnet => $ips) {
    if (!is_array($ips)) continue;
    foreach ($ips as $ip => $details) {
        $ipReverse = $details['ipReverse'] ?? $ip;
        $reverse = $details['reverse'] ?? '';
        $macInfo = findMacAndType($macData, $ipReverse);
        // Nur anzeigen, wenn MAC gefunden wurde
        if ($macInfo['macAddress']) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($ipReverse) . '</td>';
            echo '<td>' . htmlspecialchars($reverse) . '</td>';
            echo '<td>' . htmlspecialchars($macInfo['macAddress']) . '</td>';
            echo '<td>' . htmlspecialchars($macInfo['type']) . '</td>';
            echo '</tr>';
        }
    }
}
echo '</tbody></table></div>';
?>