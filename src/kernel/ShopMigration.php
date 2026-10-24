<?php
namespace SMF\Core;
use PDO;
use RuntimeException;
/** Imports a frozen SQLite copy. The source is never opened for writing. */
final class ShopMigration {
    private PDO $source;
    private string $digest;
    private array $rows=[];
    private array $maps=[];
    private array $usernames=[];
    private array $summary=[];
    public function __construct(private PDO $target,private string $sourceFile,private string $oldKey,private ?string $documents=null) {
        if(PHP_SAPI!=='cli' && !defined('SMF_DEPLOYMENT_MIGRATION'))throw new RuntimeException('Offline migration only');
        if(is_link($sourceFile)||!is_file($sourceFile)||$oldKey==='')throw new RuntimeException('Frozen database and original key required');
        $this->digest=hash_file('sha256',$sourceFile);
        $this->source=new PDO('sqlite:'.$sourceFile,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$this->source->exec('PRAGMA query_only=ON');
        foreach($this->source->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY rowid")->fetchAll(PDO::FETCH_COLUMN)as$table){
            if(!preg_match('/^(?:shop_[a-z0-9_]+|users|customers|support_tickets|ticket_replies)$/D',$table))continue;
            if(!\SchemaManager::hasTable($target,$table))throw new RuntimeException('Target schema missing: '.$table);
            $this->rows[$table]=$this->source->query('SELECT * FROM "'.$table.'"')->fetchAll();
        }
    }
    private function id(string $table): string {return \SchemaManager::identifier($table,$this->target->getAttribute(PDO::ATTR_DRIVER_NAME));}
    private function open(string $sealed,string $context): array {
        $raw=base64_decode($sealed,true);if($raw===false||strlen($raw)<29)throw new RuntimeException('Encrypted source value invalid');
        $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',hash_hkdf('sha256',$this->oldKey,32,'orga integration credentials'),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),$context);
        if($plain===false)throw new RuntimeException('Original shop key or encryption context does not match');
        $value=json_decode($plain,true,64,JSON_THROW_ON_ERROR);if(!is_array($value))throw new RuntimeException('Invalid encrypted document');return $value;
    }
    private function reseal(string $sealed,string $oldContext,?string $newContext=null): string {
        $value=$this->open($sealed,$oldContext);$this->summary['encrypted_values_verified']++;
        $new=\secret_seal($value,$newContext??$oldContext);
        if(\secret_open($new,$newContext??$oldContext)!==$value)throw new RuntimeException('Target encryption verification failed');return $new;
    }
    private function transform(string $table,array $row): array {
        $old=$row;
        if($table==='users'&&isset($this->usernames[(string)$row['id']]))$row['username']=$this->usernames[(string)$row['id']];
        foreach(['customer_id'=>'customers','user_id'=>'users','admin_id'=>'users','created_by'=>'users','ticket_id'=>'support_tickets']as$column=>$reference)if(isset($row[$column])&&isset($this->maps[$reference][(string)$row[$column]]))$row[$column]=$this->maps[$reference][(string)$row[$column]];
        if($table==='shop_audit'&&isset($row['actor_id'])){$kind=($row['actor_type']??'')==='customer'?'customers':'users';$row['actor_id']=$this->maps[$kind][(string)$row['actor_id']]??$row['actor_id'];}
        if($table==='shop_integrations')$row['secret_config']=$this->reseal($row['secret_config'],'provider:'.$row['provider']);
        if($table==='shop_resource_links'&&!empty($row['credentials']))$row['credentials']=$this->reseal($row['credentials'],'service:'.$row['service_id']);
        if($table==='shop_child_credentials')$row['secret']=$this->reseal($row['secret'],'hosting-child:'.$row['child_id']);
        if($table==='shop_provider_account_logins')$row['secret']=$this->reseal($row['secret'],'provider-account:'.$row['provider'].':'.$old['customer_id'],'provider-account:'.$row['provider'].':'.$row['customer_id']);
        if($table==='shop_template_runners')$row['secret_token']=$this->reseal($row['secret_token'],'template-runner:'.$row['node']);
        if($table==='shop_meta' && in_array($row['meta_key'],['smtp_config','ovh_pending_authorization'],true))$row['meta_value']=$this->reseal($row['meta_value'],$row['meta_key']==='smtp_config'?'commerce:smtp':'ovh:pending-authorization');
        if($table==='shop_jobs'){
            $payload=json_decode($row['payload'],true,64,JSON_THROW_ON_ERROR);
            foreach(['credentials'=>'job:'.$row['id'],'client_credentials'=>'job:'.$row['id'].':ispconfig','ftp_credentials'=>'job:'.$row['id'].':ftp']as$key=>$context)if(!empty($payload[$key]))$payload[$key]=$this->reseal($payload[$key],$context);
            if(isset($payload['password']))throw new RuntimeException('Legacy job contains plaintext credentials; explicit conversion required');
            $row['payload']=json_encode($payload,JSON_THROW_ON_ERROR);
            // Stop every copied worker at the cutover boundary. No source worker is allowed to resume implicitly.
            if(in_array($row['state'],['running','queued','waiting'],true))$row['state']='needs_action';$row['lock_token']=null;$row['locked_until']=0;
        }
        if($table==='shop_mail_queue'){
            $payload=json_decode($row['payload'],true,64,JSON_THROW_ON_ERROR);
            if(!empty($payload['sealed_body']))$payload['sealed_body']=$this->reseal($payload['sealed_body'],'mail:'.$row['event_key']);
            $row['payload']=json_encode($payload,JSON_THROW_ON_ERROR);if(in_array($row['state'],['pending','running'],true))$row['state']='migration_hold';$row['lock_token']=null;$row['locked_until']=0;
        }
        return $row;
    }
    private function prepare(): void {
        $this->summary=['source_digest'=>$this->digest,'counts'=>array_map('count',$this->rows),'money_totals'=>[],'encrypted_values_verified'=>0,'documents_verified'=>0,'conflicts'=>[],'id_mapping_counts'=>[],'renamed_admin_usernames'=>0];
        foreach(['users','customers']as$table){
            $next=(int)$this->target->query('SELECT COALESCE(MAX(id),0) FROM '.$table)->fetchColumn();
            foreach($this->rows[$table]??[]as$row){
                $q=$this->target->prepare('SELECT id,password_hash FROM '.$table.' WHERE LOWER(email)=?');$q->execute([mb_strtolower($row['email'])]);$existing=$q->fetch(PDO::FETCH_ASSOC);$q->closeCursor();
                if($existing&&!hash_equals($existing['password_hash'],$row['password_hash']))$this->summary['conflicts'][]=$table.':credential-conflict:'.$row['id'];
                $this->maps[$table][(string)$row['id']]=$existing?(int)$existing['id']:++$next;
                if($table==='users'&&!$existing){
                    $u=$this->target->prepare('SELECT id FROM users WHERE username=?');$u->execute([$row['username']]);$taken=$u->fetchColumn();$u->closeCursor();
                    if($taken){$name='shop-'.$row['id'].'-'.substr($this->digest,0,8);$u->execute([$name]);if($u->fetchColumn())$this->summary['conflicts'][]='users:import-name-conflict:'.$row['id'];$u->closeCursor();$this->usernames[(string)$row['id']]=$name;$this->summary['renamed_admin_usernames']++;}
                }
            }
        }
        foreach(['support_tickets','ticket_replies']as$table){$next=(int)$this->target->query('SELECT COALESCE(MAX(id),0) FROM '.$table)->fetchColumn();foreach($this->rows[$table]??[]as$row)$this->maps[$table][(string)$row['id']]=++$next;}
        foreach($this->rows as$table=>$rows){
            if(str_starts_with($table,'shop_') && !in_array($table,['shop_products','shop_meta','shop_domain_tlds','shop_faq','shop_product_options'],true) && (int)$this->target->query('SELECT COUNT(*) FROM '.$this->id($table))->fetchColumn()>0)$this->summary['conflicts'][]=$table.':target-not-empty';
            foreach($rows as$row){
                if(array_diff_key($row,\SchemaManager::columns($this->target,$table)))throw new RuntimeException('Unmapped source columns: '.$table);
                foreach(['amount_cents','net_cents','gross_cents']as$column)if(isset($row[$column]))$this->summary['money_totals'][$table.'.'.$column]=($this->summary['money_totals'][$table.'.'.$column]??0)+(int)$row[$column];
                $this->transform($table,$row);
                if($table==='shop_invoices'&&!empty($row['pdf_file'])){
                    $path=$this->documentPath($row['pdf_file'],'invoices');
                    if(!$path||!hash_equals($row['pdf_hash']??'',hash_file('sha256',$path)))throw new RuntimeException('Invoice PDF missing or checksum mismatch');$this->summary['documents_verified']++;
                }
            }
        }
        foreach($this->maps as$table=>$map)$this->summary['id_mapping_counts'][$table]=count($map);
    }
    private function documentPath(string $name,string $directory): ?string {
        if(!$this->documents||basename($name)!==$name||!preg_match('/^[a-zA-Z0-9_.-]+\.pdf$/D',$name))return null;
        $path=$this->documents.'/'.$directory.'/'.$name;return is_file($path)&&!is_link($path)?$path:null;
    }
    public function dryRun(): array {$this->prepare();return $this->summary+['mode'=>'dry-run','import_allowed'=>!$this->summary['conflicts']];}
    public function import(): array {
        $q=$this->target->prepare("SELECT summary FROM smf_migration_runs WHERE source_digest=? AND state='complete'");$q->execute([$this->digest]);$done=$q->fetchColumn();$q->closeCursor();if($done)return json_decode($done,true)+['replayed'=>true];
        $this->prepare();if($this->summary['conflicts'])throw new RuntimeException('Migration conflicts: review dry run');
        $this->summary['encrypted_values_verified']=0;
        $run=bin2hex(random_bytes(16));$driver=$this->target->getAttribute(PDO::ATTR_DRIVER_NAME);
        if(!in_array($driver,['sqlite','mysql'],true))throw new RuntimeException('Unsupported shop target');
        $lock=fopen(\HttpSecurity::privateDirectory('locks').'/shop-migration.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Migration already active');
        $files=[];
        try{
            if($driver==='sqlite')$this->target->exec('PRAGMA foreign_keys=OFF');else $this->target->exec('SET FOREIGN_KEY_CHECKS=0');
            $this->target->beginTransaction();
            $this->target->prepare('INSERT INTO smf_migration_runs (id,source_digest,state,summary,created_at) VALUES (?,?,?,?,?)')->execute([$run,$this->digest,'running','{}',time()]);
            foreach(array_reverse(array_keys($this->rows))as$table)if(str_starts_with($table,'shop_'))$this->target->exec('DELETE FROM '.$this->id($table));
            foreach($this->rows as$table=>$rows){
                $columns=\SchemaManager::columns($this->target,$table);
                foreach($rows as$index=>$old){
                    $row=$this->transform($table,$old);$sourceId=(string)($old['id']??$old['service_id']??$old['product_id']??$old['event_key']??$old['provider']??$old['meta_key']??$old['series']??$index);
                    $existing=false;
                    if(isset($this->maps[$table],$old['id'])){
                        $row['id']=$this->maps[$table][(string)$old['id']];
                        if(in_array($table,['users','customers'],true)){$q=$this->target->prepare('SELECT id FROM '.$table.' WHERE id=?');$q->execute([$row['id']]);$existing=(bool)$q->fetchColumn();$q->closeCursor();}
                    }
                    $row=array_intersect_key($row,$columns);
                    if(!$existing){$sql='INSERT INTO '.$this->id($table).' ('.implode(',',array_map(fn($c)=>$this->id($c),array_keys($row))).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')';$this->target->prepare($sql)->execute(array_values($row));}
                    $this->target->prepare('INSERT INTO smf_migration_ids (source_digest,entity,source_id,target_id,row_hash) VALUES (?,?,?,?,?)')->execute([$this->digest,$table,$sourceId,(string)($row['id']??$sourceId),hash('sha256',json_encode($old,JSON_THROW_ON_ERROR))]);
                    if($table==='shop_invoices'&&!empty($row['pdf_file'])){
                        $dir=\shop_private().'/invoices';if(!is_dir($dir))mkdir($dir,0700,true);$to=$dir.'/'.$row['pdf_file'];
                        if(file_exists($to)&&!hash_equals(hash_file('sha256',$to),$row['pdf_hash']))throw new RuntimeException('Private invoice file collision');
                        if(!file_exists($to)){if(!copy($this->documentPath($row['pdf_file'],'invoices'),$to))throw new RuntimeException('Invoice copy failed');chmod($to,0600);$files[]=$to;}
                    }
                }
            }
            // Validate foreign references even on MySQL, which does not revalidate after FK checks are restored.
            foreach($this->rows as$table=>$rows)foreach($this->source->query('PRAGMA foreign_key_list("'.$table.'")')->fetchAll(PDO::FETCH_ASSOC)as$fk){
                $sql='SELECT COUNT(*) FROM '.$this->id($table).' a LEFT JOIN '.$this->id($fk['table']).' b ON a.'.$this->id($fk['from']).'=b.'.$this->id($fk['to']).' WHERE a.'.$this->id($fk['from']).' IS NOT NULL AND b.'.$this->id($fk['to']).' IS NULL';
                if((int)$this->target->query($sql)->fetchColumn())throw new RuntimeException('Migrated foreign reference missing');
            }
            foreach($this->rows as$table=>$rows)if(str_starts_with($table,'shop_')&&(int)$this->target->query('SELECT COUNT(*) FROM '.$this->id($table))->fetchColumn()!==count($rows))throw new RuntimeException('Migration count mismatch');
            foreach($this->summary['money_totals']as$key=>$expected){[$table,$column]=explode('.',$key);if((int)$this->target->query('SELECT COALESCE(SUM('.$this->id($column).'),0) FROM '.$this->id($table))->fetchColumn()!==$expected)throw new RuntimeException('Migration money mismatch');}
            $summary=$this->summary+['mode'=>'import','run_id'=>$run,'ids_verified'=>true,'foreign_keys_verified'=>true];
            $this->target->prepare("UPDATE smf_migration_runs SET state='complete',summary=?,completed_at=? WHERE id=?")->execute([json_encode($summary,JSON_THROW_ON_ERROR),time(),$run]);
            $this->target->commit();return $summary;
        }catch(\Throwable $error){if($this->target->inTransaction())$this->target->rollBack();foreach($files as$file)unlink($file);throw $error;}
        finally {if($driver==='sqlite')$this->target->exec('PRAGMA foreign_keys=ON');else $this->target->exec('SET FOREIGN_KEY_CHECKS=1');flock($lock,LOCK_UN);fclose($lock);}
    }
}
