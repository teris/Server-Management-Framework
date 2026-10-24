<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
fwrite(STDERR, "The legacy installer is disabled. Use bin/install.php for an empty database or bin/migrate.php for an upgrade.\n");
exit(1);
