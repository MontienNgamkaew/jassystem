-- Migration v3: Add phone column to customers table
ALTER TABLE `customers` ADD COLUMN `phone` VARCHAR(50) NULL AFTER `address`;
