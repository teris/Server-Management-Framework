<?php 
// Ensure all variables are always defined at the very beginning
$currentDomain = $currentDomain ?? null;
$domains = $domains ?? [];
$dnsRecords = $dnsRecords ?? [];
$dnssecStatus = $dnssecStatus ?? null;
$dnssecKeys = $dnssecKeys ?? [];
$zoneContent = $zoneContent ?? '';
$translations = $translations ?? [];
?>
<div class="dns-module" id="dns-content">
    <div class="module-header">
        <h2><?php echo $translations['module_title'] ?? 'DNS Verwaltung'; ?></h2>
    </div>

    

    <!-- API Connection Test -->
    <div class="api-test-section">
        <div class="card">
            <div class="card-header">
                <h3>API-Verbindung testen</h3>
            </div>
            <div class="card-body">
                <button type="button" class="btn btn-warning dns-action-btn" data-action="test_api">
                    <i class="bi bi-plug"></i> OVH API-Verbindung testen
                </button>
            </div>
        </div>
    </div>

    <!-- Domain Selection -->
    <div class="domain-selection-section">
        <div class="card">
            <div class="card-header">
                <h3><?= t('domain_selection') ?></h3>
            </div>
            <div class="card-body">
                <button type="button" class="btn btn-primary dns-action-btn" data-action="load_domains">
                    <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                </button>
                
                <?php if (!empty($domains)): ?>
                    <div class="form-group mt-3">
                        <label for="domain-select"><?= t('select_domain') ?></label>
                        <select id="domain-select" class="form-control dns-domain-select">
                            <option value=""><?= t('select_domain') ?></option>
                            <?php foreach ($domains as $domain): ?>
                                <option value="<?php echo htmlspecialchars($domain); ?>" 
                                        <?php echo ($currentDomain === $domain) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($domain); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mt-3">
                        <?= t('no_domains_available') ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
                    <!-- Aktionsergebnis anzeigen -->
    <div id="action-result" style="display: none;"></div>

    <!-- Loading Progress Bar -->
    <div id="dns-progressbar" class="progress mb-3" style="height: 24px; display: none;">
        <div id="dns-progressbar-inner" class="progress-bar progress-bar-striped progress-bar-animated" 
            role="progressbar" style="width: 0%">0%</div>
    </div>
    <?php if ($currentDomain): ?>
        <!-- DNS Records Management -->
        <div class="dns-records-section">
            <div class="card">
                <div class="card-header">
                    <h3><?= t('dns_records') ?> - <?php echo htmlspecialchars($currentDomain); ?></h3>
                    <div class="header-actions">
                        <button type="button" class="btn btn-success dns-action-btn" data-action="show_add_form">
                            <i class="bi bi-plus"></i> <?= t('add_record') ?>
                        </button>
                        <button type="button" class="btn btn-info dns-action-btn" data-action="refresh_zone">
                            <i class="bi bi-arrow-clockwise"></i> <?= t('refresh_zone') ?>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="dns-records-table-container">
                        <table id="dns-records-table" class="table table-striped">
                            <thead>
                                <tr>
                                    <th><?= t('record_type') ?></th>
                                    <th><?= t('subdomain') ?></th>
                                    <th><?= t('target') ?></th>
                                    <th><?= t('ttl') ?></th>
                                    <th><?= t('priority') ?></th>
                                    <th><?= t('actions') ?></th>
                                </tr>
                            </thead>
                            <tbody id="dns-records-tbody">
                                <?php if (!empty($dnsRecords)): ?>
                                    <?php foreach ($dnsRecords as $record): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($record['fieldType'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($record['subDomain'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($record['target'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($record['ttl'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($record['priority'] ?? '-'); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary dns-action-btn" data-action="edit_record" data-record-id="<?php echo htmlspecialchars($record['id']); ?>" data-record-type="<?php echo htmlspecialchars($record['fieldType']); ?>" data-subdomain="<?php echo htmlspecialchars($record['subDomain'] ?? ''); ?>" data-target="<?php echo htmlspecialchars($record['target']); ?>" data-ttl="<?php echo htmlspecialchars($record['ttl']); ?>" data-priority="<?php echo htmlspecialchars($record['priority'] ?? ''); ?>">
                                                    <i class="bi bi-pencil"></i> <?= t('edit') ?>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger dns-action-btn" data-action="delete_record" data-record-id="<?php echo htmlspecialchars($record['id']); ?>">
                                                    <i class="bi bi-trash"></i> <?= t('delete') ?>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; color: #666;">
                                            <?= t('no_records_found') ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Zone Management -->
        <div class="zone-management-section">
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h4><?= t('export_zone') ?></h4>
                        </div>
                        <div class="card-body">
                            <button type="button" class="btn btn-outline-primary dns-action-btn" data-action="export_zone">
                                <i class="bi bi-download"></i> <?php echo $translations['export_zone'] ?? 'Zone exportieren'; ?>
                            </button>
                            <?php if (!empty($zoneContent)): ?>
                                <div class="mt-3">
                                    <textarea class="form-control" rows="10" readonly><?php echo htmlspecialchars($zoneContent); ?></textarea>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h4><?php echo $translations['import_zone'] ?? 'Zone importieren'; ?></h4>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="zone-import-text"><?php echo $translations['zone_content'] ?? 'Zone-Inhalt'; ?></label>
                                <textarea id="zone-import-text" class="form-control" rows="10" placeholder="<?php echo $translations['paste_zone_content'] ?? 'Zone-Inhalt hier einfügen...'; ?>"></textarea>
                            </div>
                            <button type="button" class="btn btn-outline-success dns-action-btn" data-action="import_zone">
                                <i class="bi bi-upload"></i> <?php echo $translations['import_zone'] ?? 'Zone importieren'; ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- DNSSEC Management -->
        <div class="dnssec-section">
            <div class="card">
                <div class="card-header">
                    <h3><?php echo $translations['dnssec_status'] ?? 'DNSSEC-Status'; ?></h3>
                </div>
                <div class="card-body">
                    <div id="dnssec-status-container">
                        <div class="dnssec-status-info">
                            <span id="dnssec-status-text">
                                <?php if ($dnssecStatus): ?>
                                    <?php if (isset($dnssecStatus['status']) && $dnssecStatus['status'] === 'ENABLED'): ?>
                                        <?php echo $translations['dnssec_enabled_status'] ?? 'DNSSEC aktiviert'; ?>
                                    <?php else: ?>
                                        <?php echo $translations['dnssec_disabled_status'] ?? 'DNSSEC deaktiviert'; ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php echo $translations['dnssec_not_available'] ?? 'DNSSEC nicht verfügbar'; ?>
                                <?php endif; ?>
                            </span>
                            <div class="dnssec-actions">
                                <?php if (!$dnssecStatus || (isset($dnssecStatus['status']) && $dnssecStatus['status'] !== 'ENABLED')): ?>
                                    <button type="button" class="btn btn-success dns-action-btn" data-action="enable_dnssec">
                                        <i class="bi bi-shield-check"></i> <?php echo $translations['enable_dnssec'] ?? 'DNSSEC aktivieren'; ?>
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-danger dns-action-btn" data-action="disable_dnssec">
                                        <i class="bi bi-shield-x"></i> <?php echo $translations['disable_dnssec'] ?? 'DNSSEC deaktivieren'; ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($dnssecStatus && isset($dnssecStatus['status']) && $dnssecStatus['status'] === 'ENABLED'): ?>
                        <div id="dnssec-keys-container">
                            <h4><?php echo $translations['dnssec_keys'] ?? 'DNSSEC-Schlüssel'; ?></h4>
                            <button type="button" class="btn btn-primary mb-3 dns-action-btn" data-action="show_add_dnssec_key_form">
                                <i class="bi bi-key"></i> <?php echo $translations['add_key'] ?? 'Schlüssel hinzufügen'; ?>
                            </button>
                            <table id="dnssec-keys-table" class="table table-striped">
                                <thead>
                                    <tr>
                                        <th><?php echo $translations['key_id'] ?? 'ID'; ?></th>
                                        <th><?php echo $translations['key_type'] ?? 'Schlüssel-Typ'; ?></th>
                                        <th><?php echo $translations['algorithm'] ?? 'Algorithmus'; ?></th>
                                        <th><?php echo $translations['key_size'] ?? 'Schlüssel-Größe'; ?></th>
                                        <th><?php echo $translations['actions'] ?? 'Aktionen'; ?></th>
                                    </tr>
                                </thead>
                                <tbody id="dnssec-keys-tbody">
                                    <?php if (!empty($dnssecKeys)): ?>
                                        <?php foreach ($dnssecKeys as $key): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($key['id'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($key['keyType'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($key['algorithm'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($key['keySize'] ?? ''); ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-danger dns-action-btn" data-action="delete_dnssec_key" data-key-id="<?php echo htmlspecialchars($key['id']); ?>">
                                                        <i class="bi bi-trash"></i> <?php echo $translations['delete'] ?? 'Löschen'; ?>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" style="text-align: center; color: #666;">
                                                <?php echo $translations['no_dnssec_keys_found'] ?? 'Keine DNSSEC-Schlüssel gefunden'; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>


</div>

<style>

</style>

<script src="module/dns/assets/module.js"></script> 