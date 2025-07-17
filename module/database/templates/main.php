<div id="database-content">
    <div class="card">
        <div class="card-header">
            <h2 class="mb-0">🗄️ <?php echo $translations['module_title']; ?></h2>
        </div>
        <div class="card-body">
            <form onsubmit="createDatabase(event)">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="db_name"><?php echo $translations['database_name']; ?></label>
                            <input type="text" class="form-control" id="db_name" name="name" required placeholder="my_database" pattern="[a-zA-Z0-9_]+" title="Nur Buchstaben, Zahlen und Unterstriche">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="db_user"><?php echo $translations['database_user']; ?></label>
                            <input type="text" class="form-control" id="db_user" name="user" required placeholder="db_user" pattern="[a-zA-Z0-9_]+" title="Nur Buchstaben, Zahlen und Unterstriche">
                        </div>
                    </div>
                </div>
                
                <div class="form-group mb-3">
                    <label for="db_password"><?php echo $translations['password']; ?></label>
                    <input type="password" class="form-control" id="db_password" name="password" required minlength="6">
                    <small class="form-text text-muted"><?php echo $translations['password_min_length']; ?></small>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <span class="loading hidden"></span>
                    <?php echo $translations['create_database']; ?>
                </button>
            </form>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">💡 <?php echo $translations['connection_info']; ?></h3>
                </div>
                <div class="card-body">
                    <h4><?php echo $translations['connection_details']; ?></h4>
                    <ul class="list-unstyled">
                        <li><strong><?php echo $translations['host']; ?>:</strong> <?php echo $translations['host_info']; ?></li>
                        <li><strong><?php echo $translations['port']; ?>:</strong> <?php echo $translations['port_info']; ?></li>
                        <li><strong><?php echo $translations['database_name']; ?>:</strong> Der von Ihnen gewählte Name</li>
                        <li><strong><?php echo $translations['database_user']; ?>:</strong> Der von Ihnen gewählte Benutzername</li>
                        <li><strong><?php echo $translations['password']; ?>:</strong> Das von Ihnen gewählte Passwort</li>
                    </ul>
                    
                    <h4>phpMyAdmin</h4>
                    <p><?php echo $translations['phpmyadmin_info']; ?></p>
                    <p><code><?php echo $translations['phpmyadmin_url']; ?></code></p>
                    
                    <h4><?php echo $translations['charset']; ?></h4>
                    <p><?php echo $translations['charset_info']; ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">🔧 <?php echo $translations['advanced_options']; ?></h3>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-secondary" onclick="showDatabaseInfo()">
                            📊 <?php echo $translations['database_server_info']; ?>
                        </button>
                        <button class="btn btn-outline-secondary" onclick="generatePassword()">
                            🔐 <?php echo $translations['generate_secure_password']; ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
</div>

<script>
// Database Module JavaScript
window.databaseModule = {
    init: function() {
        console.log('Database module initialized');
        this.loadTranslations();
    },
    
    translations: {},
    
    loadTranslations: function() {
        // Lade Übersetzungen vom Server mit neuem Format
        ModuleManager.makeRequest('database', 'get_translations')
            .then(data => {
                if (data.success) {
                    this.translations = data.translations;
                    console.log('Database translations loaded:', this.translations);
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
    
    showDatabaseInfo: function() {
        showNotification(this.t('database_info_message'), 'info');
    },
    
    generatePassword: function() {
        const length = 16;
        const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*";
        let password = "";
        
        for (let i = 0; i < length; i++) {
            password += charset.charAt(Math.floor(Math.random() * charset.length));
        }
        
        document.getElementById('db_password').value = password;
        showNotification(this.t('secure_password_generated'), 'success');
    }
};

// Global functions
function showDatabaseInfo() {
    databaseModule.showDatabaseInfo();
}

function generatePassword() {
    databaseModule.generatePassword();
}

// Form Handler
async function createDatabase(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    setLoading(form, true);
    
    try {
        const result = await ModuleManager.makeRequest('database', 'create_database', formData);
        
        if (result.success) {
            showNotification(databaseModule.t('database_created_message'), 'success');
            
            // Zeige Verbindungsdaten
            const dbName = formData.get('name');
            const dbUser = formData.get('user');
            
            const alertMessage = databaseModule.t('database_connection_alert', {
                dbName: dbName,
                dbUser: dbUser
            });
            
            alert(alertMessage);
            
            form.reset();
        } else {
            showNotification('Fehler: ' + (result.error || databaseModule.t('unknown_error')), 'error');
        }
    } catch (error) {
        showNotification(databaseModule.t('network_error') + ': ' + error.message, 'error');
    }
    
    setLoading(form, false);
}

// Automatische Initialisierung beim Laden
document.addEventListener('DOMContentLoaded', function() {
    if (window.databaseModule) {
        window.databaseModule.init();
    }
});

// Fallback: Initialisierung nach kurzer Verzögerung
setTimeout(function() {
    if (window.databaseModule && !window.databaseModule.translations || Object.keys(window.databaseModule.translations).length === 0) {
        console.log('Auto-initializing database module');
        window.databaseModule.init();
    }
}, 100);
</script>