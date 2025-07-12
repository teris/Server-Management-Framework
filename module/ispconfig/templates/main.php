<div id="ispconfig" class="tab-content">
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
    
    <hr>
    
    <!-- FTP User erstellen -->
    <div class="endpoint-section">
        <h3>👤 FTP-Benutzer erstellen</h3>
        <form onsubmit="createFTPUser(event)">
            <div class="form-row">
                <div class="form-group">
                    <label for="ftp_domain_id">Domain ID</label>
                    <input type="number" id="ftp_domain_id" name="domain_id" required placeholder="1">
                </div>
                <div class="form-group">
                    <label for="ftp_username">FTP Username</label>
                    <input type="text" id="ftp_username" name="username" required placeholder="ftp_user1">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="ftp_password">Passwort</label>
                    <input type="password" id="ftp_password" name="password" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="ftp_quota">Quota (MB)</label>
                    <input type="number" id="ftp_quota" name="quota" value="500" required min="0">
                </div>
            </div>
            
            <button type="submit" class="btn btn-secondary">
                <span class="loading hidden"></span>
                FTP-Benutzer erstellen
            </button>
        </form>
    </div>
    
    <!-- Subdomain erstellen -->
    <div class="endpoint-section">
        <h3>🌐 Subdomain erstellen</h3>
        <form onsubmit="createSubdomain(event)">
            <div class="form-row">
                <div class="form-group">
                    <label for="sub_parent_id">Parent Domain ID</label>
                    <input type="number" id="sub_parent_id" name="parent_domain_id" required placeholder="1">
                </div>
                <div class="form-group">
                    <label for="sub_name">Subdomain</label>
                    <input type="text" id="sub_name" name="subdomain" required placeholder="blog">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="sub_redirect_type">Redirect Type</label>
                    <select id="sub_redirect_type" name="redirect_type">
                        <option value="">Kein Redirect</option>
                        <option value="R">R (Temporary)</option>
                        <option value="L">L (Permanent)</option>
                        <option value="R,L">R,L</option>
                        <option value="R=301,L">R=301,L</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="sub_redirect_path">Redirect Path (optional)</label>
                    <input type="text" id="sub_redirect_path" name="redirect_path" placeholder="https://example.com">
                </div>
            </div>
            
            <button type="submit" class="btn btn-secondary">
                <span class="loading hidden"></span>
                Subdomain erstellen
            </button>
        </form>
    </div>
    
    <!-- Quick Actions -->
    <div class="endpoint-section">
        <h3>⚡ Schnellaktionen</h3>
        <div class="endpoint-buttons">
            <button class="btn btn-secondary" onclick="loadISPConfigClients()">
                👥 Clients laden
            </button>
            <button class="btn btn-secondary" onclick="loadServerConfig()">
                ⚙️ Server Config
            </button>
            <button class="btn btn-secondary" onclick="showWebsiteDetails()">
                📊 Website Details
            </button>
        </div>
    </div>
</div>

<script>
// ISPConfig Module JavaScript
window.ispconfigModule = {
    init: function() {
        console.log('ISPConfig module initialized');
    },
    
    loadClients: async function() {
        try {
            const result = await ModuleManager.makeRequest('ispconfig', 'get_ispconfig_clients');
            if (result.success) {
                console.log('ISPConfig Clients:', result.data);
                showNotification('Clients geladen - siehe Konsole', 'success');
            } else {
                showNotification('Fehler: ' + result.error, 'error');
            }
        } catch (error) {
            showNotification('Netzwerkfehler', 'error');
        }
    },
    
    loadServerConfig: async function() {
        try {
            const result = await ModuleManager.makeRequest('ispconfig', 'get_ispconfig_server_config');
            if (result.success) {
                console.log('Server Config:', result.data);
                showNotification('Server Config geladen - siehe Konsole', 'success');
            } else {
                showNotification('Fehler: ' + result.error, 'error');
            }
        } catch (error) {
            showNotification('Netzwerkfehler', 'error');
        }
    },
    
    showWebsiteDetails: async function() {
        const domainId = prompt('Bitte Domain ID eingeben:');
        if (!domainId) return;
        
        try {
            const result = await ModuleManager.makeRequest('ispconfig', 'get_website_details', {
                domain_id: domainId
            });
            if (result.success) {
                console.log('Website Details:', result.data);
                showNotification('Website Details geladen - siehe Konsole', 'success');
            } else {
                showNotification('Fehler: ' + result.error, 'error');
            }
        } catch (error) {
            showNotification('Netzwerkfehler', 'error');
        }
    }
};

// Global functions für Kompatibilität
function loadISPConfigClients() {
    ispconfigModule.loadClients();
}

function loadServerConfig() {
    ispconfigModule.loadServerConfig();
}

function showWebsiteDetails() {
    ispconfigModule.showWebsiteDetails();
}

// Form Handlers
async function createWebsite(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    setLoading(form, true);
    
    try {
        const result = await ModuleManager.makeRequest('ispconfig', 'create_website', formData);
        
        if (result.success) {
            showNotification('Website wurde erfolgreich erstellt!', 'success');
            form.reset();
        } else {
            showNotification('Fehler: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
    
    setLoading(form, false);
}

async function createFTPUser(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    setLoading(form, true);
    
    try {
        const result = await ModuleManager.makeRequest('ispconfig', 'create_ftp_user', formData);
        
        if (result.success) {
            showNotification('FTP-Benutzer wurde erfolgreich erstellt!', 'success');
            form.reset();
        } else {
            showNotification('Fehler: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
    
    setLoading(form, false);
}

async function createSubdomain(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    setLoading(form, true);
    
    try {
        const result = await ModuleManager.makeRequest('ispconfig', 'create_subdomain', formData);
        
        if (result.success) {
            showNotification('Subdomain wurde erfolgreich erstellt!', 'success');
            form.reset();
        } else {
            showNotification('Fehler: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
    
    setLoading(form, false);
}
</script>