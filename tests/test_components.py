import base64,hashlib,json,re,sqlite3,subprocess,tempfile,zipfile,os
from pathlib import Path
from cryptography.hazmat.primitives import hashes,serialization
from cryptography.hazmat.primitives.asymmetric import padding,rsa
from php_runtime import php_command,fixture_environment
ROOT=Path(__file__).resolve().parents[1];checks=0

def check(ok,name):
 global checks
 checks+=1
 if not ok:raise AssertionError(name)

def run(app,arguments,data=None):
 return subprocess.run(php_command()+[str(app/'bin/components.php'),*arguments],input=data,text=True,capture_output=True,env=fixture_environment(),cwd=app)

with tempfile.TemporaryDirectory(prefix='smf-components-')as tmp:
 p=Path(tmp);key=rsa.generate_private_key(public_exponent=65537,key_size=3072);public=p/'release-public.pem';public.write_bytes(key.public_key().public_bytes(serialization.Encoding.PEM,serialization.PublicFormat.SubjectPublicKeyInfo));fingerprint=hashlib.sha256(key.public_key().public_bytes(serialization.Encoding.DER,serialization.PublicFormat.SubjectPublicKeyInfo)).hexdigest();commit='a'*40
 source={n:(ROOT/n).read_bytes()for n in json.loads((ROOT/'deploy/package-files.json').read_text())}
 version=re.findall(r"'version'\s*=>\s*'([^']+)'",source['src/sys.conf.php'].decode())[-1]
 groups={'framework.core':{n:d for n,d in source.items()if not n.startswith('extensions/') and not n.startswith('public/assets/shop/')},'commerce.shop':{n:d for n,d in source.items()if n.startswith('extensions/shop.') or n.startswith('public/assets/shop/')}}
 for mid in ['provider.ogp','product.gameserver','provider.ispconfig','product.webhosting','provider.proxmox','product.vps']:groups[mid]={n:d for n,d in source.items()if n.startswith('extensions/'+mid+'/')}
 archives={}
 def archive(identity,files=None,mutate=None,extra=None,name=None):
  files=dict(groups[identity]if files is None else files);modules={}
  for n,d in files.items():
   if re.fullmatch('extensions/[^/]+/module.json',n):
    m=json.loads(d);modules[m['id']]={k:m[k]for k in ['version','requires','core','php']}
  manifest={'format':2,'type':'component','id':identity,'version':version if identity in ['framework.core','commerce.shop']else modules[identity]['version'],'release_version':version,'commit':commit,'php_min':'8.3','core':'>='+version+' <4.0.0','modules':modules,'files':{n:hashlib.sha256(d).hexdigest()for n,d in files.items()}}
  if mutate:mutate(manifest)
  raw=json.dumps(manifest,separators=(',',':'),sort_keys=True).encode();sig=base64.b64encode(key.sign(raw,padding.PKCS1v15(),hashes.SHA256()));path=p/(name or identity+'.zip')
  with zipfile.ZipFile(path,'w',zipfile.ZIP_DEFLATED)as z:
   z.writestr('manifest.json',raw);z.writestr('manifest.sig',sig)
   for n,d in files.items():z.writestr(n,d)
   if extra:z.writestr(*extra)
  return path
 for identity in groups:archives[identity]=archive(identity)
 def options(ids):return ['--key='+str(public),'--fingerprint='+fingerprint,*['--package='+str(archives[i])for i in ids]]
 selected=['framework.core','commerce.shop','provider.ogp','product.gameserver'];arguments=options(selected)
 verified=run(ROOT,arguments+['--verify']);check(verified.returncode==0,'signed compatible dependency graph verifies: '+verified.stderr)
 check(run(ROOT,options(['framework.core','commerce.shop','product.gameserver'])+['--assemble='+str(p/'missing')]).returncode!=0 and not(p/'missing').exists(),'missing provider fails before any destination is created')
 check(run(ROOT,arguments+['--fingerprint='+'0'*64]).returncode!=0,'public key pin is mandatory')
 app=p/'game-shop';result=run(ROOT,arguments+['--assemble='+str(app)]);check(result.returncode==0,'fresh game-only assembly: '+result.stderr)
 check(not(app/'extensions/provider.ispconfig').exists() and not(app/'extensions/product.webhosting').exists() and not(app/'extensions/provider.proxmox').exists(),'game shop contains no ISPConfig or VPS files')
 check(json.loads((app/'deploy/package-files.json').read_text())==sorted(source_name for identity in selected for source_name in groups[identity]),'composed inventory contains exactly selected files')
 before=(app/'framework.php').read_bytes();check(run(ROOT,arguments+['--assemble='+str(app)]).returncode!=0 and(app/'framework.php').read_bytes()==before,'assembly never resets an existing directory')
 # Malformed signed packages still fail structural validation.
 bads=[('escape',lambda m:None,{'../outside.php':b'<?php'}),('private',lambda m:None,{'config/config.inc.php':b'<?php'}),('core-version',lambda m:m.update(core='>=99.0.0'),None)]
 for label,mutate,files in bads:
  bad=archive('framework.core',files=files,mutate=mutate,name='bad-'+label+'.zip');result=run(ROOT,['--key='+str(public),'--fingerprint='+fingerprint,'--package='+str(bad),'--verify']);check(result.returncode!=0,'signed invalid '+label+' rejected')
 duplicate=archive('framework.core',extra=('FRAMEWORK.php',b'tampered'),name='duplicate.zip');check(run(ROOT,['--key='+str(public),'--fingerprint='+fingerprint,'--package='+str(duplicate)]).returncode!=0,'case-colliding archive rejected')
 tampered=p/'tampered.zip'
 with zipfile.ZipFile(archives['provider.ogp'])as z,zipfile.ZipFile(tampered,'w')as out:
  for n in z.namelist():out.writestr(n,z.read(n)+(b'bad'if n.endswith('Client.php')else b''))
 check(run(ROOT,['--key='+str(public),'--fingerprint='+fingerprint,'--package='+str(tampered)]).returncode!=0,'tampered executable rejected before extraction')
 # Real guided installation and worker use the physically reduced assembled application.
 def cli(arguments,data=None):return subprocess.run(php_command()+arguments,text=True,input=json.dumps(data)if isinstance(data,dict)else data,capture_output=True,env=fixture_environment(),cwd=app)
 prepared=cli([str(app/'bin/setup.php'),'--prepare']);check(prepared.returncode==0,'assembled game-shop installer prepares')
 token=re.search(r'SMF_SETUP_TOKEN: ([a-f0-9]{64})',(app/'config/setup-access.php').read_text())[1]
 request={'setup_token':token,'private_dir':str(p/'game-private'),'db_type':'sqlite','site_url':'https://shop.example.invalid','site_name':'Game Shop','support_email':'support@example.invalid','system_email':'system@example.invalid','admin_name':'fixture-admin','admin_email':'admin@example.invalid','admin_password':'Installer fixture password 123','modules':['product.gameserver','shop.communications']}
 installed=cli([str(app/'bin/setup.php'),'--install'],request);check(installed.returncode==0,'game-shop installs without ISPConfig: '+installed.stderr)
 worker=cli([str(app/'bin/worker.php'),'--limit=20']);check(worker.returncode==0,'reduced shop worker runs without missing product functions: '+worker.stderr)
 # Install two signed optional modules into the existing shop, preserving identity and key material.
 keyfile=p/'game-private/config/shop.key';secret=keyfile.read_bytes();dbfile=next((p/'game-private').rglob('*.sqlite'));db=sqlite3.connect(dbfile);admin_count=db.execute('SELECT COUNT(*) FROM users').fetchone()[0];db.close()
 result=run(app,options(['provider.ispconfig','product.webhosting'])+['--install']);check(result.returncode==0,'verified optional modules install with native backup: '+result.stderr)
 check(keyfile.read_bytes()==secret,'module install preserves shared commerce key')
 db=sqlite3.connect(dbfile);check(db.execute('SELECT COUNT(*) FROM users').fetchone()[0]==admin_count,'module install preserves existing admin identity');check(db.execute("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='shop_hosting_children'").fetchone()[0]==1,'webhosting module owns its independently installed schema');db.close()
 # A signed migration that fails is rolled back together with source and DB.
 files=dict(groups['product.webhosting']);manifest=json.loads(files['extensions/product.webhosting/module.json']);manifest['version']='1.0.1';manifest['migrations'].append('migrations/002.php');files['extensions/product.webhosting/module.json']=json.dumps(manifest).encode();files['extensions/product.webhosting/migrations/002.php']=b'<?php return static function(PDO $db):void {$db->exec("CREATE TABLE failed_component_fixture(id INT)");throw new RuntimeException("fixture failed migration");};';bad=archive('product.webhosting',files=files,name='failed-migration.zip')
 result=run(app,['--key='+str(public),'--fingerprint='+fingerprint,'--package='+str(bad),'--install']);check(result.returncode!=0,'failed module migration reports rollback');check(json.loads((app/'extensions/product.webhosting/module.json').read_text())['version']=='1.0.0','failed update restores previous source');db=sqlite3.connect(dbfile);check(db.execute("SELECT COUNT(*) FROM sqlite_master WHERE name='failed_component_fixture'").fetchone()[0]==0,'failed update restores native DB snapshot');db.close();check(not(p/'game-private/updates/maintenance.json').exists(),'successful rollback clears maintenance')
 # A hosting-only package does not carry OGP or gameserver source.
 host=p/'hosting-shop';result=run(ROOT,options(['framework.core','commerce.shop','provider.ispconfig','product.webhosting'])+['--assemble='+str(host)]);check(result.returncode==0 and not(host/'extensions/provider.ogp').exists(),'hosting-only signed assembly excludes OGP files')
 prepared=cli([str(host/'bin/setup.php'),'--prepare']);check(prepared.returncode==0,'hosting-only installer prepares');token=re.search(r'SMF_SETUP_TOKEN: ([a-f0-9]{64})',(host/'config/setup-access.php').read_text())[1];request.update(setup_token=token,private_dir=str(p/'hosting-private'),modules=['product.webhosting']);result=cli([str(host/'bin/setup.php'),'--install'],request);check(result.returncode==0,'hosting-only shop installs without OGP: '+result.stderr)
print(json.dumps({'checks':checks,'failed':0,'real_provider_calls':0,'package_scope':'signed reduced assemblies and native SQLite module rollback'}))
