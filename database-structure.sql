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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type_target (type, target_id),
    INDEX idx_next_run (next_run),
    INDEX idx_active (active)
);

-- Users und Permissions (falls Multi-User System gewünscht)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    role ENUM('admin', 'user', 'readonly') DEFAULT 'user',
    active ENUM('y', 'n') DEFAULT 'y',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_username (username),
    UNIQUE KEY unique_email (email),
    INDEX idx_role (role),
    INDEX idx_active (active)
);

-- Beispiel-Daten einfügen
INSERT INTO api_credentials (service_name, endpoint, username, active) VALUES 
('proxmox', 'https://your-proxmox-host:8006', 'root@pam', 'y'),
('ispconfig', 'https://your-ispconfig-host:8080', 'admin', 'y'),
('ovh', 'https://eu.api.ovh.com/1.0', '', 'y');

-- Standard Admin User (Passwort: admin123 - BITTE ÄNDERN!)
INSERT INTO users (username, email, password_hash, full_name, role) VALUES 
('admin', 'admin@localhost', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin');

-- Views für bessere Datenabfrage
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
    COUNT(d.id) as database_count,
    COUNT(e.id) as email_count
FROM vms v
LEFT JOIN websites w ON v.ip_address = w.ip_address
LEFT JOIN sm_databases d ON w.id = d.website_id
LEFT JOIN email_accounts e ON w.id = e.website_id
GROUP BY v.id;

CREATE VIEW website_overview AS
SELECT 
    w.id,
    w.domain,
    w.ip_address,
    w.system_user,
    w.active,
    v.name as vm_name,
    v.status as vm_status,
    COUNT(d.id) as database_count,
    COUNT(e.id) as email_count,
    s.status as ssl_status
FROM websites w
LEFT JOIN vms v ON w.ip_address = v.ip_address
LEFT JOIN sm_databases d ON w.id = d.website_id
LEFT JOIN email_accounts e ON w.id = e.website_id
LEFT JOIN ssl_certificates s ON w.id = s.website_id
GROUP BY w.id;

-- Trigger für automatische Updates
DELIMITER //

CREATE TRIGGER update_vm_timestamp 
BEFORE UPDATE ON vms
FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END//

CREATE TRIGGER update_website_timestamp 
BEFORE UPDATE ON websites
FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END//

CREATE TRIGGER log_vm_creation
AFTER INSERT ON vms
FOR EACH ROW
BEGIN
    INSERT INTO activity_log (action, details, status) 
    VALUES ('VM Created', CONCAT('VM ', NEW.name, ' (ID: ', NEW.vm_id, ') created'), 'success');
END//

CREATE TRIGGER log_website_creation
AFTER INSERT ON websites
FOR EACH ROW
BEGIN
    INSERT INTO activity_log (action, details, status) 
    VALUES ('Website Created', CONCAT('Website ', NEW.domain, ' created'), 'success');
END//

DELIMITER ;

-- Indizes für bessere Performance
CREATE INDEX idx_activity_log_combined ON activity_log (action, status, created_at);
CREATE INDEX idx_vms_combined ON vms (status, created_at);
CREATE INDEX idx_websites_combined ON websites (active, created_at);

-- Berechtigungen setzen (anpassen je nach Bedarf)
-- GRANT ALL PRIVILEGES ON server_management.* TO 'your_db_user'@'localhost';
-- FLUSH PRIVILEGES;