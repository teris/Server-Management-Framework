<?php
require_once __DIR__ . '/sys.conf.php';
require_once dirname(__DIR__) . '/framework.php';
require_once __DIR__ . '/core/PrivateFiles.php';
$realm = ($_GET['context'] ?? 'backend') === 'customer' ? 'customer' : 'backend';
$identity = Identity::requireIdentity($realm);
$file = PrivateFiles::read($identity, $_GET['id'] ?? '');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $file['filename'] . '"');
header('Content-Length: ' . strlen($file['content']));
echo $file['content'];
