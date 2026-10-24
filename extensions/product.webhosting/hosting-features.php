<?php
function hosting_feature_flags(): array {
    return ['cgi_enabled'=>'CGI','ssi_enabled'=>'SSI','perl_enabled'=>'Perl','ruby_enabled'=>'Ruby','python_enabled'=>'Python','suexec_enabled'=>'SuEXEC','errordocs_enabled'=>'Eigene Fehlerseiten','ssl_enabled'=>'SSL','letsencrypt_enabled'=>"Let's Encrypt SSL"];
}
function hosting_php_modes(): array {return ['no'=>'Deaktiviert','fast-cgi'=>'Fast-CGI','cgi'=>'CGI','mod'=>'Mod-PHP','php-fpm'=>'PHP-FPM'];}
function hosting_capabilities(bool $refresh=false): array {
    $config=integration_config('ispconfig');$identity=integration_identity('ispconfig');$server=(int)$config['server_id'];
    $row=one("SELECT meta_value FROM shop_meta WHERE meta_key='ispconfig_hosting_capabilities'");$saved=$row?json_decode($row['meta_value'],true):null;
    if(!$refresh && $saved && ($saved['connection_id']??'')===$identity && (int)$saved['server_id']===$server && (int)$saved['checked_at']>time()-900)return $saved;
    $api=new IspconfigProvider();$web=$api->call('server_get',['server_id'=>$server,'section'=>'web']);
    if(!is_array($web))throw new RuntimeException('Die ISPConfig-Webserveroptionen konnten nicht gelesen werden.');
    $modes=hosting_php_modes();if(($web['server_type']??'apache')==='nginx')$modes=array_intersect_key($modes,array_flip(['no','php-fpm']));
    try{$allowed=$api->call('system_config_get',['section'=>'sites','key'=>'web_php_options']);if(is_string($allowed) && $allowed!=='')$modes=array_intersect_key($modes,array_flip(array_merge(['no'],explode(',',$allowed))));}catch(ProviderFailure $e){}
    $versions=[];foreach(['php-fpm','fast-cgi'] as $mode){
        if(!isset($modes[$mode]))continue;
        $rows=$api->call('server_get_php_versions',['server_id'=>$server,'php'=>$mode,'get_full_data'=>true]);
        if(!is_array($rows))throw new RuntimeException('Die PHP-Versionen konnten nicht von ISPConfig gelesen werden.');
        foreach($rows as $r){$id=(int)($r['server_php_id']??0);if(!$id || (int)($r['server_id']??$server)!==$server || ($r['active']??'y')!=='y' || empty($r['name']))continue;
            $versions[$id]??=['id'=>$id,'label'=>mb_substr((string)$r['name'],0,100),'modes'=>[]];$versions[$id]['modes'][]=$mode;
        }
    }
    uasort($versions,fn($a,$b)=>strnatcasecmp($b['label'],$a['label']));
    $result=['connection_id'=>$identity,'server_id'=>$server,'checked_at'=>time(),'server_type'=>$web['server_type']??'apache','php_modes'=>$modes,'php_versions'=>$versions,'default_php_label'=>mb_substr((string)($web['php_default_name']??'Systemstandard'),0,100)];
    $json=json_encode($result,JSON_THROW_ON_ERROR);if($row)query("UPDATE shop_meta SET meta_value=? WHERE meta_key='ispconfig_hosting_capabilities'",[$json]);else query("INSERT INTO shop_meta (meta_key,meta_value) VALUES ('ispconfig_hosting_capabilities',?)",[$json]);return $result;
}
function normalized_hosting_features(array $input,array $old=[],bool $check=false): array {
    $explicit=isset($input['website_features_present']);$out=[];
    foreach(hosting_feature_flags() as $key=>$label){$default=in_array($key,['suexec_enabled','errordocs_enabled'],true)?1:0;$value=$input[$key]??($explicit?0:($old[$key]??$default));if(!is_scalar($value))throw new RuntimeException('Ungültige Website-Option.');$out[$key]=in_array((string)$value,['1','on','y'],true)?1:0;}
    $out['php_mode']=(string)($input['php_mode']??$old['php_mode']??(($old['php_enabled']??1)?'php-fpm':'no'));
    if(!isset(hosting_php_modes()[$out['php_mode']]))throw new RuntimeException('Bitte eine gültige PHP-Betriebsart auswählen.');
    $id=$input['php_version']??$old['php_version']??0;if(!is_scalar($id))throw new RuntimeException('Bitte eine gültige PHP-Version auswählen.');$id=(string)$id;if(!preg_match('/^\d{1,9}$/D',$id))throw new RuntimeException('Bitte eine gültige PHP-Version auswählen.');$out['php_version']=(int)$id;
    $out['auto_subdomain']=(string)($input['auto_subdomain']??$old['auto_subdomain']??'www');if(!in_array($out['auto_subdomain'],['none','www','*'],true))throw new RuntimeException('Bitte eine gültige Auto-Subdomain auswählen.');
    if($out['letsencrypt_enabled'])$out['ssl_enabled']=1;$out['php_enabled']=$out['php_mode']==='no'?0:1;
    if($out['php_version'] && !in_array($out['php_mode'],['php-fpm','fast-cgi'],true))throw new RuntimeException('Eine zusätzliche PHP-Version benötigt PHP-FPM oder Fast-CGI.');
    if($check && integration_ready('ispconfig')){
        $caps=hosting_capabilities();if(!isset($caps['php_modes'][$out['php_mode']]))throw new RuntimeException('Diese PHP-Betriebsart ist auf deinem ISPConfig-Server nicht freigegeben.');
        if($out['php_version']){$v=$caps['php_versions'][$out['php_version']]??null;if(!$v || !in_array($out['php_mode'],$v['modes'],true))throw new RuntimeException('Die gewählte PHP-Version ist für diese Betriebsart auf dem Server nicht verfügbar.');$out['php_version_label']=$v['label'];}
    }elseif($check && $out['php_version'])throw new RuntimeException('Zum Prüfen der PHP-Version ist eine eingerichtete ISPConfig-Verbindung erforderlich.');
    if($explicit || isset($old['website_features_present']))$out['website_features_present']=1;return $out;
}
function hosting_website_feature_params(array $profile,bool $check=false): array {
    $f=normalized_hosting_features($profile,$profile,$check);$out=['php'=>$f['php_mode'],'server_php_id'=>$f['php_version'],'subdomain'=>$f['auto_subdomain']];
    foreach(['cgi','ssi','perl','ruby','python','suexec','ssl'] as $key)$out[$key]=$f[$key.'_enabled']?'y':'n';$out['errordocs']=$f['errordocs_enabled']?1:0;$out['ssl_letsencrypt']=$f['letsencrypt_enabled']?'y':'n';return $out;
}
function hosting_client_feature_limits(array $profile): array {
    $f=normalized_hosting_features($profile,$profile);$out=['web_php_options'=>$f['php_mode']];
    foreach(['cgi','ssi','perl','ruby','python','ssl'] as $key)$out['limit_'.$key]=$f[$key.'_enabled']?'y':'n';
    $out['force_suexec']=$f['suexec_enabled']?'y':'n';$out['limit_hterror']=$f['errordocs_enabled']?'y':'n';$out['limit_ssl_letsencrypt']=$f['letsencrypt_enabled']?'y':'n';$out['limit_wildcard']=$f['auto_subdomain']==='*'?'y':'n';return $out;
}
function hosting_select_choices(string $target): array {
    if($target==='auto_subdomain')return [1=>['label'=>'Keine','value'=>'none'],2=>['label'=>'www.','value'=>'www'],3=>['label'=>'*.','value'=>'*']];
    $caps=hosting_capabilities();$out=[];
    if($target==='php_mode'){$id=1;foreach(hosting_php_modes() as $value=>$label){if(isset($caps['php_modes'][$value]))$out[$id]=['label'=>$label,'value'=>$value];$id++;}}
    elseif($target==='php_version')foreach($caps['php_versions'] as $id=>$v)$out[$id]=['label'=>$v['label'],'value'=>(string)$id];
    return $out;
}
