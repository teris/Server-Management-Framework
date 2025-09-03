-- =============================================================================
-- FRAMEWORK-SPEZIFISCHE POSTGRESQL-STRUKTUR FÜR STANDALONE-SYSTEM
-- =============================================================================
-- Erstellt: 2025-09-03
-- Version: 2.0
-- Beschreibung: Minimale PostgreSQL-Struktur für DatabaseOnlyFramework.php
-- Enthält nur die für das Framework notwendigen Tabellen
-- =============================================================================

-- =============================================================================
-- ENUM TYPES ERSTELLEN
-- =============================================================================

CREATE TYPE user_role AS ENUM ('admin', 'user', 'moderator');
CREATE TYPE active_status AS ENUM ('y', 'n');
CREATE TYPE logout_reason AS ENUM ('manual', 'timeout', 'forced');
CREATE TYPE success_status AS ENUM ('y', 'n');
CREATE TYPE activity_status AS ENUM ('success', 'error', 'pending', 'info');
CREATE TYPE setting_type AS ENUM ('string', 'integer', 'boolean', 'json');
CREATE TYPE public_status AS ENUM ('y', 'n');
CREATE TYPE mode_type AS ENUM ('live', 'database');

-- =============================================================================
-- FRAMEWORK-TABELLEN (minimal für DatabaseOnlyFramework.php)
-- =============================================================================

-- 1. LOGIN_ATTEMPTS TABLE
CREATE TABLE login_attempts (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    ip_address INET,
    user_agent TEXT,
    success success_status NOT NULL,
    failure_reason VARCHAR(255),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 2. ACTIVITY_LOG TABLE
CREATE TABLE activity_log (
    id SERIAL PRIMARY KEY,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    status activity_status NOT NULL DEFAULT 'info',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 3. SYSTEM_SETTINGS TABLE
CREATE TABLE system_settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    setting_type setting_type NOT NULL DEFAULT 'string',
    description TEXT,
    is_public public_status NOT NULL DEFAULT 'n',
    updated_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 4. SETTINGS TABLE
CREATE TABLE settings (
    id SERIAL PRIMARY KEY,
    site_title VARCHAR(255) NOT NULL,
    logo_path VARCHAR(255),
    favicon_path VARCHAR(255),
    mode mode_type NOT NULL DEFAULT 'live'
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

CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_system_settings_updated_at BEFORE UPDATE ON system_settings FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

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
