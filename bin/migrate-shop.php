<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/framework.php';require_once dirname(__DIR__).'/src/sys.conf.php';
HttpSecurity::cliBoundary();SMF\Core\ProviderAccounts::bootstrapShop();
$options=getopt('',['source:','source-key:','documents:','apply']);
if(!isset($options['source'],$options['source-key']))throw new RuntimeException('Usage: --source=frozen.sqlite --source-key=private.key --documents=private-directory [--apply]');
if(is_link($options['source-key']))throw new RuntimeException('Source key symlink rejected');
$migration=new SMF\Core\ShopMigration(HttpSecurity::connection(),$options['source'],file_get_contents($options['source-key']),$options['documents']??null);
$result=isset($options['apply'])?$migration->import():$migration->dryRun();echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
