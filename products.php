<?php
require_once 'db.php';

// Handle Delete or Form Submit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF Token");
    }

    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = $_POST['delete_id'] ?? '';
        try {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: products.php?msg=deleted");
            exit();
        } catch (\PDOException $e) {
            $error = "ไม่สามารถลบข้อมูลได้ อาจมีการใช้งานสินค้านี้อยู่";
        }
    } else {
        // Handle Form Submit (Add / Edit)
        $id = $_POST['id'] ?? '';
        $code = $_POST['code'];
        $name = $_POST['name'];
        $unit = $_POST['unit'];
        $unit_price = $_POST['unit_price'];

        if ($id) {
            // Update
            $stmt = $pdo->prepare("UPDATE products SET code=?, name=?, unit=?, unit_price=? WHERE id=?");
            $stmt->execute([$code, $name, $unit, $unit_price, $id]);
            header("Location: products.php?msg=updated");
            exit();
        } else {
            // Insert
            try {
                $stmt = $pdo->prepare("INSERT INTO products (code, name, unit, unit_price) VALUES (?, ?, ?, ?)");
                $stmt->execute([$code, $name, $unit, $unit_price]);
                header("Location: products.php?msg=added");
                exit();
            } catch (\PDOException $e) {
                $error = "รหัสสินค้าซ้ำ หรือเกิดข้อผิดพลาด: " . $e->getMessage();
            }
        }
    }
}

// Fetch Products
$allowed_sort_cols = ['code', 'name', 'unit_price'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort_cols) ? $_GET['sort'] : 'id';
$order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

$products = $pdo->query("SELECT * FROM products ORDER BY $sort $order")->fetchAll();

include_once 'includes/header.php';
?>

<div class="page-header">
    <h2 class="page-title"><i class="bi bi-box-seam"></i> จัดการสินค้า</h2>
    <div>
        <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importProductModal">
            <i class="bi bi-file-earmark-arrow-up"></i> Import CSV
        </button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal" onclick="clearForm()">
            <i class="bi bi-plus-circle"></i> เพิ่มสินค้าใหม่
        </button>
    </div>
</div>

<?php if (isset($error)): ?>
<script>document.addEventListener('DOMContentLoaded',()=>swalError(<?= json_encode($error) ?>));</script>
<?php endif; ?>

<?php if (isset($_GET['msg'])): ?>
<script>document.addEventListener('DOMContentLoaded',()=>{
<?php if ($_GET['msg'] == 'imported'): ?>
    swalSuccess('นำเข้าข้อมูลสินค้าสำเร็จ สำเร็จ: <?= (int)($_GET['success'] ?? 0) ?> ราย, ข้าม: <?= (int)($_GET['skipped'] ?? 0) ?> ราย', 3500);
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
                    function sortLink($colName, $label, $currentSort, $currentOrder, $alignClass = '') {
                        $newOrder = ($currentSort === $colName && $currentOrder === 'ASC') ? 'desc' : 'asc';
                        $icon = '<i class="bi bi-arrow-down-up text-muted opacity-25 ms-1"></i>';
                        if ($currentSort === $colName) {
                            $icon = $currentOrder === 'ASC' ? '<i class="bi bi-caret-up-fill ms-1 text-primary"></i>' : '<i class="bi bi-caret-down-fill ms-1 text-primary"></i>';
                        }
                        return "<a href='products.php?sort=$colName&order=$newOrder' class='text-decoration-none text-dark d-flex align-items-center $alignClass'>$label $icon</a>";
                    }
                    ?>
                    <tr>
                        <th class="ps-3" width="20%"><?= sortLink('code', 'รหัสสินค้า', $sort, $order) ?></th>
                        <th width="40%"><?= sortLink('name', 'ชื่อสินค้า', $sort, $order) ?></th>
                        <th width="15%">หน่วยนับ</th>
                        <th width="15%"><?= sortLink('unit_price', 'ราคาต่อหน่วย', $sort, $order, 'justify-content-end') ?></th>
                        <th class="text-center pe-3" width="10%">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($products) > 0): ?>
                        <?php foreach ($products as $p): ?>
                        <tr>
                            <td class="ps-3 fw-bold"><?= htmlspecialchars($p['code']) ?></td>
                            <td><?= htmlspecialchars($p['name']) ?></td>
                            <td><?= htmlspecialchars($p['unit']) ?></td>
                            <td class="text-end"><?= number_format($p['unit_price'], 2) ?></td>
                            <td class="text-center pe-3">
                                <button class="btn btn-outline-primary btn-table-action" 
                                        onclick="editProduct(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') ?>)" title="แก้ไข">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-table-action" 
                                        onclick="confirmDelete(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>')" title="ลบ">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">ยังไม่มีข้อมูลสินค้า</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="products.php">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">เพิ่มสินค้าใหม่</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
        <input type="hidden" name="id" id="productId">
        <div class="mb-3">
            <label class="form-label">รหัสสินค้า <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="code" id="productCode" required>
        </div>
        <div class="mb-3">
            <label class="form-label">ชื่อสินค้า <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="name" id="productName" required>
        </div>
        <div class="mb-3">
            <label class="form-label">หน่วยนับ <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="unit" id="productUnit" required>
        </div>
        <div class="mb-3">
            <label class="form-label">ราคาต่อหน่วย <span class="text-danger">*</span></label>
            <input type="number" step="0.01" class="form-control" name="unit_price" id="productPrice" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
        <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
      </div>
    </form>
  </div>
</div>

<!-- Import Modal -->
<div class="modal fade" id="importProductModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="import_products.php" enctype="multipart/form-data">
      <div class="modal-header">
        <h5 class="modal-title">นำเข้าสินค้าจากไฟล์ CSV</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
        <div class="alert alert-info py-2">
            <strong>รูปแบบไฟล์ (มี Header):</strong><br>
            คอลัมน์ A: รหัสสินค้า (Code)<br>
            คอลัมน์ B: ชื่อสินค้า (Name)<br>
            คอลัมน์ C: หน่วยนับ (Unit)<br>
            คอลัมน์ D: ราคาต่อหน่วย (Price)
            <div class="mt-2 text-center">
                <a href="assets/samples/sample_products.csv" class="btn btn-sm btn-outline-primary" download>
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
function clearForm() {
    document.getElementById('modalTitle').innerText = 'เพิ่มสินค้าใหม่';
    document.getElementById('productId').value = '';
    document.getElementById('productCode').value = '';
    document.getElementById('productName').value = '';
    document.getElementById('productUnit').value = '';
    document.getElementById('productPrice').value = '';
}

function confirmDelete(id, name) {
    swalConfirm('ยืนยันการลบ', `คุณต้องการลบสินค้า "${name}" ใช่หรือไม่?`, 'ใช่, ลบเลย').then((result) => {
        if (result.isConfirmed) {
            submitPostDelete('products.php', id);
        }
    });
}

function editProduct(product) {
    document.getElementById('modalTitle').innerText = 'แก้ไขสินค้า';
    document.getElementById('productId').value = product.id;
    document.getElementById('productCode').value = product.code;
    document.getElementById('productName').value = product.name;
    document.getElementById('productUnit').value = product.unit;
    document.getElementById('productPrice').value = product.unit_price;
    
    var myModal = new bootstrap.Modal(document.getElementById('productModal'));
    myModal.show();
}
</script>

<?php include_once 'includes/footer.php'; ?>
