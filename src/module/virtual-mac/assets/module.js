/**
 * Virtual MAC Module JavaScript
 * Verwaltet alle Formulare und UI-Interaktionen für das Virtual-MAC-Modul
 */

window.virtualMacModule = {
    /**
     * Zeigt einen Tab an
     */
    showTab: function(tabName, button) {
        // Alle Tabs ausblenden
        document.querySelectorAll('.virtual-mac-tab-content').forEach(tab => {
            tab.classList.add('hidden');
        });
        
        // Alle Tab-Buttons deaktivieren
        document.querySelectorAll('.tab').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Gewählten Tab anzeigen
        const targetTab = document.getElementById('virtual-mac-' + tabName);
        if (targetTab) {
            targetTab.classList.remove('hidden');
        }
        
        // Button aktivieren
        if (button) {
            button.classList.add('active');
        }
    },
    
    /**
     * Filtert eine Tabelle
     */
    filterTable: function(tableId, searchTerm) {
        const table = document.getElementById(tableId);
        if (!table) return;
        
        const tbody = table.querySelector('tbody');
        const rows = tbody.querySelectorAll('tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(searchTerm.toLowerCase())) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
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
     * Initialisiert das Modul
     */
    init: function() {
        console.log('Virtual MAC Module initialized');
        
        // Event-Listener für Formulare hinzufügen
        this.setupEventListeners();
    },
    
    /**
     * Richtet Event-Listener ein
     */
    setupEventListeners: function() {
        // Event-Listener für Tab-Buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('virtual-mac-tab-btn')) {
                e.preventDefault();
                const tabName = e.target.dataset.tab;
                this.showTab(tabName, e.target);
            }
        });
        
        // Event-Listener für Suchfeld
        const searchField = document.getElementById('virtual-mac-overview-search');
        if (searchField) {
            searchField.addEventListener('input', (e) => {
                this.filterTable('virtual-mac-overview-table', e.target.value);
            });
        }
    },
    
    /**
     * Löscht eine Virtual MAC (Bestätigung erforderlich)
     */
    deleteVirtualMac: function(serviceName, macAddress) {
        if (!confirm(`Virtual MAC ${macAddress} wirklich löschen?`)) {
            return;
        }
        
        // Formular erstellen und absenden
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'handler.php';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_virtual_mac';
        
        const serviceInput = document.createElement('input');
        serviceInput.type = 'hidden';
        serviceInput.name = 'service_name';
        serviceInput.value = serviceName;
        
        const macInput = document.createElement('input');
        macInput.type = 'hidden';
        macInput.name = 'mac_address';
        macInput.value = macAddress;
        
        const pluginInput = document.createElement('input');
        pluginInput.type = 'hidden';
        pluginInput.name = 'plugin';
        pluginInput.value = 'virtual-mac';
        
        form.appendChild(actionInput);
        form.appendChild(serviceInput);
        form.appendChild(macInput);
        form.appendChild(pluginInput);
        
        document.body.appendChild(form);
        form.submit();
    }
};

// Modul sofort initialisieren, wenn das Script geladen wird
if (window.virtualMacModule) {
    window.virtualMacModule.init();
} 