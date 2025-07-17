<div id="virtual-mac-content">
    <div class="card">
        <div class="card-header">
            <h2 class="mb-0">🔌 <?php echo $translations['virtual_mac_management']; ?></h2>
        </div>
        <div class="card-body">
            <div class="tabs" style="margin-bottom: 20px;">
                <button class="tab active" onclick="showVirtualMacTab('overview', this)">📊 <?php echo $translations['overview']; ?></button>
                <button class="tab" onclick="showVirtualMacTab('create', this)">➕ <?php echo $translations['create']; ?></button>
                <button class="tab" onclick="showVirtualMacTab('ip-management', this)">🌐 <?php echo $translations['ip_management']; ?></button>
                <button class="tab" onclick="showVirtualMacTab('reverse-dns', this)">🔄 <?php echo $translations['reverse_dns']; ?></button>
            </div>
        </div>
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
                        <?php foreach ($servers as $server): ?>
                        <option value="<?= htmlspecialchars($server) ?>"><?= htmlspecialchars($server) ?></option>
                        <?php endforeach; ?>
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
                        <?php foreach ($servers as $server): ?>
                        <option value="<?= htmlspecialchars($server) ?>"><?= htmlspecialchars($server) ?></option>
                        <?php endforeach; ?>
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
                        <?php foreach ($servers as $server): ?>
                        <option value="<?= htmlspecialchars($server) ?>"><?= htmlspecialchars($server) ?></option>
                        <?php endforeach; ?>
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

<script>
// Virtual MAC Module JavaScript
window.virtualMacModule = {
    init: function() {
        console.log('Virtual MAC module initialized');
        this.loadOverview();
    },
    
    loadOverview: async function() {
        try {
            const result = await ModuleManager.makeRequest('virtual-mac', 'load_virtual_mac_overview');
            
            if (result.success && result.data) {
                // Update stats
                document.getElementById('total-virtual-macs').textContent = result.data.stats.total_macs;
                document.getElementById('total-assigned-ips').textContent = result.data.stats.total_ips;
                document.getElementById('total-dedicated-servers').textContent = result.data.stats.total_servers;
                
                // Display MACs
                this.displayVirtualMacs(result.data.macs);
            }
        } catch (error) {
            console.error('Error loading overview:', error);
        }
    },
    
    displayVirtualMacs: function(macs) {
        const tbody = document.getElementById('virtual-mac-overview-tbody');
        
        if (!macs || macs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align: center;">Keine Virtual MACs gefunden.</td></tr>';
            return;
        }
        
        tbody.innerHTML = macs.map(mac => {
            const ips = mac.ipAddresses || [];
            const firstIP = ips[0] || {};
            
            return `
                <tr>
                    <td>${mac.macAddress || 'N/A'}</td>
                    <td>${firstIP.virtualMachineName || 'N/A'}</td>
                    <td>${firstIP.ipAddress || 'N/A'}</td>
                    <td>${mac.service_name || 'N/A'}</td>
                    <td>${mac.type || 'N/A'}</td>
                    <td>${mac.createdAt || 'N/A'}</td>
                    <td class="action-buttons">
                        <button class="btn btn-danger btn-small" onclick="deleteVirtualMac('${mac.service_name}', '${mac.macAddress}')">
                            🗑️ Löschen
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    },
    
    loadMacsForService: async function(serviceName) {
        if (!serviceName) {
            document.getElementById('vmac_ip_mac_address').innerHTML = '<option value="">Erst Service auswählen...</option>';
            return;
        }
        
        try {
            const result = await ModuleManager.makeRequest('virtual-mac', 'get_virtual_macs_for_service', {
                service_name: serviceName
            });
            
            if (result.success) {
                const select = document.getElementById('vmac_ip_mac_address');
                select.innerHTML = '<option value="">MAC auswählen...</option>';
                
                result.data.forEach(mac => {
                    select.innerHTML += `<option value="${mac}">${mac}</option>`;
                });
            }
        } catch (error) {
            showNotification('Fehler beim Laden der MACs', 'error');
        }
    }
};

// Tab Management
function showVirtualMacTab(tabName, element) {
    document.querySelectorAll('.virtual-mac-tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    element.parentNode.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    document.getElementById('virtual-mac-' + tabName).classList.remove('hidden');
    element.classList.add('active');
}

// Global functions
function loadVirtualMacOverview() {
    virtualMacModule.loadOverview();
}

function loadVirtualMacsForService(serviceName) {
    virtualMacModule.loadMacsForService(serviceName);
}

async function deleteVirtualMac(serviceName, macAddress) {
    if (!confirm('Möchten Sie diese Virtual MAC wirklich löschen?')) {
        return;
    }
    
    try {
        const result = await ModuleManager.makeRequest('virtual-mac', 'delete_virtual_mac', {
            service_name: serviceName,
            mac_address: macAddress
        });
        
        if (result.success) {
            showNotification('Virtual MAC erfolgreich gelöscht', 'success');
            loadVirtualMacOverview();
        } else {
            showNotification('Fehler: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler', 'error');
    }
}

// Form Handlers
async function createVirtualMac(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    setLoading(form, true);
    
    try {
        const result = await ModuleManager.makeRequest('virtual-mac', 'create_virtual_mac', formData);
        
        if (result.success) {
            showNotification('Virtual MAC erfolgreich erstellt!', 'success');
            form.reset();
            loadVirtualMacOverview();
        } else {
            showNotification('Fehler: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
    
    setLoading(form, false);
}

async function assignIPToVirtualMac(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    setLoading(form, true);
    
    try {
        const result = await ModuleManager.makeRequest('virtual-mac', 'assign_ip_to_virtual_mac', formData);
        
        if (result.success) {
            showNotification('IP-Adresse erfolgreich zugewiesen!', 'success');
            form.reset();
            loadVirtualMacOverview();
        } else {
            showNotification('Fehler: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
    
    setLoading(form, false);
}

async function removeIPFromVirtualMac(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    setLoading(form, true);
    
    try {
        const result = await ModuleManager.makeRequest('virtual-mac', 'remove_ip_from_virtual_mac', formData);
        
        if (result.success) {
            showNotification('IP-Adresse erfolgreich entfernt!', 'success');
            form.reset();
            loadVirtualMacOverview();
        } else {
            showNotification('Fehler: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
    
    setLoading(form, false);
}

async function createReverseDNS(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    setLoading(form, true);
    
    try {
        const result = await ModuleManager.makeRequest('virtual-mac', 'create_reverse_dns', formData);
        
        if (result.success) {
            showNotification('Reverse DNS erfolgreich erstellt!', 'success');
            form.reset();
        } else {
            showNotification('Fehler: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
    
    setLoading(form, false);
}

async function queryReverseDNS(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    setLoading(form, true);
    
    try {
        const result = await ModuleManager.makeRequest('virtual-mac', 'query_reverse_dns', formData);
        
        if (result.success) {
            document.getElementById('reverse_dns_result').classList.remove('hidden');
            document.getElementById('reverse_dns_content').textContent = JSON.stringify(result.data, null, 2);
            showNotification('Reverse DNS abgefragt!', 'success');
        } else {
            showNotification('Fehler: ' + (result.error || 'Unbekannter Fehler'), 'error');
            document.getElementById('reverse_dns_result').classList.add('hidden');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
    
    setLoading(form, false);
}
</script>