<?php if ($portal): ?>
        <footer class="portal-footer"><span>© <?= date('Y') ?> <?= e(SMF\Core\ShopSettings::get('site_name')) ?></span><div><a href="/faq">FAQ & Anleitungen</a><a href="/hilfe">Hilfe & Kontakt</a><a href="/kuendigen">Verträge hier kündigen</a><?php $footerDocs=checkout_documents();foreach(['terms_url'=>'AGB','withdrawal_url'=>'Widerruf'] as $docKey=>$docName):if(empty($footerDocs[$docKey]))continue; ?><a href="<?= e($footerDocs[$docKey]) ?>"><?= $docName ?></a><?php endforeach ?></div></footer>
        </main>
    </div>
</div>
<?php else: ?>
</main>
<footer class="site-footer"><div class="container">
    <div class="footer-top"><div><a class="brand" href="/" aria-label="Server Management Startseite"><?= shop_brand() ?><span class="brand-context">HOSTING & IT-LÖSUNGEN</span></a><p>Für deine Ideen.<br>Und die Technik dahinter.</p></div><div><strong>Entdecken</strong><a href="/konfigurator">Hosting-Konfigurator</a><a href="/produkte?kategorie=hosting">Webhosting</a><a href="/produkte?kategorie=server">Virtuelle Server</a><a href="/produkte?kategorie=domain">Domains</a><a href="/produkte?kategorie=managed">Managed Services</a></div><div><strong>Für dich da</strong><a href="/konto">Kundenbereich</a><a href="/module">Module & Direktzugänge</a><a href="/faq">FAQ & Anleitungen</a><a href="/hilfe">Hilfe & Kontakt</a><a href="/kuendigen">Verträge hier kündigen</a><?php $footerDocs=checkout_documents();foreach(['terms_url'=>'AGB','withdrawal_url'=>'Widerruf'] as $docKey=>$docName):if(empty($footerDocs[$docKey]))continue; ?><a href="<?= e($footerDocs[$docKey]) ?>"><?= $docName ?></a><?php endforeach ?><a href="/konto/support">Support-Ticket erstellen</a><a href="/">Über Server Management <?= icon('external') ?></a></div><div class="footer-contact"><strong>Lass uns über dein Projekt sprechen.</strong><a href="mailto:<?= e(SMF\Core\ShopSettings::get('support_email')) ?>"><?= e(SMF\Core\ShopSettings::get('support_email')) ?> <?= icon('arrow') ?></a><span>Individuelle Lösungen.<br>Ein persönlicher Ansprechpartner.</span></div></div>
    <div class="footer-bottom"><span>© <?= date('Y') ?> <?= e(SMF\Core\ShopSettings::get('site_name')) ?> · </span><div><span>Mit Sorgfalt gemacht.</span></div></div>
</div></footer>
<?php endif ?>
</body>
</html>
