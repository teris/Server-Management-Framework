<div class="admin-module">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0"><i class="bi bi-gear"></i> <?php echo $translations['module_title']; ?></h3>
                </div>
                <div class="card-body">
                    <!-- Schnellaktionen -->
                    <div class="row mb-4">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card border-0 bg-primary text-white">
                                <div class="card-body text-center">
                                    <i class="bi bi-display display-4"></i>
                                    <h5 class="mt-2"><?php echo $translations['manage_vms']; ?></h5>
                                    <button class="btn btn-light btn-sm" onclick="loadVMData()">
                                        <i class="bi bi-arrow-clockwise"></i> <?php echo $translations['refresh']; ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card border-0 bg-success text-white">
                                <div class="card-body text-center">
                                    <i class="bi bi-globe display-4"></i>
                                    <h5 class="mt-2"><?php echo $translations['websites']; ?></h5>
                                    <button class="btn btn-light btn-sm" onclick="loadWebsiteData()">
                                        <i class="bi bi-arrow-clockwise"></i> <?php echo $translations['refresh']; ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card border-0 bg-info text-white">
                                <div class="card-body text-center">
                                    <i class="bi bi-database display-4"></i>
                                    <h5 class="mt-2"><?php echo $translations['databases']; ?></h5>
                                    <button class="btn btn-light btn-sm" onclick="loadDatabaseData()">
                                        <i class="bi bi-arrow-clockwise"></i> <?php echo $translations['refresh']; ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card border-0 bg-warning text-white">
                                <div class="card-body text-center">
                                    <i class="bi bi-envelope display-4"></i>
                                    <h5 class="mt-2"><?php echo $translations['emails']; ?></h5>
                                    <button class="btn btn-light btn-sm" onclick="loadEmailData()">
                                        <i class="bi bi-arrow-clockwise"></i> <?php echo $translations['refresh']; ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System-Status -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="bi bi-activity"></i> System-Status</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3 col-sm-6 mb-3">
                                            <div class="d-flex align-items-center">
                                                <div class="status-indicator online me-2"></div>
                                                <div>
                                                    <strong>Proxmox</strong><br>
                                                    <small class="text-muted">Verbunden</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-sm-6 mb-3">
                                            <div class="d-flex align-items-center">
                                                <div class="status-indicator online me-2"></div>
                                                <div>
                                                    <strong>ISPConfig</strong><br>
                                                    <small class="text-muted">Verbunden</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-sm-6 mb-3">
                                            <div class="d-flex align-items-center">
                                                <div class="status-indicator online me-2"></div>
                                                <div>
                                                    <strong>OVH API</strong><br>
                                                    <small class="text-muted">Verbunden</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-sm-6 mb-3">
                                            <div class="d-flex align-items-center">
                                                <div class="status-indicator online me-2"></div>
                                                <div>
                                                    <strong>Datenbank</strong><br>
                                                    <small class="text-muted">Verbunden</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ressourcen-Verwaltung -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="bi bi-hdd-stack"></i> Ressourcen-Verwaltung</h5>
                                </div>
                                <div class="card-body">
                                    <ul class="nav nav-pills mb-3" id="adminResourceTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="admin-vms-tab" data-bs-toggle="pill" data-bs-target="#admin-vms-content" type="button" role="tab">
                                                <i class="bi bi-display"></i> VMs
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="admin-websites-tab" data-bs-toggle="pill" data-bs-target="#admin-websites-content" type="button" role="tab">
                                                <i class="bi bi-globe"></i> Websites
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="admin-databases-tab" data-bs-toggle="pill" data-bs-target="#admin-databases-content" type="button" role="tab">
                                                <i class="bi bi-database"></i> Datenbanken
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="admin-emails-tab" data-bs-toggle="pill" data-bs-target="#admin-emails-content" type="button" role="tab">
                                                <i class="bi bi-envelope"></i> E-Mails
                                            </button>
                                        </li>
                                    </ul>

                                    <div class="tab-content" id="adminResourceTabContent">
                                        <!-- VMs -->
                                        <div class="tab-pane fade show active" id="admin-vms-content" role="tabpanel">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h6>Virtuelle Maschinen</h6>
                                                <div class="btn-group">
                                                    <button class="btn btn-primary btn-sm" onclick="loadVMData()">
                                                        <i class="bi bi-arrow-clockwise"></i> Aktualisieren
                                                    </button>
                                                    <button class="btn btn-success btn-sm" onclick="createVM()">
                                                        <i class="bi bi-plus"></i> Neue VM
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
                                                <h6>Websites</h6>
                                                <div class="btn-group">
                                                    <button class="btn btn-primary btn-sm" onclick="loadWebsiteData()">
                                                        <i class="bi bi-arrow-clockwise"></i> Aktualisieren
                                                    </button>
                                                    <button class="btn btn-success btn-sm" onclick="createWebsite()">
                                                        <i class="bi bi-plus"></i> Neue Website
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
                                                <h6>Datenbanken</h6>
                                                <div class="btn-group">
                                                    <button class="btn btn-primary btn-sm" onclick="loadDatabaseData()">
                                                        <i class="bi bi-arrow-clockwise"></i> Aktualisieren
                                                    </button>
                                                    <button class="btn btn-success btn-sm" onclick="createDatabase()">
                                                        <i class="bi bi-plus"></i> Neue Datenbank
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
                                                <h6>E-Mail-Konten</h6>
                                                <div class="btn-group">
                                                    <button class="btn btn-primary btn-sm" onclick="loadEmailData()">
                                                        <i class="bi bi-arrow-clockwise"></i> Aktualisieren
                                                    </button>
                                                    <button class="btn btn-success btn-sm" onclick="createEmail()">
                                                        <i class="bi bi-plus"></i> Neues E-Mail-Konto
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
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System-Logs -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="bi bi-journal-text"></i> System-Logs</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="btn-group">
                                            <button class="btn btn-outline-primary btn-sm" onclick="loadLogs()">
                                                <i class="bi bi-arrow-clockwise"></i> Logs laden
                                            </button>
                                            <button class="btn btn-outline-danger btn-sm" onclick="clearLogs()">
                                                <i class="bi bi-trash"></i> Logs leeren
                                            </button>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="autoRefreshLogs">
                                            <label class="form-check-label" for="autoRefreshLogs">
                                                Auto-Refresh
                                            </label>
                                        </div>
                                    </div>
                                    <div id="admin-logs-content" class="table-responsive">
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
        </div>
    </div>
</div>

<script>
// Admin-Modul spezifische Funktionen
$(document).ready(function() {
    // Tab-Wechsel für Admin-Ressourcen
    $('#adminResourceTabs .nav-link').on('click', function() {
        const target = $(this).data('bs-target');
        const resourceType = target.replace('#admin-', '').replace('-content', '');
        
        // Entsprechende Daten laden
        switch(resourceType) {
            case 'vms':
                loadAdminVMData();
                break;
            case 'websites':
                loadAdminWebsiteData();
                break;
            case 'databases':
                loadAdminDatabaseData();
                break;
            case 'emails':
                loadAdminEmailData();
                break;
        }
    });
    
    // Auto-Refresh für Logs
    $('#autoRefreshLogs').on('change', function() {
        if ($(this).is(':checked')) {
            startLogAutoRefresh();
        } else {
            stopLogAutoRefresh();
        }
    });
    
    // Erste Daten laden
    loadAdminVMData();
});

// Admin-spezifische Datenlade-Funktionen
function loadAdminVMData() {
    const container = $('#admin-vm-table');
    Utils.showLoading(container);
    
    AjaxHandler.adminRequest('get_resources', { type: 'vms' })
        .done(function(response) {
            if (response.success) {
                renderAdminVMTable(container, response.data.data);
            } else {
                Utils.showError(container, 'Fehler beim Laden der VMs: ' + (response.error || 'Unbekannter Fehler'));
            }
        })
        .fail(function(xhr, status, error) {
            Utils.showError(container, 'Fehler beim Laden der VMs: ' + error);
        });
}

function renderAdminVMTable(container, vms) {
    if (!vms || vms.length === 0) {
        container.html('<div class="alert alert-info">Keine VMs gefunden.</div>');
        return;
    }
    
    let html = '<table class="table table-striped table-hover">';
    html += '<thead><tr><th>ID</th><th>Name</th><th>Status</th><th>CPU</th><th>RAM</th><th>Speicher</th><th>Node</th><th>Aktionen</th></tr></thead><tbody>';
    
    vms.forEach(function(vm) {
        const statusClass = vm.status === 'running' ? 'success' : (vm.status === 'stopped' ? 'danger' : 'warning');
        html += '<tr>';
        html += '<td>' + (vm.vmid || vm.id) + '</td>';
        html += '<td>' + vm.name + '</td>';
        html += '<td><span class="badge bg-' + statusClass + '">' + vm.status + '</span></td>';
        html += '<td>' + (vm.cores || vm.cpu || '-') + '</td>';
        html += '<td>' + (vm.memory ? Math.round(vm.memory/1024/1024) + ' MB' : (vm.ram || '-')) + '</td>';
        html += '<td>' + (vm.storage || '-') + '</td>';
        html += '<td>' + (vm.node || '-') + '</td>';
        html += '<td>';
        if (vm.status === 'running') {
            html += '<button class="btn btn-warning btn-sm me-1" onclick="controlVM(\'' + (vm.vmid || vm.id) + '\', \'stop\')"><i class="bi bi-pause"></i></button>';
        } else {
            html += '<button class="btn btn-success btn-sm me-1" onclick="controlVM(\'' + (vm.vmid || vm.id) + '\', \'start\')"><i class="bi bi-play"></i></button>';
        }
        html += '<button class="btn btn-secondary btn-sm me-1" onclick="controlVM(\'' + (vm.vmid || vm.id) + '\', \'reset\')"><i class="bi bi-arrow-clockwise"></i></button>';
        html += '<button class="btn btn-danger btn-sm" onclick="deleteVM(\'' + (vm.vmid || vm.id) + '\')"><i class="bi bi-trash"></i></button>';
        html += '</td>';
        html += '</tr>';
    });
    
    html += '</tbody></table>';
    container.html(html);
}

function loadAdminWebsiteData() {
    const container = $('#admin-website-table');
    Utils.showLoading(container);
    
    AjaxHandler.adminRequest('get_resources', { type: 'websites' })
        .done(function(response) {
            if (response.success) {
                renderAdminWebsiteTable(container, response.data.data);
            } else {
                Utils.showError(container, 'Fehler beim Laden der Websites: ' + (response.error || 'Unbekannter Fehler'));
            }
        })
        .fail(function(xhr, status, error) {
            Utils.showError(container, 'Fehler beim Laden der Websites: ' + error);
        });
}

function renderAdminWebsiteTable(container, websites) {
    if (!websites || websites.length === 0) {
        container.html('<div class="alert alert-info">Keine Websites gefunden.</div>');
        return;
    }
    
    let html = '<table class="table table-striped table-hover">';
    html += '<thead><tr><th>Domain</th><th>IP</th><th>Benutzer</th><th>Status</th><th>Quota</th><th>Aktionen</th></tr></thead><tbody>';
    
    websites.forEach(function(website) {
        const statusClass = website.active === 'y' ? 'success' : 'danger';
        html += '<tr>';
        html += '<td>' + website.domain + '</td>';
        html += '<td>' + (website.ip_address || '-') + '</td>';
        html += '<td>' + (website.system_user || '-') + '</td>';
        html += '<td><span class="badge bg-' + statusClass + '">' + (website.active === 'y' ? 'Aktiv' : 'Inaktiv') + '</span></td>';
        html += '<td>' + (website.hd_quota || '-') + '</td>';
        html += '<td>';
        html += '<button class="btn btn-primary btn-sm me-1" onclick="editWebsite(\'' + website.domain_id + '\')"><i class="bi bi-pencil"></i></button>';
        html += '<button class="btn btn-danger btn-sm" onclick="deleteWebsite(\'' + website.domain_id + '\')"><i class="bi bi-trash"></i></button>';
        html += '</td>';
        html += '</tr>';
    });
    
    html += '</tbody></table>';
    container.html(html);
}

function loadAdminDatabaseData() {
    const container = $('#admin-database-table');
    Utils.showLoading(container);
    
    AjaxHandler.adminRequest('get_resources', { type: 'databases' })
        .done(function(response) {
            if (response.success) {
                renderAdminDatabaseTable(container, response.data.data);
            } else {
                Utils.showError(container, 'Fehler beim Laden der Datenbanken: ' + (response.error || 'Unbekannter Fehler'));
            }
        })
        .fail(function(xhr, status, error) {
            Utils.showError(container, 'Fehler beim Laden der Datenbanken: ' + error);
        });
}

function renderAdminDatabaseTable(container, databases) {
    if (!databases || databases.length === 0) {
        container.html('<div class="alert alert-info">Keine Datenbanken gefunden.</div>');
        return;
    }
    
    let html = '<table class="table table-striped table-hover">';
    html += '<thead><tr><th>Name</th><th>Benutzer</th><th>Typ</th><th>Status</th><th>Aktionen</th></tr></thead><tbody>';
    
    databases.forEach(function(database) {
        const statusClass = database.active === 'y' ? 'success' : 'danger';
        html += '<tr>';
        html += '<td>' + database.database_name + '</td>';
        html += '<td>' + database.database_user + '</td>';
        html += '<td>' + (database.database_type || 'mysql') + '</td>';
        html += '<td><span class="badge bg-' + statusClass + '">' + (database.active === 'y' ? 'Aktiv' : 'Inaktiv') + '</span></td>';
        html += '<td>';
        html += '<button class="btn btn-primary btn-sm me-1" onclick="editDatabase(\'' + database.database_id + '\')"><i class="bi bi-pencil"></i></button>';
        html += '<button class="btn btn-danger btn-sm" onclick="deleteDatabase(\'' + database.database_id + '\')"><i class="bi bi-trash"></i></button>';
        html += '</td>';
        html += '</tr>';
    });
    
    html += '</tbody></table>';
    container.html(html);
}

function loadAdminEmailData() {
    const container = $('#admin-email-table');
    Utils.showLoading(container);
    
    AjaxHandler.adminRequest('get_resources', { type: 'emails' })
        .done(function(response) {
            if (response.success) {
                renderAdminEmailTable(container, response.data.data);
            } else {
                Utils.showError(container, 'Fehler beim Laden der E-Mails: ' + (response.error || 'Unbekannter Fehler'));
            }
        })
        .fail(function(xhr, status, error) {
            Utils.showError(container, 'Fehler beim Laden der E-Mails: ' + error);
        });
}

function renderAdminEmailTable(container, emails) {
    if (!emails || emails.length === 0) {
        container.html('<div class="alert alert-info">Keine E-Mail-Konten gefunden.</div>');
        return;
    }
    
    let html = '<table class="table table-striped table-hover">';
    html += '<thead><tr><th>E-Mail</th><th>Name</th><th>Quota</th><th>Status</th><th>Aktionen</th></tr></thead><tbody>';
    
    emails.forEach(function(email) {
        const statusClass = email.active === 'y' ? 'success' : 'danger';
        html += '<tr>';
        html += '<td>' + email.email + '</td>';
        html += '<td>' + (email.name || '-') + '</td>';
        html += '<td>' + (email.quota || '-') + '</td>';
        html += '<td><span class="badge bg-' + statusClass + '">' + (email.active === 'y' ? 'Aktiv' : 'Inaktiv') + '</span></td>';
        html += '<td>';
        html += '<button class="btn btn-primary btn-sm me-1" onclick="editEmail(\'' + email.mailuser_id + '\')"><i class="bi bi-pencil"></i></button>';
        html += '<button class="btn btn-danger btn-sm" onclick="deleteEmail(\'' + email.mailuser_id + '\')"><i class="bi bi-trash"></i></button>';
        html += '</td>';
        html += '</tr>';
    });
    
    html += '</tbody></table>';
    container.html(html);
}

// VM-Steuerung
function controlVM(vmId, action) {
    if (!confirm(`Möchten Sie wirklich "${action}" für VM ${vmId} ausführen?`)) {
        return;
    }
    
    AjaxHandler.adminRequest('control_vm', { vm_id: vmId, control: action })
        .done(function(response) {
            if (response.success) {
                Utils.showNotification(`VM ${vmId} ${action} erfolgreich ausgeführt`, 'success');
                loadAdminVMData();
            } else {
                Utils.showNotification('Fehler: ' + (response.error || 'Unbekannter Fehler'), 'error');
            }
        })
        .fail(function(xhr, status, error) {
            Utils.showNotification('Fehler: ' + error, 'error');
        });
}

function deleteVM(vmId) {
    if (!confirm(`Möchten Sie VM ${vmId} wirklich PERMANENT löschen? Diese Aktion kann nicht rückgängig gemacht werden!`)) {
        return;
    }
    
    AjaxHandler.adminRequest('delete_vm', { vm_id: vmId })
        .done(function(response) {
            if (response.success) {
                Utils.showNotification(`VM ${vmId} wurde erfolgreich gelöscht`, 'success');
                loadAdminVMData();
            } else {
                Utils.showNotification('Fehler: ' + (response.error || 'Unbekannter Fehler'), 'error');
            }
        })
        .fail(function(xhr, status, error) {
            Utils.showNotification('Fehler: ' + error, 'error');
        });
}

// Website-Steuerung
function editWebsite(domainId) {
    Utils.showNotification('Website-Bearbeitung noch nicht implementiert', 'info');
}

function deleteWebsite(domainId) {
    if (!confirm('Möchten Sie diese Website wirklich löschen?')) {
        return;
    }
    
    AjaxHandler.adminRequest('delete_website', { domain_id: domainId })
        .done(function(response) {
            if (response.success) {
                Utils.showNotification('Website wurde erfolgreich gelöscht', 'success');
                loadAdminWebsiteData();
            } else {
                Utils.showNotification('Fehler: ' + (response.error || 'Unbekannter Fehler'), 'error');
            }
        })
        .fail(function(xhr, status, error) {
            Utils.showNotification('Fehler: ' + error, 'error');
        });
}

// Datenbank-Steuerung
function editDatabase(databaseId) {
    Utils.showNotification('Datenbank-Bearbeitung noch nicht implementiert', 'info');
}

function deleteDatabase(databaseId) {
    if (!confirm('Möchten Sie diese Datenbank wirklich löschen?')) {
        return;
    }
    
    AjaxHandler.adminRequest('delete_database', { database_id: databaseId })
        .done(function(response) {
            if (response.success) {
                Utils.showNotification('Datenbank wurde erfolgreich gelöscht', 'success');
                loadAdminDatabaseData();
            } else {
                Utils.showNotification('Fehler: ' + (response.error || 'Unbekannter Fehler'), 'error');
            }
        })
        .fail(function(xhr, status, error) {
            Utils.showNotification('Fehler: ' + error, 'error');
        });
}

// E-Mail-Steuerung
function editEmail(mailuserId) {
    Utils.showNotification('E-Mail-Bearbeitung noch nicht implementiert', 'info');
}

function deleteEmail(mailuserId) {
    if (!confirm('Möchten Sie diese E-Mail-Adresse wirklich löschen?')) {
        return;
    }
    
    AjaxHandler.adminRequest('delete_email', { mailuser_id: mailuserId })
        .done(function(response) {
            if (response.success) {
                Utils.showNotification('E-Mail-Adresse wurde erfolgreich gelöscht', 'success');
                loadAdminEmailData();
            } else {
                Utils.showNotification('Fehler: ' + (response.error || 'Unbekannter Fehler'), 'error');
            }
        })
        .fail(function(xhr, status, error) {
            Utils.showNotification('Fehler: ' + error, 'error');
        });
}

// Erstellungs-Funktionen
function createVM() {
    Utils.showNotification('VM-Erstellung noch nicht implementiert', 'info');
}

function createWebsite() {
    Utils.showNotification('Website-Erstellung noch nicht implementiert', 'info');
}

function createDatabase() {
    Utils.showNotification('Datenbank-Erstellung noch nicht implementiert', 'info');
}

function createEmail() {
    Utils.showNotification('E-Mail-Erstellung noch nicht implementiert', 'info');
}

// Log-Funktionen
if (typeof window.logAutoRefreshInterval === 'undefined') {
    window.logAutoRefreshInterval = null;
}

function loadLogs() {
    const container = $('#admin-logs-content');
    Utils.showLoading(container);
    
    AjaxHandler.adminRequest('get_activity_logs')
        .done(function(response) {
            if (response.success) {
                renderLogs(container, response.data.logs);
            } else {
                Utils.showError(container, 'Fehler beim Laden der Logs: ' + (response.error || 'Unbekannter Fehler'));
            }
        })
        .fail(function(xhr, status, error) {
            Utils.showError(container, 'Fehler beim Laden der Logs: ' + error);
        });
}

function renderLogs(container, logs) {
    if (!logs || logs.length === 0) {
        container.html('<div class="alert alert-info">Keine Log-Einträge gefunden.</div>');
        return;
    }
    
    let html = '<table class="table table-striped table-hover">';
    html += '<thead><tr><th>Zeitstempel</th><th>Aktion</th><th>Details</th><th>Status</th></tr></thead><tbody>';
    
    logs.forEach(function(log) {
        const statusClass = log.status === 'success' ? 'success' : 'danger';
        html += '<tr>';
        html += '<td>' + new Date(log.created_at).toLocaleString('de-DE') + '</td>';
        html += '<td>' + (log.action || '-') + '</td>';
        html += '<td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;">' + (log.details || '-') + '</td>';
        html += '<td><span class="badge bg-' + statusClass + '">' + (log.status || '-') + '</span></td>';
        html += '</tr>';
    });
    
    html += '</tbody></table>';
    container.html(html);
}

function clearLogs() {
    if (!confirm('Möchten Sie wirklich alle Logs löschen?')) {
        return;
    }
    
    AjaxHandler.adminRequest('clear_activity_logs')
        .done(function(response) {
            if (response.success) {
                Utils.showNotification('Logs wurden erfolgreich gelöscht', 'success');
                loadLogs();
            } else {
                Utils.showNotification('Fehler: ' + (response.error || 'Unbekannter Fehler'), 'error');
            }
        })
        .fail(function(xhr, status, error) {
            Utils.showNotification('Fehler: ' + error, 'error');
        });
}

function startLogAutoRefresh() {
    if (window.logAutoRefreshInterval) {
        clearInterval(window.logAutoRefreshInterval);
    }
    window.logAutoRefreshInterval = setInterval(loadLogs, 10000); // Alle 10 Sekunden
    Utils.showNotification('Auto-Refresh für Logs aktiviert', 'info');
}

function stopLogAutoRefresh() {
    if (window.logAutoRefreshInterval) {
        clearInterval(window.logAutoRefreshInterval);
        window.logAutoRefreshInterval = null;
    }
    Utils.showNotification('Auto-Refresh für Logs deaktiviert', 'info');
}
</script>