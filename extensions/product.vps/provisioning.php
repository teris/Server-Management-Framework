<?php
function provision_proxmox(array $order,array &$job,array $profile,array $data): void {
    if(!proxmox_has_resource_target())throw new RuntimeException('Proxmox-Bereitstellung benötigt Ziel-Node und Shop-Ressourcenpool.');
    $api=new ProxmoxProvider();$config=integration_config('proxmox');$node=$config['node'];$templateNode=$profile['template_node']?:$node;
    if($job['phase']==='start'){
        $template=$api->vmConfig($templateNode,(int)$profile['template_vmid']);if(empty($template['template']))throw new RuntimeException('Die konfigurierte VM ist kein Proxmox-Template.');
        $storage=reserve_vps_storage($job,$node,'qemu',vps_storage_required($template,(int)($profile['disk_extra_gb']??0)),$api);
        $vmid=(int)$api->call('GET','/cluster/nextid');if($vmid<100)throw new ProviderFailure('proxmox');
        $data=['vmid'=>$vmid,'node'=>$node,'storage'=>$storage,'name'=>'oc-'.$order['id']]+proxmox_disk_configuration($template,(int)($profile['disk_extra_gb']??0));save_job_phase($job,'cloning',$data,'running');
        $clone=['newid'=>$vmid,'name'=>$data['name'],'full'=>1,'target'=>$node,'pool'=>$config['pool'],'description'=>'Server Management '.$order['reference']];$clone['storage']=$data['storage'];
        $data['task']=(string)$api->call('POST','/nodes/'.rawurlencode($templateNode).'/qemu/'.(int)$profile['template_vmid'].'/clone',$clone);
        $data['task_node']=$templateNode;if(!$data['task'])throw new ProviderFailure('proxmox');save_job_phase($job,'clone_wait',$data);return;
    }
    if($job['phase']==='clone_wait'){
        $task=$api->call('GET','/nodes/'.rawurlencode($data['task_node']).'/tasks/'.rawurlencode($data['task']).'/status');
        if(($task['status']??'')!=='stopped'){save_job_phase($job,'clone_wait',$data);return;}
        if(($task['exitstatus']??'')!=='OK')throw new RuntimeException('Proxmox hat das Klonen nicht erfolgreich abgeschlossen. Bitte Aufgabe prüfen.');
        save_job_phase($job,'clone_ready',$data);
    }
    if($job['phase']==='clone_ready'){
        $data['password']=bin2hex(random_bytes(16));
        // Generated login credentials stay encrypted even while a VM is being configured.
        $saved=$data;unset($saved['password']);$saved['credentials']=secret_seal(['username'=>$profile['cloud_user'],'password'=>$data['password']],'job:'.$job['id']);
        save_job_phase($job,'configuring',$saved,'running');
        $data=$saved;
    }
    if($job['phase']==='configuring'){
        $credentials=secret_open($data['credentials'],'job:'.$job['id']);
        $api->call('PUT','/nodes/'.rawurlencode($node).'/qemu/'.$data['vmid'].'/config',['cores'=>(int)$profile['cores'],'memory'=>(int)$profile['memory_mb'],'ciuser'=>$profile['cloud_user'],'cipassword'=>$credentials['password'],'ipconfig0'=>$profile['ipconfig0']]);
        save_job_phase($job,'configured',$data);
    }
    if($job['phase']==='disk_resize_wait'){
        $task=$api->call('GET','/nodes/'.rawurlencode($node).'/tasks/'.rawurlencode($data['disk_resize_task']).'/status');if(($task['status']??'')!=='stopped'){save_job_phase($job,'disk_resize_wait',$data);return;}if(($task['exitstatus']??'')!=='OK')throw new RuntimeException('Proxmox hat die Plattenvergrößerung nicht erfolgreich bestätigt.');$vm=$api->vmConfig($node,(int)$data['vmid']);if(proxmox_disk_gb($vm[$data['disk_device']]??'')<(int)$data['disk_target_gb'])throw new RuntimeException('Die bestätigte Plattengröße stimmt nicht mit der Bestellung überein.');$data['disk_done']=true;save_job_phase($job,'configured',$data);
    }
    if($job['phase']==='configured'){
        if(!provision_proxmox_disk($order,$job,$data,$api,$node))return;
        $vm=$api->vmConfig($node,(int)$data['vmid']);if(!$api->poolContains($node,(int)$data['vmid']) || ($vm['name']??'')!==$data['name'])throw new RuntimeException('Die neue VM konnte nicht eindeutig dem Auftrag zugeordnet werden.');
        if(!empty($profile['start_after_clone']) && ($api->vm($node,(int)$data['vmid'])['status']??'')!=='running'){$api->control($node,(int)$data['vmid'],'start');}
        $credentials=secret_open($data['credentials'],'job:'.$job['id']);
        finish_provisioning($order,$job,'qemu/'.$data['vmid'],['node'=>$node,'vmid'=>(int)$data['vmid'],'storage'=>$data['storage']??$profile['storage']],$data['name'],$credentials);
    }
}
