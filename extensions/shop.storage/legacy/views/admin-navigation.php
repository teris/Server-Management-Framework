<?php
$groups=[
    'verkauf'=>['Verkauf & Abrechnung','lock',[
        '/admin/bestellungen'=>['lock','Bestellungen'],
        '/admin/anfragen'=>['mail','Anfragen'],
        '/admin/angebote'=>['mail','Angebote'],
        '/admin/gutscheine'=>['bolt','Gutscheine & Rabatte'],
        '/admin/rechnungen'=>['package','Rechnungen'],
        '/admin/vertraege'=>['clock','Verträge & Kündigungen'],
    ]],
    'produkte'=>['Produkte & Konfigurator','package',[
        '/admin/produkte'=>['package','Pakete & Preise'],
        '/admin/module'=>['grid','Zusatzmodule'],
        '/admin/domains'=>['globe','Domainendungen & Preise'],
    ]],
    'kunden'=>['Kunden & Support','user',[
        '/admin/kunden'=>['user','Kunden & Produkte'],
        '/admin/support'=>['headset','Support-Tickets'],
    ]],
    'technik'=>['Infrastruktur','server',[
        '/admin/schnittstellen'=>['settings','Schnittstellen'],
        '/admin/infrastruktur'=>['server','IP, LXC & DNS-Vorlagen'],
        '/admin/automatisierung'=>['bolt','Bereitstellung'],
        '/admin/ressourcen'=>['database','Ressourcen'],
        '/admin/vps-sicherungen'=>['database','VPS-Abschluss & Sicherungen'],
    ]],
    'inhalte'=>['Inhalte & Dokumente','compass',[
        '/admin/faq'=>['message','FAQ & Anleitungen'],
        '/admin/dokumente'=>['package','Vertragsunterlagen & PDF'],
    ]],
    'einstellungen'=>['Einstellungen','settings',[
        '/admin/shop'=>['settings','Shop & Rechnungsdaten'],
        '/admin/email'=>['mail','E-Mail-Versand'],
        '/admin/nachrichten'=>['message','Benachrichtigungen'],
        '/admin/einstellungen'=>['user','Persönliches Profil'],
    ]],
];
$moduleRoutes=SMF\Core\Kernel::boot()->modules()->routes();foreach($groups as &$navGroup)foreach(array_keys($navGroup[2])as $navUrl)if(!isset($moduleRoutes[$navUrl]))unset($navGroup[2][$navUrl]);unset($navGroup);

$adminUnread=notification_unread_count('admin',(int)$person['id']);
?>
<a href="/admin" class="sidebar-link <?= $path==='/admin'?'active':'' ?>" <?= $path==='/admin'?'aria-current="page"':'' ?>><?= icon('grid') ?><span>Übersicht</span><?= $path==='/admin'?'<i></i>':'' ?></a>
<?php foreach($groups as $key=>[$name,$ico,$links]):
$groupActive=isset($links[$path]) || ($key==='kunden' && $page==='admin_ticket'); ?>
<details class="admin-nav-group <?= $groupActive?'has-active':'' ?>" name="admin-navigation" data-admin-nav-group="<?= $key ?>" <?= $groupActive?'open':'' ?>>
    <summary><?= icon($ico) ?><span><?= e($name) ?></span><?php if($key==='einstellungen' && $adminUnread): ?><span class="nav-count"><span aria-hidden="true"><?= $adminUnread ?></span><span class="sr-only"><?= $adminUnread ?> ungelesene Benachrichtigungen</span></span><?php endif ?><?= icon('chevron','nav-chevron') ?></summary>
    <div class="admin-nav-links">
    <?php foreach($links as $url=>[$linkIcon,$label]): $active=$path===$url || ($url==='/admin/support' && $page==='admin_ticket'); ?>
        <a href="<?= $url ?>" class="sidebar-link <?= $active?'active':'' ?>" <?= $active?'aria-current="page"':'' ?>><?= icon($linkIcon) ?><span><?= e($label) ?></span><?= $active?'<i></i>':'' ?></a>
    <?php endforeach ?>
    </div>
</details>
<?php endforeach ?>
