<?php
if(PHP_SAPI!=='cli')exit(1);ob_start();require __DIR__.'/bootstrap.php';
$m=SMF\Core\Kernel::boot()->modules();foreach(['shop.storage','shop.catalog','shop.portal','shop.orders','shop.connections','provider.ogp']as$id){$m->install($id);$m->enable($id);}
integration_save('ogp',['url'=>$argv[1],'token'=>str_repeat('a',64),'ca_pem'=>file_get_contents($argv[2])],true);$legacy=new OGPGet();$value=$legacy->getSetting('panel_name');$blocked=false;try{$legacy->getSetting('invalid_html');}catch(Throwable$error){$blocked=true;}
ob_end_clean();echo json_encode(['native_plaintext_setting'=>$value==='Fixture Panel','html_error_rejected'=>$blocked]);
