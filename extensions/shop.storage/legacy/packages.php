<?php
function package_provider(string $category): string {return ['hosting'=>'ispconfig','server'=>'proxmox','domain'=>'ovh','managed'=>'manual','gameserver'=>'ogp'][$category]??'manual';}
function package_price_valid(array $p): bool {if(isset($p['_purchase_pricing']) && (int)$p['price_cents']===0 && ($p['_purchase_pricing']['coupon'] || $p['_purchase_pricing']['reward_id']))return true;return $p['price_cents']!==null && ((int)$p['price_cents']>0 || ((int)$p['price_cents']===0 && in_array($p['category'],['hosting','server'],true)));}
function provider_label(string $provider): string {return $provider==='manual'?'Persönliche Bereitstellung':(integration_definitions()[$provider]['name']??$provider);}
function checkout_documents(): array {
    $payment=integration_row('paypal')?integration_config('paypal',false):[];
    $row=one("SELECT meta_value FROM shop_meta WHERE meta_key='checkout_documents'");
    return ($row?json_decode($row['meta_value'],true):[]) + ['terms_url'=>$payment['terms_url']??'','withdrawal_url'=>$payment['withdrawal_url']??''];
}
function package_requirements(array $p): array {
    $a=checkout_automation($p);$provider=$a['provider']??package_provider($p['category']);$settings=$a['settings']??[];
    $payment=integration_row('paypal')?integration_config('paypal',false):[];$documents=checkout_documents_for($p);
    $worker=one("SELECT meta_value FROM shop_meta WHERE meta_key='worker_last_run'");
    $items=[
        'sale_mode'=>['Direktkauf oder persönliches Angebot freigegeben',product_sale_mode($p)==='direct','/admin/produkte?id='.$p['id']],
        'invoice'=>['Rechnungsstellerdaten vollständig',invoice_seller_ready(),'/admin/shop'],
        'smtp'=>['E-Mail-Versand eingerichtet und geprüft',smtp_ready(),'/admin/email'],
        'price'=>['Grundpreis hinterlegt (Webspace/VPS auch 0 €)',package_price_valid($p),'/admin/produkte?id='.$p['id']],
        'active'=>['Paket im Shop sichtbar',(bool)$p['active'],'/admin/produkte?id='.$p['id']],
        'profile'=>['Ausstattung und Direktkauf gespeichert',$a && (bool)$a['enabled'],'/admin/produkte?id='.$p['id'].'#paket-ausstattung'],
        'contract'=>['Leistung, Laufzeit und Kündigung beschrieben',!empty($settings['contract_text']),'/admin/produkte?id='.$p['id'].'#paket-ausstattung'],
        'provider'=>[provider_label($provider).' bereit',$provider==='manual' || integration_ready($provider),$provider==='manual'?'/admin/produkte?id='.$p['id']:'/admin/schnittstellen?anbieter='.$provider],
        'binding'=>['Aktueller Anbieterzugang zugeordnet',$a && ($provider==='manual' || integration_row($provider)) && hash_equals($settings['connection_id']??'',integration_identity($provider)),'/admin/produkte?id='.$p['id'].'#paket-ausstattung'],
        'paypal'=>['PayPal-Verbindung geprüft',integration_ready('paypal'),'/admin/schnittstellen?anbieter=paypal'],
        'webhook'=>['PayPal-Webhook hinterlegt',!empty($payment['webhook_id']),'/admin/schnittstellen?anbieter=paypal'],
        'terms'=>['Vertragsbedingungen verlinkt',!empty($documents['terms_url']),'/admin/produkte#bestellunterlagen'],
        'withdrawal'=>['Widerrufsinformationen verlinkt',!empty($documents['withdrawal_url']),'/admin/produkte#bestellunterlagen'],
        'worker'=>['Automatische Auftragsverarbeitung erreichbar',($payment['mode']??'sandbox')!=='live' || ($worker && (int)$worker['meta_value']>=time()-300),'/admin/automatisierung'],
    ];
    $productModule=['hosting'=>'webhosting','server'=>'vps','gameserver'=>'gameserver'][$p['category']]??null;
    if($productModule){
        $registry=SMF\Core\Kernel::boot()->modules();
        $items['product_module']=['Produktmodul aktiviert',$registry->enabled('product.'.$productModule),'/admin/einrichtung'];
        $types=new SMF\Core\ProductRegistry($registry);$required=[];foreach($registry->providers()as$id=>$entry)$required[$id]=SMF\Core\ProviderDiagnostics::capabilities($id);
        $items['product_capabilities']=['Erforderliche Produkt- und Providerfähigkeiten geprüft',!$types->blockers($productModule,$required),'/admin/einrichtung'];
        if($productModule==='webhosting')foreach(['mail_limit'=>['mail.read','mail.create'],'database_limit'=>['database.read','database.create']]as$field=>$caps)if((int)($settings[$field]??0)>0)$items['capability_'.$field]=['Providerkontingent für '.$field.' geprüft',!array_diff($caps,$required['ispconfig']??[]),'/admin/einrichtung'];
        if($productModule==='gameserver')$items['game_capabilities']=['Gameserverfähigkeiten und Anschluss geprüft',class_exists('SMF\\Modules\\Gameserver\\Catalog') && !SMF\Modules\Gameserver\Catalog::blockers($p),'/admin/gameserver'];
    }
    if(isset($p['_domain_tld_id']))$items['domain_extension']=['Domainendung freigegeben',(bool)$p['_tld_enabled'],'/admin/domains?id='.$p['_domain_tld_id']];
    if($provider==='ovh')$items['domain_cost']=['OVH-Einkaufsgrenze hinterlegt',(int)($settings['max_purchase_cents']??0)>0,isset($p['_domain_tld_id'])?'/admin/domains?id='.$p['_domain_tld_id']:'/admin/produkte?id='.$p['id']];
    if($provider==='ovh')$items['domain_billing']=['Domainpreis für ein Registrierungsjahr',in_array($p['billing'],['year','once'],true),'/admin/produkte?id='.$p['id']];
    if($provider==='proxmox')$items['target']=['Proxmox-Node und Kundenpool hinterlegt',integration_row('proxmox') && proxmox_has_resource_target(integration_config('proxmox',false)),'/admin/schnittstellen?anbieter=proxmox'];
    if($provider==='proxmox' && ($settings['virtualization']??'qemu')==='lxc'){$items['lxc_templates']=['Mindestens eine LXC-Vorlage freigegeben',(bool)server_template_choices((int)$p['id']),'/admin/infrastruktur?bereich=vorlagen'];$items['lxc_addresses']=['IP-/MAC-Zuordnung im Pool freigegeben',(bool)one("SELECT id FROM shop_network_addresses WHERE state='available' AND order_id IS NULL AND service_id IS NULL"),'/admin/infrastruktur?bereich=ips'];}
    return $items;
}
function package_blockers(array $p): array {return array_filter(package_requirements($p),fn($item)=>!$item[1]);}
function package_is_fixed(array $p): bool {return product_sale_mode($p)==='direct' && package_price_valid($p);}
function checked_document_url(string $value): string {
    $parts=parse_url($value);
    if(strlen($value)>2048 || !$parts || ($parts['scheme']??'')!=='https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || preg_match('/[\x00-\x20]/',$value))throw new RuntimeException('Bitte eine gültige HTTPS-Adresse für die Bestellunterlagen eintragen.');
    return $value;
}
function save_package_documents(array $input): void {
    $documents=[];foreach(['terms_url','withdrawal_url'] as $key){$value=trim((string)($input[$key]??''));$documents[$key]=$value===''?'':checked_document_url($value);}
    $value=json_encode($documents,JSON_THROW_ON_ERROR);
    if(one("SELECT meta_key FROM shop_meta WHERE meta_key='checkout_documents'"))query("UPDATE shop_meta SET meta_value=? WHERE meta_key='checkout_documents'",[$value]);
    else query("INSERT INTO shop_meta (meta_key,meta_value) VALUES ('checkout_documents',?)",[$value]);
    audit_event('checkout.documents.save');
}
function save_package_configuration(int $id,array $input): void {
    $p=one('SELECT * FROM shop_products WHERE id=?',[$id]);$provider=package_provider($p['category']);
    $settings=(array)($input['automation'][$provider]??[]);
    $settings['contract_text']=$input['contract_text']??'';if(isset($input['hosting_features_present']))$settings['hosting_features_present']=1;
    if(isset($input['ovh_purchase_ack']))$settings['ovh_purchase_ack']='1';
    if(isset($input['automation'][$provider]['start_after_clone']))$settings['start_after_clone']='1';
    save_automation($id,$provider,$settings,isset($input['direct_purchase']));
    save_package_module_bindings($id,$input);
    save_server_template_bindings($id,$input);
}
function service_for_order(int $orderId,int $customerId): ?array {
    foreach(all('SELECT s.id,l.metadata FROM shop_services s JOIN shop_resource_links l ON l.service_id=s.id WHERE s.customer_id=?',[$customerId]) as $service)if((int)(json_decode($service['metadata'],true)['order_id']??0)===$orderId)return $service;
    return null;
}
function customer_pending_products(int $customerId,bool $serversOnly=false): array {
    $linked=[];
    foreach(all('SELECT l.metadata FROM shop_services s JOIN shop_resource_links l ON l.service_id=s.id WHERE s.customer_id=?',[$customerId]) as $service){$id=(int)(json_decode($service['metadata'],true)['order_id']??0);if($id)$linked[$id]=true;}
    $orders=all("SELECT o.*,p.category FROM shop_orders o LEFT JOIN shop_products p ON p.id=o.product_id LEFT JOIN shop_contracts c ON c.order_id=o.id WHERE o.customer_id=? AND o.fulfillment_status IN ('not_paid','awaiting_modules','queued','provisioning','domain_verification','needs_action') AND o.payment_status IN ('pending','paid','test_paid','no_charge') AND (c.state IS NULL OR c.state NOT IN ('cancel_requested','ended','unpaid_cancelled')) AND NOT EXISTS (SELECT 1 FROM shop_module_intents m WHERE m.order_id=o.id) ORDER BY o.id DESC",[$customerId]);
    return array_values(array_filter(array_map(function($order)use($linked,$serversOnly,$customerId){
        if(isset($linked[(int)$order['id']]) || !empty(json_decode($order['configuration'],true)['module_addon']) || !customer_order((int)$order['id'],$customerId))return null;
        $order['category']=$order['category']??(['proxmox'=>'server','ispconfig'=>'hosting','ovh'=>'domain'][$order['provider']]??'managed');
        return $serversOnly && $order['category']!=='server'?null:$order;
    },$orders)));
}
function order_status_payload(array $order): array {
    $labels=['awaiting_modules'=>'Bitte die ausgewählten Module bezahlen','domain_verification'=>'Deine Bestätigung und der Prüf-TXT sind für die Domainverbindung erforderlich','not_paid'=>'Wartet auf Zahlung','queued'=>'Bestellung bestätigt – Einrichtung vorgemerkt','provisioning'=>'Dein Paket wird eingerichtet','active'=>'Dein Paket ist bereit','needs_action'=>'Deine Einrichtung wird durch unser Team bearbeitet','sandbox'=>'Sandbox-Test abgeschlossen – keine echte Bereitstellung'];
    $service=service_for_order((int)$order['id'],(int)$order['customer_id']);if(!$service && ($intent=module_order_intent((int)$order['id'])))$service=service_for_order((int)$intent['parent_order_id'],(int)$order['customer_id']);
    if($service && (!customer_order((int)$order['id'],(int)$order['customer_id']) || !customer_service_visible((int)$service['id'],(int)$order['customer_id'])))$service=null;
    return ['payment_status'=>$order['payment_status'],'fulfillment_status'=>$order['fulfillment_status'],'label'=>$labels[$order['fulfillment_status']]??$order['fulfillment_status'],'service_url'=>$service?'/konto/verwalten?id='.$service['id']:null];
}

function package_specs(string $provider,array $profile): array {
    if($provider==='ispconfig'){
        $captions=['storage_mb'=>'Webspace (MB)','traffic_mb'=>'Traffic','ftp_limit'=>'FTP-Zugänge','database_limit'=>'Datenbanken','mail_limit'=>'Postfächer','mail_quota_mb'=>'Speicher je Postfach (MB)','cron_limit'=>'Web-Cronjobs'];
    }elseif($provider==='proxmox')$captions=['cores'=>'vCPU','memory_mb'=>'Arbeitsspeicher (MB)','disk_extra_gb'=>'Zusätzliche Systemplatte (GB)'];
    elseif($provider==='ovh')$captions=['allowed_tlds'=>'Verfügbare Domainendungen'];
    else return [];
    $result=[];
    foreach($captions as $key=>$caption){
        if($provider==='ispconfig' && $key==='traffic_mb')$result[$caption]='Unbegrenzt';
        elseif($provider==='proxmox' && $key==='disk_extra_gb')$result[$caption]=(string)($profile[$key]??0);
        elseif(isset($profile[$key]))$result[$caption]=(string)$profile[$key];
    }
    if($provider==='ispconfig' && isset($profile['website_features_present'])){$f=normalized_hosting_features($profile,$profile);$result['PHP']=hosting_php_modes()[$f['php_mode']];$result['PHP-Version']=$profile['php_version_label']??($f['php_version']?'Ausgewählte Serverversion':'Systemstandard');$result['Auto-Subdomain']=['none'=>'Keine','www'=>'www.','*'=>'*.'][$f['auto_subdomain']];foreach(hosting_feature_flags() as $key=>$caption)$result[$caption]=$f[$key]?'Enthalten':'Nicht enthalten';}
    return $result;
}

function domain_product_for(string $domain): ?array {
    if(domain_catalog_managed()){$row=domain_tld_for($domain);return $row?domain_priced_product($row):null;}
    $tld=domain_public_suffix($domain);$candidates=[];
    foreach(all("SELECT * FROM shop_products WHERE category='domain' AND active=1 ORDER BY id") as $p){
        $profile=automation_profile((int)$p['id']);$allowed=$profile['settings']['allowed_tlds']??'';
        if($allowed!=='' && !in_array($tld,explode(',',$allowed),true))continue;
        $p['_score']=(checkout_ready($p)?4:0)+(package_is_fixed($p)?2:0)+($allowed!==''?1:0);$candidates[]=$p;
    }
    usort($candidates,fn($a,$b)=>$b['_score']<=>$a['_score']);return $candidates[0]??null;
}
