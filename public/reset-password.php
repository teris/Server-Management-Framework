<?php
require_once __DIR__ . '/../src/public-bootstrap.php';
require_once __DIR__ . '/../src/core/PasswordReset.php';
$db = HttpSecurity::connection(); $service = new PasswordReset($db, new EmailTemplateManager($db));
$token = (string) ($_POST['token'] ?? $_GET['token'] ?? ''); $message = ''; $error = ''; $done = false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if ($token !== '') {
            if (($_POST['password'] ?? '') !== ($_POST['confirm_password'] ?? '') || !$service->complete($token, (string) ($_POST['password'] ?? ''))) $error = 'Passwortbestätigung prüfen. Der Link muss gültig und unbenutzt sein; das Passwort benötigt mindestens zwölf Zeichen.';
            else { $message = 'Passwort geändert. Bitte erneut anmelden.'; $done = true; }
        } else {
            $service->request(($_POST['realm'] ?? '') === 'backend' ? 'backend' : 'customer', (string) ($_POST['email'] ?? ''));
            $message = 'Falls ein aktives Konto vorhanden ist, wurde ein Rücksetzlink angefordert. Falls keine Nachricht ankommt, versuchen Sie es später erneut.';
        }
    } catch (Throwable $e) { error_log('Password reset failed: ' . get_class($e)); $message = 'Falls ein aktives Konto vorhanden ist, wurde ein Rücksetzlink angefordert. Falls keine Nachricht ankommt, versuchen Sie es später erneut.'; }
}
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Passwort zurücksetzen</title><link rel="stylesheet" href="assets/account-forms.css"></head><body><main><h1>Passwort zurücksetzen</h1><?php if ($message): ?><p role="status"><?= htmlspecialchars($message) ?></p><?php endif; ?><?php if ($error): ?><p role="alert"><?= htmlspecialchars($error) ?></p><?php endif; ?><?php if (!$done): ?><form method="post"><?= HttpSecurity::csrfField() ?><?php if ($token !== ''): ?><input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>"><label for="password">Neues Passwort</label><input id="password" name="password" type="password" minlength="12" maxlength="4096" autocomplete="new-password" required><label for="confirm">Passwort wiederholen</label><input id="confirm" name="confirm_password" type="password" minlength="12" maxlength="4096" autocomplete="new-password" required><?php else: ?><label for="email">E-Mail-Adresse</label><input id="email" name="email" type="email" autocomplete="email" maxlength="254" required><label for="realm">Bereich</label><select id="realm" name="realm"><option value="customer">Kundenportal</option><option value="backend">Verwaltung</option></select><?php endif; ?><button type="submit"><?= $token !== '' ? 'Passwort ändern' : 'Rücksetzlink anfordern' ?></button></form><?php endif; ?><p><a href="login.php">Kundenanmeldung</a> · <a href="../src/login.php">Verwaltungsanmeldung</a></p></main></body></html>
