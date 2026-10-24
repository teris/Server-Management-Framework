<?php
function package_checklist(array $p): void {
    $items=package_requirements($p);$ready=!array_filter($items,fn($item)=>!$item[1]);
    echo '<section class="panel form-panel package-checklist"><div class="panel-heading"><span class="round-icon">'.icon($ready?'check':'settings').'</span><div><h2>'.($ready?'Dieses Paket ist direkt kaufbar.':'Noch offen bis zum Direktkauf').'</h2><p>Jeder offene Punkt führt zur passenden Einstellung.</p></div></div><ul>';
    foreach($items as [$label,$ok,$url])echo '<li class="'.($ok?'is-ready':'is-pending').'"><a href="'.e($url).'">'.icon($ok?'check':'clock').'<span>'.e($label).'</span>'.(!$ok?icon('arrow'):'').'</a></li>';
    echo '</ul></section>';
}
function module_cards(bool $compact=false): void {
    echo '<div class="module-grid'.($compact?' module-grid-compact':'').'">';
    foreach(shop_modules() as $module) {
        $external=$module['external'];
        echo '<a class="module-card'.($external?'':' module-card-server').'" href="'.e($module['url']).'"'.($external?' target="_blank" rel="noopener noreferrer"':'').'>';
        echo '<span class="module-card-top"><span class="round-icon">'.icon($module['icon']).'</span><span class="module-location">'.($external?'Direktzugang':'Im Kundenbereich').'</span>'.icon($external?'external':'arrow').'</span>';
        echo '<span class="module-label">'.e($module['label']).'</span><h'.($compact?'3':'2').'>'.e($module['name']).'</h'.($compact?'3':'2').'>';
        if(!$compact)echo '<p>'.e($module['description']).'</p>';
        echo '<span class="module-card-action">'.($external?'Modul öffnen':'Server verwalten').' '.icon('arrow').'</span>';
        if($external)echo '<span class="sr-only">Öffnet in einem neuen Tab</span>';
        echo '</a>';
    }
    echo '</div>';
}
function product_card(array $p): void { $domainCard=$p['category']==='domain' && domain_catalog_managed();if($domainCard){$name=$p['name'];$candidates=array_filter(domain_tlds(),fn($row)=>(int)$row['product_id']===(int)$p['id'] && $row['net_cents']!==null && $row['sale_mode']==='direct');usort($candidates,fn($a,$b)=>(int)$a['net_cents']<=>(int)$b['net_cents']);if($candidates){$p=domain_priced_product($candidates[0]);$p['name']=$name;}else{$p['price_cents']=null;$p['_options']=array_replace(product_options($p),['sale_mode'=>'request','net_cents'=>null]);}}$bookable=checkout_ready($p);$configurable=product_has_customer_modules($p); ?>
<article class="product-card <?= $p['featured']?'featured':'' ?>" data-product data-category="<?= e($p['category']) ?>" data-name="<?= e(strtolower($p['name'].' '.$p['description'])) ?>">
    <?php if($p['featured']): ?><span class="product-tag">Mehr Raum für deine Ideen</span><?php endif ?>
    <div class="product-icon"><?= icon($p['icon']) ?></div>
    <span class="product-eyebrow"><?= e($p['eyebrow']) ?></span><h3><?= e($p['name']) ?></h3><p><?= e($p['description']) ?></p>
    <div class="product-price"><?php if(package_is_fixed($p)): ?><strong><?= $domainCard?'ab ':'' ?><?= price($p) ?></strong><span>/ <?= $domainCard?'Jahr':cycle($p['billing']) ?> · inkl. 19 % MwSt.</span><?php else: ?><strong>Auf Anfrage</strong><span>individuell für dich</span><?php endif ?></div>
    <a href="<?= $domainCard?'/domain-pruefen?produkt=':($configurable?'/konfigurator?produkt=':(package_is_fixed($p)?'/bestellen?produkt=':'/konfigurieren?produkt=')) ?><?= e($p['slug']) ?><?= !empty($_GET['domain'])?'&amp;domain='.e(rawurlencode((string)$_GET['domain'])):'' ?>" class="button <?= $p['featured']?'button-teal':'button-outline' ?>"><?= $domainCard?'Domain und Endung auswählen':($configurable?'Paket zusammenstellen':($bookable?(product_configurator_enabled((int)$p['id'])?'Konfigurieren & kaufen':'Jetzt kaufen'):(package_is_fixed($p)?'Paket ansehen':'Angebot anfragen'))) ?> <?= icon('arrow') ?></a>
    <ul class="feature-list"><?php foreach(json_decode($p['features'],true)??[] as $feature): ?><li><?= icon('check') ?><span><?= e($feature) ?></span></li><?php endforeach ?></ul>
</article>
<?php }
function empty_state(string $ico,string $heading,string $text,string $url='',string $cta=''): void { ?>
<div class="empty-state"><div class="empty-icon"><?= icon($ico) ?></div><h3><?= e($heading) ?></h3><p><?= e($text) ?></p><?php if($url): ?><a class="button button-teal button-small" href="<?= e($url) ?>"><?= e($cta) ?> <?= icon('arrow') ?></a><?php endif ?></div>
<?php }
function password_input(string $name='password',string $label='Passwort',string $autocomplete='new-password'): void { ?>
<label class="field"><span><?= e($label) ?></span><span class="password-wrap"><input type="password" name="<?= e($name) ?>" autocomplete="<?= e($autocomplete) ?>" minlength="<?= $autocomplete==='new-password'?'12':'1' ?>" maxlength="200" required><button type="button" data-password-toggle class="icon-button" aria-label="Passwort anzeigen" aria-pressed="false"><?= icon('eye') ?></button></span></label>
<?php }
function request_table(array $requests,bool $adminView=false): void { ?>
<div class="table-scroll"><table><thead><tr><th>Anfrage</th><th>Produkt<?= $adminView?' / Kunde':'' ?></th><th>Datum</th><th>Status</th><th><span class="sr-only">Details</span></th></tr></thead><tbody><?php foreach($requests as $r): ?><tr><td><strong><?= e($r['reference']) ?></strong><?php if($r['domain']): ?><small><?= e($r['domain']) ?></small><?php endif ?></td><td><?= e($r['product_name']) ?><?php if($adminView): ?><small><?= e($r['email']??'') ?></small><?php endif ?></td><td><?= date_de($r['created_at']) ?></td><td><?= badge($r['status']) ?></td><td><a class="icon-button" aria-label="Anfrage <?= e($r['reference']) ?> öffnen" href="<?= $adminView?'/admin/anfragen':'/konto/anfragen' ?>?id=<?= (int)$r['id'] ?>"><?= icon('arrow') ?></a></td></tr><?php endforeach ?></tbody></table></div>
<?php }
function ticket_table(array $tickets,bool $adminView=false): void { ?>
<div class="table-scroll"><table><thead><tr><th>Ticket</th><th>Betreff<?= $adminView?' / Kunde':'' ?></th><th>Aktualisiert</th><th>Status</th><th><span class="sr-only">Details</span></th></tr></thead><tbody><?php foreach($tickets as $t): ?><tr><td><strong><?= e($t['ticket_number']) ?></strong></td><td><?= e($t['subject']) ?><?php if($adminView): ?><small><?= e($t['email']) ?></small><?php endif ?></td><td><?= date_de($t['updated_at']) ?></td><td><?= badge($t['status']) ?></td><td><a class="icon-button" aria-label="Ticket <?= e($t['ticket_number']) ?> öffnen" href="<?= $adminView?'/admin/ticket':'/konto/ticket' ?>?id=<?= (int)$t['id'] ?>"><?= icon('arrow') ?></a></td></tr><?php endforeach ?></tbody></table></div>
<?php }
function resource_login_panel(array $service): void {if(!$service['credentials'])return; ?>
<section class="panel form-panel resource-login"><h2><?= $service['provider']==='ispconfig'?'FTP-Zugangsdaten':'Server-Zugangsdaten' ?></h2>
<?php if(isset($GLOBALS['revealed_credentials']) && ($GLOBALS['revealed_service_id']??0)===(int)$service['id']): ?><dl class="detail-list"><?php foreach($GLOBALS['revealed_credentials'] as $key=>$value): ?><div><dt><?= e(['username'=>'Benutzername','password'=>'Passwort','host'=>'Hostname'][$key]??$key) ?></dt><dd class="credential-value"><?= e($value) ?></dd></div><?php endforeach ?></dl><?php else: ?><p>Die Zugangsdaten für dein gekauftes Paket sind geschützt gespeichert.</p><form method="post"><?= csrf() ?><input type="hidden" name="action" value="resource_credentials"><input type="hidden" name="service_id" value="<?= $service['id'] ?>"><button class="button button-outline button-small"><?= icon('eye') ?> Zugangsdaten anzeigen</button></form><?php endif ?>
<?php if($service['provider']==='proxmox' && proxmox_service_type($service)==='qemu'): ?><details class="credential-change"><summary>Passwort im virtuellen Server ändern</summary><p>Der Server muss laufen und der QEMU-Gastagent muss aktiviert sein. Die Änderung betrifft deinen Gastbenutzer und wird nach erfolgreicher Bestätigung gespeichert.</p><form method="post"><?= csrf() ?><input type="hidden" name="action" value="vps_password"><input type="hidden" name="service_id" value="<?= $service['id'] ?>"><?php password_input('new_password','Neues Server-Passwort'); ?><button class="button button-teal button-small">Server-Passwort ändern</button></form></details><?php endif ?>
</section>
<?php } ?>

<?php function billing_country_select(string $name,string $value,bool $required=false): void {
    try{$selected=billing_country($value);}catch(RuntimeException $error){$selected='';}
    $countries=billing_countries();$frequent=array_intersect_key($countries,array_flip(['DE','AT','CH']));
?>
<label class="field"><span>Land<?= $required?' *':'' ?></span><select name="<?= e($name) ?>" autocomplete="country" <?= $required?'required':'' ?>>
<option value="">Bitte Land auswählen</option>
<optgroup label="Häufig gewählt"><?php foreach(['DE','AT','CH'] as $code): ?><option value="<?= $code ?>" <?= $selected===$code?'selected':'' ?>><?= e($countries[$code]['name']) ?> (<?= $code ?>)</option><?php endforeach ?></optgroup>
<optgroup label="Weitere Länder"><?php foreach(array_diff_key($countries,$frequent) as $code=>$country): ?><option value="<?= $code ?>" <?= $selected===$code?'selected':'' ?>><?= e($country['name']) ?> (<?= $code ?>)</option><?php endforeach ?></optgroup>
</select><?php if($value!=='' && $selected===''): ?><small>Bitte wähle dein Land aus der Liste aus.</small><?php endif ?></label>
<?php } ?>

<?php function tax_summary(array $tax,string $billing='once'): void { ?>
<dl class="detail-list tax-summary"><div><dt>Nettopreis</dt><dd><?= money($tax['net_cents']) ?></dd></div><div><dt>19 % MwSt.</dt><dd><?= money($tax['tax_cents']) ?></dd></div><div class="tax-total"><dt>Gesamt inkl. MwSt.</dt><dd><strong><?= money($tax['gross_cents']) ?></strong></dd></div></dl><p class="small muted"><?= $billing==='once'?'Einmalige Zahlung':'Je '.cycle($billing) ?></p>
<?php }
function delivery_notices(string $delivery,string $dns=''): void {if(!$delivery && !$dns)return; ?>
<div class="delivery-notices"><?php foreach(array_filter([$delivery,$dns]) as $notice): ?><p><?= icon('clock') ?><span><?= nl2br(e($notice)) ?></span></p><?php endforeach ?></div>
<?php }
function order_commerce_details(array $order,bool $adminView=false): void {$c=json_decode($order['configuration'],true);if(!empty($c['document_versions'])){echo '<div class="contract-box"><h3>Vertragsunterlagen zu dieser Bestellung</h3>';foreach($c['document_versions'] as $versionId){$v=one('SELECT * FROM shop_document_versions WHERE id=?',[(int)$versionId]);if($v)echo '<p><a href="'.e(document_version_url($v)).'" target="_blank" rel="noopener">'.e($v['title']).' · Fassung '.(int)$v['version_no'].' (PDF)</a></p>';}echo '</div>';}if(!empty($c['modules']))echo '<div class="contract-box"><h3>Gewählte Zusatzmodule</h3><p class="pre-wrap">'.e(module_description($c['modules'])).'</p></div>';if(isset($c['contract_policy']))echo '<p class="small muted">'.e(contract_policy_text($c['contract_policy'])).'</p>';tax_summary($c['tax']??vat_from_gross((int)$order['amount_cents']),$order['billing']);delivery_notices($c['provisioning_notice']??'',$order['provider']==='ovh'?($c['dns_notice']??''):'');$invoices=all('SELECT * FROM shop_invoices WHERE order_id=? ORDER BY id DESC',[$order['id']]);if($invoices): ?>
<div class="invoice-links"><h3>Rechnungen</h3><?php foreach($invoices as $invoice): ?><a class="button button-outline button-small" href="<?= $adminView?'/admin':'/konto' ?>/rechnungen?download=<?= (int)$invoice['id'] ?>"><?= icon('package') ?> <?= e($invoice['number']) ?> · PDF</a><?php endforeach ?></div>
<?php endif;$profile=json_decode($order['profile'],true);if(!empty($profile['terms_url']) && !empty($profile['withdrawal_url'])): ?><p class="small"><a class="text-link" href="<?= e($profile['terms_url']) ?>" target="_blank" rel="noopener">Vereinbarte Vertragsbedingungen <?= icon('external') ?></a> · <a class="text-link" href="<?= e($profile['withdrawal_url']) ?>" target="_blank" rel="noopener">Widerrufsinformationen <?= icon('external') ?></a></p><?php endif; }
function quote_status(array $q): string {return $q['status']==='sent' && strtotime($q['expires_at'].' UTC')<=time()?'Abgelaufen':(['draft'=>'Entwurf','sent'=>'Angebot verfügbar','accepted'=>'Bestellt und bezahlt','cancelled'=>'Zurückgenommen'][$q['status']]??$q['status']);}
function quote_details(array $q,bool $adminView=false): void {$profile=json_decode($q['profile'],true); ?>
<div class="eyebrow">ANGEBOT #<?= (int)$q['id'] ?></div><h2><?= e($q['title']) ?></h2><p><span class="badge status-pending"><?= e(quote_status($q)) ?></span></p><dl class="detail-list"><div><dt>Gültig bis</dt><dd><?= date_de($q['expires_at']) ?></dd></div><div><dt>Domain</dt><dd><?= e($q['domain']?:'–') ?></dd></div><div><dt>Abrechnung</dt><dd><?= e(cycle($q['billing'])) ?></dd></div></dl>
<?php tax_summary(vat_from_net((int)$q['net_cents'],(int)$q['tax_bps']),$q['billing']); ?><div class="contract-box"><h3>Leistung und Laufzeit</h3><?php if(isset($profile['contract_policy'])): ?><p><?= e(contract_policy_text($profile['contract_policy'])) ?></p><?php endif ?><p class="pre-wrap"><?= e($profile['contract_text']) ?></p><?php $specs=package_specs($q['provider'],$profile);if($specs): ?><dl class="detail-list"><?php foreach($specs as $caption=>$value): ?><div><dt><?= e($caption) ?></dt><dd><?= e($value) ?></dd></div><?php endforeach ?></dl><?php endif ?></div><?php delivery_notices($q['provisioning_notice'],$q['provider']==='ovh'?$q['dns_notice']:''); ?>
<?php if($q['order_id']): ?><a class="button button-teal" href="<?= $adminView?'/admin':'/konto' ?>/bestellungen?id=<?= (int)$q['order_id'] ?>">Bestellung öffnen <?= icon('arrow') ?></a><?php elseif(!$adminView && $q['status']==='sent' && strtotime($q['expires_at'].' UTC')>time()): ?><a class="button button-teal" href="/bestellen?angebot=<?= (int)$q['id'] ?>">Angebot prüfen & mit PayPal bezahlen <?= icon('arrow') ?></a><?php endif ?>
<?php }
function contract_policy_fields(array $policy): void { ?>
<div class="package-config"><h3>Laufzeit & Kündigung</h3><div class="form-grid"><label class="field"><span>Mindestlaufzeit in Monaten</span><input type="number" name="minimum_months" min="1" max="24" required value="<?= (int)($_POST['minimum_months']??$policy['minimum_months']) ?>"><small>Standard: ein Monat. Danach ist der Vertrag monatlich kündbar.</small></label><label class="field"><span>Kündigungsfrist in Tagen</span><input type="number" name="notice_days" min="0" max="31" required value="<?= (int)($_POST['notice_days']??$policy['notice_days']) ?>"><small>0 = keine zusätzliche Vorlaufzeit.</small></label></div><p class="small muted">Vorausbezahlte Leistungen bleiben bis zum Ende des bezahlten Zeitraums nutzbar. Die Einstellungen gelten für neue Bestellungen und werden beim Kauf festgehalten. Bei Einmalleistungen gibt es keine automatische Verlängerung.</p></div>
<?php }
require_once __DIR__.'/module-components.php';

require_once __DIR__.'/module-contract-components.php';

function pending_product_cards(array $orders): void {foreach($orders as $order):$status=order_status_payload($order); ?>
<article class="panel service-card" id="bestellung-<?= (int)$order['id'] ?>"><div class="service-card-head"><span class="round-icon"><?= icon($order['category']==='domain'?'globe':'server') ?></span><span class="badge status-pending"><span></span><?= $order['fulfillment_status']==='needs_action'?'Einrichtung wird geprüft':'In Vorbereitung' ?></span></div><h2><a href="/konto/bestellungen?id=<?= (int)$order['id'] ?>"><?= e($order['product_name']) ?></a></h2><p class="service-identifier"><?= e($order['domain']?:$order['reference']) ?></p><dl class="detail-list"><div><dt>Bestellt am</dt><dd><?= date_de($order['created_at']) ?></dd></div><div><dt>Bestellnummer</dt><dd><?= e($order['reference']) ?></dd></div></dl><div class="info-box"><span><strong><?= e($status['label']) ?></strong><br>Den Fortschritt findest du in deiner Bestellung. Sobald die Einrichtung abgeschlossen ist, kannst du das Produkt hier verwalten.</span></div><a class="button button-teal full-width" href="/konto/bestellungen?id=<?= (int)$order['id'] ?>">Bestellung & Status ansehen <?= icon('arrow') ?></a><a class="button button-outline full-width" href="/konto/vertraege?id=<?= (int)$order['id'] ?>">Vertrag & Kündigung <?= icon('arrow') ?></a></article>
<?php endforeach; }
