<div id="email" class="tab-content">
    <h2>📧 E-Mail Adresse anlegen</h2>
    <form onsubmit="createEmail(event)">
        <div class="form-row">
            <div class="form-group">
                <label for="email_address">E-Mail Adresse</label>
                <input type="email" id="email_address" name="email" required placeholder="user@example.com">
            </div>
            <div class="form-group">
                <label for="email_login">Login Name</label>
                <input type="text" id="email_login" name="login" required placeholder="user" pattern="[a-zA-Z0-9._-]+" title="Nur Buchstaben, Zahlen, Punkt, Unterstrich und Bindestrich">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="email_password">Passwort</label>
                <input type="password" id="email_password" name="password" required minlength="6">
            </div>
            <div class="form-group">
                <label for="email_quota">Speicherplatz (MB)</label>
                <input type="number" id="email_quota" name="quota" value="1000" required min="100" max="10000">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="email_name">Vollständiger Name (optional)</label>
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
    
    <hr>
    
    <div class="endpoint-section">
        <h3>📱 E-Mail Client Konfiguration</h3>
        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 20px;">
            <div class="form-row">
                <div style="flex: 1;">
                    <h4>IMAP (Empfang)</h4>
                    <ul>
                        <li><strong>Server:</strong> mail.ihre-domain.de</li>
                        <li><strong>Port:</strong> 993 (SSL/TLS)</li>
                        <li><strong>Sicherheit:</strong> SSL/TLS</li>
                        <li><strong>Benutzername:</strong> Ihre E-Mail-Adresse</li>
                    </ul>
                </div>
                <div style="flex: 1;">
                    <h4>SMTP (Versand)</h4>
                    <ul>
                        <li><strong>Server:</strong> mail.ihre-domain.de</li>
                        <li><strong>Port:</strong> 587 (STARTTLS)</li>
                        <li><strong>Sicherheit:</strong> STARTTLS</li>
                        <li><strong>Authentifizierung:</strong> Erforderlich</li>
                    </ul>
                </div>
            </div>
            
            <h4 style="margin-top: 20px;">Alternative Ports</h4>
            <p><strong>IMAP:</strong> 143 (STARTTLS) | <strong>POP3:</strong> 995 (SSL/TLS), 110 (STARTTLS)</p>
            <p><strong>SMTP:</strong> 465 (SSL/TLS), 25 (unverschlüsselt - nicht empfohlen)</p>
        </div>
    </div>
    
    <div class="endpoint-section">
        <h3>🌐 Webmail Zugang</h3>
        <p>Sie können Ihre E-Mails auch über Webmail abrufen:</p>
        <div class="endpoint-buttons">
            <button class="btn btn-secondary" onclick="openWebmail('roundcube')">
                📮 Roundcube Webmail
            </button>
            <button class="btn btn-secondary" onclick="openWebmail('horde')">
                📧 Horde Webmail
            </button>
            <button class="btn btn-secondary" onclick="generateEmailPassword()">
                🔐 Sicheres Passwort
            </button>
        </div>
    </div>
    
    <div class="endpoint-section">
        <h3>⚙️ Erweiterte E-Mail Funktionen</h3>
        <ul style="color: #666;">
            <li>Autoresponder (Abwesenheitsnotiz)</li>
            <li>E-Mail Weiterleitungen</li>
            <li>Spam-Filter Einstellungen</li>
            <li>E-Mail Aliase</li>
            <li>Catch-All Adressen</li>
        </ul>
        <p style="color: #666; font-style: italic;">
            Diese Funktionen können nach der Erstellung über das ISPConfig Control Panel konfiguriert werden.
        </p>
    </div>
</div>

<script>
// Email Module JavaScript
window.emailModule = {
    init: function() {
        console.log('Email module initialized');
    },
    
    openWebmail: function(type) {
        const domain = document.getElementById('email_domain').value || 'ihre-domain.de';
        const urls = {
            'roundcube': `https://${domain}/webmail`,
            'horde': `https://${domain}/horde`
        };
        
        if (domain === 'ihre-domain.de') {
            showNotification('Bitte geben Sie zuerst Ihre Domain ein', 'warning');
            return;
        }
        
        showNotification(`Webmail URL: ${urls[type]}`, 'info');
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
        showNotification('Sicheres Passwort generiert', 'success');
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
            showNotification('E-Mail Adresse wurde erfolgreich erstellt!', 'success');
            
            // Zeige Konfigurationsdaten
            const email = formData.get('email');
            const domain = formData.get('domain');
            
            const configInfo = `E-Mail Account erfolgreich erstellt!
            
E-Mail: ${email}
            
IMAP Server: mail.${domain}
IMAP Port: 993 (SSL/TLS)

SMTP Server: mail.${domain}
SMTP Port: 587 (STARTTLS)

Webmail: https://${domain}/webmail`;
            
            alert(configInfo);
            
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