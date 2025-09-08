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
    },
    
    t: function(key, params = {}) {
        // Übersetzungen werden jetzt über die globale t() Funktion geladen
        // Diese Funktion ist nur noch für JavaScript-spezifische Übersetzungen
        let text = key; // Fallback auf Schlüssel
        
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
                showNotification('Test erfolgreich', 'success');
            } else {
                showNotification('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Operation fehlgeschlagen', 'error');
        });
}

// Automatische Initialisierung beim Laden
document.addEventListener('DOMContentLoaded', function() {
    if (window.customModule) {
        window.customModule.init();
    }
});
</script>