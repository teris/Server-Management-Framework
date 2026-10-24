<?php
function automation_profile(int $productId): ?array { $row=one('SELECT * FROM shop_product_automation WHERE product_id=?',[$productId]);if($row)$row['settings']=json_decode($row['profile'],true);return $row; }
function checkout_ready(array $p): bool {
    return !package_blockers($p);
}
function checkout_fingerprint(array $p): string {$pricing=$p['_purchase_pricing']??null;$template=$p['_automation']['settings']['server_template']??null;$p=$p['_base_product']??$p;if($template){$p['_automation']=checkout_automation($p);$p['_automation']['settings']['server_template']=$template;}return hash('sha256',json_encode([$p['id'],$p['name'],$p['price_cents'],$p['billing'],checkout_automation($p),integration_identity('paypal'),checkout_documents_for($p),order_commerce_snapshot($p),$p['_quote_id']??0,$p['_domain_tld_id']??0,module_catalog_hash($p),$pricing,prepayment_options((int)$p['id'])]));}
function approval_redirect(string $url): never {
    $url=checked_paypal_approval($url);
    header('Location: '.$url,true,303);exit;
}
function checked_paypal_approval(string $url): string {
    $parts=parse_url($url);
    if(($parts['scheme']??'')!=='https' || !in_array($parts['host']??'',['www.paypal.com','www.sandbox.paypal.com'],true) || isset($parts['user']))throw new RuntimeException('PayPal hat keine gültige Freigabe-Adresse geliefert.');
    return $url;
}
function paypal_plan(array $order,PaypalProvider $paypal): string {
    $config=integration_config('paypal');
    $fingerprint=hash('sha256',implode('|',[$paypal->mode(),hash('sha256',$config['client_id']),$order['product_id'],$order['product_name'],$order['amount_cents'],$order['billing']]));
    $row=one('SELECT plan_id FROM shop_paypal_plans WHERE fingerprint=?',[$fingerprint]);if($row)return $row['plan_id'];
    $product=$paypal->call('POST','/v1/catalogs/products',['name'=>$order['product_name'],'type'=>'SERVICE','category'=>'SOFTWARE'],'prod-'.substr($fingerprint,0,32));
    if(empty($product['id']))throw new ProviderFailure('paypal');
    $plan=$paypal->call('POST','/v1/billing/plans',[
      'product_id'=>$product['id'],'name'=>$order['product_name'].' · '.cycle($order['billing']),'status'=>'ACTIVE',
      'billing_cycles'=>[['frequency'=>['interval_unit'=>$order['billing']==='year'?'YEAR':'MONTH','interval_count'=>$order['billing']==='year'?1:billing_months($order['billing'])],'tenure_type'=>'REGULAR','sequence'=>1,'total_cycles'=>0,'pricing_scheme'=>['fixed_price'=>['value'=>number_format($order['amount_cents']/100,2,'.',''),'currency_code'=>'EUR']]]],
      'payment_preferences'=>['auto_bill_outstanding'=>false,'payment_failure_threshold'=>1],
    ],'plan-'.substr($fingerprint,0,32));
    if(empty($plan['id']))throw new ProviderFailure('paypal');
    try{query('INSERT INTO shop_paypal_plans (fingerprint,product_id,paypal_mode,plan_id) VALUES (?,?,?,?)',[$fingerprint,$order['product_id'],$paypal->mode(),$plan['id']]);}catch(PDOException $e){if(!one('SELECT fingerprint FROM shop_paypal_plans WHERE fingerprint=?',[$fingerprint]))throw $e;}
    return $plan['id'];
}
function start_checkout(array $customer,array $p,string $domain,string $checkoutKey,array $options=[]): array {
    assert_customer_can_order((int)$customer['id']);
    $options['checkout_key']=$checkoutKey;
    if(empty($options['quote_id']) && in_array($p['category'],['hosting','server'],true))return start_module_cart($customer,purchase_pricing(selected_server_template(configured_product($p,$options['modules']??[]),$options['server_template']??0),(int)$customer['id'],$options),$domain,$checkoutKey,$options);
    return start_checkout_order($customer,$p,$domain,$checkoutKey,$options);
}
function start_checkout_order(array $customer,array $p,string $domain,string $checkoutKey,array $options=[],?array $component=null): array {
    shop_require_module('shop.orders');shop_require_module('shop.payments');
    if(empty(integration_config('paypal')['merchant_id']))throw new RuntimeException('PayPal-Händler-ID für diese Umgebung fehlt.');
    assert_customer_can_order((int)$customer['id']);
    if(!isset($options['terms'],$options['privacy'],$options['invoice_pdf']))throw new RuntimeException('Bitte bestätige Vertragsbedingungen, Datenschutz und den Rechnungserhalt als PDF.');
    $quoteId=$component?0:(int)($options['quote_id']??0);if($quoteId)$p=quote_checkout_product($quoteId,(int)$customer['id']);
    if(!$quoteId)$p=domain_select_product($p,$domain);
    if(!$component)$p=purchase_pricing(selected_server_template(configured_product($p,$options['modules']??[]),$options['server_template']??0),(int)$customer['id'],$options);
    $pricing=$p['_purchase_pricing']??null;
    $zeroBase=(int)($p['price_cents']??0)===0 && ($component['configuration']['zero_base_version']??0)===1;
    $freeDiscount=!$zeroBase && pricing_has_free_discount($p,(int)$customer['id']);
    if($p['price_cents']!==null && (int)$p['price_cents']===0 && !$zeroBase && !$freeDiscount){
        if(!product_has_customer_modules($p))throw new RuntimeException('Diese Auswahl enthält ausschließlich Inklusivleistungen und kostet bereits 0 €. Für eine kostenlose Testbestellung gib bitte deinen persönlichen Testgutschein ein und bestätige „Auswahl & Rabatt prüfen“.');
        throw new RuntimeException('Bitte mindestens ein kostenpflichtiges Modul auswählen. Der Grundpreis dieses Pakets beträgt 0 €. Für eine kostenlose Testbestellung kannst du deinen persönlichen Testgutschein anwenden.');
    }
    if(!checkout_ready($p))throw new RuntimeException('Dieses Paket wird noch für den Direktkauf vorbereitet. Bitte versuche es später erneut.');
    if(!hash_equals($_SESSION['checkout_key']??'',$checkoutKey))throw new RuntimeException('Dieser Bestellvorgang ist abgelaufen. Bitte starte ihn erneut.');
    $offer=$component['offer']??checkout_fingerprint($p);
    if(!hash_equals($_SESSION['checkout_offer']??'',$offer))throw new RuntimeException('Preis oder Tarif wurden inzwischen aktualisiert. Bitte die Bestellung neu öffnen und prüfen.');
    $automation=checkout_automation($p);$profile=$automation['settings'];$domain=valid_domain($domain);
    if(in_array($automation['provider'],['ispconfig','ovh'],true) && !$domain)throw new RuntimeException('Für dieses Produkt ist eine Domain erforderlich.');
    if($quoteId){$quote=one('SELECT * FROM shop_quotes WHERE id=? AND customer_id=?',[$quoteId,$customer['id']]);if($quote['domain'] && $domain!==$quote['domain'])throw new RuntimeException('Bitte die Domain aus deinem Angebot unverändert übernehmen.');}
    $existingId=$component['order_id']??(($quote['order_id']??null)?:($_SESSION['checkout_order'][$checkoutKey]??null));$order=null;
    if($existingId){
        $order=one('SELECT * FROM shop_orders WHERE id=? AND customer_id=?',[$existingId,$customer['id']]);$oldConfig=$order?json_decode($order['configuration'],true):[];
        if(!$order || $order['domain']!==$domain || (int)$order['product_id']!==(int)$p['id'] || (int)($oldConfig['quote_id']??0)!==$quoteId || !hash_equals($oldConfig['checkout_fingerprint']??'',$offer))throw new RuntimeException('Dieser Bestellvorgang gehört zu einer anderen Auswahl. Bitte öffne die Bestellung erneut.');
        if(!hash_equals($oldConfig['module_key']??'', $p['_module_key']??''))throw new RuntimeException('Die Modulauswahl dieser Bestellung ist bereits festgelegt. Bitte einen neuen Bestellvorgang öffnen.');
        if($freeDiscount && $order['payment_kind']==='no_charge')return pricing_accept_free($order);
        if($zeroBase && zero_base_order($order)){module_register_checkout($order,$component);return accept_zero_base_checkout($order);}
        if($order['payment_status']!=='pending')throw new RuntimeException('Dieser Bestellvorgang wurde bereits verarbeitet. Bitte öffne deine Bestellübersicht.');
        if($automation['provider']==='ovh')domain_checkout_offer($domain,$profile);
        if($order['approval_url']){module_register_checkout($order,$component);initial_order_invoice($order);commerce_event('order:'.$order['id'],'order.created',['order_id'=>(int)$order['id']]);return $order;}
        $profile=json_decode($order['profile'],true);
    }
    if(isset($options['billing']) && !is_array($options['billing']))throw new RuntimeException('Bitte gültige Rechnungsdaten eintragen.');
    $billingValues=billing_input($options['billing']??array_intersect_key($customer,billing_fields()),true);
    $customer=array_replace($customer,$billingValues);
    if(isset($options['billing']))query('UPDATE customers SET company=?,phone=?,address=?,postal_code=?,city=?,country=? WHERE id=? AND status=\'active\'',[$customer['company'],$customer['phone'],$customer['address'],$customer['postal_code'],$customer['city'],$customer['country'],$customer['id']]);
    if($automation['provider']==='ovh')$profile['domain_offer']=domain_checkout_offer($domain,$profile);
    $hostingDomain=$automation['provider']==='ispconfig' && empty($component['intent_id'])?hosting_domain_snapshot((int)$customer['id'],$domain,$options):null;
    $paypal=new PaypalProvider();
    $billing=array_intersect_key($customer,array_flip(['first_name','last_name','email','company','phone','address','postal_code','city','country']));
    $configuration=['module_key'=>$p['_module_key']??'','ordered_at'=>shop_now(),'paypal_connection_id'=>integration_identity('paypal'),'paypal_merchant_id'=>(integration_config('paypal')['merchant_id']??''),'domain_tld_id'=>$p['_domain_tld_id']??null,'domain_extension'=>$p['_domain_extension']??null,'billing'=>$billing,'quote_id'=>$quoteId,'invoice_pdf_consent'=>true,'consented_at'=>gmdate('c'),'checkout_fingerprint'=>$offer]+order_commerce_snapshot($p)+($component['configuration']??[]);
    if($hostingDomain)$configuration['hosting_domain']=$hostingDomain;
    if($automation['provider']==='ovh'){
        $legal=(string)($options['legal_form']??'');
        if(!in_array($legal,['individual','corporation','association','other'],true) || !isset($options['legal_age']))throw new RuntimeException('Bitte Rechtsform und erforderliche Inhaberangaben für die Domain bestätigen.');
        $configuration['legal_form']=$legal;
        if($paypal->mode()==='live' && !$order && empty($pricing['test_only']))$profile['prepared_cart']=prepare_domain_cart($domain,$profile,$billing,$legal);
    }
    $documents=checkout_documents_for($p);$profile['terms_url']=$documents['terms_url'];$profile['withdrawal_url']=$documents['withdrawal_url'];
    $kind=$zeroBase || ($freeDiscount && ((int)$pricing['regular_net']===0 || $p['billing']==='once' || !empty($pricing['invoice_renewal'])))?'no_charge':($p['billing']==='once' || !empty($pricing['invoice_renewal'])?'order':'subscription');$reference='OC-B-'.date('ym').'-'.strtoupper(bin2hex(random_bytes(6)));
    if(!$order){
        db()->beginTransaction();try{
            assert_customer_can_order((int)$customer['id'],true);
            if(!empty($component['intent_id'])){$lock=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';$mi=one('SELECT * FROM shop_module_intents WHERE id=? AND customer_id=?'.$lock,[$component['intent_id'],$customer['id']]);if(!$mi || $mi['order_id'])throw new RuntimeException('Dieses Zusatzmodul wurde inzwischen bestellt. Bitte öffne seine Bestellung.');}
            if($quoteId){$lock=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';$current=one('SELECT * FROM shop_quotes WHERE id=? AND customer_id=?'.$lock,[$quoteId,$customer['id']]);if(!$current || $current['status']!=='sent' || $current['order_id'] || strtotime($current['expires_at'].' UTC')<=time())throw new RuntimeException('Dieses Angebot wurde bereits bestellt oder ist nicht mehr verfügbar. Bitte öffne deine Bestellungen.');}
            query('INSERT INTO shop_orders (reference,customer_id,product_id,product_name,amount_cents,billing,domain,configuration,provider,profile,paypal_mode,payment_kind) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',[$reference,$customer['id'],$p['id'],$p['name'],$p['price_cents'],$p['billing'],$domain,json_encode($configuration),$automation['provider'],json_encode($profile),$paypal->mode(),$kind]);
            $order=one('SELECT * FROM shop_orders WHERE id=?',[db()->lastInsertId()]);
            if($pricing && empty($pricing['redemption_parent']))pricing_reserve($order,$p);
            if(!empty($component['intent_id']) && $pricing)query('UPDATE shop_module_intents SET initial_net_cents=?,initial_gross_cents=?,recurring_net_cents=?,recurring_gross_cents=? WHERE id=? AND order_id IS NULL',[(int)$pricing['initial_net'],vat_from_net((int)$pricing['initial_net'])['gross_cents'],(int)$pricing['regular_net'],vat_from_net((int)$pricing['regular_net'])['gross_cents'],$component['intent_id']]);
            if($quoteId)query("UPDATE shop_quotes SET order_id=? WHERE id=? AND order_id IS NULL",[$order['id'],$quoteId]);db()->commit();
        }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    }
    $reference=$order['reference'];$_SESSION['checkout_order'][$checkoutKey]=$order['id'];
    module_register_checkout($order,$component);
    ensure_hosting_domain_verification($order);
    if($freeDiscount && $kind==='no_charge')return pricing_accept_free($order);
    if($zeroBase)return accept_zero_base_checkout($order);
    $return=Config::FRONTPANEL_SITE_URL.'/paypal/rueckkehr?bestellung='.$order['reference'];$cancel=Config::FRONTPANEL_SITE_URL.'/konto/bestellungen?id='.$order['id'].'&abgebrochen=1';
    try{
        if($kind==='order'){
            $reply=$paypal->call('POST','/v2/checkout/orders',['intent'=>'CAPTURE','purchase_units'=>[['reference_id'=>$reference,'custom_id'=>$reference,'description'=>$p['name'],'amount'=>['currency_code'=>'EUR','value'=>number_format($p['price_cents']/100,2,'.','')]]],'payment_source'=>['paypal'=>['experience_context'=>['brand_name'=>'Server Management','locale'=>'de-DE','shipping_preference'=>'NO_SHIPPING','user_action'=>'PAY_NOW','return_url'=>$return,'cancel_url'=>$cancel]]]],'create-'.$reference);
        }else{
            $intent=module_order_intent((int)$order['id']);$plan=$pricing?pricing_paypal_plan($order,$paypal,$intent):($intent?module_paypal_plan($order,$paypal,$intent):paypal_plan($order,$paypal));query('UPDATE shop_orders SET plan_id=? WHERE id=?',[$plan,$order['id']]);
            $subscription=['plan_id'=>$plan,'custom_id'=>$reference,'application_context'=>['brand_name'=>'Server Management','locale'=>'de-DE','shipping_preference'=>'NO_SHIPPING','user_action'=>'SUBSCRIBE_NOW','return_url'=>$return,'cancel_url'=>$cancel]];if($intent)$subscription['start_time']=gmdate('Y-m-d\TH:i:s\Z',(int)$intent['initial_until']);
            $reply=$paypal->call('POST','/v1/billing/subscriptions',$subscription,'create-'.$reference);
        }
        $approval=null;foreach($reply['links']??[] as $link)if(in_array($link['rel']??'',['approve','payer-action'],true))$approval=$link['href'];
        if(empty($reply['id']) || !$approval)throw new ProviderFailure('paypal');
        $approval=checked_paypal_approval($approval);
        query('UPDATE shop_orders SET payment_id=?,approval_url=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$reply['id'],$approval,$order['id']]);
        initial_order_invoice(one('SELECT * FROM shop_orders WHERE id=?',[$order['id']]));
        commerce_event('order:'.$order['id'],'order.created',['order_id'=>(int)$order['id']]);
        audit_event('checkout.created',$reference);return one('SELECT * FROM shop_orders WHERE id=?',[$order['id']]);
    }catch(Throwable $e){audit_event('checkout.failed',$reference,'failed');throw $e;}
}
function payment_amount_cents(string $amount): int {
    if(!preg_match('/^\d{1,9}(?:\.\d{1,2})?$/D',$amount))throw new RuntimeException('Ungültiger Zahlungsbetrag.');
    [$whole,$fraction]=array_pad(explode('.',$amount,2),2,'');return (int)$whole*100+(int)str_pad($fraction,2,'0');
}
function record_paid(array $order,string $transaction,int $amount,string $currency,?string $nextBilling=null,array $context=[]): void {
    if($previous=one('SELECT * FROM shop_payments WHERE paypal_mode=? AND transaction_id=?',[$order['paypal_mode'],$transaction])){if((int)$previous['order_id']!==(int)$order['id'] || (int)$previous['amount_cents']!==$amount || $previous['currency']!==$currency)throw new RuntimeException('Zahlungsreferenz ist bereits vergeben.');return;}
    $order=module_order_view($order);
    if(!$transaction || $amount!==(int)$order['amount_cents'] || $currency!==$order['currency'])throw new RuntimeException('Die bestätigte Zahlung stimmt nicht mit der Bestellung überein.');
    $paidAt=(int)($context['paid_at']??shop_now());if($paidAt<=0 || $paidAt>shop_now()+300)throw new RuntimeException('Der bestätigte Zahlungszeitpunkt ist ungültig.');$ownsTransaction=false;db()->beginTransaction();
    try{
        $lock=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';$fresh=one('SELECT * FROM shop_orders WHERE id=?'.$lock,[$order['id']]);
        if($fresh)$fresh=module_order_view($fresh);
        if(!$fresh || $amount!==(int)$fresh['amount_cents'] || $currency!==$fresh['currency'])throw new RuntimeException('Die Zahlungszuordnung wurde inzwischen geändert.');$order=$fresh;
        $existing=one('SELECT * FROM shop_payments WHERE paypal_mode=? AND transaction_id=?',[$order['paypal_mode'],$transaction]);
        if($existing){if((int)$existing['order_id']!==(int)$order['id'])throw new RuntimeException('Zahlungsreferenz ist bereits vergeben.');db()->commit();return;}
        $contract=one('SELECT * FROM shop_contracts WHERE order_id=?',[$order['id']]);$closed=contract_closed($contract) || ($contract && $contract['state']==='cancel_requested' && (int)$contract['cancel_at']>0 && (int)$contract['cancel_at']<=shop_now()) || in_array($order['fulfillment_status'],['cancelled','ended'],true);
        $due=isset($context['invoice_id'])?one('SELECT * FROM shop_receivables WHERE invoice_id=? AND order_id=?',[(int)$context['invoice_id'],$order['id']]):one("SELECT * FROM shop_receivables WHERE order_id=? AND state='open' ORDER BY due_ts,invoice_id LIMIT 1",[$order['id']]);
        $review=test_order($order) || ($due && $due['state']!=='open') || ($closed && (!$due || $due['kind']==='initial')) || ($contract && (int)$contract['paid_until']>$paidAt+2*86400 && !$due);
        $intent=module_order_intent((int)$order['id']);if($intent && !$intent['first_settled'] && $paidAt>=(int)$intent['initial_until'])$review=true;if($intent && !$intent['applied_at']){$pc=one('SELECT state,cancel_at FROM shop_contracts WHERE order_id=?',[$intent['parent_order_id']]);if(!$pc || contract_closed($pc) || (int)$pc['cancel_at']>0)$review=true;}
        query("INSERT INTO shop_payments (order_id,paypal_mode,transaction_id,amount_cents,currency,status,received_at) VALUES (?,?,?,?,?,'completed',?)",[$order['id'],$order['paypal_mode'],$transaction,$amount,$currency,gmdate('Y-m-d H:i:s',$paidAt)]);$paymentId=(int)db()->lastInsertId();
        if(isset($context['attestation'])){$a=$context['attestation'];query('INSERT INTO shop_payment_attestations (order_id,invoice_id,payment_id,admin_id,source,reference,reason,token,allow_provisioning) VALUES (?,?,?,?,?,?,?,?,?)',[$order['id'],$a['invoice_id'],$paymentId,$a['admin_id'],$a['source'],$a['reference'],$a['reason'],$a['token'],$a['allow_provisioning']]);}

        if($review){
            query("UPDATE shop_orders SET payment_status='review',updated_at=CURRENT_TIMESTAMP WHERE id=?",[$order['id']]);commerce_event('payment-review:'.$paymentId,'payment.review',['order_id'=>(int)$order['id'],'payment_id'=>$paymentId]);
        }else{
            $settled=settle_open_invoice($order,$paymentId,$paidAt,isset($context['invoice_id'])?(int)$context['invoice_id']:null);
            module_note_payment($order,$settled);
            contract_payment_update($order,$paidAt,$nextBilling,$settled);
            commerce_event('payment:'.$paymentId,'payment.confirmed',['order_id'=>(int)$order['id'],'payment_id'=>$paymentId,'next_billing_at'=>$nextBilling]);
            query("UPDATE shop_orders SET payment_status='paid',renewal_status=?,next_billing_at=?,updated_at=CURRENT_TIMESTAMP WHERE id=?",[$order['payment_kind']==='subscription'?'active':'',$nextBilling,$order['id']]);
            if(!$closed && !one('SELECT id FROM shop_jobs WHERE order_id=?',[$order['id']])){
                $sandbox=$order['paypal_mode']!=='live';query('INSERT INTO shop_jobs (order_id,state,payload) VALUES (?,?,?)',[$order['id'],$sandbox?'sandbox':'queued','{}']);query('UPDATE shop_orders SET fulfillment_status=? WHERE id=?',[$sandbox?'sandbox':'queued',$order['id']]);
            }
        }
        db()->commit();$ownsTransaction=true;
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    if($ownsTransaction){sync_zero_base_for_module($order);pricing_confirm_redemption(one('SELECT * FROM shop_orders WHERE id=?',[$order['id']]));if($i=module_order_intent((int)$order['id']))pricing_confirm_redemption(one('SELECT * FROM shop_orders WHERE id=?',[$i['parent_order_id']]));}
    if($ownsTransaction)audit_event($review?'payment.needs_review':'payment.confirmed',$order['reference'],$review?'review':'ok');
}
function confirm_paypal_return(array $order,string $token): void {
    $config=integration_config('paypal');if($order['paypal_mode']!==$config['mode'])throw new RuntimeException('Die PayPal-Umgebung wurde seit dieser Bestellung geändert. Bitte den Support kontaktieren.');
    if($order['payment_status']==='paid')return;
    if(in_array($order['payment_status'],['cancelled','expired'],true))throw new RuntimeException('Diese Bestellung ist bereits storniert. Es wird keine Zahlung mehr eingezogen.');
    if(empty($order['payment_id']))throw new RuntimeException('Für diese Bestellung wurde noch keine PayPal-Zahlung angelegt.');
    $paypal=new PaypalProvider();
    if($order['payment_kind']==='subscription'){
        $subscription=$paypal->call('GET','/v1/billing/subscriptions/'.rawurlencode($order['payment_id']));
        if(($subscription['custom_id']??'')!==$order['reference'] || ($subscription['plan_id']??'')!==$order['plan_id'])throw new RuntimeException('PayPal-Abonnement konnte nicht zugeordnet werden.');
        // ACTIVATED is not evidence of a collected payment. Only a verified sale may provision.
        reconcile_paypal_order($order);return;
    }
    if(!hash_equals($order['payment_id'], $token))throw new RuntimeException('Die PayPal-Zahlung gehört nicht zu dieser Bestellung.');
    $reply=$paypal->call('GET','/v2/checkout/orders/'.rawurlencode($order['payment_id']));
    if(($reply['status']??'')!=='COMPLETED')$reply=$paypal->call('POST','/v2/checkout/orders/'.rawurlencode($order['payment_id']).'/capture',[],'capture-'.$order['reference']);
    accept_capture($order,$reply);
}
function accept_capture(array $order,array $reply): void {
    if(($reply['id']??'')!==$order['payment_id'] || ($reply['status']??'')!=='COMPLETED' || count($reply['purchase_units']??[])!==1)throw new RuntimeException('PayPal hat die Zahlung noch nicht bestätigt.');
    $unit=$reply['purchase_units'][0];
    $snapshot=json_decode($order['configuration'],true,64,JSON_THROW_ON_ERROR);
    $merchant=$snapshot['paypal_merchant_id']??'';
    if(!is_string($merchant)||$merchant===''||!hash_equals($merchant,(string)($unit['payee']['merchant_id']??'')))throw new RuntimeException('PayPal-Zahlungsempfänger muss geprüft werden.');
    if(($unit['custom_id']??'')!==$order['reference'])throw new RuntimeException('Die Zahlungsreferenz stimmt nicht überein.');
    $captures=$unit['payments']['captures']??[];
    if(count($captures)!==1 || ($captures[0]['status']??'')!=='COMPLETED')throw new RuntimeException('PayPal hat die Zahlung noch nicht vollständig bestätigt.');
    $capture=$captures[0];record_paid($order,(string)$capture['id'],payment_amount_cents((string)($capture['amount']['value']??'')),(string)($capture['amount']['currency_code']??''));
}
function process_paypal_event(array $event,PaypalProvider $paypal): void {
    $type=(string)($event['event_type']??'');$resource=$event['resource']??[];$mode=$paypal->mode();$key=$mode.':'.($event['id']??'');
    if(empty($event['id']) || strlen($key)>180)throw new RuntimeException('Ungültiges Zahlungsereignis.');
    if(one('SELECT event_key FROM shop_webhook_events WHERE event_key=?',[$key]))return;
    if($type==='PAYMENT.CAPTURE.COMPLETED'){
        $id=$resource['supplementary_data']['related_ids']['order_id']??'';
        $mapping=one('SELECT * FROM shop_invoice_paypal WHERE paypal_mode=? AND payment_id=?',[$mode,$id]);
        if($mapping){$r=one('SELECT * FROM shop_receivables WHERE invoice_id=?',[$mapping['invoice_id']]);$mappedOrder=one('SELECT * FROM shop_orders WHERE id=?',[$r['order_id']]);accept_invoice_checkout($mappedOrder,$r,$mapping,$paypal->call('GET','/v2/checkout/orders/'.rawurlencode($id)),$paypal);}
        $order=one("SELECT * FROM shop_orders WHERE paypal_mode=? AND payment_id=? AND payment_kind='order'",[$mode,$id]);
        if($order)accept_capture($order,$paypal->call('GET','/v2/checkout/orders/'.rawurlencode($id)));
    }elseif($type==='CHECKOUT.ORDER.APPROVED'){
        $id=$resource['id']??'';$mapping=one('SELECT p.*,r.order_id FROM shop_invoice_paypal p JOIN shop_receivables r ON r.invoice_id=p.invoice_id WHERE p.paypal_mode=? AND p.payment_id=?',[$mode,$id]);
        if($mapping){$o=one('SELECT * FROM shop_orders WHERE id=?',[$mapping['order_id']]);confirm_invoice_checkout((int)$mapping['invoice_id'],(int)$o['customer_id'],$id);}else{$o=one("SELECT * FROM shop_orders WHERE paypal_mode=? AND payment_id=? AND payment_kind='order'",[$mode,$id]);if($o)reconcile_paypal_order($o);}
    }elseif($type==='PAYMENT.SALE.COMPLETED'){
        $id=$resource['billing_agreement_id']??'';$order=one("SELECT * FROM shop_orders WHERE paypal_mode=? AND payment_id=? AND payment_kind='subscription'",[$mode,$id]);
        if($order){
            verify_subscription_sale($order,$paypal,(string)($resource['id']??''),$resource['create_time']??null);
        }
    }elseif(str_starts_with($type,'BILLING.SUBSCRIPTION.')){
        $id=$resource['id']??'';$order=one("SELECT * FROM shop_orders WHERE paypal_mode=? AND payment_id=? AND payment_kind='subscription'",[$mode,$id]);
        if($order){$s=checked_subscription($order,$paypal);$renewal=$type==='BILLING.SUBSCRIPTION.PAYMENT.FAILED'?'payment_failed':strtolower($s['status']??'');query('UPDATE shop_orders SET renewal_status=?,next_billing_at=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$renewal,$s['billing_info']['next_billing_time']??null,$order['id']]);if($renewal==='payment_failed')query("UPDATE shop_orders SET payment_status='review' WHERE id=?",[$order['id']]);elseif(($ps=pricing_snapshot($order)) && (int)$ps['initial_net']===0 && !one('SELECT order_id FROM shop_free_entitlements WHERE order_id=?',[$order['id']]) && ($s['status']??'')==='ACTIVE')pricing_accept_free($order,$s);}
    }elseif(in_array($type,['PAYMENT.CAPTURE.REFUNDED','PAYMENT.SALE.REFUNDED','PAYMENT.SALE.REVERSED','PAYMENT.CAPTURE.REVERSED'],true)){
        $transaction=$resource['sale_id']??$resource['supplementary_data']['related_ids']['capture_id']??'';
        if(!$transaction)foreach($resource['links']??[] as $link)if(($link['rel']??'')==='up' && preg_match('#/v2/payments/captures/([A-Za-z0-9-]+)$#',$link['href']??'',$match))$transaction=$match[1];
        $payment=one('SELECT * FROM shop_payments WHERE paypal_mode=? AND transaction_id=?',[$mode,$transaction]);
        if($payment){query("UPDATE shop_orders SET payment_status='review',updated_at=CURRENT_TIMESTAMP WHERE id=?",[$payment['order_id']]);query("UPDATE shop_jobs SET state='needs_action',last_error='Zahlung muss geprüft werden.' WHERE order_id=? AND state NOT IN ('done','sandbox')",[$payment['order_id']]);audit_event('payment.review',(string)$payment['order_id'],'review');}
    }
    try{query('INSERT INTO shop_webhook_events (event_key,event_type) VALUES (?,?)',[$key,$type]);}catch(PDOException $e){if(!one('SELECT event_key FROM shop_webhook_events WHERE event_key=?',[$key]))throw $e;}
}
