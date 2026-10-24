<?php
function handle_commerce_action(string $action): bool {
    switch($action){
        case 'commerce_settings':require_admin();save_commerce_settings($_POST);flash('Shop-, Rechnungs- und Bereitstellungseinstellungen gespeichert.');redirect('/admin/shop');
        case 'smtp_save':require_admin();save_smtp_settings($_POST);flash('SMTP-Zugang verschlüsselt gespeichert. Bitte die Verbindung prüfen.');redirect('/admin/email');
        case 'smtp_test':require_admin();rate_limit('smtp_test',10,900);test_smtp_connection();flash('SMTP-Verbindung und Anmeldung erfolgreich geprüft. Es wurde keine E-Mail versendet.');redirect('/admin/email');
        case 'smtp_test_mail':
            require_admin();if(!smtp_ready())throw new RuntimeException('Bitte zuerst die SMTP-Verbindung erfolgreich prüfen.');$recipient=text_field('recipient',255);if(!filter_var($recipient,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Bitte eine gültige Empfängeradresse für die Testmail eintragen.');rate_limit('smtp_test_mail',5,900);queue_shop_mail('test:'.bin2hex(random_bytes(12)),$recipient,'Testmail von Server Management','Der E-Mail-Versand deines Shops wurde erfolgreich eingerichtet.');flash('Die Testmail wurde für den Versand vorgemerkt.');redirect('/admin/email');
        case 'mail_retry':
            require_admin();$id=(int)($_POST['mail_id']??0);$changed=query("UPDATE shop_mail_queue SET state='pending',available_at=0,locked_until=0,last_error=NULL WHERE id=? AND state='needs_action' AND locked_until<?",[$id,time()]);if($changed->rowCount()!==1)throw new RuntimeException('Diese E-Mail kann gerade nicht erneut vorgemerkt werden.');audit_event('mail.retry',(string)$id);flash('Die E-Mail ist erneut zum Versand vorgemerkt.');redirect('/admin/email');
        case 'quote_save':require_admin();$id=save_quote((int)($_POST['request_id']??0),$_POST);flash('Angebotsentwurf gespeichert. Prüfe ihn vor dem Versand.');redirect('/admin/angebote?id='.$id);
        case 'quote_publish':require_admin();$id=(int)($_POST['quote_id']??0);publish_quote($id);flash('Das Angebot wurde im Kundenkonto veröffentlicht und die E-Mail mit Zahlungslink zum Versand vorgemerkt.');redirect('/admin/angebote?id='.$id);
        case 'quote_cancel':
            require_admin();$id=(int)($_POST['quote_id']??0);$changed=query("UPDATE shop_quotes SET status='cancelled' WHERE id=? AND status IN ('draft','sent') AND order_id IS NULL",[$id]);if($changed->rowCount()!==1)throw new RuntimeException('Ein Angebot mit Bestellung kann hier nicht zurückgenommen werden. Bitte den Zahlungsstatus prüfen.');audit_event('quote.cancelled',(string)$id);flash('Das Angebot wurde zurückgenommen.');redirect('/admin/angebote?id='.$id);
        case 'faq_save':require_admin();$id=faq_save((int)($_POST['id']??0),$_POST);flash('FAQ-Eintrag gespeichert.');redirect('/admin/faq?id='.$id);
        case 'commerce_worker':require_admin();run_shop_worker(3);flash('Bereitstellung, Rechnungen und Versand geprüft.');redirect('/admin/email');
        default:return false;
    }
}
