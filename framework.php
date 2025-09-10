<?php
/**
 * Server Management Framework
 * 
 * @author Teris
 * @version 3.1.2
 */
require_once 'config/config.inc.php';
require_once 'src/core/DatabaseManager.php';

// DATABASE CLASS - KompatibilitÃ¤tsklasse fÃ¼r bestehenden Code
class Database {
    private static $instance = null;
    private $dbManager;

    private function __construct() {
        try {
            $this->dbManager = DatabaseManager::getInstance();
        } catch(Exception $e) {
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
        return $this->dbManager->getConnection();
    }

    public function logAction($action, $details, $status) {
        return $this->dbManager->logAction($action, $details, $status);
    }

    public function getActivityLog($limit = 50, $offset = 0) {
        return $this->dbManager->getActivityLog($limit, $offset);
    }
    
    public function clearActivityLogs() {
        return $this->dbManager->clearActivityLogs();
    }

    // PDO Wrapper-Methoden fÃ¼r KompatibilitÃ¤t
    public function prepare($sql) {
        return $this->dbManager->prepare($sql);
    }

    public function query($sql) {
        return $this->dbManager->query($sql);
    }

    public function exec($sql) {
        if ($this->dbManager->isMongoDB()) {
            // MongoDB unterstÃ¼tzt keine SQL-Exec-Befehle
            return false;
        }
        return $this->dbManager->getConnection()->exec($sql);
    }

    public function lastInsertId($name = null) {
        return $this->dbManager->getConnection()->lastInsertId($name);
    }

    public function beginTransaction() {
        return $this->dbManager->beginTransaction();
    }

    public function commit() {
        return $this->dbManager->commit();
    }

    public function rollback() {
        return $this->dbManager->rollback();
    }

    public function inTransaction() {
        if ($this->dbManager->isMongoDB()) {
            // MongoDB unterstÃ¼tzt Transaktionen ab Version 4.0
            return false;
        }
        return $this->dbManager->getConnection()->inTransaction();
    }

    public function quote($string, $type = PDO::PARAM_STR) {
        if ($this->dbManager->isMongoDB()) {
            // MongoDB benÃ¶tigt kein Quoting
            return $string;
        }
        return $this->dbManager->getConnection()->quote($string, $type);
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

class LXC {
    public $vmid;
    public $name;
    public $node;
    public $status;
    public $cores;
    public $memory;
    public $disk;
    public $uptime;
    public $cpu_usage;
    public $memory_usage;

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

        // Alle anderen Properties dynamisch hinzufÃ¼gen
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

    public static function mapToLXC($data) {
        return new LXC([
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
    public function getLXCs($node = null) {
        $lxcs = [];
        $nodes = $node ? [$node] : $this->getNodes();

        foreach ($nodes as $nodeData) {
            $nodeName = is_array($nodeData) ? $nodeData['node'] : $nodeData;
            $url = $this->host . "/api2/json/nodes/$nodeName/lxc";
            $response = $this->makeRequest('GET', $url);

            if ($response && isset($response['data'])) {
                foreach ($response['data'] as $lxcData) {
                    $lxcData['node'] = $nodeName;
                    $lxcs[] = DataMapper::mapToLXC($lxcData);
                }
            }
        }

        ////$this->logRequest('/nodes/*/qemu', 'GET', !empty($vms));
        return $lxcs;
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
        
        if ($ch === false) {
            return false;
        }

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

        // Prüfe auf cURL Fehler
        if ($response === false) {
            return false;
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            $decoded = json_decode($response, true);
            // Stelle sicher, dass json_decode erfolgreich war
            return $decoded !== null ? $decoded : false;
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

    public function rebootVM($node, $vmid) {
        $url = $this->host . "/api2/json/nodes/$node/qemu/$vmid/status/reboot";
        $response = $this->makeRequest('POST', $url);
        //$this->logRequest("/nodes/$node/qemu/$vmid/status/reboot", 'POST', $response !== false);
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

    //Proxmox LXC
    public function createLXC($node, $lxcData) {
        $url = $this->host . "/api2/json/nodes/$node/lxc";
        $response = $this->makeRequest('POST', $url, $lxcData);
        //$this->logRequest("/nodes/$node/lxc", 'POST', $response !== false);
        return $response;
    }
    public function editLXC($node, $vmid, $lxcData) {
        $url = $this->host . "/api2/json/nodes/$node/lxc/$vmid/config";
        $response = $this->makeRequest('PUT', $url, $lxcData);
        //$this->logRequest("/nodes/$node/lxc/$vmid/config", 'PUT', $response !== false);
        return $response;
    }
    public function deleteLXC($node, $vmid) {
        $url = $this->host . "/api2/json/nodes/$node/lxc/$vmid";
        $response = $this->makeRequest('DELETE', $url);
        //$this->logRequest("/nodes/$node/lxc/$vmid", 'DELETE', $response !== false);
        return $response;
    }
    public function startLXC($node, $vmid) {
        $url = $this->host . "/api2/json/nodes/$node/lxc/$vmid/status/start";
        $response = $this->makeRequest('POST', $url);
        //$this->logRequest("/nodes/$node/lxc/$vmid/status/start", 'POST', $response !== false);
        return $response;
    }
    public function stopLXC($node, $vmid) {
        $url = $this->host . "/api2/json/nodes/$node/lxc/$vmid/status/stop";
        $response = $this->makeRequest('POST', $url);
        //$this->logRequest("/nodes/$node/lxc/$vmid/status/stop", 'POST', $response !== false);
        return $response;
    }
    public function resetLXC($node, $vmid) {
        $url = $this->host . "/api2/json/nodes/$node/lxc/$vmid/status/reset";
        $response = $this->makeRequest('POST', $url);
        //$this->logRequest("/nodes/$node/lxc/$vmid/status/reset", 'POST', $response !== false);
        return $response;
    }
    public function suspendLXC($node, $vmid) {
        $url = $this->host . "/api2/json/nodes/$node/lxc/$vmid/status/suspend";
        $response = $this->makeRequest('POST', $url);
        //$this->logRequest("/nodes/$node/lxc/$vmid/status/suspend", 'POST', $response !== false);
        return $response;
    }
    public function resumeLXC($node, $vmid) {
        $url = $this->host . "/api2/json/nodes/$node/lxc/$vmid/status/resume";
        $response = $this->makeRequest('POST', $url);
        //$this->logRequest("/nodes/$node/lxc/$vmid/status/resume", 'POST', $response !== false);
        return $response;
    }

    public function rebootLXC($node, $vmid) {
        $url = $this->host . "/api2/json/nodes/$node/lxc/$vmid/status/reboot";
        $response = $this->makeRequest('POST', $url);
        //$this->logRequest("/nodes/$node/lxc/$vmid/status/reboot", 'POST', $response !== false);
        return $response;
    }

    // PROXMOX USER MANAGEMENt
    public function createProxmoxUser($userData) {
        $url = $this->host . "/api2/json/access/users";
        
        // Proxmox API erwartet: userid, password, comment (optional), email (optional), firstname (optional), lastname (optional)
        $data = [
            'userid' => $userData['userid'] ?? $userData['username'] . '@' . ($userData['realm'] ?? 'pve'),
            'password' => $userData['password'],
            'comment' => $userData['comment'] ?? '',
            'email' => $userData['email'] ?? '',
            'firstname' => $userData['firstname'] ?? '',
            'lastname' => $userData['lastname'] ?? ''
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
    private $debug_mode = false; // FÃ¼r Debugging aktivieren

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
                throw new Exception("ISPConfig Login fehlgeschlagen - ÃœberprÃ¼fen Sie Zugangsdaten");
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
                throw new Exception("Keine gÃ¼ltige ISPConfig Session");
            }

            if ($this->debug_mode) {
                error_log("ISPConfig: Rufe E-Mail Accounts ab mit Filter: " . json_encode($filter));
            }

            // Strategie 1: Versuche mail_user_get mit primary_id (leer fÃ¼r alle)
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
                        error_log("ISPConfig: Ãœber IDs abgerufen: " . count($emails) . " E-Mails");
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
                error_log("ISPConfig: Keine E-Mails Ã¼ber alle Methoden gefunden");
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
                        // Versuche zugehÃ¶rige E-Mail-Accounts zu finden
                        // ISPConfig verknÃ¼pft oft E-Mails mit Domain-IDs
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
                        error_log("ISPConfig: Alternative Funktion {$function} erfolgreich: " . count($result) . " EintrÃ¤ge");
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
                        (is_array($result) ? count($result) . " EintrÃ¤ge" : "Einzelresultat"));
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

        // FÃ¼ge restliche gefundene Funktionen hinzu
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
     * Direkte SOAP-Calls fÃ¼r spezielle ISPConfig-Versionen
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
            // PrÃ¼fe ob SOAP Client verfÃ¼gbar ist
            if (!$this->client) {
                error_log('ISPConfig SOAP Client nicht verfÃ¼gbar');
                return false;
            }
            
            // ISPConfig API erwartet: session_id, reseller_id, params
            $reseller_id = $clientData['reseller_id'] ?? 0;
            unset($clientData['reseller_id']); // Entferne reseller_id aus den Parametern
            
            // Debug: Parameter ausgeben
            error_log('ISPConfig createClient - session_id: ' . $this->session_id . ', reseller_id: ' . $reseller_id . ', params: ' . print_r($clientData, true));
            
            $result = $this->client->client_add($this->session_id, $reseller_id, $clientData);
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

            // MAC-Adresse fÃ¼r jede IP abrufen
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
     * Holt alle Virtual MAC-Adressen fÃ¼r einen bestimmten Dedicated Server
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
     * Holt alle Virtual MAC-Adressen mit Details fÃ¼r einen Service
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
     * Holt alle IPs fÃ¼r eine Virtual MAC-Adresse
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
     * Holt Reverse-DNS Informationen fÃ¼r eine IP-Adresse
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
     * Holt alle Virtual MAC-Adressen fÃ¼r alle Dedicated Server
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
     * Holt alle Virtual MAC-Adressen mit ihren IPs und Reverse-DNS fÃ¼r einen Service
     */
    public function getAllVirtualMacDetailsWithIPs($serviceName) {
        $virtualMacs = $this->getAllVirtualMacDetails($serviceName);
        
        foreach ($virtualMacs as &$virtualMac) {
            // IPs fÃ¼r diese MAC-Adresse holen
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

                    // Reverse-DNS fÃ¼r diese IP holen
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
            $decoded = json_decode($response, true);
            return $decoded !== null ? $decoded : [];
        }

        return false;
    }

    /**
     * Holt und gibt alle Reverse-DNS-Details fÃ¼r alle IPs dynamisch aus OVH zurÃ¼ck.
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
                // Schritt 2: Reverse-IPs fÃ¼r jede IP abrufen
                $reverseList = $this->makeRequest('GET', "https://eu.api.ovh.com/1.0/ip/{$ipEncoded}/reverse");
                if (is_array($reverseList) && count($reverseList) > 0) {
                    foreach ($reverseList as $ipReverse) {
                        // Schritt 3: Details fÃ¼r jede Reverse-IP abrufen
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

// OVH POST CLASS - ERWEITERT FÃœR VIRTUAL MAC
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
        $response = $this->makeRequest('POST', $url, []);
        //$this->logRequest("/domain/zone/$domain/refresh", 'POST', $response !== false);
        return $response;
    }

    public function rebootVPS($vpsName) {
        $url = "https://eu.api.ovh.com/1.0/vps/$vpsName/reboot";
        $response = $this->makeRequest('POST', $url, []);
        //$this->logRequest("/vps/$vpsName/reboot", 'POST', $response !== false);
        return $response;
    }

    public function stopVPS($vpsName) {
        $url = "https://eu.api.ovh.com/1.0/vps/$vpsName/stop";
        $response = $this->makeRequest('POST', $url, []);
        //$this->logRequest("/vps/$vpsName/stop", 'POST', $response !== false);
        return $response;
    }

    public function startVPS($vpsName) {
        $url = "https://eu.api.ovh.com/1.0/vps/$vpsName/start";
        $response = $this->makeRequest('POST', $url, []);
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
     * LÃ¶scht eine Virtual MAC-Adresse
     */
    public function deleteVirtualMac($serviceName, $macAddress) {
        $url = "https://eu.api.ovh.com/1.0/dedicated/server/$serviceName/virtualMac/$macAddress";
        $response = $this->makeRequest('DELETE', $url);
        //$this->logRequest("/dedicated/server/$serviceName/virtualMac/$macAddress", 'DELETE', $response !== false);
        return $response;
    }

    /**
     * FÃ¼gt eine IP-Adresse zu einer Virtual MAC hinzu
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
     * LÃ¶scht einen Reverse-DNS Eintrag
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
        // Token erstellen fÃ¼r OGP API
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
    
    /**
     * Holt alle OGP-Server (Alias für getServerList)
     */
    public function getOGPServers() {
        return $this->getServerList();
    }
    
    /**
     * Holt aktive OGP-Server
     */
    public function getActiveOGPServers() {
        $servers = $this->getServerList();
        if (!$servers || !isset($servers['servers'])) {
            return [];
        }
        
        $activeServers = [];
        foreach ($servers['servers'] as $server) {
            $status = $this->getServerStatus($server['id']);
            if ($status && isset($status['status']) && $status['status'] === 'online') {
                $activeServers[] = $server;
            }
        }
        
        return $activeServers;
    }
    
    /**
     * Holt Online-Spieler von allen OGP-Servern
     */
    public function getOGPOnlinePlayers() {
        $servers = $this->getServerList();
        if (!$servers || !isset($servers['servers'])) {
            return ['total_players' => 0, 'servers' => []];
        }
        
        $totalPlayers = 0;
        $serverPlayers = [];
        
        foreach ($servers['servers'] as $server) {
            $status = $this->getServerStatus($server['id']);
            if ($status && isset($status['players'])) {
                $playerCount = count($status['players']);
                $totalPlayers += $playerCount;
                $serverPlayers[] = [
                    'server_id' => $server['id'],
                    'server_name' => $server['name'],
                    'players' => $playerCount,
                    'player_list' => $status['players']
                ];
            }
        }
        
        return [
            'total_players' => $totalPlayers,
            'servers' => $serverPlayers
        ];
    }
    
    /**
     * Holt Performance-Daten von OGP-Servern
     */
    public function getOGPServerPerformance() {
        $servers = $this->getServerList();
        if (!$servers || !isset($servers['servers'])) {
            return [];
        }
        
        $performance = [];
        foreach ($servers['servers'] as $server) {
            $status = $this->getServerStatus($server['id']);
            if ($status) {
                $performance[] = [
                    'server_id' => $server['id'],
                    'server_name' => $server['name'],
                    'cpu_usage' => $status['cpu_usage'] ?? 0,
                    'memory_usage' => $status['memory_usage'] ?? 0,
                    'disk_usage' => $status['disk_usage'] ?? 0,
                    'uptime' => $status['uptime'] ?? 0,
                    'status' => $status['status'] ?? 'unknown'
                ];
            }
        }
        
        return $performance;
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
    
    /**
     * Steuert einen OGP-Server (start, stop, restart, update)
     */
    public function controlOGPServer($serverId, $action) {
        switch ($action) {
            case 'start':
                return $this->startGameManager($serverId, 'default_port', 'default_mod');
            case 'stop':
                return $this->stopGameManager($serverId, 'default_port', 'default_mod');
            case 'restart':
                return $this->restartGameManager($serverId, 'default_port', 'default_mod');
            case 'update':
                return $this->updateGameManager($serverId, 'default_port', 'default_mod', 'auto');
            default:
                throw new Exception("Unbekannte Aktion: $action");
        }
    }
    
    /**
     * Erstellt einen neuen OGP-Server
     */
    public function createOGPServer($serverData) {
        $url = $this->host . "/ogp_api.php?server/create";
        $data = array_merge(['token' => $this->token], $serverData);
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("server/create", 'POST', $response !== false);
        return $response;
    }
    
    /**
     * Löscht einen OGP-Server
     */
    public function deleteOGPServer($serverId) {
        $url = $this->host . "/ogp_api.php?server/delete";
        $data = ['token' => $this->token, 'server_id' => $serverId];
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("server/delete/$serverId", 'POST', $response !== false);
        return $response;
    }
    
    /**
     * Aktualisiert OGP-Server-Einstellungen
     */
    public function updateOGPServer($serverId, $serverData) {
        $url = $this->host . "/ogp_api.php?server/update";
        $data = array_merge(['token' => $this->token, 'server_id' => $serverId], $serverData);
        $response = $this->makeRequest('POST', $url, $data);
        //$this->logRequest("server/update/$serverId", 'POST', $response !== false);
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
        

        // ISPConfig nur initialisieren wenn SOAP verfÃ¼gbar ist und aktiviert
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
            error_log("SOAP nicht verfÃ¼gbar - ISPConfig wird nicht initialisiert");
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
     * PrÃ¼ft ob eine API aktiviert ist
     * @param string $apiName Name der API (proxmox, ispconfig, ovh, ogp)
     * @return array|true Gibt true zurÃ¼ck wenn API aktiviert ist, sonst strukturierte Fehlermeldung
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
                    
                    $this->__log("checkAPIEnabled", $errorResponse, "error");
                    return $errorResponse;
                }
                if (!$this->proxmoxGet || !$this->proxmoxPost) {
                    $errorResponse = [
                        'success' => false,
                        'error' => 'API_NOT_INITIALIZED',
                        'message' => 'Proxmox API konnte nicht initialisiert werden',
                        'api' => 'proxmox',
                        'solution' => 'ÃœberprÃ¼fen Sie die Proxmox-Konfiguration in der config.inc.php'
                    ];                    
                    $this->__log("checkAPIEnabled", $errorResponse, "error");
                    return $errorResponse;
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
                    if ($db) {
                        $db->logAction(
                            "API Check: ISPConfig",
                            "API deaktiviert - Config: ISPCONFIG_USEING = false",
                            'error'
                        );
                    }
                    
                    $this->__log("checkAPIEnabled", $errorResponse, "error");
                    return $errorResponse;
                }
                if (!$this->ispconfigGet || !$this->ispconfigPost) {
                    $errorResponse = [
                        'success' => false,
                        'error' => 'API_NOT_INITIALIZED',
                        'message' => 'ISPConfig API konnte nicht initialisiert werden',
                        'api' => 'ispconfig',
                        'solution' => 'ÃœberprÃ¼fen Sie die ISPConfig-Konfiguration in der config.inc.php'
                    ];
                    
                    // Log API initialization failure to database
                    if ($db) {
                        $db->logAction(
                            "API Check: ISPConfig",
                            "API nicht initialisiert - ISPConfigGet/Post Objekte fehlen",
                            'error'
                        );
                    }
                    
                    $this->__log("checkAPIEnabled", $errorResponse, "error");
                    return $errorResponse;
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
                    if ($db) {
                        $db->logAction(
                            "API Check: OVH",
                            "API deaktiviert - Config: OVH_USEING = false",
                            'error'
                        );
                    }
                    
                    $this->__log("checkAPIEnabled", $errorResponse, "error");
                    return $errorResponse;
                }
                if (!$this->ovhGet || !$this->ovhPost) {
                    $errorResponse = [
                        'success' => false,
                        'error' => 'API_NOT_INITIALIZED',
                        'message' => 'OVH API konnte nicht initialisiert werden',
                        'api' => 'ovh',
                        'solution' => 'ÃœberprÃ¼fen Sie die OVH-Konfiguration in der config.inc.php'
                    ];
                    
                    // Log API initialization failure to database
                    if ($db) {
                        $db->logAction(
                            "API Check: OVH",
                            "API nicht initialisiert - OVHGet/Post Objekte fehlen",
                            'error'
                        );
                    }
                    
                    $this->__log("checkAPIEnabled", $errorResponse, "error");
                    return $errorResponse;
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
                    if ($db) {
                        $db->logAction(
                            "API Check: OGP",
                            "API deaktiviert - Config: OGP_USEING = false",
                            'error'
                        );
                    }
                    
                    $this->__log("checkAPIEnabled", $errorResponse, "error");
                    return $errorResponse;
                }
                if (!$this->ogpGet || !$this->ogpPost) {
                    $errorResponse = [
                        'success' => false,
                        'error' => 'API_NOT_INITIALIZED',
                        'message' => 'OGP API konnte nicht initialisiert werden',
                        'api' => 'ogp',
                        'solution' => 'ÃœberprÃ¼fen Sie die OGP-Konfiguration in der config.inc.php'
                    ];
                    
                    // Log API initialization failure to database
                    if ($db) {
                        $db->logAction(
                            "API Check: OGP",
                            "API nicht initialisiert - OGPGet/Post Objekte fehlen",
                            'error'
                        );
                    }
                    
                    $this->__log("checkAPIEnabled", $errorResponse, "error");
                    return $errorResponse;
                }
                break;
                
            case 'database':
                // Datenbank ist immer verfügbar, da sie für das System benötigt wird
                break;
                
            default:
                $errorResponse = [
                    'success' => false,
                    'error' => 'UNKNOWN_API',
                    'message' => 'Unbekannte API: ' . $apiName,
                    'api' => $apiName
                ];                
                $this->__log("checkAPIEnabled", $errorResponse, "error");
                return $errorResponse;
        }
        return true;
    }
    
    /**
     * Zusätzliche Validierung der API-Objekte
     * @param string $apiName Name der API
     * @param object $getObject Das GET-Objekt der API
     * @param object $postObject Das POST-Objekt der API
     * @return array|true Gibt true zurück wenn Objekte gültig sind, sonst Fehlermeldung
     */
    private function validateAPIObjects($apiName, $getObject, $postObject) {
        if (!$getObject || !$postObject) {
            return [
                'success' => false,
                'error' => 'API_OBJECTS_NULL',
                'message' => "$apiName API Objekte sind null",
                'api' => $apiName,
                'solution' => 'Überprüfen Sie die API-Initialisierung'
            ];
        }
        return true;
    }
    
    /**
     * Sicherer API-Aufruf mit Null-Check
     * @param string $apiName Name der API
     * @param object $apiObject Das API-Objekt
     * @param string $methodName Name der aufzurufenden Methode
     * @param array $params Parameter für den Methodenaufruf
     * @return mixed API Response oder Fehlermeldung
     */
    private function safeAPICall($apiName, $apiObject, $methodName, $params = []) {
        if (!$apiObject) {
            return [
                'success' => false,
                'error' => 'API_NOT_INITIALIZED',
                'message' => "$apiName API nicht initialisiert",
                'api' => $apiName,
                'method' => $methodName
            ];
        }
        
        try {
            return call_user_func_array([$apiObject, $methodName], $params);
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'API_CALL_FAILED',
                'message' => "$apiName API Aufruf fehlgeschlagen: " . $e->getMessage(),
                'api' => $apiName,
                'method' => $methodName
            ];
        }
    }
    

	
	 
    // GENERISCHE API FUNKTIONEN
     
    /**
     * Generische Proxmox API Funktion
     * @param string $type HTTP-Methode (get, post, delete, put)
     * @param string $url API-Pfad (z.B. "/nodes/pve/qemu/100/status/start")
     * @param mixed $code Optionale Daten fÃ¼r POST/PUT Requests
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
        }// Expliziter Null-Check fÃ¼r zusÃ¤tzliche Sicherheit
        if (!$this->proxmoxGet) {
            return [
                'success' => false,
                'error' => 'API_NOT_INITIALIZED',
                'message' => 'Proxmox API nicht initialisiert',
                'api' => 'proxmox'
            ];
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
     * @param mixed $code Optionale Daten fÃ¼r POST/PUT Requests
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
        }// Expliziter Null-Check fÃ¼r zusÃ¤tzliche Sicherheit
        if (!$this->ovhGet) {
            return [
                'success' => false,
                'error' => 'API_NOT_INITIALIZED',
                'message' => 'OVH API nicht initialisiert',
                'api' => 'ovh'
            ];
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
     * @param mixed $code Optionale Daten fÃ¼r POST/PUT Requests
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
        }// Expliziter Null-Check fÃ¼r zusÃ¤tzliche Sicherheit
        if (!$this->ispconfigGet || !$this->ispconfigPost) {
            return [
                'success' => false,
                'error' => 'API_NOT_INITIALIZED',
                'message' => 'ISPConfig API nicht initialisiert',
                'api' => 'ispconfig'
            ];
        }
        
        try {
            $type = strtolower($type);
            
            // ISPConfig verwendet SOAP, daher mÃ¼ssen wir die URL als Funktionsname interpretieren
            // Entferne fÃ¼hrende Slashes
            $function = ltrim($url, '/');
            
            // Bestimme die richtige SOAP-Funktion basierend auf Type und URL
            switch($type) {
                case 'get':
                    // Keine automatische Suffix-AnfÃ¼gung mehr - verwende den Funktionsnamen direkt
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
                    // FÃ¼r Updates brauchen wir: session_id, client_id, primary_id, params
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
                    // FÃ¼r Delete brauchen wir die ID
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
     * Hilfsfunktion fÃ¼r erweiterte ISPConfig Operationen
     * Erlaubt direkten Zugriff auf SOAP-Funktionen
     */
    public function IspconfigSOAP($function, $params = []) {
        $apiCheck = $this->checkAPIEnabled('ispconfig');
        if ($apiCheck !== true) {
            return $apiCheck;
        }// ZusÃ¤tzlicher Null-Check fÃ¼r zusÃ¤tzliche Sicherheit
        if (!$this->ispconfigGet || !$this->ispconfigGet->client) {
            return [
                'success' => false,
                'error' => 'SOAP_CLIENT_NOT_AVAILABLE',
                'message' => 'ISPConfig SOAP Client nicht verfÃ¼gbar',
                'api' => 'ispconfig',
                'function' => $function
            ];
        }
        
        try {
            // FÃ¼ge session_id als ersten Parameter hinzu
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
     * @param mixed $code Optionale Daten fÃ¼r POST/PUT Requests
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
        }// Expliziter Null-Check fÃ¼r zusÃ¤tzliche Sicherheit
        if (!$this->ogpGet) {
            return [
                'success' => false,
                'error' => 'API_NOT_INITIALIZED',
                'message' => 'OGP API nicht initialisiert',
                'api' => 'ogp'
            ];
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
        
        // Expliziter Null-Check für zusätzliche Sicherheit
        if (!$this->proxmoxGet) {
            return [
                'success' => false,
                'error' => 'API_NOT_INITIALIZED',
                'message' => 'Proxmox API nicht initialisiert',
                'api' => 'proxmox'
            ];
        }
        
        return $this->proxmoxGet->getVMs();
    }

    public function getProxmoxLXCs() {
        $apiCheck = $this->checkAPIEnabled('proxmox');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        // Expliziter Null-Check für zusätzliche Sicherheit
        if (!$this->proxmoxGet) {
            return [
                'success' => false,
                'error' => 'API_NOT_INITIALIZED',
                'message' => 'Proxmox API nicht initialisiert',
                'api' => 'proxmox'
            ];
        }
        
        return $this->proxmoxGet->getLXCs();
    }
    public function getProxmoxNodes() {
        $apiCheck = $this->checkAPIEnabled('proxmox');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        // Expliziter Null-Check für zusätzliche Sicherheit
        if (!$this->proxmoxGet) {
            return [
                'success' => false,
                'error' => 'API_NOT_INITIALIZED',
                'message' => 'Proxmox API nicht initialisiert',
                'api' => 'proxmox'
            ];
        }
        
        return $this->proxmoxGet->getNodes();
    }
    public function getProxmoxStorage() {
        $apiCheck = $this->checkAPIEnabled('proxmox');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        // Expliziter Null-Check für zusätzliche Sicherheit
        if (!$this->proxmoxGet) {
            return [
                'success' => false,
                'error' => 'API_NOT_INITIALIZED',
                'message' => 'Proxmox API nicht initialisiert',
                'api' => 'proxmox'
            ];
        }
        
        return $this->safeAPICall('proxmox', $this->proxmoxGet, 'getStorages', []);
    }

    /**
     * Gibt grundlegende Systeminformationen zurück
     * @return array Array mit Systeminformationen
     */
    public function getSystemInfo() {
        try {
            $systemInfo = [
                'uptime' => $this->getSystemUptime(),
                'memory' => $this->getSystemMemory(),
                'disk' => $this->getSystemDisk(),
                'load' => $this->getSystemLoad(),
                'php_version' => PHP_VERSION,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            return $systemInfo;
        } catch (Exception $e) {
            error_log("SystemInfo Error: " . $e->getMessage());
            return [
                'uptime' => 'N/A',
                'memory' => 'N/A',
                'disk' => 'N/A',
                'load' => 'N/A',
                'php_version' => PHP_VERSION,
                'server_software' => 'Unknown',
                'timestamp' => date('Y-m-d H:i:s'),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Holt die System-Uptime
     * @return string Uptime als formatierter String
     */
    private function getSystemUptime() {
        // Methode 1: PHP-interne Funktionen (sicher)
        if (function_exists('sys_getloadavg')) {
            $uptime = sys_getloadavg();
            if ($uptime !== false && isset($uptime[0])) {
                return number_format($uptime[0], 2) . ' (1min)';
            }
        }
        
        // Methode 2: Versuche über exec zu holen (nur auf Linux/Unix)
        if (function_exists('exec') && !in_array('exec', explode(',', ini_get('disable_functions')))) {
            // Versuche verschiedene Uptime-Befehle
            $commands = [
                'uptime -p 2>/dev/null',
                'cat /proc/uptime 2>/dev/null',
                'ps -eo etime= -p 1 2>/dev/null'
            ];
            
            foreach ($commands as $command) {
                $uptime = @exec($command);
                if ($uptime) {
                    // Verarbeite uptime -p Format
                    if (strpos($command, 'uptime -p') !== false) {
                        return trim($uptime);
                    }
                    
                    // Verarbeite /proc/uptime Format
                    if (strpos($command, '/proc/uptime') !== false) {
                        $seconds = (float) explode(' ', $uptime)[0];
                        return $this->formatUptime($seconds);
                    }
                    
                    // Verarbeite ps Format
                    if (strpos($command, 'ps -eo etime') !== false) {
                        return trim($uptime);
                    }
                }
            }
        }
        
        // Methode 3: PHP-interne Zeitstempel als Fallback
        // Verwende einen sicheren Fallback-Wert
        return 'N/A';
    }

    /**
     * Formatiert Uptime in Sekunden zu lesbarem Format
     * @param int $seconds Anzahl der Sekunden
     * @return string Formatierte Uptime
     */
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

    /**
     * Holt die Speicherinformationen
     * @return array Array mit Speicherinformationen
     */
    private function getSystemMemory() {
        $memory = [];
        
        if (function_exists('memory_get_usage')) {
            $memory['used'] = $this->formatBytes(memory_get_usage(true));
            $memory['peak'] = $this->formatBytes(memory_get_peak_usage(true));
        }
        
        // Versuche System-Speicher zu holen (Linux/Unix)
        if (function_exists('exec') && !in_array('exec', explode(',', ini_get('disable_functions')))) {
            $memInfo = @exec('free -h 2>/dev/null');
            if ($memInfo) {
                $lines = explode("\n", $memInfo);
                if (isset($lines[1])) {
                    $parts = preg_split('/\s+/', trim($lines[1]));
                    if (count($parts) >= 3) {
                        $memory['total'] = $parts[1];
                        $memory['used'] = $parts[2];
                        $memory['free'] = $parts[3];
                    }
                }
            }
        }
        
        return $memory;
    }

    /**
     * Holt die Festplatteninformationen
     * @return array Array mit Festplatteninformationen
     */
    private function getSystemDisk() {
        $disk = [];
        
        if (function_exists('disk_free_space') && function_exists('disk_total_space')) {
            // Versuche verschiedene Verzeichnisse, die wahrscheinlich verfügbar sind
            $directories = [
                __DIR__, // Aktuelles Verzeichnis
                dirname(__DIR__), // Übergeordnetes Verzeichnis
                '/tmp', // Temp-Verzeichnis
                '/var/tmp' // Alternative Temp-Verzeichnis
            ];
            
            $free = false;
            $total = false;
            
            foreach ($directories as $dir) {
                if (is_dir($dir) && is_readable($dir)) {
                    $free = disk_free_space($dir);
                    $total = disk_total_space($dir);
                    
                    if ($free !== false && $total !== false) {
                        break; // Erfolgreich, beende die Schleife
                    }
                }
            }
            
            if ($free !== false && $total !== false) {
                $used = $total - $free;
                $percent = round(($used / $total) * 100, 2);
                
                $disk['total'] = $this->formatBytes($total);
                $disk['used'] = $this->formatBytes($used);
                $disk['free'] = $this->formatBytes($free);
                $disk['percent'] = $percent;
            } else {
                // Fallback: Versuche über exec zu holen (nur auf Linux/Unix)
                if (function_exists('exec') && !in_array('exec', explode(',', ini_get('disable_functions')))) {
                    $dfOutput = @exec('df -h / 2>/dev/null');
                    if ($dfOutput) {
                        $lines = explode("\n", $dfOutput);
                        if (isset($lines[1])) {
                            $parts = preg_split('/\s+/', trim($lines[1]));
                            if (count($parts) >= 5) {
                                $disk['total'] = $parts[1];
                                $disk['used'] = $parts[2];
                                $disk['free'] = $parts[3];
                                $disk['percent'] = rtrim($parts[4], '%');
                            }
                        }
                    }
                }
            }
        }
        
        // Wenn immer noch keine Daten verfügbar sind, setze Standardwerte
        if (empty($disk)) {
            $disk = [
                'total' => 'N/A',
                'used' => 'N/A',
                'free' => 'N/A',
                'percent' => 'N/A',
                'note' => 'open_basedir restriction'
            ];
        }
        
        return $disk;
    }

    /**
     * Holt die System-Last
     * @return array Array mit Load-Average Informationen
     */
    private function getSystemLoad() {
        $load = [];
        
        if (function_exists('sys_getloadavg')) {
            $loadAvg = sys_getloadavg();
            if ($loadAvg !== false) {
                $load['1min'] = number_format($loadAvg[0], 2);
                $load['5min'] = number_format($loadAvg[1], 2);
                $load['15min'] = number_format($loadAvg[2], 2);
            }
        }
        
        // Fallback: Versuche über exec zu holen
        if (function_exists('exec') && !in_array('exec', explode(',', ini_get('disable_functions')))) {
            $loadAvg = @exec('cat /proc/loadavg 2>/dev/null');
            if ($loadAvg) {
                $parts = explode(' ', $loadAvg);
                if (count($parts) >= 3) {
                    $load['1min'] = number_format((float) $parts[0], 2);
                    $load['5min'] = number_format((float) $parts[1], 2);
                    $load['15min'] = number_format((float) $parts[2], 2);
                }
            }
        }
        
        return $load;
    }

    /**
     * Formatiert Bytes in lesbare Einheiten
     * @param int $bytes Anzahl der Bytes
     * @return string Formatierte Größe
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    public function getProxmoxNetwork() {
        $apiCheck = $this->checkAPIEnabled('proxmox');
        if ($apiCheck !== true) {
            return $apiCheck;
        }$networks = [];
        $nodes = $this->getProxmoxNodes();
        
        // PrÃ¼fe ob nodes erfolgreich abgerufen wurden
        if (!is_array($nodes)) {
            return $nodes; // Gibt den Fehler zurÃ¼ck
        }
        
        foreach ($nodes as $nodeData) {
            $nodeName = is_array($nodeData) ? $nodeData['node'] : $nodeData;
            // Expliziter Null-Check für zusätzliche Sicherheit
            if (!$this->proxmoxGet) {
                return [
                    'success' => false,
                    'error' => 'API_NOT_INITIALIZED',
                    'message' => 'Proxmox API nicht initialisiert',
                    'api' => 'proxmox'
                ];
            }
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
        
        // Expliziter Null-Check für zusätzliche Sicherheit
        if (!$this->proxmoxPost) {
            return [
                'success' => false,
                'error' => 'API_NOT_INITIALIZED',
                'message' => 'Proxmox API nicht initialisiert',
                'api' => 'proxmox'
            ];
        }
        
        return $this->proxmoxPost->createVM($vmData);
    }

    public function controlProxmoxVM($node, $vmid, $action, $type = 'qemu') {
        $apiCheck = $this->checkAPIEnabled('proxmox');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        // Wähle die richtige Funktion basierend auf dem Typ
        $functionPrefix = $type === 'lxc' ? 'LXC' : 'VM';
        
        switch ($action) {
            case 'start':
                return $this->safeAPICall('proxmox', $this->proxmoxPost, 'start' . $functionPrefix, [$node, $vmid]);
            case 'stop':
                return $this->safeAPICall('proxmox', $this->proxmoxPost, 'stop' . $functionPrefix, [$node, $vmid]);
            case 'reboot':
                return $this->safeAPICall('proxmox', $this->proxmoxPost, 'reboot' . $functionPrefix, [$node, $vmid]);
            case 'reset':
                // Reset funktioniert nur für QEMU, nicht für LXC
                if ($type === 'lxc') {
                    return [
                        'success' => false,
                        'error' => 'UNSUPPORTED_ACTION',
                        'message' => 'Reset wird für LXC-Container nicht unterstützt'
                    ];
                }
                return $this->safeAPICall('proxmox', $this->proxmoxPost, 'resetVM', [$node, $vmid]);
            case 'suspend':
                return $this->safeAPICall('proxmox', $this->proxmoxPost, 'suspend' . $functionPrefix, [$node, $vmid]);
            case 'resume':
                return $this->safeAPICall('proxmox', $this->proxmoxPost, 'resume' . $functionPrefix, [$node, $vmid]);
            default:
                return [
                    'success' => false,
                    'error' => 'INVALID_ACTION',
                    'message' => 'Ungültige Aktion: ' . $action,
                    'api' => 'proxmox',
                    'valid_actions' => ['start', 'stop', 'reboot', 'reset', 'suspend', 'resume']
                ];
        }
    }

    public function deleteProxmoxVM($node, $vmid, $type = 'qemu') {
        $apiCheck = $this->checkAPIEnabled('proxmox');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        // Wähle die richtige Funktion basierend auf dem Typ
        $functionName = $type === 'lxc' ? 'deleteLXC' : 'deleteVM';
        
        return $this->safeAPICall('proxmox', $this->proxmoxPost, $functionName, [$node, $vmid]);
    }

    // ISPConfig Methods
    public function getISPConfigWebsites() {
        $apiCheck = $this->checkAPIEnabled('ispconfig');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ispconfig', $this->ispconfigGet, 'getWebsites', [['active' => 'y']]);
    }

    public function createISPConfigWebsite($websiteData) {
        $apiCheck = $this->checkAPIEnabled('ispconfig');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ispconfig', $this->ispconfigPost, 'createWebsite', [$websiteData]);
    }

    public function deleteISPConfigWebsite($domainId) {
        $apiCheck = $this->checkAPIEnabled('ispconfig');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ispconfig', $this->ispconfigPost, 'deleteWebsite', [$domainId]);
    }

    public function getISPConfigDatabases() {
        $apiCheck = $this->checkAPIEnabled('ispconfig');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ispconfig', $this->ispconfigGet, 'getDatabases', [['active' => 'y']]);
    }

    public function createISPConfigDatabase($dbData) {
        $apiCheck = $this->checkAPIEnabled('ispconfig');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ispconfig', $this->ispconfigPost, 'createDatabase', [$dbData]);
    }

    public function deleteISPConfigDatabase($databaseId) {
        $apiCheck = $this->checkAPIEnabled('ispconfig');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ispconfig', $this->ispconfigPost, 'deleteDatabase', [$databaseId]);
    }

    public function getISPConfigEmails() {
        $apiCheck = $this->checkAPIEnabled('ispconfig');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ispconfig', $this->ispconfigGet, 'getEmailAccounts', [['active' => 'y']]);
    }

    public function createISPConfigEmail($emailData) {
        $apiCheck = $this->checkAPIEnabled('ispconfig');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ispconfig', $this->ispconfigPost, 'createEmailAccount', [$emailData]);
    }

    public function deleteISPConfigEmail($mailuserId) {
        $apiCheck = $this->checkAPIEnabled('ispconfig');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ispconfig', $this->ispconfigPost, 'deleteEmailAccount', [$mailuserId]);
    }
    
    public function getISPConfigClients($filter = []) {
        $apiCheck = $this->checkAPIEnabled('ispconfig');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ispconfig', $this->ispconfigGet, 'getClients', [$filter]);
    }
    
    public function createISPConfigClient($clientData) {
        $apiCheck = $this->checkAPIEnabled('ispconfig');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ispconfig', $this->ispconfigPost, 'createClient', [$clientData]);
    }
    
    public function updateISPConfigClient($clientId, $clientData) {
        $apiCheck = $this->checkAPIEnabled('ispconfig');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ispconfig', $this->ispconfigPost, 'updateClient', [$clientId, $clientData]);
    }
    
    public function deleteISPConfigClient($clientId) {
        $apiCheck = $this->checkAPIEnabled('ispconfig');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ispconfig', $this->ispconfigPost, 'deleteClient', [$clientId]);
    }

    // OVH Methods
    public function getOVHDomains() {
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ovh', $this->ovhGet, 'getDomains', []);
    }

    public function orderOVHDomain($domain, $duration) {
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ovh', $this->ovhPost, 'orderDomain', [$domain, $duration]);
    }

    public function getOVHVPS() {
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ovh', $this->ovhGet, 'getVPSList', []);
    }

    public function getOvhIP(){
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ovh', $this->ovhGet, 'getAllIPReverseDetails', []);
    }
    
    public function getOVHFailoverIPs() {
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        $failoverIPs = $this->safeAPICall('ovh', $this->ovhGet, 'getFailoverIPs', []);
        
        // PrÃ¼fe ob failoverIPs erfolgreich abgerufen wurden
        if (!is_array($failoverIPs)) {
            return $failoverIPs; // Gibt den Fehler zurÃ¼ck
        }
        
        $detailedIPs = [];

        if (!empty($failoverIPs)) {
            foreach ($failoverIPs as $ip) {
                // The getFailoverIPs method returns a list of IP strings.
                // Each string can be a single IP or a block (e.g., x.x.x.x/32).
                // We pass this string directly to getFailoverIPDetails.
                $details = $this->safeAPICall('ovh', $this->ovhGet, 'getFailoverIPDetails', [$ip]);
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
        
        $vps = $this->safeAPICall('ovh', $this->ovhGet, 'getVPS', [$vpsName]);
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
     * Holt alle Virtual MAC-Adressen fÃ¼r einen bestimmten Service
     */
    public function getVirtualMacAddresses($serviceName = null) {
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        if ($serviceName) {
            return $this->safeAPICall('ovh', $this->ovhGet, 'getAllVirtualMacDetailsWithIPs', [$serviceName]);
        } else {
            return $this->safeAPICall('ovh', $this->ovhGet, 'getAllVirtualMacAddresses', []);
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
        
        return $this->safeAPICall('ovh', $this->ovhGet, 'getVirtualMacDetails', [$serviceName, $macAddress]);
    }

    /**
     * Erstellt eine neue Virtual MAC-Adresse
     */
    public function createVirtualMac($serviceName, $virtualNetworkInterface, $type = 'ovh') {
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ovh', $this->ovhPost, 'createVirtualMac', [$serviceName, $virtualNetworkInterface, $type]);
    }

    /**
     * LÃ¶scht eine Virtual MAC-Adresse
     */
    public function deleteVirtualMac($serviceName, $macAddress) {
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ovh', $this->ovhPost, 'deleteVirtualMac', [$serviceName, $macAddress]);
    }

    /**
     * FÃ¼gt IP zu Virtual MAC hinzu
     */
    public function addIPToVirtualMac($serviceName, $macAddress, $ipAddress, $virtualNetworkInterface) {
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ovh', $this->ovhPost, 'addVirtualMacIP', [$serviceName, $macAddress, $ipAddress, $virtualNetworkInterface]);
    }

    /**
     * Entfernt IP von Virtual MAC
     */
    public function removeIPFromVirtualMac($serviceName, $macAddress, $ipAddress) {
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ovh', $this->ovhPost, 'removeVirtualMacIP', [$serviceName, $macAddress, $ipAddress]);
    }

    // Reverse DNS Methods
    public function getIPReverse($ipAddress) {
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ovh', $this->ovhGet, 'getIPReverse', [$ipAddress]);
    }

    public function createIPReverse($ipAddress, $reverse) {
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ovh', $this->ovhPost, 'createIPReverse', [$ipAddress, $reverse]);
    }

    public function updateIPReverse($ipAddress, $reverseIP, $newReverse) {
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ovh', $this->ovhPost, 'updateIPReverse', [$ipAddress, $reverseIP, $newReverse]);
    }

    public function deleteIPReverse($ipAddress, $reverseIP) {
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ovh', $this->ovhPost, 'deleteIPReverse', [$ipAddress, $reverseIP]);
    }

    // Convenience Methods
    public function getCompleteVirtualMacInfo($serviceName = null) {
        $apiCheck = $this->checkAPIEnabled('ovh');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        $result = [];
        
        if ($serviceName) {
            $servers = [$serviceName];
        } else {
            $servers = $this->safeAPICall('ovh', $this->ovhGet, 'getDedicatedServers', []);
            // PrÃ¼fe ob servers erfolgreich abgerufen wurden
            if (!is_array($servers)) {
                return $servers; // Gibt den Fehler zurÃ¼ck
            }
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

                // IPs zÃ¤hlen
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
        
        return $this->safeAPICall('ogp', $this->ogpGet, 'testToken', []);
    }
    
    // Remote Servers
    public function getOGPServerList() {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpGet, 'getServerList', []);
    }
    
    public function getOGPServerStatus($remoteServerId) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpGet, 'getServerStatus', [$remoteServerId]);
    }
    
    public function getOGPServerIPs($remoteServerId) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpGet, 'getServerIPs', [$remoteServerId]);
    }
    
    public function restartOGPServer($remoteServerId) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpPost, 'restartServer', [$remoteServerId]);
    }
    
    public function createOGPServer($serverData) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpPost, 'createServer', [$serverData]);
    }
    
    public function removeOGPServer($remoteServerId) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpPost, 'removeServer', [$remoteServerId]);
    }
    
    public function addOGPServerIP($remoteServerId, $ip) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpPost, 'addServerIP', [$remoteServerId, $ip]);
    }
    
    public function removeOGPServerIP($remoteServerId, $ip) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpPost, 'removeServerIP', [$remoteServerId, $ip]);
    }
    
    public function editOGPServerIP($remoteServerId, $oldIp, $newIp) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpPost, 'editServerIP', [$remoteServerId, $oldIp, $newIp]);
    }
    
    // Game Servers
    public function getOGPGamesList($system = 'linux', $architecture = '64') {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpGet, 'getGamesList', [$system, $architecture]);
    }
    
    public function getOGPGameServers() {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpGet, 'getGameServers', []);
    }
    
    public function createOGPGameServer($gameServerData) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpPost, 'createGameServer', [$gameServerData]);
    }
    
    public function cloneOGPGameServer($cloneData) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpPost, 'cloneGameServer', [$cloneData]);
    }
    
    public function setOGPGameServerExpiration($homeId, $timestamp) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpPost, 'setGameServerExpiration', [$homeId, $timestamp]);
    }
    
    // Users
    public function getOGPUsers() {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpGet, 'getUsers', []);
    }
    
    public function getOGPUser($email) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpGet, 'getUser', [$email]);
    }
    
    public function getOGPUserAssigned($email) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpGet, 'getUserAssigned', [$email]);
    }
    
    // Alte createOGPUser Methode entfernt - wird durch die neue erweiterte Version ersetzt
    
    public function removeOGPUser($email) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpPost, 'removeUser', [$email]);
    }
    
    public function setOGPUserExpiration($email, $timestamp) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpPost, 'setUserExpiration', [$email, $timestamp]);
    }
    
    public function assignOGPUser($email, $homeId, $timestamp = null) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpPost, 'assignUser', [$email, $homeId, $timestamp]);
    }
    
    public function removeOGPUserAssignment($email, $homeId) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpPost, 'removeUserAssignment', [$email, $homeId]);
    }

    // PROXMOX USER MANAGEMENT
    
    // Alte createProxmoxUser Methode entfernt - wird durch die neue erweiterte Version ersetzt

    public function deleteProxmoxUser($userid) {
        $apiCheck = $this->checkAPIEnabled('proxmox');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('proxmox', $this->proxmoxPost, 'deleteProxmoxUser', [$userid]);
    }

    public function updateProxmoxUser($userid, $userData) {
        $apiCheck = $this->checkAPIEnabled('proxmox');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('proxmox', $this->proxmoxPost, 'updateProxmoxUser', [$userid, $userData]);
    }

    public function getProxmoxUsers() {
        $apiCheck = $this->checkAPIEnabled('proxmox');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('proxmox', $this->proxmoxPost, 'getProxmoxUsers', []);
    }

    public function getProxmoxUser($userid) {
        $apiCheck = $this->checkAPIEnabled('proxmox');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('proxmox', $this->proxmoxPost, 'getProxmoxUser', [$userid]);
    }
    
    // Game Manager
    public function startOGPGameManager($ip, $port, $modKey) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpPost, 'startGameManager', [$ip, $port, $modKey]);
    }
    
    public function stopOGPGameManager($ip, $port, $modKey) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpPost, 'stopGameManager', [$ip, $port, $modKey]);
    }
    
    public function restartOGPGameManager($ip, $port, $modKey) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpPost, 'restartGameManager', [$ip, $port, $modKey]);
    }
    
    public function sendOGPRconCommand($ip, $port, $modKey, $command) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpPost, 'sendRconCommand', [$ip, $port, $modKey, $command]);
    }
    
    public function updateOGPGameManager($ip, $port, $modKey, $type, $manualUrl = null) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpPost, 'updateGameManager', [$ip, $port, $modKey, $type, $manualUrl]);
    }
    
    // Lite File Manager
    public function listOGPFiles($ip, $port, $relativePath) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpPost, 'listFiles', [$ip, $port, $relativePath]);
    }
    
    public function getOGPFile($ip, $port, $relativePath) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpPost, 'getFile', [$ip, $port, $relativePath]);
    }
    
    public function saveOGPFile($ip, $port, $relativePath, $contents) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpPost, 'saveFile', [$ip, $port, $relativePath, $contents]);
    }
    
    public function removeOGPFile($ip, $port, $relativePath) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpPost, 'removeFile', [$ip, $port, $relativePath]);
    }
    
    // Addons Manager
    public function getOGPAddonsList() {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpGet, 'getAddonsList', []);
    }
    
    public function installOGPAddon($ip, $port, $modKey, $addonId) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpPost, 'installAddon', [$ip, $port, $modKey, $addonId]);
    }
    
    // Steam Workshop
    public function installOGPSteamWorkshop($ip, $port, $modsList) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpPost, 'installSteamWorkshop', [$ip, $port, $modsList]);
    }
    
    // Panel Settings
    public function getOGPSetting($settingName) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpGet, 'getSetting', [$settingName]);
    }
    
    /**
     * Erstellt Benutzer in allen aktivierten Systemen
     * @param string $username Benutzername
     * @param array $systemPasswords Array mit Passwörtern für jedes System
     * @param string $firstName Vorname
     * @param string $lastName Nachname
     * @param array $additionalData Zusätzliche Daten (E-Mail, Firma, etc.)
     * @return array Ergebnis der Benutzererstellung
     */
    public function createUserInAllSystems($username, $systemPasswords, $firstName, $lastName, $additionalData = []) {
        $results = [];
        $errors = [];
        $success = true;
        
        try {
            // ISPConfig Benutzer erstellen
            if (Config::ISPCONFIG_USEING && isset($systemPasswords['ispconfig'])) {
                try {
                    $clientData = [
                        'company_name' => $additionalData['company'] ?? '',
                        'contact_firstname' => $firstName,
                        'contact_name' => $lastName,
                        'email' => $additionalData['email'] ?? '',
                        'username' => $username,
                        'password' => $systemPasswords['ispconfig'],
                        'language' => 'de',
                        'usertheme' => 'default',
                        'template_master' => 0,
                        'template_additional' => '',
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $result = $this->createISPConfigClient($clientData);
                    if ($result && !isset($result['error'])) {
                        $results['ispconfig'] = [
                            'success' => true,
                            'username' => $username,
                            'system' => 'ISPConfig',
                            'message' => 'Benutzer erfolgreich erstellt'
                        ];
                        $this->__log("User Creation", "ISPConfig Benutzer $username erfolgreich erstellt", "success");
                    } else {
                        $errors['ispconfig'] = $result['message'] ?? 'Unbekannter Fehler';
                        $success = false;
                        $this->__log("User Creation", "ISPConfig Benutzer $username Fehler: " . ($result['message'] ?? 'Unbekannt'), "error");
                    }
                } catch (Exception $e) {
                    $errors['ispconfig'] = $e->getMessage();
                    $success = false;
                    $this->__log("User Creation", "ISPConfig Benutzer $username Exception: " . $e->getMessage(), "error");
                }
            }
            
            // OGP Benutzer erstellen
            if (Config::OGP_USEING && isset($systemPasswords['ogp'])) {
                try {
                    $userData = [
                        'firstname' => $firstName,
                        'lastname' => $lastName,
                        'email' => $additionalData['email'] ?? '',
                        'password' => $systemPasswords['ogp'],
                        'phone' => $additionalData['phone'] ?? '',
                        'role' => 'user'
                    ];
                    
                    $result = $this->createOGPUser($userData);
                    if ($result && !isset($result['error'])) {
                        $results['ogp'] = [
                            'success' => true,
                            'username' => $firstName . ' ' . $lastName,
                            'system' => 'OpenGamePanel',
                            'message' => 'Benutzer erfolgreich erstellt'
                        ];
                        $this->__log("User Creation", "OGP Benutzer $username erfolgreich erstellt", "success");
                    } else {
                        $errors['ogp'] = $result['message'] ?? 'Unbekannter Fehler';
                        $success = false;
                        $this->__log("User Creation", "OGP Benutzer $username Fehler: " . ($result['message'] ?? 'Unbekannt'), "error");
                    }
                } catch (Exception $e) {
                    $errors['ogp'] = $e->getMessage();
                    $success = false;
                    $this->__log("User Creation", "OGP Benutzer $username Exception: " . $e->getMessage(), "error");
                }
            }
            
            // Proxmox Benutzer erstellen
            if (Config::PROXMOX_USEING && isset($systemPasswords['proxmox'])) {
                try {
                    $userData = [
                        'username' => $username,
                        'realm' => 'pve',
                        'password' => $systemPasswords['proxmox'],
                        'comment' => $firstName . ' ' . $lastName,
                        'email' => $additionalData['email'] ?? '',
                        'first_name' => $firstName,
                        'last_name' => $lastName
                    ];
                    
                    $result = $this->createProxmoxUser($userData);
                    if ($result && !isset($result['error'])) {
                        $results['proxmox'] = [
                            'success' => true,
                            'username' => $username,
                            'system' => 'Proxmox',
                            'message' => 'Benutzer erfolgreich erstellt'
                        ];
                        $this->__log("User Creation", "Proxmox Benutzer $username erfolgreich erstellt", "success");
                    } else {
                        $errors['proxmox'] = $result['message'] ?? 'Unbekannter Fehler';
                        $success = false;
                        $this->__log("User Creation", "Proxmox Benutzer $username Fehler: " . ($result['message'] ?? 'Unbekannt'), "error");
                    }
                } catch (Exception $e) {
                    $errors['proxmox'] = $e->getMessage();
                    $success = false;
                    $this->__log("User Creation", "Proxmox Benutzer $username Exception: " . $e->getMessage(), "error");
                }
            }
            
        } catch (Exception $e) {
            $this->__log("User Creation", "Allgemeiner Fehler bei Benutzererstellung für $username: " . $e->getMessage(), "error");
            return [
                'success' => false,
                'error' => 'GENERAL_ERROR',
                'message' => 'Allgemeiner Fehler bei der Benutzererstellung: ' . $e->getMessage(),
                'results' => $results,
                'errors' => $errors
            ];
        }
        
        return [
            'success' => $success,
            'results' => $results,
            'errors' => $errors,
            'message' => $success ? 'Alle Benutzer erfolgreich erstellt' : 'Einige Benutzer konnten nicht erstellt werden'
        ];
    }
    
    /**
     * Erstellt OGP Benutzer (erweiterte Methode)
     */
    public function createOGPUser($userData) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpPost, 'createUser', [$userData]);
    }
    
    /**
     * Erstellt Proxmox Benutzer (erweiterte Methode)
     */
    public function createProxmoxUser($userData) {
        $apiCheck = $this->checkAPIEnabled('proxmox');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('proxmox', $this->proxmoxPost, 'createProxmoxUser', [$userData]);
    }
    
    /**
     * OGP/Gameserver Funktionen
     */
    
    /**
     * Holt alle OGP-Server
     */
    public function getOGPServers() {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpGet, 'getOGPServers', []);
    }
    
    /**
     * Holt aktive OGP-Server
     */
    public function getActiveOGPServers() {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpGet, 'getActiveOGPServers', []);
    }
    
    /**
     * Holt Online-Spieler von OGP-Servern
     */
    public function getOGPOnlinePlayers() {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpGet, 'getOGPOnlinePlayers', []);
    }
    
    /**
     * Holt Performance-Daten von OGP-Servern
     */
    public function getOGPServerPerformance() {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpGet, 'getOGPServerPerformance', []);
    }
    
    /**
     * Steuert einen OGP-Server (start, stop, restart, update)
     */
    public function controlOGPServer($serverId, $action) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpPost, 'controlOGPServer', [$serverId, $action]);
    }
    
    /**
     * Löscht einen OGP-Server
     */
    public function deleteOGPServer($serverId) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpPost, 'deleteOGPServer', [$serverId]);
    }
    
    /**
     * Aktualisiert OGP-Server-Einstellungen
     */
    public function updateOGPServer($serverId, $serverData) {
        $apiCheck = $this->checkAPIEnabled('ogp');
        if ($apiCheck !== true) {
            return $apiCheck;
        }
        
        return $this->safeAPICall('ogp', $this->ogpPost, 'updateOGPServer', [$serverId, $serverData]);
    }
    
    /**
     * Testet eine einzelne API-Verbindung
     * @param string $apiName Name der API (proxmox, ovh, ispconfig, ogp)
     * @return bool true wenn Verbindung erfolgreich, false wenn nicht
     */
    private function testSingleAPI($apiName) {
        $apiName = strtolower($apiName);
        
        // Prüfe ob API aktiviert ist
        if ($this->checkAPIEnabled($apiName) !== true) {
            return false;
        }
        
        try {
            switch ($apiName) {
                case 'proxmox':
                    $result = $this->getProxmoxNodes();
                    break;
                case 'ovh':
                    $result = $this->getOVHDomains();
                    break;
                case 'ispconfig':
                    $result = $this->getISPConfigWebsites();
                    break;
                case 'ogp':
                    $result = $this->testOGPToken();
                    break;
                case 'database':
                    // Teste Datenbankverbindung
                    try {
                        $db = Database::getInstance();
                        $result = $db->query("SELECT 1");
                        return $result !== false;
                    } catch (Exception $e) {
                        return false;
                    }
                default:
                    return false;
            }
            
            return isset($result['success']) && $result['success'] === true;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Testet API-Verbindungen und gibt den Status zurück
     * @param string|null $apiName Spezifische API zum Testen (proxmox, ovh, ispconfig, ogp) oder null für alle
     * @return array|bool Bei spezifischer API: true/false, bei allen APIs: detailliertes Array
     */
    public function testAllAPIConnections($apiName = null) {
        // Wenn eine spezifische API angegeben wurde, teste nur diese
        if ($apiName !== null) {
            return $this->testSingleAPI($apiName);
        }
        
        $results = [
            'proxmox' => ['status' => 'disabled', 'message' => '', 'details' => []],
            'ovh' => ['status' => 'disabled', 'message' => '', 'details' => []],
            'ispconfig' => ['status' => 'disabled', 'message' => '', 'details' => []],
            'ogp' => ['status' => 'disabled', 'message' => '', 'details' => []]
        ];
        
        // Teste Proxmox API
        if ($this->checkAPIEnabled('proxmox') === true) {
            try {
                $proxmoxTest = $this->getProxmoxNodes();
                if (isset($proxmoxTest['success']) && $proxmoxTest['success']) {
                    $results['proxmox'] = [
                        'status' => 'connected',
                        'message' => 'Verbindung erfolgreich',
                        'details' => [
                            'nodes_count' => count($proxmoxTest['data'] ?? []),
                            'response_time' => $proxmoxTest['response_time'] ?? 'N/A'
                        ]
                    ];
                } else {
                    $results['proxmox'] = [
                        'status' => 'error',
                        'message' => $proxmoxTest['message'] ?? 'Unbekannter Fehler',
                        'details' => []
                    ];
                }
            } catch (Exception $e) {
                $results['proxmox'] = [
                    'status' => 'error',
                    'message' => 'Verbindungsfehler: ' . $e->getMessage(),
                    'details' => []
                ];
            }
        } else {
            $results['proxmox']['message'] = 'API ist deaktiviert';
        }
        
        // Teste OVH API
        if ($this->checkAPIEnabled('ovh') === true) {
            try {
                $ovhTest = $this->getOVHDomains();
                if (isset($ovhTest['success']) && $ovhTest['success']) {
                    $results['ovh'] = [
                        'status' => 'connected',
                        'message' => 'Verbindung erfolgreich',
                        'details' => [
                            'domains_count' => count($ovhTest['data'] ?? []),
                            'response_time' => $ovhTest['response_time'] ?? 'N/A'
                        ]
                    ];
                } else {
                    $results['ovh'] = [
                        'status' => 'error',
                        'message' => $ovhTest['message'] ?? 'Unbekannter Fehler',
                        'details' => []
                    ];
                }
            } catch (Exception $e) {
                $results['ovh'] = [
                    'status' => 'error',
                    'message' => 'Verbindungsfehler: ' . $e->getMessage(),
                    'details' => []
                ];
            }
        } else {
            $results['ovh']['message'] = 'API ist deaktiviert';
        }
        
        // Teste ISPConfig API
        if ($this->checkAPIEnabled('ispconfig') === true) {
            try {
                $ispconfigTest = $this->getISPConfigWebsites();
                if (isset($ispconfigTest['success']) && $ispconfigTest['success']) {
                    $results['ispconfig'] = [
                        'status' => 'connected',
                        'message' => 'Verbindung erfolgreich',
                        'details' => [
                            'websites_count' => count($ispconfigTest['data'] ?? []),
                            'response_time' => $ispconfigTest['response_time'] ?? 'N/A'
                        ]
                    ];
                } else {
                    $results['ispconfig'] = [
                        'status' => 'error',
                        'message' => $ispconfigTest['message'] ?? 'Unbekannter Fehler',
                        'details' => []
                    ];
                }
            } catch (Exception $e) {
                $results['ispconfig'] = [
                    'status' => 'error',
                    'message' => 'Verbindungsfehler: ' . $e->getMessage(),
                    'details' => []
                ];
            }
        } else {
            $results['ispconfig']['message'] = 'API ist deaktiviert';
        }
        
        // Teste OGP API
        if ($this->checkAPIEnabled('ogp') === true) {
            try {
                $ogpTest = $this->testOGPToken();
                if (isset($ogpTest['success']) && $ogpTest['success']) {
                    $results['ogp'] = [
                        'status' => 'connected',
                        'message' => 'Verbindung erfolgreich',
                        'details' => [
                            'token_valid' => true,
                            'response_time' => $ogpTest['response_time'] ?? 'N/A'
                        ]
                    ];
                } else {
                    $results['ogp'] = [
                        'status' => 'error',
                        'message' => $ogpTest['message'] ?? 'Unbekannter Fehler',
                        'details' => []
                    ];
                }
            } catch (Exception $e) {
                $results['ogp'] = [
                    'status' => 'error',
                    'message' => 'Verbindungsfehler: ' . $e->getMessage(),
                    'details' => []
                ];
            }
        } else {
            $results['ogp']['message'] = 'API ist deaktiviert';
        }
        
        // Logge den Test
        $this->__log('api_connection_test', 'API-Verbindungstest durchgeführt', 'info');
        
        return [
            'success' => true,
            'data' => $results,
            'timestamp' => date('Y-m-d H:i:s'),
            'summary' => [
                'total_apis' => 4,
                'connected' => count(array_filter($results, function($api) { return $api['status'] === 'connected'; })),
                'disabled' => count(array_filter($results, function($api) { return $api['status'] === 'disabled'; })),
                'errors' => count(array_filter($results, function($api) { return $api['status'] === 'error'; }))
            ]
        ];
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
        
        // Ãœberspringe spezielle Verzeichnisse
        if (in_array($module_key, ['.', '..', 'assets', 'templates'])) {
            continue;
        }
        
        $config_file = $dir . '/config.php';
        $module_file = $dir . '/Module.php';
        
        if (file_exists($module_file)) {
            $config = [
                'key' => $module_key,
                'path' => $dir,
                'enabled' => true, // StandardmÃ¤ÃŸig aktiviert
                'name' => ucfirst($module_key),
                'icon' => 'ðŸ“¦',
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
    
    // StandardmÃ¤ÃŸig haben alle Benutzer Zugriff
    if (!isset($config['permissions'])) {
        return true;
    }
    
    // PrÃ¼fe spezifische Berechtigungen
    $permissions = $config['permissions'];
    
    // Admin hat immer Zugriff
    if ($user_role === 'admin') {
        return true;
    }
    
    // PrÃ¼fe Benutzerrolle
    if (isset($permissions['roles'])) {
        if (!in_array($user_role, $permissions['roles'])) {
            return false;
        }
    }
    
    return true;
}

?>
