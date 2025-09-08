/**
 * Endpoints Module JavaScript
 * Verwaltet alle AJAX-Requests für das Endpoints-Modul
 */

window.endpointsModule = {
    lastResponse: null,
    i18n: {},
    
    /**
     * Testet einen Endpoint ohne Parameter
     */
    testEndpoint: function(module, action) {
        this.showLoading();
        this.sendRequest(module, action)
            .then(result => {
                this.displayResult(module, action, result);
                this.hideLoading();
            })
            .catch(error => {
                this.displayResult(module, action, {success: false, error: error.message});
                this.hideLoading();
            });
    },
    
    /**
     * Testet einen Endpoint mit einem Parameter
     */
    testEndpointWithParam: function(module, action, paramName, paramValue) {
        this.showLoading();
        const params = {};
        params[paramName] = paramValue;
        
        this.sendRequest(module, action, params)
            .then(result => {
                this.displayResult(module, action, result);
                this.hideLoading();
            })
            .catch(error => {
                this.displayResult(module, action, {success: false, error: error.message});
                this.hideLoading();
            });
    },
    
    /**
     * Testet einen Endpoint mit mehreren Parametern
     */
    testEndpointWithParams: function(module, action, params) {
        this.showLoading();
        this.sendRequest(module, action, params)
            .then(result => {
                this.displayResult(module, action, result);
                this.hideLoading();
            })
            .catch(error => {
                this.displayResult(module, action, {success: false, error: error.message});
                this.hideLoading();
            });
    },
    
    /**
     * Testet den Session Heartbeat
     */
    testHeartbeat: function() {
        this.showLoading();
        fetch('?heartbeat=1')
            .then(response => response.json())
            .then(result => {
                this.displayResult('system', 'heartbeat', result);
                this.hideLoading();
            })
            .catch(error => {
                this.displayResult('system', 'heartbeat', {success: false, error: error.message});
                this.hideLoading();
            });
    },
    
    /**
     * Testet einen benutzerdefinierten Endpoint
     */
    testCustomEndpoint: function(module, action, params) {
        this.showLoading();
        this.sendRequest(module, action, params)
            .then(result => {
                this.displayResult(module, action, result);
                this.hideLoading();
            })
            .catch(error => {
                this.displayResult(module, action, {success: false, error: error.message});
                this.hideLoading();
            });
    },

    /**
     * Sendet eine Anfrage. Wenn ein fremdes Modul angesprochen wird,
     * wird automatisch über endpoints/proxy_endpoint geroutet.
     */
    sendRequest: function(module, action, params = {}) {
        // Sonderfall: Übersetzungen und eigene Actions des Endpoints-Moduls
        const isEndpointsModule = module === 'endpoints' || !module;
        if (!isEndpointsModule) {
            const effectiveParams = Object.assign({}, params, { module: module, action: action });
            return ModuleManager.makeRequest('endpoints', 'proxy_endpoint', effectiveParams);
        }
        return ModuleManager.makeRequest(module || 'endpoints', action, params);
    },
    
    /**
     * Zeigt das Ergebnis eines Endpoint-Tests an
     */
    displayResult: function(module, action, result) {
        const statusEl = document.getElementById('endpoint-status');
        if (statusEl) {
            if (result.success) {
                statusEl.textContent = '✅ ' + (this.t('success') || 'Success');
                statusEl.style.color = '#10b981';
            } else {
                statusEl.textContent = '❌ ' + (this.t('error') || 'Error');
                statusEl.style.color = '#ef4444';
            }
        }
        
        const response = {
            module: module,
            action: action,
            timestamp: new Date().toISOString(),
            response: result
        };
        
        this.lastResponse = JSON.stringify(response, null, 2);
        const responseEl = document.getElementById('endpoint-response');
        if (responseEl) {
            responseEl.textContent = this.lastResponse;
        }
        
        const testedMsg = (this.t('testing') || 'Testing') + `: ${module}.${action}`;
        this.showResult(result.success, testedMsg);
    },
    
    /**
     * Kopiert die letzte Antwort in die Zwischenablage
     */
    copyResponse: function() {
        if (this.lastResponse) {
            navigator.clipboard.writeText(this.lastResponse)
                .then(() => {
                    this.showResult(true, this.t('response_copied') || 'Response copied!');
                })
                .catch(() => {
                    this.showResult(false, this.t('copy_failed') || 'Copy failed');
                });
        }
    },
    
    /**
     * Zeigt ein Ergebnis an
     */
    showResult: function(success, message) {
        const resultDiv = document.getElementById('action-result');
        if (resultDiv) {
            resultDiv.className = `alert alert-${success ? 'success' : 'danger'}`;
            resultDiv.textContent = message;
            resultDiv.style.display = 'block';
            
            // Nach 5 Sekunden ausblenden
            setTimeout(() => {
                resultDiv.style.display = 'none';
            }, 5000);
        }
    },
    
    /**
     * Zeigt Loading-Animation
     */
    showLoading: function() {
        // Loading-Overlay anzeigen
        const overlay = document.createElement('div');
        overlay.id = 'loading-overlay';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        `;
        overlay.innerHTML = '<div class="spinner-border text-light" role="status"><span class="visually-hidden">Laden...</span></div>';
        document.body.appendChild(overlay);
    },
    
    /**
     * Versteckt Loading-Animation
     */
    hideLoading: function() {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.remove();
        }
    },
    
    /**
     * Lädt den Modul-Inhalt neu
     */
    reloadContent: function() {
        this.showLoading();
        
        // Modul-Inhalt über AJAX neu laden
        ModuleManager.makeRequest('endpoints', 'getContent')
            .then(response => {
                if (response.success) {
                    // Inhalt in den Endpoints-Container einfügen
                    const contentDiv = document.getElementById('endpoints-content');
                    if (contentDiv) {
                        contentDiv.innerHTML = response.content;
                        
                        // Script-Tags aus dem geladenen Content extrahieren und ausführen
                        const scripts = contentDiv.querySelectorAll('script');
                        scripts.forEach(function(script) {
                            const newScript = document.createElement('script');
                            if (script.src) {
                                newScript.src = script.src;
                            } else {
                                newScript.textContent = script.textContent;
                            }
                            document.head.appendChild(newScript);
                        });
                        
                        // Modul neu initialisieren
                        if (window.endpointsModule) {
                            window.endpointsModule.init();
                        }
                    }
                }
                this.hideLoading();
            })
            .catch(error => {
                console.error('Fehler beim Neuladen des Inhalts:', error);
                this.hideLoading();
            });
    },
    
    /**
     * Initialisiert das Modul
     */
    init: function() {
        console.log('Endpoints Module initialized');
        
        // Event-Listener für Buttons hinzufügen
        this.setupEventListeners();

        // Übersetzungen laden
        this.loadTranslations();
    },
    
    /**
     * Richtet Event-Listener ein
     */
    setupEventListeners: function() {
        // Event-Listener für Endpoint-Test-Buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('endpoints-test-btn')) {
                e.preventDefault();
                this.handleButtonClick(e.target);
            }
        });
        
        // Event-Listener für Custom Endpoint Form
        document.addEventListener('submit', (e) => {
            if (e.target.classList.contains('endpoints-custom-form')) {
                e.preventDefault();
                this.handleCustomFormSubmit(e.target);
            }
        });
    },
    
    /**
     * Behandelt Button-Klicks
     */
    handleButtonClick: function(button) {
        const module = button.dataset.module;
        const action = button.dataset.action;
        const paramName = button.dataset.paramName;
        const paramValue = button.dataset.paramValue;
        const params = button.dataset.params;
        
        // Sonderfall: Heartbeat-Button
        if (action === 'testHeartbeat') {
            this.testHeartbeat();
            return;
        }

        if (params) {
            try {
                const parsedParams = JSON.parse(params);
                this.testEndpointWithParams(module, action, parsedParams);
            } catch (error) {
                this.showResult(false, this.t('invalid_json') || 'Invalid JSON in parameters');
            }
        } else if (paramName && paramValue) {
            this.testEndpointWithParam(module, action, paramName, paramValue);
        } else {
            this.testEndpoint(module, action);
        }
    },
    
    /**
     * Behandelt Custom Endpoint Form Submits
     */
    handleCustomFormSubmit: function(form) {
        const module = form.module.value;
        const action = form.action.value;
        let params = {};
        
        if (form.params.value) {
            try {
                params = JSON.parse(form.params.value);
            } catch (e) {
                this.showResult(false, this.t('invalid_json') || 'Invalid JSON in parameters');
                return;
            }
        }
        
        this.testCustomEndpoint(module, action, params);
    },

    loadTranslations: function() {
        ModuleManager.makeRequest('endpoints', 'get_translations')
            .then(result => {
                if (result.success) {
                    this.i18n = result.data || result;
                }
            })
            .catch(() => {});
    },

    t: function(key) {
        if (!key) return '';
        return this.i18n?.[key] || window?.t?.(key) || key;
    }
};

// Modul sofort initialisieren, wenn das Script geladen wird
if (window.endpointsModule) {
    window.endpointsModule.init();
} 