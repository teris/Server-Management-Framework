<?php
/**
 * Server Management Framework - Modulverwaltung
 * 
 * @author Teris
 * @version 1.0.0
 */

// Lade Abhängigkeiten
if (!isset($db)) {
    require_once dirname(__DIR__) . '/core/DatabaseManager.php';
    $db = DatabaseManager::getInstance();
}

require_once dirname(__DIR__) . '/core/ModuleManager.php';
require_once dirname(__DIR__) . '/core/LanguageManager.php';

$moduleManager = new ModuleManager();
$languageManager = LanguageManager::getInstance();
$message = '';
$messageType = '';

// POST-Verarbeitung (wie in system.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Aktivieren
        if (isset($_POST['enable_module'])) {
            $module_key = $_POST['enable_module'];
            $moduleManager->enableModule($module_key);
            $message = "Modul '$module_key' wurde aktiviert";
            $messageType = 'success';
        }
        
        // Deaktivieren
        if (isset($_POST['disable_module'])) {
            $module_key = $_POST['disable_module'];
            $moduleManager->disableModule($module_key);
            $message = "Modul '$module_key' wurde deaktiviert";
            $messageType = 'success';
        }
        
        // Deinstallieren
        if (isset($_POST['uninstall_module'])) {
            $module_key = $_POST['uninstall_module'];
            $moduleManager->uninstallModule($module_key);
            $message = "Modul '$module_key' wurde deinstalliert";
            $messageType = 'success';
        }
        
        // Installieren von GitHub
        if (isset($_POST['install_from_github'])) {
            $module_key = $_POST['module_key'];
            $result = $moduleManager->installFromGitHub($module_key);
            $message = "Modul '{$result['name']}' wurde von GitHub installiert";
            $messageType = 'success';
        }
        
        // Update von GitHub
        if (isset($_POST['update_from_github'])) {
            $module_key = $_POST['module_key'];
            $result = $moduleManager->updateFromGitHub($module_key);
            $message = "Modul wurde von GitHub aktualisiert";
            $messageType = 'success';
        }
        
        // Manuelle Installation (ZIP-Upload)
        if (isset($_POST['install_manual']) && isset($_FILES['module_zip'])) {
            $tmp_path = $_FILES['module_zip']['tmp_name'];
            $result = $moduleManager->installModule($tmp_path);
            $message = "Modul '{$result['key']}' wurde installiert";
            $messageType = 'success';
        }
        
        // Manuelles Update (ZIP-Upload)
        if (isset($_POST['update_manual']) && isset($_FILES['module_zip'])) {
            $module_key = $_POST['module_key'];
            $tmp_path = $_FILES['module_zip']['tmp_name'];
            $result = $moduleManager->updateModule($module_key, $tmp_path);
            $message = "Modul '$module_key' wurde aktualisiert";
            $messageType = 'success';
        }
        
    } catch (Exception $e) {
        $message = 'Fehler: ' . $e->getMessage();
        $messageType = 'danger';
        error_log('Module Manager Error: ' . $e->getMessage());
    }
}

// Module laden
$modules = $moduleManager->getAllModules();

// GitHub-Katalog laden
try {
    $githubCatalog = $moduleManager->getGitHubCatalog();
} catch (Exception $e) {
    $githubCatalog = [];
    error_log('Fehler beim Laden des GitHub-Katalogs: ' . $e->getMessage());
}

// Aktiven Tab ermitteln
$activeTab = $_GET['tab'] ?? 'installed';
?>

<div id="module-manager-area">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3><?php echo t('module_manager_title', 'Modulverwaltung'); ?></h3>
                    <ul class="nav nav-tabs card-header-tabs mt-2" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?php echo $activeTab === 'installed' ? 'active' : ''; ?>" href="?option=module-manager&tab=installed">
                                <i class="bi bi-box-seam"></i> Installierte Module
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?php echo $activeTab === 'available' ? 'active' : ''; ?>" href="?option=module-manager&tab=available">
                                <i class="bi bi-cloud-download"></i> Verfügbare Module
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="tab-content">
                        <!-- Installierte Module Tab -->
                        <div class="tab-pane fade <?php echo $activeTab === 'installed' ? 'show active' : ''; ?>" id="installed-modules">
                            <div class="mb-3">
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#moduleUploadModal">
                                    <i class="fas fa-upload"></i> Modul hochladen
                                </button>
                            </div>
                            
                            <div class="row">
                        <?php foreach ($modules as $key => $module): ?>
                        <div class="col-md-6 col-lg-4 mb-3" data-module-key="<?php echo htmlspecialchars($key); ?>">
                            <div class="card module-card <?php echo $module['enabled'] ? 'border-success' : ''; ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <span style="font-size: 2em;"><?php echo htmlspecialchars($module['icon'] ?? '📦'); ?></span>
                                        </div>
                                        <div>
                                            <?php if ($module['enabled']): ?>
                                                <span class="badge bg-success"><?php echo t('module_enabled', 'Aktiviert'); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><?php echo t('module_disabled', 'Deaktiviert'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <h5 class="card-title"><?php echo htmlspecialchars($module['name']); ?></h5>
                                    <p class="card-text text-muted small">
                                        <?php echo htmlspecialchars($module['description'] ?? ''); ?>
                                    </p>
                                    
                                    <div class="mb-2 small">
                                        <strong><?php echo t('module_author', 'Autor'); ?>:</strong> 
                                        <?php echo htmlspecialchars($module['author'] ?? 'Unbekannt'); ?>
                                    </div>
                                    
                                    <div class="mb-2 small">
                                        <strong><?php echo t('module_version', 'Version'); ?>:</strong> 
                                        <span class="installed-version"><?php echo htmlspecialchars($module['version'] ?? '1.0.0'); ?></span>
                                        <span class="github-version-check text-muted" data-module-key="<?php echo htmlspecialchars($key); ?>"></span>
                                    </div>
                                    
                                    <?php if (!empty($module['dependencies'])): ?>
                                    <div class="mb-2 small">
                                        <strong><?php echo t('module_dependencies', 'Abhängigkeiten'); ?>:</strong> 
                                        <?php echo htmlspecialchars(implode(', ', $module['dependencies'])); ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="mb-2">
                                        <?php if (empty($module['has_json'])): ?>
                                            <span class="btn btn-sm btn-secondary w-100" disabled title="Keine module.json vorhanden">
                                                <i class="fas fa-exclamation-triangle"></i> Kann nicht aktiviert werden
                                            </span>
                                        <?php elseif ($module['enabled']): ?>
                                            <form method="post" style="display:inline;">
                                                <button type="submit" name="disable_module" value="<?php echo htmlspecialchars($key); ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-power-off"></i> Deaktivieren
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" style="display:inline;">
                                                <button type="submit" name="enable_module" value="<?php echo htmlspecialchars($key); ?>" class="btn btn-sm btn-success">
                                                    <i class="fas fa-power-off"></i> Aktivieren
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                   
                                        <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#moduleUpdateModal" onclick="setUpdateModuleKey('<?php echo htmlspecialchars($key); ?>')">
                                            <i class="fas fa-upload"></i>Update (Manuell)
                                        </button>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="module_key" value="<?php echo htmlspecialchars($key); ?>">
                                            <button type="submit" name="update_from_github" class="btn btn-sm btn-primary">
                                                <i class="fas fa-cloud-download-alt"></i>Update via Katalog
                                            </button>
                                        </form>

                                        <form method="post" style="display:inline;" onsubmit="return confirm('Wirklich deinstallieren?');">
                                            <button type="submit" name="uninstall_module" value="<?php echo htmlspecialchars($key); ?>" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i> Deinstallieren
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Verfügbare Module Tab -->
                        <div class="tab-pane fade <?php echo $activeTab === 'available' ? 'show active' : ''; ?>" id="available-modules">
                            <div class="mb-3">
                                <p class="text-muted">
                                    <i class="bi bi-info-circle"></i> 
                                    Module aus dem Katalog. Quelle: <a href="https://github.com/teris/SMF-Module" target="_blank">github.com/teris/SMF-Module</a>
                                </p>
                            </div>
                            
                            <div class="row">
                                <?php foreach ($githubCatalog as $key => $module): 
                                    // Prüfe ob installiert
                                    $isInstalled = isset($modules[$key]);
                                    $installedVersion = $isInstalled ? ($modules[$key]['version'] ?? null) : null;
                                    $catalogVersion = $module['version'] ?? '1.0.0';
                                    $needsUpdate = $installedVersion && version_compare($catalogVersion, $installedVersion, '>');
                                ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card module-card <?php echo $isInstalled ? 'border-secondary' : ''; ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <span style="font-size: 2em;"><?php echo htmlspecialchars($module['icon'] ?? '📦'); ?></span>
                                                </div>
                                                <div>
                                                    <?php if ($isInstalled): ?>
                                                        <span class="badge bg-secondary">Installiert</span>
                                                        <?php if ($needsUpdate): ?>
                                                            <span class="badge bg-warning">Update</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-primary">Verfügbar</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <h5 class="card-title"><?php echo htmlspecialchars($module['name']); ?></h5>
                                            <p class="card-text text-muted small">
                                                <?php echo htmlspecialchars($module['description'] ?? ''); ?>
                                            </p>
                                            
                                            <div class="mb-2 small">
                                                <strong>Version:</strong> <?php echo htmlspecialchars($catalogVersion); ?>
                                                <?php if ($installedVersion): ?>
                                                    <br><strong>Installiert:</strong> <?php echo htmlspecialchars($installedVersion); ?>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="mt-2">
                                                <?php if (!$isInstalled): ?>
                                                    <form method="post">
                                                        <input type="hidden" name="module_key" value="<?php echo htmlspecialchars($key); ?>">
                                                        <button type="submit" name="install_from_github" class="btn btn-sm btn-success w-100">
                                                            <i class="fas fa-download"></i> Installieren
                                                        </button>
                                                    </form>
                                                <?php elseif ($needsUpdate): ?>
                                                    <form method="post">
                                                        <input type="hidden" name="module_key" value="<?php echo htmlspecialchars($key); ?>">
                                                        <button type="submit" name="update_from_github" class="btn btn-sm btn-warning w-100">
                                                            <i class="fas fa-sync"></i> Update verfügbar
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <button class="btn btn-sm btn-secondary w-100" disabled>
                                                        <i class="fas fa-check"></i> Aktuell
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="moduleUploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Modul hochladen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="moduleZip" class="form-label">ZIP-Datei auswählen</label>
                        <input type="file" class="form-control" id="moduleZip" name="module_zip" accept=".zip" required>
                        <div class="form-text">
                            Struktur: <code>module/&lt;name&gt;/module.json</code><br>
                            Siehe: <a href="https://github.com/teris/SMF-Module" target="_blank">github.com/teris/SMF-Module</a>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" name="install_manual" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Hochladen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Modal -->
<div class="modal fade" id="moduleUpdateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Modul aktualisieren</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="updateModuleKey" name="module_key" value="">
                    
                    <div class="mb-3">
                        <label for="updateModuleZip" class="form-label">ZIP-Datei auswählen</label>
                        <input type="file" class="form-control" id="updateModuleZip" name="module_zip" accept=".zip" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" name="update_manual" class="btn btn-primary">
                        <i class="fas fa-sync"></i> Aktualisieren
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Smooth Animations */
#module-manager-area {
    animation: fadeIn 0.4s ease-in;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.module-card {
    transition: all 0.3s ease;
    height: 100%;
    animation: slideIn 0.5s ease-out;
    animation-fill-mode: both;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Gestaffelte Animation für Karten */
.module-card:nth-child(1) { animation-delay: 0.05s; }
.module-card:nth-child(2) { animation-delay: 0.1s; }
.module-card:nth-child(3) { animation-delay: 0.15s; }
.module-card:nth-child(4) { animation-delay: 0.2s; }
.module-card:nth-child(5) { animation-delay: 0.25s; }
.module-card:nth-child(6) { animation-delay: 0.3s; }
.module-card:nth-child(7) { animation-delay: 0.35s; }
.module-card:nth-child(8) { animation-delay: 0.4s; }
.module-card:nth-child(9) { animation-delay: 0.45s; }

.module-card:hover {
    transform: translateY(-5px) scale(1.02);
    box-shadow: 0 8px 16px rgba(0,0,0,0.2);
}

.module-card.border-success {
    border-width: 2px;
}

/* Tab Content Transitions */
.tab-pane {
    transition: opacity 0.3s ease-in-out;
}

.tab-pane:not(.show) {
    opacity: 0;
}

.tab-pane.show {
    opacity: 1;
}

/* Alert Animations */
.alert {
    animation: slideDown 0.4s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Button Transitions */
button, .btn {
    transition: all 0.2s ease;
}

button:hover:not(:disabled), .btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

button:active:not(:disabled), .btn:active:not(:disabled) {
    transform: translateY(0);
}

/* Loading Overlay */
.loading-overlay {
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
    animation: fadeIn 0.3s ease;
}

.loading-content {
    background: white;
    padding: 30px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    animation: zoomIn 0.3s ease;
}

@keyframes zoomIn {
    from {
        opacity: 0;
        transform: scale(0.8);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* Badge Animations */
.badge {
    animation: popIn 0.3s ease;
}

@keyframes popIn {
    0% {
        transform: scale(0);
    }
    50% {
        transform: scale(1.1);
    }
    100% {
        transform: scale(1);
    }
}
</style>

<script>
// Einfache Hilfsfunktion für Modal
function setUpdateModuleKey(key) {
    document.getElementById('updateModuleKey').value = key;
}

// Loading Overlay
function showLoadingOverlay(message = 'Wird verarbeitet...') {
    const overlay = document.createElement('div');
    overlay.className = 'loading-overlay';
    overlay.id = 'module-loading-overlay';
    
    overlay.innerHTML = `
        <div class="loading-content">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div>${message}</div>
        </div>
    `;
    
    document.body.appendChild(overlay);
}

function hideLoadingOverlay() {
    const overlay = document.getElementById('module-loading-overlay');
    if (overlay) {
        overlay.style.opacity = '0';
        setTimeout(() => overlay.remove(), 300);
    }
}

// Form Submit mit Loading
document.addEventListener('DOMContentLoaded', function() {
    console.log('Module Manager geladen');
    
    // Alle Formulare mit Loading-Overlay
    document.querySelectorAll('#module-manager-area form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                const action = submitBtn.name;
                let message = 'Wird verarbeitet...';
                
                if (action === 'enable_module') message = 'Modul wird aktiviert...';
                if (action === 'disable_module') message = 'Modul wird deaktiviert...';
                if (action === 'uninstall_module') message = 'Modul wird deinstalliert...';
                if (action === 'install_from_github') message = 'Modul wird von GitHub installiert...';
                if (action === 'update_from_github') message = 'Modul wird von GitHub aktualisiert...';
                if (action === 'install_manual') message = 'Modul wird hochgeladen...';
                if (action === 'update_manual') message = 'Modul wird aktualisiert...';
                
                showLoadingOverlay(message);
            }
        });
    });
    
    // Prüfe GitHub-Versionen (via PHP beim Laden)
    <?php 
    // Zeige (unbekannt) für Module die nicht im GitHub-Katalog sind
    foreach ($modules as $key => $module) {
        if (!isset($githubCatalog[$key])) {
            $safeKey = str_replace('-', '_', $key);
            echo "const elem_{$safeKey} = document.querySelector('.github-version-check[data-module-key=\"{$key}\"]');\n";
            echo "if (elem_{$safeKey}) {\n";
            echo "    elem_{$safeKey}.innerHTML = ' <small class=\"text-muted\">(unbekannt)</small>';\n";
            echo "    setTimeout(() => elem_{$safeKey}.style.opacity = '1', 100);\n";
            echo "}\n";
        } elseif (isset($githubCatalog[$key]['version']) && version_compare($githubCatalog[$key]['version'], $module['version'], '>')) {
            $safeKey = str_replace('-', '_', $key);
            echo "const elem_{$safeKey} = document.querySelector('.github-version-check[data-module-key=\"{$key}\"]');\n";
            echo "if (elem_{$safeKey}) {\n";
            echo "    elem_{$safeKey}.innerHTML = '<br><span class=\"badge bg-warning\">Update: " . htmlspecialchars($githubCatalog[$key]['version']) . "</span>';\n";
            echo "    setTimeout(() => elem_{$safeKey}.querySelector('.badge').style.transform = 'scale(1)', 100);\n";
            echo "}\n";
        }
    }
    ?>
    
    // Smooth scroll to top nach Reload (wenn Nachricht vorhanden)
    <?php if ($message): ?>
        window.scrollTo({ top: 0, behavior: 'smooth' });
    <?php endif; ?>
});
</script>
