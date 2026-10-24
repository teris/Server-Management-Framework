<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/framework.php';
require_once dirname(__DIR__) . '/src/core/SecuritySchema.php';
try { echo SecuritySchema::migrate(HttpSecurity::connection()) ? "Security migration applied.\n" : "Security schema already current.\n"; }
catch (Throwable $e) { fwrite(STDERR, 'Security migration failed: ' . get_class($e) . "\n"); exit(1); }
