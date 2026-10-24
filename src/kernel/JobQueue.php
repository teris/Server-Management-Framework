<?php
namespace SMF\Core;
use PDO;
use RuntimeException;
final class JobQueue {
    public function __construct(private PDO $db, private ModuleRegistry $modules, private AuditLog $audit) {}
    public function enqueue(string $module, string $type, string $key, array $payload, string $environment='sandbox'): string {
        $this->modules->assertEnabled($module);$definition=$this->modules->jobs()[$type]??null;
        if(!$definition || $definition['module']!==$module || !in_array($environment,['sandbox','live'],true) || strlen($key)>180)throw new RuntimeException('Unregistered job');
        $id=bin2hex(random_bytes(16));$driver=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql='INSERT INTO smf_jobs (id,module_id,type,idempotency_key,payload,payload_hash,environment,available_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)';
        $sql.=$driver==='mysql'?' ON DUPLICATE KEY UPDATE id=id':' ON CONFLICT(idempotency_key) DO NOTHING';
        $this->db->prepare($sql)->execute([$id,$module,$type,$key,json_encode($payload,JSON_THROW_ON_ERROR),hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR)),$environment,time(),time(),time()]);
        $q=$this->db->prepare('SELECT id,module_id,type,payload_hash,environment FROM smf_jobs WHERE idempotency_key=?');$q->execute([$key]);$old=$q->fetch(PDO::FETCH_ASSOC);
        if(!$old || $old['module_id']!==$module || $old['type']!==$type || $old['environment']!==$environment || !hash_equals($old['payload_hash'],hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR))))throw new RuntimeException('Idempotency key belongs to another job');
        return $old['id'];
    }
    public function claim(): ?array {
        $handlers=$this->modules->jobs();
        $query=$this->db->prepare("SELECT id,type,phase FROM smf_jobs WHERE state='running' AND locked_until<?");$query->execute([time()]);
        foreach($query->fetchAll(PDO::FETCH_ASSOC)as$expired){
            $safe=($handlers[$expired['type']]['definition']['replay']??'reconcile')==='safe' && $expired['phase']!=='writing';
            $this->db->prepare("UPDATE smf_jobs SET state=?,lock_token=NULL,locked_until=0,last_error=? WHERE id=? AND state='running' AND locked_until<?")->execute([$safe?'queued':'needs_review',$safe?'LEASE_EXPIRED':'EXTERNAL_RESULT_UNCERTAIN',$expired['id'],time()]);
        }
        $query=$this->db->prepare("SELECT * FROM smf_jobs WHERE state='queued' AND available_at<=? ORDER BY created_at,id LIMIT 100");$query->execute([time()]);$rows=$query->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as$row){
            if(!isset($handlers[$row['type']]) || $handlers[$row['type']]['module']!==$row['module_id'] || !$this->modules->enabled($row['module_id']))continue;
            $token=bin2hex(random_bytes(32));$q=$this->db->prepare("UPDATE smf_jobs SET state='running',lock_token=?,locked_until=?,attempts=attempts+1,updated_at=? WHERE id=? AND state='queued'");$q->execute([$token,time()+600,time(),$row['id']]);
            if($q->rowCount()===1)return array_replace($row,['lock_token'=>$token,'locked_until'=>time()+600,'attempts'=>(int)$row['attempts']+1]);
        }
        return null;
    }
    public function runOne(array $handlers): bool {
        $job=$this->claim();if(!$job)return false;
        try{
            if(!isset($handlers[$job['type']]))throw new RuntimeException('Worker handler unavailable');
            $context=new JobContext($this->db,$job['id'],$job['lock_token'],$job['environment']);
            $handlers[$job['type']](json_decode($job['payload'],true,64,JSON_THROW_ON_ERROR),$context);
            $q=$this->db->prepare("UPDATE smf_jobs SET state='done',lock_token=NULL,locked_until=0,updated_at=? WHERE id=? AND lock_token=? AND locked_until>=?");$q->execute([time(),$job['id'],$job['lock_token'],time()]);
            if($q->rowCount()!==1)throw new RuntimeException('Job lease lost before completion');
            $this->audit->record('job.completed',$job['type'],'ok',[], $job['id']);
        }catch(\Throwable $error){
            $this->db->prepare("UPDATE smf_jobs SET state='needs_review',last_error='JOB_FAILED',lock_token=NULL,locked_until=0,updated_at=? WHERE id=? AND lock_token=?")->execute([time(),$job['id'],$job['lock_token']]);
            $this->audit->record('job.failed',$job['type'],'needs_review',['error_code'=>'JOB_FAILED'],$job['id']);
        }
        return true;
    }
    public function dispatchOutbox(int $limit=50): int {
        $count=0;
        $query=$this->db->prepare('SELECT * FROM smf_outbox WHERE delivered_at IS NULL ORDER BY created_at,id LIMIT ?');$query->bindValue(1,max(1,min(500,$limit)),PDO::PARAM_INT);$query->execute();$rows=$query->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as $row){
            if(!$this->modules->enabled($row['module_id']) || !isset($this->modules->jobs()[$row['type']]))continue;
            $this->db->beginTransaction();
            try {
                $this->enqueue($row['module_id'],$row['type'],'outbox:'.$row['id'],json_decode($row['payload'],true,64,JSON_THROW_ON_ERROR));
                $this->db->prepare('UPDATE smf_outbox SET delivered_at=? WHERE id=? AND delivered_at IS NULL')->execute([time(),$row['id']]);
                $this->db->commit();$count++;
            }catch(\Throwable $error){if($this->db->inTransaction())$this->db->rollBack();throw $error;}
        }return $count;
    }
    public function publish(string $module,string $key,string $type,array $payload): void {
        $this->modules->assertEnabled($module);$sql='INSERT INTO smf_outbox (id,module_id,event_key,type,payload,created_at) VALUES (?,?,?,?,?,?)';
        $sql.=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' ON DUPLICATE KEY UPDATE id=id':' ON CONFLICT(event_key) DO NOTHING';
        $this->db->prepare($sql)->execute([bin2hex(random_bytes(16)),$module,$key,$type,json_encode($payload,JSON_THROW_ON_ERROR),time()]);
        $query=$this->db->prepare('SELECT module_id,type,payload FROM smf_outbox WHERE event_key=?');$query->execute([$key]);$old=$query->fetch(PDO::FETCH_ASSOC);$query->closeCursor();
        if(!$old||$old['module_id']!==$module||$old['type']!==$type||$old['payload']!==json_encode($payload,JSON_THROW_ON_ERROR))throw new RuntimeException('Outbox key belongs to another event');
    }
}
