<?php
session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
require_once 'db.php';

header('Content-Type: application/json');

if (isset($_GET['package_id'])) {
    $package_id = intval($_GET['package_id']);
    
    try {
        $stmt = $pdo->prepare("
            SELECT pi.product_id, pi.quantity, p.code, p.name, p.unit, p.unit_price 
            FROM package_items pi 
            JOIN products p ON pi.product_id = p.id 
            WHERE pi.package_id = ?
        ");
        $stmt->execute([$package_id]);
        $items = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'items' => $items]);
    } catch (\PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Missing package_id']);
}
