<?php
require_once __DIR__ . '/../sys.conf.php';
require_once dirname(__DIR__, 2) . '/framework.php';
require_once __DIR__ . '/../core/ResourceAccess.php';

$realm = ($_REQUEST['context'] ?? 'customer') === 'backend' ? 'backend' : 'customer';
$identity = Identity::requireIdentity($realm);
if (in_array($modus_type['modus'] ?? 'api', ['database','mysql'], true)) HttpSecurity::abort(503, 'Providerzugriffe sind im Datenbank-Cachemodus deaktiviert.');
$action = $_REQUEST['action'] ?? 'view';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== ($action === 'view' ? 'GET' : 'POST')) HttpSecurity::abort(405, 'Methode nicht erlaubt.');
$manager = null;
$result = ResourceAccess::execute($identity, (string) ($_REQUEST['module'] ?? ''), (string) ($_REQUEST['id'] ?? ''), $action,
    function ($provider,$method,$path,$data) {
        return SMF\Core\Kernel::boot()->providers()->get($provider)->call($method,$path,$data);
    });
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'data' => $result]);
