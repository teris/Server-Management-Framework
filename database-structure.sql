-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Erstellungszeit: 17. Jul 2025 um 13:39
-- Server-Version: 10.11.11-MariaDB-0+deb12u1
-- PHP-Version: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `server_management`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `active_modules`
--

CREATE TABLE `active_modules` (
  `id` int(11) NOT NULL,
  `module_name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `activated_at` timestamp NULL DEFAULT NULL,
  `deactivated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `status` enum('success','error','pending') NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `api_credentials`
--

CREATE TABLE `api_credentials` (
  `id` int(11) NOT NULL,
  `service_name` varchar(100) NOT NULL,
  `endpoint` varchar(500) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `password_encrypted` text DEFAULT NULL,
  `api_key_encrypted` text DEFAULT NULL,
  `additional_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`additional_config`)),
  `active` enum('y','n') DEFAULT 'y',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `backup_jobs`
--

CREATE TABLE `backup_jobs` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('vm','database','files') NOT NULL,
  `target_id` int(11) DEFAULT NULL,
  `schedule_cron` varchar(100) DEFAULT NULL,
  `storage_location` varchar(500) DEFAULT NULL,
  `retention_days` int(11) DEFAULT 30,
  `compression` enum('none','lzo','gzip','zstd') DEFAULT 'zstd',
  `active` enum('y','n') DEFAULT 'y',
  `last_run` timestamp NULL DEFAULT NULL,
  `next_run` timestamp NULL DEFAULT NULL,
  `status` enum('success','failed','running','pending') DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `domains`
--

CREATE TABLE `domains` (
  `id` int(11) NOT NULL,
  `domain_name` varchar(255) NOT NULL,
  `registrar` varchar(100) DEFAULT 'OVH',
  `registration_date` date DEFAULT NULL,
  `expiration_date` date DEFAULT NULL,
  `auto_renew` enum('y','n') DEFAULT 'y',
  `nameservers` text DEFAULT NULL,
  `status` enum('active','pending','expired','suspended') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `email_accounts`
--

CREATE TABLE `email_accounts` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `groups`
--

CREATE TABLE `groups` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `groups`
--

INSERT INTO `groups` (`id`, `name`, `description`) VALUES
(1, 'admin', 'Alle sind Administratoren'),
(2, 'user', 'Einfacher Benutzer'),
(3, 'lesen', 'Kann nur daten abrufen');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `group_module_permissions`
--

CREATE TABLE `group_module_permissions` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `can_access` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `success` enum('y','n') NOT NULL,
  `failure_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `modules`
--

CREATE TABLE `modules` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `module_configs`
--

CREATE TABLE `module_configs` (
  `id` int(11) NOT NULL,
  `module_name` varchar(100) NOT NULL,
  `config_key` varchar(255) NOT NULL,
  `config_value` text DEFAULT NULL,
  `config_type` enum('string','integer','boolean','json') NOT NULL DEFAULT 'string',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `module_dependencies`
--

CREATE TABLE `module_dependencies` (
  `id` int(11) NOT NULL,
  `module_name` varchar(100) NOT NULL,
  `dependency_name` varchar(100) NOT NULL,
  `dependency_type` enum('required','optional','conflicts') NOT NULL DEFAULT 'required',
  `version_constraint` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `module_permissions`
--

CREATE TABLE `module_permissions` (
  `id` int(11) NOT NULL,
  `module_name` varchar(100) NOT NULL,
  `permission_name` varchar(255) NOT NULL,
  `permission_description` text DEFAULT NULL,
  `required_role` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `network_config`
--

CREATE TABLE `network_config` (
  `id` int(11) NOT NULL,
  `vm_id` int(11) NOT NULL,
  `interface_name` varchar(50) DEFAULT 'net0',
  `ip_address` varchar(45) DEFAULT NULL,
  `subnet_mask` varchar(45) DEFAULT '255.255.255.0',
  `gateway` varchar(45) DEFAULT NULL,
  `dns_servers` text DEFAULT NULL,
  `mac_address` varchar(17) DEFAULT NULL,
  `bridge` varchar(50) DEFAULT 'vmbr0',
  `vlan_tag` int(11) DEFAULT NULL,
  `active` enum('y','n') DEFAULT 'y',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `server_resources`
--

CREATE TABLE `server_resources` (
  `id` int(11) NOT NULL,
  `vm_id` int(11) DEFAULT NULL,
  `cpu_usage` decimal(5,2) DEFAULT NULL,
  `memory_usage` decimal(5,2) DEFAULT NULL,
  `disk_usage` decimal(5,2) DEFAULT NULL,
  `network_in` bigint(20) DEFAULT NULL,
  `network_out` bigint(20) DEFAULT NULL,
  `timestamp` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `site_title` varchar(255) NOT NULL DEFAULT 'Meine Seite',
  `logo_path` varchar(255) DEFAULT NULL,
  `favicon_path` varchar(255) DEFAULT NULL,
  `mode` enum('live','database') NOT NULL DEFAULT 'live'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `sm_databases`
--

CREATE TABLE `sm_databases` (
  `id` int(11) NOT NULL,
  `database_name` varchar(255) NOT NULL,
  `database_user` varchar(255) NOT NULL,
  `database_type` enum('mysql','postgresql') DEFAULT 'mysql',
  `server_id` int(11) DEFAULT 1,
  `charset` varchar(50) DEFAULT 'utf8',
  `remote_access` enum('y','n') DEFAULT 'n',
  `active` enum('y','n') DEFAULT 'y',
  `website_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `ssl_certificates`
--

CREATE TABLE `ssl_certificates` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','integer','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `is_public` enum('y','n') DEFAULT 'n',
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `role` varchar(255) DEFAULT NULL,
  `active` enum('y','n') DEFAULT 'y',
  `group_id` int(11) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `failed_login_attempts` int(11) DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `password_changed_at` timestamp NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `user_activity_overview`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `user_activity_overview` (
`id` int(11)
,`username` varchar(100)
,`full_name` varchar(255)
,`email` varchar(255)
,`role` varchar(255)
,`active` enum('y','n')
,`last_login` timestamp
,`failed_login_attempts` int(11)
,`locked_until` timestamp
,`active_sessions` bigint(21)
,`total_login_attempts` bigint(21)
,`failed_attempts_today` decimal(22,0)
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `user_permissions`
--

CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `permission_type` enum('proxmox','ispconfig','ovh','admin','readonly') NOT NULL,
  `resource_id` varchar(255) DEFAULT NULL,
  `granted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(128) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` timestamp NOT NULL,
  `is_active` enum('y','n') DEFAULT 'y',
  `logout_reason` enum('manual','timeout','forced') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `vms`
--

CREATE TABLE `vms` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `vm_overview`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `vm_overview` (
`id` int(11)
,`vm_id` int(11)
,`name` varchar(255)
,`node` varchar(100)
,`status` enum('running','stopped','suspended')
,`memory` int(11)
,`cores` int(11)
,`ip_address` varchar(45)
,`mac_address` varchar(17)
,`website_domain` varchar(255)
,`database_count` bigint(21)
,`email_count` bigint(21)
,`created_at` timestamp
,`updated_at` timestamp
);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `websites`
--

CREATE TABLE `websites` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `website_overview`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `website_overview` (
`id` int(11)
,`domain` varchar(255)
,`ip_address` varchar(45)
,`system_user` varchar(100)
,`active` enum('y','n')
,`vm_name` varchar(255)
,`vm_status` enum('running','stopped','suspended')
,`database_count` bigint(21)
,`email_count` bigint(21)
,`ssl_status` enum('valid','expired','revoked','pending')
,`ssl_expires` date
,`created_at` timestamp
,`updated_at` timestamp
);

-- --------------------------------------------------------

--
-- Struktur des Views `user_activity_overview`
--
DROP TABLE IF EXISTS `user_activity_overview`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `user_activity_overview`  AS SELECT `u`.`id` AS `id`, `u`.`username` AS `username`, `u`.`full_name` AS `full_name`, `u`.`email` AS `email`, `u`.`role` AS `role`, `u`.`active` AS `active`, `u`.`last_login` AS `last_login`, `u`.`failed_login_attempts` AS `failed_login_attempts`, `u`.`locked_until` AS `locked_until`, count(distinct `s`.`id`) AS `active_sessions`, count(distinct `la`.`id`) AS `total_login_attempts`, sum(case when `la`.`success` = 'n' then 1 else 0 end) AS `failed_attempts_today`, `u`.`created_at` AS `created_at` FROM ((`users` `u` left join `user_sessions` `s` on(`u`.`id` = `s`.`user_id` and `s`.`is_active` = 'y' and `s`.`expires_at` > current_timestamp())) left join `login_attempts` `la` on(`u`.`username` = `la`.`username` and cast(`la`.`created_at` as date) = curdate())) GROUP BY `u`.`id` ;

-- --------------------------------------------------------

--
-- Struktur des Views `vm_overview`
--
DROP TABLE IF EXISTS `vm_overview`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `vm_overview`  AS SELECT `v`.`id` AS `id`, `v`.`vm_id` AS `vm_id`, `v`.`name` AS `name`, `v`.`node` AS `node`, `v`.`status` AS `status`, `v`.`memory` AS `memory`, `v`.`cores` AS `cores`, `v`.`ip_address` AS `ip_address`, `v`.`mac_address` AS `mac_address`, `w`.`domain` AS `website_domain`, count(distinct `d`.`id`) AS `database_count`, count(distinct `e`.`id`) AS `email_count`, `v`.`created_at` AS `created_at`, `v`.`updated_at` AS `updated_at` FROM (((`vms` `v` left join `websites` `w` on(`v`.`ip_address` = `w`.`ip_address`)) left join `sm_databases` `d` on(`w`.`id` = `d`.`website_id`)) left join `email_accounts` `e` on(`w`.`id` = `e`.`website_id`)) GROUP BY `v`.`id` ;

-- --------------------------------------------------------

--
-- Struktur des Views `website_overview`
--
DROP TABLE IF EXISTS `website_overview`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `website_overview`  AS SELECT `w`.`id` AS `id`, `w`.`domain` AS `domain`, `w`.`ip_address` AS `ip_address`, `w`.`system_user` AS `system_user`, `w`.`active` AS `active`, `v`.`name` AS `vm_name`, `v`.`status` AS `vm_status`, count(distinct `d`.`id`) AS `database_count`, count(distinct `e`.`id`) AS `email_count`, `s`.`status` AS `ssl_status`, `s`.`expiration_date` AS `ssl_expires`, `w`.`created_at` AS `created_at`, `w`.`updated_at` AS `updated_at` FROM ((((`websites` `w` left join `vms` `v` on(`w`.`ip_address` = `v`.`ip_address`)) left join `sm_databases` `d` on(`w`.`id` = `d`.`website_id`)) left join `email_accounts` `e` on(`w`.`id` = `e`.`website_id`)) left join `ssl_certificates` `s` on(`w`.`id` = `s`.`website_id`)) GROUP BY `w`.`id` ;

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `active_modules`
--
ALTER TABLE `active_modules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `module_name` (`module_name`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_activated_at` (`activated_at`);

--
-- Indizes für die Tabelle `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indizes für die Tabelle `api_credentials`
--
ALTER TABLE `api_credentials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_service` (`service_name`);

--
-- Indizes für die Tabelle `backup_jobs`
--
ALTER TABLE `backup_jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type_target` (`type`,`target_id`),
  ADD KEY `idx_next_run` (`next_run`),
  ADD KEY `idx_active` (`active`),
  ADD KEY `created_by` (`created_by`);

--
-- Indizes für die Tabelle `domains`
--
ALTER TABLE `domains`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_domain` (`domain_name`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_expiration` (`expiration_date`);

--
-- Indizes für die Tabelle `email_accounts`
--
ALTER TABLE `email_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_email` (`email_address`),
  ADD KEY `idx_domain` (`domain`),
  ADD KEY `idx_active` (`active`),
  ADD KEY `website_id` (`website_id`);

--
-- Indizes für die Tabelle `groups`
--
ALTER TABLE `groups`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `group_module_permissions`
--
ALTER TABLE `group_module_permissions`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_ip` (`ip_address`),
  ADD KEY `idx_success` (`success`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indizes für die Tabelle `modules`
--
ALTER TABLE `modules`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `module_configs`
--
ALTER TABLE `module_configs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `module_config` (`module_name`,`config_key`),
  ADD KEY `idx_module_name` (`module_name`);

--
-- Indizes für die Tabelle `module_dependencies`
--
ALTER TABLE `module_dependencies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `module_dependency` (`module_name`,`dependency_name`),
  ADD KEY `idx_module_name` (`module_name`),
  ADD KEY `idx_dependency_name` (`dependency_name`);

--
-- Indizes für die Tabelle `module_permissions`
--
ALTER TABLE `module_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `module_permission` (`module_name`,`permission_name`),
  ADD KEY `idx_module_name` (`module_name`),
  ADD KEY `idx_required_role` (`required_role`);

--
-- Indizes für die Tabelle `network_config`
--
ALTER TABLE `network_config`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vm_id` (`vm_id`),
  ADD KEY `idx_ip` (`ip_address`);

--
-- Indizes für die Tabelle `server_resources`
--
ALTER TABLE `server_resources`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vm_timestamp` (`vm_id`,`timestamp`),
  ADD KEY `idx_timestamp` (`timestamp`);

--
-- Indizes für die Tabelle `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `sm_databases`
--
ALTER TABLE `sm_databases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_db_name` (`database_name`),
  ADD KEY `idx_user` (`database_user`),
  ADD KEY `idx_active` (`active`),
  ADD KEY `website_id` (`website_id`);

--
-- Indizes für die Tabelle `ssl_certificates`
--
ALTER TABLE `ssl_certificates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `website_id` (`website_id`),
  ADD KEY `idx_domain` (`domain`),
  ADD KEY `idx_expiration` (`expiration_date`);

--
-- Indizes für die Tabelle `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_setting_key` (`setting_key`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indizes für die Tabelle `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_username` (`username`),
  ADD UNIQUE KEY `unique_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_active` (`active`),
  ADD KEY `idx_last_login` (`last_login`);

--
-- Indizes für die Tabelle `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_permission` (`user_id`,`permission_type`,`resource_id`),
  ADD KEY `granted_by` (`granted_by`);

--
-- Indizes für die Tabelle `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_session_id` (`session_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indizes für die Tabelle `vms`
--
ALTER TABLE `vms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_vm_id` (`vm_id`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_status` (`status`);

--
-- Indizes für die Tabelle `websites`
--
ALTER TABLE `websites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_domain` (`domain`),
  ADD KEY `idx_ip` (`ip_address`),
  ADD KEY `idx_active` (`active`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `active_modules`
--
ALTER TABLE `active_modules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `api_credentials`
--
ALTER TABLE `api_credentials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `backup_jobs`
--
ALTER TABLE `backup_jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `domains`
--
ALTER TABLE `domains`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `email_accounts`
--
ALTER TABLE `email_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `groups`
--
ALTER TABLE `groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT für Tabelle `group_module_permissions`
--
ALTER TABLE `group_module_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `modules`
--
ALTER TABLE `modules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `module_configs`
--
ALTER TABLE `module_configs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `module_dependencies`
--
ALTER TABLE `module_dependencies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `module_permissions`
--
ALTER TABLE `module_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `network_config`
--
ALTER TABLE `network_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `server_resources`
--
ALTER TABLE `server_resources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `sm_databases`
--
ALTER TABLE `sm_databases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `ssl_certificates`
--
ALTER TABLE `ssl_certificates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `vms`
--
ALTER TABLE `vms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `websites`
--
ALTER TABLE `websites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `backup_jobs`
--
ALTER TABLE `backup_jobs`
  ADD CONSTRAINT `backup_jobs_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `email_accounts`
--
ALTER TABLE `email_accounts`
  ADD CONSTRAINT `email_accounts_ibfk_1` FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `network_config`
--
ALTER TABLE `network_config`
  ADD CONSTRAINT `network_config_ibfk_1` FOREIGN KEY (`vm_id`) REFERENCES `vms` (`vm_id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `server_resources`
--
ALTER TABLE `server_resources`
  ADD CONSTRAINT `server_resources_ibfk_1` FOREIGN KEY (`vm_id`) REFERENCES `vms` (`vm_id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `sm_databases`
--
ALTER TABLE `sm_databases`
  ADD CONSTRAINT `sm_databases_ibfk_1` FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `ssl_certificates`
--
ALTER TABLE `ssl_certificates`
  ADD CONSTRAINT `ssl_certificates_ibfk_1` FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `system_settings`
--
ALTER TABLE `system_settings`
  ADD CONSTRAINT `system_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_permissions_ibfk_2` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
