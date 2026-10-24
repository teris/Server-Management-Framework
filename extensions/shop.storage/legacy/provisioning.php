<?php
/** Exactly one job per paid order; sandbox jobs cannot create real resources. */
function save_job_phase(array &$job,string $phase,array $payload,string $state='waiting'): void {
    $changed=query("UPDATE shop_jobs SET phase=?,payload=?,state=?,locked_until=CASE WHEN locked_until>=? THEN locked_until+1 ELSE ? END,updated_at=CURRENT_TIMESTAMP WHERE id=? AND lock_token=? AND state IN ('running','waiting') AND locked_until>=?",[$phase,json_encode($payload),$state,time()+600,time()+600,$job['id'],$job['lock_token'],time()]);
    if($changed->rowCount()!==1)throw new RuntimeException('Bereitstellungssperre abgelaufen; Anbieterzustand prüfen.');
    $job['phase']=$phase;$job['payload']=json_encode($payload);
}
/** An administrator may reconnect a manual entry while recovering its paid order. */
function provisioning_target_service(array $order,array $job): ?array {
    $payload=json_decode($job['payload'],true,32,JSON_THROW_ON_ERROR);
    $id=(int)($payload['target_service_id']??0);if(!$id)return null;
    $service=one('SELECT s.* FROM shop_services s LEFT JOIN shop_resource_links r ON r.service_id=s.id WHERE s.id=? AND s.customer_id=? AND r.service_id IS NULL',[$id,$order['customer_id']]);
    if($order['provider']!=='ispconfig' || !$service || $service['category']!=='hosting' || !in_array($service['status'],['active','pending'],true) || $service['identifier']!==$order['domain'] || one('SELECT id FROM shop_hosting_children WHERE service_id=?',[$id]))throw new RuntimeException('Der vorhandene Produkteintrag passt nicht zu diesem Hosting-Auftrag. Bitte die Zuordnung im Adminbereich prüfen.');
    return $service;
}
function finish_provisioning(array $order,array $job,string $external,array $metadata,string $identifier,?array $credentials=null,array $children=[]): void {
    $profile=json_decode($order['profile'],true,32,JSON_THROW_ON_ERROR);$binding=$profile['connection_id']??'';
    if(!is_string($binding)||!hash_equals($binding,integration_identity($order['provider'])))throw new RuntimeException('Anbieterbindung wurde vor Abschluss geändert.');
    $existing=one('SELECT s.id FROM shop_services s JOIN shop_resource_links r ON r.service_id=s.id WHERE r.provider=? AND r.external_id=?',[$order['provider'],$external]);
    if($existing)throw new RuntimeException('Diese Anbieter-Ressource ist bereits zugeordnet. Bitte Auftrag prüfen.');
    db()->beginTransaction();
    try{
        $lease=query("UPDATE shop_jobs SET state='committing' WHERE id=? AND lock_token=? AND state IN ('running','waiting') AND locked_until>=?",[$job['id'],$job['lock_token'],time()]);
        if($lease->rowCount()!==1)throw new RuntimeException('Bereitstellungssperre vor Abschluss verloren.');
        $category=['ispconfig'=>'hosting','proxmox'=>'server','ovh'=>'domain','manual'=>'managed','ogp'=>'gameserver'][$order['provider']];
        $target=provisioning_target_service($order,$job);
        if($target){$serviceId=(int)$target['id'];query("UPDATE shop_services SET status='active',details=?,price_cents=?,billing=? WHERE id=?",['Bereitgestellt aus Bestellung '.$order['reference'],$order['amount_cents'],$order['billing'],$serviceId]);}
        else{query("INSERT INTO shop_services (customer_id,name,category,identifier,status,details,price_cents,billing) VALUES (?,?,?,?,'active',?,?,?)",[$order['customer_id'],$order['product_name'],$category,$identifier,'Bereitgestellt aus Bestellung '.$order['reference'],$order['amount_cents'],$order['billing']]);$serviceId=(int)db()->lastInsertId();}
        $oc=json_decode($order['configuration'],true);if($order['provider']==='proxmox')$metadata['ownership_marker']='Server Management '.$order['reference'];$metadata['test_mode']=test_order($order);$metadata['modules']=$oc['modules']??[];$metadata['provisioning_notice']=$oc['provisioning_notice']??'';$metadata['dns_notice']=$oc['dns_notice']??commerce_settings()['notice_dns'];$metadata['order_id']=(int)$order['id'];$metadata['connection_id']=$binding;
        if($order['provider']==='proxmox'){$type=($metadata['type']??'qemu')==='lxc'?'lxc':'qemu';$metadata=SMF\Core\ShopResourceBindings::physical($metadata,(new ProxmoxProvider())->vmConfig($metadata['node'],(int)$metadata['vmid'],$type),$type);}
        SMF\Core\ShopResourceBindings::grant($serviceId,(int)$order['customer_id'],$order['provider'],$metadata);
        query('INSERT INTO shop_resource_links (service_id,provider,external_id,metadata,credentials,verified_at) VALUES (?,?,?,?,?,CURRENT_TIMESTAMP)',[$serviceId,$order['provider'],$external,json_encode($metadata),$credentials?secret_seal($credentials,'service:'.$serviceId):null]);
        if($order['provider']==='ogp'){
            $changed=query("UPDATE smf_game_leases SET service_id=?,state='active',updated_at=? WHERE order_id=? AND marker=? AND connection_id=? AND state='installing'",[$serviceId,time(),$order['id'],$metadata['marker'],$binding]);
            if($changed->rowCount()!==1)throw new RuntimeException('Gameserverreservierung hat sich vor Abschluss geändert.');
            query("UPDATE smf_game_ports SET state='active' WHERE order_id=?",[$order['id']]);
        }
        foreach($children as $child){query('INSERT INTO shop_hosting_children (service_id,kind,external_id,name,metadata) VALUES (?,?,?,?,?)',[$serviceId,$child['kind'],(string)$child['external_id'],$child['name'],json_encode(['state'=>'active']+($child['metadata']??[]))]);if($credentials && ($credentials['username']??'')===$child['name'])save_child_credentials((int)db()->lastInsertId(),$credentials);}
        query("UPDATE shop_jobs SET state='done',last_error=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=? AND lock_token=?",[$job['id'],$job['lock_token']]);
        if(SchemaManager::hasTable(db(),'shop_storage_reservations'))query('DELETE FROM shop_storage_reservations WHERE job_id=?',[$job['id']]);
        query("UPDATE shop_orders SET fulfillment_status='active',updated_at=CURRENT_TIMESTAMP WHERE id=?",[$order['id']]);admin_order_note_active($order);commerce_event('service:'.$serviceId,'service.ready',['order_id'=>(int)$order['id']]);db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    queue_default_dns($order);
    audit_event('provision.completed',$order['reference']);
}


function provision_ovh(array $order,array &$job,array $profile,array $data): void {
    $api=new OvhProvider();$domain=valid_domain($order['domain']);
    if($job['phase']==='start'){
        $cart=$profile['prepared_cart']??null;
        if(!$cart || ($cart['expires']??0)<time()){
            $snapshot=json_decode($order['configuration'],true);
            $cart=prepare_domain_cart($domain,$profile,$snapshot['billing'],$snapshot['legal_form']);
        }
        $data=['cart_id'=>$cart['cart_id']];save_job_phase($job,'checkout_ready',$data);
    }
    $path='/order/cart/'.rawurlencode($data['cart_id']);
    if($job['phase']==='checkout_ready'){
        $preview=$api->call('GET',$path.'/checkout');check_domain_purchase_total($preview,(int)$profile['max_purchase_cents']);
        save_job_phase($job,'checking_out',$data,'running');
        $result=$api->call('POST',$path.'/checkout',['autoPayWithPreferredPaymentMethod'=>true,'waiveRetractationPeriod'=>true]);$data['ovh_order_id']=$result['orderId']??0;
        if(!$data['ovh_order_id'])throw new ProviderFailure('ovh');save_job_phase($job,'zone_wait',$data);return;
    }
    if($job['phase']==='zone_wait'){
        try{$api->call('GET','/domain/zone/'.rawurlencode($domain));}catch(ProviderFailure $e){if($e->httpStatus===404){save_job_phase($job,'zone_wait',$data);return;}throw $e;}
        finish_provisioning($order,$job,$domain,['zone'=>$domain,'ovh_order_id'=>$data['ovh_order_id']],$domain);
    }
}
function provision_manual(array $order,array &$job): void {
    save_job_phase($job,'awaiting_manual',[], 'needs_action');
    query("UPDATE shop_jobs SET last_error='Diese bezahlte Managed-Leistung wartet auf die persönliche Bereitstellung.' WHERE id=?",[$job['id']]);
    query("UPDATE shop_orders SET fulfillment_status='needs_action' WHERE id=?",[$order['id']]);
}
function complete_manual_order(int $orderId,string $identifier): void {
    $order=one("SELECT * FROM shop_orders WHERE id=? AND provider='manual'",[$orderId]);$job=$order?one("SELECT * FROM shop_jobs WHERE order_id=? AND state='needs_action'",[$orderId]):null;
    if(!$order || !order_payment_allows_provisioning($order) || !$job || !$identifier)throw new RuntimeException('Bitte einen bezahlten oder ausdrücklich freigegebenen Auftrag und eine Leistungskennung auswählen.');
    $token=bin2hex(random_bytes(16));$claim=query("UPDATE shop_jobs SET state='running',lock_token=?,locked_until=? WHERE id=? AND state='needs_action' AND locked_until<?",[$token,time()+180,$job['id'],time()]);
    if($claim->rowCount()!==1)throw new RuntimeException('Dieser Auftrag wird bereits bearbeitet.');$job['lock_token']=$token;
    try{finish_provisioning($order,$job,'manual/'.$orderId,[],$identifier);}catch(Throwable $exception){query("UPDATE shop_jobs SET state='needs_action',locked_until=0,lock_token=NULL WHERE id=? AND lock_token=?",[$job['id'],$token]);throw $exception;}finally{query('UPDATE shop_jobs SET locked_until=0,lock_token=NULL WHERE id=? AND lock_token=?',[$job['id'],$token]);}
}
function run_provisioning_jobs(int $limit=3): int {
    $count=0;$now=time();
    // A worker that died during a write is not automatically retried: the remote
    // side may already have accepted the operation.
    query("UPDATE shop_jobs SET state='needs_action',last_error='Einrichtung wurde unterbrochen. Anbieterzustand vor Fortsetzung prüfen.' WHERE state='running' AND locked_until<?",[$now]);
    $jobs=all("SELECT j.id FROM shop_jobs j JOIN shop_orders o ON o.id=j.order_id WHERE j.state IN ('queued','waiting') AND j.locked_until<? ORDER BY CASE WHEN o.payment_kind='no_charge' THEN 0 ELSE 1 END,j.id LIMIT ".max(1,min(10,$limit)),[$now]);
    foreach($jobs as $candidate){
        $claimNow=time();$token=bin2hex(random_bytes(16));$claim=query("UPDATE shop_jobs SET state='running',lock_token=?,locked_until=?,attempts=attempts+1 WHERE id=? AND state IN ('queued','waiting') AND locked_until<?",[$token,$claimNow+600,$candidate['id'],$claimNow]);
        if($claim->rowCount()!==1)continue;
        $job=one('SELECT * FROM shop_jobs WHERE id=?',[$candidate['id']]);$order=one('SELECT * FROM shop_orders WHERE id=?',[$job['order_id']]);
        try{
            if(!$order || $order['paypal_mode']!=='live')throw new RuntimeException('Sandbox-Aufträge dürfen keine Live-Ressourcen einrichten.');
            if(!order_payment_allows_provisioning($order))throw new RuntimeException('Keine bestätigte Live-Zahlung oder ausdrückliche Adminfreigabe für diesen Auftrag vorhanden.');
            if(contract_closed(one('SELECT * FROM shop_contracts WHERE order_id=?',[$order['id']])) || in_array($order['fulfillment_status'],['cancelled','ended'],true))throw new RuntimeException('Dieser Vertrag wurde bereits beendet. Eine Bereitstellung ist nicht mehr zulässig.');
            $profile=json_decode($order['profile'],true,32,JSON_THROW_ON_ERROR);$data=json_decode($job['payload'],true,32,JSON_THROW_ON_ERROR);
            if(!hash_equals($profile['connection_id']??'',integration_identity($order['provider'])))throw new RuntimeException('Anbieter-Verbindung wurde seit der Bestellung geändert.');
            query("UPDATE shop_orders SET fulfillment_status='provisioning' WHERE id=?",[$order['id']]);
            if($intent=module_order_intent((int)$order['id']))provision_module_addon($order,$job,$intent);
            elseif(isset($profile['module_product']))(new SMF\Core\ProductRegistry(SMF\Core\Kernel::boot()->modules()))->service($profile['module_product'])->provision($order,$job,$profile,$data);
            else match($order['provider']){'ispconfig'=>provision_ispconfig($order,$job,$profile,$data),'proxmox'=>(($profile['virtualization']??'qemu')==='lxc'?provision_lxc($order,$job,$profile,$data):provision_proxmox($order,$job,$profile,$data)),'ovh'=>provision_ovh($order,$job,$profile,$data),'manual'=>provision_manual($order,$job),default=>throw new RuntimeException('Keine Bereitstellung für diesen Anbieter.')};
            $count++;
        }catch(Throwable $e){
            $message=$e instanceof RuntimeException && !($e instanceof PDOException)?$e->getMessage():'Einrichtung konnte nicht abgeschlossen werden. Bitte Anbieterzustand prüfen.';
            $failed=query("UPDATE shop_jobs SET state='needs_action',last_error=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND lock_token=?",[substr($message,0,500),$job['id'],$token]);if($failed->rowCount()===1)query("UPDATE shop_orders SET fulfillment_status='needs_action' WHERE id=? AND fulfillment_status NOT IN ('cancelled','ended')",[$job['order_id']]);audit_event('provision.failed',$order['reference']??(string)$job['id'],'needs_action');
        }finally{query('UPDATE shop_jobs SET locked_until=0,lock_token=NULL WHERE id=? AND lock_token=?',[$job['id'],$token]);}
    }
    $row=one("SELECT meta_key FROM shop_meta WHERE meta_key='worker_last_run'");if($row)query("UPDATE shop_meta SET meta_value=? WHERE meta_key='worker_last_run'",[(string)time()]);else query("INSERT INTO shop_meta (meta_key,meta_value) VALUES ('worker_last_run',?)",[(string)time()]);
    return $count;
}

function retry_provisioning_job(int $jobId): void {
    $job=one('SELECT j.*,o.payment_status,o.paypal_mode FROM shop_jobs j JOIN shop_orders o ON o.id=j.order_id WHERE j.id=?',[$jobId]);
    $safe=['start','client_ready','website_ready','ftp_ready','clone_wait','clone_ready','configuring','configured','disk_resize_wait','lxc_create_wait','lxc_ready','checkout_ready','zone_wait'];
    if(!$job || !order_payment_allows_provisioning(one('SELECT * FROM shop_orders WHERE id=?',[$job['order_id']])) || $job['state']!=='needs_action' || !in_array($job['phase'],$safe,true))throw new RuntimeException('Dieser Auftrag kann nicht automatisch wiederholt werden. Bitte den Anbieterzustand prüfen und eine vorhandene Ressource zuordnen.');
    $changed=query("UPDATE shop_jobs SET state='waiting',last_error=NULL WHERE id=? AND state='needs_action' AND locked_until<?",[$jobId,time()]);
    if($changed->rowCount()!==1)throw new RuntimeException('Der Auftrag wird bereits bearbeitet.');
    query("UPDATE shop_orders SET fulfillment_status='queued' WHERE id=?",[$job['order_id']]);audit_event('provision.retry',(string)$job['order_id']);
}
function resolve_provisioning_job(int $jobId,int $serviceId): void {
    $job=one('SELECT * FROM shop_jobs WHERE id=?',[$jobId]);$order=$job?one('SELECT * FROM shop_orders WHERE id=?',[$job['order_id']]):null;
    if(!$job || !$order || $job['state']!=='needs_action' || !order_payment_allows_provisioning($order))throw new RuntimeException('Dieser Auftrag ist nicht für eine manuelle Zuordnung freigegeben.');
    $service=bound_service($serviceId,(int)$order['customer_id'],true);
    if($service['provider']!==$order['provider'] || (!empty($service['meta']['order_id']) && (int)$service['meta']['order_id']!==(int)$order['id']))throw new RuntimeException('Diese Ressource passt nicht zu diesem Auftrag.');
    $api=provider_client($service['provider']);
    if($service['provider']==='ispconfig'){
        $site=hosting_site($service,$api);if($site['domain']!==$order['domain'])throw new RuntimeException('Die Website gehört zu einer anderen Domain.');
    }elseif($service['provider']==='ovh'){
        if($service['meta']['zone']!==$order['domain'])throw new RuntimeException('Die DNS-Zone passt nicht zur Bestellung.');$api->call('GET','/domain/zone/'.rawurlencode($order['domain']));
    }elseif(!$api->poolContains($service['meta']['node'],(int)$service['meta']['vmid']))throw new RuntimeException('Die VM ist nicht im Shop-Pool.');
    $meta=$service['meta'];$oc=json_decode($order['configuration'],true);$meta['provisioning_notice']=$oc['provisioning_notice']??'';$meta['dns_notice']=$oc['dns_notice']??commerce_settings()['notice_dns'];$meta['order_id']=(int)$order['id'];
    db()->beginTransaction();try{
        $changed=query("UPDATE shop_jobs SET state='done',last_error=NULL WHERE id=? AND state='needs_action' AND locked_until<?",[$jobId,time()]);if($changed->rowCount()!==1)throw new RuntimeException('Der Auftrag wurde zwischenzeitlich geändert.');
        query('UPDATE shop_resource_links SET metadata=? WHERE service_id=?',[json_encode($meta),$serviceId]);query("UPDATE shop_orders SET fulfillment_status='active' WHERE id=?",[$order['id']]);admin_order_note_active($order);commerce_event('service:'.$serviceId,'service.ready',['order_id'=>(int)$order['id']]);db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    audit_event('provision.manually_resolved',$order['reference']);
}
