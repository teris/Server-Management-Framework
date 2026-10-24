<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ob_start();require __DIR__.'/bootstrap.php';
$checks=0;$failures=[];
function check($condition,$name){global$checks,$failures;$checks++;if(!$condition)$failures[]=$name;}
function denied(callable $f,$name){try{$f();check(false,$name);}catch(Throwable$e){check(true,$name);}}
$m=SMF\Core\Kernel::boot()->modules();foreach(['shop.storage','shop.catalog','shop.portal','shop.orders','shop.payments','shop.provisioning','shop.billing','shop.contracts','shop.connections','provider.ogp','product.gameserver']as$id){$m->install($id);$m->enable($id);}
query("UPDATE customers SET address='Teststraße 1',postal_code='10115',city='Berlin',country='DE'");
$bridge=str_repeat('e',64);integration_save('ogp',['url'=>'https://ogp.example.invalid','token'=>str_repeat('a',64),'bridge_key'=>$bridge],true);
$users=[];$homes=[];$writes=[];$installing=true;$timeoutHome=false;$offline=false;$physicalChanged=false;$foreignPorts=[30000];
$GLOBALS['shop_test_transport']=function($provider,$method,$url,$body)use(&$users,&$homes,&$writes,&$installing,&$timeoutHome,&$offline,&$physicalChanged,$foreignPorts){
 $p=[];parse_str($body,$p);$a=parse_url($url,PHP_URL_QUERY);$status=200;$result=[];
 if(!in_array($a,['token/test','server/list','server/list_ips','server/status','user_games/list_games','user_games/list_servers','user_admin/get','bridge/capabilities','bridge/ports','bridge/status','bridge/log','bridge/definition'],true))$writes[]=$a;
 switch($a){
 case'token/test':$result='admin';break;
 case'server/list':$result=[['remote_server_id'=>1,'remote_server_name'=>'Fixture agent']];break;
 case'server/list_ips':$result=[['ip'=>'192.0.2.10']];break;
 case'server/status':$result=$offline?'offline':'online';break;
 case'user_games/list_games':$result=[['home_cfg_id'=>10,'game_name'=>'Fixture Game','game_key'=>'fixture_linux64','mods'=>[['mod_cfg_id'=>20,'mod_key'=>'fixture']]]];break;
 case'bridge/capabilities':$result=['protocol'=>'smf-ogp-bridge/1','capabilities'=>['installation.status','home.identity','home.delete','port.read','home.log','game.definition','home.configure']];break;
 case'bridge/definition':$result=['agent_system'=>'linux','definition_sha256'=>str_repeat('d',64),'slots_enforceable'=>true,'install_methods'=>['steam'],'max_slots'=>64];break;
 case'bridge/ports':$result=array_map(fn($port)=>['ip'=>'192.0.2.10','port'=>$port],$foreignPorts);foreach($homes as$h)$result[]=['ip'=>$h['ip'],'port'=>$h['port']];break;
 case'user_admin/get':if(isset($users[$p['email']]))$result=$users[$p['email']];else{$status=317;$result='not found';}break;
 case'user_admin/create':$users[$p['email']]=['user_id'=>count($users)+101,'users_login'=>$p['name'],'users_email'=>$p['email']];$result='created';break;
 case'user_games/create':$id=count($homes)+201;$user=null;foreach($users as$u)if($u['users_login']===$p['assign_to_username'])$user=$u;assert($user!==null);$homes[$id]=['home_id'=>$id,'home_name'=>$p['server_name'],'remote_server_id'=>(int)$p['remote_server_id'],'user_id_main'=>$user['user_id'],'home_cfg_id'=>(int)$p['home_cfg_id'],'mod_cfg_id'=>(int)$p['mod_cfg_id'],'mod_key'=>'fixture','ip'=>$p['ip'],'port'=>(int)$p['port'],'running'=>false,'installed'=>false,'physical_id'=>hash('sha256',$p['control_password'])];$result=$id;if($timeoutHome)throw new RuntimeException('Simulated timeout after home creation');break;
 case'user_games/list_servers':$result=array_values($homes);break;
 case'bridge/configure':$h=&$homes[(int)$p['home_id']];$h['namespace_owned']=true;$h['physical_id']=hash('sha256',$h['physical_id'].'namespace');$result=['configured'=>true,'namespace_owned'=>true,'physical_id'=>$h['physical_id']];unset($h);break;
 case'bridge/status':case'bridge/log':
  $h=$homes[(int)($p['home_id']??0)]??null;if(!$h){$status=315;$result='missing';break;}
  $physical=$physicalChanged?str_repeat('f',64):$h['physical_id'];
  if(($p['marker']??'')!==$h['home_name'] || (int)($p['agent_id']??0)!==$h['remote_server_id'] || (int)($p['user_id']??0)!==$h['user_id_main'] || (isset($p['physical_id']) && $p['physical_id']!==$physical)){$status=403;$result='foreign';break;}
  $result=$a==='bridge/log'?['log'=>'Fixture server log']:['home_id'=>$h['home_id'],'physical_id'=>$physical,'running'=>$h['running'],'installing'=>$installing && $h['installed'],'executable_present'=>$h['installed'],'namespace_owned'=>$h['namespace_owned']??false,'startup_configured'=>$h['namespace_owned']??false];break;
 case'gamemanager/update':foreach($homes as&$h)if($h['ip']===$p['ip'] && $h['port']===(int)$p['port'])$h['installed']=true;unset($h);$result='Steam installation started';break;
 case'gamemanager/start':case'gamemanager/stop':case'gamemanager/restart':foreach($homes as&$h)if($h['ip']===$p['ip'] && $h['port']===(int)$p['port'])$h['running']=$a!=='gamemanager/stop';unset($h);$result='accepted';break;
 case'user_games/set_expiration':$result='expiration changed';break;
 case'bridge/delete':$id=(int)$p['home_id'];if(!isset($homes[$id]) || $homes[$id]['home_name']!==$p['marker'] || $homes[$id]['physical_id']!==$p['physical_id']){$status=403;$result='foreign';}else{unset($homes[$id]);$result=['deleted'=>true];}break;
 default:throw new RuntimeException('Unexpected fixture call '.$a);
 }
 return ['data'=>['status'=>(string)$status,'message'=>$result]];
};
SMF\Core\ProviderDiagnostics::check('ogp');
$service=(new SMF\Core\ProductRegistry($m))->service('gameserver');
query('INSERT INTO shop_products (slug,name,eyebrow,category,icon,description,features) VALUES (?,?,?,?,?,?,?)',['fixture-game','Fixture Game','Game','gameserver','server','Fixture game','[]']);$product=(int)db()->lastInsertId();
$policy=['agent_id'=>1,'home_cfg_id'=>10,'mod_cfg_id'=>20,'slot_min'=>4,'slot_max'=>32,'slot_step'=>2,'port_min'=>30000,'port_max'=>30059,'port_block'=>10,'base_net_cents'=>100,'slot_net_cents'=>50,'retention_days'=>0,'ip'=>'192.0.2.10','location'=>'Fixture','contract_text'=>'Monatliche Testleistung mit vereinbarten Slots.'];
foreach(['agent_id'=>2,'home_cfg_id'=>999,'mod_cfg_id'=>999,'ip'=>'192.0.2.99','memory_mb'=>1024,'port_max'=>80,'slot_max'=>2]as$key=>$value)denied(fn()=>SMF\Modules\Gameserver\Catalog::save($product,array_replace($policy,[$key=>$value]),true),'invalid operator option '.$key);
SMF\Modules\Gameserver\Catalog::save($product,$policy,true);$p=one('SELECT * FROM shop_products WHERE id=?',[$product]);
$configured=SMF\Modules\Gameserver\Catalog::configure($p,['slots'=>'8']);check($configured['price_cents']===595,'price computed in integer cents from trusted policy');
foreach([['slots'=>'5'],['slots'=>'1000'],['slots'=>[]],['slots'=>'8','price_cents'=>1],['slots'=>'8','agent_id'=>2]]as$input)denied(fn()=>SMF\Modules\Gameserver\Catalog::configure($p,$input),'invalid or manipulated configurator input');
function gameOrder(array$p,int$customer,string$reference,string$mode='live'):array{
 $profile=$p['_automation']['settings'];$config=order_commerce_snapshot($p);query('INSERT INTO shop_orders (reference,customer_id,product_id,product_name,amount_cents,currency,billing,provider,profile,domain,configuration,paypal_mode,payment_kind,payment_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$reference,$customer,$p['id'],$p['name'],$p['price_cents'],'EUR','month','ogp',json_encode($profile),'',json_encode($config),$mode,'order','PAY-'.$reference]);return one('SELECT * FROM shop_orders WHERE reference=?',[$reference]);
}
$sandbox=gameOrder($configured,1,'GS-SANDBOX','sandbox');record_paid($sandbox,'CAP-SANDBOX',595,'EUR');record_paid($sandbox,'CAP-SANDBOX',595,'EUR');check((int)one('SELECT COUNT(*) n FROM shop_payments WHERE order_id=?',[$sandbox['id']])['n']===1,'duplicate sandbox payment books once');check(one('SELECT state FROM shop_jobs WHERE order_id=?',[$sandbox['id']])['state']==='sandbox','sandbox payment creates no live job');
$fakejob=['id'=>0];denied(function()use($service,$sandbox,$configured,&$fakejob){$service->provision($sandbox,$fakejob,$configured['_automation']['settings'],[]);},'sandbox refuses external provision');check($writes===[],'sandbox produces zero provider writes');
file_put_contents(HttpSecurity::privateDirectory('config').'/shop-settings.json',json_encode(['environment'=>'live','allow_live_operations'=>true,'site_url'=>'https://fixture.example.invalid']));
$order=gameOrder($configured,1,'GS-LIVE');record_paid($order,'CAP-LIVE',595,'EUR');record_paid($order,'CAP-LIVE',595,'EUR');
run_provisioning_jobs();$lease=one('SELECT * FROM smf_game_leases WHERE order_id=?',[$order['id']]);check($lease && $lease['state']==='installing','home creation continues to actual game install');check(count($homes)===1 && count($users)===1,'one own account and home created');check((int)json_decode($lease['metadata'],true)['port']===30010,'occupied provider port block skipped');
check(one('SELECT fulfillment_status FROM shop_orders WHERE id=?',[$order['id']])['fulfillment_status']!=='active','installation started is never advertised as ready');
run_provisioning_jobs();check(count($homes)===1,'install polling never creates another home');check(one('SELECT fulfillment_status FROM shop_orders WHERE id=?',[$order['id']])['fulfillment_status']!=='active','running installer keeps service pending');
$installing=false;run_provisioning_jobs();$lease=one('SELECT * FROM smf_game_leases WHERE order_id=?',[$order['id']]);check($lease['state']==='active' && $lease['service_id'],'running game process produces common service binding');$sid=(int)$lease['service_id'];
check(count(array_filter($writes,fn($a)=>$a==='user_games/create'))===1,'duplicate payment and worker phases create one home');
check($service->inspect($sid,1)['game_state']['running'],'owner can see actual game status');$n=count($writes);denied(fn()=>$service->inspect($sid,2),'second customer cannot read foreign home');check(count($writes)===$n,'foreign customer performs no remote write');
$physicalChanged=true;denied(fn()=>$service->inspect($sid,1),'reused home ID is blocked');$physicalChanged=false;
query("UPDATE shop_services SET status='suspended' WHERE id=?",[$sid]);denied(fn()=>$service->queueControl($sid,1,'start',str_repeat('a',32)),'suspended service cannot queue start');query("UPDATE shop_services SET status='active' WHERE id=?",[$sid]);
$key=str_repeat('b',32);$jobId=$service->queueControl($sid,1,'stop',$key);check($service->queueControl($sid,1,'stop',$key)===$jobId,'duplicate control request keeps one durable job');
$queue=SMF\Core\Kernel::boot()->jobs();$entry=$m->jobs()['product.gameserver'];$handler=$m->runtime($entry);$queue->runOne(['product.gameserver'=>$handler]);check(!$service->inspect($sid,1)['game_state']['running'],'fenced worker controls assigned home');
denied(fn()=>$service->queueControl($sid,1,'rcon',str_repeat('c',32),'exec ../../other.cfg'),'unsafe RCON command rejected');check($service->log($sid,1)==='Fixture server log','only assigned server log is readable');
// A timeout after remote creation is retained and cannot create a second home.
$second=gameOrder($configured,2,'GS-TIMEOUT');record_paid($second,'CAP-TIMEOUT',595,'EUR');$timeoutHome=true;run_provisioning_jobs();check(one('SELECT state FROM shop_jobs WHERE order_id=?',[$second['id']])['state']==='needs_action','uncertain home write requires reconciliation');check(count($homes)===2,'remote accepted timeout home exactly once');
$timeoutHome=false;denied(fn()=>retry_provisioning_job((int)one('SELECT id FROM shop_jobs WHERE order_id=?',[$second['id']])['id']),'blind retry of uncertain home is rejected');run_provisioning_jobs();check(count($homes)===2,'retry cannot duplicate uncertain home');
$service->reconcile((int)$second['id']);run_provisioning_jobs();$installing=false;run_provisioning_jobs();check(count($homes)===2,'operator reconciliation reuses only exact own marker');
check(one('SELECT fulfillment_status FROM shop_orders WHERE id=?',[$second['id']])['fulfillment_status']==='active','reconciled operation reaches active service');
// Renewal, overdue grace, refund review and repeated cycles keep the same physical service.
$homeCount=count($homes);query('UPDATE shop_contracts SET paid_until=? WHERE order_id=?',[time()-4*86400,$order['id']]);
denied(fn()=>$service->queueControl($sid,1,'start',str_repeat('d',32)),'overdue contract blocks customer writes before worker runs');
$service->tick();check(one('SELECT status FROM shop_services WHERE id=?',[$sid])['status']==='suspended','overdue contract is suspended after grace');
$renew=one('SELECT * FROM shop_orders WHERE id=?',[$order['id']]);record_paid($renew,'CAP-RENEW',595,'EUR');$until=(int)one('SELECT paid_until FROM shop_contracts WHERE order_id=?',[$order['id']])['paid_until'];record_paid($renew,'CAP-RENEW',595,'EUR');
check((int)one('SELECT paid_until FROM shop_contracts WHERE order_id=?',[$order['id']])['paid_until']===$until,'duplicate renewal never extends contract twice');
$service->tick();check(one('SELECT status FROM shop_services WHERE id=?',[$sid])['status']==='active','verified renewal restores own service grant');check(count($homes)===$homeCount,'renewal never creates an additional game home');
query("UPDATE shop_orders SET payment_status='review' WHERE id=?",[$order['id']]);denied(fn()=>$service->queueControl($sid,1,'start',str_repeat('e',32)),'payment review immediately blocks customer action');$service->tick();
check(one('SELECT status FROM shop_services WHERE id=?',[$sid])['status']==='suspended','payment review suspends physical service');
query("UPDATE shop_orders SET payment_status='paid' WHERE id=?",[$order['id']]);$service->tick();check(one('SELECT status FROM shop_services WHERE id=?',[$sid])['status']==='active','resolved payment review can reopen same paid term');
check((int)one('SELECT generation FROM smf_game_leases WHERE order_id=?',[$order['id']])['generation']===4,'repeated suspend cycles have independent durable operation identities');
// Common invoice and contract identities are retained for gameservers.
save_commerce_settings(['seller_name'=>'Fixture','seller_address'=>'Test 1','seller_postal_code'=>'10115','seller_city'=>'Berlin','seller_country'=>'DE','seller_tax_id'=>'FIXTURE','seller_email'=>'invoice@example.invalid','invoice_prefix'=>'GS']);
$invoice=invoice_for_payment(one('SELECT * FROM shop_payments WHERE transaction_id=?',['CAP-LIVE']),one('SELECT * FROM shop_orders WHERE id=?',[$order['id']]));check(is_file(invoice_file($invoice)),'gameserver payment renders a real common invoice PDF');
query("UPDATE shop_contracts SET state='ended',cancel_at=? WHERE order_id=?",[time(),$order['id']]);$ended=one('SELECT * FROM shop_orders WHERE id=?',[$order['id']]);check(!$service->end($ended),'contract end first enters retention');check($service->end($ended),'retention expiry confirms home and files deletion');check(count($homes)===1,'ending one service preserves the other customer home');check(!one('SELECT order_id FROM smf_game_ports WHERE order_id=?',[$order['id']]),'ports released only after confirmed deletion');check(!one('SELECT id FROM security_resource_assignments WHERE id=?',[SMF\Core\ShopResourceBindings::grantId($sid)]),'deleted service loses core grant');check(count($users)===2,'per-service cancellation never deletes customer accounts');
$before=count($homes);$late=gameOrder($configured,1,'GS-LATE');query("UPDATE shop_orders SET fulfillment_status='cancelled' WHERE id=?",[$late['id']]);record_paid($late,'CAP-LATE',595,'EUR');check(one('SELECT payment_status FROM shop_orders WHERE id=?',[$late['id']])['payment_status']==='review','late payment after cancellation goes to review');check(count($homes)===$before,'late payment creates no home');
$m->disable('product.gameserver');denied(fn()=>$service->inspect((int)one('SELECT service_id FROM smf_game_leases WHERE order_id=?',[$second['id']])['service_id'],2),'disabled product blocks existing service IO');
unset($GLOBALS['shop_test_transport']);ob_end_clean();echo json_encode(['checks'=>$checks,'failed'=>count($failures),'failures'=>$failures,'provider'=>'simulated OGP protocol','real_provider_writes'=>0],JSON_PRETTY_PRINT)."\n";exit($failures?1:0);
