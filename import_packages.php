<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        header("Location: packages.php?msg=import_error");
        exit();
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (strtolower($ext) !== 'csv') {
        header("Location: packages.php?msg=import_error");
        exit();
    }
    
    $handle = fopen($file['tmp_name'], "r");
    if ($handle !== FALSE) {
        $successCount = 0;
        $skippedCount = 0;
        $isFirstRow = true;
        
        $pdo->beginTransaction();
        
        try {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if ($isFirstRow) {
                    $isFirstRow = false;
                    continue;
                }
                
                // Expected: 0: Pkg Code, 1: Pkg Name, 2: Desc, 3: Prod Code, 4: Qty
                if (count($data) >= 5) {
                    $pkgCode = trim($data[0]);
                    $pkgName = trim($data[1]);
                    $pkgDesc = trim($data[2]);
                    $prodCode = trim($data[3]);
                    $qty = floatval(trim($data[4]));
                    
                    if (empty($pkgCode) || empty($pkgName)) {
                        $skippedCount++;
                        continue;
                    }
                    
                    // 1. Get or Create Package
                    $stmt = $pdo->prepare("SELECT id FROM packages WHERE code = ?");
                    $stmt->execute([$pkgCode]);
                    $pkg = $stmt->fetch();
                    
                    if ($pkg) {
                        $packageId = $pkg['id'];
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO packages (code, name, description) VALUES (?, ?, ?)");
                        $stmt->execute([$pkgCode, $pkgName, $pkgDesc]);
                        $packageId = $pdo->lastInsertId();
                    }
                    
                    // 2. Add Product Item if Prod Code is provided
                    if (!empty($prodCode) && $qty > 0) {
                        $stmt = $pdo->prepare("SELECT id FROM products WHERE code = ?");
                        $stmt->execute([$prodCode]);
                        $product = $stmt->fetch();
                        
                        if ($product) {
                            $productId = $product['id'];
                            
                            // Check if item already exists in package
                            $stmt = $pdo->prepare("SELECT id FROM package_items WHERE package_id = ? AND product_id = ?");
                            $stmt->execute([$packageId, $productId]);
                            if (!$stmt->fetch()) {
                                $stmt = $pdo->prepare("INSERT INTO package_items (package_id, product_id, quantity) VALUES (?, ?, ?)");
                                $stmt->execute([$packageId, $productId, $qty]);
                            }
                        } else {
                            // Product not found, skip item
                        }
                    }
                    $successCount++;
                } else {
                    $skippedCount++;
                }
            }
            $pdo->commit();
            fclose($handle);
            
            header("Location: packages.php?msg=imported&success={$successCount}&skipped={$skippedCount}");
            exit();
            
        } catch (\PDOException $e) {
            $pdo->rollBack();
            fclose($handle);
            header("Location: packages.php?msg=import_error");
            exit();
        }
    } else {
        header("Location: packages.php?msg=import_error");
        exit();
    }
} else {
    header("Location: packages.php");
    exit();
}
