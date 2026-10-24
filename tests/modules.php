<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ob_start();require __DIR__.'/bootstrap.php';
$checks=0;$failures=[];
function expect($condition,$name){global $checks,$failures;$checks++;if(!$condition)$failures[]=$name;}
function rejects(callable $f,$name){try{$f();expect(false,$name);}catch(Throwable $e){expect(true,$name);}}
$modules=SMF\Core\Kernel::boot()->modules();
expect(count($modules->routes())===0,'uninstalled module contributes no routes');
rejects(fn()=>$modules->install('shop.payments'),'dependency must be enabled before installation');
foreach(['shop.storage','shop.catalog','shop.portal','shop.orders','shop.payments','shop.provisioning','shop.billing','shop.contracts','shop.communications','shop.promotions','shop.subscriptions'] as $id){$modules->install($id);$modules->enable($id);$modules->assertEnabled($id);expect(true,'install '.$id);}
expect(count($modules->routes())>30,'active modules contribute routes');
$before=(int)$db->query('SELECT COUNT(*) FROM shop_products')->fetchColumn();$modules->install('shop.storage');expect($before===(int)$db->query('SELECT COUNT(*) FROM shop_products')->fetchColumn(),'reinstall preserves catalog');
$modules->disable('shop.promotions');expect(!isset($modules->routes()['/admin/gutscheine']),'disabled module removes route');
rejects(fn()=>shop_require_action('coupon_save'),'disabled module blocks action');
rejects(fn()=>$modules->disable('shop.storage'),'active dependents block disabling storage');
Identity::clear('customer');$_SESSION['customer_id']=1;expect(user()===null,'raw legacy customer id never grants identity');
Identity::clear('backend');$_SESSION['admin_id']=1;expect(admin()===null,'raw legacy admin id never grants identity');
testLogin('customer',1);expect((int)user()['id']===1,'core login recognized by shop');
rejects(fn()=>shop_provider_guard('proxmox','POST','https://example.test/nodes/a/qemu',null),'sandbox blocks live VM creation');
rejects(fn()=>shop_provider_guard('ispconfig','POST','https://example.test/remote/json.php?sites_web_domain_add','{}'),'sandbox blocks ISP write');
expect(payment_amount_cents('12.34')===1234,'payment decimal converted to cents');
rejects(fn()=>payment_amount_cents('12.345'),'fractional cents rejected');
// A write intent survives a worker crash; retry never replays an uncertain provider call.
$queue=SMF\Core\Kernel::boot()->jobs();
$id=$queue->enqueue('shop.provisioning','shop.provisioning','fixture-operation',['order_id'=>8]);
expect($queue->enqueue('shop.provisioning','shop.provisioning','fixture-operation',['order_id'=>8])===$id,'enqueue replay preserves job ID');
rejects(fn()=>$queue->enqueue('shop.provisioning','shop.provisioning','fixture-operation',['order_id'=>9]),'job key cannot change ownership/payload');
$job=$queue->claim();expect($job['id']===$id,'worker claims registered job');expect($queue->claim()===null,'parallel worker cannot claim active lease');
$context=new SMF\Core\JobContext($db,$id,$job['lock_token'],'sandbox');
rejects(fn()=>$context->beforeWrite([]),'sandbox job refuses provider write');
$context->checkpoint('running',[]);$context->checkpoint('running',[]);expect(true,'identical immediate checkpoints retain a valid MySQL lease');
$context->checkpoint('writing',['order_id'=>8,'intent'=>'clone']);
$db->prepare('UPDATE smf_jobs SET locked_until=? WHERE id=?')->execute([time()-1,$id]);
expect($queue->claim()===null,'expired write is not automatically retried');
expect(one('SELECT state FROM smf_jobs WHERE id=?',[$id])['state']==='needs_review','uncertain job requires reconciliation');
expect($queue->enqueue('shop.provisioning','shop.provisioning','fixture-operation',['order_id'=>8])===$id,'immutable initial hash survives phase payload changes');
rejects(fn()=>$context->checkpoint('done',[]),'stale worker cannot commit after lease loss');
$queue->publish('shop.billing','fixture-event','shop.billing',['order_id'=>8]);$queue->publish('shop.billing','fixture-event','shop.billing',['order_id'=>8]);
rejects(fn()=>$queue->publish('shop.billing','fixture-event','shop.billing',['order_id'=>999]),'outbox key cannot change event contents');
expect($queue->dispatchOutbox()===1 && $queue->dispatchOutbox()===0,'transactional outbox dispatches once');
$modules->disable('shop.billing');expect($queue->claim()===null,'disabled module leaves pending jobs untouched');$modules->enable('shop.billing');
$calls=0;$queue->runOne(['shop.billing'=>function()use(&$calls){$calls++;}]);expect($calls===1 && $queue->claim()===null,'registered handler completes exactly once');
// Preserve the selected node and every existing network option, and actually apply the IP.
$networkCalls=[];$transport=function($method,$path,$data)use(&$networkCalls){$networkCalls[]=[$method,$path,$data];return $method==='GET'?['data'=>['net0'=>'virtio=02:00:00:00:00:01,bridge=vmbr7,tag=42,firewall=1','ipconfig0'=>'ip6=auto','ide2'=>'local:cloudinit','digest'=>'abc']]:null;};
SMF\Core\NetworkConfiguration::update(['node'=>'node-b','vmid'=>123,'mac'=>'02:12:34:56:78:90','ip'=>'192.0.2.7/24','gateway'=>'192.0.2.1'],$transport);
expect($networkCalls[1][1]==='/nodes/node-b/qemu/123/config','network uses actual node');
expect($networkCalls[1][2]['net0']==='virtio=02:12:34:56:78:90,bridge=vmbr7,tag=42,firewall=1','network preserves bridge VLAN firewall');
expect($networkCalls[1][2]['ipconfig0']==='ip=192.0.2.7/24,gw=192.0.2.1,ip6=auto','network applies IP and keeps other address family');
$before=count($networkCalls);rejects(fn()=>SMF\Core\NetworkConfiguration::update(['node'=>'node-b','vmid'=>123,'mac'=>'ff:ff:ff:ff:ff:ff','ip'=>'192.0.2.7/24'],$transport),'multicast MAC rejected');expect(count($networkCalls)===$before,'invalid network request makes no provider call');
rejects(fn()=>SMF\Core\NetworkConfiguration::update(['node'=>'node-b','vmid'=>123,'mac'=>'02:12:34:56:78:90','ip'=>'192.0.2.7/24'],fn()=>['data'=>['net0'=>'virtio=02:00:00:00:00:01','digest'=>'abc']]),'missing Cloud-Init rejects IP update');
$writes=0;$operations=new SMF\Core\ProviderOperations($db,SMF\Core\Kernel::boot()->audit());
$write=function()use(&$writes){$writes++;throw new RuntimeException('Simulated timeout after remote acceptance');};
rejects(fn()=>$operations->perform('proxmox',ResourceAccess::connectionId('proxmox'),'fixture:uncertain',$write),'uncertain provider write retained');
rejects(fn()=>$operations->perform('proxmox',ResourceAccess::connectionId('proxmox'),'fixture:uncertain',$write),'uncertain operation never blindly replayed');expect($writes===1,'provider timeout cannot duplicate external resource');
// The catalog computes amounts and quantities; posted prices never define an order amount.
$p=products()[0];rejects(fn()=>configured_product($p,[9999999=>1]),'unknown module ID rejected');
foreach(['-1','1.5','1e3','2147483648',[]] as $qty)rejects(fn()=>module_quantity($qty),'malformed or overflowing module quantity rejected');
expect(vat_from_net(900)['gross_cents']===1071,'tax stays in integer cents');
function fixtureOrder(string $reference,string $fulfillment='pending'): array {
    $p=products()[0];query('INSERT INTO shop_orders (reference,customer_id,product_id,product_name,amount_cents,currency,billing,provider,profile,domain,configuration,paypal_mode,payment_kind,payment_id,fulfillment_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$reference,1,$p['id'],'Fixture',1071,'EUR','once','manual','{}','',json_encode(['paypal_merchant_id'=>'MERCHANT-A']),'sandbox','order','PAYPAL-'.$reference,$fulfillment]);return one('SELECT * FROM shop_orders WHERE reference=?',[$reference]);
}
$order=fixtureOrder('TEST-A');
$reply=['id'=>$order['payment_id'],'status'=>'COMPLETED','purchase_units'=>[['custom_id'=>$order['reference'],'payee'=>['merchant_id'=>'MERCHANT-A'],'payments'=>['captures'=>[['id'=>'CAPTURE-A','status'=>'COMPLETED','amount'=>['value'=>'10.71','currency_code'=>'EUR']]]]]]];
$wrong=$reply;$wrong['purchase_units'][0]['payee']['merchant_id']='ATTACKER';rejects(fn()=>accept_capture($order,$wrong),'wrong PayPal merchant cannot book payment');
$wrong=$reply;$wrong['purchase_units'][0]['payments']['captures'][0]['amount']['value']='0.01';rejects(fn()=>accept_capture($order,$wrong),'wrong capture amount rejected');
$wrong=$reply;$wrong['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code']='USD';rejects(fn()=>accept_capture($order,$wrong),'wrong currency rejected');
expect((int)one('SELECT COUNT(*) n FROM shop_payments')['n']===0,'invalid payments leave ledger empty');
accept_capture($order,$reply);accept_capture($order,$reply);
expect((int)one('SELECT COUNT(*) n FROM shop_payments')['n']===1 && (int)one('SELECT COUNT(*) n FROM shop_jobs')['n']===1,'duplicate capture creates one payment and one job');
expect(one('SELECT state FROM shop_jobs WHERE order_id=?',[$order['id']])['state']==='sandbox','sandbox payment never queues live provisioning');
$other=fixtureOrder('TEST-B','cancelled');rejects(fn()=>record_paid($other,'CAPTURE-A',1071,'EUR'),'transaction ID cannot pay another order');record_paid($other,'CAPTURE-LATE',1071,'EUR');
expect(one('SELECT payment_status FROM shop_orders WHERE id=?',[$other['id']])['payment_status']==='review','late payment after cancellation goes to review');
expect(!one('SELECT id FROM shop_jobs WHERE order_id=?',[$other['id']]),'late payment never creates a provisioning job');
$oldCount=(int)one('SELECT COUNT(*) n FROM shop_payments')['n'];$modules->disable('shop.payments');expect((int)one('SELECT COUNT(*) n FROM shop_payments')['n']===$oldCount,'disabling payments preserves booked data');$modules->enable('shop.payments');
rejects(fn()=>new PaypalProvider(['mode'=>'live']),'test site refuses live PayPal configuration');
// Signature verification relays the expected headers and webhook ID to PayPal, never to an event-controlled URL.
$paypalConfig=['mode'=>'sandbox','client_id'=>'fixture-app','client_secret'=>'fixture-secret','webhook_id'=>'fixture-webhook','merchant_id'=>'MERCHANT-A'];
query('INSERT INTO shop_integrations (provider,enabled,secret_config) VALUES (?,?,?)',['paypal',1,secret_seal($paypalConfig,'provider:paypal')]);
$verified=false;$paypalCalls=0;$reply['purchase_units'][0]['amount']=['value'=>'10.71','currency_code'=>'EUR'];
$GLOBALS['shop_test_transport']=function($provider,$method,$url,$body,$headers)use(&$verified,&$paypalCalls,$reply){
    $paypalCalls++;if(str_ends_with($url,'/v1/oauth2/token'))return ['data'=>['access_token'=>'fixture-token']];
    if(str_ends_with($url,'/verify-webhook-signature')){$data=json_decode($body,true);expect($data['webhook_id']==='fixture-webhook','signature verification uses configured webhook ID');return ['data'=>['verification_status'=>$verified?'SUCCESS':'FAILURE']];}
    if(str_contains($url,'/v2/checkout/orders/'))return ['data'=>$reply];throw new RuntimeException('Unexpected provider request');
};
$paypal=new PaypalProvider($paypalConfig);$event=['id'=>'EVENT-CAPTURE','event_type'=>'PAYMENT.CAPTURE.COMPLETED','resource'=>['supplementary_data'=>['related_ids'=>['order_id'=>$order['payment_id']]]]];
expect(!$paypal->verifyWebhook($event,[]),'missing signature headers rejected without HTTP');
$headers=array_fill_keys(['PAYPAL-AUTH-ALGO','PAYPAL-CERT-URL','PAYPAL-TRANSMISSION-ID','PAYPAL-TRANSMISSION-SIG','PAYPAL-TRANSMISSION-TIME'],'fixture');
expect(!$paypal->verifyWebhook($event,$headers),'PayPal verification FAILURE rejects forged event');$verified=true;expect($paypal->verifyWebhook($event,$headers),'PayPal verification SUCCESS accepts signed envelope');
process_paypal_event($event,$paypal);$before=$paypalCalls;process_paypal_event($event,$paypal);expect($paypalCalls===$before,'duplicate webhook event does not repeat provider I/O');
process_paypal_event(['id'=>'EVENT-OLDER-APPROVAL','event_type'=>'CHECKOUT.ORDER.APPROVED','resource'=>['id'=>$order['payment_id']]],$paypal);
expect((int)one('SELECT COUNT(*) n FROM shop_payments')['n']===2 && (int)one('SELECT COUNT(*) n FROM shop_jobs')['n']===1,'approval arriving after capture cannot double book or provision');
unset($GLOBALS['shop_test_transport']);

// Ownership and common provider binding hold even for identical numeric IDs in different realms.
query('INSERT INTO shop_services (customer_id,name,category,identifier,details) VALUES (?,?,?,?,?)',[1,'Fixture VM','server','123','{}']);$serviceId=(int)db()->lastInsertId();
query('INSERT INTO shop_resource_links (service_id,provider,external_id,metadata) VALUES (?,?,?,?)',[$serviceId,'proxmox','123',json_encode(['connection_id'=>integration_identity('proxmox'),'node'=>'node-b','vmid'=>123,'core_instance_id'=>'00000000-0000-0000-0000-000000000123'])]);
SMF\Core\ShopResourceBindings::grant($serviceId,1,'proxmox',['node'=>'node-b','vmid'=>123,'core_instance_id'=>'00000000-0000-0000-0000-000000000123','connection_id'=>integration_identity('proxmox')]);
rejects(fn()=>bound_service($serviceId,2),'other customer cannot use bound shop service');expect(bound_service($serviceId,1)['customer_id']==1,'owner can resolve shop resource');
query("UPDATE shop_services SET status='suspended' WHERE id=?",[$serviceId]);
rejects(fn()=>bound_service($serviceId,1,true),'suspended shop service blocks common write grant');
$grant=one('SELECT * FROM security_resource_assignments WHERE id=?',[SMF\Core\ShopResourceBindings::grantId($serviceId)]);expect(!SMF\Core\ShopResourceBindings::canonicalValid($grant,true),'framework API also refuses suspended shop service');
query("UPDATE shop_services SET status='active' WHERE id=?",[$serviceId]);
rejects(fn()=>integration_config('proxmox'),'disabled Config provider cannot be opened by shop fallback');
expect(integration_identity('proxmox')===ResourceAccess::connectionId('proxmox'),'admin and shop share Config provider identity');
(new SMF\Core\AuditLog($db))->record('test.safe','fixture','ok',['password'=>'DO-NOT-LOG','error_code'=>'SAFE']);
expect(!str_contains(json_encode($db->query('SELECT * FROM smf_audit')->fetchAll()),'DO-NOT-LOG'),'audit discards secret context');

// Generate an actual Unicode PDF and prove immutable numbering/snapshots, not just copied bytes.
save_commerce_settings(['seller_name'=>'Fixture Büro','seller_address'=>'Teststraße 7','seller_postal_code'=>'10115','seller_city'=>'Berlin','seller_country'=>'DE','seller_tax_id'=>'FIXTURE','seller_email'=>'invoice@example.invalid','invoice_prefix'=>'QA']);
query("UPDATE customers SET first_name='Jörg',last_name='Müller',address='Mühlenstraße 7',postal_code='10115',city='Berlin',country='DE' WHERE id=1");
$payment=one('SELECT * FROM shop_payments WHERE transaction_id=?',['CAPTURE-A']);$document=invoice_for_payment($payment,$order);$pdf=invoice_file($document);$bytes=file_get_contents($pdf);
expect(str_starts_with($bytes,'%PDF-') && strlen($bytes)>1000,'actual invoice PDF renders Unicode using bundled fonts');
$stored=one('SELECT * FROM shop_invoices WHERE id=?',[$document['id']]);expect(hash_equals($stored['pdf_hash'],hash('sha256',$bytes)),'generated invoice stores matching immutable PDF hash');
$number=$stored['number'];$snapshot=$stored['snapshot'];query("UPDATE shop_products SET name='Changed catalog',price_cents=999999 WHERE id=?",[$order['product_id']]);
$again=invoice_for_payment($payment,$order);expect($again['number']===$number && $again['snapshot']===$snapshot && invoice_file($again)===$pdf,'catalog edits and repeated processing retain invoice number/snapshot/PDF');
$modules->disable('shop.billing');expect(is_file($pdf) && one('SELECT snapshot FROM shop_invoices WHERE id=?',[$document['id']])['snapshot']===$snapshot,'disabled billing preserves paid documents');$modules->enable('shop.billing');
$contractOrder=fixtureOrder('TEST-CONTRACT');$policy=validated_contract_policy(['minimum_months'=>12,'notice_days'=>30],'month');
query('UPDATE shop_orders SET billing=?,configuration=? WHERE id=?',['month',json_encode(['contract_policy'=>$policy]),$contractOrder['id']]);$contractOrder=one('SELECT * FROM shop_orders WHERE id=?',[$contractOrder['id']]);
record_paid($contractOrder,'CAPTURE-CONTRACT',1071,'EUR');$contract=ensure_order_contract($contractOrder);
expect($contract['state']==='active' && (int)$contract['paid_until']>(int)$contract['started_at'],'payment activates the snapshotted contract period');
save_product_contract_policy((int)$contractOrder['product_id'],['minimum_months'=>1,'notice_days'=>0],'month');expect(json_decode(ensure_order_contract($contractOrder)['policy'],true)===$policy,'later package policy edits never rewrite the booked contract');

ob_end_clean();echo json_encode(['checks'=>$checks,'failed'=>count($failures),'failures'=>$failures],JSON_PRETTY_PRINT)."\n";exit($failures?1:0);
