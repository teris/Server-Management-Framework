<?php
function save_child_credentials(int $child,array $credentials): void {
    $secret=secret_seal($credentials,'hosting-child:'.$child);if(one('SELECT child_id FROM shop_child_credentials WHERE child_id=?',[$child]))query('UPDATE shop_child_credentials SET secret=?,updated_at=CURRENT_TIMESTAMP WHERE child_id=?',[$secret,$child]);else query('INSERT INTO shop_child_credentials (child_id,secret) VALUES (?,?)',[$child,$secret]);
}
function reveal_child_credentials(array $service,int $child): array {
    $row=one("SELECT c.*,s.secret FROM shop_hosting_children c JOIN shop_child_credentials s ON s.child_id=c.id WHERE c.id=? AND c.service_id=?",[$child,$service['id']]);if(!$row)throw new RuntimeException('Für diesen Zugang ist kein lesbares Passwort gespeichert. Du kannst ein neues Passwort festlegen.');
    if((json_decode($row['metadata'],true)['state']??'')!=='active')throw new RuntimeException('Der Zugang muss zuerst durch den Support geprüft werden.');audit_event('hosting.credentials.viewed',(string)$child);return secret_open($row['secret'],'hosting-child:'.$child);
}
function hosting_cron_create(array $service,array $input): void {
    if($service['provider']!=='ispconfig' || (int)($service['meta']['limits']['cron']??0)<1)throw new RuntimeException('Dein Paket enthält keine Web-Cronjobs.');
    $url=trim((string)($input['url']??''));$parts=parse_url($url);$domain=$service['meta']['domain'];$interval=(string)($input['interval']??'');
    if(strlen($url)>2000 || ($parts['scheme']??'')!=='https' || !in_array(strtolower($parts['host']??''),[$domain,'www.'.$domain],true) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || isset($parts['port']) || preg_match('/[\x00-\x20]/',$url) || !in_array($interval,['5','10','15','30','60','1440'],true))throw new RuntimeException('Bitte eine HTTPS-Adresse deiner eigenen Website und ein gültiges Intervall auswählen.');
    resource_mutex((int)$service['id'],function()use($service,$url,$interval){
        if((int)query("SELECT COUNT(*) FROM shop_hosting_children WHERE service_id=? AND kind='cron'",[$service['id']])->fetchColumn()>=(int)$service['meta']['limits']['cron'])throw new RuntimeException('Alle Cronjob-Plätze deines Pakets sind belegt.');
        $service=module_future_resource_limits(bound_service((int)$service['id'],(int)$service['customer_id'],true));
        $api=new IspconfigProvider();$site=hosting_site($service,$api);query("INSERT INTO shop_hosting_children (service_id,kind,external_id,name,metadata) VALUES (?,'cron',?,?,?)",[$service['id'],'pending-'.bin2hex(random_bytes(12)),$url,json_encode(['state'=>'creating','interval'=>$interval])]);$child=(int)db()->lastInsertId();
        try{$id=(int)$api->call('sites_cron_add',['client_id'=>(int)$service['meta']['client_id'],'params'=>['server_id'=>(int)$site['server_id'],'parent_domain_id'=>(int)$site['domain_id'],'type'=>'url','command'=>$url,'run_min'=>(int)$interval<60?'*/'.$interval:'0','run_hour'=>$interval==='1440'?'0':'*','run_mday'=>'*','run_month'=>'*','run_wday'=>'*','active'=>'y','log'=>'n']]);if($id<1)throw new ProviderFailure('ispconfig');query("UPDATE shop_hosting_children SET external_id=?,metadata=? WHERE id=?",[(string)$id,json_encode(['state'=>'active','interval'=>$interval]),$child]);audit_event('hosting.cron.created',(string)$service['id']);}
        catch(Throwable $e){query('UPDATE shop_hosting_children SET metadata=? WHERE id=?',[json_encode(['state'=>'needs_action','interval'=>$interval]),$child]);throw $e;}
    });
}
function hosting_cron_delete(array $service,int $child): void {
    resource_mutex((int)$service['id'],function()use($service,$child){$row=one("SELECT * FROM shop_hosting_children WHERE id=? AND service_id=? AND kind='cron'",[$child,$service['id']]);if(!$row || !ctype_digit($row['external_id']))throw new RuntimeException('Dieser Cronjob muss persönlich geprüft werden.');$api=new IspconfigProvider();$site=hosting_site($service,$api);$cron=$api->call('sites_cron_get',['cron_id'=>(int)$row['external_id']]);if(!is_array($cron) || (int)($cron['parent_domain_id']??0)!==(int)$site['domain_id'])throw new RuntimeException('Dieser Cronjob gehört nicht zu deiner Website.');if(!$api->call('sites_cron_delete',['cron_id'=>(int)$row['external_id']]))throw new RuntimeException('ISPConfig hat das Entfernen des Cronjobs nicht bestätigt.');query('DELETE FROM shop_hosting_children WHERE id=?',[$child]);audit_event('hosting.cron.deleted',(string)$service['id']);});
}
function hosting_child_access_panel(array $service,array $child): void {
    $meta=json_decode($child['metadata'],true);$credentials=$GLOBALS['revealed_hosting_children'][(int)$child['id']]??null;$canReveal=(bool)one('SELECT child_id FROM shop_child_credentials WHERE child_id=?',[$child['id']]);$kind=$child['kind'];if($kind==='cron')return;
    $host=$kind==='database'?'localhost':($kind==='mail'?'mail.orga-consult.eu':$service['meta']['domain']);?>
    <div class="child-access"><dl class="detail-list"><div><dt><?= $kind==='database'?'Datenbank / Benutzer':'Benutzername' ?></dt><dd><?= e($kind==='database'?($meta['database_user']??$child['name']):$child['name']) ?></dd></div><div><dt>Server</dt><dd><?= e($host) ?></dd></div><div><dt>Verbindung</dt><dd><?= e(['ftp'=>'FTP mit explizitem TLS · Port 21','mail'=>'IMAP TLS: 993 · SMTP STARTTLS: 587','database'=>'MySQL · Port 3306 · Zugriff von der Website'][$kind]??'') ?></dd></div></dl>
    <?php if($credentials): ?><label class="field"><span>Gespeichertes Passwort</span><input type="password" readonly value="<?= e($credentials['password']) ?>" autocomplete="off" data-stored-password><button type="button" class="text-button" data-reveal-stored-password>Passwort anzeigen / verbergen</button></label><?php elseif($canReveal): ?><form method="post"><?= csrf() ?><input type="hidden" name="action" value="hosting_child_credentials"><input type="hidden" name="service_id" value="<?= (int)$service['id'] ?>"><input type="hidden" name="child_id" value="<?= (int)$child['id'] ?>"><button class="button button-outline button-small">Zugangsdaten anzeigen</button></form><?php else: ?><p class="small muted">Für bestehende Zugänge ist das bisherige Passwort nicht auslesbar. Über „Passwort ändern“ kannst du ein neues festlegen.</p><?php endif ?>
    </div><?php
}
function hosting_cron_panel(array $service): void {
    $limit=(int)($service['meta']['limits']['cron']??0);if(!$limit)return;$rows=all("SELECT * FROM shop_hosting_children WHERE service_id=? AND kind='cron'",[$service['id']]);?>
    <section class="panel form-panel"><h2>Web-Cronjobs · <?= count($rows) ?> / <?= $limit ?></h2><p>Rufe eine HTTPS-Adresse deiner Website regelmäßig auf. Die Ausführung erfolgt in der Zeitzone des Hosting-Servers.</p>
    <?php foreach($rows as $row):$m=json_decode($row['metadata'],true); ?><div class="hosting-child"><p class="pre-wrap"><?= e($row['name']) ?> · alle <?= (int)($m['interval']??0) ?> Minuten</p><?php if(($m['state']??'')==='active'): ?><form method="post" data-confirm="Diesen Cronjob entfernen?"><?= csrf() ?><input type="hidden" name="action" value="hosting_cron_delete"><input type="hidden" name="service_id" value="<?= (int)$service['id'] ?>"><input type="hidden" name="child_id" value="<?= (int)$row['id'] ?>"><button class="text-button">Entfernen</button></form><?php else: ?><p>Einrichtung wird persönlich geprüft.</p><?php endif ?></div><?php endforeach ?>
    <?php if(count($rows)<$limit): ?><form method="post"><?= csrf() ?><input type="hidden" name="action" value="hosting_cron_create"><input type="hidden" name="service_id" value="<?= (int)$service['id'] ?>"><label class="field"><span>HTTPS-Adresse deiner Website</span><input name="url" type="url" required maxlength="2000" placeholder="https://<?= e($service['meta']['domain']) ?>/cron.php"></label><label class="field"><span>Intervall</span><select name="interval"><?php foreach([5=>'Alle 5 Minuten',10=>'Alle 10 Minuten',15=>'Alle 15 Minuten',30=>'Alle 30 Minuten',60=>'Stündlich',1440=>'Täglich'] as $v=>$name): ?><option value="<?= $v ?>"><?= $name ?></option><?php endforeach ?></select></label><button class="button button-teal">Cronjob anlegen</button></form><?php endif ?></section><?php
}
function hosting_child_usage(int $service,string $kind): int {
    $rows=all('SELECT metadata FROM shop_hosting_children WHERE service_id=? AND kind=?',[$service,$kind]);return count(array_filter($rows,fn($r)=>(json_decode($r['metadata'],true)['state']??'')!=='remote_missing'));
}
function sync_hosting_accesses(array $service): int {
    if($service['provider']!=='ispconfig')throw new RuntimeException('Ungültiger Anbieter.');
    return resource_mutex((int)$service['id'],function()use($service){
        $api=new IspconfigProvider();$site=hosting_site($service,$api);$count=0;
        foreach(['ftp'=>['sites_ftp_user_get','ftp_user_id','username'],'database'=>['sites_database_get','database_id','database_name'],'mail'=>['mail_user_get','mailuser_id','email']] as $kind=>[$method,$key,$name]){
            if((int)($service['meta']['limits'][$kind]??0)<1)continue;
            $filter=$kind==='mail'?['sys_groupid'=>(int)$site['sys_groupid'],'email'=>'%@'.$service['meta']['domain']]:['parent_domain_id'=>(int)$site['domain_id']];
            $rows=$api->call($method,['primary_id'=>$filter]);if(!$rows)$rows=[];if(isset($rows[$key]))$rows=[$rows];
            if(!is_array($rows) || count($rows)>500)throw new RuntimeException('Die Liste der Zugänge muss im Adminbereich geprüft werden.');
            $seen=[];
            foreach($rows as $record){
                if(!is_array($record) || (int)($record['sys_groupid']??0)!==(int)$site['sys_groupid'] || ($kind!=='mail' && (int)($record['parent_domain_id']??0)!==(int)$site['domain_id']) || ($kind==='mail' && !str_ends_with(strtolower($record['email']??''),'@'.$service['meta']['domain'])))continue;
                $external=(int)($record[$key]??0);$login=(string)($record[$name]??'');if(!$external || !$login)continue;
                $old=one('SELECT * FROM shop_hosting_children WHERE kind=? AND external_id=?',[$kind,(string)$external]);
                if($old && (int)$old['service_id']!==(int)$service['id'])throw new RuntimeException('Ein Zugang ist bereits einem anderen Produkt zugeordnet. Bitte den Support kontaktieren.');
                $seen[]=(string)$external;$metadata=$old?json_decode($old['metadata'],true):['imported'=>true];$metadata['state']='active';$metadata['remote_active']=($record['active']??$record['postfix']??'y')==='y';
                if($kind==='mail'){$metadata['display_name']=$record['name']??'';$metadata['quota_mb']=(int)ceil((int)($record['quota']??0)/1048576);}
                if($kind==='database'){
                    $user=$api->call('sites_database_user_get',['primary_id'=>(int)($record['database_user_id']??0)]);
                    if(!is_array($user) || (int)($user['sys_groupid']??0)!==(int)$site['sys_groupid'])throw new RuntimeException('Der Datenbankbenutzer muss durch den Support geprüft werden.');
                    $metadata['database_user_id']=(int)$record['database_user_id'];$metadata['database_user']=$user['database_user']??'';
                }
                if($old)query('UPDATE shop_hosting_children SET name=?,metadata=? WHERE id=?',[$login,json_encode($metadata),$old['id']]);
                else{query('INSERT INTO shop_hosting_children (service_id,kind,external_id,name,metadata) VALUES (?,?,?,?,?)',[$service['id'],$kind,(string)$external,$login,json_encode($metadata)]);$count++;}
            }
            foreach(all('SELECT id,external_id,metadata FROM shop_hosting_children WHERE service_id=? AND kind=?',[$service['id'],$kind]) as $old){
                if(ctype_digit($old['external_id']) && !in_array($old['external_id'],$seen,true)){$metadata=json_decode($old['metadata'],true);$metadata['state']='remote_missing';query('UPDATE shop_hosting_children SET metadata=? WHERE id=?',[json_encode($metadata),$old['id']]);}
            }
        }
        return $count;
    });
}
