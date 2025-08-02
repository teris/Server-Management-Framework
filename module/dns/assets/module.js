/**
 * DNS Module JavaScript
 * Verwaltet alle AJAX-Requests für das DNS-Modul
 */

window.dnsModule = {
    currentDomain: null,
    
    /**
     * Testet die OVH API-Verbindung
     */
    testApi: function() {
        this.showLoading();
        ModuleManager.makeRequest('dns', 'test_api')
            .then(response => {
                this.showResult(response.success, response.message);
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler beim Testen der API-Verbindung');
                this.hideLoading();
            });
    },
    
    /**
     * Lädt alle Domains
     */
    loadDomains: function() {
        this.showLoading();
        ModuleManager.makeRequest('dns', 'load_domains')
            .then(response => {
                if (response.success) {
                    // Lade nur die Domainliste neu, ohne automatisch eine Domain auszuwählen
                    this.reloadContent();
                } else {
                    this.showResult(false, response.message);
                }
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler beim Laden der Domains');
                this.hideLoading();
            });
    },
    
    /**
     * Wählt eine Domain aus
     */
    selectDomain: function(domain) {
        if (!domain) return;
        
        this.currentDomain = domain;
        this.showLoading();
        
        // URL mit GET-Parameter aktualisieren
        const url = new URL(window.location);
        url.searchParams.set('domain', domain);
        window.history.pushState({}, '', url);
        
        // Verwende die neue select_domain Aktion für spezifische Domain-Daten
        ModuleManager.makeRequest('dns', 'select_domain', {
            domain: domain
        })
            .then(response => {
                if (response.success) {
                    // Inhalt in den DNS-Container einfügen
                    const contentDiv = document.getElementById('dns-content');
                    if (contentDiv) {
                        contentDiv.innerHTML = response.content;
                        
                        // Event-Listener neu einrichten
                        this.setupEventListeners();
                    }
                    
                    // Zeige Erfolgsmeldung mit Domain-Informationen
                    this.showResult(true, `Domain "${domain}" ausgewählt - ${response.dnsRecordsCount} DNS-Records geladen`);
                } else {
                    this.showResult(false, response.message);
                }
                this.hideLoading();
            })
            .catch(error => {
                console.error('Fehler beim Laden der Domain-Daten:', error);
                this.showResult(false, 'Fehler beim Laden der Domain-Daten');
                this.hideLoading();
            });
    },
    
    /**
     * Fügt einen DNS-Record hinzu
     */
    addRecord: function(formData) {
        this.showLoading();
        ModuleManager.makeRequest('dns', 'add_record', formData)
            .then(response => {
                this.showResult(response.success, response.message);
                if (response.success) {
                    this.reloadContent();
                }
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler beim Hinzufügen des Records');
                this.hideLoading();
            });
    },
    
    /**
     * Bearbeitet einen DNS-Record
     */
    editRecord: function(formData) {
        this.showLoading();
        ModuleManager.makeRequest('dns', 'edit_record', formData)
            .then(response => {
                this.showResult(response.success, response.message);
                if (response.success) {
                    this.reloadContent();
                }
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler beim Bearbeiten des Records');
                this.hideLoading();
            });
    },
    
    /**
     * Löscht einen DNS-Record
     */
    deleteRecord: function(recordId) {
        if (!confirm('Möchten Sie diesen Record wirklich löschen?')) {
            return;
        }
        
        this.showLoading();
        ModuleManager.makeRequest('dns', 'delete_record', {
            domain: this.currentDomain,
            recordId: recordId
        })
            .then(response => {
                this.showResult(response.success, response.message);
                if (response.success) {
                    this.reloadContent();
                }
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler beim Löschen des Records');
                this.hideLoading();
            });
    },
    
    /**
     * Aktualisiert die DNS-Zone
     */
    refreshZone: function() {
        this.showLoading();
        ModuleManager.makeRequest('dns', 'refresh_zone', {
            domain: this.currentDomain
        })
            .then(response => {
                this.showResult(response.success, response.message);
                if (response.success) {
                    this.reloadContent();
                }
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler beim Aktualisieren der Zone');
                this.hideLoading();
            });
    },
    
    /**
     * Exportiert die DNS-Zone
     */
    exportZone: function() {
        this.showLoading();
        ModuleManager.makeRequest('dns', 'export_zone', {
            domain: this.currentDomain
        })
            .then(response => {
                this.showResult(response.success, response.message);
                if (response.success) {
                    this.reloadContent();
                }
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler beim Exportieren der Zone');
                this.hideLoading();
            });
    },
    
    /**
     * Importiert eine DNS-Zone
     */
    importZone: function(zoneContent) {
        if (!confirm('Möchten Sie die Zone wirklich importieren? Dies überschreibt alle bestehenden Records.')) {
            return;
        }
        
        this.showLoading();
        ModuleManager.makeRequest('dns', 'import_zone', {
            domain: this.currentDomain,
            zoneContent: zoneContent
        })
            .then(response => {
                this.showResult(response.success, response.message);
                if (response.success) {
                    this.reloadContent();
                }
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler beim Importieren der Zone');
                this.hideLoading();
            });
    },
    
    /**
     * Aktiviert DNSSEC
     */
    enableDnssec: function() {
        this.showLoading();
        ModuleManager.makeRequest('dns', 'enable_dnssec', {
            domain: this.currentDomain
        })
            .then(response => {
                this.showResult(response.success, response.message);
                if (response.success) {
                    this.reloadContent();
                }
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler beim Aktivieren von DNSSEC');
                this.hideLoading();
            });
    },
    
    /**
     * Deaktiviert DNSSEC
     */
    disableDnssec: function() {
        if (!confirm('Möchten Sie DNSSEC wirklich deaktivieren?')) {
            return;
        }
        
        this.showLoading();
        ModuleManager.makeRequest('dns', 'disable_dnssec', {
            domain: this.currentDomain
        })
            .then(response => {
                this.showResult(response.success, response.message);
                if (response.success) {
                    this.reloadContent();
                }
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler beim Deaktivieren von DNSSEC');
                this.hideLoading();
            });
    },
    
    /**
     * Fügt einen DNSSEC-Schlüssel hinzu
     */
    addDnssecKey: function(formData) {
        this.showLoading();
        ModuleManager.makeRequest('dns', 'add_dnssec_key', formData)
            .then(response => {
                this.showResult(response.success, response.message);
                if (response.success) {
                    this.reloadContent();
                }
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler beim Hinzufügen des DNSSEC-Schlüssels');
                this.hideLoading();
            });
    },
    
    /**
     * Löscht einen DNSSEC-Schlüssel
     */
    deleteDnssecKey: function(keyId) {
        if (!confirm('Möchten Sie diesen DNSSEC-Schlüssel wirklich löschen?')) {
            return;
        }
        
        this.showLoading();
        ModuleManager.makeRequest('dns', 'delete_dnssec_key', {
            domain: this.currentDomain,
            keyId: keyId
        })
            .then(response => {
                this.showResult(response.success, response.message);
                if (response.success) {
                    this.reloadContent();
                }
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler beim Löschen des DNSSEC-Schlüssels');
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
     * Zeigt Loading-Animation mit Progress Bar
     */
    showLoading: function() {
        const bar = document.getElementById('dns-progressbar');
        const inner = document.getElementById('dns-progressbar-inner');
        if (!bar || !inner) return;
        
        bar.style.display = 'block';
        inner.style.width = '0%';
        inner.innerText = '0%';
        
        // Simulierter Fortschritt
        this.progressInterval = setInterval(function() {
            const currentWidth = parseInt(inner.style.width) || 0;
            const increment = Math.floor(Math.random() * 8) + 2;
            const newWidth = Math.min(currentWidth + increment, 90);
            inner.style.width = newWidth + '%';
            inner.innerText = newWidth + '%';
        }, 80);
    },
    
    /**
     * Versteckt Loading-Animation
     */
    hideLoading: function() {
        const bar = document.getElementById('dns-progressbar');
        const inner = document.getElementById('dns-progressbar-inner');
        if (!bar || !inner) return;
        
        // Progress auf 100% setzen
        inner.style.width = '100%';
        inner.innerText = '100%';
        
        // Interval stoppen
        if (this.progressInterval) {
            clearInterval(this.progressInterval);
            this.progressInterval = null;
        }
        
        // Progress Bar nach kurzer Verzögerung ausblenden
        setTimeout(function() {
            bar.style.display = 'none';
        }, 400);
    },
    
    /**
     * Lädt den Modul-Inhalt neu
     */
    reloadContent: function() {
        this.showLoading();
        
        // URL-Parameter für Domain-Auswahl setzen
        const url = new URL(window.location);
        if (this.currentDomain) {
            url.searchParams.set('domain', this.currentDomain);
        } else {
            url.searchParams.delete('domain');
        }
        
        // Modul-Inhalt über AJAX neu laden
        ModuleManager.makeRequest('dns', 'getContent')
            .then(response => {
                if (response.success) {
                    // Inhalt in den DNS-Container einfügen
                    const contentDiv = document.getElementById('dns-content');
                    if (contentDiv) {
                        contentDiv.innerHTML = response.content;
                        
                        // Event-Listener neu einrichten
                        this.setupEventListeners();
                        
                        // Wenn eine Domain ausgewählt ist, zeige eine Meldung
                        if (this.currentDomain) {
                            this.showResult(true, `Modul-Inhalt neu geladen für Domain: ${this.currentDomain}`);
                        } else {
                            this.showResult(true, 'Modul-Inhalt neu geladen - Bitte wählen Sie eine Domain aus');
                        }
                    }
                }
                this.hideLoading();
            })
            .catch(error => {
                console.error('Fehler beim Neuladen des Inhalts:', error);
                this.showResult(false, 'Fehler beim Neuladen des Inhalts');
                this.hideLoading();
            });
    },
    
    /**
     * Initialisiert das Modul
     */
    init: function() {
        console.log('DNS Module initialized');
        
        // Event-Listener für Formulare hinzufügen
        this.setupEventListeners();
        
        // Prüfe, ob bereits eine Domain in der URL ausgewählt ist
        const urlParams = new URLSearchParams(window.location.search);
        const selectedDomain = urlParams.get('domain');
        
        if (selectedDomain) {
            // Wenn eine Domain in der URL steht, wähle sie aus
            this.currentDomain = selectedDomain;
            this.selectDomain(selectedDomain);
        } else {
            // Automatisch Domainliste laden beim Modul-Start, aber nur wenn noch keine Domains geladen sind
            const domainSelect = document.getElementById('domain-select');
            if (!domainSelect || domainSelect.options.length <= 1) {
                this.loadDomains();
            }
        }
    },
    
    /**
     * Richtet Event-Listener ein
     */
    setupEventListeners: function() {
        // Event-Listener für Formulare
        document.addEventListener('submit', (e) => {
            if (e.target.classList.contains('dns-form')) {
                e.preventDefault();
                this.handleFormSubmit(e.target);
            }
        });
        
        // Event-Listener für Buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('dns-action-btn')) {
                e.preventDefault();
                this.handleButtonClick(e.target);
            }
        });
        
        // Event-Listener für Domain-Auswahl
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('dns-domain-select')) {
                this.selectDomain(e.target.value);
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
            case 'add_record':
                this.addRecord(data);
                break;
            case 'edit_record':
                this.editRecord(data);
                break;
            case 'import_zone':
                this.importZone(data.zoneContent);
                break;
            case 'add_dnssec_key':
                this.addDnssecKey(data);
                break;
        }
    },
    
    /**
     * Behandelt Button-Klicks
     */
    handleButtonClick: function(button) {
        const action = button.dataset.action;
        const recordId = button.dataset.recordId;
        const keyId = button.dataset.keyId;
        
        switch (action) {
            case 'test_api':
                this.testApi();
                break;
            case 'load_domains':
                this.loadDomains();
                break;
            case 'show_add_form':
                this.showAddRecordForm();
                break;
            case 'show_add_dnssec_key_form':
                this.showAddDnssecKeyForm();
                break;
            case 'edit_record':
                this.showEditRecordForm(button.dataset);
                break;
            case 'delete_record':
                this.deleteRecord(recordId);
                break;
            case 'refresh_zone':
                this.refreshZone();
                break;
            case 'export_zone':
                this.exportZone();
                break;
            case 'import_zone':
                const zoneContent = document.getElementById('zone-import-text').value;
                this.importZone(zoneContent);
                break;
            case 'enable_dnssec':
                this.enableDnssec();
                break;
            case 'disable_dnssec':
                this.disableDnssec();
                break;
            case 'delete_dnssec_key':
                this.deleteDnssecKey(keyId);
                break;
            case 'hide_forms':
                this.hideForms();
                break;
        }
    },
    
    /**
     * Zeigt das Formular zum Hinzufügen eines Records an
     */
    showAddRecordForm: function() {
        const formHtml = `
            <div class="add-record-section">
                <div class="card">
                    <div class="card-header">
                        <h3>Record hinzufügen</h3>
                    </div>
                    <div class="card-body">
                        <form class="dns-form">
                            <input type="hidden" name="action" value="add_record">
                            <input type="hidden" name="domain" value="${this.currentDomain}">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="record-type">Record-Typ *</label>
                                        <select id="record-type" name="recordType" class="form-control" required>
                                            <option value="A">A</option>
                                            <option value="AAAA">AAAA</option>
                                            <option value="CNAME">CNAME</option>
                                            <option value="MX">MX</option>
                                            <option value="NS">NS</option>
                                            <option value="PTR">PTR</option>
                                            <option value="SRV">SRV</option>
                                            <option value="TXT">TXT</option>
                                            <option value="CAA">CAA</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="subdomain">Subdomain</label>
                                        <input type="text" id="subdomain" name="subdomain" class="form-control" placeholder="www">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="target">Ziel *</label>
                                        <input type="text" id="target" name="target" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="ttl">TTL</label>
                                        <input type="number" id="ttl" name="ttl" class="form-control" value="3600" min="60" max="86400">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="priority">Priorität</label>
                                        <input type="number" id="priority" name="priority" class="form-control" min="0" max="65535">
                                        <small class="form-text text-muted">Nur für MX und SRV Records erforderlich</small>
                                    </div>
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary dns-action-btn" data-action="hide_forms">Abbrechen</button>
                                <button type="submit" class="btn btn-primary">Speichern</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        const container = document.getElementById('dns-content');
        container.insertAdjacentHTML('beforeend', formHtml);
    },
    
    /**
     * Zeigt das Formular zum Bearbeiten eines Records an
     */
    showEditRecordForm: function(data) {
        const formHtml = `
            <div class="edit-record-section">
                <div class="card">
                    <div class="card-header">
                        <h3>Record bearbeiten</h3>
                    </div>
                    <div class="card-body">
                        <form class="dns-form">
                            <input type="hidden" name="action" value="edit_record">
                            <input type="hidden" name="domain" value="${this.currentDomain}">
                            <input type="hidden" name="recordId" value="${data.recordId}">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="edit-record-type">Record-Typ *</label>
                                        <select id="edit-record-type" name="recordType" class="form-control" required>
                                            <option value="A" ${data.recordType === 'A' ? 'selected' : ''}>A</option>
                                            <option value="AAAA" ${data.recordType === 'AAAA' ? 'selected' : ''}>AAAA</option>
                                            <option value="CNAME" ${data.recordType === 'CNAME' ? 'selected' : ''}>CNAME</option>
                                            <option value="MX" ${data.recordType === 'MX' ? 'selected' : ''}>MX</option>
                                            <option value="NS" ${data.recordType === 'NS' ? 'selected' : ''}>NS</option>
                                            <option value="PTR" ${data.recordType === 'PTR' ? 'selected' : ''}>PTR</option>
                                            <option value="SRV" ${data.recordType === 'SRV' ? 'selected' : ''}>SRV</option>
                                            <option value="TXT" ${data.recordType === 'TXT' ? 'selected' : ''}>TXT</option>
                                            <option value="CAA" ${data.recordType === 'CAA' ? 'selected' : ''}>CAA</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="edit-subdomain">Subdomain</label>
                                        <input type="text" id="edit-subdomain" name="subdomain" class="form-control" placeholder="www" value="${data.subdomain || ''}">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="edit-target">Ziel *</label>
                                        <input type="text" id="edit-target" name="target" class="form-control" required value="${data.target || ''}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="edit-ttl">TTL</label>
                                        <input type="number" id="edit-ttl" name="ttl" class="form-control" value="${data.ttl || '3600'}" min="60" max="86400">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="edit-priority">Priorität</label>
                                        <input type="number" id="edit-priority" name="priority" class="form-control" min="0" max="65535" value="${data.priority || ''}">
                                        <small class="form-text text-muted">Nur für MX und SRV Records erforderlich</small>
                                    </div>
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary dns-action-btn" data-action="hide_forms">Abbrechen</button>
                                <button type="submit" class="btn btn-primary">Speichern</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        const container = document.getElementById('dns-content');
        container.insertAdjacentHTML('beforeend', formHtml);
    },
    
    /**
     * Zeigt das Formular zum Hinzufügen eines DNSSEC-Schlüssels an
     */
    showAddDnssecKeyForm: function() {
        const formHtml = `
            <div class="add-dnssec-key-section">
                <div class="card">
                    <div class="card-header">
                        <h3>Schlüssel hinzufügen</h3>
                    </div>
                    <div class="card-body">
                        <form class="dns-form">
                            <input type="hidden" name="action" value="add_dnssec_key">
                            <input type="hidden" name="domain" value="${this.currentDomain}">
                            <div class="form-group">
                                <label for="key-type">Schlüssel-Typ *</label>
                                <select id="key-type" name="keyType" class="form-control" required>
                                    <option value="KSK">KSK (Key Signing Key)</option>
                                    <option value="ZSK">ZSK (Zone Signing Key)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="key-algorithm">Algorithmus *</label>
                                <select id="key-algorithm" name="algorithm" class="form-control" required>
                                    <option value="5">RSA/SHA-1</option>
                                    <option value="7">RSASHA1-NSEC3-SHA1</option>
                                    <option value="8">RSA/SHA-256</option>
                                    <option value="10">RSA/SHA-512</option>
                                    <option value="13">ECDSA/SHA-256</option>
                                    <option value="14">ECDSA/SHA-384</option>
                                    <option value="15">Ed25519</option>
                                    <option value="16">Ed448</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="key-size">Schlüssel-Größe *</label>
                                <input type="number" id="key-size" name="keySize" class="form-control" required min="512" max="4096">
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn btn-secondary dns-action-btn" data-action="hide_forms">Abbrechen</button>
                                <button type="submit" class="btn btn-primary">Speichern</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        const container = document.getElementById('dns-content');
        container.insertAdjacentHTML('beforeend', formHtml);
    },
    
    /**
     * Versteckt alle Formulare
     */
    hideForms: function() {
        const forms = document.querySelectorAll('.add-record-section, .edit-record-section, .add-dnssec-key-section');
        forms.forEach(form => form.remove());
    }
};

// Modul sofort initialisieren, wenn das Script geladen wird
if (window.dnsModule) {
    window.dnsModule.init();
} 