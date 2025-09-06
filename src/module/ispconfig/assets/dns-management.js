/**
 * ISPConfig DNS Management JavaScript
 * Verwaltet DNS-Einträge für ISPConfig und OVH mit kombinierter Ansicht
 */

// Verwende den ISPConfig Module Namespace
// Globale Variablen sind bereits in module.js definiert

/**
 * DNS-Management Modal für Domain öffnen
 */
async function showDnsManagement(domain) {
    ISPConfigModule.currentDomain = domain;
    
    // Modal anzeigen
    const modal = document.getElementById('dns-management-modal');
    const title = document.getElementById('dns-modal-title');
    
    if (modal) modal.style.display = 'block';
    if (title) title.textContent = `DNS-Management: ${domain}`;
    
    // DNS-Einträge laden
    await loadDnsRecords();
}

/**
 * DNS-Einträge für die aktuelle Domain laden
 */
async function loadDnsRecords() {
    const loadingElement = document.getElementById('dns-loading');
    const tbodyElement = document.getElementById('dns-records-tbody');
    const noRecordsElement = document.getElementById('no-dns-records');
    
    if (loadingElement) loadingElement.style.display = 'block';
    if (tbodyElement) tbodyElement.innerHTML = '';
    if (noRecordsElement) noRecordsElement.style.display = 'none';
    
    try {
        const response = await ModuleManager.makeRequest('ispconfig', 'get_domain_dns_records', {
            domain: ISPConfigModule.currentDomain
        });
        
        if (response.success) {
            ISPConfigModule.currentDnsRecords = response.data;
            displayDnsRecords();
        } else {
            showError('Fehler beim Laden der DNS-Einträge: ' + response.error);
        }
    } catch (error) {
        showError('Fehler beim Laden der DNS-Einträge: ' + error.message);
    } finally {
        if (loadingElement) loadingElement.style.display = 'none';
    }
}

/**
 * DNS-Einträge in der Tabelle anzeigen
 */
function displayDnsRecords() {
    const tbodyElement = document.getElementById('dns-records-tbody');
    const noRecordsElement = document.getElementById('no-dns-records');
    
    if (!tbodyElement) return;
    
    let recordsToShow = [];
    
    switch (ISPConfigModule.currentDnsTab) {
        case 'ispconfig':
            recordsToShow = ISPConfigModule.currentDnsRecords.ispconfig || [];
            break;
        case 'ovh':
            recordsToShow = ISPConfigModule.currentDnsRecords.ovh || [];
            break;
        case 'combined':
        default:
            recordsToShow = ISPConfigModule.currentDnsRecords.combined || [];
            break;
    }
    
    if (recordsToShow.length === 0) {
        tbodyElement.innerHTML = '';
        if (noRecordsElement) noRecordsElement.style.display = 'block';
        return;
    }
    
    if (noRecordsElement) noRecordsElement.style.display = 'none';
    
    tbodyElement.innerHTML = recordsToShow.map(record => `
        <tr class="${getRecordRowClass(record)}">
            <td>
                <span class="badge badge-info">${record.type}</span>
            </td>
            <td>${record.name || '@'}</td>
            <td>${record.value}</td>
            <td>${record.ttl}</td>
            <td>${record.priority || '-'}</td>
            <td>${displayRecordSources(record)}</td>
            <td>
                <span class="badge ${record.active === 'y' ? 'badge-success' : 'badge-secondary'}">
                    ${record.active === 'y' ? 'Aktiv' : 'Inaktiv'}
                </span>
            </td>
            <td>
                <div class="btn-group" role="group">
                    ${getRecordActions(record)}
                </div>
            </td>
        </tr>
    `).join('');
}

/**
 * Zeilenklasse basierend auf Record-Status bestimmen
 */
function getRecordRowClass(record) {
    if (record.conflicts && record.conflicts.length > 0) {
        return 'table-warning';
    }
    if (record.sources && record.sources.length > 1) {
        return 'table-info';
    }
    return '';
}

/**
 * Record-Quellen anzeigen
 */
function displayRecordSources(record) {
    if (record.sources && record.sources.length > 1) {
        return record.sources.map(source => {
            const icons = {
                'ispconfig': '📋',
                'ovh': '🌐'
            };
            return `${icons[source] || '📄'} ${source}`;
        }).join('<br>');
    } else {
        const source = record.source || (record.sources && record.sources[0]);
        const icons = {
            'ispconfig': '📋',
            'ovh': '🌐'
        };
        return `${icons[source] || '📄'} ${source}`;
    }
}

/**
 * Record-Aktionen generieren
 */
function getRecordActions(record) {
    let actions = '';
    
    // Bearbeiten-Button für jeden Eintrag
    if (record.sources && record.sources.length > 1) {
        // Mehrere Quellen - Bearbeiten-Buttons für jede Quelle
        record.sources.forEach((source, index) => {
            const sourceRecord = source === 'ispconfig' ? 
                ISPConfigModule.currentDnsRecords.ispconfig.find(r => r.type === record.type && r.name === record.name) :
                ISPConfigModule.currentDnsRecords.ovh.find(r => r.type === record.type && r.name === record.name);
            
            if (sourceRecord) {
                actions += `
                    <button class="btn btn-sm btn-primary" onclick="editDnsRecord('${sourceRecord.id}', '${source}', '${record.type}', '${record.name}')" 
                            title="Bearbeiten (${source})">
                        <i class="icon">✏️</i>
                    </button>
                `;
            }
        });
    } else {
        const source = record.source || (record.sources && record.sources[0]);
        actions += `
            <button class="btn btn-sm btn-primary" onclick="editDnsRecord('${record.id}', '${source}', '${record.type}', '${record.name}')" 
                    title="Bearbeiten">
                <i class="icon">✏️</i>
            </button>
        `;
    }
    
    // Löschen-Button
    if (record.sources && record.sources.length > 1) {
        record.sources.forEach(source => {
            const sourceRecord = source === 'ispconfig' ? 
                ISPConfigModule.currentDnsRecords.ispconfig.find(r => r.type === record.type && r.name === record.name) :
                ISPConfigModule.currentDnsRecords.ovh.find(r => r.type === record.type && r.name === record.name);
            
            if (sourceRecord) {
                actions += `
                    <button class="btn btn-sm btn-danger" onclick="deleteDnsRecord('${sourceRecord.id}', '${source}', '${record.type}', '${record.name}')" 
                            title="Löschen (${source})">
                        <i class="icon">🗑️</i>
                    </button>
                `;
            }
        });
    } else {
        const source = record.source || (record.sources && record.sources[0]);
        actions += `
            <button class="btn btn-sm btn-danger" onclick="deleteDnsRecord('${record.id}', '${source}', '${record.type}', '${record.name}')" 
                    title="Löschen">
                <i class="icon">🗑️</i>
            </button>
        `;
    }
    
    return actions;
}

/**
 * DNS-Tab wechseln
 */
function switchDnsTab(tabName) {
    ISPConfigModule.currentDnsTab = tabName;
    
    // Tab-Buttons aktualisieren
    document.querySelectorAll('.dns-tabs .tab-button').forEach(btn => {
        btn.classList.remove('active');
    });
    
    const activeBtn = document.getElementById(`dns-tab-${tabName}`);
    if (activeBtn) activeBtn.classList.add('active');
    
    // Records neu anzeigen
    displayDnsRecords();
}

/**
 * DNS-Eintrag bearbeiten
 */
function editDnsRecord(recordId, source, type, name) {
    const record = findDnsRecord(recordId, source);
    if (!record) {
        showError('DNS-Eintrag nicht gefunden.');
        return;
    }
    
    // Modal anzeigen
    const modal = document.getElementById('dns-record-modal');
    const title = document.getElementById('dns-record-modal-title');
    const deleteBtn = document.getElementById('dns-record-delete');
    
    if (modal) modal.style.display = 'block';
    if (title) title.textContent = 'DNS-Eintrag bearbeiten';
    if (deleteBtn) deleteBtn.style.display = 'inline-block';
    
    // Formular ausfüllen
    document.getElementById('dns-record-id').value = recordId;
    document.getElementById('dns-record-source').value = source;
    document.getElementById('dns-record-domain').value = ISPConfigModule.currentDomain;
    document.getElementById('dns-record-type').value = type;
    document.getElementById('dns-record-name').value = name || '';
    document.getElementById('dns-record-value').value = record.value || '';
    document.getElementById('dns-record-ttl').value = record.ttl || 3600;
    document.getElementById('dns-record-priority').value = record.priority || '';
    document.getElementById('dns-record-source-select').value = source;
}

/**
 * DNS-Eintrag erstellen
 */
function addDnsRecord() {
    // Modal anzeigen
    const modal = document.getElementById('dns-record-modal');
    const title = document.getElementById('dns-record-modal-title');
    const deleteBtn = document.getElementById('dns-record-delete');
    
    if (modal) modal.style.display = 'block';
    if (title) title.textContent = 'Neuen DNS-Eintrag erstellen';
    if (deleteBtn) deleteBtn.style.display = 'none';
    
    // Formular zurücksetzen
    document.getElementById('dns-record-form').reset();
    document.getElementById('dns-record-domain').value = ISPConfigModule.currentDomain;
    document.getElementById('dns-record-ttl').value = 3600;
}

/**
 * DNS-Eintrag speichern
 */
async function saveDnsRecord() {
    const recordId = document.getElementById('dns-record-id')?.value;
    const domain = document.getElementById('dns-record-domain')?.value;
    const source = document.getElementById('dns-record-source-select')?.value;
    const type = document.getElementById('dns-record-type')?.value;
    const name = document.getElementById('dns-record-name')?.value;
    const value = document.getElementById('dns-record-value')?.value;
    const ttl = document.getElementById('dns-record-ttl')?.value;
    const priority = document.getElementById('dns-record-priority')?.value;
    
    if (!domain || !source || !type || !name || !value || !ttl) {
        showError('Bitte füllen Sie alle erforderlichen Felder aus.');
        return;
    }
    
    const data = {
        domain: domain,
        source: source,
        type: type,
        name: name,
        value: value,
        ttl: parseInt(ttl),
        priority: priority ? parseInt(priority) : null
    };
    
    try {
        let response;
        if (recordId) {
            // Eintrag aktualisieren
            data.record_id = recordId;
            response = await ModuleManager.makeRequest('ispconfig', 'update_dns_record', data);
        } else {
            // Neuen Eintrag erstellen
            response = await ModuleManager.makeRequest('ispconfig', 'create_dns_record', data);
        }
        
        if (response.success) {
            showSuccess(response.message || 'DNS-Eintrag erfolgreich gespeichert.');
            closeDnsRecordModal();
            loadDnsRecords(); // Records neu laden
        } else {
            showError('Fehler beim Speichern: ' + response.error);
        }
    } catch (error) {
        showError('Fehler beim Speichern: ' + error.message);
    }
}

/**
 * DNS-Eintrag löschen
 */
async function deleteDnsRecord(recordId, source, type, name) {
    if (!confirm(`Möchten Sie den DNS-Eintrag "${type} ${name}" wirklich löschen?`)) {
        return;
    }
    
    try {
        const response = await ModuleManager.makeRequest('ispconfig', 'delete_dns_record', {
            record_id: recordId,
            domain: ISPConfigModule.currentDomain,
            source: source
        });
        
        if (response.success) {
            showSuccess('DNS-Eintrag erfolgreich gelöscht.');
            loadDnsRecords(); // Records neu laden
        } else {
            showError('Fehler beim Löschen: ' + response.error);
        }
    } catch (error) {
        showError('Fehler beim Löschen: ' + error.message);
    }
}

/**
 * DNS-Eintrag in Records finden
 */
function findDnsRecord(recordId, source) {
    const records = source === 'ispconfig' ? ISPConfigModule.currentDnsRecords.ispconfig : ISPConfigModule.currentDnsRecords.ovh;
    return records.find(r => r.id == recordId);
}

/**
 * DNS-Synchronisation starten
 */
function showDnsSync() {
    const modal = document.getElementById('dns-sync-modal');
    if (modal) modal.style.display = 'block';
}

/**
 * DNS-Synchronisation Vorschau
 */
async function previewDnsSync() {
    const direction = document.getElementById('sync-direction')?.value;
    if (!direction) return;
    
    try {
        const response = await ModuleManager.makeRequest('ispconfig', 'sync_dns_records', {
            domain: ISPConfigModule.currentDomain,
            sync_direction: direction
        });
        
        if (response.success) {
            displaySyncPreview(response.data);
        } else {
            showError('Fehler bei der Vorschau: ' + response.error);
        }
    } catch (error) {
        showError('Fehler bei der Vorschau: ' + error.message);
    }
}

/**
 * Synchronisations-Vorschau anzeigen
 */
function displaySyncPreview(syncData) {
    const previewElement = document.getElementById('sync-preview');
    const contentElement = document.getElementById('sync-preview-content');
    const executeBtn = document.getElementById('execute-sync-btn');
    
    if (previewElement) previewElement.style.display = 'block';
    if (contentElement) {
        contentElement.innerHTML = `
            <div class="alert alert-info">
                <strong>Geplante Aktionen:</strong> ${syncData.total_actions || 0}
            </div>
            <div class="sync-actions-list">
                ${syncData.results ? syncData.results.map(action => `
                    <div class="sync-action-item">
                        <span class="badge badge-primary">${action.action}</span>
                        <strong>${action.type}</strong> ${action.name} → ${action.source}
                        <span class="badge ${action.success ? 'badge-success' : 'badge-danger'}">
                            ${action.success ? 'OK' : 'Fehler'}
                        </span>
                    </div>
                `).join('') : 'Keine Aktionen erforderlich.'}
            </div>
        `;
    }
    if (executeBtn) executeBtn.style.display = 'inline-block';
}

/**
 * DNS-Synchronisation ausführen
 */
async function executeDnsSync() {
    const direction = document.getElementById('sync-direction')?.value;
    if (!direction) return;
    
    if (!confirm(`Möchten Sie die DNS-Synchronisation wirklich ausführen?`)) {
        return;
    }
    
    try {
        const response = await ModuleManager.makeRequest('ispconfig', 'sync_dns_records', {
            domain: ISPConfigModule.currentDomain,
            sync_direction: direction
        });
        
        if (response.success) {
            showSuccess('DNS-Synchronisation erfolgreich abgeschlossen.');
            closeDnsSyncModal();
            loadDnsRecords(); // Records neu laden
        } else {
            showError('Fehler bei der Synchronisation: ' + response.error);
        }
    } catch (error) {
        showError('Fehler bei der Synchronisation: ' + error.message);
    }
}

/**
 * Modal-Funktionen
 */
function closeDnsModal() {
    const modal = document.getElementById('dns-management-modal');
    if (modal) {
        modal.style.display = 'none';
        ISPConfigModule.currentDomain = '';
        ISPConfigModule.currentDnsRecords = { ispconfig: [], ovh: [], combined: [] };
    }
}

function closeDnsRecordModal() {
    const modal = document.getElementById('dns-record-modal');
    if (modal) {
        modal.style.display = 'none';
        document.getElementById('dns-record-form')?.reset();
    }
}

function closeDnsSyncModal() {
    const modal = document.getElementById('dns-sync-modal');
    if (modal) {
        modal.style.display = 'none';
        const previewElement = document.getElementById('sync-preview');
        const executeBtn = document.getElementById('execute-sync-btn');
        if (previewElement) previewElement.style.display = 'none';
        if (executeBtn) executeBtn.style.display = 'none';
    }
}

/**
 * Event-Listener für DNS-Management
 */
document.addEventListener('DOMContentLoaded', function() {
    // Refresh-Button
    const refreshBtn = document.getElementById('refresh-dns');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => {
            loadDnsRecords();
        });
    }
    
    // Add DNS Record Button
    const addBtn = document.getElementById('add-dns-record');
    if (addBtn) {
        addBtn.addEventListener('click', () => {
            addDnsRecord();
        });
    }
    
    // Sync DNS Records Button
    const syncBtn = document.getElementById('sync-dns-records');
    if (syncBtn) {
        syncBtn.addEventListener('click', () => {
            showDnsSync();
        });
    }
    
    // Modal schließen bei Klick außerhalb
    window.addEventListener('click', (e) => {
        const modals = ['dns-management-modal', 'dns-record-modal', 'dns-sync-modal'];
        modals.forEach(modalId => {
            const modal = document.getElementById(modalId);
            if (e.target === modal) {
                const closeFunction = modalId === 'dns-management-modal' ? closeDnsModal :
                                    modalId === 'dns-record-modal' ? closeDnsRecordModal :
                                    closeDnsSyncModal;
                closeFunction();
            }
        });
    });
});

// Globale Funktionen für Template-Zugriff
window.showDnsManagement = showDnsManagement;
window.switchDnsTab = switchDnsTab;
window.editDnsRecord = editDnsRecord;
window.addDnsRecord = addDnsRecord;
window.saveDnsRecord = saveDnsRecord;
window.deleteDnsRecord = deleteDnsRecord;
window.showDnsSync = showDnsSync;
window.previewDnsSync = previewDnsSync;
window.executeDnsSync = executeDnsSync;
window.closeDnsModal = closeDnsModal;
window.closeDnsRecordModal = closeDnsRecordModal;
window.closeDnsSyncModal = closeDnsSyncModal;
