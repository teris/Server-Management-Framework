<?php
if(PHP_SAPI!=='cli' && realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){http_response_code(404);exit;}
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
    public function test(): array {
        $caps=['connection.read'];$servers=[];$functions=[];
        foreach(['server_id'=>'web','mail_server_id'=>'mail','db_server_id'=>'database'] as $field=>$role){
            $id=(int)($this->config[$field]??0);if($id<1)continue;
            try{$server=$this->call('server_get',['server_id'=>$id,'section'=>$role==='database'?'db':$role]);}catch(ProviderFailure $e){if($field==='server_id')throw $e;continue;}
            if(!is_array($server) || !$server)continue;
            $servers[$role]=$id;$caps[]=$role.'.read';
        }
        // Available functions and operator-declared user grants are separate from server-role reads.
        try{
            $available=$this->call('get_function_list');
            if(is_array($available))foreach($available as $key=>$value){$name=is_string($value)?$value:(is_string($key)?$key:'');if(preg_match('/^[a-z_]+$/D',$name))$functions[]=$name;}
        }catch(ProviderFailure $ignored){}
        foreach(['client.read'=>'client_get','client.create'=>'client_add','web.create'=>'sites_web_domain_add','mail.create'=>'mail_user_add','database.create'=>'sites_database_add','dkim.ensure'=>'orgashop_mail_domain_dkim_ensure'] as $cap=>$method)if(in_array($method,$functions,true) && in_array($method,preg_split('/[\s,]+/',(string)($this->config['allowed_functions']??''),-1,PREG_SPLIT_NO_EMPTY),true))$caps[]=$cap;
        // Detection never executes a possibly mutating optional extension.
        if(!isset($servers['web']))throw new RuntimeException('Der ausgewählte ISPConfig-Server ist kein erreichbarer Webserver.');
        return ['message'=>'ISPConfig-Grundzugang und Serverrollen geprüft. Fehlende Fachfunktionen sperren nur die betroffenen Angebote.','capabilities'=>$caps,'details'=>['servers'=>$servers,'functions'=>$functions,'permissions_source'=>'available_functions_and_operator_grants']];
    }
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
