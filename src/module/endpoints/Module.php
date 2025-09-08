<?php
/**
 * Endpoints Module
 * API Endpoints Tester für Entwickler und Admins
 */

require_once dirname(dirname(__FILE__)) . '/ModuleBase.php';

class EndpointsModule extends ModuleBase {
    
    public function getContent() {
        $translations = $this->tMultiple([
            'module_title', 'api_endpoints_tester', 'test_api_endpoints', 'proxmox_api_endpoints',
            'load_nodes', 'load_storages', 'vm_config', 'vm_status', 'clone_vm',
            'ispconfig_api_endpoints', 'load_clients', 'server_config', 'website_details',
            'ftp_user_test', 'ovh_api_endpoints', 'domain_zone', 'dns_records', 'vps_ips',
            'ip_details', 'vps_control', 'create_dns_record', 'refresh_dns_zone', 'failover_ips',
            'virtual_mac_api_endpoints', 'all_virtual_macs', 'dedicated_servers', 'mac_details',
            'create_virtual_mac', 'assign_ip', 'create_reverse_dns', 'database_api_endpoints',
            'all_databases', 'create_database', 'delete_database', 'email_api_endpoints',
            'all_emails', 'create_email', 'delete_email', 'system_endpoints', 'activity_log',
            'session_heartbeat', 'custom_endpoint_test', 'module', 'select_module', 'action',
            'parameters', 'test_endpoint', 'endpoint_response', 'success', 'error', 'copy',
            'response_copied', 'copy_failed', 'testing', 'invalid_json', 'total_endpoints',
            'active_modules', 'save', 'cancel', 'edit', 'delete', 'create', 'refresh', 'actions', 'status'
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
            'module_title', 'api_endpoints_tester', 'test_api_endpoints', 'proxmox_api_endpoints',
            'load_nodes', 'load_storages', 'vm_config', 'vm_status', 'clone_vm',
            'ispconfig_api_endpoints', 'load_clients', 'server_config', 'website_details',
            'ftp_user_test', 'ovh_api_endpoints', 'domain_zone', 'dns_records', 'vps_ips',
            'ip_details', 'vps_control', 'create_dns_record', 'refresh_dns_zone', 'failover_ips',
            'virtual_mac_api_endpoints', 'all_virtual_macs', 'dedicated_servers', 'mac_details',
            'create_virtual_mac', 'assign_ip', 'create_reverse_dns', 'database_api_endpoints',
            'all_databases', 'create_database', 'delete_database', 'email_api_endpoints',
            'all_emails', 'create_email', 'delete_email', 'system_endpoints', 'activity_log',
            'session_heartbeat', 'custom_endpoint_test', 'module', 'select_module', 'action',
            'parameters', 'test_endpoint', 'endpoint_response', 'success', 'error', 'copy',
            'response_copied', 'copy_failed', 'testing', 'invalid_json', 'total_endpoints',
            'active_modules', 'save', 'cancel', 'edit', 'delete', 'create', 'refresh', 'actions', 'status'
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
            case 'get_translations':
                return $this->getTranslations();
            case 'get_stats':
                return $this->success($this->getStats());
            case 'proxy_endpoint':
                return $this->proxyEndpoint($data);
            default:
                // Endpoints Module leitet Requests an andere Module weiter
                // Dies wird über das Frontend gehandhabt
                return $this->error($this->t('module_does_not_handle_requests'));
        }
    }

    /**
     * Führt einen Endpoint testweise direkt aus, ohne dass das Zielmodul aktiviert sein muss
     */
    private function proxyEndpoint($data) {
        $errors = $this->validate($data, [
            'module' => 'required',
            'action' => 'required'
        ]);
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }

        $targetModule = strtolower($data['module']);
        $action = $data['action'];
        $params = $data;
        unset($params['module'], $params['action']);

        try {
            $serviceManager = new ServiceManager();

            switch ($targetModule) {
                case 'proxmox':
                    return $this->proxyProxmox($serviceManager, $action, $params);
                case 'ovh':
                    return $this->proxyOvh($serviceManager, $action, $params);
                case 'ispconfig':
                    return $this->proxyIspconfig($serviceManager, $action, $params);
                case 'ogp':
                    return $this->proxyOgp($serviceManager, $action, $params);
                default:
                    return $this->error('Unsupported module for proxy: ' . $targetModule);
            }
        } catch (Exception $e) {
            $this->log('Proxy error: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }

    private function proxyProxmox($serviceManager, $action, $params) {
        switch ($action) {
            case 'get_proxmox_nodes':
                $resp = $serviceManager->ProxmoxAPI('get', '/nodes');
                return $this->success($resp);
            case 'get_proxmox_storages':
                if (empty($params['node'])) return $this->error('node required');
                $resp = $serviceManager->ProxmoxAPI('get', "/nodes/{$params['node']}/storage");
                return $this->success($resp);
            case 'get_vm_config':
                if (empty($params['node']) || empty($params['vmid'])) return $this->error('node and vmid required');
                $resp = $serviceManager->ProxmoxAPI('get', "/nodes/{$params['node']}/qemu/{$params['vmid']}/config");
                return $this->success($resp);
            case 'get_vm_status':
                if (empty($params['node']) || empty($params['vmid'])) return $this->error('node and vmid required');
                $resp = $serviceManager->ProxmoxAPI('get', "/nodes/{$params['node']}/qemu/{$params['vmid']}/status/current");
                return $this->success($resp);
            case 'clone_vm':
                if (empty($params['node']) || empty($params['vmid']) || empty($params['newid']) || empty($params['name'])) {
                    return $this->error('node, vmid, newid, name required');
                }
                $clone_config = [
                    'newid' => $params['newid'],
                    'name' => $params['name'],
                    'full' => true,
                    'target' => $params['node']
                ];
                $resp = $serviceManager->ProxmoxAPI('post', "/nodes/{$params['node']}/qemu/{$params['vmid']}/clone", $clone_config);
                return $this->success($resp);
            default:
                return $this->error('Unsupported proxmox action: ' . $action);
        }
    }

    private function proxyOvh($serviceManager, $action, $params) {
        switch ($action) {
            case 'get_ovh_domain_zone':
                if (empty($params['domain'])) return $this->error('domain required');
                $resp = $serviceManager->OvhAPI('GET', "/domain/zone/{$params['domain']}");
                return $this->success($resp);
            case 'get_ovh_dns_records':
                if (empty($params['domain'])) return $this->error('domain required');
                $resp = $serviceManager->OvhAPI('GET', "/domain/zone/{$params['domain']}/record");
                return $this->success($resp);
            case 'get_vps_ips':
                if (empty($params['vps_name'])) return $this->error('vps_name required');
                $resp = $serviceManager->OvhAPI('GET', "/vps/{$params['vps_name']}/ips");
                return $this->success($resp);
            case 'get_vps_ip_details':
                if (empty($params['vps_name']) || empty($params['ip'])) return $this->error('vps_name and ip required');
                $resp = $serviceManager->OvhAPI('GET', "/vps/{$params['vps_name']}/ips/{$params['ip']}");
                return $this->success($resp);
            case 'control_ovh_vps':
                if (empty($params['vps_name']) || empty($params['vps_action'])) return $this->error('vps_name and vps_action required');
                $map = ['start' => 'start', 'stop' => 'stop', 'reboot' => 'reboot', 'reset' => 'reboot'];
                $endpoint = $map[$params['vps_action']] ?? 'reboot';
                $resp = $serviceManager->OvhAPI('POST', "/vps/{$params['vps_name']}/{$endpoint}", []);
                return $this->success($resp);
            case 'create_dns_record':
                if (empty($params['domain']) || empty($params['type']) || empty($params['subdomain']) || empty($params['target'])) {
                    return $this->error('domain, type, subdomain, target required');
                }
                $record_config = [
                    'fieldType' => $params['type'],
                    'subDomain' => $params['subdomain'],
                    'target' => $params['target'],
                    'ttl' => $params['ttl'] ?? 3600
                ];
                $resp = $serviceManager->OvhAPI('POST', "/domain/zone/{$params['domain']}/record", $record_config);
                return $this->success($resp);
            case 'refresh_dns_zone':
                if (empty($params['domain'])) return $this->error('domain required');
                $resp = $serviceManager->OvhAPI('POST', "/domain/zone/{$params['domain']}/refresh");
                return $this->success($resp);
            case 'get_ovh_failover_ips':
                $resp = $serviceManager->OvhAPI('GET', '/ip');
                return $this->success($resp);
            default:
                return $this->error('Unsupported ovh action: ' . $action);
        }
    }

    private function proxyIspconfig($serviceManager, $action, $params) {
        switch ($action) {
            case 'get_ispconfig_server_config':
                $resp = $serviceManager->IspconfigAPI('get', 'server_get');
                return $this->success($resp);
            default:
                return $this->error('Unsupported ispconfig action: ' . $action);
        }
    }

    private function proxyOgp($serviceManager, $action, $params) {
        switch ($action) {
            case 'list_servers':
                $resp = $serviceManager->OGPAPI('post', 'server/list');
                return $this->success($resp);
            case 'list_games':
                $resp = $serviceManager->OGPAPI('post', 'user_games/list_games', ['system' => 'linux', 'architecture' => '64']);
                return $this->success($resp);
            case 'list_user_servers':
                $resp = $serviceManager->OGPAPI('post', 'user_games/list_servers');
                return $this->success($resp);
            case 'server_status':
                if (empty($params['server_id'])) return $this->error('server_id required');
                $resp = $serviceManager->OGPAPI('post', 'server/status', ['remote_server_id' => $params['server_id']]);
                return $this->success($resp);
            default:
                return $this->error('Unsupported ogp action: ' . $action);
        }
    }

    public function getStats() {
        // Zähle verfügbare Endpoints
        $modules = getEnabledModules();
        $endpoint_count = 0;
        
        // Geschätzte Anzahl von Endpoints pro Modul
        $estimated_endpoints = [
            'admin' => 10,
            'proxmox' => 6,
            'ispconfig' => 7,
            'ovh' => 11,
            'virtual-mac' => 12,
            'network' => 1,
            'database' => 3,
            'email' => 3
        ];
        
        foreach ($modules as $key => $module) {
            if (isset($estimated_endpoints[$key])) {
                $endpoint_count += $estimated_endpoints[$key];
            }
        }
        
        return [
            'total_endpoints' => $endpoint_count,
            'active_modules' => count($modules)
        ];
    }
    
    private function getTranslations() {
        $translations = $this->tMultiple([
            'response_copied', 'copy_failed', 'testing', 'invalid_json'
        ]);
        
        return $this->success($translations);
    }
}
?>