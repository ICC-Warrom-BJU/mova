-- MOVA Migration 002 — Branches (depends on regions)

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
