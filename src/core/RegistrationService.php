<?php
if (PHP_SAPI !== 'cli' && (get_included_files()[0] ?? null) === __FILE__) { http_response_code(404); exit; }
require_once __DIR__ . '/Validation.php';
require_once __DIR__ . '/AccountTokens.php';
require_once __DIR__ . '/EmailTemplateManager.php';

final class RegistrationService {
    private PDO $db;
    private EmailTemplateManager $mail;
    public function __construct(?PDO $db = null, ?EmailTemplateManager $mail = null) { $this->db = $db ?: HttpSecurity::connection(); $this->mail = $mail ?: new EmailTemplateManager($this->db); }
    public function register(array $input): array {
        foreach (['first_name','last_name','email','password','confirm_password','company','phone'] as $field) if (isset($input[$field]) && !is_string($input[$field])) return ['success'=>false,'message'=>'Ungültige Eingaben.'];
        $input['email'] = mb_strtolower(trim((string) ($input['email'] ?? '')));
        foreach (['first_name', 'last_name', 'company', 'phone'] as $key) $input[$key] = trim((string) ($input[$key] ?? ''));
        $errors = Validation::errors($input, ['first_name' => 'required|max:100','last_name' => 'required|max:100','email' => 'required|email|max:254','password' => 'required|min:12|max:4096','company' => 'max:255','phone' => 'max:50']);
        if ($errors || ($input['password'] ?? '') !== ($input['confirm_password'] ?? '') || empty($input['accept_terms'])) return ['success' => false, 'message' => 'Bitte Pflichtfelder, Passwort (mindestens 12 Zeichen), Passwortbestätigung und Zustimmung prüfen.'];
        Mailer::siteUrl();
        $stmt = $this->db->prepare('SELECT id,status FROM customers WHERE LOWER(email)=?'); $stmt->execute([$input['email']]); $existing = $stmt->fetch(PDO::FETCH_ASSOC); $stmt->closeCursor();
        if ($existing) return ['success' => false, 'message' => 'Diese E-Mail-Adresse ist bereits registriert. Nutzen Sie Anmeldung oder erneuten Bestätigungsversand.'];
        $stmt = $this->db->prepare("INSERT INTO customers (first_name,last_name,email,password_hash,company,phone,status) VALUES (?,?,?,?,?,?,'pending')");
        $stmt->execute([$input['first_name'], $input['last_name'], $input['email'], password_hash($input['password'], PASSWORD_DEFAULT), $input['company'], $input['phone']]);
        $id = (int) $this->db->lastInsertId(); $sent = $this->sendVerification($id);
        return ['success' => true, 'sent' => $sent, 'message' => $sent ? 'Konto angelegt. Bitte bestätigen Sie Ihre E-Mail-Adresse.' : 'Konto angelegt. Die Bestätigung konnte nicht versendet werden. Bitte fordern Sie den Link erneut an.'];
    }
    public function sendVerification(int $id): bool {
        $stmt = $this->db->prepare("SELECT * FROM customers WHERE id=? AND status='pending'"); $stmt->execute([$id]); $customer = $stmt->fetch(PDO::FETCH_ASSOC); $stmt->closeCursor();
        if (!$customer) return false;
        $token = AccountTokens::issue($this->db, 'customer', $id, 'verify_email', 86400);
        $sent = $this->mail->sendEmailFromTemplate('Kunden-Verifikations-E-Mail', $customer['email'], ['firstName' => $customer['first_name'], 'email' => $customer['email'], 'verificationLink' => Mailer::siteUrl() . '/public/verify.php?token=' . $token]);
        if (!$sent) { $stmt = $this->db->prepare('DELETE FROM account_tokens WHERE token_hash=?'); $stmt->execute([hash('sha256', $token)]); }
        return $sent;
    }
    public function resend(string $email): bool {
        $stmt = $this->db->prepare("SELECT id FROM customers WHERE LOWER(email)=? AND status='pending'"); $stmt->execute([mb_strtolower(trim($email))]); $id = $stmt->fetchColumn(); $stmt->closeCursor();
        return $id ? $this->sendVerification((int) $id) : true;
    }
    public function verify(string $token): bool {
        return AccountTokens::consume($this->db, $token, 'verify_email', function ($row, $account) {
            if ($row['realm'] !== 'customer' || $account['status'] !== 'pending') return false;
            $stmt = $this->db->prepare("UPDATE customers SET status='active',email_verified_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='pending'"); $stmt->execute([$account['id']]);
            return $stmt->rowCount() === 1;
        });
    }
}
