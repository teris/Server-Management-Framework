-- Datenbank erstellen
CREATE DATABASE IF NOT EXISTS server_management;
USE server_management;

-- Aktivitäts-Log Tabelle für alle Server-Aktionen
CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    status ENUM('success', 'error', 'pending') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_action (action),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- VM Management Tabelle
CREATE TABLE vms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vm_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    node VARCHAR(100) NOT NULL,
    status ENUM('running', 'stopped', 'suspended') DEFAULT 'stopped',
    memory INT NOT NULL,
    cores INT NOT NULL,
    disk_size INT NOT NULL,
    ip_address VARCHAR(45),
    mac_address VARCHAR(17),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_vm_id (vm_id),
    INDEX idx_name (name),
    INDEX idx_status (status)
);

-- Website Management Tabelle
CREATE TABLE websites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    system_user VARCHAR(100) NOT NULL,
    system_group VARCHAR(100) NOT NULL,
    document_root VARCHAR(500),
    hd_quota INT DEFAULT 1000,
    traffic_quota INT DEFAULT 10000,
    active ENUM('y', 'n') DEFAULT 'y',
    ssl_enabled ENUM('y', 'n') DEFAULT 'n',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_domain (domain),
    INDEX idx_ip (ip_address),
    INDEX idx_active (active)
);

-- Domain Management Tabelle
CREATE TABLE domains (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain_name VARCHAR(255) NOT NULL,
    registrar VARCHAR(100) DEFAULT 'OVH',
    registration_date DATE,
    expiration_date DATE,
    auto_renew ENUM('y', 'n') DEFAULT 'y',
    nameservers TEXT,
    status ENUM('active', 'pending', 'expired', 'suspended') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_domain (domain_name),
    INDEX idx_status (status),
    INDEX idx_expiration (expiration_date)
);

-- Datenbank Management Tabelle
CREATE TABLE sm_databases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    database_name VARCHAR(255) NOT NULL,
    database_user VARCHAR(255) NOT NULL,
    database_type ENUM('mysql', 'postgresql') DEFAULT 'mysql',
    server_id INT DEFAULT 1,
    charset VARCHAR(50) DEFAULT 'utf8',
    remote_access ENUM('y', 'n') DEFAULT 'n',
    active ENUM('y', 'n') DEFAULT 'y',
    website_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_db_name (database_name),
    INDEX idx_user (database_user),
    INDEX idx_active (active),
    FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE SET NULL
);

-- E-Mail Management Tabelle
CREATE TABLE email_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email_address VARCHAR(255) NOT NULL,
    login_name VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255),
    full_name VARCHAR(255),
    domain VARCHAR(255) NOT NULL,
    quota_mb INT DEFAULT 1000,
    active ENUM('y', 'n') DEFAULT 'y',
    autoresponder ENUM('y', 'n') DEFAULT 'n',
    autoresponder_text TEXT,
    forward_to VARCHAR(255),
    spam_filter ENUM('y', 'n') DEFAULT 'y',
    website_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_email (email_address),
    INDEX idx_domain (domain),
    INDEX idx_active (active),
    FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE SET NULL
);

-- API Credentials Tabelle (verschlüsselt)
CREATE TABLE api_credentials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(100) NOT NULL,
    endpoint VARCHAR(500),
    username VARCHAR(255),
    password_encrypted TEXT,
    api_key_encrypted TEXT,
    additional_config JSON,
    active ENUM('y', 'n') DEFAULT 'y',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_service (service_name)
);

-- =========================================================================
-- BENUTZER-VERWALTUNG UND LOGIN-SYSTEM
-- =========================================================================

-- Benutzer Tabelle für Login-System
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    role ENUM('admin', 'user', 'readonly') DEFAULT 'user',
    active ENUM('y', 'n') DEFAULT 'y',
    last_login TIMESTAMP NULL,
    failed_login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL,
    password_changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_username (username),
    UNIQUE KEY unique_email (email),
    INDEX idx_role (role),
    INDEX idx_active (active),
    INDEX idx_last_login (last_login)
);

-- Login-Sessions Tabelle für Session-Tracking
CREATE TABLE user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(128) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_active ENUM('y', 'n') DEFAULT 'y',
    logout_reason ENUM('manual', 'timeout', 'forced') NULL,
    INDEX idx_session_id (session_id),
    INDEX idx_user_id (user_id),
    INDEX idx_active (is_active),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Login-Attempts Tabelle für Sicherheits-Monitoring
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100),
    ip_address VARCHAR(45),
    user_agent TEXT,
    success ENUM('y', 'n') NOT NULL,
    failure_reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_ip (ip_address),
    INDEX idx_success (success),
    INDEX idx_created_at (created_at)
);

-- Benutzer-Berechtigungen Tabelle (erweitert für Zukunft)
CREATE TABLE user_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    permission_type ENUM('proxmox', 'ispconfig', 'ovh', 'admin', 'readonly') NOT NULL,
    resource_id VARCHAR(255), -- Optional: für spezifische Ressourcen
    granted_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    UNIQUE KEY unique_user_permission (user_id, permission_type, resource_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL
);

-- =========================================================================
-- WEITERE SYSTEM-TABELLEN
-- =========================================================================

-- Network Configuration Tabelle
CREATE TABLE network_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vm_id INT NOT NULL,
    interface_name VARCHAR(50) DEFAULT 'net0',
    ip_address VARCHAR(45),
    subnet_mask VARCHAR(45) DEFAULT '255.255.255.0',
    gateway VARCHAR(45),
    dns_servers TEXT,
    mac_address VARCHAR(17),
    bridge VARCHAR(50) DEFAULT 'vmbr0',
    vlan_tag INT,
    active ENUM('y', 'n') DEFAULT 'y',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vm_id) REFERENCES vms(vm_id) ON DELETE CASCADE,
    INDEX idx_vm_id (vm_id),
    INDEX idx_ip (ip_address)
);

-- SSL Certificates Tabelle
CREATE TABLE ssl_certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain VARCHAR(255) NOT NULL,
    certificate_path VARCHAR(500),
    private_key_path VARCHAR(500),
    certificate_authority VARCHAR(100),
    issue_date DATE,
    expiration_date DATE,
    auto_renew ENUM('y', 'n') DEFAULT 'y',
    status ENUM('valid', 'expired', 'revoked', 'pending') DEFAULT 'pending',
    website_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE CASCADE,
    INDEX idx_domain (domain),
    INDEX idx_expiration (expiration_date)
);

-- Server Resources Monitoring Tabelle
CREATE TABLE server_resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vm_id INT,
    cpu_usage DECIMAL(5,2),
    memory_usage DECIMAL(5,2),
    disk_usage DECIMAL(5,2),
    network_in BIGINT,
    network_out BIGINT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vm_id) REFERENCES vms(vm_id) ON DELETE CASCADE,
    INDEX idx_vm_timestamp (vm_id, timestamp),
    INDEX idx_timestamp (timestamp)
);

-- Backup Jobs Tabelle
CREATE TABLE backup_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type ENUM('vm', 'database', 'files') NOT NULL,
    target_id INT,
    schedule_cron VARCHAR(100),
    storage_location VARCHAR(500),
    retention_days INT DEFAULT 30,
    compression ENUM('none', 'lzo', 'gzip', 'zstd') DEFAULT 'zstd',
    active ENUM('y', 'n') DEFAULT 'y',
    last_run TIMESTAMP NULL,
    next_run TIMESTAMP NULL,
    status ENUM('success', 'failed', 'running', 'pending') DEFAULT 'pending',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type_target (type, target_id),
    INDEX idx_next_run (next_run),
    INDEX idx_active (active),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- System Settings Tabelle
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    is_public ENUM('y', 'n') DEFAULT 'n',
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_setting_key (setting_key),
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- =========================================================================
-- BEISPIEL-DATEN UND STANDARD-KONFIGURATION
-- =========================================================================

-- API Credentials Einträge
INSERT INTO api_credentials (service_name, endpoint, username, active) VALUES 
('proxmox', 'https://your-proxmox-host:8006', 'root@pam', 'y'),
('ispconfig', 'https://your-ispconfig-host:8080', 'admin', 'y'),
('ovh', 'https://eu.api.ovh.com/1.0', '', 'y');

-- Standard System-Einstellungen
INSERT INTO system_settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('session_timeout', '600', 'integer', 'Session-Timeout in Sekunden (Standard: 10 Minuten)', 'n'),
('max_login_attempts', '5', 'integer', 'Maximale Login-Versuche vor Sperrung', 'n'),
('lockout_duration', '900', 'integer', 'Sperrzeit nach zu vielen Login-Versuchen (Sekunden)', 'n'),
('password_min_length', '6', 'integer', 'Minimale Passwort-Länge', 'n'),
('require_password_change', 'n', 'boolean', 'Passwort-Änderung bei erstem Login erzwingen', 'n'),
('site_title', 'Server Management Interface', 'string', 'Website-Titel', 'y'),
('enable_registration', 'n', 'boolean', 'Benutzer-Registrierung aktivieren', 'n'),
('backup_retention_days', '30', 'integer', 'Standard-Aufbewahrungszeit für Backups', 'n');

-- =========================================================================
-- VIEWS FÜR BESSERE DATENABFRAGE
-- =========================================================================

-- VM Übersicht mit Website-Zuordnung
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
    w.domain as website_domain,
    COUNT(DISTINCT d.id) as database_count,
    COUNT(DISTINCT e.id) as email_count,
    v.created_at,
    v.updated_at
FROM vms v
LEFT JOIN websites w ON v.ip_address = w.ip_address
LEFT JOIN sm_databases d ON w.id = d.website_id
LEFT JOIN email_accounts e ON w.id = e.website_id
GROUP BY v.id;

-- Website Übersicht mit Benutzer-Informationen
CREATE VIEW website_overview AS
SELECT 
    w.id,
    w.domain,
    w.ip_address,
    w.system_user,
    w.active,
    v.name as vm_name,
    v.status as vm_status,
    COUNT(DISTINCT d.id) as database_count,
    COUNT(DISTINCT e.id) as email_count,
    s.status as ssl_status,
    s.expiration_date as ssl_expires,
    w.created_at,
    w.updated_at
FROM websites w
LEFT JOIN vms v ON w.ip_address = v.ip_address
LEFT JOIN sm_databases d ON w.id = d.website_id
LEFT JOIN email_accounts e ON w.id = e.website_id
LEFT JOIN ssl_certificates s ON w.id = s.website_id
GROUP BY w.id;

-- Benutzer-Aktivität Übersicht
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
    COUNT(DISTINCT s.id) as active_sessions,
    COUNT(DISTINCT la.id) as total_login_attempts,
    SUM(CASE WHEN la.success = 'n' THEN 1 ELSE 0 END) as failed_attempts_today,
    u.created_at
FROM users u
LEFT JOIN user_sessions s ON u.id = s.user_id AND s.is_active = 'y' AND s.expires_at > NOW()
LEFT JOIN login_attempts la ON u.username = la.username AND DATE(la.created_at) = CURDATE()
GROUP BY u.id;

-- =========================================================================
-- TRIGGER FÜR AUTOMATISCHE UPDATES UND LOGGING
-- =========================================================================

DELIMITER //

-- VM Timestamp Update Trigger
CREATE TRIGGER update_vm_timestamp 
BEFORE UPDATE ON vms
FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END//

-- Website Timestamp Update Trigger
CREATE TRIGGER update_website_timestamp 
BEFORE UPDATE ON websites
FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END//

-- VM Erstellung Logging
CREATE TRIGGER log_vm_creation
AFTER INSERT ON vms
FOR EACH ROW
BEGIN
    INSERT INTO activity_log (action, details, status) 
    VALUES ('VM Created', CONCAT('VM ', NEW.name, ' (ID: ', NEW.vm_id, ') created'), 'success');
END//

-- Website Erstellung Logging
CREATE TRIGGER log_website_creation
AFTER INSERT ON websites
FOR EACH ROW
BEGIN
    INSERT INTO activity_log (action, details, status) 
    VALUES ('Website Created', CONCAT('Website ', NEW.domain, ' created'), 'success');
END//

-- Benutzer Login Tracking
CREATE TRIGGER update_user_login
AFTER INSERT ON login_attempts
FOR EACH ROW
BEGIN
    IF NEW.success = 'y' THEN
        UPDATE users 
        SET last_login = NEW.created_at, failed_login_attempts = 0, locked_until = NULL
        WHERE username = NEW.username;
    ELSE
        UPDATE users 
        SET failed_login_attempts = failed_login_attempts + 1,
            locked_until = CASE 
                WHEN failed_login_attempts + 1 >= 5 THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                ELSE locked_until
            END
        WHERE username = NEW.username;
    END IF;
END//

-- Session Cleanup Trigger
CREATE TRIGGER cleanup_expired_sessions
AFTER INSERT ON user_sessions
FOR EACH ROW
BEGIN
    -- Abgelaufene Sessions als inaktiv markieren
    UPDATE user_sessions 
    SET is_active = 'n', logout_reason = 'timeout'
    WHERE expires_at < NOW() AND is_active = 'y';
END//

DELIMITER ;

-- =========================================================================
-- INDIZES FÜR BESSERE PERFORMANCE
-- =========================================================================

-- Kombinierte Indizes für häufige Abfragen
CREATE INDEX idx_activity_log_combined ON activity_log (action, status, created_at);
CREATE INDEX idx_vms_combined ON vms (status, created_at);
CREATE INDEX idx_websites_combined ON websites (active, created_at);
CREATE INDEX idx_user_login_combined ON users (username, active, locked_until);
CREATE INDEX idx_session_activity_combined ON user_sessions (user_id, is_active, expires_at);

-- =========================================================================
-- BERECHTIGUNG FÜR DATENBANK-BENUTZER (BEISPIEL)
-- =========================================================================

-- Beispiel für Berechtigungen - in Produktion anpassen!
-- CREATE USER 'server_mgmt'@'localhost' IDENTIFIED BY 'secure_password_here';
-- GRANT ALL PRIVILEGES ON server_management.* TO 'server_mgmt'@'localhost';
-- FLUSH PRIVILEGES;

-- =========================================================================
-- CLEANUP-JOBS (Optional - via Cron ausführen)
-- =========================================================================

-- Alte Login-Versuche löschen (älter als 30 Tage)
-- DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Abgelaufene Sessions löschen (älter als 7 Tage)
-- DELETE FROM user_sessions WHERE expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY);

-- Alte Activity-Logs löschen (älter als 90 Tage)
-- DELETE FROM activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);