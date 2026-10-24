<?php
if (PHP_SAPI !== 'cli' && (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__ || (get_included_files()[0] ?? null) === __FILE__)) { http_response_code(404); exit; }

require_once __DIR__ . '/sys.conf.php';
require_once dirname(__DIR__) . '/framework.php';
require_once __DIR__ . '/auth_handler.php';
require_once __DIR__ . '/core/ActivityLogger.php';

if (isset($_GET['debug'])) HttpSecurity::abort(404, 'Nicht gefunden.');
$customerPages = ['dashboard.php', 'profile.php', 'change-password.php', 'support.php', 'clear-activities.php', 'domain-registration.php'];
$entryPage = basename(HttpSecurity::requestScript());
if (in_array($entryPage, $customerPages, true)) {
    try { Identity::requireIdentity('customer'); }
    catch (SecurityViolation $e) {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' || $entryPage === 'clear-activities.php') HttpSecurity::abort($e->getCode(), $e->getMessage());
        header('Location: login.php', true, 303); exit;
    }
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    Identity::logout(); header('Location: login.php', true, 303); exit;
}
