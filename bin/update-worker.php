<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/framework.php';
require_once dirname(__DIR__).'/src/core/UpdateManager.php';
$options=getopt('',['id:','action:','once','status']);$manager=new UpdateManager(dirname(__DIR__));
if(isset($options['status'])){echo json_encode($manager->jobs(),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";exit;}
try{
    if(isset($options['id'])){
        if(isset($options['action']))$manager->queue($options['id'],$options['action']);
        echo json_encode($manager->run($options['id']),JSON_THROW_ON_ERROR)."\n";
    }elseif(isset($options['once'])){
        foreach($manager->jobs()as$job)if($job['requested'])echo json_encode($manager->run($job['id']),JSON_THROW_ON_ERROR)."\n";
    }else{fwrite(STDERR,"Use --status, --once, or --id=<update-id> [--action=stage|activate|abort|rollback].\n");exit(2);}
}catch(Throwable $e){fwrite(STDERR,"Update worker failed (".get_class($e)."). Inspect job status and recovery instructions.\n");exit(1);}
