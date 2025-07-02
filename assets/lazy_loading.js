// =============================================================================
// LAZY LOADING SYSTEM - Daten nur bei Tab-Klick laden
// Ersetzt die Auto-Loading Logik in main.js
// =============================================================================

// Globale Variablen für Cache und Status
let currentData = {
    vms: [],
    websites: [],
    databases: [],
    emails: [],
    domains: [],
    vps: [],
    logs: []
};

let dataLoaded = {
    vms: false,
    websites: false,
    databases: false,
    emails: false,
    domains: false,
    vps: false,
    logs: false
};

let loadingStates = {
    vms: false,
    websites: false,
    databases: false,
    emails: false,
    domains: false,
    vps: false,
    logs: false
};

// =============================================================================
// TAB MANAGEMENT mit Lazy Loading
// =============================================================================

function showTab(tabName, element) {
    // Alle Tab-Inhalte verstecken
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    // Alle Tabs deaktivieren
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Gewählten Tab aktivieren
    document.getElementById(tabName).classList.remove('hidden');
    element.classList.add('active');
    
    // Admin-Tab: Nur Stats laden, nicht alle Daten
    if (tabName === 'admin') {
        loadStatsOnly();
    }
}

function showAdminTab(tabName, element) {
    // Admin Sub-Tabs verwalten
    document.querySelectorAll('.admin-tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    element.parentNode.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    document.getElementById('admin-' + tabName).classList.remove('hidden');
    element.classList.add('active');
    
    // Daten nur bei Bedarf laden
    loadDataForTab(tabName);
}

// =============================================================================
// LAZY LOADING FUNCTIONS
// =============================================================================

function loadDataForTab(tabName) {
    console.log(`🔄 Loading data for tab: ${tabName}`);
    
    // Wenn bereits geladen, nichts tun
    if (dataLoaded[tabName] && !shouldReload(tabName)) {
        console.log(`✅ Data for ${tabName} already loaded`);
        return;
    }
    
    // Wenn bereits am Laden, nichts tun
    if (loadingStates[tabName]) {
        console.log(`⏳ Data for ${tabName} already loading`);
        return;
    }
    
    // Loading-Indikator anzeigen
    showLoadingIndicator(tabName);
    
    // Entsprechende Load-Funktion aufrufen
    switch (tabName) {
        case 'vms':
            loadVMs();
            break;
        case 'websites':
            loadWebsites();
            break;
        case 'databases':
            loadDatabases();
            break;
        case 'emails':
            loadEmails();
            break;
        case 'domains':
            loadDomains();
            break;
        case 'vps-list':
            loadVPSList();
            break;
        case 'logs':
            loadActivityLog();
            break;
        default:
            console.warn(`Unknown tab: ${tabName}`);
    }
}

function shouldReload(tabName) {
    // Auto-Reload nach 5 Minuten für dynamische Daten
    const reloadInterval = 5 * 60 * 1000; // 5 Minuten
    const now = Date.now();
    const lastLoaded = localStorage.getItem(`lastLoaded_${tabName}`);
    
    if (!lastLoaded) return true;
    
    return (now - parseInt(lastLoaded)) > reloadInterval;
}

function markAsLoaded(tabName) {
    dataLoaded[tabName] = true;
    loadingStates[tabName] = false;
    localStorage.setItem(`lastLoaded_${tabName}`, Date.now().toString());
    hideLoadingIndicator(tabName);
}

function markAsLoading(tabName) {
    loadingStates[tabName] = true;
    showLoadingIndicator(tabName);
}

function markAsError(tabName, error) {
    loadingStates[tabName] = false;
    hideLoadingIndicator(tabName);
    showErrorIndicator(tabName, error);
}

// =============================================================================
// LOADING INDICATORS
// =============================================================================

function showLoadingIndicator(tabName) {
    const tableId = getTableId(tabName);
    const tbody = document.getElementById(tableId + '-tbody');
    
    if (tbody) {
        const colCount = tbody.closest('table').querySelectorAll('th').length;
        tbody.innerHTML = `
            <tr>
                <td colspan="${colCount}" style="text-align: center; padding: 40px;">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 10px;">
                        <div class="loading"></div>
                        <span>Lade ${getTabDisplayName(tabName)}...</span>
                    </div>
                    <div style="margin-top: 10px; font-size: 12px; color: #666;">
                        Dies kann bei vielen Einträgen einen Moment dauern
                    </div>
                </td>
            </tr>
        `;
    }
    
    // Stats auch aktualisieren
    updateSingleStat(tabName, '⏳');
}

function hideLoadingIndicator(tabName) {
    // Wird automatisch durch Display-Funktionen ersetzt
}

function showErrorIndicator(tabName, error) {
    const tableId = getTableId(tabName);
    const tbody = document.getElementById(tableId + '-tbody');
    
    if (tbody) {
        const colCount = tbody.closest('table').querySelectorAll('th').length;
        tbody.innerHTML = `
            <tr>
                <td colspan="${colCount}" style="text-align: center; padding: 40px; color: #dc2626;">
                    <div style="margin-bottom: 15px;">❌ Fehler beim Laden</div>
                    <div style="font-size: 14px; margin-bottom: 15px;">${error}</div>
                    <button class="btn btn-secondary" onclick="reloadTab('${tabName}')" style="font-size: 12px;">
                        🔄 Erneut versuchen
                    </button>
                    <button class="btn btn-secondary" onclick="loadMockData('${tabName}')" style="font-size: 12px; margin-left: 10px;">
                        🎭 Demo-Daten
                    </button>
                </td>
            </tr>
        `;
    }
    
    updateSingleStat(tabName, '❌');
}

// =============================================================================
// UTILITY FUNCTIONS
// =============================================================================

function getTableId(tabName) {
    const mapping = {
        'vms': 'vms-table',
        'websites': 'websites-table',
        'databases': 'databases-table',
        'emails': 'emails-table',
        'domains': 'domains-table',
        'vps-list': 'vps-table',
        'logs': 'logs-table'
    };
    return mapping[tabName] || tabName + '-table';
}

function getTabDisplayName(tabName) {
    const mapping = {
        'vms': 'Virtual Machines',
        'websites': 'Websites',
        'databases': 'Datenbanken',
        'emails': 'E-Mail Accounts',
        'domains': 'Domains',
        'vps-list': 'VPS Server',
        'logs': 'Activity Log'
    };
    return mapping[tabName] || tabName;
}

function updateSingleStat(tabName, value) {
    const statMapping = {
        'vms': 'vm-count',
        'websites': 'website-count', 
        'databases': 'database-count',
        'emails': 'email-count',
        'domains': 'domain-count',
        'vps-list': 'vps-count'
    };
    
    const elementId = statMapping[tabName];
    if (elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = value;
        }
    }
}

function reloadTab(tabName) {
    dataLoaded[tabName] = false;
    localStorage.removeItem(`lastLoaded_${tabName}`);
    loadDataForTab(tabName);
}

function loadMockData(tabName) {
    console.log(`Loading mock data for ${tabName}`);
    
    // Mock-Daten je nach Tab
    switch (tabName) {
        case 'emails':
            loadMockEmails();
            break;
        case 'vms':
            loadMockVMs();
            break;
        case 'websites':
            loadMockWebsites();
            break;
        // Weitere Mock-Funktionen...
        default:
            showNotification(`Mock-Daten für ${tabName} nicht verfügbar`, 'warning');
    }
}

async function loadMockEmails() {
    try {
        markAsLoading('emails');
        const result = await makeRequest('test_email_mock');
        if (result.success) {
            currentData.emails = result.data;
            displayEmails(result.data);
            markAsLoaded('emails');
            updateSingleStat('emails', result.data.length);
            showNotification('Demo E-Mail Daten geladen', 'success');
        }
    } catch (error) {
        markAsError('emails', error.message);
    }
}

// =============================================================================
// STATS-ONLY LOADING (für Admin Dashboard)
// =============================================================================

async function loadStatsOnly() {
    console.log('📊 Loading stats only...');
    
    try {
        // Schnelle Stats-Abfrage ohne komplette Daten
        const promises = [
            getDataCount('get_all_vms'),
            getDataCount('get_all_websites'), 
            getDataCount('get_all_databases'),
            getDataCount('get_all_emails'),
            getDataCount('get_all_domains'),
            getDataCount('get_all_vps')
        ];
        
        const [vmCount, websiteCount, dbCount, emailCount, domainCount, vpsCount] = await Promise.allSettled(promises);
        
        updateSingleStat('vms', vmCount.status === 'fulfilled' ? vmCount.value : '?');
        updateSingleStat('websites', websiteCount.status === 'fulfilled' ? websiteCount.value : '?');
        updateSingleStat('databases', dbCount.status === 'fulfilled' ? dbCount.value : '?');
        updateSingleStat('emails', emailCount.status === 'fulfilled' ? emailCount.value : '?');
        updateSingleStat('domains', domainCount.status === 'fulfilled' ? domainCount.value : '?');
        updateSingleStat('vps-list', vpsCount.status === 'fulfilled' ? vpsCount.value : '?');
        
        console.log('✅ Stats loaded successfully');
        
    } catch (error) {
        console.error('❌ Error loading stats:', error);
    }
}

async function getDataCount(action) {
    try {
        const result = await makeRequest(action);
        if (result.success && result.data && Array.isArray(result.data)) {
            return result.data.length;
        } else if (result.warning) {
            // Mock-Daten wurden verwendet
            return result.data ? result.data.length : 0;
        }
        return 0;
    } catch (error) {
        console.warn(`Failed to get count for ${action}:`, error);
        return '?';
    }
}

// =============================================================================
// MODIFIZIERTE LOAD FUNCTIONS (mit Loading States)
// =============================================================================

async function loadEmails() {
    markAsLoading('emails');
    
    try {
        console.log('📧 Loading emails...');
        
        const result = await makeRequest('get_all_emails');
        
        if (result.success) {
            currentData.emails = result.data;
            displayEmails(result.data);
            markAsLoaded('emails');
            updateSingleStat('emails', result.data.length);
            
            if (result.warning) {
                showNotification(result.warning, 'warning');
            }
        } else {
            throw new Error(result.error || 'Unbekannter Fehler');
        }
        
    } catch (error) {
        console.error('❌ Email loading error:', error);
        markAsError('emails', error.message);
        showNotification('Fehler beim Laden der E-Mail Accounts: ' + error.message, 'error');
    }
}

async function loadVMs() {
    markAsLoading('vms');
    
    try {
        console.log('🖥️ Loading VMs...');
        
        const result = await makeRequest('get_all_vms');
        
        if (result.success) {
            currentData.vms = result.data;
            displayVMs(result.data);
            markAsLoaded('vms');
            updateSingleStat('vms', result.data.length);
        } else {
            throw new Error(result.error || 'Unbekannter Fehler');
        }
        
    } catch (error) {
        console.error('❌ VM loading error:', error);
        markAsError('vms', error.message);
        showNotification('Fehler beim Laden der VMs: ' + error.message, 'error');
    }
}

async function loadWebsites() {
    markAsLoading('websites');
    
    try {
        console.log('🌐 Loading websites...');
        
        const result = await makeRequest('get_all_websites');
        
        if (result.success) {
            currentData.websites = result.data;
            displayWebsites(result.data);
            markAsLoaded('websites');
            updateSingleStat('websites', result.data.length);
        } else {
            throw new Error(result.error || 'Unbekannter Fehler');
        }
        
    } catch (error) {
        console.error('❌ Website loading error:', error);
        markAsError('websites', error.message);
    }
}

async function loadDatabases() {
    markAsLoading('databases');
    
    try {
        const result = await makeRequest('get_all_databases');
        
        if (result.success) {
            currentData.databases = result.data;
            displayDatabases(result.data);
            markAsLoaded('databases');
            updateSingleStat('databases', result.data.length);
        } else {
            throw new Error(result.error || 'Unbekannter Fehler');
        }
        
    } catch (error) {
        markAsError('databases', error.message);
    }
}

async function loadDomains() {
    markAsLoading('domains');
    
    try {
        const result = await makeRequest('get_all_domains');
        
        if (result.success) {
            currentData.domains = result.data;
            displayDomains(result.data);
            markAsLoaded('domains');
            updateSingleStat('domains', result.data.length);
        } else {
            throw new Error(result.error || 'Unbekannter Fehler');
        }
        
    } catch (error) {
        markAsError('domains', error.message);
    }
}

async function loadVPSList() {
    markAsLoading('vps-list');
    
    try {
        const result = await makeRequest('get_all_vps');
        
        if (result.success) {
            currentData.vps = result.data;
            displayVPSList(result.data);
            markAsLoaded('vps-list');
            updateSingleStat('vps-list', result.data.length);
        } else {
            throw new Error(result.error || 'Unbekannter Fehler');
        }
        
    } catch (error) {
        markAsError('vps-list', error.message);
    }
}

async function loadActivityLog() {
    markAsLoading('logs');
    
    try {
        const result = await makeRequest('get_activity_log');
        
        if (result.success) {
            currentData.logs = result.data;
            displayActivityLog(result.data);
            markAsLoaded('logs');
        } else {
            throw new Error(result.error || 'Unbekannter Fehler');
        }
        
    } catch (error) {
        markAsError('logs', error.message);
    }
}

// =============================================================================
// MODIFIED INITIALIZATION
// =============================================================================

// Initialize when page loads - OHNE Auto-Loading
document.addEventListener('DOMContentLoaded', function() {
    console.log('📄 Page loaded - Lazy loading system initialized');
    
    // Session-Timer starten
    if (typeof initSessionTimer === 'function') {
        initSessionTimer();
    }
    
    // Session-Heartbeat starten
    if (typeof startSessionHeartbeat === 'function') {
        startSessionHeartbeat();
    }
    
    // NUR Stats laden beim ersten Aufruf
    if (!document.getElementById('admin').classList.contains('hidden')) {
        loadStatsOnly();
    }
    
    console.log('✅ Lazy loading system ready - click tabs to load data');
});

// Session-Heartbeat stoppen wenn Seite verlassen wird
window.addEventListener('beforeunload', function() {
    if (typeof stopSessionHeartbeat === 'function') {
        stopSessionHeartbeat();
    }
});