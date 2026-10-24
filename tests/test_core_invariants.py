import hashlib, json, re, subprocess, tempfile
from pathlib import Path
from php_runtime import php_command, fixture_environment

root=Path(__file__).resolve().parents[1];checks=0
def check(ok,name):
    global checks;checks+=1;assert ok,name
for asset in json.loads((root/'deploy/vendor-assets.json').read_text()):check(hashlib.sha256((root/asset['file']).read_bytes()).hexdigest()==asset['sha256'],'vendor asset checksum: '+asset['file'])
for engine,paths in {
    'sqlite':['install/database-structure-install-sqlite.sql','database/database-structure-sqlite.sql','config/framework-sqlite.sql'],
    'mysql':['install/database-structure-install-mysql.sql','database/database-structure-optimized.sql','config/framework-mysql.sql'],
    'pgsql':['install/database-structure-install-postgresql.sql','database/database-structure-postgresql.sql','config/framework-postgresql.sql']
}.items():
    sql=subprocess.check_output(php_command()+['-r',"require 'src/core/SchemaManager.php'; echo SchemaManager::sql('"+engine+"');"],cwd=root)
    for path in paths:check((root/path).read_bytes()==sql,'canonical schema snapshot: '+path)
for directory in ['src','public','bin','config']:
    for path in (root/directory).rglob('*'):
        if not path.is_file() or path.name=='config.inc.php' or path.suffix not in ['.php','.js','.css','.xml']:continue
        data=path.read_bytes();check(not data.startswith(b'\xef\xbb\xbf'),'BOM: '+str(path));data.decode('utf-8')
with tempfile.TemporaryDirectory(prefix='smf-cache-') as directory:
    tmp=Path(directory);config=(root/'config/config.example.php').read_text()
    for key,value in {'DB_TYPE':"'sqlite'",'DB_SQLITE_USEING':'true','DB_SQLITE_PATH':"'"+str(tmp/'cache.sqlite')+"'"}.items():config=re.sub(r'\bconst '+key+r'\s*=\s*[^;]+;',lambda _:'const '+key+' = '+value+';',config)
    (tmp/'config.php').write_text(config)
    env=fixture_environment(SMF_CONFIG_FILE=str(tmp/'config.php'),SMF_PRIVATE_DIR=str(tmp/'private'))
    script=r'''
require 'src/sys.conf.php'; require 'src/core/DatabaseOnlyFramework.php'; require 'src/core/Validation.php';
$db=Database::getInstance()->getConnection(); SchemaManager::migrate($db);
$model=new VM(); $id=$model->insert(['vm_id'=>777,'name'=>'cache-vm','node'=>'fixture','status'=>'stopped','memory'=>512,'cores'=>1,'disk_size'=>10]);
$service=new ServiceManager();$items=$service->getProxmoxVMs();
if(count($items)!==1||$items[0]->vmid!=777||$items[0]->toArray()['name']!=='cache-vm')throw new Exception('Cache model contract');
$blocked=false;try{$service->controlProxmoxVM('fixture',777,'start');}catch(RuntimeException $e){$blocked=true;}if(!$blocked)throw new Exception('Cache cannot control providers');
$blocked=false;try{$model->update($id,['name = 1; DROP TABLE users; --'=>'x']);}catch(InvalidArgumentException $e){$blocked=true;}if(!$blocked)throw new Exception('Model fields');
foreach([[['x'=>2048],'required|numeric|max:2048',false],[['x'=>2049],'numeric|max:2048',true],[['x'=>'äö'],'min:2|max:2',false],[['x'=>'  '],'required',true],[['x'=>0],'required|integer|min:0',false],[['x'=>[]],'required',true],[['x'=>['a']],'numeric',true],[['x'=>INF],'numeric',true],[['x'=>'1.5'],'integer',true]]as[$data,$rule,$error])if((bool)Validation::errors($data,['x'=>$rule])!==$error)throw new Exception('Validation boundary');
echo json_encode(['checks'=>12,'failed'=>0]);
'''
    result=subprocess.run(php_command()+['-r',script],cwd=root,env=env,capture_output=True,text=True)
    check(result.returncode==0,result.stdout+result.stderr);checks+=json.loads(result.stdout)['checks']
    (tmp/'config.php').write_text(config.replace("const DB_TYPE = 'sqlite';","const DB_TYPE = 'mongodb';"))
    result=subprocess.run(php_command()+['-r',"require 'src/core/DatabaseManager.php'; try{DatabaseManager::getInstance();exit(1);}catch(RuntimeException $e){echo $e->getMessage();}"],cwd=root,env=env,capture_output=True,text=True)
    check(result.returncode==0 and 'nicht unterstützt' in result.stdout,'MongoDB must fail explicitly')
print(json.dumps({'checks':checks,'failed':0,'schema_snapshots':9,'cache_provider_writes':'rejected','utf8':'valid'}))
