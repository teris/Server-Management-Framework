<?php
/**
 * Passwort-Änderungsseite für Kunden
 */

require_once '../src/sys.conf.php';
require_once '../framework.php';
require_once '../src/core/LanguageManager.php';
require_once '../src/core/ActivityLogger.php';

// Sprache setzen
$lang = LanguageManager::getInstance();
$currentLang = $lang->getCurrentLanguage();

// Session starten
session_start();

// Prüfen ob Kunde eingeloggt ist
if (!isset($_SESSION['customer_logged_in']) || $_SESSION['customer_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$customerId = $_SESSION['customer_id'] ?? 0;
$customerName = $_SESSION['customer_name'] ?? '';

$success = '';
$error = '';

// Formularverarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validierung
    if (empty($currentPassword)) {
        $error = 'Bitte geben Sie Ihr aktuelles Passwort ein.';
    } elseif (empty($newPassword)) {
        $error = 'Bitte geben Sie ein neues Passwort ein.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Das neue Passwort muss mindestens 8 Zeichen lang sein.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Die neuen Passwörter stimmen nicht überein.';
    } else {
        try {
            $db = Database::getInstance();
            
            // Aktuelles Passwort überprüfen
            $stmt = $db->prepare("SELECT password_hash FROM customers WHERE id = ? AND status = 'active'");
            $stmt->execute([$customerId]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$customer) {
                $error = 'Kunde nicht gefunden oder inaktiv.';
            } elseif (!password_verify($currentPassword, $customer['password_hash'])) {
                $error = 'Das aktuelle Passwort ist falsch.';
            } else {
                // Neues Passwort hashen und speichern
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("UPDATE customers SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                if ($stmt->execute([$hashedPassword, $customerId])) {
                    $success = 'Ihr Passwort wurde erfolgreich geändert.';
                    
                    // Aktivität loggen
                    try {
                        $activityLogger = ActivityLogger::getInstance();
                        $activityLogger->logCustomerActivity(
                            $customerId, 
                            'password_change', 
                            'Passwort erfolgreich geändert', 
                            $customerId, 
                            'customers'
                        );
                    } catch (Exception $e) {
                        error_log("Activity Logging Error: " . $e->getMessage());
                    }
                    
                    // Remember Me Token löschen (Sicherheitsmaßnahme)
                    if (isset($_COOKIE['remember_token'])) {
                        $stmt = $db->prepare("DELETE FROM customer_remember_tokens WHERE token = ?");
                        $stmt->execute([$_COOKIE['remember_token']]);
                        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
                    }
                } else {
                    $error = 'Fehler beim Ändern des Passworts. Bitte versuchen Sie es erneut.';
                }
            }
        } catch (Exception $e) {
            error_log("Password Change Error: " . $e->getMessage());
            $error = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
        }
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
</head>
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
                            <li><a class="dropdown-item" href="dashboard.php?logout=1">
                                <i class="bi bi-box-arrow-right"></i> <?= t('logout') ?>
                            </a></li>
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
                        
                        <form method="POST" action="change-password.php" id="changePasswordForm">
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
                                           minlength="8" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                        <i class="bi bi-eye" id="new_password_icon"></i>
                                    </button>
                                </div>
                                <div class="form-text">
                                    <small class="text-muted">
                                        <i class="bi bi-info-circle"></i> 
                                        Das Passwort muss mindestens 8 Zeichen lang sein.
                                    </small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label"><?= t('confirm_new_password') ?> *</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           minlength="8" required>
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
                                Verwenden Sie mindestens 8 Zeichen
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-check-circle text-success me-2"></i>
                                Kombinieren Sie Groß- und Kleinbuchstaben
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-check-circle text-success me-2"></i>
                                Fügen Sie Zahlen und Sonderzeichen hinzu
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-check-circle text-success me-2"></i>
                                Vermeiden Sie leicht zu erratende Wörter
                            </li>
                            <li>
                                <i class="bi bi-check-circle text-success me-2"></i>
                                Verwenden Sie für jedes Konto ein einzigartiges Passwort
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
                alert('Die neuen Passwörter stimmen nicht überein.');
                return false;
            }
        });
    </script>
</body>
</html>
