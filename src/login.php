<?php
/**
 * Server Management Framework
 * 
 * @author Teris
 * @version 3.1.2
 */

require_once __DIR__ . '/sys.conf.php';
require_once __DIR__ . '/../framework.php';
require_once 'auth_handler.php';
require_once 'sys.conf.php';

// Wenn bereits eingeloggt, zur Hauptseite weiterleiten
if (SessionManager::isLoggedIn()) {
    header('Location: ' . (SessionManager::isAdmin() ? 'index.php' : 'account.php'), true, 303);
    exit;
}

$error_message = '';

// Login verarbeiten
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error_message = t('please_login') . '.';
    } else {
        $auth = new AuthenticationHandler();
        $login_result = $auth->login($username, $password);
        
        if ($login_result['success']) {
            // Login erfolgreich, zur Hauptseite weiterleiten
            header('Location: ' . (SessionManager::isAdmin() ? 'index.php' : 'account.php'), true, 303);
            exit;
        } else {
            $error_message = $login_result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('login_title') ?> - <?= t('server_management') ?></title>
	<link rel="stylesheet" type="text/css" href="assets/login.css">
<meta name="csrf-token" content="<?php echo HttpSecurity::csrfToken(); ?>"><script src="assets/security.js"></script></head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🔐 <?= t('login_title') ?></h1>
            <p><?= t('login_subtitle') ?></p>
        </div>
        
        <?php if (!empty($error_message)): ?>
            <div class="error-message">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
        
<form method="POST" id="loginForm"><?php echo HttpSecurity::csrfField(); ?>
            <input type="hidden" name="action" value="login">
            
            <div class="form-group">
                <label for="username">🧑‍💻 <?= t('username') ?></label>
                <input type="text" id="username" name="username" required 
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       placeholder="<?= t('username') ?>">
            </div>
            
            <div class="form-group">
                <label for="password">🔑 <?= t('password') ?></label>
                <input type="password" id="password" name="password" required
                       placeholder="<?= t('password') ?>">
            </div>
            
            <button type="submit" class="login-btn" id="loginBtn">
                <span class="loading hidden" id="loadingSpinner"></span>
                <span id="loginText">🚀 <?= t('login') ?></span>
            </button>
        </form>
        
        <div class="login-footer">
            <p>© 2025 Server Management Framework</p>
            <p>Session-Timeout: <?= (int) (HttpSecurity::timeout() / 60) ?> Minuten bei Inaktivität</p>
        </div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            const spinner = document.getElementById('loadingSpinner');
            const text = document.getElementById('loginText');
            
            btn.disabled = true;
            spinner.classList.remove('hidden');
            text.textContent = 'Anmeldung läuft...';
        });
        
        // Auto-focus auf Username Feld
        document.getElementById('username').focus();
        
        // Enter-Taste Support
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('loginForm').submit();
            }
        });
    </script>
</body>
</html>