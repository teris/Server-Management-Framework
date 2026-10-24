"""Local negative control: reviewed 3.2.7 must fail these security expectations."""
import io, json, os, re, socket, subprocess, tarfile, tempfile, time, urllib.error, urllib.request, urllib.parse
from pathlib import Path
from php_runtime import php_command
ROOT=Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='smf-baseline-') as directory:
    tmp=Path(directory);source=tmp/'app';source.mkdir()
    archive=subprocess.check_output(['git','archive','3.2.7'],cwd=ROOT)
    with tarfile.open(fileobj=io.BytesIO(archive)) as tree:
        for member in tree.getmembers():
            if member.name.startswith('/') or '..' in Path(member.name).parts:raise RuntimeError('Unsafe archive member')
        tree.extractall(source,filter='data')
    config=(source/'config/config.inc.php').read_text();config=re.sub(r"const DB_TYPE\s*=\s*'[^']+';","const DB_TYPE = 'sqlite';",config)
    config=re.sub(r'const DB_SQLITE_USEING\s*=\s*false;','const DB_SQLITE_USEING = true;',config)
    for provider in ['PROXMOX','ISPCONFIG','OVH','OGP']:config=re.sub(r'(const '+provider+r'_USEING\s*=\s*)true;',r'\g<1>false;',config,flags=re.I)
    (source/'config/config.inc.php').write_text(config)
    sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close()
    log=(tmp/'server.log').open('w');server=subprocess.Popen(php_command()+['-S',f'127.0.0.1:{port}','-t',str(source)],cwd=source,stdout=log,stderr=log)
    fd,marker=tempfile.mkstemp(prefix='smf-negative-control-',suffix='.txt');os.write(fd,b'isolated marker');os.close(fd)
    try:
        def request(path,data=None):
            req=urllib.request.Request(f'http://127.0.0.1:{port}'+path,data=urllib.parse.urlencode(data).encode() if data else None)
            try:r=urllib.request.urlopen(req,timeout=10)
            except urllib.error.HTTPError as e:r=e
            return r.code,r.read().decode(errors='replace')
        for _ in range(100):
            try:request('/public/login.php');break
            except urllib.error.URLError:time.sleep(.05)
        results=[]
        for name,path,data,expected in [
            ('diagnostic page must be closed','/src/debug/index.php',None,404),
            ('updater must reject anonymous request','/src/inc/manualupdater.php',{'action':'system_info'},404),
            ('internal status must require login','/src/api/status.php',None,401),
            ('public form must require CSRF','/public/contact.php',{'name':''},403),
            ('temporary file must require owner','/src/download_zone.php?file='+Path(marker).name,None,401),
        ]:
            status,body=request(path,data);results.append({'check':name,'expected':expected,'baseline_status':status,'passes':status==expected})
        assert sum(not x['passes'] for x in results)==len(results),results
        print(json.dumps({'negative_control':'tag 3.2.7','security_checks':results,'expected_failures':len(results)},indent=2))
    finally:
        server.terminate();server.wait(timeout=5);log.close()
        if os.path.exists(marker):os.unlink(marker)
