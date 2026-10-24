<?php
if (PHP_SAPI !== 'cli' && (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__ || (get_included_files()[0] ?? null) === __FILE__)) { http_response_code(404); exit; }
require_once __DIR__ . '/core/Identity.php';

/** Compatibility facade; Identity is the single source for current account status and roles. */
class SessionManager {
    const SESSION_TIMEOUT = 3600;
    const SESSION_NAME = 'server_mgmt_session';
    public static function startSession() { HttpSecurity::session(); }
    public static function isLoggedIn() { return Identity::current('backend') !== null; }
    public static function updateActivity() { Identity::current('backend'); }
    public static function createSession($user) { return Identity::login('backend', $user); }
    public static function logout() { Identity::logout(); }
    public static function isAdmin() { $user = Identity::current('backend'); return $user && $user['role'] === 'admin'; }
    public static function getUserInfo() {
        $identity = Identity::current('backend');
        if (!$identity) return null;
        return array_merge($_SESSION['user'], ['login_time' => $_SESSION['identities']['backend']['login_time'], 'session_remaining' => self::getSessionTimeRemaining()]);
    }
    public static function getSessionTimeRemaining() {
        $last = $_SESSION['identities']['backend']['last_activity'] ?? 0;
        return max(0, HttpSecurity::timeout() - (time() - $last));
    }
}

class AuthenticationHandler {
    private $db;
    public function __construct($connection = null) { $this->db = $connection ?: HttpSecurity::connection(); }
    public function login($username, $password) {
        if (!is_string($username) || !is_string($password) || strlen($username) > 100 || strlen($password) > 4096) return $this->failure();
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ? AND active = 'y'");
        $stmt->execute([$username]); $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !password_verify($password, $user['password_hash'])) return $this->failure();
        $stmt = $this->db->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?'); $stmt->execute([$user['id']]);
        if (!Identity::login('backend', $user)) return $this->failure();
        return ['success' => true, 'message' => 'Login erfolgreich.', 'user' => $_SESSION['user']];
    }
    private function failure() { HttpSecurity::audit('login.failed', 'backend'); return ['success' => false, 'message' => 'Benutzername oder Passwort ist falsch.']; }
    public function createUser($username, $email, $password, $full_name, $role = 'user') {
        // First administrator is created by the explicit CLI bootstrap, never this public method.
        Identity::requireIdentity('backend', true);
        if (!in_array($role, ['admin', 'user', 'readonly'], true) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) throw new InvalidArgumentException('Ungültige Benutzerdaten.');
        $stmt = $this->db->prepare("INSERT INTO users (username,email,password_hash,full_name,role,active,created_at) VALUES (?,?,?,?,?,'y',CURRENT_TIMESTAMP)");
        $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $full_name, $role]);
        return ['success' => true, 'message' => 'Benutzer erstellt.', 'user_id' => $this->db->lastInsertId()];
    }
    public function changePassword($id, $old, $new) {
        $identity = Identity::requireIdentity('backend');
        if ($identity['id'] !== (int) $id) throw new SecurityViolation('Zugriff verweigert.', 403);
        return AccountPasswords::change('backend', $identity, $old, $new);
    }
    public function getAllUsers() {
        Identity::requireIdentity('backend', true);
        return $this->db->query('SELECT id,username,email,full_name,role,active,created_at,last_login FROM users ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
    }
}

final class CustomerAuthentication {
    public static function login($email, $password, $remember = false) {
        if (!is_string($email) || !is_string($password) || strlen($email) > 254 || strlen($password) > 4096) return false;
        $stmt = HttpSecurity::connection()->prepare("SELECT * FROM customers WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]); $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$customer || !password_verify($password, $customer['password_hash'])) { HttpSecurity::audit('login.failed', 'customer'); return false; }
        $token = $remember ? RememberTokens::issue($customer) : null;
        if (!Identity::login('customer', $customer)) return false;
        if ($token) Identity::rememberCookie($token, time() + 2592000);
        return true;
    }
    public static function remember($token) {
        $result = RememberTokens::consume($token);
        if (!$result) { Identity::rememberCookie('', time() - 3600); return false; }
        if (!Identity::login('customer', $result['customer'])) return false;
        Identity::rememberCookie($result['token'], time() + 2592000);
        return true;
    }
}

final class AccountPasswords {
    public static function change($realm, array $identity, $old, $new) {
        if ($identity['realm'] !== $realm || !is_string($old) || !is_string($new) || strlen($new) < 12 || strlen($new) > 4096) return ['success' => false, 'message' => 'Das neue Passwort muss mindestens 12 Zeichen enthalten.'];
        $table = $realm === 'backend' ? 'users' : 'customers';
        $db = HttpSecurity::connection(); $stmt = $db->prepare("SELECT * FROM $table WHERE id = ?");
        $stmt->execute([$identity['id']]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !password_verify($old, $row['password_hash'])) return ['success' => false, 'message' => 'Aktuelles Passwort ist falsch.'];
        $db->beginTransaction();
        try {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE $table SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND password_hash = ?");
            $stmt->execute([$hash, $identity['id'], $row['password_hash']]);
            if ($stmt->rowCount() !== 1) throw new SecurityViolation('Zugang wurde zwischenzeitlich geändert.', 409);
            if ($realm === 'customer') RememberTokens::revoke($identity['id']);
            $db->commit(); $row['password_hash'] = $hash;
            Identity::login($realm, $row);
            if ($realm === 'customer') Identity::rememberCookie('', time() - 3600);
            return ['success' => true, 'message' => 'Passwort geändert. Andere Sitzungen und Langzeitzugänge sind ungültig.'];
        } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    }
}

function checkAjaxAuth() {
    try { Identity::requireIdentity('backend'); }
    catch (SecurityViolation $e) { HttpSecurity::abort($e->getCode(), $e->getMessage()); }
}
function requireLogin() {
    if (!Identity::current('backend')) {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') HttpSecurity::abort(401, 'Anmeldung erforderlich.');
        header('Location: login.php', true, 303); exit;
    }
}
function getSessionInfoForJS() {
    if (!SessionManager::isLoggedIn()) return null;
    return ['user' => SessionManager::getUserInfo(), 'timeRemaining' => SessionManager::getSessionTimeRemaining(), 'timeout' => HttpSecurity::timeout()];
}
