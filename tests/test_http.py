import contextlib, http.cookiejar, http.server, json, os, re, shutil, socket, sqlite3, ssl, subprocess, tempfile, threading, time, urllib.error, urllib.parse, urllib.request
from pathlib import Path
from php_runtime import php_command, fixture_environment
from tls_helpers import certificate_files

ROOT=Path(__file__).resolve().parents[1]
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs):return None
class Client:
    def __init__(self,base):
        self.base=base;self.jar=http.cookiejar.CookieJar();self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar),NoRedirect())
    def request(self,path,method='GET',data=None,headers=None):
        body=urllib.parse.urlencode(data).encode() if data is not None else None
        request=urllib.request.Request(self.base+path,data=body,method=method,headers=headers or {})
        try:r=self.opener.open(request,timeout=15)
        except urllib.error.HTTPError as e:r=e
        return r.code,r.read().decode('utf-8',errors='replace'),r.headers
    def token(self,path='/public/login.php'):
        code,body,_=self.request(path);assert code==200,(code,body[:100])
        match=re.search(r'name="csrf-token" content="([a-f0-9]+)"',body);assert match,'Missing CSRF metadata'
        return match[1]
    def sid(self):return next((c.value for c in self.jar if c.name=='server_mgmt_session'),None)
    def login(self,realm,name,password,remember=False):
        path='/src/login.php' if realm=='backend' else '/public/login.php';token=self.token(path)
        data={'csrf_token':token,'password':password,'action':'login',('username' if realm=='backend' else 'email'):name}
        if remember:data['remember']='1'
        return self.request(path,'POST',data)

class Provider(http.server.BaseHTTPRequestHandler):
    calls=[]
    def dispatch(self):
        self.calls.append((self.command,self.path))
        if self.path=='/api2/json/access/ticket':body={'data':{'ticket':'FAKE-sensitive-ticket','CSRFPreventionToken':'FAKE-sensitive-csrf'}}
        elif self.path.endswith('/config'):body={'data':{'vmgenid':'00000000-0000-0000-0000-000000000101'}}
        elif self.path.endswith('/status/current'):body={'data':{'status':'running','vmid':101,'password':'FAKE-response-secret'}}
        elif self.path.endswith('/status/start'):body={'data':'UPID:isolated-test-task'}
        else:body={'data':[]}
        self.send_response(200);self.send_header('Content-Type','application/json');self.end_headers();self.wfile.write(json.dumps(body).encode())
    do_GET=dispatch;do_POST=dispatch
    def log_message(self,*args):pass

checks=[]
def check(ok,name):checks.append(name);assert ok,name
with tempfile.TemporaryDirectory(prefix='smf-http-') as tmp:
    tmp=Path(tmp);project=tmp/'app';shutil.copytree(ROOT,project,ignore=shutil.ignore_patterns('.git','.codex','node_modules','vendor','config.inc.php','__pycache__'))
    certs=certificate_files(tmp/'certs');provider=http.server.HTTPServer(('127.0.0.1',0),Provider)
    context=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER);context.load_cert_chain(str(certs/'valid.pem'),str(certs/'valid.key'))
    provider.socket=context.wrap_socket(provider.socket,server_side=True);threading.Thread(target=provider.serve_forever,daemon=True).start()
    configuration=(project/'config/config.example.php').read_text();configuration=re.sub(r"const DB_TYPE\s*=\s*'[^']+';","const DB_TYPE = 'sqlite';",configuration)
    configuration=re.sub(r'const DB_SQLITE_USEING\s*=\s*false;','const DB_SQLITE_USEING = true;',configuration)
    configuration=re.sub(r"const DB_SQLITE_PATH\s*=\s*'[^']+';","const DB_SQLITE_PATH = 'database/http-test.sqlite';",configuration)
    configuration=re.sub(r"const PROXMOX_HOST\s*=\s*'[^']+';",f"const PROXMOX_HOST = 'https://localhost:{provider.server_port}';",configuration)
    configuration=re.sub(r"const PROXMOX_PASSWORD\s*=\s*'[^']+';","const PROXMOX_PASSWORD = 'FAKE-request-secret';",configuration)
    configuration=re.sub(r'const PROXMOX_USEING\s*=\s*false;','const PROXMOX_USEING = true;',configuration)
    config=tmp/'config.php';config.write_text(configuration)
    env=fixture_environment(SMF_CONFIG_FILE=str(config),SMF_PRIVATE_DIR=str(tmp/'private'),SMF_CA_FILE=str(certs/'ca.pem'))
    hashes=json.loads(subprocess.check_output(php_command()+['-r',"echo json_encode([password_hash('Backend test password',PASSWORD_DEFAULT),password_hash('Customer test password',PASSWORD_DEFAULT)]);"],text=True,env=env))
    db=sqlite3.connect(project/'database/http-test.sqlite');db.executescript((project/'install/database-structure-install-sqlite.sql').read_text())
    for uid,username,role in [(1,'admin','admin'),(2,'user','user')]:db.execute("INSERT INTO users (id,username,email,password_hash,full_name,role,active) VALUES (?,?,?,?,?,?,'y')",(uid,username,username+'@example.invalid',hashes[0],username,role))
    for cid in [1,2]:db.execute("INSERT INTO customers (id,first_name,last_name,email,password_hash,status) VALUES (?,?,?,?,?,'active')",(cid,'Customer',str(cid),f'{cid}@example.invalid',hashes[1]))
    db.commit()
    subprocess.run(php_command()+[str(project/'bin/security-migrate.php')],env=env,check=True,capture_output=True)
    fingerprint=json.loads(subprocess.check_output(php_command()+['-r',f"require {json.dumps(str(project/'framework.php'))};require {json.dumps(str(project/'src/core/ResourceAccess.php'))};echo json_encode(ResourceAccess::connectionId('proxmox'));"],env=env,text=True))
    assignment='a'*32;db.execute('INSERT INTO security_resource_assignments (id,subject_realm,subject_id,provider,connection_id,resource_type,external_id,metadata,can_write) VALUES (?,?,?,?,?,?,?,?,?)',(assignment,'customer',1,'proxmox',fingerprint,'qemu','101',json.dumps({'node':'node-a','instance_id':'00000000-0000-0000-0000-000000000101'}),1));db.commit()
    sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close();log=(tmp/'server.log').open('w+')
    server=subprocess.Popen(php_command()+['-d','log_errors=1','-d','display_errors=0','-S',f'127.0.0.1:{port}','-t',str(project)],cwd=project,env=env,stdout=log,stderr=log)
    try:
        client=Client(f'http://127.0.0.1:{port}')
        for _ in range(100):
            try:client.request('/public/login.php');break
            except urllib.error.URLError:time.sleep(.05)
        for path in ['/src/debug/index.php','/src/debug.php','/src/info.php','/src/auth.php','/src/install.php?force_install=true','/src/setup.php','/src/ajax_ip_mac_reverse.php','/src/inc/manualupdater.php','/src/module/file-editor/Module.php','/src/inc/users.php','/src/inc/users.php/path-info','/src/module/proxmox/templates/main.php/path-info','/test.php']:
            check(client.request(path)[0]==404,'direct execution blocked: '+path)
        check(client.request('/src/api/status.php')[0]==401,'internal status requires authentication')
        check(client.request('/src/download_zone.php?file=unrelated.txt')[0]==401,'temporary-file filename is not a public download')
        check(not Provider.calls,'anonymous requests never initialize providers')
        token=client.token();before=client.sid()
        check(client.request('/public/login.php','POST',{'email':'1@example.invalid','password':'Customer test password'})[0]==403,'password login rejects missing CSRF token')
        check(client.request('/public/contact.php','POST',{'csrf_token':'forged'})[0]==403,'public contact requires valid CSRF token')
        check(client.login('customer','1@example.invalid','Customer test password',True)[0]==303,'customer password login succeeds')
        check(client.sid()!=before,'customer HTTP login rotates session cookie')
        check(client.request('/public/profile.php','POST',{'csrf_token':token,'email':'changed@example.invalid','full_name':'Changed'})[0]==403,'pre-login CSRF token invalid after login')
        code,body,_=client.request(f'/src/api/resources.php?module=proxmox&id={assignment}');check(code==200 and json.loads(body)['data']['status']=='running','owner can read assigned VM through real provider adapter')
        check('FAKE-response-secret' not in body,'provider secret not exposed to owner')
        current=client.token('/public/profile.php')
        code,body,_=client.request('/src/api/resources.php','POST',{'module':'proxmox','id':assignment,'action':'start','csrf_token':current,'node':'wrong-node','vmid':999})
        check(code==200 and json.loads(body)['data']['accepted'],'authorized owner can request VM start')
        check(Provider.calls[-1]==('POST','/api2/json/nodes/node-a/qemu/101/status/start'),'forged node and VMID cannot change remote target')
        other=Client(client.base);check(other.login('customer','2@example.invalid','Customer test password')[0]==303,'second independent customer can login')
        prior=len(Provider.calls);check(other.request(f'/src/api/resources.php?module=proxmox&id={assignment}')[0]==404,'second customer cannot view first customer VM')
        othertoken=other.token('/public/profile.php');check(other.request('/src/api/resources.php','POST',{'module':'proxmox','id':assignment,'action':'stop','csrf_token':othertoken})[0]==404,'second customer cannot control first customer VM')
        check(len(Provider.calls)==prior,'foreign requests do not contact provider')
        backend=Client(client.base);check(backend.login('backend','user','Backend test password')[0]==303,'normal backend login succeeds')
        prior=len(Provider.calls);check(backend.request('/src/index.php')[0]==403,'normal backend user denied global admin interface');check(len(Provider.calls)==prior,'admin rejection precedes provider initialization')
        check(backend.request('/src/account.php')[0]==200,'normal backend user receives scoped account page')
        administrator=Client(client.base);check(administrator.login('backend','admin','Backend test password')[0]==303,'administrator can login')
        count=len(Provider.calls);check(administrator.request('/src/index.php?option=manualupdater')[0]==200,'administrator receives signature-gated update interface')
        csrf=administrator.token('/src/password_change.php')
        sessionfile=tmp/'private/sessions'/('sess_'+administrator.sid())
        serialized=sessionfile.read_text();serialized=re.sub(r'("last_activity";i:)\d+(;)',lambda m:m[1]+str(int(time.time())-120)+m[2],serialized,count=1);sessionfile.write_text(serialized)
        status,body,_=administrator.request('/src/index.php?heartbeat=1');remaining=json.loads(body)
        check(status==200 and remaining['remaining']<=remaining['timeout']-119,'heartbeat reports actual idle expiry without extending it')
        status,body,_=administrator.request('/src/index.php','POST',{'action':'extend_session','csrf_token':csrf});extended=json.loads(body)
        check(status==200 and extended['remaining']>=extended['timeout']-1,'explicit user activity extends server session')
        check(len(Provider.calls)==count,'session management does not initialize providers')
        token=administrator.token('/src/password_change.php'); status,body,_=administrator.request('/src/index.php','POST',{'plugin':'proxmox','action':'getContent','csrf_token':token}); check(status==200 and isinstance(json.loads(body).get('content'),str) and 'proxmox-content' in json.loads(body)['content'],'Proxmox rendering returns a string inside valid JSON')
        check(client.request('/public/support.php?debug=1')[0]==404,'customer cannot enable internal support diagnostics')
        check(client.request('/public/dashboard.php?logout=1')[0]==405,'GET cannot log out customer')
        current=client.token('/public/profile.php');check(client.request('/public/dashboard.php','POST',{'action':'logout','csrf_token':current})[0]==303,'POST logout succeeds')
        check(client.request('/public/profile.php')[0]==303,'logout invalidates customer session')
        check(client.request('/public/profile.php/path-info')[0]==303,'PATH_INFO cannot bypass protected customer page')
        # A deliberate account and IP bucket shares limits across fresh sessions.
        limiter=Client(client.base)
        for attempt in range(5):
            csrf=limiter.token();status=limiter.request('/public/login.php','POST',{'email':'unknown@example.invalid','password':'wrong','csrf_token':csrf})[0]
            if status==429:break
        check(status==429,'login limit persists across browser sessions')
        csrf=limiter.token('/public/login.php/path-info')
        check(limiter.request('/public/login.php/path-info','POST',{'email':'unknown@example.invalid','password':'wrong','csrf_token':csrf})[0]==429,'PATH_INFO cannot bypass login rate limit')
        log.flush();contents=(tmp/'server.log').read_text();check(all(x not in contents for x in ['FAKE-request-secret','FAKE-sensitive-ticket','FAKE-sensitive-csrf','FAKE-response-secret']),'server logs contain no fixture provider credentials')
        print(json.dumps({'checks':len(checks),'failed':0,'provider_calls':len(Provider.calls)}))
    except Exception:
        log.flush();print((tmp/'server.log').read_text()[-5000:]);raise
    finally:
        server.terminate();server.wait(timeout=5);log.close();db.close();provider.shutdown();provider.server_close()
