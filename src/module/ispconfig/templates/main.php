<?php
if (PHP_SAPI !== 'cli' && (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__ || (get_included_files()[0] ?? null) === __FILE__)) { http_response_code(404); exit; }

/**
 * ISPConfig Module Main Template
 * Haupttemplate mit modularen Includes für bessere Wartbarkeit
 */
?>

<div id="ispconfig-content">
    <!-- Aktionsergebnis anzeigen -->
    <div id="action-result" style="display: none;"></div>
    
    <?php
    // Header mit Tab-Navigation
    include __DIR__ . '/parts/header.php';
    
    // Haupttabs
    include __DIR__ . '/tabs/websites-tab.php';
    include __DIR__ . '/tabs/user-management-tab.php';
    include __DIR__ . '/tabs/domain-management-tab.php';
    
    // Modals
    include __DIR__ . '/modals/domain-management.php';
    include __DIR__ . '/modals/dns-management.php';
    include __DIR__ . '/modals/user-management.php';
    
    // Footer mit JavaScript-Includes
    include __DIR__ . '/parts/footer.php';
    ?>
</div>
