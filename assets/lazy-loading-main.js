// Globale Variablen
let currentData = {
    vms: [],
    websites: [],
    databases: [],
    emails: [],
    domains: [],
    vps: [],
    logs: [],
    virtualMacs: [], // Added for Virtual MACs
    virtualMacOverview: [], // Added for Virtual MAC Overview
    dedicatedServers: [] // Added for dedicated server list
};

// Track welche Daten bereits geladen wurden
let dataLoaded = {
    vms: false,
    websites: false,
    databases: false,
    emails: false,
    domains: false,
    vps: false,
    logs: false,
    virtualMacs: false, // Added for Virtual MACs
    virtualMacOverview: false, // Added for Virtual MAC Overview
    dedicatedServers: false // Added for dedicated server list
};

// Session Management
let sessionHeartbeatInterval;

// Am Anfang der Datei nach den globalen Variablen
async function makeRequest(action, formData) {
    // Wenn kein Modul angegeben, nutze Legacy-Mapping
    if (!formData || !formData.module) {
        const module = window.ModuleManager ? ModuleManager.currentModule : 'admin';
        
        // Legacy-Kompatibilität
        const data = new FormData();
        data.append('action', action);
        data.append('module', module);
        
        if (formData instanceof FormData) {
            for (const [key, value] of formData.entries()) {
                if (key !== 'action') data.append(key, value);
            }
        } else if (formData) {
            for (const [key, value] of Object.entries(formData)) {
                if (key !== 'action') data.append(key, value);
            }
        }
        
        try {
            const response = await fetch('', {
                method: 'POST',
                body: data
            });
            
            const result = await response.json();
            
            if (!result.success && result.redirect) {
                showNotification('Session abgelaufen - Sie werden weitergeleitet', 'error');
                setTimeout(() => {
                    window.location.href = result.redirect;
                }, 2000);
            }
            
            return result;
        } catch (error) {
            console.error('Request failed:', error);
            throw error;
        }
    }
    
    // Neue Modul-basierte Requests
    return ModuleManager.makeRequest(ModuleManager.currentModule, action, formData);
}

function startSessionHeartbeat() {
    sessionHeartbeatInterval = setInterval(() => {
        fetch('?heartbeat=1')
            .then(response => response.json())
            .then(data => {
                if (!data.success && data.redirect) {
                    window.location.href = data.redirect;
                }
            })
            .catch(error => {
                console.warn('Heartbeat failed:', error);
            });
    }, 120000); // 2 Minuten
}

function stopSessionHeartbeat() {
    if (sessionHeartbeatInterval) {
        clearInterval(sessionHeartbeatInterval);
    }
}

// Tab Management
function showTab(tabName, element) {
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    const tabContentElement = document.getElementById(tabName);
    if (tabContentElement) {
        tabContentElement.classList.remove('hidden');
    }
    if (element) {
        element.classList.add('active');
    }
    
    if (tabName === 'admin') {
        loadStatsOnly();
        // Ensure the default admin tab (e.g., VMs) is shown and data loaded if needed
        const firstAdminTabButton = document.querySelector('#admin .tabs .tab.active');
        if (firstAdminTabButton) {
            const adminTabName = firstAdminTabButton.getAttribute('onclick').match(/showAdminTab\('([^']+)'/)[1];
            showAdminTab(adminTabName, firstAdminTabButton);
        }
    } else if (tabName === 'virtual-mac') {
        // Handle default view for 'virtual-mac' main tab
        const firstVirtualMacTabButton = document.querySelector('#virtual-mac .tabs .tab.active') || document.querySelector('#virtual-mac .tabs .tab');
         if (firstVirtualMacTabButton) {
            const virtualMacSubTabName = firstVirtualMacTabButton.getAttribute('onclick').match(/showVirtualMacTab\('([^']+)'/)[1];
            showVirtualMacTab(virtualMacSubTabName, firstVirtualMacTabButton);
        }
    }
}

function showAdminTab(tabName, element) {
    document.querySelectorAll('.admin-tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    if (element && element.parentNode) {
        element.parentNode.querySelectorAll('.tab').forEach(tab => {
            tab.classList.remove('active');
        });
    }
    
    const adminTabContentElement = document.getElementById('admin-' + tabName);
    if (adminTabContentElement) {
        adminTabContentElement.classList.remove('hidden');
    }
    if (element) {
        element.classList.add('active');
    }
    
    loadDataForTab(tabName);
}

// Lazy Loading Funktion
function loadDataForTab(tabName) {
    const loadFunctions = {
        'vms': loadVMs,
        'websites': loadWebsites,
        'databases': loadDatabases,
        'emails': loadEmails,
        'domains': loadDomains,
        'vps-list': loadVPSList,
        'logs': loadActivityLog,
        'virtual-macs': loadVirtualMacs // Added for admin virtual MACs table
    };
    
    const dataKey = tabName === 'vps-list' ? 'vps' : tabName;

    if (!dataLoaded[dataKey]) {
        const loadFunction = loadFunctions[tabName];
        if (loadFunction) {
            showLoadingForTab(tabName);
            loadFunction().catch(error => {
                console.error(`Error loading data for tab ${tabName}:`, error);
                showNotification(`Fehler beim Laden von ${tabName}: ${error.message}`, 'error');
                // Optionally hide loading indicator here or display error in table
                const tbody = document.getElementById(getTableBodyIdForTab(tabName));
                if (tbody) {
                    const colspan = tbody.parentElement.querySelector('thead tr th').length || 7;
                    tbody.innerHTML = `<tr><td colspan="${colspan}" style="text-align: center; color: red;">Laden fehlgeschlagen.</td></tr>`;
                }
            });
        }
    }
}

function getTableBodyIdForTab(tabName) {
    const tableMapping = {
        'vms': 'vms-tbody',
        'websites': 'websites-tbody',
        'databases': 'databases-tbody',
        'emails': 'emails-tbody',
        'domains': 'domains-tbody',
        'vps-list': 'vps-tbody',
        'logs': 'logs-tbody',
        'virtual-macs': 'virtual-macs-tbody',
        'virtual-mac-overview': 'virtual-mac-overview-tbody'
    };
    return tableMapping[tabName];
}

// Loading-Indikator für einen Tab anzeigen
function showLoadingForTab(tabName) {
    const tbodyId = getTableBodyIdForTab(tabName);
    const tbody = document.getElementById(tbodyId);
    if (tbody) {
        const colspan = tbody.parentElement.querySelector('thead tr th').length || 7;
        tbody.innerHTML = `<tr><td colspan="${colspan}" style="text-align: center;"><div class="loading"></div> Lade Daten...</td></tr>`;
    }
}

// Nur Statistiken laden (für Admin Dashboard)
async function loadStatsOnly() {
    try {
        const requests = [
            makeRequest('get_all_vms'),
            makeRequest('get_all_websites'),
            makeRequest('get_all_databases'),
            makeRequest('get_all_emails'),
            makeRequest('get_all_domains'),
            makeRequest('get_all_vps'),
            makeRequest('get_all_virtual_macs') // For virtual-mac-count
        ];
        
        const results = await Promise.allSettled(requests);
        
        const updateStat = (elementId, result) => {
            const element = document.getElementById(elementId);
            if (element) {
                element.textContent = result.status === 'fulfilled' && result.value.success && result.value.data ? result.value.data.length : '0';
            }
        };

        updateStat('vm-count', results[0]);
        updateStat('website-count', results[1]);
        updateStat('database-count', results[2]);
        updateStat('email-count', results[3]);
        updateStat('domain-count', results[4]);
        updateStat('vps-count', results[5]);
        updateStat('virtual-mac-count', results[6]);

    } catch (error) {
        console.error('Fehler beim Laden der Statistiken:', error);
    }
}

// Notification System
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 5000);
}

// Loading State Management
function setLoading(form, loading) {
    if (!form) return;
    const button = form.querySelector('button[type="submit"]');
    if (!button) return;
    const spinner = button.querySelector('.loading');
    
    if (loading) {
        button.disabled = true;
        if (spinner) spinner.classList.remove('hidden');
    } else {
        button.disabled = false;
        if (spinner) spinner.classList.add('hidden');
    }
}



// Data Loading Functions with Lazy Loading
async function loadVMs() {
    try {
        const result = await makeRequest('get_all_vms');
        if (result.success) {
            currentData.vms = result.data || [];
            displayVMs(currentData.vms);
            dataLoaded.vms = true;
            const vmCountEl = document.getElementById('vm-count');
            if (vmCountEl) vmCountEl.textContent = currentData.vms.length;
        } else {
            showNotification('Fehler beim Laden der VMs: ' + (result.error || 'Unbekannter Fehler'), 'error');
            displayVMs([]); // Show empty table
        }
    } catch (error) {
        showNotification('Netzwerkfehler beim Laden der VMs', 'error');
        displayVMs([]);
    }
}

async function loadWebsites() {
    try {
        const result = await makeRequest('get_all_websites');
        if (result.success) {
            currentData.websites = result.data || [];
            displayWebsites(currentData.websites);
            dataLoaded.websites = true;
            const el = document.getElementById('website-count');
            if (el) el.textContent = currentData.websites.length;
        } else {
            showNotification('Fehler beim Laden der Websites: ' + (result.error || 'Unbekannter Fehler'), 'error');
            displayWebsites([]);
        }
    } catch (error) {
        showNotification('Netzwerkfehler beim Laden der Websites', 'error');
        displayWebsites([]);
    }
}

async function loadDatabases() {
    try {
        const result = await makeRequest('get_all_databases');
        if (result.success) {
            currentData.databases = result.data || [];
            displayDatabases(currentData.databases);
            dataLoaded.databases = true;
            const el = document.getElementById('database-count');
            if (el) el.textContent = currentData.databases.length;
        } else {
            showNotification('Fehler beim Laden der Datenbanken: ' + (result.error || 'Unbekannter Fehler'), 'error');
            displayDatabases([]);
        }
    } catch (error) {
        showNotification('Netzwerkfehler beim Laden der Datenbanken', 'error');
        displayDatabases([]);
    }
}

async function loadEmails() {
    try {
        const result = await makeRequest('get_all_emails');
        if (result.success) {
            currentData.emails = result.data || [];
            displayEmails(currentData.emails);
            dataLoaded.emails = true;
            const el = document.getElementById('email-count');
            if (el) el.textContent = currentData.emails.length;
            if (result.warning) {
                showNotification(result.warning, 'warning');
            }
        } else {
            showNotification('Fehler beim Laden der E-Mail Accounts: ' + (result.error || 'Unbekannter Fehler'), 'error');
            displayEmails([]);
        }
    } catch (error) {
        showNotification('Netzwerkfehler beim Laden der E-Mail Accounts', 'error');
        displayEmails([]);
    }
}

async function loadDomains() {
    try {
        const result = await makeRequest('get_all_domains');
        if (result.success) {
            currentData.domains = result.data || [];
            displayDomains(currentData.domains);
            dataLoaded.domains = true;
            const el = document.getElementById('domain-count');
            if (el) el.textContent = currentData.domains.length;
        } else {
            showNotification('Fehler beim Laden der Domains: ' + (result.error || 'Unbekannter Fehler'), 'error');
            displayDomains([]);
        }
    } catch (error) {
        showNotification('Netzwerkfehler beim Laden der Domains', 'error');
        displayDomains([]);
    }
}

async function loadVPSList() {
    try {
        const result = await makeRequest('get_all_vps');
        if (result.success) {
            currentData.vps = result.data || [];
            displayVPSList(currentData.vps);
            dataLoaded.vps = true;
            const el = document.getElementById('vps-count');
            if (el) el.textContent = currentData.vps.length;
        } else {
            showNotification('Fehler beim Laden der VPS: ' + (result.error || 'Unbekannter Fehler'), 'error');
            displayVPSList([]);
        }
    } catch (error) {
        showNotification('Netzwerkfehler beim Laden der VPS', 'error');
        displayVPSList([]);
    }
}

async function loadActivityLog() {
    try {
        const result = await makeRequest('get_activity_log');
        if (result.success) {
            currentData.logs = result.data || [];
            displayActivityLog(currentData.logs);
            dataLoaded.logs = true;
        } else {
            showNotification('Fehler beim Laden des Activity Logs: ' + (result.error || 'Unbekannter Fehler'), 'error');
            displayActivityLog([]);
        }
    } catch (error) {
        showNotification('Netzwerkfehler beim Laden des Activity Logs', 'error');
        displayActivityLog([]);
    }
}


// Display Functions
function displayVMs(vms) {
    const tbody = document.getElementById('vms-tbody');
    if (!tbody) return;
    if (!vms || vms.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center;">Keine VMs gefunden</td></tr>';
        return;
    }
    tbody.innerHTML = vms.map(vm => `
        <tr>
            <td>${vm.vmid || 'N/A'}</td>
            <td>${vm.name || 'N/A'}</td>
            <td>${vm.node || 'N/A'}</td>
            <td><span class="status-badge ${vm.status === 'running' ? 'status-running' : 'status-stopped'}">${vm.status || 'unknown'}</span></td>
            <td>${vm.cores || vm.cpus || 'N/A'}</td>
            <td>${vm.memory ? Math.round(vm.memory/1024/1024) + ' MB' : 'N/A'}</td>
            <td class="action-buttons">
                ${vm.status === 'running' ? 
                    `<button class="btn btn-warning" onclick="controlVM('${vm.node}', '${vm.vmid}', 'stop')">?? Stop</button>
                     <button class="btn btn-secondary" onclick="controlVM('${vm.node}', '${vm.vmid}', 'suspend')">?? Suspend</button>` :
                    `<button class="btn btn-success" onclick="controlVM('${vm.node}', '${vm.vmid}', 'start')">?? Start</button>`
                }
                <button class="btn btn-secondary" onclick="controlVM('${vm.node}', '${vm.vmid}', 'reset')">?? Reset</button>
                <button class="btn btn-danger" onclick="deleteVM('${vm.node}', '${vm.vmid}')">??? Löschen</button>
            </td>
        </tr>
    `).join('');
}

function displayWebsites(websites) {
    const tbody = document.getElementById('websites-tbody');
    if (!tbody) return;
    if (!websites || websites.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Keine Websites gefunden</td></tr>';
        return;
    }
    tbody.innerHTML = websites.map(site => `
        <tr>
            <td>${site.domain || 'N/A'}</td>
            <td>${site.ip_address || 'N/A'}</td>
            <td>${site.system_user || 'N/A'}</td>
            <td><span class="status-badge ${site.active === 'y' ? 'status-active' : 'status-stopped'}">${site.active === 'y' ? 'Aktiv' : 'Inaktiv'}</span></td>
            <td>${site.hd_quota || 'N/A'}</td>
            <td class="action-buttons">
                <button class="btn btn-danger" onclick="deleteWebsite('${site.domain_id}')">??? Löschen</button>
            </td>
        </tr>
    `).join('');
}

function displayDatabases(databases) {
    const tbody = document.getElementById('databases-tbody');
    if (!tbody) return;
    if (!databases || databases.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center;">Keine Datenbanken gefunden</td></tr>';
        return;
    }
    tbody.innerHTML = databases.map(db => `
        <tr>
            <td>${db.database_name || 'N/A'}</td>
            <td>${db.database_user || 'N/A'}</td>
            <td>${db.database_type || 'mysql'}</td>
            <td><span class="status-badge ${db.active === 'y' ? 'status-active' : 'status-stopped'}">${db.active === 'y' ? 'Aktiv' : 'Inaktiv'}</span></td>
            <td class="action-buttons">
                <button class="btn btn-danger" onclick="deleteDatabase('${db.database_id}')">??? Löschen</button>
            </td>
        </tr>
    `).join('');
}

function displayEmails(emails) {
    const tbody = document.getElementById('emails-tbody');
    if (!tbody) return;
    if (!emails || emails.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center;">Keine E-Mail Accounts gefunden</td></tr>';
        return;
    }
    tbody.innerHTML = emails.map(email => `
        <tr>
            <td>${email.email || 'N/A'}</td>
            <td>${email.name || 'N/A'}</td>
            <td>${email.quota || 'N/A'}</td>
            <td><span class="status-badge ${email.active === 'y' ? 'status-active' : 'status-stopped'}">${email.active === 'y' ? 'Aktiv' : 'Inaktiv'}</span></td>
            <td class="action-buttons">
                <button class="btn btn-danger" onclick="deleteEmail('${email.mailuser_id}')">??? Löschen</button>
            </td>
        </tr>
    `).join('');
}

function displayDomains(domains) {
    const tbody = document.getElementById('domains-tbody');
    if (!tbody) return;
    if (!domains || domains.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Keine Domains gefunden</td></tr>';
        return;
    }
    tbody.innerHTML = domains.map(domain => `
        <tr>
            <td>${domain.domain || 'N/A'}</td>
            <td>${domain.expiration || 'N/A'}</td>
            <td>${domain.autoRenew ? 'Ja' : 'Nein'}</td>
            <td><span class="status-badge status-active">${domain.state || 'N/A'}</span></td>
            <td>${domain.nameServers ? domain.nameServers.join(', ') : 'N/A'}</td>
            <td class="action-buttons">
                <button class="btn btn-secondary" onclick="testEndpointWithParam('get_ovh_dns_records', 'domain', '${domain.domain}')">?? DNS</button>
            </td>
        </tr>
    `).join('');
}

function displayVPSList(vpsList) {
    const tbody = document.getElementById('vps-tbody');
    if (!tbody) return;
    if (!vpsList || vpsList.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Keine VPS gefunden</td></tr>';
        return;
    }
    tbody.innerHTML = vpsList.map(vps => `
        <tr>
            <td>${vps.name || 'N/A'}</td>
            <td>${vps.ips ? vps.ips.join(', ') : 'N/A'}</td>
            <td>${vps.mac_addresses ? Object.values(vps.mac_addresses).join(', ') : 'N/A'}</td>
            <td><span class="status-badge ${vps.state === 'running' ? 'status-running' : 'status-stopped'}">${vps.state || 'N/A'}</span></td>
            <td>${vps.cluster || 'N/A'}</td>
            <td class="action-buttons">
                <button class="btn btn-secondary" onclick="testEndpointWithParams('control_ovh_vps', {vps_name: '${vps.name}', vps_action: 'reboot'})">?? Reboot</button>
            </td>
        </tr>
    `).join('');
}

function displayActivityLog(logs) {
    const tbody = document.getElementById('logs-tbody');
    if (!tbody) return;
    if (!logs || logs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align: center;">Keine Log-Einträge gefunden</td></tr>';
        return;
    }
    tbody.innerHTML = logs.map(log => `
        <tr>
            <td>${new Date(log.created_at).toLocaleString('de-DE')}</td>
            <td>${log.action || 'N/A'}</td>
            <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;">${log.details || 'N/A'}</td>
            <td><span class="status-badge ${log.status === 'success' ? 'status-running' : 'status-stopped'}">${log.status || 'N/A'}</span></td>
        </tr>
    `).join('');
}

// Control Functions
async function controlVM(node, vmid, action) {
    if (!confirm(`Möchten Sie wirklich "${action}" für VM ${vmid} ausführen?`)) {
        return;
    }
    try {
        const result = await makeRequest('control_vm', { node, vmid, vm_action: action });
        if (result.success) {
            showNotification(`VM ${vmid} ${action} erfolgreich ausgeführt!`);
            setTimeout(() => { dataLoaded.vms = false; loadVMs(); }, 2000);
        } else {
            showNotification(`Fehler beim ${action} der VM: ` + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
}

async function deleteVM(node, vmid) {
    if (!confirm(`Möchten Sie VM ${vmid} wirklich PERMANENT löschen? Diese Aktion kann nicht rückgängig gemacht werden!`)) {
        return;
    }
    try {
        const result = await makeRequest('delete_vm', { node, vmid });
        if (result.success) {
            showNotification(`VM ${vmid} wurde erfolgreich gelöscht!`);
            dataLoaded.vms = false; loadVMs();
        } else {
            showNotification('Fehler beim Löschen der VM: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
}

async function deleteWebsite(domainId) {
    if (!confirm('Möchten Sie diese Website wirklich löschen?')) return;
    try {
        const result = await makeRequest('delete_website', { domain_id: domainId });
        if (result.success) {
            showNotification('Website wurde erfolgreich gelöscht!');
            dataLoaded.websites = false; loadWebsites();
        } else {
            showNotification('Fehler beim Löschen der Website: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
}

async function deleteDatabase(databaseId) {
    if (!confirm('Möchten Sie diese Datenbank wirklich löschen?')) return;
    try {
        const result = await makeRequest('delete_database', { database_id: databaseId });
        if (result.success) {
            showNotification('Datenbank wurde erfolgreich gelöscht!');
            dataLoaded.databases = false; loadDatabases();
        } else {
            showNotification('Fehler beim Löschen der Datenbank: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
}

async function deleteEmail(mailuserId) {
    if (!confirm('Möchten Sie diese E-Mail Adresse wirklich löschen?')) return;
    try {
        const result = await makeRequest('delete_email', { mailuser_id: mailuserId });
        if (result.success) {
            showNotification('E-Mail Adresse wurde erfolgreich gelöscht!');
            dataLoaded.emails = false; loadEmails();
        } else {
            showNotification('Fehler beim Löschen der E-Mail Adresse: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
}

// Search/Filter Function
function filterTable(tableId, searchValue) {
    const table = document.getElementById(tableId);
    if (!table) return;
    const tbody = table.querySelector('tbody');
    if (!tbody) return;
    const rows = tbody.querySelectorAll('tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchValue.toLowerCase()) ? '' : 'none';
    });
}

// Endpoint Testing Functions
async function testEndpoint(action) {
    try {
        const result = await makeRequest(action);
        displayEndpointResult(action, result);
    } catch (error) {
        displayEndpointResult(action, {success: false, error: error.message});
    }
}

async function testEndpointWithParam(action, paramName, paramValue) {
    try {
        const result = await makeRequest(action, { [paramName]: paramValue });
        displayEndpointResult(action, result);
    } catch (error) {
        displayEndpointResult(action, {success: false, error: error.message});
    }
}

async function testEndpointWithParams(action, params) {
    try {
        const result = await makeRequest(action, params);
        displayEndpointResult(action, result);
    } catch (error) {
        displayEndpointResult(action, {success: false, error: error.message});
    }
}

function displayEndpointResult(action, result) {
    const resultDiv = document.getElementById('endpoint-result');
    const responsePre = document.getElementById('endpoint-response');
    if (resultDiv && responsePre) {
        resultDiv.classList.remove('hidden');
        responsePre.textContent = `Action: ${action}\n\nResponse:\n${JSON.stringify(result, null, 2)}`;
    }
}

// Form Submission Functions
async function createVM(event) {
    event.preventDefault();
    const form = event.target;
    setLoading(form, true);
    try {
        const result = await makeRequest('create_vm', new FormData(form));
        if (result.success) {
            showNotification('VM wurde erfolgreich erstellt!');
            form.reset();
            if (!document.getElementById('admin').classList.contains('hidden')) {
                dataLoaded.vms = false; loadVMs();
            }
        } else {
            showNotification('Fehler beim Erstellen der VM: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
    setLoading(form, false);
}

async function createWebsite(event) {
    event.preventDefault();
    const form = event.target;
    setLoading(form, true);
    try {
        const result = await makeRequest('create_website', new FormData(form));
        if (result.success) {
            showNotification('Website wurde erfolgreich erstellt!');
            form.reset();
            if (!document.getElementById('admin').classList.contains('hidden')) {
                dataLoaded.websites = false; loadWebsites();
            }
        } else {
            showNotification('Fehler beim Erstellen der Website: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
    setLoading(form, false);
}

async function orderDomain(event) {
    event.preventDefault();
    const form = event.target;
    setLoading(form, true);
    try {
        const result = await makeRequest('order_domain', new FormData(form));
        if (result.success) {
            showNotification('Domain wurde erfolgreich bestellt!');
            form.reset();
            if (!document.getElementById('admin').classList.contains('hidden')) {
                dataLoaded.domains = false; loadDomains();
            }
        } else {
            showNotification('Fehler beim Bestellen der Domain: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
    setLoading(form, false);
}

async function getVPSInfo(event) {
    event.preventDefault();
    const form = event.target;
    setLoading(form, true);
    try {
        const result = await makeRequest('get_vps_info', new FormData(form));
        const vpsIpEl = document.getElementById('vps_ip');
        const vpsMacEl = document.getElementById('vps_mac');
        const vpsResultEl = document.getElementById('vps_result');

        if (result.success && result.data) {
            if (vpsIpEl) vpsIpEl.textContent = result.data.ip;
            if (vpsMacEl) vpsMacEl.textContent = result.data.mac;
            if (vpsResultEl) vpsResultEl.classList.remove('hidden');
            showNotification('VPS Informationen erfolgreich abgerufen!');
        } else {
            showNotification('Fehler beim Abrufen der VPS Informationen: ' + (result.error || 'Keine Daten gefunden'), 'error');
            if (vpsResultEl) vpsResultEl.classList.add('hidden');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
    setLoading(form, false);
}

async function updateVMNetwork(event) {
    event.preventDefault();
    const form = event.target;
    setLoading(form, true);
    try {
        const result = await makeRequest('update_vm_network', new FormData(form));
        if (result.success) {
            showNotification('VM Netzwerk wurde erfolgreich aktualisiert!');
            form.reset();
            if (!document.getElementById('admin').classList.contains('hidden')) {
                dataLoaded.vms = false; loadVMs();
            }
        } else {
            showNotification('Fehler beim Aktualisieren des VM Netzwerks: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
    setLoading(form, false);
}

async function createDatabase(event) {
    event.preventDefault();
    const form = event.target;
    setLoading(form, true);
    try {
        const result = await makeRequest('create_database', new FormData(form));
        if (result.success) {
            showNotification('Datenbank wurde erfolgreich erstellt!');
            form.reset();
            if (!document.getElementById('admin').classList.contains('hidden')) {
                dataLoaded.databases = false; loadDatabases();
            }
        } else {
            showNotification('Fehler beim Erstellen der Datenbank: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
    setLoading(form, false);
}

async function createEmail(event) {
    event.preventDefault();
    const form = event.target;
    setLoading(form, true);
    try {
        const result = await makeRequest('create_email', new FormData(form));
        if (result.success) {
            showNotification('E-Mail Adresse wurde erfolgreich erstellt!');
            form.reset();
            if (!document.getElementById('admin').classList.contains('hidden')) {
                dataLoaded.emails = false; loadEmails();
            }
        } else {
            showNotification('Fehler beim Erstellen der E-Mail Adresse: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
    setLoading(form, false);
}

// Zusätzliche Hilfsfunktionen
async function loadProxmoxNodes() {
    try {
        const result = await makeRequest('get_proxmox_nodes');
        if (result.success && result.data) {
            showNotification('Proxmox Nodes: ' + result.data.map(n => n.node).join(', '), 'success');
        } else {
            showNotification('Fehler beim Laden der Nodes: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
}

// =============================================================================
// VIRTUAL MAC FUNCTIONS (Ported from index.php and adapted)
// =============================================================================

// Virtual MAC Tab Management (within the main "Virtual MAC" tab)
function showVirtualMacTab(tabName, element) {
    document.querySelectorAll('.virtual-mac-tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    if (element && element.parentNode) {
        element.parentNode.querySelectorAll('.tab').forEach(tab => {
            tab.classList.remove('active');
        });
    }
    
    const tabContentEl = document.getElementById('virtual-mac-' + tabName);
    if (tabContentEl) {
        tabContentEl.classList.remove('hidden');
    }
    if (element) {
        element.classList.add('active');
    }
    
    // Load data for specific sub-tabs if not already loaded
    if (tabName === 'overview') {
        if (!dataLoaded.virtualMacOverview) loadVirtualMacOverview();
    } else if (tabName === 'create' || tabName === 'ip-management') {
        if (!dataLoaded.dedicatedServers) loadDedicatedServersForDropdown();
    }
}

// Load Virtual MACs for Admin Tab's table
async function loadVirtualMacs() {
    showLoadingForTab('virtual-macs');
    try {
        const result = await makeRequest('get_all_virtual_macs');
        if (result.success) {
            currentData.virtualMacs = result.data || [];
            displayVirtualMacsInTable(currentData.virtualMacs, 'virtual-macs-tbody');
            dataLoaded.virtualMacs = true;
            const el = document.getElementById('virtual-mac-count');
            if (el) el.textContent = currentData.virtualMacs.length;
        } else {
            showNotification('Fehler beim Laden der Virtual MACs (Admin): ' + (result.error || 'Unbekannter Fehler'), 'error');
            displayVirtualMacsInTable([], 'virtual-macs-tbody'); // Show empty table
        }
    } catch (error) {
        showNotification('Netzwerkfehler beim Laden der Virtual MACs (Admin)', 'error');
        displayVirtualMacsInTable([], 'virtual-macs-tbody');
    }
}

// Display Virtual MACs in a generic table (takes tbodyId)
function displayVirtualMacsInTable(virtualMacsData, tbodyId) {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) {
        console.error(`Cannot find tbody with ID: ${tbodyId}`);
        return;
    }
    if (!virtualMacsData || virtualMacsData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center;">Keine Virtual MACs gefunden</td></tr>';
        return;
    }
    
    tbody.innerHTML = virtualMacsData.map(vmac => `
        <tr>
            <td>${vmac.macAddress || 'N/A'}</td>
            <td>${vmac.ips && vmac.ips[0] ? vmac.ips[0].virtualMachineName || 'N/A' : 'N/A'}</td>
            <td>${vmac.ips && vmac.ips[0] ? vmac.ips[0].ipAddress || 'N/A' : 
                (vmac.ips && vmac.ips.length > 1 ? vmac.ips.map(ip => ip.ipAddress).join(', ') : 'N/A')}</td>
            <td>${vmac.serviceName || 'N/A'}</td>
            <td><span class="status-badge status-active">${vmac.type || 'N/A'}</span></td>
            <td>${vmac.created_at ? new Date(vmac.created_at).toLocaleDateString('de-DE') : 'N/A'}</td>
            <td class="action-buttons">
                <button class="btn btn-secondary btn-small" onclick="showVirtualMacDetails('${vmac.serviceName}', '${vmac.macAddress}')">?? Details</button>
                <button class="btn btn-danger btn-small" onclick="deleteVirtualMac('${vmac.serviceName}', '${vmac.macAddress}')">??? Löschen</button>
            </td>
        </tr>
    `).join('');
}

// Load Virtual MAC Overview data for the main "Virtual MAC" tab
async function loadVirtualMacOverview() {
    showLoadingForTab('virtual-mac-overview');
    try {
        const result = await makeRequest('get_all_virtual_macs');
        if (result.success) {
            currentData.virtualMacOverview = result.data || [];
            displayVirtualMacsInTable(currentData.virtualMacOverview, 'virtual-mac-overview-tbody');
            dataLoaded.virtualMacOverview = true;
            
            let totalMacs = currentData.virtualMacOverview.length;
            let totalIPs = 0;
            let servers = new Set();
            
            currentData.virtualMacOverview.forEach(vmac => {
                if (vmac.ips) {
                    totalIPs += vmac.ips.length;
                }
                if (vmac.serviceName) {
                    servers.add(vmac.serviceName);
                }
            });
            
            const totalMacsEl = document.getElementById('total-virtual-macs');
            const totalAssignedIpsEl = document.getElementById('total-assigned-ips');
            const totalDedicatedServersEl = document.getElementById('total-dedicated-servers');

            if (totalMacsEl) totalMacsEl.textContent = totalMacs;
            if (totalAssignedIpsEl) totalAssignedIpsEl.textContent = totalIPs;
            if (totalDedicatedServersEl) totalDedicatedServersEl.textContent = servers.size;

        } else {
            showNotification('Fehler beim Laden der Virtual MAC Übersicht: ' + (result.error || 'Unbekannter Fehler'), 'error');
            displayVirtualMacsInTable([], 'virtual-mac-overview-tbody');
        }
    } catch (error) {
        showNotification('Netzwerkfehler beim Laden der Virtual MAC Übersicht', 'error');
        displayVirtualMacsInTable([], 'virtual-mac-overview-tbody');
    }
}

// Load Dedicated Servers for dropdowns
async function loadDedicatedServersForDropdown() {
    try {
        const result = await makeRequest('get_dedicated_servers');
        if (result.success && result.data) {
            currentData.dedicatedServers = result.data || [];
            dataLoaded.dedicatedServers = true;
            const selects = [
                'vmac_service_name',        // For Create Virtual MAC form
                'vmac_ip_service_name',     // For Assign IP form
                'vmac_remove_service_name'  // For Remove IP form
            ];
            
            selects.forEach(selectId => {
                const select = document.getElementById(selectId);
                if (select) {
                    const currentValue = select.value; // Preserve selection if possible
                    select.innerHTML = '<option value="">Server auswählen...</option>';
                    currentData.dedicatedServers.forEach(server => {
                        select.innerHTML += `<option value="${server}" ${server === currentValue ? 'selected' : ''}>${server}</option>`;
                    });
                }
            });
        } else {
             showNotification('Fehler beim Laden der Dedicated Server Liste: ' + (result.error || 'Keine Daten'), 'error');
        }
    } catch (error) {
        console.error('Error loading dedicated servers:', error);
        showNotification('Netzwerkfehler beim Laden der Dedicated Server.', 'error');
    }
}
async function loadDedicatedServers() { // Used by button in admin virtual macs table
    if (!dataLoaded.dedicatedServers) {
       await loadDedicatedServersForDropdown();
    }
    if (currentData.dedicatedServers.length > 0) {
        showNotification('Dedicated Server: ' + currentData.dedicatedServers.join(', '), 'info');
    } else {
        showNotification('Keine Dedicated Server gefunden oder Fehler beim Laden.', 'warning');
    }
}


// Load Virtual MACs for a specific service (for dropdowns)
async function loadVirtualMacsForService(serviceName) {
    const selectMacEl = document.getElementById('vmac_ip_mac_address');
    if (!selectMacEl) return;

    if (!serviceName) {
        selectMacEl.innerHTML = '<option value="">Erst Service auswählen...</option>';
        return;
    }
    
    selectMacEl.innerHTML = '<option value="">Lade MACs...</option>';
    try {
        // Using get_virtual_mac_details without macAddress gives all MACs for that service
        const result = await makeRequest('get_virtual_mac_details', { service_name: serviceName });
        if (result.success && result.data) {
            selectMacEl.innerHTML = '<option value="">Virtual MAC auswählen...</option>';
            if (Array.isArray(result.data)) {
                result.data.forEach(vmac => {
                    selectMacEl.innerHTML += `<option value="${vmac.macAddress}">${vmac.macAddress} (${vmac.ips && vmac.ips[0] ? vmac.ips[0].virtualMachineName : 'Kein VM Name'})</option>`;
                });
            } else if (result.data.macAddress) { // Single MAC returned (should not happen for this call)
                 selectMacEl.innerHTML += `<option value="${result.data.macAddress}">${result.data.macAddress}</option>`;
            }
        } else {
            selectMacEl.innerHTML = '<option value="">Fehler oder keine MACs</option>';
            showNotification('Fehler beim Laden der Virtual MACs für Service: ' + (result.error || 'Keine Daten'), 'error');
        }
    } catch (error) {
        selectMacEl.innerHTML = '<option value="">Netzwerkfehler</option>';
        showNotification('Netzwerkfehler beim Laden der Virtual MACs für Service.', 'error');
    }
}

// Create Virtual MAC
async function createVirtualMac(event) {
    event.preventDefault();
    const form = event.target;
    setLoading(form, true);
    try {
        const result = await makeRequest('create_virtual_mac', new FormData(form));
        if (result.success) {
            showNotification('Virtual MAC wurde erfolgreich erstellt!');
            form.reset();
            dataLoaded.virtualMacOverview = false; // Force reload of overview
            loadVirtualMacOverview(); // Refresh overview table
            if(document.getElementById('admin-virtual-macs') && !document.getElementById('admin-virtual-macs').classList.contains('hidden')) {
                dataLoaded.virtualMacs = false; loadVirtualMacs(); // Refresh admin table if visible
            }
        } else {
            showNotification('Fehler beim Erstellen der Virtual MAC: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
    setLoading(form, false);
}

// Assign IP to Virtual MAC
async function assignIPToVirtualMac(event) {
    event.preventDefault();
    const form = event.target;
    setLoading(form, true);
    try {
        const result = await makeRequest('assign_ip_to_virtual_mac', new FormData(form));
        if (result.success) {
            showNotification('IP-Adresse wurde erfolgreich zugewiesen!');
            form.reset();
            const macSelect = form.querySelector('#vmac_ip_mac_address');
            if (macSelect) macSelect.innerHTML = '<option value="">Erst Service auswählen...</option>';

            dataLoaded.virtualMacOverview = false; 
            loadVirtualMacOverview();
             if(document.getElementById('admin-virtual-macs') && !document.getElementById('admin-virtual-macs').classList.contains('hidden')) {
                dataLoaded.virtualMacs = false; loadVirtualMacs();
            }
        } else {
            showNotification('Fehler beim Zuweisen der IP-Adresse: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
    setLoading(form, false);
}

// Remove IP from Virtual MAC
async function removeIPFromVirtualMac(event) {
    event.preventDefault();
    const form = event.target;
    if (!confirm('Möchten Sie die IP-Adresse wirklich von der Virtual MAC entfernen?')) {
        return;
    }
    setLoading(form, true);
    try {
        const result = await makeRequest('remove_ip_from_virtual_mac', new FormData(form));
        if (result.success) {
            showNotification('IP-Adresse wurde erfolgreich entfernt!');
            form.reset();
            dataLoaded.virtualMacOverview = false;
            loadVirtualMacOverview();
            if(document.getElementById('admin-virtual-macs') && !document.getElementById('admin-virtual-macs').classList.contains('hidden')) {
                dataLoaded.virtualMacs = false; loadVirtualMacs();
            }
        } else {
            showNotification('Fehler beim Entfernen der IP-Adresse: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
    setLoading(form, false);
}

// Create Reverse DNS
async function createReverseDNS(event) {
    event.preventDefault();
    const form = event.target;
    setLoading(form, true);
    try {
        const result = await makeRequest('create_reverse_dns', new FormData(form));
        if (result.success) {
            showNotification('Reverse DNS wurde erfolgreich erstellt!');
            form.reset();
        } else {
            showNotification('Fehler beim Erstellen des Reverse DNS: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
    setLoading(form, false);
}

// Query Reverse DNS
async function queryReverseDNS(event) {
    event.preventDefault();
    const form = event.target;
    setLoading(form, true);
    const resultBox = document.getElementById('reverse_dns_result');
    const contentBox = document.getElementById('reverse_dns_content');

    try {
        const result = await makeRequest('query_reverse_dns', new FormData(form));
        if (result.success) {
            if (contentBox) contentBox.textContent = JSON.stringify(result.data, null, 2);
            if (resultBox) resultBox.classList.remove('hidden');
            showNotification('Reverse DNS erfolgreich abgefragt!');
        } else {
            showNotification('Fehler beim Abfragen des Reverse DNS: ' + (result.error || 'Unbekannter Fehler'), 'error');
            if (resultBox) resultBox.classList.add('hidden');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
        if (resultBox) resultBox.classList.add('hidden');
    }
    setLoading(form, false);
}

// Delete Virtual MAC
async function deleteVirtualMac(serviceName, macAddress) {
    if (!confirm(`Möchten Sie die Virtual MAC ${macAddress} wirklich löschen?`)) {
        return;
    }
    try {
        const result = await makeRequest('delete_virtual_mac', { service_name: serviceName, mac_address: macAddress });
        if (result.success) {
            showNotification('Virtual MAC wurde erfolgreich gelöscht!');
            dataLoaded.virtualMacOverview = false; // Force reload
            loadVirtualMacOverview(); // Refresh overview table
            if(document.getElementById('admin-virtual-macs') && !document.getElementById('admin-virtual-macs').classList.contains('hidden')) {
                dataLoaded.virtualMacs = false; loadVirtualMacs(); // Refresh admin table if visible
            }
        } else {
            showNotification('Fehler beim Löschen der Virtual MAC: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
}

// Show Virtual MAC Details (displays in endpoint tester area)
async function showVirtualMacDetails(serviceName, macAddress) {
    try {
        const result = await makeRequest('get_virtual_mac_details', { service_name: serviceName, mac_address: macAddress });
        const endpointResponseEl = document.getElementById('endpoint-response');
        const endpointResultEl = document.getElementById('endpoint-result');

        if (result.success) {
            if (endpointResponseEl) endpointResponseEl.textContent = JSON.stringify(result.data, null, 2);
            if (endpointResultEl) endpointResultEl.classList.remove('hidden');
             // Scroll to the result
            if (endpointResultEl) endpointResultEl.scrollIntoView({ behavior: 'smooth' });
        } else {
            showNotification('Fehler beim Laden der Virtual MAC Details: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
}

// Helper to update a single stat value on the dashboard
function updateSingleStat(type, value) {
    const elementId = type.endsWith('-count') ? type : type + '-count'; // Ensure -count suffix
    const element = document.getElementById(elementId);
    if (element) {
        element.textContent = value;
    } else {
        console.warn(`Stat element not found: ${elementId}`);
    }
}


// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    startSessionHeartbeat();
    
    // Set default active tab to admin and load its stats
    // The actual content of the admin tab (e.g. VMs) will be lazy-loaded by showAdminTab
    const adminTabButton = document.querySelector('.tabs .tab[onclick*="showTab(\'admin\'"]');
    if (adminTabButton) {
        showTab('admin', adminTabButton); // This will call loadStatsOnly
    }
});

// Session-Heartbeat stoppen wenn Seite verlassen wird
window.addEventListener('beforeunload', function() {
    stopSessionHeartbeat();
});