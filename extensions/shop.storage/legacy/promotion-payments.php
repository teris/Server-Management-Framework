<?php
/** A zero invoice is an entitlement, never a fabricated payment. */
function pricing_discount_authorized(array $order): bool {
    $s=pricing_snapshot($order);if(!$s || (int)$s['customer_id']!==(int)$order['customer_id'])return false;
    if(!empty($s['coupon'])){$parent=(int)($s['redemption_parent']??$order['id']);$u=one("SELECT * FROM shop_coupon_uses WHERE order_id=? AND customer_id=? AND state<>'released'",[$parent,$order['customer_id']]);if(!$u || (int)$u['coupon_id']!==(int)$s['coupon']['id'] || $u['snapshot']!==json_encode($s['coupon']))return false;if($parent!==(int)$order['id']){$i=module_order_intent((int)$order['id']);if(!$i || (int)$i['parent_order_id']!==$parent || (json_decode($i['snapshot'],true)['purchase_pricing']['coupon']??null)!==$s['coupon'])return false;}return true;}
    if(!empty($s['reward_id'])){$parent=(int)($s['redemption_parent']??$order['id']);if($parent!==(int)$order['id']){$i=module_order_intent((int)$order['id']);if(!$i || (int)$i['parent_order_id']!==$parent || (int)(json_decode($i['snapshot'],true)['purchase_pricing']['reward_id']??0)!==(int)$s['reward_id'])return false;}$r=one("SELECT * FROM shop_coupon_rewards WHERE id=? AND order_id=? AND customer_id=? AND state IN ('reserved','used')",[$s['reward_id'],$parent,$order['customer_id']]);if(!$r)return false;if($r['state']==='reserved'){$source=one('SELECT * FROM shop_orders WHERE id=? AND customer_id=?',[$r['source_order_id'],$order['customer_id']]);if(!$source || !promotion_paid_source($source))return false;}return true;}return false;
}
function pricing_free_entitled(array $order): bool {
    $s=pricing_snapshot($order);if(!$s || !pricing_discount_authorized($order) || $order['payment_status']!=='no_charge' || $order['paypal_mode']!=='live' || ($s['test_only'] && $order['provider']==='ovh'))return false;
    $e=one('SELECT * FROM shop_free_entitlements WHERE order_id=? AND customer_id=?',[$order['id'],$order['customer_id']]);
    return $e && hash_equals($e['snapshot_hash'],hash('sha256',json_encode($s))) && (!$e['until_ts'] || (int)$e['until_ts']>shop_now()) && ($order['payment_kind']!=='subscription' || hash_equals($e['subscription_id'],(string)$order['payment_id']));
}
function pricing_accept_free(array $order,?array $subscription=null): array {
    $s=pricing_snapshot($order);if(!$s || (int)$s['initial_net']!==0 || !pricing_discount_authorized($order))throw new RuntimeException('Für diese kostenlose Bestellung fehlt ein gültiger Gutscheinbeleg.');
    if($order['payment_kind']==='subscription' && (!$subscription || ($subscription['status']??'')!=='ACTIVE' || ($subscription['id']??'')!==$order['payment_id'] || ($subscription['plan_id']??'')!==$order['plan_id'] || ($subscription['custom_id']??'')!==$order['reference']))throw new RuntimeException('Bitte zuerst das PayPal-Abo für die späteren Zahlungen freigeben.');
    $r=initial_order_invoice($order);$now=shop_now();$start=$now;$i=module_order_intent((int)$order['id']);$config=json_decode($order['configuration'],true);
    if($subscription && !$i && !empty($subscription['start_time'])){$apiStart=strtotime($subscription['start_time']);if(!$apiStart || $apiStart<(int)$config['ordered_at']-300 || $apiStart>$now+300)throw new RuntimeException('Der Beginn des kostenlosen PayPal-Zeitraums muss geprüft werden.');$start=$apiStart;}
    $months=billing_months($order['billing']);$until=$months?add_contract_months($start,$months):0;if($i){$until=(int)$i['initial_until'];$start=(int)($config['ordered_at']??$now);}
    $next=$subscription['billing_info']['next_billing_time']??null;if($next && !$i && ($api=strtotime($next)) && abs($api-$until)<86400)$until=$api;
    db()->beginTransaction();try{
        $lock=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';$fresh=one('SELECT * FROM shop_orders WHERE id=?'.$lock,[$order['id']]);$contract=ensure_order_contract($fresh);if(contract_closed($contract) || in_array($fresh['payment_status'],['cancelled','expired','review'],true) || in_array($fresh['fulfillment_status'],['cancelled','ended'],true))throw new RuntimeException('Diese Bestellung kann nicht mehr freigegeben werden.');
        if($e=one('SELECT * FROM shop_free_entitlements WHERE order_id=?',[$order['id']])){db()->commit();return $fresh;}
        if(!$r || (int)$r['amount_cents']!==0 || $r['state']!=='open')throw new RuntimeException('Die kostenlose Erstperiode passt nicht zur Rechnung.');
        query('INSERT INTO shop_free_entitlements (order_id,customer_id,snapshot_hash,reason,subscription_id,granted_ts,until_ts) VALUES (?,?,?,?,?,?,?)',[$order['id'],$order['customer_id'],hash('sha256',json_encode($s)),$s['test_only']?'personal_test':($s['reward_id']?'bundle_reward':'coupon'),$order['payment_id']??'',$now,$until]);
        query("UPDATE shop_receivables SET state='paid',settled_ts=?,period_end=? WHERE invoice_id=? AND state='open' AND amount_cents=0",[$now,$until,$r['invoice_id']]);module_note_payment($order,['kind'=>'initial']);
        $state=$s['test_only'] && $order['provider']==='ovh'?'needs_action':($order['paypal_mode']==='live'?($until && $until<=$now && $s['regular_net']>0?'not_paid':'queued'):'sandbox');
        query("UPDATE shop_orders SET payment_status='no_charge',fulfillment_status=?,renewal_status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?",[$state,$order['payment_kind']==='subscription'?'active':($s['regular_net']===0?'free':'invoice'),$order['id']]);
        if($contract)query("UPDATE shop_contracts SET state=CASE WHEN state='pending' THEN 'active' ELSE state END,started_at=?,paid_until=?,next_due_at=?,next_check_at=? WHERE order_id=?",[$start,$until,$until,$now+300,$order['id']]);
        if($state!=='not_paid' && !one('SELECT id FROM shop_jobs WHERE order_id=?',[$order['id']]))query("INSERT INTO shop_jobs (order_id,state,payload,last_error) VALUES (?,?,?,?)",[$order['id'],$state==='queued'?'queued':($state==='sandbox'?'sandbox':'needs_action'),'{}',$state==='needs_action'?'Ein persönlicher Testgutschein kauft keine kostenpflichtige Domain beim Anbieter.':null]);
        $fresh=one('SELECT * FROM shop_orders WHERE id=?',[$order['id']]);pricing_confirm_redemption($fresh);commerce_event('order:'.$order['id'],'order.created',['order_id'=>(int)$order['id']]);db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    sync_zero_base_for_module($fresh);audit_event('checkout.free_entitlement',$order['reference']);return $fresh;
}
function pricing_renew_free(array $order): void {
    $s=pricing_snapshot($order);if(!$s || $order['payment_kind']!=='no_charge' || (int)$s['regular_net']!==0 || !pricing_discount_authorized($order))return;$c=ensure_order_contract($order);$months=billing_months($order['billing']);if(!$c || !$months || contract_closed($c) || !(int)$c['paid_until'] || ((int)$c['cancel_at'] && (int)$c['cancel_at']<=(int)$c['paid_until']))return;
    // Bounded catch-up; a delayed worker cannot create unbounded invoices in a request.
    for($n=0;$n<3 && (int)$c['paid_until']<=shop_now();$n++){
        $due=(int)$c['paid_until'];$until=add_contract_months($due,$months);$r=create_open_invoice($order,'renewal',$due,$until);
        db()->beginTransaction();try{$lock=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';$fresh=one('SELECT * FROM shop_contracts WHERE order_id=?'.$lock,[$order['id']]);if((int)$fresh['paid_until']!==$due || contract_closed($fresh)){db()->commit();break;}if((int)$r['amount_cents']!==0)throw new RuntimeException('Kostenlose Verlängerung und Rechnungsbetrag stimmen nicht überein.');query("UPDATE shop_receivables SET state='paid',settled_ts=? WHERE invoice_id=? AND state='open'",[shop_now(),$r['invoice_id']]);query('UPDATE shop_contracts SET paid_until=?,next_due_at=? WHERE order_id=?',[$until,$until,$order['id']]);query('UPDATE shop_free_entitlements SET until_ts=? WHERE order_id=?',[$until,$order['id']]);commerce_event('free-invoice:'.$r['invoice_id'],'order.free_renewal',['order_id'=>(int)$order['id'],'invoice_id'=>(int)$r['invoice_id']]);db()->commit();$c=ensure_order_contract($order);}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    }
}
function pricing_paypal_frequency(string $billing): array {
    $months=billing_months($billing);if($months<1 || $months>12)throw new RuntimeException('Dieses Intervall wird mit wiederkehrender Rechnung und PayPal-Zahlungslink abgerechnet.');return ['interval_unit'=>$months===12?'YEAR':'MONTH','interval_count'=>$months===12?1:$months];
}
function pricing_paypal_plan(array $order,PaypalProvider $paypal,?array $intent=null): string {
    $s=pricing_snapshot($order);$initial=$intent?(int)$intent['initial_gross_cents']:vat_from_net((int)$s['initial_net'])['gross_cents'];$regular=$intent?(int)$intent['recurring_gross_cents']:vat_from_net((int)$s['regular_net'])['gross_cents'];if($regular<=0)throw new RuntimeException('Kostenlose Verträge benötigen kein PayPal-Abo.');
    $frequency=pricing_paypal_frequency($order['billing']);$key=hash('sha256',json_encode(['pricing-v1',integration_identity('paypal'),$order['reference'],$frequency,$initial,$regular,$intent['initial_until']??0]));if($row=one('SELECT plan_id FROM shop_paypal_plans WHERE fingerprint=?',[$key]))return $row['plan_id'];
    $product=$paypal->call('POST','/v1/catalogs/products',['name'=>$order['product_name'],'type'=>'SERVICE','category'=>'SOFTWARE'],'pricing-product-'.substr($key,0,30));if(empty($product['id']))throw new ProviderFailure('paypal');
    $regularCycle=['frequency'=>$frequency,'tenure_type'=>'REGULAR','sequence'=>1,'total_cycles'=>0,'pricing_scheme'=>['fixed_price'=>['currency_code'=>'EUR','value'=>number_format($regular/100,2,'.','')]]];$cycles=[];$preferences=['auto_bill_outstanding'=>false,'payment_failure_threshold'=>1];
    if($intent){if($initial>0){$preferences['setup_fee']=['currency_code'=>'EUR','value'=>number_format($initial/100,2,'.','')];$preferences['setup_fee_failure_action']='CANCEL';}}
    elseif($initial!==$regular){$trial=['frequency'=>$frequency,'tenure_type'=>'TRIAL','sequence'=>1,'total_cycles'=>1];if($initial>0)$trial['pricing_scheme']=['fixed_price'=>['currency_code'=>'EUR','value'=>number_format($initial/100,2,'.','')]];$cycles[]=$trial;$regularCycle['sequence']=2;}
    $cycles[]=$regularCycle;$plan=$paypal->call('POST','/v1/billing/plans',['product_id'=>$product['id'],'name'=>mb_substr($order['product_name'].' · '.cycle($order['billing']),0,127),'status'=>'ACTIVE','billing_cycles'=>$cycles,'payment_preferences'=>$preferences],'pricing-plan-'.substr($key,0,30));if(empty($plan['id']))throw new ProviderFailure('paypal');
    try{query('INSERT INTO shop_paypal_plans (fingerprint,product_id,paypal_mode,plan_id) VALUES (?,?,?,?)',[$key,$order['product_id'],$paypal->mode(),$plan['id']]);}catch(PDOException $e){if(!one('SELECT plan_id FROM shop_paypal_plans WHERE fingerprint=?',[$key]))throw $e;}return $plan['id'];
}
function handle_promotion_action(string $action): bool {
    switch($action){
        case 'coupon_save':require_admin();$id=coupon_save((int)($_POST['id']??0),$_POST);flash('Gutschein gespeichert. Bereits bestellte Preise bleiben unverändert.');redirect('/admin/gutscheine?id='.$id);
        case 'pricing_preview':require_user();rate_limit('coupon_preview',40,3600);return true;
    }return false;
}
