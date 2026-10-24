<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ob_start();require __DIR__.'/bootstrap.php';
$checks=0;$failures=[];
function check($value,$name){global $checks,$failures;$checks++;if(!$value)$failures[]=$name;}
function denied(callable $f,$name){try{$f();check(false,$name);}catch(Throwable $e){check(true,$name);}}
$m=SMF\Core\Kernel::boot()->modules();
foreach(['shop.storage','shop.catalog','shop.portal','shop.orders','shop.payments','shop.provisioning','shop.billing','shop.contracts','shop.connections','provider.ogp','provider.ispconfig','provider.proxmox','provider.ovh'] as $id){$m->install($id);$m->enable($id);}
check(count($m->providers())===4,'all four provider contributions registered');
check(isset(integration_definitions()['ogp']['fields']['token']),'shared OGP configuration is contributed');
$token=str_repeat('a',64);integration_save('ogp',['url'=>'https://ogp.example.invalid','token'=>$token],true);
$row=integration_row('ogp');check(!str_contains($row['secret_config'],$token),'shared token encrypted');
check(integration_config('ogp')['token']===$token,'shared token decrypts');
$id=ResourceAccess::connectionId('ogp');check($id===integration_identity('ogp'),'core and shop use same connection');
$calls=[];$GLOBALS['shop_test_transport']=function($provider,$method,$url,$body)use(&$calls,$token){
    $p=[];parse_str($body,$p);$action=parse_url($url,PHP_URL_QUERY);$calls[]=[$method,$url,$p];
    check(!str_contains($url,$token),'token never enters URL');check(($p['token']??'')===$token,'authentication is in request body');
    $data=match($action){'token/test'=>'admin','server/list'=>[['remote_server_id'=>1]],default=>[]};
    return ['data'=>['status'=>'200','message'=>$data]];
};
$c=SMF\Core\Kernel::boot()->providers()->get('ogp');check($c instanceof SMF\Core\ProviderClient,'OGP adapter implements shared interface');
$r=SMF\Core\ProviderDiagnostics::check('ogp');check(in_array('home.create',$r['capabilities'],true),'verified admin contract enables home creation');
check(!in_array('installation.status',$r['capabilities'],true),'base API never claims install completion capability');
check(!in_array('home.delete',$r['capabilities'],true),'base API never claims unsupported home deletion');
$legacy=new OGPGet();check($legacy->getServerList()['status']==='200','legacy framework reads shared encrypted account');
$before=count($calls);denied(fn()=>$c->call('POST','user_games/create',[]),'sandbox blocks OGP write');check(count($calls)===$before,'sandbox rejection happens before provider IO');
denied(fn()=>$c->call('POST','server/create',[]),'commerce adapter cannot register remote agents');
check(count($calls)===$before,'unsupported agent operations never reach provider');
integration_save('ogp',['url'=>'https://ogp.example.invalid','token'=>$token,'bridge_key'=>str_repeat('e',64)],true);
$before=count($calls);denied(fn()=>$c->call('GET','server/list'),'cached OGP adapter rejects changed bridge credentials');check(count($calls)===$before,'changed bridge credentials are rejected before IO');
integration_save('ogp',['url'=>'https://ogp.example.invalid','token'=>str_repeat('b',64)],true);
check(ResourceAccess::connectionId('ogp')!==$id,'token rotation changes binding');
check(SMF\Core\ProviderDiagnostics::capabilities('ogp')===[],'token rotation invalidates diagnostics');
denied(fn()=>$c->call('GET','server/list'),'resolved adapter rejects changed credential');
$m->disable('provider.ogp');denied(fn()=>SMF\Core\Kernel::boot()->providers()->get('ogp'),'cached registry adapter blocked after module disable');
check(!SMF\Core\ProviderAccounts::enabled('ogp'),'disabled provider module stops legacy IO');
unset($GLOBALS['shop_test_transport']);
// A new trusted provider and product require no change to framework.php.
$dir=$testRoot.'/extensions';mkdir($dir);mkdir($dir.'/provider.fixture');mkdir($dir.'/product.fixture');
$manifest=['id'=>'provider.fixture','version'=>'1.0.0','name'=>'Fixture','core'=>'>=3.9.9 <4.0.0','php'=>'>=8.3.0','requires'=>[],'routes'=>[],'jobs'=>[],'permissions'=>[],'migrations'=>[],'providers'=>['fixture'=>['runtime'=>'runtime.php','capabilities'=>['compute.read'],'fields'=>[],'resources'=>['vm']]]];
file_put_contents($dir.'/provider.fixture/module.json',json_encode($manifest));
file_put_contents($dir.'/provider.fixture/runtime.php',"<?php return static fn()=>new class implements \\SMF\\Core\\ProviderClient {public function connectionId():string{return str_repeat('a',64);}public function call(string \$method,string \$path,mixed \$payload=null):mixed{return ['fixture'=>true];}};");
$manifest['id']='product.fixture';$manifest['providers']=[];$manifest['requires']=['provider.fixture'=>'^1.0.0'];$manifest['products']=['fixture'=>['runtime'=>'runtime.php','requires_capabilities'=>['fixture'=>['compute.read']]]];
file_put_contents($dir.'/product.fixture/module.json',json_encode($manifest));file_put_contents($dir.'/product.fixture/runtime.php','<?php return static fn()=>new stdClass();');
$f=new SMF\Core\ModuleRegistry($db,$dir,'3.9.9');$f->install('provider.fixture');$f->enable('provider.fixture');$f->install('product.fixture');$f->enable('product.fixture');
$registry=new SMF\Core\ProviderRegistry($f);check($registry->get('fixture')->call('GET','/')['fixture'],'new trusted provider loads without framework change');
$products=new SMF\Core\ProductRegistry($f);check(count($products->blockers('fixture',[]))===1,'missing capability blocks only affected product');check($products->blockers('fixture',['fixture'=>['compute.read']])===[],'verified capability opens product');
denied(fn()=>$f->disable('provider.fixture'),'active product dependency prevents provider removal');$f->disable('product.fixture');$f->disable('provider.fixture');denied(fn()=>$registry->get('fixture'),'disabled custom module blocks cached adapter');
$bad=new SMF\Core\ModuleRegistry($db,$dir,'4.0.0');denied(fn()=>$bad->assertEnabled('provider.fixture'),'incompatible version rejected');
ob_end_clean();echo json_encode(['checks'=>$checks,'failed'=>count($failures),'failures'=>$failures],JSON_PRETTY_PRINT)."\n";exit($failures?1:0);
