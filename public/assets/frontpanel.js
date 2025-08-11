/**
 * Frontpanel JavaScript - Interaktive Funktionen für das öffentliche Frontend
 */

$(document).ready(function() {
    'use strict';
    
    // Konstanten
    const REFRESH_INTERVAL = 10000; // 10 Sekunden
    const API_ENDPOINTS = {
        tickets: '../src/api/tickets.php',
        status: '../src/api/status.php'
    };
    
    // Globale Variablen
    let statusRefreshInterval;
    let isSubmitting = false;
    
    // Initialisierung
    init();
    
    /**
     * Hauptfunktion zur Initialisierung
     */
    function init() {
        setupEventListeners();
        startStatusAutoRefresh();
        updateTimestamps();
        setupSmoothScrolling();
        setupFormValidation();
    }
    
    /**
     * Event Listener einrichten
     */
    function setupEventListeners() {
        // Ticket Form Submit
        $('#ticket-form').on('submit', handleTicketSubmit);
        
        // Navigation Smooth Scrolling
        $('.navbar-nav .nav-link[href^="#"]').on('click', function(e) {
            e.preventDefault();
            const target = $(this.getAttribute('href'));
            if (target.length) {
                $('html, body').animate({
                    scrollTop: target.offset().top - 80
                }, 800, 'easeInOutQuart');
            }
        });
        
        // Form Validation
        $('.form-control, .form-select').on('input change', function() {
            validateField($(this));
        });
        
        // Auto-refresh Toggle
        $('.refresh-toggle').on('click', toggleAutoRefresh);
        
        // Status Indicators
        updateStatusIndicators();
    }
    
    /**
     * Ticket-Formular verarbeiten
     */
    function handleTicketSubmit(e) {
        e.preventDefault();
        
        if (isSubmitting) return;
        
        const formData = {
            subject: $('#ticket-subject').val().trim(),
            email: $('#ticket-email').val().trim(),
            priority: $('#ticket-priority').val(),
            message: $('#ticket-message').val().trim(),
            timestamp: new Date().toISOString()
        };
        
        // Validierung
        if (!validateTicketForm(formData)) {
            return;
        }
        
        // Formular absenden
        submitTicket(formData);
    }
    
    /**
     * Ticket-Formular validieren
     */
    function validateTicketForm(data) {
        let isValid = true;
        const errors = {};
        
        // Subject validieren
        if (data.subject.length < 3) {
            errors.subject = 'Betreff muss mindestens 3 Zeichen lang sein';
            isValid = false;
        }
        
        // Email validieren
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(data.email)) {
            errors.email = 'Bitte geben Sie eine gültige E-Mail-Adresse ein';
            isValid = false;
        }
        
        // Message validieren
        if (data.message.length < 10) {
            errors.message = 'Nachricht muss mindestens 10 Zeichen lang sein';
            isValid = false;
        }
        
        if (!isValid) {
            showFormErrors(errors);
        }
        
        return isValid;
    }
    
    /**
     * Formular-Fehler anzeigen
     */
    function showFormErrors(errors) {
        // Alle Fehler zurücksetzen
        $('.form-control').removeClass('is-invalid');
        $('.invalid-feedback').remove();
        
        // Neue Fehler anzeigen
        Object.keys(errors).forEach(field => {
            const fieldElement = $(`#ticket-${field}`);
            fieldElement.addClass('is-invalid');
            fieldElement.after(`<div class="invalid-feedback">${errors[field]}</div>`);
        });
    }
    
    /**
     * Ticket absenden
     */
    function submitTicket(data) {
        isSubmitting = true;
        
        // Submit Button deaktivieren
        const submitBtn = $('#ticket-form button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Wird gesendet...');
        
        $.ajax({
            url: API_ENDPOINTS.tickets,
            method: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            dataType: 'json',
            timeout: 10000,
            success: function(response) {
                if (response.success) {
                    showTicketSuccess();
                    resetTicketForm();
                } else {
                    showTicketError(response.message || 'Ein Fehler ist aufgetreten');
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'Ein Fehler ist aufgetreten';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                showTicketError(errorMessage);
            },
            complete: function() {
                isSubmitting = false;
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    }
    
    /**
     * Ticket-Erfolg anzeigen
     */
    function showTicketSuccess() {
        showToast('Ticket wurde erfolgreich erstellt!', 'success');
        $('#ticket-form')[0].reset();
        $('.form-control').removeClass('is-valid is-invalid');
        $('.invalid-feedback, .valid-feedback').remove();
    }
    
    /**
     * Ticket-Fehler anzeigen
     */
    function showTicketError(message) {
        showToast(message, 'error');
    }
    
    /**
     * Ticket-Formular zurücksetzen
     */
    function resetTicketForm() {
        $('#ticket-form')[0].reset();
        $('.form-control').removeClass('is-valid is-invalid');
        $('.invalid-feedback, .valid-feedback').remove();
        updateSubmitButton();
    }
    
    /**
     * Toast-Nachricht anzeigen
     */
    function showToast(message, type = 'info') {
        const toastClass = type === 'error' ? 'bg-danger' : 
                          type === 'success' ? 'bg-success' : 'bg-info';
        
        const toast = $(`
            <div class="toast align-items-center ${toastClass} text-white border-0" role="alert" aria-live="assertive" aria-atomic="true" style="min-width: 300px;">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="$(this).closest('.toast').remove()" aria-label="Close"></button>
                </div>
            </div>
        `);
        
        $('.toast-container').append(toast);
        
        // Toast nach 5 Sekunden automatisch entfernen
        setTimeout(() => {
            toast.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
        
        // Toast einblenden
        toast.hide().fadeIn(300);
    }
    
    /**
     * Auto-Refresh starten
     */
    function startStatusAutoRefresh() {
        if (statusRefreshInterval) {
            clearInterval(statusRefreshInterval);
        }
        statusRefreshInterval = setInterval(refreshServerStatus, REFRESH_INTERVAL);
        refreshServerStatus(); // Initial refresh
    }
    
    /**
     * Auto-Refresh stoppen
     */
    function stopStatusAutoRefresh() {
        if (statusRefreshInterval) {
            clearInterval(statusRefreshInterval);
            statusRefreshInterval = null;
        }
    }
    
    /**
     * Auto-Refresh umschalten
     */
    function toggleAutoRefresh() {
        if (statusRefreshInterval) {
            stopStatusAutoRefresh();
            $('.refresh-toggle').html('<i class="fas fa-play"></i> Start');
        } else {
            startStatusAutoRefresh();
            $('.refresh-toggle').html('<i class="fas fa-pause"></i> Stop');
        }
    }
    
    /**
     * Server-Status aktualisieren
     */
    function refreshServerStatus() {
        console.log('Refreshing server status...');
        showLoadingIndicator();
        
        $.ajax({
            url: API_ENDPOINTS.status,
            method: 'GET',
            dataType: 'json',
            timeout: 10000,
            success: function(response) {
                if (response.success) {
                    updateStatusDisplay(response.data);
                    hideLoadingIndicator();
                } else {
                    console.error('Failed to refresh status:', response.message);
                    hideLoadingIndicator();
                }
            },
            error: function(xhr, status, error) {
                console.error('Error refreshing status:', error);
                hideLoadingIndicator();
            }
        });
    }
    
    /**
     * Status-Anzeige aktualisieren
     */
    function updateStatusDisplay(data) {
        // Proxmox VMs aktualisieren
        if (data.vms && Array.isArray(data.vms)) {
            updateProxmoxVMsDisplay(data.vms);
        }
        
        // Game Server aktualisieren
        if (data.gameServers && Array.isArray(data.gameServers)) {
            updateGameServersDisplay(data.gameServers);
        }
        
        // System Info aktualisieren
        if (data.systemInfo) {
            updateSystemInfoDisplay(data.systemInfo);
        }
        
        // Zeitstempel aktualisieren
        updateLastRefreshTime();
    }
    
    /**
     * Proxmox VMs Anzeige aktualisieren
     */
    function updateProxmoxVMsDisplay(vms) {
        const container = $('#proxmox-vms-container');
        if (!container.length) return;
        
        let html = '';
        vms.forEach(vm => {
            const cpuPercent = ((vm.cpu_usage || 0) * 100).toFixed(1);
            const memoryPercent = vm.memory && vm.memory > 0 ? 
                ((vm.memory_usage || 0) / vm.memory * 100).toFixed(1) : 0;
            const memoryUsedGB = ((vm.memory_usage || 0) / 1024 / 1024 / 1024).toFixed(1);
            const memoryTotalGB = ((vm.memory || 0) / 1024 / 1024 / 1024).toFixed(1);
            const uptime = vm.uptime ? formatUptime(vm.uptime) : 'N/A';
            
            html += `
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">${escapeHtml(vm.name || 'N/A')}</h6>
                            <span class="badge ${getStatusBadgeClass(vm.status)}">${escapeHtml(vm.status || 'unknown')}</span>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-6">
                                    <small class="text-muted">CPU</small>
                                    <div class="progress mb-1" style="height: 8px;">
                                        <div class="progress-bar" role="progressbar" style="width: ${cpuPercent}%" aria-valuenow="${cpuPercent}" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <small class="text-muted">${cpuPercent}%</small>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Memory</small>
                                    <div class="progress mb-1" style="height: 8px;">
                                        <div class="progress-bar bg-info" role="progressbar" style="width: ${memoryPercent}%" aria-valuenow="${memoryPercent}" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <small class="text-muted">${memoryUsedGB} GB / ${memoryTotalGB} GB</small>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-6">
                                    <small class="text-muted">Cores</small>
                                    <h6>${vm.cores || 'N/A'}</h6>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Uptime</small>
                                    <h6>${uptime}</h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        container.html(html);
    }
    
    /**
     * Game Server Anzeige aktualisieren
     */
    function updateGameServersDisplay(gameServers) {
        const container = $('#game-servers-container');
        const countElement = $('#game-servers-count');
        if (!container.length) return;
        
        let html = '';
        let count = 0;
        
        if (Array.isArray(gameServers)) {
            count = gameServers.length;
            gameServers.forEach(server => {
                html += `
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">${escapeHtml(server.name || 'N/A')}</h6>
                                <span class="badge bg-success">Online</span>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <small class="text-muted">Game</small>
                                        <h6>${escapeHtml(server.game_name || 'N/A')}</h6>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Server</small>
                                        <h6>${escapeHtml(server.remote_server_name || 'N/A')}</h6>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <small class="text-muted">IP Address</small>
                                        <h6>${escapeHtml(server.display_public_ip || 'N/A')}</h6>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Port</small>
                                        <h6>${escapeHtml(server.agent_port || 'N/A')}</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
        }
        
        container.html(html);
        if (countElement.length) {
            countElement.text(count);
        }
    }
    
    /**
     * System Info Anzeige aktualisieren
     */
    function updateSystemInfoDisplay(systemInfo) {
        // Uptime aktualisieren
        if (systemInfo.uptime !== undefined) {
            $('.system-uptime').text(systemInfo.uptime);
        }
        
        // Load Average aktualisieren
        if (systemInfo.load !== undefined) {
            $('.system-load').text(systemInfo.load);
        }
        
        // CPU Usage aktualisieren
        if (systemInfo.cpu_usage !== undefined) {
            const cpuPercent = (systemInfo.cpu_usage * 100).toFixed(1);
            $('.system-cpu').text(cpuPercent + '%');
            $('.system-cpu-bar').css('width', cpuPercent + '%');
        }
        
        // Memory Usage aktualisieren
        if (systemInfo.memory_usage !== undefined && systemInfo.memory_total !== undefined) {
            const memoryPercent = (systemInfo.memory_usage / systemInfo.memory_total * 100).toFixed(1);
            $('.system-memory').text(memoryPercent + '%');
            $('.system-memory-bar').css('width', memoryPercent + '%');
        }
        
        // Disk Usage aktualisieren
        if (systemInfo.disk_usage !== undefined && systemInfo.disk_total !== undefined) {
            const diskPercent = (systemInfo.disk_usage / systemInfo.disk_total * 100).toFixed(1);
            $('.system-disk').text(diskPercent + '%');
            $('.system-disk-bar').css('width', diskPercent + '%');
        }
    }
    
    /**
     * Letzten Refresh-Zeitstempel aktualisieren
     */
    function updateLastRefreshTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString();
        $('#last-refresh-time').text(timeString);
        $('#last-refresh-time-game').text(timeString);
    }
    
    /**
     * Lade-Indikator anzeigen
     */
    function showLoadingIndicator() {
        $('.status-section').each(function() {
            if (!$(this).find('.loading-spinner').length) {
                $(this).prepend('<div class="loading-spinner text-center py-3"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>');
            }
        });
    }
    
    /**
     * Lade-Indikator ausblenden
     */
    function hideLoadingIndicator() {
        $('.loading-spinner').remove();
    }
    
    /**
     * Uptime formatieren
     */
    function formatUptime(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        
        if (hours > 24) {
            const days = Math.floor(hours / 24);
            const remainingHours = hours % 24;
            return `${days}d ${remainingHours}h ${minutes}m`;
        } else if (hours > 0) {
            return `${hours}h ${minutes}m ${secs}s`;
        } else {
            return `${minutes}m ${secs}s`;
        }
    }
    
    /**
     * Status Badge Klasse ermitteln
     */
    function getStatusBadgeClass(status) {
        switch (status?.toLowerCase()) {
            case 'running':
                return 'bg-success';
            case 'stopped':
                return 'bg-danger';
            case 'paused':
                return 'bg-warning';
            case 'suspended':
                return 'bg-secondary';
            default:
                return 'bg-info';
        }
    }
    
    /**
     * HTML escapen
     */
    function escapeHtml(text) {
        if (text === null || text === undefined) return 'N/A';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Smooth Scrolling einrichten
     */
    function setupSmoothScrolling() {
        $('a[href^="#"]').on('click', function(e) {
            e.preventDefault();
            
            const target = $(this.getAttribute('href'));
            if (target.length) {
                $('html, body').animate({
                    scrollTop: target.offset().top - 80
                }, 800, 'easeInOutQuart');
            }
        });
    }
    
    /**
     * Formular-Validierung einrichten
     */
    function setupFormValidation() {
        $('.form-control').on('blur', function() {
            validateField($(this));
        });
        
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
            case 'ticket-subject':
                if (value.length < 3) {
                    isValid = false;
                    message = 'Betreff muss mindestens 3 Zeichen lang sein';
                }
                break;
                
            case 'ticket-email':
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    isValid = false;
                    message = 'Bitte geben Sie eine gültige E-Mail-Adresse ein';
                }
                break;
                
            case 'ticket-message':
                if (value.length < 10) {
                    isValid = false;
                    message = 'Nachricht muss mindestens 10 Zeichen lang sein';
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
        const submitBtn = $('#ticket-form button[type="submit"]');
        const hasErrors = $('.is-invalid').length > 0;
        const hasEmptyFields = $('.form-control, .form-select').filter(function() {
            return $(this).val().trim() === '';
        }).length > 0;
        
        submitBtn.prop('disabled', hasErrors || hasEmptyFields);
    }
    
    /**
     * Timestamps aktualisieren
     */
    function updateTimestamps() {
        const now = new Date();
        $('.text-muted:contains("Letzte Aktualisierung")').each(function() {
            $(this).text(`Letzte Aktualisierung: ${now.toLocaleTimeString()}`);
        });
    }
    
    /**
     * Status-Indikatoren aktualisieren
     */
    function updateStatusIndicators() {
        $('.badge').each(function() {
            const badge = $(this);
            const status = badge.text().toLowerCase();
            
            if (!badge.find('.status-indicator').length) {
                badge.prepend('<span class="status-indicator"></span>');
            }
            
            const indicator = badge.find('.status-indicator');
            indicator.removeClass('status-online status-offline status-warning');
            
            if (status === 'running' || status === 'online') {
                indicator.addClass('status-online');
            } else if (status === 'stopped' || status === 'offline') {
                indicator.addClass('status-offline');
            } else {
                indicator.addClass('status-warning');
            }
        });
    }
    
    /**
     * Easing-Funktion für Smooth Scrolling
     */
    $.easing.easeInOutQuart = function(x, t, b, c, d) {
        if ((t /= d / 2) < 1) return c / 2 * t * t * t * t + b;
        return -c / 2 * ((t -= 2) * t * t * t - 2) + b;
    };
    
    // Cleanup beim Verlassen der Seite
    $(window).on('beforeunload', function() {
        if (statusRefreshInterval) {
            clearInterval(statusRefreshInterval);
        }
    });
    
    // Manual refresh button functionality
    $('#manual-refresh-btn').on('click', function() {
        $(this).find('i').addClass('fa-spin');
        refreshServerStatus();
        setTimeout(() => {
            $(this).find('i').removeClass('fa-spin');
        }, 1000);
    });
    
    // Pause auto-refresh when tab is not visible
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            if (statusRefreshInterval) {
                clearInterval(statusRefreshInterval);
                statusRefreshInterval = null;
            }
        } else {
            startStatusAutoRefresh();
        }
    });
});
