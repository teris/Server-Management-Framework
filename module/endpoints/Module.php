<?php
/**
 * Endpoints Module
 * API Endpoints Tester für Entwickler und Admins
 */

require_once dirname(dirname(__FILE__)) . '/ModuleBase.php';

class EndpointsModule extends ModuleBase {
    
    public function getContent() {
        // Dieses Modul hat keinen eigenen AJAX-Handler,
        // da es nur zum Testen anderer Module dient
        return $this->render('main');
    }
    
    public function handleAjaxRequest($action, $data) {
        // Endpoints Module leitet Requests an andere Module weiter
        // Dies wird über das Frontend gehandhabt
        return $this->error('This module does not handle direct requests');
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
}
?>