<?php
function checked_order_paypal(array $order): PaypalProvider {
    $config=integration_config('paypal');$snapshot=json_decode($order['configuration'],true);
    if(($config['mode']??'sandbox')!==$order['paypal_mode'] || (isset($snapshot['paypal_connection_id']) && !hash_equals($snapshot['paypal_connection_id'],integration_identity('paypal'))))throw new RuntimeException('Die PayPal-Anbindung dieser Bestellung wurde geändert. Bitte im Adminbereich prüfen.');
    return new PaypalProvider($config);
}
function checked_subscription(array $order,PaypalProvider $paypal): array {
    $s=$paypal->call('GET','/v1/billing/subscriptions/'.rawurlencode($order['payment_id']));
    if(($s['id']??'')!==$order['payment_id'] || ($s['custom_id']??'')!==$order['reference'] || ($s['plan_id']??'')!==$order['plan_id'])throw new RuntimeException('PayPal-Abonnement konnte nicht eindeutig zugeordnet werden.');return $s;
}
function paypal_subscription_transactions(array $order,PaypalProvider $paypal,int $start,int $end): array {
    $query=http_build_query(['start_time'=>gmdate('Y-m-d\TH:i:s\Z',$start),'end_time'=>gmdate('Y-m-d\TH:i:s\Z',$end)],'','&',PHP_QUERY_RFC3986);
    $result=$paypal->call('GET','/v1/billing/subscriptions/'.rawurlencode($order['payment_id']).'/transactions?'.$query);
    if(!isset($result['transactions']) || !is_array($result['transactions']) || !array_is_list($result['transactions']) || (int)($result['total_pages']??1)>1 || (isset($result['total_items']) && (int)$result['total_items']!==count($result['transactions'])))throw new RuntimeException('Die PayPal-Zahlungsliste ist unvollständig. Vor einer Stornierung muss der Zahlungsstatus manuell geprüft werden.');
    $rows=$result['transactions'];usort($rows,fn($a,$b)=>strcmp((string)($a['time']??''),(string)($b['time']??'')));return $rows;
}
function prepare_renewal_invoice(array $order,int $at): ?array {
    $c=one('SELECT * FROM shop_contracts WHERE order_id=?',[$order['id']]);if(!$c || contract_closed($c) || !(int)$c['started_at'] || !(int)$c['paid_until'] || ($order['payment_kind']!=='subscription' && empty(pricing_snapshot($order)['invoice_renewal'])))return null;
    $due=(int)$c['paid_until'];if($at<$due-60 || ((int)$c['cancel_at'] && (int)$c['cancel_at']<=$due))return null;
    if($existing=one("SELECT * FROM shop_receivables WHERE order_id=? AND state='open' ORDER BY due_ts LIMIT 1",[$order['id']]))return $existing;
    $r=create_open_invoice($order,'renewal',$due,add_contract_months($due,billing_months($order['billing'])));commerce_event('invoice-open:'.$r['invoice_id'],'invoice.open',['order_id'=>(int)$order['id'],'invoice_id'=>(int)$r['invoice_id']]);return $r;
}
function accept_subscription_transaction(array $order,array $transaction,?string $nextBilling): void {
    if(($transaction['status']??'')!=='COMPLETED')return;
    $config=json_decode($order['configuration'],true);$id=$transaction['id']??null;$date=$transaction['time']??null;$amount=$transaction['amount_with_breakdown']['gross_amount']??[];
    if(!is_string($id) || !$id || !is_string($date) || !($at=strtotime($date)) || $at>shop_now()+300 || !is_string($amount['value']??null) || !is_string($amount['currency_code']??null))throw new RuntimeException('Die PayPal-Transaktion enthält keine vollständige Zahlungsbestätigung.');
    if(isset($config['ordered_at']) && $at<(int)$config['ordered_at']-300)throw new RuntimeException('Die PayPal-Transaktion liegt vor dieser Bestellung.');
    if(one('SELECT id FROM shop_payments WHERE paypal_mode=? AND transaction_id=? AND order_id=?',[$order['paypal_mode'],$id,$order['id']]))return;
    prepare_renewal_invoice($order,$at);record_paid($order,$id,payment_amount_cents($amount['value']),$amount['currency_code'],$nextBilling,['paid_at'=>$at]);
}
function verify_subscription_sale(array $order,PaypalProvider $paypal,string $id,?string $date=null): void {
    $s=checked_subscription($order,$paypal);$at=$date?strtotime($date):shop_now();if(!$at || $at>shop_now()+300)$at=shop_now();
    if(($pricing=pricing_snapshot($order)) && (int)$pricing['initial_net']===0 && !one('SELECT order_id FROM shop_free_entitlements WHERE order_id=?',[$order['id']]) && ($s['status']??'')==='ACTIVE'){pricing_accept_free($order,$s);$order=one('SELECT * FROM shop_orders WHERE id=?',[$order['id']]);}
    $rows=paypal_subscription_transactions($order,$paypal,max(0,$at-2*86400),min(shop_now(),$at+86400));
    foreach($rows as $row)if(($row['id']??'')===$id){if(($row['status']??'')!=='COMPLETED')throw new RuntimeException('PayPal hat diese Abo-Zahlung noch nicht als abgeschlossen bestätigt.');accept_subscription_transaction($order,$row,$s['billing_info']['next_billing_time']??null);return;}
    throw new RuntimeException('Die gemeldete Abo-Zahlung wurde in der PayPal-API noch nicht bestätigt.');
}
function validate_paypal_order_identity(array $order,array $reply): void {
    if(($reply['id']??'')!==$order['payment_id'] || count($reply['purchase_units']??[])!==1)throw new RuntimeException('Die PayPal-Zahlung passt nicht zur Bestellung.');$unit=$reply['purchase_units'][0];
    if(($unit['custom_id']??'')!==$order['reference'] || payment_amount_cents((string)($unit['amount']['value']??''))!==(int)$order['amount_cents'] || ($unit['amount']['currency_code']??'')!==$order['currency'])throw new RuntimeException('PayPal-Referenz, Betrag oder Währung stimmen nicht mit der Bestellung überein.');
}
function reconcile_paypal_order(array $order): void {
    if(zero_base_order($order)){sync_zero_base_order($order);query('UPDATE shop_contracts SET next_check_at=? WHERE order_id=?',[shop_now()+300,$order['id']]);return;}
    if($order['payment_kind']==='no_charge' && pricing_snapshot($order)){pricing_renew_free($order);query('UPDATE shop_contracts SET next_check_at=?,last_error=NULL WHERE order_id=?',[shop_now()+300,$order['id']]);return;}
    $c=ensure_order_contract($order);$paypal=checked_order_paypal($order);$now=shop_now();$completeUntil=$now;$processing=false;
    if($c)query('UPDATE shop_contracts SET last_check_at=? WHERE order_id=?',[$now,$order['id']]);
    try{
        if(!$order['payment_id'])throw new RuntimeException('Die Bestellung besitzt noch keine PayPal-Zahlungsreferenz.');
        if($order['payment_kind']==='subscription'){
            $s=checked_subscription($order,$paypal);if(($ps=pricing_snapshot($order)) && (int)$ps['initial_net']===0 && !one('SELECT order_id FROM shop_free_entitlements WHERE order_id=?',[$order['id']]) && ($s['status']??'')==='ACTIVE'){pricing_accept_free($order,$s);$order=one('SELECT * FROM shop_orders WHERE id=?',[$order['id']]);$c=ensure_order_contract($order);}$config=json_decode($order['configuration'],true);$created=(int)($config['ordered_at']??(strtotime($order['created_at'].' UTC')?:$now));
            $start=max($created-300,($c?(int)$c['checked_at']:0)-2*86400);$end=min($now,$start+31*86400);$completeUntil=$end;
            $rows=paypal_subscription_transactions($order,$paypal,max(0,$start),$end);
            foreach($rows as $row){if(($row['status']??'')==='PENDING')$processing=true;accept_subscription_transaction($order,$row,$s['billing_info']['next_billing_time']??null);}
            $last=$s['billing_info']['last_payment']??null;
            if($last && $end>=$now-120 && !one("SELECT id FROM shop_payments WHERE order_id=? AND status='completed'",[$order['id']]))throw new RuntimeException('PayPal meldet einen Zahlungseingang, die zugehörige Transaktion ist noch nicht verfügbar. Die Bestellung bleibt zur Prüfung offen.');
            query('UPDATE shop_orders SET renewal_status=?,next_billing_at=? WHERE id=?',[strtolower($s['status']??''),$s['billing_info']['next_billing_time']??null,$order['id']]);
        }else{
            $reply=$paypal->call('GET','/v2/checkout/orders/'.rawurlencode($order['payment_id']));validate_paypal_order_identity($order,$reply);
            if(($reply['status']??'')==='APPROVED' && !test_order($order) && ($order['payment_status']==='pending' || one("SELECT invoice_id FROM shop_receivables WHERE order_id=? AND state='open'",[$order['id']])) && !contract_closed($c) && !in_array($order['payment_status'],['cancelled','expired'],true))$reply=$paypal->call('POST','/v2/checkout/orders/'.rawurlencode($order['payment_id']).'/capture',[],'capture-'.$order['reference']);
            if(($reply['status']??'')==='COMPLETED')accept_capture($order,$reply);
            else foreach($reply['purchase_units'][0]['payments']['captures']??[] as $capture)if(($capture['status']??'')==='PENDING')$processing=true;
        }
        if($c)query('UPDATE shop_contracts SET checked_at=?,next_check_at=?,last_error=? WHERE order_id=?',[$completeUntil,$completeUntil<$now-120?$now:$now+300,$processing?'Eine PayPal-Zahlung wird noch verarbeitet. Die Bestellung bleibt bis zur endgültigen Bestätigung offen.':null,$order['id']]);
    }catch(Throwable $e){if($c)query('UPDATE shop_contracts SET next_check_at=?,last_error=? WHERE order_id=?',[$now+300,$e instanceof ProviderFailure?$e->getMessage():'Der PayPal-Zahlungsstatus konnte nicht vollständig bestätigt werden. Vor einer Stornierung ist eine erneute Prüfung erforderlich.',$order['id']]);throw $e;}
}
function cancel_unpaid_initial(array $order,array $r): bool {
    $now=shop_now();db()->beginTransaction();
    try{
        $lock=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';$fresh=one('SELECT * FROM shop_orders WHERE id=?'.$lock,[$order['id']]);$c=one('SELECT * FROM shop_contracts WHERE order_id=?',[$order['id']]);$r=one('SELECT * FROM shop_receivables WHERE invoice_id=?',[$r['invoice_id']]);
        if(!$c || !$r || admin_order_released($fresh) || !in_array($fresh['payment_status'],['pending','review'],true) || $fresh['fulfillment_status']!=='not_paid' || service_for_order((int)$order['id'],(int)$order['customer_id']) || $r['kind']!=='initial' || $r['state']!=='open' || $now-(int)$r['issued_ts']<7*86400 || (int)$c['checked_at']<$now-120 || $c['last_error'] || (int)$c['started_at'] || contract_closed($c) || one("SELECT id FROM shop_payments WHERE order_id=? AND status='completed'",[$order['id']])){db()->commit();return false;}
        $credit=void_open_invoice((int)$r['invoice_id'],'Unbezahlte Neubestellung nach sieben Tagen und erneuter PayPal-Prüfung storniert.');
        query("UPDATE shop_contracts SET state='unpaid_cancelled',cancel_at=?,paypal_cancel_state='pending',end_state='done',next_check_at=0 WHERE order_id=?",[$now,$order['id']]);
        query("UPDATE shop_orders SET payment_status='cancelled',fulfillment_status='cancelled',updated_at=CURRENT_TIMESTAMP WHERE id=?",[$order['id']]);query("UPDATE shop_jobs SET state='cancelled',last_error='Unbezahlte Neubestellung storniert.' WHERE order_id=? AND state NOT IN ('done','sandbox')",[$order['id']]);
        commerce_event('unpaid-cancel:'.$order['id'],'order.unpaid_cancelled',['order_id'=>(int)$order['id'],'invoice_id'=>(int)$credit['id']]);db()->commit();audit_event('order.unpaid_cancelled',$order['reference']);return true;
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function run_contract_worker(int $limit=5): int {
    $now=shop_now();$count=0;
    foreach(all('SELECT order_id FROM shop_contracts WHERE next_check_at<=? AND locked_until<? ORDER BY next_check_at,order_id LIMIT '.max(1,min(20,$limit)),[$now,$now]) as $candidate){
        $id=(int)$candidate['order_id'];$token=bin2hex(random_bytes(16));$claim=query('UPDATE shop_contracts SET lock_token=?,locked_until=? WHERE order_id=? AND locked_until<?',[$token,$now+600,$id,$now]);if($claim->rowCount()!==1)continue;
        try{
            $order=one('SELECT * FROM shop_orders WHERE id=?',[$id]);if($order['payment_status']==='paid' && pricing_snapshot($order))pricing_confirm_redemption($order);$c=one('SELECT * FROM shop_contracts WHERE order_id=?',[$id]);
            if(contract_closed($c) && admin_provider_manually_ended($c)){query('UPDATE shop_contracts SET next_check_at=2147483647 WHERE order_id=?',[$id]);$count++;continue;}
            foreach(all("SELECT invoice_id,issued_ts FROM shop_receivables WHERE order_id=? AND state='open'",[$id]) as $reminder)if(shop_now()-(int)$reminder['issued_ts']>=3*86400)commerce_event('invoice-reminder3:'.$reminder['invoice_id'],'invoice.reminder',['order_id'=>$id,'invoice_id'=>(int)$reminder['invoice_id']]);
            process_contract_end($id);$c=one('SELECT * FROM shop_contracts WHERE order_id=?',[$id]);
            if(test_order($order)){query('UPDATE shop_contracts SET next_check_at=? WHERE order_id=?',[shop_now()+300,$id]);$count++;continue;}
            if(!contract_closed($c)){reconcile_paypal_order($order);$order=one('SELECT * FROM shop_orders WHERE id=?',[$id]);prepare_renewal_invoice($order,shop_now());}
            foreach(all("SELECT * FROM shop_receivables WHERE order_id=? AND state='open'",[$id]) as $r){
                if(shop_now()-(int)$r['issued_ts']>=3*86400)commerce_event('invoice-reminder3:'.$r['invoice_id'],'invoice.reminder',['order_id'=>$id,'invoice_id'=>(int)$r['invoice_id']]);
                if($r['kind']==='initial' && shop_now()-(int)$r['issued_ts']>=7*86400)cancel_unpaid_initial($order,$r);
            }
            pricing_release_cancelled(one('SELECT * FROM shop_orders WHERE id=?',[$id]));
            reconcile_invoice_checkouts($order);
            process_contract_end($id);$count++;
        }catch(Throwable $e){query('UPDATE shop_contracts SET next_check_at=?,last_error=? WHERE order_id=?',[$now+300,$e instanceof ProviderFailure?$e->getMessage():($e instanceof RuntimeException && !($e instanceof PDOException)?substr($e->getMessage(),0,500):'Die Vertragsverarbeitung muss im Adminbereich geprüft werden.'),$id]);}
        finally{query('UPDATE shop_contracts SET locked_until=0,lock_token=NULL WHERE order_id=? AND lock_token=?',[$id,$token]);}
    }
    return $count;
}
