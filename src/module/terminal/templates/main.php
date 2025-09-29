<?php
/**
 * Terminal Module Main Template
 * Haupttemplate mit Voraussetzungsprüfung
 */

// Modul initialisieren
require_once __DIR__ . '/../Module.php';
$terminalModule = new TerminalModule('terminal');

// Prüfe Voraussetzungen beim Laden der main.php (nicht verwendet, da direkte Prüfung verwendet wird)
// $requirementsData = $terminalModule->checkRequirements();

// Prüfe spezifisch die wichtigsten Abhängigkeiten
$novncPath = dirname(__FILE__) . '/../assets/novnc';
$xtermPath = dirname(__FILE__) . '/../assets/xtermjs';
$websockifyPath = dirname(__FILE__) . '/../assets/websockify';
$sshProxyPath = dirname(__FILE__) . '/../assets/ssh-proxy';

$novncInstalled = is_dir($novncPath) && count(glob($novncPath . '/*')) > 0;
$xtermInstalled = is_dir($xtermPath) && count(glob($xtermPath . '/*')) > 0;
$proxiesInstalled = is_dir($websockifyPath) && is_dir($sshProxyPath);

$allDependenciesMet = $novncInstalled && $xtermInstalled && $proxiesInstalled;

// Debug-Ausgabe (temporär) - wird in Browser-Konsole angezeigt
echo "<script>";
echo "console.log('Terminal Module Debug:');";
echo "console.log('novncInstalled: " . ($novncInstalled ? 'true' : 'false') . "');";
echo "console.log('xtermInstalled: " . ($xtermInstalled ? 'true' : 'false') . "');";
echo "console.log('proxiesInstalled: " . ($proxiesInstalled ? 'true' : 'false') . "');";
echo "console.log('allDependenciesMet: " . ($allDependenciesMet ? 'true' : 'false') . "');";
echo "</script>";

if (!$allDependenciesMet) {
    // Zeige Installationsassistenten innerhalb des Modul-Containers
    ?>
    <div id="terminal-content">
        <div class="alert alert-warning">
            <h4><i class="fas fa-exclamation-triangle"></i> Terminal Module Installation erforderlich</h4>
            <p>Das Terminal-Modul benötigt zusätzliche Komponenten, die noch nicht installiert sind.</p>
            
            <!-- Abhängigkeiten anzeigen -->
            <div class="mt-3">
                <h5>Abhängigkeiten prüfen:</h5>
                <ul class="list-group">
                    <?php 
                    $requirements = $requirementsData['requirements'];
                    foreach ($requirements as $key => $requirement): 
                    ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>
                                <i class="fas fa-<?= $requirement['met'] ? 'check text-success' : 'times text-danger' ?>"></i>
                                <?= htmlspecialchars($requirement['name']) ?>
                                <small class="text-muted d-block"><?= htmlspecialchars($requirement['description']) ?></small>
                            </span>
                            <div class="text-right">
                                <?php if ($requirement['met']): ?>
                                    <span class="badge badge-success">OK</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Fehlt</span>
                                <?php endif; ?>
                                <small class="text-muted d-block"><?= htmlspecialchars($requirement['current']) ?></small>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div class="mt-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <p class="text-info mb-0"><i class="fas fa-info-circle"></i> Fehlende Abhängigkeiten können automatisch installiert werden</p>
                    <button class="btn btn-primary" id="installMissingBtn">
                        <i class="fas fa-download"></i> Fehlende Abhängigkeiten installieren
                    </button>
                </div>
                
                <div id="installation-progress" class="mt-3" style="display: none;">
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                    </div>
                    <div id="installation-log" class="mt-2"></div>
                </div>
                
                <!-- Schreibrechte-Hilfe -->
                <div id="permissions-help" class="mt-3" style="display: none;">
                    <div class="alert alert-warning">
                        <h5><i class="fas fa-exclamation-triangle"></i> Schreibrechte-Problem</h5>
                        <p>Falls die Installation fehlschlägt aufgrund fehlender Schreibrechte, führen Sie folgende Befehle aus:</p>
                        <div class="bg-light p-3 rounded">
                            <?php 
                            $projectRoot = dirname(dirname(dirname(__FILE__)));
                            $terminalAssetsPath = $projectRoot . '/src/module/terminal/assets';
                            ?>
                            <h6>Windows (PowerShell als Administrator):</h6>
                            <code>icacls "<?= str_replace('/', '\\', $terminalAssetsPath) ?>" /grant Everyone:F /T</code>
                            
                            <h6 class="mt-3">Linux/macOS:</h6>
                            <code>chmod -R 755 "<?= $terminalAssetsPath ?>"</code>
                            
                            <h6 class="mt-3">Alternative (für alle Benutzer):</h6>
                            <code>chmod -R 777 "<?= $terminalAssetsPath ?>"</code>
                            
                            <h6 class="mt-3">Aktueller Pfad:</h6>
                            <code><?= $terminalAssetsPath ?></code>
                        </div>
                        <p class="mt-2"><small class="text-muted">Nach der Berechtigung-Änderung klicken Sie erneut auf "Installieren".</small></p>
                        <button class="btn btn-warning btn-sm" onclick="document.getElementById('installMissingBtn').click()">
                            <i class="fas fa-redo"></i> Erneut versuchen
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
} else {
?>

<div id="terminal-content">
    <!-- Action Result Display -->
    <div id="action-result" class="alert" style="display: none;"></div>
    
    <!-- Test SSH Connection Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-terminal"></i> Test SSH-Verbindung</h5>
        </div>
        <div class="card-body">
            <form id="test-ssh-form">
                <div class="row">
                    <div class="col-md-3">
                        <label for="test-ssh-host" class="form-label">Server IP/Hostname:</label>
                        <input type="text" class="form-control" id="test-ssh-host" placeholder="192.168.1.100" required>
                    </div>
                    <div class="col-md-2">
                        <label for="test-ssh-port" class="form-label">Port:</label>
                        <input type="number" class="form-control" id="test-ssh-port" value="22" required>
                    </div>
                    <div class="col-md-2">
                        <label for="test-ssh-user" class="form-label">Benutzername:</label>
                        <input type="text" class="form-control" id="test-ssh-user" placeholder="root" required>
                    </div>
                    <div class="col-md-3">
                        <label for="test-ssh-password" class="form-label">Passwort:</label>
                        <input type="password" class="form-control" id="test-ssh-password" placeholder="Passwort" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-play"></i> Verbinden
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Header with Quick Actions -->
    <div class="terminal-header">
        <div class="header-actions">
            <button class="btn btn-primary" onclick="showAddServerModal('vnc')">
                <i class="fas fa-plus"></i> <?= t('add_server') ?> (VNC)
            </button>
            <button class="btn btn-primary" onclick="showAddServerModal('ssh')">
                <i class="fas fa-plus"></i> <?= t('add_server') ?> (SSH)
            </button>
            <button class="btn btn-secondary" onclick="refreshServers()">
                <i class="fas fa-sync"></i> <?= t('refresh') ?>
            </button>
            <button class="btn btn-outline-info" onclick="showManagement()">
                <i class="fas fa-cogs"></i> Verwaltung
            </button>
        </div>
    </div>

    <!-- Main Terminal Interface -->
    <div class="terminal-interface">
    <!-- VNC Terminal Section -->
    <div class="terminal-section">
        <div class="section-header">
            <h3><i class="fas fa-desktop"></i> <?= t('vnc_terminal') ?></h3>
            <div class="section-actions">
                <button class="btn btn-sm btn-outline-primary" onclick="toggleVNCList()">
                    <i class="fas fa-list"></i> <?= t('vnc_servers') ?>
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="refreshVNCServers()">
                    <i class="fas fa-sync"></i> <?= t('refresh') ?>
                </button>
            </div>
        </div>
        
        <!-- VNC Server List -->
        <div id="vnc-server-list" class="server-list">
            <div class="server-grid" id="vnc-servers-grid">
                <!-- VNC servers will be loaded here -->
            </div>
        </div>
            
            <!-- VNC Terminal Container -->
            <div id="vnc-terminal-container" class="terminal-container" style="display: none;">
                <div class="terminal-toolbar">
                    <div class="toolbar-left">
                        <span class="connection-status" id="vnc-status">
                            <i class="fas fa-circle text-danger"></i> <?= t('disconnected') ?>
                        </span>
                        <span class="server-info" id="vnc-server-info"></span>
                    </div>
                    <div class="toolbar-right">
                        <button class="btn btn-sm btn-outline-secondary" onclick="toggleVNCFullscreen()">
                            <i class="fas fa-expand"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="disconnectVNC()">
                            <i class="fas fa-times"></i> <?= t('disconnect') ?>
                        </button>
                    </div>
                </div>
                <div id="vnc-screen" class="terminal-screen">
                    <!-- noVNC wird hier geladen -->
                </div>
            </div>
    </div>

    <!-- SSH Terminal Section -->
    <div class="terminal-section">
        <div class="section-header">
            <h3><i class="fas fa-terminal"></i> <?= t('ssh_terminal') ?></h3>
            <div class="section-actions">
                <button class="btn btn-sm btn-outline-primary" onclick="toggleSSHList()">
                    <i class="fas fa-list"></i> <?= t('ssh_servers') ?>
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="refreshSSHServers()">
                    <i class="fas fa-sync"></i> <?= t('refresh') ?>
                </button>
            </div>
        </div>
        
        <!-- SSH Server List -->
        <div id="ssh-server-list" class="server-list">
            <div class="server-grid" id="ssh-servers-grid">
                <!-- SSH servers will be loaded here -->
            </div>
        </div>
        
        <!-- SSH Terminal Container -->
        <div id="ssh-terminal-container" class="terminal-container" style="display: none;">
            <div class="terminal-toolbar">
                <div class="toolbar-left">
                    <span class="connection-status" id="ssh-status">
                        <i class="fas fa-circle text-danger"></i> <?= t('disconnected') ?>
                    </span>
                    <span class="server-info" id="ssh-server-info"></span>
                </div>
                <div class="toolbar-right">
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleSSHFullscreen()">
                        <i class="fas fa-expand"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="disconnectSSH()">
                        <i class="fas fa-times"></i> <?= t('disconnect') ?>
                    </button>
                </div>
            </div>
            <div id="ssh-terminal" class="terminal-screen">
                <!-- xterm.js wird hier geladen -->
            </div>
        </div>
    </div>
    </div>

    <!-- VNC Connect Modal -->
    <div class="modal fade" id="vncConnectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">VNC Verbindung</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="vncConnectForm">
                        <input type="hidden" id="vncServerId">
                        <div class="mb-3">
                            <label class="form-label">Server</label>
                            <div id="vncServerInfo" class="form-control-plaintext bg-light p-2 rounded">
                                <!-- Server info wird hier angezeigt -->
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="vncPassword" class="form-label">VNC Passwort *</label>
                            <input type="password" class="form-control" id="vncPassword" name="password" 
                                   placeholder="VNC Passwort eingeben" required>
                            <div class="form-text">Das VNC Passwort wird nicht gespeichert</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <?= t('cancel') ?>
                    </button>
                    <button type="button" class="btn btn-primary" onclick="connectVNCWithPassword()">
                        <i class="fas fa-play"></i> Verbinden
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- SSH Connect Modal -->
    <div class="modal fade" id="sshConnectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">SSH Verbindung</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="sshConnectForm">
                        <input type="hidden" id="sshServerId">
                        <div class="mb-3">
                            <label class="form-label">Server</label>
                            <div id="sshServerInfo" class="form-control-plaintext bg-light p-2 rounded">
                                <!-- Server info wird hier angezeigt -->
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="sshUsername" class="form-label">Benutzername *</label>
                            <input type="text" class="form-control" id="sshUsername" name="username" 
                                   placeholder="SSH Benutzername eingeben" required>
                        </div>
                        <div class="mb-3">
                            <label for="sshPassword" class="form-label">Passwort *</label>
                            <input type="password" class="form-control" id="sshPassword" name="password" 
                                   placeholder="SSH Passwort eingeben" required>
                            <div class="form-text">Die Anmeldedaten werden nicht gespeichert</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <?= t('cancel') ?>
                    </button>
                    <button type="button" class="btn btn-primary" onclick="connectSSHWithCredentials()">
                        <i class="fas fa-play"></i> Verbinden
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Server Modal -->
    <div class="modal fade" id="addServerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= t('add_server') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addServerForm">
                        <input type="hidden" id="serverType">
                        <div class="mb-3">
                            <label for="serverName" class="form-label"><?= t('server_name') ?> *</label>
                            <input type="text" class="form-control" id="serverName" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="serverHost" class="form-label"><?= t('host') ?> *</label>
                            <input type="text" class="form-control" id="serverHost" name="host" required>
                        </div>
                        <div class="mb-3">
                            <label for="serverPort" class="form-label"><?= t('port') ?> *</label>
                            <input type="number" class="form-control" id="serverPort" name="port" required>
                        </div>
                        <div class="mb-3">
                            <label for="serverDescription" class="form-label"><?= t('description') ?></label>
                            <textarea class="form-control" id="serverDescription" name="description" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <?= t('cancel') ?>
                    </button>
                    <button type="button" class="btn btn-primary" onclick="saveServer()">
                        <i class="fas fa-save"></i> <?= t('save') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php } ?>

<!-- Terminal Modal -->
<div class="modal fade" id="terminalModal" tabindex="-1" aria-labelledby="terminalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="terminalModalLabel">
                    <i class="fas fa-terminal"></i> SSH Terminal - <span id="terminal-server-info"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="terminal-container">
                    <div id="terminal" style="height: 500px; background: #1e1e1e; color: #ffffff; font-family: 'Courier New', monospace; padding: 0; overflow: hidden; position: relative;">
                        <!-- xterm.js terminal will be mounted here -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <div class="d-flex justify-content-between w-100">
                    <div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearTerminal()">
                            <i class="fas fa-trash"></i> Terminal leeren
                        </button>
                        <button type="button" class="btn btn-outline-info btn-sm" onclick="toggleFullscreen()">
                            <i class="fas fa-expand"></i> Vollbild
                        </button>
                    </div>
                    <div>
                        <span class="badge bg-success me-2" id="connection-status">
                            <i class="fas fa-circle"></i> Verbunden
                        </span>
                        <button type="button" class="btn btn-danger" onclick="disconnectSSH()">
                            <i class="fas fa-times"></i> Trennen
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include xterm.js from CDN -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.css">
<script src="https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xterm-addon-web-links@0.9.0/lib/xterm-addon-web-links.js"></script>

<!-- Include CSS -->
<link rel="stylesheet" href="/src/module/terminal/assets/module.css">

<!-- Include JavaScript -->
<script src="/src/module/terminal/assets/module.js"></script>
