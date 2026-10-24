<?php
if (PHP_SAPI !== 'cli' && (get_included_files()[0] ?? null) === __FILE__) { http_response_code(404); exit; }

final class SchemaManager {
    public static function definition(): array { return json_decode(file_get_contents(dirname(__DIR__, 2) . '/database/schema.json'), true, 512, JSON_THROW_ON_ERROR); }
    public static function identifier(string $value, string $driver): string {
        if ($value === '' || str_contains($value, "\0")) throw new InvalidArgumentException('Invalid SQL identifier');
        $quote = $driver === 'mysql' ? '`' : '"';
        return $quote . str_replace($quote, $quote . $quote, $value) . $quote;
    }
    public static function column(string $definition, string $driver): string {
        if ($driver === 'sqlite') return $definition;
        $definition = str_replace('DATETIME', 'TIMESTAMP', $definition);
        if ($driver === 'mysql') return str_replace('INTEGER PRIMARY KEY AUTOINCREMENT', 'INTEGER PRIMARY KEY AUTO_INCREMENT', $definition);
        return str_replace('INTEGER PRIMARY KEY AUTOINCREMENT', 'SERIAL PRIMARY KEY', $definition);
    }
    public static function createTable(string $name, array $table, string $driver, bool $ifNotExists = true): string {
        $parts = [];
        foreach ($table['columns'] as $column => $definition) $parts[] = self::identifier($column, $driver) . ' ' . self::column($definition, $driver);
        foreach ($table['constraints'] as $constraint) {
            $constraint = preg_replace_callback('/REFERENCES (\w+)\(/', function ($m) use ($driver) { return 'REFERENCES ' . self::identifier($m[1], $driver) . '('; }, $constraint);
            $parts[] = $constraint;
        }
        return 'CREATE TABLE ' . ($ifNotExists ? 'IF NOT EXISTS ' : '') . self::identifier($name, $driver) . " (\n  " . implode(",\n  ", $parts) . "\n)" . ($driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '');
    }
    public static function tables(PDO $db): array {
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') return $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
        if ($driver === 'mysql') return $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        return $db->query("SELECT tablename FROM pg_tables WHERE schemaname=current_schema()")->fetchAll(PDO::FETCH_COLUMN);
    }
    public static function hasTable(PDO $db, string $table): bool { return in_array($table, self::tables($db), true); }
    public static function columns(PDO $db, string $table): array {
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME); $result = [];
        if ($driver === 'sqlite') {
            foreach ($db->query('PRAGMA table_info(' . self::identifier($table, $driver) . ')')->fetchAll(PDO::FETCH_ASSOC) as $row) $result[$row['name']] = $row;
        } else {
            $sql = 'SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_schema=' . ($driver === 'mysql' ? 'DATABASE()' : 'current_schema()') . ' AND table_name=?';
            $stmt = $db->prepare($sql); $stmt->execute([$table]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) { $row = array_change_key_case($row, CASE_LOWER); $result[$row['column_name']] = $row; }
        }
        return $result;
    }
    public static function sql(string $driver): string {
        $sql = "-- Generated from database/schema.json by bin/export-schema.php. No accounts or operator data.\n";
        foreach (self::definition()['tables'] as $name => $table) {
            $sql .= self::createTable($name, $table, $driver) . ";\n";
            foreach ($table['indexes'] as $index => $columns) $sql .= 'CREATE INDEX ' . self::identifier($index, $driver) . ' ON ' . self::identifier($name, $driver) . ' (' . implode(', ', $columns) . ");\n";
        }
        return $sql;
    }
    public static function migrate(PDO $db): array {
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!in_array($driver, ['sqlite', 'mysql', 'pgsql'], true)) throw new RuntimeException('Unsupported schema driver');
        $schema = self::definition(); $version = $schema['version']; $checksum = hash('sha256', json_encode($schema));
        $db->exec(self::createTable('schema_migrations', $schema['tables']['schema_migrations'], $driver));
        $stmt = $db->prepare('SELECT checksum FROM schema_migrations WHERE version=?'); $stmt->execute([$version]); $stored = $stmt->fetchColumn(); $stmt->closeCursor();
        if ($stored) { if (!hash_equals($stored, $checksum)) throw new RuntimeException('Applied migration checksum changed'); return ['version' => $version, 'changed' => false]; }
        $tables = self::tables($db);
        $legacyAlter = null;
        if ($driver === 'sqlite') {
            $legacyAlter = (int) $db->query('PRAGMA legacy_alter_table')->fetchColumn();
            $db->exec('PRAGMA foreign_keys=OFF');
            $db->exec('PRAGMA legacy_alter_table=ON');
        }
        if ($driver !== 'mysql') $db->beginTransaction();
        $savedViews = []; $viewGrants = []; $enumTypes = [];
        try {
            if ($driver === 'pgsql') {
                $enumTypes = $db->query("SELECT typname FROM pg_type WHERE typtype='e' AND typnamespace=current_schema()::regnamespace")->fetchAll(PDO::FETCH_COLUMN);
                $savedViews = $db->query("SELECT viewname,definition,viewowner FROM pg_views WHERE schemaname=current_schema() AND viewname IN ('user_activity_overview','vm_overview','website_overview')")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($savedViews as $view) {
                    $triggers = $db->prepare("SELECT COUNT(*) FROM pg_trigger WHERE tgrelid=?::regclass AND NOT tgisinternal"); $triggers->execute([$view['viewname']]);
                    if ($triggers->fetchColumn()) throw new RuntimeException('Custom triggers on a legacy report view require manual schema review');
                    $grants = $db->prepare('SELECT grantee,privilege_type,is_grantable FROM information_schema.role_table_grants WHERE table_schema=current_schema() AND table_name=?'); $grants->execute([$view['viewname']]); $viewGrants[$view['viewname']] = $grants->fetchAll(PDO::FETCH_ASSOC);
                    $db->exec('DROP VIEW ' . self::identifier($view['viewname'], $driver));
                }
            }
            foreach ($schema['tables'] as $name => $table) {
                if (!in_array($name, $tables, true)) { $db->exec(self::createTable($name, $table, $driver)); continue; }
                $columns = self::columns($db, $name);
                if ($driver === 'sqlite' && !in_array($name, ['schema_migrations', 'security_migrations'], true)) {
                    // Rebuild in one transaction to make defaults/checks agree while retaining extra fields and custom objects.
                    foreach ($db->query('PRAGMA table_xinfo(' . self::identifier($name, $driver) . ')')->fetchAll(PDO::FETCH_ASSOC) as $field) if ($field['hidden']) throw new RuntimeException('Generated/hidden legacy fields require manual schema review');
                    $custom = $db->prepare("SELECT sql FROM sqlite_master WHERE tbl_name=? AND type IN ('index','trigger') AND sql IS NOT NULL");
                    $custom->execute([$name]); $objects = $custom->fetchAll(PDO::FETCH_COLUMN); $custom->closeCursor();
                    // Inline UNIQUE constraints have no sqlite_master SQL entry.
                    foreach ($db->query('PRAGMA index_list(' . self::identifier($name, $driver) . ')')->fetchAll(PDO::FETCH_ASSOC) as $index) {
                        if ($index['origin'] !== 'u') continue;
                        $parts = [];
                        foreach ($db->query('PRAGMA index_xinfo(' . self::identifier($index['name'], $driver) . ')')->fetchAll(PDO::FETCH_ASSOC) as $part) if ($part['key']) $parts[] = self::identifier($part['name'], $driver) . ' COLLATE ' . self::identifier($part['coll'], $driver) . ($part['desc'] ? ' DESC' : '');
                        $objects[] = 'CREATE UNIQUE INDEX ' . self::identifier('smf_legacy_unique_' . substr(hash('sha256', $name . implode(',', $parts)), 0, 24), $driver) . ' ON ' . self::identifier($name, $driver) . ' (' . implode(',', $parts) . ')';
                    }
                    $sequence = 0;
                    if (self::hasTable($db, 'sqlite_sequence') || $db->query("SELECT name FROM sqlite_master WHERE name='sqlite_sequence'")->fetchColumn()) {
                        $s = $db->prepare('SELECT seq FROM sqlite_sequence WHERE name=?'); $s->execute([$name]); $sequence = (int) $s->fetchColumn(); $s->closeCursor();
                    }
                    foreach ($columns as $column => $row) if (!isset($table['columns'][$column])) $table['columns'][$column] = ($row['type'] ?: 'TEXT') . ($row['notnull'] ? ' NOT NULL' : '') . ($row['dflt_value'] !== null ? ' DEFAULT ' . $row['dflt_value'] : '');
                    $temporary = 'smf_migrate_' . $name;
                    $db->exec(self::createTable($temporary, $table, $driver, false));
                    $names = implode(', ', array_map(fn($column) => self::identifier($column, $driver), array_keys($columns)));
                    $db->exec('INSERT INTO ' . self::identifier($temporary, $driver) . ' (' . $names . ') SELECT ' . $names . ' FROM ' . self::identifier($name, $driver));
                    $db->exec('DROP TABLE ' . self::identifier($name, $driver));
                    $db->exec('ALTER TABLE ' . self::identifier($temporary, $driver) . ' RENAME TO ' . self::identifier($name, $driver));
                    foreach ($objects as $object) $db->exec($object);
                    if ($sequence) { $s = $db->prepare('UPDATE sqlite_sequence SET seq=MAX(seq, ?) WHERE name=?'); $s->execute([$sequence, $name]); }
                } elseif ($driver !== 'sqlite') {
                    foreach ($table['columns'] as $column => $definition) {
                        $quoted = self::identifier($name, $driver); $field = self::identifier($column, $driver);
                        if (!isset($columns[$column])) {
                            if (str_contains($definition, 'NOT NULL') && !str_contains($definition, 'DEFAULT') && !str_contains($definition, 'PRIMARY KEY')) {
                                if ((int) $db->query('SELECT COUNT(*) FROM ' . $quoted)->fetchColumn() > 0) throw new RuntimeException('Missing required legacy field: ' . $name . '.' . $column);
                            }
                            $db->exec('ALTER TABLE ' . $quoted . ' ADD COLUMN ' . $field . ' ' . self::column($definition, $driver));
                        } elseif ($driver === 'pgsql' && $columns[$column]['data_type'] === 'USER-DEFINED' && preg_match('/^VARCHAR\((\d+)\)/', $definition, $length)) {
                            $db->exec('ALTER TABLE ' . $quoted . ' ALTER COLUMN ' . $field . ' DROP DEFAULT');
                            $maximum = max((int) $length[1], (int) $db->query('SELECT MAX(LENGTH(' . $field . '::text)) FROM ' . $quoted)->fetchColumn());
                            $db->exec('ALTER TABLE ' . $quoted . ' ALTER COLUMN ' . $field . ' TYPE VARCHAR(' . $maximum . ') USING (' . $field . '::text)');
                            if (preg_match('/DEFAULT (\'[^\']*\'|\d+)/', $definition, $default)) $db->exec('ALTER TABLE ' . $quoted . ' ALTER COLUMN ' . $field . ' SET DEFAULT ' . $default[1]);
                        } elseif ($driver === 'pgsql' && $columns[$column]['data_type'] === 'boolean') {
                            $db->exec('ALTER TABLE ' . $quoted . ' ALTER COLUMN ' . $field . ' DROP DEFAULT');
                            $db->exec('ALTER TABLE ' . $quoted . ' ALTER COLUMN ' . $field . ' TYPE INTEGER USING (' . $field . '::int)');
                            if (preg_match('/DEFAULT (\d+)/', $definition, $default)) $db->exec('ALTER TABLE ' . $quoted . ' ALTER COLUMN ' . $field . ' SET DEFAULT ' . $default[1]);
                        }
                    }
                    if ($driver === 'pgsql' && in_array($name, ['users','customers'], true)) {
                        $checks = $db->prepare("SELECT conname,pg_get_constraintdef(oid) AS definition FROM pg_constraint WHERE conrelid=?::regclass AND contype='c'"); $checks->execute([$name]);
                        foreach ($checks->fetchAll(PDO::FETCH_ASSOC) as $check) if (preg_match('/\b(role|status)\b/', $check['definition'])) $db->exec('ALTER TABLE ' . self::identifier($name, $driver) . ' DROP CONSTRAINT ' . self::identifier($check['conname'], $driver));
                    }
                    if ($name === 'domain_registrations') $db->exec($driver === 'mysql' ? 'ALTER TABLE domain_registrations MODIFY user_id INTEGER NULL' : 'ALTER TABLE domain_registrations ALTER COLUMN user_id DROP NOT NULL');
                    if ($driver === 'mysql' && $name === 'users') $db->exec("ALTER TABLE users MODIFY role VARCHAR(24) NOT NULL DEFAULT 'user'");
                    if ($driver === 'mysql' && $name === 'customers') $db->exec("ALTER TABLE customers MODIFY status VARCHAR(24) NOT NULL DEFAULT 'pending'");
                }
            }
            foreach ($schema['tables'] as $name => $table) foreach ($table['indexes'] as $index => $columns) {
                $exists = false;
                if ($driver === 'mysql') { $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name=? AND index_name=?'); $stmt->execute([$name, $index]); $exists = (bool) $stmt->fetchColumn(); }
                if (!$exists) $db->exec('CREATE INDEX ' . ($driver === 'mysql' ? '' : 'IF NOT EXISTS ') . self::identifier($index, $driver) . ' ON ' . self::identifier($name, $driver) . ' (' . implode(', ', $columns) . ')');
            }
            foreach ($savedViews as $view) {
                $name = self::identifier($view['viewname'], $driver);
                $definition = $view['definition'];
                // PostgreSQL expands implicit enum literals when it stores a view.
                // The migrated columns are text; keep those comparisons as text too.
                foreach ($enumTypes as $type) $definition = preg_replace('/::' . preg_quote($type, '/') . '\\b/', '::text', $definition);
                $db->exec('CREATE VIEW ' . $name . ' AS ' . $definition);
                foreach ($viewGrants[$view['viewname']] as $grant) {
                    if (!in_array($grant['privilege_type'], ['SELECT','INSERT','UPDATE','DELETE','TRUNCATE','REFERENCES','TRIGGER'], true)) throw new RuntimeException('Unsupported view privilege');
                    $role = $grant['grantee'] === 'PUBLIC' ? 'PUBLIC' : self::identifier($grant['grantee'], $driver);
                    $db->exec('GRANT ' . $grant['privilege_type'] . ' ON ' . $name . ' TO ' . $role . ($grant['is_grantable'] === 'YES' ? ' WITH GRANT OPTION' : ''));
                }
                $db->exec('ALTER VIEW ' . $name . ' OWNER TO ' . self::identifier($view['viewowner'], $driver));
            }
            // Obsolete plaintext bearer links are deliberately invalidated; accounts/templates are retained.
            foreach (['verification_tokens', 'customer_verification_tokens', 'customer_remember_tokens'] as $legacy) if (self::hasTable($db, $legacy)) $db->exec('DELETE FROM ' . self::identifier($legacy, $driver));
            if ($driver === 'sqlite' && $db->query('PRAGMA foreign_key_check')->fetch()) throw new RuntimeException('Foreign-key inconsistency; migration rolled back');
            $stmt = $db->prepare('INSERT INTO schema_migrations (version, checksum, applied_at) VALUES (?, ?, ?)'); $stmt->execute([$version, $checksum, gmdate('c')]);
            if ($db->inTransaction()) $db->commit();
            return ['version' => $version, 'changed' => true];
        } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
        finally { if ($driver === 'sqlite') { $db->exec('PRAGMA foreign_keys=ON'); $db->exec('PRAGMA legacy_alter_table=' . $legacyAlter); } }
    }
}
