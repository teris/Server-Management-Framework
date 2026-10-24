<?php
if (PHP_SAPI !== 'cli' && (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__ || (get_included_files()[0] ?? null) === __FILE__)) { http_response_code(404); exit; }

/** Narrow AP05/AP09 migration; the complete schema reconciliation belongs to AP11. */
final class SecuritySchema {
    public static function migrate(PDO $db) {
        $db->exec("CREATE TABLE IF NOT EXISTS security_migrations (version VARCHAR(80) PRIMARY KEY, applied_at VARCHAR(32) NOT NULL)");
        $version = '20261024_security_01';
        $stmt = $db->prepare('SELECT version FROM security_migrations WHERE version = ?'); $stmt->execute([$version]);
        if ($stmt->fetchColumn()) return false;
        $db->exec("CREATE TABLE IF NOT EXISTS security_remember_tokens (
            selector VARCHAR(24) PRIMARY KEY, customer_id INTEGER NOT NULL,
            token_hash VARCHAR(64) NOT NULL, credential VARCHAR(64) NOT NULL, expires_at BIGINT NOT NULL,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS security_resource_assignments (
            id VARCHAR(32) PRIMARY KEY, subject_realm VARCHAR(16) NOT NULL, subject_id INTEGER NOT NULL,
            provider VARCHAR(32) NOT NULL, connection_id VARCHAR(64) NOT NULL, resource_type VARCHAR(32) NOT NULL,
            external_id VARCHAR(255) NOT NULL, metadata TEXT NOT NULL, can_write INTEGER NOT NULL DEFAULT 0,
            expires_at BIGINT DEFAULT NULL,
            UNIQUE (subject_realm, subject_id, provider, connection_id, resource_type, external_id)
        )");
        // Old bearer tokens intentionally expire on upgrade; passwords and accounts remain untouched.
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $exists = $driver === 'sqlite'
            ? $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='customer_remember_tokens'")->fetchColumn()
            : ($driver === 'pgsql' ? $db->query("SELECT to_regclass('public.customer_remember_tokens')")->fetchColumn()
                : $db->query("SHOW TABLES LIKE 'customer_remember_tokens'")->fetchColumn());
        if ($exists) $db->exec('DELETE FROM customer_remember_tokens');
        $stmt = $db->prepare('INSERT INTO security_migrations (version, applied_at) VALUES (?, ?)');
        $stmt->execute([$version, gmdate('c')]);
        return true;
    }
}
