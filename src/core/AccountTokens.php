<?php
if (PHP_SAPI !== 'cli' && (get_included_files()[0] ?? null) === __FILE__) { http_response_code(404); exit; }

final class AccountTokens {
    public static function issue(PDO $db, string $realm, int $id, string $purpose, int $ttl): string {
        if (!in_array($realm, ['backend', 'customer'], true) || !in_array($purpose, ['verify_email', 'reset_password'], true)) throw new InvalidArgumentException('Invalid account token');
        $table = $realm === 'customer' ? 'customers' : 'users'; $token = bin2hex(random_bytes(32));
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT password_hash,email FROM ' . $table . ' WHERE id=?' . ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE'));
            $stmt->execute([$id]); $credentials = $stmt->fetch(PDO::FETCH_ASSOC); $credential = $credentials ? $credentials['password_hash'] . "\0" . $credentials['email'] : null; $stmt->closeCursor();
            if (!$credential) throw new RuntimeException('Account not found');
            $stmt = $db->prepare('DELETE FROM account_tokens WHERE realm=? AND subject_id=? AND purpose=?'); $stmt->execute([$realm, $id, $purpose]);
            $stmt = $db->prepare('INSERT INTO account_tokens (token_hash,realm,subject_id,purpose,expires_at,created_at,credential) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([hash('sha256', $token), $realm, $id, $purpose, time() + $ttl, time(), hash('sha256', $credential)]);
            $db->commit(); return $token;
        } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    }
    public static function consume(PDO $db, string $token, string $purpose, callable $apply): bool {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) return false;
        $db->beginTransaction();
        try {
            $hash = hash('sha256', $token); $stmt = $db->prepare('UPDATE account_tokens SET used_at=? WHERE token_hash=? AND purpose=? AND used_at IS NULL AND expires_at>?');
            $stmt->execute([time(), $hash, $purpose, time()]);
            if ($stmt->rowCount() !== 1) { $db->rollBack(); return false; }
            $stmt = $db->prepare('SELECT * FROM account_tokens WHERE token_hash=?'); $stmt->execute([$hash]); $row = $stmt->fetch(PDO::FETCH_ASSOC); $stmt->closeCursor();
            $table = $row['realm'] === 'customer' ? 'customers' : 'users';
            $stmt = $db->prepare('SELECT * FROM ' . $table . ' WHERE id=?' . ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE')); $stmt->execute([$row['subject_id']]); $account = $stmt->fetch(PDO::FETCH_ASSOC); $stmt->closeCursor();
            if (!$account || !hash_equals($row['credential'], hash('sha256', $account['password_hash'] . "\0" . $account['email'])) || !$apply($row, $account)) { $db->rollBack(); return false; }
            $db->commit(); return true;
        } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    }
}
