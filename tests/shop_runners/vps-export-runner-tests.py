import hashlib,hmac,http.server,importlib.util,json,tempfile,threading,time,unittest,urllib.request,urllib.error
from pathlib import Path
from unittest.mock import patch
spec=importlib.util.spec_from_file_location('runner','extensions/shop.storage/legacy/vps-export-runner.py');r=importlib.util.module_from_spec(spec);spec.loader.exec_module(r)
class Tests(unittest.TestCase):
 def setUp(self):
  self.temp=tempfile.TemporaryDirectory();self.root=Path(self.temp.name);self.old=r.ROOT;r.ROOT=self.root/'claims';r.ROOT.mkdir();self.job={'id':12,'claim':'a'*48,'vmid':710,'node':'pve','pool':'shop','guest_type':'lxc','marker':'Orga Consult OC-TEST'};self.cfg={'node':'pve','pool':'shop','token':'test-only'}
 def tearDown(self):r.ROOT=self.old;self.temp.cleanup()
 def manifest(self,expires=None):
  d=r.job_directory(self.job);d.mkdir();target=self.root/'store01'/'orga-shop-exports'/d.name;target.mkdir(parents=True);f=target/'vzdump-lxc-710-test.tar.zst';f.write_bytes(b'0123456789abcdef');data={'id':12,'claim':'a'*48,'complete':True,'path':str(f),'directory':str(target),'archive':f.name,'size':16,'sha256':hashlib.sha256(f.read_bytes()).hexdigest(),'expires':expires or int(time.time())+300};r.atomic_json(d/'manifest.json',data);return d,target,data
 def test_sizes(self):
  self.assertEqual(r.required_bytes(self.job,{'rootfs':'store01:disk,size=2G','mp0':'store02:disk,size=3G,backup=1'}),5*1024**3)
  for v in ['store01:disk,size=2G,backup=0','/host/data,mp=/data','store01:disk,size=2G']:
   with self.assertRaises(ValueError):r.required_bytes(self.job,{'rootfs':'store01:disk,size=1G','mp0':v})
 def test_safe_claim(self):
  for claim in ['../other','a'*47,'a'*49]:
   with self.assertRaises(ValueError):r.job_directory(dict(self.job,claim=claim))
 def test_ownership(self):
  def pve(path):
   if path=='/pools/shop':return {'members':[{'vmid':710,'node':'pve','type':'lxc'}]}
   if path.endswith('/config'):return {'description':self.job['marker']+'\n'}
   return {'status':'stopped'}
  with patch.object(r,'pve',side_effect=pve):self.assertIn('description',r.guest_config(self.job,self.cfg))
  for key,val in [('node','other'),('pool','other'),('vmid',99),('guest_type','anything')]:
   with self.assertRaises(ValueError):r.guest_config(dict(self.job,**{key:val}),self.cfg)
  with patch.object(r,'pve',return_value={'members':[]}):
   with self.assertRaises(ValueError):r.guest_config(self.job,self.cfg)
 def test_backup_integrity(self):
  target=self.root/'store01'/'orga-shop-exports';target.mkdir(parents=True);commands=[]
  def command(args,timeout=120):
   commands.append(args)
   if args[0]=='vzdump':(Path(args[args.index('--dumpdir')+1])/'vzdump-lxc-710-test.tar.zst').write_bytes(b'archive-data')
  with patch.object(r,'guest_config',return_value={'rootfs':'store01:disk,size=8G'}),patch.object(r,'archive_root',return_value=target),patch.object(r,'command',side_effect=command):
   data=r.backup(self.cfg,self.job);self.assertTrue(data['complete']);self.assertEqual(data['sha256'],hashlib.sha256(b'archive-data').hexdigest());self.assertEqual(commands[1][0],'zstd');self.assertIn('--remove',commands[0]);self.assertEqual(commands[0][commands[0].index('--remove')+1],'0');again=r.backup(self.cfg,self.job);self.assertEqual(again['sha256'],data['sha256']);self.assertEqual(len(commands),2)
 def test_partial_backup_never_claimed_complete(self):
  directory=r.job_directory(self.job);directory.mkdir();r.atomic_json(directory/'manifest.json',{'id':12,'claim':'a'*48,'complete':False})
  with patch.object(r,'guest_config',side_effect=ValueError('Owner changed')):
   with self.assertRaises(ValueError):r.backup(self.cfg,self.job)
 def test_cleanup(self):
  d,target,data=self.manifest()
  with self.assertRaises(ValueError):r.cleanup(self.job)
  data['expires']=int(time.time())-1;r.atomic_json(d/'manifest.json',data);r.cleanup(self.job);self.assertFalse(target.exists());self.assertFalse(d.exists());r.cleanup(self.job)
 def test_lost_confirmation_recovery(self):
  d,target,data=self.manifest();data['expires']=0;r.atomic_json(d/'manifest.json',data)
  with patch.object(r,'callback',return_value={'accepted':True,'expires':1234567890}) as call:
   r.sync_confirmed_manifests(self.cfg);self.assertEqual(json.loads((d/'manifest.json').read_text())['expires'],1234567890);self.assertEqual(call.call_count,1);r.sync_confirmed_manifests(self.cfg);self.assertEqual(call.call_count,1)
 def test_authenticated_range_download(self):
  d,target,data=self.manifest();server=http.server.ThreadingHTTPServer(('127.0.0.1',0),r.ArchiveHandler);server.cfg=self.cfg;threading.Thread(target=server.serve_forever,daemon=True).start()
  def fetch(method='GET',rng='',token='test-only',path=None,timestamp=None):
   path=path or '/v1/exports/12/'+'a'*48;t=str(timestamp or int(time.time()));sig=hmac.new(token.encode(),(method+'\n'+path+'\n'+t+'\n'+rng).encode(),hashlib.sha256).hexdigest();req=urllib.request.Request('http://127.0.0.1:'+str(server.server_port)+path,method=method,headers={'Authorization':'OrgaExport '+sig,'X-Orga-Time':t,'Range':rng});return urllib.request.urlopen(req,timeout=3)
  try:
   with fetch() as response:self.assertEqual(response.read(),b'0123456789abcdef');self.assertEqual(response.headers['X-Orga-Sha256'],data['sha256'])
   with fetch(rng='bytes=5-9') as response:self.assertEqual(response.status,206);self.assertEqual(response.read(),b'56789');self.assertEqual(response.headers['Content-Range'],'bytes 5-9/16')
   for kw in [{'token':'wrong'},{'timestamp':int(time.time())-200},{'path':'/v1/exports/13/'+'a'*48},{'rng':'bytes=0-30'},{'path':'/v1/exports/12/../../etc/passwd'}]:
    with self.assertRaises(urllib.error.HTTPError):fetch(**kw)
   data['expires']=int(time.time())-1;r.atomic_json(d/'manifest.json',data)
   with self.assertRaises(urllib.error.HTTPError):fetch()
   with fetch('HEAD') as response:self.assertEqual(response.status,200);self.assertEqual(response.read(),b'')
  finally:server.shutdown();server.server_close()
if __name__=='__main__':unittest.main()
