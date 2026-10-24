<?php
function shop_migrate_order_admin(PDO $db): void {
    $sqlite=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';$id=$sqlite?'INTEGER PRIMARY KEY AUTOINCREMENT':'INT AUTO_INCREMENT PRIMARY KEY';$suffix=$sqlite?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $db->exec("CREATE TABLE IF NOT EXISTS shop_order_admin (order_id INT PRIMARY KEY,activated_ts INT NOT NULL DEFAULT 0,activated_by INT,activation_reason TEXT,cancelled_ts INT NOT NULL DEFAULT 0,cancelled_by INT,cancellation_reason TEXT,deleted_ts INT NOT NULL DEFAULT 0,deleted_by INT,deletion_reason TEXT,deletion_token VARCHAR(64),FOREIGN KEY(order_id) REFERENCES shop_orders(id))".$suffix);
    $columns=$sqlite?array_column($db->query('PRAGMA table_info(shop_order_admin)')->fetchAll(PDO::FETCH_ASSOC),'name'):array_column($db->query('SHOW COLUMNS FROM shop_order_admin')->fetchAll(PDO::FETCH_ASSOC),'Field');
    if(!in_array('deletion_token',$columns,true))$db->exec('ALTER TABLE shop_order_admin ADD COLUMN deletion_token VARCHAR(64)');
    $db->exec("CREATE TABLE IF NOT EXISTS shop_order_admin_events (id $id,order_id INT NOT NULL,admin_id INT NOT NULL,operation VARCHAR(20) NOT NULL,reason TEXT NOT NULL,token VARCHAR(64) NOT NULL UNIQUE,created_ts INT NOT NULL,FOREIGN KEY(order_id) REFERENCES shop_orders(id),FOREIGN KEY(admin_id) REFERENCES users(id))".$suffix);
}
function order_admin_state(int $order): ?array {return one('SELECT * FROM shop_order_admin WHERE order_id=?',[$order]);}
function admin_order_released(array $order): bool {
    if(!in_array($order['payment_status'],['pending','paid','test_paid','no_charge'],true))return false;
    $state=order_admin_state((int)$order['id']);return $state && (int)$state['activated_ts']>0 && !(int)$state['cancelled_ts'] && !(int)$state['deleted_ts'];
}
function admin_order_note_active(array $order): void {
    if(!admin_order_released($order))return;
    query("UPDATE shop_contracts SET state=CASE WHEN state='pending' THEN 'active' ELSE state END,started_at=CASE WHEN started_at=0 THEN ? ELSE started_at END,next_check_at=0 WHERE order_id=? AND state NOT IN ('ended','unpaid_cancelled','cancel_requested')",[shop_now(),$order['id']]);
}
function admin_order_family(array $root): array {
    $rows=[];$queue=[$root];while($queue){$o=array_shift($queue);$id=(int)$o['id'];if(isset($rows[$id]))continue;if((int)$o['customer_id']!==(int)$root['customer_id'])throw new RuntimeException('Die Zuordnung der Zusatzbestellungen muss zuerst geprüft werden.');$rows[$id]=$o;foreach(all('SELECT o.* FROM shop_module_intents i JOIN shop_orders o ON o.id=i.order_id WHERE i.parent_order_id=?',[$id]) as $child)$queue[]=$child;}
    ksort($rows,SORT_NUMERIC);return $rows;
}
function admin_order_validate_request(int $admin,array $input): array {
    if(!one("SELECT id FROM users WHERE id=? AND role='admin' AND active='y'",[$admin]))throw new RuntimeException('Diese Aktion erfordert einen aktiven Administrator.');
    $reason=$input['reason']??null;$token=$input['operation_token']??null;
    if(!is_string($reason) || mb_strlen(trim($reason))<3 || strlen($reason)>1000 || !is_string($token) || !preg_match('/^[a-f0-9]{64}$/D',$token) || empty($input['confirmed']))throw new RuntimeException('Bitte eine Begründung und die bewusste Bestätigung für diese Bestellaktion angeben.');
    return [trim($reason),$token];
}
function admin_order_contract(array $order): array {
    $c=ensure_order_contract($order);
    if(!$c){$policy=validated_contract_policy([],$order['billing']);query("INSERT INTO shop_contracts (order_id,policy,end_payload) VALUES (?,?,'{}')",[$order['id'],json_encode($policy)]);$c=one('SELECT * FROM shop_contracts WHERE order_id=?',[$order['id']]);}
    if(!(int)$c['started_at'] && $order['fulfillment_status']==='active' && ($service=service_for_order((int)$order['id'],(int)$order['customer_id']))){
        $row=one('SELECT created_at FROM shop_services WHERE id=?',[$service['id']]);$start=strtotime(($row['created_at']??$order['created_at']).' UTC')?:shop_now();
        query("UPDATE shop_contracts SET started_at=?,state=CASE WHEN state='pending' THEN 'active' ELSE state END WHERE order_id=?",[min(shop_now(),$start),$order['id']]);$c=one('SELECT * FROM shop_contracts WHERE order_id=?',[$order['id']]);
    }
    return $c;
}
function admin_order_store_state(int $order,string $operation,int $administrator,string $reason): void {
    if(!order_admin_state($order))query('INSERT INTO shop_order_admin (order_id) VALUES (?)',[$order]);
    $prefix=match($operation){'activate'=>'activation','cancel'=>'cancellation','delete'=>'deletion'};$time=match($operation){'activate'=>'activated','cancel'=>'cancelled','delete'=>'deleted'};
    query("UPDATE shop_order_admin SET {$time}_ts=?,{$time}_by=?,{$prefix}_reason=? WHERE order_id=?",[shop_now(),$administrator,$reason,$order]);
}
function admin_order_locked_job(int $order): ?array {
    $lock=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';$job=one('SELECT * FROM shop_jobs WHERE order_id=?'.$lock,[$order]);
    if($job && ($job['state']==='running' || (int)$job['locked_until']>=time()))throw new RuntimeException('Für diese Bestellung läuft gerade eine Einrichtung. Bitte deren Abschluss abwarten und den Anbieterzustand prüfen.');return $job;
}
function admin_order_check_resources(array $order,?array $job,bool $allowPartial=false,bool $manualEnd=false): void {
    $service=service_for_order((int)$order['id'],(int)$order['customer_id']);
    if($manualEnd){$exportLock=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';foreach(all('SELECT state,locked_until FROM shop_dns_jobs WHERE order_id=?'.$exportLock,[$order['id']]) as $dns)if($dns['state']==='running' || (int)$dns['locked_until']>=time())throw new RuntimeException('Für diese Bestellung läuft gerade eine DNS-Änderung. Bitte deren Abschluss abwarten.');$export=SchemaManager::hasTable(db(),'shop_vps_exports')?one('SELECT state FROM shop_vps_exports WHERE order_id=?'.$exportLock,[$order['id']]):null;if($export && in_array($export['state'],['building','deleting','delete_wait','cleanup'],true))throw new RuntimeException('Ein Sicherungs- oder Löschauftrag läuft bereits. Bitte dessen tatsächlichen Abschluss abwarten, bevor die manuelle Beendigung dokumentiert wird.');}
    if(!$allowPartial && !$service && $job && !module_order_intent((int)$order['id']) && !in_array($job['phase'],['start','client_ready','checkout_ready','awaiting_manual'],true))throw new RuntimeException('Die Einrichtung hat beim Anbieter bereits begonnen. Bitte zuerst die vorhandene Ressource zuordnen oder den unklaren Auftrag prüfen.');
    if(!$service && ($intent=module_order_intent((int)$order['id'])) && $intent['applied_at']){if($manualEnd){$parent=one('SELECT * FROM shop_orders WHERE id=? AND customer_id=?',[$intent['parent_order_id'],$order['customer_id']]);$service=$parent?service_for_order((int)$parent['id'],(int)$order['customer_id']):null;}else $service=module_parent_service($intent);}
    if($order['provider']==='ispconfig' && $order['domain']){$lock=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';$mailDns=one('SELECT * FROM shop_hosting_mail_dns WHERE customer_id=? AND domain=?'.$lock,[$order['customer_id'],$order['domain']]);if($mailDns && ($mailDns['state']==='running' || (int)$mailDns['locked_until']>=time()))throw new RuntimeException('Für diese Maildomain läuft gerade eine DNS-Änderung. Bitte deren Abschluss abwarten.');}
    if(!$service)return;$lock=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
    $resource=one('SELECT * FROM shop_resource_locks WHERE service_id=?'.$lock,[$service['id']]);if($resource && (int)$resource['locked_until']>=time())throw new RuntimeException('Am Kundenprodukt läuft gerade eine Änderung. Bitte deren Abschluss abwarten.');
    foreach(all("SELECT * FROM shop_server_operations WHERE service_id=? AND state NOT IN ('done','cancelled')".$lock,[$service['id']]) as $op)if($op['state']==='running' || (int)$op['locked_until']>=time() || (!$manualEnd && $op['phase']!=='start'))throw new RuntimeException('Ein laufender oder unklarer Serverauftrag muss zuerst abgeschlossen werden.');
    foreach(all("SELECT j.state,j.locked_until FROM shop_dns_jobs j WHERE j.order_id=?".$lock,[$order['id']]) as $dns)if($dns['state']==='running' || (int)$dns['locked_until']>=time())throw new RuntimeException('Für diese Bestellung läuft gerade eine DNS-Änderung. Bitte deren Abschluss abwarten.');
}
function admin_order_can_delete(array $order): bool {
    $c=one('SELECT * FROM shop_contracts WHERE order_id=?',[$order['id']]);if(!$c || !contract_closed($c) || $c['end_state']!=='done' || $c['paypal_cancel_state']!=='done')return false;
    if(!in_array($order['fulfillment_status'],['cancelled','ended'],true))return false;
    $service=service_for_order((int)$order['id'],(int)$order['customer_id']);return !$service || in_array(one('SELECT status FROM shop_services WHERE id=?',[$service['id']])['status'],['cancelled','ended'],true);
}
function admin_order_operation(int $orderId,string $operation,array $input,int $administrator): void {
    [$reason,$token]=admin_order_validate_request($administrator,$input);if(!in_array($operation,['activate','cancel','delete','restore'],true))throw new RuntimeException('Ungültige Bestellaktion.');
    // Administrator cancellations normally attest that the operator already ended the native systems.
    $manualEnd=$operation==='cancel' && (bool)($input['manual_provider_end']??true);
    db()->beginTransaction();try{
        $lock=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';$root=one('SELECT * FROM shop_orders WHERE id=?'.$lock,[$orderId]);if(!$root)throw new RuntimeException('Diese Bestellung ist nicht verfügbar.');
        if($old=one('SELECT * FROM shop_order_admin_events WHERE token=?',[$token])){if((int)$old['order_id']!==$orderId || $old['operation']!==$operation || (int)$old['admin_id']!==$administrator)throw new RuntimeException('Diese Bestätigung gehört zu einer anderen Bestellaktion.');db()->commit();return;}
        $state=order_admin_state($orderId);if($operation==='restore' && empty($state['deleted_ts']))throw new RuntimeException('Diese Bestellung ist nicht gelöscht.');if($operation!=='restore' && !empty($state['deleted_ts']))throw new RuntimeException('Diese Bestellung wurde gelöscht. Stelle sie zuerst wieder her.');
        $family=in_array($operation,['cancel','delete','restore'],true)?admin_order_family($root):[$orderId=>$root];$jobs=[];
        foreach($family as $id=>$o){$family[$id]=one('SELECT * FROM shop_orders WHERE id=?'.$lock,[$id]);$jobs[$id]=admin_order_locked_job($id);$contract=one('SELECT * FROM shop_contracts WHERE order_id=?'.$lock,[$id]);$manualResourceRecord=$manualEnd || ($operation==='delete' && admin_provider_manually_ended($contract));if($operation!=='restore')admin_order_check_resources($family[$id],$jobs[$id],$operation==='activate' || $manualResourceRecord || ($operation==='cancel' && !empty($input['provider_absence_confirmed'])),$manualResourceRecord);if($contract && (int)$contract['locked_until']>=time())throw new RuntimeException('Der Vertrag wird gerade im Hintergrund verarbeitet. Bitte kurz warten und erneut versuchen.');}
        if($operation==='activate'){
            $c=admin_order_contract($root);if(contract_closed($c) || (int)$c['cancel_at'] || in_array($root['fulfillment_status'],['cancelled','ended','active'],true) || !in_array($root['payment_status'],['pending','paid','test_paid','no_charge'],true))throw new RuntimeException('Diese Bestellung ist bereits aktiv, gekündigt oder nicht für eine Freigabe geeignet.');
            if(!one("SELECT id FROM customers WHERE id=? AND status='active'",[$root['customer_id']]))throw new RuntimeException('Das Kundenkonto ist nicht aktiv.');
            if($root['paypal_mode']!=='live' && !test_order($root))throw new RuntimeException('Für eine Sandbox-Bestellung zuerst eine Testzahlung mit bewusster Freigabe echter Ressourcen bestätigen.');
            if(empty($input['unpaid_release_confirmed']))throw new RuntimeException('Bitte die Bereitstellung unabhängig vom Zahlungsstatus ausdrücklich freigeben.');
            if($root['provider']==='ovh' && !order_payment_allows_provisioning($root) && empty($input['supplier_costs_confirmed']))throw new RuntimeException('Bitte die Kostenübernahme für die OVH-Domainbestellung bestätigen.');
            if($root['provider']!=='manual' && !integration_ready($root['provider']))throw new RuntimeException('Die Anbieter-Verbindung muss vor der manuellen Freigabe erfolgreich geprüft sein.');
            $profile=json_decode($root['profile'],true);if(!hash_equals($profile['connection_id']??'',integration_identity($root['provider'])))throw new RuntimeException('Die Anbieter-Verbindung hat sich seit der Bestellung geändert. Bitte zuerst die Zuordnung prüfen.');
            if($intent=module_order_intent($orderId)){$parent=one('SELECT * FROM shop_orders WHERE id=?',[$intent['parent_order_id']]);$pc=$parent?ensure_order_contract($parent):null;if(!$parent || !$pc || contract_closed($pc) || (int)$pc['cancel_at'] || !order_payment_allows_provisioning($parent) || module_parent_service($intent)['status']!=='active')throw new RuntimeException('Das Grundpaket muss zuerst aktiv und für die Bereitstellung freigegeben sein.');}
            $job=$jobs[$orderId];$safe=['start','client_ready','website_ready','ftp_ready','clone_wait','clone_ready','configuring','configured','disk_resize_wait','lxc_create_wait','lxc_ready','checkout_ready','zone_wait','awaiting_manual'];
            if($job && (!in_array($job['state'],['queued','waiting','waiting_domain','needs_action','sandbox'],true) || !in_array($job['phase'],$safe,true)))throw new RuntimeException('Dieser Einrichtungsauftrag muss zuerst beim Anbieter geprüft werden. Eine unklare Erstellung wird nicht wiederholt.');
            $identifier=trim((string)($input['identifier']??''));if($root['provider']==='manual' && (!$identifier || strlen($identifier)>253))throw new RuntimeException('Bitte die tatsächlich bereitgestellte Leistungskennung angeben.');
            admin_order_store_state($orderId,'activate',$administrator,$reason);
            if(!$job)query('INSERT INTO shop_jobs (order_id,state,phase,payload) VALUES (?,?,?,?)',[$orderId,$root['provider']==='manual'?'needs_action':'queued',$root['provider']==='manual'?'awaiting_manual':'start','{}']);
            else query('UPDATE shop_jobs SET state=?,last_error=NULL,locked_until=0,lock_token=NULL WHERE order_id=?',[$root['provider']==='manual'?'needs_action':($job['state']==='waiting_domain'?'waiting_domain':'queued'),$orderId]);
            query('UPDATE shop_orders SET fulfillment_status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',[$root['provider']==='manual'?'needs_action':($job && $job['state']==='waiting_domain'?'domain_verification':'queued'),$orderId]);
            customer_notification((int)$root['customer_id'],'admin-release:'.$orderId,'Bestellung manuell freigegeben','Die Bereitstellung wurde durch die Administration freigegeben. Offene Rechnungen und der Zahlungsstatus bleiben davon unberührt.','/konto/bestellungen?id='.$orderId);
        }elseif($operation==='cancel'){
            foreach($family as $id=>$o){$c=admin_order_contract($o);if($manualEnd)admin_manual_end_local($o,$c,$administrator,$reason);else{if($id===$orderId && module_order_intent($id) && !contract_closed($c))module_cancel_guard(contract_for_order($id),false);schedule_contract_cancellation($c,shop_now(),shop_now(),true);}admin_order_store_state($id,'cancel',$administrator,$reason);
                query("UPDATE shop_orders SET fulfillment_status='cancelled',updated_at=CURRENT_TIMESTAMP WHERE id=?",[$id]);query("UPDATE shop_jobs SET state='cancelled',last_error='Durch die Administration storniert.' WHERE order_id=? AND state NOT IN ('done','sandbox')",[$id]);
                if($s=service_for_order($id,(int)$o['customer_id'])){query("UPDATE shop_services SET status='cancelled' WHERE id=?",[$s['id']]);query("UPDATE shop_server_operations SET state='cancelled' WHERE service_id=? AND state IN (".($manualEnd?"'queued','waiting','needs_action'":"'queued','waiting'").")",[$s['id']]);}
                query("UPDATE shop_dns_jobs SET state='cancelled',last_error=NULL WHERE order_id=? AND state IN ('pending','needs_action')",[$id]);query("UPDATE shop_hosting_domain_verifications SET state='cancelled' WHERE order_id=?",[$id]);if($o['provider']==='ispconfig' && $o['domain'])query("UPDATE shop_hosting_mail_dns SET state='cancelled',last_error=NULL WHERE customer_id=? AND domain=? AND state IN ('pending','needs_action')",[$o['customer_id'],$o['domain']]);
                customer_notification((int)$o['customer_id'],'admin-cancel:'.$id,'Bestellung storniert',$o['product_name'].' wurde durch die Administration storniert. Eine Erstattung bereits geleisteter Zahlungen wird gesondert bearbeitet.','/konto/vertraege');
            }
        }elseif($operation==='delete'){
            foreach($family as $id=>$o){if(!admin_order_can_delete($o))throw new RuntimeException('Vor dem Löschen müssen die Bestellung und ihre Zusatzmodule storniert sowie Anbieter- und PayPal-Abschaltung vollständig abgeschlossen sein.');if(empty(order_admin_state($id)['deleted_ts'])){admin_order_store_state($id,'delete',$administrator,$reason);query('UPDATE shop_order_admin SET deletion_token=? WHERE order_id=?',[$token,$id]);}}
        }else{
            foreach($family as $id=>$o)query('UPDATE shop_order_admin SET deleted_ts=0,deleted_by=NULL,deletion_reason=NULL,deletion_token=NULL WHERE order_id=? AND deletion_token=?',[$id,$state['deletion_token']]);
        }
        query('INSERT INTO shop_order_admin_events (order_id,admin_id,operation,reason,token,created_ts) VALUES (?,?,?,?,?,?)',[$orderId,$administrator,$operation,$reason,$token,shop_now()]);audit_event('order.admin.'.$operation,$root['reference']);db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    if($operation==='activate' && $root['provider']==='manual')complete_manual_order($orderId,$identifier);
}
function handle_order_admin_action(string $action): bool {
    $operations=['admin_order_activate'=>'activate','admin_order_cancel'=>'cancel','admin_order_delete'=>'delete','admin_order_restore'=>'restore'];if(!isset($operations[$action]))return false;
    $admin=require_admin();$raw=$_POST['order_id']??null;if(!is_string($raw) || !ctype_digit($raw) || strlen($raw)>10)throw new RuntimeException('Diese Bestellung ist nicht verfügbar.');
    $input=$_POST;if($operations[$action]==='cancel')$input['manual_provider_end']=true;
    admin_order_operation((int)$raw,$operations[$action],$input,(int)$admin['id']);flash(match($operations[$action]){'activate'=>'Die Bestellung wurde ausdrücklich freigegeben. Der Zahlungsstatus bleibt unverändert; die Anbieter-Einrichtung wird geprüft und ausgeführt.','cancel'=>'Die manuelle Beendigung wurde dokumentiert. Der direkte PayPal-Abschluss wird geprüft.','delete'=>'Die Bestellung wurde aus der Bestellübersicht gelöscht. Rechnungs- und Vertragsnachweise bleiben erhalten.','restore'=>'Die Bestellung ist wieder in der Übersicht sichtbar. Ihr Vertragsstatus bleibt unverändert.'});
    if($operations[$action]==='cancel'){$checks=admin_manual_end_family_paypal((int)$raw,(int)$admin['id']);$pending=array_filter($checks,fn($r)=>!$r['done']);flash($pending?'Der Vertrag ist sofort beendet und die technische Beendigung als manuell erledigt dokumentiert. Der PayPal-Abschluss ist noch offen; bitte im Vertrag direkt erneut prüfen.':'Der Vertrag wurde mit sofortiger Wirkung gekündigt. Die manuelle Beendigung ist abgeschlossen; die PayPal-Abos sind beendet beziehungsweise es besteht kein Abo.',$pending?'error':'success');}
    redirect(($_POST['return_to']??'')==='contracts'?'/admin/vertraege?id='.(int)$raw:'/admin/bestellungen?id='.(int)$raw);
}
