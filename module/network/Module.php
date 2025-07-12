<?php
// ========================================
// Network Module
// modules/network/Module.php
// ========================================

require_once dirname(dirname(__FILE__)) . '/ModuleBase.php';

class NetworkModule extends ModuleBase {
    
    public function getContent() {
        return $this->render('main');
    }
    
    public function handleAjaxRequest($action, $data) {
        switch ($action) {
            case 'update_vm_network':
                return $this->updateVMNetwork($data);
            default:
                return $this->error('Unknown action: ' . $action);
        }
    }
    
    private function updateVMNetwork($data) {
        $errors = $this->validate($data, [
            'vmid' => 'required|numeric',
            'mac' => 'required',
            'ip' => 'required'
        ]);
        
        if (!empty($errors)) {
            return $this->error('Validation failed', $errors);
        }
        
        try {
            $api = new ProxmoxAPI();
            
            // VM Config abrufen
            $vm_config = $api->getVMConfig('pve', $data['vmid']);
            
            // Netzwerk-Config aktualisieren
            $network_config = [
                'net0' => "virtio={$data['mac']},bridge=vmbr0"
            ];
            
            $result = $api->updateVMConfig('pve', $data['vmid'], $network_config);
            
            // Optional: IP in VM konfigurieren (über cloud-init oder custom script)
            
            $this->log("VM {$data['vmid']} network updated: MAC={$data['mac']}, IP={$data['ip']}");
            
            return $this->success($result, 'Netzwerk erfolgreich aktualisiert');
            
        } catch (Exception $e) {
            $this->log('Error updating VM network: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
}
?>