<?php
/**
 * Domain Management Tab
 * Enthält den Domain-Management Bereich
 */
?>

<!-- Domain Management Tab -->
<div id="domains-tab" class="tab-content">
    <div class="card">
        <div class="card-header">
            <h2 class="mb-0">🌐 <?= t('domain_management') ?></h2>
            <div class="header-actions">
                <button id="refresh-domains" class="btn btn-sm btn-secondary">
                    <i class="icon">🔄</i>
                    <?= t('refresh') ?>
                </button>
                <button id="bulk-domain-changes" class="btn btn-sm btn-primary" disabled>
                    <i class="icon">⚡</i>
                    <?= t('bulk_changes') ?>
                </button>
            </div>
        </div>
        <div class="card-body">
            <!-- Domain-Filter -->
            <div class="domain-filters mb-3">
                <div class="row">
                    <div class="col-md-3">
                        <input type="text" id="domain-search" class="form-control" placeholder="<?= t('search_domains') ?>">
                    </div>
                    <div class="col-md-2">
                        <select id="domain-status-filter" class="form-control">
                            <option value=""><?= t('all_status') ?></option>
                            <option value="y"><?= t('active') ?></option>
                            <option value="n"><?= t('inactive') ?></option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select id="domain-assignment-filter" class="form-control">
                            <option value=""><?= t('all_assignments') ?></option>
                            <option value="assigned"><?= t('assigned') ?></option>
                            <option value="unassigned"><?= t('unassigned') ?></option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="domain-sort-filter" class="form-control">
                            <option value="domain"><?= t('sort_by_domain') ?></option>
                            <option value="assigned_user"><?= t('sort_by_user') ?></option>
                            <option value="created_at"><?= t('sort_by_date') ?></option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button id="clear-domain-filters" class="btn btn-sm btn-outline-secondary">
                            <?= t('clear_filters') ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Domain-Tabelle -->
            <div class="table-responsive">
                <table class="table table-striped" id="domains-table">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="select-all-domains">
                            </th>
                            <th><?= t('domain') ?></th>
                            <th><?= t('ip_address') ?></th>
                            <th><?= t('assigned_user') ?></th>
                            <th><?= t('hd_quota') ?></th>
                            <th><?= t('traffic_quota') ?></th>
                            <th><?= t('ssl') ?></th>
                            <th><?= t('php_version') ?></th>
                            <th><?= t('status') ?></th>
                            <th><?= t('created_at') ?></th>
                            <th><?= t('actions') ?></th>
                        </tr>
                    </thead>
                    <tbody id="domains-tbody">
                        <!-- Wird dynamisch geladen -->
                    </tbody>
                </table>
            </div>
            
            <!-- Loading-Indikator -->
            <div id="domains-loading" class="text-center" style="display: none;">
                <div class="spinner-border" role="status">
                    <span class="sr-only"><?= t('loading') ?></span>
                </div>
                <p><?= t('loading_domains') ?></p>
            </div>
            
            <!-- Keine Domains -->
            <div id="no-domains" class="text-center" style="display: none;">
                <p><?= t('no_domains_found') ?></p>
            </div>
        </div>
    </div>
</div>
