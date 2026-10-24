<?php
function shop_now(): int {return PHP_SAPI==='cli' && isset($GLOBALS['shop_test_now'])?(int)$GLOBALS['shop_test_now']:time();}
function billing_months(string $billing): int {return ['month'=>1,'months2'=>2,'months24'=>24,'quarter'=>3,'halfyear'=>6,'year'=>12,'once'=>0][$billing]??0;}
function add_contract_months(int $timestamp,int $months): int {
    $date=(new DateTimeImmutable('@'.$timestamp))->setTimezone(new DateTimeZone('UTC'));
    $base=$date->modify('first day of this month')->modify(($months>=0?'+':'').$months.' months');
    return $base->setDate((int)$base->format('Y'),(int)$base->format('m'),min((int)$date->format('d'),(int)$base->format('t')))->getTimestamp();
}
function validated_contract_policy(array $input,string $billing,array $old=[]): array {
    $policy=['minimum_months'=>$old['minimum_months']??1,'notice_days'=>$old['notice_days']??0,'billing_months'=>billing_months($billing)];
    foreach(['minimum_months'=>[1,24],'notice_days'=>[0,31]] as $key=>[$min,$max]){
        $value=$input[$key]??$policy[$key];
        if(!is_scalar($value) || !preg_match('/^\d{1,2}$/D',(string)$value) || (int)$value<$min || (int)$value>$max)throw new RuntimeException('Bitte eine Mindestlaufzeit von 1–24 Monaten und eine Kündigungsfrist von 0–31 Tagen eintragen.');
        $policy[$key]=(int)$value;
    }
    return $policy;
}
function product_contract_policy(array $p): array {
    if(isset($p['_contract_policy']))return $p['_contract_policy'];
    $row=one('SELECT * FROM shop_product_contracts WHERE product_id=?',[$p['id']]);
    return validated_contract_policy([],$p['billing'],$row?['minimum_months'=>(int)$row['min_months'],'notice_days'=>(int)$row['notice_days']]:[]);
}
function save_product_contract_policy(int $product,array $input,string $billing): void {
    $p=['id'=>$product,'billing'=>$billing];$policy=validated_contract_policy($input,$billing,product_contract_policy($p));
    if(one('SELECT product_id FROM shop_product_contracts WHERE product_id=?',[$product]))query('UPDATE shop_product_contracts SET min_months=?,notice_days=? WHERE product_id=?',[$policy['minimum_months'],$policy['notice_days'],$product]);
    else query('INSERT INTO shop_product_contracts (product_id,min_months,notice_days) VALUES (?,?,?)',[$product,$policy['minimum_months'],$policy['notice_days']]);
}
function contract_policy_text(array $policy): string {
    if(!$policy['billing_months'])return 'Einmalige Leistung ohne automatische Verlängerung.';
    return 'Mindestlaufzeit: '.$policy['minimum_months'].' Monat(e). '.((int)$policy['billing_months']===1?'Danach monatlich kündbar. ':'Vorauszahlung für jeweils '.$policy['billing_months'].' Monate; Kündigung zum Ende des Leistungszeitraums. ').'Kündigungsfrist: '.($policy['notice_days']?$policy['notice_days'].' Tag(e)':'keine zusätzliche Frist').'. Die Kündigung wird frühestens zum Ende des bereits bezahlten Leistungszeitraums wirksam.';
}
function ensure_order_contract(array $order): ?array {
    $row=one('SELECT * FROM shop_contracts WHERE order_id=?',[$order['id']]);if($row)return $row;
    $configuration=json_decode($order['configuration'],true);$policy=$configuration['contract_policy']??null;if(!is_array($policy))return null;
    try{query("INSERT INTO shop_contracts (order_id,policy,end_payload) VALUES (?,?,'{}')",[$order['id'],json_encode($policy,JSON_THROW_ON_ERROR)]);}catch(PDOException $e){if(!one('SELECT order_id FROM shop_contracts WHERE order_id=?',[$order['id']]))throw $e;}
    return one('SELECT * FROM shop_contracts WHERE order_id=?',[$order['id']]);
}
function contract_for_order(int $order,?int $customer=null): ?array {
    return one('SELECT c.*,o.reference,o.customer_id,o.product_name,o.billing,o.payment_status,o.fulfillment_status,o.paypal_mode,o.payment_id,o.payment_kind,o.profile,o.provider,o.configuration,o.amount_cents,o.currency FROM shop_contracts c JOIN shop_orders o ON o.id=c.order_id WHERE c.order_id=?'.($customer!==null?' AND o.customer_id=?':''),$customer!==null?[$order,$customer]:[$order]);
}
function contract_closed(?array $contract): bool {return $contract && in_array($contract['state'],['ended','unpaid_cancelled'],true);}
function contract_payment_update(array $order,int $paidAt,?string $nextBilling,?array $receivable): void {
    $c=ensure_order_contract($order);if(!$c || contract_closed($c))return;$policy=json_decode($c['policy'],true);$months=(int)$policy['billing_months'];
    $start=(int)$c['started_at']?:$paidAt;$until=(int)$c['paid_until'];
    if($months){
        $candidate=$receivable && (int)$receivable['period_end']?(int)$receivable['period_end']:add_contract_months(max($until,$paidAt),$months);
        $apiNext=$nextBilling?strtotime($nextBilling):false;
        if(!$until && $apiNext && $apiNext>$paidAt && abs($apiNext-add_contract_months($paidAt,$months))<7*86400){$candidate=$apiNext;$aligned=$paidAt+$apiNext-add_contract_months($paidAt,$months);if(add_contract_months($aligned,$months)===$apiNext)$start=$aligned;}
        $until=max($until,$candidate);
    }
    query("UPDATE shop_contracts SET state=CASE WHEN state='pending' THEN 'active' ELSE state END,started_at=?,paid_until=?,next_due_at=? WHERE order_id=?",[$start,$until,$until,$order['id']]);
}
function cancellation_effective_at(array $contract,int $received,int $requested=0): int {
    if(!(int)$contract['started_at'])return max($received,$requested);
    $policy=json_decode($contract['policy'],true);if(!(int)$policy['billing_months'])throw new RuntimeException('Diese Einmalleistung besitzt keine laufende Vertragsverlängerung. Eine außerordentliche Kündigung kannst du jederzeit erklären.');
    $start=(int)$contract['started_at'];$first=add_contract_months($start,(int)$policy['minimum_months']);$target=max($received+(int)$policy['notice_days']*86400,$requested,$first);
    if((int)$contract['paid_until']>=$target)return (int)$contract['paid_until'];
    $a=new DateTimeImmutable('@'.$start);$b=new DateTimeImmutable('@'.$target);$months=max((int)$policy['minimum_months'],((int)$b->format('Y')-(int)$a->format('Y'))*12+(int)$b->format('m')-(int)$a->format('m'));
    $step=(int)$policy['billing_months'];$months=(int)(ceil($months/$step)*$step);$end=add_contract_months($start,$months);if($end<$target)$end=add_contract_months($start,$months+$step);return $end;
}
function cancellation_token(string $key): string {return hash_hmac('sha256','cancellation-receipt:'.$key,ORGA_APP_KEY);}
function cancellation_receipt(array $request): string {
    $declaration=admin_contract_receipt_source($request)??'Kündigung über die Schaltfläche „Jetzt kündigen“ abgegeben.';
    return "Kündigungsbestätigung – Server Management\n\nBeleg: ".$request['reference']."\n".$declaration."\nEingegangen: ".date('d.m.Y H:i:s T',(int)$request['received_ts'])."\nName: ".$request['name']."\nE-Mail: ".$request['email']."\nVertrag: ".$request['contract_reference']."\nArt: ".($request['kind']==='extraordinary'?'Außerordentliche Kündigung':'Ordentliche Kündigung')."\nGewünschtes Ende: ".($request['requested_date']?:'zum frühestmöglichen Zeitpunkt')."\n".($request['reason']?'Grund: '.$request['reason']."\n":'').($request['effective_ts']?"Bestätigter Beendigungszeitpunkt: ".date('d.m.Y H:i T',(int)$request['effective_ts'])."\n":"Die Kündigungserklärung ist eingegangen. Die Zuordnung und der Beendigungszeitpunkt werden geprüft.\n");
}
function schedule_contract_cancellation(array $contract,int $received,int $effective,bool $cascade=false): void {
    module_cancel_guard($contract,$cascade);
    if(contract_closed($contract))return;
    $existing=(int)$contract['cancel_at'];$effective=$existing?min($existing,$effective):$effective;
    query("UPDATE shop_contracts SET state='cancel_requested',cancel_requested_at=CASE WHEN cancel_requested_at=0 THEN ? ELSE cancel_requested_at END,cancel_at=?,paypal_cancel_state=CASE WHEN paypal_cancel_state='done' THEN 'done' ELSE 'pending' END,next_check_at=0 WHERE order_id=? AND state NOT IN ('ended','unpaid_cancelled')",[$received,$effective,$contract['order_id']]);
    if($intent=module_order_intent((int)$contract['order_id']))query("UPDATE shop_module_intents SET state='cancel_requested' WHERE id=? AND applied_at>0",[$intent['id']]);else module_cancel_children($contract,$received,$effective);
}
function submit_cancellation(array $input,?array $customer): array {
    $name=trim((string)($input['name']??''));$email=strtolower(trim((string)($input['email']??'')));$reference=trim((string)($input['contract_reference']??''));$kind=(string)($input['kind']??'ordinary');$reason=trim((string)($input['reason']??''));$date=trim((string)($input['requested_date']??''));$nonce=(string)($input['submission_key']??'');
    if(!$name || strlen($name)>200 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>255 || !$reference || strlen($reference)>100 || !in_array($kind,['ordinary','extraordinary'],true) || strlen($reason)>4000 || preg_match('/[\r\n\x00]/',$name.$email.$reference) || !preg_match('/^[a-f0-9]{64}$/D',$nonce))throw new RuntimeException('Bitte Name, E-Mail-Adresse und Vertragsnummer vollständig und gültig angeben.');
    if($kind==='extraordinary' && !$reason)throw new RuntimeException('Bitte den Grund deiner außerordentlichen Kündigung angeben.');
    $received=shop_now();$requested=0;
    if($date!==''){$d=DateTimeImmutable::createFromFormat('!Y-m-d',$date,new DateTimeZone('Europe/Berlin'));if(!$d || $d->format('Y-m-d')!==$date)throw new RuntimeException('Bitte ein gültiges Kündigungsdatum auswählen.');$requested=$d->getTimestamp();}
    $key=hash_hmac('sha256',$nonce.'|'.$email.'|'.$reference.'|'.$kind.'|'.$date.'|'.$reason,ORGA_APP_KEY);$token=cancellation_token($key);
    if($old=one('SELECT * FROM shop_cancellations WHERE submission_key=?',[$key]))return ['request'=>$old,'token'=>$token];
    $order=$customer?one('SELECT * FROM shop_orders WHERE reference=? AND customer_id=?',[$reference,$customer['id']]):one('SELECT o.* FROM shop_orders o JOIN customers c ON c.id=o.customer_id WHERE o.reference=? AND LOWER(c.email)=?',[$reference,$email]);
    $owner=$order?one('SELECT * FROM customers WHERE id=?',[$order['customer_id']]):null;
    $identified=$order && ($customer || mb_strtolower(trim($owner['first_name'].' '.$owner['last_name']),'UTF-8')===mb_strtolower($name,'UTF-8'));
    $contract=$identified?contract_for_order((int)$order['id']):null;$effective=0;$state='review';
    if($contract && $kind==='ordinary' && !contract_closed($contract) && (!(int)$contract['started_at'] || (int)json_decode($contract['policy'],true)['billing_months']>0)){$effective=cancellation_effective_at($contract,$received,$requested);$state='scheduled';}
    elseif($contract && contract_closed($contract)){$effective=(int)$contract['cancel_at'];$state='completed';}
    db()->beginTransaction();
    try{
        query('INSERT INTO shop_cancellations (reference,submission_key,order_id,customer_id,name,email,contract_reference,kind,reason,requested_date,received_ts,effective_ts,state,receipt_token_hash) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',['OC-K-'.date('ym').'-'.strtoupper(bin2hex(random_bytes(5))),$key,$identified?(int)$order['id']:null,$identified?(int)$order['customer_id']:null,$name,$email,$reference,$kind,$reason,$date,$received,$effective,$state,hash('sha256',$token)]);
        $id=(int)db()->lastInsertId();if($state==='scheduled')schedule_contract_cancellation($contract,$received,$effective);
        $request=one('SELECT * FROM shop_cancellations WHERE id=?',[$id]);
        queue_shop_mail('cancellation:'.$id,$email,'Kündigungsbestätigung '.$request['reference'],cancellation_receipt($request)."\nBeleg speichern:\n".Config::FRONTPANEL_SITE_URL.'/kuendigen?beleg='.$token);
        if($identified)customer_notification((int)$order['customer_id'],'cancellation:'.$id,'Kündigung eingegangen',$effective?'Vertragsende: '.date('d.m.Y',$effective):'Deine Kündigung wird geprüft.','/konto/vertraege?id='.$order['id']);
        db()->commit();audit_event('contract.cancellation',$request['reference']);return ['request'=>$request,'token'=>$token];
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if($old=one('SELECT * FROM shop_cancellations WHERE submission_key=?',[$key]))return ['request'=>$old,'token'=>$token];throw $e;}
}
function approve_cancellation(int $id,int $orderId,int $effective): void {
    $request=one("SELECT * FROM shop_cancellations WHERE id=? AND state='review'",[$id]);$contract=contract_for_order($orderId);
    if(!$request || !$contract || $effective<(int)$request['received_ts'] || contract_closed($contract))throw new RuntimeException('Bitte eine offene Kündigung, einen gültigen Vertrag und ein Beendigungsdatum ab Kündigungseingang wählen.');
    db()->beginTransaction();try{schedule_contract_cancellation($contract,(int)$request['received_ts'],$effective);query("UPDATE shop_cancellations SET order_id=?,customer_id=?,effective_ts=?,state='scheduled' WHERE id=? AND state='review'",[$orderId,$contract['customer_id'],$effective,$id]);$request=one('SELECT * FROM shop_cancellations WHERE id=?',[$id]);$owner=one('SELECT email FROM customers WHERE id=?',[$contract['customer_id']]);queue_shop_mail('cancellation-approved:'.$id,$owner['email'],'Bestätigtes Vertragsende '.$request['reference'],cancellation_receipt($request));customer_notification((int)$contract['customer_id'],'cancellation-approved:'.$id,'Vertragsende bestätigt',date('d.m.Y',$effective),'/konto/vertraege?id='.$orderId);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    audit_event('contract.cancellation.approved',$request['reference']);
}
function complete_manual_cancellation(int $id,int $effective): void {
    $request=one("SELECT * FROM shop_cancellations WHERE id=? AND state='review'",[$id]);
    if(!$request || $effective<(int)$request['received_ts'] || $effective>shop_now())throw new RuntimeException('Bitte eine offene Kündigung und den tatsächlichen, bereits erreichten Beendigungszeitpunkt wählen.');
    if($request['order_id'] && contract_for_order((int)$request['order_id']))throw new RuntimeException('Dieser Shop-Vertrag muss über die Vertragszuordnung beendet werden.');
    db()->beginTransaction();try{
        $changed=query("UPDATE shop_cancellations SET effective_ts=?,state='completed' WHERE id=? AND state='review'",[$effective,$id]);if($changed->rowCount()!==1)throw new RuntimeException('Diese Kündigung wurde bereits bearbeitet.');
        if($request['order_id']){
            $lock=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';$order=one('SELECT * FROM shop_orders WHERE id=?'.$lock,[$request['order_id']]);
            query("UPDATE shop_orders SET fulfillment_status='ended',payment_status=CASE WHEN payment_status='pending' THEN 'cancelled' ELSE payment_status END,updated_at=CURRENT_TIMESTAMP WHERE id=?",[$order['id']]);
            query("UPDATE shop_jobs SET state='cancelled',last_error='Bestandsvertrag persönlich beendet.' WHERE order_id=? AND state NOT IN ('done','sandbox')",[$order['id']]);
            if($service=service_for_order((int)$order['id'],(int)$order['customer_id']))query("UPDATE shop_services SET status='cancelled' WHERE id=?",[$service['id']]);
        }
        $request=one('SELECT * FROM shop_cancellations WHERE id=?',[$id]);queue_shop_mail('cancellation-manual:'.$id,$request['email'],'Bestätigtes Vertragsende '.$request['reference'],cancellation_receipt($request));
        if($request['customer_id'])customer_notification((int)$request['customer_id'],'cancellation-manual:'.$id,'Vertragsende bestätigt',date('d.m.Y H:i',$effective),'/konto/vertraege');db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    audit_event('contract.cancellation.manually_completed',$request['reference']);
}
require_once __DIR__.'/contract-termination.php';
