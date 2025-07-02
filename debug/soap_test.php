<?php
/**
 * SOAP Test für ISPConfig
 * Testet SOAP-Extension und ISPConfig-Verbindung isoliert
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$result = [
    'success' => false,
    'tests' => [],
    'errors' => [],
    'debug_info' => []
];

try {
    // Test 1: SOAP Extension prüfen
    $result['tests']['soap_extension'] = [
        'name' => 'SOAP Extension',
        'status' => extension_loaded('soap') ? 'OK' : 'FEHLT',
        'details' => extension_loaded('soap') ? 'SOAP Extension ist geladen' : 'SOAP Extension nicht installiert!'
    ];
    
    if (!extension_loaded('soap')) {
        $result['errors'][] = 'SOAP Extension fehlt - installiere mit: sudo apt-get install php-soap';
        echo json_encode($result);
        exit;
    }
    
    // Test 2: Framework laden
    try {
        require_once '../framework.php';
        $result['tests']['framework'] = [
            'name' => 'Framework laden',
            'status' => 'OK',
            'details' => 'framework.php erfolgreich geladen'
        ];
    } catch (Exception $e) {
        $result['tests']['framework'] = [
            'name' => 'Framework laden',
            'status' => 'FEHLER',
            'details' => $e->getMessage()
        ];
        $result['errors'][] = 'Framework Fehler: ' . $e->getMessage();
    }
    
    // Test 3: ISPConfig Config prüfen
    $result['debug_info']['ispconfig_config'] = [
        'host' => Config::ISPCONFIG_HOST,
        'user' => Config::ISPCONFIG_USER,
        'password_set' => !empty(Config::ISPCONFIG_PASSWORD)
    ];
    
    if (Config::ISPCONFIG_HOST === 'https://your-ispconfig-host:8080') {
        $result['errors'][] = 'ISPConfig Host nicht konfiguriert - noch Standard-Wert!';
        $result['tests']['ispconfig_config'] = [
            'name' => 'ISPConfig Konfiguration',
            'status' => 'NICHT_KONFIGURIERT',
            'details' => 'Host, User oder Passwort nicht gesetzt'
        ];
    } else {
        $result['tests']['ispconfig_config'] = [
            'name' => 'ISPConfig Konfiguration',
            'status' => 'OK',
            'details' => 'Konfiguration scheint gesetzt zu sein'
        ];
    }
    
    // Test 4: SOAP Client erstellen (ohne ISPConfig zu kontaktieren)
    try {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        
        $soap_url = Config::ISPCONFIG_HOST . '/remote/index.php';
        $soap_uri = Config::ISPCONFIG_HOST . '/remote/';
        
        $result['debug_info']['soap_connection'] = [
            'url' => $soap_url,
            'uri' => $soap_uri
        ];
        
        // Zunächst nur testen ob SOAP Client erstellt werden kann
        $client = new SoapClient(null, [
            'location' => $soap_url,
            'uri' => $soap_uri,
            'trace' => 1,
            'exceptions' => 1,
            'stream_context' => $context,
            'connection_timeout' => 5,
            'cache_wsdl' => WSDL_CACHE_NONE
        ]);
        
        $result['tests']['soap_client'] = [
            'name' => 'SOAP Client',
            'status' => 'OK',
            'details' => 'SOAP Client erfolgreich erstellt'
        ];
        
        // Test 5: ISPConfig Login versuchen (nur wenn alles andere OK)
        if (Config::ISPCONFIG_HOST !== 'https://your-ispconfig-host:8080') {
            try {
                $session_id = $client->login(Config::ISPCONFIG_USER, Config::ISPCONFIG_PASSWORD);
                
                if ($session_id) {
                    $result['tests']['ispconfig_login'] = [
                        'name' => 'ISPConfig Login',
                        'status' => 'OK',
                        'details' => 'Login erfolgreich - Session ID erhalten'
                    ];
                    
                    // Test 6: Server Config abrufen
                    try {
                        $server_config = $client->server_get($session_id, 1);
                        $result['tests']['ispconfig_data'] = [
                            'name' => 'ISPConfig Daten',
                            'status' => 'OK',
                            'details' => 'Server-Konfiguration erfolgreich abgerufen'
                        ];
                        $result['success'] = true;
                    } catch (Exception $e) {
                        $result['tests']['ispconfig_data'] = [
                            'name' => 'ISPConfig Daten',
                            'status' => 'FEHLER',
                            'details' => 'Fehler beim Abrufen der Server-Config: ' . $e->getMessage()
                        ];
                    }
                    
                } else {
                    $result['tests']['ispconfig_login'] = [
                        'name' => 'ISPConfig Login',
                        'status' => 'FEHLER',
                        'details' => 'Login fehlgeschlagen - keine Session ID erhalten'
                    ];
                    $result['errors'][] = 'ISPConfig Login fehlgeschlagen - prüfe Benutzername und Passwort';
                }
                
            } catch (SoapFault $e) {
                $result['tests']['ispconfig_login'] = [
                    'name' => 'ISPConfig Login',
                    'status' => 'SOAP_FEHLER',
                    'details' => 'SOAP Fehler: ' . $e->getMessage()
                ];
                $result['errors'][] = 'SOAP Fehler: ' . $e->getMessage();
                
                // Debug-Infos zu SOAP-Fehlern
                $result['debug_info']['soap_error'] = [
                    'faultcode' => $e->faultcode,
                    'faultstring' => $e->faultstring,
                    'last_request' => $client->__getLastRequest(),
                    'last_response' => $client->__getLastResponse()
                ];
                
            } catch (Exception $e) {
                $result['tests']['ispconfig_login'] = [
                    'name' => 'ISPConfig Login',
                    'status' => 'VERBINDUNGS_FEHLER',
                    'details' => 'Verbindungsfehler: ' . $e->getMessage()
                ];
                $result['errors'][] = 'Verbindungsfehler zu ISPConfig: ' . $e->getMessage();
            }
        }
        
    } catch (SoapFault $e) {
        $result['tests']['soap_client'] = [
            'name' => 'SOAP Client',
            'status' => 'SOAP_FEHLER',
            'details' => 'SOAP Client Fehler: ' . $e->getMessage()
        ];
        $result['errors'][] = 'SOAP Client Fehler: ' . $e->getMessage();
        
    } catch (Exception $e) {
        $result['tests']['soap_client'] = [
            'name' => 'SOAP Client',
            'status' => 'FEHLER',
            'details' => 'Unerwarteter Fehler: ' . $e->getMessage()
        ];
        $result['errors'][] = 'SOAP Client Fehler: ' . $e->getMessage();
    }
    
    // Test 7: Netzwerk-Verbindung zu ISPConfig prüfen
    if (Config::ISPCONFIG_HOST !== 'https://your-ispconfig-host:8080') {
        $parsed_url = parse_url(Config::ISPCONFIG_HOST);
        $host = $parsed_url['host'];
        $port = $parsed_url['port'] ?? 8080;
        
        $connection = @fsockopen($host, $port, $errno, $errstr, 5);
        if ($connection) {
            fclose($connection);
            $result['tests']['network'] = [
                'name' => 'Netzwerk-Verbindung',
                'status' => 'OK',
                'details' => "Verbindung zu $host:$port erfolgreich"
            ];
        } else {
            $result['tests']['network'] = [
                'name' => 'Netzwerk-Verbindung',
                'status' => 'FEHLER',
                'details' => "Kann nicht zu $host:$port verbinden - $errstr ($errno)"
            ];
            $result['errors'][] = "Netzwerk-Problem: Kann nicht zu $host:$port verbinden";
        }
    }
    
} catch (Exception $e) {
    $result['errors'][] = 'Kritischer Fehler: ' . $e->getMessage();
    $result['debug_info']['critical_error'] = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];
}

// Statistik
$success_count = 0;
$total_count = count($result['tests']);
foreach ($result['tests'] as $test) {
    if ($test['status'] === 'OK') {
        $success_count++;
    }
}

$result['summary'] = [
    'tests_passed' => $success_count,
    'tests_total' => $total_count,
    'has_errors' => !empty($result['errors']),
    'overall_status' => empty($result['errors']) ? 'OK' : 'PROBLEME_GEFUNDEN'
];

echo json_encode($result, JSON_PRETTY_PRINT);
?>