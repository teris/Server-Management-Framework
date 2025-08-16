<?php
/**
 * Domain-Registrierung - Benutzeroberfläche für Domain-Registrierungen
 */

// Start output buffering to prevent any unwanted output
ob_start();

// Suppress warnings for AJAX requests
if (isset($_POST['action']) && $_POST['action'] === 'check_domain_ajax') {
    error_reporting(E_ERROR | E_PARSE);
    ini_set('display_errors', 0);
}

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
    error_log("Domain Registration Error: " . $e->getMessage());
    $error = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
}

// Domain-Verfügbarkeitsprüfung
$domainCheckResult = null;
$alternativeDomains = [];

if ($_POST && isset($_POST['action']) && $_POST['action'] === 'check_domain') {
    $domain = trim($_POST['domain']);
    
    // Echte Domain-Verfügbarkeitsprüfung
    $availabilityResult = checkDomainAvailability($domain);
    
    if (isset($availabilityResult['error'])) {
        $domainCheckResult = [
            'available' => false,
            'domain' => $domain,
            'message' => $availabilityResult['error']
        ];
        $alternativeDomains = [];
    } else {
        $isAvailable = $availabilityResult['available'];
        
        if ($isAvailable) {
            $domainCheckResult = [
                'available' => true,
                'domain' => $domain,
                'message' => t('domain_available')
            ];
        } else {
            $domainCheckResult = [
                'available' => false,
                'domain' => $domain,
                'message' => t('domain_not_available')
            ];
        }
        
        // Alternative Domains generieren
        $alternativeDomains = generateAlternativeDomains($domain);
    }
}

// AJAX Domain-Verfügbarkeitsprüfung
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'check_domain_ajax') {
    // Clear any previous output
    ob_clean();
    
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
         try {
         $domainName = trim($_POST['domainName']);
         $domainExtension = trim($_POST['domainExtension']);
         
         if (empty($domainName) || empty($domainExtension)) {
             echo json_encode([
                 'success' => false,
                 'error' => 'Domain name and extension are required'
             ]);
             exit;
         }
         
                  // Domain zusammenbauen
         $domain = $domainName . '.' . $domainExtension;
         
         if ($domainExtension === 'all') {
             // Alle aktiven TLDs prüfen
             $allResults = checkAllTLDs($domainName);
             echo json_encode([
                 'success' => true,
                 'domainName' => $domainName,
                 'allTLDs' => true,
                 'results' => $allResults
             ]);
         } else {
             // Einzelne Domain prüfen
             $availabilityResult = checkDomainAvailability($domain);
             
             if (isset($availabilityResult['error'])) {
                 echo json_encode([
                     'success' => false,
                     'error' => $availabilityResult['error']
                 ]);
             } else {
                 $isAvailable = $availabilityResult['available'];
                 $alternativeDomains = generateAlternativeDomains($domain);
                 
                 echo json_encode([
                     'success' => true,
                     'domain' => $domain,
                     'available' => $isAvailable,
                     'message' => $availabilityResult['message'] ?? '',
                     'method' => $availabilityResult['method'] ?? '',
                     'alternatives' => $alternativeDomains
                 ]);
             }
         }
    } catch (Exception $e) {
        error_log("AJAX domain check error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Internal server error during domain check'
        ]);
    }
    
    // End output buffering and send response
    ob_end_flush();
    exit;
}

// Domain-Registrierung einreichen
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'register_domain') {
    $domain = trim($_POST['domain']);
    $purpose = trim($_POST['purpose']);
    $notes = trim($_POST['notes']);
    
    $result = submitDomainRegistration($customerId, $domain, $purpose, $notes);
    
    if ($result['success']) {
        $successMessage = t('registration_submitted');
    } else {
        $errorMessage = $result['error'];
    }
}

// Hilfsfunktionen
function checkDomainAvailability($domain) {
    // Validate domain format
    if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$/', $domain)) {
        return [
            'available' => false,
            'error' => 'Invalid domain format',
            'message' => 'Please enter a valid domain name'
        ];
    }

    // Check rate limiting (reduced from 6 to 2 seconds for better user experience)
    $rateLimitFile = sys_get_temp_dir() . '/domain_check_' . md5($_SERVER['REMOTE_ADDR']);
    if (file_exists($rateLimitFile) && (time() - filemtime($rateLimitFile)) < 2) {
        return [
            'available' => false,
            'error' => 'Rate limit exceeded',
            'message' => 'Bitte warten Sie 2 Sekunden zwischen den Domain-Prüfungen'
        ];
    }
    file_put_contents($rateLimitFile, time());

    try {
        // Simple and reliable HTTP check
        return checkSimpleHTTPAvailability($domain);

    } catch (Exception $e) {
        error_log("Domain availability check failed: " . $e->getMessage());
        return [
            'available' => true,
            'message' => 'Domain availability unclear (check failed)',
            'method' => 'Error fallback'
        ];
    }
}





function checkSimpleHTTPAvailability($domain) {
    // Use OVH API from framework for reliable domain availability check
    try {
        // Check domain availability using OVH API from framework
        $availability = checkOVHDomainAvailability($domain);
        
        if ($availability !== null) {
            return $availability;
        }
        
        // Fallback to other methods if OVH API fails
        return checkFallbackAvailability($domain);
        
    } catch (Exception $e) {
        error_log("OVH API check failed: " . $e->getMessage());
        return checkFallbackAvailability($domain);
    }
}

function checkOVHDomainAvailability($domain) {
    try {
        // Try different OVH API endpoints for domain availability
        $endpoints = [
            "/domain/{$domain}/availability",
            "/order/domain/zone/{$domain}",
            "/domain/zone/{$domain}"
        ];
        
        foreach ($endpoints as $endpoint) {
            // Use the framework's OvhAPI function
            try {
                // Get ServiceManager instance from framework
                if (class_exists('ServiceManager')) {
                    $serviceManager = new ServiceManager();
                    $response = $serviceManager->OvhAPI('get', $endpoint);
                    
                    if ($response === false) {
                        // API call failed, try next endpoint
                        continue;
                    }
                    
                    if (isset($response['success']) && $response['success'] === false) {
                        // API error, try next endpoint
                        continue;
                    }
                    
                    // Check if domain is available (404 means not found = available)
                    if (isset($response['http_code']) && $response['http_code'] === 404) {
                        return [
                            'available' => true,
                            'message' => 'Domain is available (not found in OVH system)',
                            'method' => 'OVH API (Framework)'
                        ];
                    }
                    
                    // If we get a successful response, domain is likely taken
                    if (isset($response['http_code']) && $response['http_code'] === 200) {
                        return [
                            'available' => false,
                            'message' => 'Domain is not available (found in OVH system)',
                            'method' => 'OVH API (Framework)',
                            'details' => $response
                        ];
                    }
                    
                    // If response contains data, domain is taken
                    if (is_array($response) && !empty($response)) {
                        return [
                            'available' => false,
                            'message' => 'Domain is not available (found in OVH system)',
                            'method' => 'OVH API (Framework)',
                            'details' => $response
                        ];
                    }
                } else {
                    // ServiceManager class not available, try next endpoint
                    continue;
                }
            } catch (Exception $e) {
                // ServiceManager error, try next endpoint
                error_log("ServiceManager error for endpoint $endpoint: " . $e->getMessage());
                continue;
            }
        }
        
        return null;
        
    } catch (Exception $e) {
        error_log("OVH API framework call failed: " . $e->getMessage());
        return null;
    }
}

function checkFallbackAvailability($domain) {
    // Fallback method using DNS check (less reliable but better than nothing)
    try {
        $oldErrorReporting = error_reporting();
        error_reporting(0);
        
        $dnsRecords = @dns_get_record($domain, DNS_ANY);
        
        error_reporting($oldErrorReporting);
        
        if ($dnsRecords === false || empty($dnsRecords)) {
            return [
                'available' => true,
                'message' => 'Domain appears to be available (no DNS records found)',
                'method' => 'DNS fallback'
            ];
        }
        
        return [
            'available' => false,
            'message' => 'Domain is not available (DNS records found)',
            'method' => 'DNS fallback'
        ];
        
    } catch (Exception $e) {
        error_reporting($oldErrorReporting);
        return [
            'available' => true,
            'message' => 'Domain availability unclear (fallback check failed)',
            'method' => 'Fallback error'
        ];
    }
}

function checkAllTLDs($domainName) {
    $results = [];
    
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT tld, name FROM domain_extensions WHERE active = 1 ORDER BY tld ASC");
        $stmt->execute();
        $extensions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($extensions as $ext) {
            $fullDomain = $domainName . '.' . $ext['tld'];
            $availability = checkDomainAvailability($fullDomain);
            
            $results[] = [
                'domain' => $fullDomain,
                'tld' => $ext['tld'],
                'name' => $ext['name'],
                'available' => $availability['available'] ?? false,
                'message' => $availability['message'] ?? '',
                'method' => $availability['method'] ?? ''
            ];
        }
    } catch (Exception $e) {
        error_log("Error checking all TLDs: " . $e->getMessage());
    }
    
    return $results;
}

function generateAlternativeDomains($domain) {
    $alternatives = [];
    $parts = explode('.', $domain);
    $name = $parts[0];
    $tld = isset($parts[1]) ? $parts[1] : 'com';
    
    // Verschiedene TLDs
    $tlds = ['net', 'org', 'info', 'biz', 'co', 'io', 'de', 'eu'];
    foreach ($tlds as $newTld) {
        if ($newTld !== $tld) {
            $alternatives[] = $name . '.' . $newTld;
        }
    }
    
    // Variationen des Namens
    $variations = [
        $name . 'online',
        $name . 'web',
        $name . 'site',
        'my' . $name,
        'get' . $name,
        $name . 'app'
    ];
    
    foreach ($variations as $variation) {
        $alternatives[] = $variation . '.' . $tld;
    }
    
    return array_slice($alternatives, 0, 8); // Maximal 8 Alternativen
}

function submitDomainRegistration($customerId, $domain, $purpose, $notes) {
    try {
        $db = Database::getInstance();
        
        // Prüfen ob Domain bereits registriert wurde
        $stmt = $db->prepare("SELECT id FROM domain_registrations WHERE domain = ? AND status != 'cancelled'");
        $stmt->execute([$domain]);
        
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Diese Domain wurde bereits registriert.'];
        }
        
        // Neue Registrierung einfügen
        $stmt = $db->prepare("
            INSERT INTO domain_registrations (user_id, domain, purpose, notes, status, created_at) 
            VALUES (?, ?, ?, ?, 'pending', NOW())
        ");
        
        $stmt->execute([$customerId, $domain, $purpose, $notes]);
        
        // Aktivität loggen
        try {
            $activityLogger = ActivityLogger::getInstance();
            $activityLogger->logCustomerActivity(
                $customerId, 
                'domain_register', 
                "Domain-Registrierung eingereicht: $domain", 
                $db->lastInsertId(), 
                'domain_registrations'
            );
        } catch (Exception $e) {
            error_log("Activity Logging Error: " . $e->getMessage());
        }
        
        return ['success' => true];
    } catch (Exception $e) {
        error_log("Domain Registration Error: " . $e->getMessage());
        return ['success' => false, 'error' => 'Ein Datenbankfehler ist aufgetreten.'];
    }
}

// Benutzer-Registrierungen laden
$userRegistrations = [];
try {
    $stmt = $db->prepare("
        SELECT * FROM domain_registrations 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$customerId]);
    $userRegistrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error loading user registrations: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('domain_registration') ?> - Server Management</title>
    
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
                    <li class="nav-item">
                        <a class="nav-link active" href="domain-registration.php">
                            <i class="bi bi-globe"></i> <?= t('domain_registration') ?>
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

    <!-- Main Content -->
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h2">
                        <i class="bi bi-globe text-primary"></i> <?= t('domain_registration') ?>
                    </h1>
                </div>

                <?php if (isset($successMessage)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?= $successMessage ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($errorMessage)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?= $errorMessage ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Domain-Verfügbarkeitsprüfung -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-search"></i> <?= t('check_domain_availability') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                                                                         <form method="POST" id="domainCheckForm">
                            <input type="hidden" name="action" value="check_domain">
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="domainName" class="form-label"><?= t('domain_name') ?></label>
                                    <input type="text" class="form-control" name="domainName" id="domainName" 
                                           placeholder="<?= t('enter_domain_name') ?>" 
                                           value="<?= htmlspecialchars($_POST['domainName'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="domainExtension" class="form-label"><?= t('domain_extension') ?></label>
                                    <select class="form-select" name="domainExtension" id="domainExtension" required>
                                        <option value=""><?= t('select_extension') ?></option>
                                        <option value="all" <?= ($_POST['domainExtension'] ?? '') === 'all' ? 'selected' : '' ?>>
                                            <?= t('all_tlds') ?>
                                        </option>
                                        <?php
                                        // Aktive Domain-Endungen aus der Datenbank laden
                                        try {
                                            $db = Database::getInstance();
                                            $stmt = $db->prepare("SELECT tld, name FROM domain_extensions WHERE active = 1 ORDER BY tld ASC");
                                            $stmt->execute();
                                            $extensions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                            
                                            foreach ($extensions as $ext) {
                                                $selected = ($_POST['domainExtension'] ?? '') === $ext['tld'] ? 'selected' : '';
                                                echo '<option value="' . htmlspecialchars($ext['tld']) . '" ' . $selected . '>';
                                                echo '.' . htmlspecialchars($ext['tld']) . ' - ' . htmlspecialchars($ext['name']);
                                                echo '</option>';
                                            }
                                        } catch (Exception $e) {
                                            error_log("Error loading domain extensions: " . $e->getMessage());
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">&nbsp;</label>
                                    <button class="btn btn-primary w-100" type="submit">
                                        <i class="bi bi-search"></i> <?= t('check_availability') ?>
                                    </button>
                                </div>
                            </div>
                            <div class="form-text mt-2">
                                <i class="bi bi-info-circle"></i> 
                                <small><?= t('domain_check_info_rate_limit') ?></small>
                            </div>
                        </form>

                        <!-- AJAX Ergebnis-Container -->
                        <div id="availabilityResult">
                            <?php if ($domainCheckResult): ?>
                                <div class="mt-3">
                                    <div class="alert alert-<?= $domainCheckResult['available'] ? 'success' : 'warning' ?>">
                                        <h6><i class="bi bi-<?= $domainCheckResult['available'] ? 'check-circle' : 'exclamation-triangle' ?>"></i> 
                                            <?= htmlspecialchars($domainCheckResult['domain']) ?>
                                        </h6>
                                        <p class="mb-0"><?= $domainCheckResult['message'] ?></p>
                                    </div>

                                    <?php if (!empty($alternativeDomains)): ?>
                                        <div class="mt-3">
                                            <h6><?= t('alternative_domains') ?>:</h6>
                                            <div class="row">
                                                <?php foreach ($alternativeDomains as $altDomain): ?>
                                                    <div class="col-md-3 mb-2">
                                                        <button class="btn btn-outline-secondary btn-sm w-100" 
                                                                onclick="checkAlternativeDomain('<?= htmlspecialchars($altDomain) ?>')">
                                                            <?= htmlspecialchars($altDomain) ?>
                                                        </button>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($domainCheckResult['available']): ?>
                                        <div class="mt-3">
                                            <button class="btn btn-success" onclick="showRegistrationForm('<?= htmlspecialchars($domainCheckResult['domain']) ?>')">
                                                <i class="bi bi-plus-circle"></i> <?= t('register_this_domain') ?>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Domain-Registrierungsformular -->
                <div class="card mb-4" id="registrationForm" style="display: none;">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-plus-circle"></i> <?= t('register_domain') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="domainRegistrationForm">
                            <input type="hidden" name="action" value="register_domain">
                            <input type="hidden" name="domain" id="registrationDomain">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="purpose" class="form-label"><?= t('purpose') ?> *</label>
                                        <select class="form-select" name="purpose" id="purpose" required>
                                            <option value=""><?= t('select_purpose') ?></option>
                                            <option value="business"><?= t('business_website') ?></option>
                                            <option value="personal"><?= t('personal_website') ?></option>
                                            <option value="blog"><?= t('blog') ?></option>
                                            <option value="ecommerce"><?= t('ecommerce') ?></option>
                                            <option value="portfolio"><?= t('portfolio') ?></option>
                                            <option value="other"><?= t('other') ?></option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label"><?= t('additional_notes') ?></label>
                                <textarea class="form-control" name="notes" id="notes" rows="3" 
                                          placeholder="<?= t('describe_your_project') ?>"></textarea>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> 
                                <strong><?= t('important') ?>:</strong> 
                                <?= t('domain_registration_info') ?>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="button" class="btn btn-secondary" onclick="hideRegistrationForm()">
                                    <?= t('cancel') ?>
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-send"></i> <?= t('submit_registration') ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Meine Domain-Registrierungen -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-list-ul"></i> <?= t('my_domain_registrations') ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($userRegistrations)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-inbox display-4"></i>
                                <p class="mt-2"><?= t('no_domain_registrations') ?></p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th><?= t('domain') ?></th>
                                            <th><?= t('purpose') ?></th>
                                            <th><?= t('status') ?></th>
                                            <th><?= t('submitted') ?></th>
                                            <th><?= t('admin_notes') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($userRegistrations as $registration): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($registration['domain']) ?></strong>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars($registration['purpose']) ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    $statusText = '';
                                                    switch ($registration['status']) {
                                                        case 'pending':
                                                            $statusClass = 'warning';
                                                            $statusText = t('pending_approval');
                                                            break;
                                                        case 'approved':
                                                            $statusClass = 'success';
                                                            $statusText = t('approved');
                                                            break;
                                                        case 'rejected':
                                                            $statusClass = 'danger';
                                                            $statusText = t('rejected');
                                                            break;
                                                        case 'cancelled':
                                                            $statusClass = 'secondary';
                                                            $statusText = t('cancelled');
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge bg-<?= $statusClass ?>"><?= $statusText ?></span>
                                                </td>
                                                <td>
                                                    <small><?= date('d.m.Y H:i', strtotime($registration['created_at'])) ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($registration['admin_notes']): ?>
                                                        <small class="text-muted"><?= htmlspecialchars($registration['admin_notes']) ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

         <script>
     
          function checkAlternativeDomain(domain) {
         // Domain in Name und Extension aufteilen
         const parts = domain.split('.');
         if (parts.length >= 2) {
             const name = parts[0];
             const extension = parts.slice(1).join('.');
             
             document.getElementById('domainName').value = name;
             document.getElementById('domainExtension').value = extension;
             checkDomainAvailability();
         }
     }
    
    function showRegistrationForm(domain) {
        document.getElementById('registrationDomain').value = domain;
        document.getElementById('registrationForm').style.display = 'block';
        document.getElementById('registrationForm').scrollIntoView({ behavior: 'smooth' });
    }
    
    function hideRegistrationForm() {
        document.getElementById('registrationForm').style.display = 'none';
    }
    
         // AJAX Domain-Verfügbarkeitsprüfung
     function checkDomainAvailability() {
         const domainName = document.getElementById('domainName').value.trim();
         const domainExtension = document.getElementById('domainExtension').value;
         const resultContainer = document.getElementById('availabilityResult');
         const checkButton = document.querySelector('#domainCheckForm button[type="submit"]');
         
         if (!domainName || !domainExtension) {
             showError('Bitte geben Sie einen Domain-Namen und eine Endung ein.');
             return;
         }
         
         // Domain-Format validieren
         const nameRegex = /^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/i;
         if (!nameRegex.test(domainName)) {
             showError('Bitte geben Sie einen gültigen Domain-Namen ein (z.B. example)');
             return;
         }
        
        // Button deaktivieren und Loading anzeigen
        checkButton.disabled = true;
        checkButton.innerHTML = '<i class="bi bi-hourglass-split"></i> <?= t('checking_availability') ?>';
        
        // Ergebnis-Container leeren und Loading anzeigen
        resultContainer.innerHTML = `
            <div class="mt-3">
                <div class="alert alert-info">
                    <i class="bi bi-hourglass-split"></i> 
                    <strong>Verfügbarkeit wird geprüft...</strong><br>
                    <small>Dies kann einige Sekunden dauern.</small>
                </div>
            </div>
        `;
        
                 // AJAX-Request
         $.ajax({
             url: 'domain-registration.php',
             method: 'POST',
             data: {
                 action: 'check_domain_ajax',
                 domainName: domainName,
                 domainExtension: domainExtension
             },
            success: function(response) {
                // jQuery automatically parses JSON when Content-Type is application/json
                // So response is already an object, no need to parse
                if (typeof response === 'object' && response !== null) {
                    displayAvailabilityResult(response);
                } else {
                    console.error('Unexpected response format:', response);
                    showError('Unerwartetes Antwortformat. Bitte versuchen Sie es erneut.');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                if (xhr.status === 429) {
                    showError('Zu viele Anfragen. Bitte warten Sie einen Moment.');
                } else if (xhr.status === 0) {
                    showError('Netzwerkfehler. Bitte überprüfen Sie Ihre Internetverbindung.');
                } else {
                    showError('Fehler bei der Verfügbarkeitsprüfung. Bitte versuchen Sie es später erneut.');
                }
            },
            complete: function() {
                // Button wieder aktivieren
                checkButton.disabled = false;
                checkButton.innerHTML = '<i class="bi bi-search"></i> <?= t('check_availability') ?>';
            }
        });
    }
    
         function displayAvailabilityResult(data) {
         const resultContainer = document.getElementById('availabilityResult');
         
         if (data.error) {
             resultContainer.innerHTML = `
                 <div class="alert alert-danger mt-3">
                     <i class="bi bi-exclamation-triangle"></i> ${data.error}
                 </div>
             `;
             return;
         }
         
         if (data.allTLDs) {
             // Alle TLDs Ergebnis anzeigen
             displayAllTLDsResult(data);
             return;
         }
         
         const isAvailable = data.available;
         const alertClass = isAvailable ? 'success' : 'warning';
         const icon = isAvailable ? 'check-circle' : 'exclamation-triangle';
         const message = isAvailable ? '<?= t('domain_available_message') ?>' : '<?= t('domain_not_available_message') ?>';
         
         // Add method information if available
         const methodInfo = data.method ? `<br><small class="text-muted">Checked via: ${data.method}</small>` : '';
         
         let html = `
             <div class="alert alert-${alertClass} mt-3">
                 <h6><i class="bi bi-${icon}"></i> ${data.domain}</h6>
                 <p class="mb-0">${message}${methodInfo}</p>
             </div>
         `;
         
         // Alternative Domains anzeigen (nur verfügbare)
         if (data.alternatives && data.alternatives.length > 0) {
             const availableAlternatives = data.alternatives.filter(alt => {
                 // Hier würden wir die Verfügbarkeit der Alternativen prüfen
                 // Für den Moment zeigen wir alle an
                 return true;
             });
             
             if (availableAlternatives.length > 0) {
                 html += `
                     <div class="mt-3">
                         <h6><?= t('alternative_domains') ?>:</h6>
                         <div class="row">
                 `;
                 
                 availableAlternatives.forEach(altDomain => {
                     html += `
                         <div class="col-md-3 mb-2">
                             <button class="btn btn-outline-secondary btn-sm w-100" 
                                     onclick="checkAlternativeDomain('${altDomain}')">
                                 ${altDomain}
                             </button>
                         </div>
                     `;
                 });
                 
                 html += `
                         </div>
                     </div>
                 `;
             }
         }
         
         // Registrierungsbutton für verfügbare Domains
         if (isAvailable) {
             html += `
                 <div class="mt-3">
                     <button class="btn btn-success" onclick="showRegistrationForm('${data.domain}')">
                         <i class="bi bi-plus-circle"></i> <?= t('register_this_domain') ?>
                     </button>
                 </div>
             `;
         }
         
         resultContainer.innerHTML = html;
     }
     
     function displayAllTLDsResult(data) {
         const resultContainer = document.getElementById('availabilityResult');
         
         let html = `
             <div class="alert alert-info mt-3">
                 <h6><i class="bi bi-globe"></i> ${data.domainName} - Alle TLDs geprüft</h6>
                 <p class="mb-0">Hier sind die Ergebnisse für alle verfügbaren Domain-Endungen:</p>
             </div>
         `;
         
         // Verfügbare und nicht verfügbare Domains gruppieren
         const available = data.results.filter(r => r.available);
         const unavailable = data.results.filter(r => !r.available);
         
         if (available.length > 0) {
             html += `
                 <div class="mt-3">
                     <h6 class="text-success"><i class="bi bi-check-circle"></i> Verfügbare Domains:</h6>
                     <div class="row">
             `;
             
             available.forEach(result => {
                 html += `
                     <div class="col-md-4 mb-2">
                         <div class="card border-success">
                             <div class="card-body p-2">
                                 <h6 class="card-title mb-1">${result.domain}</h6>
                                 <small class="text-muted">${result.name}</small>
                                 <div class="mt-2">
                                     <button class="btn btn-success btn-sm w-100" 
                                             onclick="showRegistrationForm('${result.domain}')">
                                         <i class="bi bi-plus-circle"></i> Registrieren
                                     </button>
                                 </div>
                             </div>
                         </div>
                     </div>
                 `;
             });
             
             html += `
                     </div>
                 </div>
             `;
         }
         
         if (unavailable.length > 0) {
             html += `
                 <div class="mt-3">
                     <h6 class="text-warning"><i class="bi bi-exclamation-triangle"></i> Nicht verfügbare Domains:</h6>
                     <div class="row">
             `;
             
             unavailable.forEach(result => {
                 html += `
                     <div class="col-md-4 mb-2">
                         <div class="card border-warning">
                             <div class="card-body p-2">
                                 <h6 class="card-title mb-1">${result.domain}</h6>
                                 <small class="text-muted">${result.name}</small>
                                 <small class="text-muted d-block">${result.message}</small>
                             </div>
                         </div>
                     </div>
                 `;
             });
             
             html += `
                     </div>
                 </div>
             `;
         }
         
         resultContainer.innerHTML = html;
     }
    
    function showError(message) {
        const resultContainer = document.getElementById('availabilityResult');
        resultContainer.innerHTML = `
            <div class="alert alert-danger mt-3">
                <i class="bi bi-exclamation-triangle"></i> ${message}
            </div>
        `;
    }
    
         // Domain-Name Input Validierung (nur Format, kein Auto-Check)
     document.getElementById('domainName').addEventListener('input', function(e) {
         let value = e.target.value.toLowerCase();
         // Nur Buchstaben, Zahlen und Bindestriche erlauben (keine Punkte)
         value = value.replace(/[^a-z0-9-]/g, '');
         e.target.value = value;
     });
    
    // Form-Submit verhindern und AJAX verwenden
    document.getElementById('domainCheckForm').addEventListener('submit', function(e) {
        e.preventDefault();
        checkDomainAvailability();
    });
    </script>
</body>
</html>
