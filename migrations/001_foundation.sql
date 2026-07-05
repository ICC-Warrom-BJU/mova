-- MOVA Migration 001 — Foundation tables (no FK dependencies)
-- Regions, Roles, Subscription Plans

CREATE TABLE IF NOT EXISTS `mova_regions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(20) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mova_roles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL,
    `layer` ENUM('company', 'customer') NOT NULL,
    `description` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mova_subscription_plans` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL,
    `max_users` INT NOT NULL COMMENT '-1 = unlimited',
    `allowed_modules` JSON NOT NULL,
    `data_retention_days` INT NOT NULL COMMENT '-1 = unlimited',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed roles
INSERT INTO `mova_roles` (`name`, `layer`, `description`) VALUES
('super_admin', 'company', 'Konfigurasi platform, manage semua user, override subscription, feature flag control'),
('management', 'company', 'Dashboard global, laporan semua customer, read-only'),
('operation', 'company', 'Monitor operasional, kelola maintenance, lihat trip — hanya customer di branch-nya'),
('marketing', 'company', 'Onboarding customer, data kontrak commercial — hanya customer di branch-nya'),
('manager', 'customer', 'Dashboard & laporan lengkap, monitor semua aktivitas, tanpa chain approval'),
('supervisor', 'customer', 'Approve level 2 (jika diaktifkan Super Admin), monitor Koordinator & Driver'),
('koordinator', 'customer', 'Approve semua laporan & request dari Driver, assign tugas Driver, input semua laporan (superset Driver)'),
('driver', 'customer', 'Terima tugas, checklist kendaraan, input trip, laporan BBM & biaya, lapor kerusakan')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- Seed subscription plans
INSERT INTO `mova_subscription_plans` (`name`, `max_users`, `allowed_modules`, `data_retention_days`) VALUES
('free', 10, '["vehicle_request","trip_log","driver_self_service","fuel_expense","maintenance_reminder"]', 90),
('premium', 50, '["vehicle_request","trip_log","driver_self_service","fuel_expense","maintenance_reminder","analytics","supervisor_approval","export"]', 365),
('enterprise', -1, '["vehicle_request","trip_log","driver_self_service","fuel_expense","maintenance_reminder","analytics","supervisor_approval","export","gps_monitoring","custom_branding","api_integration","custom_approval"]', -1)
ON DUPLICATE KEY UPDATE `max_users` = VALUES(`max_users`), `allowed_modules` = VALUES(`allowed_modules`);
