<?php
if (PHP_SAPI !== 'cli' && (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__ || (get_included_files()[0] ?? null) === __FILE__)) { http_response_code(404); exit; }

require_once __DIR__ . '/Identity.php';

final class ResourceAccess {
    // Legacy screens enumerate whole provider accounts. Scoped access uses execute(), below.
    const MODULES = ['admin', 'custom-module', 'database', 'dns', 'email', 'endpoints', 'file-editor', 'ispconfig', 'migration', 'network', 'ovh', 'proxmox', 'support-tickets', 'virtual-mac'];
    const TYPES = [
        'proxmox' => ['proxmox', ['qemu', 'lxc']], 'network' => ['proxmox', ['qemu', 'lxc']],
        'ispconfig' => ['ispconfig', ['website']], 'database' => ['ispconfig', ['database']], 'email' => ['ispconfig', ['mail']],
        'dns' => ['ovh', ['zone']], 'ovh' => ['ovh', ['domain', 'vps']], 'virtual-mac' => ['ovh', ['virtual-mac']]
    ];

    public static function legacyAllowed($module) {
        if (!in_array($module, self::MODULES, true) || $module === 'file-editor') return false;
        $config = getPluginConfig($module);
        if (!$config || empty($config['enabled'])) return false;
        $identity = Identity::current('backend');
        return $identity && $identity['role'] === 'admin';
    }

    public static function requireLegacyModule($module) {
        Identity::requireIdentity('backend', true);
        if (!self::legacyAllowed($module)) throw new SecurityViolation('Modul nicht freigegeben.', 403);
    }

    public static function connectionId($provider) {
        if(class_exists('SMF\\Core\\ProviderAccounts')){ $shared=\SMF\Core\ProviderAccounts::connectionId($provider);if($shared!==null)return $shared; }
        $fields = [
            'proxmox' => ['PROXMOX_HOST', 'PROXMOX_USER'], 'ispconfig' => ['ISPCONFIG_HOST', 'ISPCONFIG_USER'],
            'ovh' => ['OVH_ENDPOINT', 'OVH_APPLICATION_KEY', 'OVH_CONSUMER_KEY'], 'ogp' => ['OGP_HOST', 'OGP_USER']
        ];
        if (!isset($fields[$provider])) throw new SecurityViolation('Unbekannter Anbieter.', 403);
        $values = [$provider];
        foreach ($fields[$provider] as $field) $values[] = defined('Config::' . $field) ? constant('Config::' . $field) : '';
        return hash('sha256', json_encode($values));
    }

    public static function assignments(array $identity) {
        $stmt = HttpSecurity::connection()->prepare('SELECT * FROM security_resource_assignments WHERE subject_realm = ? AND subject_id = ? AND (expires_at IS NULL OR expires_at > ?)');
        $stmt->execute([$identity['realm'], $identity['id'], time()]);
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);$result=[];
        foreach($rows as$row)if(\SMF\Core\ShopResourceBindings::canonicalValid($row))$result[]=array_intersect_key($row,array_flip(['id','provider','resource_type','external_id','can_write']));
        return $result;
    }

    /** All identifiers used remotely come from this verified row, never from extra request fields. */
    public static function execute(array $identity, $module, $assignmentId, $action, callable $transport) {
        if (!isset(self::TYPES[$module]) || !preg_match('/^[a-f0-9]{32}$/D', (string) $assignmentId)) throw new SecurityViolation('Ressource nicht gefunden.', 404);
        $configuration = getPluginConfig($module);
        if (!$configuration || empty($configuration['enabled'])) throw new SecurityViolation('Modul nicht freigegeben.', 403);
        $stmt = HttpSecurity::connection()->prepare('SELECT * FROM security_resource_assignments WHERE id = ?');
        $stmt->execute([$assignmentId]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $administrator = $identity['realm'] === 'backend' && $identity['role'] === 'admin';
        if (!$row || (!$administrator && ($row['subject_realm'] !== $identity['realm'] || (int) $row['subject_id'] !== $identity['id']))
            || ($row['expires_at'] !== null && $row['expires_at'] <= time())) throw new SecurityViolation('Ressource nicht gefunden.', 404);
        [$provider, $types] = self::TYPES[$module];
        if ($row['provider'] !== $provider || !in_array($row['resource_type'], $types, true)
            || !hash_equals($row['connection_id'], self::connectionId($provider))) throw new SecurityViolation('Ressourcenzuordnung muss geprüft werden.', 403);
        $write = in_array($action, ['start', 'stop', 'reboot'], true);
        if (!in_array($action, ['view', 'start', 'stop', 'reboot'], true) || ($write && ($module !== 'proxmox' || $identity['role'] === 'readonly' || (!$administrator && !$row['can_write']))))
            throw new SecurityViolation('Aktion nicht freigegeben.', 403);
        if(!\SMF\Core\ShopResourceBindings::canonicalValid($row,$write))throw new SecurityViolation('Ressourcenzuordnung muss geprüft werden.',403);
        $metadata = json_decode($row['metadata'], true);
        if (!is_array($metadata)) throw new SecurityViolation('Ungültige Ressourcenzuordnung.', 403);
        $id = $row['external_id']; $type = $row['resource_type']; $method = 'GET'; $data = null;
        if ($provider === 'proxmox') {
            if (!ctype_digit($id) || (int) $id < 100 || !preg_match('/^[a-zA-Z0-9_.-]+$/D', $metadata['node'] ?? '')) throw new SecurityViolation('Ungültige VM-Zuordnung.', 403);
            $instance = $metadata['instance_id'] ?? '';
            if (!is_string($instance) || !preg_match($type === 'qemu' ? '/^[a-f0-9-]{36}$/Di' : '/^[a-f0-9]{32}$/D', $instance)) throw new SecurityViolation('VM-Identität muss zuerst zugeordnet werden.', 403);
            $configuration = $transport($provider, 'GET', '/nodes/' . rawurlencode($metadata['node']) . '/' . $type . '/' . (int) $id . '/config', null);
            if ($configuration === false || (is_array($configuration) && isset($configuration['success']) && !$configuration['success'])) throw new RuntimeException('Provider identity check unavailable');
            $configuration = is_array($configuration) ? ($configuration['data'] ?? $configuration) : [];
            if ($type === 'qemu') $actual = $configuration['vmgenid'] ?? '';
            else { if(isset($metadata['ownership_marker'])){$description=rtrim((string)($configuration['description']??''),"\r\n");$actual=hash_equals($metadata['ownership_marker'],$description)?substr(hash('sha256',$description),0,32):'';}else{preg_match('/(?:^|\n)SMF-RESOURCE:([a-f0-9]{32})(?:\n|$)/D', $configuration['description'] ?? '', $marker); $actual = $marker[1] ?? '';} }
            if (!is_string($actual) || !hash_equals(strtolower($instance), strtolower($actual))) throw new SecurityViolation('Die VM-Identität hat sich geändert. Bitte die Zuordnung prüfen.', 403);
            $path = '/nodes/' . rawurlencode($metadata['node']) . '/' . $type . '/' . (int) $id . '/status/' . ($write ? $action : 'current');
            if ($write) $method = 'POST';
        } elseif ($provider === 'ispconfig') {
            if (!ctype_digit($id) || (int) $id < 1) throw new SecurityViolation('Ungültige Hosting-Zuordnung.', 403);
            $path = ['website' => 'sites_web_domain_get', 'database' => 'sites_database_get', 'mail' => 'mail_user_get'][$type];
            $data = (int) $id;
        } else {
            if ($type === 'virtual-mac') {
                if (!preg_match('/^[a-fA-F0-9:]{17}$/D', $id) || !preg_match('/^[a-zA-Z0-9_.-]+$/D', $metadata['service'] ?? '')) throw new SecurityViolation('Ungültige MAC-Zuordnung.', 403);
                $path = '/dedicated/server/' . rawurlencode($metadata['service']) . '/virtualMac/' . rawurlencode($id);
            } else {
                if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9.-]{0,252}$/D', $id)) throw new SecurityViolation('Ungültige Domain-/Serverzuordnung.', 403);
                $path = ($type === 'zone' ? '/domain/zone/' : ($type === 'domain' ? '/domain/' : '/vps/')) . rawurlencode($id);
            }
        }
        $result = $transport($provider, $method, $path, $data);
        if ($result === false || (is_array($result) && isset($result['success']) && !$result['success'])) throw new RuntimeException('Provider request failed');
        if ($write) HttpSecurity::audit('resource.' . $action . '.accepted', $identity['realm'], $identity['id']);
        if ($write) return ['accepted' => true, 'task' => is_string($result) ? $result : (is_string($result['data'] ?? null) ? $result['data'] : null)];
        if (is_array($result) && isset($result['data']) && is_array($result['data'])) $result = $result['data'];
        if (!is_array($result)) throw new RuntimeException('Unexpected provider response');
        if ($provider === 'ispconfig' && ((int) ($metadata['group_id'] ?? 0) < 1 || (int) ($metadata['group_id'] ?? 0) !== (int) ($result['sys_groupid'] ?? 0)))
            throw new SecurityViolation('Der Eigentümer beim Hosting-Anbieter hat sich geändert.', 403);
        // Configs may contain passwords, hashes or unrelated account details. Publish a small field set.
        $keys = $provider === 'proxmox' ? ['status', 'name', 'cpu', 'mem', 'maxmem', 'uptime', 'vmid']
            : ($provider === 'ispconfig' ? ['domain_id', 'domain', 'database_id', 'database_name', 'mailuser_id', 'email', 'active', 'hd_quota', 'quota']
                : ['name', 'domain', 'state', 'status', 'dnssecSupported', 'macAddress', 'type']);
        return array_intersect_key($result, array_flip($keys));
    }
}
