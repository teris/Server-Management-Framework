"""No provider writes: exercise runner identity, fixed networking and cleanup with subprocess doubles."""
import importlib.util,json,tempfile,subprocess,unittest
from pathlib import Path
from unittest.mock import patch
spec=importlib.util.spec_from_file_location('orga_runner','extensions/shop.storage/legacy/template-runner.py');r=importlib.util.module_from_spec(spec);spec.loader.exec_module(r)
class RunnerTests(unittest.TestCase):
 def setUp(self):
  self.p={'template':{'node':'pve','storage':'store02'},'recipe':{'slug':'debian','recipe':'','scripts_commit':'a'*40,'core_commit':'b'*40},'network':{'address':'203.0.113.55','gateway':'54.36.111.254','prefix_len':32,'mac':'02:00:00:00:00:55','bridge':'vmbr0'},'claim':'c'*48,'backup_storage':'store02','template_storage':'local','settings':{'disk_gb':8,'min_memory_mb':512,'min_cores':1,'nameserver':'1.1.1.1','unprivileged':1,'nesting':0,'keyctl':0},'_job_id':1}
 def test_validate_network_and_pins(self):
  self.assertEqual(r.validate_job({'payload':self.p},'pve'),self.p)
  for key,value in [('mac','invalid'),('prefix_len',24),('address','not-an-ip')]:
   old=self.p['network'][key];self.p['network'][key]=value
   with self.assertRaises(ValueError):r.validate_job({'payload':self.p},'pve')
   self.p['network'][key]=old
  self.p['recipe']['scripts_commit']='main'
  with self.assertRaises(ValueError):r.validate_job({'payload':self.p},'pve')
 def test_environment_isolated(self):
  env=r.build_environment(self.p,900,Path('/scripts'),Path('/core'),self.p['settings'])
  self.assertEqual(env['var_net'],'203.0.113.55/32');self.assertEqual(env['var_mac'],'02:00:00:00:00:55');self.assertEqual(env['var_gateway'],'54.36.111.254')
  self.assertEqual(env['mode'],'generated');self.assertEqual(env['var_ctid'],'900');self.assertNotIn('token',env);self.assertNotIn('SSH_AUTH_SOCK',env)
  self.assertNotEqual(env['var_pw'],r.build_environment(self.p,900,Path('/scripts'),Path('/core'),self.p['settings'])['var_pw'])
 def test_script_minimums(self):
  resources=r.recipe_resources('var_cpu="${var_cpu:-2}"\nvar_ram="${var_ram:-4096}"\nvar_disk="${var_disk:-12}"',self.p['settings'])
  self.assertEqual(resources,{'min_cores':2,'min_memory_mb':4096,'disk_gb':12})
 def test_cleanup_checks_ownership_before_delete(self):
  calls=[]
  def fake(args,**kw):
   calls.append(args)
   if '/config' in args[2] if len(args)>2 else False:
    return subprocess.CompletedProcess(args,0,json.dumps({'hostname':'other-owner'}),'')
   return subprocess.CompletedProcess(args,0,'[]','')
  with patch.object(r,'command',fake):
   with self.assertRaises(ValueError):r.prepare_archive(900,self.p)
  self.assertFalse(any('destroy' in c for c in calls))
 def test_archive_and_exact_destroy(self):
  calls=[]
  def fake(args,**kw):
   calls.append(args)
   if args[:2]==['pvesh','get'] and args[2].endswith('/config'):
    value={'hostname':'oc-build-1','net0':'name=eth0,bridge=vmbr0,ip=203.0.113.55/32,gw=54.36.111.254,hwaddr=02:00:00:00:00:55'}
   elif args[:2]==['pvesh','get']:value=[{'volid':'store02:backup/vzdump-lxc-900-2026_10_18-10_00_00.tar.zst','ctime':1},{'volid':'store02:backup/vzdump-lxc-901-foreign.tar.zst','ctime':2}]
   else:value=[]
   return subprocess.CompletedProcess(args,0,json.dumps(value),'')
  with patch.object(r,'command',fake):self.assertEqual(r.prepare_archive(900,self.p),'store02:backup/vzdump-lxc-900-2026_10_18-10_00_00.tar.zst')
  self.assertIn(['pct','destroy','900'],calls);self.assertTrue(any('passwd -l root' in a for c in calls for a in c));self.assertFalse(any('901' in c for c in calls))
if __name__=='__main__':unittest.main()
