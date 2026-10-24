<?php
require_once __DIR__.'/components.php';
$packages=array_values(array_filter(products(),fn($p)=>in_array($p['category'],['hosting','server'],true)));
// Only published packages and their assigned, enabled modules belong in the shop.
usort($packages,fn($a,$b)=>(int)product_has_customer_modules($b)<=>(int)product_has_customer_modules($a));
$slug=is_string($_GET['produkt']??null)?$_GET['produkt']:'';
$requested=null;foreach($packages as $candidate)if($candidate['slug']===$slug)$requested=$candidate;
$category=$requested['category']??(in_array($_GET['kategorie']??'',['hosting','server'],true)?$_GET['kategorie']:'hosting');
$choices=array_values(array_filter($packages,fn($p)=>$p['category']===$category));
$base=$slug!==''?$requested:($choices[0]??null);
$domain=is_string($_GET['domain']??null)?mb_substr($_GET['domain'],0,253):'';
$selection=$_GET['modules']??[];$configurationError='';$p=$base;
if($base){try{$p=selected_server_template(configured_product($base,$selection),$_GET['server_template']??0);}catch(RuntimeException $ex){$configurationError=$ex->getMessage();$selection=[];}}
$editable=$base && product_has_customer_modules($base);
$direct=$base && package_is_fixed($base);
$automation=$p && !$configurationError?checkout_automation($p):null;
$specs=$automation?package_specs($automation['provider'],$automation['settings']):[];
?>
<section class="page-intro container configurator-intro"><div class="eyebrow">DEINE IDEE. DEINE AUSSTATTUNG.</div><h1>Stell dir dein Hosting zusammen.</h1><p>Wähle Webhosting oder VPS, ein Grundpaket und die passenden Extras.<br>Du siehst deinen Preis inklusive 19 % MwSt. direkt bei der Auswahl.</p><div class="configurator-steps" aria-label="So funktioniert der Konfigurator"><span><b>1</b> Bereich wählen</span><span><b>2</b> Paket zusammenstellen</span><span><b>3</b> Bestellen oder anfragen</span></div></section>
<section class="container storefront-configurator">
<nav class="configurator-categories" aria-label="Bereich konfigurieren"><?php foreach(['hosting'=>['globe','Webhosting','Webspace, E-Mail und Datenbanken für deine Website.'],'server'=>['server','VPS','Rechenleistung und Speicher für deine Anwendungen.']] as $key=>[$ico,$label,$description]): ?><a href="<?= e('/konfigurator?'.http_build_query(['kategorie'=>$key,'domain'=>$domain])) ?>" class="configurator-category <?= $category===$key?'selected':'' ?>" <?= $category===$key?'aria-current="true"':'' ?>><span class="round-icon"><?= icon($ico) ?></span><span><strong><?= $label ?></strong><small><?= $description ?></small></span><?= icon('arrow') ?></a><?php endforeach ?></nav>
<?php if(!$base): if($slug!=='')http_response_code(404); ?>
<div class="panel"><?php empty_state('settings',$slug!==''?'Dieses Paket ist nicht verfügbar.':'Hier werden bald Pakete angeboten.','Wähle ein anderes Paket oder sprich mit uns über deine Anforderungen.','/produkte','Produkte ansehen'); ?></div>
<?php else: ?>
<form method="get" action="/konfigurator" class="panel configurator-package-picker"><input type="hidden" name="domain" value="<?= e($domain) ?>"><label class="field"><span>Dein Grundpaket</span><select name="produkt" data-configurator-package><?php foreach($choices as $choice): ?><option value="<?= e($choice['slug']) ?>" <?= $choice['slug']===$base['slug']?'selected':'' ?>><?= e($choice['name']) ?> · <?= package_is_fixed($choice)?'ab '.price($choice).' / '.cycle($choice['billing']):'auf Anfrage' ?><?= product_has_customer_modules($choice)?' · konfigurierbar':'' ?></option><?php endforeach ?></select></label><button class="button button-outline">Paket auswählen <?= icon('arrow') ?></button></form>
<?php if($configurationError): ?><div class="notice notice-error" role="alert"><span><?= e($configurationError) ?> <a class="text-link" href="<?= e('/konfigurator?'.http_build_query(['produkt'=>$base['slug'],'domain'=>$domain])) ?>">Auswahl zurücksetzen</a></span></div><?php endif ?>
<div class="configure-grid configurator-grid"><form method="get" action="<?= $direct?'/bestellen':'/konfigurieren' ?>" class="panel form-panel" data-store-configurator><input type="hidden" name="produkt" value="<?= e($base['slug']) ?>"><div class="panel-heading"><span class="step-number">02</span><div><h2><?= $editable?'Deine Ausstattung':'Dein Grundpaket' ?></h2><p><?= e($base['name']) ?></p></div></div>
<?php if(!$configurationError): server_template_picker($p);customer_module_fields($p,$selection); endif ?>
<?php if($editable && !$configurationError): ?><button class="button button-outline button-small" formaction="/konfigurator">Auswahl aktualisieren <?= icon('settings') ?></button><?php endif ?>
<?php if(!$editable): ?><div class="info-box"><?= icon('package') ?><span><?= $direct?'Dieses Paket hat eine feste Ausstattung. Zusätzliche Optionen werden angezeigt, sobald sie für dieses Paket angeboten werden.':'Beschreibe deine gewünschte Ausstattung im nächsten Schritt. Wir erstellen dir ein persönliches Angebot.' ?></span></div><?php endif ?>
<label class="field"><span><?= $category==='hosting'?'Domain für deinen Webspace':'Domain oder Projektadresse' ?> (optional)</span><input name="domain" maxlength="253" value="<?= e($domain) ?>" placeholder="meinprojekt.de"><small><?= $category==='hosting'?'Du kannst auch eine bestehende Domain nutzen. Eine neue Domain registrierst du separat über die Domainprüfung.':'Du kannst die Projektadresse auch später ergänzen.' ?></small></label>
<?php if($category==='hosting'): ?><a class="text-link" href="/domain-pruefen">Neue Wunschdomain prüfen <?= icon('globe') ?></a><?php endif ?>
<div class="configurator-continue"><button class="button button-teal full-width" <?= $configurationError?'disabled':'' ?>><?= $direct?'Weiter zur Bestellung':'Weiter zur unverbindlichen Anfrage' ?> <?= icon('arrow') ?></button><p class="form-footnote"><?= $direct?'Im nächsten Schritt prüfst du deine Auswahl und Rechnungsdaten. Bezahlt wird anschließend mit PayPal.':'Deine Auswahl wird in die Anfrage übernommen. Preis und Details vereinbaren wir im persönlichen Angebot.' ?><?= !user()?' Du kannst dich dann anmelden oder kostenlos registrieren.':'' ?></p></div>
</form><aside class="panel summary-panel configurator-summary"><div class="eyebrow">DEIN <?= $category==='hosting'?'WEBHOSTING':'VPS' ?></div><h2><?= e($base['name']) ?></h2><p><?= e($base['description']) ?></p><div class="summary-price"><span><?= $direct?'Gesamtpreis inkl. 19 % MwSt.':'Persönliches Angebot' ?></span><strong <?= !$direct?'data-preserve-price':'' ?>><?= $direct?price($p):'Auf Anfrage' ?></strong><?php if($direct): ?><small>pro <?= cycle($p['billing']) ?></small><?php else: ?><small>Der endgültige Preis wird im Angebot festgelegt.</small><?php endif ?></div>
<?php if($direct && !$configurationError): tax_summary(product_tax($p),$p['billing']); endif ?>
<?php if($specs): ?><h3>Deine konfigurierte Ausstattung</h3><dl class="detail-list package-specs" aria-live="polite"><?php foreach($specs as $caption=>$value): ?><div><dt><?= e($caption) ?></dt><dd data-package-spec-caption="<?= e($caption) ?>"><?= e($value) ?></dd></div><?php endforeach ?></dl><?php endif ?>
<?php if(json_decode($base['features'],true)): ?><ul class="feature-list"><?php foreach(json_decode($base['features'],true) as $feature): ?><li><?= icon('check') ?><span><?= e($feature) ?></span></li><?php endforeach ?></ul><?php endif ?>
<?php delivery_notices(product_notice($base),''); ?><p class="small muted"><?= e(contract_policy_text(product_contract_policy($base))) ?></p>
</aside></div>
<?php endif ?>
<?php if(admin()): ?><p class="configurator-admin-note"><a class="text-link" href="/admin/module">Module & Preise pflegen <?= icon('arrow') ?></a><span>Aktiviere deine Module und ordne sie unter „Pakete & Preise“ als wählbare Extras einem Paket mit aktiviertem Konfigurator zu.</span></p><?php endif ?>
</section>
