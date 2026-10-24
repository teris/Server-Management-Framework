<?php
/** Independent add-on contracts keep base orders and historical invoices immutable. */
function module_order_intent(int $order): ?array {return one('SELECT * FROM shop_module_intents WHERE order_id=?',[$order]);}
function module_parent_service(array $intent,bool $write=false): array {
    $parent=one('SELECT * FROM shop_orders WHERE id=? AND customer_id=?',[$intent['parent_order_id'],$intent['customer_id']]);
    if(!$parent || !in_array($parent['provider'],['ispconfig','proxmox'],true))throw new RuntimeException('Das Grundpaket dieses Zusatzmoduls ist nicht verfügbar.');
    $linked=service_for_order((int)$parent['id'],(int)$parent['customer_id']);if(!$linked)throw new RuntimeException('Das Grundpaket wird noch eingerichtet. Bitte warte, bis es verfügbar ist.');
    $service=bound_service((int)$linked['id'],(int)$parent['customer_id'],$write);if($service['provider']!==$parent['provider'])throw new RuntimeException('Grundpaket und Anbieterzuordnung stimmen nicht überein.');return $service;
}
function module_parent_for_service(array $service,bool $buy=false,bool $provision=false): array {
    $parent=one('SELECT * FROM shop_orders WHERE id=? AND customer_id=?',[(int)($service['meta']['order_id']??0),(int)$service['customer_id']]);
    if(!$parent || $parent['provider']!==$service['provider'] || !in_array($parent['provider'],['ispconfig','proxmox'],true))throw new RuntimeException('Dieses Produkt benötigt zuerst eine zugeordnete Hosting- oder VPS-Bestellung.');
    if($buy){$c=ensure_order_contract($parent);if($service['status']!=='active' || !order_payment_allows_provisioning($parent) || !$c || contract_closed($c) || (int)$c['cancel_at']>0)throw new RuntimeException('Zusatzmodule können für aktive, bezahlte und ungekündigte Grundpakete bestellt werden.');if(!$provision && admin_order_released($parent) && !(int)$c['paid_until'])throw new RuntimeException('Bitte zuerst die offene Rechnung des Grundpakets begleichen, bevor du weitere Module bestellst.');if(!billing_months($parent['billing']))throw new RuntimeException('Für Zusatzabonnements wird ein laufendes Grundpaket benötigt.');}
    return $parent;
}
function module_line_for_choice(array $module,mixed $choice): array {
    if(module_is_included($module))throw new RuntimeException('Dieses Modul ist bereits fest im Grundpaket enthalten.');$qty=module_quantity($choice);if(!$qty)throw new RuntimeException('Bitte eine zusätzliche Menge oder Option auswählen.');
    if($module['control']==='select'){$option=$module['options'][$qty]??null;if(!$option)throw new RuntimeException('Diese Moduloption wird nicht angeboten.');$count=1;$effect=$option['value'];$unit=(int)$option['net_cents'];$label=$module['label'].': '.$option['label'];}
    else{if($qty<(int)$module['min_qty'] || ((int)$module['max_qty']>0 && $qty>(int)$module['max_qty']))throw new RuntimeException('Die gewünschte Menge liegt außerhalb deiner Modulgrenzen.');$count=$qty;$effect=$qty*(int)$module['unit_value'];$unit=(int)$module['net_cents'];$label=$module['label'];}
    $net=$count*$unit;if($net<1 || $net>100000000)throw new RuntimeException('Kostenlose Optionen sind bereits enthalten. Bitte ein kostenpflichtiges Zusatzmodul auswählen.');
    $line=['id'=>(int)$module['id'],'catalog_id'=>$module['_catalog_id']??null,'label'=>$label,'description'=>$module['description'],'unit'=>$module['unit_label'],'quantity'=>$count,'choice'=>$qty,'control'=>$module['control'],'unit_value'=>(int)$module['unit_value'],'unit_net_cents'=>$unit,'net_cents'=>$net,'target'=>$module['target'],'value'=>$effect,'included'=>false];if(isset($option))$line['option_label']=$option['label'];return $line;
}
function module_available_offers(array $service): array {
    $parent=module_parent_for_service($service);$rows=product_modules((int)$parent['product_id']);return array_values(array_filter($rows,fn($m)=>!module_is_included($m)));
}
function module_checkout_base(array $configured): array {
    $base=$configured['_base_product']??$configured;$a=checkout_automation($configured);$profile=$configured['_module_base_profile']??$a['settings'];$included=array_values(array_filter($configured['_module_selection']??[],fn($l)=>!empty($l['included']) || !(int)$l['net_cents']));
    foreach($included as &$includedLine)$includedLine['included']=true;unset($includedLine);$profile['module_selection']=$included;if(isset($a['settings']['server_template'])){$profile['server_template']=$a['settings']['server_template'];$minimum=json_decode($profile['server_template']['settings'],true);if((int)$profile['memory_mb']<(int)$minimum['min_memory_mb'] || (int)$profile['cores']<(int)$minimum['min_cores'])throw new RuntimeException('Die gewählte LXC-Vorlage benötigt ihre Mindestressourcen bereits im Grundpaket. Bitte ein passendes Grundpaket wählen.');}$profile['contract_text']=$a['settings']['base_contract_text']??$profile['contract_text']??'';if($included)$profile['contract_text'].="\n\nEnthaltene Module:\n".module_description($included);
    $net=(int)($configured['_module_base_net']??product_options($base)['net_cents']);$base['_automation']=array_replace($a,['settings'=>$profile]);$base['_options']=array_replace(product_options($base),['net_cents'=>$net]);$base['price_cents']=vat_from_net($net)['gross_cents'];$base['_module_selection']=$included;$base['_module_base_net']=$net;$base['_module_key']=hash('sha256',json_encode($included));return $base;
}
function start_module_cart(array $customer,array $configured,string $domain,string $key,array $options): array {
    $lines=array_values(array_filter($configured['_module_selection']??[],fn($l)=>empty($l['included']) && (int)$l['net_cents']>0));
    if(!$lines || isset($configured['_quote_id']) || !billing_months($configured['billing']))return start_checkout_order($customer,$configured,$domain,$key,$options,isset($configured['_purchase_pricing'])?['offer'=>checkout_fingerprint($configured)]:null);
    if(isset($configured['_purchase_pricing']) && (int)$configured['_purchase_pricing']['initial_net']===0 && (int)$configured['_purchase_pricing']['regular_net']===0){$configured['_all_modules_free']=true;return start_checkout_order($customer,$configured,$domain,$key,$options,['offer'=>checkout_fingerprint($configured)]);}
    $base=module_checkout_base($configured);if(isset($configured['_purchase_pricing']))[$base,$lines]=pricing_split_cart($configured,$base,$lines);$config=['module_cart_lines'=>$lines];if((int)$base['price_cents']===0 && (empty($base['_purchase_pricing']) || !(int)$base['_purchase_pricing']['term_net']))$config+=['zero_base_version'=>1,'zero_base_until'=>add_contract_months(shop_now(),billing_months($base['billing']))];
    return start_checkout_order($customer,$base,$domain,$key,$options,['offer'=>checkout_fingerprint($configured),'configuration'=>$config]);
}
function module_register_checkout(array $order,?array $context): void {
    $c=json_decode($order['configuration'],true);
    if(isset($context['intent_id'])){query("UPDATE shop_module_intents SET order_id=?,state='payment_pending' WHERE id=? AND customer_id=? AND (order_id IS NULL OR order_id=?)",[$order['id'],$context['intent_id'],$order['customer_id'],$order['id']]);return;}
    foreach($c['module_cart_lines']??[] as $line){$key=hash('sha256','cart:'.$order['id'].':'.json_encode($line));if(one('SELECT id FROM shop_module_intents WHERE checkout_key=?',[$key]))continue;query("INSERT INTO shop_module_intents (parent_order_id,customer_id,module_id,snapshot,checkout_key,payload,created_ts) VALUES (?,?,?,?,?,'{}',?)",[$order['id'],$order['customer_id'],$line['id'],json_encode($line),$key,shop_now()]);}
}
function module_create_intent(array $service,int $module,mixed $choice,string $key): array {
    if(!preg_match('/^[a-f0-9]{48,64}$/D',$key))throw new RuntimeException('Bitte den Modulkonfigurator neu öffnen.');$parent=module_parent_for_service($service,true);
    $offer=null;foreach(module_available_offers($service) as $m)if((int)$m['id']===$module)$offer=$m;if(!$offer)throw new RuntimeException('Dieses Zusatzmodul wird für dein Paket nicht angeboten.');$line=module_line_for_choice($offer,$choice);
    $profile=module_effective_profile($service);if(in_array($line['target'],['php_mode','php_version','auto_subdomain'],true) || isset(hosting_feature_flags()[$line['target']]) || $line['target']==='php_enabled'){
        if(isset(hosting_feature_flags()[$line['target']]) && !empty($profile[$line['target']]))throw new RuntimeException('Diese Website-Funktion ist bereits in deinem Paket aktiv.');
        foreach(all("SELECT * FROM shop_module_intents WHERE parent_order_id=? AND state<>'ended' AND checkout_key<>?",[$parent['id'],$key]) as $active)if(json_decode($active['snapshot'],true)['target']===$line['target'])throw new RuntimeException('Für diese Website-Option besteht bereits ein aktives Zusatzmodul.');
    }
    $preview=apply_module_profile_value($profile,$line);validate_configured_profile($service['provider'],$preview);if($service['provider']==='ispconfig')normalized_hosting_features($preview,$preview,true);
    if($old=one('SELECT * FROM shop_module_intents WHERE checkout_key=?',[$key])){if((int)$old['parent_order_id']!==(int)$parent['id'] || $old['snapshot']!==json_encode($line))throw new RuntimeException('Dieser Modulvorgang gehört zu einer anderen Auswahl.');return $old;}
    query("INSERT INTO shop_module_intents (parent_order_id,customer_id,module_id,snapshot,checkout_key,payload,created_ts) VALUES (?,?,?,?,?,'{}',?)",[$parent['id'],$parent['customer_id'],$module,json_encode($line),$key,shop_now()]);return one('SELECT * FROM shop_module_intents WHERE id=?',[db()->lastInsertId()]);
}
function module_pricing(array $intent): array {
    $parent=one('SELECT * FROM shop_orders WHERE id=? AND customer_id=?',[$intent['parent_order_id'],$intent['customer_id']]);$c=$parent?ensure_order_contract($parent):null;
    if(!$parent || !$c || (!order_payment_allows_provisioning($parent) && !(zero_base_initial_parent($intent) && zero_base_checkout_allowed($parent))) || contract_closed($c) || (int)$c['cancel_at']>0)throw new RuntimeException('Bitte zuerst die Zahlung des aktiven Grundpakets abschließen.');
    $months=billing_months($parent['billing']);$until=(int)$c['paid_until'];if(!$until && zero_base_initial_parent($intent))$until=(int)(json_decode($parent['configuration'],true)['zero_base_until']??0);if(!$months || $until<=shop_now()+300)throw new RuntimeException('Der neue Abrechnungszeitraum des Grundpakets muss zuerst bezahlt und bestätigt werden.');
    $line=json_decode($intent['snapshot'],true);$ps=$line['purchase_pricing']??null;$parentPricing=pricing_snapshot($parent);$factor=$parentPricing['factor']??1;$periodNet=$ps?(int)$ps['regular_net']:(int)$line['net_cents']*$factor;if(!$ps && $parentPricing)$periodNet-=intdiv($periodNet*(int)$parentPricing['term_discount_bps']+5000,10000);$regular=vat_from_net($periodNet);$start=max((int)$c['started_at'],add_contract_months($until,-$months));$length=max(1,$until-$start);$remaining=min($length,max(0,$until-shop_now()));$net=max(1,min((int)$line['net_cents'],intdiv((int)$line['net_cents']*$remaining+intdiv($length,2),$length)));$first=vat_from_net($net);if($ps){$first=vat_from_net(min((int)$ps['initial_net'],$periodNet>0?intdiv((int)$ps['initial_net']*$remaining+intdiv($length,2),$length):(int)$ps['initial_net']));}elseif($factor!==1){$net=max(1,min($periodNet,intdiv($periodNet*$remaining+intdiv($length,2),$length)));$first=vat_from_net($net);}
    return ['parent'=>$parent,'line'=>$line,'regular'=>$regular,'initial'=>$first,'initial_until'=>$until];
}
function module_order_view(array $order,?string $kind=null): array {
    $intent=module_order_intent((int)$order['id']);if(!$intent)return pricing_order_view($order,$kind);$regular=$kind==='renewal' || ($kind===null && $intent['first_settled']);$net=(int)$intent[$regular?'recurring_net_cents':'initial_net_cents'];$tax=vat_from_net($net);$c=json_decode($order['configuration'],true);$c['tax']=$tax;$line=json_decode($intent['snapshot'],true);$line['net_cents']=$net;
    if(!$regular)$line['label'].=' · anteilig bis '.date('d.m.Y',(int)$intent['initial_until']);$c['modules']=[$line];$c['base_net_cents']=0;if(isset($c['purchase_pricing']))$c['pricing_period']=$regular?'renewal':'initial';$order['configuration']=json_encode($c);$order['amount_cents']=$tax['gross_cents'];return $order;
}
function module_note_payment(array $order,?array $receivable): void {
    if($receivable && $receivable['kind']==='initial')query('UPDATE shop_module_intents SET first_settled=1 WHERE order_id=?',[$order['id']]);
}
function module_checkout_product(array $intent): array {
    $parent=zero_base_initial_parent($intent);if(!$parent || !zero_base_checkout_allowed($parent)){ $service=module_parent_service($intent,true);$parent=module_parent_for_service($service,true); }$p=one('SELECT * FROM shop_products WHERE id=?',[$parent['product_id']]);if(!$p)throw new RuntimeException('Das ursprüngliche Paket ist nicht verfügbar.');
    if($intent['order_id'] || ((int)$intent['initial_until']>shop_now()+300 && (int)$intent['initial_net_cents']>0)){$pricing=['parent'=>$parent,'line'=>json_decode($intent['snapshot'],true),'initial'=>vat_from_net((int)$intent['initial_net_cents']),'regular'=>vat_from_net((int)$intent['recurring_net_cents']),'initial_until'=>(int)$intent['initial_until']];}
    else{$pricing=module_pricing($intent);query('UPDATE shop_module_intents SET initial_net_cents=?,initial_gross_cents=?,recurring_net_cents=?,recurring_gross_cents=?,initial_until=? WHERE id=? AND order_id IS NULL',[$pricing['initial']['net_cents'],$pricing['initial']['gross_cents'],$pricing['regular']['net_cents'],$pricing['regular']['gross_cents'],$pricing['initial_until'],$intent['id']]);}
    $p['billing']=$parent['billing'];$p['name']=mb_substr($p['name'].' · '.$pricing['line']['label'],0,120);$p['active']=1;$p['price_cents']=$pricing['initial']['gross_cents'];$p['_options']=array_replace(product_options($p),['sale_mode'=>'direct','net_cents'=>$pricing['initial']['net_cents']]);
    $profile=json_decode($parent['profile'],true);$profile['module_addon']=['intent_id'=>(int)$intent['id'],'parent_order_id'=>(int)$parent['id']];$profile['contract_text']='Zusatzmodul: '.module_description([$pricing['line']])."\nAnteilig bis ".date('d.m.Y H:i',$pricing['initial_until']).'. Danach eigenes PayPal-Abonnement im Abrechnungszeitraum des Grundpakets. Kündigung zum Ende des bezahlten Zeitraums.';
    $p['_automation']=['provider'=>$parent['provider'],'enabled'=>1,'settings'=>$profile];$p['_contract_policy']=['minimum_months'=>0,'notice_days'=>0,'billing_months'=>billing_months($p['billing'])];$p['_module_selection']=[$pricing['line']];$p['_module_selection'][0]['net_cents']=$pricing['initial']['net_cents'];$p['_module_base_net']=0;$p['_module_key']=hash('sha256',$intent['snapshot']);$p['_module_pricing']=$pricing;$p['_module_intent']=$intent;if(isset($pricing['line']['purchase_pricing'])){$s=$pricing['line']['purchase_pricing'];$s['redemption_parent']=(int)$parent['id'];$s['initial_net']=$pricing['initial']['net_cents'];$s['regular_net']=$pricing['regular']['net_cents'];$s['discount_net']=max(0,(int)$s['term_net']-$s['initial_net']);$p['_purchase_pricing']=$s;}elseif(($s=pricing_snapshot($parent)) && (int)$s['factor']>1){$p['_purchase_pricing']=array_replace($s,['coupon'=>null,'reward_id'=>0,'test_only'=>false,'original_net'=>(int)$pricing['line']['net_cents']*$s['factor'],'term_net'=>$pricing['regular']['net_cents'],'initial_net'=>$pricing['initial']['net_cents'],'regular_net'=>$pricing['regular']['net_cents'],'discount_net'=>0]);}return $p;
}
function module_component_offer(array $p): string {return hash('sha256',json_encode([$p['_module_intent']['id'],$p['_module_intent']['snapshot'],$p['_module_pricing']['initial'],$p['_module_pricing']['initial_until'],integration_identity('paypal'),checkout_documents_for($p),$p['_purchase_pricing']??null]));}
function start_module_checkout(array $customer,int $intentId,string $key,array $options): array {
    $intent=one('SELECT * FROM shop_module_intents WHERE id=? AND customer_id=?',[$intentId,$customer['id']]);if(!$intent || !in_array($intent['state'],['selected','payment_pending'],true))throw new RuntimeException('Dieses Zusatzmodul kann nicht erneut bestellt werden.');
    $options['checkout_key']=$key;$p=module_purchase_pricing(module_checkout_product($intent),(int)$customer['id'],$options);if((int)$p['_module_pricing']['initial_until']<=shop_now()+300)throw new RuntimeException('Der anteilige Zeitraum ist abgelaufen. Bitte die Modulauswahl neu öffnen.');return start_checkout_order($customer,$p,(string)$p['_module_pricing']['parent']['domain'],$key,$options,['offer'=>module_component_offer($p),'intent_id'=>$intentId,'order_id'=>$intent['order_id'],'configuration'=>['module_addon'=>['intent_id'=>$intentId,'parent_order_id'=>$intent['parent_order_id']]]]);
}
function module_paypal_plan(array $order,PaypalProvider $paypal,array $intent): string {
    $line=json_decode($intent['snapshot'],true);$key=hash('sha256','module-plan:'.integration_identity('paypal').':'.$order['reference']);if($p=one('SELECT plan_id FROM shop_paypal_plans WHERE fingerprint=?',[$key]))return $p['plan_id'];
    $product=$paypal->call('POST','/v1/catalogs/products',['name'=>$order['product_name'],'type'=>'SERVICE','category'=>'SOFTWARE'],'module-product-'.$order['reference']);if(empty($product['id']))throw new ProviderFailure('paypal');
    $plan=$paypal->call('POST','/v1/billing/plans',['product_id'=>$product['id'],'name'=>mb_substr($line['label'].' · '.cycle($order['billing']),0,127),'status'=>'ACTIVE','billing_cycles'=>[['frequency'=>['interval_unit'=>'MONTH','interval_count'=>billing_months($order['billing'])],'tenure_type'=>'REGULAR','sequence'=>1,'total_cycles'=>0,'pricing_scheme'=>['fixed_price'=>['value'=>number_format((int)$intent['recurring_gross_cents']/100,2,'.',''),'currency_code'=>'EUR']]]],'payment_preferences'=>['auto_bill_outstanding'=>false,'payment_failure_threshold'=>1,'setup_fee'=>['value'=>number_format((int)$intent['initial_gross_cents']/100,2,'.',''),'currency_code'=>'EUR'],'setup_fee_failure_action'=>'CANCEL']],'module-plan-'.$order['reference']);if(empty($plan['id']))throw new ProviderFailure('paypal');query('INSERT INTO shop_paypal_plans (fingerprint,product_id,paypal_mode,plan_id) VALUES (?,?,?,?)',[$key,$order['product_id'],$paypal->mode(),$plan['id']]);return $plan['id'];
}
function module_active_intents(int $parent,int $exclude=0): array {return all("SELECT * FROM shop_module_intents WHERE parent_order_id=? AND applied_at>0 AND state IN ('active','cancel_requested') AND id<>? ORDER BY id",[$parent,$exclude]);}
function module_baseline_profile(array $service): array {
    $saved=one('SELECT * FROM shop_service_module_profiles WHERE service_id=?',[$service['id']]);if($saved)return json_decode($saved['base_profile'],true,32,JSON_THROW_ON_ERROR);
    $parent=module_parent_for_service($service);$profile=json_decode($parent['profile'],true,32,JSON_THROW_ON_ERROR);
    // Historical bundles remain part of their original contract. Current catalog edits never reduce them.
    if($service['provider']==='ispconfig')$profile=array_replace($profile,$service['meta']['features']??[],['storage_mb'=>$service['meta']['storage_mb']??$profile['storage_mb'],'ftp_limit'=>$service['meta']['limits']['ftp'],'database_limit'=>$service['meta']['limits']['database'],'mail_limit'=>$service['meta']['limits']['mail'],'mail_quota_mb'=>$service['meta']['limits']['mail_quota_mb'],'cron_limit'=>$service['meta']['limits']['cron']??0]);
    return $profile;
}
function module_effective_profile(array $service,int $exclude=0,?array $extra=null): array {
    $p=module_baseline_profile($service);$parent=module_parent_for_service($service);$lines=$p['module_selection']??[];
    foreach(module_active_intents((int)$parent['id'],$exclude) as $i){$line=json_decode($i['snapshot'],true);$p=apply_module_profile_value($p,$line);$lines[]=$line;}
    if($extra){$p=apply_module_profile_value($p,$extra);$lines[]=$extra;}$p['module_selection']=$lines;validate_configured_profile($service['provider'],$p);if($service['provider']==='ispconfig')$p=array_replace($p,normalized_hosting_features($p,$p,true));return $p;
}
function module_store_baseline(array $service): void {
    if(one('SELECT service_id FROM shop_service_module_profiles WHERE service_id=?',[$service['id']]))return;
    $parent=module_parent_for_service($service);query('INSERT INTO shop_service_module_profiles (service_id,parent_order_id,base_profile) VALUES (?,?,?)',[$service['id'],$parent['id'],json_encode(module_baseline_profile($service))]);
}
function module_remote_rows(IspconfigProvider $api,string $method,array $filter,string $id): array {
    $rows=$api->call($method,$filter);if($rows===false || !is_array($rows))throw new RuntimeException('Die Ressourcennutzung konnte bei ISPConfig nicht vollständig bestätigt werden.');if(isset($rows[$id]))$rows=[$rows];return array_values($rows);
}
function module_check_reduction(array $service,array $target): void {
    $old=module_effective_profile($service);
    if($service['provider']==='proxmox'){if((int)$target['memory_mb']<(int)$old['memory_mb']){$api=new ProxmoxProvider();$type=proxmox_service_type($service);if(!$api->poolContains($service['meta']['node'],(int)$service['meta']['vmid'],$type))throw new RuntimeException('Die Serverzuordnung muss zuerst geprüft werden.');$vm=$api->vm($service['meta']['node'],(int)$service['meta']['vmid'],$type);if(($vm['status']??'')==='running' && (!isset($vm['mem']) || (float)$vm['mem']>(int)$target['memory_mb']*1048576))throw new RuntimeException('Der Server nutzt mehr RAM als künftig enthalten. Bitte zuerst Speicher freigeben oder den Server herunterfahren.');}return;} // Memory/CPU changes are applied without deleting guest data; disks require a migration.
    $api=new IspconfigProvider();$site=hosting_site($service,$api);$client=(int)$service['meta']['client_id'];$group=(int)$site['sys_groupid'];$domain=$service['meta']['domain'];
    foreach(['ftp_limit'=>['sites_ftp_user_get','primary_id','ftp_user_id'],'database_limit'=>['sites_database_get','primary_id','database_id'],'cron_limit'=>['sites_cron_get','cron_id','id']] as $key=>[$method,$arg,$id]){
        if((int)$target[$key]>=(int)($old[$key]??0))continue;$rows=module_remote_rows($api,$method,[$arg=>['parent_domain_id'=>(int)$site['domain_id']]],$id);
        foreach($rows as $row)if((int)($row['parent_domain_id']??0)!==(int)$site['domain_id'])throw new RuntimeException('Die Zuordnung einer Ressource konnte nicht bestätigt werden.');
        if(count($rows)>(int)$target[$key])throw new RuntimeException('Vor der Verkleinerung bitte '.(count($rows)-(int)$target[$key]).' '.(product_module_targets('hosting')[$key]??$key).' entfernen. Deine Daten bleiben erhalten.');
    }
    if((int)$target['storage_mb']<(int)$old['storage_mb']){
        $rows=module_remote_rows($api,'quota_get_by_user',['client_id'=>$client],'domain_id');$found=false;
        foreach($rows as $row)if((int)($row['domain_id']??0)===(int)$site['domain_id'] && ($row['system_user']??'')===($site['system_user']??'')){$found=isset($row['used_raw']) && is_numeric($row['used_raw']);if($found && (float)$row['used_raw']>(int)$target['storage_mb']*1048576)throw new RuntimeException('Der belegte Webspace passt noch nicht in das verkleinerte Paket. Bitte zuerst Speicher freigeben.');}
        if(!$found)throw new RuntimeException('Die belegte Webspace-Größe konnte noch nicht sicher ermittelt werden. Bitte den Support um eine Quota-Prüfung bitten.');
    }
    if((int)$target['mail_limit']<(int)$old['mail_limit'] || (int)$target['mail_quota_mb']<(int)$old['mail_quota_mb']){
        $mail=module_remote_rows($api,'mail_user_get',['primary_id'=>['sys_groupid'=>$group,'email'=>'%@'.$domain]],'mailuser_id');
        foreach($mail as $row)if((int)($row['sys_groupid']??0)!==$group || !str_ends_with($row['email']??'','@'.$domain))throw new RuntimeException('Die Postfachzuordnung muss zuerst geprüft werden.');
        if(count($mail)>(int)$target['mail_limit'])throw new RuntimeException('Es sind mehr Postfächer vorhanden als nach der Kündigung enthalten wären. Bitte zuerst überzählige Postfächer entfernen.');
        if((int)$target['mail_quota_mb']<(int)$old['mail_quota_mb'] && $mail){$quotas=module_remote_rows($api,'mailquota_get_by_user',['client_id'=>$client],'mailuser_id');$by=[];foreach($quotas as $q)if(isset($q['email'],$q['used_raw']) && is_numeric($q['used_raw']))$by[$q['email']]=$q['used_raw'];foreach($mail as $m){if(!isset($by[$m['email']]))throw new RuntimeException('Die belegte Postfachgröße konnte nicht vollständig bestätigt werden. Bitte den Support kontaktieren.');if((float)$by[$m['email']]>(int)$target['mail_quota_mb']*1048576)throw new RuntimeException('Das Postfach '.$m['email'].' belegt mehr Speicher als künftig enthalten. Bitte zuerst Speicher freigeben.');}}
    }
}
function module_apply_hosting(array $service,array $target): void {
    resource_mutex(-(int)$service['customer_id'],function()use($service,$target){
        $api=new IspconfigProvider();$site=hosting_site($service,$api);$client=hosting_panel_client($service,$api);$id=(int)$client['client_id'];$old=module_effective_profile($service);
        module_check_reduction($service,$target);
        $limits=hosting_account_limits((int)$service['customer_id'],$id,$api,null,[(int)$service['id']=>$target]);$limits['password']='';
        if(!$api->call('client_update',['client_id'=>$id,'reseller_id'=>(int)($client['parent_client_id']??0),'params'=>$limits]))throw new RuntimeException('ISPConfig hat die neuen Kundenlimits nicht bestätigt.');
        $params=['hd_quota'=>(int)$target['storage_mb']]+hosting_website_feature_params($target,true);
        if(!$api->call('sites_web_domain_update',['client_id'=>$id,'primary_id'=>(int)$site['domain_id'],'params'=>$params]))throw new RuntimeException('ISPConfig hat die Website-Ausstattung noch nicht bestätigt.');
        $mail=$api->mailDomain($service['meta']['domain']);if($mail && (int)($mail['sys_groupid']??0)!==(int)$site['sys_groupid'])throw new RuntimeException('Die Maildomain gehört zu einem anderen Kunden.');if(!$mail)$api->call('mail_domain_add',['client_id'=>$id,'params'=>['server_id'=>(int)integration_config('ispconfig')['mail_server_id'],'domain'=>$service['meta']['domain'],'active'=>'y']]);ensure_hosting_dkim($api,(int)$service['customer_id'],$id,(int)$site['sys_groupid'],$service['meta']['domain']);
        if((int)$target['mail_quota_mb']!==(int)$old['mail_quota_mb']){
            foreach(module_remote_rows($api,'mail_user_get',['primary_id'=>['sys_groupid'=>(int)$site['sys_groupid'],'email'=>'%@'.$service['meta']['domain']]],'mailuser_id') as $m){
                if((int)($m['sys_groupid']??0)!==(int)$site['sys_groupid'] || !str_ends_with($m['email']??'','@'.$service['meta']['domain']))throw new RuntimeException('Die Postfachzuordnung wurde geändert.');$limit=(int)$target['mail_quota_mb']*1048576;$oldLimit=(int)$old['mail_quota_mb']*1048576;$current=(int)($m['quota']??0);$desired=$limit<$oldLimit?min($current>0?$current:$limit,$limit):($current===$oldLimit?$limit:$current);
                if($desired!==$current){$m['quota']=$desired;$m['password']='';if(!$api->call('mail_user_update',['client_id'=>$id,'primary_id'=>(int)$m['mailuser_id'],'params'=>$m]))throw new RuntimeException('Eine Postfachquota muss noch geprüft werden.');}
            }
        }
    });
}
function module_save_entitlements(array $service,array $target): void {
    $meta=json_decode(one('SELECT metadata FROM shop_resource_links WHERE service_id=?',[$service['id']])['metadata'],true);$meta['modules']=$target['module_selection']??[];
    if($service['provider']==='ispconfig'){$meta['storage_mb']=(int)$target['storage_mb'];$meta['features']=normalized_hosting_features($target,$target);foreach(['ftp'=>'ftp_limit','database'=>'database_limit','mail'=>'mail_limit','mail_quota_mb'=>'mail_quota_mb','cron'=>'cron_limit'] as $k=>$v)$meta['limits'][$k]=(int)$target[$v];}
    else $meta['purchased_profile']=$target;
    $parent=module_parent_for_service($service);$gross=(int)pricing_order_view($parent,'renewal')['amount_cents'];foreach(module_active_intents((int)$parent['id']) as $i)if(!contract_closed(one('SELECT state FROM shop_contracts WHERE order_id=?',[$i['order_id']])))$gross+=(int)$i['recurring_gross_cents'];
    query('UPDATE shop_resource_links SET metadata=?,verified_at=CURRENT_TIMESTAMP WHERE service_id=?',[json_encode($meta),$service['id']]);query('UPDATE shop_services SET price_cents=? WHERE id=?',[$gross,$service['id']]);query('UPDATE shop_service_module_profiles SET revision=revision+1 WHERE service_id=?',[$service['id']]);
}
function provision_module_addon(array $order,array &$job,array $intent): void {
    if(($parent=zero_base_initial_parent($intent)) && !service_for_order((int)$parent['id'],(int)$parent['customer_id'])){save_job_phase($job,'start',[]);query("UPDATE shop_orders SET fulfillment_status='queued' WHERE id=?",[$order['id']]);return;}
    $service=module_parent_service($intent,true);resource_mutex((int)$service['id'],function()use($order,&$job,$intent,$service){
        $service=bound_service((int)$service['id'],(int)$service['customer_id'],true);module_parent_for_service($service,true,true);module_store_baseline($service);$fresh=one('SELECT * FROM shop_module_intents WHERE id=?',[$intent['id']]);
        if((!(int)$fresh['first_settled'] && !admin_order_released($order)) || !order_payment_allows_provisioning($order))throw new RuntimeException('Die separate Modulzahlung wurde noch nicht bestätigt.');
        $target=module_effective_profile($service,0,$fresh['applied_at']?null:json_decode($fresh['snapshot'],true));
        if(!$fresh['applied_at']){if($service['provider']==='ispconfig')module_apply_hosting($service,$target);else{if(!module_apply_proxmox($service,$target)){save_job_phase($job,'start',[]);return;}}}
        db()->beginTransaction();try{query("UPDATE shop_module_intents SET state='active',applied_at=CASE WHEN applied_at=0 THEN ? ELSE applied_at END,last_error=NULL WHERE id=?",[shop_now(),$intent['id']]);module_save_entitlements($service,$target);query("UPDATE shop_jobs SET state='done',last_error=NULL WHERE id=? AND lock_token=?",[$job['id'],$job['lock_token']]);query("UPDATE shop_orders SET fulfillment_status='active' WHERE id=?",[$order['id']]);admin_order_note_active($order);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
        $line=json_decode($intent['snapshot'],true);customer_notification((int)$order['customer_id'],'module-active:'.$intent['id'],'Zusatzmodul aktiviert',$line['label'],'/konto/verwalten?id='.$service['id']);$c=one('SELECT email FROM customers WHERE id=?',[$order['customer_id']]);queue_shop_mail('module-active:'.$intent['id'],$c['email'],'Zusatzmodul aktiviert',$line['label'].' ist jetzt für '.$service['name'].' freigeschaltet. Verwaltung: '.Config::FRONTPANEL_SITE_URL.'/konto/verwalten?id='.$service['id']);audit_event('module.activated',$order['reference']);
    });
}
function module_cancel_guard(array $contract,bool $cascade): void {
    $intent=module_order_intent((int)$contract['order_id']);if(!$intent || !$intent['applied_at'] || $cascade)return;$service=module_parent_service($intent);$target=module_effective_profile($service,(int)$intent['id']);
    // Also exclude already-cancelled components, so two individually possible reductions cannot overbook the remaining limits.
    foreach(module_active_intents((int)$intent['parent_order_id'],(int)$intent['id']) as $other){$c=ensure_order_contract(one('SELECT * FROM shop_orders WHERE id=?',[$other['order_id']]));if($c && (int)$c['cancel_at']>0)$target=module_profile_without_cancelled($service,(int)$intent['id']);}
    module_check_reduction($service,$target);
}
function module_profile_without_cancelled(array $service,int $exclude=0): array {
    $p=module_baseline_profile($service);$parent=module_parent_for_service($service);foreach(module_active_intents((int)$parent['id'],$exclude) as $i){$c=one('SELECT cancel_at FROM shop_contracts WHERE order_id=?',[$i['order_id']]);if(!$c || !(int)$c['cancel_at'])$p=apply_module_profile_value($p,json_decode($i['snapshot'],true));}return $p;
}
function end_module_addon(array $intent): bool {
    if(!$intent['applied_at']){query("UPDATE shop_module_intents SET state='ended' WHERE id=?",[$intent['id']]);return true;}
    $service=module_parent_service($intent);$parent=module_parent_for_service($service);$pc=ensure_order_contract($parent);
    return resource_mutex((int)$service['id'],function()use($intent,$service,$pc){
        if(!contract_closed($pc)){module_store_baseline($service);$target=module_effective_profile($service,(int)$intent['id']);module_check_reduction($service,$target);if($service['provider']==='ispconfig')module_apply_hosting($service,$target);else module_apply_proxmox($service,$target);}
        query("UPDATE shop_module_intents SET state='ended',last_error=NULL WHERE id=?",[$intent['id']]);if(!contract_closed($pc))module_save_entitlements($service,$target);return true;
    });
}
function module_cancel_children(array $contract,int $received,int $effective): void {
    foreach(all("SELECT * FROM shop_module_intents WHERE parent_order_id=? AND state<>'ended'",[$contract['order_id']]) as $i){if($i['order_id']){$child=contract_for_order((int)$i['order_id']);if($child && !contract_closed($child))schedule_contract_cancellation($child,$received,max($effective,(int)$child['paid_until']),true);}else query("UPDATE shop_module_intents SET state='ended' WHERE id=?",[$i['id']]);}
}

function module_future_resource_limits(array $service): array {
    if($service['provider']!=='ispconfig' || !one('SELECT service_id FROM shop_service_module_profiles WHERE service_id=?',[$service['id']]))return $service;$target=module_profile_without_cancelled($service);foreach(['ftp'=>'ftp_limit','mail'=>'mail_limit','database'=>'database_limit','cron'=>'cron_limit','mail_quota_mb'=>'mail_quota_mb'] as $key=>$field)$service['meta']['limits'][$key]=min((int)($service['meta']['limits'][$key]??0),(int)($target[$field]??0));return $service;
}

function module_note_contract_end(array $order): void {
    $i=module_order_intent((int)$order['id']);if(!$i)return;try{$s=module_parent_service($i);$p=module_parent_for_service($s);$gross=(int)$p['amount_cents'];foreach(module_active_intents((int)$p['id']) as $row)if(!contract_closed(one('SELECT state FROM shop_contracts WHERE order_id=?',[$row['order_id']])))$gross+=(int)$row['recurring_gross_cents'];query('UPDATE shop_services SET price_cents=? WHERE id=?',[$gross,$s['id']]);}catch(RuntimeException $e){/* The contract still ends if its old resource link needs operator recovery. */}
}
