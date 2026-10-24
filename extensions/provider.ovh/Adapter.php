<?php
if(PHP_SAPI!=='cli' && realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){http_response_code(404);exit;}
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
        $caps=['connection.read','zone.read'];
        try{$this->call('GET','/domain');$caps[]='domain.read';}catch(ProviderFailure $ignored){}
        try{$this->call('GET','/vps');$caps[]='vps.read';}catch(ProviderFailure $ignored){}
        try{
            $credential=$this->call('GET','/auth/currentCredential');$rules=$credential['rules']??[];
            $covers=static function(string $method,string $path)use($rules):bool {foreach($rules as$rule){$pattern=preg_quote((string)($rule['path']??''),'~');$pattern=str_replace('\\*','.*',$pattern);if(($rule['method']??'')===$method && preg_match('~^'.$pattern.'$~D',$path))return true;}return false;};
            if($covers('POST','/domain/zone/example.invalid/record')&&$covers('PUT','/domain/zone/example.invalid/record/1')&&$covers('DELETE','/domain/zone/example.invalid/record/1'))$caps[]='zone.write';
            if($covers('POST','/order/cart')&&$covers('POST','/order/cart/example/checkout'))$caps[]='domain.order';
        }catch(ProviderFailure $ignored){}
        return ['capabilities'=>$caps,'details'=>['api_contract'=>'ovh-rest-1.0','permissions_source'=>'auth/currentCredential'], 'message'=>'OVH-Zugriff erfolgreich. '.count((array)$zones).' DNS-Zonen erreichbar.'];
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
