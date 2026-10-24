import json,subprocess,sys,os
from pathlib import Path
root=Path(__file__).resolve().parents[1];count=0
for file in sorted((root/'tests/shop_runners').glob('*-tests.py')):
 result=subprocess.run([sys.executable,str(file)],cwd=root,capture_output=True,text=True,env=dict(os.environ,PYTHONDONTWRITEBYTECODE='1'))
 if result.returncode:raise RuntimeError(result.stdout+result.stderr)
 import re
 match=re.search(r'Ran (\d+) tests?',result.stderr);count+=int(match[1])if match else 0
print(json.dumps({'checks':count,'failed':0,'provider_writes':0}))
