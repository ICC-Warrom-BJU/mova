-- MOVA Migration 013 — Status Operasional kendaraan jadi konfigurable
-- Ubah kolom status dari ENUM (nilai terbatas) menjadi VARCHAR agar nilai baru
-- (mis. Ready, Not Ready) bisa ditambahkan lewat Konfigurasi.

ALTER TABLE `mova_vehicles` MODIFY COLUMN `status` VARCHAR(30) NOT NULL DEFAULT 'active';

-- Seed grup baru: vehicle_status (samakan dgn nilai lama supaya data existing tetap valid).
INSERT INTO `mova_config_options` (`group_key`, `value`, `label`, `sort_order`) VALUES
('vehicle_status', 'active', 'Active', 1),
('vehicle_status', 'maintenance', 'Maintenance', 2),
('vehicle_status', 'inactive', 'Inactive', 3)
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`), `sort_order` = VALUES(`sort_order`);
