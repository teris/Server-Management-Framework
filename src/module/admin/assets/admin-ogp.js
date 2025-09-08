/**
 * Admin Module - OGP/Gameserver Integration
 * JavaScript-Funktionen für OpenGamePanel Integration
 */

// Globale Variablen für Statistiken
let ogpStats = {
    totalServers: 0,
    activeServers: 0,
    onlinePlayers: 0,
    performance: {}
};

// Initialisierung beim Laden der Seite
document.addEventListener('DOMContentLoaded', function() {
    loadAllStats();
    loadSystemStatus();
    
    // Auto-Refresh alle 30 Sekunden
    setInterval(loadAllStats, 30000);
    setInterval(loadSystemStatus, 60000); // System-Status alle 60 Sekunden
});

/**
 * Lädt alle Statistiken
 */
function loadAllStats() {
    loadVMStats();
    loadWebsiteStats();
    loadDatabaseStats();
    loadEmailStats();
    loadOGPStats();
    loadActiveServersStats();
    loadOnlinePlayersStats();
    loadPerformanceStats();
}

/**
 * Lädt den System-Status
 */
function loadSystemStatus() {
    makeAdminRequest('get_system_status')
        .done(function(response) {
            if (response.success) {
                updateSystemStatusIndicators(response.data);
            } else {
                console.error('Error loading system status:', response.error);
                showSystemStatusError();
            }
        })
        .fail(function(xhr, status, error) {
            console.error('Error loading system status:', error);
            showSystemStatusError();
        });
}

/**
 * Aktualisiert die System-Status-Indikatoren
 */
function updateSystemStatusIndicators(statusData) {
    const statusMap = {
        'proxmox': 'Proxmox',
        'ispconfig': 'ISPConfig', 
        'ovh': 'OVH API',
        'database': 'Datenbank',
        'ogp': 'Open Game Panel'
    };
    
    Object.keys(statusMap).forEach(apiKey => {
        const status = statusData[apiKey];
        if (status) {
            updateStatusIndicator(apiKey, status.connected, status.message);
        }
    });
}

/**
 * Aktualisiert einen einzelnen Status-Indikator
 */
function updateStatusIndicator(apiKey, connected, message) {
    // Finde alle Status-Indikatoren für diese API
    const indicators = document.querySelectorAll(`[data-api="${apiKey}"]`);
    
    indicators.forEach(indicator => {
        const statusDot = indicator.querySelector('.status-indicator');
        const statusText = indicator.querySelector('small');
        
        if (statusDot && statusText) {
            // Entferne alle Status-Klassen
            statusDot.classList.remove('online', 'offline', 'error');
            
            // Setze die richtige Klasse
            if (connected) {
                statusDot.classList.add('online');
                statusText.textContent = 'Verbunden';
                statusText.className = 'text-muted';
            } else {
                statusDot.classList.add('offline');
                statusText.textContent = message || 'Nicht verbunden';
                statusText.className = 'text-danger';
            }
        }
    });
}

/**
 * Zeigt einen Fehler-Status für alle Indikatoren an
 */
function showSystemStatusError() {
    const allIndicators = document.querySelectorAll('.status-indicator');
    allIndicators.forEach(indicator => {
        indicator.classList.remove('online', 'offline');
        indicator.classList.add('error');
    });
    
    const allStatusTexts = document.querySelectorAll('.status-indicator + div small');
    allStatusTexts.forEach(text => {
        text.textContent = 'Status unbekannt';
        text.className = 'text-warning';
    });
}

/**
 * Lädt VM-Statistiken
 */
function loadVMStats() {
    makeAdminRequest('get_all_vms')
        .done(function(response) {
            if (response.success) {
                const activeVMs = response.data.filter(vm => vm.status === 'running').length;
                const totalVMs = response.data.length;
                updateStatsBadge('vm-stats', `${activeVMs}/${totalVMs} aktiv`);
            } else {
                updateStatsBadge('vm-stats', 'Fehler', 'error');
            }
        })
        .fail(function(xhr, status, error) {
            console.error('Error loading VM stats:', error);
            updateStatsBadge('vm-stats', 'Fehler', 'error');
        });
}

/**
 * Lädt Website-Statistiken
 */
function loadWebsiteStats() {
    makeAdminRequest('get_all_websites')
        .done(function(response) {
            if (response.success) {
                const totalWebsites = response.data.length;
                updateStatsBadge('website-stats', `${totalWebsites} Websites`);
            } else {
                updateStatsBadge('website-stats', 'Fehler', 'error');
            }
        })
        .fail(function(xhr, status, error) {
            console.error('Error loading website stats:', error);
            updateStatsBadge('website-stats', 'Fehler', 'error');
        });
}

/**
 * Lädt Datenbank-Statistiken
 */
function loadDatabaseStats() {
    makeAdminRequest('get_all_databases')
        .done(function(response) {
            if (response.success) {
                const totalDatabases = response.data.length;
                updateStatsBadge('database-stats', `${totalDatabases} DBs`);
            } else {
                updateStatsBadge('database-stats', 'Fehler', 'error');
            }
        })
        .fail(function(xhr, status, error) {
            console.error('Error loading database stats:', error);
            updateStatsBadge('database-stats', 'Fehler', 'error');
        });
}

/**
 * Lädt E-Mail-Statistiken
 */
function loadEmailStats() {
    makeAdminRequest('get_all_emails')
        .done(function(response) {
            if (response.success) {
                const totalEmails = response.data.length;
                updateStatsBadge('email-stats', `${totalEmails} E-Mails`);
            } else {
                updateStatsBadge('email-stats', 'Fehler', 'error');
            }
        })
        .fail(function(xhr, status, error) {
            console.error('Error loading email stats:', error);
            updateStatsBadge('email-stats', 'Fehler', 'error');
        });
}

/**
 * Lädt OGP-Server-Statistiken
 */
function loadOGPStats() {
    // Temporär deaktiviert - OGP-Integration noch nicht implementiert
    updateStatsBadge('ogp-stats', '0 Server (OGP nicht konfiguriert)', 'warning');
    
    /* 
    makeAdminRequest('get_ogp_servers')
        .done(function(response) {
            if (response.success) {
                ogpStats.totalServers = response.data.length;
                updateStatsBadge('ogp-stats', `${ogpStats.totalServers} Server`);
            } else {
                updateStatsBadge('ogp-stats', 'Fehler', 'error');
            }
        })
        .fail(function(xhr, status, error) {
            console.error('Error loading OGP stats:', error);
            updateStatsBadge('ogp-stats', 'Fehler', 'error');
        });
    */
}

/**
 * Lädt aktive Server-Statistiken
 */
function loadActiveServersStats() {
    // Temporär deaktiviert - OGP-Integration noch nicht implementiert
    updateStatsBadge('active-servers-stats', '0 aktiv (OGP nicht konfiguriert)', 'warning');
    
    /*
    makeAdminRequest('get_active_servers')
        .done(function(response) {
            if (response.success) {
                ogpStats.activeServers = response.data.length;
                updateStatsBadge('active-servers-stats', `${ogpStats.activeServers} aktiv`);
            } else {
                updateStatsBadge('active-servers-stats', 'Fehler', 'error');
            }
        })
        .fail(function(xhr, status, error) {
            console.error('Error loading active servers stats:', error);
            updateStatsBadge('active-servers-stats', 'Fehler', 'error');
        });
    */
}

/**
 * Lädt Online-Spieler-Statistiken
 */
function loadOnlinePlayersStats() {
    // Temporär deaktiviert - OGP-Integration noch nicht implementiert
    updateStatsBadge('online-players-stats', '0 Spieler (OGP nicht konfiguriert)', 'warning');
    
    /*
    makeAdminRequest('get_online_players')
        .done(function(response) {
            if (response.success) {
                ogpStats.onlinePlayers = response.data.total_players || 0;
                updateStatsBadge('online-players-stats', `${ogpStats.onlinePlayers} Spieler`);
            } else {
                updateStatsBadge('online-players-stats', 'Fehler', 'error');
            }
        })
        .fail(function(xhr, status, error) {
            console.error('Error loading online players stats:', error);
            updateStatsBadge('online-players-stats', 'Fehler', 'error');
        });
    */
}

/**
 * Lädt Performance-Statistiken
 */
function loadPerformanceStats() {
    // Temporär deaktiviert - OGP-Integration noch nicht implementiert
    updateStatsBadge('performance-stats', 'CPU: 0% (OGP nicht konfiguriert)', 'warning');
    
    /*
    makeAdminRequest('get_server_performance')
        .done(function(response) {
            if (response.success) {
                ogpStats.performance = response.data;
                const avgCpu = response.data.average_cpu || 0;
                updateStatsBadge('performance-stats', `CPU: ${avgCpu}%`);
            } else {
                updateStatsBadge('performance-stats', 'Fehler', 'error');
            }
        })
        .fail(function(xhr, status, error) {
            console.error('Error loading performance stats:', error);
            updateStatsBadge('performance-stats', 'Fehler', 'error');
        });
    */
}

/**
 * Aktualisiert ein Statistiken-Badge
 */
function updateStatsBadge(elementId, text, type = 'success') {
    const element = document.getElementById(elementId);
    if (element) {
        element.innerHTML = text;
        
        // Entferne das Loading-Icon
        const icon = element.querySelector('i.bi-hourglass-split');
        if (icon) {
            icon.remove();
        }
        
        // Setze die richtige Klasse basierend auf dem Typ
        let className = 'badge bg-light text-dark';
        if (type === 'error') {
            className += ' text-danger';
        } else if (type === 'warning') {
            className += ' text-warning';
        }
        
        element.className = className;
        
        // Füge eine kleine Animation hinzu
        element.classList.add('stats-update');
        setTimeout(() => {
            element.classList.remove('stats-update');
        }, 500);
    }
}

/**
 * OGP-spezifische Funktionen
 */

/**
 * Lädt OGP-Daten (für Button-Click)
 */
function loadOGPData() {
    loadOGPStats();
    showOGPNotification('OGP-Daten aktualisiert (OGP nicht konfiguriert)', 'warning');
}

/**
 * Lädt aktive Server-Daten (für Button-Click)
 */
function loadActiveServersData() {
    loadActiveServersStats();
    showOGPNotification('Aktive Server-Daten aktualisiert (OGP nicht konfiguriert)', 'warning');
}

/**
 * Lädt Online-Spieler-Daten (für Button-Click)
 */
function loadOnlinePlayersData() {
    loadOnlinePlayersStats();
    showOGPNotification('Online-Spieler-Daten aktualisiert (OGP nicht konfiguriert)', 'warning');
}

/**
 * Lädt Performance-Daten (für Button-Click)
 */
function loadPerformanceData() {
    loadPerformanceStats();
    showOGPNotification('Performance-Daten aktualisiert (OGP nicht konfiguriert)', 'warning');
}

/**
 * Lädt System-Status (für Button-Click)
 */
function refreshSystemStatus() {
    loadSystemStatus();
    showOGPNotification('System-Status aktualisiert', 'success');
}

/**
 * Steuert einen OGP-Server
 */
function controlOGPServer(serverId, action) {
    if (!confirm(`Möchten Sie den Server ${serverId} wirklich ${action}?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('server_id', serverId);
    formData.append('action', action);
    
    fetch('/src/handler.php?module=admin&action=control_ogp_server', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showOGPNotification(`Server ${action} erfolgreich`, 'success');
            loadAllStats(); // Statistiken aktualisieren
        } else {
            showOGPNotification(`Fehler beim ${action} des Servers: ${data.error}`, 'error');
        }
    })
    .catch(error => {
        console.error('Error controlling OGP server:', error);
        showOGPNotification('Fehler bei der Server-Steuerung', 'error');
    });
}

/**
 * Zeigt eine Benachrichtigung an (OGP-spezifisch)
 */
function showOGPNotification(message, type = 'info') {
    // Verwende die bestehende showNotification Funktion falls verfügbar
    if (typeof window.showNotification === 'function') {
        window.showNotification(message, type);
    } else {
        // Fallback: Einfache Toast-Benachrichtigung
        const toast = document.createElement('div');
        toast.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        toast.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(toast);
        
        // Auto-remove nach 5 Sekunden
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 5000);
    }
}

/**
 * Erweiterte OGP-Funktionen
 */

/**
 * Zeigt detaillierte Server-Informationen an
 */
function showServerDetails(serverId) {
    // Modal oder Sidebar mit Server-Details öffnen
    console.log('Showing details for server:', serverId);
    // TODO: Implementierung der Server-Details-Anzeige
}

/**
 * Zeigt Server-Logs an
 */
function showServerLogs(serverId) {
    // Modal mit Server-Logs öffnen
    console.log('Showing logs for server:', serverId);
    // TODO: Implementierung der Log-Anzeige
}

/**
 * Startet einen Server
 */
function startServer(serverId) {
    controlOGPServer(serverId, 'start');
}

/**
 * Stoppt einen Server
 */
function stopServer(serverId) {
    controlOGPServer(serverId, 'stop');
}

/**
 * Startet einen Server neu
 */
function restartServer(serverId) {
    controlOGPServer(serverId, 'restart');
}

/**
 * Aktualisiert einen Server
 */
function updateServer(serverId) {
    controlOGPServer(serverId, 'update');
}
