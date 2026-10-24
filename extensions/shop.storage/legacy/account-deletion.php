<?php
function shop_migrate_account_deletion(PDO $db): void {
    $suffix=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci':'';
    $db->exec("CREATE TABLE IF NOT EXISTS shop_account_deletions (customer_id INT PRIMARY KEY,state VARCHAR(20) NOT NULL,requested_ts INT NOT NULL,undo_until_ts INT NOT NULL,revision INT NOT NULL DEFAULT 1,cancelled_ts INT NOT NULL DEFAULT 0,completed_ts INT NOT NULL DEFAULT 0,last_checked_ts INT NOT NULL DEFAULT 0,next_check_ts INT NOT NULL DEFAULT 0,last_error VARCHAR(250),FOREIGN KEY(customer_id) REFERENCES customers(id))".$suffix);
}
function account_deletion_request(int $customer): ?array {return one('SELECT * FROM shop_account_deletions WHERE customer_id=?',[$customer]);}
function account_deletion_pending(int $customer): bool {return (account_deletion_request($customer)['state']??'')==='pending';}
function account_customer_lock(int $customer): ?array {
    $lock=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
    return one('SELECT * FROM customers WHERE id=?'.$lock,[$customer]);
}
function account_active_customer_lock(int $customer): array {
    $person=account_customer_lock($customer);
    if(!$person || $person['status']!=='active')throw new RuntimeException('Dieses Kundenkonto ist nicht mehr verfügbar.');
    return $person;
}
function assert_customer_can_order(int $customer,bool $lock=false): void {
    $person=$lock?account_customer_lock($customer):one('SELECT status FROM customers WHERE id=?',[$customer]);
    if(!$person || $person['status']!=='active')throw new RuntimeException('Dieses Kundenkonto ist nicht mehr für neue Bestellungen verfügbar.');
    if(account_deletion_pending($customer))throw new RuntimeException('Für dein Konto ist eine Löschung vorgemerkt. Bitte nimm den Löschantrag in deinen Kontoeinstellungen zurück, bevor du neue Pakete oder Module bestellst.');
}
function account_deletion_blockers(int $customer): array {
    // Deliberately use the complete records: canceled contracts can still be running.
    $contracts=(int)query("SELECT COUNT(*) FROM shop_orders o LEFT JOIN shop_contracts c ON c.order_id=o.id WHERE o.customer_id=? AND ((c.order_id IS NOT NULL AND c.state NOT IN ('ended','unpaid_cancelled')) OR (c.order_id IS NULL AND o.fulfillment_status NOT IN ('ended','cancelled') AND o.payment_status<>'cancelled'))",[$customer])->fetchColumn();
    $services=(int)query("SELECT COUNT(*) FROM shop_services WHERE customer_id=? AND status NOT IN ('cancelled','ended')",[$customer])->fetchColumn();
    $cleanup=(int)query("SELECT COUNT(*) FROM shop_contracts c JOIN shop_orders o ON o.id=c.order_id WHERE o.customer_id=? AND c.state IN ('ended','unpaid_cancelled') AND (c.end_state<>'done' OR c.paypal_cancel_state<>'done')",[$customer])->fetchColumn();
    $jobs=(int)query("SELECT COUNT(*) FROM shop_jobs j JOIN shop_orders o ON o.id=j.order_id WHERE o.customer_id=? AND j.state IN ('queued','waiting','running','needs_action')",[$customer])->fetchColumn();
    $operations=(int)query("SELECT COUNT(*) FROM shop_server_operations p JOIN shop_services s ON s.id=p.service_id WHERE s.customer_id=? AND p.state IN ('queued','waiting','running','needs_action')",[$customer])->fetchColumn();
    $mail=(int)query("SELECT COUNT(*) FROM shop_mail_queue m JOIN customers c ON c.email=m.recipient WHERE c.id=? AND m.state='sending'",[$customer])->fetchColumn();
    $dns=(int)query("SELECT COUNT(*) FROM shop_dns_jobs j JOIN shop_orders o ON o.id=j.order_id WHERE o.customer_id=? AND j.state='running'",[$customer])->fetchColumn();
    $mailDns=(int)query("SELECT COUNT(*) FROM shop_hosting_mail_dns WHERE customer_id=? AND state='running'",[$customer])->fetchColumn();
    return ['contracts'=>$contracts,'services'=>$services,'processing'=>$cleanup+$jobs+$operations+$mail+$dns+$mailDns,'blocked'=>$contracts+$services+$cleanup+$jobs+$operations+$mail+$dns+$mailDns>0];
}
function request_account_deletion(int $customer,string $password,bool $confirmed): array {
    if(!$confirmed)throw new RuntimeException('Bitte bestätige, dass du die Kontolöschung vormerken möchtest.');
    db()->beginTransaction();try{
        $person=account_customer_lock($customer);
        if(!$person || $person['status']!=='active' || !password_verify($password,$person['password_hash']))throw new RuntimeException('Bitte bestätige die Kontolöschung mit deinem aktuellen Passwort.');
        $old=account_deletion_request($customer);if(($old['state']??'')==='pending'){db()->commit();return $old;}
        $now=shop_now();$until=$now+86400;
        if($old)query("UPDATE shop_account_deletions SET state='pending',requested_ts=?,undo_until_ts=?,revision=revision+1,cancelled_ts=0,completed_ts=0,last_checked_ts=0,next_check_ts=0,last_error=NULL WHERE customer_id=?",[$now,$until,$customer]);
        else query("INSERT INTO shop_account_deletions (customer_id,state,requested_ts,undo_until_ts) VALUES (?,'pending',?,?)",[$customer,$now,$until]);
        $revision=(int)($old['revision']??0)+1;
        queue_shop_mail('account-deletion:'.$customer.':'.$revision.':requested',$person['email'],'Kontolöschung vorgemerkt',"Hallo ".$person['first_name'].",\n\ndeine Kontolöschung wurde vorgemerkt. Die Löschung erfolgt frühestens am ".date('d.m.Y H:i',$until)." Uhr (deutsche Ortszeit) und erst, wenn alle Verträge tatsächlich beendet und beim Anbieter abgeschlossen sind. Der Antrag kündigt deine Verträge nicht.\n\nSolange die Löschung noch aussteht, kannst du sie in deinen Kontoeinstellungen zurücknehmen:\n".Config::FRONTPANEL_SITE_URL.'/konto/einstellungen#konto-loeschen');
        audit_event('account.deletion.requested',(string)$customer);db()->commit();return account_deletion_request($customer);
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function cancel_account_deletion(int $customer): void {
    db()->beginTransaction();try{
        $person=account_customer_lock($customer);if(!$person || $person['status']!=='active')throw new RuntimeException('Dieses Konto ist nicht mehr verfügbar.');
        $request=account_deletion_request($customer);
        if(($request['state']??'')==='pending'){
            query("UPDATE shop_account_deletions SET state='cancelled',cancelled_ts=?,next_check_ts=0,last_error=NULL WHERE customer_id=? AND state='pending'",[shop_now(),$customer]);
            queue_shop_mail('account-deletion:'.$customer.':'.$request['revision'].':cancelled',$person['email'],'Kontolöschung zurückgenommen',"Hallo ".$person['first_name'].",\n\ndie Löschvormerkung deines Kundenkontos wurde zurückgenommen. Dein Konto bleibt bestehen.\n\n".Config::FRONTPANEL_SITE_URL.'/konto/einstellungen');
            audit_event('account.deletion.cancelled',(string)$customer);
        }
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
function account_payload_without_passwords(array $payload): array {
    foreach($payload as $key=>$value){if(preg_match('/password|credentials|secret|private_key/i',(string)$key)){unset($payload[$key]);continue;}if(is_array($value))$payload[$key]=account_payload_without_passwords($value);}return $payload;
}
function erase_shop_account(array $person): void {
    $customer=(int)$person['id'];
    // Immutable invoice/payment/contract snapshots stay outside the erased shop profile.
    query("UPDATE customers SET first_name='Gelöschtes',last_name='Kundenkonto',full_name='Gelöschtes Kundenkonto',email=?,password_hash=?,company=NULL,phone=NULL,address=NULL,postal_code=NULL,city=NULL,country=NULL,email_verified_at=NULL,last_login=NULL,status='deleted',updated_at=CURRENT_TIMESTAMP WHERE id=?",['deleted-'.$customer.'-'.bin2hex(random_bytes(12)).'@deleted.invalid',password_hash(bin2hex(random_bytes(48)),PASSWORD_DEFAULT),$customer]);
    query("DELETE FROM shop_personal_preferences WHERE actor_type='customer' AND actor_id=?",[$customer]);query('DELETE FROM shop_notifications WHERE customer_id=?',[$customer]);
    query('DELETE FROM shop_provider_account_logins WHERE customer_id=?',[$customer]);
    query('DELETE FROM shop_child_credentials WHERE child_id IN (SELECT h.id FROM shop_hosting_children h JOIN shop_services s ON s.id=h.service_id WHERE s.customer_id=?)',[$customer]);
    query('UPDATE shop_resource_links SET credentials=NULL WHERE service_id IN (SELECT id FROM shop_services WHERE customer_id=?)',[$customer]);
    query("UPDATE shop_dns_jobs SET state='cancelled',last_error=NULL WHERE order_id IN (SELECT id FROM shop_orders WHERE customer_id=?) AND state IN ('pending','needs_action')",[$customer]);
    query("UPDATE shop_hosting_mail_dns SET state='cancelled',last_error=NULL WHERE customer_id=? AND state IN ('pending','needs_action')",[$customer]);
    query("UPDATE shop_quotes SET status='cancelled' WHERE customer_id=? AND order_id IS NULL AND status IN ('draft','sent')",[$customer]);
    foreach(all('SELECT j.id,j.payload FROM shop_jobs j JOIN shop_orders o ON o.id=j.order_id WHERE o.customer_id=?',[$customer]) as $job)query('UPDATE shop_jobs SET payload=? WHERE id=?',[json_encode(account_payload_without_passwords(json_decode($job['payload'],true)??[])),$job['id']]);
    foreach(all('SELECT p.id,p.payload FROM shop_server_operations p JOIN shop_services s ON s.id=p.service_id WHERE s.customer_id=?',[$customer]) as $op)query('UPDATE shop_server_operations SET payload=? WHERE id=?',[json_encode(account_payload_without_passwords(json_decode($op['payload'],true)??[])),$op['id']]);
    query('DELETE FROM ticket_replies WHERE ticket_id IN (SELECT id FROM support_tickets WHERE customer_id=?)',[$customer]);query('DELETE FROM support_tickets WHERE customer_id=?',[$customer]);
    query("UPDATE shop_requests SET notes='',admin_note='',status=CASE WHEN status IN ('new','reviewing','quoted') THEN 'cancelled' ELSE status END WHERE customer_id=?",[$customer]);
    foreach(all('SELECT id,payload FROM shop_mail_queue WHERE recipient=?',[$person['email']]) as $mail){$payload=json_decode($mail['payload'],true)??[];if(empty($payload['invoice_id']))query('DELETE FROM shop_mail_queue WHERE id=?',[$mail['id']]);}
}
function run_account_deletions(int $limit=3): int {
    $done=0;$now=shop_now();
    foreach(all("SELECT customer_id FROM shop_account_deletions WHERE state='pending' AND undo_until_ts<=? AND next_check_ts<=? ORDER BY next_check_ts,requested_ts LIMIT ".max(1,min(10,$limit)),[$now,$now]) as $candidate){
        $customer=(int)$candidate['customer_id'];
        try{db()->beginTransaction();$person=account_customer_lock($customer);$request=account_deletion_request($customer);
            if(!$person || $person['status']!=='active' || !$request || $request['state']!=='pending' || (int)$request['undo_until_ts']>$now){db()->commit();continue;}
            if(account_deletion_blockers($customer)['blocked']){query('UPDATE shop_account_deletions SET last_checked_ts=?,next_check_ts=?,last_error=NULL WHERE customer_id=?',[$now,$now+300,$customer]);db()->commit();continue;}
            erase_shop_account($person);query("UPDATE shop_account_deletions SET state='completed',completed_ts=?,last_checked_ts=?,next_check_ts=0,last_error=NULL WHERE customer_id=? AND state='pending'",[$now,$now,$customer]);audit_event('account.deletion.completed',(string)$customer);db()->commit();$done++;
        }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();query("UPDATE shop_account_deletions SET last_checked_ts=?,next_check_ts=?,last_error='Kontolöschung konnte nicht abgeschlossen werden. Bitte durch die Administration prüfen.' WHERE customer_id=? AND state='pending'",[$now,$now+300,$customer]);}
    }
    return $done;
}
function handle_account_deletion_action(string $action): bool {
    if(!in_array($action,['account_delete_request','account_delete_cancel'],true))return false;
    $customer=require_user();rate_limit('account_delete',12,3600);
    if($action==='account_delete_request'){request_account_deletion((int)$customer['id'],(string)($_POST['deletion_password']??''),isset($_POST['deletion_confirmed']));flash('Deine Kontolöschung ist vorgemerkt. Du kannst den Antrag mindestens 24 Stunden lang zurücknehmen.');}
    else{cancel_account_deletion((int)$customer['id']);flash('Die Kontolöschung wurde zurückgenommen. Dein Konto bleibt bestehen.');}
    redirect('/konto/einstellungen#konto-loeschen');
}
function account_deletion_banner(): void {
    if(!($customer=user()) || !account_deletion_pending((int)$customer['id']))return;
    ?>
    <aside class="notice account-deletion-banner" aria-label="Kontolöschung vorgemerkt"><?= icon('clock') ?><div><strong>Kontolöschung vorgemerkt</strong><p>Dein Konto wird frühestens nach 24 Stunden und erst nach dem Ende aller Verträge gelöscht. Bis dahin kannst du den Antrag zurücknehmen.</p><a class="text-link" href="/konto/einstellungen#konto-loeschen">Löschantrag verwalten <?= icon('arrow') ?></a></div></aside>
    <?php
}
