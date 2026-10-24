<?php
/** A zero maximum means no configured quantity limit; stored quantities use SQL INT. */
function module_quantity(mixed $value,bool $unlimited=false): int {
    if(!is_string($value) && !is_int($value))throw new RuntimeException('Bitte eine gültige ganze Menge eintragen.');
    $value=trim((string)$value);
    if($unlimited && $value==='')return 0;
    if(!preg_match('/^\d{1,10}$/D',$value) || (int)$value>2147483647)throw new RuntimeException('Bitte eine gültige ganze Menge eintragen.');
    return (int)$value;
}
function validate_module_quantity_range(array $numbers): void {
    $min=$numbers['min_qty'];$max=$numbers['max_qty'];$default=$numbers['default_qty'];
    if($max>0 && $max<$min)throw new RuntimeException('Die Höchstmenge ('.$max.') liegt unter der Mindestmenge ('.$min.'). Erhöhe die Höchstmenge oder lasse sie für unbegrenzt leer.');
    if($default<$min)throw new RuntimeException('Die vorausgewählte Menge ('.$default.') liegt unter der Mindestmenge ('.$min.'). Trage als Vorauswahl mindestens '.$min.' ein. Soll das Modul optional sein, setze die Mindestmenge auf 0.');
    if($max>0 && $default>$max)throw new RuntimeException('Die vorausgewählte Menge ('.$default.') überschreitet die Höchstmenge ('.$max.'). Verringere die Vorauswahl oder erhöhe die Höchstmenge.');
}
/** Storage steps are entered in the displayed unit; persistence stays in provider units. */
function module_is_storage_target(string $target): bool {return in_array($target,['storage_mb','mail_quota_mb','memory_mb','disk_extra_gb'],true);}
function module_storage_unit(string $target,string $unit): string {
    if(!module_is_storage_target($target))return trim($unit);
    $key=strtoupper(preg_replace('/\s+/u','',trim($unit)));
    if(in_array($key,['','EINHEIT','EINHEITEN'],true))return $target==='disk_extra_gb'?'GB':'MB';
    $aliases=['MB'=>'MB','MIB'=>'MB','MBYTE'=>'MB','MEGABYTE'=>'MB','MEGABYTES'=>'MB','GB'=>'GB','GIB'=>'GB','GBYTE'=>'GB','GIGABYTE'=>'GB','GIGABYTES'=>'GB','TB'=>'TB','TIB'=>'TB','TBYTE'=>'TB','TERABYTE'=>'TB','TERABYTES'=>'TB'];
    if(isset($aliases[$key]))return $aliases[$key];
    throw new RuntimeException('Bitte für Speicher eine eindeutige Einheit auswählen: MB, GB oder TB. 1 GB entspricht 1024 MB.');
}
function module_storage_factor(string $target,string $unit): float {
    if(!module_is_storage_target($target))return 1;
    // Old free-text labels remain readable in their original native units.
    try{$unit=module_storage_unit($target,$unit);}catch(RuntimeException $e){return 1;}$units=['MB'=>1,'GB'=>1024,'TB'=>1048576];
    return $units[$unit]/($target==='disk_extra_gb'?1024:1);
}
function module_stored_step(string $target,string $unit,int $step): int {
    $unit=module_storage_unit($target,$unit);
    $value=$step*module_storage_factor($target,$unit);if($value<1 || $value>2147483647 || floor($value)!=$value)throw new RuntimeException('Der Speicherzuwachs muss einer ganzen technischen Einheit entsprechen (mindestens 1 MB, bei VPS-Platten 1 GB).');return (int)$value;
}
function module_display_step(array $module): float {return (int)$module['unit_value']/module_storage_factor($module['target'],$module['unit_label']);}
function module_storage_increment_text(array $module): string {
    $mb=(int)$module['unit_value']*($module['target']==='disk_extra_gb'?1024:1);
    $text=number_format($mb,0,',','.').' MB';if($mb>=1024)$text=number_format($mb/1024,$mb%1024?3:0,',','.').' GB ('.$text.')';return $text;
}
function module_unit_field(string $target,string $unit,string $label='Einheit'): void {
    $storage=module_is_storage_target($target);$legacy=false;try{$selected=module_storage_unit($target,$unit);}catch(RuntimeException $e){$selected=$target==='disk_extra_gb'?'GB':'MB';$legacy=true;}
    if(!$storage)$selected='GB'; ?>
    <label class="field" data-module-unit-field><span><?= e($label) ?></span><select data-storage-unit <?= $storage?'name="unit_label" required':'hidden disabled' ?>><?php foreach(['MB'=>'MB','GB'=>'GB · 1024 MB','TB'=>'TB · 1024 GB'] as $key=>$caption): ?><option value="<?= $key ?>" <?= $selected===$key?'selected':'' ?>><?= $caption ?></option><?php endforeach ?></select><input data-count-unit <?= $storage?'hidden disabled':'name="unit_label"' ?> maxlength="60" value="<?= e($unit) ?>" placeholder="Postfach, Zugang …"><?php if($legacy): ?><small>Die bisherige Beschriftung „<?= e($unit) ?>“ wird in der bisherigen technischen Größe geladen. Prüfe die Speichereinheit vor dem Speichern.</small><?php endif ?></label>
    <?php
}
function module_quantity_help(): array {
    return [
        'storage_mb'=>'Der Zuwachs gilt in der eingetragenen Einheit: GB und Zuwachs 1 ergeben 1024 MB zusätzlichen Webspace.',
        'mail_limit'=>'Anzahl zusätzlicher Postfächer pro Einheit: 1 bedeutet ein weiteres Postfach. Für Speicher verwende die technische Wirkung „Speicher je Postfach (MB)“.',
        'mail_quota_mb'=>'Der Zuwachs gilt je Postfach in der eingetragenen Einheit. GB und Zuwachs 1 ergeben 1024 MB. Die Postfachanzahl wird separat eingestellt.',
        'ftp_limit'=>'Anzahl zusätzlicher FTP-Zugänge pro Einheit: 1 bedeutet ein weiterer Zugang.',
        'database_limit'=>'Anzahl zusätzlicher Datenbanken pro Einheit: 1 bedeutet eine weitere Datenbank.',
        'cron_limit'=>'Anzahl zusätzlicher Web-Cronjobs pro Einheit: 1 bedeutet ein weiterer Cronjob.',
        'cores'=>'Zusätzliche vCPU pro Einheit: 1 bedeutet eine weitere vCPU.',
        'memory_mb'=>'Der Zuwachs gilt in der eingetragenen Einheit: GB und Zuwachs 1 ergeben 1024 MB RAM.',
        'disk_extra_gb'=>'Wie beim RAM gilt die gewählte Einheit: GB und Zuwachs 1 ergeben 1024 MB zusätzlichen Festplattenspeicher. Bei MB muss der Zuwachs für VPS-Platten ein Vielfaches von 1024 sein.',
        'description_only'=>'Diese Option ergänzt die Leistungsbeschreibung. Sie ändert keine technische Ressource.',
    ];
}
function product_module_targets(string $category): array {
    return match($category){
        'hosting'=>['storage_mb'=>'Webspace (MB)','cron_limit'=>'Web-Cronjobs','ftp_limit'=>'FTP-Zugänge','mail_limit'=>'E-Mail-Postfächer','mail_quota_mb'=>'Speicher je Postfach (MB)','database_limit'=>'Datenbanken','php_enabled'=>'PHP aktivieren','cgi_enabled'=>'CGI aktivieren','ssi_enabled'=>'SSI','perl_enabled'=>'Perl','ruby_enabled'=>'Ruby','python_enabled'=>'Python','suexec_enabled'=>'SuEXEC','errordocs_enabled'=>'Eigene Fehlerseiten','auto_subdomain'=>'Auto-Subdomain','ssl_enabled'=>'SSL','letsencrypt_enabled'=>"Let's Encrypt SSL",'php_mode'=>'PHP-Betriebsart','php_version'=>'PHP-Version','description_only'=>'Zusätzliche Leistungsbeschreibung'],
        'server'=>['cores'=>'Zusätzliche vCPU','memory_mb'=>'Zusätzlicher RAM (MB)','disk_extra_gb'=>'Zusätzliche Systemplatte (GB)','description_only'=>'Zusätzliche Leistungsbeschreibung'],default=>[]};
}
function module_is_free(array $module): bool {
    if($module['control']==='select'){$options=$module['options']??catalog_module_options($module);return (bool)$options && !array_filter($options,fn($option)=>(int)$option['net_cents']!==0);}
    return (int)$module['net_cents']===0;
}
function module_is_included(array $module): bool {return ($module['_mode']??'optional')==='included' || module_is_free($module);}
function module_included_quantity(array $module,array $profile=[]): int {
    $qty=(int)($module['_included_qty']??$module['default_qty']);
    if(!module_is_free($module))return $qty;
    if($module['control']==='select'){$options=$module['options']??catalog_module_options($module);if(($module['_mode']??'optional')!=='included'){$qty=(int)$module['default_qty'];if(!isset($options[$qty]) && array_key_exists($module['target'],$profile))foreach($options as $optionId=>$option)if((string)$option['value']===(string)$profile[$module['target']]){$qty=(int)$optionId;break;}}return isset($options[$qty])?$qty:(isset($options[(int)$module['default_qty']])?(int)$module['default_qty']:(int)array_key_first($options));}
    return $module['control']==='checkbox'?1:max(1,$qty,(int)$module['min_qty']);
}
function product_modules(int $product,bool $active=true): array {
    $rows=all('SELECT * FROM shop_product_modules WHERE product_id=? ORDER BY sort_order,id',[$product]);
    foreach($rows as &$row){$binding=one('SELECT * FROM shop_product_module_bindings WHERE module_id=?',[$row['id']]);if(!$binding)continue;
        $m=one('SELECT * FROM shop_module_catalog WHERE id=?',[$binding['catalog_id']]);if(!$m){$row['active']=0;continue;}
        $on=(bool)$row['active'] && (bool)$m['active'];foreach(['label','description','unit_label','target','unit_value','net_cents','min_qty','max_qty','default_qty','control','sort_order'] as $key)$row[$key]=$m[$key];$row['active']=$on?1:0;
        $row['_catalog_id']=(int)$m['id'];$row['_mode']=$binding['mode'];$row['_included_qty']=(int)$binding['included_qty'];$row['options']=catalog_module_options($m);
    }unset($row);return array_values(array_filter($rows,fn($m)=>!$active || $m['active']));
}
function module_catalog_hash(array $p): string {return isset($p['_quote_id'])?'':hash('sha256',json_encode(product_modules((int)$p['id'])));}
function save_product_module(int $id,int $product,array $input): int {
    $p=one('SELECT * FROM shop_products WHERE id=?',[$product]);if(!$p || !in_array($p['category'],['hosting','server'],true))throw new RuntimeException('Module sind für Webhosting und VPS vorgesehen.');
    if($id && one('SELECT module_id FROM shop_product_module_bindings WHERE module_id=?',[$id]))throw new RuntimeException('Dieses Modul wird zentral verwaltet. Bitte unter Module & Konfigurator bearbeiten.');
    if($id && !one('SELECT id FROM shop_product_modules WHERE id=? AND product_id=?',[$id,$product]))throw new RuntimeException('Modul nicht gefunden.');
    if(!$id && count(product_modules($product,false))>=20)throw new RuntimeException('Pro Paket sind höchstens 20 Module vorgesehen.');
    $label=trim((string)($input['label']??''));$description=trim((string)($input['description']??''));$unit=trim((string)($input['unit_label']??''));$target=(string)($input['target']??'');$control=(string)($input['control']??'number');$net=parse_net_price((string)($input['unit_net']??''),false);
    if(!$label || mb_strlen($label)>120 || strlen($description)>500 || mb_strlen($unit)>60 || !isset(product_module_targets($p['category'])[$target]) || !in_array($control,['number','checkbox'],true) || $net===null || $net<0 || $net>10000000)throw new RuntimeException('Bitte Bezeichnung, Preis, Einheit und Modulwirkung prüfen.');
    $numbers=[];foreach(['unit_value'=>[1,1000000],'sort_order'=>[0,9999]] as $k=>[$min,$max]){$v=module_quantity($input[$k]??($k==='unit_value'?1:0));if($v<$min || $v>$max)throw new RuntimeException('Bitte gültige Mengen und Schrittgrößen angeben.');$numbers[$k]=$v;}
    foreach(['min_qty','max_qty','default_qty'] as $key)$numbers[$key]=module_quantity($input[$key]??0,$key==='max_qty');
    if($control==='checkbox'){$numbers['min_qty']=0;$numbers['max_qty']=1;$numbers['default_qty']=min(1,$numbers['default_qty']);}
    validate_module_quantity_range($numbers);
    $unit=module_storage_unit($target,$unit);$numbers['unit_value']=module_stored_step($target,$unit,$numbers['unit_value']);
    $args=[$label,$description,$unit,$target,$numbers['unit_value'],$net,$numbers['min_qty'],$numbers['max_qty'],$numbers['default_qty'],$control,isset($input['active'])?1:0,$numbers['sort_order']];
    if($id)query('UPDATE shop_product_modules SET label=?,description=?,unit_label=?,target=?,unit_value=?,net_cents=?,min_qty=?,max_qty=?,default_qty=?,control=?,active=?,sort_order=? WHERE id=? AND product_id=?',[...$args,$id,$product]);
    else{query('INSERT INTO shop_product_modules (label,description,unit_label,target,unit_value,net_cents,min_qty,max_qty,default_qty,control,active,sort_order,product_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',[...$args,$product]);$id=(int)db()->lastInsertId();}audit_event('product.module.saved',(string)$id);return $id;
}
function seed_product_modules(int $product): void {
    $p=one('SELECT * FROM shop_products WHERE id=?',[$product]);if(!$p || product_modules($product,false))throw new RuntimeException('Vorlagen können nur bei einem Paket ohne Module angelegt werden.');
    $templates=$p['category']==='hosting'?[['HDD-Speicher','GB','storage_mb',1,'number'],['Web-Cronjobs','Cronjob','cron_limit',1,'number'],['FTP-Zugänge','Zugang','ftp_limit',1,'number'],['E-Mail-Postfächer','Postfach','mail_limit',1,'number'],['Datenbanken','Datenbank','database_limit',1,'number'],['PHP','','php_enabled',1,'checkbox'],['CGI-Bin','','cgi_enabled',1,'checkbox']]:($p['category']==='server'?[['vCPU','vCPU','cores',1,'number'],['Arbeitsspeicher','GB','memory_mb',1,'number'],['SSD-/HDD-Speicher','GB','disk_extra_gb',1,'number']]:[]);
    if(!$templates)throw new RuntimeException('Für diesen Bereich gibt es keine Modulvorlagen.');db()->beginTransaction();try{foreach($templates as $i=>[$label,$unit,$target,$value,$control])save_product_module(0,$product,['label'=>$label,'unit_label'=>$unit,'target'=>$target,'unit_value'=>$value,'unit_net'=>'0','min_qty'=>0,'max_qty'=>$control==='checkbox'?1:10,'default_qty'=>0,'control'=>$control,'sort_order'=>$i]);db()->commit();}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function validate_configured_profile(string $provider,array $profile): void {
    // ISPConfig counts use signed INT fields. Module maxima are set by the administrator.
    $limits=$provider==='ispconfig'?['storage_mb'=>[1,100000000],'ftp_limit'=>[0,2147483647],'mail_limit'=>[0,2147483647],'database_limit'=>[0,2147483647],'cron_limit'=>[0,2147483647],'mail_quota_mb'=>[1,100000000]]:($provider==='proxmox'?['cores'=>[1,128],'memory_mb'=>[128,1048576],'disk_extra_gb'=>[0,65536]]:[]);
    $labels=product_module_targets($provider==='ispconfig'?'hosting':'server');
    foreach($limits as $key=>[$min,$max])if(isset($profile[$key]) && ((int)$profile[$key]<$min || (int)$profile[$key]>$max))throw new RuntimeException('Die konfigurierte Ausstattung „'.($labels[$key]??$key).'“ ('.(int)$profile[$key].') liegt außerhalb des technisch unterstützten Bereichs von '.$min.' bis '.$max.'.');
    if($provider==='ispconfig' && (int)($profile['mail_limit']??0)*(int)($profile['mail_quota_mb']??0)>2147483647)throw new RuntimeException('Die Kombination aus '.(int)$profile['mail_limit'].' Postfächern und '.(int)$profile['mail_quota_mb'].' MB je Postfach überschreitet den technisch unterstützten Gesamt-Mail-Speicher. Verringere die Anzahl der Postfächer oder den Speicher je Postfach.');
}
function apply_module_profile_value(array $profile,array $line): array {
    $target=$line['target'];$value=$line['value'];
    if(in_array($target,['php_mode','php_version','auto_subdomain'],true)){$profile[$target]=$target==='php_version'?(int)$value:$value;if($target==='php_version'){$profile['php_version_label']=$line['option_label']??'';if(($profile['php_mode']??'no')==='no')$profile['php_mode']='php-fpm';}$profile['website_features_present']=1;}
    elseif(isset(hosting_feature_flags()[$target]) || $target==='php_enabled'){$profile[$target]=1;if($target==='php_enabled' && ($profile['php_mode']??'no')==='no')$profile['php_mode']='php-fpm';}
    elseif($target!=='description_only')$profile[$target]=(int)($profile[$target]??0)+(int)$value;
    return $profile;
}
function module_customer_base_profile(array $p): array {
    $base=$p['_base_product']??$p;$profile=checkout_automation($base)['settings']??[];
    foreach($p['_module_selection']??[] as $line)if(!empty($line['included']))$profile=apply_module_profile_value($profile,$line);
    if($base['category']==='hosting')$profile=array_replace($profile,normalized_hosting_features($profile,$profile));
    return array_intersect_key($profile,array_flip(['storage_mb','ftp_limit','database_limit','mail_limit','mail_quota_mb','cron_limit','cores','memory_mb','disk_extra_gb','php_enabled','php_mode','php_version','php_version_label','auto_subdomain',...array_keys(hosting_feature_flags())]));
}
function configured_product(array $p,mixed $selection=[]): array {
    if(isset($p['_quote_id'])){if($selection)throw new RuntimeException('Die Ausstattung deines persönlichen Angebots ist bereits festgelegt.');return $p;}
    if(!is_array($selection))throw new RuntimeException('Bitte gültige Modulmengen auswählen.');$base=$p['_base_product']??$p;$modules=product_modules((int)$base['id']);$known=array_column($modules,null,'id');
    foreach($selection as $id=>$qty)if(!isset($known[$id]) || !is_scalar($qty))throw new RuntimeException('Ein ausgewähltes Modul ist nicht mehr verfügbar. Bitte das Paket erneut öffnen.');
    if(!$modules)return $base;$a=checkout_automation($base);if(!$a)throw new RuntimeException('Die Grundausstattung für dieses Paket ist noch nicht eingerichtet.');$profile=$a['settings'];$net=product_options($base)['net_cents'];$baseNet=$net;$lines=[];
    $baseProfile=$profile;
    foreach($modules as $m){
        $included=module_is_included($m);
        if($included && array_key_exists($m['id'],$selection))throw new RuntimeException('Ein fest enthaltenes Modul kann im Checkout nicht geändert werden.');
        $qty=$included?module_included_quantity($m,$profile):($selection[$m['id']]??$m['default_qty']);
        $qty=module_quantity($qty);if(!$qty){if(!$included && $m['control']!=='select' && (int)$m['min_qty']>0)throw new RuntimeException('Die Mindestmenge für „'.$m['label'].'“ ist nicht erreicht.');continue;}
        $label=$m['label'];$technical=null;
        if($m['control']==='select'){$option=$m['options'][$qty]??null;if(!$option)throw new RuntimeException('Diese Option für „'.$m['label'].'“ ist nicht verfügbar.');$technical=$option['value'];$label.=': '.$option['label'];$unitNet=(int)$option['net_cents'];$quantity=1;$effect=$technical;}
        else{if(!$included && ($qty<(int)$m['min_qty'] || ((int)$m['max_qty']>0 && $qty>(int)$m['max_qty'])))throw new RuntimeException('Bitte eine gültige Menge für „'.$m['label'].'“ wählen.');$unitNet=(int)$m['net_cents'];$quantity=$qty;$effect=$qty*(int)$m['unit_value'];}
        $line=['id'=>(int)$m['id'],'label'=>$label,'description'=>$m['description'],'unit'=>$m['unit_label'],'quantity'=>$quantity,'unit_net_cents'=>$included?0:$unitNet,'net_cents'=>$included?0:$quantity*$unitNet,'target'=>$m['target'],'value'=>$effect,'included'=>$included];$lines[]=$line;if($net!==null)$net+=(int)$line['net_cents'];
        if($m['control']==='select'){$line['option_label']=$option['label'];$lines[array_key_last($lines)]=$line;}$line['control']=$m['control'];$line['choice']=$qty;$line['unit_value']=(int)$m['unit_value'];$line['catalog_id']=$m['_catalog_id']??null;$lines[array_key_last($lines)]=$line;$profile=apply_module_profile_value($profile,$line);if($included || !(int)$line['net_cents'])$baseProfile=apply_module_profile_value($baseProfile,$line);
    }
    if($a['provider']==='ispconfig')$profile=array_replace($profile,normalized_hosting_features($profile,$profile,isset($profile['website_features_present'])));
    if($net!==null && ($net<0 || $net>100000000))throw new RuntimeException('Die gewählte Konfiguration überschreitet den maximalen Bestellwert.');validate_configured_profile($a['provider'],$profile);
    if(strlen(json_encode($lines))>24000)throw new RuntimeException('Die gewählte Modulbeschreibung ist zu umfangreich.');
    $profile['module_selection']=$lines;$profile['base_contract_text']=$profile['contract_text']??'';if($lines)$profile['contract_text']=trim(($profile['contract_text']??'')."\n\nGewählte Zusatzmodule:\n".module_description($lines));
    $p=$base;$p['_base_product']=$base;$p['_automation']=array_replace($a,['settings'=>$profile]);$p['_options']=array_replace(product_options($base),['net_cents'=>$net]);$p['price_cents']=$net===null?null:vat_from_net($net)['gross_cents'];$p['_module_selection']=$lines;$p['_module_base_net']=$baseNet;$p['_module_base_profile']=$baseProfile;$p['_module_key']=hash('sha256',json_encode($lines));
    if($net!==null && billing_months($base['billing']) && product_sale_mode($base)==='direct'){$gross=vat_from_net((int)$baseNet)['gross_cents'];foreach($lines as $line)if(!$line['included'])$gross+=vat_from_net((int)$line['net_cents'])['gross_cents'];$p['price_cents']=$gross;$p['_contract_tax']=['net_cents'=>$net,'tax_cents'=>$gross-$net,'gross_cents'=>$gross,'tax_bps'=>1900];}return $p;
}
function module_description(array $lines): string {return implode("\n",array_map(fn($l)=>$l['quantity'].' × '.$l['label'].($l['unit']?' ('.$l['unit'].')':'').(!empty($l['included'])?' · im Grundpreis enthalten':' · '.money($l['net_cents']).' netto'),$lines));}
function save_domain_recommendations(int $product,mixed $targets): void {
    if(!is_array($targets))throw new RuntimeException('Bitte gültige Zusatzpakete auswählen.');$ids=[];foreach($targets as $target){$p=one("SELECT id FROM shop_products WHERE id=? AND category IN ('hosting','server')",[(int)$target]);if(!$p)throw new RuntimeException('Das empfohlene Zusatzpaket ist nicht verfügbar.');$ids[]=(int)$p['id'];}$value=json_encode(array_values(array_unique($ids)));
    if(one('SELECT product_id FROM shop_product_recommendations WHERE product_id=?',[$product]))query('UPDATE shop_product_recommendations SET targets=? WHERE product_id=?',[$value,$product]);else query('INSERT INTO shop_product_recommendations (product_id,targets) VALUES (?,?)',[$product,$value]);
}
function domain_recommendations(int $product): array {
    $saved=one('SELECT targets FROM shop_product_recommendations WHERE product_id=?',[$product]);$ids=$saved?json_decode($saved['targets'],true):null;
    return array_values(array_filter(all("SELECT * FROM shop_products WHERE category IN ('hosting','server') AND active=1 ORDER BY sort_order,id"),fn($p)=>$ids===null || in_array((int)$p['id'],$ids,true)));
}
