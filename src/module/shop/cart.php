<?php
session_start();
header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? 'list';
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

function jsonResponse($data){ echo json_encode($data); exit; }

switch ($action) {
    case 'add':
        $sku = $_POST['sku'] ?? '';
        $qty = max(1, intval($_POST['qty'] ?? 1));
        if ($sku === '') jsonResponse(['success'=>false,'error'=>'SKU fehlt']);
        $_SESSION['cart'][$sku] = ($_SESSION['cart'][$sku] ?? 0) + $qty;
        jsonResponse(['success'=>true,'data'=>$_SESSION['cart']]);
    case 'remove':
        $sku = $_POST['sku'] ?? '';
        if (isset($_SESSION['cart'][$sku])) unset($_SESSION['cart'][$sku]);
        jsonResponse(['success'=>true,'data'=>$_SESSION['cart']]);
    case 'clear':
        $_SESSION['cart'] = [];
        jsonResponse(['success'=>true,'data'=>$_SESSION['cart']]);
    case 'list':
    default:
        jsonResponse(['success'=>true,'data'=>$_SESSION['cart']]);
}