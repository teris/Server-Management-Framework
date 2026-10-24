<?php
function shop_migrate_appearance(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS shop_personal_preferences (actor_type VARCHAR(12) NOT NULL, actor_id INT NOT NULL, theme VARCHAR(10) NOT NULL DEFAULT 'dark', PRIMARY KEY (actor_type,actor_id))");
}
function appearance_actor(bool $adminArea): ?array {
    if($adminArea)return ($person=admin())?['type'=>'admin','id'=>(int)$person['id']]:null;
    if($person=user())return ['type'=>'customer','id'=>(int)$person['id']];
    return ($person=admin())?['type'=>'admin','id'=>(int)$person['id']]:null;
}
function appearance_theme(?array $actor=null): string {
    if($actor){$row=one('SELECT theme FROM shop_personal_preferences WHERE actor_type=? AND actor_id=?',[$actor['type'],$actor['id']]);return ($row['theme']??'dark')==='light'?'light':'dark';}
    return ($_COOKIE['orga_theme']??'dark')==='light'?'light':'dark';
}
function save_appearance(string $scope,mixed $theme): string {
    if(!is_string($theme) || !in_array($theme,['dark','light'],true))throw new RuntimeException('Bitte den hellen oder dunklen Modus wählen.');
    $person=match($scope){'admin'=>require_admin(),'customer'=>require_user(),'guest'=>null,default=>throw new RuntimeException('Ungültiges Profil.')};
    if($person){
        $sql=db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'
            ?'INSERT INTO shop_personal_preferences (actor_type,actor_id,theme) VALUES (?,?,?) ON DUPLICATE KEY UPDATE theme=VALUES(theme)'
            :'INSERT INTO shop_personal_preferences (actor_type,actor_id,theme) VALUES (?,?,?) ON CONFLICT(actor_type,actor_id) DO UPDATE SET theme=excluded.theme';
        if($scope==='customer'){
            db()->beginTransaction();try{
                $fresh=account_customer_lock((int)$person['id']);
                if(!$fresh || $fresh['status']!=='active')throw new RuntimeException('Dieses Kundenkonto ist nicht mehr verfügbar.');
                query($sql,[$scope,(int)$person['id'],$theme]);db()->commit();
            }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
        }else query($sql,[$scope,(int)$person['id'],$theme]);
    }
    setcookie('orga_theme',$theme,['expires'=>time()+31536000,'path'=>'/','secure'=>!ORGA_LOCAL,'httponly'=>true,'samesite'=>'Lax']);$_COOKIE['orga_theme']=$theme;
    return $theme;
}
function appearance_toggle(?array $actor,string $theme): void { ?>
<form method="post" class="theme-toggle-form" data-theme-toggle-form><?= csrf() ?><input type="hidden" name="action" value="appearance"><input type="hidden" name="scope" value="<?= e($actor['type']??'guest') ?>"><input type="hidden" name="theme" value="<?= $theme==='light'?'dark':'light' ?>"><button class="icon-button theme-toggle" type="submit" data-theme-toggle aria-label="<?= $theme==='light'?'Dunklen':'Hellen' ?> Modus aktivieren" title="<?= $theme==='light'?'Dunklen':'Hellen' ?> Modus aktivieren" aria-pressed="<?= $theme==='light'?'true':'false' ?>"><span data-theme-icon="light" <?= $theme==='light'?'hidden':'' ?>><?= icon('sun') ?></span><span data-theme-icon="dark" <?= $theme==='dark'?'hidden':'' ?>><?= icon('moon') ?></span></button><span class="sr-only" role="status" data-theme-feedback></span></form>
<?php }
function appearance_settings(bool $adminArea=false): void {$actor=appearance_actor($adminArea);$theme=appearance_theme($actor); ?>
<section class="panel appearance-settings" id="darstellung" aria-labelledby="appearance-heading">
    <div class="appearance-intro"><span class="round-icon"><?= icon('sun') ?></span><div class="eyebrow">DEIN PERSÖNLICHER LOOK</div><h2 id="appearance-heading">Darstellung</h2><p>Hell oder dunkel: Wähle den Look, mit dem du am liebsten arbeitest.</p><p class="appearance-profile-note"><?= icon('user') ?><span>Deine Auswahl bleibt in deinem persönlichen Profil gespeichert.</span></p></div>
    <form method="post" class="appearance-form"><?= csrf() ?><input type="hidden" name="action" value="appearance"><input type="hidden" name="scope" value="<?= $adminArea?'admin':'customer' ?>">
        <fieldset class="theme-options"><legend class="sr-only">Farbmodus wählen</legend>
        <?php foreach(['dark'=>['Dunkler Modus','Dunkle Flächen, helle Texte.','moon'],'light'=>['Heller Modus','Helle Flächen, dunkle Texte.','sun']] as $value=>[$label,$description,$icon]): ?>
        <label class="theme-option"><input type="radio" name="theme" value="<?= $value ?>" <?= $theme===$value?'checked':'' ?> required>
            <span class="theme-preview theme-preview-<?= $value ?>" aria-hidden="true"><span class="theme-preview-bar"><i></i><i></i><i></i></span><span class="theme-preview-sidebar"><i></i><b></b><b></b><b></b></span><span class="theme-preview-content"><span class="theme-preview-heading"></span><span class="theme-preview-stats"><i></i><i></i><i></i></span><span class="theme-preview-row"></span><span class="theme-preview-row"></span></span></span>
            <span class="theme-option-caption"><strong><?= icon($icon) ?> <?= $label ?></strong><small><?= $description ?></small></span>
        </label>
        <?php endforeach ?>
        </fieldset>
        <div class="appearance-form-footer"><p>Gilt auch bei deiner nächsten Anmeldung.</p><button class="button button-teal" type="submit">Darstellung speichern <?= icon('check') ?></button></div>
    </form>
</section>
<?php }
