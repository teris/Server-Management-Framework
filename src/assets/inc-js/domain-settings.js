// Domain-Endungen laden
function loadExtensions() {
    $.ajax({
        url: 'index.php',
        type: 'POST',
        data: {
            action: 'get_domain_extensions',
            core: 'admin'
        },
        success: function(response) {
            if (response.success) {
                displayExtensions(response.data.extensions);
            } else {
                showAlert('Fehler beim Laden der Domain-Endungen: ' + response.error, 'danger');
            }
        },
        error: function() {
            showAlert('Fehler beim Laden der Domain-Endungen', 'danger');
        }
    });
}

// Domain-Endungen anzeigen
function displayExtensions(extensions) {
    if (extensions.length === 0) {
        $('#extensionsTable').html('<div class="alert alert-info">Keine Domain-Endungen gefunden.</div>');
        return;
    }
    
    let html = `
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>TLD</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Erstellt</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    extensions.forEach(function(extension) {
        const statusClass = extension.active == 1 ? 'success' : 'secondary';
        const statusText = extension.active == 1 ? 'Aktiv' : 'Inaktiv';
        const createdDate = new Date(extension.created_at).toLocaleDateString('de-DE');
        
        html += `
            <tr>
                <td><strong>.${extension.tld}</strong></td>
                <td>${extension.name}</td>
                <td><span class="badge bg-${statusClass}">${statusText}</span></td>
                <td>${createdDate}</td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-primary" 
                                onclick="editExtension(${extension.id}, '${extension.tld}', '${extension.name}', ${extension.active})">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-outline-${extension.active == 1 ? 'warning' : 'success'}" 
                                onclick="toggleStatus(${extension.id})">
                            <i class="bi bi-${extension.active == 1 ? 'pause' : 'play'}"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger" 
                                onclick="deleteExtension(${extension.id}, '${extension.tld}')">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    $('#extensionsTable').html(html);
}

// Neue Domain-Endung hinzufügen
$('#addExtensionForm').on('submit', function(e) {
    e.preventDefault();
    
    const formData = {
        tld: $('#tld').val(),
        name: $('#name').val(),
        active: $('#active').is(':checked') ? 1 : 0,
        action: 'add_extension',
        core: 'admin'
    };
    
    $.ajax({
        url: 'index.php',
        type: 'POST',
        data: formData,
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                $('#addExtensionModal').modal('hide');
                $('#addExtensionForm')[0].reset();
                loadExtensions();
            } else {
                showAlert('Fehler: ' + response.error, 'danger');
            }
        },
        error: function() {
            showAlert('Fehler beim Hinzufügen der Domain-Endung', 'danger');
        }
    });
});

// Domain-Endung bearbeiten
$('#editExtensionForm').on('submit', function(e) {
    e.preventDefault();
    
    const formData = {
        id: $('#edit_id').val(),
        tld: $('#edit_tld').val(),
        name: $('#edit_name').val(),
        active: $('#edit_active').is(':checked') ? 1 : 0,
        action: 'update_extension',
        core: 'admin'
    };
    
    $.ajax({
        url: 'index.php',
        type: 'POST',
        data: formData,
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                $('#editExtensionModal').modal('hide');
                loadExtensions();
            } else {
                showAlert('Fehler: ' + response.error, 'danger');
            }
        },
        error: function() {
            showAlert('Fehler beim Aktualisieren der Domain-Endung', 'danger');
        }
    });
});

// Domain-Endung bearbeiten Modal öffnen
function editExtension(id, tld, name, active) {
    $('#edit_id').val(id);
    $('#edit_tld').val(tld);
    $('#edit_name').val(name);
    $('#edit_active').prop('checked', active == 1);
    
    new bootstrap.Modal(document.getElementById('editExtensionModal')).show();
}

// Status umschalten
function toggleStatus(id) {
    if (!confirm('Möchten Sie den Status dieser Domain-Endung wirklich ändern?')) {
        return;
    }
    
    $.ajax({
        url: 'index.php',
        type: 'POST',
        data: {
            action: 'toggle_extension_status',
            id: id,
            core: 'admin'
        },
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                loadExtensions();
            } else {
                showAlert('Fehler: ' + response.error, 'danger');
            }
        },
        error: function() {
            showAlert('Fehler beim Ändern des Status', 'danger');
        }
    });
}

// Domain-Endung löschen
function deleteExtension(id, tld) {
    if (!confirm(`Möchten Sie die Domain-Endung .${tld} wirklich löschen?`)) {
        return;
    }
    
    $.ajax({
        url: 'index.php',
        type: 'POST',
        data: {
            action: 'delete_extension',
            id: id,
            core: 'admin'
        },
        success: function(response) {
            if (response.success) {
                showAlert(response.message, 'success');
                loadExtensions();
            } else {
                showAlert('Fehler: ' + response.error, 'danger');
            }
        },
        error: function() {
            showAlert('Fehler beim Löschen der Domain-Endung', 'danger');
        }
    });
}

// Alert anzeigen
function showAlert(message, type) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Bestehende Alerts entfernen
    $('.alert').remove();
    
    // Neuen Alert einfügen
    $('.card-body').prepend(alertHtml);
    
    // Alert nach 5 Sekunden automatisch ausblenden
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 5000);
}

// Beim Laden der Seite Domain-Endungen laden
$(document).ready(function() {
    loadExtensions();
});
