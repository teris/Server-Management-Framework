/**
 * Migration-Modul JavaScript
 * 
 * Verwaltet das Frontend für das Migration-Modul
 */

class MigrationModule {
    constructor(moduleKey) {
        this.moduleKey = moduleKey;
        this.isRunning = false;
        this.progressInterval = null;
        this.selectedSystems = [];
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.loadSystemStatus();
        this.loadMigrationStats();
        this.loadMigrationLog();
        
        // Progress-Updates alle 2 Sekunden
        this.progressInterval = setInterval(() => {
            this.updateProgress();
        }, 2000);
    }
    
    bindEvents() {
        // System-Checkboxen
        document.querySelectorAll('.system-checkbox input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                this.updateSelectedSystems();
                this.updateStartButton();
            });
        });
        
        // Buttons
        document.getElementById('testConnections')?.addEventListener('click', () => {
            this.testConnections();
        });
        
        document.getElementById('startMigration')?.addEventListener('click', () => {
            this.startMigration();
        });
        
        document.getElementById('stopMigration')?.addEventListener('click', () => {
            this.stopMigration();
        });
        
        document.getElementById('rollbackMigration')?.addEventListener('click', () => {
            this.showRollbackDialog();
        });
        
        document.getElementById('refreshLog')?.addEventListener('click', () => {
            this.loadMigrationLog();
        });
        
        document.getElementById('clearLog')?.addEventListener('click', () => {
            this.clearLog();
        });
        
        // Modal-Events
        this.bindModalEvents();
    }
    
    bindModalEvents() {
        // Bestätigungs-Dialog
        const confirmDialog = document.getElementById('confirmDialog');
        const confirmCancel = document.getElementById('confirmCancel');
        const confirmOk = document.getElementById('confirmOk');
        const confirmClose = confirmDialog?.querySelector('.close');
        
        confirmCancel?.addEventListener('click', () => {
            confirmDialog.style.display = 'none';
        });
        
        confirmClose?.addEventListener('click', () => {
            confirmDialog.style.display = 'none';
        });
        
        confirmOk?.addEventListener('click', () => {
            if (this.confirmCallback) {
                this.confirmCallback();
                this.confirmCallback = null;
            }
            confirmDialog.style.display = 'none';
        });
        
        // Rollback-Dialog
        const rollbackDialog = document.getElementById('rollbackDialog');
        const rollbackCancel = document.getElementById('rollbackCancel');
        const rollbackConfirm = document.getElementById('rollbackConfirm');
        const rollbackClose = rollbackDialog?.querySelector('.close');
        
        rollbackCancel?.addEventListener('click', () => {
            rollbackDialog.style.display = 'none';
        });
        
        rollbackClose?.addEventListener('click', () => {
            rollbackDialog.style.display = 'none';
        });
        
        rollbackConfirm?.addEventListener('click', () => {
            this.executeRollback();
            rollbackDialog.style.display = 'none';
        });
        
        // Modal schließen bei Klick außerhalb
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                e.target.style.display = 'none';
            }
        });
    }
    
    async loadSystemStatus() {
        try {
            const response = await this.makeRequest('getSystemStatus');
            
            if (response.success) {
                this.updateSystemStatus(response.data);
            } else {
                this.showError('Fehler beim Laden der System-Status: ' + response.error);
            }
        } catch (error) {
            this.showError('Fehler beim Laden der System-Status: ' + error.message);
        }
    }
    
    updateSystemStatus(status) {
        const systemsGrid = document.getElementById('systemsGrid');
        if (!systemsGrid) return;
        
        systemsGrid.innerHTML = '';
        
        Object.entries(status).forEach(([system, config]) => {
            const systemCard = this.createSystemCard(system, config);
            systemsGrid.appendChild(systemCard);
        });
        
        // Benutzeranzahl in Checkboxen aktualisieren
        document.getElementById('ispconfig_count').textContent = status.ispconfig?.user_count || 0;
        document.getElementById('proxmox_count').textContent = status.proxmox?.user_count || 0;
        document.getElementById('ogp_count').textContent = status.ogp?.user_count || 0;
        
        // Checkboxen aktivieren/deaktivieren basierend auf Verfügbarkeit
        document.getElementById('system_ispconfig').disabled = !status.ispconfig?.enabled || !status.ispconfig?.connected;
        document.getElementById('system_proxmox').disabled = !status.proxmox?.enabled || !status.proxmox?.connected;
        document.getElementById('system_ogp').disabled = !status.ogp?.enabled || !status.ogp?.connected;
    }
    
    createSystemCard(system, config) {
        const card = document.createElement('div');
        card.className = `system-card ${config.connected ? 'connected' : 'disconnected'}`;
        
        const systemNames = {
            'ispconfig': 'ISPConfig 3',
            'proxmox': 'Proxmox VE',
            'ogp': 'OpenGamePanel'
        };
        
        const systemIcons = {
            'ispconfig': '🌐',
            'proxmox': '🖥️',
            'ogp': '🎮'
        };
        
        card.innerHTML = `
            <div class="system-header">
                <span class="system-icon">${systemIcons[system]}</span>
                <span class="system-name">${systemNames[system]}</span>
                <span class="status-indicator ${config.connected ? 'connected' : 'disconnected'}">
                    ${config.connected ? '✓' : '✗'}
                </span>
            </div>
            <div class="system-info">
                <div class="info-item">
                    <span class="label">Status:</span>
                    <span class="value">${config.enabled ? 'Aktiviert' : 'Deaktiviert'}</span>
                </div>
                ${config.host ? `
                <div class="info-item">
                    <span class="label">Host:</span>
                    <span class="value">${config.host}</span>
                </div>
                ` : ''}
                <div class="info-item">
                    <span class="label">Benutzer:</span>
                    <span class="value">${config.user_count || 0}</span>
                </div>
                ${config.error ? `
                <div class="info-item error">
                    <span class="label">Fehler:</span>
                    <span class="value">${config.error}</span>
                </div>
                ` : ''}
            </div>
        `;
        
        return card;
    }
    
    updateSelectedSystems() {
        this.selectedSystems = [];
        
        document.querySelectorAll('.system-checkbox input[type="checkbox"]:checked').forEach(checkbox => {
            this.selectedSystems.push(checkbox.value);
        });
    }
    
    updateStartButton() {
        const startBtn = document.getElementById('startMigration');
        if (startBtn) {
            startBtn.disabled = this.selectedSystems.length === 0 || this.isRunning;
        }
    }
    
    async testConnections() {
        this.showLoading('Verbindungen werden getestet...');
        
        try {
            // Alle Systeme testen
            const systems = ['ispconfig', 'proxmox', 'ogp'];
            const results = [];
            
            for (const system of systems) {
                try {
                    const response = await this.makeRequest('testConnection', { system });
                    results.push({ system, ...response });
                } catch (error) {
                    results.push({ system, success: false, error: error.message });
                }
            }
            
            this.hideLoading();
            this.showTestResults(results);
            
            // System-Status neu laden
            setTimeout(() => {
                this.loadSystemStatus();
            }, 1000);
            
        } catch (error) {
            this.hideLoading();
            this.showError('Fehler beim Testen der Verbindungen: ' + error.message);
        }
    }
    
    showTestResults(results) {
        let message = 'Verbindungstest-Ergebnisse:\n\n';
        
        results.forEach(result => {
            const systemName = {
                'ispconfig': 'ISPConfig 3',
                'proxmox': 'Proxmox VE',
                'ogp': 'OpenGamePanel'
            }[result.system];
            
            if (result.success) {
                message += `✓ ${systemName}: Verbindung erfolgreich (${result.data?.user_count || 0} Benutzer)\n`;
            } else {
                message += `✗ ${systemName}: ${result.error}\n`;
            }
        });
        
        alert(message);
    }
    
    async startMigration() {
        if (this.selectedSystems.length === 0) {
            this.showError('Bitte wählen Sie mindestens ein System aus.');
            return;
        }
        
        this.showConfirm(
            'Migration starten',
            `Möchten Sie die Migration für folgende Systeme starten?\n\n${this.selectedSystems.map(s => {
                const names = { 'ispconfig': 'ISPConfig 3', 'proxmox': 'Proxmox VE', 'ogp': 'OpenGamePanel' };
                return `• ${names[s]}`;
            }).join('\n')}\n\nDiese Aktion kann nicht rückgängig gemacht werden.`,
            () => {
                this.executeMigration();
            }
        );
    }
    
    async executeMigration() {
        this.isRunning = true;
        this.updateButtons();
        this.showProgress();
        
        try {
            const response = await this.makeRequest('startMigration', {
                systems: this.selectedSystems
            });
            
            if (response.success) {
                this.showSuccess('Migration gestartet!');
                this.updateProgress();
            } else {
                this.showError('Fehler beim Starten der Migration: ' + response.error);
                this.stopMigration();
            }
        } catch (error) {
            this.showError('Fehler beim Starten der Migration: ' + error.message);
            this.stopMigration();
        }
    }
    
    async stopMigration() {
        try {
            const response = await this.makeRequest('stopMigration');
            
            if (response.success) {
                this.showSuccess('Migration gestoppt!');
            } else {
                this.showError('Fehler beim Stoppen der Migration: ' + response.error);
            }
        } catch (error) {
            this.showError('Fehler beim Stoppen der Migration: ' + error.message);
        }
        
        this.isRunning = false;
        this.updateButtons();
        this.hideProgress();
    }
    
    async updateProgress() {
        try {
            const response = await this.makeRequest('getMigrationProgress');
            
            if (response.success) {
                const progress = response.data;
                this.updateProgressUI(progress);
                
                // Migration beendet?
                if (progress.status === 'completed' || progress.status === 'stopped') {
                    this.isRunning = false;
                    this.updateButtons();
                    
                    if (progress.status === 'completed') {
                        this.showSuccess('Migration abgeschlossen!');
                        this.loadMigrationStats();
                    }
                }
            }
        } catch (error) {
            console.error('Fehler beim Aktualisieren des Fortschritts:', error);
        }
    }
    
    updateProgressUI(progress) {
        const progressFill = document.getElementById('progressFill');
        const progressText = document.getElementById('progressText');
        const progressStatus = document.getElementById('progressStatus');
        const currentOperation = document.getElementById('currentOperation');
        
        if (progressFill) {
            progressFill.style.width = progress.progress + '%';
        }
        
        if (progressText) {
            progressText.textContent = progress.progress + '%';
        }
        
        if (progressStatus) {
            progressStatus.textContent = this.getStatusText(progress.status);
        }
        
        if (currentOperation) {
            currentOperation.innerHTML = this.getCurrentOperationHTML(progress);
        }
        
        // Statistiken aktualisieren
        if (progress.stats) {
            this.updateStats(progress.stats);
        }
    }
    
    getStatusText(status) {
        const statusTexts = {
            'idle': 'Bereit',
            'running': 'Läuft',
            'completed': 'Abgeschlossen',
            'stopped': 'Gestoppt',
            'error': 'Fehler'
        };
        
        return statusTexts[status] || 'Unbekannt';
    }
    
    getCurrentOperationHTML(progress) {
        if (!progress.current_system) {
            return '<div class="operation-text">Bereit</div>';
        }
        
        const systemNames = {
            'ispconfig': 'ISPConfig 3',
            'proxmox': 'Proxmox VE',
            'ogp': 'OpenGamePanel'
        };
        
        return `
            <div class="operation-text">
                Migriere: <strong>${systemNames[progress.current_system] || progress.current_system}</strong>
            </div>
        `;
    }
    
    showProgress() {
        const progressSection = document.getElementById('progressSection');
        if (progressSection) {
            progressSection.style.display = 'block';
        }
    }
    
    hideProgress() {
        const progressSection = document.getElementById('progressSection');
        if (progressSection) {
            progressSection.style.display = 'none';
        }
    }
    
    updateButtons() {
        const startBtn = document.getElementById('startMigration');
        const stopBtn = document.getElementById('stopMigration');
        
        if (startBtn) {
            startBtn.disabled = this.isRunning || this.selectedSystems.length === 0;
        }
        
        if (stopBtn) {
            stopBtn.disabled = !this.isRunning;
        }
    }
    
    async loadMigrationStats() {
        try {
            const response = await this.makeRequest('getMigrationStats');
            
            if (response.success) {
                this.updateStats(response.data);
            }
        } catch (error) {
            console.error('Fehler beim Laden der Migrations-Statistiken:', error);
        }
    }
    
    updateStats(stats) {
        document.getElementById('totalCustomers').textContent = stats.customers || 0;
        document.getElementById('ispconfigUsers').textContent = stats.ispconfig_users || 0;
        document.getElementById('proxmoxUsers').textContent = stats.proxmox_users || 0;
        document.getElementById('ogpUsers').textContent = stats.ogp_users || 0;
        document.getElementById('errorCount').textContent = stats.errors || 0;
    }
    
    async loadMigrationLog() {
        try {
            const response = await this.makeRequest('getMigrationLog');
            
            if (response.success) {
                this.displayLog(response.data);
            } else {
                this.showError('Fehler beim Laden des Logs: ' + response.error);
            }
        } catch (error) {
            this.showError('Fehler beim Laden des Logs: ' + error.message);
        }
    }
    
    displayLog(logEntries) {
        const logContent = document.getElementById('logContent');
        if (!logContent) return;
        
        if (!logEntries || logEntries.length === 0) {
            logContent.innerHTML = '<div class="no-entries">Keine Log-Einträge vorhanden.</div>';
            return;
        }
        
        logContent.innerHTML = logEntries.map(entry => `
            <div class="log-entry ${entry.level?.toLowerCase() || 'info'}">
                <div class="log-time">${this.formatTime(entry.created_at)}</div>
                <div class="log-level">${entry.level || 'INFO'}</div>
                <div class="log-message">${entry.description || entry.message || ''}</div>
            </div>
        `).join('');
    }
    
    formatTime(timestamp) {
        return new Date(timestamp).toLocaleString('de-DE');
    }
    
    async clearLog() {
        this.showConfirm(
            'Log löschen',
            'Möchten Sie das Migration-Log wirklich löschen?',
            () => {
                document.getElementById('logContent').innerHTML = '<div class="no-entries">Log gelöscht.</div>';
            }
        );
    }
    
    showRollbackDialog() {
        const rollbackDialog = document.getElementById('rollbackDialog');
        if (rollbackDialog) {
            rollbackDialog.style.display = 'block';
        }
    }
    
    async executeRollback() {
        const options = {
            customers: document.getElementById('rollbackCustomers').checked,
            websites: document.getElementById('rollbackWebsites').checked,
            vms: document.getElementById('rollbackVMs').checked,
            emails: document.getElementById('rollbackEmails').checked
        };
        
        this.showLoading('Rollback wird ausgeführt...');
        
        try {
            const response = await this.makeRequest('rollbackMigration', options);
            
            if (response.success) {
                this.showSuccess('Rollback erfolgreich ausgeführt!');
                this.loadMigrationStats();
                this.loadMigrationLog();
            } else {
                this.showError('Fehler beim Rollback: ' + response.error);
            }
        } catch (error) {
            this.showError('Fehler beim Rollback: ' + error.message);
        } finally {
            this.hideLoading();
        }
    }
    
    // Utility-Methoden
    async makeRequest(action, data = {}) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('module', this.moduleKey);
        
        Object.entries(data).forEach(([key, value]) => {
            formData.append(key, JSON.stringify(value));
        });
        
        const response = await fetch('handler.php', {
            method: 'POST',
            body: formData
        });
        
        return await response.json();
    }
    
    showLoading(text = 'Lädt...') {
        const overlay = document.getElementById('loadingOverlay');
        const loadingText = document.getElementById('loadingText');
        
        if (overlay) {
            overlay.style.display = 'flex';
        }
        
        if (loadingText) {
            loadingText.textContent = text;
        }
    }
    
    hideLoading() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    }
    
    showError(message) {
        alert('Fehler: ' + message);
    }
    
    showSuccess(message) {
        alert('Erfolg: ' + message);
    }
    
    showConfirm(title, message, callback) {
        const dialog = document.getElementById('confirmDialog');
        const titleEl = document.getElementById('confirmTitle');
        const messageEl = document.getElementById('confirmMessage');
        
        if (titleEl) titleEl.textContent = title;
        if (messageEl) messageEl.textContent = message;
        
        this.confirmCallback = callback;
        
        if (dialog) {
            dialog.style.display = 'block';
        }
    }
    
    // Cleanup beim Verlassen der Seite
    destroy() {
        if (this.progressInterval) {
            clearInterval(this.progressInterval);
        }
    }
}

// Cleanup beim Verlassen der Seite
window.addEventListener('beforeunload', () => {
    if (window.migrationModule) {
        window.migrationModule.destroy();
    }
});
