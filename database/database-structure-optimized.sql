-- =============================================================================
-- OPTIMIERTE DATENBANKSTRUKTUR FÜR SERVER MANAGEMENT SYSTEM
-- =============================================================================
-- Erstellt: 2025-09-03
-- Version: 2.0
-- Beschreibung: Optimierte SQL-Struktur basierend auf Systemanalyse
-- Unterstützte Datenbanken: MySQL, MariaDB, PostgreSQL, SQLite
-- =============================================================================

-- MySQL/MariaDB spezifische Einstellungen
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- =============================================================================
-- DATENBANK ERSTELLEN
-- =============================================================================
CREATE DATABASE IF NOT EXISTS `server_management` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `server_management`;

-- =============================================================================
-- BENUTZER-TABELLEN
-- =============================================================================

-- Admin-Benutzer
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `role` varchar(255) DEFAULT NULL,
  `active` enum('y','n') DEFAULT 'y',
  `last_login` timestamp NULL DEFAULT NULL,
  `failed_login_attempts` int(11) DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `password_changed_at` timestamp NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_username` (`username`),
  UNIQUE KEY `unique_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_active` (`active`),
  KEY `idx_last_login` (`last_login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kunden-Benutzer
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `company` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `status` enum('pending','active','suspended','deleted') DEFAULT 'pending',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_status` (`status`),
  KEY `idx_email_verified` (`email_verified_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- AUTHENTIFIZIERUNG UND SICHERHEIT
-- =============================================================================

-- Benutzerberechtigungen
CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `permission_type` enum('proxmox','ispconfig','ovh','ogp','admin','readonly') NOT NULL,
  `resource_id` varchar(255) DEFAULT NULL,
  `granted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_permission` (`user_id`,`permission_type`,`resource_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_permission_type` (`permission_type`),
  KEY `idx_granted_by` (`granted_by`),
  CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_permissions_ibfk_2` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Benutzersitzungen
CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(128) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` timestamp NOT NULL,
  `is_active` enum('y','n') DEFAULT 'y',
  `logout_reason` enum('manual','timeout','forced') DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_session_id` (`session_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Login-Versuche
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `success` enum('y','n') NOT NULL,
  `failure_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`),
  KEY `idx_ip` (`ip_address`),
  KEY `idx_success` (`success`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kunden-Login-Logs
CREATE TABLE `customer_login_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `success` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `customer_login_logs_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verifizierungstoken
CREATE TABLE `verification_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `type` enum('email_verification','password_reset') NOT NULL DEFAULT 'email_verification',
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_type` (`type`),
  KEY `idx_expires_at` (`expires_at`),
  CONSTRAINT `fk_verification_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kunden-Remember-Token
CREATE TABLE `customer_remember_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_customer_id` (`customer_id`),
  CONSTRAINT `customer_remember_tokens_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- AKTIVITÄTS- UND LOGGING-TABELLEN
-- =============================================================================

-- Aktivitätsprotokoll
CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `status` enum('success','error','pending','info') NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_action` (`action`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Benutzeraktivitäten
CREATE TABLE `user_activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `user_type` enum('customer', 'admin') NOT NULL DEFAULT 'customer',
  `activity_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `related_table` varchar(50) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_activities_user` (`user_id`, `user_type`),
  KEY `idx_user_activities_type` (`activity_type`),
  KEY `idx_user_activities_created` (`created_at`),
  KEY `idx_user_activities_related` (`related_table`, `related_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SUPPORT-SYSTEM
-- =============================================================================

-- Support-Tickets
CREATE TABLE `support_tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) DEFAULT NULL,
  `ticket_number` varchar(20) NOT NULL DEFAULT 'TEMP',
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `email` varchar(255) NOT NULL DEFAULT '',
  `customer_name` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('open','in_progress','waiting_customer','waiting_admin','resolved','closed') DEFAULT 'open',
  `category` enum('technical','billing','general','feature_request','bug_report','account') DEFAULT 'general',
  `assigned_to` int(11) DEFAULT NULL,
  `department` enum('support','billing','technical','sales') DEFAULT 'support',
  `source` enum('web','email','phone','chat') DEFAULT 'web',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `estimated_resolution_time` int(11) DEFAULT NULL COMMENT 'in hours',
  `actual_resolution_time` int(11) DEFAULT NULL COMMENT 'in hours',
  `customer_satisfaction` tinyint(1) DEFAULT NULL COMMENT '1-5 rating',
  `internal_notes` text DEFAULT NULL,
  `tags` varchar(500) DEFAULT NULL COMMENT 'comma-separated tags',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `due_date` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_number` (`ticket_number`),
  KEY `idx_email` (`email`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_category` (`category`),
  KEY `idx_assigned_to` (`assigned_to`),
  KEY `idx_department` (`department`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_due_date` (`due_date`),
  KEY `idx_status_priority` (`status`,`priority`),
  KEY `idx_email_status` (`email`,`status`),
  KEY `idx_customer_id` (`customer_id`),
  CONSTRAINT `fk_support_tickets_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket-Antworten
CREATE TABLE `ticket_replies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ticket_id` (`ticket_id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_replies_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_replies_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_replies_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kontaktnachrichten
CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `status` enum('new','read','replied','archived') DEFAULT 'new',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_contact_messages_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- DOMAIN-MANAGEMENT
-- =============================================================================

-- Domain-Registrierungen
CREATE TABLE `domain_registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `domain` varchar(255) NOT NULL,
  `purpose` varchar(50) NOT NULL DEFAULT 'other',
  `notes` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_domain` (`domain`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_domain_registrations_status_created` (`status`,`created_at`),
  KEY `idx_domain_registrations_user_status` (`user_id`,`status`),
  CONSTRAINT `domain_registrations_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Domains
CREATE TABLE `domains` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `domain_name` varchar(255) NOT NULL,
  `registrar` varchar(100) DEFAULT 'OVH',
  `registration_date` date DEFAULT NULL,
  `expiration_date` date DEFAULT NULL,
  `auto_renew` enum('y','n') DEFAULT 'y',
  `nameservers` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_domain` (`domain_name`),
  KEY `idx_status` (`status`),
  KEY `idx_expiration` (`expiration_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- INFRASTRUKTUR-TABELLEN
-- =============================================================================

-- Virtuelle Maschinen
CREATE TABLE `vms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vm_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `node` varchar(100) NOT NULL,
  `status` enum('running','stopped','suspended') DEFAULT 'stopped',
  `memory` int(11) NOT NULL,
  `cores` int(11) NOT NULL,
  `disk_size` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `mac_address` varchar(17) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_vm_id` (`vm_id`),
  KEY `idx_name` (`name`),
  KEY `idx_status` (`status`),
  KEY `idx_ip_address` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Websites
CREATE TABLE `websites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `domain` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `system_user` varchar(100) NOT NULL,
  `system_group` varchar(100) NOT NULL,
  `document_root` varchar(500) DEFAULT NULL,
  `hd_quota` int(11) DEFAULT 1000,
  `traffic_quota` int(11) DEFAULT 10000,
  `active` enum('y','n') DEFAULT 'y',
  `ssl_enabled` enum('y','n') DEFAULT 'n',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_domain` (`domain`),
  KEY `idx_ip` (`ip_address`),
  KEY `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datenbanken
CREATE TABLE `sm_databases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `database_name` varchar(255) NOT NULL,
  `database_user` varchar(255) NOT NULL,
  `database_type` enum('mysql','postgresql') DEFAULT 'mysql',
  `server_id` int(11) DEFAULT 1,
  `charset` varchar(50) DEFAULT 'utf8',
  `remote_access` enum('y','n') DEFAULT 'n',
  `active` enum('y','n') DEFAULT 'y',
  `website_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_db_name` (`database_name`),
  KEY `idx_user` (`database_user`),
  KEY `idx_active` (`active`),
  KEY `idx_website_id` (`website_id`),
  CONSTRAINT `sm_databases_ibfk_1` FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- E-Mail-Konten
CREATE TABLE `email_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email_address` varchar(255) NOT NULL,
  `login_name` varchar(255) NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `domain` varchar(255) NOT NULL,
  `quota_mb` int(11) DEFAULT 1000,
  `active` enum('y','n') DEFAULT 'y',
  `autoresponder` enum('y','n') DEFAULT 'n',
  `autoresponder_text` text DEFAULT NULL,
  `forward_to` varchar(255) DEFAULT NULL,
  `spam_filter` enum('y','n') DEFAULT 'y',
  `website_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_email` (`email_address`),
  KEY `idx_domain` (`domain`),
  KEY `idx_active` (`active`),
  KEY `idx_website_id` (`website_id`),
  CONSTRAINT `email_accounts_ibfk_1` FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SSL-Zertifikate
CREATE TABLE `ssl_certificates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `domain` varchar(255) NOT NULL,
  `certificate_path` varchar(500) DEFAULT NULL,
  `private_key_path` varchar(500) DEFAULT NULL,
  `certificate_authority` varchar(100) DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `expiration_date` date DEFAULT NULL,
  `auto_renew` enum('y','n') DEFAULT 'y',
  `status` enum('valid','expired','revoked','pending') DEFAULT 'pending',
  `website_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_website_id` (`website_id`),
  KEY `idx_domain` (`domain`),
  KEY `idx_expiration` (`expiration_date`),
  CONSTRAINT `ssl_certificates_ibfk_1` FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- IP-Adressen
CREATE TABLE `ips` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `subnet` varchar(64) NOT NULL,
  `ip_reverse` varchar(64) NOT NULL,
  `reverse` varchar(255) DEFAULT NULL,
  `ttl` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `subnet` (`subnet`,`ip_reverse`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================================================
-- SYSTEM-KONFIGURATION
-- =============================================================================

-- API-Zugangsdaten
CREATE TABLE `api_credentials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_name` varchar(100) NOT NULL,
  `endpoint` varchar(500) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `password_encrypted` text DEFAULT NULL,
  `api_key_encrypted` text DEFAULT NULL,
  `additional_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`additional_config`)),
  `active` enum('y','n') DEFAULT 'y',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_service` (`service_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Module
CREATE TABLE `modules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Systemeinstellungen
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','integer','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `is_public` enum('y','n') DEFAULT 'n',
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_setting_key` (`setting_key`),
  KEY `idx_updated_by` (`updated_by`),
  CONSTRAINT `system_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Einstellungen
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_title` varchar(255) NOT NULL DEFAULT 'Meine Seite',
  `logo_path` varchar(255) DEFAULT NULL,
  `favicon_path` varchar(255) DEFAULT NULL,
  `mode` enum('live','database') NOT NULL DEFAULT 'live',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- VIEWS FÜR ÜBERSICHTEN
-- =============================================================================

-- Benutzeraktivitäts-Übersicht
CREATE VIEW `user_activity_overview` AS
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
LEFT JOIN user_sessions s ON u.id = s.user_id AND s.is_active = 'y' AND s.expires_at > CURRENT_TIMESTAMP()
LEFT JOIN login_attempts la ON u.username = la.username AND CAST(la.created_at AS DATE) = CURDATE()
GROUP BY u.id;

-- VM-Übersicht
CREATE VIEW `vm_overview` AS
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
CREATE VIEW `website_overview` AS
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
INSERT INTO `users` (`username`, `email`, `password_hash`, `full_name`, `role`, `active`) VALUES
('admin', 'admin@your-server.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin', 'y');

-- Standard-Module
INSERT INTO `modules` (`name`, `is_active`) VALUES
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
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `is_public`) VALUES
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
INSERT INTO `settings` (`site_title`, `logo_path`, `favicon_path`, `mode`) VALUES
('Server Management System', NULL, NULL, 'live');

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
