<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
header('Content-Type: application/json');

require_once __DIR__ . '/../../../../src/sys.conf.php';
require_once __DIR__ . '/../../../../framework.php';

function jsonResponse($data){ echo json_encode($data); exit; }

$action = $_REQUEST['action'] ?? 'summary';
$db = DatabaseManager::getInstance()->getDriver();

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

function loadProducts($db, $skus){
    if (empty($skus)) return [];
    $in = implode(',', array_fill(0, count($skus), '?'));
    $stmt = $db->prepare("SELECT sku, name, price_cents, currency FROM shop_products WHERE sku IN ($in)");
    $db->execute($stmt, $skus);
    $rows = $db->fetchAll($stmt);
    $map = [];
    foreach ($rows as $r) { $map[$r['sku']] = $r; }
    return $map;
}

switch ($action) {
    case 'auth':
        $logged = (!empty($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true);
        jsonResponse(['success'=>true, 'data'=>['logged_in'=>$logged]]);

    case 'summary':
        $cart = $_SESSION['cart'];
        $skus = array_keys($cart);
        $products = loadProducts($db, $skus);
        $items = [];
        $total = 0;
        foreach ($cart as $sku => $qty) {
            if (!isset($products[$sku])) continue;
            $p = $products[$sku];
            $line = $p['price_cents'] * $qty;
            $total += $line;
            $items[] = [ 'sku'=>$sku, 'name'=>$p['name'], 'quantity'=>$qty, 'price_cents'=>$p['price_cents'], 'currency'=>$p['currency'] ];
        }
        jsonResponse(['success'=>true, 'data'=>['items'=>$items, 'total_cents'=>$total, 'currency'=> $items[0]['currency'] ?? 'EUR']]);

    case 'create':
        $email = $_POST['email'] ?? ($_SESSION['customer_email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(['success'=>false,'error'=>'Ungültige E-Mail']);
        $cart = $_SESSION['cart'];
        $skus = array_keys($cart);
        $products = loadProducts($db, $skus);
        if (empty($products)) jsonResponse(['success'=>false,'error'=>'Warenkorb leer']);

        $items = [];
        $total = 0;
        foreach ($cart as $sku => $qty) {
            if (!isset($products[$sku])) continue;
            $p = $products[$sku];
            $line = $p['price_cents'] * $qty;
            $total += $line;
            $items[] = [ 'sku'=>$sku, 'name'=>$p['name'], 'quantity'=>$qty, 'price_cents'=>$p['price_cents'], 'currency'=>$p['currency'] ];
        }

        $orderNo = 'S' . date('YmdHis') . substr((string)mt_rand(1000,9999),0,4);
        $stmt = $db->prepare("INSERT INTO shop_orders (order_number, customer_email, total_cents, currency, status) VALUES (?, ?, ?, ?, 'pending')");
        $db->execute($stmt, [ $orderNo, $email, $total, $items[0]['currency'] ?? 'EUR' ]);
        $stmt = $db->query("SELECT LAST_INSERT_ID() as id");
        $row = $db->fetch($stmt);
        $orderId = $row ? intval($row['id']) : 0;
        foreach ($items as $it) {
            $s = $db->prepare("INSERT INTO shop_order_items (order_id, sku, name, quantity, price_cents, currency) VALUES (?, ?, ?, ?, ?, ?)");
            $db->execute($s, [ $orderId, $it['sku'], $it['name'], $it['quantity'], $it['price_cents'], $it['currency'] ]);
        }
        // E-Mail Bestellübersicht senden (Template: Bestellbestätigung)
        $itemsHtml = '';
        foreach ($items as $it) {
            $line = number_format($it['price_cents']/100, 2, ',', '.') . ' ' . $it['currency'] . ' x ' . intval($it['quantity']);
            $itemsHtml .= '<li>' . htmlspecialchars($it['name']) . ' (' . htmlspecialchars($it['sku']) . '): ' . $line . '</li>';
        }
        $totalFormatted = number_format($total/100, 2, ',', '.') . ' ' . ($items[0]['currency'] ?? 'EUR');
        $vars = [
            'order_number' => $orderNo,
            'order_total' => $totalFormatted,
            'order_items' => '<ul>' . $itemsHtml . '</ul>'
        ];
        // Falls kein Template vorhanden, createDefaultTemplates wird nur Standard erzeugen; Bestell-Template optional
        // Versuche spezifisches Template (falls vorhanden), sonst Fallback
        $sent = false;
        if (function_exists('sendEmailFromTemplate') && function_exists('isEmailTemplateSystemAvailable') && isEmailTemplateSystemAvailable()) {
            $sent = sendEmailFromTemplate('Bestellbestätigung', $email, $vars);
        }
        if (!$sent) {
            @mail($email, 'Ihre Bestellung ' . $orderNo, "Vielen Dank für Ihre Bestellung.\nBestellnummer: $orderNo\nSumme: $totalFormatted", 'Content-Type: text/plain; charset=utf-8');
        }

        jsonResponse(['success'=>true, 'data'=>['order_number'=>$orderNo, 'order_id'=>$orderId]]);

    case 'select_payment':
        $method = $_POST['method'] ?? '';
        $settings = [];
        try { include __DIR__ . '/../../../../src/sys.conf.php'; } catch (\Throwable $t) {}
        $enabled = !empty($shop_settings['addons']['paypal']['enabled']);
        if ($method !== 'paypal' || !$enabled) jsonResponse(['success'=>false,'error'=>'Zahlmethode nicht verfügbar']);
        jsonResponse(['success'=>true]);

    case 'confirm':
        $orderNo = $_POST['order_number'] ?? '';
        if ($orderNo === '') jsonResponse(['success'=>false,'error'=>'order_number fehlt']);
        $stmt = $db->prepare("UPDATE shop_orders SET status='paid', payment_method='paypal' WHERE order_number=?");
        $db->execute($stmt, [ $orderNo ]);
        $_SESSION['cart'] = [];
        jsonResponse(['success'=>true]);

    default:
        jsonResponse(['success'=>false,'error'=>'unknown action']);
}


