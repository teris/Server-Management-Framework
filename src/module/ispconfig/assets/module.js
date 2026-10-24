/**
 * ISPConfig Module JavaScript
 * Grundlegende Funktionalitäten für das ISPConfig-Modul
 */

// ISPConfig Module Namespace
window.ISPConfigModule = window.ISPConfigModule || {};

// Globale Variablen im Namespace
ISPConfigModule.allUsers = [];
ISPConfigModule.filteredUsers = [];
ISPConfigModule.currentUserDetails = null;
ISPConfigModule.allDomains = [];
ISPConfigModule.filteredDomains = [];
ISPConfigModule.allDnsRecords = {
    ispconfig: [],
    ovh: [],
    combined: []
};
ISPConfigModule.currentDomain = '';
ISPConfigModule.currentDnsTab = 'combined';
ISPConfigModule.pendingChanges = [];

// Tab-Management
function switchTab(tabName, ev) {
    // Alle Tab-Inhalte verstecken
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Alle Tab-Buttons deaktivieren
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active');
    });
    
    // Gewählten Tab aktivieren
    document.getElementById(tabName + '-tab').classList.add('active');
    const e = ev || window.event;
    if (e && e.target) {
        e.target.classList.add('active');
    }
    
    // Tab-spezifische Behandlung
    switch(tabName) {
        case 'users':
            if (typeof loadAllUsers === 'function') {
                loadAllUsers();
            }
            break;
        case 'domains':
            if (typeof loadAllDomains === 'function') {
                loadAllDomains();
            }
            break;
        case 'websites':
            if (typeof loadAllWebsites === 'function') {
                loadAllWebsites();
            }
            break;
    }
}

// Benutzer-Tab-Management
function switchUserTab(tabName) {
    // Alle User-Tab-Inhalte verstecken
    document.querySelectorAll('#user-details-modal .user-tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Alle User-Tab-Buttons deaktivieren
    document.querySelectorAll('#user-details-modal .tab-button').forEach(button => {
        button.classList.remove('active');
    });
    
    // Gewählten User-Tab aktivieren
    document.getElementById('user-' + tabName + '-tab').classList.add('active');
    event.target.classList.add('active');
    
    // Daten laden
    if (ISPConfigModule.currentUserDetails) {
        if (typeof loadUserTabData === 'function') {
            loadUserTabData(tabName, ISPConfigModule.currentUserDetails.client_id);
        }
    }
}

// DNS-Tab-Management
function switchDnsTab(tabName) {
    ISPConfigModule.currentDnsTab = tabName;
    
    // Tab-Buttons aktualisieren
    document.querySelectorAll('.dns-tabs .tab-button').forEach(btn => {
        btn.classList.remove('active');
    });
    
    const activeBtn = document.getElementById(`dns-tab-${tabName}`);
    if (activeBtn) activeBtn.classList.add('active');
    
    // Records neu anzeigen
    if (typeof displayDnsRecords === 'function') {
        displayDnsRecords();
    }
}

// Globale Funktionen für Template-Zugriff
window.switchTab = switchTab;
window.switchUserTab = switchUserTab;
window.switchDnsTab = switchDnsTab;

// ISPConfig Module Manager
window.ISPConfigModuleManager = {
    /**
     * Erstellt eine neue Website
     */
    createWebsite: function(formData) {
        return ModuleManager.makeRequest('ispconfig', 'create_website', formData);
    },
    
    /**
     * Lädt alle Websites
     */
    getWebsites: function() {
        return ModuleManager.makeRequest('ispconfig', 'get_websites');
    },
    
    /**
     * Lädt alle Benutzer
     */
    getAllUsers: function() {
        return ModuleManager.makeRequest('ispconfig', 'get_all_users');
    },
    
    /**
     * Lädt Benutzer-Details
     */
    getUserDetails: function(clientId) {
        return ModuleManager.makeRequest('ispconfig', 'get_user_details', { client_id: clientId });
    },
    
    /**
     * Lädt alle Domains
     */
    getAllDomains: function() {
        return ModuleManager.makeRequest('ispconfig', 'get_all_domains');
    },
    
    /**
     * Lädt DNS-Einträge für Domain
     */
    getDomainDnsRecords: function(domain) {
        return ModuleManager.makeRequest('ispconfig', 'get_domain_dns_records', { domain: domain });
    }
};

// Einfache loadAllWebsites Funktion
async function loadAllWebsites() {
    const loadingEl = document.getElementById('websites-loading');
    const tbodyEl = document.getElementById('websites-tbody');
    const emptyEl = document.getElementById('no-websites');

    if (loadingEl) loadingEl.style.display = 'block';
    if (tbodyEl) tbodyEl.innerHTML = '';
    if (emptyEl) emptyEl.style.display = 'none';

    try {
        const response = await ModuleManager.makeRequest('ispconfig', 'get_websites');
        if (response.success) {
            const websites = response.data || [];
            if (websites.length === 0) {
                if (emptyEl) emptyEl.style.display = 'block';
                return;
            }
            if (tbodyEl) {
                tbodyEl.innerHTML = websites.map(site => `
                    <tr>
                        <td>${site.domain}</td>
                        <td>${site.assigned_user ? (site.assigned_user.company_name || site.assigned_user.contact_name || '-') : '-'}</td>
                        <td>${site.ip_address || '-'}</td>
                        <td>${site.hd_quota} MB</td>
                        <td>${site.traffic_quota} MB</td>
                        <td><span class="badge ${site.ssl_enabled === 'y' ? 'badge-success' : 'badge-secondary'}">${site.ssl_enabled === 'y' ? 'SSL' : 'No SSL'}</span></td>
                        <td><span class="badge ${site.active === 'y' ? 'badge-success' : 'badge-danger'}">${site.active === 'y' ? 'Aktiv' : 'Inaktiv'}</span></td>
                        <td>${ISPConfigUtils.formatDate(site.created_at)}</td>
                        <td>
                            <div class="btn-group" role="group">
                                <button class="btn btn-sm btn-secondary" title="Details" onclick="void(0)">ℹ️</button>
                            </div>
                        </td>
                    </tr>
                `).join('');
            }
        } else {
            ISPConfigUtils.showError('Fehler beim Laden der Websites: ' + (response.error || 'Unbekannter Fehler'));
        }
    } catch (err) {
        ISPConfigUtils.showError('Fehler beim Laden der Websites: ' + err.message);
    } finally {
        if (loadingEl) loadingEl.style.display = 'none';
    }
}

// Globale Funktion für Template-Zugriff
window.loadAllWebsites = loadAllWebsites;

// Hilfsfunktionen
window.ISPConfigUtils = {
    /**
     * Zeigt eine Erfolgsmeldung an
     */
    showSuccess: function(message) {
        if (typeof showSuccess === 'function') {
            showSuccess(message);
        } else {
            alert('Erfolg: ' + message);
        }
    },
    
    /**
     * Zeigt eine Fehlermeldung an
     */
    showError: function(message) {
        if (typeof showError === 'function') {
            showError(message);
        } else {
            alert('Fehler: ' + message);
        }
    },
    
    /**
     * Formatiert ein Datum
     */
    formatDate: function(dateString) {
        if (!dateString) return '-';
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString('de-DE');
        } catch (e) {
            return dateString;
        }
    },
    
    /**
     * Formatiert eine Dateigröße
     */
    formatFileSize: function(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
};

// Initialisierung
document.addEventListener('DOMContentLoaded', function() {
    console.log('ISPConfig Module initialized');
    
    // Event-Listener für Tab-Wechsel
    document.querySelectorAll('.tab-button').forEach(button => {
        button.addEventListener('click', function(e) {
            const tabName = this.getAttribute('onclick')?.match(/switchTab\('([^']+)'\)/)?.[1];
            if (tabName) {
                switchTab(tabName);
            }
        });
    });
    
    // Event-Listener für User-Tab-Wechsel
    document.querySelectorAll('#user-details-modal .tab-button').forEach(button => {
        button.addEventListener('click', function(e) {
            const tabName = this.getAttribute('onclick')?.match(/switchUserTab\('([^']+)'\)/)?.[1];
            if (tabName) {
                switchUserTab(tabName);
            }
        });
    });
    
    // Event-Listener für DNS-Tab-Wechsel
    document.querySelectorAll('.dns-tabs .tab-button').forEach(button => {
        button.addEventListener('click', function(e) {
            const tabName = this.getAttribute('onclick')?.match(/switchDnsTab\('([^']+)'\)/)?.[1];
            if (tabName) {
                switchDnsTab(tabName);
            }
        });
    });
});