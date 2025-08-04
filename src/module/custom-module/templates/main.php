<div id="custom-module-content">
    <div class="card">
        <div class="card-header">
            <h2 class="mb-0">🎯 <?= t('module_title') ?></h2>
        </div>
        <div class="card-body">
            <p class="lead"><?= t('welcome_message') ?></p>
            <p><?= t('custom_module_description') ?></p>
            
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><?= t('custom_feature') ?></h5>
                        </div>
                        <div class="card-body">
                            <button class="btn btn-primary" onclick="runCustomTest()">
                                <i class="bi bi-play-circle"></i> <?= t('test_button') ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><?= t('actions') ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="btn-group" role="group">
                                <button class="btn btn-success btn-sm">
                                    <i class="bi bi-plus"></i> <?= t('create') ?>
                                </button>
                                <button class="btn btn-primary btn-sm">
                                    <i class="bi bi-pencil"></i> <?= t('edit') ?>
                                </button>
                                <button class="btn btn-danger btn-sm">
                                    <i class="bi bi-trash"></i> <?= t('delete') ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Custom Module JavaScript
window.customModule = {
    init: function() {
        console.log('Custom module initialized');
        this.loadTranslations();
    },
    
    translations: {},
    
    loadTranslations: function() {
        // Lade Übersetzungen vom Server mit neuem Format
        ModuleManager.makeRequest('custom-module', 'get_translations')
            .then(data => {
                if (data.success) {
                    this.translations = data.translations;
                    console.log('Custom module translations loaded:', this.translations);
                } else {
                    console.error('Failed to load translations:', data.error);
                }
            })
            .catch(error => console.error('Error loading translations:', error));
    },
    
    t: function(key, params = {}) {
        let text = this.translations[key] || key;
        
        // Parameter ersetzen
        Object.keys(params).forEach(param => {
            text = text.replace(`{${param}}`, params[param]);
        });
        
        return text;
    }
};

// Global function
function runCustomTest() {
    ModuleManager.makeRequest('custom-module', 'test')
        .then(data => {
            if (data.success) {
                showNotification(customModule.t('test_successful'), 'success');
            } else {
                showNotification('Fehler: ' + (data.error || customModule.t('unknown_error')), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification(customModule.t('operation_failed'), 'error');
        });
}

// Automatische Initialisierung beim Laden
document.addEventListener('DOMContentLoaded', function() {
    if (window.customModule) {
        window.customModule.init();
    }
});

// Fallback: Initialisierung nach kurzer Verzögerung
setTimeout(function() {
    if (window.customModule && !window.customModule.translations || Object.keys(window.customModule.translations).length === 0) {
        console.log('Auto-initializing custom module');
        window.customModule.init();
    }
}, 100);
</script>