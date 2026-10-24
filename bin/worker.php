<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/framework.php';require_once dirname(__DIR__).'/src/sys.conf.php';
SMF\Core\UpgradeManager::runPending();
HttpSecurity::cliBoundary();
$options=getopt('',['limit:','retain-days:']);$limit=max(1,min(50,(int)($options['limit']??3)));
$kernel=SMF\Core\Kernel::boot();$modules=$kernel->modules();$jobs=$kernel->jobs();
$handlers=[];
if($modules->enabled('shop.storage')){
    SMF\Core\ProviderAccounts::bootstrapShop();
    $tasks=[
        'shop.portal'=>function()use($limit){run_account_deletions($limit);},
        'shop.payments'=>function()use($limit){foreach(all("SELECT * FROM shop_orders WHERE payment_status IN ('pending','paid') ORDER BY updated_at,id LIMIT ".$limit)as$order)reconcile_paypal_order($order);},
        'shop.provisioning'=>function()use($limit){if(!SMF\Core\ShopSettings::allowLive())return;run_infrastructure_automation();run_provisioning_jobs($limit);run_dns_template_jobs($limit);},
        'shop.billing'=>function()use($limit){process_commerce_events($limit);},
        'shop.contracts'=>function()use($limit){if(SMF\Core\ShopSettings::allowLive())run_contract_worker($limit);},
        'shop.communications'=>function()use($limit){if(SMF\Core\ShopSettings::allowLive())run_mail_queue($limit);}
    ];
    foreach($tasks as$module=>$task)if($modules->enabled($module)){
        $type=$module;
        $jobs->enqueue($module,$type,'tick:'.$type.':'.intdiv(time(),60),[],SMF\Core\ShopSettings::get('environment'));
        $handlers[$type]=function($payload,$context)use($task){$GLOBALS['smf_job_id']=$context->id();try{$context->checkpoint('running',[]);$task();}finally{unset($GLOBALS['smf_job_id']);}};
    }
}
foreach($modules->jobs() as $type=>$entry)if(is_array($entry['definition']) && isset($entry['definition']['runtime'])){
    $handler=$modules->runtime($entry);if(!is_callable($handler))throw new RuntimeException('Module worker handler required');
    $handlers[$type]=$handler;
    if(!empty($entry['definition']['tick']))$jobs->enqueue($entry['module'],$type,'tick:'.$type.':'.intdiv(time(),60),[],SMF\Core\ShopSettings::get('environment'));
}
$jobs->dispatchOutbox();$processed=0;while($processed<$limit && $jobs->runOne($handlers))$processed++;
$heartbeat=HttpSecurity::privateDirectory('jobs').'/heartbeat.json';file_put_contents($heartbeat,json_encode(['at'=>time(),'processed'=>$processed]),LOCK_EX);chmod($heartbeat,0600);
if(function_exists('shop_meta_set'))shop_meta_set('worker_last_run',(string)time());
if(isset($options['retain-days'])){
    $days=max(7,min(3650,(int)$options['retain-days']));$kernel->audit()->prune($days);
    foreach(glob(HttpSecurity::privateDirectory('logs').'/authentication-????-??-??.jsonl')?:[]as$file)if(!is_link($file)&&filemtime($file)<time()-$days*86400)unlink($file);
}
echo json_encode(['processed'=>$processed,'environment'=>SMF\Core\ShopSettings::get('environment')])."\n";
