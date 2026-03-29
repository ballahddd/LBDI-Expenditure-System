-- Run after budget_schema.sql (same database).
-- Active financial year + expenditure records.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `app_settings` (
  `setting_key` VARCHAR(64) NOT NULL,
  `setting_value` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expenditures` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sub_line_id` INT UNSIGNED NOT NULL,
  `expense_date` DATE NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `description` VARCHAR(500) NOT NULL,
  `user_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_expenditures_sub` (`sub_line_id`),
  KEY `idx_expenditures_date` (`expense_date`),
  CONSTRAINT `fk_expenditures_sub`
    FOREIGN KEY (`sub_line_id`) REFERENCES `budget_sub_lines` (`id`)
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
