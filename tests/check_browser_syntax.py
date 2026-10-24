import json,os,shutil,subprocess
from pathlib import Path
root=Path(__file__).resolve().parents[1];node=os.environ.get('NODE_BIN') or shutil.which('node');failures=[];files=[]
if not node:raise SystemExit('Node.js required')
for area in ['src','public']:
    for path in (root/area).rglob('*.js'):
        files.append(path);result=subprocess.run([node,'--check',str(path.relative_to(root))],cwd=root,capture_output=True,text=True)
        if result.returncode:failures.append({'file':str(path.relative_to(root)),'error':result.stderr})
print(json.dumps({'files':len(files),'failed':len(failures),'errors':failures},indent=2));assert not failures
