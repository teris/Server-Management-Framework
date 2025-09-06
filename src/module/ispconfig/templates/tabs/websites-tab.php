<?php
/**
 * Websites Tab
 * Enthält den Websites-Bereich
 */
?>

<!-- Websites Tab -->
<div id="websites-tab" class="tab-content active">
    <div class="card">
        <div class="card-header">
            <h2 class="mb-0">🌐 <?= t('websites') ?></h2>
            <div class="header-actions">
                <button id="refresh-websites" class="btn btn-sm btn-secondary">
                    <i class="icon">🔄</i>
                    <?= t('refresh') ?>
                </button>
                <button id="create-website" class="btn btn-sm btn-primary">
                    <i class="icon">➕</i>
                    <?= t('create_website') ?>
                </button>
            </div>
        </div>
        <div class="card-body">
            <!-- Website-Filter -->
            <div class="website-filters mb-3">
                <div class="row">
                    <div class="col-md-4">
                        <input type="text" id="website-search" class="form-control" placeholder="<?= t('search_websites') ?>">
                    </div>
                    <div class="col-md-3">
                        <select id="website-status-filter" class="form-control">
                            <option value=""><?= t('all_status') ?></option>
                            <option value="active"><?= t('active') ?></option>
                            <option value="inactive"><?= t('inactive') ?></option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="website-sort-filter" class="form-control">
                            <option value="domain"><?= t('sort_by_domain') ?></option>
                            <option value="client"><?= t('sort_by_client') ?></option>
                            <option value="created_at"><?= t('sort_by_date') ?></option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button id="clear-website-filters" class="btn btn-sm btn-outline-secondary">
                            <?= t('clear_filters') ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Website-Tabelle -->
            <div class="table-responsive">
                <table class="table table-striped" id="websites-table">
                    <thead>
                        <tr>
                            <th><?= t('domain') ?></th>
                            <th><?= t('client') ?></th>
                            <th><?= t('ip_address') ?></th>
                            <th><?= t('hd_quota') ?></th>
                            <th><?= t('traffic_quota') ?></th>
                            <th><?= t('ssl') ?></th>
                            <th><?= t('status') ?></th>
                            <th><?= t('created_at') ?></th>
                            <th><?= t('actions') ?></th>
                        </tr>
                    </thead>
                    <tbody id="websites-tbody">
                        <!-- Wird dynamisch geladen -->
                    </tbody>
                </table>
            </div>
            
            <!-- Loading-Indikator -->
            <div id="websites-loading" class="text-center" style="display: none;">
                <div class="spinner-border" role="status">
                    <span class="sr-only"><?= t('loading') ?></span>
                </div>
                <p><?= t('loading_websites') ?></p>
            </div>
            
            <!-- Keine Websites -->
            <div id="no-websites" class="text-center" style="display: none;">
                <p><?= t('no_websites_found') ?></p>
            </div>
        </div>
    </div>
</div>
