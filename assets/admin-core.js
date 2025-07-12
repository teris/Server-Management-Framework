/**
 * Admin Dashboard Core JavaScript
 */

// Admin Section Management
function showAdminSection(section, element) {
    // Hide all sections
    document.querySelectorAll('.admin-section').forEach(sec => {
        sec.classList.add('hidden');
    });
    
    // Deactivate all tabs
    element.parentNode.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Show selected section
    const sectionEl = document.getElementById('admin-' + section);
    if (sectionEl) {
        sectionEl.classList.remove('hidden');
        element.classList.add('active');
        
        // Load data if needed
        if (section === 'resources' && !window.resourcesLoaded) {
            loadResource('vms');
            window.resourcesLoaded = true;
        } else if (section === 'logs' && !window.logsLoaded) {
            loadLogs();
            window.logsLoaded = true;
        }
    }
}

// Resource Tab Management
function showResourceTab(resource, element) {
    // Hide all resource panels
    document.querySelectorAll('.resource-panel').forEach(panel => {
        panel.classList.add('hidden');
    });
    
    // Deactivate all resource tabs
    element.parentNode.querySelectorAll('.resource-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Show selected resource
    const panelEl = document.getElementById('resource-' + resource);
    if (panelEl) {
        panelEl.classList.remove('hidden');
        element.classList.add('active');
        
        // Load data if not already loaded
        const contentEl = document.getElementById(resource + '-content');
        if (contentEl && contentEl.querySelector('.loading')) {
            loadResource(resource);
        }
    }
}

// Resource Loading
async function loadResource(type) {
    const contentEl = document.getElementById(type + '-content');
    if (!contentEl) return;
    
    contentEl.innerHTML = '<div class="loading">Lade ' + type + '...</div>';
    
    try {
        const result = await AdminCore.makeRequest('get_resources', { type: type });
        
        if (result.success) {
            contentEl.innerHTML = result.data.html || '<div class="no-data">Keine Daten</div>';
            
            // Update count in stats
            const countEl = document.getElementById(type + '-count');
            if (countEl && result.data.data) {
                countEl.textContent = result.data.data.length;
            }
        } else {
            contentEl.innerHTML = '<div class="error">Fehler: ' + result.error + '</div>';
        }
    } catch (error) {
        contentEl.innerHTML = '<div class="error">Netzwerkfehler: ' + error.message + '</div>';
    }
}

// Filter Resources
function filterResource(type, searchValue) {
    const contentEl = document.getElementById(type + '-content');
    if (!contentEl) return;
    
    const table = contentEl.querySelector('table');
    if (!table) return;
    
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(searchValue.toLowerCase())) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Refresh All Stats
async function refreshAllStats() {
    showNotification('Aktualisiere alle Statistiken...', 'info');
    
    try {
        const result = await AdminCore.makeRequest('refresh_all_stats');
        
        if (result.success) {
            // Update stat cards
            for (const [key, stat] of Object.entries(result.data)) {
                const countEl = document.getElementById(key + '-count');
                if (countEl) {
                    countEl.textContent = stat.count;
                }
                
                // Update status if exists
                const card = document.querySelector(`[data-stat="${key}"]`);
                if (card && stat.status_text) {
                    let statusEl = card.querySelector('.stat-status');
                    if (!statusEl) {
                        statusEl = document.createElement('div');
                        statusEl.className = 'stat-status';
                        card.appendChild(statusEl);
                    }
                    statusEl.textContent = stat.status_text;
                    statusEl.className = 'stat-status ' + stat.status;
                }
            }
            
            showNotification('Statistiken erfolgreich aktualisiert', 'success');
        } else {
            showNotification('Fehler: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler', 'error');
    }
}

// Clear Cache
async function clearCache() {
    if (!confirm('Cache wirklich leeren?')) return;
    
    try {
        const result = await AdminCore.makeRequest('clear_cache');
        
        if (result.success) {
            showNotification('Cache erfolgreich geleert', 'success');
        } else {
            showNotification('Fehler: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler', 'error');
    }
}

// Test All Connections
async function testAllConnections() {
    showNotification('Teste alle Verbindungen...', 'info');
    
    try {
        const result = await AdminCore.makeRequest('test_connections');
        
        if (result.success) {
            let message = 'Verbindungstests:\n';
            for (const [service, test] of Object.entries(result.data)) {
                message += `\n${service}: ${test.status === 'success' ? '✅' : '❌'} ${test.message}`;
            }
            alert(message);
        } else {
            showNotification('Fehler: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler', 'error');
    }
}

// VM Control
async function controlVM(node, vmid, action) {
    if (!confirm(`VM ${vmid} wirklich ${action}?`)) return;
    
    try {
        const result = await AdminCore.makeRequest('control_vm', {
            node: node,
            vmid: vmid,
            action: action
        });
        
        if (result.success) {
            showNotification(`VM ${vmid} ${action} erfolgreich`, 'success');
            // Reload VMs after 2 seconds
            setTimeout(() => loadResource('vms'), 2000);
        } else {
            showNotification('Fehler: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler', 'error');
    }
}

// Delete VM
async function deleteVM(node, vmid) {
    if (!confirm(`VM ${vmid} wirklich LÖSCHEN? Diese Aktion kann nicht rückgängig gemacht werden!`)) return;
    
    try {
        const result = await AdminCore.makeRequest('delete_vm', {
            node: node,
            vmid: vmid
        });
        
        if (result.success) {
            showNotification(`VM ${vmid} wurde gelöscht`, 'success');
            loadResource('vms');
        } else {
            showNotification('Fehler: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler', 'error');
    }
}

// Delete Website
async function deleteWebsite(domainId) {
    if (!confirm('Website wirklich löschen?')) return;
    
    try {
        const result = await AdminCore.makeRequest('delete_website', {
            domain_id: domainId
        });
        
        if (result.success) {
            showNotification('Website wurde gelöscht', 'success');
            loadResource('websites');
        } else {
            showNotification('Fehler: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler', 'error');
    }
}

// Plugin Management
function openPlugin(pluginKey) {
    PluginManager.open(pluginKey);
}

function closePlugin() {
    PluginManager.close();
}

async function togglePlugin(pluginKey, enable) {
    try {
        const result = await AdminCore.makeRequest('toggle_plugin', {
            plugin: pluginKey,
            enable: enable
        });
        
        if (result.success) {
            showNotification('Plugin ' + (enable ? 'aktiviert' : 'deaktiviert'), 'success');
            // Reload page to apply changes
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification('Fehler: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler', 'error');
    }
}

// Activity Logs
async function loadLogs() {
    const contentEl = document.getElementById('logs-content');
    if (!contentEl) return;
    
    contentEl.innerHTML = '<div class="loading">Lade Logs...</div>';
    
    try {
        const filters = {
            level: document.getElementById('log-level').value,
            date: document.getElementById('log-date').value
        };
        
        const result = await AdminCore.makeRequest('get_activity_logs', filters);
        
        if (result.success) {
            contentEl.innerHTML = result.data.html || '<div class="no-data">Keine Logs gefunden</div>';
        } else {
            contentEl.innerHTML = '<div class="error">Fehler: ' + result.error + '</div>';
        }
    } catch (error) {
        contentEl.innerHTML = '<div class="error">Netzwerkfehler: ' + error.message + '</div>';
    }
}

function filterLogs() {
    loadLogs();
}

// Settings
async function saveSettings() {
    const settings = {};
    
    // Collect all checkbox settings
    document.querySelectorAll('.setting-group input[type="checkbox"]').forEach(checkbox => {
        settings[checkbox.id] = checkbox.checked;
    });
    
    try {
        const result = await AdminCore.makeRequest('save_settings', settings);
        
        if (result.success) {
            showNotification('Einstellungen gespeichert', 'success');
            
            // Apply some settings immediately
            if (settings.debug_mode !== undefined) {
                SystemConfig.debug = settings.debug_mode;
            }
        } else {
            showNotification('Fehler: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Netzwerkfehler', 'error');
    }
}

// Notifications
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    // Add to body
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => notification.classList.add('show'), 10);
    
    // Remove after 5 seconds
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

// Initialize on load
document.addEventListener('DOMContentLoaded', function() {
    // Load initial stats
    refreshAllStats();
    
    // Auto-refresh stats if enabled
    if (window.dashboardConfig && window.dashboardConfig.refresh_interval) {
        setInterval(refreshAllStats, window.dashboardConfig.refresh_interval * 1000);
    }
    
    // Initialize tooltips, charts, etc.
    console.log('Admin Dashboard initialized');
});

// Legacy support for old function calls
function loadVMs() { loadResource('vms'); }
function loadWebsites() { loadResource('websites'); }
function loadDatabases() { loadResource('databases'); }
function loadEmails() { loadResource('emails'); }
function loadDomains() { loadResource('domains'); }
function loadVirtualMacs() { loadResource('virtualmacs'); }
function loadVPSList() { loadResource('vps'); }
function loadActivityLog() { loadLogs(); }