<?php
function provision_ispconfig(array $order,array &$job,array $profile,array $data): void {
    provisioning_target_service($order,$job);
    if(in_array($job['phase'],['start','client_ready'],true) && !hosting_domain_provisioning_gate($order,$job,$data))return;
    $api=new IspconfigProvider();$config=integration_config('ispconfig');$customer=one('SELECT * FROM customers WHERE id=?',[$order['customer_id']]);$domain=valid_domain($order['domain']);
    if(!$domain)throw new RuntimeException('Für die Webspace-Einrichtung fehlt eine Domain.');
    if(in_array($job['phase'],['start','client_ready'],true) && isset($profile['website_features_present'])){hosting_capabilities(true);hosting_website_feature_params($profile,true);}
    if($job['phase']==='start'){
        $account=one("SELECT * FROM shop_provider_accounts WHERE customer_id=? AND provider='ispconfig'",[$order['customer_id']]);
        if($account){
            $data['client_id']=(int)$account['external_id'];$client=$api->client($data['client_id']);
            if(($client['customer_no']??'')==='OC-'.$customer['id']){
                $params=hosting_account_limits((int)$customer['id'],$data['client_id'],$api,$profile);$params['password']='';
                if(!$api->call('client_update',['client_id'=>$data['client_id'],'reseller_id'=>(int)($client['parent_client_id']??0),'params'=>$params]))throw new RuntimeException('Die ISPConfig-Paketlimits konnten nicht aktualisiert werden.');
            }
        }
        else{
            // Persist intent before a non-idempotent remote call. An ambiguous failure
            // needs operator review rather than creating the customer a second time.
            $panelCredentials=['username'=>'oc'.$customer['id'],'password'=>'Oc!'.bin2hex(random_bytes(20))];
            $data['client_credentials']=secret_seal($panelCredentials,'job:'.$job['id'].':ispconfig');save_job_phase($job,'creating_client',$data,'running');
            $clientId=(int)$api->call('client_add',['reseller_id'=>(int)($config['reseller_id']??0),'params'=>[
                'username'=>$panelCredentials['username'],'password'=>$panelCredentials['password'],'contact_name'=>$customer['first_name'].' '.$customer['last_name'],'company_name'=>$customer['company']??'','email'=>$customer['email'],'street'=>$customer['address']??'','zip'=>$customer['postal_code']??'','city'=>$customer['city']??'','country'=>strtoupper($customer['country']??'DE'),'language'=>'de','customer_no'=>'OC-'.$customer['id'],'template_master'=>(int)($config['client_template_id']??0),
                'default_webserver'=>(int)$config['server_id'],'default_mailserver'=>(int)$config['mail_server_id'],'default_dbserver'=>(int)$config['db_server_id'],'modules'=>'dashboard,sites,mail,tools','startmodule'=>'dashboard',
            ]+hosting_profile_client_limits($profile)]);
            if($clientId<1)throw new ProviderFailure('ispconfig');
            $data['client_id']=$clientId;query("INSERT INTO shop_provider_accounts (customer_id,provider,external_id) VALUES (?,'ispconfig',?)",[$customer['id'],(string)$clientId]);
            save_hosting_panel_login((int)$customer['id'],$clientId,$panelCredentials);
        }
        save_job_phase($job,'client_ready',$data);
    }
    if($job['phase']==='client_ready'){
        $exists=$api->call('sites_web_domain_get',['primary_id'=>['domain'=>$domain]]);
        if($exists)throw new RuntimeException('Die Domain existiert bereits in ISPConfig. Bitte manuell zuordnen.');
        $features=hosting_website_feature_params($profile,isset($profile['website_features_present']));
        save_job_phase($job,'creating_website',$data,'running');
        $id=(int)$api->call('sites_web_domain_add',['client_id'=>$data['client_id'],'readonly'=>false,'params'=>array_replace([
            'server_id'=>(int)$config['server_id'],'ip_address'=>infrastructure_settings()['web_ipv4'],'domain'=>$domain,'type'=>'vhost','parent_domain_id'=>0,'vhost_type'=>'name','http_port'=>80,'https_port'=>443,'hd_quota'=>(int)$profile['storage_mb'],'traffic_quota'=>-1,'cgi'=>!empty($profile['cgi_enabled'])?'y':'n','ssi'=>'n','suexec'=>'y','php'=>($profile['php_enabled']??1)?'php-fpm':'no','ruby'=>'n','active'=>'y','ssl'=>'n','ssl_letsencrypt'=>'n','subdomain'=>'www','errordocs'=>1,'allow_override'=>'All','apache_directives'=>'','nginx_directives'=>'','custom_php_ini'=>'','pm'=>'ondemand','pm_max_children'=>5,'pm_max_requests'=>500,'pm_process_idle_timeout'=>10,'backup_interval'=>'none','backup_copies'=>1,'document_root'=>'','system_user'=>'','system_group'=>'',
        ],$features)]);
        if($id<1)throw new ProviderFailure('ispconfig');$data['website_id']=$id;query("UPDATE shop_hosting_domain_verifications SET activated_ts=?,state='active' WHERE order_id=?",[time(),$order['id']]);save_job_phase($job,'website_ready',$data);
    }
    if(in_array($job['phase'],['website_ready','ftp_ready'],true)){
        $site=$api->website((int)$data['website_id']);
        if(($site['domain']??'')!==$domain || $api->clientIdForGroup((int)$site['sys_groupid'])!==(int)$data['client_id'])throw new RuntimeException('Die erzeugte Website konnte nicht eindeutig zugeordnet werden.');
        // Every website owns a mail domain and signing key; mailbox quotas stay independent.
        $mail=$api->mailDomain($domain);
        if($mail && (int)$mail['sys_groupid']!==(int)$site['sys_groupid'])throw new RuntimeException('Die Maildomain gehört zu einem anderen ISPConfig-Kunden.');
        if(!$mail){save_job_phase($job,'creating_maildomain',$data,'running');$api->call('mail_domain_add',['client_id'=>$data['client_id'],'params'=>['server_id'=>(int)$config['mail_server_id'],'domain'=>$domain,'active'=>'y']]);save_job_phase($job,'website_ready',$data);}
        ensure_hosting_dkim($api,(int)$order['customer_id'],(int)$data['client_id'],(int)$site['sys_groupid'],$domain);
        $credentials=null;$children=[];
        if(!empty($profile['create_ftp']) && (int)$profile['ftp_limit']>0){
            if(empty($data['ftp_id'])){
                if(empty($site['document_root']) || empty($site['system_user']) || empty($site['system_group'])){save_job_phase($job,'website_ready',$data);return;}
                $credentials=['username'=>'c'.$data['client_id'].'o'.$order['id'].'_web','password'=>bin2hex(random_bytes(18)),'host'=>$domain];
                $data['ftp_credentials']=secret_seal($credentials,'job:'.$job['id'].':ftp');save_job_phase($job,'creating_ftp',$data,'running');
                $data['ftp_id']=(int)$api->call('sites_ftp_user_add',['client_id'=>$data['client_id'],'params'=>['server_id'=>(int)$site['server_id'],'parent_domain_id'=>(int)$site['domain_id'],'username'=>$credentials['username'],'password'=>$credentials['password'],'quota_size'=>(int)$site['hd_quota'],'active'=>'y','uid'=>$site['system_user'],'gid'=>$site['system_group'],'dir'=>$site['document_root'],'quota_files'=>-1,'ul_ratio'=>-1,'dl_ratio'=>-1,'ul_bandwidth'=>-1,'dl_bandwidth'=>-1]]);
                if($data['ftp_id']<1)throw new ProviderFailure('ispconfig');save_job_phase($job,'ftp_ready',$data);
            }
            $credentials=secret_open($data['ftp_credentials'],'job:'.$job['id'].':ftp');$children[]=['kind'=>'ftp','external_id'=>$data['ftp_id'],'name'=>$credentials['username']];
        }
        finish_provisioning($order,$job,'website/'.$data['website_id'],['website_id'=>$data['website_id'],'client_id'=>$data['client_id'],'group_id'=>(int)$site['sys_groupid'],'domain'=>$domain,'limits'=>['ftp'=>(int)$profile['ftp_limit'],'database'=>(int)$profile['database_limit'],'mail'=>(int)$profile['mail_limit'],'mail_quota_mb'=>(int)$profile['mail_quota_mb'],'cron'=>(int)($profile['cron_limit']??0)],'storage_mb'=>(int)$profile['storage_mb'],'features'=>normalized_hosting_features($profile,$profile),'modules'=>$profile['module_selection']??[]],$domain,$credentials,$children);
    }
}

function end_hosting_service(array $service): void {
    $api=new IspconfigProvider();$site=hosting_site($service,$api);$client=(int)$service['meta']['client_id'];
    if(($site['active']??'')!=='n'){$site['active']='n';$api->call('sites_web_domain_update',['client_id'=>$client,'primary_id'=>(int)$site['domain_id'],'params'=>['active'=>'n']]);}
    foreach(all('SELECT * FROM shop_hosting_children WHERE service_id=?',[$service['id']]) as $child){
        if(!ctype_digit((string)$child['external_id']))throw new RuntimeException('Ein noch unklarer Hosting-Zugang muss vor der Vertragsbeendigung geprüft werden.');
        if($child['kind']==='cron'){$record=$api->call('sites_cron_get',['cron_id'=>(int)$child['external_id']]);if(!is_array($record) || (int)($record['parent_domain_id']??0)!==(int)$site['domain_id'])throw new RuntimeException('Die Zuordnung eines Cronjobs hat sich geändert.');if(($record['active']??'')!=='n')$api->call('sites_cron_update',['client_id'=>$client,'cron_id'=>(int)$child['external_id'],'params'=>['active'=>'n']]);continue;}
        $method=['ftp'=>'sites_ftp_user','database'=>'sites_database','mail'=>'mail_user'][$child['kind']]??null;if(!$method)continue;
        $record=$api->call($method.'_get',['primary_id'=>(int)$child['external_id']]);
        if(!is_array($record) || ($child['kind']!=='mail' && (int)($record['parent_domain_id']??0)!==(int)$site['domain_id']) || ($child['kind']==='mail' && (int)($record['sys_groupid']??0)!==(int)$site['sys_groupid']))throw new RuntimeException('Die Zuordnung eines Hosting-Zugangs hat sich geändert. Keine fremden Ressourcen werden gesperrt.');
        if(($record['active']??'')!=='n'){unset($record['password'],$record['database_password']);$record['active']='n';$api->call($method.'_update',['client_id'=>$client,'primary_id'=>(int)$child['external_id'],'params'=>['active'=>'n']]);}
    }
    $domain=$api->mailDomain($service['meta']['domain']);
    if($domain){if((int)($domain['sys_groupid']??0)!==(int)$site['sys_groupid'])throw new RuntimeException('Die Maildomain ist einem anderen Kunden zugeordnet.');if(($domain['active']??'')!=='n'){$domain['active']='n';$api->call('mail_domain_update',['client_id'=>$client,'primary_id'=>(int)$domain['domain_id'],'params'=>['active'=>'n']]);}}
}
