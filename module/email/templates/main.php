<div id="email-content">
    <div class="card">
        <div class="card-header">
            <h2 class="mb-0">📧 <?php echo $translations['module_title']; ?></h2>
        </div>
        <div class="card-body">
            <form onsubmit="createEmail(event)">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="email_address"><?php echo $translations['email_address']; ?></label>
                            <input type="email" class="form-control" id="email_address" name="email" required placeholder="user@example.com">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="email_login"><?php echo $translations['login_name']; ?></label>
                            <input type="text" class="form-control" id="email_login" name="login" required placeholder="user" pattern="[a-zA-Z0-9._-]+" title="Nur Buchstaben, Zahlen, Punkt, Unterstrich und Bindestrich">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="email_password"><?php echo $translations['password']; ?></label>
                            <input type="password" class="form-control" id="email_password" name="password" required minlength="6">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="email_quota"><?php echo $translations['storage_space']; ?></label>
                            <input type="number" class="form-control" id="email_quota" name="quota" value="1000" required min="100" max="10000">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="email_name"><?php echo $translations['full_name']; ?></label>
                            <input type="text" class="form-control" id="email_name" name="name" placeholder="Max Mustermann">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="email_domain"><?php echo $translations['domain']; ?></label>
                            <input type="text" class="form-control" id="email_domain" name="domain" required placeholder="example.com">
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <span class="loading hidden"></span>
                    <?php echo $translations['create_email']; ?>
                </button>
            </form>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">📱 <?php echo $translations['email_client_config']; ?></h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h4><?php echo $translations['imap_receive']; ?></h4>
                            <ul class="list-unstyled">
                                <li><strong><?php echo $translations['server']; ?>:</strong> mail.ihre-domain.de</li>
                                <li><strong><?php echo $translations['port']; ?>:</strong> 993 (SSL/TLS)</li>
                                <li><strong><?php echo $translations['security']; ?>:</strong> SSL/TLS</li>
                                <li><strong><?php echo $translations['username']; ?>:</strong> Ihre E-Mail-Adresse</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h4><?php echo $translations['smtp_send']; ?></h4>
                            <ul class="list-unstyled">
                                <li><strong><?php echo $translations['server']; ?>:</strong> mail.ihre-domain.de</li>
                                <li><strong><?php echo $translations['port']; ?>:</strong> 587 (STARTTLS)</li>
                                <li><strong><?php echo $translations['security']; ?>:</strong> STARTTLS</li>
                                <li><strong><?php echo $translations['authentication']; ?>:</strong> <?php echo $translations['required']; ?></li>
                            </ul>
                        </div>
                    </div>
                    
                    <h4 class="mt-3"><?php echo $translations['alternative_ports']; ?></h4>
                    <p><strong>IMAP:</strong> 143 (STARTTLS) | <strong>POP3:</strong> 995 (SSL/TLS), 110 (STARTTLS)</p>
                    <p><strong>SMTP:</strong> 465 (SSL/TLS), 25 (unverschlüsselt - nicht empfohlen)</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">🌐 <?php echo $translations['webmail_access']; ?></h3>
                </div>
                <div class="card-body">
                    <p><?php echo $translations['webmail_description']; ?></p>
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-secondary" onclick="openWebmail('roundcube')">
                            📮 <?php echo $translations['roundcube_webmail']; ?>
                        </button>
                        <button class="btn btn-outline-secondary" onclick="openWebmail('horde')">
                            📧 <?php echo $translations['horde_webmail']; ?>
                        </button>
                        <button class="btn btn-outline-secondary" onclick="generateEmailPassword()">
                            🔐 <?php echo $translations['generate_secure_password']; ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">⚙️ <?php echo $translations['advanced_email_functions']; ?></h3>
                </div>
                <div class="card-body">
                    <ul class="text-muted">
                        <li><?php echo $translations['autoresponder']; ?></li>
                        <li><?php echo $translations['email_forwarding']; ?></li>
                        <li><?php echo $translations['spam_filter_settings']; ?></li>
                        <li><?php echo $translations['email_aliases']; ?></li>
                        <li><?php echo $translations['catch_all_addresses']; ?></li>
                    </ul>
                    <p class="text-muted fst-italic">
                        <?php echo $translations['ispconfig_note']; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Email Module JavaScript
window.emailModule = {
    init: function() {
        console.log('Email module initialized');
        this.loadTranslations();
    },
    
    translations: {},
    
    loadTranslations: function() {
        // Lade Übersetzungen vom Server mit neuem Format
        ModuleManager.makeRequest('email', 'get_translations')
            .then(data => {
                if (data.success) {
                    this.translations = data.translations;
                    console.log('Email translations loaded:', this.translations);
                } else {
                    console.error('Failed to load translations:', data.error);
                }
            })
            .catch(error => console.error('Error loading translations:', error));
    },
    
    t: function(key, params = {}) {
        let text = this.translations[key] || key;
        
        // Parameter ersetzen
        Object.keys(params).forEach(param => {
            text = text.replace(`{${param}}`, params[param]);
        });
        
        return text;
    },
    
    openWebmail: function(type) {
        const domain = document.getElementById('email_domain').value || 'ihre-domain.de';
        const urls = {
            'roundcube': `https://${domain}/webmail`,
            'horde': `https://${domain}/horde`
        };
        
        if (domain === 'ihre-domain.de') {
            showNotification(this.t('please_enter_domain'), 'warning');
            return;
        }
        
        showNotification(this.t('webmail_url') + ': ' + urls[type], 'info');
        window.open(urls[type], '_blank');
    },
    
    generatePassword: function() {
        const length = 12;
        const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*";
        let password = "";
        
        // Ensure at least one of each type
        password += "ABCDEFGHIJKLMNOPQRSTUVWXYZ"[Math.floor(Math.random() * 26)];
        password += "abcdefghijklmnopqrstuvwxyz"[Math.floor(Math.random() * 26)];
        password += "0123456789"[Math.floor(Math.random() * 10)];
        password += "!@#$%^&*"[Math.floor(Math.random() * 8)];
        
        // Fill the rest
        for (let i = 4; i < length; i++) {
            password += charset.charAt(Math.floor(Math.random() * charset.length));
        }
        
        // Shuffle
        password = password.split('').sort(() => 0.5 - Math.random()).join('');
        
        document.getElementById('email_password').value = password;
        showNotification(this.t('secure_password_generated'), 'success');
    }
};

// Global functions
function openWebmail(type) {
    emailModule.openWebmail(type);
}

function generateEmailPassword() {
    emailModule.generatePassword();
}

// Form Handler
async function createEmail(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    setLoading(form, true);
    
    try {
        const result = await ModuleManager.makeRequest('email', 'create_email', formData);
        
        if (result.success) {
            showNotification(emailModule.t('email_created_message'), 'success');
            
            // Zeige Konfigurationsdaten
            const email = formData.get('email');
            const domain = formData.get('domain');
            
            const configInfo = emailModule.t('email_config_alert', {
                email: email,
                domain: domain
            });
            
            alert(configInfo);
            
            form.reset();
        } else {
            showNotification('Fehler: ' + (result.error || emailModule.t('unknown_error')), 'error');
        }
    } catch (error) {
        showNotification(emailModule.t('network_error') + ': ' + error.message, 'error');
    }
    
    setLoading(form, false);
}

// Automatische Initialisierung beim Laden
document.addEventListener('DOMContentLoaded', function() {
    if (window.emailModule) {
        window.emailModule.init();
    }
});

// Fallback: Initialisierung nach kurzer Verzögerung
setTimeout(function() {
    if (window.emailModule && !window.emailModule.translations || Object.keys(window.emailModule.translations).length === 0) {
        console.log('Auto-initializing email module');
        window.emailModule.init();
    }
}, 100);
</script>