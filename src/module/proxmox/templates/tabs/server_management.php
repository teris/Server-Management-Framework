<!-- Server-Verwaltung Tab -->
<div class="tab-content" id="server-management-tab" style="display: none;">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="mb-0">Server-Verwaltung</h3>
            <button class="btn btn-outline-secondary" onclick="proxmoxModule.closeServerManagement()">
                <i class="fas fa-times"></i> Schließen
            </button>
        </div>
        <div class="card-body">
            <div id="server-management-content">
                <div class="text-center py-4">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Laden...</span>
                    </div>
                    <p class="mt-2">Server-Details werden geladen...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Erweiterte Server-Details Tab -->
<div class="tab-content" id="server-details-tab" style="display: none;">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="mb-0">Erweiterte Server-Details</h3>
            <button class="btn btn-outline-secondary" onclick="proxmoxModule.closeServerDetails()">
                <i class="fas fa-times"></i> Schließen
            </button>
        </div>
        <div class="card-body">
            <div id="server-details-content">
                <div class="text-center py-4">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Laden...</span>
                    </div>
                    <p class="mt-2">Laden der detaillierten Server-Informationen...</p>
                </div>
            </div>
        </div>
    </div>
</div>
