<?php
// Server-Bearbeitungsseite
$vmid = $_REQUEST['vm'] ?? null;
$node = $_REQUEST['node'] ?? null;

if (!$vmid) {
    echo '<div class="alert alert-danger">Ungültige VM-ID</div>';
    return;
}

// Falls Node nicht übergeben wurde, versuche es aus der VM-Konfiguration zu laden
if (!$node) {
    try {
        $serviceManager = new ServiceManager();
        $vmConfig = $serviceManager->ProxmoxAPI('get', "/cluster/resources?type=vm&vmid={$vmid}");
        if ($vmConfig && isset($vmConfig['data'][0])) {
            $node = $vmConfig['data'][0]['node'];
        }
    } catch (Exception $e) {
        // Fallback: Node unbekannt
        $node = 'unknown';
    }
}
?>

<div id="proxmox-edit-content">
    <!-- Zurück-Button -->
    <div class="mb-3">
        <button onclick="proxmoxModule.backToServerList()" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Zurück zur Server-Liste
        </button>
    </div>

    <!-- Server-Status und -Info -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Server bearbeiten - VM <?= htmlspecialchars($vmid) ?></h4>
                    <div id="server-status-badge">
                        <span class="badge bg-secondary" id="status-indicator">
                            <i class="fas fa-spinner fa-spin"></i> Status wird geprüft...
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>VM ID:</strong> <?= htmlspecialchars($vmid) ?><br>
                            <strong>Node:</strong> <?= htmlspecialchars($node) ?><br>
                            <strong>Letzte Aktualisierung:</strong> <span id="last-update">-</span>
                        </div>
                        <div class="col-md-6">
                            <strong>Speicher:</strong> <span id="memory-info">-</span><br>
                            <strong>CPU Kerne:</strong> <span id="cores-info">-</span><br>
                            <strong>Laufzeit:</strong> <span id="uptime-info">-</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Schnellaktionen</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-success" id="start-server-btn" onclick="proxmoxModule.startServer(<?= $vmid ?>, '<?= $node ?>')">
                            <i class="fas fa-play"></i> Server starten
                        </button>
                        <button class="btn btn-warning" id="stop-server-btn" onclick="proxmoxModule.stopServer(<?= $vmid ?>, '<?= $node ?>')">
                            <i class="fas fa-stop"></i> Server stoppen
                        </button>
                        <button class="btn btn-info" id="restart-server-btn" onclick="proxmoxModule.restartServer(<?= $vmid ?>, '<?= $node ?>')">
                            <i class="fas fa-redo"></i> Server neu starten
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bearbeitungsformular -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Server-Konfiguration bearbeiten</h5>
        </div>
        <div class="card-body">
            <form id="edit-server-form" onsubmit="proxmoxModule.submitUpdateServer(event)">
                <input type="hidden" id="edit_vm_id" name="vmid" value="<?= htmlspecialchars($vmid) ?>">
                <input type="hidden" id="edit_node" name="node" value="<?= htmlspecialchars($node) ?>">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="edit_vm_name" class="form-label">VM Name</label>
                            <input type="text" class="form-control" id="edit_vm_name" name="name" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="edit_vm_description" class="form-label">Beschreibung</label>
                            <input type="text" class="form-control" id="edit_vm_description" name="description">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="edit_vm_memory" class="form-label">Arbeitsspeicher (MB)</label>
                            <input type="number" class="form-control" id="edit_vm_memory" name="memory" min="128" step="128" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="edit_vm_cores" class="form-label">CPU Kerne</label>
                            <input type="number" class="form-control" id="edit_vm_cores" name="cores" min="1" max="32" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="edit_vm_disk" class="form-label">Festplattengröße (GB)</label>
                            <input type="number" class="form-control" id="edit_vm_disk" name="disk_size" min="1" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="edit_vm_ip" class="form-label">IP-Adresse</label>
                            <input type="text" class="form-control" id="edit_vm_ip" name="ip_address" pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$">
                        </div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="edit_vm_mac" class="form-label">MAC-Adresse</label>
                    <input type="text" class="form-control" id="edit_vm_mac" name="mac_address" pattern="^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$">
                </div>
                
                <div class="d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-danger" onclick="proxmoxModule.deleteServer(<?= $vmid ?>, '<?= $node ?>')">
                        <i class="fas fa-trash"></i> Server löschen
                    </button>
                    <div>
                        <button type="button" class="btn btn-outline-secondary me-2" onclick="location.reload()">
                            <i class="fas fa-sync"></i> Aktualisieren
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Änderungen speichern
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Server-Daten beim Laden der Seite abrufen
document.addEventListener('DOMContentLoaded', function() {
    proxmoxModule.loadServerDetails(<?= $vmid ?>, '<?= $node ?>');
    proxmoxModule.checkServerStatus(<?= $vmid ?>, '<?= $node ?>');
    
    // Status alle 30 Sekunden aktualisieren
    setInterval(function() {
        proxmoxModule.checkServerStatus(<?= $vmid ?>, '<?= $node ?>');
    }, 30000);
});
</script>
