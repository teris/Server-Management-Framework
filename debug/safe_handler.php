<?php
/**
 * Sicherer Handler mit verbesserter Fehlerbehandlung
 * Erstelle diese Datei und teste sie direkt
 */

// JSON-Header immer setzen
header('Content-Type: application/json');

// Error Handler für JSON-Output
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Exception Handler für unbehandelte Exceptions
set_exception_handler(function($exception) {
    $error_response = [
        'success' => false,
        'error' => $exception->getMessage(),
        'debug' => [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'type' => get_class($exception),
            'trace' => $exception->getTraceAsString()
        ]
    ];
    echo json_encode($error_response, JSON_PRETTY_PRINT);
    exit;
});

// Output buffering starten um HTML-Output zu verhindern
ob_start();

$response = [
    'success' => false,
    'error' => 'Unbekannte Aktion',
    'debug' => [
        'timestamp' => date('Y-m-d H:i:s'),
        'php_version' => PHP_VERSION,
        'memory_usage' => memory_get_usage(true),
        'action' => $_POST['action'] ?? 'KEINE_AKTION'
    ]
];

try {
    // Framework laden
    if (!file_exists('../framework.php')) {
        throw new Exception('framework.php nicht gefunden');
    }
    
    require_once 'framework.php';
    $response['debug']['framework_loaded'] = true;
    
    // Auth Handler laden
    if (!file_exists('../auth_handler.php')) {
        throw new Exception('auth_handler.php nicht gefunden');
    }
    
    require_once '../auth_handler.php';
    $response['debug']['auth_loaded'] = true;
    
    // Session starten
    SessionManager::startSession();
    $response['debug']['session_started'] = true;
    $response['debug']['session_id'] = session_id();
    
    // Login-Status prüfen
    $is_logged_in = SessionManager::isLoggedIn();
    $response['debug']['logged_in'] = $is_logged_in;
    
    if ($is_logged_in) {
        $user_info = SessionManager::getUserInfo();
        $response['debug']['user'] = [
            'username' => $user_info['username'],
            'role' => $user_info['role']
        ];
    }
    
    // Aktion verarbeiten
    $action = $_POST['action'] ?? '';
    $response['debug']['requested_action'] = $action;
    
    if (empty($action)) {
        throw new Exception('Keine Aktion spezifiziert');
    }
    
    // Service Manager initialisieren
    try {
        $serviceManager = new ServiceManager();
        $response['debug']['service_manager_created'] = true;
    } catch (Exception $e) {
        $response['debug']['service_manager_error'] = $e->getMessage();
        throw new Exception('Service Manager Fehler: ' . $e->getMessage());
    }
    
    // Database Instanz
    try {
        $db = Database::getInstance();
        $response['debug']['database_connected'] = true;
    } catch (Exception $e) {
        $response['debug']['database_error'] = $e->getMessage();
        throw new Exception('Datenbank Fehler: ' . $e->getMessage());
    }
    
    // Aktionen verarbeiten
    switch ($action) {
        case 'test_handler':
            $response = [
                'success' => true,
                'message' => 'Handler funktioniert!',
                'data' => [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'server_info' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                    'php_version' => PHP_VERSION
                ]
            ];
            break;
            
        case 'get_ispconfig_server_config':
            $response['debug']['testing_ispconfig'] = true;
            
            // ISPConfig spezifische Tests
            if (!extension_loaded('soap')) {
                throw new Exception('SOAP Extension nicht geladen');
            }
            $response['debug']['soap_available'] = true;
            
            // Konfiguration prüfen
            if (Config::ISPCONFIG_HOST === 'https://your-ispconfig-host:8080') {
                throw new Exception('ISPConfig noch nicht konfiguriert - Standard-Werte in framework.php');
            }
            
            $response['debug']['ispconfig_config'] = [
                'host' => Config::ISPCONFIG_HOST,
                'user' => Config::ISPCONFIG_USER
            ];
            
            // ISPConfig testen
            try {
                $ispconfigGet = new ISPConfigGet();
                $response['debug']['ispconfig_instance_created'] = true;
                
                $config = $ispconfigGet->getServerConfig();
                $response['debug']['server_config_called'] = true;
                
                if ($config && is_array($config)) {
                    $response = [
                        'success' => true,
                        'data' => $config,
                        'message' => 'ISPConfig Server-Konfiguration erfolgreich abgerufen'
                    ];
                } else {
                    throw new Exception('Server-Konfiguration ist leer oder ungültig');
                }
                
            } catch (SoapFault $e) {
                $response['debug']['soap_fault'] = [
                    'faultcode' => $e->faultcode,
                    'faultstring' => $e->faultstring
                ];
                throw new Exception('SOAP Fehler: ' . $e->faultstring);
                
            } catch (Exception $e) {
                $response['debug']['ispconfig_error'] = $e->getMessage();
                throw new Exception('ISPConfig Fehler: ' . $e->getMessage());
            }
            break;
            
        case 'get_activity_log':
            if (!$is_logged_in) {
                throw new Exception('Nicht eingeloggt');
            }
            
            $logs = $db->getActivityLog(10);
            $response = [
                'success' => true,
                'data' => $logs,
                'message' => 'Activity Log erfolgreich abgerufen'
            ];
            break;
            
        case 'extend_session':
            if (!$is_logged_in) {
                throw new Exception('Nicht eingeloggt');
            }
            
            SessionManager::updateActivity();
            $response = [
                'success' => true,
                'message' => 'Session verlängert',
                'data' => [
                    'time_remaining' => SessionManager::getSessionTimeRemaining()
                ]
            ];
            break;
            
        default:
            throw new Exception('Unbekannte Aktion: ' . $action);
    }
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => array_merge($response['debug'] ?? [], [
            'exception_type' => get_class($e),
            'exception_file' => $e->getFile(),
            'exception_line' => $e->getLine(),
            'exception_trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 5) // Nur erste 5 Zeilen
        ])
    ];
}

// Buffer leeren um sicherzustellen dass nur JSON ausgegeben wird
$unwanted_output = ob_get_clean();
if (!empty($unwanted_output)) {
    $response['debug']['unwanted_output'] = $unwanted_output;
}

// JSON ausgeben
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
?>