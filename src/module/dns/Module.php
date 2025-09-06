<?php
/**
 * DNS Module - Serverseitiges Rendering
 * Verwaltung von DNS-Einstellungen für OVH-Domains
 */

require_once dirname(dirname(__FILE__)) . '/ModuleBase.php';

class DnsModule extends ModuleBase {
    
    private $currentDomain = null;
    private $domains = [];
    private $dnsRecords = [];
    private $dnssecStatus = null;
    private $dnssecKeys = [];
    private $zoneContent = '';
    private $actionResult = null;
    
    public function getContent() {
        // Diese Methode wird nur für das initiale Laden verwendet
        // Alle Aktionen werden über AJAX verarbeitet
        
        // Behandle GET-Parameter für Domain-Auswahl
        if (isset($_GET['domain'])) {
            $this->currentDomain = $_GET['domain'];
        }
        
        $translations = $this->tMultiple([
            'module_title', 'domain_selection', 'select_domain', 'dns_records', 'add_record',
            'edit_record', 'delete_record', 'record_type', 'subdomain', 'target', 'ttl',
            'priority', 'refresh_zone', 'export_zone', 'import_zone', 'zone_info',
            'save', 'cancel', 'edit', 'delete', 'create', 'refresh', 'actions', 'status',
            'loading', 'operation_successful', 'operation_failed', 'confirm_delete',
            'validation_failed', 'unknown_action', 'domain_required', 'record_type_required',
            'target_required', 'ttl_required', 'priority_required', 'mx_records',
            'a_records', 'cname_records', 'txt_records', 'ns_records', 'srv_records',
            'caa_records', 'aaaa_records', 'ptr_records', 'soa_record', 'zone_transfer',
            'dnssec_status', 'enable_dnssec', 'disable_dnssec', 'dnssec_keys',
            'add_key', 'delete_key', 'key_type', 'key_algorithm', 'key_size',
            'zone_updated', 'zone_update_failed', 'record_added', 'record_updated',
            'record_deleted', 'record_add_failed', 'record_update_failed', 'record_delete_failed',
            'test_api_connection', 'api_connection_successful', 'api_connection_failed',
            'domains_loaded', 'dns_records_loaded', 'dnssec_enabled', 'dnssec_disabled',
            'zone_exported', 'zone_imported', 'no_domains_available', 'no_records_found',
            'no_dnssec_keys_found', 'weight', 'port', 'key_id', 'algorithm', 'select'
        ]);
        
        // Lade alle Daten
        $this->loadAllData();
        
        // Stelle sicher, dass currentDomain immer einen Wert hat (auch wenn null)
        $currentDomain = $this->currentDomain ?? null;
        
        return $this->render('main', [
            'translations' => $translations,
            'domains' => $this->domains,
            'currentDomain' => $currentDomain,
            'dnsRecords' => $this->dnsRecords,
            'dnssecStatus' => $this->dnssecStatus,
            'dnssecKeys' => $this->dnssecKeys,
            'zoneContent' => $this->zoneContent,
            'actionResult' => null
        ]);
    }
    

    
    /**
     * Lädt alle benötigten Daten
     */
    private function loadAllData() {
        // Lade Domains
        $this->loadDomains();
        
        // Wenn eine Domain ausgewählt ist, lade weitere Daten
        if ($this->currentDomain) {
            $this->loadDNSRecords();
            $this->loadDnssecStatus();
            $this->loadDnssecKeys();
        }
    }
    
    /**
     * Lädt alle verfügbaren Domains
     */
    private function loadDomains() {
        try {
            $serviceManager = new ServiceManager();
            $domains = $serviceManager->getOVHDomains();
            
            if ($domains === false || empty($domains)) {
                $this->domains = [];
                return ['success' => false, 'message' => 'Fehler beim Laden der Domains'];
            }
            
            // Filtere nur die Domain-Namen
            $this->domains = [];
            if (is_array($domains)) {
                foreach ($domains as $domain) {
                    if (is_string($domain)) {
                        $this->domains[] = $domain;
                    } elseif (is_array($domain) && isset($domain['name'])) {
                        $this->domains[] = $domain['name'];
                    } elseif (is_array($domain) && isset($domain['domain'])) {
                        $this->domains[] = $domain['domain'];
                    } elseif (is_object($domain) && isset($domain->name)) {
                        $this->domains[] = $domain->name;
                    } elseif (is_object($domain) && isset($domain->domain)) {
                        $this->domains[] = $domain->domain;
                    } elseif (is_object($domain) && method_exists($domain, 'toArray')) {
                        $domainArray = $domain->toArray();
                        if (isset($domainArray['name'])) {
                            $this->domains[] = $domainArray['name'];
                        } elseif (isset($domainArray['domain'])) {
                            $this->domains[] = $domainArray['domain'];
                        }
                    }
                }
            }
            
            // Nur bei echten Fehlern loggen
            if (empty($this->domains)) {
                error_log("DNS Module: No domains loaded from OVH API");
            }
            
            return ['success' => true, 'message' => 'Domains geladen', 'data' => $this->domains];
            
        } catch (Exception $e) {
            error_log("DNS Module Error: " . $e->getMessage());
            $this->domains = [];
            return ['success' => false, 'message' => 'Fehler beim Laden der Domains: ' . $e->getMessage()];
        }
    }
    
    /**
     * Lädt DNS-Records für die aktuelle Domain
     */
    private function loadDNSRecords() {
        if (!$this->currentDomain) {
            $this->dnsRecords = [];
            return;
        }
        
        try {
            $serviceManager = new ServiceManager();
            $records = $serviceManager->OvhAPI('get', "/domain/zone/{$this->currentDomain}/record");
            
            if ($records === false || !is_array($records)) {
                $this->dnsRecords = [];
                error_log("DNS Module: Failed to load DNS records for domain: " . $this->currentDomain);
                return;
            }
            
            // Detaillierte Informationen für jeden Record holen
            $this->dnsRecords = [];
            foreach ($records as $recordId) {
                try {
                    $record = $serviceManager->OvhAPI('get', "/domain/zone/{$this->currentDomain}/record/{$recordId}");
                    if ($record !== false && is_array($record)) {
                        $record['id'] = $recordId;
                        $this->dnsRecords[] = $record;
                    }
                } catch (Exception $e) {
                    error_log("DNS Module: Failed to load record {$recordId} for domain {$this->currentDomain}: " . $e->getMessage());
                }
            }
            
        } catch (Exception $e) {
            error_log("DNS Module: Exception loading DNS records for domain {$this->currentDomain}: " . $e->getMessage());
            $this->dnsRecords = [];
        }
    }
    
    /**
     * Lädt DNSSEC-Status
     */
    private function loadDnssecStatus() {
        if (!$this->currentDomain) return;
        
        try {
            $serviceManager = new ServiceManager();
            $this->dnssecStatus = $serviceManager->OvhAPI('get', "/domain/zone/{$this->currentDomain}/dnssec");
        } catch (Exception $e) {
            $this->dnssecStatus = null;
        }
    }
    
    /**
     * Lädt DNSSEC-Schlüssel
     */
    private function loadDnssecKeys() {
        if (!$this->currentDomain || !$this->dnssecStatus) return;
        
        try {
            $serviceManager = new ServiceManager();
            $this->dnssecKeys = $serviceManager->OvhAPI('get', "/domain/zone/{$this->currentDomain}/dnssec/keys") ?: [];
        } catch (Exception $e) {
            $this->dnssecKeys = [];
        }
    }
    
    /**
     * Testet die OVH API-Verbindung
     */
    private function testOvhConnection() {
        try {
            $serviceManager = new ServiceManager();
            $serverTime = $serviceManager->OvhAPI('get', '/auth/time');
            
            if ($serverTime === false) {
                return ['success' => false, 'message' => 'OVH API-Verbindung fehlgeschlagen'];
            }
            
            return [
                'success' => true, 
                'message' => 'API-Verbindung erfolgreich',
                'data' => [
                    'server_time' => $serverTime,
                    'config' => [
                        'application_key' => Config::OVH_APPLICATION_KEY,
                        'endpoint' => Config::OVH_ENDPOINT
                    ]
                ]
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Fehler: ' . $e->getMessage()];
        }
    }
    
    /**
     * Fügt einen DNS-Record hinzu
     */
    private function addDNSRecord($data) {
        $errors = $this->validate($data, [
            'domain' => 'required|min:3',
            'recordType' => 'required',
            'target' => 'required'
        ]);
        
        if (!empty($errors)) {
            $serviceManager = new ServiceManager();
            $serviceManager->__log('DNS Record Creation', 'Validierung fehlgeschlagen: ' . implode(', ', $errors), 'error');
            return ['success' => false, 'message' => 'Validierung fehlgeschlagen', 'errors' => $errors];
        }
        
        try {
            $serviceManager = new ServiceManager();
            
            $recordData = [
                'fieldType' => $data['recordType'],
                'target' => $data['target'],
                'ttl' => (int)($data['ttl'] ?? 3600)
            ];
            
            // Subdomain nur hinzufügen wenn nicht leer
            if (isset($data['subdomain']) && !empty(trim($data['subdomain']))) {
                $recordData['subDomain'] = trim($data['subdomain']);
            }
            
            // Priority nur für MX und SRV Records hinzufügen
            if (isset($data['priority']) && !empty($data['priority']) && in_array($data['recordType'], ['MX', 'SRV'])) {
                $recordData['priority'] = (int)$data['priority'];
            }
            
            // Debug: Logge die zu sendenden Daten
            $serviceManager->__log('DNS Record Creation', 'Versuche DNS-Record zu erstellen für Domain: ' . $data['domain'] . ' mit Daten: ' . json_encode($recordData), 'info');
            
            $result = $serviceManager->OvhAPI('post', "/domain/zone/{$data['domain']}/record", $recordData);
            
            if ($result === false) {
                $serviceManager->__log('DNS Record Creation', 'DNS-Record konnte nicht erstellt werden für Domain: ' . $data['domain'], 'error');
                return ['success' => false, 'message' => 'DNS-Record konnte nicht erstellt werden'];
            }
            
            $serviceManager->__log('DNS Record Creation', 'DNS-Record erfolgreich erstellt für Domain: ' . $data['domain'] . ' (Typ: ' . $data['recordType'] . ')', 'success');
            return ['success' => true, 'message' => 'Record erfolgreich hinzugefügt'];
            
        } catch (Exception $e) {
            $serviceManager = new ServiceManager();
            $serviceManager->__log('DNS Record Creation', 'Fehler beim Erstellen des DNS-Records: ' . $e->getMessage(), 'error');
            return ['success' => false, 'message' => 'Fehler beim Hinzufügen: ' . $e->getMessage()];
        }
    }
    
    /**
     * Aktualisiert einen DNS-Record
     */
    private function updateDNSRecord($data) {
        $errors = $this->validate($data, [
            'domain' => 'required|min:3',
            'recordId' => 'required|numeric',
            'recordType' => 'required',
            'target' => 'required'
        ]);
        
        if (!empty($errors)) {
            $serviceManager = new ServiceManager();
            $serviceManager->__log('DNS Record Update', 'Validierung fehlgeschlagen: ' . implode(', ', $errors), 'error');
            return ['success' => false, 'message' => 'Validierung fehlgeschlagen', 'errors' => $errors];
        }
        
        try {
            $serviceManager = new ServiceManager();
            
            $recordData = [
                'fieldType' => $data['recordType'],
                'target' => $data['target'],
                'ttl' => (int)($data['ttl'] ?? 3600)
            ];
            
            // Subdomain nur hinzufügen wenn nicht leer
            if (isset($data['subdomain']) && !empty(trim($data['subdomain']))) {
                $recordData['subDomain'] = trim($data['subdomain']);
            }
            
            // Priority nur für MX und SRV Records hinzufügen
            if (isset($data['priority']) && !empty($data['priority']) && in_array($data['recordType'], ['MX', 'SRV'])) {
                $recordData['priority'] = (int)$data['priority'];
            }
            
            $serviceManager->__log('DNS Record Update', 'Versuche DNS-Record zu aktualisieren für Domain: ' . $data['domain'] . ' (ID: ' . $data['recordId'] . ') mit Daten: ' . json_encode($recordData), 'info');
            
            $result = $serviceManager->OvhAPI('put', "/domain/zone/{$data['domain']}/record/{$data['recordId']}", $recordData);
            
            if ($result === false) {
                $serviceManager->__log('DNS Record Update', 'DNS-Record konnte nicht aktualisiert werden für Domain: ' . $data['domain'] . ' (ID: ' . $data['recordId'] . ')', 'error');
                return ['success' => false, 'message' => 'DNS-Record konnte nicht aktualisiert werden'];
            }
            
            $serviceManager->__log('DNS Record Update', 'DNS-Record erfolgreich aktualisiert für Domain: ' . $data['domain'] . ' (ID: ' . $data['recordId'] . ')', 'success');
            return ['success' => true, 'message' => 'Record erfolgreich aktualisiert'];
            
        } catch (Exception $e) {
            $serviceManager = new ServiceManager();
            $serviceManager->__log('DNS Record Update', 'Fehler beim Aktualisieren des DNS-Records: ' . $e->getMessage(), 'error');
            return ['success' => false, 'message' => 'Fehler beim Aktualisieren: ' . $e->getMessage()];
        }
    }
    
    /**
     * Löscht einen DNS-Record
     */
    private function deleteDNSRecord($data) {
        $errors = $this->validate($data, [
            'domain' => 'required|min:3',
            'recordId' => 'required|numeric'
        ]);
        
        if (!empty($errors)) {
            return ['success' => false, 'message' => 'Validierung fehlgeschlagen', 'errors' => $errors];
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->OvhAPI('delete', "/domain/zone/{$data['domain']}/record/{$data['recordId']}");
            
            if ($result === false) {
                return ['success' => false, 'message' => 'DNS-Record konnte nicht gelöscht werden'];
            }
            
            return ['success' => true, 'message' => 'Record erfolgreich gelöscht'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Fehler beim Löschen: ' . $e->getMessage()];
        }
    }
    
    /**
     * Aktualisiert die DNS-Zone
     */
    private function refreshDNSZone($data) {
        $errors = $this->validate($data, [
            'domain' => 'required|min:3'
        ]);
        
        if (!empty($errors)) {
            return ['success' => false, 'message' => 'Validierung fehlgeschlagen', 'errors' => $errors];
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->OvhAPI('post', "/domain/zone/{$data['domain']}/refresh");
            
            if ($result === false) {
                return ['success' => false, 'message' => 'Zone-Refresh fehlgeschlagen'];
            }
            
            return ['success' => true, 'message' => 'Zone erfolgreich aktualisiert'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Fehler beim Aktualisieren: ' . $e->getMessage()];
        }
    }
    
    /**
     * Exportiert die DNS-Zone
     */
    private function exportZone($data) {
        $errors = $this->validate($data, [
            'domain' => 'required|min:3'
        ]);
        
        if (!empty($errors)) {
            return ['success' => false, 'message' => 'Validierung fehlgeschlagen', 'errors' => $errors];
        }
        
        try {
            $serviceManager = new ServiceManager();
            
            // Lade DNS-Records für die Domain
            $records = $serviceManager->OvhAPI('get', "/domain/zone/{$data['domain']}/record");
            
            if ($records === false) {
                return ['success' => false, 'message' => 'DNS-Records konnten nicht geladen werden'];
            }
            
            // Lade Zone-Informationen
            $zoneInfo = $serviceManager->OvhAPI('get', "/domain/zone/{$data['domain']}");
            
            if ($zoneInfo === false) {
                return ['success' => false, 'message' => 'Zone-Informationen konnten nicht geladen werden'];
            }
            
            // Generiere Zone-Datei im Standard-Format
            $zoneContent = $this->generateZoneFile($data['domain'], $records, $zoneInfo);
            
            // Speichere Zone-Content für Anzeige
            $this->zoneContent = $zoneContent;
            
            $serviceManager->__log('DNS Zone Export', "Domain: {$data['domain']}, Records: " . count($records), 'info');
            
            return [
                'success' => true, 
                'message' => 'Zone erfolgreich exportiert',
                'zoneContent' => $zoneContent,
                'downloadUrl' => $this->generateDownloadUrl($data['domain'], $zoneContent)
            ];
            
        } catch (Exception $e) {
            $serviceManager = new ServiceManager();
            $serviceManager->__log('DNS Zone Export Fehler', "Domain: {$data['domain']}, Fehler: " . $e->getMessage(), 'error');
            return ['success' => false, 'message' => 'Fehler beim Exportieren der Zone: ' . $e->getMessage()];
        }
    }
    
    /**
     * Generiert eine Zone-Datei im Standard-DNS-Format
     */
    private function generateZoneFile($domain, $records, $zoneInfo) {
        $zoneContent = [];
        
        // TTL aus Zone-Info oder Standard
        $ttl = (is_array($zoneInfo) && isset($zoneInfo['ttl'])) ? (int)$zoneInfo['ttl'] : 3600;
        
        // Zone-Header
        $zoneContent[] = '$TTL ' . $ttl;
        
        // SOA Record
        $soaRecord = $this->findRecordByType($records, 'SOA');
        if ($soaRecord && isset($soaRecord['target'])) {
            $soa = $soaRecord['target'];
            $zoneContent[] = "@\tIN SOA {$soa}";
        } else {
            // Fallback SOA
            $zoneContent[] = "@\tIN SOA dns100.ovh.net. tech.ovh.net. (" . date('YmdH') . " 86400 3600 3600000 60)";
        }
        
        // NS Records
        $nsRecords = $this->findRecordsByType($records, 'NS');
        foreach ($nsRecords as $nsRecord) {
            if (isset($nsRecord['target'])) {
                $zoneContent[] = "\tIN NS\t{$nsRecord['target']}";
            }
        }
        
        // Andere Records
        $otherRecords = $this->findRecordsByType($records, ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'CAA', 'SRV']);
        
        foreach ($otherRecords as $record) {
            $line = $this->formatRecordLine($record, $ttl);
            if ($line) {
                $zoneContent[] = $line;
            }
        }
        
        return implode("\n", $zoneContent);
    }
    
    /**
     * Findet Records nach Typ
     */
    private function findRecordByType($records, $type) {
        if (!is_array($records)) {
            return null;
        }
        
        foreach ($records as $record) {
            if (is_array($record) && isset($record['fieldType']) && $record['fieldType'] === $type) {
                return $record;
            }
        }
        return null;
    }
    
    /**
     * Findet alle Records nach Typ(en)
     */
    private function findRecordsByType($records, $types) {
        $found = [];
        $types = is_array($types) ? $types : [$types];
        
        if (!is_array($records)) {
            return $found;
        }
        
        foreach ($records as $record) {
            if (is_array($record) && isset($record['fieldType']) && in_array($record['fieldType'], $types)) {
                $found[] = $record;
            }
        }
        
        return $found;
    }
    
    /**
     * Formatiert eine Record-Zeile für die Zone-Datei
     */
    private function formatRecordLine($record, $defaultTtl) {
        if (!is_array($record) || !isset($record['fieldType']) || !isset($record['target'])) {
            return null;
        }
        
        $name = isset($record['subDomain']) && !empty($record['subDomain']) ? $record['subDomain'] : '@';
        $ttl = isset($record['ttl']) ? (int)$record['ttl'] : $defaultTtl;
        $type = $record['fieldType'];
        $target = $record['target'];
        
        // Spezielle Formatierung für verschiedene Record-Typen
        switch ($type) {
            case 'MX':
                $priority = isset($record['priority']) ? (int)$record['priority'] : 10;
                return "{$name}\t{$ttl}\tIN MX\t{$priority} {$target}";
                
            case 'TXT':
                // TXT Records in Anführungszeichen
                $target = '"' . $target . '"';
                return "{$name}\t{$ttl}\tIN TXT\t{$target}";
                
            case 'CNAME':
                // CNAME Records mit Punkt am Ende
                $target = rtrim($target, '.') . '.';
                return "{$name}\t{$ttl}\tIN CNAME\t{$target}";
                
            case 'SRV':
                $priority = isset($record['priority']) ? (int)$record['priority'] : 0;
                $weight = isset($record['weight']) ? (int)$record['weight'] : 0;
                $port = isset($record['port']) ? (int)$record['port'] : 0;
                return "{$name}\t{$ttl}\tIN SRV\t{$priority} {$weight} {$port} {$target}";
                
            case 'CAA':
                $flags = isset($record['flags']) ? (int)$record['flags'] : 0;
                $tag = isset($record['tag']) ? $record['tag'] : 'issue';
                return "{$name}\t{$ttl}\tIN CAA\t{$flags} {$tag} {$target}";
                
            default:
                // Standard Records (A, AAAA, NS)
                return "{$name}\t{$ttl}\tIN {$type}\t{$target}";
        }
    }
    
    /**
     * Generiert Download-URL für Zone-Datei
     */
    private function generateDownloadUrl($domain, $zoneContent) {
        // Erstelle temporäre Datei
        $filename = $domain . '_dns_zone.txt';
        $filepath = sys_get_temp_dir() . '/' . $filename;
        
        if (file_put_contents($filepath, $zoneContent) === false) {
            return null;
        }
        
        // Generiere Download-URL - verwende absolute URL
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        
        // Bestimme den korrekten Pfad
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
        if ($scriptDir === '/' || $scriptDir === '\\') {
            $scriptDir = '';
        }
        
        $baseUrl = $protocol . '://' . $host . $scriptDir;
        
        return $baseUrl . '/download_zone.php?file=' . urlencode($filename);
    }
    
    /**
     * Importiert eine DNS-Zone
     */
    private function importZone($data) {
        $errors = $this->validate($data, [
            'domain' => 'required|min:3',
            'zoneContent' => 'required'
        ]);
        
        if (!empty($errors)) {
            return ['success' => false, 'message' => 'Validierung fehlgeschlagen', 'errors' => $errors];
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->OvhAPI('post', "/domain/zone/{$data['domain']}/import", [
                'zoneFile' => $data['zoneContent']
            ]);
            
            if ($result === false) {
                return ['success' => false, 'message' => 'Zone konnte nicht importiert werden'];
            }
            
            return ['success' => true, 'message' => 'Zone importiert'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Fehler beim Importieren: ' . $e->getMessage()];
        }
    }
    
    /**
     * Aktiviert DNSSEC
     */
    private function enableDnssec($data) {
        $errors = $this->validate($data, [
            'domain' => 'required|min:3'
        ]);
        
        if (!empty($errors)) {
            return ['success' => false, 'message' => 'Validierung fehlgeschlagen', 'errors' => $errors];
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->OvhAPI('post', "/domain/zone/{$data['domain']}/dnssec");
            
            if ($result === false) {
                return ['success' => false, 'message' => 'DNSSEC konnte nicht aktiviert werden'];
            }
            
            return ['success' => true, 'message' => 'DNSSEC aktiviert'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Fehler beim Aktivieren: ' . $e->getMessage()];
        }
    }
    
    /**
     * Deaktiviert DNSSEC
     */
    private function disableDnssec($data) {
        $errors = $this->validate($data, [
            'domain' => 'required|min:3'
        ]);
        
        if (!empty($errors)) {
            return ['success' => false, 'message' => 'Validierung fehlgeschlagen', 'errors' => $errors];
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->OvhAPI('delete', "/domain/zone/{$data['domain']}/dnssec");
            
            if ($result === false) {
                return ['success' => false, 'message' => 'DNSSEC konnte nicht deaktiviert werden'];
            }
            
            return ['success' => true, 'message' => 'DNSSEC deaktiviert'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Fehler beim Deaktivieren: ' . $e->getMessage()];
        }
    }
    
    /**
     * Fügt einen DNSSEC-Schlüssel hinzu
     */
    private function addDnssecKey($data) {
        $errors = $this->validate($data, [
            'domain' => 'required|min:3',
            'keyType' => 'required',
            'algorithm' => 'required|numeric',
            'keySize' => 'required|numeric'
        ]);
        
        if (!empty($errors)) {
            return ['success' => false, 'message' => 'Validierung fehlgeschlagen', 'errors' => $errors];
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->OvhAPI('post', "/domain/zone/{$data['domain']}/dnssec/keys", [
                'keyType' => $data['keyType'],
                'algorithm' => $data['algorithm'],
                'keySize' => $data['keySize']
            ]);
            
            if ($result === false) {
                return ['success' => false, 'message' => 'DNSSEC-Schlüssel konnte nicht hinzugefügt werden'];
            }
            
            return ['success' => true, 'message' => 'DNSSEC-Schlüssel hinzugefügt'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Fehler beim Hinzufügen: ' . $e->getMessage()];
        }
    }
    
    /**
     * Löscht einen DNSSEC-Schlüssel
     */
    private function deleteDnssecKey($data) {
        $errors = $this->validate($data, [
            'domain' => 'required|min:3',
            'keyId' => 'required|numeric'
        ]);
        
        if (!empty($errors)) {
            return ['success' => false, 'message' => 'Validierung fehlgeschlagen', 'errors' => $errors];
        }
        
        try {
            $serviceManager = new ServiceManager();
            $result = $serviceManager->OvhAPI('delete', "/domain/zone/{$data['domain']}/dnssec/keys/{$data['keyId']}");
            
            if ($result === false) {
                return ['success' => false, 'message' => 'DNSSEC-Schlüssel konnte nicht gelöscht werden'];
            }
            
            return ['success' => true, 'message' => 'DNSSEC-Schlüssel gelöscht'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Fehler beim Löschen: ' . $e->getMessage()];
        }
    }
    
    /**
     * Gibt Statistiken für das Dashboard zurück
     */
    public function getStats() {
        try {
            $serviceManager = new ServiceManager();
            $domains = $serviceManager->OvhAPI('get', '/domain');
            
            if ($domains === false || !is_array($domains)) {
                return [
                    'total_domains' => 0,
                    'domains_with_dnssec' => 0,
                    'dnssec_percentage' => 0
                ];
            }
            
            $totalDomains = count($domains);
            $domainsWithDnssec = 0;
            
            // Prüfe DNSSEC-Status für jede Domain
            foreach ($domains as $domain) {
                $domainName = is_string($domain) ? $domain : ($domain['name'] ?? $domain->name ?? '');
                if ($domainName) {
                    try {
                        $dnssecStatus = $serviceManager->OvhAPI('get', "/domain/zone/{$domainName}/dnssec");
                        if ($dnssecStatus && isset($dnssecStatus['status']) && $dnssecStatus['status'] === 'ENABLED') {
                            $domainsWithDnssec++;
                        }
                    } catch (Exception $e) {
                        // DNSSEC nicht verfügbar für diese Domain
                    }
                }
            }
            
            return [
                'total_domains' => $totalDomains,
                'domains_with_dnssec' => $domainsWithDnssec,
                'dnssec_percentage' => $totalDomains > 0 ? round(($domainsWithDnssec / $totalDomains) * 100, 1) : 0
            ];
        } catch (Exception $e) {
            return [
                'total_domains' => 0,
                'domains_with_dnssec' => 0,
                'dnssec_percentage' => 0
            ];
        }
    }
    
    /**
     * Implementiert die abstrakte Methode für AJAX-Requests
     */
    public function handleAjaxRequest($action, $data) {
        switch ($action) {
            case 'getContent':
                return $this->getContentResponse();
                
            case 'test_api':
                return $this->testOvhConnection();
                
            case 'load_domains':
                return $this->loadDomains();
                
            case 'select_domain':
                return $this->selectDomain($data);
                
            case 'add_record':
                return $this->addDNSRecord($data);
                
            case 'edit_record':
                return $this->updateDNSRecord($data);
                
            case 'delete_record':
                return $this->deleteDNSRecord($data);
                
            case 'refresh_zone':
                return $this->refreshDNSZone($data);
                
            case 'export_zone':
                return $this->exportZone($data);
                
            case 'import_zone':
                return $this->importZone($data);
                
            case 'enable_dnssec':
                return $this->enableDnssec($data);
                
            case 'disable_dnssec':
                return $this->disableDnssec($data);
                
            case 'add_dnssec_key':
                return $this->addDnssecKey($data);
                
            case 'delete_dnssec_key':
                return $this->deleteDnssecKey($data);
                
            default:
                return ['success' => false, 'message' => 'Unknown action: ' . $action];
        }
    }
    
    /**
     * Gibt den Modul-Inhalt als AJAX-Response zurück
     */
    private function getContentResponse() {
        // Behandle GET-Parameter für Domain-Auswahl
        if (isset($_GET['domain'])) {
            $this->currentDomain = $_GET['domain'];
        }
        
        // Lade alle Daten
        $this->loadAllData();
        
        $translations = $this->tMultiple([
            'module_title', 'domain_selection', 'select_domain', 'dns_records', 'add_record',
            'edit_record', 'delete_record', 'record_type', 'subdomain', 'target', 'ttl',
            'priority', 'refresh_zone', 'export_zone', 'import_zone', 'zone_info',
            'save', 'cancel', 'edit', 'delete', 'create', 'refresh', 'actions', 'status',
            'loading', 'operation_successful', 'operation_failed', 'confirm_delete',
            'validation_failed', 'unknown_action', 'domain_required', 'record_type_required',
            'target_required', 'ttl_required', 'priority_required', 'mx_records',
            'a_records', 'cname_records', 'txt_records', 'ns_records', 'srv_records',
            'caa_records', 'aaaa_records', 'ptr_records', 'soa_record', 'zone_transfer',
            'dnssec_status', 'enable_dnssec', 'disable_dnssec', 'dnssec_keys',
            'add_key', 'delete_key', 'key_type', 'key_algorithm', 'key_size',
            'zone_updated', 'zone_update_failed', 'record_added', 'record_updated',
            'record_deleted', 'record_add_failed', 'record_update_failed', 'record_delete_failed',
            'test_api_connection', 'api_connection_successful', 'api_connection_failed',
            'domains_loaded', 'dns_records_loaded', 'dnssec_enabled', 'dnssec_disabled',
            'zone_exported', 'zone_imported', 'no_domains_available', 'no_records_found',
            'no_dnssec_keys_found', 'weight', 'port', 'key_id', 'algorithm', 'select'
        ]);
        
        $content = $this->render('main', [
            'translations' => $translations,
            'domains' => $this->domains,
            'currentDomain' => $this->currentDomain,
            'dnsRecords' => $this->dnsRecords,
            'dnssecStatus' => $this->dnssecStatus,
            'dnssecKeys' => $this->dnssecKeys,
            'zoneContent' => $this->zoneContent,
            'actionResult' => null
        ]);
        
        return ['success' => true, 'content' => $content];
    }

    /**
     * Wählt eine Domain aus und lädt deren spezifische Daten
     */
    private function selectDomain($data) {
        if (!isset($data['domain']) || empty($data['domain'])) {
            return ['success' => false, 'message' => 'Keine Domain angegeben'];
        }
        
        $selectedDomain = $data['domain'];
        
        // Lade zuerst die Domains, falls sie noch nicht geladen sind
        $this->loadDomains();
        
        // Prüfe, ob die Domain in der Liste der verfügbaren Domains ist
        if (!in_array($selectedDomain, $this->domains)) {
            return ['success' => false, 'message' => 'Domain nicht gefunden: ' . $selectedDomain];
        }
        
        // Setze die ausgewählte Domain
        $this->currentDomain = $selectedDomain;
        
        // Lade spezifische Daten für diese Domain
        $this->loadDNSRecords();
        $this->loadDnssecStatus();
        $this->loadDnssecKeys();
        
        // Nur bei echten Problemen loggen
        if (empty($this->dnsRecords)) {
            error_log("DNS Module: No DNS records loaded for domain: " . $selectedDomain);
        }
        
        // Lade Übersetzungen
        $translations = $this->tMultiple([
            'module_title', 'domain_selection', 'select_domain', 'dns_records', 'add_record',
            'edit_record', 'delete_record', 'record_type', 'subdomain', 'target', 'ttl',
            'priority', 'refresh_zone', 'export_zone', 'import_zone', 'zone_info',
            'save', 'cancel', 'edit', 'delete', 'create', 'refresh', 'actions', 'status',
            'loading', 'operation_successful', 'operation_failed', 'confirm_delete',
            'validation_failed', 'unknown_action', 'domain_required', 'record_type_required',
            'target_required', 'ttl_required', 'priority_required', 'mx_records',
            'a_records', 'cname_records', 'txt_records', 'ns_records', 'srv_records',
            'caa_records', 'aaaa_records', 'ptr_records', 'soa_record', 'zone_transfer',
            'dnssec_status', 'enable_dnssec', 'disable_dnssec', 'dnssec_keys',
            'add_key', 'delete_key', 'key_type', 'key_algorithm', 'key_size',
            'zone_updated', 'zone_update_failed', 'record_added', 'record_updated',
            'record_deleted', 'record_add_failed', 'record_update_failed', 'record_delete_failed',
            'test_api_connection', 'api_connection_successful', 'api_connection_failed',
            'domains_loaded', 'dns_records_loaded', 'dnssec_enabled', 'dnssec_disabled',
            'zone_exported', 'zone_imported', 'no_domains_available', 'no_records_found',
            'no_dnssec_keys_found', 'weight', 'port', 'key_id', 'algorithm', 'select'
        ]);
        
        // Rendere den vollständigen Inhalt
        $content = $this->render('main', [
            'translations' => $translations,
            'domains' => $this->domains,
            'currentDomain' => $this->currentDomain,
            'dnsRecords' => $this->dnsRecords,
            'dnssecStatus' => $this->dnssecStatus,
            'dnssecKeys' => $this->dnssecKeys,
            'zoneContent' => $this->zoneContent,
            'actionResult' => null
        ]);
        
        return [
            'success' => true, 
            'message' => 'Domain-Daten geladen: ' . $selectedDomain,
            'content' => $content,
            'domain' => $selectedDomain,
            'dnsRecordsCount' => count($this->dnsRecords),
            'dnssecKeysCount' => count($this->dnssecKeys)
        ];
    }
} 