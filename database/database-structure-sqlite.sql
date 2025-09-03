-- =============================================================================
-- OPTIMIERTE DATENBANKSTRUKTUR FÜR SQLITE
-- =============================================================================
-- Erstellt: 2025-09-03
-- Version: 2.0
-- Beschreibung: SQLite-optimierte SQL-Struktur basierend auf Systemanalyse
-- =============================================================================

-- SQLite spezifische Einstellungen
PRAGMA foreign_keys = ON;
PRAGMA journal_mode = WAL;
PRAGMA synchronous = NORMAL;
PRAGMA cache_size = 1000;
PRAGMA temp_store = MEMORY;

-- =============================================================================
-- BENUTZER-TABELLEN
-- =============================================================================

-- Admin-Benutzer
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    role VARCHAR(255),
    active VARCHAR(1) DEFAULT 'y' CHECK (active IN ('y', 'n')),
    last_login DATETIME,
    failed_login_attempts INTEGER DEFAULT 0,
    locked_until DATETIME,
    password_changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Kunden-Benutzer
CREATE TABLE customers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
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
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'active', 'suspended', 'deleted')),
    email_verified_at DATETIME,
    last_login DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- =============================================================================
-- AUTHENTIFIZIERUNG UND SICHERHEIT
-- =============================================================================

-- Benutzerberechtigungen
CREATE TABLE user_permissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    permission_type VARCHAR(20) NOT NULL CHECK (permission_type IN ('proxmox', 'ispconfig', 'ovh', 'ogp', 'admin', 'readonly')),
    resource_id VARCHAR(255),
    granted_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    UNIQUE(user_id, permission_type, resource_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Benutzersitzungen
CREATE TABLE user_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    session_id VARCHAR(128) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    is_active VARCHAR(1) DEFAULT 'y' CHECK (is_active IN ('y', 'n')),
    logout_reason VARCHAR(20) CHECK (logout_reason IN ('manual', 'timeout', 'forced')),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Login-Versuche
CREATE TABLE login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(100),
    ip_address VARCHAR(45),
    user_agent TEXT,
    success VARCHAR(1) NOT NULL CHECK (success IN ('y', 'n')),
    failure_reason VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Kunden-Login-Logs
CREATE TABLE customer_login_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    success BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Verifizierungstoken
CREATE TABLE verification_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    type VARCHAR(20) DEFAULT 'email_verification' CHECK (type IN ('email_verification', 'password_reset')),
    expires_at DATETIME NOT NULL,
    used_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- Kunden-Remember-Token
CREATE TABLE customer_remember_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- =============================================================================
-- AKTIVITÄTS- UND LOGGING-TABELLEN
-- =============================================================================

-- Aktivitätsprotokoll
CREATE TABLE activity_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    status VARCHAR(10) NOT NULL CHECK (status IN ('success', 'error', 'pending', 'info')),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Benutzeraktivitäten
CREATE TABLE user_activities (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    user_type VARCHAR(10) DEFAULT 'customer' CHECK (user_type IN ('customer', 'admin')),
    activity_type VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    related_id INTEGER,
    related_table VARCHAR(50),
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- =============================================================================
-- SUPPORT-SYSTEM
-- =============================================================================

-- Support-Tickets
CREATE TABLE support_tickets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER,
    ticket_number VARCHAR(20) NOT NULL DEFAULT 'TEMP' UNIQUE,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    email VARCHAR(255) NOT NULL DEFAULT '',
    customer_name VARCHAR(255),
    phone VARCHAR(50),
    priority VARCHAR(10) DEFAULT 'medium' CHECK (priority IN ('low', 'medium', 'high', 'urgent')),
    status VARCHAR(20) DEFAULT 'open' CHECK (status IN ('open', 'in_progress', 'waiting_customer', 'waiting_admin', 'resolved', 'closed')),
    category VARCHAR(20) DEFAULT 'general' CHECK (category IN ('technical', 'billing', 'general', 'feature_request', 'bug_report', 'account')),
    assigned_to INTEGER,
    department VARCHAR(20) DEFAULT 'support' CHECK (department IN ('support', 'billing', 'technical', 'sales')),
    source VARCHAR(10) DEFAULT 'web' CHECK (source IN ('web', 'email', 'phone', 'chat')),
    ip_address VARCHAR(45),
    user_agent TEXT,
    estimated_resolution_time INTEGER, -- in hours
    actual_resolution_time INTEGER, -- in hours
    customer_satisfaction INTEGER, -- 1-5 rating
    internal_notes TEXT,
    tags VARCHAR(500), -- comma-separated tags
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME,
    closed_at DATETIME,
    due_date DATETIME,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
);

-- Ticket-Antworten
CREATE TABLE ticket_replies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ticket_id INTEGER NOT NULL,
    customer_id INTEGER,
    admin_id INTEGER,
    message TEXT NOT NULL,
    is_internal BOOLEAN NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Kontaktnachrichten
CREATE TABLE contact_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    customer_id INTEGER,
    status VARCHAR(10) DEFAULT 'new' CHECK (status IN ('new', 'read', 'replied', 'archived')),
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
);

-- =============================================================================
-- DOMAIN-MANAGEMENT
-- =============================================================================

-- Domain-Registrierungen
CREATE TABLE domain_registrations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    domain VARCHAR(255) NOT NULL,
    purpose VARCHAR(50) NOT NULL DEFAULT 'other',
    notes TEXT,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected', 'cancelled')),
    admin_notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Domains
CREATE TABLE domains (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    domain_name VARCHAR(255) NOT NULL UNIQUE,
    registrar VARCHAR(100) DEFAULT 'OVH',
    registration_date DATE,
    expiration_date DATE,
    auto_renew VARCHAR(1) DEFAULT 'y' CHECK (auto_renew IN ('y', 'n')),
    nameservers TEXT,
    status VARCHAR(20) DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- =============================================================================
-- INFRASTRUKTUR-TABELLEN
-- =============================================================================

-- Virtuelle Maschinen
CREATE TABLE vms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    vm_id INTEGER NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    node VARCHAR(100) NOT NULL,
    status VARCHAR(20) DEFAULT 'stopped' CHECK (status IN ('running', 'stopped', 'suspended')),
    memory INTEGER NOT NULL,
    cores INTEGER NOT NULL,
    disk_size INTEGER NOT NULL,
    ip_address VARCHAR(45),
    mac_address VARCHAR(17),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Websites
CREATE TABLE websites (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    domain VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45) NOT NULL,
    system_user VARCHAR(100) NOT NULL,
    system_group VARCHAR(100) NOT NULL,
    document_root VARCHAR(500),
    hd_quota INTEGER DEFAULT 1000,
    traffic_quota INTEGER DEFAULT 10000,
    active VARCHAR(1) DEFAULT 'y' CHECK (active IN ('y', 'n')),
    ssl_enabled VARCHAR(1) DEFAULT 'n' CHECK (ssl_enabled IN ('y', 'n')),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Datenbanken
CREATE TABLE sm_databases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    database_name VARCHAR(255) NOT NULL UNIQUE,
    database_user VARCHAR(255) NOT NULL,
    database_type VARCHAR(20) DEFAULT 'mysql' CHECK (database_type IN ('mysql', 'postgresql')),
    server_id INTEGER DEFAULT 1,
    charset VARCHAR(50) DEFAULT 'utf8',
    remote_access VARCHAR(1) DEFAULT 'n' CHECK (remote_access IN ('y', 'n')),
    active VARCHAR(1) DEFAULT 'y' CHECK (active IN ('y', 'n')),
    website_id INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE SET NULL
);

-- E-Mail-Konten
CREATE TABLE email_accounts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email_address VARCHAR(255) NOT NULL UNIQUE,
    login_name VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255),
    full_name VARCHAR(255),
    domain VARCHAR(255) NOT NULL,
    quota_mb INTEGER DEFAULT 1000,
    active VARCHAR(1) DEFAULT 'y' CHECK (active IN ('y', 'n')),
    autoresponder VARCHAR(1) DEFAULT 'n' CHECK (autoresponder IN ('y', 'n')),
    autoresponder_text TEXT,
    forward_to VARCHAR(255),
    spam_filter VARCHAR(1) DEFAULT 'y' CHECK (spam_filter IN ('y', 'n')),
    website_id INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE SET NULL
);

-- SSL-Zertifikate
CREATE TABLE ssl_certificates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    domain VARCHAR(255) NOT NULL,
    certificate_path VARCHAR(500),
    private_key_path VARCHAR(500),
    certificate_authority VARCHAR(100),
    issue_date DATE,
    expiration_date DATE,
    auto_renew VARCHAR(1) DEFAULT 'y' CHECK (auto_renew IN ('y', 'n')),
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('valid', 'expired', 'revoked', 'pending')),
    website_id INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE CASCADE
);

-- IP-Adressen
CREATE TABLE ips (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    subnet VARCHAR(64) NOT NULL,
    ip_reverse VARCHAR(64) NOT NULL,
    reverse VARCHAR(255),
    ttl INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(subnet, ip_reverse)
);

-- =============================================================================
-- SYSTEM-KONFIGURATION
-- =============================================================================

-- API-Zugangsdaten
CREATE TABLE api_credentials (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    service_name VARCHAR(100) NOT NULL UNIQUE,
    endpoint VARCHAR(500),
    username VARCHAR(255),
    password_encrypted TEXT,
    api_key_encrypted TEXT,
    additional_config TEXT, -- JSON als TEXT in SQLite
    active VARCHAR(1) DEFAULT 'y' CHECK (active IN ('y', 'n')),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Module
CREATE TABLE modules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    is_active BOOLEAN NOT NULL DEFAULT 1
);

-- Systemeinstellungen
CREATE TABLE system_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type VARCHAR(10) DEFAULT 'string' CHECK (setting_type IN ('string', 'integer', 'boolean', 'json')),
    description TEXT,
    is_public VARCHAR(1) DEFAULT 'n' CHECK (is_public IN ('y', 'n')),
    updated_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Einstellungen
CREATE TABLE settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    site_title VARCHAR(255) NOT NULL DEFAULT 'Meine Seite',
    logo_path VARCHAR(255),
    favicon_path VARCHAR(255),
    mode VARCHAR(10) DEFAULT 'live' CHECK (mode IN ('live', 'database'))
);

-- =============================================================================
-- INDEXES ERSTELLEN
-- =============================================================================

-- Users indexes
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_active ON users(active);
CREATE INDEX idx_users_last_login ON users(last_login);

-- Customers indexes
CREATE INDEX idx_customers_email ON customers(email);
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
CREATE INDEX idx_support_tickets_ticket_number ON support_tickets(ticket_number);
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
CREATE INDEX idx_domains_domain_name ON domains(domain_name);
CREATE INDEX idx_domains_status ON domains(status);
CREATE INDEX idx_domains_expiration ON domains(expiration_date);

-- VMs indexes
CREATE INDEX idx_vms_vm_id ON vms(vm_id);
CREATE INDEX idx_vms_name ON vms(name);
CREATE INDEX idx_vms_status ON vms(status);
CREATE INDEX idx_vms_ip_address ON vms(ip_address);

-- Websites indexes
CREATE INDEX idx_websites_domain ON websites(domain);
CREATE INDEX idx_websites_ip ON websites(ip_address);
CREATE INDEX idx_websites_active ON websites(active);

-- Databases indexes
CREATE INDEX idx_sm_databases_database_name ON sm_databases(database_name);
CREATE INDEX idx_sm_databases_user ON sm_databases(database_user);
CREATE INDEX idx_sm_databases_active ON sm_databases(active);
CREATE INDEX idx_sm_databases_website_id ON sm_databases(website_id);

-- Email accounts indexes
CREATE INDEX idx_email_accounts_email_address ON email_accounts(email_address);
CREATE INDEX idx_email_accounts_domain ON email_accounts(domain);
CREATE INDEX idx_email_accounts_active ON email_accounts(active);
CREATE INDEX idx_email_accounts_website_id ON email_accounts(website_id);

-- SSL certificates indexes
CREATE INDEX idx_ssl_certificates_website_id ON ssl_certificates(website_id);
CREATE INDEX idx_ssl_certificates_domain ON ssl_certificates(domain);
CREATE INDEX idx_ssl_certificates_expiration ON ssl_certificates(expiration_date);

-- API credentials indexes
CREATE INDEX idx_api_credentials_service_name ON api_credentials(service_name);
CREATE INDEX idx_api_credentials_active ON api_credentials(active);

-- Modules indexes
CREATE INDEX idx_modules_name ON modules(name);
CREATE INDEX idx_modules_active ON modules(is_active);

-- System settings indexes
CREATE INDEX idx_system_settings_setting_key ON system_settings(setting_key);
CREATE INDEX idx_system_settings_updated_by ON system_settings(updated_by);

-- Settings indexes
CREATE INDEX idx_settings_mode ON settings(mode);

-- =============================================================================
-- TRIGGER FÜR UPDATED_AT
-- =============================================================================

-- Trigger für users
CREATE TRIGGER update_users_updated_at 
    AFTER UPDATE ON users 
    BEGIN 
        UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; 
    END;

-- Trigger für customers
CREATE TRIGGER update_customers_updated_at 
    AFTER UPDATE ON customers 
    BEGIN 
        UPDATE customers SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; 
    END;

-- Trigger für user_sessions
CREATE TRIGGER update_user_sessions_updated_at 
    AFTER UPDATE ON user_sessions 
    BEGIN 
        UPDATE user_sessions SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; 
    END;

-- Trigger für support_tickets
CREATE TRIGGER update_support_tickets_updated_at 
    AFTER UPDATE ON support_tickets 
    BEGIN 
        UPDATE support_tickets SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; 
    END;

-- Trigger für contact_messages
CREATE TRIGGER update_contact_messages_updated_at 
    AFTER UPDATE ON contact_messages 
    BEGIN 
        UPDATE contact_messages SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; 
    END;

-- Trigger für domain_registrations
CREATE TRIGGER update_domain_registrations_updated_at 
    AFTER UPDATE ON domain_registrations 
    BEGIN 
        UPDATE domain_registrations SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; 
    END;

-- Trigger für domains
CREATE TRIGGER update_domains_updated_at 
    AFTER UPDATE ON domains 
    BEGIN 
        UPDATE domains SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; 
    END;

-- Trigger für vms
CREATE TRIGGER update_vms_updated_at 
    AFTER UPDATE ON vms 
    BEGIN 
        UPDATE vms SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; 
    END;

-- Trigger für websites
CREATE TRIGGER update_websites_updated_at 
    AFTER UPDATE ON websites 
    BEGIN 
        UPDATE websites SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; 
    END;

-- Trigger für sm_databases
CREATE TRIGGER update_sm_databases_updated_at 
    AFTER UPDATE ON sm_databases 
    BEGIN 
        UPDATE sm_databases SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; 
    END;

-- Trigger für email_accounts
CREATE TRIGGER update_email_accounts_updated_at 
    AFTER UPDATE ON email_accounts 
    BEGIN 
        UPDATE email_accounts SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; 
    END;

-- Trigger für ssl_certificates
CREATE TRIGGER update_ssl_certificates_updated_at 
    AFTER UPDATE ON ssl_certificates 
    BEGIN 
        UPDATE ssl_certificates SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; 
    END;

-- Trigger für ips
CREATE TRIGGER update_ips_updated_at 
    AFTER UPDATE ON ips 
    BEGIN 
        UPDATE ips SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; 
    END;

-- Trigger für api_credentials
CREATE TRIGGER update_api_credentials_updated_at 
    AFTER UPDATE ON api_credentials 
    BEGIN 
        UPDATE api_credentials SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; 
    END;

-- Trigger für system_settings
CREATE TRIGGER update_system_settings_updated_at 
    AFTER UPDATE ON system_settings 
    BEGIN 
        UPDATE system_settings SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; 
    END;

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
LEFT JOIN user_sessions s ON u.id = s.user_id AND s.is_active = 'y' AND s.expires_at > datetime('now')
LEFT JOIN login_attempts la ON u.username = la.username AND date(la.created_at) = date('now')
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
('admin', 1),
('proxmox', 1),
('ispconfig', 1),
('ovh', 1),
('database', 1),
('email', 1),
('dns', 1),
('network', 1),
('support-tickets', 1),
('virtual-mac', 1),
('endpoints', 1);

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
-- ANALYZE FÜR OPTIMIERUNG
-- =============================================================================

-- Statistiken für Query-Optimizer aktualisieren
ANALYZE;
