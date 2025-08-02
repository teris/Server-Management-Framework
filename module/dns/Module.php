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
            
            // Debug-Logging hinzufügen
            error_log("DNS Module: Loaded " . count($this->domains) . " domains: " . implode(', ', $this->domains));
            
            // Entferne die automatische Domain-Auswahl - keine Domain wird automatisch ausgewählt
            // if (empty($this->currentDomain) && !empty($this->domains)) {
            //     $this->currentDomain = $this->domains[0];
            // }
            
            // Lade nur dann weitere Daten, wenn eine Domain explizit ausgewählt wurde
            if (!empty($this->currentDomain)) {
                $this->loadDNSRecords();
                $this->loadDnssecStatus();
                $this->loadDnssecKeys();
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
        if (!$this->currentDomain) return;
        
        try {
            $serviceManager = new ServiceManager();
            $records = $serviceManager->OvhAPI('get', "/domain/zone/{$this->currentDomain}/record");
            
            if ($records === false) {
                $this->dnsRecords = [];
                return;
            }
            
            // Detaillierte Informationen für jeden Record holen
            $this->dnsRecords = [];
            foreach ($records as $recordId) {
                $record = $serviceManager->OvhAPI('get', "/domain/zone/{$this->currentDomain}/record/{$recordId}");
                if ($record !== false) {
                    $record['id'] = $recordId;
                    $this->dnsRecords[] = $record;
                }
            }
            
        } catch (Exception $e) {
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
            return ['success' => false, 'message' => 'Validierung fehlgeschlagen', 'errors' => $errors];
        }
        
        try {
            $serviceManager = new ServiceManager();
            
            $recordData = [
                'fieldType' => $data['recordType'],
                'target' => $data['target'],
                'ttl' => $data['ttl'] ?? 3600
            ];
            
            if (isset($data['subdomain'])) {
                $recordData['subDomain'] = $data['subdomain'];
            }
            
            if (isset($data['priority']) && in_array($data['recordType'], ['MX', 'SRV'])) {
                $recordData['priority'] = $data['priority'];
            }
            
            $result = $serviceManager->OvhAPI('post', "/domain/zone/{$data['domain']}/record", $recordData);
            
            if ($result === false) {
                return ['success' => false, 'message' => 'DNS-Record konnte nicht erstellt werden'];
            }
            
            return ['success' => true, 'message' => 'Record erfolgreich hinzugefügt'];
            
        } catch (Exception $e) {
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
            return ['success' => false, 'message' => 'Validierung fehlgeschlagen', 'errors' => $errors];
        }
        
        try {
            $serviceManager = new ServiceManager();
            
            $recordData = [
                'fieldType' => $data['recordType'],
                'target' => $data['target'],
                'ttl' => $data['ttl'] ?? 3600
            ];
            
            if (isset($data['subdomain'])) {
                $recordData['subDomain'] = $data['subdomain'];
            }
            
            if (isset($data['priority']) && in_array($data['recordType'], ['MX', 'SRV'])) {
                $recordData['priority'] = $data['priority'];
            }
            
            $result = $serviceManager->OvhAPI('put', "/domain/zone/{$data['domain']}/record/{$data['recordId']}", $recordData);
            
            if ($result === false) {
                return ['success' => false, 'message' => 'DNS-Record konnte nicht aktualisiert werden'];
            }
            
            return ['success' => true, 'message' => 'Record erfolgreich aktualisiert'];
            
        } catch (Exception $e) {
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
            $zone = $serviceManager->OvhAPI('get', "/domain/zone/{$data['domain']}/export");
            
            if ($zone === false) {
                return ['success' => false, 'message' => 'Zone konnte nicht exportiert werden'];
            }
            
            $this->zoneContent = $zone;
            return ['success' => true, 'message' => 'Zone exportiert'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Fehler beim Exportieren: ' . $e->getMessage()];
        }
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
        
        // Prüfe, ob die Domain in der Liste der verfügbaren Domains ist
        if (!in_array($selectedDomain, $this->domains)) {
            // Lade zuerst die Domains, falls sie noch nicht geladen sind
            $this->loadDomains();
            if (!in_array($selectedDomain, $this->domains)) {
                return ['success' => false, 'message' => 'Domain nicht gefunden: ' . $selectedDomain];
            }
        }
        
        // Setze die ausgewählte Domain
        $this->currentDomain = $selectedDomain;
        
        // Lade spezifische Daten für diese Domain
        $this->loadDNSRecords();
        $this->loadDnssecStatus();
        $this->loadDnssecKeys();
        
        error_log("DNS Module: Domain selected: " . $selectedDomain . ", loaded " . count($this->dnsRecords) . " DNS records");
        
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
        
        // Rendere nur den Domain-spezifischen Inhalt
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