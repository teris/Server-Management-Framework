<?php
if (PHP_SAPI !== 'cli' && (get_included_files()[0] ?? null) === __FILE__) { http_response_code(404); exit; }
require_once __DIR__ . '/AccountTokens.php';
require_once __DIR__ . '/EmailTemplateManager.php';

final class PasswordReset {
    public function __construct(private PDO $db, private EmailTemplateManager $mail) {}
    public function request(string $realm, string $email): bool {
        if (!in_array($realm, ['customer', 'backend'], true) || !filter_var($email, FILTER_VALIDATE_EMAIL)) return true;
        $table = $realm === 'customer' ? 'customers' : 'users'; $condition = $realm === 'customer' ? "status='active'" : "active='y'";
        $stmt = $this->db->prepare('SELECT * FROM ' . $table . ' WHERE LOWER(email)=? AND ' . $condition); $stmt->execute([mb_strtolower(trim($email))]); $row = $stmt->fetch(PDO::FETCH_ASSOC); $stmt->closeCursor();
        if (!$row) return true;
        $token = AccountTokens::issue($this->db, $realm, (int) $row['id'], 'reset_password', 1800);
        $sent = $this->mail->sendEmailFromTemplate('Passwort-zurücksetzen', $row['email'], ['resetLink' => Mailer::siteUrl() . '/public/reset-password.php?token=' . $token]);
        if (!$sent) $this->db->prepare('DELETE FROM account_tokens WHERE token_hash=?')->execute([hash('sha256', $token)]);
        return $sent;
    }
    public function complete(string $token, string $password): bool {
        if (strlen($password) < 12 || strlen($password) > 4096) return false;
        return AccountTokens::consume($this->db, $token, 'reset_password', function ($row, $account) use ($password) {
            if (($row['realm'] === 'customer' && $account['status'] !== 'active') || ($row['realm'] === 'backend' && $account['active'] !== 'y')) return false;
            $table = $row['realm'] === 'customer' ? 'customers' : 'users';
            $stmt = $this->db->prepare('UPDATE ' . $table . ' SET password_hash=?,updated_at=CURRENT_TIMESTAMP WHERE id=?'); $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $account['id']]);
            if ($row['realm'] === 'customer') $this->db->prepare('DELETE FROM security_remember_tokens WHERE customer_id=?')->execute([$account['id']]);
            return true;
        });
    }
}
