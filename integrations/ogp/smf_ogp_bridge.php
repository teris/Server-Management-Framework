<?php
/** SPDX-License-Identifier: GPL-3.0-or-later
 * Optional SMF extension for an existing OpenGamePanel installation.
 * Uses the installed OGP interfaces; no OGP source or credentials are bundled.
 */
declare(strict_types=1);
ini_set('display_errors','0');ini_set('zend.exception_ignore_args','1');
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
function smf_ogp_reply(int $status,mixed $data): never {echo json_encode(['status'=>(string)$status,'message'=>$data],JSON_INVALID_UTF8_SUBSTITUTE);exit;}
try {
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST' || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off')smf_ogp_reply(400,'POST_HTTPS_REQUIRED');
    if((int)($_SERVER['CONTENT_LENGTH']??0)>16384)smf_ogp_reply(400,'REQUEST_TOO_LARGE');
    $configFile=dirname(__DIR__).'/private/smf-ogp-bridge.json';
    if(!is_link($configFile) && @is_file($configFile))$config=json_decode(file_get_contents($configFile),true,16,JSON_THROW_ON_ERROR);
    else {
        // FTP-only hosting can keep the random key's verifier in a PHP-guarded file; the key itself is never stored here.
        $configFile=__DIR__.'/includes/smf_ogp_bridge_config.php';
        if(is_link($configFile) || !is_file($configFile) || !preg_match('/^\/\/ SMF_OGP_BRIDGE_KEY_SHA256: ([a-f0-9]{64})\r?$/m',file_get_contents($configFile),$match))smf_ogp_reply(503,'BRIDGE_NOT_CONFIGURED');
        $config=['key_sha256'=>$match[1]];
    }
    $key=$_POST['bridge_key']??'';$token=$_POST['token']??'';
    if(!is_string($key) || !preg_match('/^[a-f0-9]{64}$/D',$key) || !hash_equals($config['key_sha256']??'',hash('sha256',$key)) || !is_string($token) || !preg_match('/^[a-f0-9]{64}$/Di',$token))smf_ogp_reply(401,'ACCESS_DENIED');
    chdir(__DIR__);define('INCLUDES','includes/');define('MODULES','modules/');
    foreach(['functions.php','helpers.php','html_functions.php','lib_remote.php','config.inc.php','api_functions.php'] as $file)require_once INCLUDES.$file;
    require_once MODULES.'config_games/server_config_parser.php';
    define('API_TABLE',$table_prefix.'api_tokens');
    $db=createDatabaseConnection($db_type,$db_host,$db_user,$db_pass,$db_name,$table_prefix);$settings=$db->getSettings();
    if(!is_authorized())smf_ogp_reply(401,'HOST_NOT_AUTHORIZED');
    $users=$db->resultQuery("SELECT user_id FROM ".API_TABLE." WHERE token='".$db->real_escape_string($token)."'");
    $user_info=$users?$db->getUserById($users[0]['user_id']):false;
    if(!$user_info || ($user_info['users_role']??'')!=='admin')smf_ogp_reply(350,'ADMIN_REQUIRED');
    $action=key($_GET);
    if($action==='bridge/capabilities')smf_ogp_reply(200,['protocol'=>'smf-ogp-bridge/1','capabilities'=>['installation.status','home.identity','home.delete','home.log','port.read','game.definition','home.configure']]);
    if($action==='bridge/definition'){
        $cfg=(int)($_POST['home_cfg_id']??0);$modId=(int)($_POST['mod_cfg_id']??0);$game=$db->getGameCfg($cfg);$mod=false;
        if(!$game)smf_ogp_reply(305,'GAME_CONFIG_MISSING');
        foreach($db->getCfgMods($cfg)?:[] as$row)if((int)$row['mod_cfg_id']===$modId)$mod=$row;
        if(!$mod)smf_ogp_reply(307,'MOD_CONFIG_MISMATCH');
        $file=SERVER_CONFIG_LOCATION.'/'.$game['home_cfg_file'];$xml=read_server_config($file);if(!$xml)smf_ogp_reply(305,'GAME_CONFIG_MISSING');
        $methods=[];if((string)$xml->installer==='steamcmd')$methods[]='steam';
        $slots=(bool)preg_match('/%(?:PLAYERS|MAXPLAYERS|MAX_PLAYERS)%/',(string)$xml->cli_template);
        foreach($xml->replace_texts->text??[] as$text)if((string)$text['key']==='max_players')$slots=true;
        $agentSystem='unknown';$agentId=(int)($_POST['agent_id']??0);
        if($agentId>0){$agent=$db->getRemoteServer($agentId);if(!$agent)smf_ogp_reply(304,'AGENT_NOT_FOUND');$agentRemote=new OGPRemoteLibrary($agent['agent_ip'],$agent['agent_port'],$agent['encryption_key'],max(1,min(5,(int)$agent['timeout'])));if($agentRemote->status_chk()!==1)smf_ogp_reply(310,'AGENT_OFFLINE');$os=$agentRemote->what_os();$agentSystem=preg_match('/Linux/i',$os)?'linux':(preg_match('/Windows|CYGWIN|MSWin/i',$os)?'windows':'unknown');}
        smf_ogp_reply(200,['requires_map'=>str_contains((string)$xml->cli_template,'%MAP%'),'agent_system'=>$agentSystem,'home_cfg_id'=>$cfg,'mod_cfg_id'=>$modId,'install_methods'=>$methods,'slots_enforceable'=>$slots,'max_slots'=>(int)$xml->max_user_amount,'definition_sha256'=>hash('sha256',(string)file_get_contents($file).json_encode([$cfg,$modId,$mod['mod_key']]))]);
    }
    if($action==='bridge/ports'){
        $ports=[];$agent=(int)($_POST['agent_id']??0);if($agent<1)smf_ogp_reply(400,'AGENT_REQUIRED');
        foreach($db->getGameHomes()?:[] as $home)if((int)$home['remote_server_id']===$agent)foreach($db->getHomeIpPorts($home['home_id'])?:[] as $address)$ports[]=['ip'=>$address['ip'],'port'=>(int)$address['port']];
        smf_ogp_reply(200,$ports);
    }
    if(!in_array($action,['bridge/status','bridge/delete','bridge/log','bridge/configure'],true))smf_ogp_reply(400,'UNSUPPORTED_OPERATION');
    $id=(int)($_POST['home_id']??0);$marker=$_POST['marker']??'';
    if($id<1 || !is_string($marker) || !preg_match('/^SMF-[a-f0-9]{32}$/D',$marker))smf_ogp_reply(400,'INVALID_BINDING');
    $home=$db->getGameHome($id);if(!$home)smf_ogp_reply(315,'HOME_NOT_FOUND');
    if(!hash_equals($marker,(string)$home['home_name']) || (int)$home['remote_server_id']!==(int)($_POST['agent_id']??0) || (int)$home['user_id_main']!==(int)($_POST['user_id']??0) || (int)$home['home_cfg_id']!==(int)($_POST['home_cfg_id']??0))smf_ogp_reply(403,'OWNERSHIP_CHANGED');
    $address=false;foreach($db->getHomeIpPorts($id)?:[] as $row)if($row['ip']===($_POST['ip']??'') && (int)$row['port']===(int)($_POST['port']??0))$address=true;
    $mod=false;foreach($home['mods']?:[] as $row)if((int)$row['mod_cfg_id']===(int)($_POST['mod_cfg_id']??0) && $row['mod_key']===($_POST['mod_key']??''))$mod=true;
    if(!$address || !$mod)smf_ogp_reply(403,'ADDRESS_OR_GAME_CHANGED');
    $physical=hash('sha256',json_encode([$id,$home['remote_server_id'],$home['user_id_main'],$home['home_cfg_id'],$home['home_name'],$home['home_path'],$home['control_password']]));
    if(isset($_POST['physical_id']) && !hash_equals($physical,(string)$_POST['physical_id']))smf_ogp_reply(403,'PHYSICAL_ID_CHANGED');
    $remote=new OGPRemoteLibrary($home['agent_ip'],$home['agent_port'],$home['encryption_key'],$home['timeout']);
    if($remote->status_chk()!==1)smf_ogp_reply(310,'AGENT_OFFLINE');
    $xml=read_server_config(SERVER_CONFIG_LOCATION.'/'.$home['home_cfg_file']);if(!$xml)smf_ogp_reply(305,'GAME_CONFIG_MISSING');
    $running=$remote->is_screen_running(OGP_SCREEN_TYPE_HOME,$id);$installing=$remote->is_screen_running(OGP_SCREEN_TYPE_UPDATE,$id);
    if($running<0 || $installing<0)smf_ogp_reply(503,'AGENT_STATUS_UNCONFIRMED');
    $exe=clean_path($home['home_path'].'/'.($xml->exe_location?(string)$xml->exe_location.'/':'').(string)$xml->server_exec_name);
    $exists=$remote->rfile_exists($exe);if($exists<0)smf_ogp_reply(503,'EXECUTABLE_STATUS_UNCONFIRMED');
    if($action==='bridge/status')smf_ogp_reply(200,['home_id'=>$id,'physical_id'=>$physical,'running'=>$running===1,'installing'=>$installing===1,'executable_present'=>$exists===1,'startup_configured'=>is_array(json_decode((string)($home['last_param']??''),true)),'namespace_owned'=>str_ends_with(rtrim($home['home_path'],'/'),'/'.strtolower($marker))]);
    if(!isset($_POST['physical_id']))smf_ogp_reply(403,'PHYSICAL_ID_REQUIRED');
    if($action==='bridge/configure'){
        if($running!==0 || $installing!==0 || $db->IsFtpEnabled($id))smf_ogp_reply(409,'HOME_MUST_BE_STOPPED_WITHOUT_FTP');
        $map=(string)($_POST['start_map']??'');$slots=(int)($_POST['slots']??0);
        if(!preg_match('/^[a-zA-Z0-9_.-]{0,80}$/D',$map) || str_contains($map,'..') || $slots<1 || $slots>(int)$xml->max_user_amount || (str_contains((string)$xml->cli_template,'%MAP%') && $map===''))smf_ogp_reply(400,'INVALID_START_PARAMETERS');
        $path=rtrim($home['home_path'],'/');$suffix='/'.strtolower($marker);
        if(empty($_POST['parameters_only']) && !str_ends_with($path,$suffix)){
            if($exists!==0 || strlen($path)<12 || !str_starts_with($path,'/') || str_contains($path,'..'))smf_ogp_reply(409,'EXISTING_HOME_REQUIRES_REVIEW');
            $path.=$suffix;if($remote->rfile_exists($path)!==0)smf_ogp_reply(409,'NAMESPACE_ALREADY_EXISTS');
            if($db->changeHomePath($id,$path)===false)smf_ogp_reply(503,'NAMESPACE_UNCONFIRMED');
        }
        if($db->changeLastParam($id,json_encode(['map'=>$map,'players'=>(string)$slots],JSON_THROW_ON_ERROR))===false)smf_ogp_reply(503,'PARAMETERS_UNCONFIRMED');
        $updated=$db->getGameHome($id);
        if(!$updated || $updated['home_path']!==$path || json_decode((string)$updated['last_param'],true)!==['map'=>$map,'players'=>(string)$slots])smf_ogp_reply(503,'CONFIGURATION_UNCONFIRMED');
        $physical=hash('sha256',json_encode([$id,$updated['remote_server_id'],$updated['user_id_main'],$updated['home_cfg_id'],$updated['home_name'],$path,$updated['control_password']]));
        smf_ogp_reply(200,['configured'=>true,'physical_id'=>$physical,'namespace_owned'=>str_ends_with($path,$suffix)]);
    }
    if($action==='bridge/log'){
        $kind=$_POST['kind']??'server';if(!in_array($kind,['server','installer'],true))smf_ogp_reply(400,'INVALID_LOG_KIND');
        $log='';$remote->get_log($kind==='installer'?OGP_SCREEN_TYPE_UPDATE:OGP_SCREEN_TYPE_HOME,$id,clean_path($home['home_path']),$log,100);
        $log=substr((string)$log,0,32768);foreach(['control_password','ftp_password','encryption_key'] as $field)if(!empty($home[$field]))$log=str_replace($home[$field],'[redacted]',$log);
        foreach(['steam_user','steam_pass','steam_guard'] as$field)if(!empty($settings[$field]))$log=str_replace($settings[$field],'[redacted]',$log);
        $log=str_replace([$token,$key],'[redacted]',$log);
        smf_ogp_reply(200,['log'=>$log]);
    }
    if($running!==0 || $installing!==0 || $db->IsFtpEnabled($id))smf_ogp_reply(409,'HOME_MUST_BE_STOPPED_WITHOUT_FTP');
    $path=rtrim($home['home_path'],'/');if(strlen($path)<12 || !str_starts_with($path,'/') || str_contains($path,'..'))smf_ogp_reply(403,'UNSAFE_HOME_PATH');
    foreach($db->getGameHomes()?:[] as $other){if((int)$other['home_id']===$id)continue;$otherPath=rtrim((string)$other['home_path'],'/');if($otherPath===$path || str_starts_with($otherPath,$path.'/') || str_starts_with($path,$otherPath.'/'))smf_ogp_reply(403,'HOME_PATH_OVERLAP');}
    if($remote->rfile_exists($path)===1 && $remote->remove_home($path)!==1)smf_ogp_reply(503,'HOME_FILES_NOT_REMOVED');
    if($remote->rfile_exists($path)!==0)smf_ogp_reply(503,'HOME_FILES_REMOVAL_UNCONFIRMED');
    if($db->deleteGameHome($id)===false || $db->getGameHome($id)!==false)smf_ogp_reply(503,'HOME_DATABASE_REMOVAL_UNCONFIRMED');
    smf_ogp_reply(200,['deleted'=>true,'home_id'=>$id]);
} catch(Throwable $error){error_log('SMF OGP bridge failed: '.get_class($error));smf_ogp_reply(503,'BRIDGE_OPERATION_UNCONFIRMED');}
