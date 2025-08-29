$(function() {
    // Nur noch für Modus-Umschaltung (Datenbank/Live) benötigt
    var modeSelect = document.getElementById('mode');
    if (modeSelect) {
        modeSelect.addEventListener('change', function() {
            document.getElementById('db-mode-hint').style.display = this.value === 'database' ? 'block' : 'none';
        });
    }
    
    // Benutzer hinzufügen per AJAX
    $('#user-add-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var formData = $form.serialize() + '&core=admin&action=save_user';
        $.post('index.php', formData, function(response) {
            if (response.success) {
                $('#user-add-notice').html('<div class="alert alert-success">Benutzer gespeichert</div>');
                $form[0].reset();
                $('#users-list').load('inc/settings.php #users-list > *');
            } else {
                $('#user-add-notice').html('<div class="alert alert-danger">' + (response.error || 'Fehler beim Speichern') + '</div>');
            }
        }, 'json');
    });
    
    // Gruppen hinzufügen per AJAX
    $('#group-add-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var formData = $form.serialize() + '&core=admin&action=save_group';
        $.post('index.php', formData, function(response) {
            if (response.success) {
                $('#group-add-notice').html('<div class="alert alert-success">Gruppe gespeichert</div>');
                $form[0].reset();
                $('#groups-list').load('inc/settings.php #groups-list > *');
            } else {
                $('#group-add-notice').html('<div class="alert alert-danger">' + (response.error || 'Fehler beim Speichern') + '</div>');
            }
        }, 'json');
    });
    
    // Einstellungen speichern per AJAX
    $('#general-settings-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var formData = $form.serialize() + '&core=admin&action=save_settings';
        $.post('index.php', formData, function(response) {
            if (response.success) {
                $('#general-settings-status').html('<div class="alert alert-success">Einstellungen gespeichert</div>');
            } else {
                $('#general-settings-status').html('<div class="alert alert-danger">' + (response.error || 'Fehler beim Speichern') + '</div>');
            }
        }, 'json');
    });
    
    // API-Zugangsdaten speichern per AJAX
    $('#api-settings-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var formData = $form.serialize() + '&core=admin&action=save_api_credentials';
        $.post('index.php', formData, function(response) {
            if (response.success) {
                $('#api-settings-status').html('<div class="alert alert-success">API-Zugangsdaten gespeichert</div>');
            } else {
                $('#api-settings-status').html('<div class="alert alert-danger">' + (response.error || 'Fehler beim Speichern') + '</div>');
            }
        }, 'json');
    });
    
    // Module speichern per AJAX
    $('#modules-settings-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var formData = $form.serialize() + '&core=admin&action=save_modules';
        $.post('index.php', formData, function(response) {
            if (response.success) {
                $('#modules-settings-status').html('<div class="alert alert-success">Modul-Berechtigungen gespeichert</div>');
            } else {
                $('#modules-settings-status').html('<div class="alert alert-danger">' + (response.error || 'Fehler beim Speichern') + '</div>');
            }
        }, 'json');
    });
    
    // Gruppe bearbeiten per AJAX
    $(document).on('submit', '.group-edit-form', function(e) {
        e.preventDefault();
        var $form = $(this);
        var formData = $form.serialize() + '&core=admin&action=save_group';
        $.post('index.php', formData, function(response) {
            if (response.success) {
                $('#group-add-notice').html('<div class="alert alert-success">Gruppe gespeichert</div>');
                $('#groups-list').load('inc/settings.php #groups-list > *');
            } else {
                $('#group-add-notice').html('<div class="alert alert-danger">' + (response.error || 'Fehler beim Speichern') + '</div>');
            }
        }, 'json');
    });
    
    // Gruppe löschen per AJAX
    $(document).on('submit', '.group-delete-form', function(e) {
        e.preventDefault();
        if (!confirm('Löschen bestätigen?')) return;
        var $form = $(this);
        var formData = $form.serialize() + '&core=admin&action=delete_group';
        $.post('inc/admin.php', formData, function(response) {
            if (response.success) {
                $('#group-add-notice').html('<div class="alert alert-success">Gruppe gelöscht</div>');
                $('#groups-list').load('inc/settings.php #groups-list > *');
            } else {
                $('#group-add-notice').html('<div class="alert alert-danger">' + (response.error || 'Fehler beim Löschen') + '</div>');
            }
        }, 'json');
    });
    
    // Benutzer bearbeiten per AJAX
    $(document).on('submit', '.user-edit-form', function(e) {
        e.preventDefault();
        var $form = $(this);
        var formData = $form.serialize() + '&core=admin&action=save_user';
        $.post('index.php', formData, function(response) {
            if (response.success) {
                $('#user-add-notice').html('<div class="alert alert-success">Benutzer gespeichert</div>');
                $('#users-list').load('inc/settings.php #users-list > *');
            } else {
                $('#user-add-notice').html('<div class="alert alert-danger">' + (response.error || 'Fehler beim Speichern') + '</div>');
            }
        }, 'json');
    });
    
    // Benutzer löschen per AJAX
    $(document).on('submit', '.user-delete-form', function(e) {
        e.preventDefault();
        if (!confirm('Löschen bestätigen?')) return;
        var $form = $(this);
        var formData = $form.serialize() + '&core=admin&action=delete_user';
        $.post('inc/admin.php', formData, function(response) {
            if (response.success) {
                $('#user-add-notice').html('<div class="alert alert-success">Benutzer gelöscht</div>');
                $('#users-list').load('inc/settings.php #users-list > *');
            } else {
                $('#user-add-notice').html('<div class="alert alert-danger">' + (response.error || 'Fehler beim Löschen') + '</div>');
            }
        }, 'json');
    });
});
