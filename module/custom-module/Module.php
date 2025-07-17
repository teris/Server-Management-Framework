<?php
require_once dirname(dirname(__FILE__)) . '/ModuleBase.php';

class CustomModuleModule extends ModuleBase {
    public function getContent() {
        $translations = $this->tMultiple([
            'module_title', 'custom_module_description', 'welcome_message', 
            'test_button', 'save', 'cancel', 'edit', 'delete', 'create', 'refresh'
        ]);
        
        return $this->render('main', [
            'translations' => $translations
        ]);
    }
    
    public function handleAjaxRequest($action, $data) {
        switch ($action) {
            case 'test':
                return $this->success(['message' => $this->t('test_successful')], $this->t('test_successful'));
            case 'get_translations':
                return $this->getTranslations();
            default:
                return $this->error($this->t('unknown_action'));
        }
    }
    
    private function getTranslations() {
        $translations = $this->tMultiple([
            'test_successful', 'custom_module_description', 'welcome_message'
        ]);
        
        return [
            'success' => true,
            'message' => 'Operation successful',
            'translations' => $translations
        ];
    }
}