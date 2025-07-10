<?php
// debug/debug_ovh_failover_mac.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Adjust the path to framework.php based on the new location of this debug script
require_once __DIR__ . '/../framework.php';

echo "<!DOCTYPE html><html><head><title>OVH Failover IP MAC Debug</title>";
echo "<style>body { font-family: sans-serif; margin: 20px; } table { border-collapse: collapse; width: 100%; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background-color: #f2f2f2; } .error { color: red; font-weight: bold; } .success { color: green; } .info { color: blue; }</style>";
echo "</head><body>";
echo "<h1>OVH Failover IP MAC Address Debugger</h1>";

// --- IMPORTANT ---
echo "<p class='info'><strong>Wichtig:</strong> Stellen Sie sicher, dass Ihre OVH API-Zugangsdaten in <code>framework.php</code> (Config Klasse) korrekt eingetragen sind, damit dieses Skript live API-Aufrufe tätigen kann.</p>";
// --- IMPORTANT ---

try {
    echo "<p>Initialisiere ServiceManager...</p>";
    $serviceManager = new ServiceManager();
    echo "<p class='success'>ServiceManager initialisiert.</p>";

    echo "<p>Rufe getOVHFailoverIPs() auf...</p>";
    $failoverIPs = $serviceManager->getOVHFailoverIPs();
    echo "<p class='success'>getOVHFailoverIPs() Aufruf beendet.</p>";

    if (empty($failoverIPs)) {
        echo "<p class='info'>Keine Failover IPs gefunden oder ein Fehler ist beim Abrufen aufgetreten.</p>";
    } else {
        echo "<h2>Gefundene Failover IPs (" . count($failoverIPs) . "):</h2>";
        echo "<table><thead><tr>";
        echo "<th>IP Adresse</th>";
        echo "<th>Block</th>";
        echo "<th>Geroutet zu (Dienst)</th>";
        echo "<th>Typ</th>";
        echo "<th>Geo/Land</th>";
        echo "<th>Virtuelle MAC</th>";
        echo "<th>Rohdaten Objekt</th>";
        echo "</tr></thead><tbody>";

        foreach ($failoverIPs as $ipObject) {
            if ($ipObject instanceof FailoverIP) {
                $routedToDisplay = 'N/A';
                if (is_string($ipObject->routedTo)) {
                    $routedToDisplay = htmlspecialchars($ipObject->routedTo);
                } elseif (is_array($ipObject->routedTo) && isset($ipObject->routedTo['serviceName'])) {
                    $routedToDisplay = htmlspecialchars($ipObject->routedTo['serviceName']);
                } elseif (is_object($ipObject->routedTo) && isset($ipObject->routedTo->serviceName)) {
                    $routedToDisplay = htmlspecialchars($ipObject->routedTo->serviceName);
                } elseif (!empty($ipObject->routedTo)) {
                    $routedToDisplay = htmlspecialchars(json_encode($ipObject->routedTo));
                }


                echo "<tr>";
                echo "<td>" . htmlspecialchars($ipObject->ip ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($ipObject->block ?? 'N/A') . "</td>";
                echo "<td>" . $routedToDisplay . "</td>";
                echo "<td>" . htmlspecialchars($ipObject->type ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($ipObject->country ?? ($ipObject->geo ?? 'N/A')) . "</td>";
                echo "<td><strong>" . htmlspecialchars($ipObject->virtualMac ?? 'Nicht vorhanden') . "</strong></td>";
                echo "<td><pre>" . htmlspecialchars(print_r($ipObject->toArray(), true)) . "</pre></td>";
                echo "</tr>";
            } else {
                echo "<tr><td colspan='7' class='error'>Unerwartetes Datenformat für IP erhalten.</td></tr>";
                echo "<tr><td colspan='7'><pre>" . htmlspecialchars(print_r($ipObject, true)) . "</pre></td></tr>";
            }
        }
        echo "</tbody></table>";
    }

} catch (PDOException $e) {
    echo "<h2>Datenbank Verbindungsfehler</h2>";
    echo "<p class='error'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p class='info'>Obwohl dies ein OVH-spezifischer Test ist, versucht das Framework möglicherweise, API-Aufrufe zu protokollieren, was eine Datenbankverbindung erfordert. Dieser Fehler könnte das Testen der OVH-Funktionalität verhindern, wenn Logeinträge nicht erfolgreich sind.</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
} catch (Exception $e) {
    echo "<h2>Ein allgemeiner Fehler ist aufgetreten</h2>";
    echo "<p class='error'>Fehler: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</body></html>";
?>
