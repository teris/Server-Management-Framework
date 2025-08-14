<?php
require_once 'config/config.inc.php';

// DATABASE CLASS
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . Config::DB_HOST . ";dbname=" . Config::DB_NAME,
                Config::DB_USER,
                Config::DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
                ]
             );
        } catch(PDOException $e) {
            throw new Exception("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function logAction($action, $details, $status) {
        try {
            $stmt = $this->connection->prepare("INSERT INTO activity_log (action, details, status, created_at) VALUES (?, ?, ?, NOW())");
            return $stmt->execute([$action, $details, $status]);
        } catch (PDOException $e) {
            error_log("Database logAction error: " . $e->getMessage());
            return false;
        }
    }

    public function getActivityLog($limit = 50, $offset = 0) {
        try {
            $limit = (int) $limit;
            $offset = (int) $offset;
            if ($limit <= 0) $limit = 50;
            if ($limit > 1000) $limit = 1000;
            if ($offset < 0) $offset = 0;
            $sql = "SELECT id, action, details, status, created_at 
                    FROM activity_log 
                    ORDER BY created_at DESC 
                    LIMIT $limit OFFSET $offset";
            $stmt = $this->connection->prepare($sql);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($results as &$row) {
                if (isset($row['created_at'])) {
                    $row['created_at_formatted'] = date('d.m.Y H:i:s', strtotime($row['created_at']));
                }
            }
            return $results;
        } catch (PDOException $e) {
            error_log("Database getActivityLog error: " . $e->getMessage());
            try {
                $stmt = $this->connection->prepare("SELECT id, action, details, status, created_at FROM activity_log ORDER BY created_at DESC");
                $stmt->execute();
                $all_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                return array_slice($all_results, $offset, $limit);
            } catch (PDOException $e2) {
                error_log("Database fallback error: " . $e2->getMessage());
                return [];
            }
        }
    }
    
    public function clearActivityLogs() {
        try {
            $stmt = $this->connection->prepare("TRUNCATE TABLE activity_log");
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Database clearActivityLogs error: " . $e->getMessage());
            return false;
        }
    }

    // PDO Wrapper-Methoden für Kompatibilität
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }

    public function query($sql) {
        return $this->connection->query($sql);
    }

    public function exec($sql) {
        return $this->connection->exec($sql);
    }

    public function lastInsertId($name = null) {
        return $this->connection->lastInsertId($name);
    }

    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }

    public function commit() {
        return $this->connection->commit();
    }

    public function rollback() {
        return $this->connection->rollback();
    }

    public function inTransaction() {
        return $this->connection->inTransaction();
    }

    public function quote($string, $type = PDO::PARAM_STR) {
        return $this->connection->quote($string, $type);
    }
}

// DATA MODELS
class VM {
    public $vmid;
    public $name;
    public $node;
    public $status;
    public $cores;
    public $memory;
    public $disk;
    public $ip_address;
    public $mac_address;
    public $uptime;
    public $cpu_usage;
    public $memory_usage;
    public $created_at;

    public function __construct($data = []) {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function toArray() {
        return get_object_vars($this);
    }
}

// VIRTUAL MAC DATA MODEL
class VirtualMac {
    public $macAddress;
    public $serviceName;
    public $ipAddress;
    public $virtualNetworkInterface;
    public $type;
    public $reverse;
    public $created_at;
    public $ips;
    public $reverseEntries;

    public function __construct($data = []) {
        $this->macAddress = $data['macAddress'] ?? null;
        $this->serviceName = $data['serviceName'] ?? null;
        $this->ipAddress = $data['ipAddress'] ?? null;
        $this->virtualNetworkInterface = $data['virtualNetworkInterface'] ?? null;
        $this->type = $data['type'] ?? null;
        $this->reverse = $data['reverse'] ?? null;
        $this->created_at = $data['created_at'] ?? null;
        $this->ips = $data['ips'] ?? [];
        $this->reverseEntries = $data['reverseEntries'] ?? [];

        // Alle anderen Properties dynamisch hinzufügen
        foreach ($data as $key => $value) {
            if (!property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function toArray() {
        return get_object_vars($this);
    }
}

class FailoverIP {
    public $ip;
    public $block;
    public $routedTo;
    public $type;
    public $geo;
    public $canBeTerminated;
    public $description;
    public $country;

    public function __construct($data = []) {
        $this->ip = $data['ip'] ?? null;
        $this->block = $data['block'] ?? null;
        $this->routedTo = $data['routedTo'] ?? null;
        $this->type = $data['type'] ?? null;
        $this->geo = $data['geo'] ?? null;
        $this->canBeTerminated = $data['canBeTerminated'] ?? false;
        $this->description = $data['description'] ?? null;
        $this->country = $data['country'] ?? null;

        foreach ($data as $key => $value) {
            if (!property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function toArray() {
        return get_object_vars($this);
    }
}

class Website {
    public $domain_id;
    public $domain;
    public $ip_address;
    public $system_user;
    public $system_group;
    public $active;
    public $hd_quota;
    public $traffic_quota;
    public $document_root;
    public $ssl_enabled;
    public $created_at;

    public function __construct($data = []) {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function toArray() {
        return get_object_vars($this);
    }
}

class Database_Entry {
    public $database_id;
    public $database_name;
    public $database_user;
    public $database_type;
    public $active;
    public $server_id;
    public $charset;
    public $remote_access;
    public $created_at;

    public function __construct($data = []) {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function toArray() {
        return get_object_vars($this);
    }
}

class EmailAccount {
    public $mailuser_id;
    public $email;
    public $login;
    public $name;
    public $domain;
    public $quota;
    public $active;
    public $autoresponder;
    public $forward_to;
    public $created_at;

    public function __construct($data = []) {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function toArray() {
        return get_object_vars($this);
    }
}

class Domain {
    public $domain;
    public $expiration;
    public $autoRenew;
    public $state;
    public $nameServers;
    public $dnssec;
    public $registrar;
    public $created_at;

    public function __construct($data = []) {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function toArray() {
        return get_object_vars($this);
    }
}

class VPS {
    public $name;
    public $state;
    public $cluster;
    public $ips;
    public $mac_addresses;
    public $memory;
    public $disk;
    public $cpu;
    public $model;
    public $zone;

    public function __construct($data = []) {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function toArray() {
        return get_object_vars($this);
    }
}

// DATA MAPPER CLASS
class DataMapper {

    public static function mapToVM($data) {
        return new VM([
            'vmid' => $data['vmid'] ?? null,
            'name' => $data['name'] ?? null,
            'node' => $data['node'] ?? null,
            'status' => $data['status'] ?? null,
            'cores' => $data['cores'] ?? $data['cpus'] ?? null,
            'memory' => $data['memory'] ?? $data['maxmem'] ?? null,
            'disk' => $data['disk'] ?? $data['maxdisk'] ?? null,
            'uptime' => $data['uptime'] ?? null,
            'cpu_usage' => $data['cpu'] ?? null,
            'memory_usage' => $data['mem'] ?? null
        ]);
    }

	public static function mapToVirtualMac($data, $serviceName = null, $macAddress = null) {
        return new VirtualMac([
            'macAddress' => $macAddress ?? $data['macAddress'] ?? null,
            'serviceName' => $serviceName ?? $data['serviceName'] ?? null,
            'ipAddress' => $data['ipAddress'] ?? null,
            'virtualNetworkInterface' => $data['virtualNetworkInterface'] ?? null,
            'type' => $data['type'] ?? null,
            'reverse' => $data['reverse'] ?? null,
            'created_at' => $data['created_at'] ?? date('Y-m-d H:i:s'),
            'ips' => $data['ips'] ?? [],
            'reverseEntries' => $data['reverseEntries'] ?? []
        ]);
    }

    public static function mapToWebsite($data) {
        return new Website([
            'domain_id' => $data['domain_id'] ?? null,
            'domain' => $data['domain'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'system_user' => $data['system_user'] ?? null,
            'system_group' => $data['system_group'] ?? null,
            'active' => $data['active'] ?? null,
            'hd_quota' => $data['hd_quota'] ?? null,
            'traffic_quota' => $data['traffic_quota'] ?? null,
            'document_root' => $data['document_root'] ?? null,
            'ssl_enabled' => $data['ssl'] ?? 'n'
        ]);
    }

    public static function mapToDatabase($data) {
        return new Database_Entry([
            'database_id' => $data['database_id'] ?? null,
            'database_name' => $data['database_name'] ?? null,
            'database_user' => $data['database_user'] ?? null,
            'database_type' => $data['database_type'] ?? 'mysql',
            'active' => $data['active'] ?? null,
            'server_id' => $data['server_id'] ?? null,
            'charset' => $data['database_charset'] ?? 'utf8',
            'remote_access' => $data['remote_access'] ?? 'n'
        ]);
    }

    public static function mapToEmailAccount($data) {
        return new EmailAccount([
            'mailuser_id' => $data['mailuser_id'] ?? null,
            'email' => $data['email'] ?? null,
            'login' => $data['login'] ?? null,
            'name' => $data['name'] ?? null,
            'domain' => $data['domain'] ?? null,
            'quota' => $data['quota'] ?? null,
            'active' => $data['postfix'] ?? null,
            'autoresponder' => $data['autoresponder'] ?? 'n',
            'forward_to' => $data['cc'] ?? null
        ]);
    }

    public static function mapToDomain($data) {
        return new Domain([
            'domain' => $data['domain'] ?? null,
            'expiration' => $data['expiration'] ?? null,
            'autoRenew' => $data['autoRenew'] ?? false,
            'state' => $data['state'] ?? null,
            'nameServers' => $data['nameServers'] ?? [],
            'dnssec' => $data['dnssec'] ?? false,
            'registrar' => 'OVH'
        ]);
    }

    public static function mapToVPS($data) {
        return new VPS([
            'name' => $data['name'] ?? null,
            'state' => $data['state'] ?? null,
            'cluster' => $data['cluster'] ?? null,
            'ips' => $data['ips'] ?? [],
            'mac_addresses' => $data['mac_addresses'] ?? [],
            'memory' => $data['memory'] ?? null,
            'disk' => $data['disk'] ?? null,
            'cpu' => $data['vcore'] ?? null,
            'model' => $data['model'] ?? null,
            'zone' => $data['zone'] ?? null
        ]);
    }

    public static function mapToFailoverIP($data) {
        // The $data here is expected to be the associative array
        // returned by OVHGet::getFailoverIPDetails()
        return new FailoverIP($data);
    }
}

// BASE API CLASS
abstract class BaseAPI {
    public $host;
    public $user;
    public $password;

    abstract protected function authenticate();
    abstract protected function makeRequest($method, $url, $data = null);

    public function logRequest($endpoint, $method, $success) {
        try {
            $db = Database::getInstance();
            $db->logAction(
                "API Request: " . static::class,
                "$method $endpoint",
                $success ? 'success' : 'error'
            );
        } catch (Exception $e) {
            // If database connection fails, skip logging but continue
            error_log("Database connection failed in logRequest: " . $e->getMessage());
        }
    }
}

// PROXMOX GET CLASS
class ProxmoxGet extends BaseAPI {
    private $ticket;
    private $csrf_token;

    public function __construct() {
        $this->host = Config::PROXMOX_HOST;
        $this->user = Config::PROXMOX_USER;
        $this->password = Config::PROXMOX_PASSWORD;
        $this->authenticate();
    }

    public function authenticate() {
        $url = $this->host . "/api2/json/access/ticket";
        $data = [
            'username' => $this->user,
            'password' => $this->password
        ];

        $response = $this->makeRequest('POST', $url, $data);
        if ($response && isset($response['data'])) {
            $this->ticket = $response['data']['ticket'];
            $this->csrf_token = $response['data']['CSRFPreventionToken'];
        }
    }

    public function getNodes() {
        $url = $this->host . "/api2/json/nodes";
        $response = $this->makeRequest('GET', $url);
        //$this->logRequest('/nodes', 'GET', $response !== false);
        return $response && isset($response['data']) ? $response['data'] : [];
    }

    public function getVMs($node = null) {
        $vms = [];
        $nodes = $node ? [$node] : $this->getNodes();

        foreach ($nodes as $nodeData) {
            $nodeName = is_array($nodeData) ? $nodeData['node'] : $nodeData;
            $url = $this->host . "/api2/json/nodes/$nodeName/qemu";
            $response = $this->makeRequest('GET', $url);

            if ($response && isset($response['data'])) {
                foreach ($response['data'] as $vmData) {
                    $vmData['node'] = $nodeName;
                    $vms[] = DataMapper::mapToVM($vmData);
                }
            }
        }

        ////$this->logRequest('/nodes/*/qemu', 'GET', !empty($vms));
        return $vms;
    }

    public function getVM($node, $vmid) {
        $url = $this->host . "/api2/json/nodes/$node/qemu/$vmid/config";
        $response = $this->makeRequest('GET', $url);
        //$this->logRequest("/nodes/$node/qemu/$vmid/config", 'GET', $response !== false);

        if ($response && isset($response['data'])) {
            $vmData = $response['data'];
            $vmData['node'] = $node;
            $vmData['vmid'] = $vmid;
            return DataMapper::mapToVM($vmData);
        }

        return null;
    }

    public function getVMStatus($node, $vmid) {
        $url = $this->host . "/api2/json/nodes/$node/qemu/$vmid/status/current";
        $response = $this->makeRequest('GET', $url);
        //$this->logRequest("/nodes/$node/qemu/$vmid/status/current", 'GET', $response !== false);
        return $response && isset($response['data']) ? $response['data'] : null;
    }

    public function getVMConfig($node, $vmid) {
        $url = $this->host . "/api2/json/nodes/$node/qemu/$vmid/config";
        $response = $this->makeRequest('GET', $url);
        //$this->logRequest("/nodes/$node/qemu/$vmid/config", 'GET', $response !== false);
        return $response && isset($response['data']) ? $response['data'] : null;
    }

    public function getStorages($node = null) {
        $url = $node ?
            $this->host . "/api2/json/nodes/$node/storage" :
            $this->host . "/api2/json/storage";
        $response = $this->makeRequest('GET', $url);
        //$this->logRequest('/storage', 'GET', $response !== false);
        return $response && isset($response['data']) ? $response['data'] : [];
    }

    public function getNetworks($node) {
        $url = $this->host . "/api2/json/nodes/$node/network";
        $response = $this->makeRequest('GET', $url);
        //$this->logRequest("/nodes/$node/network", 'GET', $response !== false);
        return $response && isset($response['data']) ? $response['data'] : [];
    }

    public function makeRequest($method, $url, $data = null) {
        $ch = curl_init();

        $headers = [];
        if ($this->ticket) {
            $headers[] = "Cookie: PVEAuthCookie=" . $this->ticket;
        }
        if ($this->csrf_token) {
            $headers[] = "CSRFPreventionToken: " . $this->csrf_token;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 30
        ]);

        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true);
        }

        return false;
    }
}

// PROXMOX POST CLASS
class ProxmoxPost extends ProxmoxGet {

    public function createVM($vmData) {
        $url = $this->host . "/api2/json/nodes/" . $vmData['node'] . "/qemu";

        $data = [
            'vmid' => $vmData['vmid'],
            'name' => $vmData['name'],
            'memory' => $vmData['memory'],
            'cores' => $vmData['cores'],
            'net0' => 'virtio,bridge=' . $vmData['bridge'] .
                     ($vmData['mac'] ? ',macaddr=' . $vmData['mac'] : ''),
            'scsi0' => $vmData['storage'] . ':' . $vmData['disk'],
            'ostype' => 'l26',
            'ide2' => $vmData['iso']
        ];

        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("/nodes/{$vmData['node']}/qemu", 'POST', $response !== false);
        return $response;
    }

    public function editVM($node, $vmid, $vmData) {
        $url = $this->host . "/api2/json/nodes/$node/qemu/$vmid/config";
        $response = $this->makeRequest('PUT', $url, $vmData);
        //$this->logRequest("/nodes/$node/qemu/$vmid/config", 'PUT', $response !== false);
        return $response;
    }

    public function deleteVM($node, $vmid) {
        $url = $this->host . "/api2/json/nodes/$node/qemu/$vmid";
        $response = $this->makeRequest('DELETE', $url);
        //$this->logRequest("/nodes/$node/qemu/$vmid", 'DELETE', $response !== false);
        return $response;
    }

    public function startVM($node, $vmid) {
        $url = $this->host . "/api2/json/nodes/$node/qemu/$vmid/status/start";
        $response = $this->makeRequest('POST', $url);
        //$this->logRequest("/nodes/$node/qemu/$vmid/status/start", 'POST', $response !== false);
        return $response;
    }

    public function stopVM($node, $vmid) {
        $url = $this->host . "/api2/json/nodes/$node/qemu/$vmid/status/stop";
        $response = $this->makeRequest('POST', $url);
        //$this->logRequest("/nodes/$node/qemu/$vmid/status/stop", 'POST', $response !== false);
        return $response;
    }

    public function resetVM($node, $vmid) {
        $url = $this->host . "/api2/json/nodes/$node/qemu/$vmid/status/reset";
        $response = $this->makeRequest('POST', $url);
        //$this->logRequest("/nodes/$node/qemu/$vmid/status/reset", 'POST', $response !== false);
        return $response;
    }

    public function suspendVM($node, $vmid) {
        $url = $this->host . "/api2/json/nodes/$node/qemu/$vmid/status/suspend";
        $response = $this->makeRequest('POST', $url);
        //$this->logRequest("/nodes/$node/qemu/$vmid/status/suspend", 'POST', $response !== false);
        return $response;
    }

    public function resumeVM($node, $vmid) {
        $url = $this->host . "/api2/json/nodes/$node/qemu/$vmid/status/resume";
        $response = $this->makeRequest('POST', $url);
        //$this->logRequest("/nodes/$node/qemu/$vmid/status/resume", 'POST', $response !== false);
        return $response;
    }

    public function cloneVM($node, $vmid, $newVmid, $name = null) {
        $url = $this->host . "/api2/json/nodes/$node/qemu/$vmid/clone";
        $data = [
            'newid' => $newVmid,
            'name' => $name ?: "clone-of-$vmid"
        ];

        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("/nodes/$node/qemu/$vmid/clone", 'POST', $response !== false);
        return $response;
    }

    // PROXMOX USER MANAGEMENt
    public function createProxmoxUser($userData) {
        $url = $this->host . "/api2/json/access/users";
        
        // Proxmox API erwartet: userid, password, comment (optional), email (optional), firstname (optional), lastname (optional)
        $data = [
            'userid' => $userData['username'] . '@' . $userData['realm'],
            'password' => $userData['password'],
            'comment' => $userData['comment'] ?? '',
            'email' => $userData['email'] ?? '',
            'firstname' => $userData['first_name'] ?? '',
            'lastname' => $userData['last_name'] ?? ''
        ];

        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("/access/users", 'POST', $response !== false);
        return $response;
    }

    public function deleteProxmoxUser($userid) {
        $url = $this->host . "/api2/json/access/users/$userid";
        $response = $this->makeRequest('DELETE', $url);
        //$this->logRequest("/access/users/$userid", 'DELETE', $response !== false);
        return $response;
    }

    public function updateProxmoxUser($userid, $userData) {
        $url = $this->host . "/api2/json/access/users/$userid";
        
        $data = [];
        if (isset($userData['password'])) $data['password'] = $userData['password'];
        if (isset($userData['comment'])) $data['comment'] = $userData['comment'];
        if (isset($userData['email'])) $data['email'] = $userData['email'];
        if (isset($userData['firstname'])) $data['firstname'] = $userData['firstname'];
        if (isset($userData['lastname'])) $data['lastname'] = $userData['lastname'];

        $response = $this->makeRequest('PUT', $url, $data);
        //$this->logRequest("/access/users/$userid", 'PUT', $response !== false);
        return $response;
    }

    public function getProxmoxUsers() {
        $url = $this->host . "/api2/json/access/users";
        $response = $this->makeRequest('GET', $url);
        //$this->logRequest("/access/users", 'GET', $response !== false);
        return $response;
    }

    public function getProxmoxUser($userid) {
        $url = $this->host . "/api2/json/access/users/$userid";
        $response = $this->makeRequest('GET', $url);
        //$this->logRequest("/access/users/$userid", 'GET', $response !== false);
        return $response;
    }
}

// ISPCONFIG GET CLASS
class ISPConfigGet extends BaseAPI {
    public $session_id;
    public $client;
    private $debug_mode = false; // Für Debugging aktivieren

    public function __construct() {
        $this->host = Config::ISPCONFIG_HOST;
        $this->user = Config::ISPCONFIG_USER;
        $this->password = Config::ISPCONFIG_PASSWORD;
        $this->authenticate();
    }

    public function authenticate() {
        try {
            if ($this->debug_mode) {
                error_log("ISPConfig: Verbindung zu {$this->host}");
            }

            // SOAP Client mit erweiterten Optionen
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ],
                'http' => [
                    'timeout' => 60,
                    'user_agent' => 'ISPConfig-API-Client/1.0'
                ]
            ]);

            $this->client = new SoapClient(null, [
                'location' => $this->host . '/remote/index.php',
                'uri' => $this->host . '/remote/',
                'trace' => 1,
                'exceptions' => 1,
                'connection_timeout' => 60,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'stream_context' => $context,
                'soap_version' => SOAP_1_1,
                'encoding' => 'UTF-8'
            ]);

            if ($this->debug_mode) {
                error_log("ISPConfig: SOAP Client erstellt");
            }

            $this->session_id = $this->client->login($this->user, $this->password);
            
            if ($this->debug_mode) {
                error_log("ISPConfig: Login-Ergebnis: " . ($this->session_id ? "Erfolgreich (Session: {$this->session_id})" : "Fehlgeschlagen"));
            }

            ////$this->logRequest('/remote/login', 'POST', $this->session_id !== false);
            
            if (!$this->session_id) {
                throw new Exception("ISPConfig Login fehlgeschlagen - Überprüfen Sie Zugangsdaten");
            }

        } catch (SoapFault $e) {
            error_log("ISPConfig SOAP Fehler: " . $e->getMessage());
            error_log("ISPConfig SOAP Details: " . $e->getTraceAsString());
            throw new Exception("SOAP Verbindung fehlgeschlagen: " . $e->getMessage());
        } catch (Exception $e) {
            error_log('ISPConfig Login fehlgeschlagen: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getWebsites($filter = []) {
        try {
            $websites = $this->client->sites_web_domain_get($this->session_id, $filter);
            //$this->logRequest('/sites/web_domain/get', 'GET', $websites !== false);

            if ($websites) {
                return array_map(function($site) {
                    return DataMapper::mapToWebsite($site);
                }, $websites);
            }

            return [];
        } catch (Exception $e) {
            error_log('Error getting websites: ' . $e->getMessage());
            return [];
        }
    }

    public function getWebsite($domainId) {
        try {
            $website = $this->client->sites_web_domain_get($this->session_id, ['domain_id' => $domainId]);
            //$this->logRequest("/sites/web_domain/$domainId", 'GET', $website !== false);

            if ($website && isset($website[0])) {
                return DataMapper::mapToWebsite($website[0]);
            }

            return null;
        } catch (Exception $e) {
            error_log('Error getting website: ' . $e->getMessage());
            return null;
        }
    }

    public function getDatabases($filter = []) {
        try {
            $databases = $this->client->sites_database_get($this->session_id, $filter);
            //$this->logRequest('/sites/database/get', 'GET', $databases !== false);

            if ($databases) {
                return array_map(function($db) {
                    return DataMapper::mapToDatabase($db);
                }, $databases);
            }

            return [];
        } catch (Exception $e) {
            error_log('Error getting databases: ' . $e->getMessage());
            return [];
        }
    }

    public function getDatabase($databaseId) {
        try {
            $database = $this->client->sites_database_get($this->session_id, ['database_id' => $databaseId]);
            //$this->logRequest("/sites/database/$databaseId", 'GET', $database !== false);

            if ($database && isset($database[0])) {
                return DataMapper::mapToDatabase($database[0]);
            }

            return null;
        } catch (Exception $e) {
            error_log('Error getting database: ' . $e->getMessage());
            return null;
        }
    }
    public function getEmailAccounts($filter = []) {
        try {
            if (!$this->session_id) {
                throw new Exception("Keine gültige ISPConfig Session");
            }

            if ($this->debug_mode) {
                error_log("ISPConfig: Rufe E-Mail Accounts ab mit Filter: " . json_encode($filter));
            }

            // Strategie 1: Versuche mail_user_get mit primary_id (leer für alle)
            try {
                if ($this->debug_mode) {
                    error_log("ISPConfig: Versuche mail_user_get mit primary_id");
                }

                $emails = $this->client->mail_user_get($this->session_id, ['postfix' => 'y']);
                
                if ($emails && is_array($emails) && count($emails) > 0) {
                    if ($this->debug_mode) {
                        error_log("ISPConfig: mail_user_get erfolgreich, " . count($emails) . " E-Mails gefunden");
                        error_log("ISPConfig: Beispiel E-Mail: " . json_encode($emails[0]));
                    }

                    return array_map(function($email) {
                        return DataMapper::mapToEmailAccount($email);
                    }, $emails);
                }

            } catch (SoapFault $e) {
                if ($this->debug_mode) {
                    error_log("ISPConfig: mail_user_get Fehler: " . $e->getMessage());
                }
            }

            $emailIds = $this->getEmailUserIds();
            
            if (!empty($emailIds)) {
                $emails = [];
                
                if ($this->debug_mode) {
                    error_log("ISPConfig: Gefundene E-Mail-IDs: " . implode(', ', $emailIds));
                }

                foreach ($emailIds as $mailuser_id) {
                    try {
                        $email = $this->client->mail_user_get($this->session_id, $mailuser_id);
                        if ($email && is_array($email)) {
                            $emails[] = $email;
                        }
                    } catch (Exception $e) {
                        if ($this->debug_mode) {
                            error_log("ISPConfig: Fehler beim Abrufen von E-Mail ID {$mailuser_id}: " . $e->getMessage());
                        }
                        continue;
                    }
                }

                if (!empty($emails)) {
                    if ($this->debug_mode) {
                        error_log("ISPConfig: Über IDs abgerufen: " . count($emails) . " E-Mails");
                    }

                    return array_map(function($email) {
                        return DataMapper::mapToEmailAccount($email);
                    }, $emails);
                }
            }

            $clientEmails = $this->getEmailsFromClients();
            if (!empty($clientEmails)) {
                return $clientEmails;
            }

            $alternativeEmails = $this->tryAlternativeMailFunctions();
            if (!empty($alternativeEmails)) {
                return $alternativeEmails;
            }

            if ($this->debug_mode) {
                error_log("ISPConfig: Keine E-Mails über alle Methoden gefunden");
            }

            return [];

        } catch (Exception $e) {
            error_log('ISPConfig E-Mail Fehler: ' . $e->getMessage());
            throw $e;
        }
    }
    private function getEmailUserIds() {
        $ids = [];

        try {
            $websites = $this->client->sites_web_domain_get($this->session_id, []);
            if ($websites && is_array($websites)) {
                foreach ($websites as $website) {
                    if (isset($website['domain_id'])) {
                        // Versuche zugehörige E-Mail-Accounts zu finden
                        // ISPConfig verknüpft oft E-Mails mit Domain-IDs
                        $ids[] = $website['domain_id'];
                    }
                }
            }
        } catch (Exception $e) {
            if ($this->debug_mode) {
                error_log("ISPConfig: sites_web_domain_get Fehler: " . $e->getMessage());
            }
        }

        try {
            $clients = $this->client->client_get($this->session_id, []);
            if ($clients && is_array($clients)) {
                foreach ($clients as $client) {
                    if (isset($client['client_id'])) {
                        $ids[] = $client['client_id'];
                    }
                }
            }
        } catch (Exception $e) {
            if ($this->debug_mode) {
                error_log("ISPConfig: client_get Fehler: " . $e->getMessage());
            }
        }

        if (empty($ids)) {
            if ($this->debug_mode) {
                error_log("ISPConfig: Verwende sequenzielle ID-Suche");
            }
            
            for ($i = 1; $i <= 100; $i++) {
                $ids[] = $i;
            }
        }

        return array_unique($ids);
    }

    private function getEmailsFromClients() {
        try {
            $clients = $this->client->client_get($this->session_id, []);
            
            if (!$clients || !is_array($clients)) {
                return [];
            }

            $emails = [];
            foreach ($clients as $client) {
                if (isset($client['email']) && !empty($client['email'])) {
                    $emails[] = DataMapper::mapToEmailAccount([
                        'mailuser_id' => $client['client_id'] ?? uniqid(),
                        'email' => $client['email'],
                        'login' => $client['username'] ?? $client['contact_name'] ?? '',
                        'name' => $client['contact_name'] ?? $client['company_name'] ?? '',
                        'domain' => $this->extractDomainFromEmail($client['email']),
                        'quota' => '1000',
                        'active' => 'y'
                    ]);
                }
            }

            if ($this->debug_mode) {
                error_log("ISPConfig: Aus Client-Daten extrahiert: " . count($emails) . " E-Mails");
            }

            return $emails;

        } catch (Exception $e) {
            if ($this->debug_mode) {
                error_log("ISPConfig: Client-E-Mail-Extraktion Fehler: " . $e->getMessage());
            }
            return [];
        }
    }

    private function tryAlternativeMailFunctions() {
        $alternativeFunctions = [
            // ISPConfig 3.x Varianten
            ['function' => 'mail_domain_get', 'params' => [[]]],
            ['function' => 'mail_alias_get', 'params' => [[]]],
            ['function' => 'mail_forward_get', 'params' => [[]]],
            
            // Mit spezifischen Client-IDs
            ['function' => 'mail_user_get', 'params' => [0]], // Client-ID 0
            ['function' => 'mail_user_get', 'params' => [1]], // Client-ID 1
            
            // Mit leeren Filtern
            ['function' => 'mail_user_get', 'params' => [['server_id' => 1]]],
            ['function' => 'mail_user_get', 'params' => [['sys_userid' => 1]]],
        ];

        foreach ($alternativeFunctions as $method) {
            try {
                $function = $method['function'];
                $params = array_merge([$this->session_id], $method['params']);

                if ($this->debug_mode) {
                    error_log("ISPConfig: Teste alternative Funktion {$function} mit Parametern: " . json_encode($params));
                }

                $result = call_user_func_array([$this->client, $function], $params);

                if ($result && is_array($result) && count($result) > 0) {
                    if ($this->debug_mode) {
                        error_log("ISPConfig: Alternative Funktion {$function} erfolgreich: " . count($result) . " Einträge");
                    }

                    // Transformiere verschiedene Datentypen zu E-Mail-Format
                    return $this->transformToEmailFormat($result, $function);
                }

            } catch (Exception $e) {
                if ($this->debug_mode) {
                    error_log("ISPConfig: Alternative Funktion {$method['function']} Fehler: " . $e->getMessage());
                }
                continue;
            }
        }

        return [];
    }

    private function transformToEmailFormat($data, $sourceFunction) {
        $emails = [];

        foreach ($data as $item) {
            $email = null;

            switch ($sourceFunction) {
                case 'mail_domain_get':
                    // Domain-Daten zu Standard-E-Mail transformieren
                    if (isset($item['domain'])) {
                        $email = [
                            'mailuser_id' => $item['domain_id'] ?? uniqid(),
                            'email' => 'postmaster@' . $item['domain'],
                            'login' => 'postmaster',
                            'name' => 'Postmaster',
                            'domain' => $item['domain'],
                            'quota' => '1000',
                            'active' => $item['active'] ?? 'y'
                        ];
                    }
                    break;

                case 'mail_alias_get':
                    // Alias-Daten transformieren
                    if (isset($item['source']) && isset($item['destination'])) {
                        $email = [
                            'mailuser_id' => $item['mail_forwarding_id'] ?? uniqid(),
                            'email' => $item['source'],
                            'login' => explode('@', $item['source'])[0] ?? '',
                            'name' => 'Alias',
                            'domain' => explode('@', $item['source'])[1] ?? '',
                            'quota' => '0',
                            'active' => $item['active'] ?? 'y'
                        ];
                    }
                    break;

                case 'mail_user_get':
                default:
                    // Standard E-Mail-User Daten
                    if (isset($item['email'])) {
                        $email = $item;
                    }
                    break;
            }

            if ($email) {
                $emails[] = DataMapper::mapToEmailAccount($email);
            }
        }

        return $emails;
    }

    private function extractDomainFromEmail($email) {
        $parts = explode('@', $email);
        return isset($parts[1]) ? $parts[1] : '';
    }

    public function testMailUserGetParameters() {
        $testCases = [
            // Test 1: Mit Filter-Array
            ['filter' => ['active' => 'y']],
            ['filter' => []],
            
            // Test 2: Mit Primary-ID
            ['primary_id' => 0],
            ['primary_id' => 1],
            ['primary_id' => ''],
            
            // Test 3: Mit Server-ID
            ['server_id' => 1],
            
            // Test 4: Mit Client-ID
            ['client_id' => 1],
            ['client_id' => 0],
            
            // Test 5: Kombinationen
            ['sys_userid' => 1, 'active' => 'y'],
        ];

        $results = [];

        foreach ($testCases as $index => $testCase) {
            try {
                if ($this->debug_mode) {
                    error_log("ISPConfig: Teste mail_user_get Fall " . ($index + 1) . ": " . json_encode($testCase));
                }

                $result = $this->client->mail_user_get($this->session_id, $testCase);
                
                $results[$index] = [
                    'parameters' => $testCase,
                    'success' => true,
                    'result_type' => gettype($result),
                    'result_count' => is_array($result) ? count($result) : 0,
                    'sample_data' => is_array($result) && count($result) > 0 ? $result[0] : $result
                ];

                if ($this->debug_mode) {
                    error_log("ISPConfig: Test Fall " . ($index + 1) . " erfolgreich: " . 
                        (is_array($result) ? count($result) . " Einträge" : "Einzelresultat"));
                }

            } catch (Exception $e) {
                $results[$index] = [
                    'parameters' => $testCase,
                    'success' => false,
                    'error' => $e->getMessage()
                ];

                if ($this->debug_mode) {
                    error_log("ISPConfig: Test Fall " . ($index + 1) . " Fehler: " . $e->getMessage());
                }
            }
        }

        return $results;
    }

    private function getAvailableSoapFunctions() {
        $functions = [];
        
        try {
            // Methode 1: __getFunctions()
            if (method_exists($this->client, '__getFunctions')) {
                $soapFunctions = $this->client->__getFunctions();
                if (is_array($soapFunctions)) {
                    $functions = array_merge($functions, $soapFunctions);
                }
            }
        } catch (Exception $e) {
            if ($this->debug_mode) {
                error_log("ISPConfig: __getFunctions() fehlgeschlagen: " . $e->getMessage());
            }
        }

        // Methode 2: Bekannte ISPConfig-Funktionen testen
        $knownFunctions = [
            // Mail-Funktionen (verschiedene ISPConfig-Versionen)
            'mail_user_get', 'mail_user_add', 'mail_user_update', 'mail_user_delete',
            'mail_get', 'mail_add', 'mail_update', 'mail_delete',
            'mail_mailbox_get', 'mail_mailbox_add', 'mail_mailbox_update', 'mail_mailbox_delete',
            'mail_domain_get', 'mail_domain_add', 'mail_domain_update', 'mail_domain_delete',
            'mail_alias_get', 'mail_alias_add', 'mail_alias_update', 'mail_alias_delete',
            'mail_forward_get', 'mail_forward_add', 'mail_forward_update', 'mail_forward_delete',
            
            // Client-Funktionen
            'client_get', 'client_get_all', 'client_add', 'client_update', 'client_delete',
            'client_get_by_username', 'client_get_by_groupid',
            
            // Server-Funktionen
            'server_get', 'server_get_serverid_by_ip', 'server_ip_get', 'server_ip_add',
            
            // Allgemeine Funktionen
            'login', 'logout', 'get_function_list'
        ];

        foreach ($knownFunctions as $func) {
            if (method_exists($this->client, $func)) {
                $functions[] = $func;
            }
        }

        return array_unique($functions);
    }

    /**
     * Findet Mail-relevante Funktionen
     */
    private function findMailFunctions($functions) {
        $mailFunctions = [];
        
        foreach ($functions as $func) {
            // Suche nach Funktionen die "mail" enthalten und "get" haben
            if (stripos($func, 'mail') !== false && stripos($func, 'get') !== false) {
                $mailFunctions[] = $func;
            }
        }

        // Priorisierung der Funktionen nach Wahrscheinlichkeit
        $priorityOrder = [
            'mail_user_get',
            'mail_get',
            'mail_mailbox_get',
            'mail_domain_get',
            'mail_alias_get',
            'mail_forward_get'
        ];

        $sortedFunctions = [];
        foreach ($priorityOrder as $priority) {
            if (in_array($priority, $mailFunctions)) {
                $sortedFunctions[] = $priority;
            }
        }

        // Füge restliche gefundene Funktionen hinzu
        foreach ($mailFunctions as $func) {
            if (!in_array($func, $sortedFunctions)) {
                $sortedFunctions[] = $func;
            }
        }

        return $sortedFunctions;
    }

    /**
     * Findet Client-relevante Funktionen
     */
    private function findClientFunctions($functions) {
        $clientFunctions = [];
        
        foreach ($functions as $func) {
            if (stripos($func, 'client') !== false && stripos($func, 'get') !== false) {
                $clientFunctions[] = $func;
            }
        }

        return $clientFunctions;
    }

    /**
     * Ruft eine Mail-Funktion mit verschiedenen Parameter-Kombinationen auf
     */
    private function callMailFunction($function, $filter) {
        $paramCombinations = [
            // Standard-Parameter
            [$this->session_id, $filter],
            [$this->session_id, []],
            
            // Mit Client-ID
            [$this->session_id, 0, $filter],
            [$this->session_id, 1, $filter],
            [$this->session_id, 0, []],
            [$this->session_id, 1, []],
            
            // Nur Session-ID
            [$this->session_id],
            
            // Mit Primary-ID
            [$this->session_id, ['primary_id' => '']],
            [$this->session_id, ['primary_id' => 0]],
        ];

        foreach ($paramCombinations as $params) {
            try {
                if ($this->debug_mode) {
                    error_log("ISPConfig: Teste {$function} mit Parametern: " . json_encode($params));
                }

                $result = call_user_func_array([$this->client, $function], $params);
                
                if ($result && is_array($result) && count($result) > 0) {
                    return $result;
                }

            } catch (Exception $e) {
                if ($this->debug_mode) {
                    error_log("ISPConfig: {$function} Parameter-Kombination fehlgeschlagen: " . $e->getMessage());
                }
                continue;
            }
        }

        return null;
    }

    /**
     * Ruft eine Client-Funktion auf
     */
    private function callClientFunction($function) {
        try {
            return $this->client->$function($this->session_id);
        } catch (Exception $e) {
            if ($this->debug_mode) {
                error_log("ISPConfig: Client-Funktion {$function} fehlgeschlagen: " . $e->getMessage());
            }
            return null;
        }
    }

    /**
     * Extrahiert E-Mail-Informationen aus Client-Daten
     */
    private function extractEmailsFromClients($clients) {
        $emails = [];
        
        foreach ($clients as $client) {
            // Versuche E-Mail-Informationen aus Client-Daten zu extrahieren
            if (isset($client['email']) && !empty($client['email'])) {
                $emails[] = [
                    'mailuser_id' => $client['client_id'] ?? $client['id'] ?? uniqid(),
                    'email' => $client['email'],
                    'login' => $client['username'] ?? $client['contact_name'] ?? '',
                    'name' => $client['contact_name'] ?? $client['company_name'] ?? '',
                    'domain' => $this->extractDomainFromEmail($client['email']),
                    'quota' => '1000', // Default
                    'postfix' => 'y'
                ];
            }
        }

        return $emails;
    }

    /**
     * Direkte SOAP-Calls für spezielle ISPConfig-Versionen
     */
    private function tryDirectSoapCalls() {
        // ISPConfig 3.0.x direkte Calls
        $directMethods = [
            'get_mail_users',
            'list_mail_users',
            'get_all_mail_users',
            'fetch_mail_users'
        ];

        foreach ($directMethods as $method) {
            try {
                if (method_exists($this->client, $method)) {
                    $result = $this->client->$method($this->session_id);
                    if ($result && is_array($result)) {
                        if ($this->debug_mode) {
                            error_log("ISPConfig: Direkter Call {$method} erfolgreich");
                        }
                        return array_map(function($email) {
                            return DataMapper::mapToEmailAccount($email);
                        }, $result);
                    }
                }
            } catch (Exception $e) {
                continue;
            }
        }

        return null;
    }


    public function getClients($filter = []) {
        try {
            $clients = $this->client->client_get($this->session_id, $filter);
            //$this->logRequest('/client/get', 'GET', $clients !== false);
            return $clients ?: [];
        } catch (Exception $e) {
            error_log('Error getting clients: ' . $e->getMessage());
            return [];
        }
    }

    public function getServerConfig() {
        try {
            $config = $this->client->server_get($this->session_id, 1);
            //$this->logRequest('/server/get', 'GET', $config !== false);
            return $config ?: [];
        } catch (Exception $e) {
            error_log('Error getting server config: ' . $e->getMessage());
            return [];
        }
    }

    public function makeRequest($method, $url, $data = null) {
        // ISPConfig uses SOAP, so this is handled in the specific methods above
        return true;
    }
}

// ISPCONFIG POST CLASS
class ISPConfigPost extends ISPConfigGet {

    public function createWebsite($websiteData) {
        try {
            $params = [
                'server_id' => 1,
                'ip_address' => $websiteData['ip'],
                'domain' => $websiteData['domain'],
                'type' => 'vhost',
                'parent_domain_id' => 0,
                'web_folder' => '',
                'active' => 'y',
                'document_root' => '/var/www/' . $websiteData['domain'],
                'system_user' => $websiteData['user'],
                'system_group' => $websiteData['group'],
                'hd_quota' => $websiteData['quota'],
                'traffic_quota' => $websiteData['traffic'],
                'cgi' => 'y',
                'ssi' => 'y',
                'perl' => 'y',
                'ruby' => 'y',
                'python' => 'y',
                'suexec' => 'y',
                'errordocs' => 1,
                'is_subdomainwww' => 1
            ];

            $result = $this->client->sites_web_domain_add($this->session_id, 1, $params);
            //$this->logRequest('/sites/web_domain/add', 'POST', $result !== false);
            return $result;
        } catch (Exception $e) {
            error_log('Website creation failed: ' . $e->getMessage());
            return false;
        }
    }

    public function editWebsite($domainId, $websiteData) {
        try {
            $result = $this->client->sites_web_domain_update($this->session_id, 1, $domainId, $websiteData);
            //$this->logRequest("/sites/web_domain/$domainId", 'PUT', $result !== false);
            return $result;
        } catch (Exception $e) {
            error_log('Website edit failed: ' . $e->getMessage());
            return false;
        }
    }

    public function deleteWebsite($domainId) {
        try {
            $result = $this->client->sites_web_domain_delete($this->session_id, $domainId);
            //$this->logRequest("/sites/web_domain/$domainId", 'DELETE', $result !== false);
            return $result;
        } catch (Exception $e) {
            error_log('Website deletion failed: ' . $e->getMessage());
            return false;
        }
    }

    public function createDatabase($dbData) {
        try {
            $params = [
                'server_id' => 1,
                'type' => 'mysql',
                'database_name' => $dbData['name'],
                'database_user' => $dbData['user'],
                'database_password' => $dbData['password'],
                'database_charset' => 'utf8',
                'remote_access' => 'n',
                'remote_ips' => '',
                'active' => 'y'
            ];

            $result = $this->client->sites_database_add($this->session_id, 1, $params);
            //$this->logRequest('/sites/database/add', 'POST', $result !== false);
            return $result;
        } catch (Exception $e) {
            error_log('Database creation failed: ' . $e->getMessage());
            return false;
        }
    }

    public function editDatabase($databaseId, $dbData) {
        try {
            $result = $this->client->sites_database_update($this->session_id, 1, $databaseId, $dbData);
            //$this->logRequest("/sites/database/$databaseId", 'PUT', $result !== false);
            return $result;
        } catch (Exception $e) {
            error_log('Database edit failed: ' . $e->getMessage());
            return false;
        }
    }

    public function deleteDatabase($databaseId) {
        try {
            $result = $this->client->sites_database_delete($this->session_id, $databaseId);
            //$this->logRequest("/sites/database/$databaseId", 'DELETE', $result !== false);
            return $result;
        } catch (Exception $e) {
            error_log('Database deletion failed: ' . $e->getMessage());
            return false;
        }
    }

    public function createEmailAccount($emailData) {
        try {
            $params = [
                'server_id' => 1,
                'email' => $emailData['email'],
                'login' => $emailData['login'],
                'password' => $emailData['password'],
                'name' => $emailData['name'],
                'uid' => 5000,
                'gid' => 5000,
                'maildir' => '/var/vmail/' . $emailData['domain'] . '/' . $emailData['user'],
                'quota' => $emailData['quota'],
                'cc' => '',
                'homedir' => '/var/vmail',
                'autoresponder' => 'n',
                'postfix' => 'y',
                'access' => 'y',
                'disableimap' => 'n',
                'disablepop3' => 'n',
                'disabledeliver' => 'n',
                'disablesmtp' => 'n'
            ];

            $result = $this->client->mail_user_add($this->session_id, 1, $params);
            //$this->logRequest('/mail/user/add', 'POST', $result !== false);
            return $result;
        } catch (Exception $e) {
            error_log('Email creation failed: ' . $e->getMessage());
            return false;
        }
    }

    public function editEmailAccount($mailuserId, $emailData) {
        try {
            $result = $this->client->mail_user_update($this->session_id, 1, $mailuserId, $emailData);
            //$this->logRequest("/mail/user/$mailuserId", 'PUT', $result !== false);
            return $result;
        } catch (Exception $e) {
            error_log('Email edit failed: ' . $e->getMessage());
            return false;
        }
    }

    public function deleteEmailAccount($mailuserId) {
        try {
            $result = $this->client->mail_user_delete($this->session_id, $mailuserId);
            //$this->logRequest("/mail/user/$mailuserId", 'DELETE', $result !== false);
            return $result;
        } catch (Exception $e) {
            error_log('Email deletion failed: ' . $e->getMessage());
            return false;
        }
    }
    
    public function createClient($clientData) {
        try {
            $result = $this->client->client_add($this->session_id, $clientData);
            //$this->logRequest('/client/add', 'POST', $result !== false);
            return $result;
        } catch (Exception $e) {
            error_log('Client creation failed: ' . $e->getMessage());
            return false;
        }
    }
    
    public function updateClient($clientId, $clientData) {
        try {
            $result = $this->client->client_update($this->session_id, $clientId, $clientData);
            //$this->logRequest("/client/$clientId", 'PUT', $result !== false);
            return $result;
        } catch (Exception $e) {
            error_log('Client update failed: ' . $e->getMessage());
            return false;
        }
    }
    
    public function deleteClient($clientId) {
        try {
            $result = $this->client->client_delete($this->session_id, $clientId);
            //$this->logRequest("/client/$clientId", 'DELETE', $result !== false);
            return $result;
        } catch (Exception $e) {
            error_log('Client deletion failed: ' . $e->getMessage());
            return false;
        }
    }
}

// OVH GET CLASS
class OVHGet extends BaseAPI {
    private $application_key;
    private $application_secret;
    private $consumer_key;
    private $endpoint;

    public function __construct() {
        $this->application_key = Config::OVH_APPLICATION_KEY;
        $this->application_secret = Config::OVH_APPLICATION_SECRET;
        $this->consumer_key = Config::OVH_CONSUMER_KEY;
        $this->endpoint = Config::OVH_ENDPOINT;
    }

    protected function authenticate() {
        // OVH authentication is handled in makeRequest with signatures
        return true;
    }

    public function getDomains() {
        $domains = $this->makeRequest('GET', 'https://eu.api.ovh.com/1.0/domain');
        $domainDetails = [];

        if ($domains && is_array($domains)) {
            foreach ($domains as $domain) {
                $details = $this->getDomain($domain);
                if ($details) {
                    $domainDetails[] = $details;
                }
            }
        }

        //$this->logRequest('/domain', 'GET', !empty($domainDetails));
        return $domainDetails;
    }

    public function getDomain($domain) {
        $url = "https://eu.api.ovh.com/1.0/domain/$domain";
        $response = $this->makeRequest('GET', $url);
        //$this->logRequest("/domain/$domain", 'GET', $response !== false);

        if ($response) {
            return DataMapper::mapToDomain($response);
        }

        return null;
    }

    public function getDomainZone($domain) {
        $url = "https://eu.api.ovh.com/1.0/domain/zone/$domain";
        $response = $this->makeRequest('GET', $url);
        //$this->logRequest("/domain/zone/$domain", 'GET', $response !== false);
        return $response;
    }

    public function getDomainZoneRecords($domain) {
        $url = "https://eu.api.ovh.com/1.0/domain/zone/$domain/record";
        $response = $this->makeRequest('GET', $url);
        //$this->logRequest("/domain/zone/$domain/record", 'GET', $response !== false);
        return $response ?: [];
    }

    public function getVPSList() {
        $vpsList = $this->makeRequest('GET', 'https://eu.api.ovh.com/1.0/vps');
        $vpsDetails = [];

        if ($vpsList && is_array($vpsList)) {
            foreach ($vpsList as $vps) {
                $details = $this->getVPS($vps);
                if ($details) {
                    $vpsDetails[] = $details;
                }
            }
        }

        //$this->logRequest('/vps', 'GET', !empty($vpsDetails));
        return $vpsDetails;
    }

    public function getVPS($vpsName) {
        $url = "https://eu.api.ovh.com/1.0/vps/$vpsName";
        $details = $this->makeRequest('GET', $url);

        if ($details) {
            // IP-Adressen abrufen
            $ipsUrl = "https://eu.api.ovh.com/1.0/vps/$vpsName/ips";
            $ips = $this->makeRequest('GET', $ipsUrl);
            $details['ips'] = $ips ?: [];

            // MAC-Adresse für jede IP abrufen
            $macAddresses = [];
            if ($ips) {
                foreach ($ips as $ip) {
                    $ipInfo = $this->makeRequest('GET', "https://eu.api.ovh.com/1.0/vps/$vpsName/ips/$ip");
                    if ($ipInfo && isset($ipInfo['macAddress'])) {
                        $macAddresses[$ip] = $ipInfo['macAddress'];
                    }
                }
            }
            $details['mac_addresses'] = $macAddresses;

            //$this->logRequest("/vps/$vpsName", 'GET', true);
            return DataMapper::mapToVPS($details);
        }

        //$this->logRequest("/vps/$vpsName", 'GET', false);
        return null;
    }

    public function getVPSIPs($vpsName) {
        $url = "https://eu.api.ovh.com/1.0/vps/$vpsName/ips";
        $response = $this->makeRequest('GET', $url);
        //$this->logRequest("/vps/$vpsName/ips", 'GET', $response !== false);
        return $response ?: [];
    }

    public function getVPSIPDetails($vpsName, $ip) {
        $url = "https://eu.api.ovh.com/1.0/vps/$vpsName/ips/$ip";
        $response = $this->makeRequest('GET', $url);
        //$this->logRequest("/vps/$vpsName/ips/$ip", 'GET', $response !== false);
        return $response;
    }

    public function getDedicatedServers() {
        $servers = $this->makeRequest('GET', 'https://eu.api.ovh.com/1.0/dedicated/server');
        //$this->logRequest('/dedicated/server', 'GET', $servers !== false);
        return $servers ?: [];
    }

    public function getDedicatedServer($serverName) {
        $url = "https://eu.api.ovh.com/1.0/dedicated/server/$serverName";
        $response = $this->makeRequest('GET', $url);
        //$this->logRequest("/dedicated/server/$serverName", 'GET', $response !== false);
        return $response;
    }

     
    // VIRTUAL MAC METHODS
     

    /**
     * Holt alle Virtual MAC-Adressen für einen bestimmten Dedicated Server
     */
    public function getVirtualMacAddresses($serviceName) {
        $url = "https://eu.api.ovh.com/1.0/dedicated/server/$serviceName/virtualMac";
        $response = $this->makeRequest('GET', $url);
        //$this->logRequest("/dedicated/server/$serviceName/virtualMac", 'GET', $response !== false);
        return $response ?: [];
    }

    /**
     * Holt Details zu einer bestimmten Virtual MAC-Adresse
     */
    public function getVirtualMacDetails($serviceName, $macAddress) {
        $url = "https://eu.api.ovh.com/1.0/dedicated/server/$serviceName/virtualMac/$macAddress";
        $response = $this->makeRequest('GET', $url);
        //$this->logRequest("/dedicated/server/$serviceName/virtualMac/$macAddress", 'GET', $response !== false);
        
        if ($response) {
            return DataMapper::mapToVirtualMac($response, $serviceName, $macAddress);
        }
        return null;
    }

    /**
     * Holt alle Virtual MAC-Adressen mit Details für einen Service
     */
    public function getAllVirtualMacDetails($serviceName) {
        $macAddresses = $this->getVirtualMacAddresses($serviceName);
        $detailedMacs = [];

        if (!empty($macAddresses)) {
            foreach ($macAddresses as $macAddress) {
                $details = $this->getVirtualMacDetails($serviceName, $macAddress);
                if ($details) {
                    $detailedMacs[] = $details;
                }
            }
        }

        return $detailedMacs;
    }

    /**
     * Holt alle IPs für eine Virtual MAC-Adresse
     */
    public function getVirtualMacIPs($serviceName, $macAddress) {
        $url = "https://eu.api.ovh.com/1.0/dedicated/server/$serviceName/virtualMac/$macAddress/virtualAddress";
        $response = $this->makeRequest('GET', $url);
        //$this->logRequest("/dedicated/server/$serviceName/virtualMac/$macAddress/virtualAddress", 'GET', $response !== false);
        return $response ?: [];
    }

    /**
     * Holt Details zu einer bestimmten IP einer Virtual MAC
     */
    public function getVirtualMacIPDetails($serviceName, $macAddress, $ipAddress) {
        $url = "https://eu.api.ovh.com/1.0/dedicated/server/$serviceName/virtualMac/$macAddress/virtualAddress/$ipAddress";
        $response = $this->makeRequest('GET', $url);
        //$this->logRequest("/dedicated/server/$serviceName/virtualMac/$macAddress/virtualAddress/$ipAddress", 'GET', $response !== false);
        return $response;
    }

    /**
     * Holt Reverse-DNS Informationen für eine IP-Adresse
     */
    public function getIPReverse($ipAddress) {
        $encodedIp = urlencode($ipAddress);
        $url = "https://eu.api.ovh.com/1.0/ip/$encodedIp/reverse";
        $response = $this->makeRequest('GET', $url);
        //$this->logRequest("/ip/$ipAddress/reverse", 'GET', $response !== false);
        return $response ?: [];
    }

    /**
     * Holt Details zu einem bestimmten Reverse-DNS Eintrag
     */
    public function getIPReverseDetails($ipAddress, $reverseIP) {
        $encodedIp = urlencode($ipAddress);
        $encodedReverse = urlencode($reverseIP);
        $url = "https://eu.api.ovh.com/1.0/ip/$encodedIp/reverse/$encodedReverse";
        $response = $this->makeRequest('GET', $url);
        //$this->logRequest("/ip/$ipAddress/reverse/$reverseIP", 'GET', $response !== false);
        return $response;
    }

    /**
     * Holt alle Virtual MAC-Adressen für alle Dedicated Server
     */
    public function getAllVirtualMacAddresses() {
        $servers = $this->getDedicatedServers();
        $allVirtualMacs = [];

        if (!empty($servers)) {
            foreach ($servers as $serverName) {
                $virtualMacs = $this->getAllVirtualMacDetailsWithIPs($serverName);
                if (!empty($virtualMacs)) {
                    $allVirtualMacs[$serverName] = $virtualMacs;
                }
            }
        }

        return $allVirtualMacs;
    }

    /**
     * Holt alle Virtual MAC-Adressen mit ihren IPs und Reverse-DNS für einen Service
     */
    public function getAllVirtualMacDetailsWithIPs($serviceName) {
        $virtualMacs = $this->getAllVirtualMacDetails($serviceName);
        
        foreach ($virtualMacs as &$virtualMac) {
            // IPs für diese MAC-Adresse holen
            $ips = $this->getVirtualMacIPs($serviceName, $virtualMac->macAddress);
            $virtualMac->ips = [];
            $virtualMac->reverseEntries = [];

            if (!empty($ips)) {
                foreach ($ips as $ip) {
                    // IP Details holen
                    $ipDetails = $this->getVirtualMacIPDetails($serviceName, $virtualMac->macAddress, $ip);
                    if ($ipDetails) {
                        $virtualMac->ips[] = $ipDetails;
                    }

                    // Reverse-DNS für diese IP holen
                    $reverseEntries = $this->getIPReverse($ip);
                    if (!empty($reverseEntries)) {
                        $virtualMac->reverseEntries[$ip] = $reverseEntries;
                    }
                }
            }
        }

        return $virtualMacs;
    }

    public function getFailoverIPs() {
        $url = "https://eu.api.ovh.com/1.0/ip";
        $response = $this->makeRequest('GET', $url);
        //$this->logRequest("/ip", 'GET', $response !== false);
        return $response ?: [];
    }

    public function getFailoverIPDetails($ip) {
        // URL-encode the IP address to handle special characters like '/'
        $encodedIp = urlencode($ip);
        $url = "https://eu.api.ovh.com/1.0/ip/{$encodedIp}";
        $response = $this->makeRequest('GET', $url);
        //$this->logRequest("/ip/{$ip}", 'GET', $response !== false);
        return $response ?: null;
    }

    public function makeRequest($method, $url, $data = null) {
        $timestamp = time();
        $body = $data ? json_encode($data) : '';

        $signature = '$1$' . sha1(
            $this->application_secret . '+' .
            $this->consumer_key . '+' .
            $method . '+' .
            $url . '+' .
            $body . '+' .
            $timestamp
        );

        $headers = [
            'X-Ovh-Application: ' . $this->application_key,
            'X-Ovh-Consumer: ' . $this->consumer_key,
            'X-Ovh-Signature: ' . $signature,
            'X-Ovh-Timestamp: ' . $timestamp,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 30
        ]);

        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true);
        }

        return false;
    }

    /**
     * Holt und gibt alle Reverse-DNS-Details für alle IPs dynamisch aus OVH zurück.
     * Beispiel-Ausgabe:
    **/
    public function getAllIPReverseDetails() {
        $result = [];
        // Schritt 1: Alle IPs abrufen
        $url = "https://eu.api.ovh.com/1.0/ip";
        $ipList = $this->makeRequest('GET', $url);
        if (is_array($ipList)) {
            foreach ($ipList as $ip) {
                $ipEncoded = str_replace('/', '%2F', $ip);
                // Schritt 2: Reverse-IPs für jede IP abrufen
                $reverseList = $this->makeRequest('GET', "https://eu.api.ovh.com/1.0/ip/{$ipEncoded}/reverse");
                if (is_array($reverseList) && count($reverseList) > 0) {
                    foreach ($reverseList as $ipReverse) {
                        // Schritt 3: Details für jede Reverse-IP abrufen
                        $details = $this->makeRequest('GET', "https://eu.api.ovh.com/1.0/ip/{$ipEncoded}/reverse/{$ipReverse}");
                        $result[$ip][$ipReverse] = $details;
                    }
                } else {
                    $result[$ip] = "Keine Reverse-IPs gefunden";
                }
            }
        } else {
            $result = "Fehler beim Abrufen der IP-Liste";
        }
        return $result;
        //$this->logRequest("/ip/{$ipEncoded}/reverse/{$ipReverse}", 'GET', $response !== false);
    }
}

// OVH POST CLASS - ERWEITERT FÜR VIRTUAL MAC
class OVHPost extends OVHGet {

    public function orderDomain($domain, $duration = 1) {
        $url = "https://eu.api.ovh.com/1.0/order/domain/zone/$domain";

        $data = [
            'duration' => "P{$duration}Y"
        ];

        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("/order/domain/zone/$domain", 'POST', $response !== false);
        return $response;
    }

    public function editDomain($domain, $domainData) {
        $url = "https://eu.api.ovh.com/1.0/domain/$domain";
        $response = $this->makeRequest('PUT', $url, $domainData);
        //$this->logRequest("/domain/$domain", 'PUT', $response !== false);
        return $response;
    }

    public function deleteDomain($domain) {
        $url = "https://eu.api.ovh.com/1.0/domain/$domain";
        $response = $this->makeRequest('DELETE', $url);
        //$this->logRequest("/domain/$domain", 'DELETE', $response !== false);
        return $response;
    }

    public function createDNSRecord($domain, $recordData) {
        $url = "https://eu.api.ovh.com/1.0/domain/zone/$domain/record";
        $response = $this->makeRequest('POST', $url, $recordData);
        //$this->logRequest("/domain/zone/$domain/record", 'POST', $response !== false);
        return $response;
    }

    public function editDNSRecord($domain, $recordId, $recordData) {
        $url = "https://eu.api.ovh.com/1.0/domain/zone/$domain/record/$recordId";
        $response = $this->makeRequest('PUT', $url, $recordData);
        //$this->logRequest("/domain/zone/$domain/record/$recordId", 'PUT', $response !== false);
        return $response;
    }

    public function deleteDNSRecord($domain, $recordId) {
        $url = "https://eu.api.ovh.com/1.0/domain/zone/$domain/record/$recordId";
        $response = $this->makeRequest('DELETE', $url);
        //$this->logRequest("/domain/zone/$domain/record/$recordId", 'DELETE', $response !== false);
        return $response;
    }

    public function refreshDNSZone($domain) {
        $url = "https://eu.api.ovh.com/1.0/domain/zone/$domain/refresh";
        $response = $this->makeRequest('POST', $url);
        //$this->logRequest("/domain/zone/$domain/refresh", 'POST', $response !== false);
        return $response;
    }

    public function rebootVPS($vpsName) {
        $url = "https://eu.api.ovh.com/1.0/vps/$vpsName/reboot";
        $response = $this->makeRequest('POST', $url);
        //$this->logRequest("/vps/$vpsName/reboot", 'POST', $response !== false);
        return $response;
    }

    public function stopVPS($vpsName) {
        $url = "https://eu.api.ovh.com/1.0/vps/$vpsName/stop";
        $response = $this->makeRequest('POST', $url);
        //$this->logRequest("/vps/$vpsName/stop", 'POST', $response !== false);
        return $response;
    }

    public function startVPS($vpsName) {
        $url = "https://eu.api.ovh.com/1.0/vps/$vpsName/start";
        $response = $this->makeRequest('POST', $url);
        //$this->logRequest("/vps/$vpsName/start", 'POST', $response !== false);
        return $response;
    }

     
    // VIRTUAL MAC POST METHODS
     

    /**
     * Erstellt eine neue Virtual MAC-Adresse
     */
    public function createVirtualMac($serviceName, $virtualNetworkInterface, $type = 'ovh') {
        $url = "https://eu.api.ovh.com/1.0/dedicated/server/$serviceName/virtualMac";
        
        $data = [
            'virtualNetworkInterface' => $virtualNetworkInterface,
            'type' => $type
        ];

        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("/dedicated/server/$serviceName/virtualMac", 'POST', $response !== false);
        return $response;
    }

    /**
     * Löscht eine Virtual MAC-Adresse
     */
    public function deleteVirtualMac($serviceName, $macAddress) {
        $url = "https://eu.api.ovh.com/1.0/dedicated/server/$serviceName/virtualMac/$macAddress";
        $response = $this->makeRequest('DELETE', $url);
        //$this->logRequest("/dedicated/server/$serviceName/virtualMac/$macAddress", 'DELETE', $response !== false);
        return $response;
    }

    /**
     * Fügt eine IP-Adresse zu einer Virtual MAC hinzu
     */
    public function addVirtualMacIP($serviceName, $macAddress, $ipAddress, $virtualNetworkInterface) {
        $url = "https://eu.api.ovh.com/1.0/dedicated/server/$serviceName/virtualMac/$macAddress/virtualAddress";
        
        $data = [
            'ipAddress' => $ipAddress,
            'virtualNetworkInterface' => $virtualNetworkInterface
        ];

        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("/dedicated/server/$serviceName/virtualMac/$macAddress/virtualAddress", 'POST', $response !== false);
        return $response;
    }

    /**
     * Entfernt eine IP-Adresse von einer Virtual MAC
     */
    public function removeVirtualMacIP($serviceName, $macAddress, $ipAddress) {
        $url = "https://eu.api.ovh.com/1.0/dedicated/server/$serviceName/virtualMac/$macAddress/virtualAddress/$ipAddress";
        $response = $this->makeRequest('DELETE', $url);
        //$this->logRequest("/dedicated/server/$serviceName/virtualMac/$macAddress/virtualAddress/$ipAddress", 'DELETE', $response !== false);
        return $response;
    }

    /**
     * Erstellt einen Reverse-DNS Eintrag
     */
    public function createIPReverse($ipAddress, $reverse) {
        $encodedIp = urlencode($ipAddress);
        $url = "https://eu.api.ovh.com/1.0/ip/$encodedIp/reverse";
        
        $data = [
            'ipReverse' => $ipAddress,
            'reverse' => $reverse
        ];

        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("/ip/$ipAddress/reverse", 'POST', $response !== false);
        return $response;
    }

    /**
     * Aktualisiert einen Reverse-DNS Eintrag
     */
    public function updateIPReverse($ipAddress, $reverseIP, $newReverse) {
        $encodedIp = urlencode($ipAddress);
        $encodedReverse = urlencode($reverseIP);
        $url = "https://eu.api.ovh.com/1.0/ip/$encodedIp/reverse/$encodedReverse";
        
        $data = [
            'reverse' => $newReverse
        ];

        $response = $this->makeRequest('PUT', $url, $data);
        //$this->logRequest("/ip/$ipAddress/reverse/$reverseIP", 'PUT', $response !== false);
        return $response;
    }

    /**
     * Löscht einen Reverse-DNS Eintrag
     */
    public function deleteIPReverse($ipAddress, $reverseIP) {
        $encodedIp = urlencode($ipAddress);
        $encodedReverse = urlencode($reverseIP);
        $url = "https://eu.api.ovh.com/1.0/ip/$encodedIp/reverse/$encodedReverse";
        $response = $this->makeRequest('DELETE', $url);
        //$this->logRequest("/ip/$ipAddress/reverse/$reverseIP", 'DELETE', $response !== false);
        return $response;
    }
}

// OGP GET CLASS
class OGPGet extends BaseAPI {
    protected $token;

    public function __construct() {
        $this->host = Config::OGP_HOST;
        $this->user = Config::OGP_USER;
        $this->password = Config::OGP_PASSWORD;
        $this->token = Config::OGP_TOKEN;
        // Don't test token automatically to avoid hanging
        // $this->testToken();
    }

    protected function authenticate() {
        // Token erstellen für OGP API
        $url = $this->host . "/ogp_api.php?token/create/" . urlencode($this->user) . "/" . urlencode($this->password);
        $response = $this->makeRequest('GET', $url);
        
        if ($response && isset($response['message'])) {
            $this->token = $response['message'];
        } else {
            throw new Exception("OGP Authentifizierung fehlgeschlagen");
        }
    }

    public function testToken() {
        $url = $this->host . "/ogp_api.php?token/test/" . urlencode($this->token);
        $response = $this->makeRequest('GET', $url);
        //$this->logRequest('token/test', 'GET', $response !== false);
        return $response;
    }

     
    // REMOTE SERVERS
     
    public function getServerList() {
        $url = $this->host . "/ogp_api.php?server/list";
        $data = ['token' => $this->token];
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest('server/list', 'POST', $response !== false);
        return $response;
    }

    public function getServerStatus($remoteServerId) {
        $url = $this->host . "/ogp_api.php?server/status";
        $data = ['token' => $this->token, 'remote_server_id' => $remoteServerId];
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("server/status/$remoteServerId", 'POST', $response !== false);
        return $response;
    }

    public function getServerIPs($remoteServerId) {
        $url = $this->host . "/ogp_api.php?server/list_ips";
        $data = ['token' => $this->token, 'remote_server_id' => $remoteServerId];
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("server/list_ips/$remoteServerId", 'POST', $response !== false);
        return $response;
    }

     
    // GAME SERVERS
     
    public function getGamesList($system = 'linux', $architecture = '64') {
        $url = $this->host . "/ogp_api.php?user_games/list_games";
        $data = ['token' => $this->token, 'system' => $system, 'architecture' => $architecture];
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("user_games/list_games/$system/$architecture", 'POST', $response !== false);
        return $response;
    }

    public function getGameServers() {
        $url = $this->host . "/ogp_api.php?user_games/list_servers";
        $data = ['token' => $this->token];
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest('user_games/list_servers', 'POST', $response !== false);
        return $response;
    }

     
    // USERS
     
    public function getUsers() {
        $url = $this->host . "/ogp_api.php?user_admin/list";
        $data = ['token' => $this->token];
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest('user_admin/list', 'POST', $response !== false);
        return $response;
    }

    public function getUser($email) {
        $url = $this->host . "/ogp_api.php?user_admin/get";
        $data = ['token' => $this->token, 'email' => $email];
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("user_admin/get/$email", 'POST', $response !== false);
        return $response;
    }

    public function getUserAssigned($email) {
        $url = $this->host . "/ogp_api.php?user_admin/list_assigned";
        $data = ['token' => $this->token, 'email' => $email];
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("user_admin/list_assigned/$email", 'POST', $response !== false);
        return $response;
    }

     
    // ADDONS MANAGER
     
    public function getAddonsList() {
        $url = $this->host . "/ogp_api.php?addonsmanager/list";
        $data = ['token' => $this->token];
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest('addonsmanager/list', 'POST', $response !== false);
        return $response;
    }

     
    // PANEL SETTINGS
     
    public function getSetting($settingName) {
        $url = $this->host . "/ogp_api.php?setting/get";
        $data = ['token' => $this->token, 'setting_name' => $settingName];
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("setting/get/$settingName", 'POST', $response !== false);
        return $response;
    }

    public function makeRequest($method, $url, $data = null) {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("OGP API cURL Error: $error");
            return false;
        }
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $decoded = json_decode($response, true);
            return $decoded !== null ? $decoded : $response;
        } else {
            error_log("OGP API HTTP Error: $httpCode - $response");
            return false;
        }
    }
}

// OGP POST CLASS
class OGPPost extends OGPGet {
    
     
    // REMOTE SERVERS
     
    public function restartServer($remoteServerId) {
        $url = $this->host . "/ogp_api.php?server/restart";
        $data = ['token' => $this->token, 'remote_server_id' => $remoteServerId];
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("server/restart/$remoteServerId", 'POST', $response !== false);
        return $response;
    }

    public function createServer($serverData) {
        $url = $this->host . "/ogp_api.php?server/create";
        $data = array_merge(['token' => $this->token], $serverData);
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest('server/create', 'POST', $response !== false);
        return $response;
    }

    public function removeServer($remoteServerId) {
        $url = $this->host . "/ogp_api.php?server/remove";
        $data = ['token' => $this->token, 'remote_server_id' => $remoteServerId];
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("server/remove/$remoteServerId", 'POST', $response !== false);
        return $response;
    }

    public function addServerIP($remoteServerId, $ip) {
        $url = $this->host . "/ogp_api.php?server/add_ip";
        $data = ['token' => $this->token, 'remote_server_id' => $remoteServerId, 'ip' => $ip];
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("server/add_ip/$remoteServerId/$ip", 'POST', $response !== false);
        return $response;
    }

    public function removeServerIP($remoteServerId, $ip) {
        $url = $this->host . "/ogp_api.php?server/remove_ip";
        $data = ['token' => $this->token, 'remote_server_id' => $remoteServerId, 'ip' => $ip];
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("server/remove_ip/$remoteServerId/$ip", 'POST', $response !== false);
        return $response;
    }

    public function editServerIP($remoteServerId, $oldIp, $newIp) {
        $url = $this->host . "/ogp_api.php?server/edit_ip";
        $data = ['token' => $this->token, 'remote_server_id' => $remoteServerId, 'old_ip' => $oldIp, 'new_ip' => $newIp];
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("server/edit_ip/$remoteServerId/$oldIp/$newIp", 'POST', $response !== false);
        return $response;
    }

     
    // GAME SERVERS
     
    public function createGameServer($gameServerData) {
        $url = $this->host . "/ogp_api.php?user_games/create";
        $data = array_merge(['token' => $this->token], $gameServerData);
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest('user_games/create', 'POST', $response !== false);
        return $response;
    }

    public function cloneGameServer($cloneData) {
        $url = $this->host . "/ogp_api.php?user_games/clone";
        $data = array_merge(['token' => $this->token], $cloneData);
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest('user_games/clone', 'POST', $response !== false);
        return $response;
    }

    public function setGameServerExpiration($homeId, $timestamp) {
        $url = $this->host . "/ogp_api.php?user_games/set_expiration";
        $data = ['token' => $this->token, 'home_id' => $homeId, 'timestamp' => $timestamp];
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("user_games/set_expiration/$homeId", 'POST', $response !== false);
        return $response;
    }

     
    // USERS
     
    public function createUser($userData) {
        $url = $this->host . "/ogp_api.php?user_admin/create";
        $data = array_merge(['token' => $this->token], $userData);
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest('user_admin/create', 'POST', $response !== false);
        return $response;
    }

    public function removeUser($email) {
        $url = $this->host . "/ogp_api.php?user_admin/remove";
        $data = ['token' => $this->token, 'email' => $email];
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("user_admin/remove/$email", 'POST', $response !== false);
        return $response;
    }

    public function setUserExpiration($email, $timestamp) {
        $url = $this->host . "/ogp_api.php?user_admin/set_expiration";
        $data = ['token' => $this->token, 'email' => $email, 'timestamp' => $timestamp];
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("user_admin/set_expiration/$email", 'POST', $response !== false);
        return $response;
    }

    public function assignUser($email, $homeId, $timestamp = null) {
        $url = $this->host . "/ogp_api.php?user_admin/assign";
        $data = ['token' => $this->token, 'email' => $email, 'home_id' => $homeId];
        if ($timestamp) {
            $data['timestamp'] = $timestamp;
        }
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("user_admin/assign/$email/$homeId", 'POST', $response !== false);
        return $response;
    }

    public function removeUserAssignment($email, $homeId) {
        $url = $this->host . "/ogp_api.php?user_admin/remove_assign";
        $data = ['token' => $this->token, 'email' => $email, 'home_id' => $homeId];
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("user_admin/remove_assign/$email/$homeId", 'POST', $response !== false);
        return $response;
    }

     
    // GAME MANAGER
     
    public function startGameManager($ip, $port, $modKey) {
        $url = $this->host . "/ogp_api.php?gamemanager/start";
        $data = ['token' => $this->token, 'ip' => $ip, 'port' => $port, 'mod_key' => $modKey];
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("gamemanager/start/$ip/$port", 'POST', $response !== false);
        return $response;
    }

    public function stopGameManager($ip, $port, $modKey) {
        $url = $this->host . "/ogp_api.php?gamemanager/stop";
        $data = ['token' => $this->token, 'ip' => $port, 'port' => $port, 'mod_key' => $modKey];
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("gamemanager/stop/$ip/$port", 'POST', $response !== false);
        return $response;
    }

    public function restartGameManager($ip, $port, $modKey) {
        $url = $this->host . "/ogp_api.php?gamemanager/restart";
        $data = ['token' => $this->token, 'ip' => $ip, 'port' => $port, 'mod_key' => $modKey];
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("gamemanager/restart/$ip/$port", 'POST', $response !== false);
        return $response;
    }

    public function sendRconCommand($ip, $port, $modKey, $command) {
        $url = $this->host . "/ogp_api.php?gamemanager/rcon";
        $data = ['token' => $this->token, 'ip' => $ip, 'port' => $port, 'mod_key' => $modKey, 'command' => $command];
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("gamemanager/rcon/$ip/$port", 'POST', $response !== false);
        return $response;
    }

    public function updateGameManager($ip, $port, $modKey, $type, $manualUrl = null) {
        $url = $this->host . "/ogp_api.php?gamemanager/update";
        $data = ['token' => $this->token, 'ip' => $ip, 'port' => $port, 'mod_key' => $modKey, 'type' => $type];
        if ($manualUrl) {
            $data['manual_url'] = $manualUrl;
        }
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("gamemanager/update/$ip/$port", 'POST', $response !== false);
        return $response;
    }

     
    // LITE FILE MANAGER
     
    public function listFiles($ip, $port, $relativePath) {
        $url = $this->host . "/ogp_api.php?litefm/list";
        $data = ['token' => $this->token, 'ip' => $ip, 'port' => $port, 'relative_path' => $relativePath];
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("litefm/list/$ip/$port", 'POST', $response !== false);
        return $response;
    }

    public function getFile($ip, $port, $relativePath) {
        $url = $this->host . "/ogp_api.php?litefm/get";
        $data = ['token' => $this->token, 'ip' => $ip, 'port' => $port, 'relative_path' => $relativePath];
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("litefm/get/$ip/$port", 'POST', $response !== false);
        return $response;
    }

    public function saveFile($ip, $port, $relativePath, $contents) {
        $url = $this->host . "/ogp_api.php?litefm/save";
        $data = ['token' => $this->token, 'ip' => $ip, 'port' => $port, 'relative_path' => $relativePath, 'contents' => $contents];
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("litefm/save/$ip/$port", 'POST', $response !== false);
        return $response;
    }

    public function removeFile($ip, $port, $relativePath) {
        $url = $this->host . "/ogp_api.php?litefm/remove";
        $data = ['token' => $this->token, 'ip' => $ip, 'port' => $port, 'relative_path' => $relativePath];
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("litefm/remove/$ip/$port", 'POST', $response !== false);
        return $response;
    }

     
    // ADDONS MANAGER
     
    public function installAddon($ip, $port, $modKey, $addonId) {
        $url = $this->host . "/ogp_api.php?addonsmanager/install";
        $data = ['token' => $this->token, 'ip' => $ip, 'port' => $port, 'mod_key' => $modKey, 'addon_id' => $addonId];
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("addonsmanager/install/$ip/$port/$addonId", 'POST', $response !== false);
        return $response;
    }

     
    // STEAM WORKSHOP
     
    public function installSteamWorkshop($ip, $port, $modsList) {
        $url = $this->host . "/ogp_api.php?steam_workshop/install";
        $data = ['token' => $this->token, 'ip' => $ip, 'port' => $port, 'mods_list' => $modsList];
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("steam_workshop/install/$ip/$port", 'POST', $response !== false);
        return $response;
    }
}

// SERVICE MANAGER CLASS - ERWEITERT
class ServiceManager {
    private $proxmoxGet;
    private $proxmoxPost;
    private $ispconfigGet;
    private $ispconfigPost;
    private $ovhGet;
    private $ovhPost;
    private $ogpGet;
    private $ogpPost;

    public function __construct() {
        // Proxmox API initialisieren nur wenn aktiviert
        if (Config::PROXMOX_USEING) {
            try {
                $this->proxmoxGet = new ProxmoxGet();
                $this->proxmoxPost = new ProxmoxPost();
            } catch (Exception $e) {
                error_log("Proxmox API Initialisierung fehlgeschlagen: " . $e->getMessage());
                // Setze die Objekte auf null, damit checkAPIEnabled() sie als nicht initialisiert erkennt
                $this->proxmoxGet = null;
                $this->proxmoxPost = null;
            }
        }
        

        // ISPConfig nur initialisieren wenn SOAP verfügbar ist und aktiviert
        if (Config::ISPCONFIG_USEING && class_exists('SoapClient')) {
            try {
                $this->ispconfigGet = new ISPConfigGet();
                $this->ispconfigPost = new ISPConfigPost();
            } catch (Exception $e) {
                error_log("ISPConfig API Initialisierung fehlgeschlagen: " . $e->getMessage());
                $this->ispconfigGet = null;
                $this->ispconfigPost = null;
            }
        } else if (Config::ISPCONFIG_USEING && !class_exists('SoapClient')) {
            error_log("SOAP nicht verfügbar - ISPConfig wird nicht initialisiert");
        }
        
        // OVH API initialisieren nur wenn aktiviert
        if (Config::OVH_USEING) {
            try {
                $this->ovhGet = new OVHGet();
                $this->ovhPost = new OVHPost();
            } catch (Exception $e) {
                error_log("OVH API Initialisierung fehlgeschlagen: " . $e->getMessage());
                $this->ovhGet = null;
                $this->ovhPost = null;
            }
        }
        
        // OGP API initialisieren nur wenn aktiviert
        if (Config::OGP_USEING) {
            try {
                $this->ogpGet = new OGPGet();
                $this->ogpPost = new OGPPost();
            } catch (Exception $e) {
                error_log("OGP API Initialisierung fehlgeschlagen: " . $e->getMessage());
                $this->ogpGet = null;
                $this->ogpPost = null;
            }
        }
    }
    

    public function __log($action, $details, $status = 'info') {
        try {
            $db = Database::getInstance();
            $db->logAction($action, $details, $status);
        } catch (Exception $e) {
            // Ignoriere Logging-Fehler
        }
    }

    public function __test($name = null) {
        if($name != NULL) {
            $this->__log("Test Funktion", "Erfolgreich mit Name: $name", "success");
            return "Hallo ".$name."Test Erfolgreich!";
        } else {
            $this->__log("Test Funktion", "Fehlgeschlagen - kein Name", "error");
            return "Hallo, test fehlgeschlagen!";
        }
    }
    /**
     * Prüft ob eine API aktiviert ist
     * @param string $apiName Name der API (proxmox, ispconfig, ovh, ogp)
     * @return array|true Gibt true zurück wenn API aktiviert ist, sonst strukturierte Fehlermeldung
     */
    private function checkAPIEnabled($apiName) {
        try {
            $db = Database::getInstance();
        } catch (Exception $e) {
            // If database connection fails, skip logging but continue with API check
            error_log("Database connection failed in checkAPIEnabled: " . $e->getMessage());
            $db = null;
        }
        $apiNameLower = strtolower($apiName);
        
        switch ($apiNameLower) {
            case 'proxmox':
                if (!Config::PROXMOX_USEING) {
                    $errorResponse = [
                        'success' => false,
                        'error' => 'API_NOT_ENABLED',
                        'message' => 'Proxmox API ist in der config.inc.php deaktiviert',
                        'api' => 'proxmox',
                        'config_key' => 'PROXMOX_USEING',
                        'solution' => 'Setzen Sie PROXMOX_USEING = true in der config.inc.php'
                    ];
                    
                    return $errorResponse;
                    $this->__log("checkAPIEnabled", $errorResponse, "error");
                }
                if (!isset($this->proxmoxGet) || !isset($this->proxmoxPost)) {
                    $errorResponse = [
                        'success' => false,
                        'error' => 'API_NOT_INITIALIZED',
                        'message' => 'Proxmox API konnte nicht initialisiert werden',
                        'api' => 'proxmox',
                        'solution' => 'Überprüfen Sie die Proxmox-Konfiguration in der config.inc.php'
                    ];                    
                    return $errorResponse;
                    $this->__log("checkAPIEnabled", $errorResponse, "error");
                }
                
                break;
                
            case 'ispconfig':
                if (!Config::ISPCONFIG_USEING) {
                    $errorResponse = [
                        'success' => false,
                        'error' => 'API_NOT_ENABLED',
                        'message' => 'ISPConfig API ist in der config.inc.php deaktiviert',
                        'api' => 'ispconfig',
                        'config_key' => 'ISPCONFIG_USEING',
                        'solution' => 'Setzen Sie ISPCONFIG_USEING = true in der config.inc.php'
                    ];
                    
                    // Log API check failure to database
                    $db->logAction(
                        "API Check: ISPConfig",
                        "API deaktiviert - Config: ISPCONFIG_USEING = false",
                        'error'
                    );
                    
                    return $errorResponse;
                    $this->__log("checkAPIEnabled", $errorResponse, "error");
                }
                if (!isset($this->ispconfigGet) || !isset($this->ispconfigPost)) {
                    $errorResponse = [
                        'success' => false,
                        'error' => 'API_NOT_INITIALIZED',
                        'message' => 'ISPConfig API konnte nicht initialisiert werden',
                        'api' => 'ispconfig',
                        'solution' => 'Überprüfen Sie die ISPConfig-Konfiguration in der config.inc.php'
                    ];
                    
                    // Log API initialization failure to database
                    $db->logAction(
                        "API Check: ISPConfig",
                        "API nicht initialisiert - ISPConfigGet/Post Objekte fehlen",
                        'error'
                    );
                    
                    return $errorResponse;
                    $this->__log("checkAPIEnabled", $errorResponse, "error");
                }
                
                break;
                
            case 'ovh':
                if (!Config::OVH_USEING) {
                    $errorResponse = [
                        'success' => false,
                        'error' => 'API_NOT_ENABLED',
                        'message' => 'OVH API ist in der config.inc.php deaktiviert',
                        'api' => 'ovh',
                        'config_key' => 'OVH_USEING',
                        'solution' => 'Setzen Sie OVH_USEING = true in der config.inc.php'
                    ];
                    
                    // Log API check failure to database
                    $db->logAction(
                        "API Check: OVH",
                        "API deaktiviert - Config: OVH_USEING = false",
                        'error'
                    );
                    
                    return $errorResponse;
                    $this->__log("checkAPIEnabled", $errorResponse, "error");
                }
                if (!isset($this->ovhGet) || !isset($this->ovhPost)) {
                    $errorResponse = [
                        'success' => false,
                        'error' => 'API_NOT_INITIALIZED',
                        'message' => 'OVH API konnte nicht initialisiert werden',
                        'api' => 'ovh',
                        'solution' => 'Überprüfen Sie die OVH-Konfiguration in der config.inc.php'
                    ];
                    
                    // Log API initialization failure to database
                    $db->logAction(
                        "API Check: OVH",
                        "API nicht initialisiert - OVHGet/Post Objekte fehlen",
                        'error'
                    );
                    
                    return $errorResponse;
                    $this->__log("checkAPIEnabled", $errorResponse, "error");
                }
                
                break;
                
            case 'ogp':
                if (!Config::OGP_USEING) {
                    $errorResponse = [
                        'success' => false,
                        'error' => 'API_NOT_ENABLED',
                        'message' => 'OGP API ist in der config.inc.php deaktiviert',
                        'api' => 'ogp',
                        'config_key' => 'OGP_USEING',
                        'solution' => 'Setzen Sie OGP_USEING = true in der config.inc.php'
                    ];
                    
                    // Log API check failure to database
                    $db->logAction(
                        "API Check: OGP",
                        "API deaktiviert - Config: OGP_USEING = false",
                        'error'
                    );
                    
                    return $errorResponse;
                    $this->__log("checkAPIEnabled", $errorResponse, "error");
                }
                if (!isset($this->ogpGet) || !isset($this->ogpPost)) {
                    $errorResponse = [
                        'success' => false,
                        'error' => 'API_NOT_INITIALIZED',
                        'message' => 'OGP API konnte nicht initialisiert werden',
                        'api' => 'ogp',
                        'solution' => 'Überprüfen Sie die OGP-Konfiguration in der config.inc.php'
                    ];
                    
                    // Log API initialization failure to database
                    $db->logAction(
                        "API Check: OGP",
                        "API nicht initialisiert - OGPGet/Post Objekte fehlen",
                        'error'
                    );
                    
                    return $errorResponse;
                    $this->__log("checkAPIEnabled", $errorResponse, "error");
                }
                break;
                
            default:
                $errorResponse = [
                    'success' => false,
                    'error' => 'UNKNOWN_API',
                    'message' => 'Unbekannte API: ' . $apiName,
                    'api' => $apiName
                ];                
                return $errorResponse;
                $this->__log("checkAPIEnabled", $errorResponse, "error");
        }
        return true;
    }
	
	 
    // GENERISCHE API FUNKTIONEN
     
    /**
     * Generische Proxmox API Funktion
     * @param string $type HTTP-Methode (get, post, delete, put)
     * @param string $url API-Pfad (z.B. "/nodes/pve/qemu/100/status/start")
     * @param mixed $code Optionale Daten für POST/PUT Requests
     * @return mixed API Response oder false bei Fehler
     * 
     * Beispiele:
     * $serviceManager->ProxmoxAPI('get', '/nodes');
     * $serviceManager->ProxmoxAPI('get', '/nodes/pve/qemu/100/config');
     * $serviceManager->ProxmoxAPI('post', '/nodes/pve/qemu', ['vmid' => 101, 'name' => 'test-vm']);
     * $serviceManager->ProxmoxAPI('delete', '/nodes/pve/qemu/100');
     */
    public function ProxmoxAPI($type, $url, $code = null) {
        $apiCheck = $this->checkAPIEnabled('proxmox');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        try {
            $type = strtoupper($type);
            $fullUrl = $this->proxmoxGet->host . "/api2/json" . $url;
            
            // Verwende die makeRequest Methode der Proxmox Klasse
            $response = $this->proxmoxGet->makeRequest($type, $fullUrl, $code);
            
            
            return $response;
        } catch (Exception $e) {
            error_log("ProxmoxAPI Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'API_REQUEST_FAILED',
                'message' => 'Proxmox API Anfrage fehlgeschlagen: ' . $e->getMessage(),
                'api' => 'proxmox'
            ];
        }
    }    
    /**
     * Generische OVH API Funktion
     * @param string $type HTTP-Methode (get, post, delete, put)
     * @param string $url API-Pfad (z.B. "/domain/zone/example.com/record")
     * @param mixed $code Optionale Daten für POST/PUT Requests
     * @return mixed API Response oder false bei Fehler
     * 
     * Beispiele:
     * $serviceManager->OvhAPI('get', '/domain');
     * $serviceManager->OvhAPI('get', '/domain/zone/example.com/record');
     * $serviceManager->OvhAPI('post', '/domain/zone/example.com/record', ['fieldType' => 'A', 'target' => '1.2.3.4']);
     * $serviceManager->OvhAPI('delete', '/domain/zone/example.com/record/12345');
     */
    public function OvhAPI($type, $url, $code = null) {
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        try {
            $type = strtoupper($type);
            $endpoint = "https://eu.api.ovh.com/1.0";
            $fullUrl = $endpoint . $url;
            
            // Verwende die makeRequest Methode der OVH Klasse
            $response = $this->ovhGet->makeRequest($type, $fullUrl, $code);
            
            
            return $response;
        } catch (Exception $e) {
            error_log("OvhAPI Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'API_REQUEST_FAILED',
                'message' => 'OVH API Anfrage fehlgeschlagen: ' . $e->getMessage(),
                'api' => 'ovh'
            ];
        }
    }
    
    /**
     * Generische ISPConfig API Funktion
     * @param string $type HTTP-Methode (get, post, delete, put)
     * @param string $url API-Pfad/Funktion (z.B. "sites_web_domain_add")
     * @param mixed $code Optionale Daten für POST/PUT Requests
     * @return mixed API Response oder false bei Fehler
     * 
     * Beispiele:
     * $serviceManager->IspconfigAPI('get', 'sites_web_domain', ['primary_id' => 1]);
     * $serviceManager->IspconfigAPI('post', 'sites_web_domain', $websiteData);
     * $serviceManager->IspconfigAPI('put', 'sites_web_domain', ['id' => 1, 'data' => $updateData]);
     * $serviceManager->IspconfigAPI('delete', 'sites_web_domain', 123);
     */
    public function IspconfigAPI($type, $url, $code = null) {
        $apiCheck = $this->checkAPIEnabled('ispconfig');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        try {
            $type = strtolower($type);
            
            // ISPConfig verwendet SOAP, daher müssen wir die URL als Funktionsname interpretieren
            // Entferne führende Slashes
            $function = ltrim($url, '/');
            
            // Bestimme die richtige SOAP-Funktion basierend auf Type und URL
            switch($type) {
                case 'get':
                    // Keine automatische Suffix-Anfügung mehr - verwende den Funktionsnamen direkt
                    if ($code !== null) {
                        $result = $this->ispconfigGet->client->$function($this->ispconfigGet->session_id, $code);
                    } else {
                        $result = $this->ispconfigGet->client->$function($this->ispconfigGet->session_id);
                    }
                    break;
                    
                case 'post':                 
                    // ISPConfig erwartet: session_id, client_id, params
                    if (is_array($code) && isset($code['client_id'])) {
                        $client_id = $code['client_id'];
                        unset($code['client_id']);
                        $result = $this->ispconfigPost->client->$function($this->ispconfigPost->session_id, $client_id, $code);
                    } else {
                        // Default client_id = 1
                        $result = $this->ispconfigPost->client->$function($this->ispconfigPost->session_id, 1, $code);
                    }
                    break;
                    
                case 'put':
                    // Für Updates brauchen wir: session_id, client_id, primary_id, params
                    if (is_array($code)) {
                        $client_id = $code['client_id'] ?? 1;
                        $primary_id = $code['id'] ?? $code['primary_id'] ?? null;
                        $params = $code['data'] ?? $code;
                        
                        // Entferne Meta-Daten aus params
                        unset($params['client_id'], $params['id'], $params['primary_id']);
                        
                        if ($primary_id) {
                            $result = $this->ispconfigPost->client->$function($this->ispconfigPost->session_id, $client_id, $primary_id, $params);
                        } else {
                            throw new Exception("Primary ID required for update");
                        }
                    } else {
                        throw new Exception("Data array required for update");
                    }
                    break;
                    
                case 'delete':
                    // Für Delete brauchen wir die ID
                    $result = $this->ispconfigPost->client->$function($this->ispconfigPost->session_id, $code);
                    break;
                    
                default:
                    throw new Exception("Unsupported HTTP method: $type");
            }
                      
            return $result;
            
        } catch (Exception $e) {
            error_log("IspconfigAPI Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'ISPCONFIG_API_ERROR',
                'message' => 'ISPConfig API Fehler: ' . $e->getMessage(),
                'api' => 'ispconfig',
                'function' => $function,
                'type' => $type
            ];
        }
    }
    
    /**
     * Hilfsfunktion für erweiterte ISPConfig Operationen
     * Erlaubt direkten Zugriff auf SOAP-Funktionen
     */
    public function IspconfigSOAP($function, $params = []) {
        $apiCheck = $this->checkAPIEnabled('ispconfig');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        try {
            // Füge session_id als ersten Parameter hinzu
            array_unshift($params, $this->ispconfigGet->session_id);
            
            // Rufe die SOAP-Funktion dynamisch auf
            $result = call_user_func_array([$this->ispconfigGet->client, $function], $params);
            
            
            return $result;
        } catch (Exception $e) {
            error_log("IspconfigSOAP Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'SOAP_REQUEST_FAILED',
                'message' => 'ISPConfig SOAP Anfrage fehlgeschlagen: ' . $e->getMessage(),
                'api' => 'ispconfig',
                'function' => $function
            ];
        }
    }
    
    /**
     * Generische OGP API Funktion
     * @param string $type HTTP-Methode (get, post, delete, put)
     * @param string $url API-Pfad (z.B. "server/list", "user_games/create")
     * @param mixed $code Optionale Daten für POST/PUT Requests
     * @return mixed API Response oder false bei Fehler
     * 
     * Beispiele:
     * $serviceManager->OGPAPI('get', 'server/list');
     * $serviceManager->OGPAPI('post', 'server/create', $serverData);
     * $serviceManager->OGPAPI('post', 'user_games/create', $gameServerData);
     * $serviceManager->OGPAPI('post', 'gamemanager/start', ['ip' => '1.2.3.4', 'port' => 27015, 'mod_key' => 'csgo']);
     */
    public function OGPAPI($type, $url, $code = null) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        try {
            $type = strtoupper($type);
            $fullUrl = $this->ogpGet->host . "/ogp_api.php?" . $url;
            
            // Verwende die makeRequest Methode der OGP Klasse
            $response = $this->ogpGet->makeRequest($type, $fullUrl, $code);
            
            
            return $response;
        } catch (Exception $e) {
            error_log("OGPAPI Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'API_REQUEST_FAILED',
                'message' => 'OGP API Anfrage fehlgeschlagen: ' . $e->getMessage(),
                'api' => 'ogp'
            ];
        }
    }

    // Proxmox Methods
    public function getProxmoxVMs() {
        $apiCheck = $this->checkAPIEnabled('proxmox');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->proxmoxGet->getVMs();
    }
    public function getProxmoxNodes() {
        $apiCheck = $this->checkAPIEnabled('proxmox');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->proxmoxGet->getNodes();
    }
    public function getProxmoxStorage() {
        $apiCheck = $this->checkAPIEnabled('proxmox');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->proxmoxGet->getStorages();
    }

    public function getProxmoxNetwork() {
        $apiCheck = $this->checkAPIEnabled('proxmox');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        $networks = [];
        $nodes = $this->getProxmoxNodes();
        
        foreach ($nodes as $nodeData) {
            $nodeName = is_array($nodeData) ? $nodeData['node'] : $nodeData;
            $nodeNetworks = $this->proxmoxGet->getNetworks($nodeName);
            
            if (!empty($nodeNetworks)) {
                foreach ($nodeNetworks as $network) {
                    $network['node'] = $nodeName;
                    $networks[] = $network;
                }
            }
        }
        
        return $networks;
    }
    public function createProxmoxVM($vmData) {
        $apiCheck = $this->checkAPIEnabled('proxmox');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->proxmoxPost->createVM($vmData);
    }

    public function controlProxmoxVM($node, $vmid, $action) {
        $apiCheck = $this->checkAPIEnabled('proxmox');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        switch ($action) {
            case 'start':
                return $this->proxmoxPost->startVM($node, $vmid);
            case 'stop':
                return $this->proxmoxPost->stopVM($node, $vmid);
            case 'reset':
                return $this->proxmoxPost->resetVM($node, $vmid);
            case 'suspend':
                return $this->proxmoxPost->suspendVM($node, $vmid);
            case 'resume':
                return $this->proxmoxPost->resumeVM($node, $vmid);
            default:
                return [
                    'success' => false,
                    'error' => 'INVALID_ACTION',
                    'message' => 'Ungültige Aktion: ' . $action,
                    'api' => 'proxmox',
                    'valid_actions' => ['start', 'stop', 'reset', 'suspend', 'resume']
                ];
        }
    }

    public function deleteProxmoxVM($node, $vmid) {
        $apiCheck = $this->checkAPIEnabled('proxmox');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->proxmoxPost->deleteVM($node, $vmid);
    }

    // ISPConfig Methods
    public function getISPConfigWebsites() {
        $apiCheck = $this->checkAPIEnabled('ispconfig');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ispconfigGet->getWebsites(['active' => 'y']);
    }

    public function createISPConfigWebsite($websiteData) {
        $apiCheck = $this->checkAPIEnabled('ispconfig');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ispconfigPost->createWebsite($websiteData);
    }

    public function deleteISPConfigWebsite($domainId) {
        $apiCheck = $this->checkAPIEnabled('ispconfig');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ispconfigPost->deleteWebsite($domainId);
    }

    public function getISPConfigDatabases() {
        $apiCheck = $this->checkAPIEnabled('ispconfig');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ispconfigGet->getDatabases(['active' => 'y']);
    }

    public function createISPConfigDatabase($dbData) {
        $apiCheck = $this->checkAPIEnabled('ispconfig');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ispconfigPost->createDatabase($dbData);
    }

    public function deleteISPConfigDatabase($databaseId) {
        $apiCheck = $this->checkAPIEnabled('ispconfig');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ispconfigPost->deleteDatabase($databaseId);
    }

    public function getISPConfigEmails() {
        $apiCheck = $this->checkAPIEnabled('ispconfig');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ispconfigGet->getEmailAccounts(['active' => 'y']);
    }

    public function createISPConfigEmail($emailData) {
        $apiCheck = $this->checkAPIEnabled('ispconfig');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ispconfigPost->createEmailAccount($emailData);
    }

    public function deleteISPConfigEmail($mailuserId) {
        $apiCheck = $this->checkAPIEnabled('ispconfig');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ispconfigPost->deleteEmailAccount($mailuserId);
    }
    
    public function getISPConfigClients($filter = []) {
        $apiCheck = $this->checkAPIEnabled('ispconfig');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ispconfigGet->getClients($filter);
    }
    
    public function createISPConfigClient($clientData) {
        $apiCheck = $this->checkAPIEnabled('ispconfig');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ispconfigPost->createClient($clientData);
    }
    
    public function updateISPConfigClient($clientId, $clientData) {
        $apiCheck = $this->checkAPIEnabled('ispconfig');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ispconfigPost->updateClient($clientId, $clientData);
    }
    
    public function deleteISPConfigClient($clientId) {
        $apiCheck = $this->checkAPIEnabled('ispconfig');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ispconfigPost->deleteClient($clientId);
    }

    // OVH Methods
    public function getOVHDomains() {
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ovhGet->getDomains();
    }

    public function orderOVHDomain($domain, $duration) {
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ovhPost->orderDomain($domain, $duration);
    }

    public function getOVHVPS() {
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ovhGet->getVPSList();
    }

    public function getOvhIP(){
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ovhGet->getAllIPReverseDetails();
    }
    public function getOVHFailoverIPs() {
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        $failoverIPs = $this->ovhGet->getFailoverIPs();
        $detailedIPs = [];

        if (!empty($failoverIPs)) {
            foreach ($failoverIPs as $ip) {
                // The getFailoverIPs method returns a list of IP strings.
                // Each string can be a single IP or a block (e.g., x.x.x.x/32).
                // We pass this string directly to getFailoverIPDetails.
                $details = $this->ovhGet->getFailoverIPDetails($ip);
                if ($details) {
                    // Assuming $details is already in a suitable format (e.g., an associative array or an object)
                    // Map the raw details to a FailoverIP object.
                    $detailedIPs[] = DataMapper::mapToFailoverIP($details);
                }
            }
        }
        return $detailedIPs;
    }

    public function getOVHVPSMacAddress($vpsName) {
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        $vps = $this->ovhGet->getVPS($vpsName);
        if ($vps && !empty($vps->ips) && !empty($vps->mac_addresses)) {
            $firstIp = $vps->ips[0];
            return [
                'ip' => $firstIp,
                'mac' => $vps->mac_addresses[$firstIp] ?? null
            ];
        }
        return null;
    }

    /**
     * Holt alle Virtual MAC-Adressen für einen bestimmten Service
     */
    public function getVirtualMacAddresses($serviceName = null) {
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        if ($serviceName) {
            return $this->ovhGet->getAllVirtualMacDetailsWithIPs($serviceName);
        } else {
            return $this->ovhGet->getAllVirtualMacAddresses();
        }
    }

    /**
     * Holt Details zu einer Virtual MAC-Adresse
     */
    public function getVirtualMacDetails($serviceName, $macAddress) {
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ovhGet->getVirtualMacDetails($serviceName, $macAddress);
    }

    /**
     * Erstellt eine neue Virtual MAC-Adresse
     */
    public function createVirtualMac($serviceName, $virtualNetworkInterface, $type = 'ovh') {
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ovhPost->createVirtualMac($serviceName, $virtualNetworkInterface, $type);
    }

    /**
     * Löscht eine Virtual MAC-Adresse
     */
    public function deleteVirtualMac($serviceName, $macAddress) {
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ovhPost->deleteVirtualMac($serviceName, $macAddress);
    }

    /**
     * Fügt IP zu Virtual MAC hinzu
     */
    public function addIPToVirtualMac($serviceName, $macAddress, $ipAddress, $virtualNetworkInterface) {
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ovhPost->addVirtualMacIP($serviceName, $macAddress, $ipAddress, $virtualNetworkInterface);
    }

    /**
     * Entfernt IP von Virtual MAC
     */
    public function removeIPFromVirtualMac($serviceName, $macAddress, $ipAddress) {
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ovhPost->removeVirtualMacIP($serviceName, $macAddress, $ipAddress);
    }

    // Reverse DNS Methods
    public function getIPReverse($ipAddress) {
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ovhGet->getIPReverse($ipAddress);
    }

    public function createIPReverse($ipAddress, $reverse) {
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ovhPost->createIPReverse($ipAddress, $reverse);
    }

    public function updateIPReverse($ipAddress, $reverseIP, $newReverse) {
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ovhPost->updateIPReverse($ipAddress, $reverseIP, $newReverse);
    }

    public function deleteIPReverse($ipAddress, $reverseIP) {
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ovhPost->deleteIPReverse($ipAddress, $reverseIP);
    }

    // Convenience Methods
    public function getCompleteVirtualMacInfo($serviceName = null) {
        $result = [];
        
        if ($serviceName) {
            $servers = [$serviceName];
        } else {
            $servers = $this->ovhGet->getDedicatedServers();
        }

        foreach ($servers as $server) {
            $virtualMacs = $this->getVirtualMacAddresses($server);
            if (!empty($virtualMacs)) {
                $result[$server] = [
                    'server' => $server,
                    'virtualMacs' => $virtualMacs,
                    'totalMacs' => count($virtualMacs),
                    'totalIPs' => 0
                ];

                // IPs zählen
                foreach ($virtualMacs as $mac) {
                    if (isset($mac->ips)) {
                        $result[$server]['totalIPs'] += count($mac->ips);
                    }
                }
            }
        }

        return $result;
    }
    
     
    // OGP METHODS
     
    
    // Token Management
    public function testOGPToken() {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpGet->testToken();
    }
    
    // Remote Servers
    public function getOGPServerList() {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpGet->getServerList();
    }
    
    public function getOGPServerStatus($remoteServerId) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpGet->getServerStatus($remoteServerId);
    }
    
    public function getOGPServerIPs($remoteServerId) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpGet->getServerIPs($remoteServerId);
    }
    
    public function restartOGPServer($remoteServerId) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpPost->restartServer($remoteServerId);
    }
    
    public function createOGPServer($serverData) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpPost->createServer($serverData);
    }
    
    public function removeOGPServer($remoteServerId) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpPost->removeServer($remoteServerId);
    }
    
    public function addOGPServerIP($remoteServerId, $ip) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpPost->addServerIP($remoteServerId, $ip);
    }
    
    public function removeOGPServerIP($remoteServerId, $ip) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpPost->removeServerIP($remoteServerId, $ip);
    }
    
    public function editOGPServerIP($remoteServerId, $oldIp, $newIp) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpPost->editServerIP($remoteServerId, $oldIp, $newIp);
    }
    
    // Game Servers
    public function getOGPGamesList($system = 'linux', $architecture = '64') {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpGet->getGamesList($system, $architecture);
    }
    
    public function getOGPGameServers() {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpGet->getGameServers();
    }
    
    public function createOGPGameServer($gameServerData) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpPost->createGameServer($gameServerData);
    }
    
    public function cloneOGPGameServer($cloneData) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpPost->cloneGameServer($cloneData);
    }
    
    public function setOGPGameServerExpiration($homeId, $timestamp) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpPost->setGameServerExpiration($homeId, $timestamp);
    }
    
    // Users
    public function getOGPUsers() {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpGet->getUsers();
    }
    
    public function getOGPUser($email) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpGet->getUser($email);
    }
    
    public function getOGPUserAssigned($email) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpGet->getUserAssigned($email);
    }
    
    public function createOGPUser($userData) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpPost->createUser($userData);
    }
    
    public function removeOGPUser($email) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpPost->removeUser($email);
    }
    
    public function setOGPUserExpiration($email, $timestamp) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpPost->setUserExpiration($email, $timestamp);
    }
    
    public function assignOGPUser($email, $homeId, $timestamp = null) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpPost->assignUser($email, $homeId, $timestamp);
    }
    
    public function removeOGPUserAssignment($email, $homeId) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpPost->removeUserAssignment($email, $homeId);
    }

     
    // PROXMOX USER MANAGEMENT
     
    
    public function createProxmoxUser($userData) {
        $apiCheck = $this->checkAPIEnabled('proxmox');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->proxmoxPost->createProxmoxUser($userData);
    }

    public function deleteProxmoxUser($userid) {
        $apiCheck = $this->checkAPIEnabled('proxmox');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->proxmoxPost->deleteProxmoxUser($userid);
    }

    public function updateProxmoxUser($userid, $userData) {
        $apiCheck = $this->checkAPIEnabled('proxmox');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->proxmoxPost->updateProxmoxUser($userid, $userData);
    }

    public function getProxmoxUsers() {
        $apiCheck = $this->checkAPIEnabled('proxmox');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->proxmoxPost->getProxmoxUsers();
    }

    public function getProxmoxUser($userid) {
        $apiCheck = $this->checkAPIEnabled('proxmox');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->proxmoxPost->getProxmoxUser($userid);
    }
    
    // Game Manager
    public function startOGPGameManager($ip, $port, $modKey) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpPost->startGameManager($ip, $port, $modKey);
    }
    
    public function stopOGPGameManager($ip, $port, $modKey) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpPost->stopGameManager($ip, $port, $modKey);
    }
    
    public function restartOGPGameManager($ip, $port, $modKey) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpPost->restartGameManager($ip, $port, $modKey);
    }
    
    public function sendOGPRconCommand($ip, $port, $modKey, $command) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpPost->sendRconCommand($ip, $port, $modKey, $command);
    }
    
    public function updateOGPGameManager($ip, $port, $modKey, $type, $manualUrl = null) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpPost->updateGameManager($ip, $port, $modKey, $type, $manualUrl);
    }
    
    // Lite File Manager
    public function listOGPFiles($ip, $port, $relativePath) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpPost->listFiles($ip, $port, $relativePath);
    }
    
    public function getOGPFile($ip, $port, $relativePath) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpPost->getFile($ip, $port, $relativePath);
    }
    
    public function saveOGPFile($ip, $port, $relativePath, $contents) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpPost->saveFile($ip, $port, $relativePath, $contents);
    }
    
    public function removeOGPFile($ip, $port, $relativePath) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpPost->removeFile($ip, $port, $relativePath);
    }
    
    // Addons Manager
    public function getOGPAddonsList() {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpGet->getAddonsList();
    }
    
    public function installOGPAddon($ip, $port, $modKey, $addonId) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpPost->installAddon($ip, $port, $modKey, $addonId);
    }
    
    // Steam Workshop
    public function installOGPSteamWorkshop($ip, $port, $modsList) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpPost->installSteamWorkshop($ip, $port, $modsList);
    }
    
    // Panel Settings
    public function getOGPSetting($settingName) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        return $this->ogpGet->getSetting($settingName);
    }
    
    // System-Informationen
    public function getSystemInfo() {
        try {
            $systemInfo = [
                'cpu_usage' => $this->getCPUUsage(),
                'memory_usage' => $this->getMemoryUsage(),
                'disk_usage' => $this->getDiskUsage(),
                'uptime' => $this->getUptime(),
                'load_average' => $this->getLoadAverage(),
                'network_status' => $this->getNetworkStatus()
            ];
            return $systemInfo;
        } catch (Exception $e) {
            error_log("Error getting system info: " . $e->getMessage());
            return [];
        }
    }
    
    private function getCPUUsage() {
        try {
            $cpuUsage = null;
            
            // Methode 1: sys_getloadavg mit robuster Berechnung
            if (function_exists('sys_getloadavg')) {
                try {
                    $load = @sys_getloadavg();
                    if ($load !== false && is_array($load) && isset($load[0]) && is_numeric($load[0])) {
                        // Vereinfachte CPU-Berechnung basierend auf Load Average
                        $loadValue = (float)$load[0];
                        $cpuUsage = min(100, round($loadValue * 25, 2)); // Max 100%
                    }
                } catch (Exception $e) {
                    // Ignoriere Fehler
                }
            }
            
            // Methode 2: /proc/stat (Linux) - nur versuchen wenn verfügbar
            if ($cpuUsage === null) {
                try {
                    if (@file_exists('/proc/stat')) {
                        $statContent = @file_get_contents('/proc/stat');
                        if ($statContent !== false) {
                            $lines = explode("\n", $statContent);
                            foreach ($lines as $line) {
                                if (strpos($line, 'cpu ') === 0) {
                                    $parts = preg_split('/\s+/', trim($line));
                                    if (count($parts) >= 5) {
                                        $total = array_sum(array_slice($parts, 1, 4));
                                        $idle = (int)$parts[4];
                                        if ($total > 0) {
                                            $cpuUsage = round((($total - $idle) / $total) * 100, 2);
                                        }
                                    }
                                    break;
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Ignoriere Fehler
                }
            }
            
            // Methode 3: top-Befehl über exec (falls verfügbar)
            if ($cpuUsage === null) {
                if (function_exists('exec') && !in_array('exec', explode(',', ini_get('disable_functions')))) {
                    try {
                        $output = [];
                        $returnVar = 0;
                        @exec('top -bn1 | grep "Cpu(s)" 2>/dev/null', $output, $returnVar);
                        
                        if ($returnVar === 0 && !empty($output)) {
                            $topLine = $output[0];
                            if (preg_match('/(\d+\.?\d*)%us/', $topLine, $matches)) {
                                $cpuUsage = round((float)$matches[1], 2);
                            }
                        }
                    } catch (Exception $e) {
                        // Ignoriere exec-Fehler
                    }
                }
            }
            
            if ($cpuUsage !== null && $cpuUsage >= 0 && $cpuUsage <= 100) {
                return $cpuUsage;
            }
            
            return 0;
        } catch (Exception $e) {
            error_log("Error getting CPU usage: " . $e->getMessage());
            return 0;
        }
    }
    
    private function getMemoryUsage() {
        try {
            $memoryData = null;
            
            // Methode 1: PHP memory_get_* Funktionen
            if (function_exists('memory_get_usage') && function_exists('memory_get_peak_usage')) {
                try {
                    $memoryUsage = @memory_get_usage(true);
                    $memoryPeak = @memory_get_peak_usage(true);
                    $memoryLimit = @ini_get('memory_limit');
                    
                    if ($memoryUsage !== false && $memoryPeak !== false) {
                        // Konvertiere memory_limit zu Bytes
                        $limitBytes = $this->convertToBytes($memoryLimit);
                        
                        $usagePercent = 0;
                        if ($limitBytes > 0) {
                            $usagePercent = round(($memoryPeak / $limitBytes) * 100, 2);
                        }
                        
                        $memoryData = [
                            'current' => $this->formatBytes($memoryUsage),
                            'peak' => $this->formatBytes($memoryPeak),
                            'limit' => $memoryLimit ?: 'Unbekannt',
                            'usage_percent' => $usagePercent,
                            'type' => 'php'
                        ];
                    }
                } catch (Exception $e) {
                    // Ignoriere Fehler
                }
            }
            
            // Methode 2: /proc/meminfo (Linux) - nur versuchen wenn verfügbar
            if ($memoryData === null) {
                try {
                    if (@file_exists('/proc/meminfo')) {
                        $meminfoContent = @file_get_contents('/proc/meminfo');
                        if ($meminfoContent !== false) {
                            $lines = explode("\n", $meminfoContent);
                            $memTotal = null;
                            $memAvailable = null;
                            
                            foreach ($lines as $line) {
                                if (strpos($line, 'MemTotal:') === 0) {
                                    $parts = preg_split('/\s+/', trim($line));
                                    if (count($parts) >= 2) {
                                        $memTotal = (int)$parts[1] * 1024; // KB zu Bytes
                                    }
                                } elseif (strpos($line, 'MemAvailable:') === 0) {
                                    $parts = preg_split('/\s+/', trim($line));
                                    if (count($parts) >= 2) {
                                        $memAvailable = (int)$parts[1] * 1024; // KB zu Bytes
                                    }
                                }
                            }
                            
                            if ($memTotal !== null && $memAvailable !== null && $memTotal > 0) {
                                $memUsed = $memTotal - $memAvailable;
                                $usagePercent = round(($memUsed / $memTotal) * 100, 2);
                                
                                $memoryData = [
                                    'current' => $this->formatBytes($memUsed),
                                    'peak' => $this->formatBytes($memTotal),
                                    'limit' => $this->formatBytes($memTotal),
                                    'usage_percent' => $usagePercent,
                                    'type' => 'system'
                                ];
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Ignoriere Fehler
                }
            }
            
            // Methode 3: free-Befehl über exec (falls verfügbar)
            if ($memoryData === null) {
                if (function_exists('exec') && !in_array('exec', explode(',', ini_get('disable_functions')))) {
                    try {
                        $output = [];
                        $returnVar = 0;
                        @exec('free -b 2>/dev/null', $output, $returnVar);
                        
                        if ($returnVar === 0 && !empty($output) && count($output) > 1) {
                            $parts = preg_split('/\s+/', trim($output[1]));
                            if (count($parts) >= 4) {
                                $memTotal = (int)$parts[1];
                                $memUsed = (int)$parts[2];
                                
                                if ($memTotal > 0) {
                                    $usagePercent = round(($memUsed / $memTotal) * 100, 2);
                                    
                                    $memoryData = [
                                        'current' => $this->formatBytes($memUsed),
                                        'peak' => $this->formatBytes($memTotal),
                                        'limit' => $this->formatBytes($memTotal),
                                        'usage_percent' => $usagePercent,
                                        'type' => 'free_command'
                                    ];
                                }
                            }
                        }
                    } catch (Exception $e) {
                        // Ignoriere exec-Fehler
                    }
                }
            }
            
            if ($memoryData !== null) {
                return $memoryData;
            }
            
            // Fallback: Standardwerte
            return [
                'current' => 'Unbekannt',
                'peak' => 'Unbekannt',
                'limit' => 'Unbekannt',
                'usage_percent' => 0,
                'type' => 'unavailable'
            ];
        } catch (Exception $e) {
            error_log("Error getting memory usage: " . $e->getMessage());
            return [
                'current' => 'Fehler',
                'peak' => 'Fehler',
                'limit' => 'Fehler',
                'usage_percent' => 0,
                'type' => 'error'
            ];
        }
    }
    
    private function getDiskUsage() {
        try {
            // Verwende nur das aktuelle Verzeichnis und relative Pfade
            $directories = ['.', './tmp', './logs'];
            $diskTotal = null;
            $diskFree = null;
            
            foreach ($directories as $dir) {
                try {
                    // Verwende @ Operator um alle Warnungen zu unterdrücken
                    $testTotal = @disk_total_space($dir);
                    $testFree = @disk_free_space($dir);
                    
                    if ($testTotal !== false && $testTotal > 0 && $testFree !== false) {
                        $diskTotal = $testTotal;
                        $diskFree = $testFree;
                        break;
                    }
                } catch (Exception $e) {
                    // Ignoriere Fehler und versuche das nächste Verzeichnis
                    continue;
                }
            }
            
            // Fallback: Versuche df-Befehl über exec (falls verfügbar)
            if ($diskTotal === null || $diskFree === null) {
                if (function_exists('exec') && !in_array('exec', explode(',', ini_get('disable_functions')))) {
                    try {
                        $output = [];
                        $returnVar = 0;
                        @exec('df -B1 . 2>/dev/null', $output, $returnVar);
                        
                        if ($returnVar === 0 && !empty($output) && count($output) > 1) {
                            $parts = preg_split('/\s+/', trim($output[1]));
                            if (count($parts) >= 4) {
                                $diskTotal = (int)$parts[1];
                                $diskFree = (int)$parts[3];
                            }
                        }
                    } catch (Exception $e) {
                        // Ignoriere exec-Fehler
                    }
                }
            }
            
            // Wenn immer noch keine Daten verfügbar sind, verwende Standardwerte
            if ($diskTotal === null || $diskFree === null || $diskTotal <= 0) {
                return [
                    'total' => 'Unbekannt',
                    'used' => 'Unbekannt',
                    'free' => 'Unbekannt',
                    'usage_percent' => 0,
                    'status' => 'unavailable'
                ];
            }
            
            // Berechne Werte nur wenn gültige Daten vorhanden sind
            $diskUsed = $diskTotal - $diskFree;
            $diskUsagePercent = ($diskTotal > 0) ? round(($diskUsed / $diskTotal) * 100, 2) : 0;
            
            return [
                'total' => $this->formatBytes($diskTotal),
                'used' => $this->formatBytes($diskUsed),
                'free' => $this->formatBytes($diskFree),
                'usage_percent' => $diskUsagePercent,
                'status' => 'available'
            ];
            
        } catch (Exception $e) {
            error_log("Error getting disk usage: " . $e->getMessage());
            return [
                'total' => 'Fehler',
                'used' => 'Fehler',
                'free' => 'Fehler',
                'usage_percent' => 0,
                'status' => 'error'
            ];
        }
    }
    
    private function getUptime() {
        try {
            // Versuche verschiedene Methoden für Uptime
            $uptime = null;
            
            // Methode 1: /proc/uptime (Linux) - nur versuchen wenn verfügbar
            try {
                if (@file_exists('/proc/uptime')) {
                    $uptimeContent = @file_get_contents('/proc/uptime');
                    if ($uptimeContent !== false) {
                        $parts = explode(' ', trim($uptimeContent));
                        if (!empty($parts[0]) && is_numeric($parts[0])) {
                            $uptime = (float)$parts[0];
                        }
                    }
                }
            } catch (Exception $e) {
                // Ignoriere Fehler
            }
            
            // Methode 2: uptime-Befehl über exec
            if ($uptime === null) {
                if (function_exists('exec') && !in_array('exec', explode(',', ini_get('disable_functions')))) {
                    try {
                        $output = [];
                        $returnVar = 0;
                        @exec('uptime -s 2>/dev/null', $output, $returnVar);
                        
                        if ($returnVar === 0 && !empty($output)) {
                            $startTime = strtotime($output[0]);
                            if ($startTime !== false) {
                                $uptime = time() - $startTime;
                            }
                        }
                    } catch (Exception $e) {
                        // Ignoriere exec-Fehler
                    }
                }
            }
            
            // Methode 3: sys_getloadavg mit Fallback
            if ($uptime === null) {
                if (function_exists('sys_getloadavg')) {
                    try {
                        $load = @sys_getloadavg();
                        if ($load !== false && isset($load[0])) {
                            // Schätze Uptime basierend auf Load Average (sehr ungenau)
                            $uptime = 3600; // 1 Stunde als Standard
                        }
                    } catch (Exception $e) {
                        // Ignoriere Fehler
                    }
                }
            }
            
            if ($uptime !== null && $uptime > 0) {
                return $this->formatUptime($uptime);
            }
            
            return 'Unbekannt';
        } catch (Exception $e) {
            error_log("Error getting uptime: " . $e->getMessage());
            return 'Unbekannt';
        }
    }
    
    private function getLoadAverage() {
        try {
            $load = null;
            
            // Methode 1: sys_getloadavg (Standard PHP-Funktion)
            if (function_exists('sys_getloadavg')) {
                try {
                    $loadData = @sys_getloadavg();
                    if ($loadData !== false && is_array($loadData) && count($loadData) >= 3) {
                        $load = [
                            '1min' => is_numeric($loadData[0]) ? round((float)$loadData[0], 2) : 0,
                            '5min' => is_numeric($loadData[1]) ? round((float)$loadData[1], 2) : 0,
                            '15min' => is_numeric($loadData[2]) ? round((float)$loadData[2], 2) : 0
                        ];
                    }
                } catch (Exception $e) {
                    // Ignoriere Fehler
                }
            }
            
            // Methode 2: /proc/loadavg (Linux) - nur versuchen wenn verfügbar
            if ($load === null) {
                try {
                    if (@file_exists('/proc/loadavg')) {
                        $loadContent = @file_get_contents('/proc/loadavg');
                        if ($loadContent !== false) {
                            $parts = explode(' ', trim($loadContent));
                            if (count($parts) >= 3) {
                                $load = [
                                    '1min' => is_numeric($parts[0]) ? round((float)$parts[0], 2) : 0,
                                    '5min' => is_numeric($parts[1]) ? round((float)$parts[1], 2) : 0,
                                    '15min' => is_numeric($parts[2]) ? round((float)$parts[2], 2) : 0
                                ];
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Ignoriere Fehler
                }
            }
            
            // Methode 3: uptime-Befehl über exec
            if ($load === null) {
                if (function_exists('exec') && !in_array('exec', explode(',', ini_get('disable_functions')))) {
                    try {
                        $output = [];
                        $returnVar = 0;
                        @exec('uptime 2>/dev/null', $output, $returnVar);
                        
                        if ($returnVar === 0 && !empty($output)) {
                            $uptimeLine = $output[0];
                            if (preg_match('/load average: ([\d.]+), ([\d.]+), ([\d.]+)/', $uptimeLine, $matches)) {
                                $load = [
                                    '1min' => round((float)$matches[1], 2),
                                    '5min' => round((float)$matches[2], 2),
                                    '15min' => round((float)$matches[3], 2)
                                ];
                            }
                        }
                    } catch (Exception $e) {
                        // Ignoriere exec-Fehler
                    }
                }
            }
            
            if ($load !== null) {
                return $load;
            }
            
            return ['1min' => 0, '5min' => 0, '15min' => 0];
        } catch (Exception $e) {
            error_log("Error getting load average: " . $e->getMessage());
            return ['1min' => 0, '5min' => 0, '15min' => 0];
        }
    }
    
    private function getNetworkStatus() {
        try {
            // Vereinfachte Netzwerk-Status-Prüfung
            $networkStatus = 'online';
            
            // Prüfe ob externe Verbindung möglich ist
            if (function_exists('fsockopen')) {
                $connection = @fsockopen('8.8.8.8', 53, $errno, $errstr, 5);
                if (!$connection) {
                    $networkStatus = 'offline';
                } else {
                    fclose($connection);
                }
            }
            
            return [
                'status' => $networkStatus,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            return ['status' => 'unknown', 'timestamp' => date('Y-m-d H:i:s')];
        }
    }
    
    private function convertToBytes($sizeStr) {
        $sizeStr = trim($sizeStr);
        $last = strtolower($sizeStr[strlen($sizeStr) - 1]);
        $size = (int) $sizeStr;
        
        switch ($last) {
            case 'g': $size *= 1024;
            case 'm': $size *= 1024;
            case 'k': $size *= 1024;
        }
        
        return $size;
    }
    
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    private function formatUptime($seconds) {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        if ($days > 0) {
            return "{$days}d {$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h {$minutes}m";
        } else {
            return "{$minutes}m";
        }
    }
}

// MODULE HELPER FUNCTIONS
function getAllModules() {
    $modules = [];
    $module_dir = __DIR__ . '/module/';
    
    if (!is_dir($module_dir)) {
        return $modules;
    }
    
    $dirs = glob($module_dir . '*', GLOB_ONLYDIR);
    
    foreach ($dirs as $dir) {
        $module_key = basename($dir);
        
        // Überspringe spezielle Verzeichnisse
        if (in_array($module_key, ['.', '..', 'assets', 'templates'])) {
            continue;
        }
        
        $config_file = $dir . '/config.php';
        $module_file = $dir . '/Module.php';
        
        if (file_exists($module_file)) {
            $config = [
                'key' => $module_key,
                'path' => $dir,
                'enabled' => true, // Standardmäßig aktiviert
                'name' => ucfirst($module_key),
                'icon' => '📦',
                'description' => 'Module ' . ucfirst($module_key),
                'version' => '1.0.0',
                'author' => 'System',
                'dependencies' => []
            ];
            
            // Lade spezifische Konfiguration falls vorhanden
            if (file_exists($config_file)) {
                $module_config = include $config_file;
                $config = array_merge($config, $module_config);
            }
            
            $modules[$module_key] = $config;
        }
    }
    
    return $modules;
}
function getEnabledModules() {
    $all_modules = getAllModules();
    $enabled_modules = [];
    
    foreach ($all_modules as $key => $module) {
        if ($module['enabled']) {
            $enabled_modules[$key] = $module;
        }
    }
    
    return $enabled_modules;
}
function getModuleConfig($module_key) {
    return getPluginConfig($module_key);
}

function canAccessModule($module_key, $user_role) {
    $config = getModuleConfig($module_key);
    
    if (!$config) {
        return false;
    }
    
    // Standardmäßig haben alle Benutzer Zugriff
    if (!isset($config['permissions'])) {
        return true;
    }
    
    // Prüfe spezifische Berechtigungen
    $permissions = $config['permissions'];
    
    // Admin hat immer Zugriff
    if ($user_role === 'admin') {
        return true;
    }
    
    // Prüfe Benutzerrolle
    if (isset($permissions['roles'])) {
        if (!in_array($user_role, $permissions['roles'])) {
            return false;
        }
    }
    
    return true;
}

?>