<?php
/**
 * Server Management Framework
 * 
 * @author Teris
 * @version 3.1.2
 */
/**
 * Kontaktseite für Besucher und Kunden
 */

require_once '../src/sys.conf.php';
require_once '../framework.php';
require_once '../src/core/LanguageManager.php';

// Sprache setzen
$lang = LanguageManager::getInstance();
$currentLang = $lang->getCurrentLanguage();

// Session starten
session_start();

$success = '';
$error = '';
$isLoggedIn = isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true;
$customerName = $_SESSION['customer_name'] ?? '';
$customerEmail = $_SESSION['customer_email'] ?? '';

// Formularverarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Validierung
    if (empty($name)) {
        $error = 'Bitte geben Sie Ihren Namen ein.';
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
    } elseif (empty($subject)) {
        $error = 'Bitte geben Sie einen Betreff ein.';
    } elseif (empty($message)) {
        $error = 'Bitte geben Sie eine Nachricht ein.';
    } else {
        try {
            $db = Database::getInstance();
            
            // Kontaktanfrage in Datenbank speichern
            $stmt = $db->prepare("
                INSERT INTO contact_messages (name, email, subject, message, customer_id, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $customerId = $isLoggedIn ? $_SESSION['customer_id'] : null;
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            if ($stmt->execute([$name, $email, $subject, $message, $customerId, $ipAddress, $userAgent])) {
                $success = 'Ihre Nachricht wurde erfolgreich gesendet. Wir werden uns schnellstmöglich bei Ihnen melden.';
                
                // Formular zurücksetzen
                $name = $email = $subject = $message = '';
            } else {
                $error = 'Fehler beim Senden der Nachricht. Bitte versuchen Sie es erneut.';
            }
        } catch (Exception $e) {
            error_log("Contact Form Error: " . $e->getMessage());
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
    <title><?= t('contact') ?> - Server Management</title>
    
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
                <?php if ($isLoggedIn): ?>
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
                <?php else: ?>
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">
                                <i class="bi bi-box-arrow-in-right"></i> <?= t('login') ?>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">
                                <i class="bi bi-person-plus"></i> <?= t('register') ?>
                            </a>
                        </li>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Contact Content -->
    <div class="container mt-4">
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

        <div class="row">
            <!-- Kontaktformular -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title mb-0">
                            <i class="bi bi-chat-dots text-primary"></i> 
                            <?= t('contact_us') ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-4">
                            <?= t('contact_description') ?>
                        </p>
                        
                        <form method="POST" action="contact.php">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label"><?= t('full_name') ?> *</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?= htmlspecialchars($name ?? $customerName) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label"><?= t('email') ?> *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($email ?? $customerEmail) ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="subject" class="form-label"><?= t('subject') ?> *</label>
                                <input type="text" class="form-control" id="subject" name="subject" 
                                       value="<?= htmlspecialchars($subject ?? '') ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="message" class="form-label"><?= t('message') ?> *</label>
                                <textarea class="form-control" id="message" name="message" rows="6" required><?= htmlspecialchars($message ?? '') ?></textarea>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-send"></i> <?= t('send_message') ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Kontaktinformationen -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-info-circle text-info"></i> 
                            <?= t('contact_information') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h6><i class="bi bi-geo-alt text-primary"></i> <?= t('address') ?></h6>
                            <p class="mb-0">
                                Server Management GmbH<br>
                                Musterstraße 123<br>
                                12345 Musterstadt<br>
                                Deutschland
                            </p>
                        </div>
                        
                        <div class="mb-4">
                            <h6><i class="bi bi-telephone text-success"></i> <?= t('phone') ?></h6>
                            <p class="mb-0">
                                <a href="tel:+49123456789" class="text-decoration-none">+49 123 456789</a><br>
                                <small class="text-muted"><?= t('business_hours') ?>: Mo-Fr 9:00-18:00</small>
                            </p>
                        </div>
                        
                        <div class="mb-4">
                            <h6><i class="bi bi-envelope text-warning"></i> <?= t('email') ?></h6>
                            <p class="mb-0">
                                <a href="mailto:info@servermanagement.de" class="text-decoration-none">info@servermanagement.de</a><br>
                                <a href="mailto:support@servermanagement.de" class="text-decoration-none">support@servermanagement.de</a>
                            </p>
                        </div>
                        
                        <div class="mb-4">
                            <h6><i class="bi bi-clock text-info"></i> <?= t('business_hours') ?></h6>
                            <p class="mb-0">
                                <strong><?= t('monday') ?> - <?= t('friday') ?>:</strong> 9:00 - 18:00<br>
                                <strong><?= t('saturday') ?>:</strong> 10:00 - 14:00<br>
                                <strong><?= t('sunday') ?>:</strong> <?= t('closed') ?>
                            </p>
                        </div>
                        
                        <div>
                            <h6><i class="bi bi-headset text-primary"></i> <?= t('support') ?></h6>
                            <p class="mb-0">
                                <?= t('support_description') ?><br>
                                <a href="support.php" class="btn btn-outline-primary btn-sm mt-2">
                                    <i class="bi bi-ticket"></i> <?= t('support_tickets') ?>
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- FAQ Link -->
                <div class="card mt-4">
                    <div class="card-body text-center">
                        <i class="bi bi-question-circle display-4 text-muted"></i>
                        <h5 class="mt-3"><?= t('frequently_asked_questions') ?></h5>
                        <p class="text-muted"><?= t('faq_description') ?></p>
                        <a href="faq.php" class="btn btn-outline-secondary">
                            <i class="bi bi-search"></i> <?= t('view_faq') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Karte -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-map text-success"></i> 
                            <?= t('location') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="ratio ratio-21x9">
                            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2428.4095673863403!2d13.377705999999999!3d52.516221!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x47a851c655f20989%3A0x26bbfb4e84674c63!2sBrandenburger%20Tor!5e0!3m2!1sde!2sde!4v1234567890" 
                                    style="border:0;" allowfullscreen="" loading="lazy" 
                                    referrerpolicy="no-referrer-when-downgrade"></iframe>
                        </div>
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
</body>
</html>
