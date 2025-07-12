<?php
require_once dirname(dirname(__FILE__)) . '/ModuleBase.php';

class CustomModuleModule extends ModuleBase {
    public function getContent() {
        return $this->render('main');
    }
    
    public function handleAjaxRequest($action, $data) {
        switch ($action) {
            case 'test':
                return $this->success(['message' => 'Test successful']);
            default:
                return $this->error('Unknown action');
        }
    }
}