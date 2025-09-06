<?php
/**
 * DNS-Management Modals
 * Enthält alle Modals für DNS-Verwaltung und -Synchronisation
 */
?>

<!-- DNS-Management Modal -->
<div id="dns-management-modal" class="modal" style="display: none;">
    <div class="modal-content modal-xl">
        <div class="modal-header">
            <h3 id="dns-modal-title"><?= t('dns_management') ?></h3>
            <span class="close" onclick="closeDnsModal()">&times;</span>
        </div>
        <div class="modal-body">
            <!-- DNS-Quellen Tabs -->
            <div class="dns-tabs mb-3">
                <button class="tab-button active" onclick="switchDnsTab('combined')" id="dns-tab-combined">
                    🔗 <?= t('combined_view') ?>
                </button>
                <button class="tab-button" onclick="switchDnsTab('ispconfig')" id="dns-tab-ispconfig">
                    📋 ISPConfig
                </button>
                <button class="tab-button" onclick="switchDnsTab('ovh')" id="dns-tab-ovh">
                    🌐 OVH
                </button>
            </div>
            
            <!-- DNS-Aktionen -->
            <div class="dns-actions mb-3">
                <button id="refresh-dns" class="btn btn-sm btn-secondary">
                    <i class="icon">🔄</i>
                    <?= t('refresh') ?>
                </button>
                <button id="add-dns-record" class="btn btn-sm btn-primary">
                    <i class="icon">➕</i>
                    <?= t('add_dns_record') ?>
                </button>
                <button id="sync-dns-records" class="btn btn-sm btn-warning">
                    <i class="icon">🔄</i>
                    <?= t('sync_dns_records') ?>
                </button>
            </div>
            
            <!-- DNS-Records Tabelle -->
            <div class="table-responsive">
                <table class="table table-striped" id="dns-records-table">
                    <thead>
                        <tr>
                            <th><?= t('type') ?></th>
                            <th><?= t('name') ?></th>
                            <th><?= t('value') ?></th>
                            <th><?= t('ttl') ?></th>
                            <th><?= t('priority') ?></th>
                            <th><?= t('source') ?></th>
                            <th><?= t('status') ?></th>
                            <th><?= t('actions') ?></th>
                        </tr>
                    </thead>
                    <tbody id="dns-records-tbody">
                        <!-- Wird dynamisch geladen -->
                    </tbody>
                </table>
            </div>
            
            <!-- Loading-Indikator -->
            <div id="dns-loading" class="text-center" style="display: none;">
                <div class="spinner-border" role="status">
                    <span class="sr-only"><?= t('loading') ?></span>
                </div>
                <p><?= t('loading_dns_records') ?></p>
            </div>
            
            <!-- Keine DNS-Einträge -->
            <div id="no-dns-records" class="text-center" style="display: none;">
                <p><?= t('no_dns_records_found') ?></p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeDnsModal()">
                <?= t('close') ?>
            </button>
        </div>
    </div>
</div>

<!-- DNS-Eintrag Bearbeiten Modal -->
<div id="dns-record-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="dns-record-modal-title"><?= t('edit_dns_record') ?></h3>
            <span class="close" onclick="closeDnsRecordModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="dns-record-form">
                <input type="hidden" id="dns-record-id">
                <input type="hidden" id="dns-record-source">
                <input type="hidden" id="dns-record-domain">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="dns-record-type"><?= t('type') ?></label>
                            <select id="dns-record-type" class="form-control" required>
                                <option value="A">A</option>
                                <option value="AAAA">AAAA</option>
                                <option value="CNAME">CNAME</option>
                                <option value="MX">MX</option>
                                <option value="TXT">TXT</option>
                                <option value="NS">NS</option>
                                <option value="SRV">SRV</option>
                                <option value="PTR">PTR</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="dns-record-name"><?= t('name') ?></label>
                            <input type="text" id="dns-record-name" class="form-control" placeholder="www" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group mb-3">
                    <label for="dns-record-value"><?= t('value') ?></label>
                    <input type="text" id="dns-record-value" class="form-control" placeholder="192.168.1.1" required>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="dns-record-ttl"><?= t('ttl') ?></label>
                            <input type="number" id="dns-record-ttl" class="form-control" value="3600" min="60" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="dns-record-priority"><?= t('priority') ?></label>
                            <input type="number" id="dns-record-priority" class="form-control" min="0" placeholder="Nur für MX/SRV">
                        </div>
                    </div>
                </div>
                
                <div class="form-group mb-3">
                    <label for="dns-record-source-select"><?= t('source') ?></label>
                    <select id="dns-record-source-select" class="form-control" required>
                        <option value="ispconfig">ISPConfig</option>
                        <option value="ovh">OVH</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeDnsRecordModal()">
                <?= t('cancel') ?>
            </button>
            <button type="button" class="btn btn-danger" id="dns-record-delete" onclick="deleteDnsRecord()" style="display: none;">
                <?= t('delete') ?>
            </button>
            <button type="button" class="btn btn-primary" onclick="saveDnsRecord()">
                <?= t('save') ?>
            </button>
        </div>
    </div>
</div>

<!-- DNS-Synchronisation Modal -->
<div id="dns-sync-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?= t('dns_synchronisation') ?></h3>
            <span class="close" onclick="closeDnsSyncModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div class="form-group mb-3">
                <label for="sync-direction"><?= t('sync_direction') ?></label>
                <select id="sync-direction" class="form-control">
                    <option value="ispconfig_to_ovh">ISPConfig → OVH</option>
                    <option value="ovh_to_ispconfig">OVH → ISPConfig</option>
                </select>
            </div>
            
            <div class="alert alert-warning">
                <strong><?= t('warning') ?>:</strong> <?= t('dns_sync_warning') ?>
            </div>
            
            <div id="sync-preview" style="display: none;">
                <h5><?= t('sync_preview') ?></h5>
                <div id="sync-preview-content">
                    <!-- Wird dynamisch geladen -->
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeDnsSyncModal()">
                <?= t('cancel') ?>
            </button>
            <button type="button" class="btn btn-primary" onclick="previewDnsSync()">
                <?= t('preview') ?>
            </button>
            <button type="button" class="btn btn-danger" onclick="executeDnsSync()" id="execute-sync-btn" style="display: none;">
                <?= t('execute_sync') ?>
            </button>
        </div>
    </div>
</div>
