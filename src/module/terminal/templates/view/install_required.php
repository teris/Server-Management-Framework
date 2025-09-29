<div id="terminal-install-required">
    <!-- Header -->
    <div class="install-header">
        <div class="install-icon">
            <i class="fas fa-download fa-3x text-primary"></i>
        </div>
        <h2>Terminal-Modul Installation erforderlich</h2>
        <p class="text-muted">Das Terminal-Modul benötigt zusätzliche Komponenten, die automatisch installiert werden können.</p>
    </div>

    <!-- Requirements Status -->
    <div class="requirements-status">
        <div class="status-summary">
            <div class="status-card">
                <div class="status-number"><?= $requirements['met'] ?></div>
                <div class="status-label">von <?= $requirements['total'] ?> erfüllt</div>
            </div>
            <div class="status-progress">
                <div class="progress">
                    <div class="progress-bar" role="progressbar" 
                         style="width: <?= ($requirements['met'] / $requirements['total']) * 100 ?>%"
                         aria-valuenow="<?= $requirements['met'] ?>" 
                         aria-valuemin="0" 
                         aria-valuemax="<?= $requirements['total'] ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Requirements List -->
    <div class="requirements-list">
        <h4>Systemvoraussetzungen</h4>
        <div class="list-group">
            <?php foreach ($requirements['requirements'] as $key => $req): ?>
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div class="requirement-info">
                    <div class="requirement-name">
                        <i class="fas fa-<?= $req['met'] ? 'check-circle text-success' : 'times-circle text-danger' ?>"></i>
                        <?= htmlspecialchars($req['name']) ?>
                    </div>
                    <div class="requirement-description text-muted">
                        <?= htmlspecialchars($req['description']) ?>
                    </div>
                </div>
                <div class="requirement-status">
                    <span class="badge bg-<?= $req['met'] ? 'success' : 'danger' ?>">
                        <?= htmlspecialchars($req['current']) ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Installation Actions -->
    <div class="installation-actions">
        <div class="action-buttons">
            <button class="btn btn-primary btn-lg" onclick="startInstallation()" id="installButton">
                <i class="fas fa-download"></i> Installation starten
            </button>
            <button class="btn btn-outline-secondary" onclick="refreshRequirements()" id="refreshButton">
                <i class="fas fa-sync"></i> Erneut prüfen
            </button>
        </div>
        
        <div class="installation-info">
            <div class="info-card">
                <h5><i class="fas fa-info-circle"></i> Was wird installiert?</h5>
                <ul>
                    <li><strong>noVNC Library</strong> - Für VNC-Terminal-Unterstützung</li>
                    <li><strong>xterm.js Library</strong> - Für SSH-Terminal-Unterstützung</li>
                    <li><strong>WebSocket-Proxies</strong> - Für sichere Verbindungen</li>
                    <li><strong>Datenbanktabellen</strong> - Für Session-Management</li>
                    <li><strong>Konfigurationsdateien</strong> - Mit optimalen Einstellungen</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Installation Progress (hidden initially) -->
    <div class="installation-progress" id="installationProgress" style="display: none;">
        <div class="progress-container">
            <h4>Installation läuft...</h4>
            <div class="progress mb-3">
                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                     role="progressbar" style="width: 0%" id="progressBar">
                </div>
            </div>
            <div class="progress-text" id="progressText">Vorbereitung...</div>
        </div>
        
        <div class="installation-log" id="installationLog">
            <!-- Installation log will be shown here -->
        </div>
    </div>

    <!-- Installation Complete (hidden initially) -->
    <div class="installation-complete" id="installationComplete" style="display: none;">
        <div class="success-icon">
            <i class="fas fa-check-circle fa-4x text-success"></i>
        </div>
        <h3>Installation erfolgreich abgeschlossen!</h3>
        <p>Das Terminal-Modul ist jetzt einsatzbereit. Sie können jetzt VNC- und SSH-Verbindungen zu Ihren Servern herstellen.</p>
        <button class="btn btn-success btn-lg" onclick="reloadModule()">
            <i class="fas fa-arrow-right"></i> Terminal-Modul laden
        </button>
    </div>
</div>

<style>
.install-header {
    text-align: center;
    padding: 40px 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
    margin-bottom: 30px;
}

.install-header h2 {
    margin: 20px 0 10px 0;
    font-weight: 600;
}

.requirements-status {
    background: white;
    border-radius: 10px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.status-summary {
    display: flex;
    align-items: center;
    gap: 30px;
    margin-bottom: 20px;
}

.status-card {
    text-align: center;
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    min-width: 120px;
}

.status-number {
    font-size: 2.5rem;
    font-weight: bold;
    color: #007bff;
}

.status-label {
    color: #6c757d;
    font-size: 0.9rem;
}

.status-progress {
    flex: 1;
}

.progress {
    height: 20px;
    border-radius: 10px;
    background-color: #e9ecef;
}

.progress-bar {
    border-radius: 10px;
    transition: width 0.3s ease;
}

.requirements-list {
    background: white;
    border-radius: 10px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.requirements-list h4 {
    margin-bottom: 20px;
    color: #495057;
}

.list-group-item {
    border: 1px solid #dee2e6;
    border-radius: 8px !important;
    margin-bottom: 10px;
    padding: 15px 20px;
}

.requirement-name {
    font-weight: 600;
    margin-bottom: 5px;
}

.requirement-name i {
    margin-right: 10px;
}

.requirement-description {
    font-size: 0.9rem;
}

.installation-actions {
    background: white;
    border-radius: 10px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.action-buttons {
    text-align: center;
    margin-bottom: 30px;
}

.action-buttons .btn {
    margin: 0 10px;
    min-width: 150px;
}

.installation-info {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
}

.info-card h5 {
    color: #495057;
    margin-bottom: 15px;
}

.info-card ul {
    margin: 0;
    padding-left: 20px;
}

.info-card li {
    margin-bottom: 8px;
    color: #6c757d;
}

.installation-progress {
    background: white;
    border-radius: 10px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.progress-container {
    text-align: center;
    margin-bottom: 20px;
}

.progress-text {
    margin-top: 10px;
    color: #6c757d;
    font-weight: 500;
}

.installation-log {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    max-height: 300px;
    overflow-y: auto;
    font-family: 'Courier New', monospace;
    font-size: 0.9rem;
}

.installation-complete {
    background: white;
    border-radius: 10px;
    padding: 40px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.success-icon {
    margin-bottom: 20px;
}

.installation-complete h3 {
    color: #28a745;
    margin-bottom: 15px;
}

.installation-complete p {
    color: #6c757d;
    margin-bottom: 30px;
}

@media (max-width: 768px) {
    .status-summary {
        flex-direction: column;
        gap: 20px;
    }
    
    .action-buttons .btn {
        display: block;
        width: 100%;
        margin: 10px 0;
    }
}
</style>

<script>
// Installation JavaScript
let installationInProgress = false;

function startInstallation() {
    if (installationInProgress) return;
    
    installationInProgress = true;
    document.getElementById('installButton').disabled = true;
    document.getElementById('refreshButton').disabled = true;
    
    // Show progress
    document.getElementById('installationProgress').style.display = 'block';
    document.getElementById('installationActions').style.display = 'none';
    
    // Start installation
    performInstallation();
}

function performInstallation() {
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const log = document.getElementById('installationLog');
    
    let progress = 0;
    const steps = [
        'Vorbereitung...',
        'Verzeichnisse erstellen...',
        'noVNC Library herunterladen...',
        'xterm.js Library herunterladen...',
        'WebSocket-Proxies erstellen...',
        'Datenbanktabellen erstellen...',
        'Konfiguration erstellen...',
        'Berechtigungen setzen...',
        'Installation abschließen...'
    ];
    
    let currentStep = 0;
    
    function updateProgress(step, message) {
        progress = (step / steps.length) * 100;
        progressBar.style.width = progress + '%';
        progressText.textContent = message;
        log.innerHTML += '<div class="log-entry">' + message + '</div>';
        log.scrollTop = log.scrollHeight;
    }
    
    // Simulate installation steps
    const interval = setInterval(() => {
        if (currentStep < steps.length) {
            updateProgress(currentStep, steps[currentStep]);
            currentStep++;
        } else {
            clearInterval(interval);
        }
    }, 1000);
    
    // Actually perform installation via AJAX
    fetch('/src/handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            module: 'terminal',
            action: 'start_installation'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            log.innerHTML += '<div class="log-entry success">Installation abgeschlossen!</div>';
            log.scrollTop = log.scrollHeight;
            setTimeout(() => {
                completeInstallation();
            }, 2000);
        } else {
            log.innerHTML += '<div class="log-entry error">Fehler: ' + data.error + '</div>';
            log.scrollTop = log.scrollHeight;
            installationInProgress = false;
            document.getElementById('installButton').disabled = false;
            document.getElementById('refreshButton').disabled = false;
        }
    })
    .catch(error => {
        log.innerHTML += '<div class="log-entry error">Fehler: ' + error.message + '</div>';
        log.scrollTop = log.scrollHeight;
        installationInProgress = false;
        document.getElementById('installButton').disabled = false;
        document.getElementById('refreshButton').disabled = false;
    });
}

function completeInstallation() {
    setTimeout(() => {
        document.getElementById('installationProgress').style.display = 'none';
        document.getElementById('installationComplete').style.display = 'block';
    }, 2000);
}

function refreshRequirements() {
    location.reload();
}

function reloadModule() {
    location.reload();
}

// Auto-refresh requirements every 30 seconds
setInterval(() => {
    if (!installationInProgress) {
        refreshRequirements();
    }
}, 30000);
</script>
