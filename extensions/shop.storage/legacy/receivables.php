<?php
// Settlement status stays outside the original invoice snapshot and PDF.
function allocate_invoice_number(string $series): string {
    try{query('INSERT INTO shop_invoice_counters (series,next_number) VALUES (?,1)',[$series]);}catch(PDOException $e){if(!one('SELECT series FROM shop_invoice_counters WHERE series=?',[$series]))throw $e;}
    $lock=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';$counter=one('SELECT next_number FROM shop_invoice_counters WHERE series=?'.$lock,[$series]);$n=(int)$counter['next_number'];query('UPDATE shop_invoice_counters SET next_number=next_number+1 WHERE series=?',[$series]);return $series.'-'.str_pad((string)$n,6,'0',STR_PAD_LEFT);
}
function create_open_invoice(array $order,string $kind='initial',int $due=0,int $periodEnd=0): array {
    $order=module_order_view($order,$kind);if($kind==='initial' && ($intent=module_order_intent((int)$order['id'])))$periodEnd=(int)$intent['initial_until'];
    $due=$due?:shop_now();$key=$kind==='initial'?'initial':'renewal:'.$due;
    if($row=one('SELECT r.*,i.number FROM shop_receivables r JOIN shop_invoices i ON i.id=r.invoice_id WHERE r.order_id=? AND r.cycle_key=?',[$order['id'],$key]))return $row;
    if(!invoice_seller_ready())throw new RuntimeException('Die Rechnungsstellerdaten sind unvollständig.');
    $configuration=json_decode($order['configuration'],true);$buyer=$configuration['billing']??one('SELECT first_name,last_name,email,company,phone,address,postal_code,city,country FROM customers WHERE id=?',[$order['customer_id']]);$buyer=array_replace($buyer,billing_input($buyer,true));$tax=$configuration['tax']??vat_from_gross((int)$order['amount_cents']);
    if((int)$tax['gross_cents']!==(int)$order['amount_cents'])throw new RuntimeException('Rechnungsbetrag und Bestellung stimmen nicht überein.');
    $snapshot=['seller'=>commerce_settings(),'buyer'=>$buyer,'tax'=>$tax,'product'=>$order['product_name'],'domain'=>$order['domain'],'reference'=>$order['reference'],'billing'=>$order['billing'],'payment_reference'=>'','paid_at'=>null,'period'=>['start'=>$kind==='renewal'?gmdate('Y-m-d',$due):(module_order_intent((int)$order['id'])?gmdate('Y-m-d',(int)($configuration['ordered_at']??shop_now())):null),'end'=>$periodEnd?gmdate('Y-m-d',$periodEnd-1):null],'sandbox'=>$order['paypal_mode']!=='live','issued_before_payment'=>true,'provisioning_notice'=>$configuration['provisioning_notice']??'','dns_notice'=>$configuration['dns_notice']??'','contract_policy'=>$configuration['contract_policy']??null,'modules'=>$configuration['modules']??[],'base_net_cents'=>$configuration['base_net_cents']??null,'purchase_pricing'=>$configuration['purchase_pricing']??null,'pricing_period'=>$configuration['pricing_period']??'initial'];
    db()->beginTransaction();try{
        $lock=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';one('SELECT id FROM shop_orders WHERE id=?'.$lock,[$order['id']]);
        if($row=one('SELECT * FROM shop_receivables WHERE order_id=? AND cycle_key=?',[$order['id'],$key])){db()->commit();return $row;}
        $series=($order['paypal_mode']!=='live'?'TEST-':'').commerce_settings()['invoice_prefix'].'-'.date('Y',shop_now());$number=allocate_invoice_number($series);
        query('INSERT INTO shop_invoices (order_id,customer_id,number,snapshot) VALUES (?,?,?,?)',[$order['id'],$order['customer_id'],$number,json_encode($snapshot,JSON_THROW_ON_ERROR)]);$invoice=(int)db()->lastInsertId();
        query('INSERT INTO shop_receivables (invoice_id,order_id,cycle_key,kind,amount_cents,issued_ts,due_ts,period_end) VALUES (?,?,?,?,?,?,?,?)',[$invoice,$order['id'],$key,$kind,$order['amount_cents'],shop_now(),$due,$periodEnd]);db()->commit();return one('SELECT * FROM shop_receivables WHERE invoice_id=?',[$invoice]);
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function initial_order_invoice(array $order): ?array {
    if(!isset(json_decode($order['configuration'],true)['contract_policy']))return null;
    ensure_order_contract($order);return create_open_invoice($order);
}
function settle_open_invoice(array $order,int $payment,int $paidAt,?int $invoiceId=null): ?array {
    $r=$invoiceId?one('SELECT * FROM shop_receivables WHERE invoice_id=? AND order_id=?',[$invoiceId,$order['id']]):one("SELECT * FROM shop_receivables WHERE order_id=? AND state='open' ORDER BY due_ts,invoice_id LIMIT 1",[$order['id']]);
    if(!$r || $r['state']!=='open')return null;
    if((int)$r['amount_cents']!==(int)$order['amount_cents'] || $r['currency']!==$order['currency'])throw new RuntimeException('Offene Rechnung passt nicht zur bestätigten Zahlung.');
    $changed=query("UPDATE shop_receivables SET state='paid',payment_id=?,settled_ts=? WHERE invoice_id=? AND state='open'",[$payment,$paidAt,$r['invoice_id']]);if($changed->rowCount()!==1)throw new RuntimeException('Die Rechnung wird bereits verarbeitet.');
    query('UPDATE shop_invoices SET payment_id=? WHERE id=? AND payment_id IS NULL',[$payment,$r['invoice_id']]);
    query('UPDATE shop_notifications SET is_read=1 WHERE customer_id=? AND event_key IN (?,?)',[$order['customer_id'],'invoice:'.$r['invoice_id'].':open','invoice:'.$r['invoice_id'].':reminder3']);
    return $r;
}
function void_open_invoice(int $invoiceId,string $reason): ?array {
    $r=one("SELECT r.*,i.snapshot,i.number FROM shop_receivables r JOIN shop_invoices i ON i.id=r.invoice_id WHERE r.invoice_id=? AND r.state='open'",[$invoiceId]);if(!$r)return null;
    $snapshot=json_decode($r['snapshot'],true);$snapshot['original_number']=$r['number'];$snapshot['cancellation_reason']=$reason;$snapshot['credit_note']=true;
    foreach(['net_cents','tax_cents','gross_cents'] as $key)$snapshot['tax'][$key]=-(int)$snapshot['tax'][$key];
    $series=($snapshot['sandbox']?'TEST-':'').$snapshot['seller']['invoice_prefix'].'-'.date('Y',shop_now());$number=allocate_invoice_number($series);
    query("INSERT INTO shop_invoices (order_id,customer_id,number,kind,original_id,snapshot) SELECT order_id,customer_id,?,'cancellation',id,? FROM shop_invoices WHERE id=?",[$number,json_encode($snapshot,JSON_THROW_ON_ERROR),$invoiceId]);$credit=(int)db()->lastInsertId();
    $changed=query("UPDATE shop_receivables SET state='void',cancellation_invoice_id=? WHERE invoice_id=? AND state='open'",[$credit,$invoiceId]);if($changed->rowCount()!==1)throw new RuntimeException('Der Rechnungsstatus wurde inzwischen geändert.');
    query('UPDATE shop_notifications SET is_read=1 WHERE event_key IN (?,?)',['invoice:'.$invoiceId.':open','invoice:'.$invoiceId.':reminder3']);
    return one('SELECT * FROM shop_invoices WHERE id=?',[$credit]);
}
function customer_open_invoices(int $customer): array {
    return all("SELECT r.*,i.number,o.reference,o.product_name,o.approval_url,o.payment_status FROM shop_receivables r JOIN shop_invoices i ON i.id=r.invoice_id JOIN shop_orders o ON o.id=r.order_id WHERE i.customer_id=? AND r.state='open' AND o.paypal_mode='live' ORDER BY r.issued_ts",[$customer]);
}
function invoice_dashboard_alerts(int $customer): void {
    $rows=customer_open_invoices($customer);if(!$rows)return;
    echo '<section class="panel invoice-alerts" aria-labelledby="open-invoices-title"><div class="panel-title"><div><h2 id="open-invoices-title">Offene Rechnungen</h2><p>'.count($rows).' Rechnung(en) warten auf Zahlung.</p></div><a class="button button-outline button-small" href="/konto/rechnungen">Rechnungen öffnen '.icon('arrow').'</a></div>';
    foreach(array_slice($rows,0,5) as $r){$age=max(0,shop_now()-(int)$r['issued_ts']);echo '<div class="notice '.($age>=3*86400?'notice-error':'notice-info').'"><span><strong>'.e($r['number']).' · '.money($r['amount_cents']).'</strong><br>'.e($r['product_name']).'<br>'.($age>=3*86400?'Zahlungserinnerung: Diese Rechnung ist seit mindestens drei Tagen offen. ':'Die Zahlung ist noch offen. ').($r['kind']==='initial'?'Ohne Zahlung wird die Neubestellung nach sieben Tagen und erneuter PayPal-Prüfung storniert.':'Bitte prüfe deine PayPal-Zahlung. Dein laufender Vertrag wird durch diese Erinnerung nicht automatisch beendet.').'</span></div>';}
    echo '</section>';
}
function invoice_payment_link(array $r,array $order): ?string {
    if($r['state']!=='open' || in_array($order['payment_status'],['cancelled','expired'],true))return null;
    if($r['kind']==='initial' && $order['approval_url'])return checked_paypal_approval($order['approval_url']);
    return null;
}
function start_invoice_checkout(int $invoice,int $customer): string {
    $r=one("SELECT r.* FROM shop_receivables r JOIN shop_invoices i ON i.id=r.invoice_id WHERE r.invoice_id=? AND i.customer_id=? AND r.state='open'",[$invoice,$customer]);if(!$r)throw new RuntimeException('Diese Rechnung ist nicht offen oder gehört nicht zu deinem Konto.');
    $order=one('SELECT * FROM shop_orders WHERE id=?',[$r['order_id']]);reconcile_paypal_order($order);$r=one('SELECT * FROM shop_receivables WHERE invoice_id=?',[$invoice]);
    if($r['state']!=='open')throw new RuntimeException('PayPal hat diese Rechnung bereits als bezahlt bestätigt.');
    if(contract_closed(one('SELECT * FROM shop_contracts WHERE order_id=?',[$order['id']])) && $r['kind']==='initial')throw new RuntimeException('Diese Neubestellung ist bereits storniert.');
    if($url=invoice_payment_link($r,$order))return $url;
    $existing=one('SELECT * FROM shop_invoice_paypal WHERE invoice_id=?',[$invoice]);if($existing && $existing['approval_url'])return checked_paypal_approval($existing['approval_url']);
    $paypal=checked_order_paypal($order);
    if(!$existing)query('INSERT INTO shop_invoice_paypal (invoice_id,paypal_mode) VALUES (?,?)',[$invoice,$order['paypal_mode']]);
    $reference='OC-I-'.$invoice;$return=Config::FRONTPANEL_SITE_URL.'/paypal/rechnung?rechnung='.$invoice;$cancel=Config::FRONTPANEL_SITE_URL.'/konto/rechnungen';
    $reply=$paypal->call('POST','/v2/checkout/orders',['intent'=>'CAPTURE','purchase_units'=>[['reference_id'=>$reference,'custom_id'=>$reference,'description'=>$order['product_name'].' – offene Rechnung','amount'=>['currency_code'=>$r['currency'],'value'=>number_format($r['amount_cents']/100,2,'.','')]]],'payment_source'=>['paypal'=>['experience_context'=>['brand_name'=>'Server Management','locale'=>'de-DE','shipping_preference'=>'NO_SHIPPING','user_action'=>'PAY_NOW','return_url'=>$return,'cancel_url'=>$cancel]]]],'invoice-create-'.$invoice);
    $approval=null;foreach($reply['links']??[] as $link)if(in_array($link['rel']??'',['approve','payer-action'],true))$approval=$link['href'];if(empty($reply['id']) || !$approval)throw new ProviderFailure('paypal');$approval=checked_paypal_approval($approval);
    query("UPDATE shop_invoice_paypal SET payment_id=?,approval_url=?,state='created' WHERE invoice_id=?",[$reply['id'],$approval,$invoice]);return $approval;
}
function accept_invoice_checkout(array $order,array $r,array $mapping,array $reply,PaypalProvider $paypal): void {
    $check=$order;$check['amount_cents']=(int)$r['amount_cents'];$check['currency']=$r['currency'];$check['payment_id']=$mapping['payment_id'];$check['reference']='OC-I-'.$r['invoice_id'];validate_paypal_order_identity($check,$reply);
    if(($reply['status']??'')!=='COMPLETED')return;
    $captures=$reply['purchase_units'][0]['payments']['captures']??[];
    if(count($captures)!==1 || ($captures[0]['status']??'')!=='COMPLETED')throw new RuntimeException('PayPal hat den Rechnungsbetrag noch nicht vollständig bestätigt.');
    $capture=$captures[0];$amount=$capture['amount']??[];
    record_paid($order,(string)$capture['id'],payment_amount_cents((string)($amount['value']??'')),(string)($amount['currency_code']??''),$order['next_billing_at'],['invoice_id'=>(int)$r['invoice_id'],'paid_at'=>isset($capture['create_time'])?(strtotime($capture['create_time'])?:shop_now()):shop_now()]);
    query("UPDATE shop_invoice_paypal SET state='needs_sync' WHERE invoice_id=? AND state<>'complete'",[$r['invoice_id']]);
    synchronize_invoice_subscription($order,(int)$r['invoice_id'],$paypal);
}
function confirm_invoice_checkout(int $invoice,int $customer,string $token): void {
    $r=one('SELECT r.* FROM shop_receivables r JOIN shop_invoices i ON i.id=r.invoice_id WHERE r.invoice_id=? AND i.customer_id=?',[$invoice,$customer]);$mapping=$r?one('SELECT * FROM shop_invoice_paypal WHERE invoice_id=?',[$invoice]):null;
    if(!$r || !$mapping || !hash_equals($mapping['payment_id']??'',$token))throw new RuntimeException('Diese PayPal-Zahlung gehört nicht zu deiner Rechnung.');
    $order=one('SELECT * FROM shop_orders WHERE id=?',[$r['order_id']]);$paypal=checked_order_paypal($order);$reply=$paypal->call('GET','/v2/checkout/orders/'.rawurlencode($mapping['payment_id']));
    $check=$order;$check['amount_cents']=(int)$r['amount_cents'];$check['currency']=$r['currency'];$check['payment_id']=$mapping['payment_id'];$check['reference']='OC-I-'.$invoice;validate_paypal_order_identity($check,$reply);
    if(($reply['status']??'')==='APPROVED'){
        reconcile_paypal_order($order);$r=one('SELECT * FROM shop_receivables WHERE invoice_id=?',[$invoice]);
        if($r['state']==='paid')return;if($r['state']!=='open')throw new RuntimeException('Diese Rechnung wurde inzwischen storniert. Es wird keine Zahlung eingezogen.');
        $reply=$paypal->call('POST','/v2/checkout/orders/'.rawurlencode($mapping['payment_id']).'/capture',[],'invoice-capture-'.$invoice);
    }
    accept_invoice_checkout($order,$r,$mapping,$reply,$paypal);
    if(($reply['status']??'')!=='COMPLETED')throw new RuntimeException('PayPal hat diese Zahlung noch nicht bestätigt.');
}
function synchronize_invoice_subscription(array $order,int $invoice,PaypalProvider $paypal): void {
    $mapping=one('SELECT * FROM shop_invoice_paypal WHERE invoice_id=?',[$invoice]);if($mapping['state']==='complete')return;if($order['payment_kind']!=='subscription'){query("UPDATE shop_invoice_paypal SET state='complete' WHERE invoice_id=?",[$invoice]);return;}
    try{
        $s=checked_subscription($order,$paypal);$balance=$s['billing_info']['outstanding_balance']??['value'=>'0.00','currency_code'=>'EUR'];if(($balance['currency_code']??'')!=='EUR')throw new RuntimeException('PayPal-Saldo besitzt eine unerwartete Währung.');$current=payment_amount_cents((string)$balance['value']);
        if($mapping['balance_before']===null){
            if($current===0){query("UPDATE shop_invoice_paypal SET balance_before=0,balance_after=0 WHERE invoice_id=?",[$invoice]);$before=0;$after=0;}
            else{$r=one('SELECT * FROM shop_receivables WHERE invoice_id=?',[$invoice]);if($current<(int)$r['amount_cents'])throw new RuntimeException('Der PayPal-Restbetrag muss nach der Rechnungskorrektur geprüft werden.');$before=$current;$after=$current-(int)$r['amount_cents'];query('UPDATE shop_invoice_paypal SET balance_before=?,balance_after=? WHERE invoice_id=?',[$before,$after,$invoice]);}
        }else{$before=(int)$mapping['balance_before'];$after=(int)$mapping['balance_after'];}
        if($current!==$after){
            if($current!==$before || !in_array($s['status']??'',['ACTIVE','SUSPENDED'],true))throw new RuntimeException('Der PayPal-Saldo hat sich geändert. Bitte die bezahlte Rechnung im Adminbereich abgleichen.');
            $paypal->call('PATCH','/v1/billing/subscriptions/'.rawurlencode($order['payment_id']),[['op'=>'replace','path'=>'/billing_info/outstanding_balance','value'=>['currency_code'=>'EUR','value'=>number_format($after/100,2,'.','')]]]);
            $s=checked_subscription($order,$paypal);if(payment_amount_cents((string)($s['billing_info']['outstanding_balance']['value']??'0.00'))!==$after)throw new RuntimeException('PayPal hat den korrigierten offenen Saldo noch nicht bestätigt.');
        }
        $c=one('SELECT * FROM shop_contracts WHERE order_id=?',[$order['id']]);
        if(($s['status']??'')==='SUSPENDED' && $after===0 && !contract_closed($c) && (!$c['cancel_at'] || (int)$c['paid_until']<(int)$c['cancel_at']) && !one("SELECT invoice_id FROM shop_receivables WHERE order_id=? AND state='open'",[$order['id']]))$paypal->call('POST','/v1/billing/subscriptions/'.rawurlencode($order['payment_id']).'/activate',['reason'=>'Offene Rechnung im Kundenbereich bezahlt.'],'invoice-resume-'.$invoice);
        query("UPDATE shop_invoice_paypal SET state='complete' WHERE invoice_id=?",[$invoice]);
    }catch(Throwable $e){query('UPDATE shop_contracts SET last_error=?,next_check_at=0 WHERE order_id=?',[$e instanceof ProviderFailure?$e->getMessage():'Die Rechnung ist bezahlt; der PayPal-Abgleich des Abo-Saldos muss noch bestätigt werden.',$order['id']]);}
}
function reconcile_invoice_checkouts(array $order): void {
    foreach(all("SELECT * FROM shop_invoice_paypal WHERE invoice_id IN (SELECT invoice_id FROM shop_receivables WHERE order_id=?) AND payment_id IS NOT NULL AND state<>'complete'",[$order['id']]) as $mapping){
        $r=one('SELECT * FROM shop_receivables WHERE invoice_id=?',[$mapping['invoice_id']]);$paypal=checked_order_paypal($order);
        if($mapping['state']==='needs_sync'){synchronize_invoice_subscription($order,(int)$r['invoice_id'],$paypal);continue;}
        $reply=$paypal->call('GET','/v2/checkout/orders/'.rawurlencode($mapping['payment_id']));
        if(($reply['status']??'')==='APPROVED' && $r['state']==='open')confirm_invoice_checkout((int)$r['invoice_id'],(int)$order['customer_id'],$mapping['payment_id']);
        elseif(($reply['status']??'')==='COMPLETED')accept_invoice_checkout($order,$r,$mapping,$reply,$paypal);
    }
}
