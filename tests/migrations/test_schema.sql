-- MOVA Test Schema — gabungan semua migration

-- 001 Foundation
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
    `max_users` INT NOT NULL,
    `allowed_modules` JSON NOT NULL,
    `data_retention_days` INT NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 002 Branches
CREATE TABLE IF NOT EXISTS `mova_branches` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `region_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `code` VARCHAR(20) NOT NULL,
    `address` TEXT NULL,
    `phone` VARCHAR(20) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_region_id` (`region_id`),
    CONSTRAINT `fk_branches_region` FOREIGN KEY (`region_id`) REFERENCES `mova_regions` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 003 Customers
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

-- 004 Users, Vehicles
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
    `max_users_override` INT NULL,
    `enable_supervisor_approval` TINYINT(1) NOT NULL DEFAULT 0,
    `allowed_modules_override` JSON NULL,
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
    `stnk_photo` VARCHAR(500) NULL,
    `kir_photo` VARCHAR(500) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_plate` (`plate_number`),
    KEY `idx_customer_id` (`customer_id`),
    CONSTRAINT `fk_vehicles_customer` FOREIGN KEY (`customer_id`) REFERENCES `mova_customers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 005 Vehicle Requests & Trips
CREATE TABLE IF NOT EXISTS `mova_vehicle_requests` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `request_number` VARCHAR(30) NOT NULL,
    `requested_by` BIGINT UNSIGNED NOT NULL,
    `department` VARCHAR(80) NULL,
    `origin` VARCHAR(150) NULL,
    `destination` VARCHAR(150) NOT NULL,
    `purpose` TEXT NOT NULL,
    `driver_option` ENUM('with_driver', 'without_driver') NOT NULL DEFAULT 'with_driver',
    `duration_type` ENUM('full_day', 'half_day') NOT NULL DEFAULT 'full_day',
    `departure_date` DATE NOT NULL,
    `return_date` DATE NOT NULL,
    `start_time` TIME NULL,
    `end_time` TIME NULL,
    `passenger_count` INT NOT NULL DEFAULT 1,
    `vehicle_preference` VARCHAR(50) NULL,
    `assigned_vehicle_id` BIGINT UNSIGNED NULL,
    `assigned_driver_id` BIGINT UNSIGNED NULL,
    `status` ENUM('pending', 'approved_l1', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
    `approved_by_l1` BIGINT UNSIGNED NULL,
    `approved_at_l1` TIMESTAMP NULL,
    `approved_by_l2` BIGINT UNSIGNED NULL,
    `approved_at_l2` TIMESTAMP NULL,
    `rejected_by` BIGINT UNSIGNED NULL,
    `rejection_reason` TEXT NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_request_number` (`request_number`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_requested_by` (`requested_by`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_vr_customer` FOREIGN KEY (`customer_id`) REFERENCES `mova_customers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_vr_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `mova_users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_vr_vehicle` FOREIGN KEY (`assigned_vehicle_id`) REFERENCES `mova_vehicles` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_vr_driver` FOREIGN KEY (`assigned_driver_id`) REFERENCES `mova_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mova_trips` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `vehicle_request_id` BIGINT UNSIGNED NULL,
    `vehicle_id` BIGINT UNSIGNED NOT NULL,
    `driver_id` BIGINT UNSIGNED NOT NULL,
    `trip_number` VARCHAR(30) NOT NULL,
    `origin` VARCHAR(100) NOT NULL,
    `destination` VARCHAR(100) NOT NULL,
    `trip_date` DATE NOT NULL,
    `departure_time` TIME NULL,
    `return_time` TIME NULL,
    `km_start` INT NULL,
    `km_end` INT NULL,
    `distance_km` INT NULL,
    `purpose_type` VARCHAR(50) NOT NULL,
    `notes` TEXT NULL,
    `status` ENUM('draft', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'draft',
    `input_by` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_trip_number` (`trip_number`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_vehicle_id` (`vehicle_id`),
    KEY `idx_driver_id` (`driver_id`),
    KEY `idx_status` (`status`),
    KEY `idx_trip_date` (`trip_date`),
    CONSTRAINT `fk_trips_customer` FOREIGN KEY (`customer_id`) REFERENCES `mova_customers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_trips_request` FOREIGN KEY (`vehicle_request_id`) REFERENCES `mova_vehicle_requests` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_trips_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `mova_vehicles` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_trips_driver` FOREIGN KEY (`driver_id`) REFERENCES `mova_users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_trips_input_by` FOREIGN KEY (`input_by`) REFERENCES `mova_users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 006 Driver Self Service
CREATE TABLE IF NOT EXISTS `mova_trip_checklists` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `trip_id` BIGINT UNSIGNED NOT NULL,
    `check_type` ENUM('pre_trip', 'post_trip') NOT NULL,
    `submitted_by` BIGINT UNSIGNED NOT NULL,
    `submitted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `items` JSON NOT NULL,
    `overall_condition` ENUM('good', 'minor_issue', 'major_issue') NOT NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_trip_id` (`trip_id`),
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
    `report_number` VARCHAR(30) NOT NULL,
    `reported_by` BIGINT UNSIGNED NOT NULL,
    `category` VARCHAR(50) NOT NULL,
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

-- 007 Fuel & Expense Reports
CREATE TABLE IF NOT EXISTS `mova_fuel_reports` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `trip_id` BIGINT UNSIGNED NOT NULL,
    `vehicle_id` BIGINT UNSIGNED NOT NULL,
    `reported_by` BIGINT UNSIGNED NOT NULL,
    `fuel_date` DATE NOT NULL,
    `fuel_type` VARCHAR(20) NOT NULL,
    `liters` DECIMAL(8,2) NOT NULL,
    `price_per_liter` DECIMAL(10,2) NOT NULL,
    `total_cost` DECIMAL(12,2) NOT NULL,
    `km_at_refuel` INT NULL,
    `station_name` VARCHAR(100) NULL,
    `receipt_photo` VARCHAR(255) NULL,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `approved_by` BIGINT UNSIGNED NULL,
    `approved_at` TIMESTAMP NULL,
    `rejection_reason` TEXT NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_trip_id` (`trip_id`),
    KEY `idx_vehicle_id` (`vehicle_id`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_fr_customer` FOREIGN KEY (`customer_id`) REFERENCES `mova_customers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fr_trip` FOREIGN KEY (`trip_id`) REFERENCES `mova_trips` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_fr_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `mova_vehicles` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_fr_reported_by` FOREIGN KEY (`reported_by`) REFERENCES `mova_users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_fr_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `mova_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mova_expense_reports` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `trip_id` BIGINT UNSIGNED NOT NULL,
    `vehicle_id` BIGINT UNSIGNED NOT NULL,
    `reported_by` BIGINT UNSIGNED NOT NULL,
    `expense_date` DATE NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    `description` VARCHAR(200) NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `receipt_photo` VARCHAR(255) NULL,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `approved_by` BIGINT UNSIGNED NULL,
    `approved_at` TIMESTAMP NULL,
    `rejection_reason` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_customer_id` (`customer_id`),
    KEY `idx_trip_id` (`trip_id`),
    KEY `idx_vehicle_id` (`vehicle_id`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_er_customer` FOREIGN KEY (`customer_id`) REFERENCES `mova_customers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_er_trip` FOREIGN KEY (`trip_id`) REFERENCES `mova_trips` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_er_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `mova_vehicles` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_er_reported_by` FOREIGN KEY (`reported_by`) REFERENCES `mova_users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_er_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `mova_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 008 Maintenance
CREATE TABLE IF NOT EXISTS `mova_maintenance_schedules` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `vehicle_id` BIGINT UNSIGNED NOT NULL,
    `service_type` VARCHAR(80) NOT NULL,
    `trigger_type` ENUM('km_based', 'date_based') NOT NULL,
    `km_threshold` INT NULL,
    `scheduled_date` DATE NULL,
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

-- 009 Notifications, Audit Logs, Login Attempts
CREATE TABLE IF NOT EXISTS `mova_notifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `customer_id` BIGINT UNSIGNED NULL,
    `type` VARCHAR(50) NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `channel` ENUM('in_app', 'email', 'telegram') NOT NULL,
    `reference_type` VARCHAR(50) NULL,
    `reference_id` BIGINT UNSIGNED NULL,
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

-- 012 Config Options
CREATE TABLE IF NOT EXISTS `mova_config_options` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `group_key` VARCHAR(50) NOT NULL,
    `value` VARCHAR(100) NOT NULL,
    `label` VARCHAR(100) NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_group_value` (`group_key`, `value`),
    KEY `idx_group` (`group_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 013 Role Modules
CREATE TABLE IF NOT EXISTS `mova_role_modules` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_id` BIGINT UNSIGNED NOT NULL,
    `module_key` VARCHAR(100) NOT NULL,
    `can_access` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_role_module` (`role_id`, `module_key`),
    KEY `idx_role_id` (`role_id`),
    KEY `idx_module_key` (`module_key`),
    CONSTRAINT `fk_role_modules_role` FOREIGN KEY (`role_id`) REFERENCES `mova_roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
