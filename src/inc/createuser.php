<?php
// AJAX-Request verhindern - diese Seite soll immer als normale HTML-Seite geladen werden
if (isset($_POST['action']) && !isset($_POST['createuser_form'])) {
    // Wenn es ein AJAX-Request ist, aber nicht von unserem Formular kommt, 
    // dann ignorieren wir es und zeigen die normale Seite
    $_POST = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
}

if (!isset($db)) {
    require_once dirname(__DIR__) . '/core/DatabaseManager.php';
    $db = DatabaseManager::getInstance();
}

// ServiceManager initialisieren
$serviceManager = new ServiceManager();

// Schritt-für-Schritt-Assistent Status
$currentStep = $_SESSION['create_user_step'] ?? 1;
$maxSteps = 4;

// POST-Verarbeitung für Benutzer-Erstellung
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['createuser_form'])) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'next_step':
                handleNextStep();
                break;
            case 'create_user':
                handleCreateUser($serviceManager, $db);
                break;
            case 'back_step':
                handleBackStep();
                break;
            default:
                echo '<div class="alert alert-danger">';
                echo '<strong>FEHLER:</strong> Unbekannte Aktion: ' . htmlspecialchars($_POST['action']);
                echo '</div>';
                break;
        }
    } else {
        echo '<div class="alert alert-danger">';
        echo '<strong>FEHLER:</strong> Keine Aktion angegeben';
        echo '</div>';
    }
}

function handleNextStep() {
    global $currentStep, $maxSteps;
    
    // Validierung für aktuellen Schritt
    $isValid = validateCurrentStep();
    
    if ($isValid) {
        $currentStep = min($currentStep + 1, $maxSteps);
        $_SESSION['create_user_step'] = $currentStep;
        
        // Zwischendaten speichern
        if ($currentStep < $maxSteps) {
            $_SESSION['user_data'] = array_merge($_SESSION['user_data'] ?? [], $_POST);
        }
    }
}

function handleBackStep() {
    global $currentStep;
    $currentStep = max($currentStep - 1, 1);
    $_SESSION['create_user_step'] = $currentStep;
}

function validateCurrentStep() {
    global $currentStep;
    
    switch ($currentStep) {
        case 1: // System-Auswahl
            if (!isset($_POST['systems']) || empty($_POST['systems'])) {
                echo '<div class="alert alert-danger">Bitte wählen Sie mindestens ein System aus.</div>';
                return false;
            }
            break;
            
        case 2: // Grunddaten
            $required = ['username', 'email', 'password', 'full_name'];
            foreach ($required as $field) {
                if (empty($_POST[$field])) {
                    echo '<div class="alert alert-danger">Bitte füllen Sie alle Pflichtfelder aus.</div>';
                    return false;
                }
            }
            
            // E-Mail-Validierung
            if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                echo '<div class="alert alert-danger">Bitte geben Sie eine gültige E-Mail-Adresse ein.</div>';
                return false;
            }
            
            // Passwort-Stärke prüfen
            if (strlen($_POST['password']) < 8) {
                echo '<div class="alert alert-danger">Das Passwort muss mindestens 8 Zeichen lang sein.</div>';
                return false;
            }
            
            // ISPConfig Passwort-Stärke prüfen
            if (!preg_match('/(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*\W)/', $_POST['password'])) {
                echo '<div class="alert alert-danger">Das Passwort muss mindestens einen Großbuchstaben, einen Kleinbuchstaben, eine Zahl und ein Sonderzeichen enthalten (ISPConfig-Anforderung).</div>';
                return false;
            }
            break;
            
        case 3: // System-spezifische Einstellungen
            // Hier können system-spezifische Validierungen hinzugefügt werden
            break;
    }
    
    return true;
}

// Hilfsfunktion für Benutzer-Erstellung
function handleCreateUser($serviceManager, $db) {
    try {
        // Alle gespeicherten Daten zusammenführen
        $userData = $_SESSION['user_data'] ?? [];
        $userData = array_merge($userData, $_POST);
        
        $username = $userData['username'] ?? '';
        $email = $userData['email'] ?? '';
        $password = $userData['password'] ?? '';
        $fullName = $userData['full_name'] ?? '';
        $systems = $userData['systems'] ?? [];
        
        // Vollname in Vor- und Nachname aufteilen
        $nameParts = explode(' ', $fullName, 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';
        
        // Schritt 1: Validierung
        
        if (empty($username) || empty($email) || empty($password) || empty($fullName)) {
            echo '<div class="alert alert-danger">FEHLER: Pflichtfelder nicht ausgefüllt</div>';
            return;
        }
        
        // Prüfen ob Benutzer bereits existiert
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $db->execute($stmt, [$username, $email]);
        $existingUser = $db->fetch($stmt);
        
        if ($existingUser) {
            echo '<div class="alert alert-danger">FEHLER: Benutzer bereits existiert</div>';
            return;
        }
        
        // Schritt 2: Benutzer in lokaler Datenbank anlegen
        
        $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, full_name, role, active, created_at) VALUES (?, ?, ?, ?, 'user', 'y', NOW())");
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $db->execute($stmt, [$username, $email, $passwordHash, $fullName]);
        $userId = $db->lastInsertId();
        
        $successMessages = [];
        $errorMessages = [];
        
        // Schritt 3: Benutzer in externen Systemen anlegen
        foreach ($systems as $system) {
            
            try {
                $systemUserId = null;
                $systemOptions = [];
                
                // System-spezifische Optionen sammeln
                switch ($system) {
                    case 'ogp':
                        // OGP verwendet: email, name, password
                        $systemOptions['email'] = $email;
                        
                        $result = $serviceManager->createOGPUser($username, $password, $firstName, $lastName, $systemOptions);
                        // OGP gibt "Account created" bei Erfolg zurück - das ist ein Erfolg!
                        if ($result && (isset($result['success']) && $result['success']) || (is_string($result) && strpos($result, 'Account created') !== false)) {
                            $systemUserId = $username; // OGP verwendet den Benutzernamen als ID
                        } else {
                            throw new Exception('Fehler beim Anlegen des OGP-Benutzers: ' . ($result['message'] ?? 'Unbekannter Fehler'));
                        }
                        break;
                        
                    case 'proxmox':
                        // Proxmox verwendet: userid (username@pve), email, firstname, lastname, password
                        // Sammle alle verfügbaren Proxmox-spezifischen Parameter
                        $systemOptions['email'] = $email;
                        // Standardwerte setzen, falls nicht in Schritt 3 eingegeben
                        $systemOptions['realm'] = $userData['proxmox_realm'] ?? 'pve';
                        $systemOptions['comment'] = $userData['proxmox_comment'] ?? 'Benutzer über API angelegt';
                        
                        // Proxmox-spezifische Daten für die API vorbereiten
                        $proxmoxData = [
                            'username' => $username,
                            'realm' => $systemOptions['realm'],
                            'email' => $systemOptions['email'],
                            'firstname' => $firstName,
                            'lastname' => $lastName,
                            'password' => $password,
                            'comment' => $systemOptions['comment']
                        ];
                        
                        $result = $serviceManager->createProxmoxUser($username, $password, $firstName, $lastName, $systemOptions);
                        if ($result && isset($result['success']) && $result['success']) {
                            $systemUserId = $username . '@' . $systemOptions['realm']; // Proxmox userid Format
                        } else {
                            throw new Exception('Fehler beim Anlegen des Proxmox-Benutzers: ' . ($result['message'] ?? 'Unbekannter Fehler'));
                        }
                        break;
                        
                    case 'ispconfig':
                        // ISPConfig verwendet die korrekten Parameter aus dem Test
                        $clientData = array(
                            // Must Have Parameter
                            'company_name' => $userData['ispconfig_company'] ?? '',
                            'contact_firstname' => $firstName,
                            'contact_name' => $lastName,
                            'email' => $email,
                            'default_mailserver' => $userData['ispconfig_default_mailserver'] ?? '1',
                            'default_webserver' => $userData['ispconfig_default_webserver'] ?? '1',
                            'ssh_chroot' => $userData['ispconfig_ssh_chroot'] ?? 'no,jailkit,ssh-chroot',
                            'default_dnsserver' => $userData['ispconfig_default_dnsserver'] ?? '1',
                            'default_dbserver' => $userData['ispconfig_default_dbserver'] ?? '1',
                            'username' => $username,
                            'password' => $password,
                            'language' => $userData['ispconfig_language'] ?? 'de',
                            'usertheme' => 'default',
                            'web_php_options' => $userData['ispconfig_web_php_options'] ?? 'no,fast-cgi,cgi,mod,suphp',
                            'limit_cron_type' => 'url',
                            
                            // Optional Parameter Mail-Server
                            'limit_maildomain' => intval($userData['ispconfig_limit_maildomain'] ?? -1),
                            'limit_mailbox' => intval($userData['ispconfig_limit_mailbox'] ?? -1),
                            'limit_mailalias' => intval($userData['ispconfig_limit_mailalias'] ?? -1),
                            'limit_mailaliasdomain' => intval($userData['ispconfig_limit_mailaliasdomain'] ?? -1),
                            'limit_mailforward' => intval($userData['ispconfig_limit_mailforward'] ?? -1),
                            'limit_mailcatchall' => intval($userData['ispconfig_limit_mailcatchall'] ?? -1),
                            'limit_mailrouting' => intval($userData['ispconfig_limit_mailrouting'] ?? 0),
                            'limit_mailfilter' => intval($userData['ispconfig_limit_mailfilter'] ?? -1),
                            'limit_fetchmail' => intval($userData['ispconfig_limit_fetchmail'] ?? -1),
                            'limit_mailquota' => intval($userData['ispconfig_limit_mailquota'] ?? -1),
                            'limit_spamfilter_wblist' => intval($userData['ispconfig_limit_spamfilter_wblist'] ?? -1),
                            'limit_spamfilter_user' => intval($userData['ispconfig_limit_spamfilter_user'] ?? -1),
                            'limit_spamfilter_policy' => intval($userData['ispconfig_limit_spamfilter_policy'] ?? -1),
                            
                            // Optional Parameter WebServer
                            'limit_web_ip' => $userData['ispconfig_limit_web_ip'] ?? '',
                            'limit_web_domain' => intval($userData['ispconfig_limit_web_domain'] ?? -1),
                            'limit_web_quota' => intval($userData['ispconfig_limit_web_quota'] ?? -1),
                            'limit_web_subdomain' => intval($userData['ispconfig_limit_web_subdomain'] ?? -1),
                            'limit_web_aliasdomain' => intval($userData['ispconfig_limit_web_aliasdomain'] ?? -1),
                            'limit_ftp_user' => intval($userData['ispconfig_limit_ftp_user'] ?? -1),
                            'limit_shell_user' => intval($userData['ispconfig_limit_shell_user'] ?? 0),
                            'limit_webdav_user' => intval($userData['ispconfig_limit_webdav_user'] ?? 0),
                            
                            // Optional Parameter DNSServer
                            'limit_dns_zone' => intval($userData['ispconfig_limit_dns_zone'] ?? -1),
                            'limit_dns_slave_zone' => intval($userData['ispconfig_limit_dns_slave_zone'] ?? -1),
                            'limit_dns_record' => intval($userData['ispconfig_limit_dns_record'] ?? -1),
                            
                            // Optional Parameter Database
                            'limit_database' => intval($userData['ispconfig_limit_database'] ?? -1),
                            
                            // Optional Parameter CronJobs
                            'limit_cron' => intval($userData['ispconfig_limit_cron'] ?? 0),
                            'limit_cron_frequency' => intval($userData['ispconfig_limit_cron_frequency'] ?? 5),
                            'limit_traffic_quota' => intval($userData['ispconfig_limit_traffic_quota'] ?? -1),
                            
                            // Optional Parameter Stuff
                            'limit_client' => 0, // If this value is > 0, then the client is a reseller
                            'parent_client_id' => 0,
                            
                            // Optional Parameter Templates
                            'template_master' => 0,
                            'template_additional' => '',
                            'created_at' => 0,
                            
                            // Optional Parameter Options
                            'limit_redis_instances' => 0,
                            'limit_redis_memory_per_instance' => 0,
                            'limit_redis_memory_total' => 0,
                            'limit_allow_docker_apps' => 'y',
                            'limit_allow_docker_databases' => 'y'
                        );
                        
                        // Zusätzliche Kontaktdaten hinzufügen, falls verfügbar
                        if (!empty($userData['ispconfig_phone'])) {
                            $clientData['contact_phone'] = $userData['ispconfig_phone'];
                        }
                        if (!empty($userData['ispconfig_mobile'])) {
                            $clientData['contact_mobile'] = $userData['ispconfig_mobile'];
                        }
                        if (!empty($userData['ispconfig_fax'])) {
                            $clientData['contact_fax'] = $userData['ispconfig_fax'];
                        }
                        if (!empty($userData['ispconfig_street'])) {
                            $clientData['contact_street'] = $userData['ispconfig_street'];
                        }
                        if (!empty($userData['ispconfig_zip'])) {
                            $clientData['contact_zip'] = $userData['ispconfig_zip'];
                        }
                        if (!empty($userData['ispconfig_city'])) {
                            $clientData['contact_city'] = $userData['ispconfig_city'];
                        }
                        if (!empty($userData['ispconfig_state'])) {
                            $clientData['contact_state'] = $userData['ispconfig_state'];
                        }
                        if (!empty($userData['ispconfig_country'])) {
                            $clientData['contact_country'] = $userData['ispconfig_country'];
                        }
                        if (!empty($userData['ispconfig_customer_no'])) {
                            $clientData['customer_no'] = $userData['ispconfig_customer_no'];
                        }
                        if (!empty($userData['ispconfig_vat_id'])) {
                            $clientData['vat_id'] = $userData['ispconfig_vat_id'];
                        }
                        
                        $result = $serviceManager->createISPConfigClient($clientData);
                        if ($result && is_numeric($result)) {
                            $systemUserId = $result; // ISPConfig gibt die Client-ID zurück
                        } else {
                            throw new Exception('Fehler beim Anlegen des ISPConfig-Benutzers: ' . (is_array($result) ? json_encode($result) : $result));
                        }
                        break;
                }
                
                if ($systemUserId) {
                    // Verknüpfung in user_permissions speichern
                    $stmt = $db->prepare("INSERT INTO user_permissions (user_id, permission_type, resource_id, created_at) VALUES (?, ?, ?, NOW())");
                    $db->execute($stmt, [$userId, $system, $systemUserId]);
                    
                    $successMessages[] = ucfirst($system) . ": Benutzer erfolgreich angelegt (ID: $systemUserId)";
                    
                } else {
                    $errorMessages[] = ucfirst($system) . ": Fehler beim Anlegen des Benutzers";
                }
                
            } catch (Exception $e) {
                $errorMessages[] = ucfirst($system) . ": " . $e->getMessage();
            }
        }
        
        // Abschlussbericht
        
        if (!empty($successMessages)) {
            echo '<div class="alert alert-success"><strong>ERFOLG:</strong> ' . implode(", ", $successMessages) . '</div>';
        }
        
        if (!empty($errorMessages)) {
            echo '<div class="alert alert-warning"><strong>WARNUNG:</strong> ' . implode(", ", $errorMessages) . '</div>';
        }
        
        // Session-Daten zurücksetzen
        unset($_SESSION['create_user_step']);
        unset($_SESSION['user_data']);
        
        echo '<div class="alert alert-success"><strong>Benutzer-Erstellung abgeschlossen!</strong></div>';
        
    } catch (Exception $e) {
        echo '<div class="alert alert-danger"><strong>KRITISCHER FEHLER:</strong> ' . $e->getMessage() . '</div>';
    }
}

function verifyUserInSystem($serviceManager, $system, $username) {
    try {
        switch ($system) {
            case 'ogp':
                $users = $serviceManager->getOGPUsers();
                if (is_array($users) && isset($users['message'])) {
                    foreach ($users['message'] as $user) {
                        if (($user['users_login'] ?? '') === $username || ($user['username'] ?? '') === $username) {
                            return true;
                        }
                    }
                }
                break;
                
            case 'proxmox':
                $users = $serviceManager->getProxmoxUsers();
                if (is_array($users) && isset($users['data'])) {
                    foreach ($users['data'] as $user) {
                        if (($user['userid'] ?? '') === $username || ($user['username'] ?? '') === $username) {
                            return true;
                        }
                    }
                }
                break;
                
            case 'ispconfig':
                $users = $serviceManager->getISPConfigClients();
                if (is_array($users)) {
                    foreach ($users as $user) {
                        if (($user['username'] ?? '') === $username || ($user['name'] ?? '') === $username) {
                            return true;
                        }
                    }
                }
                break;
        }
        
        return false;
    } catch (Exception $e) {
        echo '<div class="alert alert-warning">Verifikationsfehler in ' . $system . ': ' . $e->getMessage() . '</div>';
        return false;
    }
}
?>

<?php
// Session-Meldungen ausgeben
if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' . $_SESSION['success_message'] . '
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>';
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . $_SESSION['error_message'] . '
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>';
    unset($_SESSION['error_message']);
}
if (isset($_SESSION['warning_message'])) {
    echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">' . $_SESSION['warning_message'] . '
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>';
    unset($_SESSION['warning_message']);
}
if (isset($_SESSION['info_message'])) {
    echo '<div class="alert alert-info alert-dismissible fade show" role="alert">' . $_SESSION['info_message'] . '
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>';
    unset($_SESSION['info_message']);
}
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person-plus"></i> <?= t('create_user') ?> - Schritt <?= $currentStep ?> von <?= $maxSteps ?></h5>
            </div>
            <div class="card-body">
                <!-- Fortschrittsbalken -->
                <div class="progress mb-4" style="height: 25px;">
                    <?php for ($i = 1; $i <= $maxSteps; $i++): ?>
                        <div class="progress-bar <?= $i <= $currentStep ? 'bg-primary' : 'bg-secondary' ?>" 
                             style="width: <?= 100/$maxSteps ?>%">
                            Schritt <?= $i ?>
                        </div>
                    <?php endfor; ?>
                </div>

                <form id="createUserForm" method="post">
                    <input type="hidden" name="createuser_form" value="1">
                    <?php if ($currentStep == 1): ?>
                        <!-- Schritt 1: System-Auswahl -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-gear"></i> <?= t('system_selection') ?></h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="system_ogp" name="systems[]" value="ogp">
                                            <label class="form-check-label" for="system_ogp">
                                                <i class="bi bi-controller"></i> <?= t('opengamepanel') ?>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="system_ispconfig" name="systems[]" value="ispconfig">
                                            <label class="form-check-label" for="system_ispconfig">
                                                <i class="bi bi-globe"></i> ISPConfig
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="system_proxmox" name="systems[]" value="proxmox">
                                            <label class="form-check-label" for="system_proxmox">
                                                <i class="bi bi-server"></i> Proxmox
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php elseif ($currentStep == 2): ?>
                        <!-- Schritt 2: Grundlegende Benutzerdaten -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-person"></i> <?= t('basic_user_data') ?></h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="username" class="form-label"><?= t('username') ?> *</label>
                                        <input type="text" class="form-control" id="username" name="username" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label"><?= t('email') ?> *</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="full_name" class="form-label"><?= t('full_name') ?> *</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="password" class="form-label"><?= t('password') ?> *</label>
                                        <input type="password" class="form-control" id="password" name="password" required minlength="8" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*\W).{8,}">
                                        <small class="form-text text-muted">
                                            Das Passwort muss mindestens 8 Zeichen lang sein und mindestens einen Großbuchstaben, einen Kleinbuchstaben, eine Zahl und ein Sonderzeichen enthalten (ISPConfig-Anforderung).
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php elseif ($currentStep == 3): ?>
                        <!-- Schritt 3: System-spezifische Einstellungen -->
                        <?php 
                        $selectedSystems = $_SESSION['user_data']['systems'] ?? [];
                        
                        foreach ($selectedSystems as $system): 
                        ?>
                            <?php if ($system == 'ogp'): ?>
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="bi bi-controller"></i> <?= t('opengamepanel') ?> <?= t('settings') ?></h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle"></i> 
                                            <strong><?= t('ogp_info') ?>:</strong> 
                                            <?= t('ogp_user_info') ?>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="ogp_expiration" class="form-label"><?= t('expiration_date') ?></label>
                                                <input type="datetime-local" class="form-control" id="ogp_expiration" name="ogp_expiration">
                                                <small class="form-text text-muted"><?= t('ogp_expiration_help') ?></small>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="ogp_home_id" class="form-label"><?= t('home_id') ?></label>
                                                <input type="number" class="form-control" id="ogp_home_id" name="ogp_home_id" min="1">
                                                <small class="form-text text-muted"><?= t('ogp_home_id_help') ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($system == 'proxmox'): ?>
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="bi bi-server"></i> Proxmox <?= t('settings') ?></h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle"></i> 
                                            <strong><?= t('proxmox_info') ?>:</strong> 
                                            <?= t('proxmox_user_info') ?>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="proxmox_realm" class="form-label"><?= t('realm') ?> *</label>
                                                <select class="form-select" id="proxmox_realm" name="proxmox_realm" required>
                                                    <option value=""><?= t('select_realm') ?></option>
                                                    <option value="pam">PAM (Linux)</option>
                                                    <option value="pve" selected>PVE (Proxmox VE)</option>
                                                    <option value="pbs">PBS (Proxmox Backup Server)</option>
                                                </select>
                                                <small class="form-text text-muted"><?= t('proxmox_realm_help') ?></small>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="proxmox_comment" class="form-label"><?= t('comment') ?></label>
                                                <input type="text" class="form-control" id="proxmox_comment" name="proxmox_comment">
                                                <small class="form-text text-muted"><?= t('proxmox_comment_help') ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($system == 'ispconfig'): ?>
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="bi bi-globe"></i> ISPConfig <?= t('settings') ?></h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle"></i> 
                                            <strong><?= t('ispconfig_info') ?>:</strong> 
                                            <?= t('ispconfig_user_info') ?>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="ispconfig_company" class="form-label"><?= t('company') ?></label>
                                                <input type="text" class="form-control" id="ispconfig_company" name="ispconfig_company" value="Orga Consult">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="ispconfig_phone" class="form-label"><?= t('phone') ?></label>
                                                <input type="text" class="form-control" id="ispconfig_phone" name="ispconfig_phone" value="017655850539">
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="ispconfig_mobile" class="form-label"><?= t('mobile') ?></label>
                                                <input type="text" class="form-control" id="ispconfig_mobile" name="ispconfig_mobile" value="017655850539">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="ispconfig_fax" class="form-label"><?= t('fax') ?></label>
                                                <input type="text" class="form-control" id="ispconfig_fax" name="ispconfig_fax" value="03012345678">
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="ispconfig_street" class="form-label"><?= t('street') ?></label>
                                                <input type="text" class="form-control" id="ispconfig_street" name="ispconfig_street" value="Musterstraße 123">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="ispconfig_zip" class="form-label"><?= t('zip') ?></label>
                                                <input type="text" class="form-control" id="ispconfig_zip" name="ispconfig_zip" value="10115">
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label for="ispconfig_city" class="form-label"><?= t('city') ?></label>
                                                <input type="text" class="form-control" id="ispconfig_city" name="ispconfig_city" value="Berlin">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="ispconfig_state" class="form-label"><?= t('state') ?></label>
                                                <input type="text" class="form-control" id="ispconfig_state" name="ispconfig_state" value="Berlin">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="ispconfig_country" class="form-label"><?= t('country') ?></label>
                                                <select class="form-select" id="ispconfig_country" name="ispconfig_country">
                                                    <option value="DE" selected>Deutschland</option>
                                                    <option value="AT">Österreich</option>
                                                    <option value="CH">Schweiz</option>
                                                    <option value="GB">Großbritannien</option>
                                                    <option value="US">USA</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="ispconfig_language" class="form-label"><?= t('language') ?></label>
                                                <select class="form-select" id="ispconfig_language" name="ispconfig_language">
                                                    <option value="de" selected>Deutsch</option>
                                                    <option value="en">English</option>
                                                    <option value="fr">Français</option>
                                                    <option value="es">Español</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="ispconfig_customer_no" class="form-label">Kundennummer</label>
                                                <input type="text" class="form-control" id="ispconfig_customer_no" name="ispconfig_customer_no" value="CUST<?= time() ?>">
                                                <small class="form-text text-muted">Eindeutige Kundennummer (wird automatisch generiert)</small>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="ispconfig_vat_id" class="form-label">VAT-ID</label>
                                                <input type="text" class="form-control" id="ispconfig_vat_id" name="ispconfig_vat_id" value="VAT<?= time() ?>">
                                                <small class="form-text text-muted">Eindeutige VAT-ID (wird automatisch generiert)</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>

                    <?php elseif ($currentStep == 4): ?>
                        <!-- Schritt 4: Zusammenfassung und Bestätigung -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-check-circle"></i> Zusammenfassung</h6>
                            </div>
                            <div class="card-body">
                                <?php 
                                $userData = $_SESSION['user_data'] ?? [];
                                $userData = array_merge($userData, $_POST);
                                ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Grunddaten:</h6>
                                        <p><strong>Benutzername:</strong> <?= htmlspecialchars($userData['username'] ?? '') ?></p>
                                        <p><strong>E-Mail:</strong> <?= htmlspecialchars($userData['email'] ?? '') ?></p>
                                        <p><strong>Vollname:</strong> <?= htmlspecialchars($userData['full_name'] ?? '') ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Ausgewählte Systeme:</h6>
                                        <ul>
                                            <?php foreach ($userData['systems'] ?? [] as $system): ?>
                                                <li><?= ucfirst($system) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <strong>Achtung:</strong> Nach der Bestätigung wird der Benutzer in allen ausgewählten Systemen angelegt.
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Navigation Buttons -->
                    <div class="d-flex justify-content-between">
                        <?php if ($currentStep > 1): ?>
                            <button type="submit" name="action" value="back_step" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Zurück
                            </button>
                        <?php else: ?>
                            <div></div>
                        <?php endif; ?>

                        <?php if ($currentStep < $maxSteps): ?>
                            <button type="submit" name="action" value="next_step" class="btn btn-primary">
                                Weiter <i class="bi bi-arrow-right"></i>
                            </button>
                        <?php else: ?>
                            <button type="submit" name="action" value="create_user" class="btn btn-success">
                                <i class="bi bi-person-plus"></i> Benutzer erstellen
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript-Code wurde in assets/inc-js/createuser.js ausgelagert -->
