<?php
require_once __DIR__ . '/../src/public-bootstrap.php';
$lang = LanguageManager::getInstance(); $currentLang = $lang->getCurrentLanguage();
$error = ''; $success = '';
if (Identity::current('customer')) { header('Location: dashboard.php', true, 303); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (CustomerAuthentication::login(trim((string) ($_POST['email'] ?? '')), $_POST['password'] ?? '', filter_var($_POST['remember'] ?? false, FILTER_VALIDATE_BOOLEAN))) {
        header('Location: dashboard.php', true, 303); exit;
    }
    $error = t('email_or_password_is_incorrect');
} elseif (isset($_COOKIE['remember_token']) && CustomerAuthentication::remember($_COOKIE['remember_token'])) {
    header('Location: dashboard.php', true, 303); exit;
}
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('customer_login') ?> - Server Management</title>
    
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
            </div>
        </div>
    </nav>

    <!-- Login Section -->
    <div class="login-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-5 col-md-7 col-sm-9">
                    <div class="login-card">
                        <div class="login-header text-center mb-4">
                            <i class="bi bi-person-circle display-1 text-primary"></i>
                            <h2 class="mt-3"><?= t('customer_login') ?></h2>
                            <p class="text-muted"><?= t('login_description') ?></p>
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

                        <form method="POST" action="" id="login-form"><?php echo HttpSecurity::csrfField(); ?>
                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    <i class="bi bi-envelope"></i> <?= t('email') ?>
                                </label>
                                <input type="email" class="form-control form-control-lg" id="email" name="email" 
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required 
                                       placeholder="<?= t('enter_email') ?>">
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    <i class="bi bi-lock"></i> <?= t('password') ?>
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control form-control-lg" id="password" name="password" 
                                           required placeholder="<?= t('enter_password') ?>">
                                    <button class="btn btn-outline-secondary" type="button" id="toggle-password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label" for="remember">
                                    <?= t('remember_me') ?>
                                </label>
                            </div>

                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-lg" id="login-btn">
                                    <i class="bi bi-box-arrow-in-right"></i> <?= t('login') ?>
                                </button>
                            </div>

                            <div class="text-center">
                                <a href="forgot-password.php" class="text-decoration-none">
                                    <?= t('forgot_password') ?>?
                                </a>
                            </div>
                        </form>

                        <hr class="my-4">

                        <div class="text-center">
                            <p class="text-muted mb-2"><?= t('no_account') ?>?</p>
                            <a href="register.php" class="btn btn-outline-primary">
                                <i class="bi bi-person-plus"></i> <?= t('create_account') ?>
                            </a>
                        </div>

                        <div class="login-footer text-center mt-4">
                            <small class="text-muted">
                                <i class="bi bi-shield-check"></i> 
                                <?= t('secure_connection') ?> | 
                                <a href="privacy.php" class="text-decoration-none"><?= t('privacy_policy') ?></a>
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

    <script src="assets/login.js"></script>
</body>
</html>
