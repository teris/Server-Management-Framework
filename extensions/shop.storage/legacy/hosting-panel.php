<?php
/** Customer control-panel credentials are separate from the privileged remote-API login. */
function hosting_panel_client(array $service,?IspconfigProvider $api=null): array {
    if($service['provider']!=='ispconfig')throw new RuntimeException('Dieses Produkt besitzt keinen ISPConfig-Zugang.');
    $api??=new IspconfigProvider();$site=hosting_site($service,$api);$id=(int)$service['meta']['client_id'];
    $account=one("SELECT external_id FROM shop_provider_accounts WHERE customer_id=? AND provider='ispconfig'",[$service['customer_id']]);
    if(!$account || (int)$account['external_id']!==$id || $api->clientIdForGroup((int)$site['sys_groupid'])!==$id)throw new RuntimeException('Der ISPConfig-Kundenzugang ist nicht eindeutig deinem Konto zugeordnet.');
    $client=$api->client($id);
    if((int)($client['client_id']??0)!==$id || (int)($client['limit_client']??-1)!==0 || empty($client['username']))throw new RuntimeException('Der ISPConfig-Kundenzugang muss durch den Support geprüft werden.');
    return $client;
}
function save_hosting_panel_login(int $customer,int $client,array $credentials): void {
    $value=['client_id'=>$client,'connection_id'=>integration_identity('ispconfig'),'username'=>$credentials['username'],'password'=>$credentials['password']];
    $secret=secret_seal($value,'provider-account:ispconfig:'.$customer);
    if(one("SELECT customer_id FROM shop_provider_account_logins WHERE customer_id=? AND provider='ispconfig'",[$customer]))query("UPDATE shop_provider_account_logins SET secret=?,updated_at=CURRENT_TIMESTAMP WHERE customer_id=? AND provider='ispconfig'",[$secret,$customer]);
    else query("INSERT INTO shop_provider_account_logins (customer_id,provider,secret) VALUES (?,'ispconfig',?)",[$customer,$secret]);
}
function reveal_hosting_panel_login(array $service): array {
    $client=hosting_panel_client($service);$row=one("SELECT secret FROM shop_provider_account_logins WHERE customer_id=? AND provider='ispconfig'",[$service['customer_id']]);
    if(!$row)throw new RuntimeException('Das bisherige ISPConfig-Passwort ist nicht auslesbar. Lege zunächst ein neues Passwort fest.');
    $value=secret_open($row['secret'],'provider-account:ispconfig:'.$service['customer_id']);
    if(!hash_equals($value['connection_id']??'',integration_identity('ispconfig')) || (int)($value['client_id']??0)!==(int)$client['client_id'] || ($value['username']??'')!==$client['username'])throw new RuntimeException('Die gespeicherten Zugangsdaten passen nicht mehr zum ISPConfig-Konto. Bitte ein neues Passwort festlegen.');
    audit_event('hosting.panel.credentials.viewed',(string)$service['id']);return ['username'=>$value['username'],'password'=>$value['password']];
}
function hosting_profile_client_limits(array $profile): array {
    return ['limit_client'=>0,'limit_shell_user'=>0,'ssh_chroot'=>'jailkit','limit_web_domain'=>1,'limit_web_quota'=>max(0,(int)$profile['storage_mb']),'limit_ftp_user'=>max(0,(int)$profile['ftp_limit']),'limit_database'=>max(0,(int)$profile['database_limit']),'limit_database_user'=>max(0,(int)$profile['database_limit']),'limit_maildomain'=>1,'limit_mailbox'=>max(0,(int)$profile['mail_limit']),'limit_mailquota'=>max(0,(int)$profile['mail_quota_mb']*(int)$profile['mail_limit']),'limit_cron'=>max(0,(int)($profile['cron_limit']??0)),'limit_cron_type'=>'url','limit_cron_frequency'=>5,'limit_traffic_quota'=>-1,'limit_dns_zone'=>0,'limit_dns_slave_zone'=>0,'limit_dns_record'=>0,'limit_web_subdomain'=>0,'limit_web_aliasdomain'=>0,'limit_webdav_user'=>0,'limit_mailalias'=>0,'limit_mailaliasdomain'=>0,'limit_mailmailinglist'=>0,'limit_mailforward'=>0,'limit_mailcatchall'=>0,'limit_mailrouting'=>0,'limit_mailfetchmail'=>0,'limit_xmpp_domain'=>0,'limit_xmpp_user'=>0,'limit_database_postgresql'=>0]+hosting_client_feature_limits($profile);
}
function hosting_account_limits(int $customer,int $client,IspconfigProvider $api,?array $extra=null,array $overrides=[]): array {
    $sum=hosting_profile_client_limits(['storage_mb'=>0,'ftp_limit'=>0,'database_limit'=>0,'mail_limit'=>0,'mail_quota_mb'=>0,'php_enabled'=>0,'php_mode'=>'no','suexec_enabled'=>0,'errordocs_enabled'=>0]);$sum['limit_web_domain']=0;$sum['limit_maildomain']=0;
    $ids=[];$groups=[];
    foreach(all("SELECT s.id,r.metadata FROM shop_services s JOIN shop_resource_links r ON r.service_id=s.id WHERE s.customer_id=? AND r.provider='ispconfig'",[$customer]) as $row){
        $s=bound_service((int)$row['id'],$customer);if((int)$s['meta']['client_id']!==$client)throw new RuntimeException('Für diesen Kunden bestehen unterschiedliche ISPConfig-Konten. Bitte den Support kontaktieren.');
        $site=hosting_site($s,$api);$ids[]=(int)$site['domain_id'];$groups[]=(int)$site['sys_groupid'];$m=$s['meta'];
        $order=!empty($m['order_id'])?one('SELECT profile FROM shop_orders WHERE id=? AND customer_id=?',[$m['order_id'],$customer]):null;$profile=$order?json_decode($order['profile'],true):[];
        $part=hosting_profile_client_limits(array_replace($profile,$m['features']??[],['storage_mb'=>$m['storage_mb']??$profile['storage_mb']??$site['hd_quota'],'ftp_limit'=>$m['limits']['ftp'],'database_limit'=>$m['limits']['database'],'mail_limit'=>$m['limits']['mail'],'mail_quota_mb'=>$m['limits']['mail_quota_mb'],'cron_limit'=>$m['limits']['cron']??0]));
        if(isset($overrides[(int)$s['id']]))$part=hosting_profile_client_limits($overrides[(int)$s['id']]);
        foreach(['limit_web_domain','limit_web_quota','limit_ftp_user','limit_database','limit_database_user','limit_maildomain','limit_mailbox','limit_mailquota','limit_cron'] as $key)$sum[$key]+=$part[$key];foreach(['limit_cgi','limit_ssi','limit_perl','limit_ruby','limit_python','limit_ssl','force_suexec','limit_hterror','limit_ssl_letsencrypt','limit_wildcard'] as $key)if($part[$key]==='y')$sum[$key]='y';$sum['web_php_options']=implode(',',array_unique(array_merge(explode(',',$sum['web_php_options']),explode(',',$part['web_php_options']))));
    }
    foreach(array_unique($groups) as $group){$rows=$api->call('sites_web_domain_get',['primary_id'=>['sys_groupid'=>$group,'type'=>'vhost']]);if(isset($rows['domain_id']))$rows=[$rows];foreach($rows?:[] as $site)if(!in_array((int)$site['domain_id'],$ids,true))throw new RuntimeException('Das ISPConfig-Konto enthält weitere Websites. Die Paketlimits müssen zuerst im Adminbereich geprüft werden.');}
    if($extra){$part=hosting_profile_client_limits($extra);foreach(['limit_web_domain','limit_web_quota','limit_ftp_user','limit_database','limit_database_user','limit_maildomain','limit_mailbox','limit_mailquota','limit_cron'] as $key)$sum[$key]+=$part[$key];foreach(['limit_cgi','limit_ssi','limit_perl','limit_ruby','limit_python','limit_ssl','force_suexec','limit_hterror','limit_ssl_letsencrypt','limit_wildcard'] as $key)if($part[$key]==='y')$sum[$key]='y';$sum['web_php_options']=implode(',',array_unique(array_merge(explode(',',$sum['web_php_options']),explode(',',$part['web_php_options']))));}
    return $sum;
}
function set_hosting_panel_password(array $service,string $password): void {
    validate_service_password($password);
    resource_mutex(-(int)$service['customer_id'],function()use($service,$password){
        $api=new IspconfigProvider();$client=hosting_panel_client($service,$api);$params=['password'=>$password];
        // Shop-created clients must use the purchased totals when customers can log in directly.
        if(($client['customer_no']??'')==='OC-'.$service['customer_id'])$params+=hosting_account_limits((int)$service['customer_id'],(int)$client['client_id'],$api);
        $updated=$api->call('client_update',['client_id'=>(int)$client['client_id'],'reseller_id'=>(int)($client['parent_client_id']??0),'params'=>$params]);
        if(!$updated)throw new RuntimeException('ISPConfig hat die Passwortänderung nicht bestätigt.');
        save_hosting_panel_login((int)$service['customer_id'],(int)$client['client_id'],['username'=>$client['username'],'password'=>$password]);audit_event('hosting.panel.password.changed',(string)$service['id']);
    });
}
function hosting_mail_settings(array $service,int $childId,array $input): void {
    resource_mutex((int)$service['id'],function()use($service,$childId,$input){
        $service=module_future_resource_limits(bound_service((int)$service['id'],(int)$service['customer_id'],true));
        $child=one("SELECT * FROM shop_hosting_children WHERE id=? AND service_id=? AND kind='mail'",[$childId,$service['id']]);
        if(!$child || !ctype_digit($child['external_id']))throw new RuntimeException('Dieses Postfach ist für dein Produkt nicht verfügbar.');
        $name=trim((string)($input['display_name']??''));$quota=(string)($input['quota_mb']??'');
        if(mb_strlen($name)>100 || preg_match('/[\x00-\x1f]/',$name) || !preg_match('/^[0-9]{1,9}$/D',$quota) || (int)$quota<1 || (int)$quota>(int)$service['meta']['limits']['mail_quota_mb'])throw new RuntimeException('Bitte einen gültigen Anzeigenamen und eine Postfachgröße innerhalb deines Paketlimits eintragen.');
        $api=new IspconfigProvider();$site=hosting_site($service,$api);$remote=$api->call('mail_user_get',['primary_id'=>(int)$child['external_id']]);
        if((int)($remote['sys_groupid']??0)!==(int)$site['sys_groupid'] || ($remote['email']??'')!==$child['name'] || !str_ends_with($child['name'],'@'.$service['meta']['domain']))throw new RuntimeException('Dieses Postfach gehört nicht zu deiner Website.');
        $remote['name']=$name;$remote['quota']=(int)$quota*1048576;$remote['password']='';
        $updated=$api->call('mail_user_update',['client_id'=>(int)$service['meta']['client_id'],'primary_id'=>(int)$child['external_id'],'params'=>$remote]);
        if(!$updated)throw new RuntimeException('ISPConfig hat die Postfachänderung nicht bestätigt.');
        $meta=json_decode($child['metadata'],true);$meta['display_name']=$name;$meta['quota_mb']=(int)$quota;query('UPDATE shop_hosting_children SET metadata=? WHERE id=?',[json_encode($meta),$childId]);audit_event('hosting.mail.settings.changed',(string)$service['id']);
    });
}

/** Read shared profiles, but only expose policies the mailbox owner may read in ISPConfig. */
function hosting_spam_policies(IspconfigProvider $api,int $group): array {
    $rows=$api->call('mail_policy_get',['primary_id'=>-1]);
    if(isset($rows['id']))$rows=[$rows];$policies=[0=>'Domäneneinstellung übernehmen'];
    foreach($rows?:[] as $row){
        $id=(int)($row['id']??0);$name=trim((string)($row['policy_name']??''));
        $readable=str_contains($row['sys_perm_other']??'','r') || ((int)($row['sys_groupid']??0)===$group && str_contains($row['sys_perm_group']??'','r'));
        if($id>0 && $name!=='' && $readable)$policies[$id]=mb_substr($name,0,120);
    }
    return $policies;
}
function hosting_mail_spam_state(array $service,array $child,IspconfigProvider $api): array {
    if($service['provider']!=='ispconfig' || (int)($service['meta']['limits']['mail']??0)<1 || (int)$child['service_id']!==(int)$service['id'] || $child['kind']!=='mail' || !ctype_digit((string)$child['external_id']) || (json_decode($child['metadata'],true)['state']??'')!=='active')throw new RuntimeException('Für dieses Produkt ist kein verwaltbares Postfach gebucht.');
    $site=hosting_site($service,$api);$group=(int)$site['sys_groupid'];$domain=$api->mailDomain($service['meta']['domain']);
    $mail=$api->call('mail_user_get',['primary_id'=>(int)$child['external_id']]);
    if(!$domain || (int)($domain['sys_groupid']??0)!==$group || $api->clientIdForGroup($group)!==(int)$service['meta']['client_id'] || (int)($mail['mailuser_id']??0)!==(int)$child['external_id'] || (int)($mail['sys_groupid']??0)!==$group || (int)($mail['server_id']??0)!==(int)$domain['server_id'] || ($mail['email']??'')!==$child['name'] || !str_ends_with($child['name'],'@'.$service['meta']['domain']))throw new RuntimeException('Die Zuordnung des Postfachs oder seiner Maildomain muss geprüft werden.');
    $rows=$api->call('mail_spamfilter_user_get',['primary_id'=>['email'=>$child['name']]]);if(isset($rows['id']))$rows=[$rows];$rows=$rows?:[];
    if(count($rows)>1)throw new RuntimeException('Für dieses Postfach bestehen mehrere Spamfilter-Zuordnungen. Bitte den Support kontaktieren.');
    $rule=$rows[0]??null;
    if($rule && ((int)($rule['id']??0)<1 || ($rule['email']??'')!==$child['name'] || (int)($rule['sys_groupid']??0)!==$group || (int)($rule['server_id']??0)!==(int)$domain['server_id']))throw new RuntimeException('Die Spamfilter-Zuordnung gehört nicht eindeutig zu deinem Postfach.');
    // Return only public mailbox settings, never the native password hash or other account fields.
    return ['policy_id'=>(int)($rule['policy_id']??0),'move_junk'=>$mail['move_junk']??'y','rule'=>$rule,'server_id'=>(int)$domain['server_id'],'group_id'=>$group];
}
function hosting_mail_spam_settings(array $service,int $childId,array $input): void {
    $policy=(string)($input['spam_policy_id']??'');$junk=(string)($input['move_junk']??'');
    if(!preg_match('/^[0-9]{1,9}$/D',$policy) || !in_array($junk,['y','a','n'],true))throw new RuntimeException('Bitte ein gültiges Spamfilter-Profil und eine Junk-Ordner-Einstellung auswählen.');
    resource_mutex((int)$service['id'],function()use($service,$childId,$policy,$junk){
        $service=bound_service((int)$service['id'],(int)$service['customer_id'],true);
        $child=one("SELECT * FROM shop_hosting_children WHERE id=? AND service_id=? AND kind='mail'",[$childId,$service['id']]);if(!$child)throw new RuntimeException('Dieses Postfach ist für dein Produkt nicht verfügbar.');
        $api=new IspconfigProvider();$state=hosting_mail_spam_state($service,$child,$api);$policies=hosting_spam_policies($api,$state['group_id']);
        if(!array_key_exists((int)$policy,$policies))throw new RuntimeException('Dieses Spamfilter-Profil ist für dein Postfach nicht freigegeben.');
        $client=(int)$service['meta']['client_id'];$rule=$state['rule'];
        if(!$rule || (int)$rule['policy_id']!==(int)$policy){
            $params=['policy_id'=>(int)$policy];
            if(!$rule)$params+=['server_id'=>$state['server_id'],'priority'=>7,'email'=>$child['name'],'fullname'=>$child['name'],'local'=>'Y'];
            $args=['client_id'=>$client,'params'=>$params];if($rule)$args['primary_id']=(int)$rule['id'];
            if(!$api->call($rule?'mail_spamfilter_user_update':'mail_spamfilter_user_add',$args))throw new RuntimeException('ISPConfig hat die Spamfilter-Zuordnung nicht bestätigt. Bitte vor einem erneuten Versuch die aktuellen Einstellungen laden.');
        }
        if($state['move_junk']!==$junk){
            // The native API requires email even for a partial update; an empty password preserves it.
            if(!$api->call('mail_user_update',['client_id'=>$client,'primary_id'=>(int)$child['external_id'],'params'=>['email'=>$child['name'],'password'=>'','move_junk'=>$junk]]))throw new RuntimeException('Das Spamfilter-Profil wurde gespeichert, die Junk-Ordner-Einstellung aber noch nicht bestätigt. Bitte die aktuellen Einstellungen erneut laden.');
        }
        audit_event('hosting.mail.spam.changed',(string)$service['id']);
    });
}
function hosting_mail_spam_panel(array $service,array $child,?IspconfigProvider $api,array $policies,string $error=''): void {
    if((int)($service['meta']['limits']['mail']??0)<1)return;
    $state=null;if(!$error)try{$state=hosting_mail_spam_state($service,$child,$api);}catch(RuntimeException $e){$error=$e->getMessage();}
    ?>
    <details class="credential-change"><summary>Spamfilter einstellen</summary>
    <?php if($error): ?><p class="info-box">Die Spamfilter-Einstellungen konnten nicht geladen werden: <?= e($error) ?></p>
    <?php else: ?><form method="post"><?= csrf() ?><input type="hidden" name="action" value="hosting_mail_spam_settings"><input type="hidden" name="service_id" value="<?= (int)$service['id'] ?>"><input type="hidden" name="child_id" value="<?= (int)$child['id'] ?>">
    <label class="field"><span>Spamfilter-Profil aus ISPConfig</span><select name="spam_policy_id" required><?php if(!array_key_exists($state['policy_id'],$policies)): ?><option value="" selected disabled>Aktuelles Profil nicht freigegeben – bitte neu wählen</option><?php endif ?><?php foreach($policies as $id=>$name): ?><option value="<?= (int)$id ?>" <?= $state['policy_id']===(int)$id?'selected':'' ?>><?= e($name) ?></option><?php endforeach ?></select><small>„Normal“ ist das Standardprofil, „Trigger happy“ filtert strenger, „Permissive“ großzügiger. Die Profile gelten mit den vom Administrator in ISPConfig hinterlegten Regeln.</small></label>
    <label class="field"><span>Erkannten Spam in den Junk-Ordner verschieben</span><select name="move_junk"><?php foreach(['y'=>'Vor eigenen E-Mail-Filtern','a'=>'Nach eigenen E-Mail-Filtern','n'=>'Nicht automatisch verschieben'] as $value=>$name): ?><option value="<?= $value ?>" <?= $state['move_junk']===$value?'selected':'' ?>><?= e($name) ?></option><?php endforeach ?></select></label>
    <button class="button button-outline button-small">Spamfilter speichern</button><p class="form-footnote">Die Einstellung gilt für <?= e($child['name']) ?>. Bei „Domäneneinstellung übernehmen“ verwendet ISPConfig das übergeordnete Profil.</p></form><?php endif ?></details>
    <?php
}
