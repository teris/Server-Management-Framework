-- SQL-Skript zur Aktualisierung der support_tickets Tabelle
-- Führen Sie dieses Skript in Ihrer Datenbank aus, um die Tabelle zu erweitern

-- Sichern Sie zuerst Ihre bestehenden Daten!
-- CREATE TABLE support_tickets_backup AS SELECT * FROM support_tickets;

-- Neue Spalten hinzufügen
ALTER TABLE `support_tickets` 
ADD COLUMN `ticket_number` varchar(20) NOT NULL DEFAULT '' AFTER `id`,
ADD COLUMN `customer_name` varchar(255) DEFAULT NULL AFTER `email`,
ADD COLUMN `phone` varchar(50) DEFAULT NULL AFTER `customer_name`,
ADD COLUMN `category` enum('technical','billing','general','feature_request','bug_report','account') DEFAULT 'general' AFTER `status`,
ADD COLUMN `assigned_to` int(11) DEFAULT NULL AFTER `category`,
ADD COLUMN `department` enum('support','billing','technical','sales') DEFAULT 'support' AFTER `assigned_to`,
ADD COLUMN `source` enum('web','email','phone','chat') DEFAULT 'web' AFTER `department`,
ADD COLUMN `estimated_resolution_time` int(11) DEFAULT NULL COMMENT 'in hours' AFTER `user_agent`,
ADD COLUMN `actual_resolution_time` int(11) DEFAULT NULL COMMENT 'in hours' AFTER `estimated_resolution_time`,
ADD COLUMN `customer_satisfaction` tinyint(1) DEFAULT NULL COMMENT '1-5 rating' AFTER `actual_resolution_time`,
ADD COLUMN `internal_notes` text DEFAULT NULL AFTER `customer_satisfaction`,
ADD COLUMN `tags` varchar(500) DEFAULT NULL COMMENT 'comma-separated tags' AFTER `internal_notes`,
ADD COLUMN `resolved_at` timestamp NULL DEFAULT NULL AFTER `updated_at`,
ADD COLUMN `closed_at` timestamp NULL DEFAULT NULL AFTER `resolved_at`,
ADD COLUMN `due_date` timestamp NULL DEFAULT NULL AFTER `closed_at`;

-- Status-Enum erweitern
ALTER TABLE `support_tickets` 
MODIFY COLUMN `status` enum('open','in_progress','waiting_customer','waiting_admin','resolved','closed') DEFAULT 'open';

-- Ticket-Nummern für bestehende Einträge generieren
UPDATE `support_tickets` 
SET `ticket_number` = CONCAT('T-', LPAD(id, 6, '0'))
WHERE `ticket_number` = '';

-- Indizes hinzufügen
ALTER TABLE `support_tickets` 
ADD UNIQUE KEY `ticket_number` (`ticket_number`),
ADD KEY `idx_email` (`email`),
ADD KEY `idx_status` (`status`),
ADD KEY `idx_priority` (`priority`),
ADD KEY `idx_category` (`category`),
ADD KEY `idx_assigned_to` (`assigned_to`),
ADD KEY `idx_department` (`department`),
ADD KEY `idx_created_at` (`created_at`),
ADD KEY `idx_due_date` (`due_date`),
ADD KEY `idx_status_priority` (`status`, `priority`),
ADD KEY `idx_email_status` (`email`, `status`);

-- AUTO_INCREMENT für ID-Spalte setzen (falls nicht bereits gesetzt)
ALTER TABLE `support_tickets` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- Beispiel-Update für bestehende Tickets (optional)
-- UPDATE `support_tickets` SET `category` = 'general' WHERE `category` IS NULL;
-- UPDATE `support_tickets` SET `source` = 'web' WHERE `source` IS NULL;
-- UPDATE `support_tickets` SET `department` = 'support' WHERE `department` IS NULL;

-- Überprüfung der Tabelle
DESCRIBE `support_tickets`;
