/**
 * Main JavaScript für Bootstrap-basiertes Admin Dashboard
 * Nutzt jQuery für alle DOM-Manipulationen und AJAX-Requests
 */

// Die folgenden Variablen müssen weiterhin serverseitig im HTML gesetzt werden:
// window.sessionInfo = ...;
// window.enabledPlugins = ...;
// window.dashboardStats = ...;
// window.jsTranslations = ...;

// JavaScript Übersetzungsfunktion
window.t = function(key, params = {}) {
    if (!window.jsTranslations) {
        return key; // Fallback wenn Übersetzungen nicht verfügbar sind
    }
    let translation = window.jsTranslations[key] || key;
    Object.keys(params).forEach(param => {
        translation = translation.replace(`{${param}}`, params[param]);
    });
    return translation;
};

// ModuleManager für AJAX-Requests
window.ModuleManager = {
    currentModule: 'admin',
    request: function(plugin, action, data = {}) {
        return $.ajax({
            url: window.location.pathname,
            method: 'POST',
            data: {
                plugin: plugin,
                action: action,
                ...data
            },
            dataType: 'json'
        });
    },
    makeRequest: async function(module, action, data = {}) {
        try {
            const response = await $.ajax({
                url: window.location.pathname,
                method: 'POST',
                data: {
                    plugin: module,
                    action: action,
                    ...data
                },
                dataType: 'json'
            });
            if (!response.success && response.redirect) {
                const sessionMsg = (window.jsTranslations && window.jsTranslations.js_session_expired) ? window.jsTranslations.js_session_expired : 'Session abgelaufen - Sie werden weitergeleitet';
                showNotification(sessionMsg, 'error');
                setTimeout(() => {
                    window.location.href = response.redirect;
                }, 2000);
            }
            return response;
        } catch (error) {
            console.error('ModuleManager.makeRequest error:', error);
            if (error.status === 0) {
                const networkError = (window.jsTranslations && window.jsTranslations.js_network_error) ? window.jsTranslations.js_network_error : 'Netzwerkfehler - Server nicht erreichbar';
                console.error(networkError);
            } else if (error.status === 500) {
                const serverError = (window.jsTranslations && window.jsTranslations.js_server_error) ? window.jsTranslations.js_server_error : 'Server-Fehler - Bitte versuchen Sie es später erneut';
                console.error(serverError);
            } else {
                const ajaxError = (window.jsTranslations && window.jsTranslations.js_ajax_error) ? window.jsTranslations.js_ajax_error : 'AJAX-Fehler';
                console.error(ajaxError + ':', error.status, error.statusText);
            }
            throw error;
        }
    }
};

// Toast-Benachrichtigungen
function showNotification(message, type = 'info') {
    const toast = document.getElementById('notificationToast');
    const toastTitle = document.getElementById('toastTitle');
    const toastBody = document.getElementById('toastBody');
    const icons = {
        'success': 'bi-check-circle-fill text-success',
        'error': 'bi-x-circle-fill text-danger',
        'warning': 'bi-exclamation-triangle-fill text-warning',
        'info': 'bi-info-circle-fill text-info'
    };
    toastTitle.innerHTML = `<i class="bi ${icons[type]}"></i> ${type.charAt(0).toUpperCase() + type.slice(1)}`;
    toastBody.textContent = message;
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
}

// Loading-State für Formulare
function setLoading(form, isLoading) {
    const submitButton = form.querySelector('button[type="submit"]');
    const loadingSpan = form.querySelector('.loading');
    if (isLoading) {
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Verarbeite...';
        }
        if (loadingSpan) {
            loadingSpan.classList.remove('hidden');
        }
    } else {
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.innerHTML = submitButton.getAttribute('data-original-text') || 'Submit';
        }
        if (loadingSpan) {
            loadingSpan.classList.add('hidden');
        }
    }
}

// Plugin-Inhalte laden
function loadPluginContent(pluginKey) {
    const contentDiv = document.getElementById(pluginKey + '-content');
    if (!contentDiv) {
        console.error('Content div not found for plugin:', pluginKey);
        return;
    }
    contentDiv.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Laden...</span></div></div>';
    ModuleManager.request(pluginKey, 'getContent')
        .done(function(response) {
            if (response.success) {
                contentDiv.innerHTML = response.content;
                let moduleName;
                if (pluginKey === 'custom-module') {
                    moduleName = 'customModuleModule';
                } else {
                    moduleName = pluginKey.replace('-', '') + 'Module';
                }
                setTimeout(function() {
                    if (window[moduleName] && typeof window[moduleName].init === 'function') {
                        console.log('Initializing module:', moduleName);
                        window[moduleName].init();
                    } else {
                        console.log('Module not found or no init function:', moduleName);
                        const directModuleName = pluginKey.replace('-', '') + 'Module';
                        if (window[directModuleName] && typeof window[directModuleName].init === 'function') {
                            console.log('Direct initializing module:', directModuleName);
                            window[directModuleName].init();
                        }
                    }
                }, 50);
                console.log('Plugin content loaded successfully:', pluginKey);
            } else {
                const errorMsg = (window.jsTranslations && window.jsTranslations.js_plugin_load_error) ? window.jsTranslations.js_plugin_load_error : 'Fehler beim Laden des Plugins';
                const unknownError = (window.jsTranslations && window.jsTranslations.js_unknown_error) ? window.jsTranslations.js_unknown_error : 'Unbekannter Fehler';
                contentDiv.innerHTML = '<div class="alert alert-danger">' + errorMsg + ': ' + (response.error || unknownError) + '</div>';
            }
        })
        .fail(function(xhr, status, error) {
            const errorMsg = (window.jsTranslations && window.jsTranslations.js_plugin_load_error) ? window.jsTranslations.js_plugin_load_error : 'Fehler beim Laden des Plugins';
            contentDiv.innerHTML = '<div class="alert alert-danger">' + errorMsg + ': ' + error + '</div>';
            console.error('Plugin load failed:', pluginKey, error);
        });
}

// Admin-Funktionen
function refreshAllStats() {
    const message = (window.jsTranslations && window.jsTranslations.js_stats_updating) ? window.jsTranslations.js_stats_updating : 'Statistiken werden aktualisiert...';
    showNotification(message, 'info');
    $.post(window.location.pathname, {action: 'refresh_all_stats', core: 'admin'})
        .done(function(response) {
            if (response.success) {
                showNotification('Statistiken erfolgreich aktualisiert', 'success');
                location.reload();
            } else {
                showNotification('Fehler beim Aktualisieren der Statistiken: ' + (response.error || 'Unbekannter Fehler'), 'error');
            }
        })
        .fail(function(xhr, status, error) {
            showNotification('Fehler beim Aktualisieren der Statistiken: ' + error, 'error');
        });
}

function clearCache() {
    const message = (window.jsTranslations && window.jsTranslations.js_cache_clearing) ? window.jsTranslations.js_cache_clearing : 'Cache wird geleert...';
    showNotification(message, 'info');
    $.post(window.location.pathname, {action: 'clear_cache', core: 'admin'})
        .done(function(response) {
            if (response.success) {
                showNotification('Cache erfolgreich geleert', 'success');
            } else {
                showNotification('Fehler beim Leeren des Caches: ' + (response.error || 'Unbekannter Fehler'), 'error');
            }
        })
        .fail(function(xhr, status, error) {
            showNotification('Fehler beim Leeren des Caches: ' + error, 'error');
        });
}

function testAllConnections() {
    const message = (window.jsTranslations && window.jsTranslations.js_connections_testing) ? window.jsTranslations.js_connections_testing : 'Verbindungen werden getestet...';
    showNotification(message, 'info');
    $.post(window.location.pathname, {action: 'test_connections', core: 'admin'})
        .done(function(response) {
            if (response.success) {
                showNotification('Alle Verbindungen funktionieren', 'success');
            } else {
                showNotification('Einige Verbindungen haben Probleme: ' + (response.error || 'Unbekannter Fehler'), 'warning');
            }
        })
        .fail(function(xhr, status, error) {
            showNotification('Fehler beim Testen der Verbindungen: ' + error, 'error');
        });
}

// Platzhalterfunktionen, damit keine Fehler im Frontend auftreten
function loadLogs() {}
function loadVMData() {}
function loadWebsiteData() {}