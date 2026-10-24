<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ob_start();require __DIR__.'/bootstrap.php';$checks=0;
function ensure($condition,$name){global $checks;$checks++;if(!$condition)throw new RuntimeException($name);}
function migrationRejected(callable $call,string $name){try{$call();}catch(Throwable $error){ensure(true,$name);return;}throw new RuntimeException($name);}
$registry=SMF\Core\Kernel::boot()->modules();$registry->install('shop.storage');$registry->enable('shop.storage');
$oldKey='Old fixture shop key, deliberately different from the new key';$config=['endpoint'=>'ovh-eu','application_key'=>'fixture-key','application_secret'=>'fixture-secret','consumer_key'=>'fixture-consumer'];
$nonce=random_bytes(12);$tag='';$cipher=openssl_encrypt(json_encode($config),'aes-256-gcm',hash_hkdf('sha256',$oldKey,32,'orga integration credentials'),OPENSSL_RAW_DATA,$nonce,$tag,'provider:ovh');
query('INSERT INTO shop_integrations (provider,secret_config) VALUES (?,?)',['ovh',base64_encode($nonce.$tag.$cipher)]);
query('INSERT INTO shop_orders (reference,customer_id,product_id,product_name,amount_cents,billing,provider,profile,domain,configuration,paypal_mode,payment_kind) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',['FIXTURE-1',1,1,'Fixture service',1071,'once','manual','{}','','{}','sandbox','order']);$order=(int)$db->lastInsertId();
query('INSERT INTO shop_payments (order_id,paypal_mode,transaction_id,amount_cents,currency,status) VALUES (?,?,?,?,?,?)',[$order,'sandbox','FIXTURE-TRANSACTION',1071,'EUR','completed']);$payment=(int)$db->lastInsertId();
$documents=$testRoot.'/source-documents';mkdir($documents.'/invoices',0700,true);$pdf="%PDF-1.4\nFixture invoice\n%%EOF\n";file_put_contents($documents.'/invoices/fixture.pdf',$pdf);
query('INSERT INTO shop_invoices (payment_id,order_id,customer_id,number,snapshot,pdf_file,pdf_hash) VALUES (?,?,?,?,?,?,?)',[$payment,$order,1,'FIXTURE-2026-0001','{}','fixture.pdf',hash('sha256',$pdf)]);
query('INSERT INTO shop_jobs (order_id,state,phase,payload,lock_token,locked_until) VALUES (?,?,?,?,?,?)',[$order,'running','creating_vm','{}','expired-fixture-lock',time()-10]);
$source=$testRoot.'/frozen.sqlite';$db->exec('VACUUM INTO '.$db->quote($source));$sourceHash=hash_file('sha256',$source);
$original=$db;$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);HttpSecurity::useConnection($db);SMF\Core\Kernel::reset();SchemaManager::migrate($db);SecuritySchema::migrate($db);SMF\Core\CoreMigrations::migrate($db);
$registry=SMF\Core\Kernel::boot()->modules();$registry->install('shop.storage');$registry->enable('shop.storage');
$restorable=$testRoot.'/before-import.sqlite';$db->exec('VACUUM INTO '.$db->quote($restorable));
$migration=new SMF\Core\ShopMigration($db,$source,$oldKey,$documents);$dry=$migration->dryRun();
ensure($dry['import_allowed'] && $dry['encrypted_values_verified']===1 && $dry['documents_verified']===1,'dry run verifies key and invoice');
ensure((int)$db->query('SELECT COUNT(*) FROM customers')->fetchColumn()===0,'dry run never imports customer data');
migrationRejected(fn()=>(new SMF\Core\ShopMigration($db,$source,'wrong key',$documents))->dryRun(),'wrong source key rejected');
$db->exec("CREATE TRIGGER migration_failure BEFORE INSERT ON shop_payments BEGIN SELECT RAISE(ABORT,'simulated import interruption');END");
migrationRejected(fn()=>$migration->import(),'interrupted import rolls back');
ensure((int)$db->query('SELECT COUNT(*) FROM customers')->fetchColumn()===0 && (int)$db->query('SELECT COUNT(*) FROM shop_orders')->fetchColumn()===0,'partial import leaves no customers or orders');
$db->exec('DROP TRIGGER migration_failure');$result=$migration->import();
ensure($result['money_totals']['shop_payments.amount_cents']===1071,'payment total retained');
ensure(one('SELECT number FROM shop_invoices')['number']==='FIXTURE-2026-0001','invoice number preserved');
ensure(secret_open(one('SELECT secret_config FROM shop_integrations')['secret_config'],'provider:ovh')===$config,'encrypted credentials rotated with unchanged plaintext');
ensure(one('SELECT state,lock_token FROM shop_jobs')['state']==='needs_action' && one('SELECT lock_token FROM shop_jobs')['lock_token']===null,'copied worker requires reconciliation');
ensure($migration->import()['replayed']===true && (int)one('SELECT COUNT(*) n FROM shop_payments')['n']===1,'same source cannot duplicate payment');
ensure(hash_file('sha256',$source)===$sourceHash,'frozen source unchanged');
$restored=new PDO('sqlite:'.$restorable);ensure((int)$restored->query('SELECT COUNT(*) FROM customers')->fetchColumn()===0 && (int)$restored->query('SELECT COUNT(*) FROM shop_invoices')->fetchColumn()===0,'pre-import database restores baseline');$restored=null;
ob_end_clean();echo json_encode(['checks'=>$checks,'failed'=>0,'restore_verified'=>true,'key_rotation_verified'=>true])."\n";
