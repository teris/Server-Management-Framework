<?php
// Template-Daten aus der globalen Variable verfügbar machen
if (isset($GLOBALS['_template_data'])) {
    extract($GLOBALS['_template_data']);
}

// Übersetzungen werden jetzt über die globale t() Funktion geladen
?>
<!-- Ressourcen-Verwaltung -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-hdd-stack"></i> <?= t('resource_management') ?></h5>
            </div>
            <div class="card-body">
                <ul class="nav nav-pills mb-3" id="adminResourceTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="admin-vms-tab" data-bs-toggle="pill" data-bs-target="#admin-vms-content" type="button" role="tab">
                            <i class="bi bi-display"></i> <?= t('virtual_machines') ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="admin-websites-tab" data-bs-toggle="pill" data-bs-target="#admin-websites-content" type="button" role="tab">
                            <i class="bi bi-globe"></i> <?= t('websites') ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="admin-databases-tab" data-bs-toggle="pill" data-bs-target="#admin-databases-content" type="button" role="tab">
                            <i class="bi bi-database"></i> <?= t('databases') ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="admin-emails-tab" data-bs-toggle="pill" data-bs-target="#admin-emails-content" type="button" role="tab">
                            <i class="bi bi-envelope"></i> <?= t('emails') ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="admin-gameservers-tab" data-bs-toggle="pill" data-bs-target="#admin-gameservers-content" type="button" role="tab">
                            <i class="bi bi-controller"></i> <?= t('gameservers') ?>
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="adminResourceTabContent">
                    <!-- VMs -->
                    <div class="tab-pane fade show active" id="admin-vms-content" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6><?= t('virtual_machines') ?></h6>
                            <div class="btn-group">
                                <button class="btn btn-primary btn-sm" onclick="loadVMData()">
                                    <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                                </button>
                                <button class="btn btn-success btn-sm" onclick="createVM()">
                                    <i class="bi bi-plus"></i> <?= t('new_vm') ?>
                                </button>
                            </div>
                        </div>
                        <div id="admin-vm-table" class="table-responsive">
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Laden...</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Websites -->
                    <div class="tab-pane fade" id="admin-websites-content" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6><?= t('websites') ?></h6>
                            <div class="btn-group">
                                <button class="btn btn-primary btn-sm" onclick="loadWebsiteData()">
                                    <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                                </button>
                                <button class="btn btn-success btn-sm" onclick="createWebsite()">
                                    <i class="bi bi-plus"></i> <?= t('new_website') ?>
                                </button>
                            </div>
                        </div>
                        <div id="admin-website-table" class="table-responsive">
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Laden...</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Datenbanken -->
                    <div class="tab-pane fade" id="admin-databases-content" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6><?= t('databases') ?></h6>
                            <div class="btn-group">
                                <button class="btn btn-primary btn-sm" onclick="loadDatabaseData()">
                                    <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                                </button>
                                <button class="btn btn-success btn-sm" onclick="createDatabase()">
                                    <i class="bi bi-plus"></i> <?= t('new_database') ?>
                                </button>
                            </div>
                        </div>
                        <div id="admin-database-table" class="table-responsive">
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Laden...</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- E-Mails -->
                    <div class="tab-pane fade" id="admin-emails-content" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6><?= t('emails') ?></h6>
                            <div class="btn-group">
                                <button class="btn btn-primary btn-sm" onclick="loadEmailData()">
                                    <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                                </button>
                                <button class="btn btn-success btn-sm" onclick="createEmail()">
                                    <i class="bi bi-plus"></i> <?= t('new_email_account') ?>
                                </button>
                            </div>
                        </div>
                        <div id="admin-email-table" class="table-responsive">
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Laden...</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Gameserver -->
                    <div class="tab-pane fade" id="admin-gameservers-content" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6><?= t('gameservers') ?></h6>
                            <div class="btn-group">
                                <button class="btn btn-primary btn-sm" onclick="loadGameserverData()">
                                    <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                                </button>
                                <button class="btn btn-success btn-sm" onclick="createGameserver()">
                                    <i class="bi bi-plus"></i> <?= t('new_gameserver') ?>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Gameserver Statistiken -->
                        <div class="row mb-4">
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6 class="card-title"><?= t('total_servers') ?></h6>
                                                <h4 id="total-gameservers">-</h4>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="bi bi-server fs-1"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6 class="card-title"><?= t('online_servers') ?></h6>
                                                <h4 id="online-gameservers">-</h4>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="bi bi-play-circle fs-1"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6 class="card-title"><?= t('players_online') ?></h6>
                                                <h4 id="online-players">-</h4>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="bi bi-people fs-1"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="card bg-warning text-white">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6 class="card-title"><?= t('active_servers') ?></h6>
                                                <h4 id="active-gameservers">-</h4>
                                            </div>
                                            <div class="align-self-center">
                                                <i class="bi bi-activity fs-1"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Gameserver Tabelle -->
                        <div id="admin-gameserver-table" class="table-responsive">
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Laden...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
