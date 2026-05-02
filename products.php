<?php
require_once 'db.php';

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: products.php?msg=deleted");
        exit();
    } catch (\PDOException $e) {
        $error = "ไม่สามารถลบข้อมูลได้ อาจมีการใช้งานสินค้านี้อยู่";
    }
}

// Handle Form Submit (Add / Edit)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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

// Fetch Products
$products = $pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll();

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
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (isset($_GET['msg'])): ?>
    <?php if ($_GET['msg'] == 'imported'): ?>
        <div class="alert alert-success">นำเข้าข้อมูลสินค้าสำเร็จ (นำเข้าสำเร็จ: <?= (int)($_GET['success'] ?? 0) ?>, ข้าม: <?= (int)($_GET['skipped'] ?? 0) ?>)</div>
    <?php elseif ($_GET['msg'] == 'import_error'): ?>
        <div class="alert alert-danger">เกิดข้อผิดพลาดในการนำเข้าไฟล์ หรือรูปแบบไฟล์ไม่ถูกต้อง</div>
    <?php else: ?>
        <div class="alert alert-success">ทำรายการสำเร็จ</div>
    <?php endif; ?>
<?php endif; ?>

<div class="card card-custom">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-custom table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">รหัสสินค้า</th>
                        <th>ชื่อสินค้า</th>
                        <th>หน่วยนับ</th>
                        <th class="text-end">ราคาต่อหน่วย</th>
                        <th class="text-center pe-4">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($products) > 0): ?>
                        <?php foreach ($products as $p): ?>
                        <tr>
                            <td class="ps-4 fw-bold"><?= htmlspecialchars($p['code']) ?></td>
                            <td><?= htmlspecialchars($p['name']) ?></td>
                            <td><?= htmlspecialchars($p['unit']) ?></td>
                            <td class="text-end"><?= number_format($p['unit_price'], 2) ?></td>
                            <td class="text-center pe-4">
                                <button class="btn btn-sm btn-outline-primary" 
                                        onclick='editProduct(<?= json_encode($p) ?>)'>
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <a href="products.php?delete=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger" 
                                   onclick="return confirm('คุณต้องการลบสินค้านี้ใช่หรือไม่?');">
                                    <i class="bi bi-trash"></i>
                                </a>
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
        <div class="alert alert-info py-2">
            <strong>รูปแบบไฟล์ (มี Header):</strong><br>
            คอลัมน์ A: รหัสสินค้า (Code)<br>
            คอลัมน์ B: ชื่อสินค้า (Name)<br>
            คอลัมน์ C: หน่วยนับ (Unit)<br>
            คอลัมน์ D: ราคาต่อหน่วย (Price)
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
