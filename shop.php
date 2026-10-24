<?php
// Stable front controller for the optional shop and old Online Shop URLs.
ob_start();
try {
    require_once __DIR__.'/framework.php';
    require_once __DIR__.'/src/sys.conf.php';
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self'; script-src 'self'; font-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
    $path=rtrim(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/','/')?:'/';
    $registry=SMF\Core\Kernel::boot()->modules();
    $routes=$registry->routes();
    if(!isset($routes[$path]))throw new SecurityViolation('Nicht gefunden.',404);
    $registry->assertEnabled($routes[$path]['module']);
    require __DIR__.'/extensions/shop.storage/legacy/controller.php';
} catch(Throwable $exception) {
    error_log('Shop request failed: '.get_class($exception).' at '.$exception->getFile().':'.$exception->getLine());
    while(ob_get_level())ob_end_clean();
    $status=$exception instanceof SecurityViolation?$exception->getCode():503;
    HttpSecurity::abort($status,$status===404?'Nicht gefunden.':($status===401?'Anmeldung erforderlich.':'Die Anfrage ist derzeit nicht möglich.'));
}
