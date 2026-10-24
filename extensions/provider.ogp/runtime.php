<?php
if(PHP_SAPI!=='cli' && realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){http_response_code(404);exit;}
return static function(): SMF\Core\ProviderClient {
    SMF\Core\ProviderAccounts::bootstrapShop();
    require_once __DIR__.'/Client.php';
    return new SMF\Modules\Ogp\Client();
};
