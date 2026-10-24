<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$manifest = json_decode(file_get_contents($root . '/deploy/package-files.json'), true, 512, JSON_THROW_ON_ERROR);
if (!is_array($manifest) || !$manifest || count($manifest) !== count(array_unique($manifest))) throw new RuntimeException('Invalid package manifest');
foreach ($manifest as $path) {
    if (!is_string($path) || !preg_match('~^[a-zA-Z0-9_.-]+(?:/[a-zA-Z0-9_.-]+)*$~D', $path)
        || in_array('..', explode('/', $path), true)
        || preg_match('~(?:^|/)(?:config\.inc\.php|setup-access\.php|smf_ogp_bridge_config\.php|smf-ogp-bridge\.json|sys\.conf\.local\.php|\.env[^/]*|\.encryption_key)$|\.(?:db|sqlite3?|log|bnk|bak|zip|pem|key|tar|tgz|gz|7z|rar)(?:[.-]|$)~i', $path))
        throw new RuntimeException('Unsafe package manifest entry');
    $candidate = $root;
    foreach (explode('/', $path) as $part) {
        $candidate .= '/' . $part;
        if (is_link($candidate)) throw new RuntimeException('Package symlink rejected');
    }
    if (!is_file($candidate) || !is_readable($candidate)) throw new RuntimeException('Package file unavailable: ' . $path);
}
sort($manifest); echo json_encode($manifest, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),"\n";
