<?php
/**
 * Server Management Framework
 * 
 * @author Teris
 * @version 3.1.2
 */
/**
 * Kundenregistrierung - Neue Kunden können sich hier registrieren
 */

require_once __DIR__ . '/../src/public-bootstrap.php';
require_once '../src/core/LanguageManager.php';
// TODO: E-Mail-Template-System ist jetzt über EmailTemplateManager verfügbar
require_once '../src/core/EmailTemplateManager.php';

// Sprache setzen
$lang = LanguageManager::getInstance();
$currentLang = $lang->getCurrentLanguage();

// Übersetzungsfunktion wird von sys.conf.php bereitgestellt

// Session starten
HttpSecurity::session();

// Bereits eingeloggt?
if (Identity::current('customer')) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Registration writes local state only; delivery and activation are retryable.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $result = (new RegistrationService())->register($_POST);
        if ($result['success']) { $success = $result['message']; $_POST = []; }
        else $error = $result['message'];
    } catch (Throwable $e) { error_log('Registration failed: ' . get_class($e)); $error = 'Die Registrierung kann gerade nicht verarbeitet werden. Bitte später erneut versuchen.'; }
}

?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('customer_registration') ?> - Server Management</title>
    
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
                <a class="nav-link" href="login.php">
                    <i class="bi bi-person-circle"></i> <?= t('already_registered') ?>
                </a>
            </div>
        </div>
    </nav>

    <!-- Registration Section -->
    <div class="login-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6 col-md-8 col-sm-10">
                    <div class="login-card">
                        <div class="login-header text-center mb-4">
                            <i class="bi bi-person-plus display-1 text-primary"></i>
                            <h2 class="mt-3"><?= t('customer_registration') ?></h2>
                            <p class="text-muted"><?= t('registration_description') ?></p>
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
                        <?php endif; ?>

                        <p><a href="resend-verification.php">Bestätigungslink erneut anfordern</a></p>
                        <form method="POST" action="" id="registration-form"><?php echo HttpSecurity::csrfField(); ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">
                                        <i class="bi bi-person"></i> <?= t('first_name') ?> *
                                    </label>
                                    <input type="text" class="form-control form-control-lg" id="first_name" name="first_name" 
                                           value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required 
                                           placeholder="<?= t('enter_first_name') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">
                                        <i class="bi bi-person"></i> <?= t('last_name') ?> *
                                    </label>
                                    <input type="text" class="form-control form-control-lg" id="last_name" name="last_name" 
                                           value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required 
                                           placeholder="<?= t('enter_last_name') ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    <i class="bi bi-envelope"></i> <?= t('email') ?> *
                                </label>
                                <input type="email" class="form-control form-control-lg" id="email" name="email" 
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required 
                                       placeholder="<?= t('enter_email') ?>">
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="password" class="form-label">
                                        <i class="bi bi-lock"></i> <?= t('password') ?> *
                                    </label>
                                    <div class="input-group">
                                        <input type="password" class="form-control form-control-lg" id="password" name="password" minlength="12" maxlength="4096"
                                               required placeholder="<?= t('enter_password') ?>">
                                        <button class="btn btn-outline-secondary" type="button" id="toggle-password">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">
                                        <i class="bi bi-info-circle"></i> <?= t('password_requirements') ?>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">
                                        <i class="bi bi-lock"></i> <?= t('confirm_password') ?> *
                                    </label>
                                    <input type="password" class="form-control form-control-lg" id="confirm_password" name="confirm_password" 
                                           required placeholder="<?= t('confirm_password') ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="company" class="form-label">
                                        <i class="bi bi-building"></i> <?= t('company') ?>
                                    </label>
                                    <input type="text" class="form-control form-control-lg" id="company" name="company" 
                                           value="<?= htmlspecialchars($_POST['company'] ?? '') ?>" 
                                           placeholder="<?= t('enter_company') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">
                                        <i class="bi bi-telephone"></i> <?= t('phone') ?>
                                    </label>
                                    <input type="tel" class="form-control form-control-lg" id="phone" name="phone" 
                                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" 
                                           placeholder="<?= t('enter_phone') ?>">
                                </div>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="accept_terms" name="accept_terms" required>
                                <label class="form-check-label" for="accept_terms">
                                    <?= t('accept_terms_text') ?> 
                                    <a href="terms.php" target="_blank"><?= t('terms_of_service') ?></a> 
                                    <?= t('and') ?> 
                                    <a href="privacy.php" target="_blank"><?= t('privacy_policy') ?></a>
                                </label>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="newsletter" name="newsletter">
                                <label class="form-check-label" for="newsletter">
                                    <?= t('newsletter_subscription') ?>
                                </label>
                            </div>

                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-lg" id="register-btn">
                                    <i class="bi bi-person-plus"></i> <?= t('create_account') ?>
                                </button>
                            </div>
                        </form>

                        <hr class="my-4">

                        <div class="text-center">
                            <p class="text-muted mb-2"><?= t('already_have_account') ?>?</p>
                            <a href="login.php" class="btn btn-outline-primary">
                                <i class="bi bi-box-arrow-in-right"></i> <?= t('login_here') ?>
                            </a>
                        </div>

                        <div class="login-footer text-center mt-4">
                            <small class="text-muted">
                                <i class="bi bi-shield-check"></i> 
                                <?= t('secure_registration') ?> | 
                                <a href="contact.php" class="text-decoration-none"><?= t('need_help') ?></a>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden"><?= t('loading') ?>...</span>
        </div>
    </div>

    <script src="assets/register.js"></script>
</body>
</html>

<?php
// TODO: Alte E-Mail-Funktion entfernt - wird jetzt über EmailTemplateManager::sendCustomerVerificationEmail() verwendet
?>
