<?php
if (PHP_SAPI !== 'cli' && (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__ || (get_included_files()[0] ?? null) === __FILE__)) { http_response_code(404); exit; }

require_once __DIR__ . '/Identity.php';

final class PrivateFiles {
    public static function create(array $identity, $filename, $content, $kind = 'export', $seconds = 3600) {
        if (!in_array($kind, ['export', 'backup'], true) || !in_array(pathinfo($filename, PATHINFO_EXTENSION), ['txt', 'csv', 'json', 'zip'], true)
            || !preg_match('/^[a-zA-Z0-9_.-]{1,160}$/D', $filename) || strlen($content) > 10 * 1024 * 1024) throw new InvalidArgumentException('Invalid export');
        if ($kind === 'backup' && !($identity['realm'] === 'backend' && $identity['role'] === 'admin')) throw new SecurityViolation('Administratorrechte erforderlich.', 403);
        $id = bin2hex(random_bytes(24)); $directory = HttpSecurity::privateDirectory('exports');
        $meta = ['owner' => $identity['realm'] . ':' . $identity['id'], 'kind' => $kind, 'filename' => $filename, 'expires' => time() + max(1, min(86400, $seconds))];
        foreach (['.data' => $content, '.json' => json_encode($meta)] as $suffix => $value) {
            $file = fopen($directory . '/' . $id . $suffix, 'x');
            if (!$file) throw new RuntimeException('Export unavailable');
            @chmod($directory . '/' . $id . $suffix, 0600);
            try { if (fwrite($file, $value) !== strlen($value)) throw new RuntimeException('Export could not be saved'); }
            finally { fclose($file); }
        }
        return $id;
    }

    public static function read(array $identity, $id) {
        if (!is_string($id) || !preg_match('/^[a-f0-9]{48}$/D', $id)) throw new SecurityViolation('Datei nicht gefunden.', 404);
        $directory = HttpSecurity::privateDirectory('exports');
        foreach (['.data', '.json'] as $suffix) {
            $path = $directory . '/' . $id . $suffix;
            if (is_link($path) || !is_file($path) || realpath(dirname($path)) !== $directory) throw new SecurityViolation('Datei nicht gefunden.', 404);
        }
        $meta = json_decode(file_get_contents($directory . '/' . $id . '.json'), true);
        if (!is_array($meta) || ($meta['owner'] ?? '') !== $identity['realm'] . ':' . $identity['id']) throw new SecurityViolation('Datei nicht gefunden.', 404);
        if (($meta['kind'] ?? '') === 'backup' && !($identity['realm'] === 'backend' && $identity['role'] === 'admin')) throw new SecurityViolation('Administratorrechte erforderlich.', 403);
        if (($meta['expires'] ?? 0) <= time()) throw new SecurityViolation('Datei abgelaufen.', 410);
        return ['filename' => $meta['filename'], 'content' => file_get_contents($directory . '/' . $id . '.data')];
    }
}
