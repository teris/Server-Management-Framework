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
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-outline-primary" onclick="loadUsers()">
                                            <i class="bi bi-arrow-clockwise"></i> <?= t('refresh') ?>
                                        </button>
                                        <button class="btn btn-sm btn-primary" onclick="showCreateUserModal()">
                                            <i class="bi bi-plus-circle"></i> <?= t('create_user') ?>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <!-- Filter und Suche -->
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <input type="text" class="form-control" id="userSearchInput" placeholder="<?= t('search_users') ?>" onkeyup="debounce(loadUsers, 500)()">
                                        </div>
                                        <div class="col-md-3">
                                            <select class="form-select" id="userStatusFilter" onchange="loadUsers()">
                                                <option value=""><?= t('all_statuses') ?></option>
                                                <option value="active"><?= t('active') ?></option>
                                                <option value="inactive"><?= t('inactive') ?></option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <select class="form-select" id="userRoleFilter" onchange="loadUsers()">
                                                <option value=""><?= t('all_roles') ?></option>
                                                <option value="admin"><?= t('admin') ?></option>
                                                <option value="user"><?= t('user') ?></option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover">
                                            <thead>
                                                <tr>
                                                    <th><?= t('username') ?></th>
                                                    <th><?= t('full_name') ?></th>
                                                    <th><?= t('email') ?></th>
                                                    <th><?= t('role') ?></th>
                                                    <th><?= t('status') ?></th>
                                                    <th><?= t('created') ?></th>
                                                    <th><?= t('actions') ?></th>
                                                </tr>
                                            </thead>
                                            <tbody id="usersTableBody">
                                                <tr>
                                                    <td colspan="7" class="text-center">
                                                        <div class="spinner-border" role="status">
                                                            <span class="visually-hidden"><?= t('loading') ?></span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Pagination -->
                                    <nav aria-label="Users pagination" id="usersPaginationContainer">
                                        <!-- Pagination wird dynamisch geladen -->
                                    </nav>
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

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= t('create_user') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createUserForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="newUsername" class="form-label"><?= t('username') ?> *</label>
                                <input type="text" class="form-control" id="newUsername" name="username" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="newEmail" class="form-label"><?= t('email') ?> *</label>
                                <input type="email" class="form-control" id="newEmail" name="email" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="newFullName" class="form-label"><?= t('full_name') ?> *</label>
                                <input type="text" class="form-control" id="newFullName" name="full_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="newRole" class="form-label"><?= t('role') ?></label>
                                <select class="form-select" id="newRole" name="role">
                                    <option value="user"><?= t('user') ?></option>
                                    <option value="admin"><?= t('admin') ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="newPassword" class="form-label"><?= t('password') ?> *</label>
                                <input type="password" class="form-control" id="newPassword" name="password" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="newPasswordConfirm" class="form-label"><?= t('confirm_password') ?> *</label>
                                <input type="password" class="form-control" id="newPasswordConfirm" name="password_confirm" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="newActive" name="active" checked>
                            <label class="form-check-label" for="newActive">
                                <?= t('active') ?>
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                <button type="button" class="btn btn-primary" onclick="createUser()"><?= t('create') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= t('reset_password') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p><?= t('reset_password_confirm') ?></p>
                <div class="mb-3">
                    <label for="newPasswordReset" class="form-label"><?= t('new_password') ?></label>
                    <input type="text" class="form-control" id="newPasswordReset" readonly>
                </div>
                <div class="mb-3">
                    <button type="button" class="btn btn-outline-secondary" onclick="generateNewPassword()">
                        <i class="bi bi-arrow-clockwise"></i> <?= t('generate_new_password') ?>
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                <button type="button" class="btn btn-primary" onclick="confirmResetPassword()"><?= t('reset_password') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Confirm Action Modal -->
<div class="modal fade" id="confirmActionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmActionTitle"><?= t('confirm_action') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="confirmActionMessage"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                <button type="button" class="btn btn-danger" id="confirmActionButton"><?= t('confirm') ?></button>
            </div>
        </div>
    </div>
</div>

<script>
// Global variables
let currentUserId = null;
let currentUserPage = 1;

// Initialize user management
document.addEventListener('DOMContentLoaded', function() {
    loadUsers();
    bindUserEvents();
});

// Bind user management events
function bindUserEvents() {
    // Password confirmation validation for create user form
    const newPassword = document.getElementById('newPassword');
    const newPasswordConfirm = document.getElementById('newPasswordConfirm');
    
    if (newPassword && newPasswordConfirm) {
        function validateNewPassword() {
            if (newPassword.value !== newPasswordConfirm.value) {
                newPasswordConfirm.setCustomValidity('<?= t('passwords_do_not_match') ?>');
            } else {
                newPasswordConfirm.setCustomValidity('');
            }
        }
        
        newPassword.addEventListener('input', validateNewPassword);
        newPasswordConfirm.addEventListener('input', validateNewPassword);
    }
    
    // Modal events
    const createModal = document.getElementById('createUserModal');
    if (createModal) {
        createModal.addEventListener('hidden.bs.modal', () => {
            document.getElementById('createUserForm')?.reset();
        });
    }
    
    const editModal = document.getElementById('editUserModal');
    if (editModal) {
        editModal.addEventListener('hidden.bs.modal', () => {
            currentUserId = null;
        });
    }
}

// Load users from server
function loadUsers(page = 1) {
    currentUserPage = page;
    const search = document.getElementById('userSearchInput')?.value || '';
    const status = document.getElementById('userStatusFilter')?.value || '';
    const role = document.getElementById('userRoleFilter')?.value || '';
    
    const tbody = document.getElementById('usersTableBody');
    if (tbody) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden"><?= t('loading') ?></span></div></td></tr>';
    }
    
    console.log('Loading users with params:', { page, search, status, role });
    
    $.ajax({
        url: 'index.php',
        method: 'POST',
        data: {
            core: 'admin',
            action: 'get_users',
            page: page,
            search: search,
            status: status,
            role: role
        },
        success: function(response) {
            console.log('Users response:', response);
            if (response.success) {
                renderUsers(response.data.users);
                renderUserPagination(response.data.pagination);
            } else {
                showNotification(response.error || '<?= t('error_loading_users') ?>', 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', { xhr, status, error });
            showNotification('<?= t('network_error') ?>', 'error');
        }
    });
}

// Render users table
function renderUsers(users) {
    const tbody = document.getElementById('usersTableBody');
    if (!tbody) return;
    
    if (users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted"><?= t('no_users_found') ?></td></tr>';
        return;
    }
    
    tbody.innerHTML = users.map(user => `
        <tr class="fade-in">
            <td><strong>${escapeHtml(user.username)}</strong></td>
            <td>${escapeHtml(user.full_name)}</td>
            <td>${escapeHtml(user.email)}</td>
            <td>
                <span class="badge bg-${user.role === 'admin' ? 'danger' : 'primary'}">${user.role}</span>
            </td>
            <td>
                <span class="badge bg-${user.active === 'y' ? 'success' : 'secondary'}">
                    ${user.active === 'y' ? '<?= t('active') ?>' : '<?= t('inactive') ?>'}
                </span>
            </td>
            <td>
                <small>${formatDate(user.created_at)}</small>
            </td>
            <td>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-primary" onclick="editUser(${user.id})" title="<?= t('edit_user') ?>">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button type="button" class="btn btn-outline-warning" onclick="toggleUserStatus(${user.id})" title="${user.active === 'y' ? '<?= t('suspend_user') ?>' : '<?= t('activate_user') ?>'}">
                        <i class="bi bi-${user.active === 'y' ? 'pause' : 'play'}"></i>
                    </button>
                    <button type="button" class="btn btn-outline-info" onclick="resetUserPassword(${user.id})" title="<?= t('reset_password') ?>">
                        <i class="bi bi-key"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="deleteUser(${user.id})" title="<?= t('delete_user') ?>">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// Render user pagination
function renderUserPagination(pagination) {
    const container = document.getElementById('usersPaginationContainer');
    if (!container) return;
    
    if (pagination.pages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let html = '<ul class="pagination justify-content-center">';
    
    // Previous button
    if (pagination.page > 1) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="loadUsers(${pagination.page - 1})"><?= t('previous') ?></a></li>`;
    }
    
    // Page numbers
    for (let i = 1; i <= pagination.pages; i++) {
        if (i === pagination.page) {
            html += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
        } else {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="loadUsers(${i})">${i}</a></li>`;
        }
    }
    
    // Next button
    if (pagination.page < pagination.pages) {
        html += `<li class="page-item"><a class="page-link" href="#" onclick="loadUsers(${pagination.page + 1})"><?= t('next') ?></a></li>`;
    }
    
    html += '</ul>';
    container.innerHTML = html;
}

// Show create user modal
function showCreateUserModal() {
    $('#createUserModal').modal('show');
}

// Create user
function createUser() {
    const form = document.getElementById('createUserForm');
    const formData = new FormData(form);
    
    // Validate password confirmation
    if (formData.get('password') !== formData.get('password_confirm')) {
        showNotification('<?= t('passwords_do_not_match') ?>', 'error');
        return;
    }
    
    $.ajax({
        url: 'index.php',
        method: 'POST',
        data: {
            core: 'admin',
            action: 'save_user',
            ...Object.fromEntries(formData)
        },
        success: function(response) {
            if (response.success) {
                $('#createUserModal').modal('hide');
                form.reset();
                loadUsers();
                showNotification('<?= t('user_created_successfully') ?>', 'success');
            } else {
                showNotification(response.error || '<?= t('error_creating_user') ?>', 'error');
            }
        },
        error: function() {
            showNotification('<?= t('network_error') ?>', 'error');
        }
    });
}

// Edit user
function editUser(userId) {
    currentUserId = userId;
    
    $.ajax({
        url: 'index.php',
        method: 'POST',
        data: {
            core: 'admin',
            action: 'get_user',
            id: userId
        },
        success: function(response) {
            if (response.success) {
                renderEditUserForm(response.data);
                $('#editUserModal').modal('show');
            } else {
                showNotification(response.error || '<?= t('error_loading_user') ?>', 'error');
            }
        },
        error: function() {
            showNotification('<?= t('network_error') ?>', 'error');
        }
    });
}

// Render edit user form
function renderEditUserForm(user) {
    document.getElementById('editUserModalTitle').textContent = `<?= t('edit_user') ?>: ${escapeHtml(user.username)}`;
    document.getElementById('editUserModalBody').innerHTML = `
        <form id="editUserForm">
            <input type="hidden" name="user_id" value="${user.id}">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="editUsername" class="form-label"><?= t('username') ?> *</label>
                        <input type="text" class="form-control" id="editUsername" name="username" value="${escapeHtml(user.username)}" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="editEmail" class="form-label"><?= t('email') ?> *</label>
                        <input type="email" class="form-control" id="editEmail" name="email" value="${escapeHtml(user.email)}" required>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="editFullName" class="form-label"><?= t('full_name') ?> *</label>
                        <input type="text" class="form-control" id="editFullName" name="full_name" value="${escapeHtml(user.full_name)}" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="editRole" class="form-label"><?= t('role') ?></label>
                        <select class="form-select" id="editRole" name="role">
                            <option value="user" ${user.role === 'user' ? 'selected' : ''}><?= t('user') ?></option>
                            <option value="admin" ${user.role === 'admin' ? 'selected' : ''}><?= t('admin') ?></option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="editPassword" class="form-label"><?= t('password') ?> (<?= t('leave_empty_to_keep') ?>)</label>
                        <input type="password" class="form-control" id="editPassword" name="password">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="editPasswordConfirm" class="form-label"><?= t('confirm_password') ?></label>
                        <input type="password" class="form-control" id="editPasswordConfirm" name="password_confirm">
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="editActive" name="active" ${user.active === 'y' ? 'checked' : ''}>
                    <label class="form-check-label" for="editActive">
                        <?= t('active') ?>
                    </label>
                </div>
            </div>
        </form>
    `;
    
    // Bind password validation
    const editPassword = document.getElementById('editPassword');
    const editPasswordConfirm = document.getElementById('editPasswordConfirm');
    
    if (editPassword && editPasswordConfirm) {
        function validateEditPassword() {
            if (editPassword.value && editPassword.value !== editPasswordConfirm.value) {
                editPasswordConfirm.setCustomValidity('<?= t('passwords_do_not_match') ?>');
            } else {
                editPasswordConfirm.setCustomValidity('');
            }
        }
        
        editPassword.addEventListener('input', validateEditPassword);
        editPasswordConfirm.addEventListener('input', validateEditPassword);
    }
}

// Save user changes
function saveUserChanges() {
    const form = document.getElementById('editUserForm');
    const formData = new FormData(form);
    
    // Validate password confirmation if password is provided
    if (formData.get('password') && formData.get('password') !== formData.get('password_confirm')) {
        showNotification('<?= t('passwords_do_not_match') ?>', 'error');
        return;
    }
    
    $.ajax({
        url: 'index.php',
        method: 'POST',
        data: {
            core: 'admin',
            action: 'save_user',
            ...Object.fromEntries(formData)
        },
        success: function(response) {
            if (response.success) {
                $('#editUserModal').modal('hide');
                loadUsers();
                showNotification('<?= t('user_updated_successfully') ?>', 'success');
            } else {
                showNotification(response.error || '<?= t('error_updating_user') ?>', 'error');
            }
        },
        error: function() {
            showNotification('<?= t('network_error') ?>', 'error');
        }
    });
}

// Toggle user status (suspend/activate)
function toggleUserStatus(userId) {
    $.ajax({
        url: 'index.php',
        method: 'POST',
        data: {
            core: 'admin',
            action: 'toggle_user_status',
            id: userId
        },
        success: function(response) {
            if (response.success) {
                loadUsers();
                showNotification(response.data.message || '<?= t('user_status_updated') ?>', 'success');
            } else {
                showNotification(response.error || '<?= t('error_updating_user_status') ?>', 'error');
            }
        },
        error: function() {
            showNotification('<?= t('network_error') ?>', 'error');
        }
    });
}

// Reset user password
function resetUserPassword(userId) {
    currentUserId = userId;
    generateNewPassword();
    $('#resetPasswordModal').modal('show');
}

// Generate new password
function generateNewPassword() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
    let password = '';
    for (let i = 0; i < 12; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('newPasswordReset').value = password;
}

// Confirm reset password
function confirmResetPassword() {
    $.ajax({
        url: 'index.php',
        method: 'POST',
        data: {
            core: 'admin',
            action: 'reset_user_password',
            id: currentUserId
        },
        success: function(response) {
            if (response.success) {
                $('#resetPasswordModal').modal('hide');
                showNotification('<?= t('password_reset_successfully') ?>', 'success');
                // Update the password field with the generated password
                document.getElementById('newPasswordReset').value = response.data.password;
            } else {
                showNotification(response.error || '<?= t('error_resetting_password') ?>', 'error');
            }
        },
        error: function() {
            showNotification('<?= t('network_error') ?>', 'error');
        }
    });
}

// Delete user
function deleteUser(userId) {
    showConfirmAction(
        '<?= t('delete_user') ?>',
        '<?= t('confirm_delete_user_message') ?>',
        () => performDeleteUser(userId)
    );
}

// Perform delete user
function performDeleteUser(userId) {
    $.ajax({
        url: 'index.php',
        method: 'POST',
        data: {
            core: 'admin',
            action: 'delete_user',
            id: userId
        },
        success: function(response) {
            if (response.success) {
                $('#confirmActionModal').modal('hide');
                loadUsers();
                showNotification('<?= t('user_deleted_successfully') ?>', 'success');
            } else {
                showNotification(response.error || '<?= t('error_deleting_user') ?>', 'error');
            }
        },
        error: function() {
            showNotification('<?= t('network_error') ?>', 'error');
        }
    });
}

// Show confirm action modal
function showConfirmAction(title, message, onConfirm) {
    document.getElementById('confirmActionTitle').textContent = title;
    document.getElementById('confirmActionMessage').textContent = message;
    
    const confirmButton = document.getElementById('confirmActionButton');
    confirmButton.onclick = onConfirm;
    
    $('#confirmActionModal').modal('show');
}

// Helper functions
function formatDate(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// System-spezifische Felder ein-/ausblenden (für andere Bereiche)
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

// Benutzerliste aktualisieren (für andere Systeme)
function refreshUserList(system) {
    // TODO: AJAX-Implementierung für echte Datenaktualisierung
    showNotification('<?= t('refreshing') ?>...', 'info');
    setTimeout(() => {
        showNotification('<?= t('user_list_updated') ?>', 'success');
    }, 1000);
}

// Event Listeners für andere Bereiche
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
    
    // Passwort-Bestätigung validieren (für andere Formulare)
    const password = document.getElementById('password');
    const passwordConfirm = document.getElementById('password_confirm');
    
    if (password && passwordConfirm) {
        function validatePassword() {
            if (password.value !== passwordConfirm.value) {
                passwordConfirm.setCustomValidity('<?= t('passwords_do_not_match') ?>');
            } else {
                passwordConfirm.setCustomValidity('');
            }
        }
        
        password.addEventListener('input', validatePassword);
        passwordConfirm.addEventListener('input', validatePassword);
    }
    
    // Formular-Submission (für andere Formulare)
    const createUserForm = document.getElementById('createUserForm');
    if (createUserForm) {
        createUserForm.addEventListener('submit', function(e) {
            e.preventDefault();
            // TODO: AJAX-Implementierung für echte Benutzererstellung
            console.log('Benutzerdaten:', new FormData(this));
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
    }
    
    // Modal Save Button (für andere Modals)
    const saveUserChangesBtn = document.getElementById('saveUserChanges');
    if (saveUserChangesBtn) {
        saveUserChangesBtn.addEventListener('click', saveUserChanges);
    }
});
</script> 