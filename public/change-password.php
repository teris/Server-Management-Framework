<?php
require_once __DIR__ . '/../src/public-bootstrap.php';
$lang = LanguageManager::getInstance(); $currentLang = $lang->getCurrentLanguage();
$identity = Identity::requireIdentity('customer');
$customerId = $identity['id']; $customerName = $_SESSION['customer_name'];
$success = ''; $error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (($_POST['new_password'] ?? '') !== ($_POST['confirm_password'] ?? '')) $error = t('new_passwords_do_not_match');
    else {
        $result = AccountPasswords::change('customer', $identity, $_POST['current_password'] ?? '', $_POST['new_password'] ?? '');
        if ($result['success']) $success = $result['message']; else $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('change_password') ?> - Server Management</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" type="text/css" href="assets/frontpanel.css">
    <link rel="stylesheet" type="text/css" href="assets/login.css">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<meta name="csrf-token" content="<?php echo HttpSecurity::csrfToken(); ?>"><script src="../src/assets/security.js"></script></head>
<body class="dashboard-page">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-server"></i> Server Management
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-speedometer2"></i> <?= t('dashboard') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="bi bi-person-circle"></i> <?= t('profile') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="support.php">
                            <i class="bi bi-headset"></i> <?= t('support_tickets') ?>
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($customerName) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">
                                <i class="bi bi-person"></i> <?= t('profile') ?>
                            </a></li>
                            <li><a class="dropdown-item" href="change-password.php">
                                <i class="bi bi-key"></i> <?= t('change_password') ?>
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><form method="post" action="dashboard.php" class="d-inline"><?= HttpSecurity::csrfField() ?><input type="hidden" name="action" value="logout"><button type="submit" class="dropdown-item" style="background:none;border:0">
                                <i class="bi bi-box-arrow-right"></i> <?= t('logout') ?>
                            </button></form></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Change Password Content -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-lg-6 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title mb-0">
                            <i class="bi bi-key text-warning"></i> 
                            <?= t('change_password') ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="change-password.php" id="changePasswordForm"><?php echo HttpSecurity::csrfField(); ?>
                            <div class="mb-3">
                                <label for="current_password" class="form-label"><?= t('current_password') ?> *</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                        <i class="bi bi-eye" id="current_password_icon"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label"><?= t('new_password') ?> *</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="new_password" name="new_password" 
                                           minlength="12" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                        <i class="bi bi-eye" id="new_password_icon"></i>
                                    </button>
                                </div>
                                <div class="form-text">
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle"></i> 
                                        <?= t('password_must_be_at_least_8_characters_long') ?>
                                    </small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label"><?= t('confirm_new_password') ?> *</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           minlength="12" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                        <i class="bi bi-eye" id="confirm_password_icon"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="dashboard.php" class="btn btn-secondary me-md-2">
                                    <i class="bi bi-arrow-left"></i> <?= t('back') ?>
                                </a>
                                <button type="submit" class="btn btn-warning">
                                    <i class="bi bi-check-lg"></i> <?= t('change_password') ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Password Security Tips -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-shield-check text-success"></i> 
                            <?= t('password_security_tips') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="bi bi-check-circle text-success me-2"></i>
                                <?= t('use_at_least_8_characters') ?>
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-check-circle text-success me-2"></i>
                                <?= t('combine_uppercase_and_lowercase_letters') ?>
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-check-circle text-success me-2"></i>
                                <?= t('add_numbers_and_special_characters') ?>
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-check-circle text-success me-2"></i>
                                <?= t('avoid_easily_guessable_words') ?>
                            </li>
                            <li>
                                <i class="bi bi-check-circle text-success me-2"></i>
                                <?= t('use_unique_password_for_each_account') ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-light mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; <?= date('Y') ?> <?= Config::FRONTPANEL_SITE_NAME ?>. <?= t('all_rights_reserved') ?></p>
                </div>
                <div class="col-md-6 text-end">
                    <a href="privacy.php" class="text-decoration-none me-3"><?= t('privacy_policy') ?></a>
                    <a href="terms.php" class="text-decoration-none me-3"><?= t('terms_of_service') ?></a>
                    <a href="contact.php" class="text-decoration-none"><?= t('contact') ?></a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Passwort-Sichtbarkeit umschalten
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '_icon');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
        
        // Passwort-Bestätigung validieren
        document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert(<?= t('new_passwords_do_not_match') ?>);
                return false;
            }
        });
    </script>
</body>
</html>
