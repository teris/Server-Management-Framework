<?php $appearanceActor=appearance_actor($isAdmin);$appearanceTheme=appearance_theme($appearanceActor); ?>
<!doctype html>
<html lang="de" data-theme="<?= $appearanceTheme ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="<?= $appearanceTheme==='light'?'#f5f7fb':'#09090b' ?>">
    <meta name="color-scheme" content="<?= $appearanceTheme ?>">
    <meta name="description" content="Webhosting, virtuelle Server und Domains von Server Management. Individuelle IT-Lösungen mit persönlichem Ansprechpartner und eigenem Kundenbereich.">
    <?php if ($portal || in_array($page,['login','register','admin_login'],true)): ?><meta name="robots" content="noindex,nofollow"><?php endif ?>
    <title><?= e($title) ?> · <?= e(SMF\Core\ShopSettings::get('site_name')) ?></title>


    <link rel="stylesheet" href="/public/assets/shop/shop.css?v=24">
    <link rel="stylesheet" href="/public/shop-theme.php">
    <script src="/public/assets/shop/shop.js?v=25" defer></script>
</head>
<body class="<?= $portal?'portal-body':'store-body' ?> page-<?= e($page) ?>">
<a class="skip-link" href="#main">Zum Inhalt springen</a>
<?php if ($portal): $person=$isAdmin?admin():user(); ?>
<div class="portal-shell">
    <aside class="sidebar <?= $isAdmin?'admin-sidebar':'customer-sidebar' ?>" id="navigation">
        <button class="icon-button sidebar-close" type="button" data-menu-close aria-label="Navigation schließen"><?= icon('plus') ?></button>
        <a class="brand" href="/" aria-label="Server Management Startseite"><?= shop_brand() ?><span class="brand-context"><?= $isAdmin?'ADMINISTRATION':'KUNDENBEREICH' ?></span></a>
        <div class="sidebar-section-label"><?= $isAdmin?'VERWALTUNG':'DEIN KONTO' ?></div>
        <nav aria-label="<?= $isAdmin?'Administration':'Kundenbereich' ?>">
        <?php require __DIR__.($isAdmin?'/admin-navigation.php':'/customer-navigation.php'); ?>
        </nav>
        <div class="sidebar-help"><?= icon('headset') ?><strong>Eine Frage offen?</strong><p>Wir helfen dir persönlich weiter.</p><a href="<?= $isAdmin?'/admin/support':'/konto/support' ?>">Zum Support <?= icon('arrow') ?></a></div>
        <a class="sidebar-link back-shop" href="/"><?= icon('external') ?><span>Zum Hosting-Shop</span></a>
        <div class="sidebar-person"><span class="avatar"><?= e(strtoupper(substr($person['first_name']??$person['username'],0,1))) ?></span><span><strong><?= e($person['first_name']??$person['username']) ?></strong><small><?= $isAdmin?'Administrator':'Kundenkonto' ?></small></span><form method="post"><?= csrf() ?><input type="hidden" name="action" value="logout"><button class="icon-button" title="Abmelden" aria-label="Abmelden"><?= icon('logout') ?></button></form></div>
    </aside>
    <div class="portal-main">
        <header class="portal-header"><button class="icon-button mobile-menu" data-menu-toggle aria-controls="navigation" aria-expanded="false" aria-label="Menü öffnen"><?= icon('menu') ?></button><div class="breadcrumbs">Server Management <span>/</span> <?= e($title) ?></div><div class="portal-header-actions"><?php appearance_toggle($appearanceActor,$appearanceTheme); ?><a href="/produkte" class="button button-small button-outline"><?= icon('plus') ?> Produkt entdecken</a></div></header>
        <main id="main" class="portal-content">
<?php else: ?>
<div class="announcement"><div class="container"><span>Individuelle IT. Persönlich betreut.</span><div class="announcement-actions"><a href="/">Mehr über Server Management <?= icon('external') ?></a><?php appearance_toggle($appearanceActor,$appearanceTheme); ?></div></div></div>
<header class="site-header"><div class="container header-inner">
    <a class="brand" href="/" aria-label="Server Management Startseite"><?= shop_brand() ?><span class="brand-context">HOSTING & IT-LÖSUNGEN</span></a>
    <button class="icon-button mobile-menu" data-menu-toggle aria-controls="navigation" aria-expanded="false" aria-label="Menü öffnen"><?= icon('menu') ?></button>
    <nav class="main-nav" id="navigation" aria-label="Hauptnavigation">
        <a href="/produkte" <?= $page==='products'?'aria-current="page"':'' ?>>Produkte</a>
        <a href="/konfigurator" <?= $page==='configurator'?'aria-current="page"':'' ?>>Konfigurator</a>
        <a href="/module" <?= $page==='modules'?'aria-current="page"':'' ?>>Module & Zugänge</a>
        <a href="/faq">FAQ & Anleitungen</a>
        <a href="/hilfe" <?= $page==='help'?'aria-current="page"':'' ?>>Hilfe & Kontakt</a>
        <a href="<?= user()?'/konto':'/login' ?>" class="button button-dark button-small"><?= icon('user') ?> <?= user()?'Mein Kundenbereich':'Kundenlogin' ?> <?= icon('arrow') ?></a>
    </nav>
</div></header>
<main id="main">
<?php endif ?>

<?php if(!$isAdmin)account_deletion_banner(); ?>
