<?php 
require_once 'framework.php';

session_start();

echo "<pre>";
$serviceManager = new ServiceManager();

// Spezifische Methoden
$servers = $serviceManager->getOGPServerList();

$ovhDomain	= $serviceManager->getOVHDomains();

$ispDomain = $serviceManager->getISPConfigEmails();

$networks = $serviceManager->getProxmoxNetwork();



echo "<h2>getOGPServerList - OGP</h2>";
print_r($servers);;
echo "<h2>getDomains - OVH</h2>";
print_r($ovhDomain);
echo "<h2>getISPConfigEmails - ISPConfig 3</h2>";
print_r($ispDomain);
echo "<h2>getProxmoxNetwork - Proxmox</h2>";
print_r($networks);
