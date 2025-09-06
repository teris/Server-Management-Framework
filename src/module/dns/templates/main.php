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
                
                <div class="form-group mt-3">
                    <label for="domain-select"><?= t('select_domain') ?></label>
                    <select id="domain-select" class="form-control dns-domain-select">
                        <option value=""><?= t('select_domain') ?></option>
                        <?php if (!empty($domains)): ?>
                            <?php foreach ($domains as $domain): ?>
                                <option value="<?php echo htmlspecialchars($domain); ?>" 
                                        <?php echo ($currentDomain === $domain) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($domain); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
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

    <!-- Dynamic Forms Container - Always at the top -->
    <div id="dns-forms-container" style="display: none;">
        <!-- Forms will be inserted here dynamically -->
    </div>

    <!-- DNS Records Management -->
    <div class="dns-records-section">
        <div class="card">
            <div class="card-header">
                <h3><?= t('dns_records') ?> <?php if ($currentDomain): ?>- <?php echo htmlspecialchars($currentDomain); ?><?php endif; ?></h3>
                <div class="header-actions" id="dns-header-actions" style="display: none;">
                    <button type="button" class="btn btn-success dns-action-btn" data-action="show_add_form">
                        <i class="bi bi-plus-circle"></i> <?= t('add_record') ?>
                    </button>
                    <button type="button" class="btn btn-info dns-action-btn" data-action="refresh_zone">
                        <i class="bi bi-arrow-clockwise"></i> <?= t('refresh_zone') ?>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (!$currentDomain): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Bitte wählen Sie zuerst eine Domain aus, um DNS-Records zu verwalten.
                    </div>
                <?php else: ?>
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
                                        <tr data-record-id="<?php echo htmlspecialchars($record['id']); ?>">
                                            <td class="editable" data-field="fieldType">
                                                <span class="display-value"><?php echo htmlspecialchars($record['fieldType'] ?? ''); ?></span>
                                                <select class="form-control edit-input" style="display: none;">
                                                    <option value="A" <?php echo ($record['fieldType'] === 'A') ? 'selected' : ''; ?>>A</option>
                                                    <option value="AAAA" <?php echo ($record['fieldType'] === 'AAAA') ? 'selected' : ''; ?>>AAAA</option>
                                                    <option value="CNAME" <?php echo ($record['fieldType'] === 'CNAME') ? 'selected' : ''; ?>>CNAME</option>
                                                    <option value="MX" <?php echo ($record['fieldType'] === 'MX') ? 'selected' : ''; ?>>MX</option>
                                                    <option value="NS" <?php echo ($record['fieldType'] === 'NS') ? 'selected' : ''; ?>>NS</option>
                                                    <option value="PTR" <?php echo ($record['fieldType'] === 'PTR') ? 'selected' : ''; ?>>PTR</option>
                                                    <option value="SRV" <?php echo ($record['fieldType'] === 'SRV') ? 'selected' : ''; ?>>SRV</option>
                                                    <option value="TXT" <?php echo ($record['fieldType'] === 'TXT') ? 'selected' : ''; ?>>TXT</option>
                                                    <option value="CAA" <?php echo ($record['fieldType'] === 'CAA') ? 'selected' : ''; ?>>CAA</option>
                                                </select>
                                            </td>
                                            <td class="editable" data-field="subDomain">
                                                <span class="display-value"><?php echo htmlspecialchars($record['subDomain'] ?? '-'); ?></span>
                                                <input type="text" class="form-control edit-input" value="<?php echo htmlspecialchars($record['subDomain'] ?? ''); ?>" style="display: none;">
                                            </td>
                                            <td class="editable" data-field="target">
                                                <span class="display-value"><?php echo htmlspecialchars($record['target'] ?? ''); ?></span>
                                                <input type="text" class="form-control edit-input" value="<?php echo htmlspecialchars($record['target'] ?? ''); ?>" style="display: none;">
                                            </td>
                                            <td class="editable" data-field="ttl">
                                                <span class="display-value"><?php echo htmlspecialchars($record['ttl'] ?? ''); ?></span>
                                                <input type="number" class="form-control edit-input" value="<?php echo htmlspecialchars($record['ttl'] ?? '3600'); ?>" min="60" max="86400" style="display: none;">
                                            </td>
                                            <td class="editable" data-field="priority">
                                                <span class="display-value"><?php echo htmlspecialchars($record['priority'] ?? '-'); ?></span>
                                                <input type="number" class="form-control edit-input" value="<?php echo htmlspecialchars($record['priority'] ?? ''); ?>" min="0" max="65535" style="display: none;">
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button type="button" class="btn btn-sm btn-primary edit-btn" data-record-id="<?php echo htmlspecialchars($record['id']); ?>">
                                                        <i class="bi bi-pencil"></i> Bearbeiten
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-success save-btn" data-record-id="<?php echo htmlspecialchars($record['id']); ?>" style="display: none;">
                                                        <i class="bi bi-check"></i> Speichern
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-secondary cancel-btn" data-record-id="<?php echo htmlspecialchars($record['id']); ?>" style="display: none;">
                                                        <i class="bi bi-x"></i> Abbrechen
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger dns-action-btn" data-action="delete_record" data-record-id="<?php echo htmlspecialchars($record['id']); ?>">
                                                        <i class="bi bi-trash"></i> <?= t('delete') ?>
                                                    </button>
                                                </div>
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
                <?php endif; ?>
            </div>
        </div>
    </div>

        <!-- Zone Management -->
        <div class="zone-management-section" id="zone-management-section" style="display: none;">
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


</div>

<link rel="stylesheet" href="module/dns/assets/style.css">
<script src="module/dns/assets/module.js"></script>