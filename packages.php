<?php
require_once 'db.php';

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM packages WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: packages.php?msg=deleted");
        exit();
    } catch (\PDOException $e) {
        $error = "ไม่สามารถลบข้อมูลได้";
    }
}

// Fetch Packages and their total items
$allowed_sort_cols = ['code', 'name', 'item_count'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort_cols) ? $_GET['sort'] : 'id';
$order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

$sql = "SELECT p.*, (SELECT COUNT(*) FROM package_items pi WHERE pi.package_id = p.id) as item_count 
        FROM packages p ORDER BY $sort $order";
$packages = $pdo->query($sql)->fetchAll();

include_once 'includes/header.php';
?>

<div class="page-header">
    <h2 class="page-title"><i class="bi bi-collection"></i> จัดการแพ็กเกจ</h2>
    <div>
        <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importPackageModal">
            <i class="bi bi-file-earmark-arrow-up"></i> Import CSV
        </button>
        <a href="package_form.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> สร้างแพ็กเกจใหม่
        </a>
    </div>
</div>

<?php if (isset($error)): ?>
<script>document.addEventListener('DOMContentLoaded',()=>swalError(<?= json_encode($error) ?>));</script>
<?php endif; ?>

<?php if (isset($_GET['msg'])): ?>
<script>document.addEventListener('DOMContentLoaded',()=>{
<?php if ($_GET['msg'] == 'imported'): ?>
    swalSuccess('นำเข้าแพ็กเกจสำเร็จ บันทึก: <?= (int)($_GET['success'] ?? 0) ?> แถว, ข้าม: <?= (int)($_GET['skipped'] ?? 0) ?>', 3500);
<?php elseif ($_GET['msg'] == 'import_error'): ?>
    swalError('เกิดข้อผิดพลาดในการนำเข้าไฟล์ หรือรูปแบบไฟล์ไม่ถูกต้อง');
<?php else: ?>
    swalSuccess('ทำรายการสำเร็จ');
<?php endif; ?>
});</script>
<?php endif; ?>

<div class="card card-custom">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-custom table-hover mb-0">
                <thead>
                    <?php 
                    function sortLinkPkg($colName, $label, $currentSort, $currentOrder, $alignClass = '') {
                        $newOrder = ($currentSort === $colName && $currentOrder === 'ASC') ? 'desc' : 'asc';
                        $icon = '<i class="bi bi-arrow-down-up text-muted opacity-25 ms-1"></i>';
                        if ($currentSort === $colName) {
                            $icon = $currentOrder === 'ASC' ? '<i class="bi bi-caret-up-fill ms-1 text-primary"></i>' : '<i class="bi bi-caret-down-fill ms-1 text-primary"></i>';
                        }
                        return "<a href='packages.php?sort=$colName&order=$newOrder' class='text-decoration-none text-dark d-flex align-items-center $alignClass'>$label $icon</a>";
                    }
                    ?>
                    <tr>
                        <th class="ps-3" width="15%"><?= sortLinkPkg('code', 'รหัสแพ็กเกจ', $sort, $order) ?></th>
                        <th width="25%"><?= sortLinkPkg('name', 'ชื่อแพ็กเกจ', $sort, $order) ?></th>
                        <th width="40%">รายละเอียด</th>
                        <th class="text-center" width="10%">จำนวนสินค้า</th>
                        <th class="text-center pe-3" width="10%">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($packages) > 0): ?>
                        <?php foreach ($packages as $p): ?>
                        <tr>
                            <td class="ps-3 fw-bold text-primary"><?= htmlspecialchars($p['code']) ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($p['name']) ?></td>
                            <td><?= htmlspecialchars($p['description']) ?></td>
                            <td class="text-center">
                                <span class="badge bg-secondary"><?= $p['item_count'] ?> รายการ</span>
                            </td>
                            <td class="text-center pe-3">
                                <a href="package_form.php?id=<?= $p['id'] ?>" class="btn btn-outline-primary btn-table-action" title="แก้ไข">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-outline-danger btn-table-action" title="ลบ"
                                   onclick="confirmDelete(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">ยังไม่มีข้อมูลแพ็กเกจ</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importPackageModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="import_packages.php" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title">นำเข้าแพ็กเกจจากไฟล์ CSV</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info py-2" style="font-size: 0.9rem;">
            <strong>รูปแบบไฟล์ (มี Header):</strong><br>
            คอลัมน์ A: รหัสแพ็กเกจ (Package Code)<br>
            คอลัมน์ B: ชื่อแพ็กเกจ (Package Name)<br>
            คอลัมน์ C: รายละเอียด (Description)<br>
            คอลัมน์ D: รหัสสินค้า (Product Code) <em>(ต้องมีสินค้านี้ในระบบแล้ว)</em><br>
            คอลัมน์ E: จำนวน (Quantity)<br><br>
            <em>* หาก 1 แพ็กเกจมีหลายสินค้า ให้ใส่รหัสแพ็กเกจซ้ำในบรรทัดถัดไป แล้วเปลี่ยนรหัสสินค้า</em>
            <div class="mt-3 text-center">
                <a href="assets/samples/sample_packages.csv" class="btn btn-sm btn-outline-primary" download>
                    <i class="bi bi-download"></i> ดาวน์โหลดไฟล์ตัวอย่าง CSV
                </a>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">เลือกไฟล์ CSV <span class="text-danger">*</span></label>
            <input type="file" class="form-control" name="csv_file" accept=".csv" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
        <button type="submit" class="btn btn-success">นำเข้าข้อมูล</button>
      </div>
    </form>
  </div>
</div>

<script>
function confirmDelete(id, name) {
    swalConfirm('ยืนยันการลบ', `คุณต้องการลบแพ็กเกจ "${name}" ใช่หรือไม่?`, 'ใช่, ลบเลย').then((result) => {
        if (result.isConfirmed) {
            window.location.href = `packages.php?delete=${id}`;
        }
    });
}
</script>

<?php include_once 'includes/footer.php'; ?>
