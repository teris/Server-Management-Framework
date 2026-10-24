<?php
if (PHP_SAPI !== 'cli' && (get_included_files()[0] ?? null) === __FILE__) { http_response_code(404); exit; }

final class Mailer {
    public static function siteUrl(): string {
        $url = rtrim(getenv('SMF_PUBLIC_URL') ?: Config::FRONTPANEL_SITE_URL, '/');
        $parts = parse_url($url);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || preg_match('/[\x00-\x20]/', $url)) throw new RuntimeException('Eine feste HTTPS-Portaladresse muss konfiguriert sein');
        return $url;
    }
    public static function send(string $recipient, string $subject, string $html): bool {
        $from = Config::FRONTPANEL_SYSTEM_EMAIL;
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $subject . $recipient . $from)) throw new InvalidArgumentException('Invalid email headers');
        $headers = 'From: ' . $from . "\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8";
        $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $host = getenv('SMF_SMTP_HOST');
        if (!$host) return mail($recipient, $subject, $html, $headers);
        if (!preg_match('/^[a-zA-Z0-9.-]+$/D', $host)) throw new RuntimeException('Invalid SMTP host');
        $port = (int) (getenv('SMF_SMTP_PORT') ?: 465);
        $ssl = ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host, 'allow_self_signed' => false];
        if (getenv('SMF_SMTP_CA_FILE')) $ssl['cafile'] = getenv('SMF_SMTP_CA_FILE');
        $socket = @stream_socket_client('tls://' . $host . ':' . $port, $errno, $error, 10, STREAM_CLIENT_CONNECT, stream_context_create(['ssl' => $ssl]));
        if (!$socket) throw new RuntimeException('SMTP connection unavailable');
        stream_set_timeout($socket, 15);
        $read = function (array $codes) use ($socket) {
            $length = 0;
            do { $line = fgets($socket, 2048); if ($line === false || ($length += strlen($line)) > 65536) throw new RuntimeException('SMTP response unavailable'); } while (isset($line[3]) && $line[3] === '-');
            if (!in_array((int) substr($line, 0, 3), $codes, true)) throw new RuntimeException('SMTP command rejected');
        };
        $command = function (string $value, array $codes) use ($socket, $read) { $remaining = $value . "\r\n"; while ($remaining !== '') { $written = fwrite($socket, $remaining); if (!$written) throw new RuntimeException('SMTP write failed'); $remaining = substr($remaining, $written); } $read($codes); };
        try {
            $read([220]); $command('EHLO localhost', [250]);
            if (getenv('SMF_SMTP_USER') !== false && getenv('SMF_SMTP_USER') !== '') {
                $command('AUTH LOGIN', [334]); $command(base64_encode(getenv('SMF_SMTP_USER')), [334]); $command(base64_encode(getenv('SMF_SMTP_PASS') ?: ''), [235]);
            }
            $command('MAIL FROM:<' . $from . '>', [250]); $command('RCPT TO:<' . $recipient . '>', [250, 251]); $command('DATA', [354]);
            $message = 'To: ' . $recipient . "\r\nSubject: " . $subject . "\r\nDate: " . gmdate('D, d M Y H:i:s +0000') . "\r\nMessage-ID: <" . bin2hex(random_bytes(16)) . "@localhost>\r\n" . $headers . "\r\n\r\n" . $html;
            $message = str_replace(["\r\n", "\r"], "\n", $message); $message = preg_replace('/^\./m', '..', $message);
            $command(str_replace("\n", "\r\n", $message) . "\r\n.", [250]);
            fwrite($socket, "QUIT\r\n"); return true;
        } finally { fclose($socket); }
    }
}
