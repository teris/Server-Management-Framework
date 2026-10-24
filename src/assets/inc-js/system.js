$(function() {
    // Tab-Wechsel ohne Reload
    $('#systemTabs .nav-link').on('click', function(e) {
        e.preventDefault();
        var tab = $(this).data('tab');
        $('#systemTabs .nav-link').removeClass('active');
        $(this).addClass('active');
        $('.tab-pane').removeClass('show active');
        $('#tab-' + tab).addClass('show active');
        window.history.replaceState({}, '', '?option=system&tab=' + tab);
    });
    
    // Beim Laden: richtigen Tab anzeigen (wenn per URL ?tab=... gesetzt)
    var initialTab = (new URLSearchParams(window.location.search)).get('tab') || 'plugins';
    $('#systemTabs .nav-link[data-tab="' + initialTab + '"]').trigger('click');
    
    // Forms use the authenticated index route and its normal CSRF handling.
});
