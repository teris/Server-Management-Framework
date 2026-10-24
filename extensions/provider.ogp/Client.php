<?php
namespace SMF\Modules\Ogp;
use SMF\Core\ProviderClient;
use SMF\Core\ProviderAccounts;
use SMF\Core\Kernel;
use RuntimeException;
if(PHP_SAPI!=='cli' && realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){http_response_code(404);exit;}
final class Failure extends RuntimeException {
    public function __construct(public readonly string $category,public readonly int $apiStatus=0) { parent::__construct('OGP: '.$category); }
}
final readonly class Response {
    public function __construct(public int $status,public mixed $data) {}
}
/** OGP Website ogp_api.php body-auth contract. Agent management is never a game-home operation. */
final class Client implements ProviderClient {
    private array $config;
    private string $binding;
    private const READS=['token/test','server/list','server/status','server/list_ips','user_games/list_games','user_games/list_servers','user_admin/list','user_admin/get','user_admin/list_assigned','litefm/list','litefm/get','setting/get','bridge/capabilities','bridge/status','bridge/ports','bridge/log','bridge/definition'];
    private const WRITES=['user_admin/create','user_admin/assign','user_admin/remove_assign','user_games/create','user_games/set_expiration','gamemanager/start','gamemanager/stop','gamemanager/restart','gamemanager/update','gamemanager/rcon','litefm/save','bridge/delete','bridge/configure'];
    public function __construct(?array $config=null) {
        $this->config=$config??\integration_config('ogp');
        $this->config['url']=\checked_api_url($this->config['url']??'');
        if(!preg_match('/^[a-f0-9]{64}$/Di',$this->config['token']??''))throw new Failure('INVALID_TOKEN');
        $this->binding=$config===null?\integration_identity('ogp'):\integration_identity('ogp',$this->config);
    }
    public function connectionId(): string {return $this->binding;}
    public function response(string $action,array $parameters=[]): Response {
        if(!in_array($action,[...self::READS,...self::WRITES],true))throw new Failure('UNSUPPORTED_OPERATION');
        if(!ProviderAccounts::enabled('ogp') || !hash_equals($this->binding,\integration_identity('ogp')))throw new Failure('CONNECTION_CHANGED_OR_DISABLED');
        Kernel::boot()->modules()->assertEnabled('provider.ogp');
        unset($parameters['token'],$parameters['bridge_key']);
        $bridge=str_starts_with($action,'bridge/');
        if($bridge && empty($this->config['bridge_key']))throw new Failure('BRIDGE_NOT_CONFIGURED');
        if($bridge)$parameters['bridge_key']=$this->config['bridge_key'];
        $url=$this->config['url'].($bridge?'/smf_ogp_bridge.php?':'/ogp_api.php?').$action;
        $body=http_build_query(['token'=>$this->config['token']]+$parameters,'','&',PHP_QUERY_RFC3986);
        try{$reply=\ProviderHttp::request('ogp','POST',$url,$body,['Content-Type: application/x-www-form-urlencoded'],$this->config);}
        catch(\Throwable $error){throw new Failure('TRANSPORT_UNCONFIRMED');}
        $data=$reply['data']??null;
        if(!is_array($data) || !isset($data['status']) || !ctype_digit((string)$data['status']) || !array_key_exists('message',$data))throw new Failure('INVALID_RESPONSE');
        return new Response((int)$data['status'],$data['message']);
    }
    public function call(string $method,string $path,mixed $payload=null): mixed {
        if(!in_array(strtoupper($method),['GET','POST'],true) || ($payload!==null && !is_array($payload)))throw new Failure('INVALID_REQUEST');
        $action=ltrim($path,'/');if(strtoupper($method)==='GET' && !in_array($action,self::READS,true))throw new Failure('WRITE_REQUIRES_POST');
        $reply=$this->response($action,$payload??[]);
        if($reply->status!==200)throw new Failure(match($reply->status){300,301,400=>'AUTHENTICATION_FAILED',350=>'ADMIN_PERMISSION_REQUIRED',401=>'HOST_NOT_AUTHORIZED',310=>'AGENT_OFFLINE',312=>'PORT_IN_USE',317,319,323,315=>'NOT_FOUND',default=>'OPERATION_REJECTED'},$reply->status);
        return $reply->data;
    }
    public function test(): array {
        if($this->call('POST','token/test')!=='admin')throw new Failure('ADMIN_PERMISSION_REQUIRED');
        $agents=$this->call('GET','server/list');if(!is_array($agents))throw new Failure('INVALID_AGENT_LIST');
        $capabilities=['connection.read','agent.read','game.read','home.read','user.read','user.create','home.create','home.expire','game.install','game.control','game.files','game.rcon'];
        $bridge=false;
        if(!empty($this->config['bridge_key'])){
            $result=$this->call('GET','bridge/capabilities');
            if(!is_array($result) || ($result['protocol']??'')!=='smf-ogp-bridge/1')throw new Failure('UNSUPPORTED_BRIDGE');
            foreach(['installation.status','home.delete','home.identity','home.log','port.read','game.definition','home.configure'] as $capability)if(in_array($capability,$result['capabilities']??[],true))$capabilities[]=$capability;
            $bridge=true;
        }
        return ['message'=>'OGP-Administratorzugang geprüft; '.count($agents).' Agents sichtbar. Agentbereitschaft und Spielinstallation werden separat geprüft.','capabilities'=>$capabilities,'details'=>['api_contract'=>'ogp_api.php-body-v1','admin_role_verified'=>true,'agents'=>count($agents),'bridge'=>$bridge]];
    }
}
