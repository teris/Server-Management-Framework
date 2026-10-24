import hashlib,json,os,re,shutil,socket,subprocess,tempfile,time,urllib.request
from pathlib import Path
from php_runtime import php_command,fixture_environment
ROOT=Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='smf-setup-browser-')as directory:
 tmp=Path(directory);app=tmp/'app';app.mkdir()
 for name in json.loads((ROOT/'deploy/package-files.json').read_text()):
  destination=app/name;destination.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(ROOT/name,destination)
 env=fixture_environment();result=subprocess.run(php_command()+[str(app/'bin/setup.php'),'--prepare'],cwd=app,env=env,capture_output=True,text=True)
 if result.returncode:raise RuntimeError('Bootstrap preparation failed')
 token=re.search(r'SMF_SETUP_TOKEN: ([A-Za-z0-9_-]+)',(app/'config/setup-access.php').read_text())[1];sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close();base='http://127.0.0.1:'+str(port)
 with(tmp/'server.log').open('w')as log:
  process=subprocess.Popen(php_command()+['-d','display_errors=0','-S','127.0.0.1:'+str(port),'-t',str(app)],cwd=app,env=env,stdout=log,stderr=log)
  try:
   for _ in range(80):
    try:urllib.request.urlopen(base+'/setup.php',timeout=1).read();break
    except OSError:time.sleep(.05)
   options={'url':base,'module':os.environ.get('SMF_PLAYWRIGHT_MODULE'),'browser':os.environ.get('SMF_BROWSER_BIN'),'private':str(tmp/'private'),'token':token,'password':'Browser installer fixture password 123'}
   r=subprocess.run([os.environ.get('NODE_BIN','node'),'tests/setup_browser.cjs'],cwd=ROOT,input=json.dumps(options),capture_output=True,text=True)
   if r.returncode:raise RuntimeError(r.stderr)
   print(json.dumps(json.loads(r.stdout)))
  finally:
   process.terminate();process.wait(timeout=5);state_dir=Path(tempfile.gettempdir())/('smf-setup-'+hashlib.sha256(str(app/'config/setup-access.php').encode()).hexdigest()[:24]);shutil.rmtree(state_dir,ignore_errors=True)
