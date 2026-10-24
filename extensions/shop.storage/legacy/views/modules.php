<?php require_once __DIR__.'/components.php'; ?>
<section class="<?= $portal?'module-directory':'container compact-page module-directory' ?>">
    <div class="page-heading module-heading">
        <div class="eyebrow">DEINE DIENSTE BEI SERVER MANAGEMENT</div>
        <h1>Ein Ort.<br><span>Alle Möglichkeiten.</span></h1>
        <p>Wähle deinen Dienst und öffne die passende Verwaltung.<br> Deine virtuellen Server verwaltest du direkt hier im Shop.</p>
    </div>
    <?php module_cards(); ?>
    <div class="module-login-note"><?= icon('lock') ?><p>Externe Module öffnen in einem neuen Tab. Verwende dort die Zugangsdaten des jeweiligen Dienstes. Die Anmeldung im Shop gilt für deinen Kundenbereich und die Serververwaltung.</p></div>
</section>
