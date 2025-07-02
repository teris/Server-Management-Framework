<?php
/**
 * Server Management Framework
 * Modulares Framework für Proxmox, ISPConfig und OVH API-Integration
 */

// =============================================================================
// CONFIG CLASS
// =============================================================================
class Config {
    const DB_HOST = 'localhost';										//MySQL Host
    const DB_NAME = 'server_management';								//MySQL DB Name
    const DB_USER = 'root';												//MySQL User
    const DB_PASS = 'pass';										//MySQL Password

    const PROXMOX_HOST = 'https://server:8006';			//ProxmoxServer
    const PROXMOX_USER = 'user@pve';									//Proxmox User (@pam or @pve)
    const PROXMOX_PASSWORD = 'pass';								//Proxmox Password

    const ISPCONFIG_HOST = 'https://server:8080';			//ISPConfig 3 Server
    const ISPCONFIG_USER = 'user';										//ISPConfig 3 User
    const ISPCONFIG_PASSWORD = 'pass';							//ISPConfig 3 Password

    const OVH_APPLICATION_KEY = '';						//OVH Application Key
    const OVH_APPLICATION_SECRET = '';	//OVH Application Secret
    const OVH_CONSUMER_KEY = '';		//OVH Costumer key
    const OVH_ENDPOINT = 'ovh-eu';										//OVH API Server (ovh-eu, ovh-us, ovh-ca)
}

// =============================================================================
// DATABASE CLASS
// =============================================================================
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . Config::DB_HOST . ";dbname=" . Config::DB_NAME,
                Config::DB_USER,
                Config::DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
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
        $stmt = $this->connection->prepare("INSERT INTO activity_log (action, details, status, created_at) VALUES (?, ?, ?, NOW())");
        return $stmt->execute([$action, $details, $status]);
    }

    public function getActivityLog($limit = 50) {
        $stmt = $this->connection->prepare("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// =============================================================================
// DATA MODELS
// =============================================================================

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

// =============================================================================
// DATA MAPPER CLASS
// =============================================================================
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
            'active' => $data['active'] ?? null,
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

// =============================================================================
// BASE API CLASS
// =============================================================================
abstract class BaseAPI {
    protected $host;
    protected $user;
    protected $password;

    abstract protected function authenticate();
    abstract protected function makeRequest($method, $url, $data = null);

    protected function logRequest($endpoint, $method, $success) {
        $db = Database::getInstance();
        $db->logAction(
            "API Request: " . static::class,
            "$method $endpoint",
            $success ? 'success' : 'error'
        );
    }
}

// =============================================================================
// PROXMOX GET CLASS
// =============================================================================
class ProxmoxGet extends BaseAPI {
    private $ticket;
    private $csrf_token;

    public function __construct() {
        $this->host = Config::PROXMOX_HOST;
        $this->user = Config::PROXMOX_USER;
        $this->password = Config::PROXMOX_PASSWORD;
        $this->authenticate();
    }

    protected function authenticate() {
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
        $this->logRequest('/nodes', 'GET', $response !== false);
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

        $this->logRequest('/nodes/*/qemu', 'GET', !empty($vms));
        return $vms;
    }

    public function getVM($node, $vmid) {
        $url = $this->host . "/api2/json/nodes/$node/qemu/$vmid/config";
        $response = $this->makeRequest('GET', $url);
        $this->logRequest("/nodes/$node/qemu/$vmid/config", 'GET', $response !== false);

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
        $this->logRequest("/nodes/$node/qemu/$vmid/status/current", 'GET', $response !== false);
        return $response && isset($response['data']) ? $response['data'] : null;
    }

    public function getVMConfig($node, $vmid) {
        $url = $this->host . "/api2/json/nodes/$node/qemu/$vmid/config";
        $response = $this->makeRequest('GET', $url);
        $this->logRequest("/nodes/$node/qemu/$vmid/config", 'GET', $response !== false);
        return $response && isset($response['data']) ? $response['data'] : null;
    }

    public function getStorages($node = null) {
        $url = $node ?
            $this->host . "/api2/json/nodes/$node/storage" :
            $this->host . "/api2/json/storage";
        $response = $this->makeRequest('GET', $url);
        $this->logRequest('/storage', 'GET', $response !== false);
        return $response && isset($response['data']) ? $response['data'] : [];
    }

    public function getNetworks($node) {
        $url = $this->host . "/api2/json/nodes/$node/network";
        $response = $this->makeRequest('GET', $url);
        $this->logRequest("/nodes/$node/network", 'GET', $response !== false);
        return $response && isset($response['data']) ? $response['data'] : [];
    }

    protected function makeRequest($method, $url, $data = null) {
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

// =============================================================================
// PROXMOX POST CLASS
// =============================================================================
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
        $this->logRequest("/nodes/{$vmData['node']}/qemu", 'POST', $response !== false);
        return $response;
    }

    public function editVM($node, $vmid, $vmData) {
        $url = $this->host . "/api2/json/nodes/$node/qemu/$vmid/config";
        $response = $this->makeRequest('PUT', $url, $vmData);
        $this->logRequest("/nodes/$node/qemu/$vmid/config", 'PUT', $response !== false);
        return $response;
    }

    public function deleteVM($node, $vmid) {
        $url = $this->host . "/api2/json/nodes/$node/qemu/$vmid";
        $response = $this->makeRequest('DELETE', $url);
        $this->logRequest("/nodes/$node/qemu/$vmid", 'DELETE', $response !== false);
        return $response;
    }

    public function startVM($node, $vmid) {
        $url = $this->host . "/api2/json/nodes/$node/qemu/$vmid/status/start";
        $response = $this->makeRequest('POST', $url);
        $this->logRequest("/nodes/$node/qemu/$vmid/status/start", 'POST', $response !== false);
        return $response;
    }

    public function stopVM($node, $vmid) {
        $url = $this->host . "/api2/json/nodes/$node/qemu/$vmid/status/stop";
        $response = $this->makeRequest('POST', $url);
        $this->logRequest("/nodes/$node/qemu/$vmid/status/stop", 'POST', $response !== false);
        return $response;
    }

    public function resetVM($node, $vmid) {
        $url = $this->host . "/api2/json/nodes/$node/qemu/$vmid/status/reset";
        $response = $this->makeRequest('POST', $url);
        $this->logRequest("/nodes/$node/qemu/$vmid/status/reset", 'POST', $response !== false);
        return $response;
    }

    public function suspendVM($node, $vmid) {
        $url = $this->host . "/api2/json/nodes/$node/qemu/$vmid/status/suspend";
        $response = $this->makeRequest('POST', $url);
        $this->logRequest("/nodes/$node/qemu/$vmid/status/suspend", 'POST', $response !== false);
        return $response;
    }

    public function resumeVM($node, $vmid) {
        $url = $this->host . "/api2/json/nodes/$node/qemu/$vmid/status/resume";
        $response = $this->makeRequest('POST', $url);
        $this->logRequest("/nodes/$node/qemu/$vmid/status/resume", 'POST', $response !== false);
        return $response;
    }

    public function cloneVM($node, $vmid, $newVmid, $name = null) {
        $url = $this->host . "/api2/json/nodes/$node/qemu/$vmid/clone";
        $data = [
            'newid' => $newVmid,
            'name' => $name ?: "clone-of-$vmid"
        ];

        $response = $this->makeRequest('POST', $url, $data);
        $this->logRequest("/nodes/$node/qemu/$vmid/clone", 'POST', $response !== false);
        return $response;
    }
}

// =============================================================================
// ISPCONFIG GET CLASS
// =============================================================================
class ISPConfigGet extends BaseAPI {
    private $session_id;
    private $client;

    public function __construct() {
        $this->host = Config::ISPCONFIG_HOST;
        $this->user = Config::ISPCONFIG_USER;
        $this->password = Config::ISPCONFIG_PASSWORD;
        $this->authenticate();
    }

    protected function authenticate() {
        try {
            $this->client = new SoapClient(null, [
                'location' => $this->host . '/remote/index.php',
                'uri' => $this->host . '/remote/',
                'trace' => 1,
                'exceptions' => 1
            ]);

            $this->session_id = $this->client->login($this->user, $this->password);
            $this->logRequest('/remote/login', 'POST', $this->session_id !== false);
        } catch (Exception $e) {
            error_log('ISPConfig Login fehlgeschlagen: ' . $e->getMessage());
        }
    }

    public function getWebsites($filter = []) {
        try {
            $websites = $this->client->sites_web_domain_get($this->session_id, $filter);
            $this->logRequest('/sites/web_domain/get', 'GET', $websites !== false);

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
            $this->logRequest("/sites/web_domain/$domainId", 'GET', $website !== false);

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
            $this->logRequest('/sites/database/get', 'GET', $databases !== false);

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
            $this->logRequest("/sites/database/$databaseId", 'GET', $database !== false);

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
            $emails = $this->client->mail_user_get($this->session_id, $filter);
            $this->logRequest('/mail/user/get', 'GET', $emails !== false);

            if ($emails) {
                return array_map(function($email) {
                    return DataMapper::mapToEmailAccount($email);
                }, $emails);
            }

            return [];
        } catch (Exception $e) {
            error_log('Error getting emails: ' . $e->getMessage());
            return [];
        }
    }

    public function getEmailAccount($mailuserId) {
        try {
            $email = $this->client->mail_user_get($this->session_id, ['mailuser_id' => $mailuserId]);
            $this->logRequest("/mail/user/$mailuserId", 'GET', $email !== false);

            if ($email && isset($email[0])) {
                return DataMapper::mapToEmailAccount($email[0]);
            }

            return null;
        } catch (Exception $e) {
            error_log('Error getting email: ' . $e->getMessage());
            return null;
        }
    }

    public function getClients($filter = []) {
        try {
            $clients = $this->client->client_get($this->session_id, $filter);
            $this->logRequest('/client/get', 'GET', $clients !== false);
            return $clients ?: [];
        } catch (Exception $e) {
            error_log('Error getting clients: ' . $e->getMessage());
            return [];
        }
    }

    public function getServerConfig() {
        try {
            $config = $this->client->server_get($this->session_id, 1);
            $this->logRequest('/server/get', 'GET', $config !== false);
            return $config ?: [];
        } catch (Exception $e) {
            error_log('Error getting server config: ' . $e->getMessage());
            return [];
        }
    }

    protected function makeRequest($method, $url, $data = null) {
        // ISPConfig uses SOAP, so this is handled in the specific methods above
        return true;
    }
}

// =============================================================================
// ISPCONFIG POST CLASS
// =============================================================================
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
            $this->logRequest('/sites/web_domain/add', 'POST', $result !== false);
            return $result;
        } catch (Exception $e) {
            error_log('Website creation failed: ' . $e->getMessage());
            return false;
        }
    }

    public function editWebsite($domainId, $websiteData) {
        try {
            $result = $this->client->sites_web_domain_update($this->session_id, 1, $domainId, $websiteData);
            $this->logRequest("/sites/web_domain/$domainId", 'PUT', $result !== false);
            return $result;
        } catch (Exception $e) {
            error_log('Website edit failed: ' . $e->getMessage());
            return false;
        }
    }

    public function deleteWebsite($domainId) {
        try {
            $result = $this->client->sites_web_domain_delete($this->session_id, $domainId);
            $this->logRequest("/sites/web_domain/$domainId", 'DELETE', $result !== false);
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
            $this->logRequest('/sites/database/add', 'POST', $result !== false);
            return $result;
        } catch (Exception $e) {
            error_log('Database creation failed: ' . $e->getMessage());
            return false;
        }
    }

    public function editDatabase($databaseId, $dbData) {
        try {
            $result = $this->client->sites_database_update($this->session_id, 1, $databaseId, $dbData);
            $this->logRequest("/sites/database/$databaseId", 'PUT', $result !== false);
            return $result;
        } catch (Exception $e) {
            error_log('Database edit failed: ' . $e->getMessage());
            return false;
        }
    }

    public function deleteDatabase($databaseId) {
        try {
            $result = $this->client->sites_database_delete($this->session_id, $databaseId);
            $this->logRequest("/sites/database/$databaseId", 'DELETE', $result !== false);
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
            $this->logRequest('/mail/user/add', 'POST', $result !== false);
            return $result;
        } catch (Exception $e) {
            error_log('Email creation failed: ' . $e->getMessage());
            return false;
        }
    }

    public function editEmailAccount($mailuserId, $emailData) {
        try {
            $result = $this->client->mail_user_update($this->session_id, 1, $mailuserId, $emailData);
            $this->logRequest("/mail/user/$mailuserId", 'PUT', $result !== false);
            return $result;
        } catch (Exception $e) {
            error_log('Email edit failed: ' . $e->getMessage());
            return false;
        }
    }

    public function deleteEmailAccount($mailuserId) {
        try {
            $result = $this->client->mail_user_delete($this->session_id, $mailuserId);
            $this->logRequest("/mail/user/$mailuserId", 'DELETE', $result !== false);
            return $result;
        } catch (Exception $e) {
            error_log('Email deletion failed: ' . $e->getMessage());
            return false;
        }
    }
}

// =============================================================================
// OVH GET CLASS
// =============================================================================
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

        $this->logRequest('/domain', 'GET', !empty($domainDetails));
        return $domainDetails;
    }

    public function getDomain($domain) {
        $url = "https://eu.api.ovh.com/1.0/domain/$domain";
        $response = $this->makeRequest('GET', $url);
        $this->logRequest("/domain/$domain", 'GET', $response !== false);

        if ($response) {
            return DataMapper::mapToDomain($response);
        }

        return null;
    }

    public function getDomainZone($domain) {
        $url = "https://eu.api.ovh.com/1.0/domain/zone/$domain";
        $response = $this->makeRequest('GET', $url);
        $this->logRequest("/domain/zone/$domain", 'GET', $response !== false);
        return $response;
    }

    public function getDomainZoneRecords($domain) {
        $url = "https://eu.api.ovh.com/1.0/domain/zone/$domain/record";
        $response = $this->makeRequest('GET', $url);
        $this->logRequest("/domain/zone/$domain/record", 'GET', $response !== false);
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

        $this->logRequest('/vps', 'GET', !empty($vpsDetails));
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

            $this->logRequest("/vps/$vpsName", 'GET', true);
            return DataMapper::mapToVPS($details);
        }

        $this->logRequest("/vps/$vpsName", 'GET', false);
        return null;
    }

    public function getVPSIPs($vpsName) {
        $url = "https://eu.api.ovh.com/1.0/vps/$vpsName/ips";
        $response = $this->makeRequest('GET', $url);
        $this->logRequest("/vps/$vpsName/ips", 'GET', $response !== false);
        return $response ?: [];
    }

    public function getVPSIPDetails($vpsName, $ip) {
        $url = "https://eu.api.ovh.com/1.0/vps/$vpsName/ips/$ip";
        $response = $this->makeRequest('GET', $url);
        $this->logRequest("/vps/$vpsName/ips/$ip", 'GET', $response !== false);
        return $response;
    }

    public function getDedicatedServers() {
        $servers = $this->makeRequest('GET', 'https://eu.api.ovh.com/1.0/dedicated/server');
        $this->logRequest('/dedicated/server', 'GET', $servers !== false);
        return $servers ?: [];
    }

    public function getDedicatedServer($serverName) {
        $url = "https://eu.api.ovh.com/1.0/dedicated/server/$serverName";
        $response = $this->makeRequest('GET', $url);
        $this->logRequest("/dedicated/server/$serverName", 'GET', $response !== false);
        return $response;
    }

    public function getFailoverIPs() {
        $url = "https://eu.api.ovh.com/1.0/ip";
        $response = $this->makeRequest('GET', $url);
        $this->logRequest("/ip", 'GET', $response !== false);
        return $response ?: [];
    }

    public function getFailoverIPDetails($ip) {
        // URL-encode the IP address to handle special characters like '/'
        $encodedIp = urlencode($ip);
        $url = "https://eu.api.ovh.com/1.0/ip/{$encodedIp}";
        $response = $this->makeRequest('GET', $url);
        $this->logRequest("/ip/{$ip}", 'GET', $response !== false);
        return $response ?: null;
    }

    protected function makeRequest($method, $url, $data = null) {
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
}

// =============================================================================
// OVH POST CLASS
// =============================================================================
class OVHPost extends OVHGet {

    public function orderDomain($domain, $duration = 1) {
        $url = "https://eu.api.ovh.com/1.0/order/domain/zone/$domain";

        $data = [
            'duration' => "P{$duration}Y"
        ];

        $response = $this->makeRequest('POST', $url, $data);
        $this->logRequest("/order/domain/zone/$domain", 'POST', $response !== false);
        return $response;
    }

    public function editDomain($domain, $domainData) {
        $url = "https://eu.api.ovh.com/1.0/domain/$domain";
        $response = $this->makeRequest('PUT', $url, $domainData);
        $this->logRequest("/domain/$domain", 'PUT', $response !== false);
        return $response;
    }

    public function deleteDomain($domain) {
        $url = "https://eu.api.ovh.com/1.0/domain/$domain";
        $response = $this->makeRequest('DELETE', $url);
        $this->logRequest("/domain/$domain", 'DELETE', $response !== false);
        return $response;
    }

    public function createDNSRecord($domain, $recordData) {
        $url = "https://eu.api.ovh.com/1.0/domain/zone/$domain/record";
        $response = $this->makeRequest('POST', $url, $recordData);
        $this->logRequest("/domain/zone/$domain/record", 'POST', $response !== false);
        return $response;
    }

    public function editDNSRecord($domain, $recordId, $recordData) {
        $url = "https://eu.api.ovh.com/1.0/domain/zone/$domain/record/$recordId";
        $response = $this->makeRequest('PUT', $url, $recordData);
        $this->logRequest("/domain/zone/$domain/record/$recordId", 'PUT', $response !== false);
        return $response;
    }

    public function deleteDNSRecord($domain, $recordId) {
        $url = "https://eu.api.ovh.com/1.0/domain/zone/$domain/record/$recordId";
        $response = $this->makeRequest('DELETE', $url);
        $this->logRequest("/domain/zone/$domain/record/$recordId", 'DELETE', $response !== false);
        return $response;
    }

    public function refreshDNSZone($domain) {
        $url = "https://eu.api.ovh.com/1.0/domain/zone/$domain/refresh";
        $response = $this->makeRequest('POST', $url);
        $this->logRequest("/domain/zone/$domain/refresh", 'POST', $response !== false);
        return $response;
    }

    public function rebootVPS($vpsName) {
        $url = "https://eu.api.ovh.com/1.0/vps/$vpsName/reboot";
        $response = $this->makeRequest('POST', $url);
        $this->logRequest("/vps/$vpsName/reboot", 'POST', $response !== false);
        return $response;
    }

    public function stopVPS($vpsName) {
        $url = "https://eu.api.ovh.com/1.0/vps/$vpsName/stop";
        $response = $this->makeRequest('POST', $url);
        $this->logRequest("/vps/$vpsName/stop", 'POST', $response !== false);
        return $response;
    }

    public function startVPS($vpsName) {
        $url = "https://eu.api.ovh.com/1.0/vps/$vpsName/start";
        $response = $this->makeRequest('POST', $url);
        $this->logRequest("/vps/$vpsName/start", 'POST', $response !== false);
        return $response;
    }
}

// =============================================================================
// SERVICE MANAGER CLASS
// =============================================================================
class ServiceManager {
    private $proxmoxGet;
    private $proxmoxPost;
    private $ispconfigGet;
    private $ispconfigPost;
    private $ovhGet;
    private $ovhPost;

    public function __construct() {
        $this->proxmoxGet = new ProxmoxGet();
        $this->proxmoxPost = new ProxmoxPost();
        $this->ispconfigGet = new ISPConfigGet();
        $this->ispconfigPost = new ISPConfigPost();
        $this->ovhGet = new OVHGet();
        $this->ovhPost = new OVHPost();
    }

    // Proxmox Methods
    public function getProxmoxVMs() {
        return $this->proxmoxGet->getVMs();
    }

    public function createProxmoxVM($vmData) {
        return $this->proxmoxPost->createVM($vmData);
    }

    public function controlProxmoxVM($node, $vmid, $action) {
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
                return false;
        }
    }

    public function deleteProxmoxVM($node, $vmid) {
        return $this->proxmoxPost->deleteVM($node, $vmid);
    }

    // ISPConfig Methods
    public function getISPConfigWebsites() {
        return $this->ispconfigGet->getWebsites(['active' => 'y']);
    }

    public function createISPConfigWebsite($websiteData) {
        return $this->ispconfigPost->createWebsite($websiteData);
    }

    public function deleteISPConfigWebsite($domainId) {
        return $this->ispconfigPost->deleteWebsite($domainId);
    }

    public function getISPConfigDatabases() {
        return $this->ispconfigGet->getDatabases(['active' => 'y']);
    }

    public function createISPConfigDatabase($dbData) {
        return $this->ispconfigPost->createDatabase($dbData);
    }

    public function deleteISPConfigDatabase($databaseId) {
        return $this->ispconfigPost->deleteDatabase($databaseId);
    }

    public function getISPConfigEmails() {
        return $this->ispconfigGet->getEmailAccounts(['active' => 'y']);
    }

    public function createISPConfigEmail($emailData) {
        return $this->ispconfigPost->createEmailAccount($emailData);
    }

    public function deleteISPConfigEmail($mailuserId) {
        return $this->ispconfigPost->deleteEmailAccount($mailuserId);
    }

    // OVH Methods
    public function getOVHDomains() {
        return $this->ovhGet->getDomains();
    }

    public function orderOVHDomain($domain, $duration) {
        return $this->ovhPost->orderDomain($domain, $duration);
    }

    public function getOVHVPS() {
        return $this->ovhGet->getVPSList();
    }

    public function getOVHFailoverIPs() {
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
}

?>
