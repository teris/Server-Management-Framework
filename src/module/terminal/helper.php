<?php
/**
 * Terminal Module Helper
 * Verarbeitet AJAX-Requests für das Terminal-Modul
 */

// Framework einbinden (wie in index.php)
require_once dirname(dirname(dirname(__FILE__))) . '/sys.conf.php';

// Framework-Loaded Konstante für Sicherheitschecks
define('FRAMEWORK_LOADED', true);

$frameworkFile = dirname(dirname(dirname(__FILE__))) . '/../framework.php';
if ($modus_type['modus'] === 'mysql') {
    $frameworkFile = dirname(dirname(dirname(__FILE__))) . '/core/DatabaseOnlyFramework.php';
} elseif ($modus_type['modus'] === 'mysql') {
    $frameworkFile = dirname(dirname(dirname(__FILE__))) . '/core/DatabaseOnlyFramework.php';
}
require_once $frameworkFile;
require_once dirname(dirname(dirname(__FILE__))) . '/auth_handler.php';

// Module einbinden
require_once dirname(__FILE__) . '/Module.php';


// Nur AJAX-Requests verarbeiten
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Only POST requests allowed']);
    exit;
}

// Content-Type setzen
header('Content-Type: application/json');

// Error reporting für AJAX-Requests deaktivieren
error_reporting(E_ERROR | E_WARNING | E_PARSE);
ini_set('display_errors', 0);

try {
    // Terminal-Modul initialisieren
    $terminalModule = new TerminalModule('terminal');
    
    // Action aus POST-Daten extrahieren
    $action = $_POST['action'] ?? '';
    
    if (empty($action)) {
        echo json_encode(['success' => false, 'error' => 'No action specified']);
        exit;
    }
    
    // Debug-Log
    error_log("Terminal Module Helper: Processing action - $action");
    
    // Daten sammeln (ohne action)
    $data = $_POST;
    unset($data['action']);
    
    // AJAX-Request verarbeiten
    $result = $terminalModule->handleAjaxRequest($action, $data);
    
    // Debug-Log für Response
    error_log("Terminal Module Helper: Response - " . json_encode($result));
    
    // JSON-Response ausgeben
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Terminal Module Helper Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Module error: ' . $e->getMessage()
    ]);
}
?>
