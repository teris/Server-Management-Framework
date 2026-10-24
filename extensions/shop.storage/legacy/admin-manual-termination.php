<?php
function admin_provider_manually_ended(?array $contract): bool {
    return $contract && !empty(json_decode($contract['end_payload']??'{}',true)['admin_provider_ended']);
}
function admin_manual_end_local(array $order,array $contract,int $administrator,string $reason): void {
    if(admin_provider_manually_ended($contract))return;
    $now=shop_now();$payload=json_decode($contract['end_payload'],true)?:[];
    $payload['admin_provider_ended']=['at'=>$now,'by'=>$administrator,'reason'=>$reason];
    $effective=(int)$contract['cancel_at']?min((int)$contract['cancel_at'],$now):$now;
    if(!(int)$contract['started_at'] && !in_array($order['payment_status'],['paid','test_paid'],true) && !one("SELECT id FROM shop_payments WHERE order_id=? AND status='completed'",[$order['id']])){
        foreach(all("SELECT invoice_id FROM shop_receivables WHERE order_id=? AND kind='initial' AND state='open'",[$order['id']]) as $invoice)void_open_invoice((int)$invoice['invoice_id'],'Neubestellung durch die Administration vor Bereitstellung beendet.');
        if(in_array($order['payment_status'],['pending','review'],true) && !one("SELECT id FROM shop_payments WHERE order_id=? AND status='completed'",[$order['id']]))query("UPDATE shop_orders SET payment_status='cancelled' WHERE id=?",[$order['id']]);
    }
    query("UPDATE shop_contracts SET state='ended',cancel_requested_at=CASE WHEN cancel_requested_at=0 THEN ? ELSE cancel_requested_at END,cancel_at=?,end_state='done',end_payload=?,paypal_cancel_state=CASE WHEN paypal_cancel_state='done' THEN 'done' ELSE 'pending' END,last_error=NULL,next_check_at=2147483647 WHERE order_id=?",[$now,$effective,json_encode($payload),$order['id']]);
    query("UPDATE shop_cancellations SET effective_ts=CASE WHEN effective_ts=0 THEN ? ELSE effective_ts END,state='completed' WHERE order_id=? AND state IN ('review','scheduled')",[$effective,$order['id']]);
    query("UPDATE shop_module_intents SET state='ended',last_error=NULL WHERE order_id=? OR (parent_order_id=? AND order_id IS NULL)",[$order['id'],$order['id']]);
    query('DELETE FROM shop_storage_reservations WHERE job_id IN (SELECT id FROM shop_jobs WHERE order_id=?)',[$order['id']]);
    foreach(all('SELECT id,notes FROM shop_network_addresses WHERE order_id=?',[$order['id']]) as $address)query("UPDATE shop_network_addresses SET state='review',order_id=NULL,service_id=NULL,notes=? WHERE id=?",[mb_substr('Manuell beendeter Vertrag '.$order['reference'].': Nutzung vor erneuter Freigabe prüfen. '.$address['notes'],0,1000),$address['id']]);
    if($linked=service_for_order((int)$order['id'],(int)$order['customer_id'])){
        $row=one('SELECT provider,external_id,metadata FROM shop_resource_links WHERE service_id=?',[$linked['id']]);$meta=json_decode($row['metadata'],true)?:[];
        if(!isset($meta['retired_external_id']))$meta['retired_external_id']=$row['external_id'];$meta['retired_ts']=$now;$meta['admin_provider_ended']=$payload['admin_provider_ended'];
        query('UPDATE shop_resource_links SET external_id=?,metadata=?,credentials=NULL WHERE service_id=?',['retired:'.$order['id'].':'.hash('sha256',$row['provider'].'|'.$row['external_id']),json_encode($meta),$linked['id']]);
        query("UPDATE shop_services SET status='cancelled' WHERE id=?",[$linked['id']]);
    }
    $export=one('SELECT * FROM shop_vps_exports WHERE order_id=?',[$order['id']]);
    if($export){$p=json_decode($export['payload'],true)?:[];$p['admin_provider_ended']=$payload['admin_provider_ended'];query('UPDATE shop_vps_exports SET state=?,payload=?,last_error=NULL,updated_ts=? WHERE order_id=?',[$export['state']==='ready'?'ready':'cancelled',json_encode($p),$now,$order['id']]);}
    if(($intent=module_order_intent((int)$order['id'])) && $intent['applied_at']){
        $parent=one('SELECT * FROM shop_orders WHERE id=? AND customer_id=?',[$intent['parent_order_id'],$order['customer_id']]);$pc=$parent?one('SELECT * FROM shop_contracts WHERE order_id=?',[$parent['id']]):null;
        if($parent && $pc && !contract_closed($pc) && ($parentLink=service_for_order((int)$parent['id'],(int)$order['customer_id'])) && one('SELECT service_id FROM shop_service_module_profiles WHERE service_id=?',[$parentLink['id']])){$parentService=one('SELECT s.*,r.provider,r.metadata FROM shop_services s JOIN shop_resource_links r ON r.service_id=s.id WHERE s.id=?',[$parentLink['id']]);$parentService['meta']=json_decode($parentService['metadata'],true);module_save_entitlements($parentService,module_effective_profile($parentService));}
    }
    module_note_contract_end($order);pricing_release_cancelled(one('SELECT * FROM shop_orders WHERE id=?',[$order['id']]));commerce_event('contract-ended:'.$order['id'],'contract.ended',['order_id'=>(int)$order['id']]);
}
function admin_manual_end_paypal(int $orderId,int $administrator): array {
    if(!one("SELECT id FROM users WHERE id=? AND role='admin' AND active='y'",[$administrator]))throw new RuntimeException('Diese Aktion erfordert einen aktiven Administrator.');
    $c=one('SELECT * FROM shop_contracts WHERE order_id=?',[$orderId]);if(!$c || !contract_closed($c) || !admin_provider_manually_ended($c))throw new RuntimeException('Die technische Beendigung wurde noch nicht manuell abgeschlossen.');
    if($c['paypal_cancel_state']==='done')return ['order_id'=>$orderId,'done'=>true];
    $token=bin2hex(random_bytes(32));$claim=query("UPDATE shop_contracts SET lock_token=?,locked_until=? WHERE order_id=? AND locked_until<? AND paypal_cancel_state<>'done'",[$token,time()+180,$orderId,time()]);
    if($claim->rowCount()!==1){$current=one('SELECT paypal_cancel_state FROM shop_contracts WHERE order_id=?',[$orderId]);return ['order_id'=>$orderId,'done'=>($current['paypal_cancel_state']??'')==='done','error'=>'Der PayPal-Abgleich wird gerade verarbeitet. Bitte anschließend erneut direkt prüfen.'];}
    try{
        $order=one('SELECT * FROM shop_orders WHERE id=?',[$orderId]);$done=cancel_contract_paypal($order,$c,true);$error=$done?null:'PayPal hat die Beendigung noch nicht bestätigt. Bitte im Vertrag direkt erneut prüfen.';
        query('UPDATE shop_contracts SET paypal_cancel_state=?,last_error=?,next_check_at=2147483647 WHERE order_id=? AND lock_token=?',[$done?'done':'awaiting_expiry',$error,$orderId,$token]);audit_event('contract.admin.paypal_end',$order['reference']);
        return ['order_id'=>$orderId,'done'=>$done,'error'=>$error];
    }catch(Throwable $e){$error=$e instanceof ProviderFailure?$e->getMessage():'Die technische Beendigung ist abgeschlossen. Der PayPal-Abgleich muss im Vertrag direkt erneut geprüft werden.';query("UPDATE shop_contracts SET paypal_cancel_state='needs_action',last_error=?,next_check_at=2147483647 WHERE order_id=? AND lock_token=?",[$error,$orderId,$token]);return ['order_id'=>$orderId,'done'=>false,'error'=>$error];}
    finally{query('UPDATE shop_contracts SET lock_token=NULL,locked_until=0 WHERE order_id=? AND lock_token=?',[$orderId,$token]);}
}
function admin_manual_end_family_paypal(int $orderId,int $administrator): array {
    $root=one('SELECT * FROM shop_orders WHERE id=?',[$orderId]);if(!$root)throw new RuntimeException('Vertrag nicht gefunden.');$results=[];$start=microtime(true);
    foreach(admin_order_family($root) as $order){$c=one('SELECT * FROM shop_contracts WHERE order_id=?',[$order['id']]);if(!admin_provider_manually_ended($c))continue;
        if($c['paypal_cancel_state']==='done'){$results[]=['order_id'=>(int)$order['id'],'done'=>true];continue;}
        if(microtime(true)-$start>40){$error='Weitere PayPal-Abos bitte über die direkte Prüfung abschließen.';query("UPDATE shop_contracts SET paypal_cancel_state='needs_action',last_error=? WHERE order_id=? AND paypal_cancel_state<>'done' AND locked_until<?",[$error,$order['id'],time()]);$results[]=['order_id'=>(int)$order['id'],'done'=>false,'error'=>$error];continue;}
        $results[]=admin_manual_end_paypal((int)$order['id'],$administrator);
    }
    if(!$results)throw new RuntimeException('Kein manuell beendeter Vertrag für den direkten PayPal-Abschluss vorhanden.');
    return $results;
}
