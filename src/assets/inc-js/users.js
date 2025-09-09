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
        // Lokalen Benutzer bearbeiten - alle Felder zurücksetzen
        $('#editUserId').val(userId);
        $('#editUsername').val('');
        $('#editEmail').val('');
        $('#editFullName').val('');
        $('#editRole').val('user');
        $('#editActive').val('y');
        $('#editGroupId').val('');
        $('#editPassword').val('');
        $('#editFailedLoginAttempts').val('0');
        $('#editLockedUntil').val('');
        $('#editPasswordChangedAt').val('');
        $('#editLastLogin').val('');
        $('#editCreatedAt').val('');
        $('#editUpdatedAt').val('');
        
        // System-Verknüpfungen anzeigen (vereinfacht)
        $('#editSystemLinks').html('<p class="text-muted">System-Verknüpfungen werden beim Laden angezeigt...</p>');
        
        // Benutzerdaten laden
        loadUserDataForEdit(userId);
        
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

// Benutzerdaten für Bearbeitung laden
function loadUserDataForEdit(userId) {
    $.ajax({
        url: '?option=users',
        type: 'POST',
        data: {
            action: 'get_user_data',
            user_id: userId
        },
        success: function(response) {
            if (response.success && response.data) {
                var user = response.data;
                
                // Grundlegende Informationen
                $('#editUsername').val(user.username || '');
                $('#editEmail').val(user.email || '');
                $('#editFullName').val(user.full_name || '');
                $('#editRole').val(user.role || 'user');
                $('#editActive').val(user.active || 'y');
                $('#editGroupId').val(user.group_id || '');
                
                // Sicherheitsinformationen
                $('#editFailedLoginAttempts').val(user.failed_login_attempts || 0);
                if (user.locked_until) {
                    var lockedDate = new Date(user.locked_until);
                    $('#editLockedUntil').val(lockedDate.toISOString().slice(0, 16));
                }
                if (user.password_changed_at) {
                    var passwordChangedDate = new Date(user.password_changed_at);
                    $('#editPasswordChangedAt').val(passwordChangedDate.toISOString().slice(0, 16));
                }
                
                // Zeitstempel
                if (user.last_login) {
                    var lastLoginDate = new Date(user.last_login);
                    $('#editLastLogin').val(lastLoginDate.toISOString().slice(0, 16));
                }
                if (user.created_at) {
                    var createdDate = new Date(user.created_at);
                    $('#editCreatedAt').val(createdDate.toISOString().slice(0, 16));
                }
                if (user.updated_at) {
                    var updatedDate = new Date(user.updated_at);
                    $('#editUpdatedAt').val(updatedDate.toISOString().slice(0, 16));
                }
                
                // System-Verknüpfungen laden
                loadUserSystemLinks(userId);
            } else {
                showAlert('error', 'Fehler beim Laden der Benutzerdaten: ' + (response.message || 'Unbekannter Fehler'));
            }
        },
        error: function() {
            showAlert('error', 'Fehler beim Laden der Benutzerdaten');
        }
    });
}

// System-Verknüpfungen für Benutzer laden
function loadUserSystemLinks(userId) {
    $.ajax({
        url: '?option=users',
        type: 'POST',
        data: {
            action: 'get_user_system_links',
            user_id: userId
        },
        success: function(response) {
            if (response.success) {
                var linksHtml = '<div class="row">';
                
                if (response.data.ogp) {
                    linksHtml += '<div class="col-md-6 mb-2"><span class="badge bg-info">OpenGamePanel:</span> ' + response.data.ogp + '</div>';
                }
                if (response.data.proxmox) {
                    linksHtml += '<div class="col-md-6 mb-2"><span class="badge bg-warning">Proxmox:</span> ' + response.data.proxmox + '</div>';
                }
                if (response.data.ispconfig) {
                    linksHtml += '<div class="col-md-6 mb-2"><span class="badge bg-success">ISPConfig:</span> ' + response.data.ispconfig + '</div>';
                }
                
                if (linksHtml === '<div class="row">') {
                    linksHtml += '<div class="col-12"><p class="text-muted">Keine System-Verknüpfungen vorhanden</p></div>';
                }
                
                linksHtml += '</div>';
                $('#editSystemLinks').html(linksHtml);
            }
        },
        error: function() {
            $('#editSystemLinks').html('<p class="text-muted">Fehler beim Laden der System-Verknüpfungen</p>');
        }
    });
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
        url: '?option=users',
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
            url: '?option=users',
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
    console.log('Loading user details for ID:', userId);
    
    // Modal-Inhalt mit Ladeanzeige
    var detailsHtml = '<div class="text-center">';
    detailsHtml += '<p><i class="bi bi-info-circle"></i> Benutzerdetails werden geladen...</p>';
    detailsHtml += '<p class="text-muted">Benutzer-ID: ' + userId + '</p>';
    detailsHtml += '</div>';
    
    $('#userDetailsContent').html(detailsHtml);
    $('#editUserFromDetailsBtn').hide();
    $('#userDetailsModal').modal('show');
    
    // Benutzerdaten laden
    loadUserDetails(userId);
}

// Benutzerdetails laden
function loadUserDetails(userId) {
    console.log('AJAX Request: get_user_details for ID:', userId);
    
    $.ajax({
        url: '?option=users',
        type: 'POST',
        data: {
            action: 'get_user_details',
            user_id: userId
        },
        success: function(response) {
            console.log('AJAX Response:', response);
            
            if (response.success && response.data) {
                var user = response.data;
                var detailsHtml = generateUserDetailsHtml(user);
                $('#userDetailsContent').html(detailsHtml);
                $('#editUserFromDetailsBtn').show().off('click').on('click', function() {
                    $('#userDetailsModal').modal('hide');
                    editUser(userId);
                });
            } else {
                var errorHtml = '<div class="alert alert-danger">';
                errorHtml += '<i class="bi bi-exclamation-triangle"></i> ';
                errorHtml += 'Fehler beim Laden der Benutzerdetails: ' + (response.message || 'Unbekannter Fehler');
                errorHtml += '</div>';
                $('#userDetailsContent').html(errorHtml);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', xhr, status, error);
            var errorHtml = '<div class="alert alert-danger">';
            errorHtml += '<i class="bi bi-exclamation-triangle"></i> ';
            errorHtml += 'Fehler beim Laden der Benutzerdetails: ' + error;
            errorHtml += '</div>';
            $('#userDetailsContent').html(errorHtml);
        }
    });
}

// HTML für Benutzerdetails generieren
function generateUserDetailsHtml(user) {
    var html = '<div class="row">';
    
    // Grundlegende Informationen
    html += '<div class="col-12 mb-4">';
    html += '<h6 class="text-primary border-bottom pb-2"><i class="bi bi-person"></i> Grundlegende Informationen</h6>';
    html += '<div class="row">';
    html += '<div class="col-md-6"><strong>Benutzername:</strong> ' + (user.username || 'Nicht angegeben') + '</div>';
    html += '<div class="col-md-6"><strong>E-Mail:</strong> ' + (user.email || 'Nicht angegeben') + '</div>';
    html += '<div class="col-md-6"><strong>Vollständiger Name:</strong> ' + (user.full_name || 'Nicht angegeben') + '</div>';
    html += '<div class="col-md-6"><strong>Rolle:</strong> <span class="badge bg-' + (user.role === 'admin' ? 'danger' : 'primary') + '">' + (user.role || 'user') + '</span></div>';
    html += '<div class="col-md-6"><strong>Status:</strong> <span class="badge bg-' + (user.active === 'y' ? 'success' : 'secondary') + '">' + (user.active === 'y' ? 'Aktiv' : 'Inaktiv') + '</span></div>';
    html += '<div class="col-md-6"><strong>Gruppen-ID:</strong> ' + (user.group_id || 'Nicht zugewiesen') + '</div>';
    html += '</div>';
    html += '</div>';
    
    // Sicherheitsinformationen
    html += '<div class="col-12 mb-4">';
    html += '<h6 class="text-primary border-bottom pb-2"><i class="bi bi-shield-lock"></i> Sicherheitsinformationen</h6>';
    html += '<div class="row">';
    html += '<div class="col-md-6"><strong>Fehlgeschlagene Anmeldeversuche:</strong> ' + (user.failed_login_attempts || 0) + '</div>';
    html += '<div class="col-md-6"><strong>Gesperrt bis:</strong> ' + (user.locked_until ? new Date(user.locked_until).toLocaleString('de-DE') : 'Nicht gesperrt') + '</div>';
    html += '<div class="col-md-6"><strong>Passwort geändert am:</strong> ' + (user.password_changed_at ? new Date(user.password_changed_at).toLocaleString('de-DE') : 'Nicht verfügbar') + '</div>';
    html += '</div>';
    html += '</div>';
    
    // Zeitstempel
    html += '<div class="col-12 mb-4">';
    html += '<h6 class="text-primary border-bottom pb-2"><i class="bi bi-clock"></i> Zeitstempel</h6>';
    html += '<div class="row">';
    html += '<div class="col-md-6"><strong>Letzter Login:</strong> ' + (user.last_login ? new Date(user.last_login).toLocaleString('de-DE') : 'Nie') + '</div>';
    html += '<div class="col-md-6"><strong>Erstellt am:</strong> ' + (user.created_at ? new Date(user.created_at).toLocaleString('de-DE') : 'Nicht verfügbar') + '</div>';
    html += '<div class="col-md-6"><strong>Zuletzt aktualisiert:</strong> ' + (user.updated_at ? new Date(user.updated_at).toLocaleString('de-DE') : 'Nicht verfügbar') + '</div>';
    html += '</div>';
    html += '</div>';
    
    // System-Verknüpfungen
    html += '<div class="col-12 mb-4">';
    html += '<h6 class="text-primary border-bottom pb-2"><i class="bi bi-link-45deg"></i> System-Verknüpfungen</h6>';
    html += '<div id="userDetailsSystemLinks">';
    html += '<p class="text-muted">System-Verknüpfungen werden geladen...</p>';
    html += '</div>';
    html += '</div>';
    
    html += '</div>';
    
    // System-Verknüpfungen laden
    loadUserDetailsSystemLinks(user.id, '#userDetailsSystemLinks');
    
    return html;
}

// System-Verknüpfungen für Benutzerdetails laden
function loadUserDetailsSystemLinks(userId, targetElement) {
    $.ajax({
        url: '?option=users',
        type: 'POST',
        data: {
            action: 'get_user_system_links',
            user_id: userId
        },
        success: function(response) {
            if (response.success) {
                var linksHtml = '<div class="row">';
                
                if (response.data.ogp) {
                    linksHtml += '<div class="col-md-6 mb-2"><span class="badge bg-info">OpenGamePanel:</span> ' + response.data.ogp + '</div>';
                }
                if (response.data.proxmox) {
                    linksHtml += '<div class="col-md-6 mb-2"><span class="badge bg-warning">Proxmox:</span> ' + response.data.proxmox + '</div>';
                }
                if (response.data.ispconfig) {
                    linksHtml += '<div class="col-md-6 mb-2"><span class="badge bg-success">ISPConfig:</span> ' + response.data.ispconfig + '</div>';
                }
                
                if (linksHtml === '<div class="row">') {
                    linksHtml += '<div class="col-12"><p class="text-muted">Keine System-Verknüpfungen vorhanden</p></div>';
                }
                
                linksHtml += '</div>';
                $(targetElement).html(linksHtml);
            }
        },
        error: function() {
            $(targetElement).html('<p class="text-muted">Fehler beim Laden der System-Verknüpfungen</p>');
        }
    });
}

// Kundendetails anzeigen
function viewCustomerDetails(customerId) {
    console.log('Loading customer details for ID:', customerId);
    
    // Modal-Inhalt mit Ladeanzeige
    var detailsHtml = '<div class="text-center">';
    detailsHtml += '<p><i class="bi bi-info-circle"></i> Kundendetails werden geladen...</p>';
    detailsHtml += '<p class="text-muted">Kunden-ID: ' + customerId + '</p>';
    detailsHtml += '</div>';
    
    $('#customerDetailsContent').html(detailsHtml);
    $('#editCustomerFromDetailsBtn').hide();
    $('#customerDetailsModal').modal('show');
    
    // Kundendaten laden
    loadCustomerDetails(customerId);
}

// Kundendetails laden
function loadCustomerDetails(customerId) {
    console.log('AJAX Request: get_customer_details for ID:', customerId);
    
    $.ajax({
        url: '?option=users',
        type: 'POST',
        data: {
            action: 'get_customer_details',
            customer_id: customerId
        },
        success: function(response) {
            console.log('AJAX Response:', response);
            
            if (response.success && response.data) {
                var customer = response.data;
                var detailsHtml = generateCustomerDetailsHtml(customer);
                $('#customerDetailsContent').html(detailsHtml);
                $('#editCustomerFromDetailsBtn').show().off('click').on('click', function() {
                    $('#customerDetailsModal').modal('hide');
                    editCustomer(customerId);
                });
            } else {
                var errorHtml = '<div class="alert alert-danger">';
                errorHtml += '<i class="bi bi-exclamation-triangle"></i> ';
                errorHtml += 'Fehler beim Laden der Kundendetails: ' + (response.message || 'Unbekannter Fehler');
                errorHtml += '</div>';
                $('#customerDetailsContent').html(errorHtml);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', xhr, status, error);
            var errorHtml = '<div class="alert alert-danger">';
            errorHtml += '<i class="bi bi-exclamation-triangle"></i> ';
            errorHtml += 'Fehler beim Laden der Kundendetails: ' + error;
            errorHtml += '</div>';
            $('#customerDetailsContent').html(errorHtml);
        }
    });
}

// HTML für Kundendetails generieren
function generateCustomerDetailsHtml(customer) {
    var html = '<div class="row">';
    
    // Grundlegende Informationen
    html += '<div class="col-12 mb-4">';
    html += '<h6 class="text-primary border-bottom pb-2"><i class="bi bi-person"></i> Grundlegende Informationen</h6>';
    html += '<div class="row">';
    html += '<div class="col-md-6"><strong>Vorname:</strong> ' + (customer.first_name || 'Nicht angegeben') + '</div>';
    html += '<div class="col-md-6"><strong>Nachname:</strong> ' + (customer.last_name || 'Nicht angegeben') + '</div>';
    html += '<div class="col-md-6"><strong>Vollständiger Name:</strong> ' + (customer.full_name || 'Nicht angegeben') + '</div>';
    html += '<div class="col-md-6"><strong>E-Mail:</strong> ' + (customer.email || 'Nicht angegeben') + '</div>';
    html += '<div class="col-md-6"><strong>Unternehmen:</strong> ' + (customer.company || 'Nicht angegeben') + '</div>';
    html += '<div class="col-md-6"><strong>Telefon:</strong> ' + (customer.phone || 'Nicht angegeben') + '</div>';
    html += '</div>';
    html += '</div>';
    
    // Adressinformationen
    html += '<div class="col-12 mb-4">';
    html += '<h6 class="text-primary border-bottom pb-2"><i class="bi bi-geo-alt"></i> Adressinformationen</h6>';
    html += '<div class="row">';
    html += '<div class="col-12"><strong>Adresse:</strong> ' + (customer.address || 'Nicht angegeben') + '</div>';
    html += '<div class="col-md-6"><strong>Stadt:</strong> ' + (customer.city || 'Nicht angegeben') + '</div>';
    html += '<div class="col-md-6"><strong>Postleitzahl:</strong> ' + (customer.postal_code || 'Nicht angegeben') + '</div>';
    html += '<div class="col-md-6"><strong>Land:</strong> ' + (customer.country || 'Nicht angegeben') + '</div>';
    html += '</div>';
    html += '</div>';
    
    // Status und Verifizierung
    html += '<div class="col-12 mb-4">';
    html += '<h6 class="text-primary border-bottom pb-2"><i class="bi bi-shield-check"></i> Status und Verifizierung</h6>';
    html += '<div class="row">';
    var statusClass = 'secondary';
    var statusText = customer.status || 'pending';
    switch(statusText) {
        case 'active': statusClass = 'success'; break;
        case 'suspended': statusClass = 'warning'; break;
        case 'deleted': statusClass = 'danger'; break;
        default: statusClass = 'secondary'; break;
    }
    html += '<div class="col-md-6"><strong>Status:</strong> <span class="badge bg-' + statusClass + '">' + statusText + '</span></div>';
    html += '<div class="col-md-6"><strong>E-Mail verifiziert am:</strong> ' + (customer.email_verified_at ? new Date(customer.email_verified_at).toLocaleString('de-DE') : 'Nicht verifiziert') + '</div>';
    html += '<div class="col-md-6"><strong>Letzter Login:</strong> ' + (customer.last_login ? new Date(customer.last_login).toLocaleString('de-DE') : 'Nie') + '</div>';
    html += '</div>';
    html += '</div>';
    
    // Zeitstempel
    html += '<div class="col-12 mb-4">';
    html += '<h6 class="text-primary border-bottom pb-2"><i class="bi bi-clock"></i> Zeitstempel</h6>';
    html += '<div class="row">';
    html += '<div class="col-md-6"><strong>Erstellt am:</strong> ' + (customer.created_at ? new Date(customer.created_at).toLocaleString('de-DE') : 'Nicht verfügbar') + '</div>';
    html += '<div class="col-md-6"><strong>Zuletzt aktualisiert:</strong> ' + (customer.updated_at ? new Date(customer.updated_at).toLocaleString('de-DE') : 'Nicht verfügbar') + '</div>';
    html += '</div>';
    html += '</div>';
    
    html += '</div>';
    
    return html;
}

// Kunde bearbeiten
function editCustomer(customerId) {
    console.log('Loading customer data for edit, ID:', customerId);
    
    // Alle Felder zurücksetzen
    $('#editCustomerId').val(customerId);
    $('#editCustomerFirstName').val('');
    $('#editCustomerLastName').val('');
    $('#editCustomerFullName').val('');
    $('#editCustomerEmail').val('');
    $('#editCustomerCompany').val('');
    $('#editCustomerPhone').val('');
    $('#editCustomerAddress').val('');
    $('#editCustomerCity').val('');
    $('#editCustomerPostalCode').val('');
    $('#editCustomerCountry').val('');
    $('#editCustomerStatus').val('pending');
    $('#editCustomerPassword').val('');
    $('#editCustomerEmailVerifiedAt').val('');
    $('#editCustomerLastLogin').val('');
    $('#editCustomerCreatedAt').val('');
    $('#editCustomerUpdatedAt').val('');
    
    // Kundendaten laden
    loadCustomerDataForEdit(customerId);
    
    $('#editCustomerModal').modal('show');
}

// Kundendaten für Bearbeitung laden
function loadCustomerDataForEdit(customerId) {
    console.log('AJAX Request: get_customer_details for edit, ID:', customerId);
    
    $.ajax({
        url: '?option=users',
        type: 'POST',
        data: {
            action: 'get_customer_details',
            customer_id: customerId
        },
        success: function(response) {
            console.log('AJAX Response:', response);
            
            if (response.success && response.data) {
                var customer = response.data;
                
                // Grundlegende Informationen
                $('#editCustomerFirstName').val(customer.first_name || '');
                $('#editCustomerLastName').val(customer.last_name || '');
                $('#editCustomerFullName').val(customer.full_name || '');
                $('#editCustomerEmail').val(customer.email || '');
                $('#editCustomerCompany').val(customer.company || '');
                $('#editCustomerPhone').val(customer.phone || '');
                
                // Adressinformationen
                $('#editCustomerAddress').val(customer.address || '');
                $('#editCustomerCity').val(customer.city || '');
                $('#editCustomerPostalCode').val(customer.postal_code || '');
                $('#editCustomerCountry').val(customer.country || '');
                
                // Status und Sicherheit
                $('#editCustomerStatus').val(customer.status || 'pending');
                
                // Zeitstempel
                if (customer.email_verified_at) {
                    var emailVerifiedDate = new Date(customer.email_verified_at);
                    $('#editCustomerEmailVerifiedAt').val(emailVerifiedDate.toISOString().slice(0, 16));
                }
                if (customer.last_login) {
                    var lastLoginDate = new Date(customer.last_login);
                    $('#editCustomerLastLogin').val(lastLoginDate.toISOString().slice(0, 16));
                }
                if (customer.created_at) {
                    var createdDate = new Date(customer.created_at);
                    $('#editCustomerCreatedAt').val(createdDate.toISOString().slice(0, 16));
                }
                if (customer.updated_at) {
                    var updatedDate = new Date(customer.updated_at);
                    $('#editCustomerUpdatedAt').val(updatedDate.toISOString().slice(0, 16));
                }
            } else {
                showAlert('error', 'Fehler beim Laden der Kundendaten: ' + (response.message || 'Unbekannter Fehler'));
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', xhr, status, error);
            showAlert('error', 'Fehler beim Laden der Kundendaten: ' + error);
        }
    });
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

// Kundendetails anzeigen (Alias für viewCustomerDetails)
function showCustomerDetails(customerId) {
    viewCustomerDetails(customerId);
}

// Kunden-Erstellungs-Modal anzeigen
function showCreateCustomerModal() {
    console.log('Opening create customer modal');
    
    // Alle Felder zurücksetzen
    $('#createCustomerFirstName').val('');
    $('#createCustomerLastName').val('');
    $('#createCustomerEmail').val('');
    $('#createCustomerCompany').val('');
    $('#createCustomerPhone').val('');
    $('#createCustomerAddress').val('');
    $('#createCustomerCity').val('');
    $('#createCustomerPostalCode').val('');
    $('#createCustomerCountry').val('');
    $('#createCustomerPassword').val('');
    $('#createCustomerPasswordConfirm').val('');
    $('#createCustomerStatus').val('pending');
    $('#createCustomerEmailVerified').prop('checked', false);
    
    // Event-Handler für Passwort-Bestätigung
    $('#createCustomerPasswordConfirm').off('keyup').on('keyup', function() {
        validatePasswordMatch();
    });
    
    // Form-Submit-Handler hinzufügen
    $('#createCustomerModal form').off('submit').on('submit', function(e) {
        e.preventDefault();
        createCustomer();
    });
    
    $('#createCustomerModal').modal('show');
}

// System-Benutzer-Erstellungs-Formular
$(document).ready(function() {
    $('#createSystemUserForm').on('submit', function(e) {
        e.preventDefault();
        createSystemUser();
    });
    
    // Benutzer-Bearbeitungs-Formular
    $('#editUserForm').on('submit', function(e) {
        e.preventDefault();
        editUserSubmit();
    });
    
    // Kunden-Bearbeitungs-Formular
    $('#editCustomerForm').on('submit', function(e) {
        e.preventDefault();
        editCustomerSubmit();
    });
    
    // Kunden-Löschungs-Formular
    $('#deleteCustomerForm').on('submit', function(e) {
        e.preventDefault();
        deleteCustomerSubmit();
    });

    // Benutzer-Löschungs-Formular
    $('#deleteUserForm').on('submit', function(e) {
        e.preventDefault();
        deleteUserSubmit();
    });
});

function createSystemUser() {
    console.log('Creating system user...');
    
    // Validierung
    var username = $('#createUsername').val();
    var email = $('#createEmail').val();
    var fullName = $('#createFullName').val();
    var password = $('#createPassword').val();
    
    if (!username || !email || !fullName || !password) {
        showNotification('Bitte füllen Sie alle Felder aus', 'error');
        return;
    }
    
    // Formular-Daten sammeln
    var formData = {
        action: 'create_system_user',
        username: username,
        email: email,
        full_name: fullName,
        password: password
    };
    
    console.log('Sending system user data:', formData);
    
    // AJAX-Request
    $.ajax({
        url: '?option=users',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            console.log('System user creation response:', response);
            
            if (response.success) {
                showNotification(response.message, 'success');
                
                // Formular zurücksetzen
                $('#createSystemUserForm')[0].reset();
                
                // Listen aktualisieren
                if (typeof loadUsers === 'function') loadUsers();
                if (typeof loadCustomers === 'function') loadCustomers();
            } else {
                showNotification(response.message || 'Fehler beim Erstellen des Benutzers', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('System user creation error:', error);
            showNotification('Fehler beim Erstellen des Benutzers: ' + error, 'error');
        }
    });
}

// Kunde löschen - Modal öffnen
function deleteCustomer(customerId, customerName) {
    console.log('Opening delete customer modal for:', customerId, customerName);
    
    // Modal-Daten setzen
    $('#deleteCustomerId').val(customerId);
    $('#deleteCustomerName').text(customerName);
    
    // Alle Checkboxen zurücksetzen (außer lokaler Kunde)
    $('#deleteCustomerFromLocal').prop('checked', true);
    $('#deleteCustomerFromOGP').prop('checked', false);
    $('#deleteCustomerFromProxmox').prop('checked', false);
    $('#deleteCustomerFromISPConfig').prop('checked', false);
    
    // Modal anzeigen
    $('#deleteCustomerModal').modal('show');
}

// Benutzer löschen - Submit
function deleteUserSubmit() {
    console.log('Submitting user deletion...');
    var deleteFromSystems = [];
    $('#deleteUserForm input[name="delete_from_systems[]"]:checked').each(function() {
        deleteFromSystems.push($(this).val());
    });

    var formData = {
        action: 'delete_user',
        user_id: $('#deleteUserId').val(),
        delete_from_systems: deleteFromSystems
    };

    console.log('Sending user deletion data:', formData);
    $.ajax({
        url: '?option=users',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            console.log('User deletion response:', response);
            if (response.success) {
                showNotification(response.message, 'success');
                $('#deleteUserModal').modal('hide');
                if (typeof loadUsers === 'function') loadUsers();
                if (typeof loadCustomers === 'function') loadCustomers();
            } else {
                showNotification(response.message || 'Fehler beim Löschen des Benutzers', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('User deletion error:', error);
            showNotification('Fehler beim Löschen des Benutzers: ' + error, 'error');
        }
    });
}

// Benutzer-Bearbeitung abschicken
function editUserSubmit() {
    console.log('Submitting user edit...');
    
    // Formular-Daten sammeln
    var formData = {
        action: 'edit_user',
        user_id: $('#editUserId').val(),
        username: $('#editUsername').val(),
        email: $('#editEmail').val(),
        full_name: $('#editFullName').val(),
        role: $('#editRole').val(),
        active: $('#editActive').val(),
        password: $('#editPassword').val()
    };
    
    console.log('Sending user edit data:', formData);
    
    $.ajax({
        url: '?option=users',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            console.log('User edit response:', response);
            
            if (response.success) {
                showNotification(response.message, 'success');
                $('#editUserModal').modal('hide');
                if (typeof loadUsers === 'function') loadUsers();
                if (typeof loadCustomers === 'function') loadCustomers();
            } else {
                showNotification(response.message || 'Fehler beim Bearbeiten des Benutzers', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('User edit error:', error);
            showNotification('Fehler beim Bearbeiten des Benutzers: ' + error, 'error');
        }
    });
}

// Kunden-Bearbeitung abschicken
function editCustomerSubmit() {
    console.log('Submitting customer edit...');
    
    // Formular-Daten sammeln
    var formData = {
        action: 'edit_customer',
        customer_id: $('#editCustomerId').val(),
        first_name: $('#editCustomerFirstName').val(),
        last_name: $('#editCustomerLastName').val(),
        email: $('#editCustomerEmail').val(),
        company: $('#editCustomerCompany').val(),
        phone: $('#editCustomerPhone').val(),
        address: $('#editCustomerAddress').val(),
        city: $('#editCustomerCity').val(),
        postal_code: $('#editCustomerPostalCode').val(),
        country: $('#editCustomerCountry').val(),
        status: $('#editCustomerStatus').val(),
        password: $('#editCustomerPassword').val()
    };
    
    console.log('Sending customer edit data:', formData);
    
    $.ajax({
        url: '?option=users',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            console.log('Customer edit response:', response);
            
            if (response.success) {
                showNotification(response.message, 'success');
                $('#editCustomerModal').modal('hide');
                if (typeof loadUsers === 'function') loadUsers();
                if (typeof loadCustomers === 'function') loadCustomers();
            } else {
                showNotification(response.message || 'Fehler beim Bearbeiten des Kunden', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('Customer edit error:', error);
            showNotification('Fehler beim Bearbeiten des Kunden: ' + error, 'error');
        }
    });
}

// Kunden-Löschung abschicken
function deleteCustomerSubmit() {
    console.log('Submitting customer deletion...');
    
    // Ausgewählte Systeme sammeln
    var deleteFromSystems = [];
    $('#deleteCustomerForm input[name="delete_from_systems[]"]:checked').each(function() {
        deleteFromSystems.push($(this).val());
    });
    
    if (deleteFromSystems.length === 0) {
        showNotification('Bitte wählen Sie mindestens ein System aus', 'error');
        return;
    }
    
    // Formular-Daten sammeln
    var formData = {
        action: 'delete_customer',
        customer_id: $('#deleteCustomerId').val(),
        delete_from_systems: deleteFromSystems
    };
    
    console.log('Sending customer deletion data:', formData);
    
    $.ajax({
        url: '?option=users',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            console.log('Customer deletion response:', response);
            
            if (response.success) {
                showNotification(response.message, 'success');
                $('#deleteCustomerModal').modal('hide');
                if (typeof loadUsers === 'function') loadUsers();
                if (typeof loadCustomers === 'function') loadCustomers();
            } else {
                showNotification(response.message || 'Fehler beim Löschen des Kunden', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('Customer deletion error:', error);
            showNotification('Fehler beim Löschen des Kunden: ' + error, 'error');
        }
    });
}

// Passwort-Bestätigung validieren
function validatePasswordMatch() {
    var password = $('#createCustomerPassword').val();
    var confirmPassword = $('#createCustomerPasswordConfirm').val();
    
    if (confirmPassword.length > 0) {
        if (password === confirmPassword) {
            $('#createCustomerPasswordConfirm').removeClass('is-invalid').addClass('is-valid');
            $('#createCustomerPasswordConfirm').next('.invalid-feedback').remove();
        } else {
            $('#createCustomerPasswordConfirm').removeClass('is-valid').addClass('is-invalid');
            if ($('#createCustomerPasswordConfirm').next('.invalid-feedback').length === 0) {
                $('#createCustomerPasswordConfirm').after('<div class="invalid-feedback">Passwörter stimmen nicht überein</div>');
            }
        }
    } else {
        $('#createCustomerPasswordConfirm').removeClass('is-valid is-invalid');
        $('#createCustomerPasswordConfirm').next('.invalid-feedback').remove();
    }
}

// Kunde erstellen
function createCustomer() {
    console.log('Creating customer...');
    
    // Validierung
    var firstName = $('#createCustomerFirstName').val();
    var lastName = $('#createCustomerLastName').val();
    var email = $('#createCustomerEmail').val();
    var password = $('#createCustomerPassword').val();
    var passwordConfirm = $('#createCustomerPasswordConfirm').val();
    
    if (!firstName || !lastName || !email || !password) {
        showNotification('Bitte füllen Sie alle Pflichtfelder aus', 'error');
        return;
    }
    
    if (password !== passwordConfirm) {
        showNotification('Passwörter stimmen nicht überein', 'error');
        return;
    }
    
    if (password.length < 8) {
        showNotification('Passwort muss mindestens 8 Zeichen lang sein', 'error');
        return;
    }
    
    // Formular-Daten sammeln
    var formData = {
        action: 'create_customer',
        first_name: firstName,
        last_name: lastName,
        email: email,
        company: $('#createCustomerCompany').val(),
        phone: $('#createCustomerPhone').val(),
        address: $('#createCustomerAddress').val(),
        city: $('#createCustomerCity').val(),
        postal_code: $('#createCustomerPostalCode').val(),
        country: $('#createCustomerCountry').val(),
        password: password,
        password_confirm: passwordConfirm,
        status: $('#createCustomerStatus').val(),
        email_verified: $('#createCustomerEmailVerified').is(':checked') ? '1' : '0'
    };
    
    console.log('Sending customer data:', formData);
    
    // AJAX-Request
    $.ajax({
        url: '?option=users',
        method: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            console.log('Customer creation response:', response);
            
            if (response.success) {
                showNotification(response.message, 'success');
                $('#createCustomerModal').modal('hide');
                
                // Kundenliste aktualisieren
                loadCustomers();
            } else {
                showNotification(response.message || 'Fehler beim Erstellen des Kunden', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('Customer creation error:', error);
            showNotification('Fehler beim Erstellen des Kunden: ' + error, 'error');
        }
    });
}

// Kundenliste laden
function loadCustomers() {
    console.log('Loading customers...');
    
    $.ajax({
        url: '?option=users',
        method: 'POST',
        data: { action: 'get_customers' },
        dataType: 'json',
        success: function(response) {
            console.log('Customers loaded:', response);
            
            if (response.success && response.data) {
                updateCustomersTable(response.data);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading customers:', error);
        }
    });
}

// Kunden-Tabelle aktualisieren
function updateCustomersTable(customers) {
    var tbody = $('#customersTable tbody');
    if (tbody.length === 0) {
        console.log('Customers table not found');
        return;
    }
    
    tbody.empty();
    
    customers.forEach(function(customer) {
        var statusClass = customer.status === 'active' ? 'success' : 
                         customer.status === 'pending' ? 'warning' : 'danger';
        var statusText = customer.status === 'active' ? 'Aktiv' : 
                        customer.status === 'pending' ? 'Ausstehend' : 'Gesperrt';
        
        var row = '<tr>' +
            '<td>' + (customer.id || '') + '</td>' +
            '<td>' + (customer.first_name || '') + ' ' + (customer.last_name || '') + '</td>' +
            '<td>' + (customer.email || '') + '</td>' +
            '<td>' + (customer.company || '-') + '</td>' +
            '<td><span class="badge bg-' + statusClass + '">' + statusText + '</span></td>' +
            '<td>' + (customer.created_at ? new Date(customer.created_at).toLocaleDateString('de-DE') : '') + '</td>' +
            '<td>' +
                '<button class="btn btn-sm btn-outline-primary me-1" onclick="viewCustomerDetails(' + customer.id + ')">' +
                    '<i class="bi bi-eye"></i>' +
                '</button>' +
                '<button class="btn btn-sm btn-outline-warning me-1" onclick="editCustomer(' + customer.id + ')">' +
                    '<i class="bi bi-pencil"></i>' +
                '</button>' +
            '</td>' +
        '</tr>';
        
        tbody.append(row);
    });
}
