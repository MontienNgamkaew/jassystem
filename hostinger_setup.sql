-- ============================================================
-- Jasmine Active System – Full Database Setup
-- Run this ONCE on fresh Hostinger MySQL database
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── users ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `username`   VARCHAR(100) NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `full_name`  VARCHAR(255) DEFAULT '',
  `role`       ENUM('admin','user') NOT NULL DEFAULT 'user',
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin: username=admin  password=admin1234  (เปลี่ยนทันทีหลัง login)
INSERT IGNORE INTO `users` (`username`, `password`, `full_name`, `role`, `is_active`)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin', 1);

-- ── companies ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `companies` (
  `id`                     INT AUTO_INCREMENT PRIMARY KEY,
  `name_th`                VARCHAR(255) NOT NULL,
  `name_en`                VARCHAR(255) DEFAULT '',
  `address`                TEXT,
  `phone`                  VARCHAR(50) DEFAULT '',
  `email`                  VARCHAR(100) DEFAULT '',
  `tax_id`                 VARCHAR(50) DEFAULT '',
  `warranty_terms`         TEXT,
  `warranty_terms_invoice` TEXT,
  `warranty_terms_receipt` TEXT,
  `payment_terms`          VARCHAR(255) DEFAULT '',
  `logo_path`              VARCHAR(255) DEFAULT '',
  `stamp_enabled`          TINYINT(1) DEFAULT 0,
  `stamp_path`             VARCHAR(255) DEFAULT '',
  `show_date_in_signature` TINYINT(1) DEFAULT 1,
  `is_default`             TINYINT(1) DEFAULT 0,
  `created_at`             TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── products ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `products` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `code`       VARCHAR(50) NOT NULL UNIQUE,
  `name`       VARCHAR(255) NOT NULL,
  `unit`       VARCHAR(50) NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── packages ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `packages` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `code`        VARCHAR(50) NOT NULL UNIQUE,
  `name`        VARCHAR(255) NOT NULL,
  `description` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── package_items ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `package_items` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `package_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `quantity`   DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  FOREIGN KEY (`package_id`) REFERENCES `packages`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── customers ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `customers` (
  `id`      INT AUTO_INCREMENT PRIMARY KEY,
  `name`    VARCHAR(255) NOT NULL,
  `tax_id`  VARCHAR(20) DEFAULT '',
  `address` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── documents ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `documents` (
  `id`                INT AUTO_INCREMENT PRIMARY KEY,
  `doc_no`            VARCHAR(50) NOT NULL UNIQUE,
  `type`              ENUM('Quote','Invoice','Receipt') NOT NULL,
  `date`              DATE NOT NULL,
  `customer_id`       INT NOT NULL,
  `converted_from_id` INT NULL,
  `company_id`        INT NOT NULL,
  `total_amount`      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── document_items ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `document_items` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `document_id` INT NOT NULL,
  `item_name`   VARCHAR(255) NOT NULL,
  `quantity`    DECIMAL(10,2) NOT NULL,
  `unit`        VARCHAR(50) NOT NULL DEFAULT '',
  `price`       DECIMAL(10,2) NOT NULL,
  `total`       DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`document_id`) REFERENCES `documents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
