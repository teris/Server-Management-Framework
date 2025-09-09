<?php
/**
 * Server Management Framework
 * 
 * @author Teris
 * @version 3.1.2
 */
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
$systemCreationResults = '';

// Token aus URL-Parameter holen
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $error = t('invalid_confirmation_link');
} else {
    try {
        // Datenbankverbindung
        $db = Database::getInstance();
        
        // Token in der Datenbank suchen
        $stmt = $db->prepare("
            SELECT cv.id, cv.customer_id, cv.expires_at, c.email, c.first_name, c.last_name, c.status 
            FROM customer_verification_tokens cv 
            JOIN customers c ON cv.customer_id = c.id 
            WHERE cv.token = ? AND cv.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $verification = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$verification) {
            $error = t('invalid_or_expired_confirmation_token');
        } elseif ($verification['status'] === 'active') {
            $error = t('your_account_is_already_activated');
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
                
                // Benutzerkonten in allen Systemen erstellen
                try {
                    $serviceManager = new ServiceManager();
                    
                    // Benutzername aus E-Mail generieren (alles vor dem @)
                    $username = strtolower(explode('@', $verification['email'])[0]);
                    
                    // Für jedes externe System ein eigenes Passwort generieren
                    $systemPasswords = [];
                    
                    if (Config::ISPCONFIG_USEING) {
                        $systemPasswords['ispconfig'] = bin2hex(random_bytes(8)); // 16 Zeichen
                    }
                    if (Config::OGP_USEING) {
                        $systemPasswords['ogp'] = bin2hex(random_bytes(8)); // 16 Zeichen
                    }
                    if (Config::PROXMOX_USEING) {
                        $systemPasswords['proxmox'] = bin2hex(random_bytes(8)); // 16 Zeichen
                    }
                    
                    // Benutzer in allen Systemen erstellen
                    $creationResult = $serviceManager->createUserInAllSystems(
                        $username,
                        $systemPasswords, // Alle System-Passwörter übergeben
                        $verification['first_name'],
                        $verification['last_name'],
                        [
                            'email' => $verification['email'],
                            'company' => '', // Kann später ergänzt werden
                            'phone' => ''    // Kann später ergänzt werden
                        ]
                    );
                    
                    if ($creationResult['success']) {
                        $systemCreationResults = t('your_system_accounts_have_been_successfully_created');
                        
                        // Erfolgreiche Systemerstellung loggen
                        $db->logAction(
                            'System User Creation',
                            t('user') . " $username " . t('successfully_created_in_all_systems') . ": " . implode(', ', array_keys($creationResult['results'])),
                            'success'
                        );
                        
                        // E-Mail mit allen System-Anmeldedaten senden
                        $emailSent = sendSystemCredentialsEmail(
                            $verification['email'], 
                            $verification['first_name'], 
                            $verification['last_name'],
                            $username, 
                            $systemPasswords,
                            $creationResult['results']
                        );
                        
                        if ($emailSent) {
                            $success .= t('an_email_with_your_system_login_details_has_been_sent_to_your_email_address');
                        } else {
                            $success .= ' WARNUNG: Die E-Mail mit Ihren System-Anmeldedaten konnte nicht gesendet werden. Bitte kontaktieren Sie den Support.';
                        }
                        
                    } else {
                        $systemCreationResults = t('warning') . ': ' . t('some_system_accounts_could_not_be_created');
                        
                        // Fehler loggen
                        $db->logAction(
                            'System User Creation',
                            "Fehler beim Anlegen der Systemkonten für $username: " . json_encode($creationResult['errors']),
                            'error'
                        );
                    }
                    
                } catch (Exception $e) {
                    $systemCreationResults = 'Warnung: Fehler beim Anlegen der Systemkonten.';
                    error_log("System User Creation Error: " . $e->getMessage());
                    
                    // Fehler loggen
                    $db->logAction(
                        'System User Creation',
                        "Exception beim Anlegen der Systemkonten für Benutzer {$verification['customer_id']}: " . $e->getMessage(),
                        'error'
                    );
                }
                
                $success = 'Ihr Konto wurde erfolgreich aktiviert! Sie können sich jetzt anmelden.';
                
                // E-Mail mit Anmeldedaten senden (wenn noch nicht gesendet)
                if (!isset($emailSent) || !$emailSent) {
                    $emailSent = sendSystemCredentialsEmail(
                        $verification['email'], 
                        $verification['first_name'], 
                        $verification['last_name'],
                        $username, 
                        $systemPasswords ?? [],
                        $creationResult['results'] ?? []
                    );
                    
                    if ($emailSent) {
                        $success .= t('an_email_with_your_system_login_details_has_been_sent_to_your_email_address');
                    } else {
                        $success .= t('warning') . ': ' . t('the_email_with_your_system_login_details_could_not_be_sent');
                    }
                }
                
            } else {
                $error = t('error_during_account_activation');
            }
        }
    } catch (Exception $e) {
        error_log("Email Verification Error: " . $e->getMessage());
        $error = t('an_error_occurred');
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
                <i class="bi bi-server"></i> <?= Config::FRONTPANEL_SITE_NAME ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">
                    <i class="bi bi-house"></i> <?= t('back_to_frontpanel') ?>
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
                            
                            <?php if ($systemCreationResults): ?>
                                <div class="alert alert-info alert-dismissible fade show" role="alert">
                                    <i class="bi bi-info-circle"></i> <?= htmlspecialchars($systemCreationResults) ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
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
                                <a href="contact.php" class="text-decoration-none"><?= t('contact') ?></a>
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
function sendActivationEmail($email, $firstName, $lastName) {
    try {
        $to = $email;
        $subject = t('account_activated') . " - " . Config::FRONTPANEL_SITE_NAME;

        $verificationLink = "https://" . $_SERVER['HTTP_HOST'] . "/public/login.php";

        $message = "
        <html>
        <head>
            <title><?= t('account_activated') ?></title>
        </head>
        <body>
            <h2>Willkommen bei " . Config::FRONTPANEL_SITE_NAME . "!</h2>
            <p>Hallo {$firstName} {$lastName},</p>
            <p>Ihr Konto wurde erfolgreich aktiviert. Sie können sich jetzt in unserem System anmelden.</p>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$verificationLink}' style='background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; display: inline-block;'>
                    Jetzt anmelden
                </a>
            </div>
            
            <p>Falls der Button nicht funktioniert, kopieren Sie diesen Link in Ihren Browser:</p>
            <p style='word-break: break-all; color: #666;'>{$verificationLink}</p>
            
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

/**
 * E-Mail mit System-Anmeldedaten senden
 */
function sendSystemCredentialsEmail($email, $firstName, $lastName, $username, $systemPasswords, $systemResults) {
    try {
        $to = $email;
        $subject = "Ihre System-Anmeldedaten - " . Config::FRONTPANEL_SITE_NAME;
        $verificationLink = "https://" . $_SERVER['HTTP_HOST'] . "/public/login.php";
        // Portal-Links aus der Config laden
        $portalLinks = [];
        if (Config::ISPCONFIG_USEING) {
            $portalLinks['ispconfig'] = Config::ISPCONFIG_HOST;
        }
        if (Config::OGP_USEING) {
            $portalLinks['ogp'] = Config::OGP_HOST;
        }
        if (Config::PROXMOX_USEING) {
            $portalLinks['proxmox'] = Config::PROXMOX_HOST;
        }
        
        $message = "
        <html>
        <head>
            <title>System-Anmeldedaten</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #007bff; border-bottom: 2px solid #007bff; padding-bottom: 10px;'>
                    Ihre System-Anmeldedaten
                </h2>
                
                <p>Hallo {$firstName} {$lastName},</p>
                
                <p>Ihr Konto wurde erfolgreich aktiviert! Ihre Benutzerkonten in den folgenden Systemen wurden erfolgreich angelegt:</p>
                
                <div style='padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #007bff;'>
                    <h3 style='margin-top: 0; color: #007bff;'>🎯 Frontpanel-Anmeldung</h3>
                    <p><strong>Portal:</strong> <a href='{$verificationLink}'>{$verificationLink}</a></p>
                    <p><strong>E-Mail:</strong> {$email}</p>
                    <p><strong>Passwort:</strong> <span style='padding: 2px 6px; border-radius: 4px;'>Das Passwort, das Sie bei der Registrierung angegeben haben</span></p>
                </div>
                
                <h3>🔐 Externe Systeme - Neue Anmeldedaten</h3>
                <p><strong>Wichtig:</strong> Sie können alle Dienstleistungen, welche sie bei uns angefordert haben, über unsere Externe Systeme ebenfalls verwalten.<br>
                Für jedes externe System wurde ein eigenes Passwort generiert. <br>
                Bitte ändern Sie diese Passwörter daher nach dem ersten Login aus Sicherheitsgründen!</p>
                
                <div style='margin: 20px 0;'>";
        
        // ISPConfig
        if (isset($systemPasswords['ispconfig']) && isset($portalLinks['ispconfig'])) {
            $message .= "
                    <div style='padding: 15px; border-radius: 8px; margin: 10px 0; border: 1px solid #ffeaa7;'>
                        <h4 style='margin-top: 0; color: #856404;'>🌐 Webhosting-Verwaltung</h4>
                        <p>Portal: <a href='{$portalLinks['ispconfig']}'>{$portalLinks['ispconfig']}</a></p>
                        <p>Benutzername: {$username}</p>
                        <p>Passwort: {$systemPasswords['ispconfig']}</p>
                    </div>";
        }
        
        // OpenGamePanel
        if (isset($systemPasswords['ogp']) && isset($portalLinks['ogp'])) {
            $message .= "
                    <div style='padding: 15px; border-radius: 8px; margin: 10px 0; border: 1px solid #bee5eb;'>
                        <h4 style='margin-top: 0; color: #0c5460;'>🎮 Spieleserver-Verwaltung</h4>
                        <p>Portal: <a href='{$portalLinks['ogp']}'>{$portalLinks['ogp']}</a></p>
                        <p>Benutzername: {$firstName} {$lastName}</p>
                        <p>Passwort: {$systemPasswords['ogp']}</p>
                    </div>";
        }
        
        // Proxmox
        if (isset($systemPasswords['proxmox']) && isset($portalLinks['proxmox'])) {
            $message .= "
                    <div style='padding: 15px; border-radius: 8px; margin: 10px 0; border: 1px solid #c3e6cb;'>
                        <h4 style='margin-top: 0; color: #155724;'>🖥️ Virtuelle Maschinen</h4>
                        <p>Portal: <a href='{$portalLinks['proxmox']}'>{$portalLinks['proxmox']}</a></p>
                        <p>Benutzername:{$username}</p>
                        <p>Login Domäne: Proxmox VE authenitcation Server (PVE)</p>
                        <p>Passwort: {$systemPasswords['proxmox']}</p>
                    </div>";
        }
        
        $message .= "
                </div>
                
                <div style='padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #ffeaa7;'>
                    <h3 style='margin-top: 0; color: #856404;'>⚠️ WICHTIGER SICHERHEITSHINWEIS</h3>
                    <p><strong>Bitte ändern Sie die Passwörter in den externen Systemen nach dem ersten Login!</strong></p>
                    <p>Die generierten Passwörter sind nur für den ersten Login gedacht. Aus Sicherheitsgründen sollten Sie diese sofort durch eigene, sichere Passwörter ersetzen.</p>
                    <ul>
                        <li>Verwenden Sie mindestens 12 Zeichen</li>
                        <li>Kombinieren Sie Groß- und Kleinbuchstaben, Zahlen und Sonderzeichen</li>
                        <li>Verwenden Sie für jedes System ein unterschiedliches Passwort</li>
                        <li>Speichern Sie die neuen Passwörter sicher ab</li>
                    </ul>
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$verificationLink}' 
                       style='padding: 15px 30px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold;'>
                        Jetzt im Frontpanel anmelden
                    </a>
                </div>
                
                <p>Falls der Button nicht funktioniert, kopieren Sie diesen Link in Ihren Browser:</p>
                <p style='word-break: break-all; color: #666; background: #f8f9fa; padding: 10px; border-radius: 4px;'>{$verificationLink}</p>
                
                <p>Falls Sie Fragen haben oder Probleme beim Login haben, kontaktieren Sie uns gerne unter <strong>" . Config::FRONTPANEL_SUPPORT_EMAIL . "</strong></p>
                
                <p>Mit freundlichen Grüßen<br>
                Ihr <strong>" . Config::FRONTPANEL_SITE_NAME . "</strong> Team</p>
            </div>
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
        
        $mailResult = mail($to, $subject, $message, implode("\r\n", $headers));
        
        if ($mailResult) {
            error_log("System credentials email sent successfully to: " . $email);
        } else {
            error_log("Failed to send system credentials email to: " . $email);
        }
        
        return $mailResult;
        
    } catch (Exception $e) {
        error_log("Failed to send system credentials email: " . $e->getMessage());
        return false;
    }
}
?>

