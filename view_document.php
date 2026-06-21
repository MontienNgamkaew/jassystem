<?php
require_once 'db.php';
require_once 'auth.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: documents.php");
    exit();
}

// Fetch Document with Customer and Company Info
$stmt = $pdo->prepare("
    SELECT d.*, c.name as customer_name, c.tax_id as customer_tax_id, c.address as customer_address, c.phone as customer_phone,
           comp.name_th as company_name_th, comp.name_en as company_name_en, comp.address as company_address, comp.phone as company_phone, comp.tax_id as company_tax_id, comp.logo_path, comp.stamp_path, comp.stamp_enabled
    FROM documents d 
    JOIN customers c ON d.customer_id = c.id 
    LEFT JOIN companies comp ON d.company_id = comp.id
    WHERE d.id = ?
");
$stmt->execute([$id]);
$doc = $stmt->fetch();

if (!$doc) {
    die("ไม่พบเอกสารนี้ในระบบ");
}

// Fetch Document Items
$stmt = $pdo->prepare("SELECT * FROM document_items WHERE document_id = ? ORDER BY id ASC");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

// Calculate VAT status
$subtotal = array_sum(array_column($items, 'total'));
$include_vat = (bool)$doc['include_vat'];
$vat_amount = $include_vat ? round($subtotal * 0.07, 2) : 0;
$grand_total = $subtotal + $vat_amount;

// Document Type mapping
$typeLabels = [
    'Quote' => 'ใบเสนอราคา / Quotation',
    'Invoice' => 'ใบแจ้งหนี้ / Invoice',
    'Receipt' => 'ใบเสร็จรับเงิน / Receipt'
];
$docTypeName = $typeLabels[$doc['type']] ?? 'เอกสาร';

// Signature labels (match generate_pdf.php logic)
$sig_left_text = 'ผู้สั่งซื้อ';
$sig_right_text = 'ผู้เสนอราคา';
if ($doc['type'] === 'Invoice') {
    $sig_left_text = 'ผู้รับของ';
    $sig_right_text = 'ผู้ส่งของ';
} elseif ($doc['type'] === 'Receipt') {
    $sig_left_text = 'ผู้จ่ายเงิน';
    $sig_right_text = 'ผู้รับเงิน';
}

include_once 'includes/header.php';
?>

<!-- Print/Preview Styling -->
<style>
.invoice-container {
    background-color: #fff;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
    padding: 40px;
    max-width: 850px;
    margin: 20px auto;
    border: 1px solid rgba(0,0,0,0.08);
}
.invoice-header {
    border-bottom: 2px solid #84cc16;
    padding-bottom: 20px;
    margin-bottom: 30px;
}
.logo-preview {
    max-width: 80px;
    max-height: 80px;
    border-radius: 8px;
    object-fit: contain;
}
.stamp-preview {
    max-width: 120px;
    max-height: 120px;
    opacity: 0.85;
    position: absolute;
    right: 40px;
    bottom: -10px;
    pointer-events: none;
}
.signature-row {
    position: relative;
    margin-top: 60px;
    padding-top: 20px;
}
.signature-box {
    border-top: 1px dashed #ccc;
    text-align: center;
    padding-top: 10px;
    font-size: 0.9rem;
    color: #666;
    width: 220px;
    margin: 0 auto;
}

@media print {
    .no-print, .sidebar, .topbar, .sidebar-overlay {
        display: none !important;
    }
    .main-wrapper {
        margin-left: 0 !important;
        padding: 0 !important;
    }
    body {
        background-color: #fff !important;
    }
    .invoice-container {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin: 0 !important;
        max-width: 100% !important;
    }
}
</style>

<div class="page-header no-print d-flex justify-content-between align-items-center mb-4">
    <h2 class="page-title"><i class="bi bi-file-earmark-text"></i> รายละเอียดเอกสาร</h2>
    <div>
        <button onclick="window.print()" class="btn btn-sm btn-success me-2">
            <i class="bi bi-printer"></i> พิมพ์เอกสาร / PDF
        </button>
        
        <a href="generate_pdf.php?id=<?= $doc['id'] ?>" target="_blank" class="btn btn-sm btn-outline-danger me-2">
            <i class="bi bi-file-earmark-pdf"></i> ดาวน์โหลด PDF
        </a>

        <?php if ($doc['type'] === 'Quote'): ?>
            <a href="create_document.php?convert_from=<?= $doc['id'] ?>" class="btn btn-sm btn-warning me-2">
                <i class="bi bi-arrow-left-right"></i> แปลงเป็นใบแจ้งหนี้
            </a>
        <?php elseif ($doc['type'] === 'Invoice'): ?>
            <a href="create_document.php?convert_from=<?= $doc['id'] ?>" class="btn btn-sm btn-success me-2">
                <i class="bi bi-check-circle"></i> ออกใบเสร็จรับเงิน
            </a>
        <?php endif; ?>

        <a href="edit_document.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-warning me-2">
            <i class="bi bi-pencil"></i> แก้ไขเอกสาร
        </a>
        <a href="documents.php" class="btn btn-sm btn-secondary"><i class="bi bi-arrow-left"></i> ย้อนกลับ</a>
    </div>
</div>

<div class="invoice-container" id="printArea">
    <!-- Invoice Header -->
    <div class="invoice-header">
        <div class="row align-items-center">
            <div class="col-8 d-flex align-items-center">
                <?php if (!empty($doc['logo_path']) && file_exists($doc['logo_path'])): ?>
                    <img src="<?= htmlspecialchars($doc['logo_path']) ?>" class="logo-preview me-3" alt="Logo">
                <?php endif; ?>
                <div>
                    <h3 class="mb-0 text-dark fw-bold"><?= htmlspecialchars($doc['company_name_th'] ?? 'Jasmine Active') ?></h3>
                    <?php if (!empty($doc['company_name_en'])): ?>
                        <p class="text-muted small mb-0"><?= htmlspecialchars($doc['company_name_en']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-4 text-end">
                <h3 class="text-primary fw-bold mb-1" style="font-size: 1.6rem;"><?= htmlspecialchars($docTypeName) ?></h3>
                <div class="text-muted small">
                    <strong>เลขที่เอกสาร:</strong> <span class="text-dark fw-bold"><?= htmlspecialchars($doc['doc_no']) ?></span><br>
                    <strong>วันที่:</strong> <?= date('d/m/Y', strtotime($doc['date'])) ?><br>
                </div>
            </div>
        </div>
    </div>

    <!-- Company and Customer Info -->
    <div class="row mb-5">
        <div class="col-6">
            <h6 class="text-primary fw-bold border-bottom pb-1 mb-2">ข้อมูลผู้ขาย (Seller)</h6>
            <div class="small text-dark" style="line-height: 1.6;">
                <strong><?= htmlspecialchars($doc['company_name_th'] ?? 'Jasmine Active') ?></strong><br>
                <?= nl2br(htmlspecialchars($doc['company_address'] ?? '')) ?><br>
                <?php if ($doc['company_phone']): ?>
                    <strong>โทร:</strong> <?= htmlspecialchars($doc['company_phone']) ?><br>
                <?php endif; ?>
                <?php if ($doc['company_tax_id']): ?>
                    <strong>เลขประจำตัวผู้เสียภาษี:</strong> <?= htmlspecialchars($doc['company_tax_id']) ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-6 text-end">
            <h6 class="text-primary fw-bold border-bottom pb-1 mb-2">ข้อมูลลูกค้า (Customer)</h6>
            <div class="small text-dark" style="line-height: 1.6;">
                <strong><?= htmlspecialchars($doc['customer_name']) ?></strong><br>
                <?= nl2br(htmlspecialchars($doc['customer_address'] ?? '')) ?><br>
                <?php if ($doc['customer_phone']): ?>
                    <strong>โทร:</strong> <?= htmlspecialchars($doc['customer_phone']) ?><br>
                <?php endif; ?>
                <?php if ($doc['customer_tax_id']): ?>
                    <strong>เลขประจำตัวผู้เสียภาษี:</strong> <?= htmlspecialchars($doc['customer_tax_id']) ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Document Items Table -->
    <div class="table-responsive mb-4">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-light">
                <tr>
                    <th class="text-center" width="8%">#</th>
                    <th>รายการสินค้า / คำอธิบาย</th>
                    <th class="text-center" width="12%">จำนวน</th>
                    <th class="text-end" width="20%">ราคาต่อหน่วย (บาท)</th>
                    <th class="text-end" width="20%">ราคารวม (บาท)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $idx => $item): ?>
                    <tr>
                        <td class="text-center"><?= $idx + 1 ?></td>
                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                        <td class="text-center"><?= number_format($item['quantity'], 2) ?></td>
                        <td class="text-end"><?= number_format($item['price'], 2) ?></td>
                        <td class="text-end fw-bold"><?= number_format($item['total'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Summary Box -->
    <div class="row justify-content-end mb-5">
        <div class="col-6">
            <table class="table table-sm table-borderless">
                <tr>
                    <td><strong>ยอดรวมสุทธิ (Subtotal):</strong></td>
                    <td class="text-end"><?= number_format($subtotal, 2) ?> บาท</td>
                </tr>
                <?php if ($include_vat): ?>
                    <tr>
                        <td><strong>ภาษีมูลค่าเพิ่ม (VAT 7%):</strong></td>
                        <td class="text-end"><?= number_format($vat_amount, 2) ?> บาท</td>
                    </tr>
                <?php endif; ?>
                <tr class="border-top" style="font-size: 1.15rem;">
                    <td><strong class="text-primary">ยอดรวมทั้งสิ้น (Grand Total):</strong></td>
                    <td class="text-end text-primary fw-bold"><?= number_format($grand_total, 2) ?> บาท</td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Signature and Stamp Section -->
    <div class="row signature-row text-center">
        <!-- Stamp overlay -->
        <?php if ($doc['stamp_enabled'] && !empty($doc['stamp_path']) && file_exists($doc['stamp_path'])): ?>
            <img src="<?= htmlspecialchars($doc['stamp_path']) ?>" class="stamp-preview" alt="Stamp">
        <?php endif; ?>
        
        <div class="col-6">
            <div class="signature-box mt-4">
                <?= htmlspecialchars($sig_left_text) ?><br>
                <?php if ($doc['show_date']): ?>
                    วันที่ ____/____/____
                <?php endif; ?>
            </div>
        </div>
        <div class="col-6">
            <div class="signature-box mt-4">
                <?= htmlspecialchars($sig_right_text) ?><br>
                <?php if ($doc['show_date']): ?>
                    วันที่ ____/____/____
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>
