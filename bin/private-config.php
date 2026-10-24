<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/config/load.php';
require_once dirname(__DIR__).'/src/core/Security.php';
HttpSecurity::cliBoundary();
$root=dirname(__DIR__);$source=$root.'/config/config.inc.php';$destination=HttpSecurity::privateDirectory('config').'/runtime.php';
if(!is_file($source)||is_link($source))throw new RuntimeException('Regular local configuration required');
$content=file_get_contents($source);$shim="<?php\nrequire_once ".var_export($destination,true).";\n";
function preservePrivateFile(string $source,string $destination):void{
    if(is_link($source)||is_link($destination))throw new RuntimeException('Configuration/key symlink rejected');
    if(is_file($destination)){
        if(!hash_equals(hash_file('sha256',$source),hash_file('sha256',$destination)))throw new RuntimeException('Different private file already exists; no file replaced');
    }else{
        $copy=fopen($destination,'x');if(!$copy)throw new RuntimeException('Private destination unavailable');$input=fopen($source,'rb');
        try{if(stream_copy_to_stream($input,$copy)!==filesize($source))throw new RuntimeException('Private file copy incomplete');}finally{fclose($input);fclose($copy);}chmod($destination,0600);
    }
    if(!hash_equals(hash_file('sha256',$source),hash_file('sha256',$destination)))throw new RuntimeException('Private file verification failed');
}
if($content===$shim){if(!is_file($destination)||is_link($destination))throw new RuntimeException('Private configuration missing');}
else preservePrivateFile($source,$destination);
$legacy=$root.'/src/.encryption_key';if(is_link($legacy))throw new RuntimeException('Legacy key symlink rejected');
if(is_file($legacy)){preservePrivateFile($legacy,HttpSecurity::privateDirectory('keys').'/provider-encryption.key');unlink($legacy);}
$local=$root.'/src/sys.conf.local.php';if(is_link($local))throw new RuntimeException('Operator settings symlink rejected');
if(is_file($local))preservePrivateFile($local,HttpSecurity::privateDirectory('config').'/system.local.php');
if($content!==$shim){$temporary=tempnam(dirname($source),'.config-migration-');if(!$temporary||file_put_contents($temporary,$shim,LOCK_EX)!==strlen($shim))throw new RuntimeException('Configuration shim failed');chmod($temporary,0600);if(!rename($temporary,$source)){unlink($temporary);throw new RuntimeException('Configuration activation failed');}}
echo "Private configuration, optional operator overrides and existing encryption key verified. No credential values printed.\n";
