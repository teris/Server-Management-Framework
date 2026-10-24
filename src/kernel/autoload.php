<?php
if (PHP_SAPI !== 'cli' && (get_included_files()[0] ?? null) === __FILE__) { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/core/SchemaManager.php';
require_once dirname(__DIR__) . '/core/VersionFile.php';
spl_autoload_register(static function (string $class): void {
    $prefix = 'SMF\\Core\\';
    if (!str_starts_with($class, $prefix)) return;
    $name = substr($class, strlen($prefix));
    if (!preg_match('/^[A-Za-z][A-Za-z0-9]*$/D', $name)) return;
    $file = __DIR__ . '/' . $name . '.php';
    if (is_file($file) && !is_link($file)) require_once $file;
});
