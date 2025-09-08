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
