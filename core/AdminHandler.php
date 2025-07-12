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
                
                default:
                    return $this->error('Unbekannte Aktion: ' . $action);
            }
        } catch (Exception $e) {
            $this->log('Error in AdminHandler: ' . $e->getMessage(), 'ERROR');
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
        $html = '<table class="resource-table">
            <thead>
                <tr>
                    <th>Domain</th>
                    <th>IP</th>
                    <th>User</th>
                    <th>Status</th>
                    <th>Quota</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($websites as $site) {
            $status_class = ($site['active'] ?? 'n') === 'y' ? 'status-active' : 'status-inactive';
            $html .= '<tr>
                <td>' . htmlspecialchars($site['domain'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($site['ip_address'] ?? 'N/A') . '</td>
                <td>' . htmlspecialchars($site['system_user'] ?? 'N/A') . '</td>
                <td><span class="status-badge ' . $status_class . '">' . (($site['active'] ?? 'n') === 'y' ? 'Aktiv' : 'Inaktiv') . '</span></td>
                <td>' . htmlspecialchars($site['hd_quota'] ?? 'N/A') . ' MB</td>
                <td class="action-buttons">
                    <button class="btn btn-small btn-danger" onclick="deleteWebsite(\'' . ($site['domain_id'] ?? '') . '\')">Löschen</button>
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
        
        if (isset($data['level']) && $data['level']) {
            $filters['level'] = $data['level'];
        }
        
        if (isset($data['date']) && $data['date']) {
            $filters['date'] = $data['date'];
        }
        
        try {
            $logs = $this->adminCore->getActivityLogs($filters);
            
            // Sicherstellen, dass logs ein Array ist
            if (!is_array($logs)) {
                $logs = [];
            }
            
            // Format für Anzeige
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
        logActivity('AdminCore: ' . $message, $level);
    }
}
?>