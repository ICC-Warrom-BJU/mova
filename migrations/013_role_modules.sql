-- MOVA Migration 013 — Role-Module Access Permissions
-- Depends on: 001_foundation.sql (mova_roles)

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

-- Seed: default permissions — super_admin gets everything
INSERT INTO `mova_role_modules` (`role_id`, `module_key`, `can_access`)
SELECT r.id, m.module_key, 1
FROM `mova_roles` r
CROSS JOIN (
    SELECT 'dashboard' AS module_key
    UNION ALL SELECT 'region' UNION ALL SELECT 'branch' UNION ALL SELECT 'customer'
    UNION ALL SELECT 'vehicle' UNION ALL SELECT 'user' UNION ALL SELECT 'config'
    UNION ALL SELECT 'vehicle_request' UNION ALL SELECT 'trip_log' UNION ALL SELECT 'issue_report'
    UNION ALL SELECT 'fuel_report' UNION ALL SELECT 'expense_report' UNION ALL SELECT 'maintenance'
    UNION ALL SELECT 'notifications' UNION ALL SELECT 'permission'
    UNION ALL SELECT 'customer_dashboard' UNION ALL SELECT 'customer_vehicle_request'
    UNION ALL SELECT 'customer_trip_log' UNION ALL SELECT 'customer_issue_report'
    UNION ALL SELECT 'customer_fuel_report' UNION ALL SELECT 'customer_expense_report'
    UNION ALL SELECT 'customer_maintenance' UNION ALL SELECT 'customer_my_vehicles'
) m
WHERE r.name = 'super_admin'
ON DUPLICATE KEY UPDATE `can_access` = 1;

-- management: mostly read-only monitoring, no operational input
INSERT INTO `mova_role_modules` (`role_id`, `module_key`, `can_access`)
SELECT r.id, m.module_key, m.can_access
FROM `mova_roles` r
CROSS JOIN (
    SELECT 'dashboard' AS module_key, 1 AS can_access
    UNION ALL SELECT 'region', 0 UNION ALL SELECT 'branch', 0
    UNION ALL SELECT 'customer', 1 UNION ALL SELECT 'vehicle', 1
    UNION ALL SELECT 'user', 1 UNION ALL SELECT 'config', 0
    UNION ALL SELECT 'vehicle_request', 1 UNION ALL SELECT 'trip_log', 1
    UNION ALL SELECT 'issue_report', 1 UNION ALL SELECT 'fuel_report', 1
    UNION ALL SELECT 'expense_report', 1 UNION ALL SELECT 'maintenance', 1
    UNION ALL SELECT 'notifications', 1 UNION ALL SELECT 'permission', 0
    UNION ALL SELECT 'customer_dashboard', 0 UNION ALL SELECT 'customer_vehicle_request', 0
    UNION ALL SELECT 'customer_trip_log', 0 UNION ALL SELECT 'customer_issue_report', 0
    UNION ALL SELECT 'customer_fuel_report', 0 UNION ALL SELECT 'customer_expense_report', 0
    UNION ALL SELECT 'customer_maintenance', 0 UNION ALL SELECT 'customer_my_vehicles', 0
) m
WHERE r.name = 'management'
ON DUPLICATE KEY UPDATE `can_access` = VALUES(`can_access`);

-- operation: full operational monitoring in their branch scope
INSERT INTO `mova_role_modules` (`role_id`, `module_key`, `can_access`)
SELECT r.id, m.module_key, m.can_access
FROM `mova_roles` r
CROSS JOIN (
    SELECT 'dashboard' AS module_key, 1 AS can_access
    UNION ALL SELECT 'region', 0 UNION ALL SELECT 'branch', 0
    UNION ALL SELECT 'customer', 0 UNION ALL SELECT 'vehicle', 1
    UNION ALL SELECT 'user', 0 UNION ALL SELECT 'config', 0
    UNION ALL SELECT 'vehicle_request', 1 UNION ALL SELECT 'trip_log', 1
    UNION ALL SELECT 'issue_report', 1 UNION ALL SELECT 'fuel_report', 1
    UNION ALL SELECT 'expense_report', 1 UNION ALL SELECT 'maintenance', 1
    UNION ALL SELECT 'notifications', 1 UNION ALL SELECT 'permission', 0
    UNION ALL SELECT 'customer_dashboard', 0 UNION ALL SELECT 'customer_vehicle_request', 0
    UNION ALL SELECT 'customer_trip_log', 0 UNION ALL SELECT 'customer_issue_report', 0
    UNION ALL SELECT 'customer_fuel_report', 0 UNION ALL SELECT 'customer_expense_report', 0
    UNION ALL SELECT 'customer_maintenance', 0 UNION ALL SELECT 'customer_my_vehicles', 0
) m
WHERE r.name = 'operation'
ON DUPLICATE KEY UPDATE `can_access` = VALUES(`can_access`);

-- marketing: customer & vehicle management, no operational
INSERT INTO `mova_role_modules` (`role_id`, `module_key`, `can_access`)
SELECT r.id, m.module_key, m.can_access
FROM `mova_roles` r
CROSS JOIN (
    SELECT 'dashboard' AS module_key, 1 AS can_access
    UNION ALL SELECT 'region', 1 UNION ALL SELECT 'branch', 1
    UNION ALL SELECT 'customer', 1 UNION ALL SELECT 'vehicle', 1
    UNION ALL SELECT 'user', 1 UNION ALL SELECT 'config', 0
    UNION ALL SELECT 'vehicle_request', 0 UNION ALL SELECT 'trip_log', 0
    UNION ALL SELECT 'issue_report', 0 UNION ALL SELECT 'fuel_report', 0
    UNION ALL SELECT 'expense_report', 0 UNION ALL SELECT 'maintenance', 0
    UNION ALL SELECT 'notifications', 0 UNION ALL SELECT 'permission', 0
    UNION ALL SELECT 'customer_dashboard', 0 UNION ALL SELECT 'customer_vehicle_request', 0
    UNION ALL SELECT 'customer_trip_log', 0 UNION ALL SELECT 'customer_issue_report', 0
    UNION ALL SELECT 'customer_fuel_report', 0 UNION ALL SELECT 'customer_expense_report', 0
    UNION ALL SELECT 'customer_maintenance', 0 UNION ALL SELECT 'customer_my_vehicles', 0
) m
WHERE r.name = 'marketing'
ON DUPLICATE KEY UPDATE `can_access` = VALUES(`can_access`);

-- customer roles: default all customer modules ON
INSERT INTO `mova_role_modules` (`role_id`, `module_key`, `can_access`)
SELECT r.id, m.module_key, 1
FROM `mova_roles` r
CROSS JOIN (
    SELECT 'customer_dashboard' AS module_key
    UNION ALL SELECT 'customer_vehicle_request' UNION ALL SELECT 'customer_trip_log'
    UNION ALL SELECT 'customer_issue_report' UNION ALL SELECT 'customer_fuel_report'
    UNION ALL SELECT 'customer_expense_report' UNION ALL SELECT 'customer_maintenance'
    UNION ALL SELECT 'customer_my_vehicles'
) m
WHERE r.layer = 'customer'
ON DUPLICATE KEY UPDATE `can_access` = 1;
