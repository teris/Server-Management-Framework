<?php
/**
 * Support-Tickets-Seite für Kunden
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
$tickets = [];
$action = $_GET['action'] ?? 'list';

// Kundeninformationen aus der Datenbank laden
try {
    $db = Database::getInstance();
    $stmt = $db->getConnection()->prepare("SELECT * FROM customers WHERE id = ? AND status = 'active'");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        // Kunde nicht gefunden oder inaktiv - Session löschen
        session_destroy();
        header('Location: login.php?error=account_inactive');
        exit;
    }
} catch (Exception $e) {
    error_log("Support Error: " . $e->getMessage());
    $error = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
}

// Formularverarbeitung für neues Ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'new') {
    $subject = trim($_POST['subject'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $message = trim($_POST['message'] ?? '');
    
    // Validierung
    if (empty($subject)) {
        $error = 'Bitte geben Sie einen Betreff ein.';
    } elseif (empty($message)) {
        $error = 'Bitte geben Sie eine Nachricht ein.';
    } else {
        try {
            // Eindeutige Ticket-Nummer generieren
            do {
                $ticketNumber = 'TIC-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Prüfen ob Ticket-Nummer bereits existiert
                $checkStmt = $db->getConnection()->prepare("SELECT COUNT(*) FROM support_tickets WHERE ticket_number = ?");
                $checkStmt->execute([$ticketNumber]);
                $exists = $checkStmt->fetchColumn() > 0;
            } while ($exists);
            
            // E-Mail-Adresse des Kunden holen
            $customerEmail = $customer['email'] ?? $_SESSION['customer_email'] ?? '';
            
            // Falls E-Mail immer noch leer ist, aus der Datenbank holen
            if (empty($customerEmail) && $customerId) {
                $emailStmt = $db->getConnection()->prepare("SELECT email FROM customers WHERE id = ?");
                $emailStmt->execute([$customerId]);
                $customerEmail = $emailStmt->fetchColumn() ?: '';
            }
            
            $stmt = $db->getConnection()->prepare("
                INSERT INTO support_tickets (ticket_number, customer_id, email, subject, priority, message, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'open', NOW())
            ");
            
            if ($stmt->execute([$ticketNumber, $customerId, $customerEmail, $subject, $priority, $message])) {
                $ticketId = $db->getConnection()->lastInsertId();
                
                // Aktivität loggen
                try {
                    $activityLogger = ActivityLogger::getInstance();
                    $activityLogger->logCustomerActivity(
                        $customerId, 
                        'ticket_create', 
                        "Support-Ticket erstellt: $subject", 
                        $ticketId, 
                        'support_tickets'
                    );
                } catch (Exception $e) {
                    error_log("Activity Logging Error: " . $e->getMessage());
                }
                
                $success = 'Ihr Support-Ticket wurde erfolgreich eingereicht.';
                $action = 'list'; // Zurück zur Ticket-Liste
            } else {
                $error = 'Fehler beim Erstellen des Tickets. Bitte versuchen Sie es erneut.';
            }
        } catch (Exception $e) {
            error_log("Ticket Creation Error: " . $e->getMessage());
            $error = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
        }
    }
}

// Tickets laden
if ($action === 'list' || $action === 'view') {
    try {
        $stmt = $db->getConnection()->prepare("
            SELECT * FROM support_tickets 
            WHERE customer_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$customerId]);
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Ticket Loading Error: " . $e->getMessage());
        $error = 'Fehler beim Laden der Tickets.';
    }
}

// Einzelnes Ticket laden
$currentTicket = null;
$ticketReplies = [];
if ($action === 'view' && isset($_GET['id'])) {
    $ticketId = (int)$_GET['id'];
    try {
        $stmt = $db->getConnection()->prepare("
            SELECT * FROM support_tickets 
            WHERE id = ? AND customer_id = ?
        ");
        $stmt->execute([$ticketId, $customerId]);
        $currentTicket = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$currentTicket) {
            $error = 'Ticket nicht gefunden.';
            $action = 'list';
        } else {
            // Ticket-Replies laden (nur nicht-interne Antworten für Kunden)
            $repliesStmt = $db->getConnection()->prepare("
                SELECT tr.*, 
                       CASE 
                           WHEN tr.customer_id IS NOT NULL THEN CONCAT(c.first_name, ' ', c.last_name)
                           WHEN tr.admin_id IS NOT NULL THEN CONCAT(u.full_name, ' (Support Team)')
                           ELSE 'System'
                       END as author_name,
                       CASE 
                           WHEN tr.customer_id IS NOT NULL THEN 'customer'
                           WHEN tr.admin_id IS NOT NULL THEN 'admin'
                           ELSE 'system'
                       END as author_type,
                       tr.admin_id,
                       tr.customer_id
                FROM ticket_replies tr
                LEFT JOIN customers c ON tr.customer_id = c.id
                LEFT JOIN users u ON tr.admin_id = u.id
                WHERE tr.ticket_id = ? 
                AND tr.is_internal = 0
                ORDER BY tr.created_at ASC
            ");
            $repliesStmt->execute([$ticketId]);
            $ticketReplies = $repliesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Debugging: Log der gefundenen Antworten
            error_log("Support Debug: Found " . count($ticketReplies) . " replies for ticket " . $ticketId);
            foreach ($ticketReplies as $reply) {
                error_log("Support Debug: Reply ID " . $reply['id'] . " - Type: " . $reply['author_type'] . ", Admin ID: " . ($reply['admin_id'] ?? 'NULL') . ", Customer ID: " . ($reply['customer_id'] ?? 'NULL'));
            }
            
            // Debug: Alle Antworten überprüfen (auch interne)
            if (isset($_GET['debug']) && $_GET['debug'] === '1') {
                $debugStmt = $db->getConnection()->prepare("
                    SELECT tr.*, 
                           CASE 
                               WHEN tr.customer_id IS NOT NULL THEN CONCAT(c.first_name, ' ', c.last_name)
                               WHEN tr.admin_id IS NOT NULL THEN CONCAT(u.full_name, ' (Support Team)')
                               ELSE 'System'
                           END as author_name,
                           tr.is_internal
                    FROM ticket_replies tr
                    LEFT JOIN customers c ON tr.customer_id = c.id
                    LEFT JOIN users u ON tr.admin_id = u.id
                    WHERE tr.ticket_id = ?
                    ORDER BY tr.created_at ASC
                ");
                $debugStmt->execute([$ticketId]);
                $allReplies = $debugStmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("Support Debug: Total replies (including internal): " . count($allReplies));
                foreach ($allReplies as $reply) {
                    error_log("Support Debug: All Reply ID " . $reply['id'] . " - Internal: " . $reply['is_internal'] . ", Author: " . $reply['author_name']);
                }
            }
        }
    } catch (Exception $e) {
        error_log("Ticket View Error: " . $e->getMessage());
        $error = 'Fehler beim Laden des Tickets.';
        $action = 'list';
    }
}

// Kunden-Antwort auf Ticket hinzufügen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'reply' && isset($_POST['ticket_id']) && isset($_POST['message'])) {
    $replyTicketId = (int)$_POST['ticket_id'];
    $replyMessage = trim($_POST['message']);
    
    if (empty($replyMessage)) {
        $error = 'Bitte geben Sie eine Nachricht ein.';
    } else {
        try {
            // Prüfen ob das Ticket dem Kunden gehört
            $checkStmt = $db->getConnection()->prepare("SELECT id FROM support_tickets WHERE id = ? AND customer_id = ?");
            $checkStmt->execute([$replyTicketId, $customerId]);
            
            if ($checkStmt->fetch()) {
                // Kunden-Antwort hinzufügen
                $replyStmt = $db->getConnection()->prepare("
                    INSERT INTO ticket_replies (ticket_id, customer_id, message, is_internal, created_at) 
                    VALUES (?, ?, ?, 0, NOW())
                ");
                $replyStmt->execute([$replyTicketId, $customerId, $replyMessage]);
                
                // Aktivität loggen
                try {
                    $activityLogger = ActivityLogger::getInstance();
                    $activityLogger->logCustomerActivity(
                        $customerId, 
                        'ticket_reply', 
                        "Antwort auf Support-Ticket gesendet", 
                        $replyTicketId, 
                        'ticket_replies'
                    );
                } catch (Exception $e) {
                    error_log("Activity Logging Error: " . $e->getMessage());
                }
                
                // Ticket-Status auf "waiting_admin" setzen
                $updateStmt = $db->getConnection()->prepare("
                    UPDATE support_tickets 
                    SET status = 'waiting_admin', updated_at = NOW() 
                    WHERE id = ?
                ");
                $updateStmt->execute([$replyTicketId]);
                
                $success = 'Ihre Antwort wurde erfolgreich gesendet.';
                
                // Ticket und Replies neu laden
                $stmt = $db->getConnection()->prepare("SELECT * FROM support_tickets WHERE id = ? AND customer_id = ?");
                $stmt->execute([$replyTicketId, $customerId]);
                $currentTicket = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $repliesStmt = $db->getConnection()->prepare("
                    SELECT tr.*, 
                           CASE 
                               WHEN tr.customer_id IS NOT NULL THEN CONCAT(c.first_name, ' ', c.last_name)
                               WHEN tr.admin_id IS NOT NULL THEN CONCAT(u.full_name, ' (Support Team)')
                               ELSE 'System'
                           END as author_name,
                           CASE 
                               WHEN tr.customer_id IS NOT NULL THEN 'customer'
                               WHEN tr.admin_id IS NOT NULL THEN 'admin'
                               ELSE 'system'
                           END as author_type,
                           tr.admin_id,
                           tr.customer_id
                    FROM ticket_replies tr
                    LEFT JOIN customers c ON tr.customer_id = c.id
                    LEFT JOIN users u ON tr.admin_id = u.id
                    WHERE tr.ticket_id = ? 
                    AND tr.is_internal = 0
                    ORDER BY tr.created_at ASC
                ");
                $repliesStmt->execute([$replyTicketId]);
                $ticketReplies = $repliesStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $error = 'Ticket nicht gefunden oder kein Zugriff.';
            }
        } catch (Exception $e) {
            error_log("Ticket Reply Error: " . $e->getMessage());
            $error = 'Fehler beim Senden der Antwort.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('support_tickets') ?> - Server Management</title>
    
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
                        <a class="nav-link active" href="support.php">
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

    <!-- Support Content -->
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

        <?php if ($action === 'new'): ?>
            <!-- Neues Ticket erstellen -->
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title mb-0">
                                <i class="bi bi-plus-circle text-primary"></i> 
                                <?= t('submit_ticket') ?>
                            </h3>
                            <a href="support.php" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-arrow-left"></i> <?= t('back') ?>
                            </a>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="support.php?action=new">
                                <div class="mb-3">
                                    <label for="subject" class="form-label"><?= t('subject') ?> *</label>
                                    <input type="text" class="form-control" id="subject" name="subject" 
                                           value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="priority" class="form-label"><?= t('priority') ?></label>
                                    <select class="form-select" id="priority" name="priority">
                                        <option value="low" <?= ($_POST['priority'] ?? 'medium') === 'low' ? 'selected' : '' ?>>
                                            <?= t('low') ?>
                                        </option>
                                        <option value="medium" <?= ($_POST['priority'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>
                                            <?= t('medium') ?>
                                        </option>
                                        <option value="high" <?= ($_POST['priority'] ?? 'medium') === 'high' ? 'selected' : '' ?>>
                                            <?= t('high') ?>
                                        </option>
                                        <option value="urgent" <?= ($_POST['priority'] ?? 'medium') === 'urgent' ? 'selected' : '' ?>>
                                            <?= t('urgent') ?>
                                        </option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="message" class="form-label"><?= t('message') ?> *</label>
                                    <textarea class="form-control" id="message" name="message" rows="8" required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="support.php" class="btn btn-secondary me-md-2">
                                        <i class="bi bi-x-lg"></i> <?= t('cancel') ?>
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-send"></i> <?= t('submit_ticket') ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($action === 'view' && $currentTicket): ?>
            <!-- Ticket anzeigen -->
            <div class="row">
                <div class="col-lg-10 mx-auto">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title mb-0">
                                <i class="bi bi-ticket-detailed text-info"></i> 
                                Ticket #<?= $currentTicket['id'] ?>
                            </h3>
                            <div>
                                <a href="support.php" class="btn btn-outline-secondary btn-sm me-2">
                                    <i class="bi bi-arrow-left"></i> <?= t('back') ?>
                                </a>
                                <a href="support.php?action=new" class="btn btn-primary btn-sm">
                                    <i class="bi bi-plus-circle"></i> <?= t('submit_ticket') ?>
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-8">
                                    <h4><?= htmlspecialchars($currentTicket['subject']) ?></h4>
                                </div>
                                <div class="col-md-4 text-end">
                                    <?php
                                    $priorityColors = [
                                        'low' => 'success',
                                        'medium' => 'info',
                                        'high' => 'warning',
                                        'urgent' => 'danger'
                                    ];
                                    $priorityColor = $priorityColors[$currentTicket['priority']] ?? 'info';
                                    ?>
                                    <span class="badge bg-<?= $priorityColor ?> fs-6">
                                        <?= t($currentTicket['priority']) ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong><?= t('status') ?>:</strong> 
                                    <?php
                                    $statusColors = [
                                        'open' => 'success',
                                        'in_progress' => 'info',
                                        'waiting_customer' => 'warning',
                                        'waiting_admin' => 'primary',
                                        'resolved' => 'success',
                                        'closed' => 'secondary'
                                    ];
                                    $statusColor = $statusColors[$currentTicket['status']] ?? 'secondary';
                                    $statusText = $currentTicket['status'] === 'closed' ? 'closed' : 'open';
                                    ?>
                                    <span class="badge bg-<?= $statusColor ?>">
                                        <?= t($statusText) ?>
                                    </span>
                                </div>
                                <div class="col-md-6 text-end">
                                    <strong><?= t('created') ?>:</strong> 
                                    <?= date('d.m.Y H:i:s', strtotime($currentTicket['created_at'])) ?>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="mb-4">
                                <h5><?= t('message') ?>:</h5>
                                <div class="border rounded p-3 bg-light">
                                    <?= nl2br(htmlspecialchars($currentTicket['message'])) ?>
                                </div>
                            </div>
                            
                            <?php if ($currentTicket['updated_at'] && $currentTicket['updated_at'] !== $currentTicket['created_at']): ?>
                                <div class="text-muted small">
                                    <i class="bi bi-clock"></i> 
                                    <?= t('last_updated') ?>: <?= date('d.m.Y H:i:s', strtotime($currentTicket['updated_at'])) ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Antworten anzeigen -->
                            <?php if (!empty($ticketReplies)): ?>
                                <hr>
                                <h5><?= t('replies') ?>:</h5>
                                <div class="replies-container">
                                    <?php foreach ($ticketReplies as $reply): ?>
                                        <div class="reply-item mb-3 p-3 border rounded <?= $reply['author_type'] === 'admin' ? 'bg-light' : 'bg-white' ?>">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <strong class="<?= $reply['author_type'] === 'admin' ? 'text-primary' : 'text-success' ?>">
                                                        <?= htmlspecialchars($reply['author_name']) ?>
                                                    </strong>
                                                    <small class="text-muted ms-2">
                                                        <?= date('d.m.Y H:i:s', strtotime($reply['created_at'])) ?>
                                                    </small>
                                                </div>
                                                <span class="badge bg-<?= $reply['author_type'] === 'admin' ? 'primary' : 'success' ?>">
                                                    <?= $reply['author_type'] === 'admin' ? 'Support Team' : 'Sie' ?>
                                                </span>
                                            </div>
                                            <div class="reply-message">
                                                <?= nl2br(htmlspecialchars($reply['message'])) ?>
                                            </div>
                                            <!-- Debug-Informationen (nur für Entwickler sichtbar) -->
                                            <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
                                                <div class="mt-2 p-2 bg-warning text-dark small">
                                                    <strong>Debug:</strong> 
                                                    Type: <?= htmlspecialchars($reply['author_type']) ?>, 
                                                    Admin ID: <?= htmlspecialchars($reply['admin_id'] ?? 'NULL') ?>, 
                                                    Customer ID: <?= htmlspecialchars($reply['customer_id'] ?? 'NULL') ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <hr>
                                <div class="text-center text-muted py-3">
                                    <i class="bi bi-chat-dots"></i>
                                    <p class="mb-0"><?= t('no_replies_yet') ?></p>
                                    <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
                                        <div class="mt-2 p-2 bg-info text-white small">
                                            <strong>Debug:</strong> Keine Antworten gefunden. 
                                            Ticket ID: <?= $currentTicket['id'] ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Antwort-Formular (nur wenn Ticket nicht geschlossen ist) -->
                            <?php if ($currentTicket['status'] !== 'closed'): ?>
                                <hr>
                                <h5><?= t('reply_message') ?>:</h5>
                                <form method="POST" action="support.php?action=reply">
                                    <input type="hidden" name="ticket_id" value="<?= $currentTicket['id'] ?>">
                                    <div class="mb-3">
                                        <textarea class="form-control" name="message" rows="4" 
                                                  placeholder="<?= t('enter_your_reply') ?>" required></textarea>
                                    </div>
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-send"></i> <?= t('send_reply') ?>
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <hr>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> 
                                    <?= t('ticket_closed_message') ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Ticket-Liste -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title mb-0">
                                <i class="bi bi-headset text-primary"></i> 
                                <?= t('support_tickets') ?>
                            </h3>
                            <a href="support.php?action=new" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> <?= t('submit_ticket') ?>
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($tickets)): ?>
                                <div class="text-center text-muted py-5">
                                    <i class="bi bi-inbox display-4"></i>
                                    <h4 class="mt-3"><?= t('no_tickets') ?></h4>
                                    <p class="mb-4"><?= t('no_tickets_message') ?></p>
                                    <a href="support.php?action=new" class="btn btn-primary">
                                        <i class="bi bi-plus-circle"></i> <?= t('submit_ticket') ?>
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th><?= t('ticket_id') ?></th>
                                                <th><?= t('subject') ?></th>
                                                <th><?= t('priority') ?></th>
                                                <th><?= t('status') ?></th>
                                                <th><?= t('created') ?></th>
                                                <th><?= t('actions') ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($tickets as $ticket): ?>
                                                <tr>
                                                    <td>
                                                        <strong>#<?= $ticket['id'] ?></strong>
                                                    </td>
                                                    <td>
                                                        <a href="support.php?action=view&id=<?= $ticket['id'] ?>" 
                                                           class="text-decoration-none">
                                                            <?= htmlspecialchars($ticket['subject']) ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $priorityColors = [
                                                            'low' => 'success',
                                                            'medium' => 'info',
                                                            'high' => 'warning',
                                                            'urgent' => 'danger'
                                                        ];
                                                        $priorityColor = $priorityColors[$ticket['priority']] ?? 'info';
                                                        ?>
                                                        <span class="badge bg-<?= $priorityColor ?>">
                                                            <?= t($ticket['priority']) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $statusColors = [
                                                            'open' => 'success',
                                                            'in_progress' => 'info',
                                                            'waiting_customer' => 'warning',
                                                            'waiting_admin' => 'primary',
                                                            'resolved' => 'success',
                                                            'closed' => 'secondary'
                                                        ];
                                                        $statusColor = $statusColors[$ticket['status']] ?? 'secondary';
                                                        $statusText = $ticket['status'] === 'closed' ? 'closed' : 'open';
                                                        ?>
                                                        <span class="badge bg-<?= $statusColor ?>">
                                                            <?= t($statusText) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?= date('d.m.Y H:i', strtotime($ticket['created_at'])) ?>
                                                    </td>
                                                    <td>
                                                        <a href="support.php?action=view&id=<?= $ticket['id'] ?>" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-eye"></i> <?= t('view') ?>
                                                        </a>
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
        <?php endif; ?>
        
        <!-- Debug-Informationen (nur für Entwickler sichtbar) -->
        <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
            <div class="mt-3 p-3 bg-warning text-dark">
                <h6><i class="bi bi-bug"></i> Debug-Informationen</h6>
                <p><strong>Ticket ID:</strong> <?= $currentTicket['id'] ?? 'N/A' ?></p>
                <p><strong>Gefundene Antworten:</strong> <?= count($ticketReplies) ?></p>
                <p><strong>Ticket Status:</strong> <?= $currentTicket['status'] ?? 'N/A' ?></p>
                <p><strong>Customer ID:</strong> <?= $customerId ?></p>
                
                <?php if (!empty($ticketReplies)): ?>
                    <h6>Antworten Details:</h6>
                    <ul>
                        <?php foreach ($ticketReplies as $reply): ?>
                            <li>
                                ID: <?= $reply['id'] ?>, 
                                Type: <?= $reply['author_type'] ?>, 
                                Admin ID: <?= $reply['admin_id'] ?? 'NULL' ?>, 
                                Customer ID: <?= $reply['customer_id'] ?? 'NULL' ?>,
                                Internal: <?= $reply['is_internal'] ?? 'N/A' ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                
                <!-- Überprüfung der gesamten Datenbank -->
                <?php
                try {
                    $adminRepliesCount = $db->getConnection()->prepare("SELECT COUNT(*) FROM ticket_replies WHERE admin_id IS NOT NULL");
                    $adminRepliesCount->execute();
                    $totalAdminReplies = $adminRepliesCount->fetchColumn();
                    
                    $customerRepliesCount = $db->getConnection()->prepare("SELECT COUNT(*) FROM ticket_replies WHERE customer_id IS NOT NULL");
                    $customerRepliesCount->execute();
                    $totalCustomerReplies = $customerRepliesCount->fetchColumn();
                    
                    $internalRepliesCount = $db->getConnection()->prepare("SELECT COUNT(*) FROM ticket_replies WHERE is_internal = 1");
                    $internalRepliesCount->execute();
                    $totalInternalReplies = $internalRepliesCount->fetchColumn();
                } catch (Exception $e) {
                    $totalAdminReplies = 'Error';
                    $totalCustomerReplies = 'Error';
                    $totalInternalReplies = 'Error';
                }
                ?>
                <h6>Datenbank-Übersicht:</h6>
                <ul>
                    <li><strong>Gesamte Admin-Antworten:</strong> <?= $totalAdminReplies ?></li>
                    <li><strong>Gesamte Kunden-Antworten:</strong> <?= $totalCustomerReplies ?></li>
                    <li><strong>Interne Antworten:</strong> <?= $totalInternalReplies ?></li>
                </ul>
                
                <!-- Überprüfung des aktuellen Tickets
                <?php
                try {
                    $ticketAdminRepliesCount = $db->getConnection()->prepare("SELECT COUNT(*) FROM ticket_replies WHERE ticket_id = ? AND admin_id IS NOT NULL");
                    $ticketAdminRepliesCount->execute([$currentTicket['id']]);
                    $ticketAdminReplies = $ticketAdminRepliesCount->fetchColumn();
                    
                    $ticketCustomerRepliesCount = $db->getConnection()->prepare("SELECT COUNT(*) FROM ticket_replies WHERE ticket_id = ? AND customer_id IS NOT NULL");
                    $ticketCustomerRepliesCount->execute([$currentTicket['id']]);
                    $ticketCustomerReplies = $ticketCustomerRepliesCount->fetchColumn();
                    
                    $ticketInternalRepliesCount = $db->getConnection()->prepare("SELECT COUNT(*) FROM ticket_replies WHERE ticket_id = ? AND is_internal = 1");
                    $ticketInternalRepliesCount->execute([$currentTicket['id']]);
                    $ticketInternalReplies = $ticketInternalRepliesCount->fetchColumn();
                } catch (Exception $e) {
                    $ticketAdminReplies = 'Error';
                    $ticketCustomerReplies = 'Error';
                    $ticketInternalReplies = 'Error';
                }
                ?>
                <h6>Ticket-spezifische Übersicht:</h6>
                <ul>
                    <li><strong>Admin-Antworten für dieses Ticket:</strong> <?= $ticketAdminReplies ?></li>
                    <li><strong>Kunden-Antworten für dieses Ticket:</strong> <?= $ticketCustomerReplies ?></li>
                    <li><strong>Interne Antworten für dieses Ticket:</strong> <?= $ticketInternalReplies ?></li>
                </ul>
                
                <!-- Detaillierte Überprüfung der Admin-Antworten -->
                <?php
                try {
                    $detailedAdminReplies = $db->getConnection()->prepare("
                        SELECT tr.*, u.full_name, u.username
                        FROM ticket_replies tr
                        LEFT JOIN users u ON tr.admin_id = u.id
                        WHERE tr.ticket_id = ? AND tr.admin_id IS NOT NULL
                        ORDER BY tr.created_at ASC
                    ");
                    $detailedAdminReplies->execute([$currentTicket['id']]);
                    $adminRepliesDetails = $detailedAdminReplies->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    $adminRepliesDetails = [];
                }
                ?>
                <h6>Detaillierte Admin-Antworten:</h6>
                <?php if (!empty($adminRepliesDetails)): ?>
                    <ul>
                        <?php foreach ($adminRepliesDetails as $adminReply): ?>
                            <li>
                                ID: <?= $adminReply['id'] ?>, 
                                Admin: <?= htmlspecialchars($adminReply['full_name'] ?? $adminReply['username'] ?? 'Unbekannt') ?>, 
                                Internal: <?= $adminReply['is_internal'] ? 'Ja' : 'Nein' ?>,
                                Datum: <?= date('d.m.Y H:i:s', strtotime($adminReply['created_at'])) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted">Keine Admin-Antworten für dieses Ticket gefunden.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
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
