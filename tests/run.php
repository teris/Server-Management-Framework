<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

ob_start();
require __DIR__ . '/bootstrap.php';
$checks=[];$failures=[];
function check($condition,$name) {
    global $checks,$failures; $checks[]=$name;
    if (!$condition) $failures[]=$name;
}
function denied(callable $action,$status,$name) {
    try { $action(); check(false,$name); }
    catch (SecurityViolation $e) { check($e->getCode()===$status,$name); }
}
require dirname(__DIR__).'/src/core/AdminHandler.php';
$calls=0;$factory=function()use(&$calls){$calls++;return new stdClass();};
denied(function()use($factory){new AdminHandler($factory);},401,'guest cannot construct administrative services');
testLogin('backend',2);
denied(function()use($factory){new AdminHandler($factory);},403,'normal user cannot construct administrative services');
check($calls===0,'rejected admin request does not invoke provider factory');
foreach(ResourceAccess::MODULES as $module)check(!ResourceAccess::legacyAllowed($module),'normal user denied unscoped module '.$module);
testLogin('backend',1);new AdminHandler($factory);check($calls===1,'administrator reaches services after authorization');
foreach(ResourceAccess::MODULES as $module)check(ResourceAccess::legacyAllowed($module)===($module!=='file-editor'),'administrator module policy '.$module);
$plugins['dns']['enabled']=false;check(!ResourceAccess::legacyAllowed('dns'),'disabled module denied even to administrator');$plugins['dns']['enabled']=true;
require dirname(__DIR__).'/src/module/file-editor/Module.php';
testLogin('backend',2);$editor=new FileEditorModule();
denied(function()use($editor){$editor->handleAjaxRequest('save_file',['file_path'=>__FILE__,'content'=>'invalid']);},403,'editor cannot write arbitrary application code');
// Distinct realms keep a customer with the same numeric ID out of backend rights.
Identity::clear('backend');testLogin('customer',1);
denied(function(){Identity::requireIdentity('backend',true);},401,'customer ID does not become backend administrator');
check(!SecuritySchema::migrate($db),'security migration is idempotent');
// Explicit ownership, connection fingerprint, write permission and expiry checks precede provider I/O.
$assignment=str_repeat('a',32);$stmt=$db->prepare('INSERT INTO security_resource_assignments (id,subject_realm,subject_id,provider,connection_id,resource_type,external_id,metadata,can_write) VALUES (?,?,?,?,?,?,?,?,?)');
$stmt->execute([$assignment,'customer',1,'proxmox',ResourceAccess::connectionId('proxmox'),'qemu','101',json_encode(['node'=>'node-a','instance_id'=>'00000000-0000-0000-0000-000000000101']),1]);
$transportCalls=[];$transport=function($provider,$method,$path,$data)use(&$transportCalls){$transportCalls[]=[$provider,$method,$path,$data];return ['data'=>['status'=>'running','password'=>'never disclose','vmid'=>101,'vmgenid'=>'00000000-0000-0000-0000-000000000101']];};
$a=Identity::requireIdentity('customer');$value=ResourceAccess::execute($a,'proxmox',$assignment,'view',$transport);
check($value===['status'=>'running','vmid'=>101],'owner sees whitelisted data for assigned VM');
check(end($transportCalls)[2]==='/nodes/node-a/qemu/101/status/current','remote identifier comes from verified assignment');
$b=testLogin('customer',2);$before=count($transportCalls);
denied(function()use($b,$assignment,$transport){ResourceAccess::execute($b,'proxmox',$assignment,'view',$transport);},404,'customer B cannot view customer A resource');
denied(function()use($b,$assignment,$transport){ResourceAccess::execute($b,'proxmox',$assignment,'stop',$transport);},404,'customer B cannot control customer A resource');
check(count($transportCalls)===$before,'ownership rejection occurs before any provider I/O');
denied(function()use($a,$assignment,$transport){ResourceAccess::execute($a,'dns',$assignment,'view',$transport);},403,'resource cannot cross provider/module boundary');
$db->exec("UPDATE security_resource_assignments SET can_write=0");
denied(function()use($a,$assignment,$transport){ResourceAccess::execute($a,'proxmox',$assignment,'start',$transport);},403,'read-only resource grant cannot start VM');
$db->exec("UPDATE security_resource_assignments SET can_write=1, connection_id='changed'");
denied(function()use($a,$assignment,$transport){ResourceAccess::execute($a,'proxmox',$assignment,'view',$transport);},403,'provider account change invalidates assignment');
$db->prepare('UPDATE security_resource_assignments SET connection_id=?,expires_at=?')->execute([ResourceAccess::connectionId('proxmox'),time()-1]);
denied(function()use($a,$assignment,$transport){ResourceAccess::execute($a,'proxmox',$assignment,'view',$transport);},404,'expired assignment cannot be used');
$db->exec('UPDATE security_resource_assignments SET expires_at=NULL');
denied(function()use($a,$assignment,$transport){ResourceAccess::execute($a,'proxmox',$assignment,'delete',$transport);},403,'unsupported destructive action is denied');
ResourceAccess::execute($a,'proxmox',$assignment,'start',$transport);check(end($transportCalls)[1]==='POST','authorized VM start is explicit write operation');
$changedInstance=function(){return ['data'=>['vmgenid'=>'00000000-0000-0000-0000-000000000999']];};
denied(function()use($a,$assignment,$changedInstance){ResourceAccess::execute($a,'proxmox',$assignment,'start',$changedInstance);},403,'reused VMID with different instance cannot be controlled');
// All scoped module/type combinations reject foreign ownership before invoking a provider.
foreach (ResourceAccess::TYPES as $module => [$provider, $types]) {
    foreach ($types as $type) {
        $grant=bin2hex(random_bytes(16));
        $external=$provider==='proxmox' || $provider==='ispconfig' ? (string)(123+count($checks)) : ($type==='virtual-mac' ? '02:00:00:00:00:01' : 'example.test');
        $stmt=$db->prepare('INSERT INTO security_resource_assignments (id,subject_realm,subject_id,provider,connection_id,resource_type,external_id,metadata,can_write) VALUES (?,?,?,?,?,?,?,?,0)');
        $stmt->execute([$grant,'customer',1,$provider,ResourceAccess::connectionId($provider),$type,$external,json_encode(['node'=>'node-b','service'=>'ns123','group_id'=>7,'instance_id'=>$type==='lxc'?str_repeat('a',32):'00000000-0000-0000-0000-000000000123'])]);
        $seen=0;$fake=function()use(&$seen){$seen++;return ['status'=>'running','domain'=>'example.test','password_hash'=>'hidden','sys_groupid'=>7,'vmgenid'=>'00000000-0000-0000-0000-000000000123','description'=>'SMF-RESOURCE:'.str_repeat('a',32)];};
        ResourceAccess::execute($a,$module,$grant,'view',$fake);check($seen===($provider==='proxmox'?2:1),'owner permitted scoped '.$module.'/'.$type);
        denied(function()use($b,$module,$grant,$fake){ResourceAccess::execute($b,$module,$grant,'view',$fake);},404,'foreign owner denied scoped '.$module.'/'.$type);
        check($seen===($provider==='proxmox'?2:1),'foreign scope makes no provider call '.$module.'/'.$type);
    }
}
// Private exports cannot be addressed by arbitrary filenames or a different principal.
$export=PrivateFiles::create($a,'zone.txt','harmless test zone');check(PrivateFiles::read($a,$export)['content']==='harmless test zone','owner can download own export');
denied(function()use($b,$export){PrivateFiles::read($b,$export);},404,'foreign customer cannot read export');
denied(function()use($export){PrivateFiles::read(['realm'=>'backend','id'=>1,'role'=>'admin'],$export);},404,'numeric owner ID cannot cross realm');
foreach(['../zone.txt','/etc/passwd','zone.php','zone.txt/../secret'] as $path)denied(function()use($a,$path){PrivateFiles::read($a,$path);},404,'export rejects path '.$path);
$meta=HttpSecurity::privateDirectory('exports').'/'.$export.'.json';$m=json_decode(file_get_contents($meta),true);$m['expires']=time()-1;file_put_contents($meta,json_encode($m));
denied(function()use($a,$export){PrivateFiles::read($a,$export);},410,'expired export rejected');
$export=PrivateFiles::create($a,'zone.txt','test');$path=HttpSecurity::privateDirectory('exports').'/'.$export.'.data';unlink($path);symlink(__FILE__,$path);
denied(function()use($a,$export){PrivateFiles::read($a,$export);},404,'symlink cannot escape export storage');
denied(function()use($a){PrivateFiles::create($a,'backup.zip','test','backup');},403,'non-admin cannot create backup download');
// Session rotation, credential changes, expiry, role changes and account suspension.
$before=session_id();testLogin('customer',1);check(session_id()!==$before,'customer password login rotates session ID');
$customer=Identity::current('customer')['record'];$token=RememberTokens::issue($customer);
check(strpos(json_encode($db->query('SELECT * FROM security_remember_tokens')->fetchAll()),explode('.',$token)[1])===false,'remember validator never stored in plaintext');
$next=RememberTokens::consume($token);check($next!==null && $next['token']!==$token,'remember login rotates token');check(RememberTokens::consume($token)===null,'used remember token cannot be replayed');
check(RememberTokens::consume(str_repeat('f',64))===null,'legacy plaintext token format rejected');
$expired=RememberTokens::issue($customer);$db->prepare('UPDATE security_remember_tokens SET expires_at=? WHERE selector=?')->execute([time()-1,explode('.',$expired)[0]]);check(RememberTokens::consume($expired)===null,'expired remember token rejected');
$suspended=RememberTokens::issue($customer);$db->exec("UPDATE customers SET status='suspended' WHERE id=1");check(RememberTokens::consume($suspended)===null,'suspended account cannot use remember token');check(Identity::current('customer')===null,'suspension revokes existing session on next request');$db->exec("UPDATE customers SET status='active' WHERE id=1");
testLogin('customer',1);$oldIdentity=$_SESSION['identities']['customer'];$oldToken=RememberTokens::issue($customer);
$result=AccountPasswords::change('customer',Identity::current('customer'),'Customer password 1','Customer password changed 2');check($result['success'],'customer can change password with current password');
check(RememberTokens::consume($oldToken)===null,'password change revokes all remember tokens');$_SESSION['identities']['customer']=$oldIdentity;check(Identity::current('customer')===null,'password change invalidates another old session');
testLogin('backend',1);$db->exec("UPDATE users SET role='user' WHERE id=1");check(!SessionManager::isAdmin(),'role removal takes effect without relogin');$db->exec("UPDATE users SET role='admin' WHERE id=1");
$_SESSION['identities']['backend']['last_activity']=time()-HttpSecurity::timeout()-1;check(!SessionManager::isLoggedIn(),'configured inactivity timeout expires session');
testLogin('backend',2);check($_SESSION['user']['role']==='user' && $_SESSION['role']==='user','legacy and module session views use same verified role');
check(HttpSecurity::timeout()===(int)$system_config['session_timeout'],'configured timeout used instead of legacy constant');
$idle=time()-120;$_SESSION['identities']['backend']['last_activity']=$idle;$_POST['action']='heartbeat';SessionManager::updateActivity();check($_SESSION['identities']['backend']['last_activity']===$idle,'background heartbeat does not extend idle session');unset($_POST['action']);
check(HttpSecurity::validCsrf(HttpSecurity::csrfToken()),'current CSRF token accepted');check(!HttpSecurity::validCsrf(str_repeat('0',64)),'forged CSRF token rejected');check(!HttpSecurity::validCsrf([]),'array CSRF token rejected');
$_SERVER['HTTPS']='off';$_SERVER['REMOTE_ADDR']='127.0.0.1';$_SERVER['HTTP_X_FORWARDED_PROTO']='https';
putenv('SMF_TRUSTED_PROXIES=');check(!HttpSecurity::isHttps(),'untrusted forwarded protocol cannot mark cookie secure');
putenv('SMF_TRUSTED_PROXIES=127.0.0.1');check(HttpSecurity::isHttps(),'explicit trusted proxy may supply HTTPS protocol');putenv('SMF_TRUSTED_PROXIES=');unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
$limitName=bin2hex(random_bytes(8));HttpSecurity::rateLimit($limitName,'test',2,60);HttpSecurity::rateLimit($limitName,'test',2,60);denied(function()use($limitName){HttpSecurity::rateLimit($limitName,'test',2,60);},429,'request limit rejects next request');
$options=ProviderTransport::options('https://localhost:8443/api','proxmox');check($options[CURLOPT_SSL_VERIFYPEER]===true && $options[CURLOPT_SSL_VERIFYHOST]===2,'TLS verifies chain and hostname');check($options[CURLOPT_FOLLOWLOCATION]===false,'credentials are not forwarded through redirects');
foreach(['http://localhost/api','https://name:password@localhost/api'] as $url){try{ProviderTransport::options($url,'proxmox');check(false,'unsafe transport URL rejected');}catch(InvalidArgumentException $e){check(true,'unsafe transport URL rejected');}}
$clean=HttpSecurity::redact(['password'=>'marker','nested'=>['ticket'=>'marker','ok'=>1]]);check(!str_contains(json_encode($clean),'marker'),'response redaction handles nested secrets');
$token=RememberTokens::issue(Identity::current('customer')['record'] ?? $db->query('SELECT * FROM customers WHERE id=1')->fetch());testLogin('customer',1);Identity::logout();check(RememberTokens::consume($token)===null,'logout revokes long-lived tokens');
ob_end_clean();
echo json_encode(['checks'=>count($checks),'failed'=>count($failures),'failures'=>$failures],JSON_PRETTY_PRINT),"\n";
exit($failures?1:0);
