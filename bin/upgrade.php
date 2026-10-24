<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/framework.php';require_once dirname(__DIR__).'/src/sys.conf.php';
$options=getopt('',['modules:','resume:','restore:','run','status']);
if(isset($options['modules']))SMF\Core\UpgradeManager::request(array_values(array_filter(explode(',',$options['modules']))));
foreach(['resume','restore'] as$command)if(isset($options[$command]))SMF\Core\UpgradeManager::recover($options[$command],$command);
if(isset($options['run']))SMF\Core\UpgradeManager::runPending();
echo json_encode(SMF\Core\UpgradeManager::publicState(),JSON_PRETTY_PRINT)."\n";
