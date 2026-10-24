<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('zend.exception_ignore_args','1');
require_once dirname(__DIR__).'/src/kernel/VersionConstraint.php';
require_once dirname(__DIR__).'/src/kernel/ComponentPackage.php';
try{
 $options=getopt('',['key:','fingerprint:','package:','assemble:','install','verify','recover:']);$packages=$options['package']??[];if(!is_array($packages))$packages=[$packages];if(!$packages && !isset($options['recover']))throw new RuntimeException('At least one --package is required');
 $installed=null;
 if(isset($options['install']) || isset($options['recover'])){
  require dirname(__DIR__).'/framework.php';require dirname(__DIR__).'/src/sys.conf.php';$registry=SMF\Core\Kernel::boot()->modules();$modules=[];foreach($registry->catalog()as$id=>$module)if($registry->enabled($id))$modules[$id]=array_intersect_key($module,array_flip(['version','requires','core','php']));$installed=['version'=>VersionFile::version(file_get_contents(dirname(__DIR__).'/src/sys.conf.php')),'modules'=>$modules];
 }
 if(isset($options['recover'])){echo json_encode(SMF\Core\ComponentPackage::recoverModules($options['recover'],dirname(__DIR__)),JSON_PRETTY_PRINT)."\n";exit;}
 $plan=SMF\Core\ComponentPackage::plan($packages,$options['key']??'',$options['fingerprint']??'',$installed);
 if(isset($options['install']))$plan['_module_state_hash']=hash('sha256',json_encode(SMF\Core\Kernel::boot()->database()->query('SELECT * FROM smf_module_state ORDER BY module_id')->fetchAll(PDO::FETCH_ASSOC),JSON_THROW_ON_ERROR));
 if(isset($options['assemble']))$result=SMF\Core\ComponentPackage::assemble($plan,$options['assemble']);
 elseif(isset($options['install']))$result=SMF\Core\ComponentPackage::installModules($plan,dirname(__DIR__));
 else $result=['verified'=>true,'version'=>$plan['version'],'components'=>array_keys($plan['components']),'modules'=>$plan['order']];
 echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable$error){fwrite(STDERR,$error instanceof RuntimeException?$error->getMessage()."\n":"Component verification or installation failed\n");exit(1);}
