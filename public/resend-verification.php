<?php
require_once __DIR__ . '/../src/public-bootstrap.php';
$message = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try { (new RegistrationService())->resend((string) ($_POST['email'] ?? '')); }
    catch (Throwable $e) { error_log('Verification resend failed: ' . get_class($e)); }
    $message = 'Falls ein unbestätigtes Konto vorhanden ist, wurde der Versand angestoßen. Falls keine Nachricht ankommt, versuchen Sie es später erneut oder kontaktieren Sie den Support.';
}
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Bestätigungslink erneut anfordern</title><link rel="stylesheet" href="assets/account-forms.css"></head><body><main><h1>Bestätigungslink erneut anfordern</h1><?php if ($message): ?><p role="status"><?= htmlspecialchars($message) ?></p><?php endif; ?><form method="post"><?= HttpSecurity::csrfField() ?><label for="email">E-Mail-Adresse</label><input id="email" name="email" type="email" maxlength="254" autocomplete="email" required><button type="submit">Link anfordern</button></form><p><a href="login.php">Zur Anmeldung</a></p></main></body></html>
