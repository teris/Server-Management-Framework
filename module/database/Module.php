<?php
// ========================================
// Database Module
// modules/database/Module.php
// ========================================

require_once dirname(dirname(__FILE__)) . '/ModuleBase.php';

class DatabaseModule extends ModuleBase {
    
    public function getContent() {
        return $this->render('main');
    }
    
    public function handleAjaxRequest($action, $data) {
        switch ($action) {
            case 'create_database':
                return $this->createDatabase($data);
            case 'get_all_databases':
                return $this->getAllDatabases();
            case 'delete_database':
                return $this->deleteDatabase($data);
            default:
                return $this->error('Unknown action: ' . $action);
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
            $api = new ISPConfigAPI();
            
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
            
            $result = $api->createDatabase(1, $db_config); // client_id = 1
            
            $this->log("Database {$data['name']} created with user {$data['user']}");
            
            return $this->success($result, 'Datenbank erfolgreich erstellt');
            
        } catch (Exception $e) {
            $this->log('Error creating database: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function getAllDatabases() {
        if ($this->user_role !== 'admin') {
            return $this->error('Admin rights required');
        }
        
        try {
            $api = new ISPConfigAPI();
            $databases = $api->getAllDatabases();
            return $this->success($databases);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    private function deleteDatabase($data) {
        $this->requireAdmin();
        
        $errors = $this->validate($data, [
            'database_id' => 'required|numeric'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $api = new ISPConfigAPI();
            $result = $api->deleteDatabase($data['database_id']);
            
            $this->log("Database {$data['database_id']} deleted");
            
            return $this->success($result, 'Datenbank erfolgreich gelöscht');
            
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
?>