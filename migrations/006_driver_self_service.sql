-- MOVA Migration 006 — Driver Self-Service: Checklists, Photos, Issue Reports
-- Depends on: trips (005), customers (003), vehicles (004), users (004)

CREATE TABLE IF NOT EXISTS `mova_trip_checklists` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `trip_id` BIGINT UNSIGNED NOT NULL,
    `check_type` ENUM('pre_trip', 'post_trip') NOT NULL,
    `submitted_by` BIGINT UNSIGNED NOT NULL,
    `submitted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `items` JSON NOT NULL COMMENT 'Array item checklist beserta status dan catatan',
    `overall_condition` ENUM('good', 'minor_issue', 'major_issue') NOT NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_trip_id` (`trip_id`),
    KEY `idx_check_type` (`check_type`),
    CONSTRAINT `fk_tc_trip` FOREIGN KEY (`trip_id`) REFERENCES `mova_trips` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tc_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `mova_users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mova_trip_photos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `trip_id` BIGINT UNSIGNED NOT NULL,
    `photo_type` ENUM('pre_trip', 'post_trip') NOT NULL,
    `position` ENUM('front', 'rear', 'left', 'right', 'interior', 'other') NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `uploaded_by` BIGINT UNSIGNED NOT NULL,
    `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_trip_id` (`trip_id`),
    CONSTRAINT `fk_tp_trip` FOREIGN KEY (`trip_id`) REFERENCES `mova_trips` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tp_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `mova_users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mova_issue_reports` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `vehicle_id` BIGINT UNSIGNED NOT NULL,
    `trip_id` BIGINT UNSIGNED NULL,
    `report_number` VARCHAR(30) NOT NULL COMMENT 'ISS-YYYY-NNNN',
    `reported_by` BIGINT UNSIGNED NOT NULL,
    `category` VARCHAR(50) NOT NULL COMMENT 'mesin, ac_kelistrikan, rem_kemudi, body, ban, lainnya',
    `description` TEXT NOT NULL,
    `severity` ENUM('low', 'medium', 'high', 'critical') NOT NULL,
    `status` ENUM('open', 'in_review', 'in_progress', 'resolved', 'closed') NOT NULL DEFAULT 'open',
    `photo_paths` JSON NULL,
    `resolved_at` TIMESTAMP NULL,
    `resolved_notes` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_report_number` (`report_number`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_vehicle_id` (`vehicle_id`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_ir_customer` FOREIGN KEY (`customer_id`) REFERENCES `mova_customers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ir_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `mova_vehicles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ir_trip` FOREIGN KEY (`trip_id`) REFERENCES `mova_trips` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_ir_reported_by` FOREIGN KEY (`reported_by`) REFERENCES `mova_users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
