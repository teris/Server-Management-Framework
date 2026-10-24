<?php
namespace SMF\Setup;
if (PHP_SAPI !== 'cli' && (get_included_files()[0] ?? '') === __FILE__) { http_response_code(404); exit; }
require_once __DIR__.'/ProviderDetector.php';

final class Installer {
    public function __construct(private string $root, private ?ProviderDetector $detector = null) { $this->root = realpath($root) ?: throw new \RuntimeException('Anwendungsordner fehlt.'); $this->detector ??= new ProviderDetector(); }
    public function modules(): array {
        $catalog=[];
        foreach(glob($this->root.'/extensions/*/module.json')?:[] as$file){
            if(is_link($file)||is_link(dirname($file)))throw new \RuntimeException('Modulverknüpfung abgewiesen.');
            $m=json_decode(file_get_contents($file),true,64,JSON_THROW_ON_ERROR);$id=$m['id']??'';
            if(!is_string($id)||!preg_match('/^[a-z][a-z0-9]*(?:[.-][a-z0-9]+)*$/D',$id)||basename(dirname($file))!==$id||!is_array($m['requires']??null))throw new \RuntimeException('Ungültiges Modulmanifest.');
            $catalog[$id]=$m;
        }
        return $catalog;
    }
    public function available(): bool { return !class_exists('Config',false) && !file_exists($this->root.'/config/config.inc.php') && !is_link($this->root.'/config/config.inc.php') && !getenv('SMF_CONFIG_FILE'); }
    public function keyFile(): string { return $this->root.'/config/setup-access.php'; }
    public function token(): string {
        $file=$this->keyFile();if(is_link($file)||!is_file($file))return '';
        return preg_match('/^\/\/ SMF_SETUP_TOKEN: ([A-Za-z0-9_-]{32,128})\r?$/m',file_get_contents($file),$m)?$m[1]:'';
    }
    public function authorized(string $token): bool {
        $file=$this->keyFile();
        return $this->available() && !is_link($file) && is_file($file) && filemtime($file)>time()-86400 && preg_match('/^[A-Za-z0-9_-]{32,128}$/D',$token) && hash_equals($this->token(), $token);
    }
    public function prepare(): string {
        if (!$this->available()) throw new \RuntimeException('Installation bereits konfiguriert. Für Updates den Migrationsweg verwenden.');
        $token=bin2hex(random_bytes(32));$file=@fopen($this->keyFile(),'x');
        if (!$file) throw new \RuntimeException('setup-access.php existiert bereits oder der Konfigurationsordner ist nicht beschreibbar.');
        $content="<?php\nhttp_response_code(404); exit;\n// SMF_SETUP_TOKEN: ".$token."\n";
        try { if (fwrite($file,$content)!==strlen($content)) throw new \RuntimeException('Startschlüssel konnte nicht gespeichert werden.'); chmod($this->keyFile(),0600); } finally { fclose($file); }
        return $token;
    }
    public function validate(array $input): array {
        if(version_compare(PHP_VERSION,'8.3','<')||!class_exists('PDO')||!extension_loaded('mbstring'))throw new \RuntimeException('PHP >=8.3 mit PDO und mbstring ist erforderlich.');
        $defaults=['private_dir'=>'','site_url'=>'','site_name'=>'Mein Shop','support_email'=>'','system_email'=>'','admin_name'=>'','admin_email'=>'','admin_password'=>'','db_type'=>'mysql','db_host'=>'localhost','db_port'=>'3306','db_name'=>'','db_user'=>'','db_password'=>'','db_socket'=>'','mail_mode'=>'system','smtp_host'=>'','smtp_port'=>'465','smtp_user'=>'','smtp_password'=>''];
        $v=[];foreach($defaults as$key=>$default){$value=$input[$key]??$default;if(!is_string($value)&&!is_int($value))throw new \InvalidArgumentException('Ungültige Formulareingabe.');$v[$key]=str_contains($key,'password')?(string)$value:trim((string)$value);if(strlen($v[$key])>4096||str_contains($v[$key],"\0"))throw new \InvalidArgumentException('Eingabe zu lang oder ungültig.');}
        if (!in_array($v['db_type'],['mysql','sqlite'],true)) throw new \InvalidArgumentException('Der Shop unterstützt MySQL/MariaDB oder SQLite.');
        if (!preg_match('/^[A-Za-z0-9_.-]{3,100}$/D',$v['admin_name']) || mb_strlen($v['admin_password'])<12 || strlen($v['admin_password'])>4096) throw new \InvalidArgumentException('Administratorname: 3–100 Zeichen; Passwort: mindestens 12 Zeichen.');
        foreach(['admin_email','support_email','system_email']as$key)if(!filter_var($v[$key],FILTER_VALIDATE_EMAIL))throw new \InvalidArgumentException('Gültige Administrator-, Support- und Absenderadresse angeben.');
        $v['site_url']=ProviderDetector::baseUrl($v['site_url']);
        if (parse_url($v['site_url'],PHP_URL_PATH)) throw new \InvalidArgumentException('Den Shop im Wurzelverzeichnis einer eigenen HTTPS-Domain installieren.');
        if ($v['site_name']==='' || mb_strlen($v['site_name'])>180) throw new \InvalidArgumentException('Shopname fehlt oder ist zu lang.');
        if ($v['db_type']==='mysql' && (!preg_match('/^[A-Za-z0-9_.:-]{1,253}$/D',$v['db_host']) || !preg_match('/^[A-Za-z0-9_]{1,64}$/D',$v['db_name']) || $v['db_user']==='' || !ctype_digit($v['db_port']) || (int)$v['db_port']<1 || (int)$v['db_port']>65535 || preg_match('/[;\r\n]/',$v['db_socket']))) throw new \InvalidArgumentException('Datenbankhost, Port, Datenbankname und Benutzer prüfen.');
        if (!in_array($v['mail_mode'],['system','smtps'],true)) throw new \InvalidArgumentException('Mailverfahren ungültig.');
        if ($v['mail_mode']==='smtps' && (!preg_match('/^[a-zA-Z0-9.-]{1,253}$/D',$v['smtp_host']) || !ctype_digit($v['smtp_port']) || (int)$v['smtp_port']<1 || (int)$v['smtp_port']>65535)) throw new \InvalidArgumentException('SMTPS-Host und Port prüfen.');
        $v['private_dir']=$this->privatePath($v['private_dir'],false);
        $v['modules']=$input['modules']??['shop.billing','shop.contracts','shop.communications'];
        $catalog=$this->modules();
        if(!is_array($v['modules']) || array_filter($v['modules'],fn($id)=>!is_string($id)||!isset($catalog[$id])))throw new \InvalidArgumentException('Unbekanntes Shopmodul.');
        $selected=array_merge(['shop.storage','shop.catalog','shop.portal','shop.orders','shop.connections'],$v['modules']);
        foreach(['ispconfig','ogp','proxmox','ovh']as$id){
            $row=$input[$id]??[];if(!is_array($row))throw new \InvalidArgumentException('Ungültige Providerdaten.');$v[$id]=[];
            foreach(['url','username','password','token','bridge_key','ca_file','local_root','server_id','mail_server_id','db_server_id','auth_mode','token_id','token_secret','node','pool','endpoint','application_key','application_secret','consumer_key','subsidiary']as$key){
                $default=match($key){'server_id','mail_server_id','db_server_id'=>'1','auth_mode'=>'password','endpoint'=>'ovh-eu','subsidiary'=>'DE',default=>''};
                $value=$row[$key]??$default;if(!is_string($value)&&!is_int($value))throw new \InvalidArgumentException('Ungültige Providerdaten.');
                $v[$id][$key]=in_array($key,['password','token','token_secret','bridge_key','application_secret','consumer_key'],true)?(string)$value:trim((string)$value);
                if(strlen($v[$id][$key])>4096||str_contains($v[$id][$key],"\0"))throw new \InvalidArgumentException('Ungültige Providerdaten.');
            }
            if($v[$id]['url']!=='')$v[$id]['url']=ProviderDetector::baseUrl($v[$id]['url']);
            if($v[$id]['url']!=='' || ($id==='ovh' && $v[$id]['application_key']!==''))$selected[]='provider.'.$id;
        }
        $ordered=[];$visit=function(string$id,array$chain=[])use(&$visit,&$ordered,$catalog){
            if(isset($ordered[$id]))return;if(in_array($id,$chain,true)||!isset($catalog[$id]))throw new \RuntimeException('Ungültige Modulabhängigkeit.');
            foreach(array_keys($catalog[$id]['requires'])as$dependency)$visit($dependency,[...$chain,$id]);$ordered[$id]=true;
        };foreach($selected as$id)$visit($id);$v['modules']=array_keys($ordered);
        if (!ctype_digit($v['ispconfig']['server_id']) || (int)$v['ispconfig']['server_id']<1) throw new \InvalidArgumentException('ISPConfig-Server-ID muss positiv sein.');
        return $v;
    }
    public function inspect(array $input): array {
        $v=$this->validate($input);$required=['pdo','pdo_'.$v['db_type'],'mbstring','simplexml','dom','ctype','tokenizer','openssl','session','json'];
        if($v['ispconfig']['url']!==''||$v['ogp']['url']!=='')$required[]='curl';
        if($v['ispconfig']['url']!=='')$required[]='soap';
        $missing=array_values(array_filter($required,fn($id)=>!extension_loaded($id)));
        if(version_compare(PHP_VERSION,'8.3','<')||$missing)throw new \RuntimeException('PHP >=8.3 mit allen benötigten Erweiterungen erforderlich: '.implode(', ',$missing));
        if(!is_writable($this->root.'/config'))throw new \RuntimeException('PHP benötigt Schreibrechte im Ordner config für die abschließende Konfigurationsverknüpfung.');
        $providers=[];foreach(['ispconfig','ogp','proxmox','ovh']as$id)$providers[$id]=$this->detector->probe($id,$v[$id]);
        $db=$this->connect($v,true);$tables=$this->tables($db);
        $journal=$v['private_dir'].'/setup/installation.json';
        if($tables&&!is_file($journal))throw new \RuntimeException('Die Zieldatenbank ist nicht leer. Bestehende Installationen werden hier nicht überschrieben.');
        return ['php'=>PHP_VERSION,'database'=>$v['db_type'],'database_empty'=>!$tables,'providers'=>$providers,'local'=>$this->detector->local(['ispconfig'=>$v['ispconfig']['local_root'],'ogp'=>$v['ogp']['local_root']]),'modules'=>$v['modules'],'environment'=>'sandbox','live_operations'=>false];
    }
    private function privatePath(string $path,bool $create): string {
        if($path==='' || !preg_match('~^(?:/|[A-Za-z]:[\\\\/])~',$path) || str_contains($path,"\0") || preg_match('~(?:^|[\\\\/])\.\.(?:[\\\\/]|$)~',$path))throw new \InvalidArgumentException('Absoluten privaten Speicherpfad außerhalb des Webroots angeben.');
        $ancestor=$path;$tail=[];while(!file_exists($ancestor)){array_unshift($tail,basename($ancestor));$next=dirname($ancestor);if($next===$ancestor)throw new \RuntimeException('Privater Speicherpfad nicht auflösbar.');$ancestor=$next;}
        if(is_link($path)||!is_dir($ancestor)||!is_writable($ancestor))throw new \RuntimeException('Privater Speicher nicht beschreibbar oder symbolisch verknüpft.');
        $canonical=rtrim(str_replace('\\','/',realpath($ancestor)),'/').($tail?'/'.implode('/',$tail):'');
        foreach([$this->root,realpath($_SERVER['DOCUMENT_ROOT']??$this->root)?:$this->root]as$public){$public=rtrim(str_replace('\\','/',$public),'/');if($canonical===$public||str_starts_with($canonical.'/',$public.'/')||str_starts_with($public.'/',$canonical.'/'))throw new \InvalidArgumentException('Privater Speicher muss außerhalb des Anwendungs- und Dokumentverzeichnisses liegen.');}
        if($create&&!is_dir($canonical)&&!mkdir($canonical,0700,true))throw new \RuntimeException('Privater Speicher konnte nicht angelegt werden.');
        return $canonical;
    }
    private function connect(array $v,bool $preview=false): \PDO {
        if($v['db_type']==='sqlite')$dsn=$preview&&!is_file($v['private_dir'].'/data/shop.sqlite')?'sqlite::memory:':'sqlite:'.$v['private_dir'].'/data/shop.sqlite';
        else $dsn=($v['db_socket']!==''?'mysql:unix_socket='.$v['db_socket']:'mysql:host='.$v['db_host'].';port='.$v['db_port']).';dbname='.$v['db_name'].';charset=utf8mb4';
        try{return new \PDO($dsn,$v['db_type']==='mysql'?$v['db_user']:null,$v['db_type']==='mysql'?$v['db_password']:null,[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_TIMEOUT=>5]);}catch(\PDOException){throw new \RuntimeException('Datenbank nicht erreichbar. Zugang, PDO-Treiber und Datenbankfreigabe prüfen.');}
    }
    private function tables(\PDO $db):array{return $db->query($db->getAttribute(\PDO::ATTR_DRIVER_NAME)==='sqlite'?"SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'":'SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);}
    private function save(string $path,array|string $value):void{
        if(is_link($path))throw new \RuntimeException('Symbolische Verknüpfung abgewiesen.');
        $temp=tempnam(dirname($path),'.setup-');if(!$temp)throw new \RuntimeException('Privates Schreiben fehlgeschlagen.');
        try{$data=is_array($value)?json_encode($value,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT)."\n":$value;if(file_put_contents($temp,$data,LOCK_EX)!==strlen($data))throw new \RuntimeException('Privates Schreiben fehlgeschlagen.');chmod($temp,0600);if(!rename($temp,$path))throw new \RuntimeException('Private Aktivierung fehlgeschlagen.');}finally{if(is_file($temp))unlink($temp);}
    }
    private function configuration(array $v,array $report):string{
        $values=['PRIVATE_STORAGE_DIR'=>$v['private_dir'],'DB_TYPE'=>$v['db_type'],'DB_USEING'=>$v['db_type']==='mysql','DB_SQLITE_USEING'=>$v['db_type']==='sqlite','DB_PGSQL_USEING'=>false,'DB_MONGO_USEING'=>false,'DB_SQLITE_PATH'=>$v['private_dir'].'/data/shop.sqlite','DB_HOST'=>$v['db_host'],'DB_PORT'=>(int)$v['db_port'],'DB_NAME'=>$v['db_name'],'DB_USER'=>$v['db_user'],'DB_PASS'=>$v['db_password'],'DB_SOCKET'=>$v['db_socket'],'PROXMOX_USEING'=>$report['providers']['proxmox']['enabled'],'PROXMOX_HOST'=>$v['proxmox']['url'],'PROXMOX_USER'=>$v['proxmox']['username'],'PROXMOX_PASSWORD'=>$v['proxmox']['password'],'OVH_USEING'=>$report['providers']['ovh']['enabled'],'OVH_ENDPOINT'=>$v['ovh']['endpoint'],'OVH_APPLICATION_KEY'=>$v['ovh']['application_key'],'OVH_APPLICATION_SECRET'=>$v['ovh']['application_secret'],'OVH_CONSUMER_KEY'=>$v['ovh']['consumer_key'],'FRONTPANEL_SITE_NAME'=>$v['site_name'],'FRONTPANEL_SITE_URL'=>$v['site_url'],'FRONTPANEL_SUPPORT_EMAIL'=>$v['support_email'],'FRONTPANEL_SYSTEM_EMAIL'=>$v['system_email'],'FRONTPANEL_ADMIN_EMAIL'=>$v['admin_email'],'ISPCONFIG_USEING'=>$report['providers']['ispconfig']['enabled'],'ISPCONFIG_SERVER_ID'=>(int)$v['ispconfig']['server_id'],'ISPCONFIG_HOST'=>$v['ispconfig']['url'],'ISPCONFIG_USER'=>$v['ispconfig']['username'],'ISPCONFIG_PASSWORD'=>$v['ispconfig']['password'],'OGP_USEING'=>$report['providers']['ogp']['enabled'],'OGP_HOST'=>$v['ogp']['url'],'OGP_USER'=>'','OGP_PASSWORD'=>'','OGP_TOKEN'=>$v['ogp']['token']];
        $source=file_get_contents($this->root.'/config/config.example.php');
        foreach($values as$key=>$value){$source=preg_replace_callback('/\bconst '.preg_quote($key,'/').'\s*=\s*[^;]+;/',fn()=> 'const '.$key.' = '.var_export($value,true).';',$source,1,$count);if($count!==1)throw new \RuntimeException('Konfigurationsvorlage unvollständig.');}
        foreach(['ispconfig','ogp']as$id)if($v[$id]['ca_file']!=='')$source.="\nif (getenv('SMF_".strtoupper($id)."_CA_FILE') === false) putenv(".var_export('SMF_'.strtoupper($id).'_CA_FILE='.$v[$id]['ca_file'],true).");\n";
        if($v['mail_mode']==='smtps')foreach(['HOST'=>$v['smtp_host'],'PORT'=>$v['smtp_port'],'USER'=>$v['smtp_user'],'PASS'=>$v['smtp_password']]as$key=>$value)$source.="\nif (getenv('SMF_SMTP_".$key."') === false) putenv(".var_export('SMF_SMTP_'.$key.'='.$value,true).");\n";
        return $source;
    }
    public function install(array $input,string $token):array {
        if(!$this->authorized($token))throw new \RuntimeException('Installation gesperrt oder Startschlüssel ungültig/abgelaufen.');
        @set_time_limit(120);
        $lockPath=$this->root.'/config/.setup.lock';if(is_link($lockPath))throw new \RuntimeException('Installationssperre ungültig.');$lock=fopen($lockPath,'c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new \RuntimeException('Eine Installation läuft bereits.');chmod($lockPath,0600);
        try{
            if(!$this->authorized($token))throw new \RuntimeException('Installation wurde zwischenzeitlich abgeschlossen.');
            $v=$this->validate($input);$report=$this->inspect($v);
            foreach(['ispconfig','ogp','proxmox','ovh']as$id)if(($v[$id]['url']!=='' || ($id==='ovh' && $v[$id]['application_key']!==''))&&!$report['providers'][$id]['enabled']&&empty($input['allow_unverified']))throw new \RuntimeException('Panelprüfung nicht erfolgreich. Zugang korrigieren oder die Einrichtung mit deaktivierter Anbindung ausdrücklich auswählen.');
            $fingerprint=hash('sha256',json_encode($v,JSON_THROW_ON_ERROR).hash('sha256',$token).$this->root);$private=$v['private_dir'];$journalFile=$private.'/setup/installation.json';$journal=is_file($journalFile)?json_decode(file_get_contents($journalFile),true,64,JSON_THROW_ON_ERROR):null;
            if($journal&&(!hash_equals($journal['fingerprint']??'',$fingerprint)||($journal['root']??'')!==$this->root))throw new \RuntimeException('Ein anderer Installationsversuch verwendet diesen Speicher. Für Fortsetzen dieselben Angaben und denselben Startschlüssel verwenden.');
            if(!$journal && is_dir($private) && array_diff(scandir($private),['.','..']))throw new \RuntimeException('Für eine Neuinstallation ein leeres privates Verzeichnis verwenden.');
            $this->privatePath($private,true);foreach(['setup','config','data','locks']as$dir){if(is_link($private.'/'.$dir))throw new \RuntimeException('Privates Unterverzeichnis ungültig.');if(!is_dir($private.'/'.$dir)&&!mkdir($private.'/'.$dir,0700))throw new \RuntimeException('Privates Unterverzeichnis fehlt.');chmod($private.'/'.$dir,0700);}
            $db=$this->connect($v);if(!$journal&&$this->tables($db))throw new \RuntimeException('Datenbank wurde zwischenzeitlich befüllt; Installation gestoppt.');
            $journal??=['root'=>$this->root,'fingerprint'=>$fingerprint,'started_at'=>gmdate('c')];$journal['phase']='migrations';$this->save($journalFile,$journal);
            $runtime=$private.'/config/runtime.php';$configuration=$this->configuration($v,$report);if(is_file($runtime)&&!hash_equals(hash('sha256',$configuration),hash_file('sha256',$runtime)))throw new \RuntimeException('Die Konfiguration des begonnenen Versuchs wurde verändert.');$this->save($runtime,$configuration);
            putenv('SMF_CONFIG_FILE='.$runtime);putenv('SMF_PRIVATE_DIR='.$private);
            require_once $this->root.'/framework.php';require_once $this->root.'/src/core/SecuritySchema.php';require_once $this->root.'/src/core/EmailTemplateManager.php';
            $db=\Database::getInstance()->getConnection();\HttpSecurity::useConnection($db);\SchemaManager::migrate($db);\SecuritySchema::migrate($db);\SMF\Core\CoreMigrations::migrate($db);(new \EmailTemplateManager($db))->createDefaultTemplates();
            $keyFile=$private.'/config/shop.key';if(is_link($keyFile))throw new \RuntimeException('Ungültiger Shopschlüssel.');if(!is_file($keyFile)){$keyHandle=fopen($keyFile,'x');if(!$keyHandle)throw new \RuntimeException('Shopschlüssel konnte nicht angelegt werden.');try{chmod($keyFile,0600);$key=bin2hex(random_bytes(32));if(fwrite($keyHandle,$key)!==strlen($key))throw new \RuntimeException('Shopschlüssel unvollständig.');}finally{fclose($keyHandle);}}if(!preg_match('/^[a-f0-9]{64}$/D',file_get_contents($keyFile)))throw new \RuntimeException('Shopschlüssel ungültig.');$keyHash=hash_file('sha256',$keyFile);if(isset($journal['shop_key_hash'])&&!hash_equals($journal['shop_key_hash'],$keyHash))throw new \RuntimeException('Shopschlüssel wurde nach Installationsbeginn geändert.');$journal['shop_key_hash']=$keyHash;$this->save($journalFile,$journal);
            $registry=new \SMF\Core\ModuleRegistry($db,$this->root.'/extensions',\VersionFile::version(file_get_contents($this->root.'/src/sys.conf.php')));
            foreach($v['modules']as$id){$registry->install($id);$registry->enable($id);}
            \SMF\Core\Kernel::reset();\SMF\Core\ProviderAccounts::bootstrapShop();
            foreach(['ispconfig','ogp','proxmox','ovh']as$id)if(in_array('provider.'.$id,$v['modules'],true)){
                $credentials=$v[$id];
                if($id==='ispconfig'){if($credentials['url']!=='')$credentials['url'].='/remote/json.php';$credentials['reseller_id']='0';$credentials['client_template_id']='0';}
                $credentials['ca_pem']=$credentials['ca_file']!==''?(string)file_get_contents($credentials['ca_file'],false,null,0,20001):'';
                \integration_save($id,$credentials,(bool)$report['providers'][$id]['enabled']);
            }
            $db->beginTransaction();try{$count=(int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();if($count===0){$db->prepare("INSERT INTO users (username,email,password_hash,full_name,role,active) VALUES (?,?,?,?,'admin','y')")->execute([$v['admin_name'],mb_strtolower($v['admin_email']),password_hash($v['admin_password'],PASSWORD_DEFAULT),$v['admin_name']]);}else{$q=$db->prepare("SELECT password_hash FROM users WHERE username=? AND email=? AND role='admin' AND active='y'");$q->execute([$v['admin_name'],mb_strtolower($v['admin_email'])]);$hash=$q->fetchColumn();if($count!==1||!is_string($hash)||!password_verify($v['admin_password'],$hash))throw new \RuntimeException('Administratorbestand stimmt nicht mit dem begonnenen Versuch überein.');}$db->commit();}catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
            $local="<?php\n";foreach(['proxmox'=>$report['providers']['proxmox']['enabled'],'ovh'=>$report['providers']['ovh']['enabled'],'ispconfig'=>$report['providers']['ispconfig']['enabled'],'file-editor'=>false]as$id=>$enabled)$local.='$plugins['.var_export($id,true)."][\"enabled\"] = ".var_export($enabled,true).";\n";$this->save($private.'/config/system.local.php',$local);
            \SMF\Core\ShopSettings::save(['site_name'=>$v['site_name'],'site_url'=>$v['site_url'],'support_email'=>$v['support_email'],'environment'=>'sandbox']);
            $journal['phase']='complete';$journal['completed_at']=gmdate('c');$journal['report']=$report;$this->save($journalFile,$journal);
            $shim="<?php\nrequire_once ".var_export($runtime,true).";\n";$file=fopen($this->root.'/config/config.inc.php','x');if(!$file)throw new \RuntimeException('Konfiguration wurde zwischenzeitlich angelegt.');try{chmod($this->root.'/config/config.inc.php',0600);if(fwrite($file,$shim)!==strlen($shim))throw new \RuntimeException('Konfigurationsverknüpfung konnte nicht geschrieben werden.');}finally{fclose($file);}
            if(function_exists('opcache_invalidate'))opcache_invalidate($this->root.'/config/config.inc.php',true);
            @unlink($this->keyFile());return $report+['installed'=>true,'cron_configured'=>false,'mail_delivery_tested'=>false];
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
}
