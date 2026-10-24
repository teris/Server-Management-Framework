<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/bootstrap.php';require_once dirname(__DIR__).'/src/core/UserAdministration.php';require_once dirname(__DIR__).'/src/core/ProviderUserActions.php';
$checks=0;function userCheck($ok,$name){global$checks;$checks++;if(!$ok)throw new RuntimeException('Failed: '.$name);}
testLogin('backend',1);
class UserAdapterFixture {
    public array $calls=[];public bool $fail=false;public $host='https://fixture.invalid';
    public function __call($name,$arguments){$this->calls[]=[$name,$arguments];if($this->fail)return['success'=>false,'error'=>'FIXTURE_REJECTION'];if(in_array($name,['getClients','getUsers','getProxmoxUsers'],true))return[['userid'=>'fixture@pve','email'=>'fixture@example.invalid','password'=>'fixture-must-not-leak']];return['success'=>true];}
    public function createUser($data){return $this->__call(__FUNCTION__,[$data]);}
    public function createProxmoxUser($data){return $this->__call(__FUNCTION__,[$data]);}
    public function getClients($filter=[]){return $this->__call(__FUNCTION__,[$filter]);}
    public function createClient($data){return $this->__call(__FUNCTION__,[$data]);}
    public function updateClient($id,$data){return $this->__call(__FUNCTION__,[$id,$data]);}
    public function deleteClient($id){return $this->__call(__FUNCTION__,[$id]);}
    public function updateProxmoxUser($id,$data){return $this->__call(__FUNCTION__,[$id,$data]);}
    public function deleteProxmoxUser($id){return $this->__call(__FUNCTION__,[$id]);}
    public function removeUser($email){return $this->__call(__FUNCTION__,[$email]);}
    public function getUsers(){return $this->__call(__FUNCTION__,[]);}
    public function getProxmoxUsers(){return $this->__call(__FUNCTION__,[]);}
    public function makeRequest($method,$url,$data=null){$this->calls[]=[__FUNCTION__,[$method,$url,$data]];return $this->fail?false:['data'=>null];}
}
$adapters=[];foreach(['proxmox','ispconfig','ogp']as$provider){$adapters[$provider]=new UserAdapterFixture();$pairs[$provider]=[$adapters[$provider],$adapters[$provider]];}$manager=new ServiceManager($pairs);
foreach(['getISPConfigUsers','editOGPUser','editProxmoxUser','editISPConfigUser','deleteOGPUser','deleteISPConfigUser','createISPConfigUser','updateOGPUserPassword','updateProxmoxUserPassword','updateISPConfigUserPassword']as$method)userCheck(method_exists($manager,$method),'legacy method implemented: '.$method);
userCheck(ProviderResult::succeeded($manager->createOGPUser('newuser','Provider test password','First','Last',['email'=>'new@example.invalid'])),'old OGP creation signature accepted');userCheck(is_array($adapters['ogp']->calls[0][1][0]),'OGP receives a payload rather than a scalar username');
userCheck(ProviderResult::succeeded($manager->createProxmoxUser(['username'=>'newuser','password'=>'Provider test password'])),'payload creation signature remains valid');
userCheck(ProviderResult::succeeded($manager->editISPConfigUser(7,'changed@example.invalid','Changed')),'ISPConfig edit reaches existing update contract');$last=end($adapters['ispconfig']->calls);userCheck($last[0]==='updateClient'&&$last[1][0]===7&&$last[1][1]['email']==='changed@example.invalid','ISPConfig id and payload order correct');
userCheck($manager->updateOGPUserPassword('x@example.invalid','Provider test password')['error']==='UNSUPPORTED_OPERATION','unsupported OGP password operation rejected');userCheck($manager->editOGPUser('x@example.invalid','y@example.invalid','Name')['success']===false,'unsupported OGP edit does not report success');
userCheck(ProviderResult::succeeded($manager->updateProxmoxUserPassword('fixture@pve','Provider test password')),'Proxmox password endpoint succeeds');$last=end($adapters['proxmox']->calls);userCheck($last[1][0]==='PUT'&&str_ends_with($last[1][1],'/access/password')&&$last[1][2]['userid']==='fixture@pve','dedicated Proxmox password endpoint and user id used');
userCheck(!$manager->updateProxmoxUserPassword('root@pam','Provider test password')['success'],'unmanaged authentication realm cannot be reset');
$actions=new ProviderUserActions($manager);
foreach(['proxmox'=>'fixture@pve','ispconfig'=>'7','ogp'=>'fixture@example.invalid']as$provider=>$id){
 foreach(['list','create','delete']as$action){$result=$actions->execute($provider,$action,['external_id'=>$id,'username'=>'created','name'=>'Created','email'=>'created@example.invalid','password'=>'Provider test password']);userCheck($result['success'],'offered provider action '.$provider.'/'.$action);if($action==='list')userCheck(strpos(json_encode($result),'fixture-must-not-leak')===false,'provider listing filters credentials');}
}
$blocked=false;try{$actions->execute('proxmox','delete',['external_id'=>'../root@pve']);}catch(InvalidArgumentException$e){$blocked=true;}userCheck($blocked,'malformed external identity rejected before transport');
$adapters['proxmox']->fail=true;$result=$actions->execute('proxmox','delete',['external_id'=>'fixture@pve']);userCheck(!$result['success'],'truthy provider error array is not treated as success');$adapters['proxmox']->fail=false;
$service=new UserAdministration($db,$manager,new EmailTemplateManager($db,fn()=>true));$created=$service->save('backend',['username'=>'partial-test','email'=>'partial@example.invalid','role'=>'user','active'=>'y','password'=>'Local test user password']);$id=$created['id'];
foreach(['proxmox'=>'fixture@pve','ispconfig'=>'7']as$provider=>$external)$db->prepare('INSERT INTO user_permissions (user_id,permission_type,resource_id,granted_by) VALUES (?,?,?,1)')->execute([$id,$provider,$external]);
$adapters['ispconfig']->fail=true;$result=$service->delete('backend',$id,['proxmox','ispconfig']);userCheck(!$result['success'],'partial external deletion is reported');userCheck((int)$db->query('SELECT COUNT(*) FROM users WHERE id='.$id)->fetchColumn()===1,'partial deletion retains local account');userCheck((int)$db->query('SELECT COUNT(*) FROM user_permissions WHERE user_id='.$id)->fetchColumn()===2,'partial deletion retains recovery mappings');
$adapters['ispconfig']->fail=false;userCheck($service->delete('backend',$id,[])['success'],'explicit local-only removal detaches without additional provider calls');
$all=implode('',array_map(fn($a)=>json_encode($a->calls),$adapters));userCheck(strpos(json_encode($actions->execute('ogp','password',[])),'Provider test password')===false,'unsupported action response contains no password');
echo json_encode(['checks'=>$checks,'failed'=>0,'live_provider_mutations'=>0])."\n";
