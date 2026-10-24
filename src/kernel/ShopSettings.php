<?php
namespace SMF\Core;
use RuntimeException;
final class ShopSettings {
    public static function all(): array {
        $file=\HttpSecurity::privateDirectory('config').'/shop-settings.json';
        if(is_link($file))throw new RuntimeException('Settings symlink rejected');
        $values=is_file($file)?json_decode(file_get_contents($file),true,64,JSON_THROW_ON_ERROR):[];
        return array_replace(['environment'=>'sandbox','site_name'=>defined('Config::FRONTPANEL_SITE_NAME')?\Config::FRONTPANEL_SITE_NAME:'Server Management','site_url'=>defined('Config::FRONTPANEL_SITE_URL')?\Config::FRONTPANEL_SITE_URL:'','support_email'=>defined('Config::FRONTPANEL_SUPPORT_EMAIL')?\Config::FRONTPANEL_SUPPORT_EMAIL:'','logo_url'=>'','accent'=>'#2563eb','direct_links'=>[],'allow_live_operations'=>false],$values);
    }
    public static function get(string $key): mixed { return self::all()[$key]??null; }
    public static function save(array $input): void {
        $values=self::all();
        foreach(['site_name','site_url','support_email','logo_url','accent','environment','direct_links']as$key)if(array_key_exists($key,$input))$values[$key]=$input[$key];
        if(!is_string($values['site_name']) || mb_strlen($values['site_name'])<1 || mb_strlen($values['site_name'])>180 || !in_array($values['environment'],['sandbox','live'],true) || !preg_match('/^#[a-fA-F0-9]{6}$/D',$values['accent']))throw new RuntimeException('Invalid shop settings');
        foreach(['site_url','logo_url']as$key)if($values[$key]!=='' && (!is_string($values[$key]) || !filter_var($values[$key],FILTER_VALIDATE_URL) || parse_url($values[$key],PHP_URL_SCHEME)!=='https' || parse_url($values[$key],PHP_URL_USER)!==null || parse_url($values[$key],PHP_URL_PASS)!==null))throw new RuntimeException('HTTPS URL required');
        if(!filter_var($values['support_email'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('Valid support email required');
        if(!is_array($values['direct_links']) || count($values['direct_links'])>20)throw new RuntimeException('Invalid direct links');
        foreach($values['direct_links']as$link)if(!is_array($link) || !is_string($link['label']??null) || mb_strlen($link['label'])>100 || !filter_var($link['url']??'',FILTER_VALIDATE_URL) || parse_url($link['url'],PHP_URL_SCHEME)!=='https' || parse_url($link['url'],PHP_URL_USER)!==null)throw new RuntimeException('Invalid direct link');
        $file=\HttpSecurity::privateDirectory('config').'/shop-settings.json';if(is_link($file))throw new RuntimeException('Settings symlink rejected');
        $temp=tempnam(dirname($file),'.shop-');if(!$temp || file_put_contents($temp,json_encode($values,JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('Settings write failed');chmod($temp,0600);if(!rename($temp,$file))throw new RuntimeException('Settings activation failed');
    }
    public static function allowLive(): bool { return self::get('environment')==='live' && self::get('allow_live_operations')===true; }
}
