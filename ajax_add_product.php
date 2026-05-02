<?php
require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $code = trim($_POST['code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $unit = trim($_POST['unit'] ?? '');
    $price = floatval($_POST['unit_price'] ?? 0);

    if (empty($code) || empty($name) || empty($unit)) {
        echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO products (code, name, unit, unit_price) VALUES (?, ?, ?, ?)");
        $stmt->execute([$code, $name, $unit, $price]);
        
        $newId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'product' => [
                'id' => $newId,
                'code' => $code,
                'name' => $name,
                'unit' => $unit,
                'unit_price' => $price
            ]
        ]);
    } catch (\PDOException $e) {
        // Likely a duplicate code
        echo json_encode(['success' => false, 'message' => 'รหัสสินค้านี้มีอยู่แล้วในระบบ']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
}
