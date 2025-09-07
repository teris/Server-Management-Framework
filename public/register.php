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

// Registrierung verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $company = trim($_POST['company'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $acceptTerms = isset($_POST['accept_terms']);
    
    // Validierung
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
        $error = 'Bitte füllen Sie alle Pflichtfelder aus.';
    } elseif (strlen($password) < 8) {
        $error = 'Das Passwort muss mindestens 8 Zeichen lang sein.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Die Passwörter stimmen nicht überein.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
    } elseif (!$acceptTerms) {
        $error = 'Sie müssen die Nutzungsbedingungen akzeptieren.';
    } else {
        try {
            // Datenbankverbindung
            $db = Database::getInstance();
            $pdo = $db->getConnection();
            
            // Prüfen ob E-Mail bereits existiert
            $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $error = 'Diese E-Mail-Adresse ist bereits registriert.';
            } else {
                // Passwort hashen
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                
                // Kunde in Datenbank speichern
                $stmt = $pdo->prepare("
                    INSERT INTO customers (
                        first_name, last_name, email, password_hash, company, phone, 
                        status, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
                ");
                
                $result = $stmt->execute([
                    $firstName, $lastName, $email, $passwordHash, $company, $phone
                ]);
                
                if ($result) {
                    $customerId = $pdo->lastInsertId();
                    
                    // Verifikations-E-Mail senden
                    sendVerificationEmail($email, $firstName, $customerId);
                    
                    // Erfolgs-Nachricht
                    $success = 'Registrierung erfolgreich! Bitte bestätigen Sie Ihre E-Mail-Adresse.';
                    
                    // Formular zurücksetzen
                    $_POST = [];
                } else {
                    $error = 'Registrierung fehlgeschlagen. Bitte versuchen Sie es später erneut.';
                }
            }
        } catch (Exception $e) {
            error_log("Registration Error: " . $e->getMessage());
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
    <title><?= t('customer_registration') ?> - Server Management</title>
    
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
                <a class="nav-link" href="login.php">
                    <i class="bi bi-person-circle"></i> Bereits registriert?
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

                        <form method="POST" action="" id="registration-form">
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
                                        <input type="password" class="form-control form-control-lg" id="password" name="password" 
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
/**
 * Verifikations-E-Mail senden
 */
function sendVerificationEmail($email, $firstName, $customerId) {
    try {
        $verificationToken = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Token in Datenbank speichern
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO customer_verification_tokens (customer_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$customerId, $verificationToken, $expires]);
        
        $to = $email;
        $subject = "E-Mail-Adresse bestätigen - " . Config::FRONTPANEL_SITE_NAME;
        
        $verificationLink = "https://" . $_SERVER['HTTP_HOST'] . "/public/verify.php?token=" . $verificationToken;
        
        $message = "
        <html>
        <head>
            <title>E-Mail-Adresse bestätigen</title>
        </head>
        <body>
            <h2>Willkommen bei " . Config::FRONTPANEL_SITE_NAME . "!</h2>
            <p>Hallo {$firstName},</p>
            <p>vielen Dank für Ihre Registrierung. Um Ihr Konto zu aktivieren, bestätigen Sie bitte Ihre E-Mail-Adresse.</p>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$verificationLink}' 
                   style='background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; display: inline-block;'>
                    E-Mail-Adresse bestätigen
                </a>
            </div>
            
            <p>Falls der Button nicht funktioniert, kopieren Sie diesen Link in Ihren Browser:</p>
            <p style='word-break: break-all; color: #666;'>{$verificationLink}</p>
            
            <p>Dieser Link ist 24 Stunden gültig.</p>
            
            <p>Mit freundlichen Grüßen<br>
            Ihr " . Config::FRONTPANEL_SITE_NAME . " Team</p>
        </body>
        </html>
        ";
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: ' . Config::FRONTPANEL_SYSTEM_EMAIL,
            'Reply-To: ' . Config::FRONTPANEL_SUPPORT_EMAIL,
            'X-Mailer: PHP/' . phpversion()
        ];
        
        mail($to, $subject, $message, implode("\r\n", $headers));
        
    } catch (Exception $e) {
        error_log("Failed to send verification email: " . $e->getMessage());
    }
}
?>
