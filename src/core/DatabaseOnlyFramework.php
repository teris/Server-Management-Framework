<?php
/**
 * Server Management Framework
 * Modulares Framework für Proxmox, ISPConfig und OVH API-Integration
 * Erweitert um Virtual MAC Support
 */
require_once __DIR__ . '/../../config/config.inc.php';
require_once __DIR__ . '/DatabaseManager.php';

// =============================================================================
// DATABASE CLASS (angepasst für neue Abstraktionsschicht)
// =============================================================================
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
}
// =============================================================================
// PROXMOX GET/POST (DB)
// =============================================================================
abstract class Model {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    protected $data = [];

    public function __construct($data = null) {
        $this->db = Database::getInstance()->getConnection();
        if ($data !== null) {
            $this->data = $data;
        }
    }
    
    public function getDb() {
        return $this->db;
    }
    
    public function getData() {
        return $this->data;
    }
    
    public function setData($data) {
        $this->data = $data;
        return $this;
    }

    // Generische CRUD-Methoden
    public function find($id) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    public function all($limit = 100, $offset = 0) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} LIMIT ? OFFSET ?");
        $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(2, (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    public function insert($data) {
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        $sql = "INSERT INTO {$this->table} (" . implode(',', $fields) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($data));
        return $this->db->lastInsertId();
    }
    public function update($id, $data) {
        $fields = array_keys($data);
        $set = implode(', ', array_map(fn($f) => "$f = ?", $fields));
        $sql = "UPDATE {$this->table} SET $set WHERE {$this->primaryKey} = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge(array_values($data), [$id]));
        return $stmt->rowCount();
    }
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount();
    }
}
// Models für alle Tabellen:
class ModuleConfig extends Model { protected $table = 'module_configs'; }
class ModulePermission extends Model { protected $table = 'module_permissions'; }
class User extends Model { protected $table = 'users'; }
class VM extends Model { protected $table = 'vms'; }
class Website extends Model { protected $table = 'websites'; }
class EmailAccount extends Model { protected $table = 'email_accounts'; }
class SMDatabase extends Model { protected $table = 'sm_databases'; }
class Domain extends Model { protected $table = 'domains'; }
class SSLCertificate extends Model { protected $table = 'ssl_certificates'; }
class NetworkConfig extends Model { protected $table = 'network_config'; }
class ServerResource extends Model { protected $table = 'server_resources'; }
class Setting extends Model { protected $table = 'settings'; }
class SystemSetting extends Model { protected $table = 'system_settings'; }
class Module extends Model { protected $table = 'modules'; }
class ActiveModule extends Model { protected $table = 'active_modules'; }
class ModuleDependency extends Model { protected $table = 'module_dependencies'; }
class GroupModulePermission extends Model { protected $table = 'group_module_permissions'; }
class BackupJob extends Model { protected $table = 'backup_jobs'; }
class APICredential extends Model { protected $table = 'api_credentials'; }
class LoginAttempt extends Model { protected $table = 'login_attempts'; }
class Group extends Model { protected $table = 'groups'; }
class UserPermission extends Model { protected $table = 'user_permissions'; }
class UserSession extends Model { protected $table = 'user_sessions'; }
class ActivityLog extends Model { protected $table = 'activity_log'; }
class ProxmoxGet {
    public function getVMs($node = null) {
        $model = new VM();
        $where = $node ? 'WHERE node = ?' : '';
        $stmt = $model->getDb()->prepare("SELECT * FROM vms $where");
        if ($node) $stmt->execute([$node]); else $stmt->execute();
        $rows = $stmt->fetchAll();
        $result = [];
        foreach ($rows as $row) $result[] = new VM($row);
        return $result;
    }
    public function getVM($node, $vmid) {
        $model = new VM();
        $stmt = $model->getDb()->prepare("SELECT * FROM vms WHERE node = ? AND vm_id = ?");
        $stmt->execute([$node, $vmid]);
        $row = $stmt->fetch();
        return $row ? new VM($row) : null;
    }
    public function getVMStatus($node, $vmid) {
        $model = new VM();
        $stmt = $model->getDb()->prepare("SELECT status FROM vms WHERE node = ? AND vm_id = ?");
        $stmt->execute([$node, $vmid]);
        return $stmt->fetchColumn();
    }
    public function getVMConfig($node, $vmid) {
        $model = new VM();
        $stmt = $model->getDb()->prepare("SELECT * FROM vms WHERE node = ? AND vm_id = ?");
        $stmt->execute([$node, $vmid]);
        return $stmt->fetch();
    }
}
class ProxmoxPost extends ProxmoxGet {
    public function createVM($vmData) {
        $model = new VM();
        return $model->insert($vmData);
    }
    public function editVM($node, $vmid, $vmData) {
        $model = new VM();
        $stmt = $model->getDb()->prepare("UPDATE vms SET ".implode(', ', array_map(fn($k)=>"$k=?", array_keys($vmData)))." WHERE node=? AND vm_id=?");
        $stmt->execute(array_merge(array_values($vmData), [$node, $vmid]));
        return $stmt->rowCount();
    }
    public function deleteVM($node, $vmid) {
        $model = new VM();
        $stmt = $model->getDb()->prepare("DELETE FROM vms WHERE node = ? AND vm_id = ?");
        $stmt->execute([$node, $vmid]);
        return $stmt->rowCount();
    }
}
// =============================================================================
// ISPConfig GET/POST (DB)
// =============================================================================
class ISPConfigGet {
    public function getWebsites($filter = []) {
        $model = new Website();
        $sql = "SELECT * FROM websites";
        $params = [];
        if (!empty($filter)) {
            $where = [];
            foreach ($filter as $k=>$v) { $where[] = "$k=?"; $params[] = $v; }
            $sql .= " WHERE ".implode(' AND ', $where);
        }
        $stmt = $model->getDb()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $result = [];
        foreach ($rows as $row) $result[] = new Website($row);
        return $result;
    }
    public function getWebsite($domainId) {
        $model = new Website();
        $stmt = $model->getDb()->prepare("SELECT * FROM websites WHERE id = ?");
        $stmt->execute([$domainId]);
        $row = $stmt->fetch();
        return $row ? new Website($row) : null;
    }
    public function getDatabases($filter = []) {
        $model = new SMDatabase();
        $sql = "SELECT * FROM sm_databases";
        $params = [];
        if (!empty($filter)) {
            $where = [];
            foreach ($filter as $k=>$v) { $where[] = "$k=?"; $params[] = $v; }
            $sql .= " WHERE ".implode(' AND ', $where);
        }
        $stmt = $model->getDb()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $result = [];
        foreach ($rows as $row) $result[] = new SMDatabase($row);
        return $result;
    }
    public function getDatabase($databaseId) {
        $model = new SMDatabase();
        $stmt = $model->getDb()->prepare("SELECT * FROM sm_databases WHERE id = ?");
        $stmt->execute([$databaseId]);
        $row = $stmt->fetch();
        return $row ? new SMDatabase($row) : null;
    }
    public function getEmailAccounts($filter = []) {
        $model = new EmailAccount();
        $sql = "SELECT * FROM email_accounts";
        $params = [];
        if (!empty($filter)) {
            $where = [];
            foreach ($filter as $k=>$v) { $where[] = "$k=?"; $params[] = $v; }
            $sql .= " WHERE ".implode(' AND ', $where);
        }
        $stmt = $model->getDb()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $result = [];
        foreach ($rows as $row) $result[] = new EmailAccount($row);
        return $result;
    }
}
class ISPConfigPost extends ISPConfigGet {
    public function createWebsite($websiteData) {
        $model = new Website();
        return $model->insert($websiteData);
    }
    public function editWebsite($domainId, $websiteData) {
        $model = new Website();
        return $model->update($domainId, $websiteData);
    }
    public function deleteWebsite($domainId) {
        $model = new Website();
        return $model->delete($domainId);
    }
    public function createDatabase($dbData) {
        $model = new SMDatabase();
        return $model->insert($dbData);
    }
    public function editDatabase($databaseId, $dbData) {
        $model = new SMDatabase();
        return $model->update($databaseId, $dbData);
    }
    public function deleteDatabase($databaseId) {
        $model = new SMDatabase();
        return $model->delete($databaseId);
    }
    public function createEmailAccount($emailData) {
        $model = new EmailAccount();
        return $model->insert($emailData);
    }
    public function editEmailAccount($mailuserId, $emailData) {
        $model = new EmailAccount();
        return $model->update($mailuserId, $emailData);
    }
    public function deleteEmailAccount($mailuserId) {
        $model = new EmailAccount();
        return $model->delete($mailuserId);
    }
}
// =============================================================================
// OVH GET/POST (DB)
// =============================================================================
class OVHGet {
    public function getDomains() {
        $model = new Domain();
        $rows = $model->all();
        $result = [];
        foreach ($rows as $row) $result[] = new Domain($row);
        return $result;
    }
    public function getDomain($domain) {
        $model = new Domain();
        $stmt = $model->getDb()->prepare("SELECT * FROM domains WHERE domain_name = ?");
        $stmt->execute([$domain]);
        $row = $stmt->fetch();
        return $row ? new Domain($row) : null;
    }
}
class OVHPost extends OVHGet {
    public function orderDomain($domainData) {
        $model = new Domain();
        return $model->insert($domainData);
    }
    public function editDomain($domain, $domainData) {
        $model = new Domain();
        $stmt = $model->getDb()->prepare("UPDATE domains SET ".implode(', ', array_map(fn($k)=>"$k=?", array_keys($domainData)))." WHERE domain_name=?");
        $stmt->execute(array_merge(array_values($domainData), [$domain]));
        return $stmt->rowCount();
    }
    public function deleteDomain($domain) {
        $model = new Domain();
        $stmt = $model->getDb()->prepare("DELETE FROM domains WHERE domain_name = ?");
        $stmt->execute([$domain]);
        return $stmt->rowCount();
    }
}
// =============================================================================
// VIRTUAL MAC & FAILOVER IP (DB)
// =============================================================================
class VirtualMac {
    // Holt alle Virtual MACs (vereinfachtes Beispiel)
    public static function getAll($serviceName = null) {
        $model = new NetworkConfig();
        $sql = "SELECT * FROM network_config WHERE mac_address IS NOT NULL";
        $params = [];
        if ($serviceName) {
            $sql .= " AND interface_name = ?";
            $params[] = $serviceName;
        }
        $stmt = $model->getDb()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $result = [];
        foreach ($rows as $row) $result[] = $row;
        return $result;
    }
}
class FailoverIP {
    public static function getAll() {
        $model = new NetworkConfig();
        $stmt = $model->getDb()->prepare("SELECT * FROM network_config WHERE ip_address IS NOT NULL");
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
// =============================================================================
// NETWORK & RESOURCES (DB)
// =============================================================================
class Network {
    public static function getAllConfigs() {
        $model = new NetworkConfig();
        return $model->all();
    }
    public static function getByVM($vm_id) {
        $model = new NetworkConfig();
        $stmt = $model->getDb()->prepare("SELECT * FROM network_config WHERE vm_id = ?");
        $stmt->execute([$vm_id]);
        return $stmt->fetchAll();
    }
}
// Helper-Klasse für Ressourcen
class ServerResourceHelper {
    public static function getByVM($vm_id) {
        $model = new ServerResource();
        $stmt = $model->getDb()->prepare("SELECT * FROM server_resources WHERE vm_id = ? ORDER BY timestamp DESC");
        $stmt->execute([$vm_id]);
        return $stmt->fetchAll();
    }
}
// =============================================================================
// MODULES & PERMISSIONS (DB)
// =============================================================================
class ModuleHelper {
    public static function getAllModules() {
        $model = new Module();
        return $model->all();
    }
    public static function getEnabledModules() {
        $model = new Module();
        $stmt = $model->getDb()->prepare("SELECT * FROM modules WHERE is_active = 1");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    public static function getModuleConfig($module_name) {
        $model = new ModuleConfig();
        $stmt = $model->getDb()->prepare("SELECT * FROM module_configs WHERE module_name = ?");
        $stmt->execute([$module_name]);
        return $stmt->fetchAll();
    }
    public static function canAccessModule($module_name, $user_role) {
        $model = new ModulePermission();
        $stmt = $model->getDb()->prepare("SELECT * FROM module_permissions WHERE module_name = ? AND required_role = ?");
        $stmt->execute([$module_name, $user_role]);
        return $stmt->fetch() ? true : false;
    }
}
// =============================================================================
// MODULE HELPER FUNCTIONS (Dateisystem + DB)
// =============================================================================
/**
 * Gibt alle verfügbaren Module im module/-Verzeichnis zurück (Dateisystem-Scan)
 */
function getAllModules() {
    $modules = [];
    $module_dir = __DIR__ . '/../module/';
    if (!is_dir($module_dir)) {
        return $modules;
    }
    $dirs = glob($module_dir . '*', GLOB_ONLYDIR);
    foreach ($dirs as $dir) {
        $module_key = basename($dir);
        if (in_array($module_key, ['.', '..', 'assets', 'templates'])) {
            continue;
        }
        $config_file = $dir . '/config.php';
        $module_file = $dir . '/Module.php';
        if (file_exists($module_file)) {
            $config = [
                'key' => $module_key,
                'path' => $dir, // Immer setzen!
                'enabled' => true,
                'name' => ucfirst($module_key),
                'icon' => '📦',
                'description' => 'Module ' . ucfirst($module_key),
                'version' => '1.0.0',
                'author' => 'System',
                'dependencies' => []
            ];
            if (file_exists($config_file)) {
                $module_config = include $config_file;
                $config = array_merge($config, $module_config);
            }
            // Fallback: path immer setzen, falls überschrieben
            if (empty($config['path'])) {
                $config['path'] = $dir;
            }
            $modules[$module_key] = $config;
        }
    }
    return $modules;
}
/**
 * Gibt nur die aktivierten Module zurück (Dateisystem-Scan)
 */
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
/**
 * Gibt die Konfiguration für ein spezifisches Modul zurück (DB)
 */
function getModuleConfig($module_key) {
    // DB-gestützt: Hole alle Configs für das Modul
    $model = new ModuleConfig();
    $stmt = $model->getDb()->prepare("SELECT * FROM module_configs WHERE module_name = ?");
    $stmt->execute([$module_key]);
    $configs = $stmt->fetchAll();
    $result = [];
    foreach ($configs as $row) {
        $result[$row['config_key']] = $row['config_value'];
    }
    // Patch: path immer setzen!
    if (empty($result['path'])) {
        $result['path'] = __DIR__ . '/../module/' . $module_key;
    }
    return $result;
}
/**
 * Prüft ob ein Benutzer auf ein Modul zugreifen darf (DB + Logik)
 */
function canAccessModule($module_key, $user_role) {
    $model = new ModulePermission();
    $stmt = $model->getDb()->prepare("SELECT * FROM module_permissions WHERE module_name = ?");
    $stmt->execute([$module_key]);
    $permissions = $stmt->fetchAll();
    if (empty($permissions)) {
        return true; // Standard: Zugriff erlaubt
    }
    if ($user_role === 'admin') {
        return true;
    }
    foreach ($permissions as $perm) {
        if (isset($perm['required_role']) && $perm['required_role'] === $user_role) {
            return true;
        }
    }
    return false;
}
// Platzhalter für getPluginConfig, falls im Projekt nicht vorhanden
if (!function_exists('getPluginConfig')) {
    function getPluginConfig($module_key) {
        return [];
    }
}
// =============================================================================
// LOGGING & ACTIVITY (DB)
// =============================================================================
class Logger {
    public static function log($action, $details, $status = 'success') {
        $model = new ActivityLog();
        $model->insert([
            'action' => $action,
            'details' => $details,
            'status' => $status,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    public static function getActivityLog($limit = 50, $offset = 0) {
        $model = new ActivityLog();
        $stmt = $model->getDb()->prepare("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(2, (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    public static function clearActivityLogs() {
        $model = new ActivityLog();
        $stmt = $model->getDb()->prepare("TRUNCATE TABLE activity_log");
        return $stmt->execute();
    }
}
// =============================================================================
// USER & PERMISSIONS (DB)
// =============================================================================
class UserHelper {
    public static function getUser($id) {
        $model = new User();
        return $model->find($id);
    }
    public static function getAllUsers() {
        $model = new User();
        return $model->all();
    }
    public static function getUserPermissions($user_id) {
        $model = new UserPermission();
        $stmt = $model->getDb()->prepare("SELECT * FROM user_permissions WHERE user_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }
}
// =============================================================================
// SETTINGS (DB)
// =============================================================================
class SettingsHelper {
    public static function getSettings() {
        $model = new Setting();
        return $model->all();
    }
    public static function getSystemSettings() {
        $model = new SystemSetting();
        return $model->all();
    }
}
// =============================================================================
// SSL CERTIFICATES (DB)
// =============================================================================
class SSLCertHelper {
    public static function getAll() {
        $model = new SSLCertificate();
        return $model->all();
    }
    public static function getByDomain($domain) {
        $model = new SSLCertificate();
        $stmt = $model->getDb()->prepare("SELECT * FROM ssl_certificates WHERE domain = ?");
        $stmt->execute([$domain]);
        return $stmt->fetchAll();
    }
}
// =============================================================================
// BACKUP JOBS (DB)
// =============================================================================
class BackupJobHelper {
    public static function getAll() {
        $model = new BackupJob();
        return $model->all();
    }
    public static function getByType($type) {
        $model = new BackupJob();
        $stmt = $model->getDb()->prepare("SELECT * FROM backup_jobs WHERE type = ?");
        $stmt->execute([$type]);
        return $stmt->fetchAll();
    }
}
// =============================================================================
// API CREDENTIALS (DB)
// =============================================================================
class APICredentialHelper {
    public static function getAll() {
        $model = new APICredential();
        return $model->all();
    }
    public static function getByService($service_name) {
        $model = new APICredential();
        $stmt = $model->getDb()->prepare("SELECT * FROM api_credentials WHERE service_name = ?");
        $stmt->execute([$service_name]);
        return $stmt->fetch();
    }
}
// =============================================================================
// LOGIN ATTEMPTS (DB)
// =============================================================================
class LoginAttemptHelper {
    public static function getAll() {
        $model = new LoginAttempt();
        return $model->all();
    }
    public static function getByUser($username) {
        $model = new LoginAttempt();
        $stmt = $model->getDb()->prepare("SELECT * FROM login_attempts WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetchAll();
    }
}
// =============================================================================
// GROUPS (DB)
// =============================================================================
class GroupHelper {
    public static function getAll() {
        $model = new Group();
        return $model->all();
    }
}
// =============================================================================
// SERVICE MANAGER (DB)
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
    // Proxmox
    public function getProxmoxVMs() { return $this->proxmoxGet->getVMs(); }
    public function createProxmoxVM($vmData) { return $this->proxmoxPost->createVM($vmData); }
    public function deleteProxmoxVM($node, $vmid) { return $this->proxmoxPost->deleteVM($node, $vmid); }
    // ISPConfig
    public function getISPConfigWebsites() { return $this->ispconfigGet->getWebsites(['active'=>'y']); }
    public function createISPConfigWebsite($websiteData) { return $this->ispconfigPost->createWebsite($websiteData); }
    public function deleteISPConfigWebsite($domainId) { return $this->ispconfigPost->deleteWebsite($domainId); }
    public function getISPConfigDatabases() { return $this->ispconfigGet->getDatabases(['active'=>'y']); }
    public function createISPConfigDatabase($dbData) { return $this->ispconfigPost->createDatabase($dbData); }
    public function deleteISPConfigDatabase($databaseId) { return $this->ispconfigPost->deleteDatabase($databaseId); }
    public function getISPConfigEmails() { return $this->ispconfigGet->getEmailAccounts(['active'=>'y']); }
    public function createISPConfigEmail($emailData) { return $this->ispconfigPost->createEmailAccount($emailData); }
    public function deleteISPConfigEmail($mailuserId) { return $this->ispconfigPost->deleteEmailAccount($mailuserId); }
    // OVH
    public function getOVHDomains() { return $this->ovhGet->getDomains(); }
    public function orderOVHDomain($domainData) { return $this->ovhPost->orderDomain($domainData); }
    public function deleteOVHDomain($domain) { return $this->ovhPost->deleteDomain($domain); }
    public function getOvhIP() {
        $model = new NetworkConfig();
        return $model->all();
    }
    
    // OGP Game Server
    public function getOGPGameServers() {
        try {
            // Vereinfachte Implementierung - in Produktion über OGP API
            return [
                [
                    'id' => 1,
                    'name' => 'Game Server 1',
                    'status' => 'running',
                    'players' => 12,
                    'max_players' => 32,
                    'map' => 'de_dust2',
                    'ip' => '192.168.1.100',
                    'port' => 27015
                ],
                [
                    'id' => 2,
                    'name' => 'Game Server 2',
                    'status' => 'stopped',
                    'players' => 0,
                    'max_players' => 64,
                    'map' => 'de_mirage',
                    'ip' => '192.168.1.101',
                    'port' => 27016
                ]
            ];
        } catch (Exception $e) {
            error_log("Error getting OGP game servers: " . $e->getMessage());
            return [];
        }
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
        // Einfache CPU-Auslastung über /proc/loadavg
        if (file_exists('/proc/loadavg')) {
            $load = file_get_contents('/proc/loadavg');
            $loads = explode(' ', $load);
            return [
                '1min' => floatval($loads[0]),
                '5min' => floatval($loads[1]),
                '15min' => floatval($loads[2])
            ];
        }
        return ['1min' => 0, '5min' => 0, '15min' => 0];
    }
    
    private function getMemoryUsage() {
        // Speicherauslastung über /proc/meminfo
        if (file_exists('/proc/meminfo')) {
            $meminfo = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
            preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);
            
            if (isset($total[1]) && isset($available[1])) {
                $total = intval($total[1]);
                $available = intval($available[1]);
                $used = $total - $available;
                return [
                    'total' => $total,
                    'used' => $used,
                    'available' => $available,
                    'percentage' => round(($used / $total) * 100, 2)
                ];
            }
        }
        return ['total' => 0, 'used' => 0, 'available' => 0, 'percentage' => 0];
    }
    
    private function getDiskUsage() {
        // Festplattenauslastung
        $disk = disk_free_space('/');
        $total = disk_total_space('/');
        $used = $total - $disk;
        
        return [
            'total' => $total,
            'used' => $used,
            'free' => $disk,
            'percentage' => round(($used / $total) * 100, 2)
        ];
    }
    
    private function getUptime() {
        // System-Uptime
        if (file_exists('/proc/uptime')) {
            $uptime = file_get_contents('/proc/uptime');
            $uptime = floatval(explode(' ', $uptime)[0]);
            
            $days = floor($uptime / 86400);
            $hours = floor(($uptime % 86400) / 3600);
            $minutes = floor(($uptime % 3600) / 60);
            
            return [
                'seconds' => $uptime,
                'formatted' => "{$days}d {$hours}h {$minutes}m"
            ];
        }
        return ['seconds' => 0, 'formatted' => '0d 0h 0m'];
    }
    
    private function getLoadAverage() {
        // Load Average
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
    
    private function getNetworkStatus() {
        // Netzwerkstatus (vereinfacht)
        return [
            'status' => 'online',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
} 