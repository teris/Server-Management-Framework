<?php
if (PHP_SAPI !== 'cli' && (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__ || (get_included_files()[0] ?? null) === __FILE__)) { http_response_code(404); exit; }

/**
 * ISPConfig Module Footer
 * Enthält alle JavaScript-Includes
 */
?>

<script src="module/ispconfig/assets/module.js"></script>
<script src="module/ispconfig/assets/user-management.js"></script>
<script src="module/ispconfig/assets/domain-management.js"></script>
<script src="module/ispconfig/assets/dns-management.js"></script>
