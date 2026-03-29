-- Budget configuration schema for:
-- - financial_years
-- - budget_main_lines (auto total from sub-lines)
-- - budget_sub_lines
--
-- Import into your InfinityFree database (same DB used in includes/db.php).

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `financial_years` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `fy_name` VARCHAR(100) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_financial_years_name` (`fy_name`),
  KEY `idx_financial_years_dates` (`start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `budget_main_lines` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `financial_year_id` INT UNSIGNED NOT NULL,
  `main_line_name` VARCHAR(200) NOT NULL,
  `total_budget_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_budget_main_lines_fy` (`financial_year_id`),
  CONSTRAINT `fk_budget_main_lines_fy`
    FOREIGN KEY (`financial_year_id`) REFERENCES `financial_years` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `budget_sub_lines` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `budget_main_line_id` INT UNSIGNED NOT NULL,
  `sub_line_name` VARCHAR(200) NOT NULL,
  `budget_amount` DECIMAL(15,2) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_budget_sub_lines_main` (`budget_main_line_id`),
  CONSTRAINT `fk_budget_sub_lines_main`
    FOREIGN KEY (`budget_main_line_id`) REFERENCES `budget_main_lines` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note:
-- InfinityFree free hosting does not allow CREATE TRIGGER.
-- Totals are calculated in PHP/query logic and refreshed by endpoint code.

COMMIT;

