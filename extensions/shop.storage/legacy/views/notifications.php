<?php
require_once __DIR__.'/components.php';
$notificationScope=$page==='admin_notifications'?'admin':'customer';$notificationActor=notification_actor($notificationScope);
$notificationBase=$notificationScope==='admin'?'/admin/nachrichten':'/konto/nachrichten';
$overview=notification_overview($notificationScope,(int)$notificationActor['id'],notification_folder($_GET['ordner']??'inbox'),(int)($_GET['seite']??1));
?>
<div class="page-heading"><div class="eyebrow"><?= $notificationScope==='admin'?'DEINE ADMINISTRATION':'DEIN KUNDENKONTO' ?></div><h1>Benachrichtigungen</h1><p><?= $notificationScope==='admin'?'Bestellungen, Zahlungen und Bereitstellungen im Überblick.':'Angebote, Zahlungen und Bereitstellungen im Überblick.' ?></p></div>
<div class="notification-toolbar">
    <nav class="notification-folders" aria-label="Benachrichtigungsordner">
        <?php foreach(['inbox'=>'Posteingang','archive'=>'Archiv'] as $folder=>$caption): ?>
        <a href="<?= $notificationBase ?>?ordner=<?= $folder ?>" <?= $overview['folder']===$folder?'aria-current="page"':'' ?>><?= e($caption) ?><span><?= (int)$overview['counts'][$folder] ?></span></a>
        <?php endforeach ?>
    </nav>
    <?php if($overview['folder']==='inbox' && notification_unread_count($notificationScope,(int)$notificationActor['id'])): ?>
    <form method="post"><?= csrf() ?><input type="hidden" name="action" value="notifications_read"><input type="hidden" name="scope" value="<?= $notificationScope ?>"><button class="button button-outline button-small" type="submit">Alle als gelesen markieren <?= icon('check') ?></button></form>
    <?php endif ?>
</div>
<?php if($overview['rows']): ?>
<div class="notification-list">
    <?php foreach($overview['rows'] as $row): ?>
    <article class="panel notification-card <?= !$row['is_read'] && !$row['archived_ts']?'is-unread':'' ?>" data-notification-id="<?= (int)$row['id'] ?>">
        <span class="round-icon"><?= icon('message') ?></span>
        <div class="notification-content"><p class="eyebrow"><?= date_de($row['created_at']) ?><?= !$row['is_read'] && !$row['archived_ts']?' · NEU':'' ?></p><h2><?= e($row['title']) ?></h2><p><?= e($row['body']) ?></p>
            <div class="notification-card-actions">
                <a class="text-link" href="<?= e($row['url']) ?>">Öffnen <?= icon('arrow') ?></a>
                <?php $rowActions=$row['archived_ts']?['notification_restore'=>'Wiederherstellen']:['notification_archive'=>'Archivieren'];if(!$row['is_read'])$rowActions['notification_read']='Als gelesen markieren';$rowActions['notification_delete']='Löschen';foreach($rowActions as $action=>$caption): ?>
                <form method="post" <?= $action==='notification_delete'?'data-confirm="Diese Benachrichtigung endgültig löschen?"':'' ?>><?= csrf() ?><input type="hidden" name="action" value="<?= $action ?>"><input type="hidden" name="scope" value="<?= $notificationScope ?>"><input type="hidden" name="notification_id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="folder" value="<?= $overview['folder'] ?>"><input type="hidden" name="notification_page" value="<?= (int)$overview['page'] ?>"><button class="button button-outline button-small" type="submit"><?= e($caption) ?></button></form>
                <?php endforeach ?>
            </div>
        </div>
    </article>
    <?php endforeach ?>
</div>
<?php else: ?>
<section class="panel"><?php empty_state('message',$overview['folder']==='archive'?'Dein Archiv ist leer':'Du bist auf dem aktuellen Stand',$overview['folder']==='archive'?'Archivierte Benachrichtigungen kannst du hier später wiederherstellen oder löschen.':'Neue Benachrichtigungen erscheinen hier. Bereits archivierte Meldungen findest du im Archiv.'); ?></section>
<?php endif ?>
<?php if($overview['pages']>1): ?>
<nav class="notification-pagination" aria-label="Seiten der Benachrichtigungen">
    <?php if($overview['page']>1): ?><a class="button button-outline button-small" href="<?= $notificationBase ?>?ordner=<?= $overview['folder'] ?>&amp;seite=<?= $overview['page']-1 ?>">← Zurück</a><?php endif ?>
    <span>Seite <?= (int)$overview['page'] ?> von <?= (int)$overview['pages'] ?></span>
    <?php if($overview['page']<$overview['pages']): ?><a class="button button-outline button-small" href="<?= $notificationBase ?>?ordner=<?= $overview['folder'] ?>&amp;seite=<?= $overview['page']+1 ?>">Weiter →</a><?php endif ?>
</nav>
<?php endif ?>
