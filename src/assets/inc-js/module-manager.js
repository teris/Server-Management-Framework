/**
 * Module Manager JavaScript
 * @author Teris
 * @version 1.0.0
 */

// Verhindere doppelte Initialisierung
if (window.moduleManagerLoaded) {
    console.log('Module Manager bereits geladen - überspringe Initialisierung');
} else {
    window.moduleManagerLoaded = true;
    
    // Modale Fenster (global)
    var moduleUploadModal = null;
    var moduleUpdateModal = null;
    
    // Initialisierung
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof bootstrap !== 'undefined') {
            const uploadModalElement = document.getElementById('moduleUploadModal');
            const updateModalElement = document.getElementById('moduleUpdateModal');
            
            if (uploadModalElement) {
                moduleUploadModal = new bootstrap.Modal(uploadModalElement);
            }
            
            if (updateModalElement) {
                moduleUpdateModal = new bootstrap.Modal(updateModalElement);
            }
        }
    });
}

// ====== GLOBALE FUNKTIONEN (außerhalb des if-Blocks) ======

/**
 * Zeigt das Upload-Modal
 */
function showModuleUploadModal() {
    if (moduleUploadModal) {
        document.getElementById('moduleUploadForm').reset();
        moduleUploadModal.show();
    }
}

/**
 * Zeigt das Update-Modal
 */
function showModuleUpdateModal(moduleKey) {
    if (moduleUpdateModal) {
        document.getElementById('moduleUpdateForm').reset();
        document.getElementById('updateModuleKey').value = moduleKey;
        moduleUpdateModal.show();
    }
}

/**
 * Lädt ein Modul hoch
 */
async function uploadModule() {
    const form = document.getElementById('moduleUploadForm');
    const fileInput = document.getElementById('moduleZip');
    const keyInput = document.getElementById('moduleKey');
    
    if (!fileInput.files.length) {
        showAlert('Bitte wählen Sie eine ZIP-Datei aus', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'install_module');
    formData.append('module_zip', fileInput.files[0]);
    
    if (keyInput.value.trim()) {
        formData.append('module_key', keyInput.value.trim());
    }
    
    try {
        showLoadingOverlay('Modul wird installiert...');
        
        const response = await fetch('index.php?option=module-manager', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        hideLoadingOverlay();
        
        if (data.success) {
            showAlert(data.message || 'Modul erfolgreich installiert', 'success');
            if (moduleUploadModal) {
                moduleUploadModal.hide();
            }
            setTimeout(() => refreshModuleList(), 1000);
        } else {
            showAlert(data.error || 'Fehler beim Installieren des Moduls', 'danger');
        }
    } catch (error) {
        hideLoadingOverlay();
        console.error('Error uploading module:', error);
        showAlert('Fehler beim Hochladen: ' + error.message, 'danger');
    }
}

/**
 * Aktualisiert ein Modul
 */
async function updateModule() {
    const moduleKey = document.getElementById('updateModuleKey').value;
    const fileInput = document.getElementById('updateModuleZip');
    
    if (!fileInput.files.length) {
        showAlert('Bitte wählen Sie eine ZIP-Datei aus', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'update_module');
    formData.append('module_key', moduleKey);
    formData.append('module_zip', fileInput.files[0]);
    
    try {
        showLoadingOverlay('Modul wird aktualisiert...');
        
        const response = await fetch('index.php?option=module-manager', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        hideLoadingOverlay();
        
        if (data.success) {
            showAlert(data.message || 'Modul erfolgreich aktualisiert', 'success');
            if (moduleUpdateModal) {
                moduleUpdateModal.hide();
            }
            setTimeout(() => refreshModuleList(), 1000);
        } else {
            showAlert(data.error || 'Fehler beim Aktualisieren des Moduls', 'danger');
        }
    } catch (error) {
        hideLoadingOverlay();
        console.error('Error updating module:', error);
        showAlert('Fehler beim Aktualisieren: ' + error.message, 'danger');
    }
}

/**
 * Aktiviert/Deaktiviert ein Modul
 */
async function toggleModule(moduleKey, enable) {
    const action = enable ? 'aktiviert' : 'deaktiviert';
    
    if (!confirm(`Möchten Sie das Modul wirklich ${action}?`)) {
        return;
    }
    
    try {
        showLoadingOverlay(`Modul wird ${action}...`);
        
        const response = await fetch('index.php?option=module-manager', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: enable ? 'enable_module' : 'disable_module',
                module_key: moduleKey
            })
        });
        
        const data = await response.json();
        
        hideLoadingOverlay();
        
        if (data.success) {
            showAlert(data.message || `Modul erfolgreich ${action}`, 'success');
            setTimeout(() => {
                // Reload page to apply module changes
                window.location.reload();
            }, 1000);
        } else {
            showAlert(data.error || `Fehler beim ${action} des Moduls`, 'danger');
        }
    } catch (error) {
        hideLoadingOverlay();
        console.error('Error toggling module:', error);
        showAlert('Fehler: ' + error.message, 'danger');
    }
}

/**
 * Deinstalliert ein Modul
 */
async function uninstallModule(moduleKey) {
    if (!confirm('Möchten Sie das Modul wirklich deinstallieren? Diese Aktion erstellt ein Backup.')) {
        return;
    }
    
    try {
        showLoadingOverlay('Modul wird deinstalliert...');
        
        const response = await fetch('index.php?option=module-manager', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'uninstall_module',
                module_key: moduleKey
            })
        });
        
        const data = await response.json();
        
        hideLoadingOverlay();
        
        if (data.success) {
            showAlert(data.message || 'Modul erfolgreich deinstalliert', 'success');
            setTimeout(() => refreshModuleList(), 1000);
        } else {
            showAlert(data.error || 'Fehler beim Deinstallieren des Moduls', 'danger');
        }
    } catch (error) {
        hideLoadingOverlay();
        console.error('Error uninstalling module:', error);
        showAlert('Fehler beim Deinstallieren: ' + error.message, 'danger');
    }
}

/**
 * Aktualisiert die Modulliste
 */
function refreshModuleList() {
    window.location.reload();
}

/**
 * Aktualisiert ein Modul via GitHub-Katalog
 */
async function updateModuleFromGitHub(moduleKey) {
    if (!confirm('Möchten Sie das Modul via GitHub-Katalog aktualisieren?')) {
        return;
    }
    
    try {
        showLoadingOverlay('Modul wird von GitHub aktualisiert...');
        
        const response = await fetch('index.php?option=module-manager', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'update_from_github',
                module_key: moduleKey
            })
        });
        
        const data = await response.json();
        
        hideLoadingOverlay();
        
        if (data.success) {
            showAlert(data.message || 'Modul erfolgreich von GitHub aktualisiert', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showAlert(data.error || 'Fehler beim Aktualisieren des Moduls', 'danger');
        }
    } catch (error) {
        hideLoadingOverlay();
        console.error('Error updating module from GitHub:', error);
        showAlert('Fehler beim Aktualisieren: ' + error.message, 'danger');
    }
}

/**
 * Lädt den GitHub-Katalog
 */
async function loadGitHubCatalog() {
    try {
        showLoadingOverlay('Lade GitHub-Katalog...');
        
        const response = await fetch('index.php?option=module-manager', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'get_github_catalog'
            })
        });
        
        const data = await response.json();
        
        hideLoadingOverlay();
        
        if (data.success && data.catalog) {
            displayGitHubCatalog(data.catalog);
        } else {
            showAlert('Fehler beim Laden des Katalogs', 'danger');
        }
    } catch (error) {
        hideLoadingOverlay();
        console.error('Error loading GitHub catalog:', error);
        showAlert('Fehler beim Laden des Katalogs: ' + error.message, 'danger');
    }
}

/**
 * Zeigt den GitHub-Katalog an
 */
function displayGitHubCatalog(catalog) {
    const container = document.getElementById('github-catalog-list');
    if (!container) return;
    
    let html = '<div class="row">';
    
    for (const [key, module] of Object.entries(catalog)) {
        // Prüfe ob Modul bereits installiert ist
        const installedCard = document.querySelector(`[data-module-key="${key}"]`);
        const isInstalled = installedCard !== null;
        
        // Hole installierte Version
        let installedVersion = null;
        let updateAvailable = false;
        if (isInstalled) {
            const versionElement = installedCard.querySelector('.installed-version');
            if (versionElement) {
                installedVersion = versionElement.textContent;
                updateAvailable = compareVersions(module.version, installedVersion) > 0;
            }
        }
        
        html += `
        <div class="col-md-6 col-lg-4 mb-3">
            <div class="card module-card ${isInstalled ? 'border-secondary' : ''}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <span style="font-size: 2em;">${module.icon || '📦'}</span>
                        </div>
                        <div>
                            ${isInstalled ? '<span class="badge bg-secondary">Installiert</span>' : '<span class="badge bg-primary">Verfügbar</span>'}
                            ${updateAvailable ? '<span class="badge bg-warning">Update</span>' : ''}
                        </div>
                    </div>
                    
                    <h5 class="card-title">${module.name}</h5>
                    <p class="card-text text-muted small">${module.description || ''}</p>
                    
                    <div class="mb-2 small">
                        <strong>Version:</strong> ${module.version}
                        ${isInstalled && installedVersion ? `<br><strong>Installiert:</strong> ${installedVersion}` : ''}
                    </div>
                    
                    <div class="mt-2">
                        ${!isInstalled ? 
                            `<button class="btn btn-sm btn-success w-100" onclick="installModuleFromGitHub('${key}')">
                                <i class="fas fa-download"></i> Installieren
                            </button>` :
                            updateAvailable ?
                                `<button class="btn btn-sm btn-warning w-100" onclick="updateModuleFromGitHub('${key}')">
                                    <i class="fas fa-sync"></i> Update verfügbar
                                </button>` :
                                `<button class="btn btn-sm btn-secondary w-100" disabled>
                                    <i class="fas fa-check"></i> Aktuell
                                </button>`
                        }
                    </div>
                </div>
            </div>
        </div>`;
    }
    
    html += '</div>';
    container.innerHTML = html;
}

/**
 * Installiert ein Modul vom GitHub-Katalog
 */
async function installModuleFromGitHub(moduleKey) {
    if (!confirm('Möchten Sie das Modul vom GitHub-Katalog installieren?')) {
        return;
    }
    
    try {
        showLoadingOverlay('Modul wird von GitHub installiert...');
        
        const response = await fetch('index.php?option=module-manager', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'install_from_github',
                module_key: moduleKey
            })
        });
        
        const data = await response.json();
        
        hideLoadingOverlay();
        
        if (data.success) {
            showAlert(data.message || 'Modul erfolgreich installiert', 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showAlert(data.error || 'Fehler beim Installieren des Moduls', 'danger');
        }
    } catch (error) {
        hideLoadingOverlay();
        console.error('Error installing module from GitHub:', error);
        showAlert('Fehler beim Installieren: ' + error.message, 'danger');
    }
}

/**
 * Vergleicht zwei Versionen
 */
function compareVersions(v1, v2) {
    const parts1 = v1.split('.').map(Number);
    const parts2 = v2.split('.').map(Number);
    
    for (let i = 0; i < Math.max(parts1.length, parts2.length); i++) {
        const part1 = parts1[i] || 0;
        const part2 = parts2[i] || 0;
        
        if (part1 > part2) return 1;
        if (part1 < part2) return -1;
    }
    
    return 0;
}

/**
 * Zeigt eine Alert-Nachricht
 */
function showAlert(message, type = 'info') {
    // Verwende die bestehende Alert-Funktion oder erstelle eine neue
    if (typeof showNotification === 'function') {
        showNotification(message, type);
    } else {
        // Fallback zu Browser-Alert
        alert(message);
    }
}

/**
 * Zeigt ein Loading-Overlay
 */
function showLoadingOverlay(message = 'Wird geladen...') {
    let overlay = document.getElementById('loading-overlay');
    
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'loading-overlay';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        `;
        
        const content = document.createElement('div');
        content.style.cssText = `
            background: white;
            padding: 30px;
            border-radius: 8px;
            text-align: center;
        `;
        
        content.innerHTML = `
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div id="loading-message">${message}</div>
        `;
        
        overlay.appendChild(content);
        document.body.appendChild(overlay);
    } else {
        document.getElementById('loading-message').textContent = message;
        overlay.style.display = 'flex';
    }
}

/**
 * Versteckt das Loading-Overlay
 */
function hideLoadingOverlay() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
}
