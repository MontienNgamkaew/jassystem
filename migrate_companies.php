<?php
require_once 'db.php';

try {
    $pdo->beginTransaction();

    // 1. Create companies table
    $pdo->exec("CREATE TABLE IF NOT EXISTS companies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name_th VARCHAR(255) NOT NULL,
        name_en VARCHAR(255),
        address TEXT,
        phone VARCHAR(50),
        email VARCHAR(100),
        tax_id VARCHAR(50),
        warranty_terms TEXT,
        payment_terms VARCHAR(255),
        logo_path VARCHAR(255),
        stamp_enabled TINYINT(1) DEFAULT 0,
        stamp_path VARCHAR(255),
        show_date_in_signature TINYINT(1) DEFAULT 1,
        is_default TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Add company_id to documents (initially NULL to allow adding without error)
    $stmt = $pdo->query("SHOW COLUMNS FROM documents LIKE 'company_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE documents ADD COLUMN company_id INT NULL AFTER customer_id");
    }

    // 3. Check if we need to migrate settings.json
    $stmt = $pdo->query("SELECT COUNT(*) FROM companies");
    if ($stmt->fetchColumn() == 0) {
        $settings_file = __DIR__ . '/settings.json';
        if (file_exists($settings_file)) {
            $settings = json_decode(file_get_contents($settings_file), true);
            
            $stmt = $pdo->prepare("INSERT INTO companies (
                name_th, name_en, address, phone, email, tax_id, warranty_terms, payment_terms, logo_path, stamp_enabled, stamp_path, show_date_in_signature, is_default
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            
            $stmt->execute([
                $settings['company_name_th'] ?? 'Default Company',
                $settings['company_name_en'] ?? '',
                $settings['company_address'] ?? '',
                $settings['company_phone'] ?? '',
                $settings['company_email'] ?? '',
                $settings['company_tax_id'] ?? '',
                $settings['warranty_terms'] ?? '',
                $settings['payment_terms'] ?? '',
                $settings['logo_path'] ?? '',
                !empty($settings['stamp_enabled']) ? 1 : 0,
                $settings['stamp_path'] ?? '',
                !empty($settings['show_date_in_signature']) ? 1 : 0
            ]);
            
            $default_company_id = $pdo->lastInsertId();
            
            // 4. Update existing documents
            $pdo->exec("UPDATE documents SET company_id = $default_company_id WHERE company_id IS NULL");
            
            echo "Migrated settings.json to companies table.\n";
        } else {
            echo "settings.json not found. No data migrated.\n";
        }
    } else {
        // If companies exist but there are documents with NULL company_id, update them
        $stmt = $pdo->query("SELECT id FROM companies WHERE is_default = 1 LIMIT 1");
        $default_company_id = $stmt->fetchColumn();
        if (!$default_company_id) {
             $stmt = $pdo->query("SELECT id FROM companies LIMIT 1");
             $default_company_id = $stmt->fetchColumn();
        }
        
        if ($default_company_id) {
            $pdo->exec("UPDATE documents SET company_id = $default_company_id WHERE company_id IS NULL");
        }
        echo "Companies already exist. Assured documents have company_id.\n";
    }

    // 5. Make company_id NOT NULL and add Foreign Key (optional but good practice)
    $pdo->exec("ALTER TABLE documents MODIFY company_id INT NOT NULL");
    
    // Check if constraint exists before adding
    $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = 'jassystem' AND TABLE_NAME = 'documents' AND COLUMN_NAME = 'company_id' AND REFERENCED_TABLE_NAME IS NOT NULL");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE documents ADD CONSTRAINT fk_doc_company FOREIGN KEY (company_id) REFERENCES companies(id)");
    }

    $pdo->commit();
    echo "Migration completed successfully.\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "Migration failed: " . $e->getMessage() . "\n";
}
