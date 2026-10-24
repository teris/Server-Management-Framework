<?php
function cancel_contract_paypal(array $order,array $c,bool $verifyTestSubscription=false): bool {
    if(test_order($order) && !$verifyTestSubscription)return true;
    if($order['payment_kind']!=='subscription' || !$order['payment_id'])return true;
    $paypal=checked_order_paypal($order);$s=checked_subscription($order,$paypal);$status=$s['status']??'';
    if(in_array($status,['CANCELLED','EXPIRED'],true))return true;
    if(in_array($status,['APPROVAL_PENDING','APPROVED'],true))return false;
    if(!in_array($status,['ACTIVE','SUSPENDED'],true))throw new RuntimeException('Der PayPal-Abo-Status muss vor der Beendigung geprüft werden.');
    query("UPDATE shop_contracts SET paypal_cancel_state='requested' WHERE order_id=?",[$order['id']]);
    $paypal->call('POST','/v1/billing/subscriptions/'.rawurlencode($order['payment_id']).'/cancel',['reason'=>'Kündigung zum vereinbarten Vertragsende im Orga-Consult-Shop.'],'cancel-'.$order['reference']);
    $s=checked_subscription($order,$paypal);if(!in_array($s['status']??'',['CANCELLED','EXPIRED'],true))throw new RuntimeException('PayPal hat das Ende der wiederkehrenden Abbuchungen noch nicht bestätigt.');return true;
}

function disable_domain_renewal(array $service): void {
    $api=new OvhProvider();$path='/domain/'.rawurlencode($service['meta']['zone']).'/serviceInfos';$info=$api->call('GET',$path);
    if(!isset($info['renew']) || !is_array($info['renew']) || !array_key_exists('automatic',$info['renew']))throw new RuntimeException('Die OVH-Domainverlängerung muss im Anbieterbereich geprüft werden.');
    if($info['renew']['automatic']){
        if(!empty($info['renew']['forced']))throw new RuntimeException('OVH erzwingt derzeit die Verlängerung. Bitte den Domainvertrag beim Anbieter prüfen.');
        $renew=$info['renew'];$renew['automatic']=false;$api->call('PUT',$path,['renew'=>$renew]);$after=$api->call('GET',$path);
        if(($after['renew']['automatic']??true)!==false)throw new RuntimeException('OVH hat das Abschalten der automatischen Domainverlängerung noch nicht bestätigt.');
    }
}
function stop_contract_service(array $order,array $c): bool {
    if(admin_provider_manually_ended($c) || admin_provider_manually_ended(one('SELECT end_payload FROM shop_contracts WHERE order_id=?',[$order['id']])))return true;
    if($intent=module_order_intent((int)$order['id']))return end_module_addon($intent);
    if($order['provider']==='ogp')return (new SMF\Core\ProductRegistry(SMF\Core\Kernel::boot()->modules()))->service('gameserver')->end($order);
    $service=service_for_order((int)$order['id'],(int)$order['customer_id']);if(!$service){$unused=one("SELECT id FROM shop_jobs WHERE order_id=? AND phase='start' AND state='cancelled'",[$order['id']]);if($unused && SchemaManager::hasTable(db(),'shop_storage_reservations'))query('DELETE FROM shop_storage_reservations WHERE job_id=?',[$unused['id']]);return true;}
    $service=bound_service((int)$service['id'],(int)$order['customer_id']);
    return resource_mutex((int)$service['id'],function()use($order,$c,$service){
        if($order['paypal_mode']!=='live' && !test_order($order))return true;
        if($service['provider']==='ispconfig'){end_hosting_service($service);return true;}
        if($service['provider']==='ovh'){disable_domain_renewal($service);return true;}
        if($service['provider']==='manual'){throw new RuntimeException('Diese persönlich bereitgestellte Leistung muss im Adminbereich auch beim Anbieter beendet werden.');}
        if(SchemaManager::hasTable(db(),'shop_vps_exports') && ($export=one('SELECT * FROM shop_vps_exports WHERE order_id=?',[$order['id']])))return process_vps_export($export);
        $api=new ProxmoxProvider();verify_proxmox_resource($service,$api);$node=$service['meta']['node'];$vmid=(int)$service['meta']['vmid'];
        if(!$api->poolContains($node,$vmid,proxmox_service_type($service)))throw new RuntimeException('Die VM befindet sich nicht mehr im zugeordneten Shop-Pool.');
        $state=$api->vm($node,$vmid,proxmox_service_type($service));if(($state['status']??'')==='stopped'){$api->call('PUT','/nodes/'.rawurlencode($node).'/'.proxmox_service_type($service).'/'.$vmid.'/config',['onboot'=>0]);return queue_vps_export($order,$service);}
        $payload=json_decode($c['end_payload'],true);if(!empty($payload['task'])){
            $task=$api->call('GET','/nodes/'.rawurlencode($node).'/tasks/'.rawurlencode($payload['task']).'/status');
            if(($task['status']??'')!=='stopped')return false;
            throw new RuntimeException('Die VM wurde noch nicht heruntergefahren. Bitte die Proxmox-Aufgabe prüfen; die VM-Daten bleiben erhalten.');
        }
        if(!empty($payload['shutdown_requested']))throw new RuntimeException('Die Shutdown-Bestätigung fehlt. Bitte den VM-Zustand im Adminbereich prüfen.');
        query("UPDATE shop_contracts SET end_payload=? WHERE order_id=?",[json_encode(['shutdown_requested'=>true]),$order['id']]);
        $api->call('PUT','/nodes/'.rawurlencode($node).'/'.proxmox_service_type($service).'/'.$vmid.'/config',['onboot'=>0]);
        $task=$api->call('POST','/nodes/'.rawurlencode($node).'/'.proxmox_service_type($service).'/'.$vmid.'/status/shutdown',['timeout'=>300,'forceStop'=>1]);if(!is_string($task) || !str_starts_with($task,'UPID:'))throw new RuntimeException('Die Shutdown-Bestätigung fehlt. VPS-Zustand vor Wiederholung prüfen.');query('UPDATE shop_contracts SET end_payload=? WHERE order_id=?',[json_encode(['shutdown_requested'=>true,'task'=>$task]),$order['id']]);return false;
    });
}
function process_contract_end(int $id): void {
    $c=one('SELECT * FROM shop_contracts WHERE order_id=?',[$id]);if(!$c || !(int)$c['cancel_at'] || admin_provider_manually_ended($c))return;$order=one('SELECT * FROM shop_orders WHERE id=?',[$id]);$now=shop_now();
    if(!contract_closed($c) && (int)$c['cancel_at']<=$now){
        db()->beginTransaction();try{
            $lock=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';one('SELECT id FROM shop_orders WHERE id=?'.$lock,[$id]);$c=one('SELECT * FROM shop_contracts WHERE order_id=?',[$id]);
            if(!contract_closed($c)){
                query("UPDATE shop_contracts SET state='ended',end_state='pending' WHERE order_id=?",[$id]);query("UPDATE shop_orders SET fulfillment_status='ended',updated_at=CURRENT_TIMESTAMP WHERE id=?",[$id]);
                if(!(int)$c['started_at']){foreach(all("SELECT invoice_id FROM shop_receivables WHERE order_id=? AND state='open'",[$id]) as $r)void_open_invoice((int)$r['invoice_id'],'Neubestellung vor Bereitstellung gekündigt.');query("UPDATE shop_orders SET payment_status='cancelled' WHERE id=?",[$id]);}
                query("UPDATE shop_jobs SET state='cancelled',last_error='Vertrag beendet.' WHERE order_id=? AND state NOT IN ('done','sandbox')",[$id]);
                if($service=service_for_order($id,(int)$order['customer_id']))query("UPDATE shop_services SET status='cancelled' WHERE id=?",[$service['id']]);
                commerce_event('contract-ended:'.$id,'contract.ended',['order_id'=>$id]);
            }
            db()->commit();$c=one('SELECT * FROM shop_contracts WHERE order_id=?',[$id]);
        }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    }
    if($order['provider']==='ovh' && (contract_closed($c) || (int)$c['paid_until']>=(int)$c['cancel_at'])){
        $payload=json_decode($c['end_payload'],true);
        if(empty($payload['renewal_off']) && ($linked=service_for_order($id,(int)$order['customer_id']))){$service=bound_service((int)$linked['id'],(int)$order['customer_id']);resource_mutex((int)$service['id'],fn()=>disable_domain_renewal($service));$payload['renewal_off']=true;query('UPDATE shop_contracts SET end_payload=? WHERE order_id=?',[json_encode($payload),$id]);}
    }
    if($c['paypal_cancel_state']!=='done' && (contract_closed($c) || !(int)$c['started_at'] || (int)$c['paid_until']>=(int)$c['cancel_at'])){
        $done=cancel_contract_paypal($order,$c);query('UPDATE shop_contracts SET paypal_cancel_state=? WHERE order_id=?',[$done?'done':'awaiting_expiry',$id]);$c['paypal_cancel_state']=$done?'done':'awaiting_expiry';
    }
    if(contract_closed($c))module_note_contract_end($order);
    if(contract_closed($c) && $c['end_state']!=='done'){
        try{$done=stop_contract_service($order,$c);query('UPDATE shop_contracts SET end_state=? WHERE order_id=?',[$done?'done':'running',$id]);$c['end_state']=$done?'done':'running';}
        catch(Throwable $e){query("UPDATE shop_contracts SET end_state='needs_action' WHERE order_id=?",[$id]);throw $e;}
    }
    if(contract_closed($c) && $c['end_state']==='done' && $c['paypal_cancel_state']==='done' && !one("SELECT invoice_id FROM shop_receivables WHERE order_id=? AND state='open'",[$id]) && !one("SELECT p.invoice_id FROM shop_invoice_paypal p JOIN shop_receivables r ON r.invoice_id=p.invoice_id WHERE r.order_id=? AND p.state<>'complete'",[$id]))query('UPDATE shop_contracts SET next_check_at=?,last_error=NULL WHERE order_id=?',[2147483647,$id]);
    elseif(contract_closed($c))query('UPDATE shop_contracts SET next_check_at=? WHERE order_id=?',[$now+60,$id]);
}
