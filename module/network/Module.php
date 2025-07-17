<?php
// ========================================
// Network Module
// modules/network/Module.php
// ========================================

require_once dirname(dirname(__FILE__)) . '/ModuleBase.php';

class NetworkModule extends ModuleBase {
    
    public function getContent() {
        $translations = $this->tMultiple([
            'module_title', 'vm_network_configuration', 'vm_id', 'mac_address', 'ip_address',
            'update_network', 'helpful_information', 'mac_address_format', 'mac_format_description',
            'examples', 'ovh_virtual_mac', 'vmware_mac', 'kvm_qemu_mac', 'ip_configuration',
            'ip_config_description', 'dhcp_mac_binding', 'cloud_init', 'manual_vm',
            'advanced_network_options', 'advanced_network_description', 'save', 'cancel',
            'edit', 'delete', 'create', 'refresh', 'actions', 'status'
        ]);
        
        return $this->render('main', [
            'translations' => $translations
        ]);
    }
    
    public function handleAjaxRequest($action, $data) {
        switch ($action) {
            case 'update_vm_network':
                return $this->updateVMNetwork($data);
            case 'get_translations':
                return $this->getTranslations();
            default:
                return $this->error($this->t('unknown_action') . ': ' . $action);
        }
    }
    
    private function updateVMNetwork($data) {
        $errors = $this->validate($data, [
            'vmid' => 'required|numeric',
            'mac' => 'required',
            'ip' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error($this->t('validation_failed'), $errors);
        }
        
        try {
            $serviceManager = new ServiceManager();
            
            // VM Config abrufen
            $vm_config = $serviceManager->ProxmoxAPI('get', "/nodes/pve/qemu/{$data['vmid']}/config");
            
            // Netzwerk-Config aktualisieren
            $network_config = [
                'net0' => "virtio={$data['mac']},bridge=vmbr0"
            ];
            
            $result = $serviceManager->ProxmoxAPI('put', "/nodes/pve/qemu/{$data['vmid']}/config", $network_config);
            
            // Optional: IP in VM konfigurieren (über cloud-init oder custom script)
            
            $this->log("VM {$data['vmid']} network updated: MAC={$data['mac']}, IP={$data['ip']}");
            
            return $this->success($result, $this->t('network_updated_successfully'));
            
        } catch (Exception $e) {
            $this->log('Error updating VM network: ' . $e->getMessage(), 'ERROR');
            return $this->error($this->t('error_updating_network') . ': ' . $e->getMessage());
        }
    }
    
    private function getTranslations() {
        $translations = $this->tMultiple([
            'vm_network_updated_message', 'network_error', 'unknown_error'
        ]);
        
        return $this->success($translations);
    }
}
?>