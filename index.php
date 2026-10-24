<?php
require_once __DIR__.'/framework.php';
require_once __DIR__.'/src/sys.conf.php';
if(SMF\Core\Kernel::boot()->modules()->enabled('shop.catalog')){require __DIR__.'/shop.php';exit;}
header('Location: /public/',true,302);
