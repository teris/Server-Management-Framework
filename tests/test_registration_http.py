import http.cookiejar,json,re,sqlite3,urllib.error,urllib.parse,urllib.request
from http_fixture import HTTPFixture
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs):return None
class Client:
    def __init__(self,base):self.base=base;self.jar=http.cookiejar.CookieJar();self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar),NoRedirect())
    def request(self,path,data=None,headers=None):
        request=urllib.request.Request(self.base+path,data=urllib.parse.urlencode(data).encode() if data is not None else None,headers=headers or {})
        try:r=self.opener.open(request,timeout=20)
        except urllib.error.HTTPError as e:r=e
        return r.code,r.read().decode()
    def token(self,path):
        status,body=self.request(path);assert status==200,(status,body[:150]);match=re.search(r'name="(?:csrf-token|csrf_token)" (?:content|value)="([a-f0-9]+)"',body);assert match,'Missing CSRF';return match[1]
checks=0
def check(ok,name):
    global checks;checks+=1;assert ok,name
with HTTPFixture() as f:
    client=Client(f.base);db=sqlite3.connect(f.tmp/'application.sqlite')
    check('Admin fixture password' not in f.install_output,'documented installer does not print password')
    check(db.execute('SELECT COUNT(*) FROM users').fetchone()[0]==1,'documented empty install creates one explicit administrator')
    check(client.request('/public/register.php',{'first_name':'No token'})[0]==403,'registration requires CSRF')
    token=client.token('/public/register.php');data={'csrf_token':token,'first_name':'New','last_name':'Customer','email':'new@example.invalid','password':'Registration test password','confirm_password':'Registration test password','accept_terms':'1'}
    status,body=client.request('/public/register.php',data,{'Host':'attacker.invalid'});check(status==200 and 'Konto angelegt' in body,'real HTTP registration succeeds')
    check(len(f.smtp.messages)==1,'one message accepted by real local SMTPS server')
    message=f.smtp.messages[-1];check('Registration test password' not in message and 'attacker.invalid' not in message,'email contains neither password nor request-host link')
    link=re.search(r'https://portal\.example\.invalid/public/verify\.php\?token=([a-f0-9]{64})',message);check(bool(link),'verification link uses configured public URL');token=link[1]
    row=db.execute("SELECT status FROM customers WHERE email='new@example.invalid'").fetchone();check(row[0]=='pending','signup stays pending until confirmation')
    status,body=client.request('/public/verify.php?token='+token);check(status==200 and 'E-Mail-Adresse bestätigen' in body,'email GET opens confirmation form')
    check(db.execute("SELECT status FROM customers WHERE email='new@example.invalid'").fetchone()[0]=='pending','mail scanner GET cannot activate an account')
    csrf=client.token('/public/verify.php?token='+token);status,body=client.request('/public/verify.php',{'csrf_token':csrf,'token':token});check(status==200 and 'ist bestätigt' in body,'explicit protected confirmation activates account')
    status,body=client.request('/public/verify.php',{'csrf_token':csrf,'token':token});check('bereits verwendet' in body,'second confirmation is not applied twice')
    csrf=client.token('/public/login.php');status,_=client.request('/public/login.php',{'csrf_token':csrf,'email':'new@example.invalid','password':'Registration test password','action':'login'});check(status==303,'new customer can login after activation')
    check(client.request('/public/dashboard.php')[0]==200,'fresh customer dashboard renders on installed schema')
    csrf=client.token('/public/reset-password.php');status,body=client.request('/public/reset-password.php',{'csrf_token':csrf,'email':'new@example.invalid','realm':'customer'});check(status==200 and 'Falls ein aktives Konto' in body,'password reset request gives generic account response')
    reset=re.search(r'reset-password\.php\?token=([a-f0-9]{64})',f.smtp.messages[-1]);check(bool(reset),'reset link delivered through SMTPS')
    csrf=client.token('/public/reset-password.php?token='+reset[1]);status,body=client.request('/public/reset-password.php',{'csrf_token':csrf,'token':reset[1],'password':'Changed customer password','confirm_password':'Changed customer password'});check(status==200 and 'Passwort geändert' in body,'reset completes through HTTP')
    check(client.request('/public/profile.php')[0]==303,'password reset invalidates previous customer session')
    csrf=client.token('/public/resend-verification.php');unknown=client.request('/public/resend-verification.php',{'csrf_token':csrf,'email':'unknown@example.invalid'})[1];check('Falls ein unbestätigtes Konto' in unknown,'resend response does not reveal whether an email exists')
    # Installer/migration commands are restart-safe and do not overwrite a configured database.
    import subprocess
    from php_runtime import php_command
    again=subprocess.run(php_command()+[str(f.app/'bin/install.php'),'--username=other-admin','--email=other@example.invalid'],input='Other fixture password\n',text=True,env=f.env,capture_output=True);check(again.returncode!=0 and db.execute('SELECT COUNT(*) FROM users').fetchone()[0]==1,'installer refuses to overwrite existing installation')
    migration=subprocess.run(php_command()+[str(f.app/'bin/migrate.php')],env=f.env,capture_output=True,text=True);check(migration.returncode==0 and not json.loads(migration.stdout)['changed'],'documented upgrade command is idempotent')
    guest=Client(f.base);check(guest.request('/src/api/updates.php')[0] in [401,403],'update status requires administrator identity')
    admin=Client(f.base);csrf=admin.token('/src/login.php');status,_=admin.request('/src/login.php',{'csrf_token':csrf,'username':'test-admin','password':'Admin fixture password 123','action':'login'});check(status==303,'administrator login for update status')
    check(admin.request('/src/api/updates.php')[0]==200,'administrator can read update jobs')
    check(admin.request('/src/api/updates.php',{'action':'stage','id':'a'*32})[0]==403,'update queue requires CSRF')
    marker=f.tmp/'private/updates/maintenance.json';marker.write_text(json.dumps({'id':'a'*32,'since':0}))
    check(admin.request('/src/index.php')[0]==503,'maintenance blocks normal HTTP traffic')
    check(admin.request('/src/api/updates.php')[0]==200,'maintenance retains authenticated recovery status')
    marker.unlink()
    db.close()
print(json.dumps({'checks':checks,'failed':0,'mail_transport':'real local SMTPS','live_provider_mutations':0}))
