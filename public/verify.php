<?php
/**
 * E-Mail-Bestätigung für Kundenregistrierung
 */

require_once '../src/sys.conf.php';
require_once '../framework.php';
require_once '../src/core/LanguageManager.php';

// Sprache setzen
$lang = LanguageManager::getInstance();
$currentLang = $lang->getCurrentLanguage();

// Session starten
session_start();

$error = '';
$success = '';

// Token aus URL-Parameter holen
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $error = 'Ungültiger Bestätigungslink.';
} else {
    try {
        // Datenbankverbindung
        $db = Database::getInstance();
        
        // Token in der Datenbank suchen
        $stmt = $db->prepare("
            SELECT cv.id, cv.customer_id, cv.expires_at, c.email, c.first_name, c.status 
            FROM customer_verification_tokens cv 
            JOIN customers c ON cv.customer_id = c.id 
            WHERE cv.token = ? AND cv.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $verification = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$verification) {
            $error = 'Ungültiger oder abgelaufener Bestätigungstoken.';
        } elseif ($verification['status'] === 'active') {
            $error = 'Ihr Konto ist bereits aktiviert.';
        } else {
            // Konto aktivieren
            $stmt = $db->prepare("
                UPDATE customers 
                SET status = 'active', email_verified_at = NOW(), updated_at = NOW() 
                WHERE id = ?
            ");
            $result = $stmt->execute([$verification['customer_id']]);
            
            if ($result) {
                // Token löschen
                $stmt = $db->prepare("DELETE FROM customer_verification_tokens WHERE id = ?");
                $stmt->execute([$verification['id']]);
                
                $success = 'Ihr Konto wurde erfolgreich aktiviert! Sie können sich jetzt anmelden.';
                
                // Aktivierungs-E-Mail senden
                sendActivationEmail($verification['email'], $verification['first_name']);
            } else {
                $error = 'Fehler bei der Kontoaktivierung. Bitte versuchen Sie es später erneut.';
            }
        }
    } catch (Exception $e) {
        error_log("Email Verification Error: " . $e->getMessage());
        $error = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('email_verification') ?> - Server Management</title>
    
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
                                <a href="contact.php" class="text-decoration-none"><?= t('need_help') ?></a>
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
/**
 * Aktivierungs-E-Mail senden
 */
function sendActivationEmail($email, $firstName) {
    try {
        $to = $email;
        $subject = "Konto aktiviert - " . Config::FRONTPANEL_SITE_NAME;
        
        $message = "
        <html>
        <head>
            <title>Konto aktiviert</title>
        </head>
        <body>
            <h2>Willkommen bei " . Config::FRONTPANEL_SITE_NAME . "!</h2>
            <p>Hallo {$firstName},</p>
            <p>Ihr Konto wurde erfolgreich aktiviert. Sie können sich jetzt in unserem System anmelden.</p>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='" . Config::FRONTPANEL_SITE_URL . "/public/login.php' 
                   style='background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; display: inline-block;'>
                    Jetzt anmelden
                </a>
            </div>
            
            <p>Falls der Button nicht funktioniert, kopieren Sie diesen Link in Ihren Browser:</p>
            <p style='word-break: break-all; color: #666;'>" . Config::FRONTPANEL_SITE_URL . "/public/login.php</p>
            
            <p>Vielen Dank für Ihr Vertrauen!</p>
            
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
        error_log("Failed to send activation email: " . $e->getMessage());
    }
}
?>

