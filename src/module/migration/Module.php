<?php
/**
 * Migration-Modul für das Backend
 * 
 * Ermöglicht die Migration von Benutzern aus verschiedenen Systemen:
 * - ISPConfig 3
 * - Proxmox VE
 * - OpenGamePanel
 * 
 * @author Migrationstool
 * @version 1.0
 * @date 2025-01-27
 */

 require_once dirname(dirname(__FILE__)) . '/ModuleBase.php';

class MigrationModule extends ModuleBase {
    
    private $db;
    private $logger;
    private $config;
    private $migrationStats = [
        'customers' => 0,
        'ispconfig_users' => 0,
        'proxmox_users' => 0,
        'ogp_users' => 0,
        'errors' => 0,
        'warnings' => 0
    ];
    
    public function __construct($module_key) {
        parent::__construct($module_key);
        
        $this->db = DatabaseManager::getInstance();
    }
    
    /**
     * Prüft Admin-Rechte für Migration
     */
    public function canAccess() {
        return parent::canAccess() && $this->user_role === 'admin';
    }
    
    /**
     * Gibt den HTML-Content für das Migration-Interface zurück
     */
    public function getContent() {
        if (!$this->canAccess()) {
            return '<div class="error">' . $this->t('access_denied') . '</div>';
        }
        
        $data = [
            'module_key' => $this->module_key,
            't' => function($key, $default = null) { return $this->t($key, $default); },
            'migration_stats' => $this->getLastMigrationStats(),
            'system_status' => $this->getSystemStatus()
        ];
        
        return $this->render('main', $data);
    }
    
    /**
     * Verarbeitet AJAX-Requests für das Migration-Modul
     */
    public function handleAjaxRequest($action, $data) {
        if (!$this->canAccess()) {
            return $this->error($this->t('access_denied'));
        }
        
        try {
            switch ($action) {
                case 'getSystemStatus':
                    return $this->success($this->getSystemStatus());
                    
                case 'startMigration':
                    return $this->startMigration($data);
                    
                case 'getMigrationProgress':
                    return $this->getMigrationProgress();
                    
                case 'stopMigration':
                    return $this->stopMigration();
                    
                case 'getMigrationLog':
                    return $this->getMigrationLog();
                    
                case 'rollbackMigration':
                    return $this->rollbackMigration();
                    
                case 'testConnection':
                    return $this->testConnection($data);
                    
                default:
                    return $this->error($this->t('unknown_action'));
            }
        } catch (Exception $e) {
            $this->log('Migration Error: ' . $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage());
        }
    }
    
    /**
     * Startet die Migration
     */
    private function startMigration($data) {
        $systems = $data['systems'] ?? [];
        
        if (empty($systems)) {
            return $this->error($this->t('no_systems_selected'));
        }
        
        // Migration in separatem Prozess starten
        $this->log('Migration gestartet für Systeme: ' . implode(', ', $systems), 'INFO');
        
        // Migration-Status setzen
        $_SESSION['migration_status'] = 'running';
        $_SESSION['migration_systems'] = $systems;
        $_SESSION['migration_stats'] = $this->migrationStats;
        
        return $this->success([
            'message' => $this->t('migration_started'),
            'systems' => $systems
        ]);
    }
    
    /**
     * Gibt den aktuellen Migrations-Status zurück
     */
    private function getSystemStatus() {
        $status = [
            'ispconfig' => [
                'enabled' => Config::ISPCONFIG_USEING ?? false,
                'host' => Config::ISPCONFIG_HOST ?? null,
                'connected' => false,
                'user_count' => 0
            ],
            'proxmox' => [
                'enabled' => Config::PROXMOX_USEING ?? false,
                'host' => Config::PROXMOX_HOST ?? null,
                'connected' => false,
                'user_count' => 0
            ],
            'ogp' => [
                'enabled' => Config::OGP_USEING ?? false,
                'host' => Config::OGP_HOST ?? null,
                'connected' => false,
                'user_count' => 0
            ]
        ];
        
        // Verbindungen testen
        foreach ($status as $system => &$config) {
            if ($config['enabled']) {
                try {
                    $config['connected'] = $this->testSystemConnection($system);
                    if ($config['connected']) {
                        $config['user_count'] = $this->getSystemUserCount($system);
                    }
                } catch (Exception $e) {
                    $config['error'] = $e->getMessage();
                }
            }
        }
        
        return $status;
    }
    
    /**
     * Testet die Verbindung zu einem System
     */
    private function testSystemConnection($system) {
        switch ($system) {
            case 'ispconfig':
                return $this->testISPConfigConnection();
            case 'proxmox':
                return $this->testProxmoxConnection();
            case 'ogp':
                return $this->testOGPConnection();
            default:
                return false;
        }
    }
    
    /**
     * Gibt die Anzahl der Benutzer in einem System zurück
     */
    private function getSystemUserCount($system) {
        switch ($system) {
            case 'ispconfig':
                return $this->getISPConfigUserCount();
            case 'proxmox':
                return $this->getProxmoxUserCount();
            case 'ogp':
                return $this->getOGPUserCount();
            default:
                return 0;
        }
    }
    
    /**
     * ISPConfig-Verbindung testen
     */
    private function testISPConfigConnection() {
        try {
            $soapClient = new SoapClient(null, [
                'location' => Config::ISPCONFIG_HOST . '/remote/index.php',
                'uri' => Config::ISPCONFIG_HOST . '/remote/',
                'trace' => 1,
                'exceptions' => 1
            ]);
            
            $sessionId = $soapClient->login(Config::ISPCONFIG_USER, Config::ISPCONFIG_PASSWORD);
            return !empty($sessionId);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Proxmox-Verbindung testen
     */
    private function testProxmoxConnection() {
        try {
            $proxmoxAPI = new ProxmoxAPI(
                Config::PROXMOX_HOST,
                Config::PROXMOX_USER,
                Config::PROXMOX_PASSWORD
            );
            
            $response = $proxmoxAPI->get('/version');
            return isset($response['data']);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * OGP-Verbindung testen
     */
    private function testOGPConnection() {
        try {
            $ogpAPI = new OpenGamePanelAPI(
                Config::OGP_HOST,
                Config::OGP_TOKEN
            );
            
            $response = $ogpAPI->makeRequest('GET', '/api/status');
            return isset($response['status']);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * ISPConfig-Benutzeranzahl abrufen
     */
    private function getISPConfigUserCount() {
        try {
            $soapClient = new SoapClient(null, [
                'location' => Config::ISPCONFIG_HOST . '/remote/index.php',
                'uri' => Config::ISPCONFIG_HOST . '/remote/',
                'trace' => 1,
                'exceptions' => 1
            ]);
            
            $sessionId = $soapClient->login(Config::ISPCONFIG_USER, Config::ISPCONFIG_PASSWORD);
            $soapClient->__setCookie('soap_client_session_id', $sessionId);
            
            $clients = $soapClient->client_get();
            return is_array($clients) ? count($clients) : 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Proxmox-Benutzeranzahl abrufen
     */
    private function getProxmoxUserCount() {
        try {
            $proxmoxAPI = new ProxmoxAPI(
                Config::PROXMOX_HOST,
                Config::PROXMOX_USER,
                Config::PROXMOX_PASSWORD
            );
            
            $users = $proxmoxAPI->get('/access/users');
            return isset($users['data']) ? count($users['data']) : 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * OGP-Benutzeranzahl abrufen
     */
    private function getOGPUserCount() {
        try {
            $ogpAPI = new OpenGamePanelAPI(
                Config::OGP_HOST,
                Config::OGP_TOKEN
            );
            
            $users = $ogpAPI->getUsers();
            return is_array($users) ? count($users) : 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Gibt die letzten Migrations-Statistiken zurück
     */
    private function getLastMigrationStats() {
        // Aus der Datenbank oder Session laden
        return $_SESSION['last_migration_stats'] ?? $this->migrationStats;
    }
    
    /**
     * Migration-Progress abrufen
     */
    private function getMigrationProgress() {
        return [
            'status' => $_SESSION['migration_status'] ?? 'idle',
            'progress' => $_SESSION['migration_progress'] ?? 0,
            'current_system' => $_SESSION['migration_current_system'] ?? null,
            'stats' => $_SESSION['migration_stats'] ?? $this->migrationStats
        ];
    }
    
    /**
     * Migration stoppen
     */
    private function stopMigration() {
        $_SESSION['migration_status'] = 'stopped';
        $this->log('Migration gestoppt durch Benutzer', 'WARNING');
        
        return $this->success($this->t('migration_stopped'));
    }
    
    /**
     * Migration-Log abrufen
     */
    private function getMigrationLog() {
        // Log aus der Datenbank oder Datei laden
        $log = $this->db->query(
            "SELECT * FROM user_activities WHERE activity_type = 'migration' ORDER BY created_at DESC LIMIT 50"
        )->fetchAll();
        
        return $this->success($log);
    }
    
    /**
     * Migration rückgängig machen
     */
    private function rollbackMigration() {
        // Rollback-Logik implementieren
        $this->log('Migration-Rollback gestartet', 'WARNING');
        
        return $this->success($this->t('rollback_started'));
    }
    
    /**
     * Verbindung zu einem System testen
     */
    private function testConnection($data) {
        $system = $data['system'] ?? '';
        
        if (empty($system)) {
            return $this->error($this->t('no_system_specified'));
        }
        
        $connected = $this->testSystemConnection($system);
        
        if ($connected) {
            $userCount = $this->getSystemUserCount($system);
            return $this->success([
                'connected' => true,
                'user_count' => $userCount,
                'message' => $this->t('connection_successful')
            ]);
        } else {
            return $this->error($this->t('connection_failed'));
        }
    }
    
    /**
     * Gibt Statistiken für das Dashboard zurück
     */
    public function getStats() {
        if (!$this->canAccess()) {
            return [];
        }
        
        $lastStats = $this->getLastMigrationStats();
        
        return [
            'total_migrations' => $lastStats['customers'],
            'ispconfig_migrations' => $lastStats['ispconfig_users'],
            'proxmox_migrations' => $lastStats['proxmox_users'],
            'ogp_migrations' => $lastStats['ogp_users'],
            'errors' => $lastStats['errors']
        ];
    }
}

/**
 * Proxmox API-Klasse (vereinfacht für das Modul)
 */
class ProxmoxAPI {
    private $host;
    private $username;
    private $password;
    private $ticket;
    private $csrfToken;
    
    public function __construct($host, $username, $password) {
        $this->host = rtrim($host, '/');
        $this->username = $username;
        $this->password = $password;
        $this->authenticate();
    }
    
    private function authenticate() {
        $data = [
            'username' => $this->username,
            'password' => $this->password
        ];
        
        $response = $this->makeRequest('POST', '/api2/json/access/ticket', $data);
        
        if ($response['success']) {
            $this->ticket = $response['data']['ticket'];
            $this->csrfToken = $response['data']['CSRFPreventionToken'];
        } else {
            throw new Exception('Proxmox-Authentifizierung fehlgeschlagen');
        }
    }
    
    public function get($endpoint) {
        return $this->makeRequest('GET', $endpoint);
    }
    
    private function makeRequest($method, $endpoint, $data = null) {
        $url = $this->host . $endpoint;
        
        $headers = [
            'Content-Type: application/json',
            'CSRFPreventionToken: ' . $this->csrfToken
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        if ($this->ticket) {
            curl_setopt($ch, CURLOPT_COOKIE, 'PVEAuthCookie=' . $this->ticket);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Proxmox API-Fehler: HTTP $httpCode");
        }
        
        return json_decode($response, true);
    }
}

/**
 * OpenGamePanel API-Klasse (vereinfacht für das Modul)
 */
class OpenGamePanelAPI {
    private $host;
    private $token;
    
    public function __construct($host, $token) {
        $this->host = rtrim($host, '/');
        $this->token = $token;
    }
    
    public function getUsers() {
        return $this->makeRequest('GET', '/api/users');
    }
    
    public function makeRequest($method, $endpoint) {
        $url = $this->host . $endpoint;
        
        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("OGP API-Fehler: HTTP $httpCode");
        }
        
        return json_decode($response, true);
    }
}
?>
