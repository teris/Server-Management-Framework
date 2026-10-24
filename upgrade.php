<?php
try{
    require_once __DIR__.'/framework.php';require_once __DIR__.'/src/sys.conf.php';
    Identity::requireIdentity('backend',true);
    if($_SERVER['REQUEST_METHOD']==='POST'){
        HttpSecurity::requireCsrf();$command=(string)($_POST['command']??'');
        if(in_array($command,['resume','restore'],true))SMF\Core\UpgradeManager::recover((string)($_POST['id']??''),$command);
        else throw new SecurityViolation('Aktion nicht freigegeben.',403);
        header('Location: /upgrade.php',true,303);exit;
    }
    $state=SMF\Core\UpgradeManager::publicState();
    header('Content-Type: text/html; charset=utf-8');header("Content-Security-Policy: default-src 'self'; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
}catch(Throwable$error){if(class_exists('HttpSecurity'))HttpSecurity::abort($error instanceof SecurityViolation?$error->getCode():503,'Upgradezustand ist derzeit nicht verfügbar.');http_response_code(503);exit;}
?>
<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Upgrade und Wiederherstellung</title><link rel="stylesheet" href="/public/assets/setup-wizard.css"><main class="setup-card"><h1>Upgrade und Wiederherstellung</h1><p>Ein vorhandener Shop wird über den CLI-Worker aktualisiert. Die Erstinstallation bleibt geschlossen.</p><p>Status: <strong><?= htmlspecialchars($state['state'],ENT_QUOTES,'UTF-8') ?></strong></p>
<?php if(in_array($state['state'],['needs_review','running'],true)): ?><p>Die Migration wurde angehalten. Private Sicherung und Journal prüfen; danach denselben Auftrag fortsetzen oder die Datenbank auf die gesicherte Version zurücksetzen.</p><form method="post"><?= HttpSecurity::csrfField() ?><input type="hidden" name="id" value="<?= htmlspecialchars($state['id'],ENT_QUOTES,'UTF-8') ?>"><button name="command" value="resume">Fortsetzung vormerken</button><button name="command" value="restore">Gesicherte Datenbank wiederherstellen</button></form><?php endif ?>
<p><a href="/admin/einrichtung">Einrichtungsstatus</a></p></main></html>
