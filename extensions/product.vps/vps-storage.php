<?php
function vps_storage_required(array $config,int $extra=0): int {
    $gb=0;foreach($config as $key=>$value)if(preg_match('/^(?:(?:scsi|virtio|sata|ide)\d+|efidisk0|tpmstate0)$/D',(string)$key) && is_string($value) && !str_contains($value,'media=cdrom') && !str_contains($value,'cloudinit'))$gb+=proxmox_disk_gb($value);
    if($gb<=0)throw new RuntimeException('Die vollständige Plattengröße der VPS-Vorlage ist nicht erkennbar.');return (int)ceil(($gb+$extra)*1073741824);
}
function reserve_vps_storage(array $job,string $node,string $type,int $bytes,ProxmoxProvider $api): string {
    if($bytes<=0 || !in_array($type,['lxc','qemu'],true) || !preg_match('/^[\w.-]{1,80}$/D',$node))throw new RuntimeException('Ungültige VPS-Speicheranforderung.');
    try{query('INSERT INTO shop_storage_gates (node) VALUES (?)',[$node]);}catch(PDOException $e){if(!one('SELECT node FROM shop_storage_gates WHERE node=?',[$node]))throw $e;}
    db()->beginTransaction();try{
        $lock=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';one('SELECT node FROM shop_storage_gates WHERE node=?'.$lock,[$node]);
        if($existing=one('SELECT * FROM shop_storage_reservations WHERE job_id=?',[$job['id']])){if($existing['node']!==$node || (int)$existing['bytes']!==$bytes)throw new RuntimeException('Die gespeicherte Speicherreservierung muss geprüft werden.');db()->commit();return $existing['storage'];}
        $candidates=[];$content=$type==='lxc'?'rootdir':'images';
        foreach(['store01','store02'] as $store){
            try{$status=$api->call('GET','/nodes/'.rawurlencode($node).'/storage/'.$store.'/status');$volumes=$api->call('GET','/nodes/'.rawurlencode($node).'/storage/'.$store.'/content');}
            catch(ProviderFailure $e){throw new RuntimeException('Der Speichervergleich ist nicht vollständig möglich. Bitte Datastore.Audit für store01 und store02 prüfen.');}
            if(!is_array($status) || !is_array($volumes) || !is_numeric($status['avail']??null) || !is_numeric($status['total']??null))throw new RuntimeException('Proxmox hat keine vollständigen Speicherkapazitäten geliefert.');
            if(empty($status['active']) || empty($status['enabled']) || !in_array($content,explode(',',$status['content']??''),true))continue;
            $reserved=(int)query('SELECT COALESCE(SUM(bytes),0) FROM shop_storage_reservations WHERE node=? AND storage=?',[$node,$store])->fetchColumn();
            $committed=0;foreach($volumes as $volume)if(in_array($volume['content']??'',['images','rootdir'],true)){$size=$volume['size']??null;if(!is_numeric($size))throw new RuntimeException('Die belegte Plattenkapazität ist nicht vollständig erkennbar.');$committed+=(int)$size;}
            $headroom=max(1073741824,(int)ceil((int)$status['total']*0.05));$free=(int)$status['avail']-$reserved;
            // Also account for sparse/thin disks that can grow to their purchased capacity.
            $safe=min($free,(int)$status['total']-$committed-$reserved)-$headroom;
            if($safe>=$bytes)$candidates[$store]=$free;
        }
        if(!$candidates)throw new RuntimeException('Auf store01 und store02 ist für diesen VPS einschließlich Sicherheitsreserve nicht genügend freier Speicher verfügbar.');
        arsort($candidates,SORT_NUMERIC);$chosen=(string)array_key_first($candidates);query('INSERT INTO shop_storage_reservations (job_id,node,storage,bytes,created_ts) VALUES (?,?,?,?,?)',[$job['id'],$node,$chosen,$bytes,shop_now()]);db()->commit();return $chosen;
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
