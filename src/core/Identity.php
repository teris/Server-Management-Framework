<?php
if (PHP_SAPI !== 'cli' && (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__ || (get_included_files()[0] ?? null) === __FILE__)) { http_response_code(404); exit; }

require_once __DIR__ . '/Security.php';

final class Identity {
    public static function current($realm = 'backend') {
        HttpSecurity::session();
        if (!in_array($realm, ['backend', 'customer'], true)) return null;
        $identity = $_SESSION['identities'][$realm] ?? null;
        if (!is_array($identity) || time() - ($identity['last_activity'] ?? 0) > HttpSecurity::timeout()) {
            self::clear($realm); return null;
        }
        $sql = $realm === 'backend'
            ? "SELECT * FROM users WHERE id = ? AND active = 'y'"
            : "SELECT * FROM customers WHERE id = ? AND status = 'active'";
        $stmt = HttpSecurity::connection()->prepare($sql); $stmt->execute([$identity['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !hash_equals($identity['credential'], hash('sha256', $row['password_hash']))) {
            self::clear($realm); return null;
        }
        $role = $realm === 'backend' ? $row['role'] : 'customer';
        if (($_POST['action'] ?? '') !== 'heartbeat' && !isset($_GET['heartbeat'])) $_SESSION['identities'][$realm]['last_activity'] = time();
        $_SESSION['identities'][$realm]['role'] = $role;
        if ($realm === 'backend') {
            $_SESSION['user'] = ['id' => (int) $row['id'], 'username' => $row['username'], 'email' => $row['email'], 'full_name' => $row['full_name'], 'role' => $role];
            foreach ($_SESSION['user'] as $key => $value) $_SESSION[$key === 'id' ? 'user_id' : $key] = $value;
            $_SESSION['last_activity'] = time();
        } else {
            $_SESSION['customer_logged_in'] = true; $_SESSION['customer_id'] = (int) $row['id'];
            $_SESSION['customer_email'] = $row['email']; $_SESSION['customer_name'] = trim($row['first_name'] . ' ' . $row['last_name']);
        }
        return ['realm' => $realm, 'id' => (int) $row['id'], 'role' => $role, 'record' => $row];
    }

    public static function login($realm, array $row) {
        if (!in_array($realm, ['backend', 'customer'], true)) throw new InvalidArgumentException('Unknown identity realm');
        HttpSecurity::session();
        if (!session_regenerate_id(true)) throw new RuntimeException('Session rotation failed');
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['identities'][$realm] = ['id' => (int) $row['id'], 'role' => $realm === 'backend' ? $row['role'] : 'customer',
            'credential' => hash('sha256', $row['password_hash']), 'login_time' => time(), 'last_activity' => time()];
        $_SESSION['login_time'] = time();
        HttpSecurity::audit('login', $realm, (int) $row['id']);
        return self::current($realm);
    }

    public static function clear($realm) {
        unset($_SESSION['identities'][$realm]);
        $keys = $realm === 'backend' ? ['user', 'user_id', 'role', 'username', 'email', 'full_name', 'last_activity']
            : ['customer_logged_in', 'customer_id', 'customer_email', 'customer_name'];
        foreach ($keys as $key) unset($_SESSION[$key]);
    }

    public static function requireIdentity($realm = 'backend', $admin = false) {
        $identity = self::current($realm);
        if (!$identity) throw new SecurityViolation('Anmeldung erforderlich.', 401);
        if ($admin && $identity['role'] !== 'admin') throw new SecurityViolation('Administratorrechte erforderlich.', 403);
        return $identity;
    }

    public static function logout() {
        HttpSecurity::session();
        $customer = self::current('customer');
        if ($customer) RememberTokens::revoke($customer['id']);
        HttpSecurity::audit('logout', $customer ? 'customer' : 'backend', $customer['id'] ?? ($_SESSION['identities']['backend']['id'] ?? null));
        self::rememberCookie('', time() - 3600);
        $_SESSION = [];
        $params = session_get_cookie_params(); $params['expires'] = time() - 3600; unset($params['lifetime']);
        setcookie(session_name(), '', $params);
        session_destroy();
    }

    public static function rememberCookie($value, $expires) {
        setcookie('remember_token', $value, ['expires' => $expires, 'path' => '/', 'secure' => HttpSecurity::isHttps(), 'httponly' => true, 'samesite' => 'Lax']);
    }
}

final class RememberTokens {
    public static function issue(array $customer, $connection = null) {
        $db = $connection ?: HttpSecurity::connection();
        $selector = bin2hex(random_bytes(12)); $validator = bin2hex(random_bytes(32));
        $stmt = $db->prepare('INSERT INTO security_remember_tokens (selector, customer_id, token_hash, credential, expires_at) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$selector, $customer['id'], hash('sha256', $validator), hash('sha256', $customer['password_hash']), time() + 2592000]);
        return $selector . '.' . $validator;
    }

    public static function consume($token) {
        if (!is_string($token) || !preg_match('/^([a-f0-9]{24})\.([a-f0-9]{64})$/D', $token, $parts)) return null;
        $db = HttpSecurity::connection();
        $stmt = $db->prepare("SELECT t.*, c.password_hash, c.status FROM security_remember_tokens t JOIN customers c ON c.id = t.customer_id WHERE t.selector = ?");
        $stmt->execute([$parts[1]]); $stored = $stmt->fetch(PDO::FETCH_ASSOC); $stmt->closeCursor();
        if (!$stored || !hash_equals($stored['token_hash'], hash('sha256', $parts[2])) || $stored['expires_at'] <= time()
            || $stored['status'] !== 'active' || !hash_equals($stored['credential'], hash('sha256', $stored['password_hash']))) return null;
        $db->beginTransaction();
        try {
            $delete = $db->prepare('DELETE FROM security_remember_tokens WHERE selector = ? AND token_hash = ?');
            $delete->execute([$parts[1], $stored['token_hash']]);
            if ($delete->rowCount() !== 1) { $db->rollBack(); return null; }
            $stmt = $db->prepare("SELECT * FROM customers WHERE id = ? AND status = 'active'");
            $stmt->execute([$stored['customer_id']]); $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$customer || !hash_equals($stored['credential'], hash('sha256', $customer['password_hash']))) { $db->rollBack(); return null; }
            $next = self::issue($customer, $db);
            $db->commit();
            return ['customer' => $customer, 'token' => $next];
        } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    }

    public static function revoke($customerId) {
        $stmt = HttpSecurity::connection()->prepare('DELETE FROM security_remember_tokens WHERE customer_id = ?');
        $stmt->execute([$customerId]);
    }
}
