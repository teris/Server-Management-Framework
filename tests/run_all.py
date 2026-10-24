import argparse, json, os, shutil, subprocess, sys
from pathlib import Path
from php_runtime import php_command
root=Path(__file__).resolve().parents[1];parser=argparse.ArgumentParser();parser.add_argument('--report');parser.add_argument('--baseline',action='store_true');args=parser.parse_args()
node=os.environ.get('NODE_BIN') or shutil.which('node')
if not node:raise SystemExit('Node.js is required (or set NODE_BIN)')
suites=[('php_syntax',[sys.executable,'tests/check_syntax.py']),('authorization_sessions_files',php_command()+['tests/run.php']),
        ('http',[sys.executable,'tests/test_http.py']),('tls',[sys.executable,'tests/test_tls.py']),
        ('concurrency',[sys.executable,'tests/test_concurrency.py']),('package_selection',[sys.executable,'tests/test_packaging.py']),('private_config_migration',[sys.executable,'tests/test_config_migration.py']),
        ('session_browser',[node,'tests/test_session_ui.cjs']),('csrf_browser',[node,'tests/test_browser_security.cjs'])]
if args.baseline:suites.append(('baseline_negative_control',[sys.executable,'tests/test_baseline.py']))
results={}
for name,command in suites:
    result=subprocess.run(command,cwd=root,capture_output=True,text=True)
    if result.returncode:print(name+' FAILED\n'+result.stdout+result.stderr,file=sys.stderr);raise SystemExit(1)
    results[name]=json.loads(result.stdout);print(name+': passed',flush=True)
for path in ['src/assets/security.js','src/assets/session.js','src/assets/lazy-loading-main.js','public/assets/login.js']:
    result=subprocess.run([node,'--check',path],cwd=root,capture_output=True,text=True)
    if result.returncode:raise SystemExit(result.stderr)
results['changed_browser_syntax']={'files':4,'failed':0}
report={'date':'2026-10-24','php_version':subprocess.check_output(php_command()+['-r','echo PHP_VERSION;'],text=True).strip(),
        'node_version':subprocess.check_output([node,'--version'],text=True).strip(),'suites':results,
        'limitations':['No production deployment','No live provider mutations','Live MySQL/MariaDB/PostgreSQL integration and production webserver acceptance pending','GitHub Actions configured but not run remotely before push']}
if args.report:Path(args.report).write_text(json.dumps(report,indent=2,ensure_ascii=False)+'\n')
print(json.dumps({'passed':True,'suite_count':len(results)},indent=2))
