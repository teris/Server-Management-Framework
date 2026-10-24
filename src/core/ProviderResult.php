<?php
if (PHP_SAPI !== 'cli' && (get_included_files()[0] ?? null) === __FILE__) { http_response_code(404); exit; }
final class ProviderResult {
    public static function succeeded($result): bool {
        if ($result === false || $result === null || $result === 0 || $result === '') return false;
        if (is_array($result) && ((isset($result['success']) && $result['success'] !== true) || !empty($result['error']))) return false;
        return true;
    }
    public static function normalize($result, string $provider, bool $write = false): array {
        $success = self::succeeded($result) && (!$write || $result !== []);
        return ['success' => $success, 'provider' => $provider, 'data' => $success ? $result : null,
            'error' => $success ? null : (is_array($result) ? ($result['error'] ?? 'PROVIDER_REJECTED') : 'UNCONFIRMED'),
            'uncertain' => !$success && $write && !is_array($result),
            'message' => $success ? 'Aktion bestätigt.' : 'Aktion nicht bestätigt. Providerstatus vor einer Wiederholung prüfen.'];
    }
}
