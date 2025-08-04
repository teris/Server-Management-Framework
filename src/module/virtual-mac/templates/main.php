<?php 
// Ensure all variables are always defined at the very beginning
$virtualMacOverview = $virtualMacOverview ?? [
    'stats' => [
        'total_macs' => 0,
        'total_ips' => 0,
        'total_servers' => 0
    ],
    'macs' => []
];
$servers = $servers ?? [];
?>
<div id="virtual-mac-content">
    <!-- Aktionsergebnis anzeigen -->
    <div id="action-result" style="display: none;"></div>
    
    <div class="card">
        <div class="card-header">
            <h2 class="mb-0">🔌 <?= t('virtual_mac_management') ?></h2>
        </div>
        <div class="card-body">
            <div class="tabs mb-3">
                <button class="tab active virtual-mac-tab-btn" data-tab="overview">📊 <?= t('overview') ?></button>
                <button class="tab virtual-mac-tab-btn" data-tab="create">➕ <?= t('create') ?></button>
                <button class="tab virtual-mac-tab-btn" data-tab="ip-management">🌐 <?= t('ip_management') ?></button>
                <button class="tab virtual-mac-tab-btn" data-tab="reverse-dns">🔄 <?= t('reverse_dns') ?></button>
            </div>
        </div>
    </div>
    
    <!-- Overview -->
    <div id="virtual-mac-overview" class="virtual-mac-tab-content">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">📊 <?= t('virtual_mac_overview') ?></h3>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5><?= t('total_virtual_macs') ?></h5>
                                <div class="h3" id="total-virtual-macs"><?= $virtualMacOverview['stats']['total_macs'] ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5><?= t('total_assigned_ips') ?></h5>
                                <div class="h3" id="total-assigned-ips"><?= $virtualMacOverview['stats']['total_ips'] ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <h5><?= t('total_dedicated_servers') ?></h5>
                                <div class="h3" id="total-dedicated-servers"><?= $virtualMacOverview['stats']['total_servers'] ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-8">
                        <input type="text" class="form-control" id="virtual-mac-overview-search" placeholder="<?= t('search_virtual_macs') ?>">
                    </div>
                    <div class="col-md-4">
                        <a href="?option=virtual-mac" class="btn btn-secondary">🔄 <?= t('refresh') ?></a>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped" id="virtual-mac-overview-table">
                        <thead>
                            <tr>
                                <th><?= t('mac_address') ?></th>
                                <th><?= t('vm_name') ?></th>
                                <th><?= t('ip_address') ?></th>
                                <th><?= t('service_name') ?></th>
                                <th><?= t('type') ?></th>
                                <th><?= t('created_at') ?></th>
                                <th><?= t('actions') ?></th>
                            </tr>
                        </thead>
                        <tbody id="virtual-mac-overview-tbody">
                            <?php if (empty($virtualMacOverview['macs'])): ?>
                                    <tr><td colspan="7" class="text-center"><?= t('no_virtual_macs_found') ?></td></tr>
                            <?php else: ?>
                                <?php foreach ($virtualMacOverview['macs'] as $mac): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($mac['macAddress'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($mac['virtualMachineName'] ?? ''); ?></td>
                                        <td>
                                            <?php 
                                            if (isset($mac['ipAddresses']) && is_array($mac['ipAddresses'])) {
                                                echo htmlspecialchars(implode(', ', $mac['ipAddresses']));
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($mac['service_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($mac['type'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($mac['createdAt'] ?? ''); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-danger" onclick="deleteVirtualMac('<?php echo htmlspecialchars($mac['service_name']); ?>', '<?php echo htmlspecialchars($mac['macAddress']); ?>')">
                                                🗑️ <?= t('delete') ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Create Virtual MAC -->
    <div id="virtual-mac-create" class="virtual-mac-tab-content hidden">
        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">➕ <?= t('create_virtual_mac') ?></h3>
            </div>
            <div class="card-body">
                <form method="POST" action="handler.php">
                    <input type="hidden" name="action" value="create_virtual_mac">
                    <input type="hidden" name="plugin" value="virtual-mac">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="vmac_service_name"><?= t('service_name') ?> (<?= t('dedicated_server') ?>)</label>
                                <select class="form-control" id="vmac_service_name" name="service_name" required>
                                    <option value=""><?= t('select_server') ?></option>
                                    <?php foreach ($servers as $server): ?>
                                    <option value="<?= htmlspecialchars($server) ?>"><?= htmlspecialchars($server) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-3">
                                <label for="vmac_type"><?= t('mac_type') ?></label>
                                <select class="form-control" id="vmac_type" name="type">
                                    <option value="ovh"><?= t('ovh') ?> (<?= t('standard') ?>)</option>
                                    <option value="vmware">VMware</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="vmac_virtual_network_interface"><?= t('virtual_network_interface') ?></label>
                        <input type="text" class="form-control" id="vmac_virtual_network_interface" name="virtual_network_interface" required placeholder="eth0">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <span class="loading hidden"></span>
                        <?= t('create_virtual_mac') ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- IP Management -->
    <div id="virtual-mac-ip-management" class="virtual-mac-tab-content hidden">
        <div class="row">
            <!-- IP zuweisen -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">🌐 <?= t('assign_ip_address') ?></h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="handler.php">
                            <input type="hidden" name="action" value="assign_ip_to_virtual_mac">
                            <input type="hidden" name="plugin" value="virtual-mac">
                            <div class="form-group mb-3">
                                <label for="vmac_ip_service_name"><?= t('service_name') ?></label>
                                <select class="form-control" id="vmac_ip_service_name" name="service_name" required>
                                    <option value=""><?= t('select_server') ?></option>
                                    <?php foreach ($servers as $server): ?>
                                    <option value="<?= htmlspecialchars($server) ?>"><?= htmlspecialchars($server) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group mb-3">
                                <label for="vmac_ip_mac_address"><?= t('virtual_mac') ?></label>
                                <select class="form-control" id="vmac_ip_mac_address" name="mac_address" required>
                                    <option value=""><?= t('select_service') ?></option>
                                </select>
                            </div>
                            <div class="form-group mb-3">
                                <label for="vmac_ip_address"><?= t('ip_address') ?></label>
                                <input type="text" class="form-control" id="vmac_ip_address" name="ip_address" required placeholder="192.168.1.100">
                            </div>
                            <div class="form-group mb-3">
                                        <label for="vmac_ip_vm_name"><?= t('vm_name') ?></label>
                                <input type="text" class="form-control" id="vmac_ip_vm_name" name="virtual_machine_name" required placeholder="webserver-01">
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <span class="loading hidden"></span>
                                <?= t('assign_ip_address') ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- IP entfernen -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">🗑️ <?= t('remove_ip_address') ?></h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="handler.php">
                            <input type="hidden" name="action" value="remove_ip_from_virtual_mac">
                            <input type="hidden" name="plugin" value="virtual-mac">
                            <div class="form-group mb-3">
                                <label for="vmac_remove_service_name">Service Name</label>
                                <select class="form-control" id="vmac_remove_service_name" name="service_name" required>
                                    <option value=""><?= t('select_server') ?></option>
                                    <?php foreach ($servers as $server): ?>
                                    <option value="<?= htmlspecialchars($server) ?>"><?= htmlspecialchars($server) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group mb-3">
                                <label for="vmac_remove_mac_address"><?= t('virtual_mac') ?></label>
                                <input type="text" class="form-control" id="vmac_remove_mac_address" name="mac_address" required placeholder="02:00:00:96:1f:85">
                            </div>
                            <div class="form-group mb-3">
                                <label for="vmac_remove_ip_address"><?= t('ip_address') ?></label>
                                <input type="text" class="form-control" id="vmac_remove_ip_address" name="ip_address" required placeholder="192.168.1.100">
                            </div>
                            <button type="submit" class="btn btn-warning">
                                <span class="loading hidden"></span>
                                <?= t('remove_ip_address') ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Reverse DNS -->
    <div id="virtual-mac-reverse-dns" class="virtual-mac-tab-content hidden">
        <div class="row">
            <!-- Reverse DNS erstellen -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">🔄 <?= t('create_reverse_dns') ?></h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="handler.php">
                            <input type="hidden" name="action" value="create_reverse_dns">
                            <input type="hidden" name="plugin" value="virtual-mac">
                            <div class="form-group mb-3">
                                <label for="reverse_ip_address"><?= t('ip_address') ?></label>
                                <input type="text" class="form-control" id="reverse_ip_address" name="ip_address" required placeholder="192.168.1.100">
                            </div>
                            <div class="form-group mb-3">
                                <label for="reverse_hostname"><?= t('hostname') ?></label>
                                <input type="text" class="form-control" id="reverse_hostname" name="reverse" required placeholder="server.example.com">
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <span class="loading hidden"></span>
                                <?= t('create_reverse_dns') ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Reverse DNS abfragen -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                            <h4 class="mb-0">🔍 <?= t('query_reverse_dns') ?></h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="handler.php">
                            <input type="hidden" name="action" value="query_reverse_dns">
                            <input type="hidden" name="plugin" value="virtual-mac">
                            <div class="form-group mb-3">
                                <label for="query_reverse_ip"><?= t('ip_address') ?></label>
                                <input type="text" class="form-control" id="query_reverse_ip" name="ip_address" required placeholder="192.168.1.100">
                            </div>
                            <button type="submit" class="btn btn-secondary">
                                <span class="loading hidden"></span>
                                <?= t('query_reverse_dns') ?>
                            </button>
                        </form>
                        
                        <div id="reverse_dns_result" class="mt-3" style="display: none;">
                                <h5><?= t('reverse_dns_information') ?>:</h5>
                            <pre id="reverse_dns_content" class="bg-light p-3 rounded"></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="module/virtual-mac/assets/module.js"></script>