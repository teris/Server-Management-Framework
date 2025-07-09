<?php
/**
 * Update Script - Synchronisiert API-Daten mit der lokalen Datenbank
 * Speichert auch die API-Credentials aus config.inc.php in der Datenbank
 */

require_once 'framework.php';
require_once 'auth_handler.php';

// Nur Admins dürfen dieses Script ausführen
if (php_sapi_name() !== 'cli') {
    requireLogin();
    if (!SessionManager::isAdmin()) {
        die('Zugriff verweigert. Nur Administratoren können dieses Script ausführen.');
    }
}

class DatabaseUpdater {
    private $db;
    private $serviceManager;
    private $stats = [
        'vms' => ['added' => 0, 'updated' => 0, 'errors' => 0],
        'websites' => ['added' => 0, 'updated' => 0, 'errors' => 0],
        'databases' => ['added' => 0, 'updated' => 0, 'errors' => 0],
        'emails' => ['added' => 0, 'updated' => 0, 'errors' => 0],
        'domains' => ['added' => 0, 'updated' => 0, 'errors' => 0],
        'credentials' => ['updated' => 0, 'errors' => 0]
    ];
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->serviceManager = new ServiceManager();
    }
    
    /**
     * Führt das komplette Update aus
     */
    public function runFullUpdate() {
        $this->output("🚀 Starte Datenbank-Update...\n");
        $this->output(str_repeat("=", 60) . "\n\n");
        
        $startTime = microtime(true);
        
        // 1. API Credentials aktualisieren
        $this->updateApiCredentials();
        
        // 2. Proxmox VMs synchronisieren
        $this->syncProxmoxVMs();
        
        // 3. ISPConfig Daten synchronisieren
        $this->syncISPConfigWebsites();
        $this->syncISPConfigDatabases();
        $this->syncISPConfigEmails();
        
        // 4. OVH Domains synchronisieren
        $this->syncOVHDomains();
        
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        
        // Zusammenfassung anzeigen
        $this->showSummary($duration);
    }
    
    /**
     * Speichert/Aktualisiert API-Credentials aus config.inc.php
     */
    private function updateApiCredentials() {
        $this->output("📋 Aktualisiere API-Credentials...\n");
        
        try {
            $conn = $this->db->getConnection();
            
            // Proxmox Credentials
            $this->upsertCredential($conn, 'proxmox', [
                'endpoint' => Config::PROXMOX_HOST,
                'username' => Config::PROXMOX_USER,
                'password' => Config::PROXMOX_PASSWORD,
                'additional_config' => json_encode(['verify_ssl' => false])
            ]);
            
            // ISPConfig Credentials
            $this->upsertCredential($conn, 'ispconfig', [
                'endpoint' => Config::ISPCONFIG_HOST,
                'username' => Config::ISPCONFIG_USER,
                'password' => Config::ISPCONFIG_PASSWORD,
                'additional_config' => json_encode(['soap_location' => Config::ISPCONFIG_HOST . '/remote/index.php'])
            ]);
            
            // OVH Credentials
            $this->upsertCredential($conn, 'ovh', [
                'endpoint' => 'https://eu.api.ovh.com/1.0',
                'username' => '',
                'password' => '',
                'api_key' => json_encode([
                    'application_key' => Config::OVH_APPLICATION_KEY,
                    'application_secret' => Config::OVH_APPLICATION_SECRET,
                    'consumer_key' => Config::OVH_CONSUMER_KEY,
                    'endpoint' => Config::OVH_ENDPOINT
                ]),
                'additional_config' => json_encode(['endpoint' => Config::OVH_ENDPOINT])
            ]);
            
            $this->output("✅ API-Credentials erfolgreich aktualisiert\n\n");
            
        } catch (Exception $e) {
            $this->output("❌ Fehler beim Aktualisieren der API-Credentials: " . $e->getMessage() . "\n\n");
            $this->stats['credentials']['errors']++;
        }
    }
    
    /**
     * Fügt Credentials ein oder aktualisiert sie
     */
    private function upsertCredential($conn, $serviceName, $data) {
        // Verschlüsselung der sensiblen Daten
        $encryptionKey = $this->getEncryptionKey();
        
        $stmt = $conn->prepare("
            INSERT INTO api_credentials 
            (service_name, endpoint, username, password_encrypted, api_key_encrypted, additional_config, active, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'y', NOW())
            ON DUPLICATE KEY UPDATE 
            endpoint = VALUES(endpoint),
            username = VALUES(username),
            password_encrypted = VALUES(password_encrypted),
            api_key_encrypted = VALUES(api_key_encrypted),
            additional_config = VALUES(additional_config),
            updated_at = NOW()
        ");
        
        $passwordEncrypted = !empty($data['password']) ? $this->encrypt($data['password'], $encryptionKey) : null;
        $apiKeyEncrypted = !empty($data['api_key']) ? $this->encrypt($data['api_key'], $encryptionKey) : null;
        
        $stmt->execute([
            $serviceName,
            $data['endpoint'],
            $data['username'],
            $passwordEncrypted,
            $apiKeyEncrypted,
            $data['additional_config']
        ]);
        
        $this->stats['credentials']['updated']++;
    }
    
    /**
     * Synchronisiert Proxmox VMs
     */
    private function syncProxmoxVMs() {
        $this->output("🖥️  Synchronisiere Proxmox VMs...\n");
        
        try {
            $vms = $this->serviceManager->getProxmoxVMs();
            $conn = $this->db->getConnection();
            
            foreach ($vms as $vm) {
                try {
                    // Memory von Bytes in MB konvertieren
                    $memoryInMB = 0;
                    if ($vm->memory) {
                        $memoryInMB = round($vm->memory / 1024 / 1024);
                        // Sicherstellen dass der Wert in den INT-Bereich passt
                        if ($memoryInMB > 2147483647) {
                            $memoryInMB = 2147483647; // Max INT value
                        }
                    }
                    
                    // Disk von Bytes in GB konvertieren
                    $diskInGB = 0;
                    if ($vm->disk) {
                        $diskInGB = round($vm->disk / 1024 / 1024 / 1024);
                        if ($diskInGB > 2147483647) {
                            $diskInGB = 2147483647;
                        }
                    }
                    
                    // Prüfen ob VM bereits existiert
                    $stmt = $conn->prepare("SELECT id FROM vms WHERE vm_id = ?");
                    $stmt->execute([$vm->vmid]);
                    $exists = $stmt->fetch();
                    
                    if ($exists) {
                        // Update
                        $stmt = $conn->prepare("
                            UPDATE vms SET 
                                name = ?, node = ?, status = ?, cores = ?, 
                                memory = ?, disk_size = ?, ip_address = ?, 
                                mac_address = ?, updated_at = NOW()
                            WHERE vm_id = ?
                        ");
                        
                        $stmt->execute([
                            $vm->name,
                            $vm->node,
                            $vm->status,
                            $vm->cores,
                            $memoryInMB,
                            $diskInGB,
                            $vm->ip_address,
                            $vm->mac_address,
                            $vm->vmid
                        ]);
                        
                        $this->stats['vms']['updated']++;
                    } else {
                        // Insert
                        $stmt = $conn->prepare("
                            INSERT INTO vms 
                            (vm_id, name, node, status, cores, memory, disk_size, ip_address, mac_address, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        
                        $stmt->execute([
                            $vm->vmid,
                            $vm->name,
                            $vm->node,
                            $vm->status,
                            $vm->cores,
                            $memoryInMB,
                            $diskInGB,
                            $vm->ip_address,
                            $vm->mac_address
                        ]);
                        
                        $this->stats['vms']['added']++;
                    }
                    
                } catch (Exception $e) {
                    $this->output("   ⚠️  Fehler bei VM {$vm->vmid}: " . $e->getMessage() . "\n");
                    $this->stats['vms']['errors']++;
                }
            }
            
            $this->output("   ✅ VMs synchronisiert: {$this->stats['vms']['added']} neu, {$this->stats['vms']['updated']} aktualisiert\n\n");
            
        } catch (Exception $e) {
            $this->output("   ❌ Fehler beim Abrufen der VMs: " . $e->getMessage() . "\n\n");
        }
    }
    
    /**
     * Synchronisiert ISPConfig Websites
     */
    private function syncISPConfigWebsites() {
        $this->output("🌐 Synchronisiere ISPConfig Websites...\n");
        
        try {
            $websites = $this->serviceManager->getISPConfigWebsites();
            $conn = $this->db->getConnection();
            
            foreach ($websites as $website) {
                try {
                    // Standardwerte für fehlende Felder
                    $ipAddress = $website->ip_address ?: '*'; // Wildcard IP wenn leer
                    $systemUser = $website->system_user ?: 'web' . $website->domain_id;
                    $systemGroup = $website->system_group ?: 'client1';
                    $documentRoot = $website->document_root ?: '/var/www/' . $website->domain;
                    
                    // Prüfen ob Website bereits existiert
                    $stmt = $conn->prepare("SELECT id FROM websites WHERE domain = ?");
                    $stmt->execute([$website->domain]);
                    $exists = $stmt->fetch();
                    
                    if ($exists) {
                        // Update
                        $stmt = $conn->prepare("
                            UPDATE websites SET 
                                ip_address = ?, system_user = ?, system_group = ?, 
                                document_root = ?, hd_quota = ?, traffic_quota = ?, 
                                active = ?, ssl_enabled = ?, updated_at = NOW()
                            WHERE domain = ?
                        ");
                        
                        $stmt->execute([
                            $ipAddress,
                            $systemUser,
                            $systemGroup,
                            $documentRoot,
                            $website->hd_quota ?: -1,
                            $website->traffic_quota ?: -1,
                            $website->active,
                            $website->ssl_enabled,
                            $website->domain
                        ]);
                        
                        $this->stats['websites']['updated']++;
                    } else {
                        // Insert
                        $stmt = $conn->prepare("
                            INSERT INTO websites 
                            (domain, ip_address, system_user, system_group, document_root, 
                             hd_quota, traffic_quota, active, ssl_enabled, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        
                        $stmt->execute([
                            $website->domain,
                            $ipAddress,
                            $systemUser,
                            $systemGroup,
                            $documentRoot,
                            $website->hd_quota ?: -1,
                            $website->traffic_quota ?: -1,
                            $website->active,
                            $website->ssl_enabled
                        ]);
                        
                        $this->stats['websites']['added']++;
                    }
                    
                } catch (Exception $e) {
                    $this->output("   ⚠️  Fehler bei Website {$website->domain}: " . $e->getMessage() . "\n");
                    $this->stats['websites']['errors']++;
                }
            }
            
            $this->output("   ✅ Websites synchronisiert: {$this->stats['websites']['added']} neu, {$this->stats['websites']['updated']} aktualisiert\n\n");
            
        } catch (Exception $e) {
            $this->output("   ❌ Fehler beim Abrufen der Websites: " . $e->getMessage() . "\n\n");
        }
    }
    
    /**
     * Synchronisiert ISPConfig Datenbanken
     */
    private function syncISPConfigDatabases() {
        $this->output("🗄️  Synchronisiere ISPConfig Datenbanken...\n");
        
        try {
            $databases = $this->serviceManager->getISPConfigDatabases();
            $conn = $this->db->getConnection();
            
            foreach ($databases as $database) {
                try {
                    // Standardwerte für fehlende Felder
                    $dbUser = $database->database_user ?: $database->database_name; // Verwende DB-Name als User wenn leer
                    
                    // Prüfen ob Datenbank bereits existiert
                    $stmt = $conn->prepare("SELECT id FROM sm_databases WHERE database_name = ?");
                    $stmt->execute([$database->database_name]);
                    $exists = $stmt->fetch();
                    
                    if ($exists) {
                        // Update
                        $stmt = $conn->prepare("
                            UPDATE sm_databases SET 
                                database_user = ?, database_type = ?, server_id = ?, 
                                charset = ?, remote_access = ?, active = ?, updated_at = NOW()
                            WHERE database_name = ?
                        ");
                        
                        $stmt->execute([
                            $dbUser,
                            $database->database_type ?: 'mysql',
                            $database->server_id ?: 1,
                            $database->charset ?: 'utf8',
                            $database->remote_access ?: 'n',
                            $database->active,
                            $database->database_name
                        ]);
                        
                        $this->stats['databases']['updated']++;
                    } else {
                        // Insert
                        $stmt = $conn->prepare("
                            INSERT INTO sm_databases 
                            (database_name, database_user, database_type, server_id, 
                             charset, remote_access, active, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        
                        $stmt->execute([
                            $database->database_name,
                            $dbUser,
                            $database->database_type ?: 'mysql',
                            $database->server_id ?: 1,
                            $database->charset ?: 'utf8',
                            $database->remote_access ?: 'n',
                            $database->active
                        ]);
                        
                        $this->stats['databases']['added']++;
                    }
                    
                } catch (Exception $e) {
                    $this->output("   ⚠️  Fehler bei Datenbank {$database->database_name}: " . $e->getMessage() . "\n");
                    $this->stats['databases']['errors']++;
                }
            }
            
            $this->output("   ✅ Datenbanken synchronisiert: {$this->stats['databases']['added']} neu, {$this->stats['databases']['updated']} aktualisiert\n\n");
            
        } catch (Exception $e) {
            $this->output("   ❌ Fehler beim Abrufen der Datenbanken: " . $e->getMessage() . "\n\n");
        }
    }
    
    /**
     * Synchronisiert ISPConfig E-Mail Accounts
     */
    private function syncISPConfigEmails() {
        $this->output("📧 Synchronisiere ISPConfig E-Mail Accounts...\n");
        
        try {
            $emails = $this->serviceManager->getISPConfigEmails();
            $conn = $this->db->getConnection();
            
            foreach ($emails as $email) {
                try {
                    // Domain aus E-Mail extrahieren
                    $emailParts = explode('@', $email->email);
                    $domain = isset($emailParts[1]) ? $emailParts[1] : '';
                    
                    // Prüfen ob E-Mail bereits existiert
                    $stmt = $conn->prepare("SELECT id FROM email_accounts WHERE email_address = ?");
                    $stmt->execute([$email->email]);
                    $exists = $stmt->fetch();
                    
                    if ($exists) {
                        // Update
                        $stmt = $conn->prepare("
                            UPDATE email_accounts SET 
                                login_name = ?, full_name = ?, domain = ?, 
                                quota_mb = ?, active = ?, autoresponder = ?, 
                                forward_to = ?, updated_at = NOW()
                            WHERE email_address = ?
                        ");
                        
                        $stmt->execute([
                            $email->login,
                            $email->name,
                            $domain,
                            $email->quota,
                            $email->active,
                            $email->autoresponder,
                            $email->forward_to,
                            $email->email
                        ]);
                        
                        $this->stats['emails']['updated']++;
                    } else {
                        // Insert
                        $stmt = $conn->prepare("
                            INSERT INTO email_accounts 
                            (email_address, login_name, full_name, domain, quota_mb, 
                             active, autoresponder, forward_to, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        
                        $stmt->execute([
                            $email->email,
                            $email->login,
                            $email->name,
                            $domain,
                            $email->quota,
                            $email->active,
                            $email->autoresponder,
                            $email->forward_to
                        ]);
                        
                        $this->stats['emails']['added']++;
                    }
                    
                } catch (Exception $e) {
                    $this->output("   ⚠️  Fehler bei E-Mail {$email->email}: " . $e->getMessage() . "\n");
                    $this->stats['emails']['errors']++;
                }
            }
            
            $this->output("   ✅ E-Mail Accounts synchronisiert: {$this->stats['emails']['added']} neu, {$this->stats['emails']['updated']} aktualisiert\n\n");
            
        } catch (Exception $e) {
            $this->output("   ❌ Fehler beim Abrufen der E-Mail Accounts: " . $e->getMessage() . "\n\n");
        }
    }
    
    /**
     * Synchronisiert OVH Domains
     */
    private function syncOVHDomains() {
        $this->output("🔗 Synchronisiere OVH Domains...\n");
        
        try {
            $domains = $this->serviceManager->getOVHDomains();
            $conn = $this->db->getConnection();
            
            foreach ($domains as $domain) {
                try {
                    // Status-Mapping von OVH zu unserer DB
                    $statusMap = [
                        'ok' => 'active',
                        'alertExpiration' => 'active',
                        'expired' => 'expired',
                        'outOfZone' => 'suspended',
                        'toCreate' => 'pending'
                    ];
                    
                    $dbStatus = isset($statusMap[$domain->state]) ? $statusMap[$domain->state] : 'active';
                    
                    // Datum-Formatierung für MySQL
                    $expirationDate = null;
                    if ($domain->expiration) {
                        $expirationDate = date('Y-m-d', strtotime($domain->expiration));
                    }
                    
                    // Prüfen ob Domain bereits existiert
                    $stmt = $conn->prepare("SELECT id FROM domains WHERE domain_name = ?");
                    $stmt->execute([$domain->domain]);
                    $exists = $stmt->fetch();
                    
                    if ($exists) {
                        // Update
                        $stmt = $conn->prepare("
                            UPDATE domains SET 
                                registrar = ?, expiration_date = ?, auto_renew = ?, 
                                nameservers = ?, status = ?, updated_at = NOW()
                            WHERE domain_name = ?
                        ");
                        
                        $stmt->execute([
                            $domain->registrar,
                            $expirationDate,
                            $domain->autoRenew ? 'y' : 'n',
                            json_encode($domain->nameServers),
                            $dbStatus,
                            $domain->domain
                        ]);
                        
                        $this->stats['domains']['updated']++;
                    } else {
                        // Insert
                        $stmt = $conn->prepare("
                            INSERT INTO domains 
                            (domain_name, registrar, expiration_date, auto_renew, 
                             nameservers, status, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, NOW())
                        ");
                        
                        $stmt->execute([
                            $domain->domain,
                            $domain->registrar,
                            $expirationDate,
                            $domain->autoRenew ? 'y' : 'n',
                            json_encode($domain->nameServers),
                            $dbStatus
                        ]);
                        
                        $this->stats['domains']['added']++;
                    }
                    
                } catch (Exception $e) {
                    $this->output("   ⚠️  Fehler bei Domain {$domain->domain}: " . $e->getMessage() . "\n");
                    $this->stats['domains']['errors']++;
                }
            }
            
            $this->output("   ✅ Domains synchronisiert: {$this->stats['domains']['added']} neu, {$this->stats['domains']['updated']} aktualisiert\n\n");
            
        } catch (Exception $e) {
            $this->output("   ❌ Fehler beim Abrufen der Domains: " . $e->getMessage() . "\n\n");
        }
    }
    
    /**
     * Zeigt eine Zusammenfassung des Updates
     */
    private function showSummary($duration) {
        $this->output(str_repeat("=", 60) . "\n");
        $this->output("📊 UPDATE ZUSAMMENFASSUNG\n");
        $this->output(str_repeat("=", 60) . "\n\n");
        
        $totalAdded = 0;
        $totalUpdated = 0;
        $totalErrors = 0;
        
        foreach ($this->stats as $type => $stats) {
            if ($type === 'credentials') {
                $this->output(sprintf("API-Credentials: %d aktualisiert, %d Fehler\n", 
                    $stats['updated'], $stats['errors']));
            } else {
                $this->output(sprintf("%-12s: %3d neu, %3d aktualisiert, %3d Fehler\n", 
                    ucfirst($type), $stats['added'], $stats['updated'], $stats['errors']));
                
                $totalAdded += $stats['added'];
                $totalUpdated += $stats['updated'];
                $totalErrors += $stats['errors'];
            }
        }
        
        $this->output("\n" . str_repeat("-", 60) . "\n");
        $this->output(sprintf("GESAMT      : %3d neu, %3d aktualisiert, %3d Fehler\n", 
            $totalAdded, $totalUpdated, $totalErrors));
        $this->output(str_repeat("=", 60) . "\n\n");
        
        $this->output("⏱️  Ausführungszeit: {$duration} Sekunden\n");
        
        if ($totalErrors > 0) {
            $this->output("⚠️  Es sind Fehler aufgetreten. Bitte Logs überprüfen.\n");
        } else {
            $this->output("✅ Update erfolgreich abgeschlossen!\n");
        }
        
        // Activity Log
        $this->db->logAction(
            'Database Update',
            sprintf('Sync completed: %d added, %d updated, %d errors in %.2fs', 
                $totalAdded, $totalUpdated, $totalErrors, $duration),
            $totalErrors > 0 ? 'error' : 'success'
        );
    }
    
    /**
     * Hilfsfunktion für Ausgabe (Web oder CLI)
     */
    private function output($text) {
        if (php_sapi_name() === 'cli') {
            echo $text;
        } else {
            echo nl2br(htmlspecialchars($text));
        }
    }
    
    /**
     * Verschlüsselung für sensitive Daten
     */
    private function encrypt($data, $key) {
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Entschlüsselung für sensitive Daten
     */
    private function decrypt($data, $key) {
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
    
    /**
     * Generiert/Lädt den Verschlüsselungsschlüssel
     */
    private function getEncryptionKey() {
        // In Produktion sollte dieser Schlüssel sicher gespeichert werden
        // z.B. in einer Umgebungsvariable oder separaten Konfigurationsdatei
        $keyFile = __DIR__ . '/.encryption_key';
        
        if (file_exists($keyFile)) {
            return file_get_contents($keyFile);
        } else {
            $key = bin2hex(openssl_random_pseudo_bytes(32));
            file_put_contents($keyFile, $key);
            chmod($keyFile, 0600);
            return $key;
        }
    }
}

// =============================================================================
// AUSFÜHRUNG
// =============================================================================

// Web-Interface
if (php_sapi_name() !== 'cli') {
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Datenbank Update - Server Management Interface</title>
        <link rel="stylesheet" type="text/css" href="assets/main.css">
        <style>
            .update-container {
                max-width: 900px;
                margin: 50px auto;
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }
            .update-output {
                background: #1a1a1a;
                color: #00ff00;
                padding: 20px;
                border-radius: 8px;
                font-family: 'Courier New', monospace;
                font-size: 13px;
                line-height: 1.5;
                max-height: 600px;
                overflow-y: auto;
                margin: 20px 0;
            }
            .controls {
                display: flex;
                gap: 15px;
                margin-bottom: 20px;
            }
            .warning {
                background: #fef3cd;
                border: 1px solid #f59e0b;
                border-radius: 8px;
                padding: 15px;
                margin-bottom: 20px;
                color: #92400e;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="update-container">
                <h1>🔄 Datenbank Update</h1>
                
                <div class="warning">
                    <strong>⚠️ Wichtiger Hinweis:</strong> Dieses Script synchronisiert alle Daten aus den 
                    API-Schnittstellen mit der lokalen Datenbank. Der Vorgang kann je nach Datenmenge 
                    einige Minuten dauern.
                </div>
                
                <div class="controls">
                    <button class="btn btn-success" onclick="startUpdate()">
                        🚀 Update starten
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        ← Zurück zum Dashboard
                    </a>
                </div>
                
                <div id="updateOutput" class="update-output" style="display: none;">
                    Warte auf Start...
                </div>
            </div>
        </div>
        
        <script>
            async function startUpdate() {
                const output = document.getElementById('updateOutput');
                output.style.display = 'block';
                output.innerHTML = '🔄 Update läuft...\n\n';
                
                // Disable button
                document.querySelector('.btn-success').disabled = true;
                
                try {
                    const response = await fetch('update.php?ajax=1', {
                        method: 'POST'
                    });
                    
                    const text = await response.text();
                    output.innerHTML = text;
                    
                    // Auto-scroll to bottom
                    output.scrollTop = output.scrollHeight;
                    
                } catch (error) {
                    output.innerHTML += '\n❌ Fehler: ' + error.message;
                }
                
                // Re-enable button
                document.querySelector('.btn-success').disabled = false;
            }
        </script>
    </body>
    </html>
    <?php
    
    // AJAX Request verarbeiten
    if (isset($_GET['ajax'])) {
        ob_start();
        $updater = new DatabaseUpdater();
        $updater->runFullUpdate();
        $output = ob_get_clean();
        echo $output;
    }
    
} else {
    // CLI-Ausführung
    $updater = new DatabaseUpdater();
    $updater->runFullUpdate();
}
?>