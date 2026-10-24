import json, os, shutil, subprocess, tempfile
from pathlib import Path
from php_runtime import php_command, fixture_environment
ROOT=Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='smf-config-') as directory:
    tmp=Path(directory);app=tmp/'app'
    for part in ['bin','config','src/core','src/kernel']:(app/part).mkdir(parents=True,exist_ok=True)
    for file in ['bin/private-config.php','config/load.php','src/core/Security.php','src/core/SchemaManager.php','src/core/VersionFile.php','src/kernel/autoload.php']:shutil.copyfile(ROOT/file,app/file)
    content="<?php\nclass Config { const DB_PASS = 'fixture-secret'; }\n";key='fixture-key-'+'a'*64
    source=app/'config/config.inc.php';source.write_text(content);legacy=app/'src/.encryption_key';legacy.write_text(key)
    override=app/'src/sys.conf.local.php';override.write_text("<?php\n$system_config['site_title']='Custom operator';\n")
    env=fixture_environment(SMF_PRIVATE_DIR=str(tmp/'private'));env.pop('SMF_CONFIG_FILE',None)
    def migrate():return subprocess.run(php_command()+[str(app/'bin/private-config.php')],env=env,capture_output=True,text=True)
    first=migrate();assert first.returncode==0,first.stderr
    private=tmp/'private/config/runtime.php';newkey=tmp/'private/keys/provider-encryption.key'
    assert private.read_text()==content and newkey.read_text()==key and not legacy.exists()
    assert (tmp/'private/config/system.local.php').read_bytes()==override.read_bytes()
    assert 'fixture-secret' not in source.read_text() and 'fixture-secret' not in first.stdout
    second=migrate();assert second.returncode==0 and private.read_text()==content and newkey.read_text()==key
    source.write_text(content.replace('fixture-secret','different-fixture'));conflict=migrate()
    assert conflict.returncode!=0 and private.read_text()==content
    source.write_text(content);legacy.write_text('other-key');conflict=migrate()
    assert conflict.returncode!=0 and legacy.read_text()=='other-key' and newkey.read_text()==key
    print(json.dumps({'checks':6,'failed':0}))
