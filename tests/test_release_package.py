"""Verify a built release and exercise its actual install and update code."""
import argparse,base64,contextlib,hashlib,io,json,runpy,subprocess,tempfile,zipfile
from pathlib import Path
from cryptography.hazmat.primitives import hashes,serialization
from cryptography.hazmat.primitives.asymmetric import padding
from php_runtime import php_command,fixture_environment
import http_fixture
ROOT=Path(__file__).resolve().parents[1]
parser=argparse.ArgumentParser();parser.add_argument('directory',type=Path);args=parser.parse_args();directory=args.directory.resolve();checks=0
key=serialization.load_pem_public_key((directory/'smf-release-public.pem').read_bytes())
key.verify((directory/'SHA256SUMS.sig').read_bytes(),(directory/'SHA256SUMS').read_bytes(),padding.PKCS1v15(),hashes.SHA256());checks+=1
for line in (directory/'SHA256SUMS').read_text().splitlines():
    digest,name=line.split('  ',1);assert Path(name).name==name;assert hashlib.sha256((directory/name).read_bytes()).hexdigest()==digest;checks+=1
info=json.loads((directory/'build-info.json').read_text());archive=directory/info['package']
with tempfile.TemporaryDirectory(prefix='smf-release-') as tmp:
    app=Path(tmp)/'app'
    with zipfile.ZipFile(archive) as zip:
        manifest=zip.read('manifest.json');signature=base64.b64decode(zip.read('manifest.sig'));key.verify(signature,manifest,padding.PKCS1v15(),hashes.SHA256());checks+=1
        files=json.loads(manifest)['files'];assert set(zip.namelist())==set(files)|{'manifest.json','manifest.sig'};checks+=1
        for name,digest in files.items():
            assert not Path(name).is_absolute() and '..' not in Path(name).parts
            assert hashlib.sha256(zip.read(name)).hexdigest()==digest
        checks+=len(files);zip.extractall(app)
    # Reuse the documented HTTP/SMTPS installation acceptance against the ZIP contents.
    http_fixture.ROOT=app;output=io.StringIO()
    with contextlib.redirect_stdout(output):runpy.run_path(str(ROOT/'tests/test_registration_http.py'),run_name='__main__')
    install=json.loads(output.getvalue())
    result=subprocess.run(php_command()+[str(ROOT/'tests/release_package_probe.php'),str(archive),str(directory/'smf-release-public.pem')],env=fixture_environment(),text=True,capture_output=True)
    if result.returncode:raise RuntimeError(result.stdout+result.stderr)
    update=json.loads(result.stdout)
print(json.dumps({'checks':checks,'failed':0,'commit':info['commit'],'package_sha256':info['archive_sha256'],'installation_http_smtps':install,'actual_package_update':update},indent=2))
