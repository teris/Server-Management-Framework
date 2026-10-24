<?php
if (PHP_SAPI !== 'cli' && (get_included_files()[0] ?? null) === __FILE__) { http_response_code(404); exit; }
// One validated administration flow replaces the old multi-step form that retained plaintext passwords.
require __DIR__ . '/users.php';
