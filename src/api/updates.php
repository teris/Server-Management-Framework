<?php
require_once __DIR__ . '/../sys.conf.php';
require_once dirname(__DIR__,2) . '/framework.php';
require_once __DIR__ . '/../core/UpdateManager.php';
$actor = Identity::requireIdentity('backend',true);
header('Content-Type: application/json; charset=utf-8');
$manager = new UpdateManager(dirname(__DIR__,2));
if (($_SERVER['REQUEST_METHOD']??'GET')==='GET') { echo json_encode(['success'=>true,'jobs'=>$manager->jobs()],JSON_THROW_ON_ERROR); exit; }
if (($_SERVER['REQUEST_METHOD']??'GET')!=='POST') HttpSecurity::abort(405,'Methode nicht erlaubt.');
try {
    $action=$_POST['action']??'';
    if($action==='register') {
        $upload=$_FILES['package']??null;
        if(!$upload||($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($upload['tmp_name']))throw new InvalidArgumentException('Ein gültiges signiertes ZIP-Paket ist erforderlich.');
        $result=$manager->register($upload['tmp_name'],$actor['id']);
    } else $result=$manager->queue((string)($_POST['id']??''),(string)$action);
    echo json_encode(['success'=>true,'job'=>$result],JSON_THROW_ON_ERROR);
} catch (Throwable $e) { error_log('Update request rejected: '.get_class($e)); HttpSecurity::abort(400,'Update konnte nicht angenommen werden. Paket, Vertrauensschlüssel und aktuellen Auftragsstatus prüfen.'); }
