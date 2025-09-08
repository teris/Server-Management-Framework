<?php
/**
 * Server Management Framework
 * 
 * @author Teris
 * @version 3.1.4
 */
/**
 * Domain-Einstellungen - Admin-Bereich für Domain-Endungen
 */
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-gear"></i> Domain-Einstellungen
                </h5>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExtensionModal">
                    <i class="bi bi-plus"></i> Neue Domain-Endung
                </button>
            </div>
            <div class="card-body">
                <div id="extensionsTable">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Lade...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Neue Domain-Endung Modal -->
<div class="modal fade" id="addExtensionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Neue Domain-Endung hinzufügen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addExtensionForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="tld" class="form-label">TLD *</label>
                        <div class="input-group">
                            <span class="input-group-text">.</span>
                            <input type="text" class="form-control" name="tld" id="tld" 
                                   placeholder="com" required maxlength="10">
                        </div>
                        <div class="form-text">Nur die Endung ohne Punkt (z.B. com, de, net)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Name *</label>
                        <input type="text" class="form-control" name="name" id="name" 
                               placeholder="Commercial" required>
                        <div class="form-text">Beschreibender Name für die TLD</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="active" id="active" checked>
                            <label class="form-check-label" for="active">
                                Aktiv (sofort verfügbar)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Hinzufügen</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Domain-Endung bearbeiten Modal -->
<div class="modal fade" id="editExtensionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Domain-Endung bearbeiten</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editExtensionForm">
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="mb-3">
                        <label for="edit_tld" class="form-label">TLD *</label>
                        <div class="input-group">
                            <span class="input-group-text">.</span>
                            <input type="text" class="form-control" name="tld" id="edit_tld" 
                                   required maxlength="10">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Name *</label>
                        <input type="text" class="form-control" name="name" id="edit_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="active" id="edit_active">
                            <label class="form-check-label" for="edit_active">
                                Aktiv (verfügbar)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Aktualisieren</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript-Code wurde in assets/inc-js/domain-settings.js ausgelagert -->
