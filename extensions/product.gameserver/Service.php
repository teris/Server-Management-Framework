<?php
namespace SMF\Modules\Gameserver;
use SMF\Core\Kernel;
use SMF\Core\JobContext;
use SMF\Core\ShopSettings;
use SMF\Core\ProviderDiagnostics;
use SMF\Core\ProviderOperations;
use RuntimeException;
use PDOException;
if(PHP_SAPI!=='cli' && realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){http_response_code(404);exit;}
final class Service {
    private function client(): object {Kernel::boot()->modules()->assertEnabled('product.gameserver');return Kernel::boot()->providers()->get('ogp');}
    private function live(): void {if(!ShopSettings::allowLive())throw new RuntimeException('Gameserver-Schreibzugriffe sind im Sandboxbetrieb gesperrt.');}
    private function write(string $connection,string $key,callable $operation): array {
        $this->live();return (new ProviderOperations(Kernel::boot()->database(),Kernel::boot()->audit()))->perform('ogp',$connection,$key,$operation);
    }
    private function account(int $customer,array &$job,array &$data): array {
        $connection=\integration_identity('ogp');$api=$this->client();$row=\one('SELECT * FROM smf_game_accounts WHERE connection_id=? AND customer_id=?',[$connection,$customer]);
        if(!$row){
            $marker=bin2hex(random_bytes(16));$username='smf'.substr(hash('sha256',ShopSettings::get('site_url').$connection),0,12).'c'.$customer;
            $email='smf-'.$marker.'@customers.invalid';$credentials=['password'=>'Smf!'.bin2hex(random_bytes(24))];
            \query('INSERT INTO smf_game_accounts (connection_id,customer_id,username,email,marker,credentials) VALUES (?,?,?,?,?,?)',[$connection,$customer,$username,$email,$marker,\secret_seal($credentials,'ogp-account:'.$connection.':'.$customer)]);
            $row=\one('SELECT * FROM smf_game_accounts WHERE connection_id=? AND customer_id=?',[$connection,$customer]);
        }
        if($row['state']==='creating'){
            $lookup=$api->response('user_admin/get',['email'=>$row['email']]);
            if($lookup->status===200)throw new RuntimeException('Unbestätigtes vorhandenes OGP-Konto muss über den Auftragsabgleich geprüft werden.');
            if($lookup->status!==317)throw new RuntimeException('OGP-Kontoprüfung wurde nicht bestätigt.');
            \save_job_phase($job,'ogp.user.creating',$data,'running');
            $credentials=\secret_open($row['credentials'],'ogp-account:'.$connection.':'.$customer);
            $result=$this->write($connection,'game:customer:'.$customer.':'.substr($connection,0,16),function()use($api,$row,$credentials){
                $api->call('POST','user_admin/create',['email'=>$row['email'],'name'=>$row['username'],'password'=>$credentials['password']]);
                $created=$api->call('GET','user_admin/get',['email'=>$row['email']]);
                if(!is_array($created) || ($created['users_login']??'')!==$row['username'] || (int)($created['user_id']??0)<1)throw new RuntimeException('Kontobestätigung fehlt.');return ['id'=>(string)$created['user_id']];
            });
            \query("UPDATE smf_game_accounts SET user_id=?,state='ready' WHERE connection_id=? AND customer_id=? AND state='creating'",[(int)$result['external_id'],$connection,$customer]);
            $row=\one('SELECT * FROM smf_game_accounts WHERE connection_id=? AND customer_id=?',[$connection,$customer]);
        }
        $actual=$api->call('GET','user_admin/get',['email'=>$row['email']]);
        if(!is_array($actual) || (int)($actual['user_id']??0)!==(int)$row['user_id'] || ($actual['users_login']??'')!==$row['username'])throw new RuntimeException('OGP-Kontoeigentümer hat sich geändert.');
        return $row;
    }
    private function reserve(array $order,array $profile,array $account): array {
        $existing=\one('SELECT * FROM smf_game_leases WHERE order_id=?',[$order['id']]);if($existing)return $existing;
        $api=$this->client();$connection=\integration_identity('ogp');
        $occupied=$api->call('GET','bridge/ports',['agent_id'=>$profile['agent_id']]);$used=[];foreach((array)$occupied as $a)if(($a['ip']??'')===$profile['ip'])$used[(int)$a['port']]=true;
        for($port=$profile['port_min'];$port+$profile['port_block']-1<=$profile['port_max'];$port+=$profile['port_block']){
            $ports=range($port,$port+$profile['port_block']-1);if(array_intersect($ports,array_keys($used)))continue;
            \db()->beginTransaction();try{
                if($lease=\one('SELECT * FROM smf_game_leases WHERE order_id=?',[$order['id']])){\db()->commit();return $lease;}
                foreach($ports as $number)\query('INSERT INTO smf_game_ports (connection_id,agent_id,ip,port,order_id) VALUES (?,?,?,?,?)',[$connection,$profile['agent_id'],$profile['ip'],$number,$order['id']]);
                $meta=['agent_id'=>(int)$profile['agent_id'],'home_cfg_id'=>(int)$profile['home_cfg_id'],'mod_cfg_id'=>(int)$profile['mod_cfg_id'],'mod_key'=>$profile['mod_key'],'ip'=>$profile['ip'],'port'=>$port,'user_id'=>(int)$account['user_id'],'username'=>$account['username'],'slots'=>(int)$profile['slots'],'connection_id'=>$connection];
                $marker='SMF-'.bin2hex(random_bytes(16));$meta['marker']=$marker;
                \query('INSERT INTO smf_game_leases (order_id,connection_id,marker,metadata,updated_at) VALUES (?,?,?,?,?)',[$order['id'],$connection,$marker,json_encode($meta,JSON_THROW_ON_ERROR),time()]);\db()->commit();return \one('SELECT * FROM smf_game_leases WHERE order_id=?',[$order['id']]);
            }catch(PDOException $error){if(\db()->inTransaction())\db()->rollBack();if(!str_starts_with((string)$error->getCode(),'23'))throw $error;}
            catch(\Throwable $error){if(\db()->inTransaction())\db()->rollBack();throw $error;}
        }
        throw new RuntimeException('Keine freien Gameserverports im freigegebenen Kontingent.');
    }
    private function state(array $metadata): array {
        $api=$this->client();if(!hash_equals(\integration_identity('ogp'),$metadata['connection_id']??''))throw new RuntimeException('OGP-Anschluss wurde geändert.');
        $keys=['home_id','marker','agent_id','user_id','home_cfg_id','mod_cfg_id','mod_key','ip','port','physical_id'];
        $actual=$api->call('GET','bridge/status',array_intersect_key($metadata,array_flip($keys)));
        if(!is_array($actual) || !preg_match('/^[a-f0-9]{64}$/D',$actual['physical_id']??'') || (isset($metadata['physical_id']) && !hash_equals($metadata['physical_id'],$actual['physical_id'])))throw new RuntimeException('Die physische Gameserveridentität muss geprüft werden.');
        return $actual;
    }
    public function provision(array $order,array &$job,array $profile,array $data): void {
        $this->live();$api=$this->client();ProviderDiagnostics::requireCapabilities('ogp',['home.create','game.install','installation.status','home.identity','home.delete','port.read','game.definition','home.configure']);
        if($order['provider']!=='ogp' || $order['paypal_mode']!=='live' || !\order_payment_allows_provisioning($order))throw new RuntimeException('Keine gültige Live-Zahlungsfreigabe.');
        $definition=$api->call('GET','bridge/definition',['home_cfg_id'=>$profile['home_cfg_id'],'mod_cfg_id'=>$profile['mod_cfg_id'],'agent_id'=>$profile['agent_id']]);
        if(!is_array($definition) || !hash_equals($profile['definition_sha256']??'',(string)($definition['definition_sha256']??'')) || ($definition['agent_system']??'unknown')!==$profile['system'])throw new RuntimeException('Die freigegebene Spieldefinition hat sich geändert.');
        if($api->call('GET','server/status',['remote_server_id'=>$profile['agent_id']])!=='online')throw new RuntimeException('Der ausgewählte OGP-Agent ist offline.');
        $account=$this->account((int)$order['customer_id'],$job,$data);$lease=$this->reserve($order,$profile,$account);$meta=json_decode($lease['metadata'],true,64,JSON_THROW_ON_ERROR);
        if(!hash_equals($lease['connection_id'],\integration_identity('ogp')) || in_array($lease['state'],['retained','deleted'],true))throw new RuntimeException('Gameserverauftrag ist nicht mehr bereitstellbar.');
        if(!$lease['home_id']){
            \save_job_phase($job,'ogp.home.creating',$data,'running');
            $secret=bin2hex(random_bytes(24));$params=['remote_server_id'=>$meta['agent_id'],'server_name'=>$meta['marker'],'home_cfg_id'=>$meta['home_cfg_id'],'mod_cfg_id'=>$meta['mod_cfg_id'],'ip'=>$meta['ip'],'port'=>$meta['port'],'control_password'=>$secret,'enable_ftp'=>'0','ftp_password'=>bin2hex(random_bytes(24)),'slots'=>$meta['slots'],'affinity'=>'NA','nice'=>0,'assign_to_username'=>$meta['username'],'custom_user_login_homepath'=>$meta['username']];
            $result=$this->write($meta['connection_id'],'game:order:'.$order['id'].':home',function()use($api,$params){$id=$api->call('POST','user_games/create',$params);if(!is_scalar($id) || !ctype_digit((string)$id) || (int)$id<1)throw new RuntimeException('Home-ID nicht bestätigt.');return ['id'=>(string)$id];});
            $meta['home_id']=(int)$result['external_id'];$actual=$this->state($meta);$meta['physical_id']=$actual['physical_id'];
            \query("UPDATE smf_game_leases SET home_id=?,metadata=?,state='home_created',updated_at=? WHERE order_id=?",[$meta['home_id'],json_encode($meta,JSON_THROW_ON_ERROR),time(),$order['id']]);$lease['state']='home_created';
            \save_job_phase($job,'ogp.home.created',$data);
        }
        $actual=$this->state($meta);
        if($lease['state']==='home_created' && empty($actual['namespace_owned'])){
            $result=$this->write($meta['connection_id'],'game:order:'.$order['id'].':configure',function()use($api,$meta,$profile){$configured=$api->call('POST','bridge/configure',$meta+['start_map'=>$profile['start_map']??'']);if(empty($configured['configured']) || empty($configured['namespace_owned']) || !preg_match('/^[a-f0-9]{64}$/D',$configured['physical_id']??''))throw new RuntimeException('Spielverzeichnis nicht bestätigt.');return ['id'=>$configured['physical_id']];});
            $meta['physical_id']=$result['external_id'];\query('UPDATE smf_game_leases SET metadata=? WHERE order_id=?',[json_encode($meta,JSON_THROW_ON_ERROR),$order['id']]);$actual=$this->state($meta);
        }
        if($lease['state']==='home_created'){
            \save_job_phase($job,'ogp.install.starting',$data,'running');
            $this->write($meta['connection_id'],'game:order:'.$order['id'].':install',function()use($api,$meta,$profile){$api->call('POST','gamemanager/update',['ip'=>$meta['ip'],'port'=>$meta['port'],'mod_key'=>$meta['mod_key'],'type'=>$profile['install_method'],'manual_url'=>'']);return ['id'=>'home:'.$meta['home_id']];});
            \query("UPDATE smf_game_leases SET state='installing',updated_at=? WHERE order_id=?",[time(),$order['id']]);\save_job_phase($job,'ogp.installing',$data);return;
        }
        if($lease['state']==='installing'){
            if($actual['installing']){\save_job_phase($job,'ogp.installing',$data);return;}
            if(!$actual['executable_present']){
                if(time()-(int)$lease['updated_at']<120){\save_job_phase($job,'ogp.installing',$data);return;}
                throw new RuntimeException('Spielinstallation ist noch nicht erfolgreich bestätigt.');
            }
            // An executable alone is insufficient: require an observed running game process.
            if(!$actual['running']){
                \save_job_phase($job,'ogp.starting',$data,'running');
                $this->write($meta['connection_id'],'game:order:'.$order['id'].':start',function()use($api,$meta){$api->call('POST','gamemanager/start',['ip'=>$meta['ip'],'port'=>$meta['port'],'mod_key'=>$meta['mod_key']]);return ['id'=>'home:'.$meta['home_id']];});
                $actual=$this->state($meta);if(!$actual['running'])throw new RuntimeException('Spielprozess wurde nicht als laufend bestätigt.');
            }
            \finish_provisioning($order,$job,'gamehome/'.$meta['home_id'],$meta,$meta['ip'].':'.$meta['port']);

        }
    }
    public function inspect(int $service,int $customer,bool $write=false): array {
        if(PHP_SAPI!=='cli')\HttpSecurity::rateLimit('gameserver-status',(string)$customer,90,60);
        $this->client();$bound=\bound_service($service,$customer,$write);if($bound['provider']!=='ogp')throw new RuntimeException('Gameserver nicht gefunden.');
        $lease=\one('SELECT * FROM smf_game_leases WHERE order_id=? AND service_id=?',[$bound['meta']['order_id']??0,$service]);
        if(!$lease || !in_array($lease['state'],['active','suspended','retained'],true) || !hash_equals($lease['marker'],$bound['meta']['marker']??''))throw new RuntimeException('Gameserverbindung muss geprüft werden.');
        if($write){$order=\one('SELECT * FROM shop_orders WHERE id=?',[$lease['order_id']]);$contract=\one('SELECT * FROM shop_contracts WHERE order_id=?',[$lease['order_id']]);if(!$order || !\order_payment_allows_provisioning($order) || ($contract && (int)$contract['paid_until']>0 && \shop_now()>(int)$contract['paid_until']+3*86400))throw new RuntimeException('Vertrag oder Zahlung gibt diese Aktion nicht frei.');}
        $bound['game_state']=$this->state($bound['meta']);return $bound;
    }
    public function queueControl(int $service,int $customer,string $action,string $key,string $command=''): string {
        if(PHP_SAPI!=='cli')\HttpSecurity::rateLimit('gameserver-control',(string)$customer,30,900);
        if($action!=='rcon')$command='';
        $this->live();$this->inspect($service,$customer,true);
        if(!in_array($action,['start','stop','restart','rcon'],true) || !preg_match('/^[a-f0-9]{32}$/D',$key))throw new RuntimeException('Ungültige Gameserveraktion.');
        if($action==='rcon' && !preg_match('/^(?:status|players|list|say [a-zA-Z0-9 .!?,_-]{1,180})$/D',$command))throw new RuntimeException('Nur Status, Spielerliste und eine kurze Chatnachricht sind freigegeben.');
        return Kernel::boot()->jobs()->enqueue('product.gameserver','product.gameserver','game-control:'.$key,['operation'=>'control','service_id'=>$service,'customer_id'=>$customer,'action'=>$action,'command'=>$command],'live');
    }
    public function control(array $payload,JobContext $context): void {
        \resource_mutex((int)$payload['service_id'],fn()=>$this->controlLocked($payload,$context));
    }
    private function controlLocked(array $payload,JobContext $context): void {
        $this->live();$service=$this->inspect((int)$payload['service_id'],(int)$payload['customer_id'],true);$meta=$service['meta'];
        $action=$payload['action'];if(!in_array($action,['start','stop','restart','rcon'],true))throw new RuntimeException('Invalid game action');
        $state=$service['game_state'];if(($action==='start' && $state['running']) || ($action==='stop' && !$state['running']))return;
        $params=['ip'=>$meta['ip'],'port'=>$meta['port'],'mod_key'=>$meta['mod_key']];
        if($action==='rcon'){if(!preg_match('/^(?:status|players|list|say [a-zA-Z0-9 .!?,_-]{1,180})$/D',$payload['command']??''))throw new RuntimeException('RCON-Befehl nicht freigegeben.');$params['command']=$payload['command'];}
        $context->beforeWrite($payload);$reply=$this->client()->call('POST','gamemanager/'.$action,$params);
        if($action==='rcon'){
            $reply=mb_substr(is_string($reply)?$reply:json_encode($reply,JSON_THROW_ON_ERROR),0,8192);
            foreach(['token','bridge_key'] as $key){$secret=\integration_config('ogp')[$key]??'';if($secret!=='')$reply=str_replace($secret,'[redacted]',$reply);}
            $payload['reply']=$reply;
        }
        $context->checkpoint('confirmed',$payload);
    }
    public function log(int $service,int $customer): string {
        if(PHP_SAPI!=='cli')\HttpSecurity::rateLimit('gameserver-log',(string)$customer,20,60);
        $bound=$this->inspect($service,$customer);$result=$this->client()->call('GET','bridge/log',$bound['meta']);return mb_substr((string)($result['log']??''),0,32768);
    }
    public function operations(int $service,int $customer): array {
        \bound_service($service,$customer);$result=[];
        foreach(\all("SELECT id,state,payload FROM smf_jobs WHERE module_id='product.gameserver' AND type='product.gameserver' ORDER BY created_at DESC LIMIT 100") as $row){
            $payload=json_decode($row['payload'],true,64,JSON_THROW_ON_ERROR);
            if((int)($payload['service_id']??0)===$service && (int)($payload['customer_id']??0)===$customer)$result[]=['state'=>$row['state'],'action'=>$payload['action']??'','reply'=>$row['state']==='done'?($payload['reply']??''):''];
            if(count($result)>=10)break;
        }
        return $result;
    }
    public function end(array $order): bool {
        $lease=\one('SELECT service_id FROM smf_game_leases WHERE order_id=?',[$order['id']]);
        return !empty($lease['service_id'])?\resource_mutex((int)$lease['service_id'],fn()=>$this->endLocked($order)):$this->endLocked($order);
    }
    private function endLocked(array $order): bool {
        $this->live();$lease=\one('SELECT * FROM smf_game_leases WHERE order_id=?',[$order['id']]);if(!$lease || $lease['state']==='deleted')return true;
        $meta=json_decode($lease['metadata'],true,64,JSON_THROW_ON_ERROR);$actual=$this->state($meta);$api=$this->client();
        if($actual['running']){$this->write($meta['connection_id'],'game:order:'.$order['id'].':end-stop',function()use($api,$meta){$api->call('POST','gamemanager/stop',['ip'=>$meta['ip'],'port'=>$meta['port'],'mod_key'=>$meta['mod_key']]);return ['id'=>'home:'.$meta['home_id']];});if($this->state($meta)['running'])return false;}
        if($lease['state']!=='retained'){
            $policy=json_decode($order['profile'],true,64,JSON_THROW_ON_ERROR);$retire=time()+86400*(int)($policy['retention_days']??7);
            $this->write($meta['connection_id'],'game:order:'.$order['id'].':expire',function()use($api,$meta){$api->call('POST','user_games/set_expiration',['home_id'=>$meta['home_id'],'timestamp'=>time()-60]);return ['id'=>'home:'.$meta['home_id']];});
            \query("UPDATE smf_game_leases SET state='retained',retire_after=?,updated_at=? WHERE order_id=?",[$retire,time(),$order['id']]);if($lease['service_id'])\query("UPDATE shop_services SET status='cancelled' WHERE id=?",[$lease['service_id']]);return false;
        }
        if(time()<(int)$lease['retire_after'])return false;
        $this->write($meta['connection_id'],'game:order:'.$order['id'].':delete',function()use($api,$meta){$result=$api->call('POST','bridge/delete',$meta);if(empty($result['deleted']))throw new RuntimeException('Löschung nicht bestätigt.');return ['id'=>'home:'.$meta['home_id']];});
        \db()->beginTransaction();try{\query('DELETE FROM smf_game_ports WHERE order_id=?',[$order['id']]);if($lease['service_id'])\SMF\Core\ShopResourceBindings::revoke((int)$lease['service_id']);\query("UPDATE smf_game_leases SET state='deleted',updated_at=? WHERE order_id=?",[time(),$order['id']]);\db()->commit();}catch(\Throwable $e){if(\db()->inTransaction())\db()->rollBack();throw $e;}
        return true;
    }
    /** Explicit operator reconciliation uses the persisted random marker, never a customer-supplied home ID. */
    public function reconcile(int $orderId): void {
        if(PHP_SAPI!=='cli')\Identity::requireIdentity('backend',true);
        $order=\one("SELECT * FROM shop_orders WHERE id=? AND provider='ogp'",[$orderId]);$job=\one('SELECT * FROM shop_jobs WHERE order_id=?',[$orderId]);
        if(!$order || !$job || (int)$job['locked_until']>=time())throw new RuntimeException('Auftrag ist nicht zum Abgleich freigegeben.');
        $profile=json_decode($order['profile'],true,64,JSON_THROW_ON_ERROR);$connection=\integration_identity('ogp');if(!hash_equals($profile['connection_id']??'',$connection))throw new RuntimeException('Anschluss muss vor dem Abgleich geprüft werden.');
        $api=$this->client();$account=\one('SELECT * FROM smf_game_accounts WHERE connection_id=? AND customer_id=?',[$connection,$order['customer_id']]);
        if($account && $account['state']==='creating'){
            $remote=$api->call('GET','user_admin/get',['email'=>$account['email']]);if(!is_array($remote) || ($remote['users_login']??'')!==$account['username'] || ($remote['users_email']??$account['email'])!==$account['email'] || (int)($remote['user_id']??0)<1)throw new RuntimeException('Angelegtes OGP-Konto nicht eindeutig bestätigt.');
            $operation='game:customer:'.$order['customer_id'].':'.substr($connection,0,16);
            $q=\query("UPDATE smf_provider_operations SET state='confirmed',external_id=?,updated_at=? WHERE provider='ogp' AND connection_id=? AND operation_key=? AND state IN ('needs_review','writing')",[(string)$remote['user_id'],time(),$connection,$operation]);if($q->rowCount()!==1)throw new RuntimeException('Keine passende Konto-Schreibabsicht vorhanden.');
            \query("UPDATE smf_game_accounts SET state='ready',user_id=? WHERE connection_id=? AND customer_id=?",[$remote['user_id'],$connection,$order['customer_id']]);
        }
        $lease=\one('SELECT * FROM smf_game_leases WHERE order_id=?',[$orderId]);
        if($lease){
            $meta=json_decode($lease['metadata'],true,64,JSON_THROW_ON_ERROR);
            if(!$lease['home_id']){
                $matches=[];foreach($api->call('GET','user_games/list_servers') as $home)if(($home['home_name']??'')===$lease['marker'] && (int)($home['remote_server_id']??0)===(int)$meta['agent_id'] && (int)($home['user_id_main']??0)===(int)$meta['user_id'] && (int)($home['home_cfg_id']??0)===(int)$meta['home_cfg_id'])$matches[]=$home;
                if(count($matches)!==1)throw new RuntimeException('Keine eindeutige eigene Homeanlage gefunden.');$meta['home_id']=(int)$matches[0]['home_id'];$meta['physical_id']=$this->state($meta)['physical_id'];
                $q=\query("UPDATE smf_provider_operations SET state='confirmed',external_id=?,updated_at=? WHERE provider='ogp' AND connection_id=? AND operation_key=? AND state IN ('needs_review','writing','confirmed')",[(string)$meta['home_id'],time(),$connection,'game:order:'.$orderId.':home']);if($q->rowCount()!==1)throw new RuntimeException('Keine passende Home-Schreibabsicht vorhanden.');
                \query("UPDATE smf_game_leases SET home_id=?,metadata=?,state='home_created',updated_at=? WHERE order_id=?",[$meta['home_id'],json_encode($meta,JSON_THROW_ON_ERROR),time(),$orderId]);
            }elseif($lease['state']==='home_created'){
                $probe=$meta;unset($probe['physical_id']);$observed=$this->state($probe);
                if(!hash_equals($meta['physical_id']??'',$observed['physical_id']) && !empty($observed['namespace_owned']) && !empty($observed['startup_configured'])){
                    $q=\query("UPDATE smf_provider_operations SET state='confirmed',external_id=?,updated_at=? WHERE provider='ogp' AND connection_id=? AND operation_key=? AND state IN ('needs_review','writing')",[$observed['physical_id'],time(),$connection,'game:order:'.$orderId.':configure']);
                    if($q->rowCount()!==1)throw new RuntimeException('Keine passende Verzeichnis-Schreibabsicht vorhanden.');
                    $meta['physical_id']=$observed['physical_id'];\query('UPDATE smf_game_leases SET metadata=? WHERE order_id=?',[json_encode($meta,JSON_THROW_ON_ERROR),$orderId]);
                }
                $actual=$this->state($meta);
                if($actual['installing'] || $actual['executable_present']){
                    $q=\query("UPDATE smf_provider_operations SET state='confirmed',external_id=?,updated_at=? WHERE provider='ogp' AND connection_id=? AND operation_key=? AND state IN ('needs_review','writing')",['home:'.$meta['home_id'],time(),$connection,'game:order:'.$orderId.':install']);
                    if($q->rowCount()===1)\query("UPDATE smf_game_leases SET state='installing',updated_at=? WHERE order_id=?",[time(),$orderId]);
                }
            }
        }
        if($lease && $lease['home_id'] && $lease['state']==='installing'){
            $actual=$this->state(json_decode($lease['metadata'],true,64,JSON_THROW_ON_ERROR));
            if($actual['running'])\query("UPDATE smf_provider_operations SET state='confirmed',external_id=?,updated_at=? WHERE provider='ogp' AND connection_id=? AND operation_key=? AND state IN ('needs_review','writing')",['home:'.$lease['home_id'],time(),$connection,'game:order:'.$orderId.':start']);
        }
        \query("UPDATE shop_jobs SET state='waiting',locked_until=0,lock_token=NULL,last_error=NULL WHERE order_id=? AND state='needs_action'",[$orderId]);
        Kernel::boot()->audit()->record('gameserver.reconciled',(string)$orderId,'ok');
    }
    public function tick(): void {
        if(!ShopSettings::allowLive())return;
        foreach(\all("SELECT order_id,service_id FROM smf_game_leases WHERE state IN ('active','suspended','retained') ORDER BY order_id LIMIT 50")as$row){
            try{if($row['service_id'])\resource_mutex((int)$row['service_id'],fn()=>$this->tickLease((int)$row['order_id']));}
            catch(\Throwable$error){Kernel::boot()->audit()->record('gameserver.lifecycle.review',(string)$row['order_id'],'needs_review',['error_code'=>'PROVIDER_UNCONFIRMED']);}
        }
    }
    private function tickLease(int $id): void {
        $lease=\one('SELECT * FROM smf_game_leases WHERE order_id=?',[$id]);
        if(!$lease || !in_array($lease['state'],['active','suspended','retained'],true))return;
        $order=\one('SELECT * FROM shop_orders WHERE id=?',[$id]);$contract=\one('SELECT * FROM shop_contracts WHERE order_id=?',[$id]);
        if(\contract_closed($contract)){$this->endLocked($order);return;}
        if(!$contract)return;
        $meta=json_decode($lease['metadata'],true,64,JSON_THROW_ON_ERROR);$expired=!\order_payment_allows_provisioning($order) || ((int)$contract['paid_until']>0 && \shop_now()>(int)$contract['paid_until']+3*86400);
        $generation=(int)$lease['generation'];$operation='game:order:'.$id.':generation:'.$generation;
        if(!$expired && $lease['state']==='active'){
            $until=(int)$contract['paid_until']+3*86400;if((int)$contract['cancel_at']>0)$until=min($until,(int)$contract['cancel_at']);
            if(($meta['expiry_until']??0)!==$until){
                $this->state($meta);$api=$this->client();$this->write($meta['connection_id'],$operation.':renew-expire:'.$until,function()use($api,$meta,$until){$api->call('POST','user_games/set_expiration',['home_id'=>$meta['home_id'],'timestamp'=>$until]);return ['id'=>'home:'.$meta['home_id']];});
                $meta['expiry_until']=$until;\query('UPDATE smf_game_leases SET metadata=?,updated_at=? WHERE order_id=? AND generation=?',[json_encode($meta,JSON_THROW_ON_ERROR),time(),$id,$generation]);
            }
        }
        if($expired && $lease['state']==='active'){
            $api=$this->client();$actual=$this->state($meta);
            if($actual['running'])$this->write($meta['connection_id'],$operation.':suspend',function()use($api,$meta){$api->call('POST','gamemanager/stop',['ip'=>$meta['ip'],'port'=>$meta['port'],'mod_key'=>$meta['mod_key']]);return ['id'=>'home:'.$meta['home_id']];});
            $this->write($meta['connection_id'],$operation.':suspend-expire',function()use($api,$meta){$api->call('POST','user_games/set_expiration',['home_id'=>$meta['home_id'],'timestamp'=>time()-60]);return ['id'=>'home:'.$meta['home_id']];});
            $changed=\query("UPDATE shop_services SET status='suspended' WHERE id=? AND status='active'",[$lease['service_id']]);
            if($changed->rowCount()===1)\query("UPDATE smf_game_leases SET state='suspended',generation=generation+1,updated_at=? WHERE order_id=? AND generation=?",[time(),$id,$generation]);
        }elseif(!$expired && $lease['state']==='suspended'){
            $this->state($meta);$api=$this->client();$until=(int)$contract['paid_until']+3*86400;
            $this->write($meta['connection_id'],$operation.':unsuspend-expire',function()use($api,$meta,$until){$api->call('POST','user_games/set_expiration',['home_id'=>$meta['home_id'],'timestamp'=>$until]);return ['id'=>'home:'.$meta['home_id']];});
            $changed=\query("UPDATE shop_services SET status='active' WHERE id=? AND status='suspended' AND EXISTS (SELECT 1 FROM shop_orders o JOIN shop_contracts c ON c.order_id=o.id WHERE o.id=? AND o.payment_status IN ('paid','test_paid') AND o.fulfillment_status NOT IN ('cancelled','ended') AND c.state NOT IN ('ended','unpaid_cancelled') AND (c.cancel_at=0 OR c.cancel_at>?))",[$lease['service_id'],$id,\shop_now()]);
            if($changed->rowCount()===1)\query("UPDATE smf_game_leases SET state='active',generation=generation+1,updated_at=? WHERE order_id=? AND generation=?",[time(),$id,$generation]);
        }
    }
}
