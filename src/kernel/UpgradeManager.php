<?php
namespace SMF\Core;
use RuntimeException;
/** Authenticated requests are applied exclusively by the CLI worker under maintenance. */
final class UpgradeManager {
    private static function path(): string {return \HttpSecurity::privateDirectory('upgrades').'/current.json';}
    private static function save(array $state): void {
        $file=self::path();if(is_link($file))throw new RuntimeException('Upgrade journal symlink rejected');
        $temp=tempnam(dirname($file),'.upgrade-');chmod($temp,0600);if(file_put_contents($temp,json_encode($state,JSON_THROW_ON_ERROR),LOCK_EX)===false || !rename($temp,$file))throw new RuntimeException('Upgrade journal unavailable');
    }
    public static function state(): ?array {$file=self::path();if(is_link($file))throw new RuntimeException('Upgrade journal symlink rejected');return is_file($file)?json_decode(file_get_contents($file),true,64,JSON_THROW_ON_ERROR):null;}
    public static function publicState(): array {
        $state=self::state();return $state?array_intersect_key($state,array_flip(['id','state','command','modules','requested_at','finished_at','error_code','version'])):['state'=>'idle'];
    }
    private static function lock(): mixed {
        $path=\HttpSecurity::privateDirectory('locks').'/upgrade.lock';if(is_link($path))throw new RuntimeException('Upgrade lock symlink rejected');
        $lock=fopen($path,'c');if(!$lock || !flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Ein Upgradeauftrag wird bereits bearbeitet.');return $lock;
    }
    public static function plan(array $requested): array {
        $modules=Kernel::boot()->modules();$ordered=[];$visit=function(string $id,array $chain=[])use(&$visit,&$ordered,$modules){
            if(in_array($id,$chain,true))throw new RuntimeException('Circular upgrade dependency');if(isset($ordered[$id]))return;
            $manifest=$modules->manifest($id);
            if(!VersionConstraint::matches(\VersionFile::version(file_get_contents(dirname(__DIR__).'/sys.conf.php')),$manifest['core']) || !VersionConstraint::matches(PHP_VERSION,$manifest['php']))throw new RuntimeException('Incompatible module version');
            foreach($manifest['requires'] as $dependency=>$constraint){if(!VersionConstraint::matches($modules->manifest($dependency)['version'],$constraint))throw new RuntimeException('Incompatible dependency');$visit($dependency,[...$chain,$id]);}
            $ordered[$id]=$manifest['_sha256'];
        };
        foreach($requested as $id){if(!is_string($id))throw new RuntimeException('Invalid module selection');$visit($id);}
        return $ordered;
    }
    public static function request(array $requested): array {
        if(PHP_SAPI!=='cli')\Identity::requireIdentity('backend',true);
        $lock=self::lock();try{
            $old=self::state();if($old && !in_array($old['state'],['done','restored'],true))throw new RuntimeException('Vorhandenen Upgradeauftrag zuerst prüfen.');
            $plan=self::plan($requested);if(!$plan)throw new RuntimeException('Mindestens ein Modul auswählen.');
            if($old){$archive=dirname(self::path()).'/'.$old['id'].'.json';if(!is_file($archive)){file_put_contents($archive,json_encode($old,JSON_THROW_ON_ERROR),LOCK_EX);chmod($archive,0600);}}
            $state=['id'=>bin2hex(random_bytes(16)),'state'=>'queued','command'=>'apply','modules'=>array_keys($plan),'manifests'=>$plan,'requested_at'=>time(),'version'=>\VersionFile::version(file_get_contents(dirname(__DIR__).'/sys.conf.php'))];self::save($state);return self::publicState();
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    public static function recover(string $id,string $command): void {
        if(PHP_SAPI!=='cli')\Identity::requireIdentity('backend',true);
        if(!in_array($command,['resume','restore'],true))throw new RuntimeException('Invalid recovery action');
        $lock=self::lock();try{
            $state=self::state();if(!$state || !hash_equals($state['id'],$id) || !in_array($state['state'],['needs_review','running'],true))throw new RuntimeException('Upgradeauftrag nicht zur Wiederherstellung freigegeben.');
            if($command==='restore' && empty($state['backup']))throw new RuntimeException('Keine abgeschlossene Datenbanksicherung vorhanden.');
            $state['command']=$command==='restore'?'restore':'apply';$state['resume']=true;$state['state']='queued';self::save($state);
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    private static function backupFiles(string $directory): void {
        $config=\HttpSecurity::privateDirectory('config');$target=$directory.'/config';if(!is_dir($target) && !mkdir($target,0700))throw new RuntimeException('Private config backup unavailable');
        foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($config,\FilesystemIterator::SKIP_DOTS)) as $file){if($file->isLink())throw new RuntimeException('Private config symlink rejected');if(!$file->isFile())continue;$relative=substr($file->getPathname(),strlen($config)+1);$out=$target.'/'.$relative;if(!is_dir(dirname($out)))mkdir(dirname($out),0700,true);if(is_file($out) && !hash_equals(hash_file('sha256',$file->getPathname()),hash_file('sha256',$out)))throw new RuntimeException('Private config changed during recovery');if(!is_file($out) && !copy($file->getPathname(),$out))throw new RuntimeException('Private config backup failed');chmod($out,0600);}
        $root=dirname(__DIR__,2);$files=[$root.'/config/config.inc.php',$root.'/src/.encryption_key'];if(getenv('SMF_CONFIG_FILE'))$files[]=getenv('SMF_CONFIG_FILE');
        foreach($files as $i=>$file)if(is_file($file)){if(is_link($file))throw new RuntimeException('Operator config symlink rejected');$out=$directory.'/operator-'.$i.'.backup';if(!copy($file,$out))throw new RuntimeException('Operator config backup failed');chmod($out,0600);}
    }
    public static function runPending(): bool {
        if(PHP_SAPI!=='cli')throw new RuntimeException('Upgrade worker is CLI only');
        $state=self::state();if(!$state || $state['state']!=='queued')return false;
        $lock=self::lock();try{
            $state=self::state();if($state['state']!=='queued')return false;$state['state']='running';$state['started_at']=time();self::save($state);
            try{
                require_once dirname(__DIR__).'/core/DatabaseBackup.php';require_once dirname(__DIR__).'/core/SecuritySchema.php';
                \HttpSecurity::schemaMaintenance(function()use(&$state){
                    $db=Kernel::boot()->database();
                    if($state['command']==='restore'){
                        \DatabaseBackup::restore($state['backup']);$state['state']='restored';$state['finished_at']=time();self::save($state);Kernel::reset();return;
                    }
                    if(\VersionFile::version(file_get_contents(dirname(__DIR__).'/sys.conf.php'))!==$state['version'] || self::plan($state['modules'])!==$state['manifests'])throw new RuntimeException('Module files changed after upgrade review');
                    if(empty($state['backup'])){
                        $directory=\HttpSecurity::privateDirectory('upgrades').'/'.$state['id'];if(!is_dir($directory) && !mkdir($directory,0700))throw new RuntimeException('Backup directory unavailable');
                        self::backupFiles($directory);$state['backup']=\DatabaseBackup::capture($db,$directory);self::save($state);
                    }
                    \SchemaManager::migrate($db);\SecuritySchema::migrate($db);CoreMigrations::migrate($db);
                    $modules=Kernel::boot()->modules();foreach($state['modules'] as $id){$modules->install($id);$modules->enable($id);$state['last_module']=$id;self::save($state);}
                    $state['state']='done';$state['finished_at']=time();unset($state['error_code']);self::save($state);
                    Kernel::boot()->audit()->record('upgrade.completed',$state['id'],'ok');
                },!empty($state['resume']));
            }catch(\Throwable $error){$state['state']='needs_review';$state['error_code']='UPGRADE_REQUIRES_REVIEW';self::save($state);error_log('Module upgrade failed: '.get_class($error));}
            return true;
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
}
