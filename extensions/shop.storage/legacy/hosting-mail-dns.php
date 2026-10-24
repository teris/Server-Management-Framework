<?php
/** Only public key material leaves the ISPConfig-side DKIM generator. */
function public_dkim_record(array $data): array {
    $selector=(string)($data['selector']??'');$pem=(string)($data['public_key']??'');$key=@openssl_pkey_get_public($pem);$details=$key?openssl_pkey_get_details($key):false;
    if(empty($data['ready']) || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]{0,62}$/D',$selector) || !$details || $details['type']!==OPENSSL_KEYTYPE_RSA || $details['bits']<1024)throw new RuntimeException('ISPConfig hat noch keinen gültigen öffentlichen DKIM-Schlüssel bestätigt.');
    $public=preg_replace('/-----BEGIN PUBLIC KEY-----|-----END PUBLIC KEY-----|\s/','',$details['key']);$text='v=DKIM1; k=rsa; p='.$public;
    return validate_dns_record(['type'=>'TXT','subdomain'=>$selector.'._domainkey','target'=>implode(' ',array_map(fn($part)=>'"'.$part.'"',str_split($text,240))),'ttl'=>3600]);
}
function dns_txt_value(string $target): string {
    if(!str_starts_with(trim($target),'"'))return trim($target);preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"/',$target,$m);return implode('',array_map('stripcslashes',$m[1]??[]));
}
function ensure_hosting_dkim(IspconfigProvider $api,int $customer,int $client,int $group,string $domain): array {
    $domain=valid_domain($domain);$old=one('SELECT * FROM shop_hosting_mail_dns WHERE domain=?',[$domain]);if($old && ((int)$old['customer_id']!==$customer || !hash_equals($old['ispconfig_connection_id'],integration_identity('ispconfig'))))throw new RuntimeException('Die DKIM-Zuordnung dieser Domain muss durch den Support geprüft werden.');
    $mail=$api->mailDomain($domain);if(!$mail || (int)($mail['sys_groupid']??0)!==$group || (int)($mail['server_id']??0)!==(int)integration_config('ispconfig')['mail_server_id'])throw new RuntimeException('Die Maildomain gehört nicht zum eingerichteten Kunden-Webspace.');
    $data=$api->call('orgashop_mail_domain_dkim_ensure',['client_id'=>$client,'primary_id'=>(int)$mail['domain_id'],'domain'=>$domain,'dry_run'=>false]);if(!is_array($data) || ($data['domain']??'')!==$domain || (int)($data['domain_id']??0)!==(int)$mail['domain_id'])throw new RuntimeException('Die DKIM-Einrichtung muss in ISPConfig geprüft werden.');$record=public_dkim_record($data);
    $managed=customer_owns_dns_zone($customer,$domain);$mode=$managed?'ovh':'external';$identity=$managed?integration_identity('ovh'):'';$serialized=json_encode($record);$state=$managed?'pending':'manual';
    if($old){if($old['record']!==$serialized || $old['dns_mode']!==$mode || $old['ovh_connection_id']!==$identity || $old['state']==='needs_action')query('UPDATE shop_hosting_mail_dns SET mail_domain_id=?,record=?,dns_mode=?,ovh_connection_id=?,state=?,last_error=NULL,updated_ts=? WHERE domain=?',[(int)$mail['domain_id'],$serialized,$mode,$identity,$state,time(),$domain]);}
    else query('INSERT INTO shop_hosting_mail_dns (domain,customer_id,mail_domain_id,ispconfig_connection_id,ovh_connection_id,record,dns_mode,state,updated_ts) VALUES (?,?,?,?,?,?,?,?,?)',[$domain,$customer,(int)$mail['domain_id'],integration_identity('ispconfig'),$identity,$serialized,$mode,$state,time()]);
    return $record;
}
function hosting_public_dkim(string $domain,int $customer): ?array {
    $r=one('SELECT * FROM shop_hosting_mail_dns WHERE domain=? AND customer_id=?',[$domain,$customer]);if(!$r || !hash_equals($r['ispconfig_connection_id'],integration_identity('ispconfig')))return null;return json_decode($r['record'],true);
}
function read_live_hosting_dkim(string $domain,int $customer): ?array {
    $j=one('SELECT * FROM shop_hosting_mail_dns WHERE domain=? AND customer_id=?',[$domain,$customer]);if(!$j)return null;
    if(!hash_equals($j['ispconfig_connection_id'],integration_identity('ispconfig')))throw new RuntimeException('Die ISPConfig-Verbindung der Maildomain wurde geändert.');
    $api=new IspconfigProvider();$mail=$api->mailDomain($domain);$account=one("SELECT external_id FROM shop_provider_accounts WHERE customer_id=? AND provider='ispconfig'",[$customer]);
    if(!$mail || !$account || (int)$mail['domain_id']!==(int)$j['mail_domain_id'] || $api->clientIdForGroup((int)$mail['sys_groupid'])!==(int)$account['external_id'])throw new RuntimeException('Die ISPConfig-Maildomain ist nicht mehr eindeutig diesem Kunden zugeordnet.');
    $fresh=$api->call('orgashop_mail_domain_dkim_ensure',['client_id'=>(int)$account['external_id'],'primary_id'=>(int)$mail['domain_id'],'domain'=>$domain,'dry_run'=>true]);
    if(($fresh['domain']??'')!==$domain || (int)($fresh['domain_id']??0)!==(int)$mail['domain_id'])throw new RuntimeException('Der DKIM-Schlüssel gehört nicht zu dieser Maildomain.');return public_dkim_record($fresh);
}
function dynamic_dns_records(array $records,string $domain,int $customer): array {
    $records=array_values(array_filter($records,fn($r)=>!str_ends_with(strtolower($r['subDomain']??''),'._domainkey')));if($dkim=hosting_public_dkim($domain,$customer))$records[]=$dkim;return $records;
}
function publish_dkim_record(OvhProvider $api,string $domain,array $record,?array $records=null): void {
    $records??=$api->records($domain);$same=array_values(array_filter($records,fn($r)=>($r['fieldType']??'')==='TXT' && rtrim(strtolower($r['subDomain']??''),'.')===strtolower($record['subDomain'])));
    if(count($same)>1)throw new RuntimeException('Für diesen DKIM-Selektor bestehen mehrere TXT-Einträge. Bitte im DNS zuerst eindeutig zuordnen.');
    if($same && preg_replace('/\s+/','',dns_txt_value($same[0]['target']))===preg_replace('/\s+/','',dns_txt_value($record['target'])) && (int)$same[0]['ttl']===(int)$record['ttl'])return;
    $path='/domain/zone/'.rawurlencode($domain).'/record';if($same)$api->call('PUT',$path.'/'.(int)$same[0]['id'],['subDomain'=>$record['subDomain'],'target'=>$record['target'],'ttl'=>$record['ttl']]);else $api->call('POST',$path,$record);
}
function run_hosting_mail_dns_jobs(int $limit=2): int {
    $done=0;query("UPDATE shop_hosting_mail_dns SET state='pending' WHERE state='running' AND locked_until<?",[time()]);
    foreach(all("SELECT domain FROM shop_hosting_mail_dns WHERE state='pending' AND dns_mode='ovh' AND locked_until<? ORDER BY updated_ts LIMIT ".max(1,min(4,$limit)),[time()]) as $r){$token=bin2hex(random_bytes(16));if(!query("UPDATE shop_hosting_mail_dns SET state='running',lock_token=?,locked_until=? WHERE domain=? AND state='pending' AND locked_until<?",[$token,time()+180,$r['domain'],time()])->rowCount())continue;$j=one('SELECT * FROM shop_hosting_mail_dns WHERE domain=?',[$r['domain']]);
        try{if(!integration_ready('ispconfig') || !integration_ready('ovh'))throw new RuntimeException('ISPConfig und OVH müssen vor der DKIM-Veröffentlichung erfolgreich verbunden sein.');if(!customer_owns_dns_zone((int)$j['customer_id'],$j['domain']) || !hash_equals($j['ovh_connection_id'],integration_identity('ovh')) || !hash_equals($j['ispconfig_connection_id'],integration_identity('ispconfig')))throw new RuntimeException('Die Domain- oder Anbieterzuordnung wurde geändert. Der DKIM-Eintrag wurde nicht veröffentlicht.');
            // Read back the active ISPConfig selector immediately before touching DNS.
            $isp=new IspconfigProvider();$mail=$isp->mailDomain($j['domain']);$account=one("SELECT external_id FROM shop_provider_accounts WHERE customer_id=? AND provider='ispconfig'",[$j['customer_id']]);if(!$mail || !$account || (int)$mail['domain_id']!==(int)$j['mail_domain_id'] || $isp->clientIdForGroup((int)$mail['sys_groupid'])!==(int)$account['external_id'])throw new RuntimeException('Die zugehörige ISPConfig-Maildomain muss geprüft werden.');
            $fresh=$isp->call('orgashop_mail_domain_dkim_ensure',['client_id'=>(int)$account['external_id'],'primary_id'=>(int)$mail['domain_id'],'domain'=>$j['domain'],'dry_run'=>true]);$record=public_dkim_record($fresh);if(($fresh['domain']??'')!==$j['domain'])throw new RuntimeException('Die DKIM-Domain passt nicht zum DNS-Auftrag.');
            $api=new OvhProvider();publish_dkim_record($api,$j['domain'],$record);$api->call('POST','/domain/zone/'.rawurlencode($j['domain']).'/refresh',[]);query("UPDATE shop_hosting_mail_dns SET state='published',record=?,last_error=NULL,updated_ts=? WHERE domain=? AND lock_token=?",[json_encode($record),time(),$j['domain'],$token]);$done++;
        }catch(Throwable $e){query("UPDATE shop_hosting_mail_dns SET state='needs_action',last_error=? WHERE domain=? AND lock_token=?",[mb_substr($e instanceof ProviderFailure?$e->getMessage():($e instanceof RuntimeException && !($e instanceof PDOException)?$e->getMessage():'DKIM-Veröffentlichung muss geprüft werden.'),0,500),$j['domain'],$token]);}
        finally{query('UPDATE shop_hosting_mail_dns SET locked_until=0,lock_token=NULL WHERE domain=? AND lock_token=?',[$j['domain'],$token]);}}
    return $done;
}
