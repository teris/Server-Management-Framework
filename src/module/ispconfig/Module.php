<?php
/**
 * ISPConfig Module
 * Verwaltung von Websites und Webhosting
 */

require_once dirname(dirname(__FILE__)) . '/ModuleBase.php';

class IspconfigModule extends ModuleBase {
    
    public function getContent() {
        $translations = $this->tMultiple([
            'module_title', 'create_website_ispconfig', 'domain', 'ip_address', 'system_user',
            'system_group', 'hd_quota', 'traffic_quota', 'create_website', 'create_ftp_user',
            'domain_id', 'ftp_username', 'password', 'quota', 'create_subdomain', 'parent_domain_id',
            'subdomain', 'redirect_type', 'no_redirect', 'redirect_temporary', 'redirect_permanent',
            'redirect_path', 'quick_actions', 'load_clients', 'server_config', 'website_details',
            'save', 'cancel', 'edit', 'delete', 'create', 'refresh', 'actions', 'status'
        ]);
        
        return $this->render('main', [
            'translations' => $translations
        ]);
    }
    
    /**
     * Gibt den Modul-Inhalt für AJAX-Requests zurück
     */
    private function getContentResponse() {
        $translations = $this->tMultiple([
            'module_title', 'create_website_ispconfig', 'domain', 'ip_address', 'system_user',
            'system_group', 'hd_quota', 'traffic_quota', 'create_website', 'create_ftp_user',
            'domain_id', 'ftp_username', 'password', 'quota', 'create_subdomain', 'parent_domain_id',
            'subdomain', 'redirect_type', 'no_redirect', 'redirect_temporary', 'redirect_permanent',
            'redirect_path', 'quick_actions', 'load_clients', 'server_config', 'website_details',
            'save', 'cancel', 'edit', 'delete', 'create', 'refresh', 'actions', 'status'
        ]);
        
        $content = $this->render('main', [
            'translations' => $translations
        ]);
        
        return [
            'success' => true,
            'content' => $content
        ];
    }
    
    public function handleAjaxRequest($action, $data) {
        switch ($action) {
            case 'getContent':
                return $this->getContentResponse();
                
            case 'create_website':
                return $this->createWebsite($data);
                
            case 'get_ispconfig_clients':
                return $this->getISPConfigClients();
                
            case 'get_ispconfig_server_config':
                return $this->getISPConfigServerConfig();
                
            case 'get_website_details':
                return $this->getWebsiteDetails($data);
                
            case 'update_website':
                return $this->updateWebsite($data);
                
            case 'create_ftp_user':
                return $this->createFTPUser($data);
                
            case 'create_subdomain':
                return $this->createSubdomain($data);
                
            case 'get_translations':
                return $this->getTranslations();
                
            default:
                return $this->error($this->t('unknown_action') . ': ' . $action);
        }
    }
    
    private function createWebsite($data) {
        // Validierung
        $errors = $this->validate($data, [
            'domain' => 'required|min:3',
            'ip' => 'required',
            'user' => 'required|min:3|max:20',
            'group' => 'required',
            'quota' => 'required|numeric|min:100',
            'traffic' => 'required|numeric|min:1000'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            
            // Client ID ermitteln (vereinfacht - normalerweise aus Session/DB)
            $client_id = 1;
            
            $website_config = [
                'server_id' => 1,
                'ip_address' => $data['ip'],
                'domain' => $data['domain'],
                'type' => 'vhost',
                'parent_domain_id' => 0,
                'vhost_type' => 'name',
                'hd_quota' => $data['quota'],
                'traffic_quota' => $data['traffic'],
                'cgi' => 'n',
                'ssi' => 'n',
                'suexec' => 'y',
                'errordocs' => 1,
                'subdomain' => 'www',
                'ssl' => 'n',
                'php' => 'php-fpm',
                'active' => 'y',
                'redirect_type' => '',
                'redirect_path' => '',
                'ssl_state' => '',
                'ssl_locality' => '',
                'ssl_organisation' => '',
                'ssl_organisation_unit' => '',
                'ssl_country' => '',
                'ssl_domain' => '',
                'ssl_request' => '',
                'ssl_cert' => '',
                'ssl_bundle' => '',
                'ssl_action' => '',
                'stats_password' => '',
                'stats_type' => 'awstats',
                'allow_override' => 'All',
                'apache_directives' => '',
                'php_open_basedir' => '/',
                'custom_php_ini' => '',
                'backup_interval' => 'daily',
                'backup_copies' => 7,
                'traffic_quota_lock' => 'n',
                'system_user' => $data['user'],
                'system_group' => $data['group']
            ];
            
            $result = $serviceManager->IspconfigSOAP('sites_web_domain_add', [1, $website_config]); // client_id = 1
            
            $this->log("Website {$data['domain']} created successfully");
            
            return $this->success($result, $this->t('website_created_successfully'));
            
        } catch (Exception $e) {
            $this->log('Error creating website: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_creating_website') . ': ' . $e->getMessage());
        }
    }
    
    private function getISPConfigClients() {
        try {
            $serviceManager = new ServiceManager();
            $clients = $serviceManager->IspconfigSOAP('client_get');
            
            return $this->success($clients);
            
        } catch (Exception $e) {
            $this->log('Error getting clients: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_getting_clients') . ': ' . $e->getMessage());
        }
    }
    
    private function getISPConfigServerConfig() {
        try {
            $serviceManager = new ServiceManager();
            $config = $serviceManager->IspconfigSOAP('server_get', [1]); // Server ID 1
            
            return $this->success($config);
            
        } catch (Exception $e) {
            $this->log('Error getting server config: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_getting_server_config') . ': ' . $e->getMessage());
        }
    }
    
    private function getWebsiteDetails($data) {
        $errors = $this->validate($data, [
            'domain_id' => 'required|numeric'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $details = $serviceManager->IspconfigSOAP('sites_web_domain_get', [$data['domain_id']]);
            
            return $this->success($details);
            
        } catch (Exception $e) {
            $this->log('Error getting website details: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function updateWebsite($data) {
        $errors = $this->validate($data, [
            'domain_id' => 'required|numeric',
            'domain' => 'required|min:3'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            
            // Aktuelle Konfiguration laden
            $current = $serviceManager->IspconfigSOAP('sites_web_domain_get', [$data['domain_id']]);
            
            // Nur übergebene Felder aktualisieren
            $update_data = array_merge($current, $data);
            unset($update_data['domain_id']);
            
            $result = $serviceManager->IspconfigSOAP('sites_web_domain_update', [$data['domain_id'], $update_data]);
            
            $this->log("Website {$data['domain_id']} updated");
            
            return $this->success($result, 'Website erfolgreich aktualisiert');
            
        } catch (Exception $e) {
            $this->log('Error updating website: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function createFTPUser($data) {
        $errors = $this->validate($data, [
            'domain_id' => 'required|numeric',
            'username' => 'required|min:3|max:20',
            'password' => 'required|min:6',
            'quota' => 'required|numeric|min:0'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            
            $ftp_config = [
                'server_id' => 1,
                'parent_domain_id' => $data['domain_id'],
                'username' => $data['username'],
                'password' => $data['password'],
                'quota_size' => $data['quota'],
                'active' => 'y',
                'uid' => 'web' . $data['domain_id'],
                'gid' => 'client1',
                'dir' => '/var/www/clients/client1/web' . $data['domain_id'],
                'quota_files' => -1,
                'ul_ratio' => -1,
                'dl_ratio' => -1,
                'ul_bandwidth' => -1,
                'dl_bandwidth' => -1
            ];
            
            $result = $serviceManager->IspconfigSOAP('sites_ftp_user_add', [1, $ftp_config]); // client_id = 1
            
            $this->log("FTP user {$data['username']} created for domain {$data['domain_id']}");
            
            return $this->success($result, 'FTP-Benutzer erfolgreich erstellt');
            
        } catch (Exception $e) {
            $this->log('Error creating FTP user: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function createSubdomain($data) {
        $errors = $this->validate($data, [
            'parent_domain_id' => 'required|numeric',
            'subdomain' => 'required|min:1|max:50',
            'redirect_type' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            
            // Parent Domain Details holen
            $parent = $serviceManager->IspconfigSOAP('sites_web_domain_get', [$data['parent_domain_id']]);
            
            $subdomain_config = [
                'server_id' => $parent['server_id'],
                'ip_address' => $parent['ip_address'],
                'domain' => $data['subdomain'] . '.' . $parent['domain'],
                'type' => 'subdomain',
                'parent_domain_id' => $data['parent_domain_id'],
                'vhost_type' => $parent['vhost_type'],
                'redirect_type' => $data['redirect_type'],
                'redirect_path' => $data['redirect_path'] ?? '',
                'active' => 'y'
            ];
            
            $result = $serviceManager->IspconfigSOAP('sites_web_domain_add', [1, $subdomain_config]); // client_id = 1
            
            $this->log("Subdomain {$subdomain_config['domain']} created");
            
            return $this->success($result, 'Subdomain erfolgreich erstellt');
            
        } catch (Exception $e) {
            $this->log('Error creating subdomain: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    public function getStats() {
        try {
            $serviceManager = new ServiceManager();
            $websites = $serviceManager->IspconfigSOAP('sites_web_domain_get');
            
            $active = 0;
            $total_quota = 0;
            $total_traffic = 0;
            
            foreach ($websites as $site) {
                if ($site['active'] === 'y') {
                    $active++;
                }
                $total_quota += intval($site['hd_quota']);
                $total_traffic += intval($site['traffic_quota']);
            }
            
            return [
                'total' => count($websites),
                'active' => $active,
                'quota_gb' => round($total_quota / 1024, 2),
                'traffic_gb' => round($total_traffic / 1024, 2)
            ];
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function getTranslations() {
        $translations = $this->tMultiple([
            'website_created_message', 'ftp_user_created_message', 'subdomain_created_message',
            'network_error', 'unknown_error', 'clients_loaded', 'server_config_loaded',
            'website_details_loaded', 'enter_domain_id'
        ]);
        
        return $this->success($translations);
    }
}
?>