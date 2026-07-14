-- MOVA Migration 014 — Ganti status operasional "Active" menjadi "Ready"
-- Idempoten & aman walau opsi 'ready' sudah ada:
--  - pindahkan semua kendaraan status 'active' -> 'ready'
--  - hapus opsi config 'active' (digantikan 'ready')
--  - pastikan opsi 'ready' ada (urutan pertama)

UPDATE `mova_vehicles` SET `status` = 'ready' WHERE `status` = 'active';

DELETE FROM `mova_config_options` WHERE `group_key` = 'vehicle_status' AND `value` = 'active';

INSERT INTO `mova_config_options` (`group_key`, `value`, `label`, `sort_order`) VALUES
('vehicle_status', 'ready', 'Ready', 1)
ON DUPLICATE KEY UPDATE `label` = 'Ready', `sort_order` = 1;
