-- =============================================================================
-- INSTALLATION-OPTIMIERTE SQLITE-STRUKTUR FÜR SERVER MANAGEMENT SYSTEM
-- =============================================================================
-- Erstellt: 2025-09-03
-- Version: 2.0
-- Beschreibung: SQLite-Struktur für install.php ohne Standarddaten
-- =============================================================================

-- =============================================================================
-- TABELLEN ERSTELLEN
-- =============================================================================

-- 1. USERS TABLE (Admin-Benutzer)
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    role VARCHAR(20) NOT NULL DEFAULT 'user' CHECK (role IN ('admin', 'user', 'moderator')),
    active VARCHAR(1) NOT NULL DEFAULT 'y' CHECK (active IN ('y', 'n')),
    last_login DATETIME,
    failed_login_attempts INTEGER NOT NULL DEFAULT 0,
    locked_until DATETIME,
    password_changed_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 2. CUSTOMERS TABLE (Kunden-Benutzer)
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
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'active', 'suspended', 'deleted')),
    email_verified_at DATETIME,
    last_login DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 3. USER_PERMISSIONS TABLE
CREATE TABLE user_permissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    permission_type VARCHAR(20) NOT NULL CHECK (permission_type IN ('proxmox', 'ispconfig', 'ovh', 'ogp', 'admin', 'readonly')),
    resource_id VARCHAR(255),
    granted_by INTEGER,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE(user_id, permission_type, resource_id)
);

-- 4. USER_SESSIONS TABLE
CREATE TABLE user_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    session_id VARCHAR(128) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_activity DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    is_active VARCHAR(1) NOT NULL DEFAULT 'y' CHECK (is_active IN ('y', 'n')),
    logout_reason VARCHAR(20) CHECK (logout_reason IN ('manual', 'timeout', 'forced')),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 5. LOGIN_ATTEMPTS TABLE
CREATE TABLE login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    success VARCHAR(1) NOT NULL CHECK (success IN ('y', 'n')),
    failure_reason VARCHAR(255),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 6. CUSTOMER_LOGIN_LOGS TABLE
CREATE TABLE customer_login_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    success BOOLEAN NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- 7. VERIFICATION_TOKENS TABLE
CREATE TABLE verification_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    token VARCHAR(255) NOT NULL,
    type VARCHAR(20) NOT NULL CHECK (type IN ('email_verification', 'password_reset')),
    expires_at DATETIME NOT NULL,
    used_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- 8. CUSTOMER_REMEMBER_TOKENS TABLE
CREATE TABLE customer_remember_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- 9. ACTIVITY_LOG TABLE
CREATE TABLE activity_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'info' CHECK (status IN ('success', 'error', 'pending', 'info')),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 10. USER_ACTIVITIES TABLE
CREATE TABLE user_activities (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    user_type VARCHAR(20) NOT NULL CHECK (user_type IN ('customer', 'admin')),
    activity_type VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    related_id INTEGER,
    related_table VARCHAR(100),
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 11. SUPPORT_TICKETS TABLE
CREATE TABLE support_tickets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER,
    ticket_number VARCHAR(20) NOT NULL UNIQUE,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    email VARCHAR(255) NOT NULL,
    customer_name VARCHAR(255),
    phone VARCHAR(50),
    priority VARCHAR(20) NOT NULL DEFAULT 'medium' CHECK (priority IN ('low', 'medium', 'high', 'urgent')),
    status VARCHAR(20) NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'in_progress', 'waiting_customer', 'waiting_admin', 'resolved', 'closed')),
    category VARCHAR(20) NOT NULL DEFAULT 'general' CHECK (category IN ('technical', 'billing', 'general', 'feature_request', 'bug_report', 'account')),
    assigned_to INTEGER,
    department VARCHAR(20) DEFAULT 'support' CHECK (department IN ('support', 'billing', 'technical', 'sales')),
    source VARCHAR(20) NOT NULL DEFAULT 'web' CHECK (source IN ('web', 'email', 'phone', 'chat')),
    ip_address VARCHAR(45),
    user_agent TEXT,
    estimated_resolution_time INTEGER,
    actual_resolution_time INTEGER,
    customer_satisfaction INTEGER,
    internal_notes TEXT,
    tags TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME,
    closed_at DATETIME,
    due_date DATETIME,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
);

-- 12. TICKET_REPLIES TABLE
CREATE TABLE ticket_replies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ticket_id INTEGER NOT NULL,
    customer_id INTEGER,
    admin_id INTEGER,
    message TEXT NOT NULL,
    is_internal BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
);

-- 13. CONTACT_MESSAGES TABLE
CREATE TABLE contact_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    customer_id INTEGER,
    status VARCHAR(20) NOT NULL DEFAULT 'new' CHECK (status IN ('new', 'read', 'replied', 'archived')),
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
);

-- 14. DOMAIN_REGISTRATIONS TABLE
CREATE TABLE domain_registrations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    domain VARCHAR(255) NOT NULL,
    purpose TEXT NOT NULL,
    notes TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected', 'cancelled')),
    admin_notes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 15. DOMAINS TABLE
CREATE TABLE domains (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    domain_name VARCHAR(255) NOT NULL UNIQUE,
    registrar VARCHAR(100),
    registration_date DATE,
    expiration_date DATE,
    auto_renew VARCHAR(1) NOT NULL DEFAULT 'y' CHECK (auto_renew IN ('y', 'n')),
    nameservers TEXT,
    status VARCHAR(100),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 16. VMS TABLE (Virtuelle Maschinen)
CREATE TABLE vms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    vm_id INTEGER NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    node VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'stopped' CHECK (status IN ('running', 'stopped', 'suspended')),
    memory INTEGER NOT NULL,
    cores INTEGER NOT NULL,
    disk_size INTEGER NOT NULL,
    ip_address VARCHAR(45),
    mac_address VARCHAR(17),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 17. WEBSITES TABLE
CREATE TABLE websites (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    domain VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45) NOT NULL,
    system_user VARCHAR(100) NOT NULL,
    system_group VARCHAR(100) NOT NULL,
    document_root VARCHAR(255),
    hd_quota INTEGER,
    traffic_quota INTEGER,
    active VARCHAR(1) NOT NULL DEFAULT 'y' CHECK (active IN ('y', 'n')),
    ssl_enabled VARCHAR(1) NOT NULL DEFAULT 'n' CHECK (ssl_enabled IN ('y', 'n')),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 18. SM_DATABASES TABLE
CREATE TABLE sm_databases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    database_name VARCHAR(100) NOT NULL,
    database_user VARCHAR(100) NOT NULL,
    database_type VARCHAR(20) NOT NULL DEFAULT 'mysql' CHECK (database_type IN ('mysql', 'postgresql')),
    server_id INTEGER,
    charset VARCHAR(50) DEFAULT 'utf8mb4',
    remote_access VARCHAR(1) NOT NULL DEFAULT 'n' CHECK (remote_access IN ('y', 'n')),
    active VARCHAR(1) NOT NULL DEFAULT 'y' CHECK (active IN ('y', 'n')),
    website_id INTEGER,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE SET NULL
);

-- 19. EMAIL_ACCOUNTS TABLE
CREATE TABLE email_accounts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email_address VARCHAR(255) NOT NULL,
    login_name VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255),
    full_name VARCHAR(255),
    domain VARCHAR(255) NOT NULL,
    quota_mb INTEGER,
    active VARCHAR(1) NOT NULL DEFAULT 'y' CHECK (active IN ('y', 'n')),
    autoresponder VARCHAR(1) NOT NULL DEFAULT 'n' CHECK (autoresponder IN ('y', 'n')),
    autoresponder_text TEXT,
    forward_to VARCHAR(255),
    spam_filter VARCHAR(1) NOT NULL DEFAULT 'y' CHECK (spam_filter IN ('y', 'n')),
    website_id INTEGER,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE SET NULL
);

-- 20. SSL_CERTIFICATES TABLE
CREATE TABLE ssl_certificates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    domain VARCHAR(255) NOT NULL,
    certificate_path VARCHAR(255),
    private_key_path VARCHAR(255),
    certificate_authority VARCHAR(100),
    issue_date DATE,
    expiration_date DATE,
    auto_renew VARCHAR(1) NOT NULL DEFAULT 'y' CHECK (auto_renew IN ('y', 'n')),
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('valid', 'expired', 'revoked', 'pending')),
    website_id INTEGER,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE SET NULL
);

-- 21. IPS TABLE
CREATE TABLE ips (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    subnet VARCHAR(18) NOT NULL,
    ip_reverse VARCHAR(255) NOT NULL,
    reverse VARCHAR(255),
    ttl INTEGER,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 22. API_CREDENTIALS TABLE
CREATE TABLE api_credentials (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    service_name VARCHAR(100) NOT NULL,
    endpoint VARCHAR(255),
    username VARCHAR(100),
    password_encrypted TEXT,
    api_key_encrypted TEXT,
    additional_config TEXT,
    active VARCHAR(1) NOT NULL DEFAULT 'y' CHECK (active IN ('y', 'n')),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 23. MODULES TABLE
CREATE TABLE modules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE
);

-- 24. SYSTEM_SETTINGS TABLE
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

-- 25. SETTINGS TABLE
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
CREATE INDEX idx_customers_status ON customers(status);
CREATE INDEX idx_customers_email_verified_at ON customers(email_verified_at);
CREATE INDEX idx_user_permissions_user_id ON user_permissions(user_id);
CREATE INDEX idx_user_permissions_permission_type ON user_permissions(permission_type);
CREATE INDEX idx_user_sessions_user_id ON user_sessions(user_id);
CREATE INDEX idx_user_sessions_is_active ON user_sessions(is_active);
CREATE INDEX idx_user_sessions_expires_at ON user_sessions(expires_at);
CREATE INDEX idx_login_attempts_username ON login_attempts(username);
CREATE INDEX idx_login_attempts_ip_address ON login_attempts(ip_address);
CREATE INDEX idx_login_attempts_success ON login_attempts(success);
CREATE INDEX idx_login_attempts_created_at ON login_attempts(created_at);
CREATE INDEX idx_support_tickets_email ON support_tickets(email);
CREATE INDEX idx_support_tickets_status ON support_tickets(status);
CREATE INDEX idx_support_tickets_priority ON support_tickets(priority);
CREATE INDEX idx_support_tickets_category ON support_tickets(category);
CREATE INDEX idx_support_tickets_assigned_to ON support_tickets(assigned_to);
CREATE INDEX idx_support_tickets_created_at ON support_tickets(created_at);
CREATE INDEX idx_support_tickets_status_priority ON support_tickets(status, priority);
CREATE INDEX idx_domains_status ON domains(status);
CREATE INDEX idx_domains_expiration_date ON domains(expiration_date);
CREATE INDEX idx_vms_name ON vms(name);
CREATE INDEX idx_vms_status ON vms(status);
CREATE INDEX idx_vms_ip_address ON vms(ip_address);
CREATE INDEX idx_websites_ip_address ON websites(ip_address);
CREATE INDEX idx_websites_active ON websites(active);
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

CREATE TRIGGER update_customers_updated_at 
    AFTER UPDATE ON customers 
    FOR EACH ROW 
    BEGIN 
        UPDATE customers SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; 
    END;

CREATE TRIGGER update_support_tickets_updated_at 
    AFTER UPDATE ON support_tickets 
    FOR EACH ROW 
    BEGIN 
        UPDATE support_tickets SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; 
    END;

CREATE TRIGGER update_contact_messages_updated_at 
    AFTER UPDATE ON contact_messages 
    FOR EACH ROW 
    BEGIN 
        UPDATE contact_messages SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; 
    END;

CREATE TRIGGER update_domain_registrations_updated_at 
    AFTER UPDATE ON domain_registrations 
    FOR EACH ROW 
    BEGIN 
        UPDATE domain_registrations SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; 
    END;

CREATE TRIGGER update_domains_updated_at 
    AFTER UPDATE ON domains 
    FOR EACH ROW 
    BEGIN 
        UPDATE domains SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; 
    END;

CREATE TRIGGER update_vms_updated_at 
    AFTER UPDATE ON vms 
    FOR EACH ROW 
    BEGIN 
        UPDATE vms SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; 
    END;

CREATE TRIGGER update_websites_updated_at 
    AFTER UPDATE ON websites 
    FOR EACH ROW 
    BEGIN 
        UPDATE websites SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; 
    END;

CREATE TRIGGER update_sm_databases_updated_at 
    AFTER UPDATE ON sm_databases 
    FOR EACH ROW 
    BEGIN 
        UPDATE sm_databases SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; 
    END;

CREATE TRIGGER update_email_accounts_updated_at 
    AFTER UPDATE ON email_accounts 
    FOR EACH ROW 
    BEGIN 
        UPDATE email_accounts SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; 
    END;

CREATE TRIGGER update_ssl_certificates_updated_at 
    AFTER UPDATE ON ssl_certificates 
    FOR EACH ROW 
    BEGIN 
        UPDATE ssl_certificates SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; 
    END;

CREATE TRIGGER update_ips_updated_at 
    AFTER UPDATE ON ips 
    FOR EACH ROW 
    BEGIN 
        UPDATE ips SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; 
    END;

CREATE TRIGGER update_api_credentials_updated_at 
    AFTER UPDATE ON api_credentials 
    FOR EACH ROW 
    BEGIN 
        UPDATE api_credentials SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; 
    END;

CREATE TRIGGER update_system_settings_updated_at 
    AFTER UPDATE ON system_settings 
    FOR EACH ROW 
    BEGIN 
        UPDATE system_settings SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id; 
    END;
