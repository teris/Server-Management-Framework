<?php
// Compatibility class names live in the independently packaged provider modules.
foreach(['ovh','proxmox','ispconfig'] as $provider){
    $file=dirname(__DIR__,2).'/provider.'.$provider.'/Adapter.php';
    if(is_file($file))require_once $file;
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
function provider_client(string $provider): object {return match($provider){'ovh'=>new OvhProvider(),'proxmox'=>new ProxmoxProvider(),'ispconfig'=>new IspconfigProvider(),'paypal'=>new PaypalProvider(),default=>SMF\Core\Kernel::boot()->providers()->get($provider)};}
