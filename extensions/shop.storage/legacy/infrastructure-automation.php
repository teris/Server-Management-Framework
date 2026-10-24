<?php
function infrastructure_automation_settings(): array {
    return array_replace(['ovh_enabled'=>1,'ovh_interval_minutes'=>360,'build_address_id'=>0,'backup_storage'=>'store02','template_storage'=>'local','auto_build_saved'=>0],json_decode(shop_meta_get('infrastructure_automation','{}'),true)??[]);
}
function save_infrastructure_automation(array $in): void {
    $s=infrastructure_automation_settings();$s['ovh_enabled']=isset($in['ovh_enabled'])?1:0;$s['auto_build_saved']=isset($in['auto_build_saved'])?1:0;
    $s['ovh_interval_minutes']=module_quantity($in['ovh_interval_minutes']??360);$s['build_address_id']=module_quantity($in['build_address_id']??0);
    foreach(['backup_storage','template_storage'] as $k){$s[$k]=trim((string)($in[$k]??$s[$k]));if(!preg_match('/^[\w.-]{1,80}$/D',$s[$k]))throw new RuntimeException('Bitte gültige Proxmox-Storage-Namen angeben.');}
    if($s['ovh_interval_minutes']<15 || $s['ovh_interval_minutes']>10080)throw new RuntimeException('Das Abgleichintervall muss zwischen 15 Minuten und 7 Tagen liegen.');
    if($s['build_address_id']){$a=one('SELECT * FROM shop_network_addresses WHERE id=?',[$s['build_address_id']]);if(!$a || $a['order_id'] || $a['service_id'] || !in_array($a['state'],['available','build_ready','building'],true) || !$a['mac'] || (int)$a['prefix_len']!==32)throw new RuntimeException('Bitte eine freie, freigegebene IP mit fester MAC als Bau-IP auswählen.');}
    $old=infrastructure_automation_settings();db()->beginTransaction();try{
        $lock=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
        if($old['build_address_id'] && (int)$old['build_address_id']!==(int)$s['build_address_id']){$a=one('SELECT * FROM shop_network_addresses WHERE id=?'.$lock,[$old['build_address_id']]);if($a && $a['state']==='building')throw new RuntimeException('Die aktuelle Bau-IP wird noch verwendet. Bitte zuerst den Bauauftrag abschließen.');query("UPDATE shop_network_addresses SET state='available' WHERE id=? AND state='build_ready'",[$old['build_address_id']]);}
        if($s['build_address_id']){$a=one('SELECT * FROM shop_network_addresses WHERE id=?'.$lock,[$s['build_address_id']]);if(!$a || $a['order_id'] || $a['service_id'] || !in_array($a['state'],['available','build_ready','building'],true))throw new RuntimeException('Die gewählte Bau-IP wurde inzwischen vergeben.');query("UPDATE shop_network_addresses SET state='build_ready' WHERE id=? AND state='available'",[$s['build_address_id']]);}
        shop_meta_set('infrastructure_automation',json_encode($s));db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}schedule_ovh_inventory();audit_event('infrastructure.automation.saved');
}
function schedule_ovh_inventory(): void {
    if(!one("SELECT provider FROM shop_infrastructure_sync WHERE provider='ovh'"))query("INSERT INTO shop_infrastructure_sync (provider) VALUES ('ovh')");
    query("UPDATE shop_infrastructure_sync SET next_run=0 WHERE provider='ovh'");
}
function run_ovh_inventory_sync(bool $force=false): bool {
    $s=infrastructure_automation_settings();if((!$force && !$s['ovh_enabled']) || !integration_ready('ovh'))return false;
    if(!one("SELECT provider FROM shop_infrastructure_sync WHERE provider='ovh'"))schedule_ovh_inventory();
    $token=bin2hex(random_bytes(24));$now=time();$claimed=query("UPDATE shop_infrastructure_sync SET locked_until=?,lock_token=?,state='running' WHERE provider='ovh' AND locked_until<?".($force?'':' AND next_run<=?'),$force?[$now+1800,$token,$now]:[$now+1800,$token,$now,$now]);if(!$claimed->rowCount())return false;
    try{$r=ovh_network_inventory();query("UPDATE shop_infrastructure_sync SET state=?,last_success=?,next_run=?,last_error=?,summary=? WHERE provider='ovh' AND lock_token=?",[$r['notice']?'partial':'ok',$now,$now+($r['notice']?900:$s['ovh_interval_minutes']*60),$r['notice']?:null,json_encode(['created'=>$r['created'],'updated'=>$r['updated']]),$token]);return true;}
    catch(Throwable $e){$message=$e instanceof ProviderFailure?$e->getMessage():'Der OVH-Abgleich konnte nicht abgeschlossen werden. Bitte Verbindung und Freigaben prüfen.';query("UPDATE shop_infrastructure_sync SET state='error',last_error=?,next_run=? WHERE provider='ovh' AND lock_token=?",[$message,$now+900,$token]);if($force)throw new RuntimeException($message);return false;}
    finally{query("UPDATE shop_infrastructure_sync SET locked_until=0,lock_token=NULL WHERE provider='ovh' AND lock_token=?",[$token]);}
}
function template_connection_identity(?array $cfg=null): string {
    $cfg??=integration_config('proxmox');return hash('sha256',integration_identity('proxmox',$cfg).'|'.($cfg['node']??'').'|'.($cfg['pool']??''));
}
function template_build_catalog(): array {
    return ['debian','ubuntu','postgresql','dolibarr','apache-tomcat','caddy','n8n','nextcloudpi','centos','fedora','gentoo','archlinux','docker'];
}
function template_build_recipe(array $template): array {
    $settings=json_decode($template['settings'],true);$url=parse_url($template['source_url']);$slug=$settings['build_script']??'';
    if(!$slug){$slug=basename($url['path']??'');if(($url['host']??'')!=='community-scripts.org')throw new RuntimeException('Für diese Vorlage bitte ein unterstütztes Community Script auswählen.');}
    if(!in_array($slug,template_build_catalog(),true))throw new RuntimeException('Für diese Vorlage ist kein unterstütztes Community Script hinterlegt.');
    $recipe=$settings['build_profile']??'';if(!($settings['build_script']??'') && $template['name']==='Debian (DockerServer)')$slug='docker';if(!$recipe && $template['name']==='Ubuntu (Webserver)')$recipe='ubuntu-webserver';
    return ['slug'=>$slug,'recipe'=>$recipe,'scripts_commit'=>'6083e802ee77f7bcba1890bc702b7f7a0c0fbf55','core_commit'=>'d2281987365beaad7273fab29928f5f67a4e867c'];
}
function queue_template_build(int $id): int {
    $t=one('SELECT * FROM shop_server_templates WHERE id=?',[$id]);if(!$t)throw new RuntimeException('LXC-Vorlage nicht gefunden.');if($t['active'])throw new RuntimeException('Eine freigegebene Vorlage bitte zunächst als neuen Entwurf anlegen. Bestehende Kundenbestellungen behalten ihre ursprüngliche Vorlage.');
    $s=infrastructure_automation_settings();if(!$s['build_address_id'])throw new RuntimeException('Bitte unter „Automatik“ eine freie feste IP-/MAC-Zuordnung als Bau-IP festlegen.');
    $address=one('SELECT * FROM shop_network_addresses WHERE id=?',[$s['build_address_id']]);if(!$address || (int)$address['prefix_len']!==32 || !$address['mac'] || !in_array($address['state'],['build_ready','building'],true))throw new RuntimeException('Die Bau-IP muss mit /32 und fester MAC für den Vorlagendienst reserviert sein.');lxc_network_config($address);
    $cfg=integration_config('proxmox');if(($cfg['node']??'')!==$t['node'])throw new RuntimeException('Die Vorlage muss den gespeicherten Proxmox-Ziel-Node verwenden.');$recipe=template_build_recipe($t);$settings=json_decode($t['settings'],true);
    $payload=['template'=>$t,'settings'=>$settings,'recipe'=>$recipe,'network_id'=>(int)$s['build_address_id'],'backup_storage'=>$s['backup_storage'],'template_storage'=>$s['template_storage'],'pool'=>$cfg['pool']];
    db()->beginTransaction();try{$lock=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';one('SELECT id FROM shop_server_templates WHERE id=?'.$lock,[$id]);$existing=one("SELECT id FROM shop_template_builds WHERE template_id=? AND state IN ('queued','running','needs_action')",[$id]);if($existing){db()->commit();return (int)$existing['id'];}
    query("INSERT INTO shop_template_builds (template_id,node,connection_id,payload,created_ts) VALUES (?,?,?,?,?)",[$id,$t['node'],template_connection_identity($cfg),json_encode($payload),time()]);$job=(int)db()->lastInsertId();db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}audit_event('server.template.build.queued',(string)$job);return $job;
}
function template_runner_registration(): array {
    $cfg=integration_config('proxmox');$node=$cfg['node'];if(!preg_match('/^[\w.-]{1,80}$/D',$node))throw new RuntimeException('Bitte zuerst einen Proxmox-Ziel-Node speichern.');$identity=template_connection_identity($cfg);$old=one('SELECT * FROM shop_template_runners WHERE node=?',[$node]);
    if($old && hash_equals($old['connection_id'],$identity))return ['node'=>$node,'token'=>secret_open($old['secret_token'],'template-runner:'.$node)['token']];
    $token=bin2hex(random_bytes(32));$sealed=secret_seal(['token'=>$token],'template-runner:'.$node);if($old)query("UPDATE shop_template_runners SET secret_token=?,connection_id=?,seen_ts=0,last_status='' WHERE node=?",[$sealed,$identity,$node]);else query('INSERT INTO shop_template_runners (node,secret_token,connection_id) VALUES (?,?,?)',[$node,$sealed,$identity]);return ['node'=>$node,'token'=>$token];
}
function template_runner_auth(string $node,string $token): array {
    $r=one('SELECT * FROM shop_template_runners WHERE node=?',[$node]);if(!$r || !hash_equals(secret_open($r['secret_token'],'template-runner:'.$node)['token'],$token))throw new RuntimeException('Runner-Anmeldung abgelehnt.');
    if(!hash_equals(template_connection_identity(),$r['connection_id']))throw new RuntimeException('Proxmox-Verbindung geändert. Bitte den Vorlagendienst neu verbinden.');return $r;
}
function template_runner_claim(string $node): ?array {
    db()->beginTransaction();try{$lock=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
    // One builder per node, no duplicate execution after an interrupted installation.
    one('SELECT node FROM shop_template_runners WHERE node=?'.$lock,[$node]);if(one("SELECT id FROM shop_template_builds WHERE node=? AND state IN ('running','needs_action')",[$node])){db()->commit();return null;}
    $j=one("SELECT * FROM shop_template_builds WHERE node=? AND state='queued' ORDER BY id LIMIT 1".$lock,[$node]);if(!$j){db()->commit();return null;}
    if(!hash_equals($j['connection_id'],template_connection_identity()))throw new RuntimeException('Die Proxmox-Verbindung dieses Bauauftrags wurde geändert.');
    $p=json_decode($j['payload'],true);$ip=one('SELECT * FROM shop_network_addresses WHERE id=?'.$lock,[$p['network_id']]);if(!$ip || $ip['state']!=='build_ready' || $ip['order_id'] || $ip['service_id'] || !$ip['mac'] || ($ip['node'] && $ip['node']!==$node))throw new RuntimeException('Die Bau-IP ist nicht frei oder ihre feste MAC fehlt.');
    query("UPDATE shop_network_addresses SET state='building' WHERE id=? AND state='build_ready'",[$ip['id']]);$p['network']=$ip;$p['claim']=bin2hex(random_bytes(24));query("UPDATE shop_template_builds SET state='running',payload=?,started_ts=?,updated_ts=?,last_error=NULL WHERE id=?",[json_encode($p),time(),time(),$j['id']]);db()->commit();return ['id'=>(int)$j['id'],'payload'=>$p];
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function template_runner_report(string $node,array $in): void {
    $j=one("SELECT * FROM shop_template_builds WHERE id=? AND node=?",[(int)($in['id']??0),$node]);if(!$j)throw new RuntimeException('Bauauftrag nicht gefunden.');$p=json_decode($j['payload'],true);if(!hash_equals($p['claim']??'',(string)($in['claim']??'')))throw new RuntimeException('Bauauftrag ist nicht mehr offen.');if($j['state']==='review' && ($in['state']??'')==='done' && ($in['archive']??'')===$j['archive'])return;if($j['state']==='needs_action' && ($in['state']??'')==='failed')return;if(!in_array($j['state'],['running','needs_action'],true))throw new RuntimeException('Bauauftrag ist nicht mehr offen.');
    $state=(string)($in['state']??'running');if(!in_array($state,['running','done','failed'],true))throw new RuntimeException('Ungültiger Baustatus.');$vmid=(int)($in['vmid']??0);$message=['running'=>'Vorlage wird auf Proxmox vorbereitet.','failed'=>'Vorlagenerstellung abgebrochen. Baucontainer und Bau-IP bitte im Proxmox-Host prüfen.','done'=>'Vorlage erstellt. Inhalt und Anwendung prüfen, anschließend für Kundenpakete freigeben.'][$state];
    if($state==='done'){
        if($vmid<100)throw new RuntimeException('Ungültige Baucontainer-ID.');$volume=(string)($in['archive']??'');$expected='/^'.preg_quote($p['backup_storage'],'/').':backup\/vzdump-lxc-'.$vmid.'-[\w.-]+\.tar\.(?:zst|gz|lzo)$/D';if(!preg_match($expected,$volume))throw new RuntimeException('Das Bau-Archiv gehört nicht zu diesem Auftrag.');
        $api=new ProxmoxProvider();$found=false;foreach($api->call('GET','/nodes/'.rawurlencode($node).'/storage/'.rawurlencode($p['backup_storage']).'/content') as $v)if(($v['volid']??'')===$volume)$found=true;if(!$found)throw new RuntimeException('Das erstellte Archiv wurde auf Proxmox nicht gefunden.');foreach($api->call('GET','/cluster/resources') as $v)if((int)($v['vmid']??0)===$vmid)throw new RuntimeException('Der Baucontainer existiert noch. Die Bau-IP bleibt reserviert.');
        $s=$p['settings'];foreach(['disk_gb','min_memory_mb','min_cores'] as $k){$v=(int)($in['resources'][$k]??$s[$k]);if($v<(int)$s[$k] || $v>1048576)throw new RuntimeException('Ungültige Vorlagenressourcen.');$s[$k]=$v;}
        // Do not overwrite edits made by the administrator while a build was running.
        $t=one('SELECT * FROM shop_server_templates WHERE id=?',[$j['template_id']]);if(!$t || $t['active'] || $t['settings']!==$p['template']['settings'] || $t['archive']!==$p['template']['archive'] || $t['node']!==$p['template']['node'] || $t['storage']!==$p['template']['storage'] || $t['source_url']!==$p['template']['source_url'])throw new RuntimeException('Die Vorlage wurde während des Bauens geändert. Bitte das neue Archiv nach Prüfung manuell zuordnen.');
        db()->beginTransaction();try{query('UPDATE shop_server_templates SET archive=?,settings=?,active=0 WHERE id=?',[$volume,json_encode($s),$t['id']]);query("UPDATE shop_network_addresses SET state='build_ready' WHERE id=? AND state='building' AND order_id IS NULL AND service_id IS NULL",[$p['network_id']]);query("UPDATE shop_template_builds SET state='review',archive=?,vmid=?,updated_ts=?,last_error=? WHERE id=?",[$volume,$vmid,time(),$message,$j['id']]);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    }else query('UPDATE shop_template_builds SET state=?,vmid=?,updated_ts=?,last_error=? WHERE id=?',[$state==='failed'?'needs_action':'running',$vmid,time(),$message,$j['id']]);
}
function template_runner_endpoint(): never {
    header('Content-Type: application/json');try{if(($_SERVER['REQUEST_METHOD']??'')!=='POST')throw new RuntimeException();$raw=file_get_contents('php://input',false,null,0,16385);if(strlen($raw)>16384)throw new RuntimeException();$in=json_decode($raw,true,32,JSON_THROW_ON_ERROR);$node=(string)($in['node']??'');$header=$_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'';if(!str_starts_with($header,'Bearer '))throw new RuntimeException();template_runner_auth($node,substr($header,7));query("UPDATE shop_template_runners SET seen_ts=?,last_status='Verbunden' WHERE node=?",[time(),$node]);
    if(($in['action']??'')==='export_heartbeat'){vps_export_runner_heartbeat($node,$in);$out=['accepted'=>true];}elseif(($in['action']??'')==='export_poll')$out=['job'=>vps_export_runner_poll($node,$in)];elseif(($in['action']??'')==='export_report')$out=vps_export_runner_report($node,$in);elseif(($in['action']??'')==='poll')$out=['job'=>template_runner_claim($node)];elseif(($in['action']??'')==='report'){template_runner_report($node,$in);$out=['accepted'=>true];}else throw new RuntimeException();echo json_encode($out,JSON_THROW_ON_ERROR);}
    catch(Throwable $e){http_response_code(409);echo '{"accepted":false,"message":"Vorlagendienst: Anmeldung, Auftrag oder Anbieterzustand prüfen."}';}exit;
}
function template_runner_file_command(string $path,string $content): string {
    if(!str_starts_with($path,'/') || preg_match('/[\x00\r\n]/',$path))throw new RuntimeException('Ungültiger Installationspfad.');
    // JSON escapes such as \/ are not decoded by Python string literals.
    $flags=JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR;
    return 'python3 -c '.escapeshellarg('import base64,pathlib; p=pathlib.Path('.json_encode($path,$flags).'); p.write_bytes(base64.b64decode('.json_encode(base64_encode($content),$flags).')); p.chmod(0o600)');
}
function download_template_runner(): never {
    $r=template_runner_registration();$cfg=['url'=>Config::FRONTPANEL_SITE_URL.'/api/infrastructure/runner','node'=>$r['node'],'token'=>$r['token'],'pool'=>integration_config('proxmox')['pool']];$python=file_get_contents(__DIR__.'/template-runner.py');
    $installer="#!/bin/bash\nset -euo pipefail\n[ \"\$(id -u)\" = 0 ] || { echo 'Bitte als root auf dem Proxmox-Host ausführen.'; exit 1; }\ncommand -v pveversion >/dev/null\n[ \"\$(hostname -s)\" = ".escapeshellarg($r['node'])." ] || { echo 'Dieser Installer gehört zu einem anderen Proxmox-Node.'; exit 1; }\ncommand -v python3 >/dev/null\ninstall -d -m 700 /etc/orga-template-runner /var/lib/orga-template-runner\n";
    foreach(['/usr/local/sbin/orga-template-runner.py'=>$python,'/usr/local/sbin/orga-vps-export-runner.py'=>file_get_contents(__DIR__.'/vps-export-runner.py'),'/etc/orga-template-runner/config.json'=>json_encode($cfg)] as $path=>$content)$installer.=template_runner_file_command($path,$content)."\n";
    $service="[Unit]\nDescription=Server Management LXC-Vorlagendienst\nAfter=network-online.target\nWants=network-online.target\n[Service]\nType=oneshot\nExecStart=/usr/bin/python3 /usr/local/sbin/orga-template-runner.py\nTimeoutStartSec=4h\nUMask=0077\n";$timer="[Unit]\nDescription=Server Management Vorlagenaufträge prüfen\n[Timer]\nOnBootSec=60s\nOnUnitActiveSec=60s\nPersistent=true\n[Install]\nWantedBy=timers.target\n";
    foreach(['/etc/systemd/system/orga-template-runner.service'=>$service,'/etc/systemd/system/orga-template-runner.timer'=>$timer] as $path=>$content)$installer.="printf '%s' ".escapeshellarg($content).' > '.escapeshellarg($path)."\n";
    $exportService="[Unit]\nDescription=Server Management VPS-Sicherungen und Downloads\nAfter=network-online.target\nWants=network-online.target\n[Service]\nType=simple\nExecStart=/usr/bin/python3 /usr/local/sbin/orga-vps-export-runner.py\nRestart=on-failure\nRestartSec=30\nUMask=0077\n[Install]\nWantedBy=multi-user.target\n";
    $installer.="printf '%s' ".escapeshellarg($exportService)." > /etc/systemd/system/orga-vps-export-runner.service\nsystemctl daemon-reload\nsystemctl enable orga-vps-export-runner.service\nsystemctl restart orga-vps-export-runner.service\n";
    $installer.="systemctl daemon-reload\nsystemctl enable --now orga-template-runner.timer\necho 'Vorlagendienst installiert. Status und Aufträge stehen im Shop-Backend.'\n";
    audit_event('server.template.runner.download',$r['node']);header('Content-Type: text/x-shellscript; charset=UTF-8');header('Content-Disposition: attachment; filename="orga-vorlagendienst.sh"');header('Cache-Control: no-store');echo $installer;exit;
}
function run_infrastructure_automation(): void {
    run_ovh_inventory_sync();query("UPDATE shop_template_builds SET state='needs_action',last_error='Vorlagendienst meldet sich nicht mehr. Baucontainer und Bau-IP vor weiterer Ausführung prüfen.' WHERE state='running' AND updated_ts<?",[time()-14400]);
}

function clear_template_build(int $id): void {
    $j=one("SELECT * FROM shop_template_builds WHERE id=? AND state='needs_action'",[$id]);if(!$j)throw new RuntimeException('Dieser Bauauftrag ist nicht zur Prüfung angehalten.');
    if(!hash_equals($j['connection_id'],template_connection_identity()))throw new RuntimeException('Die Proxmox-Verbindung des Auftrags wurde geändert. Bitte ursprünglichen Host prüfen.');
    $api=new ProxmoxProvider();if((int)$j['vmid']<100)throw new RuntimeException('Der Vorlagendienst muss zuerst die tatsächliche Baucontainer-ID melden.');foreach($api->call('GET','/cluster/resources') as $v)if((int)($v['vmid']??0)===(int)$j['vmid'])throw new RuntimeException('Der Baucontainer existiert noch. Bitte auf Proxmox prüfen und den fehlgeschlagenen Baucontainer dort entfernen.');
    $p=json_decode($j['payload'],true);db()->beginTransaction();try{query("UPDATE shop_template_builds SET state='failed',updated_ts=? WHERE id=? AND state='needs_action'",[time(),$id]);query("UPDATE shop_network_addresses SET state='build_ready' WHERE id=? AND state='building' AND order_id IS NULL AND service_id IS NULL",[$p['network_id']]);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}audit_event('server.template.build.cleared',(string)$id);
}
