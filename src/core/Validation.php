<?php
if (PHP_SAPI !== 'cli' && (get_included_files()[0] ?? null) === __FILE__) { http_response_code(404); exit; }

final class Validation {
    public static function errors(array $data, array $rules): array {
        $errors = [];
        foreach ($rules as $field => $rule) {
            $parts = explode('|', $rule); $value = $data[$field] ?? null;
            $empty = $value === null || (is_string($value) && trim($value) === '') || $value === [];
            if ($empty) { if (in_array('required', $parts, true)) $errors[$field] = "Field $field is required"; continue; }
            if (!is_scalar($value)) { $errors[$field] = 'Invalid value'; continue; }
            $numeric = in_array('numeric', $parts, true) || in_array('integer', $parts, true);
            if ($numeric && (!is_numeric($value) || !is_finite((float) $value))) { $errors[$field] = 'Field must be numeric'; continue; }
            if (in_array('integer', $parts, true) && filter_var($value, FILTER_VALIDATE_INT) === false) { $errors[$field] = 'Field must be an integer'; continue; }
            if (in_array('email', $parts, true) && !filter_var($value, FILTER_VALIDATE_EMAIL)) $errors[$field] = 'Invalid email format';
            foreach ($parts as $part) {
                if (preg_match('/^(min|max):(-?\d+(?:\.\d+)?)$/D', $part, $m)) {
                    $measure = $numeric ? (float) $value : mb_strlen((string) $value, 'UTF-8');
                    if (($m[1] === 'min' && $measure < (float) $m[2]) || ($m[1] === 'max' && $measure > (float) $m[2])) $errors[$field] = 'Field is outside the permitted range';
                }
            }
        }
        return $errors;
    }
}
