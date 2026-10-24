<?php
function admin_contract_states(): array {
    return ['active'=>'Laufend','pending'=>'Unbestätigt','confirmed'=>'Bestätigt','cancellation'=>'Kündigung vorgemerkt','ended'=>'Beendet','cancelled'=>'Storniert'];
}
function admin_contract_state_sql(): string {
    return "CASE WHEN COALESCE(a.cancelled_ts,0)>0 OR c.state='unpaid_cancelled' OR o.fulfillment_status='cancelled' THEN 'cancelled' WHEN c.state='ended' OR o.fulfillment_status='ended' THEN 'ended' WHEN c.state='cancel_requested' OR c.cancel_at>0 THEN 'cancellation' WHEN c.state IN ('active','test') OR COALESCE(c.started_at,0)>0 OR o.fulfillment_status='active' THEN 'active' WHEN o.payment_status IN ('paid','test_paid','no_charge') OR COALESCE(a.activated_ts,0)>0 THEN 'confirmed' ELSE 'pending' END";
}
function admin_contract_joins(): string {
    return ' FROM shop_orders o JOIN customers u ON u.id=o.customer_id LEFT JOIN shop_contracts c ON c.order_id=o.id LEFT JOIN shop_order_admin a ON a.order_id=o.id LEFT JOIN shop_jobs j ON j.order_id=o.id LEFT JOIN shop_module_intents m ON m.order_id=o.id';
}
function admin_contract_attention_sql(): string {return "(COALESCE(c.last_error,'')<>'' OR c.end_state='needs_action' OR j.state='needs_action' OR o.payment_status='review')";}
function admin_contract_select(): string {
    return 'SELECT o.*,c.policy,c.state AS contract_state,c.started_at,c.paid_until,c.next_due_at,c.cancel_requested_at,c.cancel_at,c.paypal_cancel_state,c.end_state,c.last_error,c.locked_until,u.first_name,u.last_name,u.email,u.company,COALESCE(a.activated_ts,0) AS activated_ts,COALESCE(a.cancelled_ts,0) AS cancelled_ts,COALESCE(a.deleted_ts,0) AS deleted_ts,j.state AS job_state,j.phase AS job_phase,j.last_error AS provisioning_error,m.parent_order_id,'.admin_contract_state_sql().' AS admin_state,CASE WHEN '.admin_contract_attention_sql().' THEN 1 ELSE 0 END AS needs_attention'.admin_contract_joins();
}
function admin_contract_filter_text(array $input,string $key,int $length=200): string {return is_string($input[$key]??null)?mb_substr(trim($input[$key]),0,$length):'';}
function admin_contract_filters(array $input): array {
    $customer=admin_contract_filter_text($input,'kunde',10);$page=admin_contract_filter_text($input,'seite',7);$state=admin_contract_filter_text($input,'status',30);
    return ['q'=>admin_contract_filter_text($input,'q'),'kunde'=>ctype_digit($customer)?(int)$customer:0,'status'=>isset(admin_contract_states()[$state]) || $state==='attention'?$state:'','seite'=>max(1,ctype_digit($page)?(int)$page:1)];
}
function admin_contract_where(array $filters): array {
    $where=[];$args=[];
    if($filters['kunde']){$where[]='o.customer_id=?';$args[]=$filters['kunde'];}
    if($filters['q']!==''){$like='%'.str_replace(['!','%','_'],['!!','!%','!_'],$filters['q']).'%';$parts=[];$name=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?"(u.first_name || ' ' || u.last_name)":"CONCAT(u.first_name,' ',u.last_name)";foreach(['o.reference','o.product_name','o.domain','u.first_name','u.last_name','u.email','u.company',$name] as $field){$parts[]=$field." LIKE ? ESCAPE '!'";$args[]=$like;}$where[]='('.implode(' OR ',$parts).')';}
    return [$where?' WHERE '.implode(' AND ',$where):'', $args];
}
function admin_contract_overview(array $input=[],int $perPage=25): array {
    $filters=admin_contract_filters($input);[$where,$args]=admin_contract_where($filters);$counts=array_fill_keys(array_keys(admin_contract_states()),0);$counts['attention']=0;
    foreach(all('SELECT '.admin_contract_state_sql().' AS admin_state,COUNT(*) AS total,SUM(CASE WHEN '.admin_contract_attention_sql().' THEN 1 ELSE 0 END) AS attention'.admin_contract_joins().$where.' GROUP BY '.admin_contract_state_sql(),$args) as $group){$counts[$group['admin_state']]=(int)$group['total'];$counts['attention']+=(int)$group['attention'];}
    $total=$filters['status']!==''?$counts[$filters['status']]:array_sum(array_diff_key($counts,['attention'=>true]));$perPage=max(1,min(100,$perPage));$pages=max(1,(int)ceil($total/$perPage));$filters['seite']=min($filters['seite'],$pages);
    if($filters['status']!==''){$where.=($where?' AND ':' WHERE ').($filters['status']==='attention'?admin_contract_attention_sql():admin_contract_state_sql().'=?');if($filters['status']!=='attention')$args[]=$filters['status'];}
    $rows=all(admin_contract_select().$where.' ORDER BY o.id DESC LIMIT '.$perPage.' OFFSET '.(($filters['seite']-1)*$perPage),$args);
    return compact('filters','counts','rows','total','pages');
}
function admin_contract_url(array $params=[]): string {return '/admin/vertraege'.($params?'?'.http_build_query(array_filter($params,fn($v)=>$v!=='' && $v!==0 && $v!==null),'','&',PHP_QUERY_RFC3986):'');}
function admin_contract_badge(string $state): string {return '<span class="badge contract-status contract-status-'.e($state).'">'.e(admin_contract_states()[$state]??$state).'</span>';}
function admin_contract_payment_label(string $state): string {return ['pending'=>'Zahlung offen','paid'=>'Bezahlt','test_paid'=>'Testzahlung bestätigt','no_charge'=>'Ohne Zahlbetrag','review'=>'Zahlung prüfen','cancelled'=>'Zahlung storniert','expired'=>'Zahlung abgelaufen'][$state]??'Zahlungsstatus prüfen';}
function admin_contract_timestamp(mixed $time): string {return (int)$time>0?date('d.m.Y H:i',(int)$time):'–';}
function admin_contract_receipt_source(array $request): ?string {
    if(empty($request['order_id']) || !is_string($request['submission_key']??null))return null;
    foreach(all("SELECT token FROM shop_order_admin_events WHERE order_id=? AND operation='cancel_term'",[$request['order_id']]) as $event)if(hash_equals(hash('sha256','admin-contract:'.$event['token']),$request['submission_key']))return 'Durch die Administration zum Laufzeitende gekündigt.';
    return null;
}
function admin_contract_cancellation_inbox(array $input=[]): array {
    $filters=admin_contract_filters($input);$state=admin_contract_filter_text($input,'eingang',30);$page=admin_contract_filter_text($input,'eingangsseite',7);$where=[];$args=[];
    $stateSql="CASE WHEN r.state='review' THEN 'review' WHEN r.state='completed' OR c.state IN ('ended','unpaid_cancelled') THEN 'completed' ELSE 'scheduled' END";
    if($filters['kunde']){$where[]='r.customer_id=?';$args[]=$filters['kunde'];}
    if($filters['q']!==''){$like='%'.str_replace(['!','%','_'],['!!','!%','!_'],$filters['q']).'%';$where[]="(r.name LIKE ? ESCAPE '!' OR r.email LIKE ? ESCAPE '!' OR r.reference LIKE ? ESCAPE '!' OR r.contract_reference LIKE ? ESCAPE '!')";array_push($args,$like,$like,$like,$like);}
    if(in_array($state,['review','scheduled','completed'],true)){$where[]=$stateSql.'=?';$args[]=$state;}
    $from=' FROM shop_cancellations r LEFT JOIN shop_contracts c ON c.order_id=r.order_id';$clause=$where?' WHERE '.implode(' AND ',$where):'';$total=(int)query('SELECT COUNT(*)'.$from.$clause,$args)->fetchColumn();$pages=max(1,(int)ceil($total/20));$page=min($pages,max(1,ctype_digit($page)?(int)$page:1));
    return ['rows'=>all('SELECT r.*,'.$stateSql.' AS display_state'.$from.$clause.' ORDER BY r.received_ts DESC,r.id DESC LIMIT 20 OFFSET '.(($page-1)*20),$args),'total'=>$total,'pages'=>$pages,'page'=>$page,'state'=>$state];
}
function admin_contract_schedule_cancellation(int $orderId,array $input,int $administrator): void {
    [$reason,$token]=admin_order_validate_request($administrator,$input);$requestedDate=admin_contract_filter_text($input,'requested_date',20);$requested=0;
    if($requestedDate!==''){$date=DateTimeImmutable::createFromFormat('!Y-m-d',$requestedDate,new DateTimeZone('Europe/Berlin'));if(!$date || $date->format('Y-m-d')!==$requestedDate)throw new RuntimeException('Bitte ein gültiges gewünschtes Vertragsende angeben.');$requested=$date->getTimestamp();}
    db()->beginTransaction();try{
        $lock=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';$order=one('SELECT * FROM shop_orders WHERE id=?'.$lock,[$orderId]);if(!$order)throw new RuntimeException('Vertrag nicht gefunden.');
        if($event=one('SELECT * FROM shop_order_admin_events WHERE token=?',[$token])){if((int)$event['order_id']!==$orderId || $event['operation']!=='cancel_term' || (int)$event['admin_id']!==$administrator)throw new RuntimeException('Diese Bestätigung gehört zu einer anderen Vertragsaktion.');db()->commit();return;}
        $c=one('SELECT * FROM shop_contracts WHERE order_id=?'.$lock,[$orderId]);$control=order_admin_state($orderId);
        if(!$c || contract_closed($c) || (int)$c['cancel_at'] || !(int)$c['started_at'] || !billing_months($order['billing']) || in_array($order['fulfillment_status'],['cancelled','ended'],true) || !empty($control['deleted_ts']) || !empty($control['cancelled_ts']))throw new RuntimeException('Nur laufende Verträge ohne vorgemerkte Kündigung können zum Laufzeitende gekündigt werden. Für unbestätigte Bestellungen steht die Stornierung zur Verfügung.');
        foreach(admin_order_family($order) as $related){one('SELECT id FROM shop_orders WHERE id=?'.$lock,[$related['id']]);$relatedContract=one('SELECT locked_until FROM shop_contracts WHERE order_id=?'.$lock,[$related['id']]);if($relatedContract && (int)$relatedContract['locked_until']>=time())throw new RuntimeException('Der Vertrag oder ein Zusatzmodul wird gerade verarbeitet. Bitte kurz warten und erneut versuchen.');admin_order_locked_job((int)$related['id']);}
        $owner=one('SELECT * FROM customers WHERE id=?',[$order['customer_id']]);if(!$owner)throw new RuntimeException('Kundenkonto nicht gefunden.');
        $contract=contract_for_order($orderId);$now=shop_now();$effective=cancellation_effective_at($contract,$now,$requested);$key=hash('sha256','admin-contract:'.$token);$receiptToken=cancellation_token($key);
        schedule_contract_cancellation($contract,$now,$effective);
        query('INSERT INTO shop_cancellations (reference,submission_key,order_id,customer_id,name,email,contract_reference,kind,reason,requested_date,received_ts,effective_ts,state,receipt_token_hash) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',['OC-K-'.date('ym').'-'.strtoupper(bin2hex(random_bytes(5))),$key,$orderId,$owner['id'],trim($owner['first_name'].' '.$owner['last_name']),$owner['email'],$order['reference'],'ordinary',$reason,$requestedDate,$now,$effective,'scheduled',hash('sha256',$receiptToken)]);
        $id=(int)db()->lastInsertId();$request=one('SELECT * FROM shop_cancellations WHERE id=?',[$id]);
        query('INSERT INTO shop_order_admin_events (order_id,admin_id,operation,reason,token,created_ts) VALUES (?,?,?,?,?,?)',[$orderId,$administrator,'cancel_term',$reason,$token,$now]);
        queue_shop_mail('cancellation:'.$id,$owner['email'],'Kündigungsbestätigung '.$request['reference'],cancellation_receipt($request)."\nBeleg speichern:\n".Config::FRONTPANEL_SITE_URL.'/kuendigen?beleg='.$receiptToken);
        customer_notification((int)$owner['id'],'cancellation:'.$id,'Kündigung eingegangen','Vertragsende: '.date('d.m.Y',$effective),'/konto/vertraege?id='.$orderId);
        audit_event('contract.admin.cancel_scheduled',$order['reference']);db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}
