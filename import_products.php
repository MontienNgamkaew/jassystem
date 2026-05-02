<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    // Check if file is uploaded without errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        header("Location: products.php?msg=import_error");
        exit();
    }
    
    // Check file extension
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (strtolower($ext) !== 'csv') {
        header("Location: products.php?msg=import_error");
        exit();
    }
    
    $handle = fopen($file['tmp_name'], "r");
    if ($handle !== FALSE) {
        $successCount = 0;
        $skippedCount = 0;
        $isFirstRow = true;
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Skip header
            if ($isFirstRow) {
                $isFirstRow = false;
                continue;
            }
            
            // Expected: 0: Code, 1: Name, 2: Unit, 3: Price
            if (count($data) >= 4) {
                $code = trim($data[0]);
                $name = trim($data[1]);
                $unit = trim($data[2]);
                $price = floatval(trim($data[3]));
                
                if (empty($code) || empty($name)) {
                    $skippedCount++;
                    continue;
                }
                
                try {
                    // Use INSERT IGNORE or check exists to prevent duplicate codes
                    $stmt = $pdo->prepare("SELECT id FROM products WHERE code = ?");
                    $stmt->execute([$code]);
                    if ($stmt->fetch()) {
                        // Skip or update? We'll skip for now.
                        $skippedCount++;
                        continue;
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO products (code, name, unit, unit_price) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$code, $name, $unit, $price]);
                    $successCount++;
                } catch (\PDOException $e) {
                    $skippedCount++;
                }
            } else {
                $skippedCount++;
            }
        }
        fclose($handle);
        
        header("Location: products.php?msg=imported&success={$successCount}&skipped={$skippedCount}");
        exit();
    } else {
        header("Location: products.php?msg=import_error");
        exit();
    }
} else {
    header("Location: products.php");
    exit();
}
