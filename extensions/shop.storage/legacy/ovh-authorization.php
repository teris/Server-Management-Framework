<?php
/** Least-privilege rules for the DNS and domain-order calls implemented by this shop. */
function ovh_shop_access_rules(): array {
    return array_map(fn($rule)=>['method'=>$rule[0],'path'=>$rule[1]],[
        ['GET','/ip'],['GET','/dedicated/server'],['GET','/dedicated/server/*/virtualMac'],['GET','/dedicated/server/*/virtualMac/*'],
        ['GET','/domain/zone'],['GET','/domain/zone/*'],
        ['GET','/domain/*/serviceInfos'],['PUT','/domain/*/serviceInfos'],
        ['POST','/domain/zone/*/record'],['PUT','/domain/zone/*/record/*'],
        ['DELETE','/domain/zone/*/record/*'],['POST','/domain/zone/*/refresh'],
        ['POST','/order/cart'],['GET','/order/cart/*'],
        ['POST','/order/cart/*/assign'],['POST','/order/cart/*/domain'],
        ['POST','/order/cart/*/item/*/configuration'],['POST','/order/cart/*/checkout'],
        ['POST','/me/contact'],
    ]);
}
function ovh_pending_authorization(): ?array {
    $row=one("SELECT meta_value FROM shop_meta WHERE meta_key='ovh_pending_authorization'");
    return $row?secret_open($row['meta_value'],'ovh:pending-authorization'):null;
}
function ovh_prepare_authorization(): array {
    $config=integration_config('ovh',false);$identity=integration_identity('ovh',$config);$pending=ovh_pending_authorization();
    if($pending && hash_equals($pending['rules_hash']??'',hash('sha256',json_encode(ovh_shop_access_rules()))) && hash_equals($pending['connection_id'],$identity) && $pending['created_at']>time()-86400)return $pending;
    $state=bin2hex(random_bytes(24));$redirect=Config::FRONTPANEL_SITE_URL.'/admin/schnittstellen?anbieter=ovh&freigabe='.$state;
    $authorization=(new OvhProvider($config))->requestAuthorization(ovh_shop_access_rules(),$redirect);
    $pending=['consumer_key'=>$authorization['consumerKey'],'validation_url'=>$authorization['validationUrl'],'state'=>$state,'connection_id'=>$identity,'created_at'=>time(),'rules_hash'=>hash('sha256',json_encode(ovh_shop_access_rules()))];
    $sealed=secret_seal($pending,'ovh:pending-authorization');
    if(one("SELECT meta_key FROM shop_meta WHERE meta_key='ovh_pending_authorization'"))query("UPDATE shop_meta SET meta_value=? WHERE meta_key='ovh_pending_authorization'",[$sealed]);
    else query("INSERT INTO shop_meta (meta_key,meta_value) VALUES ('ovh_pending_authorization',?)",[$sealed]);
    audit_event('ovh.authorization.request','ovh');return $pending;
}
function ovh_confirm_authorization(string $state): array {
    $pending=ovh_pending_authorization();$row=integration_row('ovh');
    if(!$pending || !$row || !hash_equals($pending['state'],$state))throw new RuntimeException('Diese OVH-Freigabe ist nicht mehr aktuell. Bitte die Schnittstellen-Einstellungen neu öffnen.');
    $config=integration_config('ovh',false);
    if(!hash_equals($pending['connection_id'],integration_identity('ovh',$config)))throw new RuntimeException('Die OVH-Zugangsdaten wurden inzwischen geändert. Bitte eine neue Freigabe vorbereiten.');
    $config['consumer_key']=$pending['consumer_key'];$client=new OvhProvider($config);
    $credential=$client->call('GET','/auth/currentCredential');
    if(($credential['status']??'')!=='validated')throw new RuntimeException('Die Freigabe wurde bei OVH noch nicht bestätigt. Bitte zuerst „Bei OVH freigeben“ öffnen.');
    $granted=(array)($credential['rules']??[]);
    foreach(ovh_shop_access_rules() as $required){
        $found=false;
        foreach($granted as $rule)if(($rule['method']??'')===$required['method'] && preg_match('#^'.str_replace('\\*','.*',preg_quote($rule['path']??'','#')).'$#D',$required['path'])){$found=true;break;}
        if(!$found)throw new RuntimeException('OVH hat diese benötigte Freigabe nicht erteilt: '.$required['method'].' '.$required['path']);
    }
    $result=$client->test();$rebindings=ovh_checked_rebindings($pending['connection_id'],$client);
    $pdo=db();$pdo->beginTransaction();
    try{
        $updated=query("UPDATE shop_integrations SET secret_config=?,tested_at=CURRENT_TIMESTAMP,test_result='ok',updated_at=CURRENT_TIMESTAMP WHERE provider='ovh' AND secret_config=?",[secret_seal($config,'provider:ovh'),$row['secret_config']]);
        if($updated->rowCount()!==1)throw new RuntimeException('Die OVH-Einstellungen wurden gleichzeitig geändert. Bitte erneut prüfen.');
        ovh_apply_rebindings($pending['connection_id'],integration_identity('ovh',$config),$rebindings);
        query("DELETE FROM shop_meta WHERE meta_key='ovh_pending_authorization'");
        audit_event('ovh.authorization.confirm','ovh');$pdo->commit();
    }catch(Throwable $exception){$pdo->rollBack();throw $exception;}
    schedule_ovh_inventory();
    return $result;
}

/** Permission renewal keeps existing zone links after rechecking access to each exact zone. */
function ovh_checked_rebindings(string $old,OvhProvider $api): array {
    $ids=[];foreach(all("SELECT service_id,metadata FROM shop_resource_links WHERE provider='ovh'") as $r){$m=json_decode($r['metadata'],true);if(!hash_equals($old,$m['connection_id']??''))continue;$zone=valid_domain($m['zone']??'');if(!$zone)throw new RuntimeException('Eine bestehende Domainzuordnung muss vor der OVH-Freigabe geprüft werden.');$reply=$api->call('GET','/domain/zone/'.rawurlencode($zone));if(!is_array($reply) || ($reply['name']??'')!==$zone)throw new RuntimeException('Der neue OVH-Zugang hat keinen bestätigten Zugriff auf die bisher zugeordnete Domain '.$zone.'.');$ids[]=(int)$r['service_id'];}return $ids;
}
function ovh_apply_rebindings(string $old,string $new,array $ids): void {
    foreach($ids as $id){$r=one("SELECT metadata FROM shop_resource_links WHERE service_id=? AND provider='ovh'",[$id]);$m=$r?json_decode($r['metadata'],true):null;if(!$m || !hash_equals($old,$m['connection_id']??''))throw new RuntimeException('Eine Domainzuordnung wurde gleichzeitig geändert. Bitte erneut prüfen.');$m['connection_id']=$new;query('UPDATE shop_resource_links SET metadata=? WHERE service_id=?',[json_encode($m),$id]);}
    // Only DKIM jobs for the zones rechecked above inherit a renewed credential.
    foreach($ids as $id){$r=one('SELECT metadata FROM shop_resource_links WHERE service_id=?',[$id]);$m=json_decode($r['metadata'],true);query('UPDATE shop_hosting_mail_dns SET ovh_connection_id=? WHERE domain=? AND ovh_connection_id=?',[$new,$m['zone'],$old]);}
    foreach(all("SELECT product_id,profile FROM shop_product_automation WHERE provider='ovh'") as $r){$p=json_decode($r['profile'],true);if(!hash_equals($old,$p['connection_id']??''))continue;$p['connection_id']=$new;query('UPDATE shop_product_automation SET profile=? WHERE product_id=?',[json_encode($p),$r['product_id']]);}
}
