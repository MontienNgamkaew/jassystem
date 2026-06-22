<?php
if (session_status() === PHP_SESSION_NONE) session_start();

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
    <style>
        :root {
            --primary:       #84cc16;
            --primary-dark:  #65a30d;
            --primary-glow:  rgba(132, 204, 22, 0.35);
            --forest-deep:   #0a1a0a;
            --forest-mid:    #1a2e1a;
            --forest-light:  #2d4a2d;
            --glass-bg:      rgba(26, 46, 26, 0.55);
            --glass-border:  rgba(132, 204, 22, 0.18);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--forest-deep);
            font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
            overflow: hidden;
            position: relative;
        }

        /* ── Canvas fireflies ── */
        #bg-canvas {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
        }

        /* ── Background orbs ── */
        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(90px);
            pointer-events: none;
            z-index: 0;
            animation: orbFloat var(--dur, 10s) ease-in-out infinite;
            animation-delay: var(--delay, 0s);
        }
        .orb-1 {
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(132,204,22,0.18), transparent 70%);
            top: -180px; left: -180px;
            --dur: 12s;
        }
        .orb-2 {
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(45,74,45,0.5), transparent 70%);
            bottom: -150px; right: -150px;
            --dur: 9s; --delay: -4s;
        }
        .orb-3 {
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(132,204,22,0.10), transparent 70%);
            top: 50%; left: 55%;
            --dur: 14s; --delay: -7s;
        }
        @keyframes orbFloat {
            0%,100% { transform: translate(0,0) scale(1); }
            33%      { transform: translate(20px,-25px) scale(1.04); }
            66%      { transform: translate(-15px,15px) scale(0.97); }
        }

        /* ── Grid lines overlay ── */
        .grid-overlay {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background-image:
                linear-gradient(rgba(132,204,22,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(132,204,22,0.03) 1px, transparent 1px);
            background-size: 60px 60px;
        }

        /* ── Wrapper ── */
        .login-wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 440px;
            padding: 16px;
            animation: wrapperIn 0.7s cubic-bezier(0.22,1,0.36,1) both;
        }
        @keyframes wrapperIn {
            from { opacity: 0; transform: translateY(32px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* ── Card ── */
        .login-card {
            background: var(--glass-bg);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 44px 40px 36px;
            box-shadow:
                0 30px 70px rgba(0,0,0,0.5),
                0 0 0 1px rgba(132,204,22,0.06) inset,
                0 1px 0 rgba(132,204,22,0.15) inset;
            position: relative;
            overflow: hidden;
        }

        /* top shimmer line */
        .login-card::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 2px;
            background: linear-gradient(90deg, transparent, var(--primary), transparent);
            animation: shimmerLine 3s ease-in-out infinite;
        }
        @keyframes shimmerLine {
            0%   { left: -100%; }
            50%  { left: 100%; }
            100% { left: 100%; }
        }

        /* corner accent */
        .login-card::after {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 160px; height: 160px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(132,204,22,0.10), transparent 70%);
            pointer-events: none;
        }

        /* ── Logo area ── */
        .login-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            margin-bottom: 6px;
        }
        .logo-icon-wrap {
            width: 52px; height: 52px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--forest-mid), var(--forest-light));
            border: 1px solid rgba(132,204,22,0.3);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 0 20px var(--primary-glow);
            animation: logoPulse 3s ease-in-out infinite;
        }
        @keyframes logoPulse {
            0%,100% { box-shadow: 0 0 20px var(--primary-glow); }
            50%      { box-shadow: 0 0 35px rgba(132,204,22,0.5), 0 0 60px rgba(132,204,22,0.15); }
        }
        .logo-icon-wrap img {
            height: 34px; width: 34px; object-fit: contain;
        }
        .logo-icon-wrap .bi {
            font-size: 1.6rem;
            color: var(--primary);
        }
        .login-brand-name {
            font-size: 1.6rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.5px;
            line-height: 1;
        }
        .login-brand-name span { color: var(--primary); }
        .login-brand-sub {
            font-size: 0.72rem;
            color: rgba(255,255,255,0.4);
            letter-spacing: 2.5px;
            text-transform: uppercase;
            margin-top: 3px;
        }

        /* ── Divider ── */
        .login-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 20px 0 28px;
        }
        .login-divider::before,
        .login-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(132,204,22,0.2), transparent);
        }
        .login-divider span {
            font-size: 0.78rem;
            color: rgba(255,255,255,0.35);
            white-space: nowrap;
            letter-spacing: 0.5px;
        }

        /* ── Form fields ── */
        .field-group {
            margin-bottom: 18px;
        }
        .field-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: rgba(255,255,255,0.6);
            margin-bottom: 7px;
            letter-spacing: 0.3px;
        }
        .field-label .bi { margin-right: 5px; color: var(--primary); }

        .input-wrap {
            position: relative;
        }
        .input-wrap .field-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.3);
            font-size: 1rem;
            transition: color 0.3s;
            pointer-events: none;
            z-index: 2;
        }
        .input-wrap input {
            width: 100%;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            color: #fff;
            padding: 13px 14px 13px 42px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            outline: none;
            -webkit-appearance: none;
        }
        .input-wrap input::placeholder { color: rgba(255,255,255,0.22); }
        .input-wrap input:focus {
            background: rgba(132,204,22,0.07);
            border-color: rgba(132,204,22,0.5);
            box-shadow: 0 0 0 3px rgba(132,204,22,0.12), 0 4px 12px rgba(0,0,0,0.2);
        }
        .input-wrap input:focus + .field-icon,
        .input-wrap input:focus ~ .field-icon { color: var(--primary); }
        /* icon is before input in DOM so we use sibling trick differently */
        .input-wrap:focus-within .field-icon { color: var(--primary); }

        /* password toggle */
        .pwd-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: rgba(255,255,255,0.3);
            cursor: pointer;
            font-size: 1rem;
            padding: 0;
            transition: color 0.3s;
            z-index: 2;
        }
        .pwd-toggle:hover { color: var(--primary); }

        /* ── Submit button ── */
        .btn-login {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #84cc16, #65a30d);
            color: #0a1a0a;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 8px;
            position: relative;
            overflow: hidden;
        }
        .btn-login::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.18), transparent);
            opacity: 0;
            transition: opacity 0.3s;
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(132,204,22,0.4); }
        .btn-login:hover::before { opacity: 1; }
        .btn-login:active { transform: translateY(0); box-shadow: 0 4px 12px rgba(132,204,22,0.3); }

        /* shimmer sweep on hover */
        .btn-login::after {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 60%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.25), transparent);
            transform: skewX(-15deg);
            transition: none;
        }
        .btn-login:hover::after {
            animation: btnSweep 0.55s ease forwards;
        }
        @keyframes btnSweep {
            from { left: -100%; }
            to   { left: 160%; }
        }

        /* loading state */
        .btn-login.loading {
            pointer-events: none;
            opacity: 0.8;
        }
        .btn-login .btn-text { transition: opacity 0.2s; }
        .btn-login .btn-spinner {
            display: none;
            width: 18px; height: 18px;
            border: 2px solid rgba(0,0,0,0.3);
            border-top-color: #0a1a0a;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            margin: 0 auto;
        }
        .btn-login.loading .btn-text { display: none; }
        .btn-login.loading .btn-spinner { display: block; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Footer ── */
        .login-footer {
            text-align: center;
            margin-top: 22px;
            color: rgba(255,255,255,0.22);
            font-size: 0.76rem;
            letter-spacing: 0.3px;
        }
        .login-footer .bi { color: var(--primary); opacity: 0.7; }

        /* ── Status badge ── */
        .status-dot {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(132,204,22,0.1);
            border: 1px solid rgba(132,204,22,0.2);
            border-radius: 20px;
            padding: 3px 10px;
            font-size: 0.72rem;
            color: var(--primary);
            margin-bottom: 6px;
        }
        .status-dot .dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: var(--primary);
            animation: dotBlink 2s ease-in-out infinite;
        }
        @keyframes dotBlink {
            0%,100% { opacity: 1; }
            50%      { opacity: 0.3; }
        }

        /* ── SweetAlert theme override ── */
        .swal2-popup {
            background: #1a2e1a !important;
            border: 1px solid rgba(132,204,22,0.2) !important;
        }
        .swal2-title, .swal2-html-container { color: #fff !important; }
    </style>
</head>
<body>

<canvas id="bg-canvas"></canvas>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>
<div class="orb orb-3"></div>
<div class="grid-overlay"></div>

<div class="login-wrapper">
    <div class="login-card">

        <!-- Logo -->
        <div class="login-logo">
            <div class="logo-icon-wrap">
                <img src="assets/img/logo.png" alt="Logo"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
                <i class="bi bi-flower1" style="display:none"></i>
            </div>
            <div>
                <div class="login-brand-name">Jasmine <span>Active</span></div>
                <div class="login-brand-sub">Document System</div>
            </div>
        </div>

        <!-- Divider -->
        <div class="login-divider">
            <span><i class="bi bi-shield-lock-fill me-1"></i>Secure Login</span>
        </div>

        <!-- Form -->
        <form method="POST" action="login.php" id="loginForm" novalidate>

            <div class="field-group">
                <label class="field-label" for="username">
                    <i class="bi bi-person-fill"></i>ชื่อผู้ใช้งาน
                </label>
                <div class="input-wrap">
                    <i class="bi bi-person field-icon"></i>
                    <input type="text" id="username" name="username"
                           placeholder="กรอก Username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           autocomplete="username" required autofocus>
                </div>
            </div>

            <div class="field-group">
                <label class="field-label" for="password">
                    <i class="bi bi-lock-fill"></i>รหัสผ่าน
                </label>
                <div class="input-wrap">
                    <i class="bi bi-lock field-icon"></i>
                    <input type="password" id="password" name="password"
                           placeholder="กรอก Password"
                           autocomplete="current-password" required>
                    <button type="button" class="pwd-toggle" id="pwdToggle"
                            aria-label="แสดง/ซ่อนรหัสผ่าน">
                        <i class="bi bi-eye-slash" id="pwdIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login" id="btnLogin">
                <span class="btn-text">
                    <i class="bi bi-box-arrow-in-right me-2"></i>เข้าสู่ระบบ
                </span>
                <div class="btn-spinner"></div>
            </button>

        </form>

        <!-- Footer -->
        <div class="login-footer">
            <div class="status-dot">
                <span class="dot"></span>ระบบออนไลน์
            </div>
            <div style="margin-top:8px">
                <i class="bi bi-shield-check"></i>
                เฉพาะผู้ได้รับอนุญาตเท่านั้น
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<?php if ($error): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    Swal.fire({
        icon: 'error',
        title: 'เข้าสู่ระบบไม่สำเร็จ',
        text: <?= json_encode($error) ?>,
        background: '#1a2e1a',
        color: '#fff',
        confirmButtonColor: '#84cc16',
        confirmButtonText: '<span style="color:#0a1a0a;font-weight:700">ลองใหม่</span>',
        customClass: { popup: 'swal2-popup' }
    });
});
</script>
<?php endif; ?>

<script>
/* ── Firefly canvas ── */
(function () {
    const canvas = document.getElementById('bg-canvas');
    const ctx    = canvas.getContext('2d');
    let W, H, particles = [];

    const COLORS = ['rgba(132,204,22,', 'rgba(101,163,13,', 'rgba(180,230,50,'];

    function resize() {
        W = canvas.width  = window.innerWidth;
        H = canvas.height = window.innerHeight;
    }

    function Particle() {
        this.reset = function () {
            this.x    = Math.random() * W;
            this.y    = Math.random() * H;
            this.r    = Math.random() * 2 + 0.4;
            this.vx   = (Math.random() - 0.5) * 0.35;
            this.vy   = (Math.random() - 0.5) * 0.35 - 0.1;
            this.life = 0;
            this.max  = Math.random() * 200 + 100;
            this.col  = COLORS[Math.floor(Math.random() * COLORS.length)];
        };
        this.reset();
        this.life = Math.random() * this.max; // stagger start
    }

    function init() {
        resize();
        particles = Array.from({ length: 90 }, () => new Particle());
    }

    function draw() {
        ctx.clearRect(0, 0, W, H);
        particles.forEach(p => {
            p.life += 1;
            if (p.life > p.max) p.reset();

            const progress = p.life / p.max;
            const alpha = progress < 0.2
                ? progress / 0.2
                : progress > 0.8
                    ? (1 - progress) / 0.2
                    : 1;

            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fillStyle = p.col + (alpha * 0.7) + ')';
            ctx.fill();

            p.x += p.vx;
            p.y += p.vy;
        });
        requestAnimationFrame(draw);
    }

    window.addEventListener('resize', resize);
    init();
    draw();
})();

/* ── Password toggle ── */
document.getElementById('pwdToggle').addEventListener('click', function () {
    const input = document.getElementById('password');
    const icon  = document.getElementById('pwdIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye-slash';
    }
});

/* ── Submit loading state ── */
document.getElementById('loginForm').addEventListener('submit', function () {
    const btn = document.getElementById('btnLogin');
    btn.classList.add('loading');
});
</script>
</body>
</html>
