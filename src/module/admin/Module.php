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
            return $this->render('main', [
                'stats' => $this->getStats(),
                'translations' => $this->tMultiple([
                    'module_title', 'manage_vms', 'websites', 'databases', 'emails', 'refresh',
                    'system_status', 'connected', 'proxmox', 'ispconfig', 'ovh_api', 'database',
                    'resource_management', 'virtual_machines', 'new_vm', 'new_website', 'new_database', 'new_email_account',
                    'system_logs', 'load_logs', 'clear_logs', 'loading', 'actions', 'name', 'status', 'created', 'updated',
                    'edit', 'delete', 'view', 'create', 'save', 'cancel', 'confirm'
                ])
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
                
            case 'delete_vm':
                return $this->deleteVM($data);
                
            case 'delete_website':
                return $this->deleteWebsite($data);
                
            case 'delete_database':
                return $this->deleteDatabase($data);
                
            case 'delete_email':
                return $this->deleteEmail($data);
                
            default:
                return $this->error('Unknown action: ' . $action);
        }
    }
    
    private function getAllVMs() {
        try {
            $serviceManager = new ServiceManager();
            $vms = $serviceManager->getProxmoxVMs();
            
            return $this->success($vms);
            
        } catch (Exception $e) {
            $this->log('Error getting VMs: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_getting_vms') . ': ' . $e->getMessage());
        }
    }
    
    private function getAllWebsites() {
        try {
            $serviceManager = new ServiceManager();
            $websites = $serviceManager->getISPConfigWebsites();
            
            return $this->success($websites);
            
        } catch (Exception $e) {
            $this->log('Error getting websites: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_getting_websites') . ': ' . $e->getMessage());
        }
    }
    
    private function getAllDatabases() {
        try {
            $serviceManager = new ServiceManager();
            $databases = $serviceManager->getISPConfigDatabases();
            
            return $this->success($databases);
            
        } catch (Exception $e) {
            $this->log('Error getting databases: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_getting_databases') . ': ' . $e->getMessage());
        }
    }
    
    private function getAllEmails() {
        try {
            $serviceManager = new ServiceManager();
            $emails = $serviceManager->getISPConfigEmails();
            
            return $this->success($emails);
            
        } catch (Exception $e) {
            $this->log('Error getting emails: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_getting_emails') . ': ' . $e->getMessage());
        }
    }
    
    private function getAllDomains() {
        try {
            $serviceManager = new ServiceManager();
            $domains = $serviceManager->getOVHDomains();
            
            return $this->success($domains);
            
        } catch (Exception $e) {
            $this->log('Error getting domains: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_getting_domains') . ': ' . $e->getMessage());
        }
    }
    
    private function getAllVPS() {
        try {
            $serviceManager = new ServiceManager();
            $vps = $serviceManager->getOVHVPS();
            
            return $this->success($vps);
            
        } catch (Exception $e) {
            $this->log('Error getting VPS: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_getting_vps') . ': ' . $e->getMessage());
        }
    }
    
    private function getActivityLog() {
        try {
            $db = Database::getInstance();
            $logs = $db->getActivityLog(50);
            
            return $this->success($logs);
            
        } catch (Exception $e) {
            $this->log('Error getting activity log: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_getting_logs') . ': ' . $e->getMessage());
        }
    }
    
    private function deleteVM($data) {
        $errors = $this->validate($data, [
            'node' => 'required',
            'vmid' => 'required|numeric'
        ]);
        
        if (!empty($errors)) {
            return $this->error($this->t('validation_failed'), $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->deleteProxmoxVM($data['node'], $data['vmid']);
            
            $this->log("VM {$data['vmid']} deleted from node {$data['node']}");
            
            return $this->success($result, $this->t('vm_deleted_successfully'));
            
        } catch (Exception $e) {
            $this->log('Error deleting VM: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_deleting_vm') . ': ' . $e->getMessage());
        }
    }
    
    private function deleteWebsite($data) {
        $errors = $this->validate($data, [
            'domain_id' => 'required|numeric'
        ]);
        
        if (!empty($errors)) {
            return $this->error($this->t('validation_failed'), $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->deleteISPConfigWebsite($data['domain_id']);
            
            $this->log("Website {$data['domain_id']} deleted");
            
            return $this->success($result, $this->t('website_deleted_successfully'));
            
        } catch (Exception $e) {
            $this->log('Error deleting website: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_deleting_website') . ': ' . $e->getMessage());
        }
    }
    
    private function deleteDatabase($data) {
        $errors = $this->validate($data, [
            'database_id' => 'required|numeric'
        ]);
        
        if (!empty($errors)) {
            return $this->error($this->t('validation_failed'), $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->deleteISPConfigDatabase($data['database_id']);
            
            $this->log("Database {$data['database_id']} deleted");
            
            return $this->success($result, $this->t('database_deleted_successfully'));
            
        } catch (Exception $e) {
            $this->log('Error deleting database: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_deleting_database') . ': ' . $e->getMessage());
        }
    }
    
    private function deleteEmail($data) {
        $errors = $this->validate($data, [
            'mailuser_id' => 'required|numeric'
        ]);
        
        if (!empty($errors)) {
            return $this->error($this->t('validation_failed'), $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->deleteISPConfigEmail($data['mailuser_id']);
            
            $this->log("Email {$data['mailuser_id']} deleted");
            
            return $this->success($result, $this->t('email_deleted_successfully'));
            
        } catch (Exception $e) {
            $this->log('Error deleting email: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_deleting_email') . ': ' . $e->getMessage());
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
            
            return $stats;
            
        } catch (Exception $e) {
            $this->log('Error getting stats: ' . $e->getMessage(), 'ERROR');
            return [];
        }
    }
}
?>