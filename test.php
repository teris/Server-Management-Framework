<?php 
require_once 'framework.php';
require_once 'auth.php';

session_start();

echo "<pre>";
// Quick Check
$tester = new AuthenticationTester();
$results = $tester->testAllConnections();

$tester->testProxmoxConnection();
$tester->testISPConfigConnection();  
$tester->testOVHConnection();
try {
	$res = $tester->testISPConfigConnection();
	if ($res === false) {
        echo "Fehler beim Lesen";
    } 
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
}


echo "<hr>";
$serviceManager = new ServiceManager();
print_r($serviceManager->getISPConfigEmails());