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
