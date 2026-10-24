<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/src/core/SchemaManager.php';
foreach (['sqlite' => ['install/database-structure-install-sqlite.sql','database/database-structure-sqlite.sql','config/framework-sqlite.sql'], 'mysql' => ['install/database-structure-install-mysql.sql','database/database-structure-optimized.sql','config/framework-mysql.sql'], 'pgsql' => ['install/database-structure-install-postgresql.sql','database/database-structure-postgresql.sql','config/framework-postgresql.sql']] as $driver => $paths) foreach ($paths as $path) file_put_contents(dirname(__DIR__) . '/' . $path, SchemaManager::sql($driver));
file_put_contents(dirname(__DIR__) . '/database/email-templates-structure.sql', '-- Compatibility snapshot; prefer bin/migrate.php. No template/account seeds.' . "\n" . SchemaManager::createTable('email_templates', SchemaManager::definition()['tables']['email_templates'], 'mysql') . ";\n");
echo "SQL snapshots generated from the canonical schema.\n";
