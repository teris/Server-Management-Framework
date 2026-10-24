<?php $deletion=account_deletion_request((int)$customer['id']);$deletionPending=($deletion['state']??'')==='pending';$blockers=account_deletion_blockers((int)$customer['id']); ?>
<section class="panel form-panel account-deletion-settings" id="konto-loeschen" aria-labelledby="account-deletion-heading">
    <div class="eyebrow">DEIN KONTO</div>
    <h2 id="account-deletion-heading"><?= $deletionPending?'Kontolöschung vorgemerkt':'Kundenkonto löschen' ?></h2>
    <?php if($deletionPending): ?>
        <dl class="detail-list"><div><dt>Antrag gestellt</dt><dd><?= date('d.m.Y H:i',(int)$deletion['requested_ts']) ?> Uhr</dd></div><div><dt>Früheste Löschung</dt><dd><?= date('d.m.Y H:i',(int)$deletion['undo_until_ts']) ?> Uhr <small>(deutsche Ortszeit)</small></dd></div></dl>
        <p>Dein Antrag bleibt vorgemerkt. Nach der 24-stündigen Bedenkzeit wird dein Konto automatisch gelöscht, sobald alle Verträge tatsächlich beendet und deren Abschaltung und Abrechnung beim Anbieter abgeschlossen sind.</p>
    <?php else: ?>
        <p>Du kannst die Löschung deines Kundenkontos hier vormerken. Danach hast du <strong>mindestens 24 Stunden Zeit</strong>, den Antrag zurückzunehmen.</p>
    <?php endif ?>
    <?php if($blockers['blocked']): ?>
        <div class="account-deletion-status" role="status"><strong>Die endgültige Löschung ist noch gesperrt.</strong><p>Bitte beende zuerst alle laufenden Verträge. Auch bereits gekündigte und ausgeblendete Pakete zählen bis zum tatsächlichen Vertragsende weiter. Dein Löschantrag kann bis dahin vorgemerkt bleiben.</p><p><a class="text-link" href="/konto/vertraege">Verträge verwalten</a> · <a class="text-link" href="/kuendigen">Vertrag kündigen</a> · <a class="text-link" href="/konto/support">Support kontaktieren</a></p></div>
    <?php endif ?>
    <p>Der Löschantrag kündigt keine Verträge. Dein Zugang bleibt während der Vormerkung nutzbar; neue Pakete und Module kannst du erst nach Rücknahme des Antrags bestellen.</p>
    <p>Bei der Löschung werden dein Shop-Profil, dein Login, persönliche Einstellungen, Supportnachrichten und im Shop gespeicherte Zugangsdaten entfernt. Rechnungen sowie Zahlungs- und Vertragsnachweise bleiben für die erforderliche Aufbewahrung getrennt erhalten. <a class="text-link" href="/konto/rechnungen">Rechnungen vorher herunterladen</a>.</p>
    <?php if($deletionPending): ?>
        <p>Solange die Löschung noch aussteht, kannst du sie hier zurücknehmen – auch nach den ersten 24 Stunden, wenn noch Verträge laufen.</p>
        <form method="post"><?= csrf() ?><input type="hidden" name="action" value="account_delete_cancel"><button class="button button-teal" type="submit">Löschung zurücknehmen <?= icon('arrow') ?></button></form>
    <?php else: ?>
        <details class="account-deletion-confirm" <?= ($_POST['action']??'')==='account_delete_request'?'open':'' ?>><summary>Löschung des Kundenkontos vormerken</summary>
            <form method="post"><?= csrf() ?><input type="hidden" name="action" value="account_delete_request">
                <?php password_input('deletion_password','Aktuelles Passwort','current-password'); ?>
                <label class="checkbox-field"><input type="checkbox" name="deletion_confirmed" value="1" required><span>Ich möchte mein Kundenkonto nach mindestens 24 Stunden und dem tatsächlichen Ende aller Verträge löschen lassen. Ich weiß, dass ich meine Verträge separat kündigen muss.</span></label>
                <button class="button button-outline" type="submit">Kontolöschung verbindlich vormerken</button>
            </form>
        </details>
    <?php endif ?>
</section>
