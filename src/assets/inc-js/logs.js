$(function() {
    $('#clear-logs-btn').on('click', function(e) {
        e.preventDefault();
        if (!confirm('Löschen bestätigen?')) return;
        
        $.post('index.php', { 
            core: 'admin', 
            action: 'clear_activity_logs' 
        }, function(response) {
            if (response.success) {
                // Tabelle neu laden (Seite neu laden ist am einfachsten)
                location.reload();
            } else {
                alert(response.error || 'Fehler beim Löschen');
            }
        }, 'json');
    });
});
