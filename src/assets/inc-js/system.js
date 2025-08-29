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
    
    // AJAX-Formular-Submit (delegiert)
    $('#systemTabContent').on('submit', 'form', function(e) {
        e.preventDefault();
        var $form = $(this);
        var formData = $form.serialize();
        
        $.post('inc/system.php', formData, function(response) {
            // Nur den Tab-Inhalt ersetzen
            var tab = $form.find('input[name=active_tab]').val();
            var html = $(response).find('#tab-' + tab).html();
            $('#tab-' + tab).html(html);
        });
    });
});
