-- =============================================================================
-- INSTALLATION-OPTIMIERTE POSTGRESQL-STRUKTUR FÜR SERVER MANAGEMENT SYSTEM
-- =============================================================================
-- Erstellt: 2025-09-03
-- Version: 2.0
-- Beschreibung: PostgreSQL-Struktur für install.php ohne Standarddaten
-- =============================================================================

-- =============================================================================
-- ENUM TYPES ERSTELLEN
-- =============================================================================

CREATE TYPE user_role AS ENUM ('admin', 'user', 'moderator');
CREATE TYPE active_status AS ENUM ('y', 'n');
CREATE TYPE customer_status AS ENUM ('pending', 'active', 'suspended', 'deleted');
CREATE TYPE permission_type AS ENUM ('proxmox', 'ispconfig', 'ovh', 'ogp', 'admin', 'readonly');
CREATE TYPE logout_reason AS ENUM ('manual', 'timeout', 'forced');
CREATE TYPE success_status AS ENUM ('y', 'n');
CREATE TYPE token_type AS ENUM ('email_verification', 'password_reset');
CREATE TYPE activity_status AS ENUM ('success', 'error', 'pending', 'info');
CREATE TYPE user_type AS ENUM ('customer', 'admin');
CREATE TYPE priority_level AS ENUM ('low', 'medium', 'high', 'urgent');
CREATE TYPE ticket_status AS ENUM ('open', 'in_progress', 'waiting_customer', 'waiting_admin', 'resolved', 'closed');
CREATE TYPE ticket_category AS ENUM ('technical', 'billing', 'general', 'feature_request', 'bug_report', 'account');
CREATE TYPE department_type AS ENUM ('support', 'billing', 'technical', 'sales');
CREATE TYPE source_type AS ENUM ('web', 'email', 'phone', 'chat');
CREATE TYPE message_status AS ENUM ('new', 'read', 'replied', 'archived');
CREATE TYPE registration_status AS ENUM ('pending', 'approved', 'rejected', 'cancelled');
CREATE TYPE vm_status AS ENUM ('running', 'stopped', 'suspended');
CREATE TYPE database_type AS ENUM ('mysql', 'postgresql');
CREATE TYPE ssl_status AS ENUM ('valid', 'expired', 'revoked', 'pending');
CREATE TYPE setting_type AS ENUM ('string', 'integer', 'boolean', 'json');
CREATE TYPE public_status AS ENUM ('y', 'n');
CREATE TYPE mode_type AS ENUM ('live', 'database');

-- =============================================================================
-- TABELLEN ERSTELLEN
-- =============================================================================

-- 1. USERS TABLE (Admin-Benutzer)
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    role user_role NOT NULL DEFAULT 'user',
    active active_status NOT NULL DEFAULT 'y',
    last_login TIMESTAMP,
    failed_login_attempts INTEGER NOT NULL DEFAULT 0,
    locked_until TIMESTAMP,
    password_changed_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 2. CUSTOMERS TABLE (Kunden-Benutzer)
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
    status customer_status NOT NULL DEFAULT 'pending',
    email_verified_at TIMESTAMP,
    last_login TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 3. USER_PERMISSIONS TABLE
CREATE TABLE user_permissions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    permission_type permission_type NOT NULL,
    resource_id VARCHAR(255),
    granted_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    UNIQUE(user_id, permission_type, resource_id)
);

-- 4. USER_SESSIONS TABLE
CREATE TABLE user_sessions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    session_id VARCHAR(128) NOT NULL UNIQUE,
    ip_address INET,
    user_agent TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_active active_status NOT NULL DEFAULT 'y',
    logout_reason logout_reason
);

-- 5. LOGIN_ATTEMPTS TABLE
CREATE TABLE login_attempts (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    ip_address INET,
    user_agent TEXT,
    success success_status NOT NULL,
    failure_reason VARCHAR(255),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 6. CUSTOMER_LOGIN_LOGS TABLE
CREATE TABLE customer_login_logs (
    id SERIAL PRIMARY KEY,
    customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    ip_address INET,
    user_agent TEXT,
    success BOOLEAN NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 7. VERIFICATION_TOKENS TABLE
CREATE TABLE verification_tokens (
    id SERIAL PRIMARY KEY,
    customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    token VARCHAR(255) NOT NULL,
    type token_type NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 8. CUSTOMER_REMEMBER_TOKENS TABLE
CREATE TABLE customer_remember_tokens (
    id SERIAL PRIMARY KEY,
    customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    token VARCHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 9. ACTIVITY_LOG TABLE
CREATE TABLE activity_log (
    id SERIAL PRIMARY KEY,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    status activity_status NOT NULL DEFAULT 'info',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 10. USER_ACTIVITIES TABLE
CREATE TABLE user_activities (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    user_type user_type NOT NULL,
    activity_type VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    related_id INTEGER,
    related_table VARCHAR(100),
    ip_address INET,
    user_agent TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 11. SUPPORT_TICKETS TABLE
CREATE TABLE support_tickets (
    id SERIAL PRIMARY KEY,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    ticket_number VARCHAR(20) NOT NULL UNIQUE,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    email VARCHAR(255) NOT NULL,
    customer_name VARCHAR(255),
    phone VARCHAR(50),
    priority priority_level NOT NULL DEFAULT 'medium',
    status ticket_status NOT NULL DEFAULT 'open',
    category ticket_category NOT NULL DEFAULT 'general',
    assigned_to INTEGER REFERENCES users(id) ON DELETE SET NULL,
    department department_type DEFAULT 'support',
    source source_type NOT NULL DEFAULT 'web',
    ip_address INET,
    user_agent TEXT,
    estimated_resolution_time INTEGER,
    actual_resolution_time INTEGER,
    customer_satisfaction INTEGER,
    internal_notes TEXT,
    tags TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP,
    closed_at TIMESTAMP,
    due_date TIMESTAMP
);

-- 12. TICKET_REPLIES TABLE
CREATE TABLE ticket_replies (
    id SERIAL PRIMARY KEY,
    ticket_id INTEGER NOT NULL REFERENCES support_tickets(id) ON DELETE CASCADE,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    admin_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    message TEXT NOT NULL,
    is_internal BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 13. CONTACT_MESSAGES TABLE
CREATE TABLE contact_messages (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    status message_status NOT NULL DEFAULT 'new',
    ip_address INET,
    user_agent TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 14. DOMAIN_REGISTRATIONS TABLE
CREATE TABLE domain_registrations (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    domain VARCHAR(255) NOT NULL,
    purpose TEXT NOT NULL,
    notes TEXT,
    status registration_status NOT NULL DEFAULT 'pending',
    admin_notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 15. DOMAINS TABLE
CREATE TABLE domains (
    id SERIAL PRIMARY KEY,
    domain_name VARCHAR(255) NOT NULL UNIQUE,
    registrar VARCHAR(100),
    registration_date DATE,
    expiration_date DATE,
    auto_renew active_status NOT NULL DEFAULT 'y',
    nameservers TEXT,
    status VARCHAR(100),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 16. VMS TABLE (Virtuelle Maschinen)
CREATE TABLE vms (
    id SERIAL PRIMARY KEY,
    vm_id INTEGER NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    node VARCHAR(100) NOT NULL,
    status vm_status NOT NULL DEFAULT 'stopped',
    memory INTEGER NOT NULL,
    cores INTEGER NOT NULL,
    disk_size INTEGER NOT NULL,
    ip_address INET,
    mac_address MACADDR,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 17. WEBSITES TABLE
CREATE TABLE websites (
    id SERIAL PRIMARY KEY,
    domain VARCHAR(255) NOT NULL UNIQUE,
    ip_address INET NOT NULL,
    system_user VARCHAR(100) NOT NULL,
    system_group VARCHAR(100) NOT NULL,
    document_root VARCHAR(255),
    hd_quota INTEGER,
    traffic_quota INTEGER,
    active active_status NOT NULL DEFAULT 'y',
    ssl_enabled active_status NOT NULL DEFAULT 'n',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 18. SM_DATABASES TABLE
CREATE TABLE sm_databases (
    id SERIAL PRIMARY KEY,
    database_name VARCHAR(100) NOT NULL,
    database_user VARCHAR(100) NOT NULL,
    database_type database_type NOT NULL DEFAULT 'mysql',
    server_id INTEGER,
    charset VARCHAR(50) DEFAULT 'utf8mb4',
    remote_access active_status NOT NULL DEFAULT 'n',
    active active_status NOT NULL DEFAULT 'y',
    website_id INTEGER REFERENCES websites(id) ON DELETE SET NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 19. EMAIL_ACCOUNTS TABLE
CREATE TABLE email_accounts (
    id SERIAL PRIMARY KEY,
    email_address VARCHAR(255) NOT NULL,
    login_name VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255),
    full_name VARCHAR(255),
    domain VARCHAR(255) NOT NULL,
    quota_mb INTEGER,
    active active_status NOT NULL DEFAULT 'y',
    autoresponder active_status NOT NULL DEFAULT 'n',
    autoresponder_text TEXT,
    forward_to VARCHAR(255),
    spam_filter active_status NOT NULL DEFAULT 'y',
    website_id INTEGER REFERENCES websites(id) ON DELETE SET NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 20. SSL_CERTIFICATES TABLE
CREATE TABLE ssl_certificates (
    id SERIAL PRIMARY KEY,
    domain VARCHAR(255) NOT NULL,
    certificate_path VARCHAR(255),
    private_key_path VARCHAR(255),
    certificate_authority VARCHAR(100),
    issue_date DATE,
    expiration_date DATE,
    auto_renew active_status NOT NULL DEFAULT 'y',
    status ssl_status NOT NULL DEFAULT 'pending',
    website_id INTEGER REFERENCES websites(id) ON DELETE SET NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 21. IPS TABLE
CREATE TABLE ips (
    id SERIAL PRIMARY KEY,
    subnet CIDR NOT NULL,
    ip_reverse VARCHAR(255) NOT NULL,
    reverse VARCHAR(255),
    ttl INTEGER,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 22. API_CREDENTIALS TABLE
CREATE TABLE api_credentials (
    id SERIAL PRIMARY KEY,
    service_name VARCHAR(100) NOT NULL,
    endpoint VARCHAR(255),
    username VARCHAR(100),
    password_encrypted TEXT,
    api_key_encrypted TEXT,
    additional_config JSONB,
    active active_status NOT NULL DEFAULT 'y',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 23. MODULES TABLE
CREATE TABLE modules (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE
);

-- 24. SYSTEM_SETTINGS TABLE
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

-- 25. SETTINGS TABLE
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

CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_customers_updated_at BEFORE UPDATE ON customers FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
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
