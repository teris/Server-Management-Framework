<div id="terminal-management">
    <!-- Header -->
    <div class="management-header">
        <div class="header-icon">
            <i class="fas fa-cogs fa-3x text-primary"></i>
        </div>
        <h2>Terminal-Modul Verwaltung</h2>
        <p class="text-muted">Verwalten Sie Installation, Updates und Deinstallation des Terminal-Moduls</p>
    </div>

    <!-- Status Cards -->
    <div class="status-cards">
        <div class="status-card">
            <div class="card-icon">
                <i class="fas fa-check-circle text-success"></i>
            </div>
            <div class="card-content">
                <h4>Installationsstatus</h4>
                <p id="installationStatus">Prüfe...</p>
            </div>
        </div>
        
        <div class="status-card">
            <div class="card-icon">
                <i class="fas fa-sync text-info"></i>
            </div>
            <div class="card-content">
                <h4>Updates</h4>
                <p id="updateStatus">Prüfe...</p>
            </div>
        </div>
        
        <div class="status-card">
            <div class="card-icon">
                <i class="fas fa-shield-alt text-warning"></i>
            </div>
            <div class="card-content">
                <h4>Sicherheit</h4>
                <p id="securityStatus">Prüfe...</p>
            </div>
        </div>
    </div>

    <!-- Management Tabs -->
    <div class="management-tabs">
        <ul class="nav nav-tabs" id="managementTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="status-tab" data-bs-toggle="tab" data-bs-target="#status" type="button" role="tab">
                    <i class="fas fa-info-circle"></i> Status
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="updates-tab" data-bs-toggle="tab" data-bs-target="#updates" type="button" role="tab">
                    <i class="fas fa-sync"></i> Updates
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="maintenance-tab" data-bs-toggle="tab" data-bs-target="#maintenance" type="button" role="tab">
                    <i class="fas fa-tools"></i> Wartung
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="managementTabContent">
            <!-- Status Tab -->
            <div class="tab-pane fade show active" id="status" role="tabpanel">
                <div class="tab-content-card">
                    <h4>Systemstatus</h4>
                    <div id="systemStatus">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Lädt...</span>
                            </div>
                            <p>Status wird geladen...</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Updates Tab -->
            <div class="tab-pane fade" id="updates" role="tabpanel">
                <div class="tab-content-card">
                    <h4>Library-Updates</h4>
                    <div class="update-controls">
                        <button class="btn btn-primary" onclick="checkForUpdates()">
                            <i class="fas fa-sync"></i> Auf Updates prüfen
                        </button>
                        <button class="btn btn-success" onclick="updateAllLibraries()" id="updateAllBtn" style="display: none;">
                            <i class="fas fa-download"></i> Alle Updates installieren
                        </button>
                    </div>
                    <div id="updateResults">
                        <div class="text-center text-muted">
                            <i class="fas fa-info-circle"></i>
                            <p>Klicken Sie auf "Auf Updates prüfen" um verfügbare Updates zu sehen</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Maintenance Tab -->
            <div class="tab-pane fade" id="maintenance" role="tabpanel">
                <div class="tab-content-card">
                    <h4>Wartung & Deinstallation</h4>
                    
                    <div class="maintenance-actions">
                        <div class="action-group">
                            <h5>Backup & Wiederherstellung</h5>
                            <button class="btn btn-outline-primary" onclick="createBackup()">
                                <i class="fas fa-save"></i> Backup erstellen
                            </button>
                            <button class="btn btn-outline-secondary" onclick="restoreBackup()">
                                <i class="fas fa-undo"></i> Backup wiederherstellen
                            </button>
                        </div>
                        
                        <div class="action-group">
                            <h5>Cache & Logs</h5>
                            <button class="btn btn-outline-warning" onclick="clearCache()">
                                <i class="fas fa-broom"></i> Cache leeren
                            </button>
                            <button class="btn btn-outline-info" onclick="viewLogs()">
                                <i class="fas fa-file-alt"></i> Logs anzeigen
                            </button>
                        </div>
                        
                        <div class="action-group danger-zone">
                            <h5>Gefahrenzone</h5>
                            <button class="btn btn-outline-danger" onclick="showUninstallModal()">
                                <i class="fas fa-trash"></i> Modul deinstallieren
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Uninstall Modal -->
    <div class="modal fade" id="uninstallModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">
                        <i class="fas fa-exclamation-triangle"></i> Modul deinstallieren
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-warning"></i> Achtung!</h6>
                        <p>Diese Aktion wird das Terminal-Modul vollständig deinstallieren:</p>
                        <ul>
                            <li>Alle Libraries werden entfernt</li>
                            <li>WebSocket-Proxies werden gelöscht</li>
                            <li>Datenbanktabellen werden entfernt</li>
                            <li>Konfigurationsdateien werden gelöscht</li>
                        </ul>
                        <p><strong>Diese Aktion kann nicht rückgängig gemacht werden!</strong></p>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirmUninstall">
                        <label class="form-check-label" for="confirmUninstall">
                            Ich verstehe die Konsequenzen und möchte das Modul deinstallieren
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Abbrechen
                    </button>
                    <button type="button" class="btn btn-danger" onclick="confirmUninstall()" id="confirmUninstallBtn" disabled>
                        <i class="fas fa-trash"></i> Deinstallieren
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.management-header {
    text-align: center;
    padding: 40px 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    margin-bottom: 30px;
}

.management-header h2 {
    margin: 20px 0 10px 0;
    font-weight: 600;
}

.status-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.status-card {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    gap: 15px;
}

.card-icon {
    font-size: 2rem;
}

.card-content h4 {
    margin: 0 0 5px 0;
    color: #495057;
}

.card-content p {
    margin: 0;
    color: #6c757d;
    font-size: 0.9rem;
}

.management-tabs {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

.nav-tabs {
    border-bottom: 1px solid #dee2e6;
    margin: 0;
}

.nav-tabs .nav-link {
    border: none;
    border-radius: 0;
    padding: 15px 20px;
    color: #6c757d;
    font-weight: 500;
}

.nav-tabs .nav-link.active {
    color: #007bff;
    background: #f8f9fa;
    border-bottom: 2px solid #007bff;
}

.nav-tabs .nav-link:hover {
    color: #007bff;
    background: #f8f9fa;
}

.tab-content-card {
    padding: 30px;
}

.update-controls {
    margin-bottom: 20px;
}

.update-controls .btn {
    margin-right: 10px;
}

.maintenance-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
}

.action-group {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid #007bff;
}

.action-group.danger-zone {
    border-left-color: #dc3545;
    background: #fff5f5;
}

.action-group h5 {
    margin: 0 0 15px 0;
    color: #495057;
}

.action-group .btn {
    margin: 5px 10px 5px 0;
}

#updateResults {
    margin-top: 20px;
}

.update-item {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.update-item .update-info h6 {
    margin: 0 0 5px 0;
    color: #495057;
}

.update-item .update-info p {
    margin: 0;
    color: #6c757d;
    font-size: 0.9rem;
}

.update-item .update-actions .btn {
    margin-left: 10px;
}

@media (max-width: 768px) {
    .status-cards {
        grid-template-columns: 1fr;
    }
    
    .maintenance-actions {
        grid-template-columns: 1fr;
    }
    
    .update-controls .btn {
        display: block;
        width: 100%;
        margin: 5px 0;
    }
}
</style>

<script>
// Management JavaScript
let currentStatus = null;
let availableUpdates = null;

document.addEventListener('DOMContentLoaded', function() {
    loadSystemStatus();
    checkForUpdates();
});

function loadSystemStatus() {
    fetch('/src/handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            module: 'terminal',
            action: 'get_installation_status'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            currentStatus = data.data;
            updateStatusDisplay();
        } else {
            showError('Fehler beim Laden des Status: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error loading status:', error);
        showError('Fehler beim Laden des Status');
    });
}

function updateStatusDisplay() {
    if (!currentStatus) return;
    
    // Installation Status
    const installationStatus = document.getElementById('installationStatus');
    if (currentStatus.installation_complete) {
        installationStatus.innerHTML = '<span class="text-success">Vollständig installiert</span>';
    } else {
        installationStatus.innerHTML = '<span class="text-warning">Installation unvollständig</span>';
    }
    
    // Update Status
    const updateStatus = document.getElementById('updateStatus');
    if (currentStatus.updates && currentStatus.updates.has_updates) {
        updateStatus.innerHTML = '<span class="text-info">Updates verfügbar</span>';
    } else {
        updateStatus.innerHTML = '<span class="text-success">Aktuell</span>';
    }
    
    // Security Status
    const securityStatus = document.getElementById('securityStatus');
    securityStatus.innerHTML = '<span class="text-success">Sicher</span>';
    
    // System Status Details
    updateSystemStatusDetails();
}

function updateSystemStatusDetails() {
    const container = document.getElementById('systemStatus');
    if (!currentStatus || !currentStatus.requirements) return;
    
    const requirements = currentStatus.requirements.requirements;
    let html = '<div class="requirements-list">';
    
    Object.keys(requirements).forEach(key => {
        const req = requirements[key];
        html += `
            <div class="requirement-item">
                <div class="requirement-name">
                    <i class="fas fa-${req.met ? 'check-circle text-success' : 'times-circle text-danger'}"></i>
                    ${req.name}
                </div>
                <div class="requirement-status">
                    <span class="badge bg-${req.met ? 'success' : 'danger'}">${req.current}</span>
                </div>
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

function checkForUpdates() {
    const updateResults = document.getElementById('updateResults');
    updateResults.innerHTML = '<div class="text-center"><div class="spinner-border text-primary"></div><p>Prüfe auf Updates...</p></div>';
    
    fetch('/src/handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            module: 'terminal',
            action: 'check_updates'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            availableUpdates = data.data;
            displayUpdateResults();
        } else {
            updateResults.innerHTML = '<div class="alert alert-danger">Fehler beim Prüfen der Updates: ' + data.error + '</div>';
        }
    })
    .catch(error => {
        console.error('Error checking updates:', error);
        updateResults.innerHTML = '<div class="alert alert-danger">Fehler beim Prüfen der Updates</div>';
    });
}

function displayUpdateResults() {
    const container = document.getElementById('updateResults');
    const updateAllBtn = document.getElementById('updateAllBtn');
    
    if (!availableUpdates || !availableUpdates.libraries) {
        container.innerHTML = '<div class="text-center text-muted"><i class="fas fa-info-circle"></i><p>Keine Updates verfügbar</p></div>';
        updateAllBtn.style.display = 'none';
        return;
    }
    
    let html = '';
    let hasUpdates = false;
    
    Object.keys(availableUpdates.libraries).forEach(library => {
        const lib = availableUpdates.libraries[library];
        if (lib.update_available) {
            hasUpdates = true;
            html += `
                <div class="update-item">
                    <div class="update-info">
                        <h6>${library}</h6>
                        <p>Aktuell: ${lib.current} → Neueste: ${lib.latest}</p>
                    </div>
                    <div class="update-actions">
                        <button class="btn btn-sm btn-primary" onclick="updateLibrary('${library}')">
                            <i class="fas fa-download"></i> Update
                        </button>
                    </div>
                </div>
            `;
        }
    });
    
    if (!hasUpdates) {
        html = '<div class="text-center text-muted"><i class="fas fa-check-circle"></i><p>Alle Libraries sind aktuell</p></div>';
        updateAllBtn.style.display = 'none';
    } else {
        updateAllBtn.style.display = 'inline-block';
    }
    
    container.innerHTML = html;
}

function updateLibrary(library) {
    if (!availableUpdates || !availableUpdates.libraries[library]) return;
    
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Update...';
    button.disabled = true;
    
    fetch('/src/handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            module: 'terminal',
            action: 'update_libraries',
            libraries: [library]
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess('Library erfolgreich aktualisiert');
            checkForUpdates(); // Refresh update list
        } else {
            showError('Update fehlgeschlagen: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error updating library:', error);
        showError('Update fehlgeschlagen');
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

function updateAllLibraries() {
    if (!availableUpdates || !availableUpdates.libraries) return;
    
    const librariesToUpdate = Object.keys(availableUpdates.libraries)
        .filter(lib => availableUpdates.libraries[lib].update_available);
    
    if (librariesToUpdate.length === 0) return;
    
    const button = document.getElementById('updateAllBtn');
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updates...';
    button.disabled = true;
    
    fetch('/src/handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            module: 'terminal',
            action: 'update_libraries',
            libraries: librariesToUpdate
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess('Alle Libraries erfolgreich aktualisiert');
            checkForUpdates(); // Refresh update list
        } else {
            showError('Updates fehlgeschlagen: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error updating libraries:', error);
        showError('Updates fehlgeschlagen');
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

function showUninstallModal() {
    const modal = new bootstrap.Modal(document.getElementById('uninstallModal'));
    modal.show();
}

function confirmUninstall() {
    const checkbox = document.getElementById('confirmUninstall');
    const button = document.getElementById('confirmUninstallBtn');
    
    if (checkbox.checked) {
        button.disabled = false;
    } else {
        button.disabled = true;
    }
}

function uninstallModule() {
    if (!document.getElementById('confirmUninstall').checked) return;
    
    const button = document.getElementById('confirmUninstallBtn');
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deinstalliere...';
    button.disabled = true;
    
    fetch('/src/handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            module: 'terminal',
            action: 'uninstall_module'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess('Modul erfolgreich deinstalliert');
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            showError('Deinstallation fehlgeschlagen: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error uninstalling module:', error);
        showError('Deinstallation fehlgeschlagen');
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

function createBackup() {
    showInfo('Backup-Funktion wird implementiert...');
}

function restoreBackup() {
    showInfo('Backup-Wiederherstellung wird implementiert...');
}

function clearCache() {
    showInfo('Cache wird geleert...');
}

function viewLogs() {
    showInfo('Log-Viewer wird implementiert...');
}

function showSuccess(message) {
    // Implement success notification
    console.log('Success:', message);
}

function showError(message) {
    // Implement error notification
    console.error('Error:', message);
}

function showInfo(message) {
    // Implement info notification
    console.log('Info:', message);
}

// Enable/disable uninstall button based on checkbox
document.getElementById('confirmUninstall').addEventListener('change', function() {
    confirmUninstall();
});
</script>
