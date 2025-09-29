<?php
// Installationsassistent für Shop
?>
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="bi bi-bag"></i> Shop - Installation</h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h5><i class="bi bi-info-circle"></i> Willkommen beim Shop-Modul</h5>
                        <p>Dieser Assistent richtet die notwendigen Datenbanktabellen ein und erstellt die Frontend-Seite <code>public/shop.php</code>.</p>
                        <ul>
                            <li>Tabelle <code>shop_products</code></li>
                            <li>Tabelle <code>shop_categories</code></li>
                            <li>Tabelle <code>shop_orders</code></li>
                            <li>Tabelle <code>shop_order_items</code></li>
                            <li>Tabelle <code>email_templates</code> (E-Mail Templates anlegen)</li>
                            <li>Datei <code>public/shop.php</code></li>
                        </ul>
                    </div>

                    <div class="text-center mt-4">
                        <button class="btn btn-primary btn-lg" id="install-shop-btn">
                            <i class="bi bi-download"></i> Installation starten
                        </button>
                    </div>

                    <div id="install-progress" class="mt-4" style="display:none;">
                        <div class="progress">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width:0%"></div>
                        </div>
                        <div class="text-center mt-2">
                            <span id="install-status">Installation wird vorbereitet...</span>
                        </div>
                    </div>

                    <div id="install-result" class="mt-4" style="display:none;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const btn = document.getElementById('install-shop-btn');
    const progress = document.getElementById('install-progress');
    const status = document.getElementById('install-status');
    const result = document.getElementById('install-result');
    const bar = document.querySelector('#install-progress .progress-bar');

    async function request(action, data){
        const formData = { plugin: 'shop', action, ...(data||{}) };
        const res = await fetch(window.location.pathname, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams(formData) });
        return res.json();
    }

    btn.addEventListener('click', async ()=>{
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Installation läuft...';
        progress.style.display = 'block';
        result.style.display = 'none';

        let p = 0; const i = setInterval(()=>{ p = Math.min(90, p + Math.random()*15); bar.style.width = p+'%'; }, 200);
        status.textContent = 'Datenbank und Frontend werden eingerichtet...';
        const r = await request('install_database');
        clearInterval(i);
        bar.style.width = '100%';
        bar.classList.remove('progress-bar-animated');
        if(r.success){
            status.textContent = 'Installation erfolgreich abgeschlossen!';
            result.style.display = 'block';
            result.innerHTML = '<div class="alert alert-success"><i class="bi bi-check-circle"></i> '+ (r.message||'Erfolgreich installiert') +'</div>';
            setTimeout(()=> location.reload(), 1200);
        } else {
            status.textContent = 'Installation fehlgeschlagen!';
            result.style.display = 'block';
            result.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> '+ (r.error||'Fehler') +'</div>';
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-download"></i> Installation starten';
        }
    });
})();
</script>

