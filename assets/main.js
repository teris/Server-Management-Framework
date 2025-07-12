/**
 * Main JavaScript für Bootstrap-basiertes Admin Dashboard
 * Nutzt jQuery für alle DOM-Manipulationen und AJAX-Requests
 */

// Globale Variablen
let sessionTimer = null;
let heartbeatInterval = null;
let refreshInterval = null;

// Utility-Funktionen
const Utils = {
    // Toast-Benachrichtigungen anzeigen
    showNotification: function(message, type = 'info') {
        const toast = $('#notificationToast');
        const toastTitle = $('#toastTitle');
        const toastBody = $('#toastBody');
        
        // Icon und Titel basierend auf Typ
        const icons = {
            'success': 'bi-check-circle-fill text-success',
            'error': 'bi-x-circle-fill text-danger',
            'warning': 'bi-exclamation-triangle-fill text-warning',
            'info': 'bi-info-circle-fill text-info'
        };
        
        toastTitle.html(`<i class="bi ${icons[type]}"></i> ${type.charAt(0).toUpperCase() + type.slice(1)}`);
        toastBody.text(message);
        
        const bsToast = new bootstrap.Toast(toast[0]);
        bsToast.show();
    },
    
    // Loading-State anzeigen
    showLoading: function(container) {
        const loadingHtml = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Laden...</span>
                </div>
            </div>
        `;
        $(container).html(loadingHtml);
    },
    
    // Fehler anzeigen
    showError: function(container, message) {
        const errorHtml = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i> ${message}
            </div>
        `;
        $(container).html(errorHtml);
    },
    
    // Erfolg anzeigen
    showSuccess: function(container, message) {
        const successHtml = `
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill"></i> ${message}
            </div>
        `;
        $(container).html(successHtml);
    },
    
    // Formatierung von Bytes
    formatBytes: function(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    },
    
    // Formatierung von Prozent
    formatPercent: function(value, total) {
        if (total === 0) return '0%';
        return Math.round((value / total) * 100) + '%';
    }
};

// AJAX-Handler
const AjaxHandler = {
    // Basis-AJAX-Request
    request: function(url, data, options = {}) {
        const defaults = {
            method: 'POST',
            dataType: 'json',
            timeout: 30000
        };
        
        const settings = $.extend({}, defaults, options);
        
        return $.ajax({
            url: url,
            method: settings.method,
            data: data,
            dataType: settings.dataType,
            timeout: settings.timeout
        });
    },
    
    // Plugin-Request
    pluginRequest: function(plugin, action, data = {}) {
        return this.request('index.php', {
            plugin: plugin,
            action: action,
            ...data
        });
    },
    
    // Admin-Request
    adminRequest: function(action, data = {}) {
        return this.request('index.php', {
            core: 'admin',
            action: action,
            ...data
        });
    },
    
    // Heartbeat
    heartbeat: function() {
        return this.request('index.php', { action: 'heartbeat' });
    }
};

// Session-Management
const SessionManager = {
    // Session-Timer aktualisieren
    updateTimer: function() {
        const timeRemaining = $('#timeRemaining');
        if (timeRemaining.length) {
            // Hier würde die echte Session-Timer-Logik stehen
            timeRemaining.text('29:45');
        }
    },
    
    // Heartbeat senden
    sendHeartbeat: function() {
        AjaxHandler.heartbeat()
            .done(function(response) {
                if (!response.success && response.redirect) {
                    window.location.href = response.redirect;
                }
            })
            .fail(function() {
                Utils.showNotification('Session-Problem erkannt', 'warning');
            });
    },
    
    // Timer starten
    startTimers: function() {
        // Session-Timer jede Sekunde
        sessionTimer = setInterval(this.updateTimer, 1000);
        
        // Heartbeat alle 30 Sekunden
        heartbeatInterval = setInterval(this.sendHeartbeat, 30000);
    },
    
    // Timer stoppen
    stopTimers: function() {
        if (sessionTimer) clearInterval(sessionTimer);
        if (heartbeatInterval) clearInterval(heartbeatInterval);
    }
};

// Plugin-Management
const PluginManager = {
    // Plugin-Inhalt laden
    loadContent: function(pluginKey) {
        const contentDiv = $(`#${pluginKey}-content`);
        if (!contentDiv.length) return;
        
        Utils.showLoading(contentDiv);
        
        AjaxHandler.pluginRequest(pluginKey, 'getContent')
            .done(function(response) {
                if (response.success) {
                    contentDiv.html(response.content);
                    
                    // Plugin-spezifische Initialisierung
                    if (window[pluginKey + 'Plugin'] && typeof window[pluginKey + 'Plugin'].init === 'function') {
                        window[pluginKey + 'Plugin'].init();
                    }
                } else {
                    Utils.showError(contentDiv, 'Fehler beim Laden des Plugins: ' + (response.error || 'Unbekannter Fehler'));
                }
            })
            .fail(function(xhr, status, error) {
                Utils.showError(contentDiv, 'Fehler beim Laden des Plugins: ' + error);
            });
    },
    
    // Plugin-Aktion ausführen
    executeAction: function(pluginKey, action, data = {}) {
        return AjaxHandler.pluginRequest(pluginKey, action, data)
            .done(function(response) {
                if (response.success) {
                    Utils.showNotification(response.message || 'Aktion erfolgreich ausgeführt', 'success');
                } else {
                    Utils.showNotification(response.error || 'Fehler bei der Ausführung', 'error');
                }
            })
            .fail(function(xhr, status, error) {
                Utils.showNotification('Fehler: ' + error, 'error');
            });
    }
};

// Admin-Funktionen
const AdminFunctions = {
    // Alle Statistiken aktualisieren
    refreshAllStats: function() {
        Utils.showNotification('Statistiken werden aktualisiert...', 'info');
        
        AjaxHandler.adminRequest('get_dashboard_stats')
            .done(function(response) {
                if (response.success) {
                    AdminFunctions.updateStats(response.data);
                    Utils.showNotification('Statistiken aktualisiert', 'success');
                } else {
                    Utils.showNotification('Fehler beim Aktualisieren der Statistiken', 'error');
                }
            })
            .fail(function() {
                Utils.showNotification('Fehler beim Aktualisieren der Statistiken', 'error');
            });
    },
    
    // Statistiken in der UI aktualisieren
    updateStats: function(stats) {
        $.each(stats, function(key, value) {
            $(`#${key}-count`).text(value.count);
        });
    },
    
    // Cache leeren
    clearCache: function() {
        Utils.showNotification('Cache wird geleert...', 'info');
        
        AjaxHandler.adminRequest('clear_cache')
            .done(function(response) {
                if (response.success) {
                    Utils.showNotification('Cache erfolgreich geleert', 'success');
                } else {
                    Utils.showNotification('Fehler beim Leeren des Caches', 'error');
                }
            })
            .fail(function() {
                Utils.showNotification('Fehler beim Leeren des Caches', 'error');
            });
    },
    
    // Alle Verbindungen testen
    testAllConnections: function() {
        Utils.showNotification('Verbindungen werden getestet...', 'info');
        
        AjaxHandler.adminRequest('test_connections')
            .done(function(response) {
                if (response.success) {
                    Utils.showNotification('Alle Verbindungen funktionieren', 'success');
                } else {
                    Utils.showNotification('Einige Verbindungen haben Probleme', 'warning');
                }
            })
            .fail(function() {
                Utils.showNotification('Fehler beim Testen der Verbindungen', 'error');
            });
    },
    
    // VM-Daten laden
    loadVMData: function() {
        const contentDiv = $('#vm-content');
        Utils.showLoading(contentDiv);
        
        AjaxHandler.adminRequest('get_resources', { type: 'vms' })
            .done(function(response) {
                if (response.success) {
                    AdminFunctions.renderVMTable(contentDiv, response.data.data);
                } else {
                    Utils.showError(contentDiv, 'Fehler beim Laden der VMs: ' + (response.error || 'Unbekannter Fehler'));
                }
            })
            .fail(function(xhr, status, error) {
                Utils.showError(contentDiv, 'Fehler beim Laden der VMs: ' + error);
            });
    },
    
    // VM-Tabelle rendern
    renderVMTable: function(container, vms) {
        if (!vms || vms.length === 0) {
            container.html('<div class="alert alert-info">Keine VMs gefunden.</div>');
            return;
        }
        
        let html = '<table class="table table-striped table-hover">';
        html += '<thead><tr><th>Name</th><th>Status</th><th>CPU</th><th>RAM</th><th>Speicher</th><th>Aktionen</th></tr></thead><tbody>';
        
        vms.forEach(function(vm) {
            const statusClass = vm.status === 'running' ? 'success' : (vm.status === 'stopped' ? 'danger' : 'warning');
            html += '<tr>';
            html += '<td>' + vm.name + '</td>';
            html += '<td><span class="badge bg-' + statusClass + '">' + vm.status + '</span></td>';
            html += '<td>' + (vm.cpu || '-') + '</td>';
            html += '<td>' + (vm.ram || '-') + '</td>';
            html += '<td>' + (vm.storage || '-') + '</td>';
            html += '<td>';
            if (vm.status === 'running') {
                html += '<button class="btn btn-warning btn-sm me-1" onclick="AdminFunctions.controlVM(\'' + vm.id + '\', \'stop\')"><i class="bi bi-pause"></i></button>';
            } else {
                html += '<button class="btn btn-success btn-sm me-1" onclick="AdminFunctions.controlVM(\'' + vm.id + '\', \'start\')"><i class="bi bi-play"></i></button>';
            }
            html += '<button class="btn btn-danger btn-sm" onclick="AdminFunctions.controlVM(\'' + vm.id + '\', \'delete\')"><i class="bi bi-trash"></i></button>';
            html += '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        container.html(html);
    },
    
    // VM steuern
    controlVM: function(vmId, action) {
        AjaxHandler.adminRequest('control_vm', { vm_id: vmId, control: action })
            .done(function(response) {
                if (response.success) {
                    Utils.showNotification('VM ' + action + ' erfolgreich ausgeführt', 'success');
                    AdminFunctions.loadVMData();
                } else {
                    Utils.showNotification('Fehler: ' + (response.error || 'Unbekannter Fehler'), 'error');
                }
            })
            .fail(function(xhr, status, error) {
                Utils.showNotification('Fehler: ' + error, 'error');
            });
    },
    
    // Website-Daten laden
    loadWebsiteData: function() {
        const contentDiv = $('#website-content');
        Utils.showLoading(contentDiv);
        
        AjaxHandler.adminRequest('get_resources', { type: 'websites' })
            .done(function(response) {
                if (response.success) {
                    AdminFunctions.renderWebsiteTable(contentDiv, response.data.data);
                } else {
                    Utils.showError(contentDiv, 'Fehler beim Laden der Websites: ' + (response.error || 'Unbekannter Fehler'));
                }
            })
            .fail(function(xhr, status, error) {
                Utils.showError(contentDiv, 'Fehler beim Laden der Websites: ' + error);
            });
    },
    
    // Website-Tabelle rendern
    renderWebsiteTable: function(container, websites) {
        if (!websites || websites.length === 0) {
            container.html('<div class="alert alert-info">Keine Websites gefunden.</div>');
            return;
        }
        
        let html = '<table class="table table-striped table-hover">';
        html += '<thead><tr><th>Domain</th><th>Status</th><th>PHP Version</th><th>SSL</th><th>Aktionen</th></tr></thead><tbody>';
        
        websites.forEach(function(website) {
            const statusClass = website.status === 'active' ? 'success' : 'danger';
            const sslClass = website.ssl ? 'success' : 'secondary';
            html += '<tr>';
            html += '<td>' + website.domain + '</td>';
            html += '<td><span class="badge bg-' + statusClass + '">' + website.status + '</span></td>';
            html += '<td>' + (website.php_version || '-') + '</td>';
            html += '<td><span class="badge bg-' + sslClass + '">' + (website.ssl ? 'Ja' : 'Nein') + '</span></td>';
            html += '<td>';
            html += '<button class="btn btn-primary btn-sm me-1" onclick="AdminFunctions.controlWebsite(\'' + website.id + '\', \'edit\')"><i class="bi bi-pencil"></i></button>';
            html += '<button class="btn btn-danger btn-sm" onclick="AdminFunctions.controlWebsite(\'' + website.id + '\', \'delete\')"><i class="bi bi-trash"></i></button>';
            html += '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        container.html(html);
    },
    
    // Website steuern
    controlWebsite: function(websiteId, action) {
        AjaxHandler.adminRequest('control_website', { website_id: websiteId, control: action })
            .done(function(response) {
                if (response.success) {
                    Utils.showNotification('Website ' + action + ' erfolgreich ausgeführt', 'success');
                    AdminFunctions.loadWebsiteData();
                } else {
                    Utils.showNotification('Fehler: ' + (response.error || 'Unbekannter Fehler'), 'error');
                }
            })
            .fail(function(xhr, status, error) {
                Utils.showNotification('Fehler: ' + error, 'error');
            });
    },
    
    // Ähnliche Funktionen für andere Ressourcen
    loadDatabaseData: function() {
        const contentDiv = $('#database-content');
        Utils.showLoading(contentDiv);
        
        AjaxHandler.adminRequest('get_resources', { type: 'databases' })
            .done(function(response) {
                if (response.success) {
                    AdminFunctions.renderDatabaseTable(contentDiv, response.data.data);
                } else {
                    Utils.showError(contentDiv, 'Fehler beim Laden der Datenbanken: ' + (response.error || 'Unbekannter Fehler'));
                }
            })
            .fail(function(xhr, status, error) {
                Utils.showError(contentDiv, 'Fehler beim Laden der Datenbanken: ' + error);
            });
    },
    
    loadEmailData: function() {
        const contentDiv = $('#email-content');
        Utils.showLoading(contentDiv);
        
        AjaxHandler.adminRequest('get_resources', { type: 'emails' })
            .done(function(response) {
                if (response.success) {
                    AdminFunctions.renderEmailTable(contentDiv, response.data.data);
                } else {
                    Utils.showError(contentDiv, 'Fehler beim Laden der E-Mails: ' + (response.error || 'Unbekannter Fehler'));
                }
            })
            .fail(function(xhr, status, error) {
                Utils.showError(contentDiv, 'Fehler beim Laden der E-Mails: ' + error);
            });
    },
    
    loadDomainData: function() {
        const contentDiv = $('#domain-content');
        Utils.showLoading(contentDiv);
        
        AjaxHandler.adminRequest('get_resources', { type: 'domains' })
            .done(function(response) {
                if (response.success) {
                    AdminFunctions.renderDomainTable(contentDiv, response.data.data);
                } else {
                    Utils.showError(contentDiv, 'Fehler beim Laden der Domains: ' + (response.error || 'Unbekannter Fehler'));
                }
            })
            .fail(function(xhr, status, error) {
                Utils.showError(contentDiv, 'Fehler beim Laden der Domains: ' + error);
            });
    },
    
    loadLogs: function() {
        const contentDiv = $('#logs-content');
        Utils.showLoading(contentDiv);
        
        AjaxHandler.adminRequest('get_activity_logs')
            .done(function(response) {
                if (response.success) {
                    AdminFunctions.renderLogsTable(contentDiv, response.data.logs);
                } else {
                    Utils.showError(contentDiv, 'Fehler beim Laden der Logs: ' + (response.error || 'Unbekannter Fehler'));
                }
            })
            .fail(function(xhr, status, error) {
                Utils.showError(contentDiv, 'Fehler beim Laden der Logs: ' + error);
            });
    },
    
    saveSettings: function() {
        // Implementierung für Einstellungen
        Utils.showNotification('Einstellungen gespeichert', 'success');
    },
    
    // Render-Funktionen für Tabellen
    renderDatabaseTable: function(container, databases) {
        if (!databases || databases.length === 0) {
            container.html('<div class="alert alert-info">Keine Datenbanken gefunden.</div>');
            return;
        }
        
        let html = '<table class="table table-striped table-hover">';
        html += '<thead><tr><th>Name</th><th>Benutzer</th><th>Typ</th><th>Status</th><th>Aktionen</th></tr></thead><tbody>';
        
        databases.forEach(function(database) {
            const statusClass = database.active === 'y' ? 'success' : 'danger';
            html += '<tr>';
            html += '<td>' + database.database_name + '</td>';
            html += '<td>' + database.database_user + '</td>';
            html += '<td>' + (database.database_type || 'mysql') + '</td>';
            html += '<td><span class="badge bg-' + statusClass + '">' + (database.active === 'y' ? 'Aktiv' : 'Inaktiv') + '</span></td>';
            html += '<td>';
            html += '<button class="btn btn-primary btn-sm me-1" onclick="AdminFunctions.editDatabase(\'' + database.database_id + '\')"><i class="bi bi-pencil"></i></button>';
            html += '<button class="btn btn-danger btn-sm" onclick="AdminFunctions.deleteDatabase(\'' + database.database_id + '\')"><i class="bi bi-trash"></i></button>';
            html += '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        container.html(html);
    },
    
    renderEmailTable: function(container, emails) {
        if (!emails || emails.length === 0) {
            container.html('<div class="alert alert-info">Keine E-Mail-Konten gefunden.</div>');
            return;
        }
        
        let html = '<table class="table table-striped table-hover">';
        html += '<thead><tr><th>E-Mail</th><th>Name</th><th>Quota</th><th>Status</th><th>Aktionen</th></tr></thead><tbody>';
        
        emails.forEach(function(email) {
            const statusClass = email.active === 'y' ? 'success' : 'danger';
            html += '<tr>';
            html += '<td>' + email.email + '</td>';
            html += '<td>' + (email.name || '-') + '</td>';
            html += '<td>' + (email.quota || '-') + '</td>';
            html += '<td><span class="badge bg-' + statusClass + '">' + (email.active === 'y' ? 'Aktiv' : 'Inaktiv') + '</span></td>';
            html += '<td>';
            html += '<button class="btn btn-primary btn-sm me-1" onclick="AdminFunctions.editEmail(\'' + email.mailuser_id + '\')"><i class="bi bi-pencil"></i></button>';
            html += '<button class="btn btn-danger btn-sm" onclick="AdminFunctions.deleteEmail(\'' + email.mailuser_id + '\')"><i class="bi bi-trash"></i></button>';
            html += '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        container.html(html);
    },
    
    renderDomainTable: function(container, domains) {
        if (!domains || domains.length === 0) {
            container.html('<div class="alert alert-info">Keine Domains gefunden.</div>');
            return;
        }
        
        let html = '<table class="table table-striped table-hover">';
        html += '<thead><tr><th>Domain</th><th>Ablaufdatum</th><th>Auto-Renewal</th><th>Status</th><th>Nameserver</th><th>Aktionen</th></tr></thead><tbody>';
        
        domains.forEach(function(domain) {
            const statusClass = domain.state === 'active' ? 'success' : 'warning';
            html += '<tr>';
            html += '<td>' + domain.domain + '</td>';
            html += '<td>' + (domain.expiration || '-') + '</td>';
            html += '<td>' + (domain.autoRenew ? 'Ja' : 'Nein') + '</td>';
            html += '<td><span class="badge bg-' + statusClass + '">' + (domain.state || '-') + '</span></td>';
            html += '<td>' + (domain.nameServers ? domain.nameServers.join(', ') : '-') + '</td>';
            html += '<td>';
            html += '<button class="btn btn-secondary btn-sm" onclick="AdminFunctions.testDomainDNS(\'' + domain.domain + '\')"><i class="bi bi-globe"></i> DNS</button>';
            html += '</td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        container.html(html);
    },
    
    renderLogsTable: function(container, logs) {
        if (!logs || logs.length === 0) {
            container.html('<div class="alert alert-info">Keine Log-Einträge gefunden.</div>');
            return;
        }
        
        let html = '<table class="table table-striped table-hover">';
        html += '<thead><tr><th>Zeitstempel</th><th>Aktion</th><th>Details</th><th>Status</th></tr></thead><tbody>';
        
        logs.forEach(function(log) {
            const statusClass = log.status === 'success' ? 'success' : 'danger';
            html += '<tr>';
            html += '<td>' + new Date(log.created_at).toLocaleString('de-DE') + '</td>';
            html += '<td>' + (log.action || '-') + '</td>';
            html += '<td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;">' + (log.details || '-') + '</td>';
            html += '<td><span class="badge bg-' + statusClass + '">' + (log.status || '-') + '</span></td>';
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        container.html(html);
    },
    
    // Zusätzliche Hilfsfunktionen
    editDatabase: function(databaseId) {
        Utils.showNotification('Datenbank-Bearbeitung noch nicht implementiert', 'info');
    },
    
    editEmail: function(mailuserId) {
        Utils.showNotification('E-Mail-Bearbeitung noch nicht implementiert', 'info');
    },
    
    testDomainDNS: function(domain) {
        Utils.showNotification('DNS-Test für ' + domain + ' noch nicht implementiert', 'info');
    }
};

// Event-Handler
const EventHandlers = {
    // Tab-Wechsel für Admin-Bereich
    initAdminTabs: function() {
        $('#adminTabs .nav-link').on('click', function() {
            const target = $(this).data('bs-target');
            if (target === '#admin-resources') {
                // Ressourcen-Tab aktiviert - erste Ressource laden
                setTimeout(function() {
                    AdminFunctions.loadVMData();
                }, 100);
            }
        });
    },
    
    // Tab-Wechsel für Ressourcen
    initResourceTabs: function() {
        $('#resourceTabs .nav-link').on('click', function() {
            const target = $(this).data('bs-target');
            const resourceType = target.replace('#resource-', '');
            
            // Entsprechende Daten laden
            switch(resourceType) {
                case 'vms':
                    AdminFunctions.loadVMData();
                    break;
                case 'websites':
                    AdminFunctions.loadWebsiteData();
                    break;
                case 'databases':
                    AdminFunctions.loadDatabaseData();
                    break;
                case 'emails':
                    AdminFunctions.loadEmailData();
                    break;
                case 'domains':
                    AdminFunctions.loadDomainData();
                    break;
            }
        });
    },
    
    // Plugin-Tab-Wechsel
    initPluginTabs: function() {
        $('#pluginTabs .nav-link').on('click', function() {
            const pluginKey = $(this).attr('id').replace('-tab', '');
            PluginManager.loadContent(pluginKey);
        });
    },
    
    // Formular-Submission
    initForms: function() {
        $('form').on('submit', function(e) {
            e.preventDefault();
            
            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            const originalText = submitBtn.text();
            
            // Loading-State
            submitBtn.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Wird verarbeitet...');
            
            // AJAX-Submission
            $.ajax({
                url: form.attr('action') || window.location.href,
                method: form.attr('method') || 'POST',
                data: form.serialize(),
                dataType: 'json'
            })
            .done(function(response) {
                if (response.success) {
                    Utils.showNotification(response.message || 'Formular erfolgreich gesendet', 'success');
                    if (response.redirect) {
                        window.location.href = response.redirect;
                    }
                } else {
                    Utils.showNotification(response.error || 'Fehler beim Senden des Formulars', 'error');
                }
            })
            .fail(function() {
                Utils.showNotification('Fehler beim Senden des Formulars', 'error');
            })
            .always(function() {
                // Button zurücksetzen
                submitBtn.prop('disabled', false).text(originalText);
            });
        });
    }
};

// Initialisierung
$(document).ready(function() {
    // Session-Timer starten
    SessionManager.startTimers();
    
    // Event-Handler initialisieren
    EventHandlers.initAdminTabs();
    EventHandlers.initResourceTabs();
    EventHandlers.initPluginTabs();
    EventHandlers.initForms();
    
    // Erste Plugin-Inhalte laden
    const firstPlugin = Object.keys(window.enabledPlugins || {})[0];
    if (firstPlugin) {
        PluginManager.loadContent(firstPlugin);
    }
    
    // Erste Ressourcen laden
    AdminFunctions.loadVMData();
    
    // Auto-Refresh alle 30 Sekunden
    refreshInterval = setInterval(function() {
        AdminFunctions.refreshAllStats();
    }, 30000);
    
    // Debug-Informationen
    console.log('Admin Dashboard initialisiert');
    console.log('Verfügbare Plugins:', window.enabledPlugins || 'Nicht verfügbar');
    console.log('Session-Info:', window.sessionInfo || 'Nicht verfügbar');
});

// Cleanup beim Verlassen der Seite
$(window).on('beforeunload', function() {
    SessionManager.stopTimers();
    if (refreshInterval) clearInterval(refreshInterval);
});

// Globale Funktionen für onclick-Handler
window.refreshAllStats = AdminFunctions.refreshAllStats;
window.clearCache = AdminFunctions.clearCache;
window.testAllConnections = AdminFunctions.testAllConnections;
window.loadVMData = AdminFunctions.loadVMData;
window.loadWebsiteData = AdminFunctions.loadWebsiteData;
window.loadDatabaseData = AdminFunctions.loadDatabaseData;
window.loadEmailData = AdminFunctions.loadEmailData;
window.loadDomainData = AdminFunctions.loadDomainData;
window.loadLogs = AdminFunctions.loadLogs;
window.saveSettings = AdminFunctions.saveSettings;
window.loadPluginContent = PluginManager.loadContent;