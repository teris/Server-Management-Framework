import json,os,shutil,sqlite3,subprocess
from pathlib import Path
from http_fixture import HTTPFixture,ROOT
from php_runtime import php_command
node=os.environ.get('NODE_BIN') or shutil.which('node')
if not node:raise SystemExit('Node.js is required')
with HTTPFixture() as fixture:
    password=subprocess.check_output(php_command()+['-r',"echo password_hash('Browser customer password 123', PASSWORD_DEFAULT);"],text=True).strip()
    with sqlite3.connect(fixture.tmp/'application.sqlite') as database:
        database.execute("INSERT INTO customers (first_name,last_name,email,password_hash,status) VALUES ('Browser','Customer','browser-portal@example.invalid',?,'active')",[password])
    env=dict(fixture.env,SMF_TEST_BASE=fixture.base)
    result=subprocess.run([node,'tests/browser_stability.cjs',json.dumps({'base':fixture.base,'module':os.environ.get('SMF_PLAYWRIGHT_MODULE'),'browser':os.environ.get('SMF_BROWSER_BIN'),'artifacts':os.environ.get('SMF_BROWSER_ARTIFACT_DIR')})],cwd=ROOT,env=env,capture_output=True,text=True)
    if result.returncode:raise RuntimeError(result.stdout+result.stderr)
    report=json.loads(result.stdout);assert fixture.smtp.messages,'Browser registration did not reach the local SMTP fixture'
    print(json.dumps(report))
