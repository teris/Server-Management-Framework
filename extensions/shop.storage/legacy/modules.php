<?php
function shop_modules(): array {
    $links=[];
    foreach(SMF\Core\ShopSettings::get('direct_links') as $link)$links[]=['name'=>$link['label'],'label'=>'DIREKTZUGANG','icon'=>'external','description'=>'Zugang zum Dienst','url'=>$link['url'],'external'=>true];
    if(shop_feature('product.vps'))$links[]=['name'=>'Virtuelle Server','label'=>'SERVER','icon'=>'server','description'=>'Deine zugeordneten Server verwalten.','url'=>'/konto/server','external'=>false];
    return $links;
}
