<?php
namespace SMF\Core;
use PDO;
use RuntimeException;
/** Shop commerce links use the core grant as their common authorization record. */
final class ShopResourceBindings {
    public static function grantId(int $service): string {return substr(hash('sha256','smf:shop-service:'.$service),0,32);}
    public static function grant(int $service,int $customer,string $provider,array $metadata,bool $write=true): void {
        if($provider==='manual')return;
        [$type,$external]=match($provider){'proxmox'=>[($metadata['type']??'qemu')==='lxc'?'lxc':'qemu',(string)($metadata['vmid']??'')],'ispconfig'=>['website',(string)($metadata['website_id']??'')],'ovh'=>['zone',(string)($metadata['zone']??'')],default=>throw new RuntimeException('Unknown shop provider')};
        $core=['shop_service_id'=>$service];
        if($provider==='proxmox'){
            $core+=['node'=>$metadata['node']??'','instance_id'=>$metadata['core_instance_id']??''];
            if($type==='lxc')$core['ownership_marker']=$metadata['ownership_marker']??'';
            if(!preg_match($type==='qemu'?'/^[a-f0-9-]{36}$/Di':'/^[a-f0-9]{32}$/D',$core['instance_id']))throw new RuntimeException('Physical VM identity required before binding');
        }elseif($provider==='ispconfig')$core['group_id']=(int)($metadata['group_id']??0);
        $db=\HttpSecurity::connection();$id=self::grantId($service);$connection=$metadata['connection_id']??'';
        if(!hash_equals(\ResourceAccess::connectionId($provider),$connection))throw new RuntimeException('Provider account changed before binding');
        $q=$db->prepare('SELECT * FROM security_resource_assignments WHERE id=?');$q->execute([$id]);$old=$q->fetch(PDO::FETCH_ASSOC);$q->closeCursor();
        $values=['customer',$customer,$provider,$connection,$type,$external,json_encode($core,JSON_THROW_ON_ERROR),$write?1:0];
        if($old){if($old['subject_realm']!=='customer'||(int)$old['subject_id']!==$customer||$old['provider']!==$provider||$old['external_id']!==$external||!hash_equals($old['connection_id'],$connection))throw new RuntimeException('Existing core grant must be explicitly unlinked');}
        else $db->prepare('INSERT INTO security_resource_assignments (subject_realm,subject_id,provider,connection_id,resource_type,external_id,metadata,can_write,id) VALUES (?,?,?,?,?,?,?,?,?)')->execute([...$values,$id]);
    }
    public static function authorize(array $service,?int $customer,bool $write): void {
        if(PHP_SAPI!=='cli'){
            $admin=\Identity::current('backend');$admin=$admin&&$admin['role']==='admin';
            if(!$admin){$actor=\Identity::requireIdentity('customer');if($customer===null||$actor['id']!==$customer)throw new \SecurityViolation('Ressource nicht gefunden.',404);}
        }
        if($service['provider']==='manual')return;
        $q=\HttpSecurity::connection()->prepare('SELECT * FROM security_resource_assignments WHERE id=?');$q->execute([self::grantId((int)$service['id'])]);$grant=$q->fetch(PDO::FETCH_ASSOC);$q->closeCursor();
        if(!$grant || !self::canonicalValid($grant,$write) || ($customer!==null && (int)$grant['subject_id']!==$customer))throw new \SecurityViolation('Ressourcenzuordnung muss geprüft werden.',403);
    }
    public static function canonicalValid(array $grant,bool $write=false): bool {
        if($grant['expires_at']!==null&&(int)$grant['expires_at']<=time())return false;
        $metadata=json_decode($grant['metadata'],true,64,JSON_THROW_ON_ERROR);if(!isset($metadata['shop_service_id']))return true;
        $q=\HttpSecurity::connection()->prepare('SELECT s.customer_id,s.status,l.provider,l.metadata FROM shop_services s JOIN shop_resource_links l ON l.service_id=s.id WHERE s.id=?');$q->execute([(int)$metadata['shop_service_id']]);$service=$q->fetch(PDO::FETCH_ASSOC);$q->closeCursor();
        if(!$service||$grant['subject_realm']!=='customer'||(int)$grant['subject_id']!==(int)$service['customer_id']||$grant['provider']!==$service['provider']||($write&&($service['status']!=='active'||!$grant['can_write'])))return false;
        $meta=json_decode($service['metadata'],true,64,JSON_THROW_ON_ERROR);
        if(!hash_equals($grant['connection_id'],$meta['connection_id']??''))return false;
        if($grant['provider']==='proxmox')return ($grant['resource_type']===($meta['type']??'qemu'))&&$grant['external_id']===(string)($meta['vmid']??'')&&($metadata['node']??'')===($meta['node']??'')&&hash_equals($metadata['instance_id']??'',(string)($meta['core_instance_id']??''));
        if($grant['provider']==='ispconfig')return $grant['external_id']===(string)($meta['website_id']??'')&&(int)($metadata['group_id']??0)===(int)($meta['group_id']??0);
        return $grant['external_id']===(string)($meta['zone']??'');
    }
    public static function revoke(int $service): void {
        \HttpSecurity::connection()->prepare('DELETE FROM security_resource_assignments WHERE id=?')->execute([self::grantId($service)]);
    }
    public static function physical(array $metadata,array $config,string $type): array {
        if($type==='lxc'){
            $marker=$metadata['ownership_marker']??'';
            if($marker===''||!hash_equals($marker,rtrim((string)($config['description']??''),"\r\n")))throw new RuntimeException('Container ownership marker changed');
            $metadata['core_instance_id']=substr(hash('sha256',$marker),0,32);
        }else{
            $instance=$config['vmgenid']??'';if(!is_string($instance)||!preg_match('/^[a-f0-9-]{36}$/Di',$instance))throw new RuntimeException('VM generation ID required');$metadata['core_instance_id']=$instance;
        }
        return $metadata;
    }
}
