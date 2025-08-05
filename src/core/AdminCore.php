<?php
/**
 * AdminCore - Kern-Funktionalität des Admin Dashboards
 * Nutzt das modulare System aus framework.php
 */

class AdminCore {
    private $db;
    private $serviceManager;
    
    public function __construct() {
        // Database connection
        $this->db = Database::getInstance();
        
        // ServiceManager aus framework.php nutzen
        $this->serviceManager = new ServiceManager();
    }
    
    /**
     * Hole Dashboard-Statistiken
     */
    public function getDashboardStats() {
        $stats = [
            'vms' => [
                'label' => 'Virtuelle Maschinen',
                'count' => 0,
                'icon' => '🖥️',
                'status' => 'normal',
                'status_text' => ''
            ],
            'websites' => [
                'label' => 'Websites',
                'count' => 0,
                'icon' => '🌐',
                'status' => 'normal',
                'status_text' => ''
            ],
            'databases' => [
                'label' => 'Datenbanken',
                'count' => 0,
                'icon' => '🗄️',
                'status' => 'normal',
                'status_text' => ''
            ],
            'emails' => [
                'label' => 'E-Mail Accounts',
                'count' => 0,
                'icon' => '📧',
                'status' => 'normal',
                'status_text' => ''
            ],
            'domains' => [
                'label' => 'Domains',
                'count' => 0,
                'icon' => '🔗',
                'status' => 'normal',
                'status_text' => ''
            ],
            'storage' => [
                'label' => 'Speicher genutzt',
                'count' => '0 GB',
                'icon' => '💾',
                'status' => 'normal',
                'status_text' => ''
            ]
        ];
        
        // VMs zählen (Proxmox)
        try {
            $vms = $this->serviceManager->getProxmoxVMs();
            $stats['vms']['count'] = count($vms);
            
            $running = 0;
            foreach ($vms as $vm) {
                // Robuste Behandlung von VM-Objekten und Arrays
                $status = null;
                
                if (is_object($vm)) {
                    // Versuche verschiedene Eigenschaftsnamen
                    if (property_exists($vm, 'status')) {
                        $status = $vm->status;
                    } elseif (property_exists($vm, 'state')) {
                        $status = $vm->state;
                    } elseif (method_exists($vm, 'getStatus')) {
                        $status = $vm->getStatus();
                    }
                } elseif (is_array($vm)) {
                    $status = $vm['status'] ?? $vm['state'] ?? null;
                }
                
                if ($status === 'running' || $status === 'active') {
                    $running++;
                }
            }
            $stats['vms']['status_text'] = "$running laufend";
        } catch (Exception $e) {
            $stats['vms']['status'] = 'error';
            $stats['vms']['status_text'] = 'Fehler';
            error_log("Error getting VMs: " . $e->getMessage());
        }
        
        // Websites zählen (ISPConfig)
        try {
            $websites = $this->serviceManager->getISPConfigWebsites();
            $stats['websites']['count'] = count($websites);
            
            $active = 0;
            foreach ($websites as $site) {
                // Robuste Behandlung von Website-Objekten und Arrays
                $is_active = false;
                
                if (is_object($site)) {
                    // Versuche verschiedene Eigenschaftsnamen
                    if (property_exists($site, 'active')) {
                        $is_active = ($site->active === 'y' || $site->active === true);
                    } elseif (property_exists($site, 'status')) {
                        $is_active = ($site->status === 'active' || $site->status === 'y');
                    }
                } elseif (is_array($site)) {
                    $active_val = $site['active'] ?? $site['status'] ?? null;
                    $is_active = ($active_val === 'y' || $active_val === true || $active_val === 'active');
                }
                
                if ($is_active) {
                    $active++;
                }
            }
            $stats['websites']['status_text'] = "$active aktiv";
        } catch (Exception $e) {
            $stats['websites']['status'] = 'error';
            $stats['websites']['status_text'] = 'Fehler';
            error_log("Error getting websites: " . $e->getMessage());
        }
        
        // Datenbanken zählen (ISPConfig)
        try {
            $databases = $this->serviceManager->getISPConfigDatabases();
            $stats['databases']['count'] = count($databases);
            $stats['databases']['status_text'] = "Alle aktiv";
        } catch (Exception $e) {
            $stats['databases']['status'] = 'error';
            $stats['databases']['status_text'] = 'Fehler';
            error_log("Error getting databases: " . $e->getMessage());
        }
        
        // E-Mails zählen (ISPConfig)
        try {
            $emails = $this->serviceManager->getISPConfigEmails();
            $stats['emails']['count'] = count($emails);
            $stats['emails']['status_text'] = "Alle aktiv";
        } catch (Exception $e) {
            $stats['emails']['status'] = 'error';
            $stats['emails']['status_text'] = 'Fehler';
            error_log("Error getting emails: " . $e->getMessage());
        }
        
        // Domains zählen (OVH)
        try {
            $domains = $this->serviceManager->getOVHDomains();
            $stats['domains']['count'] = count($domains);
            $stats['domains']['status_text'] = "Alle aktiv";
        } catch (Exception $e) {
            $stats['domains']['status'] = 'error';
            $stats['domains']['status_text'] = 'Fehler';
            error_log("Error getting domains: " . $e->getMessage());
        }
        
        // Speicher-Info
        try {
            $diskUsage = $this->getDiskUsage();
            $stats['storage']['count'] = $diskUsage['formatted'];
            $stats['storage']['status_text'] = $diskUsage['percent'] . '% genutzt';
        } catch (Exception $e) {
            $stats['storage']['status'] = 'error';
            $stats['storage']['status_text'] = 'Fehler';
            error_log("Error getting disk usage: " . $e->getMessage());
        }
        
        return $stats;
    }
    
    /**
     * Hole Ressourcen-Daten
     */
    public function getResources($type) {
        switch ($type) {
            case 'vms':
                return $this->getVMs();
            case 'websites':
                return $this->getWebsites();
            case 'databases':
                return $this->getDatabases();
            case 'emails':
                return $this->getEmails();
            case 'domains':
                return $this->getDomains();
            case 'ip':
                return $this->getIP();
            case 'ogp_servers':
                return $this->getOGPServers();
            case 'ogp_gameservers':
                return $this->getOGPGameServers();
            case 'ogp_games':
                return $this->getOGPGames();
            default:
                throw new Exception("Unknown resource type: $type");
        }
    }
    
    private function getVMs() {
        global $modus_type;
        try {
            if ($modus_type['modus']  == 'mysql') {
                $db = Database::getInstance()->getConnection();
                $sql = "SELECT vm_id, name, node, status, cores, memory, disk_size, ip_address, mac_address FROM vms";
                $stmt = $db->prepare($sql);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result = [];
                foreach ($rows as $row) {
                    $result[] = [
                        'vmid' => $row['vm_id'],
                        'name' => $row['name'],
                        'node' => $row['node'],
                        'status' => $row['status'],
                        'cores' => $row['cores'],
                        'memory' => $row['memory'],
                        'disk' => $row['disk_size'],
                        'ip_address' => $row['ip_address'],
                        'mac_address' => $row['mac_address']
                    ];
                }
                return $result;
            } else {
                $vms = $this->serviceManager->getProxmoxVMs();
                $result = [];
                foreach ($vms as $vm) {
                    if (is_object($vm)) {
                        if (method_exists($vm, 'toArray')) {
                            $result[] = $vm->toArray();
                        } else {
                            $result[] = (array) $vm;
                        }
                    } else {
                        $result[] = $vm;
                    }
                }
                return $result;
            }
        } catch (Exception $e) {
            error_log("Error getting VMs: " . $e->getMessage());
            return [];
        }
    }
    
    private function getWebsites() {
        global $modus_type;
        try {
            if ($modus_type['modus']  === 'mysql') {
                $db = Database::getInstance()->getConnection();
                $sql = "SELECT domain, ip_address, system_user, system_group, document_root, hd_quota, traffic_quota, active, ssl_enabled FROM websites";
                $stmt = $db->prepare($sql);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result = [];
                foreach ($rows as $row) {
                    $result[] = [
                        'domain' => $row['domain'],
                        'ip_address' => $row['ip_address'],
                        'system_user' => $row['system_user'],
                        'system_group' => $row['system_group'],
                        'document_root' => $row['document_root'],
                        'hd_quota' => $row['hd_quota'],
                        'traffic_quota' => $row['traffic_quota'],
                        'active' => $row['active'],
                        'ssl_enabled' => $row['ssl_enabled']
                    ];
                }
                return $result;
            } else {
                $websites = $this->serviceManager->getISPConfigWebsites();
                $result = [];
                foreach ($websites as $site) {
                    if (is_object($site)) {
                        if (method_exists($site, 'toArray')) {
                            $result[] = $site->toArray();
                        } else {
                            $result[] = (array) $site;
                        }
                    } else {
                        $result[] = $site;
                    }
                }
                return $result;
            }
        } catch (Exception $e) {
            error_log("Error getting websites: " . $e->getMessage());
            return [];
        }
    }
    
    private function getDatabases() {
        global $modus_type;
        try {
            if ($modus_type['modus']  === 'mysql') {
                $db = Database::getInstance()->getConnection();
                $sql = "SELECT database_name, database_user, database_type, server_id, charset, remote_access, active FROM sm_databases";
                $stmt = $db->prepare($sql);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result = [];
                foreach ($rows as $row) {
                    $result[] = [
                        'database_name' => $row['database_name'],
                        'database_user' => $row['database_user'],
                        'database_type' => $row['database_type'],
                        'server_id' => $row['server_id'],
                        'charset' => $row['charset'],
                        'remote_access' => $row['remote_access'],
                        'active' => $row['active']
                    ];
                }
                return $result;
            } else {
                $databases = $this->serviceManager->getISPConfigDatabases();
                $result = [];
                foreach ($databases as $db) {
                    if (is_object($db)) {
                        if (method_exists($db, 'toArray')) {
                            $result[] = $db->toArray();
                        } else {
                            $result[] = (array) $db;
                        }
                    } else {
                        $result[] = $db;
                    }
                }
                return $result;
            }
        } catch (Exception $e) {
            error_log("Error getting databases: " . $e->getMessage());
            return [];
        }
    }
    
    private function getEmails() {
        global $modus_type;
        try {
            if ($modus_type['modus']  === 'mysql') {
                $db = Database::getInstance()->getConnection();
                $sql = "SELECT email_address, login_name, full_name, domain, quota_mb, active, autoresponder, forward_to FROM email_accounts";
                $stmt = $db->prepare($sql);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result = [];
                foreach ($rows as $row) {
                    $result[] = [
                        'email' => $row['email_address'],
                        'login' => $row['login_name'],
                        'name' => $row['full_name'],
                        'domain' => $row['domain'],
                        'quota' => $row['quota_mb'],
                        'active' => $row['active'],
                        'autoresponder' => $row['autoresponder'],
                        'forward_to' => $row['forward_to']
                    ];
                }
                return $result;
            } else {
                $emails = $this->serviceManager->getISPConfigEmails();
                $result = [];
                foreach ($emails as $email) {
                    if (is_object($email)) {
                        if (method_exists($email, 'toArray')) {
                            $result[] = $email->toArray();
                        } else {
                            $result[] = (array) $email;
                        }
                    } else {
                        $result[] = $email;
                    }
                }
                return $result;
            }
        } catch (Exception $e) {
            error_log("Error getting emails: " . $e->getMessage());
            return [];
        }
    }
    
    private function getDomains() {
        global $modus_type;
        try {
            if ($modus_type['modus'] === 'mysql') {
                $db = Database::getInstance()->getConnection();
                $sql = "SELECT domain_name, registrar, expiration_date, auto_renew, nameservers, status FROM domains";
                $stmt = $db->prepare($sql);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result = [];
                foreach ($rows as $row) {
                    $result[] = [
                        'domain' => $row['domain_name'],
                        'registrar' => $row['registrar'],
                        'expiration' => $row['expiration_date'],
                        'auto_renew' => $row['auto_renew'],
                        'nameServers' => json_decode($row['nameservers'], true),
                        'status' => $row['status']
                    ];
                }
                return $result;
            } else {
                $domains = $this->serviceManager->getOVHDomains();
                $result = [];
                foreach ($domains as $domain) {
                    if (is_object($domain)) {
                        if (method_exists($domain, 'toArray')) {
                            $result[] = $domain->toArray();
                        } else {
                            $result[] = (array) $domain;
                        }
                    } else {
                        $result[] = $domain;
                    }
                }
                return $result;
            }
        } catch (Exception $e) {
            error_log("Error getting domains: " . $e->getMessage());
            return [];
        }
    }

    private function getIP() {
        global $modus_type;
        try {
            if ($modus_type['modus']  === 'mysql') {
                // Aus der Datenbank lesen
                $db = Database::getInstance()->getConnection();
                $sql = "SELECT subnet, ip_reverse, reverse, ttl FROM ips";
                $stmt = $db->prepare($sql);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                // Rückgabe wie bisher: [subnet => [ip_reverse => [details...], ...], ...]
                $result = [];
                foreach ($rows as $row) {
                    $subnet = $row['subnet'];
                    $ipReverse = $row['ip_reverse'];
                    $result[$subnet][$ipReverse] = [
                        'ipReverse' => $ipReverse,
                        'reverse' => $row['reverse'],
                        'ttl' => $row['ttl'],
                        'macAddress' => '',
                        'type' => ''
                    ];
                }
                return $result;
            } else {
                $ip = $this->serviceManager->getOvhIP();
                return $ip;
            }
        } catch (Exception $e) {
            error_log("Error getting IP: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Hole OGP Server-Liste
     */
    private function getOGPServers() {
        try {
            $servers = $this->serviceManager->getOGPServerList();

            if (isset($servers['status']) && $servers['status'] == 200 && isset($servers['message'])) {
                // Nach IP-Adresse sortieren
                $serverList = $servers['message'];
                usort($serverList, function($a, $b) {
                    return ip2long($a['agent_ip']) - ip2long($b['agent_ip']);
                });

                return $serverList;
            }
            error_log("OGP Servers: No valid data returned");
            return [];
        } catch (Exception $e) {
            error_log("Error getting OGP servers: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Hole OGP GameServer-Liste
     */
    private function getOGPGameServers() {
        try {
            $gameServers = $this->serviceManager->getOGPGameServers();

            if (isset($gameServers['status']) && $gameServers['status'] == 200 && isset($gameServers['message'])) {
                // Nach IP-Adresse sortieren
                $serverList = $gameServers['message'];
                usort($serverList, function($a, $b) {
                    return ip2long($a['agent_ip']) - ip2long($b['agent_ip']);
                });

                return $serverList;
            }
            error_log("OGP GameServers: No valid data returned");
            return [];
        } catch (Exception $e) {
            error_log("Error getting OGP game servers: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Hole OGP Games-Liste
     */
    private function getOGPGames() {
        try {
            $games = [];
            $systems = ['linux', 'windows'];
            $architectures = ['32', '64'];
            
            // Hole Spiele für alle Systeme und Architekturen
            foreach ($systems as $system) {
                foreach ($architectures as $architecture) {
                    $gameList = $this->serviceManager->getOGPGamesList($system, $architecture);
                    
                    if (isset($gameList['status']) && $gameList['status'] == 200 && isset($gameList['message'])) {
                        foreach ($gameList['message'] as $game) {
                            $gameKey = $game['game_key'];
                            if (!isset($games[$gameKey])) {
                                $games[$gameKey] = [
                                    'game_name' => $game['game_name'],
                                    'game_key' => $gameKey,
                                    'variants' => []
                                ];
                            }
                            
                            $variant = [
                                'home_cfg_id' => $game['home_cfg_id'],
                                'home_cfg_file' => $game['home_cfg_file'],
                                'system' => $system,
                                'architecture' => $architecture,
                                'mods' => isset($game['mods']) ? $game['mods'] : []
                            ];
                            
                            $games[$gameKey]['variants'][] = $variant;
                        }
                    }
                }
            }
            
            // Sortiere nach Game-Name
            ksort($games);
            
            return array_values($games);
        } catch (Exception $e) {
            error_log("Error getting OGP games: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Gibt eine kombinierte Liste aller IPs mit zugehörigem Reverse-DNS, MAC-Adresse und Typ zurück
     * Nur Einträge mit passender MAC-Adresse werden ausgegeben
     * Rückgabe: Array mit Schlüsseln: ipReverse, reverse, macAddress, type
     */
    public function getIpMacReverseTable() {
        $macData = $this->serviceManager->getCompleteVirtualMacInfo();
        $reverseData = $this->serviceManager->getOvhIP();
        $result = [];
        foreach ($reverseData as $subnet => $ips) {
            if (!is_array($ips)) continue;
            foreach ($ips as $ip => $details) {
                $ipReverse = $details['ipReverse'] ?? $ip;
                $reverse = $details['reverse'] ?? '';
                $macInfo = $this->findMacAndType($macData, $ipReverse);
                $result[] = [
                    'ipReverse' => $ipReverse,
                    'reverse' => $reverse,
                    'macAddress' => $macInfo['macAddress'] ? $macInfo['macAddress'] : 'Nicht zugewiesen',
                    'type' => $macInfo['macAddress'] ? $macInfo['type'] : 'Nicht zugewiesen'
                ];
            }
        }
        return $result;
    }
    /**
     * Hilfsfunktion: Sucht MAC und Typ zu einer IP
     */
    private function findMacAndType($macData, $ip) {
        foreach ($macData as $server) {
            if (!isset($server['virtualMacs'])) continue;
            foreach ($server['virtualMacs'] as $macObj) {
                if (is_object($macObj)) $macObj = (array)$macObj;
                if (!isset($macObj['ips']) || !is_array($macObj['ips'])) continue;
                foreach ($macObj['ips'] as $ipEntry) {
                    if (isset($ipEntry['ipAddress']) && $ipEntry['ipAddress'] === $ip) {
                        return [
                            'macAddress' => $macObj['macAddress'] ?? '',
                            'type' => $macObj['type'] ?? ''
                        ];
                    }
                }
            }
        }
        return ['macAddress' => '', 'type' => ''];
    }
    
    /**
     * Activity Logs abrufen
     */
    public function getActivityLogs($filters = [], $limit = 50, $offset = 0) {
        try {
            $logs = $this->db->getActivityLog($limit, $offset);
            
            // Filter anwenden
            if (!empty($filters)) {
                $logs = $this->filterLogs($logs, $filters);
            }
            
            return $logs;
        } catch (Exception $e) {
            error_log("Error getting activity logs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Activity Logs löschen
     */
    public function clearActivityLogs() {
        try {
            $result = $this->db->clearActivityLogs();
            
            if ($result) {
                $this->db->logAction('Activity Logs Cleared', 'All activity logs were cleared by admin', 'SUCCESS');
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Error clearing activity logs: " . $e->getMessage());
            return false;
        }
    }
    
    private function filterLogs($logs, $filters) {
        $filtered = [];
        
        foreach ($logs as $log) {
            $include = true;
            
            if (isset($filters['level']) && $filters['level']) {
                if (strtolower($log['status']) !== strtolower($filters['level'])) {
                    $include = false;
                }
            }
            
            if (isset($filters['date']) && $filters['date']) {
                $logDate = date('Y-m-d', strtotime($log['created_at']));
                if ($logDate !== $filters['date']) {
                    $include = false;
                }
            }
            
            if ($include) {
                $filtered[] = $log;
            }
        }
        
        return $filtered;
    }
    
    /**
     * System-Informationen abrufen
     */
    public function getSystemInfo() {
        return [
            'php_version' => phpversion(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'system_load' => $this->getSystemLoad(),
            'disk_usage' => $this->getDiskUsage(),
            'memory_usage' => $this->getMemoryUsage(),
            'active_modules' => count(getEnabledModules())
        ];
    }
    
    private function getSystemLoad() {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return [
                '1min' => $load[0],
                '5min' => $load[1],
                '15min' => $load[2]
            ];
        }
        return ['1min' => 0, '5min' => 0, '15min' => 0];
    }
    
    private function getDiskUsage() {
        // Versuche verschiedene Pfade wegen open_basedir Restriktionen
        $paths_to_try = [
            '/var/www/clients/client0/web24/remote',
            '/var/www/clients/client0/web24/private',
            '/tmp',
            '.',
            '/'
        ];
        
        $total = 0;
        $free = 0;
        
        foreach ($paths_to_try as $path) {
            try {
                $test_total = disk_total_space($path);
                $test_free = disk_free_space($path);
                
                if ($test_total > 0 && $test_free > 0) {
                    $total = $test_total;
                    $free = $test_free;
                    break;
                }
            } catch (Exception $e) {
                // Ignoriere Fehler und versuche nächsten Pfad
                continue;
            }
        }
        
        // Fallback wenn keine Disk-Space-Info verfügbar
        if ($total <= 0 || $free <= 0) {
            return [
                'total' => 0,
                'used' => 0,
                'free' => 0,
                'percent' => 0,
                'formatted' => 'N/A'
            ];
        }
        
        $used = $total - $free;
        $percent = round(($used / $total) * 100, 2);
        
        return [
            'total' => $total,
            'used' => $used,
            'free' => $free,
            'percent' => $percent,
            'formatted' => $this->formatBytes($used) . ' / ' . $this->formatBytes($total)
        ];
    }
    
    private function getMemoryUsage() {
        if (function_exists('memory_get_usage')) {
            $memory = memory_get_usage(true);
            $peak = memory_get_peak_usage(true);
            
            return [
                'current' => $this->formatBytes($memory),
                'peak' => $this->formatBytes($peak)
            ];
        }
        return ['current' => 'Unknown', 'peak' => 'Unknown'];
    }
    
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Cache leeren
     */
    public function clearCache() {
        try {
            // Session-Cache leeren
            if (isset($_SESSION['cache'])) {
                unset($_SESSION['cache']);
            }
            
            // OpCache leeren (falls verfügbar)
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            
            // Log-Aktion
            $this->db->logAction('Cache cleared', 'Admin cache was cleared', 'SUCCESS');
            
            return true;
        } catch (Exception $e) {
            error_log("Error clearing cache: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verbindungen testen
     */
    public function testConnections() {
        $results = [];
        
        // Proxmox testen
        try {
            $vms = $this->serviceManager->getProxmoxVMs();
            $results['proxmox'] = [
                'status' => 'success',
                'message' => 'Verbindung erfolgreich (' . count($vms) . ' VMs gefunden)'
            ];
        } catch (Exception $e) {
            $results['proxmox'] = [
                'status' => 'error',
                'message' => 'Verbindung fehlgeschlagen: ' . $e->getMessage()
            ];
        }
        
        // ISPConfig testen
        try {
            $websites = $this->serviceManager->getISPConfigWebsites();
            $results['ispconfig'] = [
                'status' => 'success',
                'message' => 'Verbindung erfolgreich (' . count($websites) . ' Websites gefunden)'
            ];
        } catch (Exception $e) {
            $results['ispconfig'] = [
                'status' => 'error',
                'message' => 'Verbindung fehlgeschlagen: ' . $e->getMessage()
            ];
        }
        
        // OVH testen
        try {
            $domains = $this->serviceManager->getOVHDomains();
            $results['ovh'] = [
                'status' => 'success',
                'message' => 'Verbindung erfolgreich (' . count($domains) . ' Domains gefunden)'
            ];
        } catch (Exception $e) {
            $results['ovh'] = [
                'status' => 'error',
                'message' => 'Verbindung fehlgeschlagen: ' . $e->getMessage()
            ];
        }
        
        return $results;
    }

    // --- API-Zugangsdaten ---
    public function getApiCredentials() {
        try {
            $stmt = $this->db->getConnection()->query("SELECT * FROM api_credentials");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Werte aus config/config.inc.php holen
            require_once dirname(__DIR__) . '/config/config.inc.php';
            $config = [
                'proxmox' => [
                    'endpoint' => Config::PROXMOX_HOST,
                    'username' => Config::PROXMOX_USER,
                    'password' => Config::PROXMOX_PASSWORD
                ],
                'ispconfig' => [
                    'endpoint' => Config::ISPCONFIG_HOST,
                    'username' => Config::ISPCONFIG_USER,
                    'password' => Config::ISPCONFIG_PASSWORD
                ],
                'ovh' => [
                    'endpoint' => Config::OVH_ENDPOINT,
                    'application_key' => Config::OVH_APPLICATION_KEY,
                    'application_secret' => Config::OVH_APPLICATION_SECRET,
                    'consumer_key' => Config::OVH_CONSUMER_KEY
                ]
            ];
            return ['success' => true, 'data' => $data, 'config' => $config];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    public function saveApiCredentials($data) {
        try {
            $db = $this->db->getConnection();
            foreach ($data as $key => $value) {
                if (preg_match('/^api_url_(\d+)$/', $key, $m)) {
                    $id = $m[1];
                    $url = $value;
                    $user = $data['api_user_' . $id] ?? '';
                    $pass = $data['api_password_' . $id] ?? '';
                    $stmt = $db->prepare("UPDATE api_credentials SET api_url=?, api_user=?, api_password=? WHERE id=?");
                    $stmt->execute([$url, $user, $pass, $id]);
                }
            }
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    // --- Module ---
    public function getModules() {
        try {
            $stmt = $this->db->getConnection()->query("SELECT * FROM modules");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return ['success' => true, 'data' => $data];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    public function saveModules($data) {
        try {
            $db = $this->db->getConnection();
            $stmt = $db->query("SELECT id FROM modules");
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($ids as $id) {
                $isActive = isset($data['module_' . $id]) ? 1 : 0;
                $db->prepare("UPDATE modules SET is_active=? WHERE id=?")->execute([$isActive, $id]);
            }
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    // --- Benutzer ---
    public function getUsers() {
        try {
            $stmt = $this->db->getConnection()->query("SELECT * FROM users");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return ['success' => true, 'data' => $data];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    public function getUser($id) {
        try {
            $stmt = $this->db->getConnection()->prepare("SELECT * FROM users WHERE id=?");
            $stmt->execute([$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            return ['success' => true, 'data' => $data];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    public function saveUser($data) {
        try {
            $db = $this->db->getConnection();
            $id = $data['user_id'] ?? null;
            $username = $data['username'] ?? '';
            $full_name = $data['full_name'] ?? '';
            $email = $data['email'] ?? '';
            $group_id = $data['group_id'] ?? null;
            $role = $data['role'] ?? 'user';
            if ($group_id) {
                $stmt_group = $db->prepare("SELECT name FROM groups WHERE id = ?");
                $stmt_group->execute([$group_id]);
                $role = $stmt_group->fetchColumn() ?: $role;
            }
            $active = isset($data['active']) && $data['active'] === 'y' ? 'y' : 'n';
            $password = $data['password'] ?? '';
            if ($id) {
                // Update
                if ($password) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET username=?, full_name=?, email=?, role=?, active=?, password_hash=?, group_id=? WHERE id=?");
                    $stmt->execute([$username, $full_name, $email, $role, $active, $hash, $group_id, $id]);
                } else {
                    $stmt = $db->prepare("UPDATE users SET username=?, full_name=?, email=?, role=?, active=?, group_id=? WHERE id=?");
                    $stmt->execute([$username, $full_name, $email, $role, $active, $group_id, $id]);
                }
            } else {
                // Insert
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, full_name, email, role, active, password_hash, group_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $full_name, $email, $role, $active, $hash, $group_id]);
            }
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    public function deleteUser($id) {
        try {
            $stmt = $this->db->getConnection()->prepare("DELETE FROM users WHERE id=?");
            $stmt->execute([$id]);
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    // --- Gruppen ---
    public function getGroups() {
        try {
            $stmt = $this->db->getConnection()->query("SELECT * FROM groups");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return ['success' => true, 'data' => $data];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    public function getGroup($id) {
        try {
            $stmt = $this->db->getConnection()->prepare("SELECT * FROM groups WHERE id=?");
            $stmt->execute([$id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            return ['success' => true, 'data' => $data];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    public function saveGroup($data) {
        try {
            $db = $this->db->getConnection();
            $id = $data['group_id'] ?? null;
            $name = $data['group_name'] ?? '';
            $desc = $data['group_description'] ?? '';
            if ($id) {
                $stmt = $db->prepare("UPDATE groups SET name=?, description=? WHERE id=?");
                $stmt->execute([$name, $desc, $id]);
            } else {
                $stmt = $db->prepare("INSERT INTO groups (name, description) VALUES (?, ?)");
                $stmt->execute([$name, $desc]);
                $id = $db->lastInsertId();
            }
            // Modulrechte speichern
            $db->prepare("DELETE FROM group_module_permissions WHERE group_id=?")->execute([$id]);
            foreach ($data as $key => $value) {
                if (preg_match('/^module_(\d+)$/', $key, $m)) {
                    $module_id = $m[1];
                    $can_access = $value === 'on' ? 1 : 0;
                    $db->prepare("INSERT INTO group_module_permissions (group_id, module_id, can_access) VALUES (?, ?, ?)")
                        ->execute([$id, $module_id, $can_access]);
                }
            }
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    public function deleteGroup($id) {
        try {
            $db = $this->db->getConnection();
            $db->prepare("DELETE FROM group_module_permissions WHERE group_id=?")->execute([$id]);
            $db->prepare("DELETE FROM groups WHERE id=?")->execute([$id]);
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    public function getGroupModules($groupId = null) {
        try {
            $db = $this->db->getConnection();
            $modules = $db->query("SELECT * FROM modules")->fetchAll(PDO::FETCH_ASSOC);
            if ($groupId) {
                $perms = $db->prepare("SELECT module_id, can_access FROM group_module_permissions WHERE group_id=?");
                $perms->execute([$groupId]);
                $permMap = [];
                foreach ($perms->fetchAll(PDO::FETCH_ASSOC) as $p) {
                    $permMap[$p['module_id']] = $p['can_access'];
                }
                foreach ($modules as &$m) {
                    $m['can_access'] = isset($permMap[$m['id']]) ? (bool)$permMap[$m['id']] : false;
                }
            } else {
                foreach ($modules as &$m) {
                    $m['can_access'] = false;
                }
            }
            return ['success' => true, 'data' => $modules];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
?>