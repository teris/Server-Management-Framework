<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/framework.php';
HttpSecurity::cliBoundary();
require_once dirname(__DIR__).'/src/core/SchemaManager.php';
require_once dirname(__DIR__).'/src/core/SecuritySchema.php';
require_once dirname(__DIR__).'/src/core/EmailTemplateManager.php';
$options=getopt('',['username:','email:']);
if(!preg_match('/^[a-zA-Z0-9_.-]{3,100}$/D',$options['username']??'')||!filter_var($options['email']??'',FILTER_VALIDATE_EMAIL)){fwrite(STDERR,"Use --username=<admin> --email=<address>; password is read from standard input.\n");exit(2);}
if(version_compare(PHP_VERSION,'8.3','<')){fwrite(STDERR,"PHP 8.3 or newer is required.\n");exit(1);}
$db=HttpSecurity::connection();if(SchemaManager::tables($db)){fwrite(STDERR,"Database is not empty. Back up the installation and use bin/migrate.php.\n");exit(1);}
fwrite(STDERR,"New administrator password (12–4096 characters): ");$terminal=function_exists('stream_isatty')&&stream_isatty(STDIN);if($terminal&&PHP_OS_FAMILY!=='Windows')system('stty -echo');
try{$password=rtrim(fgets(STDIN)?:'',"\r\n");}finally{if($terminal&&PHP_OS_FAMILY!=='Windows')system('stty echo');}
if(strlen($password)<12||strlen($password)>4096){fwrite(STDERR,"\nInvalid password length. Database not changed.\n");exit(1);}
$lock=fopen(HttpSecurity::privateDirectory('locks').'/schema-migration.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Another migration is active');
try{
    SchemaManager::migrate($db);SecuritySchema::migrate($db);(new EmailTemplateManager($db))->createDefaultTemplates();
    $stmt=$db->prepare("INSERT INTO users (username,email,password_hash,full_name,role,active) VALUES (?,?,?,?,'admin','y')");$stmt->execute([$options['username'],mb_strtolower($options['email']),password_hash($password,PASSWORD_DEFAULT),$options['username']]);
    echo "\nInstallation complete. One explicit administrator created; no provider resources provisioned.\n";
}finally{flock($lock,LOCK_UN);fclose($lock);}
