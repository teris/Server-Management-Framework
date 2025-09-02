<?php
/**
 * Frontpanel - Öffentliche Hauptseite
 * Zeigt Server-Status, Kundenlogin und Ticket-System
 */

require_once '../src/sys.conf.php';
require_once '../framework.php';
require_once '../src/core/LanguageManager.php';

// Sprache setzen
$lang = LanguageManager::getInstance();
$currentLang = $lang->getCurrentLanguage();

// ServiceManager für Server-Status
$serviceManager = new ServiceManager();

// Hilfsfunktion für sichere Array-Anzeige
function safeDisplay($value, $default = 'N/A') {
    if (is_array($value)) {
        if (isset($value['1min'])) {
            return htmlspecialchars($value['1min']);
        } elseif (isset($value['total'])) {
            return htmlspecialchars($value['total']);
        } elseif (isset($value['used'])) {
            return htmlspecialchars($value['used']);
        } else {
            return $default;
        }
    }
    return htmlspecialchars($value ?? $default);
}

// Server-Status abrufen
try {
    $proxmoxVMs = $serviceManager->getProxmoxVMs();
    $gameServers = $serviceManager->getOGPGameServers();
    $systemInfo = $serviceManager->getSystemInfo();
} catch (Exception $e) {
    $proxmoxVMs = [];
    $gameServers = [];
    $systemInfo = [];
    error_log("Frontpanel Error: " . $e->getMessage());
}

// Übersetzungsfunktion wird von sys.conf.php bereitgestellt
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('frontpanel_title') ?> - Server Management</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" type="text/css" href="assets/frontpanel.css">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-server"></i> Server Management
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#server-status"><?= t('server_status') ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#game-servers"><?= t('game_servers') ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#support"><?= t('support') ?></a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">
                            <i class="bi bi-person-circle"></i> <?= t('customer_login') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">
                            <i class="bi bi-person-plus"></i> <?= t('register') ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-section bg-gradient-primary text-white py-5">
        <div class="container text-center">
            <h1 class="display-4 mb-3"><?= t('welcome_message') ?></h1>
            <p class="lead mb-4"><?= t('hero_description') ?></p>
            <div class="row justify-content-center">
                <div class="col-md-3 col-6 mb-3">
                    <div class="stat-card">
                        <i class="bi bi-server display-6"></i>
                        <h3><?= count($proxmoxVMs) ?></h3>
                        <p><?= t('virtual_machines') ?></p>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="stat-card">
                        <i class="bi bi-controller display-6"></i>
                        <h3 id="game-servers-count"><?= isset($gameServers['message']) && is_array($gameServers['message']) ? count($gameServers['message']) : 0 ?></h3>
                        <p><?= t('game_servers') ?></p>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="stat-card">
                        <i class="bi bi-activity display-6"></i>
                        <h3><?= $systemInfo['uptime'] ?? 'N/A' ?></h3>
                        <p><?= t('uptime') ?></p>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="stat-card">
                        <i class="bi bi-speedometer2 display-6"></i>
                        <h3><?= safeDisplay($systemInfo['load']) ?></h3>
                        <p><?= t('system_load') ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Server Status Section -->
    <section id="server-status" class="py-5 status-section">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">
                    <i class="bi bi-server"></i> <?= t('proxmox_server_status') ?>
                </h2>
                <div class="d-flex align-items-center">
                    <small class="text-muted me-3">
                        <i class="bi bi-clock"></i> <?= t('last_update') ?>: <span id="last-refresh-time"><?= date('H:i:s') ?></span>
                    </small>
                    <button id="manual-refresh-btn" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                    </button>
                </div>
            </div>
            <div class="row" id="proxmox-vms-container">
                <?php if (!empty($proxmoxVMs)): ?>
                    <?php foreach ($proxmoxVMs as $vm): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="card server-card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="bi bi-cpu"></i> 
                                        <?= htmlspecialchars($vm->name ?? 'Unbekannte VM') ?>
                                    </h5>
                                    <span class="badge bg-<?= ($vm->status ?? '') === 'running' ? 'success' : 'secondary' ?>">
                                        <?= htmlspecialchars($vm->status ?? 'unbekannt') ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-6">
                                            <small class="text-muted"><?= t('cpu_usage') ?></small>
                                            <div class="progress mb-2" style="height: 8px;">
                                                <div class="progress-bar" style="width: <?= ($vm->cpu_usage ?? 0) * 100 ?>%"></div>
                                            </div>
                                            <small class="text-muted"><?= number_format(($vm->cpu_usage ?? 0) * 100, 1) ?>%</small>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted"><?= t('memory_usage') ?></small>
                                            <div class="progress mb-2" style="height: 8px;">
                                                <div class="progress-bar bg-info" style="width: <?= ($vm->memory_usage ?? 0) / ($vm->memory ?? 1) * 100 ?>%"></div>
                                            </div>
                                            <small class="text-muted"><?= number_format(($vm->memory_usage ?? 0) / 1024 / 1024 / 1024, 1) ?> GB / <?= number_format(($vm->memory ?? 0) / 1024 / 1024 / 1024, 1) ?> GB</small>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <small class="text-muted"><?= t('cores') ?></small>
                                            <h6><?= htmlspecialchars($vm->cores ?? 'N/A') ?></h6>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted"><?= t('uptime') ?></small>
                                            <h6><?= $vm->uptime ? gmdate('H:i:s', $vm->uptime) : 'N/A' ?></h6>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <small class="text-muted">
                                            <i class="bi bi-clock"></i> 
                                            <?= t('last_update') ?>: <?= date('H:i:s') ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> <?= t('no_vms_available') ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Game Servers Section -->
    <section id="game-servers" class="py-5 bg-light status-section">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">
                    <i class="bi bi-controller"></i> <?= t('game_server_status') ?>
                </h2>
                <div class="d-flex align-items-center">
                    <small class="text-muted me-3">
                        <i class="bi bi-clock"></i> <?= t('last_update') ?>: <span id="last-refresh-time-game"><?= date('H:i:s') ?></span>
                    </small>
                </div>
            </div>
            <div class="row" id="game-servers-container">
                <?php if (!empty($gameServers) && isset($gameServers['message']) && is_array($gameServers['message'])): ?>
                    <?php foreach ($gameServers['message'] as $server): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="card game-server-card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="bi bi-controller"></i> 
                                        <?= htmlspecialchars($server['home_name'] ?? $server['game_name'] ?? 'Unbekannter Server') ?>
                                    </h5>
                                    <?php 
                                    $serverStatus = $serviceManager->getOGPServerStatus($server['remote_server_id']);
                                    $isOnline = ($serverStatus['message'] ?? '') === 'online';
                                    ?>
                                    <span class="badge bg-<?= $isOnline ? 'success' : 'secondary' ?>">
                                        <?= htmlspecialchars($isOnline ? 'Online' : 'Offline') ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <small class="text-muted"><?= t('game_type') ?></small>
                                            <h6><?= htmlspecialchars($server['game_name'] ?? 'N/A') ?></h6>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted"><?= t('server') ?></small>
                                            <h6><?= htmlspecialchars($server['remote_server_name'] ?? 'N/A') ?></h6>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <small class="text-muted"><?= t('ip_address') ?></small>
                                            <h6><?= htmlspecialchars($server['display_public_ip'] ?? 'N/A') ?></h6>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted"><?= t('port') ?></small>
                                            <h6><?= htmlspecialchars($server['agent_port'] ?? 'N/A') ?></h6>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <small class="text-muted">
                                            <i class="bi bi-clock"></i> 
                                            <?= t('last_update') ?>: <?= date('H:i:s') ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> <?= t('no_game_servers_available') ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Support Section -->
    <section id="support" class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">
                <i class="bi bi-headset"></i> <?= t('support_tickets') ?>
            </h2>
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="card">
                        <div class="card-body">
                            <form id="ticket-form">
                                <div class="mb-3">
                                    <label for="ticket-subject" class="form-label"><?= t('subject') ?></label>
                                    <input type="text" class="form-control" id="ticket-subject" required>
                                </div>
                                <div class="mb-3">
                                    <label for="ticket-email" class="form-label"><?= t('email') ?></label>
                                    <input type="email" class="form-control" id="ticket-email" required>
                                </div>
                                <div class="mb-3">
                                    <label for="ticket-priority" class="form-label"><?= t('priority') ?></label>
                                    <select class="form-select" id="ticket-priority" required>
                                        <option value="low"><?= t('low') ?></option>
                                        <option value="medium"><?= t('medium') ?></option>
                                        <option value="high"><?= t('high') ?></option>
                                        <option value="urgent"><?= t('urgent') ?></option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="ticket-message" class="form-label"><?= t('message') ?></label>
                                    <textarea class="form-control" id="ticket-message" rows="5" required></textarea>
                                </div>
                                <div class="text-center">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="bi bi-send"></i> <?= t('submit_ticket') ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container text-center">
            <p>&copy; <?= date('Y') ?> Server Management. <?= t('all_rights_reserved') ?></p>
            <div class="mt-2">
                <a href="../src/index.php" class="text-white-50 me-3">
                    <i class="bi bi-shield-lock"></i> <?= t('admin_panel') ?>
                </a>
                <a href="status.php" class="text-white-50 me-3">
                    <i class="bi bi-activity"></i> <?= t('system_status') ?>
                </a>
                <a href="contact.php" class="text-white-50">
                    <i class="bi bi-envelope"></i> <?= t('contact') ?>
                </a>
            </div>
        </div>
    </footer>

    <!-- Ticket Success Modal -->
    <div class="modal fade" id="ticketSuccessModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= t('ticket_submitted') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><?= t('ticket_submitted_message') ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal"><?= t('ok') ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container für Benachrichtigungen -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1050;">
        <!-- Toasts werden hier dynamisch eingefügt -->
    </div>
    
    <script src="assets/frontpanel.js"></script>
</body>
</html>
