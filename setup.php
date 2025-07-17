<?php
/**
 * Setup-Script für Server Management Interface
 * Erstellt den ersten Admin-Benutzer und initialisiert das System
 */

require_once 'framework.php';
require_once 'auth_handler.php';
require_once 'sys.conf.php';

// Prüfen ob bereits ein Admin-User existiert
function hasAdminUser() {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 'y'");
        $stmt->execute();
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Datenbank-Tabellen prüfen und erstellen falls nötig
function checkAndCreateTables() {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Prüfen ob users Tabelle existiert
        $stmt = $db->query("SHOW TABLES LIKE 'users'");
        if ($stmt->rowCount() == 0) {
            // Users Tabelle erstellen
            $db->exec("
                CREATE TABLE users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(100) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    full_name VARCHAR(255),
                    role ENUM('admin', 'user', 'readonly') DEFAULT 'user',
                    active ENUM('y', 'n') DEFAULT 'y',
                    last_login TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_username (username),
                    UNIQUE KEY unique_email (email),
                    INDEX idx_role (role),
                    INDEX idx_active (active)
                )
            ");
            return true;
        }
        return false;
    } catch (Exception $e) {
        throw new Exception("Fehler beim Erstellen der Tabellen: " . $e->getMessage());
    }
}

$error_message = '';
$success_message = '';
$setup_complete = false;

// Wenn bereits ein Admin existiert, zur Login-Seite weiterleiten
if (hasAdminUser()) {
    header('Location: login.php');
    exit;
}

// Setup verarbeiten
if (isset($_POST['action']) && $_POST['action'] === 'setup') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    
    // Validierung
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error_message = t('all_fields_required') . '.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = t('invalid_email') . '.';
    } elseif (strlen($password) < 6) {
        $error_message = t('password_too_short') . '.';
    } elseif ($password !== $confirm_password) {
        $error_message = t('passwords_not_match') . '.';
    } else {
        try {
            // Tabellen erstellen falls nötig
            $tablesCreated = checkAndCreateTables();
            
            // Admin-User erstellen
            $auth = new AuthenticationHandler();
            $result = $auth->createUser($username, $email, $password, $full_name, 'admin');
            
            if ($result['success']) {
                $success_message = t('setup_complete') . ' ' . t('setup_complete_message');
                $setup_complete = true;
                
                // Log-Eintrag für Setup
                $db = Database::getInstance();
                $db->logAction(
                    'System Setup', 
                    "Admin user '{$username}' created during initial setup", 
                    'success'
                );
            } else {
                $error_message = $result['message'];
            }
        } catch (Exception $e) {
            $error_message = t('setup_error') . ': ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('system_setup') ?> - <?= t('server_management') ?></title>
    <link rel="stylesheet" type="text/css" href="assets/setup.css">
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <h1>🚀 <?= t('system_setup') ?></h1>
            <p><?= t('welcome_setup') ?><br>
               <?= t('create_admin_account') ?></p>
        </div>
        
        <?php if (!empty($error_message)): ?>
            <div class="error-message">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="success-message">
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!$setup_complete): ?>
            <div class="password-requirements">
                <h4>📋 <?= t('requirements') ?>:</h4>
                <ul>
                    <li><?= t('all_fields_required') ?></li>
                    <li><?= t('password_min_length') ?></li>
                    <li><?= t('valid_email_required') ?></li>
                    <li><?= t('unique_username_required') ?></li>
                </ul>
            </div>
            
            <form method="POST" id="setupForm">
                <input type="hidden" name="action" value="setup">
                
                <div class="form-group">
                    <label for="full_name">👤 <?= t('full_name') ?></label>
                    <input type="text" id="full_name" name="full_name" required 
                           value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                           placeholder="Max Mustermann">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">🧑‍💻 <?= t('username') ?></label>
                        <input type="text" id="username" name="username" required 
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               placeholder="admin">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">📧 <?= t('email') ?></label>
                        <input type="email" id="email" name="email" required 
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               placeholder="admin@example.com">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">🔑 <?= t('password') ?></label>
                        <input type="password" id="password" name="password" required
                               minlength="6" placeholder="Mindestens 6 Zeichen">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">🔒 <?= t('confirm_password') ?></label>
                        <input type="password" id="confirm_password" name="confirm_password" required
                               minlength="6" placeholder="<?= t('password_confirm_placeholder') ?>">
                    </div>
                </div>
                
                <button type="submit" class="setup-btn" id="setupBtn">
                    <span class="loading hidden" id="loadingSpinner"></span>
                    <span id="setupText">🎯 <?= t('create_admin_user') ?></span>
                </button>
            </form>
        <?php else: ?>
            <a href="login.php" class="continue-btn">
                🚪 <?= t('go_to_login') ?>
            </a>
        <?php endif; ?>
        
        <div class="setup-footer">
            <p>© 2025 Server Management Framework</p>
            <p>Nach dem Setup können Sie weitere Benutzer anlegen</p>
        </div>
    </div>

    <script>
        document.getElementById('setupForm')?.addEventListener('submit', function() {
            const btn = document.getElementById('setupBtn');
            const spinner = document.getElementById('loadingSpinner');
            const text = document.getElementById('setupText');
            
            // Passwort-Validierung
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                alert('Passwörter stimmen nicht überein!');
                return false;
            }
            
            if (password.length < 6) {
                alert('Passwort muss mindestens 6 Zeichen lang sein!');
                return false;
            }
            
            btn.disabled = true;
            spinner.classList.remove('hidden');
            text.textContent = 'Setup läuft...';
        });
        
        // Auto-focus auf ersten Input
        document.getElementById('full_name')?.focus();
        
        // Passwort-Match-Indikator
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.style.borderColor = '#dc2626';
            } else {
                this.style.borderColor = '#e5e7eb';
            }
        });
        
        // Auto-redirect nach erfolgreichem Setup
        <?php if ($setup_complete): ?>
        setTimeout(() => {
            window.location.href = 'login.php';
        }, 3000);
        <?php endif; ?>
    </script>
</body>
</html>