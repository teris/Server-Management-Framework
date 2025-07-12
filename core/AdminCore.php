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
            default:
                throw new Exception("Unknown resource type: $type");
        }
    }
    
    private function getVMs() {
        try {
            $vms = $this->serviceManager->getProxmoxVMs();
            // Objekte zu Arrays konvertieren
            $result = [];
            foreach ($vms as $vm) {
                if (is_object($vm)) {
                    $result[] = $vm->toArray();
                } else {
                    $result[] = $vm;
                }
            }
            return $result;
        } catch (Exception $e) {
            error_log("Error getting VMs: " . $e->getMessage());
            return [];
        }
    }
    
    private function getWebsites() {
        try {
            $websites = $this->serviceManager->getISPConfigWebsites();
            // Objekte zu Arrays konvertieren
            $result = [];
            foreach ($websites as $site) {
                if (is_object($site)) {
                    $result[] = $site->toArray();
                } else {
                    $result[] = $site;
                }
            }
            return $result;
        } catch (Exception $e) {
            error_log("Error getting websites: " . $e->getMessage());
            return [];
        }
    }
    
    private function getDatabases() {
        try {
            $databases = $this->serviceManager->getISPConfigDatabases();
            // Objekte zu Arrays konvertieren
            $result = [];
            foreach ($databases as $db) {
                if (is_object($db)) {
                    $result[] = $db->toArray();
                } else {
                    $result[] = $db;
                }
            }
            return $result;
        } catch (Exception $e) {
            error_log("Error getting databases: " . $e->getMessage());
            return [];
        }
    }
    
    private function getEmails() {
        try {
            $emails = $this->serviceManager->getISPConfigEmails();
            // Objekte zu Arrays konvertieren
            $result = [];
            foreach ($emails as $email) {
                if (is_object($email)) {
                    $result[] = $email->toArray();
                } else {
                    $result[] = $email;
                }
            }
            return $result;
        } catch (Exception $e) {
            error_log("Error getting emails: " . $e->getMessage());
            return [];
        }
    }
    
    private function getDomains() {
        try {
            $domains = $this->serviceManager->getOVHDomains();
            // Objekte zu Arrays konvertieren
            $result = [];
            foreach ($domains as $domain) {
                if (is_object($domain)) {
                    $result[] = $domain->toArray();
                } else {
                    $result[] = $domain;
                }
            }
            return $result;
        } catch (Exception $e) {
            error_log("Error getting domains: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Activity Logs abrufen
     */
    public function getActivityLogs($filters = []) {
        try {
            $logs = $this->db->getActivityLog(100);
            
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
}
?>