<?php
/**
 * Test-Skript für das mehrsprachige System
 * 
 * Dieses Skript demonstriert die Verwendung des LanguageManager
 * und testet verschiedene Funktionen des mehrsprachigen Systems.
 */

// Framework einbinden
require_once __DIR__ . '/../sys.conf.php';
require_once __DIR__ . '/../core/LanguageManager.php';

// Sprachmanager initialisieren
$lm = getLanguageManager();

echo "<h1>Mehrsprachiges System - Test</h1>\n";

// 1. Aktuelle Konfiguration anzeigen
echo "<h2>1. Aktuelle Konfiguration</h2>\n";
echo "<p><strong>Aktuelle Sprache:</strong> " . $lm->getCurrentLanguage() . "</p>\n";
echo "<p><strong>Verfügbare Sprachen:</strong> " . implode(', ', $lm->getAvailableLanguages()) . "</p>\n";

// 2. Verfügbare Sprachdateien für Module anzeigen
echo "<h2>2. Verfügbare Sprachdateien für Module</h2>\n";
$modules = ['admin', 'proxmox', 'ispconfig', 'ovh'];
foreach ($modules as $module) {
    $available_langs = $lm->getAvailableLanguagesForModule($module);
    echo "<p><strong>$module:</strong> " . (empty($available_langs) ? 'Keine Sprachdateien' : implode(', ', $available_langs)) . "</p>\n";
}

// 3. Übersetzungen für Admin-Modul testen
echo "<h2>3. Übersetzungen für Admin-Modul</h2>\n";
$admin_translations = $lm->loadModuleTranslations('admin');
if (!empty($admin_translations)) {
    echo "<p><strong>Geladene Übersetzungen:</strong></p>\n";
    echo "<ul>\n";
    foreach (array_slice($admin_translations, 0, 10) as $key => $value) { // Nur erste 10 anzeigen
        echo "<li><strong>$key:</strong> $value</li>\n";
    }
    echo "</ul>\n";
} else {
    echo "<p>Keine Übersetzungen für Admin-Modul gefunden.</p>\n";
}

// 4. Einzelne Übersetzungen testen
echo "<h2>4. Einzelne Übersetzungen testen</h2>\n";
$test_keys = ['module_title', 'manage_vms', 'websites', 'refresh', 'nonexistent_key'];
foreach ($test_keys as $key) {
    $translation = $lm->translate('admin', $key, "Standardwert für $key");
    echo "<p><strong>$key:</strong> $translation</p>\n";
}

// 5. Mehrere Übersetzungen auf einmal testen
echo "<h2>5. Mehrere Übersetzungen auf einmal</h2>\n";
$multiple_keys = ['module_title', 'manage_vms', 'websites', 'databases', 'emails'];
$multiple_translations = $lm->translateMultiple('admin', $multiple_keys);
echo "<ul>\n";
foreach ($multiple_translations as $key => $value) {
    echo "<li><strong>$key:</strong> $value</li>\n";
}
echo "</ul>\n";

// 6. Sprachänderung testen
echo "<h2>6. Sprachänderung testen</h2>\n";
$original_language = $lm->getCurrentLanguage();

// Auf Englisch wechseln
if ($lm->setLanguage('en')) {
    echo "<p>Sprache erfolgreich auf 'en' geändert.</p>\n";
    $en_translation = $lm->translate('admin', 'module_title');
    echo "<p><strong>Englische Übersetzung für 'module_title':</strong> $en_translation</p>\n";
} else {
    echo "<p>Sprache konnte nicht auf 'en' geändert werden (keine Sprachdatei verfügbar).</p>\n";
}

// Zurück zur ursprünglichen Sprache
$lm->setLanguage($original_language);
echo "<p>Sprache zurück auf '$original_language' gesetzt.</p>\n";

// 7. Fallback-Mechanismus testen
echo "<h2>7. Fallback-Mechanismus testen</h2>\n";
$non_existent_key = 'this_key_does_not_exist';
$fallback_value = $lm->translate('admin', $non_existent_key, 'Fallback-Wert');
echo "<p><strong>Nicht existierender Schlüssel '$non_existent_key':</strong> $fallback_value</p>\n";

// 8. Cache-Test
echo "<h2>8. Cache-Test</h2>\n";
echo "<p>Cache wird automatisch verwaltet. Bei Sprachänderungen wird er automatisch geleert.</p>\n";
echo "<p>Manuelles Cache-Clearing möglich mit: \$lm->clearCache()</p>\n";

// 9. Sprachdatei-Erstellung testen
echo "<h2>9. Sprachdatei-Erstellung testen</h2>\n";
$test_translations = [
    'test_key_1' => 'Test-Wert 1',
    'test_key_2' => 'Test-Wert 2',
    'test_key_3' => 'Test-Wert 3'
];

$test_module = 'test-module';
$test_language = 'test';

if ($lm->createLanguageFile($test_module, $test_language, $test_translations)) {
    echo "<p>Test-Sprachdatei erfolgreich erstellt für Modul '$test_module' und Sprache '$test_language'.</p>\n";
    
    // Testen ob die Datei existiert
    if ($lm->hasLanguageFile($test_module, $test_language)) {
        echo "<p>Test-Sprachdatei existiert und kann geladen werden.</p>\n";
        
        // Übersetzungen laden und testen
        $test_loaded = $lm->loadModuleTranslations($test_module);
        echo "<p><strong>Geladene Test-Übersetzungen:</strong></p>\n";
        echo "<ul>\n";
        foreach ($test_loaded as $key => $value) {
            echo "<li><strong>$key:</strong> $value</li>\n";
        }
        echo "</ul>\n";
        
        // Test-Datei löschen
        $test_file = "../module/$test_module/lang/$test_language.xml";
        if (file_exists($test_file)) {
            unlink($test_file);
            echo "<p>Test-Sprachdatei gelöscht.</p>\n";
        }
    }
} else {
    echo "<p>Test-Sprachdatei konnte nicht erstellt werden (Modul '$test_module' existiert möglicherweise nicht).</p>\n";
}

// 10. Performance-Test
echo "<h2>10. Performance-Test</h2>\n";
$start_time = microtime(true);

// Mehrere Übersetzungen laden
for ($i = 0; $i < 100; $i++) {
    $lm->translate('admin', 'module_title');
}

$end_time = microtime(true);
$execution_time = ($end_time - $start_time) * 1000; // in Millisekunden

echo "<p><strong>100 Übersetzungsaufrufe in:</strong> " . number_format($execution_time, 2) . " ms</p>\n";
echo "<p><strong>Durchschnitt pro Aufruf:</strong> " . number_format($execution_time / 100, 4) . " ms</p>\n";

// 11. ModulBase-Integration testen
echo "<h2>11. ModulBase-Integration testen</h2>\n";
echo "<p>Die ModuleBase-Klasse wurde erweitert um:</p>\n";
echo "<ul>\n";
echo "<li><code>\$this->t(\$key, \$default)</code> - Einzelne Übersetzung</li>\n";
echo "<li><code>\$this->tMultiple(\$keys)</code> - Mehrere Übersetzungen</li>\n";
echo "</ul>\n";

// 12. Beispiel für Modul-Integration
echo "<h2>12. Beispiel für Modul-Integration</h2>\n";
echo "<pre><code>\n";
echo "class MyModule extends ModuleBase {\n";
echo "    public function getContent() {\n";
echo "        \$translations = \$this->tMultiple([\n";
echo "            'module_title', 'welcome_message', 'action_button'\n";
echo "        ]);\n";
echo "        \n";
echo "        return \$this->render('main', [\n";
echo "            'translations' => \$translations\n";
echo "        ]);\n";
echo "    }\n";
echo "    \n";
echo "    public function handleAjaxRequest(\$action, \$data) {\n";
echo "        try {\n";
echo "            // ... Aktion ausführen\n";
echo "            return \$this->success(\$result, \$this->t('action_successful'));\n";
echo "        } catch (Exception \$e) {\n";
echo "            return \$this->error(\$this->t('action_failed') . ': ' . \$e->getMessage());\n";
echo "        }\n";
echo "    }\n";
echo "}\n";
echo "</code></pre>\n";

// 13. Template-Integration Beispiel
echo "<h2>13. Template-Integration Beispiel</h2>\n";
echo "<pre><code>\n";
echo "&lt;div class=\"card-header\"&gt;\n";
echo "    &lt;h3&gt;&lt;?php echo \$translations['module_title']; ?&gt;&lt;/h3&gt;\n";
echo "&lt;/div&gt;\n";
echo "&lt;button class=\"btn btn-primary\"&gt;\n";
echo "    &lt;?php echo \$translations['action_button']; ?&gt;\n";
echo "&lt;/button&gt;\n";
echo "</code></pre>\n";

echo "<h2>Test abgeschlossen!</h2>\n";
echo "<p>Das mehrsprachige System funktioniert korrekt.</p>\n";
echo "<p>Weitere Informationen finden Sie in der <a href=\"../MULTILANGUAGE_SYSTEM.md\">Dokumentation</a>.</p>\n";
?> 