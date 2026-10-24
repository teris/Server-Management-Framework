<?php
/**
 * Server Management Framework
 * 
 * @author Teris
 * @version 3.1.2
 */
/**
 * E-Mail-Bestätigung für Kundenregistrierung
 */

require_once __DIR__ . '/../src/public-bootstrap.php';
require_once '../src/core/LanguageManager.php';
// TODO: E-Mail-Template-System ist jetzt über EmailTemplateManager verfügbar
require_once '../src/core/EmailTemplateManager.php';

// Sprache setzen
$lang = LanguageManager::getInstance();
$currentLang = $lang->getCurrentLanguage();

// Session starten
HttpSecurity::session();

$error = '';
$success = '';
$systemCreationResults = '';
$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
if (!preg_match('/^[a-f0-9]{64}$/D', $token)) $error = 'Ungültiger Bestätigungslink.';
elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if ((new RegistrationService())->verify($token)) $success = 'Ihre E-Mail-Adresse ist bestätigt. Sie können sich jetzt anmelden.';
        else $error = 'Der Link ist abgelaufen, wurde bereits verwendet oder ist nicht mehr gültig. Bitte einen neuen Link anfordern.';
    } catch (Throwable $e) { error_log('Verification failed: ' . get_class($e)); $error = 'Die Bestätigung kann gerade nicht verarbeitet werden.'; }
}

?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('email_verification') ?> - Server Management</title>
    
    <!-- Bootstrap CSS -->
    <link href="assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" type="text/css" href="assets/frontpanel.css">
    <link rel="stylesheet" type="text/css" href="assets/login.css">
    
    <!-- jQuery -->
    <script src="assets/vendor/jquery/jquery.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<meta name="csrf-token" content="<?php echo HttpSecurity::csrfToken(); ?>"><script src="../src/assets/security.js"></script></head>
<body class="login-page">
<?php if (!$error && !$success): ?>
<div class="container py-4"><h1>E-Mail-Adresse bestätigen</h1><p>Mit der Bestätigung aktivieren Sie Ihr Kundenkonto.</p>
<form method="POST"><?php echo HttpSecurity::csrfField(); ?><input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>"><button class="btn btn-primary" type="submit">E-Mail-Adresse bestätigen</button></form></div>
<?php endif; ?>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-server"></i> <?= Config::FRONTPANEL_SITE_NAME ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">
                    <i class="bi bi-house"></i> <?= t('back_to_frontpanel') ?>
                </a>
            </div>
        </div>
    </nav>

    <!-- Verification Section -->
    <div class="login-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-5 col-md-7 col-sm-9">
                    <div class="login-card">
                        <div class="login-header text-center mb-4">
                            <i class="bi bi-envelope-check display-1 text-primary"></i>
                            <h2 class="mt-3"><?= t('email_verification') ?></h2>
                            <p class="text-muted"><?= t('email_verification_required') ?></p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            
                            <?php if ($systemCreationResults): ?>
                                <div class="alert alert-info alert-dismissible fade show" role="alert">
                                    <i class="bi bi-info-circle"></i> <?= htmlspecialchars($systemCreationResults) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <div class="text-center mt-4">
                            <?php if ($success): ?>
                                <a href="login.php" class="btn btn-primary btn-lg">
                                    <i class="bi bi-box-arrow-in-right"></i> <?= t('login') ?>
                                </a>
                            <?php else: ?>
                                <a href="register.php" class="btn btn-outline-primary">
                                    <i class="bi bi-person-plus"></i> <?= t('create_account') ?>
                                </a>
                                <a href="login.php" class="btn btn-outline-secondary ms-2">
                                    <i class="bi bi-box-arrow-in-right"></i> <?= t('login') ?>
                                </a>
                            <?php endif; ?>
                        </div>

                        <div class="login-footer text-center mt-4">
                            <small class="text-muted">
                                <i class="bi bi-shield-check"></i> 
                                <?= t('secure_connection') ?> | 
                                <a href="contact.php" class="text-decoration-none"><?= t('contact') ?></a>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

<?php
// TODO: Alte E-Mail-Funktion entfernt - wird jetzt über EmailTemplateManager::sendCustomerWelcomeEmail() verwendet

// TODO: Alte E-Mail-Funktion entfernt - wird jetzt über EmailTemplateManager::sendSystemCredentialsEmail() verwendet
?>

