-- MOVA Migration 012 — Config options (lookup values yang bisa ditambah kapan saja)
-- Grup: trip_purpose (Tipe Perjalanan), expense_category (Kategori Biaya),
--       issue_category (Kategori Kerusakan). Global (dikonfigurasi Super Admin).

CREATE TABLE IF NOT EXISTS `mova_config_options` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `group_key` VARCHAR(50) NOT NULL COMMENT 'trip_purpose, expense_category, issue_category, ...',
    `value` VARCHAR(100) NOT NULL COMMENT 'nilai yang disimpan di record',
    `label` VARCHAR(100) NOT NULL COMMENT 'teks yang ditampilkan',
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_group_value` (`group_key`, `value`),
    KEY `idx_group` (`group_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
('issue_category', 'lainnya', 'Lainnya', 6),
('vehicle_type', 'pickup', 'Pickup', 1),
('vehicle_type', 'box', 'Box', 2),
('vehicle_type', 'wingbox', 'Wingbox', 3),
('vehicle_type', 'fuso', 'Fuso', 4),
('vehicle_type', 'tronton', 'Tronton', 5),
('vehicle_type', 'trailer', 'Trailer', 6)
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`), `sort_order` = VALUES(`sort_order`);
