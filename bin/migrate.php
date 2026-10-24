<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/framework.php';
require_once dirname(__DIR__) . '/src/core/SchemaManager.php';
require_once dirname(__DIR__) . '/src/core/SecuritySchema.php';
$options = getopt('', ['status','resume']); $db = Database::getInstance()->getConnection();
if (isset($options['status'])) { echo json_encode(['target' => SchemaManager::definition()['version'], 'tables' => SchemaManager::tables($db)], JSON_PRETTY_PRINT) . "\n"; exit; }
$lock = fopen(HttpSecurity::privateDirectory('locks') . '/schema-migration.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Another migration is active');
try {
    $result = HttpSecurity::schemaMaintenance(function() use ($db) {
        $result = SchemaManager::migrate($db); SecuritySchema::migrate($db); \SMF\Core\CoreMigrations::migrate($db);
        require_once dirname(__DIR__) . '/src/core/EmailTemplateManager.php';
        (new EmailTemplateManager($db))->createDefaultTemplates(); return $result;
    }, isset($options['resume']));
    echo json_encode($result) . "\n";
}
finally { flock($lock, LOCK_UN); fclose($lock); }
