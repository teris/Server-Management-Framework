import argparse,json,os,re,shlex,subprocess,tempfile,secrets
from pathlib import Path
from php_runtime import php_command,fixture_environment
parser=argparse.ArgumentParser();parser.add_argument('--suite',choices=['modules','provider_modules','provider_matrix','gameserver'],default='modules');args=parser.parse_args()
root=Path(__file__).resolve().parents[1];name='smf_modules_'+secrets.token_hex(6)
client=[os.environ.get('SMF_TEST_MYSQL_CLIENT','mysql'),'--host='+os.environ.get('SMF_TEST_MYSQL_HOST','127.0.0.1'),'--user='+os.environ.get('SMF_TEST_MYSQL_USER','root')]
if os.environ.get('SMF_TEST_MYSQL_PLUGIN_DIR'):client+=['--plugin-dir='+os.environ['SMF_TEST_MYSQL_PLUGIN_DIR']]
socket=os.environ.get('SMF_TEST_MYSQL_SOCKET','')
if socket:client+=['--socket='+socket]
else:client+=['--port='+os.environ.get('SMF_TEST_MYSQL_PORT','3306')]
env=fixture_environment(SMF_MODULE_TEST_NATIVE='1',MYSQL_PWD=os.environ.get('SMF_TEST_MYSQL_PASSWORD',''))
subprocess.run(client+['--execute=CREATE DATABASE '+name+' CHARACTER SET utf8mb4'],env=env,check=True,capture_output=True)
try:
 with tempfile.TemporaryDirectory(prefix='smf-modules-mysql-')as directory:
    tmp=Path(directory);config=(root/'config/config.example.php').read_text()
    for key,value in {'DB_TYPE':'mysql','DB_HOST':os.environ.get('SMF_TEST_MYSQL_HOST','127.0.0.1'),'DB_PORT':int(os.environ.get('SMF_TEST_MYSQL_PORT','3306')),'DB_NAME':name,'DB_USER':os.environ.get('SMF_TEST_MYSQL_USER','root'),'DB_PASS':env['MYSQL_PWD'],'DB_SOCKET':socket}.items():
        literal=str(value)if isinstance(value,int)else "'"+value.replace('\\','\\\\').replace("'","\\'")+"'"
        config=re.sub(r'\bconst '+key+r'\s*=\s*[^;]+;',lambda _:'const '+key+' = '+literal+';',config)
    (tmp/'config.php').write_text(config);env['SMF_CONFIG_FILE']=str(tmp/'config.php')
    result=subprocess.run(php_command()+[str(root/('tests/'+args.suite+'.php'))],env=env,cwd=root,capture_output=True,text=True)
    if result.returncode:raise RuntimeError(result.stdout+result.stderr)
    print(json.dumps(json.loads(result.stdout)|{'engine':'mysql/mariadb'}))
finally:subprocess.run(client+['--execute=DROP DATABASE '+name],env=env,check=True,capture_output=True)
