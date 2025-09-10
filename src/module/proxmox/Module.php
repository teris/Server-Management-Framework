<?php
/**
 * Proxmox Module
 * Verwaltung von virtuellen Maschinen
 */

require_once dirname(dirname(__FILE__)) . '/ModuleBase.php';

class ProxmoxModule extends ModuleBase {
    
    public function getContent() {
        $translations = $this->tMultiple([
            'module_title', 'server_list', 'create_vm', 'vm_management', 'server_details',
            'vm_id', 'node', 'status', 'memory_mb', 'cpu_cores_count', 'uptime',
            'start_vm', 'stop_vm', 'restart_vm', 'edit_server', 'delete_vm',
            'vm_created_successfully', 'vm_updated_successfully', 'vm_deleted_successfully',
            'error_creating_vm', 'error_updating_vm', 'error_deleting_vm'
        ]);
        
        return $this->render('main', [
            'translations' => $translations
        ]);
    }
    
    public function handleAjaxRequest($action, $data) {
        switch ($action) {
            case 'create_vm':
                return $this->createVM($data);
                
            case 'get_proxmox_nodes':
                return $this->getProxmoxNodes();
                
            case 'get_node_status':
                return $this->getNodeStatus($data);
                
            case 'get_proxmox_storages':
                return $this->getProxmoxStorages($data);
                
            case 'get_storage_list':
                return $this->getStorageList($data);
                
            case 'get_storage_status':
                return $this->getStorageStatus($data);
                
            case 'get_storage_content':
                return $this->getStorageContent($data);
                
            case 'get_vm_config':
                return $this->getVMConfig($data);
                
            case 'get_vm_status':
                return $this->getVMStatus($data);
                
            case 'clone_vm':
                return $this->cloneVM($data);
                
            case 'get_vms':
                return $this->getVMs($data);
                
            case 'start_vm':
                return $this->startVM($data);
                
            case 'stop_vm':
                return $this->stopVM($data);
                
            case 'restart_vm':
                return $this->restartVM($data);
                
            case 'delete_vm':
                return $this->deleteVM($data);
                
            case 'reset_vm':
                return $this->resetVM($data);
                
            case 'resume_vm':
                return $this->resumeVM($data);
                
            case 'update_vm':
                return $this->updateVM($data);
                
            default:
                return $this->error($this->t('unknown_action') . ': ' . $action);
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
            $serviceManager = new ServiceManager();
            
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
            
            $result = $serviceManager->createProxmoxVM($vm_config);
            
            $this->log("VM {$data['vmid']} ({$data['name']}) created successfully");
            
            return $this->success($result, $this->t('vm_created_successfully'));
            
        } catch (Exception $e) {
            $this->log('Error creating VM: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_creating_vm') . ': ' . $e->getMessage());
        }
    }
    
    private function getProxmoxNodes() {
        try {
            $serviceManager = new ServiceManager();
            $nodes = $serviceManager->ProxmoxAPI('get', '/nodes');
            
            // Lade VMs und Container, um die Anzahl pro Node zu berechnen
            $vms = $this->getVMs();
            $vmCounts = [];
            
            if ($vms['success'] && isset($vms['data'])) {
                foreach ($vms['data'] as $vm) {
                    $node = $vm['node'];
                    if (!isset($vmCounts[$node])) {
                        $vmCounts[$node] = 0;
                    }
                    $vmCounts[$node]++;
                }
            }
            
            // Füge VM/Container-Anzahl zu den Node-Daten hinzu
            if (isset($nodes['data']) && is_array($nodes['data'])) {
                foreach ($nodes['data'] as &$node) {
                    $node['vms'] = $vmCounts[$node['node']] ?? 0;
                }
            }
            
            return $this->success($nodes);
            
        } catch (Exception $e) {
            $this->log('Error getting nodes: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_getting_nodes') . ': ' . $e->getMessage());
        }
    }
    
    private function getNodeStatus($data) {
        $errors = $this->validate($data, [
            'node' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $status = $serviceManager->ProxmoxAPI('get', "/nodes/{$data['node']}/status");
            
            return $this->success($status);
            
        } catch (Exception $e) {
            $this->log('Error getting node status: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_getting_node_status') . ': ' . $e->getMessage());
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
            $serviceManager = new ServiceManager();
            $storages = $serviceManager->ProxmoxAPI('get', "/nodes/{$data['node']}/storage");
            
            return $this->success($storages);
            
        } catch (Exception $e) {
            $this->log('Error getting storages: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function getVMConfig($data) {
        $errors = $this->validate($data, [
            'node' => 'required',
            'vmid' => 'required|numeric',
            'type' => 'required|in:qemu,lxc'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $endpoint = $data['type'] === 'lxc' 
                ? "/nodes/{$data['node']}/lxc/{$data['vmid']}/config"
                : "/nodes/{$data['node']}/qemu/{$data['vmid']}/config";
            
            $config = $serviceManager->ProxmoxAPI('get', $endpoint);
            
            return $this->success($config);
            
        } catch (Exception $e) {
            $this->log('Error getting VM config: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    private function getVMStatus($data) {
        $errors = $this->validate($data, [
            'node' => 'required',
            'vmid' => 'required|numeric',
            'type' => 'required|in:qemu,lxc'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $endpoint = $data['type'] === 'lxc' 
                ? "/nodes/{$data['node']}/lxc/{$data['vmid']}/status/current"
                : "/nodes/{$data['node']}/qemu/{$data['vmid']}/status/current";
            
            $status = $serviceManager->ProxmoxAPI('get', $endpoint);
            
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
            $serviceManager = new ServiceManager();
            
            $clone_config = [
                'newid' => $data['newid'],
                'name' => $data['name'],
                'full' => true,
                'target' => $data['node']
            ];
            
            $result = $serviceManager->ProxmoxAPI('post', "/nodes/{$data['node']}/qemu/{$data['vmid']}/clone", $clone_config);
            
            $this->log("VM {$data['vmid']} cloned to {$data['newid']} ({$data['name']})");
            
            return $this->success($result, 'VM erfolgreich geklont');
            
        } catch (Exception $e) {
            $this->log('Error cloning VM: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    public function getStats() {
        try {
            $serviceManager = new ServiceManager();
            $vms = $serviceManager->getProxmoxVMs();
            
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
    
    private function getVMs($data = []) {
        try {
            $serviceManager = new ServiceManager();
            
            // Versuche zuerst, VMs und Container von der Proxmox-API zu laden
            try {
                $formattedVMs = [];
                
                // Lade alle Ressourcen (VMs und Container) in einem API-Aufruf
                $allResources = $serviceManager->ProxmoxAPI('get', '/cluster/resources');
                if ($allResources && isset($allResources['data'])) {
                    foreach ($allResources['data'] as $resource) {
                        if ($resource['type'] === 'qemu' || $resource['type'] === 'lxc') {
                            // Filter nach Node, wenn angegeben
                            if (!empty($data['node']) && $resource['node'] !== $data['node']) {
                                continue;
                            }
                            
                            $formattedVMs[] = [
                                'vmid' => $resource['vmid'],
                                'name' => $resource['name'] ?? ($resource['type'] === 'lxc' ? 'Container ' : 'VM ') . $resource['vmid'],
                                'node' => $resource['node'],
                                'type' => $resource['type'],
                                'status' => $resource['status'] ?? 'stopped',
                                'memory' => $resource['maxmem'] ?? 0,
                                'cores' => $resource['maxcpu'] ?? 0,
                                'uptime' => $resource['uptime'] ?? 0,
                                'disk' => $resource['maxdisk'] ?? 0
                            ];
                        }
                    }
                }
                
                if (!empty($formattedVMs)) {
                    return $this->success($formattedVMs);
                }
            } catch (Exception $apiError) {
                $this->log('Proxmox API error, falling back to database: ' . $apiError->getMessage(), 'WARNING');
            }
            
            // Fallback: VMs aus der lokalen Datenbank laden
            $vms = $serviceManager->getProxmoxVMs();
            $formattedVMs = [];
            
            foreach ($vms as $vm) {
                $formattedVMs[] = [
                    'vmid' => $vm->vm_id ?? $vm->vmid ?? 0,
                    'name' => $vm->name ?? 'VM ' . ($vm->vm_id ?? $vm->vmid ?? 0),
                    'node' => $vm->node ?? 'unknown',
                    'status' => $vm->status ?? 'stopped',
                    'memory' => $vm->memory ?? 0,
                    'cores' => $vm->cores ?? 0,
                    'uptime' => 0,
                    'disk' => $vm->disk_size ?? 0
                ];
            }
            
            return $this->success($formattedVMs);
            
        } catch (Exception $e) {
            $this->log('Error getting VMs: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_getting_vms') . ': ' . $e->getMessage());
        }
    }
    
    
    private function startVM($data) {
        $errors = $this->validate($data, [
            'node' => 'required',
            'vmid' => 'required|numeric',
            'type' => 'required|in:qemu,lxc'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->controlProxmoxVM($data['node'], $data['vmid'], 'start', $data['type']);
            
            $this->log("VM {$data['vmid']} ({$data['type']}) started successfully");
            
            return $this->success($result, $this->t('vm_started_successfully'));
            
        } catch (Exception $e) {
            $this->log('Error starting VM: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_starting_vm') . ': ' . $e->getMessage());
        }
    }
    
    private function stopVM($data) {
        $errors = $this->validate($data, [
            'node' => 'required',
            'vmid' => 'required|numeric',
            'type' => 'required|in:qemu,lxc'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->controlProxmoxVM($data['node'], $data['vmid'], 'stop', $data['type']);
            
            $this->log("VM {$data['vmid']} ({$data['type']}) stopped successfully");
            
            return $this->success($result, $this->t('vm_stopped_successfully'));
            
        } catch (Exception $e) {
            $this->log('Error stopping VM: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_stopping_vm') . ': ' . $e->getMessage());
        }
    }
    
    private function restartVM($data) {
        $errors = $this->validate($data, [
            'node' => 'required',
            'vmid' => 'required|numeric',
            'type' => 'required|in:qemu,lxc'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->controlProxmoxVM($data['node'], $data['vmid'], 'reboot', $data['type']);
            
            $this->log("VM {$data['vmid']} ({$data['type']}) restarted successfully");
            
            return $this->success($result, $this->t('vm_restarted_successfully'));
            
        } catch (Exception $e) {
            $this->log('Error restarting VM: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_restarting_vm') . ': ' . $e->getMessage());
        }
    }
    
    private function deleteVM($data) {
        $errors = $this->validate($data, [
            'node' => 'required',
            'vmid' => 'required|numeric',
            'type' => 'required|in:qemu,lxc'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->deleteProxmoxVM($data['node'], $data['vmid'], $data['type']);
            
            $this->log("VM {$data['vmid']} ({$data['type']}) deleted successfully");
            
            return $this->success($result, $this->t('vm_deleted_successfully'));
            
        } catch (Exception $e) {
            $this->log('Error deleting VM: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_deleting_vm') . ': ' . $e->getMessage());
        }
    }
    
    private function resetVM($data) {
        $errors = $this->validate($data, [
            'node' => 'required',
            'vmid' => 'required|numeric',
            'type' => 'required|in:qemu,lxc'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->controlProxmoxVM($data['node'], $data['vmid'], 'reset', $data['type']);
            
            $this->log("VM {$data['vmid']} ({$data['type']}) reset successfully");
            
            return $this->success($result, $this->t('vm_reset_successfully'));
            
        } catch (Exception $e) {
            $this->log('Error resetting VM: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_resetting_vm') . ': ' . $e->getMessage());
        }
    }
    
    private function resumeVM($data) {
        $errors = $this->validate($data, [
            'node' => 'required',
            'vmid' => 'required|numeric',
            'type' => 'required|in:qemu,lxc'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->controlProxmoxVM($data['node'], $data['vmid'], 'resume', $data['type']);
            
            $this->log("VM {$data['vmid']} ({$data['type']}) resumed successfully");
            
            return $this->success($result, $this->t('vm_resumed_successfully'));
            
        } catch (Exception $e) {
            $this->log('Error resuming VM: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_resuming_vm') . ': ' . $e->getMessage());
        }
    }
    
    private function updateVM($data) {
        $errors = $this->validate($data, [
            'node' => 'required',
            'vmid' => 'required|numeric',
            'name' => 'required|min:3|max:50'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            
            $update_config = [];
            
            // Nur geänderte Werte aktualisieren
            if (isset($data['name'])) {
                $update_config['name'] = $data['name'];
            }
            if (isset($data['memory'])) {
                $update_config['memory'] = $data['memory'];
            }
            if (isset($data['cores'])) {
                $update_config['cores'] = $data['cores'];
            }
            if (isset($data['description'])) {
                $update_config['description'] = $data['description'];
            }
            
            $result = $serviceManager->ProxmoxAPI('put', "/nodes/{$data['node']}/qemu/{$data['vmid']}/config", $update_config);
            
            $this->log("VM {$data['vmid']} updated successfully");
            
            return $this->success($result, $this->t('vm_updated_successfully'));
            
        } catch (Exception $e) {
            $this->log('Error updating VM: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_updating_vm') . ': ' . $e->getMessage());
        }
    }
    
    private function getStorageList($data) {
        $errors = $this->validate($data, ['node' => 'required']);
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $storages = $serviceManager->ProxmoxAPI('get', "/nodes/{$data['node']}/storage");
            return $this->success($storages);
        } catch (Exception $e) {
            $this->log('Error getting storage list: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_getting_storage_list') . ': ' . $e->getMessage());
        }
    }
    
    private function getStorageStatus($data) {
        $errors = $this->validate($data, [
            'node' => 'required',
            'storage' => 'required'
        ]);
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $status = $serviceManager->ProxmoxAPI('get', "/nodes/{$data['node']}/storage/{$data['storage']}/status");
            return $this->success($status);
        } catch (Exception $e) {
            $this->log('Error getting storage status: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_getting_storage_status') . ': ' . $e->getMessage());
        }
    }
    
    private function getStorageContent($data) {
        $errors = $this->validate($data, [
            'node' => 'required',
            'storage' => 'required'
        ]);
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            $content = $serviceManager->ProxmoxAPI('get', "/nodes/{$data['node']}/storage/{$data['storage']}/content");
            return $this->success($content);
        } catch (Exception $e) {
            $this->log('Error getting storage content: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_getting_storage_content') . ': ' . $e->getMessage());
        }
    }
    
}
?>