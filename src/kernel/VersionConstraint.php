<?php
namespace SMF\Core;
use InvalidArgumentException;
final class VersionConstraint {
    public static function matches(string $version, string $constraint): bool {
        foreach (preg_split('/\s+/', trim($constraint)) as $part) {
            if ($part === '*') continue;
            if (!preg_match('/^(>=|<=|>|<|=|\^|~)?(\d+\.\d+(?:\.\d+)?)$/D', $part, $m)) throw new InvalidArgumentException('Invalid version constraint');
            $operator = $m[1] ?: '='; $target = $m[2];
            if (in_array($operator, ['^','~'], true)) {
                $segments = array_map('intval', explode('.', $target));
                $upper = $operator === '^' ? ($segments[0]>0?($segments[0]+1).'.0.0':($segments[1]>0?'0.'.($segments[1]+1).'.0':'0.0.'.(($segments[2]??0)+1))) : $segments[0] . '.' . ($segments[1] + 1) . '.0';
                if (!version_compare($version, $target, '>=') || !version_compare($version, $upper, '<')) return false;
            } elseif (!version_compare($version, $target, $operator)) return false;
        }
        return true;
    }
}
