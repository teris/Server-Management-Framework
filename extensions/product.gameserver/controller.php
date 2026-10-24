<?php
SMF\Core\ProviderAccounts::bootstrapShop();
require_once __DIR__.'/Catalog.php';require_once __DIR__.'/Service.php';
$path=rtrim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'/','/')?:'/';
$isAdmin=$path==='/admin/gameserver';$portal=$isAdmin || $path==='/konto/gameserver';
if($isAdmin)require_admin();elseif($portal)require_user();
$error='';$message='';$selected=null;$offer=null;$configured=null;$game=new SMF\Modules\Gameserver\Service();
try {
    if($_SERVER['REQUEST_METHOD']==='POST'){
        HttpSecurity::requireCsrf();$action=(string)($_POST['action']??'');
        if(in_array($action,['logout','appearance'],true)){require_once dirname(__DIR__).'/shop.storage/legacy/actions.php';handle_action($action);}
        if($isAdmin && $action==='game_reconcile'){$game->reconcile((int)($_POST['order_id']??0));flash('Eigene OGP-Schreibabsicht abgeglichen.');redirect('/admin/gameserver');}
        if($isAdmin && $action==='game_offer_save'){
            $id=(int)($_POST['product_id']??0);
            if(!$id){
                $name=text_field('name',120);if($name==='')throw new RuntimeException('Produktname fehlt.');
                query('INSERT INTO shop_products (slug,name,eyebrow,category,icon,description,features,active) VALUES (?,?,?,?,?,?,?,?)',['gameserver-'.bin2hex(random_bytes(6)),$name,'Dein eigener Gameserver','gameserver','server','Gameserver mit reserviertem Portkontingent und ausgewählten Slots.','[]',1]);$id=(int)db()->lastInsertId();
            }
            SMF\Modules\Gameserver\Catalog::save($id,$_POST,isset($_POST['enabled']));flash('Gameserverangebot gespeichert.');redirect('/admin/gameserver?id='.$id);
        }elseif(!$isAdmin && $action==='game_checkout'){
            $customer=require_user();$p=one("SELECT * FROM shop_products WHERE id=? AND category='gameserver' AND active=1",[(int)($_POST['product_id']??0)]);if(!$p)throw new RuntimeException('Gameserverprodukt nicht gefunden.');
            $options=array_intersect_key($_POST,array_flip(['terms','privacy','invoice_pdf']));$options['gameserver']=['slots'=>$_POST['slots']??''];
            $order=start_checkout_order($customer,$p,'',(string)($_POST['checkout_key']??''),$options);
            if(!empty($order['approval_url']))approval_redirect($order['approval_url']);redirect('/konto/bestellungen?id='.$order['id']);
        }elseif(!$isAdmin && $action==='game_enquiry'){
            $customer=require_user();assert_customer_can_order((int)$customer['id']);rate_limit('gameserver_enquiry',8,900);
            $p=one("SELECT * FROM shop_products WHERE id=? AND category='gameserver' AND active=1",[(int)($_POST['product_id']??0)]);if(!$p)throw new RuntimeException('Gameserverprodukt nicht gefunden.');
            $p=SMF\Modules\Gameserver\Catalog::configure($p,['slots'=>$_POST['slots']??'']);
            query('INSERT INTO shop_requests (reference,customer_id,product_slug,product_name,price_cents,billing,configuration,notes) VALUES (?,?,?,?,?,?,?,?)',['GS-'.date('ym').'-'.strtoupper(bin2hex(random_bytes(5))),$customer['id'],$p['slug'],$p['name'],$p['price_cents'],$p['billing'],json_encode($p['_game_snapshot'],JSON_THROW_ON_ERROR),text_field('notes',3000)]);flash('Deine Gameserveranfrage wurde gespeichert.');redirect('/konto/anfragen');
        }elseif($portal && !$isAdmin && $action==='game_control'){
            $customer=require_user();$id=$game->queueControl((int)($_POST['service_id']??0),(int)$customer['id'],(string)($_POST['command']??''),(string)($_POST['command_key']??''),(string)($_POST['rcon']??''));flash('Aktion zur Verarbeitung vorgemerkt.');redirect('/konto/gameserver?id='.(int)$_POST['service_id']);
        }else throw new SecurityViolation('Aktion nicht freigegeben.',403);
    }
    if(!$portal || $isAdmin){
        $selected=isset($_GET['id'])?one("SELECT * FROM shop_products WHERE id=? AND category='gameserver'".($isAdmin?'':' AND active=1'),[(int)$_GET['id']]):null;
        if($selected){$offer=SMF\Modules\Gameserver\Catalog::offer((int)$selected['id']);if($offer)$configured=SMF\Modules\Gameserver\Catalog::configure($selected,['slots'=>$_GET['slots']??$offer['policy']['slot_min']]);}
    }
}catch(SecurityViolation $e){throw $e;}catch(Throwable $e){$error=$e instanceof PDOException?'Die Anfrage konnte nicht gespeichert werden.':$e->getMessage();}
$page=$isAdmin?'admin_gameserver':($portal?'game_services':'game_catalog');
$title=$isAdmin?'Gameserverangebote':($portal?'Meine Gameserver':'Gameserver konfigurieren');
require_once dirname(__DIR__).'/shop.storage/legacy/views/components.php';require dirname(__DIR__).'/shop.storage/legacy/views/layout-start.php';
if($error)echo '<div class="notice notice-error" role="alert">'.e($error).'</div>';
if(isset($_SESSION['flash'])){[$message,$kind]=$_SESSION['flash'];unset($_SESSION['flash']);echo '<div class="notice" role="status">'.e($message).'</div>';}
?>
<section class="page-heading"><h1><?= e($title) ?></h1><p>Spiel, Standort und Ausstattung bleiben mit deiner Bestellung verbunden.</p></section>
<?php if($isAdmin): ?>
<p><a href="/admin/einrichtung">Anschlüsse und Einrichtung prüfen</a> · <a href="/admin/gameserver">Neues Angebot</a></p>
<form method="post" class="card"><?= csrf() ?><input type="hidden" name="action" value="game_reconcile"><label class="field"><span>Unklaren OGP-Auftrag abgleichen (Bestell-ID)</span><input type="number" name="order_id" min="1" required></label><button class="button button-outline">Eigene Anlage anhand der Auftragskennung prüfen</button></form>
<div class="card"><h2>Vorhandene Angebote</h2><?php foreach(all("SELECT id,name FROM shop_products WHERE category='gameserver' ORDER BY id")as$p): ?><p><a href="/admin/gameserver?id=<?= (int)$p['id'] ?>"><?= e($p['name']) ?></a></p><?php endforeach ?></div>
<form method="post" class="card"><?= csrf() ?><input type="hidden" name="action" value="game_offer_save"><input type="hidden" name="product_id" value="<?= (int)($selected['id']??0) ?>">
<?php if(!$selected): ?><label class="field"><span>Produktname</span><input name="name" required maxlength="120"></label><?php endif ?>
<?php $policy=$offer['policy']??[];$fields=['agent_id'=>['Agent-ID',1],'home_cfg_id'=>['Spieldefinition-ID',1],'mod_cfg_id'=>['Moddefinition-ID',1],'ip'=>['Freigegebene Agent-IPv4',''],'location'=>['Standort','Deutschland'],'slot_min'=>['Mindestens Slots',4],'slot_max'=>['Höchstens Slots',32],'slot_step'=>['Slotabstufung',1],'port_min'=>['Erster exklusiver Shopport',30000],'port_max'=>['Letzter exklusiver Shopport',30049],'port_block'=>['Reservierte Ports pro Spiel',10],'base_net_cents'=>['Grundpreis netto in Cent',0],'slot_net_cents'=>['Preis je Slot netto in Cent',50],'retention_days'=>['Aufbewahrung nach Vertragsende in Tagen',7]];foreach($fields as$key=>[$label,$default]): ?>
<label class="field"><span><?= e($label) ?></span><input name="<?= e($key) ?>" value="<?= e($policy[$key]??$default) ?>" <?= is_int($default)?'type="number" min="0"':'type="text"' ?> required></label>
<?php endforeach ?>
<label class="field"><span>Betriebssystem</span><select name="system"><?php foreach(['linux','windows']as$v): ?><option <?= ($policy['system']??'linux')===$v?'selected':'' ?>><?= e($v) ?></option><?php endforeach ?></select></label>
<label class="field"><span>Architektur</span><select name="architecture"><?php foreach(['64','32']as$v): ?><option <?= ($policy['architecture']??'64')===$v?'selected':'' ?>><?= e($v) ?></option><?php endforeach ?></select></label>
<label class="field"><span>Startkarte (falls vom Spiel benötigt)</span><input name="start_map" maxlength="80" value="<?= e($policy['start_map']??'') ?>"></label>
<label class="field"><span>Installation</span><select name="install_method"><?php foreach(['steam','rsync']as$v): ?><option <?= ($policy['install_method']??'steam')===$v?'selected':'' ?>><?= e($v) ?></option><?php endforeach ?></select></label>
<label class="field"><span>Leistung, Laufzeit und Kündigung</span><textarea name="contract_text" required maxlength="2000"><?= e($policy['contract_text']??'') ?></textarea></label>
<label><input type="checkbox" name="enabled" <?= !empty($offer['enabled'])?'checked':'' ?>> Direktkauf freigeben; der Portbereich ist ausschließlich diesem Shop vorbehalten.</label>
<p>RAM, Datenträgergröße und harte CPU-Limits werden über diese OGP-API nicht als garantierte Kontingente verkauft. Spieldefinition und Agent werden beim Speichern aus OGP geprüft.</p><button class="button">Angebot speichern</button></form>
<details class="card"><summary>OGP-Spieldefinitionen und Agents anzeigen</summary><?php try{$api=SMF\Core\Kernel::boot()->providers()->get('ogp');echo '<h3>Agents</h3>';foreach($api->call('GET','server/list')as$a)echo '<p>'.(int)$a['remote_server_id'].' · '.e($a['remote_server_name']??'Agent').'</p>';echo '<h3>Linux 64 Bit</h3>';foreach($api->call('GET','user_games/list_games',['system'=>'linux','architecture'=>'64'])as$g){echo '<p>Spiel '.(int)$g['home_cfg_id'].' · '.e($g['game_name']);foreach($g['mods']??[] as$mod)echo ' · Mod '.(int)$mod['mod_cfg_id'].' '.e($mod['mod_key']);echo '</p>';}}catch(Throwable$e){echo '<p>OGP-Anschluss bitte zuerst unter Einrichtung prüfen.</p>';} ?></details>
<?php elseif($portal): ?>
<?php $services=all("SELECT s.id,s.name,s.identifier,s.status FROM shop_services s JOIN shop_resource_links l ON l.service_id=s.id WHERE s.customer_id=? AND l.provider='ogp' ORDER BY s.id DESC",[(int)user()['id']]);if(!$services): ?><div class="card"><p>Du hast noch keinen eingerichteten Gameserver.</p><a class="button" href="/gameserver">Gameserver entdecken</a></div><?php endif ?>
<?php foreach($services as$s): ?><div class="card"><h2><?= e($s['name']) ?></h2><p><?= e($s['identifier']) ?> · <?= e(label($s['status'])) ?></p><a href="/konto/gameserver?id=<?= (int)$s['id'] ?>">Server anzeigen</a></div><?php endforeach ?>
<?php if(isset($_GET['id'])): ?>
<?php try{$service=$game->inspect((int)$_GET['id'],(int)user()['id']);$state=$service['game_state'];$commandKey=bin2hex(random_bytes(16)); ?>
<div class="card"><h2><?= e($service['name']) ?></h2><p><?= $state['running']?'Spielprozess läuft.':'Spielprozess ist gestoppt.' ?></p>
<?php if($service['status']==='active' && SMF\Core\ShopSettings::allowLive()): ?><form method="post"><?= csrf() ?><input type="hidden" name="action" value="game_control"><input type="hidden" name="service_id" value="<?= (int)$service['id'] ?>"><input type="hidden" name="command_key" value="<?= e($commandKey) ?>"><?php foreach(['start'=>'Starten','stop'=>'Stoppen','restart'=>'Neustarten']as$cmd=>$caption): ?><button name="command" value="<?= e($cmd) ?>" class="button"><?= e($caption) ?></button><?php endforeach ?><label class="field"><span>RCON: status, players, list oder say Nachricht</span><input name="rcon" maxlength="184"></label><button name="command" value="rcon" class="button">RCON senden</button></form><?php endif ?>
<?php foreach($game->operations((int)$service['id'],(int)user()['id']) as$operation): ?><p><?= e($operation['action']) ?> · <?= e($operation['state']) ?></p><?php if($operation['reply']!==''): ?><pre><?= e($operation['reply']) ?></pre><?php endif ?><?php endforeach ?>
<details><summary>Serverprotokoll</summary><pre><?= e($game->log((int)$service['id'],(int)user()['id'])) ?></pre></details></div>
<?php }catch(Throwable$e){http_response_code(403);echo '<div class="notice notice-error" role="alert">Gameserver ist für dieses Konto derzeit nicht verfügbar; Zuordnung oder Anschluss muss geprüft werden.</div>';} ?>
<?php endif ?>
<?php else: ?>
<?php foreach(all("SELECT p.* FROM shop_products p JOIN smf_game_offers g ON g.product_id=p.id WHERE p.category='gameserver' AND p.active=1 ORDER BY p.sort_order,p.id") as$p): ?><div class="card"><h2><?= e($p['name']) ?></h2><p><?= e($p['description']) ?></p><a class="button" href="/gameserver?id=<?= (int)$p['id'] ?>">Slots und Preis auswählen</a></div><?php endforeach ?>
<?php if($selected && $offer && $configured):$policy=$offer['policy']; ?>
<div class="card"><h2><?= e($selected['name']) ?></h2><p><?= e($policy['game_name']) ?> · <?= e($policy['location']) ?></p>
<form method="get"><input type="hidden" name="id" value="<?= (int)$selected['id'] ?>"><label class="field"><span>Slots</span><input name="slots" type="number" min="<?= (int)$policy['slot_min'] ?>" max="<?= (int)$policy['slot_max'] ?>" step="<?= (int)$policy['slot_step'] ?>" value="<?= (int)$configured['_game_snapshot']['slots'] ?>"></label><button class="button">Preis berechnen</button></form>
<p><strong><?= money($configured['price_cents']) ?> / <?= e(cycle($configured['billing'])) ?></strong> inkl. USt.</p><p><?= nl2br(e($policy['contract_text'])) ?></p>
<?php $blockers=package_blockers($configured);$key=bin2hex(random_bytes(24));$_SESSION['checkout_key']=$key;$_SESSION['checkout_offer']=checkout_fingerprint($configured);$documents=checkout_documents_for($configured); ?>
<form method="post"><?= csrf() ?><input type="hidden" name="product_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="slots" value="<?= (int)$configured['_game_snapshot']['slots'] ?>"><input type="hidden" name="checkout_key" value="<?= e($key) ?>">
<?php if(!$blockers): ?><input type="hidden" name="action" value="game_checkout"><label><input type="checkbox" name="terms" required> Ich akzeptiere die <a href="<?= e($documents['terms_url']) ?>">Vertragsbedingungen</a> und habe die <a href="<?= e($documents['withdrawal_url']) ?>">Widerrufsinformationen</a> gelesen.</label><label><input type="checkbox" name="privacy" required> Ich habe die Datenschutzhinweise gelesen.</label><label><input type="checkbox" name="invoice_pdf" required> Ich stimme dem Rechnungserhalt als PDF zu.</label><button class="button">Zahlungspflichtig bestellen</button>
<?php else: ?><input type="hidden" name="action" value="game_enquiry"><p>Dieses Angebot ist derzeit auf Anfrage verfügbar. Es wird keine Zahlung ausgelöst.</p><label class="field"><span>Deine Nachricht</span><textarea name="notes" maxlength="3000"></textarea></label><button class="button">Angebot anfragen</button><?php endif ?></form></div>
<?php endif ?>
<?php endif ?>
<?php require dirname(__DIR__).'/shop.storage/legacy/views/layout-end.php'; ?>
