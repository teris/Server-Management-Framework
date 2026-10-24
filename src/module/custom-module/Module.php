<?php
if (PHP_SAPI !== 'cli' && (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__ || (get_included_files()[0] ?? null) === __FILE__)) { http_response_code(404); exit; }

require_once dirname(dirname(__FILE__)) . '/ModuleBase.php';

class CustomModuleModule extends ModuleBase {
    public function getContent() {
        ResourceAccess::requireLegacyModule($this->module_key);
        return $this->render('main');
    }
    
    public function handleAjaxRequest($action, $data) {
        ResourceAccess::requireLegacyModule($this->module_key);
        switch ($action) {
            case 'test':
                return $this->success(['message' => $this->t('test_successful')], $this->t('test_successful'));
            default:
                return $this->error($this->t('unknown_action'));
        }
    }
}