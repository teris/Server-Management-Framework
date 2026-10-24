<?php
declare(strict_types=1);
// Keep headers mutable until record-level access checks in the views finish.
ob_start();
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/actions.php';
$path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/', '/') ?: '/';
$aliases = ['/index.php'=>'/','/public/'=>'/','/public/index.php'=>'/','/public/login.php'=>'/login','/public/register.php'=>'/registrieren','/public/dashboard.php'=>'/konto','/public/support.php'=>'/konto/support','/public/profile.php'=>'/konto/einstellungen'];
if (isset($aliases[$path])) redirect($aliases[$path]);
$error = '';
try { db(); }
catch (Throwable $exception) {
    error_log('Orga shop connection unavailable: '.get_class($exception).' '.$exception->getCode());
    http_response_code(503);
    header('Retry-After: 300');
    require __DIR__ . '/views/unavailable.php';
    exit;
}
try {
    // Open the framework connection before rendering any customer data.
    db();
    if($path==='/api/infrastructure/runner'){shop_require_module('shop.provisioning');if(!SMF\Core\ShopSettings::allowLive())throw new SecurityViolation('Der Providerdienst ist im Testbetrieb gesperrt.',503);template_runner_endpoint();}
    if ($path === '/api/paypal/webhook') { shop_require_module('shop.payments');
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo '{"accepted":false}'; exit; }
        try {
            if (!integration_ready('paypal')) throw new RuntimeException('PayPal is not configured');
            $raw=file_get_contents('php://input',false,null,0,1048577);
            if(strlen($raw)>1048576)throw new RuntimeException('Event too large');
            $event=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
            if(!is_array($event))throw new RuntimeException('Invalid event');
            $headers=[];foreach($_SERVER as $key=>$value)if(str_starts_with($key,'HTTP_PAYPAL_'))$headers[str_replace('_','-',substr($key,5))]=$value;
            $paypal=new PaypalProvider();
            if(!$paypal->verifyWebhook($event,$headers)){http_response_code(400);echo '{"accepted":false}';exit;}
            process_paypal_event($event,$paypal);echo '{"accepted":true}';

            exit;
        } catch (Throwable $exception) { http_response_code(503);error_log('PayPal webhook could not be processed: '.get_class($exception));echo '{"accepted":false}';exit; }
    }
    if($path==='/api/domain-check'){shop_require_module('shop.catalog');
        header('Content-Type: application/json');if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);echo '{"status":"error"}';exit;}
        try{rate_limit('public_domain_lookup',60,60);$domain=valid_domain((string)($_GET['domain']??''));if(!$domain)throw new RuntimeException('Bitte eine Domain eingeben.');echo json_encode(domain_lookup_result($domain),JSON_THROW_ON_ERROR);}
        catch(RuntimeException $ex){http_response_code($ex instanceof ProviderFailure || $ex instanceof PDOException?503:422);echo json_encode(['status'=>'error','message'=>$ex instanceof ProviderFailure || $ex instanceof PDOException?'Die Domainprüfung ist gerade nicht erreichbar. Bitte erneut versuchen.':$ex->getMessage()]);}exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') handle_action((string)($_POST['action'] ?? ''));
} catch (RuntimeException $exception) {
    if($exception instanceof SecurityViolation)throw $exception;
    if ($exception instanceof PDOException) { error_log('Orga shop database failure: '.$exception->getCode()); $error='Die Anfrage konnte gerade nicht gespeichert werden. Bitte versuche es noch einmal.'; }
    else $error=$exception->getMessage();
} catch (Throwable $exception) {
    error_log('Orga shop failure: '.get_class($exception).' '.$exception->getCode());
    http_response_code(503);
    $error='Der Kundenbereich ist gerade nicht erreichbar. Bitte versuche es später erneut.';
}
$routes = ['/' => 'home', '/produkte' => 'products', '/konfigurieren' => 'configure', '/login' => 'login', '/registrieren' => 'register', '/hilfe' => 'help', '/konto' => 'dashboard', '/konto/produkte' => 'services', '/konto/anfragen' => 'requests', '/konto/support' => 'support', '/konto/ticket' => 'ticket', '/konto/einstellungen' => 'settings', '/admin/login' => 'admin_login', '/admin' => 'admin_dashboard', '/admin/produkte' => 'admin_products', '/admin/anfragen' => 'admin_requests', '/admin/kunden' => 'admin_customers', '/admin/support' => 'admin_support', '/admin/ticket' => 'admin_ticket', '/admin/einstellungen' => 'admin_settings'];
$routes += ['/module'=>'modules','/konto/module'=>'customer_modules','/konto/server'=>'servers'];
$commerceRoutes=['/admin/shop'=>'admin_shop','/admin/email'=>'admin_email','/admin/angebote'=>'admin_quotes','/admin/rechnungen'=>'admin_invoices','/admin/faq'=>'admin_faq','/konto/angebote'=>'quotes','/konto/rechnungen'=>'invoices','/konto/nachrichten'=>'notifications','/faq'=>'faq'];
$routes+=$commerceRoutes;
$routes['/admin/nachrichten']='admin_notifications';
$routes['/konto/vps-sicherungen']='vps_exports';$routes['/admin/vps-sicherungen']='admin_vps_exports';
$optionRoutes=['/admin/dokumente'=>'admin_documents','/dokumente'=>'documents'];$routes+=$optionRoutes;
$routes['/admin/gutscheine']='admin_coupons';$routes['/konto/gutscheine']='coupon_rewards';
$routes['/admin/domains']='admin_domains';$routes['/admin/module']='admin_module_catalog';
$routes['/konfigurator']='configurator';$routes['/admin/infrastruktur']='admin_infrastructure';$routes['/konto/zusatzmodul']='module_checkout';
$contractRoutes=['/kuendigen'=>'cancellation','/konto/vertraege'=>'contracts','/admin/vertraege'=>'admin_contracts','/paypal/rechnung'=>'invoice_return'];$routes+=$contractRoutes;
$page = $routes[$path] ?? '404';
$integrationRoutes=['/domain-pruefen'=>'domain_check','/bestellen'=>'checkout','/paypal/rueckkehr'=>'payment_return','/konto/verwalten'=>'manage_resource','/konto/bestellungen'=>'orders','/admin/schnittstellen'=>'admin_integrations','/admin/automatisierung'=>'admin_automation','/admin/ressourcen'=>'admin_resources','/admin/bestellungen'=>'admin_orders'];
if(isset($integrationRoutes[$path]))$page=$integrationRoutes[$path];
$registered=SMF\Core\Kernel::boot()->modules()->routes();
if(!isset($registered[$path]))$page='404';
if ($page === '404') {http_response_code(404);$path='/404';}
if (str_starts_with($path, '/konto')) require_user();
if (in_array($page,['checkout','payment_return','invoice_return'],true)) require_user();
if (str_starts_with($page, 'admin_') && $page !== 'admin_login') require_admin();
if (($page === 'login' || $page === 'register') && user()) redirect('/konto');
if ($page === 'admin_login' && admin()) redirect('/admin');
$portal = str_starts_with($path, '/konto') || (str_starts_with($path, '/admin') && $page !== 'admin_login');
$isAdmin = str_starts_with($page, 'admin_');
$titles = ['home'=>'Hosting, das zu dir passt','products'=>'Unsere Produkte','configure'=>'Dein Angebot','login'=>'Willkommen zurück','register'=>'Dein Kundenkonto','help'=>'Wir sind für dich da','dashboard'=>'Deine Übersicht','services'=>'Meine Produkte','requests'=>'Meine Anfragen','support'=>'Support','ticket'=>'Dein Support-Ticket','settings'=>'Kontoeinstellungen','admin_login'=>'Administration','admin_dashboard'=>'Shop-Übersicht','admin_products'=>'Produkte & Tarife','admin_requests'=>'Anfragen','admin_customers'=>'Kunden & Produkte','admin_support'=>'Support-Tickets','admin_ticket'=>'Support-Ticket','admin_settings'=>'Profil & Sicherheit','404'=>'Seite nicht gefunden'];
$titles+=['domain_check'=>'Wunschdomain prüfen','checkout'=>'Deine Bestellung','payment_return'=>'PayPal-Zahlung abgleichen','manage_resource'=>'Produkt verwalten','orders'=>'Bestellungen & Zahlungen','admin_integrations'=>'Schnittstellen','admin_automation'=>'Bereitstellung & Tarife','admin_resources'=>'Ressourcen zuordnen','admin_orders'=>'Bestellungen & Bereitstellung'];
$titles += ['modules'=>'Module & Direktzugänge','customer_modules'=>'Module & Direktzugänge','servers'=>'Meine virtuellen Server'];
$titles+=['admin_shop'=>'Shop & Rechnungen','admin_email'=>'E-Mail-Versand','admin_quotes'=>'Individuelle Angebote','admin_invoices'=>'Rechnungen','admin_faq'=>'FAQ verwalten','quotes'=>'Meine Angebote','invoices'=>'Meine Rechnungen','notifications'=>'Benachrichtigungen','faq'=>'FAQ & Anleitungen'];
$titles['admin_notifications']='Benachrichtigungen';$titles['vps_exports']='VPS-Sicherungen';$titles['admin_vps_exports']='VPS-Abschluss & Sicherungen';
$titles['admin_coupons']='Gutscheine & Rabatte';$titles['coupon_rewards']='Gutscheine & Zusatzpakete';$titles['admin_domains']='Domainendungen & Preise';$titles+=['cancellation'=>'Verträge hier kündigen','contracts'=>'Verträge & Kündigungen','admin_contracts'=>'Verträge & Kündigungen','invoice_return'=>'PayPal-Zahlung abgleichen'];
$titles['admin_module_catalog']='Module & Konfigurator';
$titles['configurator']='Dein Hosting-Konfigurator';$titles['admin_infrastructure']='Infrastruktur & Vorlagen';$titles['module_checkout']='Zusatzmodul bestellen';
$titles+=['admin_documents'=>'Dokumente & PDF','documents'=>'Vertragsunterlagen'];
$title = $titles[$page];
if($page==='vps_exports' && isset($_GET['download']))download_vps_export((int)$_GET['download'],(int)user()['id']);
if($page==='documents')public_document_download();
if($page==='services' && isset($_GET['id'])){$requested=customer_service_visible((int)$_GET['id'],(int)user()['id'])?one('SELECT s.id,l.provider FROM shop_services s LEFT JOIN shop_resource_links l ON l.service_id=s.id WHERE s.id=? AND s.customer_id=?',[(int)$_GET['id'],(int)user()['id']]):null;if(!$requested)http_response_code(404);elseif($requested['provider'])redirect('/konto/verwalten?id='.$requested['id']);}
if(in_array($page,['invoices','admin_invoices'],true) && isset($_GET['download'])){try{download_invoice((int)$_GET['download'],$page==='invoices'?(int)user()['id']:null);}catch(RuntimeException $ex){$error=$ex instanceof PDOException?'Die Rechnung ist gerade nicht abrufbar.':$ex->getMessage();}}

if($page==='cancellation' && isset($_GET['beleg'])){
    $token=(string)$_GET['beleg'];$request=preg_match('/^[a-f0-9]{64}$/D',$token)?one('SELECT * FROM shop_cancellations WHERE receipt_token_hash=?',[hash('sha256',$token)]):null;if(!$request){http_response_code(404);exit('Beleg nicht gefunden.');}while(ob_get_level())ob_end_clean();header('Content-Type: text/plain; charset=utf-8');header('Cache-Control: private, no-store');header('Content-Disposition: attachment; filename="Kuendigung-'.$request['reference'].'.txt"');echo cancellation_receipt($request);exit;
}
require_once __DIR__.'/views/components.php';
require __DIR__ . '/views/layout-start.php';
if ($error) echo '<div class="notice notice-error" role="alert">'.icon('shield').'<span>'.e($error).'</span></div>';
if (isset($_SESSION['flash'])) { [$message,$type]=$_SESSION['flash'];unset($_SESSION['flash']);echo '<div class="notice notice-'.e($type).'" role="status">'.icon('check').'<span>'.e($message).'</span></div>'; }
$view=isset($integrationRoutes[$path])?(str_starts_with($page,'admin_')?'integrations-admin':'integrations-customer'):(str_starts_with($page,'admin_')?'admin':($portal?'account':'store'));
if(isset($commerceRoutes[$path]))$view=$page==='faq'?'faq':($isAdmin?'commerce-admin':'commerce-customer');
if(in_array($page,['modules','customer_modules'],true))$view='modules';
if($page==='admin_coupons')$view='promotions-admin';if($page==='coupon_rewards')$view='promotion-rewards';
if($page==='admin_domains')$view='domains-admin';
if($page==='admin_module_catalog')$view='module-catalog-admin';if($page==='admin_infrastructure')$view='infrastructure-admin';if($page==='module_checkout')$view='module-checkout';
if($page==='configurator')$view='configurator';
if(isset($contractRoutes[$path]))$view='contracts';
if(isset($optionRoutes[$path]))$view='documents';
if(in_array($page,['notifications','admin_notifications'],true))$view='notifications';
if(in_array($page,['vps_exports','admin_vps_exports'],true))$view='vps-exports';
require __DIR__ . '/views/' . $view . '.php';
require __DIR__ . '/views/layout-end.php';
ob_end_flush();
