<?php
if (PHP_SAPI !== 'cli' && (get_included_files()[0] ?? null) === __FILE__) { http_response_code(404); exit; }

final class VersionFile {
    public static function blocks(string $source): array {
        $tokens = token_get_all($source); $allowed = ['$plugins','$system_config','$feature_flags','$api_config','$security_config','$modus_type','$dashboard_config'];
        $offset = 0; $curly = 0; $blocks = [];
        for ($i=0; $i<count($tokens); $i++) {
            $token = $tokens[$i]; $text = is_array($token) ? $token[1] : $token;
            if ($curly === 0 && is_array($token) && $token[0] === T_VARIABLE && in_array($text, $allowed, true) && !isset($blocks[$text])) {
                $j = $i+1; while (isset($tokens[$j]) && is_array($tokens[$j]) && in_array($tokens[$j][0],[T_WHITESPACE,T_COMMENT,T_DOC_COMMENT],true)) $j++;
                if (($tokens[$j] ?? null) === '=') {
                    $block = ''; $depth = 0;
                    for ($k=$i; $k<count($tokens); $k++) {
                        $part = $tokens[$k]; $value = is_array($part) ? $part[1] : $part; $block .= $value;
                        if (!is_array($part)) { if (in_array($part,['(','[','{'],true)) $depth++; if (in_array($part,[')',']','}'],true)) $depth--; if ($part === ';' && $depth === 0) { $blocks[$text] = $block; break; } }
                    }
                }
            }
            if ($token === '{') $curly++; elseif ($token === '}') $curly--;
            $offset += strlen($text);
        }
        return $blocks;
    }
    public static function version(string $source): string {
        $block = self::blocks($source)['$system_config'] ?? '';
        if (!preg_match('/[\'"]version[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $block, $match)) throw new RuntimeException('Framework version missing');
        return $match[1];
    }
    public static function merge(string $previous, string $incoming, string $version): string {
        if (!preg_match('/^\d+\.\d+\.\d+(?:-[a-zA-Z0-9.-]+)?$/D',$version)) throw new InvalidArgumentException('Invalid framework version');
        $old = self::blocks($previous); $new = self::blocks($incoming);
        foreach ($old as $name=>$block) {
            if (!isset($new[$name])) throw new RuntimeException('Configuration contract changed; manual migration required');
            if ($name === '$system_config') {
                $block = preg_replace('/([\'"]version[\'"]\s*=>\s*)[\'"][^\'"]*[\'"]/', '$1\'' . $version . '\'', $block, -1, $count);
                if ($count !== 1) throw new RuntimeException('Ambiguous framework version');
            }
            $incoming = str_replace($new[$name], $block, $incoming);
        }
        if (self::version($incoming) !== $version) throw new RuntimeException('Version write failed');
        return $incoming;
    }
}
