<?php
// Local HTTP test/development router; Apache uses the root .htaccess.
if(PHP_SAPI!=='cli-server'){http_response_code(404);exit;}
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)?:'/';
if(preg_match('~^/(?:config|database|install|docs|tests|bin|deploy|integrations|extensions|\.)~i',$path)||preg_match('~^/src/(?:core|kernel|inc|module)/.*\.php(?:/|$)~i',$path)){http_response_code(403);exit;}
$file=dirname(__DIR__).$path;
if($path!=='/' && is_file($file))return false;
require dirname(__DIR__).'/shop.php';
