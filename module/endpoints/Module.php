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
    
    public function handleAjaxRequest($action, $data) {
        switch ($action) {
            case 'get_translations':
                return $this->getTranslations();
            case 'get_stats':
                return $this->success($this->getStats());
            default:
                // Endpoints Module leitet Requests an andere Module weiter
                // Dies wird über das Frontend gehandhabt
                return $this->error($this->t('module_does_not_handle_requests'));
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