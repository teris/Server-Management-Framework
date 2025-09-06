<?php
/**
 * Domain-Management Modals
 * Enthält alle Modals für Domain-Zuordnung und -Einstellungen
 */
?>

<!-- Domain-Zuordnung Modal -->
<div id="domain-assignment-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?= t('assign_domain_to_user') ?></h3>
            <span class="close" onclick="closeDomainModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="domain-assignment-form">
                <input type="hidden" id="assignment-domain-id">
                
                <div class="form-group mb-3">
                    <label><?= t('domain') ?></label>
                    <input type="text" id="assignment-domain-name" class="form-control" readonly>
                </div>
                
                <div class="form-group mb-3">
                    <label for="assignment-client-id"><?= t('select_user') ?></label>
                    <select id="assignment-client-id" class="form-control" required>
                        <option value=""><?= t('select_user_placeholder') ?></option>
                    </select>
                </div>
                
                <div class="form-group mb-3">
                    <label for="assignment-notes"><?= t('notes') ?></label>
                    <textarea id="assignment-notes" class="form-control" rows="3" placeholder="<?= t('assignment_notes_placeholder') ?>"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeDomainModal()">
                <?= t('cancel') ?>
            </button>
            <button type="button" class="btn btn-primary" onclick="assignDomain()">
                <?= t('assign') ?>
            </button>
        </div>
    </div>
</div>

<!-- Domain-Einstellungen Modal -->
<div id="domain-settings-modal" class="modal" style="display: none;">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3><?= t('domain_settings') ?></h3>
            <span class="close" onclick="closeDomainSettingsModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="domain-settings-form">
                <input type="hidden" id="settings-domain-id">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label><?= t('domain') ?></label>
                            <input type="text" id="settings-domain-name" class="form-control" readonly>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="settings-ip-address"><?= t('ip_address') ?></label>
                            <input type="text" id="settings-ip-address" class="form-control">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="settings-hd-quota"><?= t('hd_quota') ?></label>
                            <input type="number" id="settings-hd-quota" class="form-control" min="100">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="settings-traffic-quota"><?= t('traffic_quota') ?></label>
                            <input type="number" id="settings-traffic-quota" class="form-control" min="1000">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="settings-php-version"><?= t('php_version') ?></label>
                            <select id="settings-php-version" class="form-control">
                                <option value="php-fpm">PHP-FPM</option>
                                <option value="php-fpm-7.4">PHP 7.4</option>
                                <option value="php-fpm-8.0">PHP 8.0</option>
                                <option value="php-fpm-8.1">PHP 8.1</option>
                                <option value="php-fpm-8.2">PHP 8.2</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="settings-ssl-enabled"><?= t('ssl_enabled') ?></label>
                            <select id="settings-ssl-enabled" class="form-control">
                                <option value="n"><?= t('disabled') ?></option>
                                <option value="y"><?= t('enabled') ?></option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-group mb-3">
                    <label for="settings-active"><?= t('status') ?></label>
                    <select id="settings-active" class="form-control">
                        <option value="y"><?= t('active') ?></option>
                        <option value="n"><?= t('inactive') ?></option>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeDomainSettingsModal()">
                <?= t('cancel') ?>
            </button>
            <button type="button" class="btn btn-primary" onclick="updateDomainSettings()">
                <?= t('save_changes') ?>
            </button>
        </div>
    </div>
</div>

<!-- Änderungs-Vorschau Modal -->
<div id="changes-preview-modal" class="modal" style="display: none;">
    <div class="modal-content modal-xl">
        <div class="modal-header">
            <h3><?= t('preview_changes') ?></h3>
            <span class="close" onclick="closeChangesPreviewModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div class="changes-summary mb-4">
                <div class="row">
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <h4 class="text-primary" id="total-changes">0</h4>
                                <p><?= t('total_changes') ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <h4 class="text-warning" id="affected-users">0</h4>
                                <p><?= t('affected_users') ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <h4 class="text-info" id="affected-domains">0</h4>
                                <p><?= t('affected_domains') ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="changes-list">
                <h4><?= t('changes_details') ?></h4>
                <div id="changes-preview-list">
                    <!-- Wird dynamisch geladen -->
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeChangesPreviewModal()">
                <?= t('cancel') ?>
            </button>
            <button type="button" class="btn btn-danger" onclick="executeDomainChanges()">
                <?= t('execute_changes') ?>
            </button>
        </div>
    </div>
</div>
