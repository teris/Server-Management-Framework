<?php
function billing_fields(): array {
    return [
        'company'=>['Firma (optional)','organization',255],
        'phone'=>['Telefon','tel',50],
        'address'=>['Straße und Hausnummer','street-address',255],
        'postal_code'=>['Postleitzahl','postal-code',20],
        'city'=>['Ort','address-level2',100],
        'country'=>['Land','country',2],
    ];
}
function billing_countries(): array {
    static $countries=null;
    return $countries??=require __DIR__.'/countries.php';
}
function billing_country(string $value): string {
    $value=preg_replace('/\s+/u',' ',trim($value));
    if($value===null)throw new RuntimeException('Bitte wähle im Feld „Land“ ein gültiges Land aus.');
    $value=trim($value);
    if($value==='')return '';
    if(strlen($value)>512)throw new RuntimeException('Bitte wähle im Feld „Land“ ein gültiges Land aus.');
    $countries=billing_countries();$code=strtoupper($value);
    if(isset($countries[$code]))return $code;
    if(in_array($code,['UK','U.K.'],true))return 'GB';
    foreach($countries as $code=>$country){
        foreach([$country['alpha3'],...$country['aliases']] as $alias)if(preg_match('/\A'.preg_quote($alias,'/').'\z/iu',$value))return $code;
    }
    throw new RuntimeException('Bitte wähle im Feld „Land“ ein gültiges Land aus.');
}
function billing_input(array $input,bool $required=false): array {
    $result=[];
    foreach(billing_fields() as $key=>[$caption,$autocomplete,$maximum]){
        $value=$input[$key]??'';
        if(!is_string($value))throw new RuntimeException('Bitte prüfe das Feld „'.$caption.'“.');
        $value=trim($value);
        if(!preg_match('//u',$value))throw new RuntimeException('Bitte trage im Feld „'.$caption.'“ gültigen Text ein.');
        if($key==='country')$value=billing_country($value);
        // HTML maxlength counts characters, not the bytes used by UTF-8 umlauts.
        if(preg_match_all('/./us',$value)>$maximum)throw new RuntimeException('Das Feld „'.$caption.'“ darf höchstens '.$maximum.' Zeichen enthalten.');
        if($required && in_array($key,['address','postal_code','city','country'],true) && $value==='')throw new RuntimeException('Bitte fülle das Feld „'.$caption.'“ für die Rechnungsanschrift aus.');
        $result[$key]=$value;
    }
    return $result;
}
