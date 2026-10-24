<?php
use SMF\Core\Kernel;
use SMF\Core\ShopSettings;
function shop_private(): string { return HttpSecurity::privateDirectory('shop'); }
function shop_key(): string {
    $file=HttpSecurity::privateDirectory('config').'/shop.key';
    if(is_link($file))throw new RuntimeException('Shop key symlink rejected');
    if(!is_file($file)) {
        if(PHP_SAPI!=='cli' && !defined('SMF_DEPLOYMENT_MIGRATION'))throw new RuntimeException('Install shop key before activating the module');
        $handle=@fopen($file,'x');
        if($handle){chmod($file,0600);fwrite($handle,bin2hex(random_bytes(32)));fflush($handle);fclose($handle);}
    }
    $key=file_get_contents($file);if(strlen($key)<32)throw new RuntimeException('Shop key unavailable');return $key;
}
if(!defined('ORGA_LOCAL'))define('ORGA_LOCAL',PHP_SAPI==='cli-server');
if(!defined('ORGA_APP_KEY'))define('ORGA_APP_KEY',shop_key());
function shop_require_module(string $id): void { Kernel::boot()->modules()->assertEnabled($id); }
function shop_feature(string $id): bool { return Kernel::boot()->modules()->enabled($id); }
function shop_require_action(string $action): void {
    static $owners;$owners??=json_decode(file_get_contents(dirname(__DIR__).'/actions.json'),true,64,JSON_THROW_ON_ERROR);
    if(!isset($owners[$action]))throw new SecurityViolation('Aktion nicht freigegeben.',404);
    shop_require_module($owners[$action]);
    if(str_starts_with($action,'paypal_') || $action==='checkout_start')shop_require_module('shop.payments');
    if(in_array($action,['worker_run','job_retry','job_resolve'],true))shop_require_module('shop.provisioning');
    if(in_array($action,['smtp_save','mail_retry','mail_test'],true))shop_require_module('shop.communications');
    if($action==='worker_run' && PHP_SAPI!=='cli')throw new SecurityViolation('Worker nur über CLI.',403);
    Kernel::boot()->audit()->record('shop.action',$action,'started');
}
function shop_brand(): string {
    $url=ShopSettings::get('logo_url');$name=e(ShopSettings::get('site_name'));
    return $url?'<img class="brand-logo" src="'.e($url).'" alt="'.$name.'" width="230">':'<strong class="brand-name">'.$name.'</strong>';
}
function shop_provider_guard(string $provider,string $method,string $url,?string $body): void {
    global $modus_type;
    if(in_array($modus_type['modus']??'api',['database','mysql'],true))throw new SecurityViolation('Providerzugriffe sind im Datenbank-Cachemodus deaktiviert.',503);
    if(isset(Kernel::boot()->modules()->catalog()['provider.'.$provider]) && Kernel::boot()->modules()->installed('provider.'.$provider)){
        Kernel::boot()->modules()->assertEnabled('provider.'.$provider);
        if(!SMF\Core\ProviderAccounts::enabled($provider))throw new SecurityViolation('Provideranschluss ist deaktiviert.',403);
    }
    if(!in_array($provider,['ovh','proxmox','ispconfig','ogp'],true))return;
    $read=strtoupper($method)==='GET';
    if($provider==='proxmox' && strtoupper($method)==='POST' && (parse_url($url,PHP_URL_PATH)?:'')==='/api2/json/access/ticket')$read=true;
    // ISPConfig JSON authenticates and reads over POST; allow only named read methods.
    if($provider==='ispconfig') {
        $path=rawurldecode(parse_url($url,PHP_URL_QUERY)?:'');
        $read=$path==='get_function_list' || (bool)preg_match('~^(?:login|logout|[a-z0-9_]+_get(?:_[a-z0-9_]+)?)$~D',$path);
    }
    if($provider==='ogp')$read=in_array(parse_url($url,PHP_URL_QUERY)?:'',['token/test','server/list','server/status','server/list_ips','user_games/list_games','user_games/list_servers','user_admin/list','user_admin/get','user_admin/list_assigned','litefm/list','litefm/get','setting/get','bridge/capabilities','bridge/status','bridge/ports','bridge/log','bridge/definition'],true);
    if(!$read && !ShopSettings::allowLive())throw new SecurityViolation('Provider-Schreibzugriff ist im Testbetrieb gesperrt.',403);
}
