$(document).ready(function() {
    // System-spezifische Felder ein-/ausblenden
    $('input[name="systems[]"]').change(function() {
        var system = $(this).val();
        var isChecked = $(this).is(':checked');
        
        if (isChecked) {
            $('#' + system + '-fields').show();
        } else {
            $('#' + system + '-fields').hide();
        }
    });
    
    // Passwort-Bestätigung prüfen
    $('#createUserForm').submit(function(e) {
        var password = $('#password').val();
        
        // Mindestens ein System auswählen
        var systems = $('input[name="systems[]"]:checked').length;
        if (systems === 0) {
            e.preventDefault();
            alert('Bitte wählen Sie mindestens ein System aus');
            return false;
        }
        
        // Debug-Logging
        console.log('Formular wird abgesendet...');
        console.log('Action:', $('input[name="action"]').val());
        console.log('Username:', $('#username').val());
        console.log('Email:', $('#email').val());
        console.log('Systems:', $('input[name="systems[]"]:checked').map(function() { return this.value; }).get());
        
        // Formular normal absenden
        return true;
    });
});

// Benutzer bearbeiten
function editUser(userId, system = null, email = null) {
    // Parameter-Analyse: Wenn userId eine Zahl ist, handelt es sich um einen lokalen Benutzer
    if (!isNaN(userId) && userId !== '') {
        // Lokalen Benutzer bearbeiten
        $('#editUserId').val(userId);
        $('#editUsername').val(''); // Wird durch PHP gefüllt
        $('#editEmail').val(''); // Wird durch PHP gefüllt
        $('#editFullName').val(''); // Wird durch PHP gefüllt
        $('#editRole').val('user');
        $('#editActive').val('y');
        $('#editPassword').val(''); // Passwort-Feld leeren
        
        // System-Verknüpfungen anzeigen (vereinfacht)
        $('#editSystemLinks').html('<p class="text-muted">System-Verknüpfungen werden beim Laden angezeigt...</p>');
        
        $('#editUserModal').modal('show');
    } else {
        // System-Benutzer bearbeiten
        var systemType = userId; // userId ist hier eigentlich das System
        var systemUserId = system; // system ist hier die User-ID oder E-Mail
        var userEmail = email; // email ist der dritte Parameter
        
        // System-Benutzer direkt bearbeiten
        editSystemUser(systemType, systemUserId, userEmail);
    }
}

// Inline-Bearbeitung starten
function startEditUser(button) {
    var row = $(button).closest('tr');
    var system = row.data('system');
    var userId = row.data('user-id');
    
    // Alle anderen Zeilen in den Anzeige-Modus setzen
    $('tr[data-system="' + system + '"]').each(function() {
        if ($(this).data('user-id') !== userId) {
            cancelUserEdit($(this).find('.edit-mode button').first());
        }
    });
    
    // Aktuelle Zeile in den Bearbeitungs-Modus setzen
    row.find('.view-mode').hide();
    row.find('.edit-mode').show();
    row.find('.user-email, .user-username, .user-expires').hide();
    row.find('.edit-email, .edit-username, .edit-expires').show();
    
    // Zeile hervorheben
    row.addClass('table-warning');
}

// Bearbeitung abbrechen
function cancelUserEdit(button) {
    var row = $(button).closest('tr');
    
    // Zurück zum Anzeige-Modus
    row.find('.view-mode').show();
    row.find('.edit-mode').hide();
    row.find('.user-email, .user-username, .user-expires').show();
    row.find('.edit-email, .edit-username, .edit-expires').hide();
    
    // Hervorhebung entfernen
    row.removeClass('table-warning');
}

// Benutzer-Änderungen speichern
function saveUserEdit(button) {
    var row = $(button).closest('tr');
    var system = row.data('system');
    var userId = row.data('user-id');
    
    // Neue Werte sammeln
    var newEmail = row.find('.edit-email').val();
    var newUsername = row.find('.edit-username').val();
    var newExpires = row.find('.edit-expires').val();
    
    // AJAX-Request senden
    $.ajax({
        url: '?option=users&ajax=1',
        type: 'POST',
        data: {
            action: 'edit_system_user',
            system_type: system,
            system_user_id: userId,
            email: newEmail,
            username: newUsername,
            expires: newExpires
        },
        success: function(response) {
            if (response.success) {
                // Werte in der Tabelle aktualisieren
                row.find('.user-email').text(newEmail);
                row.find('.user-username').text(newUsername);
                
                // Ablaufdatum aktualisieren
                if (newExpires) {
                    var expiresDate = new Date(newExpires);
                    var formattedDate = expiresDate.toLocaleDateString('de-DE') + ' ' + expiresDate.toLocaleTimeString('de-DE', {hour: '2-digit', minute: '2-digit'});
                    row.find('.user-expires').html('<span class="badge bg-warning">' + formattedDate + '</span>');
                } else {
                    row.find('.user-expires').html('<span class="badge bg-success">Unbegrenzt</span>');
                }
                
                // Zurück zum Anzeige-Modus
                cancelUserEdit(button);
                
                // Erfolgsmeldung anzeigen
                showAlert('success', 'Benutzer erfolgreich aktualisiert!');
            } else {
                showAlert('error', 'Fehler beim Aktualisieren: ' + response.message);
            }
        },
        error: function() {
            showAlert('error', 'Fehler beim Speichern der Änderungen');
        }
    });
}

// Alert-Meldungen anzeigen
function showAlert(type, message) {
    var alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    var alertHtml = '<div class="alert ' + alertClass + ' alert-dismissible fade show" role="alert">' +
                    message +
                    '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                    '</div>';
    
    // Alert am Anfang der card-body einfügen
    $('.card-body').first().prepend(alertHtml);
    
    // Alert nach 5 Sekunden automatisch ausblenden
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 5000);
}

// System-Benutzer löschen (für inline-Löschung)
function deleteSystemUser(systemType, systemUserId) {
    if (confirm('Sind Sie sicher, dass Sie den Benutzer "' + systemUserId + '" aus dem System "' + systemType.toUpperCase() + '" löschen möchten?')) {
        $.ajax({
            url: '?option=users&ajax=1',
            type: 'POST',
            data: {
                action: 'delete_system_user',
                system_type: systemType,
                system_user_id: systemUserId
            },
            success: function(response) {
                if (response.success) {
                    // Zeile aus der Tabelle entfernen
                    $('tr[data-system="' + systemType + '"][data-user-id="' + systemUserId + '"]').fadeOut();
                    showAlert('success', 'System-Benutzer erfolgreich gelöscht!');
                } else {
                    showAlert('error', 'Fehler beim Löschen: ' + response.message);
                }
            },
            error: function() {
                showAlert('error', 'Fehler beim Löschen des Benutzers');
            }
        });
    }
}

// Benutzer löschen
function deleteUser(userId, username = null, system = null) {
    // Wenn userId eine Zahl ist, handelt es sich um einen lokalen Benutzer
    if (!isNaN(userId)) {
        // Lokalen Benutzer löschen
        $('#deleteUserId').val(userId);
        $('#deleteUsername').text(username || 'Unbekannt');
        
        // Alle Checkboxen aktivieren (vereinfacht)
        $('#deleteFromOGP, #deleteFromProxmox, #deleteFromISPConfig').prop('disabled', false);
        
        $('#deleteUserModal').modal('show');
    } else {
        // System-Benutzer löschen (noch nicht implementiert)
        if (confirm('Sind Sie sicher, dass Sie den Benutzer "' + (username || userId) + '" aus dem System "' + (system || 'unbekannt') + '" löschen möchten?')) {
            alert('Löschung von System-Benutzern ist noch nicht implementiert. System: ' + (system || userId) + ', Benutzer: ' + (username || 'unbekannt'));
        }
    }
}

// Benutzerdetails anzeigen
function viewUserDetails(userId) {
    // Einfache Implementierung: Modal mit Platzhalter öffnen
    var detailsHtml = '<div class="text-center">';
    detailsHtml += '<p><i class="bi bi-info-circle"></i> Benutzerdetails werden geladen...</p>';
    detailsHtml += '<p class="text-muted">Benutzer-ID: ' + userId + '</p>';
    detailsHtml += '</div>';
    
    $('#userDetailsModal .modal-body').html(detailsHtml);
    $('#userDetailsModal').modal('show');
}

// Benutzerliste laden
function loadUsers() {
    // Hier können Sie die AJAX-Logik implementieren
    console.log('Benutzerliste wird geladen...');
}

// Benutzerliste für andere Systeme aktualisieren
function refreshUserList(system) {
    // Hier können Sie die AJAX-Logik implementieren
    console.log('Benutzerliste für ' + system + ' wird aktualisiert...');
}

// Debounce-Funktion für Suche
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Modal für Benutzererstellung anzeigen
function showCreateUserModal() {
    // Zum ersten Tab wechseln
    $('#create-tab').tab('show');
}

// Kundendetails anzeigen
function showCustomerDetails(customerId) {
    // Hier könnte AJAX verwendet werden, um detaillierte Kundendaten zu laden
    // Für den Moment zeigen wir nur eine einfache Nachricht
    
    // Modal-Inhalt aktualisieren
    document.getElementById('customerDetailsModalLabel').textContent = 'Kundendetails';
    document.getElementById('customerDetailsContent').innerHTML = `
        <div class="text-center">
            <p>Kundendetails für ID: ${customerId}</p>
            <p>Diese Funktion kann erweitert werden, um detaillierte Informationen anzuzeigen.</p>
            <p>Sie können hier weitere Informationen wie:</p>
            <ul class="text-start">
                <li>Registrierungsdatum</li>
                <li>Letzte Aktivität</li>
                <li>Systemkonten-Status</li>
                <li>Verknüpfte Dienste</li>
            </ul>
        </div>
    `;
    
    // Bootstrap Modal öffnen
    var modalElement = document.getElementById('customerDetailsModal');
    if (modalElement) {
        var modal = new bootstrap.Modal(modalElement);
        modal.show();
    } else {
        console.error('Modal-Element nicht gefunden');
    }
}
