<?php
function vps_export_for_customer(int $order,int $customer): ?array {return one('SELECT * FROM shop_vps_exports WHERE order_id=? AND customer_id=?',[$order,$customer]);}
function vps_export_guest(array $row,ProxmoxProvider $api): array {
    if(!hash_equals($row['connection_id'],template_connection_identity()))throw new RuntimeException('Die Proxmox-Verbindung der VPS-Sicherung wurde geändert.');
    $service=bound_service((int)$row['service_id'],(int)$row['customer_id']);$meta=$service['meta'];$p=json_decode($row['payload'],true);
    if((int)($meta['vmid']??0)!==(int)$row['vmid'] || ($meta['node']??'')!==$row['node'] || proxmox_service_type($service)!==$row['guest_type'] || ($meta['ownership_marker']??'')!==$p['marker'])throw new RuntimeException('Die Zuordnung des VPS stimmt nicht mehr.');
    $cfg=verify_proxmox_resource($service,$api);if(!proxmox_marker_matches($cfg,$p['marker']))throw new RuntimeException('Der VPS gehört nicht mehr eindeutig zu dieser Bestellung.');return $cfg;
}
function vps_export_path(array $row): string {return '/v1/exports/'.(int)$row['order_id'].'/'.$row['claim'];}
function vps_export_transport(array $row,string $method,?callable $write=null,string $range=''): array {
    if(PHP_SAPI==='cli' && isset($GLOBALS['shop_test_export_transport']))return ($GLOBALS['shop_test_export_transport'])($row,$method,$write,$range);
    $runner=one('SELECT * FROM shop_template_runners WHERE node=?',[$row['node']]);if(!$runner || !hash_equals($runner['connection_id'],$row['connection_id']))throw new RuntimeException('Der VPS-Sicherungsdienst ist noch nicht verbunden.');
    $health=json_decode(shop_meta_get('vps_export_runner:'.$row['node'],'{}'),true);$pin=$health['pin']??'';if(!preg_match('~^sha256//[A-Za-z0-9+/]{43}=$~D',$pin))throw new RuntimeException('Der sichere Download-Dienst wurde noch nicht eingerichtet.');
    $host=parse_url(integration_config('proxmox')['url'],PHP_URL_HOST);if(!$host)throw new RuntimeException('Der Proxmox-Host fehlt.');$host=trim($host,'[]');if(str_contains($host,':'))$host='['.$host.']';
    $path=vps_export_path($row);$timestamp=(string)time();$token=secret_open($runner['secret_token'],'template-runner:'.$row['node'])['token'];$signature=hash_hmac('sha256',$method."\n".$path."\n".$timestamp."\n".$range,$token);$headers=[];
    $ch=curl_init('https://'.$host.':9444'.$path);curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>$method==='HEAD'?30:0,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>0,CURLOPT_PINNEDPUBLICKEY=>$pin,CURLOPT_HTTPHEADER=>['X-Orga-Time: '.$timestamp,'Authorization: OrgaExport '.$signature,'Range: '.$range],CURLOPT_NOBODY=>$method==='HEAD',CURLOPT_RETURNTRANSFER=>false,CURLOPT_HEADERFUNCTION=>function($ch,$line)use(&$headers){if(str_contains($line,':')){[$k,$v]=explode(':',$line,2);$headers[strtolower(trim($k))]=trim($v);}return strlen($line);}]);
    if($write)curl_setopt($ch,CURLOPT_WRITEFUNCTION,function($ch,$chunk)use($write){$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);return in_array($status,[200,206],true)?$write($chunk):0;});
    $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);if($ok===false || !in_array($status,[200,206],true))throw new RuntimeException('Der VPS-Download-Dienst ist nicht erreichbar. Bitte Runner, TCP-Port 9444 und Zertifikatsbindung prüfen.');return ['status'=>$status,'headers'=>$headers];
}
function vps_export_probe(array $row): void {
    $r=vps_export_transport($row,'HEAD');$h=$r['headers'];if((int)($h['content-length']??-1)!==(int)$row['size_bytes'] || !hash_equals($row['sha256'],(string)($h['x-orga-sha256']??'')))throw new RuntimeException('Die VPS-Sicherung ist nicht vollständig bestätigt. Der VPS bleibt erhalten.');
}
function queue_vps_export(array $order,array $service): bool {
    if($row=one('SELECT * FROM shop_vps_exports WHERE order_id=?',[$order['id']]))return process_vps_export($row);
    $marker=$service['meta']['ownership_marker']??'';if($marker!=='Server Management '.$order['reference'])throw new RuntimeException('Für die automatische VPS-Löschung fehlt die eindeutige Bestellmarkierung.');
    $api=new ProxmoxProvider();$cfg=verify_proxmox_resource($service,$api);if(!proxmox_marker_matches($cfg,$marker))throw new RuntimeException('VPS-Zuordnung vor der Sicherung unklar.');
    $p=['marker'=>$marker,'pool'=>integration_config('proxmox')['pool']];
    query("INSERT INTO shop_vps_exports (order_id,service_id,customer_id,node,vmid,guest_type,connection_id,claim,payload,updated_ts) VALUES (?,?,?,?,?,?,?,?,?,?)",[$order['id'],$service['id'],$order['customer_id'],$service['meta']['node'],$service['meta']['vmid'],proxmox_service_type($service),template_connection_identity(),bin2hex(random_bytes(24)),json_encode($p),shop_now()]);return false;
}
function process_vps_export(array $row): bool {
    if(admin_provider_manually_ended(one('SELECT end_payload FROM shop_contracts WHERE order_id=?',[$row['order_id']])))return true;
    if($row['state']==='done')return true;
    if(!hash_equals($row['connection_id'],template_connection_identity()))throw new RuntimeException('Die Proxmox-Verbindung dieser VPS-Sicherung muss geprüft werden.');
    if(in_array($row['state'],['queued','building','cleanup'],true))return false;
    if($row['state']==='needs_action')throw new RuntimeException($row['last_error']?:'Die VPS-Sicherung muss im Adminbereich geprüft werden.');
    if($row['state']==='ready' && shop_now()<(int)$row['expires_ts'])return false;
    $api=new ProxmoxProvider();$p=json_decode($row['payload'],true);$path='/nodes/'.rawurlencode($row['node']).'/'.$row['guest_type'].'/'.(int)$row['vmid'];
    if($row['state']==='ready'){
        // Never delete unless a complete, reachable archive has been available for five days.
        vps_export_probe($row);vps_export_guest($row,$api);
        if(($api->vm($row['node'],(int)$row['vmid'],$row['guest_type'])['status']??'')!=='stopped')throw new RuntimeException('Der gekündigte VPS wurde erneut gestartet. Vor der Löschung bitte prüfen.');
        if(one("SELECT id FROM shop_server_operations WHERE service_id=? AND state IN ('queued','waiting','running','needs_action')",[$row['service_id']]))throw new RuntimeException('Vor der VPS-Löschung muss ein offener Serverauftrag geprüft werden.');
        // Persist intent before DELETE. A lost response must never lead to a second deletion.
        query("UPDATE shop_vps_exports SET state='deleting',updated_ts=? WHERE order_id=?",[shop_now(),$row['order_id']]);
        $task=$api->call('DELETE',$path,['purge'=>1]);if(!is_string($task) || !str_starts_with($task,'UPID:'))throw new RuntimeException('Die Löschbestätigung fehlt. Vor einer Wiederholung den Proxmox-Zustand prüfen.');$p['delete_task']=$task;query("UPDATE shop_vps_exports SET state='delete_wait',payload=?,updated_ts=? WHERE order_id=?",[json_encode($p),shop_now(),$row['order_id']]);return false;
    }
    if($row['state']==='deleting')throw new RuntimeException('Die VPS-Löschung wurde bereits angefragt. Die fehlende Aufgabenbestätigung muss manuell geprüft werden.');
    if($row['state']==='delete_wait'){
        $task=$api->call('GET','/nodes/'.rawurlencode($row['node']).'/tasks/'.rawurlencode($p['delete_task']).'/status');if(($task['status']??'')!=='stopped')return false;if(($task['exitstatus']??'')!=='OK')throw new RuntimeException('Proxmox hat die VPS-Löschung nicht bestätigt.');
        foreach((array)$api->call('GET','/cluster/resources?type=vm') as $guest)if((int)($guest['vmid']??0)===(int)$row['vmid'])throw new RuntimeException('Die VPS-ID ist noch belegt. IP und Sicherung bleiben gesperrt.');
        db()->beginTransaction();try{query("UPDATE shop_network_addresses SET state='available',order_id=NULL,service_id=NULL WHERE order_id=? AND service_id=?",[$row['order_id'],$row['service_id']]);$link=one('SELECT external_id,metadata FROM shop_resource_links WHERE service_id=?',[$row['service_id']]);$meta=json_decode($link['metadata'],true);$meta['retired_external_id']=$link['external_id'];$meta['retired_ts']=shop_now();query('UPDATE shop_resource_links SET external_id=?,metadata=?,credentials=NULL WHERE service_id=?',['retired:'.$row['order_id'].':'.$link['external_id'],json_encode($meta),$row['service_id']]);query("UPDATE shop_vps_exports SET state='cleanup',updated_ts=? WHERE order_id=?",[shop_now(),$row['order_id']]);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}return false;
    }
    throw new RuntimeException('Unbekannter VPS-Abschlussstatus.');
}
function vps_export_runner_heartbeat(string $node,array $in): void {
    $pin=(string)($in['pin']??'');if(!preg_match('~^sha256//[A-Za-z0-9+/]{43}=$~D',$pin) || ($in['version']??0)!==1)throw new RuntimeException('Ungültiger Download-Dienst.');
    shop_meta_set('vps_export_runner:'.$node,json_encode(['pin'=>$pin,'seen'=>shop_now(),'version'=>1]));
}
function vps_export_runner_poll(string $node,array $in): ?array {
    vps_export_runner_heartbeat($node,$in);
    $row=one("SELECT * FROM shop_vps_exports WHERE node=? AND state IN ('cleanup','building','queued') ORDER BY CASE WHEN state='cleanup' THEN 0 ELSE 1 END,order_id LIMIT 1",[$node]);if(!$row)return null;
    if(admin_provider_manually_ended(one('SELECT end_payload FROM shop_contracts WHERE order_id=?',[$row['order_id']]))){query("UPDATE shop_vps_exports SET state='cancelled' WHERE order_id=? AND state='queued'",[$row['order_id']]);return null;}
    if(!hash_equals($row['connection_id'],template_connection_identity()))throw new RuntimeException('Die VPS-Verbindung wurde geändert.');
    if($row['state']==='queued' && query("UPDATE shop_vps_exports SET state='building',updated_ts=? WHERE order_id=? AND state='queued'",[shop_now(),$row['order_id']])->rowCount()!==1)return null;
    return ['id'=>(int)$row['order_id'],'claim'=>$row['claim'],'operation'=>$row['state']==='cleanup'?'cleanup':'backup','vmid'=>(int)$row['vmid'],'guest_type'=>$row['guest_type'],'node'=>$node]+json_decode($row['payload'],true);
}
function vps_export_runner_report(string $node,array $in): array {
    $row=one('SELECT * FROM shop_vps_exports WHERE order_id=? AND node=?',[(int)($in['id']??0),$node]);if(!$row || !hash_equals($row['claim'],(string)($in['claim']??'')) || !hash_equals($row['connection_id'],template_connection_identity()))throw new RuntimeException('Ungültiger Sicherungsauftrag.');
    if(admin_provider_manually_ended(one('SELECT end_payload FROM shop_contracts WHERE order_id=?',[$row['order_id']])))return ['accepted'=>true];
    if(($in['result']??'')==='failed'){
        if(!in_array($row['state'],['queued','building','needs_action','cleanup'],true))return ['accepted'=>true];
        $payload=json_decode($row['payload'],true);$payload['retry_state']=$payload['retry_state']??($row['state']==='cleanup'?'cleanup':'building');
        query("UPDATE shop_vps_exports SET state='needs_action',payload=?,last_error='Der Sicherungsdienst konnte den Auftrag nicht abschließen. Bitte dessen Journal, freien Speicher und die Erreichbarkeit von Port 9444 prüfen.',updated_ts=? WHERE order_id=?",[json_encode($payload),shop_now(),$row['order_id']]);
        notification_broadcast_admins(['customer_id'=>$row['customer_id'],'event_key'=>'vps-export-error:'.$row['order_id'],'title'=>'VPS-Sicherung benötigt Prüfung','body'=>'Der VPS bleibt bis zum erfolgreichen Abschluss erhalten.','url'=>'/konto/vps-sicherungen','created_at'=>gmdate('Y-m-d H:i:s'),'deleted_ts'=>0]);return ['accepted'=>true];
    }
    if(($in['result']??'')==='cleaned'){
        if(!in_array($row['state'],['cleanup','done'],true))throw new RuntimeException('Die Löschung ist noch nicht freigegeben.');
        query("UPDATE shop_vps_exports SET state='done',last_error=NULL,updated_ts=? WHERE order_id=?",[shop_now(),$row['order_id']]);return ['accepted'=>true];
    }
    if(($in['result']??'')!=='ready' || !in_array($row['state'],['building','ready'],true))throw new RuntimeException('Der Sicherungsauftrag ist nicht offen.');
    $archive=(string)($in['archive']??'');$ext=$row['guest_type']==='lxc'?'tar':'vma';if(!preg_match('/^vzdump-'.preg_quote($row['guest_type'],'/').'-'.(int)$row['vmid'].'-[\w.-]+\.'.$ext.'\.zst$/D',$archive) || !is_int($in['size']??null) || $in['size']<1 || !preg_match('/^[a-f0-9]{64}$/D',(string)($in['sha256']??'')))throw new RuntimeException('Ungültiges VPS-Sicherungsarchiv.');
    if($row['state']==='ready'){
        if($row['archive']!==$archive || (int)$row['size_bytes']!==$in['size'] || !hash_equals($row['sha256'],$in['sha256']))throw new RuntimeException('Die bestätigte Sicherung wurde geändert.');return ['accepted'=>true,'expires'=>(int)$row['expires_ts']];
    }
    $row['archive']=$archive;$row['size_bytes']=$in['size'];$row['sha256']=$in['sha256'];vps_export_probe($row);$now=shop_now();$expires=$now+5*86400;
    db()->beginTransaction();try{
        query("UPDATE shop_vps_exports SET state='ready',archive=?,size_bytes=?,sha256=?,ready_ts=?,expires_ts=?,updated_ts=?,last_error=NULL WHERE order_id=? AND state='building'",[$archive,$in['size'],$in['sha256'],$now,$expires,$now,$row['order_id']]);
        $body='Dein VPS wurde zum Vertragsende heruntergefahren. Deine vollständige VPS-Sicherung steht bis '.date('d.m.Y H:i T',$expires)." zum Download bereit. Danach werden VPS und Sicherung endgültig gelöscht.\n\n".Config::FRONTPANEL_SITE_URL.'/konto/vps-sicherungen';
        customer_notification((int)$row['customer_id'],'vps-export-ready:'.$row['order_id'],'Deine VPS-Sicherung ist bereit',$body,'/konto/vps-sicherungen');$c=one('SELECT email FROM customers WHERE id=?',[$row['customer_id']]);queue_shop_mail('vps-export-ready:'.$row['order_id'],$c['email'],'VPS-Sicherung: Download für fünf Tage verfügbar',$body);db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}return ['accepted'=>true,'expires'=>$expires];
}
function download_vps_export(int $id,int $customer): never {
    $row=vps_export_for_customer($id,$customer);if(!$row || $row['state']!=='ready' || shop_now()>=(int)$row['expires_ts']){http_response_code(404);exit('Diese VPS-Sicherung ist nicht verfügbar oder ihre Frist ist abgelaufen.');}
    try{vps_export_probe($row);}catch(Throwable $e){http_response_code(503);exit('Die Sicherung ist gerade nicht erreichbar. Bitte den Support kontaktieren.');}
    $size=(int)$row['size_bytes'];$start=0;$end=$size-1;$range='';$raw=$_SERVER['HTTP_RANGE']??'';
    if($raw!==''){
        if(!preg_match('/^bytes=(\d+)-(\d*)$/D',$raw,$m) || strlen($m[1])>18 || strlen($m[2])>18 || (int)$m[1]>=$size || ($m[2]!=='' && (int)$m[2]<(int)$m[1])){http_response_code(416);header('Content-Range: bytes */'.$size);exit;}
        $start=(int)$m[1];$end=$m[2]===''?$end:min($end,(int)$m[2]);$range='bytes='.$start.'-'.$end;
    }
    session_write_close();while(ob_get_level())ob_end_clean();@set_time_limit(0);header('Content-Type: application/octet-stream');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('X-Accel-Buffering: no');header('Accept-Ranges: bytes');header('Content-Disposition: attachment; filename="'.$row['archive'].'"');header('Content-Length: '.($end-$start+1));if($range){http_response_code(206);header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);}
    try{vps_export_transport($row,'GET',function($chunk){if(connection_aborted())return 0;echo $chunk;flush();return strlen($chunk);},$range);}catch(Throwable $e){error_log('VPS archive stream interrupted: '.(int)$row['order_id']);}exit;
}

function handle_vps_export_action(string $action): bool {
    if($action!=='vps_export_retry')return false;require_admin();$id=(int)($_POST['order_id']??0);$row=one('SELECT * FROM shop_vps_exports WHERE order_id=?',[$id]);
    if(!$row || $row['state']!=='needs_action')throw new RuntimeException('Dieser Sicherungsauftrag benötigt keine erneute Freigabe.');
    if(admin_provider_manually_ended(one('SELECT end_payload FROM shop_contracts WHERE order_id=?',[$id])))throw new RuntimeException('Dieser Vertrag wurde manuell beendet. Es wird kein weiterer Sicherungs- oder Löschauftrag gestartet.');
    $p=json_decode($row['payload'],true);$state=$p['retry_state']??'';if(!in_array($state,['building','cleanup'],true))throw new RuntimeException('Dieser Anbieterzustand muss vor einer Fortsetzung manuell geprüft werden.');
    resource_mutex((int)$row['service_id'],function()use($row,$state){
        if(!hash_equals($row['connection_id'],template_connection_identity()))throw new RuntimeException('Die ursprüngliche Proxmox-Verbindung muss zuerst wieder zugeordnet werden.');
        query("UPDATE shop_vps_exports SET state=?,last_error=NULL,updated_ts=? WHERE order_id=? AND state='needs_action'",[$state,shop_now(),$row['order_id']]);query('UPDATE shop_contracts SET next_check_at=0 WHERE order_id=?',[$row['order_id']]);
    });audit_event('server.export.retry',(string)$id);flash('Der Sicherungsdienst darf den Auftrag erneut prüfen. Eine bestehende Downloadfrist bleibt unverändert.');redirect('/admin/vps-sicherungen');
}
