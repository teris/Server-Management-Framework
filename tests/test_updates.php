<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/bootstrap.php';
require_once dirname(__DIR__).'/src/core/UpdateManager.php';
$checks=0;function updateCheck($ok,$name){global$checks;$checks++;if(!$ok)throw new RuntimeException('Failed: '.$name);}
$source=$testRoot.'/live-source';mkdir($source);$link=$testRoot.'/current';symlink($source,$link);putenv('SMF_CURRENT_LINK='.$link);
$key=openssl_pkey_new(['private_key_bits'=>3072,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);$public=$testRoot.'/publisher-public.pem';file_put_contents($public,openssl_pkey_get_details($key)['key']);putenv('SMF_UPDATE_PUBLIC_KEY='.$public);
$arguments=preg_split('/\s+/',trim(getenv('PHP_ARGS')?:''));putenv('SMF_PHP_ARGS_JSON='.json_encode(array_values(array_filter($arguments))));
$database=new PortablePDO('sqlite:'.$testRoot.'/deployment.sqlite');$database->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$database->exec('CREATE TABLE deployment_data (id INTEGER PRIMARY KEY, value TEXT, runs INTEGER)');$database->exec("INSERT INTO deployment_data VALUES (1,'original',0)");
$sys=file_get_contents(dirname(__DIR__).'/src/sys.conf.php');$sys=str_replace("'admin_email' => 'admin@example.com'","'admin_email' => 'operator@example.invalid'",$sys);$sys=preg_replace("/'version' => '1.0.0'/","'version' => '8.8.8'",$sys,1);
$migrate="<?php \$pdo=new PDO('sqlite:".$testRoot."/deployment.sqlite');\$pdo->exec(\"UPDATE deployment_data SET value='migrated',runs=runs+1\");\n";
$files=['framework.php'=>"<?php // old framework\n",'src/index.php'=>"<?php echo 'old-active';\n",'src/sys.conf.php'=>$sys,'config/load.php'=>"<?php // private config loader\n",'bin/migrate.php'=>$migrate,'database/schema.json'=>json_encode(['version'=>SchemaManager::definition()['version']]),'src/module/example/Module.php'=>"<?php // old independent module\n",'public/customer.txt'=>'old customer file'];$files['deploy/package-files.json']=json_encode([...array_keys($files),'deploy/package-files.json']);
foreach($files as$path=>$content){if(!is_dir(dirname($source.'/'.$path)))mkdir(dirname($source.'/'.$path),0755,true);file_put_contents($source.'/'.$path,$content);}
function updatePackage(array $files,string $type,string $version,array $changes=[]):string{
    global$testRoot,$key; $path=$testRoot.'/package-'.bin2hex(random_bytes(5)).'.zip';$hashes=[];foreach($files as$name=>$content)$hashes[$name]=hash('sha256',$content);
    $manifest=array_merge(['format'=>1,'type'=>$type,'version'=>$version,'commit'=>str_repeat('a',40),'schema_version'=>SchemaManager::definition()['version'],'php_min'=>'8.3','files'=>$hashes],$changes);$raw=json_encode($manifest);openssl_sign($raw,$signature,$key,OPENSSL_ALGO_SHA256);
    $zip=new ZipArchive();$zip->open($path,ZipArchive::CREATE);$zip->addFromString('manifest.json',$raw);$zip->addFromString('manifest.sig',base64_encode($signature));foreach($files as$name=>$content)$zip->addFromString($name,$content);$zip->close();return$path;
}
function rejectPackage($manager,$package,$name){$before=count($manager->jobs());$rejected=false;try{$manager->register($package,1);}catch(Throwable$e){$rejected=true;}updateCheck($rejected&&count($manager->jobs())===$before,$name);}
$manager=new UpdateManager($source,$database);$new=$files;$new['framework.php']="<?php // signed new framework\n";$new['src/index.php']="<?php echo 'new-active';\n";$new['src/module/example/Module.php']="<?php // new module only in full package\n";
$full=updatePackage($new,'full','3.2.8');$job=$manager->register($full,1);updateCheck($job['state']==='verified'&&$job['type']==='full','signed full package registered');
$signature=$testRoot.'/bad-signature.zip';copy($full,$signature);$zip=new ZipArchive();$zip->open($signature);$zip->addFromString('manifest.sig',base64_encode('bad signature'));$zip->close();rejectPackage($manager,$signature,'invalid signature rejected');
$corrupt=$testRoot.'/corrupt.zip';copy($full,$corrupt);$zip=new ZipArchive();$zip->open($corrupt);$zip->addFromString('framework.php','tampered');$zip->close();rejectPackage($manager,$corrupt,'tampered payload rejected');
$unsafe=$new;$unsafe['../outside.php']='fixture';rejectPackage($manager,updatePackage($unsafe,'full','3.2.8'),'ZIP traversal rejected');
$unsafe=$new;$unsafe['config/config.inc.php']='fixture-private';rejectPackage($manager,updatePackage($unsafe,'full','3.2.8'),'runtime config overwrite rejected');
$unsafe=$new;$unsafe['src/Foo.php']='a';$unsafe['src/foo.php']='b';rejectPackage($manager,updatePackage($unsafe,'full','3.2.8'),'case collision rejected');
$symlink=$testRoot.'/symlink.zip';copy($full,$symlink);$zip=new ZipArchive();$zip->open($symlink);$zip->setExternalAttributesName('framework.php',ZipArchive::OPSYS_UNIX,0120777<<16);$zip->close();rejectPackage($manager,$symlink,'ZIP symlink rejected');
rejectPackage($manager,updatePackage($new,'full','3.2.7'),'same or older version rejected');rejectPackage($manager,updatePackage($new,'full','3.2.8',['php_min'=>'99.0']),'incompatible PHP rejected');
$coreInvalid=['public/customer.txt'=>'x'];rejectPackage($manager,updatePackage($coreInvalid,'core','3.2.8'),'core package cannot replace customer/module files');
$manager->queue($job['id'],'stage');$fresh=new UpdateManager($source,$database);$staged=$fresh->run($job['id']);updateCheck($staged['state']==='staged'&&realpath($link)===$source,'separate worker stages without activation');
$reader=fopen(HttpSecurity::privateDirectory('locks').'/deployment.lock','c');flock($reader,LOCK_SH);
$fresh->queue($job['id'],'activate');$blocked=false;try{$fresh->run($job['id']);}catch(Throwable$e){$blocked=true;}
updateCheck($blocked&&realpath($link)===$source,'active request lock prevents activation');flock($reader,LOCK_UN);fclose($reader);
$fresh->queue($job['id'],'stage');updateCheck($fresh->run($job['id'])['requested']===null,'stage retry is idempotent');
$privateConfig=getenv('SMF_CONFIG_FILE');$privateBytes=file_get_contents($privateConfig);
$fresh->queue($job['id'],'activate');$activated=(new UpdateManager($source,$database))->run($job['id']);$target=realpath($link);
updateCheck($activated['state']==='activated'&&$target!==$source,'managed symlink activated atomically');updateCheck(VersionFile::version(file_get_contents($target.'/src/sys.conf.php'))==='3.2.8','framework version updated');
updateCheck(VersionFile::blocks(file_get_contents($target.'/src/sys.conf.php'))['$plugins']===VersionFile::blocks($sys)['$plugins'],'independent module versions and operator flags preserved');
updateCheck(str_contains(file_get_contents($target.'/src/sys.conf.php'),'operator@example.invalid'),'operator email preserved');updateCheck(file_get_contents($privateConfig)===$privateBytes,'private config byte-preserved');
updateCheck($database->query('SELECT value FROM deployment_data')->fetchColumn()==='migrated','real candidate CLI migration ran');updateCheck(file_get_contents($target.'/src/module/example/Module.php')===$new['src/module/example/Module.php'],'full update installs declared module files');
$fresh->queue($job['id'],'activate');$fresh->run($job['id']);updateCheck((int)$database->query('SELECT runs FROM deployment_data')->fetchColumn()===1,'activation retry does not repeat migration');
$fresh->queue($job['id'],'rollback');updateCheck($fresh->run($job['id'])['state']==='rolled_back'&&realpath($link)===$source,'file activation rolled back');updateCheck($database->query('SELECT value FROM deployment_data')->fetchColumn()==='original','database restored using actual SQLite backup API');
$fresh->queue($job['id'],'rollback');updateCheck($fresh->run($job['id'])['requested']===null,'rollback retry idempotent');
$core=array_intersect_key($new,array_flip(['framework.php','src/sys.conf.php','bin/migrate.php','database/schema.json','deploy/package-files.json']));$coreJob=$manager->register(updatePackage($core,'core','3.2.9'),1);$manager->queue($coreJob['id'],'stage');$manager->run($coreJob['id']);$manager->queue($coreJob['id'],'activate');$manager->run($coreJob['id']);
updateCheck(file_get_contents(realpath($link).'/src/module/example/Module.php')===$files['src/module/example/Module.php'],'core update retains prior module');updateCheck(file_get_contents(realpath($link).'/public/customer.txt')==='old customer file','core update retains customer frontend');
$database->exec("UPDATE deployment_data SET value='after-live-write'");$manager->queue($coreJob['id'],'rollback');$blocked=false;try{$manager->run($coreJob['id']);}catch(Throwable$e){$blocked=true;}
updateCheck($blocked&&$database->query('SELECT value FROM deployment_data')->fetchColumn()==='after-live-write','rollback refuses to erase post-activation writes');updateCheck(!is_file(HttpSecurity::privateDirectory('updates').'/maintenance.json'),'refused rollback does not leave maintenance enabled');
// Restore the exact post-migration state to exercise the normal rollback path.
$database->exec("UPDATE deployment_data SET value='migrated'");$manager->queue($coreJob['id'],'rollback');$manager->run($coreJob['id']);
$badJob=$manager->register(updatePackage($new,'full','3.2.10'),1);$manager->queue($badJob['id'],'stage');$manager->run($badJob['id']);
$badWorker=new UpdateManager($source,$database,function()use($database){$database->exec("UPDATE deployment_data SET value='partial-failure'");throw new RuntimeException('fixture migration failed');});$badWorker->queue($badJob['id'],'activate');$failed=false;try{$badWorker->run($badJob['id']);}catch(Throwable$e){$failed=true;}
updateCheck($failed&&realpath($link)===$source&&$database->query('SELECT value FROM deployment_data')->fetchColumn()==='original','failed migration restores data and preserves active code');
updateCheck($manager->status($badJob['id'])['state']==='staged','failed migration remains recoverable');updateCheck(!is_file(HttpSecurity::privateDirectory('updates').'/maintenance.json'),'successful recovery clears maintenance');
$protected=$manager->register(updatePackage($new,'full','3.2.10'),1);$manager->queue($protected['id'],'stage');$manager->run($protected['id']);
$changingWorker=new UpdateManager($source,$database,function()use($privateConfig){file_put_contents($privateConfig,'fixture overwrite');});$changingWorker->queue($protected['id'],'activate');$failed=false;try{$changingWorker->run($protected['id']);}catch(Throwable$e){$failed=true;}
updateCheck($failed&&file_get_contents($privateConfig)===$privateBytes&&realpath($link)===$source,'migration cannot replace private configuration');
$recovery=$manager->register(updatePackage($new,'full','3.2.10'),1);$manager->queue($recovery['id'],'stage');$manager->run($recovery['id']);$backupPath=null;$backupBytes=null;
$brokenWorker=new UpdateManager($source,$database,function()use($database,$recovery,&$backupPath,&$backupBytes){$job=json_decode(file_get_contents(HttpSecurity::privateDirectory('updates').'/jobs/'.$recovery['id'].'.json'),true);$backupPath=$job['backup']['file'];$backupBytes=file_get_contents($backupPath);file_put_contents($backupPath,'damaged fixture backup');$database->exec("UPDATE deployment_data SET value='partial' ");throw new RuntimeException('fixture failure');});
$brokenWorker->queue($recovery['id'],'activate');$failed=false;try{$brokenWorker->run($recovery['id']);}catch(Throwable$e){$failed=true;}
updateCheck($failed&&$manager->status($recovery['id'])['state']==='recovery_required','restore failure requires explicit recovery');
updateCheck(is_file(HttpSecurity::privateDirectory('updates').'/maintenance.json')&&realpath($link)===$source,'restore failure retains maintenance and previous release');
$blocked=false;try{HttpSecurity::cliBoundary();}catch(Throwable$e){$blocked=true;}updateCheck($blocked,'maintenance blocks other CLI writers');
file_put_contents($backupPath,$backupBytes);$manager->queue($recovery['id'],'rollback');$manager->run($recovery['id']);
updateCheck($database->query('SELECT value FROM deployment_data')->fetchColumn()==='original'&&$manager->status($recovery['id'])['state']==='rolled_back','explicit recovery restores verified repaired backup');
updateCheck(!is_file(HttpSecurity::privateDirectory('updates').'/maintenance.json'),'explicit recovery clears maintenance');
$manager->queue($badJob['id'],'abort');updateCheck($manager->run($badJob['id'])['state']==='aborted','staged update can be aborted');updateCheck(realpath($link)===$source,'abort does not change active release');
$tamper=$manager->register(updatePackage($new,'full','3.2.11'),1);$manager->queue($tamper['id'],'stage');$manager->run($tamper['id']);file_put_contents(HttpSecurity::privateDirectory('releases').'/'.$tamper['id'].'/framework.php','tampered staging');$manager->queue($tamper['id'],'activate');$blocked=false;try{$manager->run($tamper['id']);}catch(Throwable$e){$blocked=true;}updateCheck($blocked&&realpath($link)===$source,'modified staged release rejected before activation');
updateCheck(strpos(json_encode($manager->jobs()),$testRoot)===false,'web status excludes private paths and backup contents');
echo json_encode(['checks'=>$checks,'failed'=>0,'engine'=>'SQLite 3','signed_packages'=>true,'real_cli_migration'=>true])."\n";
