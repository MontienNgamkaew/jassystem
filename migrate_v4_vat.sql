-- Migration v4: Add missing columns to documents, customers, and document_items tables
-- Run this in phpMyAdmin if your database is missing these columns

-- 1. Add include_vat and show_date to documents table
ALTER TABLE `documents` ADD COLUMN `include_vat` TINYINT(1) DEFAULT 0 AFTER `total_amount`;
ALTER TABLE `documents` ADD COLUMN `show_date` TINYINT(1) DEFAULT 1 AFTER `include_vat`;

-- 2. Add phone to customers table (in case v3 was not run)
ALTER TABLE `customers` ADD COLUMN `phone` VARCHAR(50) NULL AFTER `address`;

-- 3. Add unit to document_items table (in case v2 was not run)
ALTER TABLE `document_items` ADD COLUMN `unit` VARCHAR(50) NOT NULL DEFAULT '' AFTER `quantity`;

-- 4. Add converted_from_id to documents table (in case v2 was not run)
ALTER TABLE `documents` ADD COLUMN `converted_from_id` INT NULL AFTER `customer_id`;
