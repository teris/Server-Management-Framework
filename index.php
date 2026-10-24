<?php
if (!is_file(__DIR__.'/config/config.inc.php') && !getenv('SMF_CONFIG_FILE')) { header('Location: /setup.php',true,303); exit; }
require_once __DIR__.'/framework.php';
require_once __DIR__.'/src/sys.conf.php';
if(SMF\Core\Kernel::boot()->modules()->enabled('shop.catalog')){require __DIR__.'/shop.php';exit;}
header('Location: /public/',true,302);
