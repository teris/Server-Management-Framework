<?php
/**
 * Server Management Interface - Hauptseite mit Login-System
 * Erweitert um Virtual MAC Management
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
            <button class="tab" onclick="showTab('virtual-mac', this)">🔌 Virtual MAC</button>
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
                        <h3>Virtual MACs</h3>
                        <div class="number" id="virtual-mac-count">-</div>
                    </div>
                </div>
                
                <div class="tabs" style="margin-bottom: 20px;">
                    <button class="tab active" onclick="showAdminTab('vms', this)">VMs verwalten</button>
                    <button class="tab" onclick="showAdminTab('websites', this)">Websites verwalten</button>
                    <button class="tab" onclick="showAdminTab('databases', this)">Datenbanken verwalten</button>
                    <button class="tab" onclick="showAdminTab('emails', this)">E-Mails verwalten</button>
                    <button class="tab" onclick="showAdminTab('domains', this)">Domains verwalten</button>
                    <button class="tab" onclick="showAdminTab('virtual-macs', this)">Virtual MACs verwalten</button>
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
                
                <!-- Databases Management -->
                <div id="admin-databases" class="admin-tab-content hidden">
                    <div class="search-box">
                        <input type="text" id="database-search" placeholder="Datenbanken durchsuchen..." onkeyup="filterTable('databases-table', this.value)">
                        <button class="btn" onclick="loadDatabases()">🔄 Aktualisieren</button>
                    </div>
                    <div class="table-container">
                        <table class="data-table" id="databases-table">
                            <thead>
                                <tr>
                                    <th>Datenbank Name</th>
                                    <th>User</th>
                                    <th>Typ</th>
                                    <th>Status</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody id="databases-tbody">
                                <tr><td colspan="5" style="text-align: center;">Lade Datenbanken...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Emails Management -->
                <div id="admin-emails" class="admin-tab-content hidden">
                    <div class="search-box">
                        <input type="text" id="email-search" placeholder="E-Mails durchsuchen..." onkeyup="filterTable('emails-table', this.value)">
                        <button class="btn" onclick="loadEmails()">🔄 Aktualisieren</button>
                    </div>
                    <div class="table-container">
                        <table class="data-table" id="emails-table">
                            <thead>
                                <tr>
                                    <th>E-Mail Adresse</th>
                                    <th>Name</th>
                                    <th>Quota (MB)</th>
                                    <th>Status</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody id="emails-tbody">
                                <tr><td colspan="5" style="text-align: center;">Lade E-Mail Accounts...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Domains Management -->
                <div id="admin-domains" class="admin-tab-content hidden">
                    <div class="search-box">
                        <input type="text" id="domain-search" placeholder="Domains durchsuchen..." onkeyup="filterTable('domains-table', this.value)">
                        <button class="btn" onclick="loadDomains()">🔄 Aktualisieren</button>
                    </div>
                    <div class="table-container">
                        <table class="data-table" id="domains-table">
                            <thead>
                                <tr>
                                    <th>Domain</th>
                                    <th>Ablaufdatum</th>
                                    <th>Auto-Renewal</th>
                                    <th>Status</th>
                                    <th>Nameserver</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody id="domains-tbody">
                                <tr><td colspan="6" style="text-align: center;">Lade Domains...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Virtual MACs Management -->
                <div id="admin-virtual-macs" class="admin-tab-content hidden">
                    <div class="search-box">
                        <input type="text" id="virtual-mac-search" placeholder="Virtual MACs durchsuchen..." onkeyup="filterTable('virtual-macs-table', this.value)">
                        <button class="btn" onclick="loadVirtualMacs()">🔄 Aktualisieren</button>
                        <button class="btn btn-secondary" onclick="loadDedicatedServers()">📡 Server laden</button>
                    </div>
                    <div class="table-container">
                        <table class="data-table" id="virtual-macs-table">
                            <thead>
                                <tr>
                                    <th>MAC-Adresse</th>
                                    <th>VM-Name</th>
                                    <th>IP-Adresse</th>
                                    <th>Service Name</th>
                                    <th>Typ</th>
                                    <th>Erstellt am</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody id="virtual-macs-tbody">
                                <tr><td colspan="7" style="text-align: center;">Lade Virtual MACs...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- VPS Management -->
                <div id="admin-vps-list" class="admin-tab-content hidden">
                    <div class="search-box">
                        <input type="text" id="vps-search" placeholder="VPS durchsuchen..." onkeyup="filterTable('vps-table', this.value)">
                        <button class="btn" onclick="loadVPSList()">🔄 Aktualisieren</button>
                    </div>
                    <div class="table-container">
                        <table class="data-table" id="vps-table">
                            <thead>
                                <tr>
                                    <th>VPS Name</th>
                                    <th>IP Adressen</th>
                                    <th>MAC Adressen</th>
                                    <th>Status</th>
                                    <th>Cluster</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody id="vps-tbody">
                                <tr><td colspan="6" style="text-align: center;">Lade VPS...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Activity Log -->
                <div id="admin-logs" class="admin-tab-content hidden">
                    <div class="search-box">
                        <input type="text" id="log-search" placeholder="Logs durchsuchen..." onkeyup="filterTable('logs-table', this.value)">
                        <button class="btn" onclick="loadActivityLog()">🔄 Aktualisieren</button>
                    </div>
                    <div class="table-container">
                        <table class="data-table" id="logs-table">
                            <thead>
                                <tr>
                                    <th>Zeitpunkt</th>
                                    <th>Aktion</th>
                                    <th>Details</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="logs-tbody">
                                <tr><td colspan="4" style="text-align: center;">Lade Activity Log...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Virtual MAC Tab -->
            <div id="virtual-mac" class="tab-content hidden">
                <h2>🔌 Virtual MAC Management</h2>
                
                <div class="tabs" style="margin-bottom: 20px;">
                    <button class="tab active" onclick="showVirtualMacTab('overview', this)">📊 Übersicht</button>
                    <button class="tab" onclick="showVirtualMacTab('create', this)">➕ Erstellen</button>
                    <button class="tab" onclick="showVirtualMacTab('ip-management', this)">🌐 IP Management</button>
                    <button class="tab" onclick="showVirtualMacTab('reverse-dns', this)">🔄 Reverse DNS</button>
                </div>
                
                <!-- Overview -->
                <div id="virtual-mac-overview" class="virtual-mac-tab-content">
                    <h3>📊 Virtual MAC Übersicht</h3>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h3>Gesamt Virtual MACs</h3>
                            <div class="number" id="total-virtual-macs">-</div>
                        </div>
                        <div class="stat-card">
                            <h3>Zugewiesene IPs</h3>
                            <div class="number" id="total-assigned-ips">-</div>
                        </div>
                        <div class="stat-card">
                            <h3>Dedicated Server</h3>
                            <div class="number" id="total-dedicated-servers">-</div>
                        </div>
                    </div>
                    
                    <div class="search-box">
                        <input type="text" id="virtual-mac-overview-search" placeholder="Virtual MACs durchsuchen..." onkeyup="filterTable('virtual-mac-overview-table', this.value)">
                        <button class="btn" onclick="loadVirtualMacOverview()">🔄 Aktualisieren</button>
                    </div>
                    
                    <div class="table-container">
                        <table class="data-table" id="virtual-mac-overview-table">
                            <thead>
                                <tr>
                                    <th>MAC-Adresse</th>
                                    <th>VM-Name</th>
                                    <th>IP-Adresse</th>
                                    <th>Service Name</th>
                                    <th>Typ</th>
                                    <th>Erstellt am</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody id="virtual-mac-overview-tbody">
                                <tr><td colspan="7" style="text-align: center;">Lade Virtual MAC Übersicht...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Create Virtual MAC -->
                <div id="virtual-mac-create" class="virtual-mac-tab-content hidden">
                    <h3>➕ Neue Virtual MAC erstellen</h3>
                    
                    <form onsubmit="createVirtualMac(event)">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="vmac_service_name">Service Name (Dedicated Server)</label>
                                <select id="vmac_service_name" name="service_name" required>
                                    <option value="">Server auswählen...</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="vmac_type">MAC-Typ</label>
                                <select id="vmac_type" name="type">
                                    <option value="ovh">OVH (Standard)</option>
                                    <option value="vmware">VMware</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="vmac_virtual_network_interface">Virtual Network Interface</label>
                            <input type="text" id="vmac_virtual_network_interface" name="virtual_network_interface" required placeholder="eth0">
                        </div>
                        
                        <button type="submit" class="btn">
                            <span class="loading hidden"></span>
                            Virtual MAC erstellen
                        </button>
                    </form>
                </div>
                
                <!-- IP Management -->
                <div id="virtual-mac-ip-management" class="virtual-mac-tab-content hidden">
                    <h3>🌐 IP-Adresse zu Virtual MAC zuweisen</h3>
                    
                    <form onsubmit="assignIPToVirtualMac(event)">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="vmac_ip_service_name">Service Name</label>
                                <select id="vmac_ip_service_name" name="service_name" required onchange="loadVirtualMacsForService(this.value)">
                                    <option value="">Server auswählen...</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="vmac_ip_mac_address">Virtual MAC</label>
                                <select id="vmac_ip_mac_address" name="mac_address" required>
                                    <option value="">Erst Service auswählen...</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="vmac_ip_address">IP-Adresse</label>
                                <input type="text" id="vmac_ip_address" name="ip_address" required placeholder="192.168.1.100">
                            </div>
                            <div class="form-group">
                                <label for="vmac_ip_vm_name">VM-Name</label>
                                <input type="text" id="vmac_ip_vm_name" name="virtual_machine_name" required placeholder="webserver-01">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn">
                            <span class="loading hidden"></span>
                            IP-Adresse zuweisen
                        </button>
                    </form>
                    
                    <hr>
                    
                    <h4>🗑️ IP-Adresse entfernen</h4>
                    <form onsubmit="removeIPFromVirtualMac(event)">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="vmac_remove_service_name">Service Name</label>
                                <select id="vmac_remove_service_name" name="service_name" required>
                                    <option value="">Server auswählen...</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="vmac_remove_mac_address">Virtual MAC</label>
                                <input type="text" id="vmac_remove_mac_address" name="mac_address" required placeholder="02:00:00:96:1f:85">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="vmac_remove_ip_address">IP-Adresse</label>
                            <input type="text" id="vmac_remove_ip_address" name="ip_address" required placeholder="192.168.1.100">
                        </div>
                        
                        <button type="submit" class="btn btn-warning">
                            <span class="loading hidden"></span>
                            IP-Adresse entfernen
                        </button>
                    </form>
                </div>
                
                <!-- Reverse DNS -->
                <div id="virtual-mac-reverse-dns" class="virtual-mac-tab-content hidden">
                    <h3>🔄 Reverse DNS Management</h3>
                    
                    <form onsubmit="createReverseDNS(event)">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="reverse_ip_address">IP-Adresse</label>
                                <input type="text" id="reverse_ip_address" name="ip_address" required placeholder="192.168.1.100">
                            </div>
                            <div class="form-group">
                                <label for="reverse_hostname">Hostname</label>
                                <input type="text" id="reverse_hostname" name="reverse" required placeholder="server.example.com">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn">
                            <span class="loading hidden"></span>
                            Reverse DNS erstellen
                        </button>
                    </form>
                    
                    <hr>
                    
                    <h4>🔍 Reverse DNS abfragen</h4>
                    <form onsubmit="queryReverseDNS(event)">
                        <div class="form-group">
                            <label for="query_reverse_ip">IP-Adresse</label>
                            <input type="text" id="query_reverse_ip" name="ip_address" required placeholder="192.168.1.100">
                        </div>
                        
                        <button type="submit" class="btn btn-secondary">
                            <span class="loading hidden"></span>
                            Reverse DNS abfragen
                        </button>
                    </form>
                    
                    <div id="reverse_dns_result" class="result-box hidden">
                        <h4>Reverse DNS Informationen:</h4>
                        <pre id="reverse_dns_content"></pre>
                    </div>
                </div>
            </div>
            
            <!-- Existing tabs (Proxmox, ISPConfig, etc.) remain the same -->
            
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
            
            <!-- ISPConfig Website Tab -->
            <div id="ispconfig" class="tab-content hidden">
                <h2>🌐 Website in ISPConfig erstellen</h2>
                <form onsubmit="createWebsite(event)">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="website_domain">Domain</label>
                            <input type="text" id="website_domain" name="domain" required placeholder="example.com">
                        </div>
                        <div class="form-group">
                            <label for="website_ip">IP Adresse</label>
                            <input type="text" id="website_ip" name="ip" required placeholder="192.168.1.100">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="website_user">System User</label>
                            <input type="text" id="website_user" name="user" required placeholder="web1">
                        </div>
                        <div class="form-group">
                            <label for="website_group">System Group</label>
                            <input type="text" id="website_group" name="group" required placeholder="client1">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="website_quota">HD Quota (MB)</label>
                            <input type="number" id="website_quota" name="quota" value="1000" required min="100">
                        </div>
                        <div class="form-group">
                            <label for="website_traffic">Traffic Quota (MB)</label>
                            <input type="number" id="website_traffic" name="traffic" value="10000" required min="1000">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn">
                        <span class="loading hidden"></span>
                        Website erstellen
                    </button>
                </form>
            </div>
            
            <!-- OVH Domain Tab -->
            <div id="ovh" class="tab-content hidden">
                <h2>🔗 Domain bei OVH bestellen</h2>
                <form onsubmit="orderDomain(event)">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="domain_name">Domain Name</label>
                            <input type="text" id="domain_name" name="domain" required placeholder="example.com">
                        </div>
                        <div class="form-group">
                            <label for="domain_duration">Laufzeit (Jahre)</label>
                            <select id="domain_duration" name="duration">
                                <option value="1" selected>1 Jahr</option>
                                <option value="2">2 Jahre</option>
                                <option value="3">3 Jahre</option>
                                <option value="5">5 Jahre</option>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn">
                        <span class="loading hidden"></span>
                        Domain bestellen
                    </button>
                </form>
                
                <hr>
                
                <h3>🔍 VPS Informationen abrufen</h3>
                <form onsubmit="getVPSInfo(event)" style="margin-top: 20px;">
                    <div class="form-group">
                        <label for="vps_name">VPS Name</label>
                        <input type="text" id="vps_name" name="vps_name" required placeholder="vpsXXXXX.ovh.net">
                    </div>
                    
                    <button type="submit" class="btn btn-secondary">
                        <span class="loading hidden"></span>
                        VPS Info abrufen
                    </button>
                </form>
                
                <div id="vps_result" class="result-box hidden">
                    <h4>VPS Informationen:</h4>
                    <p><strong>IP Adresse:</strong> <span id="vps_ip"></span></p>
                    <p><strong>MAC Adresse:</strong> <span id="vps_mac"></span></p>
                </div>
            </div>
            
            <!-- Network Configuration Tab -->
            <div id="network" class="tab-content hidden">
                <h2>🔧 VM Netzwerk Konfiguration</h2>
                <form onsubmit="updateVMNetwork(event)">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="net_vmid">VM ID</label>
                            <input type="number" id="net_vmid" name="vmid" required placeholder="100" min="100">
                        </div>
                        <div class="form-group">
                            <label for="net_mac">MAC Adresse</label>
                            <input type="text" id="net_mac" name="mac" required placeholder="aa:bb:cc:dd:ee:ff" pattern="[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}:[a-fA-F0-9]{2}">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="net_ip">IP Adresse</label>
                        <input type="text" id="net_ip" name="ip" required placeholder="192.168.1.100">
                    </div>
                    
                    <button type="submit" class="btn">
                        <span class="loading hidden"></span>
                        Netzwerk aktualisieren
                    </button>
                </form>
            </div>
            
            <!-- Database Tab -->
            <div id="database" class="tab-content hidden">
                <h2>🗄️ Datenbank anlegen</h2>
                <form onsubmit="createDatabase(event)">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="db_name">Datenbank Name</label>
                            <input type="text" id="db_name" name="name" required placeholder="my_database">
                        </div>
                        <div class="form-group">
                            <label for="db_user">Datenbank User</label>
                            <input type="text" id="db_user" name="user" required placeholder="db_user">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="db_password">Passwort</label>
                        <input type="password" id="db_password" name="password" required minlength="6">
                    </div>
                    
                    <button type="submit" class="btn">
                        <span class="loading hidden"></span>
                        Datenbank erstellen
                    </button>
                </form>
            </div>
            
            <!-- Email Tab -->
            <div id="email" class="tab-content hidden">
                <h2>📧 E-Mail Adresse anlegen</h2>
                <form onsubmit="createEmail(event)">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email_address">E-Mail Adresse</label>
                            <input type="email" id="email_address" name="email" required placeholder="user@example.com">
                        </div>
                        <div class="form-group">
                            <label for="email_login">Login Name</label>
                            <input type="text" id="email_login" name="login" required placeholder="user">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email_password">Passwort</label>
                            <input type="password" id="email_password" name="password" required minlength="6">
                        </div>
                        <div class="form-group">
                            <label for="email_quota">Quota (MB)</label>
                            <input type="number" id="email_quota" name="quota" value="1000" required min="100">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email_name">Vollständiger Name</label>
                            <input type="text" id="email_name" name="name" placeholder="Max Mustermann">
                        </div>
                        <div class="form-group">
                            <label for="email_domain">Domain</label>
                            <input type="text" id="email_domain" name="domain" required placeholder="example.com">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn">
                        <span class="loading hidden"></span>
                        E-Mail Adresse erstellen
                    </button>
                </form>
            </div>
            
            <!-- Endpoints Tab -->
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
                
                <!-- ISPConfig Endpoints -->
                <div class="endpoint-section">
                    <h3>🌐 ISPConfig API Endpoints</h3>
                    <div class="endpoint-buttons">
                        <button class="btn" onclick="testEndpoint('get_ispconfig_clients')">👥 Clients laden</button>
                        <button class="btn" onclick="testEndpoint('get_ispconfig_server_config')">⚙️ Server Config</button>
                    </div>
                </div>
                
                <!-- OVH Endpoints -->
                <div class="endpoint-section">
                    <h3>🔗 OVH API Endpoints</h3>
                    <div class="endpoint-buttons">
                        <button class="btn" onclick="testEndpointWithParam('get_ovh_domain_zone', 'domain', 'example.com')">🌐 Domain Zone</button>
                        <button class="btn" onclick="testEndpointWithParam('get_ovh_dns_records', 'domain', 'example.com')">📝 DNS Records</button>
                        <button class="btn" onclick="testEndpointWithParam('get_vps_ips', 'vps_name', 'vpsXXXXX.ovh.net')">🌐 VPS IPs</button>
                        <button class="btn" onclick="testEndpointWithParams('get_vps_ip_details', {vps_name: 'vpsXXXXX.ovh.net', ip: '1.2.3.4'})">📊 IP Details</button>
                        <button class="btn" onclick="testEndpointWithParams('control_ovh_vps', {vps_name: 'vpsXXXXX.ovh.net', vps_action: 'reboot'})">🔄 VPS Control</button>
                        <button class="btn" onclick="testEndpointWithParams('create_dns_record', {domain: 'example.com', type: 'A', subdomain: 'test', target: '1.2.3.4'})">➕ DNS Record</button>
                        <button class="btn" onclick="testEndpointWithParam('refresh_dns_zone', 'domain', 'example.com')">🔄 DNS Refresh</button>
                    </div>
                </div>
                
                <!-- Virtual MAC Endpoints -->
                <div class="endpoint-section">
                    <h3>🔌 Virtual MAC API Endpoints</h3>
                    <div class="endpoint-buttons">
                        <button class="btn" onclick="testEndpoint('get_all_virtual_macs')">📋 Alle Virtual MACs</button>
                        <button class="btn" onclick="testEndpointWithParam('get_virtual_mac_details', 'service_name', 'ns3112327.ip-54-36-111.eu')">🔍 MAC Details</button>
                        <button class="btn" onclick="testEndpointWithParams('create_virtual_mac', {service_name: 'ns3112327.ip-54-36-111.eu', virtual_network_interface: 'eth0', type: 'ovh'})">➕ Virtual MAC</button>
                        <button class="btn" onclick="testEndpointWithParams('assign_ip_to_virtual_mac', {service_name: 'ns3112327.ip-54-36-111.eu', mac_address: '02:00:00:96:1f:85', ip_address: '192.168.1.100', virtual_machine_name: 'test-vm'})">🌐 IP zuweisen</button>
                        <button class="btn" onclick="testEndpointWithParams('create_reverse_dns', {ip_address: '192.168.1.100', reverse: 'test.example.com'})">🔄 Reverse DNS</button>
                    </div>
                </div>
                
                <!-- Result Display -->
                <div id="endpoint-result" class="result-box hidden">
                    <h4>🔍 Endpoint Response:</h4>
                    <pre id="endpoint-response" style="background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; max-height: 400px;"></pre>
                </div>
            </div>
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
    <script data-cfasync="false" type="text/javascript" src="assets/lazy-loading-main.js"></script>
    <script>
        // Session-Timer beim Laden der Seite starten
        document.addEventListener('DOMContentLoaded', function() {
            initSessionTimer();
            // Weitere Initialisierungen, falls nicht von lazy-loading-main.js abgedeckt, hier einfügen.
            // lazy-loading-main.js sollte das Anzeigen des initialen Tabs und das Laden der Stats übernehmen.
        });
    </script>
</body>
</html>