<?php
if (PHP_SAPI !== 'cli' && (get_included_files()[0] ?? null) === __FILE__) { http_response_code(404); exit; }
require_once __DIR__ . '/ProviderResult.php';
final class ProviderUserActions {
    public function __construct(private $manager) { Identity::requireIdentity('backend', true); }
    public function execute(string $provider, string $action, array $data): array {
        if (!in_array($provider, ['proxmox','ispconfig','ogp'], true) || !in_array($action, ['list','create','edit','delete','password'], true)) throw new InvalidArgumentException('Ungültige Provideraktion');
        if ($provider === 'ogp' && in_array($action, ['edit','password'], true)) return ['success'=>false,'error'=>'UNSUPPORTED_OPERATION','message'=>'Diese OGP-Aktion wird nicht unterstützt. Bitte das OGP-Portal verwenden.'];
        $id = (string) ($data['external_id'] ?? '');
        if (!in_array($action, ['list','create'], true)) {
            $valid = $provider === 'proxmox' ? preg_match('/^[a-zA-Z0-9_.-]+@pve$/D', $id) : ($provider === 'ispconfig' ? ctype_digit($id) && (int) $id > 0 : filter_var($id, FILTER_VALIDATE_EMAIL));
            if (!$valid) throw new InvalidArgumentException('Provider-ID oder Benutzerbereich ungültig');
        }
        if (in_array($action, ['create','password'], true) && (!is_string($data['password'] ?? null) || strlen($data['password']) < 12 || strlen($data['password']) > 4096)) throw new InvalidArgumentException('Passwort benötigt zwölf bis 4096 Zeichen');
        if (in_array($action, ['create','edit'], true) && !filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Ungültige E-Mail-Adresse');
        $prefix = ['proxmox'=>'Proxmox','ispconfig'=>'ISPConfig','ogp'=>'OGP'][$provider];
        try {
            if ($action === 'list') { $method = 'get' . $prefix . 'Users'; $raw = $this->manager->$method(); }
            elseif ($action === 'delete') { $method = 'delete' . $prefix . 'User'; $raw = $this->manager->$method($id); }
            elseif ($action === 'password') { $method = 'update' . $prefix . 'UserPassword'; $raw = $this->manager->$method($id, $data['password']); }
            elseif ($action === 'edit') { $method = 'edit' . $prefix . 'User'; $raw = $this->manager->$method($id, $data['email'], (string) ($data['name'] ?? '')); }
            else {
                $username = (string) ($data['username'] ?? '');
                if (!preg_match('/^[a-zA-Z0-9_.-]{1,64}$/D', $username)) throw new InvalidArgumentException('Ungültiger Benutzername');
                $method = 'create' . $prefix . 'User';
                $raw = $this->manager->$method($username, $data['password'], (string) ($data['name'] ?? ''), '', ['email'=>$data['email'],'realm'=>'pve']);
            }
            $result = ProviderResult::normalize($raw, $provider, $action !== 'list');
            if ($action === 'list' && $result['success']) {
                $rows = is_array($raw) ? ($raw['data'] ?? $raw) : [];
                $allowed = array_flip(['userid','username','email','firstname','lastname','client_id','contact_name','enable','expire','name']);
                $result['data'] = array_values(array_map(fn($row) => is_array($row) ? array_intersect_key($row, $allowed) : [], is_array($rows) ? $rows : []));
            } elseif ($result['success']) $result['data'] = null;
            return $result;
        } catch (InvalidArgumentException $e) { throw $e; }
        catch (Throwable $e) { return ProviderResult::normalize(false, $provider, $action !== 'list'); }
    }
}
