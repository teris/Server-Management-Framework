<div id="ispconfig-content">
    <!-- Aktionsergebnis anzeigen -->
    <div id="action-result" style="display: none;"></div>
    
    <div class="card">
        <div class="card-header">
            <h2 class="mb-0">🌐 <?= t('create_website_ispconfig') ?></h2>
        </div>
        <div class="card-body">
            <form class="ispconfig-form">
                <input type="hidden" name="action" value="create_website">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="website_domain"><?= t('domain') ?></label>
                            <input type="text" class="form-control" id="website_domain" name="domain" required placeholder="example.com">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="website_ip"><?= t('ip_address') ?></label>
                            <input type="text" class="form-control" id="website_ip" name="ip" required placeholder="192.168.1.100">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="website_user"><?= t('system_user') ?></label>
                            <input type="text" class="form-control" id="website_user" name="user" required placeholder="web1">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="website_group"><?= t('system_group') ?></label>
                            <input type="text" class="form-control" id="website_group" name="group" required placeholder="client1">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="website_quota"><?= t('hd_quota') ?></label>
                            <input type="number" class="form-control" id="website_quota" name="quota" value="1000" required min="100">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="website_traffic"><?= t('traffic_quota') ?></label>
                            <input type="number" class="form-control" id="website_traffic" name="traffic" value="10000" required min="1000">
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <span class="loading hidden"></span>
                        <?= t('create_website') ?>
                </button>
            </form>
       
    
    </div>
    
    <hr class="my-4">
    
    <!-- FTP User erstellen -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">👤 FTP-Benutzer erstellen</h3>
                </div>
                <div class="card-body">
                    <form class="ispconfig-form">
                        <input type="hidden" name="action" value="create_ftp_user">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="ftp_domain_id">Domain ID</label>
                                    <input type="number" class="form-control" id="ftp_domain_id" name="domain_id" required placeholder="1">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="ftp_username">FTP Username</label>
                                    <input type="text" class="form-control" id="ftp_username" name="username" required placeholder="ftp_user1">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="ftp_password">Passwort</label>
                                    <input type="password" class="form-control" id="ftp_password" name="password" required minlength="6">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="ftp_quota">Quota (MB)</label>
                                    <input type="number" class="form-control" id="ftp_quota" name="quota" value="500" required min="0">
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-secondary">
                            <span class="loading hidden"></span>
                            FTP-Benutzer erstellen
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Subdomain erstellen -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">🌐 Subdomain erstellen</h3>
                </div>
                <div class="card-body">
                    <form class="ispconfig-form">
                        <input type="hidden" name="action" value="create_subdomain">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="sub_parent_id">Parent Domain ID</label>
                                    <input type="number" class="form-control" id="sub_parent_id" name="parent_domain_id" required placeholder="1">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="sub_name">Subdomain</label>
                                    <input type="text" class="form-control" id="sub_name" name="subdomain" required placeholder="blog">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="sub_redirect_type">Redirect Type</label>
                                    <select class="form-control" id="sub_redirect_type" name="redirect_type">
                                        <option value="">Kein Redirect</option>
                                        <option value="R">R (Temporary)</option>
                                        <option value="L">L (Permanent)</option>
                                        <option value="R,L">R,L</option>
                                        <option value="R=301,L">R=301,L</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="sub_redirect_path">Redirect Path (optional)</label>
                                    <input type="text" class="form-control" id="sub_redirect_path" name="redirect_path" placeholder="https://example.com">
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-secondary">
                            <span class="loading hidden"></span>
                            Subdomain erstellen
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">⚡ Schnellaktionen</h3>
                </div>
                <div class="card-body">
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-secondary ispconfig-action-btn" data-action="load_clients">
                            👥 Clients laden
                        </button>
                        <button class="btn btn-secondary ispconfig-action-btn" data-action="load_server_config">
                            ⚙️ Server Config
                        </button>
                        <button class="btn btn-secondary ispconfig-action-btn" data-action="show_website_details">
                            📊 Website Details
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="module/ispconfig/assets/module.js"></script>