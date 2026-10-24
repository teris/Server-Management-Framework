<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(is_file(shop_private().'/commerce-release.lock'))exit;
require __DIR__.'/bootstrap.php';
run_shop_worker(5);
echo "Bereitstellung, Rechnungen und E-Mail-Versand geprüft.\n";
