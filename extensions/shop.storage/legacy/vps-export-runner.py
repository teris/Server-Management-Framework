#!/usr/bin/env python3
"""Private node-local VPS archives; no guest commands or customer supplied paths."""
import base64, fcntl, hashlib, hmac, http.server, json, re, shutil, ssl, subprocess, threading, time, urllib.request
from pathlib import Path

CONFIG = Path('/etc/orga-template-runner/config.json')
ROOT = Path('/var/lib/orga-template-runner/exports')

def command(args, timeout=120):
    return subprocess.run(args, check=True, timeout=timeout, stdin=subprocess.DEVNULL, stdout=subprocess.PIPE, stderr=subprocess.PIPE)

def pve(path):
    return json.loads(command(['pvesh', 'get', path, '--output-format', 'json']).stdout)

def callback(cfg, message):
    raw = json.dumps(dict(message, node=cfg['node'])).encode()
    req = urllib.request.Request(cfg['url'], data=raw, headers={'Authorization': 'Bearer '+cfg['token'], 'Content-Type': 'application/json'}, method='POST')
    with urllib.request.urlopen(req, timeout=50) as r:
        return json.load(r)

def atomic_json(path, value):
    temp = path.with_suffix('.tmp')
    temp.write_text(json.dumps(value)); temp.chmod(0o600); temp.replace(path)

def job_directory(job):
    if not isinstance(job.get('id'), int) or job['id'] < 1 or not re.fullmatch('[a-f0-9]{48}', job.get('claim','')):
        raise ValueError('Invalid archive claim')
    return ROOT/(str(job['id'])+'-'+job['claim'])

def guest_config(job, cfg):
    if job['node'] != cfg['node'] or job['pool'] != cfg['pool'] or job['guest_type'] not in ('lxc','qemu') or not isinstance(job['vmid'],int) or job['vmid'] < 100:
        raise ValueError('Invalid guest identity')
    kind = job['guest_type']; vmid = job['vmid']
    if not re.fullmatch(r'(?:Server Management|Orga Consult) [A-Za-z0-9_-]{1,100}',job['marker']):
        raise ValueError('Invalid order marker')
    pool = pve('/pools/'+cfg['pool'])
    if not any(int(m.get('vmid',0))==vmid and m.get('node')==cfg['node'] and m.get('type')==kind for m in pool.get('members',[])):
        raise ValueError('Guest outside shop pool')
    path = '/nodes/'+cfg['node']+'/'+kind+'/'+str(vmid)
    result = pve(path+'/config')
    if result.get('description','').rstrip('\r\n') != job['marker'] or pve(path+'/status/current').get('status') != 'stopped':
        raise ValueError('Guest not stopped or ownership changed')
    return result

def required_bytes(job, config):
    size = 0
    for key, value in config.items():
        disk = (job['guest_type']=='lxc' and re.fullmatch(r'(rootfs|mp\d+)',key)) or (job['guest_type']=='qemu' and re.fullmatch(r'((scsi|virtio|sata|ide)\d+|efidisk0|tpmstate0)',key))
        if not disk or 'media=cdrom' in value or 'cloudinit' in value: continue
        if 'backup=0' in value or (key.startswith('mp') and 'backup=1' not in value) or not re.match(r'^[\w.-]+:',value):
            raise ValueError('Guest has non-backed-up or external disks')
        match = re.search(r'(?:^|,)size=(\d+(?:\.\d+)?)([KMGT]?)(?:,|$)',value)
        if not match: raise ValueError('Unknown disk capacity')
        size += int(float(match[1])*{'':1,'K':1024,'M':1024**2,'G':1024**3,'T':1024**4}[match[2]])
    if size <= 0: raise ValueError('No disk found')
    return size

def archive_root(required, node):
    choices=[]
    for store in ('store01','store02'):
        info=pve('/nodes/'+node+'/storage/'+store+'/status')
        if not info.get('active') or not info.get('enabled') or 'backup' not in info.get('content','').split(','): continue
        storage=pve('/storage/'+store)
        if storage.get('type')!='dir' or not Path(storage.get('path','')).is_absolute(): continue
        root=Path(storage['path']).resolve()/'orga-shop-exports'
        # Reserve enough space for an incompressible full archive, with 5% headroom.
        headroom=max(1024**3,int(info['total']*.05))
        if int(info['avail']) >= required + max(required//10,1024**3) + headroom:
            choices.append((int(info['avail']),root))
    if not choices: raise ValueError('No safe space for a complete archive')
    root=max(choices,key=lambda c:c[0])[1];root.mkdir(mode=0o700,exist_ok=True);root.chmod(0o700);return root

def backup(cfg, job):
    directory=job_directory(job);directory.mkdir(mode=0o700,parents=True,exist_ok=True)
    manifest=directory/'manifest.json'
    if manifest.exists():
        data=json.loads(manifest.read_text())
        if data['claim']!=job['claim'] or data['id']!=job['id']: raise ValueError('Archive identity changed')
        if data.get('complete'):
            file=Path(data['path'])
            if not file.is_file() or file.stat().st_size!=data['size']: raise ValueError('Archive missing')
            return data
        # The shop only reissues an interrupted job after administrator review.
        # Discard only this claim's incomplete archive, never the guest disks.
        guest_config(job,cfg)
        partial=Path(data['directory'])
        if partial.name!=directory.name or partial.parent.name!='orga-shop-exports' or partial.is_symlink(): raise ValueError('Invalid partial directory')
        if partial.exists(): shutil.rmtree(partial)
        manifest.unlink()
    config=guest_config(job,cfg);size=required_bytes(job,config);root=archive_root(size,cfg['node'])
    target=root/(str(job['id'])+'-'+job['claim'])
    target.mkdir(mode=0o700,exist_ok=False)
    data={'id':job['id'],'claim':job['claim'],'directory':str(target),'complete':False}
    atomic_json(manifest,data)
    # A stopped guest stays stopped. No backup pruning, no hook scripts, no shell expansion.
    command(['vzdump',str(job['vmid']),'--dumpdir',str(target),'--compress','zstd','--mode','stop','--remove','0','--stdexcludes','0'],timeout=86400)
    guest_config(job,cfg)
    pattern=r'vzdump-'+job['guest_type']+'-'+str(job['vmid'])+r'-[\w.-]+\.'+('tar' if job['guest_type']=='lxc' else 'vma')+r'\.zst'
    files=[p for p in target.iterdir() if re.fullmatch(pattern,p.name) and p.is_file() and not p.is_symlink()]
    if len(files)!=1: raise ValueError('Archive not unique')
    file=files[0];command(['zstd','--test','--quiet',str(file)],timeout=86400)
    digest=hashlib.sha256()
    with file.open('rb') as stream:
        for chunk in iter(lambda:stream.read(4*1024*1024),b''): digest.update(chunk)
    data.update(complete=True,path=str(file),archive=file.name,size=file.stat().st_size,sha256=digest.hexdigest(),expires=0)
    atomic_json(manifest,data);return data

def cleanup(job):
    directory=job_directory(job);manifest=directory/'manifest.json'
    if not manifest.exists(): return
    data=json.loads(manifest.read_text())
    if data['id']!=job['id'] or data['claim']!=job['claim'] or not data.get('complete') or not data.get('expires') or time.time()<data['expires']:
        raise ValueError('Archive cleanup not allowed')
    target=Path(data['directory'])
    if target.name!=directory.name or target.parent.name!='orga-shop-exports' or target.is_symlink(): raise ValueError('Invalid archive directory')
    # Only this claim directory is removed, never a storage or another customer's files.
    if target.exists(): shutil.rmtree(target)
    manifest.unlink();directory.rmdir()

class ArchiveHandler(http.server.BaseHTTPRequestHandler):
    server_version='OrgaExport'
    def log_message(self,*args): pass
    def do_HEAD(self): self.serve(False)
    def do_GET(self): self.serve(True)
    def serve(self,body):
        try:
            match=re.fullmatch(r'/v1/exports/([1-9]\d{0,10})/([a-f0-9]{48})',self.path)
            timestamp=self.headers.get('X-Orga-Time','');rng=self.headers.get('Range','')
            if not match or not timestamp.isdigit() or abs(time.time()-int(timestamp))>90 or len(rng)>80: raise ValueError()
            signed=self.command+'\n'+self.path+'\n'+timestamp+'\n'+rng
            expected='OrgaExport '+hmac.new(self.server.cfg['token'].encode(),signed.encode(),hashlib.sha256).hexdigest()
            if not hmac.compare_digest(self.headers.get('Authorization',''),expected): raise ValueError()
            job={'id':int(match[1]),'claim':match[2]};directory=job_directory(job)
            data=json.loads((directory/'manifest.json').read_text())
            if not data.get('complete') or data['id']!=job['id'] or data['claim']!=job['claim']: raise ValueError()
            # HEAD remains possible after expiry for the shop's pre-deletion integrity check.
            if body and (not data.get('expires') or time.time()>=data['expires']): raise ValueError()
            path=Path(data['path']);size=data['size'];start=0;end=size-1
            if path.is_symlink() or not path.is_file() or path.stat().st_size!=size: raise ValueError()
            if rng:
                m=re.fullmatch(r'bytes=(\d+)-(\d+)',rng)
                if not m: raise ValueError()
                start,end=map(int,m.groups())
                if not (0<=start<=end<size): raise ValueError()
            self.send_response(206 if rng else 200);self.send_header('Content-Type','application/octet-stream');self.send_header('Content-Length',str(end-start+1));self.send_header('X-Orga-Sha256',data['sha256']);self.send_header('Cache-Control','no-store');self.send_header('Accept-Ranges','bytes')
            if rng: self.send_header('Content-Range',f'bytes {start}-{end}/{size}')
            self.end_headers()
            if body:
                with path.open('rb') as stream:
                    stream.seek(start);remaining=end-start+1
                    while remaining:
                        chunk=stream.read(min(1024*1024,remaining))
                        if not chunk: break
                        self.wfile.write(chunk);remaining-=len(chunk)
        except (BrokenPipeError,ConnectionResetError): pass
        except Exception:
            self.send_error(404,'Archive unavailable')

class ArchiveServer(http.server.ThreadingHTTPServer):
    daemon_threads=True
    def get_request(self):
        sock,address=super().get_request();sock.settimeout(10)
        try:
            sock=self.tls.wrap_socket(sock,server_side=True);sock.settimeout(90);return sock,address
        except Exception:
            sock.close();raise

def certificate():
    cert=Path('/etc/pve/local/pveproxy-ssl.pem')
    if not cert.exists(): cert=Path('/etc/pve/local/pve-ssl.pem')
    key=cert.with_suffix('.key')
    pem=command(['openssl','x509','-in',str(cert),'-pubkey','-noout']).stdout
    public=subprocess.run(['openssl','pkey','-pubin','-outform','DER'],input=pem,check=True,stdout=subprocess.PIPE,stderr=subprocess.PIPE).stdout
    return cert,key,'sha256//'+base64.b64encode(hashlib.sha256(public).digest()).decode()

def sync_confirmed_manifests(cfg):
    # Recover a lost ready acknowledgement or a crash before persisting its deadline.
    for manifest in ROOT.glob('*/manifest.json'):
        try:
            data=json.loads(manifest.read_text())
            if not data.get('complete') or data.get('expires'): continue
            response=callback(cfg,dict(action='export_report',id=data['id'],claim=data['claim'],result='ready',archive=data['archive'],size=data['size'],sha256=data['sha256']))
            data['expires']=int(response['expires']);atomic_json(manifest,data)
        except Exception: pass

def run():
    cfg=json.loads(CONFIG.read_text());ROOT.mkdir(mode=0o700,parents=True,exist_ok=True)
    lockfile=(ROOT/'runner.lock').open('a');fcntl.flock(lockfile,fcntl.LOCK_EX|fcntl.LOCK_NB)
    if not re.fullmatch(r'[\w.-]{1,80}',cfg['node']) or not re.fullmatch(r'[\w.-]{1,80}',cfg['pool']): raise ValueError('Invalid node/pool')
    cert,key,pin=certificate();context=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER);context.minimum_version=ssl.TLSVersion.TLSv1_2;context.load_cert_chain(cert,key)
    server=ArchiveServer(('0.0.0.0',9444),ArchiveHandler);server.cfg=cfg;server.tls=context
    threading.Thread(target=server.serve_forever,daemon=True).start()
    def heartbeat():
        while True:
            try: callback(cfg,{'action':'export_heartbeat','pin':pin,'version':1})
            except Exception: pass
            time.sleep(40)
    threading.Thread(target=heartbeat,daemon=True).start()
    while True:
        job=None
        try:
            sync_confirmed_manifests(cfg)
            response=callback(cfg,{'action':'export_poll','pin':pin,'version':1});job=response.get('job')
            if job:
                if job['operation']=='cleanup': cleanup(job);callback(cfg,dict(action='export_report',id=job['id'],claim=job['claim'],result='cleaned'))
                else:
                    data=backup(cfg,job)
                    response=callback(cfg,dict(action='export_report',id=job['id'],claim=job['claim'],result='ready',archive=data['archive'],size=data['size'],sha256=data['sha256']))
                    data['expires']=int(response['expires']);atomic_json(job_directory(job)/'manifest.json',data)
        except Exception as error:
            # Upstream output may contain secrets; journal only the error class.
            print('VPS archive service:',type(error).__name__,flush=True)
            if job:
                try: callback(cfg,dict(action='export_report',id=job['id'],claim=job['claim'],result='failed'))
                except Exception: pass
        time.sleep(20)

if __name__=='__main__': run()
