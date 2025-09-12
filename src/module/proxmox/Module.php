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
        
        $template_path = __DIR__ . '/templates/main.php';
        
        if (file_exists($template_path)) {
            $template_data = ['translations' => $translations];
            extract($template_data);
            include $template_path;
        } else {
            echo "Template not found: main.php";
        }
    }
    
    public function handleAjaxRequest($action, $data) {
        switch ($action) {
            case 'create_vm':
                return $this->createVM($data);
            case 'get_proxmox_nodes':
                return $this->getProxmoxNodes();
            case 'get_vms':
                return $this->getVMs();
            case 'get_vm_details':
                return $this->getVMDetails($data);
            case 'start_vm':
                return $this->startVM($data);
            case 'stop_vm':
                return $this->stopVM($data);
            case 'restart_vm':
                return $this->restartVM($data);
            case 'delete_vm':
                return $this->deleteVM($data);
            case 'get_node_status':
                return $this->getNodeStatus($data);
            case 'get_node_tasks':
                return $this->getNodeTasks($data);
            case 'get_node_networks':
                return $this->getNodeNetworks($data);
            case 'get_iso_files':
                return $this->getIsoFiles($data);
            case 'get_storage_list':
                return $this->getStorageList($data);
            case 'get_storage_content':
                return $this->getStorageContent($data);
            case 'get_proxmox_storages':
                return $this->getProxmoxStorages($data);
            case 'get_vm_status':
                return $this->getVMStatus($data);
            case 'get_vm_config':
                return $this->getVMConfig($data);
            case 'update_vm':
                return $this->updateVM($data);
            case 'create_lxc':
                return $this->createLXC($data);
            case 'test':
                return ['success' => true, 'message' => 'Proxmox module is working', 'timestamp' => time()];
            default:
                return ['success' => false, 'error' => 'Unknown action: ' . $action];
        }
    }
    
    private function createVM($data) {
        try {
            // Debug: Zeige alle übergebenen Daten
            $debugInfo = [
                'raw_data' => $data,
                'required_fields_check' => [],
                'vm_params' => [],
                'api_call_info' => []
            ];
            
            // Validierung der erforderlichen Felder
            $requiredFields = ['vmid', 'node', 'name', 'memory', 'cores', 'sockets'];
            foreach ($requiredFields as $field) {
                $debugInfo['required_fields_check'][$field] = [
                    'exists' => isset($data[$field]),
                    'not_empty' => !empty($data[$field]),
                    'value' => $data[$field] ?? 'NOT_SET'
                ];
                
                if (!isset($data[$field]) || empty($data[$field])) {
                    $debugInfo['error'] = "Field $field is required";
                    return [
                        'success' => false, 
                        'error' => "Field $field is required",
                        'debug_info' => $debugInfo
                    ];
                }
            }
            
            // VM-Parameter zusammenstellen
            $vmParams = [
                'vmid' => intval($data['vmid']),
                'name' => $data['name'],
                'memory' => intval($data['memory']),
                'cores' => intval($data['cores']),
                'sockets' => intval($data['sockets']),
                'ostype' => $data['ostype'] ?? 'l26',
                'bios' => $data['bios'] ?? 'seabios',
                'machine' => $data['machine'] ?? 'pc',
                'cpu' => $data['cpu'] ?? 'host',
                // 'arch' entfernt - nur root kann diesen Parameter setzen
                'scsihw' => $data['scsihw'] ?? 'lsi',
                'vga' => $data['vga'] ?? 'std',
                'keyboard' => $data['keyboard'] ?? 'de',
                'citype' => $data['citype'] ?? 'nocloud',
                'startdate' => $data['startdate'] ?? 'now'
            ];
            
            // Alle anderen Parameter hinzufügen (auch leere)
            $allFields = [
                'pool', 'description', 'shares', 'cpulimit', 'cpuunits', 'storage',
                'onboot', 'agent', 'kvm', 'acpi', 'localtime', 'tablet', 'autostart', 
                'protection', 'template', 'start', 'reboot', 'unique', 'ciupgrade',
                'net0', 'scsi0', 'ide2', 'bootdisk', 'boot', 'bootorder', 'bridge',
                'mac', 'cdrom', 'tags', 'hookscript', 'ciuser', 'cipassword',
                'sshkeys', 'nameserver', 'searchdomain', 'cicustom', 'ipconfig0',
                'ipconfig1', 'serial0', 'parallel0', 'usb0', 'usb1', 'watchdog',
                'rng0', 'migrate_downtime', 'migrate_speed', 'startup', 'args',
                'affinity', 'smbios1', 'vmgenid'
            ];
            
            foreach ($allFields as $field) {
                if (isset($data[$field])) {
                    $vmParams[$field] = $data[$field];
                }
            }
            
            $debugInfo['vm_params'] = $vmParams;
            $debugInfo['api_call_info'] = [
                'method' => 'POST',
                'url' => '/nodes/' . $data['node'] . '/qemu',
                'node' => $data['node']
            ];
            
            // VM erstellen
            $serviceManager = new ServiceManager();
            $result = $serviceManager->ProxmoxAPI('post', '/nodes/' . $data['node'] . '/qemu', $vmParams);
            
            if ($result && isset($result['data'])) {
                return ['success' => true, 'data' => $result['data'], 'message' => 'VM erfolgreich erstellt'];
            } else {
                return ['success' => false, 'error' => 'Fehler beim Erstellen der VM'];
            }
            
        } catch (Exception $e) {
            error_log('Error creating VM: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Erstellen der VM: ' . $e->getMessage()];
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
            
            return ['success' => true, 'data' => $nodes];
            
        } catch (Exception $e) {
            error_log('Error getting nodes: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Laden der Nodes: ' . $e->getMessage()];
        }
    }
    
    private function getNodeStatus($data) {
        if (!isset($data['node'])) {
            return ['success' => false, 'error' => 'Node parameter is required'];
        }
        
        try {
            $serviceManager = new ServiceManager();
            $status = $serviceManager->ProxmoxAPI('get', '/nodes/' . $data['node'] . '/status');
            return ['success' => true, 'data' => $status];
        } catch (Exception $e) {
            error_log('Error getting node status: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Laden des Node-Status: ' . $e->getMessage()];
        }
    }
    
    private function getNodeTasks($data) {
        if (!isset($data['node'])) {
            return ['success' => false, 'error' => 'Node parameter is required'];
        }
        
        try {
            $serviceManager = new ServiceManager();
            $tasks = $serviceManager->ProxmoxAPI('get', '/nodes/' . $data['node'] . '/tasks');
            return ['success' => true, 'data' => $tasks];
        } catch (Exception $e) {
            error_log('Error getting node tasks: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Laden der Tasks: ' . $e->getMessage()];
        }
    }
    
    private function getNodeNetworks($data) {
        if (!isset($data['node'])) {
            return ['success' => false, 'error' => 'Node parameter is required'];
        }
        
        try {
            $serviceManager = new ServiceManager();
            $networks = $serviceManager->ProxmoxAPI('get', '/nodes/' . $data['node'] . '/network');
            
            // Filtere nur aktive Bridge-Netzwerke
            if (isset($networks['data']) && is_array($networks['data'])) {
                $bridges = array_filter($networks['data'], function($network) {
                    return isset($network['active']) && $network['active'] == 1 && 
                           isset($network['type']) && $network['type'] == 'bridge';
                });
                $networks['data'] = array_values($bridges);
            }
            
            return ['success' => true, 'data' => $networks];
        } catch (Exception $e) {
            error_log('Error getting node networks: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Laden der Netzwerke: ' . $e->getMessage()];
        }
    }
    
    private function getIsoFiles($data) {
        if (!isset($data['node'])) {
            return ['success' => false, 'error' => 'Node parameter is required'];
        }
        
        try {
            $serviceManager = new ServiceManager();
            $isoFiles = [];
            
            // Wenn ein spezifischer Storage angegeben ist, lade nur von diesem
            if (isset($data['storage'])) {
                $volumes = $serviceManager->ProxmoxAPI('get', '/nodes/' . $data['node'] . '/storage/' . $data['storage'] . '/content');
                
                // Prüfe auf API-Fehler
                if (isset($volumes['success']) && $volumes['success'] === false) {
                    error_log('ISO files API error: ' . json_encode($volumes));
                    return ['success' => false, 'error' => 'API-Fehler beim Laden der ISO-Dateien: ' . ($volumes['error'] ?? 'Unbekannter Fehler')];
                }
                
                if (isset($volumes['data']) && is_array($volumes['data'])) {
                    foreach ($volumes['data'] as $volume) {
                        if (isset($volume['volid']) && strpos($volume['volid'], '.iso') !== false) {
                            $isoFiles[] = [
                                'volid' => $volume['volid'],
                                'size' => $volume['size'] ?? 0,
                                'storage' => $data['storage']
                            ];
                        }
                    }
                }
            } else {
                // Lade von allen Storages
                $storages = $serviceManager->ProxmoxAPI('get', '/nodes/' . $data['node'] . '/storage');
                
                // Prüfe auf API-Fehler
                if (isset($storages['success']) && $storages['success'] === false) {
                    error_log('Storages API error: ' . json_encode($storages));
                    return ['success' => false, 'error' => 'API-Fehler beim Laden der Storages: ' . ($storages['error'] ?? 'Unbekannter Fehler')];
                }
                
                if (isset($storages['data']) && is_array($storages['data'])) {
                    foreach ($storages['data'] as $storage) {
                        if (isset($storage['content']) && in_array('iso', $storage['content'])) {
                            $volumes = $serviceManager->ProxmoxAPI('get', '/nodes/' . $data['node'] . '/storage/' . $storage['storage'] . '/content');
                            
                            // Prüfe auf API-Fehler für jeden Storage
                            if (isset($volumes['success']) && $volumes['success'] === false) {
                                error_log('Storage content API error for ' . $storage['storage'] . ': ' . json_encode($volumes));
                                continue; // Überspringe diesen Storage
                            }
                            
                            if (isset($volumes['data']) && is_array($volumes['data'])) {
                                foreach ($volumes['data'] as $volume) {
                                    if (isset($volume['volid']) && strpos($volume['volid'], '.iso') !== false) {
                                        $isoFiles[] = [
                                            'volid' => $volume['volid'],
                                            'size' => $volume['size'] ?? 0,
                                            'storage' => $storage['storage']
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }
            
            return ['success' => true, 'data' => $isoFiles];
        } catch (Exception $e) {
            error_log('Error getting ISO files: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Laden der ISO-Dateien: ' . $e->getMessage()];
        }
    }
    
    private function getVMs($data = []) {
        try {
            $serviceManager = new ServiceManager();
            
            // Wenn ein Node spezifiziert ist, lade VMs von diesem Node
            if (isset($data['node'])) {
                $node = $data['node'];
                
                // Lade sowohl QEMU VMs als auch LXC Container
                $qemuVMs = $serviceManager->ProxmoxAPI('get', '/nodes/' . $node . '/qemu');
                $lxcContainers = $serviceManager->ProxmoxAPI('get', '/nodes/' . $node . '/lxc');
                
                $allVMs = [];
                
                // Verarbeite QEMU VMs
                if (isset($qemuVMs['data']) && is_array($qemuVMs['data'])) {
                    foreach ($qemuVMs['data'] as $vm) {
                        $vm['type'] = 'qemu';
                        $vm['node'] = $node;
                        $allVMs[] = $vm;
                    }
                }
                
                // Verarbeite LXC Container
                if (isset($lxcContainers['data']) && is_array($lxcContainers['data'])) {
                    foreach ($lxcContainers['data'] as $container) {
                        $container['type'] = 'lxc';
                        $container['node'] = $node;
                        $allVMs[] = $container;
                    }
                }
                
                return ['success' => true, 'data' => $allVMs];
            } else {
                // Lade alle VMs vom Cluster
                $vms = $serviceManager->ProxmoxAPI('get', '/cluster/resources?type=vm');
                return ['success' => true, 'data' => $vms];
            }
        } catch (Exception $e) {
            error_log('Error getting VMs: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Laden der VMs: ' . $e->getMessage()];
        }
    }
    
    private function getVMDetails($data) {
        if (!isset($data['node']) || !isset($data['vmid'])) {
            return ['success' => false, 'error' => 'Node and VMID parameters are required'];
        }
        
        try {
            $serviceManager = new ServiceManager();
            $vmDetails = $serviceManager->ProxmoxAPI('get', '/nodes/' . $data['node'] . '/qemu/' . $data['vmid'] . '/config');
            return ['success' => true, 'data' => $vmDetails];
        } catch (Exception $e) {
            error_log('Error getting VM details: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Laden der VM-Details: ' . $e->getMessage()];
        }
    }
    
    private function startVM($data) {
        if (!isset($data['node']) || !isset($data['vmid'])) {
            return ['success' => false, 'error' => 'Node and VMID parameters are required'];
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->ProxmoxAPI('post', '/nodes/' . $data['node'] . '/qemu/' . $data['vmid'] . '/status/start');
            return ['success' => true, 'data' => $result];
        } catch (Exception $e) {
            error_log('Error starting VM: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Starten der VM: ' . $e->getMessage()];
        }
    }
    
    private function stopVM($data) {
        if (!isset($data['node']) || !isset($data['vmid'])) {
            return ['success' => false, 'error' => 'Node and VMID parameters are required'];
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->ProxmoxAPI('post', '/nodes/' . $data['node'] . '/qemu/' . $data['vmid'] . '/status/stop');
            return ['success' => true, 'data' => $result];
        } catch (Exception $e) {
            error_log('Error stopping VM: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Stoppen der VM: ' . $e->getMessage()];
        }
    }
    
    private function restartVM($data) {
        if (!isset($data['node']) || !isset($data['vmid'])) {
            return ['success' => false, 'error' => 'Node and VMID parameters are required'];
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->ProxmoxAPI('post', '/nodes/' . $data['node'] . '/qemu/' . $data['vmid'] . '/status/reboot');
            return ['success' => true, 'data' => $result];
        } catch (Exception $e) {
            error_log('Error restarting VM: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Neustarten der VM: ' . $e->getMessage()];
        }
    }
    
    private function deleteVM($data) {
        if (!isset($data['node']) || !isset($data['vmid'])) {
            return ['success' => false, 'error' => 'Node and VMID parameters are required'];
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->ProxmoxAPI('delete', '/nodes/' . $data['node'] . '/qemu/' . $data['vmid']);
            return ['success' => true, 'data' => $result];
        } catch (Exception $e) {
            error_log('Error deleting VM: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Löschen der VM: ' . $e->getMessage()];
        }
    }
    
    private function getStorageList($data) {
        if (!isset($data['node'])) {
            return ['success' => false, 'error' => 'Node parameter is required'];
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->ProxmoxAPI('get', '/nodes/' . $data['node'] . '/storage');
            return ['success' => true, 'data' => $result];
        } catch (Exception $e) {
            error_log('Error getting storage list: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Laden der Storage-Liste: ' . $e->getMessage()];
        }
    }
    
    private function getStorageContent($data) {
        if (!isset($data['node']) || !isset($data['storage'])) {
            return ['success' => false, 'error' => 'Node and storage parameters are required'];
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->ProxmoxAPI('get', '/nodes/' . $data['node'] . '/storage/' . $data['storage'] . '/content');
            return ['success' => true, 'data' => $result];
        } catch (Exception $e) {
            error_log('Error getting storage content: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Laden des Storage-Inhalts: ' . $e->getMessage()];
        }
    }
    
    private function getProxmoxStorages($data) {
        if (!isset($data['node'])) {
            return ['success' => false, 'error' => 'Node parameter is required'];
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->ProxmoxAPI('get', '/nodes/' . $data['node'] . '/storage');
            return ['success' => true, 'data' => $result];
        } catch (Exception $e) {
            error_log('Error getting proxmox storages: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Laden der Proxmox-Storages: ' . $e->getMessage()];
        }
    }
    
    private function getVMStatus($data) {
        if (!isset($data['node']) || !isset($data['vmid'])) {
            return ['success' => false, 'error' => 'Node and VMID parameters are required'];
        }
        
        try {
            $serviceManager = new ServiceManager();
            $type = isset($data['type']) ? $data['type'] : 'qemu';
            $result = $serviceManager->ProxmoxAPI('get', '/nodes/' . $data['node'] . '/' . $type . '/' . $data['vmid'] . '/status/current');
            return ['success' => true, 'data' => $result];
        } catch (Exception $e) {
            error_log('Error getting VM status: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Laden des VM-Status: ' . $e->getMessage()];
        }
    }
    
    private function getVMConfig($data) {
        if (!isset($data['node']) || !isset($data['vmid'])) {
            return ['success' => false, 'error' => 'Node and VMID parameters are required'];
        }
        
        try {
            $serviceManager = new ServiceManager();
            $type = isset($data['type']) ? $data['type'] : 'qemu';
            $result = $serviceManager->ProxmoxAPI('get', '/nodes/' . $data['node'] . '/' . $type . '/' . $data['vmid'] . '/config');
            return ['success' => true, 'data' => $result];
        } catch (Exception $e) {
            error_log('Error getting VM config: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Laden der VM-Konfiguration: ' . $e->getMessage()];
        }
    }
    
    private function updateVM($data) {
        if (!isset($data['node']) || !isset($data['vmid'])) {
            return ['success' => false, 'error' => 'Node and VMID parameters are required'];
        }
        
        try {
            $serviceManager = new ServiceManager();
            $type = isset($data['type']) ? $data['type'] : 'qemu';
            
            // Entferne node, vmid und type aus den Daten
            $configData = $data;
            unset($configData['node'], $configData['vmid'], $configData['type']);
            
            $result = $serviceManager->ProxmoxAPI('put', '/nodes/' . $data['node'] . '/' . $type . '/' . $data['vmid'] . '/config', $configData);
            return ['success' => true, 'data' => $result];
        } catch (Exception $e) {
            error_log('Error updating VM: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Aktualisieren der VM: ' . $e->getMessage()];
        }
    }
    
    private function createLXC($data) {
        if (!isset($data['node']) || !isset($data['vmid'])) {
            return ['success' => false, 'error' => 'Node and VMID parameters are required'];
        }
        
        try {
            $serviceManager = new ServiceManager();
            
            // Entferne node und vmid aus den Daten
            $configData = $data;
            unset($configData['node'], $configData['vmid']);
            
            $result = $serviceManager->ProxmoxAPI('post', '/nodes/' . $data['node'] . '/lxc', $configData);
            return ['success' => true, 'data' => $result];
        } catch (Exception $e) {
            error_log('Error creating LXC: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Fehler beim Erstellen des LXC-Containers: ' . $e->getMessage()];
        }
    }
}
?>