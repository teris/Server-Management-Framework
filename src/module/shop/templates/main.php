<?php
require_once __DIR__ . '/../Module.php';
$shopModule = new ShopModule('shop');

if ($shopModule->needsInstallation()) {
    include __DIR__ . '/install.php';
} else {
?>
<?php
// Admin-UI für Shop Management (wird nur angezeigt, wenn Installation abgeschlossen ist)
?>
<div class="container-fluid">
    <h2 class="mb-4">Shop Management</h2>

    <ul class="nav nav-tabs" id="shop-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button" role="tab">Produkte</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab">Einstellungen</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="addons-tab" data-bs-toggle="tab" data-bs-target="#addons" type="button" role="tab">Addons</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders" type="button" role="tab">Bestellungen</button>
        </li>
    </ul>

    <div class="tab-content p-3 border border-top-0" id="shop-tabs-content">
        <!-- Produkte -->
        <div class="tab-pane fade show active" id="products" role="tabpanel" aria-labelledby="products-tab">
            <form id="product-form" class="mb-3">
                <div class="row g-2">
                    <div class="col-md-3">
                        <input class="form-control" name="sku" placeholder="SKU" required>
                    </div>
                    <div class="col-md-3">
                        <input class="form-control" name="name" placeholder="Name" required>
                    </div>
                    <div class="col-md-2">
                        <input class="form-control" name="price" placeholder="Preis (z.B. 1,99 €)" required>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="currency">
                            <option>EUR</option>
                            <option>USD</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" name="status">
                            <option value="active">active</option>
                            <option value="inactive">inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="access_scope" title="Zugriffsbereich">
                            <option value="internal">internal</option>
                            <option value="proxmox">proxmox</option>
                            <option value="ispconfig">ispconfig</option>
                            <option value="ovh">ovh</option>
                            <option value="ogp">ogp</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <textarea class="form-control" name="description" rows="4" placeholder="Beschreibung"></textarea>
                    </div>
                    <div class="col-md-12">
                        <button class="btn btn-primary" type="submit">Speichern</button>
                    </div>
                </div>
            </form>
            
            <div class="table-responsive">
                <table class="table table-striped" id="products-table">
                    <thead><tr><th>SKU</th><th>Name</th><th>Preis</th><th>Status</th><th>Scope</th><th>Kategorie</th><th>Aktualisiert</th><th></th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <!-- Einstellungen -->
        <div class="tab-pane fade" id="settings" role="tabpanel" aria-labelledby="settings-tab">
            <form id="shop-settings-form">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">AGB Seite (Slug/URL)</label>
                        <input class="form-control" name="terms" placeholder="agb">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Datenschutz (Slug/URL)</label>
                        <input class="form-control" name="privacy" placeholder="datenschutz">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Impressum (Slug/URL)</label>
                        <input class="form-control" name="imprint" placeholder="impressum">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">FAQ (Slug/URL)</label>
                        <input class="form-control" name="faq" placeholder="faq">
                    </div>
                </div>
                <div class="form-check form-switch mt-3">
                    <input class="form-check-input" type="checkbox" id="maintenance-switch">
                    <label class="form-check-label" for="maintenance-switch">Wartungsmodus aktivieren</label>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-success">Einstellungen speichern</button>
                </div>
            </form>

            <hr>
            <h5>Kategorien</h5>
            <form id="category-form" class="row g-2 mb-3">
                <div class="col-md-4"><input class="form-control" name="slug" placeholder="Kategorie-Slug (z.B. games)" required></div>
                <div class="col-md-6"><input class="form-control" name="name" placeholder="Subkategorie-Name (z.B. Webseite, TS3)" required></div>
                <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Hinzufügen</button></div>
            </form>
            <div class="table-responsive">
                <table class="table table-sm" id="categories-table">
                    <thead><tr><th>Slug</th><th>Subkategorien</th><th>Aktion</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>

            <hr>
            <h5>E-Mail Template-Variablen</h5>
            <div class="card mb-3">
                <div class="card-body">
                    <p class="text-muted mb-2">Verfügbar für das Template <code>Bestellbestätigung</code>:</p>
                    <ul class="mb-0">
                        <li><code>{order_number}</code> – Bestellnummer</li>
                        <li><code>{order_total}</code> – Gesamtsumme (formatiert, z. B. 12,34 EUR)</li>
                        <li><code>{order_items}</code> – HTML-Liste der Positionen</li>
                        <li><code>{site_name}</code> – Seiten-/Shopname</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Addons -->
        <div class="tab-pane fade" id="addons" role="tabpanel" aria-labelledby="addons-tab">
            <p class="text-muted">Hier können shop-typische Erweiterungen (Zahlmethoden, Gutscheine, etc.) verwaltet werden.</p>
            <div id="addons-list" class="row g-3"></div>
        </div>

        <!-- Bestellungen -->
        <div class="tab-pane fade" id="orders" role="tabpanel" aria-labelledby="orders-tab">
            <div class="table-responsive">
                <table class="table table-striped" id="orders-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Bestellnummer</th>
                            <th>Kunde</th>
                            <th>Summe</th>
                            <th>Status</th>
                            <th>Datum</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="modal fade" id="orderModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Bestellung</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div id="order-details">Lade…</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    async function request(action, data){
        const formData = { plugin: 'shop', action, ...data };
        const res = await fetch(window.location.pathname, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(formData) });
        return res.json();
    }

    async function loadProducts(){
        const r = await request('list_products', {});
        const tbody = document.querySelector('#products-table tbody');
        tbody.innerHTML = '';
        if(r.success && Array.isArray(r.data)){
            r.data.forEach(p => {
                const price = (p.price_cents/100).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + (p.currency||'EUR');
                const updated = p.updated_at ? new Date(p.updated_at).toLocaleString('de-DE') : '';
                const tr = document.createElement('tr');
                const cat = p.category_slug || '';
                tr.innerHTML = `<td>${p.sku}</td><td>${p.name}</td><td>${price}</td><td>${p.status}</td><td>${p.access_scope||''}</td><td>${cat}</td><td>${updated}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary me-1" data-edit-product="${p.sku}">Bearbeiten</button>
                        <button class="btn btn-sm btn-danger" data-del-product="${p.sku}">Löschen</button>
                    </td>`;
                tbody.appendChild(tr);
            });
        }
    }

    async function loadSettings(){
        const r = await request('get_settings', {});
        if(r.success && r.data){
            const s = r.data.settings || {};
            document.querySelector('#shop-settings-form [name="terms"]').value = s.terms || 'agb';
            document.querySelector('#shop-settings-form [name="privacy"]').value = s.privacy || 'datenschutz';
            document.querySelector('#shop-settings-form [name="imprint"]').value = s.imprint || 'impressum';
            document.querySelector('#shop-settings-form [name="faq"]').value = s.faq || 'faq';
            document.getElementById('maintenance-switch').checked = !!r.data.maintenance;
        }
    }

    document.getElementById('product-form').addEventListener('submit', async (e)=>{
        e.preventDefault();
        const fd = new FormData(e.target);
        const data = Object.fromEntries(fd.entries());
        try{
            const r = await request('save_product', data);
            if(r.success){ e.target.reset(); await loadProducts(); }
            else { alert('Fehler beim Speichern: ' + (r.error||'Unbekannter Fehler')); }
        }catch(err){ console.error(err); alert('Fehler beim Speichern (Netzwerk/JSON)'); }
    });

    document.addEventListener('click', async (e)=>{
        const btnDel = e.target.closest('[data-del-product]');
        if(btnDel){
            const sku = btnDel.getAttribute('data-del-product');
            try{
                const r = await request('delete_product', { sku });
                if(r.success){ await loadProducts(); }
                else { alert('Fehler beim Löschen: ' + (r.error||'Unbekannter Fehler')); }
            }catch(err){ console.error(err); alert('Fehler beim Löschen (Netzwerk/JSON)'); }
            return;
        }
        const btnEdit = e.target.closest('[data-edit-product]');
        if(btnEdit){
            const sku = btnEdit.getAttribute('data-edit-product');
            const r = await request('get_product', { sku });
            if(r.success && r.data){
                const f = document.getElementById('product-form');
                f.querySelector('[name="sku"]').value = r.data.sku;
                f.querySelector('[name="name"]').value = r.data.name || '';
                const displayPrice = (r.data.price_cents/100).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                f.querySelector('[name="price"]').value = displayPrice + ' ' + (r.data.currency || 'EUR');
                f.querySelector('[name="currency"]').value = r.data.currency || 'EUR';
                f.querySelector('[name="status"]').value = r.data.status || 'active';
                f.querySelector('[name="access_scope"]').value = r.data.access_scope || 'internal';
                f.querySelector('[name="description"]').value = r.data.description || '';
                const catSel = document.querySelector('#product-category');
                if (catSel) catSel.value = r.data.category_slug || '';
                const sub = document.querySelector('#product-subcategory');
                if (sub) sub.value = r.data.subcategory || '';
                f.scrollIntoView({behavior:'smooth'});
            }
        }
    });

    // Kategorien
    async function loadCategories(){
        const r = await request('list_categories', {});
        const tbody = document.querySelector('#categories-table tbody');
        tbody.innerHTML = '';
        if(r.success && Array.isArray(r.data)){
            r.data.forEach(c => {
                const tr = document.createElement('tr');
                const names = (c.names||[]).map(n=>`<span class="badge bg-secondary me-1">${n}</span>`).join(' ');
                tr.innerHTML = `<td>${c.slug}</td><td>${names}</td><td>
                    <button class="btn btn-sm btn-outline-primary me-1" data-edit-category="${c.slug}">Sub hinzufügen</button>
                    <button class="btn btn-sm btn-outline-danger" data-del-category="${c.slug}">Kategorie löschen</button>
                </td>`;
                tbody.appendChild(tr);
            });
        }
        // Kategorie-/Subkategorie-Auswahl im Produktformular aktualisieren
        updateCategorySelect(r.data || []);
    }

    function updateCategorySelect(cats){
        let sel = document.querySelector('#product-category');
        if(!sel){
            const holder = document.createElement('div');
            holder.className = 'col-md-3';
            holder.innerHTML = '<select class="form-select" id="product-category" name="category_slug"><option value="">(Keine Kategorie)</option></select>';
            document.querySelector('#product-form .row').appendChild(holder);
            sel = holder.querySelector('select');
        }
        sel.innerHTML = '<option value="">(Keine Kategorie)</option>' + cats.map(c=>`<option value="${c.slug}">${c.slug}</option>`).join('');
        // Subkategorie-Feld sicherstellen
        let sub = document.querySelector('#product-subcategory');
        if(!sub){
            const holder = document.createElement('div');
            holder.className = 'col-md-3';
            holder.innerHTML = '<input class="form-control" id="product-subcategory" name="subcategory" placeholder="Subkategorie (optional)">';
            document.querySelector('#product-form .row').appendChild(holder);
        }
    }

    document.getElementById('category-form').addEventListener('submit', async (e)=>{
        e.preventDefault();
        const fd = new FormData(e.target);
        const data = Object.fromEntries(fd.entries());
        const r = await request('save_category', data);
        if(r.success){ e.target.reset(); await loadCategories(); }
    });

    document.addEventListener('click', async (e)=>{
        const btnCatDel = e.target.closest('[data-del-category]');
        if(btnCatDel){
            const slug = btnCatDel.getAttribute('data-del-category');
            const r = await request('delete_category', { slug });
            if(r.success){ await loadCategories(); }
            return;
        }
        const btnCatEdit = e.target.closest('[data-edit-category]');
        if(btnCatEdit){
            const slug = btnCatEdit.getAttribute('data-edit-category');
            const r = await request('get_category', { slug });
            if(r.success && r.data){
                const f = document.getElementById('category-form');
                f.querySelector('[name="slug"]').value = r.data.slug;
                f.querySelector('[name="name"]').value = '';
            }
        }
    });

    async function loadAddons(){
        const r = await request('list_addons', {});
        const list = document.getElementById('addons-list');
        list.innerHTML = '';
        if(r.success && Array.isArray(r.data)){
            r.data.forEach(a => {
                const col = document.createElement('div');
                col.className = 'col-md-4';
                col.innerHTML = `
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">${a.name}</h5>
                            <p class="card-text">${a.description || ''}</p>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" ${a.enabled ? 'checked' : ''} data-toggle-addon="${a.key}">
                                <label class="form-check-label">Aktiviert</label>
                            </div>
                        </div>
                    </div>`;
                list.appendChild(col);
            });
        }
    }

    document.addEventListener('change', async (e)=>{
        const tgl = e.target.closest('[data-toggle-addon]');
        if(tgl){
            const key = tgl.getAttribute('data-toggle-addon');
            await request('toggle_addon', { key, enabled: e.target.checked ? '1' : '0' });
        }
    });

    // Bestellungen
    async function loadOrders(){
        const r = await request('list_orders', {});
        const tbody = document.querySelector('#orders-table tbody');
        tbody.innerHTML = '';
        if(r.success && Array.isArray(r.data)){
            r.data.forEach(o => {
                const tr = document.createElement('tr');
                const total = (o.total_cents/100).toLocaleString('de-DE', {minimumFractionDigits:2}) + ' ' + (o.currency||'EUR');
                const date = o.created_at ? new Date(o.created_at).toLocaleString('de-DE') : '';
                tr.innerHTML = `
                    <td>${o.id}</td>
                    <td><code>${o.order_number}</code></td>
                    <td>${o.customer_email || ''}</td>
                    <td class="text-end">${total}</td>
                    <td>${o.status}</td>
                    <td>${date}</td>
                    <td><button class="btn btn-sm btn-outline-primary" data-view-order="${o.id}">Ansehen</button></td>`;
                tbody.appendChild(tr);
            });
        }
    }

    document.addEventListener('click', async (e)=>{
        const btnView = e.target.closest('[data-view-order]');
        if(btnView){
            const id = btnView.getAttribute('data-view-order');
            const r = await request('get_order', { order_id: id });
            if(r.success && r.data){
                const o = r.data;
                const items = (o.items||[]).map(it => `
                    <tr><td>${it.sku}</td><td>${it.name}</td><td class="text-end">${it.quantity}</td><td class="text-end">${(it.price_cents/100).toLocaleString('de-DE',{minimumFractionDigits:2})} ${it.currency}</td></tr>
                `).join('');
                const html = `
                    <div class="mb-2"><strong>Bestellnummer:</strong> ${o.order_number}</div>
                    <div class="mb-2"><strong>Kunde:</strong> ${o.customer_email||''}</div>
                    <div class="mb-2"><strong>Status:</strong> ${o.status}</div>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th>SKU</th><th>Produkt</th><th class="text-end">Menge</th><th class="text-end">Preis</th></tr></thead>
                            <tbody>${items}</tbody>
                        </table>
                    </div>`;
                document.getElementById('order-details').innerHTML = html;
                const modalEl = document.getElementById('orderModal');
                const footer = document.createElement('div');
                footer.className = 'modal-footer';
                footer.innerHTML = `
                    <button type="button" class="btn btn-success" data-accept-order="${o.id}"><i class="bi bi-check2"></i> Bestätigen</button>
                    <button type="button" class="btn btn-warning" data-reject-order="${o.id}"><i class="bi bi-x-circle"></i> Ablehnen</button>
                    <button type="button" class="btn btn-danger" data-delete-order="${o.id}"><i class="bi bi-trash"></i> Löschen</button>
                `;
                const mc = modalEl.querySelector('.modal-content');
                mc.querySelector('.modal-footer')?.remove();
                mc.appendChild(footer);
                new bootstrap.Modal(modalEl).show();
            }
        }
    });

    // Order-Action Buttons (accept/reject/delete)
    document.addEventListener('click', async (e)=>{
        const btnAccept = e.target.closest('[data-accept-order]');
        const btnReject = e.target.closest('[data-reject-order]');
        const btnDelete = e.target.closest('[data-delete-order]');
        if(btnAccept){
            const id = btnAccept.getAttribute('data-accept-order');
            const r = await request('update_order_status', { order_id: id, status: 'paid' });
            if(r.success){ loadOrders(); document.querySelector('#orderModal .btn-close')?.click(); }
            return;
        }
        if(btnReject){
            const id = btnReject.getAttribute('data-reject-order');
            const r = await request('update_order_status', { order_id: id, status: 'rejected' });
            if(r.success){ loadOrders(); document.querySelector('#orderModal .btn-close')?.click(); }
            return;
        }
        if(btnDelete){
            const id = btnDelete.getAttribute('data-delete-order');
            if(!confirm('Bestellung wirklich löschen?')) return;
            const r = await request('delete_order', { order_id: id });
            if(r.success){ loadOrders(); document.querySelector('#orderModal .btn-close')?.click(); }
            return;
        }
    });

    document.getElementById('shop-settings-form').addEventListener('submit', async (e)=>{
        e.preventDefault();
        const fd = new FormData(e.target);
        const data = Object.fromEntries(fd.entries());
        await request('save_settings', data);
        // Optional: Feedback anzeigen
    });

    document.getElementById('maintenance-switch').addEventListener('change', async (e)=>{
        await request('toggle_maintenance', { enabled: e.target.checked ? '1' : '0' });
    });

    loadProducts();
    loadSettings();
    loadCategories();
    loadAddons();
    loadOrders();
})();
</script>
<?php } // Ende else Installation ?>
