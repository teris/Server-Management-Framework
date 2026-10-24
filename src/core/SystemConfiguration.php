<?php
if(PHP_SAPI!=='cli'&&(get_included_files()[0]??null)===__FILE__){http_response_code(404);exit;}
final class SystemConfiguration {
    private static function file():string{return HttpSecurity::privateDirectory('config').'/ui-settings.json';}
    public static function load():array{ $file=self::file();if(is_link($file))throw new RuntimeException('Settings symlink rejected');return is_file($file)?json_decode(file_get_contents($file),true,64,JSON_THROW_ON_ERROR):[]; }
    public static function apply():void{
        global$system_config,$dashboard_config,$modus_type;$values=self::load();
        foreach(['site_title','session_timeout']as$key)if(isset($values[$key]))$system_config[$key]=$values[$key];
        if(isset($values['refresh_interval']))$dashboard_config['refresh_interval']=$values['refresh_interval'];
        if(isset($values['mode']))$modus_type['modus']=$values['mode']==='database'?'database':'api';
    }
    public static function get():array{global$system_config,$dashboard_config,$modus_type;return ['site_title'=>$system_config['site_title']??Config::FRONTPANEL_SITE_NAME,'session_timeout'=>(int)ceil(HttpSecurity::timeout()/60),'refresh_interval'=>(int)($dashboard_config['refresh_interval']??30),'mode'=>in_array($modus_type['modus']??'api',['database','mysql'],true)?'database':'live'];}
    public static function save(array$data):array{
        $timeout=filter_var($data['session_timeout']??null,FILTER_VALIDATE_INT);$refresh=filter_var($data['refresh_interval']??null,FILTER_VALIDATE_INT);$title=trim((string)($data['site_title']??''));$mode=$data['mode']??'';
        if($timeout===false||$timeout<1||$timeout>1440||$refresh===false||$refresh<10||$refresh>300||$title===''||mb_strlen($title)>255||!in_array($mode,['live','database'],true))throw new InvalidArgumentException('Titel, Modus und Zeitgrenzen prüfen. Sitzung: 1–1440 Minuten; Aktualisierung: 10–300 Sekunden.');
        $file=self::file();if(is_link($file))throw new RuntimeException('Settings symlink rejected');$temporary=tempnam(dirname($file),'.settings-');$values=['site_title'=>$title,'session_timeout'=>$timeout*60,'refresh_interval'=>$refresh,'mode'=>$mode];
        if(!$temporary||file_put_contents($temporary,json_encode($values,JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('Settings write failed');chmod($temporary,0600);if(!rename($temporary,$file))throw new RuntimeException('Settings activation failed');self::apply();return self::get();
    }
}
