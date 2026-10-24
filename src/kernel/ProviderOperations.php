<?php
namespace SMF\Core;
use PDO;
use RuntimeException;
final class ProviderOperations {
    public function __construct(private PDO $db,private AuditLog $audit) {}
    /** Persist intent before I/O. Uncertain responses require explicit provider reconciliation. */
    public function perform(string $provider,string $connection,string $key,callable $write): array {
        if(!preg_match('/^[a-z0-9:_.-]{1,180}$/D',$key)||!preg_match('/^[a-f0-9]{64}$/D',$connection))throw new RuntimeException('Invalid operation identity');
        $id=bin2hex(random_bytes(16));$now=time();
        $sql='INSERT INTO smf_provider_operations (id,provider,connection_id,operation_key,request_id,state,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?)';
        $sql.=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' ON DUPLICATE KEY UPDATE id=id':' ON CONFLICT(operation_key) DO NOTHING';
        $this->db->prepare($sql)->execute([$id,$provider,$connection,$key,AuditLog::requestId(),'writing',$now,$now]);
        $query=$this->db->prepare('SELECT * FROM smf_provider_operations WHERE operation_key=?');$query->execute([$key]);$operation=$query->fetch(PDO::FETCH_ASSOC);$query->closeCursor();
        if(!$operation||$operation['provider']!==$provider||!hash_equals($connection,$operation['connection_id']))throw new RuntimeException('Operation belongs to another provider account');
        if($operation['id']!==$id){if($operation['state']!=='confirmed')throw new RuntimeException('Provider result is uncertain; reconcile before continuing');return ['operation_id'=>$operation['id'],'state'=>'confirmed','external_id'=>$operation['external_id']];}
        try {
            $result=$write();if($result===false||(is_array($result)&&isset($result['success'])&&!$result['success']))throw new RuntimeException('Provider did not confirm operation');
            $external=is_string($result)?substr($result,0,255):(is_array($result)?(string)($result['id']??$result['task_id']??''):'');
            $this->db->prepare("UPDATE smf_provider_operations SET state='confirmed',external_id=?,updated_at=? WHERE id=? AND state='writing'")->execute([$external,$now,$id]);
            $this->audit->record('provider.confirmed',$id,'ok',['provider'=>$provider]);return ['operation_id'=>$id,'state'=>'confirmed','external_id'=>$external];
        }catch(\Throwable $error){
            $this->db->prepare("UPDATE smf_provider_operations SET state='needs_review',updated_at=? WHERE id=?")->execute([time(),$id]);$this->audit->record('provider.uncertain',$id,'needs_review',['provider'=>$provider,'error_code'=>'UNCONFIRMED']);throw new RuntimeException('Provider result is uncertain; reconcile before continuing',0,$error);
        }
    }
}
