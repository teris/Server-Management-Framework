<?php
return static function():object {\SMF\Core\ProviderAccounts::bootstrapShop();require_once __DIR__.'/Catalog.php';require_once __DIR__.'/Service.php';return new \SMF\Modules\Gameserver\Service();};
