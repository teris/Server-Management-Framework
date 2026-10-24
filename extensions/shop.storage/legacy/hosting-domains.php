<?php
/** A TXT proof and explicit customer instruction precede every new external-domain website. */
function hosting_domain_snapshot(int $customer,string $domain,array $input): array {
    if(empty($input['hosting_domain_consent']))throw new RuntimeException('Bitte bestätige, dass du diese Domain für deinen Webspace verwenden darfst.');
    return ['version'=>1,'mode'=>customer_owns_dns_zone($customer,$domain)?'managed':'external','requested_at'=>time()];
}
function hosting_external_domain(array $order): bool {
    $c=json_decode($order['configuration'],true);return $order['provider']==='ispconfig' && ($c['hosting_domain']['mode']??'')==='external' && !isset($c['module_addon']);
}
function ensure_hosting_domain_verification(array $order): ?array {
    if(!hosting_external_domain($order))return null;$row=one('SELECT * FROM shop_hosting_domain_verifications WHERE order_id=?',[$order['id']]);if($row){if((int)$row['customer_id']!==(int)$order['customer_id'] || $row['domain']!==$order['domain'])throw new RuntimeException('Die Domainprüfung passt nicht mehr zu dieser Bestellung.');return $row;}
    $token=bin2hex(random_bytes(32));try{query('INSERT INTO shop_hosting_domain_verifications (order_id,customer_id,domain,token,expires_ts) VALUES (?,?,?,?,?)',[$order['id'],$order['customer_id'],$order['domain'],$token,time()+30*86400]);}catch(PDOException $e){if(!one('SELECT order_id FROM shop_hosting_domain_verifications WHERE order_id=?',[$order['id']]))throw $e;}
    return one('SELECT * FROM shop_hosting_domain_verifications WHERE order_id=?',[$order['id']]);
}
function hosting_verification_value(array $v): string {return 'orga-consult-verification='.$v['token'];}
function hosting_verification_name(array $v): string {return '_orga-consult-verification.'.$v['domain'];}
function hosting_dns_txt_values(string $host): array {
    if(PHP_SAPI==='cli' && ORGA_LOCAL && isset($GLOBALS['shop_test_dns']))$rows=($GLOBALS['shop_test_dns'])($host);else $rows=@dns_get_record($host,DNS_TXT);
    if(!is_array($rows))throw new RuntimeException('Die DNS-Abfrage ist vorübergehend nicht erreichbar. Die Prüfung wird erneut versucht.');$values=[];
    foreach($rows as $row){if(($row['type']??'TXT')!=='TXT')continue;if(isset($row['entries']) && is_array($row['entries']))$values[]=implode('',$row['entries']);elseif(isset($row['txt']) && is_string($row['txt']))$values[]=$row['txt'];}return $values;
}
function check_hosting_domain_verification(array $v): bool {
    if(!$v['confirmed_ts'] || $v['expires_ts']<time() || $v['activated_ts'])return (bool)$v['activated_ts'];
    $found=false;foreach(hosting_dns_txt_values(hosting_verification_name($v)) as $value)if(hash_equals(hosting_verification_value($v),$value)){$found=true;break;}
    $msg=$found?null:'Der Prüf-TXT ist noch nicht öffentlich sichtbar. Bitte Namen und Inhalt prüfen; DNS-Änderungen können bis zu 24 Stunden dauern.';
    $updated=query('UPDATE shop_hosting_domain_verifications SET verified_ts=?,state=?,next_check=?,last_error=? WHERE order_id=? AND token=? AND activated_ts=0',[$found?time():0,$found?'verified':'pending',time()+300,$msg,$v['order_id'],$v['token']]);
    if(!$updated->rowCount())return false;
    if($found){query("UPDATE shop_jobs SET state='waiting',last_error=NULL WHERE order_id=? AND state='waiting_domain' AND phase IN ('start','client_ready') AND locked_until<?",[$v['order_id'],time()]);query("UPDATE shop_orders SET fulfillment_status='queued' WHERE id=? AND fulfillment_status='domain_verification'",[$v['order_id']]);}
    return $found;
}
function customer_confirm_hosting_domain(int $orderId,int $customer,array $in): bool {
    if(empty($in['installation_consent']))throw new RuntimeException('Bitte bestätige ausdrücklich, dass der Shop die Domain mit deinem Webspace verbinden soll.');
    $o=one('SELECT * FROM shop_orders WHERE id=? AND customer_id=?',[$orderId,$customer]);if(!$o || in_array($o['fulfillment_status'],['active','ended','cancelled'],true) || in_array($o['payment_status'],['cancelled','expired'],true))throw new RuntimeException('Diese Bestellung ist nicht für eine Domainverbindung verfügbar.');
    $v=ensure_hosting_domain_verification($o);if(!$v || $v['activated_ts'])throw new RuntimeException('Für diese Bestellung ist keine offene externe Domainprüfung vorhanden.');if($v['expires_ts']<time())throw new RuntimeException('Der Prüf-TXT ist abgelaufen. Bitte zunächst einen neuen Prüfcode anfordern.');
    if(!hash_equals($v['token'],(string)($in['verification_token']??'')))throw new RuntimeException('Der Prüfcode wurde erneuert. Bitte die Seite neu laden.');
    query('UPDATE shop_hosting_domain_verifications SET confirmed_ts=?,next_check=0 WHERE order_id=? AND token=?',[time(),$orderId,$v['token']]);$v['confirmed_ts']=time();audit_event('hosting.domain.confirmed',(string)$orderId);return check_hosting_domain_verification($v);
}
function rotate_hosting_domain_verification(int $orderId,int $customer): void {
    $o=one('SELECT * FROM shop_orders WHERE id=? AND customer_id=?',[$orderId,$customer]);if(!$o || service_for_order($orderId,$customer) || !hosting_external_domain($o) || in_array($o['fulfillment_status'],['cancelled','ended'],true))throw new RuntimeException('Der Prüfcode dieser Bestellung kann nicht erneuert werden.');
    $v=ensure_hosting_domain_verification($o);$job=one('SELECT * FROM shop_jobs WHERE order_id=?',[$orderId]);if($v['activated_ts'] || ($job && (!in_array($job['phase'],['start','client_ready'],true) || $job['locked_until']>=time())))throw new RuntimeException('Die Domain wird bereits eingerichtet. Der Prüfcode bleibt bestehen.');
    query("UPDATE shop_hosting_domain_verifications SET token=?,confirmed_ts=0,verified_ts=0,expires_ts=?,state='pending',next_check=0,last_error=NULL WHERE order_id=?",[bin2hex(random_bytes(32)),time()+30*86400,$orderId]);audit_event('hosting.domain.token.rotated',(string)$orderId);
}
function hosting_domain_provisioning_gate(array $order,array &$job,array $data): bool {
    $c=json_decode($order['configuration'],true);if(!isset($c['hosting_domain']))return true;
    if(($c['hosting_domain']['mode']??'')==='managed'){if(!customer_owns_dns_zone((int)$order['customer_id'],$order['domain']))throw new RuntimeException('Die zugeordnete Shop-Domain gehört nicht mehr zu diesem Kunden.');return true;}
    $v=ensure_hosting_domain_verification($order);if(!$v)return true;
    $ready=$v['confirmed_ts'] && $v['verified_ts']>time()-900 && $v['expires_ts']>=time();
    if($ready){try{$ready=check_hosting_domain_verification($v);}catch(Throwable $e){$ready=false;}}
    if(!$ready){customer_notification((int)$order['customer_id'],'hosting-domain-proof:'.$order['id'],'Externe Domain bestätigen','Bitte den Prüf-TXT bei deinem Domainanbieter setzen und die Verbindung zu deinem Webspace bestätigen.','/konto/bestellungen?id='.$order['id'].'#domain-verbinden');save_job_phase($job,$job['phase'],$data,'waiting_domain');query("UPDATE shop_orders SET fulfillment_status='domain_verification' WHERE id=?",[$order['id']]);return false;}return true;
}
function run_hosting_domain_checks(int $limit=2): int {
    $done=0;foreach(all("SELECT v.* FROM shop_hosting_domain_verifications v JOIN shop_orders o ON o.id=v.order_id WHERE v.confirmed_ts>0 AND v.activated_ts=0 AND v.expires_ts>=? AND v.next_check<=? AND v.locked_until<? AND o.fulfillment_status NOT IN ('cancelled','ended','active') AND o.payment_status NOT IN ('cancelled','expired') ORDER BY v.next_check LIMIT ".max(1,min(4,$limit)),[time(),time(),time()]) as $v){$token=bin2hex(random_bytes(16));if(!query('UPDATE shop_hosting_domain_verifications SET locked_until=?,lock_token=? WHERE order_id=? AND locked_until<?',[time()+180,$token,$v['order_id'],time()])->rowCount())continue;
        try{if(check_hosting_domain_verification($v))$done++;}catch(Throwable $e){query("UPDATE shop_hosting_domain_verifications SET last_error='DNS-Prüfung vorübergehend nicht erreichbar. Der Shop versucht es erneut.',next_check=? WHERE order_id=?",[time()+300,$v['order_id']]);}finally{query('UPDATE shop_hosting_domain_verifications SET locked_until=0,lock_token=NULL WHERE order_id=? AND lock_token=?',[$v['order_id'],$token]);}}
    return $done;
}
function handle_hosting_domain_action(string $action): bool {
    if($action==='hosting_domain_confirm'){$u=require_user();rate_limit('hosting_domain_proof',10,900);$id=(int)($_POST['order_id']??0);$ok=customer_confirm_hosting_domain($id,(int)$u['id'],$_POST);flash($ok?'Prüf-TXT bestätigt. Nach bestätigter Zahlung wird dein Webspace automatisch eingerichtet.':'Dein Auftrag zur Domainverbindung ist gespeichert. Sobald der Prüf-TXT sichtbar ist, setzt der Shop die Einrichtung nach Zahlung fort.');redirect('/konto/bestellungen?id='.$id);}
    if($action==='hosting_domain_rotate'){$u=require_user();rate_limit('hosting_domain_rotate',5,3600);$id=(int)($_POST['order_id']??0);rotate_hosting_domain_verification($id,(int)$u['id']);flash('Neuer Prüf-TXT erstellt. Bitte den bisherigen TXT-Inhalt ersetzen und erneut bestätigen.');redirect('/konto/bestellungen?id='.$id);}
    if($action==='hosting_dkim_sync'){$u=require_user();rate_limit('hosting_dkim_sync',10,3600);$s=bound_service((int)($_POST['service_id']??0),(int)$u['id'],true);if($s['provider']!=='ispconfig')throw new RuntimeException('Dieses Paket ist kein Webspace.');resource_mutex((int)$s['id'],function()use($s){$api=new IspconfigProvider();$site=hosting_site($s,$api);ensure_hosting_dkim($api,(int)$s['customer_id'],(int)$s['meta']['client_id'],(int)$site['sys_groupid'],$s['meta']['domain']);});flash('DKIM in ISPConfig geprüft. Den aktuellen DNS-Eintrag findest du unten; verwaltete OVH-Zonen werden automatisch aktualisiert.');redirect('/konto/verwalten?id='.$s['id'].'#hosting-dns');}return false;
}
