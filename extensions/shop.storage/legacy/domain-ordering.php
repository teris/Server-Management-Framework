<?php
/** Current general-availability offers use phase "ga" and pricingMode
 * "create-default". "gold" is an OVH offer tier, not a premium flag. */
function standard_domain_offer(array $offer): bool {
    if(($offer['action']??'')!=='create' || ($offer['orderable']??false)!==true || empty($offer['offerId']))return false;
    if(!in_array($offer['phase']??'normal',['normal','ga'],true))return false;
    if(isset($offer['duration']) && (!is_array($offer['duration']) || !in_array('P1Y',$offer['duration'],true)))return false;
    if(isset($offer['pricingMode']))return in_array($offer['pricingMode'],['create-default','default'],true);
    return empty($offer['offer']) || strtolower((string)$offer['offer'])==='gold';
}
function domain_checkout_offer(string $domain,array $profile): array {
    if(!$domain)throw new RuntimeException('Bitte eine Wunschdomain für die Neuregistrierung eintragen.');
    return domain_registration_offer((new OvhProvider())->availability($domain),$profile);
}
function check_domain_purchase_total(array $preview,int $maximum): void {
    $gross=$preview['prices']['withTax']??[];
    if(($gross['currencyCode']??'')!=='EUR' || !isset($gross['value']) || (int)round($gross['value']*100)>$maximum || $maximum<1)throw new RuntimeException('Die endgültige OVH-Bestellsumme überschreitet die freigegebene Grenze.');
}
/** Prepares and validates an OVH cart but NEVER checks out or pays it. */
function prepare_domain_cart(string $domain,array $profile,array $billing,string $legal): array {
    if(!preg_match('/^\+[1-9]\d{0,2}\.\d{4,15}$/D',$billing['phone']??''))throw new RuntimeException('Bitte die Telefonnummer für den Domaininhaber im internationalen OVH-Format eintragen, zum Beispiel +49.301234567.');
    if($legal!=='individual' && empty($billing['company']))throw new RuntimeException('Bitte für diese Rechtsform den Namen der Organisation im Kundenprofil eintragen.');
    $api=new OvhProvider();$cart=$api->call('POST','/order/cart',['ovhSubsidiary'=>integration_config('ovh')['subsidiary'],'description'=>'Server Management Domainvorbereitung','expire'=>gmdate('c',time()+86400)]);$cartId=$cart['cartId']??'';
    if(!$cartId)throw new ProviderFailure('ovh');$path='/order/cart/'.rawurlencode($cartId);$api->call('POST',$path.'/assign');
    $offers=$api->call('GET',$path.'/domain?domain='.rawurlencode($domain));$availability=OvhProvider::parseAvailability($domain,(array)$offers);$offer=$availability['offer'];
    if(!$offer || !standard_domain_offer($offer))throw new RuntimeException('Die Domain benötigt eine persönliche Angebotsprüfung.');
    $item=$api->call('POST',$path.'/domain',['domain'=>$domain,'duration'=>'P1Y','offerId'=>$offer['offerId'],'quantity'=>1]);$itemId=$item['itemId']??0;if(!$itemId)throw new ProviderFailure('ovh');
    $requirements=$api->call('GET',$path.'/item/'.$itemId.'/requiredConfiguration');$contactId=null;
    foreach((array)$requirements as $requirement){
        $label=$requirement['label'];$value=$profile['domain_configuration'][$label]??null;
        if($value===null && !empty($requirement['required']))throw new RuntimeException('Für diese Domain fehlen zusätzliche Registrierungsangaben. Bitte ein Angebot anfragen.');
        if($value==='{customer_contact}'){
            if(!$contactId){
                $contact=$api->call('POST','/me/contact',['firstName'=>$billing['first_name'],'lastName'=>$billing['last_name'],'email'=>$billing['email'],'phone'=>$billing['phone'],'legalForm'=>$legal,'organisationName'=>$legal==='individual'?null:$billing['company'],'language'=>'de_DE','address'=>['line1'=>$billing['address'],'city'=>$billing['city'],'zip'=>$billing['postal_code'],'country'=>strtoupper($billing['country'])]]);
                $contactId=$contact['id']??0;if(!$contactId)throw new ProviderFailure('ovh');
            }
            $value='/me/contact/'.$contactId;
        }
        if($value!==null)$api->call('POST',$path.'/item/'.$itemId.'/configuration',['label'=>$label,'value'=>(string)$value]);
    }
    $preview=$api->call('GET',$path.'/checkout');check_domain_purchase_total($preview,(int)$profile['max_purchase_cents']);
    return ['cart_id'=>$cartId,'item_id'=>$itemId,'expires'=>time()+82800];
}
