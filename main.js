// [Das JavaScript bleibt größtenteils gleich, nur kleine Ergänzungen für die neuen Endpoints]
        
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
            
            if (tabName === 'admin') {
                loadAllData();
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
            
            const response = await fetch('', {
                method: 'POST',
                body: data
            });
            
            return response.json();
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
        
        // [Rest of the JavaScript functions remain the same...]
        // Data Loading Functions
        async function loadAllData() {
            updateStats();
            loadVMs();
            loadWebsites();
            loadDatabases();
            loadEmails();
            loadDomains();
            loadVPSList();
            loadActivityLog();
        }
        
        async function updateStats() {
            try {
                const [vms, websites, databases, emails, domains, vps] = await Promise.all([
                    makeRequest('get_all_vms'),
                    makeRequest('get_all_websites'),
                    makeRequest('get_all_databases'),
                    makeRequest('get_all_emails'),
                    makeRequest('get_all_domains'),
                    makeRequest('get_all_vps')
                ]);
                
                document.getElementById('vm-count').textContent = vms.data ? vms.data.length : 0;
                document.getElementById('website-count').textContent = websites.data ? websites.data.length : 0;
                document.getElementById('database-count').textContent = databases.data ? databases.data.length : 0;
                document.getElementById('email-count').textContent = emails.data ? emails.data.length : 0;
                document.getElementById('domain-count').textContent = domains.data ? domains.data.length : 0;
                document.getElementById('vps-count').textContent = vps.data ? vps.data.length : 0;
            } catch (error) {
                console.error('Fehler beim Laden der Statistiken:', error);
            }
        }
        
        async function loadVMs() {
            try {
                const result = await makeRequest('get_all_vms');
                if (result.success) {
                    currentData.vms = result.data;
                    displayVMs(result.data);
                } else {
                    showNotification('Fehler beim Laden der VMs', 'error');
                }
            } catch (error) {
                showNotification('Netzwerkfehler beim Laden der VMs', 'error');
            }
        }
        
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
        
        async function loadWebsites() {
            try {
                const result = await makeRequest('get_all_websites');
                if (result.success) {
                    currentData.websites = result.data;
                    displayWebsites(result.data);
                } else {
                    showNotification('Fehler beim Laden der Websites', 'error');
                }
            } catch (error) {
                showNotification('Netzwerkfehler beim Laden der Websites', 'error');
            }
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
        
        async function loadDatabases() {
            try {
                const result = await makeRequest('get_all_databases');
                if (result.success) {
                    currentData.databases = result.data;
                    displayDatabases(result.data);
                } else {
                    showNotification('Fehler beim Laden der Datenbanken', 'error');
                }
            } catch (error) {
                showNotification('Netzwerkfehler beim Laden der Datenbanken', 'error');
            }
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
        
        async function loadEmails() {
            try {
                const result = await makeRequest('get_all_emails');
                if (result.success) {
                    currentData.emails = result.data;
                    displayEmails(result.data);
                } else {
                    showNotification('Fehler beim Laden der E-Mail Accounts', 'error');
                }
            } catch (error) {
                showNotification('Netzwerkfehler beim Laden der E-Mail Accounts', 'error');
            }
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
        
        async function loadDomains() {
            try {
                const result = await makeRequest('get_all_domains');
                if (result.success) {
                    currentData.domains = result.data;
                    displayDomains(result.data);
                } else {
                    showNotification('Fehler beim Laden der Domains', 'error');
                }
            } catch (error) {
                showNotification('Netzwerkfehler beim Laden der Domains', 'error');
            }
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
        
        async function loadVPSList() {
            try {
                const result = await makeRequest('get_all_vps');
                if (result.success) {
                    currentData.vps = result.data;
                    displayVPSList(result.data);
                } else {
                    showNotification('Fehler beim Laden der VPS', 'error');
                }
            } catch (error) {
                showNotification('Netzwerkfehler beim Laden der VPS', 'error');
            }
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
        
        async function loadActivityLog() {
            try {
                const result = await makeRequest('get_activity_log');
                if (result.success) {
                    currentData.logs = result.data;
                    displayActivityLog(result.data);
                } else {
                    showNotification('Fehler beim Laden des Activity Logs', 'error');
                }
            } catch (error) {
                showNotification('Netzwerkfehler beim Laden des Activity Logs', 'error');
            }
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
                    setTimeout(() => loadVMs(), 2000);
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
        
        // Original Creation Functions
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
                    if (!document.getElementById('admin').classList.contains('hidden')) {
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
        
        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            if (!document.getElementById('admin').classList.contains('hidden')) {
                loadAllData();
            }
        });