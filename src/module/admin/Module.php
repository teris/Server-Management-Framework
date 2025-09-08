<?php
/**
 * Admin Module
 * Admin Dashboard und System-Verwaltung
 */

require_once dirname(dirname(__FILE__)) . '/ModuleBase.php';

class AdminModule extends ModuleBase {
    
    public function getContent() {
        if (!$this->canAccess()) {
            return '<div class="alert alert-danger">' . $this->t('access_denied') . '</div>';
        }
        
        try {
            // Erstelle ServiceManager-Instanz für Template
            $serviceManager = new ServiceManager();
            
            return $this->render('main', [
                'stats' => $this->getStats(),
                'serviceManager' => $serviceManager
            ]);
        } catch (Exception $e) {
            error_log("AdminModule getContent error: " . $e->getMessage());
            return '<div class="alert alert-danger">' . $this->t('error_loading_module') . ': ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
    
    public function handleAjaxRequest($action, $data) {
        switch ($action) {
            case 'get_all_vms':
                return $this->getAllVMs();
                
            case 'get_all_websites':
                return $this->getAllWebsites();
                
            case 'get_all_databases':
                return $this->getAllDatabases();
                
            case 'get_all_emails':
                return $this->getAllEmails();
                
            case 'get_all_domains':
                return $this->getAllDomains();
                
            case 'get_all_vps':
                return $this->getAllVPS();
                
            case 'get_activity_log':
                return $this->getActivityLog();
                
            case 'get_all_users':
                return $this->getAllUsers($data);
                
            case 'save_customer':
                return $this->saveCustomer($data);
                
            case 'delete_customer':
                return $this->deleteCustomer($data);
                
            case 'toggle_customer_status':
                return $this->toggleCustomerStatus($data);
                
            case 'control_vm':
                return $this->controlVM($data);
                
            case 'delete_vm':
                return $this->deleteVM($data);
                
            case 'delete_website':
                return $this->deleteWebsite($data);
                
            case 'delete_database':
                return $this->deleteDatabase($data);
                
            case 'delete_email':
                return $this->deleteEmail($data);
                
            case 'clear_activity_logs':
                return $this->clearActivityLogs();
                
            case 'get_ogp_servers':
                return $this->getOGPServers();
                
            case 'get_active_servers':
                return $this->getActiveServers();
                
            case 'get_online_players':
                return $this->getOnlinePlayers();
                
            case 'get_server_performance':
                return $this->getServerPerformance();
                
            case 'control_ogp_server':
                return $this->controlOGPServer($data);
                
            case 'get_server_stats':
                return $this->getServerStats();
                
            case 'get_system_status':
                return $this->getSystemStatus();
                
            default:
                return $this->error('Unknown action: ' . $action);
        }
    }
    
    private function getAllVMs() {
        try {
            $serviceManager = new ServiceManager();
            $vms = $serviceManager->getProxmoxVMs();
            
            return $this->successResponse($vms);
            
        } catch (Exception $e) {
            $this->logMessage('Error getting VMs: ' . $e->getMessage(), 'ERROR');
            return $this->errorResponse($this->t('error_getting_vms') . ': ' . $e->getMessage());
        }
    }
    
    private function getAllWebsites() {
        try {
            $serviceManager = new ServiceManager();
            $websites = $serviceManager->getISPConfigWebsites();
            
            return $this->successResponse($websites);
            
        } catch (Exception $e) {
            $this->logMessage('Error getting websites: ' . $e->getMessage(), 'ERROR');
            return $this->errorResponse($this->t('error_getting_websites') . ': ' . $e->getMessage());
        }
    }
    
    private function getAllDatabases() {
        try {
            $serviceManager = new ServiceManager();
            $databases = $serviceManager->getISPConfigDatabases();
            
            return $this->successResponse($databases);
            
        } catch (Exception $e) {
            $this->logMessage('Error getting databases: ' . $e->getMessage(), 'ERROR');
            return $this->errorResponse($this->t('error_getting_databases') . ': ' . $e->getMessage());
        }
    }
    
    private function getAllEmails() {
        try {
            $serviceManager = new ServiceManager();
            $emails = $serviceManager->getISPConfigEmails();
            
            return $this->successResponse($emails);
            
        } catch (Exception $e) {
            $this->logMessage('Error getting emails: ' . $e->getMessage(), 'ERROR');
            return $this->errorResponse($this->t('error_getting_emails') . ': ' . $e->getMessage());
        }
    }
    
    private function getAllUsers($data) {
        try {
            $adminCore = new AdminCore();
            $page = $data['page'] ?? 1;
            $search = $data['search'] ?? '';
            $status = $data['status'] ?? '';
            $role = $data['role'] ?? '';
            $userType = $data['userType'] ?? '';
            
            $result = $adminCore->getAllUsers($page, 25, $search, $status, $userType);
            
            if ($result['success']) {
                return $this->successResponse($result['data']);
            } else {
                return $this->errorResponse($result['error'] ?? 'Fehler beim Laden der Benutzer');
            }
            
        } catch (Exception $e) {
            $this->logMessage('Error getting users: ' . $e->getMessage(), 'ERROR');
            return $this->errorResponse($this->t('error_getting_users') . ': ' . $e->getMessage());
        }
    }
    
    private function saveCustomer($data) {
        try {
            $adminCore = new AdminCore();
            $result = $adminCore->saveCustomer($data);
            
            if ($result['success']) {
                return $this->successResponse('Kunde erfolgreich gespeichert');
            } else {
                return $this->errorResponse($result['error'] ?? 'Fehler beim Speichern des Kunden');
            }
            
        } catch (Exception $e) {
            $this->logMessage('Error saving customer: ' . $e->getMessage(), 'ERROR');
            return $this->errorResponse($this->t('error_saving_customer') . ': ' . $e->getMessage());
        }
    }
    
    private function deleteCustomer($data) {
        try {
            $adminCore = new AdminCore();
            $result = $adminCore->deleteCustomer($data['id']);
            
            if ($result['success']) {
                return $this->successResponse('Kunde erfolgreich gelöscht');
            } else {
                return $this->errorResponse($result['error'] ?? 'Fehler beim Löschen des Kunden');
            }
            
        } catch (Exception $e) {
            $this->logMessage('Error deleting customer: ' . $e->getMessage(), 'ERROR');
            return $this->errorResponse($this->t('error_deleting_customer') . ': ' . $e->getMessage());
        }
    }
    
    private function toggleCustomerStatus($data) {
        try {
            $adminCore = new AdminCore();
            $result = $adminCore->toggleCustomerStatus($data['id']);
            
            if ($result['success']) {
                return $this->successResponse('Kundenstatus erfolgreich geändert');
            } else {
                return $this->errorResponse($result['error'] ?? 'Fehler beim Ändern des Kundenstatus');
            }
            
        } catch (Exception $e) {
            $this->logMessage('Error toggling customer status: ' . $e->getMessage(), 'ERROR');
            return $this->errorResponse($this->t('error_toggling_customer_status') . ': ' . $e->getMessage());
        }
    }
    
    private function getAllDomains() {
        try {
            $serviceManager = new ServiceManager();
            $domains = $serviceManager->getOVHDomains();
            
            return $this->successResponse($domains);
            
        } catch (Exception $e) {
            $this->logMessage('Error getting domains: ' . $e->getMessage(), 'ERROR');
            return $this->errorResponse($this->t('error_getting_domains') . ': ' . $e->getMessage());
        }
    }
    
    private function getAllVPS() {
        try {
            $serviceManager = new ServiceManager();
            $vps = $serviceManager->getOVHVPS();
            
            return $this->successResponse($vps);
            
        } catch (Exception $e) {
            $this->logMessage('Error getting VPS: ' . $e->getMessage(), 'ERROR');
            return $this->errorResponse($this->t('error_getting_vps') . ': ' . $e->getMessage());
        }
    }
    
    private function getActivityLog() {
        try {
            $db = Database::getInstance();
            $logs = $db->getActivityLog(50);
            
            return $this->successResponse($logs);
            
        } catch (Exception $e) {
            $this->logMessage('Error getting activity log: ' . $e->getMessage(), 'ERROR');
            return $this->errorResponse($this->t('error_getting_logs') . ': ' . $e->getMessage());
        }
    }
    
    private function clearActivityLogs() {
        try {
            $db = Database::getInstance();
            $result = $db->clearActivityLog();
            
            $this->logMessage("Activity logs cleared");
            
            return $this->successResponse($result, $this->t('logs_cleared_successfully'));
            
        } catch (Exception $e) {
            $this->logMessage('Error clearing activity logs: ' . $e->getMessage(), 'ERROR');
            return $this->errorResponse($this->t('error_clearing_logs') . ': ' . $e->getMessage());
        }
    }
    
    private function controlVM($data) {
        $errors = $this->validateData($data, [
            'vm_id' => 'required',
            'control' => 'required|in:start,stop,reset,shutdown'
        ]);
        
        if (!empty($errors)) {
            return $this->errorResponse($this->t('validation_failed'), $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->controlProxmoxVM($data['vm_id'], $data['control']);
            
            $this->logMessage("VM {$data['vm_id']} {$data['control']} executed");
            
            return $this->successResponse($result, $this->t('vm_control_successful'));
            
        } catch (Exception $e) {
            $this->logMessage('Error controlling VM: ' . $e->getMessage(), 'ERROR');
            return $this->errorResponse($this->t('error_controlling_vm') . ': ' . $e->getMessage());
        }
    }
    
    private function deleteVM($data) {
        $errors = $this->validateData($data, [
            'vm_id' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->errorResponse($this->t('validation_failed'), $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->deleteProxmoxVM($data['vm_id']);
            
            $this->logMessage("VM {$data['vm_id']} deleted");
            
            return $this->successResponse($result, $this->t('vm_deleted_successfully'));
            
        } catch (Exception $e) {
            $this->logMessage('Error deleting VM: ' . $e->getMessage(), 'ERROR');
            return $this->errorResponse($this->t('error_deleting_vm') . ': ' . $e->getMessage());
        }
    }
    
    private function deleteWebsite($data) {
        $errors = $this->validateData($data, [
            'domain_id' => 'required|numeric'
        ]);
        
        if (!empty($errors)) {
            return $this->errorResponse($this->t('validation_failed'), $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->deleteISPConfigWebsite($data['domain_id']);
            
            $this->logMessage("Website {$data['domain_id']} deleted");
            
            return $this->successResponse($result, $this->t('website_deleted_successfully'));
            
        } catch (Exception $e) {
            $this->logMessage('Error deleting website: ' . $e->getMessage(), 'ERROR');
            return $this->errorResponse($this->t('error_deleting_website') . ': ' . $e->getMessage());
        }
    }
    
    private function deleteDatabase($data) {
        $errors = $this->validateData($data, [
            'database_id' => 'required|numeric'
        ]);
        
        if (!empty($errors)) {
            return $this->errorResponse($this->t('validation_failed'), $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->deleteISPConfigDatabase($data['database_id']);
            
            $this->logMessage("Database {$data['database_id']} deleted");
            
            return $this->successResponse($result, $this->t('database_deleted_successfully'));
            
        } catch (Exception $e) {
            $this->logMessage('Error deleting database: ' . $e->getMessage(), 'ERROR');
            return $this->errorResponse($this->t('error_deleting_database') . ': ' . $e->getMessage());
        }
    }
    
    private function deleteEmail($data) {
        $errors = $this->validateData($data, [
            'mailuser_id' => 'required|numeric'
        ]);
        
        if (!empty($errors)) {
            return $this->errorResponse($this->t('validation_failed'), $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->deleteISPConfigEmail($data['mailuser_id']);
            
            $this->logMessage("Email {$data['mailuser_id']} deleted");
            
            return $this->successResponse($result, $this->t('email_deleted_successfully'));
            
        } catch (Exception $e) {
            $this->logMessage('Error deleting email: ' . $e->getMessage(), 'ERROR');
            return $this->errorResponse($this->t('error_deleting_email') . ': ' . $e->getMessage());
        }
    }
    
    public function getStats() {
        try {
            $stats = [];
            $serviceManager = new ServiceManager();
            
            // VM Stats
            try {
                $vms = $serviceManager->getProxmoxVMs();
                $stats['vms'] = [
                    'label' => 'Virtuelle Maschinen',
                    'count' => count($vms),
                    'status' => 'success'
                ];
            } catch (Exception $e) {
                $stats['vms'] = [
                    'label' => 'Virtuelle Maschinen',
                    'count' => 0,
                    'status' => 'error',
                    'status_text' => 'Verbindung fehlgeschlagen'
                ];
            }
            
            // Website Stats
            try {
                $websites = $serviceManager->getISPConfigWebsites();
                $stats['websites'] = [
                    'label' => 'Websites',
                    'count' => count($websites),
                    'status' => 'success'
                ];
            } catch (Exception $e) {
                $stats['websites'] = [
                    'label' => 'Websites',
                    'count' => 0,
                    'status' => 'error',
                    'status_text' => 'Verbindung fehlgeschlagen'
                ];
            }
            
            // Database Stats
            try {
                $databases = $serviceManager->getISPConfigDatabases();
                $stats['databases'] = [
                    'label' => 'Datenbanken',
                    'count' => count($databases),
                    'status' => 'success'
                ];
            } catch (Exception $e) {
                $stats['databases'] = [
                    'label' => 'Datenbanken',
                    'count' => 0,
                    'status' => 'error',
                    'status_text' => 'Verbindung fehlgeschlagen'
                ];
            }
            
            // Email Stats
            try {
                $emails = $serviceManager->getISPConfigEmails();
                $stats['emails'] = [
                    'label' => 'E-Mail Accounts',
                    'count' => count($emails),
                    'status' => 'success'
                ];
            } catch (Exception $e) {
                $stats['emails'] = [
                    'label' => 'E-Mail Accounts',
                    'count' => 0,
                    'status' => 'error',
                    'status_text' => 'Verbindung fehlgeschlagen'
                ];
            }
            
            // OGP/Gameserver Stats
            try {
                $ogpServers = $serviceManager->getOGPServers();
                $activeServers = $serviceManager->getActiveOGPServers();
                $onlinePlayers = $serviceManager->getOGPOnlinePlayers();
                
                $stats['ogp'] = [
                    'label' => 'Gameserver (OGP)',
                    'total_servers' => count($ogpServers),
                    'active_servers' => count($activeServers),
                    'online_players' => $onlinePlayers['total_players'] ?? 0,
                    'status' => 'success'
                ];
            } catch (Exception $e) {
                $stats['ogp'] = [
                    'label' => 'Gameserver (OGP)',
                    'total_servers' => 0,
                    'active_servers' => 0,
                    'online_players' => 0,
                    'status' => 'error',
                    'status_text' => 'OGP Verbindung fehlgeschlagen'
                ];
            }
            
            return $stats;
            
        } catch (Exception $e) {
            $this->logMessage('Error getting stats: ' . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    /**
     * Helper: Validiert Eingabedaten
     */
    private function validateData($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $rulesArray = explode('|', $rule);
            
            foreach ($rulesArray as $singleRule) {
                if ($singleRule === 'required' && (!isset($data[$field]) || empty($data[$field]))) {
                    $errors[$field] = "Field $field is required";
                } elseif ($singleRule === 'numeric' && isset($data[$field]) && !is_numeric($data[$field])) {
                    $errors[$field] = "Field $field must be numeric";
                } elseif (strpos($singleRule, 'in:') === 0) {
                    $allowedValues = explode(',', substr($singleRule, 3));
                    if (isset($data[$field]) && !in_array($data[$field], $allowedValues)) {
                        $errors[$field] = "Field $field must be one of: " . implode(', ', $allowedValues);
                    }
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Helper: Erfolgreiche Antwort
     */
    private function successResponse($data = null, $message = 'Operation successful') {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data
        ];
    }
    
    /**
     * Helper: Fehler-Antwort
     */
    private function errorResponse($message = 'Operation failed', $data = null) {
        return [
            'success' => false,
            'error' => $message,
            'data' => $data
        ];
    }
    
    /**
     * OGP/Gameserver Funktionen
     */
    private function getOGPServers() {
        try {
            $serviceManager = new ServiceManager();
            $servers = $serviceManager->getOGPServers();
            
            return $this->successResponse($servers);
            
        } catch (Exception $e) {
            $this->logMessage('Error getting OGP servers: ' . $e->getMessage(), 'ERROR');
            return $this->errorResponse($this->t('error_getting_ogp_servers') . ': ' . $e->getMessage());
        }
    }
    
    private function getActiveServers() {
        try {
            $serviceManager = new ServiceManager();
            $activeServers = $serviceManager->getActiveOGPServers();
            
            return $this->successResponse($activeServers);
            
        } catch (Exception $e) {
            $this->logMessage('Error getting active servers: ' . $e->getMessage(), 'ERROR');
            return $this->errorResponse($this->t('error_getting_active_servers') . ': ' . $e->getMessage());
        }
    }
    
    private function getOnlinePlayers() {
        try {
            $serviceManager = new ServiceManager();
            $onlinePlayers = $serviceManager->getOGPOnlinePlayers();
            
            return $this->successResponse($onlinePlayers);
            
        } catch (Exception $e) {
            $this->logMessage('Error getting online players: ' . $e->getMessage(), 'ERROR');
            return $this->errorResponse($this->t('error_getting_online_players') . ': ' . $e->getMessage());
        }
    }
    
    private function getServerPerformance() {
        try {
            $serviceManager = new ServiceManager();
            $performance = $serviceManager->getOGPServerPerformance();
            
            return $this->successResponse($performance);
            
        } catch (Exception $e) {
            $this->logMessage('Error getting server performance: ' . $e->getMessage(), 'ERROR');
            return $this->errorResponse($this->t('error_getting_server_performance') . ': ' . $e->getMessage());
        }
    }
    
    private function controlOGPServer($data) {
        $errors = $this->validateData($data, [
            'server_id' => 'required|numeric',
            'action' => 'required|in:start,stop,restart,update'
        ]);
        
        if (!empty($errors)) {
            return $this->errorResponse($this->t('validation_failed'), $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->controlOGPServer($data['server_id'], $data['action']);
            
            $this->logMessage("OGP Server {$data['server_id']} {$data['action']} executed");
            
            return $this->successResponse($result, $this->t('ogp_server_control_successful'));
            
        } catch (Exception $e) {
            $this->logMessage('Error controlling OGP server: ' . $e->getMessage(), 'ERROR');
            return $this->errorResponse($this->t('error_controlling_ogp_server') . ': ' . $e->getMessage());
        }
    }
    
    private function getServerStats() {
        try {
            $stats = [];
            $serviceManager = new ServiceManager();
            
            // OGP Server Stats
            try {
                $ogpServers = $serviceManager->getOGPServers();
                $activeServers = $serviceManager->getActiveOGPServers();
                $onlinePlayers = $serviceManager->getOGPOnlinePlayers();
                
                $stats['ogp'] = [
                    'total_servers' => count($ogpServers),
                    'active_servers' => count($activeServers),
                    'online_players' => $onlinePlayers['total_players'] ?? 0,
                    'status' => 'success'
                ];
            } catch (Exception $e) {
                $stats['ogp'] = [
                    'total_servers' => 0,
                    'active_servers' => 0,
                    'online_players' => 0,
                    'status' => 'error',
                    'status_text' => 'OGP Verbindung fehlgeschlagen'
                ];
            }
            
            return $this->successResponse($stats);
            
        } catch (Exception $e) {
            $this->logMessage('Error getting server stats: ' . $e->getMessage(), 'ERROR');
            return $this->errorResponse($this->t('error_getting_server_stats') . ': ' . $e->getMessage());
        }
    }

    /**
     * System-Status überprüfen
     */
    private function getSystemStatus() {
        try {
            $serviceManager = new ServiceManager();
            $status = [];
            
            // Proxmox Status
            try {
                $proxmoxCheck = $this->checkAPIStatus($serviceManager, 'proxmox');
                $status['proxmox'] = [
                    'name' => 'Proxmox',
                    'status' => $proxmoxCheck['status'],
                    'message' => $proxmoxCheck['message'],
                    'connected' => $proxmoxCheck['connected']
                ];
            } catch (Exception $e) {
                $status['proxmox'] = [
                    'name' => 'Proxmox',
                    'status' => 'error',
                    'message' => 'Fehler beim Überprüfen: ' . $e->getMessage(),
                    'connected' => false
                ];
            }
            
            // ISPConfig Status
            try {
                $ispconfigCheck = $this->checkAPIStatus($serviceManager, 'ispconfig');
                $status['ispconfig'] = [
                    'name' => 'ISPConfig',
                    'status' => $ispconfigCheck['status'],
                    'message' => $ispconfigCheck['message'],
                    'connected' => $ispconfigCheck['connected']
                ];
            } catch (Exception $e) {
                $status['ispconfig'] = [
                    'name' => 'ISPConfig',
                    'status' => 'error',
                    'message' => 'Fehler beim Überprüfen: ' . $e->getMessage(),
                    'connected' => false
                ];
            }
            
            // OVH API Status
            try {
                $ovhCheck = $this->checkAPIStatus($serviceManager, 'ovh');
                $status['ovh'] = [
                    'name' => 'OVH API',
                    'status' => $ovhCheck['status'],
                    'message' => $ovhCheck['message'],
                    'connected' => $ovhCheck['connected']
                ];
            } catch (Exception $e) {
                $status['ovh'] = [
                    'name' => 'OVH API',
                    'status' => 'error',
                    'message' => 'Fehler beim Überprüfen: ' . $e->getMessage(),
                    'connected' => false
                ];
            }
            
            // Datenbank Status
            try {
                $db = Database::getInstance();
                $db->getConnection(); // Teste die Verbindung
                $status['database'] = [
                    'name' => 'Datenbank',
                    'status' => 'success',
                    'message' => 'Verbunden',
                    'connected' => true
                ];
            } catch (Exception $e) {
                $status['database'] = [
                    'name' => 'Datenbank',
                    'status' => 'error',
                    'message' => 'Verbindung fehlgeschlagen: ' . $e->getMessage(),
                    'connected' => false
                ];
            }
            
            // OGP Status
            try {
                $ogpCheck = $this->checkAPIStatus($serviceManager, 'ogp');
                $status['ogp'] = [
                    'name' => 'Open Game Panel',
                    'status' => $ogpCheck['status'],
                    'message' => $ogpCheck['message'],
                    'connected' => $ogpCheck['connected']
                ];
            } catch (Exception $e) {
                $status['ogp'] = [
                    'name' => 'Open Game Panel',
                    'status' => 'error',
                    'message' => 'Fehler beim Überprüfen: ' . $e->getMessage(),
                    'connected' => false
                ];
            }
            
            return $this->successResponse($status);
            
        } catch (Exception $e) {
            $this->logMessage('Error getting system status: ' . $e->getMessage(), 'ERROR');
            return $this->errorResponse($this->t('error_getting_system_status') . ': ' . $e->getMessage());
        }
    }
    
    /**
     * Hilfsmethode zum Überprüfen des API-Status
     */
    private function checkAPIStatus($serviceManager, $apiName) {
        // Verwende Reflection um auf die private checkAPIEnabled Methode zuzugreifen
        $reflection = new ReflectionClass($serviceManager);
        $method = $reflection->getMethod('checkAPIEnabled');
        $method->setAccessible(true);
        
        $result = $method->invoke($serviceManager, $apiName);
        
        if ($result === true) {
            return [
                'status' => 'success',
                'message' => 'Verbunden',
                'connected' => true
            ];
        } else {
            // $result ist ein Fehler-Array
            return [
                'status' => 'error',
                'message' => $result['message'] ?? 'Verbindung fehlgeschlagen',
                'connected' => false
            ];
        }
    }

    /**
     * Helper: Log-Funktion
     */
    private function logMessage($message, $level = 'INFO') {
        if (function_exists('logActivity')) {
            logActivity($this->module_key . ': ' . $message, $level);
        } else {
            error_log("AdminModule [$level]: $message");
        }
    }
}
?>