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
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script>
function swalSuccess(msg, timer) {
    return Swal.fire({ icon:'success', title:'สำเร็จ', text:msg, timer:timer||2500, showConfirmButton:false, toast:true, position:'top-end' });
}
function swalError(msg) {
    return Swal.fire({ icon:'error', title:'เกิดข้อผิดพลาด', text:msg });
}
function swalWarning(msg) {
    return Swal.fire({ icon:'warning', title:'แจ้งเตือน', text:msg });
}
function swalConfirm(title, text, confirmText) {
    return Swal.fire({ icon:'warning', title:title, text:text, showCancelButton:true, confirmButtonColor:'#d33', cancelButtonColor:'#6c757d', confirmButtonText:confirmText||'ยืนยัน', cancelButtonText:'ยกเลิก' });
}
function swalInfo(msg) {
    return Swal.fire({ icon:'info', title:'แจ้งเตือน', text:msg, timer:2000, showConfirmButton:false, toast:true, position:'top-end' });
}
</script>

<?php $currentPage = basename($_SERVER['PHP_SELF']); ?>

<!-- Sidebar Toggle Button (Mobile) -->
<button class="sidebar-toggle-btn d-lg-none" id="sidebarToggle" onclick="toggleSidebar()">
    <i class="bi bi-list"></i>
</button>

<!-- Overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <!-- Logo / Brand -->
    <div class="sidebar-brand">
        <a href="index.php" class="sidebar-logo-link">
            <img src="assets/img/logo.png" alt="Logo" class="sidebar-logo-img">
            <div class="sidebar-brand-text">
                <span class="sidebar-brand-name">Jasmine</span>
                <span class="sidebar-brand-sub">Active System</span>
            </div>
        </a>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <div class="sidebar-section-label">เมนูหลัก</div>
        <a href="index.php" class="sidebar-link <?= $currentPage=='index.php' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>
        <a href="products.php" class="sidebar-link <?= $currentPage=='products.php' ? 'active' : '' ?>">
            <i class="bi bi-box-seam"></i>
            <span>สินค้า</span>
        </a>
        <a href="packages.php" class="sidebar-link <?= ($currentPage=='packages.php'||$currentPage=='package_form.php') ? 'active' : '' ?>">
            <i class="bi bi-collection"></i>
            <span>แพ็กเกจ</span>
        </a>
        <a href="customers.php" class="sidebar-link <?= $currentPage=='customers.php' ? 'active' : '' ?>">
            <i class="bi bi-people"></i>
            <span>ลูกค้า</span>
        </a>

        <div class="sidebar-section-label mt-3">เอกสาร</div>
        <a href="create_document.php" class="sidebar-link <?= $currentPage=='create_document.php' ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-plus"></i>
            <span>ออกเอกสาร</span>
        </a>
        <a href="documents.php" class="sidebar-link <?= $currentPage=='documents.php' ? 'active' : '' ?>">
            <i class="bi bi-archive"></i>
            <span>ประวัติเอกสาร</span>
        </a>

        <div class="sidebar-section-label mt-3">ระบบ</div>
        <a href="companies.php" class="sidebar-link <?= ($currentPage=='companies.php'||$currentPage=='company_form.php') ? 'active' : '' ?>">
            <i class="bi bi-buildings"></i>
            <span>จัดการข้อมูลบริษัท</span>
        </a>
    </nav>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <i class="bi bi-shield-check me-1 text-success"></i>
        <small>v1.0 &nbsp;|&nbsp; JAS System</small>
    </div>
</aside>

<!-- MAIN CONTENT WRAPPER -->
<div class="main-wrapper">
    <!-- Top Bar -->
    <header class="topbar">
        <button class="topbar-toggle d-lg-none me-3" onclick="toggleSidebar()">
            <i class="bi bi-list fs-4"></i>
        </button>
        <div class="topbar-title">
            <?php
            $titles = [
                'index.php'           => '<i class="bi bi-speedometer2 me-2"></i>Dashboard',
                'products.php'        => '<i class="bi bi-box-seam me-2"></i>จัดการสินค้า',
                'packages.php'        => '<i class="bi bi-collection me-2"></i>จัดการแพ็กเกจ',
                'package_form.php'    => '<i class="bi bi-collection me-2"></i>แก้ไขแพ็กเกจ',
                'customers.php'       => '<i class="bi bi-people me-2"></i>จัดการลูกค้า',
                'create_document.php' => '<i class="bi bi-file-earmark-plus me-2"></i>ออกเอกสารใหม่',
                'documents.php'       => '<i class="bi bi-archive me-2"></i>ประวัติเอกสาร',
                'companies.php'       => '<i class="bi bi-buildings me-2"></i>จัดการข้อมูลบริษัท',
                'company_form.php'    => '<i class="bi bi-buildings me-2"></i>แก้ไขข้อมูลบริษัท',
            ];
            echo $titles[$currentPage] ?? '<i class="bi bi-house me-2"></i>หน้าหลัก';
            ?>
        </div>
        <div class="topbar-right">
            <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i><?= date('d/m/') . (date('Y') + 543) ?></span>
        </div>
    </header>

    <!-- Page Content -->
    <div class="page-content">
