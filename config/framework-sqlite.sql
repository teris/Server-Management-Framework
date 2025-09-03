-- =============================================================================
-- FRAMEWORK-SPEZIFISCHE SQLITE-STRUKTUR FÜR STANDALONE-SYSTEM
-- =============================================================================
-- Erstellt: 2025-09-03
-- Version: 2.0
-- Beschreibung: Minimale SQLite-Struktur für DatabaseOnlyFramework.php
-- Enthält nur die für das Framework notwendigen Tabellen
-- =============================================================================

-- =============================================================================
-- FRAMEWORK-TABELLEN (minimal für DatabaseOnlyFramework.php)
-- =============================================================================

-- 1. LOGIN_ATTEMPTS TABLE
CREATE TABLE login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    success VARCHAR(1) NOT NULL CHECK (success IN ('y', 'n')),
    failure_reason VARCHAR(255),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 2. ACTIVITY_LOG TABLE
CREATE TABLE activity_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'info' CHECK (status IN ('success', 'error', 'pending', 'info')),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 3. SYSTEM_SETTINGS TABLE
CREATE TABLE system_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    setting_type VARCHAR(20) NOT NULL DEFAULT 'string' CHECK (setting_type IN ('string', 'integer', 'boolean', 'json')),
    description TEXT,
    is_public VARCHAR(1) NOT NULL DEFAULT 'n' CHECK (is_public IN ('y', 'n')),
    updated_by INTEGER,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 4. SETTINGS TABLE
CREATE TABLE settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    site_title VARCHAR(255) NOT NULL,
    logo_path VARCHAR(255),
    favicon_path VARCHAR(255),
    mode VARCHAR(20) NOT NULL DEFAULT 'live' CHECK (mode IN ('live', 'database'))
);

-- =============================================================================
-- INDIZES ERSTELLEN
-- =============================================================================

CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_active ON users(active);
CREATE INDEX idx_users_last_login ON users(last_login);
CREATE INDEX idx_user_sessions_user_id ON user_sessions(user_id);
CREATE INDEX idx_user_sessions_is_active ON user_sessions(is_active);
CREATE INDEX idx_user_sessions_expires_at ON user_sessions(expires_at);
CREATE INDEX idx_login_attempts_username ON login_attempts(username);
CREATE INDEX idx_login_attempts_ip_address ON login_attempts(ip_address);
CREATE INDEX idx_login_attempts_success ON login_attempts(success);
CREATE INDEX idx_login_attempts_created_at ON login_attempts(created_at);
CREATE INDEX idx_activity_log_action ON activity_log(action);
CREATE INDEX idx_activity_log_status ON activity_log(status);
CREATE INDEX idx_activity_log_created_at ON activity_log(created_at);
CREATE INDEX idx_system_settings_setting_key ON system_settings(setting_key);

-- =============================================================================
-- TRIGGER FÜR UPDATED_AT FELDER
-- =============================================================================

CREATE TRIGGER update_users_updated_at 
    AFTER UPDATE ON users 
    FOR EACH ROW 
    BEGIN 
        UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; 
    END;

CREATE TRIGGER update_system_settings_updated_at 
    AFTER UPDATE ON system_settings 
    FOR EACH ROW 
    BEGIN 
        UPDATE system_settings SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; 
    END;

-- =============================================================================
-- GRUNDDATEN FÜR FRAMEWORK (Standard-Einstellungen)
-- =============================================================================

-- Standard-Systemeinstellungen
INSERT INTO system_settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('site_name', 'Server Management Framework', 'string', 'Name der Website', 'y'),
('site_url', 'https://framework.example.com', 'string', 'URL der Website', 'y'),
('support_email', 'support@framework.example.com', 'string', 'Support-E-Mail-Adresse', 'y'),
('system_email', 'system@framework.example.com', 'string', 'System-E-Mail-Adresse', 'n'),
('admin_email', 'admin@framework.example.com', 'string', 'Admin-E-Mail-Adresse', 'n'),
('max_login_attempts', '5', 'integer', 'Maximale Login-Versuche', 'n'),
('session_timeout', '3600', 'integer', 'Session-Timeout in Sekunden', 'n'),
('password_min_length', '8', 'integer', 'Mindestlänge für Passwörter', 'n'),
('enable_registration', 'true', 'boolean', 'Registrierung aktiviert', 'y'),
('maintenance_mode', 'false', 'boolean', 'Wartungsmodus aktiviert', 'y');

-- Standard-Einstellungen
INSERT INTO settings (site_title, mode) VALUES
('Server Management Framework', 'live');
