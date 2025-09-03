-- =============================================================================
-- OPTIMIERTE DATENBANKSTRUKTUR FÜR POSTGRESQL
-- =============================================================================
-- Erstellt: 2025-09-03
-- Version: 2.0
-- Beschreibung: PostgreSQL-optimierte SQL-Struktur basierend auf Systemanalyse
-- =============================================================================

-- PostgreSQL spezifische Einstellungen
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

-- =============================================================================
-- DATENBANK ERSTELLEN
-- =============================================================================
CREATE DATABASE server_management
    WITH 
    OWNER = postgres
    ENCODING = 'UTF8'
    LC_COLLATE = 'de_DE.UTF-8'
    LC_CTYPE = 'de_DE.UTF-8'
    TABLESPACE = pg_default
    CONNECTION LIMIT = -1;

\c server_management;

-- =============================================================================
-- ENUMS ERSTELLEN
-- =============================================================================

-- Benutzerstatus
CREATE TYPE user_status AS ENUM ('y', 'n');
CREATE TYPE customer_status AS ENUM ('pending', 'active', 'suspended', 'deleted');
CREATE TYPE vm_status AS ENUM ('running', 'stopped', 'suspended');
CREATE TYPE ticket_priority AS ENUM ('low', 'medium', 'high', 'urgent');
CREATE TYPE ticket_status AS ENUM ('open', 'in_progress', 'waiting_customer', 'waiting_admin', 'resolved', 'closed');
CREATE TYPE ticket_category AS ENUM ('technical', 'billing', 'general', 'feature_request', 'bug_report', 'account');
CREATE TYPE ticket_department AS ENUM ('support', 'billing', 'technical', 'sales');
CREATE TYPE ticket_source AS ENUM ('web', 'email', 'phone', 'chat');
CREATE TYPE contact_status AS ENUM ('new', 'read', 'replied', 'archived');
CREATE TYPE domain_status AS ENUM ('pending', 'approved', 'rejected', 'cancelled');
CREATE TYPE ssl_status AS ENUM ('valid', 'expired', 'revoked', 'pending');
CREATE TYPE token_type AS ENUM ('email_verification', 'password_reset');
CREATE TYPE user_type AS ENUM ('customer', 'admin');
CREATE TYPE activity_status AS ENUM ('success', 'error', 'pending', 'info');
CREATE TYPE permission_type AS ENUM ('proxmox', 'ispconfig', 'ovh', 'ogp', 'admin', 'readonly');
CREATE TYPE logout_reason AS ENUM ('manual', 'timeout', 'forced');
CREATE TYPE setting_type AS ENUM ('string', 'integer', 'boolean', 'json');
CREATE TYPE database_type AS ENUM ('mysql', 'postgresql');
CREATE TYPE site_mode AS ENUM ('live', 'database');

-- =============================================================================
-- BENUTZER-TABELLEN
-- =============================================================================

-- Admin-Benutzer
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    role VARCHAR(255),
    active user_status DEFAULT 'y',
    last_login TIMESTAMP,
    failed_login_attempts INTEGER DEFAULT 0,
    locked_until TIMESTAMP,
    password_changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Kunden-Benutzer
CREATE TABLE customers (
    id SERIAL PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    full_name VARCHAR(255),
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    company VARCHAR(255),
    phone VARCHAR(50),
    address TEXT,
    city VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100),
    status customer_status DEFAULT 'pending',
    email_verified_at TIMESTAMP,
    last_login TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================================================
-- AUTHENTIFIZIERUNG UND SICHERHEIT
-- =============================================================================

-- Benutzerberechtigungen
CREATE TABLE user_permissions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    permission_type permission_type NOT NULL,
    resource_id VARCHAR(255),
    granted_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    UNIQUE(user_id, permission_type, resource_id)
);

-- Benutzersitzungen
CREATE TABLE user_sessions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    session_id VARCHAR(128) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_active user_status DEFAULT 'y',
    logout_reason logout_reason
);

-- Login-Versuche
CREATE TABLE login_attempts (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100),
    ip_address VARCHAR(45),
    user_agent TEXT,
    success user_status NOT NULL,
    failure_reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Kunden-Login-Logs
CREATE TABLE customer_login_logs (
    id SERIAL PRIMARY KEY,
    customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    success BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Verifizierungstoken
CREATE TABLE verification_tokens (
    id SERIAL PRIMARY KEY,
    customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    token VARCHAR(255) NOT NULL UNIQUE,
    type token_type DEFAULT 'email_verification',
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Kunden-Remember-Token
CREATE TABLE customer_remember_tokens (
    id SERIAL PRIMARY KEY,
    customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================================================
-- AKTIVITÄTS- UND LOGGING-TABELLEN
-- =============================================================================

-- Aktivitätsprotokoll
CREATE TABLE activity_log (
    id SERIAL PRIMARY KEY,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    status activity_status NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Benutzeraktivitäten
CREATE TABLE user_activities (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    user_type user_type DEFAULT 'customer',
    activity_type VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    related_id INTEGER,
    related_table VARCHAR(50),
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================================================
-- SUPPORT-SYSTEM
-- =============================================================================

-- Support-Tickets
CREATE TABLE support_tickets (
    id SERIAL PRIMARY KEY,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    ticket_number VARCHAR(20) NOT NULL DEFAULT 'TEMP' UNIQUE,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    email VARCHAR(255) NOT NULL DEFAULT '',
    customer_name VARCHAR(255),
    phone VARCHAR(50),
    priority ticket_priority DEFAULT 'medium',
    status ticket_status DEFAULT 'open',
    category ticket_category DEFAULT 'general',
    assigned_to INTEGER,
    department ticket_department DEFAULT 'support',
    source ticket_source DEFAULT 'web',
    ip_address VARCHAR(45),
    user_agent TEXT,
    estimated_resolution_time INTEGER, -- in hours
    actual_resolution_time INTEGER, -- in hours
    customer_satisfaction SMALLINT, -- 1-5 rating
    internal_notes TEXT,
    tags VARCHAR(500), -- comma-separated tags
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP,
    closed_at TIMESTAMP,
    due_date TIMESTAMP
);

-- Ticket-Antworten
CREATE TABLE ticket_replies (
    id SERIAL PRIMARY KEY,
    ticket_id INTEGER NOT NULL REFERENCES support_tickets(id) ON DELETE CASCADE,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    admin_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    message TEXT NOT NULL,
    is_internal BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Kontaktnachrichten
CREATE TABLE contact_messages (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    status contact_status DEFAULT 'new',
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================================================
-- DOMAIN-MANAGEMENT
-- =============================================================================

-- Domain-Registrierungen
CREATE TABLE domain_registrations (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    domain VARCHAR(255) NOT NULL,
    purpose VARCHAR(50) NOT NULL DEFAULT 'other',
    notes TEXT,
    status domain_status DEFAULT 'pending',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Domains
CREATE TABLE domains (
    id SERIAL PRIMARY KEY,
    domain_name VARCHAR(255) NOT NULL UNIQUE,
    registrar VARCHAR(100) DEFAULT 'OVH',
    registration_date DATE,
    expiration_date DATE,
    auto_renew user_status DEFAULT 'y',
    nameservers TEXT,
    status VARCHAR(20) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =============================================================================
-- INFRASTRUKTUR-TABELLEN
-- =============================================================================

-- Virtuelle Maschinen
CREATE TABLE vms (
    id SERIAL PRIMARY KEY,
    vm_id INTEGER NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    node VARCHAR(100) NOT NULL,
    status vm_status DEFAULT 'stopped',
    memory INTEGER NOT NULL,
    cores INTEGER NOT NULL,
    disk_size INTEGER NOT NULL,
    ip_address VARCHAR(45),
    mac_address VARCHAR(17),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Websites
CREATE TABLE websites (
    id SERIAL PRIMARY KEY,
    domain VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45) NOT NULL,
    system_user VARCHAR(100) NOT NULL,
    system_group VARCHAR(100) NOT NULL,
    document_root VARCHAR(500),
    hd_quota INTEGER DEFAULT 1000,
    traffic_quota INTEGER DEFAULT 10000,
    active user_status DEFAULT 'y',
    ssl_enabled user_status DEFAULT 'n',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Datenbanken
CREATE TABLE sm_databases (
    id SERIAL PRIMARY KEY,
    database_name VARCHAR(255) NOT NULL UNIQUE,
    database_user VARCHAR(255) NOT NULL,
    database_type database_type DEFAULT 'mysql',
    server_id INTEGER DEFAULT 1,
    charset VARCHAR(50) DEFAULT 'utf8',
    remote_access user_status DEFAULT 'n',
    active user_status DEFAULT 'y',
    website_id INTEGER REFERENCES websites(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- E-Mail-Konten
CREATE TABLE email_accounts (
    id SERIAL PRIMARY KEY,
    email_address VARCHAR(255) NOT NULL UNIQUE,
    login_name VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255),
    full_name VARCHAR(255),
    domain VARCHAR(255) NOT NULL,
    quota_mb INTEGER DEFAULT 1000,
    active user_status DEFAULT 'y',
    autoresponder user_status DEFAULT 'n',
    autoresponder_text TEXT,
    forward_to VARCHAR(255),
    spam_filter user_status DEFAULT 'y',
    website_id INTEGER REFERENCES websites(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- SSL-Zertifikate
CREATE TABLE ssl_certificates (
    id SERIAL PRIMARY KEY,
    domain VARCHAR(255) NOT NULL,
    certificate_path VARCHAR(500),
    private_key_path VARCHAR(500),
    certificate_authority VARCHAR(100),
    issue_date DATE,
    expiration_date DATE,
    auto_renew user_status DEFAULT 'y',
    status ssl_status DEFAULT 'pending',
    website_id INTEGER REFERENCES websites(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- IP-Adressen
CREATE TABLE ips (
    id SERIAL PRIMARY KEY,
    subnet VARCHAR(64) NOT NULL,
    ip_reverse VARCHAR(64) NOT NULL,
    reverse VARCHAR(255),
    ttl INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(subnet, ip_reverse)
);

-- =============================================================================
-- SYSTEM-KONFIGURATION
-- =============================================================================

-- API-Zugangsdaten
CREATE TABLE api_credentials (
    id SERIAL PRIMARY KEY,
    service_name VARCHAR(100) NOT NULL UNIQUE,
    endpoint VARCHAR(500),
    username VARCHAR(255),
    password_encrypted TEXT,
    api_key_encrypted TEXT,
    additional_config JSONB,
    active user_status DEFAULT 'y',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Module
CREATE TABLE modules (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE
);

-- Systemeinstellungen
CREATE TABLE system_settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type setting_type DEFAULT 'string',
    description TEXT,
    is_public user_status DEFAULT 'n',
    updated_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Einstellungen
CREATE TABLE settings (
    id SERIAL PRIMARY KEY,
    site_title VARCHAR(255) NOT NULL DEFAULT 'Meine Seite',
    logo_path VARCHAR(255),
    favicon_path VARCHAR(255),
    mode site_mode DEFAULT 'live'
);

-- =============================================================================
-- INDEXES ERSTELLEN
-- =============================================================================

-- Users indexes
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_active ON users(active);
CREATE INDEX idx_users_last_login ON users(last_login);

-- Customers indexes
CREATE INDEX idx_customers_status ON customers(status);
CREATE INDEX idx_customers_email_verified ON customers(email_verified_at);

-- User permissions indexes
CREATE INDEX idx_user_permissions_user_id ON user_permissions(user_id);
CREATE INDEX idx_user_permissions_type ON user_permissions(permission_type);
CREATE INDEX idx_user_permissions_granted_by ON user_permissions(granted_by);

-- User sessions indexes
CREATE INDEX idx_user_sessions_session_id ON user_sessions(session_id);
CREATE INDEX idx_user_sessions_user_id ON user_sessions(user_id);
CREATE INDEX idx_user_sessions_active ON user_sessions(is_active);
CREATE INDEX idx_user_sessions_expires ON user_sessions(expires_at);

-- Login attempts indexes
CREATE INDEX idx_login_attempts_username ON login_attempts(username);
CREATE INDEX idx_login_attempts_ip ON login_attempts(ip_address);
CREATE INDEX idx_login_attempts_success ON login_attempts(success);
CREATE INDEX idx_login_attempts_created ON login_attempts(created_at);

-- Customer login logs indexes
CREATE INDEX idx_customer_login_logs_customer_id ON customer_login_logs(customer_id);
CREATE INDEX idx_customer_login_logs_created ON customer_login_logs(created_at);

-- Verification tokens indexes
CREATE INDEX idx_verification_tokens_customer_id ON verification_tokens(customer_id);
CREATE INDEX idx_verification_tokens_type ON verification_tokens(type);
CREATE INDEX idx_verification_tokens_expires ON verification_tokens(expires_at);

-- Customer remember tokens indexes
CREATE INDEX idx_customer_remember_tokens_customer_id ON customer_remember_tokens(customer_id);

-- Activity log indexes
CREATE INDEX idx_activity_log_action ON activity_log(action);
CREATE INDEX idx_activity_log_status ON activity_log(status);
CREATE INDEX idx_activity_log_created ON activity_log(created_at);

-- User activities indexes
CREATE INDEX idx_user_activities_user ON user_activities(user_id, user_type);
CREATE INDEX idx_user_activities_type ON user_activities(activity_type);
CREATE INDEX idx_user_activities_created ON user_activities(created_at);
CREATE INDEX idx_user_activities_related ON user_activities(related_table, related_id);

-- Support tickets indexes
CREATE INDEX idx_support_tickets_email ON support_tickets(email);
CREATE INDEX idx_support_tickets_status ON support_tickets(status);
CREATE INDEX idx_support_tickets_priority ON support_tickets(priority);
CREATE INDEX idx_support_tickets_category ON support_tickets(category);
CREATE INDEX idx_support_tickets_assigned_to ON support_tickets(assigned_to);
CREATE INDEX idx_support_tickets_department ON support_tickets(department);
CREATE INDEX idx_support_tickets_created ON support_tickets(created_at);
CREATE INDEX idx_support_tickets_due_date ON support_tickets(due_date);
CREATE INDEX idx_support_tickets_status_priority ON support_tickets(status, priority);
CREATE INDEX idx_support_tickets_email_status ON support_tickets(email, status);
CREATE INDEX idx_support_tickets_customer_id ON support_tickets(customer_id);

-- Ticket replies indexes
CREATE INDEX idx_ticket_replies_ticket_id ON ticket_replies(ticket_id);
CREATE INDEX idx_ticket_replies_customer_id ON ticket_replies(customer_id);
CREATE INDEX idx_ticket_replies_admin_id ON ticket_replies(admin_id);
CREATE INDEX idx_ticket_replies_created ON ticket_replies(created_at);

-- Contact messages indexes
CREATE INDEX idx_contact_messages_customer_id ON contact_messages(customer_id);
CREATE INDEX idx_contact_messages_status ON contact_messages(status);
CREATE INDEX idx_contact_messages_created ON contact_messages(created_at);

-- Domain registrations indexes
CREATE INDEX idx_domain_registrations_user_id ON domain_registrations(user_id);
CREATE INDEX idx_domain_registrations_domain ON domain_registrations(domain);
CREATE INDEX idx_domain_registrations_status ON domain_registrations(status);
CREATE INDEX idx_domain_registrations_created ON domain_registrations(created_at);
CREATE INDEX idx_domain_registrations_status_created ON domain_registrations(status, created_at);
CREATE INDEX idx_domain_registrations_user_status ON domain_registrations(user_id, status);

-- Domains indexes
CREATE INDEX idx_domains_status ON domains(status);
CREATE INDEX idx_domains_expiration ON domains(expiration_date);

-- VMs indexes
CREATE INDEX idx_vms_name ON vms(name);
CREATE INDEX idx_vms_status ON vms(status);
CREATE INDEX idx_vms_ip_address ON vms(ip_address);

-- Websites indexes
CREATE INDEX idx_websites_ip ON websites(ip_address);
CREATE INDEX idx_websites_active ON websites(active);

-- Databases indexes
CREATE INDEX idx_sm_databases_user ON sm_databases(database_user);
CREATE INDEX idx_sm_databases_active ON sm_databases(active);
CREATE INDEX idx_sm_databases_website_id ON sm_databases(website_id);

-- Email accounts indexes
CREATE INDEX idx_email_accounts_domain ON email_accounts(domain);
CREATE INDEX idx_email_accounts_active ON email_accounts(active);
CREATE INDEX idx_email_accounts_website_id ON email_accounts(website_id);

-- SSL certificates indexes
CREATE INDEX idx_ssl_certificates_website_id ON ssl_certificates(website_id);
CREATE INDEX idx_ssl_certificates_domain ON ssl_certificates(domain);
CREATE INDEX idx_ssl_certificates_expiration ON ssl_certificates(expiration_date);

-- System settings indexes
CREATE INDEX idx_system_settings_updated_by ON system_settings(updated_by);

-- =============================================================================
-- TRIGGER FÜR UPDATED_AT
-- =============================================================================

-- Funktion für updated_at Trigger
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Trigger für alle Tabellen mit updated_at
CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_customers_updated_at BEFORE UPDATE ON customers FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_user_sessions_updated_at BEFORE UPDATE ON user_sessions FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_support_tickets_updated_at BEFORE UPDATE ON support_tickets FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_contact_messages_updated_at BEFORE UPDATE ON contact_messages FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_domain_registrations_updated_at BEFORE UPDATE ON domain_registrations FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_domains_updated_at BEFORE UPDATE ON domains FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_vms_updated_at BEFORE UPDATE ON vms FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_websites_updated_at BEFORE UPDATE ON websites FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_sm_databases_updated_at BEFORE UPDATE ON sm_databases FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_email_accounts_updated_at BEFORE UPDATE ON email_accounts FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_ssl_certificates_updated_at BEFORE UPDATE ON ssl_certificates FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_ips_updated_at BEFORE UPDATE ON ips FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_api_credentials_updated_at BEFORE UPDATE ON api_credentials FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_system_settings_updated_at BEFORE UPDATE ON system_settings FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- =============================================================================
-- VIEWS FÜR ÜBERSICHTEN
-- =============================================================================

-- Benutzeraktivitäts-Übersicht
CREATE VIEW user_activity_overview AS
SELECT 
    u.id,
    u.username,
    u.full_name,
    u.email,
    u.role,
    u.active,
    u.last_login,
    u.failed_login_attempts,
    u.locked_until,
    COUNT(DISTINCT s.id) AS active_sessions,
    COUNT(DISTINCT la.id) AS total_login_attempts,
    SUM(CASE WHEN la.success = 'n' THEN 1 ELSE 0 END) AS failed_attempts_today,
    u.created_at
FROM users u
LEFT JOIN user_sessions s ON u.id = s.user_id AND s.is_active = 'y' AND s.expires_at > CURRENT_TIMESTAMP
LEFT JOIN login_attempts la ON u.username = la.username AND DATE(la.created_at) = CURRENT_DATE
GROUP BY u.id;

-- VM-Übersicht
CREATE VIEW vm_overview AS
SELECT 
    v.id,
    v.vm_id,
    v.name,
    v.node,
    v.status,
    v.memory,
    v.cores,
    v.ip_address,
    v.mac_address,
    w.domain AS website_domain,
    COUNT(DISTINCT d.id) AS database_count,
    COUNT(DISTINCT e.id) AS email_count,
    v.created_at,
    v.updated_at
FROM vms v
LEFT JOIN websites w ON v.ip_address = w.ip_address
LEFT JOIN sm_databases d ON w.id = d.website_id
LEFT JOIN email_accounts e ON w.id = e.website_id
GROUP BY v.id;

-- Website-Übersicht
CREATE VIEW website_overview AS
SELECT 
    w.id,
    w.domain,
    w.ip_address,
    w.system_user,
    w.active,
    v.name AS vm_name,
    v.status AS vm_status,
    COUNT(DISTINCT d.id) AS database_count,
    COUNT(DISTINCT e.id) AS email_count,
    s.status AS ssl_status,
    s.expiration_date AS ssl_expires,
    w.created_at,
    w.updated_at
FROM websites w
LEFT JOIN vms v ON w.ip_address = v.ip_address
LEFT JOIN sm_databases d ON w.id = d.website_id
LEFT JOIN email_accounts e ON w.id = e.website_id
LEFT JOIN ssl_certificates s ON w.id = s.website_id
GROUP BY w.id;

-- =============================================================================
-- GRUNDDATEN EINFÜGEN
-- =============================================================================

-- Standard-Admin-Benutzer
INSERT INTO users (username, email, password_hash, full_name, role, active) VALUES
('admin', 'admin@yourserver.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin', 'y');

-- Standard-Module
INSERT INTO modules (name, is_active) VALUES
('admin', TRUE),
('proxmox', TRUE),
('ispconfig', TRUE),
('ovh', TRUE),
('database', TRUE),
('email', TRUE),
('dns', TRUE),
('network', TRUE),
('support-tickets', TRUE),
('virtual-mac', TRUE),
('endpoints', TRUE);

-- Standard-Systemeinstellungen
INSERT INTO system_settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('site_name', 'Server Management System', 'string', 'Name der Website', 'y'),
('site_url', 'https://yourserver.com', 'string', 'URL der Website', 'y'),
('support_email', 'support@yourserver.com', 'string', 'Support-E-Mail-Adresse', 'y'),
('system_email', 'system@yourserver.com', 'string', 'System-E-Mail-Adresse', 'n'),
('admin_email', 'admin@yourserver.com', 'string', 'Admin-E-Mail-Adresse', 'n'),
('max_login_attempts', '5', 'integer', 'Maximale Login-Versuche', 'n'),
('session_timeout', '3600', 'integer', 'Session-Timeout in Sekunden', 'n'),
('password_min_length', '8', 'integer', 'Mindestlänge für Passwörter', 'n'),
('enable_registration', 'true', 'boolean', 'Registrierung aktiviert', 'y'),
('maintenance_mode', 'false', 'boolean', 'Wartungsmodus aktiviert', 'y');

-- Standard-Einstellungen
INSERT INTO settings (site_title, logo_path, favicon_path, mode) VALUES
('Server Management System', NULL, NULL, 'live');

-- =============================================================================
-- BERECHTIGUNGEN SETZEN
-- =============================================================================

-- Berechtigungen für den postgres Benutzer
GRANT ALL PRIVILEGES ON DATABASE server_management TO postgres;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO postgres;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO postgres;
GRANT ALL PRIVILEGES ON ALL FUNCTIONS IN SCHEMA public TO postgres;

-- Berechtigungen für andere Benutzer (falls benötigt)
-- GRANT CONNECT ON DATABASE server_management TO app_user;
-- GRANT USAGE ON SCHEMA public TO app_user;
-- GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO app_user;
-- GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO app_user;
