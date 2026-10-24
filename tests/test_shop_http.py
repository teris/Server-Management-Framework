import json,re,subprocess,urllib.request,urllib.error,http.cookiejar,sqlite3
from http_fixture import HTTPFixture
from php_runtime import php_command
checks=0

def check(condition,name):
    global checks
    checks+=1
    if not condition:raise AssertionError(name)

with HTTPFixture(shop_routes=True,config_storage=True) as f:
    result=subprocess.run(php_command()+[str(f.app/'bin/modules.php'),'--shop'],cwd=f.app,env=f.env,capture_output=True,text=True)
    check(result.returncode==0,'shop installation '+result.stderr)
    jar=http.cookiejar.CookieJar();opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
    def request(path,data=None):
        if isinstance(data,dict):data=urllib.parse.urlencode(data).encode()
        try:
            response=opener.open(urllib.request.Request(f.base+path,data=data),timeout=20);return response.status,response.read().decode(),response.headers
        except urllib.error.HTTPError as error:return error.code,error.read().decode(),error.headers
    for path in ['/','/produkte','/konfigurator','/login','/registrieren','/hilfe','/faq','/module','/kuendigen']:
        status,body,headers=request(path);check(status==200,path+' returns '+str(status))
        check('Fatal error' not in body and 'Warning:' not in body,path+' has no PHP diagnostic')
    status,body,headers=request('/public/status.php');check(set(json.loads(body))=={'status','checked_at'},'public status discloses no infrastructure')
    status,body,headers=request('/admin');check('/admin/login' in headers.get('Content-Location','') or 'Administration' in body or 'Anmelden' in body,'shop admin requires login')
    check(request('/src/modules.php')[0] in [401,403],'guest cannot manage modules')
    check(request('/api/paypal/webhook',b'{}')[0] in [400,403,404,503],'unsigned webhook cannot book payment')
    check(request('/produkte',{'action':'request'})[0]==403,'shop POST requires shared CSRF')
    for path in ['/extensions/shop.storage/legacy/bootstrap.php','/src/kernel/ShopSettings.php','/config/config.inc.php']:
        check(request(path)[0] in [403,404],path+' denied')
    status,body,_=request('/src/login.php');token=re.search(r'name="csrf_token" value="([a-f0-9]+)"',body)[1]
    status,body,_=request('/src/login.php',{'action':'login','username':'test-admin','password':'Admin fixture password 123','csrf_token':token})
    check(status==200,'shared backend login')
    status,body,_=request('/admin');check(status==200 and 'Shop-Übersicht' in body,'core backend session opens shop administration')
    for admin_path in ['/admin/produkte','/admin/anfragen','/admin/kunden','/admin/support','/admin/schnittstellen','/admin/automatisierung','/admin/rechnungen','/admin/vertraege','/admin/gutscheine','/admin/module','/admin/nachrichten','/admin/dokumente']:
        check(request(admin_path)[0]==200,'admin page with empty initial data '+admin_path)
    status,body,_=request('/src/modules.php');check(status==200 and 'shop.payments' in body,'core administrator manages optional modules')
    token=re.search(r'name="csrf_token" value="([a-f0-9]+)"',body)[1]
    status,body,_=request('/src/modules.php',{'action':'disable','module':'shop.promotions','csrf_token':token});check(status==200,'disable optional promotions')
    check(request('/admin/gutscheine')[0]==404,'disabled module route absent')
    status,body,_=request('/admin');check('href="/admin/gutscheine"' not in body,'disabled optional menu item hidden')
    # Request mode works without payment configuration and has shared customer identity.
    db=sqlite3.connect(f.tmp/'application.sqlite')
    password=subprocess.check_output(php_command()+['-r',"echo password_hash('Customer fixture password 1',PASSWORD_DEFAULT);"],text=True).strip()
    db.execute("INSERT INTO customers(first_name,last_name,email,password_hash,status) VALUES('Test','Customer','buyer@example.invalid',?,'active')",[password]);db.commit()
    status,body,_=request('/login');token=re.search(r'name="csrf_token" value="([a-f0-9]+)"',body)[1]
    status,body,_=request('/login',{'action':'login','email':'buyer@example.invalid','password':'Customer fixture password 1','csrf_token':token});check(status==200 and 'Deine Übersicht' in body,'shop login opens shared customer session')
    status,body,_=request('/public/dashboard.php');check(status==200 and 'buyer@example.invalid' in body or status==200 and 'Test' in body,'same session opens framework customer dashboard')
    status,body,_=request('/konto');token=re.search(r'name="csrf_token" value="([a-f0-9]+)"',body)[1]
    slug=db.execute("SELECT slug FROM shop_products WHERE category='hosting' AND active=1 LIMIT 1").fetchone()[0]
    status,body,_=request('/konfigurieren?produkt='+slug,{'action':'request','product':slug,'domain':'','notes':'Fixture request','csrf_token':token,'price_cents':'1'})
    check(status==200 and db.execute('SELECT COUNT(*) FROM shop_requests').fetchone()[0]==1,'request without payment provider succeeds')
    check(db.execute('SELECT price_cents FROM shop_requests').fetchone()[0] is None,'posted price cannot replace server catalog price')
    status,body,_=request('/konto/support');token=re.search(r'name="csrf_token" value="([a-f0-9]+)"',body)[1]
    status,body,_=request('/konto/support',{'action':'ticket','subject':'Fixture support','message':'Visible customer message','category':'general','priority':'medium','csrf_token':token})
    ticket=db.execute('SELECT MAX(id) FROM support_tickets').fetchone()[0];check(status==200 and ticket,'customer creates own support ticket')
    status,body,_=request('/admin/ticket?id='+str(ticket));token=re.search(r'name="csrf_token" value="([a-f0-9]+)"',body)[1]
    status,body,_=request('/admin/ticket?id='+str(ticket),{'action':'admin_reply','id':ticket,'message':'PRIVATE-INTERNAL-FIXTURE','internal':'1','csrf_token':token})
    check(status==200 and 'PRIVATE-INTERNAL-FIXTURE' in body,'administrator sees the stored internal note')
    status,body,_=request('/konto/ticket?id='+str(ticket));check(status==200 and 'PRIVATE-INTERNAL-FIXTURE' not in body,'customer view hides internal note even with parallel backend session')
    token=re.search(r'name="csrf_token" value="([a-f0-9]+)"',body)[1]
    request('/konto/ticket?id='+str(ticket),{'action':'reply','id':ticket,'message':'Public reply','internal':'1','csrf_token':token})
    check(db.execute("SELECT is_internal FROM ticket_replies WHERE message='Public reply'").fetchone()[0]==0,'customer cannot forge an internal reply')
    db.execute("INSERT INTO customers(first_name,last_name,email,password_hash,status) VALUES('Other','Customer','other@example.invalid',?,'active')",[password]);db.commit()
    opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    status,body,_=request('/login');token=re.search(r'name="csrf_token" value="([a-f0-9]+)"',body)[1]
    request('/login',{'action':'login','email':'other@example.invalid','password':'Customer fixture password 1','csrf_token':token})
    check(request('/konto/ticket?id='+str(ticket))[0]==404,'second customer cannot read the first customer ticket')
    db.close()
print(json.dumps({'checks':checks,'failed':0}))
