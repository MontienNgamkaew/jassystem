<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการเอกสาร Jasmine Active System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-custom sticky-top glass-effect">
    <div class="container-fluid">
        <!-- Logo and System Name clearly at the top -->
        <a class="navbar-brand" href="index.php">
            <img src="assets/img/logo.png" alt="Jasmine Active System Logo" class="logo-img">
            ระบบจัดการเอกสาร Jasmine Active System
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>" href="index.php">
                        <i class="bi bi-speedometer2 me-1"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'products.php') ? 'active' : ''; ?>" href="products.php">
                        <i class="bi bi-box-seam me-1"></i> สินค้า
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'packages.php') ? 'active' : ''; ?>" href="packages.php">
                        <i class="bi bi-collection me-1"></i> แพ็กเกจ
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'customers.php') ? 'active' : ''; ?>" href="customers.php">
                        <i class="bi bi-people me-1"></i> ลูกค้า
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'create_document.php') ? 'active' : ''; ?>" href="create_document.php">
                        <i class="bi bi-file-earmark-plus me-1"></i> ออกเอกสาร
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4 mb-5">
