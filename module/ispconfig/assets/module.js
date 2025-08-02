/**
 * ISPConfig Module JavaScript
 * Verwaltet alle AJAX-Requests für das ISPConfig-Modul
 */

window.ispconfigModule = {
    /**
     * Erstellt eine neue Website
     */
    createWebsite: function(formData) {
        this.showLoading();
        ModuleManager.makeRequest('ispconfig', 'create_website', formData)
            .then(response => {
                this.showResult(response.success, response.message);
                if (response.success) {
                    this.reloadContent();
                }
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler beim Erstellen der Website');
                this.hideLoading();
            });
    },
    
    /**
     * Erstellt einen FTP-Benutzer
     */
    createFTPUser: function(formData) {
        this.showLoading();
        ModuleManager.makeRequest('ispconfig', 'create_ftp_user', formData)
            .then(response => {
                this.showResult(response.success, response.message);
                if (response.success) {
                    this.reloadContent();
                }
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler beim Erstellen des FTP-Benutzers');
                this.hideLoading();
            });
    },
    
    /**
     * Erstellt eine Subdomain
     */
    createSubdomain: function(formData) {
        this.showLoading();
        ModuleManager.makeRequest('ispconfig', 'create_subdomain', formData)
            .then(response => {
                this.showResult(response.success, response.message);
                if (response.success) {
                    this.reloadContent();
                }
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler beim Erstellen der Subdomain');
                this.hideLoading();
            });
    },
    
    /**
     * Lädt ISPConfig-Clients
     */
    loadISPConfigClients: function() {
        this.showLoading();
        ModuleManager.makeRequest('ispconfig', 'get_ispconfig_clients')
            .then(response => {
                this.showResult(response.success, response.message);
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler beim Laden der Clients');
                this.hideLoading();
            });
    },
    
    /**
     * Lädt Server-Konfiguration
     */
    loadServerConfig: function() {
        this.showLoading();
        ModuleManager.makeRequest('ispconfig', 'get_ispconfig_server_config')
            .then(response => {
                this.showResult(response.success, response.message);
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler beim Laden der Server-Konfiguration');
                this.hideLoading();
            });
    },
    
    /**
     * Zeigt Website-Details an
     */
    showWebsiteDetails: function() {
        this.showLoading();
        ModuleManager.makeRequest('ispconfig', 'get_website_details')
            .then(response => {
                this.showResult(response.success, response.message);
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler beim Laden der Website-Details');
                this.hideLoading();
            });
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
        ModuleManager.makeRequest('ispconfig', 'getContent')
            .then(response => {
                if (response.success) {
                    // Inhalt in den ISPConfig-Container einfügen
                    const contentDiv = document.getElementById('ispconfig-content');
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
                        if (window.ispconfigModule) {
                            window.ispconfigModule.init();
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
        console.log('ISPConfig Module initialized');
        
        // Event-Listener für Formulare hinzufügen
        this.setupEventListeners();
    },
    
    /**
     * Richtet Event-Listener ein
     */
    setupEventListeners: function() {
        // Event-Listener für Formulare
        document.addEventListener('submit', (e) => {
            if (e.target.classList.contains('ispconfig-form')) {
                e.preventDefault();
                this.handleFormSubmit(e.target);
            }
        });
        
        // Event-Listener für Buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('ispconfig-action-btn')) {
                e.preventDefault();
                this.handleButtonClick(e.target);
            }
        });
    },
    
    /**
     * Behandelt Formular-Submits
     */
    handleFormSubmit: function(form) {
        const formData = new FormData(form);
        const data = {};
        
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }
        
        const action = data.action;
        delete data.action;
        
        switch (action) {
            case 'create_website':
                this.createWebsite(data);
                break;
            case 'create_ftp_user':
                this.createFTPUser(data);
                break;
            case 'create_subdomain':
                this.createSubdomain(data);
                break;
        }
    },
    
    /**
     * Behandelt Button-Klicks
     */
    handleButtonClick: function(button) {
        const action = button.dataset.action;
        
        switch (action) {
            case 'load_clients':
                this.loadISPConfigClients();
                break;
            case 'load_server_config':
                this.loadServerConfig();
                break;
            case 'show_website_details':
                this.showWebsiteDetails();
                break;
        }
    }
};

// Modul sofort initialisieren, wenn das Script geladen wird
if (window.ispconfigModule) {
    window.ispconfigModule.init();
} 