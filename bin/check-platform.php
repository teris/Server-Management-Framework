<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/config/load.php';
$type=strtolower(Config::DB_TYPE);$type=['mariadb'=>'mysql','postgresql'=>'pgsql'][$type]??$type;
$required=['pdo','mbstring','simplexml','dom','ctype','tokenizer','openssl','session','json'];
if(in_array($type,['mysql','pgsql','sqlite'],true))$required[]='pdo_'.$type;
if(Config::ISPCONFIG_USEING)$required[]='soap';if(Config::PROXMOX_USEING||Config::OVH_USEING||Config::OGP_USEING)$required[]='curl';
$missing=array_values(array_filter($required,fn($extension)=>!extension_loaded($extension)));
$supported=in_array($type,['mysql','pgsql','sqlite'],true)&&version_compare(PHP_VERSION,'8.3','>=')&&!$missing;
echo json_encode(['supported'=>$supported,'php'=>PHP_VERSION,'database'=>$type,'missing_extensions'=>$missing,'update_extensions'=>['zip'=>extension_loaded('zip'),'sqlite3'=>extension_loaded('sqlite3')]],JSON_PRETTY_PRINT)."\n";exit($supported?0:1);
