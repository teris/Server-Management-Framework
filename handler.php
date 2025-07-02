<?php
// Service Manager initialisieren
$serviceManager = new ServiceManager();
$db = Database::getInstance();

// AJAX Request Handler mit Authentication Check
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // Session-Authentifizierung für alle AJAX-Calls prüfen
    checkAjaxAuth();
    
    try {
        $result = handleAjaxRequest($_POST['action'], $_POST);
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

function handleAjaxRequest($action, $data) {
    global $serviceManager, $db;
    
    // Session bei jeder AJAX-Anfrage verlängern
    SessionManager::updateActivity();
    
    switch ($action) {
        // ===== SESSION MANAGEMENT =====
        case 'extend_session':
            SessionManager::updateActivity();
            return ['success' => true, 'message' => 'Session verlängert'];
            
        case 'get_session_info':
            return [
                'success' => true, 
                'data' => getSessionInfoForJS()
            ];
            
        // ===== PROXMOX ACTIONS =====
        case 'create_vm':
            // Admin-Rechte prüfen für kritische Aktionen
            if (!SessionManager::isAdmin()) {
                return ['success' => false, 'error' => 'Admin-Rechte erforderlich'];
            }
            
            $result = $serviceManager->createProxmoxVM($data);
            $db->logAction(
                'VM erstellt', 
                json_encode($data) . ' (User: ' . SessionManager::getUserInfo()['username'] . ')', 
                $result ? 'success' : 'error'
            );
            return ['success' => $result !== false, 'data' => $result];
            
        case 'get_all_vms':
            $vms = $serviceManager->getProxmoxVMs();
            return ['success' => true, 'data' => array_map(function($vm) {
                return $vm->toArray();
            }, $vms)];
            
        case 'control_vm':
            // Admin-Rechte prüfen
            if (!SessionManager::isAdmin()) {
                return ['success' => false, 'error' => 'Admin-Rechte erforderlich'];
            }
            
            $result = $serviceManager->controlProxmoxVM($data['node'], $data['vmid'], $data['vm_action']);
            $db->logAction(
                'VM ' . $data['vm_action'], 
                "VM {$data['vmid']} auf Node {$data['node']} (User: " . SessionManager::getUserInfo()['username'] . ")", 
                $result ? 'success' : 'error'
            );
            return ['success' => $result !== false, 'data' => $result];
            
        case 'delete_vm':
            // Admin-Rechte prüfen
            if (!SessionManager::isAdmin()) {
                return ['success' => false, 'error' => 'Admin-Rechte erforderlich'];
            }
            
            $result = $serviceManager->deleteProxmoxVM($data['node'], $data['vmid']);
            $db->logAction(
                'VM gelöscht', 
                "VM {$data['vmid']} auf Node {$data['node']} (User: " . SessionManager::getUserInfo()['username'] . ")", 
                $result ? 'success' : 'error'
            );
            return ['success' => $result !== false, 'data' => $result];
            
        case 'update_vm_network':
            if (!SessionManager::isAdmin()) {
                return ['success' => false, 'error' => 'Admin-Rechte erforderlich'];
            }
            
            $proxmoxPost = new ProxmoxPost();
            $result = $proxmoxPost->editVM($data['node'] ?? 'pve', $data['vmid'], [
                'net0' => "virtio,bridge=vmbr0,macaddr={$data['mac']}"
            ]);
            $db->logAction(
                'VM Netzwerk aktualisiert', 
                json_encode($data) . ' (User: ' . SessionManager::getUserInfo()['username'] . ')', 
                $result ? 'success' : 'error'
            );
            return ['success' => $result !== false, 'data' => $result];
            
        // ===== ISPCONFIG ACTIONS =====
        case 'create_website':
            if (!SessionManager::isAdmin()) {
                return ['success' => false, 'error' => 'Admin-Rechte erforderlich'];
            }
            
            $result = $serviceManager->createISPConfigWebsite($data);
            $db->logAction(
                'Website erstellt', 
                json_encode($data) . ' (User: ' . SessionManager::getUserInfo()['username'] . ')', 
                $result ? 'success' : 'error'
            );
            return ['success' => $result !== false, 'data' => $result];
            
        case 'get_all_websites':
            $websites = $serviceManager->getISPConfigWebsites();
            return ['success' => true, 'data' => array_map(function($website) {
                return $website->toArray();
            }, $websites)];
            
        case 'delete_website':
            if (!SessionManager::isAdmin()) {
                return ['success' => false, 'error' => 'Admin-Rechte erforderlich'];
            }
            
            $result = $serviceManager->deleteISPConfigWebsite($data['domain_id']);
            $db->logAction(
                'Website gelöscht', 
                "Website ID {$data['domain_id']} (User: " . SessionManager::getUserInfo()['username'] . ")", 
                $result ? 'success' : 'error'
            );
            return ['success' => $result !== false, 'data' => $result];
            
        case 'create_database':
            if (!SessionManager::isAdmin()) {
                return ['success' => false, 'error' => 'Admin-Rechte erforderlich'];
            }
            
            $result = $serviceManager->createISPConfigDatabase($data);
            $db->logAction(
                'Datenbank erstellt', 
                json_encode($data) . ' (User: ' . SessionManager::getUserInfo()['username'] . ')', 
                $result ? 'success' : 'error'
            );
            return ['success' => $result !== false, 'data' => $result];
            
        case 'get_all_databases':
            $databases = $serviceManager->getISPConfigDatabases();
            return ['success' => true, 'data' => array_map(function($database) {
                return $database->toArray();
            }, $databases)];
            
        case 'delete_database':
            if (!SessionManager::isAdmin()) {
                return ['success' => false, 'error' => 'Admin-Rechte erforderlich'];
            }
            
            $result = $serviceManager->deleteISPConfigDatabase($data['database_id']);
            $db->logAction(
                'Datenbank gelöscht', 
                "Database ID {$data['database_id']} (User: " . SessionManager::getUserInfo()['username'] . ")", 
                $result ? 'success' : 'error'
            );
            return ['success' => $result !== false, 'data' => $result];
            
        case 'create_email':
            if (!SessionManager::isAdmin()) {
                return ['success' => false, 'error' => 'Admin-Rechte erforderlich'];
            }
            
            $result = $serviceManager->createISPConfigEmail($data);
            $db->logAction(
                'E-Mail Adresse erstellt', 
                json_encode($data) . ' (User: ' . SessionManager::getUserInfo()['username'] . ')', 
                $result ? 'success' : 'error'
            );
            return ['success' => $result !== false, 'data' => $result];
            
        case 'get_all_emails':
            try {
                $emails = $serviceManager->getISPConfigEmails();
                return ['success' => true, 'data' => array_map(function($email) {
                    return $email->toArray();
                }, $emails)];
            } catch (Exception $e) {
                // Fallback zu Mock-Daten bei ISPConfig-Problemen
                $mockEmails = [
                    ['mailuser_id' => '1', 'email' => 'admin@example.com', 'login' => 'admin', 'name' => 'Administrator', 'domain' => 'example.com', 'quota' => '1000', 'active' => 'y', 'autoresponder' => 'n', 'forward_to' => ''],
                    ['mailuser_id' => '2', 'email' => 'support@example.com', 'login' => 'support', 'name' => 'Support Team', 'domain' => 'example.com', 'quota' => '2000', 'active' => 'y', 'autoresponder' => 'n', 'forward_to' => '']
                ];
                return ['success' => true, 'data' => $mockEmails, 'warning' => 'Verwendet Demo-Daten (ISPConfig-Fehler: ' . $e->getMessage() . ')'];
            }
            
        case 'delete_email':
            if (!SessionManager::isAdmin()) {
                return ['success' => false, 'error' => 'Admin-Rechte erforderlich'];
            }
            
            $result = $serviceManager->deleteISPConfigEmail($data['mailuser_id']);
            $db->logAction(
                'E-Mail gelöscht', 
                "Email ID {$data['mailuser_id']} (User: " . SessionManager::getUserInfo()['username'] . ")", 
                $result ? 'success' : 'error'
            );
            return ['success' => $result !== false, 'data' => $result];
            
        // ===== OVH ACTIONS =====
        case 'order_domain':
            if (!SessionManager::isAdmin()) {
                return ['success' => false, 'error' => 'Admin-Rechte erforderlich'];
            }
            
            $result = $serviceManager->orderOVHDomain($data['domain'], $data['duration']);
            $db->logAction(
                'Domain bestellt', 
                json_encode($data) . ' (User: ' . SessionManager::getUserInfo()['username'] . ')', 
                $result ? 'success' : 'error'
            );
            return ['success' => $result !== false, 'data' => $result];
            
        case 'get_all_domains':
            $domains = $serviceManager->getOVHDomains();
            return ['success' => true, 'data' => array_map(function($domain) {
                return $domain->toArray();
            }, $domains)];
            
        case 'get_vps_info':
            $result = $serviceManager->getOVHVPSMacAddress($data['vps_name']);
            return ['success' => $result !== null, 'data' => $result];
            
        case 'get_all_vps':
            $vpsList = $serviceManager->getOVHVPS();
            return ['success' => true, 'data' => array_map(function($vps) {
                return $vps->toArray();
            }, $vpsList)];
            
        // ===== ADMIN ACTIONS =====
        case 'get_activity_log':
            $logs = $db->getActivityLog(100);
            return ['success' => true, 'data' => $logs];
            
        // ===== ADVANCED ENDPOINT ACTIONS =====
        case 'get_proxmox_nodes':
            $proxmoxGet = new ProxmoxGet();
            $nodes = $proxmoxGet->getNodes();
            return ['success' => true, 'data' => $nodes];
            
        case 'get_proxmox_storages':
            $proxmoxGet = new ProxmoxGet();
            $node = $data['node'] ?? 'pve';
            $storages = $proxmoxGet->getStorages($node);
            return ['success' => true, 'data' => $storages];
            
        case 'get_vm_config':
            $proxmoxGet = new ProxmoxGet();
            $config = $proxmoxGet->getVMConfig($data['node'], $data['vmid']);
            return ['success' => $config !== null, 'data' => $config];
            
        case 'get_vm_status':
            $proxmoxGet = new ProxmoxGet();
            $status = $proxmoxGet->getVMStatus($data['node'], $data['vmid']);
            return ['success' => $status !== null, 'data' => $status];
            
        case 'clone_vm':
            if (!SessionManager::isAdmin()) {
                return ['success' => false, 'error' => 'Admin-Rechte erforderlich'];
            }
            
            $proxmoxPost = new ProxmoxPost();
            $result = $proxmoxPost->cloneVM($data['node'], $data['vmid'], $data['newid'], $data['name'] ?? null);
            $db->logAction(
                'VM geklont', 
                json_encode($data) . ' (User: ' . SessionManager::getUserInfo()['username'] . ')', 
                $result ? 'success' : 'error'
            );
            return ['success' => $result !== false, 'data' => $result];
            
        case 'get_ispconfig_clients':
            $ispconfigGet = new ISPConfigGet();
            $clients = $ispconfigGet->getClients();
            return ['success' => true, 'data' => $clients];
            
        case 'get_ispconfig_server_config':
            $ispconfigGet = new ISPConfigGet();
            $config = $ispconfigGet->getServerConfig();
            return ['success' => true, 'data' => $config];
            
        case 'get_ovh_domain_zone':
            $ovhGet = new OVHGet();
            $zone = $ovhGet->getDomainZone($data['domain']);
            return ['success' => $zone !== false, 'data' => $zone];
            
        case 'get_ovh_dns_records':
            $ovhGet = new OVHGet();
            $records = $ovhGet->getDomainZoneRecords($data['domain']);
            return ['success' => true, 'data' => $records];
            
        case 'create_dns_record':
            if (!SessionManager::isAdmin()) {
                return ['success' => false, 'error' => 'Admin-Rechte erforderlich'];
            }
            
            $ovhPost = new OVHPost();
            $result = $ovhPost->createDNSRecord($data['domain'], [
                'fieldType' => $data['type'],
                'subDomain' => $data['subdomain'],
                'target' => $data['target'],
                'ttl' => $data['ttl'] ?? 3600
            ]);
            $db->logAction(
                'DNS Record erstellt', 
                json_encode($data) . ' (User: ' . SessionManager::getUserInfo()['username'] . ')', 
                $result ? 'success' : 'error'
            );
            return ['success' => $result !== false, 'data' => $result];
            
        case 'refresh_dns_zone':
            if (!SessionManager::isAdmin()) {
                return ['success' => false, 'error' => 'Admin-Rechte erforderlich'];
            }
            
            $ovhPost = new OVHPost();
            $result = $ovhPost->refreshDNSZone($data['domain']);
            $db->logAction(
                'DNS Zone aktualisiert', 
                "Domain: {$data['domain']} (User: " . SessionManager::getUserInfo()['username'] . ")", 
                $result ? 'success' : 'error'
            );
            return ['success' => $result !== false, 'data' => $result];
            
        case 'get_vps_ips':
            $ovhGet = new OVHGet();
            $ips = $ovhGet->getVPSIPs($data['vps_name']);
            return ['success' => true, 'data' => $ips];
            
        case 'get_vps_ip_details':
            $ovhGet = new OVHGet();
            $details = $ovhGet->getVPSIPDetails($data['vps_name'], $data['ip']);
            return ['success' => $details !== false, 'data' => $details];
            
        case 'control_ovh_vps':
            if (!SessionManager::isAdmin()) {
                return ['success' => false, 'error' => 'Admin-Rechte erforderlich'];
            }
            
            $ovhPost = new OVHPost();
            $action = $data['vps_action'];
            $vpsName = $data['vps_name'];
            
            switch ($action) {
                case 'reboot':
                    $result = $ovhPost->rebootVPS($vpsName);
                    break;
                case 'stop':
                    $result = $ovhPost->stopVPS($vpsName);
                    break;
                case 'start':
                    $result = $ovhPost->startVPS($vpsName);
                    break;
                default:
                    return ['success' => false, 'error' => 'Unbekannte VPS Aktion'];
            }
            
            $db->logAction(
                "VPS $action", 
                "VPS: $vpsName (User: " . SessionManager::getUserInfo()['username'] . ")", 
                $result ? 'success' : 'error'
            );
            return ['success' => $result !== false, 'data' => $result];
            
        // ===== USER MANAGEMENT =====
        case 'test_email_mock':
            // Mock E-Mail Daten für Testing
            $mockEmails = [
                ['mailuser_id' => '1', 'email' => 'admin@example.com', 'login' => 'admin', 'name' => 'Administrator', 'domain' => 'example.com', 'quota' => '1000', 'active' => 'y', 'autoresponder' => 'n', 'forward_to' => ''],
                ['mailuser_id' => '2', 'email' => 'support@example.com', 'login' => 'support', 'name' => 'Support Team', 'domain' => 'example.com', 'quota' => '2000', 'active' => 'y', 'autoresponder' => 'n', 'forward_to' => ''],
                ['mailuser_id' => '3', 'email' => 'info@test.com', 'login' => 'info', 'name' => 'Information', 'domain' => 'test.com', 'quota' => '500', 'active' => 'y', 'autoresponder' => 'y', 'forward_to' => 'admin@example.com']
            ];
            return ['success' => true, 'data' => $mockEmails, 'message' => 'Mock E-Mail Daten geladen'];
            
        case 'change_password':
            $user_info = SessionManager::getUserInfo();
            if (!$user_info) {
                return ['success' => false, 'error' => 'Nicht eingeloggt'];
            }
            
            $auth = new AuthenticationHandler();
            $result = $auth->changePassword(
                $user_info['id'], 
                $data['current_password'], 
                $data['new_password']
            );
            
            return $result;
            
        case 'get_users':
            if (!SessionManager::isAdmin()) {
                return ['success' => false, 'error' => 'Admin-Rechte erforderlich'];
            }
            
            $auth = new AuthenticationHandler();
            $users = $auth->getAllUsers();
            return ['success' => true, 'data' => $users];
            
        default:
            return ['success' => false, 'error' => 'Unbekannte Aktion: ' . $action];
    }
}

// Heartbeat-Endpoint für Session-Verlängerung
if (isset($_GET['heartbeat'])) {
    header('Content-Type: application/json');
    
    if (SessionManager::isLoggedIn()) {
        SessionManager::updateActivity();
        echo json_encode([
            'success' => true, 
            'timeRemaining' => SessionManager::getSessionTimeRemaining()
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'redirect' => 'login.php'
        ]);
    }
    exit;
}
?>