<?php
require_once dirname(__DIR__).'/framework.php';require_once dirname(__DIR__).'/src/sys.conf.php';
SMF\Core\Kernel::boot()->modules()->assertEnabled('shop.portal');
$accent=SMF\Core\ShopSettings::get('accent');if(!is_string($accent)||!preg_match('/^#[a-fA-F0-9]{6}$/D',$accent))$accent='#2563eb';
header('Content-Type: text/css; charset=utf-8');echo ':root{--color-primary:'.$accent.';--color-primary-light:'.$accent.';--teal:'.$accent.';--teal-dark:'.$accent.';--accent:'.$accent.';--blue:'.$accent.'} .button-primary{background:'.$accent.'}';
