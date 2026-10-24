import hashlib,json,subprocess,tempfile
from pathlib import Path
from php_runtime import php_command,fixture_environment
ROOT=Path(__file__).resolve().parents[1];checks=0

def check(ok,name):
 global checks
 checks+=1
 if not ok:raise AssertionError(name)

with tempfile.TemporaryDirectory(prefix='smf-ogp-bridge-')as tmp:
 p=Path(tmp);app=p/'ogp';(app/'includes').mkdir(parents=True);(app/'modules/config_games').mkdir(parents=True);(p/'private').mkdir()
 (app/'smf_ogp_bridge.php').write_bytes((ROOT/'integrations/ogp/smf_ogp_bridge.php').read_bytes())
 (app/'configs').mkdir();(app/'configs/fixture.xml').write_text('<game_config><installer>steamcmd</installer><cli_template>%PLAYERS%</cli_template><max_user_amount>32</max_user_amount><exe_location>bin</exe_location><server_exec_name>game-server</server_exec_name><replace_texts/></game_config>')
 key='a'*64;token='b'*64;(p/'private/smf-ogp-bridge.json').write_text(json.dumps({'key_sha256':hashlib.sha256(key.encode()).hexdigest()}))
 for name in ['functions.php','helpers.php','html_functions.php','lib_remote.php','config.inc.php','api_functions.php']:(app/'includes'/name).write_text('<?php')
 (app/'modules/config_games/server_config_parser.php').write_text('<?php')
 (app/'includes/helpers.php').write_text('''<?php
 define('OGP_SCREEN_TYPE_HOME','HOME');define('OGP_SCREEN_TYPE_UPDATE','UPDATE');define('SERVER_CONFIG_LOCATION','configs');
 $db_type='mysql';$db_host='fixture';$db_user='fixture';$db_pass='fixture';$db_name='fixture';$table_prefix='ogp_';
 function createDatabaseConnection(...$args){return new FixtureDb();}
 function is_authorized(){return !($GLOBALS['fixture']['host_denied']??false);}
 function clean_path($path){return preg_replace('~/+~','/',$path);}
 function read_server_config($file){return simplexml_load_string(file_get_contents($file));}
 class FixtureDb{
  private bool $deleted=false;private string $path='/home/ogp/OGP_User_Files/7';private string $parameters='null';
  public function changeHomePath($id,$path){$this->path=$path;return true;}
  public function changeLastParam($id,$json){$this->parameters=$json;return true;}
  public function getSettings(){return [];}
  public function resultQuery($q){return [['user_id'=>1]];}
  public function real_escape_string($x){return $x;}
  public function getUserById($id){return ['users_role'=>($GLOBALS['fixture']['user_role']??'admin')];}
  public function getRemoteServer($id){return ['agent_ip'=>'192.0.2.1','agent_port'=>12679,'encryption_key'=>'fixture-agent-secret','timeout'=>2];}
  public function getGameCfg($id){return $id===12?['home_cfg_file'=>'fixture.xml']:false;}
  public function getCfgMods($id){return [['mod_cfg_id'=>13,'mod_key'=>'fixture']];}
  public function getGameHome($id){if($this->deleted || ($GLOBALS['fixture']['missing']??false))return false;return ['home_id'=>7,'home_name'=>'SMF-'.str_repeat('c',32),'remote_server_id'=>2,'user_id_main'=>11,'home_cfg_id'=>12,'home_path'=>$this->path,'last_param'=>$this->parameters,'control_password'=>'fixture-control-secret','ftp_password'=>'fixture-ftp-secret','home_cfg_file'=>'fixture.xml','agent_ip'=>'192.0.2.1','agent_port'=>12679,'encryption_key'=>'fixture-agent-secret','timeout'=>2,'mods'=>[['mod_cfg_id'=>13,'mod_key'=>'fixture']]];}
  public function getHomeIpPorts($id){return [['ip'=>'192.0.2.10','port'=>30010]];}
  public function getGameHomes(){if($GLOBALS['fixture']['overlap']??false)return [['home_id'=>8,'home_path'=>'/home/ogp/OGP_User_Files/7/other','remote_server_id'=>2]];return [$this->getGameHome(7)];}
  public function IsFtpEnabled($id){return $GLOBALS['fixture']['ftp']??false;}
  public function deleteGameHome($id){$this->deleted=true;return true;}
 }
 class OGPRemoteLibrary{
  private bool $deleted=false;
  public function __construct(...$args){}
  public function what_os(){return 'Linux';}
  public function status_chk(){return ($GLOBALS['fixture']['offline']??false)?0:1;}
  public function is_screen_running($type,$id){return ($type==='HOME' && ($GLOBALS['fixture']['running']??false))||($type==='UPDATE' && ($GLOBALS['fixture']['installing']??false))?1:0;}
  public function rfile_exists($path){return $this->deleted || ($GLOBALS['fixture']['fresh']??false)?0:1;}
  public function remove_home($path){if($GLOBALS['fixture']['delete_fails']??false)return 0;$this->deleted=true;return 1;}
  public function get_log($type,$id,$path,&$log,$lines){$log='Safe log fixture-control-secret fixture-ftp-secret fixture-agent-secret';return 1;}
 }
''')
 wrapper=p/'probe.php';wrapper.write_text('<?php $input=json_decode(stream_get_contents(STDIN),true);$GLOBALS["fixture"]=$input["fixture"]??[];$_SERVER["HTTPS"]=$input["https"]??"on";$_SERVER["REQUEST_METHOD"]=$input["method"]??"POST";$_GET=[$input["action"]=>""];$_POST=$input["parameters"];require '+repr(str(app/'smf_ogp_bridge.php'))+';')
 base={'token':token,'bridge_key':key,'home_id':7,'marker':'SMF-'+'c'*32,'agent_id':2,'user_id':11,'home_cfg_id':12,'mod_cfg_id':13,'mod_key':'fixture','ip':'192.0.2.10','port':30010}
 def call(action='bridge/status',parameters=None,fixture=None,**kwargs):
  payload={'action':action,'parameters':base if parameters is None else parameters,'fixture':fixture or {}}|kwargs
  r=subprocess.run(php_command()+[str(wrapper)],input=json.dumps(payload),text=True,capture_output=True,env=fixture_environment())
  check(r.returncode==0,'bridge request terminates without fatal error')
  return json.loads(r.stdout)
 for kw in [{'method':'GET'},{'https':'off'}]:check(call(**kw)['status']=='400','HTTPS POST required')
 for field in ['token','bridge_key']:
  for value in ['',[], 'x'*64]:check(call(parameters=base|{field:value})['status']=='401','invalid credentials rejected')
 check(call(fixture={'host_denied':True})['status']=='401','OGP IP allowlist is respected')
 check(call(fixture={'user_role':'user'})['status']=='350','OGP administrator role is required')
 caps=call('bridge/capabilities');check(caps['message']['protocol']=='smf-ogp-bridge/1','bridge exposes versioned contract')
 definition=call('bridge/definition');check(definition['status']=='200' and definition['message']['slots_enforceable'] and definition['message']['install_methods']==['steam'],'actual XML controls available installer and slot enforcement')
 check(call('bridge/definition',base|{'mod_cfg_id':99})['status']=='307','definition does not accept foreign mod')
 good=call();check(good['status']=='200' and good['message']['executable_present'],'actual agent and executable flags returned')
 physical=good['message']['physical_id'];check(len(physical)==64,'physical identity is opaque SHA256')
 for field,value in [('marker','SMF-'+'d'*32),('agent_id',3),('user_id',99),('home_cfg_id',55),('mod_cfg_id',88),('ip','192.0.2.99'),('port',30011),('physical_id','f'*64)]:check(call(parameters=base|{field:value})['status']!='200','changed owner or physical identity rejected: '+field)
 check(call(fixture={'offline':True})['status']=='310','offline agent cannot appear ready')
 check(call(fixture={'installing':True})['message']['installing'],'running installer is reported independently')
 check(call('bridge/delete')['status']=='403','delete needs previously verified physical identity')
 for fixture in [{'running':True},{'installing':True},{'ftp':True},{'overlap':True},{'delete_fails':True}]:check(call('bridge/delete',base|{'physical_id':physical},fixture)['status']!='200','unsafe or unconfirmed home deletion rejected')
 log=call('bridge/log',base|{'physical_id':physical});check('Safe log' in log['message']['log'] and 'secret' not in log['message']['log'],'server log redacts known remote secrets')
 check(call('bridge/configure',base|{'physical_id':physical,'slots':4})['status']=='409','existing executable prevents silently adopting or moving a directory')
 configured=call('bridge/configure',base|{'physical_id':physical,'slots':4},fixture={'fresh':True});check(configured['status']=='200' and configured['message']['namespace_owned'] and configured['message']['physical_id']!=physical,'fresh homes get a random order-owned namespace and initialized PHP8-safe startup parameters')
 check(call('bridge/configure',base|{'physical_id':physical,'slots':4,'parameters_only':1})['status']=='200','operator may correct parameters of an exactly identified stopped home')
 check(call('bridge/configure',base|{'physical_id':physical,'slots':4,'start_map':'../../other'})['status']=='400','startup map cannot inject a path or command')
 result=call('bridge/delete',base|{'physical_id':physical});check(result['status']=='200' and result['message']['deleted'],'files and home deletion are confirmed separately')
 check(call('server/create')['status']=='400','bridge has no arbitrary OGP operation dispatch')
 (p/'private/smf-ogp-bridge.json').unlink();check(call()['status']=='503','missing bridge configuration keeps endpoint closed')
 (app/'includes/smf_ogp_bridge_config.php').write_text('<?php http_response_code(404);exit;\n// SMF_OGP_BRIDGE_KEY_SHA256: '+hashlib.sha256(key.encode()).hexdigest()+'\n')
 check(call()['status']=='200','FTP-only verifier file works without storing raw bridge key')
print(json.dumps({'checks':checks,'failed':0,'real_provider_writes':0,'scope':'isolated OGP interface fixtures'}))
