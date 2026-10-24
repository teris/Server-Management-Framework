import json, subprocess
from pathlib import Path
from php_runtime import php_command
root=Path(__file__).resolve().parents[1];errors=[];files=[]
for directory in ['src','public','config','bin','tests']:
    files.extend((root/directory).rglob('*.php'))
files.extend([root/'framework.php',root/'test.php'])
files=[source for source in files if source.name!='config.inc.php']
for source in files:
    result=subprocess.run(php_command()+['-d','short_open_tag=0','-l',str(source)],capture_output=True,text=True)
    if result.returncode:errors.append({'file':str(source.relative_to(root)),'error':result.stdout+result.stderr})
print(json.dumps({'php_files':len(files),'errors':errors},indent=2));assert not errors
