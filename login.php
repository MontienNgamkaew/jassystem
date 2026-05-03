<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Already logged in → go to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once 'db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'กรุณากรอกชื่อผู้ใช้งานและรหัสผ่าน';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['username']   = $user['username'];
            $_SESSION['full_name']  = $user['full_name'];
            $_SESSION['role']       = $user['role'];
            header('Location: index.php');
            exit;
        } else {
            $error = 'ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ – Jasmine Active System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
            overflow: hidden;
        }

        /* Animated background orbs */
        body::before, body::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.4;
            animation: float 8s ease-in-out infinite;
            pointer-events: none;
        }
        body::before {
            width: 400px; height: 400px;
            background: radial-gradient(circle, #7b4fff, transparent);
            top: -100px; left: -100px;
        }
        body::after {
            width: 350px; height: 350px;
            background: radial-gradient(circle, #00d4ff, transparent);
            bottom: -100px; right: -100px;
            animation-delay: -4s;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0) scale(1); }
            50%       { transform: translateY(30px) scale(1.05); }
        }

        .login-card {
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 20px;
            padding: 48px 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.5);
            position: relative;
            z-index: 1;
        }

        .login-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        .login-logo img {
            height: 48px;
            filter: drop-shadow(0 0 12px rgba(123,79,255,0.6));
        }
        .login-brand {
            display: flex;
            flex-direction: column;
        }
        .login-brand-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
            line-height: 1;
        }
        .login-brand-sub {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.5);
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .login-title {
            text-align: center;
            color: rgba(255,255,255,0.6);
            font-size: 0.9rem;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .form-label {
            color: rgba(255,255,255,0.7);
            font-size: 0.85rem;
            margin-bottom: 6px;
        }
        .form-control {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 10px;
            color: #fff;
            padding: 12px 16px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        .form-control:focus {
            background: rgba(255,255,255,0.12);
            border-color: rgba(123,79,255,0.7);
            box-shadow: 0 0 0 3px rgba(123,79,255,0.2);
            color: #fff;
            outline: none;
        }
        .form-control::placeholder { color: rgba(255,255,255,0.3); }

        .input-group-text {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            border-right: none;
            border-radius: 10px 0 0 10px;
            color: rgba(255,255,255,0.5);
        }
        .input-group .form-control { border-left: none; border-radius: 0 10px 10px 0; }
        .input-group .form-control:focus { border-left: none; }

        .btn-login {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, #7b4fff, #00d4ff);
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 8px;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(123,79,255,0.5);
        }
        .btn-login:active { transform: translateY(0); }

        .login-footer {
            text-align: center;
            margin-top: 24px;
            color: rgba(255,255,255,0.3);
            font-size: 0.78rem;
        }
    </style>
</head>
<body>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<?php if ($error): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    Swal.fire({
        icon: 'error',
        title: 'เข้าสู่ระบบไม่สำเร็จ',
        text: <?= json_encode($error) ?>,
        background: '#1a1a2e',
        color: '#fff',
        confirmButtonColor: '#7b4fff'
    });
});
</script>
<?php endif; ?>

<div class="login-card">
    <div class="login-logo">
        <img src="assets/img/logo.png" alt="Logo" onerror="this.style.display='none'">
        <div class="login-brand">
            <span class="login-brand-name">Jasmine</span>
            <span class="login-brand-sub">Active System</span>
        </div>
    </div>
    <p class="login-title">กรุณาเข้าสู่ระบบเพื่อดำเนินการต่อ</p>

    <form method="POST" action="login.php">
        <div class="mb-3">
            <label class="form-label"><i class="bi bi-person me-1"></i>ชื่อผู้ใช้งาน</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                <input type="text" class="form-control" name="username" id="username"
                       placeholder="กรอกชื่อผู้ใช้งาน" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                       autocomplete="username" required autofocus>
            </div>
        </div>
        <div class="mb-4">
            <label class="form-label"><i class="bi bi-lock me-1"></i>รหัสผ่าน</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                <input type="password" class="form-control" name="password" id="password"
                       placeholder="กรอกรหัสผ่าน" autocomplete="current-password" required>
            </div>
        </div>
        <button type="submit" class="btn-login">
            <i class="bi bi-box-arrow-in-right me-2"></i>เข้าสู่ระบบ
        </button>
    </form>

    <div class="login-footer">
        <i class="bi bi-shield-lock me-1"></i>ระบบปิด – เฉพาะผู้ได้รับอนุญาตเท่านั้น
    </div>
</div>
</body>
</html>
