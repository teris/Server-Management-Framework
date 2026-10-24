<?php
namespace SMF\Core;
final class ProviderAccounts {
    public static function shopActive(): bool {
        return class_exists('HttpSecurity',false) && \SchemaManager::hasTable(\HttpSecurity::connection(),'smf_module_state') && Kernel::boot()->modules()->installed('shop.storage');
    }
    public static function bootstrapShop(): void {
        if(!self::shopActive())throw new \RuntimeException('Shop adapter is not installed');
        require_once dirname(__DIR__,2).'/extensions/shop.storage/legacy/bootstrap.php';
        require_once dirname(__DIR__,2).'/extensions/shop.storage/legacy/providers.php';
    }
    public static function enabled(string $provider): bool {
        if(self::shopActive() && in_array($provider,['ovh','proxmox','ispconfig'],true)){
            $q=\HttpSecurity::connection()->prepare('SELECT enabled FROM shop_integrations WHERE provider=?');$q->execute([$provider]);$enabled=$q->fetchColumn();$q->closeCursor();if($enabled!==false)return (bool)$enabled;
        }
        $constant='Config::'.strtoupper($provider).'_USEING';return defined($constant)&&(bool)constant($constant);
    }
    public static function fallback(string $provider): array {
        $value=fn($name,$default='')=>defined('Config::'.$name)?constant('Config::'.$name):$default;
        return match($provider) {
            'proxmox'=>['url'=>rtrim($value('PROXMOX_HOST'),'/'),'auth_mode'=>'password','username'=>$value('PROXMOX_USER'),'password'=>$value('PROXMOX_PASSWORD'),'node'=>'','pool'=>''],
            'ispconfig'=>['url'=>rtrim($value('ISPCONFIG_HOST'),'/').'/remote/json.php','username'=>$value('ISPCONFIG_USER'),'password'=>$value('ISPCONFIG_PASSWORD'),'server_id'=>1,'mail_server_id'=>1,'db_server_id'=>1,'web_ip'=>'*'],
            'ovh'=>['endpoint'=>$value('OVH_ENDPOINT','ovh-eu'),'application_key'=>$value('OVH_APPLICATION_KEY'),'application_secret'=>$value('OVH_APPLICATION_SECRET'),'consumer_key'=>$value('OVH_CONSUMER_KEY'),'subsidiary'=>'DE'],
            default=>throw new \RuntimeException('Provider has no shared fallback')
        };
    }
    public static function connectionId(string $provider): ?string {
        if(!self::shopActive() || !in_array($provider,['ovh','proxmox','ispconfig'],true))return null;
        self::bootstrapShop();
        $row=\integration_row($provider);
        if(!$row)return null; // Preserve legacy assignments when using unchanged Config values.
        return \integration_identity($provider);
    }
}
