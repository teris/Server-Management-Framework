<?php
if (PHP_SAPI !== 'cli' && (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__ || (get_included_files()[0] ?? null) === __FILE__)) { http_response_code(404); exit; }

// ========================================
// Network Module
// modules/network/Module.php
// ========================================

require_once dirname(dirname(__FILE__)) . '/ModuleBase.php';

class NetworkModule extends ModuleBase {
    
    public function getContent() {
        ResourceAccess::requireLegacyModule($this->module_key);
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
        ResourceAccess::requireLegacyModule($this->module_key);
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
        try {
            $result=\SMF\Core\NetworkConfiguration::update($data, function($method,$path,$payload)use($data){
                $kernel=\SMF\Core\Kernel::boot();
                if($method==='GET')return $kernel->providers()->execute('proxmox',$method,$path,$payload);
                $key=$data['operation_key']??'';if(!is_string($key)||!preg_match('/^[a-f0-9]{32}$/D',$key))throw new InvalidArgumentException('Formular neu öffnen.');
                $operations=new \SMF\Core\ProviderOperations($kernel->database(),$kernel->audit());
                return $operations->perform('proxmox',ResourceAccess::connectionId('proxmox'),'network:'.$key.':'.hash('sha256',json_encode([$path,$data['ip'],$data['mac'],$data['gateway']??''])),fn()=>$kernel->providers()->execute('proxmox',$method,$path,$payload));
            });
            $this->log('VM network configuration updated through Cloud-Init');
            return $this->success($result,'Netzwerk- und Cloud-Init-Konfiguration gespeichert; im Gast nach dem nächsten Cloud-Init-Lauf wirksam.');
        } catch (Throwable $error) {
            $this->log('Network configuration not confirmed: '.get_class($error),'ERROR');
            return $this->error($error instanceof InvalidArgumentException?$error->getMessage():'Netzwerkänderung nicht bestätigt. Cloud-Init, Node und Anbieterzustand prüfen.');
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