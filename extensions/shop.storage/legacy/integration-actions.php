<?php
function automation_fields(string $provider): array {
    return match($provider){
      'ispconfig'=>['cron_limit'=>['Web-Cronjobs','number','0'],'php_enabled'=>['PHP in der Grundausstattung','checkbox','1'],'cgi_enabled'=>['CGI-Bin in der Grundausstattung','checkbox','0'],'storage_mb'=>['Webspace in MB','number','1024'],'ftp_limit'=>['FTP-Zugänge','number','2'],'database_limit'=>['Datenbanken','number','2'],'mail_limit'=>['Postfächer','number','5'],'mail_quota_mb'=>['Speicher je Postfach in MB','number','1024'],'create_ftp'=>['Ersten FTP-Zugang automatisch anlegen','checkbox','1']],
      'proxmox'=>['template_vmid'=>['Cloud-Init-Template-VMID','number','9000'],'template_node'=>['Template-Node (leer = Ziel-Node)','text',''],'storage'=>['Ziel-Storage (leer = Template-Vorgabe)','text',''],'cores'=>['vCPU','number','2'],'memory_mb'=>['Arbeitsspeicher in MB','number','2048'],'cloud_user'=>['Cloud-Init-Benutzer','text','debian'],'ipconfig0'=>['Cloud-Init-Netzwerk','text','ip=dhcp'],'start_after_clone'=>['Nach Einrichtung starten','checkbox','1']],
      'ovh'=>['allowed_tlds'=>['Freigegebene Endungen, kommagetrennt','text','de,com,eu'],'max_purchase_cents'=>['Maximaler OVH-Einkaufspreis in Cent inkl. USt.','number','2000'],'domain_configuration'=>['OVH-Domain-Konfiguration (JSON; Inhaberkontakt: {customer_contact})','json','{"OWNER_CONTACT":"{customer_contact}"}']],
      default=>[],
    };
}
function validated_automation(array $p,string $provider,array $input,bool $enabled): array {
    $expected=['hosting'=>'ispconfig','server'=>'proxmox','domain'=>'ovh','managed'=>'manual'];
    if(($expected[$p['category']]??'')!==$provider)throw new RuntimeException('Anbieter und Produktkategorie passen nicht zusammen.');
    $profile=['contract_text'=>trim((string)($input['contract_text']??''))];
    if(strlen($profile['contract_text'])>2000)throw new RuntimeException('Die Vertragsbeschreibung ist zu lang.');
    foreach(automation_fields($provider) as $key=>$f){
        $value=trim((string)($input[$key]??((in_array($key,['cron_limit','php_enabled','cgi_enabled'],true) || ($provider==='proxmox' && ($input['virtualization']??'qemu')==='lxc'))?$f[2]:'')));
        if($f[1]==='number'){
            if(!preg_match('/^\d{1,9}$/D',$value))throw new RuntimeException('Bitte gültige positive Zahlen für Ausstattung und Limits eintragen.');
            $value=(int)$value;
            if($value>100000000)throw new RuntimeException('Ein Ausstattungslimit ist zu groß.');
        }elseif($f[1]==='checkbox')$value=isset($input[$key])?(in_array((string)$input[$key],['1','on'],true)?1:0):($key==='php_enabled' && !isset($input['hosting_features_present'])?1:0);
        elseif($f[1]==='json'){
            try{$value=json_decode($value,true,8,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new RuntimeException('Die Domain-Konfiguration muss ein gültiges JSON-Objekt sein.');}
            if(!is_array($value) || count($value)>30)throw new RuntimeException('Ungültige Domain-Konfiguration.');
            foreach($value as $label=>$v)if(!is_string($label) || !is_string($v) || strlen($label)>100 || strlen($v)>500)throw new RuntimeException('Domain-Konfiguration: nur kurze Bezeichner und Textwerte verwenden.');
        }elseif(strlen($value)>500 || preg_match('/[\r\n\x00]/',$value))throw new RuntimeException('Ein Ausstattungsfeld ist ungültig.');
        $profile[$key]=$value;
    }
    // Hosting includes unlimited traffic; browser-supplied limits are ignored.
    if($provider==='ispconfig'){$profile['traffic_mb']=-1;$profile=array_replace($profile,normalized_hosting_features($input,$profile,isset($input['website_features_present'])));}
    if($provider==='proxmox'){
        $profile['virtualization']=(string)($input['virtualization']??'qemu');if(!in_array($profile['virtualization'],['qemu','lxc'],true))throw new RuntimeException('Bitte QEMU oder LXC als Servertyp wählen.');
        if($enabled && !proxmox_has_resource_target())throw new RuntimeException('Bitte zuerst Ziel-Node und Shop-Ressourcenpool in der Proxmox-Schnittstelle ergänzen.');
        if(($profile['virtualization']==='qemu' && (int)$profile['template_vmid']<100) || (int)$profile['cores']<1 || (int)$profile['cores']>128 || (int)$profile['memory_mb']<128 || (int)$profile['memory_mb']>1048576)throw new RuntimeException('Bitte gültige Template-, CPU- und RAM-Werte eintragen.');
        if($profile['virtualization']==='qemu' && !preg_match('/^[a-z_][a-z0-9_-]{0,30}$/D',$profile['cloud_user']))throw new RuntimeException('Ungültiger Cloud-Init-Benutzername.');
        if($profile['virtualization']==='qemu' && $profile['ipconfig0']!=='ip=dhcp')throw new RuntimeException('Automatische VPS-Bereitstellung benötigt derzeit ein vorbereitetes Template mit DHCP-Netzwerk. Statische IP-Pools werden noch nicht automatisch vergeben.');
        foreach(['template_node','storage'] as $key)if($profile[$key]!=='' && !preg_match('/^[a-zA-Z0-9_.-]+$/D',$profile[$key]))throw new RuntimeException('Ungültiger Node- oder Storage-Name.');
    }
    if($provider==='ovh'){
        if(!preg_match('/^[a-z]{2,63}(?:\.[a-z]{2,63})*(?:,[a-z]{2,63}(?:\.[a-z]{2,63})*)*$/D',$profile['allowed_tlds']) || $profile['max_purchase_cents']<1)throw new RuntimeException('Bitte Endungen ohne Leerzeichen und eine Einkaufspreisgrenze eintragen.');
        if(($profile['domain_configuration']['OWNER_CONTACT']??'')!=='{customer_contact}')throw new RuntimeException('Der Inhaberkontakt muss {customer_contact} sein, damit jede Domain für den Kunden registriert wird.');
        if($p['billing']==='month')throw new RuntimeException('Domainregistrierung bitte als jährlichen oder einmaligen Tarif anlegen.');
        if($enabled && !isset($input['ovh_purchase_ack']))throw new RuntimeException('Bitte die kostenpflichtige OVH-Bestellung und die OVH-Verlängerungseinstellungen bestätigen.');
    }
    if($enabled){
        if(!package_price_valid($p))throw new RuntimeException('Bitte einen gültigen Grundpreis hinterlegen. Bei Webspace und VPS sind auch 0 € möglich.');
        if(!$profile['contract_text'])throw new RuntimeException('Bitte vor der Freischaltung die Leistung, Laufzeit und Kündigung des Pakets beschreiben.');
        if(!integration_ready($provider))throw new RuntimeException('Bitte die Verbindung zu '.provider_label($provider).' unter Schnittstellen erfolgreich testen, bevor das Paket freigeschaltet wird.');
    }
    $profile['connection_id']=$provider==='manual' || integration_row($provider)?integration_identity($provider):'';
    return $profile;
}
function save_automation(int $id,string $provider,array $input,bool $enabled): void {
    $p=one('SELECT * FROM shop_products WHERE id=?',[$id]);if(!$p)throw new RuntimeException('Produkt nicht gefunden.');
    if($provider==='proxmox' && !isset($input['virtualization'])){$previous=automation_profile($id);$input['virtualization']=$previous['settings']['virtualization']??'qemu';}
    $profile=validated_automation($p,$provider,$input,$enabled);
    if(one('SELECT product_id FROM shop_product_automation WHERE product_id=?',[$id]))query('UPDATE shop_product_automation SET provider=?,enabled=?,profile=? WHERE product_id=?',[$provider,$enabled?1:0,json_encode($profile),$id]);
    else query('INSERT INTO shop_product_automation (product_id,provider,enabled,profile) VALUES (?,?,?,?)',[$id,$provider,$enabled?1:0,json_encode($profile)]);
    audit_event('automation.save',(string)$id);
}
function handle_integration_action(string $action): bool {
    switch($action){
      case 'checkout_domain_check':
        require_user();header('Content-Type: application/json');
        try{
            rate_limit('checkout_domain_check',20,60);$p=product(text_field('product',80));
            if(!$p || !in_array($p['category'],['domain','hosting'],true))throw new RuntimeException('Für dieses Paket ist keine Domainprüfung vorgesehen.');
            $domain=valid_domain(text_field('domain',253));if(!$domain)throw new RuntimeException('Bitte eine Domain eingeben.');
            if(!integration_ready('ovh'))throw new RuntimeException('Die Domainprüfung ist gerade nicht erreichbar. Bitte versuche es später erneut.');
            $availability=$p['category']==='domain'?domain_lookup_result($domain):(new OvhProvider())->availability($domain);
            echo json_encode(array_intersect_key($availability,array_flip(['domain','status','message','url','price','action','extension','gross_cents'])));
        }catch(RuntimeException $exception){$unavailable=$exception instanceof ProviderFailure || $exception instanceof PDOException;http_response_code($unavailable?503:422);echo json_encode(['status'=>'error','message'=>$unavailable?'Die Domainprüfung ist gerade nicht erreichbar. Eine Domainbestellung wird erst nach erfolgreicher Prüfung weitergeleitet.':$exception->getMessage()]);}
        exit;
      case 'order_status':
        $customer=require_user();$order=one('SELECT * FROM shop_orders WHERE id=? AND customer_id=?',[(int)($_POST['order_id']??0),$customer['id']]);if(!$order)throw new RuntimeException('Bestellung nicht gefunden.');
        header('Content-Type: application/json');echo json_encode(order_status_payload($order));exit;
      case 'manual_fulfill':
        require_admin();complete_manual_order((int)($_POST['order_id']??0),text_field('identifier',253));flash('Die bezahlte Leistung wurde dem Kunden bereitgestellt.');redirect('/admin/bestellungen?id='.(int)$_POST['order_id']);
      case 'hosting_password':
        $customer=require_user();$id=(int)($_POST['service_id']??0);$service=bound_service($id,(int)$customer['id'],true);if($service['provider']!=='ispconfig')throw new RuntimeException('Ungültiger Anbieter.');rate_limit('service_password',20,3600);hosting_child_password($service,(int)($_POST['child_id']??0),(string)($_POST['new_password']??''));flash('Das neue Passwort wurde an ISPConfig übergeben.');redirect('/konto/verwalten?id='.$id);
      case 'vps_password':
        $customer=require_user();$id=(int)($_POST['service_id']??0);$service=bound_service($id,(int)$customer['id'],true);rate_limit('service_password',20,3600);change_vps_password($service,(string)($_POST['new_password']??''));flash('Das Passwort wurde im virtuellen Server geändert.');redirect('/konto/verwalten?id='.$id);
      case 'checkout_documents':
        require_admin();save_package_documents($_POST);flash('Die Bestellunterlagen wurden gespeichert.');redirect('/admin/produkte#bestellunterlagen');
      case 'ovh_authorization_prepare':
        require_admin();rate_limit('ovh_authorization',5,3600);ovh_prepare_authorization();flash('Die Freigabe ist vorbereitet. Öffne jetzt „Bei OVH freigeben“ und bestätige die aufgeführten Berechtigungen.');redirect('/admin/schnittstellen?anbieter=ovh#ovh-freigabe');
      case 'ovh_authorization_confirm':
        require_admin();rate_limit('ovh_authorization_confirm',20,900);$result=ovh_confirm_authorization(text_field('authorization_state',100));flash($result['message'].' Der neue Consumer Key wurde verschlüsselt übernommen.');redirect('/admin/schnittstellen?anbieter=ovh#konfiguration');
      case 'integration_save':
        require_admin();$provider=text_field('provider',30);integration_save($provider,$_POST,isset($_POST['enabled']));flash('Die Zugangsdaten wurden verschlüsselt gespeichert. Bitte die Verbindung erneut prüfen.');redirect('/admin/schnittstellen?anbieter='.rawurlencode($provider));
      case 'proxmox_targets':
        require_admin();header('Content-Type: application/json');header('Cache-Control: no-store');
        try{rate_limit('proxmox_targets',60,900);$targets=(new ProxmoxProvider(integration_config('proxmox',false)))->targetOptions();echo json_encode(['status'=>'ok']+$targets,JSON_THROW_ON_ERROR);}
        catch(Throwable $e){http_response_code(503);echo json_encode(['status'=>'error','message'=>$e instanceof ProviderFailure?$e->getMessage():'Die Proxmox-Auswahl ist gerade nicht erreichbar. Bitte die Zugangsdaten speichern und die Verbindung prüfen.']);}
        exit;
      case 'integration_test':
        require_admin();rate_limit('integration_test',20,900);$provider=text_field('provider',30);
        try{$result=isset(SMF\Core\Kernel::boot()->modules()->providers()[$provider])?SMF\Core\ProviderDiagnostics::check($provider):provider_client($provider)->test();query("UPDATE shop_integrations SET tested_at=CURRENT_TIMESTAMP,test_result='ok' WHERE provider=?",[$provider]);audit_event('integration.test',$provider);flash($result['message']);}
        catch(Throwable $e){query("UPDATE shop_integrations SET tested_at=NULL,test_result='failed' WHERE provider=?",[$provider]);audit_event('integration.test',$provider,'failed');throw $e;}
        redirect('/admin/schnittstellen?anbieter='.rawurlencode($provider));
      case 'automation_save':
        require_admin();$id=(int)($_POST['product_id']??0);save_automation($id,text_field('provider',30),$_POST,isset($_POST['enabled']));flash('Ausstattung und Bereitstellungsregeln gespeichert.');redirect('/admin/automatisierung?produkt='.$id);
      case 'resource_assign':
        require_admin();$id=(int)($_POST['service_id']??0);assign_existing_resource($id,text_field('provider',30),text_field('external_id',253),$_POST);flash('Die Anbieter-Ressource wurde geprüft und dem Kundenprodukt zugeordnet.');redirect('/admin/ressourcen?produkt='.$id);
      case 'resource_unlink':
        require_admin();$id=(int)($_POST['service_id']??0);
        if(one('SELECT id FROM shop_hosting_children WHERE service_id=?',[$id]))throw new RuntimeException('Für dieses Produkt bestehen noch verwaltete Zugänge. Bitte vor dem Trennen prüfen.');
        SMF\Core\ShopResourceBindings::revoke($id);query('DELETE FROM shop_resource_links WHERE service_id=?',[$id]);audit_event('resource.unlinked',(string)$id);flash('Die Zuordnung wurde getrennt. Die Ressource beim Anbieter wurde nicht gelöscht.');redirect('/admin/ressourcen');
      case 'dns_create':case 'dns_update':case 'dns_delete':case 'dns_refresh':
        $customer=require_user();$id=(int)($_POST['service_id']??0);$service=bound_service($id,(int)$customer['id'],true);if($service['provider']!=='ovh')throw new RuntimeException('Für dieses Produkt ist keine OVH-DNS-Zone verbunden.');rate_limit('dns_write',30,3600);
        resource_mutex($id,function()use($action,$service){$api=new OvhProvider();$zone=$service['meta']['zone'];if($action==='dns_refresh')$api->call('POST','/domain/zone/'.rawurlencode($zone).'/refresh');else{$record=$action==='dns_delete'?[]:validate_dns_record($_POST,true);$api->changeRecord($zone,substr($action,4),$record,(int)($_POST['record_id']??0),($_POST['nameserver_confirm']??'')==='1');}});
        audit_event($action,(string)$id);flash(trim('Die Änderung wurde an OVH übergeben und die DNS-Zone veröffentlicht. '.service_notice($service,'dns')));redirect('/konto/verwalten?id='.$id);
      case 'vps_control':
        $customer=require_user();$id=(int)($_POST['service_id']??0);$service=bound_service($id,(int)$customer['id'],true);if($service['provider']!=='proxmox')throw new RuntimeException('Für dieses Produkt ist keine Proxmox-VM verbunden.');rate_limit('vps_control',15,900);
        $command=text_field('command',20);$meta=$service['meta'];$api=new ProxmoxProvider();verify_proxmox_resource($service,$api);if(!$api->poolContains($meta['node'],(int)$meta['vmid'],proxmox_service_type($service)))throw new RuntimeException('Die VM ist nicht mehr im zugeordneten Shop-Pool. Bitte den Support kontaktieren.');
        $api->control($meta['node'],(int)$meta['vmid'],$command,proxmox_service_type($service));audit_event('vps.'.$command,(string)$id);flash('Der Serverauftrag wurde an Proxmox übergeben.');redirect('/konto/verwalten?id='.$id);
      case 'hosting_create':
        $customer=require_user();$id=(int)($_POST['service_id']??0);$service=bound_service($id,(int)$customer['id'],true);if($service['provider']!=='ispconfig')throw new RuntimeException('Für dieses Produkt ist kein ISPConfig-Webspace verbunden.');rate_limit('hosting_write',20,3600);
        hosting_child_create($service,text_field('kind',20),$_POST);flash('Der Zugang wurde bei ISPConfig angelegt. Die Einrichtung auf dem Hosting-Server kann einen Moment dauern.');redirect('/konto/verwalten?id='.$id);
      case 'hosting_delete':
        $customer=require_user();$id=(int)($_POST['service_id']??0);$service=bound_service($id,(int)$customer['id'],true);if($service['provider']!=='ispconfig')throw new RuntimeException('Ungültiger Anbieter.');hosting_child_delete($service,(int)($_POST['child_id']??0));flash('Der Zugang wurde beim Anbieter gelöscht.');redirect('/konto/verwalten?id='.$id);
      case 'resource_credentials':
        $customer=require_user();$id=(int)($_POST['service_id']??0);$service=bound_service($id,(int)$customer['id'],true);if(!$service['credentials'])throw new RuntimeException('Für dieses Produkt sind keine Zugangsdaten hinterlegt.');
        if($service['provider']==='proxmox')verify_proxmox_resource($service,new ProxmoxProvider());$GLOBALS['revealed_credentials']=secret_open($service['credentials'],'service:'.$id);$GLOBALS['revealed_service_id']=$id;audit_event('credentials.viewed',(string)$id);return true;
      case 'checkout_start':
        $customer=require_user();rate_limit('checkout_start',10,3600);$p=checkout_selection(text_field('product',80),(int)$customer['id'],(int)($_POST['quote_id']??0),text_field('domain',253));if(!$p)throw new RuntimeException('Dieses Produkt ist nicht verfügbar.');
        if(!isset($_POST['terms']) || !isset($_POST['privacy']) || !isset($_POST['invoice_pdf']))throw new RuntimeException('Bitte bestätige die Vertrags- und Datenschutzhinweise sowie den Rechnungserhalt als PDF.');
        $order=start_checkout($customer,$p,text_field('domain',253),text_field('checkout_key',100),$_POST);if(zero_base_order($order) || $order['payment_kind']==='no_charge')redirect('/konto/bestellungen?id='.$order['id']);approval_redirect($order['approval_url']);
      case 'paypal_confirm_return':
        $customer=require_user();$order=one('SELECT * FROM shop_orders WHERE reference=? AND customer_id=?',[text_field('reference',40),$customer['id']]);if(!$order)throw new RuntimeException('Bestellung nicht gefunden.');
        confirm_paypal_return($order,text_field('paypal_token',150));flash($order['payment_kind']==='subscription'?'PayPal-Freigabe geprüft. Die Einrichtung startet nach bestätigtem Zahlungseingang.':'PayPal-Zahlung bestätigt. Die Einrichtung wird vorbereitet.');redirect('/konto/bestellungen?id='.$order['id']);
      case 'paypal_cancel_subscription':
        $customer=require_user();$order=one("SELECT * FROM shop_orders WHERE id=? AND customer_id=? AND payment_kind='subscription'",[(int)($_POST['order_id']??0),$customer['id']]);if(!$order)throw new RuntimeException('Abonnement nicht gefunden.');
        redirect('/kuendigen?vertrag='.rawurlencode($order['reference']));
      case 'worker_run':
        require_admin();run_shop_worker(3);flash('Bereitstellungsaufträge geprüft.');redirect('/admin/bestellungen');
      case 'job_retry':
        require_admin();$jobId=(int)($_POST['job_id']??0);retry_provisioning_job($jobId);flash('Der Auftrag wurde zur kontrollierten Fortsetzung vorgemerkt.');redirect('/admin/bestellungen');
      case 'job_resolve':
        require_admin();resolve_provisioning_job((int)($_POST['job_id']??0),(int)($_POST['service_id']??0));flash('Die bestehende Ressource wurde dem bezahlten Auftrag zugeordnet.');redirect('/admin/bestellungen');
      default:return false;
    }
}
