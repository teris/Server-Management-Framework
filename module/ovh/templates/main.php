<div id="ovh-content">
    <div class="card">
        <div class="card-header">
            <h2 class="mb-0">🔗 <?php echo $translations['order_domain_ovh']; ?></h2>
        </div>
        <div class="card-body">
            <form onsubmit="orderDomain(event)">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="domain_name"><?php echo $translations['domain_name']; ?></label>
                            <input type="text" class="form-control" id="domain_name" name="domain" required placeholder="example.com">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="domain_duration"><?php echo $translations['duration']; ?></label>
                            <select class="form-control" id="domain_duration" name="duration">
                                <option value="1" selected>1 Jahr</option>
                                <option value="2">2 Jahre</option>
                                <option value="3">3 Jahre</option>
                                <option value="5">5 Jahre</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <span class="loading hidden"></span>
                    <?php echo $translations['order_domain']; ?>
                </button>
            </form>

    
    <hr>
    <div class="row">
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
    
    <hr>
    
    <!-- DNS Management -->
    <div class="endpoint-section col-md-6">
        <h3>🌐 DNS Management</h3>
        
        <h4>DNS Record erstellen</h4>
        <form onsubmit="createDNSRecord(event)">
            <div class="form-row">
                <div class="form-group">
                    <label for="dns_domain">Domain</label>
                    <input type="text" id="dns_domain" name="domain" required placeholder="example.com">
                </div>
                <div class="form-group">
                    <label for="dns_type">Record Type</label>
                    <select id="dns_type" name="type" required>
                        <option value="A">A</option>
                        <option value="AAAA">AAAA</option>
                        <option value="CNAME">CNAME</option>
                        <option value="MX">MX</option>
                        <option value="TXT">TXT</option>
                        <option value="SRV">SRV</option>
                        <option value="NS">NS</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="dns_subdomain">Subdomain</label>
                    <input type="text" id="dns_subdomain" name="subdomain" placeholder="www">
                </div>
                <div class="form-group">
                    <label for="dns_target">Target</label>
                    <input type="text" id="dns_target" name="target" required placeholder="192.168.1.100">
                </div>
            </div>
            
            <div class="form-group">
                <label for="dns_ttl">TTL (Sekunden)</label>
                <input type="number" id="dns_ttl" name="ttl" value="3600" min="60" max="604800">
            </div>
            
            <button type="submit" class="btn btn-secondary">
                <span class="loading hidden"></span>
                DNS Record erstellen
            </button>
        </form>
    </div>
    
    <!-- VPS Control -->
    <div class="endpoint-section col-md-6">
        <h3>🖥️ VPS Control</h3>
        <form onsubmit="controlVPS(event)">
            <div class="form-row">
                <div class="form-group">
                    <label for="control_vps_name">VPS Name</label>
                    <input type="text" id="control_vps_name" name="vps_name" required placeholder="vpsXXXXX.ovh.net">
                </div>
                <div class="form-group">
                    <label for="control_vps_action">Aktion</label>
                    <select id="control_vps_action" name="vps_action" required>
                        <option value="reboot">Reboot</option>
                        <option value="start">Start</option>
                        <option value="stop">Stop</option>
                        <option value="reset">Reset</option>
                    </select>
                </div>
            </div>
            
            <button type="submit" class="btn btn-warning">
                <span class="loading hidden"></span>
                VPS Aktion ausführen
            </button>
        </form>
    </div>
    
    <!-- Failover IPs -->
    <div class="endpoint-section col-md-12">
        <h3>🌐 Failover IPs</h3>
        <button class="btn btn-secondary" onclick="loadFailoverIPs()">
            📋 Failover IPs laden
        </button>
        
        <div class="table-container" style="margin-top: 20px;">
            <table class="data-table" id="failover-ips-table">
                <thead>
                    <tr>
                        <th>IP</th>
                        <th>Block</th>
                        <th>Geroutet zu</th>
                        <th>Typ</th>
                        <th>Land</th>
                        <th>Virtual MAC</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody id="failover-ips-tbody">
                    <tr><td colspan="7" style="text-align: center;">Klicken Sie auf "Failover IPs laden"</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    </div>
    <!-- Quick Actions -->
    <div class="endpoint-section col-md-12  ">
        <h3>⚡ Schnellaktionen</h3>
        <div class="endpoint-buttons">
            <button class="btn btn-secondary" onclick="checkDomainAvailability()">
                🔍 Domain Verfügbarkeit
            </button>
            <button class="btn btn-secondary" onclick="showDNSRecords()">
                📝 DNS Records anzeigen
            </button>
            <button class="btn btn-secondary" onclick="refreshDNSZone()">
                🔄 DNS Zone refresh
            </button>
        </div>
    </div>
</div>  
</div>
</div>
<script>
// OVH Module JavaScript
window.ovhModule = {
    init: function() {
        console.log('OVH module initialized');
    },
    
    loadFailoverIPs: async function() {
        try {
            const result = await ModuleManager.makeRequest('ovh', 'get_ovh_failover_ips');
            
            if (result.success) {
                this.displayFailoverIPs(result.data);
                showNotification('Failover IPs geladen', 'success');
            } else {
                showNotification('Fehler: ' + result.error, 'error');
            }
        } catch (error) {
            showNotification('Netzwerkfehler', 'error');
        }
    },
    
    displayFailoverIPs: function(ips) {
        const tbody = document.getElementById('failover-ips-tbody');
        
        if (!ips || ips.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align: center;">Keine Failover IPs gefunden.</td></tr>';
            return;
        }
        
        tbody.innerHTML = ips.map(ip => `
            <tr>
                <td>${ip.ip || 'N/A'}</td>
                <td>${ip.block || 'N/A'}</td>
                <td>${ip.routedTo ? (ip.routedTo.serviceName || ip.routedTo) : 'N/A'}</td>
                <td>${ip.type || 'N/A'}</td>
                <td>${ip.country || ip.geo || 'N/A'}</td>
                <td>${ip.virtualMac || 'Nicht vorhanden'}</td>
                <td class="action-buttons">
                    ${!ip.virtualMac ? `<button class="btn btn-secondary btn-small" onclick="generateMacAddress('${ip.ip}')">MAC erzeugen</button>` : ''}
                </td>
            </tr>
        `).join('');
    },
    
    checkDomainAvailability: async function() {
        const domain = prompt('Domain eingeben:');
        if (!domain) return;
        
        showNotification('Prüfe Verfügbarkeit...', 'info');
        
        // Hier würde die API-Anfrage kommen
        showNotification('Domain-Verfügbarkeitsprüfung noch nicht implementiert', 'info');
    },
    
    showDNSRecords: async function() {
        const domain = prompt('Domain eingeben:');
        if (!domain) return;
        
        try {
            const result = await ModuleManager.makeRequest('ovh', 'get_ovh_dns_records', { domain: domain });
            
            if (result.success) {
                console.log('DNS Records:', result.data);
                showNotification('DNS Records geladen - siehe Konsole', 'success');
            } else {
                showNotification('Fehler: ' + result.error, 'error');
            }
        } catch (error) {
            showNotification('Netzwerkfehler', 'error');
        }
    },
    
    refreshDNSZone: async function() {
        const domain = prompt('Domain eingeben:');
        if (!domain) return;
        
        try {
            const result = await ModuleManager.makeRequest('ovh', 'refresh_dns_zone', { domain: domain });
            
            if (result.success) {
                showNotification('DNS Zone erfolgreich aktualisiert', 'success');
            } else {
                showNotification('Fehler: ' + result.error, 'error');
            }
        } catch (error) {
            showNotification('Netzwerkfehler', 'error');
        }
    }
};

// Global functions
function loadFailoverIPs() {
    ovhModule.loadFailoverIPs();
}

function checkDomainAvailability() {
    ovhModule.checkDomainAvailability();
}

function showDNSRecords() {
    ovhModule.showDNSRecords();
}

function refreshDNSZone() {
    ovhModule.refreshDNSZone();
}

async function generateMacAddress(ipAddress) {
    if (!confirm(`Virtual MAC für IP ${ipAddress} erzeugen?`)) {
        return;
    }
    
    try {
        const result = await ModuleManager.makeRequest('ovh', 'create_ovh_virtual_mac', {
            ip: ipAddress,
            type: 'ovh'
        });
        
        if (result.success) {
            showNotification('Virtual MAC erfolgreich erzeugt', 'success');
            loadFailoverIPs();
        } else {
            showNotification('Fehler: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler', 'error');
    }
}

// Form Handlers
async function orderDomain(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    setLoading(form, true);
    
    try {
        const result = await ModuleManager.makeRequest('ovh', 'order_domain', formData);
        
        if (result.success) {
            showNotification('Domain wurde erfolgreich bestellt!', 'success');
            form.reset();
        } else {
            showNotification('Fehler: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
    
    setLoading(form, false);
}

async function getVPSInfo(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    setLoading(form, true);
    
    try {
        const result = await ModuleManager.makeRequest('ovh', 'get_vps_info', formData);
        
        if (result.success && result.data) {
            document.getElementById('vps_ip').textContent = result.data.ip;
            document.getElementById('vps_mac').textContent = result.data.mac;
            document.getElementById('vps_result').classList.remove('hidden');
            showNotification('VPS Informationen erfolgreich abgerufen!', 'success');
        } else {
            showNotification('Fehler: ' + (result.error || 'Keine Daten gefunden'), 'error');
            document.getElementById('vps_result').classList.add('hidden');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
    
    setLoading(form, false);
}

async function createDNSRecord(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    setLoading(form, true);
    
    try {
        const result = await ModuleManager.makeRequest('ovh', 'create_dns_record', formData);
        
        if (result.success) {
            showNotification('DNS Record erfolgreich erstellt!', 'success');
            form.reset();
        } else {
            showNotification('Fehler: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
    
    setLoading(form, false);
}

async function controlVPS(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    const action = formData.get('vps_action');
    if (!confirm(`VPS ${action} wirklich ausführen?`)) {
        return;
    }
    
    setLoading(form, true);
    
    try {
        const result = await ModuleManager.makeRequest('ovh', 'control_ovh_vps', formData);
        
        if (result.success) {
            showNotification(`VPS ${action} erfolgreich ausgeführt!`, 'success');
        } else {
            showNotification('Fehler: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
    
    setLoading(form, false);
}
</script>