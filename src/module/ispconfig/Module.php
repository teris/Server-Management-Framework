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
                
            // Neue Handler für Benutzer-Übersicht
            case 'get_all_users':
                return $this->getAllUsers();
                
            case 'get_user_details':
                return $this->getUserDetails($data);
                
            case 'get_user_websites':
                return $this->getUserWebsites($data);
                
            case 'get_user_email_accounts':
                return $this->getUserEmailAccounts($data);
                
            case 'get_user_databases':
                return $this->getUserDatabases($data);
                
            case 'get_user_ftp_users':
                return $this->getUserFTPUsers($data);
                
            case 'update_user_status':
                return $this->updateUserStatus($data);
                
            // Domain-Management Handler
            case 'get_all_domains':
                return $this->getAllDomains();
                
            case 'assign_domain_to_user':
                return $this->assignDomainToUser($data);
                
            case 'unassign_domain_from_user':
                return $this->unassignDomainFromUser($data);
                
            case 'update_domain_settings':
                return $this->updateDomainSettings($data);
                
            case 'preview_domain_changes':
                return $this->previewDomainChanges($data);
                
            case 'execute_domain_changes':
                return $this->executeDomainChanges($data);
                
            // DNS-Management Handler
            case 'get_domain_dns_records':
                return $this->getDomainDnsRecords($data);
                
            case 'get_ovh_dns_records':
                return $this->getOvhDnsRecords($data);
                
            case 'update_dns_record':
                return $this->updateDnsRecord($data);
                
            case 'create_dns_record':
                return $this->createDnsRecord($data);
                
            case 'delete_dns_record':
                return $this->deleteDnsRecord($data);
                
            case 'sync_dns_records':
                return $this->syncDnsRecords($data);
                
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
            $clients = $serviceManager->IspconfigSOAP('client_get', []);
            
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
            $websites = $serviceManager->IspconfigSOAP('sites_web_domain_get', []);
            
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
    
    // Neue Methoden für Benutzer-Übersicht
    
    /**
     * Alle ISPConfig-Benutzer abrufen
     */
    private function getAllUsers() {
        try {
            $serviceManager = new ServiceManager();
            
            // ISPConfig-Verbindung wird automatisch in IspconfigSOAP geprüft
            $clients = $serviceManager->IspconfigSOAP('client_get', []);
            
            if (!is_array($clients) || empty($clients)) {
                $this->log('Keine Clients gefunden oder ungültige Antwort', 'WARNING');
                return $this->success([]);
            }
            
            // Zusätzliche Informationen für jeden Benutzer sammeln
            $usersWithDetails = [];
            foreach ($clients as $client) {
                if (!is_array($client)) {
                    continue;
                }
                
                $clientDetails = [
                    'client_id' => $client['client_id'] ?? '',
                    'company_name' => $client['company_name'] ?? '',
                    'contact_name' => $client['contact_name'] ?? '',
                    'email' => $client['email'] ?? '',
                    'phone' => $client['phone'] ?? '',
                    'street' => $client['street'] ?? '',
                    'city' => $client['city'] ?? '',
                    'zip' => $client['zip'] ?? '',
                    'country' => $client['country'] ?? '',
                    'active' => $client['active'] ?? 'n',
                    'created_at' => $client['created_at'] ?? '',
                    'websites_count' => 0,
                    'email_accounts_count' => 0,
                    'databases_count' => 0,
                    'ftp_users_count' => 0
                ];
                
                // Zähle Websites
                try {
                    $websites = $serviceManager->IspconfigSOAP('sites_web_domain_get', [$client['client_id']]);
                    $clientDetails['websites_count'] = is_array($websites) ? count($websites) : 0;
                } catch (Exception $e) {
                    $clientDetails['websites_count'] = 0;
                }
                
                // Zähle E-Mail-Konten
                try {
                    $emailAccounts = $serviceManager->IspconfigSOAP('mail_user_get', [$client['client_id']]);
                    $clientDetails['email_accounts_count'] = is_array($emailAccounts) ? count($emailAccounts) : 0;
                } catch (Exception $e) {
                    $clientDetails['email_accounts_count'] = 0;
                }
                
                // Zähle Datenbanken
                try {
                    $databases = $serviceManager->IspconfigSOAP('sites_database_get', [$client['client_id']]);
                    $clientDetails['databases_count'] = is_array($databases) ? count($databases) : 0;
                } catch (Exception $e) {
                    $clientDetails['databases_count'] = 0;
                }
                
                // Zähle FTP-Benutzer
                try {
                    $ftpUsers = $serviceManager->IspconfigSOAP('sites_ftp_user_get', [$client['client_id']]);
                    $clientDetails['ftp_users_count'] = is_array($ftpUsers) ? count($ftpUsers) : 0;
                } catch (Exception $e) {
                    $clientDetails['ftp_users_count'] = 0;
                }
                
                $usersWithDetails[] = $clientDetails;
            }
            
            return $this->success($usersWithDetails);
            
        } catch (Exception $e) {
            $this->log('Error getting all users: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_getting_users') . ': ' . $e->getMessage());
        }
    }
    
    /**
     * Detaillierte Informationen eines Benutzers abrufen
     */
    private function getUserDetails($data) {
        $errors = $this->validate($data, [
            'client_id' => 'required|numeric'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $client = $serviceManager->IspconfigSOAP('client_get', [$data['client_id']]);
            
            if (!$client) {
                return $this->error($this->t('user_not_found'));
            }
            
            return $this->success($client);
            
        } catch (Exception $e) {
            $this->log('Error getting user details: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Websites eines Benutzers abrufen
     */
    private function getUserWebsites($data) {
        $errors = $this->validate($data, [
            'client_id' => 'required|numeric'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $websites = $serviceManager->IspconfigSOAP('sites_web_domain_get', [$data['client_id']]);
            
            if (!is_array($websites)) {
                return $this->success([]);
            }
            
            // Zusätzliche Informationen für jede Website
            $websitesWithDetails = [];
            foreach ($websites as $website) {
                $websiteDetails = [
                    'domain_id' => $website['domain_id'],
                    'domain' => $website['domain'],
                    'ip_address' => $website['ip_address'],
                    'system_user' => $website['system_user'],
                    'system_group' => $website['system_group'],
                    'hd_quota' => $website['hd_quota'],
                    'traffic_quota' => $website['traffic_quota'],
                    'active' => $website['active'],
                    'ssl_enabled' => $website['ssl'] ?? 'n',
                    'php_version' => $website['php'] ?? 'php-fpm',
                    'created_at' => $website['created_at'] ?? '',
                    'subdomains_count' => 0,
                    'ftp_users_count' => 0
                ];
                
                // Zähle Subdomains
                try {
                    $subdomains = $serviceManager->IspconfigSOAP('sites_web_subdomain_get', [$website['domain_id']]);
                    $websiteDetails['subdomains_count'] = is_array($subdomains) ? count($subdomains) : 0;
                } catch (Exception $e) {
                    $websiteDetails['subdomains_count'] = 0;
                }
                
                // Zähle FTP-Benutzer für diese Website
                try {
                    $ftpUsers = $serviceManager->IspconfigSOAP('sites_ftp_user_get', [$data['client_id']]);
                    $websiteFTPCount = 0;
                    if (is_array($ftpUsers)) {
                        foreach ($ftpUsers as $ftpUser) {
                            if ($ftpUser['parent_domain_id'] == $website['domain_id']) {
                                $websiteFTPCount++;
                            }
                        }
                    }
                    $websiteDetails['ftp_users_count'] = $websiteFTPCount;
                } catch (Exception $e) {
                    $websiteDetails['ftp_users_count'] = 0;
                }
                
                $websitesWithDetails[] = $websiteDetails;
            }
            
            return $this->success($websitesWithDetails);
            
        } catch (Exception $e) {
            $this->log('Error getting user websites: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * E-Mail-Konten eines Benutzers abrufen
     */
    private function getUserEmailAccounts($data) {
        $errors = $this->validate($data, [
            'client_id' => 'required|numeric'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $emailAccounts = $serviceManager->IspconfigSOAP('mail_user_get', [$data['client_id']]);
            
            if (!is_array($emailAccounts)) {
                return $this->success([]);
            }
            
            return $this->success($emailAccounts);
            
        } catch (Exception $e) {
            $this->log('Error getting user email accounts: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Datenbanken eines Benutzers abrufen
     */
    private function getUserDatabases($data) {
        $errors = $this->validate($data, [
            'client_id' => 'required|numeric'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $databases = $serviceManager->IspconfigSOAP('sites_database_get', [$data['client_id']]);
            
            if (!is_array($databases)) {
                return $this->success([]);
            }
            
            return $this->success($databases);
            
        } catch (Exception $e) {
            $this->log('Error getting user databases: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * FTP-Benutzer eines Benutzers abrufen
     */
    private function getUserFTPUsers($data) {
        $errors = $this->validate($data, [
            'client_id' => 'required|numeric'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $ftpUsers = $serviceManager->IspconfigSOAP('sites_ftp_user_get', [$data['client_id']]);
            
            if (!is_array($ftpUsers)) {
                return $this->success([]);
            }
            
            return $this->success($ftpUsers);
            
        } catch (Exception $e) {
            $this->log('Error getting user FTP users: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Status eines Benutzers aktualisieren
     */
    private function updateUserStatus($data) {
        $errors = $this->validate($data, [
            'user_id' => 'required|numeric',
            'status' => 'required|in:y,n'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            
            // Aktuelle Benutzer-Daten abrufen
            $currentClient = $serviceManager->IspconfigSOAP('client_get', [$data['user_id']]);
            
            if (!$currentClient || !is_array($currentClient)) {
                return $this->error($this->t('user_not_found'));
            }
            
            // Status aktualisieren
            $updateData = $currentClient;
            $updateData['active'] = $data['status'];
            
            $result = $serviceManager->IspconfigSOAP('client_update', [$data['user_id'], $updateData]);
            
            $statusText = $data['status'] === 'y' ? 'aktiviert' : 'deaktiviert';
            $this->log("Benutzer {$data['user_id']} {$statusText}");
            
            return $this->success($result, "Benutzer erfolgreich {$statusText}");
            
        } catch (Exception $e) {
            $this->log('Error updating user status: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    // Domain-Management Methoden
    
    /**
     * Alle Domains mit Benutzer-Zuordnung abrufen
     */
    private function getAllDomains() {
        try {
            $serviceManager = new ServiceManager();
            
            // ISPConfig-Verbindung wird automatisch in IspconfigSOAP geprüft
            // Alle Domains abrufen
            $domains = $serviceManager->IspconfigSOAP('sites_web_domain_get', []);
            
            if (!is_array($domains) || empty($domains)) {
                $this->log('Keine Domains gefunden oder ungültige Antwort', 'WARNING');
                return $this->success([]);
            }
            
            // Alle Benutzer abrufen für Zuordnung
            $clients = $serviceManager->IspconfigSOAP('client_get', []);
            $clientsMap = [];
            if (is_array($clients)) {
                foreach ($clients as $client) {
                    if (is_array($client) && isset($client['client_id'])) {
                        $clientsMap[$client['client_id']] = $client;
                    }
                }
            }
            
            // Domains mit Benutzer-Informationen erweitern
            $domainsWithUsers = [];
            foreach ($domains as $domain) {
                if (!is_array($domain)) {
                    continue;
                }
                
                $domainInfo = [
                    'domain_id' => $domain['domain_id'] ?? '',
                    'domain' => $domain['domain'] ?? '',
                    'ip_address' => $domain['ip_address'] ?? '',
                    'system_user' => $domain['system_user'] ?? '',
                    'system_group' => $domain['system_group'] ?? '',
                    'hd_quota' => $domain['hd_quota'] ?? 0,
                    'traffic_quota' => $domain['traffic_quota'] ?? 0,
                    'active' => $domain['active'] ?? 'n',
                    'ssl_enabled' => $domain['ssl'] ?? 'n',
                    'php_version' => $domain['php'] ?? 'php-fpm',
                    'created_at' => $domain['created_at'] ?? '',
                    'client_id' => $domain['client_id'] ?? null,
                    'assigned_user' => null
                ];
                
                // Benutzer-Informationen hinzufügen
                if (isset($domain['client_id']) && isset($clientsMap[$domain['client_id']])) {
                    $client = $clientsMap[$domain['client_id']];
                    $domainInfo['assigned_user'] = [
                        'client_id' => $client['client_id'],
                        'company_name' => $client['company_name'] ?? '',
                        'contact_name' => $client['contact_name'] ?? '',
                        'email' => $client['email'] ?? ''
                    ];
                }
                
                $domainsWithUsers[] = $domainInfo;
            }
            
            return $this->success($domainsWithUsers);
            
        } catch (Exception $e) {
            $this->log('Error getting all domains: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_getting_domains') . ': ' . $e->getMessage());
        }
    }
    
    /**
     * Domain einem Benutzer zuweisen
     */
    private function assignDomainToUser($data) {
        $errors = $this->validate($data, [
            'domain_id' => 'required|numeric',
            'client_id' => 'required|numeric'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            
            // Aktuelle Domain-Daten abrufen
            $currentDomain = $serviceManager->IspconfigSOAP('sites_web_domain_get', [$data['domain_id']]);
            
            if (!$currentDomain) {
                return $this->error($this->t('domain_not_found'));
            }
            
            // Domain-Daten aktualisieren
            $updateData = $currentDomain;
            $updateData['client_id'] = $data['client_id'];
            
            $result = $serviceManager->IspconfigSOAP('sites_web_domain_update', [$data['domain_id'], $updateData]);
            
            $this->log("Domain {$data['domain_id']} zu Benutzer {$data['client_id']} zugewiesen");
            
            return $this->success($result, $this->t('domain_assigned_successfully'));
            
        } catch (Exception $e) {
            $this->log('Error assigning domain: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Domain-Zuordnung von Benutzer entfernen
     */
    private function unassignDomainFromUser($data) {
        $errors = $this->validate($data, [
            'domain_id' => 'required|numeric'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            
            // Aktuelle Domain-Daten abrufen
            $currentDomain = $serviceManager->IspconfigSOAP('sites_web_domain_get', [$data['domain_id']]);
            
            if (!$currentDomain) {
                return $this->error($this->t('domain_not_found'));
            }
            
            // Domain-Daten aktualisieren (client_id auf null setzen)
            $updateData = $currentDomain;
            $updateData['client_id'] = null;
            
            $result = $serviceManager->IspconfigSOAP('sites_web_domain_update', [$data['domain_id'], $updateData]);
            
            $this->log("Domain-Zuordnung {$data['domain_id']} entfernt");
            
            return $this->success($result, $this->t('domain_unassigned_successfully'));
            
        } catch (Exception $e) {
            $this->log('Error unassigning domain: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Domain-Einstellungen aktualisieren
     */
    private function updateDomainSettings($data) {
        $errors = $this->validate($data, [
            'domain_id' => 'required|numeric',
            'settings' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            
            // Aktuelle Domain-Daten abrufen
            $currentDomain = $serviceManager->IspconfigSOAP('sites_web_domain_get', [$data['domain_id']]);
            
            if (!$currentDomain) {
                return $this->error($this->t('domain_not_found'));
            }
            
            // Einstellungen aktualisieren
            $updateData = $currentDomain;
            $settings = is_string($data['settings']) ? json_decode($data['settings'], true) : $data['settings'];
            
            foreach ($settings as $key => $value) {
                if (isset($updateData[$key])) {
                    $updateData[$key] = $value;
                }
            }
            
            $result = $serviceManager->IspconfigSOAP('sites_web_domain_update', [$data['domain_id'], $updateData]);
            
            $this->log("Domain-Einstellungen {$data['domain_id']} aktualisiert");
            
            return $this->success($result, $this->t('domain_settings_updated_successfully'));
            
        } catch (Exception $e) {
            $this->log('Error updating domain settings: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Vorschau der Domain-Änderungen
     */
    private function previewDomainChanges($data) {
        $errors = $this->validate($data, [
            'changes' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            
            $changes = is_string($data['changes']) ? json_decode($data['changes'], true) : $data['changes'];
            $preview = [];
            
            foreach ($changes as $change) {
                $changePreview = [
                    'type' => $change['type'],
                    'domain_id' => $change['domain_id'],
                    'domain' => $change['domain'],
                    'current' => [],
                    'new' => [],
                    'affected_user' => null
                ];
                
                // Aktuelle Domain-Daten abrufen
                $currentDomain = $serviceManager->IspconfigSOAP('sites_web_domain_get', [$change['domain_id']]);
                
                if ($currentDomain) {
                    // Aktuelle Benutzer-Informationen
                    if (isset($currentDomain['client_id'])) {
                        $clients = $serviceManager->IspconfigSOAP('client_get', [$currentDomain['client_id']]);
                        if ($clients) {
                            $changePreview['affected_user'] = [
                                'client_id' => $clients['client_id'],
                                'company_name' => $clients['company_name'] ?? '',
                                'contact_name' => $clients['contact_name'] ?? '',
                                'email' => $clients['email'] ?? ''
                            ];
                        }
                    }
                    
                    switch ($change['type']) {
                        case 'assign':
                            $changePreview['current']['client_id'] = $currentDomain['client_id'] ?? null;
                            $changePreview['new']['client_id'] = $change['client_id'];
                            break;
                            
                        case 'unassign':
                            $changePreview['current']['client_id'] = $currentDomain['client_id'] ?? null;
                            $changePreview['new']['client_id'] = null;
                            break;
                            
                        case 'update_settings':
                            $changePreview['current'] = $currentDomain;
                            $changePreview['new'] = array_merge($currentDomain, $change['settings']);
                            break;
                    }
                }
                
                $preview[] = $changePreview;
            }
            
            return $this->success($preview);
            
        } catch (Exception $e) {
            $this->log('Error previewing domain changes: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Domain-Änderungen ausführen
     */
    private function executeDomainChanges($data) {
        $errors = $this->validate($data, [
            'changes' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            
            $changes = is_string($data['changes']) ? json_decode($data['changes'], true) : $data['changes'];
            $results = [];
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($changes as $change) {
                try {
                    switch ($change['type']) {
                        case 'assign':
                            $result = $this->assignDomainToUser([
                                'domain_id' => $change['domain_id'],
                                'client_id' => $change['client_id']
                            ]);
                            break;
                            
                        case 'unassign':
                            $result = $this->unassignDomainFromUser([
                                'domain_id' => $change['domain_id']
                            ]);
                            break;
                            
                        case 'update_settings':
                            $result = $this->updateDomainSettings([
                                'domain_id' => $change['domain_id'],
                                'settings' => $change['settings']
                            ]);
                            break;
                            
                        default:
                            $result = ['success' => false, 'error' => 'Unknown change type'];
                    }
                    
                    if ($result['success']) {
                        $successCount++;
                    } else {
                        $errorCount++;
                    }
                    
                    $results[] = [
                        'domain_id' => $change['domain_id'],
                        'domain' => $change['domain'],
                        'type' => $change['type'],
                        'success' => $result['success'],
                        'message' => $result['message'] ?? ($result['error'] ?? 'Unknown error')
                    ];
                    
                } catch (Exception $e) {
                    $errorCount++;
                    $results[] = [
                        'domain_id' => $change['domain_id'],
                        'domain' => $change['domain'],
                        'type' => $change['type'],
                        'success' => false,
                        'message' => $e->getMessage()
                    ];
                }
            }
            
            $this->log("Domain-Änderungen ausgeführt: {$successCount} erfolgreich, {$errorCount} Fehler");
            
            return $this->success([
                'results' => $results,
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'total_count' => count($changes)
            ], "Änderungen ausgeführt: {$successCount} erfolgreich, {$errorCount} Fehler");
            
        } catch (Exception $e) {
            $this->log('Error executing domain changes: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    // DNS-Management Methoden
    
    /**
     * DNS-Einträge für eine Domain abrufen (ISPConfig + OVH)
     */
    private function getDomainDnsRecords($data) {
        $errors = $this->validate($data, [
            'domain' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $domain = $data['domain'];
            $records = [
                'ispconfig' => [],
                'ovh' => [],
                'combined' => []
            ];
            
            // ISPConfig DNS-Einträge abrufen
            $ispconfigRecords = $this->getIspconfigDnsRecords($domain);
            if ($ispconfigRecords) {
                $records['ispconfig'] = $ispconfigRecords;
            }
            
            // OVH DNS-Einträge abrufen
            $ovhRecords = $this->getOvhDnsRecords($data);
            if ($ovhRecords && $ovhRecords['success']) {
                $records['ovh'] = $ovhRecords['data'] ?? [];
            }
            
            // Kombinierte Ansicht erstellen
            $records['combined'] = $this->combineDnsRecords($records['ispconfig'], $records['ovh']);
            
            return $this->success($records);
            
        } catch (Exception $e) {
            $this->log('Error getting domain DNS records: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_getting_dns_records') . ': ' . $e->getMessage());
        }
    }
    
    /**
     * ISPConfig DNS-Einträge abrufen
     */
    private function getIspconfigDnsRecords($domain) {
        try {
            $serviceManager = new ServiceManager();
            
            // DNS-Zone für Domain finden
            $dnsZones = $serviceManager->IspconfigSOAP('dns_zone_get', []);
            if (!is_array($dnsZones)) {
                return [];
            }
            
            $zone = null;
            foreach ($dnsZones as $dnsZone) {
                if ($dnsZone['origin'] === $domain . '.') {
                    $zone = $dnsZone;
                    break;
                }
            }
            
            if (!$zone) {
                return [];
            }
            
            // DNS-Einträge für Zone abrufen
            $dnsRecords = $serviceManager->IspconfigSOAP('dns_rr_get', [$zone['id']]);
            
            if (!is_array($dnsRecords)) {
                return [];
            }
            
            $formattedRecords = [];
            foreach ($dnsRecords as $record) {
                $formattedRecords[] = [
                    'id' => $record['id'],
                    'type' => $record['type'],
                    'name' => $record['name'],
                    'value' => $record['value'],
                    'ttl' => $record['ttl'],
                    'priority' => $record['prio'] ?? null,
                    'source' => 'ispconfig',
                    'active' => $record['active'] ?? 'y'
                ];
            }
            
            return $formattedRecords;
            
        } catch (Exception $e) {
            $this->log('Error getting ISPConfig DNS records: ' . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    /**
     * OVH DNS-Einträge abrufen
     */
    private function getOvhDnsRecords($data) {
        $errors = $this->validate($data, [
            'domain' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $domain = $data['domain'];
            $serviceManager = new ServiceManager();
            
            // OVH API verwenden
            $ovhRecords = $serviceManager->OvhAPI('GET', "/domain/zone/{$domain}/record");
            
            if (!is_array($ovhRecords)) {
                return $this->success([]);
            }
            
            $formattedRecords = [];
            foreach ($ovhRecords as $recordId) {
                $record = $serviceManager->OvhAPI('GET', "/domain/zone/{$domain}/record/{$recordId}");
                
                if ($record) {
                    $formattedRecords[] = [
                        'id' => $record['id'],
                        'type' => $record['fieldType'],
                        'name' => $record['subDomain'],
                        'value' => $record['target'],
                        'ttl' => $record['ttl'],
                        'priority' => $record['priority'] ?? null,
                        'source' => 'ovh',
                        'active' => 'y'
                    ];
                }
            }
            
            return $this->success($formattedRecords);
            
        } catch (Exception $e) {
            $this->log('Error getting OVH DNS records: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_getting_ovh_dns_records') . ': ' . $e->getMessage());
        }
    }
    
    /**
     * DNS-Einträge kombinieren und konfliktfrei anzeigen
     */
    private function combineDnsRecords($ispconfigRecords, $ovhRecords) {
        $combined = [];
        
        // ISPConfig-Einträge hinzufügen
        foreach ($ispconfigRecords as $record) {
            $key = $record['type'] . '_' . $record['name'];
            $combined[$key] = $record;
            $combined[$key]['sources'] = ['ispconfig'];
            $combined[$key]['conflicts'] = [];
        }
        
        // OVH-Einträge hinzufügen und Konflikte prüfen
        foreach ($ovhRecords as $record) {
            $key = $record['type'] . '_' . $record['name'];
            
            if (isset($combined[$key])) {
                // Konflikt gefunden
                $combined[$key]['sources'][] = 'ovh';
                $combined[$key]['conflicts'][] = [
                    'source' => 'ovh',
                    'value' => $record['value'],
                    'ttl' => $record['ttl'],
                    'priority' => $record['priority']
                ];
            } else {
                // Neuer Eintrag
                $combined[$key] = $record;
                $combined[$key]['sources'] = ['ovh'];
                $combined[$key]['conflicts'] = [];
            }
        }
        
        return array_values($combined);
    }
    
    /**
     * DNS-Eintrag aktualisieren
     */
    private function updateDnsRecord($data) {
        $errors = $this->validate($data, [
            'record_id' => 'required',
            'domain' => 'required',
            'source' => 'required',
            'type' => 'required',
            'name' => 'required',
            'value' => 'required',
            'ttl' => 'required|numeric',
            'priority' => 'nullable|numeric'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $source = $data['source'];
            $domain = $data['domain'];
            
            if ($source === 'ispconfig') {
                return $this->updateIspconfigDnsRecord($data);
            } elseif ($source === 'ovh') {
                return $this->updateOvhDnsRecord($data);
            } else {
                return $this->error($this->t('invalid_dns_source'));
            }
            
        } catch (Exception $e) {
            $this->log('Error updating DNS record: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_updating_dns_record') . ': ' . $e->getMessage());
        }
    }
    
    /**
     * ISPConfig DNS-Eintrag aktualisieren
     */
    private function updateIspconfigDnsRecord($data) {
        $serviceManager = new ServiceManager();
        
        // Aktuellen Eintrag abrufen
        $currentRecord = $serviceManager->IspconfigSOAP('dns_rr_get', [$data['record_id']]);
        
        if (!$currentRecord) {
            return $this->error($this->t('dns_record_not_found'));
        }
        
        // Eintrag aktualisieren
        $updateData = $currentRecord;
        $updateData['type'] = $data['type'];
        $updateData['name'] = $data['name'];
        $updateData['value'] = $data['value'];
        $updateData['ttl'] = $data['ttl'];
        if (isset($data['priority'])) {
            $updateData['prio'] = $data['priority'];
        }
        
        $result = $serviceManager->IspconfigSOAP('dns_rr_update', [$data['record_id'], $updateData]);
        
        $this->log("ISPConfig DNS-Eintrag {$data['record_id']} aktualisiert");
        
        return $this->success($result, $this->t('dns_record_updated_successfully'));
    }
    
    /**
     * OVH DNS-Eintrag aktualisieren
     */
    private function updateOvhDnsRecord($data) {
        $serviceManager = new ServiceManager();
        $domain = $data['domain'];
        $recordId = $data['record_id'];
        
        $updateData = [
            'fieldType' => $data['type'],
            'subDomain' => $data['name'],
            'target' => $data['value'],
            'ttl' => $data['ttl']
        ];
        
        if (isset($data['priority'])) {
            $updateData['priority'] = $data['priority'];
        }
        
        $result = $serviceManager->OvhAPI('PUT', "/domain/zone/{$domain}/record/{$recordId}", $updateData);
        
        $this->log("OVH DNS-Eintrag {$recordId} für Domain {$domain} aktualisiert");
        
        return $this->success($result, $this->t('dns_record_updated_successfully'));
    }
    
    /**
     * DNS-Eintrag erstellen
     */
    private function createDnsRecord($data) {
        $errors = $this->validate($data, [
            'domain' => 'required',
            'source' => 'required',
            'type' => 'required',
            'name' => 'required',
            'value' => 'required',
            'ttl' => 'required|numeric',
            'priority' => 'nullable|numeric'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $source = $data['source'];
            
            if ($source === 'ispconfig') {
                return $this->createIspconfigDnsRecord($data);
            } elseif ($source === 'ovh') {
                return $this->createOvhDnsRecord($data);
            } else {
                return $this->error($this->t('invalid_dns_source'));
            }
            
        } catch (Exception $e) {
            $this->log('Error creating DNS record: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_creating_dns_record') . ': ' . $e->getMessage());
        }
    }
    
    /**
     * ISPConfig DNS-Eintrag erstellen
     */
    private function createIspconfigDnsRecord($data) {
        $serviceManager = new ServiceManager();
        $domain = $data['domain'];
        
        // DNS-Zone für Domain finden
        $dnsZones = $serviceManager->IspconfigSOAP('dns_zone_get', []);
        $zone = null;
        
        foreach ($dnsZones as $dnsZone) {
            if ($dnsZone['origin'] === $domain . '.') {
                $zone = $dnsZone;
                break;
            }
        }
        
        if (!$zone) {
            return $this->error($this->t('dns_zone_not_found'));
        }
        
        // Neuen Eintrag erstellen
        $newRecord = [
            'server_id' => $zone['server_id'],
            'zone' => $zone['id'],
            'name' => $data['name'],
            'type' => $data['type'],
            'value' => $data['value'],
            'ttl' => $data['ttl'],
            'active' => 'y'
        ];
        
        if (isset($data['priority'])) {
            $newRecord['prio'] = $data['priority'];
        }
        
        $result = $serviceManager->IspconfigSOAP('dns_rr_add', [$newRecord]);
        
        $this->log("ISPConfig DNS-Eintrag für Domain {$domain} erstellt");
        
        return $this->success($result, $this->t('dns_record_created_successfully'));
    }
    
    /**
     * OVH DNS-Eintrag erstellen
     */
    private function createOvhDnsRecord($data) {
        $serviceManager = new ServiceManager();
        $domain = $data['domain'];
        
        $newRecord = [
            'fieldType' => $data['type'],
            'subDomain' => $data['name'],
            'target' => $data['value'],
            'ttl' => $data['ttl']
        ];
        
        if (isset($data['priority'])) {
            $newRecord['priority'] = $data['priority'];
        }
        
        $result = $serviceManager->OvhAPI('POST', "/domain/zone/{$domain}/record", $newRecord);
        
        $this->log("OVH DNS-Eintrag für Domain {$domain} erstellt");
        
        return $this->success($result, $this->t('dns_record_created_successfully'));
    }
    
    /**
     * DNS-Eintrag löschen
     */
    private function deleteDnsRecord($data) {
        $errors = $this->validate($data, [
            'record_id' => 'required',
            'domain' => 'required',
            'source' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $source = $data['source'];
            
            if ($source === 'ispconfig') {
                return $this->deleteIspconfigDnsRecord($data);
            } elseif ($source === 'ovh') {
                return $this->deleteOvhDnsRecord($data);
            } else {
                return $this->error($this->t('invalid_dns_source'));
            }
            
        } catch (Exception $e) {
            $this->log('Error deleting DNS record: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_deleting_dns_record') . ': ' . $e->getMessage());
        }
    }
    
    /**
     * ISPConfig DNS-Eintrag löschen
     */
    private function deleteIspconfigDnsRecord($data) {
        $serviceManager = new ServiceManager();
        
        $result = $serviceManager->IspconfigSOAP('dns_rr_delete', [$data['record_id']]);
        
        $this->log("ISPConfig DNS-Eintrag {$data['record_id']} gelöscht");
        
        return $this->success($result, $this->t('dns_record_deleted_successfully'));
    }
    
    /**
     * OVH DNS-Eintrag löschen
     */
    private function deleteOvhDnsRecord($data) {
        $serviceManager = new ServiceManager();
        $domain = $data['domain'];
        $recordId = $data['record_id'];
        
        $result = $serviceManager->OvhAPI('DELETE', "/domain/zone/{$domain}/record/{$recordId}");
        
        $this->log("OVH DNS-Eintrag {$recordId} für Domain {$domain} gelöscht");
        
        return $this->success($result, $this->t('dns_record_deleted_successfully'));
    }
    
    /**
     * DNS-Einträge zwischen ISPConfig und OVH synchronisieren
     */
    private function syncDnsRecords($data) {
        $errors = $this->validate($data, [
            'domain' => 'required',
            'sync_direction' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $domain = $data['domain'];
            $direction = $data['sync_direction']; // 'ispconfig_to_ovh' oder 'ovh_to_ispconfig'
            
            $results = [];
            
            if ($direction === 'ispconfig_to_ovh') {
                // ISPConfig zu OVH synchronisieren
                $ispconfigRecords = $this->getIspconfigDnsRecords($domain);
                $ovhRecords = $this->getOvhDnsRecords(['domain' => $domain]);
                
                if ($ovhRecords && $ovhRecords['success']) {
                    $ovhRecords = $ovhRecords['data'];
                    
                    foreach ($ispconfigRecords as $ispRecord) {
                        // Prüfen ob OVH-Eintrag existiert
                        $exists = false;
                        foreach ($ovhRecords as $ovhRecord) {
                            if ($ovhRecord['type'] === $ispRecord['type'] && 
                                $ovhRecord['name'] === $ispRecord['name']) {
                                $exists = true;
                                break;
                            }
                        }
                        
                        if (!$exists) {
                            // Eintrag in OVH erstellen
                            $result = $this->createOvhDnsRecord([
                                'domain' => $domain,
                                'source' => 'ovh',
                                'type' => $ispRecord['type'],
                                'name' => $ispRecord['name'],
                                'value' => $ispRecord['value'],
                                'ttl' => $ispRecord['ttl'],
                                'priority' => $ispRecord['priority']
                            ]);
                            
                            $results[] = [
                                'action' => 'created',
                                'source' => 'ovh',
                                'type' => $ispRecord['type'],
                                'name' => $ispRecord['name'],
                                'success' => $result['success'],
                                'message' => $result['message'] ?? ''
                            ];
                        }
                    }
                }
            } elseif ($direction === 'ovh_to_ispconfig') {
                // OVH zu ISPConfig synchronisieren
                $ovhRecords = $this->getOvhDnsRecords(['domain' => $domain]);
                $ispconfigRecords = $this->getIspconfigDnsRecords($domain);
                
                if ($ovhRecords && $ovhRecords['success']) {
                    $ovhRecords = $ovhRecords['data'];
                    
                    foreach ($ovhRecords as $ovhRecord) {
                        // Prüfen ob ISPConfig-Eintrag existiert
                        $exists = false;
                        foreach ($ispconfigRecords as $ispRecord) {
                            if ($ispRecord['type'] === $ovhRecord['type'] && 
                                $ispRecord['name'] === $ovhRecord['name']) {
                                $exists = true;
                                break;
                            }
                        }
                        
                        if (!$exists) {
                            // Eintrag in ISPConfig erstellen
                            $result = $this->createIspconfigDnsRecord([
                                'domain' => $domain,
                                'source' => 'ispconfig',
                                'type' => $ovhRecord['type'],
                                'name' => $ovhRecord['name'],
                                'value' => $ovhRecord['value'],
                                'ttl' => $ovhRecord['ttl'],
                                'priority' => $ovhRecord['priority']
                            ]);
                            
                            $results[] = [
                                'action' => 'created',
                                'source' => 'ispconfig',
                                'type' => $ovhRecord['type'],
                                'name' => $ovhRecord['name'],
                                'success' => $result['success'],
                                'message' => $result['message'] ?? ''
                            ];
                        }
                    }
                }
            }
            
            $this->log("DNS-Synchronisation für Domain {$domain} abgeschlossen: " . count($results) . " Aktionen");
            
            return $this->success([
                'results' => $results,
                'total_actions' => count($results)
            ], $this->t('dns_sync_completed_successfully'));
            
        } catch (Exception $e) {
            $this->log('Error syncing DNS records: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_syncing_dns_records') . ': ' . $e->getMessage());
        }
    }
}
?>