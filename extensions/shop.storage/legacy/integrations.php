<?php
/** Credentials never enter HTML or logs. AES-GCM authenticates provider context. */
function secret_seal(array $value, string $context): string {
    $nonce=random_bytes(12);$tag='';
    $cipher=openssl_encrypt(json_encode($value,JSON_THROW_ON_ERROR),'aes-256-gcm',hash_hkdf('sha256',ORGA_APP_KEY,32,'orga integration credentials'),OPENSSL_RAW_DATA,$nonce,$tag,$context);
    if($cipher===false)throw new RuntimeException('Zugangsdaten konnten nicht verschlüsselt werden.');
    return base64_encode($nonce.$tag.$cipher);
}
function secret_open(string $value, string $context): array {
    $raw=base64_decode($value,true);
    if($raw===false || strlen($raw)<29)throw new RuntimeException('Gespeicherte Zugangsdaten sind ungültig.');
    $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',hash_hkdf('sha256',ORGA_APP_KEY,32,'orga integration credentials'),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),$context);
    if($plain===false)throw new RuntimeException('Gespeicherte Zugangsdaten konnten nicht entschlüsselt werden.');
    return json_decode($plain,true,32,JSON_THROW_ON_ERROR);
}
function integration_definitions(): array {
    $definitions = [
      'ovh'=>['name'=>'OVHcloud','description'=>'Domainverfügbarkeit, Registrierung und DNS-Zonen.','icon'=>'globe','fields'=>[
        'endpoint'=>['API-Region','select',['ovh-eu'=>'Europa','ovh-ca'=>'Kanada','ovh-us'=>'USA']], 'subsidiary'=>['OVH-Vertragsland','text','DE'],
        'application_key'=>['Application Key','secret'], 'application_secret'=>['Application Secret','secret'], 'consumer_key'=>['Consumer Key','secret'],
      ]],
      'ispconfig'=>['name'=>'ISPConfig 3','description'=>'Webspace, FTP-Zugänge, Postfächer und Datenbanken.','icon'=>'package','fields'=>[
        'url'=>['Remote-API-URL','url','https://panel.example.de:8080/remote/json.php'], 'username'=>['Remote-Benutzer','text',''], 'password'=>['Remote-Passwort','secret'],
        'server_id'=>['Webserver-ID','number','1'], 'mail_server_id'=>['Mailserver-ID','number','1'], 'db_server_id'=>['Datenbankserver-ID','number','1'],
        'web_ip'=>['Webserver-IP','text','*'], 'ca_pem'=>['Eigenes CA-Zertifikat (PEM, optional)','pem'],
      ]],
      'proxmox'=>['name'=>'Proxmox VE','description'=>'Virtuelle Server aus vorbereiteten Templates und Kundensteuerung.','icon'=>'server','fields'=>[
        'auth_mode'=>['Anmeldung','select',['password'=>'Benutzername & Passwort','token'=>'API-Token']],
        'url'=>['Proxmox-Host','host','pve.example.de:8006'],
        'username'=>['Benutzername (z. B. root@pam)','text',''], 'password'=>['Proxmox-Passwort','secret'],
        'token_id'=>['API-Token-ID','text',''], 'token_secret'=>['API-Token-Secret','secret'],
        'node'=>['Ziel-Node','text',''], 'pool'=>['Shop-Ressourcenpool','text',''], 'ca_pem'=>['Eigenes CA-Zertifikat (PEM, optional)','pem'],
      ]],
      'paypal'=>['name'=>'PayPal Business','description'=>'Einzelzahlungen, wiederkehrende Zahlungen und bestätigte Zahlungsereignisse.','icon'=>'lock','fields'=>[
        'mode'=>['Umgebung','select',['sandbox'=>'Sandbox (Test)','live'=>'Live']], 'client_id'=>['REST-App Client ID','secret'], 'client_secret'=>['REST-App Secret','secret'], 'webhook_id'=>['Webhook-ID','text',''], 'merchant_id'=>['PayPal-Händler-ID dieser Umgebung','text',''],
        'terms_url'=>['URL deiner Vertragsbedingungen','url',''], 'withdrawal_url'=>['URL deiner Widerrufsinformationen','url',''],
      ]],
    ];
    foreach(['ovh','proxmox','ispconfig']as$id)if(!is_file(dirname(__DIR__,2).'/provider.'.$id.'/Adapter.php'))unset($definitions[$id]);
    foreach(SMF\Core\Kernel::boot()->modules()->providers() as $id=>$entry){
        $definition=$entry['definition'];
        $definitions[$id]=['name'=>$definition['name']??$id,'description'=>'Modularer Provideranschluss','icon'=>'server','fields'=>$definition['fields']];
    }
    return $definitions;
}
function integration_row(string $provider): ?array { return one('SELECT * FROM shop_integrations WHERE provider=?',[$provider]); }
function integration_config(string $provider, bool $requireEnabled=true): array {
    if(!isset(integration_definitions()[$provider]))throw new RuntimeException('Unbekannter Anbieter.');
    $row=integration_row($provider);
    if($requireEnabled && !SMF\Core\ProviderAccounts::enabled($provider))throw new RuntimeException('Die Providerverbindung ist deaktiviert.');
    if(!$row && in_array($provider,['ovh','proxmox','ispconfig','ogp'],true))return SMF\Core\ProviderAccounts::fallback($provider);
    if(!$row || ($requireEnabled && !$row['enabled']))throw new RuntimeException(integration_definitions()[$provider]['name'].' ist noch nicht eingerichtet.');
    return secret_open($row['secret_config'],'provider:'.$provider);
}
function integration_ready(string $provider): bool { if($provider==='manual')return true;$row=integration_row($provider);return $row && $row['enabled'] && $row['tested_at'] && $row['test_result']==='ok'; }
function integration_identity(string $provider,?array $config=null): string {
    if($provider==='manual')return hash('sha256','orga-consult:manual-fulfillment');
    if($config===null && in_array($provider,['ovh','proxmox','ispconfig','ogp'],true) && !integration_row($provider))return ResourceAccess::connectionId($provider);
    $config??=integration_config($provider,false);
    $keys=match($provider){'ovh'=>['endpoint','application_key','consumer_key'],'proxmox'=>($config['auth_mode']??'token')==='password'?['url','username','pool']:['url','token_id','pool'],'ispconfig'=>['url','username'],'ogp'=>['url','token'],'paypal'=>['mode','client_id'],default=>array_keys($config)};
    return hash('sha256',json_encode(array_intersect_key($config,array_flip($keys))));
}
function checked_api_url(string $url): string {
    $p=parse_url($url);
    if(!$p || ($p['scheme']??'')!=='https' || empty($p['host']) || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment']) || preg_match('/[\x00-\x20]/',$url)) throw new RuntimeException('Bitte eine HTTPS-Adresse ohne Zugangsdaten, Query oder Fragment eintragen.');
    return rtrim($url,'/');
}
function proxmox_username(string $username): string {
    $username=trim($username);
    if(!str_contains($username,'@'))$username.='@pam';
    if(strlen($username)>64 || !preg_match('/^[a-zA-Z0-9_.-]+@[a-zA-Z0-9_.-]+$/D',$username))throw new RuntimeException('Bitte einen Proxmox-Benutzer wie root@pam oder shop@pve eintragen.');
    return $username;
}
function proxmox_host_url(string $host): string {
    if($host==='')return '';
    if(!str_contains($host,'://')){
        $host='https://'.$host;
        if(parse_url($host,PHP_URL_PORT)===null)$host=rtrim($host,'/').':8006';
    }
    $url=checked_api_url($host);
    if((parse_url($url,PHP_URL_PATH)??'')!=='')throw new RuntimeException('Bitte nur den Proxmox-Host mit Port eintragen, zum Beispiel https://pve.example.de:8006.');
    return $url;
}
function proxmox_has_resource_target(?array $config=null): bool {
    $config??=integration_config('proxmox');
    return !empty($config['node']) && !empty($config['pool']);
}
function integration_save(string $provider,array $input,bool $enabled): void {
    $definition=integration_definitions()[$provider]??null;
    if(!$definition)throw new RuntimeException('Unbekannter Anbieter.');
    $row=integration_row($provider);$old=$row?secret_open($row['secret_config'],'provider:'.$provider):[];$config=[];
    if($provider==='proxmox')$input['auth_mode']??=$old['auth_mode']??'token';
    foreach($definition['fields'] as $key=>$field){
        $value=(string)($input[$key]??($provider==='paypal' && in_array($key,['terms_url','withdrawal_url'],true)?($old[$key]??''):(in_array($field[1],['number','integer'],true)?($field[2]??''):'')));if(!($provider==='proxmox' && $key==='password'))$value=trim($value);$type=$field[1];
        if(strlen($value)>20000)throw new RuntimeException('Ein Konfigurationsfeld ist zu lang.');
        if($type==='secret' && $value==='')$value=$old[$key]??'';
        if($type==='select' && !array_key_exists($value,$field[2]))throw new RuntimeException('Bitte eine gültige Auswahl treffen.');
        if($type==='url' && $value!=='')$value=checked_api_url($value);
        if($type==='host')$value=proxmox_host_url($value);
        if($type==='number' && $value!=='' && (!preg_match('/^\d+$/D',$value) || (int)$value<1))throw new RuntimeException('Server-IDs müssen positive Zahlen sein.');
        if($type==='integer' && !preg_match('/^\d{1,9}$/D',$value))throw new RuntimeException('Bitte eine nichtnegative ganze Zahl eintragen.');
        if($type==='pem' && $value!==''){
            $cert=openssl_x509_read($value);
            if(!$cert)throw new RuntimeException('Das CA-Zertifikat ist kein gültiges PEM-Zertifikat.');
        }
        $optional=($field[3]??false)===true || in_array($key,['ca_pem','web_ip','webhook_id','terms_url','withdrawal_url'],true);
        if($provider==='proxmox')$optional=$optional || in_array($key,['node','pool'],true) || in_array($key,$input['auth_mode']==='password'?['token_id','token_secret']:['username','password'],true);
        if($enabled && !$optional && $value==='')throw new RuntimeException('Bitte alle erforderlichen Zugangsdaten ausfüllen.');
        if(preg_match('/[\r\n]/',$value) && $type!=='pem')throw new RuntimeException('Einzeilige Konfigurationsfelder dürfen keine Zeilenumbrüche enthalten.');
        $config[$key]=$value;
    }
    if($provider==='ovh' && !preg_match('/^[A-Z]{2}$/D',$config['subsidiary']))throw new RuntimeException('Bitte ein zweistelliges OVH-Vertragsland eintragen, zum Beispiel DE.');
    if($provider==='ogp' && $enabled && !preg_match('/^[a-f0-9]{64}$/Di',$config['token']))throw new RuntimeException('OGP benötigt einen vorhandenen API-Token mit 64 Hexzeichen.');
    if($provider==='proxmox'){
        if($config['auth_mode']==='password' && $config['username']!=='')$config['username']=proxmox_username($config['username']);
        if($enabled && $config['auth_mode']==='token' && !preg_match('/^[a-zA-Z0-9_.-]+@[^!\s]+![a-zA-Z0-9_.-]+$/D',$config['token_id']))throw new RuntimeException('Die Token-ID hat das Format benutzer@realm!tokenname.');
        foreach(['node','pool'] as $key)if($config[$key]!=='' && !preg_match('/^[a-zA-Z0-9_.-]+$/D',$config[$key]))throw new RuntimeException('Node und Pool dürfen nur Buchstaben, Zahlen, Punkt, Bindestrich und Unterstrich enthalten.');
    }
    $encrypted=secret_seal($config,'provider:'.$provider);
    if($row)query('UPDATE shop_integrations SET enabled=?,secret_config=?,tested_at=NULL,test_result=NULL,updated_at=CURRENT_TIMESTAMP WHERE provider=?',[$enabled?1:0,$encrypted,$provider]);
    else query('INSERT INTO shop_integrations (provider,enabled,secret_config) VALUES (?,?,?)',[$provider,$enabled?1:0,$encrypted]);
    SMF\Core\Kernel::boot()->providers()->invalidate($provider);
    audit_event('integration.save',$provider,'ok');
}
function audit_event(string $action,string $resource='',string $result='ok'): void {
    SMF\Core\Kernel::boot()->audit()->record($action,$resource,$result,[], $GLOBALS['smf_job_id']??null,str_starts_with($_SERVER['REQUEST_URI']??'','/admin')?'backend':'customer');
    $actor=isset($_SESSION['admin_id'])?'admin':(isset($_SESSION['customer_id'])?'customer':'system');
    query('INSERT INTO shop_audit (actor_type,actor_id,action,resource,result) VALUES (?,?,?,?,?)',[$actor,$_SESSION['admin_id']??$_SESSION['customer_id']??null,$action,substr($resource,0,150),$result]);
}
class ProviderFailure extends RuntimeException {
    public function __construct(public readonly string $provider,public readonly int $httpStatus=0,public readonly string $reason='') {
        $name=integration_definitions()[$provider]['name']??$provider;
        $hint=match(true){
            $provider==='ispconfig' && $reason==='SSH_CHROOT_REQUIRED'=>'Beim Anlegen des Kunden fehlt die erforderliche SSH-Voreinstellung. Bitte die Hosting-Bereitstellung prüfen.',
            $provider==='ispconfig' && $reason==='WEB_PORTS_REQUIRED'=>'Beim Anlegen der Website fehlen gültige HTTP-/HTTPS-Ports. Bitte die Hosting-Bereitstellung prüfen.',
            $provider==='ispconfig' && $reason==='PERMISSION_DENIED'=>'Dem Remote-Benutzer fehlt die Berechtigung für diese ISPConfig-Funktion.',
            $provider==='ovh' && $reason==='NOT_GRANTED_CALL'=>'Der API-Schlüssel hat keine Freigabe für diesen Aufruf. Bitte die OVH-Berechtigungen unter „Schnittstellen“ neu autorisieren.',
            $provider==='ovh' && in_array($reason,['INVALID_CREDENTIAL','NOT_CREDENTIAL'],true)=>'Der Consumer Key ist ungültig, abgelaufen oder noch nicht bei OVH bestätigt.',
            $provider==='ovh' && $reason==='INVALID_KEY'=>'Bitte Application Key und Application Secret sowie die API-Region prüfen.',
            $provider==='proxmox' && $reason==='ROOT_FEATURE_REQUIRED'=>'Die gewählten LXC-Sonderfunktionen erfordern einen dafür freigegebenen Root-API-Zugang. Bitte die Vorlage und den Proxmox-Zugang prüfen.',
            $httpStatus===403=>'Der Anbieter hat den Zugriff verweigert. Bitte API-Zugangsdaten und Berechtigungen prüfen.',
            default=>'Bitte Verbindung, Berechtigungen und Zertifikat prüfen.',
        };
        parent::__construct($name.' konnte die Anfrage nicht ausführen'.($httpStatus?' (HTTP '.$httpStatus.')':'').'. '.$hint);
    }
    public static function fromResponse(string $provider,int $status,string $body): self {
        // Only fixed error categories are exposed. Upstream messages may contain secrets.
        $data=json_decode($body,true);$reason='';
        if($provider==='proxmox' && $status===403 && is_array($data)){
            $message=is_string($data['message']??null)?$data['message']:'';
            if(str_contains($message,'changing feature flags') && str_contains($message,'only allowed for root@pam'))$reason='ROOT_FEATURE_REQUIRED';
        }
        if($provider==='ovh' && is_array($data)){
            $code=$data['errorCode']??'';
            if(in_array($code,['NOT_GRANTED_CALL','INVALID_CREDENTIAL','NOT_CREDENTIAL','INVALID_KEY'],true))$reason=$code;
            else $reason=match($data['class']??''){'Client::NotGrantedCall'=>'NOT_GRANTED_CALL','Client::InvalidCredential'=>'INVALID_CREDENTIAL','Client::NotCredential'=>'NOT_CREDENTIAL','Client::InvalidKey'=>'INVALID_KEY',default=>''};
            if(!$reason && ($data['message']??'')==='This call has not been granted')$reason='NOT_GRANTED_CALL';
        }
        return new self($provider,$status,$reason);
    }
    public static function fromIspconfig(array $data): self {
        // Never expose upstream messages: SQL faults can include passwords and customer data.
        $message=is_string($data['message']??null)?$data['message']:'';
        $reason=match(true){
            str_contains($message,'ssh_chroot_notempty')=>'SSH_CHROOT_REQUIRED',
            str_contains($message,'http_port_error_regex') || str_contains($message,'https_port_error_regex')=>'WEB_PORTS_REQUIRED',
            str_contains($message,'You do not have the permissions to access this function')=>'PERMISSION_DENIED',
            default=>'',
        };
        return new self('ispconfig',0,$reason);
    }
}
class ProviderHttp {
    public static function request(string $provider,string $method,string $url,?string $body=null,array $headers=[],array $config=[]): array {
        shop_provider_guard($provider,$method,$url,$body);
        // Tests inject a transport only in CLI; a browser request cannot enable it.
        if(PHP_SAPI==='cli' && isset($GLOBALS['shop_test_transport']))return ($GLOBALS['shop_test_transport'])($provider,$method,$url,$body,$headers);
        if(!str_starts_with($url,'https://') || preg_match('/[\r\n]/',$url))throw new ProviderFailure($provider);
        $ssl=['verify_peer'=>true,'verify_peer_name'=>true];
        if(!empty($config['ca_pem'])){
            $dir=shop_private().'/ca';if(!is_dir($dir))mkdir($dir,0700,true);
            $file=$dir.'/'.hash('sha256',$config['ca_pem']).'.pem';
            if(!is_file($file)){file_put_contents($file,$config['ca_pem'],LOCK_EX);chmod($file,0600);}
            $ssl['cafile']=$file;
        }
        $context=stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",array_merge(['Accept: application/json','User-Agent: SMF-Shop/1'],$headers)),'content'=>$body??'','timeout'=>20,'ignore_errors'=>true,'follow_location'=>0,'max_redirects'=>0,'protocol_version'=>1.1],'ssl'=>$ssl]);
        $stream=@fopen($url,'rb',false,$context);
        if(!$stream)throw new ProviderFailure($provider);
        $response=stream_get_contents($stream,2097153);$meta=stream_get_meta_data($stream);fclose($stream);
        if(strlen($response)>2097152 || !empty($meta['timed_out']))throw new ProviderFailure($provider);
        $status=0;foreach($meta['wrapper_data']??[] as $line)if(preg_match('#^HTTP/\S+ (\d{3})#',$line,$m))$status=(int)$m[1];
        if($status<200 || $status>=300)throw ProviderFailure::fromResponse($provider,$status,$response);
        if($response==='')return [];
        $value=json_decode($response,true);
        if(json_last_error()!==JSON_ERROR_NONE){
            $plain=(bool)array_filter($meta['wrapper_data']??[],fn($line)=>(bool)preg_match('~^Content-Type:\s*text/plain(?:;|$)~i',$line));
            if($provider==='ogp' && (parse_url($url,PHP_URL_QUERY)??'')==='setting/get' && !empty($config['legacy_plain_setting']) && $plain && strlen($response)<=65536)$value=$response;else throw new ProviderFailure($provider,$status);
        }
        return ['status'=>$status,'data'=>$value];
    }
}
require_once __DIR__.'/providers.php';
