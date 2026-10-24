<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/framework.php';
$count = 0;
foreach (HttpSecurity::connection()->query("SELECT id, username, password_hash FROM users WHERE role = 'admin' AND active = 'y'") as $row) {
    foreach (['admin123', 'password', 'admin'] as $known) {
        if (password_verify($known, $row['password_hash'])) { echo 'Known default credential on administrator ID ' . (int) $row['id'] . "\n"; $count++; break; }
    }
}
echo $count ? "Change those passwords explicitly before deployment. No account was modified.\n" : "No checked default administrator credential detected.\n";
exit($count ? 2 : 0);
