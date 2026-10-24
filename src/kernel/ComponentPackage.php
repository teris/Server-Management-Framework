<?php
namespace SMF\Core;
use RuntimeException;
use ZipArchive;
/** Verified release components. No executable package code is loaded during inspection. */
final class ComponentPackage {
    public static function inspect(string $archive,string $keyFile,string $fingerprint):array {
        require_once dirname(__DIR__).'/core/UpdateManager.php';
        if(!preg_match('/^[a-f0-9]{64}$/D',$fingerprint) || is_link($keyFile) || !is_file($keyFile))throw new RuntimeException('Trusted release key and published fingerprint required');
        $key=openssl_pkey_get_public(file_get_contents($keyFile));$details=$key?openssl_pkey_get_details($key):false;
        $der=$details?base64_decode(preg_replace('/-----[^-]+-----|\s/','',$details['key']),true):false;
        if(!$details || $details['type']!==OPENSSL_KEYTYPE_RSA || $details['bits']<3072 || $der===false || !hash_equals($fingerprint,hash('sha256',$der)))throw new RuntimeException('Release key fingerprint rejected');
        if(is_link($archive) || !is_file($archive) || filesize($archive)>67108864)throw new RuntimeException('Invalid component archive');
        $zip=new ZipArchive();if($zip->open($archive)!==true)throw new RuntimeException('Invalid ZIP');
        try{
            if($zip->numFiles>1500)throw new RuntimeException('Too many component files');
            foreach(['manifest.json'=>1048576,'manifest.sig'=>16384]as$name=>$maximum){$stat=$zip->statName($name);if(!$stat || $stat['size']>$maximum)throw new RuntimeException('Missing component signature');}
            $raw=$zip->getFromName('manifest.json');$sig=base64_decode($zip->getFromName('manifest.sig'),true);
            if($sig===false || openssl_verify($raw,$sig,$key,OPENSSL_ALGO_SHA256)!==1)throw new RuntimeException('Component signature rejected');
            $m=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
            if(($m['format']??null)!==2 || ($m['type']??'')!=='component' || !preg_match('/^(?:framework\.core|commerce\.shop|(?:provider|product)\.[a-z][a-z0-9.-]*)$/D',$m['id']??'') || !preg_match('/^\d+\.\d+\.\d+$/D',$m['version']??'') || !preg_match('/^\d+\.\d+\.\d+$/D',$m['release_version']??'') || !preg_match('/^[a-f0-9]{40}$/D',$m['commit']??'') || !is_array($m['files']??null) || !$m['files'] || !is_array($m['modules']??null) || !preg_match('/^\d+\.\d+(?:\.\d+)?$/D',$m['php_min']??'') || version_compare(PHP_VERSION,$m['php_min'],'<'))throw new RuntimeException('Incompatible component manifest');
            $seen=[];$bytes=0;
            for($i=0;$i<$zip->numFiles;$i++){
                $stat=$zip->statIndex($i);$name=$stat['name'];$lower=strtolower($name);$zip->getExternalAttributesIndex($i,$os,$attributes);$kind=($attributes>>16)&0170000;
                if(isset($seen[$lower]) || ($kind!==0 && $kind!==0100000) || $stat['size']>8388608 || ($bytes+=$stat['size'])>67108864)throw new RuntimeException('Unsafe component entry');$seen[$lower]=true;
                if(in_array($name,['manifest.json','manifest.sig'],true))continue;
                if(!\UpdateManager::safePath($name) || !preg_match('/^[a-f0-9]{64}$/D',$m['files'][$name]??'') || !self::owns($m['id'],$name))throw new RuntimeException('Unexpected component path');
                $data=$zip->getFromIndex($i);if($data===false || strlen($data)!==$stat['size'] || !hash_equals($m['files'][$name],hash('sha256',$data)))throw new RuntimeException('Component checksum rejected');
            }
            if(count($seen)!==count($m['files'])+2)throw new RuntimeException('Incomplete component inventory');
            foreach($m['files']as$name=>$hash)if(!isset($seen[strtolower($name)]))throw new RuntimeException('Missing component file');
            $found=[];foreach($m['files']as$name=>$hash)if(preg_match('~^extensions/([^/]+)/module\.json$~D',$name,$match)){
                $module=json_decode($zip->getFromName($name),true,64,JSON_THROW_ON_ERROR);$id=$match[1];
                if(($module['id']??'')!==$id || ($m['modules'][$id]??null)!=array_intersect_key($module,array_flip(['version','requires','core','php'])))throw new RuntimeException('Component module metadata differs');$found[]=$id;
            }
            if(count($found)!==count($m['modules']))throw new RuntimeException('Module inventory differs');
            $m['_archive']=realpath($archive);$m['_sha256']=hash_file('sha256',$archive);return $m;
        }finally{$zip->close();}
    }
    private static function owns(string $id,string $name):bool {
        if($id==='framework.core')return !str_starts_with($name,'extensions/') && !str_starts_with($name,'public/assets/shop/');
        if($id==='commerce.shop')return (bool)preg_match('~^extensions/shop\.[a-z0-9.-]+/~D',$name) || str_starts_with($name,'public/assets/shop/');
        return str_starts_with($name,'extensions/'.$id.'/');
    }
    /** Resolve all signed dependency/version contracts before creating a destination. */
    public static function plan(array $archives,string $key,string $fingerprint,?array $installed=null):array {
        $components=[];$modules=$installed['modules']??[];$files=[];
        foreach($archives as$archive){$m=self::inspect($archive,$key,$fingerprint);if(isset($components[$m['id']]))throw new RuntimeException('Duplicate component');$components[$m['id']]=$m;
            foreach($m['files']as$name=>$hash){if(isset($files[strtolower($name)]))throw new RuntimeException('Overlapping components');$files[strtolower($name)]=true;}
            foreach($m['modules']as$id=>$module)$modules[$id]=$module;
        }
        $trusted=realpath($key);foreach($components as$m)if(str_starts_with($trusted,dirname(__DIR__,2).'/'))throw new RuntimeException('Trusted key must be outside the application');
        $core=$components['framework.core']['version']??$installed['version']??null;if(!$core)throw new RuntimeException('Framework core component required');
        foreach($components as$m){if(!VersionConstraint::matches($core,$m['core']))throw new RuntimeException('Component core version mismatch');
            if(isset($components['framework.core']) && ($components['framework.core']['commit']!==$m['commit'] || $components['framework.core']['release_version']!==$m['release_version']))throw new RuntimeException('Fresh assembly requires one committed release');
        }
        $ordered=[];$visit=function($id,$chain=[])use(&$visit,&$ordered,$modules,$core){if(isset($ordered[$id]))return;if(in_array($id,$chain,true))throw new RuntimeException('Circular component dependency');$m=$modules[$id]??throw new RuntimeException('Missing dependency: '.$id);
            if(!VersionConstraint::matches($core,$m['core']) || !VersionConstraint::matches(PHP_VERSION,$m['php']))throw new RuntimeException('Module version incompatible');
            foreach($m['requires']as$dependency=>$constraint){if(!isset($modules[$dependency]) || !VersionConstraint::matches($modules[$dependency]['version'],$constraint))throw new RuntimeException('Missing compatible dependency: '.$dependency);$visit($dependency,[...$chain,$id]);}$ordered[$id]=true;
        };
        foreach($components as$m)foreach($m['modules']as$id=>$module)$visit($id);
        return ['components'=>$components,'order'=>array_keys($ordered),'version'=>$core];
    }
    private static function extract(array $component,string $target):void {
        if(!hash_equals($component['_sha256'],hash_file('sha256',$component['_archive'])))throw new RuntimeException('Verified archive changed');
        $zip=new ZipArchive();if($zip->open($component['_archive'])!==true)throw new RuntimeException('Verified archive unavailable');
        try{foreach($component['files']as$name=>$hash){$file=$target.'/'.$name;if(!is_dir(dirname($file)) && !mkdir(dirname($file),0755,true))throw new RuntimeException('Component directory unavailable');$data=$zip->getFromName($name);if(!hash_equals($hash,hash('sha256',$data)) || file_put_contents($file,$data)===false)throw new RuntimeException('Component extraction failed');chmod($file,0644);}}finally{$zip->close();}
    }
    private static function provenance(string $target,array $plan):void {
        $files=[];foreach($plan['components']as$m)$files=[...$files,...array_keys($m['files'])];sort($files);
        file_put_contents($target.'/deploy/package-files.json',json_encode($files,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
        $proof=['format'=>1,'version'=>$plan['version'],'components'=>array_map(fn($m)=>['id'=>$m['id'],'version'=>$m['version'],'commit'=>$m['commit'],'archive_sha256'=>$m['_sha256']],array_values($plan['components']))];
        file_put_contents($target.'/deploy/assembled-components.json',json_encode($proof,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
    }
    public static function assemble(array $plan,string $target):array {
        if(file_exists($target) || is_link($target) || !is_dir(dirname($target)) || is_link(dirname($target)))throw new RuntimeException('Fresh non-existing target directory required');
        if(getenv('SMF_CONFIG_FILE'))throw new RuntimeException('Do not assemble with an existing operator configuration');
        $parent=realpath(dirname($target));$target=$parent.'/'.basename($target);$stage=$parent.'/.smf-assembly-'.bin2hex(random_bytes(16));if(!mkdir($stage,0700))throw new RuntimeException('Assembly staging unavailable');
        try{foreach($plan['components']as$m)self::extract($m,$stage);self::provenance($stage,$plan);chmod($stage,0755);if(!rename($stage,$target))throw new RuntimeException('Assembly activation failed');return ['assembled'=>true,'version'=>$plan['version'],'modules'=>$plan['order'],'target'=>$target];}
        catch(\Throwable$error){self::remove($stage);throw$error;}
    }
    private static function remove(string $path):void {
        if(is_link($path))throw new RuntimeException('Component symlink rejected');if(!is_dir($path))return;
        foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST)as$file){if($file->isLink())throw new RuntimeException('Component symlink rejected');$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());}rmdir($path);
    }
    private static function copyTree(string $source,string $target,bool $private=true):void {
        if(is_link($source))throw new RuntimeException('Module symlink rejected');if(!is_dir($target) && !mkdir($target,$private?0700:0755,true))throw new RuntimeException('Module backup unavailable');
        foreach(new \DirectoryIterator($source)as$file){if($file->isDot())continue;if($file->isLink())throw new RuntimeException('Module symlink rejected');$destination=$target.'/'.$file->getFilename();if($file->isDir())self::copyTree($file->getPathname(),$destination,$private);else{if(!copy($file->getPathname(),$destination))throw new RuntimeException('Module backup failed');chmod($destination,$private?0600:0644);}}
    }
    public static function recoverModules(string $id,string $root):array {
        require_once dirname(__DIR__).'/core/DatabaseBackup.php';
        if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new RuntimeException('Invalid component recovery ID');
        $backup=\HttpSecurity::privateDirectory('components').'/'.$id;$file=$backup.'/state.json';if(is_link($file) || !is_file($file))throw new RuntimeException('Component journal unavailable');$journal=json_decode(file_get_contents($file),true,64,JSON_THROW_ON_ERROR);
        if(($journal['root']??'')!==realpath($root) || !in_array($journal['state'],['applying','needs_recovery'],true))throw new RuntimeException('Only interrupted component updates may be recovered');
        $maintenance=\HttpSecurity::privateDirectory('updates').'/maintenance.json';$state=is_file($maintenance)?json_decode(file_get_contents($maintenance),true):null;
        if(!$state || ($state['id']??null)!==($journal['maintenance_id']??null))throw new RuntimeException('Component maintenance owner changed');
        \HttpSecurity::schemaMaintenance(function()use(&$journal,$backup,$root,$id,$file){
            foreach($journal['modules']as$name){if(!preg_match('/^(?:provider|product)\.[a-z0-9.-]+$/D',$name))throw new RuntimeException('Invalid recovery module');$target=$root.'/extensions/'.$name;$restore=$root.'/extensions/.smf-restore-'.$id;
                if(is_dir($restore))self::remove($restore);if(in_array($name,$journal['previous_modules'],true))self::copyTree($backup.'/previous-modules/'.$name,$restore,false);
                self::remove($target);if(is_dir($restore) && !rename($restore,$target))throw new RuntimeException('Module source restore failed');
            }
            \DatabaseBackup::restore($journal['database']);Kernel::reset();$journal['state']='rolled_back';file_put_contents($file,json_encode($journal,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX);
        },true);return ['recovered'=>true,'backup_id'=>$id];
    }
    public static function installModules(array $plan,string $root):array {
        if(PHP_SAPI!=='cli')throw new RuntimeException('Component installation is CLI only');
        foreach($plan['components']as$m)if(!preg_match('/^(?:provider|product)\./D',$m['id']))throw new RuntimeException('Use the signed framework updater for core/shop replacement');
        $root=realpath($root)?:throw new RuntimeException('Application root unavailable');$id=bin2hex(random_bytes(16));$stage=$root.'/extensions/.smf-stage-'.$id;$old=$root.'/extensions/.smf-previous-'.$id;
        $backup=\HttpSecurity::privateDirectory('components').'/'.$id;if(!mkdir($backup,0700) || !mkdir($stage,0700) || !mkdir($old,0700))throw new RuntimeException('Private component workspace unavailable');
        foreach($plan['components']as$m)self::extract($m,$stage);
        $journal=['id'=>$id,'root'=>$root,'state'=>'prepared','modules'=>array_keys($plan['components']),'previous_modules'=>[]];$journalFile=$backup.'/state.json';$save=function()use(&$journal,$journalFile){file_put_contents($journalFile,json_encode($journal,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX);chmod($journalFile,0600);};$save();$failed=false;
        \HttpSecurity::schemaMaintenance(function()use(&$journal,$save,$backup,$plan,$root,$stage,$old,&$failed){
            if(!hash_equals($plan['_module_state_hash'],hash('sha256',json_encode(Kernel::boot()->database()->query('SELECT * FROM smf_module_state ORDER BY module_id')->fetchAll(\PDO::FETCH_ASSOC),JSON_THROW_ON_ERROR))))throw new RuntimeException('Installed module state changed after verification');
            $journal['database']=\DatabaseBackup::capture(Kernel::boot()->database(),$backup);
            foreach($journal['modules']as$name)if(is_dir($root.'/extensions/'.$name)){self::copyTree($root.'/extensions/'.$name,$backup.'/previous-modules/'.$name);$journal['previous_modules'][]=$name;}
            self::copyTree(\HttpSecurity::privateDirectory('config'),$backup.'/private-config');
            $journal['maintenance_id']=json_decode(file_get_contents(\HttpSecurity::privateDirectory('updates').'/maintenance.json'),true)['id'];$journal['state']='applying';$save();
            try{
                foreach($plan['components']as$m){$name=$m['id'];$target=$root.'/extensions/'.$name;if(is_link($target))throw new RuntimeException('Installed module symlink rejected');
                    if(is_dir($target) && !rename($target,$old.'/'.$name))throw new RuntimeException('Module backup failed');
                    if(!rename($stage.'/extensions/'.$name,$target))throw new RuntimeException('Module activation failed');
                }
                Kernel::reset();$modules=Kernel::boot()->modules();foreach($plan['order']as$name){$modules->install($name);$modules->enable($name);}
                $journal['state']='done';$save();
            }catch(\Throwable$error){
                $journal['state']='needs_recovery';$save();
                foreach($journal['modules']as$name){self::remove($root.'/extensions/'.$name);if(in_array($name,$journal['previous_modules'],true))self::copyTree($backup.'/previous-modules/'.$name,$root.'/extensions/'.$name,false);}
                \DatabaseBackup::restore($journal['database']);Kernel::reset();$journal['state']='rolled_back';$save();$failed=true;
            }
        });
        self::remove($stage);self::remove($old);if($failed)throw new RuntimeException('Module update rolled back; private journal retained');
        return ['installed'=>true,'modules'=>$journal['modules'],'backup_id'=>$id];
    }
}
