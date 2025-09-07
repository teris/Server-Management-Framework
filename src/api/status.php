<?php
/**
 * Server Management Framework
 * 
 * @author Teris
 * @version 3.1.2
 */
/**
 * Status API - Endpunkt für Server-Status-Updates
 */

// Fehler unterdrücken für saubere JSON-Ausgabe
error_reporting(0);
ini_set('display_errors', 0);

require_once '../sys.conf.php';
require_once '../../framework.php';

// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// OPTIONS Request für CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Nur GET Requests erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // ServiceManager für Server-Status
    $serviceManager = new ServiceManager();
    
    // Status-Daten sammeln
    $statusData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'vms' => [],
        'gameServers' => [],
        'systemInfo' => []
    ];
    
    // Proxmox VMs Status
    try {
        $proxmoxVMs = $serviceManager->getProxmoxVMs();
        $statusData['vms'] = array_map(function($vm) {
            return [
                'id' => $vm->vmid ?? $vm->id ?? null,
                'name' => $vm->name ?? 'Unbekannte VM',
                'status' => $vm->status ?? 'unknown',
                'cpu_usage' => $vm->cpu_usage ?? $vm->cpu ?? 0,
                'memory_usage' => $vm->memory_usage ?? $vm->mem ?? 0,
                'memory' => $vm->memory ?? $vm->mem ?? 0,
                'cores' => $vm->cores ?? 0,
                'uptime' => $vm->uptime ?? 0,
                'disk' => $vm->disk ?? 0,
                'network' => $vm->network ?? []
            ];
        }, $proxmoxVMs);
    } catch (Exception $e) {
        error_log("Error getting Proxmox VMs: " . $e->getMessage());
        $statusData['vms'] = [];
    }
    
    // Game Server Status
    try {
        $gameServers = $serviceManager->getOGPGameServers();
        if (isset($gameServers['message']) && is_array($gameServers['message'])) {
            $statusData['gameServers'] = array_map(function($server) {
                return [
                    'id' => $server['remote_server_id'] ?? null,
                    'name' => $server['home_name'] ?? 'Unbekannter Server',
                    'game_name' => $server['game_name'] ?? 'Unknown',
                    'remote_server_name' => $server['remote_server_name'] ?? 'N/A',
                    'display_public_ip' => $server['display_public_ip'] ?? 'N/A',
                    'agent_port' => $server['agent_port'] ?? 0,
                    'status' => 'online' // Default status, will be updated by getOGPServerStatus
                ];
            }, $gameServers['message']);
        } else {
            $statusData['gameServers'] = [];
        }
    } catch (Exception $e) {
        error_log("Error getting game servers: " . $e->getMessage());
        $statusData['gameServers'] = [];
    }
    
    // System Information
    try {
        $systemInfo = $serviceManager->getSystemInfo();
        $statusData['systemInfo'] = [
            'uptime' => $systemInfo['uptime'] ?? 'N/A',
            'load' => $systemInfo['load'] ?? 'N/A',
            'cpu_usage' => $systemInfo['cpu_usage'] ?? 0,
            'memory_usage' => $systemInfo['memory_usage'] ?? 0,
            'disk_usage' => $systemInfo['disk_usage'] ?? 0,
            'network_traffic' => $systemInfo['network_traffic'] ?? [],
            'temperature' => $systemInfo['temperature'] ?? 'N/A',
            'last_update' => date('Y-m-d H:i:s')
        ];
    } catch (Exception $e) {
        error_log("Error getting system info: " . $e->getMessage());
        $statusData['systemInfo'] = [
            'uptime' => 'N/A',
            'load' => 'N/A',
            'cpu_usage' => 0,
            'memory_usage' => 0,
            'disk_usage' => 0,
            'network_traffic' => [],
            'temperature' => 'N/A',
            'last_update' => date('Y-m-d H:i:s')
        ];
    }
    
    // Zusätzliche Status-Informationen
    $statusData['overall_status'] = calculateOverallStatus($statusData);
    $statusData['alerts'] = generateAlerts($statusData);
    $statusData['performance_metrics'] = calculatePerformanceMetrics($statusData);
    
    // Cache-Header für Performance
    header('Cache-Control: public, max-age=30'); // 30 Sekunden Cache
    header('ETag: "' . md5(json_encode($statusData)) . '"');
    
    // Erfolgsantwort
    echo json_encode([
        'success' => true,
        'data' => $statusData,
        'generated_at' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Status API Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
}

/**
 * Gesamtstatus berechnen
 */
function calculateOverallStatus($statusData) {
    $status = 'healthy';
    $issues = [];
    
    // VM Status prüfen
    foreach (($statusData['vms'] ?? []) as $vm) {
        if (($vm['status'] ?? '') !== 'running') {
            $issues[] = "VM " . ($vm['name'] ?? 'Unbekannt') . " ist nicht aktiv";
            $status = 'warning';
        }
        
        if (($vm['cpu_usage'] ?? 0) > 90) {
            $issues[] = "VM " . ($vm['name'] ?? 'Unbekannt') . " hat hohe CPU-Auslastung";
            $status = 'warning';
        }
        
        if (($vm['memory_usage'] ?? 0) > 90) {
            $issues[] = "VM " . ($vm['name'] ?? 'Unbekannt') . " hat hohe Speicherauslastung";
            $status = 'warning';
        }
    }
    
    // Game Server Status prüfen
    foreach (($statusData['gameServers'] ?? []) as $server) {
        if (($server['status'] ?? '') !== 'online') {
            $issues[] = "Game Server " . ($server['name'] ?? 'Unbekannt') . " ist offline";
            $status = 'critical';
        }
    }
    
    // System Status prüfen
    $systemInfo = $statusData['systemInfo'] ?? [];
    if (($systemInfo['cpu_usage'] ?? 0) > 90) {
        $issues[] = "Hohe System-CPU-Auslastung";
        $status = 'warning';
    }
    
    if (($systemInfo['memory_usage'] ?? 0) > 90) {
        $issues[] = "Hohe System-Speicherauslastung";
        $status = 'warning';
    }
    
    if (($systemInfo['disk_usage'] ?? 0) > 90) {
        $issues[] = "Kritische Festplattenauslastung";
        $status = 'critical';
    }
    
    return [
        'status' => $status,
        'issues' => $issues,
        'summary' => count($issues) === 0 ? 'Alle Systeme funktionieren normal' : implode('; ', $issues)
    ];
}

/**
 * Alerts generieren
 */
function generateAlerts($statusData) {
    $alerts = [];
    
    // VM Alerts
    foreach (($statusData['vms'] ?? []) as $vm) {
        if (($vm['status'] ?? '') !== 'running') {
            $alerts[] = [
                'type' => 'vm',
                'severity' => 'high',
                'message' => "VM " . ($vm['name'] ?? 'Unbekannt') . " ist nicht aktiv",
                'timestamp' => date('Y-m-d H:i:s'),
                'entity_id' => $vm['id'] ?? 0,
                'entity_name' => $vm['name'] ?? 'Unbekannt'
            ];
        }
        
        if (($vm['cpu_usage'] ?? 0) > 95) {
            $alerts[] = [
                'type' => 'vm',
                'severity' => 'medium',
                'message' => "VM " . ($vm['name'] ?? 'Unbekannt') . " hat kritische CPU-Auslastung",
                'timestamp' => date('Y-m-d H:i:s'),
                'entity_id' => $vm['id'] ?? 0,
                'entity_name' => $vm['name'] ?? 'Unbekannt'
            ];
        }
    }
    
    // Game Server Alerts
    foreach (($statusData['gameServers'] ?? []) as $server) {
        if (($server['status'] ?? '') !== 'online') {
            $alerts[] = [
                'type' => 'game_server',
                'severity' => 'critical',
                'message' => "Game Server " . ($server['name'] ?? 'Unbekannt') . " ist offline",
                'timestamp' => date('Y-m-d H:i:s'),
                'entity_id' => $server['id'] ?? 0,
                'entity_name' => $server['name'] ?? 'Unbekannt'
            ];
        }
    }
    
    // System Alerts
    $systemInfo = $statusData['systemInfo'] ?? [];
    if (($systemInfo['cpu_usage'] ?? 0) > 95) {
        $alerts[] = [
            'type' => 'system',
            'severity' => 'high',
            'message' => 'Kritische System-CPU-Auslastung',
            'timestamp' => date('Y-m-d H:i:s'),
            'entity_id' => 'system',
            'entity_name' => 'System'
        ];
    }
    
    if (($systemInfo['disk_usage'] ?? 0) > 95) {
        $alerts[] = [
            'type' => 'system',
            'severity' => 'critical',
            'message' => 'Kritische Festplattenauslastung',
            'timestamp' => date('Y-m-d H:i:s'),
            'entity_id' => 'system',
            'entity_name' => 'System'
        ];
    }
    
    return $alerts;
}

/**
 * Performance-Metriken berechnen
 */
function calculatePerformanceMetrics($statusData) {
    $metrics = [
        'vm_count' => count($statusData['vms'] ?? []),
        'running_vms' => count(array_filter($statusData['vms'] ?? [], function($vm) {
            return ($vm['status'] ?? '') === 'running';
        })),
        'game_server_count' => count($statusData['gameServers'] ?? []),
        'online_game_servers' => count(array_filter($statusData['gameServers'] ?? [], function($server) {
            return ($server['status'] ?? '') === 'online';
        })),
        'total_players' => 0, // Game servers don't have player count in current structure
        'system_health_score' => 100
    ];
    
    // System Health Score berechnen
    $systemInfo = $statusData['systemInfo'] ?? [];
    
    // CPU Score - sicherstellen, dass cpu_usage ein numerischer Wert ist
    $cpuUsage = is_numeric($systemInfo['cpu_usage'] ?? 0) ? (float)($systemInfo['cpu_usage']) : 0;
    $cpuScore = max(0, 100 - $cpuUsage);
    
    // Memory Score - sicherstellen, dass memory_usage ein numerischer Wert ist
    $memoryUsage = is_numeric($systemInfo['memory_usage'] ?? 0) ? (float)($systemInfo['memory_usage']) : 0;
    $memoryScore = max(0, 100 - $memoryUsage);
    
    // Disk Score - sicherstellen, dass disk_usage ein numerischer Wert ist
    $diskUsage = is_numeric($systemInfo['disk_usage'] ?? 0) ? (float)($systemInfo['disk_usage']) : 0;
    $diskScore = max(0, 100 - $diskUsage);
    
    // VM Score
    $vmScore = $metrics['vm_count'] > 0 ? ($metrics['running_vms'] / $metrics['vm_count']) * 100 : 100;
    
    // Game Server Score
    $gameServerScore = $metrics['game_server_count'] > 0 ? ($metrics['online_game_servers'] / $metrics['game_server_count']) * 100 : 100;
    
    // Gesamtscore (gewichtet)
    $metrics['system_health_score'] = round(
        ($cpuScore * 0.25) + 
        ($memoryScore * 0.25) + 
        ($diskScore * 0.2) + 
        ($vmScore * 0.15) + 
        ($gameServerScore * 0.15)
    );
    
    return $metrics;
}
