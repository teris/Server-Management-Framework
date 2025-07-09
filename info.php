<?php
// =============================================================================
// Beispiele zur Verwendung der zusatz Methoden get, post, put, delete
// =============================================================================

require_once 'framework.php';
require_once 'auth_handler.php';

// Service Manager initialisieren
$serviceManager = new ServiceManager();

// Output-Funktion
function output($message, $type = 'info') {
    $prefix = [
        'info' => '📌',
        'success' => '✅',
        'error' => '❌',
        'data' => '📊'
    ];
    
    $icon = $prefix[$type] ?? '📌';
    
    if (php_sapi_name() === 'cli') {
        echo "$icon $message\n";
    } else {
        echo "<div style='margin: 10px 0; padding: 10px; background: " . 
             ($type === 'error' ? '#fee' : ($type === 'success' ? '#efe' : '#f0f0f0')) . 
             "; border-radius: 5px;'>$icon " . htmlspecialchars($message) . "</div>";
    }
}

// =============================================================================
// PROXMOX API BEISPIELE
// =============================================================================

// 1. Alle Nodes abrufen
output("Test 1: Alle Proxmox Nodes abrufen", "info");
$nodes = $serviceManager->ProxmoxAPI('get', '/nodes');
if ($nodes && isset($nodes['data'])) {
    output("Gefundene Nodes: " . count($nodes['data']), "success");
    foreach ($nodes['data'] as $node) {
        output("  - Node: {$node['node']}, Status: {$node['status']}", "data");
    }
} else {
    output("Fehler beim Abrufen der Nodes", "error");
}

// =============================================================================
// OVH API BEISPIELE
// =============================================================================

// 1. Alle Domains abrufen
output("Test 1: Alle OVH Domains abrufen", "info");
$domains = $serviceManager->OvhAPI('get', '/domain');
if ($domains && is_array($domains)) {
    output("Gefundene Domains: " . count($domains), "success");
    foreach (array_slice($domains, 0, 3) as $domain) {
        output("  - Domain: $domain", "data");
    }
} else {
    output("Fehler beim Abrufen der Domains", "error");
}

// =============================================================================
// ISPCONFIG API BEISPIELE
// =============================================================================


$databases = $serviceManager->IspconfigAPI('get', 'sites_database');
if ($databases && is_array($databases)) {
    output("Gefundene Datenbanken: " . count($databases), "success");
    foreach (array_slice($databases, 0, 3) as $db) {
        output("  - DB: {$db['database_name']}, User: {$db['database_user']}", "data");
    }
} else {
    output("Fehler beim Abrufen der Datenbanken", "error");
}
?>

