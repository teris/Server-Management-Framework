<?php
if(PHP_SAPI!=='cli' && realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){http_response_code(404);exit;}
return static fn()=>new \SMF\Core\ShopProviderClient('ispconfig');
