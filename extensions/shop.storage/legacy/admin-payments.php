<?php
function payment_attestation(int $order): ?array {return one('SELECT * FROM shop_payment_attestations WHERE order_id=? ORDER BY id DESC LIMIT 1',[$order]);}
function test_order(array $order): bool {return (bool)one("SELECT id FROM shop_payment_attestations WHERE order_id=? AND source='test'",[$order['id']]);}
function order_payment_allows_provisioning(array $order): bool {
    $contract=one('SELECT * FROM shop_contracts WHERE order_id=?',[$order['id']]);if(in_array($order['fulfillment_status'],['cancelled','ended'],true) || contract_closed($contract) || ($contract && (int)$contract['cancel_at']>0 && (int)$contract['cancel_at']<=shop_now()))return false;
    if(admin_order_released($order))return true;
    if(zero_base_order($order))return zero_base_payment_ready($order);
    if(pricing_free_entitled($order))return true;
    if($order['payment_status']==='test_paid' && $order['provider']!=='ovh')return (bool)one("SELECT a.id FROM shop_payment_attestations a JOIN shop_payments p ON p.id=a.payment_id WHERE a.order_id=? AND a.source='test' AND a.allow_provisioning=1 AND p.status='test_confirmed'",[$order['id']]);
    if($order['paypal_mode']!=='live')return false;
    if($order['payment_status']==='paid')return (bool)one("SELECT id FROM shop_payments WHERE order_id=? AND status='completed' AND paypal_mode='live'",[$order['id']]);
    return false;
}
function checked_manual_payment_order(int $id,int $invoice): array {
    $o=one('SELECT * FROM shop_orders WHERE id=?',[$id]);if(!$o || in_array($o['payment_status'],['cancelled','expired','refunded','review','test_paid'],true) || in_array($o['fulfillment_status'],['cancelled','ended'],true) || contract_closed(one('SELECT * FROM shop_contracts WHERE order_id=?',[$id])))throw new RuntimeException('Diese Bestellung kann nicht manuell als bezahlt bestätigt werden.');
    if($invoice){$r=one("SELECT * FROM shop_receivables WHERE invoice_id=? AND order_id=? AND state='open'",[$invoice,$id]);if(!$r)throw new RuntimeException('Die ausgewählte Rechnung ist nicht mehr offen.');}
    elseif($o['payment_status']!=='pending')throw new RuntimeException('Die Zahlung ist bereits bestätigt. Bitte gegebenenfalls eine offene Folgerechnung auswählen.');
    return module_order_view($o,$invoice?$r['kind']:'initial');
}
function inspect_before_manual_payment(array $order): void {
    if(!$order['payment_id'])return;$paypal=checked_order_paypal($order);
    if($order['payment_kind']==='subscription'){$s=checked_subscription($order,$paypal);if(!in_array($s['status']??'',['APPROVAL_PENDING','CANCELLED','EXPIRED'],true))throw new RuntimeException('Dieses PayPal-Abo ist bereits freigegeben. Bitte zuerst „Zahlungsstatus prüfen“ verwenden und eine laufende automatische Abbuchung bei PayPal klären.');}
    else{$r=$paypal->call('GET','/v2/checkout/orders/'.rawurlencode($order['payment_id']));validate_paypal_order_identity($order,$r);if(!in_array($r['status']??'',['CREATED','SAVED','VOIDED'],true))throw new RuntimeException('PayPal meldet bereits eine Freigabe oder Zahlung. Bitte zuerst den PayPal-Zahlungsstatus abgleichen.');}
}
function admin_confirm_payment(int $orderId,array $input,int $administrator): void {
    if(!one("SELECT id FROM users WHERE id=? AND role='admin' AND active='y'",[$administrator]))throw new RuntimeException('Diese Aktion erfordert einen aktiven Administrator.');
    $source=(string)($input['source']??'test');$reason=trim((string)($input['reason']??''));$reference=trim((string)($input['reference']??''));$token=(string)($input['confirmation_token']??'');$invoice=(int)($input['invoice_id']??0);$allow=isset($input['allow_provisioning']);
    if(!in_array($source,['manual','test'],true) || mb_strlen($reason)<8 || strlen($reason)>1000 || !$reference || strlen($reference)>120 || preg_match('/[\r\n\x00]/',$reference) || !preg_match('/^[a-f0-9]{64}$/D',$token) || !isset($input['confirmed']))throw new RuntimeException('Bitte Art, Referenz, Begründung und die bewusste Bestätigung angeben.');
    if($old=one('SELECT order_id FROM shop_payment_attestations WHERE token=?',[$token])){if((int)$old['order_id']!==$orderId)throw new RuntimeException('Die Bestätigung wurde bereits verwendet.');return;}
    $order=checked_manual_payment_order($orderId,$invoice);if($source==='manual' && $order['paypal_mode']!=='live')throw new RuntimeException('Echte Zahlungseingänge können nur für Live-Bestellungen bestätigt werden.');if($source==='test' && $invoice)throw new RuntimeException('Testzahlungen sind für neue Testbestellungen vorgesehen.');if($source==='test' && $allow && $order['provider']==='ovh')throw new RuntimeException('Eine Testzahlung löst keine kostenpflichtige Domainregistrierung aus. Bestehende Domains kannst du unter Ressourcen zuordnen.');
    inspect_before_manual_payment($order);
    if($source==='manual'){
        record_paid($order,'MANUAL-'.substr($token,0,40),(int)$order['amount_cents'],$order['currency'],null,['invoice_id'=>$invoice?:null,'attestation'=>['admin_id'=>$administrator,'source'=>$source,'reference'=>$reference,'reason'=>$reason,'token'=>$token,'invoice_id'=>$invoice?:null,'allow_provisioning'=>1]]);
    }else{
        db()->beginTransaction();try{
            $lock=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';one('SELECT id FROM shop_orders WHERE id=?'.$lock,[$orderId]);$order=checked_manual_payment_order($orderId,0);
            query("INSERT INTO shop_payments (order_id,paypal_mode,transaction_id,amount_cents,currency,status) VALUES (?,?,?,?,?,'test_confirmed')",[$orderId,$order['paypal_mode'],'TEST-ADMIN-'.substr($token,0,40),$order['amount_cents'],$order['currency']]);$payment=(int)db()->lastInsertId();
            query("INSERT INTO shop_payment_attestations (order_id,payment_id,admin_id,source,reference,reason,token,allow_provisioning) VALUES (?,?,?,'test',?,?,?,?)",[$orderId,$payment,$administrator,$reference,$reason,$token,$allow?1:0]);
            foreach(all("SELECT invoice_id FROM shop_receivables WHERE order_id=? AND state='open' AND kind='initial'",[$orderId]) as $r)void_open_invoice((int)$r['invoice_id'],'Bestellung durch den Administrator in einen Test ohne echten Zahlungseingang umgewandelt.');
            query("UPDATE shop_orders SET payment_status='test_paid',fulfillment_status=?,updated_at=CURRENT_TIMESTAMP WHERE id=?",[$allow?'queued':'sandbox',$orderId]);$contract=ensure_order_contract($order);if($contract)query("UPDATE shop_contracts SET state='test',started_at=?,paid_until=?,next_due_at=0,next_check_at=? WHERE order_id=?",[shop_now(),billing_months($order['billing'])?add_contract_months(shop_now(),billing_months($order['billing'])):0,shop_now()+300,$orderId]);
            if(!one('SELECT id FROM shop_jobs WHERE order_id=?',[$orderId]))query("INSERT INTO shop_jobs (order_id,state,payload) VALUES (?,?,'{}')",[$orderId,$allow?'queued':'sandbox']);
            $copy=$order;$copy['paypal_mode']='sandbox';invoice_for_payment(one('SELECT * FROM shop_payments WHERE id=?',[$payment]),$copy);
            if($intent=module_order_intent($orderId)){query('UPDATE shop_contracts SET paid_until=?,next_due_at=? WHERE order_id=?',[$intent['initial_until'],$intent['initial_until'],$orderId]);module_note_payment($order,['kind'=>'initial']);}
            customer_notification((int)$order['customer_id'],'admin-test:'.$payment,'Testzahlung bestätigt','Diese Bestellung läuft als Test ohne echten Zahlungseingang. '.($allow?'Die Einrichtung auf dem zugeordneten System ist freigegeben.':'Es wird keine echte Ressource eingerichtet.'),'/konto/bestellungen?id='.$orderId);db()->commit();
        }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if(one('SELECT id FROM shop_payment_attestations WHERE token=?',[$token]))return;throw $e;}
    }
    audit_event('payment.admin.'.$source,$order['reference']);
}
function admin_payment_form(array $order): void {
    if(test_order($order)){echo '<div class="info-box"><span><strong>Testzahlung · kein echter Zahlungseingang.</strong> Eine freigegebene Testbereitstellung verwendet echte Serverressourcen.</span></div>';return;}
    $open=all("SELECT r.invoice_id,i.number FROM shop_receivables r JOIN shop_invoices i ON i.id=r.invoice_id WHERE r.order_id=? AND r.state='open'",[$order['id']]);if($order['payment_status']!=='pending' && !$open)return;
    ?>
    <details class="job-recovery"><summary>Zahlung als Administrator bestätigen</summary><form method="post"><?= csrf() ?><input type="hidden" name="action" value="admin_payment_confirm"><input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>"><input type="hidden" name="confirmation_token" value="<?= bin2hex(random_bytes(32)) ?>">
    <label class="field"><span>Art der Bestätigung</span><select name="source"><option value="test">Testzahlung – ohne echten Geldfluss</option><option value="manual">Tatsächlichen Zahlungseingang manuell buchen</option></select></label>
    <?php if($order['payment_status']!=='pending'): ?><label class="field"><span>Offene Rechnung</span><select name="invoice_id"><?php foreach($open as $r): ?><option value="<?= (int)$r['invoice_id'] ?>"><?= e($r['number']) ?></option><?php endforeach ?></select></label><?php endif ?>
    <label class="field"><span>Referenz / Beleg</span><input name="reference" maxlength="120" required placeholder="z. B. Testlauf 1 oder Bankbeleg"></label><label class="field"><span>Begründung</span><textarea name="reason" minlength="8" maxlength="1000" required></textarea></label>
    <?php if($order['provider']!=='ovh'): ?><label class="checkbox-field"><input type="checkbox" name="allow_provisioning"><span>Bei einer Testzahlung auf echten Servern bereitstellen. Das belegt Ressourcen; es wurde kein Geld bezahlt.</span></label><?php else: ?><p>Testzahlungen registrieren keine Domain. Bereits registrierte Domains werden unter „Ressourcen“ zugeordnet.</p><?php endif ?>
    <label class="checkbox-field"><input type="checkbox" name="confirmed" required><span>Ich bestätige die Angaben. Echte manuelle Zahlungseingänge starten die reguläre Bereitstellung; Testzahlungen bleiben als Test gekennzeichnet.</span></label><button class="button button-outline">Bestätigung speichern</button></form></details>
    <?php
}
