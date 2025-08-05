<?php

$serviceManager = new ServiceManager();

// Benutzerdaten abrufen
$adminUsers = $adminCore->getUsers();
$ogpUsers = $serviceManager->getOGPUsers();
$proxmoxUsers = $serviceManager->getProxmoxUsers();
$ispconfigClients = $serviceManager->getISPConfigClients();

// Fehlerbehandlung für API-Aufrufe
$apiErrors = [];

if (!is_array($ogpUsers)) {
    $apiErrors['ogp'] = $ogpUsers;
    $ogpUsers = [];
}
if (!is_array($proxmoxUsers)) {
    $apiErrors['proxmox'] = $proxmoxUsers;
    $proxmoxUsers = [];
}
if (!is_array($ispconfigClients)) {
    $apiErrors['ispconfig'] = $ispconfigClients;
    $ispconfigClients = [];
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="userTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="create-tab" data-bs-toggle="tab" data-bs-target="#create" type="button" role="tab">
                                <i class="bi bi-person-plus"></i> <?= t('create_user') ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="list-tab" data-bs-toggle="tab" data-bs-target="#list" type="button" role="tab">
                                <i class="bi bi-people"></i> <?= t('user_list') ?>
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="userTabsContent">
                        <!-- Benutzer erstellen Tab -->
                        <div class="tab-pane fade show active" id="create" role="tabpanel">
                            <form id="createUserForm" method="post">
                                <!-- Grundlegende Benutzerdaten -->
                                 <!-- System-Auswahl -->
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="bi bi-gear"></i> <?= t('system_selection') ?></h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-3 mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="system_ogp" name="systems[]" value="ogp">
                                                    <label class="form-check-label" for="system_ogp">
                                                        <i class="bi bi-controller"></i> <?= t('opengamepanel') ?>
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="system_ispconfig" name="systems[]" value="ispconfig">
                                                    <label class="form-check-label" for="system_ispconfig">
                                                        <i class="bi bi-globe"></i> ISPConfig
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="system_proxmox" name="systems[]" value="proxmox">
                                                    <label class="form-check-label" for="system_proxmox">
                                                        <i class="bi bi-server"></i> Proxmox
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="bi bi-person"></i> <?= t('basic_user_data') ?></h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="username" class="form-label"><?= t('username') ?> *</label>
                                                <input type="text" class="form-control" id="username" name="username" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="email" class="form-label"><?= t('email') ?> *</label>
                                                <input type="email" class="form-control" id="email" name="email" required>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="first_name" class="form-label"><?= t('first_name') ?> *</label>
                                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="last_name" class="form-label"><?= t('last_name') ?> *</label>
                                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="password" class="form-label"><?= t('password') ?> *</label>
                                                <input type="password" class="form-control" id="password" name="password" required minlength="8">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="password_confirm" class="form-label"><?= t('password_confirm') ?> *</label>
                                                <input type="password" class="form-control" id="password_confirm" name="password_confirm" required minlength="8">
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-12 mb-3">
                                                <label for="notes" class="form-label"><?= t('notes') ?></label>
                                                <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="admin_role" class="form-label"><?= t('role') ?> *</label>
                                            <select class="form-select" id="admin_role" name="admin_role" required>
                                                <option value="user"><?= t('user') ?></option>
                                                <option value="admin"><?= t('admin') ?></option>
                                                <option value="moderator"><?= t('moderator') ?></option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="admin_group" class="form-label"><?= t('group') ?></label>
                                            <select class="form-select" id="admin_group" name="admin_group">
                                                <option value=""><?= t('select_group') ?></option>
                                                <!-- Wird dynamisch gefüllt -->
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- OGP spezifische Felder -->
                                <div id="ogp-fields" class="system-fields" style="display: none;">
                                    <div class="card mb-3">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="bi bi-controller"></i> <?= t('opengamepanel') ?> <?= t('settings') ?></h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="alert alert-info">
                                                <i class="bi bi-info-circle"></i> 
                                                <strong><?= t('ogp_info') ?>:</strong> 
                                                <?= t('ogp_user_info') ?>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="ogp_expiration" class="form-label"><?= t('expiration_date') ?></label>
                                                    <input type="datetime-local" class="form-control" id="ogp_expiration" name="ogp_expiration">
                                                    <small class="form-text text-muted"><?= t('ogp_expiration_help') ?></small>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="ogp_home_id" class="form-label"><?= t('home_id') ?></label>
                                                    <input type="number" class="form-control" id="ogp_home_id" name="ogp_home_id" min="1">
                                                    <small class="form-text text-muted"><?= t('ogp_home_id_help') ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Proxmox spezifische Felder -->
                                <div id="proxmox-fields" class="system-fields" style="display: none;">
                                    <div class="card mb-3">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="bi bi-server"></i> Proxmox <?= t('settings') ?></h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="alert alert-info">
                                                <i class="bi bi-info-circle"></i> 
                                                <strong><?= t('proxmox_info') ?>:</strong> 
                                                <?= t('proxmox_user_info') ?>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="proxmox_realm" class="form-label"><?= t('realm') ?> *</label>
                                                    <select class="form-select" id="proxmox_realm" name="proxmox_realm">
                                                        <option value=""><?= t('select_realm') ?></option>
                                                        <option value="pam">PAM (Linux)</option>
                                                        <option value="pve">PVE (Proxmox VE)</option>
                                                        <option value="pbs">PBS (Proxmox Backup Server)</option>
                                                    </select>
                                                    <small class="form-text text-muted"><?= t('proxmox_realm_help') ?></small>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="proxmox_comment" class="form-label"><?= t('comment') ?></label>
                                                    <input type="text" class="form-control" id="proxmox_comment" name="proxmox_comment">
                                                    <small class="form-text text-muted"><?= t('proxmox_comment_help') ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- ISPConfig spezifische Felder -->
                                <div id="ispconfig-fields" class="system-fields" style="display: none;">
                                    <div class="card mb-3">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="bi bi-globe"></i> ISPConfig <?= t('settings') ?></h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="alert alert-info">
                                                <i class="bi bi-info-circle"></i> 
                                                <strong><?= t('ispconfig_info') ?>:</strong> 
                                                <?= t('ispconfig_user_info') ?>
                                            </div>
                                            
                                            <!-- Kontaktdaten -->
                                            <h6 class="mt-3 mb-2 text-muted"><?= t('contact_information') ?></h6>
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="ispconfig_company_name" class="form-label"><?= t('company_name') ?></label>
                                                    <input type="text" class="form-control" id="ispconfig_company_name" name="ispconfig_company_name">
                                                </div>
                                            </div>
                                            
                                            <!-- Adresse -->
                                            <h6 class="mt-3 mb-2 text-muted"><?= t('address') ?></h6>
                                            <div class="row">
                                                <div class="col-12 mb-3">
                                                    <label for="ispconfig_street" class="form-label"><?= t('street') ?> *</label>
                                                    <input type="text" class="form-control" id="ispconfig_street" name="ispconfig_street" required>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-3 mb-3">
                                                    <label for="ispconfig_zip" class="form-label"><?= t('zip_code') ?> *</label>
                                                    <input type="text" class="form-control" id="ispconfig_zip" name="ispconfig_zip" required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="ispconfig_city" class="form-label"><?= t('city') ?> *</label>
                                                    <input type="text" class="form-control" id="ispconfig_city" name="ispconfig_city" required>
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label for="ispconfig_state" class="form-label"><?= t('state') ?> *</label>
                                                    <input type="text" class="form-control" id="ispconfig_state" name="ispconfig_state" required>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="ispconfig_country" class="form-label"><?= t('country') ?> *</label>
                                                    <input type="text" class="form-control" id="ispconfig_country" name="ispconfig_country" required>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="ispconfig_language" class="form-label"><?= t('language') ?> *</label>
                                                    <select class="form-select" id="ispconfig_language" name="ispconfig_language" required>
                                                        <option value=""><?= t('select_language') ?></option>
                                                        <option value="de">Deutsch</option>
                                                        <option value="en">English</option>
                                                        <option value="fr">Français</option>
                                                        <option value="es">Español</option>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <!-- Kontakt -->
                                            <h6 class="mt-3 mb-2 text-muted"><?= t('contact_details') ?></h6>
                                            <div class="row">
                                                <div class="col-md-4 mb-3">
                                                    <label for="ispconfig_telephone" class="form-label"><?= t('telephone') ?></label>
                                                    <input type="tel" class="form-control" id="ispconfig_telephone" name="ispconfig_telephone">
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label for="ispconfig_mobile" class="form-label"><?= t('mobile') ?></label>
                                                    <input type="tel" class="form-control" id="ispconfig_mobile" name="ispconfig_mobile">
                                                </div>
                                                <div class="col-md-4 mb-3">
                                                    <label for="ispconfig_fax" class="form-label"><?= t('fax') ?></label>
                                                    <input type="tel" class="form-control" id="ispconfig_fax" name="ispconfig_fax">
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="ispconfig_internet" class="form-label"><?= t('website') ?></label>
                                                    <input type="url" class="form-control" id="ispconfig_internet" name="ispconfig_internet">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="ispconfig_notes" class="form-label"><?= t('notes') ?></label>
                                                    <textarea class="form-control" id="ispconfig_notes" name="ispconfig_notes" rows="2"></textarea>
                                                </div>
                                            </div>
                                            
                                            <!-- Server-Einstellungen -->
                                            <h6 class="mt-3 mb-2 text-muted"><?= t('server_settings') ?></h6>
                                            <div class="row">
                                                <div class="col-md-3 mb-3">
                                                    <label for="ispconfig_default_mailserver" class="form-label"><?= t('default_mailserver') ?> *</label>
                                                    <input type="number" class="form-control" id="ispconfig_default_mailserver" name="ispconfig_default_mailserver" value="1" required>
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label for="ispconfig_default_webserver" class="form-label"><?= t('default_webserver') ?> *</label>
                                                    <input type="number" class="form-control" id="ispconfig_default_webserver" name="ispconfig_default_webserver" value="1" required>
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label for="ispconfig_default_dnsserver" class="form-label"><?= t('default_dnsserver') ?> *</label>
                                                    <input type="number" class="form-control" id="ispconfig_default_dnsserver" name="ispconfig_default_dnsserver" value="1" required>
                                                </div>
                                                <div class="col-md-3 mb-3">
                                                    <label for="ispconfig_limit_client" class="form-label"><?= t('limit_client') ?></label>
                                                    <input type="number" class="form-control" id="ispconfig_limit_client" name="ispconfig_limit_client" value="0" min="0">
                                                    <small class="form-text text-muted"><?= t('limit_client_help') ?></small>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="ispconfig_web_php_options" class="form-label"><?= t('web_php_options') ?> *</label>
                                                    <select class="form-select" id="ispconfig_web_php_options" name="ispconfig_web_php_options" required>
                                                        <option value="no"><?= t('no_php') ?></option>
                                                        <option value="fast-cgi">Fast-CGI</option>
                                                        <option value="cgi">CGI</option>
                                                        <option value="mod">mod_php</option>
                                                        <option value="suphp">suPHP</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="ispconfig_ssh_chroot" class="form-label"><?= t('ssh_chroot') ?> *</label>
                                                    <select class="form-select" id="ispconfig_ssh_chroot" name="ispconfig_ssh_chroot" required>
                                                        <option value="no"><?= t('no_chroot') ?></option>
                                                        <option value="jailkit">Jailkit</option>
                                                        <option value="ssh-chroot">SSH Chroot</option>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="ispconfig_usertheme" class="form-label"><?= t('user_theme') ?> *</label>
                                                    <select class="form-select" id="ispconfig_usertheme" name="ispconfig_usertheme" required>
                                                        <option value="default">Default</option>
                                                        <option value="dark">Dark</option>
                                                        <option value="light">Light</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-person-plus"></i> <?= t('create_user') ?>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Benutzerliste Tab -->
                        <div class="tab-pane fade" id="list" role="tabpanel">
                            <!-- API Fehler anzeigen -->
                            <?php if (!empty($apiErrors)): ?>
                                <div class="alert alert-warning">
                                    <h6><i class="bi bi-exclamation-triangle"></i> <?= t('api_connection_issues') ?></h6>
                                    <ul class="mb-0">
                                        <?php foreach ($apiErrors as $system => $error): ?>
                                            <li><strong><?= strtoupper($system) ?>:</strong> <?= htmlspecialchars($error) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <!-- Admin Dashboard Benutzer -->
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="bi bi-speedometer2"></i> <?= t('admin_dashboard') ?> <?= t('users') ?></h6>
                                    <button class="btn btn-sm btn-outline-primary" onclick="refreshUserList('admin')">
                                        <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                                    </button>
                                </div>
                                <div class="card-body">
                                    <?php if ($adminUsers['success']): ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover">
                                                <thead>
                                                    <tr>
                                                        <th><?= t('username') ?></th>
                                                        <th><?= t('full_name') ?></th>
                                                        <th><?= t('email') ?></th>
                                                        <th><?= t('role') ?></th>
                                                        <th><?= t('status') ?></th>
                                                        <th><?= t('actions') ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($adminUsers['data'] as $user): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($user['username']) ?></td>
                                                            <td><?= htmlspecialchars($user['full_name']) ?></td>
                                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                                            <td>
                                                                <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'moderator' ? 'warning' : 'primary') ?>">
                                                                    <?= htmlspecialchars($user['role']) ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-<?= $user['active'] === 'y' ? 'success' : 'secondary' ?>">
                                                                    <?= $user['active'] === 'y' ? t('active') : t('inactive') ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <button class="btn btn-sm btn-outline-primary" onclick="editUser('admin', <?= $user['id'] ?>)">
                                                                    <i class="bi bi-pencil"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteUser('admin', <?= $user['id'] ?>)">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-danger">
                                            <i class="bi bi-exclamation-triangle"></i> <?= t('error_loading_users') ?>: <?= htmlspecialchars($adminUsers['error']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- OGP Benutzer -->
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="bi bi-controller"></i> <?= t('opengamepanel') ?> <?= t('users') ?></h6>
                                    <button class="btn btn-sm btn-outline-primary" onclick="refreshUserList('ogp')">
                                        <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                                    </button>
                                </div>
                                <div class="card-body">
                                    <?php if (is_array($ogpUsers) && isset($ogpUsers['message']) && is_array($ogpUsers['message']) && count($ogpUsers['message']) > 0): ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover">
                                                <thead>
                                                    <tr>
                                                        <th><?= t('email') ?></th>
                                                        <th><?= t('username') ?></th>
                                                        <th><?= t('expiration') ?></th>
                                                        <th><?= t('actions') ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($ogpUsers['message'] as $user): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($user['users_email'] ?? '') ?></td>
                                                            <td><?= htmlspecialchars($user['users_login'] ?? '') ?></td>
                                                            <td>
                                                                <?php if (isset($user['user_expires']) && $user['user_expires'] && $user['user_expires'] !== 'X'): ?>
                                                                    <span class="badge bg-warning">
                                                                        <?= date('d.m.Y H:i', strtotime($user['user_expires'])) ?>
                                                                    </span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-success"><?= t('unlimited') ?></span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <button class="btn btn-sm btn-outline-primary" onclick="editUser('ogp', '<?= htmlspecialchars($user['users_email'] ?? '') ?>')">
                                                                    <i class="bi bi-pencil"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteUser('ogp', '<?= htmlspecialchars($user['users_email'] ?? '') ?>')">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle"></i> <?= t('no_ogp_users_found') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Proxmox Benutzer -->
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="bi bi-server"></i> Proxmox <?= t('users') ?></h6>
                                    <button class="btn btn-sm btn-outline-primary" onclick="refreshUserList('proxmox')">
                                        <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                                    </button>
                                </div>
                                <div class="card-body">
                                    <?php if (is_array($proxmoxUsers) && isset($proxmoxUsers['data']) && is_array($proxmoxUsers['data']) && count($proxmoxUsers['data']) > 0): ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover">
                                                <thead>
                                                    <tr>
                                                        <th><?= t('userid') ?></th>
                                                        <th><?= t('Realm') ?></th>
                                                        <th><?= t('email') ?></th>
                                                        <th><?= t('actions') ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($proxmoxUsers['data'] as $user): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($user['userid'] ?? '') ?></td>
                                                            <td><?= htmlspecialchars($user['realm-type'] ?? '') ?></td>
                                                            <td><?= htmlspecialchars($user['email'] ?? '') ?></td>
                                                            <td>
                                                                <button class="btn btn-sm btn-outline-primary" onclick="editUser('proxmox', '<?= htmlspecialchars($user['userid'] ?? '') ?>')">
                                                                    <i class="bi bi-pencil"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteUser('proxmox', '<?= htmlspecialchars($user['userid'] ?? '') ?>')">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle"></i> <?= t('no_proxmox_users_found') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- ISPConfig Clients -->
                            <div class="card mb-4">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="bi bi-globe"></i> ISPConfig <?= t('clients') ?></h6>
                                    <button class="btn btn-sm btn-outline-primary" onclick="refreshUserList('ispconfig')">
                                        <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                                    </button>
                                </div>
                                <div class="card-body">
                                    <?php if (is_array($ispconfigClients) && !empty($ispconfigClients)): ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped table-hover">
                                                <thead>
                                                    <tr>
                                                        <th><?= t('username') ?></th>
                                                        <th><?= t('company_name') ?></th>
                                                        <th><?= t('contact_name') ?></th>
                                                        <th><?= t('email') ?></th>
                                                        <th><?= t('status') ?></th>
                                                        <th><?= t('actions') ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($ispconfigClients as $client): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($client['username'] ?? '') ?></td>
                                                            <td><?= htmlspecialchars($client['company_name'] ?? '') ?></td>
                                                            <td><?= htmlspecialchars($client['contact_name'] ?? '') ?></td>
                                                            <td><?= htmlspecialchars($client['email'] ?? '') ?></td>
                                                            <td>
                                                                <span class="badge bg-<?= ($client['locked'] ?? '') === 'n' ? 'success' : 'secondary' ?>">
                                                                    <?= ($client['locked'] ?? '') === 'n' ? t('active') : t('inactive') ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <button class="btn btn-sm btn-outline-primary" onclick="editUser('ispconfig', <?= $client['client_id'] ?? 0 ?>)">
                                                                    <i class="bi bi-pencil"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteUser('ispconfig', <?= $client['client_id'] ?? 0 ?>)">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle"></i> <?= t('no_ispconfig_clients_found') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalTitle"><?= t('edit_user') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="editUserModalBody">
                <!-- Wird dynamisch gefüllt -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                <button type="button" class="btn btn-primary" id="saveUserChanges"><?= t('save_changes') ?></button>
            </div>
        </div>
    </div>
</div>

<script>
// System-spezifische Felder ein-/ausblenden
function toggleSystemFields() {
    const systems = document.querySelectorAll('input[name="systems[]"]:checked');
    const systemFields = document.querySelectorAll('.system-fields');
    
    // Alle Felder ausblenden
    systemFields.forEach(field => {
        field.style.display = 'none';
    });
    
    // Ausgewählte Felder anzeigen
    systems.forEach(system => {
        const fieldId = system.value + '-fields';
        const field = document.getElementById(fieldId);
        if (field) {
            field.style.display = 'block';
        }
    });
}

// Admin-Gruppen laden (Platzhalter)
function loadAdminGroups() {
    // TODO: Implementierung für echte Gruppen-Daten
    const groupSelect = document.getElementById('admin_group');
    if (groupSelect) {
        groupSelect.innerHTML = '<option value=""><?= t('select_group') ?></option>';
        // Hier würden echte Gruppen geladen werden
    }
}

// Benutzerliste aktualisieren
function refreshUserList(system) {
    // TODO: AJAX-Implementierung für echte Datenaktualisierung
    showNotification('<?= t('refreshing') ?>...', 'info');
    setTimeout(() => {
        showNotification('<?= t('user_list_updated') ?>', 'success');
    }, 1000);
}

// Benutzer bearbeiten
function editUser(system, userId) {
    // TODO: AJAX-Implementierung für echte Benutzerbearbeitung
    showNotification('<?= t('loading_user_data') ?>...', 'info');
    
    // Modal öffnen
    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    document.getElementById('editUserModalTitle').textContent = `<?= t('edit_user') ?> (${system.toUpperCase()})`;
    document.getElementById('editUserModalBody').innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden"><?= t('loading') ?>...</span>
            </div>
            <p class="mt-2"><?= t('loading_user_data') ?>...</p>
        </div>
    `;
    modal.show();
}

// Benutzer löschen
function deleteUser(system, userId) {
    if (confirm('<?= t('confirm_delete_user') ?>')) {
        // TODO: AJAX-Implementierung für echte Benutzerlöschung
        showNotification('<?= t('deleting_user') ?>...', 'info');
        setTimeout(() => {
            showNotification('<?= t('user_deleted_successfully') ?>', 'success');
        }, 1000);
    }
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    // Admin Dashboard Checkbox immer aktiviert
    const adminCheckbox = document.getElementById('system_admin');
    if (adminCheckbox) {
        adminCheckbox.checked = true;
        adminCheckbox.disabled = true;
        adminCheckbox.style.opacity = '0.6';
    }
    
    // System-Felder initial anzeigen
    toggleSystemFields();
    
    // Admin-Gruppen laden
    loadAdminGroups();
    
    // System-Checkbox Event Listener
    const systemCheckboxes = document.querySelectorAll('input[name="systems[]"]');
    systemCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', toggleSystemFields);
    });
    
    // Passwort-Bestätigung validieren
    const password = document.getElementById('password');
    const passwordConfirm = document.getElementById('password_confirm');
    
    function validatePassword() {
        if (password.value !== passwordConfirm.value) {
            passwordConfirm.setCustomValidity('<?= t('passwords_do_not_match') ?>');
        } else {
            passwordConfirm.setCustomValidity('');
        }
    }
    
    password.addEventListener('input', validatePassword);
    passwordConfirm.addEventListener('input', validatePassword);
    
    // Formular-Submission
    document.getElementById('createUserForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const selectedSystems = formData.getAll('systems[]');
        
        // Validierung
        if (selectedSystems.length === 0) {
            showNotification('<?= t('select_at_least_one_system') ?>', 'error');
            return;
        }
        
        // System-spezifische Validierung
        if (selectedSystems.includes('ogp')) {
            const ogpExpiration = formData.get('ogp_expiration');
            if (ogpExpiration) {
                const expirationDate = new Date(ogpExpiration);
                if (expirationDate <= new Date()) {
                    showNotification('<?= t('ogp_expiration_must_be_future') ?>', 'error');
                    return;
                }
            }
        }
        
        if (selectedSystems.includes('proxmox')) {
            const proxmoxRealm = formData.get('proxmox_realm');
            if (!proxmoxRealm) {
                showNotification('<?= t('proxmox_realm_required') ?>', 'error');
                return;
            }
        }
        
        if (selectedSystems.includes('ispconfig')) {
            const requiredFields = ['ispconfig_street', 'ispconfig_zip', 'ispconfig_city', 'ispconfig_state', 'ispconfig_country', 'ispconfig_language'];
            for (const field of requiredFields) {
                if (!formData.get(field)) {
                    showNotification('<?= t('ispconfig_required_fields_missing') ?>', 'error');
                    return;
                }
            }
        }
        
        // Benutzerdaten sammeln
        const userData = {
            basic: {
                username: formData.get('username'),
                email: formData.get('email'),
                first_name: formData.get('first_name'),
                last_name: formData.get('last_name'),
                password: formData.get('password'),
                notes: formData.get('notes')
            },
            systems: selectedSystems,
            admin: {
                role: formData.get('admin_role'),
                group: formData.get('admin_group')
            },
            ogp: {
                expiration: formData.get('ogp_expiration') || null, // Unbegrenzt wenn leer
                home_id: formData.get('ogp_home_id') || null
            },
            proxmox: {
                realm: formData.get('proxmox_realm'),
                comment: formData.get('proxmox_comment') || '',
                // Verwende die gleichen Grunddaten für alle Systeme
                username: formData.get('username'),
                email: formData.get('email'),
                first_name: formData.get('first_name'),
                last_name: formData.get('last_name'),
                password: formData.get('password')
            },
            ispconfig: {
                company_name: formData.get('ispconfig_company_name') || '',
                street: formData.get('ispconfig_street'),
                zip: formData.get('ispconfig_zip'),
                city: formData.get('ispconfig_city'),
                state: formData.get('ispconfig_state'),
                country: formData.get('ispconfig_country'),
                language: formData.get('ispconfig_language'),
                telephone: formData.get('ispconfig_telephone') || '',
                mobile: formData.get('ispconfig_mobile') || '',
                fax: formData.get('ispconfig_fax') || '',
                internet: formData.get('ispconfig_internet') || '',
                notes: formData.get('ispconfig_notes') || '',
                default_mailserver: formData.get('ispconfig_default_mailserver'),
                default_webserver: formData.get('ispconfig_default_webserver'),
                default_dnsserver: formData.get('ispconfig_default_dnsserver'),
                limit_client: formData.get('ispconfig_limit_client'),
                web_php_options: formData.get('ispconfig_web_php_options'),
                ssh_chroot: formData.get('ispconfig_ssh_chroot'),
                usertheme: formData.get('ispconfig_usertheme'),
                // Verwende die gleichen Grunddaten
                username: formData.get('username'),
                password: formData.get('password'),
                contact_firstname: formData.get('first_name'),
                contact_name: formData.get('last_name'),
                email: formData.get('email')
            }
        };
        
        // TODO: AJAX-Implementierung für echte Benutzererstellung
        console.log('Benutzerdaten:', userData);
        showNotification('<?= t('creating_user') ?>...', 'info');
        
        // Simulierte Verarbeitung
        setTimeout(() => {
            showNotification('<?= t('user_created_successfully') ?>', 'success');
            this.reset();
            
            // Admin Dashboard Checkbox wieder aktivieren
            if (adminCheckbox) {
                adminCheckbox.checked = true;
                adminCheckbox.disabled = true;
                adminCheckbox.style.opacity = '0.6';
            }
            toggleSystemFields();
        }, 2000);
    });
    
    // Modal Save Button
    document.getElementById('saveUserChanges').addEventListener('click', function() {
        // TODO: AJAX-Implementierung für echte Änderungen
        showNotification('<?= t('saving_changes') ?>...', 'info');
        setTimeout(() => {
            showNotification('<?= t('changes_saved_successfully') ?>', 'success');
            bootstrap.Modal.getInstance(document.getElementById('editUserModal')).hide();
        }, 1000);
    });
});
</script> 