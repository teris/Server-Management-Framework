<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ob_start();require __DIR__.'/bootstrap.php';$checks=0;$failures=[];
function check($ok,$name){global$checks,$failures;$checks++;if(!$ok)$failures[]=$name;}
function denied(callable$f,$name){try{$f();check(false,$name);}catch(Throwable$error){check(true,$name);}}
$m=SMF\Core\Kernel::boot()->modules();foreach(['shop.storage','shop.catalog','shop.portal','shop.orders','shop.connections','provider.ispconfig','provider.proxmox','provider.ovh']as$id){$m->install($id);$m->enable($id);}
$calls=[];$permissions=false;$dkim=false;$fail=false;
$GLOBALS['shop_test_transport']=function($provider,$method,$url,$body)use(&$calls,&$permissions,&$dkim,&$fail){
 $calls[]=[$provider,$method,$url];if($fail)throw new ProviderFailure($provider);
 if($provider==='ispconfig'){
  $action=parse_url($url,PHP_URL_QUERY);$p=json_decode($body,true);$response=match($action){'login'=>'fixture-session','logout'=>true,'server_get'=>['server_id'=>$p['server_id']],'get_function_list'=>array_merge(['client_get','client_add','sites_web_domain_add','mail_user_add','sites_database_add'],$dkim?['orgashop_mail_domain_dkim_ensure']:[]),default=>null};
  if($action==='orgashop_mail_domain_dkim_ensure')return ['data'=>['code'=>$dkim?'ok':'unknown_method','response'=>['available'=>$dkim]]];return ['data'=>['code'=>'ok','response'=>$response]];
 }
 if($provider==='proxmox'){
  $path=parse_url($url,PHP_URL_PATH);$data=match($path){'/api2/json/nodes'=>[['node'=>'node1']],'/api2/json/pools'=>[['poolid'=>'shop']],'/api2/json/access/permissions'=>$permissions?['/'=>['VM.Allocate'=>1,'VM.Clone'=>1,'Datastore.AllocateSpace'=>1,'VM.PowerMgmt'=>1]]:[],default=>[]};return ['data'=>['data'=>$data]];
 }
 if($provider==='ovh'){
  $path=parse_url($url,PHP_URL_PATH);$data=str_ends_with($path,'/auth/currentCredential')?['status'=>'validated','rules'=>$permissions?[['method'=>'POST','path'=>'/domain/zone/*'],['method'=>'PUT','path'=>'/domain/zone/*'],['method'=>'DELETE','path'=>'/domain/zone/*'],['method'=>'POST','path'=>'/order/cart*']]:[['method'=>'GET','path'=>'/*']]]:[];return ['data'=>$data];
 }
 throw new RuntimeException('Unknown fixture provider');
};
integration_save('ispconfig',['url'=>'https://isp.example.invalid/remote/json.php','username'=>'fixture','password'=>'fixture-password','server_id'=>1,'mail_server_id'=>2,'db_server_id'=>3,'allowed_functions'=>'','web_ip'=>'*'],true);
$r=SMF\Core\ProviderDiagnostics::check('ispconfig');check(in_array('web.read',$r['capabilities'],true),'standard ISPConfig without custom DKIM stays usable');check(!in_array('dkim.ensure',$r['capabilities'],true),'missing DKIM is an optional capability');check(!in_array('web.create',$r['capabilities'],true),'global function availability alone is not a user write grant');check(count($r['details']['servers'])===3,'separate web mail database server assignments retained');
$c=integration_config('ispconfig');$c['allowed_functions']='client_add,sites_web_domain_add';integration_save('ispconfig',$c,true);$r=SMF\Core\ProviderDiagnostics::check('ispconfig');check(in_array('web.create',$r['capabilities'],true)&&in_array('client.create',$r['capabilities'],true),'available methods intersect explicit operator grants');check(!in_array('mail.create',$r['capabilities'],true),'ungranted mail feature remains unavailable');
$dkim=true;$c['allowed_functions'].=',orgashop_mail_domain_dkim_ensure';integration_save('ispconfig',$c,true);$r=SMF\Core\ProviderDiagnostics::check('ispconfig');check(in_array('dkim.ensure',$r['capabilities'],true),'operator extension is detected separately');
integration_save('proxmox',['url'=>'https://pve.example.invalid:8006','auth_mode'=>'token','token_id'=>'fixture@pve!shop','token_secret'=>'fixture-secret','node'=>'','pool'=>''],true);
$r=SMF\Core\ProviderDiagnostics::check('proxmox');check(!in_array('vm.create',$r['capabilities'],true),'Proxmox read access does not imply VM allocation');$permissions=true;$r=SMF\Core\ProviderDiagnostics::check('proxmox');check(in_array('vm.create',$r['capabilities'],true)&&in_array('vm.control',$r['capabilities'],true),'Proxmox explicit privileges provide separate capabilities');
$permissions=false;integration_save('ovh',['endpoint'=>'ovh-eu','subsidiary'=>'DE','application_key'=>'fixture-application','application_secret'=>'fixture-secret','consumer_key'=>'fixture-consumer'],true);$r=SMF\Core\ProviderDiagnostics::check('ovh');check(!in_array('zone.write',$r['capabilities'],true),'OVH GET rules cannot authorize zone changes');$permissions=true;$r=SMF\Core\ProviderDiagnostics::check('ovh');check(in_array('zone.write',$r['capabilities'],true)&&in_array('domain.order',$r['capabilities'],true),'OVH exact write paths are reported independently');
foreach(['ispconfig','proxmox','ovh']as$id){$cached=SMF\Core\Kernel::boot()->providers()->get($id);$before=count($calls);query('UPDATE shop_integrations SET enabled=0 WHERE provider=?',[$id]);denied(fn()=>$cached->call('GET',$id==='ispconfig'?'server_get':'/nodes',[]),'disabled connection blocks cached '.$id);check(count($calls)===$before,'disabled '.$id.' has zero provider IO');query('UPDATE shop_integrations SET enabled=1 WHERE provider=?',[$id]);}
$fail=true;denied(fn()=>SMF\Core\ProviderDiagnostics::check('proxmox'),'failed diagnostics reject outdated capability proofs');check(SMF\Core\ProviderDiagnostics::capabilities('proxmox')===[],'failed retest clears previous proof');
check(!array_filter($calls,fn($call)=>$call[0]!=='ispconfig' && $call[1]!=='GET'),'matrix performs no external writes');unset($GLOBALS['shop_test_transport']);ob_end_clean();echo json_encode(['checks'=>$checks,'failed'=>count($failures),'failures'=>$failures,'real_provider_calls'=>0],JSON_PRETTY_PRINT)."\n";exit($failures?1:0);
