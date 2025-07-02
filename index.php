<?php
/**
 * Server Management Interface - Hauptseite mit Login-System
 * Verwendet framework.php für alle API-Operationen
 */

require_once 'framework.php';
require_once 'auth_handler.php';

// Login-Überprüfung - Weiterleitung zu login.php wenn nicht eingeloggt
requireLogin();

// Handler für Logout
if (isset($_GET['logout'])) {
    SessionManager::logout();
    header('Location: login.php');
    exit;
}

// Session-Informationen für JavaScript
$session_info = getSessionInfoForJS();

// Handler einbinden (mit AJAX-Auth-Check)
include("handler.php");

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Management Interface</title>
    <link rel="stylesheet" type="text/css" href="assets/main.css">
</head>
<body>
    <div class="container">
        <!-- User Info Header -->
        <div class="user-info">
            <div class="user-details">
                <div class="user-avatar">
                    <?= strtoupper(substr($session_info['user']['full_name'] ?? $session_info['user']['username'], 0, 1)) ?>
                </div>
                <div class="user-text">
                    <h3><?= htmlspecialchars($session_info['user']['full_name'] ?? $session_info['user']['username']) ?></h3>
                    <p><?= htmlspecialchars($session_info['user']['email']) ?> • <?= htmlspecialchars($session_info['user']['role']) ?></p>
                </div>
            </div>
            
                            <div class="session-controls">
                <div class="session-timer" id="sessionTimer">
                    🕒 <span id="timeRemaining">--:--</span>
                </div>
                <a href="password_change.php" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px; margin-right: 10px;">
                    🔑 Passwort
                </a>
                <a href="?logout=1" class="logout-btn" onclick="return confirm('Möchten Sie sich wirklich abmelden?')">
                    🚪 Abmelden
                </a>
            </div>
        </div>
        
        <div class="header">
            <h1>Server Management Interface</h1>
            <p>Professionelles Framework für Proxmox, ISPConfig und OVH - Modulares OOP-Design</p>
        </div>
        
        <div class="tabs">
            <button class="tab active" onclick="showTab('admin', this)">📊 Admin Dashboard</button>
            <button class="tab" onclick="showTab('proxmox', this)">🖥️ Proxmox VM</button>
            <button class="tab" onclick="showTab('ispconfig', this)">🌐 ISPConfig Website</button>
            <button class="tab" onclick="showTab('ovh', this)">🔗 OVH Domain</button>
            <button class="tab" onclick="showTab('network', this)">🔧 Netzwerk Config</button>
            <button class="tab" onclick="showTab('database', this)">🗄️ Datenbank</button>
            <button class="tab" onclick="showTab('email', this)">📧 E-Mail</button>
            <button class="tab" onclick="showTab('endpoints', this)">🔌 API Endpoints</button>
        </div>
        
        <div class="content">
            <!-- Admin Dashboard Tab -->
            <div id="admin" class="tab-content">
                <h2>📊 Admin Dashboard</h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Proxmox VMs</h3>
                        <div class="number" id="vm-count">-</div>
                    </div>
                    <div class="stat-card">
                        <h3>ISPConfig Websites</h3>
                        <div class="number" id="website-count">-</div>
                    </div>
                    <div class="stat-card">
                        <h3>Datenbanken</h3>
                        <div class="number" id="database-count">-</div>
                    </div>
                    <div class="stat-card">
                        <h3>E-Mail Accounts</h3>
                        <div class="number" id="email-count">-</div>
                    </div>
                    <div class="stat-card">
                        <h3>OVH Domains</h3>
                        <div class="number" id="domain-count">-</div>
                    </div>
                    <div class="stat-card">
                        <h3>OVH VPS</h3>
                        <div class="number" id="vps-count">-</div>
                    </div>
                </div>
                
                <div class="tabs" style="margin-bottom: 20px;">
                    <button class="tab active" onclick="showAdminTab('vms', this)">VMs verwalten</button>
                    <button class="tab" onclick="showAdminTab('websites', this)">Websites verwalten</button>
                    <button class="tab" onclick="showAdminTab('databases', this)">Datenbanken verwalten</button>
                    <button class="tab" onclick="showAdminTab('emails', this)">E-Mails verwalten</button>
                    <button class="tab" onclick="showAdminTab('domains', this)">Domains verwalten</button>
                    <button class="tab" onclick="showAdminTab('vps-list', this)">VPS verwalten</button>
                    <button class="tab" onclick="showAdminTab('logs', this)">Activity Log</button>
                </div>
                
                <!-- VMs Management -->
                <div id="admin-vms" class="admin-tab-content">
                    <div class="search-box">
                        <input type="text" id="vm-search" placeholder="VMs durchsuchen..." onkeyup="filterTable('vms-table', this.value)">
                        <button class="btn" onclick="loadVMs()">🔄 Aktualisieren</button>
                        <button class="btn btn-secondary" onclick="loadProxmoxNodes()">📡 Nodes laden</button>
                    </div>
                    <div class="table-container">
                        <table class="data-table" id="vms-table">
                            <thead>
                                <tr>
                                    <th>VM ID</th>
                                    <th>Name</th>
                                    <th>Node</th>
                                    <th>Status</th>
                                    <th>CPU</th>
                                    <th>RAM</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody id="vms-tbody">
                                <tr><td colspan="7" style="text-align: center;">Lade VMs...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Weitere Admin Tabs bleiben unverändert... -->
                <!-- [Alle anderen Tab-Inhalte aus der ursprünglichen index.php übernehmen] -->
                
                <!-- Websites Management -->
                <div id="admin-websites" class="admin-tab-content hidden">
                    <div class="search-box">
                        <input type="text" id="website-search" placeholder="Websites durchsuchen..." onkeyup="filterTable('websites-table', this.value)">
                        <button class="btn" onclick="loadWebsites()">🔄 Aktualisieren</button>
                    </div>
                    <div class="table-container">
                        <table class="data-table" id="websites-table">
                            <thead>
                                <tr>
                                    <th>Domain</th>
                                    <th>IP Adresse</th>
                                    <th>System User</th>
                                    <th>Status</th>
                                    <th>Quota (MB)</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody id="websites-tbody">
                                <tr><td colspan="6" style="text-align: center;">Lade Websites...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Weitere Admin-Tabs... [Rest der ursprünglichen Tabs] -->
            </div>
            
            <!-- API Endpoints Tab -->
            <div id="endpoints" class="tab-content hidden">
                <h2>🔌 API Endpoints Tester</h2>
                <p>Testen Sie einzelne API-Endpunkte der verschiedenen Services</p>
                
                <!-- Proxmox Endpoints -->
                <div class="endpoint-section">
                    <h3>🖥️ Proxmox API Endpoints</h3>
                    <div class="endpoint-buttons">
                        <button class="btn" onclick="testEndpoint('get_proxmox_nodes')">📡 Nodes laden</button>
                        <button class="btn" onclick="testEndpointWithParam('get_proxmox_storages', 'node', 'pve')">💾 Storages laden</button>
                        <button class="btn" onclick="testEndpointWithParams('get_vm_config', {node: 'pve', vmid: '100'})">⚙️ VM Config</button>
                        <button class="btn" onclick="testEndpointWithParams('get_vm_status', {node: 'pve', vmid: '100'})">📊 VM Status</button>
                        <button class="btn" onclick="testEndpointWithParams('clone_vm', {node: 'pve', vmid: '100', newid: '101', name: 'clone-test'})">📋 VM Klonen</button>
                    </div>
                </div>
                
                <!-- Result Display -->
                <div id="endpoint-result" class="result-box hidden">
                    <h4>🔍 Endpoint Response:</h4>
                    <pre id="endpoint-response" style="background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; max-height: 400px;"></pre>
                </div>
            </div>
            
            <!-- [Alle anderen Tab-Inhalte aus der ursprünglichen index.php übernehmen] -->
            
            <!-- Proxmox VM Tab -->
            <div id="proxmox" class="tab-content hidden">
                <h2>🖥️ VM auf Proxmox anlegen</h2>
                <form onsubmit="createVM(event)">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="vm_name">VM Name</label>
                            <input type="text" id="vm_name" name="name" required placeholder="z.B. web-server-01">
                        </div>
                        <div class="form-group">
                            <label for="vm_id">VM ID</label>
                            <input type="number" id="vm_id" name="vmid" required placeholder="100" min="100" max="999999">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="vm_memory">RAM (MB)</label>
                            <select id="vm_memory" name="memory">
                                <option value="1024">1 GB</option>
                                <option value="2048">2 GB</option>
                                <option value="4096" selected>4 GB</option>
                                <option value="8192">8 GB</option>
                                <option value="16384">16 GB</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="vm_cores">CPU Kerne</label>
                            <select id="vm_cores" name="cores">
                                <option value="1">1 Kern</option>
                                <option value="2" selected>2 Kerne</option>
                                <option value="4">4 Kerne</option>
                                <option value="8">8 Kerne</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="vm_disk">Festplatte (GB)</label>
                            <input type="number" id="vm_disk" name="disk" value="20" required min="10">
                        </div>
                        <div class="form-group">
                            <label for="vm_node">Proxmox Node</label>
                            <input type="text" id="vm_node" name="node" value="pve" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="vm_storage">Storage</label>
                            <input type="text" id="vm_storage" name="storage" value="local-lvm" required>
                        </div>
                        <div class="form-group">
                            <label for="vm_bridge">Netzwerk Bridge</label>
                            <input type="text" id="vm_bridge" name="bridge" value="vmbr0" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="vm_mac">MAC Adresse (optional)</label>
                        <input type="text" id="vm_mac" name="mac" placeholder="aa:bb:cc:dd:ee:ff" pattern="[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}">
                    </div>
                    
                    <div class="form-group">
                        <label for="vm_iso">ISO Image</label>
                        <input type="text" id="vm_iso" name="iso" value="local:iso/ubuntu-22.04-server-amd64.iso" required>
                    </div>
                    
                    <button type="submit" class="btn">
                        <span class="loading hidden"></span>
                        VM erstellen
                    </button>
                </form>
            </div>
            
            <!-- [Weitere Tabs aus der ursprünglichen index.php...] -->
        </div>
    </div>

    <!-- Session Warning -->
    <div class="session-warning" id="sessionWarning">
        <h4>⚠️ Session läuft ab</h4>
        <p>Ihre Sitzung läuft in <span id="warningTime"></span> ab. Klicken Sie hier um zu verlängern.</p>
    </div>

    <!-- Session Expired Modal -->
    <div class="session-expired" id="sessionExpired">
        <div class="session-expired-content">
            <h2>🔒 Sitzung abgelaufen</h2>
            <p>Ihre Sitzung ist abgelaufen. Sie werden zur Anmeldeseite weitergeleitet.</p>
            <button class="btn" onclick="window.location.href='login.php'">Zur Anmeldung</button>
        </div>
    </div>

	<script data-cfasync="false" type="text/javascript" src="assets/session.js"></script>
    <script data-cfasync="false" type="text/javascript" src="assets/main.js"></script>
    
    <script>
        // Session-Timer beim Laden der Seite starten
        document.addEventListener('DOMContentLoaded', function() {
            initSessionTimer();
            
            if (!document.getElementById('admin').classList.contains('hidden')) {
                loadAllData();
            }
        });
    </script>

</body>
</html>