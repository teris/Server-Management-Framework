<?php
if (PHP_SAPI !== 'cli' && (get_included_files()[0] ?? null) === __FILE__) { http_response_code(404); exit; }
require_once __DIR__ . '/Validation.php';
require_once __DIR__ . '/ProviderResult.php';
require_once __DIR__ . '/PasswordReset.php';

final class UserAdministration {
    public function __construct(private PDO $db, private $providers = null, private ?EmailTemplateManager $mail = null) { Identity::requireIdentity('backend', true); }
    private function table(string $realm): string { if (!in_array($realm, ['backend','customer'], true)) throw new InvalidArgumentException('Ungültiger Benutzerbereich'); return $realm === 'backend' ? 'users' : 'customers'; }
    public function listing(string $realm): array {
        $fields = $realm === 'backend' ? 'id,username,email,full_name,role,active,group_id,created_at' : 'id,first_name,last_name,email,company,phone,status,created_at';
        return $this->db->query('SELECT ' . $fields . ' FROM ' . $this->table($realm) . ' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    }
    public function save(string $realm, array $data): array {
        $table = $this->table($realm); $id = (int) ($data[$realm === 'backend' ? 'user_id' : 'customer_id'] ?? $data['id'] ?? 0);
        if ($id) { $stmt = $this->db->prepare('SELECT * FROM ' . $table . ' WHERE id=?'); $stmt->execute([$id]); $existing = $stmt->fetch(PDO::FETCH_ASSOC); $stmt->closeCursor(); if (!$existing) throw new InvalidArgumentException('Konto nicht gefunden'); $data = array_merge($existing, $data); }
        $data['email'] = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $rules = $realm === 'backend' ? ['username'=>'required|max:100','full_name'=>'max:255','email'=>'required|email|max:254'] : ['first_name'=>'required|max:100','last_name'=>'required|max:100','email'=>'required|email|max:254','phone'=>'max:50','company'=>'max:255'];
        $password = $data['password'] ?? '';
        if (Validation::errors($data, $rules) || !is_string($password) || (($id === 0 || $password !== '') && (strlen($password) < 12 || strlen($password) > 4096))) throw new InvalidArgumentException('Benutzerdaten prüfen; neue Passwörter benötigen mindestens zwölf Zeichen.');
        if ($realm === 'backend' && (!in_array($data['role'] ?? 'user', ['admin','user','moderator','readonly'], true) || !in_array($data['active'] ?? 'y', ['y','n'], true))) throw new InvalidArgumentException('Ungültige Rolle oder Aktivierung');
        if ($realm === 'customer' && !in_array($data['status'] ?? 'pending', ['pending','active','suspended','deleted'], true)) throw new InvalidArgumentException('Ungültiger Kundenstatus');
        $this->db->beginTransaction();
        try {
            if ($id) {
                $stmt = $this->db->prepare('SELECT * FROM ' . $table . ' WHERE id=?'); $stmt->execute([$id]); $old = $stmt->fetch(PDO::FETCH_ASSOC); $stmt->closeCursor();
                if (!$old) throw new InvalidArgumentException('Konto nicht gefunden');
                if ($realm === 'backend' && (($data['role'] ?? 'user') !== 'admin' || ($data['active'] ?? 'y') !== 'y')) $this->protectAdministrator($id);
            }
            $values = $realm === 'backend' ? ['username'=>$data['username'],'email'=>$data['email'],'full_name'=>$data['full_name'] ?? '', 'role'=>$data['role'] ?? 'user','active'=>$data['active'] ?? 'y','group_id'=>empty($data['group_id']) ? null : (int) $data['group_id']]
                : ['first_name'=>$data['first_name'],'last_name'=>$data['last_name'],'email'=>$data['email'],'company'=>$data['company'] ?? '', 'phone'=>$data['phone'] ?? '', 'status'=>$data['status'] ?? 'pending'];
            if ($password !== '') $values['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            if ($id) {
                $stmt = $this->db->prepare('UPDATE ' . $table . ' SET ' . implode(', ', array_map(fn($key) => $key . '=?', array_keys($values))) . ',updated_at=CURRENT_TIMESTAMP WHERE id=?');
                $stmt->execute([...array_values($values), $id]);
                if ($password !== '') {
                    $this->db->prepare('DELETE FROM account_tokens WHERE realm=? AND subject_id=?')->execute([$realm, $id]);
                    if ($realm === 'customer') $this->db->prepare('DELETE FROM security_remember_tokens WHERE customer_id=?')->execute([$id]);
                }
            } else {
                $stmt = $this->db->prepare('INSERT INTO ' . $table . ' (' . implode(',', array_keys($values)) . ') VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')'); $stmt->execute(array_values($values)); $id = (int) $this->db->lastInsertId();
            }
            $this->db->commit(); return ['success'=>true,'id'=>$id,'message'=>'Konto gespeichert. Es wurden keine Providerkonten automatisch angelegt.'];
        } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
    private function protectAdministrator(int $id): void {
        $stmt = $this->db->query("SELECT id FROM users WHERE role='admin' AND active='y'" . ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE'));
        $admins = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)); $stmt->closeCursor();
        if (in_array($id, $admins, true) && count($admins) <= 1) throw new InvalidArgumentException('Der letzte aktive Administrator muss erhalten bleiben.');
        if ((int) Identity::requireIdentity('backend', true)['id'] === $id) throw new InvalidArgumentException('Das eigene Administratorkonto kann hier nicht deaktiviert oder gelöscht werden.');
    }
    public function delete(string $realm, int $id, array $deleteProviders = []): array {
        $table = $this->table($realm); $results = [];
        if ($realm === 'backend') {
            $this->db->beginTransaction(); try { $this->protectAdministrator($id); $this->db->commit(); } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
            $stmt = $this->db->prepare('SELECT permission_type,resource_id FROM user_permissions WHERE user_id=?'); $stmt->execute([$id]); $links = $stmt->fetchAll(PDO::FETCH_ASSOC); $stmt->closeCursor();
            foreach ($links as $link) {
                $provider = $link['permission_type'];
                if (!in_array($provider, $deleteProviders, true)) continue;
                $method = ['proxmox'=>'deleteProxmoxUser','ispconfig'=>'deleteISPConfigUser','ogp'=>'deleteOGPUser'][$provider] ?? null;
                try { $result = $method && $this->providers ? $this->providers->$method($link['resource_id']) : ['success'=>false,'error'=>'UNSUPPORTED_OPERATION']; }
                catch (Throwable $e) { $result = false; }
                $results[$provider] = ProviderResult::normalize($result, $provider, true);
            }
            foreach ($results as $result) if (!$result['success']) return ['success'=>false,'providers'=>$results,'message'=>'Externe Löschung nicht vollständig bestätigt. Lokales Konto und Verknüpfungen bleiben zur Prüfung erhalten.'];
        }
        $this->db->beginTransaction();
        try {
            if ($realm === 'backend') $this->protectAdministrator($id);
            $this->db->prepare('DELETE FROM account_tokens WHERE realm=? AND subject_id=?')->execute([$realm,$id]);
            $this->db->prepare('DELETE FROM security_resource_assignments WHERE subject_realm=? AND subject_id=?')->execute([$realm,$id]);
            if ($realm === 'backend') $this->db->prepare('DELETE FROM user_permissions WHERE user_id=?')->execute([$id]);
            $stmt = $this->db->prepare('DELETE FROM ' . $table . ' WHERE id=?'); $stmt->execute([$id]); $deleted = $stmt->rowCount() === 1;
            $this->db->commit(); return ['success'=>$deleted,'providers'=>$results,'message'=>$deleted ? 'Lokales Konto gelöscht.' : 'Konto nicht gefunden.'];
        } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
    public function requestReset(string $realm, int $id): array {
        $stmt = $this->db->prepare('SELECT email FROM ' . $this->table($realm) . ' WHERE id=?'); $stmt->execute([$id]); $email = $stmt->fetchColumn();
        if (!$email) throw new InvalidArgumentException('Konto nicht gefunden');
        $sent = (new PasswordReset($this->db, $this->mail ?: new EmailTemplateManager($this->db)))->request($realm, $email);
        return ['success'=>$sent,'message'=>$sent ? 'Rücksetzlink angefordert.' : 'Rücksetzlink konnte nicht versendet werden.'];
    }
}
