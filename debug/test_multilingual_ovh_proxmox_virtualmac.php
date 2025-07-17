<?php
/**
 * Testskript für mehrsprachige Module: OVH, Proxmox, Virtual-MAC
 * Testet die Übersetzungsfunktionalität und AJAX-Endpunkte
 */

require_once '../framework.php';

// Konfiguration
$config = [
    'test_modules' => ['ovh', 'proxmox', 'virtual-mac'],
    'test_languages' => ['de', 'en'],
    'test_actions' => [
        'ovh' => [
            'get_translations',
            'order_domain',
            'get_ovh_domain_zone',
            'get_ovh_dns_records',
            'create_dns_record',
            'refresh_dns_zone',
            'get_vps_info',
            'get_vps_ips',
            'get_vps_ip_details',
            'control_ovh_vps',
            'get_ovh_failover_ips',
            'create_ovh_virtual_mac'
        ],
        'proxmox' => [
            'get_translations',
            'create_vm',
            'get_proxmox_nodes',
            'get_proxmox_storages',
            'get_vm_config',
            'get_vm_status',
            'clone_vm'
        ],
        'virtual-mac' => [
            'get_translations',
            'create_virtual_mac',
            'get_virtual_mac_details',
            'get_all_virtual_macs',
            'delete_virtual_mac',
            'assign_ip_to_virtual_mac',
            'remove_ip_from_virtual_mac',
            'create_reverse_dns',
            'query_reverse_dns',
            'delete_reverse_dns',
            'get_dedicated_servers',
            'get_virtual_macs_for_service',
            'load_virtual_mac_overview'
        ]
    ]
];

// Test-Daten
$test_data = [
    'ovh' => [
        'order_domain' => [
            'domain' => 'test-domain.com',
            'duration' => 1
        ],
        'get_ovh_domain_zone' => [
            'domain' => 'test-domain.com'
        ],
        'get_ovh_dns_records' => [
            'domain' => 'test-domain.com'
        ],
        'create_dns_record' => [
            'domain' => 'test-domain.com',
            'type' => 'A',
            'subdomain' => 'www',
            'target' => '192.168.1.100',
            'ttl' => 3600
        ],
        'refresh_dns_zone' => [
            'domain' => 'test-domain.com'
        ],
        'get_vps_info' => [
            'vps_name' => 'vps12345.ovh.net'
        ],
        'get_vps_ips' => [
            'vps_name' => 'vps12345.ovh.net'
        ],
        'get_vps_ip_details' => [
            'vps_name' => 'vps12345.ovh.net',
            'ip' => '192.168.1.100'
        ],
        'control_ovh_vps' => [
            'vps_name' => 'vps12345.ovh.net',
            'vps_action' => 'reboot'
        ],
        'create_ovh_virtual_mac' => [
            'ip' => '192.168.1.100',
            'type' => 'ovh',
            'vm_name' => 'test-vm'
        ]
    ],
    'proxmox' => [
        'create_vm' => [
            'name' => 'test-vm',
            'vmid' => 1000,
            'memory' => 1024,
            'cores' => 2,
            'disk' => 20,
            'node' => 'pve',
            'storage' => 'local',
            'bridge' => 'vmbr0',
            'iso' => 'debian-11.0.0-amd64-netinst.iso'
        ],
        'get_proxmox_storages' => [
            'node' => 'pve'
        ],
        'get_vm_config' => [
            'node' => 'pve',
            'vmid' => 1000
        ],
        'get_vm_status' => [
            'node' => 'pve',
            'vmid' => 1000
        ],
        'clone_vm' => [
            'node' => 'pve',
            'vmid' => 1000,
            'newid' => 1001,
            'name' => 'test-vm-clone'
        ]
    ],
    'virtual-mac' => [
        'create_virtual_mac' => [
            'service_name' => 'ns123456.ip-123-45-67.eu',
            'virtual_network_interface' => 'eth0',
            'type' => 'ovh'
        ],
        'get_virtual_mac_details' => [
            'service_name' => 'ns123456.ip-123-45-67.eu'
        ],
        'delete_virtual_mac' => [
            'service_name' => 'ns123456.ip-123-45-67.eu',
            'mac_address' => '02:00:00:96:1f:85'
        ],
        'assign_ip_to_virtual_mac' => [
            'service_name' => 'ns123456.ip-123-45-67.eu',
            'mac_address' => '02:00:00:96:1f:85',
            'ip_address' => '192.168.1.100',
            'virtual_machine_name' => 'test-vm'
        ],
        'remove_ip_from_virtual_mac' => [
            'service_name' => 'ns123456.ip-123-45-67.eu',
            'mac_address' => '02:00:00:96:1f:85',
            'ip_address' => '192.168.1.100'
        ],
        'create_reverse_dns' => [
            'ip_address' => '192.168.1.100',
            'reverse' => 'server.example.com'
        ],
        'query_reverse_dns' => [
            'ip_address' => '192.168.1.100'
        ],
        'delete_reverse_dns' => [
            'ip_address' => '192.168.1.100'
        ],
        'get_virtual_macs_for_service' => [
            'service_name' => 'ns123456.ip-123-45-67.eu'
        ]
    ]
];

// Hilfsfunktionen
function testModule($module_name, $language = 'de') {
    global $config, $test_data;
    
    echo "<h3>🧪 Teste Modul: {$module_name} (Sprache: {$language})</h3>";
    
    // Sprache setzen
    $_SESSION['language'] = $language;
    
    // Modul instanziieren
    $module_class = ucfirst($module_name) . 'Module';
    $module_file = "../module/{$module_name}/Module.php";
    
    if (!file_exists($module_file)) {
        echo "<p style='color: red;'>❌ Modul-Datei nicht gefunden: {$module_file}</p>";
        return false;
    }
    
    require_once $module_file;
    
    if (!class_exists($module_class)) {
        echo "<p style='color: red;'>❌ Modul-Klasse nicht gefunden: {$module_class}</p>";
        return false;
    }
    
    $module = new $module_class();
    
    // Teste getContent() mit Übersetzungen
    echo "<h4>📄 Teste getContent()</h4>";
    try {
        $content = $module->getContent();
        if (strpos($content, 'translations') !== false || strpos($content, '<?php echo $translations') !== false) {
            echo "<p style='color: green;'>✅ getContent() gibt Übersetzungen zurück</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ getContent() gibt möglicherweise keine Übersetzungen zurück</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Fehler in getContent(): " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    // Teste AJAX-Aktionen
    echo "<h4>🔧 Teste AJAX-Aktionen</h4>";
    $success_count = 0;
    $total_count = 0;
    
    foreach ($config['test_actions'][$module_name] as $action) {
        $total_count++;
        echo "<p><strong>Teste: {$action}</strong></p>";
        
        try {
            $data = isset($test_data[$module_name][$action]) ? $test_data[$module_name][$action] : [];
            $result = $module->handleAjaxRequest($action, $data);
            
            if (is_array($result) && isset($result['success'])) {
                if ($result['success']) {
                    echo "<p style='color: green;'>✅ {$action}: Erfolgreich</p>";
                    $success_count++;
                } else {
                    echo "<p style='color: orange;'>⚠️ {$action}: " . htmlspecialchars($result['error'] ?? 'Unbekannter Fehler') . "</p>";
                }
            } else {
                echo "<p style='color: red;'>❌ {$action}: Ungültige Antwort</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ {$action}: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
    
    echo "<p><strong>Ergebnis: {$success_count}/{$total_count} Tests erfolgreich</strong></p>";
    
    return $success_count > 0;
}

function testLanguageFiles($module_name) {
    echo "<h4>🌐 Teste Sprachdateien</h4>";
    
    $lang_dir = "../module/{$module_name}/lang";
    $de_file = "{$lang_dir}/de.xml";
    $en_file = "{$lang_dir}/en.xml";
    
    $results = [];
    
    // Teste deutsche Sprachdatei
    if (file_exists($de_file)) {
        $xml = simplexml_load_file($de_file);
        if ($xml) {
            $de_count = count($xml->children());
            echo "<p style='color: green;'>✅ Deutsche Sprachdatei: {$de_count} Übersetzungen</p>";
            $results['de'] = $de_count;
        } else {
            echo "<p style='color: red;'>❌ Deutsche Sprachdatei: XML-Fehler</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Deutsche Sprachdatei nicht gefunden</p>";
    }
    
    // Teste englische Sprachdatei
    if (file_exists($en_file)) {
        $xml = simplexml_load_file($en_file);
        if ($xml) {
            $en_count = count($xml->children());
            echo "<p style='color: green;'>✅ Englische Sprachdatei: {$en_count} Übersetzungen</p>";
            $results['en'] = $en_count;
        } else {
            echo "<p style='color: red;'>❌ Englische Sprachdatei: XML-Fehler</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Englische Sprachdatei nicht gefunden</p>";
    }
    
    return $results;
}

function testTemplate($module_name) {
    echo "<h4>📋 Teste Template</h4>";
    
    $template_file = "../module/{$module_name}/templates/main.php";
    
    if (!file_exists($template_file)) {
        echo "<p style='color: red;'>❌ Template-Datei nicht gefunden</p>";
        return false;
    }
    
    $content = file_get_contents($template_file);
    
    // Prüfe auf Übersetzungsverwendung
    $translation_patterns = [
        '<?php echo $translations[',
        '$translations[',
        'translations['
    ];
    
    $found_translations = false;
    foreach ($translation_patterns as $pattern) {
        if (strpos($content, $pattern) !== false) {
            $found_translations = true;
            break;
        }
    }
    
    if ($found_translations) {
        echo "<p style='color: green;'>✅ Template verwendet Übersetzungen</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Template verwendet möglicherweise keine Übersetzungen</p>";
    }
    
    // Prüfe auf Bootstrap-Klassen
    $bootstrap_patterns = [
        'class="card"',
        'class="btn btn-',
        'class="form-control"',
        'class="row"',
        'class="col-'
    ];
    
    $found_bootstrap = false;
    foreach ($bootstrap_patterns as $pattern) {
        if (strpos($content, $pattern) !== false) {
            $found_bootstrap = true;
            break;
        }
    }
    
    if ($found_bootstrap) {
        echo "<p style='color: green;'>✅ Template verwendet Bootstrap-Klassen</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Template verwendet möglicherweise keine Bootstrap-Klassen</p>";
    }
    
    return $found_translations;
}

// Haupttest
echo "<!DOCTYPE html>
<html>
<head>
    <title>Test: Mehrsprachige Module (OVH, Proxmox, Virtual-MAC)</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background-color: #d4edda; border-color: #c3e6cb; }
        .error { background-color: #f8d7da; border-color: #f5c6cb; }
        .warning { background-color: #fff3cd; border-color: #ffeaa7; }
        pre { background-color: #f8f9fa; padding: 10px; border-radius: 3px; }
    </style>
</head>
<body>";

echo "<h1>🧪 Test: Mehrsprachige Module (OVH, Proxmox, Virtual-MAC)</h1>";
echo "<p>Dieses Skript testet die mehrsprachige Implementierung der Module OVH, Proxmox und Virtual-MAC.</p>";

$overall_results = [];

foreach ($config['test_modules'] as $module_name) {
    echo "<div class='test-section'>";
    echo "<h2>🔧 Modul: " . strtoupper($module_name) . "</h2>";
    
    // Teste Sprachdateien
    $lang_results = testLanguageFiles($module_name);
    
    // Teste Template
    $template_ok = testTemplate($module_name);
    
    // Teste Modul-Funktionalität
    $module_results = [];
    foreach ($config['test_languages'] as $lang) {
        $module_results[$lang] = testModule($module_name, $lang);
    }
    
    $overall_results[$module_name] = [
        'languages' => $lang_results,
        'template' => $template_ok,
        'functionality' => $module_results
    ];
    
    echo "</div>";
}

// Zusammenfassung
echo "<div class='test-section success'>";
echo "<h2>📊 Test-Zusammenfassung</h2>";

foreach ($overall_results as $module_name => $results) {
    echo "<h3>Modul: " . strtoupper($module_name) . "</h3>";
    
    // Sprachdateien
    if (!empty($results['languages'])) {
        $de_count = $results['languages']['de'] ?? 0;
        $en_count = $results['languages']['en'] ?? 0;
        echo "<p>🌐 Sprachdateien: DE ({$de_count}), EN ({$en_count})</p>";
    }
    
    // Template
    echo "<p>📋 Template: " . ($results['template'] ? '✅ OK' : '⚠️ Probleme') . "</p>";
    
    // Funktionalität
    $de_ok = $results['functionality']['de'] ?? false;
    $en_ok = $results['functionality']['en'] ?? false;
    echo "<p>🔧 Funktionalität: DE " . ($de_ok ? '✅' : '❌') . ", EN " . ($en_ok ? '✅' : '❌') . "</p>";
}

echo "</div>";

// Empfehlungen
echo "<div class='test-section warning'>";
echo "<h2>💡 Empfehlungen</h2>";
echo "<ul>";
echo "<li>Stellen Sie sicher, dass alle Module die getTranslations()-Methode implementieren</li>";
echo "<li>Überprüfen Sie, dass alle Templates Bootstrap-Klassen verwenden</li>";
echo "<li>Testen Sie die AJAX-Endpunkte mit echten Daten</li>";
echo "<li>Überprüfen Sie die Konsistenz der Übersetzungsschlüssel zwischen den Sprachen</li>";
echo "<li>Testen Sie die Sprachumschaltung in der Benutzeroberfläche</li>";
echo "</ul>";
echo "</div>";

echo "</body></html>";
?> 