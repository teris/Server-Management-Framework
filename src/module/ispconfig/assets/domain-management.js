/**
 * ISPConfig Domain Management JavaScript
 * Verwaltet Domain-Zuordnungen und -Einstellungen mit zweistufigem Bestätigungsprozess
 */

// Verwende den ISPConfig Module Namespace
// Globale Variablen sind bereits in module.js definiert

// Tab-Management erweitern
const originalSwitchTab = window.switchTab;
window.switchTab = function(tabName) {
    if (originalSwitchTab) {
        originalSwitchTab(tabName);
    }
    
    // Domain-Tab spezielle Behandlung
    if (tabName === 'domains') {
        loadAllDomains();
    }
};

/**
 * Alle Domains laden
 */
async function loadAllDomains() {
    const loadingElement = document.getElementById('domains-loading');
    const tbodyElement = document.getElementById('domains-tbody');
    const noDomainsElement = document.getElementById('no-domains');
    
    if (loadingElement) loadingElement.style.display = 'block';
    if (tbodyElement) tbodyElement.innerHTML = '';
    if (noDomainsElement) noDomainsElement.style.display = 'none';
    
    try {
        const response = await ModuleManager.makeRequest('ispconfig', 'get_all_domains');
        
        if (response.success) {
            ISPConfigModule.allDomains = response.data || [];
            ISPConfigModule.filteredDomains = [...ISPConfigModule.allDomains];
            displayDomains();
            updateBulkActionsButton();
        } else {
            showError('Fehler beim Laden der Domains: ' + response.error);
        }
    } catch (error) {
        showError('Fehler beim Laden der Domains: ' + error.message);
    } finally {
        if (loadingElement) loadingElement.style.display = 'none';
    }
}

/**
 * Domains in der Tabelle anzeigen
 */
function displayDomains() {
    const tbodyElement = document.getElementById('domains-tbody');
    const noDomainsElement = document.getElementById('no-domains');
    
    if (!tbodyElement) return;
    
    if (ISPConfigModule.filteredDomains.length === 0) {
        tbodyElement.innerHTML = '';
        if (noDomainsElement) noDomainsElement.style.display = 'block';
        return;
    }
    
    if (noDomainsElement) noDomainsElement.style.display = 'none';
    
    tbodyElement.innerHTML = ISPConfigModule.filteredDomains.map(domain => `
        <tr>
            <td>
                <input type="checkbox" class="domain-checkbox" value="${domain.domain_id}" 
                       onchange="updateBulkActionsButton()">
            </td>
            <td><a href="#" onclick="showDnsManagement('${domain.domain}')" title="DNS-Einträge verwalten">${domain.domain}</a></td>
            <td>${domain.ip_address}</td>
            <td>
                ${domain.assigned_user ? `
                    <div class="user-info">
                        <strong>${domain.assigned_user.company_name || domain.assigned_user.contact_name}</strong><br>
                        <small>${domain.assigned_user.email}</small>
                    </div>
                ` : '<span class="text-muted">Nicht zugewiesen</span>'}
            </td>
            <td>${domain.hd_quota} MB</td>
            <td>${domain.traffic_quota} MB</td>
            <td>
                <span class="badge ${domain.ssl_enabled === 'y' ? 'badge-success' : 'badge-secondary'}">
                    ${domain.ssl_enabled === 'y' ? 'SSL' : 'No SSL'}
                </span>
            </td>
            <td>${domain.php_version}</td>
            <td>
                <span class="badge ${domain.active === 'y' ? 'badge-success' : 'badge-danger'}">
                    ${domain.active === 'y' ? 'Aktiv' : 'Inaktiv'}
                </span>
            </td>
            <td>${formatDate(domain.created_at)}</td>
            <td>
                <div class="btn-group" role="group">
                    <button class="btn btn-sm btn-primary" onclick="showDomainAssignmentModal(${domain.domain_id})" 
                            title="Benutzer zuweisen">
                        <i class="icon">👤</i>
                    </button>
                    <button class="btn btn-sm btn-secondary" onclick="showDomainSettingsModal(${domain.domain_id})" 
                            title="Einstellungen">
                        <i class="icon">⚙️</i>
                    </button>
                    ${domain.assigned_user ? `
                        <button class="btn btn-sm btn-warning" onclick="unassignDomain(${domain.domain_id})" 
                                title="Zuweisung entfernen">
                            <i class="icon">❌</i>
                        </button>
                    ` : ''}
                </div>
            </td>
        </tr>
    `).join('');
}

/**
 * Domain filtern
 */
function filterDomains() {
    const searchTerm = document.getElementById('domain-search')?.value.toLowerCase() || '';
    const statusFilter = document.getElementById('domain-status-filter')?.value || '';
    const assignmentFilter = document.getElementById('domain-assignment-filter')?.value || '';
    
    ISPConfigModule.filteredDomains = ISPConfigModule.allDomains.filter(domain => {
        const matchesSearch = !searchTerm || 
            domain.domain.toLowerCase().includes(searchTerm) ||
            domain.ip_address.toLowerCase().includes(searchTerm) ||
            (domain.assigned_user && (
                domain.assigned_user.company_name?.toLowerCase().includes(searchTerm) ||
                domain.assigned_user.contact_name?.toLowerCase().includes(searchTerm) ||
                domain.assigned_user.email?.toLowerCase().includes(searchTerm)
            ));
        
        const matchesStatus = !statusFilter || domain.active === statusFilter;
        
        const matchesAssignment = !assignmentFilter || 
            (assignmentFilter === 'assigned' && domain.assigned_user) ||
            (assignmentFilter === 'unassigned' && !domain.assigned_user);
        
        return matchesSearch && matchesStatus && matchesAssignment;
    });
    
    displayDomains();
}

/**
 * Domains sortieren
 */
function sortDomains() {
    const sortBy = document.getElementById('domain-sort-filter')?.value || 'domain';
    
    ISPConfigModule.filteredDomains.sort((a, b) => {
        let aVal, bVal;
        
        switch (sortBy) {
            case 'domain':
                aVal = a.domain;
                bVal = b.domain;
                break;
            case 'assigned_user':
                aVal = a.assigned_user?.company_name || a.assigned_user?.contact_name || '';
                bVal = b.assigned_user?.company_name || b.assigned_user?.contact_name || '';
                break;
            case 'created_at':
                aVal = new Date(a.created_at);
                bVal = new Date(b.created_at);
                return bVal - aVal; // Neueste zuerst
            default:
                aVal = a[sortBy] || '';
                bVal = b[sortBy] || '';
        }
        
        return aVal.localeCompare(bVal);
    });
    
    displayDomains();
}

/**
 * Filter zurücksetzen
 */
function clearDomainFilters() {
    const searchInput = document.getElementById('domain-search');
    const statusFilter = document.getElementById('domain-status-filter');
    const assignmentFilter = document.getElementById('domain-assignment-filter');
    const sortFilter = document.getElementById('domain-sort-filter');
    
    if (searchInput) searchInput.value = '';
    if (statusFilter) statusFilter.value = '';
    if (assignmentFilter) assignmentFilter.value = '';
    if (sortFilter) sortFilter.value = 'domain';
    
    ISPConfigModule.filteredDomains = [...ISPConfigModule.allDomains];
    displayDomains();
}

/**
 * Domain-Zuordnung Modal anzeigen
 */
async function showDomainAssignmentModal(domainId) {
    const domain = ISPConfigModule.allDomains.find(d => d.domain_id == domainId);
    if (!domain) return;
    
    // Modal anzeigen
    const modal = document.getElementById('domain-assignment-modal');
    const domainIdInput = document.getElementById('assignment-domain-id');
    const domainNameInput = document.getElementById('assignment-domain-name');
    
    if (modal) modal.style.display = 'block';
    if (domainIdInput) domainIdInput.value = domainId;
    if (domainNameInput) domainNameInput.value = domain.domain;
    
    // Benutzer-Liste laden
    await loadUsersForAssignment();
}

/**
 * Benutzer für Zuweisung laden
 */
async function loadUsersForAssignment() {
    try {
        const response = await ModuleManager.makeRequest('ispconfig', 'get_all_users');
        
        if (response.success) {
            ISPConfigModule.allUsers = response.data || [];
            const selectElement = document.getElementById('assignment-client-id');
            
            if (selectElement) {
                selectElement.innerHTML = '<option value="">Benutzer auswählen...</option>';
                
                ISPConfigModule.allUsers.forEach(user => {
                    const option = document.createElement('option');
                    option.value = user.client_id;
                    option.textContent = `${user.company_name || user.contact_name} (${user.email})`;
                    selectElement.appendChild(option);
                });
            }
        }
    } catch (error) {
        showError('Fehler beim Laden der Benutzer: ' + error.message);
    }
}

/**
 * Domain zuweisen
 */
async function assignDomain() {
    const domainId = document.getElementById('assignment-domain-id')?.value;
    const clientId = document.getElementById('assignment-client-id')?.value;
    const notes = document.getElementById('assignment-notes')?.value;
    
    if (!domainId || !clientId) {
        showError('Bitte füllen Sie alle erforderlichen Felder aus.');
        return;
    }
    
    // Änderung zu pending changes hinzufügen
    const domain = ISPConfigModule.allDomains.find(d => d.domain_id == domainId);
    const user = ISPConfigModule.allUsers.find(u => u.client_id == clientId);
    
    ISPConfigModule.pendingChanges.push({
        type: 'assign',
        domain_id: parseInt(domainId),
        domain: domain?.domain || '',
        client_id: parseInt(clientId),
        user_name: user?.company_name || user?.contact_name || '',
        notes: notes
    });
    
    closeDomainModal();
    updateBulkActionsButton();
    showSuccess('Domain-Zuweisung zur Änderungsliste hinzugefügt. Bitte bestätigen Sie die Änderungen.');
}

/**
 * Domain-Zuweisung entfernen
 */
async function unassignDomain(domainId) {
    const domain = ISPConfigModule.allDomains.find(d => d.domain_id == domainId);
    if (!domain) return;
    
    if (!confirm(`Möchten Sie die Zuweisung der Domain "${domain.domain}" wirklich entfernen?`)) {
        return;
    }
    
    // Änderung zu pending changes hinzufügen
    ISPConfigModule.pendingChanges.push({
        type: 'unassign',
        domain_id: parseInt(domainId),
        domain: domain.domain
    });
    
    updateBulkActionsButton();
    showSuccess('Domain-Zuweisung zur Änderungsliste hinzugefügt. Bitte bestätigen Sie die Änderungen.');
}

/**
 * Domain-Einstellungen Modal anzeigen
 */
async function showDomainSettingsModal(domainId) {
    const domain = ISPConfigModule.allDomains.find(d => d.domain_id == domainId);
    if (!domain) return;
    
    ISPConfigModule.currentDomainSettings = domain;
    
    // Modal anzeigen
    const modal = document.getElementById('domain-settings-modal');
    const domainIdInput = document.getElementById('settings-domain-id');
    const domainNameInput = document.getElementById('settings-domain-name');
    
    if (modal) modal.style.display = 'block';
    if (domainIdInput) domainIdInput.value = domainId;
    if (domainNameInput) domainNameInput.value = domain.domain;
    
    // Formular ausfüllen
    document.getElementById('settings-ip-address').value = domain.ip_address || '';
    document.getElementById('settings-hd-quota').value = domain.hd_quota || '';
    document.getElementById('settings-traffic-quota').value = domain.traffic_quota || '';
    document.getElementById('settings-php-version').value = domain.php_version || 'php-fpm';
    document.getElementById('settings-ssl-enabled').value = domain.ssl_enabled || 'n';
    document.getElementById('settings-active').value = domain.active || 'n';
}

/**
 * Domain-Einstellungen aktualisieren
 */
async function updateDomainSettings() {
    const domainId = document.getElementById('settings-domain-id')?.value;
    
    if (!domainId) return;
    
    // Einstellungen sammeln
    const settings = {
        ip_address: document.getElementById('settings-ip-address')?.value,
        hd_quota: document.getElementById('settings-hd-quota')?.value,
        traffic_quota: document.getElementById('settings-traffic-quota')?.value,
        php: document.getElementById('settings-php-version')?.value,
        ssl: document.getElementById('settings-ssl-enabled')?.value,
        active: document.getElementById('settings-active')?.value
    };
    
    // Änderung zu pending changes hinzufügen
    const domain = ISPConfigModule.allDomains.find(d => d.domain_id == domainId);
    
    ISPConfigModule.pendingChanges.push({
        type: 'update_settings',
        domain_id: parseInt(domainId),
        domain: domain?.domain || '',
        settings: settings
    });
    
    closeDomainSettingsModal();
    updateBulkActionsButton();
    showSuccess('Domain-Einstellungen zur Änderungsliste hinzugefügt. Bitte bestätigen Sie die Änderungen.');
}

/**
 * Bulk-Actions Button aktualisieren
 */
function updateBulkActionsButton() {
    const bulkButton = document.getElementById('bulk-domain-changes');
    const selectedCount = document.querySelectorAll('.domain-checkbox:checked').length;
    
    if (bulkButton) {
        bulkButton.disabled = ISPConfigModule.pendingChanges.length === 0;
        bulkButton.innerHTML = `
            <i class="icon">⚡</i>
            Änderungen (${ISPConfigModule.pendingChanges.length})
        `;
    }
}

/**
 * Bulk-Changes Button Handler
 */
function showBulkDomainChanges() {
    if (ISPConfigModule.pendingChanges.length === 0) {
        showError('Keine Änderungen ausstehend.');
        return;
    }
    
    // Vorschau der Änderungen anzeigen
    previewDomainChanges();
}

/**
 * Vorschau der Domain-Änderungen
 */
async function previewDomainChanges() {
    try {
        const response = await ModuleManager.makeRequest('ispconfig', 'preview_domain_changes', {
            changes: ISPConfigModule.pendingChanges
        });
        
        if (response.success) {
            displayChangesPreview(response.data);
        } else {
            showError('Fehler bei der Vorschau: ' + response.error);
        }
    } catch (error) {
        showError('Fehler bei der Vorschau: ' + error.message);
    }
}

/**
 * Änderungs-Vorschau anzeigen
 */
function displayChangesPreview(previewData) {
    const modal = document.getElementById('changes-preview-modal');
    const totalChangesEl = document.getElementById('total-changes');
    const affectedUsersEl = document.getElementById('affected-users');
    const affectedDomainsEl = document.getElementById('affected-domains');
    const previewListEl = document.getElementById('changes-preview-list');
    
    if (modal) modal.style.display = 'block';
    
    // Statistiken aktualisieren
    if (totalChangesEl) totalChangesEl.textContent = previewData.length;
    
    const uniqueUsers = new Set();
    const uniqueDomains = new Set();
    
    previewData.forEach(change => {
        uniqueDomains.add(change.domain_id);
        if (change.affected_user) {
            uniqueUsers.add(change.affected_user.client_id);
        }
    });
    
    if (affectedUsersEl) affectedUsersEl.textContent = uniqueUsers.size;
    if (affectedDomainsEl) affectedDomainsEl.textContent = uniqueDomains.size;
    
    // Änderungsliste anzeigen
    if (previewListEl) {
        previewListEl.innerHTML = previewData.map(change => `
            <div class="change-item card mb-3">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <h5>${change.domain}</h5>
                            <small class="text-muted">Domain ID: ${change.domain_id}</small>
                        </div>
                        <div class="col-md-3">
                            <strong>Änderungstyp:</strong><br>
                            <span class="badge badge-info">${getChangeTypeText(change.type)}</span>
                        </div>
                        <div class="col-md-6">
                            ${displayChangeDetails(change)}
                        </div>
                    </div>
                    ${change.affected_user ? `
                        <div class="row mt-2">
                            <div class="col-12">
                                <small class="text-warning">
                                    <strong>Betroffener Benutzer:</strong> 
                                    ${change.affected_user.company_name || change.affected_user.contact_name} 
                                    (${change.affected_user.email})
                                </small>
                            </div>
                        </div>
                    ` : ''}
                </div>
            </div>
        `).join('');
    }
}

/**
 * Änderungstyp-Text
 */
function getChangeTypeText(type) {
    const types = {
        'assign': 'Benutzer zuweisen',
        'unassign': 'Zuweisung entfernen',
        'update_settings': 'Einstellungen ändern'
    };
    return types[type] || type;
}

/**
 * Änderungsdetails anzeigen
 */
function displayChangeDetails(change) {
    switch (change.type) {
        case 'assign':
            return `
                <strong>Von:</strong> Nicht zugewiesen<br>
                <strong>Zu:</strong> Benutzer ID ${change.new.client_id}
            `;
        case 'unassign':
            return `
                <strong>Von:</strong> Benutzer ID ${change.current.client_id || 'N/A'}<br>
                <strong>Zu:</strong> Nicht zugewiesen
            `;
        case 'update_settings':
            return `
                <strong>Geänderte Einstellungen:</strong><br>
                ${Object.keys(change.new).map(key => {
                    if (change.current[key] !== change.new[key]) {
                        return `${key}: ${change.current[key]} → ${change.new[key]}`;
                    }
                    return '';
                }).filter(Boolean).join('<br>')}
            `;
        default:
            return 'Unbekannte Änderung';
    }
}

/**
 * Domain-Änderungen ausführen
 */
async function executeDomainChanges() {
    if (ISPConfigModule.pendingChanges.length === 0) {
        showError('Keine Änderungen zu verarbeiten.');
        return;
    }
    
    if (!confirm(`Möchten Sie wirklich ${ISPConfigModule.pendingChanges.length} Änderung(en) ausführen? Diese Aktion kann nicht rückgängig gemacht werden.`)) {
        return;
    }
    
    try {
        const response = await ModuleManager.makeRequest('ispconfig', 'execute_domain_changes', {
            changes: ISPConfigModule.pendingChanges
        });
        
        if (response.success) {
            const results = response.data;
            
            // Erfolgsmeldung anzeigen
            showSuccess(`Änderungen ausgeführt: ${results.success_count} erfolgreich, ${results.error_count} Fehler`);
            
            // Ergebnisse anzeigen
            displayExecutionResults(results.results);
            
            // Pending changes zurücksetzen
            ISPConfigModule.pendingChanges = [];
            updateBulkActionsButton();
            
            // Domains neu laden
            loadAllDomains();
            
            // Modal schließen
            closeChangesPreviewModal();
            
        } else {
            showError('Fehler beim Ausführen der Änderungen: ' + response.error);
        }
    } catch (error) {
        showError('Fehler beim Ausführen der Änderungen: ' + error.message);
    }
}

/**
 * Ausführungs-Ergebnisse anzeigen
 */
function displayExecutionResults(results) {
    const successCount = results.filter(r => r.success).length;
    const errorCount = results.filter(r => !r.success).length;
    
    let message = `Ausführung abgeschlossen:\n\n`;
    message += `✅ Erfolgreich: ${successCount}\n`;
    message += `❌ Fehler: ${errorCount}\n\n`;
    
    if (errorCount > 0) {
        message += `Fehler-Details:\n`;
        results.filter(r => !r.success).forEach(result => {
            message += `• ${result.domain}: ${result.message}\n`;
        });
    }
    
    alert(message);
}

/**
 * Modal-Funktionen
 */
function closeDomainModal() {
    const modal = document.getElementById('domain-assignment-modal');
    if (modal) {
        modal.style.display = 'none';
        // Formular zurücksetzen
        document.getElementById('domain-assignment-form')?.reset();
    }
}

function closeDomainSettingsModal() {
    const modal = document.getElementById('domain-settings-modal');
    if (modal) {
        modal.style.display = 'none';
        ISPConfigModule.currentDomainSettings = null;
    }
}

function closeChangesPreviewModal() {
    const modal = document.getElementById('changes-preview-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Event-Listener für Domain-Management
 */
document.addEventListener('DOMContentLoaded', function() {
    // Refresh-Button
    const refreshBtn = document.getElementById('refresh-domains');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => {
            loadAllDomains();
        });
    }
    
    // Bulk-Changes Button
    const bulkBtn = document.getElementById('bulk-domain-changes');
    if (bulkBtn) {
        bulkBtn.addEventListener('click', () => {
            showBulkDomainChanges();
        });
    }
    
    // Such-Filter
    const searchInput = document.getElementById('domain-search');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            filterDomains();
        });
    }
    
    // Status-Filter
    const statusFilter = document.getElementById('domain-status-filter');
    if (statusFilter) {
        statusFilter.addEventListener('change', () => {
            filterDomains();
        });
    }
    
    // Assignment-Filter
    const assignmentFilter = document.getElementById('domain-assignment-filter');
    if (assignmentFilter) {
        assignmentFilter.addEventListener('change', () => {
            filterDomains();
        });
    }
    
    // Sort-Filter
    const sortFilter = document.getElementById('domain-sort-filter');
    if (sortFilter) {
        sortFilter.addEventListener('change', () => {
            sortDomains();
        });
    }
    
    // Filter zurücksetzen
    const clearFiltersBtn = document.getElementById('clear-domain-filters');
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', () => {
            clearDomainFilters();
        });
    }
    
    // Select All Checkbox
    const selectAllCheckbox = document.getElementById('select-all-domains');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', (e) => {
            const checkboxes = document.querySelectorAll('.domain-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = e.target.checked;
            });
            updateBulkActionsButton();
        });
    }
    
    // Modal schließen bei Klick außerhalb
    window.addEventListener('click', (e) => {
        const modals = ['domain-assignment-modal', 'domain-settings-modal', 'changes-preview-modal'];
        modals.forEach(modalId => {
            const modal = document.getElementById(modalId);
            if (e.target === modal) {
                const closeFunction = modalId === 'domain-assignment-modal' ? closeDomainModal :
                                    modalId === 'domain-settings-modal' ? closeDomainSettingsModal :
                                    closeChangesPreviewModal;
                closeFunction();
            }
        });
    });
});

// Globale Funktionen für Template-Zugriff
window.loadAllDomains = loadAllDomains;
window.showDomainAssignmentModal = showDomainAssignmentModal;
window.showDomainSettingsModal = showDomainSettingsModal;
window.unassignDomain = unassignDomain;
window.assignDomain = assignDomain;
window.updateDomainSettings = updateDomainSettings;
window.closeDomainModal = closeDomainModal;
window.closeDomainSettingsModal = closeDomainSettingsModal;
window.closeChangesPreviewModal = closeChangesPreviewModal;
window.executeDomainChanges = executeDomainChanges;
window.showBulkDomainChanges = showBulkDomainChanges;
window.updateBulkActionsButton = updateBulkActionsButton;
