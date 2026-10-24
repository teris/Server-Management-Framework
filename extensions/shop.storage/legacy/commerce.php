<?php
function shop_meta_get(string $key,?string $fallback=null): ?string {$row=one('SELECT meta_value FROM shop_meta WHERE meta_key=?',[$key]);return $row?$row['meta_value']:$fallback;}
function shop_meta_set(string $key,string $value): void {if(one('SELECT meta_key FROM shop_meta WHERE meta_key=?',[$key]))query('UPDATE shop_meta SET meta_value=? WHERE meta_key=?',[$value,$key]);else query('INSERT INTO shop_meta (meta_key,meta_value) VALUES (?,?)',[$key,$value]);}
function commerce_settings(): array {
    return array_replace(['seller_name'=>'','seller_address'=>'','seller_postal_code'=>'','seller_city'=>'','seller_country'=>'DE','seller_tax_id'=>'','seller_vat_id'=>'','seller_email'=>SMF\Core\ShopSettings::get('support_email'),'invoice_prefix'=>'RE','notice_hosting'=>'','notice_server'=>'Voraussichtliche Bereitstellung: ca. 2 Stunden nach bestätigtem Zahlungseingang.','notice_domain'=>'','notice_managed'=>'','notice_quote'=>'Voraussichtliche Bereitstellung: ca. 120 Sekunden nach bestätigtem Zahlungseingang.','notice_dns'=>'DNS-Änderungen können bis zu 24 Stunden benötigen, bis sie überall aktiv sind.'],json_decode(shop_meta_get('commerce_settings','{}'),true)??[]);
}
function save_commerce_settings(array $input): void {
    $s=commerce_settings();
    foreach($s as $key=>$default){$value=trim((string)($input[$key]??$default));$limit=str_starts_with($key,'notice_')?1000:255;if(preg_match_all('/./us',$value)>$limit || str_contains($value,"\0"))throw new RuntimeException('Ein Shop-Einstellungsfeld ist zu lang oder ungültig.');$s[$key]=$value;}
    $s['seller_country']=billing_country($s['seller_country']);if(!preg_match('/^[A-Z0-9-]{1,20}$/D',$s['invoice_prefix']))throw new RuntimeException('Der Rechnungspräfix darf 1–20 Großbuchstaben, Zahlen oder Bindestriche enthalten.');
    if($s['seller_email'] && !filter_var($s['seller_email'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('Bitte eine gültige Rechnungs-E-Mail-Adresse eintragen.');
    shop_meta_set('commerce_settings',json_encode($s,JSON_THROW_ON_ERROR));audit_event('commerce.settings');
}
function invoice_seller_ready(): bool {$s=commerce_settings();foreach(['seller_name','seller_address','seller_postal_code','seller_city','seller_country','seller_email'] as $key)if(!$s[$key])return false;return (bool)($s['seller_tax_id'] || $s['seller_vat_id']);}
function vat_from_net(int $net,int $bps=1900): array {$gross=intdiv($net*(10000+$bps)+5000,10000);return ['net_cents'=>$net,'tax_cents'=>$gross-$net,'gross_cents'=>$gross,'tax_bps'=>$bps];}
function vat_from_gross(int $gross,int $bps=1900): array {$net=intdiv($gross*10000+intdiv(10000+$bps,2),10000+$bps);return ['net_cents'=>$net,'tax_cents'=>$gross-$net,'gross_cents'=>$gross,'tax_bps'=>$bps];}
function product_options(array $p): array {
    if(isset($p['_options']))return $p['_options'];
    return one('SELECT * FROM shop_product_options WHERE product_id=?',[$p['id']])??['sale_mode'=>($p['price_cents']??0)>0?'direct':'request','net_cents'=>$p['price_cents']===null?null:vat_from_gross((int)$p['price_cents'])['net_cents'],'tax_bps'=>1900,'provisioning_notice'=>'','dns_notice'=>''];
}
function product_tax(array $p): array {if(isset($p['_contract_tax']))return $p['_contract_tax'];$o=product_options($p);$gross=(int)($p['price_cents']??0);return $o['net_cents']!==null && vat_from_net((int)$o['net_cents'],(int)$o['tax_bps'])['gross_cents']===$gross?vat_from_net((int)$o['net_cents'],(int)$o['tax_bps']):vat_from_gross($gross,(int)$o['tax_bps']);}
function product_sale_mode(array $p): string {return isset($p['_quote_id'])?'direct':product_options($p)['sale_mode'];}
function product_notice(array $p,string $kind='provisioning'): string {$o=product_options($p);$s=commerce_settings();return $kind==='dns'?($o['dns_notice']?:$s['notice_dns']):($o['provisioning_notice']?:($s['notice_'.$p['category']]??'Bereitstellung nach erfolgreicher Installation.'));}
function save_product_options(int $id,array $input,?int $net): void {
    $mode=(string)($input['sale_mode']??($net!==null?'direct':'request'));if(!in_array($mode,['direct','request'],true))throw new RuntimeException('Bitte Direktkauf oder Angebot auf Anfrage auswählen.');
    $delivery=trim((string)($input['provisioning_notice']??''));$dns=trim((string)($input['dns_notice']??''));if(strlen($delivery)>3000 || strlen($dns)>3000)throw new RuntimeException('Die Bereitstellungshinweise sind zu lang.');
    if(one('SELECT product_id FROM shop_product_options WHERE product_id=?',[$id]))query('UPDATE shop_product_options SET sale_mode=?,net_cents=?,tax_bps=1900,provisioning_notice=?,dns_notice=? WHERE product_id=?',[$mode,$net,$delivery,$dns,$id]);
    else query('INSERT INTO shop_product_options (product_id,sale_mode,net_cents,tax_bps,provisioning_notice,dns_notice) VALUES (?,?,?,1900,?,?)',[$id,$mode,$net,$delivery,$dns]);
}
function checkout_automation(array $p): ?array {return $p['_automation']??automation_profile((int)$p['id']);}
function commerce_event(string $key,string $type,array $payload): void {
    try{query('INSERT INTO shop_commerce_events (event_key,event_type,payload) VALUES (?,?,?)',[$key,$type,json_encode($payload,JSON_THROW_ON_ERROR)]);}catch(PDOException $e){if(!one('SELECT event_key FROM shop_commerce_events WHERE event_key=?',[$key]))throw $e;}
}
function customer_notification(int $customer,string $key,string $title,string $body,string $url): void {
    if(!str_starts_with($url,'/konto/') || str_contains($url,"\n"))throw new RuntimeException('Ungültiges Benachrichtigungsziel.');
    try{query('INSERT INTO shop_notifications (customer_id,title,body,url,event_key) VALUES (?,?,?,?,?)',[$customer,$title,$body,$url,$key]);}catch(PDOException $e){if(!one('SELECT id FROM shop_notifications WHERE event_key=?',[$key]))throw $e;}
    $notification=one('SELECT * FROM shop_notifications WHERE event_key=?',[$key]);
    if((int)$notification['customer_id']!==$customer)throw new RuntimeException('Die Benachrichtigung gehört zu einem anderen Kundenkonto.');
    notification_broadcast_admins($notification);
}
function order_commerce_snapshot(array $p): array {return ['tax'=>product_tax($p),'provisioning_notice'=>product_notice($p),'dns_notice'=>product_notice($p,'dns'),'contract_policy'=>product_contract_policy($p),'modules'=>$p['_module_selection']??[],'base_net_cents'=>$p['_module_base_net']??null,'document_versions'=>document_versions_for_product($p),'purchase_pricing'=>$p['_purchase_pricing']??null];}
function quote_link(int $id): string {return '/konto/angebote?id='.$id;}
function service_notice(array $service,string $kind): string {return $service['meta'][$kind.'_notice']??commerce_settings()[$kind==='dns'?'notice_dns':'notice_'.$service['category']];}
