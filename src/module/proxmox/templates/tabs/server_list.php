<!-- Server-Liste Tab -->
<div class="tab-content" id="server-list-tab">
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
        <div class="card-header">
            <h4 class="mb-0"><i class="fas fa-list"></i> VMs und Container</h4>
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
</div>
