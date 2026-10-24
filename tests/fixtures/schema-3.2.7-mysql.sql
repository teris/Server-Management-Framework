-- =============================================================================
-- INSTALLATION-OPTIMIERTE MYSQL-STRUKTUR FÜR SERVER MANAGEMENT SYSTEM
-- =============================================================================
-- Erstellt: 2025-09-03
-- Version: 2.0
-- Beschreibung: MySQL-Struktur für install.php ohne Standarddaten
-- =============================================================================

-- Datenbank erstellen (falls nicht vorhanden)
CREATE DATABASE IF NOT EXISTS `server_management` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `server_management`;

-- =============================================================================
-- TABELLEN ERSTELLEN
-- =============================================================================

-- 1. USERS TABLE (Admin-Benutzer)
CREATE TABLE `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(100) NOT NULL,
    `email` varchar(255) NOT NULL,
    `password_hash` varchar(255) NOT NULL,
    `full_name` varchar(255) DEFAULT NULL,
    `role` enum('admin','user','moderator') NOT NULL DEFAULT 'user',
    `active` enum('y','n') NOT NULL DEFAULT 'y',
    `last_login` datetime DEFAULT NULL,
    `failed_login_attempts` int(11) NOT NULL DEFAULT 0,
    `locked_until` datetime DEFAULT NULL,
    `password_changed_at` datetime DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`),
    KEY `role` (`role`),
    KEY `active` (`active`),
    KEY `last_login` (`last_login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. CUSTOMERS TABLE (Kunden-Benutzer)
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
    `status` enum('pending','active','suspended','deleted') NOT NULL DEFAULT 'pending',
    `email_verified_at` datetime DEFAULT NULL,
    `last_login` datetime DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`),
    KEY `status` (`status`),
    KEY `email_verified_at` (`email_verified_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. USER_PERMISSIONS TABLE
CREATE TABLE `user_permissions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `permission_type` enum('proxmox','ispconfig','ovh','ogp','admin','readonly') NOT NULL,
    `resource_id` varchar(255) DEFAULT NULL,
    `granted_by` int(11) DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_permission_resource` (`user_id`,`permission_type`,`resource_id`),
    KEY `user_id` (`user_id`),
    KEY `permission_type` (`permission_type`),
    CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `user_permissions_ibfk_2` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. USER_SESSIONS TABLE
CREATE TABLE `user_sessions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `session_id` varchar(128) NOT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_activity` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` datetime NOT NULL,
    `is_active` enum('y','n') NOT NULL DEFAULT 'y',
    `logout_reason` enum('manual','timeout','forced') DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `session_id` (`session_id`),
    KEY `user_id` (`user_id`),
    KEY `is_active` (`is_active`),
    KEY `expires_at` (`expires_at`),
    CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. LOGIN_ATTEMPTS TABLE
CREATE TABLE `login_attempts` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(100) NOT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    `success` enum('y','n') NOT NULL,
    `failure_reason` varchar(255) DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `username` (`username`),
    KEY `ip_address` (`ip_address`),
    KEY `success` (`success`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. CUSTOMER_LOGIN_LOGS TABLE
CREATE TABLE `customer_login_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `customer_id` int(11) NOT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    `success` tinyint(1) NOT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `customer_id` (`customer_id`),
    CONSTRAINT `customer_login_logs_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. VERIFICATION_TOKENS TABLE
CREATE TABLE `verification_tokens` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `customer_id` int(11) NOT NULL,
    `token` varchar(255) NOT NULL,
    `type` enum('email_verification','password_reset') NOT NULL,
    `expires_at` datetime NOT NULL,
    `used_at` datetime DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `customer_id` (`customer_id`),
    KEY `token` (`token`),
    KEY `type` (`type`),
    CONSTRAINT `verification_tokens_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. CUSTOMER_REMEMBER_TOKENS TABLE
CREATE TABLE `customer_remember_tokens` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `customer_id` int(11) NOT NULL,
    `token` varchar(64) NOT NULL,
    `expires_at` datetime NOT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `customer_id` (`customer_id`),
    CONSTRAINT `customer_remember_tokens_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. ACTIVITY_LOG TABLE
CREATE TABLE `activity_log` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `action` varchar(255) NOT NULL,
    `details` text DEFAULT NULL,
    `status` enum('success','error','pending','info') NOT NULL DEFAULT 'info',
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `action` (`action`),
    KEY `status` (`status`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. USER_ACTIVITIES TABLE
CREATE TABLE `user_activities` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `user_type` enum('customer','admin') NOT NULL,
    `activity_type` varchar(100) NOT NULL,
    `description` text NOT NULL,
    `related_id` int(11) DEFAULT NULL,
    `related_table` varchar(100) DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `user_type` (`user_type`),
    KEY `activity_type` (`activity_type`),
    KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. SUPPORT_TICKETS TABLE
CREATE TABLE `support_tickets` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `customer_id` int(11) DEFAULT NULL,
    `ticket_number` varchar(20) NOT NULL,
    `subject` varchar(255) NOT NULL,
    `message` text NOT NULL,
    `email` varchar(255) NOT NULL,
    `customer_name` varchar(255) DEFAULT NULL,
    `phone` varchar(50) DEFAULT NULL,
    `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    `status` enum('open','in_progress','waiting_customer','waiting_admin','resolved','closed') NOT NULL DEFAULT 'open',
    `category` enum('technical','billing','general','feature_request','bug_report','account') NOT NULL DEFAULT 'general',
    `assigned_to` int(11) DEFAULT NULL,
    `department` enum('support','billing','technical','sales') DEFAULT 'support',
    `source` enum('web','email','phone','chat') NOT NULL DEFAULT 'web',
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    `estimated_resolution_time` int(11) DEFAULT NULL,
    `actual_resolution_time` int(11) DEFAULT NULL,
    `customer_satisfaction` int(11) DEFAULT NULL,
    `internal_notes` text DEFAULT NULL,
    `tags` text DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `resolved_at` datetime DEFAULT NULL,
    `closed_at` datetime DEFAULT NULL,
    `due_date` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ticket_number` (`ticket_number`),
    KEY `email` (`email`),
    KEY `status` (`status`),
    KEY `priority` (`priority`),
    KEY `category` (`category`),
    KEY `assigned_to` (`assigned_to`),
    KEY `created_at` (`created_at`),
    KEY `status_priority` (`status`,`priority`),
    CONSTRAINT `support_tickets_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
    CONSTRAINT `support_tickets_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. TICKET_REPLIES TABLE
CREATE TABLE `ticket_replies` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `ticket_id` int(11) NOT NULL,
    `customer_id` int(11) DEFAULT NULL,
    `admin_id` int(11) DEFAULT NULL,
    `message` text NOT NULL,
    `is_internal` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ticket_id` (`ticket_id`),
    KEY `customer_id` (`customer_id`),
    KEY `admin_id` (`admin_id`),
    CONSTRAINT `ticket_replies_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
    CONSTRAINT `ticket_replies_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
    CONSTRAINT `ticket_replies_ibfk_3` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. CONTACT_MESSAGES TABLE
CREATE TABLE `contact_messages` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `email` varchar(255) NOT NULL,
    `subject` varchar(255) NOT NULL,
    `message` text NOT NULL,
    `customer_id` int(11) DEFAULT NULL,
    `status` enum('new','read','replied','archived') NOT NULL DEFAULT 'new',
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `email` (`email`),
    KEY `status` (`status`),
    KEY `created_at` (`created_at`),
    CONSTRAINT `contact_messages_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. DOMAIN_REGISTRATIONS TABLE
CREATE TABLE `domain_registrations` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `domain` varchar(255) NOT NULL,
    `purpose` text NOT NULL,
    `notes` text DEFAULT NULL,
    `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    `admin_notes` text DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    KEY `status` (`status`),
    CONSTRAINT `domain_registrations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. DOMAINS TABLE
CREATE TABLE `domains` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `domain_name` varchar(255) NOT NULL,
    `registrar` varchar(100) DEFAULT NULL,
    `registration_date` date DEFAULT NULL,
    `expiration_date` date DEFAULT NULL,
    `auto_renew` enum('y','n') NOT NULL DEFAULT 'y',
    `nameservers` text DEFAULT NULL,
    `status` varchar(100) DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `domain_name` (`domain_name`),
    KEY `status` (`status`),
    KEY `expiration_date` (`expiration_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. VMS TABLE (Virtuelle Maschinen)
CREATE TABLE `vms` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `vm_id` int(11) NOT NULL,
    `name` varchar(255) NOT NULL,
    `node` varchar(100) NOT NULL,
    `status` enum('running','stopped','suspended') NOT NULL DEFAULT 'stopped',
    `memory` int(11) NOT NULL,
    `cores` int(11) NOT NULL,
    `disk_size` int(11) NOT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `mac_address` varchar(17) DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `vm_id` (`vm_id`),
    KEY `name` (`name`),
    KEY `status` (`status`),
    KEY `ip_address` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. WEBSITES TABLE
CREATE TABLE `websites` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `domain` varchar(255) NOT NULL,
    `ip_address` varchar(45) NOT NULL,
    `system_user` varchar(100) NOT NULL,
    `system_group` varchar(100) NOT NULL,
    `document_root` varchar(255) DEFAULT NULL,
    `hd_quota` int(11) DEFAULT NULL,
    `traffic_quota` int(11) DEFAULT NULL,
    `active` enum('y','n') NOT NULL DEFAULT 'y',
    `ssl_enabled` enum('y','n') NOT NULL DEFAULT 'n',
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `domain` (`domain`),
    KEY `ip_address` (`ip_address`),
    KEY `active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. SM_DATABASES TABLE
CREATE TABLE `sm_databases` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `database_name` varchar(100) NOT NULL,
    `database_user` varchar(100) NOT NULL,
    `database_type` enum('mysql','postgresql') NOT NULL DEFAULT 'mysql',
    `server_id` int(11) DEFAULT NULL,
    `charset` varchar(50) DEFAULT 'utf8mb4',
    `remote_access` enum('y','n') NOT NULL DEFAULT 'n',
    `active` enum('y','n') NOT NULL DEFAULT 'y',
    `website_id` int(11) DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `database_name` (`database_name`),
    KEY `active` (`active`),
    CONSTRAINT `sm_databases_ibfk_1` FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. EMAIL_ACCOUNTS TABLE
CREATE TABLE `email_accounts` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `email_address` varchar(255) NOT NULL,
    `login_name` varchar(100) NOT NULL,
    `password_hash` varchar(255) DEFAULT NULL,
    `full_name` varchar(255) DEFAULT NULL,
    `domain` varchar(255) NOT NULL,
    `quota_mb` int(11) DEFAULT NULL,
    `active` enum('y','n') NOT NULL DEFAULT 'y',
    `autoresponder` enum('y','n') NOT NULL DEFAULT 'n',
    `autoresponder_text` text DEFAULT NULL,
    `forward_to` varchar(255) DEFAULT NULL,
    `spam_filter` enum('y','n') NOT NULL DEFAULT 'y',
    `website_id` int(11) DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `email_address` (`email_address`),
    KEY `active` (`active`),
    CONSTRAINT `email_accounts_ibfk_1` FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 20. SSL_CERTIFICATES TABLE
CREATE TABLE `ssl_certificates` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `domain` varchar(255) NOT NULL,
    `certificate_path` varchar(255) DEFAULT NULL,
    `private_key_path` varchar(255) DEFAULT NULL,
    `certificate_authority` varchar(100) DEFAULT NULL,
    `issue_date` date DEFAULT NULL,
    `expiration_date` date DEFAULT NULL,
    `auto_renew` enum('y','n') NOT NULL DEFAULT 'y',
    `status` enum('valid','expired','revoked','pending') NOT NULL DEFAULT 'pending',
    `website_id` int(11) DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `domain` (`domain`),
    KEY `status` (`status`),
    CONSTRAINT `ssl_certificates_ibfk_1` FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 21. IPS TABLE
CREATE TABLE `ips` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `subnet` varchar(18) NOT NULL,
    `ip_reverse` varchar(255) NOT NULL,
    `reverse` varchar(255) DEFAULT NULL,
    `ttl` int(11) DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `subnet` (`subnet`),
    KEY `ip_reverse` (`ip_reverse`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 22. API_CREDENTIALS TABLE
CREATE TABLE `api_credentials` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `service_name` varchar(100) NOT NULL,
    `endpoint` varchar(255) DEFAULT NULL,
    `username` varchar(100) DEFAULT NULL,
    `password_encrypted` text DEFAULT NULL,
    `api_key_encrypted` text DEFAULT NULL,
    `additional_config` text DEFAULT NULL,
    `active` enum('y','n') NOT NULL DEFAULT 'y',
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `service_name` (`service_name`),
    KEY `active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 23. MODULES TABLE
CREATE TABLE `modules` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 24. SYSTEM_SETTINGS TABLE
CREATE TABLE `system_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `setting_key` varchar(100) NOT NULL,
    `setting_value` text NOT NULL,
    `setting_type` enum('string','integer','boolean','json') NOT NULL DEFAULT 'string',
    `description` text DEFAULT NULL,
    `is_public` enum('y','n') NOT NULL DEFAULT 'n',
    `updated_by` int(11) DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `setting_key` (`setting_key`),
    CONSTRAINT `system_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 25. SETTINGS TABLE
CREATE TABLE `settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `site_title` varchar(255) NOT NULL,
    `logo_path` varchar(255) DEFAULT NULL,
    `favicon_path` varchar(255) DEFAULT NULL,
    `mode` enum('live','database') NOT NULL DEFAULT 'live',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;