<?php
function handle_contract_action(string $action): bool {
    switch($action){
        case 'admin_manual_paypal_close':
            $administrator=require_admin();$raw=$_POST['order_id']??null;if(!is_string($raw) || !ctype_digit($raw) || strlen($raw)>10)throw new RuntimeException('Vertrag nicht gefunden.');
            $results=admin_manual_end_family_paypal((int)$raw,(int)$administrator['id']);$pending=array_filter($results,fn($r)=>!$r['done']);flash($pending?'Die manuelle technische Beendigung bleibt abgeschlossen. Mindestens ein PayPal-Abo muss noch geprüft werden.':'PayPal-Abschluss direkt geprüft und abgeschlossen.',$pending?'error':'success');redirect('/admin/vertraege?id='.(int)$raw);
        case 'admin_contract_cancel':
            $administrator=require_admin();$raw=$_POST['order_id']??null;if(!is_string($raw) || !ctype_digit($raw) || strlen($raw)>10)throw new RuntimeException('Vertrag nicht gefunden.');
            admin_contract_schedule_cancellation((int)$raw,$_POST,(int)$administrator['id']);flash('Die Kündigung zum Laufzeitende wurde vorgemerkt. Die Bestätigung für den Kunden ist zum Versand vorgemerkt.');redirect('/admin/vertraege?id='.(int)$raw);
        case 'contract_cancel':
            rate_limit('cancellation',15,3600);if(!empty($_POST['website']))throw new RuntimeException('Die Kündigung konnte nicht zugeordnet werden.');
            $customer=user();$result=submit_cancellation($_POST,$customer);$_SESSION['cancellation_receipt']=$result['token'];redirect('/kuendigen?eingegangen=1');
        case 'contract_cancel_approve':
            require_admin();if(!isset($_POST['identity_verified']))throw new RuntimeException('Bitte die Zuordnung der Kündigung zum Kunden bestätigen.');
            $date=DateTimeImmutable::createFromFormat('!Y-m-d',(string)($_POST['effective_date']??''),new DateTimeZone('Europe/Berlin'));if(!$date || $date->format('Y-m-d')!==(string)($_POST['effective_date']??''))throw new RuntimeException('Bitte ein Beendigungsdatum angeben.');
            approve_cancellation((int)($_POST['request_id']??0),(int)($_POST['order_id']??0),$date->setTime(23,59,59)->getTimestamp());flash('Kündigung übernommen und Bestätigung vorgemerkt.');redirect('/admin/vertraege');
        case 'payment_refresh':
            $customer=require_user();$order=one('SELECT * FROM shop_orders WHERE id=? AND customer_id=?',[(int)($_POST['order_id']??0),$customer['id']]);if(!$order)throw new RuntimeException('Bestellung nicht gefunden.');rate_limit('payment_refresh',15,900);reconcile_paypal_order($order);flash('Der Zahlungsstatus wurde mit PayPal abgeglichen.');redirect('/konto/rechnungen');
        case 'contract_cancel_manual':
            require_admin();if(!isset($_POST['manual_confirmed']))throw new RuntimeException('Bitte die persönlich abgeschlossene Vertragsbeendigung bestätigen.');
            $date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',(string)($_POST['effective_datetime']??''),new DateTimeZone('Europe/Berlin'));if(!$date || $date->format('Y-m-d\TH:i')!==(string)($_POST['effective_datetime']??''))throw new RuntimeException('Bitte den tatsächlichen Beendigungszeitpunkt angeben.');
            complete_manual_cancellation((int)($_POST['request_id']??0),$date->getTimestamp());flash('Persönliche Bearbeitung dokumentiert und Bestätigung vorgemerkt.');redirect('/admin/vertraege');
        case 'contract_manual_end':
            require_admin();$c=contract_for_order((int)($_POST['order_id']??0));if(!isset($_POST['manual_confirmed']) || !$c || !contract_closed($c) || $c['provider']!=='manual')throw new RuntimeException('Bitte eine beendete, persönlich bereitgestellte Leistung und deren tatsächliche Abschaltung bestätigen.');
            query("UPDATE shop_contracts SET end_state='done',last_error=NULL,next_check_at=0 WHERE order_id=?",[$c['order_id']]);audit_event('contract.provider.manually_ended',$c['reference']);flash('Persönliche Abschaltung dokumentiert.');redirect('/admin/vertraege');
        case 'invoice_payment_return':
            $customer=require_user();confirm_invoice_checkout((int)($_POST['invoice_id']??0),(int)$customer['id'],(string)($_POST['token']??''));flash('PayPal hat die Rechnungszahlung bestätigt.');redirect('/konto/rechnungen');
        case 'invoice_checkout':
            $customer=require_user();rate_limit('invoice_checkout',10,900);$url=start_invoice_checkout((int)($_POST['invoice_id']??0),(int)$customer['id']);approval_redirect($url);
        case 'contract_retry':
            require_admin();query("UPDATE shop_contracts SET next_check_at=0,last_error=NULL,end_state=CASE WHEN end_state='needs_action' THEN 'pending' ELSE end_state END WHERE order_id=? AND locked_until<?",[(int)($_POST['order_id']??0),shop_now()]);flash('Der Vertrag wird beim nächsten Hintergrundlauf erneut geprüft.');redirect(($_POST['return_to']??'')==='contracts'?'/admin/vertraege?id='.(int)($_POST['order_id']??0):'/admin/vertraege');
        default:return false;
    }
}
