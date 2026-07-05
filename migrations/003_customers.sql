-- MOVA Migration 003 — Customers (depends on branches, subscription_plans)

CREATE TABLE IF NOT EXISTS `mova_customers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `branch_id` BIGINT UNSIGNED NOT NULL,
    `subscription_plan_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `code` VARCHAR(30) NOT NULL,
    `pic_name` VARCHAR(100) NULL,
    `pic_phone` VARCHAR(20) NULL,
    `pic_email` VARCHAR(150) NULL,
    `contract_start` DATE NULL,
    `contract_end` DATE NULL,
    `total_units` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_branch_id` (`branch_id`),
    KEY `idx_subscription_plan_id` (`subscription_plan_id`),
    CONSTRAINT `fk_customers_branch` FOREIGN KEY (`branch_id`) REFERENCES `mova_branches` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_customers_plan` FOREIGN KEY (`subscription_plan_id`) REFERENCES `mova_subscription_plans` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
