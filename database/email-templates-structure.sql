-- Compatibility snapshot; prefer bin/migrate.php. No template/account seeds.
CREATE TABLE IF NOT EXISTS `email_templates` (
  `id` INTEGER PRIMARY KEY AUTO_INCREMENT,
  `template_name` VARCHAR(255) NOT NULL UNIQUE,
  `subject` TEXT NOT NULL,
  `html_content` TEXT,
  `raw_content` TEXT,
  `variables` TEXT,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
