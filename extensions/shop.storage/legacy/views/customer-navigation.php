<?php
$customerGroups=[
    'produkte'=>['Produkte & Dienste','package',[
        '/konto/gameserver'=>['server','Gameserver'],
    '/konto/produkte'=>['package','Meine Produkte'],
        '/konto/server'=>['server','Virtuelle Server'],
        '/konto/vps-sicherungen'=>['database','VPS-Sicherungen'],
        '/konto/module'=>['grid','Module & Direktzugänge'],
    ]],
    'bestellungen'=>['Bestellungen & Abrechnung','lock',[
        '/konto/bestellungen'=>['lock','Bestellungen & Zahlungen'],
        '/konto/angebote'=>['mail','Meine Angebote'],
        '/konto/anfragen'=>['mail','Meine Anfragen'],
        '/konto/rechnungen'=>['package','Rechnungen'],
        '/konto/vertraege'=>['clock','Verträge & Kündigungen'],
        '/konto/gutscheine'=>['bolt','Gutscheine & Zusatzpakete'],
    ]],
    'support'=>['Hilfe & Support','headset',[
        '/konto/support'=>['headset','Support'],
        '/faq'=>['compass','FAQ & Anleitungen'],
    ]],
    'konto'=>['Mein Konto','user',[
        '/konto/nachrichten'=>['message','Benachrichtigungen'],
        '/konto/einstellungen'=>['settings','Einstellungen'],
    ]],
];
$moduleRoutes=SMF\Core\Kernel::boot()->modules()->routes();foreach($customerGroups as &$navGroup)foreach(array_keys($navGroup[2])as $navUrl)if(!isset($moduleRoutes[$navUrl]))unset($navGroup[2][$navUrl]);unset($navGroup);

$customerNavPath=match($page){'manage_resource','module_checkout'=>'/konto/produkte','ticket'=>'/konto/support',default=>$path};
$unread=notification_unread_count('customer',(int)$person['id']);
?>
<a href="/konto" class="sidebar-link <?= $path==='/konto'?'active':'' ?>" <?= $path==='/konto'?'aria-current="page"':'' ?>><?= icon('grid') ?><span>Übersicht</span><?= $path==='/konto'?'<i></i>':'' ?></a>
<?php foreach($customerGroups as $key=>[$name,$ico,$links]):$groupActive=isset($links[$customerNavPath]); ?>
<details class="customer-nav-group <?= $groupActive?'has-active':'' ?>" name="customer-navigation" data-customer-nav-group="<?= $key ?>" <?= $groupActive?'open':'' ?>>
    <summary><?= icon($ico) ?><span><?= e($name) ?></span><?php if($key==='konto' && $unread): ?><span class="nav-count"><span aria-hidden="true"><?= $unread ?></span><span class="sr-only"><?= $unread ?> ungelesene Benachrichtigungen</span></span><?php endif ?><?= icon('chevron','nav-chevron') ?></summary>
    <div class="customer-nav-links">
    <?php foreach($links as $url=>[$linkIcon,$label]):$active=$customerNavPath===$url; ?>
        <a href="<?= $url ?>" class="sidebar-link <?= $active?'active':'' ?>" <?= $active?'aria-current="page"':'' ?>><?= icon($linkIcon) ?><span><?= e($label) ?><?= $url==='/konto/nachrichten' && $unread?' ('.$unread.')':'' ?></span><?= $active?'<i></i>':'' ?></a>
    <?php endforeach ?>
    </div>
</details>
<?php endforeach ?>
