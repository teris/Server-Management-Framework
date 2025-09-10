<!-- Server-Management-Modal -->
<div class="modal fade" id="serverManagementModal" tabindex="-1" aria-labelledby="serverManagementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="serverManagementModalLabel"><?= t('server_management_modal') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= t('close') ?>"></button>
            </div>
            <div class="modal-body">
                <div id="server-details">
                    <!-- Server-Details werden hier dynamisch geladen -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('close') ?></button>
            </div>
        </div>
    </div>
</div>
