<?php
require_once __DIR__ . '/../framework.php';
require_once __DIR__ . '/core/AdminCore.php';
$core = new AdminCore();
// Optional: Zugriffsschutz, z.B. Session-Check
// session_start();
// if (!isset($_SESSION['user_id'])) { http_response_code(403); exit; }
header('Content-Type: application/json');
echo json_encode($core->getIpMacReverseTable()); 