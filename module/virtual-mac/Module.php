<?php
/**
 * Virtual MAC Module
 * Verwaltung von Virtual MAC Adressen
 */

require_once dirname(dirname(__FILE__)) . '/ModuleBase.php';

// ServiceManager sicherstellen, dass er geladen ist
if (!class_exists('ServiceManager')) {
    require_once dirname(dirname(__FILE__)) . '/../framework.php';
}

class VirtualMacModule extends ModuleBase {
    
    public function getContent() {
        // Lade Dedicated Servers für Dropdowns
        $servers = $this->getDedicatedServersList();
        
        return $this->render('main', [
            'servers' => $servers
        ]);
    }
    
    public function handleAjaxRequest($action, $data) {
        switch ($action) {
            // Virtual MAC Management
            case 'create_virtual_mac':
                return $this->createVirtualMac($data);
                
            case 'get_virtual_mac_details':
                return $this->getVirtualMacDetails($data);
                
            case 'get_all_virtual_macs':
                return $this->getAllVirtualMacs();
                
            case 'delete_virtual_mac':
                return $this->deleteVirtualMac($data);
                
            // IP Management
            case 'assign_ip_to_virtual_mac':
                return $this->assignIPToVirtualMac($data);
                
            case 'remove_ip_from_virtual_mac':
                return $this->removeIPFromVirtualMac($data);
                
            // Reverse DNS
            case 'create_reverse_dns':
                return $this->createReverseDNS($data);
                
            case 'query_reverse_dns':
                return $this->queryReverseDNS($data);
                
            case 'delete_reverse_dns':
                return $this->deleteReverseDNS($data);
                
            // Helper
            case 'get_dedicated_servers':
                return $this->getDedicatedServers();
                
            case 'get_virtual_macs_for_service':
                return $this->getVirtualMacsForService($data);
                
            case 'load_virtual_mac_overview':
                return $this->loadVirtualMacOverview();
                
            default:
                return $this->error('Unknown action: ' . $action);
        }
    }
    
    private function createVirtualMac($data) {
        $errors = $this->validate($data, [
            'service_name' => 'required',
            'virtual_network_interface' => 'required',
            'type' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            
            $result = $serviceManager->createVirtualMac(
                $data['service_name'],
                $data['virtual_network_interface'],
                $data['type'] ?? 'ovh'
            );
            
            $this->log("Virtual MAC created for service {$data['service_name']}");
            
            return $this->success($result, 'Virtual MAC erfolgreich erstellt');
            
        } catch (Exception $e) {
            $this->log('Error creating virtual MAC: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function getVirtualMacDetails($data) {
        $errors = $this->validate($data, [
            'service_name' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $details = $serviceManager->getVirtualMacDetails($data['service_name'], null);
            
            return $this->success($details);
            
        } catch (Exception $e) {
            $this->log('Error getting virtual MAC details: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function getAllVirtualMacs() {
        try {
            $serviceManager = new ServiceManager();
            
            // Alle Dedicated Servers abrufen
            $servers = $serviceManager->OvhAPI('get', '/dedicated/server');
            $all_macs = [];
            
            foreach ($servers as $server) {
                try {
                    $server_macs = $serviceManager->getVirtualMacAddresses($server);
                    
                    foreach ($server_macs as $mac) {
                        $mac_details = $serviceManager->getVirtualMacDetails($server, $mac);
                        $mac_details['service_name'] = $server;
                        $all_macs[] = $mac_details;
                    }
                } catch (Exception $e) {
                    // Server ohne Virtual MACs überspringen
                    continue;
                }
            }
            
            $this->log('Retrieved ' . count($all_macs) . ' virtual MACs');
            
            return $this->success($all_macs);
            
        } catch (Exception $e) {
            $this->log('Error retrieving all virtual MACs: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function deleteVirtualMac($data) {
        $errors = $this->validate($data, [
            'service_name' => 'required',
            'mac_address' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->deleteVirtualMac(
                $data['service_name'],
                $data['mac_address']
            );
            
            $this->log("Virtual MAC {$data['mac_address']} deleted from {$data['service_name']}");
            
            return $this->success($result, 'Virtual MAC erfolgreich gelöscht');
            
        } catch (Exception $e) {
            $this->log('Error deleting virtual MAC: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function assignIPToVirtualMac($data) {
        $errors = $this->validate($data, [
            'service_name' => 'required',
            'mac_address' => 'required',
            'ip_address' => 'required',
            'virtual_machine_name' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            
            $result = $serviceManager->addIPToVirtualMac(
                $data['service_name'],
                $data['mac_address'],
                $data['ip_address'],
                $data['virtual_machine_name']
            );
            
            $this->log("IP {$data['ip_address']} assigned to MAC {$data['mac_address']}");
            
            return $this->success($result, 'IP-Adresse erfolgreich zugewiesen');
            
        } catch (Exception $e) {
            $this->log('Error assigning IP to virtual MAC: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function removeIPFromVirtualMac($data) {
        $errors = $this->validate($data, [
            'service_name' => 'required',
            'mac_address' => 'required',
            'ip_address' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->removeIPFromVirtualMac(
                $data['service_name'],
                $data['mac_address'],
                $data['ip_address']
            );
            
            $this->log("IP {$data['ip_address']} removed from MAC {$data['mac_address']}");
            
            return $this->success($result, 'IP-Adresse erfolgreich entfernt');
            
        } catch (Exception $e) {
            $this->log('Error removing IP from virtual MAC: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function createReverseDNS($data) {
        $errors = $this->validate($data, [
            'ip_address' => 'required',
            'reverse' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            
            $result = $serviceManager->createIPReverse($data['ip_address'], $data['reverse']);
            
            $this->log("Reverse DNS created: {$data['ip_address']} -> {$data['reverse']}");
            
            return $this->success($result, 'Reverse DNS erfolgreich erstellt');
            
        } catch (Exception $e) {
            $this->log('Error creating reverse DNS: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function queryReverseDNS($data) {
        $errors = $this->validate($data, [
            'ip_address' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->getIPReverse($data['ip_address']);
            
            return $this->success($result);
            
        } catch (Exception $e) {
            $this->log('Error querying reverse DNS: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function deleteReverseDNS($data) {
        $errors = $this->validate($data, [
            'ip_address' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->deleteIPReverse($data['ip_address'], null);
            
            $this->log("Reverse DNS deleted for IP {$data['ip_address']}");
            
            return $this->success($result, 'Reverse DNS erfolgreich gelöscht');
            
        } catch (Exception $e) {
            $this->log('Error deleting reverse DNS: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function getDedicatedServers() {
        try {
            $serviceManager = new ServiceManager();
            $servers = $serviceManager->OvhAPI('get', '/dedicated/server');
            
            return $this->success($servers);
            
        } catch (Exception $e) {
            $this->log('Error getting dedicated servers: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function getDedicatedServersList() {
        try {
            $serviceManager = new ServiceManager();
            $servers = $serviceManager->OvhAPI('get', '/dedicated/server');
            
            // Nur Namen für Dropdowns
            $server_names = [];
            foreach ($servers as $server) {
                $server_names[] = $server['name'] ?? $server;
            }
            
            return $server_names;
            
        } catch (Exception $e) {
            $this->log('Error getting dedicated servers list: ' . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    private function getVirtualMacsForService($data) {
        $errors = $this->validate($data, [
            'service_name' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $macs = $serviceManager->OvhAPI('get', '/dedicated/server/' . $data['service_name'] . '/virtualMac');
            
            return $this->success($macs);
            
        } catch (Exception $e) {
            $this->log('Error getting virtual MACs for service: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function loadVirtualMacOverview() {
        try {
            $all_macs = $this->getAllVirtualMacs();
            
            if ($all_macs['success']) {
                $stats = [
                    'total_macs' => count($all_macs['data']),
                    'total_ips' => 0,
                    'servers' => []
                ];
                
                foreach ($all_macs['data'] as $mac) {
                    if (isset($mac['ipAddresses'])) {
                        $stats['total_ips'] += count($mac['ipAddresses']);
                    }
                    
                    if (!in_array($mac['service_name'], $stats['servers'])) {
                        $stats['servers'][] = $mac['service_name'];
                    }
                }
                
                $stats['total_servers'] = count($stats['servers']);
                
                return $this->success([
                    'macs' => $all_macs['data'],
                    'stats' => $stats
                ]);
            }
            
            return $all_macs;
            
        } catch (Exception $e) {
            $this->log('Error loading virtual MAC overview: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    public function getStats() {
        try {
            $result = $this->getAllVirtualMacs();
            
            if ($result['success']) {
                $total_ips = 0;
                foreach ($result['data'] as $mac) {
                    if (isset($mac['ipAddresses'])) {
                        $total_ips += count($mac['ipAddresses']);
                    }
                }
                
                return [
                    'total' => count($result['data']),
                    'assigned_ips' => $total_ips
                ];
            }
            
            return ['total' => 0, 'assigned_ips' => 0];
            
        } catch (Exception $e) {
            return ['total' => 0, 'assigned_ips' => 0];
        }
    }
}
?>