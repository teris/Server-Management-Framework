<?php
/**
 * Proxmox Module Main Template
 * Haupttemplate mit modularen Includes für bessere Wartbarkeit
 */
?>

<div id="proxmox-content">
    <!-- Aktionsergebnis anzeigen -->
    <div id="action-result"><pre>
    	<?php
        $serverManager = new ServiceManager();
        //@get /nodes/{node}/qemu/{vmid}/config
		//@get nodes/{node}/storage/{storage}/status
       // $debug =   $serverManager->ProxmoxAPI('get', '/nodes/server/storage/store01/status');
        //print_r($debug);
        ?>
    </pre></div>
    
    <?php
    // Prüfe URL-Parameter für verschiedene Ansichten
    if (isset($_REQUEST['vm']) && !empty($_REQUEST['vm']) && isset($_REQUEST['edit']) && $_REQUEST['edit'] == '1') {
        // VM-Bearbeitungsseite
        include __DIR__ . '/tabs/edit_server.php';
    } else {
        // Header mit Tab-Navigation
        include __DIR__ . '/parts/header.php';
        
               // Haupttabs
               include __DIR__ . '/tabs/node_selection.php';
               include __DIR__ . '/tabs/server_list.php';
               include __DIR__ . '/tabs/vm_creation.php';
               include __DIR__ . '/tabs/extended_features.php';
               include __DIR__ . '/tabs/server_management.php';
        
        // Modals
        include __DIR__ . '/modals/server_management.php';
        include __DIR__ . '/modals/create_vm.php';
        include __DIR__ . '/modals/edit_server.php';
        include __DIR__ . '/modals/confirmation_modal.php';
        
        // Footer mit JavaScript-Includes
        include __DIR__ . '/parts/footer.php';
    }
    ?>
</div>
