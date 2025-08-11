<?php
/**
 * Kunden-Dashboard - Hauptseite für angemeldete Kunden
 */

require_once '../src/sys.conf.php';
require_once '../framework.php';
require_once '../src/core/LanguageManager.php';

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
    error_log("Dashboard Error: " . $e->getMessage());
    $error = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
}

// Logout verarbeiten
if (isset($_GET['logout'])) {
    // Remember Me Token löschen
    if (isset($_COOKIE['remember_token'])) {
        try {
            $stmt = $db->prepare("DELETE FROM customer_remember_tokens WHERE token = ?");
            $stmt->execute([$_COOKIE['remember_token']]);
        } catch (Exception $e) {
            error_log("Logout Error: " . $e->getMessage());
        }
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }
    
    // Session löschen
    session_destroy();
    header('Location: login.php?success=logout');
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('dashboard') ?> - Server Management</title>
    
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
                        <a class="nav-link active" href="dashboard.php">
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

    <!-- Dashboard Content -->
    <div class="container mt-4">
        <!-- Welcome Section -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h2 class="card-title">
                            <i class="bi bi-house-heart text-primary"></i> 
                            <?= t('dashboard_welcome') ?>
                        </h2>
                        <p class="card-text">
                            Willkommen zurück, <?= htmlspecialchars($customerName) ?>! 
                            Hier finden Sie eine Übersicht über Ihre Services und können diese verwalten.
                        </p>
                        <div class="row">
                            <div class="col-md-6">
                                <strong><?= t('email') ?>:</strong> <?= htmlspecialchars($customerEmail) ?>
                            </div>
                            <div class="col-md-6">
                                <strong><?= t('last_login') ?>:</strong> 
                                <?= isset($_SESSION['login_time']) ? date('d.m.Y H:i:s', $_SESSION['login_time']) : t('unknown') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-server display-4 text-primary"></i>
                        <h5 class="card-title mt-2"><?= t('virtual_machines') ?></h5>
                        <p class="card-text display-6">0</p>
                        <small class="text-muted"><?= t('active_servers') ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-globe display-4 text-success"></i>
                        <h5 class="card-title mt-2"><?= t('websites') ?></h5>
                        <p class="card-text display-6">0</p>
                        <small class="text-muted"><?= t('active_websites') ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-envelope display-4 text-info"></i>
                        <h5 class="card-title mt-2"><?= t('email_accounts') ?></h5>
                        <p class="card-text display-6">0</p>
                        <small class="text-muted"><?= t('active_accounts') ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="bi bi-headset display-4 text-warning"></i>
                        <h5 class="card-title mt-2"><?= t('support_tickets') ?></h5>
                        <p class="card-text display-6">0</p>
                        <small class="text-muted"><?= t('open_tickets') ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-lightning"></i> <?= t('quick_actions') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="support.php?action=new" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-plus-circle"></i> <?= t('submit_ticket') ?>
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="profile.php" class="btn btn-outline-secondary w-100">
                                    <i class="bi bi-person-gear"></i> <?= t('edit_profile') ?>
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="change-password.php" class="btn btn-outline-warning w-100">
                                    <i class="bi bi-key"></i> <?= t('change_password') ?>
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="contact.php" class="btn btn-outline-info w-100">
                                    <i class="bi bi-chat-dots"></i> <?= t('contact') ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <div class="col-md-8 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-clock-history"></i> <?= t('recent_activity') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-inbox display-4"></i>
                            <p class="mt-2"><?= t('no_recent_activity') ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-bell"></i> <?= t('notifications') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-bell-slash display-4"></i>
                            <p class="mt-2"><?= t('no_notifications') ?></p>
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

    <script>
        // Session-Timeout prüfen
        let sessionTimeout = <?= 3600 * 1000 ?>; // 1 Stunde in Millisekunden
        let lastActivity = Date.now();
        
        function checkSession() {
            let now = Date.now();
            if (now - lastActivity > sessionTimeout) {
                alert('<?= t('session_expired') ?>');
                window.location.href = 'login.php';
            }
        }
        
        // Aktivität verfolgen
        document.addEventListener('click', function() {
            lastActivity = Date.now();
        });
        
        document.addEventListener('keypress', function() {
            lastActivity = Date.now();
        });
        
        // Alle 5 Minuten prüfen
        setInterval(checkSession, 5 * 60 * 1000);
    </script>
</body>
</html>

