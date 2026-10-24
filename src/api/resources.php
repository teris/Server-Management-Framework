<?php
require_once __DIR__ . '/../sys.conf.php';
require_once dirname(__DIR__, 2) . '/framework.php';
require_once __DIR__ . '/../core/ResourceAccess.php';

$realm = ($_REQUEST['context'] ?? 'customer') === 'backend' ? 'backend' : 'customer';
$identity = Identity::requireIdentity($realm);
$action = $_REQUEST['action'] ?? 'view';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== ($action === 'view' ? 'GET' : 'POST')) HttpSecurity::abort(405, 'Methode nicht erlaubt.');
$manager = null;
$result = ResourceAccess::execute($identity, (string) ($_REQUEST['module'] ?? ''), (string) ($_REQUEST['id'] ?? ''), $action,
    function ($provider, $method, $path, $data) use (&$manager) {
        // Instantiate only after authentication, ownership, account binding and action checks.
        if (!$manager) $manager = new ServiceManager();
        if ($provider === 'proxmox') return $manager->ProxmoxAPI(strtolower($method), $path, $data);
        if ($provider === 'ispconfig') return $manager->IspconfigAPI(strtolower($method), $path, $data);
        return $manager->OvhAPI($method, $path, $data);
    });
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'data' => $result]);
