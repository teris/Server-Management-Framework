function loadIpMacReverseTable() {
    var bar = document.getElementById('ip-progressbar');
    var inner = document.getElementById('ip-progressbar-inner');
    var content = document.getElementById('ip-content');
    if (!bar || !inner || !content) return;
    
    bar.style.display = 'block';
    inner.style.width = '0%';
    inner.innerText = '0%';
    content.innerHTML = '';
    
    // Simulierter Fortschritt
    var percent = 0;
    var fakeInterval = setInterval(function() {
        percent += Math.floor(Math.random() * 8) + 2;
        if (percent > 90) percent = 90;
        inner.style.width = percent + '%';
        inner.innerText = percent + '%';
    }, 80);
    
    // AJAX-Request
    fetch('ajax_ip_mac_reverse.php')
        .then(response => response.json())
        .then(data => {
            clearInterval(fakeInterval);
            inner.style.width = '100%';
            inner.innerText = '100%';
            setTimeout(function() { bar.style.display = 'none'; }, 400);
            
            if (Array.isArray(data) && data.length > 0) {
                var html = '<table class="table table-bordered table-striped table-sm align-middle">';
                html += '<thead class="table-light"><tr>' +
                        '<th>IP</th><th>IP Reverse</th><th>MAC-Adresse</th><th>Typ</th>' +
                        '</tr></thead><tbody>';
                
                data.forEach(function(row) {
                    html += '<tr>' +
                        '<td>' + escapeHtml(row.ipReverse) + '</td>' +
                        '<td>' + escapeHtml(row.reverse) + '</td>' +
                        '<td>' + (row.macAddress === 'Nicht zugewiesen' ? '<span class="text-danger fw-bold">Nicht zugewiesen</span>' : escapeHtml(row.macAddress)) + '</td>' +
                        '<td>' + (row.type === 'Nicht zugewiesen' ? '<span class="text-danger fw-bold">Nicht zugewiesen</span>' : escapeHtml(row.type)) + '</td>' +
                        '</tr>';
                });
                
                html += '</tbody></table>';
                content.innerHTML = html;
            } else {
                content.innerHTML = '<div class="alert alert-info">Keine passenden Kombinationen gefunden</div>';
            }
        })
        .catch(function() {
            clearInterval(fakeInterval);
            bar.style.display = 'none';
            content.innerHTML = '<div class="alert alert-danger">Fehler beim Laden</div>';
        });
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>"']/g, function(m) {
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[m];
    });
}

document.addEventListener('DOMContentLoaded', function() {
    var ipTab = document.getElementById('ip-tab');
    if (ipTab) {
        ipTab.addEventListener('shown.bs.tab', function () {
            loadIpMacReverseTable();
        });
    } else {
        // Falls kein Tab, direkt laden
        loadIpMacReverseTable();
    }
});
