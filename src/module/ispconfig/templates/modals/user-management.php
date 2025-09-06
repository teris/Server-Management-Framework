<?php
/**
 * User-Management Modal
 * Enthält das Modal für Benutzer-Details und Service-Übersicht
 */
?>

<!-- Benutzer-Details Modal -->
<div id="user-details-modal" class="modal" style="display: none;">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3 id="user-details-title"><?= t('user_details') ?></h3>
            <span class="close" onclick="closeUserModal()">&times;</span>
        </div>
        <div class="modal-body">
            <!-- Benutzer-Tabs -->
            <div class="user-tabs mb-3">
                <button class="tab-button active" onclick="switchUserTab('websites')" id="user-tab-websites">
                    🌐 <?= t('websites') ?>
                </button>
                <button class="tab-button" onclick="switchUserTab('emails')" id="user-tab-emails">
                    📧 <?= t('email_accounts') ?>
                </button>
                <button class="tab-button" onclick="switchUserTab('databases')" id="user-tab-databases">
                    🗄️ <?= t('databases') ?>
                </button>
                <button class="tab-button" onclick="switchUserTab('ftp')" id="user-tab-ftp">
                    📁 <?= t('ftp_users') ?>
                </button>
            </div>
            
            <!-- Websites Tab -->
            <div id="user-websites-tab" class="user-tab-content active">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><?= t('domain') ?></th>
                                <th><?= t('ip_address') ?></th>
                                <th><?= t('hd_quota') ?></th>
                                <th><?= t('traffic_quota') ?></th>
                                <th><?= t('ssl') ?></th>
                                <th><?= t('status') ?></th>
                            </tr>
                        </thead>
                        <tbody id="user-websites-tbody">
                            <!-- Wird dynamisch geladen -->
                        </tbody>
                    </table>
                </div>
                <div id="user-websites-loading" class="text-center" style="display: none;">
                    <div class="spinner-border" role="status">
                        <span class="sr-only"><?= t('loading') ?></span>
                    </div>
                    <p><?= t('loading_websites') ?></p>
                </div>
            </div>
            
            <!-- Email Tab -->
            <div id="user-emails-tab" class="user-tab-content">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><?= t('email') ?></th>
                                <th><?= t('quota') ?></th>
                                <th><?= t('redirect') ?></th>
                                <th><?= t('status') ?></th>
                            </tr>
                        </thead>
                        <tbody id="user-emails-tbody">
                            <!-- Wird dynamisch geladen -->
                        </tbody>
                    </table>
                </div>
                <div id="user-emails-loading" class="text-center" style="display: none;">
                    <div class="spinner-border" role="status">
                        <span class="sr-only"><?= t('loading') ?></span>
                    </div>
                    <p><?= t('loading_email_accounts') ?></p>
                </div>
            </div>
            
            <!-- Databases Tab -->
            <div id="user-databases-tab" class="user-tab-content">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><?= t('database_name') ?></th>
                                <th><?= t('database_user') ?></th>
                                <th><?= t('status') ?></th>
                            </tr>
                        </thead>
                        <tbody id="user-databases-tbody">
                            <!-- Wird dynamisch geladen -->
                        </tbody>
                    </table>
                </div>
                <div id="user-databases-loading" class="text-center" style="display: none;">
                    <div class="spinner-border" role="status">
                        <span class="sr-only"><?= t('loading') ?></span>
                    </div>
                    <p><?= t('loading_databases') ?></p>
                </div>
            </div>
            
            <!-- FTP Tab -->
            <div id="user-ftp-tab" class="user-tab-content">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><?= t('username') ?></th>
                                <th><?= t('quota_size') ?></th>
                                <th><?= t('directory') ?></th>
                                <th><?= t('status') ?></th>
                            </tr>
                        </thead>
                        <tbody id="user-ftp-tbody">
                            <!-- Wird dynamisch geladen -->
                        </tbody>
                    </table>
                </div>
                <div id="user-ftp-loading" class="text-center" style="display: none;">
                    <div class="spinner-border" role="status">
                        <span class="sr-only"><?= t('loading') ?></span>
                    </div>
                    <p><?= t('loading_ftp_users') ?></p>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeUserModal()">
                <?= t('close') ?>
            </button>
        </div>
    </div>
</div>
