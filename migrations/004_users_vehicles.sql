-- MOVA Migration 004 — Users, Customer Configs, User Branch Access, Vehicles
-- Depends on: regions, branches, roles, customers, subscription_plans

CREATE TABLE IF NOT EXISTS `mova_users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_id` BIGINT UNSIGNED NOT NULL,
    `customer_id` BIGINT UNSIGNED NULL,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20) NULL,
    `telegram_chat_id` VARCHAR(50) NULL,
    `avatar` VARCHAR(255) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_login_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_email` (`email`),
    KEY `idx_role_id` (`role_id`),
    KEY `idx_customer_id` (`customer_id`),
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `mova_roles` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_users_customer` FOREIGN KEY (`customer_id`) REFERENCES `mova_customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mova_customer_configs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `max_users_override` INT NULL COMMENT 'NULL = ikuti plan',
    `enable_supervisor_approval` TINYINT(1) NOT NULL DEFAULT 0,
    `allowed_modules_override` JSON NULL COMMENT 'NULL = ikuti plan',
    `updated_by` BIGINT UNSIGNED NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_customer` (`customer_id`),
    KEY `idx_updated_by` (`updated_by`),
    CONSTRAINT `fk_config_customer` FOREIGN KEY (`customer_id`) REFERENCES `mova_customers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_config_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `mova_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mova_user_branch_access` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `branch_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_branch` (`user_id`, `branch_id`),
    KEY `idx_branch_id` (`branch_id`),
    CONSTRAINT `fk_uba_user` FOREIGN KEY (`user_id`) REFERENCES `mova_users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_uba_branch` FOREIGN KEY (`branch_id`) REFERENCES `mova_branches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mova_vehicles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `plate_number` VARCHAR(20) NOT NULL,
    `brand` VARCHAR(50) NOT NULL,
    `model` VARCHAR(50) NULL,
    `year` YEAR NULL,
    `color` VARCHAR(30) NULL,
    `vehicle_type` VARCHAR(30) NULL,
    `current_km` INT NOT NULL DEFAULT 0,
    `status` ENUM('active', 'maintenance', 'inactive') NOT NULL DEFAULT 'active',
    `stnk_expiry` DATE NULL,
    `kir_expiry` DATE NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_plate` (`plate_number`),
    KEY `idx_customer_id` (`customer_id`),
    CONSTRAINT `fk_vehicles_customer` FOREIGN KEY (`customer_id`) REFERENCES `mova_customers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
