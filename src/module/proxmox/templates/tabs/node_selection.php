<?php
if (PHP_SAPI !== 'cli' && (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__ || (get_included_files()[0] ?? null) === __FILE__)) { http_response_code(404); exit; }
?>
<!-- Node-Auswahl Tab -->
<div class="tab-content" id="node-selection-tab" style="display: block;">
    <div class="card">
        <div class="card-header">
            <h3 class="mb-0"><i class="fas fa-server"></i> Node-Auswahl</h3>
        </div>
        <div class="card-body">
            <div id="node-selection-content">
                <div class="text-center py-4">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Laden...</span>
                    </div>
                    <p class="mt-2">Laden der verfügbaren Nodes...</p>
                </div>
            </div>
        </div>
    </div>
</div>
