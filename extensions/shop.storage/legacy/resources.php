<?php
function bound_service(int $id,?int $customerId=null,bool $write=false): array {
    $sql='SELECT s.*,l.provider,l.external_id,l.metadata,l.credentials FROM shop_services s JOIN shop_resource_links l ON l.service_id=s.id WHERE s.id=?';$args=[$id];
    if($customerId!==null){$sql.=' AND s.customer_id=?';$args[]=$customerId;}
    $service=one($sql,$args);
    if(!$service)throw new RuntimeException('Dieses Produkt ist für dein Konto nicht verfügbar.');
    if($write && $service['status']!=='active')throw new RuntimeException('Dieses Produkt ist derzeit nicht für Änderungen freigeschaltet.');
    $service['meta']=json_decode($service['metadata'],true,32,JSON_THROW_ON_ERROR);
    if(!hash_equals($service['meta']['connection_id']??'',integration_identity($service['provider'])))throw new RuntimeException('Die Anbieter-Verbindung wurde geändert. Bitte die Ressourcenzuordnung im Adminbereich erneut prüfen.');
    SMF\Core\ShopResourceBindings::authorize($service,$customerId,$write);
    return $service;
}
function resource_mutex(int $id,callable $callback): mixed {
    try{query('INSERT INTO shop_resource_locks (service_id) VALUES (?)',[$id]);}catch(PDOException $e){if(!one('SELECT service_id FROM shop_resource_locks WHERE service_id=?',[$id]))throw $e;}
    $token=bin2hex(random_bytes(16));$now=time();
    $lock=query('UPDATE shop_resource_locks SET lock_token=?,locked_until=? WHERE service_id=? AND locked_until<?',[$token,$now+180,$id,$now]);
    if($lock->rowCount()!==1)throw new RuntimeException('Für dieses Produkt läuft bereits eine Änderung. Bitte warte einen Moment.');
    try{return $callback();}finally{query('UPDATE shop_resource_locks SET locked_until=0,lock_token=NULL WHERE service_id=? AND lock_token=?',[$id,$token]);}
}
function validate_dns_record(array $input,bool $allowNameserver=false): array {
    $type=strtoupper(trim((string)($input['type']??'')));$name=trim((string)($input['subdomain']??''));$target=trim((string)($input['target']??''));$ttl=(string)($input['ttl']??'3600');
    if($name==='@')$name='';
    if(!in_array($type,$allowNameserver?['A','AAAA','CNAME','MX','TXT','SRV','CAA','NS']:['A','AAAA','CNAME','MX','TXT','SRV','CAA'],true) || strlen($name)>253 || ($name!=='' && $name!=='*' && !preg_match('/^(?:\*\.)?[a-zA-Z0-9_](?:[a-zA-Z0-9_.-]*[a-zA-Z0-9_])?$/D',$name)))throw new RuntimeException('Bitte einen gültigen DNS-Typ und Hostnamen eintragen.');
    if(!preg_match('/^\d{2,6}$/D',$ttl) || (int)$ttl<60 || (int)$ttl>86400)throw new RuntimeException('Die TTL muss zwischen 60 und 86400 Sekunden liegen.');
    if($target==='' || strlen($target)>2048 || preg_match('/[\r\n\x00]/',$target))throw new RuntimeException('Bitte ein gültiges DNS-Ziel eintragen.');
    if($type==='A' && !filter_var($target,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4))throw new RuntimeException('Für einen A-Eintrag wird eine IPv4-Adresse benötigt.');
    if($type==='AAAA' && !filter_var($target,FILTER_VALIDATE_IP,FILTER_FLAG_IPV6))throw new RuntimeException('Für einen AAAA-Eintrag wird eine IPv6-Adresse benötigt.');
    if(in_array($type,['CNAME','NS'],true))$target=valid_domain(rtrim($target,'.')).'.';
    if($type==='MX'){
        if(!preg_match('/^(\d{1,5})\s+(\S+)$/D',$target,$m) || (int)$m[1]>65535)throw new RuntimeException('MX-Ziel: Priorität und Mailserver, zum Beispiel 10 mail.example.de.');
        $target=(int)$m[1].' '.valid_domain(rtrim($m[2],'.')).'.';
    }
    if($type==='SRV'){
        if(!preg_match('/^(\d{1,5})\s+(\d{1,5})\s+(\d{1,5})\s+(\S+)$/D',$target,$m) || max((int)$m[1],(int)$m[2],(int)$m[3])>65535)throw new RuntimeException('SRV-Ziel: Priorität, Gewicht, Port und Zielhost.');
        $target=(int)$m[1].' '.(int)$m[2].' '.(int)$m[3].' '.valid_domain(rtrim($m[4],'.')).'.';
    }
    if($type==='CAA' && !preg_match('/^(0|128)\s+(issue|issuewild|iodef)\s+"[^"\r\n]+"$/D',$target))throw new RuntimeException('CAA-Ziel: zum Beispiel 0 issue "letsencrypt.org".');
    if($type==='TXT' && !str_starts_with($target,'"'))$target='"'.str_replace(['\\','"'],['\\\\','\\"'],$target).'"';
    return ['fieldType'=>$type,'subDomain'=>strtolower($name),'target'=>$target,'ttl'=>(int)$ttl];
}
function assign_existing_resource(int $serviceId,string $provider,string $external,array $options): void {
    db()->beginTransaction();try{assign_existing_resource_impl($serviceId,$provider,$external,$options);db()->commit();}catch(Throwable $error){if(db()->inTransaction())db()->rollBack();throw $error;}
}
function assign_existing_resource_impl(int $serviceId,string $provider,string $external,array $options): void {
    $service=one('SELECT * FROM shop_services WHERE id=?',[$serviceId]);if(!$service)throw new RuntimeException('Produkt nicht gefunden.');
    $client=provider_client($provider);$meta=[];
    if($provider==='ovh'){
        if($service['category']!=='domain')throw new RuntimeException('OVH-DNS-Zonen werden einem Domain-Produkt zugeordnet.');
        $domain=valid_domain($external);$zone=$client->call('GET','/domain/zone/'.rawurlencode($domain));
        if(!$domain || !is_array($zone))throw new RuntimeException('DNS-Zone nicht gefunden.');
        $external=$domain;$meta=['zone'=>$domain];
    }elseif($provider==='proxmox'){
        if($service['category']!=='server' || !preg_match('/^\d+$/D',$external) || (int)$external<100)throw new RuntimeException('Bitte ein Server-Produkt und eine gültige QEMU-VMID auswählen.');
        $node=trim($options['node']??'')?:integration_config('proxmox')['node'];
        if(!preg_match('/^[a-zA-Z0-9_.-]+$/D',$node))throw new RuntimeException('Ungültiger Node.');
        $vmid=(int)$external;$vm=$client->vmConfig($node,$vmid);
        if(!empty($vm['template']) || !$client->poolContains($node,$vmid))throw new RuntimeException('Die VM muss eine reguläre VM im konfigurierten Shop-Pool sein.');
        $external='qemu/'.$vmid;$meta=SMF\Core\ShopResourceBindings::physical(['node'=>$node,'vmid'=>$vmid],$vm,'qemu');
    }elseif($provider==='ispconfig'){
        if($service['category']!=='hosting' || !preg_match('/^\d+$/D',$external))throw new RuntimeException('Bitte ein Hosting-Produkt und eine numerische Website-ID auswählen.');
        $site=$client->website((int)$external);$owner=$client->clientIdForGroup((int)$site['sys_groupid']);
        if($owner<1 || ($site['type']??'')!=='vhost')throw new RuntimeException('Die Website muss einem regulären ISPConfig-Kunden gehören.');
        $assigned=one("SELECT customer_id FROM shop_provider_accounts WHERE provider='ispconfig' AND external_id=?",[(string)$owner]);
        if($assigned && (int)$assigned['customer_id']!==(int)$service['customer_id'])throw new RuntimeException('Dieser ISPConfig-Kunde ist bereits einem anderen Kundenkonto zugeordnet.');
        $external='website/'.(int)$external;
        $meta=['website_id'=>(int)$site['domain_id'],'client_id'=>$owner,'domain'=>$site['domain'],'group_id'=>(int)$site['sys_groupid'],'limits'=>[
          'ftp'=>max(0,min(100,(int)($options['ftp_limit']??5))),'database'=>max(0,min(100,(int)($options['database_limit']??5))),'mail'=>max(0,min(100,(int)($options['mail_limit']??10))),'mail_quota_mb'=>max(1,min(102400,(int)($options['mail_quota_mb']??1024)))]];
        $primary=one("SELECT * FROM shop_provider_accounts WHERE customer_id=? AND provider='ispconfig'",[$service['customer_id']]);
        if(!$primary)query("INSERT INTO shop_provider_accounts (customer_id,provider,external_id) VALUES (?,'ispconfig',?)",[$service['customer_id'],(string)$owner]);
    }else throw new RuntimeException('Dieser Anbieter kann keinem Produkt zugeordnet werden.');
    if(one('SELECT service_id FROM shop_resource_links WHERE service_id=?',[$serviceId]))throw new RuntimeException('Das Produkt ist bereits verbunden. Bitte die bestehende Zuordnung zuerst prüfen.');
    if(one('SELECT service_id FROM shop_resource_links WHERE provider=? AND external_id=?',[$provider,$external]))throw new RuntimeException('Diese Ressource ist bereits einem Produkt zugeordnet.');
    $meta['connection_id']=integration_identity($provider);
    query('INSERT INTO shop_resource_links (service_id,provider,external_id,metadata,verified_at) VALUES (?,?,?,?,CURRENT_TIMESTAMP)',[$serviceId,$provider,$external,json_encode($meta)]);
    SMF\Core\ShopResourceBindings::grant($serviceId,(int)$service['customer_id'],$provider,$meta,$service['status']==='active');
    audit_event('resource.assigned',(string)$serviceId);
}
function hosting_site(array $service,IspconfigProvider $api): array {
    $site=$api->website((int)$service['meta']['website_id']);
    if(($site['domain']??'')!==$service['meta']['domain'] || (int)($site['sys_groupid']??0)!==(int)$service['meta']['group_id'])throw new RuntimeException('Die Hosting-Zuordnung hat sich beim Anbieter geändert. Bitte den Support kontaktieren.');
    return $site;
}
function hosting_child_create(array $service,string $kind,array $input): void {
    if(!in_array($kind,['ftp','database','mail'],true))throw new RuntimeException('Unbekannte Hosting-Funktion.');
    resource_mutex((int)$service['id'],function()use($service,$kind,$input){
        $service=module_future_resource_limits(bound_service((int)$service['id'],(int)$service['customer_id'],true));
        $api=new IspconfigProvider();$site=hosting_site($service,$api);$meta=$service['meta'];$clientId=(int)$meta['client_id'];
        $used=hosting_child_usage((int)$service['id'],$kind);
        if($used>=(int)($meta['limits'][$kind]??0))throw new RuntimeException('Das Limit für diese Funktion ist erreicht. Bitte den Support kontaktieren.');
        $name=strtolower(trim((string)($input['name']??'')));$password=(string)($input['new_password']??'');
        $validName=$kind==='mail'?preg_match('/^[a-z0-9](?:[a-z0-9._+-]{0,62}[a-z0-9])?$/D',$name) && !str_contains($name,'..'):preg_match('/^[a-z][a-z0-9_]{1,15}$/D',$name);
        if(!$validName)throw new RuntimeException($kind==='mail'?'Bitte einen gültigen E-Mail-Namen vor dem @ mit 1–64 Zeichen eintragen.':'Bitte einen Namen mit 2–16 Kleinbuchstaben, Zahlen oder Unterstrichen verwenden.');
        validate_service_password($password);$mailName=trim((string)($input['display_name']??$name));$mailQuota=(string)($input['quota_mb']??$meta['limits']['mail_quota_mb']??1024);
        if($kind==='mail' && (mb_strlen($mailName)>100 || preg_match('/[\x00-\x1f]/',$mailName) || !preg_match('/^[0-9]{1,9}$/D',$mailQuota) || (int)$mailQuota<1 || (int)$mailQuota>(int)$meta['limits']['mail_quota_mb']))throw new RuntimeException('Bitte einen gültigen Anzeigenamen und eine Postfachgröße innerhalb deines Paketlimits eintragen.');
        $full=$kind==='mail'?$name.'@'.$meta['domain']:'c'.$clientId.'s'.$service['id'].'_'.$name;
        if(one('SELECT id FROM shop_hosting_children WHERE service_id=? AND name=?',[$service['id'],$full]))throw new RuntimeException('Dieser Name ist bereits vergeben oder wird gerade angelegt.');
        $pending='pending-'.bin2hex(random_bytes(12));
        query('INSERT INTO shop_hosting_children (service_id,kind,external_id,name,metadata) VALUES (?,?,?,?,?)',[$service['id'],$kind,$pending,$full,json_encode(['state'=>'creating'])]);$childId=(int)db()->lastInsertId();$extra=[];
        try{
            if($kind==='ftp'){
                $id=$api->call('sites_ftp_user_add',['client_id'=>$clientId,'params'=>['server_id'=>(int)$site['server_id'],'parent_domain_id'=>(int)$site['domain_id'],'username'=>$full,'password'=>$password,'quota_size'=>(int)$site['hd_quota'],'active'=>'y','uid'=>$site['system_user'],'gid'=>$site['system_group'],'dir'=>$site['document_root'],'quota_files'=>-1,'ul_ratio'=>-1,'dl_ratio'=>-1,'ul_bandwidth'=>-1,'dl_bandwidth'=>-1]]);
            }elseif($kind==='database'){
                $config=integration_config('ispconfig');
                $dbUser=(int)$api->call('sites_database_user_add',['client_id'=>$clientId,'params'=>['server_id'=>(int)$config['db_server_id'],'database_user'=>$full,'database_password'=>$password]]);
                if($dbUser<1)throw new ProviderFailure('ispconfig');
                $extra['database_user_id']=$dbUser;$extra['database_user']=$full;
                query('UPDATE shop_hosting_children SET metadata=? WHERE id=?',[json_encode(['state'=>'creating']+$extra),$childId]);
                $id=$api->call('sites_database_add',['client_id'=>$clientId,'params'=>['server_id'=>(int)$config['db_server_id'],'type'=>'mysql','parent_domain_id'=>(int)$site['domain_id'],'database_name'=>$full,'database_user_id'=>$dbUser,'database_ro_user_id'=>0,'database_charset'=>'utf8mb4','remote_access'=>'n','remote_ips'=>'','backup_interval'=>'none','backup_copies'=>1,'active'=>'y']]);
            }else{
                $mailDomain=$api->mailDomain($meta['domain']);
                if(empty($mailDomain['domain_id']) || (int)($mailDomain['sys_groupid']??0)!==(int)$meta['group_id'])throw new RuntimeException('Für diese Website ist noch keine eigene Maildomain eingerichtet. Bitte den Support kontaktieren.');
                ensure_hosting_dkim($api,(int)$service['customer_id'],$clientId,(int)$site['sys_groupid'],$meta['domain']);
                $quota=(int)$mailQuota;$extra=['display_name'=>$mailName,'quota_mb'=>$quota];
                $id=$api->call('mail_user_add',['client_id'=>$clientId,'params'=>['server_id'=>(int)$mailDomain['server_id'],'email'=>$full,'login'=>$full,'password'=>$password,'name'=>$mailName,'quota'=>$quota*1048576,'homedir'=>'/var/vmail','autoresponder'=>'n','autoresponder_days'=>0,'purge_trash_days'=>0,'purge_junk_days'=>0,'move_junk'=>'y','postfix'=>'y','access'=>'y','disableimap'=>'n','disablepop3'=>'n','disabledeliver'=>'n','disablesmtp'=>'n']]);
            }
            if((int)$id<1)throw new ProviderFailure('ispconfig');
            query('UPDATE shop_hosting_children SET external_id=?,metadata=? WHERE id=?',[(string)$id,json_encode(['state'=>'active']+$extra),$childId]);save_child_credentials($childId,['username'=>$full,'password'=>$password]);audit_event('hosting.create.'.$kind,(string)$service['id']);
        }catch(Throwable $e){query('UPDATE shop_hosting_children SET metadata=? WHERE id=?',[json_encode(['state'=>'needs_action']+$extra),$childId]);audit_event('hosting.create.'.$kind,(string)$service['id'],'needs_action');throw $e;}
    });
}
function hosting_child_delete(array $service,int $childId): void {
    resource_mutex((int)$service['id'],function()use($service,$childId){
        $child=one('SELECT * FROM shop_hosting_children WHERE id=? AND service_id=?',[$childId,$service['id']]);
        if(!$child || !preg_match('/^\d+$/D',$child['external_id']))throw new RuntimeException('Dieser Zugang muss durch den Support geprüft werden.');
        $api=new IspconfigProvider();$site=hosting_site($service,$api);
        $method=['ftp'=>'sites_ftp_user','database'=>'sites_database','mail'=>'mail_user'][$child['kind']]??throw new RuntimeException('Ungültiger Zugang.');
        $remote=$api->call($method.'_get',['primary_id'=>(int)$child['external_id']]);
        if((int)($remote['sys_groupid']??0)!==(int)$service['meta']['group_id'])throw new RuntimeException('Die Zuordnung dieses Zugangs muss geprüft werden.');
        if($child['kind']!=='mail' && (int)($remote['parent_domain_id']??0)!==(int)$site['domain_id'])throw new RuntimeException('Dieser Zugang gehört nicht zu dieser Website.');
        if($child['kind']==='mail' && ($remote['email']??'')!==$child['name'])throw new RuntimeException('Dieses Postfach wurde beim Anbieter geändert.');
        if(!$api->call($method.'_delete',['primary_id'=>(int)$child['external_id']]))throw new RuntimeException('ISPConfig hat das Löschen dieses Zugangs nicht bestätigt.');
        query('DELETE FROM shop_hosting_children WHERE id=? AND service_id=?',[$childId,$service['id']]);audit_event('hosting.delete.'.$child['kind'],(string)$service['id']);
        if($child['kind']==='ftp' && $service['credentials']){$credentials=secret_open($service['credentials'],'service:'.$service['id']);if(($credentials['username']??'')===$child['name'])query('UPDATE shop_resource_links SET credentials=NULL WHERE service_id=?',[$service['id']]);}
        // A database user may be shared externally; it is deliberately not deleted.
    });
}
function validate_service_password(string $password): void {
    if(strlen($password)<12 || strlen($password)>128 || str_contains($password,"\0"))throw new RuntimeException('Bitte ein Passwort mit 12–128 Zeichen verwenden.');
}
function hosting_child_password(array $service,int $childId,string $password): void {
    validate_service_password($password);
    resource_mutex((int)$service['id'],function()use($service,$childId,$password){
        $child=one('SELECT * FROM shop_hosting_children WHERE id=? AND service_id=?',[$childId,$service['id']]);
        if(!$child || !preg_match('/^\d+$/D',$child['external_id']))throw new RuntimeException('Dieser Zugang ist für dein Produkt nicht verfügbar.');
        $api=new IspconfigProvider();$site=hosting_site($service,$api);$metadata=json_decode($child['metadata'],true);
        $prefix=['ftp'=>'sites_ftp_user','database'=>'sites_database','mail'=>'mail_user'][$child['kind']]??throw new RuntimeException('Ungültiger Zugang.');
        $remote=$api->call($prefix.'_get',['primary_id'=>(int)$child['external_id']]);
        if(!is_array($remote) || (int)($remote['sys_groupid']??0)!==(int)$service['meta']['group_id'])throw new RuntimeException('Die Zuordnung dieses Zugangs muss geprüft werden.');
        if($child['kind']==='mail'){
            if(($remote['email']??'')!==$child['name'])throw new RuntimeException('Dieses Postfach gehört nicht zum zugeordneten Produkt.');
        }elseif((int)($remote['parent_domain_id']??0)!==(int)$site['domain_id'])throw new RuntimeException('Dieser Zugang gehört nicht zur zugeordneten Website.');
        $id=(int)$child['external_id'];$passwordKey='password';
        if($child['kind']==='database'){
            $id=(int)($metadata['database_user_id']??0);if($id<1)throw new RuntimeException('Für diese Datenbank fehlt die eindeutige Benutzerzuordnung.');
            $remote=$api->call('sites_database_user_get',['primary_id'=>$id]);
            if(!is_array($remote) || (int)($remote['sys_groupid']??0)!==(int)$service['meta']['group_id'] || ($remote['database_user']??'')!==($metadata['database_user']??''))throw new RuntimeException('Dieser Datenbankbenutzer ist nicht eindeutig zugeordnet.');
            $prefix='sites_database_user';$passwordKey='database_password';
        }
        $remote[$passwordKey]=$password;
        $updated=$api->call($prefix.'_update',['client_id'=>(int)$service['meta']['client_id'],'primary_id'=>$id,'params'=>$remote]);if(!$updated)throw new RuntimeException('Die Passwortänderung konnte durch ISPConfig nicht bestätigt werden.');
        if($child['kind']==='ftp' && $service['credentials']){$credentials=secret_open($service['credentials'],'service:'.$service['id']);if(($credentials['username']??'')===$child['name']){$credentials['password']=$password;query('UPDATE shop_resource_links SET credentials=? WHERE service_id=?',[secret_seal($credentials,'service:'.$service['id']),$service['id']]);}}
        save_child_credentials($childId,['username'=>$child['kind']==='database'?($metadata['database_user']??$child['name']):$child['name'],'password'=>$password]);audit_event('hosting.password.'.$child['kind'],(string)$service['id']);
    });
}
function change_vps_password(array $service,string $password): void {
    if($service['provider']!=='proxmox' || !$service['credentials'])throw new RuntimeException('Für diesen Server sind keine verwalteten Gast-Zugangsdaten hinterlegt.');
    if(proxmox_service_type($service)==='lxc')throw new RuntimeException('Das Root-Passwort kannst du im Container mit passwd ändern. Bei einer Neuinstallation erzeugt der Shop ein neues Passwort.');
    validate_service_password($password);
    resource_mutex((int)$service['id'],function()use($service,$password){
        $credentials=secret_open($service['credentials'],'service:'.$service['id']);$username=$credentials['username']??'';
        if(!preg_match('/^[a-z_][a-z0-9_-]{0,30}$/D',$username))throw new RuntimeException('Der Gastbenutzer muss durch den Support geprüft werden.');
        $api=new ProxmoxProvider();$meta=$service['meta'];if(!$api->poolContains($meta['node'],(int)$meta['vmid']))throw new RuntimeException('Der Server ist nicht im zugeordneten Kundenpool.');
        try{$api->call('POST','/nodes/'.rawurlencode($meta['node']).'/qemu/'.(int)$meta['vmid'].'/agent/set-user-password',['username'=>$username,'password'=>$password,'crypted'=>0]);}
        catch(ProviderFailure $error){throw new RuntimeException('Die Passwortänderung im Gast konnte nicht bestätigt werden. Der Server muss laufen und den QEMU-Gastagenten mit passenden Rechten bereitstellen. Bitte bei Bedarf den Support kontaktieren.');}
        $credentials['password']=$password;query('UPDATE shop_resource_links SET credentials=? WHERE service_id=?',[secret_seal($credentials,'service:'.$service['id']),$service['id']]);audit_event('vps.password',(string)$service['id']);
    });
}
