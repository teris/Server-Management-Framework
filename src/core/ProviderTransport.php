<?php
if (PHP_SAPI !== 'cli' && (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__ || (get_included_files()[0] ?? null) === __FILE__)) { http_response_code(404); exit; }

/** HTTPS-only transport policy shared by every credential-bearing provider request. */
final class ProviderTransport {
    public static function checkUrl($url) {
        $parts = parse_url($url);
        if (!$parts || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || preg_match('/[\x00-\x20]/', $url))
            throw new InvalidArgumentException('Provider connections require an HTTPS URL without embedded credentials');
    }
    private static function caFile($provider) {
        $key = strtoupper($provider) . '_CA_FILE';
        $file = getenv('SMF_' . strtoupper($provider) . '_CA_FILE') ?: (getenv('SMF_CA_FILE') ?: (defined('Config::' . $key) ? constant('Config::' . $key) : ''));
        if ($file !== '' && (!is_file($file) || !is_readable($file))) throw new RuntimeException('Configured CA bundle unavailable');
        return $file;
    }
    public static function options($url, $provider) {
        self::checkUrl($url);
        $options = [CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 30, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS];
        $ca = self::caFile($provider);
        if ($ca !== '') $options[CURLOPT_CAINFO] = $ca;
        return $options;
    }
    public static function apply($handle, $url, $provider) {
        if (!curl_setopt_array($handle, self::options($url, $provider))) throw new RuntimeException('TLS configuration failed');
    }
    public static function soapContext($url, $provider) {
        self::checkUrl($url);
        $ssl = ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false];
        $ca = self::caFile($provider); if ($ca !== '') $ssl['cafile'] = $ca;
        return stream_context_create(['ssl' => $ssl, 'http' => ['timeout' => 30, 'follow_location' => 0]]);
    }
}
