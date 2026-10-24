<?php
function domain_public_suffix(string $domain): string {
    static $exact,$wild,$exceptions;if($exact===null){$exact=$wild=$exceptions=[];foreach(file(__DIR__.'/lib/publicsuffix/icann.dat',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line){if(str_starts_with($line,'//'))continue;if($line[0]==='!')$exceptions[substr($line,1)]=true;elseif(str_starts_with($line,'*.'))$wild[substr($line,2)]=true;else $exact[$line]=true;}}
    $labels=explode('.',strtolower($domain));$best=end($labels);$n=count($labels);for($i=0;$i<$n;$i++){$part=implode('.',array_slice($labels,$i));if(isset($exceptions[$part]))return implode('.',array_slice($labels,$i+1));if(isset($exact[$part]) && strlen($part)>strlen($best))$best=$part;if($i<$n-1 && isset($wild[implode('.',array_slice($labels,$i+1))]) && strlen($part)>strlen($best))$best=$part;}return $best;
}
function domain_registration_name(string $domain): string {
    $domain=valid_domain($domain);if(!$domain)throw new RuntimeException('Bitte eine Domain eingeben.');$suffix=domain_public_suffix($domain);$stem=substr($domain,0,-strlen($suffix)-1);if(!$stem || str_contains($stem,'.'))throw new RuntimeException('Bitte einen Domainnamen ohne Subdomain eingeben, zum Beispiel meinprojekt.de.');return $domain;
}
function domain_catalog_managed(): bool {return (bool)query('SELECT COUNT(*) FROM shop_domain_tlds')->fetchColumn();}
function domain_tlds(bool $enabledOnly=true): array {return all('SELECT t.*,p.active AS product_active FROM shop_domain_tlds t JOIN shop_products p ON p.id=t.product_id WHERE p.category=\'domain\''.($enabledOnly?' AND t.enabled=1 AND p.active=1':'').' ORDER BY t.sort_order,t.extension');}
function domain_extension(string $input): string {
    $v=strtolower(trim($input));if(str_starts_with($v,'.'))$v=substr($v,1);
    if(strlen($v)>100 || !preg_match('/^(?:[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*(?:[a-z]{2,63}|xn--[a-z0-9-]{2,59})$/D',$v))throw new RuntimeException('Bitte eine gültige Endung wie .de, .com oder .co.uk eintragen.');if(domain_public_suffix('example.'.$v)!==$v)throw new RuntimeException('Bitte eine Registrierungsendung wie .de oder .co.uk eingeben, keinen vollständigen Domainnamen.');return $v;
}
function domain_tld_for(string $domain,bool $enabledOnly=true): ?array {
    $suffix=domain_public_suffix($domain);foreach(domain_tlds(false) as $row)if($row['extension']===$suffix){if($enabledOnly && (!$row['enabled'] || !$row['product_active']))return null;return $row;}return null;
}
function domain_tld_save(int $id,array $input): int {
    $extension=domain_extension((string)($input['extension']??''));$p=one("SELECT * FROM shop_products WHERE id=? AND category='domain'",[(int)($input['product_id']??0)]);if(!$p)throw new RuntimeException('Bitte ein Domainpaket als Grundlage für Leistung und Bereitstellung auswählen.');
    $net=parse_net_price((string)($input['net_price']??''));$mode=(string)($input['sale_mode']??'request');if(!in_array($mode,['direct','request'],true) || ($mode==='direct' && ($net===null || $net<1)))throw new RuntimeException('Für Direktkauf wird ein positiver Nettopreis benötigt. Alternativ „Auf Anfrage“ auswählen.');
    $maximum=parse_net_price((string)($input['max_purchase']??''));$maximum??=0;
    if($mode==='direct' && !in_array($p['billing'],['year','once'],true))throw new RuntimeException('Domainpreise gelten für ein Registrierungsjahr. Bitte das zugeordnete Paket auf jährlich oder einmalig stellen.');
    if(one('SELECT id FROM shop_domain_tlds WHERE extension=? AND id<>?',[$extension,$id]))throw new RuntimeException('Diese Endung ist bereits hinterlegt. Bitte den vorhandenen Eintrag bearbeiten.');
    $args=[$extension,$p['id'],$net,$mode,isset($input['enabled'])?1:0,$maximum,max(0,min(9999,(int)($input['sort_order']??0)))];
    if($id){if(!one('SELECT id FROM shop_domain_tlds WHERE id=?',[$id]))throw new RuntimeException('Diese Domainendung wurde nicht gefunden.');query('UPDATE shop_domain_tlds SET extension=?,product_id=?,net_cents=?,sale_mode=?,enabled=?,max_purchase_cents=?,sort_order=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[...$args,$id]);}
    else{query('INSERT INTO shop_domain_tlds (extension,product_id,net_cents,sale_mode,enabled,max_purchase_cents,sort_order) VALUES (?,?,?,?,?,?,?)',$args);$id=(int)db()->lastInsertId();}audit_event('domain.tld.save',(string)$id);return $id;
}
function domain_priced_product(array $row): array {
    $p=one("SELECT * FROM shop_products WHERE id=? AND category='domain'",[$row['product_id']]);if(!$p)throw new RuntimeException('Das Domainpaket wurde nicht gefunden.');$a=automation_profile((int)$p['id']);$o=product_options($p);
    $p['_domain_tld_id']=(int)$row['id'];$p['_domain_extension']=$row['extension'];$p['name']='Domain .'.$row['extension'];$p['price_cents']=$row['net_cents']===null?null:vat_from_net((int)$row['net_cents'])['gross_cents'];$p['_options']=array_replace($o,['net_cents'=>$row['net_cents']===null?null:(int)$row['net_cents'],'tax_bps'=>1900,'sale_mode'=>$row['sale_mode']]);
    if($a){$a['settings']['allowed_tlds']=$row['extension'];$a['settings']['max_purchase_cents']=(int)$row['max_purchase_cents'];$p['_automation']=$a;}$p['_tld_enabled']=(bool)$row['enabled'];return $p;
}
function domain_select_product(array $p,string $domain): array {
    if($p['category']!=='domain' || isset($p['_quote_id']) || !domain_catalog_managed())return $p;
    $row=domain_tld_for(domain_registration_name($domain));if(!$row)throw new RuntimeException('Diese Domainendung wird derzeit nicht angeboten. Bitte wähle eine freigegebene Endung in der Domain-Suche.');
    if((int)$row['product_id']!==(int)$p['id'])throw new RuntimeException('Für diese Endung gilt ein anderes Domainpaket. Bitte öffne es über die Domain-Suche.');return domain_priced_product($row);
}
function domain_search_name(string $input): string {
    $input=trim($input);if($input==='')return '';if(!str_contains($input,'.')){$first=domain_tlds()[0]??null;if(!$first)throw new RuntimeException('Bitte eine vollständige Wunschdomain mit Endung eingeben.');$input.='.'.$first['extension'];}
    $input=valid_domain($input);if(str_starts_with($input,'www.'))$input=substr($input,4);return domain_registration_name($input);
}
function domain_search_stem(string $domain): string {
    return substr($domain,0,-strlen(domain_public_suffix($domain))-1);
}
function domain_suggestions(string $domain,int $offset=0,int $limit=8): array {
    $stem=domain_search_stem($domain);$result=[];foreach(domain_tlds() as $row){$candidate=$stem.'.'.$row['extension'];if($candidate===$domain)continue;try{valid_domain($candidate);}catch(RuntimeException $e){continue;}$row['domain']=$candidate;$result[]=$row;}return array_slice($result,max(0,$offset),$limit);
}
function domain_price_label(?array $p): string {return !$p || $p['price_cents']===null?'Auf Anfrage':money($p['price_cents']);}
function domain_search_url(string $domain): string {return '/domain-pruefen?domain='.rawurlencode($domain);}
function domain_registration_offer(array $availability,array $profile): array {
    $domain=$availability['domain'];$offer=$availability['offer']??null;
    if($availability['status']!=='available' || !$offer)throw new RuntimeException('Die Domain '.$domain.' ist aktuell nicht zur Neuregistrierung verfügbar. Bitte wähle eine andere Domain. Es wurde keine Zahlung gestartet.');
    $allowed=array_map('trim',explode(',',strtolower($profile['allowed_tlds']??'')));$allowed=array_filter($allowed,fn($tld)=>$tld!=='' && str_ends_with($domain,'.'.$tld));
    if(!$allowed || !standard_domain_offer($offer))throw new RuntimeException('Für diese Domain erstellen wir dir ein individuelles Angebot. Es wurde keine Zahlung gestartet.');
    foreach($offer['configurations']??[] as $required)if(($required['required']??false) && !isset($profile['domain_configuration'][$required['label']]))throw new RuntimeException('Für diese Domain sind zusätzliche Angaben erforderlich. Bitte frage ein Angebot an.');
    $maximum=(int)($profile['max_purchase_cents']??0);$cost=null;foreach($offer['prices']??[] as $item)if(($item['label']??'')==='TOTAL' && ($item['price']['currencyCode']??'')==='EUR' && isset($item['price']['value']))$cost=(int)round($item['price']['value']*100);
    if($cost===null || $maximum<1 || $cost>$maximum)throw new RuntimeException('Für diese Domain muss der Preis persönlich geprüft werden. Bitte frage ein Angebot an.');return $offer;
}
function domain_lookup_result(string $domain): array {
    $domain=domain_registration_name($domain);$p=domain_product_for($domain);$result=['domain'=>$domain,'status'=>'unsupported','message'=>'Diese Endung bieten wir derzeit nicht an. Wähle eine unserer angebotenen Endungen.','price'=>domain_price_label($p),'url'=>null,'action'=>null,'extension'=>$p['_domain_extension']??domain_public_suffix($domain),'gross_cents'=>$p['price_cents']??null,'tax_note'=>'inkl. 19 % MwSt. / Registrierungsjahr'];
    if(domain_catalog_managed() && !$p){$result['price']='Nicht angeboten';$result['tax_note']='';return $result;}
    if(!integration_ready('ovh'))throw new RuntimeException('Die Domainprüfung ist gerade nicht erreichbar. Bitte versuche es später erneut.');$availability=(new OvhProvider())->availability($domain);$result=array_replace($result,array_intersect_key($availability,array_flip(['status','message','transfer_available'])));
    if($p){$result['url']='/konfigurieren?produkt='.rawurlencode($p['slug']).'&domain='.rawurlencode($domain);$result['action']=$availability['status']==='available'?'Angebot anfragen':'Domainumzug anfragen';if($availability['status']!=='available'){$result['url'].='&domain_intent=transfer';$result['price']='Auf Anfrage';$result['tax_note']='Domainumzug nach persönlichem Angebot';}
        if($availability['status']==='available'){
            $regular=standard_domain_offer($availability['offer']);$profile=checkout_automation($p)['settings']??[];$safe=null;if((int)($profile['max_purchase_cents']??0)>0){$safe=false;try{domain_registration_offer($availability,$profile);$safe=true;}catch(RuntimeException $e){}}
            if(!$regular){$result['price']='Auf Anfrage';$result['message']='Diese Domain ist als Premium- oder Sonderangebot verfügbar. Wir erstellen dir einen individuellen Preis.';}
            elseif(package_is_fixed($p) && $safe===false){$result['price']='Auf Anfrage';$result['message']='Diese Domain ist verfügbar und benötigt eine persönliche Preis- oder Registrierungsprüfung.';}
            elseif(package_is_fixed($p) && $safe!==false){$result['url']='/bestellen?produkt='.rawurlencode($p['slug']).'&domain='.rawurlencode($domain);$result['action']=checkout_ready($p)?'Domain auswählen':'Paket ansehen';}
        }
    }
    return $result;
}
function handle_domain_action(string $action): bool {
    if($action==='domain_tld_save'){require_admin();$id=domain_tld_save((int)($_POST['id']??0),$_POST);flash('Domainendung und Nettopreis gespeichert.');redirect('/admin/domains?id='.$id);}
    if($action==='domain_tld_toggle'){require_admin();$id=(int)($_POST['id']??0);query('UPDATE shop_domain_tlds SET enabled=CASE WHEN enabled=1 THEN 0 ELSE 1 END,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$id]);audit_event('domain.tld.toggle',(string)$id);flash('Angebotene Domainendungen aktualisiert.');redirect('/admin/domains');}
    return false;
}
