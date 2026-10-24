<?php
function proxmox_disk_gb(string $spec): float {
    if(!preg_match('/(?:^|,)size=(\d+(?:\.\d+)?)([KMGT]?)(?:,|$)/D',$spec,$m))throw new RuntimeException('Die Größe der Systemplatte muss im Proxmox-Template hinterlegt sein.');return (float)$m[1]*(['K'=>1/1048576,'M'=>1/1024,'G'=>1,'T'=>1024,''=>1/1073741824][$m[2]]);
}
function proxmox_disk_configuration(array $template,int $extra): array {
    if(!$extra)return [];$boot=[];if(preg_match('/(?:^|,)order=([^,]+)/',(string)($template['boot']??''),$m))$boot=explode(';',$m[1]);if(!empty($template['bootdisk']))array_unshift($boot,$template['bootdisk']);$disks=array_unique([...$boot,...array_keys($template)]);
    foreach($disks as $disk){if(!preg_match('/^(?:scsi|virtio|sata|ide)\d+$/D',$disk) || !isset($template[$disk]) || str_contains($template[$disk],'media=cdrom') || str_contains($template[$disk],'cloudinit'))continue;$current=proxmox_disk_gb($template[$disk]);return ['disk_device'=>$disk,'disk_target_gb'=>(int)ceil($current)+$extra];}
    throw new RuntimeException('Für zusätzliche Festplatte wird ein Template mit eindeutig erkennbarer Systemplatte benötigt.');
}
function provision_proxmox_disk(array $order,array &$job,array &$data,ProxmoxProvider $api,string $node): bool {
    if(empty($data['disk_target_gb']) || !empty($data['disk_done']))return true;$path='/nodes/'.rawurlencode($node).'/qemu/'.(int)$data['vmid'];$vm=$api->vmConfig($node,(int)$data['vmid']);$disk=$data['disk_device'];if(($vm['name']??'')!==($data['name']??'') || !$api->poolContains($node,(int)$data['vmid']))throw new RuntimeException('Die VM gehört nicht mehr eindeutig zu diesem Auftrag.');if(!isset($vm[$disk]))throw new RuntimeException('Die Systemplatte der neuen VM hat sich geändert.');
    if(proxmox_disk_gb($vm[$disk])>=(int)$data['disk_target_gb']){$data['disk_done']=true;save_job_phase($job,'configured',$data);return true;}
    save_job_phase($job,'disk_resizing',$data,'running');$task=$api->call('PUT',$path.'/resize',['disk'=>$disk,'size'=>(int)$data['disk_target_gb'].'G']);if(!is_string($task) || !$task)throw new RuntimeException('Die Plattenvergrößerung wurde angefragt; vor einer Fortsetzung den Proxmox-Zustand prüfen.');$data['disk_resize_task']=$task;save_job_phase($job,'disk_resize_wait',$data);return false;
}
