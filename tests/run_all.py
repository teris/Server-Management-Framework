import argparse, json, os, shutil, subprocess, sys
from pathlib import Path
from php_runtime import php_command
root=Path(__file__).resolve().parents[1];parser=argparse.ArgumentParser();parser.add_argument('--report');parser.add_argument('--baseline',action='store_true');args=parser.parse_args()
node=os.environ.get('NODE_BIN') or shutil.which('node')
if not node:raise SystemExit('Node.js is required (or set NODE_BIN)')
suites=[('php_syntax',[sys.executable,'tests/check_syntax.py']),('authorization_sessions_files',php_command()+['tests/run.php']),('modular_shop',php_command()+['tests/modules.php']),('shop_migration',php_command()+['tests/shop_migration.php']),('shop_node_runners',[sys.executable,'tests/test_shop_runners.py']),('database_sqlite',[sys.executable,'tests/test_database_engines.py']),('changed_browser_syntax',[sys.executable,'tests/check_browser_syntax.py']),
        ('core_invariants',[sys.executable,'tests/test_core_invariants.py']),('user_provider_contracts',php_command()+['tests/test_user_contracts.php']),('signed_updates',php_command()+['tests/test_updates.php']),
        ('registration_install_smtps',[sys.executable,'tests/test_registration_http.py']),('http',[sys.executable,'tests/test_http.py']),('shop_http',[sys.executable,'tests/test_shop_http.py']),('tls',[sys.executable,'tests/test_tls.py']),
        ('concurrency',[sys.executable,'tests/test_concurrency.py']),('package_selection',[sys.executable,'tests/test_packaging.py']),('private_config_migration',[sys.executable,'tests/test_config_migration.py']),
        ('session_browser',[node,'tests/test_session_ui.cjs']),('csrf_browser',[node,'tests/test_browser_security.cjs'])]
if args.baseline:suites.append(('baseline_negative_control',[sys.executable,'tests/test_baseline.py']))
results={}
for name,command in suites:
    result=subprocess.run(command,cwd=root,capture_output=True,text=True)
    if result.returncode:print(name+' FAILED\n'+result.stdout+result.stderr,file=sys.stderr);raise SystemExit(1)
    results[name]=json.loads(result.stdout);print(name+': passed',flush=True)
report={'date':'2026-10-24','php_version':subprocess.check_output(php_command()+['-r','echo PHP_VERSION;'],text=True).strip(),
        'node_version':subprocess.check_output([node,'--version'],text=True).strip(),'suites':results,
        'limitations':['No production deployment','No live provider mutations','Native SQL and headless browser suites are reported separately','Production webserver acceptance pending in AP30','GitHub Actions configured but not run remotely before push']}
if args.report:Path(args.report).write_text(json.dumps(report,indent=2,ensure_ascii=False)+'\n')
print(json.dumps({'passed':True,'suite_count':len(results)},indent=2))
