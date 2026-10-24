<?php
namespace SMF\Core;
use PDO;
use RuntimeException;
final class JobContext {
    public function __construct(private PDO $db, public readonly string $id, private string $token, public readonly string $environment) {}
    public function id(): string { return $this->id; }
    public function checkpoint(string $phase, array $payload, ?string $externalId=null): void {
        if(!preg_match('/^[a-zA-Z0-9_.-]{1,80}$/D',$phase))throw new RuntimeException('Invalid job phase');
        $q=$this->db->prepare('UPDATE smf_jobs SET phase=?,payload=?,external_id=?,updated_at=?,locked_until=CASE WHEN locked_until>=? THEN locked_until+1 ELSE ? END WHERE id=? AND lock_token=? AND state=\'running\' AND locked_until>=?');
        $q->execute([$phase,json_encode($payload,JSON_THROW_ON_ERROR),$externalId,time(),time()+600,time()+600,$this->id,$this->token,time()]);
        if($q->rowCount()!==1)throw new RuntimeException('Job lease lost');
    }
    public function beforeWrite(array $payload): void {
        if($this->environment!=='live' || !ShopSettings::allowLive())throw new RuntimeException('Sandbox jobs cannot write to live providers');
        $this->checkpoint('writing',$payload);
    }
}
