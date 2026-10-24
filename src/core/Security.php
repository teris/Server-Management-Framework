<?php
if (PHP_SAPI !== 'cli' && (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__ || (get_included_files()[0] ?? null) === __FILE__)) { http_response_code(404); exit; }

/** Shared HTTP boundary. Never trusts request data as an identity or a path. */
class SecurityViolation extends RuntimeException {}

final class HttpSecurity {
    private static $connection;

    public static function connection() {
        if (self::$connection instanceof PDO) return self::$connection;
        $connection = DatabaseManager::getInstance()->getConnection();
        if (!$connection instanceof PDO) throw new RuntimeException('Security requires a supported SQL PDO connection');
        return $connection;
    }

    /** Dependency injection for CLI tools and isolated tests, never from a request. */
    public static function useConnection(PDO $connection) { self::$connection = $connection; }

    public static function privateDirectory($area = '') {
        $root = realpath(dirname(__DIR__, 2));
        $base = getenv('SMF_PRIVATE_DIR') ?: dirname($root) . '/.smf-private/' . substr(hash('sha256', $root), 0, 16);
        if (is_link($base)) throw new RuntimeException('Private storage symlinks are forbidden');
        if (!is_dir($base) && !@mkdir($base, 0700, true) && !is_dir($base)) throw new RuntimeException('Private storage unavailable');
        $base = realpath($base);
        if (!$base || $base === $root || strpos($base . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR) === 0 || is_link($base)) {
            throw new RuntimeException('SMF_PRIVATE_DIR must be outside the application directory');
        }
        if ($area !== '' && !preg_match('/^[a-z][a-z0-9-]*$/D', $area)) throw new InvalidArgumentException('Invalid storage area');
        $path = $area === '' ? $base : $base . '/' . $area;
        if (is_link($path)) throw new RuntimeException('Storage symlinks are forbidden');
        if (!is_dir($path) && !@mkdir($path, 0700, true) && !is_dir($path)) throw new RuntimeException('Private storage unavailable');
        @chmod($path, 0700);
        return $path;
    }

    public static function isHttps() {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') return true;
        $trusted = array_filter(array_map('trim', explode(',', getenv('SMF_TRUSTED_PROXIES') ?: '')));
        return in_array($_SERVER['REMOTE_ADDR'] ?? '', $trusted, true)
            && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    public static function requestScript() {
        // SCRIPT_FILENAME can contain PATH_INFO under some PHP handlers.
        // The first included file is the actual executed entry point.
        return str_replace('\\', '/', get_included_files()[0] ?? ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    }

    public static function timeout() {
        global $system_config;
        return max(60, min(86400, (int) ($system_config['session_timeout'] ?? 3600)));
    }

    public static function session() {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        if (headers_sent()) throw new RuntimeException('Session must start before output');
        session_name('server_mgmt_session');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.save_path', self::privateDirectory('sessions'));
        ini_set('session.gc_maxlifetime', (string) self::timeout());
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => self::isHttps(), 'httponly' => true, 'samesite' => 'Lax']);
        if (!session_start()) throw new RuntimeException('Session unavailable');
        if (!isset($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    public static function csrfToken() { self::session(); return $_SESSION['csrf_token']; }
    public static function csrfField() { return '<input type="hidden" name="csrf_token" value="' . self::csrfToken() . '">'; }
    public static function validCsrf($token = null) {
        self::session();
        $token = $token ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null);
        return is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
    }
    public static function requireCsrf() {
        if (!self::validCsrf()) throw new SecurityViolation('Ungültige oder fehlende Formularfreigabe. Bitte die Seite neu laden.', 403);
    }

    /** One lock covers read, increment and persist, including concurrent workers. */
    public static function rateLimit($name, $identifier, $maximum, $seconds) {
        $maximum = max(1, (int) $maximum); $seconds = max(1, (int) $seconds);
        $path = self::privateDirectory('rate-limits') . '/' . hash('sha256', $name . "\0" . $identifier) . '.json';
        $file = fopen($path, 'c+');
        if (!$file || !flock($file, LOCK_EX)) throw new RuntimeException('Rate limiting unavailable');
        @chmod($path, 0600);
        try {
            $state = json_decode(stream_get_contents($file), true);
            $now = time();
            if (!is_array($state) || ($state['until'] ?? 0) <= $now) $state = ['count' => 0, 'until' => $now + $seconds];
            if ($state['count'] >= $maximum) throw new SecurityViolation('Zu viele Anfragen. Bitte später erneut versuchen.', 429);
            $state['count']++;
            rewind($file); ftruncate($file, 0);
            if (fwrite($file, json_encode($state)) === false || !fflush($file)) throw new RuntimeException('Rate limiting unavailable');
        } finally { flock($file, LOCK_UN); fclose($file); }
    }

    public static function guardRequest() {
        if (PHP_SAPI === 'cli') return;
        $localPreview = PHP_SAPI === 'cli-server' && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
        if (!self::isHttps() && !$localPreview) throw new SecurityViolation('Für diese Anwendung ist HTTPS erforderlich.', 426);
        self::session();
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: same-origin');
        header('Cache-Control: private, no-store');
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['GET', 'HEAD', 'POST', 'OPTIONS'], true)) throw new SecurityViolation('Methode nicht erlaubt.', 405);
        if ($method === 'POST') self::requireCsrf();
        if (isset($_GET['logout'])) throw new SecurityViolation('Abmelden erfordert eine geschützte POST-Anfrage.', 405);
        $script = self::requestScript();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        global $security_config, $api_config;
        if ($method === 'POST' && preg_match('~/(?:public|src)/login\.php$~D', $script)) {
            $limit = (int) ($security_config['max_login_attempts'] ?? 5);
            $window = (int) ($security_config['lockout_duration'] ?? 900);
            self::rateLimit('login-ip', $ip, $limit, $window);
            $account = strtolower(trim((string) ($_POST['username'] ?? $_POST['email'] ?? '')));
            self::rateLimit('login-account', $account, $limit, $window);
        }
        if ($method === 'POST') {
            $kind = basename($script, '.php');
            if (in_array($kind, ['register', 'contact', 'tickets'], true)) self::rateLimit('public-' . $kind, $ip, 10, 3600);
            if ($kind === 'support') self::rateLimit('support', $ip, 30, 3600);
            if ($kind === 'domain-registration') self::rateLimit('domain-check', $ip, 30, 60);
        }
        if (strpos($script, '/src/api/') !== false && !empty($api_config['rate_limit']['enabled'])) {
            self::rateLimit('api-minute', $ip, $api_config['rate_limit']['requests_per_minute'] ?? 60, 60);
            self::rateLimit('api-hour', $ip, $api_config['rate_limit']['requests_per_hour'] ?? 1000, 3600);
        }
    }

    public static function denyDirect($file) {
        if (PHP_SAPI !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath($file)) self::abort(404, 'Nicht gefunden.');
    }
    public static function abort($status, $message) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        if ($status === 429) header('Retry-After: 60');
        echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
    public static function encode($value, $flags = 0, $depth = 512) { return json_encode(self::redact($value), $flags, $depth); }
    public static function audit($event, $realm, $id = null) {
        $record = ['time' => gmdate('c'), 'event' => $event, 'realm' => $realm, 'subject_id' => $id, 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'cli'];
        $file = self::privateDirectory('logs') . '/authentication-' . gmdate('Y-m-d') . '.jsonl';
        if (file_put_contents($file, json_encode($record) . "\n", FILE_APPEND | LOCK_EX) === false) throw new RuntimeException('Audit unavailable');
        @chmod($file, 0600);
    }
    public static function redact($value) {
        if (!is_array($value)) return $value;
        foreach ($value as $key => $item) {
            if (is_string($key) && (preg_match('/password|passwd|secret|cookie|authorization|^.*token$|^api_key|^consumer_key$|^raw_response$|^debug_info$/i', $key) || (strtolower($key) === 'ticket' && is_string($item)))) $value[$key] = '[redacted]';
            elseif (is_array($item)) $value[$key] = self::redact($item);
        }
        return $value;
    }
}
