<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/framework.php';
require_once dirname(__DIR__).'/src/sys.conf.php';
HttpSecurity::cliBoundary();
$options=getopt('',['list','install:','enable:','disable:','shop']);
$registry=SMF\Core\Kernel::boot()->modules();
$lock=fopen(HttpSecurity::privateDirectory('locks').'/modules.lock','c');
if(!$lock || !flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Module operation in progress');
try {
    if(isset($options['shop'])) {
        foreach(['shop.storage','shop.catalog','shop.portal','shop.orders','shop.payments','shop.provisioning','shop.billing','shop.contracts','shop.communications','shop.promotions','shop.subscriptions'] as $id){$registry->install($id);$registry->enable($id);}
    }
    foreach(['install','enable','disable']as$operation)if(isset($options[$operation]))$registry->$operation($options[$operation]);
    $out=[];foreach($registry->catalog()as$id=>$module)$out[]=['id'=>$id,'version'=>$module['version'],'installed'=>$registry->installed($id),'enabled'=>$registry->enabled($id)];
    echo json_encode($out,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
}finally{flock($lock,LOCK_UN);fclose($lock);}
