-- =============================================================================
-- FRONTPANEL DATABASE STRUCTURE
-- =============================================================================

-- Kunden-Tabelle
CREATE TABLE IF NOT EXISTS `customers` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `email` varchar(255) NOT NULL,
    `password_hash` varchar(255) NOT NULL,
    `first_name` varchar(100) NOT NULL,
    `last_name` varchar(100) NOT NULL,
    `company` varchar(255) DEFAULT NULL,
    `phone` varchar(50) DEFAULT NULL,
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `is_verified` tinyint(1) NOT NULL DEFAULT 0,
    `verification_token` varchar(255) DEFAULT NULL,
    `verification_expires` datetime DEFAULT NULL,
    `remember_token` varchar(255) DEFAULT NULL,
    `last_login` datetime DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`),
    KEY `is_active` (`is_active`),
    KEY `is_verified` (`is_verified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Support-Tickets-Tabelle
CREATE TABLE IF NOT EXISTS `support_tickets` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `ticket_number` varchar(20) NOT NULL,
    `customer_id` int(11) NOT NULL,
    `subject` varchar(255) NOT NULL,
    `message` text NOT NULL,
    `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    `status` enum('open','in_progress','waiting_customer','resolved','closed') NOT NULL DEFAULT 'open',
    `category` enum('technical','billing','general','feature_request') NOT NULL DEFAULT 'general',
    `assigned_to` int(11) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `resolved_at` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ticket_number` (`ticket_number`),
    KEY `customer_id` (`customer_id`),
    KEY `status` (`status`),
    KEY `priority` (`priority`),
    KEY `created_at` (`created_at`),
    CONSTRAINT `fk_tickets_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ticket-Antworten-Tabelle
CREATE TABLE IF NOT EXISTS `ticket_replies` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `ticket_id` int(11) NOT NULL,
    `customer_id` int(11) DEFAULT NULL,
    `admin_id` int(11) DEFAULT NULL,
    `message` text NOT NULL,
    `is_internal` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `ticket_id` (`ticket_id`),
    KEY `customer_id` (`customer_id`),
    KEY `admin_id` (`admin_id`),
    CONSTRAINT `fk_replies_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_replies_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_replies_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- E-Mail-Verifikation-Tokens-Tabelle
CREATE TABLE IF NOT EXISTS `verification_tokens` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `customer_id` int(11) NOT NULL,
    `token` varchar(255) NOT NULL,
    `type` enum('email_verification','password_reset') NOT NULL DEFAULT 'email_verification',
    `expires_at` datetime NOT NULL,
    `used_at` datetime DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `token` (`token`),
    KEY `customer_id` (`customer_id`),
    KEY `type` (`type`),
    KEY `expires_at` (`expires_at`),
    CONSTRAINT `fk_verification_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Login-Versuche-Tabelle (für Brute-Force-Schutz)
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `email` varchar(255) NOT NULL,
    `ip_address` varchar(45) NOT NULL,
    `user_agent` text,
    `success` tinyint(1) NOT NULL DEFAULT 0,
    `attempted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `email` (`email`),
    KEY `ip_address` (`ip_address`),
    KEY `attempted_at` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indizes für bessere Performance
CREATE INDEX `idx_customers_email_active` ON `customers` (`email`, `is_active`);
CREATE INDEX `idx_tickets_status_priority` ON `support_tickets` (`status`, `priority`);
CREATE INDEX `idx_tickets_customer_status` ON `support_tickets` (`customer_id`, `status`);
CREATE INDEX `idx_replies_ticket_created` ON `ticket_replies` (`ticket_id`, `created_at`);
CREATE INDEX `idx_verification_customer_type` ON `verification_tokens` (`customer_id`, `type`);
CREATE INDEX `idx_login_attempts_email_time` ON `login_attempts` (`email`, `attempted_at`);

-- Beispiel-Daten für Tests (optional)
INSERT INTO `customers` (`email`, `password_hash`, `first_name`, `last_name`, `is_verified`) VALUES
('test@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Test', 'User', 1)
ON DUPLICATE KEY UPDATE `updated_at` = CURRENT_TIMESTAMP;
