$(document).ready(function() {
    // Formular-Validierung
    $('#createUserForm').submit(function(e) {
        var currentStep = parseInt($('input[name="current_step"]').val()) || 1;
        
        if (currentStep == 1) {
            // Mindestens ein System auswählen
            var systems = $('input[name="systems[]"]:checked').length;
            if (systems === 0) {
                e.preventDefault();
                alert('Bitte wählen Sie mindestens ein System aus');
                return false;
            }
        }
        
        if (currentStep == 2) {
            // Passwort-Stärke prüfen
            var password = $('#password').val();
            if (password.length < 8) {
                e.preventDefault();
                alert('Das Passwort muss mindestens 8 Zeichen lang sein.');
                return false;
            }
            
            // ISPConfig Passwort-Stärke prüfen
            var strongPasswordRegex = /(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*\W)/;
            if (!strongPasswordRegex.test(password)) {
                e.preventDefault();
                alert('Das Passwort muss mindestens einen Großbuchstaben, einen Kleinbuchstaben, eine Zahl und ein Sonderzeichen enthalten (ISPConfig-Anforderung).');
                return false;
            }
        }
        
        return true;
    });
});
