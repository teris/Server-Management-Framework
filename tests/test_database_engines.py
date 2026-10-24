import argparse,json,os,re,shutil,subprocess,tempfile,uuid
from pathlib import Path
from php_runtime import php_command, fixture_environment
root=Path(__file__).resolve().parents[1]
parser=argparse.ArgumentParser();parser.add_argument('--engines',default='sqlite');args=parser.parse_args();results=[]
# Every SQL database receives a new random test name; an existing database is never reused or dropped.
for engine in args.engines.split(','):
    if engine not in ['sqlite','mysql','pgsql']:raise SystemExit('Unsupported test engine')
    for mode in ['fresh','legacy','legacy_full']:
        with tempfile.TemporaryDirectory(prefix='smf-engine-') as directory:
            tmp=Path(directory);name='smf_test_'+uuid.uuid4().hex[:16];env=fixture_environment(SMF_PRIVATE_DIR=str(tmp/'private'),SMF_CONFIG_FILE=str(tmp/'config.php'))
            values={'DB_TYPE':engine,'DB_USEING':engine=='mysql','DB_SQLITE_USEING':engine=='sqlite','DB_PGSQL_USEING':engine=='pgsql','DB_SQLITE_PATH':str(tmp/'app.sqlite')}
            created=False;client=[]
            if engine=='mysql':
                client=[os.environ.get('SMF_TEST_MYSQL_CLIENT','mariadb'),'--no-defaults','--user='+os.environ.get('SMF_TEST_MYSQL_USER','root')]
                if os.environ.get('SMF_TEST_MYSQL_PLUGIN_DIR'):client+=['--plugin-dir='+os.environ['SMF_TEST_MYSQL_PLUGIN_DIR']]
                socket=os.environ.get('SMF_TEST_MYSQL_SOCKET','');host=os.environ.get('SMF_TEST_MYSQL_HOST','127.0.0.1');port=int(os.environ.get('SMF_TEST_MYSQL_PORT','3306'))
                client+=['--socket='+socket] if socket else ['--host='+host,'--port='+str(port)]
                env['MYSQL_PWD']=os.environ.get('SMF_TEST_MYSQL_PASSWORD','')
                values.update(DB_HOST=host,DB_PORT=port,DB_NAME=name,DB_USER=os.environ.get('SMF_TEST_MYSQL_USER','root'),DB_PASS=env['MYSQL_PWD'],DB_SOCKET=socket)
                subprocess.run(client+['--execute=CREATE DATABASE '+name+' CHARACTER SET utf8mb4'],env=env,check=True,capture_output=True);created=True
            elif engine=='pgsql':
                client=[os.environ.get('SMF_TEST_PSQL_CLIENT','psql'),'--no-psqlrc','--host='+os.environ.get('SMF_TEST_PGSQL_HOST','127.0.0.1'),'--port='+os.environ.get('SMF_TEST_PGSQL_PORT','5432'),'--username='+os.environ.get('SMF_TEST_PGSQL_USER','postgres'),'--dbname=postgres','--set=ON_ERROR_STOP=1']
                env['PGPASSWORD']=os.environ.get('SMF_TEST_PGSQL_PASSWORD','')
                values.update(DB_PGSQL_HOST=os.environ.get('SMF_TEST_PGSQL_HOST','127.0.0.1'),DB_PGSQL_PORT=int(os.environ.get('SMF_TEST_PGSQL_PORT','5432')),DB_PGSQL_NAME=name,DB_PGSQL_USER=os.environ.get('SMF_TEST_PGSQL_USER','postgres'),DB_PGSQL_PASS=env['PGPASSWORD'])
                subprocess.run(client+['--command=CREATE DATABASE '+name],env=env,check=True,capture_output=True);created=True
            configuration=(root/'config/config.example.php').read_text()
            for key,value in values.items():
                literal=('true' if value else 'false') if isinstance(value,bool) else (str(value) if isinstance(value,int) else "'"+str(value).replace('\\','\\\\').replace("'","\\'")+"'")
                pattern=r'\bconst '+re.escape(key)+r'\s*=\s*[^;]+;'
                if re.search(pattern,configuration):configuration=re.sub(pattern,lambda _: 'const '+key+' = '+literal+';',configuration)
                else:configuration=configuration.replace('class Config {','class Config {\n    const '+key+' = '+literal+';')
            (tmp/'config.php').write_text(configuration)
            try:
                result=subprocess.run(php_command()+[str(root/'tests/database_probe.php'),mode],env=env,capture_output=True,text=True)
                if result.returncode:raise RuntimeError(engine+'/'+mode+'\n'+result.stdout+result.stderr)
                results.append(json.loads(result.stdout))
            finally:
                if created:subprocess.run(client+([ '--execute=DROP DATABASE '+name] if engine=='mysql' else ['--command=DROP DATABASE '+name]),env=env,check=True,capture_output=True)
print(json.dumps({'checks':sum(x['checks'] for x in results),'failed':0,'engines':results},indent=2))
