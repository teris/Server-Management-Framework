<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/src/core/Security.php';
$root = dirname(__DIR__); $source = $root . '/config/config.inc.php';
$directory = HttpSecurity::privateDirectory('config'); $destination = $directory . '/runtime.php';
if (!is_file($source) || is_link($source)) { fwrite(STDERR, "No regular local configuration to migrate.\n"); exit(1); }
$content = file_get_contents($source);
$shim = "<?php\nrequire_once " . var_export($destination, true) . ";\n";
if (is_link($destination)) throw new RuntimeException('Private configuration symlink rejected');
if (is_file($destination)) {
    if ($content === $shim) { echo "Private configuration already active.\n"; exit(0); }
    if (!hash_equals(hash('sha256', $content), hash_file('sha256', $destination))) {
        fwrite(STDERR, "Different private configuration already exists; no file was replaced.\n"); exit(2);
    }
} else {
    $copy = fopen($destination, 'x');
    if (!$copy) throw new RuntimeException('Private configuration already claimed');
    try { if (fwrite($copy, $content) !== strlen($content)) throw new RuntimeException('Configuration copy failed'); }
    finally { fclose($copy); }
    chmod($destination, 0600);
}
if (!hash_equals(hash('sha256', $content), hash_file('sha256', $destination))) throw new RuntimeException('Configuration verification failed');
$legacyKey = $root . '/src/.encryption_key';
if (is_file($legacyKey)) {
    if (is_link($legacyKey)) throw new RuntimeException('Legacy key symlink rejected');
    $key = HttpSecurity::privateDirectory('keys') . '/provider-encryption.key';
    if (is_link($key) || (is_file($key) && !hash_equals(hash_file('sha256', $key), hash_file('sha256', $legacyKey)))) throw new RuntimeException('Different existing keys; no key was replaced');
    if (!is_file($key)) { copy($legacyKey, $key); chmod($key, 0600); }
    if (!hash_equals(hash_file('sha256', $key), hash_file('sha256', $legacyKey))) throw new RuntimeException('Key verification failed');
    unlink($legacyKey);
}
$temporary = tempnam(dirname($source), '.config-migration-');
if (!$temporary || file_put_contents($temporary, $shim, LOCK_EX) !== strlen($shim)) throw new RuntimeException('Configuration shim failed');
chmod($temporary, 0600);
if (!rename($temporary, $source)) { unlink($temporary); throw new RuntimeException('Configuration activation failed'); }
echo "Configuration moved outside the webroot; local loader preserved. No credential values were printed.\n";
