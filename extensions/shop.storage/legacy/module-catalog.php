<?php
function product_has_customer_modules(array $p): bool {
    return in_array($p['category'],['hosting','server'],true)
        && (bool)array_filter(product_modules((int)$p['id']),fn($m)=>!module_is_included($m));
}
function module_catalog(string $category='',bool $active=false): array {
    $where=[];$args=[];if($category!==''){$where[]='category=?';$args[]=$category;}if($active)$where[]='active=1';
    return all('SELECT * FROM shop_module_catalog'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY category,sort_order,id',$args);
}
function product_configurator_enabled(int $product): bool {
    $row=one('SELECT enabled FROM shop_product_configurators WHERE product_id=?',[$product]);if($row)return (bool)$row['enabled'];
    return (bool)one('SELECT id FROM shop_product_modules WHERE product_id=? AND active=1',[$product]);
}
function catalog_module_options(array $module): array {return json_decode($module['options_json']??'[]',true,32,JSON_THROW_ON_ERROR);}
function save_catalog_module(int $id,array $input): int {
    $category=(string)($input['category']??'');$target=(string)($input['target']??'');$targets=product_module_targets($category);
    if(!in_array($category,['hosting','server'],true) || !isset($targets[$target]))throw new RuntimeException('Bitte eine gültige Kategorie und Modulwirkung auswählen.');
    $old=$id?one('SELECT * FROM shop_module_catalog WHERE id=?',[$id]):null;if($id && !$old)throw new RuntimeException('Modul nicht gefunden.');
    if($old && ($old['category']!==$category || $old['target']!==$target) && one('SELECT module_id FROM shop_product_module_bindings WHERE catalog_id=?',[$id]))throw new RuntimeException('Kategorie und technische Wirkung eines verwendeten Moduls bleiben fest. Bitte ein neues Modul anlegen.');
    $label=trim((string)($input['label']??''));$description=trim((string)($input['description']??''));$unit=trim((string)($input['unit_label']??''));$net=parse_net_price((string)($input['unit_net']??''),false);
    if(!$label || mb_strlen($label)>120 || mb_strlen($description)>500 || mb_strlen($unit)>60 || $net===null || $net<0 || $net>10000000)throw new RuntimeException('Bitte Modulbezeichnung, Beschreibung und Nettopreis prüfen.');
    $select=in_array($target,['php_mode','php_version','auto_subdomain'],true);$boolean=isset(hosting_feature_flags()[$target]) || $target==='php_enabled';$control=$select?'select':($boolean?'checkbox':(string)($input['control']??'number'));
    if(!in_array($control,['number','checkbox','select'],true))throw new RuntimeException('Ungültiges Bedienelement.');
    $numbers=[];foreach(['unit_value'=>[1,1000000,1],'sort_order'=>[0,9999,0]] as $key=>[$min,$max,$default]){$v=module_quantity($input[$key]??$default);if($v<$min || $v>$max)throw new RuntimeException('Bitte gültige Mengen für das Modul eintragen.');$numbers[$key]=$v;}
    foreach(['min_qty','max_qty','default_qty'] as $key)$numbers[$key]=module_quantity($input[$key]??0,$key==='max_qty');
    $options=[];
    if($select){
        $available=hosting_select_choices($target);$enabled=$input['option_enabled']??(isset($input['option_selection_present'])?[]:array_keys($available));if(!is_array($enabled))throw new RuntimeException('Bitte gültige Auswahloptionen eintragen.');
        foreach($enabled as $key){if(!isset($available[$key]))throw new RuntimeException('Eine ausgewählte Serveroption ist nicht mehr verfügbar.');$price=parse_net_price((string)($input['option_prices'][$key]??number_format($net/100,2,'.','')),false);if($price===null || $price<0 || $price>10000000)throw new RuntimeException('Bitte gültige Optionspreise eintragen.');$options[(int)$key]=$available[$key]+['net_cents'=>$price];}
        if(!$options || ($numbers['default_qty'] && !isset($options[$numbers['default_qty']])))throw new RuntimeException('Bitte mindestens eine Option und eine gültige Vorauswahl festlegen.');$numbers['min_qty']=0;$numbers['max_qty']=1;$numbers['unit_value']=1;
    }elseif($control==='checkbox'){$numbers['min_qty']=0;$numbers['max_qty']=1;$numbers['default_qty']=min(1,$numbers['default_qty']);$numbers['unit_value']=1;}
    else validate_module_quantity_range($numbers);
    $unit=module_storage_unit($target,$unit);$numbers['unit_value']=module_stored_step($target,$unit,$numbers['unit_value']);
    $args=[$category,$label,$description,$unit,$target,$numbers['unit_value'],$net,$numbers['min_qty'],$numbers['max_qty'],$numbers['default_qty'],$control,json_encode($options),isset($input['active'])?1:0,$numbers['sort_order']];
    if($id)query('UPDATE shop_module_catalog SET category=?,label=?,description=?,unit_label=?,target=?,unit_value=?,net_cents=?,min_qty=?,max_qty=?,default_qty=?,control=?,options_json=?,active=?,sort_order=? WHERE id=?',[...$args,$id]);
    else{query('INSERT INTO shop_module_catalog (category,label,description,unit_label,target,unit_value,net_cents,min_qty,max_qty,default_qty,control,options_json,active,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',$args);$id=(int)db()->lastInsertId();}
    audit_event('module.catalog.saved',(string)$id);return $id;
}
function save_package_module_bindings(int $product,array $input): void {
    if(!isset($input['module_bindings_present']))return;
    $p=one('SELECT category FROM shop_products WHERE id=?',[$product]);$selected=$input['package_modules']??[];if(!is_array($selected))throw new RuntimeException('Bitte gültige Paketmodule auswählen.');
    $config=isset($input['configurator_enabled']) && in_array($p['category'],['hosting','server'],true);
    $keep=[];
    foreach($selected as $catalog=>$values){
        if(!is_array($values) || !isset($values['enabled']))continue;$m=one('SELECT * FROM shop_module_catalog WHERE id=?',[(int)$catalog]);
        if(!$m || $m['category']!==$p['category'])throw new RuntimeException('Ein ausgewähltes Modul gehört nicht zur Paketkategorie.');
        $mode=(string)($values['mode']??'optional');if(!in_array($mode,['included','optional'],true))throw new RuntimeException('Ungültige Modulzuordnung.');if(module_is_free($m))$mode='included';
        $qty=module_quantity($values['included_qty']??($m['control']==='select'?$m['default_qty']:1));
        if(module_is_free($m))$qty=module_included_quantity($m+['_included_qty'=>$qty,'_mode'=>'included']);
        if($mode==='included'){
            if($m['control']==='select' && !isset(catalog_module_options($m)[(int)$qty]))throw new RuntimeException('Bitte die enthaltene Auswahl für „'.$m['label'].'“ festlegen.');
            if($m['control']!=='select' && ($qty<1 || ((int)$m['max_qty']>0 && $qty>(int)$m['max_qty'])))throw new RuntimeException('Bitte die enthaltene Menge für „'.$m['label'].'“ prüfen.');
        }
        $link=one('SELECT b.module_id FROM shop_product_module_bindings b JOIN shop_product_modules m ON m.id=b.module_id WHERE m.product_id=? AND b.catalog_id=?',[$product,$catalog]);
        if($link)$id=(int)$link['module_id'];else{
            query('INSERT INTO shop_product_modules (product_id,label,description,unit_label,target,unit_value,net_cents,min_qty,max_qty,default_qty,control,active,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',[$product,$m['label'],$m['description'],$m['unit_label'],$m['target'],$m['unit_value'],$m['net_cents'],$m['min_qty'],$m['max_qty'],$m['default_qty'],$m['control'],1,$m['sort_order']]);$id=(int)db()->lastInsertId();
            query('INSERT INTO shop_product_module_bindings (module_id,catalog_id,mode,included_qty) VALUES (?,?,?,?)',[$id,$catalog,$mode,(int)$qty]);
        }
        query('UPDATE shop_product_modules SET active=1 WHERE id=?',[$id]);query('UPDATE shop_product_module_bindings SET mode=?,included_qty=? WHERE module_id=?',[$mode,(int)$qty,$id]);$keep[]=$id;
    }
    foreach(all('SELECT b.module_id FROM shop_product_module_bindings b JOIN shop_product_modules m ON m.id=b.module_id WHERE m.product_id=?',[$product]) as $row)if(!in_array((int)$row['module_id'],$keep,true))query('UPDATE shop_product_modules SET active=0 WHERE id=?',[$row['module_id']]);
    if(one('SELECT product_id FROM shop_product_configurators WHERE product_id=?',[$product]))query('UPDATE shop_product_configurators SET enabled=? WHERE product_id=?',[$config?1:0,$product]);else query('INSERT INTO shop_product_configurators (product_id,enabled) VALUES (?,?)',[$product,$config?1:0]);
}
function seed_catalog_modules(string $category): void {
    if(module_catalog($category))throw new RuntimeException('Für diese Kategorie gibt es bereits zentrale Module.');
    $targets=product_module_targets($category);$i=0;
    foreach($targets as $target=>$label){if(in_array($target,['description_only','php_enabled'],true))continue;$in=['category'=>$category,'label'=>$label,'target'=>$target,'unit_net'=>'0','unit_value'=>1,'max_qty'=>10,'sort_order'=>$i++];save_catalog_module(0,$in);}
}
