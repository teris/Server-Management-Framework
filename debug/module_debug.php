<?php
/**
 * Module Debug Script
 * Testet das Laden der Module
 */

require_once '../framework.php';
require_once '../module/ModuleBase.php';

echo "<h1>Module Debug</h1>";

// Test 1: Plugin-Konfiguration
echo "<h2>1. Plugin-Konfiguration</h2>";
$enabled_plugins = getEnabledPlugins();
echo "<pre>";
print_r($enabled_plugins);
echo "</pre>";

// Test 2: ModuleLoader
echo "<h2>2. ModuleLoader Test</h2>";
try {
    $moduleLoader = ModuleLoader::getInstance();
    echo "ModuleLoader erfolgreich erstellt<br>";
    
    $enabled_modules = $moduleLoader->getEnabledModules();
    echo "Anzahl geladener Module: " . count($enabled_modules) . "<br>";
    
    echo "<h3>Geladene Module:</h3>";
    foreach ($enabled_modules as $key => $module) {
        echo "Modul: $key<br>";
        echo "Klasse: " . get_class($module) . "<br>";
        echo "Pfad: " . $module->module_config['path'] . "<br>";
        echo "<br>";
    }
    
} catch (Exception $e) {
    echo "Fehler beim Laden der Module: " . $e->getMessage() . "<br>";
}

// Test 3: Spezifische Module testen
echo "<h2>3. Spezifische Module Test</h2>";

$test_modules = ['database', 'email'];

foreach ($test_modules as $module_key) {
    echo "<h3>Teste Modul: $module_key</h3>";
    
    // Teste Plugin-Konfiguration
    $config = getPluginConfig($module_key);
    echo "Plugin-Konfiguration: ";
    if ($config) {
        echo "OK<br>";
        echo "Pfad: " . $config['path'] . "<br>";
        echo "Aktiviert: " . ($config['enabled'] ? 'Ja' : 'Nein') . "<br>";
    } else {
        echo "FEHLER - Keine Konfiguration gefunden<br>";
        continue;
    }
    
    // Teste Module-Datei
    $module_file = $config['path'] . '/Module.php';
    echo "Module-Datei: $module_file<br>";
    if (file_exists($module_file)) {
        echo "Datei existiert: OK<br>";
        
        // Teste Klasse
        require_once $module_file;
        $class_name = ucfirst($module_key) . 'Module';
        echo "Erwartete Klasse: $class_name<br>";
        
        if (class_exists($class_name)) {
            echo "Klasse existiert: OK<br>";
            
            // Teste Instanziierung
            try {
                $module = new $class_name($module_key);
                echo "Instanziierung: OK<br>";
                
                // Teste getContent
                try {
                    $content = $module->getContent();
                    echo "getContent: OK (Länge: " . strlen($content) . ")<br>";
                } catch (Exception $e) {
                    echo "getContent Fehler: " . $e->getMessage() . "<br>";
                }
                
            } catch (Exception $e) {
                echo "Instanziierung Fehler: " . $e->getMessage() . "<br>";
            }
        } else {
            echo "Klasse existiert: FEHLER<br>";
        }
    } else {
        echo "Datei existiert: FEHLER<br>";
    }
    
    echo "<br>";
}

// Test 4: AJAX-Request simulieren
echo "<h2>4. AJAX-Request Simulation</h2>";

foreach ($test_modules as $module_key) {
    echo "<h3>Simuliere getContent für: $module_key</h3>";
    
    try {
        $moduleLoader = ModuleLoader::getInstance();
        $result = $moduleLoader->handlePluginRequest($module_key, 'getContent', []);
        
        echo "Ergebnis:<br>";
        echo "<pre>";
        print_r($result);
        echo "</pre>";
        
    } catch (Exception $e) {
        echo "Fehler: " . $e->getMessage() . "<br>";
    }
    
    echo "<br>";
}

echo "<h2>Debug abgeschlossen</h2>";
?> 