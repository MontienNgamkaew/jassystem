-- Migration v2: Run once on existing installations
-- Adds unit column to document_items and converted_from_id to documents

ALTER TABLE `document_items` ADD COLUMN `unit` VARCHAR(50) NOT NULL DEFAULT '' AFTER `quantity`;
ALTER TABLE `documents` ADD COLUMN `converted_from_id` INT NULL AFTER `customer_id`;
