<?php
require_once dirname(__DIR__).'/framework.php';require_once dirname(__DIR__).'/src/sys.conf.php';
if(!in_array($_SERVER['REQUEST_METHOD']??'GET',['GET','HEAD'],true))HttpSecurity::abort(405,'Methode nicht erlaubt.');
HttpSecurity::rateLimit('public-status',$_SERVER['REMOTE_ADDR']??'unknown',60,60);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['status'=>'operational','checked_at'=>gmdate('c')]);
