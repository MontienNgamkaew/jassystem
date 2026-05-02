<?php
require_once 'db.php';

// Handle Delete Document
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $pdo->beginTransaction();
        // Delete items first
        $stmt = $pdo->prepare("DELETE FROM document_items WHERE document_id = ?");
        $stmt->execute([$id]);
        // Delete document
        $stmt = $pdo->prepare("DELETE FROM documents WHERE id = ?");
        $stmt->execute([$id]);
        $pdo->commit();
        header("Location: documents.php?msg=deleted");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "เกิดข้อผิดพลาดในการลบเอกสาร";
    }
}

$sql = "SELECT d.*, c.name as customer_name, comp.name_th as company_name 
        FROM documents d 
        LEFT JOIN customers c ON d.customer_id = c.id 
        LEFT JOIN companies comp ON d.company_id = comp.id
        ORDER BY d.created_at DESC";
$documents = $pdo->query($sql)->fetchAll();

$type_labels = [
    'Quote'   => ['label' => 'ใบเสนอราคา',    'class' => 'bg-primary'],
    'Invoice' => ['label' => 'ใบแจ้งหนี้',     'class' => 'bg-warning text-dark'],
    'Receipt' => ['label' => 'ใบเสร็จรับเงิน', 'class' => 'bg-success'],
];

include_once 'includes/header.php';
?>

<div class="page-header">
    <h2 class="page-title"><i class="bi bi-archive"></i> ประวัติเอกสารทั้งหมด</h2>
    <a href="create_document.php" class="btn btn-primary">
        <i class="bi bi-file-earmark-plus"></i> ออกเอกสารใหม่
    </a>
</div>

<?php if (isset($_GET['msg'])): ?>
<script>document.addEventListener('DOMContentLoaded',()=>{
<?php if ($_GET['msg'] === 'created'): ?>
    swalSuccess('บันทึกเอกสารสำเร็จแล้ว! กดปุ่ม PDF เพื่อดาวน์โหลดได้เลยครับ', 3000);
<?php elseif ($_GET['msg'] === 'deleted'): ?>
    swalSuccess('ลบเอกสารสำเร็จ');
<?php endif; ?>
});</script>
<?php endif; ?>

<?php if (isset($error)): ?>
<script>document.addEventListener('DOMContentLoaded',()=>swalError(<?= json_encode($error) ?>));</script>
<?php endif; ?>

<div class="card card-custom">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-custom table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">เลขที่เอกสาร</th>
                        <th>ประเภท</th>
                        <th>วันที่</th>
                        <th>ออกในนามบริษัท</th>
                        <th>ลูกค้า</th>
                        <th class="text-end">ยอดรวม (บาท)</th>
                        <th class="text-center">VAT</th>
                        <th class="text-center pe-3">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($documents) > 0): ?>
                        <?php foreach ($documents as $d): ?>
                        <tr>
                            <td class="ps-3 fw-bold text-primary"><?= htmlspecialchars($d['doc_no']) ?></td>
                            <td>
                                <?php $t = $type_labels[$d['type']] ?? ['label'=>$d['type'],'class'=>'bg-secondary']; ?>
                                <span class="badge <?= $t['class'] ?>"><?= $t['label'] ?></span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($d['date'])) ?></td>
                            <td><?= htmlspecialchars($d['company_name'] ?? 'บริษัทหลัก') ?></td>
                            <td><?= htmlspecialchars($d['customer_name'] ?? '-') ?></td>
                            <td class="text-end fw-bold"><?= number_format($d['total_amount'], 2) ?></td>
                            <td class="text-center">
                                <?php if ($d['include_vat']): ?>
                                    <span class="badge bg-success">รวม VAT</span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center pe-3">
                                <a href="generate_pdf.php?id=<?= $d['id'] ?>" target="_blank" class="btn btn-outline-danger btn-table-action" title="พิมพ์ PDF">
                                    <i class="bi bi-file-earmark-pdf"></i>
                                </a>
                                <button type="button" class="btn btn-outline-secondary btn-table-action" title="ลบเอกสาร" onclick="confirmDelete(<?= $d['id'] ?>, '<?= htmlspecialchars($d['doc_no']) ?>')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox" style="font-size:2rem;"></i><br>
                                ยังไม่มีเอกสาร <a href="create_document.php" class="ms-2 btn btn-sm btn-primary">ออกเอกสารใหม่</a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, doc_no) {
    swalConfirm('ยืนยันการลบ', `คุณต้องการลบเอกสาร "${doc_no}" ใช่หรือไม่?\n(ข้อมูลจะไม่สามารถกู้คืนได้)`, 'ใช่, ลบเลย').then((result) => {
        if (result.isConfirmed) {
            window.location.href = `documents.php?delete=${id}`;
        }
    });
}
</script>

<?php include_once 'includes/footer.php'; ?>
