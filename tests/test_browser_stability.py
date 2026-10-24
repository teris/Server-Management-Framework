import json,os,shutil,subprocess
from pathlib import Path
from http_fixture import HTTPFixture,ROOT
node=os.environ.get('NODE_BIN') or shutil.which('node')
if not node:raise SystemExit('Node.js is required')
with HTTPFixture() as fixture:
    env=dict(fixture.env,SMF_TEST_BASE=fixture.base)
    result=subprocess.run([node,'tests/browser_stability.cjs',json.dumps({'base':fixture.base,'module':os.environ.get('SMF_PLAYWRIGHT_MODULE'),'browser':os.environ.get('SMF_BROWSER_BIN'),'artifacts':os.environ.get('SMF_BROWSER_ARTIFACT_DIR')})],cwd=ROOT,env=env,capture_output=True,text=True)
    if result.returncode:raise RuntimeError(result.stdout+result.stderr)
    report=json.loads(result.stdout);assert fixture.smtp.messages,'Browser registration did not reach the local SMTP fixture'
    print(json.dumps(report))
