#!/usr/bin/env python3
"""Build a deterministic, signed full package from one committed Git tree."""
import argparse, base64, datetime, hashlib, io, json, re, subprocess, tarfile, zipfile
from pathlib import Path
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import padding, rsa

ROOT=Path(__file__).resolve().parents[1]
def git(*args):return subprocess.check_output(['git',*args],cwd=ROOT)
def safe_path(path):
    return (isinstance(path,str) and re.fullmatch(r'[a-zA-Z0-9_.-]+(?:/[a-zA-Z0-9_.-]+)*',path)
        and all(p not in ['.','..'] and (not p.startswith('.') or p=='.htaccess') for p in path.split('/'))
        and not re.search(r'^(?:tests|private|var|cache|logs|backups|node_modules)/|(?:^|/)(?:config\.inc\.php|setup-access\.php|smf_ogp_bridge_config\.php|smf-ogp-bridge\.json|sys\.conf\.local\.php|\.encryption_key)$|\.(?:db|sqlite3?|log|bnk|bak|zip|pem|key|env|tar|tgz|gz|7z|rar)(?:[.-]|$)',path,re.I))
def build(ref,output,key_file):
    commit=git('rev-parse','--verify',ref+'^{commit}').decode().strip()
    inventory=json.loads(git('show',commit+':deploy/package-files.json'))
    if not inventory or len(inventory)!=len(set(inventory)) or not all(safe_path(p) for p in inventory):raise ValueError('Unsafe package inventory')
    tree={}
    for line in git('ls-tree','-r',commit).decode().splitlines():
        metadata,path=line.split('\t',1);tree[path]=metadata.split()[0]
    if any(tree.get(p)!='100644' and tree.get(p)!='100755' for p in inventory):raise ValueError('Package includes absent/nonregular Git objects')
    payload={}
    with tarfile.open(fileobj=io.BytesIO(git('archive',commit,'--',*sorted(inventory)))) as archive:
        for entry in archive:
            if entry.isdir():continue
            if not entry.isfile() or entry.name not in inventory:raise ValueError('Unexpected archive entry')
            payload[entry.name]=archive.extractfile(entry).read()
    if set(payload)!=set(inventory):raise ValueError('Incomplete archive')
    match=re.search(rb'\$system_config\s*=\s*(?:array\s*\(|\[)([\s\S]*?)(?:\)|\])\s*;',payload['src/sys.conf.php'])
    version=re.search(rb"['\"]version['\"]\s*=>\s*['\"]([0-9]+\.[0-9]+\.[0-9]+)['\"]",match[1] if match else b'')
    if not version:raise ValueError('Framework version missing')
    version=version[1].decode();schema=json.loads(payload['database/schema.json'])['version']
    if key_file.resolve().is_relative_to(ROOT):raise ValueError('Signing key must be outside the checkout')
    key=serialization.load_pem_private_key(key_file.read_bytes(),password=None)
    if not isinstance(key,rsa.RSAPrivateKey) or key.key_size<3072:raise ValueError('RSA key of at least 3072 bits required')
    public=key.public_key();public_der=public.public_bytes(serialization.Encoding.DER,serialization.PublicFormat.SubjectPublicKeyInfo)
    manifest={'format':1,'type':'full','version':version,'commit':commit,'schema_version':schema,'php_min':'8.3','files':{p:hashlib.sha256(b).hexdigest() for p,b in sorted(payload.items())}}
    raw=(json.dumps(manifest,sort_keys=True,separators=(',',':'))+'\n').encode()
    signature=base64.b64encode(key.sign(raw,padding.PKCS1v15(),hashes.SHA256()))+b'\n'
    output.mkdir(parents=True,exist_ok=False)
    epoch=int(git('show','-s','--format=%ct',commit));timestamp=datetime.datetime.fromtimestamp(max(epoch,315532800),datetime.timezone.utc).timetuple()[:6]
    name='server-management-framework-'+version+'-full.zip'
    with zipfile.ZipFile(output/name,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=9) as archive:
        for path,data in [('manifest.json',raw),('manifest.sig',signature),*sorted(payload.items())]:
            info=zipfile.ZipInfo(path,timestamp);info.create_system=3;info.external_attr=0o100644<<16;info.compress_type=zipfile.ZIP_DEFLATED
            archive.writestr(info,data,compress_type=zipfile.ZIP_DEFLATED,compresslevel=9)
    # Separately signed components always originate from exactly the same committed tree.
    modules={name.split('/')[1]:json.loads(data) for name,data in payload.items() if re.fullmatch(r'extensions/[^/]+/module.json',name)}
    groups={'framework.core':{p:b for p,b in payload.items() if not p.startswith('extensions/') and not p.startswith('public/assets/shop/')},'commerce.shop':{p:b for p,b in payload.items() if p.startswith('extensions/shop.') or p.startswith('public/assets/shop/')}}
    for module in modules:
        if not module.startswith('shop.'):groups[module]={p:b for p,b in payload.items() if p.startswith('extensions/'+module+'/')}
    components=[]
    def component_zip(filename,metadata,content):
        document=metadata|{'files':{p:hashlib.sha256(b).hexdigest() for p,b in sorted(content.items())}}
        document_raw=(json.dumps(document,sort_keys=True,separators=(',',':'))+'\n').encode()
        document_sig=base64.b64encode(key.sign(document_raw,padding.PKCS1v15(),hashes.SHA256()))+b'\n'
        with zipfile.ZipFile(output/filename,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=9)as archive:
            for path,data in [('manifest.json',document_raw),('manifest.sig',document_sig),*sorted(content.items())]:
                entry=zipfile.ZipInfo(path,timestamp);entry.create_system=3;entry.external_attr=0o100644<<16;entry.compress_type=zipfile.ZIP_DEFLATED;archive.writestr(entry,data,compress_type=zipfile.ZIP_DEFLATED,compresslevel=9)
        return {'id':metadata.get('id',metadata['type']),'package':filename,'files':len(content),'sha256':hashlib.sha256((output/filename).read_bytes()).hexdigest()}
    for identity,content in groups.items():
        included={mid:{'version':m['version'],'requires':m['requires'],'core':m['core'],'php':m['php']}for mid,m in modules.items() if 'extensions/'+mid+'/module.json' in content}
        metadata={'format':2,'type':'component','id':identity,'version':version if identity in ['framework.core','commerce.shop'] else modules[identity]['version'],'release_version':version,'commit':commit,'php_min':'8.3','core':'>='+version+' <4.0.0','modules':included}
        if identity=='framework.core':content=content|{'deploy/package-files.json':(json.dumps(sorted(content),indent=2)+'\n').encode()}
        components.append(component_zip('server-management-framework-'+version+'-'+identity+'.zip',metadata,content))
    # Existing core updater overlays these files while preserving installed customer/provider components.
    core_update={p:b for p,b in groups['framework.core'].items() if p!='deploy/package-files.json' and not p.startswith(('public/','src/module/'))}
    components.append(component_zip('server-management-framework-'+version+'-core-update.zip',{'format':1,'type':'core','version':version,'commit':commit,'schema_version':schema,'php_min':'8.3'},core_update))
    (output/'manifest.json').write_bytes(raw);(output/'manifest.sig').write_bytes(signature)
    (output/'smf-release-public.pem').write_bytes(public.public_bytes(serialization.Encoding.PEM,serialization.PublicFormat.SubjectPublicKeyInfo))
    (output/'RELEASE-NOTES.md').write_bytes(payload['docs/releases/'+version+'.md'])
    info={'version':version,'commit':commit,'schema_version':schema,'package':name,'package_files':len(payload),'components':components,'key_fingerprint_sha256':hashlib.sha256(public_der).hexdigest(),'archive_sha256':hashlib.sha256((output/name).read_bytes()).hexdigest()}
    (output/'build-info.json').write_text(json.dumps(info,indent=2)+'\n')
    checksums=''.join(hashlib.sha256(p.read_bytes()).hexdigest()+'  '+p.name+'\n' for p in sorted(output.iterdir()))
    (output/'SHA256SUMS').write_text(checksums)
    (output/'SHA256SUMS.sig').write_bytes(key.sign(checksums.encode(),padding.PKCS1v15(),hashes.SHA256()))
    public.verify(base64.b64decode(signature),raw,padding.PKCS1v15(),hashes.SHA256())
    return info
if __name__=='__main__':
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--ref',default='HEAD');parser.add_argument('--output',type=Path,required=True);parser.add_argument('--key',type=Path,required=True);args=parser.parse_args()
    print(json.dumps(build(args.ref,args.output,args.key),indent=2))
