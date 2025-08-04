// Globale Variablen
let currentData = {
    vms: [],
    websites: [],
    databases: [],
    emails: [],
    domains: [],
    vps: [],
    logs: []
};

// Track welche Daten bereits geladen wurden
let dataLoaded = {
    vms: false,
    websites: false,
    databases: false,
    emails: false,
    domains: false,
    vps: false,
    logs: false
};

// Session Management
let sessionHeartbeatInterval;

function startSessionHeartbeat() {
    // Heartbeat alle 2 Minuten senden
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
    
    document.getElementById(tabName).classList.remove('hidden');
    element.classList.add('active');
    
    // Bei Admin-Tab nur Stats laden, nicht alle Daten
    if (tabName === 'admin') {
        loadStatsOnly();
    }
}

function showAdminTab(tabName, element) {
    document.querySelectorAll('.admin-tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    element.parentNode.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    document.getElementById('admin-' + tabName).classList.remove('hidden');
    element.classList.add('active');
    
    // Lazy Loading: Daten nur laden wenn noch nicht geladen
    loadDataForTab(tabName);
}

// Lazy Loading Funktion
function loadDataForTab(tabName) {
    // Mapping von Tab-Namen zu Load-Funktionen
    const loadFunctions = {
        'vms': loadVMs,
        'websites': loadWebsites,
        'databases': loadDatabases,
        'emails': loadEmails,
        'domains': loadDomains,
        'vps-list': loadVPSList,
        'logs': loadActivityLog
    };
    
    // Prüfen ob Daten bereits geladen wurden
    if (!dataLoaded[tabName === 'vps-list' ? 'vps' : tabName]) {
        const loadFunction = loadFunctions[tabName];
        if (loadFunction) {
            // Loading-Indikator anzeigen
            showLoadingForTab(tabName);
            loadFunction();
        }
    }
}

// Loading-Indikator für einen Tab anzeigen
function showLoadingForTab(tabName) {
    const tableMapping = {
        'vms': 'vms-tbody',
        'websites': 'websites-tbody',
        'databases': 'databases-tbody',
        'emails': 'emails-tbody',
        'domains': 'domains-tbody',
        'vps-list': 'vps-tbody',
        'logs': 'logs-tbody'
    };
    
    const tbody = document.getElementById(tableMapping[tabName]);
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="10" style="text-align: center;"><div class="loading"></div> Lade Daten...</td></tr>';
    }
}

// Nur Statistiken laden (für Admin Dashboard)
async function loadStatsOnly() {
    try {
        // Parallele Requests für bessere Performance
        const promises = [
            makeRequest('get_all_vms'),
            makeRequest('get_all_websites'),
            makeRequest('get_all_databases'),
            makeRequest('get_all_emails'),
            makeRequest('get_all_domains'),
            makeRequest('get_all_vps')
        ];
        
        const results = await Promise.allSettled(promises);
        
        // Stats aktualisieren
        document.getElementById('vm-count').textContent = results[0].status === 'fulfilled' && results[0].value.success ? results[0].value.data.length : '0';
        document.getElementById('website-count').textContent = results[1].status === 'fulfilled' && results[1].value.success ? results[1].value.data.length : '0';
        document.getElementById('database-count').textContent = results[2].status === 'fulfilled' && results[2].value.success ? results[2].value.data.length : '0';
        document.getElementById('email-count').textContent = results[3].status === 'fulfilled' && results[3].value.success ? results[3].value.data.length : '0';
        document.getElementById('domain-count').textContent = results[4].status === 'fulfilled' && results[4].value.success ? results[4].value.data.length : '0';
        document.getElementById('vps-count').textContent = results[5].status === 'fulfilled' && results[5].value.success ? results[5].value.data.length : '0';
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
    const button = form.querySelector('button[type="submit"]');
    const spinner = button.querySelector('.loading');
    
    if (loading) {
        button.disabled = true;
        spinner.classList.remove('hidden');
    } else {
        button.disabled = false;
        spinner.classList.add('hidden');
    }
}

// API Request Handler
async function makeRequest(action, formData) {
    const data = new FormData();
    data.append('action', action);
    
    if (formData) {
        if (formData instanceof FormData) {
            for (const [key, value] of formData.entries()) {
                data.append(key, value);
            }
        } else {
            for (const [key, value] of Object.entries(formData)) {
                data.append(key, value);
            }
        }
    }
    
    try {
        const response = await fetch('', {
            method: 'POST',
            body: data
        });
        
        const result = await response.json();
        
        // Session-Expired Check
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

// Data Loading Functions with Lazy Loading
async function loadVMs() {
    try {
        const result = await makeRequest('get_all_vms');
        if (result.success) {
            currentData.vms = result.data;
            displayVMs(result.data);
            dataLoaded.vms = true;
            document.getElementById('vm-count').textContent = result.data.length;
        } else {
            showNotification('Fehler beim Laden der VMs: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler beim Laden der VMs', 'error');
    }
}

async function loadWebsites() {
    try {
        const result = await makeRequest('get_all_websites');
        if (result.success) {
            currentData.websites = result.data;
            displayWebsites(result.data);
            dataLoaded.websites = true;
            document.getElementById('website-count').textContent = result.data.length;
        } else {
            showNotification('Fehler beim Laden der Websites: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler beim Laden der Websites', 'error');
    }
}

async function loadDatabases() {
    try {
        const result = await makeRequest('get_all_databases');
        if (result.success) {
            currentData.databases = result.data;
            displayDatabases(result.data);
            dataLoaded.databases = true;
            document.getElementById('database-count').textContent = result.data.length;
        } else {
            showNotification('Fehler beim Laden der Datenbanken: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler beim Laden der Datenbanken', 'error');
    }
}

async function loadEmails() {
    try {
        const result = await makeRequest('get_all_emails');
        if (result.success) {
            currentData.emails = result.data;
            displayEmails(result.data);
            dataLoaded.emails = true;
            document.getElementById('email-count').textContent = result.data.length;
            
            if (result.warning) {
                showNotification(result.warning, 'warning');
            }
        } else {
            showNotification('Fehler beim Laden der E-Mail Accounts: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler beim Laden der E-Mail Accounts', 'error');
    }
}

async function loadDomains() {
    try {
        const result = await makeRequest('get_all_domains');
        if (result.success) {
            currentData.domains = result.data;
            displayDomains(result.data);
            dataLoaded.domains = true;
            document.getElementById('domain-count').textContent = result.data.length;
        } else {
            showNotification('Fehler beim Laden der Domains: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler beim Laden der Domains', 'error');
    }
}

async function loadVPSList() {
    try {
        const result = await makeRequest('get_all_vps');
        if (result.success) {
            currentData.vps = result.data;
            displayVPSList(result.data);
            dataLoaded.vps = true;
            document.getElementById('vps-count').textContent = result.data.length;
        } else {
            showNotification('Fehler beim Laden der VPS: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler beim Laden der VPS', 'error');
    }
}

async function loadActivityLog() {
    try {
        const result = await makeRequest('get_activity_log');
        if (result.success) {
            currentData.logs = result.data;
            displayActivityLog(result.data);
            dataLoaded.logs = true;
        } else {
            showNotification('Fehler beim Laden des Activity Logs: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler beim Laden des Activity Logs', 'error');
    }
}

// Display Functions
function displayVMs(vms) {
    const tbody = document.getElementById('vms-tbody');
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
                    `<button class="btn btn-warning" onclick="controlVM('${vm.node}', '${vm.vmid}', 'stop')">⏹️ Stop</button>
                     <button class="btn btn-secondary" onclick="controlVM('${vm.node}', '${vm.vmid}', 'suspend')">⏸️ Suspend</button>` :
                    `<button class="btn btn-success" onclick="controlVM('${vm.node}', '${vm.vmid}', 'start')">▶️ Start</button>`
                }
                <button class="btn btn-secondary" onclick="controlVM('${vm.node}', '${vm.vmid}', 'reset')">🔄 Reset</button>
                <button class="btn btn-danger" onclick="deleteVM('${vm.node}', '${vm.vmid}')">🗑️ Löschen</button>
            </td>
        </tr>
    `).join('');
}

function displayWebsites(websites) {
    const tbody = document.getElementById('websites-tbody');
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
                <button class="btn btn-danger" onclick="deleteWebsite('${site.domain_id}')">🗑️ Löschen</button>
            </td>
        </tr>
    `).join('');
}

function displayDatabases(databases) {
    const tbody = document.getElementById('databases-tbody');
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
                <button class="btn btn-danger" onclick="deleteDatabase('${db.database_id}')">🗑️ Löschen</button>
            </td>
        </tr>
    `).join('');
}

function displayEmails(emails) {
    const tbody = document.getElementById('emails-tbody');
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
                <button class="btn btn-danger" onclick="deleteEmail('${email.mailuser_id}')">🗑️ Löschen</button>
            </td>
        </tr>
    `).join('');
}

function displayDomains(domains) {
    const tbody = document.getElementById('domains-tbody');
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
                <button class="btn btn-secondary" onclick="testEndpointWithParam('get_ovh_dns_records', 'domain', '${domain.domain}')">📝 DNS</button>
            </td>
        </tr>
    `).join('');
}

function displayVPSList(vpsList) {
    const tbody = document.getElementById('vps-tbody');
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
                <button class="btn btn-secondary" onclick="testEndpointWithParams('control_ovh_vps', {vps_name: '${vps.name}', vps_action: 'reboot'})">🔄 Reboot</button>
            </td>
        </tr>
    `).join('');
}

function displayActivityLog(logs) {
    const tbody = document.getElementById('logs-tbody');
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
        const formData = new FormData();
        formData.append('node', node);
        formData.append('vmid', vmid);
        formData.append('vm_action', action);
        
        const result = await makeRequest('control_vm', formData);
        
        if (result.success) {
            showNotification(`VM ${vmid} ${action} erfolgreich ausgeführt!`);
            setTimeout(() => {
                dataLoaded.vms = false; // Force reload
                loadVMs();
            }, 2000);
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
        const formData = new FormData();
        formData.append('node', node);
        formData.append('vmid', vmid);
        
        const result = await makeRequest('delete_vm', formData);
        
        if (result.success) {
            showNotification(`VM ${vmid} wurde erfolgreich gelöscht!`);
            dataLoaded.vms = false; // Force reload
            loadVMs();
        } else {
            showNotification('Fehler beim Löschen der VM: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
}

async function deleteWebsite(domainId) {
    if (!confirm('Möchten Sie diese Website wirklich löschen?')) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('domain_id', domainId);
        
        const result = await makeRequest('delete_website', formData);
        
        if (result.success) {
            showNotification('Website wurde erfolgreich gelöscht!');
            dataLoaded.websites = false; // Force reload
            loadWebsites();
        } else {
            showNotification('Fehler beim Löschen der Website: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
}

async function deleteDatabase(databaseId) {
    if (!confirm('Möchten Sie diese Datenbank wirklich löschen?')) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('database_id', databaseId);
        
        const result = await makeRequest('delete_database', formData);
        
        if (result.success) {
            showNotification('Datenbank wurde erfolgreich gelöscht!');
            dataLoaded.databases = false; // Force reload
            loadDatabases();
        } else {
            showNotification('Fehler beim Löschen der Datenbank: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
}

async function deleteEmail(mailuserId) {
    if (!confirm('Möchten Sie diese E-Mail Adresse wirklich löschen?')) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('mailuser_id', mailuserId);
        
        const result = await makeRequest('delete_email', formData);
        
        if (result.success) {
            showNotification('E-Mail Adresse wurde erfolgreich gelöscht!');
            dataLoaded.emails = false; // Force reload
            loadEmails();
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
    const tbody = table.querySelector('tbody');
    const rows = tbody.querySelectorAll('tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(searchValue.toLowerCase())) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
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
        const params = {};
        params[paramName] = paramValue;
        const result = await makeRequest(action, params);
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
    document.getElementById('endpoint-result').classList.remove('hidden');
    document.getElementById('endpoint-response').textContent = 
        `Action: ${action}\n\nResponse:\n${JSON.stringify(result, null, 2)}`;
}

// Form Submission Functions
async function createVM(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    setLoading(form, true);
    
    try {
        const result = await makeRequest('create_vm', formData);
        
        if (result.success) {
            showNotification('VM wurde erfolgreich erstellt!');
            form.reset();
            // Nur VMs neu laden wenn Admin-Tab sichtbar ist
            if (!document.getElementById('admin').classList.contains('hidden')) {
                dataLoaded.vms = false;
                loadVMs();
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
    const formData = new FormData(form);
    
    setLoading(form, true);
    
    try {
        const result = await makeRequest('create_website', formData);
        
        if (result.success) {
            showNotification('Website wurde erfolgreich erstellt!');
            form.reset();
            if (!document.getElementById('admin').classList.contains('hidden')) {
                dataLoaded.websites = false;
                loadWebsites();
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
    const formData = new FormData(form);
    
    setLoading(form, true);
    
    try {
        const result = await makeRequest('order_domain', formData);
        
        if (result.success) {
            showNotification('Domain wurde erfolgreich bestellt!');
            form.reset();
            if (!document.getElementById('admin').classList.contains('hidden')) {
                dataLoaded.domains = false;
                loadDomains();
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
    const formData = new FormData(form);
    
    setLoading(form, true);
    
    try {
        const result = await makeRequest('get_vps_info', formData);
        
        if (result.success && result.data) {
            document.getElementById('vps_ip').textContent = result.data.ip;
            document.getElementById('vps_mac').textContent = result.data.mac;
            document.getElementById('vps_result').classList.remove('hidden');
            showNotification('VPS Informationen erfolgreich abgerufen!');
        } else {
            showNotification('Fehler beim Abrufen der VPS Informationen: ' + (result.error || 'Keine Daten gefunden'), 'error');
            document.getElementById('vps_result').classList.add('hidden');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
    
    setLoading(form, false);
}

async function updateVMNetwork(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    setLoading(form, true);
    
    try {
        const result = await makeRequest('update_vm_network', formData);
        
        if (result.success) {
            showNotification('VM Netzwerk wurde erfolgreich aktualisiert!');
            form.reset();
            if (!document.getElementById('admin').classList.contains('hidden')) {
                dataLoaded.vms = false;
                loadVMs();
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
    const formData = new FormData(form);
    
    setLoading(form, true);
    
    try {
        const result = await makeRequest('create_database', formData);
        
        if (result.success) {
            showNotification('Datenbank wurde erfolgreich erstellt!');
            form.reset();
            if (!document.getElementById('admin').classList.contains('hidden')) {
                dataLoaded.databases = false;
                loadDatabases();
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
    const formData = new FormData(form);
    
    setLoading(form, true);
    
    try {
        const result = await makeRequest('create_email', formData);
        
        if (result.success) {
            showNotification('E-Mail Adresse wurde erfolgreich erstellt!');
            form.reset();
            if (!document.getElementById('admin').classList.contains('hidden')) {
                dataLoaded.emails = false;
                loadEmails();
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
        
        if (result.success) {
            showNotification('Proxmox Nodes: ' + result.data.map(n => n.node).join(', '), 'success');
        } else {
            showNotification('Fehler beim Laden der Nodes: ' + (result.error || 'Unbekannter Fehler'), 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler: ' + error.message, 'error');
    }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Session-Heartbeat starten
    startSessionHeartbeat();
    
    // Nur Stats laden wenn Admin-Tab sichtbar ist
    if (!document.getElementById('admin').classList.contains('hidden')) {
        loadStatsOnly();
    }
});

// Session-Heartbeat stoppen wenn Seite verlassen wird
window.addEventListener('beforeunload', function() {
    stopSessionHeartbeat();
});