-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Aug 14, 2025 at 06:51 PM
-- Server version: 11.8.2-MariaDB-1 from Debian
-- PHP Version: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `server_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `active_modules`
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
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `status` enum('success','error','pending','info') NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `api_credentials`
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
-- Table structure for table `backup_jobs`
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
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `status` enum('new','read','replied','archived') DEFAULT 'new',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_login_logs`
--

CREATE TABLE `customer_login_logs` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `success` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_remember_tokens`
--

CREATE TABLE `customer_remember_tokens` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_verification_tokens`
--

CREATE TABLE `customer_verification_tokens` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `domains`
--

CREATE TABLE `domains` (
  `id` int(11) NOT NULL,
  `domain_name` varchar(255) NOT NULL,
  `registrar` varchar(100) DEFAULT 'OVH',
  `registration_date` date DEFAULT NULL,
  `expiration_date` date DEFAULT NULL,
  `auto_renew` enum('y','n') DEFAULT 'y',
  `nameservers` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `domain_extensions`
--

CREATE TABLE `domain_extensions` (
  `id` int(11) NOT NULL,
  `tld` varchar(10) NOT NULL,
  `name` varchar(100) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `domain_registrations`
--

CREATE TABLE `domain_registrations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `domain` varchar(255) NOT NULL,
  `purpose` varchar(50) NOT NULL DEFAULT 'other',
  `notes` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_accounts`
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
-- Table structure for table `groups`
--

CREATE TABLE `groups` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `group_module_permissions`
--

CREATE TABLE `group_module_permissions` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `can_access` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ips`
--

CREATE TABLE `ips` (
  `id` int(10) UNSIGNED NOT NULL,
  `subnet` varchar(64) NOT NULL,
  `ip_reverse` varchar(64) NOT NULL,
  `reverse` varchar(255) DEFAULT NULL,
  `ttl` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
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
-- Table structure for table `modules`
--

CREATE TABLE `modules` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `module_configs`
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
-- Table structure for table `module_dependencies`
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
-- Table structure for table `module_permissions`
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
-- Table structure for table `network_config`
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
-- Table structure for table `server_resources`
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
-- Table structure for table `settings`
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
-- Table structure for table `sm_databases`
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
-- Table structure for table `ssl_certificates`
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
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` int(11) NOT NULL,
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
  `due_date` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
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
-- Table structure for table `ticket_replies`
--

CREATE TABLE `ticket_replies` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
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
-- Stand-in structure for view `user_activity_overview`
-- (See below for the actual view)
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
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `permission_type` enum('proxmox','ispconfig','ovh','ogp','admin','readonly') NOT NULL,
  `resource_id` varchar(255) DEFAULT NULL,
  `granted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
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
-- Table structure for table `verification_tokens`
--

CREATE TABLE `verification_tokens` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `type` enum('email_verification','password_reset') NOT NULL DEFAULT 'email_verification',
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vms`
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
-- Stand-in structure for view `vm_overview`
-- (See below for the actual view)
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
-- Table structure for table `websites`
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
-- Stand-in structure for view `website_overview`
-- (See below for the actual view)
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
-- Structure for view `user_activity_overview`
--
DROP TABLE IF EXISTS `user_activity_overview`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `user_activity_overview`  AS SELECT `u`.`id` AS `id`, `u`.`username` AS `username`, `u`.`full_name` AS `full_name`, `u`.`email` AS `email`, `u`.`role` AS `role`, `u`.`active` AS `active`, `u`.`last_login` AS `last_login`, `u`.`failed_login_attempts` AS `failed_login_attempts`, `u`.`locked_until` AS `locked_until`, count(distinct `s`.`id`) AS `active_sessions`, count(distinct `la`.`id`) AS `total_login_attempts`, sum(case when `la`.`success` = 'n' then 1 else 0 end) AS `failed_attempts_today`, `u`.`created_at` AS `created_at` FROM ((`users` `u` left join `user_sessions` `s` on(`u`.`id` = `s`.`user_id` and `s`.`is_active` = 'y' and `s`.`expires_at` > current_timestamp())) left join `login_attempts` `la` on(`u`.`username` = `la`.`username` and cast(`la`.`created_at` as date) = curdate())) GROUP BY `u`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `vm_overview`
--
DROP TABLE IF EXISTS `vm_overview`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `vm_overview`  AS SELECT `v`.`id` AS `id`, `v`.`vm_id` AS `vm_id`, `v`.`name` AS `name`, `v`.`node` AS `node`, `v`.`status` AS `status`, `v`.`memory` AS `memory`, `v`.`cores` AS `cores`, `v`.`ip_address` AS `ip_address`, `v`.`mac_address` AS `mac_address`, `w`.`domain` AS `website_domain`, count(distinct `d`.`id`) AS `database_count`, count(distinct `e`.`id`) AS `email_count`, `v`.`created_at` AS `created_at`, `v`.`updated_at` AS `updated_at` FROM (((`vms` `v` left join `websites` `w` on(`v`.`ip_address` = `w`.`ip_address`)) left join `sm_databases` `d` on(`w`.`id` = `d`.`website_id`)) left join `email_accounts` `e` on(`w`.`id` = `e`.`website_id`)) GROUP BY `v`.`id` ;

-- --------------------------------------------------------

--
-- Structure for view `website_overview`
--
DROP TABLE IF EXISTS `website_overview`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `website_overview`  AS SELECT `w`.`id` AS `id`, `w`.`domain` AS `domain`, `w`.`ip_address` AS `ip_address`, `w`.`system_user` AS `system_user`, `w`.`active` AS `active`, `v`.`name` AS `vm_name`, `v`.`status` AS `vm_status`, count(distinct `d`.`id`) AS `database_count`, count(distinct `e`.`id`) AS `email_count`, `s`.`status` AS `ssl_status`, `s`.`expiration_date` AS `ssl_expires`, `w`.`created_at` AS `created_at`, `w`.`updated_at` AS `updated_at` FROM ((((`websites` `w` left join `vms` `v` on(`w`.`ip_address` = `v`.`ip_address`)) left join `sm_databases` `d` on(`w`.`id` = `d`.`website_id`)) left join `email_accounts` `e` on(`w`.`id` = `e`.`website_id`)) left join `ssl_certificates` `s` on(`w`.`id` = `s`.`website_id`)) GROUP BY `w`.`id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `active_modules`
--
ALTER TABLE `active_modules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `module_name` (`module_name`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_activated_at` (`activated_at`);

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `api_credentials`
--
ALTER TABLE `api_credentials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_service` (`service_name`);

--
-- Indexes for table `backup_jobs`
--
ALTER TABLE `backup_jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type_target` (`type`,`target_id`),
  ADD KEY `idx_next_run` (`next_run`),
  ADD KEY `idx_active` (`active`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `customer_login_logs`
--
ALTER TABLE `customer_login_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `customer_remember_tokens`
--
ALTER TABLE `customer_remember_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `customer_verification_tokens`
--
ALTER TABLE `customer_verification_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Indexes for table `domains`
--
ALTER TABLE `domains`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_domain` (`domain_name`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_expiration` (`expiration_date`);

--
-- Indexes for table `domain_extensions`
--
ALTER TABLE `domain_extensions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tld` (`tld`),
  ADD KEY `active` (`active`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `idx_domain_extensions_active_tld` (`active`,`tld`);

--
-- Indexes for table `domain_registrations`
--
ALTER TABLE `domain_registrations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `domain` (`domain`),
  ADD KEY `status` (`status`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `idx_domain_registrations_status_created` (`status`,`created_at`),
  ADD KEY `idx_domain_registrations_user_status` (`user_id`,`status`);

--
-- Indexes for table `email_accounts`
--
ALTER TABLE `email_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_email` (`email_address`),
  ADD KEY `idx_domain` (`domain`),
  ADD KEY `idx_active` (`active`),
  ADD KEY `website_id` (`website_id`);

--
-- Indexes for table `groups`
--
ALTER TABLE `groups`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `group_module_permissions`
--
ALTER TABLE `group_module_permissions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ips`
--
ALTER TABLE `ips`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subnet` (`subnet`,`ip_reverse`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_ip` (`ip_address`),
  ADD KEY `idx_success` (`success`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `modules`
--
ALTER TABLE `modules`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `module_configs`
--
ALTER TABLE `module_configs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `module_config` (`module_name`,`config_key`),
  ADD KEY `idx_module_name` (`module_name`);

--
-- Indexes for table `module_dependencies`
--
ALTER TABLE `module_dependencies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `module_dependency` (`module_name`,`dependency_name`),
  ADD KEY `idx_module_name` (`module_name`),
  ADD KEY `idx_dependency_name` (`dependency_name`);

--
-- Indexes for table `module_permissions`
--
ALTER TABLE `module_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `module_permission` (`module_name`,`permission_name`),
  ADD KEY `idx_module_name` (`module_name`),
  ADD KEY `idx_required_role` (`required_role`);

--
-- Indexes for table `network_config`
--
ALTER TABLE `network_config`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vm_id` (`vm_id`),
  ADD KEY `idx_ip` (`ip_address`);

--
-- Indexes for table `server_resources`
--
ALTER TABLE `server_resources`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vm_timestamp` (`vm_id`,`timestamp`),
  ADD KEY `idx_timestamp` (`timestamp`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sm_databases`
--
ALTER TABLE `sm_databases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_db_name` (`database_name`),
  ADD KEY `idx_user` (`database_user`),
  ADD KEY `idx_active` (`active`),
  ADD KEY `website_id` (`website_id`);

--
-- Indexes for table `ssl_certificates`
--
ALTER TABLE `ssl_certificates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `website_id` (`website_id`),
  ADD KEY `idx_domain` (`domain`),
  ADD KEY `idx_expiration` (`expiration_date`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_number` (`ticket_number`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_assigned_to` (`assigned_to`),
  ADD KEY `idx_department` (`department`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `idx_status_priority` (`status`,`priority`),
  ADD KEY `idx_email_status` (`email`,`status`),
  ADD KEY `idx_customer_id` (`customer_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_setting_key` (`setting_key`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `ticket_replies`
--
ALTER TABLE `ticket_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_id` (`ticket_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_username` (`username`),
  ADD UNIQUE KEY `unique_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_active` (`active`),
  ADD KEY `idx_last_login` (`last_login`);

--
-- Indexes for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_permission` (`user_id`,`permission_type`,`resource_id`),
  ADD KEY `granted_by` (`granted_by`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_session_id` (`session_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `verification_tokens`
--
ALTER TABLE `verification_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `type` (`type`),
  ADD KEY `expires_at` (`expires_at`);

--
-- Indexes for table `vms`
--
ALTER TABLE `vms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_vm_id` (`vm_id`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `websites`
--
ALTER TABLE `websites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_domain` (`domain`),
  ADD KEY `idx_ip` (`ip_address`),
  ADD KEY `idx_active` (`active`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `active_modules`
--
ALTER TABLE `active_modules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
  MODIFY COLUMN `status` enum('success','error','pending','info') NOT NULL;

--
-- AUTO_INCREMENT for table `api_credentials`
--
ALTER TABLE `api_credentials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `backup_jobs`
--
ALTER TABLE `backup_jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_login_logs`
--
ALTER TABLE `customer_login_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_remember_tokens`
--
ALTER TABLE `customer_remember_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_verification_tokens`
--
ALTER TABLE `customer_verification_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `domains`
--
ALTER TABLE `domains`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `domain_extensions`
--
ALTER TABLE `domain_extensions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `domain_registrations`
--
ALTER TABLE `domain_registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_accounts`
--
ALTER TABLE `email_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `groups`
--
ALTER TABLE `groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `group_module_permissions`
--
ALTER TABLE `group_module_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ips`
--
ALTER TABLE `ips`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `modules`
--
ALTER TABLE `modules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `module_configs`
--
ALTER TABLE `module_configs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `module_dependencies`
--
ALTER TABLE `module_dependencies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `module_permissions`
--
ALTER TABLE `module_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `network_config`
--
ALTER TABLE `network_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `server_resources`
--
ALTER TABLE `server_resources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sm_databases`
--
ALTER TABLE `sm_databases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ssl_certificates`
--
ALTER TABLE `ssl_certificates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_replies`
--
ALTER TABLE `ticket_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `verification_tokens`
--
ALTER TABLE `verification_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vms`
--
ALTER TABLE `vms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `websites`
--
ALTER TABLE `websites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `backup_jobs`
--
ALTER TABLE `backup_jobs`
  ADD CONSTRAINT `backup_jobs_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD CONSTRAINT `fk_contact_messages_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `customer_login_logs`
--
ALTER TABLE `customer_login_logs`
  ADD CONSTRAINT `customer_login_logs_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_remember_tokens`
--
ALTER TABLE `customer_remember_tokens`
  ADD CONSTRAINT `customer_remember_tokens_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_verification_tokens`
--
ALTER TABLE `customer_verification_tokens`
  ADD CONSTRAINT `customer_verification_tokens_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `domain_registrations`
--
ALTER TABLE `domain_registrations`
  ADD CONSTRAINT `domain_registrations_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `email_accounts`
--
ALTER TABLE `email_accounts`
  ADD CONSTRAINT `email_accounts_ibfk_1` FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `network_config`
--
ALTER TABLE `network_config`
  ADD CONSTRAINT `network_config_ibfk_1` FOREIGN KEY (`vm_id`) REFERENCES `vms` (`vm_id`) ON DELETE CASCADE;

--
-- Constraints for table `server_resources`
--
ALTER TABLE `server_resources`
  ADD CONSTRAINT `server_resources_ibfk_1` FOREIGN KEY (`vm_id`) REFERENCES `vms` (`vm_id`) ON DELETE CASCADE;

--
-- Constraints for table `sm_databases`
--
ALTER TABLE `sm_databases`
  ADD CONSTRAINT `sm_databases_ibfk_1` FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ssl_certificates`
--
ALTER TABLE `ssl_certificates`
  ADD CONSTRAINT `ssl_certificates_ibfk_1` FOREIGN KEY (`website_id`) REFERENCES `websites` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD CONSTRAINT `fk_support_tickets_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD CONSTRAINT `system_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ticket_replies`
--
ALTER TABLE `ticket_replies`
  ADD CONSTRAINT `fk_replies_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_replies_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_replies_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_permissions_ibfk_2` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `verification_tokens`
--
ALTER TABLE `verification_tokens`
  ADD CONSTRAINT `fk_verification_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

-- Benutzeraktivitäten-Tabelle
CREATE TABLE IF NOT EXISTS user_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_type ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
    activity_type VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    related_id INT NULL,
    related_table VARCHAR(50) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_activities_user (user_id, user_type),
    INDEX idx_user_activities_type (activity_type),
    INDEX idx_user_activities_created (created_at),
    INDEX idx_user_activities_related (related_table, related_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
-- Update user_permissions table to add 'ogp' to permission_type enum
-- This script fixes the "Data truncated for column 'permission_type'" error

-- First, create a temporary table with the new structure
CREATE TABLE `user_permissions_new` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `permission_type` enum('proxmox','ispconfig','ovh','ogp','admin','readonly') NOT NULL,
  `resource_id` varchar(255) DEFAULT NULL,
  `granted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Copy data from old table to new table
INSERT INTO `user_permissions_new` 
SELECT * FROM `user_permissions`;

-- Drop the old table
DROP TABLE `user_permissions`;

-- Rename the new table to the original name
RENAME TABLE `user_permissions_new` TO `user_permissions`;

-- Recreate the indexes
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `permission_type` (`permission_type`),
  ADD KEY `granted_by` (`granted_by`);

-- Set auto increment
ALTER TABLE `user_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- Recreate the foreign key constraints
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_permissions_ibfk_2` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- Alternative method (if the above doesn't work):
-- ALTER TABLE `user_permissions` MODIFY COLUMN `permission_type` enum('proxmox','ispconfig','ovh','ogp','admin','readonly') NOT NULL;
