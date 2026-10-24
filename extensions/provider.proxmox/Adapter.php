<?php
if(PHP_SAPI!=='cli' && realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){http_response_code(404);exit;}
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
        $caps=['connection.read','node.read','pool.read'];
        try{$this->call('GET','/cluster/resources?type=vm');$caps[]='vm.read';}catch(ProviderFailure $ignored){}
        try{
            $permissions=$this->call('GET','/access/permissions');$root=is_array($permissions)?($permissions['/']??[]):[];
            if(!array_diff(['VM.Allocate','VM.Clone','Datastore.AllocateSpace'],array_keys(array_filter((array)$root))))$caps[]='vm.create';
            if(!empty($root['VM.PowerMgmt']))$caps[]='vm.control';
        }catch(ProviderFailure $ignored){}
        return ['capabilities'=>$caps,'details'=>['api_contract'=>'pve-api2-json','permissions_source'=>'access/permissions'], 'message'=>'Proxmox-Anmeldung erfolgreich. '.($details?implode('. ',$details).'. Für die Kundenserver bitte Ziel-Node und Shop-Ressourcenpool aus den API-Listen auswählen und speichern.':'Node und Shop-Ressourcenpool sind erreichbar.')];
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
