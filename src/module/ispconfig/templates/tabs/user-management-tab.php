<?php
/**
 * User Management Tab
 * Enthält den User-Management Bereich
 */
?>

<!-- Users Tab -->
<div id="users-tab" class="tab-content">
    <div class="card">
        <div class="card-header">
            <h2 class="mb-0">👥 <?= t('users_overview') ?></h2>
            <div class="header-actions">
                <button id="refresh-users" class="btn btn-sm btn-secondary">
                    <i class="icon">🔄</i>
                    <?= t('refresh') ?>
                </button>
            </div>
        </div>
        <div class="card-body">
            <!-- Benutzer-Filter -->
            <div class="user-filters mb-3">
                <div class="row">
                    <div class="col-md-4">
                        <input type="text" id="user-search" class="form-control" placeholder="<?= t('search_users') ?>">
                    </div>
                    <div class="col-md-3">
                        <select id="user-status-filter" class="form-control">
                            <option value=""><?= t('all_status') ?></option>
                            <option value="active"><?= t('active') ?></option>
                            <option value="inactive"><?= t('inactive') ?></option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="user-sort-filter" class="form-control">
                            <option value="company_name"><?= t('sort_by_company') ?></option>
                            <option value="contact_name"><?= t('sort_by_contact') ?></option>
                            <option value="email"><?= t('sort_by_email') ?></option>
                            <option value="created_at"><?= t('sort_by_date') ?></option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button id="clear-user-filters" class="btn btn-sm btn-outline-secondary">
                            <?= t('clear_filters') ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Benutzer-Tabelle -->
            <div class="table-responsive">
                <table class="table table-striped" id="users-table">
                    <thead>
                        <tr>
                            <th><?= t('company_name') ?></th>
                            <th><?= t('contact_name') ?></th>
                            <th><?= t('email') ?></th>
                            <th><?= t('websites') ?></th>
                            <th><?= t('emails') ?></th>
                            <th><?= t('databases') ?></th>
                            <th><?= t('ftp_users') ?></th>
                            <th><?= t('status') ?></th>
                            <th><?= t('created_at') ?></th>
                            <th><?= t('actions') ?></th>
                        </tr>
                    </thead>
                    <tbody id="users-tbody">
                        <!-- Wird dynamisch geladen -->
                    </tbody>
                </table>
            </div>
            
            <!-- Loading-Indikator -->
            <div id="users-loading" class="text-center" style="display: none;">
                <div class="spinner-border" role="status">
                    <span class="sr-only"><?= t('loading') ?></span>
                </div>
                <p><?= t('loading_users') ?></p>
            </div>
            
            <!-- Keine Benutzer -->
            <div id="no-users" class="text-center" style="display: none;">
                <p><?= t('no_users_found') ?></p>
            </div>
        </div>
    </div>
</div>
