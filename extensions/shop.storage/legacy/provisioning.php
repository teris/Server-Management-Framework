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
        $category=['ispconfig'=>'hosting','proxmox'=>'server','ovh'=>'domain','manual'=>'managed'][$order['provider']];
        $target=provisioning_target_service($order,$job);
        if($target){$serviceId=(int)$target['id'];query("UPDATE shop_services SET status='active',details=?,price_cents=?,billing=? WHERE id=?",['Bereitgestellt aus Bestellung '.$order['reference'],$order['amount_cents'],$order['billing'],$serviceId]);}
        else{query("INSERT INTO shop_services (customer_id,name,category,identifier,status,details,price_cents,billing) VALUES (?,?,?,?,'active',?,?,?)",[$order['customer_id'],$order['product_name'],$category,$identifier,'Bereitgestellt aus Bestellung '.$order['reference'],$order['amount_cents'],$order['billing']]);$serviceId=(int)db()->lastInsertId();}
        $oc=json_decode($order['configuration'],true);if($order['provider']==='proxmox')$metadata['ownership_marker']='Server Management '.$order['reference'];$metadata['test_mode']=test_order($order);$metadata['modules']=$oc['modules']??[];$metadata['provisioning_notice']=$oc['provisioning_notice']??'';$metadata['dns_notice']=$oc['dns_notice']??commerce_settings()['notice_dns'];$metadata['order_id']=(int)$order['id'];$metadata['connection_id']=$binding;
        if($order['provider']==='proxmox'){$type=($metadata['type']??'qemu')==='lxc'?'lxc':'qemu';$metadata=SMF\Core\ShopResourceBindings::physical($metadata,(new ProxmoxProvider())->vmConfig($metadata['node'],(int)$metadata['vmid'],$type),$type);}
        SMF\Core\ShopResourceBindings::grant($serviceId,(int)$order['customer_id'],$order['provider'],$metadata);
        query('INSERT INTO shop_resource_links (service_id,provider,external_id,metadata,credentials,verified_at) VALUES (?,?,?,?,?,CURRENT_TIMESTAMP)',[$serviceId,$order['provider'],$external,json_encode($metadata),$credentials?secret_seal($credentials,'service:'.$serviceId):null]);
        foreach($children as $child){query('INSERT INTO shop_hosting_children (service_id,kind,external_id,name,metadata) VALUES (?,?,?,?,?)',[$serviceId,$child['kind'],(string)$child['external_id'],$child['name'],json_encode(['state'=>'active']+($child['metadata']??[]))]);if($credentials && ($credentials['username']??'')===$child['name'])save_child_credentials((int)db()->lastInsertId(),$credentials);}
        query("UPDATE shop_jobs SET state='done',last_error=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=? AND lock_token=?",[$job['id'],$job['lock_token']]);
        query('DELETE FROM shop_storage_reservations WHERE job_id=?',[$job['id']]);
        query("UPDATE shop_orders SET fulfillment_status='active',updated_at=CURRENT_TIMESTAMP WHERE id=?",[$order['id']]);admin_order_note_active($order);commerce_event('service:'.$serviceId,'service.ready',['order_id'=>(int)$order['id']]);db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    queue_default_dns($order);
    audit_event('provision.completed',$order['reference']);
}
function provision_ispconfig(array $order,array &$job,array $profile,array $data): void {
    provisioning_target_service($order,$job);
    if(in_array($job['phase'],['start','client_ready'],true) && !hosting_domain_provisioning_gate($order,$job,$data))return;
    $api=new IspconfigProvider();$config=integration_config('ispconfig');$customer=one('SELECT * FROM customers WHERE id=?',[$order['customer_id']]);$domain=valid_domain($order['domain']);
    if(!$domain)throw new RuntimeException('Für die Webspace-Einrichtung fehlt eine Domain.');
    if(in_array($job['phase'],['start','client_ready'],true) && isset($profile['website_features_present'])){hosting_capabilities(true);hosting_website_feature_params($profile,true);}
    if($job['phase']==='start'){
        $account=one("SELECT * FROM shop_provider_accounts WHERE customer_id=? AND provider='ispconfig'",[$order['customer_id']]);
        if($account){
            $data['client_id']=(int)$account['external_id'];$client=$api->client($data['client_id']);
            if(($client['customer_no']??'')==='OC-'.$customer['id']){
                $params=hosting_account_limits((int)$customer['id'],$data['client_id'],$api,$profile);$params['password']='';
                if(!$api->call('client_update',['client_id'=>$data['client_id'],'reseller_id'=>(int)($client['parent_client_id']??0),'params'=>$params]))throw new RuntimeException('Die ISPConfig-Paketlimits konnten nicht aktualisiert werden.');
            }
        }
        else{
            // Persist intent before a non-idempotent remote call. An ambiguous failure
            // needs operator review rather than creating the customer a second time.
            $panelCredentials=['username'=>'oc'.$customer['id'],'password'=>'Oc!'.bin2hex(random_bytes(20))];
            $data['client_credentials']=secret_seal($panelCredentials,'job:'.$job['id'].':ispconfig');save_job_phase($job,'creating_client',$data,'running');
            $clientId=(int)$api->call('client_add',['reseller_id'=>0,'params'=>[
                'username'=>$panelCredentials['username'],'password'=>$panelCredentials['password'],'contact_name'=>$customer['first_name'].' '.$customer['last_name'],'company_name'=>$customer['company']??'','email'=>$customer['email'],'street'=>$customer['address']??'','zip'=>$customer['postal_code']??'','city'=>$customer['city']??'','country'=>strtoupper($customer['country']??'DE'),'language'=>'de','customer_no'=>'OC-'.$customer['id'],
                'default_webserver'=>(int)$config['server_id'],'default_mailserver'=>(int)$config['mail_server_id'],'default_dbserver'=>(int)$config['db_server_id'],'modules'=>'dashboard,sites,mail,tools','startmodule'=>'dashboard',
            ]+hosting_profile_client_limits($profile)]);
            if($clientId<1)throw new ProviderFailure('ispconfig');
            $data['client_id']=$clientId;query("INSERT INTO shop_provider_accounts (customer_id,provider,external_id) VALUES (?,'ispconfig',?)",[$customer['id'],(string)$clientId]);
            save_hosting_panel_login((int)$customer['id'],$clientId,$panelCredentials);
        }
        save_job_phase($job,'client_ready',$data);
    }
    if($job['phase']==='client_ready'){
        $exists=$api->call('sites_web_domain_get',['primary_id'=>['domain'=>$domain]]);
        if($exists)throw new RuntimeException('Die Domain existiert bereits in ISPConfig. Bitte manuell zuordnen.');
        $features=hosting_website_feature_params($profile,isset($profile['website_features_present']));
        save_job_phase($job,'creating_website',$data,'running');
        $id=(int)$api->call('sites_web_domain_add',['client_id'=>$data['client_id'],'readonly'=>false,'params'=>array_replace([
            'server_id'=>(int)$config['server_id'],'ip_address'=>infrastructure_settings()['web_ipv4'],'domain'=>$domain,'type'=>'vhost','parent_domain_id'=>0,'vhost_type'=>'name','http_port'=>80,'https_port'=>443,'hd_quota'=>(int)$profile['storage_mb'],'traffic_quota'=>-1,'cgi'=>!empty($profile['cgi_enabled'])?'y':'n','ssi'=>'n','suexec'=>'y','php'=>($profile['php_enabled']??1)?'php-fpm':'no','ruby'=>'n','active'=>'y','ssl'=>'n','ssl_letsencrypt'=>'n','subdomain'=>'www','errordocs'=>1,'allow_override'=>'All','apache_directives'=>'','nginx_directives'=>'','custom_php_ini'=>'','pm'=>'ondemand','pm_max_children'=>5,'pm_max_requests'=>500,'pm_process_idle_timeout'=>10,'backup_interval'=>'none','backup_copies'=>1,'document_root'=>'','system_user'=>'','system_group'=>'',
        ],$features)]);
        if($id<1)throw new ProviderFailure('ispconfig');$data['website_id']=$id;query("UPDATE shop_hosting_domain_verifications SET activated_ts=?,state='active' WHERE order_id=?",[time(),$order['id']]);save_job_phase($job,'website_ready',$data);
    }
    if(in_array($job['phase'],['website_ready','ftp_ready'],true)){
        $site=$api->website((int)$data['website_id']);
        if(($site['domain']??'')!==$domain || $api->clientIdForGroup((int)$site['sys_groupid'])!==(int)$data['client_id'])throw new RuntimeException('Die erzeugte Website konnte nicht eindeutig zugeordnet werden.');
        // Every website owns a mail domain and signing key; mailbox quotas stay independent.
        $mail=$api->mailDomain($domain);
        if($mail && (int)$mail['sys_groupid']!==(int)$site['sys_groupid'])throw new RuntimeException('Die Maildomain gehört zu einem anderen ISPConfig-Kunden.');
        if(!$mail){save_job_phase($job,'creating_maildomain',$data,'running');$api->call('mail_domain_add',['client_id'=>$data['client_id'],'params'=>['server_id'=>(int)$config['mail_server_id'],'domain'=>$domain,'active'=>'y']]);save_job_phase($job,'website_ready',$data);}
        ensure_hosting_dkim($api,(int)$order['customer_id'],(int)$data['client_id'],(int)$site['sys_groupid'],$domain);
        $credentials=null;$children=[];
        if(!empty($profile['create_ftp']) && (int)$profile['ftp_limit']>0){
            if(empty($data['ftp_id'])){
                if(empty($site['document_root']) || empty($site['system_user']) || empty($site['system_group'])){save_job_phase($job,'website_ready',$data);return;}
                $credentials=['username'=>'c'.$data['client_id'].'o'.$order['id'].'_web','password'=>bin2hex(random_bytes(18)),'host'=>$domain];
                $data['ftp_credentials']=secret_seal($credentials,'job:'.$job['id'].':ftp');save_job_phase($job,'creating_ftp',$data,'running');
                $data['ftp_id']=(int)$api->call('sites_ftp_user_add',['client_id'=>$data['client_id'],'params'=>['server_id'=>(int)$site['server_id'],'parent_domain_id'=>(int)$site['domain_id'],'username'=>$credentials['username'],'password'=>$credentials['password'],'quota_size'=>(int)$site['hd_quota'],'active'=>'y','uid'=>$site['system_user'],'gid'=>$site['system_group'],'dir'=>$site['document_root'],'quota_files'=>-1,'ul_ratio'=>-1,'dl_ratio'=>-1,'ul_bandwidth'=>-1,'dl_bandwidth'=>-1]]);
                if($data['ftp_id']<1)throw new ProviderFailure('ispconfig');save_job_phase($job,'ftp_ready',$data);
            }
            $credentials=secret_open($data['ftp_credentials'],'job:'.$job['id'].':ftp');$children[]=['kind'=>'ftp','external_id'=>$data['ftp_id'],'name'=>$credentials['username']];
        }
        finish_provisioning($order,$job,'website/'.$data['website_id'],['website_id'=>$data['website_id'],'client_id'=>$data['client_id'],'group_id'=>(int)$site['sys_groupid'],'domain'=>$domain,'limits'=>['ftp'=>(int)$profile['ftp_limit'],'database'=>(int)$profile['database_limit'],'mail'=>(int)$profile['mail_limit'],'mail_quota_mb'=>(int)$profile['mail_quota_mb'],'cron'=>(int)($profile['cron_limit']??0)],'storage_mb'=>(int)$profile['storage_mb'],'features'=>normalized_hosting_features($profile,$profile),'modules'=>$profile['module_selection']??[]],$domain,$credentials,$children);
    }
}
function provision_proxmox(array $order,array &$job,array $profile,array $data): void {
    if(!proxmox_has_resource_target())throw new RuntimeException('Proxmox-Bereitstellung benötigt Ziel-Node und Shop-Ressourcenpool.');
    $api=new ProxmoxProvider();$config=integration_config('proxmox');$node=$config['node'];$templateNode=$profile['template_node']?:$node;
    if($job['phase']==='start'){
        $template=$api->vmConfig($templateNode,(int)$profile['template_vmid']);if(empty($template['template']))throw new RuntimeException('Die konfigurierte VM ist kein Proxmox-Template.');
        $storage=reserve_vps_storage($job,$node,'qemu',vps_storage_required($template,(int)($profile['disk_extra_gb']??0)),$api);
        $vmid=(int)$api->call('GET','/cluster/nextid');if($vmid<100)throw new ProviderFailure('proxmox');
        $data=['vmid'=>$vmid,'node'=>$node,'storage'=>$storage,'name'=>'oc-'.$order['id']]+proxmox_disk_configuration($template,(int)($profile['disk_extra_gb']??0));save_job_phase($job,'cloning',$data,'running');
        $clone=['newid'=>$vmid,'name'=>$data['name'],'full'=>1,'target'=>$node,'pool'=>$config['pool'],'description'=>'Server Management '.$order['reference']];$clone['storage']=$data['storage'];
        $data['task']=(string)$api->call('POST','/nodes/'.rawurlencode($templateNode).'/qemu/'.(int)$profile['template_vmid'].'/clone',$clone);
        $data['task_node']=$templateNode;if(!$data['task'])throw new ProviderFailure('proxmox');save_job_phase($job,'clone_wait',$data);return;
    }
    if($job['phase']==='clone_wait'){
        $task=$api->call('GET','/nodes/'.rawurlencode($data['task_node']).'/tasks/'.rawurlencode($data['task']).'/status');
        if(($task['status']??'')!=='stopped'){save_job_phase($job,'clone_wait',$data);return;}
        if(($task['exitstatus']??'')!=='OK')throw new RuntimeException('Proxmox hat das Klonen nicht erfolgreich abgeschlossen. Bitte Aufgabe prüfen.');
        save_job_phase($job,'clone_ready',$data);
    }
    if($job['phase']==='clone_ready'){
        $data['password']=bin2hex(random_bytes(16));
        // Generated login credentials stay encrypted even while a VM is being configured.
        $saved=$data;unset($saved['password']);$saved['credentials']=secret_seal(['username'=>$profile['cloud_user'],'password'=>$data['password']],'job:'.$job['id']);
        save_job_phase($job,'configuring',$saved,'running');
        $data=$saved;
    }
    if($job['phase']==='configuring'){
        $credentials=secret_open($data['credentials'],'job:'.$job['id']);
        $api->call('PUT','/nodes/'.rawurlencode($node).'/qemu/'.$data['vmid'].'/config',['cores'=>(int)$profile['cores'],'memory'=>(int)$profile['memory_mb'],'ciuser'=>$profile['cloud_user'],'cipassword'=>$credentials['password'],'ipconfig0'=>$profile['ipconfig0']]);
        save_job_phase($job,'configured',$data);
    }
    if($job['phase']==='disk_resize_wait'){
        $task=$api->call('GET','/nodes/'.rawurlencode($node).'/tasks/'.rawurlencode($data['disk_resize_task']).'/status');if(($task['status']??'')!=='stopped'){save_job_phase($job,'disk_resize_wait',$data);return;}if(($task['exitstatus']??'')!=='OK')throw new RuntimeException('Proxmox hat die Plattenvergrößerung nicht erfolgreich bestätigt.');$vm=$api->vmConfig($node,(int)$data['vmid']);if(proxmox_disk_gb($vm[$data['disk_device']]??'')<(int)$data['disk_target_gb'])throw new RuntimeException('Die bestätigte Plattengröße stimmt nicht mit der Bestellung überein.');$data['disk_done']=true;save_job_phase($job,'configured',$data);
    }
    if($job['phase']==='configured'){
        if(!provision_proxmox_disk($order,$job,$data,$api,$node))return;
        $vm=$api->vmConfig($node,(int)$data['vmid']);if(!$api->poolContains($node,(int)$data['vmid']) || ($vm['name']??'')!==$data['name'])throw new RuntimeException('Die neue VM konnte nicht eindeutig dem Auftrag zugeordnet werden.');
        if(!empty($profile['start_after_clone']) && ($api->vm($node,(int)$data['vmid'])['status']??'')!=='running'){$api->control($node,(int)$data['vmid'],'start');}
        $credentials=secret_open($data['credentials'],'job:'.$job['id']);
        finish_provisioning($order,$job,'qemu/'.$data['vmid'],['node'=>$node,'vmid'=>(int)$data['vmid'],'storage'=>$data['storage']??$profile['storage']],$data['name'],$credentials);
    }
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
            if($intent=module_order_intent((int)$order['id']))provision_module_addon($order,$job,$intent);else match($order['provider']){'ispconfig'=>provision_ispconfig($order,$job,$profile,$data),'proxmox'=>(($profile['virtualization']??'qemu')==='lxc'?provision_lxc($order,$job,$profile,$data):provision_proxmox($order,$job,$profile,$data)),'ovh'=>provision_ovh($order,$job,$profile,$data),'manual'=>provision_manual($order,$job),default=>throw new RuntimeException('Keine Bereitstellung für diesen Anbieter.')};
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
