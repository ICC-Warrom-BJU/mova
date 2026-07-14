-- MOVA Test Seed Data

-- Roles
INSERT INTO `mova_roles` (`name`, `layer`, `description`) VALUES
('super_admin', 'company', 'Super Admin'),
('management', 'company', 'Management'),
('operation', 'company', 'Operation'),
('marketing', 'company', 'Marketing'),
('manager', 'customer', 'Manager'),
('supervisor', 'customer', 'Supervisor'),
('koordinator', 'customer', 'Koordinator'),
('driver', 'customer', 'Driver')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- Subscription Plans
INSERT INTO `mova_subscription_plans` (`name`, `max_users`, `allowed_modules`, `data_retention_days`) VALUES
('free', 10, '["vehicle_request","trip_log","driver_self_service","fuel_expense","maintenance_reminder"]', 90),
('premium', 50, '["vehicle_request","trip_log","driver_self_service","fuel_expense","maintenance_reminder","analytics","supervisor_approval","export"]', 365),
('enterprise', -1, '["vehicle_request","trip_log","driver_self_service","fuel_expense","maintenance_reminder","analytics","supervisor_approval","export","gps_monitoring","custom_branding","api_integration","custom_approval"]', -1)
ON DUPLICATE KEY UPDATE `max_users` = VALUES(`max_users`);

-- Region
INSERT INTO `mova_regions` (`id`, `name`, `code`) VALUES
(1, 'Sulawesi Selatan', 'SLSEL'),
(2, 'Sulawesi Barat', 'SLBAR')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Branches
INSERT INTO `mova_branches` (`id`, `region_id`, `name`, `code`) VALUES
(1, 1, 'Makassar', 'MKS'),
(2, 1, 'Maros', 'MRS')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Customers
INSERT INTO `mova_customers` (`id`, `branch_id`, `subscription_plan_id`, `name`, `code`, `total_units`, `is_active`) VALUES
(1, 1, 1, 'PT. Customer A', 'CUSTA', 5, 1),
(2, 2, 1, 'PT. Customer B', 'CUSTB', 3, 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Users (passwords are bcrypt of 'password123')
INSERT INTO `mova_users` (`id`, `role_id`, `customer_id`, `name`, `email`, `password`, `is_active`) VALUES
(1, 1, NULL, 'Super Admin', 'super@mova.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
(2, 2, NULL, 'Management User', 'management@mova.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
(3, 3, NULL, 'Operation User', 'operation@mova.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
(4, 4, NULL, 'Marketing User', 'marketing@mova.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
(5, 5, 1, 'Manager A', 'manager.a@mova.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
(6, 6, 1, 'Supervisor A', 'supervisor.a@mova.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
(7, 7, 1, 'Koordinator A', 'koordinator.a@mova.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
(8, 8, 1, 'Driver A1', 'driver.a1@mova.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
(9, 8, 1, 'Driver A2', 'driver.a2@mova.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
(10, 5, 2, 'Manager B', 'manager.b@mova.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
(11, 7, 2, 'Koordinator B', 'koordinator.b@mova.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
(12, 8, 2, 'Driver B1', 'driver.b1@mova.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Vehicles
INSERT INTO `mova_vehicles` (`id`, `customer_id`, `plate_number`, `brand`, `model`, `vehicle_type`, `current_km`, `status`, `is_active`) VALUES
(1, 1, 'DD 1234 AB', 'Toyota', 'Avanza', 'MPV', 50000, 'active', 1),
(2, 1, 'DD 5678 CD', 'Mitsubishi', 'L300', 'pickup', 75000, 'active', 1),
(3, 1, 'DD 9012 EF', 'Honda', 'Brio', 'MPV', 25000, 'maintenance', 1),
(4, 2, 'DP 3456 FG', 'Toyota', 'Innova', 'MPV', 30000, 'active', 1),
(5, 2, 'DP 7890 HI', 'Suzuki', 'Carry', 'pickup', 60000, 'active', 1)
ON DUPLICATE KEY UPDATE `brand` = VALUES(`brand`);

-- Config Options
INSERT INTO `mova_config_options` (`group_key`, `value`, `label`, `sort_order`) VALUES
('trip_purpose', 'dinas', 'Dinas', 1),
('trip_purpose', 'material', 'Material', 2),
('trip_purpose', 'karyawan', 'Karyawan', 3),
('trip_purpose', 'klien', 'Klien', 4),
('trip_purpose', 'lainnya', 'Lainnya', 5),
('expense_category', 'tol', 'Tol', 1),
('expense_category', 'parkir', 'Parkir', 2),
('expense_category', 'retribusi', 'Retribusi', 3),
('expense_category', 'penyeberangan', 'Penyeberangan', 4),
('expense_category', 'makan', 'Makan', 5),
('expense_category', 'lainnya', 'Lainnya', 6),
('issue_category', 'mesin', 'Mesin', 1),
('issue_category', 'ac_kelistrikan', 'AC / Kelistrikan', 2),
('issue_category', 'rem_kemudi', 'Rem / Kemudi', 3),
('issue_category', 'body', 'Body', 4),
('issue_category', 'ban', 'Ban', 5),
('issue_category', 'lainnya', 'Lainnya', 6)
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`), `sort_order` = VALUES(`sort_order`);

-- Role Modules (super_admin gets all)
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

-- Customer roles: all customer modules ON
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
