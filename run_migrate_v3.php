<?php
require_once 'db.php';

// Check if column already exists
$cols = $pdo->query("SHOW COLUMNS FROM customers LIKE 'phone'")->fetchAll();

if ($cols) {
    $status = 'already';
} else {
    try {
        $pdo->exec("ALTER TABLE `customers` ADD COLUMN `phone` VARCHAR(50) NULL AFTER `address`");
        $status = 'ok';
    } catch (\PDOException $e) {
        $status = 'error';
        $msg = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>Migration v3</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh">
<div class="card shadow p-4 text-center" style="max-width:420px;width:100%">
    <?php if ($status === 'ok'): ?>
        <div class="text-success fs-1 mb-2">✅</div>
        <h5 class="fw-bold">Migration สำเร็จ!</h5>
        <p class="text-muted">เพิ่มคอลัมน์ <code>phone</code> ใน table <code>customers</code> เรียบร้อยแล้ว</p>
        <a href="customers.php" class="btn btn-success mt-2">ไปหน้าลูกค้าเลย →</a>
    <?php elseif ($status === 'already'): ?>
        <div class="text-primary fs-1 mb-2">ℹ️</div>
        <h5 class="fw-bold">ไม่ต้องทำอะไร</h5>
        <p class="text-muted">คอลัมน์ <code>phone</code> มีอยู่แล้วใน DB</p>
        <a href="customers.php" class="btn btn-primary mt-2">ไปหน้าลูกค้าเลย →</a>
    <?php else: ?>
        <div class="text-danger fs-1 mb-2">❌</div>
        <h5 class="fw-bold">เกิดข้อผิดพลาด</h5>
        <p class="text-muted small"><?= htmlspecialchars($msg ?? '') ?></p>
    <?php endif; ?>
    <hr class="mt-4">
    <small class="text-danger">⚠️ ลบไฟล์นี้ออกหลังใช้งาน: <code>run_migrate_v3.php</code></small>
</div>
</body>
</html>
