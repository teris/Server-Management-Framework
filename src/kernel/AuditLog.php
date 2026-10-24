<?php
namespace SMF\Core;
use PDO;
final class AuditLog {
    private static ?string $request = null;
    public function __construct(private PDO $db) {}
    public static function requestId(): string { return self::$request ??= bin2hex(random_bytes(16)); }
    public function record(string $event, string $subject='', string $result='ok', array $context=[], ?string $job=null,?string $realm=null): void {
        $identity = PHP_SAPI==='cli' ? null : ($realm!==null?\Identity::current($realm):(\Identity::current('backend') ?? \Identity::current('customer')));
        $safe=[];foreach(['provider','phase','state','order_id','service_id','invoice_id','module','error_code'] as $key) if(isset($context[$key]) && is_scalar($context[$key]))$safe[$key]=$context[$key];
        $this->db->prepare('INSERT INTO smf_audit (occurred_at,request_id,job_id,actor_realm,actor_id,event,subject,result,context) VALUES (?,?,?,?,?,?,?,?,?)')->execute([time(),self::requestId(),$job,$identity['realm']??'system',$identity['id']??null,substr($event,0,120),substr($subject,0,180),substr($result,0,32),json_encode($safe,JSON_THROW_ON_ERROR)]);
    }
    public function prune(int $days=90): int {
        if(PHP_SAPI!=='cli')throw new \RuntimeException('Log retention is CLI only');
        $q=$this->db->prepare('DELETE FROM smf_audit WHERE occurred_at<?');$q->execute([time()-max(7,min(3650,$days))*86400]);return $q->rowCount();
    }
}
