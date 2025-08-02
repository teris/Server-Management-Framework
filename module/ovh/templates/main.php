<div id="ovh-content">
    <!-- Aktionsergebnis anzeigen -->
    <div id="action-result" style="display: none;"></div>
    
    <div class="card">
        <div class="card-header">
            <h2 class="mb-0">🔗 <?= t('order_domain_ovh') ?></h2>
        </div>
        <div class="card-body">
            <form class="ovh-form">
                <input type="hidden" name="action" value="order_domain">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="domain_name"><?= t('domain_name') ?></label>
                            <input type="text" class="form-control" id="domain_name" name="domain" required placeholder="example.com">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="domain_duration"><?= t('duration') ?></label>
                            <select class="form-control" id="domain_duration" name="duration">
                                <option value="1" selected>1 Jahr</option>
                                <option value="2">2 Jahre</option>
                                <option value="3">3 Jahre</option>
                                <option value="5">5 Jahre</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <span class="loading hidden"></span>
                    <?= t('order_domain') ?>
                </button>
            </form>

    
    </div>
    
    <hr class="my-4">
    
    <!-- VPS Informationen abrufen -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">🔍 <?= t('get_vps_info') ?></h3>
                </div>
                <div class="card-body">
                    <form class="ovh-form">
                        <input type="hidden" name="action" value="get_vps_info">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="vps_name"><?= t('vps_name') ?></label>
                                    <input type="text" class="form-control" id="vps_name" name="vps_name" required placeholder="vpsXXXXX.ovh.net">
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-secondary">
                            <span class="loading hidden"></span>
                            <?= t('get_vps_info') ?>
                        </button>
                    </form>
                    
                    <div id="vps_result" class="result-box hidden mt-3">
                        <h4><?= t('vps_info') ?></h4>
                        <p><strong><?= t('ip_address') ?>:</strong> <span id="vps_ip"></span></p>
                        <p><strong><?= t('mac_address') ?>:</strong> <span id="vps_mac"></span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- DNS Management und VPS Control -->
    <div class="row">
        <!-- DNS Management -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">🌐 <?= t('dns_management') ?></h3>
                </div>
                <div class="card-body">
                    <h4><?= t('create_dns_record') ?></h4>
                    <form class="ovh-form">
                        <input type="hidden" name="action" value="create_dns_record">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="dns_domain"><?= t('domain') ?></label>
                                    <input type="text" class="form-control" id="dns_domain" name="domain" required placeholder="example.com">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                            <label for="dns_type"><?= t('record_type') ?></label>
                                    <select class="form-control" id="dns_type" name="type" required>
                                        <option value="A">A</option>
                                        <option value="AAAA">AAAA</option>
                                        <option value="CNAME">CNAME</option>
                                        <option value="MX">MX</option>
                                        <option value="TXT">TXT</option>
                                        <option value="SRV">SRV</option>
                                        <option value="NS">NS</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="dns_subdomain">Subdomain</label>
                                    <input type="text" class="form-control" id="dns_subdomain" name="subdomain" placeholder="www">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="dns_target">Target</label>
                                    <input type="text" class="form-control" id="dns_target" name="target" required placeholder="192.168.1.100">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="dns_ttl">TTL (Sekunden)</label>
                            <input type="number" class="form-control" id="dns_ttl" name="ttl" value="3600" min="60" max="604800">
                        </div>
                        
                        <button type="submit" class="btn btn-secondary">
                            <span class="loading hidden"></span>
                            DNS Record erstellen
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- VPS Control -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">🖥️ VPS Control</h3>
                </div>
                <div class="card-body">
                    <form class="ovh-form">
                        <input type="hidden" name="action" value="control_vps">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="control_vps_name">VPS Name</label>
                                    <input type="text" class="form-control" id="control_vps_name" name="vps_name" required placeholder="vpsXXXXX.ovh.net">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="control_vps_action">Aktion</label>
                                    <select class="form-control" id="control_vps_action" name="vps_action" required>
                                        <option value="reboot">Reboot</option>
                                        <option value="start">Start</option>
                                        <option value="stop">Stop</option>
                                        <option value="reset">Reset</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-warning">
                            <span class="loading hidden"></span>
                            VPS Aktion ausführen
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Failover IPs -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">🌐 Failover IPs</h3>
                </div>
                <div class="card-body">
                    <button class="btn btn-secondary ovh-action-btn" data-action="load_failover_ips">
                        📋 Failover IPs laden
                    </button>
                    
                    <div class="table-container mt-3">
                        <table class="table table-striped" id="failover-ips-table">
                            <thead>
                                <tr>
                                    <th>IP</th>
                                    <th>Block</th>
                                    <th>Geroutet zu</th>
                                    <th>Typ</th>
                                    <th>Land</th>
                                    <th>Virtual MAC</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody id="failover-ips-tbody">
                                <tr><td colspan="7" class="text-center">Klicken Sie auf "Failover IPs laden"</td></tr>
                            </tbody>
                        </table>
                    </div>
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
                        <button class="btn btn-secondary ovh-action-btn" data-action="check_domain_availability">
                            🔍 Domain Verfügbarkeit
                        </button>
                        <button class="btn btn-secondary ovh-action-btn" data-action="show_dns_records">
                            📝 DNS Records anzeigen
                        </button>
                        <button class="btn btn-secondary ovh-action-btn" data-action="refresh_dns_zone">
                            🔄 DNS Zone refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="module/ovh/assets/module.js"></script>