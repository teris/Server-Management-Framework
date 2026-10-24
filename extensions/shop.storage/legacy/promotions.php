<?php
/** Purchase pricing is calculated from catalog data and frozen in the order. */
function coupon_code(mixed $value): string {
    if(!is_scalar($value))throw new RuntimeException('Bitte einen gültigen Gutscheincode eingeben.');
    $code=strtoupper(trim((string)$value));if($code!=='' && !preg_match('/^[A-Z0-9][A-Z0-9_-]{2,63}$/D',$code))throw new RuntimeException('Ein Gutscheincode benötigt 3–64 Buchstaben, Zahlen, Bindestriche oder Unterstriche.');return $code;
}
function promotion_int(mixed $value,int $max=100000000): int {
    if(!is_scalar($value) || !preg_match('/^\d{1,10}$/D',(string)$value) || (int)$value>$max)throw new RuntimeException('Bitte eine gültige nichtnegative Zahl eintragen.');return (int)$value;
}
function promotion_percent(mixed $value): int {
    if(!is_scalar($value) || !preg_match('/^(?:\d{1,3})(?:[.,]\d{1,2})?$/D',trim((string)$value)))throw new RuntimeException('Bitte einen Prozentsatz von 0 bis 100 eintragen.');
    $bps=(int)round((float)str_replace(',','.',trim((string)$value))*100);if($bps>10000)throw new RuntimeException('Ein Rabatt darf höchstens 100 % betragen.');return $bps;
}
function promotion_net(mixed $value): int {if(!is_scalar($value))throw new RuntimeException('Bitte einen gültigen Nettobetrag eintragen.');return parse_net_price(trim((string)$value))??0;}
function coupon_save(int $id,array $input): int {
    $code=coupon_code($input['code']??'');$name=trim((string)($input['name']??''));$kind=(string)($input['kind']??'percent');
    if(!$code || !$name || mb_strlen($name)>120 || !in_array($kind,['percent','fixed','bundle'],true))throw new RuntimeException('Bitte Code, Bezeichnung und Gutscheinart vervollständigen.');
    $old=$id?one('SELECT * FROM shop_coupons WHERE id=?',[$id]):null;if($id && !$old)throw new RuntimeException('Gutschein nicht gefunden.');
    if(one('SELECT id FROM shop_coupons WHERE code=? AND id<>?',[$code,$id]))throw new RuntimeException('Dieser Gutscheincode ist bereits vergeben.');
    $customer=promotion_int($input['customer_id']??0);$product=promotion_int($input['product_id']??0);$category=(string)($input['category']??'');
    if($customer && !one("SELECT id FROM customers WHERE id=? AND status='active'",[$customer]))throw new RuntimeException('Das ausgewählte Kundenkonto ist nicht aktiv.');
    if($product && !one('SELECT id FROM shop_products WHERE id=?',[$product]))throw new RuntimeException('Das ausgewählte Produkt fehlt.');
    if(!in_array($category,['','hosting','server','domain','managed'],true))throw new RuntimeException('Ungültige Kategorie.');
    $duration=(string)($input['duration']??'first');if(!in_array($duration,['first','recurring'],true))throw new RuntimeException('Bitte die Rabattdauer auswählen.');
    $rules=['customer_id'=>$customer,'product_id'=>$product,'category'=>$category,'duration'=>$duration,'percent_bps'=>promotion_percent($input['percent']??0),'fixed_net_cents'=>promotion_net($input['fixed_net']??'0'),'minimum_monthly_net'=>promotion_net($input['minimum_monthly_net']??'0'),'max_uses'=>promotion_int($input['max_uses']??0),'per_customer'=>promotion_int($input['per_customer']??0),'test_only'=>isset($input['test_only']), 'starts_ts'=>0,'ends_ts'=>0,'domain_cap_net'=>promotion_net($input['domain_cap_net']??'0'),'hosting_cap_net'=>promotion_net($input['hosting_cap_net']??'0')];
    foreach(['starts','ends'] as $key){$date=trim((string)($input[$key]??''));if($date!==''){if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date))throw new RuntimeException('Bitte ein gültiges Kalenderdatum eintragen.');$day=new DateTimeImmutable($date.' 00:00:00',new DateTimeZone('Europe/Berlin'));if($day->format('Y-m-d')!==$date)throw new RuntimeException('Bitte ein gültiges Kalenderdatum eintragen.');$rules[$key.'_ts']=($key==='ends'?$day->modify('+1 day'):$day)->getTimestamp();}}
    if($rules['ends_ts'] && $rules['starts_ts']>=$rules['ends_ts'])throw new RuntimeException('Das Enddatum muss nach dem Beginn liegen.');
    if(($kind==='percent' && !$rules['percent_bps']) || ($kind==='fixed' && !$rules['fixed_net_cents']))throw new RuntimeException('Bitte einen Rabatt größer als 0 eintragen.');
    if($kind==='bundle'){if(!$rules['domain_cap_net'] && !$rules['hosting_cap_net'])throw new RuntimeException('Bitte mindestens einen Höchstwert für Domain oder Webspace eintragen.');$rules['category']='server';$rules['duration']='first';}
    if($rules['test_only'] && (!$customer || $kind!=='percent' || $rules['percent_bps']!==10000 || $duration!=='recurring'))throw new RuntimeException('Ein persönlicher Testgutschein muss einem Kunden zugeordnet sein und dauerhaft 100 % Rabatt geben.');
    if($id)query('UPDATE shop_coupons SET code=?,name=?,kind=?,rules=?,active=?,revision=revision+1,updated_ts=? WHERE id=?',[$code,$name,$kind,json_encode($rules),isset($input['active'])?1:0,shop_now(),$id]);
    else{query('INSERT INTO shop_coupons (code,name,kind,rules,active,created_ts,updated_ts) VALUES (?,?,?,?,?,?,?)',[$code,$name,$kind,json_encode($rules),isset($input['active'])?1:0,shop_now(),shop_now()]);$id=(int)db()->lastInsertId();}
    audit_event('coupon.saved',(string)$id);return $id;
}
function prepayment_options(int $product): array {return all('SELECT * FROM shop_prepayment_options WHERE product_id=? AND enabled=1 ORDER BY months',[$product]);}
function save_prepayment_options(int $product,array $input): void {
    if(!isset($input['prepayment_present']))return;$p=one('SELECT billing FROM shop_products WHERE id=?',[$product]);$options=[];
    foreach([2,3,6,12,24] as $m){$item=$input['prepayment'][$m]??[];if(!is_array($item))throw new RuntimeException('Ungültige Vorauszahlungseinstellung.');$bps=promotion_percent($item['discount']??0);if($bps>=10000)throw new RuntimeException('Der Vorauszahlungsrabatt muss kleiner als 100 % sein.');$options[]=[$product,$m,$bps,$p['billing']==='month' && isset($item['enabled'])?1:0];}
    foreach($options as [$id,$m,$bps,$enabled]){if(one('SELECT product_id FROM shop_prepayment_options WHERE product_id=? AND months=?',[$id,$m]))query('UPDATE shop_prepayment_options SET discount_bps=?,enabled=? WHERE product_id=? AND months=?',[$bps,$enabled,$id,$m]);else query('INSERT INTO shop_prepayment_options (product_id,months,discount_bps,enabled) VALUES (?,?,?,?)',[$id,$m,$bps,$enabled]);}
}
function coupon_validate(array $row,int $customer,array $p,int $monthlyNet,?int $orderId=null): array {
    $r=json_decode($row['rules'],true);$now=shop_now();$bad=!$row['active'] || ($r['customer_id'] && (int)$r['customer_id']!==$customer) || ($r['product_id'] && (int)$r['product_id']!==(int)$p['id']) || ($r['category'] && $r['category']!==$p['category']) || ($r['starts_ts'] && $now<$r['starts_ts']) || ($r['ends_ts'] && $now>=$r['ends_ts']) || $monthlyNet<(int)$r['minimum_monthly_net'];
    if($bad)throw new RuntimeException('Dieser Gutschein ist für dein Konto, diese Auswahl oder diesen Zeitpunkt nicht gültig.');
    foreach(['max_uses'=>false,'per_customer'=>true] as $key=>$own){if(!(int)$r[$key])continue;$sql="SELECT COUNT(*) FROM shop_coupon_uses WHERE coupon_id=? AND state<>'released' AND order_id<>?".($own?' AND customer_id=?':'');$params=[$row['id'],$orderId??0];if($own)$params[]=$customer;if((int)query($sql,$params)->fetchColumn()>=(int)$r[$key])throw new RuntimeException('Das Einlösekontingent dieses Gutscheins ist ausgeschöpft. Offene Bestellungen reservieren ihre Einlösung.');}return $r;
}
function promotion_reduction(int $net,array $coupon): int {return match($coupon['kind']??''){ 'percent'=>intdiv($net*(int)$coupon['rules']['percent_bps']+5000,10000),'fixed'=>min($net,(int)$coupon['rules']['fixed_net_cents']),default=>0};}
function purchase_pricing(array $p,int $customer,array $options): array {
    if(isset($p['_purchase_pricing']))return $p;
    $months=promotion_int($options['prepay_months']??1,24);if(!$months)$months=1;$termBps=0;$factor=1;
    if($months!==1){if($p['billing']!=='month' || isset($p['_quote_id']))throw new RuntimeException('Vorauszahlung wird nur für dafür freigegebene Monatspakete angeboten.');$term=one('SELECT * FROM shop_prepayment_options WHERE product_id=? AND months=? AND enabled=1',[$p['id'],$months]);if(!$term)throw new RuntimeException('Dieser Vorauszahlungszeitraum wird für das Paket nicht angeboten.');$factor=$months;$termBps=(int)$term['discount_bps'];}
    $code=coupon_code($options['coupon_code']??'');$rewardId=promotion_int($options['reward_id']??0);if($code && $rewardId)throw new RuntimeException('Ein Gutschein und ein Zusatzpaket-Bonus können nicht miteinander kombiniert werden.');
    if(!$code && !$rewardId && $factor===1)return $p;
    $total=product_tax($p)['net_cents'];$monthly=billing_months($p['billing'])?intdiv((int)($p['_module_pricing']['regular']['net_cents']??$total),billing_months($p['billing'])):$total;$coupon=null;$reward=null;$existing=null;
    $checkoutKey=(string)($options['checkout_key']??'');if($checkoutKey && hash_equals($_SESSION['checkout_key']??'',$checkoutKey) && ($oid=$_SESSION['checkout_order'][$checkoutKey]??0))$existing=one('SELECT id FROM shop_orders WHERE id=? AND customer_id=? AND product_id=?',[$oid,$customer,$p['id']]);
    if($code){$row=one('SELECT * FROM shop_coupons WHERE code=?',[$code]);if(!$row)throw new RuntimeException('Dieser Gutschein ist für deine Auswahl nicht gültig.');$rules=coupon_validate($row,$customer,$p,$monthly,$existing?(int)$existing['id']:null);$coupon=['id'=>(int)$row['id'],'code'=>$row['code'],'name'=>$row['name'],'kind'=>$row['kind'],'revision'=>(int)$row['revision'],'rules'=>$rules];}
    if($rewardId){$reward=promotion_checked_reward($rewardId,$customer,$p,$total*$factor,$existing?(int)$existing['id']:null);}
    $fullNet=$total*$factor;if($fullNet>100000000)throw new RuntimeException('Der Preis der Vorauszahlung ist zu hoch.');
    $termNet=$fullNet-intdiv($fullNet*$termBps+5000,10000);$discount=$reward?$termNet:($coupon?promotion_reduction($termNet,$coupon):0);$initial=$termNet-$discount;$regular=$coupon && $coupon['rules']['duration']==='recurring'?$initial:$termNet;
    $pricing=['version'=>1,'customer_id'=>$customer,'factor'=>$factor,'months'=>$factor===1?billing_months($p['billing']):$months,'term_discount_bps'=>$termBps,'catalog_net'=>$total,'original_net'=>$fullNet,'term_net'=>$termNet,'discount_net'=>$discount,'initial_net'=>$initial,'regular_net'=>$regular,'coupon'=>$coupon,'reward_id'=>$rewardId,'reward_source'=>$reward?(int)$reward['source_order_id']:0,'qualifying_monthly_net'=>$monthly,'test_only'=>(bool)($coupon['rules']['test_only']??false),'invoice_renewal'=>$months===24];
    $p['_purchase_pricing']=$pricing;$p['_purchase_original']=$p['_base_product']??$p;
    if($factor!==1)$p['billing']=match($months){2=>'months2',3=>'quarter',6=>'halfyear',12=>'year',24=>'months24'};
    $p['_contract_policy']=array_replace(product_contract_policy($p),['billing_months'=>$pricing['months']]);$p=pricing_set_amount($p,$initial);
    $gross=pricing_cart_gross($p,false);$p['price_cents']=$gross;$p['_contract_tax']=['net_cents'=>$initial,'tax_cents'=>$gross-$initial,'gross_cents'=>$gross,'tax_bps'=>1900];return $p;
}
function pricing_cart_gross(array $p,bool $regular=false): int {
    $s=$p['_purchase_pricing']??null;if(!$s)return (int)$p['price_cents'];$net=(int)$s[$regular?'regular_net':'initial_net'];$lines=array_values(array_filter($p['_module_selection']??[],fn($l)=>empty($l['included']) && (int)$l['net_cents']>0));
    if(!$lines || !billing_months($p['billing']) || ($s['initial_net']===0 && $s['regular_net']===0) || !isset($p['_base_product']))return vat_from_net($net)['gross_cents'];
    [$base,$parts]=pricing_split_cart($p,module_checkout_base($p),$lines);$gross=vat_from_net((int)$base['_purchase_pricing'][$regular?'regular_net':'initial_net'])['gross_cents'];foreach($parts as $line)$gross+=vat_from_net((int)$line['purchase_pricing'][$regular?'regular_net':'initial_net'])['gross_cents'];return $gross;
}
function pricing_cart_regular_gross(array $p): int {return pricing_cart_gross($p,true);}
function pricing_set_amount(array $p,int $net): array {$p['_options']=array_replace(product_options($p),['net_cents'=>$net]);$p['_contract_tax']=vat_from_net($net);$p['price_cents']=$p['_contract_tax']['gross_cents'];return $p;}
function pricing_component(array $p,array $pricing,int $original,int $term,int $initial,int $regular): array {
    $pricing=array_replace($pricing,['catalog_net'=>intdiv($original,max(1,$pricing['factor'])),'original_net'=>$original,'term_net'=>$term,'discount_net'=>$term-$initial,'initial_net'=>$initial,'regular_net'=>$regular]);$p['_purchase_pricing']=$pricing;$p['_contract_policy']=array_replace(product_contract_policy($p),['billing_months'=>$pricing['months']]);return pricing_set_amount($p,$initial);
}
function pricing_split_cart(array $configured,array $base,array $lines): array {
    $s=$configured['_purchase_pricing'];$base['billing']=$configured['billing'];$components=[(int)$base['_module_base_net']];foreach($lines as $l)$components[]=(int)$l['net_cents'];
    // Allocate integer cents once across the cart. The sum of components is always the advertised total.
    $termLeft=(int)$s['term_net'];$discountLeft=(int)$s['discount_net'];$originalLeft=array_sum($components)*$s['factor'];$out=[];
    foreach($components as $i=>$catalog){$original=$catalog*$s['factor'];$term=$i===array_key_last($components)?$termLeft:($originalLeft?intdiv($termLeft*$original+intdiv($originalLeft,2),$originalLeft):0);$term=max(0,min($termLeft,$term));$off=min($term,$discountLeft);if($s['coupon'] && $s['coupon']['kind']==='percent' && $i!==array_key_last($components))$off=min($discountLeft,intdiv($term*$s['coupon']['rules']['percent_bps']+5000,10000));$first=$term-$off;$regular=$s['coupon'] && $s['coupon']['rules']['duration']==='recurring'?$first:$term;
        $part=array_replace($s,['catalog_net'=>$catalog,'original_net'=>$original,'term_net'=>$term,'discount_net'=>$off,'initial_net'=>$first,'regular_net'=>$regular]);$out[]=$part;$termLeft-=$term;$discountLeft-=$off;$originalLeft-=$original;}
    $base=pricing_component($base,$out[0],$out[0]['original_net'],$out[0]['term_net'],$out[0]['initial_net'],$out[0]['regular_net']);
    foreach($lines as $i=>&$line)$line['purchase_pricing']=$out[$i+1];unset($line);return [$base,$lines];
}
function pricing_snapshot(array $order): ?array {return json_decode($order['configuration'],true)['purchase_pricing']??null;}
/** Quote eligibility only; checkout still reserves and verifies the stored coupon. */
function pricing_has_free_discount(array $p,int $customer): bool {
    $s=$p['_purchase_pricing']??null;
    if(!$s || (int)$s['initial_net']!==0 || (!$s['coupon'] && !$s['reward_id']))return false;
    if((int)$s['term_net']>0)return true;
    $coupon=$s['coupon']??[];$rules=$coupon['rules']??[];
    return !empty($s['test_only']) && !empty($rules['test_only'])
        && (int)$s['customer_id']===$customer && (int)($rules['customer_id']??0)===$customer
        && ($coupon['kind']??'')==='percent' && (int)($rules['percent_bps']??0)===10000
        && ($rules['duration']??'')==='recurring';
}
function pricing_order_view(array $order,?string $kind=null): array {
    $s=pricing_snapshot($order);if(!$s)return $order;$initial=one("SELECT state FROM shop_receivables WHERE order_id=? AND kind='initial'",[$order['id']]);$renew=$kind==='renewal' || ($kind===null && $initial && $initial['state']==='paid');$net=(int)$s[$renew?'regular_net':'initial_net'];$c=json_decode($order['configuration'],true);$c['tax']=vat_from_net($net);$c['pricing_period']=$renew?'renewal':'initial';$order['configuration']=json_encode($c);$order['amount_cents']=$c['tax']['gross_cents'];return $order;
}
function pricing_reserve(array $order,array $p): void {
    $s=$p['_purchase_pricing']??null;if(!$s)return;$lock=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
    if($s['coupon']){$row=one('SELECT * FROM shop_coupons WHERE id=?'.$lock,[$s['coupon']['id']]);if(!$row || (int)$row['revision']!==$s['coupon']['revision'])throw new RuntimeException('Der Gutschein wurde geändert. Bitte die Preise erneut prüfen.');coupon_validate($row,(int)$order['customer_id'],$p,(int)$s['qualifying_monthly_net'],(int)$order['id']);query('INSERT INTO shop_coupon_uses (order_id,coupon_id,customer_id,snapshot,created_ts) VALUES (?,?,?,?,?)',[$order['id'],$row['id'],$order['customer_id'],json_encode($s['coupon']),shop_now()]);}
    if($s['reward_id']){$r=one('SELECT * FROM shop_coupon_rewards WHERE id=?'.$lock,[$s['reward_id']]);promotion_checked_reward((int)$s['reward_id'],(int)$order['customer_id'],$p,(int)$s['original_net']);$q=query("UPDATE shop_coupon_rewards SET state='reserved',order_id=? WHERE id=? AND state='available' AND order_id IS NULL",[$order['id'],$r['id']]);if($q->rowCount()!==1)throw new RuntimeException('Dieser Zusatzpaket-Bonus ist bereits reserviert.');}
}
function promotion_checked_reward(int $id,int $customer,array $p,int $net,?int $existing=null): array {
    $r=one('SELECT * FROM shop_coupon_rewards WHERE id=? AND customer_id=?',[$id,$customer]);$source=$r?one('SELECT * FROM shop_orders WHERE id=? AND customer_id=?',[$r['source_order_id'],$customer]):null;
    if(!$r || (($r['state']!=='available' || $r['order_id']) && (!$existing || (int)$r['order_id']!==$existing || !in_array($r['state'],['reserved','used'],true))) || $r['category']!==$p['category'] || $net>(int)$r['cap_net_cents'] || !$source || !promotion_paid_source($source))throw new RuntimeException('Dieser Zusatzpaket-Bonus ist für deine Auswahl nicht verfügbar oder der Paketpreis überschreitet seinen Höchstwert.');return $r;
}
function promotion_paid_source(array $order): bool {return ($order['payment_status']==='paid' || zero_base_order($order)) && order_payment_allows_provisioning($order);}
function pricing_confirm_redemption(array $order): void {
    query("UPDATE shop_coupon_uses SET state='used' WHERE order_id=? AND state='reserved'",[$order['id']]);query("UPDATE shop_coupon_rewards SET state='used' WHERE order_id=? AND state='reserved'",[$order['id']]);$s=pricing_snapshot($order);
    if(!$s || ($s['coupon']['kind']??'')!=='bundle' || !promotion_paid_source($order))return;
    foreach(all('SELECT * FROM shop_module_intents WHERE parent_order_id=?',[$order['id']]) as $intent){$child=$intent['order_id']?one('SELECT * FROM shop_orders WHERE id=? AND customer_id=?',[$intent['order_id'],$order['customer_id']]):null;if(!$intent['first_settled'] || !$child || $child['payment_status']!=='paid' || !order_payment_allows_provisioning($child))return;}
    foreach(['domain'=>'domain_cap_net','hosting'=>'hosting_cap_net'] as $category=>$key){$cap=(int)$s['coupon']['rules'][$key];if(!$cap || one('SELECT id FROM shop_coupon_rewards WHERE source_order_id=? AND category=?',[$order['id'],$category]))continue;try{query('INSERT INTO shop_coupon_rewards (source_order_id,customer_id,category,cap_net_cents,created_ts) VALUES (?,?,?,?,?)',[$order['id'],$order['customer_id'],$category,$cap,shop_now()]);}catch(PDOException $e){if(!one('SELECT id FROM shop_coupon_rewards WHERE source_order_id=? AND category=?',[$order['id'],$category]))throw $e;}}
    customer_notification((int)$order['customer_id'],'coupon-rewards:'.$order['id'],'Deine kostenlosen Zusatzpakete sind verfügbar','Wähle deinen Domain- oder Webspace-Bonus im Kundenkonto. Der erste Abrechnungszeitraum ist bis zum vereinbarten Höchstwert kostenlos.','/konto/gutscheine');
}
function pricing_release_cancelled(array $order): void {
    if(!in_array($order['payment_status'],['cancelled','expired'],true) || one("SELECT id FROM shop_payments WHERE order_id=? AND status='completed'",[$order['id']]))return;
    query("UPDATE shop_coupon_uses SET state='released' WHERE order_id=? AND state='reserved'",[$order['id']]);query("UPDATE shop_coupon_rewards SET state='available',order_id=NULL WHERE order_id=? AND state='reserved'",[$order['id']]);
}
function module_purchase_pricing(array $p,int $customer,array $input): array {
    $code=coupon_code($input['coupon_code']??'');if(!$code)return $p;
    $intent=$p['_module_intent'];if($intent['order_id']){$order=one('SELECT * FROM shop_orders WHERE id=? AND customer_id=?',[$intent['order_id'],$customer]);$s=$order?pricing_snapshot($order):null;if(!$s || ($s['coupon']['code']??'')!==$code)throw new RuntimeException('Der Gutschein dieser Modulbestellung ist bereits festgelegt.');$p['_purchase_pricing']=$s;return $p;}
    if(!empty($p['_purchase_pricing']['coupon']) || !empty($p['_purchase_pricing']['reward_id']))throw new RuntimeException('Dieses Modul hat den Rabatt deiner Paketbestellung bereits erhalten. Ein weiterer Code ist nicht möglich.');
    $first=$p['_module_pricing']['initial']['net_cents'];$regular=$p['_module_pricing']['regular']['net_cents'];$clean=$p;unset($clean['_purchase_pricing']);$clean=purchase_pricing($clean,$customer,['coupon_code'=>$code]);$s=$clean['_purchase_pricing'];
    if($s['coupon']['kind']==='bundle')throw new RuntimeException('VPS-Aktionscodes gelten für neue VPS-Grundpakete, nicht für einzelne Zusatzmodule.');
    $s['regular_net']=$s['coupon']['rules']['duration']==='recurring'?$regular-promotion_reduction($regular,$s['coupon']):$regular;$s['qualifying_monthly_net']=intdiv($regular,max(1,billing_months($p['billing'])));$s['invoice_renewal']=$p['billing']==='months24';$clean['_purchase_pricing']=$s;$clean['_module_pricing']['initial']=vat_from_net((int)$s['initial_net']);$clean['_module_pricing']['regular']=vat_from_net((int)$s['regular_net']);return $clean;
}
