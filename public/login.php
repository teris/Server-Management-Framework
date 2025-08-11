<?php
/**
 * Kundenlogin - Authentifizierung für Kunden
 */

require_once '../src/sys.conf.php';
require_once '../framework.php';
require_once '../src/core/LanguageManager.php';

// Sprache setzen
$lang = LanguageManager::getInstance();
$currentLang = $lang->getCurrentLanguage();

// Übersetzungsfunktion wird von sys.conf.php bereitgestellt

// Session starten
session_start();

// Bereits eingeloggt?
if (isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

// Login verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        $error = 'Bitte füllen Sie alle Felder aus.';
    } else {
        try {
            // Datenbankverbindung
            $db = Database::getInstance();
            $pdo = $db->getConnection();
            
            // Kunde in der Datenbank suchen
            $stmt = $pdo->prepare("SELECT id, email, password_hash, first_name, last_name, status FROM customers WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($customer && password_verify($password, $customer['password_hash'])) {
                // Login erfolgreich
                $_SESSION['customer_logged_in'] = true;
                $_SESSION['customer_id'] = $customer['id'];
                $_SESSION['customer_email'] = $customer['email'];
                $_SESSION['customer_name'] = $customer['first_name'] . ' ' . $customer['last_name'];
                $_SESSION['login_time'] = time();
                
                // Remember Me
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                    
                    $stmt = $pdo->prepare("INSERT INTO customer_remember_tokens (customer_id, token, expires_at) VALUES (?, ?, ?)");
                    $stmt->execute([$customer['id'], $token, $expires]);
                    
                    setcookie('remember_token', $token, strtotime('+30 days'), '/', '', true, true);
                }
                
                // Login-Log
                $stmt = $pdo->prepare("INSERT INTO customer_login_logs (customer_id, ip_address, user_agent, success) VALUES (?, ?, ?, 1)");
                $stmt->execute([$customer['id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '']);
                
                header('Location: dashboard.php');
                exit;
            } else {
                // Login fehlgeschlagen
                $error = 'E-Mail oder Passwort ist falsch.';
                
                // Fehlgeschlagener Login-Log (falls Kunde existiert)
                if ($customer) {
                    $stmt = $pdo->prepare("INSERT INTO customer_login_logs (customer_id, ip_address, user_agent, success) VALUES (?, ?, ?, 0)");
                    $stmt->execute([$customer['id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '']);
                }
            }
        } catch (Exception $e) {
            error_log("Login Error: " . $e->getMessage());
            $error = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
        }
    }
}

// Remember Me Token prüfen
if (empty($error) && empty($_POST) && isset($_COOKIE['remember_token'])) {
    try {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        $stmt = $pdo->prepare("SELECT c.id, c.email, c.first_name, c.last_name, c.status FROM customers c 
                              JOIN customer_remember_tokens crt ON c.id = crt.customer_id 
                              WHERE crt.token = ? AND crt.expires_at > NOW() AND c.status = 'active'");
        $stmt->execute([$_COOKIE['remember_token']]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($customer) {
            $_SESSION['customer_logged_in'] = true;
            $_SESSION['customer_id'] = $customer['id'];
            $_SESSION['customer_email'] = $customer['email'];
            $_SESSION['customer_name'] = $customer['first_name'] . ' ' . $customer['last_name'];
            $_SESSION['login_time'] = time();
            
            header('Location: dashboard.php');
            exit;
        }
    } catch (Exception $e) {
        error_log("Remember Me Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('customer_login') ?> - Server Management</title>
    
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
</head>
<body class="login-page">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-server"></i> Server Management
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">
                    <i class="bi bi-house"></i> Zurück zum Frontpanel
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

                        <form method="POST" action="" id="login-form">
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
