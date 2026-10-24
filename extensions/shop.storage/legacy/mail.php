<?php
require_once __DIR__.'/lib/phpmailer/src/Exception.php';
require_once __DIR__.'/lib/phpmailer/src/PHPMailer.php';
require_once __DIR__.'/lib/phpmailer/src/SMTP.php';
class ShopSmtpConnection extends PHPMailer\PHPMailer\SMTP {
    public string $stage='connect';
    public array $failure=[];
    public bool $certificateFailure=false;
    private function result(bool $ok): bool {if(!$ok)$this->failure=$this->getError();return $ok;}
    public function connect($host,$port=null,$timeout=30,$options=[]) {$this->stage='connect';return $this->result(parent::connect($host,$port,$timeout,$options));}
    public function hello($host='') {$this->stage='hello';return $this->result(parent::hello($host));}
    public function startTLS() {$this->stage='starttls';return $this->result(parent::startTLS());}
    public function authenticate($username,$password,$authtype=null,$OAuth=null) {$this->stage='authenticate';return $this->result(parent::authenticate($username,$password,$authtype,$OAuth));}
    protected function errorHandler($errno,$errmsg,$errfile='',$errline=0) {
        if(preg_match('/certificate|peer name|did not match|unknown ca/i',$errmsg))$this->certificateFailure=true;
        parent::errorHandler($errno,$errmsg,$errfile,$errline);
    }
}
function smtp_settings(): array {$v=shop_meta_get('smtp_config');return $v?secret_open($v,'commerce:smtp'):['enabled'=>false,'host'=>'','port'=>587,'security'=>'tls','username'=>'','password'=>'','from_email'=>'','from_name'=>'Server Management','reply_to'=>''];}
function smtp_identity(array $s): string {return hash('sha256',json_encode($s));}
function smtp_ready(): bool {$s=smtp_settings();return $s['enabled'] && $s['host'] && $s['from_email'] && hash_equals(smtp_identity($s),shop_meta_get('smtp_test_identity',''));}
function smtp_last_test(): ?array {
    $test=json_decode(shop_meta_get('smtp_last_test',''),true);
    return is_array($test) && hash_equals(smtp_identity(smtp_settings()),$test['identity']??'')?$test:null;
}
function smtp_connection_failure(?ShopSmtpConnection $smtp,array $settings): array {
    // Only fixed messages leave this function. SMTP replies can contain credentials.
    $stage=$smtp?->stage??'connect';$error=$smtp?->failure??[];
    $detail=strtolower(implode(' ',array_map('strval',$error)));$code=(string)($error['smtp_code']??'');
    $endpoint=(preg_match('/^[a-zA-Z0-9_.:-]{1,253}$/D',$settings['host']??'')?$settings['host']:'SMTP-Server').':'.(int)($settings['port']??0);
    if($smtp?->certificateFailure)return ['stage'=>'tls','cause'=>'certificate','message'=>'Das TLS-Zertifikat von '.$endpoint.' konnte nicht bestätigt werden. Zertifikatskette, Gültigkeit und passenden Servernamen prüfen.'];
    if($stage==='authenticate')return ['stage'=>$stage,'cause'=>'authentication','message'=>'Die verschlüsselte Verbindung steht, aber die SMTP-Anmeldung konnte nicht abgeschlossen werden. SMTP-Benutzername (meist die vollständige E-Mail-Adresse), Passwort und SMTP-Freigabe des Postfachs prüfen.'];
    if($stage==='starttls')return ['stage'=>$stage,'cause'=>'tls','message'=>'Der SMTP-Server wurde erreicht, aber STARTTLS konnte nicht aufgebaut werden. STARTTLS-Unterstützung, Port und TLS-Konfiguration des Mailservers prüfen.'];
    if(preg_match('/getaddrinfo|php_network_getaddresses|name or service not known|nodename nor servname|no such host|temporary failure in name resolution/',$detail))return ['stage'=>'dns','cause'=>'dns','message'=>'Der SMTP-Hostname konnte nicht aufgelöst werden. Hostname und DNS-Einträge prüfen.'];
    if(in_array($code,['110','10060'],true) || str_contains($detail,'timed out') || str_contains($detail,'timeout'))return ['stage'=>$stage,'cause'=>'timeout','message'=>'Zeitüberschreitung beim Verbindungsaufbau zu '.$endpoint.'. Der SMTP-Server antwortet nicht. SMTP-Dienst, Listener und Firewall prüfen; TLS-Aushandlung und Anmeldung wurden noch nicht erreicht.'];
    if(in_array($code,['111','10061'],true) || str_contains($detail,'connection refused'))return ['stage'=>$stage,'cause'=>'refused','message'=>'Die Verbindung zu '.$endpoint.' wurde abgewiesen. Prüfen, ob der SMTP-Dienst auf diesem Port lauscht und die Firewall den Zugriff erlaubt. Die Anmeldung wurde noch nicht erreicht.'];
    if(in_array($code,['101','113'],true) || preg_match('/no route|network is unreachable/',$detail))return ['stage'=>$stage,'cause'=>'network','message'=>'Der SMTP-Server '.$endpoint.' ist vom Shop aus nicht erreichbar. Netzwerkverbindung und Firewall prüfen.'];
    if($stage==='hello' || in_array($code,['421','554'],true))return ['stage'=>$stage,'cause'=>'protocol','message'=>'Der Server '.$endpoint.' hat den SMTP-Verbindungsaufbau abgelehnt. SMTP-Dienst, Zugriffsbeschränkungen und Serverauslastung prüfen.'];
    return ['stage'=>$stage,'cause'=>'connection','message'=>'Die SMTP-Verbindung zu '.$endpoint.' konnte nicht aufgebaut werden. Host, Port und Verschlüsselung prüfen: STARTTLS üblicherweise auf Port 587, direktes TLS auf Port 465.'];
}
function save_smtp_settings(array $input): void {
    $old=smtp_settings();$s=['enabled'=>isset($input['enabled']),'host'=>trim((string)($input['host']??'')),'port'=>(int)($input['port']??587),'security'=>(string)($input['security']??'tls'),'username'=>trim((string)($input['username']??'')),'password'=>(string)($input['password']??''),'from_email'=>trim((string)($input['from_email']??'')),'from_name'=>trim((string)($input['from_name']??'Server Management')),'reply_to'=>trim((string)($input['reply_to']??''))];
    if($s['password']==='')$s['password']=$old['password'];
    if($s['host']!=='' && !preg_match('/^[a-zA-Z0-9_.:-]{1,253}$/D',$s['host']))throw new RuntimeException('Bitte einen SMTP-Host ohne Protokoll oder Pfad eintragen.');
    if($s['port']<1 || $s['port']>65535 || !in_array($s['security'],['tls','ssl'],true))throw new RuntimeException('Bitte SMTP-Port und TLS-Verschlüsselung prüfen.');
    foreach(['from_email','reply_to'] as $key)if($s[$key]!=='' && !filter_var($s[$key],FILTER_VALIDATE_EMAIL))throw new RuntimeException('Bitte gültige Absender- und Antwortadressen eintragen.');
    foreach(['username','from_name','from_email','reply_to'] as $key)if(strlen($s[$key])>255 || preg_match('/[\r\n\x00]/',$s[$key]))throw new RuntimeException('Ein E-Mail-Einstellungsfeld ist ungültig.');
    if(strlen($s['password'])>2000 || str_contains($s['password'],"\0"))throw new RuntimeException('Das SMTP-Passwort ist ungültig.');
    if($s['enabled'] && (!$s['host'] || !$s['from_email']))throw new RuntimeException('SMTP-Server und Absenderadresse werden benötigt.');
    shop_meta_set('smtp_config',secret_seal($s,'commerce:smtp'));shop_meta_set('smtp_test_identity','');shop_meta_set('smtp_last_test','');audit_event('smtp.settings');
}
function configured_mailer(): PHPMailer\PHPMailer\PHPMailer {
    $s=smtp_settings();$m=new PHPMailer\PHPMailer\PHPMailer(true);$m->isSMTP();$m->Host=$s['host'];$m->Port=(int)$s['port'];$m->SMTPSecure=$s['security'];$m->SMTPAuth=$s['username']!=='';$m->Username=$s['username'];$m->Password=$s['password'];$m->CharSet='UTF-8';$m->Timeout=12;$m->SMTPDebug=0;
    $smtp=new ShopSmtpConnection();$smtp->Timelimit=12;$m->setSMTPInstance($smtp);
    $m->SMTPOptions=['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false]];
    $ca=getenv('SMF_SMTP_CA_FILE');if($ca){if(is_link($ca)||!is_file($ca)||!is_readable($ca))throw new RuntimeException('Private SMTP-CA nicht lesbar.');$m->SMTPOptions['ssl']['cafile']=$ca;}
    $m->setFrom($s['from_email'],$s['from_name']);if($s['reply_to'])$m->addReplyTo($s['reply_to']);return $m;
}
function test_smtp_connection(): void {
    $s=smtp_settings();if(!$s['enabled'])throw new RuntimeException('Bitte den E-Mail-Versand zuerst aktivieren und speichern.');
    $m=null;
    try{if(PHP_SAPI==='cli' && isset($GLOBALS['shop_test_smtp_connect']))($GLOBALS['shop_test_smtp_connect'])($s);else{$m=configured_mailer();if(!$m->smtpConnect())throw new RuntimeException('SMTP');}}
    catch(Throwable $e){
        $error=smtp_connection_failure($m?->getSMTPInstance(),$s);
        shop_meta_set('smtp_test_identity','');shop_meta_set('smtp_last_test',json_encode($error+['ok'=>false,'tested_at'=>time(),'identity'=>smtp_identity($s)]));
        throw new RuntimeException($error['message']);
    }finally{if($m)$m->smtpClose();}
    shop_meta_set('smtp_last_test',json_encode(['ok'=>true,'tested_at'=>time(),'identity'=>smtp_identity($s),'message'=>'SMTP-Verbindung und Anmeldung erfolgreich. Es wurde keine E-Mail versendet.']));
    shop_meta_set('smtp_test_identity',smtp_identity($s));audit_event('smtp.test');
}
function queue_shop_mail(string $key,string $recipient,string $subject,string $body,?int $invoice=null,array $documents=[]): void {
    if(!filter_var($recipient,FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/',$subject))throw new RuntimeException('Die E-Mail-Adresse oder der Betreff ist ungültig.');
    $payload=json_encode(['body'=>$body,'invoice_id'=>$invoice,'document_versions'=>array_values(array_unique(array_map('intval',$documents)))],JSON_THROW_ON_ERROR);
    try{query('INSERT INTO shop_mail_queue (event_key,recipient,subject,payload) VALUES (?,?,?,?)',[$key,$recipient,$subject,$payload]);}catch(PDOException $e){if(!one('SELECT id FROM shop_mail_queue WHERE event_key=?',[$key]))throw $e;}
}
function run_mail_queue(int $limit=5): int {
    if(!smtp_ready())return 0;$sent=0;$now=time();
    // An interrupted SMTP DATA exchange is ambiguous: require explicit retry.
    query("UPDATE shop_mail_queue SET state='needs_action',last_error='Versand wurde unterbrochen. Zustellung vor Wiederholung prüfen.' WHERE state='sending' AND locked_until<?",[$now]);
    foreach(all("SELECT id FROM shop_mail_queue WHERE state='pending' AND available_at<=? AND locked_until<? ORDER BY id LIMIT ".max(1,min($limit,10)),[$now,$now]) as $candidate){
        $token=bin2hex(random_bytes(16));$claim=query("UPDATE shop_mail_queue SET state='sending',lock_token=?,locked_until=?,attempts=attempts+1 WHERE id=? AND state='pending' AND locked_until<?",[$token,time()+120,$candidate['id'],time()]);if($claim->rowCount()!==1)continue;
        $row=one('SELECT * FROM shop_mail_queue WHERE id=?',[$candidate['id']]);$payload=json_decode($row['payload'],true);
        try{
            if(!empty($payload['sealed_body']))$payload['body']='Deine Zugangsdaten findest du nach Anmeldung im Kundenbereich: '.Config::FRONTPANEL_SITE_URL.'/konto/produkte';
            if(preg_match('/^invoice-(?:reminder3|open):\d+$/D',$row['event_key']) && !empty($payload['invoice_id']) && !one("SELECT invoice_id FROM shop_receivables WHERE invoice_id=? AND state='open'",[$payload['invoice_id']])){query("UPDATE shop_mail_queue SET state='cancelled',last_error=NULL,locked_until=0 WHERE id=? AND lock_token=?",[$row['id'],$token]);continue;}
            $attachment=null;if(!empty($payload['invoice_id'])){$invoice=one('SELECT * FROM shop_invoices WHERE id=?',[$payload['invoice_id']]);if(!$invoice)throw new RuntimeException('Rechnung fehlt.');$attachment=invoice_file($invoice);}
            if(PHP_SAPI==='cli' && isset($GLOBALS['shop_test_mail_transport']))($GLOBALS['shop_test_mail_transport'])($row,$payload,$attachment);
            else{$m=configured_mailer();$m->addAddress($row['recipient']);$m->Subject=$row['subject'];$m->Body=$payload['body'];$m->MessageID='<'.hash('sha256',$row['event_key']).'@'.(parse_url(Config::FRONTPANEL_SITE_URL,PHP_URL_HOST)?:'orga-consult.eu').'>';if($attachment)$m->addAttachment($attachment,'Rechnung-'.$invoice['number'].'.pdf');foreach($payload['document_versions']??[] as $documentId){$v=one('SELECT v.*,d.slug FROM shop_document_versions v JOIN shop_documents d ON d.id=v.document_id WHERE v.id=?',[(int)$documentId]);if(!$v)throw new RuntimeException('Vertragsunterlage fehlt.');$m->addAttachment(shop_document_pdf($v),$v['slug'].'-v'.$v['version_no'].'.pdf');}$m->send();}
            query("UPDATE shop_mail_queue SET state='sent',sent_at=CURRENT_TIMESTAMP,last_error=NULL,locked_until=0 WHERE id=? AND lock_token=?",[$row['id'],$token]);$sent++;
        }catch(Throwable $e){query("UPDATE shop_mail_queue SET state='needs_action',last_error='Die Zustellung konnte nicht bestätigt werden. SMTP-Einstellungen und Empfänger prüfen; vor Wiederholung möglichen Eingang kontrollieren.',locked_until=0 WHERE id=? AND lock_token=?",[$row['id'],$token]);}
    }
    return $sent;
}
function order_mail_body(array $order): string {
    $profile=json_decode($order['profile'],true);$c=json_decode($order['configuration'],true);$tax=$c['tax']??vat_from_gross((int)$order['amount_cents']);
    return $order['product_name']."\nBestellung: ".$order['reference']."\nNettobetrag: ".money($tax['net_cents'])."\n19 % MwSt.: ".money($tax['tax_cents'])."\nGesamt: ".money($order['amount_cents']).' / '.cycle($order['billing'])."\n".($c['provisioning_notice']??'')."\n".($order['provider']==='ovh'?($c['dns_notice']??''):'')."\n\nVereinbarte Leistung und Laufzeit:\n".($profile['contract_text']??'')."\nVertragsbedingungen: ".($profile['terms_url']??'')."\nWiderrufsinformationen: ".($profile['withdrawal_url']??'')."\n\nKundenbereich: ".Config::FRONTPANEL_SITE_URL.'/konto/bestellungen?id='.$order['id'];
}
function process_commerce_events(int $limit=5): int {
    $done=0;
    foreach(all("SELECT event_key FROM shop_commerce_events WHERE state IN ('pending','retry') AND locked_until<? ORDER BY created_at LIMIT ".max(1,min(20,$limit)),[time()]) as $candidate){
        $token=bin2hex(random_bytes(16));$claim=query("UPDATE shop_commerce_events SET lock_token=?,locked_until=? WHERE event_key=? AND state IN ('pending','retry') AND locked_until<?",[$token,time()+180,$candidate['event_key'],time()]);if($claim->rowCount()!==1)continue;
        $event=one('SELECT * FROM shop_commerce_events WHERE event_key=?',[$candidate['event_key']]);$data=json_decode($event['payload'],true);
        try{
            $order=one('SELECT * FROM shop_orders WHERE id=?',[$data['order_id']??0]);if(!$order)throw new RuntimeException('Bestellung fehlt.');$customer=one('SELECT * FROM customers WHERE id=?',[$order['customer_id']]);$key=$event['event_key'];$sandbox=$order['paypal_mode']!=='live' || test_order($order);
            if(($customer['status']??'')==='deleted'){query("UPDATE shop_commerce_events SET state='done',last_error=NULL,locked_until=0 WHERE event_key=? AND lock_token=?",[$key,$token]);$done++;continue;}
            if($event['event_type']==='order.created'){
                customer_notification((int)$customer['id'],$key,'Bestellung eingegangen',$order['product_name'].' · '.money($order['amount_cents']),'/konto/bestellungen?id='.$order['id']);
                $initial=one("SELECT invoice_id FROM shop_receivables WHERE order_id=? AND kind='initial'",[$order['id']]);
                if(!$sandbox)queue_shop_mail($key,$customer['email'],'Deine Bestellung '.$order['reference'],"Hallo ".$customer['first_name'].",\n\nwir haben deine Bestellung erhalten. Die Rechnung findest du im Anhang, den aktuellen Zahlungsstatus im Kundenbereich. ".(pricing_snapshot($order) && $order['payment_status']==='no_charge'?"Die Rechnung ist durch deinen Gutschein vollständig ausgeglichen. Es wird kein Geld abgebucht.":(zero_base_order($order)?"Der Grundpreis beträgt 0 Euro. Bitte abonniere deine ausgewählten Module im Kundenbereich. Die Bereitstellung beginnt erst, wenn alle ausgewählten Module bezahlt sind.":"Die Bereitstellung beginnt nach bestätigter Zahlung."))."\n\n".order_mail_body($order),$initial?(int)$initial['invoice_id']:null,json_decode($order['configuration'],true)['document_versions']??[]);
            }elseif($event['event_type']==='order.free_renewal'){
                $invoice=one('SELECT * FROM shop_invoices WHERE id=? AND order_id=?',[$data['invoice_id'],$order['id']]);if(!$invoice)throw new RuntimeException('Kostenlose Folgerechnung fehlt.');customer_notification((int)$customer['id'],$key,'Kostenlose Verlängerung', $order['product_name'].' · 0,00 €','/konto/rechnungen');if(!$sandbox)queue_shop_mail($key,$customer['email'],'Rechnung '.$invoice['number'].' – kostenlose Verlängerung','Dein Paket wurde für den vereinbarten Zeitraum kostenlos verlängert. Die Rechnung liegt bei.',(int)$invoice['id']);
            }elseif($event['event_type']==='payment.confirmed'){
                $payment=one('SELECT * FROM shop_payments WHERE id=? AND order_id=?',[$data['payment_id'],$order['id']]);if(!$payment || $payment['status']!=='completed')throw new RuntimeException('Bestätigte Zahlung fehlt.');
                $kind=one('SELECT kind FROM shop_receivables WHERE payment_id=?',[$payment['id']]);$order=module_order_view($order,$kind['kind']??null);$invoiceOrder=$order;if(array_key_exists('next_billing_at',$data))$invoiceOrder['next_billing_at']=$data['next_billing_at'];$invoice=invoice_for_payment($payment,$invoiceOrder);invoice_file($invoice);
                customer_notification((int)$customer['id'],$key,'Zahlung bestätigt – Rechnung verfügbar','Rechnung '.$invoice['number'].' für '.$order['product_name'],'/konto/rechnungen?id='.$invoice['id']);
                if(!$sandbox)queue_shop_mail($key,$customer['email'],'Rechnung '.$invoice['number'].' – '.$order['product_name'],"Hallo ".$customer['first_name'].",\n\nvielen Dank für deine Zahlung. Im Anhang findest du deine Rechnung als PDF.\n\n".order_mail_body($order),(int)$invoice['id']);
                $q=(int)(json_decode($order['configuration'],true)['quote_id']??0);if($q){query("UPDATE shop_quotes SET status='accepted' WHERE id=? AND order_id=?",[$q,$order['id']]);query("UPDATE shop_requests SET status='completed' WHERE id=(SELECT request_id FROM shop_quotes WHERE id=?)",[$q]);}
            }elseif(in_array($event['event_type'],['invoice.open','invoice.reminder'],true)){
                $r=one("SELECT r.*,i.number FROM shop_receivables r JOIN shop_invoices i ON i.id=r.invoice_id WHERE r.invoice_id=? AND r.order_id=? AND r.state='open'",[$data['invoice_id'],$order['id']]);
                if($r){$reminder=$event['event_type']==='invoice.reminder';$title=$reminder?'Zahlungserinnerung':'Neue offene Rechnung';$note=$r['kind']==='initial'?'Unbezahlte Neubestellungen werden nach sieben Tagen und erneuter PayPal-Prüfung storniert.':'Diese Folgerechnung beendet deinen laufenden Vertrag nicht automatisch.';$url='/konto/rechnungen';customer_notification((int)$customer['id'],'invoice:'.$r['invoice_id'].($reminder?':reminder3':':open'),$title,$r['number'].' · '.money($r['amount_cents']).' · '.$note,$url);if(!$sandbox)queue_shop_mail($key,$customer['email'],$title.' '.$r['number'],"Hallo ".$customer['first_name'].",\n\nfür ".$order['product_name']." ist die Rechnung ".$r['number']." über ".money($r['amount_cents'])." noch offen.\n".$note."\n\nZahlung und PayPal-Status prüfen:\n".Config::FRONTPANEL_SITE_URL.$url,(int)$r['invoice_id']);}
            }elseif($event['event_type']==='order.unpaid_cancelled'){
                customer_notification((int)$customer['id'],$key,'Unbezahlte Neubestellung storniert',$order['product_name'].' wurde nach sieben Tagen ohne bestätigte Zahlung storniert.','/konto/bestellungen?id='.$order['id']);if(!$sandbox)queue_shop_mail($key,$customer['email'],'Bestellung storniert – '.$order['reference'],"Hallo ".$customer['first_name'].",\n\ndeine Neubestellung wurde nach sieben Tagen ohne bestätigte Zahlung und erneuter Prüfung bei PayPal storniert. Die Stornorechnung findest du im Anhang.\n\n".Config::FRONTPANEL_SITE_URL.'/konto/rechnungen',(int)$data['invoice_id']);
            }elseif($event['event_type']==='contract.ended'){
                customer_notification((int)$customer['id'],$key,'Vertrag beendet',$order['product_name'].' wurde zum bestätigten Kündigungstermin beendet.','/konto/vertraege?id='.$order['id']);if(!$sandbox)queue_shop_mail($key,$customer['email'],'Vertrag beendet – '.$order['product_name'],"Hallo ".$customer['first_name'].",\n\ndein Vertrag wurde zum bestätigten Kündigungstermin beendet. Deine Unterlagen bleiben im Kundenkonto abrufbar:\n".Config::FRONTPANEL_SITE_URL.'/konto/vertraege?id='.$order['id']);
            }elseif($event['event_type']==='payment.review'){
                customer_notification((int)$customer['id'],$key,'Zahlung wird persönlich geprüft','Deine Zahlung ist eingegangen und muss einer stornierten Bestellung oder bereits bezahlten Periode zugeordnet werden. Es wird kein neues Paket automatisch eingerichtet.','/konto/bestellungen?id='.$order['id']);if(!$sandbox)queue_shop_mail($key,$customer['email'],'Zahlung eingegangen – Prüfung erforderlich',"Hallo ".$customer['first_name'].",\n\ndeine Zahlung für ".$order['reference']." ist eingegangen. Die Bestellung ist bereits beendet oder die Rate bereits bezahlt. Wir prüfen die Zahlung und eine gegebenenfalls erforderliche Erstattung persönlich.\n\n".Config::FRONTPANEL_SITE_URL.'/konto/bestellungen?id='.$order['id']);
            }elseif($event['event_type']==='service.ready'){
                $service=service_for_order((int)$order['id'],(int)$customer['id']);if(!$service)throw new RuntimeException('Bereitgestelltes Paket fehlt.');
                if($order['provider']==='proxmox' && $service && !$sandbox)queue_server_credentials(bound_service((int)$service['id'],(int)$order['customer_id']),'server-credentials:initial:'.$order['id']);
                customer_notification((int)$customer['id'],$key,'Dein Paket ist bereit',$order['product_name'],'/konto/verwalten?id='.$service['id']);if(!$sandbox)queue_shop_mail($key,$customer['email'],'Dein Paket ist bereit – '.$order['product_name'],"Hallo ".$customer['first_name'].",\n\ndein Paket wurde eingerichtet. Deine Zugangsdaten und Verwaltung findest du geschützt im Kundenbereich:\n".Config::FRONTPANEL_SITE_URL.'/konto/verwalten?id='.$service['id']);
            }
            query("UPDATE shop_commerce_events SET state='done',last_error=NULL,locked_until=0 WHERE event_key=? AND lock_token=?",[$key,$token]);$done++;
        }catch(Throwable $e){$message=$e instanceof RuntimeException && !($e instanceof PDOException)?$e->getMessage():'Verarbeitung konnte nicht abgeschlossen werden.';query("UPDATE shop_commerce_events SET state='retry',last_error=?,locked_until=? WHERE event_key=? AND lock_token=?",[substr($message,0,500),time()+60,$event['event_key'],$token]);}
    }
    return $done;
}
function run_shop_worker(int $limit=3): void {
    throw new RuntimeException('Use bin/worker.php with registered module jobs.');
}
