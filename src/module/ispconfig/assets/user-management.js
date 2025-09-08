/**
 * ISPConfig User Management JavaScript
 * Erweiterte Funktionen für das Benutzer-Management
 */

// Globale ISPConfigModule-Initialisierung
if (typeof window.ISPConfigModule === 'undefined') {
    window.ISPConfigModule = {
        allUsers: [],
        filteredUsers: [],
        currentUserDetails: null
    };
}

// Tab-Management
function switchTab(tabName) {
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
    event.target.classList.add('active');
    
    // Benutzer-Tab spezielle Behandlung
    if (tabName === 'users') {
        loadAllUsers();
    }
}

// Benutzer-Tab-Management
function switchUserTab(tabName) {
    // Alle User-Tab-Inhalte verstecken
    document.querySelectorAll('#user-details-modal .tab-content').forEach(tab => {
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
    if (window.ISPConfigModule && window.ISPConfigModule.currentUserDetails) {
        loadUserTabData(tabName, window.ISPConfigModule.currentUserDetails.client_id);
    }
}

/**
 * Alle Benutzer laden
 */
async function loadAllUsers() {
    const loadingElement = document.getElementById('users-loading');
    const tbodyElement = document.getElementById('users-tbody');
    const noUsersElement = document.getElementById('no-users');
    
    if (loadingElement) loadingElement.style.display = 'block';
    if (tbodyElement) tbodyElement.innerHTML = '';
    if (noUsersElement) noUsersElement.style.display = 'none';
    
    try {
        const response = await ModuleManager.makeRequest('ispconfig', 'get_all_users');
        
        if (response.success) {
            window.ISPConfigModule.allUsers = response.data || [];
            window.ISPConfigModule.filteredUsers = [...window.ISPConfigModule.allUsers];
            displayUsers();
        } else {
            showError('Fehler beim Laden der Benutzer: ' + response.error);
        }
    } catch (error) {
        showError('Fehler beim Laden der Benutzer: ' + error.message);
    } finally {
        if (loadingElement) loadingElement.style.display = 'none';
    }
}

/**
 * Benutzer in der Tabelle anzeigen
 */
function displayUsers() {
    const tbodyElement = document.getElementById('users-tbody');
    const noUsersElement = document.getElementById('no-users');
    
    if (!tbodyElement) return;
    
    if (!window.ISPConfigModule || !window.ISPConfigModule.filteredUsers || window.ISPConfigModule.filteredUsers.length === 0) {
        tbodyElement.innerHTML = '';
        if (noUsersElement) noUsersElement.style.display = 'block';
        return;
    }
    
    if (noUsersElement) noUsersElement.style.display = 'none';
    
    tbodyElement.innerHTML = window.ISPConfigModule.filteredUsers.map(user => `
        <tr>
            <td>${user.client_id}</td>
            <td>${user.company_name || '-'}</td>
            <td>${user.contact_name || '-'}</td>
            <td>${user.email || '-'}</td>
            <td>
                <span class="badge ${user.active === 'y' ? 'badge-success' : 'badge-danger'}">
                    ${user.active === 'y' ? 'Aktiv' : 'Inaktiv'}
                </span>
            </td>
            <td>
                <span class="badge badge-info">${user.websites_count}</span>
            </td>
            <td>
                <span class="badge badge-info">${user.email_accounts_count}</span>
            </td>
            <td>
                <span class="badge badge-info">${user.databases_count}</span>
            </td>
            <td>
                <span class="badge badge-info">${user.ftp_users_count}</span>
            </td>
            <td>${formatDate(user.created_at)}</td>
            <td>
                <button class="btn btn-sm btn-primary" onclick="showUserDetails(${user.client_id})">
                    <i class="icon">👁️</i>
                </button>
                <button class="btn btn-sm btn-${user.active === 'y' ? 'warning' : 'success'}" 
                        onclick="updateUserStatus(${user.client_id}, '${user.active === 'y' ? 'n' : 'y'}')">
                    <i class="icon">${user.active === 'y' ? '⏸️' : '▶️'}</i>
                </button>
            </td>
        </tr>
    `).join('');
}

/**
 * Benutzer filtern
 */
function filterUsers() {
    const searchTerm = document.getElementById('user-search')?.value.toLowerCase() || '';
    const statusFilter = document.getElementById('user-status-filter')?.value || '';
    
    window.ISPConfigModule.filteredUsers = window.ISPConfigModule.allUsers.filter(user => {
        const matchesSearch = !searchTerm || 
            user.company_name?.toLowerCase().includes(searchTerm) ||
            user.contact_name?.toLowerCase().includes(searchTerm) ||
            user.email?.toLowerCase().includes(searchTerm);
        
        const matchesStatus = !statusFilter || user.active === statusFilter;
        
        return matchesSearch && matchesStatus;
    });
    
    displayUsers();
}

/**
 * Benutzer sortieren
 */
function sortUsers() {
    const sortBy = document.getElementById('user-sort-filter')?.value || 'company_name';
    
    window.ISPConfigModule.filteredUsers.sort((a, b) => {
        const aVal = a[sortBy] || '';
        const bVal = b[sortBy] || '';
        
        if (sortBy === 'created_at') {
            return new Date(bVal) - new Date(aVal);
        }
        
        return aVal.localeCompare(bVal);
    });
    
    displayUsers();
}

/**
 * Filter zurücksetzen
 */
function clearFilters() {
    const searchInput = document.getElementById('user-search');
    const statusFilter = document.getElementById('user-status-filter');
    const sortFilter = document.getElementById('user-sort-filter');
    
    if (searchInput) searchInput.value = '';
    if (statusFilter) statusFilter.value = '';
    if (sortFilter) sortFilter.value = 'company_name';
    
    window.ISPConfigModule.filteredUsers = [...window.ISPConfigModule.allUsers];
    displayUsers();
}

/**
 * Benutzer-Details anzeigen
 */
async function showUserDetails(clientId) {
    const modal = document.getElementById('user-details-modal');
    const title = document.getElementById('user-details-title');
    
    if (modal) modal.style.display = 'block';
    if (title) title.textContent = `Benutzer-Details #${clientId}`;
    
    try {
        // Benutzer-Details laden
        const response = await ModuleManager.makeRequest('ispconfig', 'get_user_details', { client_id: clientId });
        
        if (response.success) {
            window.ISPConfigModule.currentUserDetails = response.data;
            displayUserInfo(response.data);
            loadUserTabData('websites', clientId);
        } else {
            showError('Fehler beim Laden der Benutzer-Details: ' + response.error);
        }
    } catch (error) {
        showError('Fehler beim Laden der Benutzer-Details: ' + error.message);
    }
}

/**
 * Benutzer-Informationen anzeigen
 */
function displayUserInfo(user) {
    const userInfoElement = document.getElementById('user-info');
    const userStatsElement = document.getElementById('user-stats');
    
    if (userInfoElement) {
        userInfoElement.innerHTML = `
            <div class="row">
                <div class="col-6"><strong>Firma:</strong></div>
                <div class="col-6">${user.company_name || '-'}</div>
            </div>
            <div class="row">
                <div class="col-6"><strong>Kontakt:</strong></div>
                <div class="col-6">${user.contact_name || '-'}</div>
            </div>
            <div class="row">
                <div class="col-6"><strong>E-Mail:</strong></div>
                <div class="col-6">${user.email || '-'}</div>
            </div>
            <div class="row">
                <div class="col-6"><strong>Telefon:</strong></div>
                <div class="col-6">${user.phone || '-'}</div>
            </div>
            <div class="row">
                <div class="col-6"><strong>Adresse:</strong></div>
                <div class="col-6">${user.street || '-'}</div>
            </div>
            <div class="row">
                <div class="col-6"><strong>Stadt:</strong></div>
                <div class="col-6">${user.city || '-'}</div>
            </div>
            <div class="row">
                <div class="col-6"><strong>PLZ:</strong></div>
                <div class="col-6">${user.zip || '-'}</div>
            </div>
            <div class="row">
                <div class="col-6"><strong>Land:</strong></div>
                <div class="col-6">${user.country || '-'}</div>
            </div>
            <div class="row">
                <div class="col-6"><strong>Status:</strong></div>
                <div class="col-6">
                    <span class="badge ${user.active === 'y' ? 'badge-success' : 'badge-danger'}">
                        ${user.active === 'y' ? 'Aktiv' : 'Inaktiv'}
                    </span>
                </div>
            </div>
            <div class="row">
                <div class="col-6"><strong>Erstellt:</strong></div>
                <div class="col-6">${formatDate(user.created_at)}</div>
            </div>
        `;
    }
    
    if (userStatsElement) {
        userStatsElement.innerHTML = `
            <div class="row">
                <div class="col-6"><strong>Websites:</strong></div>
                <div class="col-6"><span class="badge badge-info">${user.websites_count || 0}</span></div>
            </div>
            <div class="row">
                <div class="col-6"><strong>E-Mail-Konten:</strong></div>
                <div class="col-6"><span class="badge badge-info">${user.email_accounts_count || 0}</span></div>
            </div>
            <div class="row">
                <div class="col-6"><strong>Datenbanken:</strong></div>
                <div class="col-6"><span class="badge badge-info">${user.databases_count || 0}</span></div>
            </div>
            <div class="row">
                <div class="col-6"><strong>FTP-Benutzer:</strong></div>
                <div class="col-6"><span class="badge badge-info">${user.ftp_users_count || 0}</span></div>
            </div>
        `;
    }
}

/**
 * Benutzer-Tab-Daten laden
 */
async function loadUserTabData(tabName, clientId) {
    let endpoint = '';
    
    switch (tabName) {
        case 'websites':
            endpoint = 'get_user_websites';
            break;
        case 'emails':
            endpoint = 'get_user_email_accounts';
            break;
        case 'databases':
            endpoint = 'get_user_databases';
            break;
        case 'ftp':
            endpoint = 'get_user_ftp_users';
            break;
    }
    
    if (!endpoint) return;
    
    try {
        const response = await ModuleManager.makeRequest('ispconfig', endpoint, { client_id: clientId });
        
        if (response.success) {
            displayUserTabData(tabName, response.data);
        } else {
            showError(`Fehler beim Laden der ${tabName}: ` + response.error);
        }
    } catch (error) {
        showError(`Fehler beim Laden der ${tabName}: ` + error.message);
    }
}

/**
 * Benutzer-Tab-Daten anzeigen
 */
function displayUserTabData(tabName, data) {
    const tbodyElement = document.getElementById(`user-${tabName}-tbody`);
    
    if (!tbodyElement) return;
    
    if (!data || data.length === 0) {
        tbodyElement.innerHTML = '<tr><td colspan="10" class="text-center">Keine Daten vorhanden</td></tr>';
        return;
    }
    
    switch (tabName) {
        case 'websites':
            tbodyElement.innerHTML = data.map(website => `
                <tr>
                    <td>${website.domain}</td>
                    <td>${website.ip_address}</td>
                    <td>${website.hd_quota} MB</td>
                    <td>${website.traffic_quota} MB</td>
                    <td>
                        <span class="badge ${website.ssl_enabled === 'y' ? 'badge-success' : 'badge-secondary'}">
                            ${website.ssl_enabled === 'y' ? 'SSL' : 'No SSL'}
                        </span>
                    </td>
                    <td>${website.php_version}</td>
                    <td>
                        <span class="badge ${website.active === 'y' ? 'badge-success' : 'badge-danger'}">
                            ${website.active === 'y' ? 'Aktiv' : 'Inaktiv'}
                        </span>
                    </td>
                </tr>
            `).join('');
            break;
            
        case 'emails':
            tbodyElement.innerHTML = data.map(email => `
                <tr>
                    <td>${email.email}</td>
                    <td>${email.login}</td>
                    <td>${email.quota || '-'} MB</td>
                    <td>
                        <span class="badge ${email.spamfilter === 'y' ? 'badge-success' : 'badge-secondary'}">
                            ${email.spamfilter === 'y' ? 'Aktiv' : 'Inaktiv'}
                        </span>
                    </td>
                    <td>
                        <span class="badge ${email.active === 'y' ? 'badge-success' : 'badge-danger'}">
                            ${email.active === 'y' ? 'Aktiv' : 'Inaktiv'}
                        </span>
                    </td>
                </tr>
            `).join('');
            break;
            
        case 'databases':
            tbodyElement.innerHTML = data.map(db => `
                <tr>
                    <td>${db.database_name}</td>
                    <td>${db.database_user}</td>
                    <td>${db.database_type || 'MySQL'}</td>
                    <td>
                        <span class="badge ${db.active === 'y' ? 'badge-success' : 'badge-danger'}">
                            ${db.active === 'y' ? 'Aktiv' : 'Inaktiv'}
                        </span>
                    </td>
                </tr>
            `).join('');
            break;
            
        case 'ftp':
            tbodyElement.innerHTML = data.map(ftp => `
                <tr>
                    <td>${ftp.username}</td>
                    <td>${ftp.quota_size || '-'} MB</td>
                    <td>${ftp.dir || '-'}</td>
                    <td>
                        <span class="badge ${ftp.active === 'y' ? 'badge-success' : 'badge-danger'}">
                            ${ftp.active === 'y' ? 'Aktiv' : 'Inaktiv'}
                        </span>
                    </td>
                </tr>
            `).join('');
            break;
    }
}

/**
 * Benutzer-Status umschalten
 */
async function toggleUserStatus(clientId, newStatus) {
    const statusText = newStatus === 'y' ? 'aktivieren' : 'deaktivieren';
    
    if (!confirm(`Möchten Sie diesen Benutzer wirklich ${statusText}?`)) {
        return;
    }
    
    try {
        const response = await ModuleManager.makeRequest('ispconfig', 'update_user_status', {
            client_id: clientId,
            status: newStatus
        });
        
        if (response.success) {
            showSuccess(`Benutzer erfolgreich ${statusText === 'aktivieren' ? 'aktiviert' : 'deaktiviert'}`);
            loadAllUsers(); // Liste aktualisieren
        } else {
            showError('Fehler beim Aktualisieren des Benutzer-Status: ' + response.error);
        }
    } catch (error) {
        showError('Fehler beim Aktualisieren des Benutzer-Status: ' + error.message);
    }
}

/**
 * Benutzer-Modal schließen
 */
function closeUserModal() {
    const modal = document.getElementById('user-details-modal');
    if (modal) {
        modal.style.display = 'none';
        window.ISPConfigModule.currentUserDetails = null;
    }
}

/**
 * Utility-Funktionen
 */
function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('de-DE');
}

function showError(message) {
    alert('Fehler: ' + message);
}

function showSuccess(message) {
    alert('Erfolg: ' + message);
}

// Event-Listener für Benutzer-Management
document.addEventListener('DOMContentLoaded', function() {
    // Refresh-Button
    const refreshBtn = document.getElementById('refresh-users');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', async () => {
            try {
                await loadAllUsers();
            } catch (error) {
                console.error('Error loading users:', error);
            }
        });
    }
    
    // Such-Filter
    const searchInput = document.getElementById('user-search');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            filterUsers();
        });
    }
    
    // Status-Filter
    const statusFilter = document.getElementById('status-filter');
    if (statusFilter) {
        statusFilter.addEventListener('change', (e) => {
            filterUsers();
        });
    }
    
    // Sort-Filter
    const sortFilter = document.getElementById('sort-filter');
    if (sortFilter) {
        sortFilter.addEventListener('change', (e) => {
            sortUsers();
        });
    }
    
    // Filter zurücksetzen
    const clearFiltersBtn = document.getElementById('clear-user-filters');
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', () => {
            clearFilters();
        });
    }
    
    // Modal schließen bei Klick außerhalb
    window.addEventListener('click', (e) => {
        const modal = document.getElementById('user-details-modal');
        if (e.target === modal) {
            closeUserModal();
        }
    });
});

// Benutzer-Status aktualisieren
async function updateUserStatus(userId, newStatus) {
    const statusData = {
        action: 'update_user_status',
        user_id: userId,
        status: newStatus
    };
    
    try {
        const response = await ModuleManager.makeRequest('ispconfig', 'update_user_status', statusData);
        
        if (response.success) {
            showSuccess('Benutzer-Status erfolgreich aktualisiert');
            await loadAllUsers(); // Tabelle aktualisieren
        } else {
            showError('Fehler beim Aktualisieren des Benutzer-Status: ' + response.message);
        }
    } catch (error) {
        console.error('Fehler beim Aktualisieren des Benutzer-Status:', error);
        showError('Netzwerkfehler beim Aktualisieren des Benutzer-Status');
    }
}

// Globale Funktionen für Template-Zugriff
window.loadAllUsers = loadAllUsers;
window.showUserDetails = showUserDetails;
window.closeUserModal = closeUserModal;
window.switchUserTab = switchUserTab;
window.loadUserTabData = loadUserTabData;
window.updateUserStatus = updateUserStatus;
