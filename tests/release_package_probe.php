<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/bootstrap.php';
require_once dirname(__DIR__).'/src/core/UpdateManager.php';
$archive=$argv[1];$public=$argv[2];$source=$testRoot.'/source';mkdir($source);$zip=new ZipArchive();if($zip->open($archive)!==true)throw new RuntimeException('Package unavailable');$zip->extractTo($source);$zip->close();
$version=VersionFile::version(file_get_contents($source.'/src/sys.conf.php'));
file_put_contents($source.'/src/sys.conf.php',VersionFile::merge(file_get_contents($source.'/src/sys.conf.php'),file_get_contents($source.'/src/sys.conf.php'),'3.2.7'));
$link=$testRoot.'/current';symlink($source,$link);putenv('SMF_CURRENT_LINK='.$link);putenv('SMF_UPDATE_PUBLIC_KEY='.$public);
putenv('SMF_PHP_ARGS_JSON='.json_encode(array_values(array_filter(preg_split('/\s+/',trim(getenv('PHP_ARGS')?:''))))));
$db=DatabaseManager::getInstance()->getConnection();HttpSecurity::useConnection($db);$db->exec(file_get_contents(__DIR__.'/fixtures/schema-3.2.7-sqlite.sql'));
$db->exec('ALTER TABLE users ADD COLUMN operator_note VARCHAR(100)');$db->exec('CREATE INDEX operator_note_index ON users(operator_note)');
$hash=password_hash('Release fixture password',PASSWORD_DEFAULT);$db->prepare("INSERT INTO users (username,email,password_hash,role,active,operator_note) VALUES ('release-admin','release@example.invalid',?,'admin','y','preserve')")->execute([$hash]);
$db->exec('CREATE TABLE email_templates (id INTEGER PRIMARY KEY AUTOINCREMENT,template_name VARCHAR(255) UNIQUE,subject TEXT,html_content TEXT,raw_content TEXT,variables TEXT)');$db->exec("INSERT INTO email_templates (template_name,subject,html_content) VALUES ('Kunden-Verifikations-E-Mail','Operator template','Custom {verificationLink}')");
$keyFile=HttpSecurity::privateDirectory('keys').'/provider-encryption.key';file_put_contents($keyFile,'existing-fixture-provider-key');$config=file_get_contents(getenv('SMF_CONFIG_FILE'));
$checks=0;function releaseCheck($ok,$message){global$checks;$checks++;if(!$ok)throw new RuntimeException($message);}
$manager=new UpdateManager($source,$db);$job=$manager->register($archive,1);releaseCheck($job['state']==='verified','actual release signature');
$manager->queue($job['id'],'stage');$manager->run($job['id']);$manager->queue($job['id'],'activate');$result=(new UpdateManager($source,$db))->run($job['id']);
releaseCheck($result['state']==='activated'&&realpath($link)!==$source,'actual package activation');
releaseCheck(VersionFile::version(file_get_contents(realpath($link).'/src/sys.conf.php'))===$version,'actual release version');
releaseCheck($db->query("SELECT checksum FROM schema_migrations WHERE version='20261024_core_01'")->fetchColumn()!==false,'actual candidate CLI schema migration');
releaseCheck($db->query("SELECT operator_note FROM users WHERE username='release-admin'")->fetchColumn()==='preserve','operator field survives real migration');
releaseCheck($db->query("SELECT password_hash FROM users WHERE username='release-admin'")->fetchColumn()===$hash,'password hash preserved');
releaseCheck($db->query("SELECT subject FROM email_templates WHERE template_name='Kunden-Verifikations-E-Mail'")->fetchColumn()==='Operator template','operator template preserved');
releaseCheck(file_get_contents($keyFile)==='existing-fixture-provider-key'&&file_get_contents(getenv('SMF_CONFIG_FILE'))===$config,'private configuration and encryption key preserved');
$manager->queue($job['id'],'rollback');$result=$manager->run($job['id']);releaseCheck($result['state']==='rolled_back'&&realpath($link)===$source,'actual release rollback');
releaseCheck(!SchemaManager::hasTable($db,'schema_migrations')&&$db->query("SELECT operator_note FROM users WHERE username='release-admin'")->fetchColumn()==='preserve','rollback restores original legacy database');
echo json_encode(['checks'=>$checks,'failed'=>0,'package_version'=>$version,'actual_candidate_cli_migration'=>true])."\n";
