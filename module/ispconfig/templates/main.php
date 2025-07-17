<div id="ispconfig-content">
    <div class="card">
        <div class="card-header">
            <h2 class="mb-0">🌐 <?php echo $translations['create_website_ispconfig']; ?></h2>
        </div>
        <div class="card-body">
            <form onsubmit="createWebsite(event)">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="website_domain"><?php echo $translations['domain']; ?></label>
                            <input type="text" class="form-control" id="website_domain" name="domain" required placeholder="example.com">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="website_ip"><?php echo $translations['ip_address']; ?></label>
                            <input type="text" class="form-control" id="website_ip" name="ip" required placeholder="192.168.1.100">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="website_user"><?php echo $translations['system_user']; ?></label>
                            <input type="text" class="form-control" id="website_user" name="user" required placeholder="web1">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="website_group"><?php echo $translations['system_group']; ?></label>
                            <input type="text" class="form-control" id="website_group" name="group" required placeholder="client1">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="website_quota"><?php echo $translations['hd_quota']; ?></label>
                            <input type="number" class="form-control" id="website_quota" name="quota" value="1000" required min="100">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="website_traffic"><?php echo $translations['traffic_quota']; ?></label>
                            <input type="number" class="form-control" id="website_traffic" name="traffic" value="10000" required min="1000">
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <span class="loading hidden"></span>
                    <?php echo $translations['create_website']; ?>
                </button>
            </form>
       
    
    <hr>
    
    <!-- FTP User erstellen -->
    <div class="row">
    <div class="endpoint-section col-md-6">
        <h3>👤 FTP-Benutzer erstellen</h3>
        <form onsubmit="showBootstrapConfirm('FTP-Benutzer wirklich erstellen?', createFTPUser); return false;">
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
    <div class="endpoint-section col-md-6">
        <h3>🌐 Subdomain erstellen</h3>
        <form onsubmit="showBootstrapConfirm('Subdomain wirklich erstellen?', createSubdomain); return false;">
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
    <div class="endpoint-section col-md-12">
        <h3>⚡ Schnellaktionen</h3>
        <div class="endpoint-buttons">
            <button class="btn btn-secondary" onclick="showBootstrapConfirm('Clients wirklich laden?', loadISPConfigClients)">
                👥 Clients laden
            </button>
            <button class="btn btn-secondary" onclick="showBootstrapConfirm('Server Config wirklich laden?', loadServerConfig)">
                ⚙️ Server Config
            </button>
            <button class="btn btn-secondary" onclick="showBootstrapConfirm('Website Details wirklich anzeigen?', showWebsiteDetails)">
                📊 Website Details
            </button>
        </div>
    </div>
</div>
</div>
<!-- Bootstrap Confirm Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmModalLabel">Bestätigen</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
      </div>
      <div class="modal-body" id="confirmModalBody">
        <!-- Dynamischer Text -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
        <button type="button" class="btn btn-primary" id="confirmModalOk">Bestätigen</button>
      </div>
    </div>
  </div>
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

function showBootstrapConfirm(message, onConfirm) {
    const modalBody = document.getElementById('confirmModalBody');
    modalBody.textContent = message;

    const okButton = document.getElementById('confirmModalOk');
    // Vorherige Eventlistener entfernen
    const newOkButton = okButton.cloneNode(true);
    okButton.parentNode.replaceChild(newOkButton, okButton);

    newOkButton.addEventListener('click', function() {
        const modal = bootstrap.Modal.getInstance(document.getElementById('confirmModal'));
        modal.hide();
        if (typeof onConfirm === 'function') onConfirm();
    });

    const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
    modal.show();
}
</script>