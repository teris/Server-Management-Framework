<?php
/**
 * ISPConfig Module
 * Verwaltung von Websites und Webhosting
 */

require_once dirname(dirname(__FILE__)) . '/ModuleBase.php';

class IspconfigModule extends ModuleBase {
    
    public function getContent() {
        return $this->render('main');
    }
    
    public function handleAjaxRequest($action, $data) {
        switch ($action) {
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
                
            default:
                return $this->error('Unknown action: ' . $action);
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
            $api = new ISPConfigAPI();
            
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
                'active' => 'y',
                'traffic_quota_lock' => 'n',
                'system_user' => $data['user'],
                'system_group' => $data['group']
            ];
            
            $result = $api->createWebsite($client_id, $website_config);
            
            $this->log("Website {$data['domain']} created successfully");
            
            return $this->success($result, 'Website erfolgreich erstellt');
            
        } catch (Exception $e) {
            $this->log('Error creating website: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function getISPConfigClients() {
        try {
            $api = new ISPConfigAPI();
            $clients = $api->getClients();
            
            return $this->success($clients);
            
        } catch (Exception $e) {
            $this->log('Error getting clients: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function getISPConfigServerConfig() {
        try {
            $api = new ISPConfigAPI();
            $config = $api->getServerConfig();
            
            return $this->success($config);
            
        } catch (Exception $e) {
            $this->log('Error getting server config: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
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
            $api = new ISPConfigAPI();
            $details = $api->getWebsiteDetails($data['domain_id']);
            
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
            $api = new ISPConfigAPI();
            
            // Aktuelle Konfiguration laden
            $current = $api->getWebsiteDetails($data['domain_id']);
            
            // Nur übergebene Felder aktualisieren
            $update_data = array_merge($current, $data);
            unset($update_data['domain_id']);
            
            $result = $api->updateWebsite($data['domain_id'], $update_data);
            
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
            $api = new ISPConfigAPI();
            
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
            
            $result = $api->createFTPUser($ftp_config);
            
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
            $api = new ISPConfigAPI();
            
            // Parent Domain Details holen
            $parent = $api->getWebsiteDetails($data['parent_domain_id']);
            
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
            
            $result = $api->createSubdomain($subdomain_config);
            
            $this->log("Subdomain {$subdomain_config['domain']} created");
            
            return $this->success($result, 'Subdomain erfolgreich erstellt');
            
        } catch (Exception $e) {
            $this->log('Error creating subdomain: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    public function getStats() {
        try {
            $api = new ISPConfigAPI();
            $websites = $api->getAllWebsites();
            
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
}
?>