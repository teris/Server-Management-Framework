<!-- Server-Liste Tab -->
<div class="tab-content" id="server-list-tab" style="display: none;">
    <!-- Node-Informationen -->
    <div class="card mb-4">
        <div class="card-header">
            <h4 class="mb-0"><i class="fas fa-server"></i> Verfügbare Nodes</h4>
        </div>
        <div class="card-body">
            <div id="nodes-overview-container">
                <div class="text-center py-4">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Laden...</span>
                    </div>
                    <p class="mt-2">Laden der verfügbaren Nodes...</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Server-Liste (wird erst angezeigt wenn Node ausgewählt) -->
    <div class="card" id="server-list-card" style="display: none;">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-list"></i> VMs und Container</h4>
            <div>
                <button class="btn btn-primary me-2" onclick="proxmoxModule.showCreateVMForm()">
                    <i class="fas fa-plus"></i> VM erstellen
                </button>
                <button class="btn btn-info" onclick="proxmoxModule.showCreateLXCForm()">
                    <i class="fas fa-cube"></i> LXC erstellen
                </button>
            </div>
        </div>
        <div class="card-body">
            <div id="server-list-container">
                <div class="text-center py-4">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Laden...</span>
                    </div>
                    <p class="mt-2">Laden...</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- VM-Erstellungsformular (versteckt, wird per JavaScript angezeigt) -->
    <div id="vm-creation-container" style="display: none;">
        <?php include 'vm_creation.php'; ?>
    </div>
</div>
