<?php
class OvhProvider {
    private string $base;
    public function __construct(private ?array $config=null){$this->config??=integration_config('ovh');$this->base=['ovh-eu'=>'https://eu.api.ovh.com/1.0','ovh-ca'=>'https://ca.api.ovh.com/1.0','ovh-us'=>'https://api.us.ovhcloud.com/1.0'][$this->config['endpoint']]??throw new ProviderFailure('ovh');}
    public function call(string $method,string $path,?array $data=null,bool $authenticated=true): mixed {
        $url=$this->base.$path;$body=$data===null?'':json_encode($data,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$headers=['Content-Type: application/json'];
        if($authenticated){
            static $offset=[];
            if(!isset($offset[$this->base])){$r=ProviderHttp::request('ovh','GET',$this->base.'/auth/time');$offset[$this->base]=(int)$r['data']-time();}
            $timestamp=(string)(time()+$offset[$this->base]);
            $signature='$1$'.sha1($this->config['application_secret'].'+'.$this->config['consumer_key'].'+'.$method.'+'.$url.'+'.$body.'+'.$timestamp);
            $headers=array_merge($headers,['X-Ovh-Application: '.$this->config['application_key'],'X-Ovh-Consumer: '.$this->config['consumer_key'],'X-Ovh-Timestamp: '.$timestamp,'X-Ovh-Signature: '.$signature]);
        }
        return ProviderHttp::request('ovh',$method,$url,$body,$headers)['data']??null;
    }
    public function test(): array {
        try{$zones=$this->call('GET','/domain/zone');}
        catch(ProviderFailure $failure){
            if($failure->httpStatus!==403)throw $failure;
            try{$credential=$this->call('GET','/auth/currentCredential');}catch(Throwable $ignored){throw $failure;}
            if(($credential['status']??'')!=='validated')throw $failure;
            $rules=(array)($credential['rules']??[]);
            $rootOnly=$rules && !array_filter($rules,fn($rule)=>($rule['path']??'')!=='/');
            throw new RuntimeException('Der OVH-Schlüssel ist gültig, aber GET /domain/zone ist nicht freigegeben.'.($rootOnly?' Deine Freigaben gelten nur für den Pfad „/“; dieser umfasst keine Unterpfade.':'').' Bitte unter OVH-Berechtigungen eine neue Freigabe vorbereiten und bei OVH bestätigen.');
        }
        return ['message'=>'OVH-Zugriff erfolgreich. '.count((array)$zones).' DNS-Zonen erreichbar.'];
    }
    public function requestAuthorization(array $rules,string $redirect): array {
        $body=json_encode(['accessRules'=>$rules,'redirection'=>$redirect],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $result=ProviderHttp::request('ovh','POST',$this->base.'/auth/credential',$body,['Content-Type: application/json','X-Ovh-Application: '.$this->config['application_key']])['data']??[];
        $url=parse_url((string)($result['validationUrl']??''));
        $authHosts=match($this->config['endpoint']){'ovh-eu'=>['eu.api.ovh.com','api.ovh.com','api.eu.ovhcloud.com'],'ovh-ca'=>['ca.api.ovh.com','api.ca.ovhcloud.com'],'ovh-us'=>['api.us.ovhcloud.com']};
        $legacy=in_array($url['host']??'',$authHosts,true) && in_array($url['path']??'',['/auth','/auth/'],true);
        $sso=$this->config['endpoint']==='ovh-eu' && ($url['host']??'')==='www.ovh.com' && in_array($url['path']??'',['/auth/sso/api','/auth/sso/api/'],true);
        $query=[];parse_str($url['query']??'',$query);
        if(empty($result['consumerKey']) || !$url || ($url['scheme']??'')!=='https' || (!$legacy && !$sso) || !is_string($query['credentialToken']??null) || !$query['credentialToken'] || count($query)!==1 || isset($url['user']) || isset($url['pass']) || isset($url['fragment']) || isset($url['port']))throw new RuntimeException('OVH hat keine gültige Freigabe-Adresse zurückgegeben.');
        return $result;
    }
    public function availability(string $domain): array {
        $domain=valid_domain($domain);if(!$domain)throw new RuntimeException('Bitte eine Domain eingeben.');
        // Cart searches require a signed OVH call. Bind the empty lookup cart to
        // the current account and market; never cache a domain's availability.
        $identity=hash('sha256',json_encode([$this->base,$this->config['subsidiary'],$this->config['application_key'],$this->config['consumer_key']]));
        unset($_SESSION['ovh_availability_cart'],$_SESSION['ovh_availability_expire']);
        for($attempt=0;$attempt<2;$attempt++){
            $cached=$_SESSION['ovh_lookup_cart']??null;
            if(!$cached || ($cached['expires']??0)<time() || !hash_equals($identity,$cached['identity']??'')){
                $cart=$this->call('POST','/order/cart',['ovhSubsidiary'=>$this->config['subsidiary'],'description'=>'Server Management Verfügbarkeitsprüfung','expire'=>gmdate('c',time()+3600)]);
                if(empty($cart['cartId']) || !is_string($cart['cartId']))throw new ProviderFailure('ovh');
                $cached=['id'=>$cart['cartId'],'expires'=>time()+3300,'identity'=>$identity];$_SESSION['ovh_lookup_cart']=$cached;
            }
            try{$offers=$this->call('GET','/order/cart/'.rawurlencode($cached['id']).'/domain?domain='.rawurlencode($domain));}
            catch(ProviderFailure $failure){
                if($attempt===0 && in_array($failure->httpStatus,[404,410],true)){unset($_SESSION['ovh_lookup_cart']);continue;}
                throw $failure;
            }
            if(!is_array($offers) || !array_is_list($offers))throw new ProviderFailure('ovh');
            $result=self::parseAvailability($domain,$offers);if($result['status']==='unknown')throw new ProviderFailure('ovh');
            return $result;
        }
        throw new ProviderFailure('ovh');
    }
    public static function parseAvailability(string $domain,array $offers): array {
        $valid=array_is_list($offers) && !array_filter($offers,fn($o)=>!is_array($o) || !is_string($o['action']??null) || !is_bool($o['orderable']??null));
        $free=$valid?array_values(array_filter($offers,fn($o)=>$o['action']==='create' && $o['orderable']===true)):[];
        usort($free,fn($a,$b)=>(int)standard_domain_offer($b)<=>(int)standard_domain_offer($a));
        $transfer=$valid && (bool)array_filter($offers,fn($o)=>$o['action']==='transfer' && $o['orderable']===true);
        $offer=$free[0]??null;$status=!$valid?'unknown':($offer?'available':'unavailable');
        return ['domain'=>$domain,'status'=>$status,'offer'=>$offer,'transfer_available'=>$transfer,'message'=>match($status){
            'available'=>'Diese Domain kann aktuell neu registriert werden.',
            'unavailable'=>'Für diese Domain bietet OVHcloud aktuell keine Neuregistrierung an. Bitte wähle eine andere Domain oder frage einen Domainumzug an.',
            default=>'OVHcloud hat keine eindeutige Antwort zur Verfügbarkeit geliefert.',
        }];
    }
    public function records(string $zone): array {
        $path='/domain/zone/'.rawurlencode($zone);$ids=(array)$this->call('GET',$path.'/record');
        if(count($ids)>300)throw new RuntimeException('Diese DNS-Zone enthält mehr als 300 Einträge. Bitte den Support kontaktieren.');
        $result=[];foreach($ids as $id)$result[]=$this->call('GET',$path.'/record/'.(int)$id);return $result;
    }
    public function changeRecord(string $zone,string $action,array $record,?int $id=null,bool $nameserverConfirmed=false): void {
        $path='/domain/zone/'.rawurlencode($zone).'/record';
        if(($record['fieldType']??'')==='NS' && !$nameserverConfirmed)throw new RuntimeException('Bitte die Änderung des NS-Eintrags ausdrücklich bestätigen.');
        if($action==='create')$this->call('POST',$path,$record);
        else {
            $current=$this->call('GET',$path.'/'.$id);
            if(($current['fieldType']??'')==='NS' && !$nameserverConfirmed)throw new RuntimeException('Bitte die Änderung des NS-Eintrags ausdrücklich bestätigen.');
            if(!in_array($current['fieldType']??'',['A','AAAA','CNAME','MX','TXT','SRV','CAA','NS'],true))throw new RuntimeException('Dieser Eintragstyp wird nur durch den Support geändert.');
            if($action==='update' && ($record['fieldType']??'')!==$current['fieldType'])throw new RuntimeException('Der DNS-Eintragstyp kann bei einer Änderung nicht gewechselt werden.');
            if($action==='delete')$this->call('DELETE',$path.'/'.$id);
            else{$edit=$record;unset($edit['fieldType']);$this->call('PUT',$path.'/'.$id,$edit);}
        }
        try{$this->call('POST','/domain/zone/'.rawurlencode($zone).'/refresh');}
        catch(ProviderFailure $e){throw new RuntimeException('Der Eintrag wurde gespeichert. Die Veröffentlichung der DNS-Zone ist noch offen; bitte „Zone veröffentlichen“ auswählen.');}
    }
}
class ProxmoxProvider {
    private ?array $ticket=null;
    private function passwordHeaders(string $method): array {
        if(!$this->ticket || $this->ticket['expires']<time()){
            $body=http_build_query(['username'=>proxmox_username($this->config['username']??''),'password'=>$this->config['password']??''],'','&',PHP_QUERY_RFC3986);
            try{$response=ProviderHttp::request('proxmox','POST',$this->config['url'].'/api2/json/access/ticket',$body,['Content-Type: application/x-www-form-urlencoded'],$this->config);}
            catch(ProviderFailure $error){if($error->httpStatus===401)throw new RuntimeException('Proxmox hat die Anmeldung abgelehnt. Bitte Benutzername einschließlich Realm (z. B. root@pam) und Passwort prüfen.');throw $error;}
            $data=$response['data']['data']??[];
            if(!is_array($data))throw new ProviderFailure('proxmox');
            if(!empty($data['NeedTFA']) || str_starts_with($data['ticket']??'','PVE:!tfa!'))throw new RuntimeException('Dieser Proxmox-Benutzer benötigt eine Zwei-Faktor-Anmeldung. Für dauerhafte Shop-Zugriffe bitte dessen API-Token verwenden.');
            $ticket=$data['ticket']??'';$csrf=$data['CSRFPreventionToken']??'';
            if(!is_string($ticket) || !str_starts_with($ticket,'PVE:') || !is_string($csrf) || $csrf==='' || preg_match('/[\r\n]/',$ticket.$csrf))throw new RuntimeException('Proxmox hat keine vollständige Anmeldung bestätigt.');
            $this->ticket=['cookie'=>$ticket,'csrf'=>$csrf,'expires'=>time()+6600];
        }
        $headers=['Cookie: PVEAuthCookie='.rawurlencode($this->ticket['cookie'])];
        if($method!=='GET')$headers[]='CSRFPreventionToken: '.$this->ticket['csrf'];
        return $headers;
    }
    public function __construct(private ?array $config=null){$this->config??=integration_config('proxmox');}
    public function call(string $method,string $path,?array $data=null): mixed {
        $body=$data===null?null:http_build_query($data,'','&',PHP_QUERY_RFC3986);
        $headers=($this->config['auth_mode']??'token')==='password'?$this->passwordHeaders($method):['Authorization: PVEAPIToken='.$this->config['token_id'].'='.$this->config['token_secret']];
        // A denied write is never retried: the server may already have executed it.
        $response=ProviderHttp::request('proxmox',$method,$this->config['url'].'/api2/json'.$path,$body,array_merge($headers,['Content-Type: application/x-www-form-urlencoded']),$this->config);
        return $response['data']['data']??null;
    }
    public function test(): array {
        $details=[];
        if(!empty($this->config['node']))$this->call('GET','/nodes/'.rawurlencode($this->config['node']).'/status');
        else{$nodes=(array)$this->call('GET','/nodes');$names=array_values(array_filter(array_column($nodes,'node'),fn($name)=>is_string($name)&&preg_match('/^[a-zA-Z0-9_.-]+$/D',$name)));$details[]='Nodes: '.($names?implode(', ',array_slice($names,0,20)):'keine sichtbar');}
        if(!empty($this->config['pool']))$this->call('GET','/pools/'.rawurlencode($this->config['pool']));
        else{$pools=(array)$this->call('GET','/pools');$names=array_values(array_filter(array_column($pools,'poolid'),fn($name)=>is_string($name)&&preg_match('/^[a-zA-Z0-9_.-]+$/D',$name)));$details[]='Ressourcenpools: '.($names?implode(', ',array_slice($names,0,20)):'noch keiner sichtbar');}
        return ['message'=>'Proxmox-Anmeldung erfolgreich. '.($details?implode('. ',$details).'. Für die Kundenserver bitte Ziel-Node und Shop-Ressourcenpool aus den API-Listen auswählen und speichern.':'Node und Shop-Ressourcenpool sind erreichbar.')];
    }
    public function targetOptions(): array {
        $result=['nodes'=>[],'pools'=>[],'errors'=>[]];
        foreach(['nodes'=>'node','pools'=>'poolid'] as $list=>$key){
            try{
                $rows=$this->call('GET','/'.$list);
                if(!is_array($rows) || !array_is_list($rows))throw new ProviderFailure('proxmox');
                $options=[];
                foreach($rows as $row){
                    $id=is_array($row)?($row[$key]??null):null;
                    if(!is_string($id) || !preg_match('/^[a-zA-Z0-9_.-]{1,100}$/D',$id))continue;
                    $option=['value'=>$id,'label'=>$id];
                    if($list==='nodes'){
                        $option['status']=in_array($row['status']??'', ['online','offline'],true)?$row['status']:'unknown';
                        $option['label'].=' ('.['online'=>'online','offline'=>'offline','unknown'=>'Status unbekannt'][$option['status']].')';
                    }
                    $options[$id]=$option;
                }
                uksort($options,'strnatcasecmp');$result[$list]=array_values($options);
            }catch(Throwable $e){
                // Never forward upstream replies, tickets or authentication headers.
                if(!($e instanceof ProviderFailure) || $e->httpStatus===401)throw $e;
                $result['errors'][$list]=$e instanceof ProviderFailure?$e->getMessage():'Die Proxmox-Liste konnte nicht geladen werden. Bitte die gespeicherten Zugangsdaten und die Verbindung prüfen.';
            }
        }
        return $result;
    }
    public function vm(string $node,int $vmid,string $type='qemu'): array { return (array)$this->call('GET','/nodes/'.rawurlencode($node).'/'.($type==='lxc'?'lxc':'qemu').'/'.$vmid.'/status/current'); }
    public function vmConfig(string $node,int $vmid,string $type='qemu'): array { return (array)$this->call('GET','/nodes/'.rawurlencode($node).'/'.($type==='lxc'?'lxc':'qemu').'/'.$vmid.'/config'); }
    public function control(string $node,int $vmid,string $action,string $type='qemu'): string {
        if(!in_array($action,['start','stop','shutdown','reboot'],true))throw new RuntimeException('Ungültige Serveraktion.');
        $task=$this->call('POST','/nodes/'.rawurlencode($node).'/'.($type==='lxc'?'lxc':'qemu').'/'.$vmid.'/status/'.$action);
        if(!is_string($task) || $task==='')throw new ProviderFailure('proxmox');
        return $task;
    }
    public function poolContains(string $node,int $vmid,string $type='qemu'): bool {
        if(empty($this->config['pool']))throw new RuntimeException('Bitte zuerst den Shop-Ressourcenpool in der Proxmox-Schnittstelle eintragen.');
        $pool=$this->call('GET','/pools/'.rawurlencode($this->config['pool']));
        foreach($pool['members']??[] as $m)if(($m['type']??'')===$type && (int)($m['vmid']??0)===$vmid && ($m['node']??'')===$node)return true;
        return false;
    }
}
class IspconfigProvider {
    private ?string $session=null;
    public function __construct(private ?array $config=null){$this->config??=integration_config('ispconfig');}
    private function request(string $method,array $params): mixed {
        // ISPConfig's built-in JSON endpoint takes the method as the query key.
        $reply=ProviderHttp::request('ispconfig','POST',$this->config['url'].'?'.rawurlencode($method),json_encode($params,JSON_THROW_ON_ERROR),['Content-Type: application/json'],$this->config);
        $data=$reply['data']??[];
        if(($data['code']??'')!=='ok')throw ProviderFailure::fromIspconfig(is_array($data)?$data:[]);
        return $data['response']??null;
    }
    public function call(string $method,array $params=[]): mixed {
        if(!$this->session){$this->session=(string)$this->request('login',['username'=>$this->config['username'],'password'=>$this->config['password'],'client_login'=>false]);if(!$this->session)throw new ProviderFailure('ispconfig');}
        return $this->request($method,['session_id'=>$this->session]+$params);
    }
    public function close(): void {if($this->session){try{$this->request('logout',['session_id'=>$this->session]);}catch(Throwable $e){}$this->session=null;}}
    public function __destruct(){$this->close();}
    public function test(): array {$server=$this->call('server_get',['server_id'=>(int)$this->config['server_id']]);if(!$server)throw new ProviderFailure('ispconfig');$dkim=$this->call('orgashop_mail_domain_dkim_ensure',['client_id'=>0,'primary_id'=>0,'domain'=>'','dry_run'=>true]);if(empty($dkim['available']))throw new RuntimeException('Die ISPConfig-Funktion zur DKIM-Einrichtung fehlt oder ihre Berechtigungen müssen geprüft werden.');return ['message'=>'ISPConfig-Anmeldung, Webserver-Zugriff und geschützte DKIM-Funktion erfolgreich geprüft.'];}
    public function website(int $id): array {$site=$this->call('sites_web_domain_get',['primary_id'=>$id]);if(!is_array($site) || empty($site['domain_id']))throw new ProviderFailure('ispconfig');return $site;}
    public function clientIdForGroup(int $group): int {$client=$this->call('client_get_by_groupid',['group_id'=>$group]);return (int)($client['client_id']??0);}
    public function client(int $id): array {return (array)$this->call('client_get',['client_id'=>$id]);}
    public function mailDomain(string $domain): ?array {
        $rows=$this->call('mail_domain_get_by_domain',['domain'=>$domain]);if(!$rows)return null;
        if(isset($rows['domain_id']))$rows=[$rows];
        if(!is_array($rows) || count($rows)!==1 || !is_array(reset($rows)))throw new RuntimeException('Die Maildomain ist bei ISPConfig nicht eindeutig zugeordnet.');
        $row=reset($rows);if(isset($row['domain']) && $row['domain']!==$domain)throw new RuntimeException('Die Maildomain passt nicht zur Website.');unset($row['dkim_private']);return $row;
    }
}
class PaypalProvider {
    private string $base;
    private ?string $token=null;
    public function __construct(private ?array $config=null){$this->config??=integration_config('paypal');if(($this->config['mode']??'sandbox')==='live' && !SMF\Core\ShopSettings::allowLive())throw new SecurityViolation('Live-Zahlungen sind im Testbetrieb gesperrt.',403);$this->base=($this->config['mode']??'sandbox')==='live'?'https://api-m.paypal.com':'https://api-m.sandbox.paypal.com';}
    public function mode(): string {return $this->config['mode']??'sandbox';}
    public function call(string $method,string $path,?array $payload=null,?string $requestId=null): array {
        if(!$this->token){$auth=ProviderHttp::request('paypal','POST',$this->base.'/v1/oauth2/token','grant_type=client_credentials',['Content-Type: application/x-www-form-urlencoded','Authorization: Basic '.base64_encode($this->config['client_id'].':'.$this->config['client_secret'])]);$this->token=$auth['data']['access_token']??null;if(!$this->token)throw new ProviderFailure('paypal');}
        $headers=['Authorization: Bearer '.$this->token,'Content-Type: application/json','Prefer: return=representation'];
        if($requestId)$headers[]='PayPal-Request-Id: '.$requestId;
        $body=$payload===null?null:json_encode($payload?:new stdClass(),JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        return ProviderHttp::request('paypal',$method,$this->base.$path,$body,$headers)['data']??[];
    }
    public function test(): array {
        $this->call('GET','/v1/catalogs/products?page_size=1');
        if(!empty($this->config['webhook_id'])){$hook=$this->call('GET','/v1/notifications/webhooks/'.rawurlencode($this->config['webhook_id']));if(($hook['url']??'')!==Config::FRONTPANEL_SITE_URL.'/api/paypal/webhook')throw new RuntimeException('Die PayPal-Webhook-URL muss auf '.Config::FRONTPANEL_SITE_URL.'/api/paypal/webhook zeigen.');}
        return ['message'=>'PayPal-API erreichbar ('.$this->mode().').'.(empty($this->config['webhook_id'])?' Für Zahlungen fehlt noch die Webhook-ID.':' Webhook-Ziel geprüft.')];
    }
    public function verifyWebhook(array $event,array $headers): bool {
        if(empty($this->config['webhook_id']))throw new RuntimeException('PayPal-Webhook ist noch nicht eingerichtet.');
        $payload=['webhook_id'=>$this->config['webhook_id'],'webhook_event'=>$event];
        foreach(['auth_algo'=>'PAYPAL-AUTH-ALGO','cert_url'=>'PAYPAL-CERT-URL','transmission_id'=>'PAYPAL-TRANSMISSION-ID','transmission_sig'=>'PAYPAL-TRANSMISSION-SIG','transmission_time'=>'PAYPAL-TRANSMISSION-TIME'] as $field=>$header){$value=$headers[$header]??'';if(!$value || strlen($value)>4096)return false;$payload[$field]=$value;}
        return ($this->call('POST','/v1/notifications/verify-webhook-signature',$payload)['verification_status']??'')==='SUCCESS';
    }
}
function provider_client(string $provider): object {return match($provider){'ovh'=>new OvhProvider(),'proxmox'=>new ProxmoxProvider(),'ispconfig'=>new IspconfigProvider(),'paypal'=>new PaypalProvider(),default=>throw new RuntimeException('Unbekannter Anbieter.')};}
