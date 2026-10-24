#!/usr/bin/env python3
"""Node-local worker. Only administrator-created, fixed-revision template jobs run."""
import fcntl, ipaddress, json, os, re, secrets, shutil, socket, subprocess, tarfile, time, urllib.request, urllib.parse
from pathlib import Path

ROOT = Path('/var/lib/orga-template-runner')
CONFIG = Path('/etc/orga-template-runner/config.json')
ALLOWED = {'debian','ubuntu','postgresql','dolibarr','apache-tomcat','caddy','n8n','nextcloudpi','centos','fedora','gentoo','archlinux','docker'}


def command(args, **kwargs):
    return subprocess.run(args, check=True, timeout=kwargs.pop('timeout', 120), stdin=kwargs.pop('stdin', subprocess.DEVNULL), stdout=kwargs.pop('stdout', subprocess.PIPE), stderr=kwargs.pop('stderr', subprocess.PIPE), text=True, **kwargs)


def api(cfg, message):
    raw=json.dumps(dict(message,node=cfg['node'])).encode()
    req=urllib.request.Request(cfg['url'],data=raw,headers={'Authorization':'Bearer '+cfg['token'],'Content-Type':'application/json'},method='POST')
    with urllib.request.urlopen(req, timeout=45) as response:
        return json.load(response)


def checked_name(value):
    if not isinstance(value,str) or not re.fullmatch(r'[\w.-]{1,80}', value, flags=re.ASCII):
        raise ValueError('Invalid Proxmox identifier')
    return value


def validate_job(job, node):
    p=job['payload']; t=p['template']; recipe=p['recipe']; n=p['network']
    if t['node'] != node or recipe['slug'] not in ALLOWED or recipe['recipe'] not in ('','ubuntu-webserver'):
        raise ValueError('Unsupported template recipe')
    for name in ('scripts_commit','core_commit'):
        if not re.fullmatch(r'[a-f0-9]{40}',recipe[name]): raise ValueError('Unpinned recipe')
    for value in (p['backup_storage'], p['template_storage'], t['storage'], n['bridge']): checked_name(value)
    ipaddress.IPv4Address(n['address']); ipaddress.IPv4Address(n['gateway'])
    if int(n['prefix_len']) != 32 or not re.fullmatch(r'(?:[A-Fa-f0-9]{2}:){5}[A-Fa-f0-9]{2}',n['mac']):
        raise ValueError('Fixed IPv4/MAC required')
    if not re.fullmatch(r'[a-f0-9]{48}',p['claim']): raise ValueError('Invalid build claim')
    return p


def checkout(repo, commit):
    dest=ROOT/'sources'/(repo+'-'+commit)
    if (dest/'.complete').exists(): return dest
    dest.mkdir(parents=True, exist_ok=True)
    archive=dest/'source.tar.gz'
    request=urllib.request.Request('https://codeload.github.com/community-scripts/'+repo+'/tar.gz/'+commit,headers={'User-Agent':'Orga-Template-Runner/1'})
    with urllib.request.urlopen(request,timeout=90) as response, archive.open('wb') as out:
        total=0
        while True:
            chunk=response.read(1024*1024)
            if not chunk: break
            total+=len(chunk)
            if total>100*1024*1024: raise ValueError('Source archive too large')
            out.write(chunk)
    with tarfile.open(archive) as source:
        for member in source:
            parts=Path(member.name).parts[1:]
            if not parts or '..' in parts or Path(*parts).is_absolute(): continue
            target=dest.joinpath(*parts)
            if member.isdir(): target.mkdir(parents=True, exist_ok=True)
            elif member.isfile():
                target.parent.mkdir(parents=True,exist_ok=True)
                if member.size>20*1024*1024: raise ValueError('Source member too large')
                with source.extractfile(member) as src, target.open('wb') as out: shutil.copyfileobj(src,out)
                target.chmod(0o600)
    archive.unlink();(dest/'.complete').touch();return dest


def recipe_resources(script, settings):
    result={}
    for key,source,fallback in [('min_cores','cpu',1),('min_memory_mb','ram',1024),('disk_gb','disk',8)]:
        found=re.findall(r'var_'+source+r'=.*?:-([0-9]+)',script)
        result[key]=max([int(settings[key]),fallback]+[int(x) for x in found])
    if result['min_cores']>128 or result['min_memory_mb']>1048576 or result['disk_gb']>65536: raise ValueError('Build limits exceeded')
    return result


def build_environment(p,vmid,sources,core,resources):
    n=p['network'];s=p['settings'];slug=p['recipe']['slug']
    # Use a minimal environment: host credentials and the runner token never enter scripts.
    env={'PATH':'/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin','HOME':str(ROOT/'home'),'TERM':'dumb','LANG':'C.UTF-8',
         'COMMUNITY_SCRIPTS_ROOT':str(sources),'COMMUNITY_SCRIPTS_CORE_DIR':str(core),
         'COMMUNITY_SCRIPTS_URL':'https://raw.githubusercontent.com/community-scripts/ProxmoxVE/'+p['recipe']['scripts_commit'],
         'COMMUNITY_SCRIPTS_CORE_URL':'https://raw.githubusercontent.com/community-scripts/core/'+p['recipe']['core_commit'],
         'mode':'generated','PHS_SILENT':'1','var_unattended':'yes','UNATTENDED':'1',
         'var_ctid':str(vmid),'var_hostname':'oc-build-'+str(p['_job_id']),
         'var_cpu':str(resources['min_cores']),'var_ram':str(resources['min_memory_mb']),'var_disk':str(resources['disk_gb']),
         'var_brg':n['bridge'],'var_net':n['address']+'/32','var_gateway':n['gateway'],'var_mac':n['mac'],
         'var_ns':s['nameserver'],'var_ipv6_method':'none','var_unprivileged':str(s['unprivileged']),
         'var_nesting':str(s['nesting']),'var_keyctl':str(s['keyctl']),'var_pw':'Oc!'+secrets.token_hex(24),
         'var_ssh':'yes','var_timezone':'Europe/Berlin','var_tags':'orga-template-build',
         'var_template_storage':p['template_storage'],'var_container_storage':p['template']['storage']}
    if slug in ('debian','ubuntu'): env['var_os']=slug
    elif slug not in ('centos','fedora','gentoo','archlinux'): env['var_os']='debian'
    if s.get('build_os'): env['var_os']=s['build_os']
    if s.get('build_version'): env['var_version']=s['build_version']
    return env


def own_config(vmid, p):
    cfg=json.loads(command(['pvesh','get','/nodes/'+p['template']['node']+'/lxc/'+str(vmid)+'/config','--output-format','json']).stdout)
    if cfg.get('hostname')!='oc-build-'+str(p['_job_id']): raise ValueError('Builder ownership changed')
    net=dict(item.split('=',1) for item in cfg.get('net0','').split(',') if '=' in item)
    n=p['network']
    if net.get('ip')!=n['address']+'/32' or net.get('hwaddr','').upper()!=n['mac'].upper() or net.get('bridge')!=n['bridge'] or net.get('gw')!=n['gateway']:
        raise ValueError('Builder network changed')
    if any(re.fullmatch(r'mp[0-9]+',k) for k in cfg): raise ValueError('Additional mountpoints require manual review')
    return cfg


def prepare_archive(vmid,p):
    own_config(vmid,p)
    # Root credentials and SSH identity must be unique when restoring a customer server.
    sanitize=r'''set -eu
passwd -l root >/dev/null
rm -f /etc/ssh/ssh_host_* /root/.bash_history /root/.ssh/authorized_keys
mkdir -p /etc/systemd/system /var/lib/orga-firstboot
cat > /etc/systemd/system/orga-firstboot.service <<'EOF'
[Unit]
Description=Initialize unique server identity
Before=ssh.service sshd.service
After=systemd-remount-fs.service
ConditionPathExists=!/var/lib/orga-firstboot/done
[Service]
Type=oneshot
ExecStart=/bin/sh -c 'command -v ssh-keygen >/dev/null && ssh-keygen -A; touch /var/lib/orga-firstboot/done'
[Install]
WantedBy=multi-user.target
EOF
systemctl enable orga-firstboot.service
rm -f /var/lib/orga-firstboot/done
: > /etc/machine-id
rm -f /var/lib/dbus/machine-id
'''
    command(['pct','exec',str(vmid),'--','sh','-c',sanitize])
    command(['pct','stop',str(vmid)],timeout=180)
    own_config(vmid,p)
    command(['vzdump',str(vmid),'--storage',p['backup_storage'],'--compress','zstd','--mode','stop','--remove','0'],timeout=3600)
    content=json.loads(command(['pvesh','get','/nodes/'+p['template']['node']+'/storage/'+p['backup_storage']+'/content','--output-format','json']).stdout)
    candidates=[v for v in content if re.fullmatch(re.escape(p['backup_storage'])+r':backup/vzdump-lxc-'+str(vmid)+r'-[\w.-]+\.tar\.(?:zst|gz|lzo)',v.get('volid',''))]
    if not candidates: raise ValueError('Build backup missing')
    archive=max(candidates,key=lambda v:int(v.get('ctime',0)))['volid']
    own_config(vmid,p)
    command(['pct','destroy',str(vmid)],timeout=300)
    return archive


def run_job(cfg,job):
    p=validate_job(job,cfg['node']);p['_job_id']=int(job['id'])
    record=ROOT/('job-'+str(job['id'])+'.json')
    # Durable local journal before creating anything. An interrupted build is never repeated.
    if record.exists():
        existing=json.loads(record.read_text())
        if existing.get('state') in ('done','failed'): api(cfg,existing);return
        raise ValueError('Interrupted build needs operator review')
    vmid=int(command(['pvesh','get','/cluster/nextid']).stdout.strip())
    if vmid<100: raise ValueError('Invalid container ID')
    state={'action':'report','id':int(job['id']),'claim':p['claim'],'state':'running','vmid':vmid}
    def persist():
        temp=record.with_suffix('.tmp');temp.write_text(json.dumps(state));temp.chmod(0o600);temp.replace(record)
    persist();api(cfg,state)
    try:
        for storage,content in [(p['backup_storage'],'backup'),(p['template_storage'],'vztmpl'),(p['template']['storage'],'rootdir')]:
            info=json.loads(command(['pvesh','get','/nodes/'+cfg['node']+'/storage/'+storage+'/status','--output-format','json']).stdout)
            if not info.get('active') or content not in info.get('content','').split(','): raise ValueError('Unsupported storage')
        sources=checkout('ProxmoxVE',p['recipe']['scripts_commit']);core=checkout('core',p['recipe']['core_commit'])
        entry=sources/'ct'/(p['recipe']['slug']+'.sh');resources=recipe_resources(entry.read_text(),p['settings']);state['resources']=resources;persist()
        env=build_environment(p,vmid,sources,core,resources);Path(env['HOME']).mkdir(exist_ok=True)
        log=ROOT/('job-'+str(job['id'])+'.log')
        with log.open('w') as output:
            log.chmod(0o600)
            command(['/bin/bash',str(entry),'generated'],env=env,stdout=output,stderr=subprocess.STDOUT,timeout=7200)
        own_config(vmid,p)
        if p['recipe']['recipe']=='ubuntu-webserver':
            command(['pct','exec',str(vmid),'--','sh','-c','export DEBIAN_FRONTEND=noninteractive; apt-get update && apt-get install -y apache2 php libapache2-mod-php'],timeout=1800)
        state['archive']=prepare_archive(vmid,p);state['state']='done';persist();api(cfg,state);state['reported']=True;persist()
    except Exception:
        if state.get('state')=='done': raise
        state['state']='failed';persist()
        try: api(cfg,state);state['reported']=True;persist()
        except Exception: pass
        raise


def main():
    os.umask(0o077);ROOT.mkdir(mode=0o700,parents=True,exist_ok=True)
    with (ROOT/'worker.lock').open('w') as lock:
        try: fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
        except BlockingIOError: return
        cfg=json.loads(CONFIG.read_text())
        if os.geteuid()!=0 or socket.gethostname().split('.')[0]!=cfg['node']: raise ValueError('Wrong Proxmox node')
        parsed=urllib.parse.urlsplit(cfg['url'])
        if parsed.scheme!='https' or parsed.username or parsed.password or parsed.path!='/api/infrastructure/runner': raise ValueError('Invalid shop endpoint')
        # Retry final reports if HTTPS was briefly unavailable after successful cleanup.
        for record in ROOT.glob('job-*.json'):
            state=json.loads(record.read_text())
            if state.get('state')=='running':
                # A previous process ended while building. Preserve the reservation; never replay it.
                state['state']='failed';record.write_text(json.dumps(state))
            if state.get('state') in ('done','failed') and not state.get('reported'):
                try: api(cfg,state);state['reported']=True;record.write_text(json.dumps(state))
                except Exception: return
        job=api(cfg,{'action':'poll'}).get('job')
        if job: run_job(cfg,job)

if __name__=='__main__':
    try: main()
    except Exception:
        # Application installers can output credentials: keep detailed logs local, root-only.
        print('Vorlagenauftrag konnte nicht abgeschlossen werden. Status im Shop-Backend und lokales Bauprotokoll prüfen.')
        raise SystemExit(1)
