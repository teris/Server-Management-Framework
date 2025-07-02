<?php
/**
 * ISPConfig Debug Tool - Systematisches Debugging der ISPConfig-Verbindung
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../framework.php';

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>🔍 ISPConfig Debug Tool</title>
    <style>
        body { font-family: monospace; margin: 20px; background: #1e1e1e; color: #fff; }
        .debug-section { background: #2d2d2d; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { border-left: 4px solid #51cf66; }
        .error { border-left: 4px solid #ff6b6b; }
        .warning { border-left: 4px solid #ffd43b; }
        .info { border-left: 4px solid #74c0fc; }
        pre { background: #1a1a1a; padding: 10px; border-radius: 3px; white-space: pre-wrap; word-wrap: break-word; max-height: 400px; overflow-y: auto; }
        .step { margin: 20px 0; padding: 15px; background: #2a2a2a; border-left: 4px solid #007acc; }
        .result { margin: 10px 0; padding: 10px; background: #1a1a1a; border-radius: 3px; }
        button { background: #007acc; color: white; border: none; padding: 8px 15px; margin: 5px; border-radius: 3px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>🔍 ISPConfig Debug Tool</h1>
    
    <?php
    
    echo "<div class='debug-section info'>";
    echo "<h3>🎯 Problem: getISPConfigEmails() gibt keine Daten zurück</h3>";
    echo "<p>Systematisches Debugging der ISPConfig-Verbindung...</p>";
    echo "</div>";
    
    // =================================================================
    // SCHRITT 1: Grundlegende Checks
    // =================================================================
    
    echo "<div class='step'>";
    echo "<h3>📋 Schritt 1: Grundlegende Checks</h3>";
    
    // SOAP Extension
    echo "<div class='result'>";
    if (extension_loaded('soap')) {
        echo "✅ <strong>SOAP Extension:</strong> Verfügbar<br>";
    } else {
        echo "❌ <strong>SOAP Extension:</strong> FEHLT! Installiere mit: sudo apt-get install php-soap<br>";
    }
    echo "</div>";
    
    // Konfiguration
    echo "<div class='result'>";
    echo "<strong>ISPConfig Konfiguration:</strong><br>";
    echo "Host: " . Config::ISPCONFIG_HOST . "<br>";
    echo "User: " . Config::ISPCONFIG_USER . "<br>";
    echo "Password: " . (empty(Config::ISPCONFIG_PASSWORD) ? "❌ NICHT GESETZT" : "✅ Gesetzt (" . strlen(Config::ISPCONFIG_PASSWORD) . " Zeichen)") . "<br>";
    
    if (Config::ISPCONFIG_HOST === 'https://your-ispconfig-host:8080') {
        echo "❌ <strong>PROBLEM:</strong> Host noch nicht konfiguriert (Standard-Werte)!<br>";
    }
    echo "</div>";
    
    echo "</div>";
    
    // =================================================================
    // SCHRITT 2: Netzwerk-Verbindung testen
    // =================================================================
    
    echo "<div class='step'>";
    echo "<h3>🌐 Schritt 2: Netzwerk-Verbindung testen</h3>";
    
    if (Config::ISPCONFIG_HOST !== 'https://your-ispconfig-host:8080') {
        $parsed_url = parse_url(Config::ISPCONFIG_HOST);
        $host = $parsed_url['host'];
        $port = $parsed_url['port'] ?? 8080;
        
        echo "<div class='result'>";
        echo "<strong>Teste Verbindung zu $host:$port...</strong><br>";
        
        $connection = @fsockopen($host, $port, $errno, $errstr, 5);
        if ($connection) {
            fclose($connection);
            echo "✅ Netzwerk-Verbindung erfolgreich<br>";
            
            // HTTP-Test
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ],
                'http' => [
                    'timeout' => 10
                ]
            ]);
            
            $test_url = Config::ISPCONFIG_HOST . '/remote/';
            $response = @file_get_contents($test_url, false, $context);
            
            if ($response !== false) {
                echo "✅ HTTP-Verbindung zu /remote/ erfolgreich<br>";
                echo "Response Length: " . strlen($response) . " Bytes<br>";
                if (strpos($response, 'soap') !== false || strpos($response, 'wsdl') !== false) {
                    echo "✅ SOAP-Endpunkt verfügbar<br>";
                }
            } else {
                echo "⚠️ HTTP-Verbindung zu /remote/ fehlgeschlagen<br>";
            }
            
        } else {
            echo "❌ Netzwerk-Verbindung fehlgeschlagen: $errstr ($errno)<br>";
        }
        echo "</div>";
    } else {
        echo "<div class='result error'>";
        echo "❌ Kann Netzwerk nicht testen - Host nicht konfiguriert<br>";
        echo "</div>";
    }
    
    echo "</div>";
    
    // =================================================================
    // SCHRITT 3: SOAP Client Test
    // =================================================================
    
    echo "<div class='step'>";
    echo "<h3>🔧 Schritt 3: SOAP Client Test</h3>";
    
    if (extension_loaded('soap') && Config::ISPCONFIG_HOST !== 'https://your-ispconfig-host:8080') {
        echo "<div class='result'>";
        
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
            
            echo "<strong>Erstelle SOAP Client...</strong><br>";
            echo "Location: $soap_url<br>";
            echo "URI: $soap_uri<br>";
            
            $client = new SoapClient(null, [
                'location' => $soap_url,
                'uri' => $soap_uri,
                'trace' => 1,
                'exceptions' => 1,
                'stream_context' => $context,
                'connection_timeout' => 10,
                'cache_wsdl' => WSDL_CACHE_NONE
            ]);
            
            echo "✅ SOAP Client erfolgreich erstellt<br>";
            
            // Login testen
            echo "<strong>Teste ISPConfig Login...</strong><br>";
            $session_id = $client->login(Config::ISPCONFIG_USER, Config::ISPCONFIG_PASSWORD);
            
            if ($session_id) {
                echo "✅ Login erfolgreich! Session ID: " . substr($session_id, 0, 20) . "...<br>";
                
                // E-Mail Accounts abrufen
                echo "<strong>Teste E-Mail Accounts abrufen...</strong><br>";
                $emails = $client->mail_user_get($session_id, []);
                
                echo "<strong>E-Mail Response:</strong><br>";
                echo "<pre>";
                if (is_array($emails)) {
                    echo "Array mit " . count($emails) . " Einträgen:\n";
                    if (count($emails) > 0) {
                        echo "Erster Eintrag:\n";
                        print_r($emails[0]);
                        if (count($emails) > 1) {
                            echo "\nWeitere " . (count($emails) - 1) . " Einträge...\n";
                        }
                    } else {
                        echo "❌ PROBLEM: Array ist leer - keine E-Mail Accounts gefunden!\n";
                        echo "Mögliche Ursachen:\n";
                        echo "- Keine E-Mail Accounts in ISPConfig erstellt\n";
                        echo "- User hat keine Berechtigung\n";
                        echo "- Falsche API-Methode\n";
                    }
                } elseif ($emails === false) {
                    echo "❌ PROBLEM: mail_user_get() gab FALSE zurück\n";
                } elseif (is_null($emails)) {
                    echo "❌ PROBLEM: mail_user_get() gab NULL zurück\n";
                } else {
                    echo "⚠️ Unerwarteter Typ: " . gettype($emails) . "\n";
                    var_dump($emails);
                }
                echo "</pre>";
                
                // Weitere Tests
                echo "<strong>Teste andere ISPConfig Methoden...</strong><br>";
                
                // Server Config
                try {
                    $server_config = $client->server_get($session_id, 1);
                    echo "✅ server_get(): " . (is_array($server_config) ? "Array mit " . count($server_config) . " Einträgen" : gettype($server_config)) . "<br>";
                } catch (Exception $e) {
                    echo "❌ server_get() Fehler: " . $e->getMessage() . "<br>";
                }
                
                // Client get
                try {
                    $clients = $client->client_get($session_id, []);
                    echo "✅ client_get(): " . (is_array($clients) ? "Array mit " . count($clients) . " Einträgen" : gettype($clients)) . "<br>";
                } catch (Exception $e) {
                    echo "❌ client_get() Fehler: " . $e->getMessage() . "<br>";
                }
                
                // Websites
                try {
                    $websites = $client->sites_web_domain_get($session_id, []);
                    echo "✅ sites_web_domain_get(): " . (is_array($websites) ? "Array mit " . count($websites) . " Einträgen" : gettype($websites)) . "<br>";
                } catch (Exception $e) {
                    echo "❌ sites_web_domain_get() Fehler: " . $e->getMessage() . "<br>";
                }
                
                // Datenbanken
                try {
                    $databases = $client->sites_database_get($session_id, []);
                    echo "✅ sites_database_get(): " . (is_array($databases) ? "Array mit " . count($databases) . " Einträgen" : gettype($databases)) . "<br>";
                } catch (Exception $e) {
                    echo "❌ sites_database_get() Fehler: " . $e->getMessage() . "<br>";
                }
                
            } else {
                echo "❌ Login fehlgeschlagen - keine Session ID erhalten<br>";
                echo "Prüfe Benutzername und Passwort in framework.php<br>";
                
                echo "<strong>SOAP Debug Info:</strong><br>";
                echo "<pre>";
                echo "Request:\n" . htmlspecialchars($client->__getLastRequest()) . "\n\n";
                echo "Response:\n" . htmlspecialchars($client->__getLastResponse()) . "\n";
                echo "</pre>";
            }
            
        } catch (SoapFault $e) {
            echo "❌ SOAP Fehler: " . $e->getMessage() . "<br>";
            echo "Fault Code: " . $e->faultcode . "<br>";
            echo "Fault String: " . $e->faultstring . "<br>";
            
            if (isset($client)) {
                echo "<strong>SOAP Debug Info:</strong><br>";
                echo "<pre>";
                echo "Request:\n" . htmlspecialchars($client->__getLastRequest()) . "\n\n";
                echo "Response:\n" . htmlspecialchars($client->__getLastResponse()) . "\n";
                echo "</pre>";
            }
            
        } catch (Exception $e) {
            echo "❌ Allgemeiner Fehler: " . $e->getMessage() . "<br>";
            echo "Datei: " . $e->getFile() . " Zeile: " . $e->getLine() . "<br>";
        }
        
        echo "</div>";
    } else {
        echo "<div class='result error'>";
        echo "❌ Kann SOAP Client nicht testen - Extension fehlt oder Host nicht konfiguriert<br>";
        echo "</div>";
    }
    
    echo "</div>";
    
    // =================================================================
    // SCHRITT 4: Framework ISPConfig Klasse testen
    // =================================================================
    
    echo "<div class='step'>";
    echo "<h3>🏗️ Schritt 4: Framework ISPConfig Klasse testen</h3>";
    
    echo "<div class='result'>";
    
    try {
        echo "<strong>Teste ISPConfigGet Klasse...</strong><br>";
        $ispconfigGet = new ISPConfigGet();
        echo "✅ ISPConfigGet Instanz erstellt<br>";
        
        echo "<strong>Teste getEmailAccounts()...</strong><br>";
        $emails = $ispconfigGet->getEmailAccounts();
        
        echo "getEmailAccounts() Ergebnis:<br>";
        echo "<pre>";
        if (is_array($emails)) {
            echo "Array mit " . count($emails) . " Einträgen\n";
            if (count($emails) > 0) {
                echo "Erster Eintrag (EmailAccount Object):\n";
                $firstEmail = $emails[0];
                if (is_object($firstEmail)) {
                    print_r($firstEmail->toArray());
                } else {
                    print_r($firstEmail);
                }
            }
        } else {
            echo "❌ PROBLEM: Kein Array zurückgegeben\n";
            echo "Typ: " . gettype($emails) . "\n";
            var_dump($emails);
        }
        echo "</pre>";
        
        echo "<strong>Teste getServerConfig()...</strong><br>";
        $config = $ispconfigGet->getServerConfig();
        echo "Server Config: " . (is_array($config) ? "Array mit " . count($config) . " Einträgen" : gettype($config)) . "<br>";
        
    } catch (Exception $e) {
        echo "❌ Framework Fehler: " . $e->getMessage() . "<br>";
        echo "Datei: " . $e->getFile() . " Zeile: " . $e->getLine() . "<br>";
    }
    
    echo "</div>";
    echo "</div>";
    
    // =================================================================
    // LÖSUNGSVORSCHLÄGE
    // =================================================================
    
    echo "<div class='debug-section warning'>";
    echo "<h3>💡 Lösungsvorschläge</h3>";
    echo "<ul>";
    echo "<li><strong>Keine E-Mails in ISPConfig:</strong> Erstelle zuerst E-Mail Accounts im ISPConfig Panel</li>";
    echo "<li><strong>User-Berechtigung:</strong> Prüfe ob der API-User Berechtigung für E-Mail Verwaltung hat</li>";
    echo "<li><strong>Remote API:</strong> Stelle sicher dass Remote API in ISPConfig aktiviert ist</li>";
    echo "<li><strong>SOAP Fehler:</strong> Prüfe ISPConfig Logs in /var/log/ispconfig/</li>";
    echo "<li><strong>SSL Problem:</strong> Teste mit HTTP statt HTTPS</li>";
    echo "</ul>";
    echo "</div>";
    
    ?>
    
    <div class="debug-section info">
        <h3>🔧 Nächste Schritte</h3>
        <p><strong>Wenn keine E-Mails gefunden werden:</strong></p>
        <ol>
            <li>Logge dich ins ISPConfig Panel ein</li>
            <li>Gehe zu "E-Mail → E-Mail Mailbox"</li>
            <li>Erstelle einen Test-E-Mail Account</li>
            <li>Führe diesen Debug erneut aus</li>
        </ol>
        
        <p><strong>Wenn SOAP Fehler auftreten:</strong></p>
        <ol>
            <li>Prüfe ISPConfig Logs: <code>tail -f /var/log/ispconfig/ispconfig.log</code></li>
            <li>Aktiviere Remote API: System → Interface-Config → Remote API</li>
            <li>Prüfe User-Berechtigung für Remote API</li>
        </ol>
    </div>
</body>
</html>