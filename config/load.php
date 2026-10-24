<?php
if (PHP_SAPI !== 'cli' && (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__ || (get_included_files()[0] ?? null) === __FILE__)) { http_response_code(404); exit; }

// Runtime credentials are never taken from a request parameter or cookie.
if (!class_exists('Config', false)) {
    $configuration = getenv('SMF_CONFIG_FILE') ?: __DIR__ . '/config.inc.php';
    if (!is_file($configuration) || !is_readable($configuration)) {
        throw new RuntimeException('Configure SMF_CONFIG_FILE or copy config/config.example.php to config/config.inc.php');
    }
    require_once $configuration;
    if (!class_exists('Config', false)) throw new RuntimeException('The runtime configuration must define Config');
}
