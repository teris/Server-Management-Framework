<?php
/** A zero-price base has no PayPal payment. Only its paid module contracts release resources. */
function zero_base_order(array $order): bool {
    $c=json_decode($order['configuration'],true);
    return (int)$order['amount_cents']===0 && $order['payment_kind']==='no_charge' && in_array($order['provider'],['ispconfig','proxmox'],true) && billing_months($order['billing'])>0 && ($c['zero_base_version']??0)===1 && !empty($c['module_cart_lines']);
}
function zero_base_initial_parent(array $intent): ?array {
    $parent=one('SELECT * FROM shop_orders WHERE id=? AND customer_id=?',[$intent['parent_order_id'],$intent['customer_id']]);if(!$parent || !zero_base_order($parent))return null;
    foreach(json_decode($parent['configuration'],true)['module_cart_lines'] as $line)if(hash_equals(hash('sha256','cart:'.$parent['id'].':'.json_encode($line)),$intent['checkout_key']) && json_encode($line)===$intent['snapshot'])return $parent;
    return null;
}
function zero_base_checkout_allowed(array $parent): bool {
    $c=ensure_order_contract($parent);
    return zero_base_order($parent) && $parent['payment_status']==='no_charge' && !in_array($parent['fulfillment_status'],['cancelled','ended'],true) && $c && !contract_closed($c) && !(int)$c['cancel_at'];
}
function accept_zero_base_checkout(array $order): array {
    if(!zero_base_order($order))throw new RuntimeException('Ein Grundpaket ohne Grundpreis benötigt mindestens ein kostenpflichtiges Zusatzmodul.');
    query("UPDATE shop_orders SET payment_status='no_charge',fulfillment_status='awaiting_modules' WHERE id=? AND payment_status='pending'",[$order['id']]);
    $order=one('SELECT * FROM shop_orders WHERE id=?',[$order['id']]);
    if(!zero_base_checkout_allowed($order))throw new RuntimeException('Diese Bestellung ist bereits beendet. Bitte eine neue Auswahl erstellen.');
    // A genuine zero invoice, without a fabricated payment or a zero-value PayPal subscription.
    $r=initial_order_invoice($order);if($r)query("UPDATE shop_receivables SET state='paid',settled_ts=? WHERE invoice_id=? AND state='open' AND amount_cents=0",[shop_now(),$r['invoice_id']]);
    commerce_event('order:'.$order['id'],'order.created',['order_id'=>(int)$order['id']]);audit_event('checkout.zero_base.accepted',$order['reference']);return $order;
}
function zero_base_payment_ready(array $parent): bool {
    if(!zero_base_checkout_allowed($parent) || $parent['paypal_mode']!=='live')return false;
    $c=json_decode($parent['configuration'],true);$established=(bool)service_for_order((int)$parent['id'],(int)$parent['customer_id']);
    foreach($c['module_cart_lines'] as $line){
        if(empty($line['id']) || (int)($line['net_cents']??0)<1 || !empty($line['included']))return false;
        $key=hash('sha256','cart:'.$parent['id'].':'.json_encode($line));$i=one('SELECT * FROM shop_module_intents WHERE checkout_key=? AND parent_order_id=? AND customer_id=?',[$key,$parent['id'],$parent['customer_id']]);
        if(!$i || !$i['order_id'] || !(int)$i['first_settled'] || $i['snapshot']!==json_encode($line))return false;
        $order=one('SELECT * FROM shop_orders WHERE id=? AND customer_id=?',[$i['order_id'],$parent['customer_id']]);
        if(!$order || $order['paypal_mode']!==$parent['paypal_mode'] || !order_payment_allows_provisioning($order)){
            // Once installed, the original module may expire normally; its confirmed initial payment remains evidence.
            if(!$established || !$order || $order['payment_status']!=='paid' || !one("SELECT id FROM shop_payments WHERE order_id=? AND status='completed' AND paypal_mode='live'",[$order['id']]))return false;
        }
        if(!$established){$contract=ensure_order_contract($order);if(!$contract || contract_closed($contract) || (int)$contract['cancel_at'])return false;}
    }
    return true;
}
function sync_zero_base_order(array $parent): void {
    if(!zero_base_order($parent) || !zero_base_payment_ready($parent))return;
    db()->beginTransaction();try{
        $lock=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';$parent=one('SELECT * FROM shop_orders WHERE id=?'.$lock,[$parent['id']]);
        if(!zero_base_payment_ready($parent)){db()->commit();return;}
        $c=ensure_order_contract($parent);$start=(int)$c['started_at']?:shop_now();$until=max((int)$c['paid_until'],(int)(json_decode($parent['configuration'],true)['zero_base_until']??0));
        $period=(int)query('SELECT COALESCE(MAX(c.paid_until),0) FROM shop_contracts c JOIN shop_module_intents i ON i.order_id=c.order_id WHERE i.parent_order_id=?',[$parent['id']])->fetchColumn();$until=max($until,$period);
        while($until<=shop_now())$until=add_contract_months($until?:$start,billing_months($parent['billing']));
        query("UPDATE shop_contracts SET state=CASE WHEN state='pending' THEN 'active' ELSE state END,started_at=?,paid_until=?,next_due_at=0,last_error=NULL,next_check_at=? WHERE order_id=?",[$start,$until,shop_now()+300,$parent['id']]);
        if(!one('SELECT id FROM shop_jobs WHERE order_id=?',[$parent['id']]) && !service_for_order((int)$parent['id'],(int)$parent['customer_id'])){query("INSERT INTO shop_jobs (order_id,state,payload) VALUES (?,'queued','{}')",[$parent['id']]);query("UPDATE shop_orders SET fulfillment_status='queued' WHERE id=?",[$parent['id']]);}
        db()->commit();pricing_confirm_redemption($parent);
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function sync_zero_base_for_module(array $order): void {
    $i=module_order_intent((int)$order['id']);if(!$i)return;$parent=one('SELECT * FROM shop_orders WHERE id=? AND customer_id=?',[$i['parent_order_id'],$order['customer_id']]);if($parent && zero_base_order($parent))sync_zero_base_order($parent);
}
