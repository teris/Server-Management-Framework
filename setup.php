<?php
if (PHP_VERSION_ID < 80300) { http_response_code(503); header('Content-Type: text/plain; charset=UTF-8'); echo 'Für die Shopinstallation ist PHP 8.3 oder neuer erforderlich.'; exit; }
require_once __DIR__.'/src/setup/WebWizard.php';
(new \SMF\Setup\WebWizard(new \SMF\Setup\Installer(__DIR__)))->run();
