<?php
/**
 * Server Management Framework
 * 
 * @author Teris
 * @version 3.1.2
 */
if (!isset($db)) {
    require_once dirname(__DIR__) . '/core/DatabaseManager.php';
    $db = DatabaseManager::getInstance();
}

// ServiceManager initialisieren
$serviceManager = new ServiceManager();

// Kunden abrufen
try {
    $customers = [];
    $stmt = $db->query("SELECT * FROM customers ORDER BY created_at DESC");
    $customers = $db->fetchAll($stmt);
} catch (Exception $e) {
    $customers = [];
    error_log("Fehler beim Laden der Kunden: " . $e->getMessage());
}

// Aktionsverarbeitung für Kundenaktivierung
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'activate_customer') {
    $customerId = (int)($_POST['customer_id'] ?? 0);
    
    if ($customerId > 0) {
        try {
            // Kundendaten abrufen
            $stmt = $db->prepare("SELECT * FROM customers WHERE id = ? AND status = 'pending'");
            $stmt->execute([$customerId]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($customer) {
                // Konto aktivieren
                $stmt = $db->prepare("UPDATE customers SET status = 'active', email_verified_at = NOW(), updated_at = NOW() WHERE id = ?");
                $result = $stmt->execute([$customerId]);
                
                if ($result) {
                    // Benutzerkonten in allen Systemen erstellen
                    try {
                        // Benutzername aus E-Mail generieren
                        $username = strtolower(explode('@', $customer['email'])[0]);
                        
                        // Neues Passwort generieren
                        $newPassword = bin2hex(random_bytes(8)); // 16 Zeichen
                        
                        // Passwort in der Datenbank aktualisieren
                        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                        $stmt = $db->prepare("UPDATE customers SET password_hash = ? WHERE id = ?");
                        $stmt->execute([$passwordHash, $customerId]);
                        
                        // Benutzer in allen Systemen erstellen
                        $creationResult = $serviceManager->createUserInAllSystems(
                            $username,
                            $newPassword,
                            $customer['first_name'],
                            $customer['last_name'],
                            [
                                'email' => $customer['email'],
                                'company' => $customer['company'] ?? '',
                                'phone' => $customer['phone'] ?? ''
                            ]
                        );
                        
                        if ($creationResult['success']) {
                            // Erfolgreiche Systemerstellung loggen
                            $db->logAction(
                                'Admin Customer Activation',
                                "Kunde {$customer['email']} erfolgreich aktiviert und in allen Systemen angelegt: " . implode(', ', array_keys($creationResult['results'])),
                                'success'
                            );
                            
                            // E-Mail mit Anmeldedaten senden
                            sendSystemCredentialsEmail($customer['email'], $customer['first_name'], $username, $newPassword, $creationResult['results']);
                            
                            $_SESSION['success_message'] = "Kunde {$customer['email']} erfolgreich aktiviert und Systemkonten angelegt.";
                        } else {
                            $_SESSION['warning_message'] = "Kunde aktiviert, aber Fehler beim Anlegen der Systemkonten: " . json_encode($creationResult['errors']);
                            
                            // Fehler loggen
                            $db->logAction(
                                'Admin Customer Activation',
                                "Kunde {$customer['email']} aktiviert, aber Fehler bei Systemkonten: " . json_encode($creationResult['errors']),
                                'error'
                            );
                        }
                        
                    } catch (Exception $e) {
                        $_SESSION['warning_message'] = "Kunde aktiviert, aber Fehler beim Anlegen der Systemkonten: " . $e->getMessage();
                        error_log("System User Creation Error: " . $e->getMessage());
                        
                        // Fehler loggen
                        $db->logAction(
                            'Admin Customer Activation',
                            "Exception beim Anlegen der Systemkonten für Kunde {$customer['email']}: " . $e->getMessage(),
                            'error'
                        );
                    }
                    
                } else {
                    $_SESSION['error_message'] = "Fehler bei der Kontoaktivierung.";
                }
            } else {
                $_SESSION['error_message'] = "Kunde nicht gefunden oder bereits aktiviert.";
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Fehler: " . $e->getMessage();
            error_log("Customer Activation Error: " . $e->getMessage());
        }
        
        // Weiterleitung zur gleichen Seite
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}


// Benutzerdaten abrufen - Verwende direkte Datenbankabfrage statt adminCore
try {
    $adminUsers = [];
    $stmt = $db->query("SELECT * FROM users ORDER BY username");
    $adminUsers = $db->fetchAll($stmt);
} catch (Exception $e) {
    $adminUsers = [];
    error_log("Fehler beim Laden der Admin-Benutzer: " . $e->getMessage());
}

try {
    $ogpUsers = $serviceManager->getOGPUsers();
} catch (Exception $e) {
    $ogpUsers = [];
    error_log("Fehler beim Laden der OGP Benutzer: " . $e->getMessage());
}

try {
    $proxmoxUsers = $serviceManager->getProxmoxUsers();
} catch (Exception $e) {
    $proxmoxUsers = [];
    error_log("Fehler beim Laden der Proxmox Benutzer: " . $e->getMessage());
}

try {
    $ispconfigClients = $serviceManager->getISPConfigClients();
} catch (Exception $e) {
    $ispconfigClients = [];
    error_log("Fehler beim Laden der ISPConfig Benutzer: " . $e->getMessage());
}

// Fehlerbehandlung für API-Aufrufe
$apiErrors = [];

if (!is_array($ogpUsers)) {
    $apiErrors['ogp'] = $ogpUsers;
    $ogpUsers = [];
}
if (!is_array($proxmoxUsers)) {
    $apiErrors['proxmox'] = $proxmoxUsers;
    $proxmoxUsers = [];
}
if (!is_array($ispconfigClients)) {
    $apiErrors['ispconfig'] = $ispconfigClients;
    $ispconfigClients = [];
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
        error_log("Verifikationsfehler in $system: " . $e->getMessage());
        return false;
    }
}

function handleMergeUsers($serviceManager, $db) {
    try {
        $mainUserId = $_POST['main_user_id'];
        $systemUsers = $_POST['system_users'] ?? [];
        
        // Bestehende Benutzer verknüpfen (verwendet user_permissions Tabelle)
        foreach ($systemUsers as $system => $systemUserId) {
            if (!empty($systemUserId)) {
                $stmt = $db->prepare("INSERT INTO user_permissions (user_id, permission_type, resource_id, granted_by, created_at) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE resource_id = ?");
                $db->execute($stmt, [$mainUserId, $system, $systemUserId, $mainUserId, $systemUserId]);
            }
        }
        
        $_SESSION['success_message'] = "Benutzer erfolgreich zusammengeführt!";
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Fehler beim Zusammenführen: " . $e->getMessage();
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}

function handleLinkExistingUser($serviceManager, $db) {
    try {
        $username = $_POST['username'];
        $email = $_POST['email'];
        $fullName = $_POST['full_name'];
        $password = $_POST['password'];
        $systems = $_POST['systems'] ?? [];
        
        // Lokalen Benutzer anlegen
        $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, full_name, role, active, created_at) VALUES (?, ?, ?, ?, 'user', 'y', NOW())");
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $db->execute($stmt, [$username, $email, $passwordHash, $fullName]);
        $userId = $db->lastInsertId();
        
        // Bestehende System-Benutzer verknüpfen (verwendet user_permissions Tabelle)
        foreach ($systems as $system => $systemUserId) {
            if (!empty($systemUserId)) {
                $stmt = $db->prepare("INSERT INTO user_permissions (user_id, permission_type, resource_id, granted_by, created_at) VALUES (?, ?, ?, ?, NOW())");
                $db->execute($stmt, [$userId, $system, $systemUserId, $userId]);
            }
        }
        
        $_SESSION['success_message'] = "Bestehender Benutzer erfolgreich verknüpft!";
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Fehler beim Verknüpfen: " . $e->getMessage();
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}

function handleGrantAccess($serviceManager, $db) {
    try {
        $userId = $_POST['user_id'];
        $system = $_POST['system'];
        $username = $_POST['username'];
        $password = $_POST['password'];
        $fullName = $_POST['full_name'];
        $email = $_POST['email'];
        
        // Benutzer im ausgewählten System anlegen
        $systemUserId = null;
        switch ($system) {
            case 'ogp':
                $systemUserId = $serviceManager->createOGPUser($username, $password, $fullName, '', ['email' => $email]);
                break;
            case 'proxmox':
                $systemUserId = $serviceManager->createProxmoxUser($username, $password, $fullName, '', ['email' => $email]);
                break;
            case 'ispconfig':
                $systemUserId = $serviceManager->createISPConfigUser($username, $password, $fullName, '', ['email' => $email]);
                break;
        }
        
        if ($systemUserId) {
            // Überprüfen, ob der permission_type gültig ist
            $validTypes = ['proxmox', 'ispconfig', 'ovh', 'ogp', 'admin', 'readonly'];
            if (!in_array($system, $validTypes)) {
                error_log("Ungültiger permission_type: " . $system);
                $_SESSION['warning_message'] = "Ungültiger System-Typ: " . $system;
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit;
            }
            
            // Verknüpfung in user_permissions speichern
            try {
                $stmt = $db->prepare("INSERT INTO user_permissions (user_id, permission_type, resource_id, granted_by, created_at) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE resource_id = ?");
                $db->execute($stmt, [$userId, $system, $systemUserId, $userId, $systemUserId]);
            } catch (Exception $e) {
                // Falls 'ogp' nicht in der ENUM enthalten ist, verwende 'admin' als Fallback
                if (strpos($e->getMessage(), 'permission_type') !== false && $system === 'ogp') {
                    error_log("OGP permission_type nicht verfügbar, verwende 'admin' als Fallback");
                    $stmt = $db->prepare("INSERT INTO user_permissions (user_id, permission_type, resource_id, granted_by, created_at) VALUES (?, 'admin', ?, ?, NOW()) ON DUPLICATE KEY UPDATE resource_id = ?");
                    $db->execute($stmt, [$userId, $systemUserId, $userId, $systemUserId]);
                } else {
                    throw $e;
                }
            }
            
            $_SESSION['success_message'] = "Zugriff auf " . ucfirst($system) . " erfolgreich gewährt!";
        } else {
            $_SESSION['warning_message'] = "Benutzer konnte in " . ucfirst($system) . " nicht angelegt werden.";
        }
        
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Fehler beim Gewähren des Zugriffs: " . $e->getMessage();
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}

function handleRevokeAccess($serviceManager, $db) {
    try {
        $userId = $_POST['user_id'];
        $system = $_POST['system'];
        
        // Verknüpfung aus user_permissions entfernen
        $stmt = $db->prepare("DELETE FROM user_permissions WHERE user_id = ? AND permission_type = ?");
        $db->execute($stmt, [$userId, $system]);
        
        $_SESSION['success_message'] = "Zugriff auf " . ucfirst($system) . " erfolgreich entzogen!";
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Fehler beim Entziehen des Zugriffs: " . $e->getMessage();
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}

function handleEditUser($serviceManager, $db) {
    try {
        $userId = $_POST['user_id'];
        $username = $_POST['username'];
        $email = $_POST['email'];
        $fullName = $_POST['full_name'];
        $role = $_POST['role'];
        $active = $_POST['active'];
        
        // Lokalen Benutzer aktualisieren
        $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, role = ?, active = ? WHERE id = ?");
        $db->execute($stmt, [$username, $email, $fullName, $role, $active, $userId]);
        
        // System-Verknüpfungen abrufen
        $stmt = $db->prepare("SELECT permission_type, resource_id FROM user_permissions WHERE user_id = ?");
        $db->execute($stmt, [$userId]);
        $permissions = $db->fetchAll($stmt);
        
        // Passwort-Update in verknüpften Systemen
        if (!empty($_POST['password'])) {
            $password = $_POST['password'];
            
            foreach ($permissions as $permission) {
                $system = $permission['permission_type'];
                $systemUserId = $permission['resource_id'];
                
                switch ($system) {
                    case 'ogp':
                        // OGP Passwort-Update
                        $serviceManager->updateOGPUserPassword($systemUserId, $password);
                        break;
                    case 'proxmox':
                        // Proxmox Passwort-Update
                        $serviceManager->updateProxmoxUserPassword($systemUserId, $password);
                        break;
                    case 'ispconfig':
                        // ISPConfig Passwort-Update
                        $serviceManager->updateISPConfigUserPassword($systemUserId, $password);
                        break;
                }
            }
        }
        
        $_SESSION['success_message'] = "Benutzer erfolgreich aktualisiert!";
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Fehler beim Bearbeiten des Benutzers: " . $e->getMessage();
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}

function handleDeleteUser($serviceManager, $db) {
    try {
        $userId = $_POST['user_id'];
        $deleteFromSystems = $_POST['delete_from_systems'] ?? [];
        
        // System-Verknüpfungen abrufen
        $stmt = $db->prepare("SELECT permission_type, resource_id FROM user_permissions WHERE user_id = ?");
        $db->execute($stmt, [$userId]);
        $permissions = $db->fetchAll($stmt);
        
        // Benutzer aus verknüpften Systemen löschen
        foreach ($permissions as $permission) {
            $system = $permission['permission_type'];
            $systemUserId = $permission['resource_id'];
            
            // Nur löschen wenn explizit gewünscht
            if (in_array($system, $deleteFromSystems)) {
                switch ($system) {
                    case 'ogp':
                        $serviceManager->deleteOGPUser($systemUserId);
                        break;
                    case 'proxmox':
                        $serviceManager->deleteProxmoxUser($systemUserId);
                        break;
                    case 'ispconfig':
                        $serviceManager->deleteISPConfigClient($systemUserId);
                        break;
                }
            }
        }
        
        // Verknüpfungen aus user_permissions entfernen
        $stmt = $db->prepare("DELETE FROM user_permissions WHERE user_id = ?");
        $db->execute($stmt, [$userId]);
        
        // Lokalen Benutzer löschen
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $db->execute($stmt, [$userId]);
        
        $_SESSION['success_message'] = "Benutzer erfolgreich gelöscht!";
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Fehler beim Löschen des Benutzers: " . $e->getMessage();
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}

function handleUpdatePassword($serviceManager, $db) {
    try {
        $userId = $_POST['user_id'];
        $newPassword = $_POST['new_password'];
        $systems = $_POST['systems'] ?? [];
        
        // Lokales Passwort aktualisieren
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $db->execute($stmt, [$passwordHash, $userId]);
        
        // System-Verknüpfungen abrufen
        $stmt = $db->prepare("SELECT permission_type, resource_id FROM user_permissions WHERE user_id = ?");
        $db->execute($stmt, [$userId]);
        $permissions = $db->fetchAll($stmt);
        
        $updatedSystems = [];
        
        // Passwort in verknüpften Systemen aktualisieren
        foreach ($permissions as $permission) {
            $system = $permission['permission_type'];
            $systemUserId = $permission['resource_id'];
            
            // Nur aktualisieren wenn explizit gewünscht
            if (in_array($system, $systems)) {
                switch ($system) {
                    case 'ogp':
                        $result = $serviceManager->updateOGPUserPassword($systemUserId, $newPassword);
                        if ($result) $updatedSystems[] = 'OGP';
                        break;
                    case 'proxmox':
                        $result = $serviceManager->updateProxmoxUserPassword($systemUserId, $newPassword);
                        if ($result) $updatedSystems[] = 'Proxmox';
                        break;
                    case 'ispconfig':
                        $result = $serviceManager->updateISPConfigUserPassword($systemUserId, $newPassword);
                        if ($result) $updatedSystems[] = 'ISPConfig';
                        break;
                }
            }
        }
        
        if (!empty($updatedSystems)) {
            $_SESSION['success_message'] = "Passwort erfolgreich aktualisiert in: " . implode(', ', $updatedSystems);
        } else {
            $_SESSION['success_message'] = "Lokales Passwort erfolgreich aktualisiert!";
        }
        
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Fehler beim Aktualisieren des Passworts: " . $e->getMessage();
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}


?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="userTabs" role="tablist">
                        
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="list-tab" data-bs-toggle="tab" data-bs-target="#list" type="button" role="tab">
                                <i class="bi bi-people"></i> <?= t('user_list') ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="merge-tab" data-bs-toggle="tab" data-bs-target="#merge" type="button" role="tab">
                                <i class="bi bi-arrow-left-right"></i> Benutzer zusammenführen
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="link-tab" data-bs-toggle="tab" data-bs-target="#link" type="button" role="tab">
                                <i class="bi bi-arrow-left-right"></i> Bestehende Benutzer verknüpfen
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="customers-tab" data-bs-toggle="tab" data-bs-target="#customers" type="button" role="tab">
                                <i class="bi bi-people-fill"></i> Kundenverwaltung
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <!-- Session-Meldungen ausgeben -->
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= $_SESSION['success_message'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['success_message']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= $_SESSION['error_message'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['error_message']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['warning_message'])): ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <?= $_SESSION['warning_message'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['warning_message']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['info_message'])): ?>
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <?= $_SESSION['info_message'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['info_message']); ?>
                    <?php endif; ?>
                    
                    <div class="tab-content" id="userTabsContent">
                        <!-- Benutzerliste Tab -->
                        <div class="tab-pane fade show active" id="list" role="tabpanel">
                            <!-- API Fehler anzeigen -->
                            <?php if (!empty($apiErrors)): ?>
                                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                    <h5><i class="bi bi-exclamation-triangle"></i> API-Warnungen</h5>
                                    <ul class="mb-0">
                                        <?php foreach ($apiErrors as $system => $error): ?>
                                            <li><strong><?= ucfirst($system) ?>:</strong> 
                                                <?php if (is_string($error)): ?>
                                                    <?= htmlspecialchars($error) ?>
                                                <?php else: ?>
                                                    <?= htmlspecialchars(var_export($error, true)) ?>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Debug-Informationen -->
                            <div class="alert alert-info alert-dismissible fade show" role="alert">
                                <h6><i class="bi bi-info-circle"></i> API-Status</h6>
                                <ul class="mb-0">
                                    <li><strong>OpenGamePanel:</strong> <?= is_array($ogpUsers) ? count($ogpUsers) . ' Benutzer' : 'Fehler: ' . gettype($ogpUsers) ?></li>
                                    <li><strong>Proxmox:</strong> <?= is_array($proxmoxUsers) ? count($proxmoxUsers) . ' Benutzer' : 'Fehler: ' . gettype($proxmoxUsers) ?></li>
                                    <li><strong>ISPConfig:</strong> <?= is_array($ispconfigClients) ? count($ispconfigClients) . ' Benutzer' : 'Fehler: ' . gettype($ispconfigClients) ?></li>
                                </ul>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>

                            <!-- Admin Dashboard Benutzer -->
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="bi bi-speedometer2"></i> <?= t('admin_dashboard') ?> <?= t('users') ?></h6>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-outline-primary" onclick="loadUsers()">
                                            <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                                        </button>
                                        <button class="btn btn-sm btn-primary" onclick="showCreateUserModal()">
                                            <i class="bi bi-plus-circle"></i> <?= t('create_user') ?>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <!-- Filter und Suche -->
                                    <div class="row mb-3">
                                        <div class="col-md-3">
                                            <input type="text" class="form-control" id="userSearchInput" placeholder="<?= t('search_users') ?>" onkeyup="debounce(loadUsers, 500)()">
                                        </div>
                                        <div class="col-md-2">
                                            <select class="form-select" id="userStatusFilter" onchange="loadUsers()">
                                                <option value=""><?= t('all_statuses') ?></option>
                                                <option value="active"><?= t('active') ?></option>
                                                <option value="inactive"><?= t('inactive') ?></option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <select class="form-select" id="userRoleFilter" onchange="loadUsers()">
                                                <option value=""><?= t('all_roles') ?></option>
                                                <option value="admin"><?= t('admin') ?></option>
                                                <option value="user"><?= t('user') ?></option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <select class="form-select" id="userTypeFilter" onchange="loadUsers()">
                                                <option value=""><?= t('all_user_types') ?></option>
                                                <option value="admin"><?= t('admin_users') ?></option>
                                                <option value="customer"><?= t('customers') ?></option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover">
                                            <thead>
                                                <tr>
                                                    <th><?= t('username') ?></th>
                                                    <th><?= t('full_name') ?></th>
                                                    <th><?= t('email') ?></th>
                                                    <th><?= t('role') ?></th>
                                                    <th><?= t('user_type') ?></th>
                                                    <th><?= t('status') ?></th>
                                                    <th><?= t('created') ?></th>
                                                    <th><?= t('actions') ?></th>
                                                </tr>
                                            </thead>
                                            <tbody id="usersTableBody">
                                                <?php if (!empty($adminUsers)): ?>
                                                    <?php foreach ($adminUsers as $user): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($user['username']) ?></td>
                                                            <td><?= htmlspecialchars($user['full_name'] ?? '') ?></td>
                                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                                            <td><?= htmlspecialchars($user['role'] ?? '') ?></td>
                                                            <td><?= htmlspecialchars($user['role'] ?? 'user') ?></td>
                                                            <td>
                                                                <?php if (($user['active'] ?? '') === 'y'): ?>
                                                                    <span class="badge bg-success"><?= t('active') ?></span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-danger"><?= t('inactive') ?></span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><?= htmlspecialchars($user['created_at'] ?? '') ?></td>
                                                            <td>
                                                                <div class="btn-group" role="group">
                                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                            onclick="editUser(<?= $user['id'] ?>)">
                                                                        <i class="bi bi-pencil"></i>
                                                                    </button>
                                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                            onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                                                        <i class="bi bi-trash"></i>
                                                                    </button>
                                                                    <button type="button" class="btn btn-sm btn-outline-info" 
                                                                            onclick="viewUserDetails(<?= $user['id'] ?>)">
                                                                        <i class="bi bi-eye"></i>
                                                                    </button>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="8" class="text-center">
                                                            <div class="alert alert-info">
                                                                <i class="bi bi-info-circle"></i> <?= t('no_users_found') ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                                                         <!-- OGP Benutzer -->
                             <div class="card mb-4">
                                 <div class="card-header d-flex justify-content-between align-items-center">
                                     <h6 class="mb-0"><i class="bi bi-controller"></i> <?= t('opengamepanel') ?> <?= t('users') ?></h6>
                                     <button class="btn btn-sm btn-outline-primary" onclick="refreshUserList('ogp')">
                                         <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                                     </button>
                                 </div>
                                 <div class="card-body">
                                     <?php if (is_array($ogpUsers) && isset($ogpUsers['message']) && is_array($ogpUsers['message']) && count($ogpUsers['message']) > 0): ?>
                                         <div class="table-responsive">
                                             <table class="table table-striped table-hover" id="ogpUsersTable">
                                                 <thead>
                                                     <tr>
                                                         <th><?= t('email') ?></th>
                                                         <th><?= t('username') ?></th>
                                                         <th><?= t('expires') ?></th>
                                                         <th><?= t('actions') ?></th>
                                                     </tr>
                                                 </thead>
                                                 <tbody>
                                                     <?php foreach ($ogpUsers['message'] as $user): ?>
                                                         <tr data-user-id="<?= htmlspecialchars($user['users_email'] ?? '') ?>" data-system="ogp">
                                                             <td>
                                                                 <span class="user-email"><?= htmlspecialchars($user['users_email'] ?? '') ?></span>
                                                                 <input type="email" class="form-control form-control-sm edit-email" style="display:none;" value="<?= htmlspecialchars($user['users_email'] ?? '') ?>">
                                                             </td>
                                                             <td>
                                                                 <span class="user-username"><?= htmlspecialchars($user['users_login'] ?? '') ?></span>
                                                                 <input type="text" class="form-control form-control-sm edit-username" style="display:none;" value="<?= htmlspecialchars($user['users_login'] ?? '') ?>">
                                                             </td>
                                                             <td>
                                                                 <?php if (isset($user['user_expires']) && $user['user_expires'] && $user['user_expires'] !== 'X'): ?>
                                                                     <span class="badge bg-warning user-expires">
                                                                         <?= date('d.m.Y H:i', strtotime($user['user_expires'])) ?>
                                                                     </span>
                                                                 <?php else: ?>
                                                                     <span class="badge bg-success user-expires"><?= t('unlimited') ?></span>
                                                                 <?php endif; ?>
                                                                 <input type="datetime-local" class="form-control form-control-sm edit-expires" style="display:none;" value="<?= isset($user['user_expires']) && $user['user_expires'] && $user['user_expires'] !== 'X' ? date('Y-m-d\TH:i', strtotime($user['user_expires'])) : '' ?>">
                                                             </td>
                                                             <td>
                                                                 <div class="btn-group view-mode" role="group">
                                                                     <button class="btn btn-sm btn-outline-primary" onclick="startEditUser(this)">
                                                                         <i class="bi bi-pencil"></i>
                                                                     </button>
                                                                     <button class="btn btn-sm btn-outline-danger" onclick="deleteSystemUser('ogp', '<?= htmlspecialchars($user['users_email'] ?? '') ?>')">
                                                                         <i class="bi bi-trash"></i>
                                                                     </button>
                                                                 </div>
                                                                 <div class="btn-group edit-mode" role="group" style="display:none;">
                                                                     <button class="btn btn-sm btn-success" onclick="saveUserEdit(this)">
                                                                         <i class="bi bi-check"></i>
                                                                     </button>
                                                                     <button class="btn btn-sm btn-secondary" onclick="cancelUserEdit(this)">
                                                                         <i class="bi bi-x"></i>
                                                                     </button>
                                                                 </div>
                                                             </td>
                                                         </tr>
                                                     <?php endforeach; ?>
                                                 </tbody>
                                             </table>
                                         </div>
                                     <?php else: ?>
                                         <div class="alert alert-info">
                                             <i class="bi bi-info-circle"></i> <?= t('no_ogp_users_found') ?>
                                         </div>
                                     <?php endif; ?>
                                 </div>
                             </div>

                            <!-- Proxmox Benutzer -->
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="bi bi-server"></i> Proxmox <?= t('users') ?></h6>
                                    <button class="btn btn-sm btn-outline-primary" onclick="refreshUserList('proxmox')">
                                        <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                                    </button>
                                </div>
                                <div class="card-body">
                                    <?php if (is_array($proxmoxUsers) && isset($proxmoxUsers['data']) && is_array($proxmoxUsers['data']) && count($proxmoxUsers['data']) > 0): ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover">
                                                <thead>
                                                    <tr>
                                                        <th><?= t('userid') ?></th>
                                                        <th><?= t('Realm') ?></th>
                                                        <th><?= t('email') ?></th>
                                                        <th><?= t('actions') ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($proxmoxUsers['data'] as $user): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($user['userid'] ?? '') ?></td>
                                                            <td><?= htmlspecialchars($user['realm-type'] ?? '') ?></td>
                                                            <td><?= htmlspecialchars($user['email'] ?? '') ?></td>
                                                            <td>
                                                                <button class="btn btn-sm btn-outline-primary" onclick="editUser('proxmox', '<?= htmlspecialchars($user['userid'] ?? '') ?>')">
                                                                    <i class="bi bi-pencil"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteUser('proxmox', '<?= htmlspecialchars($user['userid'] ?? '') ?>')">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle"></i> <?= t('no_proxmox_users_found') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- ISPConfig Benutzer -->
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="bi bi-globe"></i> ISPConfig <?= t('users') ?></h6>
                                    <button class="btn btn-sm btn-outline-primary" onclick="refreshUserList('ispconfig')">
                                        <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                                    </button>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($ispconfigClients) && is_array($ispconfigClients)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th><?= t('username') ?></th>
                                                        <th><?= t('email') ?></th>
                                                        <th><?= t('status') ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($ispconfigClients as $user): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($user['username'] ?? $user['name'] ?? $user['user'] ?? 'Unbekannt') ?></td>
                                                            <td><?= htmlspecialchars($user['email'] ?? $user['mail'] ?? 'Keine E-Mail') ?></td>
                                                            <td><span class="badge bg-success"><?= t('active') ?></span></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle"></i> 
                                            <?php if (is_array($ispconfigClients)): ?>
                                                Keine ISPConfig Benutzer gefunden
                                            <?php else: ?>
                                                ISPConfig API Fehler: <?= htmlspecialchars(gettype($ispconfigClients)) ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Benutzer zusammenführen Tab -->
                        <div class="tab-pane fade" id="merge" role="tabpanel">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                <strong>Hinweis:</strong> Hier können Sie bestehende Benutzer aus verschiedenen Systemen zu einem Hauptbenutzer zusammenführen.
                            </div>
                            
                            <?php
                            // Benutzer-Zusammenführungsvorschläge generieren
                            $mergeSuggestions = [];
                            
                            // Nach E-Mail-Adressen gruppieren
                            $emailGroups = [];
                            
                            // OGP Benutzer nach E-Mail gruppieren
                            if (is_array($ogpUsers) && isset($ogpUsers['message'])) {
                                foreach ($ogpUsers['message'] as $user) {
                                    $email = '';
                                    if (isset($user['users_email']) && !empty($user['users_email'])) {
                                        $email = strtolower(trim($user['users_email']));
                                    } elseif (isset($user['email']) && !empty($user['email'])) {
                                        $email = strtolower(trim($user['email']));
                                    }
                                    
                                    if (!empty($email)) {
                                        if (!isset($emailGroups[$email])) {
                                            $emailGroups[$email] = [];
                                        }
                                        $emailGroups[$email]['ogp'] = $user;
                                    }
                                }
                            }
                            
                            // Proxmox Benutzer nach E-Mail gruppieren
                            if (is_array($proxmoxUsers) && isset($proxmoxUsers['data'])) {
                                foreach ($proxmoxUsers['data'] as $user) {
                                    $email = '';
                                    if (isset($user['email']) && !empty($user['email'])) {
                                        $email = strtolower(trim($user['email']));
                                    }
                                    
                                    if (!empty($email)) {
                                        if (!isset($emailGroups[$email])) {
                                            $emailGroups[$email] = [];
                                        }
                                        $emailGroups[$email]['proxmox'] = $user;
                                    }
                                }
                            }
                            
                            // ISPConfig Benutzer nach E-Mail gruppieren
                            if (is_array($ispconfigClients)) {
                                foreach ($ispconfigClients as $user) {
                                    $email = '';
                                    if (isset($user['email']) && !empty($user['email'])) {
                                        $email = strtolower(trim($user['email']));
                                    } elseif (isset($user['mail']) && !empty($user['mail'])) {
                                        $email = strtolower(trim($user['mail']));
                                    }
                                    
                                    if (!empty($email)) {
                                        if (!isset($emailGroups[$email])) {
                                            $emailGroups[$email] = [];
                                        }
                                        $emailGroups[$email]['ispconfig'] = $user;
                                    }
                                }
                            }
                            
                            // Lokale Benutzer nach E-Mail gruppieren
                            foreach ($adminUsers as $user) {
                                if (isset($user['email']) && !empty($user['email'])) {
                                    $email = strtolower(trim($user['email']));
                                    if (!isset($emailGroups[$email])) {
                                        $emailGroups[$email] = [];
                                    }
                                    $emailGroups[$email]['local'] = $user;
                                }
                            }
                            
                            // Verknüpfungsvorschläge generieren
                            foreach ($emailGroups as $email => $systems) {
                                // Nur Vorschläge für E-Mails mit mehreren Systemen oder lokalen Benutzern
                                if (count($systems) > 1 || isset($systems['local'])) {
                                    $mergeSuggestions[] = [
                                        'email' => $email,
                                        'systems' => $systems
                                    ];
                                }
                            }
                            
                            // Aktuelle Verknüpfungen aus der Datenbank abrufen
                            $currentLinks = [];
                            try {
                                $stmt = $db->query("
                                    SELECT u.id, u.username, u.email, u.full_name, up.permission_type, up.resource_id, up.created_at
                                    FROM users u
                                    LEFT JOIN user_permissions up ON u.id = up.user_id
                                    WHERE up.permission_type IN ('ogp', 'proxmox', 'ispconfig')
                                    ORDER BY u.username, up.permission_type
                                ");
                                $currentLinks = $db->fetchAll($stmt);
                            } catch (Exception $e) {
                                error_log("Fehler beim Laden der Verknüpfungen: " . $e->getMessage());
                            }
                            
                            // Verknüpfungsstatus für jede E-Mail prüfen
                            $linkedEmails = [];
                            foreach ($currentLinks as $link) {
                                $linkedEmails[] = strtolower(trim($link['email']));
                            }
                            ?>
                            
                            <!-- Verknüpfungsstatus -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="bi bi-info-circle"></i> Verknüpfungsstatus</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <h4 class="text-primary"><?= count($adminUsers) ?></h4>
                                                <small>Lokale Benutzer</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <h4 class="text-success"><?= count($currentLinks) ?></h4>
                                                <small>Aktive Verknüpfungen</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <h4 class="text-warning"><?= count($emailGroups) ?></h4>
                                                <small>E-Mail-Gruppen</small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <h4 class="text-info"><?= count($mergeSuggestions) ?></h4>
                                                <small>Verknüpfungsvorschläge</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($mergeSuggestions)): ?>
                                <h5>Verknüpfungsvorschläge:</h5>
                                <?php foreach ($mergeSuggestions as $suggestion): ?>
                                    <div class="card mb-3">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <strong>E-Mail: <?= htmlspecialchars($suggestion['email']) ?></strong>
                                            <?php if (in_array(strtolower($suggestion['email']), $linkedEmails)): ?>
                                                <span class="badge bg-success">Bereits verknüpft</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Nicht verknüpft</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-body">
                                            <form method="post" class="merge-form">
                                                <input type="hidden" name="action" value="merge_users">
                                                <div class="row">
                                                    <div class="col-md-2">
                                                        <label>OpenGamePanel:</label>
                                                        <?php if (isset($suggestion['systems']['ogp'])): ?>
                                                            <input type="text" class="form-control" value="<?= htmlspecialchars($suggestion['systems']['ogp']['users_login'] ?? $suggestion['systems']['ogp']['username'] ?? '') ?>" readonly>
                                                            <input type="hidden" name="system_users[ogp]" value="<?= htmlspecialchars($suggestion['systems']['ogp']['users_login'] ?? $suggestion['systems']['ogp']['username'] ?? '') ?>">
                                                        <?php else: ?>
                                                            <span class="text-muted">Kein Benutzer</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label>Proxmox:</label>
                                                        <?php if (isset($suggestion['systems']['proxmox'])): ?>
                                                            <input type="text" class="form-control" value="<?= htmlspecialchars($suggestion['systems']['proxmox']['userid'] ?? $suggestion['systems']['proxmox']['username'] ?? '') ?>" readonly>
                                                            <input type="hidden" name="system_users[proxmox]" value="<?= htmlspecialchars($suggestion['systems']['proxmox']['userid'] ?? $suggestion['systems']['proxmox']['username'] ?? '') ?>">
                                                        <?php else: ?>
                                                            <span class="text-muted">Kein Benutzer</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label>ISPConfig:</label>
                                                        <?php if (isset($suggestion['systems']['ispconfig'])): ?>
                                                            <input type="text" class="form-control" value="<?= htmlspecialchars($suggestion['systems']['ispconfig']['username'] ?? $suggestion['systems']['ispconfig']['name'] ?? '') ?>" readonly>
                                                            <input type="hidden" name="system_users[ispconfig]" value="<?= htmlspecialchars($suggestion['systems']['ispconfig']['username'] ?? $suggestion['systems']['ispconfig']['name'] ?? '') ?>">
                                                        <?php else: ?>
                                                            <span class="text-muted">Kein Benutzer</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label>Lokaler Benutzer:</label>
                                                        <?php if (isset($suggestion['systems']['local'])): ?>
                                                            <input type="text" class="form-control" value="<?= htmlspecialchars($suggestion['systems']['local']['username']) ?>" readonly>
                                                            <input type="hidden" name="main_user_id" value="<?= htmlspecialchars($suggestion['systems']['local']['id']) ?>">
                                                        <?php else: ?>
                                                            <select name="main_user_id" class="form-select" required>
                                                                <option value="">Benutzer auswählen</option>
                                                                <?php foreach ($adminUsers as $user): ?>
                                                                    <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label>Aktionen:</label>
                                                        <div class="d-grid">
                                                            <?php if (in_array(strtolower($suggestion['email']), $linkedEmails)): ?>
                                                                <button type="button" class="btn btn-success btn-sm" disabled>
                                                                    <i class="bi bi-check-circle"></i> Bereits verknüpft
                                                                </button>
                                                            <?php else: ?>
                                                                <button type="submit" class="btn btn-primary btn-sm">
                                                                    <i class="bi bi-link-45deg"></i> Verknüpfen
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle"></i>
                                    Keine Zusammenführungsvorschläge gefunden. Alle Benutzer sind bereits korrekt verknüpft.
                                </div>
                            <?php endif; ?>
                            
                            <!-- Alle verfügbaren System-Benutzer anzeigen -->
                            <div class="card mt-4">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="bi bi-list-ul"></i> Alle verfügbaren System-Benutzer</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <h6>OpenGamePanel Benutzer:</h6>
                                            <?php if (is_array($ogpUsers) && isset($ogpUsers['message']) && count($ogpUsers['message']) > 0): ?>
                                                <ul class="list-group">
                                                    <?php foreach (array_slice($ogpUsers['message'], 0, 10) as $user): ?>
                                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <strong><?= htmlspecialchars($user['users_login'] ?? $user['username'] ?? 'Unbekannt') ?></strong><br>
                                                                <small class="text-muted"><?= htmlspecialchars($user['users_email'] ?? $user['email'] ?? 'Keine E-Mail') ?></small>
                                                            </div>
                                                            <?php if (isset($user['users_email']) && !empty($user['users_email'])): ?>
                                                                <span class="badge bg-info"><?= count($emailGroups[strtolower($user['users_email'])] ?? 0) ?> Systeme</span>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <p class="text-muted">Keine OGP Benutzer gefunden</p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <h6>Proxmox Benutzer:</h6>
                                            <?php if (is_array($proxmoxUsers) && isset($proxmoxUsers['data']) && count($proxmoxUsers['data']) > 0): ?>
                                                <ul class="list-group">
                                                    <?php foreach (array_slice($proxmoxUsers['data'], 0, 10) as $user): ?>
                                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <strong><?= htmlspecialchars($user['userid'] ?? $user['username'] ?? 'Unbekannt') ?></strong><br>
                                                                <small class="text-muted"><?= htmlspecialchars($user['email'] ?? 'Keine E-Mail') ?></small>
                                                            </div>
                                                            <?php if (isset($user['email']) && !empty($user['email'])): ?>
                                                                <span class="badge bg-info"><?= count($emailGroups[strtolower($user['email'])] ?? 0) ?> Systeme</span>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <p class="text-muted">Keine Proxmox Benutzer gefunden</p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <h6>ISPConfig Benutzer:</h6>
                                            <?php if (is_array($ispconfigClients) && count($ispconfigClients) > 0): ?>
                                                <ul class="list-group">
                                                    <?php foreach (array_slice($ispconfigClients, 0, 10) as $user): ?>
                                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <strong><?= htmlspecialchars($user['username'] ?? $user['name'] ?? 'Unbekannt') ?></strong><br>
                                                                <small class="text-muted"><?= htmlspecialchars($user['email'] ?? $user['mail'] ?? 'Keine E-Mail') ?></small>
                                                            </div>
                                                            <?php if (isset($user['email']) && !empty($user['email'])): ?>
                                                                <span class="badge bg-info"><?= count($emailGroups[strtolower($user['email'])] ?? 0) ?> Systeme</span>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <p class="text-muted">Keine ISPConfig Benutzer gefunden</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bestehende Benutzer verknüpfen Tab -->
                        <div class="tab-pane fade" id="link" role="tabpanel">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                <strong>Hinweis:</strong> Hier können Sie einen neuen lokalen Benutzer anlegen und ihn mit bestehenden System-Benutzern verknüpfen.
                            </div>
                            
                            <form method="post">
                                <input type="hidden" name="action" value="link_existing_user">
                                
                                <!-- Grundlegende Benutzerdaten -->
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6 class="mb-0">Neuer lokaler Benutzer</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="link_username" class="form-label">Benutzername *</label>
                                                <input type="text" class="form-control" id="link_username" name="username" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="link_email" class="form-label">E-Mail *</label>
                                                <input type="email" class="form-control" id="link_email" name="email" required>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="link_full_name" class="form-label">Vollständiger Name *</label>
                                                <input type="text" class="form-control" id="link_full_name" name="full_name" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="link_password" class="form-label">Passwort *</label>
                                                <input type="password" class="form-control" id="link_password" name="password" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Bestehende System-Benutzer verknüpfen -->
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6 class="mb-0">Bestehende System-Benutzer verknüpfen</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label for="link_ogp_user" class="form-label">OpenGamePanel Benutzer</label>
                                                <select class="form-select" id="link_ogp_user" name="systems[ogp]">
                                                    <option value="">Keine Verknüpfung</option>
                                                    <?php if (is_array($ogpUsers) && isset($ogpUsers['message'])): ?>
                                                        <?php foreach ($ogpUsers['message'] as $user): ?>
                                                            <option value="<?= htmlspecialchars($user['users_login'] ?? $user['username'] ?? '') ?>">
                                                                <?= htmlspecialchars($user['users_login'] ?? $user['username'] ?? 'Unbekannt') ?> 
                                                                (<?= htmlspecialchars($user['users_email'] ?? $user['email'] ?? 'Keine E-Mail') ?>)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="link_proxmox_user" class="form-label">Proxmox Benutzer</label>
                                                <select class="form-select" id="link_proxmox_user" name="systems[proxmox]">
                                                    <option value="">Keine Verknüpfung</option>
                                                    <?php if (is_array($proxmoxUsers) && isset($proxmoxUsers['data'])): ?>
                                                        <?php foreach ($proxmoxUsers['data'] as $user): ?>
                                                            <option value="<?= htmlspecialchars($user['userid'] ?? $user['username'] ?? '') ?>">
                                                                <?= htmlspecialchars($user['userid'] ?? $user['username'] ?? 'Unbekannt') ?> 
                                                                (<?= htmlspecialchars($user['email'] ?? 'Keine E-Mail') ?>)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="link_ispconfig_user" class="form-label">ISPConfig Benutzer</label>
                                                <select class="form-select" id="link_ispconfig_user" name="systems[ispconfig]">
                                                    <option value="">Keine Verknüpfung</option>
                                                    <?php foreach ($ispconfigClients as $user): ?>
                                                        <option value="<?= $user['client_id'] ?? $user['id'] ?? '' ?>">
                                                            <?= htmlspecialchars($user['username'] ?? 'Unbekannt') ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-arrow-left-right"></i> Benutzer verknüpfen
                                </button>
                            </form>
                        </div>

                        <!-- Kundenverwaltung Tab -->
                        <div class="tab-pane fade" id="customers" role="tabpanel">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                Hier können Sie neue Kunden anlegen und bestehende verwalten.
                            </div>
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="bi bi-people-fill"></i> Kundenverwaltung</h6>
                                    <button class="btn btn-sm btn-primary" onclick="showCreateCustomerModal()">
                                        <i class="bi bi-plus-circle"></i> Neuen Kunden anlegen
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-3">
                                            <input type="text" class="form-control" id="customerSearchInput" placeholder="<?= t('search_customers') ?>" onkeyup="debounce(loadCustomers, 500)()">
                                        </div>
                                        <div class="col-md-2">
                                            <select class="form-select" id="customerStatusFilter" onchange="loadCustomers()">
                                                <option value=""><?= t('all_statuses') ?></option>
                                                <option value="active"><?= t('active') ?></option>
                                                <option value="inactive"><?= t('inactive') ?></option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <select class="form-select" id="customerTypeFilter" onchange="loadCustomers()">
                                                <option value=""><?= t('all_customer_types') ?></option>
                                                <option value="reseller"><?= t('resellers') ?></option>
                                                <option value="customer"><?= t('customers') ?></option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover" id="customersTable">
                                            <thead>
                                                <tr>
                                                    <th><?= t('customer_id') ?></th>
                                                    <th>Name</th>
                                                    <th>E-Mail</th>
                                                    <th>Firma</th>
                                                    <th>Telefon</th>
                                                    <th>Status</th>
                                                    <th>Registriert am</th>
                                                    <th>Aktionen</th>
                                                </tr>
                                            </thead>
                                            <tbody id="customersTableBody">
                                                <?php if (!empty($customers)): ?>
                                                    <?php foreach ($customers as $customer): ?>
                                                        <tr data-customer-id="<?= htmlspecialchars($customer['id']) ?>">
                                                            <td><?= htmlspecialchars($customer['id']) ?></td>
                                                            <td>
                                                                <strong><?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?></strong>
                                                            </td>
                                                            <td>
                                                                <a href="mailto:<?= htmlspecialchars($customer['email']) ?>">
                                                                    <?= htmlspecialchars($customer['email']) ?>
                                                                </a>
                                                            </td>
                                                            <td><?= htmlspecialchars($customer['company'] ?? '-') ?></td>
                                                            <td><?= htmlspecialchars($customer['phone'] ?? '-') ?></td>
                                                            <td>
                                                                <?php
                                                                $statusClass = '';
                                                                $statusText = '';
                                                                switch ($customer['status']) {
                                                                    case 'pending':
                                                                        $statusClass = 'warning';
                                                                        $statusText = 'Ausstehend';
                                                                        break;
                                                                    case 'active':
                                                                        $statusClass = 'success';
                                                                        $statusText = 'Aktiv';
                                                                        break;
                                                                    case 'suspended':
                                                                        $statusClass = 'danger';
                                                                        $statusText = 'Gesperrt';
                                                                        break;
                                                                    case 'deleted':
                                                                        $statusClass = 'secondary';
                                                                        $statusText = 'Gelöscht';
                                                                        break;
                                                                    default:
                                                                        $statusClass = 'secondary';
                                                                        $statusText = 'Unbekannt';
                                                                }
                                                                ?>
                                                                <span class="badge bg-<?= $statusClass ?>"><?= $statusText ?></span>
                                                            </td>
                                                            <td>
                                                                <?= $customer['created_at'] ? date('d.m.Y H:i', strtotime($customer['created_at'])) : '-' ?>
                                                            </td>
                                                            <td>
                                                                <?php if ($customer['status'] === 'pending'): ?>
                                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Kunde wirklich aktivieren?')">
                                                                        <input type="hidden" name="action" value="activate_customer">
                                                                        <input type="hidden" name="customer_id" value="<?= $customer['id'] ?>">
                                                                        <button type="submit" class="btn btn-success btn-sm" title="Kunde aktivieren">
                                                                            <i class="bi bi-check"></i> Aktivieren
                                                                        </button>
                                                                    </form>
                                                                <?php elseif ($customer['status'] === 'active'): ?>
                                                                    <span class="text-success">
                                                                        <i class="bi bi-check-circle"></i> Aktiviert
                                                                    </span>
                                                                <?php endif; ?>
                                                                
                                                                <button type="button" class="btn btn-info btn-sm ms-1" 
                                                                        onclick="showCustomerDetails(<?= $customer['id'] ?>)" 
                                                                        title="Details anzeigen">
                                                                    <i class="bi bi-eye"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="8" class="text-center">
                                                            <div class="alert alert-info">
                                                                <i class="bi bi-info-circle"></i> <?= t('no_customers_found') ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- Modal für Benutzer bearbeiten -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">Benutzer bearbeiten</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" id="editUserId" name="user_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editUsername" class="form-label">Benutzername *</label>
                            <input type="text" class="form-control" id="editUsername" name="username" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editEmail" class="form-label">E-Mail *</label>
                            <input type="email" class="form-control" id="editEmail" name="email" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editFullName" class="form-label">Vollständiger Name</label>
                            <input type="text" class="form-control" id="editFullName" name="full_name">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editRole" class="form-label">Rolle</label>
                            <select class="form-select" id="editRole" name="role">
                                <option value="user">Benutzer</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editActive" class="form-label">Status</label>
                            <select class="form-select" id="editActive" name="active">
                                <option value="y">Aktiv</option>
                                <option value="n">Inaktiv</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editPassword" class="form-label">Neues Passwort (leer lassen für keine Änderung)</label>
                            <input type="password" class="form-control" id="editPassword" name="password">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">System-Verknüpfungen</label>
                        <div id="editSystemLinks" class="p-2 border rounded">
                            <!-- System-Verknüpfungen werden hier dynamisch eingefügt -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal für Benutzerdetails -->
<div class="modal fade" id="userDetailsModal" tabindex="-1" aria-labelledby="userDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userDetailsModalLabel">Benutzerdetails</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Benutzerdetails werden hier dynamisch eingefügt -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal für Benutzer löschen -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteUserModalLabel">Benutzer löschen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" id="deleteUserId" name="user_id">
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Achtung:</strong> Sie sind dabei, den Benutzer "<span id="deleteUsername"></span>" zu löschen.
                    </div>
                    <p>Bitte wählen Sie aus, aus welchen Systemen der Benutzer gelöscht werden soll:</p>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="deleteFromLocal" name="delete_from_systems[]" value="local" checked>
                        <label class="form-check-label" for="deleteFromLocal">
                            Lokaler Benutzer (immer gelöscht)
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="deleteFromOGP" name="delete_from_systems[]" value="ogp">
                        <label class="form-check-label" for="deleteFromOGP">
                            OpenGamePanel Benutzer
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="deleteFromProxmox" name="delete_from_systems[]" value="proxmox">
                        <label class="form-check-label" for="deleteFromProxmox">
                            Proxmox Benutzer
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="deleteFromISPConfig" name="delete_from_systems[]" value="ispconfig">
                        <label class="form-check-label" for="deleteFromISPConfig">
                            ISPConfig Benutzer
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-danger">Löschen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal für Kundendetails -->
<div class="modal fade" id="customerDetailsModal" tabindex="-1" aria-labelledby="customerDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customerDetailsModalLabel">Kundendetails</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="customerDetailsContent">
                <!-- Wird dynamisch geladen -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript-Code wurde in assets/inc-js/users.js ausgelagert --> 