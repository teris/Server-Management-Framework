import json, shutil, subprocess, tempfile
from pathlib import Path
from php_runtime import php_command
root=Path(__file__).resolve().parents[1]
files=json.loads(subprocess.check_output(php_command()+[str(root/'bin/package-files.php')],text=True));checks=0
for forbidden in ['config/config.inc.php','src/install.php','src/setup.php','src/debug.php','src/debug/index.php','src/info.php','src/auth.php','src/ajax_ip_mac_reverse.php','test.php','config/framework-mongodb.js','install/database-structure-install-mongodb.js']:
    assert forbidden not in files,forbidden
    checks+=1
assert all(not name.endswith(('.db','.sqlite','.pem','.key','.zip','.log','.bak','.bnk','.tar.gz')) for name in files);checks+=1
assert 'config/config.example.php' in files and 'config/load.php' in files and 'bin/security-migrate.php' in files;checks+=1
assert all(path in files for path in ['bin/install.php','bin/migrate.php','bin/update-worker.php','database/schema.json','src/api/updates.php','src/inc/manualupdater.php','src/assets/index.js','public/reset-password.php','public/assets/vendor/bootstrap/bootstrap.min.css']);checks+=1
with tempfile.TemporaryDirectory(prefix='smf-package-') as directory:
    fixture=Path(directory)/'app';fixture.mkdir()
    for name in files:
        destination=fixture/name;destination.parent.mkdir(parents=True,exist_ok=True);destination.touch()
    shutil.copyfile(root/'bin/package-files.php',fixture/'bin/package-files.php')
    manifest=fixture/'deploy/package-files.json';manifest.write_text(json.dumps(files))
    (fixture/'archive.tar.gz').write_text('fixture-private-backup')
    (fixture/'src/private-provider.php').write_text('fixture-secret')
    def select():return subprocess.run(php_command()+[str(fixture/'bin/package-files.php')],capture_output=True,text=True)
    selected=select();assert selected.returncode==0 and json.loads(selected.stdout)==files;checks+=1
    target=fixture/'src/core/Security.php';target.unlink();outside=Path(directory)/'outside.txt';outside.write_text('fixture-private-file');target.symlink_to(outside)
    assert select().returncode!=0;checks+=1
    target.unlink();assert select().returncode!=0;checks+=1
    target.touch();manifest.write_text(json.dumps(files+['../outside.txt']))
    assert select().returncode!=0;checks+=1
    manifest.write_text(json.dumps(files+['archive.tar.gz']))
    assert select().returncode!=0;checks+=1
print(json.dumps({'checks':checks,'files_selected':len(files),'failed':0}))
