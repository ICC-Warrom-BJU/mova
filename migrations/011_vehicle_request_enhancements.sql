-- MOVA Migration 011 — Vehicle Request enhancements
-- Tambah: origin (asal), driver_option (with/without driver),
--         duration_type (full/half day), start_time & end_time (untuk half day).
-- assigned_driver_id sudah nullable (dibuat di 005), aman untuk request tanpa driver.

ALTER TABLE `mova_vehicle_requests`
    ADD COLUMN `origin` VARCHAR(150) NULL AFTER `destination`,
    ADD COLUMN `driver_option` ENUM('with_driver', 'without_driver') NOT NULL DEFAULT 'with_driver' AFTER `purpose`,
    ADD COLUMN `duration_type` ENUM('full_day', 'half_day') NOT NULL DEFAULT 'full_day' AFTER `driver_option`,
    ADD COLUMN `start_time` TIME NULL AFTER `return_date`,
    ADD COLUMN `end_time` TIME NULL AFTER `start_time`;
