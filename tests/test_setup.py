import contextlib, fcntl, hashlib, http.cookiejar, http.server, json, os, re, shutil, socket, sqlite3, ssl, subprocess, tempfile, threading, time, urllib.error, urllib.parse, urllib.request
from pathlib import Path
from php_runtime import php_command, fixture_environment
from tls_helpers import certificate_files
ROOT=Path(__file__).resolve().parents[1];checks=0

def check(ok,message):
 global checks
 checks+=1
 if not ok:raise AssertionError(message)

def inputs(private):
 return {'private_dir':str(private),'db_type':'sqlite','site_url':'https://shop.example.invalid','site_name':'Fixture Shop','support_email':'support@example.invalid','system_email':'system@example.invalid','admin_name':'fixture-admin','admin_email':'admin@example.invalid','admin_password':'Installer fixture password 123','modules':['shop.billing','shop.contracts','shop.communications']}

def clone(path):
 path.mkdir()
 for name in json.loads((ROOT/'deploy/package-files.json').read_text()):
  destination=path/name;destination.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(ROOT/name,destination)

def cli(app,data=None,option='--install'):
 return subprocess.run(php_command()+[str(app/'bin/setup.php'),option],cwd=app,env=fixture_environment(),input=json.dumps(data)if data is not None else None,capture_output=True,text=True)

def prepare(app):
 r=cli(app,option='--prepare');check(r.returncode==0,'start key can be generated');token=re.search(r'SMF_SETUP_TOKEN: ([A-Za-z0-9_-]+)',(app/'config/setup-access.php').read_text())[1];check(len(token)==64,'generated key has 256 random bits');return token

class Api(http.server.BaseHTTPRequestHandler):
 requests=[]
 def log_message(self,*args):pass
 def do_POST(self):
  raw=self.rfile.read(int(self.headers.get('Content-Length',0)));data=json.loads(raw)if self.headers.get('Content-Type')=='application/json'else{k:v[0]for k,v in urllib.parse.parse_qs(raw.decode()).items()};self.requests.append((self.path,data));action=urllib.parse.urlparse(self.path).query;status=200
  if self.path.startswith('/isp/'):
   if action=='login':body={'code':'ok','response':'fixture-session'}if data.get('password')=='isp-fixture-password'else{'code':'remote_login_failed','response':False}
   elif action=='server_get':body={'code':'ok','response':{'server_id':1}if data.get('server_id')==1 else False}
   elif action=='logout':body={'code':'ok','response':True}
   else:status=404;body={}
  elif self.path.startswith('/missing/'):status=404;body={}
  elif self.path.startswith('/redirect/'):status=302;body={}
  elif action=='token/test':
   token=data.get('token');body={'status':'200','message':'admin'}if token in ['ogp-fixture-token','ogp-restricted-token']else({'status':'200','message':'user'}if token=='ogp-user-token'else({'status':'401','message':'Unauthorized host'}if token=='ogp-host-denied'else{'status':'400','message':'Invalid Token'}))
  elif action=='server/list':body={'status':'350','message':'Administrator function denied'}if data.get('token')=='ogp-restricted-token'else{'status':'200','message':[{'remote_server_id':1}]}
  else:status=404;body={}
  self.send_response(status)
  if status==302:self.send_header('Location','/ogp/ogp_api.php?token/test')
  self.send_header('Content-Type','application/json');self.end_headers();self.wfile.write(json.dumps(body).encode())

with tempfile.TemporaryDirectory(prefix='smf-setup-acceptance-')as directory:
 tmp=Path(directory);certs=certificate_files(tmp/'certificates');api=http.server.ThreadingHTTPServer(('127.0.0.1',0),Api);context=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER);context.load_cert_chain(str(certs/'valid.pem'),str(certs/'valid.key'));api.socket=context.wrap_socket(api.socket,server_side=True);threading.Thread(target=api.serve_forever,daemon=True).start();api_url='https://localhost:'+str(api.server_address[1]);probe=tmp/'probe.php';probe.write_text("<?php require "+repr(str(ROOT/'src/setup/ProviderDetector.php'))+"; $i=json_decode(stream_get_contents(STDIN),true); $d=new \\SMF\\Setup\\ProviderDetector(); echo json_encode($i['id']==='local'?$d->local($i['input']):$d->probe($i['id'],$i['input']));")
 def detect(kind,data):
  r=subprocess.run(php_command()+[str(probe)],env=fixture_environment(),input=json.dumps({'id':kind,'input':data}),capture_output=True,text=True);check(r.returncode==0,'provider probe terminates safely');return json.loads(r.stdout)
 try:
  ca=str(certs/'ca.pem');ogp={'url':api_url+'/ogp','token':'ogp-fixture-token','ca_file':ca};isp={'url':api_url+'/isp','username':'fixture-remote','password':'isp-fixture-password','server_id':'1','ca_file':ca}
  r=detect('ogp',ogp);check(r['state']=='ready'and r['agent_count']==1 and r['game_provisioning']is False,'OGP authenticated API is separate from game provisioning')
  check(detect('ispconfig',isp)['state']=='ready','ISPConfig login, server read and logout work over verified TLS')
  check(any(path.endswith('?logout')for path,_ in Api.requests),'ISPConfig session is closed')
  check(all('ogp-fixture-token'not in path and 'isp-fixture-password'not in path for path,_ in Api.requests),'provider credentials never enter URLs')
  check(all(urllib.parse.urlparse(path).query in ['token/test','server/list','login','server_get','logout']for path,_ in Api.requests),'recognition issues no provisioning operations')
  check(detect('ispconfig',isp|{'password':'wrong-password'})['state']=='authentication_failed','ISPConfig rejects wrong remote credentials')
  check(detect('ispconfig',isp|{'server_id':'2'})['state']=='permission_denied','ISPConfig server read permission is separate from login')
  check(detect('ogp',ogp|{'token':'ogp-user-token'})['state']=='permission_denied','OGP customer token cannot become operator connection')
  check(detect('ogp',ogp|{'token':'ogp-host-denied'})['state']=='access_denied','OGP IP denial remains distinct from missing installation')
  check(detect('ogp',ogp|{'token':'ogp-restricted-token'})['state']=='permission_denied','OGP server list capability must be readable')
  check(detect('ogp',ogp|{'token':'bad-token'})['state']=='authentication_failed','invalid OGP token cannot activate provider')
  check(detect('ogp',ogp|{'token':''})['state']=='credentials_required','API detection does not imply authenticated access')
  check(detect('ogp',ogp|{'ca_file':''})['state']=='unreachable','untrusted certificate cannot be accepted')
  check(detect('ogp',ogp|{'url':api_url+'/missing'})['state']=='endpoint_missing','missing endpoint is not a claim that panel is uninstalled')
  count=len(Api.requests);check(detect('ogp',ogp|{'url':api_url+'/redirect'})['state']=='unrecognized'and len(Api.requests)==count+1,'redirects are not followed with tokens')
  markers=tmp/'ogp';markers.mkdir();(markers/'ogp_api.php').write_text("<?php throw new Exception('MUST NOT EXECUTE');")
  check(detect('local',{'ogp':str(markers)})['ogp']['state']=='local_found','local installation marker is read without executing panel configuration')
  check(detect('local',{'ogp':str(tmp/'absent')})['ogp']['state']=='unknown','absent or inaccessible marker is reported as unknown')
 finally:api.shutdown();api.server_close()

 app=tmp/'cli-app';clone(app);token=prepare(app);data=inputs(tmp/'cli-private')|{'setup_token':token}
 lock=(app/'config/.setup.lock').open('w');fcntl.flock(lock,fcntl.LOCK_EX);r=cli(app,data);check(r.returncode!=0 and not(tmp/'cli-private').exists(),'concurrent installer is blocked before private or database writes');fcntl.flock(lock,fcntl.LOCK_UN);lock.close()
 os.utime(app/'config/setup-access.php',(time.time()-90000,time.time()-90000));r=cli(app,data);check(r.returncode!=0 and not(tmp/'cli-private').exists(),'expired ownership key cannot begin installation');os.utime(app/'config/setup-access.php',None)
 # Simulate a real interruption during an optional module migration, after core and shop storage exist.
 manifest=app/'extensions/shop.billing/module.json';original=manifest.read_bytes();bad=json.loads(original);bad['migrations']=['migrations/fixture.php'];manifest.write_text(json.dumps(bad));migration=manifest.parent/'migrations/fixture.php';migration.parent.mkdir();migration.write_text("<?php return static function(PDO $db):void { throw new RuntimeException('fixture interruption'); };")
 r=cli(app,data);check(r.returncode!=0,'interrupted installation stops before activation');check(not(app/'config/config.inc.php').exists(),'partial installation remains unpublished');db=sqlite3.connect(tmp/'cli-private/data/shop.sqlite');check(db.execute('SELECT COUNT(*) FROM users').fetchone()[0]==0,'failed module migration cannot leave a default administrator');db.close()
 r=cli(app,data|{'site_name':'Changed attempt'});check(r.returncode!=0,'resume rejects changed installation identity')
 key_file=tmp/'cli-private/config/shop.key';key_bytes=key_file.read_bytes();key_file.write_bytes(b'0'*64);r=cli(app,data);check(r.returncode!=0,'resume refuses a changed shop encryption key');key_file.write_bytes(key_bytes)
 manifest.write_bytes(original);migration.unlink();r=cli(app,data);check(r.returncode==0,'same installation resumes after interrupted migration');report=json.loads(r.stdout);check(report['installed']and len(report['modules'])==8,'selected modules and dependencies are installed');check(report['cron_configured']is False and report['mail_delivery_tested']is False,'installer does not claim unperformed operational checks');check(not(app/'config/setup-access.php').exists(),'start key is removed after successful activation');check('admin_password'not in r.stdout and data['admin_password']not in r.stdout,'installation report contains no credentials');check('require_once'in(app/'config/config.inc.php').read_text()and data['admin_password']not in(app/'config/config.inc.php').read_text(),'webroot contains a private configuration reference')
 db=sqlite3.connect(tmp/'cli-private/data/shop.sqlite');before=db.execute('SELECT id,username,email,password_hash FROM users').fetchall();check(len(before)==1 and before[0][1]=='fixture-admin','one explicitly configured administrator exists');check(db.execute('SELECT COUNT(*) FROM smf_module_state WHERE enabled=1').fetchone()[0]==8,'only selected modules are enabled');r=cli(app,data);check(r.returncode!=0 and db.execute('SELECT id,username,email,password_hash FROM users').fetchall()==before,'reinstallation cannot overwrite the administrator');db.close()

 # A foreign database and a private directory inside the document root are rejected.
 foreign=tmp/'foreign-app';clone(foreign);foreign_token=prepare(foreign);private=tmp/'foreign-private';(private/'data').mkdir(parents=True);db=sqlite3.connect(private/'data/shop.sqlite');db.execute('CREATE TABLE operator_data (value TEXT)');db.execute("INSERT INTO operator_data VALUES ('keep')");db.commit();db.close();r=cli(foreign,inputs(private)|{'setup_token':foreign_token});check(r.returncode!=0 and 'nicht leer'in r.stderr,'foreign database is rejected');db=sqlite3.connect(private/'data/shop.sqlite');check(db.execute('SELECT value FROM operator_data').fetchone()[0]=='keep','foreign data remains unchanged');db.close();r=cli(foreign,inputs(foreign/'private')|{'setup_token':foreign_token});check(r.returncode!=0 and not(foreign/'private').exists(),'private data inside application is rejected before writes');r=cli(foreign,inputs(tmp/'pgsql-private')|{'setup_token':foreign_token,'db_type':'pgsql'});check(r.returncode!=0,'unsupported shop database cannot be selected')

 # Complete browser wizard on a fresh application, without a pre-existing Config class or SSH worker.
 web=tmp/'web-app';clone(web);web_token=prepare(web);sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close();base='http://127.0.0.1:'+str(port);log=(tmp/'web.log').open('w+');process=subprocess.Popen(php_command()+['-d','display_errors=0','-S','127.0.0.1:'+str(port),'-t',str(web)],cwd=web,env=fixture_environment(),stdout=log,stderr=log)
 jar=http.cookiejar.CookieJar();opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
 def request(path='/setup.php',data=None):
  if data is not None:data=urllib.parse.urlencode(data,doseq=True).encode()
  try:r=opener.open(base+path,data=data,timeout=30);return r.status,r.read().decode(),r.url
  except urllib.error.HTTPError as e:return e.code,e.read().decode(),e.url
 def post(data):
  status,body,_=request();csrf=re.search(r'name="csrf_token" value="([a-f0-9]+)"',body);check(bool(csrf),'wizard step has CSRF protection');return request(data=dict(data,csrf_token=csrf[1]))
 try:
  for _ in range(80):
   try:request();break
   except OSError:time.sleep(.05)
  code,body,url=request('/');check(code==200 and url.endswith('/setup.php'),'unconfigured homepage opens protected installer');check(request(data={'setup_token':web_token})[0]==403,'wizard rejects a missing CSRF token');check(post({'setup_token':'wrong'})[0]==403,'wizard rejects wrong owner key');check(request('/config/setup-access.php')[0]==404,'start key refuses direct HTTP even without webserver rules');check(post({'setup_token':web_token})[0]==200,'owner can start guided installation')
  web_data=inputs(tmp/'web-private');post({k:web_data[k]for k in ['private_dir','db_type']});post({k:web_data[k]for k in ['site_url','site_name','support_email','system_email','admin_name','admin_email','admin_password']}|{'admin_password_confirm':web_data['admin_password'],'mail_mode':'system'});code,body,_=post({});check(code==200 and 'Shopmodule auswählen'in body,'provider step supports independent shop without panels');code,body,_=post({'modules[]':['shop.billing']});check(code==200 and 'Prüfergebnis'in body,'module selection reaches final review');code,body,_=post({});check(code==200 and 'Installation abgeschlossen'in body,'full browser installation succeeds: '+str(re.findall(r'<p class="error"[^>]*>(.*?)</p>',body))+'; '+str(json.loads((Path(tempfile.gettempdir())/('smf-setup-'+hashlib.sha256(str(web/'config/setup-access.php').encode()).hexdigest()[:24])/'last-error.json').read_text())));check(web_data['admin_password']not in body,'browser result does not disclose credentials');check(request()[0]==409,'finished browser installer is locked');check(not(web/'config/setup-access.php').exists(),'browser installer removes owner key');db=sqlite3.connect(tmp/'web-private/data/shop.sqlite');check(db.execute('SELECT COUNT(*) FROM users').fetchone()[0]==1 and db.execute('SELECT COUNT(*) FROM smf_module_state WHERE enabled=1').fetchone()[0]==6,'browser wizard provisions one admin and requested module dependency set');db.close()
  code,body,_=request('/src/login.php');csrf=re.search(r'name="csrf_token" value="([a-f0-9]+)"',body)[1];code,body,url=request('/src/login.php',{'csrf_token':csrf,'action':'login','username':web_data['admin_name'],'password':web_data['admin_password']});check(code==200 and url.endswith('/src/index.php'),'created administrator can log into installed framework')
 finally:
  process.terminate();process.wait(timeout=5);log.close();state_dir=Path(tempfile.gettempdir())/('smf-setup-'+hashlib.sha256(str(web/'config/setup-access.php').encode()).hexdigest()[:24]);shutil.rmtree(state_dir,ignore_errors=True)
 if os.environ.get('SMF_TEST_SETUP_NATIVE')=='1':
  import secrets
  name='smf_setup_'+secrets.token_hex(6);client=[os.environ.get('SMF_TEST_MYSQL_CLIENT','mariadb'),'--no-defaults','--user='+os.environ.get('SMF_TEST_MYSQL_USER','root')]
  if os.environ.get('SMF_TEST_MYSQL_PLUGIN_DIR'):client+=['--plugin-dir='+os.environ['SMF_TEST_MYSQL_PLUGIN_DIR']]
  mysql_socket=os.environ.get('SMF_TEST_MYSQL_SOCKET','');client+=['--socket='+mysql_socket]if mysql_socket else ['--host='+os.environ.get('SMF_TEST_MYSQL_HOST','127.0.0.1'),'--port='+os.environ.get('SMF_TEST_MYSQL_PORT','3306')];mysql_env=fixture_environment(MYSQL_PWD=os.environ.get('SMF_TEST_MYSQL_PASSWORD',''))
  subprocess.run(client+['--execute=CREATE DATABASE '+name+' CHARACTER SET utf8mb4'],env=mysql_env,check=True,capture_output=True)
  try:
   native=tmp/'native-app';clone(native);native_token=prepare(native);native_data=inputs(tmp/'native-private')|{'setup_token':native_token,'db_type':'mysql','db_host':os.environ.get('SMF_TEST_MYSQL_HOST','127.0.0.1'),'db_port':os.environ.get('SMF_TEST_MYSQL_PORT','3306'),'db_name':name,'db_user':os.environ.get('SMF_TEST_MYSQL_USER','root'),'db_password':os.environ.get('SMF_TEST_MYSQL_PASSWORD',''),'db_socket':mysql_socket};r=cli(native,native_data);check(r.returncode==0,'native MySQL/MariaDB guided backend installation succeeds: '+r.stderr);check(json.loads(r.stdout)['installed'],'native installation reports completion');counts=subprocess.check_output(client+['--database='+name,'--batch','--skip-column-names','--execute=SELECT COUNT(*) FROM users; SELECT COUNT(*) FROM smf_module_state WHERE enabled=1;'],env=mysql_env,text=True).split();check(counts==['1','8'],'native database has one chosen administrator and seven chosen modules');check(not(native/'config/setup-access.php').exists(),'native installation removes bootstrap key');check(cli(native,native_data).returncode!=0,'native reinstallation is rejected')
  finally:subprocess.run(client+['--execute=DROP DATABASE '+name],env=mysql_env,check=True,capture_output=True)

print(json.dumps({'checks':checks,'failed':0,'provider_writes':0,'tested':['verified TLS detection','interrupted CLI resume','foreign database preservation','guided browser install','administrator login','completed installer lock']}))
