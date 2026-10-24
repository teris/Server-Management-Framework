<?php
if (PHP_SAPI !== 'cli' && (get_included_files()[0] ?? null) === __FILE__) { http_response_code(404); exit; }
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/VersionFile.php';
require_once __DIR__ . '/DatabaseBackup.php';

final class UpdateManager {
    private string $root;
    private string $work;
    private string $releases;
    public function __construct(string $root, private ?PDO $db = null, private $migrator = null) {
        $this->root = realpath($root) ?: throw new RuntimeException('Application root unavailable');
        $this->work = HttpSecurity::privateDirectory('updates');
        $this->releases = HttpSecurity::privateDirectory('releases');
        foreach (['jobs','packages'] as $part) {
            $path = $this->work . '/' . $part;
            if (is_link($path)) throw new RuntimeException('Update storage symlink rejected');
            if (!is_dir($path) && !mkdir($path,0700)) throw new RuntimeException('Update storage unavailable');
        }
    }
    private function id(string $id): string { if (!preg_match('/^[a-f0-9]{32}$/D',$id)) throw new InvalidArgumentException('Invalid update ID'); return $id; }
    private function jobFile(string $id): string { return $this->work . '/jobs/' . $this->id($id) . '.json'; }
    private function load(string $id): array {
        $file = $this->jobFile($id); if (!is_file($file) || is_link($file)) throw new RuntimeException('Update job not found');
        return json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
    }
    private function save(array $job): array {
        $file = $this->jobFile($job['id']); $temporary = tempnam(dirname($file),'.job-');
        if (!$temporary || file_put_contents($temporary,json_encode($job,JSON_THROW_ON_ERROR)) === false) throw new RuntimeException('Update state unavailable');
        chmod($temporary,0600); if (!rename($temporary,$file)) throw new RuntimeException('Update state unavailable'); return $job;
    }
    private function locked(string $id, callable $action) {
        $file = $this->work . '/jobs/' . $this->id($id) . '.lock'; if (is_link($file)) throw new RuntimeException('Update lock symlink rejected');
        $lock = fopen($file,'c'); if (!$lock || !flock($lock,LOCK_EX|LOCK_NB)) throw new RuntimeException('Update job is busy');
        try { return $action(); } finally { flock($lock,LOCK_UN); fclose($lock); }
    }
    public static function safePath(string $path): bool {
        if (!preg_match('~^[a-zA-Z0-9_.-]+(?:/[a-zA-Z0-9_.-]+)*$~D',$path)) return false;
        foreach (explode('/',$path) as $part) if (in_array($part,['.','..'],true) || ($part[0] === '.' && $part !== '.htaccess')) return false;
        return !preg_match('~^(?:private|var|cache|logs|backups|tests|vendor|node_modules)/|(?:^|/)(?:config\.inc\.php|setup-access\.php|smf_ogp_bridge_config\.php|smf-ogp-bridge\.json|sys\.conf\.local\.php|\.encryption_key)$|\.(?:sqlite3?|db|log|bak|bnk|zip|tar|gz|pem|key|env)(?:[.-]|$)~i',$path);
    }
    private function inspect(string $package): array {
        if (!class_exists('ZipArchive') || !is_file($package) || is_link($package) || filesize($package)>67108864) throw new RuntimeException('Invalid update archive or missing ZIP extension');
        $keyFile = getenv('SMF_UPDATE_PUBLIC_KEY');
        if (!$keyFile || !is_file($keyFile) || is_link($keyFile) || str_starts_with(realpath($keyFile), $this->root . '/') || str_starts_with(realpath($keyFile), $this->releases . '/')) throw new RuntimeException('Trusted update public key must be configured');
        $key = openssl_pkey_get_public(file_get_contents($keyFile)); $details = $key ? openssl_pkey_get_details($key) : false;
        if (!$details || $details['type'] !== OPENSSL_KEYTYPE_RSA || $details['bits']<3072) throw new RuntimeException('Update key requires RSA with at least 3072 bits');
        $zip = new ZipArchive(); if ($zip->open($package)!==true) throw new RuntimeException('Invalid ZIP archive');
        try {
            $manifestStat = $zip->statName('manifest.json'); $signatureStat = $zip->statName('manifest.sig');
            if (!$manifestStat || !$signatureStat || $manifestStat['size']>1048576 || $signatureStat['size']>16384) throw new RuntimeException('Update signature missing');
            $raw = $zip->getFromName('manifest.json'); $signature = base64_decode($zip->getFromName('manifest.sig'),true);
            if ($signature===false || openssl_verify($raw,$signature,$key,OPENSSL_ALGO_SHA256)!==1) throw new RuntimeException('Update signature rejected');
            $manifest = json_decode($raw,true,512,JSON_THROW_ON_ERROR);
            if (($manifest['format']??null)!==1 || !in_array($manifest['type']??'', ['core','full'],true) || !preg_match('/^\d+\.\d+\.\d+(?:-[a-zA-Z0-9.-]+)?$/D',$manifest['version']??'')
                || !preg_match('/^[a-f0-9]{40}$/D',$manifest['commit']??'') || !preg_match('/^[a-zA-Z0-9_.-]{1,80}$/D',$manifest['schema_version']??'')
                || !is_array($manifest['files']??null) || !$manifest['files'] || count($manifest['files'])>1000 || !preg_match('/^\d+\.\d+(?:\.\d+)?$/D',$manifest['php_min']??'') || version_compare(PHP_VERSION,$manifest['php_min'],'<')) throw new RuntimeException('Update manifest incompatible');
            $seen=[]; $bytes=0;
            for ($i=0;$i<$zip->numFiles;$i++) {
                $stat=$zip->statIndex($i); $path=$stat['name']; $keyName=strtolower($path);
                $zip->getExternalAttributesIndex($i,$opsys,$attributes);
                if (isset($seen[$keyName]) || (($attributes>>16)&0170000)===0120000 || $stat['size']>8388608 || ($bytes+=$stat['size'])>67108864) throw new RuntimeException('Unsafe or oversized ZIP entry');
                $seen[$keyName]=true;
                if (in_array($path,['manifest.json','manifest.sig'],true)) continue;
                if (!self::safePath($path) || !preg_match('/^[a-f0-9]{64}$/D',$manifest['files'][$path]??'')) throw new RuntimeException('Unexpected package file');
                if ($manifest['type']==='core' && (str_starts_with($path,'src/module/') || str_starts_with($path,'public/'))) throw new RuntimeException('Core update contains module/customer files');
                $stream=$zip->getStream($path); if (!$stream) throw new RuntimeException('Package file unreadable'); $hash=hash_init('sha256'); $read=hash_update_stream($hash,$stream,8388609); $extra=fread($stream,1); fclose($stream); if($read!==$stat['size']||$extra!=='')throw new RuntimeException('ZIP entry size mismatch');
                if (!hash_equals($manifest['files'][$path],hash_final($hash))) throw new RuntimeException('Package checksum rejected');
            }
            if (count($seen)!==count($manifest['files'])+2) throw new RuntimeException('Package file inventory mismatch');
            foreach ($manifest['files'] as $path=>$hash) if (!isset($seen[strtolower($path)])) throw new RuntimeException('Manifest file missing');
            if ($manifest['type']==='full') foreach (['framework.php','src/index.php','src/sys.conf.php','config/load.php','bin/migrate.php','database/schema.json','deploy/package-files.json'] as $required) if (!isset($manifest['files'][$required])) throw new RuntimeException('Full update incomplete');
            return $manifest;
        } finally { $zip->close(); }
    }
    public function register(string $package, int $actor): array {
        $manifest=$this->inspect($package); $current=VersionFile::version(file_get_contents($this->root.'/src/sys.conf.php'));
        if (!version_compare($manifest['version'],$current,'>')) throw new RuntimeException('Update must target a newer framework version');
        $id=bin2hex(random_bytes(16)); $destination=$this->work.'/packages/'.$id.'.zip';
        if (!copy($package,$destination)) throw new RuntimeException('Package storage failed'); chmod($destination,0600);
        $job=['id'=>$id,'actor'=>$actor,'created_at'=>time(),'state'=>'verified','requested'=>null,'manifest'=>$manifest,'package_sha256'=>hash_file('sha256',$destination),'source_root'=>$this->root,'source_version'=>$current,'error'=>null];
        return self::summary($this->save($job));
    }
    public static function summary(array $job): array { return ['id'=>$job['id'],'actor'=>$job['actor'],'created_at'=>$job['created_at'],'state'=>$job['state'],'requested'=>$job['requested'],'version'=>$job['manifest']['version'],'type'=>$job['manifest']['type'],'commit'=>$job['manifest']['commit'],'error'=>$job['error']]; }
    public function status(string $id): array { return self::summary($this->load($id)); }
    public function jobs(): array { $jobs=[]; foreach (glob($this->work.'/jobs/*.json') as $file) { $id=basename($file,'.json'); if (preg_match('/^[a-f0-9]{32}$/D',$id) && !is_link($file)) $jobs[]=self::summary($this->load($id)); } return array_reverse($jobs); }
    public function queue(string $id, string $action): array {
        return $this->locked($id,function()use($id,$action){
            $job=$this->load($id); $allowed=['stage'=>['verified','staged'],'activate'=>['staged','applying','activated'],'abort'=>['verified','staged','aborted'],'rollback'=>['activated','rolled_back','recovery_required']];
            if (!isset($allowed[$action]) || !in_array($job['state'],$allowed[$action],true)) throw new RuntimeException('Update state does not permit this action');
            $job['requested']=$action; return self::summary($this->save($job));
        });
    }
    private function copySource(string $source, string $destination, string $path): void {
        if (!self::safePath($path)) throw new RuntimeException('Unsafe source inventory');
        $file=$source; foreach(explode('/',$path)as$part){$file.='/'.$part;if(is_link($file))throw new RuntimeException('Release symlink rejected');}
        if (!is_file($file)) throw new RuntimeException('Source file missing');
        $target=$destination.'/'.$path; if(!is_dir(dirname($target)))mkdir(dirname($target),0755,true);
        if(!copy($file,$target))throw new RuntimeException('Release copy failed'); chmod($target,0644);
    }
    private function removeRelease(string $path): void {
        if(dirname($path)!==$this->releases || is_link($path))throw new RuntimeException('Invalid release removal');
        if(!is_dir($path))return;
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($it as$file){if($file->isLink())throw new RuntimeException('Release symlink rejected');$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());}rmdir($path);
    }
    private function stage(array $job): array {
        if($job['state']==='staged'){$job['requested']=null;return $this->save($job);}
        $package=$this->work.'/packages/'.$job['id'].'.zip';
        if(!hash_equals($job['package_sha256'],hash_file('sha256',$package)))throw new RuntimeException('Stored package changed');
        $manifest=$this->inspect($package); if($manifest!==$job['manifest'])throw new RuntimeException('Stored manifest changed');
        $target=$this->releases.'/'.$job['id']; $this->removeRelease($target); mkdir($target,0755);
        if($manifest['type']==='core'){
            $inventory=json_decode(file_get_contents($job['source_root'].'/deploy/package-files.json'),true,512,JSON_THROW_ON_ERROR);
            foreach($inventory as$path)$this->copySource($job['source_root'],$target,$path);
        }
        $zip=new ZipArchive();$zip->open($package);
        try{foreach($manifest['files']as$path=>$hash){$file=$target.'/'.$path;if(!is_dir(dirname($file)))mkdir(dirname($file),0755,true);$stream=$zip->getStream($path);$output=fopen($file,'wb');if(!$output||!$stream)throw new RuntimeException('Release extraction failed');$length=stream_copy_to_stream($stream,$output,8388609);fclose($stream);fclose($output);chmod($file,0644);if($length>8388608||!hash_equals($hash,hash_file('sha256',$file)))throw new RuntimeException('Extracted checksum mismatch');}}finally{$zip->close();}
        if($manifest['type']==='core'){
            $inventory=array_values(array_unique([...$inventory,...array_keys($manifest['files'])]));sort($inventory);
            file_put_contents($target.'/deploy/package-files.json',json_encode($inventory,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
        }
        $legacyLocal=$job['source_root'].'/src/sys.conf.local.php';
        if(is_file($legacyLocal)){
            if(is_link($legacyLocal))throw new RuntimeException('Legacy settings symlink rejected');
            $privateLocal=HttpSecurity::privateDirectory('config').'/system.local.php';
            if(is_file($privateLocal)&&!hash_equals(hash_file('sha256',$privateLocal),hash_file('sha256',$legacyLocal)))throw new RuntimeException('Private operator settings conflict');
            if(!is_file($privateLocal)){if(!copy($legacyLocal,$privateLocal))throw new RuntimeException('Operator settings migration failed');chmod($privateLocal,0600);}
        }
        $previous=file_get_contents($job['source_root'].'/src/sys.conf.php');$incoming=file_get_contents($target.'/src/sys.conf.php');
        file_put_contents($target.'/src/sys.conf.php',VersionFile::merge($previous,$incoming,$manifest['version']));
        $schema=json_decode(file_get_contents($target.'/database/schema.json'),true,512,JSON_THROW_ON_ERROR);
        if(($schema['version']??'')!==$manifest['schema_version'])throw new RuntimeException('Package schema mismatch');
        $inventory=json_decode(file_get_contents($target.'/deploy/package-files.json'),true,512,JSON_THROW_ON_ERROR);
        foreach($inventory as$path)if(!self::safePath($path)||!is_file($target.'/'.$path)||is_link($target.'/'.$path))throw new RuntimeException('Staged release inventory incomplete');
        if($manifest['type']==='full' && array_diff(array_keys($manifest['files']),$inventory))throw new RuntimeException('Full release inventory mismatch');
        $job['staged_files']=[];foreach($inventory as$path)$job['staged_files'][$path]=hash_file('sha256',$target.'/'.$path);
        $job['source_config_sha256']=hash_file('sha256',$job['source_root'].'/src/sys.conf.php');
        $job['state']='staged';$job['requested']=null;return $this->save($job);
    }
    private function privateSnapshot(string $config): array {
        $paths=[$config, HttpSecurity::privateDirectory('keys').'/provider-encryption.key', HttpSecurity::privateDirectory('config').'/system.local.php', HttpSecurity::privateDirectory('config').'/ui-settings.json']; $files=[];
        foreach(array_unique($paths) as $path){
            if(is_link($path))throw new RuntimeException('Private configuration symlink rejected');
            $record=['path'=>$path,'exists'=>is_file($path),'sha256'=>is_file($path)?hash_file('sha256',$path):null];
            if($record['exists']){$record['backup']=$this->work.'/private-'.bin2hex(random_bytes(12)).'.backup';if(!copy($path,$record['backup']))throw new RuntimeException('Private configuration backup failed');chmod($record['backup'],0600);}
            $files[]=$record;
        }return $files;
    }
    private function privateUnchanged(array $files): bool {
        foreach($files as$file)if(is_link($file['path'])||is_file($file['path'])!==$file['exists']||($file['exists']&&!hash_equals($file['sha256'],hash_file('sha256',$file['path']))))return false;return true;
    }
    private function restorePrivate(array $files): void {
        foreach($files as$file){
            if(is_link($file['path']))throw new RuntimeException('Private restore symlink rejected');
            if(!$file['exists']){if(is_file($file['path']))unlink($file['path']);continue;}
            if(is_link($file['backup'])||!is_file($file['backup'])||!hash_equals($file['sha256'],hash_file('sha256',$file['backup'])))throw new RuntimeException('Private backup integrity failed');
            $temporary=tempnam(dirname($file['path']),'.restore-');if(!$temporary||!copy($file['backup'],$temporary))throw new RuntimeException('Private restore failed');chmod($temporary,0600);if(!rename($temporary,$file['path']))throw new RuntimeException('Private restore activation failed');
        }
    }
    private function switchLink(string $link, string $target): void {
        if(!is_link($link)||!is_dir($target))throw new RuntimeException('Managed current symlink required');
        $temporary=dirname($link).'/.smf-switch-'.bin2hex(random_bytes(8));
        if(!symlink($target,$temporary))throw new RuntimeException('Release link creation failed');
        if(!rename($temporary,$link)){unlink($temporary);throw new RuntimeException('Atomic release activation failed');}clearstatcache(true,$link);
    }
    private function deployment(array $job, bool $rollback): array {
        if((!$rollback&&$job['state']==='activated')||($rollback&&$job['state']==='rolled_back')){$job['requested']=null;return $this->save($job);}
        $link=getenv('SMF_CURRENT_LINK');$private=getenv('SMF_PRIVATE_DIR');$config=getenv('SMF_CONFIG_FILE');
        if(!$link||!is_link($link)||!$private||!$config||!is_file($config)||str_starts_with(realpath($config),$job['source_root'].'/'))throw new RuntimeException('Stable private configuration and managed current link are required');
        $target=$this->releases.'/'.$job['id'];$expected=$rollback?$target:$job['source_root'];
        if(!$rollback&&$job['state']==='applying'&&realpath($link)===realpath($target)){$job['state']='activated';$job['requested']=null;return $this->save($job);}
        if(realpath($link)!==realpath($expected)&&!($rollback&&realpath($link)===realpath($job['source_root'])))throw new RuntimeException('Active release changed since this job was created');
        if(!$rollback){
            if(time()-$job['created_at']>86400)throw new RuntimeException('Update job expired; register the signed package again');
            $verified=$this->inspect($this->work.'/packages/'.$job['id'].'.zip');if($verified!==$job['manifest'])throw new RuntimeException('Update trust changed');
            if(!hash_equals($job['source_config_sha256'],hash_file('sha256',$job['source_root'].'/src/sys.conf.php')))throw new RuntimeException('Operator settings changed; create a new update job');
            foreach($job['staged_files'] as$path=>$hash){$file=$target.'/'.$path;if(is_link($file)||!is_file($file)||!str_starts_with(realpath($file),$target.'/')||!hash_equals($hash,hash_file('sha256',$file)))throw new RuntimeException('Staged release changed');}
        }
        $lock=fopen(HttpSecurity::privateDirectory('locks').'/deployment.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Active requests still in progress; retry maintenance later');
        $maintenance=$this->work.'/maintenance.json';$clear=false;
        try{
            if(is_file($maintenance)&&((json_decode(file_get_contents($maintenance),true)['id']??null)!==$job['id']))throw new RuntimeException('Another update requires recovery');
            file_put_contents($maintenance,json_encode(['id'=>$job['id'],'since'=>time()]),LOCK_EX);chmod($maintenance,0600);
            if($rollback){
                if($job['state']!=='recovery_required'&&(!isset($job['post_database_fingerprint'])||!hash_equals($job['post_database_fingerprint'],DatabaseBackup::fingerprint($this->db?:HttpSecurity::connection()))||!$this->privateUnchanged($job['private_files'])||!hash_equals($job['staged_files']['src/sys.conf.php'],hash_file('sha256',$target.'/src/sys.conf.php')))){$clear=true;throw new RuntimeException('Database or settings changed after activation; automatic rollback would lose data');}
                DatabaseBackup::restore($job['backup']);$this->restorePrivate($job['private_files']);if(realpath($link)!==realpath($job['source_root']))$this->switchLink($link,$job['source_root']);$job['state']='rolled_back';$clear=true;}
            else{
                if($job['state']==='applying'&&isset($job['backup'])){DatabaseBackup::restore($job['backup']);$this->restorePrivate($job['private_files']);$job['state']='staged';}
                $job['backup']=DatabaseBackup::capture($this->db?:HttpSecurity::connection(),$this->work);$job['private_files']=$this->privateSnapshot($config);$job['state']='applying';$this->save($job);
                try{
                    if($this->migrator)($this->migrator)($target);
                    else{$arguments=json_decode(getenv('SMF_PHP_ARGS_JSON')?:'[]',true,512,JSON_THROW_ON_ERROR);if(!is_array($arguments)||array_filter($arguments,fn($value)=>!is_string($value)))throw new RuntimeException('Invalid CLI PHP arguments');DatabaseBackup::command([PHP_BINARY,...$arguments,$target.'/bin/migrate.php'],['SMF_MAINTENANCE_JOB'=>$job['id']]);}
                    if(!$this->privateUnchanged($job['private_files']))throw new RuntimeException('Migration changed protected configuration or keys');
                    $job['post_database_fingerprint']=DatabaseBackup::fingerprint($this->db?:HttpSecurity::connection());
                    $this->save($job);
                    $this->switchLink($link,$target);$job['state']='activated';$clear=true;
                }catch(Throwable $e){DatabaseBackup::restore($job['backup']);$this->restorePrivate($job['private_files']);$job['state']='staged';$clear=true;throw $e;}
            }
            $job['requested']=null;$job['error']=null;return $this->save($job);
        }catch(Throwable $e){if(!$rollback&&$job['state']==='staged')$clear=true;$job['requested']=null;$job['error']='UPDATE_FAILED';if(!$clear)$job['state']='recovery_required';$this->save($job);throw $e;}
        finally{if($clear&&is_file($maintenance))unlink($maintenance);flock($lock,LOCK_UN);fclose($lock);}
    }
    public function run(string $id): array {
        if(PHP_SAPI!=='cli')throw new RuntimeException('Update worker is CLI only');
        return $this->locked($id,function()use($id){
            $job=$this->load($id);$action=$job['requested'];
            try{
            if($action==='stage')$job=$this->stage($job);
            elseif($action==='activate')$job=$this->deployment($job,false);
            elseif($action==='rollback')$job=$this->deployment($job,true);
            elseif($action==='abort'){if($job['state']!=='aborted')$this->removeRelease($this->releases.'/'.$id);$job['state']='aborted';$job['requested']=null;$job=$this->save($job);}
            return self::summary($job);
            }catch(Throwable $e){$failed=$this->load($id);$failed['requested']=null;$failed['error']=$failed['error']?:'UPDATE_FAILED';if($action==='stage'&&$failed['state']==='verified')$this->removeRelease($this->releases.'/'.$id);$this->save($failed);throw $e;}
        });
    }
}
