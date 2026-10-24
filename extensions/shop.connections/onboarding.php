<?php
SMF\Core\ProviderAccounts::bootstrapShop();require_admin();
$page='admin_onboarding';$path='/admin/einrichtung';$portal=true;$isAdmin=true;$title='Einrichtung & Module';$error='';$message='';$preview=null;
try{
    if($_SERVER['REQUEST_METHOD']==='POST'){
        HttpSecurity::requireCsrf();$action=(string)($_POST['action']??'');
        if(in_array($action,['logout','appearance'],true)){require_once dirname(__DIR__).'/shop.storage/legacy/actions.php';handle_action($action);}
        switch($action){
            case'provider_check':$report=SMF\Core\ProviderDiagnostics::check((string)($_POST['provider']??''));$message=$report['message'];break;
            case'mail_test':SMF\Core\Onboarding::testMail();$message='Der SMTP-Server hat die Testmail an deine Administratoradresse angenommen.';break;
            case'module_disable':SMF\Core\Kernel::boot()->modules()->disable((string)($_POST['module']??''));$message='Modul deaktiviert. Vorhandene Daten bleiben gespeichert.';break;
            case'upgrade_preview':
                $selection=$_POST['modules']??[];if(!is_array($selection))throw new RuntimeException('Ungültige Modulauswahl.');$preview=SMF\Core\UpgradeManager::plan($selection);
                $_SESSION['module_upgrade_review']=['modules'=>$selection,'manifests'=>$preview,'expires'=>time()+1800,'token'=>bin2hex(random_bytes(24))];break;
            case'upgrade_apply':
                $review=$_SESSION['module_upgrade_review']??null;
                if(!$review || $review['expires']<time() || !hash_equals($review['token'],(string)($_POST['review_token']??'')) || SMF\Core\UpgradeManager::plan($review['modules'])!==$review['manifests'])throw new RuntimeException('Upgradeübersicht ist abgelaufen oder der Dateistand hat sich geändert.');
                SMF\Core\UpgradeManager::request($review['modules']);unset($_SESSION['module_upgrade_review']);$message='Upgrade zur gesicherten Verarbeitung durch den CLI-Worker vorgemerkt.';break;
            default:throw new SecurityViolation('Aktion nicht freigegeben.',403);
        }
    }
}catch(SecurityViolation$e){throw$e;}catch(Throwable$e){$error=$e instanceof PDOException?'Die Einrichtung ist derzeit nicht verfügbar.':$e->getMessage();}
$state=SMF\Core\Onboarding::status();
require_once dirname(__DIR__).'/shop.storage/legacy/views/components.php';require dirname(__DIR__).'/shop.storage/legacy/views/layout-start.php';
?>
<section class="page-heading"><h1>Einrichtung & Module</h1><p>Hier siehst du die tatsächliche Bereitschaft deiner Installation und kannst Module ergänzen.</p></section>
<?php if($error): ?><div class="notice notice-error" role="alert"><?= e($error) ?></div><?php endif ?>
<?php if($message): ?><div class="notice" role="status"><?= e($message) ?></div><?php endif ?>
<div class="card"><h2>Einrichtungsstatus</h2><?php foreach($state['checks'] as$check): ?><p><strong><?= $check['ready']?'Bereit':'Offen' ?></strong> · <a href="<?= e($check['url']) ?>"><?= e($check['label']) ?></a></p><?php endforeach ?>
<form method="post"><?= csrf() ?><input type="hidden" name="action" value="mail_test"><button class="button">SMTP-Testmail an meine Adresse senden</button></form><p>Der Empfang im eigenen Postfach wird durch den SMTP-Test nicht bestätigt.</p></div>
<div class="card"><h2>Provideranschlüsse</h2><?php foreach($state['providers'] as$id=>$provider): ?><section><h3><?= e(integration_definitions()[$id]['name']??$id) ?></h3><p><?= $provider['enabled']?'Anschluss aktiviert':'Anschluss deaktiviert' ?> · <?= count($provider['capabilities']) ?> aktuell geprüfte Fähigkeiten</p><p><?= e(implode(', ',$provider['capabilities'])) ?></p><a href="/admin/schnittstellen?anbieter=<?= rawurlencode($id) ?>">Zugang und Serverauswahl bearbeiten</a><form method="post"><?= csrf() ?><input type="hidden" name="action" value="provider_check"><input type="hidden" name="provider" value="<?= e($id) ?>"><button class="button button-outline">Verbindung und Fähigkeiten prüfen</button></form></section><?php endforeach ?></div>
<div class="card"><h2>Produktbereitschaft</h2><?php foreach($state['products'] as$id=>$product): ?><p><strong><?= e($id) ?></strong>: <?= $product['blockers']?e(implode('; ',$product['blockers'])):'Providerfähigkeiten vorhanden. Angebot, Zahlung und Vertragsangaben separat prüfen.' ?></p><?php endforeach ?><p><a href="/admin/gameserver">Gameserverangebote einrichten</a></p></div>
<div class="card"><h2>Module ergänzen und aktualisieren</h2><p>Der Auftrag enthält ausschließlich bereits auf diesem Server vorhandene, geprüfte Module. Bestehende Konten werden erhalten. Der Worker erstellt zuerst eine private Sicherung und führt danach die Migration aus.</p>
<form method="post"><?= csrf() ?><input type="hidden" name="action" value="upgrade_preview"><?php $registry=SMF\Core\Kernel::boot()->modules();foreach($registry->catalog() as$id=>$module): ?><label class="field"><span><input type="checkbox" name="modules[]" value="<?= e($id) ?>" <?= $registry->enabled($id)?'checked':'' ?>> <?= e($module['name']??$id) ?> · <?= e($module['version']) ?> (<?= $registry->installed($id)?($registry->enabled($id)?'aktiv':'deaktiviert'):'noch nicht installiert' ?>)</span></label><?php endforeach ?><button class="button">Upgradeübersicht erstellen</button></form>
<?php if($preview): ?><h3>Konkreter Auftrag mit Abhängigkeiten</h3><ol><?php foreach($preview as$id=>$hash): ?><li><?= e($id) ?></li><?php endforeach ?></ol><form method="post"><?= csrf() ?><input type="hidden" name="action" value="upgrade_apply"><input type="hidden" name="review_token" value="<?= e($_SESSION['module_upgrade_review']['token']) ?>"><button class="button">Gesichertes Upgrade vormerken</button></form><?php endif ?>
<p>Upgradezustand: <strong><?= e($state['upgrade']['state']) ?></strong> · <a href="/upgrade.php">Auftrag und Wiederherstellung</a></p>
</div>
<div class="card"><h2>Modul deaktivieren</h2><p>Aktive Abhängigkeiten verhindern die Abschaltung. Die Providerverbindung führt nach einer Abschaltung keine weiteren Aufrufe aus.</p><form method="post"><?= csrf() ?><input type="hidden" name="action" value="module_disable"><select name="module"><?php foreach($registry->catalog() as$id=>$module)if($registry->enabled($id)): ?><option value="<?= e($id) ?>"><?= e($module['name']??$id) ?></option><?php endif ?></select><button class="button button-outline">Deaktivieren</button></form></div>
<?php require dirname(__DIR__).'/shop.storage/legacy/views/layout-end.php'; ?>
