/**
 * Login JavaScript - Interaktive Funktionen für die Login-Seite
 */

$(document).ready(function() {
    'use strict';
    
    // Konstanten
    const LOGIN_TIMEOUT = 10000; // 10 Sekunden
    const MAX_LOGIN_ATTEMPTS = 5;
    const LOCKOUT_DURATION = 300000; // 5 Minuten
    
    // Globale Variablen
    let loginAttempts = 0;
    let isLocked = false;
    let lockoutTimer = null;
    
    // Initialisierung
    init();
    
    /**
     * Hauptfunktion zur Initialisierung
     */
    function init() {
        setupEventListeners();
        setupFormValidation();
        checkRememberMe();
        setupPasswordToggle();
        setupAutoFocus();
        setupKeyboardNavigation();
    }
    
    /**
     * Event Listener einrichten
     */
    function setupEventListeners() {
        // Form Submit
        $('#login-form').on('submit', handleLoginSubmit);
        
        // Password Toggle
        $('#toggle-password').on('click', togglePasswordVisibility);
        
        // Form Validation
        $('.form-control').on('input blur', validateField);
        
        // Remember Me Checkbox
        $('#remember').on('change', handleRememberMeChange);
        
        // Enter Key Navigation
        $('.form-control').on('keypress', handleEnterKey);
        
        // Auto-fill Detection
        $('.form-control').on('animationstart', handleAutoFill);
    }
    
    /**
     * Login-Formular verarbeiten
     */
    function handleLoginSubmit(e) {
        e.preventDefault();
        
        if (isLocked) {
            showLockoutMessage();
            return;
        }
        
        if (loginAttempts >= MAX_LOGIN_ATTEMPTS) {
            lockAccount();
            return;
        }
        
        const formData = {
            email: $('#email').val().trim(),
            password: $('#password').val(),
            remember: $('#remember').is(':checked')
        };
        
        // Validierung
        if (!validateLoginForm(formData)) {
            return;
        }
        
        // Login durchführen
        submitLogin(formData);
    }
    
    /**
     * Login-Formular validieren
     */
    function validateLoginForm(data) {
        let isValid = true;
        const errors = {};
        
        // Email validieren
        if (empty(data.email)) {
            errors.email = 'E-Mail-Adresse ist erforderlich';
            isValid = false;
        } else if (!isValidEmail(data.email)) {
            errors.email = 'Bitte geben Sie eine gültige E-Mail-Adresse ein';
            isValid = false;
        }
        
        // Password validieren
        if (empty(data.password)) {
            errors.password = 'Passwort ist erforderlich';
            isValid = false;
        } else if (data.password.length < 6) {
            errors.password = 'Passwort muss mindestens 6 Zeichen lang sein';
            isValid = false;
        }
        
        // Fehler anzeigen
        if (!isValid) {
            showFormErrors(errors);
        }
        
        return isValid;
    }
    
    /**
     * Formularfehler anzeigen
     */
    function showFormErrors(errors) {
        // Alle Fehler zurücksetzen
        $('.form-control').removeClass('is-invalid');
        $('.invalid-feedback').remove();
        
        // Neue Fehler anzeigen
        Object.keys(errors).forEach(field => {
            const element = $(`#${field}`);
            element.addClass('is-invalid');
            element.after(`<div class="invalid-feedback">${errors[field]}</div>`);
        });
        
        // Scroll zu erstem Fehler
        $('.is-invalid').first().get(0)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Fehler-Animation
        $('.is-invalid').addClass('shake');
        setTimeout(() => {
            $('.is-invalid').removeClass('shake');
        }, 500);
    }
    
    /**
     * Login absenden
     */
    function submitLogin(data) {
        // Loading State
        const submitBtn = $('#login-btn');
        const originalText = submitBtn.html();
        submitBtn.html('<i class="bi bi-hourglass-split"></i> Anmeldung läuft...').prop('disabled', true);
        
        // Loading Overlay anzeigen
        $('#loading-overlay').show();
        
        // Formular-Daten vorbereiten
        const formData = new FormData();
        formData.append('email', data.email);
        formData.append('password', data.password);
        formData.append('remember', data.remember ? '1' : '0');
        
        // AJAX Request
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            timeout: LOGIN_TIMEOUT
        })
        .done(function(response) {
            if (response.includes('dashboard.php') || response.includes('success')) {
                // Login erfolgreich
                showSuccessMessage('Anmeldung erfolgreich! Weiterleitung...');
                setTimeout(() => {
                    window.location.href = 'dashboard.php';
                }, 1000);
            } else {
                // Login fehlgeschlagen
                handleLoginFailure();
            }
        })
        .fail(function(xhr, status, error) {
            handleLoginFailure();
        })
        .always(function() {
            // Loading State zurücksetzen
            submitBtn.html(originalText).prop('disabled', false);
            $('#loading-overlay').hide();
        });
    }
    
    /**
     * Login-Fehler behandeln
     */
    function handleLoginFailure() {
        loginAttempts++;
        
        if (loginAttempts >= MAX_LOGIN_ATTEMPTS) {
            lockAccount();
        } else {
            const remainingAttempts = MAX_LOGIN_ATTEMPTS - loginAttempts;
            showErrorMessage(`Anmeldung fehlgeschlagen. Noch ${remainingAttempts} Versuche übrig.`);
            
            // Formular zurücksetzen
            $('#password').val('').focus();
            
            // Shake-Animation
            $('.login-card').addClass('shake');
            setTimeout(() => {
                $('.login-card').removeClass('shake');
            }, 500);
        }
    }
    
    /**
     * Account sperren
     */
    function lockAccount() {
        isLocked = true;
        showLockoutMessage();
        
        // Formular deaktivieren
        $('#login-form').addClass('locked');
        $('.form-control, .btn').prop('disabled', true);
        
        // Lockout-Timer starten
        lockoutTimer = setTimeout(() => {
            unlockAccount();
        }, LOCKOUT_DURATION);
        
        // Lockout-Log
        logLockout();
    }
    
    /**
     * Account entsperren
     */
    function unlockAccount() {
        isLocked = false;
        loginAttempts = 0;
        
        // Formular aktivieren
        $('#login-form').removeClass('locked');
        $('.form-control, .btn').prop('disabled', false);
        
        // Lockout-Nachricht ausblenden
        $('.lockout-alert').remove();
        
        // Erfolgs-Nachricht anzeigen
        showSuccessMessage('Account entsperrt. Sie können sich jetzt wieder anmelden.');
    }
    
    /**
     * Lockout-Nachricht anzeigen
     */
    function showLockoutMessage() {
        if ($('.lockout-alert').length === 0) {
            const lockoutHtml = `
                <div class="alert alert-warning lockout-alert" role="alert">
                    <i class="bi bi-shield-exclamation"></i>
                    <strong>Account gesperrt!</strong> Zu viele fehlgeschlagene Anmeldeversuche. 
                    Bitte warten Sie 5 Minuten oder kontaktieren Sie den Support.
                </div>
            `;
            $('.login-header').after(lockoutHtml);
        }
    }
    
    /**
     * Lockout loggen
     */
    function logLockout() {
        const logData = {
            email: $('#email').val(),
            ip: getClientIP(),
            userAgent: navigator.userAgent,
            timestamp: new Date().toISOString()
        };
        
        // In localStorage speichern (für Demo-Zwecke)
        const lockoutLogs = JSON.parse(localStorage.getItem('lockoutLogs') || '[]');
        lockoutLogs.push(logData);
        localStorage.setItem('lockoutLogs', JSON.stringify(lockoutLogs));
    }
    
    /**
     * Passwort-Sichtbarkeit umschalten
     */
    function togglePasswordVisibility() {
        const passwordField = $('#password');
        const toggleBtn = $('#toggle-password');
        const icon = toggleBtn.find('i');
        
        if (passwordField.attr('type') === 'password') {
            passwordField.attr('type', 'text');
            icon.removeClass('bi-eye').addClass('bi-eye-slash');
            toggleBtn.attr('title', 'Passwort ausblenden');
        } else {
            passwordField.attr('type', 'password');
            icon.removeClass('bi-eye-slash').addClass('bi-eye');
            toggleBtn.attr('title', 'Passwort anzeigen');
        }
        
        // Animation
        toggleBtn.addClass('pulse');
        setTimeout(() => {
            toggleBtn.removeClass('pulse');
        }, 200);
    }
    
    /**
     * Remember Me Checkbox behandeln
     */
    function handleRememberMeChange() {
        const isChecked = $('#remember').is(':checked');
        
        if (isChecked) {
            showInfoMessage('Ihre Anmeldedaten werden für 30 Tage gespeichert.');
        }
    }
    
    /**
     * Enter-Taste behandeln
     */
    function handleEnterKey(e) {
        if (e.which === 13) { // Enter
            e.preventDefault();
            
            const currentField = $(e.target);
            const nextField = currentField.closest('.mb-3').next().find('.form-control');
            
            if (nextField.length) {
                nextField.focus();
            } else {
                // Letztes Feld - Login absenden
                $('#login-form').submit();
            }
        }
    }
    
    /**
     * Auto-fill behandeln
     */
    function handleAutoFill(e) {
        if (e.animationName === 'onAutoFillStart') {
            $(e.target).addClass('auto-filled');
        } else if (e.animationName === 'onAutoFillCancel') {
            $(e.target).removeClass('auto-filled');
        }
    }
    
    /**
     * Formular-Validierung einrichten
     */
    function setupFormValidation() {
        // Real-time Validierung
        $('.form-control').on('blur', function() {
            validateField($(this));
        });
        
        // Submit Button Status aktualisieren
        updateSubmitButton();
    }
    
    /**
     * Einzelnes Feld validieren
     */
    function validateField(field) {
        const value = field.val().trim();
        const fieldName = field.attr('id');
        
        field.removeClass('is-valid is-invalid');
        $('.invalid-feedback, .valid-feedback').remove();
        
        let isValid = true;
        let message = '';
        
        switch (fieldName) {
            case 'email':
                if (empty(value)) {
                    isValid = false;
                    message = 'E-Mail-Adresse ist erforderlich';
                } else if (!isValidEmail(value)) {
                    isValid = false;
                    message = 'Bitte geben Sie eine gültige E-Mail-Adresse ein';
                }
                break;
                
            case 'password':
                if (empty(value)) {
                    isValid = false;
                    message = 'Passwort ist erforderlich';
                } else if (value.length < 6) {
                    isValid = false;
                    message = 'Passwort muss mindestens 6 Zeichen lang sein';
                }
                break;
        }
        
        if (isValid) {
            field.addClass('is-valid');
            field.after('<div class="valid-feedback">✓ Gültig</div>');
        } else {
            field.addClass('is-invalid');
            field.after(`<div class="invalid-feedback">${message}</div>`);
        }
        
        updateSubmitButton();
    }
    
    /**
     * Submit Button Status aktualisieren
     */
    function updateSubmitButton() {
        const submitBtn = $('#login-btn');
        const hasErrors = $('.is-invalid').length > 0;
        const hasEmptyFields = $('.form-control').filter(function() {
            return $(this).val().trim() === '';
        }).length > 0;
        
        submitBtn.prop('', hasErrors || hasEmptyFields || isLocked);
    }
    
    /**
     * Remember Me Status prüfen
     */
    function checkRememberMe() {
        if (localStorage.getItem('rememberMe') === 'true') {
            $('#remember').prop('checked', true);
        }
    }
    
    /**
     * Auto-Focus einrichten
     */
    function setupAutoFocus() {
        // Fokus auf erstes Feld setzen
        $('#email').focus();
        
        // Fokus nach Fehlern wiederherstellen
        $(document).on('shown.bs.alert', function() {
            if ($('.is-invalid').length > 0) {
                $('.is-invalid').first().focus();
            }
        });
    }
    
    /**
     * Tastaturnavigation einrichten
     */
    function setupKeyboardNavigation() {
        // Tab-Navigation verbessern
        $('.form-control, .btn, .form-check-input').on('keydown', function(e) {
            if (e.key === 'Tab') {
                const focusableElements = $('.form-control, .btn, .form-check-input').filter(':visible');
                const currentIndex = focusableElements.index(this);
                
                if (e.shiftKey) {
                    // Shift+Tab
                    if (currentIndex === 0) {
                        e.preventDefault();
                        focusableElements.last().focus();
                    }
                } else {
                    // Tab
                    if (currentIndex === focusableElements.length - 1) {
                        e.preventDefault();
                        focusableElements.first().focus();
                    }
                }
            }
        });
    }
    
    /**
     * Hilfsfunktionen
     */
    function empty(value) {
        return value === null || value === undefined || value === '';
    }
    
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    function getClientIP() {
        // Für Demo-Zwecke - in Produktion über Server ermitteln
        return '127.0.0.1';
    }
    
    function showSuccessMessage(message) {
        showMessage(message, 'success');
    }
    
    function showErrorMessage(message) {
        showMessage(message, 'danger');
    }
    
    function showInfoMessage(message) {
        showMessage(message, 'info');
    }
    
    function showMessage(message, type) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'}"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        $('.login-header').after(alertHtml);
        
        // Auto-dismiss nach 5 Sekunden
        setTimeout(() => {
            $(`.alert-${type}`).fadeOut();
        }, 5000);
    }
    
    // Cleanup beim Verlassen der Seite
    $(window).on('beforeunload', function() {
        if (lockoutTimer) {
            clearTimeout(lockoutTimer);
        }
    });
    
    // Performance-Optimierung
    let resizeTimer;
    $(window).on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            // Resize-basierte Anpassungen hier
        }, 250);
    });
});
