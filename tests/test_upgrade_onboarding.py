import hashlib,http.cookiejar,json,re,sqlite3,subprocess,urllib.error,urllib.parse,urllib.request
from pathlib import Path
from http_fixture import HTTPFixture
from php_runtime import php_command
checks=0

def check(value,name):
 global checks
 checks+=1
 if not value:raise AssertionError(name)

with HTTPFixture(shop_routes=True) as f:
 def cli(script,*args):
  r=subprocess.run(php_command()+[str(f.app/script),*args],env=f.env,capture_output=True,text=True)
  if r.returncode:raise RuntimeError(r.stderr)
  return json.loads(r.stdout)
 cli('bin/modules.php','--shop')
 private=f.tmp/'private';(private/'config'/'fixture-operator.txt').write_text('Preserve operator private data')
 sql=sqlite3.connect(f.tmp/'application.sqlite');before=sql.execute('SELECT id,username,email,password_hash FROM users ORDER BY id').fetchall();key=(private/'config/shop.key').read_bytes()
 state=cli('bin/upgrade.php','--modules=product.gameserver')
 check(state['state']=='queued','authenticated change is queued before any migration')
 check(sql.execute("SELECT COUNT(*) FROM sqlite_master WHERE name='smf_game_leases'").fetchone()[0]==0,'queued request performs no browser migration')
 state=cli('bin/upgrade.php','--run')
 check(state['state']=='done','CLI worker performs backed-up module upgrade')
 check(sql.execute("SELECT COUNT(*) FROM smf_module_state WHERE module_id IN ('shop.connections','provider.ogp','product.gameserver') AND enabled=1").fetchone()[0]==3,'provider/product dependencies installed in order')
 check(sql.execute('SELECT id,username,email,password_hash FROM users ORDER BY id').fetchall()==before,'upgrade preserves administrator identities and hashes')
 check((private/'config/shop.key').read_bytes()==key,'upgrade preserves original shop encryption key')
 journal=json.loads((private/'upgrades/current.json').read_text());backup=Path(journal['backup']['file']);check(backup.exists() and hashlib.sha256(backup.read_bytes()).hexdigest()==journal['backup']['sha256'],'actual SQLite backup has verified checksum')
 check((private/'upgrades'/journal['id']/'config/fixture-operator.txt').read_text()=='Preserve operator private data','private operator configuration backed up outside webroot')
 # Request authentication and CSRF, then keep a real backend session for recovery.
 jar=http.cookiejar.CookieJar();opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
 def request(path,data=None):
  if isinstance(data,dict):data=urllib.parse.urlencode(data,doseq=True).encode()
  try:
   with opener.open(urllib.request.Request(f.base+path,data=data),timeout=20)as r:return r.status,r.read().decode()
  except urllib.error.HTTPError as e:return e.code,e.read().decode()
 check(request('/upgrade.php')[0] in (401,403),'recovery status requires backend administrator')
 check(request('/admin/einrichtung',{'action':'upgrade_apply'})[0]==403,'upgrade request without CSRF is rejected')
 _,body=request('/src/login.php');csrf=re.search(r'name="csrf_token" value="([a-f0-9]+)"',body)[1];request('/src/login.php',{'action':'login','username':'test-admin','password':'Admin fixture password 123','csrf_token':csrf})
 code,body=request('/admin/einrichtung');check(code==200 and 'Einrichtungsstatus' in body,'administrator sees onboarding status')
 check('Worker innerhalb der letzten drei Minuten aktiv' in body and 'Offen' in body,'missing scheduler is reported honestly')
 code,body=request('/admin/gameserver');check(code==200 and 'Gameserverangebote' in body,'gameserver administration opens without configured provider')
 code,body=request('/gameserver');check(code==200,'empty gameserver catalog renders')
 # Actual SMTP transaction to own stored administrator address; posted recipient is ignored.
 probe=f.tmp/'smtp.php';probe.write_text('<?php require '+repr(str(f.app/'framework.php'))+';require '+repr(str(f.app/'src/sys.conf.php'))+';SMF\\Core\\ProviderAccounts::bootstrapShop();$u=one("SELECT * FROM users WHERE username=?",["test-admin"]);Identity::login("backend",$u);save_smtp_settings(["enabled"=>1,"host"=>"localhost","port"=>getenv("SMF_SMTP_PORT"),"security"=>"ssl","username"=>"","password"=>"","from_email"=>"shop@example.invalid","from_name"=>"Fixture"]);$_POST["recipient"]="foreign@example.invalid";SMF\\Core\\Onboarding::testMail();echo json_encode(["accepted"=>true]);')
 r=subprocess.run(php_command()+[str(probe)],env=f.env,capture_output=True,text=True);check(r.returncode==0,'actual SMTPS onboarding test succeeds: '+r.stderr)
 check(len(f.smtp.messages)==1,'one actual test message accepted by local SMTP server')
 check('admin@example.invalid' in f.smtp.messages[0] and 'foreign@example.invalid' not in f.smtp.messages[0],'test recipient comes exclusively from own administrator record')
 # A trusted local module simulates interruption after DDL; its source remains unchanged on resume.
 folder=f.app/'extensions'/'fixture.upgrade';(folder/'migrations').mkdir(parents=True)
 manifest={'id':'fixture.upgrade','version':'1.0.0','name':'Upgrade fixture','core':'>=3.9.9 <4.0.0','php':'>=8.3.0','requires':{},'routes':{},'jobs':{},'permissions':[],'migrations':['migrations/001.php']}
 (folder/'module.json').write_text(json.dumps(manifest))
 (folder/'migrations/001.php').write_text("<?php return static function(PDO $db):void {$db->exec('CREATE TABLE IF NOT EXISTS fixture_upgrade (id INTEGER PRIMARY KEY)');if(!is_file(HttpSecurity::privateDirectory('config').'/fixture-continue'))throw new RuntimeException('Fixture interruption');};")
 state=cli('bin/upgrade.php','--modules=fixture.upgrade','--run');check(state['state']=='needs_review','interrupted migration retains recovery state')
 check((private/'updates/maintenance.json').exists(),'failed upgrade keeps maintenance active')
 check(request('/')[0]==503,'normal HTTP traffic stays blocked during recovery')
 code,body=request('/upgrade.php');check(code==200 and 'Wiederherstellung' in body,'authenticated recovery remains accessible during maintenance')
 (private/'config/fixture-continue').write_text('continue')
 state=cli('bin/upgrade.php','--resume='+state['id'],'--run');check(state['state']=='done','same reviewed module resumes after interruption')
 check(not(private/'updates/maintenance.json').exists(),'successful resume releases maintenance')
 check(sql.execute('SELECT id,username,email,password_hash FROM users ORDER BY id').fetchall()==before,'resume never resets administrators')
 # A second failed migration is restored from the actual native snapshot.
 folder=f.app/'extensions'/'fixture.rollback';(folder/'migrations').mkdir(parents=True)
 manifest['id']='fixture.rollback';(folder/'module.json').write_text(json.dumps(manifest));(folder/'migrations/001.php').write_text("<?php return static function(PDO $db):void {$db->exec('CREATE TABLE fixture_unfinished (id INTEGER PRIMARY KEY)');throw new RuntimeException('Fixture rollback');};")
 state=cli('bin/upgrade.php','--modules=fixture.rollback','--run');check(state['state']=='needs_review','rollback case captures its own backup before DDL')
 sql.close();state=cli('bin/upgrade.php','--restore='+state['id'],'--run');check(state['state']=='restored','native backup restoration completes')
 sql=sqlite3.connect(f.tmp/'application.sqlite');check(sql.execute("SELECT COUNT(*) FROM sqlite_master WHERE name='fixture_unfinished'").fetchone()[0]==0,'restoration removes newly introduced failed module table')
 check(sql.execute('SELECT id,username,email,password_hash FROM users ORDER BY id').fetchall()==before,'restored database retains original account hashes')
 check((private/'config/shop.key').read_bytes()==key,'restoration retains private encryption key')
 sql.close()
print(json.dumps({'checks':checks,'failed':0,'actual_sqlite_backup_restore':True,'actual_smtps':True}))
