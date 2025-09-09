<?php
/**
 * Server Management Framework
 * 
 * @author Teris
 * @version 3.1.2
 */
/**
 * Kunden-Profilseite - Bearbeitung der persönlichen Daten
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
$customerEmail = $_SESSION['customer_email'] ?? '';

$success = '';
$error = '';

// Kundeninformationen aus der Datenbank laden
try {
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT * FROM customers WHERE id = ? AND status = 'active'");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        // Kunde nicht gefunden oder inaktiv - Session löschen
        session_destroy();
        header('Location: login.php?error=account_inactive');
        exit;
    }
} catch (Exception $e) {
    error_log("Profile Error: " . $e->getMessage());
    $error = t('an_error_occurred') . ' ' . t('please_try_again_later');
}

// Formularverarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $company = trim($_POST['company'] ?? '');
    
    // Validierung
    if (empty($fullName)) {
        $error = t('a_full_name_is_required');
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = t('a_valid_email_address_is_required');
    } else {
        // Prüfen ob E-Mail bereits von anderem Kunden verwendet wird
        try {
            $stmt = $db->prepare("SELECT id FROM customers WHERE email = ? AND id != ?");
            $stmt->execute([$email, $customerId]);
            if ($stmt->fetch()) {
                $error = t('this_email_address_is_already_used_by_another_customer');
            } else {
                // Profil aktualisieren
                $stmt = $db->prepare("
                    UPDATE customers SET 
                        first_name = ?, 
                        last_name = ?, 
                        email = ?, 
                        phone = ?, 
                        company = ?, 
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                // Name in first_name und last_name aufteilen
                $nameParts = explode(' ', $fullName, 2);
                $firstName = $nameParts[0] ?? '';
                $lastName = $nameParts[1] ?? '';
                
                if ($stmt->execute([$firstName, $lastName, $email, $phone, $company, $customerId])) {
                    $success = t('your_profile_has_been_successfully_updated');
                    
                    // Session-Daten aktualisieren
                    $_SESSION['customer_name'] = $fullName;
                    $_SESSION['customer_email'] = $email;
                    
                    // Aktualisierte Daten laden
                    $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
                    $stmt->execute([$customerId]);
                    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

                    // Profiländerung protokollieren
                    try {
                        $activityLogger = ActivityLogger::getInstance();
                        $activityLogger->logCustomerActivity(
                            $customerId, 
                            'profile_update', 
                            'Profil erfolgreich aktualisiert', 
                            $customerId, 
                            'customers'
                        );
                    } catch (Exception $e) {
                        error_log("Activity Logging Error: " . $e->getMessage());
                    }
                } else {
                    $error = t('error_updating_profile') . ' ' . t('please_try_again_later');
                }
            }
        } catch (Exception $e) {
            error_log("Profile Update Error: " . $e->getMessage());
            $error = t('an_error_occurred') . ' ' . t('please_try_again_later');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('profile') ?> - Server Management</title>
    
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
                <i class="bi bi-server"></i> <?= Config::FRONTPANEL_SITE_NAME ?>
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
                        <a class="nav-link active" href="profile.php">
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

    <!-- Profile Content -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title mb-0">
                            <i class="bi bi-person-gear text-primary"></i> 
                            <?= t('edit_profile') ?>
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
                        
                        <form method="POST" action="profile.php">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="full_name" class="form-label"><?= t('full_name') ?> *</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" 
                                           value="<?= htmlspecialchars(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label"><?= t('email') ?> *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($customer['email'] ?? '') ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label"><?= t('phone') ?></label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?= htmlspecialchars($customer['phone'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="company" class="form-label"><?= t('company') ?></label>
                                    <input type="text" class="form-control" id="company" name="company" 
                                           value="<?= htmlspecialchars($customer['company'] ?? '') ?>">
                                </div>
                            </div>
                            

                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="dashboard.php" class="btn btn-secondary me-md-2">
                                    <i class="bi bi-arrow-left"></i> <?= t('back') ?>
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i> <?= t('save') ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Account Information -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-info-circle text-info"></i> 
                            <?= t('account_information') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong><?= t('customer_id') ?>:</strong> <?= htmlspecialchars($customer['id']) ?></p>
                                <p><strong><?= t('status') ?>:</strong> 
                                    <span class="badge bg-success"><?= t('active') ?></span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p><strong><?= t('created') ?>:</strong> 
                                    <?= date('d.m.Y H:i:s', strtotime($customer['created_at'])) ?>
                                </p>
                                <p><strong><?= t('last_login') ?>:</strong> 
                                    <?= (isset($customer['last_login']) && $customer['last_login']) ? date('d.m.Y H:i:s', strtotime($customer['last_login'])) : t('unknown') ?>
                                </p>
                            </div>
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
