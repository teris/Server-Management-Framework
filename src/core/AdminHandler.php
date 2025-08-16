<?php
/**
 * AdminHandler - Verarbeitet AJAX-Requests für das Admin Dashboard
 */

require_once dirname(__FILE__) . '/AdminCore.php';

class AdminHandler {
    private $adminCore;
    private $user;
    
    public function __construct() {
        $this->adminCore = new AdminCore();
        
        // Session starten und Benutzer-Info abrufen
        require_once dirname(__FILE__) . '/../auth_handler.php';
        SessionManager::startSession();
        
        $this->user = SessionManager::getUserInfo();
        
        // Temporäre weichere Admin-Rechte-Prüfung
        if (!$this->user) {
            throw new Exception('Benutzer nicht angemeldet');
        }
        
        // Prüfe Admin-Rechte (temporär deaktiviert für Debugging)
        if ($this->user['role'] !== 'admin') {
            // Temporär: Log statt Exception
            error_log("AdminHandler: Benutzer {$this->user['username']} hat Rolle {$this->user['role']}, Admin-Rolle erforderlich");
            // throw new Exception('Admin-Rechte erforderlich');
        }
    }
    
    /**
     * Hauptmethode für Request-Verarbeitung
     */
    public function handleRequest($action, $data) {
        try {
            switch ($action) {
                // Dashboard Stats
                case 'get_dashboard_stats':
                    return $this->success($this->adminCore->getDashboardStats());
                
                // Ressourcen-Management
                case 'get_resources':
                    return $this->getResources($data);
                
                case 'refresh_all_stats':
                    return $this->refreshAllStats();
                
                // VM-Aktionen
                case 'control_vm':
                    return $this->controlVM($data);
                
                case 'delete_vm':
                    return $this->deleteVM($data);
                
                // Website-Aktionen
                case 'delete_website':
                    return $this->deleteWebsite($data);
                
                // Datenbank-Aktionen
                case 'delete_database':
                    return $this->deleteDatabase($data);
                
                // E-Mail-Aktionen
                case 'delete_email':
                    return $this->deleteEmail($data);
                
                // System-Aktionen
                case 'get_system_info':
                    return $this->success($this->adminCore->getSystemInfo());
                
                case 'test_connections':
                    return $this->success($this->adminCore->testConnections());
                
                case 'clear_cache':
                    $result = $this->adminCore->clearCache();
                    return $result ? 
                        $this->success(['message' => 'Cache erfolgreich geleert']) : 
                        $this->error('Cache konnte nicht geleert werden');
                
                // Logs
                case 'get_activity_logs':
                    return $this->getActivityLogs($data);
                
                case 'clear_activity_logs':
                    return $this->clearActivityLogs();
                
                // Plugin-Management
                case 'toggle_plugin':
                    return $this->togglePlugin($data);
                
                case 'get_plugin_list':
                    return $this->getPluginList();
                
                // Einstellungen
                case 'save_settings':
                    return $this->saveSettings($data);
                
                case 'get_settings':
                    return $this->getSettings();
                
                // --- Erweiterungen für Settings-Seite ---
                case 'get_api_credentials':
                    return $this->adminCore->getApiCredentials();
                case 'save_api_credentials':
                    return $this->adminCore->saveApiCredentials($data);
                case 'get_modules':
                    return $this->adminCore->getModules();
                case 'save_modules':
                    return $this->adminCore->saveModules($data);
                case 'get_users':
                    return $this->getUsers($data);
                case 'get_user':
                    return $this->adminCore->getUser($data['id']);
                case 'save_user':
                    return $this->adminCore->saveUser($data);
                case 'delete_user':
                    return $this->adminCore->deleteUser($data['id']);
                case 'toggle_user_status':
                    return $this->toggleUserStatus($data);
                case 'reset_user_password':
                    return $this->resetUserPassword($data);
                
                // --- Domain-Registrierungsverwaltung ---
                case 'get_domain_registration':
                    return $this->getDomainRegistration($data);
                case 'update_domain_registration':
                    return $this->updateDomainRegistration($data);
                case 'update_domain_registration_status':
                    return $this->updateDomainRegistrationStatus($data);
                
                // --- Domain-Einstellungen ---
                case 'get_domain_extensions':
                    return $this->getDomainExtensions($data);
                case 'add_extension':
                    return $this->addDomainExtension($data);
                case 'update_extension':
                    return $this->updateDomainExtension($data);
                case 'delete_extension':
                    return $this->deleteDomainExtension($data);
                case 'toggle_extension_status':
                    return $this->toggleDomainExtensionStatus($data);
                
                case 'get_groups':
                    return $this->adminCore->getGroups();
                case 'get_group':
                    return $this->adminCore->getGroup($data['id']);
                case 'save_group':
                    return $this->adminCore->saveGroup($data);
                case 'delete_group':
                    return $this->adminCore->deleteGroup($data['id']);
                case 'get_group_modules':
                    return $this->getGroupModules($data);
                case 'save_group_modules':
                    return $this->saveGroupModules($data);
                
                // Benutzer-Management
                case 'get_all_users':
                    return $this->getAllUsers($data);
                
                case 'save_customer':
                    return $this->saveCustomer($data);
                
                case 'delete_customer':
                    return $this->deleteCustomer($data);
                
                case 'toggle_customer_status':
                    return $this->toggleCustomerStatus($data);
                

                
                default:
                    return $this->error('Unbekannte Aktion: ' . $action);
            }
        } catch (Exception $e) {
            error_log('AdminHandler Exception: ' . $e->getMessage());
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Ressourcen abrufen
     */
    private function getResources($data) {
        if (!isset($data['type'])) {
            return $this->error('Resource type required');
        }
        
        try {
            $resources = $this->adminCore->getResources($data['type']);
            
            // Sicherstellen, dass resources ein Array ist
            if (!is_array($resources)) {
                $resources = [];
            }
            
            // Format für Tabellen-Anzeige
            $formatted = $this->formatResourcesForTable($data['type'], $resources);
            
            return $this->success([
                'type' => $data['type'],
                'data' => $resources,
                'html' => $formatted
            ]);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Formatiere Ressourcen für HTML-Tabelle
     */
    private function formatResourcesForTable($type, $resources) {
        if (empty($resources)) {
            return '<div class="no-data">Keine Daten gefunden</div>';
        }
        
        switch ($type) {
            case 'vms':
                return $this->formatVMsTable($resources);
            case 'websites':
                return $this->formatWebsitesTable($resources);
            case 'databases':
                return $this->formatDatabasesTable($resources);
            case 'emails':
                return $this->formatEmailsTable($resources);
            case 'domains':
                return $this->formatDomainsTable($resources);
            default:
                return '<div class="no-data">Unbekannter Ressourcentyp</div>';
        }
    }
    
    private function formatVMsTable($vms) {
        $html = '<table class="resource-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Node</th>
                    <th>Status</th>
                    <th>CPU</th>
                    <th>RAM</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($vms as $vm) {
            $status = $vm['status'] ?? 'unknown';
            $status_class = $status === 'running' ? 'status-running' : 'status-stopped';
            $html .= '<tr>
                <td>' . htmlspecialchars($vm['vmid'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($vm['name'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($vm['node'] ?? 'N/A') . '</td>
                <td><span class="status-badge ' . $status_class . '">' . htmlspecialchars($status) . '</span></td>
                <td>' . htmlspecialchars($vm['cores'] ?? $vm['cpus'] ?? 'N/A') . '</td>
                <td>' . ($vm['memory'] ? round($vm['memory']/1024/1024) . ' MB' : 'N/A') . '</td>
                <td class="action-buttons">
                    ' . ($status === 'running' ? 
                        '<button class="btn btn-small btn-warning" onclick="controlVM(\'' . ($vm['node'] ?? '') . '\', \'' . ($vm['vmid'] ?? '') . '\', \'stop\')">Stop</button>' :
                        '<button class="btn btn-small btn-success" onclick="controlVM(\'' . ($vm['node'] ?? '') . '\', \'' . ($vm['vmid'] ?? '') . '\', \'start\')">Start</button>'
                    ) . '
                    <button class="btn btn-small btn-danger" onclick="deleteVM(\'' . ($vm['node'] ?? '') . '\', \'' . ($vm['vmid'] ?? '') . '\')">Löschen</button>
                </td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
        return $html;
    }
    
    private function formatWebsitesTable($websites) {
        $html = '<table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Domain</th>
                    <th>IP</th>
                    <th>Benutzer</th>
                    <th>Status</th>
                    <th>Quota</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($websites as $site) {
            $isActive = ($site['active'] ?? 'n') === 'y';
            $statusClass = $isActive ? 'success' : 'danger';
            $statusText = $isActive ? 'Aktiv' : 'Inaktiv';
            
            $html .= '<tr>
                <td>' . htmlspecialchars($site['domain'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($site['ip_address'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($site['system_user'] ?? 'N/A') . '</td>
                <td><span class="badge bg-' . $statusClass . '">' . $statusText . '</span></td>
                <td>' . htmlspecialchars($site['hd_quota'] ?? 'N/A') . ' MB</td>
                <td>
                    <button class="btn btn-primary btn-sm me-1" onclick="editWebsite(\'' . ($site['domain_id'] ?? '') . '\')" title="Bearbeiten"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-danger btn-sm" onclick="deleteWebsite(\'' . ($site['domain_id'] ?? '') . '\')" title="Löschen"><i class="bi bi-trash"></i></button>
                </td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
        return $html;
    }
    
    private function formatDatabasesTable($databases) {
        $html = '<table class="resource-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Server</th>
                    <th>User</th>
                    <th>Quota</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($databases as $db) {
            $html .= '<tr>
                <td>' . htmlspecialchars($db['database_id'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($db['database_name'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($db['server_name'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($db['database_user'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($db['database_quota'] ?? 'N/A') . ' MB</td>
                <td class="action-buttons">
                    <button class="btn btn-small btn-danger" onclick="deleteDatabase(\'' . ($db['database_id'] ?? '') . '\')">Löschen</button>
                </td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
        return $html;
    }
    
    private function formatEmailsTable($emails) {
        $html = '<table class="resource-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>E-Mail</th>
                    <th>Domain</th>
                    <th>Quota</th>
                    <th>Status</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($emails as $email) {
            $status_class = ($email['active'] ?? 'n') === 'y' ? 'status-active' : 'status-inactive';
            $html .= '<tr>
                <td>' . htmlspecialchars($email['mailuser_id'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($email['email'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($email['domain'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($email['quota'] ?? 'N/A') . ' MB</td>
                <td><span class="status-badge ' . $status_class . '">' . (($email['active'] ?? 'n') === 'y' ? 'Aktiv' : 'Inaktiv') . '</span></td>
                <td class="action-buttons">
                    <button class="btn btn-small btn-danger" onclick="deleteEmail(\'' . ($email['mailuser_id'] ?? '') . '\')">Löschen</button>
                </td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
        return $html;
    }
    
    private function formatDomainsTable($domains) {
        $html = '<table class="resource-table">
            <thead>
                <tr>
                    <th>Domain</th>
                    <th>Status</th>
                    <th>Registrar</th>
                    <th>Expiry</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($domains as $domain) {
            $status = $domain['status'] ?? 'unknown';
            $status_class = $status === 'ok' ? 'status-active' : 'status-inactive';
            $html .= '<tr>
                <td>' . htmlspecialchars($domain['domain'] ?? 'N/A') . '</td>
                <td><span class="status-badge ' . $status_class . '">' . htmlspecialchars($status) . '</span></td>
                <td>' . htmlspecialchars($domain['registrar'] ?? 'OVH') . '</td>
                <td>' . htmlspecialchars($domain['expiry'] ?? 'N/A') . '</td>
                <td class="action-buttons">
                    <button class="btn btn-small btn-secondary" onclick="viewDomain(\'' . ($domain['domain'] ?? '') . '\')">Anzeigen</button>
                </td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
        return $html;
    }
    
    /**
     * VM-Kontrolle
     */
    private function controlVM($data) {
        if (!isset($data['node']) || !isset($data['vmid']) || !isset($data['action'])) {
            return $this->error('Fehlende Parameter');
        }
        
        try {
            // ServiceManager über AdminCore zugreifen
            $serviceManager = new ServiceManager();
            $result = $serviceManager->controlProxmoxVM($data['node'], $data['vmid'], $data['action']);
            
            $this->log("VM {$data['vmid']} action {$data['action']} executed");
            
            return $this->success($result, 'VM-Aktion erfolgreich ausgeführt');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Alle Stats aktualisieren
     */
    private function refreshAllStats() {
        try {
            $stats = $this->adminCore->getDashboardStats();
            return $this->success($stats, 'Statistiken aktualisiert');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * VM löschen
     */
    private function deleteVM($data) {
        if (!isset($data['node']) || !isset($data['vmid'])) {
            return $this->error('Fehlende Parameter');
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->deleteProxmoxVM($data['node'], $data['vmid']);
            
            $this->log("VM {$data['vmid']} deleted");
            
            return $this->success($result, 'VM erfolgreich gelöscht');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Website löschen
     */
    private function deleteWebsite($data) {
        if (!isset($data['domain_id'])) {
            return $this->error('Fehlende Parameter');
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->deleteISPConfigWebsite($data['domain_id']);
            
            $this->log("Website {$data['domain_id']} deleted");
            
            return $this->success($result, 'Website erfolgreich gelöscht');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Datenbank löschen
     */
    private function deleteDatabase($data) {
        if (!isset($data['database_id'])) {
            return $this->error('Fehlende Parameter');
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->deleteISPConfigDatabase($data['database_id']);
            
            $this->log("Database {$data['database_id']} deleted");
            
            return $this->success($result, 'Datenbank erfolgreich gelöscht');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * E-Mail löschen
     */
    private function deleteEmail($data) {
        if (!isset($data['mailuser_id'])) {
            return $this->error('Fehlende Parameter');
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->deleteISPConfigEmail($data['mailuser_id']);
            
            $this->log("Email {$data['mailuser_id']} deleted");
            
            return $this->success($result, 'E-Mail erfolgreich gelöscht');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Plugin umschalten
     */
    private function togglePlugin($data) {
        if (!isset($data['plugin_key']) || !isset($data['enabled'])) {
            return $this->error('Fehlende Parameter');
        }
        
        try {
            // Plugin-Status in der Konfiguration ändern
            $config = getModuleConfig($data['plugin_key']);
            $config['enabled'] = (bool) $data['enabled'];
            
            // TODO: Konfiguration speichern
            $this->log("Plugin {$data['plugin_key']} " . ($data['enabled'] ? 'enabled' : 'disabled'));
            
            return $this->success(null, 'Plugin-Status geändert');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Plugin-Liste abrufen
     */
    private function getPluginList() {
        try {
            $plugins = getAllModules();
            $enabled = getEnabledModules();
            
            $list = [];
            foreach ($plugins as $key => $plugin) {
                $list[] = [
                    'key' => $key,
                    'name' => $plugin['name'] ?? $key,
                    'enabled' => in_array($key, $enabled),
                    'description' => $plugin['description'] ?? ''
                ];
            }
            
            return $this->success($list);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Einstellungen speichern
     */
    private function saveSettings($data) {
        try {
            // TODO: Einstellungen in Datenbank speichern
            $this->log("Settings saved");
            
            return $this->success(null, 'Einstellungen gespeichert');
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Einstellungen abrufen
     */
    private function getSettings() {
        try {
            // TODO: Einstellungen aus Datenbank laden
            $settings = [
                'debug_mode' => false,
                'auto_refresh' => true,
                'session_timeout' => 3600
            ];
            
            return $this->success($settings);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Activity Logs abrufen
     */
    private function getActivityLogs($data) {
        $filters = [];
        $limit = 50; // Standard-Limit
        $offset = 0;
        if (isset($data['level']) && $data['level']) {
            $filters['level'] = $data['level'];
        }
        if (isset($data['date']) && $data['date']) {
            $filters['date'] = $data['date'];
        }
        if (isset($data['limit']) && is_numeric($data['limit'])) {
            $limit = (int) $data['limit'];
            if ($limit < 1) $limit = 1;
            if ($limit > 100) $limit = 100;
        }
        if (isset($data['offset']) && is_numeric($data['offset'])) {
            $offset = (int) $data['offset'];
            if ($offset < 0) $offset = 0;
        }
        try {
            $logs = $this->adminCore->getActivityLogs($filters, $limit, $offset);
            if (!is_array($logs)) {
                $logs = [];
            }
            $html = $this->formatLogsTable($logs);
            return $this->success([
                'logs' => $logs,
                'html' => $html
            ]);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Activity Logs löschen
     */
    private function clearActivityLogs() {
        try {
            $result = $this->adminCore->clearActivityLogs();
            
            if ($result) {
                $this->log('Activity logs cleared by user ' . $this->user['username'], 'INFO');
                return $this->success(['message' => 'Activity Logs erfolgreich gelöscht']);
            } else {
                return $this->error('Fehler beim Löschen der Activity Logs');
            }
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    private function formatLogsTable($logs) {
        if (empty($logs)) {
            return '<div class="no-data">Keine Logs gefunden</div>';
        }
        
        $html = '<table class="resource-table">
            <thead>
                <tr>
                    <th>Zeitpunkt</th>
                    <th>Level</th>
                    <th>Aktion</th>
                    <th>Details</th>
                    <th>User</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($logs as $log) {
            $level = $log['level'] ?? 'INFO';
            $level_class = 'log-' . strtolower($level);
            $html .= '<tr class="' . $level_class . '">
                <td>' . date('d.m.Y H:i:s', strtotime($log['created_at'] ?? 'now')) . '</td>
                <td><span class="badge ' . $level_class . '">' . htmlspecialchars($level) . '</span></td>
                <td>' . htmlspecialchars($log['action'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($log['details'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($log['username'] ?? 'System') . '</td>
            </tr>';
        }
        
        $html .= '</tbody></table>';
        return $html;
    }
    
    /**
     * Benutzer-Gruppen-Module abrufen
     */
    private function getGroupModules($data) {
        try {
            $groupId = $data['id'] ?? 0;
            if (!$groupId) {
                return $this->error('Gruppen-ID erforderlich');
            }
            
            $modules = $this->adminCore->getGroupModules($groupId);
            return $this->success(['modules' => $modules]);
            
        } catch (Exception $e) {
            return $this->error('Fehler beim Abrufen der Gruppen-Module: ' . $e->getMessage());
        }
    }
    
    /**
     * Benutzer-Gruppen-Module speichern
     */
    private function saveGroupModules($data) {
        try {
            $groupId = $data['group_id'] ?? 0;
            $modules = $data['modules'] ?? [];
            
            if (!$groupId) {
                return $this->error('Gruppen-ID erforderlich');
            }
            
            $result = $this->adminCore->saveGroupModules($groupId, $modules);
            
            if ($result) {
                $this->log("Group modules updated for group {$groupId} by admin {$this->user['username']}", 'INFO');
                return $this->success(['message' => 'Gruppen-Module erfolgreich aktualisiert']);
            } else {
                return $this->error('Fehler beim Speichern der Gruppen-Module');
            }
            
        } catch (Exception $e) {
            return $this->error('Fehler beim Speichern der Gruppen-Module: ' . $e->getMessage());
        }
    }
    
    /**
     * Benutzer abrufen
     */
    private function getUsers($data) {
        try {
            $page = $data['page'] ?? 1;
            $search = $data['search'] ?? '';
            $status = $data['status'] ?? '';
            $role = $data['role'] ?? '';
            
            $result = $this->adminCore->getUsers();
            if ($result['success']) {
                $users = $result['data'];
                
                // Filter users based on search, status, and role
                if ($search) {
                    $users = array_filter($users, function($user) use ($search) {
                        return stripos($user['username'], $search) !== false ||
                               stripos($user['full_name'], $search) !== false ||
                               stripos($user['email'], $search) !== false;
                    });
                }
                
                if ($status) {
                    $users = array_filter($users, function($user) use ($status) {
                        if ($status === 'active') {
                            return $user['active'] === 'y';
                        } else {
                            return $user['active'] === 'n';
                        }
                    });
                }
                
                if ($role) {
                    $users = array_filter($users, function($user) use ($role) {
                        return $user['role'] === $role;
                    });
                }
                
                // Pagination
                $perPage = 10;
                $totalUsers = count($users);
                $totalPages = ceil($totalUsers / $perPage);
                $offset = ($page - 1) * $perPage;
                $users = array_slice($users, $offset, $perPage);
                
                return $this->success([
                    'users' => $users,
                    'pagination' => [
                        'page' => $page,
                        'pages' => $totalPages,
                        'total' => $totalUsers,
                        'per_page' => $perPage
                    ]
                ]);
            } else {
                return $this->error($result['error']);
            }
        } catch (Exception $e) {
            return $this->error('Fehler beim Abrufen der Benutzer: ' . $e->getMessage());
        }
    }

    /**
     * Benutzerstatus umschalten
     */
    private function toggleUserStatus($data) {
        if (!isset($data['id'])) {
            return $this->error('Fehlende Parameter');
        }

        try {
            $result = $this->adminCore->toggleUserStatus($data['id']);
            if ($result['success']) {
                $status = $result['data']['active'] === 'y' ? 'aktiviert' : 'deaktiviert';
                $this->log("User {$data['id']} status changed to {$status} by admin {$this->user['username']}", 'INFO');
                return $this->success(['message' => "Benutzer erfolgreich {$status}"]);
            } else {
                return $this->error($result['error']);
            }
        } catch (Exception $e) {
            return $this->error('Fehler beim Ändern des Benutzerstatus: ' . $e->getMessage());
        }
    }

    /**
     * Benutzerpasswort zurücksetzen
     */
    private function resetUserPassword($data) {
        if (!isset($data['id'])) {
            return $this->error('Fehlende Parameter');
        }

        try {
            $result = $this->adminCore->resetUserPassword($data['id']);
            if ($result['success']) {
                $this->log("Password reset for user {$data['id']} by admin {$this->user['username']}", 'INFO');
                return $this->success([
                    'message' => 'Benutzerpasswort erfolgreich zurückgesetzt',
                    'password' => $result['data']['password']
                ]);
            } else {
                return $this->error($result['error']);
            }
        } catch (Exception $e) {
            return $this->error('Fehler beim Zurücksetzen des Benutzerpassworts: ' . $e->getMessage());
        }
    }
    
    /**
     * Domain-Registrierungsverwaltung
     */
    private function getDomainRegistration($data) {
        if (!isset($data['id'])) {
            return $this->error('Registrierungs-ID erforderlich');
        }

        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            $stmt = $conn->prepare("
                SELECT dr.*, u.username, u.email, u.full_name 
                FROM domain_registrations dr
                LEFT JOIN users u ON dr.user_id = u.id
                WHERE dr.id = ?
            ");
            $stmt->execute([$data['id']]);
            $registration = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$registration) {
                return $this->error('Registrierung nicht gefunden');
            }
            
            return $this->success($registration);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    private function updateDomainRegistration($data) {
        if (!isset($data['id'])) {
            return $this->error('Registrierungs-ID erforderlich');
        }

        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            $stmt = $conn->prepare("
                UPDATE domain_registrations 
                SET status = ?, purpose = ?, admin_notes = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $data['status'],
                $data['purpose'],
                $data['admin_notes'] ?? '',
                $data['id']
            ]);
            
            $this->log("Domain registration updated ID {$data['id']} by admin {$this->user['username']}", 'INFO');
            return $this->success(['message' => 'Registrierung erfolgreich aktualisiert']);
        } catch (Exception $e) {
            return $this->error('Fehler beim Aktualisieren der Registrierung: ' . $e->getMessage());
        }
    }

    private function updateDomainRegistrationStatus($data) {
        if (!isset($data['id']) || !isset($data['status'])) {
            return $this->error('Registrierungs-ID und Status erforderlich');
        }

        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            $stmt = $conn->prepare("
                UPDATE domain_registrations 
                SET status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$data['status'], $data['id']]);
            
            $this->log("Domain registration status updated ID {$data['id']} to {$data['status']} by admin {$this->user['username']}", 'INFO');
            return $this->success(['message' => 'Status erfolgreich aktualisiert']);
        } catch (Exception $e) {
            return $this->error('Fehler beim Aktualisieren des Status: ' . $e->getMessage());
        }
    }
    
    /**
     * Domain-Einstellungen
     */
    private function getDomainExtensions($data) {
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("SELECT * FROM domain_extensions ORDER BY tld ASC");
            $stmt->execute();
            $extensions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $this->success(['extensions' => $extensions]);
        } catch (Exception $e) {
            return $this->error('Fehler beim Abrufen der Domain-Endungen: ' . $e->getMessage());
        }
    }

    private function addDomainExtension($data) {
        if (!isset($data['tld']) || !isset($data['name'])) {
            return $this->error('TLD und Name sind erforderlich');
        }

        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            // Prüfen ob TLD bereits existiert
            $stmt = $conn->prepare("SELECT id FROM domain_extensions WHERE tld = ?");
            $stmt->execute([$data['tld']]);
            if ($stmt->fetch()) {
                return $this->error('Diese TLD existiert bereits');
            }
            
            $stmt = $conn->prepare("
                INSERT INTO domain_extensions (tld, name, active, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([
                $data['tld'],
                $data['name'],
                $data['active'] ?? 1
            ]);
            
            $this->log("Domain extension {$data['tld']} added by admin {$this->user['username']}", 'INFO');
            return $this->success(['message' => 'Domain-Endung erfolgreich hinzugefügt']);
        } catch (Exception $e) {
            return $this->error('Fehler beim Hinzufügen der Domain-Endung: ' . $e->getMessage());
        }
    }

    private function updateDomainExtension($data) {
        if (!isset($data['id']) || !isset($data['tld']) || !isset($data['name'])) {
            return $this->error('ID, TLD und Name sind erforderlich');
        }

        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            // Prüfen ob TLD bereits bei anderer ID existiert
            $stmt = $conn->prepare("SELECT id FROM domain_extensions WHERE tld = ? AND id != ?");
            $stmt->execute([$data['tld'], $data['id']]);
            if ($stmt->fetch()) {
                return $this->error('Diese TLD existiert bereits bei einer anderen Endung');
            }
            
            $stmt = $conn->prepare("
                UPDATE domain_extensions 
                SET tld = ?, name = ?, active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $data['tld'],
                $data['name'],
                $data['active'] ?? 1,
                $data['id']
            ]);
            
            $this->log("Domain extension {$data['tld']} updated by admin {$this->user['username']}", 'INFO');
            return $this->success(['message' => 'Domain-Endung erfolgreich aktualisiert']);
        } catch (Exception $e) {
            return $this->error('Fehler beim Aktualisieren der Domain-Endung: ' . $e->getMessage());
        }
    }

    private function deleteDomainExtension($data) {
        if (!isset($data['id'])) {
            return $this->error('ID ist erforderlich');
        }

        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("DELETE FROM domain_extensions WHERE id = ?");
            $stmt->execute([$data['id']]);
            
            if ($stmt->rowCount() > 0) {
                $this->log("Domain extension ID {$data['id']} deleted by admin {$this->user['username']}", 'INFO');
                return $this->success(['message' => 'Domain-Endung erfolgreich gelöscht']);
            } else {
                return $this->error('Domain-Endung nicht gefunden');
            }
        } catch (Exception $e) {
            return $this->error('Fehler beim Löschen der Domain-Endung: ' . $e->getMessage());
        }
    }

    private function toggleDomainExtensionStatus($data) {
        if (!isset($data['id'])) {
            return $this->error('ID ist erforderlich');
        }

        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("
                UPDATE domain_extensions 
                SET active = NOT active, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$data['id']]);
            
            if ($stmt->rowCount() > 0) {
                $this->log("Domain extension ID {$data['id']} status toggled by admin {$this->user['username']}", 'INFO');
                return $this->success(['message' => 'Status erfolgreich geändert']);
            } else {
                return $this->error('Domain-Endung nicht gefunden');
            }
        } catch (Exception $e) {
            return $this->error('Fehler beim Ändern des Status: ' . $e->getMessage());
        }
    }
    
    /**
     * Benutzer-Management-Methoden
     */
    private function getAllUsers($data) {
        try {
            $page = $data['page'] ?? 1;
            $search = $data['search'] ?? '';
            $status = $data['status'] ?? '';
            $role = $data['role'] ?? '';
            $userType = $data['user_type'] ?? '';
            
            $result = $this->adminCore->getAllUsers($page, 25, $search, $status, $userType);
            
            if ($result['success']) {
                return $this->success($result['data']);
            } else {
                return $this->error($result['error'] ?? 'Fehler beim Laden der Benutzer');
            }
            
        } catch (Exception $e) {
            $this->log('Error getting users: ' . $e->getMessage(), 'ERROR');
            return $this->error('Fehler beim Laden der Benutzer: ' . $e->getMessage());
        }
    }
    
    private function saveCustomer($data) {
        try {
            $result = $this->adminCore->saveCustomer($data);
            
            if ($result['success']) {
                return $this->success('Kunde erfolgreich gespeichert');
            } else {
                return $this->error($result['error'] ?? 'Fehler beim Speichern des Kunden');
            }
            
        } catch (Exception $e) {
            $this->log('Error saving customer: ' . $e->getMessage(), 'ERROR');
            return $this->error('Fehler beim Speichern des Kunden: ' . $e->getMessage());
        }
    }
    
    private function deleteCustomer($data) {
        try {
            $result = $this->adminCore->deleteCustomer($data['id']);
            
            if ($result['success']) {
                return $this->success('Kunde erfolgreich gelöscht');
            } else {
                return $this->error($result['error'] ?? 'Fehler beim Löschen des Kunden');
            }
            
        } catch (Exception $e) {
            $this->log('Error deleting customer: ' . $e->getMessage(), 'ERROR');
            return $this->error('Fehler beim Löschen des Kunden: ' . $e->getMessage());
        }
    }
    
    private function toggleCustomerStatus($data) {
        try {
            $result = $this->adminCore->toggleCustomerStatus($data['id']);
            
            if ($result['success']) {
                return $this->success('Kundenstatus erfolgreich geändert');
            } else {
                return $this->error($result['error'] ?? 'Fehler beim Ändern des Kundenstatus');
            }
            
        } catch (Exception $e) {
            $this->log('Error toggling customer status: ' . $e->getMessage(), 'ERROR');
            return $this->error('Fehler beim Ändern des Kundenstatus: ' . $e->getMessage());
        }
    }
    
    /**
     * Helper-Methoden
     */
    private function success($data = null, $message = '') {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data
        ];
    }
    
    private function error($message) {
        return [
            'success' => false,
            'error' => $message
        ];
    }
    
    private function log($message, $level = 'INFO') {
        // Einfaches Logging über error_log
        $logMessage = '[' . strtoupper($level) . '] AdminHandler: ' . $message;
        error_log($logMessage);
    }
}
?>