<?php
require_once 'db.php';
include_once 'includes/header.php';

// Dashboard Summary Queries
$totalCustomers = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalPackages = $pdo->query("SELECT COUNT(*) FROM packages")->fetchColumn();
$totalDocuments = $pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();

// Fetch Recent Documents
$stmt = $pdo->query("SELECT d.id, d.doc_no, d.type, d.date, c.name as customer_name, d.total_amount 
                     FROM documents d 
                     LEFT JOIN customers c ON d.customer_id = c.id 
                     ORDER BY d.created_at DESC LIMIT 5");
$recentDocs = $stmt->fetchAll();
?>

<div class="page-header">
    <h2 class="page-title"><i class="bi bi-speedometer2"></i> Dashboard</h2>
    <a href="create_document.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> ออกเอกสารใหม่</a>
</div>

<!-- Stats Row -->
<div class="row mb-4 g-3">
    <div class="col-md-3 col-sm-6">
        <div class="card card-custom">
            <div class="stat-card-body">
                <div class="stat-icon"><i class="bi bi-people"></i></div>
                <div>
                    <div class="stat-number"><?= number_format($totalCustomers) ?></div>
                    <div class="stat-label">ลูกค้าทั้งหมด</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card card-custom">
            <div class="stat-card-body">
                <div class="stat-icon"><i class="bi bi-box-seam"></i></div>
                <div>
                    <div class="stat-number"><?= number_format($totalProducts) ?></div>
                    <div class="stat-label">สินค้าทั้งหมด</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card card-custom">
            <div class="stat-card-body">
                <div class="stat-icon"><i class="bi bi-collection"></i></div>
                <div>
                    <div class="stat-number"><?= number_format($totalPackages) ?></div>
                    <div class="stat-label">แพ็กเกจ</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card card-custom">
            <div class="stat-card-body">
                <div class="stat-icon"><i class="bi bi-file-earmark-text"></i></div>
                <div>
                    <div class="stat-number"><?= number_format($totalDocuments) ?></div>
                    <div class="stat-label">เอกสารที่ออกแล้ว</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Documents Table -->
<div class="card card-custom">
    <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history"></i> เอกสารล่าสุด</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-custom table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">เลขที่เอกสาร</th>
                        <th>ประเภท</th>
                        <th>ลูกค้า</th>
                        <th>วันที่</th>
                        <th class="text-end pe-4">ยอดรวม (บาท)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recentDocs) > 0): ?>
                        <?php foreach ($recentDocs as $doc): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-primary"><?= htmlspecialchars($doc['doc_no']) ?></td>
                            <td>
                                <?php if($doc['type'] == 'Quote'): ?>
                                    <span class="badge bg-info text-dark">ใบเสนอราคา</span>
                                <?php elseif($doc['type'] == 'Invoice'): ?>
                                    <span class="badge bg-warning text-dark">ใบแจ้งหนี้</span>
                                <?php elseif($doc['type'] == 'Receipt'): ?>
                                    <span class="badge bg-success">ใบเสร็จรับเงิน</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($doc['customer_name']) ?></td>
                            <td><?= date('d/m/Y', strtotime($doc['date'])) ?></td>
                            <td class="text-end pe-4 fw-bold"><?= number_format($doc['total_amount'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">ยังไม่มีข้อมูลเอกสาร</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>