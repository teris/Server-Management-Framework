<?php
// Template-Daten aus der globalen Variable verfügbar machen
if (isset($GLOBALS['_template_data'])) {
    extract($GLOBALS['_template_data']);
}

// ServiceManager-Instanz erstellen, falls nicht verfügbar
if (!isset($serviceManager)) {
    $serviceManager = new ServiceManager();
}

// Übersetzungen werden jetzt über die globale t() Funktion geladen
?>
<div class="admin-module">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0"><i class="bi bi-gear"></i> <?= t('module_title') ?></h3>
                </div>
                <div class="card-body">
                    <!-- Schnellaktionen -->
                    <div class="row mb-4">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card border-0 bg-primary text-white">
                                <div class="card-body text-center">
                                    <i class="bi bi-display display-4"></i>
                                    <h5 class="mt-2"><?= t('manage_vms') ?></h5>
                                    <div class="stats-info mb-2">
                                        <span class="badge bg-light text-dark" id="vm-stats">
                                            <i class="bi bi-hourglass-split"></i> <?= t('loading') ?>
                                        </span>
                                    </div>
                                    <button class="btn btn-light btn-sm" onclick="loadAdminVMData()">
                                        <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card border-0 bg-success text-white">
                                <div class="card-body text-center">
                                    <i class="bi bi-globe display-4"></i>
                                    <h5 class="mt-2"><?= t('websites') ?></h5>
                                    <div class="stats-info mb-2">
                                        <span class="badge bg-light text-dark" id="website-stats">
                                            <i class="bi bi-hourglass-split"></i> <?= t('loading') ?>
                                        </span>
                                    </div>
                                    <button class="btn btn-light btn-sm" onclick="loadAdminWebsiteData()">
                                        <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card border-0 bg-info text-white">
                                <div class="card-body text-center">
                                    <i class="bi bi-database display-4"></i>
                                    <h5 class="mt-2"><?= t('databases') ?></h5>
                                    <div class="stats-info mb-2">
                                        <span class="badge bg-light text-dark" id="database-stats">
                                            <i class="bi bi-hourglass-split"></i> <?= t('loading') ?>
                                        </span>
                                    </div>
                                    <button class="btn btn-light btn-sm" onclick="loadAdminDatabaseData()">
                                        <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card border-0 bg-warning text-white">
                                <div class="card-body text-center">
                                    <i class="bi bi-envelope display-4"></i>
                                    <h5 class="mt-2"><?= t('emails') ?></h5>
                                    <div class="stats-info mb-2">
                                        <span class="badge bg-light text-dark" id="email-stats">
                                            <i class="bi bi-hourglass-split"></i> <?= t('loading') ?>
                                        </span>
                                    </div>
                                    <button class="btn btn-light btn-sm" onclick="loadAdminEmailData()">
                                        <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Gameserver (OGP) Schnellaktionen -->
                    <div class="row mb-4">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card border-0 bg-danger text-white">
                                <div class="card-body text-center">
                                    <i class="bi bi-controller display-4"></i>
                                    <h5 class="mt-2"><?= t('gameservers') ?></h5>
                                    <div class="stats-info mb-2">
                                        <span class="badge bg-light text-dark" id="ogp-stats">
                                            <i class="bi bi-hourglass-split"></i> <?= t('loading') ?>
                                        </span>
                                    </div>
                                    <button class="btn btn-light btn-sm" onclick="loadOGPData()">
                                        <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card border-0 bg-secondary text-white">
                                <div class="card-body text-center">
                                    <i class="bi bi-server display-4"></i>
                                    <h5 class="mt-2"><?= t('active_servers') ?></h5>
                                    <div class="stats-info mb-2">
                                        <span class="badge bg-light text-dark" id="active-servers-stats">
                                            <i class="bi bi-hourglass-split"></i> <?= t('loading') ?>
                                        </span>
                                    </div>
                                    <button class="btn btn-light btn-sm" onclick="loadActiveServersData()">
                                        <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card border-0 bg-dark text-white">
                                <div class="card-body text-center">
                                    <i class="bi bi-people display-4"></i>
                                    <h5 class="mt-2"><?= t('online_players') ?></h5>
                                    <div class="stats-info mb-2">
                                        <span class="badge bg-light text-dark" id="online-players-stats">
                                            <i class="bi bi-hourglass-split"></i> <?= t('loading') ?>
                                        </span>
                                    </div>
                                    <button class="btn btn-light btn-sm" onclick="loadOnlinePlayersData()">
                                        <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card border-0 bg-purple text-white">
                                <div class="card-body text-center">
                                    <i class="bi bi-graph-up display-4"></i>
                                    <h5 class="mt-2"><?= t('server_performance') ?></h5>
                                    <div class="stats-info mb-2">
                                        <span class="badge bg-light text-dark" id="performance-stats">
                                            <i class="bi bi-hourglass-split"></i> <?= t('loading') ?>
                                        </span>
                                    </div>
                                    <button class="btn btn-light btn-sm" onclick="loadPerformanceData()">
                                        <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System-Status -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="bi bi-activity"></i> System-Status</h5>
                                    <button class="btn btn-sm btn-outline-primary" onclick="refreshSystemStatus()">
                                        <i class="bi bi-arrow-clockwise"></i> Aktualisieren
                                    </button>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-2 col-sm-6 mb-3">
                                            <div class="d-flex align-items-center" data-api="proxmox">
                                                <div class="status-indicator online me-2"></div>
                                                <div>
                                                    <strong>Proxmox</strong><br>
                                                    <small class="text-muted"><?= $serviceManager->testAllAPIConnections("proxmox") ? 'Verbunden' : 'Nicht verbunden' ?></small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-2 col-sm-6 mb-3">
                                            <div class="d-flex align-items-center" data-api="ispconfig">
                                                <div class="status-indicator online me-2"></div>
                                                <div>
                                                    <strong>ISPConfig</strong><br>
                                                    <small class="text-muted"><?= $serviceManager->testAllAPIConnections("ispconfig") ? 'Verbunden' : 'Nicht verbunden' ?></small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-2 col-sm-6 mb-3">
                                            <div class="d-flex align-items-center" data-api="ovh">
                                                <div class="status-indicator online me-2"></div>
                                                <div>
                                                    <strong>OVH API</strong><br>
                                                    <small class="text-muted"><?= $serviceManager->testAllAPIConnections("ovh") ? 'Verbunden' : 'Nicht verbunden' ?></small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-2 col-sm-6 mb-3">
                                            <div class="d-flex align-items-center" data-api="database">
                                                <div class="status-indicator online me-2"></div>
                                                <div>
                                                    <strong>Datenbank</strong><br>
                                                    <small class="text-muted"><?= $serviceManager->testAllAPIConnections("database") ? 'Verbunden' : 'Nicht verbunden' ?></small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-2 col-sm-6 mb-3">
                                            <div class="d-flex align-items-center" data-api="ogp">
                                                <div class="status-indicator online me-2"></div>
                                                <div>
                                                    <strong>Open Game Panel</strong><br>
                                                    <small class="text-muted"><?= $serviceManager->testAllAPIConnections("ogp") ? 'Verbunden' : 'Nicht verbunden' ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
