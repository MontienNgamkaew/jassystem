<?php
/**
 * setup_admin.php - Run ONCE to create the first admin account.
 * DELETE or RENAME this file after use for security.
 */
require_once 'db.php';

$count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

if ($count > 0) {
    die('<div style="font-family:sans-serif;padding:40px;text-align:center;color:#c00;">
        <h2>⛔ ไม่สามารถรันสคริปต์นี้ได้</h2>
        <p>มีผู้ใช้งานอยู่ในระบบแล้ว (' . $count . ' คน)<br>
        กรุณาลบหรือเปลี่ยนชื่อไฟล์นี้เพื่อความปลอดภัยครับ</p>
        <a href="login.php" style="color:#7b4fff;">→ ไปหน้า Login</a>
    </div>');
}

$username  = 'admin';
$password  = 'Admin@1234';
$full_name = 'ผู้ดูแลระบบ';
$hash      = password_hash($password, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, 'admin')");
$stmt->execute([$username, $hash, $full_name]);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<style>
body { font-family: sans-serif; display:flex; align-items:center; justify-content:center; min-height:100vh; background:#f0f4ff; margin:0; }
.box { background:#fff; border-radius:16px; padding:40px 48px; box-shadow:0 4px 30px rgba(0,0,0,.1); text-align:center; max-width:480px; }
h2 { color:#2a2a6e; }
.badge { display:inline-block; background:#f0f0ff; border:1px solid #dde; border-radius:8px; padding:12px 24px; margin:8px 0; font-size:1rem; }
.badge b { color:#7b4fff; }
.warn { color:#c00; font-size:.85rem; margin-top:16px; }
a.btn { display:inline-block; margin-top:20px; padding:10px 28px; background:linear-gradient(135deg,#7b4fff,#00d4ff); color:#fff; border-radius:8px; text-decoration:none; font-weight:600; }
</style>
</head>
<body>
<div class="box">
    <h2>✅ สร้างบัญชีแอดมินสำเร็จ!</h2>
    <p>ข้อมูลสำหรับเข้าสู่ระบบครั้งแรก:</p>
    <div class="badge">Username: <b>admin</b></div><br>
    <div class="badge">Password: <b>Admin@1234</b></div>
    <p class="warn">⚠️ กรุณาเปลี่ยนรหัสผ่านทันทีหลังเข้าสู่ระบบ<br>และ <b>ลบไฟล์ setup_admin.php</b> ออกจากเซิร์ฟเวอร์ด้วยครับ!</p>
    <a href="login.php" class="btn"><i>→</i> ไปหน้า Login</a>
</div>
</body>
</html>
