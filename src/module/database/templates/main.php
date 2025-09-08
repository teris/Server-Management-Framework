<div id="database-content">
    <div class="card">
        <div class="card-header">
            <h2 class="mb-0">🗄️ <?= t('module_title') ?></h2>
        </div>
        <div class="card-body">
            <form onsubmit="createDatabase(event)">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="db_name"><?= t('database_name') ?></label>
                            <input type="text" class="form-control" id="db_name" name="name" required placeholder="my_database" pattern="[a-zA-Z0-9_]+" title="Nur Buchstaben, Zahlen und Unterstriche">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="db_user"><?= t('database_user') ?></label>
                            <input type="text" class="form-control" id="db_user" name="user" required placeholder="db_user" pattern="[a-zA-Z0-9_]+" title="Nur Buchstaben, Zahlen und Unterstriche">
                        </div>
                    </div>
                </div>
                
                <div class="form-group mb-3">
                    <label for="db_password"><?= t('password') ?></label>
                    <input type="password" class="form-control" id="db_password" name="password" required minlength="6">
                    <small class="form-text text-muted"><?= t('password_min_length') ?></small>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <span class="loading hidden"></span>
                    <?= t('create_database') ?>
                </button>
            </form>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">💡 <?= t('connection_info') ?></h3>
                </div>
                <div class="card-body">
                    <h4><?= t('connection_details') ?></h4>
                    <ul class="list-unstyled">
                        <li><strong><?= t('host') ?>:</strong> <?= t('host_info') ?></li>
                        <li><strong><?= t('port') ?>:</strong> <?= t('port_info') ?></li>
                        <li><strong><?= t('database_name') ?>:</strong> Der von Ihnen gewählte Name</li>
                        <li><strong><?= t('database_user') ?>:</strong> Der von Ihnen gewählte Benutzername</li>
                        <li><strong><?= t('password') ?>:</strong> Das von Ihnen gewählte Passwort</li>
                    </ul>
                    
                    <h4>phpMyAdmin</h4>
                    <p><?= t('phpmyadmin_info') ?></p>
                    <p><code><?= t('phpmyadmin_url') ?></code></p>
                    
                    <h4><?= t('charset') ?></h4>
                    <p><?= t('charset_info') ?></p>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">🔧 <?= t('advanced_options') ?></h3>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-secondary" onclick="showDatabaseInfo()">
                                📊 <?= t('database_server_info') ?>
                        </button>
                        <button class="btn btn-outline-secondary" onclick="generatePassword()">
                            🔐 <?= t('generate_secure_password') ?>
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
    },
    
    t: function(key, params = {}) {
        // Übersetzungen werden jetzt über die globale t() Funktion geladen
        // Diese Funktion ist nur noch für JavaScript-spezifische Übersetzungen
        let text = key; // Fallback auf Schlüssel
        
        // Parameter ersetzen
        Object.keys(params).forEach(param => {
            text = text.replace(`{${param}}`, params[param]);
        });
        
        return text;
    },
    
    showDatabaseInfo: function() {
        showNotification('MySQL/MariaDB Server läuft auf Port 3306', 'info');
    },
    
    generatePassword: function() {
        const length = 16;
        const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*";
        let password = "";
        
        for (let i = 0; i < length; i++) {
            password += charset.charAt(Math.floor(Math.random() * charset.length));
        }
        
        document.getElementById('db_password').value = password;
        showNotification('Sicheres Passwort generiert', 'success');
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
            showNotification('Datenbank wurde erfolgreich erstellt!', 'success');
            
            // Zeige Verbindungsdaten
            const dbName = formData.get('name');
            const dbUser = formData.get('user');
            
            const alertMessage = `Datenbank erfolgreich erstellt!\n\nVerbindungsdaten:\nHost: localhost\nDatenbank: ${dbName}\nBenutzer: ${dbUser}\nPasswort: [Ihr gewähltes Passwort]`;
            
            alert(alertMessage);
            
            form.reset();
        } else {
            showNotification('Fehler: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
    
    setLoading(form, false);
}

// Automatische Initialisierung beim Laden
document.addEventListener('DOMContentLoaded', function() {
    if (window.databaseModule) {
        window.databaseModule.init();
    }
});
</script>