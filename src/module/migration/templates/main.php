<?php
/**
 * Migration-Modul Template
 * 
 * Hauptinterface für die Migration von Benutzern aus verschiedenen Systemen
 */
// Extrahiere Daten aus dem übergebenen Array
$t = $translations ?? [];
$module_key = $module_key ?? 'migration';
?>

<div class="migration-container">
    <div class="migration-header">
        <h2><?= $t['migration_title'] ?? 'System-Migration' ?></h2>
        <p class="migration-description"><?= $t['migration_description'] ?? 'Migrieren Sie Benutzer und Daten aus verschiedenen Systemen in das Server Management System.' ?></p>
    </div>

    <!-- System-Status -->
    <div class="system-status-section">
        <h3><?= $t['system_status'] ?? 'System-Status' ?></h3>
        <div class="systems-grid" id="systemsGrid">
            <!-- Wird dynamisch geladen -->
        </div>
    </div>

    <!-- Migration-Interface -->
    <div class="migration-interface-section">
        <h3><?= $t['migration_interface'] ?? 'Migration-Interface' ?></h3>
        
        <div class="migration-controls">
            <div class="system-selection">
                <h4><?= $t['select_systems'] ?? 'Systeme auswählen' ?></h4>
                <div class="system-checkboxes">
                    <label class="system-checkbox">
                        <input type="checkbox" id="system_ispconfig" value="ispconfig">
                        <span class="checkbox-label">
                            <i class="icon">🌐</i>
                            ISPConfig 3
                            <span class="user-count" id="ispconfig_count">0</span>
                        </span>
                    </label>
                    
                    <label class="system-checkbox">
                        <input type="checkbox" id="system_proxmox" value="proxmox">
                        <span class="checkbox-label">
                            <i class="icon">🖥️</i>
                            Proxmox VE
                            <span class="user-count" id="proxmox_count">0</span>
                        </span>
                    </label>
                    
                    <label class="system-checkbox">
                        <input type="checkbox" id="system_ogp" value="ogp">
                        <span class="checkbox-label">
                            <i class="icon">🎮</i>
                            OpenGamePanel
                            <span class="user-count" id="ogp_count">0</span>
                        </span>
                    </label>
                </div>
            </div>
            
            <div class="migration-actions">
                <button id="testConnections" class="btn btn-secondary">
                    <i class="icon">🔍</i>
                    <?= $t['test_connections'] ?? 'Verbindungen testen' ?>
                </button>
                
                <button id="startMigration" class="btn btn-primary" disabled>
                    <i class="icon">▶️</i>
                    <?= $t['start_migration'] ?? 'Migration starten' ?>
                </button>
                
                <button id="stopMigration" class="btn btn-danger" disabled>
                    <i class="icon">⏹️</i>
                    <?= $t['stop_migration'] ?? 'Migration stoppen' ?>
                </button>
                
                <button id="rollbackMigration" class="btn btn-warning">
                    <i class="icon">↶</i>
                    <?= $t['rollback_migration'] ?? 'Migration rückgängig machen' ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Migration-Progress -->
    <div class="migration-progress-section" id="progressSection" style="display: none;">
        <h3><?= $t['migration_progress'] ?? 'Migrations-Fortschritt' ?></h3>
        
        <div class="progress-container">
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill" style="width: 0%"></div>
            </div>
            <div class="progress-text">
                <span id="progressText">0%</span>
                <span id="progressStatus"><?= $t['ready'] ?? 'Bereit' ?></span>
            </div>
        </div>
        
        <div class="current-operation" id="currentOperation">
            <!-- Wird dynamisch aktualisiert -->
        </div>
    </div>

    <!-- Migration-Statistiken -->
    <div class="migration-stats-section">
        <h3><?= $t['migration_statistics'] ?? 'Migrations-Statistiken' ?></h3>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-content">
                    <div class="stat-number" id="totalCustomers">0</div>
                    <div class="stat-label"><?= $t['total_customers'] ?? 'Gesamtkunden' ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🌐</div>
                <div class="stat-content">
                    <div class="stat-number" id="ispconfigUsers">0</div>
                    <div class="stat-label"><?= $t['ispconfig_users'] ?? 'ISPConfig Benutzer' ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🖥️</div>
                <div class="stat-content">
                    <div class="stat-number" id="proxmoxUsers">0</div>
                    <div class="stat-label"><?= $t['proxmox_users'] ?? 'Proxmox Benutzer' ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🎮</div>
                <div class="stat-content">
                    <div class="stat-number" id="ogpUsers">0</div>
                    <div class="stat-label"><?= $t['ogp_users'] ?? 'OGP Benutzer' ?></div>
                </div>
            </div>
            
            <div class="stat-card error">
                <div class="stat-icon">❌</div>
                <div class="stat-content">
                    <div class="stat-number" id="errorCount">0</div>
                    <div class="stat-label"><?= $t['errors'] ?? 'Fehler' ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Migration-Log -->
    <div class="migration-log-section">
        <h3><?= $t['migration_log'] ?? 'Migrations-Log' ?></h3>
        
        <div class="log-container">
            <div class="log-header">
                <button id="refreshLog" class="btn btn-sm btn-secondary">
                    <i class="icon">🔄</i>
                    <?= $t['refresh_log'] ?? 'Aktualisieren' ?>
                </button>
                <button id="clearLog" class="btn btn-sm btn-warning">
                    <i class="icon">🗑️</i>
                    <?= $t['clear_log'] ?? 'Löschen' ?>
                </button>
            </div>
            
            <div class="log-content" id="logContent">
                <!-- Log-Einträge werden hier angezeigt -->
            </div>
        </div>
    </div>

    <!-- Bestätigungs-Dialoge -->
    <div id="confirmDialog" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="confirmTitle"><?= $t['confirmation'] ?? 'Bestätigung' ?></h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <p id="confirmMessage"><?= $t['confirm_message'] ?? 'Sind Sie sicher?' ?></p>
            </div>
            <div class="modal-footer">
                <button id="confirmCancel" class="btn btn-secondary"><?= $t['cancel'] ?? 'Abbrechen' ?></button>
                <button id="confirmOk" class="btn btn-danger"><?= $t['confirm'] ?? 'Bestätigen' ?></button>
            </div>
        </div>
    </div>

    <!-- Rollback-Dialog -->
    <div id="rollbackDialog" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><?= $t['rollback_title'] ?? 'Migration rückgängig machen' ?></h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <div class="warning-box">
                    <i class="icon">⚠️</i>
                    <p><?= $t['rollback_warning'] ?? 'Warnung: Diese Aktion kann nicht rückgängig gemacht werden!' ?></p>
                </div>
                
                <div class="rollback-options">
                    <label>
                        <input type="checkbox" id="rollbackCustomers" checked>
                        <?= $t['rollback_customers'] ?? 'Kunden löschen' ?>
                    </label>
                    <label>
                        <input type="checkbox" id="rollbackWebsites" checked>
                        <?= $t['rollback_websites'] ?? 'Websites löschen' ?>
                    </label>
                    <label>
                        <input type="checkbox" id="rollbackVMs" checked>
                        <?= $t['rollback_vms'] ?? 'VMs löschen' ?>
                    </label>
                    <label>
                        <input type="checkbox" id="rollbackEmails" checked>
                        <?= $t['rollback_emails'] ?? 'E-Mails löschen' ?>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button id="rollbackCancel" class="btn btn-secondary"><?= $t['cancel'] ?? 'Abbrechen' ?></button>
                <button id="rollbackConfirm" class="btn btn-danger"><?= $t['execute_rollback'] ?? 'Rollback ausführen' ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Loading-Overlay -->
<div id="loadingOverlay" class="loading-overlay" style="display: none;">
    <div class="loading-content">
        <div class="spinner"></div>
        <p id="loadingText"><?= $t['loading'] ?? 'Laden...' ?></p>
    </div>
</div>

<script>
// Migration-Modul JavaScript wird in der separaten JS-Datei geladen
document.addEventListener('DOMContentLoaded', function() {
    if (typeof MigrationModule !== 'undefined') {
        new MigrationModule('<?= $module_key ?? 'migration' ?>');
    }
});
</script>
