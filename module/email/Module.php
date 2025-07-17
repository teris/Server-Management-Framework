<?php
// ========================================
// Email Module
// modules/email/Module.php
// ========================================

require_once dirname(dirname(__FILE__)) . '/ModuleBase.php';

class EmailModule extends ModuleBase {
    
    public function getContent() {
        $translations = $this->tMultiple([
            'module_title', 'create_email', 'email_address', 'login_name', 'password',
            'storage_space', 'full_name', 'domain', 'email_client_config', 'imap_receive',
            'smtp_send', 'server', 'port', 'security', 'username', 'authentication',
            'required', 'alternative_ports', 'webmail_access', 'webmail_description',
            'roundcube_webmail', 'horde_webmail', 'generate_secure_password',
            'advanced_email_functions', 'autoresponder', 'email_forwarding',
            'spam_filter_settings', 'email_aliases', 'catch_all_addresses', 'ispconfig_note',
            'save', 'cancel', 'edit', 'delete', 'create', 'refresh', 'actions', 'status'
        ]);
        
        return $this->render('main', [
            'translations' => $translations
        ]);
    }
    
    public function handleAjaxRequest($action, $data) {
        switch ($action) {
            case 'create_email':
                return $this->createEmail($data);
            case 'get_all_emails':
                return $this->getAllEmails();
            case 'delete_email':
                return $this->deleteEmail($data);
            case 'get_translations':
                return $this->getTranslations();
            default:
                return $this->error($this->t('unknown_action') . ': ' . $action);
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
            $serviceManager = new ServiceManager();
            
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
            
            $result = $serviceManager->createISPConfigEmail($email_config);
            
            $this->log("Email account {$data['email']} created");
            
            return $this->success($result, $this->t('email_created_successfully'));
            
        } catch (Exception $e) {
            $this->log('Error creating email: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_creating_email') . ': ' . $e->getMessage());
        }
    }
    
    private function getAllEmails() {
        if ($this->user_role !== 'admin') {
            return $this->error($this->t('admin_rights_required'));
        }
        
        try {
            $serviceManager = new ServiceManager();
            $emails = $serviceManager->getISPConfigEmails();
            return $this->success($emails);
        } catch (Exception $e) {
            return $this->error($this->t('error_getting_emails') . ': ' . $e->getMessage());
        }
    }
    
    private function deleteEmail($data) {
        $this->requireAdmin();
        
        $errors = $this->validate($data, [
            'mailuser_id' => 'required|numeric'
        ]);
        
        if (!empty($errors)) {
            return $this->error($this->t('validation_failed'), $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->deleteISPConfigEmail($data['mailuser_id']);
            
            $this->log("Email account {$data['mailuser_id']} deleted");
            
            return $this->success($result, $this->t('email_deleted_successfully'));
            
        } catch (Exception $e) {
            return $this->error($this->t('error_deleting_email') . ': ' . $e->getMessage());
        }
    }
    
    private function getTranslations() {
        $translations = $this->tMultiple([
            'email_created_message', 'secure_password_generated', 'please_enter_domain',
            'webmail_url', 'network_error', 'unknown_error', 'email_config_alert'
        ]);
        
        return [
            'success' => true,
            'message' => 'Operation successful',
            'translations' => $translations
        ];
    }
}
?>