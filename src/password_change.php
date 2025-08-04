<?php
/**
 * Passwort ändern für eingeloggte Benutzer
 */

require_once '../framework.php';
require_once 'auth_handler.php';
require_once 'sys.conf.php';

// Login-Überprüfung
requireLogin();

$error_message = '';
$success_message = '';

// Passwort-Änderung verarbeiten
if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validierung
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = t('all_fields_required') . '.';
    } elseif (strlen($new_password) < 6) {
        $error_message = t('password_too_short') . '.';
    } elseif ($new_password !== $confirm_password) {
        $error_message = t('passwords_not_match') . '.';
    } elseif ($current_password === $new_password) {
        $error_message = t('new_password_must_differ') . '.';
    } else {
        // Passwort ändern
        $user_info = SessionManager::getUserInfo();
        $auth = new AuthenticationHandler();
        $result = $auth->changePassword($user_info['id'], $current_password, $new_password);
        
        if ($result['success']) {
            $success_message = t('password_changed') . '.';
            
            // Optional: Session beenden und Neuanmeldung erfordern
            // SessionManager::logout();
            // header('Location: login.php?message=password_changed');
            // exit;
        } else {
            $error_message = $result['message'];
        }
    }
}

$user_info = SessionManager::getUserInfo();
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('change_password_title') ?> - <?= t('server_management') ?></title>
    <link rel="stylesheet" type="text/css" href="assets/main.css">
    <style>
        .password-container {
            max-width: 600px;
            margin: 50px auto;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .password-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .password-header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .user-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #f3f4f6;
            padding: 10px 15px;
            border-radius: 25px;
            color: #374151;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .user-avatar {
            width: 30px;
            height: 30px;
            background: #4f46e5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }
        
        .password-requirements {
            background: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
        }
        
        .password-requirements h4 {
            color: #0369a1;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .password-requirements ul {
            color: #0369a1;
            font-size: 13px;
            margin-left: 20px;
        }
        
        .password-strength {
            margin-top: 5px;
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }
        
        .strength-weak { background: #ef4444; width: 25%; }
        .strength-fair { background: #f59e0b; width: 50%; }
        .strength-good { background: #10b981; width: 75%; }
        .strength-strong { background: #059669; width: 100%; }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #4f46e5;
            text-decoration: none;
            margin-bottom: 20px;
            font-size: 14px;
            transition: color 0.3s ease;
        }
        
        .back-link:hover {
            color: #4338ca;
        }
        
        .security-info {
            background: #fef3cd;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 15px;
            margin-top: 25px;
        }
        
        .security-info h4 {
            color: #92400e;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .security-info p {
            color: #92400e;
            font-size: 13px;
            line-height: 1.4;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="password-container">
            <a href="index.php" class="back-link">
                ← <?= t('back') ?> <?= t('dashboard') ?>
            </a>
            
            <div class="password-header">
                <h1>🔐 <?= t('change_password_title') ?></h1>
                
                <div class="user-badge">
                    <div class="user-avatar">
                        <?= strtoupper(substr($user_info['full_name'] ?? $user_info['username'], 0, 1)) ?>
                    </div>
                    <span><?= htmlspecialchars($user_info['full_name'] ?? $user_info['username']) ?></span>
                </div>
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
            
            <div class="password-requirements">
                <h4>🛡️ Passwort-Anforderungen:</h4>
                <ul>
                    <li>Mindestens 6 Zeichen</li>
                    <li>Muss sich vom aktuellen Passwort unterscheiden</li>
                    <li>Empfohlen: Kombination aus Buchstaben, Zahlen und Sonderzeichen</li>
                    <li>Keine persönlichen Informationen verwenden</li>
                </ul>
            </div>
            
            <form method="POST" id="passwordForm">
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label for="current_password">🔑 Aktuelles Passwort</label>
                    <input type="password" id="current_password" name="current_password" required
                           placeholder="Ihr aktuelles Passwort">
                </div>
                
                <div class="form-group">
                    <label for="new_password">🆕 Neues Passwort</label>
                    <input type="password" id="new_password" name="new_password" required
                           minlength="6" placeholder="Neues Passwort (mindestens 6 Zeichen)">
                    <div class="password-strength">
                        <div class="password-strength-bar" id="strengthBar"></div>
                    </div>
                    <small id="strengthText" style="color: #6b7280; font-size: 12px;"></small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">🔒 Neues Passwort bestätigen</label>
                    <input type="password" id="confirm_password" name="confirm_password" required
                           minlength="6" placeholder="Neues Passwort wiederholen">
                </div>
                
                <button type="submit" class="btn" id="changeBtn">
                    <span class="loading hidden" id="loadingSpinner"></span>
                    🔄 Passwort ändern
                </button>
            </form>
            
            <div class="security-info">
                <h4>🔒 Sicherheitshinweise:</h4>
                <p>Nach der Passwort-Änderung bleibt Ihre aktuelle Sitzung bestehen. 
                   Bei verdächtigen Aktivitäten empfiehlt es sich, sich ab- und wieder anzumelden.
                   Verwenden Sie niemals das gleiche Passwort für mehrere Dienste.</p>
            </div>
        </div>
    </div>

    <script>
        // Passwort-Stärke-Prüfung
        function checkPasswordStrength(password) {
            let strength = 0;
            let feedback = '';
            
            if (password.length >= 6) strength += 1;
            if (password.length >= 10) strength += 1;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength += 1;
            if (/\d/.test(password)) strength += 1;
            if (/[^a-zA-Z\d]/.test(password)) strength += 1;
            
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            
            strengthBar.className = 'password-strength-bar';
            
            if (strength <= 1) {
                strengthBar.classList.add('strength-weak');
                feedback = 'Schwach';
            } else if (strength <= 2) {
                strengthBar.classList.add('strength-fair');
                feedback = 'Ausreichend';
            } else if (strength <= 3) {
                strengthBar.classList.add('strength-good');
                feedback = 'Gut';
            } else {
                strengthBar.classList.add('strength-strong');
                feedback = 'Stark';
            }
            
            strengthText.textContent = feedback;
        }
        
        // Passwort-Match-Validierung
        function checkPasswordMatch() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const confirmField = document.getElementById('confirm_password');
            
            if (confirmPassword && newPassword !== confirmPassword) {
                confirmField.style.borderColor = '#dc2626';
                confirmField.style.boxShadow = '0 0 0 3px rgba(220, 38, 38, 0.1)';
            } else if (confirmPassword) {
                confirmField.style.borderColor = '#10b981';
                confirmField.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.1)';
            } else {
                confirmField.style.borderColor = '#e5e7eb';
                confirmField.style.boxShadow = 'none';
            }
        }
        
        // Event Listeners
        document.getElementById('new_password').addEventListener('input', function() {
            checkPasswordStrength(this.value);
            checkPasswordMatch();
        });
        
        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);
        
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const currentPassword = document.getElementById('current_password').value;
            
            if (newPassword !== confirmPassword) {
                alert('Neue Passwörter stimmen nicht überein!');
                e.preventDefault();
                return false;
            }
            
            if (newPassword === currentPassword) {
                alert('Neues Passwort muss sich vom aktuellen unterscheiden!');
                e.preventDefault();
                return false;
            }
            
            if (newPassword.length < 6) {
                alert('Neues Passwort muss mindestens 6 Zeichen lang sein!');
                e.preventDefault();
                return false;
            }
            
            const btn = document.getElementById('changeBtn');
            const spinner = document.getElementById('loadingSpinner');
            
            btn.disabled = true;
            spinner.classList.remove('hidden');
            btn.innerHTML = '<span class="loading"></span> Passwort wird geändert...';
        });
        
        // Auto-focus
        document.getElementById('current_password').focus();
        
        // Auto-redirect bei erfolgreichem Passwort-Wechsel
        <?php if (!empty($success_message)): ?>
        setTimeout(() => {
            if (confirm('Passwort erfolgreich geändert! Möchten Sie zum Dashboard zurückkehren?')) {
                window.location.href = 'index.php';
            }
        }, 2000);
        <?php endif; ?>
    </script>
</body>
</html>