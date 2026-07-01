<?php
require_once 'db.php';

// Auth guard - restrict to logged in admin (or allow if database is empty/first run, but for safety check user role)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    die("Unauthorized: Only administrator can run migrations.");
}

echo "<pre>";
echo "Starting Database Migration...\n";
echo "Connecting to DB: " . DB_NAME . "\n\n";

try {
    // 1. Check/Add include_vat to documents table
    $stmt = $pdo->query("SHOW COLUMNS FROM documents LIKE 'include_vat'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE documents ADD COLUMN include_vat TINYINT(1) DEFAULT 0 AFTER total_amount");
        echo "[SUCCESS] Added 'include_vat' column to 'documents' table.\n";
    } else {
        echo "[INFO] Column 'include_vat' already exists in 'documents' table.\n";
    }

    // 2. Check/Add show_date to documents table
    $stmt = $pdo->query("SHOW COLUMNS FROM documents LIKE 'show_date'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE documents ADD COLUMN show_date TINYINT(1) DEFAULT 1 AFTER include_vat");
        echo "[SUCCESS] Added 'show_date' column to 'documents' table.\n";
    } else {
        echo "[INFO] Column 'show_date' already exists in 'documents' table.\n";
    }

    // 3. Check/Add phone to customers table
    $stmt = $pdo->query("SHOW COLUMNS FROM customers LIKE 'phone'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE customers ADD COLUMN phone VARCHAR(50) NULL AFTER address");
        echo "[SUCCESS] Added 'phone' column to 'customers' table.\n";
    } else {
        echo "[INFO] Column 'phone' already exists in 'customers' table.\n";
    }

    // 4. Check/Add unit to document_items table
    $stmt = $pdo->query("SHOW COLUMNS FROM document_items LIKE 'unit'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE document_items ADD COLUMN unit VARCHAR(50) NOT NULL DEFAULT '' AFTER quantity");
        echo "[SUCCESS] Added 'unit' column to 'document_items' table.\n";
    } else {
        echo "[INFO] Column 'unit' already exists in 'document_items' table.\n";
    }

    // 5. Check/Add converted_from_id to documents table
    $stmt = $pdo->query("SHOW COLUMNS FROM documents LIKE 'converted_from_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE documents ADD COLUMN converted_from_id INT NULL AFTER customer_id");
        echo "[SUCCESS] Added 'converted_from_id' column to 'documents' table.\n";
    } else {
        echo "[INFO] Column 'converted_from_id' already exists in 'documents' table.\n";
    }

    // 6. Check/Add warranty_terms_invoice to companies table
    $stmt = $pdo->query("SHOW COLUMNS FROM companies LIKE 'warranty_terms_invoice'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE companies ADD COLUMN warranty_terms_invoice TEXT NULL AFTER warranty_terms");
        echo "[SUCCESS] Added 'warranty_terms_invoice' column to 'companies' table.\n";
    } else {
        echo "[INFO] Column 'warranty_terms_invoice' already exists in 'companies' table.\n";
    }

    // 7. Check/Add warranty_terms_receipt to companies table
    $stmt = $pdo->query("SHOW COLUMNS FROM companies LIKE 'warranty_terms_receipt'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE companies ADD COLUMN warranty_terms_receipt TEXT NULL AFTER warranty_terms_invoice");
        echo "[SUCCESS] Added 'warranty_terms_receipt' column to 'companies' table.\n";
    } else {
        echo "[INFO] Column 'warranty_terms_receipt' already exists in 'companies' table.\n";
    }

    // 8. Check/Add note to documents table
    $stmt = $pdo->query("SHOW COLUMNS FROM documents LIKE 'note'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE documents ADD COLUMN note TEXT NULL AFTER show_date");
        echo "[SUCCESS] Added 'note' column to 'documents' table.\n";
    } else {
        echo "[INFO] Column 'note' already exists in 'documents' table.\n";
    }

    echo "\nAll migrations checked/applied successfully.\n";
    echo "Please delete this file ('run_migration.php') from your server for security.\n";

} catch (Exception $e) {
    echo "\n[ERROR] Migration failed: " . $e->getMessage() . "\n";
}
echo "</pre>";
