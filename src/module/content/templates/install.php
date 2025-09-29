<?php
// Installationsassistent für Content Management
?>
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="bi bi-gear"></i> Content Management - Installation
                    </h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h5><i class="bi bi-info-circle"></i> Willkommen beim Content Management System</h5>
                        <p>Dieses Modul ermöglicht es Ihnen, dynamische Seiten für Ihr Frontend zu erstellen und zu verwalten.</p>
                        <p><strong>Was wird installiert:</strong></p>
                        <ul>
                            <li>Datenbank-Tabelle für CMS-Seiten</li>
                            <li>Beispiel-Seiten (Impressum, AGB, Datenschutz)</li>
                            <li>Editor-Interface für die Seitenverwaltung</li>
                        </ul>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6><i class="bi bi-check-circle text-success"></i> Funktionen nach der Installation:</h6>
                            <ul class="list-unstyled">
                                <li><i class="bi bi-file-text"></i> Dynamische Seiten erstellen</li>
                                <li><i class="bi bi-code-slash"></i> HTML-Editor mit Syntax-Highlighting</li>
                                <li><i class="bi bi-eye"></i> Live-Vorschau der Seiten</li>
                                <li><i class="bi bi-globe"></i> Frontend-Integration über <code>public/page.php</code></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="bi bi-shield-check text-info"></i> System-Anforderungen:</h6>
                            <ul class="list-unstyled">
                                <li><i class="bi bi-database"></i> MySQL/MariaDB Datenbank</li>
                                <li><i class="bi bi-file-earmark-code"></i> ACE Editor (bereits geladen)</li>
                                <li><i class="bi bi-folder"></i> Schreibrechte für Datenbank</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button class="btn btn-primary btn-lg" id="install-btn">
                            <i class="bi bi-download"></i> Installation starten
                        </button>
                    </div>
                    
                    <div id="install-progress" class="mt-4" style="display: none;">
                        <div class="progress">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                        </div>
                        <div class="text-center mt-2">
                            <span id="install-status">Installation wird vorbereitet...</span>
                        </div>
                    </div>
                    
                    <div id="install-result" class="mt-4" style="display: none;">
                        <!-- Ergebnis wird hier angezeigt -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    document.getElementById('install-btn').addEventListener('click', async function() {
        const btn = this;
        const progress = document.getElementById('install-progress');
        const result = document.getElementById('install-result');
        const status = document.getElementById('install-status');
        const progressBar = document.querySelector('.progress-bar');
        
        // UI vorbereiten
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Installation läuft...';
        progress.style.display = 'block';
        result.style.display = 'none';
        
        // Fortschritt simulieren
        let progressValue = 0;
        const progressInterval = setInterval(() => {
            progressValue += Math.random() * 15;
            if (progressValue > 90) progressValue = 90;
            progressBar.style.width = progressValue + '%';
        }, 200);
        
        try {
            // Installation starten
            status.textContent = 'Datenbank-Tabelle wird erstellt...';
            
            const response = await fetch(window.location.pathname, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'plugin=content&action=install_database'
            });
            
            const data = await response.json();
            
            // Fortschritt abschließen
            clearInterval(progressInterval);
            progressBar.style.width = '100%';
            progressBar.classList.remove('progress-bar-animated');
            
            if (data.success) {
                status.textContent = 'Installation erfolgreich abgeschlossen!';
                result.innerHTML = `
                    <div class="alert alert-success">
                        <h5><i class="bi bi-check-circle"></i> Installation erfolgreich!</h5>
                        <p>${data.message}</p>
                        <p>Das Content Management System ist jetzt einsatzbereit.</p>
                        <button class="btn btn-success" onclick="location.reload()">
                            <i class="bi bi-arrow-clockwise"></i> Seite neu laden
                        </button>
                    </div>
                `;
            } else {
                status.textContent = 'Installation fehlgeschlagen!';
                result.innerHTML = `
                    <div class="alert alert-danger">
                        <h5><i class="bi bi-exclamation-triangle"></i> Installation fehlgeschlagen!</h5>
                        <p>${data.error}</p>
                        <button class="btn btn-warning" onclick="location.reload()">
                            <i class="bi bi-arrow-clockwise"></i> Erneut versuchen
                        </button>
                    </div>
                `;
            }
            
        } catch (error) {
            clearInterval(progressInterval);
            status.textContent = 'Fehler bei der Installation!';
            result.innerHTML = `
                <div class="alert alert-danger">
                    <h5><i class="bi bi-exclamation-triangle"></i> Fehler bei der Installation!</h5>
                    <p>Ein unerwarteter Fehler ist aufgetreten: ${error.message}</p>
                    <button class="btn btn-warning" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise"></i> Erneut versuchen
                    </button>
                </div>
            `;
        }
        
        result.style.display = 'block';
    });
})();
</script>
