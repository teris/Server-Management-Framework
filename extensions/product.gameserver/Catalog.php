<?php
namespace SMF\Modules\Gameserver;
use SMF\Core\Kernel;
use SMF\Core\ProductRegistry;
use SMF\Core\ProviderDiagnostics;
use RuntimeException;
if(PHP_SAPI!=='cli' && realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){http_response_code(404);exit;}
final class Catalog {
    private static function number(array $input,string $key,int $min,int $max): int {
        $value=$input[$key]??null;
        if(!is_scalar($value) || !preg_match('/^\d{1,9}$/D',(string)$value) || (int)$value<$min || (int)$value>$max)throw new RuntimeException('Ungültiger Gameserverwert: '.$key);
        return (int)$value;
    }
    public static function offer(int $product): ?array {
        Kernel::boot()->modules()->assertEnabled('product.gameserver');
        $row=\one('SELECT * FROM smf_game_offers WHERE product_id=?',[$product]);if(!$row)return null;$row['policy']=json_decode($row['policy'],true,64,JSON_THROW_ON_ERROR);return $row;
    }
    /** Operator choices must agree with live, read-only OGP inventories. */
    public static function save(int $product,array $input,bool $enabled): void {
        Kernel::boot()->modules()->assertEnabled('product.gameserver');
        $p=\one('SELECT * FROM shop_products WHERE id=?',[$product]);if(!$p || $p['category']!=='gameserver')throw new RuntimeException('Ein Gameserverprodukt auswählen.');
        $policy=[];
        foreach(['agent_id'=>[1,2147483647],'home_cfg_id'=>[1,2147483647],'mod_cfg_id'=>[1,2147483647],'slot_min'=>[1,1000],'slot_max'=>[1,1000],'slot_step'=>[1,1000],'port_min'=>[1024,65000],'port_max'=>[1024,65000],'port_block'=>[1,100],'base_net_cents'=>[0,100000000],'slot_net_cents'=>[0,1000000],'retention_days'=>[0,365]] as $key=>[$min,$max])$policy[$key]=self::number($input,$key,$min,$max);
        foreach(['memory_mb','disk_mb','cpu_limit'] as $key)if((int)($input[$key]??0)!==0)throw new RuntimeException('Die OGP-API kann diese RAM-/Datenträger-/CPU-Grenze nicht durchsetzen. Dieses Limit darf nicht verkauft werden.');
        if($policy['slot_min']>$policy['slot_max'] || $policy['port_min']>$policy['port_max'] || $policy['port_min']+$policy['port_block']-1>$policy['port_max'])throw new RuntimeException('Ungültiger Slot- oder Portbereich.');
        $policy['system']=(string)($input['system']??'linux');$policy['architecture']=(string)($input['architecture']??'64');
        if(!in_array($policy['system'],['linux','windows'],true) || !in_array($policy['architecture'],['32','64'],true))throw new RuntimeException('Ungültige Spielplattform.');
        $policy['ip']=(string)($input['ip']??'');if(!filter_var($policy['ip'],FILTER_VALIDATE_IP,FILTER_FLAG_IPV4))throw new RuntimeException('OGP benötigt eine freigegebene IPv4-Adresse.');
        $policy['location']=trim((string)($input['location']??''));$policy['contract_text']=trim((string)($input['contract_text']??''));
        if(mb_strlen($policy['location'])<1 || mb_strlen($policy['location'])>100 || mb_strlen($policy['contract_text'])<10 || mb_strlen($policy['contract_text'])>2000)throw new RuntimeException('Standort und Leistungs-/Vertragsbeschreibung ergänzen.');
        $policy['start_map']=trim((string)($input['start_map']??''));if(!preg_match('/^[a-zA-Z0-9_.-]{0,80}$/D',$policy['start_map']) || str_contains($policy['start_map'],'..'))throw new RuntimeException('Ungültige Startkarte.');
        $policy['install_method']=(string)($input['install_method']??'steam');if(!in_array($policy['install_method'],['steam','rsync'],true))throw new RuntimeException('Nur die festgelegten Steam-/Rsync-Spieldefinitionen sind zugelassen.');
        if($enabled && $policy['system']!=='linux')throw new RuntimeException('Diese Agentplattform benötigt vor Direktverkauf eine eigene Spielabnahme. Angebot auf Anfrage belassen.');
        $client=Kernel::boot()->providers()->get('ogp');
        $agents=$client->call('GET','server/list');$found=false;foreach($agents as $agent)if((int)($agent['remote_server_id']??0)===$policy['agent_id'])$found=true;if(!$found)throw new RuntimeException('Agent nicht freigegeben oder nicht vorhanden.');
        $ips=$client->call('GET','server/list_ips',['remote_server_id'=>$policy['agent_id']]);$found=false;foreach((array)$ips as $ip)if(($ip['ip']??'')===$policy['ip'])$found=true;if(!$found)throw new RuntimeException('IP gehört nicht zum gewählten Agent.');
        $games=$client->call('GET','user_games/list_games',['system'=>$policy['system'],'architecture'=>$policy['architecture']]);$game=null;$mod=null;
        foreach((array)$games as $row)if((int)($row['home_cfg_id']??0)===$policy['home_cfg_id']){$game=$row;foreach($row['mods']??[] as $m)if((int)($m['mod_cfg_id']??0)===$policy['mod_cfg_id'])$mod=$m;}
        if(!$game || !$mod || !preg_match('/^[a-zA-Z0-9_.-]{1,80}$/D',$mod['mod_key']??''))throw new RuntimeException('Unbekannte Spiel-/Moddefinition für diese Plattform.');
        $definition=null;
        try{$definition=$client->call('GET','bridge/definition',['home_cfg_id'=>$policy['home_cfg_id'],'mod_cfg_id'=>$policy['mod_cfg_id'],'agent_id'=>$policy['agent_id']]);}catch(\Throwable$error){if($enabled)throw new RuntimeException('Spieldefinition und Installer können ohne die aktuelle OGP-Erweiterung nicht freigegeben werden.');}
        if(is_array($definition)){
            if(!empty($definition['requires_map']) && $policy['start_map']==='')throw new RuntimeException('Diese Spieldefinition benötigt eine Startkarte.');
            $policy['definition_sha256']=$definition['definition_sha256']??'';
            if(($definition['max_slots']??0)>0 && $policy['slot_max']>(int)$definition['max_slots'])throw new RuntimeException('Slotlimit überschreitet die Spieldefinition.');
        }
        if($enabled && (!is_array($definition) || empty($definition['slots_enforceable']) || ($definition['agent_system']??'unknown')!==$policy['system'] || !in_array($policy['install_method'],$definition['install_methods']??[],true) || !preg_match('/^[a-f0-9]{64}$/D',$policy['definition_sha256']??'')))throw new RuntimeException('Spieldefinition kann dieses Slotlimit oder Installationsverfahren nicht bereitstellen. Angebot auf Anfrage belassen.');
        $policy['game_name']=mb_substr((string)($game['game_name']??$game['game_key']??'Spiel'),0,120);$policy['game_key']=(string)($game['game_key']??'');$policy['mod_key']=$mod['mod_key'];$policy['connection_id']=\integration_identity('ogp');
        if($enabled){ProviderDiagnostics::requireCapabilities('ogp',['installation.status','home.delete','home.identity']);if($client->call('GET','server/status',['remote_server_id'=>$policy['agent_id']])!=='online')throw new RuntimeException('Der Agent ist nicht online.');}
        $json=json_encode($policy,JSON_THROW_ON_ERROR);$revision=hash('sha256',$json);$old=self::offer($product);
        if($old)\query('UPDATE smf_game_offers SET enabled=?,policy=?,revision=?,updated_at=? WHERE product_id=?',[$enabled?1:0,$json,$revision,time(),$product]);
        else \query('INSERT INTO smf_game_offers (product_id,enabled,policy,revision,updated_at) VALUES (?,?,?,?,?)',[$product,$enabled?1:0,$json,$revision,time()]);
        \save_product_options($product,['sale_mode'=>$enabled?'direct':'request','provisioning_notice'=>'Nach bestätigter Zahlung und erfolgreicher Spielinstallation.'],null);
        Kernel::boot()->audit()->record('gameserver.offer.saved',(string)$product,'ok');
    }
    public static function configure(array $product,array $input=[]): array {
        $offer=self::offer((int)$product['id']);if(!$offer)throw new RuntimeException('Gameserverangebot ist noch nicht eingerichtet.');$policy=$offer['policy'];
        if(array_diff(array_keys($input),['slots']))throw new RuntimeException('Unbekannte Gameserveroption. Preise und Providerwerte werden serverseitig festgelegt.');
        $slots=self::number(['slots'=>$input['slots']??$policy['slot_min']],'slots',$policy['slot_min'],$policy['slot_max']);if(($slots-$policy['slot_min'])%$policy['slot_step'])throw new RuntimeException('Ungültige Slotabstufung.');
        $net=$policy['base_net_cents']+$slots*$policy['slot_net_cents'];if($net<1 || $net>100000000)throw new RuntimeException('Ungültiger Gameserverpreis.');
        $policy['slots']=$slots;$policy['offer_revision']=$offer['revision'];$policy['module_product']='gameserver';
        $product['price_cents']=\vat_from_net($net)['gross_cents'];$product['_options']=array_replace(\product_options($product),['net_cents'=>$net,'sale_mode'=>$offer['enabled']?'direct':'request']);
        $product['_automation']=['provider'=>'ogp','enabled'=>$offer['enabled'],'settings'=>$policy];$product['_game_snapshot']=$policy;$product['_module_key']=hash('sha256',json_encode($policy,JSON_THROW_ON_ERROR));
        return $product;
    }
    public static function blockers(array $product): array {
        $registry=new ProductRegistry(Kernel::boot()->modules());
        $missing=$registry->blockers('gameserver',['ogp'=>ProviderDiagnostics::capabilities('ogp')]);
        $offer=self::offer((int)$product['id']);if(!$offer || !$offer['enabled'])$missing[]='Angebot ist im Anfragebetrieb.';
        if($offer && !hash_equals($offer['policy']['connection_id'],\integration_identity('ogp')))$missing[]='Provideranschluss wurde geändert.';
        return $missing;
    }
}
