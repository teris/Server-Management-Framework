<?php
function default_dns_template(): string {return <<<'ZONE'
$TTL 3600
@ IN MX 1 mail.orga-consult.eu.
@ IN A {web_ipv4}
@ IN TXT "v=spf1 include:mx.ovh.com ~all"
@ IN TXT "1|www.{domain}"
* IN A {web_ipv4}
_autodiscover._tcp IN SRV 0 0 443 mail.orga-consult.eu.
_submission._tcp IN SRV 0 0 465 mail.orga-consult.eu.
autoconfig IN CNAME mailconfig.mail.orga-consult.eu.
autodiscover IN CNAME mailconfig.mail.orga-consult.eu.
{dkim_selector}._domainkey 3600 IN TXT "v=DKIM1; k=rsa; p={dkim_public}"
mail IN CNAME mail.orga-consult.eu.
www IN MX 1 mail.orga-consult.eu.
www IN A {web_ipv4}
www IN TXT "3|welcome"
www IN TXT "l|de"
ZONE;}
function dns_template_settings(): array {$r=one("SELECT meta_value FROM shop_meta WHERE meta_key='dns_template'");return ($r?json_decode($r['meta_value'],true):[])+['enabled'=>0,'content'=>default_dns_template()];}
function parse_dns_template(string $content,string $domain,?array $dkim=null): array {
    if(strlen($content)>20000)throw new RuntimeException('Die DNS-Vorlage ist zu umfangreich.');$settings=infrastructure_settings();$content=strtr($content,['{domain}'=>valid_domain($domain),'{web_ipv4}'=>$settings['web_ipv4'],'{web_ipv6}'=>$settings['web_ipv6'],'{mail_host}'=>$settings['mail_host']]);$ttl=3600;$records=[];$last='@';
    foreach(preg_split('/\r?\n/',$content) as $n=>$line){$line=trim($line);if(stripos($line,'_domainkey')!==false || str_contains($line,'{dkim_'))continue;if($line==='' || str_starts_with($line,';') || str_starts_with($line,'#'))continue;if(preg_match('/^\$TTL\s+(\d+)$/iD',$line,$m)){$ttl=(int)$m[1];continue;}if(preg_match('/^(?:.*\s)?IN\s+(?:SOA|NS)\s/i',$line))continue;
        if(!preg_match('/^(?:(\S+)\s+)?(?:(\d+)\s+)?IN\s+(A|AAAA|CNAME|MX|TXT|SRV|CAA)\s+(.+)$/iD',$line,$m))throw new RuntimeException('DNS-Vorlage: Zeile '.($n+1).' ist kein unterstützter Eintrag.');$name=$m[1]?:$last;$last=$name;if(str_ends_with($name,'.'.$domain.'.'))$name=substr($name,0,-strlen('.'.$domain.'.'));if($name===$domain.'.')$name='@';$records[]=validate_dns_record(['type'=>strtoupper($m[3]),'subdomain'=>$name,'target'=>$m[4],'ttl'=>$m[2]?:$ttl]);
    }
    if($dkim)$records[]=$dkim;
    if(count($records)>100)throw new RuntimeException('Bitte höchstens 100 DNS-Einträge in einer Vorlage verwenden.');return $records;
}
function save_dns_template(array $in): void {$content=trim((string)($in['content']??''));parse_dns_template($content,'example.org');$value=json_encode(['content'=>$content,'enabled'=>isset($in['enabled'])?1:0]);if(one("SELECT meta_key FROM shop_meta WHERE meta_key='dns_template'"))query("UPDATE shop_meta SET meta_value=? WHERE meta_key='dns_template'",[$value]);else query("INSERT INTO shop_meta (meta_key,meta_value) VALUES ('dns_template',?)",[$value]);audit_event('dns.template.saved');}
function queue_default_dns(array $order): void {
    if(hosting_external_domain($order))return;
    if(!in_array($order['provider'],['ovh','ispconfig'],true) || module_order_intent((int)$order['id']))return;$settings=dns_template_settings();if(!$settings['enabled'])return;$domain=valid_domain($order['domain']);if(!$domain || one('SELECT order_id FROM shop_dns_jobs WHERE order_id=?',[$order['id']]))return;
    $records=parse_dns_template($settings['content'],$domain);query("INSERT INTO shop_dns_jobs (order_id,domain,records,payload) VALUES (?,?,?,'{}')",[$order['id'],$domain,json_encode($records)]);
}
function run_dns_template_jobs(int $limit=2): int {
    $count=0;query("UPDATE shop_dns_jobs SET state='needs_action',last_error='DNS-Auftrag unterbrochen. Vor Fortsetzung vorhandene Einträge prüfen.' WHERE state='running' AND locked_until<?",[time()]);
    foreach(all("SELECT order_id FROM shop_dns_jobs WHERE state='pending' AND locked_until<? LIMIT ".max(1,min(4,$limit)),[time()]) as $r){$token=bin2hex(random_bytes(16));if(query("UPDATE shop_dns_jobs SET state='running',lock_token=?,locked_until=? WHERE order_id=? AND state='pending' AND locked_until<?",[$token,time()+600,$r['order_id'],time()])->rowCount()!==1)continue;$job=one('SELECT * FROM shop_dns_jobs WHERE order_id=?',[$r['order_id']]);try{$order=one('SELECT * FROM shop_orders WHERE id=?',[$r['order_id']]);if(!$order || !order_payment_allows_provisioning($order) || contract_closed(ensure_order_contract($order)))throw new RuntimeException('Kein aktiver bezahlter Auftrag für diese DNS-Einrichtung.');if(hosting_external_domain($order))throw new RuntimeException('Externe Domains werden ausschließlich beim bisherigen DNS-Anbieter durch den Kunden eingerichtet.');if(valid_domain($job['domain'])!==valid_domain($order['domain']))throw new RuntimeException('Die DNS-Zone passt nicht zur bezahlten Bestellung.');if($order['provider']==='ispconfig' && !customer_owns_dns_zone((int)$order['customer_id'],$job['domain']))throw new RuntimeException('Die Domain muss diesem Kunden als OVH-Domain zugeordnet sein, bevor die DNS-Vorlage angewendet wird.');if(!integration_ready('ovh'))throw new RuntimeException('Die OVH-Anbindung muss zuerst geprüft werden.');$api=new OvhProvider();$zone=$job['domain'];$path='/domain/zone/'.rawurlencode($zone);$api->call('GET',$path);$existing=$api->records($zone);$records=dynamic_dns_records(json_decode($job['records'],true),$zone,(int)$order['customer_id']);if($live=read_live_hosting_dkim($zone,(int)$order['customer_id'])){$records=array_values(array_filter($records,fn($r)=>!str_ends_with(strtolower($r['subDomain']??''),'._domainkey')));$records[]=$live;}$payload=json_decode($job['payload'],true);
        foreach($records as $index=>$record){
            // Also protect old queued jobs: automation never changes authority or delegation.
            if(!in_array(strtoupper($record['fieldType']??''),['A','AAAA','CNAME','MX','TXT','SRV','CAA'],true))continue;
            if(str_ends_with(strtolower($record['subDomain']??''),'._domainkey')){publish_dkim_record($api,$zone,$record,$existing);continue;}$same=array_values(array_filter($existing,fn($v)=>$v['fieldType']===$record['fieldType'] && rtrim($v['subDomain'],'.')===rtrim($record['subDomain'],'.')));$exact=array_filter($same,fn($v)=>rtrim($v['target'],'.')===rtrim($record['target'],'.') && (int)$v['ttl']===(int)$record['ttl']);if($exact)continue;
            // Only replace a unique value. Preserve multiple independently maintained MX/TXT records.
            if(count($same)===1 && in_array($record['fieldType'],['A','AAAA','CNAME','SRV'],true))$api->call('PUT',$path.'/record/'.(int)$same[0]['id'],['subDomain'=>$record['subDomain'],'target'=>$record['target'],'ttl'=>$record['ttl']]);else{$payload['writing_index']=$index;query('UPDATE shop_dns_jobs SET payload=? WHERE order_id=?',[json_encode($payload),$order['id']]);$api->call('POST',$path.'/record',$record);}unset($payload['writing_index']);$payload['completed_index']=$index;query('UPDATE shop_dns_jobs SET payload=? WHERE order_id=?',[json_encode($payload),$order['id']]);
        }
        $api->call('POST',$path.'/refresh',[]);query("UPDATE shop_dns_jobs SET state='done',last_error=NULL WHERE order_id=?",[$order['id']]);customer_notification((int)$order['customer_id'],'dns-template:'.$order['id'],'DNS-Vorlage eingerichtet','Die DNS-Änderungen können bis zu 24 Stunden benötigen.','/konto/bestellungen?id='.$order['id']);$count++;
    }catch(Throwable $e){query("UPDATE shop_dns_jobs SET state='needs_action',last_error=? WHERE order_id=?",[mb_substr($e instanceof ProviderFailure?$e->getMessage():($e instanceof RuntimeException && !($e instanceof PDOException)?$e->getMessage():'DNS-Einrichtung muss geprüft werden.'),0,500),$r['order_id']]);}finally{query('UPDATE shop_dns_jobs SET lock_token=NULL,locked_until=0 WHERE order_id=? AND lock_token=?',[$r['order_id'],$token]);}}
    return $count;
}

function customer_owns_dns_zone(int $customer,string $domain): bool {
    if(!integration_row('ovh'))return false;$identity=integration_identity('ovh');foreach(all("SELECT metadata FROM shop_resource_links r JOIN shop_services s ON s.id=r.service_id WHERE s.customer_id=? AND r.provider='ovh' AND s.status='active'",[$customer]) as $r){$m=json_decode($r['metadata'],true);if(($m['zone']??'')===$domain && hash_equals($m['connection_id']??'',$identity))return true;}return false;
}
