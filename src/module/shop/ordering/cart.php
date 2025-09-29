<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? 'list';
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

function jsonResponse($data){ echo json_encode($data); exit; }

switch ($action) {
    case 'add':
        $sku = $_POST['sku'] ?? ($_GET['sku'] ?? '');
        $qty = max(1, intval($_POST['qty'] ?? ($_GET['qty'] ?? 1)));
        if ($sku === '') jsonResponse(['success'=>false,'error'=>'SKU fehlt']);
        $_SESSION['cart'][$sku] = ($_SESSION['cart'][$sku] ?? 0) + $qty;
        jsonResponse(['success'=>true,'data'=>$_SESSION['cart']]);
    case 'set':
        $sku = $_POST['sku'] ?? ($_GET['sku'] ?? '');
        $qty = intval($_POST['qty'] ?? ($_GET['qty'] ?? 0));
        if ($sku === '') jsonResponse(['success'=>false,'error'=>'SKU fehlt']);
        if ($qty <= 0) {
            unset($_SESSION['cart'][$sku]);
        } else {
            $_SESSION['cart'][$sku] = $qty;
        }
        jsonResponse(['success'=>true,'data'=>$_SESSION['cart']]);
    case 'remove':
        $sku = $_POST['sku'] ?? ($_GET['sku'] ?? '');
        if (isset($_SESSION['cart'][$sku])) unset($_SESSION['cart'][$sku]);
        jsonResponse(['success'=>true,'data'=>$_SESSION['cart']]);
    case 'clear':
        $_SESSION['cart'] = [];
        jsonResponse(['success'=>true,'data'=>$_SESSION['cart']]);
    case 'list':
    default:
        jsonResponse(['success'=>true,'data'=>$_SESSION['cart']]);
}


