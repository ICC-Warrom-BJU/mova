-- Migration 010: Add photo columns for STNK & KIR documents to mova_vehicles
-- Run this once on your database before using the vehicle photo upload feature.

ALTER TABLE `mova_vehicles`
    ADD COLUMN `stnk_photo` VARCHAR(500) NULL COMMENT 'Relative path to STNK scan/photo file' AFTER `stnk_expiry`,
    ADD COLUMN `kir_photo`  VARCHAR(500) NULL COMMENT 'Relative path to KIR scan/photo file'  AFTER `kir_expiry`;
