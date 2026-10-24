<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/framework.php';
HttpSecurity::cliBoundary();
$options = getopt('', ['username:', 'email:']);
if (!preg_match('/^[a-zA-Z0-9_.-]{3,100}$/D', $options['username'] ?? '') || !filter_var($options['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php bin/create-admin.php --username=name --email=address (password via standard input)\n"); exit(1);
}
fwrite(STDERR, "New administrator password (at least 12 characters): ");
$terminal = function_exists('stream_isatty') && stream_isatty(STDIN);
if ($terminal && PHP_OS_FAMILY !== 'Windows') system('stty -echo');
try { $password = rtrim(fgets(STDIN) ?: '', "\r\n"); }
finally { if ($terminal && PHP_OS_FAMILY !== 'Windows') system('stty echo'); }
if (strlen($password) < 12 || strlen($password) > 4096) { fwrite(STDERR, "\nPassword must have 12–4096 characters.\n"); exit(1); }
try {
    $db = HttpSecurity::connection();
    $stmt = $db->prepare("INSERT INTO users (username,email,password_hash,full_name,role,active,created_at) VALUES (?,?,?,?,'admin','y',CURRENT_TIMESTAMP)");
    $stmt->execute([$options['username'], $options['email'], password_hash($password, PASSWORD_DEFAULT), $options['username']]);
    echo "\nAdministrator created. Existing accounts were not changed.\n";
} catch (Throwable $e) { fwrite(STDERR, "\nAdministrator creation failed: " . get_class($e) . "\n"); exit(1); }
