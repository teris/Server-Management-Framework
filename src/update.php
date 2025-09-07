<?php
/**
 * Server Management Framework
 * 
 * @author Teris
 * @version 3.1.2
 */
/**
 * Update Script - Synchronisiert API-Daten mit der lokalen Datenbank
 * Speichert auch die API-Credentials aus config.inc.php in der Datenbank
 */

require_once '../framework.php';
require_once 'auth_handler.php';
require_once 'sys.conf.php';

// Nur Admins dürfen dieses Script ausführen
if (php_sapi_name() !== 'cli') {
    requireLogin();
    if (!SessionManager::isAdmin()) {
        die(t('access_denied') . '. ' . t('admin_rights_required') . '.');
    }
}

// Hilfsfunktionen für sichere Werte
function safeString($value, $default = '') {
    return ($value === null || $value === '') ? $default : $value;
}
function safeInt($value, $default = 0, $min = 0, $max = 999999) {
    if (!is_numeric($value)) return $default;
    $value = (int)$value;
    if ($value < $min) return $min;
    if ($value > $max) return $max;
    return $value;
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
    private $debugSql = true; // NEU: Debug-Flag für SQL-Ausgabe

    public function setDebugSql($debug) {
        $this->debugSql = (bool)$debug;
    }

    // Hilfsfunktion für SQL-Debug-Ausgabe
    private function debugSql($sql, $params) {
        if ($this->debugSql) {
            $this->output("\n--- SQL-DEBUG ---\n");
            $this->output("SQL: " . $sql . "\n");
            $this->output("Parameter: " . var_export($params, true) . "\n");
            $this->output("--- ENDE SQL-DEBUG ---\n\n");
        }
    }
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->serviceManager = new ServiceManager();
    }
    
    /**
     * Führt das komplette Update aus
     */
    public function runFullUpdate() {
        $this->output("🚀 " . t('system_update') . "...\n");
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
        // NEU: Ressourcen aus AdminCore synchronisieren
        $this->syncResourcesFromAdminCore();
        
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
        
        $sql = "
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
        ";
        
        $passwordEncrypted = !empty($data['password']) ? $this->encrypt($data['password'], $encryptionKey) : null;
        $apiKeyEncrypted = !empty($data['api_key']) ? $this->encrypt($data['api_key'], $encryptionKey) : null;
        
        $params = [
            $serviceName,
            $data['endpoint'],
            $data['username'],
            $passwordEncrypted,
            $apiKeyEncrypted,
            $data['additional_config']
        ];
        $this->debugSql($sql, $params);
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        
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
                    $sql = "SELECT id FROM vms WHERE vm_id = ?";
                    $params = [$vm->vmid];
                    $this->debugSql($sql, $params);
                    $stmt = $conn->prepare($sql);
                    $stmt->execute($params);
                    $exists = $stmt->fetch();
                    
                    if ($exists) {
                        // Update
                        $sql = "
                            UPDATE vms SET 
                                name = ?, node = ?, status = ?, cores = ?, 
                                memory = ?, disk_size = ?, ip_address = ?, 
                                mac_address = ?, updated_at = NOW()
                            WHERE vm_id = ?
                        ";
                        $params = [
                            $vm->name,
                            $vm->node,
                            $vm->status,
                            $vm->cores,
                            $memoryInMB,
                            $diskInGB,
                            $vm->ip_address,
                            $vm->mac_address,
                            $vm->vmid
                        ];
                        $this->debugSql($sql, $params);
                        $stmt = $conn->prepare($sql);
                        $stmt->execute($params);
                        
                        $this->stats['vms']['updated']++;
                    } else {
                        // Insert
                        $sql = "
                            INSERT INTO vms 
                            (vm_id, name, node, status, cores, memory, disk_size, ip_address, mac_address, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ";
                        $params = [
                            $vm->vmid,
                            $vm->name,
                            $vm->node,
                            $vm->status,
                            $vm->cores,
                            $memoryInMB,
                            $diskInGB,
                            $vm->ip_address,
                            $vm->mac_address
                        ];
                        $this->debugSql($sql, $params);
                        $stmt = $conn->prepare($sql);
                        $stmt->execute($params);
                        
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
                    $sql = "SELECT id FROM websites WHERE domain = ?";
                    $params = [$website->domain];
                    $this->debugSql($sql, $params);
                    $stmt = $conn->prepare($sql);
                    $stmt->execute($params);
                    $exists = $stmt->fetch();
                    
                    if ($exists) {
                        // Update
                        $sql = "
                            UPDATE websites SET 
                                ip_address = ?, system_user = ?, system_group = ?, 
                                document_root = ?, hd_quota = ?, traffic_quota = ?, 
                                active = ?, ssl_enabled = ?, updated_at = NOW()
                            WHERE domain = ?
                        ";
                        $params = [
                            $ipAddress,
                            $systemUser,
                            $systemGroup,
                            $documentRoot,
                            $website->hd_quota ?: -1,
                            $website->traffic_quota ?: -1,
                            $website->active,
                            $website->ssl_enabled,
                            $website->domain
                        ];
                        $this->debugSql($sql, $params);
                        $stmt = $conn->prepare($sql);
                        $stmt->execute($params);
                        
                        $this->stats['websites']['updated']++;
                    } else {
                        // Insert
                        $sql = "
                            INSERT INTO websites 
                            (domain, ip_address, system_user, system_group, document_root, 
                             hd_quota, traffic_quota, active, ssl_enabled, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ";
                        $params = [
                            $website->domain,
                            $ipAddress,
                            $systemUser,
                            $systemGroup,
                            $documentRoot,
                            $website->hd_quota ?: -1,
                            $website->traffic_quota ?: -1,
                            $website->active,
                            $website->ssl_enabled
                        ];
                        $this->debugSql($sql, $params);
                        $stmt = $conn->prepare($sql);
                        $stmt->execute($params);
                        
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
                    $sql = "SELECT id FROM sm_databases WHERE database_name = ?";
                    $params = [$database->database_name];
                    $this->debugSql($sql, $params);
                    $stmt = $conn->prepare($sql);
                    $stmt->execute($params);
                    $exists = $stmt->fetch();
                    
                    if ($exists) {
                        // Update
                        $sql = "
                            UPDATE sm_databases SET 
                                database_user = ?, database_type = ?, server_id = ?, 
                                charset = ?, remote_access = ?, active = ?, updated_at = NOW()
                            WHERE database_name = ?
                        ";
                        $params = [
                            $dbUser,
                            $database->database_type ?: 'mysql',
                            $database->server_id ?: 1,
                            $database->charset ?: 'utf8',
                            $database->remote_access ?: 'n',
                            $database->active,
                            $database->database_name
                        ];
                        $this->debugSql($sql, $params);
                        $stmt = $conn->prepare($sql);
                        $stmt->execute($params);
                        
                        $this->stats['databases']['updated']++;
                    } else {
                        // Insert
                        $sql = "
                            INSERT INTO sm_databases 
                            (database_name, database_user, database_type, server_id, 
                             charset, remote_access, active, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                        ";
                        $params = [
                            $database->database_name,
                            $dbUser,
                            $database->database_type ?: 'mysql',
                            $database->server_id ?: 1,
                            $database->charset ?: 'utf8',
                            $database->remote_access ?: 'n',
                            $database->active
                        ];
                        $this->debugSql($sql, $params);
                        $stmt = $conn->prepare($sql);
                        $stmt->execute($params);
                        
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
					$emailParts = explode('@', $email->email ?: '');
					$domain = isset($emailParts[1]) ? $emailParts[1] : '';
					
					// *** QUOTA FIX - Sichere Konvertierung ***
					$quotaMB = $this->convertQuotaToMB($email->quota);
					
					// Debug-Ausgabe für problematische Werte
					if ($quotaMB === null || $quotaMB < 0 || $quotaMB > 999999) {
						$this->output("   🔍 Quota-Debug für {$email->email}: Original='{$email->quota}', Konvertiert='{$quotaMB}'\n");
					}
					
					// Weitere Feld-Validierungen
					$loginName = $this->sanitizeString($email->login, 50);
					$fullName = $this->sanitizeString($email->name, 100);
					$forwardTo = $this->sanitizeString($email->forward_to, 255);
					
					// Aktivitäts-Status normalisieren
					$activeStatus = $this->normalizeActiveStatus($email->active);
					
					// Prüfen ob E-Mail bereits existiert
					$sql = "SELECT id FROM email_accounts WHERE email_address = ?";
					$params = [$email->email];
					$this->debugSql($sql, $params);
					$stmt = $conn->prepare($sql);
					$stmt->execute($params);
					$exists = $stmt->fetch();
					
					if ($exists) {
						// Update
						$sql = "
							UPDATE email_accounts SET 
								login_name = ?, full_name = ?, domain = ?, 
								quota_mb = ?, active = ?, autoresponder = ?, 
								forward_to = ?, updated_at = NOW()
							WHERE email_address = ?
						";
						$params = [
							$loginName,
							$fullName,
							$domain,
							$quotaMB,
							$activeStatus,
							$email->autoresponder ?: 'n',
							$forwardTo,
							$email->email
						];
						$this->debugSql($sql, $params);
						$stmt = $conn->prepare($sql);
						$stmt->execute($params);
						
						$this->stats['emails']['updated']++;
					} else {
						// Insert
						$sql = "
							INSERT INTO email_accounts 
							(email_address, login_name, full_name, domain, quota_mb, 
							 active, autoresponder, forward_to, created_at) 
							VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
						";
						$params = [
							$email->email,
							$loginName,
							$fullName,
							$domain,
							$quotaMB,
							$activeStatus,
							$email->autoresponder ?: 'n',
							$forwardTo
						];
						$this->debugSql($sql, $params);
						$stmt = $conn->prepare($sql);
						$stmt->execute($params);
						
						$this->stats['emails']['added']++;
					}
					
				} catch (Exception $e) {
					$this->output("   ⚠️  Fehler bei E-Mail {$email->email}: " . $e->getMessage() . "\n");
					$this->output("       Quota-Rohdaten: " . var_export($email->quota, true) . "\n");
					$this->stats['emails']['errors']++;
				}
			}
        
        $this->output("   ✅ E-Mail Accounts synchronisiert: {$this->stats['emails']['added']} neu, {$this->stats['emails']['updated']} aktualisiert\n\n");
        
		} catch (Exception $e) {
			$this->output("   ❌ Fehler beim Abrufen der E-Mail Accounts: " . $e->getMessage() . "\n\n");
		}
	}
	
	private function convertQuotaToMB($quota) {
		// Null oder leer
		if ($quota === null || $quota === '' || $quota === false) {
			return 1000; // Standard 1GB
		}
		
		// Bereits numerisch und im erlaubten Bereich
		if (is_numeric($quota)) {
			$quotaInt = (int)$quota;
			
			// Negative Werte = Unbegrenzt (setze auf 0)
			if ($quotaInt < 0) {
				return 0;
			}
			
			// Sehr große Werte begrenzen (Max 999GB)
			if ($quotaInt > 999999) {
				return 999999;
			}
			
			return $quotaInt;
		}
		
		// String-Werte mit Einheiten (z.B. "1000M", "2G", "500MB")
		if (is_string($quota)) {
			$quota = trim(strtoupper($quota));
			
			// Entferne bekannte Einheiten und konvertiere
			if (preg_match('/^(\d+(?:\.\d+)?)\s*(B|KB|MB|GB|TB|K|M|G|T)?$/', $quota, $matches)) {
				$value = floatval($matches[1]);
				$unit = isset($matches[2]) ? $matches[2] : 'MB';
				
				switch ($unit) {
					case 'B':
						return max(1, (int)($value / (1024 * 1024))); // Bytes zu MB
					case 'KB':
					case 'K':
						return max(1, (int)($value / 1024)); // KB zu MB
					case 'MB':
					case 'M':
						return (int)$value; // Bereits in MB
					case 'GB':
					case 'G':
						return min(999999, (int)($value * 1024)); // GB zu MB
					case 'TB':
					case 'T':
						return 999999; // TB ist zu groß, setze Maximum
					default:
						return (int)$value; // Fallback: als MB behandeln
				}
			}
			
			// Versuche nur den numerischen Teil zu extrahieren
			if (preg_match('/(\d+)/', $quota, $matches)) {
				$numericValue = (int)$matches[1];
				return min(999999, max(1, $numericValue));
			}
		}
		
		// Fallback für unbekannte Formate
		return 1000; // Standard 1GB
	}

    private function normalizeActiveStatus($active) {
		if ($active === 'y' || $active === '1' || $active === 1 || $active === true || $active === 'yes') {
			return 'y';
		} elseif ($active === 'n' || $active === '0' || $active === 0 || $active === false || $active === 'no') {
			return 'n';
		}
		
		// Fallback: wenn E-Mail-Daten vorhanden sind, ist Account vermutlich aktiv
		return 'y';
	}

	private function sanitizeString($value, $maxLength = 255) {
		if ($value === null || $value === false) {
			return '';
		}
		
		$cleaned = trim((string)$value);
		
		// Kürze zu lange Strings
		if (strlen($cleaned) > $maxLength) {
			$cleaned = substr($cleaned, 0, $maxLength - 3) . '...';
		}
		
		return $cleaned;
	}	

	public function analyzeEmailQuotas() {
		$this->output("🔍 Analysiere E-Mail Quota-Werte...\n\n");
		
		try {
			$emails = $this->serviceManager->getISPConfigEmails();
			
			$quotaAnalysis = [
				'total_emails' => count($emails),
				'quota_formats' => [],
				'quota_values' => [],
				'problematic_quotas' => [],
				'sample_emails' => []
			];
			
			foreach ($emails as $index => $email) {
				$originalQuota = $email->quota;
				$convertedQuota = $this->convertQuotaToMB($originalQuota);
				
				// Sammle Quota-Formate
				$quotaType = gettype($originalQuota);
				$quotaFormatKey = $quotaType . ': ' . $originalQuota;
				
				if (!isset($quotaAnalysis['quota_formats'][$quotaFormatKey])) {
					$quotaAnalysis['quota_formats'][$quotaFormatKey] = 0;
				}
				$quotaAnalysis['quota_formats'][$quotaFormatKey]++;
				
				// Sammle konvertierte Werte
				if (!isset($quotaAnalysis['quota_values'][$convertedQuota])) {
					$quotaAnalysis['quota_values'][$convertedQuota] = 0;
				}
				$quotaAnalysis['quota_values'][$convertedQuota]++;
				
				// Identifiziere problematische Quotas
				if ($convertedQuota === null || $convertedQuota < 0 || $convertedQuota > 999999) {
					$quotaAnalysis['problematic_quotas'][] = [
						'email' => $email->email,
						'original' => $originalQuota,
						'converted' => $convertedQuota,
						'type' => $quotaType
					];
				}
				
				// Sammle erste 5 E-Mails als Beispiele
				if ($index < 5) {
					$quotaAnalysis['sample_emails'][] = [
						'email' => $email->email,
						'original_quota' => $originalQuota,
						'converted_quota' => $convertedQuota,
						'quota_type' => $quotaType
					];
				}
			}
			
			// Ausgabe der Analyse
			$this->output("📊 QUOTA-ANALYSE ERGEBNISSE:\n");
			$this->output("Gesamt E-Mails: {$quotaAnalysis['total_emails']}\n\n");
			
			$this->output("🔢 Gefundene Quota-Formate:\n");
			foreach ($quotaAnalysis['quota_formats'] as $format => $count) {
				$this->output("  - {$format} ({$count}x)\n");
			}
			
			$this->output("\n📏 Konvertierte Quota-Werte:\n");
			ksort($quotaAnalysis['quota_values']);
			foreach ($quotaAnalysis['quota_values'] as $value => $count) {
				$this->output("  - {$value} MB ({$count}x)\n");
			}
			
			if (!empty($quotaAnalysis['problematic_quotas'])) {
				$this->output("\n⚠️  PROBLEMATISCHE QUOTA-WERTE:\n");
				foreach ($quotaAnalysis['problematic_quotas'] as $problem) {
					$this->output("  - {$problem['email']}: '{$problem['original']}' → '{$problem['converted']}' ({$problem['type']})\n");
				}
			} else {
				$this->output("\n✅ Keine problematischen Quota-Werte gefunden!\n");
			}
			
			$this->output("\n📧 BEISPIEL E-MAILS:\n");
			foreach ($quotaAnalysis['sample_emails'] as $sample) {
				$this->output("  - {$sample['email']}: '{$sample['original_quota']}' → {$sample['converted_quota']} MB\n");
			}
			
			return $quotaAnalysis;
			
		} catch (Exception $e) {
			$this->output("❌ Quota-Analyse fehlgeschlagen: " . $e->getMessage() . "\n");
			return false;
		}
	}

	public function checkDatabaseSchema() {
		$this->output("🗄️  Prüfe Datenbank-Schema...\n\n");
		
		try {
			$conn = $this->db->getConnection();
			
			// Prüfe email_accounts Tabelle
			$sql = "DESCRIBE email_accounts";
			$params = [];
			$this->debugSql($sql, $params);
			$stmt = $conn->prepare($sql);
			$stmt->execute($params);
			$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
			$this->output("📋 email_accounts Tabellen-Schema:\n");
			foreach ($columns as $column) {
				$this->output("  - {$column['Field']}: {$column['Type']} (Null: {$column['Null']}, Default: {$column['Default']})\n");
				
				// Spezielle Prüfung für quota_mb Feld
				if ($column['Field'] === 'quota_mb') {
					$this->output("    ⚠️  QUOTA-FELD DETAILS:\n");
					$this->output("        Typ: {$column['Type']}\n");
					
					// Bestimme den erlaubten Wertebereich
					if (stripos($column['Type'], 'tinyint') !== false) {
						$this->output("        Bereich: 0 - 255 (zu klein für Quota-Werte!)\n");
						$this->output("        🔧 EMPFEHLUNG: Ändere zu INT oder MEDIUMINT\n");
					} elseif (stripos($column['Type'], 'smallint') !== false) {
						$this->output("        Bereich: 0 - 65535 (kann für große Quotas zu klein sein)\n");
					} elseif (stripos($column['Type'], 'mediumint') !== false) {
						$this->output("        Bereich: 0 - 16777215 (gut für Quota-Werte)\n");
					} elseif (stripos($column['Type'], 'int') !== false) {
						$this->output("        Bereich: 0 - 4294967295 (perfekt für Quota-Werte)\n");
					}
				}
			}
			
			return true;
			
		} catch (Exception $e) {
			$this->output("❌ Schema-Prüfung fehlgeschlagen: " . $e->getMessage() . "\n");
			return false;
		}
	}

	public function fixQuotaColumn() {
		$this->output("🔧 Repariere quota_mb Spalte...\n");
		
		try {
			$conn = $this->db->getConnection();
			
			// Ändere quota_mb zu einem größeren Datentyp
			$sql = "ALTER TABLE email_accounts MODIFY COLUMN quota_mb INT UNSIGNED DEFAULT 1000";
			$params = [];
			$this->debugSql($sql, $params);
			$stmt = $conn->prepare($sql);
			$stmt->execute($params);
			
			$this->output("✅ quota_mb Spalte erfolgreich zu INT UNSIGNED geändert\n");
			$this->output("   Neuer Wertebereich: 0 - 4,294,967,295 MB\n\n");
			
			return true;
			
		} catch (Exception $e) {
			$this->output("❌ Spalten-Reparatur fehlgeschlagen: " . $e->getMessage() . "\n");
			return false;
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
                    $sql = "SELECT id FROM domains WHERE domain_name = ?";
                    $params = [$domain->domain];
                    $this->debugSql($sql, $params);
                    $stmt = $conn->prepare($sql);
                    $stmt->execute($params);
                    $exists = $stmt->fetch();
                    
                    if ($exists) {
                        // Update
                        $sql = "
                            UPDATE domains SET 
                                registrar = ?, expiration_date = ?, auto_renew = ?, 
                                nameservers = ?, status = ?, updated_at = NOW()
                            WHERE domain_name = ?
                        ";
                        $params = [
                            $domain->registrar,
                            $expirationDate,
                            $domain->autoRenew ? 'y' : 'n',
                            json_encode($domain->nameServers),
                            $dbStatus,
                            $domain->domain
                        ];
                        $this->debugSql($sql, $params);
                        $stmt = $conn->prepare($sql);
                        $stmt->execute($params);
                        
                        $this->stats['domains']['updated']++;
                    } else {
                        // Insert
                        $sql = "
                            INSERT INTO domains 
                            (domain_name, registrar, expiration_date, auto_renew, 
                             nameservers, status, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, NOW())
                        ";
                        $params = [
                            $domain->domain,
                            $domain->registrar,
                            $expirationDate,
                            $domain->autoRenew ? 'y' : 'n',
                            json_encode($domain->nameServers),
                            $dbStatus
                        ];
                        $this->debugSql($sql, $params);
                        $stmt = $conn->prepare($sql);
                        $stmt->execute($params);
                        
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

    private function syncResourcesFromAdminCore() {
        $this->output("🔄 Synchronisiere Ressourcen aus AdminCore...\n");
        require_once 'core/AdminCore.php';
        $adminCore = new AdminCore();
        $conn = $this->db->getConnection();
        // 1. VMs
        $vms = $adminCore->getResources('vms');
        foreach ($vms as $vm) {
            // Patch: Speicherwerte umrechnen (Bytes → MB)
            $memory = $vm['memory'] ?? null;
            if ($memory > 100000) { // vermutlich Bytes
                $memory = round($memory / 1024 / 1024); // MB
            }
            $disk = $vm['disk'] ?? $vm['disk_size'] ?? null;
            if ($disk > 100000000) { // vermutlich Bytes
                $disk = round($disk / 1024 / 1024 / 1024); // GB
            }
            $ip_address = $vm['ip_address'] ?? '';
            if ($ip_address === null) $ip_address = '';
            $stmt = $conn->prepare("SELECT id FROM vms WHERE vm_id = ?");
            $this->debugSql("SELECT id FROM vms WHERE vm_id = ?", [$vm['vmid'] ?? $vm['id'] ?? null]);
            $stmt->execute([$vm['vmid'] ?? $vm['id'] ?? null]);
            $exists = $stmt->fetch();
            if ($exists) {
                $sql = "UPDATE vms SET name=?, node=?, status=?, cores=?, memory=?, disk_size=?, ip_address=?, mac_address=?, updated_at=NOW() WHERE vm_id=?";
                $params = [
                    $vm['name'] ?? null,
                    $vm['node'] ?? null,
                    $vm['status'] ?? null,
                    $vm['cores'] ?? null,
                    $memory,
                    $disk,
                    $ip_address,
                    $vm['mac_address'] ?? null,
                    $vm['vmid'] ?? $vm['id'] ?? null
                ];
                $this->debugSql($sql, $params);
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
            } else {
                $sql = "INSERT INTO vms (vm_id, name, node, status, cores, memory, disk_size, ip_address, mac_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $params = [
                    $vm['vmid'] ?? $vm['id'] ?? null,
                    $vm['name'] ?? null,
                    $vm['node'] ?? null,
                    $vm['status'] ?? null,
                    $vm['cores'] ?? null,
                    $memory,
                    $disk,
                    $ip_address,
                    $vm['mac_address'] ?? null
                ];
                $this->debugSql($sql, $params);
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
            }
        }
        // 2. Websites
        $websites = $adminCore->getResources('websites');
        foreach ($websites as $site) {
            $ip_address = $site['ip_address'] ?? '';
            if ($ip_address === null) $ip_address = '';
            $system_user = $site['system_user'] ?? '';
            if ($system_user === null) $system_user = '';
            $system_group = $site['system_group'] ?? '';
            if ($system_group === null) $system_group = '';
            $document_root = $site['document_root'] ?? '';
            if ($document_root === null) $document_root = '';
            $domain = $site['domain'] ?? $site['name'] ?? '';
            if ($domain === null) $domain = '';
            $stmt = $conn->prepare("SELECT id FROM websites WHERE domain = ?");
            $this->debugSql("SELECT id FROM websites WHERE domain = ?", [$domain]);
            $stmt->execute([$domain]);
            $exists = $stmt->fetch();
            if ($exists) {
                $sql = "UPDATE websites SET ip_address=?, system_user=?, system_group=?, document_root=?, hd_quota=?, traffic_quota=?, active=?, ssl_enabled=?, updated_at=NOW() WHERE domain=?";
                $params = [
                    $ip_address,
                    $system_user,
                    $system_group,
                    $document_root,
                    $site['hd_quota'] ?? 0,
                    $site['traffic_quota'] ?? 0,
                    $site['active'] ?? 'y',
                    $site['ssl_enabled'] ?? 'n',
                    $domain
                ];
                $this->debugSql($sql, $params);
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
            } else {
                $sql = "INSERT INTO websites (domain, ip_address, system_user, system_group, document_root, hd_quota, traffic_quota, active, ssl_enabled, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $params = [
                    $domain,
                    $ip_address,
                    $system_user,
                    $system_group,
                    $document_root,
                    $site['hd_quota'] ?? 0,
                    $site['traffic_quota'] ?? 0,
                    $site['active'] ?? 'y',
                    $site['ssl_enabled'] ?? 'n'
                ];
                $this->debugSql($sql, $params);
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
            }
        }
        // 3. Datenbanken
        $databases = $adminCore->getResources('databases');
        foreach ($databases as $db) {
            $database_name = $db['database_name'] ?? $db['name'] ?? '';
            if ($database_name === null) $database_name = '';
            $database_user = $db['database_user'] ?? '';
            if ($database_user === null) $database_user = '';
            $database_type = $db['database_type'] ?? 'mysql';
            if ($database_type === null) $database_type = 'mysql';
            $charset = $db['charset'] ?? 'utf8';
            if ($charset === null) $charset = 'utf8';
            $active = $db['active'] ?? 'y';
            if ($active === null) $active = 'y';
            $stmt = $conn->prepare("SELECT id FROM sm_databases WHERE database_name = ?");
            $this->debugSql("SELECT id FROM sm_databases WHERE database_name = ?", [$database_name]);
            $stmt->execute([$database_name]);
            $exists = $stmt->fetch();
            if ($exists) {
                $sql = "UPDATE sm_databases SET database_user=?, database_type=?, server_id=?, charset=?, remote_access=?, active=?, updated_at=NOW() WHERE database_name=?";
                $params = [
                    $database_user,
                    $database_type,
                    $db['server_id'] ?? 1,
                    $charset,
                    $db['remote_access'] ?? 'n',
                    $active,
                    $database_name
                ];
                $this->debugSql($sql, $params);
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
            } else {
                $sql = "INSERT INTO sm_databases (database_name, database_user, database_type, server_id, charset, remote_access, active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                $params = [
                    $database_name,
                    $database_user,
                    $database_type,
                    $db['server_id'] ?? 1,
                    $charset,
                    $db['remote_access'] ?? 'n',
                    $active
                ];
                $this->debugSql($sql, $params);
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
            }
        }
        // 4. E-Mails
        $emails = $adminCore->getResources('emails');
        foreach ($emails as $email) {
            $email_address = $email['email'] ?? '';
            if ($email_address === null) $email_address = '';
            $login_name = $email['login'] ?? '';
            if ($login_name === null) $login_name = '';
            $full_name = $email['name'] ?? '';
            if ($full_name === null) $full_name = '';
            $domain = $email['domain'] ?? '';
            if ($domain === null) $domain = '';
            // Patch: quota_mb sicher konvertieren und begrenzen
            $quota_mb = safeInt($this->convertQuotaToMB($email['quota']), 1000, 0, 999999);
            $active = $email['active'] ?? 'y';
            if ($active === null) $active = 'y';
            $autoresponder = $email['autoresponder'] ?? 'n';
            if ($autoresponder === null) $autoresponder = 'n';
            $forward_to = $email['forward_to'] ?? '';
            if ($forward_to === null) $forward_to = '';
            $stmt = $conn->prepare("SELECT id FROM email_accounts WHERE email_address = ?");
            $this->debugSql("SELECT id FROM email_accounts WHERE email_address = ?", [$email_address]);
            $stmt->execute([$email_address]);
            $exists = $stmt->fetch();
            if ($exists) {
                $sql = "UPDATE email_accounts SET login_name=?, full_name=?, domain=?, quota_mb=?, active=?, autoresponder=?, forward_to=?, updated_at=NOW() WHERE email_address=?";
                $params = [
                    $login_name,
                    $full_name,
                    $domain,
                    $quota_mb,
                    $active,
                    $autoresponder,
                    $forward_to,
                    $email_address
                ];
                $this->debugSql($sql, $params);
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
            } else {
                $sql = "INSERT INTO email_accounts (email_address, login_name, full_name, domain, quota_mb, active, autoresponder, forward_to, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $params = [
                    $email_address,
                    $login_name,
                    $full_name,
                    $domain,
                    $quota_mb,
                    $active,
                    $autoresponder,
                    $forward_to
                ];
                $this->debugSql($sql, $params);
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
            }
        }
        // 5. Domains
        $domains = $adminCore->getResources('domains');
        foreach ($domains as $domain) {
            $domain_name = $domain['domain'] ?? '';
            if ($domain_name === null) $domain_name = '';
            $owner = $domain['owner'] ?? '';
            if ($owner === null) $owner = '';
            $active = $domain['active'] ?? 'y';
            if ($active === null) $active = 'y';
            // Patch: auto_renew sicher auf 'y'/'n' mappen
            $auto_renew = $domain['auto_renew'] ?? 'n';
            if (is_bool($auto_renew)) {
                $auto_renew = $auto_renew ? 'y' : 'n';
            } elseif (is_numeric($auto_renew)) {
                $auto_renew = $auto_renew ? 'y' : 'n';
            } elseif (is_string($auto_renew)) {
                $ar = strtolower($auto_renew);
                if (in_array($ar, ['1', 'true', 'yes', 'y', 'ja'])) {
                    $auto_renew = 'y';
                } else {
                    $auto_renew = 'n';
                }
            } else {
                $auto_renew = 'n';
            }
            // Patch: status auf gültige Werte mappen
            $status = $domain['state'] ?? $domain['status'] ?? '';
            if (is_bool($status)) {
                $status = $status ? 'active' : 'inactive';
            } elseif (is_numeric($status)) {
                $status = $status ? 'active' : 'inactive';
            } elseif (is_string($status)) {
                $s = strtolower($status);
                if (in_array($s, ['active', 'enabled', 'ok', '1', 'true', 'y', 'ja'])) {
                    $status = 'active';
                } elseif (in_array($s, ['inactive', 'disabled', 'error', '0', 'false', 'n', 'nein'])) {
                    $status = 'inactive';
                } else {
                    $status = 'unknown';
                }
            } else {
                $status = 'unknown';
            }
            $stmt = $conn->prepare("SELECT id FROM domains WHERE domain_name = ?");
            $this->debugSql("SELECT id FROM domains WHERE domain_name = ?", [$domain_name]);
            $stmt->execute([$domain_name]);
            $exists = $stmt->fetch();
            if ($exists) {
                $sql = "UPDATE domains SET registrar=?, expiration_date=?, auto_renew=?, nameservers=?, status=?, updated_at=NOW() WHERE domain_name=?";
                $params = [
                    $domain['registrar'] ?? null,
                    $domain['expiration'] ?? null,
                    $auto_renew,
                    json_encode($domain['nameServers'] ?? []),
                    $status,
                    $domain_name
                ];
                $this->debugSql($sql, $params);
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
            } else {
                $sql = "INSERT INTO domains (domain_name, registrar, expiration_date, auto_renew, nameservers, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
                $params = [
                    $domain_name,
                    $domain['registrar'] ?? null,
                    $domain['expiration'] ?? null,
                    $auto_renew,
                    json_encode($domain['nameServers'] ?? []),
                    $status
                ];
                $this->debugSql($sql, $params);
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
            }
        }
        // 6. IPs (optional, je nach Datenstruktur)
        $ips = $adminCore->getResources('ip');
        foreach ($ips as $subnet => $ipEntries) {
            if (!is_array($ipEntries)) continue;
            foreach ($ipEntries as $ipReverse => $details) {
                $reverse = $details['reverse'] ?? null;
                $ttl = $details['ttl'] ?? null;
                $sql = "SELECT id FROM ips WHERE subnet = ? AND ip_reverse = ?";
                $params = [$subnet, $ipReverse];
                $this->debugSql($sql, $params);
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                $exists = $stmt->fetch();
                if ($exists) {
                    $sql = "UPDATE ips SET reverse = ?, ttl = ?, updated_at = NOW() WHERE subnet = ? AND ip_reverse = ?";
                    $params = [$reverse, $ttl, $subnet, $ipReverse];
                    $this->debugSql($sql, $params);
                    $stmt = $conn->prepare($sql);
                    $stmt->execute($params);
                } else {
                    $sql = "INSERT INTO ips (subnet, ip_reverse, reverse, ttl, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())";
                    $params = [$subnet, $ipReverse, $reverse, $ttl];
                    $this->debugSql($sql, $params);
                    $stmt = $conn->prepare($sql);
                    $stmt->execute($params);
                }
            }
        }
        // Hier kannst du nach deinem Datenbankschema für IPs verfahren
        $this->output("   ✅ Ressourcen aus AdminCore synchronisiert\n\n");
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