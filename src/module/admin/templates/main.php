<?php
if (PHP_SAPI !== 'cli' && (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__ || (get_included_files()[0] ?? null) === __FILE__)) { http_response_code(404); exit; }
?>
<div id="admin-content">
    <!-- Aktionsergebnis anzeigen -->
    <div id="action-result" style="display: none;"></div>
    
    <?php

    // Header mit Schnellaktionen und System-Status
    include __DIR__ . '/parts/header.php';
    ?>

    <?php
    // Haupttabs
    include __DIR__ . '/tabs/resources-tab.php';
    include __DIR__ . '/tabs/system-logs-tab.php';
    
    // Footer mit JavaScript-Includes
    include __DIR__ . '/parts/footer.php';
    ?>
