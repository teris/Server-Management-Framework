<?php
// ========================================
// Database Module
// modules/database/Module.php
// ========================================

require_once dirname(dirname(__FILE__)) . '/ModuleBase.php';

class DatabaseModule extends ModuleBase {
    
    public function getContent() {
        $translations = $this->tMultiple([
            'module_title', 'create_database', 'database_name', 'database_user', 'password',
            'password_min_length', 'connection_info', 'host', 'port', 'charset', 'advanced_options',
            'database_server_info', 'generate_secure_password', 'connection_details', 'host_info',
            'port_info', 'charset_info', 'phpmyadmin_info', 'phpmyadmin_url', 'save', 'cancel',
            'edit', 'delete', 'create', 'refresh', 'actions', 'status'
        ]);
        
        return $this->render('main', [
            'translations' => $translations
        ]);
    }
    
    public function handleAjaxRequest($action, $data) {
        switch ($action) {
            case 'create_database':
                return $this->createDatabase($data);
            case 'get_all_databases':
                return $this->getAllDatabases();
            case 'delete_database':
                return $this->deleteDatabase($data);
            case 'get_translations':
                return $this->getTranslations();
            default:
                return $this->error($this->t('unknown_action') . ': ' . $action);
        }
    }
    
    private function createDatabase($data) {
        $errors = $this->validate($data, [
            'name' => 'required|min:3|max:50',
            'user' => 'required|min:3|max:20',
            'password' => 'required|min:6'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            
            $db_config = [
                'server_id' => 1,
                'type' => 'mysql',
                'database_name' => $data['name'],
                'database_user' => $data['user'],
                'database_password' => $data['password'],
                'database_charset' => 'utf8mb4',
                'remote_access' => 'n',
                'remote_ips' => '',
                'active' => 'y'
            ];
            
            $result = $serviceManager->createISPConfigDatabase($db_config);
            
            $this->log("Database {$data['name']} created with user {$data['user']}");
            
            return $this->success($result, $this->t('database_created_successfully'));
            
        } catch (Exception $e) {
            $this->log('Error creating database: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_creating_database') . ': ' . $e->getMessage());
        }
    }
    
    private function getAllDatabases() {
        if ($this->user_role !== 'admin') {
            return $this->error($this->t('admin_rights_required'));
        }
        
        try {
            $serviceManager = new ServiceManager();
            $databases = $serviceManager->getISPConfigDatabases();
            return $this->success($databases);
        } catch (Exception $e) {
            return $this->error($this->t('error_getting_databases') . ': ' . $e->getMessage());
        }
    }
    
    private function deleteDatabase($data) {
        $this->requireAdmin();
        
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
            return $this->error($this->t('error_deleting_database') . ': ' . $e->getMessage());
        }
    }
    
    private function getTranslations() {
        $translations = $this->tMultiple([
            'database_created_message', 'secure_password_generated', 'database_info_message',
            'network_error', 'unknown_error', 'database_connection_alert'
        ]);
        
        return [
            'success' => true,
            'message' => 'Operation successful',
            'translations' => $translations
        ];
    }
}
?>