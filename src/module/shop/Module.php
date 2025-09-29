<?php
require_once dirname(dirname(__FILE__)) . '/ModuleBase.php';

class ShopModule extends ModuleBase {

    public function init() {
        // Optional: Initialisierung
    }

    public function getContent() {
        if (!$this->canAccess()) {
            return '<div class="alert alert-danger">' . $this->t('access_denied', 'Zugriff verweigert') . '</div>';
        }

        if ($this->needsInstallation()) {
            return $this->render('install', []);
        }

        return $this->render('main', []);
    }

    public function handleAjaxRequest($action, $data) {
        try {
            switch ($action) {
                case 'install_database':
                    return $this->installDatabase();
                case 'list_products':
                    return $this->listProducts();
                case 'get_product':
                    return $this->getProduct($data);
                case 'save_product':
                    return $this->saveProduct($data);
                case 'delete_product':
                    return $this->deleteProduct($data);
                case 'get_settings':
                    return $this->getSettings();
                case 'save_settings':
                    return $this->saveSettings($data);
                case 'toggle_maintenance':
                    return $this->toggleMaintenance($data);
                // Kategorien
                case 'list_categories':
                    return $this->listCategories();
                case 'get_category':
                    return $this->getCategory($data);
                case 'save_category':
                    return $this->saveCategory($data);
                case 'delete_category':
                    return $this->deleteCategory($data);
                // Addons
                case 'list_addons':
                    return $this->listAddons();
                case 'toggle_addon':
                    return $this->toggleAddon($data);
                // Bestellungen
                case 'list_orders':
                    return $this->listOrders();
                case 'get_order':
                    return $this->getOrder($data);
                case 'update_order_status':
                    return $this->updateOrderStatus($data);
                case 'delete_order':
                    return $this->deleteOrder($data);
                default:
                    return [ 'success' => false, 'error' => 'Unknown action' ];
            }
        } catch (Exception $e) {
            return [ 'success' => false, 'error' => $e->getMessage() ];
        }
    }

    public function needsInstallation() {
        try {
            $db = DatabaseManager::getInstance()->getDriver();
            $stmt = $db->query("SELECT 1 FROM shop_products LIMIT 1");
            $db->fetch($stmt);
            return false;
        } catch (Exception $e) {
            return true;
        }
    }

    private function installDatabase() {
        try {
            $db = DatabaseManager::getInstance()->getDriver();

            // Produkte-Tabelle
            $db->query("CREATE TABLE IF NOT EXISTS shop_products (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                sku VARCHAR(190) UNIQUE,
                name VARCHAR(255),
                description MEDIUMTEXT,
                price_cents INT DEFAULT 0,
                currency VARCHAR(8) DEFAULT 'EUR',
                category_id INT DEFAULT NULL,
                category_slug VARCHAR(190) DEFAULT NULL,
                subcategory VARCHAR(190) DEFAULT NULL,
                status VARCHAR(32) DEFAULT 'active',
                access_scope VARCHAR(64) DEFAULT 'internal',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )");
            // Versuche fehlende Spalten hinzuzufügen (ignoriert Fehler wenn vorhanden)
            try { $db->query("ALTER TABLE shop_products ADD COLUMN category_slug VARCHAR(190) NULL"); } catch (Exception $e) {}
            try { $db->query("ALTER TABLE shop_products ADD COLUMN subcategory VARCHAR(190) NULL"); } catch (Exception $e) {}

            // Kategorien-Tabelle
            $db->query("CREATE TABLE IF NOT EXISTS shop_categories (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                slug VARCHAR(190) UNIQUE,
                name VARCHAR(255),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )");

            // Orders-Tabellen
            $db->query("CREATE TABLE IF NOT EXISTS shop_orders (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                order_number VARCHAR(64) UNIQUE,
                customer_email VARCHAR(255),
                total_cents INT DEFAULT 0,
                currency VARCHAR(8) DEFAULT 'EUR',
                status VARCHAR(32) DEFAULT 'pending',
                payment_method VARCHAR(64) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )");
            $db->query("CREATE TABLE IF NOT EXISTS shop_order_items (
                id INTEGER PRIMARY KEY AUTO_INCREMENT,
                order_id INT,
                sku VARCHAR(190),
                name VARCHAR(255),
                quantity INT DEFAULT 1,
                price_cents INT DEFAULT 0,
                currency VARCHAR(8) DEFAULT 'EUR',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX(order_id)
            )");
            // Optional: E-Mail-Template "Bestellbestätigung" anlegen, falls E-Mail-Templatesystem aktiv ist
            try {
                // Prüfen, ob die Tabelle existiert, indem wir einfach eine COUNT-Abfrage versuchen
                $checkStmt = $db->prepare("SELECT COUNT(*) AS cnt FROM email_templates WHERE template_name = ?");
                $db->execute($checkStmt, ['Bestellbestätigung']);
                $row = $db->fetch($checkStmt);
                $exists = isset($row['cnt']) ? intval($row['cnt']) > 0 : false;
                if (!$exists) {
                    $subject = 'Ihre Bestellung {order_number} bei {site_name}';
                    $html = <<<HTML
<html><head><title>Bestellbestätigung</title></head><body style="font-family: Arial, sans-serif;">
    <h2>Vielen Dank für Ihre Bestellung!</h2>
    <p>Bestellnummer: <strong>{order_number}</strong></p>
    <h3>Bestellübersicht</h3>
    {order_items}
    <p><strong>Summe:</strong> {order_total}</p>
    <p>Mit freundlichen Grüßen<br>{site_name}</p>
</body></html>
HTML;
                    $raw = "Bestellbestätigung\n\nBestellnummer: {order_number}\nSumme: {order_total}\n\nDetails:\n{order_items}";
                    $variables = 'order_number,order_total,order_items,site_name';
                    $ins = $db->prepare("INSERT INTO email_templates (template_name, subject, html_content, raw_content, variables) VALUES (?, ?, ?, ?, ?)");
                    $db->execute($ins, ['Bestellbestätigung', $subject, $html, $raw, $variables]);
                }

                // Bestellung bestätigt
                $db->execute($checkStmt, ['Bestellung bestätigt']);
                $row = $db->fetch($checkStmt);
                $existsConfirmed = isset($row['cnt']) ? intval($row['cnt']) > 0 : false;
                if (!$existsConfirmed) {
                    $subject = 'Ihre Bestellung {order_number} wurde bestätigt - {site_name}';
                    $html = <<<HTML
<html><head><title>Bestellung bestätigt</title></head><body style="font-family: Arial, sans-serif;">
    <h2>Ihre Bestellung wurde bestätigt</h2>
    <p>Bestellnummer: <strong>{order_number}</strong></p>
    <p>Wir beginnen nun mit der weiteren Bearbeitung.</p>
    <p>Vielen Dank für Ihr Vertrauen.<br>{site_name}</p>
</body></html>
HTML;
                    $raw = "Ihre Bestellung {order_number} wurde bestätigt.";
                    $variables = 'order_number,site_name';
                    $ins = $db->prepare("INSERT INTO email_templates (template_name, subject, html_content, raw_content, variables) VALUES (?, ?, ?, ?, ?)");
                    $db->execute($ins, ['Bestellung bestätigt', $subject, $html, $raw, $variables]);
                }

                // Bestellung abgelehnt
                $db->execute($checkStmt, ['Bestellung abgelehnt']);
                $row = $db->fetch($checkStmt);
                $existsRejected = isset($row['cnt']) ? intval($row['cnt']) > 0 : false;
                if (!$existsRejected) {
                    $subject = 'Ihre Bestellung {order_number} wurde abgelehnt - {site_name}';
                    $html = <<<HTML
<html><head><title>Bestellung abgelehnt</title></head><body style="font-family: Arial, sans-serif;">
    <h2>Ihre Bestellung wurde abgelehnt</h2>
    <p>Bestellnummer: <strong>{order_number}</strong></p>
    <p>Leider konnten wir Ihre Bestellung nicht annehmen. Für Rückfragen steht Ihnen unser Support gerne zur Verfügung.</p>
    <p>Ihr {site_name} Team</p>
</body></html>
HTML;
                    $raw = "Ihre Bestellung {order_number} wurde abgelehnt.";
                    $variables = 'order_number,site_name';
                    $ins = $db->prepare("INSERT INTO email_templates (template_name, subject, html_content, raw_content, variables) VALUES (?, ?, ?, ?, ?)");
                    $db->execute($ins, ['Bestellung abgelehnt', $subject, $html, $raw, $variables]);
                }
            } catch (Exception $e) {
                // E-Mail-Templatesystem nicht vorhanden → ignorieren
            }
            // Frontend-Datei erstellen (nur public/shop.php wird während der Installation angelegt)
            $this->createFrontendShopPage();

            // sys.conf.php um $shop_settings erweitern (Defaults inkl. Maintenance/Categories/Addons)
            $this->ensureShopSettingsInSysConf(true);

            // Hinweis: Backend-Dateien (Addons, Ordering) gehören zum Modul und werden nicht im Installer erzeugt

            return [ 'success' => true, 'message' => 'Shop erfolgreich installiert (DB + Frontend + Einstellungen)' ];
        } catch (Exception $e) {
            return [ 'success' => false, 'error' => 'Installation fehlgeschlagen: ' . $e->getMessage() ];
        }
    }

    private function getSysConfPath() {
        return realpath(__DIR__ . '/../../sys.conf.php');
    }

    private function readShopSettingsFromSysConf() {
        // Wenn bereits geladen, globale Variable verwenden
        if (isset($GLOBALS['shop_settings']) && is_array($GLOBALS['shop_settings'])) {
            return $GLOBALS['shop_settings'];
        }
        $sysConf = $this->getSysConfPath();
        if (!$sysConf || !is_file($sysConf)) return [];
        // Einmalig laden, um Redeclarations zu vermeiden
        try { include_once $sysConf; } catch (Exception $e) {}
        if (isset($GLOBALS['shop_settings']) && is_array($GLOBALS['shop_settings'])) {
            return $GLOBALS['shop_settings'];
        }
        return [];
    }

    private function writeShopSettingsToSysConf(array $settings) {
        $sysConf = $this->getSysConfPath();
        if (!$sysConf || !is_writable($sysConf)) return false;
        $contents = file_get_contents($sysConf);
        $block = "$" . "shop_settings = " . var_export($settings, true) . ";\n";
        if (strpos($contents, '$shop_settings') !== false) {
            $contents = preg_replace('/\$shop_settings\s*=\s*[^;]*;\s*/s', $block, $contents, 1);
        } else {
            // Vor schließendem PHP-Tag einfügen, falls vorhanden
            if (strpos($contents, '?>') !== false) {
                $contents = str_replace('?>', "\n\n" . $block . "?>", $contents);
            } else {
                $contents .= "\n\n" . $block;
            }
        }
        @copy($sysConf, $sysConf . '.bnk');
        file_put_contents($sysConf, $contents);
        $verify = file_get_contents($sysConf);
        if (strpos($verify, '$shop_settings') !== false) {
            @unlink($sysConf . '.bnk');
            return true;
        }
        return false;
    }

    private function ensureShopSettingsInSysConf($forceDefaults = false) {
        $existing = $this->readShopSettingsFromSysConf();
        $defaults = [
            'terms' => 'AGBs',
            'privacy' => 'Datenschutz',
            'imprint' => 'Impressum',
            'faq' => 'FAQ',
            'maintenance' => 0,
            'categories' => [],
            'addons' => [ 'paypal' => [ 'enabled' => 0 ] ],
        ];
        $settings = $forceDefaults ? array_merge($defaults, $existing) : ($existing + $defaults);
        $this->writeShopSettingsToSysConf($settings);
    }

    private function createFrontendShopPage() {
        $content = <<<'PHP'
<?php
session_start();
require_once "../src/sys.conf.php";
require_once "../framework.php";
require_once "../src/core/LanguageManager.php";

$lang = LanguageManager::getInstance();
$currentLang = $lang->getCurrentLanguage();

// Maintenance prüfen über sys.conf.php
$isMaintenance = false;
if (isset($shop_settings) && is_array($shop_settings) && !empty($shop_settings['maintenance'])) {
    $isMaintenance = (bool)$shop_settings['maintenance'];
}

if ($isMaintenance) {
    http_response_code(503);
}

// Backend-Mechanik aus dem Modul einbinden (cart/order) bei Bedarf
if (isset($_GET['include'])) {
    $inc = $_GET['include'];
    $action = $_REQUEST['action'] ?? null;
    // Pfad zum Modul-Backend
    $orderingBase = realpath(__DIR__ . '/../src/module/shop/ordering');
    if ($orderingBase && is_dir($orderingBase)) {
        if ($inc === 'cart') {
            $file = $orderingBase . DIRECTORY_SEPARATOR . 'cart.php';
        } elseif ($inc === 'order') {
            $file = $orderingBase . DIRECTORY_SEPARATOR . 'order.php';
        } else {
            $file = null;
        }
        if ($file && is_file($file)) {
            // cart.php und order.php senden selbst Content-Type und JSON
            require $file;
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop - <?= Config::FRONTPANEL_SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="assets/frontpanel.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <script>
        window.isLoggedIn = <?php echo (!empty($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true) ? 'true' : 'false'; ?>;
        // Serverseitigen Loginstatus zur Sicherheit verifizieren
        (async function(){
            try{
                const res = await fetch('shop.php?include=order&action=auth',{headers:{'Accept':'application/json'}});
                const j = await res.json();
                if(j && j.success && j.data){ window.isLoggedIn = !!j.data.logged_in; }
            }catch(e){ /* ignore */ }
        })();
    </script>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-server"></i> <?= Config::FRONTPANEL_SITE_NAME ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php#server-status"><?= t("server_status") ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#support"><?= t("support") ?></a></li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="login.php"><i class="bi bi-person-circle"></i> <?= t("customer_login") ?></a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <?php if (!empty($isMaintenance)) : ?>
            <div class="alert alert-warning d-flex align-items-center" role="alert">
                <i class="bi bi-tools me-2"></i>
                <div>Der Shop befindet sich aktuell im Wartungsmodus.</div>
            </div>
        <?php else: ?>
            <h1 class="mb-4">Shop</h1>
            <nav class="navbar navbar-expand-lg navbar-dark" style="background:#0d6efd;border-radius:0.5rem;">
                <div class="container">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item"><a class="nav-link js-open-cart" href="#">Warenkorb</a></li>
                        <li class="nav-item"><a class="nav-link js-open-checkout" href="#">Kasse</a></li>
                    </ul>
                </div>
            </nav>
            <?php
            try {
                $db = DatabaseManager::getInstance()->getDriver();
                $stmt = $db->query("SELECT sku, name, description, price_cents, currency, status FROM shop_products WHERE status = 'active' ORDER BY updated_at DESC");
                $rows = $db->fetchAll($stmt);
            } catch (Exception $e) { $rows = []; }
            ?>
            <div class="row g-3">
                <?php foreach ($rows as $p): $price = number_format(($p["price_cents"] ?? 0)/100, 2, ',', '.'); ?>
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($p["name"]) ?></h5>
                            <p class="card-text small text-muted"><?= htmlspecialchars($p["description"]) ?></p>
                        </div>
                        <div class="card-footer d-flex justify-content-between align-items-center">
                            <span class="fw-bold"><?= $price ?> <?= htmlspecialchars($p["currency"] ?? 'EUR') ?></span>
                            <button class="btn btn-sm btn-primary js-add-to-cart" data-sku="<?= htmlspecialchars($p['sku']) ?>">
                                <i class="bi bi-cart-plus"></i> In den Warenkorb
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-4">
                <button class="btn btn-success" id="btn-checkout">
                    <i class="bi bi-bag-check"></i> Zur Kasse
                </button>
            </div>
        <?php endif; ?>
    </div>

    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container text-center">
            <p>&copy; <?= date('Y') ?> <?= Config::FRONTPANEL_SITE_NAME ?>.</p>
        </div>
    </footer>

    <!-- Cart/Checkout Modal -->
    <div class="modal fade" id="cartModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-cart"></i> Warenkorb & Kasse</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="cart-summary" class="mb-3">
                        <div class="text-muted">Warenkorb wird geladen…</div>
                    </div>
                    <hr>
                    <!-- Auth-Schritt -->
                    <div id="checkout-auth" class="mb-3" style="display:none;">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="card h-100 border-primary">
                                    <div class="card-body">
                                        <h5 class="card-title"><i class="bi bi-person-check"></i> Ich bin bereits Kunde</h5>
                                        <p class="card-text text-muted">Bitte melde dich an, um fortzufahren.</p>
                                        <a href="login.php?redirect=shop.php" class="btn btn-primary w-100"><i class="bi bi-box-arrow-in-right"></i> Anmelden</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card h-100 border-success">
                                    <div class="card-body">
                                        <h5 class="card-title"><i class="bi bi-person-plus"></i> Ich bin neu hier</h5>
                                        <p class="card-text text-muted">Erstelle ein Konto, um zu bestellen.</p>
                                        <a href="register.php?redirect=shop.php" class="btn btn-success w-100"><i class="bi bi-person-plus"></i> Registrieren</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form id="checkout-form" class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">E-Mail für Bestellbestätigung</label>
                            <input type="email" class="form-control" name="email" placeholder="kunde@example.com" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Zahlmethode</label>
                            <select name="method" class="form-select">
                                <option value="paypal">PayPal</option>
                            </select>
                        </div>
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-credit-card"></i> Bestellung anlegen</button>
                            <button type="button" id="btn-clear-cart" class="btn btn-outline-danger"><i class="bi bi-trash"></i> Warenkorb leeren</button>
                        </div>
                        <div class="col-12">
                            <div id="checkout-status" class="mt-2"></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function(){
        async function api(include, params){
            const qs = new URLSearchParams(params || {});
            const url = `shop.php?include=${include}&` + qs.toString();
            try{
                const res = await fetch(url, { method: 'GET', headers: { 'Accept': 'application/json' } });
                const text = await res.text();
                try {
                    const json = JSON.parse(text);
                    return json;
                } catch(parseErr){
                    console.error('Antwort ist kein gültiges JSON:', text);
                    return { success:false, error:'Ungültige Server-Antwort', _raw:text };
                }
            }catch(e){
                console.error('Netzwerkfehler:', e);
                return { success:false, error:'Netzwerkfehler', _exception: String(e) };
            }
        }

        async function loadSummary(){
            const box = document.getElementById('cart-summary');
            box.innerHTML = '<div class="text-muted">Lade Warenkorb…</div>';
            try{
                const r = await api('order', { action: 'summary' });
                if(!r.success){ box.innerHTML = '<div class="alert alert-danger">'+(r.error||'Fehler beim Laden')+'</div>'; return; }
                const items = r.data.items || [];
                if(items.length === 0){ box.innerHTML = '<div class="alert alert-info">Ihr Warenkorb ist leer.</div>'; return; }
                const rows = items.map(it => `
                    <tr>
                        <td>${it.sku}</td>
                        <td>${it.name}</td>
                        <td class="text-end">${(it.price_cents/100).toLocaleString('de-DE', {minimumFractionDigits:2})} ${it.currency}</td>
                        <td class="text-end">
                            <div class="input-group input-group-sm justify-content-end" style="max-width:140px; float:right;">
                                <button class="btn btn-outline-secondary js-qty" data-sku="${it.sku}" data-delta="-1">-</button>
                                <input type="number" min="0" class="form-control text-end js-qty-input" data-sku="${it.sku}" value="${it.quantity}">
                                <button class="btn btn-outline-secondary js-qty" data-sku="${it.sku}" data-delta="1">+</button>
                            </div>
                        </td>
                    </tr>`).join('');
                const total = (r.data.total_cents/100).toLocaleString('de-DE', {minimumFractionDigits:2});
                box.innerHTML = `
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th>SKU</th><th>Produkt</th><th class="text-end">Preis</th><th class="text-end">Menge</th></tr></thead>
                            <tbody>${rows}</tbody>
                            <tfoot><tr><th colspan="2"></th><th class="text-end">Summe</th><th class="text-end">${total} ${r.data.currency}</th></tr></tfoot>
                        </table>
                    </div>`;
            }catch(e){ box.innerHTML = '<div class="text-danger">Netzwerkfehler</div>'; }
        }

        document.addEventListener('click', async function(e){
            const btn = e.target.closest('.js-add-to-cart');
            if(btn){
                e.preventDefault();
                const sku = btn.getAttribute('data-sku');
                try{
                    const r = await api('cart', { action: 'add', sku });
                    if(r && r.success){
                        btn.classList.remove('btn-primary');
                        btn.classList.add('btn-success');
                        btn.innerHTML = '<i class="bi bi-check"></i> Hinzugefügt';
                        setTimeout(()=>{ btn.classList.add('btn-primary'); btn.classList.remove('btn-success'); btn.innerHTML = '<i class="bi bi-cart-plus"></i> In den Warenkorb'; }, 1500);
                    } else {
                        alert('Fehler beim Hinzufügen zum Warenkorb');
                    }
                }catch(err){ console.error(err); }
            }
        });

        async function openCheckout(){
            await loadSummary();
            const modal = new bootstrap.Modal(document.getElementById('cartModal'));
            modal.show();
            // Auth-Choice anzeigen, wenn nicht eingeloggt
            const authBox = document.getElementById('checkout-auth');
            const form = document.getElementById('checkout-form');
            if (!window.isLoggedIn) {
                if (authBox) authBox.style.display = '';
                if (form) form.style.display = 'none';
            } else {
                if (authBox) authBox.style.display = 'none';
                if (form) form.style.display = '';
            }
        }
        document.getElementById('btn-checkout')?.addEventListener('click', openCheckout);
        document.querySelector('.js-open-checkout')?.addEventListener('click', function(e){ e.preventDefault(); openCheckout(); });
        document.querySelector('.js-open-cart')?.addEventListener('click', async function(e){ e.preventDefault(); await loadSummary(); openCheckout(); });

        document.getElementById('btn-clear-cart')?.addEventListener('click', async function(){
            await api('cart', { action: 'clear' });
            await loadSummary();
        });

        document.getElementById('checkout-form')?.addEventListener('submit', async function(e){
            e.preventDefault();
            const form = e.target;
            const email = form.querySelector('[name="email"]').value;
            const method = form.querySelector('[name="method"]').value;
            const status = document.getElementById('checkout-status');
            status.innerHTML = '';
            try{
                const created = await fetch('shop.php?include=order&action=create', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ email }) });
                const cr = await created.json();
                if(!cr.success){ status.innerHTML = '<div class="alert alert-danger">'+(cr.error||'Fehler bei Bestellung')+'</div>'; return; }
                const sel = await fetch('shop.php?include=order&action=select_payment', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ method }) });
                const sr = await sel.json();
                if(!sr.success){ status.innerHTML = '<div class="alert alert-danger">'+(sr.error||'Zahlmethode nicht verfügbar')+'</div>'; return; }
                const conf = await fetch('shop.php?include=order&action=confirm', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ order_number: cr.data.order_number }) });
                const cf = await conf.json();
                if(cf.success){
                    status.innerHTML = '<div class="alert alert-success">Bestellung abgeschlossen. Vielen Dank!</div>';
                    await loadSummary();
                } else {
                    status.innerHTML = '<div class="alert alert-danger">'+(cf.error||'Fehler bei Bestätigung')+'</div>';
                }
            }catch(err){ status.innerHTML = '<div class="alert alert-danger">Netzwerkfehler</div>'; }
        });
        // Mengen-Änderung (+- Buttons / Eingabe)
        document.addEventListener('click', async function(e){
            const btn = e.target.closest('.js-qty');
            if(!btn) return;
            const sku = btn.getAttribute('data-sku');
            const delta = parseInt(btn.getAttribute('data-delta')||'0',10);
            const input = document.querySelector(`.js-qty-input[data-sku="${sku}"]`);
            let val = parseInt(input.value||'0',10) + delta;
            if (val < 0) val = 0;
            await api('cart', { action: 'set', sku, qty: val });
            await loadSummary();
        });
        document.addEventListener('change', async function(e){
            const input = e.target.closest('.js-qty-input');
            if(!input) return;
            const sku = input.getAttribute('data-sku');
            let val = parseInt(input.value||'0',10);
            if (isNaN(val) || val < 0) val = 0;
            await api('cart', { action: 'set', sku, qty: val });
            await loadSummary();
        });
    })();
    </script>
</body>
</html>
PHP;

        $filePath = __DIR__ . '/../../../public/shop.php';
        $dirPath = dirname($filePath);
        if (!is_dir($dirPath)) {
            @mkdir($dirPath, 0775, true);
        }
        if (file_exists($filePath)) {
            @copy($filePath, $filePath . '.bnk');
        }
        file_put_contents($filePath, $content);
    }

    private function listProducts() {
        $db = DatabaseManager::getInstance()->getDriver();
        $stmt = $db->query("SELECT id, sku, name, price_cents, currency, status, access_scope, category_id, category_slug, subcategory, updated_at FROM shop_products ORDER BY updated_at DESC");
        $rows = $db->fetchAll($stmt);
        return [ 'success' => true, 'data' => $rows ];
    }

    private function getProduct($data) {
        $errors = $this->validate($data, [ 'sku' => 'required' ]);
        if (!empty($errors)) {
            return [ 'success' => false, 'error' => 'Validation failed', 'data' => $errors ];
        }
        $db = DatabaseManager::getInstance()->getDriver();
        $stmt = $db->prepare("SELECT id, sku, name, description, price_cents, currency, status, access_scope, category_slug, subcategory FROM shop_products WHERE sku = ? LIMIT 1");
        $db->execute($stmt, [ $data['sku'] ]);
        $row = $db->fetch($stmt);
        if (!$row) {
            return [ 'success' => false, 'error' => 'Produkt nicht gefunden' ];
        }
        return [ 'success' => true, 'data' => $row ];
    }

    private function saveProduct($data) {
        $errors = $this->validate($data, [
            'sku' => 'required|min:1',
            'name' => 'required|min:1',
        ]);
        if (!empty($errors)) {
            return [ 'success' => false, 'error' => 'Validation failed', 'data' => $errors ];
        }

        // Preis aus formatiertem String in cents + currency normalisieren
        $parsed = $this->parsePrice($data['price'] ?? ($data['price_cents'] ?? ''));
        if (!$parsed['ok']) {
            return [ 'success' => false, 'error' => 'Ungültiges Preisformat' ];
        }

        $price_cents = $parsed['cents'];
        $currency = strtoupper($data['currency'] ?? $parsed['currency']);

        $access_scope = isset($data['access_scope']) ? strtolower($data['access_scope']) : 'internal';
        $allowedScopes = ['internal', 'proxmox', 'ispconfig', 'ovh', 'ogp'];
        if (!in_array($access_scope, $allowedScopes, true)) {
            $access_scope = 'internal';
        }

        $category_id = isset($data['category_id']) && is_numeric($data['category_id']) ? intval($data['category_id']) : null;
        $category_slug = isset($data['category_slug']) ? trim($data['category_slug']) : null;
        $subcategory = isset($data['subcategory']) ? trim($data['subcategory']) : null;

        $db = DatabaseManager::getInstance()->getDriver();
        $existsStmt = $db->prepare("SELECT id FROM shop_products WHERE sku = ?");
        $db->execute($existsStmt, [ $data['sku'] ]);
        $exists = $db->fetch($existsStmt);
        if ($exists) {
            $stmt = $db->prepare("UPDATE shop_products SET name = ?, description = ?, price_cents = ?, currency = ?, status = ?, access_scope = ?, category_id = ?, category_slug = ?, subcategory = ?, updated_at = CURRENT_TIMESTAMP WHERE sku = ?");
            $db->execute($stmt, [ $data['name'], $data['description'] ?? '', $price_cents, $currency ?: 'EUR', $data['status'] ?? 'active', $access_scope, $category_id, $category_slug, $subcategory, $data['sku'] ]);
        } else {
            $stmt = $db->prepare("INSERT INTO shop_products (sku, name, description, price_cents, currency, status, access_scope, category_id, category_slug, subcategory) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $db->execute($stmt, [ $data['sku'], $data['name'], $data['description'] ?? '', $price_cents, $currency ?: 'EUR', $data['status'] ?? 'active', $access_scope, $category_id, $category_slug, $subcategory ]);
        }
        return [ 'success' => true ];
    }

    private function deleteProduct($data) {
        $errors = $this->validate($data, [ 'sku' => 'required' ]);
        if (!empty($errors)) {
            return [ 'success' => false, 'error' => 'Validation failed', 'data' => $errors ];
        }
        $db = DatabaseManager::getInstance()->getDriver();
        $stmt = $db->prepare("DELETE FROM shop_products WHERE sku = ?");
        $db->execute($stmt, [ $data['sku'] ]);
        return [ 'success' => true ];
    }

    // Kategorien
    private function listCategories() {
        $settings = $this->readShopSettingsFromSysConf();
        $cats = $settings['categories'] ?? [];
        $rows = [];
        foreach ($cats as $slug => $names) {
            if (is_array($names)) {
                $rows[] = [ 'slug' => $slug, 'names' => array_values($names) ];
            } else {
                $rows[] = [ 'slug' => $slug, 'names' => [ (string)$names ] ];
            }
        }
        return [ 'success' => true, 'data' => $rows ];
    }

    private function getCategory($data) {
        $errors = $this->validate($data, [ 'slug' => 'required' ]);
        if (!empty($errors)) {
            return [ 'success' => false, 'error' => 'Validation failed', 'data' => $errors ];
        }
        $settings = $this->readShopSettingsFromSysConf();
        $cats = $settings['categories'] ?? [];
        if (!isset($cats[$data['slug']])) return [ 'success' => false, 'error' => 'Kategorie nicht gefunden' ];
        $names = is_array($cats[$data['slug']]) ? array_values($cats[$data['slug']]) : [ (string)$cats[$data['slug']] ];
        return [ 'success' => true, 'data' => [ 'slug' => $data['slug'], 'names' => $names ] ];
    }

    private function saveCategory($data) {
        $errors = $this->validate($data, [ 'slug' => 'required|min:1', 'name' => 'required|min:1' ]);
        if (!empty($errors)) {
            return [ 'success' => false, 'error' => 'Validation failed', 'data' => $errors ];
        }
        $settings = $this->readShopSettingsFromSysConf();
        if (!isset($settings['categories']) || !is_array($settings['categories'])) $settings['categories'] = [];
        if (!isset($settings['categories'][$data['slug']]) || !is_array($settings['categories'][$data['slug']])) {
            $settings['categories'][$data['slug']] = [];
        }
        // Füge Subkategorie hinzu, wenn noch nicht enthalten
        if (!in_array($data['name'], $settings['categories'][$data['slug']], true)) {
            $settings['categories'][$data['slug']][] = $data['name'];
        }
        $ok = $this->writeShopSettingsToSysConf($settings);
        return [ 'success' => $ok ];
    }

    private function deleteCategory($data) {
        $errors = $this->validate($data, [ 'slug' => 'required' ]);
        if (!empty($errors)) {
            return [ 'success' => false, 'error' => 'Validation failed', 'data' => $errors ];
        }
        $settings = $this->readShopSettingsFromSysConf();
        if (!isset($settings['categories'][$data['slug']])) {
            return [ 'success' => false, 'error' => 'Kategorie nicht gefunden' ];
        }
        // Wenn name angegeben: nur diese Subkategorie löschen, sonst gesamte Kategorie
        if (!empty($data['name'])) {
            $names = is_array($settings['categories'][$data['slug']]) ? $settings['categories'][$data['slug']] : [ (string)$settings['categories'][$data['slug']] ];
            $names = array_values(array_filter($names, function($n) use ($data){ return $n !== $data['name']; }));
            if (empty($names)) {
                unset($settings['categories'][$data['slug']]);
            } else {
                $settings['categories'][$data['slug']] = $names;
            }
        } else {
            unset($settings['categories'][$data['slug']]);
        }
        $ok = $this->writeShopSettingsToSysConf($settings);
        return [ 'success' => $ok ];
    }

    // --- Orders Admin ---
    private function listOrders() {
        $db = DatabaseManager::getInstance()->getDriver();
        $stmt = $db->query("SELECT id, order_number, customer_email, total_cents, currency, status, created_at FROM shop_orders ORDER BY created_at DESC");
        $rows = $db->fetchAll($stmt);
        return [ 'success' => true, 'data' => $rows ];
    }

    private function getOrder($data) {
        $errors = $this->validate($data, [ 'order_id' => 'required|numeric' ]);
        if (!empty($errors)) {
            return [ 'success' => false, 'error' => 'Validation failed', 'data' => $errors ];
        }
        $db = DatabaseManager::getInstance()->getDriver();
        // Header
        $stmt = $db->prepare("SELECT id, order_number, customer_email, total_cents, currency, status, created_at FROM shop_orders WHERE id = ? LIMIT 1");
        $db->execute($stmt, [ intval($data['order_id']) ]);
        $order = $db->fetch($stmt);
        if (!$order) return [ 'success' => false, 'error' => 'Bestellung nicht gefunden' ];
        // Items
        $stmt = $db->prepare("SELECT sku, name, quantity, price_cents, currency FROM shop_order_items WHERE order_id = ? ORDER BY id ASC");
        $db->execute($stmt, [ intval($data['order_id']) ]);
        $items = $db->fetchAll($stmt);
        $order['items'] = $items ?: [];
        return [ 'success' => true, 'data' => $order ];
    }

    private function updateOrderStatus($data) {
        $errors = $this->validate($data, [ 'order_id' => 'required|numeric', 'status' => 'required' ]);
        if (!empty($errors)) {
            return [ 'success' => false, 'error' => 'Validation failed', 'data' => $errors ];
        }
        $allowed = ['paid','rejected','pending'];
        $status = strtolower(trim($data['status']));
        if (!in_array($status, $allowed, true)) {
            return [ 'success' => false, 'error' => 'Ungültiger Status' ];
        }
        $db = DatabaseManager::getInstance()->getDriver();
        // E-Mail des Kunden ermitteln
        $stmt = $db->prepare("SELECT order_number, customer_email FROM shop_orders WHERE id = ? LIMIT 1");
        $db->execute($stmt, [ intval($data['order_id']) ]);
        $o = $db->fetch($stmt);
        if (!$o) return [ 'success' => false, 'error' => 'Bestellung nicht gefunden' ];

        // Update
        $upd = $db->prepare("UPDATE shop_orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $db->execute($upd, [ $status, intval($data['order_id']) ]);

        // E-Mail versenden, falls Templatesystem verfügbar
        $email = $o['customer_email'] ?? '';
        if (function_exists('sendEmailFromTemplate') && function_exists('isEmailTemplateSystemAvailable') && isEmailTemplateSystemAvailable() && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $vars = [ 'order_number' => $o['order_number'] ];
            if ($status === 'paid') {
                @sendEmailFromTemplate('Bestellung bestätigt', $email, $vars);
            } elseif ($status === 'rejected') {
                @sendEmailFromTemplate('Bestellung abgelehnt', $email, $vars);
            }
        }

        return [ 'success' => true ];
    }

    private function deleteOrder($data) {
        $errors = $this->validate($data, [ 'order_id' => 'required|numeric' ]);
        if (!empty($errors)) {
            return [ 'success' => false, 'error' => 'Validation failed', 'data' => $errors ];
        }
        $db = DatabaseManager::getInstance()->getDriver();
        $id = intval($data['order_id']);
        $db->execute($db->prepare("DELETE FROM shop_order_items WHERE order_id = ?"), [ $id ]);
        $db->execute($db->prepare("DELETE FROM shop_orders WHERE id = ?"), [ $id ]);
        return [ 'success' => true ];
    }

    // Preis-Parser: akzeptiert z.B. "1,50€", "1.99", "2 €", "3$"
    private function parsePrice($input) {
        if ($input === null) return ['ok' => false];
        $str = trim((string)$input);
        if ($str === '') return ['ok' => false];
        // Währung erkennen (EUR, USD, €, $)
        $currency = 'EUR';
        if (preg_match('/(€|eur)/i', $str)) $currency = 'EUR';
        if (preg_match('/(\$|usd)/i', $str)) $currency = 'USD';
        // Nicht-Ziffern außer Trennzeichen entfernen
        $clean = preg_replace('/[^0-9,\.]/', '', $str);
        // Deutsches Format: Komma als Dezimaltrenner bevorzugen
        if (strpos($clean, ',') !== false && strpos($clean, '.') !== false) {
            // Entferne Tausenderpunkte, behalte Komma als Dezimaltrenner
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } elseif (strpos($clean, ',') !== false) {
            $clean = str_replace(',', '.', $clean);
        }
        if ($clean === '' || !is_numeric($clean)) return ['ok' => false];
        $float = (float)$clean;
        $cents = (int)round($float * 100);
        return ['ok' => true, 'cents' => $cents, 'currency' => $currency];
    }

    private function ensureAddonsStructure() {
        $base = __DIR__ . '/addons';
        if (!is_dir($base)) @mkdir($base, 0775, true);
        $paypal = $base . '/paypal';
        if (!is_dir($paypal)) @mkdir($paypal, 0775, true);
        $addonFile = $paypal . '/Addon.php';
        if (!file_exists($addonFile)) {
            $code = <<<'PHP'
<?php
class PayPalAddon {
    public static function getKey() { return 'paypal'; }
    public static function getName() { return 'PayPal'; }
    public static function getDescription() { return 'Bezahlmethode PayPal (Beispiel-Addon)'; }
    public static function isEnabled($settings) {
        return !empty($settings['addons']['paypal']['enabled']);
    }
}
PHP;
            file_put_contents($addonFile, $code);
        }
    }

    private function listAddons() {
        $addons = [];
        $settings = $this->readShopSettingsFromSysConf();
        $addonsDir = __DIR__ . '/addons';
        if (is_dir($addonsDir)) {
            foreach (glob($addonsDir . '/*/Addon.php') as $file) {
                $before = get_declared_classes();
                try { require_once $file; } catch (\Throwable $t) {}
                $after = get_declared_classes();
                $newClasses = array_diff($after, $before);
                foreach ($newClasses as $cls) {
                    if (method_exists($cls, 'getKey')) {
                        $key = call_user_func([$cls, 'getKey']);
                        $name = method_exists($cls, 'getName') ? call_user_func([$cls, 'getName']) : $key;
                        $desc = method_exists($cls, 'getDescription') ? call_user_func([$cls, 'getDescription']) : '';
                        $enabled = !empty($settings['addons'][$key]['enabled']);
                        $addons[] = [ 'key' => $key, 'name' => $name, 'description' => $desc, 'enabled' => $enabled ];
                    }
                }
            }
        }
        return [ 'success' => true, 'data' => $addons ];
    }

    private function toggleAddon($data) {
        $errors = $this->validate($data, [ 'key' => 'required' ]);
        if (!empty($errors)) {
            return [ 'success' => false, 'error' => 'Validation failed', 'data' => $errors ];
        }
        $enabled = !empty($data['enabled']) && ($data['enabled'] === '1' || $data['enabled'] === 1 || $data['enabled'] === true || $data['enabled'] === 'true');
        $settings = $this->readShopSettingsFromSysConf();
        if (!isset($settings['addons']) || !is_array($settings['addons'])) $settings['addons'] = [];
        if (!isset($settings['addons'][$data['key']])) $settings['addons'][$data['key']] = [];
        $settings['addons'][$data['key']]['enabled'] = $enabled ? 1 : 0;
        $ok = $this->writeShopSettingsToSysConf($settings);
        return [ 'success' => $ok ];
    }

    private function getSettings() {
        $settings = $this->readShopSettingsFromSysConf();
        return [ 'success' => true, 'data' => [ 'settings' => $settings, 'maintenance' => !empty($settings['maintenance']) ] ];
    }

    private function saveSettings($data) {
        $settings = $this->readShopSettingsFromSysConf();
        $settings['terms'] = $data['terms'] ?? ($settings['terms'] ?? 'AGBs');
        $settings['privacy'] = $data['privacy'] ?? ($settings['privacy'] ?? 'Datenschutz');
        $settings['imprint'] = $data['imprint'] ?? ($settings['imprint'] ?? 'Impressum');
        $settings['faq'] = $data['faq'] ?? ($settings['faq'] ?? 'FAQ');
        $ok = $this->writeShopSettingsToSysConf($settings);
        return [ 'success' => $ok ];
    }

    private function toggleMaintenance($data) {
        $enabled = !empty($data['enabled']) && ($data['enabled'] === '1' || $data['enabled'] === 1 || $data['enabled'] === true);
        $settings = $this->readShopSettingsFromSysConf();
        $settings['maintenance'] = $enabled ? 1 : 0;
        $ok = $this->writeShopSettingsToSysConf($settings);
        return [ 'success' => $ok ];
    }
}
