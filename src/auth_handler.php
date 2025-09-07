<?php
/**
 * Server Management Framework
 * 
 * @author Teris
 * @version 3.1.2
 */

class SessionManager {
    const SESSION_TIMEOUT = 600; // 10 Minuten in Sekunden
    const SESSION_NAME = 'server_mgmt_session';
    
    public static function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(self::SESSION_NAME);
            
            // Sichere Session-Konfiguration
            ini_set('session.cookie_httponly', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_secure', $_SERVER['HTTPS'] ?? false ? '1' : '0');
            ini_set('session.cookie_samesite', 'Strict');
            
            session_start();
            
            // Session-Regeneration für Sicherheit
            if (!isset($_SESSION['initiated'])) {
                session_regenerate_id(true);
                $_SESSION['initiated'] = true;
            }
        }
    }
    
    /**
     * Prüft ob Benutzer eingeloggt ist und Session gültig
     */
    public static function isLoggedIn() {
        self::startSession();
        
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_activity'])) {
            return false;
        }
        
        // Session-Timeout prüfen
        if (time() - $_SESSION['last_activity'] > self::SESSION_TIMEOUT) {
            self::logout();
            return false;
        }
        
        // Session verlängern bei Aktivität
        self::updateActivity();
        
        return true;
    }
    
    /**
     * Aktualisiert die letzte Aktivitätszeit
     */
    public static function updateActivity() {
        self::startSession();
        $_SESSION['last_activity'] = time();
    }
    
    /**
     * Erstellt eine neue Login-Session
     */
    public static function createSession($user_data) {
        self::startSession();
        
        // Session-ID neu generieren für Sicherheit
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user_data['id'];
        $_SESSION['username'] = $user_data['username'];
        $_SESSION['email'] = $user_data['email'];
        $_SESSION['role'] = $user_data['role'];
        $_SESSION['full_name'] = $user_data['full_name'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // Login in Datenbank loggen
        try {
            $db = Database::getInstance();
            $db->logAction(
                'User Login', 
                "User '{$user_data['username']}' logged in from IP " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 
                'success'
            );
        } catch (Exception $e) {
            error_log('Failed to log user login: ' . $e->getMessage());
        }
    }
    
    /**
     * Beendet die Session (Logout)
     */
    public static function logout() {
        self::startSession();
        
        // Logout in Datenbank loggen
        if (isset($_SESSION['username'])) {
            try {
                $db = Database::getInstance();
                $db->logAction(
                    'User Logout', 
                    "User '{$_SESSION['username']}' logged out", 
                    'success'
                );
            } catch (Exception $e) {
                error_log('Failed to log user logout: ' . $e->getMessage());
            }
        }
        
        // Session-Daten löschen
        $_SESSION = array();
        
        // Session-Cookie löschen
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Session zerstören
        session_destroy();
    }
    
    /**
     * Gibt Benutzerinformationen zurück
     */
    public static function getUserInfo() {
        if (!self::isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'email' => $_SESSION['email'],
            'role' => $_SESSION['role'],
            'full_name' => $_SESSION['full_name'],
            'login_time' => $_SESSION['login_time'],
            'session_remaining' => self::SESSION_TIMEOUT - (time() - $_SESSION['last_activity'])
        ];
    }
    
    /**
     * Prüft ob Benutzer Admin-Rechte hat
     */
    public static function isAdmin() {
        $user = self::getUserInfo();
        return $user !== null && isset($user['role']) && $user['role'] === 'admin';
    }
    
    /**
     * Gibt verbleibende Session-Zeit in Sekunden zurück
     */
    public static function getSessionTimeRemaining() {
        if (!isset($_SESSION['last_activity'])) {
            return 0;
        }
        
        $remaining = self::SESSION_TIMEOUT - (time() - $_SESSION['last_activity']);
        return max(0, $remaining);
    }
}

class AuthenticationHandler {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Authentifiziert einen Benutzer
     */
    public function login($username, $password) {
        try {
            // Benutzer aus Datenbank laden
            $stmt = $this->db->prepare("
                SELECT id, username, email, password_hash, full_name, role, active, last_login 
                FROM users 
                WHERE username = ? AND active = 'y'
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $this->logFailedLogin($username, 'User not found');
                return [
                    'success' => false,
                    'message' => 'Benutzername oder Passwort ist falsch.'
                ];
            }
            
            // Passwort verifizieren
            if (!password_verify($password, $user['password_hash'])) {
                $this->logFailedLogin($username, 'Wrong password');
                return [
                    'success' => false,
                    'message' => 'Benutzername oder Passwort ist falsch.'
                ];
            }
            
            // Letzten Login aktualisieren
            $this->updateLastLogin($user['id']);
            
            // Session erstellen
            SessionManager::createSession($user);
            
            return [
                'success' => true,
                'message' => 'Login erfolgreich.',
                'user' => [
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name'],
                    'role' => $user['role']
                ]
            ];
            
        } catch (Exception $e) {
            error_log('Login error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.'
            ];
        }
    }
    
    /**
     * Erstellt einen neuen Benutzer
     */
    public function createUser($username, $email, $password, $full_name, $role = 'user') {
        try {
            // Prüfen ob Benutzername bereits existiert
            $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Benutzername oder E-Mail bereits vergeben.'
                ];
            }
            
            // Passwort hashen
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Benutzer erstellen
            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, password_hash, full_name, role, active, created_at) 
                VALUES (?, ?, ?, ?, ?, 'y', NOW())
            ");
            
            $result = $stmt->execute([$username, $email, $password_hash, $full_name, $role]);
            
            if ($result) {
                $userId = $this->db->lastInsertId();
                
                // User-Erstellung loggen
                $db = Database::getInstance();
                $db->logAction(
                    'User Created', 
                    "New user '{$username}' created with role '{$role}'", 
                    'success'
                );
                
                return [
                    'success' => true,
                    'message' => 'Benutzer erfolgreich erstellt.',
                    'user_id' => $userId
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Fehler beim Erstellen des Benutzers.'
                ];
            }
            
        } catch (Exception $e) {
            error_log('User creation error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ein Fehler ist aufgetreten beim Erstellen des Benutzers.'
            ];
        }
    }
    
    /**
     * Ändert das Passwort eines Benutzers
     */
    public function changePassword($user_id, $old_password, $new_password) {
        try {
            // Aktuelles Passwort prüfen
            $stmt = $this->db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !password_verify($old_password, $user['password_hash'])) {
                return [
                    'success' => false,
                    'message' => 'Aktuelles Passwort ist falsch.'
                ];
            }
            
            // Neues Passwort setzen
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $result = $stmt->execute([$new_password_hash, $user_id]);
            
            if ($result) {
                // Passwort-Änderung loggen
                $db = Database::getInstance();
                $db->logAction(
                    'Password Changed', 
                    "User ID {$user_id} changed password", 
                    'success'
                );
                
                return [
                    'success' => true,
                    'message' => 'Passwort erfolgreich geändert.'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Fehler beim Ändern des Passworts.'
                ];
            }
            
        } catch (Exception $e) {
            error_log('Password change error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Ein Fehler ist aufgetreten beim Ändern des Passworts.'
            ];
        }
    }
    
    /**
     * Loggt fehlgeschlagene Login-Versuche
     */
    private function logFailedLogin($username, $reason) {
        try {
            $db = Database::getInstance();
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $db->logAction(
                'Failed Login', 
                "Failed login attempt for user '{$username}' from IP {$ip}. Reason: {$reason}", 
                'error'
            );
        } catch (Exception $e) {
            error_log('Failed to log failed login: ' . $e->getMessage());
        }
    }
    
    /**
     * Aktualisiert den letzten Login-Zeitpunkt
     */
    private function updateLastLogin($user_id) {
        try {
            $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user_id]);
        } catch (Exception $e) {
            error_log('Failed to update last login: ' . $e->getMessage());
        }
    }
    
    /**
     * Gibt alle Benutzer zurück (nur für Admins)
     */
    public function getAllUsers() {
        try {
            $stmt = $this->db->prepare("
                SELECT id, username, email, full_name, role, active, created_at, last_login 
                FROM users 
                ORDER BY created_at DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Get users error: ' . $e->getMessage());
            return [];
        }
    }
}

/**
 * Middleware-Funktion für AJAX-Requests
 * Prüft Session und verlängert sie automatisch
 */
function checkAjaxAuth() {
    SessionManager::startSession();
    
    if (!SessionManager::isLoggedIn()) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Session expired',
            'redirect' => 'login.php'
        ]);
        exit;
    }
    
    // Session bei jeder AJAX-Anfrage verlängern
    SessionManager::updateActivity();
}

/**
 * Middleware-Funktion für normale Seiten
 */
function requireLogin() {
    SessionManager::startSession();
    
    if (!SessionManager::isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
    
    // Session bei jedem Seitenaufruf verlängern
    SessionManager::updateActivity();
}

/**
 * Gibt Session-Informationen für JavaScript zurück
 */
function getSessionInfoForJS() {
    if (!SessionManager::isLoggedIn()) {
        return null;
    }
    
    return [
        'user' => SessionManager::getUserInfo(),
        'timeRemaining' => SessionManager::getSessionTimeRemaining(),
        'timeout' => SessionManager::SESSION_TIMEOUT
    ];
}

// ============================================================================
// HINWEIS: Modul-Funktionen sind bereits in framework.php implementiert
// ============================================================================
// 
// Die folgenden Funktionen sind bereits in framework.php verfügbar:
// - getModuleConfig()
// - getModulePermissions()
// - getModuleSettings()
// - getModuleLogs()
// - getModuleStats()
// - getModuleTranslations()
// - getModuleAssets()
// - getModuleTemplates()
// - getModuleDependencies()
// - getModuleVersion()
// - getModuleAuthor()
// - getModuleDescription()
// - getModuleLicense()
// - getModuleSupport()
// - getModuleChangelog()
// - getModuleInstallation()
// - getModuleConfiguration()
// - getModuleTroubleshooting()
// - getModuleFAQ()
// - getModuleExamples()
// - getModuleAPI()
// - getModuleTesting()
// - getModuleDeployment()
// - getModuleSecurity()
// - getModulePerformance()
// - getModuleMonitoring()
// - getModuleBackup()
// - getModuleRestore()
// - getModuleMigration()
// - getModuleUpgrade()
// - getModuleDowngrade()
// - getModuleRollback()
// - getModuleCleanup()
// - getModuleOptimization()
// - getModuleMaintenance()
// - getModuleHealth()
// - getModuleDiagnostics()
//
// Diese Funktionen werden von ModuleBase.php verwendet und sind bereits
// vollständig in framework.php implementiert.
// ============================================================================
?>