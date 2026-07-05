-- MOVA Migration 009 — Notifications, Audit Logs, Login Attempts
-- Depends on: users (004), customers (003)

CREATE TABLE IF NOT EXISTS `mova_notifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `customer_id` BIGINT UNSIGNED NULL,
    `type` VARCHAR(50) NOT NULL COMMENT 'vehicle_request, fuel_report, maintenance, issue, trip',
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `channel` ENUM('in_app', 'email', 'telegram') NOT NULL,
    `reference_type` VARCHAR(50) NULL COMMENT 'Nama tabel yang dirujuk (polymorphic)',
    `reference_id` BIGINT UNSIGNED NULL COMMENT 'ID record yang dirujuk (polymorphic)',
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `sent_at` TIMESTAMP NULL,
    `failed_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_is_read` (`is_read`),
    KEY `idx_type` (`type`),
    CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `mova_users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_notif_customer` FOREIGN KEY (`customer_id`) REFERENCES `mova_customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mova_audit_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `resource_type` VARCHAR(50) NULL,
    `resource_id` BIGINT UNSIGNED NULL,
    `ip_address` VARCHAR(45) NULL,
    `metadata` JSON NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_action` (`action`),
    KEY `idx_created_at` (`created_at`),
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `mova_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mova_login_attempts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(150) NOT NULL,
    `ip_address` VARCHAR(45) NULL,
    `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `is_success` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_email` (`email`),
    KEY `idx_attempted_at` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
