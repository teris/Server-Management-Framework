<?php
declare(strict_types=1);
date_default_timezone_set('Europe/Berlin');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if(!class_exists('Database',false))require_once dirname(__DIR__,3) . '/framework.php';
require_once dirname(__DIR__,3) . '/src/sys.conf.php';
require_once dirname(__DIR__,3) . '/src/core/ResourceAccess.php';
require_once __DIR__ . '/core-bridge.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/appearance.php';
require_once __DIR__ . '/modules.php';
require_once __DIR__ . '/billing.php';
require_once __DIR__ . '/integrations.php';
require_once __DIR__ . '/ovh-authorization.php';
require_once __DIR__ . '/resources.php';
require_once __DIR__ . '/payments.php';
require_once __DIR__ . '/packages.php';
require_once __DIR__ . '/domain-ordering.php';
require_once __DIR__ . '/provisioning.php';
require_once __DIR__ . '/integration-actions.php';
require_once __DIR__.'/commerce.php';
require_once __DIR__.'/offers.php';
require_once __DIR__.'/invoices.php';
require_once __DIR__.'/mail.php';
require_once __DIR__.'/faq.php';
require_once __DIR__.'/commerce-actions.php';
require_once __DIR__.'/domains.php';
require_once __DIR__.'/contracts.php';
require_once __DIR__.'/receivables.php';
require_once __DIR__.'/payment-reconciliation.php';
require_once __DIR__.'/contract-actions.php';
require_once __DIR__.'/customer-visibility.php';
require_once __DIR__.'/account-deletion.php';
require_once __DIR__.'/notifications.php';
require_once __DIR__.'/documents.php';
require_once __DIR__.'/product-modules.php';
require_once __DIR__.'/admin-payments.php';
require_once __DIR__.'/order-admin.php';
require_once __DIR__.'/admin-manual-termination.php';
require_once __DIR__.'/contract-admin.php';
require_once __DIR__.'/hosting-options.php';
require_once __DIR__.'/hosting-panel.php';
require_once __DIR__.'/hosting-domains.php';
require_once __DIR__.'/hosting-mail-dns.php';
require_once __DIR__.'/hosting-features.php';
require_once __DIR__.'/module-catalog.php';
require_once __DIR__.'/module-subscriptions.php';
require_once __DIR__.'/zero-base-packages.php';
require_once __DIR__.'/promotions.php';
require_once __DIR__.'/promotion-payments.php';
require_once __DIR__.'/proxmox-options.php';
require_once __DIR__.'/vps-storage.php';
require_once __DIR__.'/vps-exports.php';
require_once __DIR__.'/infrastructure.php';
require_once __DIR__.'/lxc-provisioning.php';
require_once __DIR__.'/dns-templates.php';
require_once __DIR__.'/infrastructure-automation.php';
require_once __DIR__.'/infrastructure-actions.php';
require_once __DIR__.'/shop-option-actions.php';

function e($value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function db(): PDO { return HttpSecurity::connection(); }
function query(string $sql, array $params = []): PDOStatement { $s = db()->prepare($sql); $s->execute($params); return $s; }
function one(string $sql, array $params = []): ?array { return query($sql, $params)->fetch(PDO::FETCH_ASSOC) ?: null; }
function all(string $sql, array $params = []): array { return query($sql, $params)->fetchAll(PDO::FETCH_ASSOC); }
function redirect(string $url): never { header('Location: ' . $url, true, 303); exit; }
function csrf(): string { return HttpSecurity::csrfField(); }
function flash(string $message, string $type = 'success'): void { $_SESSION['flash'] = [$message, $type]; }
function user(): ?array { return Identity::current('customer')['record'] ?? null; }
function admin(): ?array { $actor=Identity::current('backend'); return $actor && $actor['role']==='admin' ? $actor['record'] : null; }
function require_user(): array { if (!user()) { $_SESSION['after_login'] = $_SERVER['REQUEST_URI']; redirect('/login'); } return user(); }
function require_admin(): array { if (!admin()) redirect('/admin/login'); return admin(); }
function rate_limit(string $name,int $max=8,int $seconds=900): void { HttpSecurity::rateLimit('shop-'.$name,$_SERVER['REMOTE_ADDR']??'local',$max,$seconds); }
function products(): array { return all('SELECT * FROM shop_products WHERE active = 1 ORDER BY sort_order, id'); }
function product(string $slug): ?array { return one('SELECT * FROM shop_products WHERE slug = ? AND active = 1', [$slug]); }
function money($cents): string { return number_format((int)$cents / 100, 2, ',', '.') . ' €'; }
function price(array $p): string { return $p['price_cents'] === null ? 'Auf Anfrage' : money($p['price_cents']); }
function cycle(string $value): string { return ['month'=>'Monat','months2'=>'2 Monate','months24'=>'24 Monate','quarter'=>'3 Monate','halfyear'=>'6 Monate','year'=>'Jahr','once'=>'einmalig'][$value] ?? 'Monat'; }
function label(string $value): string {
    return ['new'=>'Eingegangen','reviewing'=>'In Bearbeitung','quoted'=>'Angebot erstellt','completed'=>'Abgeschlossen','declined'=>'Abgelehnt','cancelled'=>'Storniert','active'=>'Aktiv','pending'=>'In Einrichtung','suspended'=>'Pausiert','open'=>'Offen','in_progress'=>'In Bearbeitung','waiting_customer'=>'Antwort erforderlich','waiting_admin'=>'Wartet auf Antwort','resolved'=>'Gelöst','closed'=>'Geschlossen'][$value] ?? $value;
}
function badge(string $status): string { return '<span class="badge status-' . e($status) . '"><span></span>' . e(label($status)) . '</span>'; }
function date_de(?string $date): string { return $date ? date('d.m.Y', strtotime($date . ' UTC')) : '–'; }
function valid_domain(string $domain): string {
    $domain = strtolower(trim($domain));
    $domain = preg_replace('#^https?://#', '', $domain);
    $domain = rtrim($domain, '/');
    if ($domain === '') return '';
    if (function_exists('idn_to_ascii')) $domain = idn_to_ascii($domain, 0, INTL_IDNA_VARIANT_UTS46) ?: $domain;
    if (strlen($domain) > 253 || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+(?:[a-z]{2,63}|xn--[a-z0-9-]{2,59})$/D', $domain)) throw new RuntimeException('Bitte gib eine gültige Domain ein, zum Beispiel meinprojekt.de.');
    return $domain;
}
function text_field(string $key, int $max = 255): string {
    $input=$_POST[$key]??'';if(!is_string($input)&&!is_int($input))throw new RuntimeException('Bitte gültige Texteingaben verwenden.');
    $value = trim((string)$input);
    if (strlen($value) > $max) throw new RuntimeException('Deine Eingabe ist zu lang. Bitte kürze das Feld.');
    return $value;
}
function login_customer(array $customer): void { Identity::login('customer',$customer); }
function return_after_login(): never {
    $target = $_SESSION['after_login'] ?? '/konto'; unset($_SESSION['after_login']);
    if (!str_starts_with($target, '/') || str_starts_with($target, '//')) $target = '/konto';
    redirect($target);
}
function icon(string $name, string $class = ''): string {
    $paths = [
      'sun'=>'<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"/>',
      'moon'=>'<path d="M20.5 14A9 9 0 0 1 10 3.5 9 9 0 1 0 20.5 14Z"/>',
      'arrow'=>'<path d="M5 12h14m-6-6 6 6-6 6"/>',
      'chevron'=>'<path d="m9 5 7 7-7 7"/>',
      'check'=>'<path d="m5 12 4 4L19 6"/>',
      'globe'=>'<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c5 5 5 13 0 18-5-5-5-13 0-18Z"/>',
      'server'=>'<rect x="3" y="3" width="18" height="7" rx="2"/><rect x="3" y="14" width="18" height="7" rx="2"/><path d="M7 6.5h.01M7 17.5h.01M12 6.5h5M12 17.5h5"/>',
      'database'=>'<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 4 16 4 16 0V5M4 12c0 4 16 4 16 0"/>',
      'cloud'=>'<path d="M7 19a5 5 0 1 1 .6-10A7 7 0 0 1 21 12a3.5 3.5 0 0 1-1 7Z"/>',
      'compass'=>'<circle cx="12" cy="12" r="9"/><path d="m16 8-2 6-6 2 2-6Z"/>',
      'shield'=>'<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6Z"/><path d="m8 12 3 3 5-6"/>',
      'user'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
      'grid'=>'<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
      'package'=>'<path d="m12 3 9 5-9 5-9-5Z"/><path d="M3 8v9l9 5 9-5V8M12 13v9M7 5l10 6"/>',
      'message'=>'<path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H4l-3 2 2-6A8.5 8.5 0 1 1 21 11.5Z"/><path d="M7 10h9M7 14h6"/>',
      'settings'=>'<path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="3"/><circle cx="16" cy="17" r="3"/>',
      'logout'=>'<path d="M10 4H4v16h6M14 8l4 4-4 4M8 12h10"/>',
      'plus'=>'<path d="M12 5v14M5 12h14"/>',
      'mail'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>',
      'lock'=>'<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4M12 14v3"/>',
      'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
      'headset'=>'<path d="M4 13V10a8 8 0 0 1 16 0v3M20 17v2a2 2 0 0 1-2 2h-4"/><rect x="2" y="11" width="5" height="7" rx="2"/><rect x="17" y="11" width="5" height="7" rx="2"/>',
      'bolt'=>'<path d="m13 2-9 12h7l-1 8 10-13h-7Z"/>',
      'menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>',
      'external'=>'<path d="M14 3h7v7M21 3l-11 11M10 3H4v17h17v-6"/>',
      'search'=>'<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
      'eye'=>'<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
      'leaf'=>'<path d="M20 3C9 2 2 7 5 15c3 8 17 1 15-12ZM4 21 16 9"/>',
    ];
    return '<svg class="icon ' . e($class) . '" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? $paths['package']) . '</svg>';
}


if(PHP_SAPI!=='cli') { HttpSecurity::session(); }
