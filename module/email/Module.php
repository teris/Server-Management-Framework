<?php
// ========================================
// Email Module
// modules/email/Module.php
// ========================================

require_once dirname(dirname(__FILE__)) . '/ModuleBase.php';

class EmailModule extends ModuleBase {
    
    public function getContent() {
        return $this->render('main');
    }
    
    public function handleAjaxRequest($action, $data) {
        switch ($action) {
            case 'create_email':
                return $this->createEmail($data);
            case 'get_all_emails':
                return $this->getAllEmails();
            case 'delete_email':
                return $this->deleteEmail($data);
            default:
                return $this->error('Unknown action: ' . $action);
        }
    }
    
    private function createEmail($data) {
        $errors = $this->validate($data, [
            'email' => 'required|email',
            'login' => 'required|min:3|max:20',
            'password' => 'required|min:6',
            'quota' => 'required|numeric|min:10',
            'domain' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $api = new ISPConfigAPI();
            
            $email_config = [
                'server_id' => 1,
                'email' => $data['email'],
                'login' => $data['login'] . '@' . $data['domain'],
                'password' => $data['password'],
                'name' => $data['name'] ?? '',
                'quota' => $data['quota'] * 1024 * 1024, // MB to Bytes
                'cc' => '',
                'forward_in_lda' => 'n',
                'policy' => '5',
                'postfix' => 'y',
                'disableimap' => 'n',
                'disablepop3' => 'n',
                'disabledeliver' => 'n',
                'disablesmtp' => 'n',
                'disablesieve' => 'n',
                'disablelda' => 'n',
                'disabledoveadm' => 'n',
                'active' => 'y'
            ];
            
            $result = $api->createEmail(1, $email_config); // client_id = 1
            
            $this->log("Email account {$data['email']} created");
            
            return $this->success($result, 'E-Mail Account erfolgreich erstellt');
            
        } catch (Exception $e) {
            $this->log('Error creating email: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function getAllEmails() {
        if ($this->user_role !== 'admin') {
            return $this->error('Admin rights required');
        }
        
        try {
            $api = new ISPConfigAPI();
            $emails = $api->getAllEmails();
            return $this->success($emails);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
    
    private function deleteEmail($data) {
        $this->requireAdmin();
        
        $errors = $this->validate($data, [
            'mailuser_id' => 'required|numeric'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $api = new ISPConfigAPI();
            $result = $api->deleteEmail($data['mailuser_id']);
            
            $this->log("Email account {$data['mailuser_id']} deleted");
            
            return $this->success($result, 'E-Mail Account erfolgreich gelöscht');
            
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
?>