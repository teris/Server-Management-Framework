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
     * Lädt Domains im Hintergrund ohne Loading-Animation
     */
    loadDomainsSilently: function() {
        ModuleManager.makeRequest('dns', 'load_domains')
            .then(response => {
                if (response.success && response.data) {
                    console.log('Domains geladen:', response.data);
                    // Aktualisiere nur die Domain-Auswahl, ohne den gesamten Inhalt neu zu laden
                    this.updateDomainSelect(response.data);
                } else {
                    console.error('Fehler beim Laden der Domains:', response.message);
                }
            })
            .catch(error => {
                console.error('Fehler beim Laden der Domains:', error);
            })
            .finally(() => {
                // Reset des Loading-Flags
                this.isLoadingDomains = false;
            });
    },
    
    /**
     * Aktualisiert die Domain-Auswahl ohne Neuladen des gesamten Inhalts
     */
    updateDomainSelect: function(domains) {
        // Versuche verschiedene Selektoren für das Domain-Select Element
        let domainSelect = document.getElementById('domain-select');
        
        // Fallback: Suche nach anderen möglichen Selektoren
        if (!domainSelect) {
            domainSelect = document.querySelector('select.dns-domain-select');
        }
        if (!domainSelect) {
            domainSelect = document.querySelector('select[class*="domain"]');
        }
        if (!domainSelect) {
            domainSelect = document.querySelector('select[name="domain"]');
        }
        
        if (!domainSelect) {
            console.error('Domain-Select Element nicht gefunden - erstelle temporäres Element');
            // Erstelle ein temporäres Select-Element, falls keins existiert
            this.createTemporaryDomainSelect(domains);
            return;
        }
        
        // Reset der Wiederholungszähler
        this.domainUpdateRetries = 0;
        
        if (!domains || !Array.isArray(domains)) {
            console.error('Keine gültigen Domains erhalten:', domains);
            return;
        }
        
        console.log('Aktualisiere Domain-Auswahl mit:', domains.length, 'Domains');
        
        // Leere die aktuelle Auswahl (außer der ersten Option)
        while (domainSelect.children.length > 1) {
            domainSelect.removeChild(domainSelect.lastChild);
        }
        
        // Füge neue Domains hinzu
        domains.forEach(domain => {
            const option = document.createElement('option');
            option.value = domain;
            option.textContent = domain;
            domainSelect.appendChild(option);
        });
        
        // Verstecke die "Keine Domains verfügbar" Meldung
        const noDomainsAlert = domainSelect.parentNode.querySelector('.alert-info');
        if (noDomainsAlert) {
            noDomainsAlert.style.display = 'none';
        }
        
        console.log('Domain-Auswahl erfolgreich aktualisiert. Anzahl Optionen:', domainSelect.children.length);
        
        // Zeige eine Erfolgsmeldung
        this.showResult(true, `${domains.length} Domains geladen`);
        
        // Prüfe, ob es ausstehende Domains gibt
        if (this.pendingDomains) {
            console.log('Verarbeite ausstehende Domains...');
            this.updateDomainSelect(this.pendingDomains);
            this.pendingDomains = null;
        }
    },
    
    /**
     * Erstellt ein temporäres Domain-Select Element
     */
    createTemporaryDomainSelect: function(domains) {
        console.log('Erstelle temporäres Domain-Select Element...');
        
        // Suche nach dem Domain-Selection-Container
        let container = document.querySelector('.domain-selection-section .card-body');
        if (!container) {
            container = document.querySelector('.domain-selection-section');
        }
        if (!container) {
            container = document.querySelector('#dns-content');
        }
        if (!container) {
            container = document.body;
        }
        
        // Erstelle Ladebalken-Container
        const loadingContainer = document.createElement('div');
        loadingContainer.id = 'domain-loading-container';
        loadingContainer.className = 'domain-loading-container';
        loadingContainer.innerHTML = `
            <div class="loading-content">
                <div class="loading-spinner">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Lädt...</span>
                    </div>
                </div>
                <div class="loading-text">
                    <h5>Domains werden geladen...</h5>
                    <p class="text-muted">Bitte warten Sie, während wir Ihre Domains abrufen.</p>
                </div>
                <div class="loading-progress">
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                             role="progressbar" style="width: 0%" id="domain-loading-progress">
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Füge Ladebalken zum Container hinzu
        container.appendChild(loadingContainer);
        
        // Simuliere Ladevorgang mit Fortschrittsbalken
        this.animateDomainLoading(domains, container, loadingContainer);
    },
    
    /**
     * Animiert den Domain-Ladevorgang
     */
    animateDomainLoading: function(domains, container, loadingContainer) {
        const progressBar = document.getElementById('domain-loading-progress');
        const loadingText = loadingContainer.querySelector('.loading-text h5');
        const loadingSubtext = loadingContainer.querySelector('.loading-text p');
        
        let progress = 0;
        const totalSteps = 100;
        const stepDuration = 30; // 30ms pro Schritt = 3 Sekunden total
        
        const loadingSteps = [
            { progress: 20, text: 'Verbindung zur OVH API...', subtext: 'Stelle Verbindung her...' },
            { progress: 40, text: 'Domains werden abgerufen...', subtext: 'Lade Domain-Liste...' },
            { progress: 60, text: 'Daten werden verarbeitet...', subtext: 'Verarbeite ${domains.length} Domains...' },
            { progress: 80, text: 'Dropdown wird erstellt...', subtext: 'Erstelle Auswahlmenü...' },
            { progress: 100, text: 'Fertig!', subtext: '${domains.length} Domains erfolgreich geladen' }
        ];
        
        let currentStep = 0;
        
        const updateProgress = () => {
            if (currentStep < loadingSteps.length) {
                const step = loadingSteps[currentStep];
                progress = step.progress;
                
                // Aktualisiere Fortschrittsbalken
                progressBar.style.width = progress + '%';
                
                // Aktualisiere Text
                loadingText.textContent = step.text;
                loadingSubtext.textContent = step.subtext.replace('${domains.length}', domains.length);
                
                currentStep++;
                
                if (currentStep < loadingSteps.length) {
                    setTimeout(updateProgress, stepDuration * 20); // 20 Schritte pro Text-Update
                } else {
                    // Ladevorgang abgeschlossen
                    setTimeout(() => {
                        this.createDomainSelectElement(domains, container, loadingContainer);
                    }, 500);
                }
            }
        };
        
        // Starte Animation
        updateProgress();
    },
    
    /**
     * Erstellt das eigentliche Domain-Select Element
     */
    createDomainSelectElement: function(domains, container, loadingContainer) {
        // Entferne Ladebalken mit Fade-out-Animation
        if (loadingContainer && loadingContainer.parentNode) {
            loadingContainer.style.transition = 'opacity 0.3s ease';
            loadingContainer.style.opacity = '0';
            setTimeout(() => {
                if (loadingContainer.parentNode) {
                    loadingContainer.parentNode.removeChild(loadingContainer);
                }
            }, 300);
        }
        
        // Erstelle das Select-Element
        const domainSelect = document.createElement('select');
        domainSelect.id = 'domain-select';
        domainSelect.className = 'form-control dns-domain-select';
        
        // Füge Platzhalter-Option hinzu
        const placeholderOption = document.createElement('option');
        placeholderOption.value = '';
        placeholderOption.textContent = 'Domain auswählen';
        domainSelect.appendChild(placeholderOption);
        
        // Füge Domains hinzu
        if (domains && Array.isArray(domains)) {
            domains.forEach(domain => {
                const option = document.createElement('option');
                option.value = domain;
                option.textContent = domain;
                domainSelect.appendChild(option);
            });
        }
        
        // Erstelle Label
        const label = document.createElement('label');
        label.setAttribute('for', 'domain-select');
        label.textContent = 'Domain auswählen';
        
        // Erstelle Form-Group mit Fade-in-Animation
        const formGroup = document.createElement('div');
        formGroup.className = 'form-group mt-3 domain-select-fade-in';
        formGroup.style.opacity = '0';
        formGroup.appendChild(label);
        formGroup.appendChild(domainSelect);
        
        // Füge zum Container hinzu
        container.appendChild(formGroup);
        
        // Trigger Fade-in-Animation
        setTimeout(() => {
            formGroup.style.opacity = '1';
        }, 100);
        
        console.log('Temporäres Domain-Select Element erstellt mit', domains.length, 'Domains');
        
        // Verstecke Domain-Buttons initial (keine Domain ausgewählt)
        this.hideDomainButtons();
        
        // Zeige Erfolgsmeldung
        this.showResult(true, `${domains.length} Domains erfolgreich geladen`);
    },
    
    /**
     * Lädt nur die DNS-Records für die aktuelle Domain neu
     */
    loadDNSRecordsOnly: function() {
        if (!this.currentDomain) return;
        
        ModuleManager.makeRequest('dns', 'select_domain', {
            domain: this.currentDomain
        })
        .then(response => {
            if (response.success) {
                // Aktualisiere nur die DNS-Records-Tabelle
                const contentDiv = document.getElementById('dns-content');
                if (contentDiv) {
                    // Extrahiere nur den DNS-Records-Bereich aus der Antwort
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(response.content, 'text/html');
                    const newRecordsSection = doc.querySelector('.dns-records-section');
                    const currentRecordsSection = contentDiv.querySelector('.dns-records-section');
                    
                    if (newRecordsSection && currentRecordsSection) {
                        currentRecordsSection.innerHTML = newRecordsSection.innerHTML;
                        // Event-Listener neu einrichten
                        this.setupEventListeners();
                    }
                }
            }
        })
        .catch(error => {
            console.error('Fehler beim Laden der DNS-Records:', error);
        });
    },
    
    /**
     * Wählt eine Domain aus
     */
    selectDomain: function(domain) {
        if (!domain) {
            this.hideDomainButtons();
            return;
        }
        
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
                    
                    // Zeige Domain-spezifische Buttons
                    this.showDomainButtons();
                    
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
     * Zeigt Domain-spezifische Buttons an
     */
    showDomainButtons: function() {
        const headerActions = document.getElementById('dns-header-actions');
        const zoneSection = document.getElementById('zone-management-section');
        
        if (headerActions) {
            headerActions.style.display = 'block';
        }
        
        if (zoneSection) {
            zoneSection.style.display = 'block';
        }
    },
    
    /**
     * Versteckt Domain-spezifische Buttons
     */
    hideDomainButtons: function() {
        const headerActions = document.getElementById('dns-header-actions');
        const zoneSection = document.getElementById('zone-management-section');
        
        if (headerActions) {
            headerActions.style.display = 'none';
        }
        
        if (zoneSection) {
            zoneSection.style.display = 'none';
        }
    },
    
    /**
     * Fügt einen DNS-Record hinzu
     */
    addRecord: function(formData) {
        // Prüfe, ob bereits ein Add-Vorgang läuft
        if (this.isAddingRecord) {
            console.log('Add-Record-Vorgang bereits aktiv, überspringe...');
            return;
        }
        
        this.isAddingRecord = true;
        this.showLoading();
        
        ModuleManager.makeRequest('dns', 'add_record', formData)
            .then(response => {
                this.showResult(response.success, response.message);
                if (response.success) {
                    // Verstecke das Formular
                    this.hideForms();
                    // Lade nur die DNS-Records neu, nicht den gesamten Inhalt
                    this.loadDNSRecordsOnly();
                }
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler beim Hinzufügen des Records');
                this.hideLoading();
            })
            .finally(() => {
                // Reset des Add-Flags
                this.isAddingRecord = false;
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
        // Prüfe, ob bereits ein Löschvorgang läuft
        if (this.isDeletingRecord) {
            console.log('Löschvorgang bereits aktiv, überspringe...');
            return;
        }
        
        if (!confirm('Möchten Sie diesen Record wirklich löschen?')) {
            return;
        }
        
        this.isDeletingRecord = true;
        this.showLoading();
        
        ModuleManager.makeRequest('dns', 'delete_record', {
            domain: this.currentDomain,
            recordId: recordId
        })
            .then(response => {
                this.showResult(response.success, response.message);
                if (response.success) {
                    // Lade nur die DNS-Records neu
                    this.loadDNSRecordsOnly();
                }
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler beim Löschen des Records');
                this.hideLoading();
            })
            .finally(() => {
                // Reset des Lösch-Flags
                this.isDeletingRecord = false;
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
                    // Zeige Zone-Content in der Anzeige
                    if (response.zoneContent) {
                        this.displayZoneContent(response.zoneContent);
                    }
                    
                    // Starte Download, falls URL verfügbar
                    if (response.downloadUrl) {
                        this.downloadZoneFile(response.downloadUrl);
                    }
                }
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler beim Exportieren der Zone');
                this.hideLoading();
            });
    },
    
    /**
     * Zeigt Zone-Content in der Anzeige an
     */
    displayZoneContent: function(zoneContent) {
        const zoneTextarea = document.getElementById('zone-import-text');
        if (zoneTextarea) {
            zoneTextarea.value = zoneContent;
            zoneTextarea.rows = Math.max(10, zoneContent.split('\n').length + 2);
        }
    },
    
    /**
     * Startet Download der Zone-Datei
     */
    downloadZoneFile: function(downloadUrl) {
        // Erstelle temporären Link für Download
        const link = document.createElement('a');
        link.href = downloadUrl;
        link.download = this.currentDomain + '_dns_zone.txt';
        link.style.display = 'none';
        
        // Füge zum DOM hinzu und klicke
        document.body.appendChild(link);
        link.click();
        
        // Entferne Link wieder
        document.body.removeChild(link);
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
        
        // Warte auf DOM-Loading, bevor wir fortfahren
        const initializeAfterDOM = () => {
            // Prüfe, ob bereits eine Domain in der URL ausgewählt ist
            const urlParams = new URLSearchParams(window.location.search);
            const selectedDomain = urlParams.get('domain');
            
            if (selectedDomain) {
                // Wenn eine Domain in der URL steht, wähle sie aus
                this.currentDomain = selectedDomain;
                this.selectDomain(selectedDomain);
            } else {
                // Lade Domains automatisch beim Modul-Start
                this.waitForDOMAndLoadDomains();
            }
        };
        
        // Prüfe DOM-Status
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeAfterDOM);
        } else {
            // DOM ist bereits geladen
            initializeAfterDOM();
        }
    },
    
    /**
     * Wartet auf das DOM und lädt dann die Domains
     */
    waitForDOMAndLoadDomains: function() {
        // Prüfe, ob bereits ein Domain-Loading läuft
        if (this.isLoadingDomains) {
            console.log('Domain-Loading bereits aktiv, überspringe...');
            return;
        }
        
        let retryCount = 0;
        const maxRetries = 50; // 5 Sekunden bei 100ms Intervallen
        
        // Verwende DOMContentLoaded Event oder warte auf das Element
        const checkDOM = () => {
            const domainSelect = document.getElementById('domain-select');
            if (domainSelect) {
                console.log('DOM ist bereit, lade Domains...');
                this.isLoadingDomains = true;
                this.loadDomainsSilently();
            } else {
                retryCount++;
                if (retryCount < maxRetries) {
                    console.log(`DOM noch nicht bereit, warte weiter... (${retryCount}/${maxRetries})`);
                    // Debug: Zeige verfügbare Select-Elemente
                    if (retryCount % 10 === 0) {
                        const allSelects = document.querySelectorAll('select');
                        console.log('Verfügbare Select-Elemente:', allSelects.length);
                        allSelects.forEach((select, index) => {
                            console.log(`Select ${index}:`, select.id, select.className, select.name);
                        });
                    }
                    setTimeout(checkDOM, 100);
                } else {
                    console.error('Domain-Select Element nach 5 Sekunden nicht gefunden - lade Domains trotzdem...');
                    // Lade Domains trotzdem, falls das Element später erscheint
                    this.isLoadingDomains = true;
                    this.loadDomainsSilently();
                }
            }
        };
        
        // Prüfe sofort und dann alle 100ms
        checkDOM();
        
        // Zusätzlich: Warte auf DOMContentLoaded Event
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                console.log('DOMContentLoaded Event - prüfe erneut...');
                retryCount = 0; // Reset für DOMContentLoaded
                checkDOM();
            });
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
        
        // Event-Listener für Buttons (delegiert für dynamische Inhalte)
        document.addEventListener('click', (e) => {
            // Verhindere mehrfache Klicks
            if (e.target.disabled || e.target.classList.contains('disabled')) {
                e.preventDefault();
                return;
            }
            
            if (e.target.classList.contains('dns-action-btn')) {
                e.preventDefault();
        // Verhindere mehrfache Klicks auf Action-Buttons
        if (e.target.dataset.action === 'delete_record' && this.isDeletingRecord) {
            return;
        }
        if (e.target.dataset.action === 'show_add_form' && this.isAddingRecord) {
            return;
        }
                this.handleButtonClick(e.target);
            }
            
            // Inline-Bearbeitung Buttons
            if (e.target.classList.contains('edit-btn')) {
                e.preventDefault();
                const recordId = e.target.dataset.recordId;
                if (recordId) {
                    this.startInlineEdit(recordId);
                }
            }
            
            if (e.target.classList.contains('save-btn')) {
                e.preventDefault();
                const recordId = e.target.dataset.recordId;
                if (recordId) {
                    this.saveInlineEdit(recordId);
                }
            }
            
            if (e.target.classList.contains('cancel-btn')) {
                e.preventDefault();
                const recordId = e.target.dataset.recordId;
                if (recordId) {
                    this.cancelInlineEdit(recordId);
                }
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
        
        // Verhindere mehrfache Klicks
        if (button.disabled || button.classList.contains('disabled')) {
            return;
        }
        
        // Deaktiviere Button während der Verarbeitung
        button.disabled = true;
        button.classList.add('disabled');
        
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
        
        // Reaktiviere Button nach kurzer Verzögerung
        setTimeout(() => {
            button.disabled = false;
            button.classList.remove('disabled');
        }, 1000);
    },
    
    /**
     * Zeigt das Formular zum Hinzufügen eines Records an
     */
    showAddRecordForm: function() {
        // Verstecke alle anderen Formulare zuerst
        this.hideForms();
        
        const formHtml = `
            <div class="add-record-section">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3><i class="bi bi-plus-circle"></i> Neuen DNS-Record hinzufügen</h3>
                        <button type="button" class="btn btn-sm btn-outline-secondary dns-action-btn" data-action="hide_forms">
                            <i class="bi bi-x"></i> Schließen
                        </button>
                    </div>
                    <div class="card-body">
                        <form class="dns-form">
                            <input type="hidden" name="action" value="add_record">
                            <input type="hidden" name="domain" value="${this.currentDomain}">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="record-type">Record-Typ *</label>
                                        <select id="record-type" name="recordType" class="form-control" required>
                                            <option value="">Bitte wählen...</option>
                                            <option value="A">A (IPv4-Adresse)</option>
                                            <option value="AAAA">AAAA (IPv6-Adresse)</option>
                                            <option value="CNAME">CNAME (Alias)</option>
                                            <option value="MX">MX (Mail-Server)</option>
                                            <option value="NS">NS (Name Server)</option>
                                            <option value="PTR">PTR (Reverse DNS)</option>
                                            <option value="SRV">SRV (Service)</option>
                                            <option value="TXT">TXT (Text)</option>
                                            <option value="CAA">CAA (Certificate Authority)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="subdomain">Subdomain</label>
                                        <input type="text" id="subdomain" name="subdomain" class="form-control" placeholder="www, mail, ftp...">
                                        <small class="form-text text-muted">Leer lassen für Root-Domain</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="target">Ziel *</label>
                                        <input type="text" id="target" name="target" class="form-control" required placeholder="IP-Adresse oder Hostname">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="ttl">TTL (Sekunden)</label>
                                        <input type="number" id="ttl" name="ttl" class="form-control" value="3600" min="60" max="86400">
                                        <small class="form-text text-muted">Standard: 3600</small>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="priority">Priorität</label>
                                        <input type="number" id="priority" name="priority" class="form-control" min="0" max="65535" placeholder="10">
                                        <small class="form-text text-muted">Nur für MX und SRV</small>
                                    </div>
                                </div>
                                <div class="col-md-9">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-success">
                                                <i class="bi bi-check-circle"></i> Record hinzufügen
                                            </button>
                                            <button type="button" class="btn btn-secondary dns-action-btn" data-action="hide_forms">
                                                <i class="bi bi-x-circle"></i> Abbrechen
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        // Füge das Formular in den Container oben ein
        const formsContainer = document.getElementById('dns-forms-container');
        if (formsContainer) {
            formsContainer.innerHTML = formHtml;
            formsContainer.style.display = 'block';
            
            // Scroll zum Formular
            formsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    },
    
    /**
     * Zeigt das Formular zum Bearbeiten eines Records an
     */
    showEditRecordForm: function(data) {
        // Verstecke alle anderen Formulare zuerst
        this.hideForms();
        
        const formHtml = `
            <div class="edit-record-section">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3><i class="bi bi-pencil-square"></i> DNS-Record bearbeiten</h3>
                        <button type="button" class="btn btn-sm btn-outline-secondary dns-action-btn" data-action="hide_forms">
                            <i class="bi bi-x"></i> Schließen
                        </button>
                    </div>
                    <div class="card-body">
                        <form class="dns-form">
                            <input type="hidden" name="action" value="edit_record">
                            <input type="hidden" name="domain" value="${this.currentDomain}">
                            <input type="hidden" name="recordId" value="${data.recordId}">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="edit-record-type">Record-Typ *</label>
                                        <select id="edit-record-type" name="recordType" class="form-control" required>
                                            <option value="A" ${data.recordType === 'A' ? 'selected' : ''}>A (IPv4-Adresse)</option>
                                            <option value="AAAA" ${data.recordType === 'AAAA' ? 'selected' : ''}>AAAA (IPv6-Adresse)</option>
                                            <option value="CNAME" ${data.recordType === 'CNAME' ? 'selected' : ''}>CNAME (Alias)</option>
                                            <option value="MX" ${data.recordType === 'MX' ? 'selected' : ''}>MX (Mail-Server)</option>
                                            <option value="NS" ${data.recordType === 'NS' ? 'selected' : ''}>NS (Name Server)</option>
                                            <option value="PTR" ${data.recordType === 'PTR' ? 'selected' : ''}>PTR (Reverse DNS)</option>
                                            <option value="SRV" ${data.recordType === 'SRV' ? 'selected' : ''}>SRV (Service)</option>
                                            <option value="TXT" ${data.recordType === 'TXT' ? 'selected' : ''}>TXT (Text)</option>
                                            <option value="CAA" ${data.recordType === 'CAA' ? 'selected' : ''}>CAA (Certificate Authority)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="edit-subdomain">Subdomain</label>
                                        <input type="text" id="edit-subdomain" name="subdomain" class="form-control" placeholder="www, mail, ftp..." value="${data.subdomain || ''}">
                                        <small class="form-text text-muted">Leer lassen für Root-Domain</small>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="edit-target">Ziel *</label>
                                        <input type="text" id="edit-target" name="target" class="form-control" required placeholder="IP-Adresse oder Hostname" value="${data.target || ''}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="edit-ttl">TTL (Sekunden)</label>
                                        <input type="number" id="edit-ttl" name="ttl" class="form-control" value="${data.ttl || '3600'}" min="60" max="86400">
                                        <small class="form-text text-muted">Standard: 3600</small>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="edit-priority">Priorität</label>
                                        <input type="number" id="edit-priority" name="priority" class="form-control" min="0" max="65535" value="${data.priority || ''}" placeholder="10">
                                        <small class="form-text text-muted">Nur für MX und SRV</small>
                                    </div>
                                </div>
                                <div class="col-md-9">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-check-circle"></i> Änderungen speichern
                                            </button>
                                            <button type="button" class="btn btn-secondary dns-action-btn" data-action="hide_forms">
                                                <i class="bi bi-x-circle"></i> Abbrechen
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        // Füge das Formular in den Container oben ein
        const formsContainer = document.getElementById('dns-forms-container');
        if (formsContainer) {
            formsContainer.innerHTML = formHtml;
            formsContainer.style.display = 'block';
            
            // Scroll zum Formular
            formsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    },
    
    /**
     * Zeigt das Formular zum Hinzufügen eines DNSSEC-Schlüssels an
     */
    showAddDnssecKeyForm: function() {
        // Verstecke alle anderen Formulare zuerst
        this.hideForms();
        
        const formHtml = `
            <div class="add-dnssec-key-section">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3><i class="bi bi-key"></i> DNSSEC-Schlüssel hinzufügen</h3>
                        <button type="button" class="btn btn-sm btn-outline-secondary dns-action-btn" data-action="hide_forms">
                            <i class="bi bi-x"></i> Schließen
                        </button>
                    </div>
                    <div class="card-body">
                        <form class="dns-form">
                            <input type="hidden" name="action" value="add_dnssec_key">
                            <input type="hidden" name="domain" value="${this.currentDomain}">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="key-type">Schlüssel-Typ *</label>
                                        <select id="key-type" name="keyType" class="form-control" required>
                                            <option value="">Bitte wählen...</option>
                                            <option value="KSK">KSK (Key Signing Key)</option>
                                            <option value="ZSK">ZSK (Zone Signing Key)</option>
                                        </select>
                                        <small class="form-text text-muted">KSK für Zonen-Signierung, ZSK für Records</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="key-algorithm">Algorithmus *</label>
                                        <select id="key-algorithm" name="algorithm" class="form-control" required>
                                            <option value="">Bitte wählen...</option>
                                            <option value="8">RSA/SHA-256 (empfohlen)</option>
                                            <option value="13">ECDSA/SHA-256</option>
                                            <option value="15">Ed25519 (modern)</option>
                                            <option value="5">RSA/SHA-1 (veraltet)</option>
                                            <option value="7">RSASHA1-NSEC3-SHA1</option>
                                            <option value="10">RSA/SHA-512</option>
                                            <option value="14">ECDSA/SHA-384</option>
                                            <option value="16">Ed448</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="key-size">Schlüssel-Größe *</label>
                                        <input type="number" id="key-size" name="keySize" class="form-control" required min="512" max="4096" placeholder="2048">
                                        <small class="form-text text-muted">Empfohlen: 2048 für RSA, 256 für ECDSA</small>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-success">
                                                <i class="bi bi-key"></i> Schlüssel hinzufügen
                                            </button>
                                            <button type="button" class="btn btn-secondary dns-action-btn" data-action="hide_forms">
                                                <i class="bi bi-x-circle"></i> Abbrechen
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        // Füge das Formular in den Container oben ein
        const formsContainer = document.getElementById('dns-forms-container');
        if (formsContainer) {
            formsContainer.innerHTML = formHtml;
            formsContainer.style.display = 'block';
            
            // Scroll zum Formular
            formsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    },
    
    /**
     * Versteckt alle Formulare
     */
    hideForms: function() {
        const formsContainer = document.getElementById('dns-forms-container');
        if (formsContainer) {
            formsContainer.innerHTML = '';
            formsContainer.style.display = 'none';
        }
    },
    
    /**
     * Startet die Inline-Bearbeitung für einen Record
     */
    startInlineEdit: function(recordId) {
        const row = document.querySelector(`tr[data-record-id="${recordId}"]`);
        if (!row) return;
        
        // Verstecke alle anderen Bearbeitungsmodi
        this.cancelAllInlineEdits();
        
        // Zeige Eingabefelder
        const editableCells = row.querySelectorAll('.editable');
        editableCells.forEach(cell => {
            const displayValue = cell.querySelector('.display-value');
            const editInput = cell.querySelector('.edit-input');
            
            if (displayValue && editInput) {
                displayValue.style.display = 'none';
                editInput.style.display = 'block';
            }
        });
        
        // Zeige Speichern/Abbrechen Buttons
        const editBtn = row.querySelector('.edit-btn');
        const saveBtn = row.querySelector('.save-btn');
        const cancelBtn = row.querySelector('.cancel-btn');
        
        if (editBtn) editBtn.style.display = 'none';
        if (saveBtn) saveBtn.style.display = 'inline-block';
        if (cancelBtn) cancelBtn.style.display = 'inline-block';
        
        // Markiere die Zeile als bearbeitbar
        row.classList.add('editing');
    },
    
    /**
     * Speichert die Inline-Bearbeitung
     */
    saveInlineEdit: function(recordId) {
        const row = document.querySelector(`tr[data-record-id="${recordId}"]`);
        if (!row) return;
        
        // Sammle die Daten
        const data = {
            action: 'edit_record',
            domain: this.currentDomain,
            recordId: recordId
        };
        
        const editableCells = row.querySelectorAll('.editable');
        editableCells.forEach(cell => {
            const field = cell.dataset.field;
            const editInput = cell.querySelector('.edit-input');
            
            if (editInput) {
                let value = editInput.value;
                
                // Spezielle Behandlung für leere Werte
                if (field === 'subDomain' && value === '') {
                    value = null; // Leere Subdomain
                } else if (field === 'priority' && value === '') {
                    value = null; // Leere Priorität
                }
                
                // Feldnamen-Mapping für die API
                if (field === 'fieldType') {
                    data['recordType'] = value; // API erwartet recordType
                } else if (field === 'subDomain') {
                    data['subdomain'] = value; // API erwartet subdomain
                } else {
                    data[field] = value;
                }
            }
        });
        
        // Sende die Daten
        this.showLoading();
        ModuleManager.makeRequest('dns', 'edit_record', data)
            .then(response => {
                this.showResult(response.success, response.message);
                if (response.success) {
                    // Aktualisiere die Anzeigewerte direkt ohne Neuladen
                    this.updateDisplayValues(row, data);
                    this.cancelInlineEdit(recordId);
                    // Kein reloadContent() mehr - die Daten bleiben sichtbar
                }
                this.hideLoading();
            })
            .catch(error => {
                this.showResult(false, 'Fehler beim Speichern des Records');
                this.hideLoading();
            });
    },
    
    /**
     * Bricht die Inline-Bearbeitung ab
     */
    cancelInlineEdit: function(recordId) {
        const row = document.querySelector(`tr[data-record-id="${recordId}"]`);
        if (!row) return;
        
        // Verstecke Eingabefelder
        const editableCells = row.querySelectorAll('.editable');
        editableCells.forEach(cell => {
            const displayValue = cell.querySelector('.display-value');
            const editInput = cell.querySelector('.edit-input');
            
            if (displayValue && editInput) {
                displayValue.style.display = 'block';
                editInput.style.display = 'none';
            }
        });
        
        // Zeige Bearbeiten Button
        const editBtn = row.querySelector('.edit-btn');
        const saveBtn = row.querySelector('.save-btn');
        const cancelBtn = row.querySelector('.cancel-btn');
        
        if (editBtn) editBtn.style.display = 'inline-block';
        if (saveBtn) saveBtn.style.display = 'none';
        if (cancelBtn) cancelBtn.style.display = 'none';
        
        // Entferne Bearbeitungsmodus
        row.classList.remove('editing');
    },
    
    /**
     * Bricht alle Inline-Bearbeitungen ab
     */
    cancelAllInlineEdits: function() {
        const editingRows = document.querySelectorAll('tr.editing');
        editingRows.forEach(row => {
            const recordId = row.dataset.recordId;
            if (recordId) {
                this.cancelInlineEdit(recordId);
            }
        });
    },
    
    /**
     * Aktualisiert die Anzeigewerte nach dem Speichern
     */
    updateDisplayValues: function(row, data) {
        const editableCells = row.querySelectorAll('.editable');
        editableCells.forEach(cell => {
            const field = cell.dataset.field;
            const displayValue = cell.querySelector('.display-value');
            const editInput = cell.querySelector('.edit-input');
            
            if (displayValue && editInput) {
                let value = '';
                
                // Feldnamen-Mapping für die Anzeige
                if (field === 'fieldType') {
                    value = data['recordType'] || '';
                } else if (field === 'subDomain') {
                    value = data['subdomain'] || '';
                } else {
                    value = data[field] || '';
                }
                
                // Spezielle Behandlung für Anzeige
                let displayText = value;
                if (field === 'subDomain' && (!displayText || displayText === '')) {
                    displayText = '-';
                } else if (field === 'priority' && (!displayText || displayText === '')) {
                    displayText = '-';
                }
                
                displayValue.textContent = displayText;
                editInput.value = value;
            }
        });
    }
};

// Modul sofort initialisieren, wenn das Script geladen wird
if (window.dnsModule) {
    window.dnsModule.init();
} 