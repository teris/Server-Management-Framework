<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require dirname(__DIR__).'/src/core/ProviderTransport.php';
try {
    $handle=curl_init($argv[1]);curl_setopt($handle,CURLOPT_RETURNTRANSFER,true);
    ProviderTransport::apply($handle,$argv[1],'proxmox');
    $body=curl_exec($handle);$error=curl_errno($handle);curl_close($handle);
    echo json_encode(['ok'=>$body!==false,'error'=>$error]);
} catch(Throwable $e) { echo json_encode(['ok'=>false,'class'=>get_class($e)]); }
