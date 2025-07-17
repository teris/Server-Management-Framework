<?php
/**
 * Testskript für das mehrsprachige Database-Modul
 * 
 * Dieses Skript testet die Übersetzungsfunktionalität des Database-Moduls
 * und zeigt, wie die verschiedenen Sprachen funktionieren.
 */

require_once '../framework.php';

// Konfiguration für Tests
$test_languages = ['de', 'en'];
$test_module = 'database';

echo "<h1>🧪 Test: Mehrsprachiges Database-Modul</h1>\n";
echo "<p>Dieses Skript testet die Übersetzungsfunktionalität des Database-Moduls.</p>\n";

// Test 1: Sprachdateien prüfen
echo "<h2>📁 Test 1: Sprachdateien prüfen</h2>\n";
foreach ($test_languages as $lang) {
    $lang_file = "../module/{$test_module}/lang/{$lang}.xml";
    if (file_exists($lang_file)) {
        echo "✅ Sprachdatei gefunden: <code>{$lang_file}</code><br>\n";
        
        // XML validieren
        $xml = simplexml_load_file($lang_file);
        if ($xml) {
            echo "✅ XML ist gültig für Sprache: {$lang}<br>\n";
        } else {
            echo "❌ XML ist ungültig für Sprache: {$lang}<br>\n";
        }
    } else {
        echo "❌ Sprachdatei fehlt: <code>{$lang_file}</code><br>\n";
    }
}

// Test 2: LanguageManager testen
echo "<h2>🔧 Test 2: LanguageManager testen</h2>\n";
$language_manager = new LanguageManager();

foreach ($test_languages as $lang) {
    echo "<h3>Sprache: {$lang}</h3>\n";
    
    // Sprache setzen
    $language_manager->setLanguage($lang);
    
    // Test-Übersetzungen
    $test_keys = [
        'module_title',
        'create_database',
        'database_name',
        'database_user',
        'password',
        'connection_info',
        'advanced_options'
    ];
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Schlüssel</th><th>Übersetzung</th></tr>\n";
    
    foreach ($test_keys as $key) {
        $translation = $language_manager->get($test_module, $key);
        $status = $translation ? '✅' : '❌';
        echo "<tr><td>{$key}</td><td>{$status} {$translation}</td></tr>\n";
    }
    
    echo "</table>\n";
}

// Test 3: Module-Übersetzungen testen
echo "<h2>📦 Test 3: Module-Übersetzungen testen</h2>\n";

foreach ($test_languages as $lang) {
    echo "<h3>Sprache: {$lang}</h3>\n";
    
    // Sprache in sys.conf.php setzen
    $_SESSION['language'] = $lang;
    
    // Module instanziieren
    $module = new DatabaseModule();
    
    // Übersetzungen abrufen
    $translations = $module->tMultiple([
        'module_title',
        'create_database',
        'database_name',
        'database_user',
        'password',
        'password_min_length',
        'connection_info',
        'host',
        'port',
        'charset',
        'advanced_options',
        'database_server_info',
        'generate_secure_password'
    ]);
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Schlüssel</th><th>Übersetzung</th></tr>\n";
    
    foreach ($translations as $key => $translation) {
        $status = $translation ? '✅' : '❌';
        echo "<tr><td>{$key}</td><td>{$status} {$translation}</td></tr>\n";
    }
    
    echo "</table>\n";
}

// Test 4: AJAX-Übersetzungen testen
echo "<h2>🔄 Test 4: AJAX-Übersetzungen testen</h2>\n";

foreach ($test_languages as $lang) {
    echo "<h3>Sprache: {$lang}</h3>\n";
    
    $_SESSION['language'] = $lang;
    $module = new DatabaseModule();
    
    // AJAX-Antwort simulieren
    $response = $module->handleAjaxRequest('get_translations', []);
    
    echo "<pre>" . print_r($response, true) . "</pre>\n";
}

// Test 5: Template-Rendering testen
echo "<h2>🎨 Test 5: Template-Rendering testen</h2>\n";

foreach ($test_languages as $lang) {
    echo "<h3>Sprache: {$lang}</h3>\n";
    
    $_SESSION['language'] = $lang;
    $module = new DatabaseModule();
    
    // Template rendern
    $content = $module->getContent();
    
    echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0; background: #f9f9f9;'>\n";
    echo "<strong>Gerendertes Template:</strong><br>\n";
    echo htmlspecialchars(substr($content, 0, 500)) . "...\n";
    echo "</div>\n";
}

// Test 6: Fehlerbehandlung testen
echo "<h2>⚠️ Test 6: Fehlerbehandlung testen</h2>\n";

$_SESSION['language'] = 'de';
$module = new DatabaseModule();

// Unbekannte Aktion
$response = $module->handleAjaxRequest('unknown_action', []);
echo "<p><strong>Unbekannte Aktion:</strong> " . json_encode($response) . "</p>\n";

// Validierungsfehler simulieren
$response = $module->handleAjaxRequest('create_database', []);
echo "<p><strong>Validierungsfehler:</strong> " . json_encode($response) . "</p>\n";

echo "<h2>✅ Test abgeschlossen</h2>\n";
echo "<p>Das Database-Modul wurde erfolgreich auf Mehrsprachigkeit getestet.</p>\n";
echo "<p><strong>Verfügbare Sprachen:</strong> " . implode(', ', $test_languages) . "</p>\n";
echo "<p><strong>Standardsprache:</strong> " . ($_SESSION['language'] ?? 'de') . "</p>\n";
?> 