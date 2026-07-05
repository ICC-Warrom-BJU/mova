-- MOVA Migration 008 — Maintenance Schedules & Logs
-- Depends on: customers (003), vehicles (004), users (004)

CREATE TABLE IF NOT EXISTS `mova_maintenance_schedules` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `vehicle_id` BIGINT UNSIGNED NOT NULL,
    `service_type` VARCHAR(80) NOT NULL COMMENT 'e.g. Ganti Oli, Servis 20.000 KM',
    `trigger_type` ENUM('km_based', 'date_based') NOT NULL,
    `km_threshold` INT NULL COMMENT 'KM batas servis, jika trigger km_based',
    `scheduled_date` DATE NULL COMMENT 'Tanggal jadwal, jika trigger date_based',
    `reminder_days_before` INT NOT NULL DEFAULT 7,
    `status` ENUM('scheduled', 'overdue', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled',
    `notes` TEXT NULL,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_vehicle_id` (`vehicle_id`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_ms_customer` FOREIGN KEY (`customer_id`) REFERENCES `mova_customers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ms_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `mova_vehicles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ms_created_by` FOREIGN KEY (`created_by`) REFERENCES `mova_users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mova_maintenance_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `schedule_id` BIGINT UNSIGNED NULL,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `vehicle_id` BIGINT UNSIGNED NOT NULL,
    `service_type` VARCHAR(80) NOT NULL,
    `service_date` DATE NOT NULL,
    `km_at_service` INT NULL,
    `workshop_name` VARCHAR(100) NULL,
    `cost` DECIMAL(12,2) NULL,
    `notes` TEXT NULL,
    `next_service_km` INT NULL,
    `next_service_date` DATE NULL,
    `logged_by` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_schedule_id` (`schedule_id`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_vehicle_id` (`vehicle_id`),
    CONSTRAINT `fk_ml_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `mova_maintenance_schedules` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_ml_customer` FOREIGN KEY (`customer_id`) REFERENCES `mova_customers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ml_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `mova_vehicles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ml_logged_by` FOREIGN KEY (`logged_by`) REFERENCES `mova_users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
