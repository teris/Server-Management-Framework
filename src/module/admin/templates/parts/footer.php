<?php
// Template-Daten aus der globalen Variable verfügbar machen
if (isset($GLOBALS['_template_data'])) {
    extract($GLOBALS['_template_data']);
}

// Erstelle Übersetzungen direkt hier
if (!isset($translations)) {
    $translations = [
        'module_title' => 'Admin Dashboard',
        'manage_vms' => 'VMs verwalten',
        'websites' => 'Websites',
        'databases' => 'Datenbanken',
        'emails' => 'E-Mails',
        'refresh' => 'Aktualisieren',
        'system_status' => 'System-Status',
        'connected' => 'Verbunden',
        'proxmox' => 'Proxmox',
        'ispconfig' => 'ISPConfig',
        'ovh_api' => 'OVH API',
        'database' => 'Datenbank',
        'resource_management' => 'Ressourcen-Verwaltung',
        'virtual_machines' => 'Virtuelle Maschinen',
        'new_vm' => 'Neue VM',
        'new_website' => 'Neue Website',
        'new_database' => 'Neue Datenbank',
        'new_email_account' => 'Neues E-Mail-Konto',
        'system_logs' => 'System-Logs',
        'load_logs' => 'Logs laden',
        'clear_logs' => 'Logs löschen',
        'loading' => 'Lädt...',
        'actions' => 'Aktionen',
        'name' => 'Name',
        'status' => 'Status',
        'created' => 'Erstellt',
        'updated' => 'Aktualisiert',
        'edit' => 'Bearbeiten',
        'delete' => 'Löschen',
        'view' => 'Anzeigen',
        'create' => 'Erstellen',
        'save' => 'Speichern',
        'cancel' => 'Abbrechen',
        'confirm' => 'Bestätigen'
    ];
}
?>
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
    showLoading(container);
    
    makeAdminRequest('get_all_vms')
        .done(function(response) {
            if (response.success) {
                renderAdminVMTable(container, response.data);
            } else {
                showError(container, 'Fehler beim Laden der VMs: ' + (response.error || 'Unbekannter Fehler'));
            }
        })
        .fail(function(xhr, status, error) {
            showError(container, 'Fehler beim Laden der VMs: ' + error);
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
    showLoading(container);
    
    makeAdminRequest('get_all_websites')
        .done(function(response) {
            if (response.success) {
                renderAdminWebsiteTable(container, response.data);
            } else {
                showError(container, 'Fehler beim Laden der Websites: ' + (response.error || 'Unbekannter Fehler'));
            }
        })
        .fail(function(xhr, status, error) {
            showError(container, 'Fehler beim Laden der Websites: ' + error);
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
    showLoading(container);
    
    makeAdminRequest('get_all_databases')
        .done(function(response) {
            if (response.success) {
                renderAdminDatabaseTable(container, response.data);
            } else {
                showError(container, 'Fehler beim Laden der Datenbanken: ' + (response.error || 'Unbekannter Fehler'));
            }
        })
        .fail(function(xhr, status, error) {
            showError(container, 'Fehler beim Laden der Datenbanken: ' + error);
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
    showLoading(container);
    
    makeAdminRequest('get_all_emails')
        .done(function(response) {
            if (response.success) {
                renderAdminEmailTable(container, response.data);
            } else {
                showError(container, 'Fehler beim Laden der E-Mails: ' + (response.error || 'Unbekannter Fehler'));
            }
        })
        .fail(function(xhr, status, error) {
            showError(container, 'Fehler beim Laden der E-Mails: ' + error);
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
    
    makeAdminRequest('control_vm', { vm_id: vmId, control: action })
        .done(function(response) {
            if (response.success) {
                showNotification(`VM ${vmId} ${action} erfolgreich ausgeführt`, 'success');
                loadAdminVMData();
            } else {
                showNotification('Fehler: ' + (response.error || 'Unbekannter Fehler'), 'error');
            }
        })
        .fail(function(xhr, status, error) {
            showNotification('Fehler: ' + error, 'error');
        });
}

function deleteVM(vmId) {
    if (!confirm(`Möchten Sie VM ${vmId} wirklich PERMANENT löschen? Diese Aktion kann nicht rückgängig gemacht werden!`)) {
        return;
    }
    
    $.ajax({
        url: 'index.php',
        type: 'POST',
        data: {
            option: 'ajax',
            module: 'admin',
            action: 'delete_vm',
            vm_id: vmId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification(`VM ${vmId} wurde erfolgreich gelöscht`, 'success');
                loadAdminVMData();
            } else {
                showNotification('Fehler: ' + (response.error || 'Unbekannter Fehler'), 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('Fehler: ' + error, 'error');
        }
    });
}

// Website-Steuerung
function editWebsite(domainId) {
    showNotification('Website-Bearbeitung noch nicht implementiert', 'info');
}

function deleteWebsite(domainId) {
    if (!confirm('Möchten Sie diese Website wirklich löschen?')) {
        return;
    }
    
    $.ajax({
        url: 'index.php',
        type: 'POST',
        data: {
            option: 'ajax',
            module: 'admin',
            action: 'delete_website',
            domain_id: domainId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification('Website wurde erfolgreich gelöscht', 'success');
                loadAdminWebsiteData();
            } else {
                showNotification('Fehler: ' + (response.error || 'Unbekannter Fehler'), 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('Fehler: ' + error, 'error');
        }
    });
}

// Datenbank-Steuerung
function editDatabase(databaseId) {
    showNotification('Datenbank-Bearbeitung noch nicht implementiert', 'info');
}

function deleteDatabase(databaseId) {
    if (!confirm('Möchten Sie diese Datenbank wirklich löschen?')) {
        return;
    }
    
    $.ajax({
        url: 'index.php',
        type: 'POST',
        data: {
            option: 'ajax',
            module: 'admin',
            action: 'delete_database',
            database_id: databaseId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification('Datenbank wurde erfolgreich gelöscht', 'success');
                loadAdminDatabaseData();
            } else {
                showNotification('Fehler: ' + (response.error || 'Unbekannter Fehler'), 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('Fehler: ' + error, 'error');
        }
    });
}

// E-Mail-Steuerung
function editEmail(mailuserId) {
    showNotification('E-Mail-Bearbeitung noch nicht implementiert', 'info');
}

function deleteEmail(mailuserId) {
    if (!confirm('Möchten Sie diese E-Mail-Adresse wirklich löschen?')) {
        return;
    }
    
    $.ajax({
        url: 'index.php',
        type: 'POST',
        data: {
            option: 'ajax',
            module: 'admin',
            action: 'delete_email',
            mailuser_id: mailuserId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification('E-Mail-Adresse wurde erfolgreich gelöscht', 'success');
                loadAdminEmailData();
            } else {
                showNotification('Fehler: ' + (response.error || 'Unbekannter Fehler'), 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('Fehler: ' + error, 'error');
        }
    });
}

// Erstellungs-Funktionen
function createVM() {
    showNotification('VM-Erstellung noch nicht implementiert', 'info');
}

function createWebsite() {
    showNotification('Website-Erstellung noch nicht implementiert', 'info');
}

function createDatabase() {
    showNotification('Datenbank-Erstellung noch nicht implementiert', 'info');
}

function createEmail() {
    showNotification('E-Mail-Erstellung noch nicht implementiert', 'info');
}

// Log-Funktionen
if (typeof window.logAutoRefreshInterval === 'undefined') {
    window.logAutoRefreshInterval = null;
}

function loadLogs() {
    const container = $('#admin-logs-content');
    showLoading(container);
    
    makeAdminRequest('get_activity_log')
        .done(function(response) {
            if (response.success) {
                renderLogs(container, response.data);
            } else {
                showError(container, 'Fehler beim Laden der Logs: ' + (response.error || 'Unbekannter Fehler'));
            }
        })
        .fail(function(xhr, status, error) {
            showError(container, 'Fehler beim Laden der Logs: ' + error);
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
    
    $.ajax({
        url: 'index.php',
        type: 'POST',
        data: {
            option: 'ajax',
            module: 'admin',
            action: 'clear_activity_logs'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification('Logs wurden erfolgreich gelöscht', 'success');
                loadLogs();
            } else {
                showNotification('Fehler: ' + (response.error || 'Unbekannter Fehler'), 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('Fehler: ' + error, 'error');
        }
    });
}

function startLogAutoRefresh() {
    if (window.logAutoRefreshInterval) {
        clearInterval(window.logAutoRefreshInterval);
    }
    window.logAutoRefreshInterval = setInterval(loadLogs, 10000); // Alle 10 Sekunden
    showNotification('Auto-Refresh für Logs aktiviert', 'info');
}

function stopLogAutoRefresh() {
    if (window.logAutoRefreshInterval) {
        clearInterval(window.logAutoRefreshInterval);
        window.logAutoRefreshInterval = null;
    }
    showNotification('Auto-Refresh für Logs deaktiviert', 'info');
}

// Helper-Funktionen
function showLoading(container) {
    container.html('<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Laden...</span></div></div>');
}

function showError(container, message) {
    container.html('<div class="alert alert-danger">' + message + '</div>');
}

function showNotification(message, type) {
    // Einfache Notification-Implementierung
    const alertClass = type === 'success' ? 'alert-success' : (type === 'error' ? 'alert-danger' : 'alert-info');
    const notification = $('<div class="alert ' + alertClass + ' alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;">' + message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
    $('body').append(notification);
    
    // Auto-remove nach 5 Sekunden
    setTimeout(function() {
        notification.alert('close');
    }, 5000);
}

// Hilfsfunktion für AJAX-Requests
function makeAdminRequest(action, data = {}) {
    if (typeof ModuleManager !== 'undefined') {
        return ModuleManager.request('admin', action, data);
    } else {
        return $.ajax({
            url: 'index.php',
            type: 'POST',
            data: {
                option: 'ajax',
                module: 'admin',
                action: action,
                ...data
            },
            dataType: 'json'
        });
    }
}
</script>

<!-- Admin OGP Integration -->
<link rel="stylesheet" href="/src/module/admin/assets/admin-ogp.css">
<script src="/src/module/admin/assets/admin-ogp.js"></script>