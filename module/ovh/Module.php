<?php
/**
 * OVH Module
 * Verwaltung von Domains und VPS
 */

require_once dirname(dirname(__FILE__)) . '/ModuleBase.php';

class OvhModule extends ModuleBase {
    
    public function getContent() {
        return $this->render('main');
    }
    
    public function handleAjaxRequest($action, $data) {
        switch ($action) {
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
                
            default:
                return $this->error('Unknown action: ' . $action);
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
            $api = new OVHAPI();
            
            // Prüfe Verfügbarkeit
            $availability = $api->checkDomainAvailability($data['domain']);
            
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
            
            $result = $api->orderDomain($order_config);
            
            $this->log("Domain {$data['domain']} ordered for {$data['duration']} year(s)");
            
            return $this->success($result, 'Domain erfolgreich bestellt');
            
        } catch (Exception $e) {
            $this->log('Error ordering domain: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
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
            $api = new OVHAPI();
            $zone = $api->getDomainZone($data['domain']);
            
            return $this->success($zone);
            
        } catch (Exception $e) {
            $this->log('Error getting domain zone: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
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
            $api = new OVHAPI();
            $records = $api->getDNSRecords($data['domain']);
            
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
            $api = new OVHAPI();
            
            $record_config = [
                'fieldType' => $data['type'],
                'subDomain' => $data['subdomain'],
                'target' => $data['target'],
                'ttl' => $data['ttl'] ?? 3600
            ];
            
            $result = $api->createDNSRecord($data['domain'], $record_config);
            
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
            $api = new OVHAPI();
            $result = $api->refreshDNSZone($data['domain']);
            
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
}
?>