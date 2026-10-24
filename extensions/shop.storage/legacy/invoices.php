<?php
require_once __DIR__.'/lib/tfpdf/font/unifont/ttfonts.php';
require_once __DIR__.'/lib/tfpdf/tfpdf.php';
class ShopInvoicePdf extends tFPDF {
    function __construct(){
        parent::__construct();$dir=shop_private().'/pdf-fonts/unifont';if(!is_dir($dir))mkdir($dir,0700,true);
        foreach(['DejaVuSansCondensed.ttf','DejaVuSansCondensed-Bold.ttf'] as $font)if(!is_file($dir.'/'.$font))copy(__DIR__.'/lib/tfpdf/font/unifont/'.$font,$dir.'/'.$font);
        $this->fontpath=dirname($dir).'/';$this->SetMargins(18,18,18);$this->SetAutoPageBreak(true,25);$this->AddFont('DejaVu','','DejaVuSansCondensed.ttf',true);$this->AddFont('DejaVu','B','DejaVuSansCondensed-Bold.ttf',true);
    }
    function Footer(){ $this->SetY(-17);$this->SetFont('DejaVu','',8);$this->SetTextColor(100,110,125);$this->Cell(0,5,'Seite '.$this->PageNo().' | Server Management',0,0,'R'); }
}
function invoice_period(array $payment,array $order): array {
    $start=new DateTimeImmutable($payment['received_at'].' UTC');$next=$order['next_billing_at']?new DateTimeImmutable($order['next_billing_at']):null;
    if($order['billing']==='once')return ['start'=>$start->format('Y-m-d'),'end'=>null];
    if(!$next || $next<=$start){$months=billing_months($order['billing']);$base=$start->modify('first day of this month')->modify('+'.$months.' months');$day=min((int)$start->format('d'),(int)$base->format('t'));$next=$base->setDate((int)$base->format('Y'),(int)$base->format('m'),$day);}
    return ['start'=>$start->format('Y-m-d'),'end'=>$next->modify('-1 day')->format('Y-m-d')];
}
function invoice_for_payment(array $payment,array $order): array {
    $existing=one('SELECT * FROM shop_invoices WHERE payment_id=?',[$payment['id']]);if($existing)return $existing;
    if(!invoice_seller_ready())throw new RuntimeException('Bitte zuerst die vollständigen Rechnungsstellerdaten unter Shop-Einstellungen hinterlegen.');
    $test=$payment['status']==='test_confirmed' && (bool)one("SELECT id FROM shop_payment_attestations WHERE payment_id=? AND source='test'",[$payment['id']]);if(($payment['status']!=='completed' && !$test) || (int)$payment['order_id']!==(int)$order['id'])throw new RuntimeException('Für die Rechnung fehlt eine zugeordnete bestätigte Zahlung.');
    $configuration=json_decode($order['configuration'],true);$buyer=$configuration['billing']??one('SELECT first_name,last_name,email,company,phone,address,postal_code,city,country FROM customers WHERE id=?',[$order['customer_id']]);
    $buyer=array_replace($buyer,billing_input($buyer,true));$tax=$configuration['tax']??vat_from_gross((int)$payment['amount_cents']);
    if((int)$tax['gross_cents']!==(int)$payment['amount_cents'] || (int)$tax['net_cents']+(int)$tax['tax_cents']!==(int)$payment['amount_cents'])throw new RuntimeException('Der Rechnungsbetrag stimmt nicht mit der Zahlung überein.');
    $seller=commerce_settings();$sandbox=$payment['paypal_mode']!=='live' || $test;$series=($sandbox?'TEST-':'').$seller['invoice_prefix'].'-'.date('Y');
    $snapshot=['seller'=>$seller,'buyer'=>$buyer,'tax'=>$tax,'product'=>$order['product_name'],'domain'=>$order['domain'],'reference'=>$order['reference'],'billing'=>$order['billing'],'payment_reference'=>$payment['transaction_id'],'paid_at'=>$payment['received_at'],'period'=>invoice_period($payment,$order),'sandbox'=>$sandbox,'provisioning_notice'=>$configuration['provisioning_notice']??'','dns_notice'=>$configuration['dns_notice']??'','modules'=>$configuration['modules']??[],'base_net_cents'=>$configuration['base_net_cents']??null,'purchase_pricing'=>$configuration['purchase_pricing']??null,'pricing_period'=>$configuration['pricing_period']??'initial'];
    $ownsTransaction=!db()->inTransaction();if($ownsTransaction)db()->beginTransaction();
    try{
        $existing=one('SELECT * FROM shop_invoices WHERE payment_id=?',[$payment['id']]);if($existing){if($ownsTransaction)db()->commit();return $existing;}
        try{query('INSERT INTO shop_invoice_counters (series,next_number) VALUES (?,1)',[$series]);}catch(PDOException $e){if(!one('SELECT series FROM shop_invoice_counters WHERE series=?',[$series]))throw $e;}
        $lock=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';$counter=one('SELECT next_number FROM shop_invoice_counters WHERE series=?'.$lock,[$series]);$n=(int)$counter['next_number'];query('UPDATE shop_invoice_counters SET next_number=next_number+1 WHERE series=?',[$series]);
        query('INSERT INTO shop_invoices (payment_id,order_id,customer_id,number,snapshot) VALUES (?,?,?,?,?)',[$payment['id'],$order['id'],$order['customer_id'],$series.'-'.str_pad((string)$n,6,'0',STR_PAD_LEFT),json_encode($snapshot,JSON_THROW_ON_ERROR)]);$id=(int)db()->lastInsertId();if($ownsTransaction)db()->commit();return one('SELECT * FROM shop_invoices WHERE id=?',[$id]);
    }catch(Throwable $e){if($ownsTransaction && db()->inTransaction())db()->rollBack();$existing=one('SELECT * FROM shop_invoices WHERE payment_id=?',[$payment['id']]);if($existing)return $existing;throw $e;}
}
function render_invoice_pdf(array $invoice): string {
    $s=json_decode($invoice['snapshot'],true);$credit=!empty($s['credit_note']);$openDocument=!empty($s['issued_before_payment']);$seller=$s['seller'];$buyer=$s['buyer'];$tax=$s['tax'];$pdf=new ShopInvoicePdf();$pdf->SetTitle(($s['sandbox']?'TEST – ':'').($credit?'Stornorechnung ':'Rechnung ').$invoice['number'],true);$pdf->SetAuthor($seller['seller_name'],true);$pdf->AddPage();
    $pdf->SetFillColor(59,130,246);$pdf->Rect(0,0,210,5,'F');$pdf->SetXY(112,18);$pdf->SetFont('DejaVu','B',12);$pdf->SetTextColor(20,30,50);$pdf->MultiCell(80,6,$seller['seller_name'],0,'R');$pdf->SetFont('DejaVu','',9);$pdf->SetX(112);$pdf->MultiCell(80,5,$seller['seller_address']."\n".$seller['seller_postal_code'].' '.$seller['seller_city']."\n".(billing_countries()[$seller['seller_country']]['name']??$seller['seller_country'])."\n".$seller['seller_email'],0,'R');
    $pdf->SetY(max(65,$pdf->GetY()+8));$pdf->SetFont('DejaVu','B',10);$pdf->Cell(0,6,'Rechnungsempfänger',0,1);$pdf->SetFont('DejaVu','',10);$address=($buyer['company']?($buyer['company']."\n"):'').$buyer['first_name'].' '.$buyer['last_name']."\n".$buyer['address']."\n".$buyer['postal_code'].' '.$buyer['city']."\n".(billing_countries()[$buyer['country']]['name']??$buyer['country']);$pdf->MultiCell(0,5.5,$address);$pdf->Ln(9);
    $pdf->SetFont('DejaVu','B',20);$pdf->SetTextColor(35,85,175);$pdf->Cell(0,11,$credit?'Stornorechnung':($s['sandbox']?'TESTRECHNUNG':'Rechnung'),0,1);$pdf->SetTextColor(20,30,50);$pdf->SetFont('DejaVu','',10);$date=date('d.m.Y',strtotime($invoice['issued_at'].' UTC'));$pdf->MultiCell(0,6,'Rechnungsnummer: '.$invoice['number']."\nRechnungsdatum: ".$date."\nBestellung: ".$s['reference'].($openDocument?'':"\nZahlungseingang: ".date('d.m.Y',strtotime($s['paid_at'].' UTC'))));
    $period=$s['period']['start']?'Leistungszeitraum: '.date('d.m.Y',strtotime($s['period']['start'])):((int)$tax['gross_cents']===0 && !empty($s['purchase_pricing'])?'Leistungsbeginn gemäß Gutscheinfreigabe.':'Leistungsbeginn nach bestätigtem Zahlungseingang.');if($s['period']['end'])$period.=' bis '.date('d.m.Y',strtotime($s['period']['end']));elseif($s['period']['start'])$period.=' (einmalige Leistung / Vorauszahlung)';$pdf->MultiCell(0,6,$period);$pdf->Ln(7);
    $pdf->SetFillColor(235,242,255);$pdf->SetFont('DejaVu','B',10);$pdf->Cell(20,9,'Menge',0,0,'L',true);$pdf->Cell(109,9,'Leistung',0,0,'L',true);$pdf->Cell(45,9,'Netto',0,1,'R',true);$pdf->SetFont('DejaVu','',10);$y=$pdf->GetY()+3;$pdf->SetXY(18,$y);$pdf->Cell(20,7,'1');$pdf->SetXY(38,$y);$pdf->MultiCell(109,6,$s['product'].($s['domain']?"\n".$s['domain']:'').' / '.cycle($s['billing']));$end=max($pdf->GetY(),$y+12);$pdf->SetXY(147,$y);$pdf->Cell(45,7,money($tax['net_cents']),0,1,'R');$pdf->SetY($end+7);
    if(!empty($s['modules'])){$pdf->SetFont('DejaVu','B',10);$pdf->MultiCell(0,6,'Enthaltene Zusatzmodule (Nettobeträge):');$pdf->SetFont('DejaVu','',9);$sign=$credit?-1:1;foreach($s['modules'] as $module)$pdf->MultiCell(0,5.5,$module['quantity'].' × '.$module['label'].' · '.money($sign*(int)$module['net_cents']));$pdf->Ln(5);}
    if(!empty($s['purchase_pricing'])){$ps=$s['purchase_pricing'];$off=($s['pricing_period']??'initial')==='renewal'?(int)$ps['term_net']-(int)$ps['regular_net']:(int)$ps['discount_net'];$sign=$credit?-1:1;$pdf->SetFont('DejaVu','',9);$pdf->MultiCell(0,5.5,'Listenpreis netto: '.money($sign*(int)$ps['original_net']).' | Vorauszahlungsrabatt: '.money($sign*((int)$ps['original_net']-(int)$ps['term_net'])).' | Gutscheinrabatt: '.money($sign*$off).(!empty($ps['coupon'])?' ('.$ps['coupon']['code'].')':'').(!empty($ps['reward_id'])?' (Zusatzpaket-Bonus)':''));$pdf->Ln(4);}
    foreach([['Nettobetrag',$tax['net_cents']],['19 % MwSt.',$tax['tax_cents']],['Gesamtbetrag inkl. MwSt.',$tax['gross_cents']]] as $i=>[$label,$value]){$pdf->SetFont('DejaVu',$i===2?'B':'',11);$pdf->SetX(80);$pdf->Cell(67,8,$label);$pdf->Cell(45,8,money($value),0,1,'R');}
    $pdf->Ln(7);$pdf->SetFont('DejaVu','',9);$paymentText=$credit?'Stornierung der Rechnung '.$s['original_number'].'. '.$s['cancellation_reason']:($s['sandbox']?'Nur Test. Keine echte Zahlung und keine steuerliche Rechnung.':((int)$tax['gross_cents']===0 && !empty($s['purchase_pricing'])?'Vollständig durch Gutschein ausgeglichen. Es ist keine Zahlung fällig.':($openDocument?'Zahlung über PayPal. Den aktuellen Zahlungsstatus und Zahlungslink findest du im geschützten Kundenbereich.':'Der Zahlungseingang ist im Kundenkonto bestätigt.')));$pdf->MultiCell(0,5,$paymentText);if($s['payment_reference'])$pdf->MultiCell(0,5,'Zahlungsreferenz: '.$s['payment_reference']);
    if($s['provisioning_notice'])$pdf->MultiCell(0,5,$s['provisioning_notice']);
    $pdf->Ln(5);$ids=[];if($seller['seller_tax_id'])$ids[]='Steuernummer: '.$seller['seller_tax_id'];if($seller['seller_vat_id'])$ids[]='USt-IdNr.: '.$seller['seller_vat_id'];$pdf->MultiCell(0,5,implode(' | ',$ids));
    return $pdf->Output('S');
}
function invoice_file(array $invoice): string {
    $dir=shop_private().'/invoices';if(!is_dir($dir))mkdir($dir,0700,true);$lock=fopen($dir.'/.render.lock','c');if(!$lock || !flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Die Rechnung wird gerade erstellt. Bitte erneut versuchen.');
    try{
        $invoice=one('SELECT * FROM shop_invoices WHERE id=?',[$invoice['id']]);$name=$invoice['pdf_file']?:'invoice-'.$invoice['id'].'-'.bin2hex(random_bytes(8)).'.pdf';
        if(!preg_match('/^invoice-\d+-[a-f0-9]{16}\.pdf$/D',$name))throw new RuntimeException('Ungültiger Rechnungsdateipfad.');$path=$dir.'/'.$name;
        if($invoice['pdf_hash']){if(!is_file($path) || !hash_equals($invoice['pdf_hash'],hash_file('sha256',$path)))throw new RuntimeException('Die Rechnungsdatei muss durch den Support geprüft werden.');return $path;}
        $bytes=render_invoice_pdf($invoice);if(file_put_contents($path.'.tmp',$bytes,LOCK_EX)!==strlen($bytes))throw new RuntimeException('Die Rechnung konnte nicht gespeichert werden.');chmod($path.'.tmp',0600);rename($path.'.tmp',$path);query('UPDATE shop_invoices SET pdf_file=?,pdf_hash=? WHERE id=? AND pdf_hash IS NULL',[$name,hash('sha256',$bytes),$invoice['id']]);return $path;
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
function download_invoice(int $id,?int $customer): never {
    $sql='SELECT * FROM shop_invoices WHERE id=?';$args=[$id];if($customer!==null){$sql.=' AND customer_id=?';$args[]=$customer;}$invoice=one($sql,$args);if(!$invoice){http_response_code(404);exit('Rechnung nicht gefunden.');}$path=invoice_file($invoice);while(ob_get_level())ob_end_clean();header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="Rechnung-'.$invoice['number'].'.pdf"');header('Content-Length: '.filesize($path));readfile($path);exit;
}
