<?php
/**
 * OVH Module
 * Verwaltung von Domains und VPS
 */

require_once dirname(dirname(__FILE__)) . '/ModuleBase.php';

// Leichter Adapter, damit der in diesem Modul verwendete Typ OVHAPI existiert
// und über den vorhandenen ServiceManager die OVH-Endpunkte anspricht.
class OVHAPI {
    private $serviceManager;

    public function __construct() {
        $this->serviceManager = new ServiceManager();
    }

    private function request($method, $path, $data = null) {
        return $this->serviceManager->OvhAPI($method, $path, $data);
    }

    public function getVPSInfo($vpsName) {
        return $this->request('GET', "/vps/{$vpsName}");
    }

    public function getVPSIPs($vpsName) {
        return $this->request('GET', "/vps/{$vpsName}/ips");
    }

    public function getVPSIPDetails($vpsName, $ip) {
        return $this->request('GET', "/vps/{$vpsName}/ips/{$ip}");
    }

    public function controlVPS($vpsName, $action) {
        $map = [
            'start' => 'start',
            'stop' => 'stop',
            'reboot' => 'reboot',
            'reset' => 'reboot' // Fallback
        ];
        $endpoint = $map[$action] ?? 'reboot';
        return $this->request('POST', "/vps/{$vpsName}/{$endpoint}", []);
    }

    public function getFailoverIPs() {
        return $this->request('GET', '/ip');
    }

    public function getFailoverIPDetails($ip) {
        $encoded = urlencode($ip);
        return $this->request('GET', "/ip/{$encoded}");
    }

    public function createVirtualMacForIP($ip, $config) {
        $encoded = urlencode($ip);
        return $this->request('POST', "/ip/{$encoded}/virtualMac", $config);
    }

    public function getAllDomains() {
        $domains = $this->request('GET', '/domain');
        if (is_array($domains)) {
            $result = [];
            foreach ($domains as $domainName) {
                $result[] = ['name' => $domainName, 'state' => 'ok'];
            }
            return $result;
        }
        return [];
    }

    public function getAllVPS() {
        $vps = $this->request('GET', '/vps');
        return is_array($vps) ? $vps : [];
    }
}

class OvhModule extends ModuleBase {
    
    public function getContent() {
        $translations = $this->tMultiple([
            'module_title', 'order_domain_ovh', 'domain_name', 'duration', 'order_domain',
            'get_vps_info', 'vps_name', 'get_vps_info_button', 'vps_information', 'ip_address',
            'mac_address', 'dns_management', 'create_dns_record', 'domain', 'record_type',
            'subdomain', 'target', 'ttl_seconds', 'vps_control', 'action', 'reboot', 'start',
            'stop', 'reset', 'execute_vps_action', 'failover_ips', 'load_failover_ips',
            'quick_actions', 'check_domain_availability', 'show_dns_records', 'refresh_dns_zone',
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
            'module_title', 'order_domain_ovh', 'domain_name', 'duration', 'order_domain',
            'get_vps_info', 'vps_name', 'get_vps_info_button', 'vps_information', 'ip_address',
            'mac_address', 'dns_management', 'create_dns_record', 'domain', 'record_type',
            'subdomain', 'target', 'ttl_seconds', 'vps_control', 'action', 'reboot', 'start',
            'stop', 'reset', 'execute_vps_action', 'failover_ips', 'load_failover_ips',
            'quick_actions', 'check_domain_availability', 'show_dns_records', 'refresh_dns_zone',
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
                
            // Domain Management
            case 'order_domain':
                return $this->orderDomain($data);
                
            case 'get_ovh_domain_zone':
                return $this->getDomainZone($data);
                
            case 'get_ovh_dns_records':
                return $this->getDNSRecords($data);
                
            case 'create_dns_record':
                return $this->createDNSRecord($data);
                
            case 'refresh_dns_zone':
                return $this->refreshDNSZone($data);
                
            // VPS Management
            case 'get_vps_info':
                return $this->getVPSInfo($data);
                
            case 'get_vps_ips':
                return $this->getVPSIPs($data);
                
            case 'get_vps_ip_details':
                return $this->getVPSIPDetails($data);
                
            case 'control_ovh_vps':
                return $this->controlVPS($data);
                
            // Failover IP Management
            case 'get_ovh_failover_ips':
                return $this->getFailoverIPs();
                
            case 'create_ovh_virtual_mac':
                return $this->createVirtualMacForIP($data);
                
            case 'get_translations':
                return $this->getTranslations();
                
            default:
                return $this->error($this->t('unknown_action') . ': ' . $action);
        }
    }
    
    private function orderDomain($data) {
        $errors = $this->validate($data, [
            'domain' => 'required|min:3',
            'duration' => 'required|numeric|min:1|max:10'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            
            // Prüfe Verfügbarkeit
            $availability = $serviceManager->OvhAPI('get', "/domain/{$data['domain']}/availability");
            
            if (!$availability['available']) {
                return $this->error('Domain ist nicht verfügbar');
            }
            
            // Domain bestellen
            $order_config = [
                'domain' => $data['domain'],
                'duration' => $data['duration'],
                'owner_contact' => 'default', // Sollte aus Benutzerdaten kommen
                'admin_contact' => 'default',
                'tech_contact' => 'default',
                'billing_contact' => 'default',
                'dns_zone' => true
            ];
            
            $result = $serviceManager->OvhAPI('post', '/domain/order', $order_config);
            
            $this->log("Domain {$data['domain']} ordered for {$data['duration']} year(s)");
            
            return $this->success($result, $this->t('domain_ordered_successfully'));
            
        } catch (Exception $e) {
            $this->log('Error ordering domain: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_ordering_domain') . ': ' . $e->getMessage());
        }
    }
    
    private function getDomainZone($data) {
        $errors = $this->validate($data, [
            'domain' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $zone = $serviceManager->OvhAPI('get', "/domain/zone/{$data['domain']}");
            
            return $this->success($zone);
            
        } catch (Exception $e) {
            $this->log('Error getting domain zone: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_getting_domain_zone') . ': ' . $e->getMessage());
        }
    }
    
    private function getDNSRecords($data) {
        $errors = $this->validate($data, [
            'domain' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $records = $serviceManager->OvhAPI('get', "/domain/zone/{$data['domain']}/record");
            
            return $this->success($records);
            
        } catch (Exception $e) {
            $this->log('Error getting DNS records: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function createDNSRecord($data) {
        $errors = $this->validate($data, [
            'domain' => 'required',
            'type' => 'required',
            'subdomain' => 'required',
            'target' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            
            $record_config = [
                'fieldType' => $data['type'],
                'subDomain' => $data['subdomain'],
                'target' => $data['target'],
                'ttl' => $data['ttl'] ?? 3600
            ];
            
            $result = $serviceManager->OvhAPI('post', "/domain/zone/{$data['domain']}/record", $record_config);
            
            $this->log("DNS record created for {$data['domain']}: {$data['subdomain']} {$data['type']} {$data['target']}");
            
            return $this->success($result, 'DNS-Eintrag erfolgreich erstellt');
            
        } catch (Exception $e) {
            $this->log('Error creating DNS record: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function refreshDNSZone($data) {
        $errors = $this->validate($data, [
            'domain' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->OvhAPI('post', "/domain/zone/{$data['domain']}/refresh");
            
            $this->log("DNS zone refreshed for {$data['domain']}");
            
            return $this->success($result, 'DNS-Zone erfolgreich aktualisiert');
            
        } catch (Exception $e) {
            $this->log('Error refreshing DNS zone: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function getVPSInfo($data) {
        $errors = $this->validate($data, [
            'vps_name' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $api = new OVHAPI();
            $info = $api->getVPSInfo($data['vps_name']);
            
            // IPs und MACs abrufen
            $ips = $api->getVPSIPs($data['vps_name']);
            $macs = [];
            
            foreach ($ips as $ip) {
                $ip_details = $api->getVPSIPDetails($data['vps_name'], $ip);
                if (isset($ip_details['macAddress'])) {
                    $macs[] = $ip_details['macAddress'];
                }
            }
            
            $result = [
                'info' => $info,
                'ip' => implode(', ', $ips),
                'mac' => implode(', ', array_unique($macs))
            ];
            
            return $this->success($result);
            
        } catch (Exception $e) {
            $this->log('Error getting VPS info: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function getVPSIPs($data) {
        $errors = $this->validate($data, [
            'vps_name' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $api = new OVHAPI();
            $ips = $api->getVPSIPs($data['vps_name']);
            
            return $this->success($ips);
            
        } catch (Exception $e) {
            $this->log('Error getting VPS IPs: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function getVPSIPDetails($data) {
        $errors = $this->validate($data, [
            'vps_name' => 'required',
            'ip' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $api = new OVHAPI();
            $details = $api->getVPSIPDetails($data['vps_name'], $data['ip']);
            
            return $this->success($details);
            
        } catch (Exception $e) {
            $this->log('Error getting VPS IP details: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function controlVPS($data) {
        $errors = $this->validate($data, [
            'vps_name' => 'required',
            'vps_action' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        $allowed_actions = ['start', 'stop', 'reboot', 'reset'];
        if (!in_array($data['vps_action'], $allowed_actions)) {
            return $this->error('Invalid VPS action');
        }
        
        try {
            $api = new OVHAPI();
            $result = $api->controlVPS($data['vps_name'], $data['vps_action']);
            
            $this->log("VPS {$data['vps_name']} action {$data['vps_action']} executed");
            
            return $this->success($result, 'VPS-Aktion erfolgreich ausgeführt');
            
        } catch (Exception $e) {
            $this->log('Error controlling VPS: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function getFailoverIPs() {
        try {
            $api = new OVHAPI();
            $ips = $api->getFailoverIPs();
            
            $detailed_ips = [];
            foreach ($ips as $ip) {
                try {
                    $details = $api->getFailoverIPDetails($ip);
                    $detailed_ips[] = $details;
                } catch (Exception $e) {
                    // Skip IPs that can't be fetched
                    continue;
                }
            }
            
            return $this->success($detailed_ips);
            
        } catch (Exception $e) {
            $this->log('Error getting failover IPs: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function createVirtualMacForIP($data) {
        $errors = $this->validate($data, [
            'ip' => 'required',
            'type' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $api = new OVHAPI();
            
            $mac_config = [
                'type' => $data['type'],
                'virtualMachineName' => $data['vm_name'] ?? 'vm-' . time()
            ];
            
            $result = $api->createVirtualMacForIP($data['ip'], $mac_config);
            
            $this->log("Virtual MAC created for IP {$data['ip']}");
            
            return $this->success($result, 'Virtual MAC erfolgreich erstellt');
            
        } catch (Exception $e) {
            $this->log('Error creating virtual MAC for IP: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    public function getStats() {
        try {
            $api = new OVHAPI();
            
            $domains = $api->getAllDomains();
            $vps_list = $api->getAllVPS();
            
            $active_domains = 0;
            foreach ($domains as $domain) {
                if ($domain['state'] === 'ok') {
                    $active_domains++;
                }
            }
            
            return [
                'domains_total' => count($domains),
                'domains_active' => $active_domains,
                'vps_total' => count($vps_list)
            ];
            
        } catch (Exception $e) {
            return [
                'domains_total' => 0,
                'domains_active' => 0,
                'vps_total' => 0
            ];
        }
    }
    
    private function getTranslations() {
        $translations = $this->tMultiple([
            'domain_ordered_successfully', 'error_ordering_domain', 'domain_not_available',
            'dns_record_created_successfully', 'error_creating_dns_record',
            'dns_zone_updated_successfully', 'error_refreshing_dns_zone',
            'error_getting_vps_info', 'error_getting_vps_ips', 'error_getting_vps_ip_details',
            'vps_action_executed_successfully', 'error_controlling_vps',
            'failover_ips_loaded', 'error_getting_failover_ips', 'no_failover_ips_found',
            'virtual_mac_created_successfully', 'error_creating_virtual_mac',
            'network_error', 'unknown_error', 'enter_domain', 'checking_availability',
            'domain_availability_not_implemented', 'dns_records_loaded'
        ]);
        
        return $this->success($translations);
    }
}
?>