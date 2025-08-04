/**
 * OVH Module JavaScript
 * Verwaltet alle AJAX-Requests für das OVH-Modul
 */

window.ovhModule = {
    /**
     * Bestellt eine Domain
     */
    orderDomain: function(formData) {
        this.showLoading();
        ModuleManager.makeRequest('ovh', 'order_domain', formData)
            .then(response => {
                this.showResult(response.success, response.message);
                if (response.success) {
                    this.reloadContent();
                }
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler beim Bestellen der Domain');
                this.hideLoading();
            });
    },
    
    /**
     * Ruft VPS-Informationen ab
     */
    getVPSInfo: function(formData) {
        this.showLoading();
        ModuleManager.makeRequest('ovh', 'get_vps_info', formData)
            .then(response => {
                this.showResult(response.success, response.message);
                if (response.success) {
                    this.reloadContent();
                }
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler beim Abrufen der VPS-Informationen');
                this.hideLoading();
            });
    },
    
    /**
     * Erstellt einen DNS-Record
     */
    createDNSRecord: function(formData) {
        this.showLoading();
        ModuleManager.makeRequest('ovh', 'create_dns_record', formData)
            .then(response => {
                this.showResult(response.success, response.message);
                if (response.success) {
                    this.reloadContent();
                }
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler beim Erstellen des DNS-Records');
                this.hideLoading();
            });
    },
    
    /**
     * Steuert einen VPS
     */
    controlVPS: function(formData) {
        this.showLoading();
        ModuleManager.makeRequest('ovh', 'control_ovh_vps', formData)
            .then(response => {
                this.showResult(response.success, response.message);
                if (response.success) {
                    this.reloadContent();
                }
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler bei der VPS-Steuerung');
                this.hideLoading();
            });
    },
    
    /**
     * Lädt Failover-IPs
     */
    loadFailoverIPs: function() {
        this.showLoading();
        ModuleManager.makeRequest('ovh', 'get_ovh_failover_ips')
            .then(response => {
                this.showResult(response.success, response.message);
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler beim Laden der Failover-IPs');
                this.hideLoading();
            });
    },
    
    /**
     * Prüft Domain-Verfügbarkeit
     */
    checkDomainAvailability: function() {
        this.showLoading();
        ModuleManager.makeRequest('ovh', 'check_domain_availability')
            .then(response => {
                this.showResult(response.success, response.message);
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler beim Prüfen der Domain-Verfügbarkeit');
                this.hideLoading();
            });
    },
    
    /**
     * Zeigt DNS-Records an
     */
    showDNSRecords: function() {
        this.showLoading();
        ModuleManager.makeRequest('ovh', 'get_ovh_dns_records')
            .then(response => {
                this.showResult(response.success, response.message);
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler beim Laden der DNS-Records');
                this.hideLoading();
            });
    },
    
    /**
     * Aktualisiert DNS-Zone
     */
    refreshDNSZone: function() {
        this.showLoading();
        ModuleManager.makeRequest('ovh', 'refresh_dns_zone')
            .then(response => {
                this.showResult(response.success, response.message);
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler beim Aktualisieren der DNS-Zone');
                this.hideLoading();
            });
    },
    
    /**
     * Generiert MAC-Adresse für IP
     */
    generateMacAddress: function(ip) {
        this.showLoading();
        ModuleManager.makeRequest('ovh', 'create_ovh_virtual_mac', { ip: ip })
            .then(response => {
                this.showResult(response.success, response.message);
                if (response.success) {
                    this.reloadContent();
                }
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler beim Generieren der MAC-Adresse');
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
        ModuleManager.makeRequest('ovh', 'getContent')
            .then(response => {
                if (response.success) {
                    // Inhalt in den OVH-Container einfügen
                    const contentDiv = document.getElementById('ovh-content');
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
                        if (window.ovhModule) {
                            window.ovhModule.init();
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
        console.log('OVH Module initialized');
        
        // Event-Listener für Formulare hinzufügen
        this.setupEventListeners();
    },
    
    /**
     * Richtet Event-Listener ein
     */
    setupEventListeners: function() {
        // Event-Listener für Formulare
        document.addEventListener('submit', (e) => {
            if (e.target.classList.contains('ovh-form')) {
                e.preventDefault();
                this.handleFormSubmit(e.target);
            }
        });
        
        // Event-Listener für Buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('ovh-action-btn')) {
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
            case 'order_domain':
                this.orderDomain(data);
                break;
            case 'get_vps_info':
                this.getVPSInfo(data);
                break;
            case 'create_dns_record':
                this.createDNSRecord(data);
                break;
            case 'control_vps':
                this.controlVPS(data);
                break;
        }
    },
    
    /**
     * Behandelt Button-Klicks
     */
    handleButtonClick: function(button) {
        const action = button.dataset.action;
        const ip = button.dataset.ip;
        
        switch (action) {
            case 'load_failover_ips':
                this.loadFailoverIPs();
                break;
            case 'check_domain_availability':
                this.checkDomainAvailability();
                break;
            case 'show_dns_records':
                this.showDNSRecords();
                break;
            case 'refresh_dns_zone':
                this.refreshDNSZone();
                break;
            case 'generate_mac_address':
                this.generateMacAddress(ip);
                break;
        }
    }
};

// Modul sofort initialisieren, wenn das Script geladen wird
if (window.ovhModule) {
    window.ovhModule.init();
} 