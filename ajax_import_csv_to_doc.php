<?php
session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์']);
        exit;
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    if (strtolower($ext) !== 'csv') {
        echo json_encode(['success' => false, 'message' => 'กรุณาอัปโหลดไฟล์นามสกุล CSV เท่านั้น']);
        exit;
    }
    
    $handle = fopen($file['tmp_name'], "r");
    if ($handle !== FALSE) {
        $items = [];
        $isFirstRow = true;
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if ($isFirstRow) {
                $isFirstRow = false;
                continue;
            }
            
            // Expected: 0: Code, 1: Name, 2: Unit, 3: Price, 4: Quantity
            if (count($data) >= 5) {
                $code = trim($data[0]);
                $name = trim($data[1]);
                $unit = trim($data[2]);
                $price = floatval(trim($data[3]));
                $qty = floatval(trim($data[4]));
                
                if (!empty($code) && !empty($name) && $qty > 0) {
                    $items[] = [
                        'code' => $code,
                        'name' => $name,
                        'unit' => $unit,
                        'unit_price' => $price,
                        'quantity' => $qty
                    ];
                }
            }
        }
        fclose($handle);
        
        if (count($items) > 0) {
            echo json_encode(['success' => true, 'items' => $items]);
        } else {
            echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลรายการสินค้าที่ถูกต้องในไฟล์ (ตรวจสอบว่าใส่จำนวนหรือยัง)']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'ไม่สามารถอ่านไฟล์ได้']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
