<?php
namespace SMF\Core;
use PDO;
use RuntimeException;
use InvalidArgumentException;
final class ModuleRegistry {
    private array $catalog = [];
    private array $state = [];
    public function __construct(private PDO $db, private string $directory, private string $coreVersion) {
        foreach (glob($directory . '/*/module.json') ?: [] as $file) {
            if (is_link($file) || is_link(dirname($file))) throw new RuntimeException('Module symlinks rejected');
            $manifest = json_decode(file_get_contents($file), true, 64, JSON_THROW_ON_ERROR);
            $id = $manifest['id'] ?? '';
            if (!preg_match('/^[a-z][a-z0-9]*(?:[.-][a-z0-9]+)*$/D', $id) || basename(dirname($file)) !== $id || isset($this->catalog[$id])) throw new InvalidArgumentException('Invalid module identity');
            if (!preg_match('/^\d+\.\d+\.\d+$/D', $manifest['version'] ?? '') || !is_array($manifest['requires'] ?? null)) throw new InvalidArgumentException('Invalid module contract');
            foreach (['routes','jobs','permissions','migrations'] as $key) if (!is_array($manifest[$key] ?? null)) throw new InvalidArgumentException('Incomplete module contract');
            foreach (['providers','products','ui'] as $kind) {
                $manifest[$kind] ??= [];
                if (!is_array($manifest[$kind])) throw new InvalidArgumentException('Invalid module contributions');
                foreach ($manifest[$kind] as $name => $definition) {
                    if (!is_string($name) || !preg_match('/^[a-z][a-z0-9_.-]{0,79}$/D', $name) || !is_array($definition)) throw new InvalidArgumentException('Invalid contribution identity');
                    if ($kind === 'providers') {
                        foreach (['capabilities','fields','resources'] as $key) if (!is_array($definition[$key] ?? null)) throw new InvalidArgumentException('Incomplete provider contract');
                        foreach ($definition['capabilities'] as $capability) if (!is_string($capability) || !preg_match('/^[a-z][a-z0-9_.-]{0,79}$/D', $capability)) throw new InvalidArgumentException('Invalid capability');
                    }
                    if ($kind !== 'ui' && (!is_string($definition['runtime'] ?? null) || !preg_match('~^[a-zA-Z0-9_-]+\.php$~D', $definition['runtime']))) throw new InvalidArgumentException('Invalid contribution runtime');
                    if ($kind === 'products' && !is_array($definition['requires_capabilities'] ?? null)) throw new InvalidArgumentException('Product capability contract required');
                }
            }
            $manifest['_directory'] = dirname($file); $manifest['_sha256'] = hash_file('sha256', $file);
            foreach($manifest['migration_inputs']??[] as $migration=>$inputs){
                if(!in_array($migration,$manifest['migrations'],true)||!is_array($inputs))throw new InvalidArgumentException('Invalid migration inputs');
                foreach($inputs as $input){$source=self::migrationInput($manifest['_directory'],$input);$manifest['_sha256']=hash('sha256',$manifest['_sha256']."\0".$input."\0".hash_file('sha256',$source));}
            }
            $this->catalog[$id] = $manifest;
        }
        if (\SchemaManager::hasTable($db, 'smf_module_state')) foreach ($db->query('SELECT module_id,installed_version,manifest_hash,enabled FROM smf_module_state')->fetchAll(PDO::FETCH_ASSOC) as $row) $this->state[$row['module_id']] = $row;
    }
    public function catalog(): array { return $this->catalog; }
    public function manifest(string $id): array { return $this->catalog[$id] ?? throw new InvalidArgumentException('Unknown module'); }
    public function installed(string $id): bool { return isset($this->state[$id]); }
    public function enabled(string $id): bool { return !empty($this->state[$id]['enabled']) && isset($this->catalog[$id]); }
    public function assertCompatible(string $id, array $chain = []): void {
        if (in_array($id, $chain, true)) throw new RuntimeException('Circular module dependency');
        $manifest = $this->manifest($id);
        if (!VersionConstraint::matches($this->coreVersion, $manifest['core']) || !VersionConstraint::matches(PHP_VERSION, $manifest['php'])) throw new RuntimeException('Incompatible module/core/PHP version');
        foreach ($manifest['requires'] as $dependency => $constraint) {
            $required = $this->manifest($dependency);
            if (!$this->enabled($dependency) || !VersionConstraint::matches($required['version'], $constraint)) throw new RuntimeException('Enable compatible dependency first: ' . $dependency);
            $this->assertCompatible($dependency, [...$chain, $id]);
        }
    }
    public function assertEnabled(string $id): void {
        if (!$this->enabled($id)) throw new \SecurityViolation('Modul ist nicht aktiviert.', 404);
        $this->assertCompatible($id);
        if ($this->state[$id]['installed_version'] !== $this->manifest($id)['version'] || !hash_equals($this->state[$id]['manifest_hash'],$this->manifest($id)['_sha256'])) throw new RuntimeException('Module migration required');
    }
    public function enable(string $id): void {
        if (!$this->installed($id)) throw new RuntimeException('Install module migrations before enabling');
        $this->assertCompatible($id);
        if($this->state[$id]['installed_version']!==$this->manifest($id)['version'] || !hash_equals($this->state[$id]['manifest_hash'],$this->manifest($id)['_sha256']))throw new RuntimeException('Install current manifest first');
        $this->db->prepare('UPDATE smf_module_state SET enabled=1 WHERE module_id=?')->execute([$id]); $this->state[$id]['enabled'] = 1;
    }
    public function disable(string $id): void {
        foreach ($this->catalog as $other => $manifest) if ($this->enabled($other) && isset($manifest['requires'][$id])) throw new RuntimeException('Disable dependent module first: ' . $other);
        $this->db->prepare('UPDATE smf_module_state SET enabled=0 WHERE module_id=?')->execute([$id]); $this->state[$id]['enabled'] = 0;
    }
    public function routes(): array { return $this->contributions('routes'); }
    public function jobs(): array { return $this->contributions('jobs'); }
    public function providers(): array { return $this->contributions('providers'); }
    public function products(): array { return $this->contributions('products'); }
    public function ui(): array { return $this->contributions('ui'); }
    /** Executable contributions come only from the administrator-installed module tree. */
    public function runtime(array $contribution): mixed {
        $module = $contribution['module']; $this->assertEnabled($module);
        $path = $contribution['definition']['runtime'] ?? '';
        return require $this->file($module,$path);
    }
    public function file(string $module,string $path): string {
        $this->assertEnabled($module);
        $directory=$this->manifest($module)['_directory'];
        if (!is_string($path) || !preg_match('~^[a-zA-Z0-9_-]+\.php$~D', $path)) throw new RuntimeException('Invalid runtime path');
        $file = $directory . '/' . $path;
        if (is_link($file) || !is_file($file) || !str_starts_with(realpath($file), realpath($directory) . '/')) throw new RuntimeException('Unsafe module runtime');
        return $file;
    }
    private function contributions(string $kind): array {
        $result = [];
        foreach ($this->catalog as $id => $manifest) if ($this->enabled($id)) {
            $this->assertEnabled($id);
            foreach ($manifest[$kind] as $name => $definition) {
                if (isset($result[$name])) throw new RuntimeException('Conflicting module contribution');
                $result[$name] = ['module'=>$id, 'definition'=>$definition];
            }
        }
        return $result;
    }
    public function install(string $id): void {
        $manifest = $this->manifest($id); $this->assertCompatible($id);
        foreach ($manifest['migrations'] as $path) {
            if (!preg_match('~^migrations/[a-zA-Z0-9_.-]+\.php$~D', $path)) throw new RuntimeException('Invalid migration path');
            $file = $manifest['_directory'] . '/' . $path;
            if (is_link($file) || !is_file($file) || !str_starts_with(realpath($file), realpath($manifest['_directory']) . '/')) throw new RuntimeException('Unsafe migration file');
            $hash = hash_file('sha256', $file);
            foreach($manifest['migration_inputs'][$path]??[] as $input)$hash=hash('sha256',$hash."\0".$input."\0".hash_file('sha256',self::migrationInput($manifest['_directory'],$input)));
            $query = $this->db->prepare('SELECT checksum FROM smf_module_migrations WHERE module_id=? AND migration=?'); $query->execute([$id,$path]); $old = $query->fetchColumn(); $query->closeCursor();
            if ($old) { if (!hash_equals($old, $hash)) throw new RuntimeException('Applied module migration changed'); continue; }
            $migration = require $file; if (!is_callable($migration)) throw new RuntimeException('Invalid module migration');
            $migration($this->db);
            $this->db->prepare('INSERT INTO smf_module_migrations (module_id,migration,checksum,applied_at) VALUES (?,?,?,?)')->execute([$id,$path,$hash,time()]);
        }
        if ($this->installed($id)) $this->db->prepare('UPDATE smf_module_state SET installed_version=?,manifest_hash=? WHERE module_id=?')->execute([$manifest['version'],$manifest['_sha256'],$id]);
        else $this->db->prepare('INSERT INTO smf_module_state (module_id,installed_version,manifest_hash,enabled) VALUES (?,?,?,0)')->execute([$id,$manifest['version'],$manifest['_sha256']]);
        $this->state[$id] = ['module_id'=>$id,'installed_version'=>$manifest['version'],'manifest_hash'=>$manifest['_sha256'],'enabled'=>$this->state[$id]['enabled'] ?? 0];
    }
    private static function migrationInput(string $directory,mixed $path): string {
        if(!is_string($path) || !preg_match('~^[a-zA-Z0-9_-]+\.json$~D',$path))throw new RuntimeException('Invalid schema input');
        $file=$directory.'/'.$path;if(is_link($file)||!is_file($file)||!str_starts_with(realpath($file),realpath($directory).'/'))throw new RuntimeException('Unsafe schema input');return $file;
    }
}
