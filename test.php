<?php 
/**
 * Server Management Framework
 * 
 * @author Teris
 * @version 3.1.2
 */
require_once 'framework.php';

session_start();

echo "<pre>";
$serviceManager = new ServiceManager();

// Spezifische Methoden
//$servers = $serviceManager->getISPConfigClients();
//$ovhDomain	= $serviceManager->getSystemInfo();
//$getOGPGamesList = $serviceManager->getOGPGamesList("windows","32");
//echo "<h2>getOGPServerList</h2>";
//print_r($servers);;
//echo "<h2>getOGPGameServers</h2>";
//print_r($ovhDomain);
//echo "<h2>getOGPGamesList</h2>";
//print_r($getOGPGamesList);

// Korrigierter Aufruf mit dem fehlenden zweiten Parameter
// Der zweite Parameter ist normalerweise eine client_id oder ein Filter-Array
//print_r($serviceManager->IspconfigAPI('get', 'client_templates_get_all'));


print_r($serviceManager->IspconfigAPI('get', 'get_function_list'));


