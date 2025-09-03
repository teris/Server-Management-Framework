-- =============================================================================
-- FRAMEWORK-SPEZIFISCHE MYSQL-STRUKTUR FÜR STANDALONE-SYSTEM
-- =============================================================================
-- Erstellt: 2025-09-03
-- Version: 2.0
-- Beschreibung: Minimale MySQL-Struktur für DatabaseOnlyFramework.php
-- Enthält nur die für das Framework notwendigen Tabellen
-- =============================================================================

-- Datenbank erstellen (falls nicht vorhanden)
CREATE DATABASE IF NOT EXISTS `server_management` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `server_management`;

-- =============================================================================
-- FRAMEWORK-TABELLEN (minimal für DatabaseOnlyFramework.php)
-- =============================================================================


-- 1. LOGIN_ATTEMPTS TABLE
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

-- 2. ACTIVITY_LOG TABLE
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

-- 3. SYSTEM_SETTINGS TABLE
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

-- 4. SETTINGS TABLE
CREATE TABLE `settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `site_title` varchar(255) NOT NULL,
    `logo_path` varchar(255) DEFAULT NULL,
    `favicon_path` varchar(255) DEFAULT NULL,
    `mode` enum('live','database') NOT NULL DEFAULT 'live',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- GRUNDDATEN FÜR FRAMEWORK (Standard-Einstellungen)
-- =============================================================================

-- Standard-Systemeinstellungen
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `is_public`) VALUES
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
INSERT INTO `settings` (`site_title`, `mode`) VALUES
('Server Management Framework', 'live');
