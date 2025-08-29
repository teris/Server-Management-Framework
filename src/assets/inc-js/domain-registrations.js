let currentRegistrationId = null;

// Filter registrations
function filterRegistrations() {
    const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
    const domainFilter = document.getElementById('domainFilter').value.toLowerCase();
    const userFilter = document.getElementById('userFilter').value.toLowerCase();
    
    const rows = document.querySelectorAll('.registration-row');
    
    rows.forEach(row => {
        const status = row.dataset.status;
        const domain = row.dataset.domain;
        const user = row.dataset.user;
        
        const statusMatch = !statusFilter || status === statusFilter;
        const domainMatch = !domainFilter || domain.includes(domainFilter);
        const userMatch = !userFilter || user.includes(userFilter);
        
        if (statusMatch && domainMatch && userMatch) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Refresh registrations
function refreshRegistrations() {
    location.reload();
}

// Show notes
function showNotes(notes) {
    document.getElementById('notesContent').textContent = notes;
    new bootstrap.Modal(document.getElementById('notesModal')).show();
}

// Edit registration
function editRegistration(id) {
    currentRegistrationId = id;
    
    // Load registration data via AJAX
    $.ajax({
        url: 'index.php',
        method: 'POST',
        data: {
            core: 'admin',
            action: 'get_domain_registration',
            id: id
        },
        success: function(response) {
            if (response.success) {
                const reg = response.data;
                document.getElementById('editRegistrationId').value = reg.id;
                document.getElementById('editDomain').value = reg.domain;
                document.getElementById('editStatus').value = reg.status;
                document.getElementById('editPurpose').value = reg.purpose;
                document.getElementById('editNotes').value = reg.notes || '';
                document.getElementById('editAdminNotes').value = reg.admin_notes || '';
                
                new bootstrap.Modal(document.getElementById('editRegistrationModal')).show();
            } else {
                showNotification(response.error || 'Fehler beim Laden der Registrierung', 'error');
            }
        },
        error: function() {
            showNotification('Netzwerkfehler', 'error');
        }
    });
}

// Save registration changes
function saveRegistrationChanges() {
    const formData = {
        core: 'admin',
        action: 'update_domain_registration',
        id: currentRegistrationId,
        status: document.getElementById('editStatus').value,
        purpose: document.getElementById('editPurpose').value,
        admin_notes: document.getElementById('editAdminNotes').value
    };
    
    $.ajax({
        url: 'index.php',
        method: 'POST',
        data: formData,
        success: function(response) {
            if (response.success) {
                $('#editRegistrationModal').modal('hide');
                refreshRegistrations();
                showNotification('Registrierung erfolgreich aktualisiert', 'success');
            } else {
                showNotification(response.error || 'Fehler beim Aktualisieren der Registrierung', 'error');
            }
        },
        error: function() {
            showNotification('Netzwerkfehler', 'error');
        }
    });
}

// Approve registration
function approveRegistration(id) {
    showConfirmAction(
        'Registrierung genehmigen',
        'Sind Sie sicher, dass Sie diese Registrierung genehmigen möchten?',
        () => updateRegistrationStatus(id, 'approved')
    );
}

// Reject registration
function rejectRegistration(id) {
    showConfirmAction(
        'Registrierung ablehnen',
        'Sind Sie sicher, dass Sie diese Registrierung ablehnen möchten?',
        () => updateRegistrationStatus(id, 'rejected')
    );
}

// Cancel registration
function cancelRegistration(id) {
    showConfirmAction(
        'Registrierung stornieren',
        'Sind Sie sicher, dass Sie diese Registrierung stornieren möchten?',
        () => updateRegistrationStatus(id, 'cancelled')
    );
}

// Update registration status
function updateRegistrationStatus(id, status) {
    $.ajax({
        url: 'index.php',
        method: 'POST',
        data: {
            core: 'admin',
            action: 'update_domain_registration_status',
            id: id,
            status: status
        },
        success: function(response) {
            if (response.success) {
                $('#confirmActionModal').modal('hide');
                refreshRegistrations();
                showNotification(response.data.message || 'Registrierungsstatus aktualisiert', 'success');
            } else {
                showNotification(response.error || 'Fehler beim Aktualisieren des Status', 'error');
            }
        },
        error: function() {
            showNotification('Netzwerkfehler', 'error');
        }
    });
}

// Show confirm action modal
function showConfirmAction(title, message, onConfirm) {
    document.getElementById('confirmActionTitle').textContent = title;
    document.getElementById('confirmActionMessage').textContent = message;
    
    const confirmButton = document.getElementById('confirmActionButton');
    confirmButton.onclick = onConfirm;
    
    new bootstrap.Modal(document.getElementById('confirmActionModal')).show();
}
