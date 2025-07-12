<?php
/**
 * Proxmox Module
 * Verwaltung von virtuellen Maschinen
 */

require_once dirname(dirname(__FILE__)) . '/ModuleBase.php';

class ProxmoxModule extends ModuleBase {
    
    public function getContent() {
        return $this->render('main');
    }
    
    public function handleAjaxRequest($action, $data) {
        switch ($action) {
            case 'create_vm':
                return $this->createVM($data);
                
            case 'get_proxmox_nodes':
                return $this->getProxmoxNodes();
                
            case 'get_proxmox_storages':
                return $this->getProxmoxStorages($data);
                
            case 'get_vm_config':
                return $this->getVMConfig($data);
                
            case 'get_vm_status':
                return $this->getVMStatus($data);
                
            case 'clone_vm':
                return $this->cloneVM($data);
                
            default:
                return $this->error('Unknown action: ' . $action);
        }
    }
    
    private function createVM($data) {
        // Validierung
        $errors = $this->validate($data, [
            'name' => 'required|min:3|max:50',
            'vmid' => 'required|numeric|min:100|max:999999',
            'memory' => 'required|numeric|min:512',
            'cores' => 'required|numeric|min:1|max:32',
            'disk' => 'required|numeric|min:1',
            'node' => 'required',
            'storage' => 'required',
            'bridge' => 'required',
            'iso' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $api = new ProxmoxAPI();
            
            $vm_config = [
                'vmid' => $data['vmid'],
                'name' => $data['name'],
                'memory' => $data['memory'],
                'cores' => $data['cores'],
                'sockets' => 1,
                'cpu' => 'host',
                'net0' => "virtio,bridge={$data['bridge']}",
                'ide2' => "{$data['iso']},media=cdrom",
                'scsi0' => "{$data['storage']}:{$data['disk']},format=qcow2",
                'scsihw' => 'virtio-scsi-pci',
                'ostype' => 'l26',
                'boot' => 'order=scsi0;ide2;net0'
            ];
            
            // MAC-Adresse hinzufügen wenn angegeben
            if (!empty($data['mac'])) {
                $vm_config['net0'] .= ",macaddr={$data['mac']}";
            }
            
            $result = $api->createVM($data['node'], $vm_config);
            
            $this->log("VM {$data['vmid']} ({$data['name']}) created successfully");
            
            return $this->success($result, 'VM erfolgreich erstellt');
            
        } catch (Exception $e) {
            $this->log('Error creating VM: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function getProxmoxNodes() {
        try {
            $api = new ProxmoxAPI();
            $nodes = $api->getNodes();
            
            return $this->success($nodes);
            
        } catch (Exception $e) {
            $this->log('Error getting nodes: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function getProxmoxStorages($data) {
        $errors = $this->validate($data, [
            'node' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $api = new ProxmoxAPI();
            $storages = $api->getStorages($data['node']);
            
            return $this->success($storages);
            
        } catch (Exception $e) {
            $this->log('Error getting storages: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function getVMConfig($data) {
        $errors = $this->validate($data, [
            'node' => 'required',
            'vmid' => 'required|numeric'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $api = new ProxmoxAPI();
            $config = $api->getVMConfig($data['node'], $data['vmid']);
            
            return $this->success($config);
            
        } catch (Exception $e) {
            $this->log('Error getting VM config: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function getVMStatus($data) {
        $errors = $this->validate($data, [
            'node' => 'required',
            'vmid' => 'required|numeric'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $api = new ProxmoxAPI();
            $status = $api->getVMStatus($data['node'], $data['vmid']);
            
            return $this->success($status);
            
        } catch (Exception $e) {
            $this->log('Error getting VM status: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function cloneVM($data) {
        $errors = $this->validate($data, [
            'node' => 'required',
            'vmid' => 'required|numeric',
            'newid' => 'required|numeric',
            'name' => 'required|min:3|max:50'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $api = new ProxmoxAPI();
            
            $clone_config = [
                'newid' => $data['newid'],
                'name' => $data['name'],
                'full' => true,
                'target' => $data['node']
            ];
            
            $result = $api->cloneVM($data['node'], $data['vmid'], $clone_config);
            
            $this->log("VM {$data['vmid']} cloned to {$data['newid']} ({$data['name']})");
            
            return $this->success($result, 'VM erfolgreich geklont');
            
        } catch (Exception $e) {
            $this->log('Error cloning VM: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    public function getStats() {
        try {
            $api = new ProxmoxAPI();
            $vms = $api->getAllVMs();
            
            $running = 0;
            $stopped = 0;
            $total_memory = 0;
            $total_cores = 0;
            
            foreach ($vms as $vm) {
                if ($vm['status'] === 'running') {
                    $running++;
                } else {
                    $stopped++;
                }
                
                $total_memory += $vm['memory'] ?? 0;
                $total_cores += $vm['cores'] ?? $vm['cpus'] ?? 0;
            }
            
            return [
                'total' => count($vms),
                'running' => $running,
                'stopped' => $stopped,
                'memory_gb' => round($total_memory / 1024 / 1024 / 1024, 2),
                'cores' => $total_cores
            ];
            
        } catch (Exception $e) {
            return [];
        }
    }
}
?>