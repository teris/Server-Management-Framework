<div id="database" class="tab-content">
    <h2>🗄️ Datenbank anlegen</h2>
    <form onsubmit="createDatabase(event)">
        <div class="form-row">
            <div class="form-group">
                <label for="db_name">Datenbank Name</label>
                <input type="text" id="db_name" name="name" required placeholder="my_database" pattern="[a-zA-Z0-9_]+" title="Nur Buchstaben, Zahlen und Unterstriche">
            </div>
            <div class="form-group">
                <label for="db_user">Datenbank User</label>
                <input type="text" id="db_user" name="user" required placeholder="db_user" pattern="[a-zA-Z0-9_]+" title="Nur Buchstaben, Zahlen und Unterstriche">
            </div>
        </div>
        
        <div class="form-group">
            <label for="db_password">Passwort</label>
            <input type="password" id="db_password" name="password" required minlength="6">
            <small style="color: #666;">Mindestens 6 Zeichen. Verwenden Sie ein sicheres Passwort!</small>
        </div>
        
        <button type="submit" class="btn">
            <span class="loading hidden"></span>
            Datenbank erstellen
        </button>
    </form>
    
    <hr>
    
    <div class="endpoint-section">
        <h3>💡 Datenbank-Informationen</h3>
        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 20px;">
            <h4>Verbindungsdaten</h4>
            <p>Nach der Erstellung können Sie sich mit folgenden Daten verbinden:</p>
            <ul>
                <li><strong>Host:</strong> localhost (oder Server-IP für Remote-Zugriff)</li>
                <li><strong>Port:</strong> 3306 (MySQL/MariaDB Standard)</li>
                <li><strong>Datenbank:</strong> Der von Ihnen gewählte Name</li>
                <li><strong>Benutzer:</strong> Der von Ihnen gewählte Benutzername</li>
                <li><strong>Passwort:</strong> Das von Ihnen gewählte Passwort</li>
            </ul>
            
            <h4>phpMyAdmin</h4>
            <p>Sie können Ihre Datenbank auch über phpMyAdmin verwalten:</p>
            <p><code>https://your-server.com/phpmyadmin</code></p>
            
            <h4>Zeichensatz</h4>
            <p>Alle Datenbanken werden standardmäßig mit <code>utf8mb4</code> Zeichensatz erstellt, 
            der volle Unicode-Unterstützung bietet (inkl. Emojis).</p>
        </div>
    </div>
    
    <div class="endpoint-section">
        <h3>🔧 Erweiterte Optionen</h3>
        <div class="endpoint-buttons">
            <button class="btn btn-secondary" onclick="showDatabaseInfo()">
                📊 Datenbank-Server Info
            </button>
            <button class="btn btn-secondary" onclick="generatePassword()">
                🔐 Sicheres Passwort generieren
            </button>
        </div>
    </div>
</div>

<script>
// Database Module JavaScript
window.databaseModule = {
    init: function() {
        console.log('Database module initialized');
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
            
            alert(`Datenbank erfolgreich erstellt!\n\nVerbindungsdaten:\nHost: localhost\nDatenbank: ${dbName}\nBenutzer: ${dbUser}\nPasswort: [Ihr gewähltes Passwort]`);
            
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